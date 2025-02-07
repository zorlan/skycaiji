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

use skycaiji\admin\model\CacheModel;

class Collected extends BaseController {
	public function listAction(){
		$taskName=input('task_name');
   		$mcollected=model('Collected');
   		$mtask=model('Task');
   		$cond=array();
		$search=array();
		$null_task=false;
		$navTips='';
   		if(!empty($taskName)){
   		    $navTips='搜索任务名：'.$taskName;
   			$search['task_name']=$taskName;
   			
   			$searchTasks=$mtask->field('`id`,`name`')->where(array('name'=>array('like',"%{$taskName}%")))->column('name','id');
   			if(!empty($searchTasks)){
   				$cond['task_id']=array('in',array_keys($searchTasks));
   			}else{
   				$null_task=true;
   			}
   		}else{
   		    
   		    $taskId=input('task_id');
   		    if(!empty($taskId)){
   		        $taskData=$mtask->where('id',$taskId)->find();
   		        if(!empty($taskData)){
   		            $search['task_name']=$taskData['name'];
   		            $navTips='任务：'.$taskData['name'];
   		            $cond['task_id']=$taskData['id'];
   		        }
   		    }
   		}
   		
   		$search['begin']=input('begin','');
   		$search['end']=input('end','');
   		if($search['begin']&&$search['end']){
   		    $cond['addtime']=array('between',array(strtotime($search['begin']),strtotime($search['end'])));
   		}elseif($search['begin']){
   		    $cond['addtime']=array('>=',strtotime($search['begin']));
   		}elseif($search['end']){
   		    $cond['addtime']=array('<=',strtotime($search['end']));
   		}
   		
   		$mcache=CacheModel::getInstance();
   		$search['num']=input('num/d');
   		if($search['num']<=0){
   		    
   		    $search['num']=$mcache->getCache('action_collected_list_num','data');
   		    $search['num']=intval($search['num']);
   		    if($search['num']<=0){
   		        $search['num']=200;
   		    }
   		}
   		$mcache->setCache('action_collected_list_num',$search['num']);
   		
   		$search['url']=input('url','','trim');
   		if(!empty($search['url'])){
   			$cond['url']=array('like','%'.addslashes($search['url']).'%');
   		}
   		$search['release']=input('release');
   		if(!empty($search['release'])){
   			$cond['release']=$search['release'];
   		}
   		$search['status']=input('status');
   		if(!is_empty($search['status'],true)){
   		    $cond['status']=intval($search['status']);
   		}
   		$dataList=array();
   		$taskList=array();
   		if(!$null_task){
   		    $condJoin=array();
   		    if($cond['url']){
   		        foreach ($cond as $k=>$v){
   		            $k=($k=='url'?'i.':'c.').$k;
   		            $condJoin[$k]=$v;
   		        }
   		    }
   		   
	   		$limit=$search['num'];
   		    
   		    if($condJoin){
   		        $dataList=$mcollected->alias('c')->join($mcollected->collected_info_tname().' i','c.id=i.id')->field('c.id')->where($condJoin)->order('c.id desc')->paginate($limit,false,paginate_auto_config());
   		    }else{
   		        $dataList=$mcollected->field('id')->where($cond)->order('id desc')->paginate($limit,false,paginate_auto_config());
   		    }
   			$pagenav=$dataList->render();
   			$this->assign('pagenav',$pagenav);
   			$dataList=$dataList->all();
   			if($dataList){
   			    $cids=array();
   			    foreach ($dataList as $k=>$v){
   			        $cids[]=$v['id'];
   			    }
   			    $dataList1=$mcollected->where('id','in',$cids)->column('*','id');
   			    $dataList=array();
   			    
   			    foreach ($cids as $cid){
   			        $dataList[$cid]=$dataList1[$cid];
   			        unset($dataList1[$cid]);
   			    }
   			}else{
   			    $dataList=array();
   			}
   			
   			$dataList=$mcollected->getInfoDatas($dataList);
   			
   			$taskIds=array();
   			foreach ($dataList as $itemK=>$item){
   				$taskIds[$item['task_id']]=$item['task_id'];
   				if(\util\Funcs::is_right_url($item['target'])){
   					
   					$dataList[$itemK]['target']='<a href="'.$item['target'].'" target="_blank">'.$item['target'].'</a>';
   				}
   			}
   			if(!empty($taskIds)){
   				$taskList=model('Task')->where(array('id'=>array('in',$taskIds)))->column('name','id');
   			}
	   		
   		}
   		$this->set_html_tags(
   		    lang('collected_list'),
   		    lang('collected_list').' <small><a href="'.url('collected/chart').'">统计图表</a></small>',
   		    breadcrumb(array(array('url'=>url('collected/list'),'title'=>'已采集数据'),array('url'=>url('collected/list'),'title'=>$navTips?$navTips:'数据列表')))
   		);
   		$this->assign('search',$search);
		$this->assign('dataList',$dataList);
	   	$this->assign('taskList',$taskList);
   		return $this->fetch();
	}
	/*清理失败的数据*/
	public function clearErrorAction(){
	    if(request()->isPost()){
	        $release=input('release/a');
	        init_array($release);
	        if(in_array('all', $release)){
	            
	            model('Collected')->deleteByCond(array('status'=>0));
	        }else{
	            
	            model('Collected')->deleteByCond(array('release'=>array('in',$release),'status'=>0));
	        }
	        $this->success('清理完成','admin/collected/list');
	    }else{
	        return $this->fetch('clear_error');
	    }
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
			
		    $mcollected->deleteByCond(array('id'=>$id));
			$this->success(lang('delete_success'));
		}elseif($op=='deleteall'){
			
			$ids=input('ids/a',array(),'intval');
			if(is_array($ids)&&count($ids)>0){
			    $mcollected->deleteByCond(array('id'=>array('in',$ids)));
			}
    		$this->success(lang('op_success'),'list');
		}
	}
	/*图表显示*/
	public function chartAction(){
	    $this->set_html_tags(
	        '已采集数据：统计图表',
	        '已采集数据：统计图表 <small><a href="'.url('collected/list').'">数据列表</a></small>',
	        breadcrumb(array(array('url'=>url('collected/list'),'title'=>'已采集数据'),array('url'=>url('collected/chart'),'title'=>'统计图表')))
	    );
		return $this->fetch();
	}
	
	public function chartOpAction(){
		$op=input('op');
		$mcollected=model('Collected');
		$nowTime=time();
		$nowYear=intval(date('Y',$nowTime));
		$nowMonth=intval(date('m',$nowTime));
		$nowDay=intval(date('d',$nowTime));
		if(in_array($op,array('today','yesterday','this_month','last_month','this_year','last_year','years'))){
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
			}elseif($op=='yesterday'){
			    
			    $yesterday=date('Y-m-d',strtotime("{$nowYear}-{$nowMonth}-{$nowDay} -1 day"));
			    for($i=0;$i<24;$i++){
			        $start=$yesterday.' '.$i.':00';
			        $end=strtotime($start.' +1 hour')-1;
			        $start=strtotime($start);
			        $dateList[$i+1]=array('name'=>($i+1).'点','start'=>$start,'end'=>$end);
			    }
			}elseif($op=='this_month'){
				
				$endDay=date('d',strtotime("{$nowYear}-{$nowMonth}-1 +1 month -1 day"));
				$endDay=intval($endDay);
				for($i=1;$i<=$endDay;$i++){
					$start=$nowYear.'-'.$nowMonth.'-'.$i;
					$end=strtotime($start.' +1 day')-1;
					$start=strtotime($start);
					$dateList[$i]=array('name'=>$i.'号','start'=>$start,'end'=>$end);
				}
			}elseif($op=='last_month'){
			    
			    $endDay=strtotime("{$nowYear}-{$nowMonth}-1 -1 day");
			    $lastMonth=date('Y-m',$endDay);
			    $endDay=date('d',$endDay);
			    $endDay=intval($endDay);
			    for($i=1;$i<=$endDay;$i++){
			        $start=$lastMonth.'-'.$i;
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
			}elseif($op=='last_year'){
			    
			    $lastYear=$nowYear-1;
			    for($i=1;$i<=12;$i++){
			        $start=$lastYear.'-'.$i.'-1';
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
					'status'=>1
				))->count();
				
				
				$dataList['failed'][$k]=$mcollected->where(array(
					'addtime'=>array('between',array($v['start'],$v['end'])),
					'status'=>0
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
					'status'=>1
				))->count();
				
				
				$dataList['failed'][$module]=$mcollected->where(array(
					'release'=>$module,
					'status'=>0
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