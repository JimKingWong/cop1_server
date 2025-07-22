<?php

namespace app\common\service;

use app\common\model\Recharge;
use app\common\model\SigninLog;
use app\common\model\User;
use fast\Date;
use think\Db;

/**
 * 活动
 */
class Activity extends Base
{
    protected $model = null;

    public function __construct()
    {
        parent::__construct();

        $this->model = new \app\common\model\Activity();
    }

    public function config($name)
    {
        $row = $this->model::config($name);
        if(empty($row)){
            $this->error(__('活动不存在'));
        }

        if(empty($row['config'])){
            $this->error(__('活动不存在'));
        }

        if($row['status'] != 1){
            $this->error(__('活动已关闭'));
        }

        return $row;
    }

    /**
     * 签到检查
     */
    public function checkSignin($row)
    {
        $where = [];
        if(isset($this->auth->id)){
            $where['user_id'] = $this->auth->id;
        }
        // 今日充值
        $recharge_money = Recharge::whereTime('paytime', 'today')->where($where)->sum('money');

        // 查询最后一次签到记录
        $last_signin = SigninLog::where($where)->order('createtime desc')->find();
        
        // 当前连续签到天数
        $days = $last_signin && strtotime($last_signin['createtime']) > Date::unixtime('day', -1) ? $last_signin['days'] : 0;
        // $days = 2;

        // 可签状态
        $is_signin = 0;
        // 今日充值大于等于配置最小充值金额，且上次签到时间小于今天凌晨时间，则可签到
        if($recharge_money >= $row['config']['min_recharge_money']){
            if($last_signin && strtotime($last_signin['createtime']) < strtotime(date('Ymd'))){

                $is_signin = 1;
            }elseif(!$last_signin){
                // 属于没签到过的
                $is_signin = 1;
            }
        }

        $list = [];
        $k = 0;
        foreach($row['config']['signin'] as $key => $val){
            $day = str_replace('s', '', $key);
            $list[$k]['title'] = $day == 'n' ? __('更多') :__('天数') . ' ' . $day;
            $list[$k]['money'] = sprintf('%.2f', $val);
            $list[$k]['is_signin'] = 0;
            if($k < $days){
                $list[$k]['is_signin'] = 1;
            }
            $k ++;
        }

        $retval = [
            'list'              => $list, // 签到列表
            'is_signin'         => $is_signin, // 今日是否可签
            'recharge_money'    => $recharge_money, // 今日充值
            'min_recharge_money' => $row['config']['min_recharge_money'], // 最小充值金额
            'days'              => $days, // 连续签到天数
            'config'            => $row['config']['signin'], // 配置
            'status'            => $row['status'], // 活动状态
        ];

        return $retval;
    }

    /**
     * 获取活动签到配置信息
     */
    public function siginConfig()
    {
        $name = $this->request->get('name');

        // 签到配置
        $row = $this->config($name);

        // 签到检查
        $retval = $this->checkSignin($row);
        unset($retval['config']);
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 签到
     */
    public function signin()
    {
        $user = $this->auth->getUser();

        // 签到配置
        $row = $this->config('signin_bonus');

        // 签到检查
        $retval = $this->checkSignin($row);
        // dd($retval);

        $config = $retval['config'];
        unset($retval['config']);

        $result = false;
        Db::startTrans();
        try{
            $signin = SigninLog::lock(true)->where('user_id', $user->id)->whereTime('createtime', 'today')->find();
            
            if($signin){
                Db::rollback();
                throw new \Exception(__('今日已签到'));
            }

            if($retval['is_signin'] != 1){
                throw new \Exception(__('未达到签到条件'));
            }

            // 连续签到天数
            $days = $retval['days'] + 1;

            // 奖励金额
            $moeny = $config['s' . $days] ?? $config['sn'];

            // 添加签到记录
            $result = SigninLog::create([
                'admin_id'          => $user->admin_id,
                'user_id'           => $user->id,
                'days'              => $days,
                'money'             => $moeny,
            ]);

             // 数据准备
            $reward_data = [
                'signin_bonus' => [
                    'money'                 => $moeny,
                    'typing_amount_limit'   => $moeny, 
                    'transaction_id'        => $days, // 连签天数
                    'status'                => 1,
                ],
            ];

            if(!User::insertLog($user, $reward_data)){
                $result = false;
            }

            // 都成功才commit
            if($result){
                Db::commit();
            }
        }catch(\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        
        if(!$result){
            $this->error(__('签到失败'));
        }

        $retval['is_signin'] = 0; // 改为不可签到
        $retval['days'] = $days;
        if($days < 8){
            // 不然会多出一条数据
            $retval['list'][$days - 1]['is_signin'] = 1;
        }
        $this->success(__('签到成功'), $retval);
    }


}