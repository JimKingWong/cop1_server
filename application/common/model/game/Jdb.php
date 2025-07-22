<?php

namespace app\common\model\game;

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


    public static function getPlatform()
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
