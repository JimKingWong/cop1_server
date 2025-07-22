<?php

namespace app\admin\model\channels;

use think\Model;


class Recharge extends Model
{

    
    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'recharge';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

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
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




    public function admindata()
    {
        return $this->belongsTo('app\admin\model\AdminData', 'admin_id', 'admin_id', [], 'LEFT')->setEagerlyType(0);
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function channel()
    {
        return $this->belongsTo('app\admin\model\channels\Channel', 'channel_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
