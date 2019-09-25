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

use skycaiji\admin\model\FuncApp;
class Mystore extends BaseController {
	public function indexAction(){
    	$this->redirect('Mystore/rule');
	}
	public function ruleAction(){
		$mrule=model('Rule');
		$type=input('type','collect');
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
		if(!empty($ruleList)){
			$provList=array();
			foreach ($ruleList as $k=>$v){
				$provList[$v['provider_id']]=$v['provider_id'];
			}
			$provList=model('Provider')->where('id','in',$provList)->column('*','id');
			foreach ($ruleList as $k=>$v){
				$url='https://www.skycaiji.com';
				if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
					
					$url=$provList[$v['provider_id']]['url'];
				}
				$ruleList[$k]['store_url']=$url.'/client/rule/detail?id='.$v['store_id'];
			}
		}

		$GLOBALS['_sc']['p_name']='已下载';
		$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/rule'),'title'=>'已下载：'.lang('rule_'.$type))));
		
		$this->assign('ruleList',$ruleList);

		$tpl=input('tpl');
		$tpl='rules'.(!empty($tpl)?('_'.$tpl):'');
		
		return $this->fetch($tpl);
	}
	
	public function ruleOpAction(){
		$id=input('id/d',0);
		$op=input('op');
		
		$ops=array('item'=>array('delete'),'list'=>array('deleteall','check_store_update'),'else'=>array('auto_check'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])&&!in_array($op,$ops['else'])){
			
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
    		$this->success(lang('op_success'),'Mystore/rule');
		}elseif($op=='auto_check'){
			
			$auto=input('auto/d',0);
			model('Config')->setConfig('store_auto_check_rule',$auto);
			if($auto){
				$this->success('规则设置为自动检测更新');
			}else{
				$this->error('规则设置为手动检测更新');
			}
		}elseif($op=='check_store_update'){
			
			$ids=input('ids/a');
			
			if(!empty($ids)){
				$ruleList=model('Rule')->where(array('id'=>array('in',$ids)))->select();
				$ruleList1=array();
				foreach ($ruleList as $k=>$v){
					$ruleList1[$v['store_id'].'_'.$v['provider_id']]=$v;
				}
				$ruleList=$ruleList1;
				unset($ruleList1);
			}else{
				$ruleList=array();
			}
			
			$uptimeList=array();
			$updateList=array();
			if(!empty($ruleList)){
				$provList=array();
				$provStoreIds=array();
				foreach ($ruleList as $v){
					$provList[$v['provider_id']]=$v['provider_id'];
					$provStoreIds[$v['provider_id']][$v['store_id']]=$v['store_id'];
				}
				if(!empty($provList)){
					$provList=model('Provider')->where('id','in',$provList)->column('*','id');
				}else{
					$provList=array();
				}
				
				foreach ($provStoreIds as $provId=>$storeIds){
					$url='';
					$storeIds=implode(',',$storeIds);
					$storeIds=rawurlencode($storeIds);
					if(empty($provId)){
						
						$url='https://www.skycaiji.com';
					}elseif(!empty($provList[$provId])){
						
						$url=$provList[$provId]['url'];
					}
					$url.='/client/rule/update?ids='.$storeIds;
					
					$uptimeList=get_html($url,null,array('timeout'=>2));
					$uptimeList=json_decode($uptimeList,true);
					
					if(!empty($uptimeList)){
						
						foreach ($uptimeList as $storeId=>$storeUptime){
							if($storeUptime>0&&$storeUptime>$ruleList[$storeId.'_'.$provId]['uptime']){
								
								$updateList[]=$ruleList[$storeId.'_'.$provId]['id'];
							}
						}
					}
				}
			}
			
			if(!empty($updateList)){
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
		
		if(!empty($appList)){
			$provList=array();
			foreach ($appList as $k=>$v){
				if(!empty($v['provider_id'])){
					
					$provList[$v['provider_id']]=$v['provider_id'];
				}
			}
			$provList=model('Provider')->where('id','in',$provList)->column('*','id');
			
			foreach ($appList as $k=>$v){
				$url='https://www.skycaiji.com';
				if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
					
					$url=$provList[$v['provider_id']]['url'];
				}
				$appList[$k]['store_url']=$url.'/client/plugin/detail?app='.$v['app'];
			}
		}
		
		$GLOBALS['_sc']['p_name']='已下载';
		$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/releaseApp'),'title'=>'已下载：发布插件')));
		
		$this->assign('appList',$appList);
		return $this->fetch('releaseApp');
	}
	public function releaseAppOpAction(){
		$id=input('id/d',0);
		$op=input('op');
		
		$ops=array('item'=>array('delete'),'list'=>array('deleteall','check_store_update'),'else'=>array('auto_check'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])&&!in_array($op,$ops['else'])){
			
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
		}elseif($op=='auto_check'){
			
			$this->_auto_check_plugin();
		}elseif($op=='check_store_update'){
			
			$ids=input('ids/a');
			
			$appList=model('ReleaseApp')->where(array('module'=>'cms','id'=>array('in',$ids)))->column('*','app');
			$updateList=$this->_check_store_plugin_update($appList);
			if(!empty($updateList)){
				$this->success('',null,$updateList);
			}else{
				$this->error();
			}
		}
	}

	/*应用程序列表*/
	public function appAction(){
		
		$mapp=model('App');
		$mprov=model('Provider');
		$dbApps=$mapp->order('uptime desc')->paginate(20);
		$pagenav=$dbApps->render();
		$dbApps=$dbApps->all();
		$dbApps1=array();
		$provIds=array();
		foreach ($dbApps as $k=>$v){
			$v=$v->toArray();
			$v['config']=$mapp->get_config($v['app']);
				
			$dbApps1[$v['app']]=$v;

			try {
				$appClass=$mapp->app_class($v['app'],false);
			}catch (\Exception $ex ){
				$appClass=null;
			}
			
			if(is_object($appClass)){
				
				if(version_compare($appClass->config['version'], $v['config']['version'],'>')===true){
					
					$dbApps1[$v['app']]['newest_version']=$appClass->config['version'];
				}
			}
			if($v['provider_id']>0){
				$provIds[$v['provider_id']]=$v['provider_id'];
			}
		}
		
		$dbApps=$dbApps1;
		unset($dbApps1);
	
		
		$dirApps=scandir(config('apps_path'));
		$pathApps=array();
		if(!empty($dirApps)){
			foreach( $dirApps as $dirApp ){
				if(isset($dbApps[$dirApp])){
					continue;
				}
	
				try {
					$appClass=$mapp->app_class($dirApp,false);
				}catch (\Exception $ex ){
					$appClass=null;
				}
	
				if(is_object($appClass)){
					
					$pathApp=array('config'=>$mapp->clear_config($appClass->config));
					if(!empty($pathApp['config']['website'])){
						
						$pathApp['provider_id']=$mprov->getIdByUrl($pathApp['config']['website']);
						if($pathApp['provider_id']>0){
							$provIds[$pathApp['provider_id']]=$pathApp['provider_id'];
						}
					}
					$pathApps[$dirApp]=$pathApp;
				}
			}
		}
		
		$provList=array();
		if($provIds){
			$provList=$mprov->where('id','in',$provIds)->column('*','id');
		}
		
		if($pathApps){
			
			$existApps=$mapp->where('app','in',array_keys($pathApps))->column('*','app');
			foreach ($pathApps as $k=>$v){
				if(!empty($existApps[$k])){
					
					unset($pathApps[$k]);
				}
			}
		}
	
		$GLOBALS['_sc']['p_name']='应用程序';
		$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/app'),'title'=>'应用程序')));
	
		$this->assign('pagenav',$pagenav);
		$this->assign('dbApps',$dbApps);
		$this->assign('pathApps',$pathApps);
		$this->assign('provList',$provList);
		
		return $this->fetch();
	}
	
	public function appOpAction(){
		$op=input('op');
		$mapp=model('App');
		if($op=='auto_check'){
			
			$auto=input('auto/d');
			model('Config')->setConfig('store_auto_check_app',$auto);
			if($auto){
				$this->success('应用设置为自动检测更新');
			}else{
				$this->error('应用设置为手动检测更新');
			}
		}elseif($op=='check_store_update'){
			
			$apps=input('apps/a');
			
			$mprov=model('Provider');
			$appList=array();
			$provList=array();
			$provApps=array();
			if(!empty($apps)){
				foreach( $apps as $app ){
					try {
						$appClass=$mapp->app_class($app,false);
					}catch (\Exception $ex ){
						$appClass=null;
					}
					
					if(is_object($appClass)){
						$provId=$mprov->getIdByUrl($appClass->config['website']);
						$provList[$provId]=$provId;
						$appList[$app]=array('provider_id'=>$provId,'version'=>$appClass->config['version']);
						$provApps[$provId][$app]=$app;
					}
				}
			}
			$updateList=array();
			
			$provList=$mprov->where('id','in',$provList)->column('*','id');
			foreach($provApps as $provId=>$apps){
				$apps=implode(',',$apps);
				$apps=rawurlencode($apps);
				$url='';
				$appUrl='';
				$isProv=false;
				if(!empty($provList[$provId])){
					
					$url=$provList[$provId]['url'];
					$isProv=true;
				}else{
					
					$url='https://www.skycaiji.com';
				}
				$appUrl=$url;
				$url.='/client/app/update?apps='.$apps;

				$storeList=get_html($url,null,array('timeout'=>2));
				$storeList=json_decode($storeList,true);
				
				if(!empty($storeList)){
					
					foreach ($storeList as $storeApp=>$storeVer){
						if(!empty($storeVer)&&version_compare($storeVer,$appList[$app]['version'],'>')){
							
							$updateList[]=array('app'=>$storeApp,'is_provider'=>$isProv,'app_url'=>$appUrl.'/client/app/detail?app='.rawurlencode($storeApp));
						}
					}
				}
			}
			
			if(!empty($updateList)){
				$this->success('',null,$updateList);
			}else{
				$this->error('无更新');
			}
		}
	}
	/*函数插件*/
	public function funcAppAction(){
		$page=max(1,input('p/d',0));
		$cond=array();
		
		$sortBy=input('sort','desc');
		$sortBy=($sortBy=='asc')?'asc':'desc';
		$orderKey=input('order');
		
		$this->assign('sortBy',$sortBy);
		$this->assign('orderKey',$orderKey);
		$orderBy=!empty($orderKey)?($orderKey.' '.$sortBy):'id desc';
		$mfuncApp=model('FuncApp');
		$limit=20;
		$count=$mfuncApp->where($cond)->count();
		$appList=$mfuncApp->where($cond)->order($orderBy)->paginate($limit,false,paginate_auto_config());
		
		$pagenav = $appList->render();
		$this->assign('pagenav',$pagenav);
		$appList=$appList->all();
		
		if(!empty($appList)){
			$provList=array();
			foreach ($appList as $k=>$v){
				if(!empty($v['provider_id'])){
					
					$provList[$v['provider_id']]=$v['provider_id'];
				}
			}
			$provList=model('Provider')->where('id','in',$provList)->column('*','id');
			
			foreach ($appList as $k=>$v){
				$url='https://www.skycaiji.com';
				if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
					
					$url=$provList[$v['provider_id']]['url'];
				}
				$appList[$k]['store_url']=$url.'/client/plugin/detail?app='.$v['app'];
			}
		}
		
		$GLOBALS['_sc']['p_name']='已下载';
		$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/funcApp'),'title'=>'已下载：函数插件')));
		
		$this->assign('appList',$appList);
		$this->assign('modules',$mfuncApp->funcModules);
		return $this->fetch('func');
	}
	
	public function funcAppOpAction(){
		$op=input('op');
		$id=input('id');
		
		$ops=array('item'=>array('delete','enable','detail'),'list'=>array('deleteall','check_store_update'),'else'=>array('auto_check'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])&&!in_array($op,$ops['else'])){
			
			$this->error(lang('invalid_op'));
		}
		
		$mfuncApp=new FuncApp();
		$appData=$mfuncApp->where('id',$id)->find();
		if($op=='detail'){
			$appClass=empty($appData)?array():$mfuncApp->get_app_class($appData['module'], $appData['app']);
			$this->success('',null,$appClass);
		}elseif($op=='enable'){
			$enable=input('enable/d');
			$mfuncApp->where('id',$appData['id'])->update(array('enable'=>$enable));
			$this->success();
		}elseif($op=='delete'){
			if(!empty($appData['module'])&&!empty($appData['app'])){
				$filename=$mfuncApp->filename($appData['module'], $appData['app']);
				if(file_exists($filename)){
					unlink($filename);
				}
			}
			$mfuncApp->where('id',$appData['id'])->delete();
			$this->success('删除成功');
		}elseif($op=='deleteall'){
			
			$ids=input('ids/a');
			if(is_array($ids)&&count($ids)>0){
				$mfuncApp->where(array('id'=>array('in',$ids)))->delete();
			}
    		$this->success(lang('op_success'),'Mystore/funcApp');
		}elseif($op=='auto_check'){
			
			$this->_auto_check_plugin();
		}elseif($op=='check_store_update'){
			
			$ids=input('ids/a');
			
			$appList=model('FuncApp')->where(array('id'=>array('in',$ids)))->column('*','app');
			$updateList=$this->_check_store_plugin_update($appList);
			if(!empty($updateList)){
				$this->success('',null,$updateList);
			}else{
				$this->error();
			}
		}
	}
	/*插件设置自动检测*/
	public function _auto_check_plugin(){
		$auto=input('auto/d');
		model('Config')->setConfig('store_auto_check_plugin',$auto);
		if($auto){
			$this->success('插件设置为自动检测更新');
		}else{
			$this->error('插件设置为手动检测更新');
		}
	}
	/*检测插件云平台c插件更新*/
	public function _check_store_plugin_update($appList=array()){
		$appList1=array();
		foreach ($appList as $k=>$v){
			$appList1[$v['app'].'_'.$v['provider_id']]=$v;
		}
		$appList=$appList1;
		unset($appList1);
			
		$uptimeList=array();
		$updateList=array();
		if(!empty($appList)){
			$provList=array();
			$provApps=array();
			foreach ($appList as $v){
				$provList[$v['provider_id']]=$v['provider_id'];
				$provApps[$v['provider_id']][$v['app']]=$v['app'];
			}
			if(!empty($provList)){
				$provList=model('Provider')->where('id','in',$provList)->column('*','id');
			}else{
				$provList=array();
			}
		
			foreach ($provApps as $provId=>$apps){
				$apps=implode(',',$apps);
				$apps=rawurlencode($apps);
				$url='https://www.skycaiji.com';
				if(!empty($provId)&&!empty($provList[$provId])){
					
					$url=$provList[$provId]['url'];
				}
				$url.='/client/plugin/update?apps='.$apps;
				
				$uptimeList=get_html($url,null,array('timeout'=>2));
				$uptimeList=json_decode($uptimeList,true);
				
				if(!empty($uptimeList)){
					
					foreach ($uptimeList as $app=>$uptime){
						if($uptime>0&&$uptime>$appList[$app.'_'.$provId]['uptime']){
							
							$updateList[]=$appList[$app.'_'.$provId]['id'];
						}
					}
				}
			}
		}
		return $updateList;
	}
}