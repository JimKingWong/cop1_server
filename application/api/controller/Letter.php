<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 站内信接口
 */
class Letter extends Api
{
    protected $noNeedLogin = ['list'];
    protected $noNeedRight = ['*'];


    /**
     * 站内信
     * @ApiMethod (GET)
     * @ApiParams (name="type", type="int", description="0,1,2, 分别代表系统消息、用户、面板")
     */
    public function list()
    {
        $service = new \app\common\service\Platform;
        $service->letter();
    }

    /**
     * 站内信已读
     * @ApiMethod (GET)
     * @ApiParams (name="letter_id", type="int", description="站内信ID")
     */
    public function read()
    {
        $service = new \app\common\service\Platform;
        $service->read();
    }
}
