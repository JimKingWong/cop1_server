<?php

namespace app\common\model;

use app\common\model\game\Omg;
use app\common\model\game\Platform;
use think\Cache;
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

    /**
     * 玩家提款率
     */
    public static function withDrawRate()
    {
        $rate = Cache::store('redis')->get('withdraw_rate');
        if(!$rate){
            $where['b.role'] = 0;
            $where['b.is_test'] = 0;
            $recharge_money = Recharge::alias('a')->join('user b', 'a.user_id = b.id')->where('a.status', '1')->whereTime('a.paytime', 'today')->where($where)->sum('a.money');

            $withdraw_money = Withdraw::alias('a')->join('user b', 'a.user_id = b.id')->where('a.status', '1')->whereTime('a.paytime', 'today')->where($where)->sum('a.money');

            if($recharge_money > 0){
                $rate = round($withdraw_money / $recharge_money, 2) * 100;
            }
            \think\Log::record($rate, 'withdraw_rate');
            Cache::store('redis')->set('withdraw_rate', $rate, 3600);
        }
        
        return $rate;
    }
}
