<?php

namespace app\admin\controller\user;

use app\admin\model\channels\Recharge;
use app\admin\model\channels\Withdraw;
use app\common\model\User;
use app\common\controller\Backend;
use app\common\model\game\Jdb;
use app\common\model\game\Omg;
use app\common\service\util\Es;
use think\Db;

/**
 * 多表格示例
 *
 * @icon fa fa-table
 * @remark 当一个页面上存在多个Bootstrap-table时该如何控制按钮和表格
 */
class Userdata extends Backend
{
    protected $model = null;
    // protected $noNeedRight = ['moneylog', 'rewardlog', 'recharge', 'withdraw', 'subuser'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 查看
     */
    public function index($ids = null)
    {
        $this->assign('ids', $ids);
        $this->assignconfig('user_id', $ids);
        return $this->view->fetch();
    }

    /**
     * 余额明细
     */
    public function moneylog()
    {
        $this->model = model('\app\admin\model\user\Moneylog');
        $this->dataLimit = 'department';
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
                    ->with(['user','admin'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['username']);
				$row->getRelation('admin')->visible(['username']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch('index');
    }

    /**
     * 奖励明细
     */
    public function rewardlog()
    {
        $this->model = model('\app\admin\model\user\Rewardlog');
        $this->dataLimit = 'department';

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
                    ->with(['user','admin'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['username']);
				$row->getRelation('admin')->visible(['username']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch('index');
    }

    /**
     * 充值
     */
    public function recharge()
    {
        $this->dataLimit = 'department';
        
        $this->model = new Recharge();
        // 语言包加载
        $this->loadlang('channels.recharge');

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
                    ->with(['admindata','user','channel'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->visible(['id','user_id','channel_id','order_no','money','real_amount','real_pay_amount','status','paytime','createtime']);
                $row->visible(['admindata']);
				$row->getRelation('admindata')->visible(['invite_code']);
				$row->visible(['user']);
				$row->getRelation('user')->visible(['username']);
				$row->visible(['channel']);
				$row->getRelation('channel')->visible(['title','name']);
            }

            $recharge = $this->model
                ->with(['admindata','user','channel'])
                ->where($where)
                ->select();
            $total_recharge = 0;
            $total_recharge_num = count($recharge);
            $success_recharge = 0;

            $today_recharge = 0; // 今日充值金额
            $today_recharge_num = 0; // 今日充值笔数
            $today_success_recharge = 0; // 今日成功充值笔数

            $yestoday_recharge = 0; // 昨日充值金额
            $yestoday_recharge_num = 0; // 昨日充值笔数
            $yestoday_success_recharge = 0; // 昨日成功充值笔数

            $today_time = strtotime(date('Ymd'));
            $yestoday_time = strtotime(date('Ymd', strtotime('-1 day')));

            foreach ($recharge as $row) {
                if ($row->status == 1) {
                    $total_recharge += $row->money;
                    $success_recharge ++;
                }

                // 今日
                if(strtotime($row->paytime) >= $today_time){
                    if($row->status == 1){
                        $today_recharge += $row->money;
                        $today_success_recharge ++;
                    }
                }

                if(strtotime($row->createtime) >= $today_time){
                    $today_recharge_num ++;
                }

                // 昨日
                if(strtotime($row->paytime) >= $yestoday_time && strtotime($row->paytime) < $today_time){
                    if($row->status == 1){
                        $yestoday_recharge += $row->money;
                        $yestoday_success_recharge ++;
                    }
                }

                if(strtotime($row->createtime) >= $yestoday_time && strtotime($row->createtime) < $today_time){
                    $yestoday_recharge_num ++;
                }
            }
            $retval = [
                'total_recharge'            => sprintf('%.2f', $total_recharge),
                'total_recharge_num'        => $total_recharge_num,
                'success_recharge'          => $success_recharge,
                'today_recharge'            => sprintf('%.2f', $today_recharge),
                'today_recharge_num'        => $today_recharge_num,
                'today_success_recharge'    => $today_success_recharge,
                'yestoday_recharge'         => sprintf('%.2f', $yestoday_recharge),
                'yestoday_recharge_num'     => $yestoday_recharge_num,
                'yestoday_success_recharge' => $yestoday_success_recharge,
            ];
            $result = array("total" => $list->total(), "rows" => $list->items(), 'retval' => $retval);

            return json($result);
        }
        return $this->view->fetch('index');
    }

    /**
     * 提现
     */
    public function withdraw()
    {
        $this->dataLimit = 'department';

        $this->model = new Withdraw();

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
                    ->with(['admindata','user','wallet', 'userdata'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('admindata')->visible(['invite_code']);
				$row->getRelation('user')->visible(['username', 'money', 'origin', 'role']);
				$row->getRelation('wallet')->visible(['name', 'area_code','phone_number','pix_type','chave_pix','cpf','pix','is_default']);
            }

            $withdraw = $this->model
                ->with(['admindata','user','wallet', 'userdata'])
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
        return $this->view->fetch('index');
    }

    /**
     * 下级数据(掉绑前)
     */
    public function subuser()
    {
        ini_set('memory_limit', '512M'); 
        $this->dataLimit = 'department';

        if ($this->request->isAjax()) {
            $filter = json_decode($this->request->get('filter'), true);
            $user_id = $filter['user_id'];
            $row = User::get($user_id);
            $invite_code = $row->invite_code;

            $map['be_invite_code'] = $invite_code;
            $map['is_test'] = 0;
            $one_users = User::where($map)->select();

            // ===================下级数据===================
            $oneLevelIds = []; // 一级用户id
            // 一级有效用户数
            $one_valid_users = 0;
            // 一级流水
            $one_total_bet = 0;
            foreach($one_users as $val){
                if(User::isValidUser($val)){
                    $one_valid_users ++;
                }
                $oneLevelIds[] = $val['id'];
                $one_total_bet += $val->userdata->total_bet;
            }

            // ========= 二级数据 ========
            $twoLevelIds = []; // 二级用户id
            // 二级有效用户数
            $two_valid_users = 0;
            // 二级流水
            $two_total_bet = 0;
            if(!empty($oneLevelIds)){
                $two_users = User::where('parent_id', 'in', $oneLevelIds)->select();
                foreach($two_users as $val){
                    if(User::isValidUser($val)){
                        $two_valid_users ++;
                    }
                    $twoLevelIds[] = $val['id'];
                    $two_total_bet += $val->userdata->total_bet;
                }
            }

            // ========= 三级数据 ========
            $threeLevelIds = []; // 三级用户id
            // 三级有效用户数
            $three_valid_users = 0;
            // 三级流水
            $three_total_bet = 0;
            if(!empty($twoLevelIds)){
                $three_users = User::where('parent_id', 'in', $twoLevelIds)->select();
           
                foreach($three_users as $val){
                    if(User::isValidUser($val)){
                        $three_valid_users ++;
                    }
                    $threeLevelIds[] = $val['id'];
                    $three_total_bet += $val->userdata->total_bet;
                }
            }

            // 合并所有用户id
            $user_ids = array_merge($oneLevelIds, $twoLevelIds, $threeLevelIds);

            // 总的有效用户数
            $valid_users = $one_valid_users + $two_valid_users + $three_valid_users;

            // 总流水
            $total_bet = $one_total_bet + $two_total_bet + $three_total_bet;

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

            // 流水数组
            $betArr = [
                1 => $one_total_bet,
                2 => $two_total_bet,
                3 => $three_total_bet,
                4 => $total_bet,
            ];
            // dd($peopleArr);

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
            $withdraw = Withdraw::where($where)->group('user_id')->field('user_id,sum(money) as money, count(id) as num')->select();

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
                $retval[$i - 1]['total_recharge_money'] = sprintf('%.2f', $rechargeMoneyArr[$i]) ?? 0;
                $retval[$i - 1]['avg_recharge_money'] = sprintf('%.2f', $avgRechargeMoneyArr[$i]) ?? 0;
                $retval[$i - 1]['total_withdraw_num'] = $withdrawNumArr[$i] ?? 0;
                $retval[$i - 1]['total_withdraw_money'] = sprintf('%.2f', $withdrawMoneyArr[$i]) ?? 0;
                $retval[$i - 1]['total_bet'] = sprintf('%.2f', $betArr[$i]) ?? 0;
            }

            // 工资
            $salary = db('user_reward_log')->whereIn('type', ['admin_bonus', 'return_money'])->where('user_id', $user_id)->where('status', 1)->sum('money');

            // 系统分佣
            $commission = db('user_reward_log')->whereIn('type', ['direct', 'indirect'])->where('user_id', $user_id)->where('status', 1)->sum('money');

            $extend = [
                'salary'        => $salary,
                'commission'    => $commission,
                'valid_users'   => $valid_users,
            ];
            // dd($oneLevelIds);
            return json(['total' => 4, 'rows' => $retval, "extend" => $extend]);
        }
    }

    /**
     * 下级数据
     */
    public function unbind()
    {
        ini_set('memory_limit', '512M'); 
        $this->dataLimit = 'department';

        if ($this->request->isAjax()) {
            $filter = json_decode($this->request->get('filter'), true);
            $user_id = $filter['user_id'];
            
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
            // 总的流水
            $one_total_bet = 0;
            $two_total_bet = 0;
            $three_total_bet = 0;
            $total_bet = 0;
            foreach($users as $val){
                if($val['is_valid'] == 1){
                    $valid_users ++;
                }

                $user_ids[] = $val['id'];

                $total_bet += $val['total_bet'];
                
                if($val['rank'] == 1){
                    $oneLevelIds[] = $val['id'];

                    $one_total_bet += $val['total_bet'];

                    if($val['is_valid'] == 1){
                        $one_valid_users ++;
                    }
                }elseif($val['rank'] == 2){
                    $twoLevelIds[] = $val['id'];

                    $two_total_bet += $val['total_bet'];

                    if($val['is_valid'] == 1){
                        $two_valid_users ++;
                    }
                }elseif($val['rank'] == 3){
                    $threeLevelIds[] = $val['id'];

                    $three_total_bet += $val['total_bet'];

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
            
             // 流水数组
            $betArr = [
                1 => $one_total_bet,
                2 => $two_total_bet,
                3 => $three_total_bet,
                4 => $total_bet,
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
            $withdraw = Withdraw::where($where)->group('user_id')->field('user_id,sum(money) as money, count(id) as num')->select();

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
                $retval[$i - 1]['total_recharge_money'] = sprintf('%.2f', $rechargeMoneyArr[$i]) ?? 0;
                $retval[$i - 1]['avg_recharge_money'] = sprintf('%.2f', $avgRechargeMoneyArr[$i]) ?? 0;
                $retval[$i - 1]['total_withdraw_num'] = $withdrawNumArr[$i] ?? 0;
                $retval[$i - 1]['total_withdraw_money'] = sprintf('%.2f', $withdrawMoneyArr[$i]) ?? 0;
                $retval[$i - 1]['total_bet'] = sprintf('%.2f', $betArr[$i]) ?? 0;
            }

            // 工资
            $salary = db('user_reward_log')->whereIn('type', ['admin_bonus', 'return_money'])->where('user_id', $user_id)->where('status', 1)->sum('money');

            // 系统分佣
            $commission = db('user_reward_log')->whereIn('type', ['direct', 'indirect'])->where('user_id', $user_id)->where('status', 1)->sum('money');

            $extend = [
                'salary'        => $salary,
                'commission'    => $commission,
                'valid_users'   => $valid_users,
            ];
            // dd($oneLevelIds);
            return json(['total' => 4, 'rows' => $retval, "extend" => $extend]);
        }
    }
}
