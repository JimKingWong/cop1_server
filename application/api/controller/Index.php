<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Admin;
use app\common\model\game\Omg;
use app\common\model\Mydata;
use app\common\model\Recharge;
use app\common\model\User;
use app\common\model\Withdraw;
use app\common\service\util\Es;
use app\common\service\util\Notice;
use app\common\service\util\Redis;
use app\common\service\util\Startup;
use think\Response;

/**
 * 首页接口
 * @ApiInternal
 * 
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     */
    public function index()
    {
        $this->success('请求成功');
    }

    /**
     * 修正业务员数据
     */
    public function daybookadmin()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '1024M'); // 调整内存限制

        $field = "id,username,role";
        $where['role'] = ['>', 2];
        $admins = Admin::where($where)->field($field)->select();

        $admin_ids = [];
        foreach($admins as $admin){
            $admin_ids[] = $admin->id;
        }

        array_push($admin_ids, 0);

        // 过去几天, 修改这个参数
        $day = 3;
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
    

            $add_count = 0;
            $edit_count = 0;
            if(isset($adminLogs[$admin_id])){
                $arr[$key] = [
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
                $edit_count += db('daybookadmin')->where('id', $adminLogs[$admin_id])->update($arr[$key]);
                echo "summaryAdminDaybook: 修正日结报表，日期：{" . date('Y-m-d', strtotime('-'.$day.' day')) . "}，处理记录数：" . $edit_count. "\n";
            }else{
                $data[$key] = [
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
                $add_count += db('daybookadmin')->insertAll($data);
                echo "summaryAdminDaybook: 生成日结报表，日期：{" . date('Y-m-d', strtotime('-'.$day.' day')) . "}，处理记录数：" . count($data). "\n";
            }
        }
    }

    public function test()
    {
        $user = $this->auth->getUser();
        $game = Omg::where('id', 10)->find();
        $platform = 'PG';
        $bet_money = 100;
        $win_money = 200;

        Notice::handlingGameAwards($user, $game, $bet_money, $win_money, $platform);

        // $message = "大奖预警 \n";
        // $message .= "后台: 【{$admin_name}】 \n";
        // $message .= "站点: 【{$user['origin']}】 \n";
        // $message .= "用户ID: 【{$user['id']}】 \n";
        // $message .= "类型: 【{$user->role}】 \n";
        // $message .= "已充: 【{$user->userdata->total_recharge}】 \n";
        // $message .= "游戏：【{$platform['code']}】 \n";
        // $message .= "游戏ID：【{$game['id']}】 \n";
        // $message .= "下注金额: 【{$bet_money}】 \n";
        // $message .= "派彩金额: 【{$win_money}】 \n";
        // $params = [
        //     'chat_id'  => 7104843880,
        //     'text'  => $message,
        //     'parse_mode' => 'HTML'
        // ];
        // $apiUrl = "https://api.telegram.org/bot7593152406:AAGQc3rjkIXo1PlxCF4HEhTdSxPapAyAYDc/sendMessage";

        // // 设置请求头
        // $header = [
        //     CURLOPT_HTTPHEADER  => [
        //         'Content-Type: application/x-www-form-urlencoded',
        //     ]
        // ];
        // // $res = \fast\Http::post($apiUrl, $params);
        // $res = \fast\Http::post($apiUrl, http_build_query($params), $header);
        // dd($res);
    }

    public function download()
    {
        set_time_limit(0);

        $path = './uploads/cq8/';


        $games = [
            [
                'name' => 'Go Fishing',
                'url' => 'https://sgsag.w1-bullionpg.com/game_pictures/g/EA/3/3/30355/default.avif?g0=1750854608'
            ]
        ];
        // dd(count($games));
        $arr = [];
        foreach($games as $val){
            $arr[$val['name']] = $val['url'];
            // $this->save($path, $val['name'], $val['url']);
        }
        $list = db('game_cq')->where('thumb', null)->select();
        // dd($list);
        foreach($list as $key => $val){
            // $game_name = str_replace(' ', '', strtolower($val['game_name']));
            $game_name = $val['game_name'];
            if(isset($arr[$game_name])){
                // $this->save($path, $val['game_id'], $arr[$game_name]);
                db('game_cq')->where('id', $val['id'])->update([
                    // 'thumb' => '/uploads/game/cq/v1/big/' . $val['game_id'] . '.png'
                ]);
            }
        }

    }

    public function save($path, $filename, $image)
    {
        $imageUrl = str_replace('https://', 'http://', $image);
     
        $context = stream_context_create([
            'ssl'   => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ]);
        $imageData = file_get_contents($imageUrl, false, $context);
        if($imageData){
            file_put_contents($path . $filename . '.png', $imageData);
        }
    }

    /**
     * 部署第一步
     */
    public function startup()
    {
        // 创建es
        $service = new \app\common\service\util\Startup;
        $service::createEs();
    }

    public function csss()
    {
       set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制
        $field = "id,username,role";
        $admins = Admin::where('role', '>', 2)->field($field)->select();

        $admin_ids = [];
        foreach($admins as $admin){
            $admin_ids[] = $admin->id;
        }
        
        $recharge = Recharge::whereTime('createtime', 'yesterday')
            ->where('status', 1)
            ->whereIn('admin_id', $admin_ids)
            ->group('admin_id')
            ->column('sum(real_pay_amount)', 'admin_id');

        $withdraw = Withdraw::whereTime('createtime', 'yesterday')
            ->where('status', 1)
            ->whereIn('admin_id', $admin_ids)
            ->group('admin_id')
            ->column('sum(money)', 'admin_id');

        // 昨天的数据
        $starttime = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $endtime = date('Y-m-d 23:59:59', strtotime('-1 day'));

        // 博主工资
        $salary = db('user_reward_log')->whereTime('createtime', 'yesterday')->where('status', 1)->where('type', 'admin_bonus')->group('admin_id')->column('sum(money)', 'admin_id');
        $es = new Es();

        // 用作判断是否已插入
        $adminLogs = db('daybookadmin')->where('date', date('Y-m-d', strtotime('-1 day')))->column('id', 'admin_id');

        $game_api_fee = config('channel.game_api_fee');
        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');
        $data = [];
        foreach($admins as $key => $admin){
            if(!isset($adminLogs[$admin->id])){
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
                        'value' => $admin->id
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

                $recharge_amount = $recharge[$admin->id] ?? 0;
                $withdraw_amount = $withdraw[$admin->id] ?? 0;
                $transfer_amount = $recharge_amount - $withdraw_amount;
                $channel_fee = $recharge_amount * $recharge_channel_rate + $withdraw_amount * $withdraw_channel_rate;
                $profit_and_loss = $recharge_amount - $withdraw_amount - $api_fee - $channel_fee;
                
                $data[] = [
                    'admin_id'              => $admin->id,
                    'salary'                => $salary[$admin->id] ?? 0,
                    'recharge_amount'       => $recharge[$admin->id] ?? 0,
                    'withdraw_amount'       => $withdraw[$admin->id] ?? 0,
                    'transfer_amount'       => $transfer_amount,
                    'api_amount'            => sprintf('%.2f', $api_fee),
                    'channel_fee'           => sprintf('%.2f', $channel_fee),
                    'profit_and_loss'       => sprintf('%.2f', $profit_and_loss),
                    'date'                  => date('Y-m-d', strtotime('-1 day')),
                    'createtime'            => date('Y-m-d H:i:s'),
                ];
            }
        }
        // dd($data);
        if(empty($data)){
            echo 'summaryAdminDaybook: 没有数据'. "\n"; return;
        }

        db('daybookadmin')->insertAll($data);
        echo "summaryAdminDaybook: 生成日结报表，日期：{" . date('Y-m-d', strtotime('-1 day')) . "}，处理记录数：" . count($data). "\n";
    }

    /**
     * 查询用户记录
     */
    public function record()
    {
        // wdn6tpcBt8C6GeCFMQGU
        $service = new \app\common\service\util\Es;
        // $list = $service->searchRecord(11, 'omg_game_record');
        // $list = $service->searchByPlatform(2, 'omg_game_record');

        // // 检查索引是否存在
        // $indexExists = $service->checkIndexExists('jdb_game_record');
        // if (!$indexExists) {
        //     $this->error('索引不存在');
        // }
        // dd($indexExists);
        // // 创建索引
        // $params = [  
        //     'mappings' => [  
        //         'properties' => [
        //                 "admin_id"          => ["type" => "keyword"],
        //                 'user_id'           => ['type' => 'long'],
        //                 'game_id'           => ['type' => 'keyword'],
        //                 "image"             => ["type" => "keyword" ],
        //                 'transaction_id'    => ['type' => 'keyword'],
        //                 'bet_amount'        => ['type' => 'double'],
        //                 'win_amount'        => ['type' => 'double'],
        //                 "transfer_amount"   => ["type" => "double"],
        //                 "typing_amount"     => ["type" => "double"],
        //                 "balance"           => ["type" => "double"],
        //                 "is_fake"           => ["type" => "integer"],
        //                 "platform"          => ["type" => "integer"],
        //                 "createtime"        => ["type" => "long"],
        //             ]
        //     ],  
        // ];
        // $res = $service->createIndex('jdb_game_record', $params);
        // if($res){
        //     $this->success('ok');
        // }else{
        //     $this->error('error');
        // }
        
        // // 删除索引
        // $service->deleteIndex('jdb_game_record');
        // $this->success('ok');

        $starttime = date('Y-m-d 00:00:00');
        $endtime = date('Y-m-d 23:59:59');
        // 昨天的数据
        // $starttime = date('Y-m-d 00:00:00', strtotime('-1 day'));
        // $endtime = date('Y-m-d 23:59:59', strtotime('-1 day'));

        $condition = [
                // 用户id搜索
                // [
                //     'type' => 'term',
                //     'field' => 'user_id',
                //     'value' =>  30002292,
                // ],

                // 平台搜索
                // [
                //     'type' => 'term',
                //     'field' => 'platform',
                //     'value' => 13,
                // ],

                // // 交易id搜索
                // [
                //     'type' => 'term',
                //     'field' => 'transaction_id',
                //     'value' => '1975-02-09 21:25:16',
                // ],

                // admin_id搜索
                // [
                //     'type' => 'term',
                //     'field' => 'admin_id',
                //     'value' => 0,
                // ],
                [
                    'type' => 'range',
                    'field' => 'createtime',
                    'value' => [
                        'gte' => strtotime($starttime),
                        'lte' => strtotime($endtime),
                    ]
                ]
        ];
        // dd($condition);
        // 纯列表
        // $list = $service->multiSearch('omg_game_record', $condition);
        // 根据platform分组聚合
        $list = $service->groupAggregation('omg_game_record', $condition, 'game_id', ['win_amount', 'bet_amount', 'transfer_amount']);

        // $omg_win_amount = array_sum(array_column($list, 'win_amount_sum'));
        // $omg_bet_amount = array_sum(array_column($list, 'bet_amount_sum'));
        // $game_api_fee = config('channel.game_api_fee');
        // $omg_api = bcmul($omg_bet_amount - $omg_win_amount, $game_api_fee, 2);

        // dd($omg_api);

        // $list = $service->groupAggregation('omg_game_record', $condition, 'platform', ['bet_amount']);
       
        // $list = $service->searchByTransactionId('82935895969', 'jdb_game_record');
        $this->success('ok', $list);
    }

    /**
     * 删除es索引
     */
    public function deleteEsIndex()
    {
        $service = new \app\common\service\util\Es;

        // 删除索引
        // $service->deleteIndex('cq_game_record');
        $this->success('ok');
    }

    /**
     * 模拟游戏数据插入
     */
    public function addgameRecord()
    {
        // $data = array (
        //     'app_id' => '8285',
        //     'bet' => '0.5',
        //     'game_id' => 68,
        //     'money' => '-0.5',
        //     'order_id' => '20250705065802eqzppj2ayo02',
        //     'player_login_token' => 'adfe75d6-adcc-4c03-ba49-b2234048ad95',
        //     'session_id' => '1941391100066770944',
        //     'round_id' => '1941391100066770944',
        //     'timestamp' => 1751698682,
        //     'uname' => '10',
        //     'end_round' => false,
        //     'type' => 1,
        //     'cancel_order_id' => '',
        //     'award_order_ids' => NULL,
        // );
        // dd(json_encode($data, JSON_UNESCAPED_UNICODE));
      
        $omgService = new \app\common\service\game\Omg;

        $omgService->changeBalance();
    }
}
