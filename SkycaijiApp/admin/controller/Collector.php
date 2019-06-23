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

class Collector extends BaseController {
    public function indexAction(){
        return $this->fetch();
    }
    
    public function setAction(){
    	$taskId=input('task_id/d',0);
    	$mtask=model('Task');
	    $mcoll=model('Collector');
    	$taskData=$mtask->getById($taskId);
    	if(empty($taskData)){
    		$this->error(lang('task_error_empty_task'));
    	}
    	if(empty($taskData['module'])){
    		
    		$this->error(lang('task_error_null_module'));
    	}
    	if(!in_array($taskData['module'],config('allow_coll_modules'))){
    		$this->error(lang('coll_error_invalid_module'));
    	}
    	$collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
    	if(request()->isPost()){
    		$effective=input('effective');
    		if(empty($effective)){
    			
    			$this->error(lang('coll_error_empty_effective'));
    		}
    		$name=trim(input('name'));
    		$module=trim(input('module'));
    		$module=strtolower($module);
    		if(!in_array($module,config('allow_coll_modules'))){
    			$this->error(lang('coll_error_invalid_module'));
    		}
    		$config=input('post.config/a',null,'trim');
    		$config=array_array_map('trim',$config);

    		
    		$acoll=controller('admin/C'.$module,'event');
    		$config=$acoll->setConfig($config);
    		
    		$newColl=array('name'=>$name,'module'=>$module,'task_id'=>$taskId,'config'=>serialize($config),'uptime'=>NOW_TIME);
    		$collId=$collData['id'];
    		if(empty($collData)){
    			$collId=$mcoll->add_new($newColl);
    		}else{
    			$mcoll->edit_by_id($collId,$newColl);
    		}
    		if($collId>0){
    			$tab_link=trim(input('tab_link'),'#');
    			$this->success(lang('op_success'),'Collector/set?task_id='.$taskId.($tab_link?'&tab_link='.$tab_link:''));
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		if(!empty($collData)){
	    		$collData['config']=unserialize($collData['config']);
    		}
	    	$GLOBALS['content_header']=lang('coll_set').lang('separator').lang('task_module_'.$taskData['module']);
	    	$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Task/edit?id='.$taskData['id']),'title'=>lang('task').lang('separator').$taskData['name']),lang('coll_set')));
	    	$this->assign('collData',$collData);
	    	$this->assign('taskData',$taskData);
	    	return $this->fetch();
    	}
    }
    /*列表*/
    public function listAction(){
    	$page=max(1,input('p/d',0));
    	$module=input('module');
    	$cond=array();
    	$taskCond=array();
    	if(!empty($module)){
    		$cond=array('module'=>$module);
    	}
    	
    	$mcoll=model('Collector');
    	$limit=20;
    	$count=$mcoll->where($cond)->count();
    	$collList=$mcoll->where($cond)->paginate($limit,false,paginate_auto_config()); 

    	$pagenav = $collList->render();
    	$this->assign('pagenav',$pagenav);
    	$collList=$collList->all();
    	$collList=empty($collList)?array():$collList;
    	if($count>0){
    		$taskIds=array();
    		foreach ($collList as $coll){
    			$taskIds[$coll['task_id']]=$coll['task_id'];
    		}
    		if(!empty($taskIds)){
    			
    			$taskCond['id']=array('in',$taskIds);
    			$taskNames=model('Task')->where($taskCond)->column('name','id');
    			$this->assign('taskNames',$taskNames);
    		}
    	}
    	
    	$this->assign('collList',$collList);
		return $this->fetch('list'.(input('tpl')?'_'.input('tpl'):''));
    }
    /*保存到云端*/
    public function save2storeAction(){
    	$coll_id=input('coll_id/d',0);
    	$mcoll=model('Collector');
    	$collData=$mcoll->where(array('id'=>$coll_id))->find();
    	if(empty($collData)){
    		$this->error(lang('coll_error_empty_coll'));
    	}
    	$collData=$collData->toArray();
    	if(!in_array($collData['module'],config('allow_coll_modules'))){
    		$this->error(lang('coll_error_invalid_module'));
    	}
    	$config=unserialize($collData['config']);
    	if(empty($config)){
    		$this->error('规则不存在');
    	}
    	if(empty($config['source_url'])){
    		$this->error('请先完善起始页网址！');
    	}
    	if(empty($config['field_list'])){
    		$this->error('请先完善字段列表！');
    	}
    	$this->assign('collData',$collData);
    	return $this->fetch();
    }
    /*导出规则*/
    public function exportAction(){
    	$coll_id=input('coll_id/d',0);
    	$mcoll=model('Collector');
    	$collData=$mcoll->where(array('id'=>$coll_id))->find();
    	if(empty($collData)){
    		$this->error(lang('coll_error_empty_coll'));
    	}
    	$config=unserialize($collData['config']);
    	if(empty($config)){
    		$this->error('规则不存在');
    	}
    	$taskData=model('Task')->getById($collData['task_id']);
    	$name=($collData['name']?$collData['name']:$taskData['name']);
    	$module=strtolower($collData['module']);
    	
    	set_time_limit(600);
    	$collector=array(
    		'name'=>$name,
    		'module'=>$module,
    		'config'=>serialize($config),
    	);
    	$txt='/*skycaiji-collector-start*/'.base64_encode(serialize($collector)).'/*skycaiji-collector-end*/';
    	$name='规则_'.$name;
    	ob_start();
    	header("Expires: 0" );
    	header("Pragma:public" );
    	header("Cache-Control:must-revalidate,post-check=0,pre-check=0" );
    	header("Cache-Control:public");
    	header("Content-Type:application/octet-stream" );
    	
    	header("Content-transfer-encoding: binary");
    	header("Accept-Length: " .mb_strlen($txt));
    	if (preg_match("/MSIE/i", $_SERVER["HTTP_USER_AGENT"])) {
    		header('Content-Disposition: attachment; filename="'.urlencode($name).'.skycaiji"');
    	}else{
    		header('Content-Disposition: attachment; filename="'.$name.'.skycaiji"');
    	}
    	echo $txt;
    	ob_end_flush();
    }
}