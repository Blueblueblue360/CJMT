<?php
/**
 * Created by PhpStorm.
 * User: zhoujun
 * Date: 2018/3/31
 * Time: 15:36
 */

namespace app\admin\model;


use think\Cache;
use think\Model;

class CronTaskLogModel extends Model
{
    private static $instance;
    const CRONTAB_LOG = 'cmd_execute_';

    private function __clone(){} //禁止被克隆
    /**
     * 单例
     */
    public static function getInstance()
    {
        if(!(self::$instance instanceof self)){
            self::$instance = new static();
        }
        return self::$instance;
    }
    /**
     * 添加一条数据
     */
    public static function insertLog($insert_data)
    {
        if(self::getInstance()->insertAll($insert_data)) {
            return true;
        }
        return false;
    }

    public static function deletCache($day)
    {
        if(self::getInstance()->where('create_time','<',date('Y-m-d 00:00:00',strtotime("-$day day")))->delete()) {
            return true;
        }
        return false;
    }

}