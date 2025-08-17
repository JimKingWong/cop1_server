<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Game;
use app\admin\model\User;
use app\admin\service\UserService;
use app\api\service\RechargeService;
use app\api\service\SumService;
use app\common\controller\Api;
use app\common\library\Es;
use app\common\model\GameRecord;
use tests\thinkphp\library\think\dbTest;
use think\Cache;
use think\Db;
use think\Env;

class Cron extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

   
      //telegramBot 飞机机器人报表
          //telegramBot 飞机机器人报表
    public function telegramBot()
    {
        
        $n = -1;
        // 获取当前日期的时间戳
        $todayTimestamp = time() - 86400 * $n;
        // 计算昨天的时间戳
        $yesterdayTimestamp = strtotime('-1 day', $todayTimestamp);
        // 格式化昨天的日期为"Y-m-d"格式
        $yesterdayDate = date('Y-m-d', $yesterdayTimestamp);
        $todayDate = $todayTimestamp + 86400;
        $todayDate = strtotime('-1 day', $todayDate);
        $todayDate =date('Y-m-d', $todayDate);
        dump($yesterdayDate);
        dump($todayDate);
        //0注册人数
        $today_register_users = db('user')->whereTime('jointime', [$yesterdayDate,$todayDate])->count();
        dump("注册人数 " . $today_register_users);

        //1注册且充值人数
        $today_register_recharge_users = db('user')->whereTime('jointime', [$yesterdayDate,$todayDate])->where(['is_recharge' => 1])->count();
        dump("注册且充值人数 " . $today_register_recharge_users);

        //2复充人数
        $repeat_users = 10000;
        //昨天的充值人数 和 ID
        $recharge_list = db("recharge")
            ->alias('r')
            ->join('user u', 'r.uid = u.id')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->whereTime('u.jointime', "<", $yesterdayDate)
            ->where([
                'r.status' => ['=', '1'],
            ])
            ->field('r.uid,r.money')->group('r.uid')->select();
        //复充人数
        $repeat_users = count($recharge_list);
        dump("复充人数：" . $repeat_users);
        $repeat_ids = [];
        foreach ($recharge_list as $key => $val) {
            $repeat_ids[] = $val['uid'];

        }
        //3复充金额 $repeat_amount
        $repeat_amount = db("recharge")
            ->alias('r')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
                'r.uid' => ['in', $repeat_ids]
            ])->sum("money");
        dump("复充金额：" . $repeat_amount);


        //4充值人数
        $recharge_count = db("recharge")
            ->alias('r')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
            ])->group('r.uid')->count();

        dump("充值人数：" . $recharge_count);

        //5充值总金额
        $recharge_money = db("recharge")
            ->alias('r')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
            ])->sum("money");
        dump("充值总金额：" . $recharge_money);

        //6客损金额
        $user_lost = 0;
        $startTime = strtotime($yesterdayDate);
        $endTime = strtotime($todayDate);
        //dump($startTime);return;
        $es = new Es();
        $pg_win_amount = $es->sumAmountByDate(Env::get('es.gameRecord'), $startTime, $endTime,'win_amount');
        $pg_bet_amount = $es->sumAmountByDate(Env::get('es.gameRecord'), $startTime, $endTime,'bet_amount');
        $pg_ggr = $pg_bet_amount - $pg_win_amount;
        if ($pg_bet_amount == 0) {  
            $pg_rtp = 0;  
        } else {  
            $pg_rtp = round($pg_win_amount/$pg_bet_amount * 100, 2); 
        }  
        //PG  下注金额 - 派彩金额 = 盈亏
        // dump($pg_ggr);

        $jdb_api_win = $es->sumAmountByDate(Env::get('es.jdbEsGameRecord'), $startTime, $endTime,'win_amount');
        $jdb_api_bet = $es->sumAmountByDate(Env::get('es.jdbEsGameRecord'), $startTime, $endTime,'bet_amount');
        $jdb_ggr =$jdb_api_bet - $jdb_api_win;
        if ($jdb_api_bet == 0) {  
            $jdb_rtp = 0;  
        } else {  
            $jdb_rtp = round($jdb_api_win/$jdb_api_bet * 100, 2);
        }  
        // dump($jdb_ggr);

        $tada_api_win = $es->sumAmountByDate(Env::get('es.TadaEsGameRecord'), $startTime, $endTime, "win_amount");
        $tada_api_bet = $es->sumAmountByDate(Env::get('es.TadaEsGameRecord'),$startTime, $endTime, "bet_amount");
        $tada_ggr = $tada_api_bet - $tada_api_win;
        if ($tada_api_bet == 0) {  
            $tada_rtp = 0;  
        } else {  
            $tada_rtp = round($tada_api_win/$tada_api_bet * 100, 2);
        }  
        // dump($tada_ggr);
        
        $pp_api_win = $es->sumAmountByDate(Env::get('es.ppGameRecord'), $startTime, $endTime, "win_amount");
        $pp_api_bet = $es->sumAmountByDate(Env::get('es.ppGameRecord'),$startTime, $endTime, "bet_amount");
        $pp_ggr = $pp_api_bet - $pp_api_win;
        
        if ($pp_api_bet == 0) {  
            $pp_rtp = 0;  
        } else {  
            $pp_rtp = round($pp_api_win/$pp_api_bet * 100, 2);
        }  
        
        // dump($pp_ggr);
        $cp_api_win = $es->sumAmountByDate(Env::get('es.cpGameRecord'), $startTime, $endTime, "win_amount");
        $cp_api_bet = $es->sumAmountByDate(Env::get('es.cpGameRecord'),$startTime, $endTime, "bet_amount");
        $cp_ggr = $cp_api_bet - $cp_api_win;
        
        if ($cp_api_bet == 0) {  
            $cp_rtp = 0;  
        } else {  
            $cp_rtp = round($cp_api_win/$cp_api_bet * 100, 2);
        }  
        
         
        dump("PG盈亏: $pg_bet_amount - $pg_win_amount = $pg_ggr");
        dump("CP盈亏: $cp_api_bet - $cp_api_win = $cp_ggr ");
        dump("PP盈亏: $pp_api_bet - $pp_api_win = $pp_ggr ");
        dump("TADA盈亏: $tada_api_bet - $tada_api_win = $tada_ggr ");
        dump("JDB盈亏: $jdb_api_bet - $jdb_api_win = $jdb_ggr ");
        $user_lost = $pg_ggr + $jdb_ggr + $tada_ggr + $pp_ggr +$cp_ggr;
        dump("客损：" . $user_lost);

        // 7 提现金额
        $wd_money = db('withdrow')->whereTime('update_time', [$yesterdayDate,$todayDate])->where(['status' => "1"])->sum("money");
        dump("提现金额：" . $wd_money);

        //博主充值金额/工资金额
        $wd_bz_recharge_money = db("recharge")
            ->alias('r')->join('user u', "r.uid=u.id")
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
                'u.type' => 1
            ])->sum("r.money");
        $wd_bz_wage = db('user_money_log')->whereTime('createtime', [$yesterdayDate,$todayDate])->where(['memo' => "管理员发放佣金"])->sum("money");
        
        
        
        // 8 博主提现、客户提现
        $wd_bz_money = db('withdrow')->alias('w')->join('user u', "w.uid=u.id")
            ->whereTime('w.update_time', [$yesterdayDate,$todayDate])
            ->where(['w.status' => "1", 'u.type' => 1])
            ->sum("w.money");
        dump("博主提现金额：" . $wd_bz_money);
        
        
        // 9 博主提现、客户提现
        $wd_kf_money = $wd_money - $wd_bz_money;
        dump("客户提现金额：" . $wd_kf_money);
        //return;
        $bt_bz_money = Db::table('tp_box_table')  
            ->alias('b')  
            ->join('tp_user u', 'b.user_id = u.id')  
            ->where('u.type', 1)  
            ->whereTime('b.createtime',[$yesterdayDate,$todayDate])  
            ->sum('b.money');
        dump("博主领取宝箱：" . $bt_bz_money);
        $bt_kf_money = Db::table('tp_box_table')  
            ->alias('b')  
            ->join('tp_user u', 'b.user_id = u.id')  
            ->where('u.type', 0)  
            ->whereTime('b.createtime',[$yesterdayDate,$todayDate])  
            ->sum('b.money');
        dump("玩家领取宝箱：" . $bt_kf_money);

        // 10 充值费用
        $recharge_rate = Env::get('sys.recharge_rate');
        $withdraw_rate = Env::get('sys.withdraw_rate');
        $recharge_fee = $recharge_money * $recharge_rate + $wd_money * $withdraw_rate;
        dump("通道费用：" . $recharge_fee);

        // 11 API费用
        $API_fee = $user_lost * 0.07;
        dump("API费用：" . $API_fee);

        // 12 今日盈亏
        $win_money = $recharge_money - $wd_money - $recharge_fee - $API_fee;

        dump("今日盈利：" . $win_money);
       
        
        //13下注流水
        $bet_amount = $es->sumAmountByDate(Env::get('es.gameRecord'), $startTime, $endTime, "bet_amount");
        
        $reward_all = db('robot')
                ->whereTime('maketime','today')
                ->sum('make_money');
        $reward_do = db('robot')
                ->whereTime('maketime','today')
                ->where('status',1)
                ->sum('make_money');
        dump("风控数据：" . $reward_do.'/'.$reward_all);
        
        $rate_user = "0.00%";
        if($recharge_money != 0) $rate_user = bcdiv($wd_kf_money,$recharge_money,4) * 100 . "%";
        dump("客户提现金额：$wd_kf_money ($rate_user)");
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // 三级分佣金额
        $bz__commission = Db::name('user_commission')
            ->where('date', $yesterday)
            ->sum('total');
            
        dump("昨日总佣金金额：$bz__commission");
        
        // 查询1：今日首充奖励总金额（type=0）
        $firstRecharge = Db::name('user_award')
            ->where('date', date('Y-m-d'))
            ->where('type', 0)
            ->sum('money') ?: 0;
            
        // 查询2：今日亏损返次奖励总金额（type=1）
        $lossRebate = Db::name('user_award')
            ->where('date', $yesterday)
            ->where('type', 1)
            ->sum('money') ?: 0;
            
        // 查询3：今日PG奖励总金额（type=2）
        $pgReward = Db::name('user_award')
            ->where('date', $yesterday)
            ->where('type', 2)
            ->sum('money') ?: 0;
        
        
        //总流水
        $total_bet_amount = $pg_bet_amount + $cp_api_bet + $pp_api_bet + $tada_api_bet + $jdb_api_bet;
        $total_bet_amount = bcmul($total_bet_amount,1,2);
        //总派彩金额
        $total_win_amount = $pg_win_amount + $cp_api_win + $pp_api_win + $tada_api_win + $jdb_api_win;
        $total_win_amount = bcmul($total_win_amount,1,2);
        //总抽水
        $table_fee = bcmul($total_win_amount,0.1,2);
        
        //fake pg data
        $startTime = mktime(0, 0, 0, date('m'), date('d') , date('Y'));
        $endTime = mktime(23, 59, 59, date('m'), date('d') , date('Y'));
      
        $fake_pg_ggr = $es->sumFakePgAmount(Env::get('es.gameRecord'), $startTime, $endTime);
        
        $today = date("Y-m-d H:i:s");
        $str = "====={$today}===== \n";
        // $str .= "-----仿PG游戏-----\n";
        // $str .= "下注金额 " . $fake_pg_ggr['sum_bet_amount']['value'] . " \n";
        // $str .= "派彩金额 " .  $fake_pg_ggr['sum_win_amount']['value'] . " \n ";
        // $str .= "客损 ". $fake_pg_ggr['sum_transfer_amount']['value'] * -1 . "\n";
        // $str .= "玩家人数 ". $fake_pg_ggr['user_person']['value'] . " \n ";
        // $str .= "-----仿PG游戏-----\n";
        $str .= "PG游戏: 下注 $pg_bet_amount  派彩 $pg_win_amount 客损 $pg_ggr RTP: $pg_rtp%\n";
        $str .= "TADA游戏: 下注 $tada_api_bet 派彩  $tada_api_win 客损 $tada_ggr RTP: $tada_rtp%\n";
        $str .= "JDB游戏: 下注 $jdb_api_bet 派彩  $jdb_api_win 客损 $jdb_ggr RTP: $jdb_rtp%\n";
        $str .= "PP游戏: 下注 $pp_api_bet 派彩  $pp_api_win 客损 $pp_ggr RTP: $pp_rtp%\n";
        $str .= "CP游戏: 下注 $cp_api_bet 派彩  $cp_api_win 客损 $cp_ggr RTP: $cp_rtp%\n";
        // $str .= "风控数据: $reward_do / $reward_all \n";
        $str .= "注册人数: $today_register_users \n";
        $str .= "注册且充值人数: $today_register_recharge_users \n";
        $str .= "复充人数: $repeat_users \n";
        $str .= "复充金额: $repeat_amount \n";
        $str .= "充值人数: $recharge_count \n";
        $str .= "充值金额: $recharge_money \n";
        $str .= "有效下注: $bet_amount \n";
        $str .= "总流水: $total_bet_amount \n";
        $str .= "总派彩: $total_win_amount \n";
        // $str .= "总抽水: $table_fee \n";
        $str .= "总客损: $user_lost \n";
        $str .= "提现金额: $wd_money \n";

        $str .= "博主工资金额(后台): $wd_bz_wage \n";
        $str .= "博主领取宝箱(邀请): $bt_bz_money \n";
        $str .= "博主佣金发放(流水): $bz__commission \n";
        
        $str .= "博主充值金额: $wd_bz_recharge_money \n";
        $str .= "博主提现金额: $wd_bz_money \n";
        $str .= "玩家领取宝箱: $bt_kf_money \n";
        $str .= "玩家提现金额: $wd_kf_money ($rate_user) \n";
        
        $str .= "-----活动----- \n";
        $str .= "PG流水奖励: $pgReward \n";
        $str .= "玩家亏损返水: $lossRebate \n";
        $str .= "玩家首充奖励: $firstRecharge \n";
        $str .= "-----活动----- \n";
        
        $str .= "通道费用: $recharge_fee \n";
        $str .= "API费用: $API_fee \n";
        $str .= "今日盈利: $win_money \n";
        $url = "https://api.telegram.org/bot7120074308:AAGKWlR5XQ0MySxca2vup1MmMYW3mJ8vUjU/sendMessage";
        $chat_id = db('config')->where(['name'=>'telegram_chat_id'])->value("value");
        $result = http_post($url,['chat_id'=>$chat_id,"text"=>$str]);
        $res = json_decode($result, true);
        if($res['ok']){
            echo "Sent successfully";
        }else{
            dump($res);
            echo "Sent failed";
        }
        
               /* "today_register_users" => $today_register_users,
                "today_register_recharge_users" => $today_register_recharge_users,
                "repeat_users" => $repeat_users,
                "repeat_amount" => $repeat_amount,
                "recharge_count" => $recharge_count,
                "recharge_money" => $recharge_money,
                "user_lost" => $user_lost,
                "wd_money" => $wd_money,
                "wd_bz_money" => $wd_bz_money,
                "wd_kf_money" => $wd_kf_money,
                "recharge_fee" => $recharge_fee,
                "API_fee" => $API_fee,
                "win_money" => $win_money,
                "bet_amount" => $bet_amount,
                "date_str" =>$yesterdayDate,
                "create_time" =>time(),*/
    }
    

    
    /**
     * 
     * 数据报表
     * 0注册人数
     * 1注册且充值人数
     * 2复充人数
     * 3复充值金额
     * 4充值人数
     * 5充值总金额
     * 6客损金额
     * 7提现金额
     * 8博主提现、客户提现
     * 9充值费用
     * 10 API费用
     * 11 今日盈亏
     * @return void
     */
    public function dataRecord()
    {

        $n = 0;
        // 获取当前日期的时间戳
        $todayTimestamp = time() - 86400 * $n;
        // 计算昨天的时间戳
        $yesterdayTimestamp = strtotime('-1 day', $todayTimestamp);
        // 格式化昨天的日期为"Y-m-d"格式
        $yesterdayDate = date('Y-m-d', $yesterdayTimestamp);
        $todayDate = $todayTimestamp + 86400;
        $todayDate = strtotime('-1 day', $todayDate);
        $todayDate =date('Y-m-d', $todayDate);
        dump($yesterdayDate);
        dump($todayDate);
        //0注册人数
        $today_register_users = db('user')->whereTime('jointime', [$yesterdayDate,$todayDate])->count();
        dump("注册人数 " . $today_register_users);

        //1注册且充值人数
        $today_register_recharge_users = db('user')->whereTime('jointime', [$yesterdayDate,$todayDate])->where(['is_recharge' => 1])->count();
        dump("注册且充值人数 " . $today_register_recharge_users);

        //2复充人数
        $repeat_users = 10000;
        //昨天的充值人数 和 ID
        $recharge_list = db("recharge")
            ->alias('r')
            ->join('user u', 'r.uid = u.id')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->whereTime('u.jointime', "<", $yesterdayDate)
            ->where([
                'r.status' => ['=', '1'],
            ])
            ->field('r.uid,r.money')->group('r.uid')->select();
        //复充人数
        $repeat_users = count($recharge_list);
        dump("复充人数：" . $repeat_users);
        $repeat_ids = [];
        foreach ($recharge_list as $key => $val) {
            $repeat_ids[] = $val['uid'];

        }
        //3复充金额 $repeat_amount
        $repeat_amount = db("recharge")
            ->alias('r')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
                'r.uid' => ['in', $repeat_ids]
            ])->sum("money");
        dump("复充金额：" . $repeat_amount);


        //4充值人数
        $recharge_count = db("recharge")
            ->alias('r')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
            ])->group('r.uid')->count();

        dump("充值人数：" . $recharge_count);

        //5充值总金额
        $recharge_money = db("recharge")
            ->alias('r')
            ->whereTime('r.create_time', [$yesterdayDate,$todayDate])
            ->where([
                'r.status' => ['=', '1'],
            ])->sum("money");
        dump("充值总金额：" . $recharge_money);

        //6客损金额
        $user_lost = 0;
        $startTime = strtotime($yesterdayDate);
        $endTime = strtotime($todayDate);
        //dump($startTime);return;
        $es = new Es();

        $pg_win_amount = $es->sumAmountByDate(Env::get('es.gameRecord'), $startTime, $endTime,'win_amount');
        $pg_bet_amount = $es->sumAmountByDate(Env::get('es.gameRecord'), $startTime, $endTime,'bet_amount');
        $pg_ggr = $pg_bet_amount - $pg_win_amount;

        $jdb_api_win = $es->sumAmountByDate(Env::get('es.jdbEsGameRecord'), $startTime, $endTime,'win_amount');
        $jdb_api_bet = $es->sumAmountByDate(Env::get('es.jdbEsGameRecord'), $startTime, $endTime,'bet_amount');
        $jdb_ggr =$jdb_api_bet - $jdb_api_win;
        
        
        $tada_api_win = $es->sumAmountByDate(Env::get('es.TadaEsGameRecord'), $startTime, $endTime, "win_amount");
        $tada_api_bet = $es->sumAmountByDate(Env::get('es.TadaEsGameRecord'),$startTime, $endTime, "bet_amount");
        $tada_ggr = $tada_api_bet - $tada_api_win;
        //dump($tada_ggr);return;

        $pp_api_win = $es->sumAmountByDate(Env::get('es.ppGameRecord'), $startTime, $endTime, "win_amount");
        $pp_api_bet = $es->sumAmountByDate(Env::get('es.ppGameRecord'),$startTime, $endTime, "bet_amount");
        $pp_ggr = $pp_api_bet - $pp_api_win;

        $cp_api_win = $es->sumAmountByDate(Env::get('es.ppGameRecord'), $startTime, $endTime, "win_amount");
        $cp_api_bet = $es->sumAmountByDate(Env::get('es.ppGameRecord'),$startTime, $endTime, "bet_amount");
        $cp_ggr = $cp_api_bet - $cp_api_win;

        $user_lost = $pg_ggr + $jdb_ggr + $tada_ggr + $pp_ggr +$cp_ggr;
        dump("客损：" . $user_lost);

        // 7 提现金额
        $wd_money = db('withdrow')->whereTime('update_time', [$yesterdayDate,$todayDate])->where(['status' => "1"])->sum("money");
        dump("提现金额：" . $wd_money);


        // 8 博主提现、客户提现
        $wd_bz_money = db('withdrow')->alias('w')->join('user u', "w.uid=u.id")
            ->whereTime('w.update_time', [$yesterdayDate,$todayDate])
            ->where(['w.status' => "1", 'u.type' => 1])
            ->sum("w.money");
        dump("博主提现金额：" . $wd_bz_money);
        // 9 博主提现、客户提现
        $wd_kf_money = $wd_money - $wd_bz_money;
        dump("客户提现金额：" . $wd_kf_money);

        // 10 充值费用
        $recharge_rate = Env::get('sys.recharge_rate');
        $withdraw_rate = Env::get('sys.withdraw_rate');
        $recharge_fee = $recharge_money * $recharge_rate + $wd_money * $withdraw_rate;
        dump("通道费用：" . $recharge_fee);

        // 11 API费用
        $API_fee = $user_lost * 0.07;
        dump("API费用：" . $API_fee);

        // 12 今日盈亏
        $win_money = $recharge_money - $wd_money  - $recharge_fee - $API_fee;

        dump("今日盈利：" . $win_money);
        
        
        //13下注流水
        $bet_amount = $es->sumAmountByDate(Env::get('es.gameRecord'), $startTime, $endTime, "bet_amount");

        if(!db("mydata")->where(['date_str'=>$yesterdayDate])->find()){
            db("mydata")->insert([
                "today_register_users" => $today_register_users,
                "today_register_recharge_users" => $today_register_recharge_users,
                "repeat_users" => $repeat_users,
                "repeat_amount" => $repeat_amount,
                "recharge_count" => $recharge_count,
                "recharge_money" => $recharge_money,
                "user_lost" => $user_lost,
                "wd_money" => $wd_money,
                "wd_bz_money" => $wd_bz_money,
                "wd_kf_money" => $wd_kf_money,
                "recharge_fee" => $recharge_fee,
                "API_fee" => $API_fee,
                "win_money" => $win_money,
                "bet_amount" => $bet_amount,
                "date_str" =>$yesterdayDate,
                "create_time" =>time(),
            ]);
            dump("数据插入成功");
        }else{
            db("mydata")->where(['date_str'=>$yesterdayDate])->update([
                "today_register_users" => $today_register_users,
                "today_register_recharge_users" => $today_register_recharge_users,
                "repeat_users" => $repeat_users,
                "repeat_amount" => $repeat_amount,
                "recharge_count" => $recharge_count,
                "recharge_money" => $recharge_money,
                "user_lost" => $user_lost,
                "wd_money" => $wd_money,
                "wd_bz_money" => $wd_bz_money,
                "wd_kf_money" => $wd_kf_money,
                "recharge_fee" => $recharge_fee,
                "API_fee" => $API_fee,
                "win_money" => $win_money,
                "bet_amount" => $bet_amount,
            ]);
            dump("数据更新成功");
        }



    }

    //清除用户的 今日收益 & 清除今日流水
    public function clear_today_profit()
    {
         //清除今日收益
        db('user')->where('today_profit', '<>', 0)->update(['today_profit' => 0]);
        //清除今日流水
        db('user')->where('today_bet_amount', '<>', 0)->update(['today_bet_amount' => 0]);
        db('admin')->where('extra_money', '<>', 0)->update(['extra_money' => 0]);
         echo "all users today_profit& today_bet_amount been clean";return;
    }

  
    
    //管理员数据
    public function summary_admin_daybook($diff = 1)
    {
        $sumService = new SumService();
        $sumService->summary_admin_daybook_api($diff);
        echo "agents data been update successfully";return;
    }

    //清零 余额小于等于0.5的提现限制
    public function clear_typing_amount_limit()
    {
        db('user')
            ->where([
               "money" => ["<=",0.5],
               "typing_amount_limit" => [">",0]
            ])->update(["typing_amount_limit" => 0]);
        echo "users typing_amount_limit been clean";return;
    }

    //3秒内下注次数清零
    public function clear_user_rob_time()
    {
        return "hoho";
        db('user')
            ->where([
                "rob_time" => [">",0],
            ])->update(["rob_time" => 0]);
        echo "users rob_time been clean";return;
    }
    
    //清理pp记录数据
    public function deleteOldRecords()  
    {  
        $oneDayAgoTimestamp = strtotime('-1 day');
        $result = Db::name('pp_record')  
            ->where('createtime', '<', $oneDayAgoTimestamp)  
            ->delete();  
  
        if ($result) {  
            echo "Deleted pp_record records older than 1 day.";
        } else {  
            echo 'Failed to delete records.';
        }
        return;
    }  

    private function clearTable()
    {
        $tableNames = [
            'tp_withdrow',
            'tp_vip_table',
            'tp_user_token',
            'tp_user_money_log',
            'tp_user_invite_config',
            'tp_user',
            'tp_todaybet_table',
            'tp_summary_data',
            'tp_send_record',
            'tp_robot',
            'tp_rechargedaliy',
            'tp_recharge',
            'tp_prize_record',
            'tp_pp_record',
            'tp_pg_result',
            'tp_mydata',
            'tp_daybookadmin',
            'tp_box_table',
            'tp_daybook',
            'tp_from_url',
            'tp_admin_log'
        ];

        $needclear = [
            'tp_withdrow',
            'tp_vip_table',
            'tp_user_money_log',
            'tp_user_invite_config',
            'tp_user',
            'tp_todaybet_table',
            'tp_summary_data',
            'tp_send_record',
            'tp_robot',
            'tp_rechargedaliy',
            'tp_recharge',
            'tp_prize_record',
            'tp_mydata',
            'tp_daybookadmin',
            'tp_daybook',
            'tp_box_table',
            'tp_from_url',
            'tp_admin_log'
        ];

        $results = [];

        foreach ($tableNames as $tableName) {
            try {
                Db::query("truncate table `$tableName`");
                if (in_array($tableName, $needclear)) {
                    Db::execute("ALTER TABLE `$tableName` AUTO_INCREMENT = 1");
                }
                echo "Table $tableName has been cleared and auto-increment has been reset to 1.\n";
            } catch (\Exception $e) {
                echo "Error clearing table $tableName: " . $e->getMessage()."\n";
            }
        }
        echo "清理完成";
        $name = Env::get('database.username', 'game');
        Db::name('admin')->where('id',2)->update([
            'username'=>$name,
            'remark'=>$name
        ]);
        Db::name('auth_group')->where('id',52)->update([
            'name'=>$name
        ]);
        Db::execute("ALTER TABLE `tp_user` AUTO_INCREMENT = 21000000");
        
        //取前缀
        $prefix = Env::get('es.prefix', 'xxx');
        $data = [
            ['url' => $name.'.com', 'name'=>$prefix,'create_time'=>time()],
            ['url' => 'localhost', 'name'=>$prefix,'create_time'=>time()],
        ];
        Db::name('from_url')->insertAll($data);
        return;
    }
    
    
    
    private function createalles(){
        $es = new Es();
        $body_userMoneyLog = [  
            'mappings' => [  
                'properties' => [  
                    'user_id' => ['type' => 'long'],  
                    'money' => ['type' => 'double'],  
                    'before' => ['type' => 'double'],  
                    'after' => ['type' => 'double'],  
                    'memo' => ['type' => 'keyword'],  
                    'transaction_id' => ['type' => 'keyword'],  
                    'createtime' => ['type' => 'long'],  
                    'root_invite' => ['type' => 'keyword'], 
                ], 
            ],  
        ];  
        $body_gameRecord = [  
            'mappings' => [  
                'properties' => [  
                    'user_id' => ['type' => 'long'],  
                    'game_id' => ['type' => 'long'],  
                    'transaction_id' => ['type' => 'keyword'],  
                    'transfer_amount' => ['type' => 'double'],  
                    'bet_amount' => ['type' => 'double'],  
                    'win_amount' => ['type' => 'double'],  
                    'createtime' => ['type' => 'long'],  
                    'root_invite' => ['type' => 'keyword'], 
                    'typing_amount' => ['type' => 'double'],
                    'game_id_str' => ['type' => 'keyword']
                ], 
            ],  
        ];
        $body_cpGameRecord = [  
            'mappings' => [  
                'properties' => [  
                    'user_id' => ['type' => 'long'],  
                    'game_id' => ['type' => 'keyword'],  
                    'transaction_id' => ['type' => 'keyword'],  
                    'transfer_amount' => ['type' => 'double'],  
                    'bet_amount' => ['type' => 'double'],  
                    'win_amount' => ['type' => 'double'],  
                    'createtime' => ['type' => 'long'],  
                    'root_invite' => ['type' => 'keyword'], 
                    'typing_amount' => ['type' => 'double'],
                ], 
            ],  
        ];
        $body_ppGameRecord = [  
            'mappings' => [  
                'properties' => [  
                    'user_id' => ['type' => 'long'],  
                    'game_id' => ['type' => 'keyword'],  
                    'transaction_id' => ['type' => 'keyword'],  
                    'transfer_amount' => ['type' => 'double'],  
                    'bet_amount' => ['type' => 'double'],  
                    'win_amount' => ['type' => 'double'],  
                    'createtime' => ['type' => 'long'],  
                    'root_invite' => ['type' => 'keyword'], 
                    'typing_amount' => ['type' => 'double'],
                    'bet_type' => ['type' => 'keyword'],
                ], 
            ],  
        ];
        
       
          
        $indexName = Env::get('es.userMoneyLog');
        $result_1 = $es->createIndex($indexName, $body_userMoneyLog);
        if ($result_1 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
        
        $indexName = Env::get('es.gameRecord');
        $result_2 = $es->createIndex($indexName, $body_gameRecord);
        if ($result_2 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
        $indexName = Env::get('es.jdbEsGameRecord');
        $result_3 = $es->createIndex($indexName, $body_gameRecord);
        if ($result_3 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
        $indexName = Env::get('es.TadaEsGameRecord');
        $result_4 = $es->createIndex($indexName, $body_gameRecord);
        if ($result_4 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
        $indexName = Env::get('es.BotRecord');
        $result_5 = $es->createIndex($indexName, $body_gameRecord);
        if ($result_5 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
        $indexName = Env::get('es.cpGameRecord');
        $result_6 = $es->createIndex($indexName, $body_cpGameRecord);
        if ($result_6 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
        $indexName = Env::get('es.ppGameRecord');
        $result_7 = $es->createIndex($indexName, $body_ppGameRecord);
        if ($result_7 !== false) {
            echo "$indexName created successfully.\n";
        } else {  
            echo "Failed to create $indexName.";
        }
    }
    
    public function agentData()
    {
        $username = $this->request->get('username');
        $date = $this->request->get('date', -1);

        $token = $this->request->get('token');
        if($token != 'xxxxxx'){
            $this->error('token校验失败, 请重试');
        }

        $admin = new \app\admin\model\Admin;

        $check = $admin->getByUsername($username);
       
        if(empty($check)){
            $this->error('未找到业务组');
        }

        $authGroupAccess = new \app\admin\model\AuthGroupAccess;
        $group_id = $authGroupAccess->where('uid', $check->id)->value('group_id');

        $authGroup = new \app\admin\model\AuthGroup;
        $pid = $authGroup->where('id', $group_id)->value('pid');
        if($pid != 1){
            $data = [
                'recharge_amount'       => sprintf('%.2f', 0),
                'withdraw_amount'       => sprintf('%.2f', 0),
                'api_amount'            => sprintf('%.2f', 0),
                'recharge_rate_amount'  => sprintf('%.2f', 0),
                'all_profit'            => sprintf('%.2f', 0)
            ];
            $this->success('请求成功', $data);
        }else{
            // $all_code = $this->getAllId($group_id, $check->agent_code);
            $all_code = \app\admin\model\department\Admin::getChildrenAdminIds($check->id, true);
        }

        $dayBookAdmin = new \app\admin\model\report\Daybook;

        $cur_month = date('Y-m-1');
        if($date > 0){
            $where['date'] = ['>=', $cur_month];
        }else{
            $last_month = date('Y-m-d', strtotime('-1 month', strtotime($cur_month)));
            $cur_month = date('Y-m-d', strtotime('-1 day',strtotime($cur_month)));
            $where['date'] = ['between', [$last_month, $cur_month]];
        }

        $where['admin_id'] = ['in', $all_code];
        $list = $dayBookAdmin->where($where)->select();
        
        $recharge_amount        = 0;
        $withdraw_amount        = 0;
        $api_amount             = 0;
        $recharge_rate_amount   = 0;

        foreach($list as $val){
            $recharge_amount        += $val->recharge_amount;
            $withdraw_amount        += $val->withdraw_amount;
            $api_amount             += $val->api_amount;
            $recharge_rate_amount   += $val->channel_fee;
        }

        $all_profit = $recharge_amount - $withdraw_amount - $api_amount - $recharge_rate_amount;

        $data = [
            'recharge_amount'       => sprintf('%.2f', $recharge_amount),
            'withdraw_amount'       => sprintf('%.2f', $withdraw_amount),
            'api_amount'            => sprintf('%.2f', $api_amount),
            'recharge_rate_amount'  => sprintf('%.2f', $recharge_rate_amount),
            'all_profit'            => sprintf('%.2f', $all_profit)
        ];

        $this->success('请求成功', $data);
    }
    
    public function getAgentData()
    {
        $username = $this->request->get('username');

        $token = $this->request->get('token');
        if($token != 'xxxxxx'){
            $this->error('token校验失败, 请重试');
        }

        $admin = new \app\admin\model\Admin;

        $check = $admin->getByUsername($username);
       
        if(empty($check)){
            $this->error('未找到业务组');
        }

        $where = [];
        $map = [];
        if($check->type == 1){
            $authGroupAccess = new \app\admin\model\AuthGroupAccess;
            $group_id = $authGroupAccess->where('uid', $check->id)->value('group_id');

            $authGroup = new \app\admin\model\AuthGroup;
            $pid = $authGroup->where('id', $group_id)->value('pid');

            
            if($pid != 1){
                $all_code = $check->agent_code;

                $all_id = $this->auth->id;

            }else{
                $all_code = $this->getAllCode($group_id, $check->agent_code);

                
                $all_id = $this->getAllId($group_id,$this->auth->agent_code);
            }
            
            //获取所有业务员的邀请码
            $where['root_invite'] = ['in', $all_code];

            $map['admin_id'] = ['in', $all_id];
        }

        $user = new \app\admin\model\User();
        $totalUser = $user->where($where)->count();

        $todayRechargeAmount =  \app\admin\model\Recharge::whereTime('create_time', 'today')->where('status', 1)->where($where)->sum('real_pay_amount');

        $sumRechargeAmount = db('daybookadmin')->where($map)->sum('recharge_amount') + $todayRechargeAmount;

        //今日提现
        $todayWithdrawAmount  = \app\admin\model\Withdrow::whereTime('update_time', 'today')->where(['status' => 1])->where($where)->sum('money');
        
        //总提现 = 所以的历史提现加上今天的提现金额
        $sumWithdrawAmount = db('daybookadmin')->where($map)->sum('withdraw_amount') + $todayWithdrawAmount;

        $retval = [
            'total_user'            => $totalUser,
            'sum_recharge_amount'   => sprintf('%.2f', $sumRechargeAmount),
            'sum_withdraw_amount'   => sprintf('%.2f', $sumWithdrawAmount)
        ];

        $this->success('请求成功', $retval);
    }

    private function getAllCode($group_id,$our = "")
    {
       $all_group_id =  db("auth_group")->where(['pid'=>$group_id])->field("id")->select();
       //没有下级
       if(!$all_group_id){
           return [$our];
       }
       $all_user_id = db("auth_group_access")->where(['group_id'=>$all_group_id[0]['id']])->field("uid")->select();

       $data = [];
       foreach ($all_user_id as $key=>$value){
           $data[] = $value['uid'];
       }
       $ic = db("admin")->where(['id'=>['in',$data]])->field('agent_code')->select();
        $codes = [];
        foreach ($ic as $key=>$value){
            $codes[] = $value['agent_code'];
        }
        $codes[] = $our;
       return $codes;
    }

    private function getAllId($group_id, $our = "")
    {
       $all_group_id =  db("auth_group")->where(['pid'=>$group_id])->field("id")->select();
       //没有下级
       if(!$all_group_id){
           return [$our];
       }
       $all_user_id = db("auth_group_access")->where(['group_id'=>$all_group_id[0]['id']])->field("uid")->select();

       $data = [];
       foreach ($all_user_id as $key=>$value){
           $data[] = $value['uid'];
       }
       
       return $data;
    }
    
     /**
     * 设置白名单
     */
    public function banIp()
    {
        $params = $this->request->post();
        
        $token = '1fdsa#gdfs%34d';
        if($params['token'] != $token){
            $this->error('校验失败');
        }
        // dd($params);
        $username = !empty($params['child_username']) ? $params['child_username'] : $params['username'];

        $admin = db('admin')->where('username', $username)->field('id,username')->find();

        if(empty($admin)){
            $this->error('未找到用户');
        }

        $group_access = db('auth_group_access')->where('uid', $admin['id'])->find();

        // 找到分组
        $group = db('auth_group')->where('status', 'normal')->where('id', $group_access['group_id'])->find();
        
        if(!$group){
            $this->error('未找到分组');
        }
        
        $ip = $params['ip'] ?? '';
        if($group['pid'] > 1){
            // 需修改的用户id
            $uids = $admin['id'];
        }elseif($group['pid'] == 1){
            // 等于1则表示主管
            $group_ids = db('auth_group')->where('status', 'normal')->where('pid', $group['id'])->column('id');

            // 合并主管的分组id
            array_push($group_ids, $group['id']);

            // 需修改的用户id
            $uids = db('auth_group_access')->where('group_id', 'in', $group_ids)->column('uid');
        }

        if(empty($uids)){
            $this->error('没有可操作对象');
        }

        $result = db('admin')->where('id', 'in', $uids)->update([
            'ip'    => $ip
        ]);

        if($result === false){
            $this->error('设置失败');
        }
        $this->success('设置成功');
    }
    
      /**
     * 定时添加白名单
     */
    public function cronBanip()
    {
        $params = $this->request->post();
        
        $token = '1fdsagdfs34d';
        if($params['token'] != $token){
            $this->error('校验失败');
        }

        // 客服 和 admin的不禁止
        $group_ids = [0, 55];
        $where['pid'] = ['in', $group_ids];
        $child_group_ids = db('auth_group')->where($where)->column('id');

        $group_ids = array_merge($group_ids, $child_group_ids);
        if(!$group_ids){
            $this->error('没有可执行对象');
        }

        $uids = db('auth_group_access')->where('group_id', 'not in', $group_ids)->column('uid');

        $ip = $params['ip'];
        $result = db('admin')->where('id', 'in', $uids)->update([
            'ip'    => $ip
        ]);

        if($result === false){
            $this->error('设置失败');
        }
        $this->success('设置成功');
    }
    
    //充值返利任务
    // public function recharge_gift(){
    //     $tasks = Db::name('daily_task') 
    //         ->where('status', 0)  
    //         ->where('type', 1)  
    //         ->limit(5)  
    //         ->select();  
  
    //     foreach ($tasks as $task) {  
    //         // 计算返水金额  
    //         $rewardAmount = round($task['amount'] * $task['scale'] / 100, 2);
  
    //         // 检查返水金额是否大于0.1  
    //         if ($rewardAmount > 0.1) {  
    //             // 更新用户余额
    //             $pay = new RechargeService();
    //             $res = $pay->money($rewardAmount, $task['user_id'], $rewardAmount, '充值返水', $task['id'], 'recharge_gift');
    //             echo '用户:'.$task['user_id'].'发放奖励:'.$rewardAmount."\n";
    //             //站内信通知
    //             $data = [  
    //                 'typedata' => 1, // 消息类型  
    //                 'user_ids' =>  $task['user_id'], 
    //                 'title' => 'Recompensas de campanhas de recarga', // 标题  
    //                 'content' => '<b style=""><font ><span style="font-size: 14px; color: #fff;">Recharge rewards emissão concluída.  Valor: '.$rewardAmount.' reais.</span></font></b>', // 内容  
    //                 'createtime' => time(), // 创建时间，使用当前时间戳  
    //             ];  

    //             Db::name('letter')->insert($data);  
               
    //         }else{
    //             $rewardAmount = 0 ;
    //         }
    //         //更新任务状态
    //         Db::name('daily_task')  
    //         ->where('id', $task['id'])
    //         ->update(['status' => 1,'money' => $rewardAmount]);
    //     }  
    //     echo "充值返水任务处理完成";
    // }

    /**
     * 下注佣金
     */
    public function betCommission()
    {
        // 没有分佣的组
        $noCommissionGroup = db('auth_group')->where('is_commission', 0)->column('id');
        // 所有下级id
        $access_uids = db('auth_group_access')->where('group_id', 'in', $noCommissionGroup)->column('uid');
        // 获取agent_code
        $admin_agent_codes = db('admin')->where('id', 'in', $access_uids)->column('agent_code');

        if($admin_agent_codes){
            $where['root_invite'] = ['not in', $admin_agent_codes];
        }
        $where['today_bet_amount'] = ['>', 0];
        $where['pid'] = ['>', 0];
        $where['commission_rate'] = ['>', 0];
        $fields = "id,pid,ppid,money,today_bet_amount,root_invite,commission_rate";
        $list = db('user')->where($where)->field($fields)->select();
        if(empty($list)){
            return '无数据';
        }

        foreach($list as $val){
            $user_ids[] = $val['id'];
            $user_ids[] = $val['pid'];
            if($val['ppid']){
                $user_ids[] = $val['ppid'];
            }
        }

        $user_ids = array_unique($user_ids);
        $users = db('user')->where('id', 'in', $user_ids)->where('commission_rate', '>', 0)->field($fields)->select();
        $user_money_arr = [];
        foreach($users as $val){
            $user_money_arr[$val['id']]['money'] = $val['money'];
            $user_money_arr[$val['id']]['root_invite'] = $val['root_invite'];
            $user_money_arr[$val['id']]['commission_rate'] = $val['commission_rate'] / 100;
        }

        $results = [];
        foreach ($users as $user) {
            $commission = $this->calculateCommission($users, $user['id']);
            // 过滤总佣金为0的记录
            if ($commission['total'] > 0) {
                $results[$user['id']] = $commission;
                $results[$user['id']]['commission_rate'] = $user_money_arr[$user['id']]['commission_rate'] ?? 0;
            }
        }
        // dd($results);

        // 检查是否已经计算过佣金
        $commission_user_ids = db('user_commission')->where('date', date('Y-m-d'))->column('user_id');

        $data = [];
        $k = 0;
        foreach($results as $key => $val){
            if(!in_array($key, $commission_user_ids)){
                $data[$k]['user_id']    = $key;
                $data[$k]['direct']     = $val['direct'];
                $data[$k]['indirect']   = $val['indirect']; 
                $data[$k]['total']      = $val['total'] * $val['commission_rate']; // 发放太多,需要加这个0.6
                $data[$k]['rate']       = $val['rate'];
                $data[$k]['date']       = date('Y-m-d');
                $data[$k]['createtime'] = datetime(time());
                $k ++;
            }
        }

        if(empty($data)){
            return '无数据';
        }
        db('user_commission')->insertAll($data);
        return '计算完成';

    }

    /**
     * 发放昨日佣金
     */
    public function sendCommission()
    {
        $date = date('Y-m-d', strtotime('-1 day'));
        $commission = db('user_commission')->where('date', $date)->where('status', 0)->select();

        if(empty($commission)){
            return '无数据';
        }

        $user_ids = array_column($commission, 'user_id');

        $userInfo = db('user')->where('id', 'in', $user_ids)->column('money, root_invite', 'id');

        $data = [];
        $k = 0;
        foreach($commission as  $val){
            db('user')->where(['id' => $val['user_id']])->setInc('money', $val['total']);
            db('user_commission')->where(['id' => $val['id']])->update(['status' => 1, 'sendtime' => datetime(time())]);
            
            $memo[$k] = date('Y-m-d') . ' 直属佣金: ' . $val['direct'] . ', 间接佣金: ' . $val['indirect'] . ', 总佣金: ' . $val['total'];
            $data[$k]['user_id'] = $val['user_id'];
            $data[$k]['money'] = $val['total'];
            $data[$k]['before'] = $userInfo[$val['user_id']]['money'] ?? 0;
            $data[$k]['after'] = $data[$k]['before'] + $data[$k]['money'];
            $data[$k]['memo'] = $memo[$k];
            $data[$k]['root_invite'] = $userInfo[$val['user_id']]['root_invite'] ?? '';
            $data[$k]['transaction_id'] = date('Ymd');
            $data[$k]['createtime'] = time();
            $k ++;
        }

        db('user_money_log')->insertAll($data);
        echo implode(' || ', $memo);
    }

    
    /**
     * 获取所有用户数据
     */
    public function getUsers()
    {
        $where['today_bet_amount'] = ['>', 0];
        $where['pid'] = ['>', 0];
     
        $fields = "id,pid,ppid,money,today_bet_amount";
        $list = db('user')->where($where)->field($fields)->select();

        foreach($list as $val){
            $user_ids[] = $val['id'];
            $user_ids[] = $val['pid'];
            if($val['ppid']){
                $user_ids[] = $val['ppid'];
            }
        }

        $user_ids = array_unique($user_ids);
        $users = db('user')->where('id', 'in', $user_ids)->select();
        return $users;
    }

    /**
     * 计算总有效投注额
     */
    public function calculateTotalBets($users, $userId)
    {
        $total = 0;
        foreach ($users as $user) {
            if ($user['id'] == $userId) {
                $total += $user['today_bet_amount'];
                // 递归计算下属的投注额
                foreach ($users as $sub) {
                    if ($sub['pid'] == $userId) {
                        $total += $this->calculateTotalBets($users, $sub['id']);
                    }
                }
            }
        }
        return $total;
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
        foreach ($users as $user) {
            if ($user['pid'] == $userId) {
                $directCommission += $user['today_bet_amount'] * $commissionRate;
            }
        }

        // 3. 间接佣金计算（仅计算正差额）
        $indirectCommission = 0;
        foreach ($users as $user) {
            if ($user['pid'] == $userId) {
                $subTotalBets = $this->calculateTotalBets($users, $user['id']);
                $subRate = $this->getCommissionRate($subTotalBets);
                $subSubBets = $subTotalBets - $user['today_bet_amount'];
                $rateDiff = $commissionRate - $subRate;
                
                // 仅计算正差额
                if ($rateDiff > 0) {
                    $indirectCommission += $subSubBets * $rateDiff;
                }
            }
        }

        return [
            'direct'   => round($directCommission, 2),
            'indirect' => round($indirectCommission, 2),
            'total'    => round($directCommission + $indirectCommission, 2),
            'rate'     => $commissionRate
        ];
    }

    /**
     * 计算所有用户的佣金（过滤佣金为0的记录）
     */
    public function calculateAllCommissionsFiltered()
    {
        $users = $this->getUsers();
        $results = [];

        foreach ($users as $user) {
            $commission = $this->calculateCommission($users, $user['id']);
            // 过滤总佣金为0的记录
            if ($commission['total'] > 0) {
                $results[$user['id']] = $commission;
            }
        }

        return $results;
    }
    
    public function backwater()
    {
        $where['today_profit'] = ['<', 0];
        $list = db('user')->where($where)->field('id,today_profit')->select();

        $check = db('user_award')->where('date', date('Y-m-d'))->where('type', 1)->find();
        
        if($check){
            return '今天已经跑过数据了';
        }

        $data = [];
        foreach($list as $val){
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

            if($today_profit >= 200){
                $data[] = [
                    'user_id'       => $val['id'],
                    'type'          => 1,
                    'money'         => round($today_profit * $rate, 2),
                    'rate'          => $rate,
                    'date'          => date('Y-m-d'),
                    'createtime'    => datetime(time()),
                ];
            }
        }
        
        if(empty($data)){
            return '没有数据';
        }
        db('user_award')->insertAll($data);
        return '收集亏损数据完成';
    }
    
    /**
     * 下注奖励
     */
    public function betAward()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制
        
        $check = db('user_award')->where('date', date('Y-m-d'))->where('type', 2)->find();
        
        if($check){
            return '今天已经跑过数据了';
        }
        
        $where['today_bet_amount'] = ['>=', 500];
        $list = db('user')->where($where)->field('id,today_bet_amount')->select();

        $es = new Es();

        $gameRecord = Env::get('es.gameRecord');

        $arr = [];
        foreach($list as $val){
            $arr[$val['id']][] = $es->sumAmount($gameRecord, $val['id'], 'bet_amount');
        }

        foreach($arr as $key => $val){
            $money = 0;
            if($val >= 500){
                $money = 1;
            }else if($val >= 1000){
                $money = 2;
            }else if($val >= 3000){
                $money = 3;
            }else if($val >= 5000){
                $money = 7;
            }else if($val >= 10000){
                $money = 17;
            }else if($val >= 30000){
                $money = 37;
            }else if($val >= 50000){
                $money = 77;
            }else if($val >= 100000){
                $money = 127;
            }else if($val >= 300000){
                $money = 277;
            }else if($val >= 500000){
                $money = 377;
            }else if($val >= 1000000){
                $money = 777;
            }else if($val >= 3000000){
                $money = 1777;
            }else if($val >= 10000000){
                $money = 8777;
            }else if($val >= 30000000){
                $money = 18888;
            }

            if($money > 0){
                $data[] = [
                    'user_id'       => $key,
                    'type'          => 2,
                    'money'         => $money,
                    'rate'          => 0,
                    'date'          => date('Y-m-d'),
                    'createtime'    => datetime(time()),
                ];
            }
        }

        if(empty($data)){
            return '没有数据';
        }
        \think\Log::record($arr, 'betAward');
        db('user_award')->insertAll($data);
        return '收集下注数据完成';
    }
}