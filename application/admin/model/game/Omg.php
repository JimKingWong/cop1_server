<?php

namespace app\admin\model\game;

use think\Model;
use traits\model\SoftDelete;

class Omg extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'game_omg';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'platform_text',
        'type_text',
        'game_type_text',
        'status_text',
        'is_works_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }

    
    public function getPlatformList()
    {
        return ['1' => __('Platform 1'), '2' => __('Platform 2'), '3' => __('Platform 3'), '4' => __('Platform 4'), '5' => __('Platform 5'), '6' => __('Platform 6'), '7' => __('Platform 7'), '8' => __('Platform 8'), '23' => __('Platform 23'), '24' => __('Platform 24'), '25' => __('Platform 25')];
    }

    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }

    public function getGameTypeList()
    {
        return ['1' => __('Game_type 1'), '2' => __('Game_type 2'), '3' => __('Game_type 3'), '4' => __('Game_type 4')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getIsWorksList()
    {
        return ['0' => __('Is_works 0'), '1' => __('Is_works 1')];
    }


    public function getPlatformTextAttr($value, $data)
    {
        $value = $value ?: ($data['platform'] ?? '');
        $list = $this->getPlatformList();
        return $list[$value] ?? '';
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? '';
    }


    public function getGameTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['game_type'] ?? '');
        $list = $this->getGameTypeList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getIsWorksTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_works'] ?? '');
        $list = $this->getIsWorksList();
        return $list[$value] ?? '';
    }




}
