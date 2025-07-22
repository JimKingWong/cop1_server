<?php

namespace app\api\controller\blogger;

use app\common\controller\Api;

/**
 * 工作台接口
 * @ApiSector (博主后台)
 */
class Workbench extends Api
{
    protected $noNeedLogin = ['init'];
    protected $noNeedRight = ['*'];

    /**
     * 工作台初始化
     */
    public function init()
    {
        
        $retval = [
            
        ];
        $this->success(__('请求成功'), $retval);
    }
    
    /**
     * 控制台数据
     * @ApiParams (name="origin",description="站点")
     */
    public function dashboard()
    {
        $service = new \app\common\service\blogger\User();
        $service->dashboard();
    }

    /**
     * 每日统计
     * @ApiMethod (GET)
     * @ApiParams (name="limit",description="每页显示数量")
     * @ApiParams (name="page",description="当前页数")
     * @ApiParams (name="date",description="日期, 如2025-07-08")
     */
    public function daybook()
    {
        $service = new \app\common\service\blogger\User();
        $service->daybook();
    }
}
