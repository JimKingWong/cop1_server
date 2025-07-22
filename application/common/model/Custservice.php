<?php

namespace app\common\model;

use think\Model;

/**
 * 客服
 */
class Custservice extends Model
{

    protected $resultSetType = 'collection';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = "Y-m-d H:i:s";

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    

}
