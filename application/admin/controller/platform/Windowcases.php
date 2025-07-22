<?php

namespace app\admin\controller\platform;

use app\admin\model\platform\Window;
use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Windowcases extends Backend
{

    /**
     * Windowcases模型对象
     * @var \app\admin\model\platform\Windowcases
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\platform\Windowcases;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isDefaultList", $this->model->getIsDefaultList());

        $window = Window::where('status', 1)->order('id desc')->field('id,title name')->select();
        $this->view->assign('window', json_encode($window));
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
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
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

        $this->success('成功设置 ('. $row['name'] .') 为默认方案');
    }
}
