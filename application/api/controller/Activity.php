<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 活动接口
 */
class Activity extends Api
{
    protected $noNeedLogin = ['siginConfig'];
    protected $noNeedRight = ['*'];

    /**
     * 初始化
     */
    public function siginConfig()
    {
        $service = new \app\common\service\Activity();
        $service->siginConfig();
    }
    
    /**
     * 签到
     */
    public function signin()
    {
        $service = new \app\common\service\Activity();
        $service->signin();
    }

}
