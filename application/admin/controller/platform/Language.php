<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Language extends Backend
{

    /**
     * Language模型对象
     * @var \app\admin\model\platform\Language
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\platform\Language;
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


     /**
     * 设为默认方案
     */
    public function setDefault($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if($row->status == 0){
            $this->error('请先启用状态');
        }

        $list = $this->model->select();

        $count = 0;
        foreach($list as $val){
            if($val->id == $ids){
                $val->is_default = 1;
            }else{
                $val->is_default = 0;
            }
            $count += $val->save();
        }

        $this->success('成功设置 ('. $row['title'] .') 为默认语音');
    }
}
