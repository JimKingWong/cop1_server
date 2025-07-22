<?php

namespace app\common\model;

use think\Model;

/**
 * 会员信息模型
 */
class UserInfo extends Model
{
   
    protected $name = 'user_info';

    
    
       // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['createtime', 'updatetime', 'id', 'admin_id'];
}
