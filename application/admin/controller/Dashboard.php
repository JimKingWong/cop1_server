<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\model\channels\Recharge;
use app\common\model\User;
use app\admin\model\channels\Withdraw;


/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{
    public function index()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '5096M');

        $origin = $this->request->get('origin');
        $adminIds = \app\admin\model\department\Admin::getChildrenAdminIds($this->auth->id, true);
        
        if ($this->auth->role < 2) {
            $adminIds[] = 0;
        }

        $todayTime = strtotime(date('Y-m-d'));
        $yesterdayTime = strtotime('-1 day', $todayTime);
        
        $where['admin_id'] = ['in', $adminIds];
        if($origin != ''){
            $origin_user_ids = User::where('origin', $origin)->column('id');
            $where['id'] = ['in', $origin_user_ids];
        }

        $usernameArr = db('user')->where($where)->where('is_test', 0)->column('username', 'id');

        // dd($counters);
        // ===========总的统计数据===========
        $userCount = db('user')->where($where)->where('is_test', 0)->count(); // 用户总数
        $rechargeUserCount = db('user')->where($where)->where('is_test', 0)->where('is_first_recharge', 1)->count(); // 已充值玩家数
        $money = db('user')->where($where)->where('is_test', 0)->sum('money'); // 玩家余额

        $datafields = "SUM(CASE WHEN total_bet > 29.99 AND total_recharge > 19.99 THEN 1 ELSE 0 END) as dabiao1,";
        $datafields .= "SUM(CASE WHEN total_bet < 30 AND total_recharge > 19.99 AND total_profit < -18.50 THEN 1 ELSE 0 END) as dabiao2";
        $dabiao = db('user_data')->field($datafields)->find();
        // dd($dabiao1);
        // 有效玩家数
        $validUserCount = $dabiao['dabiao1'] + $dabiao['dabiao2']; // 有效玩家数

        // 博主uid
        $bl_money = db('user')->where($where)->where('role', 1)->sum('money'); // 博主余额
        $bl_user_ids = db('user')->where($where)->where('role', 1)->column('id');

        // ==========今日的统计数据===========
        $todayUserCount = db('user')->where($where)->where('is_test', 0)->whereTime('jointime', 'today')->count(); // 今日新增用户数
        $todayLoginUserCount = db('user')->where($where)->where('is_test', 0)->whereTime('logintime', 'today')->count(); // 今日活跃用户数

        // ==========昨日的统计数据===========
        $yesterdayUserCount = db('user')->where($where)->where('is_test', 0)->whereTime('jointime', 'yesterday')->count(); // 昨日新增用户数
        $yesterdayLoginUserCount = db('user')->where($where)->where('is_test', 0)->whereTime('logintime', 'yesterday')->count(); // 昨日活跃用户数

        if($origin != ''){
            $where['user_id'] = $where['id'];
            unset($where['id']);
        }
        
        // 昨天今天的充值
        $todayRecharge = db('recharge')->where($where)->whereTime('paytime', 'today')->where('status', '1')->field('id,user_id,money,paytime')->select();
        $todayRechargeUser = []; // 今日充值用户
        $todayRechargeMoney = 0; // 今日充值金额
        foreach($todayRecharge as $val){
            $todayRechargeUser[$val['user_id']][] = $val;
            $todayRechargeMoney += $val['money'];
        }
        // dd($todayRechargeMoney);
        // 今日充值人数
        $todayRechargeCount = count($todayRechargeUser);
        $todayRepeatRechargeCount = 0; // 今日新玩家复充人数
        foreach($todayRechargeUser as $val){
            if(count($val) > 1) {
                $todayRepeatRechargeCount ++;
            }
        }
        // 今日新玩家复充人数
        $todayOldRepeatRechargeCount = $todayRechargeCount - $todayRepeatRechargeCount;
        
        $yesterdayRecharge = db('recharge')->where($where)->whereTime('paytime', 'yesterday')->where('status', '1')->field('id,user_id,money,paytime')->select();
        $yesterdayRechargeUser = []; // 昨日充值用户
        $yesterdayRechargeMoney = 0; // 昨日充值金额
        foreach($yesterdayRecharge as $val){
            $yesterdayRechargeUser[$val['user_id']][] = $val;
            $yesterdayRechargeMoney += $val['money'];
        }
        // 昨日充值人数
        $yesterdayRechargeUserCount = count($yesterdayRechargeUser);
        $yesterdayRepeatRechargeCount = 0; // 昨日新玩家复充人数
        foreach($yesterdayRechargeUser as $val){
            if(count($val) > 1) {
                $yesterdayRepeatRechargeCount ++;
            }
        }
        // 昨日新玩家复充人数
        $yesterdayOldRepeatRechargeCount = $yesterdayRechargeUserCount - $yesterdayRepeatRechargeCount;

        // 今天的提现
        $todayWithdraw = db('withdraw')->where($where)->whereTime('paytime', 'today')->where('status', '1')->field('id,user_id,money,paytime')->select();

        // 今日提现金额
        $todayWithdrawMoney = 0;
        foreach($todayWithdraw as $val){
            $todayWithdrawMoney += $val['money'];
        }

        // 昨天的提现
        $yesterdayWithdraw = db('withdraw')->where($where)->whereTime('paytime', 'yesterday')->where('status', '1')->field('id,user_id,money,paytime')->select();

        // 昨日提现金额
        $yesterdayWithdrawMoney = 0;
        foreach($yesterdayWithdraw as $val){
            $yesterdayWithdrawMoney += $val['money'];
        }

        // 排名颜色
        $colorArr = ['text-red', 'text-green', 'text-yellow'];
        // 排行榜优化（直接SQL排序取前20）
        $rechargeRank = db('recharge')
            ->where($where)
            ->where('status', '1')
            ->field('user_id,SUM(money) AS money')
            ->group('user_id')
            ->order('money DESC')
            ->limit(20)
            ->select();
        foreach($rechargeRank as $key => $val) {
            // 处理每个排名的数据
            $rechargeRank[$key]['rank'] = $key < 3 ? '<span class="'.$colorArr[$key].'">'.($key + 1).'</span>' : ($key + 1);
            $rechargeRank[$key]['username'] = $usernameArr[$val['user_id']] ?? '';
        }

        $withdrawRank = db('withdraw')
            ->where($where)
            ->where('status', '1')
            ->field('user_id,SUM(money) AS money')
            ->group('user_id')
            ->order('money DESC')
            ->limit(20)
            ->select();
        foreach($withdrawRank as $key => $val) {
            // 处理每个排名的数据
            $withdrawRank[$key]['rank'] = $key < 3 ? '<span class="'.$colorArr[$key].'">'.($key + 1).'</span>' : ($key + 1);
            $withdrawRank[$key]['username'] = $usernameArr[$val['user_id']] ?? '';
        }

        // 博主相关统计（使用单独查询）
        $blWhere = ['user_id' => ['in', $bl_user_ids]];
        $bl_rechargeMoney = db('recharge')->where($blWhere)->where($where)->where('status', '1')->sum('money'); // 博主充值
        $bl_withdrawMoney = db('withdraw')->where($blWhere)->where($where)->where('status', '1')->sum('money'); // 博主提现

        // 今日博主提现
        $today_bl_WithdrawMoney = db('withdraw')->where($blWhere)->where($where)->where('status', '1')->whereTime('paytime', 'today')->sum('money');
        // 昨日博主提现
        $yesterday_bl_WithdrawMoney = db('withdraw')->where($blWhere)->where($where)->where('status', '1')->whereTime('paytime', 'yesterday')->sum('money');

        // 日账统计优化
        $daybookData = db('daybookadmin')
            ->where(['admin_id' => ['in', $adminIds]])
            ->field([
                'SUM(api_amount) AS api_fee',
                'SUM(channel_fee) AS channel_fee',
                'SUM(recharge_amount) AS lj_recharge',
                'SUM(withdraw_amount) AS lj_withdraw',
                'SUM(profit_and_loss) AS total_profit',
                'SUM(IF(date LIKE "'.date('Y-m').'%", profit_and_loss, 0)) AS month_profit',
            ])
            ->find();

        $lj_rechargeMoney = $daybookData['lj_recharge'] ?? 0; // 累计充值金额
        $lj_withdrawMoney = $daybookData['lj_withdraw'] ?? 0; // 累计提现金额
        $apiFee = $daybookData['api_fee'] ?? 0; // API费用
        $channelFee = $daybookData['channel_fee'] ?? 0; // 通道费用
        $profit_and_loss = $daybookData['total_profit'] ?? 0; // 总利润
        $monthlyData = $daybookData['month_profit'] ?? 0; // 月利润

        $rechargeMoney = db('recharge')->where($where)->where('status', '1')->sum('money'); // 总充值
        $withdrawMoney = db('withdraw')->where($where)->where('status', '1')->sum('money'); // 总提现
        $user_recharegeMoney = $rechargeMoney - $bl_rechargeMoney; // 用户充值
        $user_withdrawMoney = $withdrawMoney - $bl_withdrawMoney; // 用户提现
        $withdrawRate = $user_recharegeMoney ? round($user_withdrawMoney / $user_recharegeMoney, 4) * 100 : 0; // 提现率

        // 工资统计优化
        $salaryTypes = ['admin_bonus', 'refund_money'];
        $salaryData = db('user_money_log')
            ->where($where)
            ->where(['type' => ['in', $salaryTypes]])
            ->field([
                'SUM(money) AS total_salary',
                'SUM(IF(type = "admin_bonus", money, 0)) AS bl_salary',
                'SUM(IF(type = "refund_money", money, 0)) AS total_refund',
                'SUM(IF(type = "admin_bonus" AND createtime >= "'.$todayTime.'", money, 0)) AS today_salary',
                'SUM(IF(type = "admin_bonus" AND createtime BETWEEN "'.$yesterdayTime.'" AND "'.$todayTime.'", money, 0)) AS yesterday_salary',
                'SUM(IF(type = "refund_money" AND createtime >= "'.$todayTime.'", money, 0)) AS today_return_salary',
                'SUM(IF(type = "refund_money" AND createtime BETWEEN "'.$yesterdayTime.'" AND "'.$todayTime.'", money, 0)) AS yesterday_return_salary',
            ])
            ->find();

        // dd(db('user_money_log')->where('type', 'refund_money')->sum('money'));
        $total_salary = $salaryData['total_salary'] ?? 0; // 总工资
        $bl_return_salary = $salaryData['total_refund'] ?? 0; // 工资退款
        $bl_salary = $salaryData['bl_salary'] ?? 0; // 工资退款

        $today_bl_salary = $salaryData['today_salary'] ?? 0; // 今日博主工资
        $yesterday_bl_salary = $salaryData['yesterday_salary'] ?? 0; // 昨日博主工资

        $today_bl_return_salary = $salaryData['today_return_salary'] ?? 0; // 今日博主退款
        $yesterday_bl_return_salary = $salaryData['yesterday_return_salary'] ?? 0; // 昨日博主退款

        // 返回数据
        $retval = [
            'total' => [
                'user_count'            => $userCount, 
                'recharge_user_count'   => $rechargeUserCount,
                'money'                 => sprintf('%.2f', $money),
                'bl_money'              => sprintf('%.2f', $bl_money),
                'valid_user_count'      => $validUserCount,

                'recharge_money'        => sprintf('%.2f', $rechargeMoney),
                'user_recharege_money'  => sprintf('%.2f', $user_recharegeMoney),
                'bl_recharge_money'     => sprintf('%.2f', $bl_rechargeMoney),

                'withdraw_money'        => sprintf('%.2f', $withdrawMoney),
                'bl_withdraw_money'     => sprintf('%.2f', $bl_withdrawMoney),
                'user_withdraw_money'   => sprintf('%.2f', $user_withdrawMoney),
                'withdraw_rate'         => $withdrawRate,

                'bl_salary'             => sprintf('%.2f', $bl_salary),
                'bl_return_salary'      => sprintf('%.2f', $bl_return_salary),
                'salary'                => sprintf('%.2f', $total_salary),
                'rechare_salary'        => $bl_withdrawMoney ? sprintf('%.2f', $user_recharegeMoney / $bl_withdrawMoney) : 0,


                'lj_recharge_money'     => $lj_rechargeMoney,
                'lj_withdraw_money'     => $lj_withdrawMoney,
                'api_fee'               => $apiFee,
                'channel_fee'           => $channelFee,
                'profit_and_loss'       => sprintf('%.2f', $profit_and_loss),
                'profit_month'          => sprintf('%.2f', $monthlyData),
            ],
            'today' => [
                'user_count'                => $todayUserCount,
                'recharge_count'            => $todayRechargeCount,
                'recharge_money'            => $todayRechargeMoney,
                'withdraw_money'            => $todayWithdrawMoney,
                'login_user_count'          => $todayLoginUserCount,
                'repeat_recharge_count'     => $todayRepeatRechargeCount,
                'old_repeat_recharge_count' => $todayOldRepeatRechargeCount,
                'bl_salary'                 => sprintf('%.2f', $today_bl_salary),
                'bl_withdraw_money'         => sprintf('%.2f', $today_bl_WithdrawMoney),
                'bl_return_salary'          => sprintf('%.2f', $today_bl_return_salary),
            ],
            'yesterday' => [
                'user_count'                => $yesterdayUserCount,
                'recharge_count'            => $yesterdayRechargeUserCount,
                'recharge_money'            => $yesterdayRechargeMoney,
                'withdraw_money'            => $yesterdayWithdrawMoney,
                'login_user_count'          => $yesterdayLoginUserCount,
                'repeat_recharge_count'     => $yesterdayRepeatRechargeCount,
                'old_repeat_recharge_count' => $yesterdayOldRepeatRechargeCount,
                'bl_salary'                 => sprintf('%.2f', $yesterday_bl_salary),
                'bl_withdraw_money'         => sprintf('%.2f', $yesterday_bl_WithdrawMoney),
                'bl_return_salary'          => sprintf('%.2f', $yesterday_bl_return_salary),
            ],
            'rank' => [
                'recharge_rank_user' => $rechargeRank,
                'withdraw_rank_user' => $withdrawRank,
            ]
        ];

        $this->assign('retval', $retval);

        $site = db('site')->where('status', 1)->field('url id,url name')->order('createtime desc')->select();
        $this->assign('site', json_encode($site));
        $this->assign('origin', $origin);
        return $this->view->fetch();
    }
    
    /**
     * 查看
     */
    public function index1()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '5096M');
        $origin = $this->request->get('origin');

        if($origin != ''){
            $origin_user_ids = User::where('origin', $origin)->column('id');
            $where['id'] = ['in', $origin_user_ids];
        }

        // 获取部门下面的所有子管理员
        $admin_ids = \app\admin\model\department\Admin::getChildrenAdminIds($this->auth->id, true);
        if($this->auth->role < 2){
            array_push($admin_ids, 0);
        }
        // 当前管理员下面的所有子管理员
        // $admin_ids = $this->auth->getChildrenAdminIds(true);
        
        $where['admin_id'] = ['in', $admin_ids];
        $users = User::where($where)->where('is_test', 0)->select();
        // 下面的表为user_id, 所以需要把id换成user_id
        if(isset($where['id'])){
            $where['user_id'] = $where['id'];
            unset($where['id']);
        }
        
        // 今日凌晨时间
        $time = strtotime(date('Y-m-d', time()));
        // 昨天凌晨时间
        $yesterday_time = strtotime(date('Y-m-d', strtotime('-1 day')));

        // 已充值玩家
        $rechargeUserCount = 0;
        // 今日新玩家
        $todayUserCount = 0;
        // 今日活跃玩家
        $todayLoginUserCount = 0;
        // 玩家余额
        $money = 0;

        // 达标1
        $dabiao1 = 0;
        // 达标2
        $dabiao2 = 0;

        // 昨日注册人数
        $yestodayUserCount = 0;

        // 今日用户id
        $user_ids = [];
        // 昨日用户id
        $yesterday_user_ids = [];
        // 昨日活跃玩家
        $yesterdayLoginUserCount = 0;
        // 博主用户id
        $bl_user_ids = [];
        // 博主余额
        $bl_money = 0;
        foreach($users as $val){
            if($val['createtime'] >= $time){
                $todayUserCount ++;
                $user_ids[] = $val['id'];
            }

            if($val['logintime'] >= $time){
                $todayLoginUserCount ++;
            }

            if($val['logintime'] >= $yesterday_time && $val['logintime'] < $time){
                $yesterdayLoginUserCount ++;
            }

            if($val['is_first_recharge'] == 1){
                $rechargeUserCount ++;
            }

            $money += $val['money'];

            if($val->userdata->total_bet > 29.99 && $val->userdata->total_recharge > 19.99){
                $dabiao1 ++;
            }

            if($val->userdata->total_bet < 30 && $val->userdata->total_recharge > 19.99 && $val->userdata->total_profit < -18.50){
                $dabiao2 ++;
            }

            if($val['createtime'] >= $yesterday_time && $val['createtime'] < $time){
                $yestodayUserCount ++;
                $yesterday_user_ids[] = $val['id'];
            }

            if($val['role'] == 1){
                $bl_user_ids[] = $val['id'];
                $bl_money += $val['money'];
            }
        }

        // 有效玩家数
        $validUserCount = $dabiao1 + $dabiao2;

        // 玩家总数
        $userCount = count($users);

        // 充值
        $recharge = Recharge::where($where)->where('status', 1)->select();
            
        // 今日充值用户(新用户)
        $rechargeUser = [];

        // 今日充值的所有人数(包括新用户和复充用户)
        $todayRechargeUser = [];

        // 总充值
        $rechargeMoney = 0;
        // 今日充值
        $todayRechargeMoney = 0;

        // 昨日充值人数
        $yesterdayRechargeUserCount = 0;
        // 昨日充值的所有人数(包括新用户和复充用户)
        $yesterdayRechargeUser = [];

        // 昨日充值金额
        $yesterdayRechargeMoney = 0;

        // 博主充值金额
        $bl_rechargeMoney = 0;

        // 充值排行榜用户
        $rechargeRankUser = [];
        foreach($recharge as $val){
            if(in_array($val['user_id'], $user_ids)){
                $rechargeUser[$val['user_id']][] = $val;
            }

            if(strtotime($val['paytime']) >= $time){
                $todayRechargeMoney += $val['money'];
                $todayRechargeUser[] = $val;
            }

            if(strtotime($val['paytime']) >= $yesterday_time && strtotime($val['paytime']) < $time){
                $yesterdayRechargeUserCount ++;
                $yesterdayRechargeMoney += $val['money'];
                $yesterdayRechargeUser[$val['user_id']][] = $val;
            }
           
            $rechargeMoney += $val['money'];

            if(in_array($val['user_id'], $bl_user_ids)){
                $bl_rechargeMoney += $val['money'];
            }
            if(isset($rechargeRankUser[$val['user_id']])){
                $rechargeRankUser[$val['user_id']]['user_id'] = $val['user_id'];
                $rechargeRankUser[$val['user_id']]['username'] = $val->user->username;
                $rechargeRankUser[$val['user_id']]['money'] += $val['money'];
            }else{
                $rechargeRankUser[$val['user_id']]['user_id'] = $val['user_id'];
                $rechargeRankUser[$val['user_id']]['username'] = $val->user->username;
                $rechargeRankUser[$val['user_id']]['money'] = $val['money'];
            }
        }
        $rechargeRank = array_column($rechargeRankUser, 'money');
        array_multisort($rechargeRank, SORT_DESC, $rechargeRankUser);

        $colorArr = ['text-red', 'text-green', 'text-yellow'];
        foreach($rechargeRankUser as $key => $val){
            $rechargeRankUser[$key]['rank'] = $key < 3 ? '<span class="'.$colorArr[$key].'">'.($key + 1).'</span>' : $key + 1;
            // 只取前20名
            if($key >= 20){
                unset($rechargeRankUser[$key]);
            }
        }

        // dd($rechargeRankUser);
        // 用户充值金额
        $user_recharegeMoney = $rechargeMoney - $bl_rechargeMoney;

        // 今日充值人数
        $todayRechargeCount = count($todayRechargeUser);
        // 今日新玩家复充人数
        $todayRepeatRechargeCount = 0;
        foreach($rechargeUser as $val){
            if(count($val) > 1){
                $todayRepeatRechargeCount ++;
            }
        }
        // 今日老玩家复充人数
        $todayOldRepeatRechargeCount = $todayRechargeCount - $todayRepeatRechargeCount;
        
        // 昨日新玩家复充人数
        $yesterdayRepeatRechargeCount = 0;
        foreach($yesterdayRechargeUser as $val){
            if(count($val) > 1){
                $yesterdayRepeatRechargeCount ++;
            }
        }
        // 昨日老玩家复充人数
        $yesterdayOldRepeatRechargeCount = $yesterdayRechargeUserCount - $yesterdayRepeatRechargeCount;

        // 提现
        $withdraw = Withdraw::where($where)->where('status', 1)->select();
        // 总提现
        $withdrawMoney = 0;
        // 今日提现
        $todayWithdrawMoney = 0;
        // 昨日提现
        $yestodayWithdrawMoney = 0;
        // 博主提现金额
        $bl_withdrawMoney = 0;
        // 博主今日提现
        $today_bl_WithdrawMoney = 0;
        // 博主昨日提现
        $yestoday_bl_WithdrawMoney = 0;

        // 提现排行榜
        $withdrawRankUser = [];
        
        // 提现人数
        $withdrawUser= [];
        foreach($withdraw as $val){
            if(strtotime($val['paytime']) >= $time){
                $todayWithdrawMoney += $val['money'];
            }

            if(strtotime($val['paytime']) >= $yesterday_time && strtotime($val['paytime']) < $time){
                $yestodayWithdrawMoney += $val['money'];
            }
            $withdrawMoney += $val['money'];

            if(in_array($val['user_id'], $bl_user_ids)){
                $bl_withdrawMoney += $val['money'];
                if(strtotime($val['paytime']) >= $time){
                    $today_bl_WithdrawMoney += $val['money'];
                }
                if(strtotime($val['paytime']) >= $yesterday_time && strtotime($val['paytime']) < $time){
                    $yestoday_bl_WithdrawMoney += $val['money'];
                }
            }

            if(isset($withdrawRankUser[$val['user_id']])){
                $withdrawRankUser[$val['user_id']]['user_id'] = $val['user_id'];
                $withdrawRankUser[$val['user_id']]['username'] = $val->user->username;
                $withdrawRankUser[$val['user_id']]['money'] += $val['money'];
            }else{
                $withdrawRankUser[$val['user_id']]['user_id'] = $val['user_id'];
                $withdrawRankUser[$val['user_id']]['username'] = $val->user->username;
                $withdrawRankUser[$val['user_id']]['money'] = $val['money'];
            }
            $withdrawUser[$val['user_id']][] = $val;
        }
        $withdrawRank = array_column($withdrawRankUser, 'money');
        array_multisort($withdrawRank, SORT_DESC, $withdrawRankUser);
        foreach($withdrawRankUser as $key => $val){
            $withdrawRankUser[$key]['rank'] = $key < 3 ? '<span class="'.$colorArr[$key].'">'.($key + 1).'</span>' : $key + 1;
            // 只取前20名
            if($key >= 20){
                unset($withdrawRankUser[$key]);
            }
        }

        // 用户提现
        $user_withdrawMoney = $withdrawMoney - $bl_withdrawMoney;

        // 提款率
        $withdrawRate = $user_recharegeMoney ? round($user_withdrawMoney / $user_recharegeMoney, 4) * 100 : 0;

        // 属于工资范围的 1.业务员手动发 2.宝箱  3.分佣
        $acvivityArr = ['admin_bonus', 'return_money'];

        $rewards = db('user_reward_log')->where($where)->where('status', 1)->whereIn('type', $acvivityArr)->select();
        $total_salary = 0;
        $bl_salary = 0;
        $bl_return_salary = 0;

        // 今日博主工资
        $today_bl_salary = 0;
        // 昨日博主工资
        $yestoday_bl_salary = 0;
        
        foreach($rewards as $val){
            $total_salary += $val['money'];
            if(in_array($val['user_id'], $bl_user_ids)){
                $bl_salary += $val['money'];
                if($val['type'] == 'return_money'){
                    $bl_return_salary += $val['money'];
                }

                if(strtotime($val['createtime']) >= $time){
                    $today_bl_salary += $val['money'];
                }
                if(strtotime($val['createtime']) >= $yesterday_time && strtotime($val['createtime']) < $time){
                    $yestoday_bl_salary += $val['money'];
                }
            }
        }

        $return_salary = db('user_money_log')->where($where)->where('type', 'refund_money')->select();

        // 博主退款
        $bl_return_salary = 0;
        // 今日博主退款
        $today_bl_return_salary = 0;
        // 昨日博主退款
        $yestoday_bl_return_salary = 0;
        foreach($return_salary as $val){
            $bl_return_salary += $val['money'];

            if($val['createtime'] >= $time){
                $today_bl_return_salary += $val['money'];
            }
            if($val['createtime'] >= $yesterday_time && $val['createtime'] < $time){
                $yestoday_bl_return_salary += $val['money'];
            }
        }
        
        $total_salary = $total_salary + $bl_return_salary;

        if(isset($where['user_id'])){
            unset($where['user_id']);
        }
        // 每日数据报表
        $daybookadmin = db('daybookadmin')->where($where)->select();

        // 累计充值金额 补上不包含自然流量的充值
        $lj_rechargeMoney = 0;

        // 累计提现金额 补上不包含自然流量今日的
        $lj_withdrawMoney = 0;
        // api费用
        $apiFee = 0;
        // 通道费用
        $channelFee = 0;
        // 累计盈亏
        $profit_and_loss = 0;
        foreach($daybookadmin as $val){
            $apiFee += $val['api_amount'];
            $channelFee += $val['channel_fee'];
            $lj_rechargeMoney += $val['recharge_amount'];
            $lj_withdrawMoney += $val['withdraw_amount'];
            $profit_and_loss += $val['profit_and_loss'];
        }
        
        //本月盈亏
        $monthlyData = db('daybookadmin')
            ->where($where) // 保留原有查询条件
            ->where('date', 'like', date('Y-m') . '%') // 本月数据
            ->sum('profit_and_loss'); // 直接返回 SUM(profit_and_loss)
        
        // 返回数据
        $retval = [
            'total' => [
                'user_count'            => $userCount, 
                'recharge_user_count'   => $rechargeUserCount,
                'recharge_money'        => $rechargeMoney,
                'withdraw_money'        => $withdrawMoney,
                'withdraw_rate'         => $withdrawRate,
                'money'                 => sprintf('%.2f', $money),
                'valid_user_count'      => $validUserCount,
                'lj_recharge_money'     => $lj_rechargeMoney,
                'lj_withdraw_money'     => $lj_withdrawMoney,
                'api_fee'               => $apiFee,
                'channel_fee'           => $channelFee,
                'profit_and_loss'       => sprintf('%.2f', $profit_and_loss),
                'profit_month'          => sprintf('%.2f', $monthlyData),
                'bl_recharge_money'     => $bl_rechargeMoney,
                'user_recharege_money'  => $user_recharegeMoney,
                'bl_withdraw_money'     => $bl_withdrawMoney,
                'user_withdraw_money'   => $user_withdrawMoney,
                'bl_money'              => $bl_money,
                'salary'                => sprintf('%.2f', $total_salary),
                'rechare_salary'        => $bl_withdrawMoney ? sprintf('%.2f', $user_recharegeMoney/$bl_withdrawMoney) : 0,
                'bl_salary'             => sprintf('%.2f', $bl_salary),
                'bl_return_salary'      => sprintf('%.2f', $bl_return_salary),
            ],
            'today' => [
                'user_count'                => $todayUserCount,
                'recharge_count'            => $todayRechargeCount,
                'recharge_money'            => $todayRechargeMoney,
                'withdraw_money'            => $todayWithdrawMoney,
                'login_user_count'          => $todayLoginUserCount,
                'repeat_recharge_count'     => $todayRepeatRechargeCount,
                'old_repeat_recharge_count' => $todayOldRepeatRechargeCount,
                'bl_salary'                 => sprintf('%.2f', $today_bl_salary),
                'bl_withdraw_money'         => sprintf('%.2f', $today_bl_WithdrawMoney),
                'bl_return_salary'          => sprintf('%.2f', $today_bl_return_salary),
            ],
            'yesterday' => [
                'user_count'                => $yestodayUserCount,
                'recharge_count'            => $yesterdayRechargeUserCount,
                'recharge_money'            => $yesterdayRechargeMoney,
                'withdraw_money'            => $yestodayWithdrawMoney,
                'login_user_count'          => $yesterdayLoginUserCount,
                'repeat_recharge_count'     => $yesterdayRepeatRechargeCount,
                'old_repeat_recharge_count' => $yesterdayOldRepeatRechargeCount,
                'bl_salary'                 => sprintf('%.2f', $yestoday_bl_salary),
                'bl_withdraw_money'         => sprintf('%.2f', $yestoday_bl_WithdrawMoney),
                'bl_return_salary'          => sprintf('%.2f', $yestoday_bl_return_salary),
            ],
            'rank' => [
                'recharge_rank_user' => $rechargeRankUser,
                'withdraw_rank_user' => $withdrawRankUser,
            ]
        ];

        $this->assign('retval', $retval);

        $site = db('site')->where('status', 1)->field('url id,url name')->order('createtime desc')->select();
        $this->assign('site', json_encode($site));
        $this->assign('origin', $origin);
        return $this->view->fetch();
    }

}
