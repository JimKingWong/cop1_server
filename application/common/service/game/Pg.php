<?php

namespace app\common\service\game;

use app\common\model\game\Platform;
use app\common\service\Base;
use app\common\service\util\Es;
use app\common\service\util\Notice;
use fast\Http;
use think\Db;

class Pg extends Base
{
    protected $config;
    
    protected $platform = 'pg';
    
    protected $gameRecord = 'pg_game_record';

    protected $gameUrl = null;
    protected $operator_token = null;
    protected $secret_key = null;
    protected $prefix = null;
    protected $tax = null;
    
    public function __construct()
    {
        parent::__construct();

        $platform = Platform::where('code', $this->platform)->cache(true, 86400)->find();

        $this->config = $platform->config;
        
        if(empty($this->config)){
            $this->error(__('PG游戏配置不存在'));
        }

        if($platform->status != 1){
           $this->error(__('PG游戏未开启'));
        }

        foreach($this->config as $k => $v){
            if($v == ''){
                $this->error(__($this->platform . '游戏配置不完整, 缺少' . $k . '配置'));
            }
        }

        $this->gameUrl              = $this->config['gameUrl'];
        $this->operator_token       = $this->config['operator_token'];
        $this->secret_key           = $this->config['secret_key'];
        $this->prefix               = $this->config['prefix'];
        $this->tax                  = isset($this->config['tax']) && $this->config['tax'] > 1 ? $this->config['tax'] / 100 : $this->config['tax'];
    }

    /**
     * 获取游戏链接
     */
    public function getLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        $user_token = $this->auth->getToken();

        // 请求地址
        $apiUrl = $this->gameUrl;

        // 请求参数
        $data = [
            'form_params' => [
                'operator_token'    => $this->operator_token,
                'path'              => "/". $game->game_id . "/index.html",
                'extra_args'        => 'l=pt&btt=1&ops=' . $user_token,
                'url_type'          => 'game-entry',
                'client_ip'         => GetUserIP(),
            ]
        ];

        // 异步请求
        $res = Http::post($apiUrl, $data);
        dd($res);
        header("Cache-Control: no-cache, no-store, must-revalidate, Content-Type: text/html");
        // echo $res->getBody();
    }

   
    /**
     * 令牌验证api
     */
    public function VerifySession() 
    {
        $params = $this->request->param();
        // \think\Log::record($params, 'VerifySession_param');

        $retval = [
            'data'  => null, 
            'error' => [
                'code' => 1034,
                'message' => 'fail'
            ]
        ];
        if($params['operator_token'] != $this->operator_token || $params['secret_key'] != $this->secret_key){
            return json_encode($retval);
        } 
        
        // 通过token获取用户信息
        $userinfo = \app\common\library\Token::get($params['operator_player_session']);

        if(!$userinfo || $userinfo['expiretime'] < time()){
            return json_encode($retval);
        }

        // 验证用户是否存在
        $user = \app\common\model\User::where('id', $userinfo['user_id'])->find();

        if(!$user){
            return json_encode($retval);
        }

        // 返回数据
        $data = [
            'data'  => [
                'player_name' => $this->prefix . $user['id'],
                'currency'    => 'BRL'
            ],
            'error' => null
        ];

        // \think\Log::record($data, 'VerifySession');
        return json_encode($data);
    }

    /**
     * @return void
     * 获取用户钱包
     */
    public function Get() 
    {
        $params = $this->request->param();
        // \think\Log::record($params, 'Get_param');

        // 获取用户id
        $userId = str_replace($this->prefix, '', $params['player_name']);

        // 验证基本参数
        if($params['operator_token'] != $this->operator_token || $params['secret_key'] != $this->secret_key){
            // 错误信息
            $retval = [
                'data'  => null, 
                'error' => [
                    'code' => 1034,
                    'message' => 'fail'
                ]
            ];
            return json_encode($retval);
        } 

        // 验证用户
        $user = \app\common\model\User::where('id', $userId)->find();

        if(!$user){
            // 错误信息
            $retval = [
                'data'  => null, 
                'error' => [
                    'code' => 3005,
                    'message' => 'fail'
                ]
            ];
            return json_encode($retval);
        }

        $data = [
            'data'  => [
                'balance_amount' => $user->money,
                'currency_code'  => 'BRL',
                'updated_time'   => time() * 1000
            ],
            'error' => null
        ];

        // \think\Log::record($data, 'Get_return');
        return json_encode($data);
    }

    /**
     * @return void
     * 下注派彩
     */
    public function TransferInOut() 
    {
        $params = $this->request->param();
      
        // \think\Log::record($param, 'TransferInOut');
        // 验证基本参数
        if($params['operator_token'] != $this->operator_token || $params['secret_key'] != $this->secret_key){
            // 错误信息
            $retval = [
                'data'  => null, 
                'error' => [
                    'code' => 1034,
                    'message' => 'fail'
                ]
            ];
            return json_encode($retval);
        }

        // 交易单号
        $transaction_id = $params['transaction_id'];

        //验证基本参数2
        $arr = explode('-', $transaction_id);
        if ($arr[2] != 106) {
            return json_encode([
                "data"=> null,
                "error"=> [
                    'code'=> 1401,
                    "message"=> "fail"
                ]
            ]);
        }

        //验证货币
        if($params['currency_code'] != 'BRL'){
            return json_encode([
                "data"=> null,
                "error"=> [
                    'code'=> 1035,
                    "message"=> "fail"
                ]
            ]);
        }

        // 获取用户id
        $userId = str_replace($this->prefix, '', $params['player_name']);
       
        // 验证用户
        $user = \app\common\model\User::where('id', $userId)->cache(true, 3600)->find();
        if(!$user){
            // 错误信息
            $retval = [
                'data'  => null, 
                'error' => [
                    'code' => 3004,
                    'message' => 'fail'
                ]
            ];
            return json_encode($retval);
        }
        
        $es = new Es();
        // 查询交易记录是否存在
        $esLog = $es->searchByTransactionId($transaction_id, $this->gameRecord);
        $log = count($esLog);

        // 查询游戏
        $game_id = $params['game_id'];
        $game = db('game_pg')->where('id', $game_id)->cache(true, 86400)->find();

        $bet_amount = $params['bet_amount'];
        $win_amount = $params['win_amount'];
        $transfer_amount = $params['transfer_amount'];
        
        // 税费计算
        $tax = $this->tax;
        $tax_fee = $win_amount * $tax;
        
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            
            $money = bcadd($user->money, $transfer_amount, 2);
            $bet_money_remain = bcsub($user->money, $bet_amount, 2);
            if($money < 0 || $bet_money_remain < 0){
                Db::rollback();
                return json_encode([
                    "data"  => null,
                    "error" => [
                        'code'      => 3202,
                        "message"   => "fail"
                    ]
                ]);
            }
            
            //扣税
            $money = bcsub($money, $tax_fee, 2);
            $transfer_amount = bcsub($transfer_amount, $tax_fee, 2);
            
            if ($arr[2] == 106 && !$log) {
                if($transfer_amount != 0 || $bet_amount != 0){
                    // 添加ES记录
                    $betInfoArr = [
                        'transaction_id'    => $transaction_id,
                        'bet_amount'        => $bet_amount,
                        'win_amount'        => $win_amount,
                        'transfer_amount'   => $transfer_amount,
                        'is_fake'           => 0,
                    ];
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
            }
            Db::commit();
        } catch (\Exception $e) {
            \think\Log::record($e->getMessage(), 'TransferInOut');
            Db::rollback();
            return json_encode([
                "data"=> null,
                "error"=> 3033
            ]);
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);
        
        $data = [
            "data"=>[
                "balance_amount"    => $user['money']*1000,
                "currency_code"     => "BRL",
                "updated_time"      => time()*1000
            ],
            "error"=>null
        ];
        
        // \think\Log::record($data, 'TransferInOut');
        return json_encode($data);
    }

    /**
     * @return void
     * 余额调整
     */
    public function Adjustment() 
    {
        $params = $this->request->param();
        $str = '';
        foreach ($params as $item => $value) {
            $str = $str . $item . '=' . $value . '&';
        }

        $insert_data = null;
        $insert_data['type'] = 'Adjustment';
        $insert_data['result_json'] = $str;

        $es = new Es();
        $es->addGameResult($insert_data);

        if($params['currency_code'] != 'BRL'){
            return json_encode([
                "data"      => null,
                "error"     => [
                    'code'      => 1035,
                    "message"   => "fail"
                ]
            ]);
        }

        // 验证基本参数
        if($params['operator_token'] != $this->operator_token || $params['secret_key'] != $this->secret_key){
            // 错误信息
            $retval = [
                'data'  => null, 
                'error' => [
                    'code' => 1034,
                    'message' => 'fail'
                ]
            ];
            return json_encode($retval);
        }

        $log = \app\common\model\MoneyLog::where(['transaction_id' => $params['adjustment_transaction_id']])->find();
        if($log){
            return json_encode([
                "data"=>[
                    "adjust_amount"     => $log['money'],
                    "balance_before"    => $log['before'],
                    "balance_after"     => $log['after'],
                    "updated_time"      => $params['adjustment_time']
                ],
                "error" => null
            ]);
        }
        
        $user = \app\common\model\User::where('username', $params['player_name'])->find();
        if (!$user) {
            return json_encode([
                "data"      => null,
                "error"     => [
                    'code'          => 3004,
                    "message"       => "fail"
                ]
            ]);
        }

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            $money = bcadd($user->money, $params['transfer_amount'], 2);
            if($money < 0){
                return json_encode([
                    "data"      => null,
                    "error"     => [
                        'code'      => 3202,
                        "message"   => "fail"
                    ]
                ]);
            }

            $moneyLog = [
                'user_id'           => $user['id'], 
                'money'             => $params['transfer_amount'], 
                'before'            => $user['money'], 
                'after'             => $money, 
                'memo'              => '余额调整', 
                'transaction_id'    => $params['adjustment_transaction_id'], 
                'admin_id'          => $user['admin_id']
            ];
            \app\common\model\MoneyLog::create($moneyLog);
            $user['money'] = $money;
            $user->save();

            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json_encode([
                "data"=> null,
                "error"=> [
                    'code'=> 1200,
                    "message"=> "fail"
                ]
            ]);
        }

        $data = [
            "data"=>[
                "adjust_amount"     => $params['transfer_amount'],
                "balance_before"    => bcsub($user['money'], $params['transfer_amount'], 2),
                "balance_after"     => $user['money'],
                "updated_time"      => $params['adjustment_time']
            ],
            "error"=>null
        ];
        // \think\Log::record($data, 'Adjustment');
        return json_encode($data);
    }
}