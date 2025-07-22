<?php

namespace app\common\service\game;

use app\common\library\Token;
use app\common\model\game\Platform;
use app\common\model\User;
use app\common\service\Base;
use app\common\service\util\Es;
use app\common\service\util\Notice;
use app\common\service\util\Sign;
use fast\Http;
use think\Db;

class Pp extends Base
{
    /**
     * 配置
     */
    protected $config;

    /**
     * Es表名
     */
    protected $gameRecord = 'pp_game_record';
    
    /**
     * 厂商
     */
    protected $platform = 'pp';

    protected $gameKey = null;
    protected $secureLogin = null;
    protected $gameUrl = null;
    protected $prefix = null;
    
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

        $this->gameKey = $this->config['gameKey'];
        $this->secureLogin = $this->config['secureLogin'];
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

        // 用户信息
        $user = $this->auth->getUser();

        // 用户token
        $token = $this->auth->getToken();

        // 请求接口
        $apiUrl = $this->gameUrl;

        $lobbyUrl = "https://".$user['origin']."/";

        $data = [
            "secureLogin"       => $this->secureLogin,
            "symbol"            => $game->id,
            "language"          => "en",
            "token"             => $token,
            "externalPlayerId"  => $user->id,
            'lobbyUrl'          => $lobbyUrl,
            'cashierUrl'        => $lobbyUrl . "#/recharge",
            'jurisdiction'      => '99',
        ];
        
        $data['hash'] = Sign::ppSign($data, $this->gameKey);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);

        $retval = [
            'game_url' => $res['gameURL'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 认证
     */
    public function Authenticate()
    {
        $token = $this->request->param('token', '');
        if(!$token){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        // 根据token获取用户信息
        $data = Token::get($token);
        if(!$data){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        $user_id = intval($data['user_id']);
        $user = User::get($user_id);

        if(!$user){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        $return_data = [
            "error"             => 0,
            "description"       => "Success",
            "userId"            =>$user_id,
            "currency"          => "BRL",
            "cash"              => (float)$user['money'],
            "bonus"             => 0,
            "jurisdiction"      => "99",
        ];
        return json_encode($return_data);
    }

    /**
     * 获取玩家余额
     */
    public function Balance()
    {
        $userId = $this->request->param('userId', '');
        $user = User::find($userId);
        if(!$user){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        return json_encode([
            "error"             => 0,
            "description"       => "Success",
            "currency"          => "BRL",
            "cash"              => (float)$user['money'],
            "bonus"             => 0,
        ]);
    }

    /**
     * 下注
     */
    public function bet()
    {
        $params = $this->request->param();
        
        $user = \app\common\model\User::get($params['userId']);
        if(!$user){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        // 查询记录
        $transaction_id =  $params['reference'];
        $log = db("pp_record")->where(['transaction_id' => $transaction_id])->find();

        // 游戏id
        $game_id = $params['gameId'];
        $pos = strpos($game_id, '_');
        if($pos !== false){
            $game_id = substr($game_id, $pos + 1);
        }
        
        $game = db('game_' . $this->platform)->where('game_id', $game_id)->cache(true)->find();
        $bet_amount = (float)$params['amount'];
        $transfer_amount = bcmul($bet_amount,-1,2);
        $win_amount = 0;
        
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($params['userId']);
            
            if($log){
                Db::rollback();
                return json_encode([
                    "error"                 => 0,
                    "description"           => "Success",
                    "transactionId"         => $transaction_id,
                    "currency"              => "BRL",
                    "cash"                  => (float)$user->money,
                    "bonus"                 => 0,
                    "usedPromo"             => 0,
                ]);
            }

            $pp_res = db("pp_record")->insert([
                'transaction_id'    => $transaction_id,
                'amount'            => (float)$params['amount'],
                'createtime'        => time()
            ]);

            if(!$pp_res){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     =>$transaction_id,
                    "currency"          =>"BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             =>0,
                    "usedPromo"         =>0,
                ]);
            }

            // 计算余额
            $money = bcsub($user->money, $bet_amount, 2);
            if($money < 0){
                return json_encode([
                    "error"         => 1,
                    "description"   => "Insufficient balance. The error should be returned in the response on the Bet request.",
                ]);
            }

            // 修改用户信息
            $user->userdata->total_bet = bcadd($bet_amount * $game['bet_rate'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcadd($bet_amount * $game['bet_rate'], $user->userdata->today_bet, 2);
            $user->userdata->total_profit = bcadd($transfer_amount, $user->userdata->total_profit, 2);
            $user->userdata->today_profit = bcadd($transfer_amount, $user->userdata->today_profit, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();

            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            \think\Log::record($e->getMessage(),'PP_Bet_Error');
            return json_encode([
                "error"         => 100,
                "description"   => "Internal  server  error.",
            ]);
        }
        $return_data = [
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
            "currency"          => "BRL",
            "cash"              => (float)$money,
            "bonus"             => 0,
            "usedPromo"         => 0,
        ];
        
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

        // 加日志
        // \think\Log::record($return_data,'pp_bet');
        return json_encode($return_data);
    }

    /**
     * 结局
     */
    public function BetResult()
    {
        $params = $this->request->param();
        
        \think\Log::record($params,'pp_BetResult');

        $user = \app\common\model\User::where('id', $params['userId'])->find();
        if(!$user){
            return json_encode(["error" => 10, "description" => "User not found"]);
        }

        // 查找记录
        $transaction_id = $params['reference'];
        $log = db("pp_record")->where(['transaction_id' => $transaction_id])->find();

        $win_amount = (float)$params['amount'];
        $transfer_amount = $win_amount;
        $bet_amount = 0;

        // 游戏id
        $game_id = $params['gameId'];
        $pos = strpos($game_id, '_');
        if($pos !== false){
            $game_id = substr($game_id, $pos + 1);
        }
        
        $game = db('game_' . $this->platform)->where('game_id', $game_id)->cache(true)->find();

        $tax = 0.1;
        $tax_fee =  $win_amount * $tax;

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($params['userId']);
            if($log){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     => $transaction_id,
                    "currency"          => "BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             => 0,
                ]);
            }

            $pp_insert = [
                'transaction_id'    => $transaction_id,
                'amount'            => $win_amount,
                'createtime'        => time()
            ];

            if(isset($param['bonusCode'])){
                $pp_insert['bonusCode'] = $param['bonusCode'];
            }
            $pp_res = db("pp_record")->insert($pp_insert);

            if(!$pp_res){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     => $transaction_id,
                    "currency"          => "BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             => 0,
                ]);
            }

            if(isset($param['promoWinAmount'])){
                $win_amount = bcadd($win_amount, $param['promoWinAmount'], 2);
            }

            // 计算输赢
            $money = bcadd($user->money, $win_amount, 2);
            
            // 扣税
            $money = bcsub($money, $tax_fee, 2);
            $transfer_amount = bcsub($transfer_amount, $tax_fee, 2);

            // 加钱
            if($params['amount'] > 0){
                $user['money'] = $money;
                $user['total_profit'] = bcadd($user->total_profit, $transfer_amount, 2);
                $user['today_profit'] = bcadd($user->today_profit, $transfer_amount, 2);
                $user->save();
            }

            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            \think\Log::record($params,'PP_BetResult_Error');
            return json_encode([
                "error"         => 100,
                "description"   => "Internal  server  error.",
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
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
        
        
        return json_encode([
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
            "currency"          => "BRL",
            "cash"              => (float)$money,
            "bonus"             => 0,
        ]);
    }

    /**
     * 免费旋转奖励 no
     */
    public function BonusWin()
    {
        $params = $this->request->param();

        $insert_data['type'] = 'PP_BonusWin' . date('H:i:s');
        $insert_data['result_json'] = json_encode($params);
        $insert_data['createtime'] = time();
        $res = db('pg_result')->insert($insert_data);

        $user = \app\common\model\User::where('id', $params['userId'])->find();
        if(!$user){
            return json_encode([
                "error" => 10,
                "description" => "User not found",
            ]);
        }
        //查找记录
        $transaction_id = $params['reference'];
      
        return json_encode([
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
            "currency"          => "BRL",
            "cash"              => (float)$user->money,
            "bonus"             => 0,
        ]);
    }

    /**
     * 返还
     */
    public function Refund()
    {
        $params = $this->request->param();
        \think\Log::record($params,'pp_Refund');

        $insert_data['type'] = 'PP_Refund'.date('H:i:s');
        $insert_data['result_json'] = json_encode($params);
        $insert_data['createtime'] = time();
        $res = db('pg_result')->insert($insert_data);

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($params['userId']);
            if(!$user){
                Db::rollback();
                return json_encode([
                    "error"             => 10,
                    "description"       => "User not found",
                ]);
            }

            // 查找记录
            $transaction_id = $params['reference'];
            $log = db("pp_record")->where(['transaction_id' => $transaction_id])->find();
            if (!$log || $log['status']) {
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     =>$transaction_id,
                ]);
            }

            $game_id = 'Refund';

            // 计算输赢
            $amount = (float)$log['amount'];
            $money = bcadd($user->money, $amount, 2);
            $transfer_amount = $amount;
            $bet_amount = 0;
            $win_amount = $amount;

            // 加钱
            if($amount > 0){
                $user->userdata->total_profit = bcsub($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->save();

                $user->money = $money;
                $user->save();
            }

            // 返还后更新状态
            db("pp_record")->where('transaction_id',$transaction_id)->update(['status'=>1]);
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            \think\Log::record($e->getMessage(),'PP_Refund_Error');
            return json_encode([
                "error"             => 100,
                "description"       => "Internal  server  error.",
            ]);
        }
        // 添加到ES表中
        $game['id'] = $game_id;
        $game['image'] = '';

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
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
        ]);

    }

    /**
     * 累计奖池中奖
     */
    public function JackpotWin()
    {
        $param = $this->request->param();
        \think\Log::record($param,'pp_JackpotWin');
        $insert_data['type'] = 'PP_JackpotWin'.date('H:i:s');
        $insert_data['result_json'] = json_encode($param);
        $insert_data['createtime'] = time();
        $res = db('pg_result')->insert($insert_data);

        $user = \app\common\model\User::where('id', $param['userId'])->find();
        if(!$user){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        // 游戏id
        $game_id = $param['gameId'];
        $pos = strpos($game_id, '_');
        if($pos !== false){
            $game_id = substr($game_id, $pos + 1);
        }
        
        $game = db('game_' . $this->platform)->where('game_id', $game_id)->cache(true)->find();

        Db::startTrans();
        try{
            //取出结算数据
            $transaction_id = $param['reference'];
            $amount = (float)$param['amount'];

            $log = db("pp_record")->where(['transaction_id' => $transaction_id])->find();
            if($log){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     => $transaction_id,
                    "currency"          => "BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             => 0,
                ]);
            }

            $user = \app\common\model\User::lock(true)->find($param['userId']);

            $pp_res = db("pp_record")->insert([
                'transaction_id'    => $transaction_id,
                'amount'            => $amount,
                'createtime'        => time()
            ]);
            if(!$pp_res){
                Db::rollback();
                return json_encode([
                    "error"         => 0,
                    "description"   => "Success",
                    "transactionId" => $transaction_id,
                    "currency"      => "BRL",
                    "cash"          => (float)$user->money,
                    "bonus"         => 0,
                ]);
            }

            // 计算输赢
            $money = bcadd($user->money, $amount, 2);
            $transfer_amount = $amount;
            $bet_amount = 0;
            $win_amount = $amount;
            // 加钱
            if($amount > 0){
                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->save();

                $user->money = $money;
                $user->save();
            }
           
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            \think\Log::record($e->getMessage(),'PP_JackpotWin_Error');
            return json_encode([
                "error"         => 100,
                "description"   => "Internal  server  error.",
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
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

        return json_encode([
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
            "currency"          => "BRL",
            "cash"              => (float)$money,
            "bonus"             => 0,
        ]);
    }

    /**
     * 竞标赛中奖
     */
    public function PromoWin()
    {
        $param = $this->request->param();
        \think\Log::record($param,'pp_PromoWin');
        $insert_data['type'] = 'PP_PromoWin'.date('H:i:s');
        $insert_data['result_json'] = json_encode($param);
        $insert_data['createtime'] = time();
        $res = db('pg_result')->insert($insert_data);

        $user = \app\common\model\User::where('id', $param['userId'])->find();
        if(!$user){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        $transaction_id = $param['reference'];

        Db::startTrans();
        try{
            $log = db("pp_record")->where(['transaction_id' => $transaction_id])->find();
            if($log){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     => $transaction_id,
                    "currency"          => "BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             => 0
                ]);
            }

            $user = \app\common\model\User::lock(true)->find($param['userId']);
            $amount = (float)$param['amount'];
            $pp_res = db("pp_record")->insert([
                'transaction_id'    => $transaction_id,
                'amount'            => $amount,
                'createtime'        => time()
            ]);

            if(!$pp_res){
                Db::rollback();
                return json_encode([
                    "error"         => 0,
                    "description"   => "Success",
                    "transactionId" => $transaction_id,
                    "currency"      => "BRL",
                    "cash"          => (float)$user->money,
                    "bonus"         => 0
                ]);
            }
            //游戏ID
            $game_id = $param['campaignId'].'_'.$param['campaignType'];
            $game = db('game_' . $this->platform)->where('game_id', $game_id)->cache(true)->find();

            //计算输赢
            $money = bcadd($user->money, $amount, 2);
            $transfer_amount = $amount;
            $bet_amount = 0;
            $win_amount = $amount;
            //加钱
            if($amount>0){
                $user['money'] = $money;
                $user['total_profit'] = bcadd($user->total_profit, $transfer_amount, 2);
                $user['today_profit'] = bcadd($user->today_profit, $transfer_amount, 2);
                $user->save();
            }
            //大奖记录
            $prize_record_data = [
                'user_id'=> $user['id'],
                'game_id'=> '0',
                'game_type'=> 'PP-Promo'.$game_id,
                'bet_amount'=> $bet_amount,
                'win_amount'=> $win_amount,
                'createtime'=> time(),
            ];
            \db('prize_record')->insert($prize_record_data);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json_encode([
                "error" => 100,
                "description" => "Internal  server  error.",
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
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

        return json_encode([
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
            "currency"          => "BRL",
            "cash"              => (float)$money,
            "bonus"             => 0
        ]);
    }

    /**
     * 调整
     */
    public function Adjustment()
    {
        $param = $this->request->param();
        \think\Log::record($param,'pp_Adjustment');

        $user = \app\common\model\User::where('id', $param['userId'])->find();
        if(!$user){
            return json_encode([
                "error"         => 10,
                "description"   => "User not found",
            ]);
        }

        // 传过来的数据
        $game_id = $param['gameId'];
        $game = db('game_' . $this->platform)->where('game_id', $game_id)->cache(true)->find();

        $roundId = $param['roundId'];
        $transaction_id =  $param['reference'];

        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($param['userId']);
            $log = db("pp_record")->where(['transaction_id' => $transaction_id])->find();
            if($log){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     => $transaction_id,
                    "currency"          => "BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             => 0
                ]);
            }

            $pp_res = db("pp_record")->insert([
                'transaction_id'    =>$transaction_id,
                'amount'            =>$param['amount'],
                'createtime'        =>time()
            ]);

            if(!$pp_res){
                Db::rollback();
                return json_encode([
                    "error"             => 0,
                    "description"       => "Success",
                    "transactionId"     => $transaction_id,
                    "currency"          => "BRL",
                    "cash"              => (float)$user->money,
                    "bonus"             => 0
                ]);
            }

            // 计算余额
            $amount = $param['amount'];
            $transfer_amount = $amount;
            $bet_amount =  0;
            $win_amount = 0;
            $money = bcadd($user->money, $amount, 2);
            if($money < 0){
                Db::rollback();
                return json_encode([
                    "error" => 1,
                    "description" => "Insufficient balance",
                ]);
            }

            // 修改用户信息
            $user['money'] = $money;
            $user->save();
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json_encode([
                "error"         => 100,
                "description"   => "Internal  server  error.",
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
        $es = new Es();
        $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

        return json_encode([
            "error"             => 0,
            "description"       => "Success",
            "transactionId"     => $transaction_id,
            "currency"          => "BRL",
            "cash"              => (float)$money,
            "bonus"             => 0
        ]);
    }

    /**
     * 端圆
     */
    public function Endround()
    {
        $params = $this->request->param();
        $user = \app\common\model\User::lock(true)->find($params['userId']);
        if (!$user) {
            return json_encode([
                "error" => 10,
                "description" => "User not found",
            ]);
        }

        //传过来的数据
        $game_id = $params['gameId'];
        $roundId = $params['roundId'];

        //结束回合
        return json_encode([
            "error"         => 0,
            "description"   => "Success",
            "cash"          => (float)$user->money,
            "bonus"         => 0,
        ]);
    }

}