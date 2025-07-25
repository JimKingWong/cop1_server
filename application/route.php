<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    'player/check/:account'             => 'api/Cq/check',
    'transaction/balance/:account'      => 'api/Cq/balance',
    'transaction/game/bet'              => 'api/Cq/bet',
    'transaction/game/endround'         => 'api/Cq/endround',
    'transaction/game/rollout'          => 'api/Cq/rollout',
    'transaction/game/takeall'          => 'api/Cq/takeall',
    'transaction/game/rollin'           => 'api/Cq/rollin',
    'transaction/game/debit'            => 'api/Cq/debit',
    'transaction/game/credit'           => 'api/Cq/credit',
    'transaction/game/payoff'           => 'api/Cq/payoff',
    'transaction/game/refund'           => 'api/Cq/refund',
    '/gameboy/player/logout'            => 'api/Cq/logout',
    
    //别名配置,别名只能是映射到控制器且访问时必须加上请求的方法
    '__alias__'   => [
    ],
    //变量规则
    '__pattern__' => [
    ],
//        域名绑定到模块
//        '__domain__'  => [
//            'admin' => 'admin',
//            'api'   => 'api',
//        ],
];
