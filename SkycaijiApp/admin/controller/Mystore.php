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
    	$this->redirect('Mystore/store');
	}
	public function storeAction(){
	    set_g_sc('p_title','云平台');
	    
	    $url=input('url','','trim');
	    
	    $notSafe='';
	    
	    if(!empty($url)&&!\skycaiji\admin\model\Provider::is_official_url($url)){
	        
	        $provData=model('Provider')->where('url',$url)->find();
	        set_g_sc('p_title','第三方平台');
	        if(empty($provData)){
	            $this->error($url.' 平台未添加','');
	        }
	        if(empty($provData['enable'])){
	            $this->error($url.' 已设置为拒绝访问','');
	        }
	        $url=$provData['url'];
	        set_g_sc('p_title','第三方:'.$provData['title']);
	        
	        $storeData=curl_skycaiji('/client/info/store?url='.rawurlencode($url).'&clientinfo='.rawurlencode(g_sc('clientinfo')));
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
	        
	        set_g_sc('p_name',lang('store'));
	        set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Mystore/store'),'title'=>lang('store')))));
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
		
		set_g_sc('p_title',lang('rule_'.$type));
		set_g_sc('p_name','已下载');
		set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Mystore/rule'),'title'=>'已下载：'.lang('rule_'.$type)))));
		
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
		
		set_g_sc('p_title','发布插件');
		set_g_sc('p_name','已下载');
		set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Mystore/releaseApp'),'title'=>'已下载：发布插件'))));
		
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
			
			$this->_deleteReleaseApp($id);
			$this->success(lang('delete_success'));
		}elseif($op=='deleteall'){
			
		    $ids=input('ids/a',array());
			if(is_array($ids)&&count($ids)>0){
				foreach ($ids as $idv){
					$this->_deleteReleaseApp($idv);
				}
			}
    		$this->success(lang('op_success'),'Mystore/ReleaseApp');
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
	
	protected function _deleteReleaseApp($id){
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
		
		set_g_sc('p_title','应用程序');
		set_g_sc('p_name','应用程序');
		set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Mystore/app'),'title'=>'应用程序'))));
		
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
						if(!empty($verV)&&version_compare($verV,$appList[$app]['version'],'>')){
							
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
		
		set_g_sc('p_title','函数插件');
		set_g_sc('p_name','已下载');
		set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Mystore/funcApp'),'title'=>'已下载：函数插件'))));
		
		$this->assign('appList',$appList);
		$this->assign('modules',$mfuncApp->funcModules);
		return $this->fetch('func');
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
    		$this->success(lang('op_success'),'Mystore/funcApp');
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
	/*导入插件*/
	public function uploadAction(){
		if(request()->isPost()){
			if(g_sc_c('site','verifycode')){
				
				$verifycode=trim(input('verifycode'));
				$check=check_verify($verifycode);
				if(!$check['success']){
					$this->error($check['msg']);
				}
			}
			$file=$_FILES['plugin_file'];
			if(empty($file)||empty($file['tmp_name'])){
				$this->error('请选择插件文件');
			}
			$fileTxt=file_get_contents($file['tmp_name']);
			$pluginData=null;
    		if(preg_match('/\/\*skycaiji-plugin-start\*\/(?P<plugin>[\s\S]+?)\/\*skycaiji-plugin-end\*\//i',$fileTxt,$pluginMatch)){
    			$pluginData=unserialize(base64_decode(trim($pluginMatch['plugin'])));
			}
			if(empty($pluginData)){
				$this->error('不是插件文件');
			}
			$mapp=null;
			if($pluginData['type']=='release'){
				$mapp=model('ReleaseApp');
			}elseif($pluginData['type']=='func'){
				$mapp=model('FuncApp');
			}else{
				$this->error('分类错误');
			}
			
			if(!input('replace')){
				
				$pluginDb=$mapp->where('app',$pluginData['app'])->find();
				if(!empty($pluginDb)){
					
					$this->error('',null,array('js'=>"confirmRight('插件已存在，是否替换？',win_submit_replace)"));
				}
			}
			
			$result=controller('admin/Store')->_installPlugin($pluginData);
			
			if($result['success']){
				$this->success('成功导入插件：'.$pluginData['app'],'Mystore/'.$pluginData['type'].'App');
			}else{
				$this->error($result['msg']);
			}
			
		}else{
			return $this->fetch();
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
	/*检测插件云平台插件更新*/
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
	
	public function _get_store_uptimes($provData,$addonCat,$addonIds){
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
    	    $uptimeList=curl_store($provData?$provData['url']:'','/client/addon/update',null,array('timeout'=>3),$postParams);
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