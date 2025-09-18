<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\service\util\Risk;
use think\Db;
/**
 * 示例接口
 * @ApiInternal
 * 
 */
class Demo extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['test', 'test1','getMyData','month'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];

    /**
     * 测试方法
     *
     * @ApiTitle    (测试名称)
     * @ApiSummary  (测试描述信息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/test/id/{id}/name/{name})
     * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
     * @ApiParams   (name="id", type="integer", required=true, description="会员ID")
     * @ApiParams   (name="name", type="string", required=true, description="用户名")
     * @ApiParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
         'code':'1',
         'msg':'返回成功'
        })
     */
    public function test()
    {
        $this->success('返回成功', $this->request->param());
    }

    /**
     * 无需登录的接口
     *
     */
    public function test1()
    {
       // 1. 获取所有主管的 admin_id 和 nickname
$zhuguanList = Db::name('admin')
    ->where('role', 2)
    ->field('id, nickname')
    ->select();

// 2. 获取昨天的日期
$yesterday = date('Y-m-d', strtotime('-1 day'));

// 3. 初始化报表数据
$reportData = [];
foreach ($zhuguanList as $zhuguan) {
    $teamAdminIds = \app\admin\model\department\Admin::getChildrenAdminIds($zhuguan['id'], true);
    
    $stats = Db::name('daybookadmin')
        ->where('admin_id', 'in', $teamAdminIds)
        ->where('date', $yesterday)
        ->field([
            'SUM(salary) as salary',
            'SUM(recharge_amount) as recharge_amount',
            'SUM(withdraw_amount) as withdraw_amount',
            'SUM(transfer_amount) as transfer_amount',
            'SUM(api_amount) as api_amount',
            'SUM(channel_fee) as channel_fee',
            'SUM(profit_and_loss) as profit_and_loss',
            'SUM(register_user) as register_user',
            'SUM(recharge_user) as recharge_user',
        ])
        ->find() ?: [
            'salary' => 0,
            'recharge_amount' => 0,
            'withdraw_amount' => 0,
            'transfer_amount' => 0,
            'api_amount' => 0,
            'channel_fee' => 0,
            'profit_and_loss' => 0,
            'register_user' => 0,
            'recharge_user' => 0,
        ];
    
    $reportData[] = [
        'admin_id' => $zhuguan['id'],
        'nickname' => $zhuguan['nickname'],
        'stats' => $stats
    ];
}

// 4. 生成HTML表格
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>主管团队昨日统计报表</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .total-row {
            font-weight: bold;
            background-color: #e6f7ff;
        }
    </style>
</head>
<body>
    <h2>主管团队昨日统计报表</h2>
    <p>统计日期：{$yesterday}</p>
    
    <table>
        <thead>
            <tr>
                <th>主管ID</th>
                <th>主管昵称</th>
                <th>工资</th>
                <th>充值金额</th>
                <th>提现金额</th>
                <th>充提差额</th>
                <th>API费用</th>
                <th>渠道费用</th>
                <th>盈亏</th>
                <th>注册人数</th>
                <th>充值人数</th>
            </tr>
        </thead>
        <tbody>
HTML;

// 添加各行数据
$totals = [
    'salary' => 0,
    'recharge_amount' => 0,
    'withdraw_amount' => 0,
    'transfer_amount' => 0,
    'api_amount' => 0,
    'channel_fee' => 0,
    'profit_and_loss' => 0,
    'register_user' => 0,
    'recharge_user' => 0,
];

foreach ($reportData as $item) {
    $html .= <<<HTML
            <tr>
                <td>{$item['admin_id']}</td>
                <td>{$item['nickname']}</td>
                <td>{$item['stats']['salary']}</td>
                <td>{$item['stats']['recharge_amount']}</td>
                <td>{$item['stats']['withdraw_amount']}</td>
                <td>{$item['stats']['transfer_amount']}</td>
                <td>{$item['stats']['api_amount']}</td>
                <td>{$item['stats']['channel_fee']}</td>
                <td>{$item['stats']['profit_and_loss']}</td>
                <td>{$item['stats']['register_user']}</td>
                <td>{$item['stats']['recharge_user']}</td>
            </tr>
HTML;
    
    // 计算总计
    foreach ($totals as $key => $value) {
        $totals[$key] += $item['stats'][$key];
    }
}

// 添加总计行
$html .= <<<HTML
            <tr class="total-row">
                <td colspan="2">总计</td>
                <td>{$totals['salary']}</td>
                <td>{$totals['recharge_amount']}</td>
                <td>{$totals['withdraw_amount']}</td>
                <td>{$totals['transfer_amount']}</td>
                <td>{$totals['api_amount']}</td>
                <td>{$totals['channel_fee']}</td>
                <td>{$totals['profit_and_loss']}</td>
                <td>{$totals['register_user']}</td>
                <td>{$totals['recharge_user']}</td>
            </tr>
HTML;

$html .= <<<HTML
        </tbody>
    </table>
</body>
</html>
HTML;

return $html;
        
    }
    
    public function month(){
        // 1. 获取本月起始和结束日期
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');
$currentMonth = date('Y-m');

// 2. 获取所有主管的 admin_id 和 nickname
$zhuguanList = Db::name('admin')
    ->where('role', 2)
    ->field('id, nickname')
    ->select();

// 3. 初始化报表数据
$reportData = [];
foreach ($zhuguanList as $zhuguan) {
    $teamAdminIds = \app\admin\model\department\Admin::getChildrenAdminIds($zhuguan['id'], true);
    
    // 查询本月统计数据
    $stats = Db::name('daybookadmin')
        ->where('admin_id', 'in', $teamAdminIds)
        ->whereBetween('date', [$firstDayOfMonth, $lastDayOfMonth])
        ->field([
            'SUM(salary) as salary',
            'SUM(recharge_amount) as recharge_amount',
            'SUM(withdraw_amount) as withdraw_amount',
            'SUM(transfer_amount) as transfer_amount',
            'SUM(api_amount) as api_amount',
            'SUM(channel_fee) as channel_fee',
            'SUM(profit_and_loss) as profit_and_loss',
            'SUM(register_user) as register_user',
            'SUM(recharge_user) as recharge_user',
        ])
        ->find() ?: [
            'salary' => 0,
            'recharge_amount' => 0,
            'withdraw_amount' => 0,
            'transfer_amount' => 0,
            'api_amount' => 0,
            'channel_fee' => 0,
            'profit_and_loss' => 0,
            'register_user' => 0,
            'recharge_user' => 0,
        ];
    
    $reportData[] = [
        'admin_id' => $zhuguan['id'],
        'nickname' => $zhuguan['nickname'],
        'stats' => $stats
    ];
}

// 4. 计算总计
$totals = [
    'salary' => 0,
    'recharge_amount' => 0,
    'withdraw_amount' => 0,
    'transfer_amount' => 0,
    'api_amount' => 0,
    'channel_fee' => 0,
    'profit_and_loss' => 0,
    'register_user' => 0,
    'recharge_user' => 0,
];

foreach ($reportData as $item) {
    foreach ($totals as $key => $value) {
        $totals[$key] += $item['stats'][$key];
    }
}

// 5. 生成HTML表格
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>主管团队本月统计报表</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .total-row {
            font-weight: bold;
            background-color: #e6f7ff;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h2>主管团队本月统计报表</h2>
    <p>统计周期：{$firstDayOfMonth} 至 {$lastDayOfMonth}</p>
    
    <table>
        <thead>
            <tr>
                <th>主管ID</th>
                <th>主管昵称</th>
                <th class="text-right">工资</th>
                <th class="text-right">充值金额</th>
                <th class="text-right">提现金额</th>
                <th class="text-right">充提差额</th>
                <th class="text-right">API费用</th>
                <th class="text-right">渠道费用</th>
                <th class="text-right">盈亏</th>
                <th>注册人数</th>
                <th>充值人数</th>
            </tr>
        </thead>
        <tbody>
HTML;

// 添加各行数据
foreach ($reportData as $item) {
    $html .= <<<HTML
            <tr>
                <td>{$item['admin_id']}</td>
                <td>{$item['nickname']}</td>
                <td class="text-right">¥{$item['stats']['salary']}</td>
                <td class="text-right">¥{$item['stats']['recharge_amount']}</td>
                <td class="text-right">¥{$item['stats']['withdraw_amount']}</td>
                <td class="text-right">¥{$item['stats']['transfer_amount']}</td>
                <td class="text-right">¥{$item['stats']['api_amount']}</td>
                <td class="text-right">¥{$item['stats']['channel_fee']}</td>
                <td class="text-right">¥{$item['stats']['profit_and_loss']}</td>
                <td>{$item['stats']['register_user']}</td>
                <td>{$item['stats']['recharge_user']}</td>
            </tr>
HTML;
}

// 添加总计行
$html .= <<<HTML
            <tr class="total-row">
                <td colspan="2">总计</td>
                <td class="text-right">¥{$totals['salary']}</td>
                <td class="text-right">¥{$totals['recharge_amount']}</td>
                <td class="text-right">¥{$totals['withdraw_amount']}</td>
                <td class="text-right">¥{$totals['transfer_amount']}</td>
                <td class="text-right">¥{$totals['api_amount']}</td>
                <td class="text-right">¥{$totals['channel_fee']}</td>
                <td class="text-right">¥{$totals['profit_and_loss']}</td>
                <td>{$totals['register_user']}</td>
                <td>{$totals['recharge_user']}</td>
            </tr>
HTML;

$html .= <<<HTML
        </tbody>
    </table>
</body>
</html>
HTML;

return $html;
    }
    
    public function extractIdFromUrl($url) 
    {
        // 解码HTML实体和URL编码
        $url = html_entity_decode(urldecode($url));
        
        // 尝试从查询参数或Fragment中提取 invite_code
        $inviteCode = '';
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
    
        // 检查常规查询参数
        if ($query) {
            parse_str($query, $params);
            $inviteCode = $params['invite_code'] ?? '';
        }
    
        // 检查Fragment中的参数（如 #/?invite_code=xxx）
        if (!$inviteCode && $fragment) {
            if (preg_match('/invite_code=([^&]+)/', $fragment, $matches)) {
                $inviteCode = $matches[1];
            }
        }
    
        // 最后尝试全局正则匹配
        if (!$inviteCode && preg_match('/invite_code=([^&]+)/', $url, $matches)) {
            $inviteCode = $matches[1];
        }
    
        // 过滤非ASCII字符（只保留字母、数字、下划线等）
        if ($inviteCode) {
            $inviteCode = preg_replace('/[^\x20-\x7E]/', '', $inviteCode); // 移除非ASCII字符
            // 或者更严格：只保留字母和数字
            // $inviteCode = preg_replace('/[^a-zA-Z0-9]/', '', $inviteCode);
        }
    
        return $inviteCode ?: '';
    }
    
    // public function extractIdFromUrl($url) 
    // {
    //     // 使用parse_url函数获取URL中的查询字符串部分
    //     $queryString = parse_url($url, PHP_URL_QUERY);

    //     // 使用parse_str函数将查询字符串解析成关联数组
    //     parse_str($queryString, $params);

    //     // 提取"id"参数值
    //     $idValue = isset($params['invite_code']) ? $params['invite_code'] : '';

    //     // 提取"id"后六位字符
    //     // $idLastSix = substr($idValue, -6);

    //     // 返回提取的"id"
    //     return $idValue;
    // }

    /**
     * 需要登录的接口
     *
     */
    public function test2()
    {
        $this->success('返回成功', ['action' => 'test2']);
    }

    /**
     * 需要登录且需要验证有相应组的权限
     *
     */
    public function test3()
    {
        $this->success('返回成功', ['action' => 'test3']);
    }
    
    public function getMyData()
    {
        // $token = '1fdsagdfs34d';

        // $post_token = $this->request->post('token');
        // if($post_token != $token){
        //     $this->error('校验失败');
        // }

        $date = date('Y-m-d', strtotime('-1 day'));
        $fields = "register_users as today_register_users ,register_recharge_users as today_register_recharge_users ,repeat_users ,repeat_amount ,";
        $fields .= "recharge_count ,recharge_money,user_lost,bet_amount ,";
        $fields .= "withdraw_money as wd_money ,blogger_withdraw_money as wd_bz_money ,member_withdraw_money as wd_kf_money ,channel_fee as recharge_fee ,";
        $fields .= "api_fee as API_fee,profit as win_money,date as date_str";
        $row = db('mydata')->where('date', '=', $date)->field($fields)->find();

        $this->success('ok', $row);
    }
}
