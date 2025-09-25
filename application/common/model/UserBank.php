<?php

namespace app\common\model;

use think\Model;

/**
 * 会员银行模型
 */
class UserBank extends Model
{

    // 表名
    protected $name = 'user_bank';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    

    public static function certType($flag = 1)
    {
        if ($flag == 1) {
            $cert = [
                __('身份证（CC/TI）'), __('外国人身份证（CE）'), __('税号'), __('护照')
                // __('身份证（CC/TI）'), __('外国人身份证（CE）'), __('税号'), __('护照'), __('离境证'), __('军官证'), __('其他'), __('公民身份证明'), __('居留许可证')
            ];
            return $cert;
        } else {
            $cert = [
                [
                    'type' => '00',
                    'name' => __('身份证（CC/TI）'),
                ],
                [
                    'type' => '01',
                    'name' => __('外国人身份证（CE）'),
                ],
                [
                    'type' => '02',
                    'name' => __('税号'),
                ],
                [
                    'type' => '03',
                    'name' => __('护照'),
                ],
            ];
        }
        return $cert;
    }

    /**
     * 账户类型
     */
    public static function accountType($flag = 1)
    {
        if($flag == 1){
            $account = [
                0 => __('活期账户'),
                1 => __('储蓄账户'),
            ];
        }else{
            $account = [
                [
                    'type' => 0,
                    'name' => __('活期账户'),
                ],
                [
                    'type' => 1,
                    'name' => __('储蓄账户'),
                ],
            ];
        }

        return $account;
    }
}
