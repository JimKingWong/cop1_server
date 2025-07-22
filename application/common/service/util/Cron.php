<?php

namespace app\common\service\util;

use app\admin\model\Admin;
use app\common\model\Channel;
use app\common\model\MoneyLog;
use app\common\model\Mydata;
use app\common\model\Recharge;
use app\common\model\RewardLog;
use app\common\model\User;
use app\common\model\Withdraw;
use app\common\service\Channel as ServiceChannel;
use think\Db;

/**
 * 定时任务
 */
class Cron
{

    /**
     * 设置奖池号码
     */
    public function setJackpotNumber()
    {
        $money = db('bet_number')->value('money');
        $new = $money + random_int(1000, 50000) / 100;
        $new = $new > 100000000 ? $new / 5 : $new;
        
        db('bet_number')->where('id', 1)->update(['money' => $new]);
        
        echo "setJackpotNumber: 更新成功，当前金额：". number_format($new, 2) . "\n";
    }

    /**
     * 清除今日盈利
     */
    public function clearTodayProfit()
    {
        db('user_data')->where('today_bet|today_profit', '<>', 0)->update([
            'today_bet'     => 0,
            'today_profit'  => 0,
        ]);
        echo "clearTodayProfit: all users today_profit & today_bet been clean" . "\n";
    }

    /**
     * 清零 余额小于等于0.5的提现限制
     */
    public function clearTypingAmountLimit()
    {
        $user_id = db('user')->where('money', '<=', 1)->column('id');
        db('user_data')->where('user_id', 'in', $user_id)->where('typing_amount_limit', '>', 0)->update(['typing_amount_limit' => 0]);

        echo "clearTypingAmountLimit: users typing_amount_limit been clean". "\n";
    }

    /**
     * 自动放款
     */
    public function autopay()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制
        
        $is_open = config('system.auto_pay');
        if($is_open == 0) {
            echo "autopay: 自动放款未开启". "\n"; return;
        }
        
        $where['a.status'] = '0';
        $where['b.role'] = 0;
        $where['a.money'] = ['between', [10, 2000]];

        $fields = 'a.*';
        $list = Withdraw::alias('a')
            ->join('User b', 'a.user_id = b.id')
            ->where($where)
            ->field($fields)
            ->limit(10)
            ->select();

        // 系统赠送太多的
        $reward = db('user_reward_log')->where('type', 'system_gift')->group('user_id')->column('sum(money)', 'user_id');
        
        // 提现开关开了的
        $channel = Channel::where('is_default_withdraw', 1)->find();

        $method = trim(strtolower($channel['name'])) . 'Withdraw';

        if(!$channel){
            echo "没有提现通道可用! ". "\n"; return;
        }

        $rechargeConfig = $channel['withdraw_config'];
        
        foreach($list as $v){
            if(isset($reward[$v['user_id']]) && $reward[$v['user_id']] >= 200){
                // 改成异常单
                $v->save(['status' => 5]);
                continue;
            }
            
            $v->channel_id = $channel->id;

            $res = ServiceChannel::$method($rechargeConfig, $v);
            if($res['code'] == 0){
                $v->status = '2';
                $v->remark = '代付失败! 原因: ' . $res['msg'];
                $result = $v->save();

                // 数据准备
                $data = [
                    'withdraw_return' => [
                        'money'                 => $v->money,
                        'typing_amount_limit'   => 0,
                        'transaction_id'        => $v->order_no, // 记录表id
                        'status'                => 0,
                    ],
                ];

                if($result){
                    $user = User::where('id', $v->user_id)->find();
                    User::insertLog($user, $data);
                }

                echo $channel['name'] . '单号: ' . $v['order_no'] . '代付失败! 原因: ' . $res['msg'] . "\n  上游提示有误, 联系开发处理". "\n";
            }else{
                $v->status = '4';
                $result = $v->save();
                
                echo "订单" . $v['order_no'] . "已提交". "\n";
            }
        }
    }

    /**
     * 数据报表
     */
    public function dataRecord()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $day = 1;
        $starttime = date('Y-m-d 00:00:00', strtotime('-'.$day.' day'));
        $endtime = date('Y-m-d 23:59:59', strtotime('-'.$day.' day'));
        
        $users = User::whereTime('jointime', [strtotime($starttime), strtotime($endtime)])->where('is_test', 0)->select();

        // 注册人数
        $user_count = count($users);
        echo '注册人数: ' . $user_count. "\n";
        
        // 昨日用户id
        $user_ids = [];
        // 博主用户id
        $blogger_user_ids = [];

        // 注册且充值人数
        $register_recharge_users = 0;
        foreach($users as $user){
            if($user->is_first_recharge == 1){
                $register_recharge_users ++;
            }

            if($user->role == 1){
                $blogger_user_ids[] = $user->id;
            }

            $user_ids[] = $user->id;
        }
        echo '注册且充值人数: ' . $register_recharge_users. "\n";

        // 复冲人数
        $repeat_recharge_users = 0;
        // 复冲金额
        $repeat_recharge_money = 0;
        // 充值总金额
        $recharge_money = 0;
        $recharges = Recharge::whereTime('createtime', [$starttime, $endtime])
            ->where('status', 1)
            ->field("user_id,count(id) count,sum(money) money")
            ->group('user_id')
            ->select();
        foreach($recharges as $recharge){
            if($recharge['count'] > 1){
                $repeat_recharge_users ++;
                $repeat_recharge_money += $recharge['money'];
            }
            $recharge_money += $recharge['money'];
        }

        // 充值人数
        $recharge_count = count($recharges);
        echo '复冲人数: ' . $repeat_recharge_users. "\n";
        echo '复冲金额: ' . $repeat_recharge_money. "\n";
        echo '充值人数: ' . $recharge_count. "\n";
        echo '充值总金额: ' . $recharge_money. "\n";

        // 提现金额
        $withdraw_money = 0;
        // 博主提现金额
        $blogger_withdraw_money = 0;
        $withdraws = Withdraw::whereTime('createtime', [$starttime, $endtime])
            ->where('status', 1)
            ->where('is_virtual', 0)
            ->field("user_id,sum(money) money")
            ->group('user_id')
            ->select();

        foreach($withdraws as $withdraw){
            $withdraw_money += $withdraw['money'];
            if(in_array($withdraw['user_id'], $blogger_user_ids)){
                $blogger_withdraw_money += $withdraw['money'];
            }
        }

        // 客户提现金额
        $member_withdraw_money = $withdraw_money - $blogger_withdraw_money;

        echo '提现金额: ' . $withdraw_money. "\n";
        echo '博主提现金额: ' . $blogger_withdraw_money. "\n";
        echo '客户提现金额: ' . $member_withdraw_money. "\n";

        // 通道费用
        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');
        $channel_fee = $recharge_money * $recharge_channel_rate + $withdraw_money * $withdraw_channel_rate;
        echo '通道费用: ' . $channel_fee. "\n";

        // 游戏输赢记录统计
        $es = new Es();
        $condition = [
            // 时间范围查询
            [
                'type' => 'range',
                'field' => 'createtime',
                'value' => [
                    'gte' => strtotime($starttime),
                    'lte' => strtotime($endtime),
                ]
            ]
        ];

        // 下注流水
        $bet_amount = 0;

        // omg聚合游戏记录集合
        $omgGroupSearch = $es->groupAggregation('omg_game_record', $condition, 'platform', ['win_amount', 'bet_amount']);
        // dd($omgGroupSearch);
        // 客损
        $omg_user_lost = 0;
        foreach($omgGroupSearch as $val){
            $omg_user_lost += $val['bet_amount_sum'] - $val['win_amount_sum'];

            $bet_amount += $val['bet_amount_sum'];
        }

        $jdbGroupSearch = $es->groupAggregation('jdb_game_record', $condition, 'platform', ['win_amount', 'bet_amount']);
        $jdb_user_lost = 0;
        foreach($jdbGroupSearch as $val){
            $jdb_user_lost += $val['bet_amount_sum'] - $val['win_amount_sum'];

            $bet_amount += $val['bet_amount_sum'];
        }

        // 客损
        $user_lost = $omg_user_lost + $jdb_user_lost;
        echo '客损: ' . $user_lost. "\n";

        // API费用
        $game_api_fee = abs($user_lost) * config('channel.game_api_fee');
        echo 'API费用: ' . $game_api_fee. "\n";

        // 今日盈利 = 充值总金额 - 提现金额 - 通道费用 - API费用
        $profit = $recharge_money - $withdraw_money - $channel_fee - $game_api_fee;
        echo '今日盈利: ' . $profit. "\n";

        $data = [
            'date'                      => $starttime,
            'register_users'            => $user_count,
            'register_recharge_users'   => $register_recharge_users,
            'repeat_users'              => $repeat_recharge_users,
            'repeat_amount'             => $repeat_recharge_money,
            'recharge_count'            => $recharge_count,
            'recharge_money'            => $recharge_money,
            'user_lost'                 => $user_lost,
            'withdraw_money'            => $withdraw_money,
            'blogger_withdraw_money'    => $blogger_withdraw_money,
            'member_withdraw_money'     => $member_withdraw_money,
            'api_fee'                   => $game_api_fee,
            'channel_fee'               => $channel_fee,
            'profit'                    => $profit,
            'bet_amount'                => $bet_amount,
        ];
        // dd($data);
        $mydata = Mydata::where('date', $starttime)->find();
        if($mydata){
            $mydata->save($data);
            echo '数据更新成功'. "\n";
        }else{
            Mydata::create($data);
            echo '数据插入成功'. "\n";
        }
    }

    /**
     * 飞机机器人报表
     */
    public function telegramBot()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $today = date('Y-m-d');

        $users = User::whereTime('jointime', 'today')->where('is_test', 0)->select();

        // 注册人数
        $user_count = count($users);
        echo '注册人数: ' . $user_count. "\n";

        // 昨日用户id
        $user_ids = [];
        // 博主用户id
        $blogger_user_ids = [];

        // 注册且充值人数
        $register_recharge_users = 0;
        foreach($users as $user){
            if($user->is_first_recharge == 1){
                $register_recharge_users ++;
            }

            if($user->role == 1){
                $blogger_user_ids[] = $user->id;
            }

            $user_ids[] = $user->id;
        }
        echo '注册且充值人数: ' . $register_recharge_users. "\n";

        // 复冲人数
        $repeat_recharge_users = 0;
        // 复冲金额
        $repeat_recharge_money = 0;
        // 充值总金额
        $recharge_money = 0;
        // 博主充值金额
        $blogger_recharge_money = 0;
        
        $recharges = Recharge::whereTime('createtime', 'today')
            ->where('status', '1')
            ->field("user_id,count(id) count,sum(money) money")
            ->group('user_id')
            ->select();
        foreach($recharges as $recharge){
            if($recharge['count'] > 1){
                $repeat_recharge_users ++;
                $repeat_recharge_money += $recharge['money'];
            }

            if(in_array($recharge['user_id'], $blogger_user_ids)){
                $blogger_recharge_money += $recharge['money'];
            }
            $recharge_money += $recharge['money'];
        }

        // 充值人数
        $recharge_count = count($recharges);
        echo '复冲人数: ' . $repeat_recharge_users. "\n";
        echo '复冲金额: ' . $repeat_recharge_money. "\n";
        echo '充值人数: ' . $recharge_count. "\n";
        echo '充值总金额: ' . $recharge_money. "\n";

        // 提现金额
        $withdraw_money = 0;
        // 博主提现金额
        $blogger_withdraw_money = 0;
        $withdraws = Withdraw::whereTime('createtime', 'today')
            ->where('status', '1')
            ->where('is_virtual', 0)
            ->field("user_id,sum(money) money")
            ->group('user_id')
            ->select();

        foreach($withdraws as $withdraw){
            $withdraw_money += $withdraw['money'];
            if(in_array($withdraw['user_id'], $blogger_user_ids)){
                $blogger_withdraw_money += $withdraw['money'];
            }
        }

        // 客户提现金额
        $member_withdraw_money = $withdraw_money - $blogger_withdraw_money;

        echo '提现金额: ' . $withdraw_money. "\n";
        echo '博主提现金额: ' . $blogger_withdraw_money. "\n";
        echo '客户提现金额: ' . $member_withdraw_money. "\n";

        $rate_user = "0.00%";
        if($recharge_money != 0) $rate_user = bcdiv($member_withdraw_money, $recharge_money, 4) * 100 . "%";

        // 通道费用
        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');
        $channel_fee = $recharge_money * $recharge_channel_rate + $withdraw_money * $withdraw_channel_rate;
        echo '通道费用: ' . $channel_fee. "\n";

        $es = new Es();
        
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // 查询条件
        $condition = [
            // 时间范围查询
            [
                'type' => 'range',
                'field' => 'createtime',
                'value' => [
                    'gte' => strtotime($today),
                    'lte' => strtotime($tomorrow),
                ]
            ]
        ];

        // 下注流水
        $bet_amount = 0;

        // 总派彩金额
        $win_amount = 0;

        // omg聚合游戏记录集合
        $omgGroupSearch = $es->groupAggregation('omg_game_record', $condition, 'platform', ['win_amount', 'bet_amount']);
        // dd($omgGroupSearch);

        // omg总下注流水
        $omg_bet_amount = 0;
        // omg总派彩金额
        $omg_win_amount = 0;
        // 客损
        $omg_user_lost = 0;
        foreach($omgGroupSearch as $val){
            $omg_user_lost += $val['bet_amount_sum'] - $val['win_amount_sum'];

            $bet_amount += $val['bet_amount_sum'];
            $win_amount += $val['win_amount_sum'];
        }
        if($bet_amount == 0){
            $omg_rtp = 0;
        } else {
            $omg_rtp = round($win_amount / $bet_amount * 100, 2);
        }

        // pg游戏数据
        $pg_bet_amount = isset($omgGroupSearch[2]) ? $omgGroupSearch[2]['bet_amount_sum'] : 0;
        $pg_win_amount = isset($omgGroupSearch[2]) ? $omgGroupSearch[2]['win_amount_sum'] : 0;
        $pg_user_lost = $pg_bet_amount - $pg_win_amount;
        if($pg_bet_amount == 0) {  
            $pg_rtp = 0;  
        } else {  
            $pg_rtp = round($pg_win_amount / $pg_bet_amount * 100, 2);
        }

        // jili游戏数据
        $jili_bet_amount = isset($omgGroupSearch[3]) ? $omgGroupSearch[3]['bet_amount_sum'] : 0;
        $jili_win_amount = isset($omgGroupSearch[3]) ? $omgGroupSearch[3]['win_amount_sum'] : 0;
        $jili_user_lost = $jili_bet_amount - $jili_win_amount;
        if($jili_bet_amount == 0) {  
            $jili_rtp = 0;  
        } else {
            $jili_rtp = round($jili_win_amount / $jili_bet_amount * 100, 2);
        }

        // pp游戏数据
        $pp_bet_amount = isset($omgGroupSearch[4]) ? $omgGroupSearch[4]['bet_amount_sum'] : 0;
        $pp_win_amount = isset($omgGroupSearch[4]) ? $omgGroupSearch[4]['win_amount_sum'] : 0;
        $pp_user_lost = $pp_bet_amount - $pp_win_amount;
        if($pp_bet_amount == 0) {  
            $pp_rtp = 0;
        } else {  
            $pp_rtp = round($pp_win_amount / $pp_bet_amount * 100, 2);
        }

        $tada_bet_amount = isset($omgGroupSearch[23]) ? $omgGroupSearch[23]['bet_amount_sum'] : 0;
        $tada_win_amount = isset($omgGroupSearch[23]) ? $omgGroupSearch[23]['win_amount_sum'] : 0;
        $tada_user_lost = $tada_bet_amount - $tada_win_amount;
        if($tada_bet_amount == 0) {  
            $tada_rtp = 0;
        } else {  
            $tada_rtp = round($tada_win_amount / $tada_bet_amount * 100, 2);
        }

        // cp游戏数据
        $cp_bet_amount = isset($omgGroupSearch[24]) ? $omgGroupSearch[24]['bet_amount_sum'] : 0;
        $cp_win_amount = isset($omgGroupSearch[24]) ? $omgGroupSearch[24]['win_amount_sum'] : 0;
        $cp_user_lost = $cp_bet_amount - $cp_win_amount;
        if($cp_bet_amount == 0) {
            $cp_rtp = 0;
        } else {
            $cp_rtp = round($cp_win_amount / $cp_bet_amount * 100, 2);
        }

        // jdb旗下的供应商数据
        $jdbsGroupSearch = $es->groupAggregation('jdb_game_record', $condition, 'platform', ['win_amount', 'bet_amount']);
        $jdbs_user_lost = 0;
        foreach($jdbsGroupSearch as $val){
            $jdbs_user_lost += $val['bet_amount_sum'] - $val['win_amount_sum'];

            $bet_amount += $val['bet_amount_sum'];
            $win_amount += $val['win_amount_sum'];
        }

        $jdb_bet_amount = isset($jdbsGroupSearch[1]) ? $jdbsGroupSearch[1]['bet_amount_sum'] : 0;
        $jdb_win_amount = isset($jdbsGroupSearch[1]) ? $jdbsGroupSearch[1]['win_amount_sum'] : 0;
        $jdb_user_lost = $jdb_bet_amount - $jdb_win_amount;
        if($jdb_bet_amount == 0) {  
            $jdb_rtp = 0;  
        } else {  
            $jdb_rtp = round($jdb_win_amount / $jdb_bet_amount * 100, 2);
        }

        $spribe_bet_amount = isset($jdbsGroupSearch[2]) ? $jdbsGroupSearch[2]['bet_amount_sum'] : 0;
        $spribe_win_amount = isset($jdbsGroupSearch[2]) ? $jdbsGroupSearch[2]['win_amount_sum'] : 0;
        $spribe_user_lost = $spribe_bet_amount - $spribe_win_amount;
        if($spribe_bet_amount == 0) {  
            $spribe_rtp = 0;  
        } else {  
            $spribe_rtp = round($spribe_win_amount / $spribe_bet_amount * 100, 2);
        }

        $amb_bet_amount = isset($jdbsGroupSearch[11]) ? $jdbsGroupSearch[11]['bet_amount_sum'] : 0;
        $amb_win_amount = isset($jdbsGroupSearch[11]) ? $jdbsGroupSearch[11]['win_amount_sum'] : 0;
        $amb_user_lost = $amb_bet_amount - $amb_win_amount;
        if($amb_bet_amount == 0) {  
            $amb_rtp = 0;  
        } else {  
            $amb_rtp = round($amb_win_amount / $amb_bet_amount * 100, 2);
        }

        $smartsoft_bet_amount = isset($jdbsGroupSearch[13]) ? $jdbsGroupSearch[13]['bet_amount_sum'] : 0;
        $smartsoft_win_amount = isset($jdbsGroupSearch[13]) ? $jdbsGroupSearch[13]['win_amount_sum'] : 0;
        $smartsoft_user_lost = $smartsoft_bet_amount - $smartsoft_win_amount;
        if($smartsoft_bet_amount == 0) {  
            $smartsoft_rtp = 0;  
        } else {  
            $smartsoft_rtp = round($smartsoft_win_amount / $smartsoft_bet_amount * 100, 2);
        }

        echo 'OMG下注 ' . $omg_bet_amount . ' 派彩 ' . $omg_win_amount . ' 客损 ' . $omg_user_lost . "\n";
        echo 'PG下注 ' . $pg_bet_amount . ' 派彩 ' . $pg_win_amount . ' 客损 ' . $pg_user_lost . "\n";
        echo 'PP下注 ' . $pp_bet_amount . ' 派彩 ' . $pp_win_amount . ' 客损 ' . $pp_user_lost . "\n";
        echo 'JILI下注 ' . $jili_bet_amount . ' 派彩 ' . $jili_win_amount . ' 客损 ' . $jili_user_lost . "\n";
        echo 'TADA下注 ' . $tada_bet_amount . ' 派彩 ' . $tada_win_amount . ' 客损 ' . $tada_user_lost . "\n";
        echo 'CP下注 ' . $cp_bet_amount . ' 派彩 ' . $cp_win_amount . ' 客损 ' . $cp_user_lost . "\n";
        echo 'JDB下注 ' . $jdb_bet_amount . ' 派彩 ' . $jdb_win_amount . ' 客损 ' . $jdb_user_lost . "\n";
        echo 'SPRIBE下注 ' . $spribe_bet_amount . ' 派彩 ' . $spribe_win_amount . ' 客损 ' . $spribe_user_lost . "\n";
        echo 'AMB下注 ' . $amb_bet_amount . ' 派彩 ' . $amb_win_amount . ' 客损 ' . $amb_user_lost . "\n";
        echo 'SMARTSOFT下注 ' . $smartsoft_bet_amount . ' 派彩 ' . $smartsoft_win_amount . ' 客损 ' . $smartsoft_user_lost . "\n";


        // 客损 = omg客损 + jdb旗下的供应商客损 后面补PG官方的
        $user_lost = $omg_user_lost + $jdbs_user_lost;
        echo '客损: ' . $user_lost. "\n";

        // API费用
        $game_api_fee = $user_lost * config('channel.game_api_fee');
        $game_api_fee = abs($game_api_fee);
        echo 'API费用: ' . $game_api_fee. "\n";

        // 今日盈利 = 充值总金额 - 提现金额 - 通道费用 - API费用
        $profit = $recharge_money - $withdraw_money - $channel_fee - $game_api_fee;
        echo '今日盈利: ' . $profit. "\n";

        // 宝箱
        $boxs = db('box_record')->whereTime('createtime', 'today')->select();
        // 博主
        $bz_box_money = 0;
        // 客户
        $kh_box_money = 0;
        foreach($boxs as $box){
            if(in_array($box['user_id'], $blogger_user_ids)){
                $bz_box_money += $box['money'];
            }else{
                $kh_box_money += $box['money'];
            }
        }
        
        // 今日活动奖励
        $acvivityArr = ['pg_bet_bonus', 'loss_bonus', 'first_recharge_bonus', 'admin_bonus'];
        $acvivity = db('user_reward_log')->whereTime('createtime', 'today')->whereIn('type', $acvivityArr)->where('status', 1)->select();
        $pgReward = 0;
        $lossRebate = 0;
        $firstRecharge = 0;
        // 博主工资金额(后台)
        $wd_bz_wage = 0;
        foreach($acvivity as $val){
            if($val['type'] == 'pg_bet_bonus'){
                $pgReward += $val['money'];
            }else if($val['type'] == 'loss_bonus'){
                $lossRebate += $val['money'];
            }else if($val['type'] == 'first_recharge_bonus'){
                $firstRecharge += $val['money'];
            }else if($val['type'] == 'admin_bonus'){
                $wd_bz_wage += $val['money'];
            }
        }

        // 博主工资金额(流水)
        $bz_commission = db('user_reward_log')->whereTime('createtime', 'today')->where('status', 1)->whereIn('type', ['direct_bonus', 'indirect_bonus'])->sum('money');

        $today = date("Y-m-d H:i:s") . "\n";

        $str = "==={$today}=== \n";
        $str .= "===HMS共推台-数据报表=== \n";
        $str .= "OMG游戏: 下注 $bet_amount 派彩  $win_amount 客损 $omg_user_lost RTP: $omg_rtp%\n";
        $str .= "PG游戏: 下注 $pg_bet_amount  派彩 $pg_win_amount 客损 $pg_user_lost RTP: $pg_rtp%\n";
        $str .= "TADA游戏: 下注 $tada_bet_amount 派彩  $tada_win_amount 客损 $tada_user_lost RTP: $tada_rtp%\n";
        $str .= "JILI游戏: 下注 $jili_bet_amount 派彩  $jili_win_amount 客损 $jili_user_lost RTP: $jili_rtp%\n";
        $str .= "PP游戏: 下注 $pp_bet_amount 派彩  $pp_win_amount 客损 $pp_user_lost RTP: $pp_rtp%\n";
        $str .= "CP游戏: 下注 $cp_bet_amount 派彩  $cp_win_amount 客损 $cp_user_lost RTP: $cp_rtp%\n";
        $str .= "JDB游戏: 下注 $jdb_bet_amount 派彩  $jdb_win_amount 客损 $jdb_user_lost RTP: $jdb_rtp%\n";
        $str .= "SPRIBE游戏: 下注 $spribe_bet_amount 派彩  $spribe_win_amount 客损 $spribe_user_lost RTP: $spribe_rtp%\n";
        $str .= "AMB游戏: 下注 $amb_bet_amount 派彩  $amb_win_amount 客损 $amb_user_lost RTP: $amb_rtp%\n";
        $str .= "SMARTSOFT游戏: 下注 $smartsoft_bet_amount 派彩  $smartsoft_win_amount 客损 $smartsoft_user_lost RTP: $smartsoft_rtp%\n";
        $str .= "注册人数: $user_count \n";
        $str .= "注册且充值人数: $register_recharge_users \n";
        $str .= "复充人数: $repeat_recharge_users \n";
        $str .= "复充金额: $repeat_recharge_money \n";
        $str .= "充值人数: $recharge_count \n";
        $str .= "充值金额: $recharge_money \n";
        $str .= "有效下注: $bet_amount \n";
        $str .= "总流水: $bet_amount \n";
        $str .= "总派彩: $win_amount \n";
        $str .= "总客损: $user_lost \n";
        $str .= "提现金额: $withdraw_money \n";
        $str .= "博主工资金额(后台): $wd_bz_wage \n";
        $str .= "博主领取宝箱(邀请): $bz_box_money \n";
        $str .= "博主佣金发放(流水): $bz_commission \n";
        $str .= "博主充值金额: $blogger_recharge_money \n";
        $str .= "博主提现金额: $blogger_withdraw_money \n";
        $str .= "玩家领取宝箱: $kh_box_money \n";
        $str .= "玩家提现金额: $member_withdraw_money ($rate_user) \n";
        
        $str .= "-----活动----- \n";
        $str .= "PG流水奖励: $pgReward \n";
        $str .= "玩家亏损返水: $lossRebate \n";
        $str .= "玩家首充奖励: $firstRecharge \n";
        $str .= "-----活动----- \n";
        
        $str .= "通道费用: $channel_fee \n";
        $str .= "API费用: $game_api_fee \n";
        $str .= "今日盈利: $profit \n";
        // echo $str; return;

        $apiUrl = "https://api.telegram.org/bot7120074308:AAGKWlR5XQ0MySxca2vup1MmMYW3mJ8vUjU/sendMessage";

        $chat_id = config('platform.telegram_chat_id');

        $params = [
            'chat_id'   => $chat_id,
            'text'      => $str
        ];

        // 测试的
        // $params = [
        //     'chat_id'  => 7104843880,
        //     'text'  => $str,
        // ];
        // $apiUrl = "https://api.telegram.org/bot7593152406:AAGQc3rjkIXo1PlxCF4HEhTdSxPapAyAYDc/sendMessage";

        $res = Notice::send($apiUrl, $params);
        $res = json_decode($res, true);
        if($res['ok']){
            echo "Sent successfully". "\n";
        }else{
            echo "Sent failed". "\n";
        }
    }

    /**
     * 下注奖励
     */
    public function betAward()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $rewardLog = db('user_reward_log')->whereTime('createtime', 'today')->where('type', 'pg_bet_bonus')->column('type', 'user_id');
        
        // 定义阈值与金额的映射关系（按阈值降序排列）
        $thresholdMap = [
            30000000 => 18888,
            10000000 => 8777,
            3000000  => 1777,
            1000000  => 777,
            500000   => 377,
            300000   => 277,
            100000   => 127,
            50000    => 77,
            30000    => 37,
            10000    => 17,
            5000     => 7,
            3000     => 3,
            1000     => 2,
            500      => 1
        ];

        $where['today_bet'] = ['>=', 500];
        $user_ids = db('user_data')->where($where)->column('user_id');

        $list = User::where('id', 'in', $user_ids)->field('id,admin_id,money')->select();
        // dd($list->toarray());
        $es = new Es();

        $gameRecord = 'omg_game_record';

        $platform = 2; // pg
        $condition = [
            [
                'type' => 'term',
                'field' => 'platform',
                'value' => $platform, 
            ],
        ];

        $data = [];
        foreach($list as $val){
            $condition[] = [
                'type'  => 'term',
                'field' => 'user_id',
                'value' => $val->id
            ];
            // 遍历映射表，匹配第一个满足的阈值
            $money = 0;

            $omgGroupSearch = $es->groupAggregation($gameRecord, $condition, 'platform', ['bet_amount']);

            $bet_amount = $omgGroupSearch[$platform]['bet_amount_sum'] ?? 0; // 获取下注金额
            $bet_amount = 1000;
            
            foreach($thresholdMap as $threshold => $amount){
                if($bet_amount >= $threshold){
                    $money = $amount;
                    break; // 匹配成功后立即跳出循环
                }
            }

            if(!isset($rewardLog[$val->id])){
                if($money > 0){
                    $data[] = [
                        'admin_id' => $val->admin_id,
                        'user_id'  => $val->id,
                        'type'     => 'pg_bet_bonus',   
                        'money'    => $money,
                        'memo'     => 'PG下注奖励',
                        'status'   => '0',
                        'transaction_id' => $bet_amount,
                        'createtime' => datetime(time()),
                    ];
                }
            }
        }
        // dd($data);
        if(empty($data)){
            echo 'betAward: 没有数据'. "\n"; return;
        }

        db('user_reward_log')->insertAll($data);
        echo 'betAward: 收集下注数据完成'. "\n";
    }

    /**
     * 亏损返水
     */
    public function backWater()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $rewardLog = db('user_reward_log')->whereTime('createtime', 'today')->where('type', 'loss_bonus')->column('type', 'user_id');
        // dd($rewardLog);
        $where['today_profit'] = ['<', 0];
        $userdata = db('user_data')->where($where)->column('today_profit,admin_id', 'user_id');
        // dd($userdata);
        $data = [];
        foreach($userdata as $key => $val){
            $today_profit = abs($val['today_profit']);
            $rate = 0;
            if($today_profit >= 200){
                $rate = 0.005;
            }elseif($today_profit >= 1000){
                $rate = 0.01;
            }elseif($today_profit >= 10000){
                $rate = 0.02;
            }elseif($today_profit >= 50000){
                $rate = 0.03;
            }elseif($today_profit >= 100000){
                $rate = 0.04;
            }elseif($today_profit >= 500000){
                $rate = 0.05;
            }
            if(!isset($rewardLog[$key])){
                if($today_profit >= 200){
                    $data[] = [
                        'admin_id'          => $val['admin_id'],
                        'user_id'           => $key,
                        'type'              => 'loss_bonus',   
                        'money'             => round($today_profit * $rate, 2),
                        'memo'              => '亏损返水',
                        'status'            => '0',
                        'transaction_id'    => -$today_profit,
                        'createtime'        => datetime(time()),
                    ];
                }
            }
        }
        // dd($data);
        if(empty($data)){
            echo 'backWater: 没有数据'. "\n"; return;
        }

        db('user_reward_log')->insertAll($data);

        echo 'backWater: 收集亏损数据完成'. "\n";
    }

    /**
     * 业务员数据
     */
    public function summaryAdminDaybook()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $field = "id,username,role";
        $admins = Admin::where('role', '>', 2)->field($field)->select();

        $admin_ids = [];
        foreach($admins as $admin){
            $admin_ids[] = $admin->id;
        }

        array_push($admin_ids, 0);

        // 过去几天, 修改这个参数
        $day = 1;
        // 昨天的数据
        $starttime = date('Y-m-d 00:00:00', strtotime('-'. $day .' day'));
        $endtime = date('Y-m-d 23:59:59', strtotime('-'. $day .' day'));
        $where['paytime'] = ['between', [$starttime, $endtime]];
        
        $recharge = Recharge::where('paytime', 'between', [$starttime, $endtime])
            ->where('status', '1')
            ->whereIn('admin_id', $admin_ids)
            ->group('admin_id')
            ->column('sum(money)', 'admin_id');

        $withdraw = Withdraw::where('paytime', 'between', [$starttime, $endtime])
            ->where('status', '1')
            ->whereIn('admin_id', $admin_ids)
            ->group('admin_id')
            ->column('sum(money)', 'admin_id');

        // 博主工资
        $salary = db('user_reward_log')->where('createtime', 'between', [$starttime, $endtime])->where('status', 1)->where('type', 'admin_bonus')->group('admin_id')->column('sum(money)', 'admin_id');
        $es = new Es();

        // 用作判断是否已插入
        $adminLogs = db('daybookadmin')->where('date', date('Y-m-d', strtotime('-' . $day . ' day')))->column('id', 'admin_id');

        $game_api_fee = config('channel.game_api_fee');
        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');
        $data = [];
        foreach($admin_ids as $key => $admin_id){
            if(!isset($adminLogs[$admin_id])){
                $condition[$key] = [
                    // 时间范围查询
                    [
                        'type' => 'range',
                        'field' => 'createtime',
                        'value' => [
                            'gte' => strtotime($starttime),
                            'lte' => strtotime($endtime),
                        ]
                    ],
                    [
                        'type'  => 'term',
                        'field' => 'admin_id',
                        'value' => $admin_id
                    ]
                ];
                
                // omg聚合游戏记录集合
                $omgGroupSearch = $es->groupAggregation('omg_game_record', $condition[$key], 'platform', ['win_amount', 'bet_amount']);

                $omg_win_amount = array_sum(array_column($omgGroupSearch, 'win_amount_sum'));
                $omg_bet_amount = array_sum(array_column($omgGroupSearch, 'bet_amount_sum'));
                $omg_api = bcmul($omg_bet_amount - $omg_win_amount, $game_api_fee, 2);

                // jdb聚合游戏记录集合
                $jdbGroupSearch = $es->groupAggregation('jdb_game_record', $condition[$key], 'platform', ['win_amount', 'bet_amount']);

                $jdb_win_amount = array_sum(array_column($jdbGroupSearch, 'win_amount_sum'));
                $jdb_bet_amount = array_sum(array_column($jdbGroupSearch, 'bet_amount_sum'));
                $jdb_api = bcmul($jdb_bet_amount - $jdb_win_amount, $game_api_fee, 2);
                
                $api_fee = bcadd($omg_api, $jdb_api, 2);
                $api_fee = abs($api_fee);

                $recharge_amount = $recharge[$admin_id] ?? 0;
                $withdraw_amount = $withdraw[$admin_id] ?? 0;
                $transfer_amount = $recharge_amount - $withdraw_amount;
                $channel_fee = $recharge_amount * $recharge_channel_rate + $withdraw_amount * $withdraw_channel_rate;
                $profit_and_loss = $recharge_amount - $withdraw_amount - $api_fee - $channel_fee;
                
                $data[] = [
                    'admin_id'              => $admin_id,
                    'salary'                => $salary[$admin_id] ?? 0,
                    'recharge_amount'       => $recharge[$admin_id] ?? 0,
                    'withdraw_amount'       => $withdraw[$admin_id] ?? 0,
                    'transfer_amount'       => $transfer_amount,
                    'api_amount'            => sprintf('%.2f', $api_fee),
                    'channel_fee'           => sprintf('%.2f', $channel_fee),
                    'profit_and_loss'       => sprintf('%.2f', $profit_and_loss),
                    'date'                  => date('Y-m-d', strtotime('-'.$day.' day')),
                    'createtime'            => date('Y-m-d H:i:s'),
                ];
            }
        }
        // dd($data);
        if(empty($data)){
            echo 'summaryAdminDaybook: 没有数据'. "\n"; return;
        }

        db('daybookadmin')->insertAll($data);
        echo "summaryAdminDaybook: 生成日结报表，日期：{" . date('Y-m-d', strtotime('-'.$day.' day')) . "}，处理记录数：" . count($data). "\n";
    }

    
    /**
     * 博主数据
     */
    public function summaryBloggerDaybook()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $usersetting = db('user_setting')->where('is_open_blogger', 1)->column('admin_id', 'user_id');
        if(empty($usersetting)){
            echo 'summaryBloggerDaybook: 没有博主数据'. "\n"; return;
        }
        
        // 获取所有用户, 找出该用户的团队
        $allUsers = User::where('is_test', 0)->field('id,parent_id')->select();

        // 用作判断是否已插入
        $logs = db('daybookblogger')->where('date', date('Y-m-d'))->column('id', 'user_id');
        
        $data = [];
        foreach($usersetting as $key => $val){
            if(!isset($logs[$key])){
                // logs
                $myUserTeam = User::getTeam($allUsers, $key);
                
                $data[] = $this->bloggerDaybook($key, $myUserTeam, $val);
            }
        }
        
        if(empty($data)){
            echo 'summaryBloggerDaybook: 没有数据'. "\n"; return;
        }

        db('daybookblogger')->insertAll($data);
        echo "summaryBloggerDaybook: 生成日结报表，日期：{" . date('Y-m-d') . "}，处理记录数：" . count($data). "\n";
    }

    /**
     * 博主业务数据
     */
    public function bloggerDaybook($user_id, $myUserTeam, $admin_id = 0)
    {
        $user_ids = [];
        foreach($myUserTeam as $val){
            $user_ids[] = $val['id'];
        }
        
        $recharge_amount = Recharge::whereTime('createtime', 'yesterday')
            ->where('status', 1)
            ->whereIn('user_id', $user_ids)
            ->sum('real_pay_amount');

        $withdraw_amount = Withdraw::whereTime('createtime', 'yesterday')
            ->where('status', 1)
            ->whereIn('user_id', $user_ids)
            ->sum('money');


        // 昨天的数据
        $starttime = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $endtime = date('Y-m-d 23:59:59', strtotime('-1 day'));
        
        $es = new Es();

        $condition = [
            // 时间范围查询
            [
                'type' => 'range',
                'field' => 'createtime',
                'value' => [
                    'gte' => strtotime($starttime),
                    'lte' => strtotime($endtime),
                ]
            ],
            // 博主下面的用户id
            [
                'type'  => 'terms',
                'field' => 'user_id',
                'value' => $user_ids,
            ]
        ];

        $game_api_fee = config('channel.game_api_fee');
        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');

        // omg聚合游戏记录集合
        $omgGroupSearch = $es->groupAggregation('omg_game_record', $condition, 'platform', ['win_amount', 'bet_amount']);
        $omg_win_amount = array_sum(array_column($omgGroupSearch, 'win_amount_sum'));
        $omg_bet_amount = array_sum(array_column($omgGroupSearch, 'bet_amount_sum'));
        $omg_api = bcmul($omg_bet_amount - $omg_win_amount, $game_api_fee, 2);
        

        // jdb聚合游戏记录集合
        $jdbGroupSearch = $es->groupAggregation('jdb_game_record', $condition, 'platform', ['win_amount', 'bet_amount']);
        $jdb_win_amount = array_sum(array_column($jdbGroupSearch, 'win_amount_sum'));
        $jdb_bet_amount = array_sum(array_column($jdbGroupSearch, 'bet_amount_sum'));
        $jdb_api = bcmul($jdb_bet_amount - $jdb_win_amount, $game_api_fee, 2);

        $api_fee = bcadd($omg_api, $jdb_api, 2);

        $transfer_amount = $recharge_amount - $withdraw_amount;
        $channel_fee = $recharge_amount * $recharge_channel_rate + $withdraw_amount * $withdraw_channel_rate;
        $profit_and_loss = $recharge_amount - $withdraw_amount - $api_fee - $channel_fee;
      
        $retval =  [
            'admin_id'          => $admin_id,
            'user_id'           => $user_id,
            'recharge_amount'   => $recharge_amount,
            'withdraw_amount'   => $withdraw_amount,
            'transfer_amount'   => $transfer_amount,
            'api_amount'        => $api_fee,
            'channel_fee'       => $channel_fee,
            'profit_and_loss'   => $profit_and_loss,
            'date'              => date('Y-m-d'),
            'createtime'        => date('Y-m-d H:i:s'),
        ];
        
        return $retval;
    }

    /**
     * 发放下注佣金返佣
     */
    public function sendCommission()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        // 两个加起来等于总佣金total_bonus
        $arr = ['direct_bonus', 'indirect_bonus'];

        $rewards = RewardLog::where('type', 'in', $arr)->where('status', 0)->select();

        Db::startTrans();
        try{    
            foreach($rewards as $reward){
                $reward->status = 1;
                $reward->save();

                $user = User::get($reward->user_id);
                $user->money += $reward->money;
                $user->save();

                MoneyLog::create([
                    'admin_id'          => $reward->admin_id,
                    'user_id'           => $reward->user_id,
                    'money'             => $reward->money,
                    'type'              => $reward->type,
                    'memo'              => $reward->memo,
                    'transaction_id'    => $reward->transaction_id,
                ]);
            }

        }catch(\Exception $e){
            Db::rollback();
            echo $e->getMessage(); return;
        }
        echo 'sendCommission: 发放佣金成功'. "\n";
    }

    /**
     * 计算所有用户的下注佣金
     */
    public function betCommission()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $users = $this->getUsers();

        $rewardArr = ['direct_bonus' => '直属佣金', 'indirect_bonus' =>'间接佣金'];

        $arr = array_keys($rewardArr);

        // 检查是否已经计算过佣金
        $rewards = db('user_reward_log')
            ->where('type', 'in', $arr)
            ->whereTime('createtime', 'today')
            ->column('type', 'user_id');

        $user_ids = [];
        $data = [];
        foreach($users as $user){
            $commission = $this->calculateCommission($users, $user['id']);
            // 过滤总佣金为0的记录
            if($commission['total'] > 0){
                foreach($rewardArr as $key => $val){
                    // 判断是否已有记录, 没有的情况下可以插入, 有的话看是不是同一类型的
                    if(!isset($rewards[$user['id']]) || ($rewards[$user['id']] != $key)){
                        $data[] = [
                            'admin_id'          => $user->admin_id,
                            'user_id'           => $user->id,
                            'type'              => $key,   
                            'money'             => $commission[$key] * $user['bet_commission_rate'],
                            'memo'              => $val,
                            'transaction_id'    => $commission['rate'],
                        ];

                        $user_ids[] = $user['id'];
                    }
                }
            }
        }

        if(empty($data)){
            echo 'betCommission: 没有数据'. "\n"; return;
        }

        RewardLog::insertAll($data);
        echo "betCommission: 生成记录数：" . count($user_ids). "\n";
    }

    /**
     * 获取符合条件的所有用户
     */
    public function getUsers()
    {
        $map['today_bet'] = ['>', 0];
        $user_ids = db('user_data')->where($map)->column('user_id');

        $where['parent_id'] = ['>', 0];
        $where['id'] = ['in', $user_ids];
        $list = User::where($where)->column('parent_id_str');
        $userIds = [];
        foreach($list as $val){
            $parent_id_arr = explode(',', $val);
            if(count($parent_id_arr) > 2){
                unset($parent_id_arr[0]);
            }

            foreach($parent_id_arr as $v){
                $userIds[] = $v;
            }
        }

        $unique_user_ids = array_unique($userIds);
        $fields = "id,admin_id,parent_id,parent_id_str,money";
        $users = User::where('id', 'in', $unique_user_ids)->field($fields)->select();
        foreach($users as $val){
            $val->today_bet = $val->userdata->today_bet;
            $val->bet_commission_rate = $val->usersetting->bet_commission_rate;
            $val->hidden(['userdata', 'usersetting']);
        }
        return $users;
    }

    /**
     * 动态佣金率
     */
    public  function getCommissionRate($totalBets)
    {
        return $totalBets > 10000 ? 0.03 : 0.01;
    }

    /**
     * 计算佣金
     */
    public  function calculateCommission($users, $userId)
    {
        // 1. 计算总投注和佣金率
        $totalBets = $this->calculateTotalBets($users, $userId);
        $commissionRate = $this->getCommissionRate($totalBets);

        // 2. 直接佣金计算
        $directCommission = 0;
        foreach($users as $user){
            if($user['parent_id'] == $userId){
                $directCommission += $user['today_bet'] * $commissionRate;
            }
        }

        // 3. 间接佣金计算（仅计算正差额）
        $indirectCommission = 0;
        foreach ($users as $user) {
            if ($user['parent_id'] == $userId) {
                $subTotalBets = $this->calculateTotalBets($users, $user['id']);
                $subRate = $this->getCommissionRate($subTotalBets);
                $subSubBets = $subTotalBets - $user['today_bet'];
                $rateDiff = $commissionRate - $subRate;
                
                // 仅计算正差额
                if($rateDiff > 0){
                    $indirectCommission += $subSubBets * $rateDiff;
                }
            }
        }

        return [
            'direct_bonus'   => round($directCommission, 2),
            'indirect_bonus' => round($indirectCommission, 2),
            'total_bonus'    => round($directCommission + $indirectCommission, 2),
            'rate'           => $commissionRate
        ];
    }

    /**
     * 计算总有效投注额
     */
    public function calculateTotalBets($users, $userId)
    {
        $total = 0;
        foreach ($users as $user) {
            if ($user['id'] == $userId) {
                $total += $user['today_bet'];
                // 递归计算下属的投注额
                foreach ($users as $sub) {
                    if ($sub['parent_id'] == $userId) {
                        $total += $this->calculateTotalBets($users, $sub['id']);
                    }
                }
            }
        }
        return $total;
    }
}