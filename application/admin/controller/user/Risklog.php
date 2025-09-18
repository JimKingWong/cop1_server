<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Risklog extends Backend
{
    protected $dataLimit = 'department'; // 部门数据权限

    /**
     * Risklog模型对象
     * @var \app\admin\model\user\Risklog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Risklog;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isPassList", $this->model->getIsPassList());
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

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['username', 'origin']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 查看
     */
    public function detail($task_id = null)
    {
        $task = \app\admin\model\user\Risk::get($task_id);

        $list = $this->model->where('task_id', $task_id)->order('id asc')->select();

        $arr = [];
        $tab = [];
        foreach($list as $key => $val){
            $val->result = json_decode($val->result, true);
            $arr[$val['method']][] = $val->toarray();
            $tab[$val['method']] = $val['method_intro'];
        }
        // dd($arr);
        
        $data = [
            'checkSameIPGroups' => [],
            'checkQuickWithdrawAfterBet' => [],
            'checkSameWithdrawPassword' => [],
            'checkHighWithdrawRate' => [],
            'checkSharedWithdrawInfo' => [],
            'checkQuickDepositWithdraw' => [],
            'checkSameDepositAmount' => [],
            'checkSameDepositCpf' => [],
        ];
        foreach($arr as $key => $val){
            
            if($key == 'checkSameIPGroups'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        foreach($v['result'] as $row){
                            $result_str .= "<b>检测结果: </b>";
                            // $result_str .= "<p>IP: {$row['joinip']} 当前站点注册了{$row['count']}次</p>";
                            $user_ids = implode(',', db('user')->where('origin', $task['user']['origin'])->whereIn('id', $row['user_ids'])->column('id'));
                            $result_str .= "<p>IP: {$row['joinip']} 当前站点注册了{$row['count']}次, 用户id为: {$user_ids}</p>";
                            // $result_str .= "<p>IP: {$row['joinip']} 当前站点注册了{$row['count']}次, 全站一样用户id为: {$row['user_ids']}</p>";
                        }

                        $data['checkSameIPGroups'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkQuickWithdrawAfterBet'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        foreach($v['result'] as $row){
                            $result_str .= "<b>检测结果: </b>";
                            $result_str .= "<p>用户ID: {$row['user_id']} 提现单号: {$row['order_no']}, 代付时间: {$row['paytime']}, 注册时间: {$row['createtime']}</p>";
                        }

                        $data['checkQuickWithdrawAfterBet'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkSameWithdrawPassword'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        foreach($v['result'] as $row){
                            $result_str .= "<b>检测结果: </b>";
                            $user_ids = implode(',', db('user')->where('origin', $task['user']['origin'])->whereIn('id', $row['user_ids'])->column('id'));

                            $result_str .= "<p>同一支付密码: {$row['pay_password']} 当前站点使用了{$row['count']}次</p>";
                            // $result_str .= "<p>同一支付密码: {$row['pay_password']} 当前站点使用了{$row['count']}次, 用户id为: {$user_ids}</p>";
                            // $result_str .= "<p>同一支付密码: {$row['pay_password']} 当前站点使用了{$row['count']}次, 全站使用的用户id为: {$row['user_ids']}</p>";
                        }

                        $data['checkSameWithdrawPassword'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkHighWithdrawRate'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                       
                        $result_str .= "<b>检测结果: </b>";
                        $result_str .= "<p>总用户: {$v['result']['total_users']} 提现用户数: {$v['result']['withdraw_users']}次, 提现率: {$v['result']['rate']}%</p>";

                        $data['checkHighWithdrawRate'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkSharedWithdrawInfo'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        foreach($v['result'] as $row){
                            $result_str .= "<b>检测结果: </b>";
                            $result_str .= "<p>同一CPF: {$row['pix']} 当前站点使用该CPF{$row['count']}人</p>";
                            $user_ids = implode(',', db('user')->where('origin', $task['user']['origin'])->whereIn('id', $row['user_ids'])->column('id'));

                            $result_str .= "<p>同一CPF: {$row['pix']} 当前站点使用该CPF{$row['count']}人, 用户id为: {$user_ids}</p>";
                            // $result_str .= "<p>同一CPF: {$row['pix']} 当前站点使用该CPF{$row['count']}人, 全站使用的用户id为: {$row['user_ids']}</p>";
                        }

                        $data['checkSharedWithdrawInfo'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkQuickDepositWithdraw'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        
                        // foreach($v['result'] as $row){
                            
                        //     $result_str .= "<b>检测结果: </b>";
                        //     $result_str .= "<p>充值用户数: {$row['recharge_users']} 提现用户数: {$row['withdraw_users']}人, 比例: {$row['cur_rate']}</p>";
                        // }
                        
                        $result_str .= "<b>检测结果: </b>";
                        $result_str .= "<p>充值用户数: {$v['result']['recharge_users']} 提现用户数: {$v['result']['withdraw_users']}人, 比例: {$v['result']['cur_rate']}%</p>";
                        
                        $data['checkQuickDepositWithdraw'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkSameDepositAmount'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        foreach($v['result'] as $row){
                            $count = count($row);
                            $result_str .= "<b>检测结果: </b>";
                            $result_str .= "<p>人数共: {$count}人</p>";

                        }

                        $data['checkSameDepositAmount'][] = $result_str;
                    }
                    
                }
            }

            if($key == 'checkSameDepositCpf'){
                foreach($val as $k => $v){
                    $result_str = '';
                    $result_str .= "<p class='text-red'>第" . ($k + 1) . "次检测: {$v['result_intro']} 检测时间: {$v['updatetime']}</p>";
                    
                    if($v['result']){
                        foreach($v['result'] as $row){
                            $result_str .= "<b>检测结果: </b>";
                            $result_str .= "<p>同一CPF: {$row['cpf']} 当前站点使用该CPF{$row['count']}人</p>";
                            $user_ids = implode(',', db('user')->where('origin', $task['user']['origin'])->whereIn('id', $row['user_ids'])->column('id'));

                            $result_str .= "<p>同一CPF: {$row['cpf']} 当前站点使用该CPF{$row['count']}人, 用户id为: {$user_ids}</p>";
                            // $result_str .= "<p>同一CPF: {$row['cpf']} 当前站点使用该CPF{$row['count']}人, 全站使用的用户id为: {$row['user_ids']}</p>";
                        }

                        $data['checkSameDepositCpf'][] = $result_str;
                    }
                }
            }
        }
        // dd($data);
        // dd($list->toarray());
        $this->assign('data', $data);
        $this->assign('tab', $tab);
        return $this->view->fetch();
    }
}
