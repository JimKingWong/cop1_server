<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 * @ApiInternal
 * 
 */
class Jili extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 获取链接
     */
    public function link()
    {
        $service = new \app\common\service\Game();
        $service->startup();
    }

    /**
     * 获取用户信息
     */
    public function auth()
    {
        $service = new \app\common\service\game\Tada();
        $service->auth();
    }

    /**
     * 下注
     */
    public function action()
    {
        $service = new \app\common\service\game\Tada();
        return $service->bet();
    }

    /**
     * 下注
     */
    public function sessionBet()
    {
        $service = new \app\common\service\game\Tada();
        return $service->sessionBet();
    }
}
