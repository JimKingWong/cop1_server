<?php

namespace app\common\model\game;

use think\Model;
use traits\model\SoftDelete;

class Omg extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'game_omg';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';


    /**
     * 获取厂商平台代码
     */
    public static function getPlatform()
    {
        // 平台类型:1=Spribe,2=PG,3=JILI,4=PP,5=OMG_MINI,6=MiniGame,7=OMG_CRYPTO,8=Hacksaw,23=TADA,24=CP
        return [
            2 => 'PG', 
            4 => 'PP', 
            3 => 'JILI', 
            23 => 'TADA', 
            24 => 'CP',
            1 => 'Spribe', 
            5 => 'OMG_MINI', 
            6 => 'MiniGame', 
            7 => 'OMG_CRYPTO', 
            8 => 'Hacksaw', 
            25 => 'ASKME'
        ];
    }
}
