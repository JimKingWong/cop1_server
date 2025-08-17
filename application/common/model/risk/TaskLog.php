<?php

namespace app\common\model\risk;

use think\Model;

/**
 * 风险检测任务日志
 */
class TaskLog extends Model
{

    protected $name = 'risk_task_log';

    protected $resultSetType = 'collection';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = "Y-m-d H:i:s";

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';


}
