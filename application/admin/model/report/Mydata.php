<?php

namespace app\admin\model\report;

use think\Model;


class Mydata extends Model
{

    

    

    // 表名
    protected $name = 'mydata';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







}
