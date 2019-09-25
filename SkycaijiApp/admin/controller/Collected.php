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
	   		$GLOBALS['_sc']['p_name']=lang('collected_list');
			$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Collected/list'),'title'=>'已采集数据'),array('url'=>url('Collected/list'),'title'=>'数据列表')));
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
	/*图表显示*/
	public function chartAction(){
		$GLOBALS['_sc']['p_name']='已采集数据：统计图表';
		$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Collected/list'),'title'=>'已采集数据'),array('url'=>url('Collected/chart'),'title'=>'统计图表')));
		
		return $this->fetch();
	}
	
	public function chartOpAction(){
		$op=input('op');
		$mcollected=model('Collected');
		$nowTime=time();
		$nowYear=intval(date('Y',$nowTime));
		$nowMonth=intval(date('m',$nowTime));
		$nowDay=intval(date('d',$nowTime));
		if(in_array($op,array('today','this_month','this_year','years'))){
			$dataList = array (
				'name'=>array(),
				'success' => array (),
				'failed' => array ()
			);
			$dateList=array();
			if($op=='today'){
				
				for($i=0;$i<24;$i++){
					$start=$nowYear.'-'.$nowMonth.'-'.$nowDay.' '.$i.':00';
					$end=strtotime($start.' +1 hour')-1;
					$start=strtotime($start);
					$dateList[$i+1]=array('name'=>($i+1).'点','start'=>$start,'end'=>$end);
				}
			}if($op=='this_month'){
				
				$endDay=date('d',strtotime("{$nowYear}-{$nowMonth}-1 +1 month -1 day"));
				$endDay=intval($endDay);
				for($i=1;$i<=$endDay;$i++){
					$start=$nowYear.'-'.$nowMonth.'-'.$i;
					$end=strtotime($start.' +1 day')-1;
					$start=strtotime($start);
					$dateList[$i]=array('name'=>$i.'号','start'=>$start,'end'=>$end);
				}
			}elseif($op=='this_year'){
				
				for($i=1;$i<=12;$i++){
					$start=$nowYear.'-'.$i.'-1';
					$end=strtotime($start.' +1 month')-1;
					$start=strtotime($start);
					$dateList[$i]=array('name'=>$i.'月','start'=>$start,'end'=>$end);
					
				}
			}elseif($op=='years'){
				
				$minTime=$mcollected->min('addtime');
				$minYear=intval(date('Y',$minTime));
				for($i=$nowYear;$i>=$minYear;$i--){
					$start=$i.'-1-1';
					$end=strtotime($start.' +1 year')-1;
					$start=strtotime($start);
					$dateList[$i]=array('name'=>$i.'年','start'=>$start,'end'=>$end);
				}
			}
			foreach ($dateList as $k=>$v){
				$dataList['name'][$k]=$v['name'];
				
				$dataList['success'][$k]=$mcollected->where(array(
					'addtime'=>array('between',array($v['start'],$v['end'])),
					'target'=>array('<>','')
				))->count();
				
				
				$dataList['failed'][$k]=$mcollected->where(array(
					'addtime'=>array('between',array($v['start'],$v['end'])),
					'error'=>array('<>','')
				))->count();
			}
			
			$this->success('',null,$dataList);
		}elseif($op=='release'){
			
			$dataList = array (
				'name'=>array(),
				'success' => array (),
				'failed' => array (),
			);
			
			foreach(config('release_modules') as $module){
				if($module=='api'){
					
					continue;
				}
				$dataList['name'][$module]=lang('rele_module_'.$module);
				
				$dataList['success'][$module]=$mcollected->where(array(
					'release'=>$module,
					'target'=>array('<>','')
				))->count();
				
				
				$dataList['failed'][$module]=$mcollected->where(array(
					'release'=>$module,
					'error'=>array('<>','')
				))->count();
			}
			$this->success('',null,$dataList);
		}elseif($op=='task'){
			
			$dataList = array (
				'name'=>array(),
				'total' => array (),
			);
			
			
			$list=$mcollected->field('task_id,count(id) as ct')->group('task_id')->having('count(id)>0')->select();
			$taskIds=array();
			foreach($list as $v){
				$taskIds[$v['task_id']]=$v['task_id'];
			}
			if($taskIds){
				$taskList=model('Task')->where('id','in',$taskIds)->column('name','id');
			}
			
			foreach($list as $v){
				if(isset($taskList[$v['task_id']])){
					
					$dataList['name'][$v['task_id']]=$taskList[$v['task_id']];
					$dataList['total'][$v['task_id']]=$v['ct'];
				}
			}
			
			$this->success('',null,$dataList);
		}
	}
}