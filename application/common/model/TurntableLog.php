<?php

namespace app\common\model;

use think\Model;

class TurntableLog extends Model
{


    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'user_turntable_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;


    
}
