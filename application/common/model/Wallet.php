<?php

namespace app\common\model;

use think\Model;

/**
 * 会员钱包模型
 */
class Wallet extends Model
{

    // 表名
    protected $name = 'user_wallet';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
}
