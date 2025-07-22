<?php

namespace app\api\controller;

use app\common\controller\Api;


/**
 * 转盘接口
 */
class Turntable extends Api
{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['init', 'info'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    /**
     * 初始化转盘
     * @ApiMethod (GET)
     * 
     */
    public function init()
    {
        $service = new \app\common\service\Turntable;
        $service->init();
    }

    /**
     * 获取转盘信息
     * @ApiMethod (GET)
     * 
     */
    public function record()
    {
        $service = new \app\common\service\Turntable;
        $service->record();
    }

    /**
     * 转盘抽奖
     * @ApiMethod (POST)
     * 
     */
    public function turn()
    {
        $service = new \app\common\service\Turntable;
        $service->turn();
    }
}
