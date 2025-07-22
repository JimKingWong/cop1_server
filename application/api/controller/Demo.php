<?php

namespace app\api\controller;

use app\common\controller\Api;

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
    protected $noNeedLogin = ['test', 'test1','getMyData'];
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
        $url = 'https://hms-fortunapg.bet/?invite_code=b1b3a0%F0%9F%8D%80#/index';
        $res = $this->extractIdFromUrl($url);
        var_dump($res);
        
        $url = 'https://hms-fortunapg.bet/?invite_code=c5699175&amp;fbclid=PAZXh0bgNhZW0CMTEAAadMWVJB7FeZ8OIQ-x3uLa7g2mEPw8_ufIyb0ubzpa2J2LRy-TofUG7GJEjtHw_aem_iuF9ZvY8JcXfhd0EU-bsjQ#/index';
        $res = $this->extractIdFromUrl($url);
        var_dump($res);
        
        $url = 'https://hms-fortunapg.bet/?invite_code=d199d47a&amp;fbclid=PAZXh0bgNhZW0CMTEAAacpWxjyJdX-GDeGmelnJ8Itse79C-I9zdsRY7cZ-yLGcgDyEw_Xf13lTg5yhw_aem_i25krpA3haTdX0YT-nQMKQ#/index';
        $res = $this->extractIdFromUrl($url);
        var_dump($res);
        // $this->success('返回成功', ['action' => 'test1']);
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
