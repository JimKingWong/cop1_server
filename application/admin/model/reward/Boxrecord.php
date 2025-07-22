<?php

namespace app\admin\model\reward;

use think\Model;


class Boxrecord extends Model
{

    

    

    // 表名
    protected $name = 'box_record';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

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
        return $this->belongsTo('app\admin\model\reward\Box', 'num_id', 'num', [], 'LEFT')->setEagerlyType(0);
    }
}
