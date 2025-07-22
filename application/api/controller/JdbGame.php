<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 * @ApiInternal
 * 
 */
class JdbGame extends Api
{
    protected $noNeedLogin = ['action', 'getNewGame'];
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
        $service = new \app\common\service\game\Jdb();
        return $service->action();
    }

    /**
     * 获取最新游戏
     */
    public function getNewGame()
    {
        $service = new \app\common\service\game\Jdb();
        return $service->getNewGame();
    }
}
