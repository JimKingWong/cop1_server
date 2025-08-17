<?php

namespace app\common\service;

use app\common\model\Dictionary;
use app\common\model\game\Jdb;
use app\common\model\game\Omg;
use app\common\model\MoneyLog;
use app\common\model\Recharge;
use app\common\model\RewardLog;
use app\common\model\User;
use app\common\model\Withdraw;
use think\Db;

/**
 * 记录服务
 */
class Record extends Base
{
    protected $platform = [
        'PG'            => 2,
        'PP'            => 4,
        'JILI'          => 3,
        'JDB'           => 1,
        'TADA'          => 23,
        'CP'            => 24,
        'Spribe'        => 1,
        'MiniGame'      => 6,
        'FC'            => 4,
        'AMB'           => 11,
        'OMG_MINI'      => 5,
        'OMG_CRYPTO'    => 7,
        'Hacksaw'       => 8,
    ];

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 奖励记录
     */
    public function rewardLog()
    {
        $limit = (int)$this->request->get('limit', 10);
        $type = $this->request->get('type');
        $date = $this->request->get('date/d', '');
        $status = $this->request->get('status');

        // 今天 昨天, 过去7天, 过去15天, 过去30天
        if(!in_array($date, [0, 1, 7, 15, 30])) $date = ''; // 默认全部
        if(!in_array($status, [0, 1, 2])) $status = ''; // 默认全部

        $dictionary = Dictionary::where('type', 1)->field('title,name')->select();
        foreach($dictionary as $val){
            $val->title = __($val->title);
        }

        $where['user_id'] = $this->auth->id;

        // 奖励类型
        if($type != ''){
            $where['type'] = $type;
        }

        // 状态
        if($status != ''){
            $where['status'] = (string)$status;
        }

        if($date === 0){
            $starttime = date('Y-m-d');
            $where['createtime'] = ['>=', $starttime];
        }

        if($date > 0){
            $starttime = date('Y-m-d', strtotime('-' . $date . ' day'));
            $endtime = date('Y-m-d');
            $where['createtime'] = ['between', [$starttime, $endtime]];
        }
        // dd($where);

        $list = RewardLog::where($where)
            ->order('id desc')
            ->field('id,type,money,memo,createtime,status')
            ->select();
            // ->paginate([
            //     'list_rows' => $limit,
            //     'query'     => $this->request->param(),
            // ]);
        foreach($list as $val){
            $val->memo = __($val->memo);
            $val->createtime = date('Y-m-d H:i:s', $val->createtime);
        }

        // 总奖金
        $bonus = RewardLog::where($where)->sum('money');
        $retval = [
            'dictionary'    => $dictionary,
            'bonus'         => $bonus,
            'list'          => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 余额明细
     */
    public function moneyLog()
    {
        $limit = (int)$this->request->get('limit', 10);
        $type = $this->request->get('type');
        $date = $this->request->get('date/d', '');

        // 今天 昨天, 过去7天, 过去15天, 过去30天
        if(!in_array($date, [0, 1, 7, 15, 30])) $date = ''; // 默认全部

        $dictionary = Dictionary::field('title,name')->select();
        foreach($dictionary as $val){
            $val->title = __($val->title);
        }

        $where['user_id'] = $this->auth->id;

        // 奖励类型
        if($type != ''){
            $where['type'] = $type;
        }

        if($date === 0){
            $starttime = date('Y-m-d');
            $where['createtime'] = ['>=', $starttime];
        }

        if($date > 0){
            $starttime = date('Y-m-d', strtotime('-' . $date . ' day'));
            $endtime = date('Y-m-d');
            $where['createtime'] = ['between', [$starttime, $endtime]];
        }
        // dd($where);

        $list = MoneyLog::where($where)
            ->order('id desc')
            ->field('id,type,money,before,after,memo,createtime')
            ->select();
            // ->paginate([
            //     'list_rows' => $limit,
            //     'query'     => $this->request->param(),
            // ]);
        foreach($list as $val){
            $val->memo = __($val->memo);
            $val->money = $val->after - $val->before > 0 ? '+' . $val->money : '-' . $val->money;
            $val->createtime = date('Y-m-d H:i:s', $val->createtime);
        }

        // 总奖金
        $bonus = RewardLog::where($where)->sum('money');
        
        unset($where['type']);
        $total_recharge = Recharge::where($where)->sum('money');
        $total_withdraw = Withdraw::where($where)->sum('money');
        $retval = [
            'dictionary'        => $dictionary,
            'bonus'             => $bonus,
            'total_recharge'    => $total_recharge,
            'total_withdraw'    => $total_withdraw,
            'list'              => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 余额详情
     */
    public function moneyDetail()
    {
        $id = $this->request->get('id/d', 0);
        $where['id'] = $id;
        $where['user_id'] = $this->auth->id;
        $detail = MoneyLog::where($where)
            ->field('id,type,money,before,after,memo,createtime')
            ->find();

        $detail->memo = __($detail->memo);

        if(!$detail){
            $this->error(__('无效参数'));
        }

        $retval = [
            'detail' => $detail,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 游戏下注记录
     */
    public function gamebet()
    {
        $retval = $this->gameRecord();
        unset($retval['game_list']);
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 游戏记录统计
     */
    public function gamestats()
    {
        $res = $this->gameRecord();

        // 游戏记录列表
        $list = $res['list'];

        // 游戏列表
        $gameList = $res['game_list'];

        // 平台
        $platform = $res['platform'];

        $result = cache('gamestats_' . $this->auth->id . '_'  . $platform);
        if(!$result){
            $stats = []; // 统计
            foreach($list as $val){
                $gameId = $val['game_id'];
                $date = $val['date'];
                $key = $date . '_' . $gameId;
                
                if(!isset($stats[$key])){
                    $game_name = $gameList[$gameId] ?? null;
                    $stats[$key] = [
                        'date'          => $date,
                        'game_id'       => $gameId,
                        'game_name'     => $game_name ? $game_name : $platform . '_' . $gameId,
                        'play_count'    => 0,
                        'total_bet'     => 0,
                        'valid_bet'     => 0,
                        'total_transfer'=> 0,
                    ];
                }
                
                $stats[$key]['play_count'] ++;
                $stats[$key]['total_bet'] += $val['bet_amount'] ?? 0;
                $stats[$key]['valid_bet'] += $val['bet_amount'] ?? 0;
                $stats[$key]['total_transfer'] += $val['transfer_amount'] ?? 0;

                // 精度
                $stats[$key]['total_bet'] = number_format($stats[$key]['total_bet'], 2);
                $stats[$key]['valid_bet'] = number_format($stats[$key]['valid_bet'], 2);
                $stats[$key]['total_transfer'] = number_format($stats[$key]['total_transfer'], 2);
            }

            // 转换为数组并按日期排序
            $result = array_values($stats);
            usort($result, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            cache('gamestats_' . $this->auth->id . '_'  . $platform, $result, 60);
        }

        
        $retval = [
            'platform_list' => $res['platform_list'],
            'stats'         => $result,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 游戏记录
     */
    public function gameRecord()
    {
        // omg 平台
        $omgPlatform = Omg::getPlatform();

        // 键值互反
        $omgFlip = array_flip($omgPlatform);

        // jdb 平台
        $jdbPlatform = Jdb::getPlatform();

        // 键值互反
        $jdbFilp = array_flip($jdbPlatform);

        // 合并omg和jdb
        $platformArr = $this->platform;
        
        // 取key返给前端
        $platformList = array_keys($platformArr);
        
        // 传入平台 PG PP 等
        $platform = $this->request->get('platform', 'PG'); // 默认PG

        // 检测传入平台是否在数组中
        if(!in_array($platform, $platformList)) $platform = 'PG';

        // 默认PG, 即默认是omg平台
        $gameRecord = 'omg_game_record';
        $platformKey = $omgFlip[$platform] ?? 'PG';

        $gameList = Omg::field('game_id,game_name')->cache(true, 86400)->select();
        if(in_array($platform, $jdbPlatform)){
            $gameRecord = 'jdb_game_record';
            $platformKey = $jdbFilp[$platform];
            $gameList = Jdb::field('game_id,game_name')->cache(true, 86400)->select();
        }

        $gameArr = [];
        foreach($gameList as $val){
            $gameArr[$val['game_id']] = $val['game_name'];
        }

         // 时间范围
        $date = $this->request->get('date/d', 0); // 默认今天

        // 今天 昨天, 过去7天, 过去15天, 过去30天
        if(!in_array($date, [0, 1, 7, 15, 30])) $date = 0; // 默认今天

        // 凌晨时间
        $time = strtotime(date('Y-m-d'));
        $starttime = strtotime('-' . $date . ' day', $time);
        $endtime = $date == 0 ? $time + 86400 : $time;

        $es = new \app\common\service\util\Es();

          $condition = [
                // 用户id搜索
                [
                    'type' => 'term',
                    'field' => 'user_id',
                    'value' =>  $this->auth->id,
                ],
                [
                    'type' => 'term',
                    'field' => 'platform',
                    'value' =>  $platformKey,
                ],
                [
                    'type' => 'range',
                    'field' => 'createtime',
                    'value' => [
                        'gte' => $starttime,
                        'lte' => $endtime,
                    ]
                ]
        ];

        // $where['user_id'] = $this->auth->id;
        // $where['platform'] = $platformKey;
        // $list = $es->searchByDate($gameRecord, $where, $starttime, $endtime);
        $list = $es->multiSearch($gameRecord, $condition);
        if($platform == 'PG'){
            $linshi_pg = $es->multiSearch('other_game_record', $condition); // 临时用这个表
            $list = array_merge($list, $linshi_pg);
            
            usort($list, function($a, $b) {
                // 如果createtime不存在，则使用paytime
                $timeA = $a['createtime'];
                $timeB = $b['createtime'];
                
                // 降序排序（最新的在前）
                if ($timeA == $timeB) {
                    return 0;
                }
                return ($timeA > $timeB) ? -1 : 1;
                
                // 如果需要升序排序（最旧的在前），使用下面的代码
                // return ($timeA < $timeB) ? -1 : 1;
            });
        }
        // dd($list);

        foreach($list as $key => $val){
            $list[$key]['platform'] = $platform;
            $list[$key]['game_name'] = $gameArr[$val['game_id']] ?? $platform . '_' . $val['game_id'];
            $list[$key]['createtime'] = date('Y-m-d H:i:s', $val['createtime']);
            $list[$key]['date'] = date('Y-m-d', $val['createtime']);
            $list[$key]['image'] = cdnurl($val['image']);
            unset($list[$key]['is_fake']);
            unset($list[$key]['balance']);
        }

        $retval = [
            'platform_list' => $platformList,
            'list'          => $list,
            'game_list'     => $gameArr,
            'platform'      => $platform,
        ];
        return $retval;
    }

    /**
     * 亏损返水, 下注奖励
     */
    public function detail()
    {
        $type = $this->request->get('type');

        $arr = ['loss_bonus', 'pg_bet_bonus'];
        if(!in_array($type, $arr)){
            $this->error(__('无效参数'));
        }

        $where['user_id'] = $this->auth->id;
        $where['type'] = $type;
        $where['status'] = 0;

        // 设置过期
        $where['createtime'] = ['<', date('Y-m-d')];
        $list = RewardLog::where($where)->select();
        foreach($list as $val){
            $val->status = 2; // 已过期
            $val->save();
        }
        
        unset($where['createtime']);
        $money = RewardLog::where($where)->sum('money');

        $retval = [
            'amount' => sprintf('%.2f', $money),
        ];
        $this->success(__('请求成功'), $retval);
    }

     /**
     * 领取奖励
     */
    public function receive()
    {
        $type = $this->request->post('type');

        $arr = ['loss_bonus', 'pg_bet_bonus'];

        if(!in_array($type, $arr)){
            $this->error(__('无效参数'));
        }

        $user = $this->auth->getUser();

        $where['user_id'] = $user->id;
        $where['type']  = $type;
        $where['status'] = 0;

        $reward = RewardLog::where($where)->select();

        $money = 0;
        $ids = [];
        foreach($reward as $val){
            $money += $val['money'];
            $ids[] = $val['id'];
        }

        if($money <= 0){
            $this->error(__('未达到领取条件'));
        }

        $title = Dictionary::where('name', $type)->value('title');

        $result = false;
        Db::startTrans();
        try{
            $user = User::lock(true)->find($user->id);

            $before = $user->money;
            $after = $before + $money;
            $user->money = $after;
            $result = $user->save();

            $data = [
                'admin_id' => $user->admin_id,
                'user_id'  => $user->id,
                'money'    => $money,
                'before'   => $before,
                'after'    => $after,
                'type'     => $type,
                'transaction_id'    => implode(',', $ids),
                'memo'     => $title,
                // 'createtime' => time(),
            ];
            if(MoneyLog::create($data) === false){
                $result = false;
            }

            foreach($reward as $val){
                $val->status = 1;
                $val->receivetime = datetime(time());
                $val->save();
            }

            if($result != false){
                Db::commit();
            }
            
        }catch(\Exception $e){
            Db::rollback();
            $this->error(__('领取失败'));
        }

        if($result == false){
            $this->error(__('领取失败'));
        }

        $this->success(__('领取成功'));
    }

}