<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Config;

/**
 * 会员接口
 * @ApiSector (用户)
 * 
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'register', 'testLogin'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }

    /**
     * 测试登录
     * @ApiInternal
     */
    public function testLogin()
    {
        $this->auth->direct($this->request->get('user_id'));
        $this->success('ok', $this->auth->getUserinfo());
    }

    /**
     * 会员信息
     * @ApiMethod (GET)
     */
    public function userinfo()
    {
        $service = new \app\common\service\User();
        $service->userinfo();
    }

    /**
     * 会员登录
     * @ApiSector (登录注册注销)
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     */
    public function login()
    {
        $service = new \app\common\service\User();
        $service->login();
    }

    /**
     * 注册会员
     * @ApiSector (登录注册注销)
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="账号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="repassword", type="string", required=true, description="确认密码")
     * @ApiParams (name="invite_code", type="string", required=true, description="邀请码")
     */
    public function register()
    {
        $service = new \app\common\service\User();
        $service->register();
    }

    /**
     * 退出登录
     * @ApiSector (登录注册注销)
     * @ApiMethod (POST)
     */
    public function logout()
    {
        $service = new \app\common\service\User();
        $service->logout();
    }

    /**
     * vip列表
     * @ApiMethod (GET)
     */
    public function vipList()
    {
        $service = new \app\common\service\User();
        $service->vipList();
    }

    /**
     * 领取vip奖励
     * @ApiMethod (POST)
     */
    public function receiveVip()
    {
        $service = new \app\common\service\User();
        $service->receiveVip();
    }

    /**
     * 编辑钱包
     * @ApiMethod (POST)
     * @ApiParams (name="name", type="string", required=true, description="姓名")
     * @ApiParams (name="phone_number", type="string", required=true, description="手机号")
     * @ApiParams (name="chave_pix", type="string", required=true, description="chave_pix")
     * @ApiParams (name="pix", type="string", required=true, description="pix")
     * @ApiParams (name="cpf", type="string", required=true, description="cpf")
     * @ApiParams (name="area_code", type="string", required=true, description="area_code区号")
     * @ApiParams (name="is_default", type="int", required=true, description="is_default")
     * @ApiParams (name="id", type="int", required=true, description="id 修改时传")
     */
    public function editwallet()
    {
        $service = new \app\common\service\User();
        $service->editwallet();
    }

    /**
     * 设置提现密码
     * @ApiMethod (POST)
     * @ApiParams (name="pay_password", type="string", required=true, description="密码")
     * @ApiParams (name="re_pay_password", type="string", required=true, description="密码")
     */
    public function setPassword()
    {
        $service = new \app\common\service\User();
        $service->setPassword();
    }

    /**
     * 校验提现密码
     * @ApiMethod (POST)
     * @ApiParams (name="pay_password", type="string", required=true, description="密码")
     */
    public function checkPassword()
    {
        $service = new \app\common\service\User();
        $service->checkPassword();
    }

    /**
     * 用户资料
     * @ApiMethod (GET)
     * 
     */
    public function profile()
    {
        $service = new \app\common\service\User();
        $service->profile();
    }

    /**
     * 编辑用户资料
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="whatsapp", type="string", required=true, description="whatsapp")
     * @ApiParams (name="facebook", type="string", required=true, description="facebook")
     * @ApiParams (name="telegram", type="string", required=true, description="telegram")
     * @ApiParams (name="line", type="string", required=true, description="line")
     * @ApiParams (name="twitter", type="string", required=true, description="twitter")
     * @ApiParams (name="birthday", type="int", required=true, description="birthday")
     */
    public function editProfile()
    {
        $service = new \app\common\service\User();
        $service->editProfile();
    }

    /**
     * 用户层级
     * @ApiMethod (GET)
     * @ApiParams (name="rank", type="int", required=true, description="1,2,3")
     * @ApiParams (name="search_user_id", type="int", required=true, description="用户id")
     */
    public function rank()
    {
        $service = new \app\common\service\User();
        $service->rank();
    }
    
    /**
     * 添加银行卡
     */
    public function addbank()
    {
        $service = new \app\common\service\User();
        $service->addbank();
    }
    
    
}
