<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 * @ApiInternal
 * 
 */
class Cp extends Api
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
     * 统一调用
     */
    public function action()
    {
        $service = new \app\common\service\game\Cp();
        return $service->handle();
    }
}
