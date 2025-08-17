<?php

namespace app\admin\model\user;

use think\Model;


class Risklog extends Model
{

    

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'risk_task_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'is_pass_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getIsPassList()
    {
        return ['0' => __('Is_pass 0'), '1' => __('Is_pass 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getIsPassTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_pass'] ?? '');
        $list = $this->getIsPassList();
        return $list[$value] ?? '';
    }




    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
