<?php

namespace app\common\service;

/**
 * 收发平台服务数据
 */
class Develop extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取开发平台配置
     */
    public function developConfig()
    {
        $params = $this->request->post();
        
        // dd(json_decode($params, true));
        // dd($params);
        $filename = 'develop';

        // $params = json_decode($params, true);
        
        // 写入配置文件
        file_put_contents(
            CONF_PATH . 'extra' . DS . $filename . '.php',
            '<?php' . "\n\nreturn " . var_export_short($params, true) . ";\n"
        );
        $this->success('写入成功');
    }
}