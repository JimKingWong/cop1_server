<?php

namespace app\api\controller\blogger;

use app\common\controller\Api;

/**
 * 博主后台
 * @ApiSector (博主后台)
 * 
 */
class User extends Api
{
    protected $noNeedLogin = ['login'];
    protected $noNeedRight = ['*'];

    /**
     * 博主登录
     */
    public function login()
    {
        $service = new \app\common\service\blogger\User();
        $service->login();
    }
    
    /**
     * 工作台
     * @ApiMethod (GET)
     * @ApiParams (name="limit",description="每页显示数量")
     * @ApiParams (name="page",description="当前页数")
     * @ApiParams (name="keyword",description="站点,路径")
     */
    public function site()
    {
        $service = new \app\common\service\blogger\User();
        $service->site();
    }

    /**
     * 用户列表
     * @ApiMethod (GET)
     * @ApiParams (name="limit",description="每页显示数量")
     * @ApiParams (name="page",description="当前页数")
     * @ApiParams (name="user_id",description="用户id")
     * @ApiParams (name="parent_id",description="父级id")
     * @ApiParams (name="username",description="用户名")
     * @ApiParams (name="origin",description="站点")
     * @ApiParams (name="invite_code",description="邀请码")
     * @ApiParams (name="be_invite_code",description="上级邀请码")
     */
    public function list()
    {
        $service = new \app\common\service\blogger\User();
        $service->list();
    }

    /**
     * 下级数据
     * @ApiMethod (GET)
     * @ApiParams (name="id", type="int", description="用户id")
     */
    public function subData()
    {
        $service = new \app\common\service\blogger\User();
        $service->subData();
    }
}
