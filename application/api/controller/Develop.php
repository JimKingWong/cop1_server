<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 收发平台服务接口
 * @ApiInternal
 * 
 */
class Develop extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 获取开发平台配置
     *
     */
    public function developConfig()
    {
        $service = new \app\common\service\Develop;
        $service->developConfig();
    }

}
