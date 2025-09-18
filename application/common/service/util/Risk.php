<?php
namespace app\common\service\util;

use app\common\model\risk\Task;
use app\common\model\User;
use think\Db;
use think\Log;

class Risk
{
    // 会被添加进风险预警栏的条件
    // 1.单个博主ID下储户出现两组或以上 两个相同IP地址
    // 2.单个博主ID下储户出现流水达到充值金额的1~1.5倍后立即提款
    // 3.单个博主ID下储户出现5名以上提款密码格式相同 如:ABBABB,ABABAB或者连续数字 如123456
    // 4.单个博主ID下储户出现用户名前5个字符相同
    // 5.单个博主ID下储户出现相同设备 注册达到3个账号
    // 6.单个博主ID下储户出现5~10分钟内多次（3个以上）相同存款金额（待定  数量过多 可能误判 后台可能出现问题）
    // 7.单个博主ID下储户出现50%储户账号内拥有余额且超过20分钟无任何操作
    // 8.单个博主ID下储户出现40%以上储户提款（带来50位储户以上）
    // 9.单个博主ID下储户出现两个不同的ID  使用一个取款信息

    // 会被系统直接设置杀率的条件（被系统直接设置杀率的博主会出现在已设置杀率窗口中 方便业务员及时查看是否有误杀）
    // 1.单个博主ID下储户出现登录密码或提款密码超过4个以上相同
    // 2.单个博主ID下储户出现60%以上储户提款（带来10位储户以上）
    // 3.单个博主ID下储户出现两组或以上 3个相同IP地址
    // 4.单个博主ID下储户出现5~10分钟内多次（3个以上）相同存款金额，1~1.5倍流水立即提款，40%以上储户提款（待定 高储户博主容易误判）
    // 5.单个博主ID下储户出现三个不同的ID  使用一个取款信息

    // 两组两个相同IP
    // 流水1-1.5倍后立即提款
    // 提款密码超过3个相同
    // 40%以上储户提款(50人以上)
    // 两个不同ID用同一取款信息
    // 5-10分钟相同存款+快速提款
    // 5名以上密码格式异常
    // 5-10分钟相同存款金额
    public static $jobs = [
        'checkSameIPGroups'                 => [
            'intro' => '相同IP',
            'refer' => [2, 2], // 标准值
        ],
        'checkQuickWithdrawAfterBet'        => [
            'intro' => '立即提款',
            'refer' => [1, 1.5],
        ],
        'checkSameWithdrawPassword'         => [
            'intro' => '提款密码相同',
            'refer' => [3],
        ],
        'checkHighWithdrawRate'             => [
            'intro' => '储户提款率',
            'refer' => [
                [
                    'people' => 50,
                    'percent' => 0.4
                ],
                [
                    'people' => 10,
                    'percent' => 0.6
                ]
            ]
        ],
        'checkSharedWithdrawInfo'           => [
            'intro' => '同一取款信息',
            'refer' => [2],
        ],
        'checkQuickDepositWithdraw'         => [
            'intro' => '相同存款+快速提款',
            'refer' => [3, 0.4],
        ],
        // 'checkPasswordPattern'              => [
        //     'intro' => '密码格式异常',
        //     'refer' => [5],
        // ],
        'checkSameDepositAmount'            => [
            'intro' => '相同存款金额',
            'refer' => [5],
        ],
        'checkSameDepositCpf'               => [
            'intro' => '充值同一CPF',
            'refer' => [3],
        ],
    ];

    /**
     * 通过用户信息, 检测博主下的风险用户
     */
    public static function checkRiskByRole($user)
    {
        if($user->is_test == 1){
            return;
        }

        // 1. 如果是博主的话, 则直接使用该id去检测
        if($user['role'] == 1){
            $bloggerUserId = $user->id;
        }else{
            // 查询上级有博主没
            $where['role'] = 1;
            $where['id'] = ['in', $user['parent_id_str']];
            $bloggerUserId = db('user')->where($where)->value('id');
        }

        if(!$bloggerUserId){
            return;
        }
        
        // 检查该博主是否有创建风险检测任务
        $riskTask = Task::where('user_id', $bloggerUserId)->find();

        if($riskTask){
            if($riskTask->status == 1){
                return; // 已经审核过了, 不再检测
            }

            // 如果距离上次执行超过半小时了, 则重新创建任务
            if(time() - strtotime($riskTask['lasttime']) > 1800){
                $data = [];
                foreach(self::$jobs as $job => $val){
                    $data[] = [
                        'admin_id'      => $riskTask->admin_id,
                        'user_id'       => $riskTask->user_id,
                        'task_id'       => $riskTask->id,
                        'method'        => $job,
                        'method_intro'  => $val['intro'],
                        'refer'         => json_encode($val['refer']),
                        'createtime'    => datetime(time()),
                    ];
                }
                db('risk_task_log')->insertAll($data);
            }
            return;
        }

        Db::startTrans();
        try{
            // 创建风险检测任务
            $task_id = db('risk_task')->insertGetId([
                'admin_id'      => $user['admin_id'],
                'user_id'       => $bloggerUserId,
                'createtime'    => datetime(time()),
            ]);

            $data = [];
            foreach(self::$jobs as $job => $val){
                $data[] = [
                    'admin_id'      => $user->admin_id,
                    'user_id'       => $bloggerUserId,
                    'task_id'       => $task_id,
                    'method'        => $job,
                    'method_intro'  => $val['intro'],
                    'refer'         => json_encode($val['refer']),
                    'createtime'    => datetime(time()),
                ];
            }
            db('risk_task_log')->insertAll($data);
            Db::commit();
        }catch (\Exception $e){
            Log::record($e->getMessage(), 'risk_task_error');
            Db::rollback();
            return false;
        }

        return true;
    }

    /**
     * 发送消息到Telegram
     */
    public static function sendTelegram($log, $is_test = 0)
    {
        $user = User::where('id', $log['user_id'])->field('id,admin_id,origin')->find();

        

        $origin = $user->origin;
        $admin_nickname = $user->admin->nickname;
        $admin_username = $user->admin->username;
        $result_intro = $log['result_intro'] ?? '有风险';

        $domain = config('channel.domain');
        $domain = str_replace('api', 'admin', $domain) . '/game.php';

        $message = "风险预警 \n";
        $message .= "站点：$origin\n";
        $message .= "后台：$domain\n";
        $message .= "归属业务员：$admin_nickname\n";
        $message .= "业务员账号：$admin_username\n";
        $message .= "博主UID: $user->id \n";
        $message .= "风险说明：$result_intro \n";
        $message .= "请及时审核, 详情请到后台查看.";

        if($is_test == 1){
            $params = [
                'chat_id'   => 7104843880,
                'text'      => $message,
            ];
            $apiUrl = "https://api.telegram.org/bot7593152406:AAGQc3rjkIXo1PlxCF4HEhTdSxPapAyAYDc/sendMessage";
        }else{
            $chat_id = db('admin')->where('id', $log['admin_id'])->value('chat_id');

            // 老高组单独群
            $department_ids = db('department')->where('id|parent_id', 31)->column('id');
            $admin_ids = db('department_admin')->where('department_id', 'in', $department_ids)->column('admin_id');

            if(!$chat_id){
                if(in_array($log['admin_id'], $admin_ids)){
                    $chat_id = -4815691008;
                }else{
                    $chat_id = -4850275893;
                }
            }

            $params = [
                'chat_id'       => $chat_id,
                'text'          => $message,
            ];
            // if($chat_id){
            //     $params = [
            //         'chat_id'       => $chat_id,
            //         'text'          => $message,
            //     ];
            // }else{
                
            //     if(in_array($log['admin_id'], $admin_ids)){
            //         $params = [
            //             'chat_id'       => -4815691008,
            //             'text'          => $message,
            //         ];

            //     }else{
            //         $params = [
            //             'chat_id'       => -4850275893,
            //             'text'          => $message,
            //         ];
            //     }
            // }
            
            $apiUrl = "https://api.telegram.org/bot7120074308:AAGKWlR5XQ0MySxca2vup1MmMYW3mJ8vUjU/sendMessage";
        }

        Notice::send($apiUrl, $params);
    }

    /**
     * 检测相同IP地址的用户组
     */
    public static function checkSameIPGroups($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }
        
        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 2; // 标准值 组

        $range = $refer[1] ?? 2; // 范围值

        // 检测相同IP地址的用户组
        $where['is_test'] = 0;

        $fields = "joinip, COUNT(*) as count";
        // 查询下三级
        $user = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
            ->field($fields)
            ->group('joinip')
            ->having('count >= ' . $standard)
            ->select();
        
        // 如果没有则返回
        if(empty($user)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 统计同ip人数
        $user_count = 0;
        foreach($user as $key => $val){
            $user_count += $val['count'];
            $user[$key]['user_ids'] = implode(',', db('user')->where('joinip', $val['joinip'])->where('role', 0)->where('is_test', 0)->column('id'));
        }
        
        // 统计
        $count = count($user);

        // 没有超过, 则不处理  两组两个相同IP
        $is_pass = 1;
        if($range <= $count){
            $is_pass = 0; // 不通过
        }

        $log->result = json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $count . '组' . $user_count . '个相同IP地址';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = $is_pass;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';
    }

    /**
     * 检查流水1-1.5倍后立即提款
     */
    public static function checkQuickWithdrawAfterBet($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 1; // 标准值 组

        $range = $refer[1] ?? 1.5; // 范围值

        // 检查流水1-1.5倍后立即提款
        $second = 20 * 60;

        $startTime = time() - $second;
        $endTime = time();

        // 该博主下, 在20分钟内注册的用户
        $where['is_test'] = 0;
        $where['createtime'] = ['between', [$startTime, $endTime]];

        // 查询下三级
        $userIds = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
            ->column('id');
        
        // 如果没有则返回
        if(empty($userIds)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $user_ids = db('user_data')
            ->where('user_id', 'in', $userIds)
            ->where('total_bet', '>', 0)
            ->whereExp('total_bet', "BETWEEN total_recharge * " . $standard . " AND total_recharge * " . $range)
            ->column('user_id');
            
        // dd($user_ids);
        // 如果没有则返回
        if(empty($user_ids)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 流水1-1.5倍后立即提款
        $map['a.user_id'] = ['in', $user_ids];
        $map['a.status'] = '1';
        $withdraw = db('withdraw a')
            ->join('user b', 'a.user_id = b.id')
            ->where($map)
            ->field('a.user_id,a.order_no,a.paytime,b.createtime')
            ->select();
        
        $data = [];
        $k = 0;
        foreach($withdraw as $val){
            // 如果提款时间在用户注册的20分钟内, 则认为是立即提款
            if($val['createtime'] + $second > strtotime($val['paytime'])){
                $val['createtime'] = datetime($val['createtime']);
                $data[$k] = $val;
                $k ++;
            }
        }
        
        // 没有的情况下则不处理
        if(empty($withdraw)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $count = count($data);

        if($count == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }
      
        $is_pass = 0; // 不通过

        $log->result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = '注册' . $second/60 . '分钟内, 流水' . $standard . '-' . $range . '倍后立即提款';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = $is_pass;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';
    }
    
    /**
     * 检查相同提款密码的用户
     */
    public static function checkSameWithdrawPassword($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 3; // 标准值 组

        // 检查相同提款密码的用户
        $where['is_test'] = 0;
        $where['is_first_recharge'] = 1;

        // 不为空的
        $where['pay_password'] = ['neq', ''];

        $fields = "pay_password, COUNT(*) as count";

        // 查询下三级
        $user = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
            ->field($fields)
            ->group('pay_password')
            ->having('count >= ' . $standard)
            ->select();

        if(empty($user)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 统计用户数量
        $user_count = 0;
        foreach($user as $key => $val){
            $user_count += $val['count'];
            $user[$key]['user_ids'] = implode(',', db('user')->where('pay_password', $val['pay_password'])->where('role', 0)->where('is_test', 0)->column('id'));
        }
        // dd($user);

        $count = count($user);

        if($count == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // // 没有超过, 则不处理
        // $is_pass = 1;
        // if($standard <= $count){
        //     $is_pass = 0; // 不通过
        // }
        $is_pass = 0; // 不通过
        // 提款密码超过3个相同
        $log->result = json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $count . '组' . $user_count . '个相同提款密码';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = $is_pass;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';
    }

    /**
     * 检测高提款比例
     */
    public static function checkHighWithdrawRate($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $arr = json_decode($log['refer'], true);

        $where['is_test'] = 0;
        $where['is_first_recharge'] = 1;

        // 查询下三级
        $userIds = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
           ->column('id');

        // 总人数
        $count = count($userIds);
        if($count == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }   

        $rate = 0;
        $people = 0;
        foreach($arr as $item){

            if($count >= $item['people']){
                $rate = $item['percent'];
                $people = $item['people'];
                break;
            }
        }

        if($rate == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $userdata = db('user_data')
            ->where('user_id', 'in', $userIds)
            ->where('total_withdraw', '>', 0)
            ->count();

        // 40%以上储户提款(50人以上)
        $cur_rate = sprintf('%.2f', $userdata / $count);
        if($cur_rate < $rate){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $data = [
            'total_users'       => $count,
            'withdraw_users'    => $userdata,
            'rate'              => $cur_rate * 100
        ];
        // dd($data);
        $is_pass = 0;
     
        $log->result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $cur_rate * 100 . '%以上储户提款(' . $people . '人以上), 共有' . $userdata . '个用户';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = $is_pass;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';
    }

    /**
     * 检查三个不同ID用同一个取款信息
     */
    public static function checkSharedWithdrawInfo($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 2; // 标准值 组

        $where['is_test'] = 0;
        
        // 查询下三级
        $userIds = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
           ->column('id');

        // dd($userIds);
        if(empty($userIds)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $cpf = db('withdraw')
            ->alias('a')
            ->join('user_wallet b', 'a.wallet_id = b.id')
            ->where('a.user_id', 'in', $userIds)
            ->where('a.status', '1')
            ->field('b.pix, count(distinct a.user_id) count')
            ->group('b.pix')
            ->having('count >= ' . $standard)
            ->select();
        
        // 统计用户数量
        $user_count = 0;
        foreach($cpf as $key => $val){
            $user_count += $val['count'];
            $cpf[$key]['user_ids'] = implode(',', db('user_wallet')->where('pix', $val['pix'])->column('user_id'));
        }
        // dd($cpf);
        $count = count($cpf);

        if($count == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $is_pass = 0; // 不通过

        // 提款密码超过3个相同
        $log->result = json_encode($cpf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $count . '组' . $user_count . '单使用相同取款信息';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = $is_pass;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';    
    }

    /**
     * 检测快速存取款
     */
    public static function checkQuickDepositWithdraw($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 3; // 标准值 组

        $range = $refer[1] ?? 0.4; 

        $where['is_test'] = 0;
        
        // 查询下三级
        $userIds = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
           ->column('id');

        if(empty($userIds)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 获取最近10分钟的存款记录
        $depositUsers = db('recharge')
            ->where('user_id', 'in', $userIds)
            ->where('status', '1')
            ->where('paytime', '>=', datetime(time() - 10 * 60))
            ->group('money')
            ->having('COUNT(*) >= ' . $standard)
            ->column('DISTINCT user_id');
        
        if(empty($depositUsers)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 检查提款用户比例
        $total = count($depositUsers);
        $withdrawUsers = db('withdraw')
            ->where('user_id', 'in', $depositUsers)
            ->where('status', '1')
            ->where('paytime', '>=', datetime(time() - 10 * 60))
            ->group('user_id')
            ->column('DISTINCT user_id');
        
        $count = count($withdrawUsers);

        $cur_rate = sprintf('%.2f', $count / $total);
        if($cur_rate < $range){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 5-10分钟相同存款+快速提款
        $data = [
            'cur_rate'       => $cur_rate * 100,
            'recharge_users' => $total,
            'withdraw_users' => $count
        ];

        $log->result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $count . '人10分钟相同存款+快速提款(设定比例为' . $range * 100 . '%), 实际为: ' . $cur_rate * 100 . '%';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = 0;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }

        return $log->result_intro . '<br>';    
    }

    /**
     * 检测密码格式
     */
    public static function checkPasswordPattern($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 5; // 标准值 组

        $where['is_test'] = 0;
        $where['is_first_recharge'] = 1;
        $where['pay_password'] = ['<>', ''];

        // 查询下三级
        $passwords = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
           ->column('pay_password');
        
        if(empty($passwords)){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        $patternCount = 0;
        foreach ($passwords as $pwd) {
            if (preg_match('/^(\d)\1{5}$|^(\d{2})\2{2}$|^\d{6}$/', $pwd)) {
                if ($pwd === str_repeat(substr($pwd, 0, 1), 6) || 
                    $pwd === str_repeat(substr($pwd, 0, 2), 3) ||
                    ctype_digit($pwd) && $pwd == strval(intval($pwd)+111111-111111)
                ) {
                    $patternCount ++;
                }
            }
        }
        
        return $patternCount >= $standard;
    }
    
    /**
     * 检测相同存款金额
     */
    public static function checkSameDepositAmount($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);

        $standard = $refer[0] ?? 3; // 标准值 组

        $timeRange = 60 * 10;

        // 获取博主下所有储户ID
        $where['is_test'] = 0;
        $where['is_first_recharge'] = 1;
         // 查询下三级
        $userIds = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
            ->column('id');
        // dd($userIds);
        if(count($userIds) < $standard){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }
    
        // 检测最近时间范围内的存款记录
        $result = db('recharge')
            ->where('user_id', 'in', $userIds)
            ->where('status', '1')
            ->where('paytime', '>=', datetime(time() - $timeRange))
            ->group('money') // 按存款金额分组
            ->having('COUNT(*) >= ' . $standard)
            ->column('DISTINCT user_id');

        $count = count($result);
        if($count == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 10分钟内相同存款金额
        $log->result = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $count . '人10分钟相同存款, 次数超过' . $standard . '次';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = 0;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';    
    }

    /**
     * 检查充值超过3个不同uid的使用同个cpf
     */
    public static function checkSameDepositCpf($log)
    {
        if($log->status == 1){
            return; // 已经检测过了
        }

        // 参考值
        $refer = json_decode($log['refer'], true);
        $standard = $refer[0] ?? 3; // 标准值 组
        
        // 获取博主下所有储户ID
        $where['is_test'] = 0;
        $where['is_first_recharge'] = 1;
         // 查询下三级
        $userIds = db('user')
            ->where($where)
            ->where([
                ['EXP', Db::raw("FIND_IN_SET(". $log['user_id'] .", parent_id_str)")]
            ])
            ->column('id');
        // dd($userIds);
        if(count($userIds) < $standard){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }
        // dd($standard);
        $recharge = db('recharge')
                ->where('user_id', 'in', $userIds)
                ->where('status', '1')
                ->where('cpf', '<>', '')
                ->group('cpf')
                ->field('cpf,count(distinct user_id) count')
                ->having('count >= ' . $standard)
                ->select();
            // dd($recharge);

        $count = count($recharge);

        if($count == 0){
            $log->status = 1;
            $log->is_pass = 1;
            $log->save();
            return;
        }

        // 统计用户数量
        $user_count = 0;
        foreach($recharge as $key => $val){
            $user_count += $val['count'];
            $recharge[$key]['user_ids'] = implode(',', db('recharge')->where('cpf', $val['cpf'])->column('distinct user_id'));
        }

        // 提款密码超过3个相同
        $log->result = json_encode($recharge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->result_intro = $count . '组' . $user_count . '人使用相同取款信息';
        $log->status = 1; // 设置为已完成检测
        $log->is_pass = 0;
        $log->result_value = $count;
        $log->save();

        if($log->is_pass == 0){
            self::sendTelegram($log);
        }
        return $log->result_intro . '<br>';    
    }
}