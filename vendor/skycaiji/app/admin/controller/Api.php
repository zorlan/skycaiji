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
	    \util\Param::set_task_close_echo();
		$taskId=input('id/d',0);
		$apiurl=input('apiurl');
		$mrele=model('Release');
		$releData=$mrele->where(array('task_id'=>$taskId))->find();
		if(empty($releData)){
		    json(array('error'=>'没有发布设置！'))->send();
		}
		$releData['config']=$mrele->compatible_config($releData['config']);
		if($apiurl!=$releData['config']['api']['url']){
		    json(array('error'=>'api地址错误！'))->send();
		}
		\util\Param::set_task_api_response();
		header('Content-type:text/json');
		$this->collect_tasks($taskId, null, true);
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