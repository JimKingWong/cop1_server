<?php

namespace app\common\model;

use think\Model;


class Boxrecord extends Model
{

    

    

    // 表名
    protected $name = 'box_record';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function config()
    {
        return $this->belongsTo('app\admin\model\box\Config', 'num_id', 'num', [], 'LEFT')->setEagerlyType(0);
    }
}
