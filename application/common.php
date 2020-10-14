<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

use app\admin\model\CronTaskLogModel;
use think\Cache;

error_reporting(E_ERROR | E_PARSE );

function encryPassword($password, $salt){
    return md5(md5($password . $salt));
}

function getSetting($name){
    $key = 'jtimer_setting_'.$name;
    $value = cache($key);

    if($value){
        return $value;
    }

    $value = \think\Db::name('setting')->where('name',$name)->value('value');
    cache($key,$value);
    return $value;
}

// 执行日志缓存持久化
function cachePersist(){
    if (Cache::get('cron_task_log_times',0) > 2){
        $insert_arr = array();
        $start_time = time() - 150;
        $end_time = time();
        for ($i = $start_time; $i < $end_time; $i++){
            $temp_value = Cache::pull(CronTaskLogModel::CRONTAB_LOG.$i);
            if (! is_null($temp_value)){
                $item_arr = explode(';', $temp_value);
                foreach ($item_arr as $item)
                    empty($item)?:$insert_arr[] = json_decode($item, true);
            }
        }
        dump($insert_arr);
        CronTaskLogModel::insertLog($insert_arr);
        Cache::set('cron_task_log_times',0);
    } else {
        Cache::inc('cron_task_log_times');
    }
}
