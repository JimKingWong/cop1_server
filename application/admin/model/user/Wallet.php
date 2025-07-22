<?php

namespace app\admin\model\user;

use think\Model;


class Wallet extends Model
{

    

    

    // 表名
    protected $name = 'user_wallet';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_default_text'
    ];
    

    
    public function getIsDefaultList()
    {
        return ['0' => __('Is_default 0'), '1' => __('Is_default 1')];
    }


    public function getIsDefaultTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_default'] ?? '');
        $list = $this->getIsDefaultList();
        return $list[$value] ?? '';
    }




    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
