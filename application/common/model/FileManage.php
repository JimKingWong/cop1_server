<?php

namespace app\common\model;

use think\Model;

/**
 * 文件管理类
 */
class FileManage extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = "Y-m-d H:i:s";

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
    ];


    /**
     * 获取上传目录
     */
    public static function getDir($name, $dir)
    {
        $where['name'] = $name;
        $where['status'] = 1;
        $route = self::where($where)->column("concat(dir,'/',format) format", 'dir');
        return $route[$dir] ?? 'default/{filemd5}{.suffix}';
    }
}
