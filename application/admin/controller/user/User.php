<?php

namespace app\admin\controller\user;

use app\admin\model\Admin;
use app\common\controller\Backend;
use app\common\library\Auth;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;
    protected $dataLimit = 'department'; // 部门数据权限

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $filter = json_decode($this->request->get("filter", ''), true);
            $op = json_decode($this->request->get("op", ''), true);
            $map = [];
            if(isset($filter['root_invite'])){
                $admin_id = db('admin_data')->where('invite_code', $filter['root_invite'])->value('admin_id');
                unset($filter['root_invite']);
                $map['user.admin_id'] = $admin_id;
            }
            $this->request->get(['filter' => json_encode($filter)]);
            $this->request->get(['op' => json_encode($op)]);
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            
            $list = $this->model
                ->with(['userdata', 'admin.dadmin.department'])
                ->where($where)
                ->where($map)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                if($v->admin_id > 0){
                    $v->root_invite = $v->admindata->invite_code;
                }

                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 默认用户
     */
    public function virtual()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }


            $filter = json_decode($this->request->get("filter", ''), true);
            $op = json_decode($this->request->get("op", ''), true);
            $map = [];
            if(isset($filter['root_invite'])){
                $admin_id = db('admin_data')->where('invite_code', $filter['root_invite'])->value('admin_id');
                unset($filter['root_invite']);
                $map['user.admin_id'] = $admin_id;
            }
            $this->request->get(['filter' => json_encode($filter)]);
            $this->request->get(['op' => json_encode($op)]);
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            
            $list = $this->model
                ->with(['userdata', 'admin.dadmin.department'])
                ->where($where)
                ->where($map)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                if($v->admin_id > 0){
                    $v->root_invite = $v->admindata->invite_code;
                }

                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 加减余额
     */
    public function amount($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if(!$params['money']){
            $this->error('请输入金额!');
        }
        $result = false;
        Db::startTrans();
        try {
            $row->money += $params['money'];
            $result = $row->save();
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 设置
     */
    public function setting($type = null, $ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        // 余额类型
        if($this->auth->role > 2){
            $balanceArr = [0 => '发放工资', 2 => '退款'];
        }else{
            $balanceArr = ['发放工资', '赠送', '退款'];
        }
        if (false === $this->request->isPost()) {
            // 黑名单状态
            $boxStatus = ['正常领取', '禁止领取'];
            
            $this->view->assign('boxStatus', $boxStatus);
            $this->view->assign('balanceArr', $balanceArr);
            
            $commission_rate = explode(',', $row->usersetting->commission_rate);
            $this->view->assign('commission_rate', $commission_rate);
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $this->$type($row, $params);
        
        $this->success('设置成功! ');
    }

    /**
     * 设置rtp
     */
    public function rtp($row, $params)
    {
        if($params['rtp_rate'] == ''){
            $this->error('请输入杀率');
        }

        if($params['rtp_rate'] > 9.5){
            $this->error('杀率不能大于9.5');
        }

        $row->usersetting->rtp_rate = $params['rtp_rate'];
        if($row->role != 1){
            $omg = new \app\common\service\game\Omg;
            $res = $omg->setRtp($row->id, $row->usersetting->rtp_rate);
            if($res['code'] != 1){
                $this->error($res['msg']);
            }
        }
        $row->usersetting->save();
    }

    /**
     * 开通博主账号
     */
    public function open($row, $params)
    {
        if($row->role != 1){
            $this->error('对象用户不是博主，不能开通博主账号! ');
        }

        if($row->usersetting){
            $row->usersetting->is_open_blogger = $params['is_open_blogger'];
            if(!$row->usersetting->opentime && $params['is_open_blogger'] == 1){
                $row->usersetting->opentime = datetime(time());
                $row->usersetting->open_admin_id = $this->auth->id;
            }
            $row->usersetting->save();
        }else{
            db('user_setting')->insert([
                'admin_id'  => $row->admin_id,
                'user_id'   => $row['id'],
                'is_open_blogger'  => $params['is_open_blogger'],
                'open_admin_id'    => $this->auth->id,
                'opentime'         => datetime(time()),
            ]);
        }

    }

    /**
     * 宝箱黑名单
     */
    public function box($row, $params)
    {
        if($row->usersetting){
            $row->usersetting->is_black = $params['is_black'];
            $row->usersetting->save();
        }else{
            db('user_setting')->insert([
                'admin_id'  => $row->admin_id,
                'user_id'   => $row['id'],
                'is_black'  => $params['is_black'],
            ]);
        }
    }

    /**
     * 余额修改
     */
    public function balance($row, $params)
    {
        $flag = $params['flag'];

        $admin = Admin::where('id', $row->admin_id)->find();
        
        $money = $params['money'];

        // 赠送余额
        if($flag == 1){
            if($row->is_test == 0){
                // $this->error('只有测试号才能赠送余额! ');
            }

            if($params['money'] == 0){
                $row->userdata->typing_amount_limit += $params['typing_amount_limit'];
                $row->userdata->save();
                $this->success('设置成功! ');
            }

        }elseif($flag == 0){
            if($row->role != 1){
                $this->error('对象用户是博主才能发放工资! ');
            }

            // 发放工资
            if($this->auth->role == 3){
                if($admin->admindata->quota < $params['money']){
                    $this->error('可发工资不足，请联系主管发放');
                }

                // 业务员扣减额度
                $admin->admindata->quota -= $params['money'];
            }

            if($params['money'] == 0){
                $row->userdata->typing_amount_limit += $params['typing_amount_limit'];
                $row->userdata->save();
                $this->success('设置成功! ');
            }

            if($params['money'] > 0){
                // 系统发放工资
                $row->userdata->salary += $params['money'];
            }
            
        }elseif($flag == 2){
            if($money >= 0){
                $this->error('退款金额不能为正数');
            }

            if($row->money < $params['money']){
                $this->error('退款金额不能大于用户余额');
            }

            $row->userdata->salary += $params['money'];
        }

        if($admin){
            // 共赠送
            if($params['money'] > 0){
                $admin->admindata->send_amount += $params['money'];
                $admin->admindata->save();
            }
        }

        // 奖励类型
        $arr = ['admin_bonus', 'system_gift', 'refund_money'];

        // 数据准备
        $reward_data = [
            $arr[$flag] => [
                'money'                 => $money,
                'typing_amount_limit'   => (int)($params['typing_amount_limit'] ?? 0),
                'transaction_id'        => $row->id, // 记录表id
                'status'                => 1,
            ],
        ];

        // 插入余额变动日志, 以及奖励日志
        \app\common\model\User::insertLog($row, $reward_data);
    }

    /**
     * 掉绑设置
     */
    public function unbind($row, $params)
    {
        // ini_set('memory_limit', '512M'); 
        if($row->usersetting){
            $row->usersetting->unbind_status = $params['unbind_status'];
            $row->usersetting->unbind_rate = $params['unbind_rate'];
            $row->usersetting->save();
        }else{
            db('user_setting')->insert([
                'admin_id'          => $row->admin_id,
                'user_id'           => $row['id'],
                'unbind_status'     => $params['unbind_status'],
                'unbind_rate'       => $params['unbind_rate'],
            ]);
        }
    }
    
    /**
     * 分佣设置
     */
    public function commission($row, $params)
    {
        if($row->role != 1 && $params['commission_status'] == 1){
            $this->error('对象用户是博主才能设置分佣比例! ');
        }

        $commission_rate = $params['commission_rate'];
        for($i = 0; $i < count($commission_rate); $i++){
            if($commission_rate[$i] == ''){
                $commission_rate[$i] = 0;
            }

            if($commission_rate[$i] > 25 || $commission_rate[$i] < 0){
                $this->error($i+1 . '级分佣比例在0-25之间');
            }
        }
        $commission_rate = implode(',', $commission_rate);
        if($row->usersetting){
            $row->usersetting->commission_status = $params['commission_status'];
            $row->usersetting->commission_rate = $commission_rate;
            $row->usersetting->save();
        }else{
            db('user_setting')->insert([
                'admin_id'          => $row->admin_id,
                'user_id'           => $row['id'],
                'commission_rate'   => $commission_rate,
                'commission_status' => $params['commission_status'],
            ]);
        }
    }
    
    /**
     * 换绑
     */
    public function parent($row, $params)
    {
        set_time_limit(0);
        $check = $this->findParent($params['invite_code']);

        if($check['code'] == 0){
            $this->error($check['msg']);
        }

        if($check['flag'] == 2){
            $row->admin_id = $check['data']['admin_id'];
            // $row->parent_id = 0;
            // $row->parent_id_str = '';
            $row->save();

            $arr = [
                '\app\common\model\Recharge', '\app\common\model\Withdraw',
                '\app\common\model\MoneyLog', '\app\common\model\RewardLog', 
                '\app\common\model\Wallet', '\app\common\model\VipLog', 
                '\app\common\model\TurntableLog', '\app\common\model\PrizeLog'
            ];

            foreach($arr as $val){
                $userModel = (new $val);
                $data =  $userModel::where('user_id', $row->id)->select();
                foreach($data as $v){
                    $v->admin_id = $check['data']['admin_id'];
                    $v->save();
                }
            }

            $infoArr = [
                '\app\common\model\UserData', '\app\common\model\UserInfo', '\app\common\model\UserSetting'
            ];

            foreach($infoArr as $val){
                $userModel = (new $val);
                $userModel::where('user_id', $row->id)->update(['admin_id' => $check['data']['admin_id']]);

            }
           
        }else{
            $row->admin_id = $check['data']['admin_id'];
            $row->parent_id = $check['data']['id'];
            $row->be_invite_code = $check['data']['invite_code'];
            $row->parent_id_str = \app\common\model\User::parentIdStr($check['data']);
            
            $arr = [
                '\app\common\model\Recharge', '\app\common\model\Withdraw',
                '\app\common\model\MoneyLog', '\app\common\model\RewardLog', 
                '\app\common\model\Wallet', '\app\common\model\VipLog', 
                '\app\common\model\TurntableLog', '\app\common\model\PrizeLog'
            ];
            
            // $parent = db('user_data')->where('user_id', $row->parent_id)->setInc('invite_num');
            Db::name('user_data')
            ->where('user_id', $row->parent_id)
            ->update([
                'invite_num' => Db::raw('invite_num + 1'),
                'invite_recharge_num' => Db::raw('invite_recharge_num + 1')
            ]);

            foreach($arr as $val){
                $userModel = (new $val);
                $data =  $userModel::where('user_id', $row->id)->select();
                foreach($data as $v){
                    $v->admin_id = $check['data']['admin_id'];
                    $v->save();
                }
            }

            $infoArr = [
                '\app\common\model\UserData', '\app\common\model\UserInfo', '\app\common\model\UserSetting'
            ];

            foreach($infoArr as $val){
                $userModel = (new $val);
                $userModel::where('user_id', $row->id)->update(['admin_id' => $check['data']['admin_id']]);

            }
        }
        
        $row->save();
    }

    /**
     * 换绑
     */
    public function findParent($inviteCode = null)
    {
        $inviteCode = $inviteCode ?: $this->request->post('invite_code');

        // 未找到用户
        $retval = [
            'code'  => 0,
            'msg'   => '未找到用户'
        ];

        if(strlen($inviteCode) == config('system.agent_code_length')){
            $where['invite_code'] = $inviteCode;
            $admindata = db('admin_data')->where($where)->find();
            
            if(empty($admindata)){
                // 未找到用户
                $retval = [
                    'code'  => 0,
                    'msg'   => '未找到业务员'
                ];
                return $retval;
            }

            // 所属部门
            $sup_ids = \app\admin\model\department\Admin::getParentDepartmentIds($admindata['admin_id']);

            $department = db('department')->where('id', 'in', $sup_ids)->column('name');

            $group_name = '';
            if($department){
                $group_name = implode(' / ', $department);
            }

            $admin = db('admin')->where('id', $admindata['admin_id'])->find();

            $admindata['username'] = $admin['username'];
            $admindata['remark'] = $admin['remark'];
            $admindata['group_name'] = $group_name;

            $retval = [
                'code'  => 1,
                'flag'  => 2,
                'msg'   => '请求成功',
                'data'  => $admindata
            ];
        }

        if(is_numeric($inviteCode)){
            $where['id'] = $inviteCode;
            $parent = $this->model->where($where)->find();
            
            if(empty($parent)){
                return $retval;
            }

            $parent->root_invite = isset($parent->admindata->invite_code) ? $parent->admindata->invite_code : '-';

            $retval = [
                'code'  => 1,
                'flag'  => 1,
                'msg'   => '请求成功',
                'data'  => $parent
            ];
        }

        return $retval;
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if(isset($params['pay_password']) && strlen($params['pay_password']) != 6 && is_numeric($params['pay_password'])){
            $this->error('支付密码必须为6位数字');
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            
            if(isset($params['role']) && $params['role'] != $row->role && $params['role'] == 1){
                // $row->userdata->typing_amount_limit = 0;
                $totalBoxMoney = Db::name('user_reward_log')
                ->where('user_id', $row->id)
                ->where('type', 'box_bonus')
                ->sum('money');
                $row->userdata->typing_amount_limit = max(0, $row->userdata->typing_amount_limit - ($totalBoxMoney * 1.5));
                $row->userdata->save();
            }
            
            $result = $row->allowField(true)->save($params);
            
            if(isset($params['cpf_status'])){
                $row->usersetting->cpf_status = $params['cpf_status'];
            }

            if(isset($params['game_status'])){
                $row->usersetting->game_status = $params['game_status'];
            }
            $row->usersetting->save();
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

    /**
     * 批量生成用户
     */
    public function patch()
    {
        if (false === $this->request->isPost()) {
            $site = db('site')->where('status', 1)->field('url id,url name')->order('createtime desc')->select();

            $admin = Admin::where('role', '>', 2)->field('id,username')->order('id desc')->select();
            $admins = [];
            foreach($admin as $k => $v){
                if($v->admindata->invite_code){
                    $admins[$k]['id'] = $v->id;
                    $admins[$k]['name'] = $v->username;
                }
            }
            $this->assign('site', json_encode($site));
            $this->assign('admins', json_encode($admins));
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $admin_id = $this->auth->role > 2 ? $this->auth->id : 0;
        if(isset($params['invite_code']) && $params['invite_code'] != ''){
            $agent = db('admin_data')->where('invite_code', $params['invite_code'])->find();
            if(!$agent){
                $this->error('未通过邀请码, 查找到业务员, 请检查');
            }
            $admin_id = $agent['admin_id'];
        }

        $number = $params['number'];
        if($number < 1 || $number > 100){
            $this->error('请在规定范围数量内生成');
        }

        $count = $this->model->where('username', 'like', $params['username'] . '%')->where('origin', $params['origin'])->count();

        $salt = \fast\Random::alnum();
        $clear_passwrod = $params['password'];
        $password = \app\common\library\Auth::instance()->getEncryptPassword($params['password'], $salt);
        $data = [];
        for($i=0; $i<$number; $i++){
            $data[] = [
                'origin'        => $params['origin'],
                'admin_id'      => $admin_id,
                'pay_password'  => $params['pay_password'],
                'username'      => $params['username'] . ($count + $i + 1),
                'nickname'      => $params['username'] . ($count + $i + 1),
                'email'         => $params['username'] . ($count + $i + 1) . '@gmail.com',
                'password'      => $password,
                'salt'          => $salt,
                'mobile'        => 18888888888,
                'clear_passwrod'=> $clear_passwrod,
                'money'         => $params['money'],
                'remark'        => $params['remark'],
                'is_test'       => 1,
                'status'        => 'normal',
                'joinip'        => $this->request->ip(),
                'jointime'      => time(),
            ];
        }
        $this->model->saveAll($data);
        $this->success('生成成功');
    }
}
