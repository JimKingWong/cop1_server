<?php

namespace app\common\service\util;

/**
 * 项目启动
 */
class Startup
{
    /**
     * 创建es表
     */
    public static function createEs()
    {
        $es = new Es;

        $config = config('es');

        // 表前缀
        $prefix = $config['prefix'];

        // 需要创建的表
        $arr = [
            'omg',
            // 'spribe',
            // 'pg',
            // 'jili',
            // 'pp',
            // 'omg_mini',
            // 'mini',
            // 'omg_crypto',
            // 'hacksaw',
            // 'tada',
            // 'cp',
            'jdb',
        ];
        
        // 游戏记录表
        $params = [  
            'mappings' => [  
                'properties' => [
                    "admin_id"          => ["type" => "keyword"],
                    'user_id'           => ['type' => 'long'],
                    'game_id'           => ['type' => 'keyword'],
                    "image"             => ["type" => "keyword"] ,
                    'transaction_id'    => ['type' => 'keyword'],
                    'bet_amount'        => ['type' => 'double'],
                    'win_amount'        => ['type' => 'double'],
                    "transfer_amount"   => ["type" => "double"],
                    "typing_amount"     => ["type" => "double"],
                    "balance"           => ["type" => "double"],
                    "is_fake"           => ["type" => "integer"],
                    "platform"          => ["type" => "integer"],
                    "createtime"        => ["type" => "long"],
                ]
            ],  
        ];

       
        foreach($arr as $v){
            $indexName = $v . '_game_record';
            // 检查索引是否存在，不存在则创建
            if(!$es->indices()->exists(['index' => $prefix . '_' . $indexName])){
                $es->createIndex($indexName, $params);
                echo $prefix . '_' . $indexName . ' 创建成功! <br>';
            }else{
                echo $prefix . '_' . $indexName . ' 已经存在! <br>';
            }
        }

        $cq_params = [  
            'mappings' => [  
                'properties' => [
                    "admin_id"          => ["type" => "keyword"],
                    'user_id'           => ['type' => 'long'],
                    'game_id'           => ['type' => 'keyword'],
                    "image"             => ["type" => "keyword"],
                    "roundid"           => ["type" => "keyword"],
                    'mtcode'            => ['type' => 'keyword'], 
                    'amount'            => ['type' => 'double'],
                    'bet_amount'        => ['type' => 'double'],
                    'win_amount'        => ['type' => 'double'],
                    "transfer_amount"   => ["type" => "double"],
                    "balance"           => ["type" => "double"],
                    "is_fake"           => ["type" => "integer"],
                    "platform"          => ["type" => "integer"],
                    "flag"              => ["type" => "integer"], // 1 => bet, 2 => endround, 3 => rollout, 4 => takeall, 5 => rollin, 6 => debit, 7 => credit, 8 => payoff, 9 => refund
                    "createtime"        => ["type" => "long"],
                ]
            ],  
        ];

        if(!$es->indices()->exists(['index' => $prefix . '_cq_game_record'])){
            $es->createIndex('cq_game_record', $cq_params);
            echo $prefix . '_cq_game_record 创建成功! <br>';
        }else{
            echo $prefix . '_cq_game_record 已经存在! <br>';
        }

        // 以及用户余额变动表
        $userMoneyLog_params = [  
            'mappings' => [  
                'properties' => [  
                    'admin_id'          => ['type' => 'long'], 
                    'user_id'           => ['type' => 'long'],  
                    'money'             => ['type' => 'double'],  
                    'before'            => ['type' => 'double'],  
                    'after'             => ['type' => 'double'],  
                    'memo'              => ['type' => 'keyword'],  
                    'transaction_id'    => ['type' => 'keyword'],  
                    'createtime'        => ['type' => 'long'],  
                ], 
            ] 
        ];
        if(!$es->indices()->exists(['index' => $prefix . '_user_money_log'])){
            $es->createIndex('user_money_log', $userMoneyLog_params);
            echo $prefix . '_user_money_log 创建成功! <br>';
        }else{
            echo $prefix . '_user_money_log 已经存在! <br>';
        }
    }
    
}