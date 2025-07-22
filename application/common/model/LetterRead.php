<?php

namespace app\common\model;

use think\Model;


class LetterRead extends Model
{
    protected $name = 'letter_read';


    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = "Y-m-d H:i:s";

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    // 追加属性
    protected $append = [
    ];


  
}
