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

class Cp extends Base
{
    /**
     * 配置
     */
    protected $config;

    /**
     * Es表名
     */
    protected $gameRecord = 'cp_game_record';
    
    /**
     * 厂商
     */
    protected $platform = 'cp';

    protected $appId = null;
    protected $gamekey = null;
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

        $this->appId = $this->config['appId'];
        $this->gamekey = $this->config['gamekey'];
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
        
        $apiUrl = $this->gameUrl;

        $data = [
            "appid"         => $this->appId,
            "game_key"      => "hog",
            "sub_uid"       => $user['id'],
            "game_id"       => $game->game_id,
            "lang"          => "en",
            "time"          => time(),
        ];

        $data['token'] = Sign::cpSign($data, $this->gamekey);
        
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];

        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode(htmlspecialchars_decode($res),true);
       
        $retval = [
            'game_url' => $res['data'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 统一回调地址
     */
    public function handle()
    {
        $param = $this->request->param();

        $methodArr = [
            'get'               => 'getBalance',
            'transferInOut'     => 'transferInOut',
            'cancelInOut'       => 'cancelInOut',
            'transferOut'       => 'transferOut',
            'cancelOut'         => 'cancelOut',
            'transferIn'        => 'transferIn',
        ];

        if(!isset($methodArr[$param['balance']])){
            return json_encode([
                "code" => 1199,
                "msg" => "未知错误通用错误返回",
                "data" => null
            ]);
        }

        return $this->$methodArr[$param['balance']]($param);
    }

    /**
     * 获取玩家余额
     */
    protected function getBalance($param)
    {
        $message = json_decode(htmlspecialchars_decode($param['message']), true);
        $user = \app\common\model\User::find($message['sub_uid']);

        if(!$user){
            return json_encode(["code" => 1116, "msg" => "玩家不存在哦.", "data" => null]);
        }
     
        return json_encode(["code" => 0, "msg" => "获取余额成功,用户ID:" . $user['id'],
            "data" => [
                "balance"   => $user['money'],
                "currency"  => "BRL"
            ]
        ]);
    }

    /**
     * 下注并结算
     */
    protected function transferInOut($param)
    {
        $message = json_decode(htmlspecialchars_decode($param['message']), true);

        $user = \app\common\model\User::find($message['sub_uid']);
        if(!$user){
            return json_encode(["code" => 1116, "msg" => "玩家不存在", "data" => null]);
        }

        $bet_info = $message['bet_info'];

        //游戏ID
        $game_id = $message['game_id'];
        $game = db('game_'. $this->platform)->where('game_id', $game_id)->cache(true)->find();
        
        //取出结算数据
        $win_amount = $bet_info['win_amount'];
        $transfer_amount = $bet_info['transfer_amount'];
        $bet_amount =  $bet_info['bet_amount'];

        // 税费计算
        $tax = $this->config['tax'];
        $tax_fee =  $win_amount * $tax;

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            
            // 计算输赢
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                Db::rollback();
                return json_encode(["code" => 1117, "msg" => "玩家余额不足", "data" => null]);
            }
            
            $transaction_id = $bet_info['bet_id'];

            $check = Cache::store('redis')->get($transaction_id);
            if(!$check){
                Cache::store('redis')->set($transaction_id, $bet_info, 15 * 60);
            }else{
                $return_data = [
                    "code" => 0,
                    "msg" => "计算成功",
                    "data" => [
                        "balance"       => $user['money'],
                        "currency"      => "BRL",
                        "updated_ms"    => microtime()
                    ]
                ];
                return json_encode($return_data);
            }
            
            
            // 扣税
            $money = bcsub($money,$tax_fee,2);
            $transfer_amount = bcsub($transfer_amount,$tax_fee,2);
            
            // 修改用户信息 流水、盈利、金额
            $user->userdata->total_bet = bcadd($bet_info['bet_amount'] * $game['bet_rate'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcadd($bet_info['bet_amount'] * $game['bet_rate'], $user->userdata->today_bet, 2);
            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();

            Db::commit();
        }catch(\Exception $e){
            \think\Log::record($e->getMessage(),'cp_error');
            Db::rollback();
            return json_encode(["code" => 1199, "msg" => "计算成功", "data" => null]);
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);

        // 添加ES记录
        $betInfoArr = [
            'transaction_id'    => $transaction_id,
            'bet_amount'        => $bet_amount,
            'win_amount'        => $win_amount,
            'transfer_amount'   => $transfer_amount,
            'is_fake'           => 0,
        ];
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

        $return_data = [
            "code"  => 0,
            "msg"   => "计算成功",
            "data"  => [
                "balance"       => $user['money'],
                "currency"      => "BRL",
                "updated_ms"    => microtime()
            ]
        ];
        return json_encode($return_data);
    }
    
    /**
     * 取消下注并结算
     */
    protected function cancelInOut($param)
    {
        $message = json_decode(htmlspecialchars_decode($param['message']), true);

        $user = \app\common\model\User::find($message['sub_uid']);
        if(!$user){
            return json_encode(["code" => 1116, "msg" => "玩家不存在", "data" => null]);
        }

        // 查找记录
        $transaction_id = $message['bet_id'];
        $es = new Es();
        $log = $es->searchByTransactionId($transaction_id, $this->gameRecord);
        if(!$log){
            return json_encode(["code" => 1118, "msg" => "订单不存在", "data" => null]);
        }

        // 游戏ID
        $game_id = $message['game_id'];
        $game = db('game_'. $this->platform)->where('game_id', $game_id)->cache(true)->find();

        $transfer_amount = bcmul($log['bet_amount'], 1, 2);
        $bet_amount =  0;
        $win_amount = $transfer_amount;

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            $money = bcadd($user->money, $transfer_amount, 2);

            // 退钱+派彩记录
            // 加记录
            $betInfoArr = [
                'transaction_id'    => $transaction_id,
                'bet_amount'        => $bet_amount,
                'win_amount'        => $win_amount,
                'transfer_amount'   => $transfer_amount,
                'is_fake'           => 0,
            ];
            
            $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

            $user->userdata->total_bet = bcsub($log['bet_amount'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcsub($log['bet_amount'], $user->userdata->today_bet, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json_encode([
                "code"  => 1199,
                "msg"   => "未知错误通用错误返回",
                "data"  => null]);
        }

        return json_encode([
            "code"  => 0,
            "msg"   => "计算成功",
            "data"  => [
                "balance"       => $user['money'],
                "currency"      => "BRL",
                "updated_ms"    => microtime()
            ]
        ]);
    }

    /**
     * 下注
     */
    protected function transferOut($param)
    {
        $message = json_decode(htmlspecialchars_decode($param['message']), true);

        $user = \app\common\model\User::find($message['sub_uid']);
        if(!$user){
            return json_encode(["code" => 1116, "msg" => "玩家不存在", "data" => null]);
        }

        // 取出下注数据
        $bet_info = $message['bet_info'];
        $transaction_id = $bet_info['bet_id'];
        $transfer_amount = bcmul($bet_info['bet_amount'],-1,2);
        $bet_amount =  $bet_info['bet_amount'];
        $win_amount = 0;
        
        //游戏ID
        $game_id = $message['game_id'];
        $game = db('game_'. $this->platform)->where('game_id', $game_id)->cache(true)->find();
        
        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($message['sub_uid']);
            
            $check = Cache::store('redis')->get($transaction_id);
            if(!$check){
                Cache::store('redis')->set($transaction_id, $bet_info, 15 * 60);
            }else{
                $return_data = [
                    "code"  => 0,
                    "msg"   => "计算成功",
                    "data"  => [
                        "balance"       => $user['money'],
                        "currency"      => "BRL",
                        "updated_ms"    => microtime()
                    ]
                ];
                return json_encode($return_data);
            }
            
            // 计算余额
            $money = bcsub($user->money, $bet_amount, 2);
            if($money < 0){
                return json_encode(["code" => 1117, "msg" => "玩家余额不足", "data" => null]);
            }

            // 修改用户信息
            $user->userdata->total_bet = bcadd($bet_info['bet_amount'] * $game['bet_rate'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcadd($bet_info['bet_amount'] * $game['bet_rate'], $user->userdata->today_bet, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json_encode(["code" => 1199, "msg" => "未知错误通用错误返回", "data" => null]);
        }

        // 加记录
        $betInfoArr = [
            'transaction_id'    => $transaction_id,
            'bet_amount'        => $bet_amount,
            'win_amount'        => $win_amount,
            'transfer_amount'   => $transfer_amount,
            'is_fake'           => 0,
        ];

        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
        
        $return_data = [
            "code"  => 0,
            "msg"   => "计算成功",
            "data"  => [
                "balance"       => $user['money'],
                "currency"      => "BRL",
                "updated_ms"    => microtime()
            ]
        ];
       
        return json_encode($return_data);
    }

    /**
     * 取消下注
     */
    protected function cancelOut($param)
    {
        $message = json_decode(htmlspecialchars_decode($param['message']), true);

        $user = \app\common\model\User::find($message['sub_uid']);
        if(!$user){
            return json_encode([
                "code"  => 1116,
                "msg"   => "玩家不存在",
                "data"  => null
            ]);
        }
        // 查找记录
        $transaction_id = $message['bet_id'];
        
        $bet_info = $message['bet_info'];
        // 先检查订单是否存在
        $checkOut = Cache::store('redis')->get($transaction_id);
        if(!$checkOut){
            return json_encode(["code" => 1118, "msg" => "订单不存在", "data" => null]);
        }
        
        
        // 派彩记录 投注0 派彩回下注
        $game_id = $message['game_id'];
        $game = db('game_'. $this->platform)->where('game_id', $game_id)->cache(true)->find();

        // 用户返回下注金额
        $transfer_amount = 0;
        $bet_amount = 0;
        $win_amount = $bet_info['bet_amount'];

        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($message['sub_uid']);
            
            // 用来做幂等
            $check = Cache::store('redis')->get('ca_' . $transaction_id);
            if(!$check){
                Cache::store('redis')->set('ca_' . $transaction_id, $bet_info, 15 * 60);
            }else{
                $return_data = [
                    "code"  => 0,
                    "msg"   => "计算成功",
                    "data"  => [
                        "balance"       => $user['money'],
                        "currency"      => "BRL",
                        "updated_ms"    => microtime()
                    ]
                ];
                return json_encode($return_data);
            }
            
            $money = bcadd($user->money, $bet_info['bet_amount'], 2);

            // 修改用户信息
            $user->userdata->total_bet = bcsub($bet_info['bet_amount'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcsub($bet_info['bet_amount'], $user->userdata->today_bet, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json_encode([
                "code"  => 1199,
                "msg"   => "未知错误通用错误返回",
                "data"  => null
            ]);
        }
        
        // 加记录
        $betInfoArr = [
            'transaction_id'    => $transaction_id,
            'bet_amount'        => $bet_amount,
            'win_amount'        => $win_amount,
            'transfer_amount'   => $transfer_amount,
            'is_fake'           => 0,
        ];
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

        return json_encode([
            "code"  => 0,
            "msg"   => "计算成功",
            "data"  => [
                "balance"       => $user['money'],
                "currency"      => "BRL",
                "updated_ms"    => microtime()
            ]
        ]);
    }

    /**
     * 结算
     */
    protected function transferIn($param)
    {
        $message = json_decode(htmlspecialchars_decode($param['message']), true);

        $user = \app\common\model\User::find($message['sub_uid']);
        if(!$user){
            return json_encode(["code" => 1116, "msg" => "玩家不存在", "data" => null]);
        }

        // 查找记录
        $bet_info = $message['bet_info'];
        $transaction_id = $bet_info['bet_id'];
        
        // 先检查订单是否存在
        $checkOut = Cache::store('redis')->get($transaction_id);
        if(!$checkOut){
            return json_encode(["code" => 1118, "msg" => "订单不存在", "data" => null]);
        }
        
        // 游戏ID
        $game_id = $message['game_id'];
        $game = db('game_'. $this->platform)->where('game_id', $game_id)->cache(true)->find();

        // 计算输赢
        $transfer_amount = $bet_info['win_amount'];
        $bet_amount = 0;
        $win_amount = $bet_info['win_amount'];

        // 税费计算
        $tax = $this->config['tax'];
        $tax_fee =  $win_amount * $tax;
        
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($message['sub_uid']);
            
            // 用来做幂等
            $check = Cache::store('redis')->get('in_' . $transaction_id);
            if(!$check){
                Cache::store('redis')->set('in_' . $transaction_id, $bet_info, 15 * 60);
            }else{
                $return_data = [
                    "code" => 0,
                    "msg" => "计算成功",
                    "data" => [
                        "balance" => $user['money'],
                        "currency" => "BRL",
                        "updated_ms" => microtime()
                    ]
                ];
                return json_encode($return_data);
            }
            
            $money = bcadd($user->money, $bet_info['win_amount'], 2);

            //扣税
            $money = bcsub($money, $tax_fee, 2);
            $transfer_amount = bcsub($transfer_amount, $tax_fee, 2);

            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
            $user->userdata->save();

            // 修改用户信息
            $user->money = $money;
            $user->save();
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json_encode(["code" => 1199, "msg" => "未知错误通用错误返回", "data" => null]);
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);

        // 添加ES记录
        $betInfoArr = [
            'transaction_id'    => $transaction_id,
            'bet_amount'        => $bet_amount,
            'win_amount'        => $win_amount,
            'transfer_amount'   => $transfer_amount,
            'is_fake'           => 0,
        ];
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
     
        return json_encode([
            "code"      => 0,
            "msg"       => "计算成功",
            "data"      => [
                "balance"       => $user['money'],
                "currency"      => "BRL",
                "updated_ms"    => microtime()
            ]
        ]);
    }
    
    
}