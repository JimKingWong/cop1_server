<?php

namespace app\admin\model\platform;

use think\Model;


class Site extends Model
{

    

    

    // 表名
    protected $name = 'site';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function cases()
    {
        return $this->belongsTo('app\admin\model\game\Cases', 'case_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
