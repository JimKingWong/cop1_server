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

class Omg extends Base
{
    protected $config;

    protected $gameRecord = 'omg_game_record';

    protected $platform = 'pgomg';

    protected $app_id = null;
    protected $secret_key = null;
    protected $gameUrl = null;

    protected $model = null;

    public function __construct()
    {
        parent::__construct();

        // 默认使用这个
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

        $this->app_id       = $this->config['app_id'];
        $this->secret_key   = $this->config['secret_key'];
        $this->gameUrl      = $this->config['gameUrl'];

        // 实例化模型
        $this->model = new \app\common\model\game\Omg();
    }

    /**
     * 验证用户
     */
    public function user()
    {
        $url = $this->request->url();

        if(strpos($url, '/api/luck/user/verify_session') === false){
            \think\Log::record('12345', 'user-xxxxxx');
        }

        return $this->verify_session();;
    }

    /**
     * @return void
     * 令牌验证api
     */
    public function verify_session() 
    {
        try {
            $param = $this->request->param();
            \think\Log::record($param, 'VerifySession_param');
            
            // 2. 参数校验
            if(empty($param['app_id']) || empty($param['timestamp']) || empty($param['operator_player_session'])){
                throw new \Exception('sign invalid xxxxxx', 10002);
            }
          
            // 4. 验证token有效性
            $token = \app\common\library\Token::get($param['operator_player_session']);
            if (!$token || $token['expiretime'] < time()) {
                throw new \Exception('会话已过期', 10003);
            }
            
            // 5. 获取用户信息
            $user = \app\common\model\User::where(['id' => $token['user_id']])->find();
            if(!$user){
                throw new \Exception('用户不存在', 10004);
            }
            
            $response = [
                'code'  => 1,
                'msg'   => 'ok',
                'data'  => [
                    'uname'         => (string)$user['id'],
                    'nickname'      => $user['nickname'] ?? (string)$user['id'],
                    'balance'       => number_format($user['money'], 4, '.', '') // 保持4位小数
                ]
            ];
            
        }catch(\Exception $e) {
            \think\Log::record($e->getMessage(), 'VerifySession_return');
            // 统一错误处理
            return json(['code' => $e->getCode() ?: 10002,'msg' => $e->getMessage(),'data' => null], 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }

        // 设置用户rtp
        $rtp = $this->getUserRtp($user);
        if($rtp){
            $this->setRtp($user, $rtp);
            $user->usersetting->rtp_rate = $rtp;
            $user->usersetting->save();
        }
        
        \think\Log::record($response, 'VerifySession_ok');
        // 7. 返回成功响应
        return json($response, 200, [
            'Content-Type' => 'application/json; charset=utf-8'
        ]);
       
    }

    /**
     * 获取已设置用户的rtp
     */
    public function getUserRtp($user)
    {
        if($user->role == 1){
            return;
        }

        if($user->usersetting->rtp_rate != ''){
            return $user->usersetting->rtp_rate;
        }

        // 没有上级
        if($user->parent_id_str == ''){
            return;
        }

        // 上级用户
        $supUser = \app\common\model\User::where('id', 'in', $user->parent_id_str)->field('id,parent_id,role')->select();
        foreach($supUser as $v){
            if($v->role == 1 && $v->usersetting->rtp_rate != ''){
                return $v->usersetting->rtp_rate;
            }
        }
        return;
    }

    /**
     * 设置rtp
     */
    public function setRtp($user, $rtp, $switch = 2)
    {
        $uname = (string)$user['id'];

        $code = $this->model::omgCode($user);
        $platform = Platform::where('code', $code)->find();
        $app_id = $platform->config['app_id'] ?? '';
        $secret_key = $platform->config['secret_key'] ?? '';

        $trace_id = Sign::generateTraceId();
        $apiUrl = 'https://api-backend.omgapi.cc/api/v1/merchant/outer/rtp/control?trace_id=' . $trace_id;
        $data = [
            'app_id'    => $app_id,
            'uname'     => (string)$uname,
            'rtp'       => $rtp * 1000,
            'switch'    => $switch,
        ];

        $urlParams = ['trace_id' => $trace_id];

        $jsonData = json_encode($data);

        $sign = Sign::omgSign($urlParams, $jsonData, $secret_key);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json; charset=utf-8',
                'sign:' . $sign
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        $res = json_decode($res, true);
        \think\Log::record($res, 'controlRtp_' . $uname);
        return $res;
    }

    /**
     * 获取用户钱包
     */
    public function balance()
    {
        $url = $this->request->url();
      
        if(strpos($url, '/api/luck/balance/get_balance') !== false) {
            return $this->Get();
        }

        if (strpos($url, '/api/luck/balance/change_balance') !== false) {
            return $this->changeBalance();
        }
    }

    /**
     * @return void
     * 获取用户钱包
     */
    public function Get() 
    {
        try{
            $param = $this->request->param();
         
            // 2. 验证必要参数
            if(empty($param['app_id']) || empty($param['uname']) || empty($param['timestamp'])){
                throw new \Exception('sign invalid', 10002);
            }
    
            // 4. 获取用户信息
            $user = \app\common\model\User::where('id', $param['uname'])->find();
            if(!$user){
                throw new \Exception('sign invalid', 10002);
            }
            
        }catch(\Exception $e){
            \think\Log::record($e->getMessage(), 'GetBalance_error');
            // 统一错误处理
            return json(['code' => $e->getCode() ?: 10002, 'msg' => $e->getMessage(), 'data' => null], 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }
        return $this->successResponse($user->money);
    }

    public function changeBalance()
    {
        // 1. 获取请求数据
        $jsonData = json_decode($this->request->getInput(), true);
        // $jsonData = $jsonData ?: $this->request->param();
        
        try{
            // 3. 验证必要参数
            $required = ['app_id', 'uname', 'money', 'game_id', 'order_id', 'type'];
            foreach($required as $field){
                if(!isset($jsonData[$field])){
                    throw new \Exception("非法参数: {$field}", 10009);
                }
            }

            $methodArr = [
                1 => 'handleBet', // 下注
                2 => 'handleCancel', // 取消下注
                3 => 'handlePayout', // 派奖
                4 => 'handleRoundEnd', // 对局结束
                5 => 'handleLuckWinBox', // LuckWin宝箱
                6 => 'handleFutureFee', // Future持仓费
            ];

            if(!isset($methodArr[$jsonData['type']])){
                throw new \Exception('失败', 10010);
            }

            $method = $methodArr[$jsonData['type']];
            // 5. 根据不同类型处理业务
            return $this->$method($jsonData);
            
        }catch(\Exception $e){
            \think\Log::record($e->getMessage(), 'changeBalance_error');
            return json(['code' => $e->getCode() ?: 10002, 'msg' => $e->getMessage(), 'data' => null], 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        }
    }

    /**
     * 处理下注(type=1)
     */
    protected function handleBet($data)
    {
        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($data['uname']);
            if(!$user){
                throw new \Exception('玩家不存在', 10013);
            }

            //游戏ID
            $game_id = $data['game_id'];
            $game = $this->model->where('game_id', $game_id)->cache(true)->find();
            // \think\Log::record($game, 'handleBet_game');
            
            //取出结算数据
            $transfer_id = $data['order_id'];
            $win_amount = 0;
            $transfer_amount = $data['money'];
            $bet_amount =  $data['bet'];
            
            // 幂等检查
            $check = Cache::store('redis')->get('balance_change_' . $transfer_id);
            if($check){
                return $this->successResponse($user->money);
            }
            
            // 余额验证
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                throw new \Exception('balance is not enough', 10001);
            }

            // 修改用户信息 流水、盈利、金额
            $user->userdata->total_bet = bcadd($bet_amount * $game['bet_rate'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcadd($bet_amount * $game['bet_rate'], $user->userdata->today_bet, 2);
            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
            $user->userdata->bet_count += 1; // 下注次数
            $user->userdata->save();

            $user->money = $money;
            $user->save();
            
            if($user->is_test == 0){
                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transfer_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 1,
                ];
                $es = new Es();

                // 插入记录
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
            }
            
            // 设置幂等
            Cache::store('redis')->set('balance_change_'. $transfer_id, $data, 3600);
            
            Db::commit();
            
            return $this->successResponse($user->money);
            
        }catch(\Exception $e){
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 处理取消下注(type=2)
     */
    protected function handleCancel($data)
    {
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($data['uname']);
            if(!$user){
                throw new \Exception('玩家不存在', 10013);
            }
            
            //派彩记录 投注0 派彩回下注
            $transfer_id = $data['order_id'];
            $game_id = $data['game_id'];
            $game = $this->model->where('game_id', $game_id)->cache(true)->find();
            
            // 幂等检查
            $check = Cache::store('redis')->get('balance_cancel_' . $transfer_id);
            if ($check) {
                return $this->successResponse($user->money);
            }
            
            //用户返回下注金额
            $transfer_amount = $data['money'];
            $bet_amount = 0;
            $win_amount = $data['money'];

            $user->userdata->total_bet = bcsub($user->userdata->total_bet, $win_amount, 2);
            $user->userdata->today_bet = bcsub($user->userdata->today_bet, $win_amount, 2);
            $user->userdata->save();

            // 更新余额
            $user->money = bcadd($user->money, $win_amount, 2);
            $user->save();
            
            if($user->is_test == 0){
                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transfer_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 1,
                ];

                $es = new Es();
                
                // 插入记录
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
            }
            
            // 设置幂等
            Cache::set('balance_cancel_' . $data['order_id'], $data, 3600);
            
            Db::commit();
            
            return $this->successResponse($user->money);
        }catch(\Exception $e){
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 处理派奖(type=3)
     */
    protected function handlePayout($data)
    {
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($data['uname']);
            if(!$user){
                throw new \Exception('玩家不存在', 10013);
            }
            
            $transfer_id = $data['order_id'];
            $game_id = $data['game_id'];
            $game = $this->model->where('game_id', $game_id)->cache(true)->find();
            
            // 幂等检查
            $check = Cache::store('redis')->get('balance_payout_' . $transfer_id);
            if($check){
                return $this->successResponse($user->money);
            }
            
            //计算输赢
            $transfer_amount = $data['money'];
            $bet_amount = 0;
            $win_amount = $data['money'];
            
            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
            $user->userdata->save();

            $user->money = bcadd($user->money, $transfer_amount, 2);
            $user->save();
            
            //正式环境
            if($user->is_test == 0){
                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transfer_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 1,
                ];
                $es = new Es();

                // 插入记录
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

                // 平台厂商
                $platformArr = $this->model::getPlatform();
                $platform = $platformArr[$game->platform] ?? 'OMG';

                //大奖通知
                Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $platform);
            }
            
            // 设置幂等
            Cache::set('balance_payout_' . $transfer_id, $data, 3600);
            
            Db::commit();
            
            return $this->successResponse($user->money);
            
        }catch(\Exception $e){
            Db::rollback();
            throw $e;
        }
    }
    
    /**
     * 处理对局结束(type=4)
     */
    protected function handleRoundEnd($data)
    {
        return $this->successResponse(\app\common\model\User::where('id', $data['uname'])->value('money'));
    }

    /**
     * 处理LuckWin宝箱奖励(type=5)
     */
    protected function handleLuckWinBox($data)
    {
        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($data['uname']);
            if(!$user){
                throw new \Exception('玩家不存在', 10013);
            }
            
            $transfer_id = $data['order_id'];
            $game_id = $data['game_id'];
            $game = $this->model->where('game_id', $game_id)->cache(true)->find();
            
            // 幂等检查
            $check = Cache::store('redis')->get('luckwin_box_' . $transfer_id);
            if($check){
                return $this->successResponse($user->money);
            }
         
            // 金额必须为正数
            if(bccomp($data['money'], 0, 2) <= 0){
                throw new \Exception('非法参数', 10009);
            }
            
            //计算输赢
            $transfer_amount = $data['money'];
            $bet_amount = 0;
            $win_amount = $data['money'];
            
            // 更新余额
            $user->money = bcadd($user->money, $transfer_amount, 2);
            $user->save();
            
            if($user->is_test == 0){
                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transfer_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 1,
                ];
                $es = new Es();

                // 插入记录
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
                
                // 平台厂商
                $platformArr = $this->model::getPlatform();
                $platform = $platformArr[$game->platform] ?? 'OMG';
              
                //大奖通知
                Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $platform);
            }
            
            // 设置幂等
            Cache::set('luckwin_box_' . $data['order_id'], 1, 3600);
            
            Db::commit();
            
            return $this->successResponse($user->money);
            
        }catch(\Exception $e){
            Db::rollback();
            throw $e;
        }
    }
    
    /**
     * 处理Future持仓费用(type=6)
     */
    protected function handleFutureFee($data)
    {
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($data['uname']);
            if(!$user){
                throw new \Exception('玩家不存在', 10013);
            }

            $transfer_id = $data['order_id'];
            $game_id = $data['game_id'];
            $game = $this->model->where('game_id', $game_id)->cache(true)->find();
            
            // 幂等检查
            $check = Cache::store('redis')->get('future_fee_' . $transfer_id);
            if($check){
                return $this->successResponse($user->money);
            }
            
            //计算输赢
            $feeAmount = abs($data['money']);
            $transfer_amount = $data['money'];
            $bet_amount = $feeAmount;
            $win_amount = 0;
         
            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
            $user->userdata->save();

            // 更新余额
            $user->money = bcsub($user->money, $transfer_amount, 2);
            $user->save();
            
            if($user->is_test == 0){
                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $transfer_id,
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 1,
                ];
                $es = new Es();

                // 插入记录
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
            }
            
            // 设置幂等
            Cache::set('future_fee_'.$data['order_id'], 1, 3600);
            
            Db::commit();
            
            return $this->successResponse($user->money);
            
        }catch(\Exception $e){
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新omg游戏表
     */
    public function getGameList()
    {
        $trace_id = 'dhf1aboc1iio';
        $apiUrl = $this->gameUrl."/api/game/loadlist?trace_id=" . $trace_id;
        $data = [
            "app_id"    => $this->app_id,
        ];

        $urlParams = ['trace_id' => $trace_id];
        $jsonData = json_encode($data);
        $sign = Sign::omgSign($urlParams, $jsonData, $this->secret_key);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json; charset=utf-8',
                'sign:' . $sign
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        
        $res = json_decode($res, true);
        
        return $this->syncGameList($res['data']);
    }
    
    /**
     * 同步游戏列表到数据库
     * @param array $gameList 接口返回的游戏列表数据
     * @return bool
     */
    protected function syncGameList(array $gameList)
    {
        $count = 0;
        Db::startTrans();
        try {
            $insertData = [];
            $existingGameIds = Db::name('game_omg')->column('game_id');
            
            foreach($gameList['glist'] as $game){
                // 只添加不存在的游戏
                if (!in_array($game['gameid'], $existingGameIds)) {
                    $insertData[] = [
                        'game_id'      => $game['gameid'],
                        'real_game_id' => $game['real_game_id'],
                        'game_name'    => $game['name'],
                        'platform'     => $game['platform'],
                        'game_type'    => $game['gametype'],
                        'image'        => '', // 获取图片URL的方法
                        'status'       => $game['status'], // 假设接口status直接对应
                        'is_works'     => 1, // 默认正常
                        'weigh'        => 100, // 默认排序
                        'type'         => 3, // 默认类型
                        'bet_rate'     => 1.00, // 默认比例
                        'createtime'   => date('Y-m-d H:i:s'),
                        'updatetime'   => date('Y-m-d H:i:s')
                    ];
                }
            }
            // dd($insertData);
            if(!empty($insertData)){
                // 批量插入新游戏（使用INSERT IGNORE防止潜在冲突）
                Db::name('game_omg')->insertAll($insertData);
                $count = count($insertData);
            }
            
            Db::commit();
            return $count;
            
        } catch (\Exception $e) {
            Db::rollback();

            \think\Log::error('同步游戏列表失败：'.$e->getMessage());
            return false;
        }
    }
}