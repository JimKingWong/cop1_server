<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// [ 应用入口文件 ]
// 跨域
header('Access-Control-Allow-Origin: *'); //星号表示允许所有来源跨域请求，生产环境建议配置成你的来源站点的域名
header("Access-Control-Allow-Headers: *");
header('Access-Control-Allow-Methods:*');
header('Access-Control-Expose-Headers:*');
header('Access-Control-Allow-Credentials:true');

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

// 判断是否安装
if (!is_file(APP_PATH . 'admin/command/Install/install.lock')) {
    header("location:./install.php");
    exit;
}

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
