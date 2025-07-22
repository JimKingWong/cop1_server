<?php

namespace app\admin\controller\game;

use app\admin\model\game\Cate;
use app\common\controller\Backend;

use think\Db;
use Exception;
use fast\Tree;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 游戏方案
 *
 * @icon fa fa-circle-o
 */
class Cases extends Backend
{

    /**
     * Cases模型对象
     * @var \app\admin\model\game\Cases
     */
    protected $model = null;
    protected $dataLimit = 'auth';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\game\Cases;
        $this->view->assign("statusList", $this->model->getStatusList());

        $tree = Tree::instance();
        
        $list = Cate::where('status', 1)->where('pid', 0)->order('weigh', 'desc')->select();
        $tree->init($list, 'pid');
        $cateList = $tree->getTreeList($tree->getTreeArray(0), 'name');
        foreach($cateList as $v){
            $v['name']=preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", " ", strip_tags($v['name'])); //过滤空格
            $v->remark = $v->remark ? $v->remark : '';
        }
        $cateList = json_encode(array_values($cateList));
        
        $this->assign("cateList", $cateList);
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['site'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->visible(['id','title','status','weigh','createtime','updatetime', 'remark', 'game_cate_ids', 'is_default']);
                $row->visible(['site']);
				$row->getRelation('site')->visible(['abbr']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
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
            //是否采用模型验证
            if ($this->modelValidate) {
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

    /**
     * 设为默认方案
     */
    public function setDefault($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if($row->status == 0){
            $this->error('请先启用方案');
        }

        $list = $this->model->select();

        $count = 0;
        foreach($list as $val){
            if($val->id == $ids){
                $val->is_default = 1;
            }else{
                $val->is_default = 0;
            }
            $count += $val->save();
        }

        $this->success('成功设置 ('. $row['title'] .') 为默认方案');
    }
}
