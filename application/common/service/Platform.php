<?php

namespace app\common\service;

use app\common\model\Activity;
use app\common\model\Cases;
use app\common\model\Cate;
use app\common\model\Custservice;
use think\Cache;
use think\Db;

/**
 * 平台服务
 */
class Platform extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 首页初始化
     */
    public function init()
    {
        // Jackpot数字
        $jackpot = db('bet_number')->cache(true, 86400)->value('money');

        // 跑马灯公告
        $notice = config('platform.notice');

        // 获取站点弹窗信息
        $window_case_id = $this->site->window_case_id ?? 0;
        if($window_case_id){
            $where['id'] = $window_case_id;
        }else{
            $where['is_default'] = 1;
        }
        $window_ids = db('window_cases')->where($where)->cache(true, 86400)->value('window_ids');
        $window = db('window')->where('id', 'in', $window_ids)->where('status', 1)->cache(true, 86400)->order('weigh desc')->field('id,title,image,type,content')->select();
        foreach($window as $key => $val){
            $list[$key]['image'] = $val['image'] ? cdnurl($val['image']) : '';
        }

        // 音乐
        $music = db('music')->where('status', 1)->cache(true)->order('weigh desc')->field('id,name,url')->select();

        // 语言
        $language = db('language')->where('status', 1)->order('weigh desc')->field('id,name,title,is_default')->cache(true)->select();

        // 签到状态
        $signin = Activity::where('name', 'signin_bonus')->find();
        $signin_status = $signin['status'] ?? 0;

        $retval = [
            'jackpot'       => $jackpot,
            'notice'        => $notice,
            'window'        => $window,
            'music'         => $music,
            'language'      => $language,
            'signin_status' => $signin_status,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取分类
     */
    public function nav()
    {
        $is_nav = $this->request->get('is_nav', 0);

        // 获取当前方案
        $cases = Cases::getCases($this->origin);
        // 获取游戏分类
        $cateList = Cate::getNavCate($cases, $is_nav);
        $retval = [
            'cate_list'     => $cateList,
            'case_tpl_id'   => $cases['case_tpl_id'],
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 客服
     */
    public function support()
    {
        $data = Cache::get('custservice');
        if(!$data){
            $custservice = Custservice::where('status', 1)->order('weigh desc')->field('id,name,channel,image,content,url')->select();

            $config = config('platform');

            $data = [
                'system_service'    => $config['system_service'],
                'group_telegram'    => $config['group_telegram'],
                'group_whatsapp'    => $config['group_whatsapp'],
                'group_ins'         => $config['group_ins'],
                'custservice'       => $custservice
            ];
            Cache::set('custservice', $data, 86400);
        }
        
        $this->success(__('请求成功'), $data);
    }

    /**
     * 获取语言列表
     */
    public function lang()
    {
        $language = db('language')->where('status', 1)->order('weigh desc')->field('id,name,title,is_default')->cache(true)->select();
        
        $retval = [
            'language' => $language,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 银行列表
     */
    public function bank()
    {
        $bank = db('bank')->where('status', 1)->order('weigh desc')->field('id,code,name')->select();
        // 证件类型:0=身份证（CC/TI）,1=外国人身份证（CE）,2=税号,3=护照,4=公民身份证明,5=居留许可证,6=其他
        $cert = \app\common\model\UserBank::certType(0);
        $retval = [
            'bank' => $bank,
            'cert' => $cert,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 站内信
     */
    public function letter()
    {
        $type = $this->request->get('type', 0);

        $where['type'] = $type;

        $user_id = $this->auth->id ?? 0;

        $fields = "id,title,content,createtime,type";
        $list = \app\common\model\Letter::field($fields)->order('id desc');

        if($type != 1){
            $list = $list->where($where);
        }
        
        if($type == 1){
            if($user_id > 0){
                $list = $list->where($where);
            }
            $list = $list->where([
                ['EXP', Db::raw("FIND_IN_SET(". $user_id .", user_ids)")]
            ]);
        }
        
        $list = $list->select();

        $readList = \app\common\model\LetterRead::where('user_id', $this->auth->id)->column('id', 'letter_id');
        foreach($list as $val){
            $val->is_read = isset($readList[$val->id]) ? 1 : 0;
        }

        $this->success(__('请求成功'), $list);
    }

    
    /**
     * 站内信已读
     */
    public function read()
    {
        $letter_id = $this->request->get('letter_id', 0);
        
        $fields = "id,title,content,createtime,type";
        $letter = \app\common\model\Letter::field($fields)->find($letter_id);
        if(!$letter){
            $this->error(__('无效参数'));
        }

        $where['user_id'] = $this->auth->id;
        $where['letter_id'] = $letter_id;
        $check = \app\common\model\LetterRead::where($where)->find();
        if(!$check){
            $data = [
                'user_id'       => $this->auth->id,
                'letter_id'     => $letter_id,
                'is_read'       => 1,
            ];
            \app\common\model\LetterRead::create($data);
        }

        $this->success(__('请求成功'), $letter);
    }
}