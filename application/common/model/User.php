<?php

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * 会员模型
 * @method static mixed getByUsername($str) 通过用户名查询用户
 * @method static mixed getByNickname($str) 通过昵称查询用户
 * @method static mixed getByMobile($str) 通过手机查询用户
 * @method static mixed getByEmail($str) 通过邮箱查询用户
 */
class User extends Model
{

    protected $resultSetType = 'collection';

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'url',
    ];

    protected static function init()
    {
        self::afterInsert(function ($row) {
            UserData::create(['user_id' => $row->id, 'admin_id' => $row->admin_id]);
            UserSetting::create(['user_id' => $row->id, 'admin_id' => $row->admin_id]);
            UserInfo::create(['user_id' => $row->id, 'admin_id' => $row->admin_id, 'email' => $row->email, 'mobile' => $row->mobile]);
        });
    }

    public function userdata()
    {
        return $this->hasOne('UserData', 'user_id', 'id');
    }

    public function usersetting()
    {
        return $this->hasOne('UserSetting', 'user_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo('Admin', 'admin_id', 'id');
    }

    public function admindata()
    {
        return $this->belongsTo('AdminData', 'admin_id', 'admin_id');
    }

    public function parent()
    {
        return $this->belongsTo('User', 'parent_id', 'id');
    }

    public function userinfo()
    {
        return $this->hasOne('UserInfo', 'user_id', 'id');
    }

    /**
     * 获取个人URL
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getUrlAttr($value, $data)
    {
        return "/u/" . $data['id'];
    }

    /**
     * 获取头像
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getAvatarAttr($value, $data)
    {
        if (!$value) {
            //如果不需要启用首字母头像，请使用
            //$value = '/assets/img/avatar.png';
            $value = letter_avatar($data['nickname']);
        }
        return $value;
    }

    /**
     * 获取会员的组别
     */
    public function getGroupAttr($value, $data)
    {
        return UserGroup::get($data['group_id']);
    }

    /**
     * 获取验证字段数组值
     * @param string $value
     * @param array  $data
     * @return  object
     */
    public function getVerificationAttr($value, $data)
    {
        $value = array_filter((array)json_decode($value, true));
        $value = array_merge(['email' => 0, 'mobile' => 0], $value);
        return (object)$value;
    }

    /**
     * 设置验证字段
     * @param mixed $value
     * @return string
     */
    public function setVerificationAttr($value)
    {
        $value = is_object($value) || is_array($value) ? json_encode($value) : $value;
        return $value;
    }

    /**
     * 变更会员余额
     * @param int    $money   余额
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function money($money, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $money != 0) {
                $before = $user->money;
                //$after = $user->money + $money;
                $after = function_exists('bcadd') ? bcadd($user->money, $money, 2) : $user->money + $money;
                //更新会员信息
                $user->save(['money' => $after]);
                //写入日志
                MoneyLog::create(['user_id' => $user_id, 'money' => $money, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 变更会员积分
     * @param int    $score   积分
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function score($score, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $score != 0) {
                $before = $user->score;
                $after = $user->score + $score;
                $level = self::nextlevel($after);
                //更新会员信息
                $user->save(['score' => $after, 'level' => $level]);
                //写入日志
                ScoreLog::create(['user_id' => $user_id, 'score' => $score, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 根据积分获取等级
     * @param int $score 积分
     * @return int
     */
    public static function nextlevel($score = 0)
    {
        $lv = array(1 => 0, 2 => 30, 3 => 100, 4 => 500, 5 => 1000, 6 => 2000, 7 => 3000, 8 => 5000, 9 => 8000, 10 => 10000);
        $level = 1;
        foreach ($lv as $key => $value) {
            if ($score >= $value) {
                $level = $key;
            }
        }
        return $level;
    }

    /**
     * 余额日志, 奖励日志
     * @param string $type 类型
     * @param array  $data 数据 $data = ['box_bonus' => ['money' => 2, 'typing_amount_limit' => 0, 'transaction_id' => 123321, 'status' => 0];
     * 其中key为Dictionary表中的name字段, value为array, 格式必须要有money, typing_amount_limit, transaction_id, status, 
     * stauts为0表示未领取, 1表示已领取,moneyLog 01都可以默认0
     */
    public static function insertLog($user, $data)
    {
        // 当前余额
        $money = $user->money;

        // 日志记录
        $log = [];

        $moneyLog = [];
        $rewardLog = [];

        // 总变更余额
        $total_money = 0;
        // 总打码量
        $typing_amount_limit = 0;
        
        $k = 0;
        foreach($data as $key => $val){
            // 总变更余额
            $total_money += $val['money'];
            // 总打码量
            $typing_amount_limit += $val['typing_amount_limit'] ?? 0;

            $dictionary[$k] = Dictionary::getByName($key);
            if(!$dictionary[$k]){
                continue;
            }
            
            // reward_log表与money_log表共用字段
            $log[$k] = [
                'admin_id'          => $user->admin_id,
                'user_id'           => $user->id,
                'type'              => $key,
                'money'             => $val['money'],
                'memo'              => $dictionary[$k]->title,
                'transaction_id'    => $val['transaction_id'],
            ];

            // 余额日志
            $moneyLog[$k] = $log[$k];
            $moneyLog[$k]['before'] = $money;
            $moneyLog[$k]['after'] = $money + $val['money'];
            $moneyLog[$k]['createtime'] = time();

            // 重新赋值
            $money = $moneyLog[$k]['after'];

            // 如果字典中是常规的就不要插入奖励日志
            if($dictionary[$k]['type'] != 0){
                // 奖励日志
                $rewardLog[$k] = $log[$k];
                $rewardLog[$k]['createtime'] = datetime(time());
                // 根据data的status字段来设置状态未领取或者已领取
                $rewardLog[$k]['status'] = (string)$val['status'];
                $rewardLog[$k]['receivetime'] = $val['status'] == 1 ? datetime(time()) : null;
            }
            
            $k ++;
        }

        // 计算最终余额
        $total_money = $money;
        $typing_amount_limit = $user->userdata->typing_amount_limit + $typing_amount_limit;
        
        $result = false;
        Db::startTrans();
        try {
            if ($user) {
                //更新会员余额以及打码量
                $user->money = $total_money;
                $result = $user->save();
        
                // 更新打码量
                $user->userdata->typing_amount_limit = $typing_amount_limit;
                if($user->userdata->save() === false){
                    $result = false;
                }
                
                //写入日志
                if(MoneyLog::insertAll($moneyLog) === false){
                    $result = false;
                }
                
                // 数据不为空的时候
                if(!empty($rewardLog) && RewardLog::insertAll($rewardLog) === false){
                    $result = false;
                }

            }
            
            if($result != false){
                Db::commit();
            }
        } catch (\Exception $e) {
            // dd($e->getMessage());
            Db::rollback();
        }
        return $result;
    }

    /**
     * 所有上级
     */
    public static function getParents($user)
    {
        $parent_id_str = $user->parent_id_str;
        if(!$parent_id_str){
            return [];
        }
        $parent_id_arr = explode(',', $parent_id_str);
        $parents = self::where('id', 'in', $parent_id_arr)->select();
        return $parents;
    }

    /**
     * 生成与上级的关系字符串
     */
    public static function parentIdStr($user)
    {
        $parent_id_str = $user->parent_id_str;

        if(!$parent_id_str){
            $parent_id_str = $user->id;
        }else{

            $parent_id_arr = explode(',', $parent_id_str);
            // 如果已经有3个上级了，就删除第一个, 只显示最近的3个上级
            if(count($parent_id_arr) >= 3){
                unset($parent_id_arr[0]);
            }
            $parent_id_arr[] = $user->id;
            $parent_id_str = implode(',', $parent_id_arr);
        }
        return $parent_id_str;
    }

    /**
     * 获取有效用户(默认返回数量)
     * @，$flag 1 返回数量，0 返回用户列表
     */
    public static function validUser($user_id, $flag = 1)
    {
        if(!$user_id) return 0;

        $valid_bet = config('system.valid_bet');
        $valid_recharge = config('system.valid_recharge');

        $where['parent_id'] = $user_id;
        $userinfo = self::where($where)->select();
        // 有效用户
        $valid_user = [];
        foreach($userinfo as $user){
            if($user->userdata->total_recharge >= $valid_recharge && $user->userdata->total_bet >= $valid_bet){
                $valid_user[] = $user;
            }
        }

        return $flag ? count($valid_user) : $valid_user;
    }

     /**
     * 判断是否为有效用户
     */
    public static function isValidUser($user)
    {
        $valid_bet = config('system.valid_bet');
        $valid_recharge = config('system.valid_recharge');

        $is_valid = 0;
        if($user->userdata->total_recharge >= $valid_recharge && $user->userdata->total_bet >= $valid_bet){
            $is_valid = 1;
        }
        return $is_valid;
    }

    /**
     * 获取下级用户
     * $is_level 1 返回层级，0 返回用户列表
     */
    public static function getSubUsers($user_id, $is_level = 0, $custom_fields = '', $where = [])
    {
        $fields = 'id,parent_id,parent_id_str,username,is_first_recharge,createtime';
        if($custom_fields){
            $fields .= ',' . $custom_fields;
        }

        $users = User::where([
            ['EXP', Db::raw("FIND_IN_SET(". $user_id .", parent_id_str)")]
        ])->field($fields)->where($where)->select();

        foreach($users as $user){
            $user->total_bet = $user->userdata->total_bet;
            $user->total_recharge = $user->userdata->total_recharge;
            $user->total_typing = $user->userdata->total_typing;
            $user->total_profit = $user->userdata->total_profit;
            $user->createtime = datetime($user->createtime);

            $user->is_valid = self::isValidUser($user);
            unset($user->userdata);
        }

        $users = $users->toArray();

        // 标记每个用户的等级
        foreach ($users as &$user) {
            $parentArr = explode(',', $user['parent_id_str']);
            $pos = array_search($user_id, $parentArr); // 查找当前用户ID的位置
            
            if ($pos !== false) {
                // 层级 = 总长度 - 当前用户位置 (从0开始)
                $user['rank'] = count($parentArr) - $pos;
            } else {
                $user['rank'] = 0; // 理论上不会发生，安全处理
            }
        }
        unset($user); // 解除引用

        $array = [];
        foreach($users as $val){
            if($is_level == 1){
                $array[$val['rank']][] = $val;
            }else{
                $array[] = $val;    
            }
        }

        return $array;
    }

    /**
     * 获取直推用户
     */
    public static function directUser($user_id)
    {
        $fields = 'id,admin_id,username,invite_code,parent_id,parent_id_str,money,is_first_recharge';
        $list = self::where('parent_id', $user_id)->field($fields)->select();
        foreach($list as $user){
            $user->is_valid = self::isValidUser($user);
        }
        return $list;
    }

    /**
     * 博主获取全平台邀请用户
     */
    public static function getTeam($users, $user_id)
    {
        // 构建父级ID到子用户的映射 (优化查找效率)
        $childrenMap = [];
        foreach ($users as $user) {
            $parentId = $user['parent_id'];
            $childrenMap[$parentId][] = $user;
        }
        
        $team = [];      // 结果数组
        $queue = [];      // BFS队列
        $level = 1;       // 起始层级 (直接下级为1级)
        
        // 初始化：将直接下级加入队列
        if (!empty($childrenMap[$user_id])) {
            foreach ($childrenMap[$user_id] as $child) {
                $child['level'] = $level;  // 标记层级
                $queue[] = $child;         // 入队
            }
        }

        // BFS遍历
        while (!empty($queue)) {
            $level++;  // 进入下一层级
            $currentSize = count($queue);
            
            for ($i = 0; $i < $currentSize; $i++) {
                $curUser = array_shift($queue);  // 出队
                $team[] = $curUser;              // 加入结果
                
                // 处理当前用户的下级
                $curId = $curUser['id'];
                if (empty($childrenMap[$curId])) continue;
                
                foreach ($childrenMap[$curId] as $subUser) {
                    $subUser['level'] = $level;  // 标记子级层级
                    $queue[] = $subUser;          // 子级入队
                }
            }
        }
        
        return collection($team);
    }
}
