<?php

namespace app\admin\controller\report;

use app\common\controller\Backend;

/**
 * 管理员统计管理
 *
 * @icon fa fa-circle-o
 */
class Daybookbl extends Backend
{
    protected $dataLimit = 'department';
    /**
     * Daybookbl模型对象
     * @var \app\admin\model\report\Daybookbl
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\report\Daybookbl;

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
                    ->with(['user','admin'])
                    ->where($where)
                    ->order($sort, $order);
             // 先赋值
            $daybook = $list;

            $list = $list->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['username']);
				$row->getRelation('admin')->visible(['username']);
            }

            $recharge = 0;
            $withdraw = 0;
            $api = 0;
            $channel = 0;
            $profit = 0;
            $daybook = $daybook->where($where)->select();
            foreach($daybook as $row){
                $recharge += $row->recharge_amount;
                $withdraw += $row->withdraw_amount;
                $api += $row->api_amount;
                $channel += $row->channel_fee;
                $profit += $row->profit_and_loss;
            }
            
            $extend = [
                'recharge' => sprintf('%.2f', $recharge),
                'withdraw' => sprintf('%.2f', $withdraw),
                'api' => sprintf('%.2f', $api),
                'channel' => sprintf('%.2f', $channel),
                'profit' => sprintf('%.2f', $profit),
            ];

            $result = array("total" => $list->total(), "rows" => $list->items(), 'extend' => $extend);

            return json($result);
        }
        return $this->view->fetch();
    }

}
