<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/29
 * Time: 11:50
 */

namespace app\admin\model;


use think\Db;
use think\Model;
use app\admin\model\CateModel;

class CronTask extends Model
{
    private static $instance;
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

    public static function getBelongCateName($cron_task_id){
        $cate_id = self::getNameById($cron_task_id);
        if (is_null($cate_id)){
            return false;
        }
        return CateModel::getNameById($cate_id);
    }

    public static function getNameById($id)
    {
//        return self::getInstance()->query("select cate_id from jt_cron_task where id={$id}");
        return self::getInstance()->where('id', $id)->value('cate_id');
    }

    public static function getValueById($id_field,$id,$value_field){
//        return self::getInstance()->query("select {$value_field} from jt_cron_task where {$id_field}={$id}");
        return self::getInstance()->where($id_field, $id)->value($value_field);
    }

    public static function getById($id_field, $id){
        return self::getInstance()->query("select * from jt_cron_task where {$id_field}={$id}");
//        return self::getInstance()->where($id_field, $id)->select();
    }

    public static function getCountById($id_field,$id){
        return self::getInstance()->query("select count(*) from jt_cron_task where {$id_field}={$id}");
    }
}