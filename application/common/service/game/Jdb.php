<?php

namespace app\common\service\game;

use app\common\model\game\Platform;
use app\common\service\Base;
use app\common\service\util\Es;
use app\common\service\util\Notice;
use app\common\service\util\Sign;
use Exception;
use fast\Http;
use think\Db;

class Jdb extends Base
{
    /**
     * 配置
     */
    protected $config;

    /**
     * Es表名
     */
    protected $gameRecord = 'jdb_game_record';
    
    /**
     * 厂商
     */
    protected $platform = 'jdb';

    protected $parentName = null;
    protected $key = null;
    protected $iv = null;
    protected $gameUrl = null;
    protected $dc = null;

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

        $this->parentName = $this->config['parentName'];
        $this->dc = $this->config['dc'];
        $this->key = $this->config['key'];
        $this->iv = $this->config['iv'];
        $this->gameUrl = $this->config['gameUrl'];

        // 实例化模型
        $this->model = new \app\common\model\game\Jdb();
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
        
        $language = 'pt';
        
        $data = [
            'action'        => 21,
            'ts'            => time() * 1000,
            'uid'           => $this->parentName . $user->id,
            'parent'        => $this->parentName,
            'balance'       => $user->money,
            'gType'         => $game->game_type,
            'mType'         => $game->game_id,
            'lang'          => $language
        ];

        $encryptData = Sign::encrypt(json_encode($data, true), $this->key, $this->iv);
       
        $api_url = $this->gameUrl . $this->dc . '&x=' . $encryptData;

        $res = Http::get($api_url);
        $res = json_decode($res, true);
        
        if($res['status'] != '0000'){
            $this->error(__('获取游戏链接失败'));
        }

        $retval = [
            'game_url' => $res['path'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取最新游戏
     */
    public function getNewGame()
    {
        $language = 'pt';
        
        $data = [
            'action'        => 49,
            'ts'            => time() * 1000,
            'parent'        => $this->parentName,
            'lang'          => $language
        ];
        
        $encryptData = Sign::encrypt(json_encode($data, true), $this->key, $this->iv);
        $api_url = $this->gameUrl . $this->dc . '&x=' . $encryptData;

        $res = Http::get($api_url);
        $res = json_decode($res, true);
        
        if($res['status'] != '0000'){
            $this->error(__('获取失败'));
        }

        // 当前更新厂商
        // 按照jdb游戏厂商从上到下顺序归类platform
        $arr = [
            0 => 1, 7 => 1, 9 => 1, 12 => 1, 18 => 1, 
            22 => 2, 
            66 => 3, 67 => 3,
            30 => 4, 31 => 4, 32 => 4,
            41 => 5,
            58 => 6, 59 => 6, 60 => 6,
            57 => 7, 75 => 7,
            80 => 8, 81 => 8,
            90 => 9, 91 => 9, 92 => 9, 93 => 9,
            101 => 10,
            50 => 11,
            120 => 12,
            130 => 13, 131 => 13,
            140 => 14, 141 => 14, 142 => 14,
            160 => 15, 161 => 15, 162 => 15,
            150 => 16
        ];
        
        // 已有游戏
        $jdb = $this->model->column('game_type', 'game_id');

        $params = [];
        foreach($res['data'] as $val){
            foreach($val['list'] as $v){
                if(!isset($jdb[$v['mType']])){
                    $params[] = [
                        'platform'    => $arr[$val['gType']],
                        'game_id'     => $v['mType'],
                        'game_type'   => $val['gType'],
                        'game_name'   => $v['name'],
                        'image'       => $v['image'],
                        'status'      => 1,
                        'is_works'    => 1,
                        'weigh'       => 100,
                        'createtime'  => datetime(time()),
                    ];
                }
            }
        }

        if(!empty($params)){
            $this->model->saveAll($params);
        }

        $retval = [
            'list' => $params ?: $res['data'],
            'total' => count($params)
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 统一调用
     */
    public function action()
    {
        $params = $this->request->param();

        // 解密
        $data = Sign::decrypt($params['x'], $this->key, $this->iv);
        // \think\Log::record($data, 'action');


        $data = json_decode($data, true);

        if(!$data){
            return json_encode([
                "status"    => '9999',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }

        $data['uid'] = str_replace($this->parentName, '', $data['uid']);

        // 函数名集合
        $actionArr = [
            4       => 'CancelTransferInOut',
            6       => 'userBalance',
            8       => 'TransferInOut',
            9       => 'TransferOut',
            10      => 'TransferIn',
            11      => 'CancelTransferOut',
            12      => 'ActiveReward',
            13      => 'TakeOut',
            14      => 'SaveIn',
            15      => 'CancelTakeOut',
        ];

        try {
            // \think\Log::record($data, 'action222');
            $action = $data['action'];

            if(!isset($actionArr[$action])){
                return json_encode([
                    "status"    => '9007',
                    "balance"   => 0,
                    "err_text"  => "Unknow action"
                ]);
            }
            
            $method = $actionArr[$data['action']];
            return $this->$method($data);
        }catch(Exception $e){
            \think\Log::record($e->getMessage(), 'error');
        }
        
    }

    /**
     * 获取用户钱包
     */
    protected function userBalance($params)
    {
        if($params['currency'] != 'BR'){
            return json_encode([
                "status"    => '9999',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }

        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            $data = [
                "status"=> '7501',
                "balance"=> 0,
                "err_text" => "Failed"
            ];
        }else{
            $data = [
                "status"=> '0000',
                "balance"=> $user['money'],
                "err_text" => "ok"
            ];
        }

        return json_encode($data);
    }

    /**
     * 下注结算
     */
    protected function TransferInOut($params)
    {
        // 检测货币
        if($params['currency'] != 'BR'){
             return json_encode([
                 "status"   => '9999',
                 "balance"  => 0,
                 "err_text" => "Failed"
             ]);
         }

        // 检测用户
        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"    => '7501',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }

        // 检测游戏
        $game_id = $params['mType'];
        $game = $this->model->where('game_id', $game_id)->find();
        
        if(!$game){
            return json_encode([
                "status"    => '9999',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }

        $params['bet'] = bcmul($params['bet'], -1, 2);
        $transaction_id = $params['transferId'];
        $bet_amount = $params['bet']; 
        $transfer_amount = $params['netWin'];
        $win_amount = $params['win'];

        // 税费计算
        $tax = $this->config['tax'];
        $tax_fee =  $win_amount * $tax;

        $es = new Es;
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);

            $bet_money_remain = bcsub($user->money, $bet_amount, 2);
            if($bet_money_remain < 0){
                Db::rollback();
                return json_encode([
                "status"   => '6006',
                "balance"  => 0,
                "err_text" => "Failed"
                ]);
             }

            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                Db::rollback();
                return json_encode([
                    "status"       => '6006',
                    "balance"      => 0,
                    "err_text"     => "Failed"
                ]);
            }
 
            // 扣税
            $money = bcsub($money, $tax_fee, 2);
            $transfer_amount = bcsub($transfer_amount, $tax_fee, 2);

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

                //插入用户资金流水记录
                $moneyLogRecordParam['body'] = [
                    'user_id'               => $user['id'],
                    'money'                 => $params['netWin'],
                    'before'                => $user['money'],
                    'after'                 => $money,
                    'memo'                  => 'JDB投注付彩',
                    'transaction_id'        => $params['transferId'],
                    'admin_id'              => $user['admin_id'],
                    'createtime'            => time(),
                ];

                $moneyLogRecordParam['index'] = 'user_money_log';
                $moneyLogRecordParam['type'] = '_doc';
                $es->add($moneyLogRecordParam);

                $user->userdata->total_bet = bcadd($user->userdata->total_bet, $params['bet'] * $game['bet_rate'], 2);
                $user->userdata->today_bet = bcadd($user->userdata->today_bet, $params['bet'] * $game['bet_rate'], 2);
                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->bet_count += 1; // 下注次数

                $user->userdata->save();

                $user->money = $money;
                $user->save();
             }

             Db::commit();
        }catch(\Exception $e){
            \think\Log::record($e->getMessage(), 'JDB_TransferInOut_Error');
            Db::rollback();
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);

        $data = [
            "status"    => '0000',
            "balance"   => $user['money'],
            "err_text"  => "OK"
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 取消下注结算
     */
    public function CancelTransferInOut($params) 
    {
        $es = new Es();

        $log = $es->searchGameRecord($params['transferId'], $this->gameRecord);

        if(!$log){
            return json_encode([
                "status"    => '0000',
                "err_text"  => "ok"
            ]);
        }

        // 检测游戏
        $game_id = $params['mType'];
        $game = $this->model->where('game_id', $game_id)->find();
        
        if(!$game){
            return json_encode([
                "status"    => '9999',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }
    $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"    => '7501',
                "err_text"  => "Failed"
            ]);
        }

        $transfer_amount = bcmul($log['transfer_amount'], -1, 2);
        $bet_amount =  $log['bet_amount'];
        $win_amount = $log['win_amount'];

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);

            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                return json_encode([
                    "status"    => '6006',
                    "err_text"  => "Failed"
                ]);
            }

            if($transfer_amount != 0){

                $moneyLogRecordParam['body'] = array(
                    'user_id'       => $user['id'],
                    'money'         => $transfer_amount,
                    'before'        => $user['money'],
                    'after'         => $money,
                    'memo'          => 'JDB取消下注结算',
                    'transaction_id'=> $params['transferId'],
                    'admin_id'      => $user['admin_id'],
                    'createtime'    => time(),
                );
                $moneyLogRecordParam['index'] = 'user_money_log';
                $moneyLogRecordParam['type'] = '_doc';
                $es->add($moneyLogRecordParam);

                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $log['transaction_id'],
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 0,
                ];
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->save();

                $user->money = $money;
                $user->save();
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
        }

        $data = [
            "status"        => '0000',
            "err_text"      => "OK"
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 下注
     */
    protected function TransferOut($params) 
    {
        if($params['currency'] != 'BR'){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }

    $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"        => '7501',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }

        $game_id = $params['mType'];
        $game = $this->model->where('game_id', $game_id)->find();
        if(!$game){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }

        $es = new Es;
        Db::startTrans();
        try{
            $transfer_amount = bcmul($params['amount'], -1, 2);

            $user = \app\common\model\User::lock(true)->find($user['id']);
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                return json_encode([
                    "status"        => '6006',
                    "balance"       => 0,
                    "err_text"      => "Failed"
                ]);
            }

            if($transfer_amount != 0){
                    // 添加ES记录
                    $betInfoArr = [
                        'transaction_id'    => $params['transferId'],
                        'bet_amount'        => $params['amount'],
                        'win_amount'        => 0,
                        'transfer_amount'   => 0,
                        'is_fake'           => 0,
                    ];
                    
                    $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);
                    
                    $moneyLogRecordParam['body'] = array(
                        'user_id'       => $user['id'],
                        'money'         => $transfer_amount,
                        'before'        => $user['money'],
                        'after'         => $money,
                        'memo'          => 'JDB投注',
                        'transaction_id'=> $params['transferId'],
                        'admin_id'      => $user['admin_id'],
                        'createtime'    => time(),
                    );
                    $moneyLogRecordParam['index'] = 'user_money_log';
                    $moneyLogRecordParam['type'] = '_doc';
                    $es->add($moneyLogRecordParam);
                    
                    $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                    $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                    $user->userdata->save();

                    $user->money = $money;
                    $user->save();
                }
            
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }

        $data = [
            "status"        => '0000',
            "balance"       => $user['money'],
            "err_text"      => "OK"
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 结算
     */
    protected function TransferIn($params) 
    {
        if($params['currency'] != 'BR'){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);

        }
        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"        => '7501',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }

        $game_id = $params['mType'];
        $game = $this->model->where('game_id', $game_id)->find();
        if(!$game){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }
        $transfer_amount = $params['netWin'];
        $win_amount = $params['win'];
        $bet_amount = $params['bet'];
        $validBet = $params['validBet'];

        // 税费计算
        $tax = $this->config['tax'];
        $tax_fee =  $win_amount * $tax;

        $es = new Es;
        
        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            $money = bcadd($user->money, $win_amount, 2);

            // 扣税
            $money = bcsub($money,$tax_fee,2);
            $transfer_amount = bcsub($transfer_amount,$tax_fee,2);
            
            if ($money < 0) {
                return json_encode([
                    "status"        => '6006',
                    "balance"       => 0,
                    "err_text"      => "Failed"
                ]);
            }

            // 添加ES记录
            $betInfoArr = [
                'transaction_id'    => $params['transferId'],
                'bet_amount'        => $params['bet'],
                'win_amount'        => $params['win'],
                'transfer_amount'   => $params['netWin'],
                'is_fake'           => 0,
            ];
            $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

            $user->userdata->total_bet = bcadd($validBet * $game['bet_rate'], $user->userdata->total_bet, 2);
            $user->userdata->today_bet = bcadd($validBet * $game['bet_rate'], $user->userdata->today_bet, 2);
            $user->userdata->total_profit = bcadd($user->userdata->total_profit, $win_amount, 2);
            $user->userdata->today_profit = bcadd($user->userdata->today_profit, $win_amount, 2);
            $user->userdata->save();

            $user->money = $money;
            $user->save();
            
            Db::commit();
        }catch (\Exception $e){
            \think\Log::record($e->getMessage(), 'Jdb_TransferIn_error');
            Db::rollback();
        }

        // 大奖通知
        Notice::handlingGameAwards($user, $game, $bet_amount, $win_amount, $this->platform);

        $data = [
            "status"        => '0000',
            "balance"       =>  $user['money'],
            "err_text"      => "OK"
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 活動派彩
     */
    protected function ActiveReward($params) 
    {
        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"=> '7501',
                "balance"=> 0,
                "err_text" => "Failed"
            ]);
        }

        $es = new Es;

        $esLog = $es->searchByTransactionId($params['transferId'], 'user_money_log');
        $log = count($esLog) > 0;

        if($log){
            return json_encode([
                "status"        => '0000',
                "balance"       => $user['money'],
                "err_text"      => "ok"
            ]);
        }

        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($user['id']);
            $money = bcadd($user->money, $params['amount'], 2);
            if ($money < 0) {
                return json_encode([
                    "status"=> '6006',
                    "balance"=> 0,
                    "err_text" => "Failed"
                ]);
            }

            if(!$log){
                $moneyLogRecordParam['body'] = [
                    'user_id'           => $user['id'],
                    'money'             => $params['amount'],
                    'before'            => $user['money'],
                    'after'             => $money,
                    'memo'              => 'JDB活动派彩',
                    'transaction_id'    => $params['transferId'],
                    'root_invite'       => $user['root_invite'],
                    'createtime'        => time(),
                ];
                $moneyLogRecordParam['index'] = 'user_money_log';
                $moneyLogRecordParam['type'] = '_doc';
                $es->add($moneyLogRecordParam);

                $user->money = $money;
                $user->save();
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => $e->getMessage()
            ]);
        }

        $data = [
            "data"=>[
                "status"=> '0000',
                "balance"=> $user['money'],
                "err_text" => "ok"
            ],
            "error"=>null
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 取款
     */
    protected function TakeOut($params) 
    {

       

        if($params['currency'] != 'BR'){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }
        
        $es = new Es();

        $esLog = $es->searchByTransactionId($params['transferId'], 'user_money_log');
        $log = count($esLog) > 0;

        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"        => '7501',
                "err_text"      => "Failed"
            ]);
        }

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);

            $transfer_amount = bcmul($params['amount'], -1, 2);
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                return json_encode([
                    "status"        => '6006',
                    "balance"       => 0,
                    "err_text"      => "Failed"
                ]);
            }

            if(!$log){
                if($params['transfer_amount'] != 0){
                    $moneyLogRecordParam['body'] = [
                        'user_id'       => $user['id'],
                        'money'         => $transfer_amount,
                        'before'        => $user['money'],
                        'after'         => $money,
                        'memo'          => 'Jdb取款',
                        'transaction_id'=> $params['transferId'],
                        'admin_id'      => $user['admin_id'],
                        'createtime'    => time(),
                    ];
                    $moneyLogRecordParam['index'] = 'user_money_log';
                    $moneyLogRecordParam['type'] = '_doc';
                    $es->add($moneyLogRecordParam);

                    $user->money = $money;
                    $user->save();
                }
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
        }

        $data = [
            "status"    => '0000',
            "err_text"  => "OK"
        ];
        return json_encode($data);
    }


    /**
     * @return void
     * 取消取款
     */
    protected function CancelTakeOut($params) 
    {

        if($params['currency'] != 'BR'){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }

        $es = new Es();
        $log = false;
        $esLog = $es->searchByTransactionId($params['transferId'], 'user_money_log');
        $log = count($esLog) > 0;

        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"    => '7501',
                "err_text"  => "Failed"
            ]);
        }

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
            $transfer_amount = $params['amount'];
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                return json_encode([
                    "status"=> '6006',
                    "balance"=> 0,
                    "err_text" => "Failed"
                ]);
            }

            if(!$log){
                if($params['transfer_amount'] != 0){
                    $moneyLogRecordParam['body'] = [
                        'user_id'           => $user['id'],
                        'money'             => $transfer_amount,
                        'before'            => $user['money'],
                        'after'             => $money,
                        'memo'              => 'Jdb取消取款',
                        'transaction_id'    => $params['transferId'],
                        'admin_id'          => $user['admin_id'],
                        'createtime'        => time(),
                    ];

                    $moneyLogRecordParam['index'] = 'user_money_log';
                    $moneyLogRecordParam['type'] = '_doc';
                    $es->add($moneyLogRecordParam);

                    $user->money = $money;
                    $user->save();
                }
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
        }

        $data = [
            "status"    => '0000',
            "err_text"  => "OK"
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 取消下注
     */
    public function CancelTransferOut($params) 
    {
        if($params['currency'] != 'BR'){
            return json_encode([
                "status"        => '9999',
                "balance"       => 0,
                "err_text"      => "Failed"
            ]);
        }
        
        // \think\Log::record($params, 'CancelTransferOut1');
        // 检测游戏
        $game_id = $params['mType'];
        $game = $this->model->where('game_id', $game_id)->find();
        // \think\Log::record($game, 'CancelTransferOut2');
        if(!$game){
            return json_encode([
                "status"    => '9999',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }

        $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"    => '7501',
                "err_text"  => "Failed"
            ]);
        }
        
        $es = new Es();

        // $log = $es->searchGameRecord($params['transferId'], $this->gameRecord);

        // if(!$log){
        //     return json_encode([
        //         "status"    => '0000',
        //         "err_text"  => "ok"
        //     ]);
        // }


        $transfer_amount = $params['amount'];
        $win_amount = $params['amount'];
        $bet_amount =  0;

        Db::startTrans();
        try{
            $user = \app\common\model\User::lock(true)->find($user['id']);
       
            $money = bcadd($user->money, $transfer_amount, 2);
            if($money < 0){
                return json_encode([
                    "status"        => '6006',
                    "err_text"      => "Failed"
                ]);
            }

            if($transfer_amount != 0){
                // $moneyLogRecordParam['body'] = array(
                //     'user_id'       => $user['id'],
                //     'money'         => $transfer_amount,
                //     'before'        => $user['money'],
                //     'after'         => $money,
                //     'memo'          => 'JDB取消下注',
                //     'transaction_id'=> $params['transferId'],
                //     'admin_id'      => $user['admin_id'],
                //     'createtime'    => time(),
                // );
                // $moneyLogRecordParam['index'] = 'user_money_log';
                // $moneyLogRecordParam['type'] = '_doc';
                // $es->add($moneyLogRecordParam);

                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $params['transferId'],
                    // 'transaction_id'    => $log['transaction_id'],
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => $transfer_amount,
                    'is_fake'           => 0,
                ];
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->save();

                $user->money = $money;
                $user->save();

                // $jackpot = db('jackpot')->where(['game_id'=> $log['game_id'], 'type'=> 2])->find();
                // if ($jackpot) {
                //     db('jackpot')->where(['id'=> $jackpot['id']])->update(['real_jackpot_amount'=> bcadd($jackpot['real_jackpot_amount'], $transfer_amount, 2), 'jackpot_amount'=> bcadd($jackpot['jackpot_amount'], $transfer_amount, 2), 'updatetime'=> time()]);
                // }

           
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
        }

        $data = [
            "status"        => '0000',
            "balance"       =>  $money,
            "err_text"      => ""
        ];
        return json_encode($data);
    }

    /**
     * @return void
     * 存款
     */
    public function SaveIn($params) 
    {
        if($params['currency'] != 'BR'){
            return json_encode([
                "status"=> '9999',
                "balance"=> 0,
                "err_text" => "Failed"
            ]);
        }

        $es = new Es();

        $log = $es->searchGameRecord($params['transferId'], $this->gameRecord);

        if(!$log){
            return json_encode([
                "status"    => '0000',
                "err_text"  => "ok"
            ]);
        }

        // 检测游戏
        $game_id = $params['mType'];
        $game = $this->model->where('game_id', $game_id)->find();
        
        if(!$game){
            return json_encode([
                "status"    => '9999',
                "balance"   => 0,
                "err_text"  => "Failed"
            ]);
        }

         $user_id = str_replace('hermesgame', '', $params['uid']);
    $user = \app\common\model\User::where('id', $params['uid'])->find();
        if(!$user){
            return json_encode([
                "status"    => '7501',
                "err_text"  => "Failed"
            ]);
        }

        $transfer_amount = $params['amount'];
        $bet_amount =  $params['totalBet'];
        $win_amount = $params['amount'];

        Db::startTrans();
        try {
            $user = \app\common\model\User::lock(true)->find($user['id']);

            $money = bcadd($user->money, $transfer_amount, 2);
            if ($money < 0) {
                return json_encode([
                    "status"    => '6006',
                    "err_text"  => "Failed"
                ]);
            }

            if ($transfer_amount != 0) {

                $moneyLogRecordParam['body'] = array(
                    'user_id'       => $user['id'],
                    'money'         => $transfer_amount,
                    'before'        => $user['money'],
                    'after'         => $money,
                    'memo'          => 'JDB存款',
                    'transaction_id'=> $params['transferId'],
                    'admin_id'      => $user['admin_id'],
                    'createtime'    => time(),
                );
                $moneyLogRecordParam['index'] = 'user_money_log';
                $moneyLogRecordParam['type'] = '_doc';
                $es->add($moneyLogRecordParam);

                // 添加ES记录
                $betInfoArr = [
                    'transaction_id'    => $log['transaction_id'],
                    'bet_amount'        => $bet_amount,
                    'win_amount'        => $win_amount,
                    'transfer_amount'   => bcsub($bet_amount, $win_amount, 2),
                    'is_fake'           => 0,
                ];
                $es->addGameRecord($user, $game, $betInfoArr, $this->gameRecord);

                $user->userdata->total_profit = bcadd($user->userdata->total_profit, $transfer_amount, 2);
                $user->userdata->today_profit = bcadd($user->userdata->today_profit, $transfer_amount, 2);
                $user->userdata->save();

                $user->money = $money;
                $user->save();
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
        }

        $data = [
            "status"        => '0000',
            "err_text"      => "OK"
        ];
        return json_encode($data);
    }


}