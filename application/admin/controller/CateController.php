<?php

namespace app\admin\controller;

use think\Request;
use app\admin\model\CateModel;
use app\admin\model\CronTaskModel;

class CateController extends AdminBaseController
{

    /**
     * @param string $model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lists($model = '',$return = false)
    {
//        $list = CateModel::getInstance()
//            ->alias('c')
//            ->join('__CRON_TASK__ ct', 'c.id = ct.cate_id', 'LEFT')
//            ->group('c.id')
//            ->field('c.id,c.name,count(ct.id) as count')
//            ->select();
        $list = CateModel::getJoin();
        $this->tableSet($list);
    }

    public function doDel($model = '')
    {
        $request = Request::instance();
        $id = $request->param('ids');
//        $count = CronTaskModel::getInstance()->where('cate_id',$id)->count();
        $count = CronTaskModel::getCountById('cate_id',$id);
        if($count > 0){
            $this->error('请先删除该分类下的所有任务');
        }

        parent::doDel($model);
    }


}