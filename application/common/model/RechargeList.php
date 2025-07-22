<?php

namespace app\common\model;

use think\Model;

/**
 * 充值配置表
 */
class RechargeList extends Model
{
    protected $resultSetType = 'collection';

    protected $name = 'recharge_list';

    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    
}
