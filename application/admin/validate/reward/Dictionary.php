<?php

namespace app\admin\validate\reward;

use think\Validate;

class Dictionary extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require|unique:dictionary',
    ];
    /**
     * 提示消息
     */
    protected $message = [
        'name.require' => '标识不能为空',
        'name.unique' => '标识已存在',
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];
    
}
