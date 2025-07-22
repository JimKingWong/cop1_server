<?php

namespace app\common\model;

use think\Model;

/**
 * 充值表
 */
class Recharge extends Model
{
    protected $resultSetType = 'collection';

    protected $name = 'recharge';

    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    public function channel()
    {
        return $this->belongsTo('Channel', 'channel_id', 'id')->field('id,title,name,recharge_config,withdraw_config');
    }

    /**
     * 创建充值订单号
     */
    public static function createOrderNo($pre_order_no)
    {
        return $pre_order_no . date('YmdHis') . rand(1000, 9999);
    }
}
