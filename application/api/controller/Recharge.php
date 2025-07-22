<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 充值
 * 
 */
class Recharge extends Api
{
    protected $noNeedLogin = ['init'];
    protected $noNeedRight = ['*'];

    /**
     * 初始
     * @ApiMethod (GET)
     */
    public function init()
    {
        $service = new \app\common\service\Recharge;
        $service->init();
    }

    /**
     * 创建充值订单
     * @ApiMethod (POST)
     * @ApiParams (name="channel_id", type="integer", description="充值渠道")
     * @ApiParams (name="money", type="string", description="充值金额")
     * 
     */
    public function create()
    {
        $service = new \app\common\service\Recharge;
        $service->create();
    }

    /**
     * 充值记录
     * @ApiMethod (GET)
     * @ApiParams (name="status", type="integer", description="状态:0-未支付,1-已支付, 传空显示全部")
     * @ApiParams (name="date", type="string", description="0 1 7 15 30")
     * 
     */
    public function record()
    {
        $service = new \app\common\service\Recharge;
        $service->record();
    }
}
