<?php

namespace app\admin\model\channels;

use think\Model;
use traits\model\SoftDelete;

class Channel extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'channel';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'state_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            // 默认生成充值订单号, 提现订单号前缀字段名
            $arr = ['pre_order_no' => ''];
            if (!isset($row['weigh']) || !$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk], 'recharge_config' => json_encode($arr)]);
            }
        });
    }

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getStateList()
    {
        return ['0' => __('State 0'), '1' => __('State 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getStateTextAttr($value, $data)
    {
        $value = $value ?: ($data['state'] ?? '');
        $list = $this->getStateList();
        return $list[$value] ?? '';
    }




}
