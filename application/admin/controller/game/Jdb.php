<?php

namespace app\admin\controller\game;

use app\common\controller\Backend;
use fast\Tree;
use app\admin\model\game\Cate;
use app\admin\model\game\Games;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 游戏列管理
 *
 * @icon fa fa-circle-o
 */
class Jdb extends Backend
{

    /**
     * Jdb模型对象
     * @var \app\admin\model\game\Jdb
     */
    protected $model = null;
    protected $tree = null;
    protected $cateList = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\game\Jdb;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isWorksList", $this->model->getIsWorksList());

        $platformList = $this->model->getPlatform();
        $arr = [];
        $k = 0;
        foreach($platformList as $k => $v){
            $arr[$k]['id'] = $k;
            $arr[$k]['name'] = $v;
        }
        $arr = array_values($arr);
        $this->view->assign("platformList", json_encode($arr));

        $this->tree = Tree::instance();
        
        $list = Cate::where('status', 1)->select();
        $this->tree->init($list, 'pid');
        $this->cateList = $this->tree->getTreeList($this->tree->getTreeArray(0), 'name');
        $this->view->assign("cateList", $this->cateList);
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
      * 归类游戏
      */
    public function patchadd($ids = null)
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $g_ids = Games::where('cate_id', $params['cate_id'])->where('table_name', 'game_jdb')->column('g_id');
        
        $ids = explode(',', $ids);
        $ids = array_diff($ids, $g_ids);

        $list = $this->model->where('id', 'in', $ids)->where('image', 'not null')->select();
        
        $data = [];
        foreach($list as $v){
            $data[] = [
                'cate_id'           => $params['cate_id'],
                'g_id'              => $v->id,
                'table_name'        => 'game_jdb',
                'game_id'           => $v->game_id,
                'game_name'         => $v->game_name,
                'dir'               => $v->dir,
                'image'             => $v->image,
                'thumb'             => $v->thumb,
                'bet_rate'          => $v->bet_rate,
                'type'              => $v->type,
                'game_type'         => $v->game_type,
                'status'            => (string)$v->status,
                'is_works'          => $v->is_works,
                'weigh'             => $v->weigh,
                'createtime'        => datetime(time())
            ];
        }
        
        if(empty($data)){
            $this->error(__('当前选择的游戏已全部归类到该分类下! '));
        }

        $result = Games::insertAll($data);
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        } 

        $count = count($ids);
        $this->success(__('归类成功! 数量为: %s', $count));
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
}
