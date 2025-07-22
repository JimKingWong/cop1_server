<?php

namespace app\admin\model\game;

use think\Model;
use traits\model\SoftDelete;

class Platform extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'game_platform';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'is_default_text',
        'status_text'
    ];
    

    
    public function getIsDefaultList()
    {
        return ['0' => __('Is_default 0'), '1' => __('Is_default 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getIsDefaultTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_default'] ?? '');
        $list = $this->getIsDefaultList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
