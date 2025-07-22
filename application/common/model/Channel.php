<?php

namespace app\common\model;

use think\Model;

/**
 * 充值通道表
 */
class Channel extends Model
{
    protected $resultSetType = 'collection';

    protected $name = 'channel';

    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    protected $type = [
        'recharge_config' => 'array',
        'withdraw_config' => 'array',
    ];
}
