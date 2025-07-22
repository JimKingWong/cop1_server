<?php

namespace app\admin\controller\department;

use app\admin\model\department\Department;
use app\admin\model\department\Department as DepartmentModel;
use app\common\controller\Backend;
use \app\admin\model\department\Admin as DepartmentAdminModel;
use \app\admin\model\department\AuthAdmin as AdminModel;
use app\admin\model\User;
use app\common\service\util\Notice;
use fast\Random;
use fast\Tree;
use think\Db;
use think\Model;
use think\Validate;


/**
 * 部门成员
 */
class Admin extends Backend
{

    protected $tree = null;
    /**
     * 部门人员对象
     * @var null
     */
    protected $dadminModel = null;
    protected $childrenGroupIds = [];//权限组
    /**
     * 部门所有
     * @var array
     */
    protected $allDepartment = [];//
    protected $noNeedRight=['selectpage','getDepartmentIds'];

    public function _initialize()
    {
        parent::_initialize();
        $this->childrenGroupIds = $this->auth->getChildrenGroupIds($this->auth->isSuperAdmin());

        $this->dadminModel = new DepartmentAdminModel;
        $departmentList = [];
        $this->allDepartment = \app\admin\model\department\Admin::getAllDepartmentsArray($this->auth->id,($this->auth->isSuperAdmin()||$this->auth->data_scope)?true:false);
        $this->allDepartment=collection($this->allDepartment)->toArray();

        $departmentdata = [];
        foreach ($this->allDepartment as $k => $v) {
            $state = ['opened' => true];
            $departmentList[] = [
                'id' => $v['id'],
                'parent' => $v['parent_id'] ? $v['parent_id'] : '#',
                'text' =>preg_replace('/&nbsp;|\s*|│|└|├\s*/','',__($v['name'])),//str_replace("&nbsp;",'', __($v['name'])) ,
                'state' => $state
            ];
        }


        $tree = Tree::instance()->init($this->allDepartment, 'parent_id');
        $departmentOptions = $tree->getTree(0, "<option model='@model_id' value=@id @selected @disabled>@spacer@name</option>");

        $this->view->assign('departmentOptions', $departmentOptions);
        $this->assignconfig('departmentList', $departmentList);

        $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
        foreach ($result as $k => $v) {
            $departmentdata[$v['id']] = $v['name'];
        }
        $this->view->assign('departmentdata', $departmentdata);

        //兼容旧版本没有手机号
        $database=config('database');
        $exits_mobile=\think\Db::query("SELECT 1 FROM  information_schema.COLUMNS WHERE  table_name='{$database['prefix']}admin' AND COLUMN_NAME='mobile' limit 1");
        $this->view->assign('exits_mobile', $exits_mobile?1:0);
        $this->assignconfig('exits_mobile', $exits_mobile?1:0);
        $this->assignconfig("admin", ['id' => $this->auth->id]);

        $this->assign('roleList', \app\admin\model\department\AuthAdmin::getRoleList());
    }

    /**
     * 成员列表
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        $department_id = $this->request->request("department_id");
        if ($this->request->isAjax()) {
            $this->model = new \app\admin\model\department\AuthAdmin();
            $filter = $this->request->get("filter", '');
            $filter = (array)json_decode($filter, true);
            $filter_w = [];

            if (isset($filter['department_id'])) {
                $department_id = $filter['department_id'];
                unset($filter['department_id']);
                $this->request->get(['filter' => json_encode($filter)]);
            }
            if ($department_id) {
                if (!in_array($department_id,array_column($this->allDepartment,'id'))){
                    $this->error("您所选的部门没有权限");
                }
                $admin_ids = $this->dadminModel->where('department_id', 'in', $department_id)->column('admin_id');
                $filter_w['id'] = ['in', $admin_ids];
            }else if ($this->auth->data_scope!=1&&!$this->auth->isSuperAdmin()){
                $this->childrenAdminIds = \app\admin\model\department\Admin::getChildrenAdminIds($this->auth->id, true);
                $filter_w['id']=['in',$this->childrenAdminIds];
            }


            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->where($filter_w)
                ->order($sort, $order)->fetchSql(false)
                ->count();
            $list = $this->model
                ->where($where)
                ->with(['admindata'])
                ->with(['dadmin.department'])
                ->with(['groups.getGroup'])
                ->where($filter_w)
                ->order($sort, $order)
                ->limit($offset, $limit)->fetchSql(false)
                ->select();

            $user = User::where('is_test', 0)->where('admin_id', '>', 0)->field('id,is_first_recharge,admin_id')->select();
            $userArr = [];
            $userRechargeArr = [];
            foreach($user as $val){
                $userArr[$val['admin_id']][] = $val['id'];
                if($val['is_first_recharge']){
                    $userRechargeArr[$val['admin_id']][] = $val['is_first_recharge'];
                }
            }

            foreach ($list as $k => $v) {
                $v->user_count = isset($userArr[$v['id']]) ? count($userArr[$v['id']]) : 0;
                $v->user_recharge_count = isset($userRechargeArr[$v['id']]) ? count($userRechargeArr[$v['id']]) : 0;
            }

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }



    /**
     * 添加
     * @return string|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add()
    {
        $groupdata = DepartmentModel::getGroupdata(
            $this->childrenGroupIds,
            $this->auth->isSuperAdmin() ? null : $this->auth->getGroups()
        );

        if ($this->request->isPost()) {

            $adminModel = new AdminModel();
            $departmentModel = new DepartmentModel();

            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    $department_id = $this->request->post("department_id/a");
                    $is_principal = $this->request->post("is_principal",'0','intval');

                    //获取部门信息
                    if (!$department_id) {
                        exception(__("部门不能为空"));
                    }
                    $d_list = $departmentModel->where('id', 'in', $department_id)->select();

                    if (!$d_list) {
                        exception(__("部门不能为空"));
                    }

                    if (!Validate::is($params['password'], '\S{6,16}')) {
                        exception(__("Please input correct password"));
                    }
                    $params['salt'] = Random::alnum();
                    $params['password'] = md5(md5($params['password']) . $params['salt']);
                    $params['avatar'] = '/assets/img/avatar.png'; //设置新管理员默认头像。
                    $result = $adminModel->validate('Admin.add')->save($params);
                    if ($result === false) {
                        exception($adminModel->getError());
                    }
                    if (!$this->auth->isSuperAdmin()&&isset($params['data_scope'])){
                        unset($params['data_scope']);//超级管理人才能赋予全部数据权限
                    }

                    $admin_id = $adminModel->id;
                    $dadmin = array();
                    //添加部门信息
                    foreach ($d_list as $d_row) {
                        $dadmin[] = ['is_principal'=>$is_principal==1?1:0,'department_id' => $d_row->id, 'organise_id' => $d_row->organise_id ? $d_row->organise_id : $d_row->id, 'admin_id' => $admin_id];
                    }
                    $this->dadminModel->saveAll($dadmin);

                    $group = $this->request->post("group/a");
                    //过滤不允许的组别,避免越权
                    $group = array_intersect($this->childrenGroupIds, $group);

                    //添加权限默认
                    if (!$group) {
                        exception(__('The parent group exceeds permission limit'));
                    }

                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $admin_id, 'group_id' => $value];
                    }
                    model('AuthGroupAccess')->saveAll($dataset);


                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('groupdata', $groupdata);
        return $this->view->fetch();
    }

    /**
     * 修改
     * @param null $ids
     * @return string|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit($ids = null)
    {

        $this->model = new AdminModel();
        $departmentModel = new DepartmentModel();
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!\app\admin\model\department\Admin::checkDataAuth($this->auth,$row,'id')){
            $this->error(__('You have no permission'));
        }
        if ($this->request->isPost()) {

            //判断是不是超级管理员编辑
            if (!$this->auth->isSuperAdmin() && DepartmentAdminModel::isSuperAdmin($row->id)) {
                $this->error("您无权操作超级管理员");
            }

            $params = $this->request->post("row/a");

            $invite_code = $params['invite_code'];
            unset($params['invite_code']);
            if($invite_code != '' && strlen($invite_code) != config('system.agent_code_length')){
                $this->error('邀请码只能是6位!!! ');
            }

            if($row->admindata->invite_code != $invite_code){
                $checkInviteCode = db('admin_data')->where('invite_code', $invite_code)->find();
                if($checkInviteCode){
                    $this->error('邀请码已存在!!! ');
                }
            }
            
            if ($params) {
                Db::startTrans();
                try {
                    $department_id = $this->request->post("department_id/a");
                    //获取部门信息
                    if (!$department_id) {
                        exception(__("Department can't null"));
                    }
                    $d_list = $departmentModel->where('id', 'in', $department_id)->select();

                    if (!$d_list) {
                        exception(__("Department can't null"));
                    }

                    if ($params['password']) {
                        if (!Validate::is($params['password'], '\S{6,16}')) {
                            exception(__("Please input correct password"));
                        }
                        $params['salt'] = Random::alnum();
                        $params['password'] = md5(md5($params['password']) . $params['salt']);
                    } else {
                        unset($params['password'], $params['salt']);
                    }
                    if (!$this->auth->isSuperAdmin()&&isset($params['data_scope'])){
                        unset($params['data_scope']);//超级管理人才能赋予全部数据权限
                    }
                    //这里需要针对username和email做唯一验证
                    $adminValidate = \think\Loader::validate('Admin');
                    $adminValidate->rule([
                        'username' => 'require|regex:\w{3,20}|unique:admin,username,' . $row->id,
                        'email' => 'require|email|unique:admin,email,' . $row->id,
                        'mobile'    => 'regex:1[3-9]\d{9}|unique:admin,mobile,' . $row->id,
                        'password' => 'regex:\S{32}',
                    ]);
                    $result = $row->validate('Admin.edit')->save($params);
                    
                    if($params['role'] > 2){
                        $data['invite_code'] = $invite_code;
                    }else{
                        $data['invite_code'] = null;
                    }
                    $row->admindata->save($data);

                    if ($result === false) {
                        exception($row->getError());
                    }
                    $exist_departmentids = $this->dadminModel->where('admin_id', $row->id)->column('department_id');
                    $dadmin = array();
                    $deleteids = array_diff($exist_departmentids, $department_id);
                    //添加部门信息
                    foreach ($d_list as $d_row) {
                        if (!in_array($d_row->id, $exist_departmentids)) {
                            $dadmin[] = ['department_id' => $d_row->id, 'organise_id' => $d_row->organise_id ? $d_row->organise_id : $d_row->id, 'admin_id' => $row->id];
                        }
                    }
                    if ($deleteids) {
                        $this->dadminModel->where('admin_id', $row->id)->where('department_id', 'in', $deleteids)->delete();
                    }
                    if (count($dadmin) > 0) {
                        $this->dadminModel->saveAll($dadmin);
                    }


                    // 先移除所有权限
                    model('AuthGroupAccess')->where('uid', $row->id)->delete();

                    $group = $this->request->post("group/a");
                    // 过滤不允许的组别,避免越权
                    $group = array_intersect($this->childrenGroupIds, $group);
                    if (!$group) {
                        exception(__('The parent group exceeds permission limit'));
                    }

                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $row->id, 'group_id' => $value];
                    }
                    model('AuthGroupAccess')->saveAll($dataset);

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $groupdata = DepartmentModel::getGroupdata(
            $this->childrenGroupIds,
            $this->auth->isSuperAdmin() ? null : $this->auth->getGroups()
        );
        $grouplist = $this->auth->getGroups($row['id']);
        $groupids = [];
        foreach ($grouplist as $k => $v) {
            $groupids[] = $v['id'];
        }
        $this->view->assign("groupids", $groupids);

        $this->view->assign('row', $row);
        $this->view->assign('department_ids', $this->dadminModel->getDepartmentIds($ids));
        $this->view->assign('groupdata', $groupdata);
        return $this->view->fetch();
    }


    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $this->childrenAdminIds = $this->auth->getChildrenAdminIds($this->auth->isSuperAdmin());
        $this->model = new AdminModel();

        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $ids = array_intersect($this->childrenAdminIds, array_filter(explode(',', $ids)));
            // 避免越权删除管理员
            $childrenGroupIds = $this->childrenGroupIds;
            $adminList = $this->model->where('id', 'in', $ids)->where('id', 'in', function ($query) use ($childrenGroupIds) {
                $query->name('auth_group_access')->where('group_id', 'in', $childrenGroupIds)->field('uid');
            })->select();
            if ($adminList) {
                $deleteIds = [];
                foreach ($adminList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                $deleteIds = array_values(array_diff($deleteIds, [$this->auth->id]));
                if ($deleteIds) {
                    Db::startTrans();
                    try {
                        $this->model->destroy($deleteIds);
                        model('AuthGroupAccess')->where('uid', 'in', $deleteIds)->delete();
                        //删除部门员工信息
                        $this->dadminModel->where('admin_id', 'in', $deleteIds)->delete();;

                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success();
                }
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('You have no permission'));
    }

    /**
     * 批量更新
     */
    public function multi($ids = "")
    {
        $this->model = new AdminModel();
        return parent::multi($ids);
    }

    /**
     * 设置部门负责人
     */
    public function principal($ids = "")
    {

        $this->model = new AdminModel();
        $departmentModel = new DepartmentModel();
        $row = $this->model->get($ids);
        if ($this->request->isPost()) {

            //判断是不是超级管理员编辑
            if (!DepartmentAdminModel::isSuperAdmin($this->auth->id) && DepartmentAdminModel::isSuperAdmin($row->id)) {
                $this->error("您无权操作超级管理员");
            }

            $department_id = $this->request->post("department_id/a");
            if ($department_id && $department_id[0]) {
                Db::startTrans();
                try {
                    $d_list = $departmentModel->where('id', 'in', $department_id)->select();

                    if (!$d_list) {
                        exception(__("Department can't null"));
                    }
                    //先移除他所有负责的部门
                    $this->dadminModel->where('admin_id', $row->id)->update(['is_principal' => 0]);

                    //判断选择的部门是否存在，不存在就先把他加入
                    $p_departmentids = array_column($d_list, 'id');
                    $exist_departmentids = $this->dadminModel->where('admin_id', $row->id)->column('department_id');

                    $dadmin = array();
                    //添加部门信息
                    foreach ($d_list as $d_row) {
                        if (!in_array($d_row->id, $exist_departmentids)) {
                            $dadmin[] = ['department_id' => $d_row->id, 'organise_id' => $d_row->organise_id ? $d_row->organise_id : $d_row->id, 'admin_id' => $row->id];
                        }
                    }
                    if (count($dadmin) > 0) {
                        $this->dadminModel->saveAll($dadmin);
                    }
                    //更改为负责人
                    $this->dadminModel->where('admin_id', $row->id)->where('department_id', 'in', $p_departmentids)->update(['is_principal' => 1]);


                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }

            } else {
                //移除他所有负责的部门
                $this->dadminModel->where('admin_id', $row->id)->update(['is_principal' => 0]);
            }
            $this->success();
        }


        $this->view->assign('row', $row);
        $this->view->assign('department_ids', $this->dadminModel::getPrincipalIds($ids));
        return $this->view->fetch();
    }

    /**
     * 获取员工的部门IDs
     */
    public function getDepartmentIds($id = ""){

        $departmentIds=DepartmentAdminModel::getDepartmentIds($id);
        //这里一定要返回有list这个字段,total是可选的,如果total<=list的数量,则会隐藏分页按钮
        $this->success("",'',['departmentIds' => $departmentIds]);
    }

    /**
     * 下拉选择
     * @Internal
     */
    public function selectpage()
    {
        $type=$this->request->request('type');//all代表选择所有
        $custom = ['status' => 'normal'];
        if ($type != "all") {
            $childrenAdminIds = \app\admin\model\department\Admin::getChildrenAdminIds($this->auth->id, true);
            $custom['id'] = ['in', $childrenAdminIds];
        }
        $this->selectpageFields = ['id', 'concat(nickname,"(",id,")") as nickname'];
        $this->request->request(['custom' => $custom]);
        //搜索字段
        $searchfield = (array)$this->request->request("searchField/a");
        $searchfield[]='id';
        $this->request->request(['searchField'=>$searchfield]);
        $this->model = model('admin');

        //设置过滤方法
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);

        //搜索关键词,客户端输入以空格分开,这里接收为数组
        $word = (array)$this->request->request("q_word/a");
        //当前页
        $page = $this->request->request("pageNumber");
        //分页大小
        $pagesize = $this->request->request("pageSize");
        //搜索条件
        $andor = $this->request->request("andOr", "and", "strtoupper");
        //排序方式
        $orderby = (array)$this->request->request("orderBy/a");
        //显示的字段
        $field = $this->request->request("showField");
        //主键
        $primarykey = $this->request->request("keyField");
        //主键值
        $primaryvalue = $this->request->request("keyValue");
        //搜索字段
        $searchfield = (array)$this->request->request("searchField/a");
        //自定义搜索条件
        $custom = (array)$this->request->request("custom/a");
        //是否返回树形结构
        $istree = $this->request->request("isTree", 0);
        $ishtml = $this->request->request("isHtml", 0);
        if ($istree) {
            $word = [];
            $pagesize = 999999;
        }
        $order = [];
        foreach ($orderby as $k => $v) {
            $order[$v[0]] = $v[1];
        }
        $field = $field ? $field : 'name';

        //如果有primaryvalue,说明当前是初始化传值
        if ($primaryvalue !== null) {
            $where = [$primarykey => ['in', $primaryvalue]];
            $pagesize = 999999;
        } else {
            $where = function ($query) use ($word, $andor, $field, $searchfield, $custom) {
                $logic = $andor == 'AND' ? '&' : '|';
                $searchfield = is_array($searchfield) ? implode($logic, $searchfield) : $searchfield;
                $searchfield = str_replace(',', $logic, $searchfield);
                $word = array_filter(array_unique($word));
                if (count($word) == 1) {
                    $query->where($searchfield, "like", "%" . reset($word) . "%");
                } else {
                    $query->where(function ($query) use ($word, $searchfield) {
                        foreach ($word as $index => $item) {
                            $query->whereOr(function ($query) use ($item, $searchfield) {
                                $query->where($searchfield, "like", "%{$item}%");
                            });
                        }
                    });
                }
                if ($custom && is_array($custom)) {
                    foreach ($custom as $k => $v) {
                        if (is_array($v) && 2 == count($v)) {
                            $query->where($k, trim($v[0]), $v[1]);
                        } else {
                            $query->where($k, '=', $v);
                        }
                    }
                }
            };
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $list = [];
        $total = $this->model->where($where)->count();
        if ($total > 0) {
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }

            $fields = is_array($this->selectpageFields) ? $this->selectpageFields : ($this->selectpageFields && $this->selectpageFields != '*' ? explode(',', $this->selectpageFields) : []);

            //如果有primaryvalue,说明当前是初始化传值,按照选择顺序排序
            if ($primaryvalue !== null && preg_match("/^[a-z0-9_\-]+$/i", $primarykey)) {
                $primaryvalue = array_unique(is_array($primaryvalue) ? $primaryvalue : explode(',', $primaryvalue));
                //修复自定义data-primary-key为字符串内容时，给排序字段添加上引号
                $primaryvalue = array_map(function ($value) {
                    return '\'' . $value . '\'';
                }, $primaryvalue);

                $primaryvalue = implode(',', $primaryvalue);

                $this->model->orderRaw("FIELD(`{$primarykey}`, {$primaryvalue})");
            } else {
                $this->model->order($order);
            }

            $datalist = $this->model->where($where)
                ->field($this->selectpageFields)
                ->page($page, $pagesize)
                ->select();

            foreach ($datalist as $index => $item) {
                unset($item['password'], $item['salt']);
                if ($this->selectpageFields == '*') {
                    $result = [
                        $primarykey => isset($item[$primarykey]) ? $item[$primarykey] : '',
                        $field => isset($item[$field]) ? $item[$field] : '',
                    ];
                } else {
                    $result = (($item instanceof Model ? $item->toArray() : (array)$item));
                }
                $result['pid'] = isset($item['pid']) ? $item['pid'] : (isset($item['parent_id']) ? $item['parent_id'] : 0);
                $list[] = $result;
            }

            if ($istree && !$primaryvalue) {
                $tree = Tree::instance();
                $tree->init(collection($list)->toArray(), 'pid');
                $list = $tree->getTreeList($tree->getTreeArray(0), $field);
                if (!$ishtml) {
                    foreach ($list as &$item) {
                        $item = str_replace('&nbsp;', ' ', $item);
                    }
                    unset($item);
                }
            }
        }
        //这里一定要返回有list这个字段,total是可选的,如果total<=list的数量,则会隐藏分页按钮
        return json(['list' => $list, 'total' => $total]);
    }

    /**
     * 修改额度
     */
    public function quota()
    {
        $params = $this->request->param();

        $adminModel = new AdminModel();

        $row = $adminModel->get($params['ids']);
        
        if(!$row){
            $this->error(__('No Results were found'));
        }

        $row->admindata->quota = $params['quota'];

        $row->admindata->save();

        $this->success(__('Operation completed'));
    }

    /**
     * 发送测试飞机号可用?
     */
    public function send()
    {
        $chat_id = $this->request->post('chat_id');

        $message = '收到这条消息, 代表当前飞机号可用';
        $params = [
            'chat_id' => $chat_id,
            'text'    => $message,
        ];

        $apiUrl = "https://api.telegram.org/bot7120074308:AAGKWlR5XQ0MySxca2vup1MmMYW3mJ8vUjU/sendMessage";

        // 发送消息
        $res = Notice::send($apiUrl, $params);
        $res = json_decode($res, true);
        if($res['ok']){
            $this->success(__('发送成功'));
        }
        $this->error(__('发送失败, 当前不可用') . '<br>' . $res['description'] ?? '');
    }
}
