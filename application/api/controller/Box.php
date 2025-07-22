<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 宝箱接口
 */
class Box extends Api
{
    protected $noNeedLogin = ['boxList'];
    protected $noNeedRight = ['*'];


    /**
     * 宝箱列表
     */
    public function boxList()
    {
        $service = new \app\common\service\Box;
        $service->boxList();
    }

    /**
     * 领取宝箱
     */
    public function receive()
    {
        $service = new \app\common\service\Box;
        $service->receive();
    }
    
}
