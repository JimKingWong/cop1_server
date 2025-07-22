<?php

namespace app\admin\model\game;

use think\Model;
use traits\model\SoftDelete;

class Jdb extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';

    // 表名
    protected $name = 'game_jdb';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'status_text',
        'is_works_text'
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

    
    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getIsWorksList()
    {
        return ['0' => __('Is_works 0'), '1' => __('Is_works 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
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

    public function getPlatform()
    {
        // 提供商:1=JDB,2=SPRIBE,3=GTF,4=FC,5=HRG,6=YB,7=MANCALA,8=ONLYPLAY,9=INJOY,10=CREEDROOMZ,11=AMB,12=ZESTPLAY,13=SMARTSOFT,14=FUNKY GAMES,15=SWGS,16=AVIATRIX
        return [
            1 => 'JDB',
            2 => 'SPRIBE',
            // 3 => 'GTF',
            // 4 => 'FC',
            // 5 => 'HRG',
            // 6 => 'YB',
            // 7 => 'MANCALA',
            // 8 => 'ONLYPLAY',
            // 9 => 'INJOY',
            // 10 => 'CREEDROOMZ',
            11 => 'AMB',
            // 12 => 'ZESTPLAY',
            13 => 'SMARTSOFT',
            // 14 => 'FUNKY GAMES',
        ];
    }


}
