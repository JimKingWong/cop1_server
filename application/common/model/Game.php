<?php

namespace app\common\model;

use app\common\model\game\Platform;
use think\Model;
use traits\model\SoftDelete;

class Game extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'game';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

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

    public function gamecq()
    {
        return $this->belongsTo('\app\common\model\game\Cq', 'g_id', 'id');
    }

    /**
     * 获取游戏服务类
     */
    public static function services($where)
    {
        $platform = Platform::where($where)->find();
        return $platform;
    }

    
}
