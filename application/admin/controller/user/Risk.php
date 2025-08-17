<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Risk extends Backend
{
    protected $dataLimit = 'department'; // 部门数据权限

    protected $noNeedLogin = ['multi'];

    /**
     * Risk模型对象
     * @var \app\admin\model\user\Risk
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Risk;

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
                    ->with(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $arr = db('risk_task_log')->where('is_pass', 0)->where('status', 1)->column('task_id');
            $arr = array_unique($arr);
            // dd($arr);
            foreach ($list as $row) {
                // db('risk_task')->where('id', $row['id'])->setInc('num');
                // db('risk_task')->where('id', $row['id'])->update(['lasttime' => datetime(time())]);
                // db('risk_task_log')->where('task_id', $row['id'])->where('is_pass', 0)->find()
                $row->is_problem = 0;
                if (in_array($row['id'], $arr)) {
                    $row->is_problem = 1;
                }
                $row->getRelation('user')->visible(['username', 'origin']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
