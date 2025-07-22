<?php

namespace app\common\model;

use think\Model;

class CollectLog extends Model
{


    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'user_collect_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';


    public function gamepg()
    {
        return $this->belongsTo('\app\common\model\game\Pg', 'g_id', 'id');
    }

    public function gamejdb()
    {
        return $this->belongsTo('\app\common\model\game\Jdb', 'g_id', 'id');
    }

    public function gametada()
    {
        return $this->belongsTo('\app\common\model\game\Tada', 'g_id', 'id');
    }
    
    public function gamepp()
    {
        return $this->belongsTo('\app\common\model\game\Pp', 'g_id', 'id');
    }

    public function gamecp()
    {
        return $this->belongsTo('\app\common\model\game\Cp', 'g_id', 'id');
    }

    public function gameomg()
    {
        return $this->belongsTo('\app\common\model\game\Omg', 'g_id', 'id');
    }

    /**
     * 用户收藏记录
     */
    public static function getCollectLog($user_id)
    {
        $list = self::where('user_id', $user_id)->field("id,concat(g_id,'-',table_name) game_name_id,status,type")->select();
        
        $log = [];
        foreach($list as $val){
            $log[$val['type']][$val->game_name_id] = $val->status;
        }
        return $log;
    }
}
