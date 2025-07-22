<?php

namespace app\admin\model;

use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use app\common\model\UserData;
use app\common\model\UserInfo;
use app\common\model\UserSetting;
use think\Model;

class User extends Model
{
    protected $resultSetType = 'collection';

    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'prevtime_text',
        'logintime_text',
        'jointime_text'
    ];

    public function getOriginData()
    {
        return $this->origin;
    }

    protected static function init()
    {
        self::beforeUpdate(function ($row) {
            $changed = $row->getChangedData();
            //如果有修改密码
            if (isset($changed['password'])) {
                if ($changed['password']) {
                    $salt = \fast\Random::alnum();
                    $row->password = \app\common\library\Auth::instance()->getEncryptPassword($changed['password'], $salt);
                    $row->salt = $salt;
                } else {
                    unset($row->password);
                }
            }
        });


        // self::beforeUpdate(function ($row) {
        //     $changedata = $row->getChangedData();
        //     $origin = $row->getOriginData();
        //     if (isset($changedata['money']) && (function_exists('bccomp') ? bccomp($changedata['money'], $origin['money'], 2) !== 0 : (double)$changedata['money'] !== (double)$origin['money'])) {
        //         MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money'] - $origin['money'], 'before' => $origin['money'], 'after' => $changedata['money'], 'memo' => '管理员变更金额']);
        //     }
        //     if (isset($changedata['score']) && (int)$changedata['score'] !== (int)$origin['score']) {
        //         ScoreLog::create(['user_id' => $row['id'], 'score' => $changedata['score'] - $origin['score'], 'before' => $origin['score'], 'after' => $changedata['score'], 'memo' => '管理员变更积分']);
        //     }
        // });

        self::afterInsert(function ($row) {
            UserData::create(['user_id' => $row->id, 'admin_id' => $row->admin_id]);
            UserSetting::create(['user_id' => $row->id, 'admin_id' => $row->admin_id]);
            UserInfo::create(['user_id' => $row->id, 'admin_id' => $row->admin_id, 'email' => $row->email, 'mobile' => $row->mobile]);
        });
    }

    public function getGenderList()
    {
        return ['1' => __('Male'), '0' => __('Female')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }


    public function getPrevtimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['prevtime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['logintime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getJointimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['jointime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPrevtimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setLogintimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setJointimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setBirthdayAttr($value)
    {
        return $value ? $value : null;
    }

    public function group()
    {
        return $this->belongsTo('UserGroup', 'group_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function userdata()
    {
        return $this->hasOne('\app\common\model\UserData', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function admin()
    {
        return $this->hasOne('\app\admin\model\department\AuthAdmin', 'id', 'admin_id');
    }

    public function admindata()
    {
        return $this->hasOne('AdminData', 'admin_id', 'admin_id');
    }
    
    public function parent()
    {
        return $this->belongsTo('User', 'parent_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function usersetting()
    {
        return $this->hasOne('\app\common\model\UserSetting', 'user_id', 'id');
    }
}
