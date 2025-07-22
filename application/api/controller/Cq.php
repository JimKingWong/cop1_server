<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 游戏接口
 * @ApiInternal
 * 
 */
class Cq extends Api
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
     * 检查玩家帐号
     * /player/check/:account
     */
    public function check()
    {
        $service = new \app\common\service\game\Cq();
        return $service->check();
    }

    /**
     * 取得玩家錢包餘額
     * /transaction/balance/:account
     */
    public function balance()
    {
        $service = new \app\common\service\game\Cq();
        return $service->balance();
    }

    /**
     * 投注
     * /transaction/game/bet
     */
    public function bet()
    {
        $service = new \app\common\service\game\Cq();
        return $service->bet();
    }

    /**
     * 派彩
     * /transaction/game/bet
     */
    public function endround()
    {
        $service = new \app\common\service\game\Cq();
        return $service->endround();
    }

    /**
     * 转出(部分)
     * /transaction/game/rollout
     */
    public function rollout()
    {
        $service = new \app\common\service\game\Cq();
        return $service->rollout();
    }

    /**
     * 转出(全部)
     * /transaction/game/takeall
     */
    public function takeall()
    {
        $service = new \app\common\service\game\Cq();
        return $service->takeall();
    }

    /**
     * 转回(全部)
     * /transaction/game/rollin
     */
    public function rollin()
    {
        $service = new \app\common\service\game\Cq();
        return $service->rollin();
    }

    /**
     * 针对已完成订单做扣款 例如，游戏逻辑错误进行修正
     * /transaction/game/debit
     */
    public function debit()
    {
        $service = new \app\common\service\game\Cq();
        return $service->debit();
    }

    /**
     * 针对'已完成'的订单做补款。 例如，游戏逻辑错误进行修正
     * /transaction/game/credit
     */
    public function credit()
    {
        $service = new \app\common\service\game\Cq();
        return $service->credit();
    }

    /**
     * 活动奖励 如有参与台方举办之活动，活动奖励通过此支API派发给玩家
     */
    public function payoff()
    {
        $service = new \app\common\service\game\Cq();
        return $service->payoff();
    }

    /**
     * 退款下注行为（bet/rollout/takeall） 的金额
     * /transaction/game/refund
     */
    public function refund()
    {
        $service = new \app\common\service\game\Cq();
        return $service->refund();
    }

    /**
     * 玩家退出
     * /gameboy/player/logout
     */
    public function logout()
    {
        $service = new \app\common\service\game\Cq();
        return $service->logout();
    }

    /**
     * 统一调用
     */
    public function getNewGame()
    {
        $service = new \app\common\service\game\Cq();
        $service->getNewGame();
    }
}
