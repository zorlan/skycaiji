<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

namespace skycaiji\admin\controller;

class Dataapi extends BaseController {
    public function listAction(){
        $page=input('p/d',1);
        $page=max(1,$page);
        
        $search=array(
            'id'=>input('id/d',0),
            'name'=>input('name','','trim'),
            'ds'=>input('ds','')
        );
        if($search['id']<=0){
            unset($search['id']);
        }
        
        $mds=model('Dataset');
        $mda=model('Dataapi');
        $cond=array();
        if($search['id']){
            $cond['id']=$search['id'];
        }
        if($search['name']){
            $cond['name']=array('like','%'.$search['name'].'%');
        }
        if($search['ds']){
            if(is_numeric($search['ds'])){
                $cond['ds_id']=$search['ds'];
            }else{
                $dsIds=$mds->where('name','like','%'.$search['ds'].'%')->column('id','id');
                if($dsIds){
                    $cond['ds_id']=array('in',$dsIds);
                }
            }
        }
        $daList=array();
        $limit=50;
        $daList=$mda->where($cond)->order('sort desc')->paginate($limit,false,paginate_auto_config());
        $pagenav=$daList->render();
        $daList=$daList->all();
        
        $dsNames=array();
        foreach ($daList as $k=>$v){
            $dsNames[$v['ds_id']]=$v['ds_id'];
        }
        if($dsNames){
            $dsNames=model('Dataset')->where('id','in',$dsNames)->column('name','id');
        }
        
        $this->set_html_tags(
            '数据接口',
            '数据接口'.($cond?'：搜索结果':''),
            breadcrumb(array(array('url'=>url('dataapi/list'),'title'=>'数据接口'),array('url'=>url('dataapi/list'),'title'=>'列表')))
        );
        
        $this->assign('dsNames',$dsNames);
        $this->assign('search',$search);
        $this->assign('daList',$daList);
        $this->assign('pagenav',$pagenav);
        return $this->fetch();
    }
    public function opAction(){
        $op=input('op','');
        $mda=model('Dataapi');
        if($this->request->isPost()){
            if(empty($op)){
                
                $newsort=input('newsort/a',array(),'intval');
                foreach ($newsort as $id=>$sort){
                    $mda->where('id',$id)->update(array('sort'=>$sort));
                }
                $this->success('操作成功','dataapi/list');
            }elseif($op=='delete'){
                $id=input('id/d',0);
                $mda->where('id',$id)->delete();
                $this->success('已删除','');
            }elseif($op=='status'){
                $id=input('id/d',0);
                $status=input('status/d',0);
                $status=$status?0:1;
                $mda->where('id',$id)->update(array('status'=>$status));
                $this->success($status?'已开启':'已关闭','',array('status'=>$status));
            }
        }else{
            $this->error('无效操作','');
        }
    }
    public function setAction(){
        $id=input('id/d');
        $daData=array();
        $mda=model('Dataapi');
        $mds=model('Dataset');
        if($id){
            $daData=$mda->getById($id);
        }
        if($this->request->isPost()){
            $newData=array(
                'name'=>input('name'),
                'route'=>input('route','','trim'),
                'desc'=>input('desc'),
                'sort'=>input('sort/d',0),
                'status'=>input('status/d',0),
                'ds_id'=>input('ds_id/d',0)
            );
            if(empty($newData['name'])){
                $this->error('请输入名称');
            }
            if(empty($daData)||$newData['name']!=$daData['name']){
                if($mda->where('name',$newData['name'])->count()>0){
                    $this->error('名称已存在');
                }
            }
            if(!empty($newData['route'])){
                if(!preg_match('/^[\w\-]+$/',$newData['route'])){
                    $this->error('网址别名只能由字母、数字、横线和下划线组成！');
                }
                if(empty($daData)||$newData['route']!=$daData['route']){
                    if($mda->where('route',$newData['route'])->count()>0){
                        $this->error('网址别名已存在');
                    }
                }
            }
            $config=trim_input_array('config');
            init_array($config);
            $config['page_per']=$config['page_per']?:'';
            $config['page_max']=$config['page_max']?:'';
            if(!empty($config['page_name'])){
                $checkRst=$mda->check_url_param_name($config['page_name']);
                if(!$checkRst['success']){
                    $this->error('分页'.$checkRst['msg']);
                }
            }
            if($newData['ds_id']<=0){
                $this->error('请绑定数据集');
            }
            
            $dsData=$mds->getById($newData['ds_id']);
            
            $conds=trim_input_array('conds');
            $conds=$mda->filter_conds($conds);
            foreach ($conds['name'] as $k=>$v){
                if(!empty($v)){
                    $checkRst=$mda->check_url_param_name($v);
                    if(!$checkRst['success']){
                        $dsField=$mds->get_config_field($dsData,$conds['field'][$k]);
                        $this->error('数据集字段“'.$dsField['name'].'”'.$checkRst['msg']);
                    }
                }
            }
            $config['conds']=$conds;
            $newData['config']=serialize($config);
            if(empty($daData)){
                
                $id=$mda->strict(false)->insert($newData,false,true);
            }else{
                
                $mda->strict(false)->where(array('id'=>$id))->update($newData);
            }
            $this->success('操作成功','dataapi/set?id='.$id);
        }else{
            $title=$daData?'编辑':'添加';
            $this->set_html_tags(
                $title.'数据接口'.($daData?(':'.$daData['name']):''),
                $title.'数据接口'.($daData?('：'.$daData['name'].'（id:'.$id.'）'):''),
                breadcrumb(array(array('url'=>url('dataapi/list'),'title'=>'数据接口'),array('url'=>url('dataapi/set?id='.($daData?$daData['id']:'')),'title'=>($daData?$daData['name']:$title))))
            );
            $config=array();
            $apiUrl='';
            $isSafeMode=false;
            $groupConds=array();
            $fieldNames=array();
            if($daData){
                $daData['name']=htmlspecialchars_decode($daData['name'],ENT_QUOTES);
                $daData['desc']=htmlspecialchars_decode($daData['desc'],ENT_QUOTES);
                $config=$daData['config'];
                $apiUrl=url('api/data/'.($daData['route']?$daData['route']:$daData['id']),'',false,true);
                if(!empty($config['api_key'])){
                    
                    $apiUrl.=(strpos($apiUrl,'?')===false?'?':'&').'k=';
                    if(empty($config['api_mode'])){
                        $apiUrl.=md5($config['api_key']);
                    }else{
                        $isSafeMode=true;
                    }
                }
                $apiUrl=htmlspecialchars($apiUrl);
                
                $dsData=$daData['ds_id']?$mds->getById($daData['ds_id']):array();
                if($config['conds']&&$config['conds']['field']){
                    foreach($config['conds']['field'] as $k=>$v){
                        $dsField=$mds->get_config_field($dsData,$v);
                        $fieldNames[$v]=$dsField['name'];
                    }
                }
                $groupConds=$mda->get_group_conds($config['conds']);
            }
            $fieldNames['id']='id';
            
            $this->assign('daData',$daData);
            $this->assign('config',$config);
            $this->assign('apiUrl',$apiUrl);
            $this->assign('isSafeMode',$isSafeMode);
            $this->assign('groupConds',$groupConds);
            $this->assign('fieldNames',$fieldNames);
            $this->assign('condOps',$mda->get_cond_op(null));
            return $this->fetch();
        }
    }
    
    public function datasetAction(){
        $dsId=input('ds_id/d',0);
        $mds=model('Dataset');
        $dsData=$mds->getById($dsId);
        $fields=$dsData['config']['fields'];
        init_array($fields);
        foreach ($fields as $k=>$v){
            $fields[$k]=$v['name'];
        }
        $fields=array_merge(array('id'=>'id'),$fields);
        $this->success('','',array('fields'=>$fields,'name'=>$dsData['name'],'id'=>$dsData['id']));
    }
}