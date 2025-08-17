<?php
namespace app\common\service\util;

use think\Db;
use think\Log;

class Risk
{
    // 风险等级常量
    const RISK_LEVEL_NORMAL = 0;  // 正常
    const RISK_LEVEL_LOW = 1;     // 低风险
    const RISK_LEVEL_HIGH = 2;    // 高风险
    const RISK_LEVEL_BLOCK = 3;   // 刷子
    const RISK_LEVEL_VERIFIED = 4;// 已审核

    // 直接杀率规则配置（规则一）
    protected static $killRules = [
        [
            'method' => 'checkSameWithdrawPassword',
            'params' => [4],
            'description' => '提款密码超过4个相同'
        ],
        [
            'method' => 'checkHighWithdrawRate',
            'params' => [0.6, 10],
            'description' => '60%以上储户提款(10人以上)'
        ],
        // [
        //     'method' => 'checkSameIPGroups',
        //     'params' => [3, 2],
        //     'description' => '两组或以上3个相同IP'
        // ],
        [
            'method' => 'checkQuickDepositWithdraw',
            'params' => [],
            'description' => '5-10分钟相同存款+快速提款'
        ],
        [
            'method' => 'checkSharedWithdrawInfo',
            'params' => [3],
            'description' => '三个不同ID用同一取款信息'
        ]
    ];

    // 风险预警规则配置（规则三）
    protected static $warnRules = [
        [
            'method' => 'checkSameIPGroups',
            'params' => [2, 2],
            'description' => '两组两个相同IP'
        ],
        [
            'method' => 'checkQuickWithdrawAfterBet',
            'params' => [],
            'description' => '流水1-1.5倍立即提款'
        ],
        [
            'method' => 'checkSameWithdrawPassword',
            'params' => [3],
            'description' => '提款密码超过3个相同'
        ],
        // [
        //     'method' => 'checkPasswordPattern',
        //     'params' => [5],
        //     'description' => '5名以上密码格式异常'
        // ],
        [
            'method' => 'checkUsernamePrefix',
            'params' => [5],
            'description' => '用户名前5字符相同'
        ],
        [
            'method' => 'checkSameDepositAmount',
            'params' => [3, 600],
            'description' => '5-10分钟相同存款金额'
        ],
        [
            'method' => 'checkHighWithdrawRate',
            'params' => [0.4, 50],
            'description' => '40%以上储户提款(50人以上)'
        ],
        [
            'method' => 'checkSharedWithdrawInfo',
            'params' => [2],
            'description' => '两个不同ID用同一取款信息'
        ]
    ];
    
    
     /**
     * 添加风控任务
     */
    public static function addJob($type, $targetId, $origin = 'default')
    {
        try {
            Db::name('risk_jobs')->insert([
                'type' => $type,
                'target_id' => $targetId,
                'origin' => $origin,
                'create_time' => time(),
                'update_time' => time()
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("添加风控任务失败：".$e->getMessage());
            return false;
        }
    }

    public static function processJobs($limit = 100)
    {
        // 1. 获取去重后的待处理任务（每个target_id+type取最早的一条）
        $jobs = Db::name('risk_jobs')
            ->field('MIN(id) as id, target_id, type, origin')
            ->where('status', '<>', 2)
            ->where('attempts', '<', 3)
            ->group('target_id, type') // 按target_id和任务类型分组
            ->order('id ASC')
            ->limit($limit)
            ->select();
    
        if (empty($jobs)) return 0;
    
        $processed = 0;
        foreach ($jobs as $job) {
            Db::startTrans();
            try {
                // 2. 锁定并获取任务详情
                $realJob = Db::name('risk_jobs')
                    ->where('id', $job['id'])
                    ->lock(true)
                    ->find();
    
                if (!$realJob || $realJob['status'] == 2) {
                    Db::rollback();
                    continue;
                }
    
                // 3. 标记当前任务为处理中
                Db::name('risk_jobs')
                    ->where('id', $realJob['id'])
                    ->update([
                        'status' => 1,
                        'attempts' => Db::raw('attempts+1'),
                        'update_time' => time()
                    ]);
    
                Db::commit();
    
                // 4. 执行实际风控检测
                if ($realJob['type'] == 'blogger') {
                    self::checkBloggerRisk($realJob['target_id']);
                } else {
                    self::checkAgentRisk($realJob['target_id'], $realJob['origin']);
                }
    
                // 5. 标记所有同target_id的任务为已完成（关键修改点）
                Db::name('risk_jobs')
                    ->where('target_id', $realJob['target_id'])
                    ->where('type', $realJob['type'])
                    ->where('status', '<>', 2)
                    ->update([
                        'status' => 2,
                        'update_time' => time()
                    ]);
    
                $processed++;
    
            } catch (\Exception $e) {
                Db::rollback();
                Log::error("任务处理失败：[ID:{$realJob['id']}]".$e->getMessage());
            }
        }
    
        return $processed;
    }
    
      /**
     * 检测业务员名下刷子
     */
    public static function checkAgentRisk($rootInvite, $origin)
    {
        // 1. 获取业务员信息
        $agent = Db::name('admin')
            ->where('agent_code', $rootInvite)
            ->find();
        
        // 跳过有后台的博主(type>=2)
        if ($agent && $agent['type'] >= 2) {
            return false;
        }

        $triggerRules = [];
        
        // 2. 检测相同IP规则
        $duplicateIPs = Db::name('user')
            ->where('root_invite', $rootInvite)
            ->where('origin', $origin)
            ->where('pid', 0)
            ->where('joinip', '<>', '')
            ->group('joinip')
            ->having('COUNT(*) >= 2')
            ->column('joinip');
        
        if (!empty($duplicateIPs)) {
            Db::name('user')
                ->where('joinip', 'in', $duplicateIPs)
                ->where('origin', $origin)
                ->update([
                    'rtp_rate' => 1,
                    'risk' => self::RISK_LEVEL_BLOCK
                ]);
            $triggerRules[] = 'agent_same_ip';
        }

        // 3. 检测相同提款密码规则
        $duplicatePwds = Db::name('user')
            ->where('root_invite', $rootInvite)
            ->where('origin', $origin)
            ->where('pid', 0)
            ->where('pay_password', '<>', '')
            ->group('pay_password')
            ->having('COUNT(*) >= 2')
            ->column('pay_password');
        
        if (!empty($duplicatePwds)) {
            Db::name('user')
                ->where('pay_password', 'in', $duplicatePwds)
                ->where('origin', $origin)
                ->update([
                    'rtp_rate' => 1,
                    'risk' => self::RISK_LEVEL_BLOCK
                ]);
            $triggerRules[] = 'agent_same_password';
        }

        // 记录日志
        if (!empty($triggerRules)) {
            self::logRisk($rootInvite, 'agent', $triggerRules, 'block_account');
            return true;
        }

        return false;
    }

    /**
     * 检测博主风险（整合规则一和三）
     */
    public static function checkBloggerRisk($bloggerId)
    {
        // $blogger = Db::name('user')->where('id', $bloggerId)->find();
        $blogger = \app\common\model\User::where(['id' => $bloggerId])->find();
        // 检测条件检查
        if ($blogger['invite_num'] <= 3 || $blogger['risk'] >= self::RISK_LEVEL_BLOCK) {
            return false;
        }
        // 检测终止条件（满足任一条件即不再检测）
        $skipConditions = [
            $blogger['invite_num'] <= 3,          // 邀请人数不足
            // $blogger['risk'] == self::RISK_LEVEL_VERIFIED,  // 已人工审核
            // $blogger['risk'] == self::RISK_LEVEL_BLOCK      // 已是刷子状态
        ];
        
        if (in_array(true, $skipConditions, true)) {
            // self::logRisk(
            //     $bloggerId, 
            //     'skip_check',
            //     [],
            //     '跳过检测',
            //     ['reason' => self::getSkipReason($blogger)]
            // );
            return false;
        }
        
        

        $killTriggers = [];
        $warnTriggers = [];

        // 先检测直接杀率规则
        foreach (self::$killRules as $rule) {
            $result = call_user_func_array(
                [self::class, $rule['method']], 
                array_merge([$bloggerId], $rule['params'])
            );
            
            if ($result) {
                $killTriggers[] = $rule['description'];
            }
        }

        // 触发直接杀率规则
        if (!empty($killTriggers)) {
            self::setBlockStatus($bloggerId, $killTriggers);
            return true;
        }

        // 检测风险预警规则
        foreach (self::$warnRules as $rule) {
            $result = call_user_func_array(
                [self::class, $rule['method']], 
                array_merge([$bloggerId], $rule['params'])
            );
            
            if ($result) {
                $warnTriggers[] = $rule['description'];
            }
        }

        // 处理预警结果
        $warnCount = count($warnTriggers);
        if ($warnCount > 0) {
            $riskLevel = $warnCount >= 2 ? self::RISK_LEVEL_HIGH : self::RISK_LEVEL_LOW;
            self::setRiskLevel($bloggerId, $riskLevel, $warnTriggers, false);
            return true;
        }

        // 无风险则重置状态
        // if ($blogger['risk'] != self::RISK_LEVEL_NORMAL) {
        //     self::setRiskLevel($bloggerId, self::RISK_LEVEL_NORMAL, [], false);
        // }
        
        return false;
    }
    

    /**
     * 设置刷子状态（规则一）
     */
    protected static function setBlockStatus($bloggerId, $triggers)
    {
        // 更新博主状态
        // Db::name('user')->where('id', $bloggerId)->update([
        //     'risk' => self::RISK_LEVEL_BLOCK,
        //     'rtp_rate' => 7.00
        // ]);
        // 更新博主状态（在原有备注后追加"刷子"）
        Db::name('user')
            ->where('id', $bloggerId)
            ->update([
                'risk'     => self::RISK_LEVEL_BLOCK,
                // 'rtp_rate' => 7.00,
                'remark'   => Db::raw("CONCAT(IFNULL(remark, ''), '[系统检测:刷子博主]')") // 处理NULL值
            ]);

        // // 标记所有下级用户
        // Db::name('user')->where('pid', $bloggerId)->update([
        //     'risk' => self::RISK_LEVEL_BLOCK,
        //     // 'rtp_rate' => 7.00
        // ]);

        // 记录日志
        self::logRisk(
            $bloggerId,
            'block',
            $triggers,
            '系统自动标记刷子',
            ['rtp' => 7]
        );
        
        self::sendTelegram(
            $bloggerId,
            '警告',
            $triggers,
            3
            );
    }

    /**
     * 设置风险等级（规则三）
     */
    protected static function setRiskLevel($bloggerId, $level, $triggers, $isBlock = false)
    {
        $updateData = ['risk' => $level];
        
        // 只有刷子状态才修改RTP
        if ($isBlock) {
            $updateData['rtp_rate'] = 7.00;
        }
        
        Db::name('user')->where('id', $bloggerId)->update($updateData);
        
        // 记录日志
        self::logRisk(
            $bloggerId,
            $isBlock ? 'block' : 'warn',
            $triggers,
            '风险等级变更: '.$level
        );
        
        self::sendTelegram(
            $bloggerId,
            $isBlock ? '警告' : '预警',
            $triggers,
            $level
            );
    }
    
    /**
     * 获取跳过检测原因
     */
    protected static function getSkipReason($blogger)
    {
        $reasons = [
            self::RISK_LEVEL_VERIFIED => '已人工审核',
            self::RISK_LEVEL_BLOCK => '已是刷子状态',
            'invite_num' => '邀请人数不足'
        ];
        
        if ($blogger['risk'] == self::RISK_LEVEL_VERIFIED) {
            return $reasons[self::RISK_LEVEL_VERIFIED];
        }
        if ($blogger['risk'] == self::RISK_LEVEL_BLOCK) {
            return $reasons[self::RISK_LEVEL_BLOCK];
        }
        if ($blogger['invite_num'] <= 3) {
            return $reasons['invite_num'];
        }
        
        return '未知原因';
    }

    // 以下是具体的检测方法实现
    //----------------------------------------------------------------

    /**
     * 检测相同提款密码
     */
    protected static function checkSameWithdrawPassword($bloggerId, $threshold)
    {
        return Db::name('user')
            ->where('pid', $bloggerId)
            ->where('is_recharge', 1)
            ->where('pay_password', '<>', '')
            ->group('pay_password')
            ->having('COUNT(*) >= '.$threshold)
            ->count() > 0;
    }

    /**
     * 检测高提款比例
     */
    protected static function checkHighWithdrawRate($bloggerId, $rate, $minUsers)
    {
        $total = Db::name('user')
            ->where('pid', $bloggerId)
            ->where('is_recharge', 1)
            ->count();

        if ($total < $minUsers) return false;

        $withdrawCount = Db::name('user')
            ->where('pid', $bloggerId)
            ->where('is_recharge', 1)
            ->where('total_withdraw_amount', '>', 0)
            ->count();

        return ($withdrawCount / $total) >= $rate;
    }
    
    /**
     * 检查流水1-1.5倍后立即提款（预警规则2）
     */
    protected static function checkQuickWithdrawAfterBet($bloggerId)
    {
        return Db::name('user')
        ->alias('u')
        ->join('tp_withdrow w', 'u.id=w.uid')
        ->where('u.pid', $bloggerId)
        ->where('u.is_recharge', 1)
        ->whereRaw('w.create_time BETWEEN u.createtime AND u.createtime + ?', [1200]) // 统一位置参数
        ->whereExp('u.total_bet_amount', 'BETWEEN u.total_recharge_amount AND u.total_recharge_amount*1.5')
        ->count() > 0;
    }

    /**
     * 检测相同IP组
     */
    protected static function checkSameIPGroups($bloggerId, $ipPerGroup, $groupCount)
    {
        $ipGroups = Db::name('user')
            ->where('pid', $bloggerId)
            ->where('joinip', '<>', '')
            ->group('joinip')
            ->having('COUNT(*) >= '.$ipPerGroup)
            ->count();

        return $ipGroups >= $groupCount;
    }
    
    /**
     * 检查三个不同ID用同一个取款信息（规则5）
     */
    protected static function checkSharedWithdrawInfo($bloggerId, $threshold)
    {
        // 检查CPF重复
        $cpfCount = Db::name('withdrow')
            ->alias('w')
            ->join('tp_user u', 'w.uid=u.id')
            ->where('u.pid', $bloggerId)
            ->where('w.cpf', '<>', '')
            ->group('w.cpf')
            ->having('COUNT(DISTINCT w.uid) >= ' . $threshold)
            ->count();
            
        return $cpfCount > 0 ;
    }

    /**
     * 检测快速存取款
     */
    protected static function checkQuickDepositWithdraw($bloggerId)
    {
        // 获取最近10分钟的存款记录
        $depositUsers = Db::name('recharge')
            ->where('uid', 'in', function($query) use ($bloggerId) {
                $query->name('user')
                    ->where('pid', $bloggerId)
                    ->field('id');
            })
            ->where('create_time', '>=', time() - 600)
            ->group('money')
            ->having('COUNT(*) >= 3')
            ->column('DISTINCT uid');

        if (empty($depositUsers)) return false;

        // 检查提款用户比例
        $total = count($depositUsers);
        $withdrawUsers = Db::name('withdrow')
            ->where('uid', 'in', $depositUsers)
            ->group('uid')
            ->count();

        return ($withdrawUsers / $total) >= 0.4;
    }

    /**
     * 检测密码格式
     */
    protected static function checkPasswordPattern($bloggerId, $threshold)
    {
        $passwords = Db::name('user')
            ->where('pid', $bloggerId)
            ->where('is_recharge', 1)
            ->column('pay_password');

        $patternCount = 0;
        foreach ($passwords as $pwd) {
            if (preg_match('/^(\d)\1{5}$|^(\d{2})\2{2}$|^\d{6}$/', $pwd)) {
                if ($pwd === str_repeat(substr($pwd, 0, 1), 6) || 
                    $pwd === str_repeat(substr($pwd, 0, 2), 3) ||
                    ctype_digit($pwd) && $pwd == strval(intval($pwd)+111111-111111)
                ) {
                    $patternCount++;
                }
            }
        }

        return $patternCount >= $threshold;
    }
    
    /**
     * 检测用户名前N个字符相同（规则三.4）
     * @param int $bloggerId 博主ID
     * @param int $prefixLength 前缀长度
     * @param int $threshold 触发阈值
     * @return bool
     */
    protected static function checkUsernamePrefix($bloggerId, $prefixLength = 5, $threshold = 5)
    {
        return false;
        // 1. 参数安全处理
        $prefixLength = intval($prefixLength); // 强制转为整数
        $threshold = intval($threshold);
    
        // 2. 执行查询
        $result = Db::name('user')
            ->where('pid', $bloggerId)
            ->where('is_recharge', 1)
            ->whereRaw('CHAR_LENGTH(nickname) >= ?', [$prefixLength])
            ->group(Db::raw("LEFT(nickname, {$prefixLength})")) // 正确分组方式
            ->having('COUNT(*) >= ?', [$threshold])
            ->find(); // 使用 find() 替代 exists()
    
        // 3. 返回是否存在匹配记录
        return !is_null($result);
    }
    
    /**
     * 检测相同存款金额（规则三.5）
     * @param int $bloggerId 博主ID
     * @param int $sameCount 相同次数阈值
     * @param int $timeRange 时间范围（秒）
     * @return bool
     */
    protected static function checkSameDepositAmount($bloggerId, $sameCount = 3, $timeRange = 600)
    {
        // 获取博主下所有储户ID
        $userIds = Db::name('user')
            ->where('pid', $bloggerId)
            ->where('is_recharge', 1)
            ->column('id');
    
        if (count($userIds) < $sameCount) {
            return false;
        }
    
        // 检测最近时间范围内的存款记录
        $result = Db::name('recharge')
            ->alias('r')
            ->join('tp_user u', 'r.uid=u.id')
            ->where('r.uid', 'in', $userIds)
            ->where('r.create_time', '>=', time() - $timeRange)
            ->group('r.money') // 按存款金额分组
            ->having('COUNT(DISTINCT r.uid) >= ' . $sameCount)
            ->count();
    
        return $result > 0;
    }

    /**
     * 记录风控日志（完整实现）
     */
    protected static function logRisk($targetId, $type, $triggers, $action, $extra = [])
    {
        try {
            Db::name('risk_logs')->insert([
                'target_id' => $targetId,
                'risk_type' => $type,
                'trigger_rule' => implode(';', $triggers),
                'action' => $action,
                'details' => json_encode(array_merge(
                    [
                        'time' => date('Y-m-d H:i:s'),
                        'triggers' => $triggers
                    ],
                    $extra
                )),
                'create_time' => time()
            ]);
        } catch (\Exception $e) {
            Log::error("风控日志记录失败：".$e->getMessage());
        }
    }
    
    protected static function sendTelegram($bloggerId,$isBlock,$triggers,$level){
            $triggers_txt = implode(';', $triggers);
            // $admin_name = Env::get('database.database', '未知');
            if($level>1){
                 //站点：$origin 
                
                $userInfo = Db::name('user')
                    ->alias('u') // user表别名
                    ->join('tp_admin a', 'u.root_invite = a.agent_code') // 关联admin表
                    ->where('u.id', $bloggerId)
                    ->field('u.*, a.remark as admin_remark') // 获取admin表的备注，别名防止冲突
                    ->find();
                $agentCodes = Db::name('admin')
                    ->alias('a')
                    ->join('tp_auth_group_access aga', 'aga.uid = a.id')
                    ->whereIn('aga.group_id', [80, 81, 199])
                    ->column('a.agent_code');
                $rootInvite = $userInfo['root_invite'];
                $origin = $userInfo['origin'];
                $admin_remark = $userInfo['admin_remark'];
                $chat_id = 0;
                if (in_array($rootInvite, $agentCodes)) {
                    $chat_id = "-4815691008";
                }else{
                    $chat_id = "-4850275893";
                }
                $message = "风险$isBlock \n站点：$origin\n归属业务员：$admin_remark\n用户id:$bloggerId \n风险等级:$level  \n风险说明：$triggers_txt 
                \n请及时审核,如是刷子设置杀率.";
                $url = "https://api.telegram.org/bot7120074308:AAGKWlR5XQ0MySxca2vup1MmMYW3mJ8vUjU/sendMessage";
                
                $result = self::http_post($url,['chat_id'=>$chat_id,"text"=>$message]);
            }
           
            
    }
    
    private static function http_post($url, $data)
    {
//        $data = ['username'=>'乔峰','skill'=>'擒龙手'];
        $headers = array('Content-Type: application/x-www-form-urlencoded');
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, null); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话
//        echo($result);
        return $result;
    }

}