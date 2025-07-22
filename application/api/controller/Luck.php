<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * omg游戏接口
 * @ApiInternal
 * 
 */
class Luck extends Api
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
     * 验证用户
     */
    public function user()
    {
        $service = new \app\common\service\game\Omg();
        return $service->user();
    }

    /**
     * 验证用户
     */
    public function verify_session()
    {
        $service = new \app\common\service\game\Omg();
        return $service->verify_session();
    }

     /**
     * 获取钱包
     */
    public function Get()
    {
        $service = new \app\common\service\game\Omg();
        return $service->Get();
    }

    /**
     * 处理不同类型业务, 统一调用
     */
    public function balance()
    {
        $service = new \app\common\service\game\Omg();
        return $service->balance();
    }

    /**
     * 处理不同类型业务, 统一调用
     */
    public function changeBalance()
    {
        $service = new \app\common\service\game\Omg();
        return $service->changeBalance();
    }

    /**
     * 手动更新omg游戏表
     */
    public function getGameList()
    {
        $service = new \app\common\service\game\Omg();
        return $service->getGameList();
    }
}
