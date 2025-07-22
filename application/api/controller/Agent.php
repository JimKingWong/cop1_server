<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 代理接口
 */
class Agent extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    /**
     * 代理人信息初始化
     */
    public function init()
    {
        $service = new \app\common\service\Agent();
        $service->init();
    }

    /**
     * 我的团队
     */
    public function team()
    {
        $service = new \app\common\service\Agent();
        $service->team();
    }

    /**
     * 我的数据
     * @ApiMethod (GET)
     * @ApiParams (name="date", type="string", required=true, description="昨天 yestoday, 今天 today, 本周 week, 上周 last_week, 本月 month, 上个月 last month")
     */
    public function mydata()
    {
        $service = new \app\common\service\Agent();
        $service->mydata();
    }

    /**
     * 所有数据
     * @ApiMethod (GET)
     * @ApiParams (name="start_time", type="string", required=true, description="开始时间")
     * @ApiParams (name="end_time", type="string", required=true, description="结束时间")
     */
    public function alldata()
    {
        $service = new \app\common\service\Agent();
        $service->alldata();
    }

    /**
     * 二级数据
     * @ApiMethod (GET)
     * @ApiParams (name="start_time", type="string", required=true, description="开始时间")
     * @ApiParams (name="end_time", type="string", required=true, description="结束时间")
     * @ApiParams (name="id", type="int", required=true, description="用户id")
     */
    public function secData()
    {
        $service = new \app\common\service\Agent();
        $service->secData();
    }

    /**
     * 业绩
     * @ApiMethod (GET)
     * @ApiParams (name="start_time", type="string", required=true, description="开始时间")
     * @ApiParams (name="end_time", type="string", required=true, description="结束时间")
     * @ApiParams (name="id", type="int", required=true, description="用户id")
     */
    public function performance()
    {
        $service = new \app\common\service\Agent();
        $service->performance();
    }

    /**
     * 下属资料
     * @ApiMethod (GET)
     * @ApiParams (name="start_time", type="string", required=true, description="开始时间")
     * @ApiParams (name="end_time", type="string", required=true, description="结束时间")
     * @ApiParams (name="id", type="int", required=true, description="用户id")
     * @ApiParams (name="user_id", type="int", required=true, description="搜索用户id")
     */
    public function subActive()
    {
        $service = new \app\common\service\Agent();
        $service->subActive();
    }

    /**
     * 下级下注报告
     * @ApiMethod (GET)
     * @ApiParams (name="start_time", type="string", required=true, description="开始时间")
     * @ApiParams (name="end_time", type="string", required=true, description="结束时间")
     * @ApiParams (name="id", type="int", required=true, description="用户id")
     */
    public function subBetReport()
    {
        $service = new \app\common\service\Agent();
        $service->subBetReport();
    }
}
