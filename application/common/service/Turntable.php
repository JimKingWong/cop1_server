<?php

namespace app\common\service;

use app\common\controller\Api;
use app\common\model\TurntableLog;
use app\common\model\User;
use Exception;
use fast\Random;
use think\cache\driver\Redis;
use think\Db;

/**
 * 转盘抽奖
 */
class Turntable extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    protected $model = null;
    protected $logModel = null;

    public function __construct()
    {
        parent::__construct();

        $this->model = new \app\common\model\Turntable();
        $this->logModel = new TurntableLog();
    }

    /**
     * 初始化数据
     */
    public function init()
    {
        // 转盘列表
        $where['status'] = 1;
        $list = $this->model::list($where);

        // 转盘按钮
        $button = $this->model->getWheelList();
        foreach($button as $key => $val){
            unset($button[$key]['range']);
        }

        $today_bet = 0;
        $num = 0;
        if($this->auth->id){
            $user = $this->auth->getUser();

            $today_bet = $user->userdata->today_bet;

            $num = $this->curNum() < 0 ? 0 : $this->curNum(); // 当前次数
        }
        
        $need_bet = $button['silver']['need'] - $today_bet % $button['silver']['need'];

        $retval = [
            'num'               => $num,
            'today_bet'         => $today_bet,
            'need_bet'          => $need_bet,
            'list'              => $list,
            'button'            => $button,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 转盘记录
     */
    public function record()
    {
        $type = $this->request->post('type', 'silver'); // 暂时不需要传参
        if(!$type){
            $this->error(__('无效参数'));
        }

        $flag = $this->request->post('flag', 0); // 0 1
        
        if($flag != 0){
            $log = new TurntableLog();
            $table = $log->getTable();
    
            $where = [];
            if($flag){
                $where[$table . '.user_id'] = $this->auth->id;
            }
            
            $record = $log
                    ->where($where)
                    ->with('user')
                    ->order('id desc')
                    ->select()->each(function($item){
                        $item->weigh = $item->createtime;
                        $item->createtime = date('m/d H:i', strtotime($item->createtime));
                        $item->memo = $this->dealUsername($item->nickname) . ' ' . __('赢得轮盘抽奖');
                        $item->money = sprintf('%.2f', $item->money);
    
                        $item->visible(['money', 'createtime', 'memo']);
                    });
        }else{
            $record = $this->faultData();
            
        }
       
        $retval = [
            'record'    => $record,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 假数据
     */
    public function faultData()
    {
        $user = db('user')->orderRaw("rand()")->limit(20)->field('nickname')->select();
        
        // 时间
        $end_time = time();
        $start_time = time() - 2 * 3600;

        $arr = $this->model->where('money', '>', 50)->column('name');
        // dd($time);
        $data = [];
        foreach($user as $key => $val){
            $time = mt_rand($start_time, $end_time);
            $data[$key]['weigh'] = $time;
            $data[$key]['money'] = $arr[mt_rand(0, count($arr) - 1)];
            $data[$key]['createtime'] = date('m/d H:i', $time);
            $data[$key]['memo'] = $this->dealUsername($val['nickname']) . ' ' . __('赢得轮盘抽奖');
        }

        array_multisort(array_column($data, 'weigh'), SORT_DESC, $data);

        return $data;
    }

    /**
     * 转动
     */
    public function turn()
    {
        $type = $this->request->post('type', 'silver'); // 暂时不传参
        if(!$type){
            $this->error('type is required');
        }

        $user = $this->auth->getUser();
        
        $button = $this->model->getWheelList();

        // 检查下注情况
        if($user->userdata->today_bet < $button[$type]['need']){
            $this->error(__('今日投注金额不足'));
        }

        // 检查次数
        $num = $this->curNum();
        if($num <= 0){
            $this->error(__('抽奖次数不足'));
        }
        
        // 中奖范围
        $range = $button[$type]['range'];

        $where['status'] = 1;
        // 中奖范围为0时, 不限制
        if($range){
            $where['money'] = ['<', $range];
        }
        $list = $this->model::list($where, 'id,name,odds*10000 as odds,money,left,num');

        $ps = [];
        $info = []; // 中奖信息
        foreach($list as $val){
            $ps[$val['id']] = $val['odds'];

            $info[$val['id']]= $val;
        }

        // 调用抽奖函数, 返回中奖id
        $id = Random::lottery($ps);
        
        // 详细信息
        $turntable = $info[$id];
        // 如果数量为0, 默认抽到第一个
        if($turntable->left <= 0){
            $turntable = $info[1];
        }
     
        $result = false;
     
        Db::startTrans();
        try{
            $user = User::lock(true)->find($user->id);
            
            // 转盘数据更新
            $turntable->left -= 1;
            $turntable->num  += 1;
            $result = $turntable->save();

            // 数据准备
            $reward_data = [
                'lottery' => [
                    'money'                 => $turntable->money,
                    'typing_amount_limit'   => 0,
                    'transaction_id'        => $turntable->id, // 记录表id
                    'status'                => 1,
                ],
            ];
            
            // 插入余额变动日志, 以及奖励日志
            if(!User::insertLog($user, $reward_data)){
                $result = false;
            }

            // 插入抽奖记录
            if(!$this->logModel->save([
                'user_id'           => $user->id, 
                'turntable_id'      => $turntable->id, 
                'money'             => $turntable->money, 
                'today_bet'         => $button[$type]['need'],
                'type'              => $type, 
            ])){
                $result = false;
            }

            if($result != false){
                Db::commit();
            }

        }catch(Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
   
        if(!$result){
            $this->error(__('未知错误'));
        }

        // 删除隐秘信息
        unset($turntable['odds']);
        unset($turntable['left']);
        unset($turntable['num']);

         $retval = [
            'turntable' => $turntable,
            'num'       => $num - 1, // 剩余次数
            'info'      => [
                'memo'          => $this->dealUsername($user->nickname) . ' ' . __('赢得轮盘抽奖'),
                'money'         => $this->logModel->money,
                'createtime'    => date('m/d H:i', strtotime($this->logModel->createtime)),
                'weigh'         => strtotime($this->logModel->createtime),
            ]
        ];

        $this->success('ok', $retval);
    }

    /**
     * 当前次数
     */
    public function curNum($type = 'silver')
    {
        $count = $this->logModel->where('user_id', $this->auth->id)->whereTime('createtime', 'today')->count(); 

        $button = $this->model->getWheelList();

        $user = $this->auth->getUser();
      
        $num = floor($user->userdata->today_bet / $button[$type]['need']);

        return $num - $count;
    }

    /**
     * 处理用户名
     */
    private function dealUsername($username)
    {
        $pattern = '/^(.)(.*)(.)$/';
        $replacement = '$1**$3';
        $username = preg_replace($pattern, $replacement, $username);

        return $username;
    }
}
