<?php

namespace app\admin\model;

use think\Model;

class AdminData extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';
    // 定义表名
    protected $name = 'admin_data';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
   

   

}
