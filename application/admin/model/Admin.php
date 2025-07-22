<?php

namespace app\admin\model;

use think\Model;

class Admin extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $hidden = [
        'password',
        'salt'
    ];

    public static function init()
    {
        self::beforeWrite(function ($row) {
            $changed = $row->getChangedData();
            //如果修改了用户或或密码则需要重新登录
            if (isset($changed['username']) || isset($changed['password']) || isset($changed['salt'])) {
                $row->token = '';
            }
        });

        self::afterInsert(function($row){
            if(isset($row['role']) && $row['role'] > 2){
                $data['invite_code'] = createInviteCode(config('system.agent_code_length'));
            }
            // 业务员才要邀请码
            $data['admin_id'] = $row['id'];
            AdminData::create($data);
        });

    }

    /**
     * 关联用户数据
     */
    public function admindata()
    {
        return $this->hasOne('AdminData', 'admin_id', 'id');
    }
}
