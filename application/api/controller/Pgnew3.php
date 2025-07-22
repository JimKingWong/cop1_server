<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 * @ApiInternal
 * 
 */
class Pgnew3 extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 获取链接
     */
    public function link()
    {
        $service = new \app\common\service\Game();
        $service->startup(); // 启动游戏服务
    }

    /**
     * 令牌验证api
     */
    public function VerifySession() 
    {
        $service = new \app\common\service\game\Pgnew3;
        return $service->VerifySession();
    }

    /**
     * 获取用户钱包
     */
    public function Get()
    {
        $service = new \app\common\service\game\Pgnew3;
        return $service->Get();
    }

    /**
     * 下注派彩
     */
    public function TransferInOut()
    {
        $service = new \app\common\service\game\Pgnew3;
        return $service->TransferInOut();
    }

}
