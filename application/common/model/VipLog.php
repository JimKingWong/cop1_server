<?php

namespace app\common\model;

use think\Model;

/**
 * vip领取记录模型
 */
class VipLog extends Model
{
    protected $resultSetType = 'collection';

    protected $name = 'user_vip_log';

    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    
}
