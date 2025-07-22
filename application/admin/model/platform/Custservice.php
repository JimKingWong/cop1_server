<?php

namespace app\admin\model\platform;

use think\Model;


class Custservice extends Model
{

    

    

    // 表名
    protected $name = 'custservice';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'channel_text',
        'status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!isset($row['weigh']) || !$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }

    
    public function getChannelList()
    {
        return ['0' => __('Channel 0'), '1' => __('Channel 1'), '2' => __('Channel 2'), '3' => __('Channel 3')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getChannelTextAttr($value, $data)
    {
        $value = $value ?: ($data['channel'] ?? '');
        $list = $this->getChannelList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
