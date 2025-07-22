<?php

namespace app\common\service\util;

use fast\Http;

class Notice
{
    /**
     * 发送消息到群
     */
    public static function send($apiUrl, $params)
    {
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];
        $res = Http::post($apiUrl, http_build_query($params), $header);
        return $res;
    }

    /**
     * 大奖通知到群
     */
    public static function handlingGameAwards($user, $game, $bet_money, $win_money, $platform)
    {
        if($win_money < 3000){
            return;
        }

        // 插入大奖记录
        $data = [
            'admin_id'      => $user->admin_id,  
            'user_id'       => $user['id'],
            'game_id'       => $game['game_id'],
            'platform'      => $platform,
            'bet_amount'    => $bet_money,
            'win_amount'    => $win_money,
            'createtime'    => datetime(time()),
        ];

        db('prize_log')->insert($data);

        $admin_name = \Think\Env::get('database.database', '未知');

        $role = $user->role ? '博主' : '玩家';

        $message = "大奖预警 \n";
        $message .= "后台: 【{$admin_name}】 \n";
        $message .= "站点: 【{$user['origin']}】 \n";
        $message .= "用户ID: 【{$user['id']}】 \n";
        $message .= "类型: 【{$role}】 \n";
        $message .= "已充: 【{$user->userdata->total_recharge}】 \n";
        $message .= "游戏：【{$platform}】 \n";
        $message .= "游戏ID：【{$game['game_id']}】 \n";
        $message .= "下注金额: 【{$bet_money}】 \n";
        $message .= "派彩金额: 【{$win_money}】 \n";
 

        $chat_id = "-4193525325";

        $params = [
            'chat_id' => $chat_id,
            'text'    => $message,
        ];

        $apiUrl = "https://api.telegram.org/bot7120074308:AAGKWlR5XQ0MySxca2vup1MmMYW3mJ8vUjU/sendMessage";

        // 发送消息
        self::send($apiUrl, $params);

        // 发给填了飞机号的业务员
        if(isset($user->admin->chat_id)){
            $data = [
                'chat_id' => $user->admin->chat_id,
                'text'    => $message,
            ];
            self::send($apiUrl, $data);
        }
    }

    
    
}