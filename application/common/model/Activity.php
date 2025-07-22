<?php

namespace app\common\model;

use think\Model;

/**
 * 活动管理
 */
class Activity extends Model
{

    protected $resultSetType = 'collection';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = "Y-m-d H:i:s";

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $type = [
        'config'    => 'array'
    ];


    /**
     * 获取配置数据
     */
    public static function config($name)
    {
        if(!$name) return [];

        $row = self::where('name', $name)->find();

        $retval = [
            'id'        => $row->id,
            'status'    => $row->status,
            'config'    => $row->config,
        ];
        return $retval;
    }

}
