<?php

namespace app\common\service;

use app\common\controller\Api;

class Base extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 统一成功响应 omg游戏的
     */
    protected function successResponse($balance)
    {
        return json([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'balance' => number_format($balance, 2, '.', '')
            ]
        ]);
    }
}