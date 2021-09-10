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
class Api extends BaseController{
	/*任务api发布*/
	public function taskAction(){
		define('CLOSE_ECHO_MSG', true);
		$taskId=input('id/d',0);
		$apiurl=input('apiurl');
		$releData=model('Release')->where(array('task_id'=>$taskId))->find();
		$releData['config']=unserialize($releData['config']);
		if($apiurl!=$releData['config']['api']['url']){
			exit('api地址错误！');
		}
		
		define('API_TASK_RESPONSE_JSON', true);
		
		header('Content-type:text/json');
		controller('admin/Task','controller')->_collect($taskId);
	}
	/*执行采集*/
	public function collectAction(){
	    if(!input('?backstage')){
	        
	        config('dispatch_error_tmpl','common:error');
	        config('dispatch_success_tmpl','common:success');
	    }
	    
	    $this->_backstage_cli_collect('auto');
	    
		define('IS_COLLECTING', 1);
		$mcache=CacheModel::getInstance();
		$autoCacheData=$mcache->getCache('auto_collecting');
		if($autoCacheData){
		    
		    
		    $autoInterval=time()-intval($autoCacheData['dateline']);
		    if($autoInterval<=60*(g_sc('c_caiji_interval')+10)){
		        
		        
                $this->echo_msg_exit('有任务正在自动采集');
		    }
		}
		$mcache->setCache('auto_collecting',1);
		register_shutdown_function('remove_auto_collecting');
		
		if(g_sc_c('caiji','timeout')>0){
		    set_time_limit(60*g_sc_c('caiji','timeout'));
		}else{
			set_time_limit(0);
		}
		
		if(is_empty(g_sc_c('caiji','auto'))){
		    $this->echo_msg_exit('请先开启自动采集 <a href="'.url('Admin/Setting/caiji').'" target="_blank">设置</a>');
		}
		
		$checkCollectWait=\skycaiji\admin\model\Config::check_collect_wait();
		if($checkCollectWait){
		    $this->echo_msg_exit($checkCollectWait['msg'].' <a href="'.url('Admin/Setting/caiji').'" target="_blank">设置运行间隔</a>');
		}
		
		
		$mtask=model('Task');
		$taskIds=$mtask->alias('t')->join(model('Collector')->get_table_name().' c','t.id=c.task_id')
			->field('task_id')->where("t.auto=1 and t.module='pattern'")->order('t.caijitime asc')->column('task_id');
		
		if(empty($taskIds)){
		    $this->echo_msg_exit('没有可自动采集的任务 <a href="'.url('Admin/Task/list').'" target="_blank">设置</a>');
		}
		cache('last_collect_time',time());
		
		controller('admin/Task','controller')->_collect_batch($taskIds);
		
		$this->echo_msg('所有任务执行完毕！','green');
		
		$this->_echo_msg_end();
	}
	/*客户端信息*/
	public function clientinfoAction(){
	    return json(clientinfo());
	}
	
	/*验证站点*/
	public function certificateAction(){
	    $data=array('code'=>0,'msg'=>'','data'=>array());
	    $mprov=model('Provider');
	    $resultData=$mprov->storeAuthResult();
	    if(!$resultData['success']){
	        
	        $data['msg']=$resultData['msg'];
	    }else{
	        $data['code']=1;
	        $data['data']['clientinfo']=clientinfo();
	    }
	    return json($data);
	}
	
	/*云平台检测更新*/
	public function store_updateAction(){
	    $updateResult=array('code'=>0,'msg'=>'','data'=>array());
	    
        $mprov=model('Provider');
        $resultData=$mprov->storeAuthResult();
        if(!$resultData['success']){
            
            $updateResult['msg']=$resultData['msg'];
        }else{
            
            $resultData=$resultData['data'];
            $provId=$resultData['provider_id'];
            
            $storeAddons=input('store_addons');
            $storeAddons=json_decode(base64_decode($storeAddons),true);
            $storeAddons=is_array($storeAddons)?$storeAddons:array();
            
            if(!empty($storeAddons['rule'])&&is_array($storeAddons['rule'])){
                
                $cond=array('store_id'=>array('in',$storeAddons['rule']),'provider_id'=>$provId);
                $list=model('Rule')->field('`id`,`store_id`,`uptime`')->where($cond)->column('uptime','store_id');
                $updateResult['data']['rule']=$list;
            }
            if(!empty($storeAddons['plugin'])&&is_array($storeAddons['plugin'])){
                
                $cond=array('app'=>array('in',$storeAddons['plugin']),'provider_id'=>$provId);
                $listRelease=model('ReleaseApp')->where($cond)->column('uptime','app');
                $listRelease=is_array($listRelease)?$listRelease:array();
                $listFunc=model('FuncApp')->where($cond)->column('uptime','app');
                $listFunc=is_array($listFunc)?$listFunc:array();
                $updateResult['data']['plugin']=array_merge($listRelease,$listFunc);
            }
            if(!empty($storeAddons['app'])&&is_array($storeAddons['app'])){
                
                $list=array();
                foreach ($storeAddons['app'] as $app){
                    
                    $appVer=model('App')->app_class($app,false,'version');
                    if(!empty($appVer)){
                        $list[$app]=$appVer;
                    }
                }
                $updateResult['data']['app']=$list;
            }
            $updateResult['code']=1;
        }
	    
	    return json($updateResult);
	}
}