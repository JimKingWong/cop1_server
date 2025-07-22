<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 平台接口
 */
class Platform extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页数据初始化
     */
    public function init()
    {
        $service = new \app\common\service\Platform;
        $service->init();
    }

    /**
     * 获取分类导航
     * @ApiMethod (GET)
    * @ApiParams (name="is_nav", type="int", required=true, description="是否显示子分类")
     */
    public function nav()
    {
        $service = new \app\common\service\Platform;
        $service->nav();
    }

    /**
     * 客服
     */
    public function support()
    {
        $service = new \app\common\service\Platform;
        $service->support();
    }

    /**
     * 获取语言列表
     */
    public function lang()
    {
        $service = new \app\common\service\Platform;
        $service->lang();
    }

    /**
     * 站内信
     * @ApiMethod (GET)
     * @ApiParams (name="type", type="int", description="0,1,2,3 分别代表系统消息、用户、面板")
     */
    public function letter()
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
