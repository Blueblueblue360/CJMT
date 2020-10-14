<?php

namespace app\timer\lib;


use think\Log;

class TaskManager
{
    const TASK_KEY = 'cmd_cron_task_list';
    const TASK_IS_CHANGE = 'cmd_cron_task_is_change';

    /**
     * @param $tasks
     * @return bool 是否有写到缓存中
     * 如果任务内容改变，则重新写到缓存中
     */
    public static function loadTask($tasks){
        $old_task = self::getTasks();
        if(md5(serialize($old_task)) != md5(serialize($tasks))){
            cache(self::TASK_KEY, $tasks);
            return true;
        }
        return false;
    }


    public static function isChange($tag = null){
        if(is_null($tag)){
            return cache(self::TASK_IS_CHANGE);
        }
        cache(self::TASK_IS_CHANGE, $tag);
    }

    /**
     * @return mixed
     * 读取缓存中的任务列表
     */
    public static function getTasks(){
        return cache(self::TASK_KEY);
    }

    /**
     * @param $tasks
     * 将任务直接写到缓存中
     */
    public static function setTasks($tasks){
        cache(self::TASK_KEY,$tasks);
    }

    /**
     * 将任务标记为无
     */
    public static function clear(){
        self::loadTask([]);
    }
}