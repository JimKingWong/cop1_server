<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 记录接口
 */
class Record extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    
    /**
     * 奖励记录
     * @ApiMethod (GET)
     */
    public function rewardLog()
    {
        $service = new \app\common\service\Record();
        $service->rewardLog();
    }

    /**
     * 余额明细
     * @ApiMethod (GET)
     */
    public function moneyLog()
    {
        $service = new \app\common\service\Record();
        $service->moneyLog();
    }

    /**
     * 余额详情
     */
    public function moneyDetail()
    {
        $service = new \app\common\service\Record();
        $service->moneyDetail();
    }
    
    /**
     * 游戏下注记录
     * @ApiMethod (GET)
     * @ApiParams (name="date", type="int", required=true, description="时间0,1,7,15,30")
     * @ApiParams (name="platform", type="string", required=true, description="平台如PG")
     * 
     */
    public function gamebet()
    {
        $service = new \app\common\service\Record();
        $service->gamebet();
    }

    /**
     * 游戏下注记录统计
     * @ApiMethod (GET)
     * @ApiParams (name="date", type="int", required=true, description="时间0,1,7,15,30")
     * @ApiParams (name="platform", type="string", required=true, description="平台如PG")
     */
    public function gamestats()
    {
        $service = new \app\common\service\Record();
        $service->gamestats();
    }

    /**
     * 亏损返水, 下注奖励
     */
    public function detail()
    {
        $service = new \app\common\service\Record();
        $service->detail();
    }

    /**
     * 领取奖励
     */
    public function receive()
    {
        $service = new \app\common\service\Record();
        $service->receive();
    }
}
