<?php

namespace app\common\service\game;

use app\common\model\game\Platform;
use app\common\service\Base;
use app\common\service\util\Es;
use app\common\service\util\Notice;
use app\common\service\util\Sign;
use fast\Http;
use think\Cache;
use think\Db;

class Pgnew3 extends Base
{
    /**
     * 配置
     */
    protected $config;

    /**
     * Es表名
     */
    protected $gameRecord = 'pg_game_record';
    
    /**
     * 厂商
     */
    protected $platform = 'pgnew3';

    protected $operator_token = null;
    protected $secret_key = null;
    protected $gameUrl = null;
    protected $model = null;
    
    public function __construct()
    {
        parent::__construct();

        $platform = Platform::where('code', $this->platform)->find();

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

        $this->operator_token = $this->config['agentId'];
        $this->secret_key = $this->config['secret_key'];
        $this->gameUrl = $this->config['gameUrl'];
    }

    /**
     * @return void
     * 令牌验证api
     */
    public function VerifySession() 
    {
        $param = $this->request->param();

        if($param['operator_token'] != $this->operator_token || $param['secret_key'] != $this->secret_key){
            $data = [
                "data"      => null,
                "error"     => 1034
            ];
            return json_encode($data);
        }

        $token = \app\common\library\Token::get($param['operator_player_session']);
            
        if(!$token || $token['expiretime'] < time()){
            $data = [
                "data"      => null,
                "error"     => 1034
            ];
            return json_encode($data);
        }

        $user = \app\common\model\User::where(['id' => $token['user_id']])->find();
        if(!$user){
            $data = [
                "data"      => null,
                "error"     => 1034
            ];
            return json_encode($data);
        }

        $data = [
            "data" => [
                "player_name"       => $this->config['prefix'] . $user['id'],
                "nickname"          => $this->config['prefix'] . $user['id'],
                "currency"          => "BRL"
            ],
            "error" => null
        ];

        \think\Log::record($data, 'VerifySession');
        return json_encode($data);
    }

    /**
     * @return void
     * 获取用户钱包
     */
    public function Get() 
    {
        $param = $this->request->param();
        \think\Log::record($param, 'Get');

        $userId = $param['UseID'];

        if($param['operator_token'] != $this->operator_token || $param['secret_key'] != $this->secret_key){
            $data = [
                "data"      => null,
                "error"     => 1034
            ];
            return json_encode($data);
        }

        $user = \app\common\model\User::where('id', $userId)->find();
        if(!$user){
            $data = [
                "data"      => null,
                "error"     => 3004
            ];
            return json_encode($data);
        }

        $money = $user['money'] * 1000;
        $data = [
            "data" => [
                "balance_amount"        => (int)$money,
                "currency_code"         => "BRL",
                "updated_time"          => time() * 1000
            ],
            "error" => null
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 下注派彩
     */
    public function TransferInOut() 
    {
        $es = new Es();

        $param = $this->request->param();
        \think\Log::record($param, 'TransferInOut');

        // 交易电话
        $transaction_id = $param['Term'];

        $userId = $param['UseID'];
        $gameId = $param['GameID'];  
        // \think\Log::record($param, 'TransferInOut');
        // 验证基本参数1
        if($param['operator_token'] != $this->operator_token || $param['secret_key'] != $this->secret_key){
            return json_encode([
                "data"  => null,
                "error" => 3004
            ]);
        }
       
        $user = \app\common\model\User::where('id', $userId)->find();
        if(!$user){
            return json_encode([
                "data"  => null,
                "error" => 3004
            ]);
        }
        
        // //查询交易记录是否存在
        // $esLog = $es->searchByTransactionId($transaction_id, $this->gameRecord);
        // $log = count($esLog) > 0;

         // 幂等检查
        $check = Cache::store('redis')->get('TransferInOut_' . $transaction_id);
        if($check){
            $data = [
                "data" => [
                    "balance_amount"    => $user['money'] * 1000,
                    "currency_code"     => "BRL",
                    "updated_time"      => time() * 1000
                ],
                "error" => null
            ];
            
            return json_encode($data);
        }

        //查询游戏
        $game = db('game_raspa')->where('id', $gameId)->find();
        
        $bet_amount = $param['Bet'] / 1000;
        $win_amount = $param['Award'] / 1000;
        $transfer_amount = $param['UpdateCredit'] / 1000;
        
        //税费计算
        $tax = $this->config['tax'];
        $tax_fee = $win_amount * $tax;
        
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);            
            
            $bet_money_remain = bcsub($user->money, $bet_amount, 2);
            $money = bcadd($user->money, $transfer_amount, 2);

            //扣10%的税收 第二种 用户只要中奖都扣 下注后余额0不扣税 $bet_money_remain > 0 &&
            if(($win_amount > 0) && ($money >= $tax_fee)){
                $money = bcsub($money,$tax_fee,2);
            }

            if($money < 0){
                Db::rollback();
                return json_encode([
                    "data"  => [
                        "balance_amount"    => $user['money'] * 1000,
                        "currency_code"     => "BRL",
                        "updated_time"      => time() * 1000
                    ],
                    "error" => 3202
                ]);
            }

            if($bet_money_remain < 0){
                Db::rollback();
                return json_encode([
                    "data" => [
                        "balance_amount"    => $user['money'] * 1000,
                        "currency_code"     => "BRL",
                        "updated_time"      => time() * 1000
                    ],
                    "error" => 3202
                ]);
            }
          
            // if(!$log){
                if($transfer_amount != 0 || $bet_amount != 0){
                    // 添加ES记录
                    $betInfoArr = [
                        'transaction_id'    => $transaction_id,
                        'bet_amount'        => $bet_amount,
                        'win_amount'        => $win_amount,
                        'transfer_amount'   => $transfer_amount,
                        'is_fake'           => 1,
                    ];
                    $es = new Es();
                    $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
                    
                    $user->userdata->total_bet = bcadd($user->userdata->total_bet, $bet_amount * $game['bet_rate'], 2);
                    $user->userdata->today_bet = bcadd($user->userdata->today_bet, $bet_amount * $game['bet_rate'], 2);
                    $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                    $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                    $user->userdata->bet_count += 1;
                    $user->userdata->save();

                    $user->money = $money;
                    $user->save();
                }
            // }

            Cache::store('redis')->set('TransferInOut_' . $transaction_id, $param, 3600);

            Db::commit();
        }catch(\Exception $e){
            \think\Log::record($e->getMessage(), 'TransferInOut');
            Db::rollback();
            return json_encode([
                "data" => null,
                "error"=> 3033
            ]);
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);
        
        $data = [
            "data" => [
                "balance_amount"    => $user['money'] * 1000,
                "currency_code"     => "BRL",
                "updated_time"      => time() * 1000
            ],
            "error" => null
        ];
        
        return json_encode($data);
    }
}