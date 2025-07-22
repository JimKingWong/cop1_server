<?php

namespace app\admin\controller\platform;

use app\admin\model\game\Cases;
use app\admin\model\platform\Windowcases;
use app\common\controller\Backend;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 域名配置
 *
 * @icon fa fa-circle-o
 */
class Site extends Backend
{

    /**
     * Site模型对象
     * @var \app\admin\model\platform\Site
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\platform\Site;
        $this->view->assign("statusList", $this->model->getStatusList());

        $casesList = Cases::where('status', 1)->order('weigh desc')->field('id,title name,is_default')->select();
        $default_id = 0;
        foreach($casesList as $v){
            if($v->is_default == 1){
                $default_id = $v->id;
            }
        }
        $this->assign('casesList', json_encode($casesList));
        $this->assign('default_id', $default_id);

        $windowcases = Windowcases::where('status', 1)->order('id desc')->field('id,name,is_default')->select();
        $window_default_id = 0;
        foreach($windowcases as $v){
            if($v->is_default == 1){
                $window_default_id = $v->id;
            }
        }
        
        $this->assign('window_default_id', $window_default_id);
        $this->assign('windowcases', json_encode($windowcases));
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

      /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with(['cases'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        foreach($list as $row){
            // $row->cases->visible(['name']);
            $row->url = 'https://' . $row->url;
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function workbench()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        if($this->auth->role < 3){
            // return json(['total' => 0, 'rows' => []]);
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $invite_code = db('admin_data')->where('admin_id', $this->auth->id)->value('invite_code');
        $list = $this->model
            ->with(['cases'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        foreach($list as $row){
            // $row->cases->visible(['name']);
            if($invite_code){
                $row->url = 'https://' . $row->url .'/?invite_code='. $invite_code;
            }
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            $this->modelValidate = true;
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
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
        $result = false;
        Db::startTrans();
        try {
            $this->modelValidate = true;

            //是否采用模型验证
            if ($this->modelValidate && $row->name != $params['name']) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
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

}
