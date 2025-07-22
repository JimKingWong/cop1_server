<?php

namespace app\common\service\util;

use think\cache\driver\Redis as DriverRedis;

class Redis
{
    protected $redis = null;

    public function __construct()
    {
        // 获取配置信息
        $options = config('cache.redis');
        $this->redis = new DriverRedis($options); // 实例化redis
    }

    /**
     * 保存数据 保存总投注额，保存总赢取额
     */
    public function saveData($bet_money, $win_money)
    {
        $bet_key = 'pg_total_bet_money';
        $win_key = 'pg_total_win_money';

        $this->redis->inc($win_key, $win_money);
        $this->redis->set($bet_key, $bet_money);
    }

    
}