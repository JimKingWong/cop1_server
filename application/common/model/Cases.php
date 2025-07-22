<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;

class Cases extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'game_case';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';


    /**
     * 获取当前游戏方案
     */
    public static function getCases($origin)
    {
        // 获取当前站点的游戏列表方案
        $case_id = Site::where('url', $origin)->value('case_id');

        // 获取方案
        $cases = Self::where('id', $case_id)->where('status', 1)->find();
        if(!$cases){
            // 如果为空取默认方案
            $map['is_default'] = 1;
            $cases = Self::where($map)->find();
        }
        return $cases;
    }

}
