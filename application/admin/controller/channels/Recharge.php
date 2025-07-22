<?php

namespace app\admin\controller\channels;

use app\admin\model\channels\Channel;
use app\common\controller\Backend;

/**
 * 充值管理
 *
 * @icon fa fa-circle-o
 */
class Recharge extends Backend
{

    /**
     * Recharge模型对象
     * @var \app\admin\model\channels\Recharge
     */
    protected $model = null;
    protected $dataLimit = 'department'; // 部门数据权限

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\channels\Recharge;
        $this->view->assign("statusList", $this->model->getStatusList());
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
        ini_set('memory_limit', '256M');
        
        //当前是否为关联查询
        $this->relationSearch = true;
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

            $channel = Channel::column('title,name', 'id');
            $admindata = db('admin_data')->column('invite_code', 'admin_id');
            $list = $this->model
                    ->with(['user'])
                    // ->with(['user','channel'])
                    ->where($where)
                    ->where($map)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->channel_name = $channel[$row->channel_id]['name'] ?? '';
                $row->channel_title = $channel[$row->channel_id]['title'] ?? '';
                $row->root_invite = $admindata[$row->admin_id] ?? '';
            }
       
             $recharge = $this->model
                ->with(['user'])
                ->where($where)
                ->where($map)
                ->select();

            $total_recharge = 0; // 总充值金额
            $total_recharge_num = 0; // 总充值笔数
            $success_recharge = 0; // 成功充值笔数

            $today_recharge = 0; // 今日充值金额
            $today_recharge_num = 0; // 今日充值笔数
            $today_success_recharge = 0; // 今日成功充值笔数

            $yestoday_recharge = 0; // 昨日充值金额
            $yestoday_recharge_num = 0; // 昨日充值笔数
            $yestoday_success_recharge = 0; // 昨日成功充值笔数

            $today_time = strtotime(date('Ymd'));
            $yestoday_time = strtotime(date('Ymd', strtotime('-1 day')));
            
            foreach ($recharge as $row) {
                $total_recharge_num ++;
                if ($row->status == 1) {
                    $total_recharge += $row->money;
                    $success_recharge ++;
                }

                // 今日
                if(strtotime($row->paytime) >= $today_time){
                    if($row->status == 1){
                        $today_recharge += $row->money;
                        $today_success_recharge ++;
                    }
                }

                if(strtotime($row->createtime) >= $today_time){
                    $today_recharge_num ++;
                }

                // 昨日
                if(strtotime($row->paytime) >= $yestoday_time && strtotime($row->paytime) < $today_time){
                    if($row->status == 1){
                        $yestoday_recharge += $row->money;
                        $yestoday_success_recharge ++;
                    }
                }

                if(strtotime($row->createtime) >= $yestoday_time && strtotime($row->createtime) < $today_time){
                    $yestoday_recharge_num ++;
                }
            }
            $retval = [
                'total_recharge'            => sprintf('%.2f', $total_recharge),
                'total_recharge_num'        => $total_recharge_num,
                'success_recharge'          => $success_recharge,
                'today_recharge'            => sprintf('%.2f', $today_recharge),
                'today_recharge_num'        => $today_recharge_num,
                'today_success_recharge'    => $today_success_recharge,
                'yestoday_recharge'         => sprintf('%.2f', $yestoday_recharge),
                'yestoday_recharge_num'     => $yestoday_recharge_num,
                'yestoday_success_recharge' => $yestoday_success_recharge,
            ];
            $result = array("total" => $list->total(), "rows" => $list->items(), 'retval' => $retval);

            return json($result);
        }
        return $this->view->fetch();
    }

}
