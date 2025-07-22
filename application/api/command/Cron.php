<?php

namespace app\api\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Cron extends Command
{
    protected function configure()
    {
        $this->setName('cron')->setDescription('定时任务');
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        $output->writeln(('start'));

        $exeMethod = [];

        $config = config('cron');
        $cronList = $config['cron_list'];
        $cronFormat = $config['cron_format'];
        
        foreach($cronFormat as $format){
            // $time = '1718821800';
            // $dateFormat = date($format, $time);
            $dateFormat = date($format, time());
            
            // 规定每几分钟执行的带有%
            if(strpos($dateFormat, '%')){
                list($i, $m) = explode('%', $dateFormat);
                $dateFormat = $i % $m == 0 ? 'i%' . $m : '';
            }

            if(empty($cronList[$dateFormat])){
                continue;
            }

            if(is_array($cronList[$dateFormat])){
                $exeMethod = array_merge($exeMethod, $cronList[$dateFormat]);
            }
        }
        
        // dd($exeMethod);
       
        foreach($exeMethod as $method => $class){
            
            // 检查函数是否可调用
            // if(!is_callable($method)){
            //     continue;
            // }
            
            call_user_func([new $class, $method]);
            // (new $method)->$key();
        }
        
        $output->writeln('end');
    }
}