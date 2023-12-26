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

class Api extends CollectController{
	/*任务api发布*/
    public function taskAction(){
        header('Content-type:text/json');
        \util\Param::set_task_close_echo();
        $taskId=input('id/d',0);
        $apiKey=input('key');
        $mrele=model('Release');
        $keyIsOk=false;
        $keyIsUrl=false;
        $releData=$mrele->where(array('task_id'=>$taskId))->find();
        if(!empty($releData)){
            $releData['config']=$mrele->compatible_config($releData['config']);
            $apiConfig=$releData['config']['api'];
            init_array($apiConfig);
            if(!empty($apiKey)){
                if(isset($apiConfig['url'])){
                    
                    if($apiKey==$apiConfig['url']){
                        $keyIsOk=true;
                    }
                }elseif($apiKey==md5($apiConfig['key'])){
                    $keyIsOk=true;
                }
            }
            if(isset($apiConfig['url'])){
                $keyIsUrl=true;
            }
        }
        if($keyIsUrl){
            set_g_sc('api_task_key_is_url', true);
        }
        if(!$keyIsOk){
            if($keyIsUrl){
                json(array('error'=>'密钥错误'))->send();
            }else{
                $this->_json('密钥错误');
            }
        }
        \util\Param::set_task_api_response();
        
        register_shutdown_function(function(){
            \skycaiji\admin\model\Task::collecting_remove_all();
            
            $taskIds=g_sc('backstage_task_ids');
            if(!empty($taskIds)&&is_array($taskIds)){
                \skycaiji\admin\model\CacheModel::getInstance('backstage_task')->db()->strict(false)->where('cname','in',$taskIds)->update(array('ctype'=>1,'data'=>time()));
            }
        });
        $this->collect_tasks($taskId, null, true);
    }
	/*任务单页采集*/
	public function singleAction(){
	    \util\Param::set_task_close_echo();
	    \util\Param::set_collector_single();
	    $taskId=input('id/d',0);
	    $key=input('key');
	    
	    $mtask=model('Task');
	    $mcoll=model('Collector');
	    $mrele=model('Release');
	    $taskData=$mtask->getById($taskId);
	    if(empty($taskData)){
	        $this->_json(lang('task_error_empty_task'));
	    }
	    
	    $singleConfig=$taskData['config']['single'];
	    init_array($singleConfig);
	    if(empty($singleConfig['open'])){
	        $this->_json('未开启单页采集模式');
	    }
	    if($singleConfig['key']){
	        if($key!=md5($singleConfig['key'])){
	            $this->_json('接口密钥错误');
	        }
	    }
	    $taskTips='任务：'.$taskData['name'].' » ';
	    if(empty($taskData['module'])){
	        
	        $this->_json($taskTips.lang('task_error_null_module'));
	    }
	    if(!in_array($taskData['module'],config('allow_coll_modules'))){
	        
	        $this->_json($taskTips.lang('coll_error_invalid_module'));
	    }
	    $collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
	    if(empty($collData)){
	        
	        $this->_json($taskTips.lang('coll_error_empty_coll'));
	    }
	    $collData=$collData->toArray();
	    $mtask->loadConfig($taskData);
	    $acoll='\\skycaiji\\admin\\event\\C'.strtolower($collData['module']).'Single';
	    $acoll=new $acoll();
	    $acoll->init($collData);
	    $releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
	    $arele=null;
	    if($releData){
	        
	        $releData=$releData->toArray();
	        if($releData['module']&&$releData['module']!='api'){
	            
	            $arele='\\skycaiji\\admin\\event\\R'.strtolower($releData['module']);
	            $arele=new $arele();
	            $arele->init($releData);
	            $GLOBALS['_sc']['real_time_release']=&$arele;
	        }
	    }
	    $fieldData=$acoll->collectSingle($singleConfig);
	    init_array($fieldData);
	    if($fieldData['collected']&&$arele&&is_empty(g_sc_c('caiji','real_time'))){
	        
	        $arele->doExport($fieldData['collected']);
	    }
	    if(empty($fieldData['data'])){
	        $msg=g_sc('collect_echo_msg_txt');
	        $msg=strip_tags($msg);
	        $this->_json($msg);
	    }else{
	        $this->_json('',1,$fieldData['data']);
	    }
	}
	
	/*api触发任务采集*/
	public function caijiAction(){
	    $result=return_result('');
	    if(is_empty(g_sc_c('caiji','api'))){
	        $result['msg']='不允许接口触发采集';
	    }else{
	        $taskIds=input('tids','');
	        $taskIds=explode(',', $taskIds);
	        init_array($taskIds);
	        $taskIds=array_map('intval', $taskIds);
	        $taskIds=array_unique($taskIds);
	        $taskIds=array_filter($taskIds);
	        $taskIds=array_values($taskIds);
	        if(empty($taskIds)){
	            $result['msg']='没有任务id';
	        }else{
	            $nowTime=time();
	            $apiKey=g_sc_c('caiji','api_key');
	            $isRight=false;
	            if(g_sc_c('caiji','api_type')=='safe'){
	                
	                $ts=input('ts/d',0);
	                if(md5($apiKey.$ts)==input('sign','')){
	                    if(abs($ts-$nowTime)<=300){
	                        $isRight=true;
	                    }else{
	                        $result['msg']='签名过期';
	                    }
	                }else{
	                    $result['msg']='签名错误';
	                }
	            }else{
	                
	                if($apiKey){
	                    
	                    if(md5($apiKey)==input('key','')){
	                        $isRight=true;
	                    }else{
	                        $result['msg']='密钥错误';
	                    }
	                }else{
	                    
	                    $isRight=true;
	                }
	            }
	            if($isRight){
	                $cacheKey='api_caiji_visit_time'.($taskIds?('_'.$taskIds):'');
	                $cacheKey=md5($cacheKey);
	                
	                $mcache=\skycaiji\admin\model\CacheModel::getInstance();
	                $visitTime=$mcache->getCache($cacheKey,'data');
	                $visitTime=intval($visitTime);
	                $apiInterval=g_sc_c('caiji','api_interval');
	                $apiInterval=intval($apiInterval);
	                $apiInterval=$apiInterval>0?$apiInterval:15;
	                $apiInterval=$apiInterval-abs($nowTime-$visitTime);
	                
	                if($visitTime&&$apiInterval>0){
	                    
	                    $result['msg']='采集已经触发，'.$apiInterval.'秒后才能再次访问';
	                }else{
	                    $mcache->setCache($cacheKey,$nowTime);
	                    $rootUrl=\think\Config::get('root_website').'/index.php?s=';
	                    \skycaiji\admin\model\Collector::collect_run_auto($rootUrl,$taskIds,true);
	                    $result['msg']='成功触发采集';
	                    $result['success']=true;
	                }
	            }
	        }
	    }
	    return json($result);
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
	
	private function _json($msg='',$code=0,$data=array()){
	    init_array($data);
	    $result=array('code'=>$code,'msg'=>$msg,'data'=>$data);
	    json($result)->send();
	}
}