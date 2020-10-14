<?php

namespace app\admin\controller;


use app\admin\model\CronTaskLogModel;
use app\admin\model\CronTaskModel;
use think\Request;
use app\admin\model\CateModel;

class CronTaskLogController extends AdminBaseController
{
    public function index()
    {
        $this->assign('cates',CateModel::getInstance()->select());
        return parent::index();
    }

    public function lists($model = '', $return = false)
    {
        $data = parent::lists($model, true);
        foreach ($data['data'] as &$item){
            $item['cate_name'] = CronTaskModel::getBelongCateName($item['ct_id']);
            $item['remark'] = CronTaskModel::getValueById('id',$item['ct_id'],'remark');
        }
        $this->tableSet($data['data'],$data['count']);
    }

    protected function getWhere($model = '')
    {
        $request = Request::instance();

        $cmd = $request->param('cmd');
        $cate_id = $request->param('cate_id');
        $ct_id = $request->param('ct_id');

        if($cmd){
            $where['cmd'] = ['like',"%{$cmd}%"];
        }

        if($ct_id){
            $where['ct_id'] = $ct_id;
        }

        if($cate_id){
            $task_ids = CronTaskModel::getInstance()->where('cate_id',$cate_id)->column('id');
            $where['ct_id'] = ['in',$task_ids];
        }
        return $where;
    }

}