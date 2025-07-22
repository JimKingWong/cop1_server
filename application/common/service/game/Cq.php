<?php

namespace app\common\service\game;

use app\common\model\game\Platform;
use app\common\model\User;
use app\common\service\Base;
use app\common\service\util\Es;
use fast\Http;
use think\Cache;
use think\Db;

class Cq extends Base
{
    /**
     * 配置
     */
    protected $config;

    /**
     * Es表名
     */
    protected $gameRecord = 'cq_game_record';
    
    /**
     * 厂商
     */
    protected $platform = 'cq';

    protected $gamehall = null;
    protected $token = null;
    protected $gameUrl = null;
    protected $agent = null;
    protected $curency = null;
    protected $model = null;

    public function __construct()
    {
        parent::__construct();

        $platform = Platform::where('code', $this->platform)->cache(true, 86400)->find();

        // \think\Log::error($platform, 'cq9_construct');
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

        $this->token = $this->config['token'];
        $this->gameUrl = $this->config['gameUrl'];
        $this->gamehall = $this->config['gamehall'];
        $this->agent = $this->config['agent'];

        $langArr = [
            'pt' => 'BRL',
            'spa' => 'COP'
        ];

        // 币种
        $this->curency = $langArr[$this->language] ?? 'BRL';

        $this->model = new \app\common\model\game\Cq();
    }

    /**
     * 校验请求
     */
    private function checkInfo()
    {
        $wtoken = $this->request->header('wtoken');
        if($wtoken != $this->token){
            return $this->returnJson('Success', 0, false);
        }

        // 获取账号
        $account = $this->request->param('account');
        list($agent, $user_id) = explode('_', $account);

        // 验证代理
        if($agent != $this->agent){
            return $this->returnJson('Success', 0, false);
        }

        $user = User::where('id', $user_id)->cache(true, 86400)->find();
        if(empty($user)){
            return $this->returnJson('Success', 0, false);
        }
        return $user;
    }

    /**
     * 检查玩家帐号
     * /player/check/:account
     */
    public function check()
    {
        \think\Log::error($this->request->param('account'), 'cq9_check');
        $this->checkInfo();

        return $this->returnJson('Success', 0, true);
    }

    /**
     * 取得玩家錢包餘額
     * /transaction/balance/:account
     */
    public function balance()
    {
        $user = $this->checkInfo();
        \think\Log::error($user, 'cq9_balance');
        $data = [
            'balance'   => floatval($user->money),
            'currency'  => $this->curency,
        ];
        return $this->returnJson('Success', 0, $data);
    }

    /**
     * 投注
     * /transaction/game/bet
     */
    public function bet()
    {
        $user = $this->checkInfo();

        $params = $this->request->post();
        \think\Log::error($params, 'cq9_bet');

        // 游戏id
        $game_id = $params['gamecode'];
        $game = $this->model->where('game_id', $game_id)->find();
        if(empty($game)){
            return $this->returnJson('Success', 0, false);
        }

        // 下注金额
        $bet_amount = 0;
        // 交易码
        $transaction_id = $params['mtcode'];
        $win_amount = 0;
        $transfer_amount = -$params['amount'];

        $es = new Es();
        Db::startTrans();
        try{
            $user = User::lock(true)->find($user['id']);

            $bet_money_remain = bcsub($user->money, $bet_amount, 2);
            if($bet_money_remain < 0){
                Db::rollback();
                // 余额不足
                return $this->returnJson('Success', 1005, null);
            }

            // 幂等
            $check_key = $user['id'] . 'bet_' . $transaction_id;
            
            $check = Cache::store('redis')->get($check_key);
            if(!$check){
                $tradeInfo = [
                    'transaction_id'    => $transaction_id,
                    'before'            => $user->money,
                    'after'             => $bet_money_remain,
                    'money'             => $bet_amount,
                ];
                // 幂等 第一次进来, 将余额存起来
                Cache::store('redis')->set($check_key, $tradeInfo, 86400);
            }else{
                Db::rollback();

                // 未结算, 又不是第一次进来, 将上一次的余额返回, 如结算了需清理此单缓存
                $data = [
                    'balance'   => floatval($check['after']),
                    'currency'  => $this->curency,
                ];
                return $this->returnJson('Success', 0, $data);
            }

            if($transfer_amount != 0){
                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transaction_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 0,
                ];
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

                //插入用户资金流水记录
                $moneyLogRecordParam['body'] = [
                    'user_id'               => $user['id'],
                    'money'                 => $bet_amount,
                    'before'                => $user['money'],
                    'after'                 => $bet_money_remain,
                    'memo'                  => 'CQ9投注',
                    'transaction_id'        => $transaction_id,
                    'admin_id'              => $user['admin_id'],
                    'createtime'            => time(),
                ];

                $moneyLogRecordParam['index'] = 'user_money_log';
                $moneyLogRecordParam['type'] = '_doc';
                $es->add($moneyLogRecordParam);

                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->bet_count += 1; // 下注次数
                $user->userdata->save();

                $user->money = $bet_money_remain;
                $user->save();
            }

            Db::commit();
        }catch(\Exception $e){
            \think\Log::error($e->getMessage(), 'cq9_bet');
            Db::rollback();
            return $this->returnJson('Success', 0, false);
        }

        $data = [
            'balance'   => floatval($bet_money_remain),
            'currency'  => $this->curency,
        ];
        return $this->returnJson('Success', 0, $data);
    }

    /**
     * 派彩
     */
    public function endround()
    {
        $user = $this->checkInfo();

        $params = $this->request->post();
        \think\Log::error($params, 'cq9_endround');

        // 游戏id
        $game_id = $params['gamecode'];
        $game = $this->model->where('game_id', $game_id)->find();
        if(empty($game)){
            return $this->returnJson('Success', 0, false);
        }

        $es = new Es();

        // 玩家損益金額 (從玩家角度來計算玩家損益)：
        // 【Slot/老虎機】、【Fish/漁機】、【Arcade/街機】、【Sports/Lotto/體彩】：
        // Endround amount - Bet amount = profit
        // Rollin win - Rollin bet = profit
        // Wins amount - Batch Bets amount = profit
        // 【Table/牌桌】、【live/真人視訊】：
        // Rollin win - Rollin rake - Rollin roomfee = profit
        // 代理損益金額 (從代理角度來計算代理損益)：
        // 【Slot/老虎機】、【Fish/漁機】、【Arcade/街機】、【Sports/Lotto/體彩】：
        // Bet amount - Endround amount = profit
        // Rollin bet - Rollin win = profit
        // Batch Bets amount - Wins amount = profit
        // 【Table/牌桌】、【live/真人視訊】：
        // Rollin rake - Rollin win + Rollin roomfee = profit

        // 当前采用用户角度计算
        // 下注金额
        $bet_amount = 0;
        // 交易码
        $transaction_id = '';

        // 派彩金额
        $win_amount = 0;
        // 输赢金额
        $transfer_amount = 0;

        // 有效投注
        $valid_bet = 0;

        // 缓存key
        $check_key = $user['id'] . 'bet_';
        // 派彩数据
        $betParams = $params['data'];

        Db::startTrans();
        try{
            
            foreach($betParams as $v){
                $betData = Cache::store('redis')->get($check_key . $v['mtcode']);
                if($betData){
                    // 下注金额
                    $bet_amount = $betData['amount'];

                    // 派彩金额
                    $win_amount = $v['amount'];
                    // 交易码
                    $transaction_id = $v['mtcode'];
                    
                    // 输赢金额
                    $transfer_amount = $win_amount - $bet_amount;

                    // 有效投注
                    $valid_bet = $v['validbet'];
                    
                }
            }
        }catch(\Exception $e){
            \think\Log::error($e->getMessage(), 'cq9_endround');
            Db::rollback();
            return $this->returnJson('Success', 0, false);
        }
       
    }

     /**
     * 转出(部分)
     * /transaction/game/rollout
     */
    public function rollout()
    {
        $user = $this->checkInfo();
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_rollout');

        // 操作金额
        $amount = $params['amount'];
        $roundid = $params['roundid'];
        $mtcode = $params['mtcode'];
        
        $es = new Es();
        Db::startTrans();
        try{
            $user = User::lock(true)->find($user['id']);

            $after = bcsub($user->money, $amount, 2);
            if($after < 0){
                Db::rollback();
                // 余额不足
                return $this->returnJson('Success', 1005, null);
            }

            $roundid_key = $user['id'] . 'roundid_' . $params['roundid'];
            $roundInfo = Cache::store('redis')->get($roundid_key, 6 * 60 * 60);

            if(empty($roundInfo)){
                $tradeInfo = [
                    'roundid'            => $roundid,
                    'mtcode'             => $mtcode,
                    'amount'             => $amount,
                    'before'             => $user->money,
                    'after'              => $after,
                    'currency'           => $this->curency,
                    'flag'               => 3,
                    'status'             => 0,
                    'user_id'            => $user['id'],
                ];
                Cache::store('redis')->set($roundid_key, $tradeInfo);
            }else{
                Db::rollback();

                $data = [
                    'balance'  => floatval($after),
                    'currency' => $this->curency,
                ];
                return $this->returnJson('Success', 0, $data);
            }

            $moneyLogRecordParam['body'] = array(
                'user_id'       => $user['id'],
                'money'         => $amount,
                'before'        => $user->money,
                'after'         => $after,
                'memo'          => '转出部分金额',
                'transaction_id'=> $roundid . ' ' . $mtcode,
                'admin_id'      => $user['admin_id'],
                'createtime'    => time(),
            );
            $moneyLogRecordParam['index'] = 'user_money_log';
            $moneyLogRecordParam['type'] = '_doc';
            $es->add($moneyLogRecordParam);

            $user->money = $after;
            $user->save();
            
        }catch(\Exception $e){
            \think\Log::error($e->getMessage(), 'cq9_error_rollout');
            Db::rollback();
            return $this->returnJson('Success', 0, false);
        }
    }

    /**
     * 转出(全部)
     * /transaction/game/takeall
     */
    public function takeall()
    {
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_takeall');
    }

    /**
     * 转回(全部)
     * /transaction/game/rollin
     */
    public function rollin()
    {
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_rollin');
    }

    /**
     * 针对已完成订单做扣款 例如，游戏逻辑错误进行修正
     * /transaction/game/debit
     */
    public function debit()
    {
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_debit');
    }

    /**
     * 针对'已完成'的订单做补款。 例如，游戏逻辑错误进行修正
     * /transaction/game/credit
     */
    public function credit()
    {
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_credit');
    }

    /**
     * 活动奖励 如有参与台方举办之活动，活动奖励通过此支API派发给玩家
     */
    public function payoff()
    {
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_payoff');
    }

    /**
     * 退款下注行为（bet/rollout/takeall） 的金额
     * /transaction/game/refund
     */
    public function refund()
    {
        $user = $this->checkInfo();
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_refund');

        // 缓存key
        $check_bet_key = $user['id'] . 'bet_' . $params['mtcode'];
        $check_rollout_key = $user['id'] . 'rollout_' . $params['mtcode'];
        $check_takeall_key = $user['id'] . 'takeall_' . $params['mtcode'];


    }

    /**
     * 玩家退出
     * /gameboy/player/logout
     */
    public function logout()
    {
        $params = $this->request->post();
        \think\Log::error($params, 'cq9_logout');
        
        $api_url = $this->gameUrl . '/gameboy/player/logout/';

        $header = $this->setHeader();

        $data = [
            'account'   => $this->agent . '_' . $this->auth->id,
        ];

        $res = Http::get($api_url, http_build_query($data), $header);
        $res = json_decode($res, true);
        // dd($res['data']);

        if(!isset($res['status'])){
            $this->error(__('请求失败'));
        }

        if($res['status']['code'] != 0){
            $this->error($res['status']['message']);
        }

        $this->Success(__( '注销成功'));
    }
    
     /**
     * 游戏列表
     */
    public function getNewGame()
    {
        $api_url = $this->gameUrl . '/gameboy/game/list/' . $this->gamehall;

        $header = $this->setHeader();

        $res = Http::get($api_url, [], $header);
        $res = json_decode($res, true);
        // dd($res['data']);

        $cq = $this->model->column('id', 'game_id');

        $data = [];
        foreach($res['data'] as $k => $v){
            if($v['gamecode'] > 0 && !isset($cq[$v['gamecode']])){
                $data[] = [
                    'game_id'   => $v['gamecode'],
                    'game_name' => $v['gamename'],
                    'image'     => '',
                    'status'    => $v['status'] ? 1 : 0,
                    'is_works'  => $v['maintain'] ? 1 : 0,
                    'thumb'     => $v['gametype'],
                ];
            }
        }
        if(!empty($data)){
            $this->model->saveAll($data);
        }

        $retval = [
            'list' => $data ?: $res['data'],
            'total' => count($data) ?: count($res['data']),
        ];
        $this->Success(__( '请求成功'), $retval);
    }
    
    /**
     * 请求头设置
     * $auth: Authorization 或 wtoken
     * $type: x-www-form-urlencoded 或 json
     */
    public function setHeader($auth = 'Authorization', $type = 'x-www-form-urlencoded')
    {
        $header = [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/' . $type,
                $auth . ': ' . $this->token
            ]
        ];

        return $header;
    }

    /**
     * 统一返回格式
     */
    public function returnJson($msg, $code = 0, $data = false)
    {
        $ret = [
            'data'          => $data,
            'status'        => [
                'code'      => (string)$code,
                'message'   => $msg,
                'datetime'  => date('Y-m-d\TH:i:s.vvvP'),
            ]
        ];

        return json($ret);
    }
    
}