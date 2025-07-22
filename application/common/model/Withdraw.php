<?php

namespace app\common\model;

use think\Model;

/**
 * 提现模型
 */
class Withdraw extends Model
{
    protected $resultSetType = 'collection';
    
    // 表名
    protected $name = 'withdraw';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function wallet()
    {
        return $this->belongsTo('Wallet', 'wallet_id', 'id')
            ->field('id,name,area_code,phone_number,chave_pix,pix,cpf,is_default');
    }

    public function channel()
    {
        return $this->belongsTo('Channel', 'channel_id', 'id')->field('id,title,name,recharge_config,withdraw_config');
    }
}
