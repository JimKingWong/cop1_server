<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 * @ApiInternal
 * 
 */
class Pp extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 获取链接
     */
    public function link()
    {
        $service = new \app\common\service\Game();
        $service->startup();
    }

    /**
     * 认证
     */
    public function Authenticate()
    {
        $service = new \app\common\service\game\Pp();
        return $service->Authenticate();
    }

    /**
     * 获取玩家余额
     */
    public function Balance()
    {
        $service = new \app\common\service\game\Pp();
        return $service->Balance();
    }

    /**
     * 下注
     */
    public function bet()
    {
        $service = new \app\common\service\game\Pp();
        return $service->bet();
    }

    /**
     * 结局
     */
    public function BetResult()
    {
        $service = new \app\common\service\game\Pp();
        return $service->BetResult();
    }

    /**
     * 免费旋转奖励 no
     */
    public function BonusWin()
    {
        $service = new \app\common\service\game\Pp();
        return $service->BonusWin();
    }

    /**
     * 返还
     */
    public function Refund()
    {
        $service = new \app\common\service\game\Pp();
        return $service->Refund();
    }

    /**
     * 累计奖池中奖
     */
    public function JackpotWin()
    {
        $service = new \app\common\service\game\Pp();
        return $service->JackpotWin();
    }

    /**
     * 竞标赛中奖
     */
    public function PromoWin()
    {
        $service = new \app\common\service\game\Pp();
        return $service->PromoWin();
    }

    /**
     * 竞标赛中奖
     */
    public function Adjustment()
    {
        $service = new \app\common\service\game\Pp();
        return $service->Adjustment();
    }

    /**
     * 竞标赛中奖
     */
    public function Endround()
    {
        $service = new \app\common\service\game\Pp();
        return $service->Endround();
    }
}
