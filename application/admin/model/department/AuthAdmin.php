<?php

namespace app\admin\model\department;


class AuthAdmin extends \app\admin\model\Admin
{
    // 表名
    protected $name = 'admin';

    public static function getRoleList()
    {
        return ['管理员', '客服', '主管', '业务员'];
    }

    /**
     * 关联部门中间表
     * @return \think\model\relation\HasMany
     */
    public function dadmin()
    {
        return $this->hasMany('\app\admin\model\department\Admin', 'admin_id', 'id');
    }

    /**
     * 关联部门表
     * @return \think\model\relation\BelongsToMany
     */
    public function departments()
    {
        return $this->belongsToMany('\app\admin\model\department\Department','DepartmentAdmin','department_id','admin_id');
    }

    /**
     * 关联角色组
     * @return \think\model\relation\HasMany
     */
    public function groups()
    {
        return $this->hasMany('\app\admin\model\department\AuthGroupAccess', 'uid', 'id');
    }

    /**
     * 关联用户数据
     */
    public function admindata()
    {
        return $this->hasOne('\app\admin\model\AdminData', 'admin_id', 'id');
    }
}