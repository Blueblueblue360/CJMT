<?php

namespace app\admin\model;

use think\Model;

class Cate extends Model
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

    public static function getNameById($id)
    {
//        return self::getInstance()->query("select name from jt_cate where id={$id}");
        return self::getInstance()->where('id', $id)->value('name');
    }

    public static function getAll()
    {
        return self::getInstance()->query("select * from jt_cate");
    }

    public static function getJoin()
    {
        return self::getInstance()->query("select c.id,c.name,count(ct.id) as count from jt_cate c left join jt_cron_task ct on c.id = ct.cate_id group by c.id");
    }

}