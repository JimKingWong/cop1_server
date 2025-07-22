<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 提现接口
 */
class Withdraw extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    /**
     * 初始化
     * @ApiMethod (GET)
     */
    public function init()
    {
        $serivce = new \app\common\service\Withdraw;
        $serivce->init();
    }

    /**
     * 提现申请
     * @ApiMethod (POST)
     * @ApiParams (name="pay_password", type="string", required=true, description="支付密码")
     * @ApiParams (name="money", type="string", required=true, description="充值金额")
     * @ApiParams (name="wallet_id", type="int", required=true, description="钱包id")
     */
    public function apply()
    {
        $serivce = new \app\common\service\Withdraw;
        $serivce->apply();
    }

    /**
     * 提现记录
     * @ApiMethod (GET)
     * @ApiParams (name="date", type="string", required=true, description="0今天,1昨天,7近7天以此类推")
     * 
     */
    public function record()
    {
        $serivce = new \app\common\service\Withdraw;
        $serivce->record();
    }
}
