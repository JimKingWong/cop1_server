<?php

namespace app\common\service;

use app\common\model\Promotion;
use app\common\model\Recharge;
use app\common\model\RewardLog;
use app\common\model\User;
use think\Cache;
use think\Db;

/**
 * 代理服务
 */
class Agent extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 代理人信息
     */
    public function init()
    {
        $user = $this->auth->getUser();

        // 是否可领取宝箱 0 否, 1 是
        $is_receive_box = $user->usersetting->is_black;

        // 有效用户
        $valid_user_num = $user->validUser($user->id, 1);

        // 直推用户
        $direct_user_num = count($user->directUser($user->id));

        // 邀请链接
        $invite_url = 'https://' . $user->origin . '?invite_code=' . $user->invite_code;

        // 二维码
        $qrcode = Cache::get('user_qrcode_' . $this->auth->id);
        if(!$qrcode){
            $qrcode = getQrcode($invite_url);
            Cache::set('user_qrcode_' . $this->auth->id, $qrcode, 24 * 3600);
        }

        // 平台推广
        $promotion = Promotion::where('status', 1)->cache(true)->field('name,image,android_url,ios_url')->order('weigh desc')->select();
        
        $retval = [
            'invite_url'            => $invite_url, 
            'invite_code'           => $user->invite_code,
            'is_receive_box'        => $is_receive_box,
            'valid_user_num'        => $valid_user_num,
            'direct_user_num'       => $direct_user_num,
            'promotion'             => $promotion,
            'level_icon'            => 'https://img2.thethsdnadagvx.com/gpmaster/' . $user->level .'.png',
            'valid_bet'             => config('system.valid_bet'),
            'valid_recharge'        => config('system.valid_recharge'),
            'qrcode'                => $qrcode,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 我的团队
     */
    public function team()
    {
        $rank  = $this->request->get('rank');

        $user = $this->auth->getUser();

        $fields = 'level,money,is_first_recharge,createtime';
        $teamList = $user::getSubUsers($user->id, 1, $fields);

        // 团队
        $team = $teamList[$rank] ?? [];

        // 首充人数
        $first_recharge_num = 0;
        // 有效用户数
        $valid_user_num = 0;
        //总投注
        $total_bet = 0;
        // 总充值
        $total_recharge = 0;
        foreach($team as $key => $val){
            $team[$key]['createtime'] = datetime($val['createtime']);

            if($val['is_first_recharge'] == 1){
                $first_recharge_num ++;
            }

            if($val['is_valid'] == 1){
                $valid_user_num ++;
            }

            $total_bet += $val['total_bet'];
            $total_recharge += $val['total_recharge'];
        }

        // 平均充值
        $avg_recharge = $first_recharge_num > 0 ? $total_recharge / $first_recharge_num : 0;

        // 团队统计
        $summary = [
            'total_user_num'    => count($team),
            'first_recharge_num'=> $first_recharge_num,
            'valid_user_num'    => $valid_user_num,
            'total_bet'         => $total_bet,
            'total_recharge'    => $total_recharge,
            'avg_recharge'      => sprintf('%.2f', $avg_recharge),
        ];
        
        $retval = [
            'team'          => $team,
            'summary'       => $summary,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 我的数据
     */
    public function mydata()
    {
        // 昨天 yesterday, 今天 today, 本周 week, 上周 last_week, 本月 month, 上个月 last month
        $date = $this->request->post('date', 'today');

        $dateArr = ['yesterday', 'today', 'week', 'last week', 'month', 'last month'];
        if(!in_array($date, $dateArr)){
            $this->error(__('无效参数'));
        }
        
        $user = $this->auth->getUser();
        // 直推用户
        $fields = 'id,admin_id,username,invite_code,parent_id,parent_id_str,money,is_first_recharge';
        $directUser = User::where('parent_id', $user->id)->whereTime('createtime', $date)->field($fields)->select();
        // dd($directUser);

        // 首次存款金额
        $firstRechargeMoney = 0;
        // 首充用户
        $firstRechargeUserNum = 0;
        // 获取uid
        $user_ids = [];
        // 直推下级总流水
        $total_bet = 0;
        foreach($directUser as $val){
            if($val->is_first_recharge == 1){
                $firstRechargeUserNum ++;
            }

            $total_bet += $val->userdata->total_bet;
            $firstRechargeMoney += $val->userdata->first_recharge_money;

            $user_ids[] = $val->id;
        }

        // 直推总存款
        $rechargeMoney = Recharge::where('user_id', 'in', $user_ids)->where('status', 1)->whereTime('paytime', $date)->sum('money');

        $rewardArr = ['box_bonus'];
        // 获得奖励
        $rewardMoney = RewardLog::where('user_id', 'in', $user_ids)->where('status', 1)->whereTime('receivetime', $date)->whereIn('type', $rewardArr)->sum('money');

        $arr = ['recharge_commission'];
        // 直推获得佣金
        $commission = RewardLog::where('user_id', 'in', $user_ids)->where('status', 1)->whereTime('receivetime', $date)->whereIn('type', $arr)->sum('money');

        // 数据概览
        // 我的团队
        $teamList = $user::getSubUsers($user->id);
        
        // 团队规模
        $total_user_num = count($teamList);
        // 直推用户
        $direct_user_num = 0;
        // 其他用户
        $other_user_num = 0;
        foreach($teamList as $val){
            if($val['rank'] == 1){
                $direct_user_num ++;
            }else{
                $other_user_num ++;
            }
        }

        // 总佣金 直接佣金 间接佣金

        // 总佣金 已领取 未领取


        $retval = [
            'direct'    => [
                'first_recharge_money'  => sprintf('%.2f', $firstRechargeMoney),
                'first_recharge_num'    => $firstRechargeUserNum,
                'recharge_money'        => sprintf('%.2f', $rechargeMoney),
                'total_bet'             => sprintf('%.2f', $total_bet),
                'reward_money'          => sprintf('%.2f', $rewardMoney),
                'commission'            => sprintf('%.2f', $commission),
            ],
            'summary'  => [
                'total_user_num'    => $total_user_num,
                'direct_user_num'   => $direct_user_num,
                'other_user_num'    => $other_user_num,
            ]
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 所有数据
     */
    public function alldata()
    {
        $startTime = $this->request->get('start_time');
        $endTime = $this->request->get('end_time');
        $id = $this->request->get('id/d');

        $where = [];
        if($startTime != ''){
            $where['createtime'] = ['>=', strtotime($startTime)];
            if($endTime != ''){
                $where['createtime'] = ['between', [strtotime($startTime), strtotime($endTime)]];
            }
        }

        $user = $this->auth->getUser();
        $user_id = $user->id;
        if($id != ''){
            $where['id'] = $id;
        }

        // 所有下级
        $fields = 'id,parent_id,username,invite_code,parent_id,level,is_first_recharge,createtime';
        $subUsers = User::where([
            ['EXP', Db::raw("FIND_IN_SET(". $user_id .", parent_id_str)")]
        ])->where($where)->field($fields)->select();


        // 直推充值
        $direct_recharge = 0;
        // 其他充值
        $other_recharge = 0;

        // 直推首充人数
        $direct_first_recharge_num = 0;
        // 其他首充人数
        $other_first_recharge_num = 0;
        
        foreach($subUsers as $val){
            $val->total_bet = $val->userdata->total_bet;
            $val->total_recharge = $val->userdata->total_recharge;
            $val->createtime = datetime($val->createtime);

            if($val->parent_id == $user->id){
                $direct_recharge += $val->total_recharge;

                if($val->is_first_recharge == 1){
                    $direct_first_recharge_num ++;
                }
            }else{
                $other_recharge += $val->total_recharge;

                if($val->is_first_recharge == 1){
                    $other_first_recharge_num ++;
                }
            }

            // 判断是否有效用户
            $val->is_valid = $val::isValidUser($val);
            unset($val->userdata);
        }

        // 总充值
        $total_recharge = $direct_recharge + $other_recharge;

        // 总首充人数
        $total_first_recharge_num = $direct_first_recharge_num + $other_first_recharge_num;
        
        $retval = [
            'user_list' => $subUsers,
            'summary'   => [
                'recharge'    => [
                    'direct'    => $direct_recharge,
                    'other'     => $other_recharge,
                    'total'     => $total_recharge,
                ],
                'first_recharge' => [
                    'direct'    => $direct_first_recharge_num,
                    'other'     => $other_first_recharge_num,
                    'total'     => $total_first_recharge_num,
                ],
                
            ]
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 二级数据
     */
    public function secData()
    {
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');
        $id = $this->request->get('id/d');

        $user = $this->auth->getUser();

        $user_id = $user->id;
        if($id != ''){
            $user_id = $id;
        }

        // 获取下级用户
        $user_ids = db('user')->where('parent_id', $user_id)->column('id');
        // dd($user_ids);

        $fields = 'id,parent_id,username,is_first_recharge,createtime';
        $list = User::where([
            ['EXP', Db::raw("FIND_IN_SET(". $user_id .", parent_id_str)")]
        ])->where('parent_id', 'in', $user_ids)->field($fields);

        if($start_time != ''){
            $list->where('createtime', '>=', strtotime($start_time));
            if($end_time != ''){
                $list->where('createtime', '<=', strtotime($end_time));
            }
        }

        $list = $list->select();

        $directRechargeAmount = 0;
        $otherRechargeAmount = 0;
        $totalRechargeAmount = 0;
        $directFirstNum = 0;
        $otherFirstNum = 0;
        $totalFirstNum = 0;

        foreach($list as $val){
            $val->createtime = datetime($val->createtime);
            $val->total_recharge = $val->userdata->total_recharge;
            $val->total_bet = $val->userdata->total_bet;

            if($val->parent_id == $user->id){
                if($val->is_first_recharge == 1){
                    $directFirstNum ++;
                    $totalFirstNum ++;
                }

                $directRechargeAmount += $val->userdata->total_recharge;
            }else{
                if($val->is_first_recharge == 1){
                    $otherFirstNum ++;
                    $totalFirstNum ++;
                }

                $otherRechargeAmount += $val->userdata->total_recharge;
            }

            $val->hidden(['userdata']);
        }

        $totalRechargeAmount = $directRechargeAmount + $otherRechargeAmount;

        $otherData = [
            'directRechargeAmount' => $directRechargeAmount,
            'otherRechargeAmount' => $otherRechargeAmount,
            'totalRechargeAmount' => $totalRechargeAmount,
            'directFirstNum' => $directFirstNum,
            'otherFirstNum' => $otherFirstNum,
            'totalFirstNum' => $totalFirstNum,
        ];

        $retval = [
            'otherData' => $otherData,
            'list'      => $list,
        ];

        $this->success(__('请求成功'), $retval);
    }

     /**
     * 业绩
     */
    public function performance()
    {
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');
        $id = $this->request->get('id/d');

        $user = $this->auth->getUser();

        $user_id = $user->id;
        if($id != ''){
            $user_id = $id;
        }

        $where['parent_id'] = $user_id;
        if($start_time != ''){
            $where['createtime'] = ['>=', strtotime($start_time)];
            if($end_time != ''){
                $where['createtime'] = ['between', [strtotime($start_time), strtotime($end_time)]];
            }
        }

        $fields = 'id,parent_id,username,is_first_recharge,createtime';
        $list = User::where($where)->field($fields)->select();

        $directNum = 0;
        $otherNum = 0;
        $directValidAmount = 0;
        $otherValidAmount = 0;

        foreach($list as $val){
            $val->createtime = datetime($val->createtime);
            $val->invite_num = $val->userdata->invite_num;
            $val->total_bet = $val->userdata->total_bet;

            $otherNum += $val->userdata->invite_num;
            $directValidAmount += $val->userdata->total_bet;
            $directNum ++;

            $val->hidden(['userdata']);
        }

        $otherData = [
            "myTotalCommission"         => 0,
            "myTotalValidAmount"        => 0,
            "directNum"                 => $directNum,
            "otherNum"                  => $otherNum,
            "directValidAmount"         => $directValidAmount,
            "otherValidAmount"          => $otherValidAmount,
            "directCommission"          => 0,
        ];

        $retval = [
            'otherData' => $otherData,
            'list'      => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }

     /**
     * 下属资料
     */
    public function subActive()
    {
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');
        $id = $this->request->get('id/d');
        $search_user_id = $this->request->get('user_id/d');

        $user = $this->auth->getUser();

        $user_id = $user->id;
        if($id != ''){
            $user_id = $id;
        }

        $where['parent_id'] = $user_id;
        if($search_user_id != ''){
            $where['id'] = $search_user_id;
        }

        if($start_time != ''){
            $where['createtime'] = ['>=', strtotime($start_time)];
            if($end_time != ''){
                $where['createtime'] = ['between', [strtotime($start_time), strtotime($end_time)]];
            }
        }

        $fields = 'id,parent_id,username,is_first_recharge,level vipLevel,createtime,logintime';
        $list = User::where($where)->field($fields)->select();
        foreach($list as $val){
            $val->createtime = datetime($val->createtime);
            $val->logintime = datetime($val->logintime);
            $val->invite_num = $val->userdata->invite_num;
            $val->total_bet = $val->userdata->total_bet;
            $val->total_recharge = $val->userdata->total_recharge;

            $val->status = 1;
            $val->online = 0;
            $val->hidden(['userdata']);
        }

        $retval = [
            'list'   => $list
        ];
        $this->success(__('请求成功'), $retval);

    }

    /**
     * 下属下注报告
     */
    public function subBetReport()
    {
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');
        $id = $this->request->get('id/d');

        $user = $this->auth->getUser();

        $user_id = $user->id;
        if($id != ''){
            $user_id = $id;
        }
        
        $fields = 'id,parent_id,parent_id_str,username';
        $list = User::where([
            ['EXP', Db::raw("FIND_IN_SET(". $user_id .", parent_id_str)")]
        ])->field($fields);

        if($start_time != ''){
            $list->where('createtime', '>=', strtotime($start_time));
            if($end_time != ''){
                $list->where('createtime', '<=', strtotime($end_time));
            }
        }

        $list = $list->select();

        $directValid = 0;
        $otherValid = 0;
        $totalValid = 0;
        $directWin = 0;
        $otherWin = 0;
        $totalWin = 0;

        foreach($list as $val){
            $val->total_typing = $val->userdata->total_typing;
            $val->total_profit = $val->userdata->total_profit;
            $val->bet_count = $val->userdata->bet_count;
            if($val->parent_id == $user->id){
                $directValid += $val->userdata->total_typing;
                $directWin += $val->userdata->total_profit;
            }else{
                $otherValid += $val->userdata->total_typing;
                $otherWin += $val->userdata->total_profit;
            }
            $val->hidden(['userdata']);
        }

        $totalValid = $directValid + $otherValid;
        $totalWin = $directWin + $otherWin;

        $otherData = [
            'directValid'    => $directValid,
            'otherValid'   => $otherValid,
            'totalValid'   => $totalValid,
            'directWin'    => $directWin,
            'otherWin'     => $otherWin,
            'totalWin'     => $totalWin,
        ];

        $retval = [
            'otherData' => $otherData,
            'records'   => $list
        ];
        $this->success(__('请求成功'), $retval);
    }
}