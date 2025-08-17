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
     * supepay 充值回调
     */
    public function supepay_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->supepay_recharge();
    }

    /**
     * supepay 提现回调
     */
    public function supepay_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->supepay_withdraw();
    }

    /**
     * fun 充值回调
     */
    public function funpay_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->funpay_recharge();
    }

    /**
     * fun 提现回调
     */
    public function funpay_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->funpay_withdraw();
    }

    /**
     * u2c 充值回调
     */
    public function u2cpay_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->u2cpay_recharge();
    }

    /**
     * u2c 提现回调
     */
    public function u2cpay_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->u2cpay_withdraw();
    }

    /**
     * ce 充值回调
     */
    public function cepay_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->cepay_recharge();
    }

    /**
     * ce 提现回调
     */
    public function cepay_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->cepay_withdraw();
    }

    /**
     * ouro 充值回调
     */
    public function ouropago_recharge()
    {
        $service = new \app\common\service\Recharge();
        // 控制器返回
        return $service->ouropago_recharge();
    }

    /**
     * ouro 提现回调
     */
    public function ouropago_withdraw()
    {
        $service = new \app\common\service\Withdraw();
        // 控制器返回
        return $service->ouropago_withdraw();
    }
}
