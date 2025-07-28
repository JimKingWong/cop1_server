<?php

namespace app\admin\controller\game;

use app\common\controller\Backend;
use app\common\service\util\Es;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Summary extends Backend
{
    protected $noNeedRight = ['omgsummary', 'jdbsummary'];

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
    public function omgsummary()
    {
        ini_set('memory_limit', '1024M');
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

            $fieldArr = ['platform', 'game_id'];
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
            
            $res = $service->groupAggregation('omg_game_record', $condition, 'game_id', ['win_amount', 'bet_amount', 'transfer_amount']);

            // 获取当前页数据
            $items = array_slice($res, $offset, $limit);

            $omg = new \app\common\model\game\Omg();
            $omgList = $omg->column('platform,game_name,image', 'game_id');

            $platformArr = $omg->getPlatform();
            foreach($omgList as $key => $val){
                $omgList[$key]['platform'] = $platformArr[$omgList[$key]['platform']] ?? 'PG';
            }

            $list = [];
            $total_bet_amount = 0;
            $total_win_amount = 0;
            $total_transfer_amount = 0;
            $total_bet_count = 0;
            foreach($items as $key => $val){
                $list[$key]['game_id'] = $val['key'];
                $list[$key]['game_name'] = $omgList[$val['key']]['game_name'] ?? '';
                $list[$key]['platform'] = $omgList[$val['key']]['platform'] ?? 'PG';
                $list[$key]['image'] = $omgList[$val['key']]['image'] ? cdnurl($omgList[$val['key']]['image']) : '';
                $list[$key]['win_amount'] = sprintf('%.2f', $val['win_amount_sum']);
                $list[$key]['bet_amount'] = sprintf('%.2f', $val['bet_amount_sum']);
                $list[$key]['bet_count'] = $val['doc_count']['value'];
                $list[$key]['transfer_amount'] = sprintf($val['bet_amount_sum'] - $val['win_amount_sum']);
                $list[$key]['rtp_rate'] = ($val['bet_amount_sum'] > 0 ? sprintf('%.2f', $val['win_amount_sum'] / $val['bet_amount_sum'] * 100) : 0) . '%';

                $total_bet_amount += $val['bet_amount_sum'];
                $total_win_amount += $val['win_amount_sum'];
                $total_transfer_amount += $val['bet_amount_sum'] - $val['win_amount_sum'];
                $total_bet_count += $val['doc_count']['value'];
            }
            // dd($res);
            
            $extend = [
                'bet_amount' => sprintf('%.2f', $total_bet_amount),
                'win_amount' => sprintf('%.2f', $total_win_amount),
                'transfer_amount' => sprintf('%.2f', $total_transfer_amount),
                'bet_count' => $total_bet_count, // 总投注次数
            ]; // 扩展字段
            $result = ['total' => count($res), 'rows' => $list, 'extend' => $extend];
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
    public function jdbsummary()
    {
        ini_set('memory_limit', '1024M');
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

        $fieldArr = ['platform', 'game_id'];
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
        
        $res = $service->groupAggregation('jdb_game_record', $condition, 'game_id', ['win_amount', 'bet_amount', 'transfer_amount']);

        // 获取当前页数据
        $items = array_slice($res, $offset, $limit);

        $jdb = new \app\common\model\game\Jdb();
        $jdbList = $jdb->column('platform,game_name,image', 'game_id');

        $platformArr = $jdb->getPlatform();
        foreach($jdbList as $key => $val){
            $jdbList[$key]['platform'] = $platformArr[$jdbList[$key]['platform']] ?? 'JDB';
        }

        $list = [];
        $total_bet_amount = 0;
        $total_win_amount = 0;
        $total_transfer_amount = 0;
        $total_bet_count = 0;
        foreach($items as $key => $val){
            $list[$key]['game_id'] = $val['key'];
            $list[$key]['game_name'] = $jdbList[$val['key']]['game_name'] ?? '';
            $list[$key]['platform'] = $jdbList[$val['key']]['platform'] ?? 'JDB';
            $list[$key]['image'] = $jdbList[$val['key']]['image'] ? cdnurl($jdbList[$val['key']]['image']) : '';
            $list[$key]['win_amount'] = sprintf('%.2f', $val['win_amount_sum']);
            $list[$key]['bet_amount'] = sprintf('%.2f', $val['bet_amount_sum']);
            $list[$key]['bet_count'] = $val['doc_count']['value'];
            $list[$key]['transfer_amount'] = sprintf($val['bet_amount_sum'] - $val['win_amount_sum']);
            $list[$key]['rtp_rate'] = ($val['bet_amount_sum'] > 0 ? sprintf('%.2f', $val['win_amount_sum'] / $val['bet_amount_sum'] * 100) : 0) . '%';

            $total_bet_amount += $val['bet_amount_sum'];
            $total_win_amount += $val['win_amount_sum'];
            $total_transfer_amount += $val['bet_amount_sum'] - $val['win_amount_sum'];
            $total_bet_count += $val['doc_count']['value'];
        }
        // dd($list);
        $extend = [
            'bet_amount' => sprintf('%.2f', $total_bet_amount),
            'win_amount' => sprintf('%.2f', $total_win_amount),
            'transfer_amount' => sprintf('%.2f', $total_transfer_amount),
            'bet_count' => $total_bet_count, // 总投注次数
        ]; // 扩展字段
        $result = ['total' => count($res), 'rows' => $list, 'extend' => $extend];
        return json($result);
    }

}
