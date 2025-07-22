<?php

namespace app\common\service;

use app\common\model\Box as ModelBox;
use app\common\model\User;
use think\Db;

/**
 * 宝箱
 */
class Box extends Base
{
    protected $model = null;

    public function __construct()
    {
        parent::__construct();

        $this->model = new ModelBox();
    }

    /**
     * 领取宝箱
     */
    public function receive()
    {
        $box_num = $this->request->post('num');

        $where['num'] = $box_num;
        $where['status'] = 1;
        $box = $this->model->where($where)->find();
        if(!$box){
            // 宝箱不存在
            $this->error(__('宝箱不存在'));
        }

        // 用户信息
        $user= $this->auth->getUser();

        // 有效用户数
        $valid_user_num = $user::validUser($user->id);
        // $valid_user_num = 2;
        if($valid_user_num < $box->condition){
            // 未达到领取条件
            $this->error(__('未达到领取条件'));
        }

        // 领取记录
        $record = $this->model::record($user->id);
        if(isset($record[$box_num])){
            // 已领取
            $this->error(__('宝箱已领取'));
        }

        // 黑名单
        if(isset($user->usersetting->is_black) && $user->usersetting->is_black == 1){
            $this->error(__('您被禁止领取宝箱'));
        }
        
        $result = false;
        Db::startTrans();
        try{
            // 插入领取记录日志
            $data = [
                'user_id'       => $user->id,
                'num_id'        => $box->num,
                'money'         => $box->money,
                'condition'     => $box->condition,
                'good_num'      => $valid_user_num,
                'createtime'    => datetime(time()),
            ];
            $result = $this->model->insertRecord($data);
            
            $box_typing_multiple = config('system.box_typing_multiple');

            if($user->role == 1){
                $box_typing_multiple = 0;
            }

            // 数据准备
            $reward_data = [
                'box_bonus' => [
                    'money'                 => $box->money,
                    'typing_amount_limit'   => $box->money * $box_typing_multiple, // 计算打赏金额限制
                    'transaction_id'        => $result->id, // 记录表id
                    'status'                => 1,
                ],
            ];
            
            // 插入余额变动日志, 以及奖励日志
            if(!$user::insertLog($user, $reward_data)){
                $result = false;
            }

            // 都成功才commit
            if($result){
                Db::commit();
            }
        }catch(\Exception $e){
            // echo $e->getMessage();
            Db::rollback();
        }
        
        if(!$result){
            // 领取失败
            $this->error(__('领取失败, 请重试! '));
        }
        $this->success(__('领取成功'));
    }

    /**
     * 宝箱列表
     */
    public function boxList()
    {
        $list = $this->model->where('status', 1)->order('num asc')->field('num,money,condition')->select();

        // 获取用户领取记录
        $valid_user_num = 0;
        if(isset($this->auth->id)){
            $record = $this->model::record($this->auth->id);

            $users = User::directUser($this->auth->id);
            foreach($users as $user){
                if($user->is_valid == 1){
                    $valid_user_num++;
                }
            }
        }

        
        foreach($list as $val){
            // 是否领取, 1:未领取 0:0未达到条件, 2:已领取
          
            $val->is_get = 0;
            if(isset($record[$val->num])){
                $val->is_get = 2;
            }else{
                if($val['condition'] <= $valid_user_num){
                    $val->is_get = 1;
                }
            }
        }

        $retval = [
            'list'  => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }
}