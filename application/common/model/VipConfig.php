<?php

namespace app\common\model;

use think\Model;

/**
 * vip配置表
 */
class VipConfig extends Model
{
    protected $resultSetType = 'collection';

    protected $name = 'vip_config';

    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    /**
     * 获取vip列表
     */
    public static function vipList($fields = '')
    {
        if(!$fields){
            $fields = 'level,image,level_reward,recharge_amount,bet_amount,week_reward,month_reward,withdraw_times,withdraw_amount_day,withdraw_amount';
        }
        
        return self::order('level asc')->cache(true)->column($fields, 'level');
    }

    /**
     * 获取用户的vip信息
     */
    public static function vipInfo($user)
    {
        $vipList = self::vipList('image,bet_amount');

        // 当前vip信息
        $current_level = $user['level'];
        $current_vip = $vipList[$current_level];

        if($user){
            // 判断当前等级应该是等级几
            $cur_level = 0;
            foreach($vipList as $key => $val){
                if($user->userdata->total_bet >= $val['bet_amount']){
                    $cur_level = $key;
                }
            }
            
            // 保存等级
            if($cur_level > $user->level){
                $user->level = $cur_level;
                $user->save();
            }
        }

        // 当前下注量
        $current_total_bet = $user->userdata->total_bet;

        $next_level = $current_level + 1;
        $next_vip = $vipList[$next_level];

        $retval = [
            'current_level'         => $current_level,
            'current_level_image'   => cdnurl($current_vip['image']),
            'current_total_bet'     => $current_total_bet,
            'next_bet'              => $next_vip['bet_amount'],
            'need_bet'              => number_format($next_vip['bet_amount'] - $current_total_bet, 2),
            'next_level'            => $next_level,
            'percentage'            => round($current_total_bet / $next_vip['bet_amount'] * 100, 2),
        ];

        return $retval;
    }

    /**
     * 用户领取记录
     */
    public static function receiveLog($user_id)
    {
        return VipLog::where('user_id', $user_id)->column('*', 'level');
    }

    /**
     * 插入领取记录
     */
    public static function insertLog($data)
    {
        return VipLog::create($data);
    }
}
