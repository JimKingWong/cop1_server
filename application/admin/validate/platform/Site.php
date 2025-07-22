<?php

namespace app\admin\validate\platform;

use think\Validate;

class Site extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require|unique:site,name',
        'url' => 'require',
    ];
    /**
     * 提示消息
     */
    protected $message = [
        'name.require' => '站点名称不能为空',
        'name.unique'  => '站点名称已存在',
        'url.require' => '域名不能为空',
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];
    
}
