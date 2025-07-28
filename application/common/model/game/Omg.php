<?php

namespace app\common\model\game;

use app\common\model\Game;
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

    
    /**
     * 获取omgcode
     */
    public static function omgCode($user)
    {
        // 刷子直接走这个
        if($user->usersetting->is_risk == 1){
            return 'pg_omg_100X';
        }

        // 测试用户
        if($user->is_test == 1){
            return 'pgomg_test';
        }

        $rate = Game::withDrawRate();
        if($rate < 45){
            $code = 'pg_omg_500X';
        }elseif($rate >= 45 && $rate < 55){
            $code = 'pgomg';
        }elseif($rate >= 55 && $rate < 60){
            $code = 'pg_omg_1500X';
        }elseif($rate >= 60){
            $code = 'pg_omg_2000X';
        }

        return $code;
    }
}
