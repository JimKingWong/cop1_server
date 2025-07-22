<?php

namespace app\common\service;

use app\common\model\Activity;
use app\common\model\Channel;
use app\common\model\Recharge as ModelRecharge;
use app\common\model\RechargeConfig;
use app\common\model\RechargeList;
use app\common\model\User;
use app\common\service\Channel as ServiceChannel;
use Exception;
use think\Db;

/**
 * 充值服务
 */
class Recharge extends Base
{

    protected $model = null;

    public function __construct()
    {
        parent::__construct();

        $this->model = new ModelRecharge();
    }

    /**
     * 初始
     * @ApiMethod (GET)
     */
    public function init()
    {
        // 获取充值配置
        $config = RechargeConfig::where('status', 1)
            ->field('id,min_money,max_money,typing_amount,gift_amount')
            ->cache(true)
            ->order('weigh asc')
            ->select();

        $list = RechargeList::where('status', 1)
            ->field('id,money')
            ->order('weigh desc')
            ->cache(true)
            ->select();
        foreach($list as $key => $val){
            $list[$key]['gift_amount'] = 0;
            foreach($config as $v){
                if($val['money'] >= $v['min_money'] && $val['money'] < $v['max_money']){
                    $list[$key]['gift_amount'] = $v['gift_amount']; // 暂时不需要
                }
            }
        }

        // 最小充值
        $min_recharge = config('system.min_recharge');

        // 通道列表
        $channel = Channel::where('status', 1)->field('id,title,name')->order('weigh desc')->select();

        // 是否首充
        $is_first_recharge = $this->auth->is_first_recharge;

        $retval = [
            'min_recharge'          => $min_recharge,
            'is_first_recharge'     => $is_first_recharge,
            'config'                => $list,
            'channel'               => $channel,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 充值记录
     */
    public function record()
    {
        $status = $this->request->get('status');

        if($status != ''){
            $where['status'] = (string)$status;
        }

        // 0 今天 1 昨天 7最近7天 15最近15天 30最近30天
        $date = $this->request->get('date');

        if(!in_array($date, [0, 1, 7, 15, 30])) $date = ''; // 默认全部

        if($date === 0){
            $starttime = date('Y-m-d');
            $where['createtime'] = ['>=', $starttime];
        }

        if($date > 0){
            $starttime = date('Y-m-d', strtotime('-' . $date . ' day'));
            $endtime = date('Y-m-d');
            $where['createtime'] = ['between', [$starttime, $endtime]];
        }

        $user = $this->auth->getUser();

        $fields = "id,order_no,money,real_amount,createtime,paytime,status";

        $where['user_id'] = $user->id;
        $list = $this->model->where($where)->field($fields)->order('id desc')->select();
        foreach($list as $val){
            $val->status_text = $val->status == 0 ? __('未支付') : __('已支付');
        }

        $retval = [
            'list' => $list,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 创建充值订单
     */
    public function create()
    {
        $channel_id = $this->request->post('channel_id', 0);
        $money = $this->request->post('money', 0);

        // 验证通道ID
        $channel = Channel::where('id', $channel_id)->find();

        if(!$channel){
            $this->error(__('通道不存在'));
        }

        // 充值配置
        $config = $channel->recharge_config;
        if(!$config){
            // 充值配置不存在
            $this->error(__('充值配置不存在'));
        }

        // 最小充值
        $min_recharge = config('system.min_recharge');
        if($money < $min_recharge){
            // 充值金额小于最小充值金额
            $this->error(__('充值金额小于最小充值金额'));
        }

        // 生成订单号
        $pre_order_no = isset($config['pre_order_no']) ? $config['pre_order_no'] : '';
        $order_no = $this->model->createOrderNo($pre_order_no);
        
        // 用户信息
        $user = $this->auth->getUser();

        // 充值金额配置信息
        $configList = RechargeConfig::where('status', 1)->order('weigh asc')->select();
        $recharge_config_id = 0;

        $gift_amount = 0;
        foreach($configList as $val){
            if($money >= $val['min_money'] && $money <= $val['max_money']){
                $recharge_config_id = $val['id'];
                $gift_amount = $val['gift_amount'];
                break;
            }
        }

        // 获取站点信息
        $multiple = \app\common\model\Site::where('url', $this->origin)->value('multiple');
        
        // 订单数据
        $orderData = [
            'admin_id'            => $user->admin_id,
            'user_id'             => $user->id,
            'channel_id'          => $channel_id,
            'recharge_config_id'  => $recharge_config_id,
            'order_no'            => $order_no,
            'money'               => $money,
            'gift_amount'         => $gift_amount,
            'typing_amount'       => ($money + $gift_amount) * $multiple,
        ];
        // $this->model->save($orderData);exit;
        

        // 调用对应支付通道函数
        $method = trim(strtolower($channel->name)) . 'Recharge';
        
        $res = ServiceChannel::$method($config, $orderData);

        if(!$res){
            // 调用支付通道失败
            $this->error(__('调用支付渠道失败'));
        }

        // 有成功返回支付链接 再保存订单
        $this->model->save($orderData);

        $retval = [
            'order_no' => $order_no,
            'pay_url'  => $res, // 支付链接
        ];
        
        $this->success(__('请求成功'), $retval);
    }

    /**
     * u2cpay 充值回调接口
     */
    public function u2cpay_recharge()
    {
        $params = $this->request->param();
        \think\Log::record($params,'u2cpay_recharge_param');

        $where['order_no'] = $params['merchantOrderNo'];
        $where['status'] = '0';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 用户cpf补上
        $order->cpf = $params['cpf'] ?? '';

        // 获取配置
        $config = $order->channel->recharge_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        // 首充活动
        $activity = Activity::where('name', 'first_recharge')->where('status', 1)->find();

        if($params['status'] == 'PAID'){
            $amount = $params['amount'] / 100; // 转为元
            
            $this->notify($order, $amount, $activity);
        }
        
        return 'success';
    }

    /**
     * cepay 充值回调接口
     */
    public function cepay_recharge()
    {
        $params = $this->request->param();
        \think\Log::record($params,'cepay_recharge_param');

        $where['order_no'] = $params['orderid'];
        $where['status'] = '0';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 用户cpf补上
        $order->cpf = $params['real_cpf'] ?? '';

        // 获取配置
        $config = $order->channel->recharge_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        // 首充活动
        $activity = Activity::where('name', 'first_recharge')->where('status', 1)->find();

        if($params['returncode'] == 1){
            $amount = $params['amount']; // 转为元
            
            $this->notify($order, $amount, $activity);
        }
        
        return 'OK';
    }

      /**
     * ouropago 充值回调接口
     */
    public function ouropago_recharge()
    {
        $params = $this->request->param();
        

        $params = html_entity_decode($params['data'], ENT_QUOTES, 'UTF-8');
        $params = json_decode($params, true);
        \think\Log::record($params,'ouropago_recharge_param');
        
        $where['order_no'] = $params['orderNo'];
        $where['status'] = '0';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 用户cpf补上
        $order->cpf = $params['real_cpf'] ?? '';

        // 获取配置
        $config = $order->channel->recharge_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        // 首充活动
        $activity = Activity::where('name', 'first_recharge')->where('status', 1)->find();

        if($params['status'] == 'SUCCESS'){
            $amount = $params['price'] / 100; // 转为元
            
            $this->notify($order, $amount, $activity);
        }
        
        return 'success';
    }

    /**
     * 统一处理回调
     */
    public function notify($order, $amount, $activity)
    {
        $result = false;
        Db::startTrans();
        try{
            $user = User::lock(true)->find($order->user_id);

            $total_amount = $amount + $order->gift_amount; // 充值总金额

            // 更新订单
            $order->status               = '1'; // 充值成功
            $order->paytime              = datetime(time());
            $order->real_pay_amount      = $amount; // 实际支付金额
            $order->real_amount          = $total_amount; // 实际到账金额
            $order->bet_amount           = $user->userdata->total_bet;
            $order->activity_id          = $activity->id ?? 0;
            $result = $order->save();
            
            // 更新业务员数据
            if($user->admindata){
                $user->admindata->recharge_amount += $amount; // 累计充值金额
                if($user->admindata->save() === false){
                    $result = false;
                }
            }
            
            // 默认打码量要求
            $typing_amount_limit = $order->typing_amount;

            // 打码量要求
            $typing_diff = $user->userdata->typing_amount_limit - $user->userdata->total_bet;
            // if($typing_diff >= 10){
                    // 原来$user->userdata->total_bet + $typing_diff 本来就等于 $user->userdata->typing_amount_limit, 那么就相当于
                    // $typing_amount_limit = $order->typing_amount; 综合起来就是大于0的等于订单的打码量
            //     $typing_amount_limit = $user->userdata->total_bet + $typing_diff + $order->typing_amount;
            // }

            if($typing_diff < 0){
                // insertlog多加了一次要减掉, 相当于之前的$user->userdata->total_bet + $order->typing_amount 值存入数据
                $typing_amount_limit = $user->userdata->total_bet + $order->typing_amount - $user->userdata->typing_amount_limit;
            }
            
            // 首充活动奖励 需要开关, 利率后台设置 
            if($user->is_first_recharge == 0){
                $user->is_first_recharge = 1; // 设置为已首充
                
                // 上级邀请充值人数+1
                if($user->parent){
                    $user->parent->userdata->invite_recharge_num += 1;
                    if($user->parent->userdata->save() === false){
                        $result = false;
                    }
                }
                
                // 首充活动
                if($activity && $activity->status == 1){
                    if(isset($activity->config) && $activity->config){

                        $activity_money = $activity->config['number']; // 首充奖励金额
                        if($activity->config['type'] == 0){
                            // 百分比
                            $activity_money = $amount * $activity->config['number'] / 100; // 首充奖励金额
                        }
                        // 首充数据
                        $reward_data = [
                            'first_recharge' => [
                                'money'                 => $activity_money, // 首充奖励金额
                                'typing_amount_limit'   => 0,
                                'transaction_id'        => $order->order_no,
                                'status'                => 1, // 直发
                            ],
                        ];
                    }
                }
                
                // 首次存款金额
                $user->userdata->first_recharge_money = $amount;
            }

            // 充值数据
            $reward_data['recharge'] = [
                'money'                 => $amount, // 充值金额
                'typing_amount_limit'   => $typing_amount_limit, // 打码量要求
                'transaction_id'        => $order->order_no,
                'status'                => 1,
            ];

            if($order->gift_amount > 0){
                // 赠送数据
                $reward_data['recharge_gift'] = [
                    'money'                 => $order->gift_amount, // 充值金额
                    'typing_amount_limit'   => 0, // 打码量要求
                    'transaction_id'        => $order->order_no,
                    'status'                => 1,
                ];
            }
            
            // 累计充值金额
            $user->userdata->total_recharge += $amount; 
            $user->userdata->first_recharge_money = $amount; // 首次存款金额


            // 充值佣金
            $this->commission($user, $order, $amount);
            
            // 写入日志并更新用户数据
            if(User::insertLog($user, $reward_data) === false){
                $result = false;
            }
            
            if($result){
                Db::commit();
            }

        }catch(Exception $e){
            \think\Log::record($e->getMessage(),'u2cppay_recharge_ERROR');
            // echo $e->getMessage();
            Db::rollback();
        }
    }

    /**
     * 上级博主分佣
     */
    public function commission($user, $order, $amount)
    {
        $parent_id_str = $user->parent_id_str;
        // dd($parent_id_str);
        
        // 不存在
        if(!$parent_id_str){
            return;
        }

        // 所有上级博主
        $where['id'] = ['in', $parent_id_str]; 
        $where['role'] = 1;
        $supUsers = User::where($where)->field('id,admin_id,parent_id_str,money')->select();

        if($supUsers->isEmpty()){
            return;
        }
        // dd($supUsers->toarray());
        $parent_id_arr = explode(',', $parent_id_str);
        // 反转, 看当前用户在第几级
        $flip_parent_id_arr = array_flip($parent_id_arr);
        // dd($flip_parent_id_arr);

        foreach($supUsers as $val){
            
            if(isset($flip_parent_id_arr[$val->id])){
                // 确定当前用户属于上级的第几级
                $level = count($parent_id_arr) - $flip_parent_id_arr[$val->id] - 1;
                // dd($level);
                // 是否开启分佣
                if($val->usersetting->commission_status && $val->usersetting->commission_rate != ''){
                    
                    // 取得对应博主能抽到的佣金比例
                    $commission_rate = explode(',', $val->usersetting->commission_rate)[$level];
                    
                    // 给上级插入充值分佣数据
                    if($commission_rate > 0){
                        
                        $reward_data['recharge_commission'] = [
                            'money'                 => $commission_rate / 100 * $amount, // 奖励佣金
                            'typing_amount_limit'   => 0,
                            'transaction_id'        => $order->order_no,
                            'status'                => 1,
                        ];
                        // dd($reward_data);
                        User::insertLog($val, $reward_data);
                    }
                }
            }
        }
        
    }
}