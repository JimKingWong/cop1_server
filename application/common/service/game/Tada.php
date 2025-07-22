<?php

namespace app\common\service\game;

use app\common\model\game\Platform;
use app\common\service\Base;
use app\common\service\util\Es;
use app\common\service\util\Notice;
use think\Db;

class Tada extends Base
{
    /**
     * 配置
     */
    protected $config;

    /**
     * Es表名
     */
    protected $gameRecord = 'tada_game_record';
    
    /**
     * 厂商
     */
    protected $platform = 'tada';

    protected $agentPre = null;
    protected $agentId = null;
    protected $agentKey = null;
    protected $gameUrl = null;
    
    public function __construct()
    {
        parent::__construct();

        $platform = Platform::where('code', $this->platform)->cache(true, 86400)->find();

        $this->config = $platform->config;
        
         if(empty($this->config)){
            $this->error($this->platform . __( '游戏配置不存在'));
        }

        if($platform->status != 1){
           $this->error($this->platform . __('游戏未开启'));
        }

        foreach($this->config as $k => $v){
            if($v == ''){
                $this->error($this->platform . __('游戏配置不完整, 缺少%s配置', $k));
            }
        }

        $this->agentId = $this->config['agentId'];
        $this->agentKey = $this->config['agentKey'];
        $this->gameUrl = $this->config['gameUrl'];
        
    }

    /**
     * 获取游戏链接
     */
    public function getLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        $user = $this->auth->getUser();
        
        $randomstr_1 = "jkluio";
        $randomstr_2 = "poihy7";
        
        $dateStr = date('ymj');
        $agentId = $this->agentId;
        $agentKey = $this->agentKey;
        $keyG = md5($dateStr. $agentId. $agentKey);

        $lang = "pt-BR";
        $params= "Token=". $this->agentPre. $user['id'] . "&GameId=" . $game['id'] . "&Lang=". $lang. "&AgentId=" . $agentId;
        
        $key = $randomstr_1 . md5($params . $keyG) . $randomstr_2; 
       
        $url = "https://wb-api-2.tadagaming.com/api1/singleWallet/LoginWithoutRedirect?" . $params . "&Key=" . $key;
       
        $res = file_get_contents($url);
        $res = json_decode($res, true);
       
        if($res['status'] !=  0){
            $this->error(__('获取失败'));
        }
        
        $retval = [
            'game_url' => $res['Data'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取用户信息
     */
    public function auth()
    {
        $param = $this->request->param();
        $param['token'] = str_replace($this->agentPre, "", $param['token']);
        $user = \app\common\model\User::where('id', $param['token'])->find();
        if(!$user){
            $data = [
                "errorCode"=> 5,
                "message" => "No such user found"
            ];
        }else{
            $data = [
                "username"=> $this->agentPre.$user['nickname'],
                "currency"=> "BRL",
                "balance"=> $user['money'],
                "errorCode"=> 0
            ];
        }
        return json_encode($data);
    }

    /**
     * 下注
     */
    public function bet()
    {
        $es = new Es();
        
        $param = $this->request->param();
        // \think\Log::record($param, 'tada_bet');
        //检测用户信息
        $param['token'] = str_replace($this->agentPre, "", $param['token']);
        $user = \app\common\model\User::where('id', $param['token'])->find();

        if(!$user){
            return json_encode([
                "errorCode"     => 5,
                "message"       => "No such user found"
            ]);
        }
        
        // 检测货币
        if($param['currency'] != 'BRL'){
            return json_encode([
                "errorCode"     => 3,
                "message"       => "currency must be BRL"
            ]);
        }

        // 盈利金额 = 奖金 - 下注金额
        $profit = $param['winloseAmount'] - $param['betAmount'];
        $win_amount = $param['winloseAmount'];
        $transfer_amount = $profit;
        $bet_amount = $param['betAmount'];
        $transaction_id = $param['round'];

        // 游戏
        $game = db('game_' . $this->platform)->where('game_id', $param['game'])->cache(true)->find();
        
        // 税费计算
        $tax = $this->config['tax'];
        $tax_fee =  $win_amount * $tax;
        
        // 开始处理下注逻辑 金额问题
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);

            //计算输赢
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                Db::rollback();
                return json_encode([
                    "errorCode"     => 2,
                    "message"       => "Not enough balance"
                ]);
            }

            //扣税
            $money = bcsub($money, $tax_fee, 2);
            $transfer_amount = bcsub($transfer_amount, $tax_fee, 2);

            // 添加ES记录
            $betInfoArr = [
                'transaction_id'    => $transaction_id,
                'bet_amount'        => $bet_amount,
                'win_amount'        => $win_amount,
                'transfer_amount'   => $transfer_amount,
                'is_fake'           => 0,
            ];
            $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
                
            // 插入用户资金流水记录
            $moneyLogRecordParam['body'] = array(
                'user_id'           => $user['id'],
                'money'             => $profit,
                'before'            => $user['money'],
                'after'             => $money,
                'memo'              => 'TADA投注付彩',
                'transaction_id'    => $param['round'] ,
                'admin_id'          => $user['admin_id'],
                'createtime'        => time(),

            );
            $moneyLogRecordParam['index'] = $this->esMoneyLog;
            $moneyLogRecordParam['type'] = '_doc';
            $es->add($moneyLogRecordParam);
            
            // 保存用户的余额
            $user->userdata->total_bet = bcadd($param['betAmount'] * $game['bet_rate'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcadd($param['betAmount'] * $game['bet_rate'], $user->userdata->today_bet, 2);
            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();
            
            Db::commit();
        }catch(\Exception $e){
            \think\Log::record($e->getMessage(), 'TADA_Error');
            Db::rollback();
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);
        
        $data = [
            "errorCode"     => 0,
            "message"       => "Success",
            "username"      => $this->agentPre . $user['nickname'],
            "currency"      => "BRL",
            "balance"       => $user['money'],
        ];
        return json_encode($data);
    }

    /**
     * 牌局型注单 sessionBet
     */
    public function sessionBet()
    {
        $es = new Es();
        $param = $this->request->param();
        // \think\Log::record($param, 'TADA_sessionBet');

        // 检测用户信息
        $param['token'] = str_replace($this->agentPre, "", $param['token']);

        $user = \app\common\model\User::where('id', $param['token'])->find();
        if(!$user){
            return json_encode([
                "errorCode"     => 5,
                "message"       => "No such user found"
            ]);
        }

        // 检测货币
        if($param['currency'] != 'BRL'){
            return json_encode([
                "errorCode"     => 3,
                "message"       => "currency must be BRL"
            ]);
        }
        
        // 游戏
        $game = db('game_' . $this->platform)->where('game_id', $param['game'])->cache(true)->find();

        // 保证金
        $preserve = $param['preserve'];
        $bet_amount = $param['betAmount'];
        $win_amount = $param['winloseAmount'];
        $transfer_amount = bcsub($win_amount, $bet_amount, 2);
        $transaction_id = $param['round'];
        
        // 税费计算
        $tax = $this->config['tax'];
        $tax_fee =  $win_amount * $tax;

        // 开始处理下注逻辑 金额问题
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            // 下注
            if($param['type'] == 1){
                // 下注: 下注后余额 = 下注前余额 – betAmount – preserve
                $money = $user->money - $preserve - $bet_amount;
                if($money < 0){
                    Db::rollback();
                    return json_encode([
                        "errorCode"     => 2,
                        "message"       => "Not enough balance"
                    ]);
                }

                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transaction_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 0,
                ];
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

                //保存用户的余额
                $user['money'] = $money;
                $user['total_profit'] = bcadd($user->total_profit, $transfer_amount, 2);
                $user['today_profit'] = bcadd($user->today_profit, $transfer_amount, 2);

                $user->money = $money;

                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);

            }

            // 结算
            if ($param['type'] == 2){
                // 结算: 结算后余额 = 结算前余额 – betAmount + preserve + winloseAmount
                $money = $user->money - $bet_amount + $preserve + $win_amount;

                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transaction_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 0,
                ];
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
                
                //扣税
                $money = bcsub($money, $tax_fee, 2);
                $transfer_amount = bcsub($transfer_amount, $tax_fee, 2);
                
                //有效投注流水
                $turnover = $param['turnover'];

                //插入用户资金流水记录
                $moneyLogRecordParam['body'] = [
                    'user_id'           => $user['id'],
                    'money'             => bcadd($win_amount,$preserve,2),
                    'before'            => $user['money'],
                    'after'             => $money,
                    'memo'              => 'TADA棋牌结算',
                    'transaction_id'    => $param['round'] ,
                    'admin_id'          => $user['admin_id'],
                    'createtime'        => time(),
                ];
                $moneyLogRecordParam['index'] = $this->esMoneyLog;
                $moneyLogRecordParam['type'] = '_doc';
                $es->add($moneyLogRecordParam);

                //大奖通知
                Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);

                $user->userdata->total_bet = bcadd($user->userdata->total_bet, $turnover * $game['bet_rate'], 2);
                $user->userdata->today_bet = bcadd($user->userdata->today_bet, $turnover * $game['bet_rate'], 2);
                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);

                $user->money = bcadd($money, 0, 2);
            }
            // 用户数据
            $user->userdata->save();

            // 用户信息
            $user->save();
            Db::commit();
        }catch(\Exception $e){
            error_log($e->getMessage(),'tada_poker_error');
            Db::rollback();
        }

        $data = [
            "errorCode"     => 0,
            "message"       => "Success",
            "username"      => $this->agentPre.$user['nickname'],
            "currency"      => "BRL",
            "balance"       => $user['money'],
        ];
        return json_encode($data);
    }
}