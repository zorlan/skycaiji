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

class Mystore extends BaseController {
	public function indexAction(){





		


		
    	$this->redirect('Mystore/collect');
	}
	public function collectAction(){
		$mrule=model('Rule');
		$type='collect';
		$module=input('module');
		$page=max(1,input('p/d',0));
		$cond=array('type'=>$type);
		
		if(!empty($module)){
			$cond=array('module'=>$module);
		}
		
		$sortBy=input('sort','desc');
		$sortBy=($sortBy=='asc')?'asc':'desc';
		$orderKey=input('order');
		
		$this->assign('sortBy',$sortBy);
		$this->assign('orderKey',$orderKey);
		$orderBy=!empty($orderKey)?($orderKey.' '.$sortBy):'id desc';
		
		$limit=20;
		$count=$mrule->where($cond)->count();
		$ruleList = $mrule->where($cond)->order($orderBy)->paginate($limit,false,paginate_auto_config());
		
		$pagenav = $ruleList->render();
		$this->assign('pagenav',$pagenav);
		$ruleList=$ruleList->all();

		$GLOBALS['content_header']='已下载';
		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Mystore/index'),'title'=>'已下载'),lang('rule_'.$type)));
		
		$this->assign('ruleList',$ruleList);

		$tpl=input('tpl');
		$tpl='rules'.(!empty($tpl)?('_'.$tpl):'');
		
		return $this->fetch($tpl);
	}
	
	public function ruleOpAction(){
		$id=input('id/d',0);
		$op=input('op');
		
		$ops=array('item'=>array('delete'),'list'=>array('deleteall','check_store_update'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])){
			
			$this->error(lang('invalid_op'));
		}
		$mrule=model('Rule');
		if($op=='delete'){
			
			$mrule->where(array('id'=>$id))->delete();
			$this->success(lang('delete_success'));
		}elseif($op=='deleteall'){
			
			$ids=input('ids/a');
			if(is_array($ids)&&count($ids)>0){
				$mrule->where(array('id'=>array('in',$ids)))->delete();
			}
    		$this->success(lang('op_success'),'Mystore/collect');
		}elseif($op=='check_store_update'){
			
			$ids=input('ids/a');
			
			if(!empty($ids)){
				$ruleList=model('Rule')->where(array('id'=>array('in',$ids)))->column('*','store_id');
			}else{
				$ruleList=array();
			}
			
			$uptimeList=array();
			if(!empty($ruleList)){
				$storeIds=implode(',', array_keys($ruleList));
				$uptimeList=get_html('http://www.skycaiji.com/Store/Client/collectUpdate?ids='.rawurlencode($storeIds));
				$uptimeList=json_decode($uptimeList,true);
			}
			
			if(!empty($uptimeList)){
				$updateList=array();
				
				foreach ($uptimeList as $storeId=>$storeUptime){
					if($storeUptime>0&&$storeUptime>$ruleList[$storeId]['uptime']){
						
						$updateList[]=$ruleList[$storeId]['id'];
					}
				}
				$this->success('',null,$updateList);
			}else{
				$this->error();
			}
		}
	}
	public function releaseAppAction(){
		$page=max(1,input('p/d',0));
		$cond=array();
		
		$sortBy=input('sort','desc');
		$sortBy=($sortBy=='asc')?'asc':'desc';
		$orderKey=input('order');
		
		$this->assign('sortBy',$sortBy);
		$this->assign('orderKey',$orderKey);
		$orderBy=!empty($orderKey)?($orderKey.' '.$sortBy):'id desc';
		$mapp=model('ReleaseApp');
		$limit=20;
		$count=$mapp->where($cond)->count();
		$appList=$mapp->where($cond)->order($orderBy)->paginate($limit,false,paginate_auto_config());
		
		$pagenav = $appList->render();
		$this->assign('pagenav',$pagenav);
		$appList=$appList->all();
		
		$GLOBALS['content_header']='已下载';
		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Mystore/index'),'title'=>'已下载'),'发布插件'));
		
		$this->assign('appList',$appList);
		return $this->fetch('releaseApp');
	}
	public function releaseAppOpAction(){
		$id=input('id/d',0);
		$op=input('op');
		
		$ops=array('item'=>array('delete'),'list'=>array('deleteall','check_store_update'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])){
			
			$this->error(lang('invalid_op'));
		}
		$mapp=model('ReleaseApp');
		if($op=='delete'){
			
			$mapp->where(array('id'=>$id))->delete();
			$this->success(lang('delete_success'));
		}elseif($op=='deleteall'){
			
			$ids=input('ids/a');
			if(is_array($ids)&&count($ids)>0){
				$mapp->where(array('id'=>array('in',$ids)))->delete();
			}
    		$this->success(lang('op_success'),'Mystore/ReleaseApp');
		}elseif($op=='check_store_update'){
			
			$ids=input('ids/a');
			
			$appList=model('ReleaseApp')->where(array('module'=>'cms','id'=>array('in',$ids)))->column('*','app');
			
			$uptimeList=array();
			if(!empty($appList)){
				$apps=implode(',', array_keys($appList));
				$uptimeList=get_html('http://www.skycaiji.com/Store/Client/cmsUpdate?apps='.rawurlencode($apps));
				$uptimeList=json_decode($uptimeList,true);
			}
			if(!empty($uptimeList)){
				$updateList=array();
				
				foreach ($uptimeList as $app=>$storeUptime){
					if($storeUptime>0&&$storeUptime>$appList[$app]['uptime']){
						
						$updateList[]=$appList[$app]['id'];
					}
				}
				$this->success('',null,$updateList);
			}else{
				$this->error();
			}
		}
	}
}