<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;

class Cate extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'game_category';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

   

    public function children()
    {
        return $this->hasMany(__CLASS__, 'pid')
            ->field('id,pid,name,abbr,image,weigh')
            ->cache(true, 86400)
            ->order('weigh desc');
    }

    public function games()
    {
        return $this->hasMany('Game', 'cate_id')
            ->where('status', 1)
            ->field("id,cate_id,g_id,game_name,concat(g_id,'-',table_name) game_name_id,image,thumb,status,is_works,weigh")
            ->cache(true, 86400)
            ->order('weigh desc');
    }

    /**
     * 获取当前方案的nav分类
     */
    public static function getNavCate($cases, $is_nav = 0)
    {

        $game_cate_ids = $cases->game_cate_ids;

        $where['id|pid'] = ['in', $game_cate_ids];
        $where['status'] = 1;
        if($is_nav == 1){
            $where['is_nav'] = 1;
        }

        if($is_nav == 0){
            $where['is_left_show'] = 1;
        }
        $where['direction'] = 0;

        $list = self::where($where)
            ->order('weigh desc')
            ->cache(true, 86400)
            ->field('id,pid,name,abbr,image,thumb,bg_image,left_image,weigh')
            ->select();

        $version = config('site.version');
        foreach($list as $val){
            $val->image = cdnurl($val->image) . '?v=' . $version;
            $val->thumb = cdnurl($val->thumb) . '?v=' . $version;
            $val->bg_image = cdnurl($val->bg_image) . '?v=' . $version;
            if($is_nav == 0){
                // 当分类游戏列表是, 显示左侧图
                $val->image = $val->left_image ? cdnurl($val->left_image)  . '?v=' . $version : $val->image  . '?v=' . $version;
                $val->thumb = cdnurl($val->thumb) . '?v=' . $version;
            }
        }
        return $list;
    }

    /**
     * 获取游戏分类
     * $is_show_sub 是否显示下级 0 不显示 1 显示
     */
    public static function getCateList($cases, $is_show_sub = 0)
    {
        $game_cate_ids = $cases->game_cate_ids;
        $where['id|pid'] = ['in', $game_cate_ids];
        $where['status'] = 1;
        $where['is_home_show'] = 1; // 是否显示在首页
        $list = self::where($where)
            ->order('weigh desc')
            ->cache(true, 86400)
            ->field('id,pid,name,abbr,image,thumb,range,direction')
            ->select();
        
        $version = config('site.version');
        foreach($list as $val){
            $val->image = cdnurl($val->image)  . '?v=' . $version;
            $val->thumb = cdnurl($val->thumb)  . '?v=' . $version;

            // 是否显示下级数据
            if($is_show_sub == 1){
                // 如果分组子类为空显示游戏列表
                if($val->children->isEmpty()){
                    // 竖向的游戏分成四组
                    if($val->direction == 2){
                        foreach($val->games as $v){
                            if($v->thumb){
                                $v->image = $v->thumb;
                            }
                            $v->image = cdnurl($v->image) . '?v=' . $version;
                        }
                        $gameGroup = [
                            array_slice($val->games->toarray(), 0, 6),
                            array_slice($val->games->toarray(), 6, 6),
                            array_slice($val->games->toarray(), 12, 6),
                            array_slice($val->games->toarray(), 18, 6)
                        ];
                        $val->children_games = $gameGroup;
                    }else{
                        $val->children_games = $val->games;
                    }
                    $val->is_game_list = 1; // 是否是游戏列表
                }else{
                    foreach($val->children as $v){
                        $v->image = cdnurl($v->image) . '?v=' . $version;
                    }
                    $val->children_games = $val->children;
                    $val->is_game_list = 0; // 是否是游戏列表
                }

                $val->hidden(['games', 'children']);
            }
        }
        return $list;
    }

    /**
     * 获取分类游戏列表
     */
    public static function getCateGameList($where = [], $keyword)
    {
        $version = config('site.version');

        // 父级分类 排在数组第一位
        $row = self::where('id', $where['pid'])->field('id,pid,name,abbr,image,range,weigh')->find();
        // dd($row);
        $list = self::where($where)
            ->order('weigh desc')
            ->cache(true, 86400)
            ->field('id,pid,name,abbr,image,weigh')
            ->select();

        // 将子分类的游戏合并到父分类中
        $gameList = [];
        foreach($list as $val){
            $val->image = cdnurl($val->image) . '?v=' . $version;
            foreach($val->games as $v){
                $gameList[] = $v->toarray();
            }
        }

        // 合并父级的游戏
        $gameList = array_merge($row->games->toarray(), $gameList);
        
        // 重新排序
        array_multisort(array_column($gameList, 'weigh'), SORT_DESC, $gameList);
        
        if($keyword != ''){
            // 模糊查询
            $gameList = array_filter($gameList, function($v) use ($keyword) {
                
                if(strpos($v['game_name'], $keyword) !== false){
                    return $v;
                }else{
                    // 不存在的删掉
                    unset($v);
                }
            });
        }

        // 删除原先的属性
        unset($row->games);
        // 赋值给父分类
        $row->games = $gameList;

        // 图片转换
        $row->image = cdnurl($row->image) . '?v=' . $version;

        // 转为数组
        $row = $row->toarray();

        return $row;
    }
}
