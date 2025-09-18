<?php

namespace app\common\model\game;

use app\common\model\Game;
use think\Model;
use traits\model\SoftDelete;
use app\common\model\User;

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
       
        // 测试用户
        if($user->is_test == 1){
            return 'pgomg_test';
        }
 
        $is_risk = $user->usersetting->is_risk;

        if($is_risk == 0){
            // 为0的时候找上级的设置
            $users = User::whereIn('id', $user->parent_id_str)
                ->field('id,parent_id,parent_id_str,username,money')
                ->select();
            
            foreach ($users as $v) {
                // 添加安全检查：确保 usersetting 存在
                if ($v->usersetting->is_risk > 0) {
                    $is_risk = $v->usersetting->is_risk;
                    // 就近博主的设置 就远加break
                    // break;
                }
            }
        }

        // 保存
        if($is_risk != $user->usersetting->is_risk){
            $user->usersetting->is_risk = $is_risk;
            $user->usersetting->save();
        }
        
        $arr = [
            'pgomg',   // 索引0 - 默认奖池 pgomg
            'pg_omg_100X',    // 索引1 - 刷子
            'pg_omg_500X',    // 索引2 - 高
            'pg_omg_2000X',   // 索引3 - 低
            'pgomg'           // 索引4 - 85  pg_omg_1500X
        ];

        // \think\Log::record($user->admin_id . '_OMG_CODE: ' . $is_risk, 'omgcode');
        $code = $arr[$is_risk] ?? 'pg_omg_1500X';
        return $code;
    }
}
