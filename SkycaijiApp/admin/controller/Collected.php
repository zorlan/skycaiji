<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

namespace skycaiji\admin\controller;

class Collected extends BaseController {
	public function listAction(){
		$taskName=input('task_name');
		$page=input('p/d',1);
   		$page=max(1,$page);
   		$mcollected=model('Collected');
   		$mtask=model('Task');
   		$cond=array();
		$search=array();
		$null_task=false;
   		if(!empty($taskName)){
   			$search['task_name']=$taskName;
   			
   			$searchTasks=$mtask->field('`id`,`name`')->where(array('name'=>array('like',"%{$taskName}%")))->column('name','id');
   			if(!empty($searchTasks)){
   				$cond['task_id']=array('in',array_keys($searchTasks));
   			}else{
   				$null_task=true;
   			}
   		}
   		$search['num']=input('num/d',200);
   		$search['url']=input('url','','trim');
   		if(!empty($search['url'])){
   			$cond['url']=array('like','%'.addslashes($search['url']).'%');
   		}
   		$search['release']=input('release');
   		if(!empty($search['release'])){
   			$cond['release']=$search['release'];
   		}
   		$search['status']=input('status');
   		if(!empty($search['status'])){
   			if($search['status']==1){
   				
   				$cond['target']=array('<>','');
   			}elseif($search['status']==2){
   				
   				$cond['error']=array('<>','');
   			}
   		}
   		$dataList=array();
   		$taskList=array();
   		if(!$null_task){
	   		$count=$mcollected->where($cond)->count();
	   		$limit=$search['num'];
	   		if($count>0){
	   			
	   			$dataList=$mcollected->where($cond)->order('id desc')->paginate($limit,false,paginate_auto_config());
	   			
	   			$pagenav=$dataList->render();
	   			$this->assign('pagenav',$pagenav);
	   			$dataList=$dataList->all();
	   			$dataList=empty($dataList)?array():$dataList;
	   			
	   			$taskIds=array();
	   			foreach ($dataList as $itemK=>$item){
	   				$taskIds[$item['task_id']]=$item['task_id'];
	   				if(preg_match('/^\w+\:\/\//', $item['target'])){
	   					
	   					$dataList[$itemK]['target']='<a href="'.$item['target'].'" target="_blank">'.$item['target'].'</a>';
	   				}
	   			}
	   			if(!empty($taskIds)){
	   				$taskList=model('Task')->where(array('id'=>array('in',$taskIds)))->column('name','id');
	   			}
	   		}
	   		$GLOBALS['content_header']=lang('collected_list');
	   		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Collected/list'),'title'=>lang('collected_list'))));
   		}
   		$this->assign('search',$search);
		$this->assign('dataList',$dataList);
	   	$this->assign('taskList',$taskList);
   		return $this->fetch();
	}
	/*清理失败的数据*/
	public function clearErrorAction(){
		model('Collected')->where("`error` is not null and `error`<>''")->delete();
		$this->success('清理完成','Admin/Collected/list');
	}
	/**
	 * 操作
	 */
	public function opAction(){
		$id=input('id/d',0);
		$op=input('op');
		$ops=array('item'=>array('delete'),'list'=>array('deleteall'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])){
			
			$this->error(lang('invalid_op'));
		}
		 
		$mcollected=model('Collected');
		if(in_array($op,$ops['item'])){
			
			$collectedData=$mcollected->getById($id);
			if(empty($collectedData)){
				$this->error(lang('empty_data'));
			}
		}
		if($op=='delete'){
			
			$mcollected->where(array('id'=>$id))->delete();
			$this->success(lang('delete_success'));
		}elseif($op=='deleteall'){
			
			$ids=input('ids/a',0,'intval');
			if(is_array($ids)&&count($ids)>0){
				$mcollected->where(array('id'=>array('in',$ids)))->delete();
			}
    		$this->success(lang('op_success'),'list');
		}
	}
}