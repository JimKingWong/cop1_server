<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 */
class Game extends Api
{
    protected $noNeedLogin = ['list', 'filter', 'collectList'];
    protected $noNeedRight = ['*'];

    /**
     * 游戏列表
     */
    public function list()
    {
        $service = new \app\common\service\Game();
        $service->list();
    }

    /**
     * 分类游戏列表
     */
    public function filter()
    {
        $service = new \app\common\service\Game();
        $service->filter();
    }

    /**
     * 收藏和添加最近游戏
     */
    public function collect()
    {
        $service = new \app\common\service\Game();
        $service->collect();
    }

    /**
     * 收藏记录
     */
    public function collectList()
    {
        $service = new \app\common\service\Game();
        $service->collectList();
    }

    /**
     * 启动游戏
     */
    public function startup()
    {
        $service = new \app\common\service\Game();

        if($this->auth->is_test == 1){
            // 测试号调用测试启动游戏
            $service->testStartup();
        }
        $service->startup();
    }

    /**
     * omg测试启动游戏
     */
    public function testStartup()
    {
        $service = new \app\common\service\Game();
        $service->testStartup();
    }
}
