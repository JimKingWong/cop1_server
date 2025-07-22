<?php

namespace app\common\model;

use think\Model;


class Box extends Model
{

    

    

    // 表名
    protected $name = 'box_config';
    
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
    

    
    /**
     * 领取记录
     */
    public static function record($user_id)
    {
        if(!$user_id) return [];

        return Boxrecord::where('user_id', $user_id)->column('user_id', 'num_id');
    }

    /**
     * 插入记录
     */
    public function insertRecord($data)
    {
        if(!$data) return false;

        return Boxrecord::create($data);
    }
}
