<?php

namespace app\common\service;

use app\common\model\Cases;
use app\common\model\Cate;
use app\common\model\CollectLog;

/**
 * 游戏服务
 */
class Game extends Base
{

    protected $model = null;
    public function __construct()
    {
        parent::__construct();

        $this->model = new \app\common\model\Game();
    }

    /**
     * 启动游戏
     * @var \app\common\service\game\Pg
     * @var \app\common\service\game\Pgnew
     * @var \app\common\service\game\Pgnew3
     * @var \app\common\service\game\Omg
     * @var \app\common\service\game\Jdb
     * @var \app\common\service\game\Tada
     * @var \app\common\service\game\Pp
     * @var \app\common\service\game\Cp
     * @var \app\common\service\game\Cq
     */
    public function startup()
    {
        // 传id
        $game_id = $this->request->get('game_id');
        $row = $this->model->where('id', $game_id)->find();
        if(!$row){
            $this->error(__('游戏不存在'));
        }

        $user = $this->auth->getUser();
        if($user->usersetting->game_status == 0){
            $this->error(__('您已被禁止玩游戏'));
        }

        // 对应表名
        $table_name = $row->table_name;

        // 关联对应的游戏模型
        $tableModel = str_replace('_', '', $table_name);
        // 对应游戏
        $game = $row->$tableModel;

        // 如果不是omg表的游戏, 先找到对应在omg表的游戏
        if($row->table_name != 'game_omg'){
            $platformArr = [
                'game_pg'       => [2],
                'game_cp'       => [24],
                'game_tada'     => [3, 23],
                'game_jdb'      => [],
                'game_pp'       => [4],
                'game_cq'       => [],
            ];
            $where['real_game_id'] = $row->game_id;
            $where['platform'] = ['in', $platformArr[$table_name]];
            // dd($where);
            $omg = \app\common\model\game\Omg::where($where)->find();
            // 找的到就用omg表的游戏
            if($omg){
                $table_name = 'game_omg';

                // 重新赋值游戏
                $game = $omg;
            }
        }
        // dd($omg);

        // omg分流
        if($table_name == 'game_omg'){
            $code = \app\common\model\game\Omg::omgCode($user);
            $map['code'] = $code;
        }

        // 获取游戏服务
        $map['table_name'] = $table_name;
        $platform = $this->model::services($map);
        
        // 对应游戏服务实例
        $service = new \app\common\service\game\Platform($platform);

        // 对应方法
        $method = $platform->method ?? 'omgLink';

        $service->$method($game);
    }

    /**
     * omg测试启动游戏
     */
    public function testStartup()
    {
        // 传id
        $game_id = $this->request->get('game_id');
        $row = $this->model->where('id', $game_id)->find();
        if(!$row){
            $this->error(__('游戏不存在'));
        }

        // 关联对应的游戏模型
        $tableModel = str_replace('_', '', $row->table_name);
        // 对应游戏
        $game = $row->$tableModel;

        // 如果不是omg表的游戏, 先找到对应在omg表的游戏
        if($row->table_name != 'game_omg'){
            $platformArr = [
                'game_pg'       => [2],
                'game_cp'       => [24],
                'game_tada'     => [3, 23],
                'game_jdb'      => [],
                'game_pp'       => [4],
                'game_cq'       => [],
            ];
            $where['real_game_id'] = $row->game_id;
            $where['platform'] = ['in', $platformArr[$row->table_name]];
            // dd($where);
            $omg = \app\common\model\game\Omg::where($where)->find();
            // 找的到就用omg表的游戏
            if($omg){
                // 重新赋值游戏
                $game = $omg;
            }
        }
        // dd($game->toarray());

        // 获取游戏服务
        $map['code'] = 'pgomg_test';
        $platform = $this->model::services($map);
        // dd($platform->toarray());

        // 对应游戏服务实例
        $service = new \app\common\service\game\Platform($platform);

        // 对应方法
        $method = $platform->method ?? 'omgLink';

        $service->$method($game);
    }

    /**
     * 启动游戏
     * @var \app\common\service\game\Pg
     * @var \app\common\service\game\Pgnew
     * @var \app\common\service\game\Pgnew3
     * @var \app\common\service\game\Omg
     * @var \app\common\service\game\Jdb
     * @var \app\common\service\game\Tada
     * @var \app\common\service\game\Pp
     * @var \app\common\service\game\Cp
     */
    public function startups()
    {
        // 传id
        $game_id = $this->request->get('game_id');
        $row = $this->model->where('id', $game_id)->find();
        if(!$row){
            $this->error(__('游戏不存在'));
        }

        // 关联对应的游戏模型
        $tableModel = str_replace('_', '', $row->table_name);

        // 对应游戏
        $game = $row->$tableModel;

        // 获取游戏服务
        $map['table_name'] = $row->table_name;
        $gameService = $this->model::services($map);

        // 如果不是omg表的游戏, 先找到对应在omg表的游戏
        if($row->table_name != 'game_omg'){
            $plarformArr = [
                'game_pg'       => [2],
                'game_cp'       => [24],
                'game_tada'     => [3, 23],
                'game_jdb'      => [],
                'game_pp'       => [4],
            ];
            $where['real_game_id'] = $row->game_id;
            $where['plarform'] = ['in', $plarformArr[$row->table_name]];
            $omg = \app\common\model\game\Omg::where($where)->find();
            // 找的到就用omg表的游戏
            if($omg){
                $gameService = new \app\common\service\game\Omg;

                // 重新赋值游戏
                $game = $omg;
            }
        }
        
        // dd($gameService);
        if(!$gameService){
            $this->error(__('游戏服务不存在'));
        }

        // dd($row->$tableModel->toarray());
        // 创建对应游戏服务实例
        $service = new $gameService;

        // 统一获取游戏链接 game_id是游戏真实id, 不是游戏列表id
        $service->getLink($game);
    }

    /**
     * 游戏列表
     */
    public function list()
    {
        // 获取当前站点的游戏列表方案
        $origin = $this->origin;

        // 获取当前方案
        $cases = Cases::getCases($origin);
        
        // 游戏分类列表
        $cateList = Cate::getCateList($cases, 1);

        $collectLogModel = new CollectLog();
        // 用户的收藏记录
        $log = [];
        if(isset($this->auth->id)){
            $log = $collectLogModel::getCollectLog($this->auth->id);
        }

        foreach($cateList as $key => $val){
            if($val['range'] != ''){
                list($min, $max) = explode('-', $val['range']);
            }
            foreach($val['games'] as $k => $v){
                // 已收藏
                $cateList[$key]['games'][$k]['is_collect'] = isset($log[1][$v['game_name_id']]) ? $log[1][$v['game_name_id']] : 0;
                $cateList[$key]['games'][$k]['is_recent'] = isset($log[0][$v['game_name_id']]) ? 1 : 0;
                $cateList[$key]['games'][$k]['game_num'] = mt_rand($min ?? 10, $max ?? 50);
                $cateList[$key]['games'][$k]['image'] = cdnurl($v['image'], true);
            }
        }
        
        $retval = [
            'list'          => $cateList,
            'case_tpl_id'  => $cases->case_tpl_id,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 分类游戏列表
     */
    public function filter()
    {
        $cate_id = $this->request->get('cate_id', 1);
        // $pid = $this->request->get('pid', 0);
        $keyword = $this->request->get('keyword', '');

        // pid大于0的话, 用传过来的pid, 否则用cate_id
        // $where['pid'] = $pid ?: $cate_id;
        $where['pid'] = $cate_id;
        $row = Cate::getCateGameList($where, $keyword);

        $collectLogModel = new CollectLog();
        // 用户的收藏记录
        $log = [];
        if(isset($this->auth->id)){
            $log = $collectLogModel::getCollectLog($this->auth->id);
        }

        if(isset($row['range'])){
            list($min, $max) = explode('-', $row['range']);
        }
        
        foreach($row['games'] as $key => $val){
            // 已收藏
            $row['games'][$key]['is_collect'] = isset($log[1][$val['game_name_id']]) ? $log[1][$val['game_name_id']] : 0;
            $row['games'][$key]['is_recent'] = isset($log[0][$val['game_name_id']]) ? 1 : 0;
            $row['games'][$key]['game_num'] = mt_rand($min ?? 10, $max ?? 50);
            $row['games'][$key]['image'] = cdnurl($val['image'], true);
        }

        $retval = [
            'list'  => $row,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 收藏和添加最近游戏
     */
    public function collect()
    {
        // 传id
        $game_id = $this->request->post('game_id');
        $type = $this->request->post('type', 0);

        $row = $this->model->where('id', $game_id)->find();
        if(!$row){
            $this->error(__('游戏不存在'));
        }

        // 对应游戏表
        $g_id = $row->g_id;
        $table_name = $row->table_name;

        // 用户信息
        $user = $this->auth->getUser();

        $collectLogModel = new CollectLog();

        $where['user_id'] = $user->id;
        $where['type'] = $type;
        $where['g_id'] = $g_id;
        $where['table_name'] = $table_name;
        $check = $collectLogModel->where($where)->find();
        if($check){
            // 如已经添加最近的不再操作
            $result = true;
            if($type == 1){
                // 点击一次, 状态取相反值
                $check->status = $check->status ? 0 : 1;
                $result = $check->save();
            }
        }else{
            $collectLogModel->user_id       = $user->id;
            $collectLogModel->game_id       = $game_id;
            $collectLogModel->g_id          = $g_id;
            $collectLogModel->table_name    = $table_name;
            $collectLogModel->type          = $type;
            $result = $collectLogModel->save();
        }

        if($result === false){
            $this->error(__('收藏失败'));
        }
        $this->success(__('请求成功'));
    }

    /**
     * 收藏和最近列表
     */
    public function collectList()
    {
        $type = $this->request->get('type', 1);
        
        $user_id = $this->auth->id ?? 0;

        $collectLogModel = new CollectLog();

        // 用户的收藏记录
        $log = [];
        if($user_id){
            $log = $collectLogModel::getCollectLog($user_id);
        }
        // dd($log);
        $where['user_id'] = $user_id;
        $where['type'] = $type;
        $where['status'] = 1;
        $list = $collectLogModel->where($where)
            ->field("game_id id,concat(g_id,'-',table_name) game_name_id,g_id,table_name,type")
            ->order('id desc')
            ->select()
            ->each(function($item) use($log, $type){
                
                // 对应的关联模型方法名
                $table_name = str_replace('_', '', $item->table_name);

                $item->game_name = $item->$table_name->game_name;
                $item->is_works = $item->$table_name->is_works;
                $item->image = cdnurl($item->$table_name->image);
                $item->is_collect = $type == 1 ? 1 : (isset($log[1][$item->game_name_id]) ? $log[1][$item->game_name_id] : 0); // 冒号后面是留给最近游戏那边判断
                $item->is_recent = $type == 0 ? 1 : (isset($log[0][$item->game_name_id]) ? 1 : 0); // 冒号后面是留给收藏游戏那边判断
                unset($item->$table_name);
            });

        $retval = [
            'list'  => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }
}