<?php

namespace app\common\service;

use app\common\model\User;
use app\common\model\Wallet;
use app\common\model\Withdraw as ModelWithdraw;
use think\Db;

/**
 * 提现服务
 */
class Withdraw extends Base
{
    protected $model = null;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ModelWithdraw();
    }

    /**
     * 初始化
     */
    public function init()
    {
        $user = $this->auth->getUser();

        // $wallet = Wallet::where('user_id', $user->id)
        //     ->order('is_default desc,id desc')
        //     ->field('id,name,area_code,phone_number,chave_pix,pix,cpf,is_default')
        //     ->select();

        $bank = db('user_bank')->where('user_id', $user->id)->field('id,name,area_code,phone_number,email,bank_name,bank_code,bank_account,identityType,identityNo')->select();

        $need_bet = max($user->userdata->typing_amount_limit - $user->userdata->total_bet, 0);
        $retval = [
            'is_set'        => $user->pay_password ? 1 : 0, // 密码检查
            'min_withdraw'  => config('system.min_withdraw'), // 最小提现金额
            'withdraw_rate' => config('system.withdraw_rate'), // 提现手续费
            'money'         => $user->money, // 账户余额
            'need_bet'      => sprintf('%.2f', $need_bet), // 打码量
            'bank'          => $bank, // 钱包列表
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 提现申请
     */
    public function apply()
    {
        $pay_password = $this->request->post('pay_password', '');
        $user_bank_id = $this->request->post('user_bank_id', 0);
        $money = $this->request->post('money', 0);

        $user = $this->auth->getUser();
        
        // 检查密码
        if(!$pay_password){
            $this->error(__('请输入提现密码'));
        }
        if($pay_password != $user->pay_password){
            $this->error(__('提现密码错误'));
        }        

        // 检查有没进行中的提现单
        $where['user_id'] = $user->id;
        $where['status'] = ['in', [0, 4]];
        $check = $this->model->where($where)->find();
        
        if($check){
            $this->error(__('您有未处理的提现申请，请耐心等待'));
        }

        // 打码量符合要求 总流水 0 充值100 不参与活动 提现所需打码金额 100  验证 总流水是不是大于提现所需打码金额
        if($user->userdata->typing_amount_limit > $user->userdata->total_bet){
            // Complete sua aposta para continuar
            $this->error(__('完成投注即可继续'));
        }

        // 钱包信息
        $user_bank = \app\common\model\UserBank::where('user_id', $user->id)->where('id', $user_bank_id)->find();
        if(!$user_bank){
            $this->error(__('请先绑定银行卡'));
        }

        // 查询相同CPF账号的用户
        $bank_account = $user_bank->bank_account;
        
        // 提现系统配置
        $system = config('system');
        $cpf_status = $user->usersetting->cpf_status; // CPF状态
        // cpf开关开启 只能与上次提现相同CPF账号提现
        if($cpf_status == 1){
            $where['status'] = 1;
            $last_withdraw = $this->model->where($where)->order('id desc')->find();
            if($last_withdraw && $bank_account != $last_withdraw->wallet->pix){
                $this->error(__('本次提现账号与上次不同，请联系客服修改。'));
            }
        }
        
        // 相同账户的钱包id
        // $sameWalletId = \app\common\model\UserBank::where('bank_account', $bank_account)->column('id');
        // dd($sameWalletId);

        // 7日内一个账号只能三个用户使用 去提现表查询用相同cpf的用户
        $checkSameCpfUser = $this->model
            ->where('account', $bank_account)
            ->where('status', '1')
            ->whereTime('createtime', '>=', datetime(strtotime('-7 days')))
            ->group('user_id')
            ->column('user_id');
        
        // 排除不是同一个站点的用户id
        $diffUserids = User::where('origin', $user->origin)->where('id', 'in', $checkSameCpfUser)->column('id');
        
        if(count($diffUserids) >= 1 && !in_array($user->id, $diffUserids)){
            //  为什么大于等于1, 不是三个用户吗 TODO
            $this->error(__('一个CPF只能用于一个账户'));
        }

        $min_withdraw = $system['min_withdraw']; // 最小提现金额
        $withdraw_rate = $system['withdraw_rate']; // 提现手续费率

        if(!$money || $money < $min_withdraw){
            $this->error(__('提现金额不能小于最小提现金额'));
        }

        if($money > $user->money){
            $this->error(__('提现金额不能大于账户余额'));
        }

        // 提现收手续费（6W（约105RMB）以下5%手续费  6W以上免手续费）
        $fee = 0;
        if($money <= 60000){
            $fee = $money * $withdraw_rate / 100;
        }

        $real_money = $money - $fee; // 实际到账金额

        // 账号类型
        $account_type = $user_bank->account_type;

        // 提现数据
        $withdrawData = [
            'admin_id'      => $user->admin_id,
            'user_id'       => $user->id,
            'user_bank_id'  => $user_bank->id,
            'identityType'  => $user_bank->identityType,
            'identityNo'    => $user_bank->identityNo,
            'account'       => $user_bank->bank_account,
            'email'         => $user_bank->email,
            'name'          => $user_bank->name,
            'phone_number'  => $user_bank->phone_number,
            'account_type'  => $account_type, 
            'bank_code'     => $user_bank->bank_code,
            'bank_name'     => $user_bank->bank_name,
            'money'         => $money,
            'order_no'      => date('YmdHis') . rand(100000, 999999), // 生成订单号
            'fee'           => $money * $withdraw_rate / 100,
            'real_money'    => $real_money,
            'type'          => 0, // 0表示普通提现, 1佣金
            'is_virtual'    => $user->is_test == 1 ? 1 : 0, // 0表示真实提现, 1虚拟提现
            'status'        => $user->is_test == 1 ? 3 : 0, 
        ];
        
        // 有用户提交就创建风控
        \app\common\service\util\Risk::checkRiskByRole($user);

        $result = false;
        Db::startTrans();
        try{
            $result = $this->model->save($withdrawData);

            $data = [
                'withdraw'  => [
                    'money'                 => -$money, // 扣去余额
                    'typing_amount_limit'   => 0, // 打码量要求
                    'transaction_id'        => $withdrawData['order_no'],
                    'status'                => 0,
                ],
            ];

            if($user->insertLog($user, $data) === false){
                $result = false;
            }

            if($result != false){
                Db::commit();
            }

        }catch(\Exception $e){
            Db::rollback();
            \think\Log::record($e->getMessage(), 'withdraw_error');
            $this->error(__('提现申请失败') . ': ' . $e->getMessage());
        }
        
        if($result === false){
            $this->error(__('提现申请失败'));
        }
        $this->success(__('提现申请成功，请耐心等待审核'), ['money' => $user->money]);
    }

    /**
     * 提现记录
     */
    public function record()
    {
        $date = $this->request->get('date', '');

        if(!in_array($date, [0, 1, 7, 15, 30])){
            $date = ''; // 默认全部
        }

        if($date != ''){
            $where['createtime'] = ['>=', datetime(strtotime('-' . $date . ' days'))];
        }

        $user = $this->auth->getUser();

        $arr = [__('审核中'), __('提现成功'), __('拒绝'), __('提现失败'), __('异常')];

        $fields = "id,order_no,wallet_id,money,real_money,fee,status,paytime,remark,createtime";
        $where['user_id'] = $user->id;
        $list = $this->model->where($where)->field($fields)->order('id desc')->select();

        $total = 0; // 总提现
        foreach($list as $val){
            if($val->status == 1){
                $total += $val->real_money;
            }
            $val->status_text = $arr[$val->status] ?? __('异常');
            if($user->is_test){
                $val->status_text = __('提现成功');
                $total += $val->real_money;
            }
            
            $val->wallet = $val->wallet;

            if(!containsChinese($val->remark)){
                $val->remark = $val->remark ? substr($val->remark, strpos($val->remark, ':')) : '';
            }else{
                $val->remark = '';
            }
            
            
            $val->paytime = $val->paytime ? date('H:i:s', strtotime($val->paytime)) : '';
        }

        $retval = [
            'list'      => $list,
            'total'     => $total,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * supe 提现回调
     */
    public function supepay_withdraw()
    {
        $params = $this->request->param();
        \think\Log::record($params,'supepay_withdraw_param');

        $where['order_no'] = $params['merOrderId'];
        $where['status'] = '4';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 补上平台订单号
        $order->transaction_id = $params['supefinaOrderId'] ?? '';

        // 获取配置
        $config = $order->channel->withdraw_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        if($params['status'] == '01'){
            // 成功
            $this->notify($order, 'SUPEPAY PAID');
        }else{
            // 失败的话退回 并改成异常单
            $this->failNotify($order, $params['msg']);
        }
        return 'SUCCESS';
    }

    /**
     * u2c提现回调
     */
    public function u2cpay_withdraw()
    {
        $params = $this->request->param();
        \think\Log::record($params,'u2cpay_withdraw_param');

        $where['order_no'] = $params['merchantOrderNo'];
        $where['status'] = '4';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 获取配置
        $config = $order->channel->withdraw_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        if($params['status'] == 'PAID'){
            // 成功
            $this->notify($order, 'U2CPAY PAID');
        }else{
            // 失败的话退回 并改成异常单
            $this->failNotify($order, $params['errorMsg']);
        }
        return 'success';
    }

    /**
     * cepay提现回调
     */
    public function cepay_withdraw()
    {
        $params = $this->request->param();
        \think\Log::record($params,'cepay_withdraw_param');

        $where['order_no'] = $params['out_trade_no'];
        $where['status'] = '4';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 获取配置
        $config = $order->channel->withdraw_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        if($params['refCode'] == 1){
            // 成功
            $this->notify($order, 'CEPAY PAID');
        }else{
            // 失败的话退回 并改成异常单
            $this->failNotify($order, $params['error_info']);
        }
        return 'OK';
    }

    
    /**
     * ouropago提现回调
     */
    public function ouropago_withdraw()
    {
        $params = $this->request->param();
        $params = html_entity_decode($params['data'], ENT_QUOTES, 'UTF-8');
        $params = json_decode($params, true);
        \think\Log::record($params,'ouropago_withdraw_param');

        $where['order_no'] = $params['orderNo'];
        $where['status'] = '4';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return '查无此单'; 
        }

        // 获取配置
        $config = $order->channel->withdraw_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            // return 'error'; // IP白名单验证不通过
        }

        if($params['status'] == 'SUCCESS'){
            // 成功
            $this->notify($order, 'OUROPAGO PAID');
        }else{
            // 失败的话退回 并改成异常单
            $this->failNotify($order, $params['message']);
        }
        return 'success';
    }

    
     /**
     * funpay 提现回调
     */
    public function funpay_withdraw()
    {
        $params = $this->request->param();
        \think\Log::record($params,'funpay_withdraw_param');

        $where['order_no'] = $params['orderNo'];
        $where['status'] = '4';
        $order = $this->model->where($where)->find();
        if(!$order){
            // 订单不存在
            return 'fail'; 
        }

        // 获取配置
        $config = $order->channel->withdraw_config;

        // IP白名单验证通过
        $ip = getUserIP();
        if(isset($config['ip_white_list']) && !in_array($ip, explode(',', $config['ip_white_list']))){
            return 'fail'; // IP白名单验证不通过
        }

        if($params['status'] == 'success'){
            // 成功
            $this->notify($order, 'FUNPAY PAID');
        }else{
            // 失败的话退回 并改成退款单
            $this->failNotify($order, $params['message']);
        }
        return 'success';
    }

    /**
     * 统一提现回调逻辑处理
     */
    public function notify($order, $remark = '')
    {
        // 成功代付
        $result = false;
        Db::startTrans();
        try{
            $user = User::lock(true)->find($order->user_id);

            // 修改订单状态
            $order->remark = $remark;
            $order->status = 1;
            $order->paytime = datetime(time());
            $result = $order->save();
            
            // 总提现
            $user->userdata->total_withdraw += $order->money;
            if($user->userdata->save() === false){
                $result = false;
            }

            // 更新业务员数据
            if($user->admindata){
                $user->admindata->withdraw_amount += $order->money; // 累计提现金额
                if($user->admindata->save() === false){
                    $result = false;
                }
            }

            if($result != false){
                Db::commit();
            }

        }catch(\Exception $e){
            \think\Log::record($e->getMessage(),'u2cppay_withdraw_ERROR');
            // echo $e->getMessage();
            Db::rollback();
        }
    }

    /**
     * 失败处理
     */
    public function failNotify($order, $remark = '')
    {
        $result = false;
        Db::startTrans();
        try{
            $user = User::lock(true)->find($order->user_id);

            // 修改订单状态
            $order->remark = $order->channel->name . ': ' . $remark;
            $order->status = 3; // 改成失败单
            $result = $order->save();
            
            // 返回金额到用户钱包
            // 数据准备
            $data = [
                'withdraw_return' => [
                    'money'                 => $order->money,
                    'typing_amount_limit'   => 0,
                    'transaction_id'        => $order->order_no, // 记录表id
                    'status'                => 0,
                ],
            ];
            if(User::insertLog($user, $data) === false){
                $result = false;
            }

            if($result != false){
                Db::commit();
            }

        }catch(\Exception $e){
            // \think\Log::record($e->getMessage(),'u2cppay_withdraw_ERROR');
            echo $e->getMessage();
            Db::rollback();
        }
    }
}