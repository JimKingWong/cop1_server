<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;

class Turntable extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'turntable';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';


     public function getWheelList()
    {
        $selectType = [
            'silver'    => [
                'name'          => 'Sorteio de roleta',
                'type'          => 'silver',
                'need'          => 2000, // 需要积分
                'range'         => 200, // 中奖范围
                'selected'      => true, // 初始化选择
            ],
            // 'golden'      => [
            //     'name'          => 'Golden Wheel',
            //     'type'          => 'golden',
            //     'need'          => 7000,
            //     'range'         => 2000, // 中奖范围
            //     'selected'      => false
            // ],
            // 'diamond'   => [
            //     'name'          => 'Diamond Wheel',
            //     'type'          => 'diamond',
            //     'need'          => 30000,
            //     'range'         => 0, // 中奖范围 0 不限制
            //     'selected'      => false
            // ]
        ];
        return $selectType;
    }

    /**
     * 列表
     */
    public static function list($where, $fields = 'id,name')
    {
        return self::where($where)->order('weigh desc')->field($fields)->select();
    }
}
