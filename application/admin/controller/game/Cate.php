<?php

namespace app\admin\controller\game;

use app\common\controller\Backend;

use think\Db;
use Exception;
use fast\Tree;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 游戏分类管理
 *
 * @icon fa fa-circle-o
 */
class Cate extends Backend
{

    /**
     * Cate模型对象
     * @var \app\admin\model\game\Cate
     */
    protected $model = null;
    protected $tree = null;
    protected $cateList = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\game\Cate;

        $this->tree = Tree::instance();

        $list = $this->model->order('weigh desc')->select();
        $this->tree->init($list, 'pid');

        $this->cateList = $this->tree->getTreeList($this->tree->getTreeArray(0), 'name');
        $this->view->assign("cateList", $this->cateList);

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
    public function index($cate_id = null)
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            $searchValue = $this->request->request("searchValue");
            $search = $this->request->request("search");
            // dd($this->request->request());
            //构造父类select列表选项数据
            $list = [];
            if ($search||$searchValue) {

                foreach ($this->cateList as $k => &$v) {

                    if ($search&&stripos($v['name'], $search) !== false) {
                        $list[] = $v;
                    }
                    
                    if ($searchValue&&in_array($v['id'], explode(',',$searchValue)) !== false) {
                        $v['name']=preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", " ", strip_tags($v['name'])); //过滤空格
                        $list[] = $v;
                    }
                }
            } else {
                $list = $this->cateList;
            }

            $list = array_values($list);
            foreach($list as $v){
                $v['name']=preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", " ", strip_tags($v['name'])); //过滤空格
            }

            $total = count($list);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        $this->assignconfig('cate_id', $cate_id);
        $this->assign('cate_id', $cate_id);
        return $this->view->fetch();
    }

    /**
     * 复制
     */
    public function copy($ids = null)
    {
        $row = $this->model->with(['children', 'children.games'])->find($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
       
        $result = false;
        Db::startTrans();
        try {
            unset($row['id']);
            $result = $this->model->allowField(true)->save($row->toArray());
            if($row->children){
                foreach($row->children as $key => $val){
                    $childData[$key] = $val->getData();
                    unset($childData[$key]['id']);
                    $childData[$key]['pid'] = (int)$this->model->id;
                    $childData[$key]['createtime'] = datetime(time());
                    $childData[$key]['updatetime'] = datetime(time());
                    $res[$key] = $this->model->children()->save($childData[$key]);

                    $cate_id[$key] = $res[$key]->id; // 存储子记录ID
                    if($val->games){
                        foreach($val->games as $k => $v){
                            $gameData[$k] = $v->getData();
                            unset($gameData[$k]['id']);

                            $gameData[$k]['cate_id'] = $cate_id[$key];
                            $gameData[$k]['createtime'] = datetime(time());
                            $gameData[$k]['updatetime'] = datetime(time());
                            
                            $res[$key]->games()->save($gameData[$k]);
                        }
                    }
                }
            }   

            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success('复制成功');
    }

    /**
     * 批量更新状态
     *
     * @param $ids
     * @return void
     */
    public function multi($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $ids ?: $this->request->post('ids');
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        if (false === $this->request->has('params')) {
            $this->error(__('No rows were updated'));
        }
        parse_str($this->request->post('params'), $values);
        $values = $this->auth->isSuperAdmin() ? $values : array_intersect_key($values, array_flip(is_array($this->multiFields) ? $this->multiFields : explode(',', $this->multiFields)));
        if (empty($values)) {
            $this->error(__('You have no permission'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }

        // 包含自身
        $ids = $this->tree->getChildrenIds($ids, true);
        // dd($ids);
        $count = 0;
        Db::startTrans();
        try {
            $list = $this->model->where($this->model->getPk(), 'in', $ids)->select();
            foreach ($list as $item) {
                $count += $item->allowField(true)->isUpdate(true)->save($values);
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were updated'));
    }

     /**
     * 回收站
     *
     * @return string|Json
     * @throws \think\Exception
     */
    public function recyclebin()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }

        $list = $this->model->onlyTrashed()->order('weigh desc')->select()->toArray();
        $this->tree->init($list, 'pid');
        $this->cateList = $this->tree->getTreeList($this->tree->getTreeArray(0), 'name');
        
        $list = $this->cateList;
        foreach($list as $k => $v){
            $list[$k]['name']=preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", " ", strip_tags($v['name'])); //过滤空格
        }

        $list = array_values($list);
        
        $total = count($list);
        $result = array("total" => $total, "rows" => $list);

        return json($result);
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add($case_id = null)
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $params['case_id'] = $case_id ?? 0;

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }

        $params['abbr'] = ucfirst(strtolower($params['name']));
        
        if($params['range'] != ''){
            if(strpos($params['range'], '-') === false){
                $this->error("未输入''-''符号");
            }
        }

        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        
        if($params['range'] != ''){
            if(strpos($params['range'], '-') === false){
                $this->error("未输入''-''符号");
            }

            list($min, $max) = explode('-', $params['range']);
            if($min >= $max){
                $this->error("最小值不能大于等于最大值");
            }
        }

        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 删除
     *
     * @param $ids
     * @return void
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function del($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post("ids");
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }

        $arr = explode(',', $ids);
        $idsArr = [];
        for($i=0; $i<count($arr);$i++){
            $idsArr[] = $this->tree->getChildrenIds($arr[$i], true);
        }
        $idsArr = array_merge(...$idsArr);
        // dd($idsArr);
        $list = $this->model->where($pk, 'in', $idsArr)->select();

        $count = 0;
        Db::startTrans();
        try {
            foreach ($list as $item) {
                $count += $item->delete();
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }

    
    /**
     * 真实删除
     *
     * @param $ids
     * @return void
     */
    public function destroy($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post('ids');
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $arr = explode(',', $ids);
            
            $this->model->where('id|pid', 'in', $ids);
            $delList = $this->model->onlyTrashed()->select();
            foreach($delList as $item){
                if(!in_array($item['id'], $arr)){
                    array_push($arr, $item['id']);
                }
            }
            // dd($arr);
            $this->model->where($pk, 'in', $arr);
        }
        $count = 0;
        Db::startTrans();
        try {
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $item) {
                $count += $item->delete(true);
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }

    /**
     * 还原
     *
     * @param $ids
     * @return void
     */
    public function restore($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $ids = $ids ?: $this->request->post('ids');
        // dd($ids);
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $arr = explode(',', $ids);

            // 如果上级也被删了, 则一并恢复
            $this->model->where($pk, 'in', $ids);
            $delList = $this->model->onlyTrashed()->select();
            foreach ($delList as $item) {
                if($item['pid'] != 0 && !in_array($item['pid'], $arr)){
                    array_push($arr, $item['pid']);
                }
            }
            $this->model->where($pk, 'in', $arr);
        }
        $count = 0;
        Db::startTrans();
        try {
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $item) {
                $count += $item->restore();
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were updated'));
    }

}
