<?php

namespace app\api\controller;

use app\common\controller\Api;


/**
 * 首页接口
 * @ApiInternal
 * 
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function css()
    {
        echo 111;
    }

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success(__('请求成功'));
    }

    /**
     * 部署第一步
     */
    private function startup()
    {
        // 创建es
        $service = new \app\common\service\util\Startup;
        // 创建es
        // $service::createEs();

        // 清理数据库数据
        // $service::clearData();

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
                [
                    'type' => 'term',
                    'field' => 'admin_id',
                    'value' => 0,
                ],
                // [
                //     'type' => 'range',
                //     'field' => 'createtime',
                //     'value' => [
                //         'gte' => strtotime($starttime),
                //         'lte' => strtotime($endtime),
                //     ]
                // ]
        ];
        // dd($condition);
        // 纯列表
        // $list = $service->multiSearch('omg_game_record', $condition);
        // 根据platform分组聚合
        $list = $service->groupAggregation('omg_game_record', $condition, 'platform', ['win_amount', 'bet_amount', 'transfer_amount']);

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
