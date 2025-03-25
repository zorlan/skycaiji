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
use skycaiji\admin\model\ApiApp;
class Mystore extends BaseController {
	public function indexAction(){
    	$this->redirect('mystore/store');
	}
	public function storeAction(){
	    $this->set_html_tags('云平台');
	    
	    $url=input('url','','trim');
	    
	    $notSafe='';
	    
	    if(!empty($url)&&!\skycaiji\admin\model\Provider::is_official_url($url)){
	        
	        $provData=model('Provider')->where('url',$url)->find();
	        $this->set_html_tags('第三方平台');
	        if(empty($provData)){
	            $this->error($url.' 平台未添加','');
	        }
	        if(empty($provData['enable'])){
	            $this->error($url.' 已设置为拒绝访问','');
	        }
	        $url=$provData['url'];
	        
	        $this->set_html_tags('第三方:'.$provData['title']);
	        
	        $storeData=\util\Tools::curl_skycaiji('/client/info/store?url='.rawurlencode($url).'&clientinfo='.rawurlencode(g_sc('clientinfo')));
	        $storeData=json_decode($storeData,true);
	        if(!is_array($storeData)){
	            $storeData=array();
	        }
	        if(empty($storeData)||empty($storeData['safe'])){
	            
	            $notSafe=empty($storeData)?'安全检测失败，是否继续访问？':$storeData['msg'];
	        }
	    }
	    if(empty($url)){
	        
	        $url=\skycaiji\admin\model\Provider::create_store_url(null,'store');
	    }
	    
	    $url.=(strpos($url,'?')===false?'?':'&').'clientinfo='.rawurlencode(g_sc('clientinfo'));
	    
	    if(empty($notSafe)){
	        
	        $this->redirect($url);
	    }else{
	        
	        $this->set_html_tags(
	            null,
	            lang('store'),
	            breadcrumb(array(array('url'=>url('mystore/store'),'title'=>lang('store'))))
	        );
	        $this->assign('storeInfo',array('url'=>$url,'notSafe'=>$notSafe));
	        return $this->fetch();
	    }
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
		
		$sortBy=input('sort','');
		$orderKey=input('order','');
		
		\util\Param::set_cache_action_order_by('action_mystore_rule_order', $orderKey, $sortBy);
		
		$sortBy=($sortBy=='asc')?'asc':'desc';
		$this->assign('sortBy',$sortBy);
		$this->assign('orderKey',$orderKey);
		
		$orderBy=empty($orderKey)?'id desc':($orderKey.' '.$sortBy);
		
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
			$mprov=model('Provider');
			$provList=$mprov->where('id','in',$provList)->column('*','id');
			foreach ($ruleList as $k=>$v){
				$url='';
				if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
					
					$url=$provList[$v['provider_id']]['url'];
					$ruleList[$k]['_is_provider']=true;
				}
				$ruleList[$k]['_store_url']=\skycaiji\admin\model\Provider::create_store_url($url,'client/addon/rule',array('id'=>$v['store_id']));
			}
		}
		
		$this->set_html_tags(
		    lang('rule_'.$type),
		    lang('rule_'.$type),
		    breadcrumb(array(array('url'=>url('mystore/rule'),'title'=>lang('rule_'.$type))))
		);
		
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
			
		    $ids=input('ids/a',array());
			if(is_array($ids)&&count($ids)>0){
				$mrule->where(array('id'=>array('in',$ids)))->delete();
			}
    		$this->success(lang('op_success'),'mystore/rule');
		}elseif($op=='auto_check'){
			
			$auto=input('auto/d',0);
			model('Config')->setConfig('store_auto_check_rule',$auto);
			if($auto){
				$this->success('规则设置为自动检测更新');
			}else{
				$this->error('规则设置为手动检测更新');
			}
		}elseif($op=='check_store_update'){
			
		    $ids=input('ids/a',array());
			
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
					$uptimeList=$this->_get_store_uptimes($provList[$provId],'rule',$storeIds);
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
		
		$sortBy=input('sort','');
		$orderKey=input('order','');
		
		\util\Param::set_cache_action_order_by('action_mystore_release_order', $orderKey, $sortBy);
		
		$sortBy=($sortBy=='asc')?'asc':'desc';
		$this->assign('sortBy',$sortBy);
		$this->assign('orderKey',$orderKey);
		
		$orderBy=empty($orderKey)?'id desc':($orderKey.' '.$sortBy);
		
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
			
			$mprov=model('Provider');
			
			$provList=$mprov->where('id','in',$provList)->column('*','id');
			
			foreach ($appList as $k=>$v){
				$url='';
				if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
					
				    $url=$provList[$v['provider_id']]['url'];
				    $appList[$k]['_is_provider']=true;
				}
				$appList[$k]['_store_url']=\skycaiji\admin\model\Provider::create_store_url($url,'client/addon/plugin',array('app'=>$v['app']));
			}
		}
		
		$this->set_html_tags(
		    '发布插件',
		    '发布插件',
		    breadcrumb(array(array('url'=>url('mystore/releaseApp'),'title'=>'发布插件')))
		);
		
		$this->assign('appList',$appList);
		return $this->fetch('release_app');
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
			
		    $this->_delete_release_app($id);
			$this->success(lang('delete_success'));
		}elseif($op=='deleteall'){
			
		    $ids=input('ids/a',array());
			if(is_array($ids)&&count($ids)>0){
				foreach ($ids as $idv){
				    $this->_delete_release_app($idv);
				}
			}
    		$this->success(lang('op_success'),'mystore/releaseApp');
		}elseif($op=='auto_check'){
			
			$this->_auto_check_plugin();
		}elseif($op=='check_store_update'){
			
		    $ids=input('ids/a',array());
			
			$appList=model('ReleaseApp')->where(array('module'=>'cms','id'=>array('in',$ids)))->column('*','app');
			$updateList=$this->_check_store_plugin_update($appList);
			if(!empty($updateList)){
				$this->success('',null,$updateList);
			}else{
				$this->error();
			}
		}
	}
	
	private function _delete_release_app($id){
		if($id>0){
			$mapp=model('ReleaseApp');
			$pluginPath=config('plugin_path').'/release';
			$appData=$mapp->where('id',$id)->find();
			if(!empty($appData)){
				$appFile=$pluginPath.'/'.strtolower($appData['module']).'/'.ucfirst($appData['app']).'.php';
				$appTpl=$pluginPath.'/view/'.strtolower($appData['module']).'/'.ucfirst($appData['app']).'.html';
				if(file_exists($appFile)){
					
					unlink($appFile);
				}
				if(file_exists($appTpl)){
					
					unlink($appTpl);
				}
				$mapp->where('id',$id)->delete();
			}
		}
	}

	/*应用程序列表*/
	public function appAction(){
		
		$mapp=model('App');
		$mprov=model('Provider');
		$dbApps=$mapp->order('uptime desc')->paginate(20,false,paginate_auto_config());
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
		
        foreach ($dbApps as $k=>$v){
            $storeUrl='';
            if(!empty($provList[$v['provider_id']])){
                $storeUrl=$provList[$v['provider_id']]['url'];
                $dbApps[$k]['_is_provider']=true;
            }
            $dbApps[$k]['_store_url']=\skycaiji\admin\model\Provider::create_store_url($storeUrl,'client/addon/app',array('app'=>$k));
            
            if(is_array($v['config'])){
                
                if(is_array($v['config']['packs'])){
                    $dbApps[$k]['_nav_packs']=$mapp->convert_packs($v['config']['packs'],$v['app'],'nav');
                }
                
                $content='';
                if($v['config']['author']){
                    $content='作者：'.htmlspecialchars($v['config']['author'],ENT_QUOTES);
                }
                if($v['config']['desc']){
                    $content.=($content?'<br>描述：':'').htmlspecialchars($v['config']['desc'],ENT_QUOTES);
                }
                $dbApps[$k]['_content']=$content;
            }
		}
		
		foreach ($pathApps as $k=>$v){
		    $storeUrl='';
		    if(!empty($provList[$v['provider_id']])){
		        $storeUrl=$provList[$v['provider_id']]['url'];
		        $pathApps[$k]['_is_provider']=true;
		    }
		    $pathApps[$k]['_store_url']=\skycaiji\admin\model\Provider::create_store_url($storeUrl,'client/addon/app',array('app'=>$k));
		    
		    if(is_array($v['config'])){
		        
		        $content='';
		        if($v['config']['author']){
		            $content='作者：'.htmlspecialchars($v['config']['author'],ENT_QUOTES);
		        }
		        if($v['config']['desc']){
		            $content.=($content?'<br>描述：':'').htmlspecialchars($v['config']['desc'],ENT_QUOTES);
		        }
		        $pathApps[$k]['_content']=$content;
		    }
		}
		
		$this->set_html_tags(
		    '应用程序',
		    '应用程序',
		    breadcrumb(array(array('url'=>url('mystore/app'),'title'=>'应用程序')))
		);
		
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
			
		    $apps=input('apps/a',array());
			
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
				$verList=$this->_get_store_uptimes($provList[$provId],'app',$apps);
				if(!empty($verList)){
					
					foreach ($verList as $verK=>$verV){
					    if(!empty($verV)&&version_compare($verV,$appList[$verK]['version'],'>')){
							
						    $updateList[]=$verK;
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
	/*函数插件*/
	public function funcAppAction(){
		$page=max(1,input('p/d',0));
		$cond=array();
		
		$sortBy=input('sort','');
		$orderKey=input('order','');
		
		\util\Param::set_cache_action_order_by('action_mystore_func_order', $orderKey, $sortBy);
		
		$sortBy=($sortBy=='asc')?'asc':'desc';
		$this->assign('sortBy',$sortBy);
		$this->assign('orderKey',$orderKey);
		
		$orderBy=empty($orderKey)?'id desc':($orderKey.' '.$sortBy);
		
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
			
			$mprov=model('Provider');
			$provList=$mprov->where('id','in',$provList)->column('*','id');
			
			foreach ($appList as $k=>$v){
				$url='';
				if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
					
				    $url=$provList[$v['provider_id']]['url'];
				    $appList[$k]['_is_provider']=true;
				}
				$appList[$k]['_store_url']=\skycaiji\admin\model\Provider::create_store_url($url,'client/addon/plugin',array('app'=>$v['app']));
			}
		}
		
		$this->set_html_tags(
		    '函数插件',
		    '函数插件',
		    breadcrumb(array(array('url'=>url('mystore/funcApp'),'title'=>'函数插件')))
		);
		
		$this->assign('appList',$appList);
		$this->assign('modules',$mfuncApp->funcModules);
		return $this->fetch('func_app');
	}
	
	public function funcAppOpAction(){
		$op=input('op');
		$id=input('id');
		
		$ops=array('item'=>array('delete','enable','detail','method'),'list'=>array('deleteall','check_store_update'),'else'=>array('auto_check'));
		if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])&&!in_array($op,$ops['else'])){
			
			$this->error(lang('invalid_op'));
		}
		
		$mfuncApp=new FuncApp();
		$appData=$mfuncApp->where('id',$id)->find();
		if($op=='detail'){
		    $appClass=empty($appData)?array():$mfuncApp->get_app_class($appData['module'], $appData['app'],array('comment_cut'=>1));
		    $this->success('',null,$appClass);
		}elseif($op=='method'){
		    $methodName=input('name');
		    if(empty($methodName)){
		        $this->error('方法名为空');
		    }
		    $appClass=empty($appData)?array():$mfuncApp->get_app_class($appData['module'], $appData['app'],array('doc_comment'=>1,'method_code'=>1));
		    $methodData=$appClass['methods'][$methodName];
		    if(!is_array($methodData)){
		        $methodData=array();
		    }
		    $this->assign('methodData',$methodData);
		    return $this->fetch('func_method');
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
			
		    $ids=input('ids/a',array());
			if(is_array($ids)&&count($ids)>0){
				
				foreach ($ids as $idv){
					$vAppData=$mfuncApp->where('id',$idv)->find();
					if(!empty($vAppData)){
						if(!empty($vAppData['module'])&&!empty($vAppData['app'])){
							$filename=$mfuncApp->filename($vAppData['module'], $vAppData['app']);
							if(file_exists($filename)){
								unlink($filename);
							}
						}
						$mfuncApp->where('id',$vAppData['id'])->delete();
					}
				}
			}
    		$this->success(lang('op_success'),'mystore/funcApp');
		}elseif($op=='auto_check'){
			
			$this->_auto_check_plugin();
		}elseif($op=='check_store_update'){
			
		    $ids=input('ids/a',array());
			
			$appList=model('FuncApp')->where(array('id'=>array('in',$ids)))->column('*','app');
			$updateList=$this->_check_store_plugin_update($appList);
			if(!empty($updateList)){
				$this->success('',null,$updateList);
			}else{
				$this->error();
			}
		}
	}
	
	
	/*接口插件*/
	public function apiAppAction(){
	    $page=max(1,input('p/d',0));
	    $cond=array();
	    
	    $sortBy=input('sort','');
	    $orderKey=input('order','');
	    
	    \util\Param::set_cache_action_order_by('action_mystore_api_order', $orderKey, $sortBy);
	    
	    $sortBy=($sortBy=='asc')?'asc':'desc';
	    $this->assign('sortBy',$sortBy);
	    $this->assign('orderKey',$orderKey);
	    
	    $orderBy=empty($orderKey)?'id desc':($orderKey.' '.$sortBy);
	    
	    $mapiApp=model('ApiApp');
	    $limit=20;
	    $count=$mapiApp->where($cond)->count();
	    $appList=$mapiApp->where($cond)->order($orderBy)->paginate($limit,false,paginate_auto_config());
	    
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
	        
	        $mprov=model('Provider');
	        $provList=$mprov->where('id','in',$provList)->column('*','id');
	        
	        foreach ($appList as $k=>$v){
	            $url='';
	            if(!empty($v['provider_id'])&&!empty($provList[$v['provider_id']])){
	                
	                $url=$provList[$v['provider_id']]['url'];
	                $appList[$k]['_is_provider']=true;
	            }
	            $appList[$k]['_store_url']=\skycaiji\admin\model\Provider::create_store_url($url,'client/addon/plugin',array('app'=>$v['app']));
	        }
	    }
	    
	    $this->set_html_tags(
	        '接口插件',
	        '接口插件',
	        breadcrumb(array(array('url'=>url('mystore/apiApp'),'title'=>'接口插件')))
	    );
	    $this->assign('appList',$appList);
	    $this->assign('modules',$mapiApp->apiModules);
	    return $this->fetch('api_app');
	}
	
	
	public function apiAppOpAction(){
	    $op=input('op');
	    $id=input('id');
	    
	    $ops=array('item'=>array('delete','enable'),'list'=>array('check_store_update'),'else'=>array('auto_check'));
	    if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])&&!in_array($op,$ops['else'])){
	        
	        $this->error(lang('invalid_op'));
	    }
	    
	    $mapiApp=new ApiApp();
	    $appData=$mapiApp->where('id',$id)->find();
	    if($op=='enable'){
	        $enable=input('enable/d');
	        $mapiApp->where('id',$appData['id'])->update(array('enable'=>$enable));
	        $this->success();
	    }elseif($op=='delete'){
	        if(!empty($appData['module'])&&!empty($appData['app'])){
	            $filename=$mapiApp->app_filename($appData['module'], $appData['app']);
	            if(file_exists($filename)){
	                unlink($filename);
	            }
	        }
	        $mapiApp->where('id',$appData['id'])->delete();
	        $this->success('删除成功');
	    }elseif($op=='auto_check'){
	        
	        $this->_auto_check_plugin();
	    }elseif($op=='check_store_update'){
	        
	        $ids=input('ids/a',array());
	        
	        $appList=model('ApiApp')->where(array('id'=>array('in',$ids)))->column('*','app');
	        $updateList=$this->_check_store_plugin_update($appList);
	        if(!empty($updateList)){
	            $this->success('',null,$updateList);
	        }else{
	            $this->error();
	        }
	    }
	}
	
	public function apiAppConfigAction(){
	    $id=input('id');
	    $mapiApp=model('ApiApp');
	    $appData=$mapiApp->where('id',$id)->find();
	    if(empty($appData)){
	        $this->error('插件不存在');
	    }
	    $config=$mapiApp->compatible_config($appData['config']);
	    $globalOps=$mapiApp->get_app_ops($appData,null,true,true);
	    if(request()->isPost()){
	        $global=input('global/a',array(),null);
	        foreach($globalOps as $globalOp){
	            if($globalOp['user']['required']&&is_empty($global[$globalOp['name_key']],true)){
	                
	                if($globalOp['user']['tag']=='select'||is_empty($globalOp['user']['default'],true)){
	                    
	                    $this->error('必填：'.$globalOp['name']);
	                }
	            }
	        }
	        $config['global']=$global;
	        $mapiApp->where('id',$id)->update(array('config'=>serialize($config)));
	        $this->success('配置成功','',array('js'=>"$('#myModal').modal('hide');"));
	    }else{
	        $this->assign('globalOps',$globalOps);
	        $this->assign('appData',$appData);
	        $this->assign('config',$config);
	        return $this->fetch();
	    }
	}
	
	/*导入*/
	public function uploadAction(){
	    $type=input('type','');
	    $type=$type?:'plugin';
	    $fromTask=input('from_task','');
	    $types=array('rule'=>'规则','plugin'=>'插件','release'=>'发布设置');
	    $typeIsPlugin=$type=='plugin'?true:false;
	    $typeName=$types[$type];
		if(request()->isPost()){
			if(g_sc_c('site','verifycode')){
				
				$verifycode=trim(input('verifycode'));
				$check=\util\Tools::check_verify($verifycode);
				if(!$check['success']){
					$this->error($check['msg']);
				}
			}
			
			$result=$this->_upload_addon($type,$fromTask?false:true,true);
			$url=$result['url']?$result['url']:'';
			
			$data=array();
			if($result['show_ipt_pwd']){
			    
			    $data['js']='win_upload_ipt_pwd();';
			}elseif($result['show_plugins']){
			    
			    $data['js']='win_upload_plugins('.json_encode($result['show_plugins']).');';
			}elseif($result['show_replace']){
			    
			    $data['js']="confirmRight('插件已存在，是否覆盖？',win_upload_replace);";
			}elseif($result['show_rule_data']){
			    
			    if($fromTask){
			        
			        $ruleData=$result['rule_data'];
			        init_array($ruleData);
			        if($ruleData){
			            
			            $name='文件';
			            $name.=$ruleData['name']?(' » '.$ruleData['name']):'';
			            $name=addslashes($name);
			            $ruleData=base64_encode(serialize($ruleData));
			            $ruleData=addslashes($ruleData);
			            $data['js']=sprintf("taskOpClass.import_rule('%s','%s')",'file:'.$ruleData,$name);
			        }else{
			            $this->error('没有规则');
			        }
			    }
			}elseif($result['show_release_data']){
			    
			    $releData=$result['release_data'];
			    init_array($releData);
			    if($releData){
			        
			        $name='文件';
			        $name.=$releData['name']?(' » '.$releData['name']):'';
			        $name=addslashes($name);
			        $releData=base64_encode(serialize($releData));
			        $releData=addslashes($releData);
			        $data['js']=sprintf("releaseClass.import_rele('%s','%s')",'file:'.$releData,$name);
			    }else{
			        $this->error('没有发布设置');
			    }
			}
			if($result['success']){
			    $this->success($result['msg'],$url,$data);
			}else{
			    $this->error($result['msg'],$url,$data);
			}
		}else{
		    $this->assign('type',$type);
		    $this->assign('typeIsPlugin',$typeIsPlugin);
		    $this->assign('typeName',$typeName);
		    $this->assign('fromTask',$fromTask);
			return $this->fetch();
		}
	}
	
	private function _safe_unserialize($code,$base64Decode=true){
	    if($code){
	        $code=trim($code);
	        if($base64Decode){
	            $code=base64_decode($code);
	        }
    	    if(preg_match('/\bO\:\d+\:[\'\"][^\'\"]+?[\'\"]/',$code)){
    	        
    	        throw new \Exception('错误的文件');
    	    }
    	    $code=unserialize($code);
	    }
	    return $code;
	}
	
	private function _upload_decrypt($pwd,&$data){
	    if(isset($data['encrypt_version'])){
	        if(empty($pwd)){
	            return return_result('请输入密码',false,array('show_ipt_pwd'=>1));
	        }else{
	            
	            $edClass=new \util\EncryptDecrypt($data['encrypt_version'],$data['skycaiji_version']);
	            $data=$edClass->decrypt(array('data'=>$data['data'],'pwd'=>$pwd));
	            $data=$this->_safe_unserialize($data);
	            if(empty($data)){
	                return return_result('密码错误');
	            }
	        }
	    }
	    return null;
	}
	
	private function _upload_addon($type,$installRule,$installPlugin){
	    try{
	        $types=array('rule'=>'规则','plugin'=>'插件','release'=>'发布设置');
	        $typeName=$types[$type];
	        $typeIsPlugin=$type=='plugin'?true:false;
	        
	        $uploadAddon=input('upload_addon/a',array());
	        init_array($uploadAddon);
	        
	        $file=$_FILES['upload_file'];
	        if(empty($file)||empty($file['tmp_name'])){
	            return return_result('请选择'.$typeName.'文件');
	        }
	        
	        $uploadPwd=$uploadAddon['pwd'];
	        
	        $fileTxt=file_get_contents($file['tmp_name']);
	        
	        
	        $pluginDataList=array();
	        if(preg_match_all('/\/\*skycaiji-plugin-start\*\/(?P<data>[\s\S]+?)\/\*skycaiji-plugin-end\*\//i',$fileTxt,$fileMatches)){
	            foreach ($fileMatches['data'] as $k=>$v){
	                $v=$v?:'';
	                $v=$this->_safe_unserialize($v);
	                if($v&&is_array($v)){
	                    $returnResult=$this->_upload_decrypt($uploadPwd,$v);
	                    if($returnResult){
	                        return $returnResult;
	                    }
	                    if($v['type']&&$v['app']){
	                        $pluginDataList[$v['type'].':'.$v['module'].':'.$v['app']]=$v;
	                    }
	                }
	            }
	        }
	        
	        $typeData=null;
	        
	        if($typeIsPlugin){
	            
	            if(empty($pluginDataList)||!is_array($pluginDataList)){
	                return return_result('不是插件文件');
	            }
	        }else{
	            if($type=='rule'){
	                
    	            if(preg_match('/\/\*skycaiji-collector-start\*\/(?P<data>[\s\S]+?)\/\*skycaiji-collector-end\*\//i',$fileTxt,$fileMatch)){
    	                $typeData=$this->_safe_unserialize($fileMatch['data']);
    	            }
	            }elseif($type=='release'){
	                
	                if(preg_match('/\/\*skycaiji-release-start\*\/(?P<data>[\s\S]+?)\/\*skycaiji-release-end\*\//i',$fileTxt,$fileMatch)){
	                    $typeData=$this->_safe_unserialize($fileMatch['data']);
	                }
	            }
	            if($typeData&&is_array($typeData)){
	                $returnResult=$this->_upload_decrypt($uploadPwd,$typeData);
	                if($returnResult){
	                    return $returnResult;
	                }
	            }
	            if(empty($typeData)||!is_array($typeData)){
	                return return_result('不是'.$typeName.'文件');
	            }
	            
	            $typeData['config']=$this->_safe_unserialize($typeData['config'],false);
	            $typeData['config']=serialize($typeData['config']);
	        }
	        $uploadedPlugin=false;
	        
	        if(!empty($pluginDataList)&&is_array($pluginDataList)){
	            if(!$typeIsPlugin||count($pluginDataList)>1){
	                
	                $uploadPlugins=is_array($uploadAddon['plugins'])?$uploadAddon['plugins']:array();
	                if(empty($uploadPlugins)){
	                    
	                    if($typeIsPlugin||!$uploadAddon['ignore_plugin']){
	                        
	                        $pluginList=array();
	                        foreach ($pluginDataList as $pluginData){
	                            $isReleDiy=false;
	                            $pluginTitle='';
	                            $mapp=null;
	                            if($pluginData['type']=='release'){
	                                $mapp=model('ReleaseApp');
	                                $isReleDiy=$pluginData['module']=='diy'?true:false;
	                                $pluginTitle.=lang('rele_m_name_'.$pluginData['module']).'发布插件 » ';
	                            }elseif($pluginData['type']=='func'){
	                                $mapp=model('FuncApp');
	                                $pluginTitle='函数插件：'.$mapp->get_func_module_val($pluginData['module'],'name').' » ';
	                            }elseif($pluginData['type']=='api'){
	                                $mapp=model('ApiApp');
	                                $pluginTitle='接口插件：'.$mapp->get_api_module_val($pluginData['module'],'name').' » ';
	                            }else{
	                                continue;
	                            }
	                            if($isReleDiy){
	                                
	                                $pluginTitle.=$pluginData['app'];
	                                if($mapp->appFileExists($pluginData['app'],'diy')){
	                                    $pluginTitle='<span style="color:red">[覆盖已有]</span> '.$pluginTitle;
	                                }
	                            }else{
	                                $pluginTitle.=$pluginData['name'].'('.$pluginData['app'].')';
	                                if($mapp->where('app',$pluginData['app'])->count()>0){
	                                    $pluginTitle='<span style="color:red">[覆盖已有]</span> '.$pluginTitle;
	                                }
	                            }
	                            $pluginList[$pluginData['type'].':'.$pluginData['module'].':'.$pluginData['app']]=$pluginTitle;
	                        }
	                        return return_result('',false,array('show_plugins'=>$pluginList));
	                    }
	                }else{
	                    
	                    if($installPlugin){
	                        $pluginType='';
	                        foreach ($uploadPlugins as $plugin){
	                            $pluginData=$pluginDataList[$plugin];
	                            if(empty($pluginData)){
	                                continue;
	                            }
	                            $isReleDiy=false;
	                            $pluginTitle='';
	                            $pluginType=$pluginData['type'];
	                            if($pluginType=='release'){
	                                $pluginTitle.=lang('rele_m_name_'.$pluginData['module']).'发布插件：';
	                                $isReleDiy=$pluginData['module']=='diy'?true:false;
	                            }elseif($pluginType=='func'){
	                                $pluginTitle='函数插件：';
	                            }elseif($pluginType=='api'){
	                                $pluginTitle='接口插件：';
	                            }else{
	                                continue;
	                            }
	                            if($isReleDiy){
	                                
	                                $pluginTitle.=$pluginData['app'];
	                                $pluginData['code']=base64_decode($pluginData['code']);
	                                $pluginFile=model('ReleaseApp')->appFileName($pluginData['app'],'diy');
	                                $safeCheck=model('ReleaseApp')->safeCodeCheck($pluginData['code']);
	                                if(!$safeCheck['success']){
	                                    return return_result($pluginTitle.' » 导入失败 » '.$safeCheck['msg']);
	                                }
	                                if(!write_dir_file($pluginFile, $pluginData['code'])){
	                                    return return_result($pluginTitle.' » 导入失败');
	                                }
	                            }else{
	                                $pluginTitle.=$pluginData['name'].'('.$pluginData['app'].')';
	                                $result=controller('admin/Store')->_install_plugin($pluginData);
	                                if(!$result['success']){
	                                    return return_result($pluginTitle.' » '.($result['msg']?$result['msg']:'失败'));
	                                }
	                            }
	                            $uploadedPlugin=true;
	                        }
	                        if($typeIsPlugin){
	                            return return_result('导入成功',true,array('url'=>'mystore/'.($pluginType.'App')));
	                        }
	                    }
	                }
	            }else{
	                
	                if($installPlugin){
	                    $pluginData=reset($pluginDataList);
	                    $mapp=null;
	                    if($pluginData['type']=='release'){
	                        $mapp=model('ReleaseApp');
	                    }elseif($pluginData['type']=='func'){
	                        $mapp=model('FuncApp');
	                    }elseif($pluginData['type']=='api'){
	                        $mapp=model('ApiApp');
	                    }else{
	                        return return_result('插件类型错误');
	                    }
	                    if(!input('replace')){
	                        
	                        if($mapp->where('app',$pluginData['app'])->count()>0){
	                            
	                            return return_result('',false,array('show_replace'=>1));
	                        }
	                    }
	                    
	                    $result=controller('admin/Store')->_install_plugin($pluginData);
	                    
	                    if($result['success']){
	                        return return_result('成功导入插件：'.$pluginData['app'],true,array('url'=>'mystore/'.$pluginData['type'].'App'));
	                    }else{
	                        return return_result($result['msg']);
	                    }
	                }
	            }
	        }
	        if(!$typeIsPlugin){
	            
	            if($type=='rule'){
	                
    	            if($installRule){
    	                
    	                $typeData['type']='collect';
    	                $typeData['config']=base64_encode($typeData['config']);
    	                $result=controller('admin/Store')->_install_rule($typeData,0,true);
    	                if($result['success']){
    	                    return return_result('成功导入规则'.($uploadedPlugin?'及包含的插件':'').'：'.$typeData['name'],true,array('url'=>'mystore/rule'));
    	                }else{
    	                    return return_result($result['msg']);
    	                }
    	            }else{
    	                
    	                return return_result('',true,array('show_rule_data'=>1,'rule_data'=>$typeData));
    	            }
	            }elseif($type=='release'){
	                
	                return return_result('',true,array('show_release_data'=>1,'release_data'=>$typeData));
	            }
	        }
	    }catch (\Exception $ex){
	        return return_result($ex->getMessage());
	    }
	}
	
	
	private function _auto_check_plugin(){
		$auto=input('auto/d');
		model('Config')->setConfig('store_auto_check_plugin',$auto);
		if($auto){
			$this->success('插件设置为自动检测更新');
		}else{
			$this->error('插件设置为手动检测更新');
		}
	}
	/*检测插件云平台插件更新*/
	private function _check_store_plugin_update($appList=array()){
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
			    $uptimeList=$this->_get_store_uptimes($provList[$provId], 'plugin', $apps);
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
	
	private function _get_store_uptimes($provData,$addonCat,$addonIds){
	    $uptimeList=array();
	    if(!empty($addonCat)&&!empty($addonIds)){
    	    $mprov=model('Provider');
    	    $storeUrl=$mprov->getStoreUrl($provData);
    	    $authkey=$mprov->getAuthkey($provData);
    	    
    	    $timestamp=time();
    	    
    	    $clientinfo=clientinfo();
    	    $authsign=$mprov->createAuthsign($authkey,$clientinfo['url'],$storeUrl,$timestamp);
    	    
    	    $postParams=array(
    	        'authsign'=>$authsign,
    	        'client_url'=>$clientinfo['url'],
    	        'timestamp'=>$timestamp,
    	        'addons'=>array(
    	            $addonCat=>implode(',',$addonIds),
    	        )
    	    );
    	    $uptimeList=\util\Tools::curl_store($provData?$provData['url']:'','/client/addon/update',null,array('timeout'=>3),$postParams);
	        $uptimeList=json_decode($uptimeList,true);
	        if(is_array($uptimeList)&&$uptimeList['code']&&is_array($uptimeList['data'])){
	            $uptimeList=$uptimeList['data'][$addonCat];
	            $uptimeList=is_array($uptimeList)?$uptimeList:array();
	        }else{
	            $uptimeList=array();
	        }
	    }
	    return $uptimeList;
	}
}