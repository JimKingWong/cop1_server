<?php

namespace app\admin\model\game;

use think\Model;
use traits\model\SoftDelete;

class Cate extends Model
{

    use SoftDelete;


    // 表名
    protected $name = 'game_category';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $resultSetType = 'collection';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
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

    public static function softDelData($where)
    {
        return self::onlyTrashed()->where($where)->select();
    }

    public function children()
    {
        return $this->hasMany(__CLASS__, 'pid');
    }

    public function games()
    {
        return $this->hasMany('Games', 'cate_id', 'id');
    }
}
