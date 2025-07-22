<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 充值提现
 * @ApiInternal
 * 
 */
class Channel extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * u2c充值回调
     */
    public function u2cpay_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->u2cpay_recharge();
    }

    /**
     * u2c提现回调
     */
    public function u2cpay_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->u2cpay_withdraw();
    }

    /**
     * ce充值回调
     */
    public function cepay_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->cepay_recharge();
    }

    /**
     * ce提现回调
     */
    public function cepay_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->cepay_withdraw();
    }

  /**
     * ce充值回调
     */
    public function ouropago_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->ouropago_recharge();
    }

    /**
     * ce提现回调
     */
    public function ouropago_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->ouropago_withdraw();
    }
}
