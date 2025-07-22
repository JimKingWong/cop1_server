<?php

namespace app\common\model;

use think\Model;

/**
 * 会员奖励日志模型
 */
class RewardLog extends Model
{

    // 表名
    protected $name = 'user_reward_log';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    // 追加属性
    protected $append = [
    ];
}
