<?php

namespace app\common\service\blogger;

use app\admin\model\report\Daybookbl;
use app\common\model\Recharge;
use app\common\model\Site;
use app\common\model\Withdraw;
use app\common\service\Base;
use think\Db;

/**
 * 博主服务
 */
class User extends Base
{
    protected $model = null;
    public function __construct()
    {
        parent::__construct();
        $this->model = new \app\common\model\User();
    }

    /**
     * 博主登录
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if(!$account || !$password){
            $this->error(__('无效参数'));
        }
        
        $field = 'username';
        $user = $this->model::get([$field => $account]);
        if(!$user){
            $this->error(__('账户不正确'));
        }

        if($user->status != 'normal'){
            $this->error(__('账户已经被锁定'));
        }

        if($user->usersetting->is_open_blogger != 1){
            $this->error(__('未开通博主账号'));
        }

        if($user->password != $this->auth->getEncryptPassword($password, $user->salt)){
            $this->error(__('密码不正确'));
        }

        // 直接登录会员
        $ret = $this->auth->direct($user->id);

        if(!$ret){
            $this->error(__('登录失败, 请稍后再试! '));
        }

        $data = ['userinfo' => $this->auth->getUserinfo()];
        $this->success(__('登录成功'), $data);
    }

    /**
     * 站点管理
     */
    public function site()
    {
        $limit = $this->request->get('limit/d', 10);

        $where['status'] = 1;

        $keyword = $this->request->get('keyword');
        if($keyword != ''){
            $where['name|url'] = ['like', '%' . $keyword . '%'];
        }

        $user = $this->auth->getUser();

        // 用户邀请码
        $invite_code = $user->invite_code;

        $site = Site::where($where)
            ->field('id,name,url,onlinetime')
            ->order('onlinetime,id desc')
            ->paginate([
                'list_rows' => $limit,
                'query' => $this->request->param()
            ])->each(function($item) use ($invite_code){
                $item->url = 'https://' . $item->url . '?invite_code=' . $invite_code;
            });

   
        $retval = [
            'list'  => $site
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 用户列表
     */
    public function list()
    {
        $limit = $this->request->get('limit/d', 10);

        $username = $this->request->get('username');
        $origin = $this->request->get('origin');
        $invite_code = $this->request->get('invite_code');
        
        $where = [];
        if($username != ''){
            $where['a.username'] = ['like', '%' . $username . '%'];
        }

        if($origin != ''){
            $where['a.origin'] = ['like', '%' . $origin . '%'];
        }

        if($invite_code != ''){
            $where['a.invite_code'] = $invite_code;
        }
        
        $user_id = $this->request->get('user_id/d');
        if($user_id != ''){
            $where['a.id'] = $user_id;
        }

        $parent_id = $this->request->get('parent_id/d');
        if($parent_id != ''){
            $where['a.parent_id'] = $parent_id;
        }

        $be_invite_code = $this->request->get('be_invite_code');
        if($be_invite_code != ''){
            $where['b.invite_code'] = $be_invite_code;
        }

        // 所有用户
        $allUsers = $this->model->where('is_test', 0)->field('id,parent_id')->select();
        // 我的用户团队
        $myUserTeam = $this->model::getTeam($allUsers, $this->auth->id);
        $user_ids = [];
        foreach($myUserTeam as $val){
            $user_ids[] = $val['id'];
        }
        $where['a.id'] = ['in', $user_ids];

        // dd($myUserTeam->toArray());
        // dd($this->auth->id);
        // dd($where);
        $fields = 'a.id,a.parent_id,a.parent_id_str,a.money,a.level,a.role,a.is_first_recharge,a.username,';
        $fields .= 'a.origin,a.invite_code,a.createtime,a.logintime,b.invite_code be_invite_code,b.username be_username';

        $list = $this->model->alias('a')
            ->join('User b', 'a.parent_id = b.id', 'LEFT')
            // ->where([
            //     ['EXP', Db::raw("FIND_IN_SET(". $this->auth->id .", a.parent_id_str)")]
            // ])
            ->where($where)
            ->field($fields)
            ->order('a.id desc')
            ->paginate([
                'list_rows' => $limit,
                'query' => $this->request->param()
            ])->each(function($item){
                $item->role_text = $item->role ? __('博主') : __('会员');
                $item->createtime = date('Y-m-d H:i:s', $item->createtime);
                $item->logintime = date('Y-m-d H:i:s', $item->logintime);

                $item->total_bet = $item->userdata->total_bet; // 流水
                $item->today_bet = $item->userdata->today_bet; // 今日流水
                $item->typing_amount_limit = $item->userdata->typing_amount_limit; // 提现所需流水
                $item->total_withdraw = $item->userdata->total_withdraw; // 累计提现
                $item->total_recharge = $item->userdata->total_recharge; // 累计充值
                $item->total_profit = $item->userdata->total_profit; // 累计盈利
                $item->today_profit = $item->userdata->today_profit; // 今日盈利
                $item->salary = $item->userdata->salary; // 工资

                $item->hidden(['userdata']);
            });
            // dd($list);
        $retval = [
            'list'  => $list
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 下级数据
     */
    public function subData()
    {
        $user_id = $this->request->get('id');

        $row = $this->model->get($user_id);

        if(!$row){
            $this->error(__('无效参数'));
        }

        // 所有下三级用户
        $users = $this->model::getSubUsers($user_id);
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
            1 => __('下级'),
            2 => __('下二级'),
            3 => __('下三级'),
            4 => __('总计')
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

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 用户统计
     */
    public function dashboard()
    {
        $origin = $this->request->get('origin');

        $where['is_test'] = 0;
        if($origin != ''){
            $where['origin'] = $origin;
        }
        // 所有用户
        $allUsers = $this->model->where($where)->field('id,parent_id,money,createtime,logintime')->select();
        // 我的用户团队
        $myUserTeam = $this->model::getTeam($allUsers, $this->auth->id);

        // 今日凌晨时间
        $time = strtotime(date('Y-m-d'));

        // 所属id
        $user_ids = [];
        // 玩家余额
        $balance = 0;
        // 有效用户数
        $valid_user_count = 0;

        // 今日注册人数
        $today_user_ids = [];
        $today_register_count = 0;
        
        // 今日活跃人数
        $today_login_count = 0;
        foreach($myUserTeam as $val){
            $user_ids[] = $val['id'];
            $balance += $val['money'];

            if($val::isValidUser($val)){
                $valid_user_count ++;
            }
            if($val->createtime >= $time){
                $today_register_count ++;
                $today_user_ids[] = $val['id'];
            }
            if($val->logintime >= $time){
                $today_login_count ++;
            }
        }
        // dd($user_ids);
        // 每日统计
        $daybookbl = Daybookbl::where('user_id', $this->auth->id)->select();

        $recharge = Recharge::where('user_id', 'in', $user_ids)
            ->whereTime('paytime', 'today')
            ->where('status', 1)
            ->group('user_id')
            ->field('user_id,count(id) as count,sum(money) as money')
            ->select();

        // 今日充值人数
        $today_recharge_count = isset($recharge['count']) ? array_sum(array_column($recharge, 'count')) : 0;
            
        // 今日充值
        $today_recharge_amount = isset($recharge['money']) ? array_sum(array_column($recharge, 'money')) : 0;

        // 总充值
        $recharge_amount = $today_recharge_amount;

        // 总提现
        $withdraw_amount = Withdraw::where('user_id', 'in', $user_ids)->whereTime('createtime', 'today')->where('status', 1)->sum('money');

        $withdraw = Withdraw::where('user_id', 'in', $user_ids)
            ->whereTime('createtime', 'today')
            ->where('status', 1)
            ->group('user_id')
            ->field('user_id,count(id) as count,sum(money) as money')
            ->select();

        // 今日提现人数
        $today_withdraw_count = isset($withdraw['count']) ? array_sum(array_column($withdraw, 'count')) : 0;
        // 今日提现
        $today_withdraw_amount = isset($withdraw['money']) ? array_sum(array_column($withdraw, 'money')) : 0;

        // 总提现
        $withdraw_amount = $today_withdraw_amount;
        foreach($daybookbl as $val){
            $recharge_amount += $val['recharge_amount'];
            $withdraw_amount += $val['withdraw_amount'];
        }

        // 玩家总数
        $user_count = count($user_ids);

        $retval = [
            'total' => [
                'user_count'            => $user_count,  // 玩家总数
                'recharge_amount'       => $recharge_amount, // 累计充值
                'withdraw_amount'       => $withdraw_amount, // 累计提现
                'balance'               => $balance, // 玩家余额
                'valid_user_count'      => $valid_user_count, // 有效玩家数
            ],
            'today' => [
                'register_count'        => $today_register_count, // 今日注册人数
                'recharge_count'        => $today_recharge_count, // 今日充值人数
                'recharge_amount'       => $today_recharge_amount, // 今日充值金额
                'withdraw_count'        => $today_withdraw_count, // 今日提现人数
                'withdraw_amount'       => $today_withdraw_amount, // 今日提现金额
                'login_count'           => $today_login_count, // 今日活跃人数
            ],
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 每日统计
     */
    public function daybook()
    {
        $limit = $this->request->get('limit', 10);
        $date = $this->request->get('date');
        
        $fields = "recharge_amount,withdraw_amount,transfer_amount,api_amount,channel_fee,profit_and_loss,date";
        $list = Daybookbl::field($fields)
            ->order('date desc');

        if($date != ''){
            $list = $list->where('date', $date);
        }

        $list = $list->paginate([
                'list_rows' => $limit,
                'query' => $this->request->param()
            ]);

        $retval = [
            'list' => $list
        ];
        $this->success(__('请求成功'), $retval);
    }
}