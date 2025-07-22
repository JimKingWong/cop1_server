<?php

namespace app\admin\controller\game;

use app\common\controller\Backend;
use app\common\service\util\Es;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Record extends Backend
{
    protected $noNeedRight = ['omgrecord', 'jdbrecord'];

    public function _initialize()
    {
        parent::_initialize();
       
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
        return $this->view->fetch();
    }

    /**
     * omg游戏记录
     */
    public function omgrecord()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
       
        if($this->request->isAjax()){
            $filter = $this->request->get("filter", '');
            $filter = json_decode($filter, true);

            // 后台所有部门的id
            $adminIds = \app\admin\model\department\Admin::getChildrenAdminIds($this->auth->id, true);
            if($this->auth->role < 2){
                array_push($adminIds, 0);
            }
            
            $condition = [
                // 必要条件
                [
                    'type' => 'terms',
                    'field' => 'admin_id',
                    'value' =>  $adminIds,
                ],
            ];

            $fieldArr = ['platform', 'transaction_id', 'game_id', 'user_id'];
            foreach($fieldArr as $val){
                if(isset($filter[$val]) && $filter[$val] != ''){
                    $condition[] = [
                        'type' => 'term',
                        'field' => $val,
                        'value' =>  $filter[$val],
                    ];
                }
            }

            if(isset($filter['createtime']) && $filter['createtime'] != ''){
                list($starttime, $endtime) = explode(' - ', $filter['createtime']);
                $condition[] = [
                    'type' => 'range',
                    'field' => 'createtime',
                    'value' => [
                        'gte' => strtotime($starttime),
                        'lte' => strtotime($endtime),
                    ]       
                ];
            }
        
            // dd($condition);
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $service = new Es();
            
            $list = $service->record('omg_game_record', $condition, $offset, $limit);
            foreach($list['list'] as $key => $val){
                $list['list'][$key]['createtime'] = datetime($val['createtime']);
            }
            
            $result = ['total' => $list['total'], 'rows' => $list['list']];
            return json($result);
        }
        return $this->view->fetch('index');
    }

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function jdbrecord()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }

        $filter = $this->request->get("filter", '');
        $filter = json_decode($filter, true);

        // 后台所有部门的id
        $adminIds = \app\admin\model\department\Admin::getChildrenAdminIds($this->auth->id, true);
        
        $condition = [
            // 必要条件
            [
                'type' => 'terms',
                'field' => 'admin_id',
                'value' =>  $adminIds,
            ],
        ];

        $fieldArr = ['platform', 'transaction_id', 'game_id', 'user_id'];
        foreach($fieldArr as $val){
            if(isset($filter[$val]) && $filter[$val] != ''){
                $condition[] = [
                    'type' => 'term',
                    'field' => $val,
                    'value' =>  $filter[$val],
                ];
            }
        }

        if(isset($filter['createtime']) && $filter['createtime'] != ''){
            list($starttime, $endtime) = explode(' - ', $filter['createtime']);
            $condition[] = [
                'type' => 'range',
                'field' => 'createtime',
                'value' => [
                    'gte' => strtotime($starttime),
                    'lte' => strtotime($endtime),
                ]       
            ];
        }
      
        // dd($condition);
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();

        $service = new Es();
        
        $list = $service->record('jdb_game_record', $condition, $offset, $limit);
        foreach($list['list'] as $key => $val){
            $list['list'][$key]['createtime'] = datetime($val['createtime']);
        }
        
        $result = ['total' => $list['total'], 'rows' => $list['list']];
        return json($result);
    }

}
