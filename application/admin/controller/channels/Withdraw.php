<?php

namespace app\admin\controller\channels;

use app\admin\model\channels\Channel;
use app\common\controller\Backend;
use app\common\model\game\Jdb;
use app\common\model\game\Omg;
use app\common\model\Recharge;
use app\common\model\User;
use app\common\service\Channel as ServiceChannel;
use app\common\service\util\Es;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Withdraw extends Backend
{

    /**
     * Withdraw模型对象
     * @var \app\admin\model\channels\Withdraw
     */
    protected $model = null;
    protected $dataLimit = 'department';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\channels\Withdraw;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        ini_set('memory_limit', '512M');
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['admin','admindata','user','wallet', 'userdata'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->getRelation('admin')->visible(['nickname']);
                $row->getRelation('admindata')->visible(['invite_code']);
				$row->getRelation('user')->visible(['username', 'money', 'origin', 'role', 'remark']);
				$row->getRelation('wallet')->visible(['name', 'area_code','phone_number','pix_type','chave_pix','cpf','pix','is_default']);
            }

            $withdraw = $this->model
                ->with(['admin','admindata','user','wallet', 'userdata'])
                ->where($where)
                ->select();

            $total_withdraw = 0; // 总提现金额
            $total_withdraw_num = count($withdraw); // 总提现笔数
            $success_withdraw = 0; // 成功提现笔数

            $today_withdraw = 0; // 今日提现金额
            $today_withdraw_num = 0; // 今日提现笔数
            $today_success_withdraw = 0; // 今日成功提现笔数

            $yestoday_withdraw = 0; // 昨日提现金额
            $yestoday_withdraw_num = 0; // 昨日提现笔数
            $yestoday_success_withdraw = 0; // 昨日成功提现笔数

            $today_time = strtotime(date('Ymd'));
            $yestoday_time = strtotime(date('Ymd', strtotime('-1 day')));
            foreach ($withdraw as $row) {
                if ($row->status == 1) {
                    $total_withdraw += $row->money;
                    $success_withdraw ++;
                }

                // 今日
                if(strtotime($row->paytime) >= $today_time){
                    if($row->status == 1){
                        $today_withdraw += $row->money;
                        $today_success_withdraw ++;
                    }
                }

                if(strtotime($row->createtime) >= $today_time){
                    $today_withdraw_num ++;
                }

                // 昨日
                if(strtotime($row->paytime) >= $yestoday_time && strtotime($row->paytime) < $today_time){
                    if($row->status == 1){
                        $yestoday_withdraw += $row->money;
                        $yestoday_success_withdraw ++;
                    }
                }

                if(strtotime($row->createtime) >= $yestoday_time && strtotime($row->createtime) < $today_time){
                    $yestoday_withdraw_num ++;
                }
            }
            $retval = [
                'total_withdraw'            => sprintf('%.2f', $total_withdraw),
                'total_withdraw_num'        => $total_withdraw_num,
                'success_withdraw'          => $success_withdraw,
                'today_withdraw'            => sprintf('%.2f', $today_withdraw),
                'today_withdraw_num'        => $today_withdraw_num,
                'today_success_withdraw'    => $today_success_withdraw,
                'yestoday_withdraw'         => sprintf('%.2f', $yestoday_withdraw),
                'yestoday_withdraw_num'     => $yestoday_withdraw_num,
                'yestoday_success_withdraw' => $yestoday_success_withdraw,
            ];
            $result = array("total" => $list->total(), "rows" => $list->items(), 'retval' => $retval);

            return json($result);
        }
        // 提现按钮关闭的了通道
        $channel = Channel::where('state', 0)->column('name');
        $this->assignconfig('channel', $channel);
        return $this->view->fetch();
    }

    /**
     * 查看凭证
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
              $this->error(__('You have no permission'));
        }
           
        if (false === $this->request->isPost()) {
            $channel = Channel::where('id', $row->channel_id)->find();
            if(!$channel){
                $this->error('通道未找到或已关闭');
            }
            
            // 提现配置
            $config = $channel->withdraw_config;
            if(!$config){
                // 充值配置不存在
                $this->error('提现配置不存在');
            }

            // 有提供凭证链接的
            $urlArr = [
                'CEPAY'     => 'https://proof.cepays.com/receipt.html?ClientCode={transaction_id}',
                'OUROPAGO'  => "https://api.xdpag.com/receipt/{transaction_id}/payout",
                'U2CPAY'    => 'https://front.novavexis.com/#/payment/prove?paymentSn={transaction_id}&currency=BRL'
            ];
            
            $tradeFieldArr = [
                'CEPAY'         => 'transaction_id',
                'OUROPAGO'      => 'endToEndId',
                'U2CPAY'        => 'orderNo'
            ];

            $keyArr = array_keys($urlArr);
            
            $url = '';
             if(in_array($channel->name, $keyArr)){
                $config = json_decode($config, true);
                
                // 调用对应支付通道函数
                $method = trim(strtolower($channel->name)) . 'Query';
                
                $res = ServiceChannel::$method($config, $row);
                // dd($res);
                $url = $urlArr[$channel->name] ?? '';
                $transaction_id = $res[$tradeFieldArr[$channel->name]] ?? '';
                
                $arr = [
                    '{transaction_id}' => $transaction_id,
                ];
                $url = strtr($url, $arr);
            }
            
            $this->view->assign('url', $url);
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
    }

    
    /**
     * 成功通知但未处理的
     */
    public function notify($ids = null)
    {
        $row = $this->model->get($ids);
        if($row->status != 4){
            $this->error('该操作只适用于付款中的订单! ');
        }

        $channel = Channel::where('id', $row->channel_id)->find();
        if(!$channel){
            $this->error('通道未找到或已关闭');
        }
        
        // // 提现配置
        // $config = $channel->withdraw_config;
        // if(!$config){
        //     // 充值配置不存在
        //     $this->error('提现配置不存在');
        // }

        // $config = json_decode($config, true);
                
        // // 调用对应支付通道函数
        // $method = trim(strtolower($channel->name)) . 'Query';
        
        // $res = ServiceChannel::$method($config, $row);

        $remark = $channel->name . ' PAY';
        $withdraw = new \app\common\service\Withdraw();
        $res = $withdraw->notify($row, $remark);
        $this->success('手动回调成功');
    }


    /**
     * 拒绝提现
     */
    public function refuse($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
              $this->error(__('You have no permission'));
        }
           
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }

        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
      
        if(!in_array($row->status, [0, 5])){
            $this->error('订单未找到, 或已操作! ');
        }

        // 直接修改状态2
        $params['status'] = 2;

        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);

            // 返回金额到用户钱包
            // 数据准备
            $data = [
                'withdraw_return' => [
                    'money'                 => $row->money,
                    'typing_amount_limit'   => 0,
                    'transaction_id'        => $row->order_no, // 记录表id
                    'status'                => 0,
                ],
            ];
            if(User::insertLog($row->user, $data) === false){
                $result = false;
            }
            if($result != false){
                Db::commit();
            }
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('操作失败, 多次失败请联系管理员'));
        }
        $this->success('操作成功');
    }

    /**
     * 代付
     */
    public function pay($ids = null, $channel_name = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $channel = Channel::where('state', 1)->select();
            // 按钮颜色
            $colorArr = ['success', 'danger', 'warning', 'info', 'primary'];
            foreach($channel as $key => $val){
                $val->color_class = $colorArr[$key%5] ?? 'success';
            }
            $this->view->assign('channel', $channel);
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }

        $channel = Channel::where('name', $channel_name)->where('state', 1)->find();
        if(!$channel){
            $this->error('通道未找到或已关闭');
        }
        
        // 提现配置
        $config = $channel->withdraw_config;
        if(!$config){
            // 充值配置不存在
            $this->error('提现配置不存在');
        }
        
        $config = json_decode($config, true);
         
        // 调用对应支付通道函数
        $method = trim(strtolower($channel->name)) . 'Withdraw';
       
        $res = ServiceChannel::$method($config, $row);
        // dd($res);
        if($res['code'] == 0){
            $this->error($channel_name . '代付失败! 原因: ' . $res['msg'] . "\n  上游提示有误, 联系开发处理");
        }

        // 改成正在付款中 以及使用的通道id
        $row->status = 4;
        $row->channel_id = $channel->id;
        $result = $row->save();

        if($result === false){
            $this->error('操作失败, 多次失败请联系管理员');
        }

        $this->success('操作成功');
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $arr = [
                0 => '审核中',
                1 => '通过(成功付款)',
                2 => '拒绝(退款)',
                3 => '失败(不退款)',
                4 => '付款中',
            ];
            $this->view->assign('arr', $arr);
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            // $params['channel_id'] = 3;
            $result = $row->allowField(true)->save($params);

            if($params['status'] == 2){
                // 数据准备
                $data = [
                    'withdraw_return' => [
                        'money'                 => $row->money,
                        'typing_amount_limit'   => 0,
                        'transaction_id'        => $row->order_no,
                        'status'                => 0,
                    ],
                ];
                if(User::insertLog($row->user, $data) === false){
                    $result = false;
                }
            }
           if($result != false){
                Db::commit();
            }
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 下级数据
     */
    public function subuser($user_id = null, $withdraw_id = null)
    {
        // 所有下三级用户
        $users = User::getSubUsers($user_id);
        // dd($users);

        $user_ids = []; // 所有用户id
        $oneLevelIds = []; // 一级用户id
        $twoLevelIds = []; // 二级用户id
        $threeLevelIds = []; // 三级用户id

        // 总的有效用户
        $valid_users = 0;
        $one_valid_users = 0;
        $two_valid_users = 0;
        $three_valid_users = 0;
        foreach($users as $val){
            if($val['is_valid'] == 1){
                $valid_users ++;
            }

            $user_ids[] = $val['id'];
            if($val['rank'] == 1){
                $oneLevelIds[] = $val['id'];

                if($val['is_valid'] == 1){
                    $one_valid_users ++;
                }
            }elseif($val['rank'] == 2){
                $twoLevelIds[] = $val['id'];

                if($val['is_valid'] == 1){
                    $two_valid_users ++;
                }
            }elseif($val['rank'] == 3){
                $threeLevelIds[] = $val['id'];

                if($val['is_valid'] == 1){
                    $three_valid_users ++;
                }
            }
        }

        // 有效用户
        $validArr = [
            1 => $one_valid_users,
            2 => $two_valid_users,
            3 => $three_valid_users,
            4 => $valid_users,
        ];

        // 人数数组
        $peopleArr = [
            1 => count($oneLevelIds),
            2 => count($twoLevelIds),
            3 => count($threeLevelIds),
            4 => count($user_ids),
        ];

        // 充值记录
        $where['user_id'] = ['in', $user_ids];
        $where['status'] = 1;
        $recharge = Recharge::where($where)->group('user_id')->field('user_id,sum(money) as money, count(id) as num')->select()->toarray();

        // dd($recharge);
        // 人数
        $total_recharge_num = 0;
        $one_total_recharge_num = 0;
        $two_total_recharge_num = 0;
        $three_total_recharge_num = 0;

        // 金额
        $total_recharge_money = 0;
        $one_total_recharge_money = 0;
        $two_total_recharge_money = 0;
        $three_total_recharge_money = 0;
        foreach ($recharge as $val) {
            $total_recharge_money += $val['money'];
            $total_recharge_num ++;

            if(in_array($val['user_id'], $oneLevelIds)){
                $one_total_recharge_money += $val['money'];
                $one_total_recharge_num ++;

            }elseif(in_array($val['user_id'], $twoLevelIds)){
                $two_total_recharge_money += $val['money'];
                $two_total_recharge_num ++;

            }elseif(in_array($val['user_id'], $threeLevelIds)){
                $three_total_recharge_money += $val['money'];
                $three_total_recharge_num ++;
            }
        }

        // 充值人数数组
        $rechargeNumArr = [
            1 => $one_total_recharge_num,
            2 => $two_total_recharge_num,
            3 => $three_total_recharge_num,
            4 => $total_recharge_num,
        ];


        // 充值金额数组
        $rechargeMoneyArr = [
            1 => $one_total_recharge_money,
            2 => $two_total_recharge_money,
            3 => $three_total_recharge_money,
            4 => $total_recharge_money,
        ];

        // 平均充值金额
        $avgRechargeMoneyArr = [
            1 => $one_total_recharge_num ? $one_total_recharge_money / $one_total_recharge_num : 0,
            2 => $two_total_recharge_num ? $two_total_recharge_money / $two_total_recharge_num : 0,
            3 => $three_total_recharge_num ? $three_total_recharge_money / $three_total_recharge_num : 0,
            4 => $total_recharge_num ? $total_recharge_money / $total_recharge_num : 0,
        ];

        // 提现记录
        $withdraw = $this->model->where($where)->group('user_id')->field('user_id,sum(money) as money, count(id) as num')->select();

        // 提现人数
        $total_withdraw_num = 0;
        $one_total_withdraw_num = 0;
        $two_total_withdraw_num = 0;
        $three_total_withdraw_num = 0;

        // 提现金额
        $total_withdraw = 0;
        $one_total_withdraw = 0;
        $two_total_withdraw = 0;
        $three_total_withdraw = 0;
        foreach ($withdraw as $val) {
            $total_withdraw += $val['money'];
            $total_withdraw_num ++;

            if(in_array($val['user_id'], $oneLevelIds)){
                $one_total_withdraw += $val['money'];
                $one_total_withdraw_num ++;

            }elseif(in_array($val['user_id'], $twoLevelIds)){
                $two_total_withdraw += $val['money'];
                $two_total_withdraw_num ++;

            }elseif(in_array($val['user_id'], $threeLevelIds)){
                $three_total_withdraw += $val['money'];
                $three_total_withdraw_num ++;
            }
        }

        // 提现人数数组
        $withdrawNumArr = [
            1 => $one_total_withdraw_num,
            2 => $two_total_withdraw_num,
            3 => $three_total_withdraw_num,
            4 => $total_withdraw_num,
        ];

        // 提现金额数组
        $withdrawMoneyArr = [
            1 => $one_total_withdraw,
            2 => $two_total_withdraw,
            3 => $three_total_withdraw,
            4 => $total_withdraw,
        ];

        // 等级数组
        $levelArr = [
            1 => '下级',
            2 => '下二级',
            3 => '下三级',
            4 => '总计'
        ];

        $retval = [];
        for($i = 1; $i <= 4; $i++){
            $retval[$i - 1]['level'] = $levelArr[$i] ?? '';
            $retval[$i - 1]['total_user'] = $peopleArr[$i] ?? 0;
            $retval[$i - 1]['valid_user'] = $validArr[$i] ?? 0;
            $retval[$i - 1]['total_recharge_num'] = $rechargeNumArr[$i] ?? 0;
            $retval[$i - 1]['total_recharge_money'] = $rechargeMoneyArr[$i] ?? 0;
            $retval[$i - 1]['avg_recharge_money'] = sprintf('%.2f', $avgRechargeMoneyArr[$i]) ?? 0;
            $retval[$i - 1]['total_withdraw_num'] = $withdrawNumArr[$i] ?? 0;
            $retval[$i - 1]['total_withdraw_money'] = $withdrawMoneyArr[$i] ?? 0;
        }

        $es = new Es();

        $condition = [
            // 用户id搜索
            [
                'type' => 'term',
                'field' => 'user_id',
                'value' =>  $user_id,
            ],
        ];

        $platformList = Omg::getPlatform();
        $omgGroupSearch = $es->groupAggregation('omg_game_record', $condition, 'platform', ['win_amount', 'bet_amount', 'transfer_amount']);
        $gameData = [];
        foreach($platformList as $key => $val){
            if(isset($omgGroupSearch[$key])){
                $gameData[$key]['win_amount'] = $omgGroupSearch[$key]['win_amount_sum'] ?? 0;
                $gameData[$key]['bet_amount'] = $omgGroupSearch[$key]['bet_amount_sum'] ?? 0;
                $gameData[$key]['transfer_amount'] = $omgGroupSearch[$key]['transfer_amount_sum'] ?? 0;
                $gameData[$key]['platform'] = $platformList[$key];
            }else{
                $gameData[$key]['win_amount'] = 0;
                $gameData[$key]['bet_amount'] = 0;
                $gameData[$key]['transfer_amount'] = 0;
                $gameData[$key]['platform'] = $platformList[$key];
            }
        }   

        $jdbPlatformList = Jdb::getPlatform();
        $jdbGroupSearch = $es->groupAggregation('jdb_game_record', $condition, 'platform', ['win_amount', 'bet_amount', 'transfer_amount']);
        $jdbGameData = [];
        foreach($jdbPlatformList as $key => $val){
            if(isset($jdbGroupSearch[$key])){
                $jdbGameData[$key]['win_amount'] = $jdbGroupSearch[$key]['win_amount_sum'] ?? 0;
                $jdbGameData[$key]['bet_amount'] = $jdbGroupSearch[$key]['bet_amount_sum'] ?? 0;
                $jdbGameData[$key]['transfer_amount'] = $jdbGroupSearch[$key]['transfer_amount_sum'] ?? 0;
                $jdbGameData[$key]['platform'] = $jdbPlatformList[$key];
            }else{
                $jdbGameData[$key]['win_amount'] = 0;
                $jdbGameData[$key]['bet_amount'] = 0;
                $jdbGameData[$key]['transfer_amount'] = 0;
                $jdbGameData[$key]['platform'] = $jdbPlatformList[$key];
            }
        }

        $cur_withdraw = db('withdraw')->where('user_id', $user_id)->where('id', $withdraw_id)->value('money');

        $titleArr = [
            'total_recharge_money'        => db('recharge')->where('user_id', $user_id)->where('status', 1)->sum('money'),
            'cur_withdraw'                => $cur_withdraw,
            'total_withdraw'              => db('withdraw')->where('user_id', $user_id)->where('status', 1)->sum('money'),
        ];

        $gameData = array_merge($gameData, $jdbGameData);
        $extend = [
            'valid_users'   => $valid_users,
            'game_data'     => array_values($gameData),
            'titleArr'         => $titleArr
        ];
        $this->assign('extend', $extend);
        $this->assign('retval', $retval);
        return $this->fetch();
    }
}
