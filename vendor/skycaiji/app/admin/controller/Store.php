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
use think\Controller;

class Store extends BaseController {
    
    public function downloadAction(){
        $mprov=model('Provider');
        $resultData=$mprov->storeAuthResult();
        if(!$resultData['success']){
            
            $this->error($resultData['msg'],'');
        }
        $resultData=$resultData['data'];
        $provId=$resultData['provider_id'];
        
        $storeAddon=input('store_addon','','trim');
        $storeAddon=json_decode(base64_decode($storeAddon),true);
        if(!is_array($storeAddon)||empty($storeAddon)){
            $this->error('下载参数错误','');
        }
        $storeAddon=array_map('strip_tags', $storeAddon);
        list($addonCat,$addonId,$addonVer,$addonName)=$storeAddon;
        $addonCat=strtolower($addonCat);
       
        $cats=array('rule'=>'采集规则','plugin'=>'插件','app'=>'应用程序');
        $catName=$cats[$addonCat];
        
        $existAddon=false;
        $updateAddon=false;
        if(in_array($addonCat, array('rule','plugin'))){
            if('rule'==$addonCat){
                $addonData=model('Rule')->where(array('store_id'=>$addonId,'provider_id'=>$provId))->find();
            }elseif('plugin'==$addonCat){
                $addonData=model('ReleaseApp')->where('app',$addonId)->find();
                if(empty($addonData)){
                    $addonData=model('FuncApp')->where('app',$addonId)->find();
                }
            }
            if(!empty($addonData)){
                $existAddon=true;
                if($addonVer&&version_compare($addonVer, $addonData['uptime'],'>')){
                    $updateAddon=true;
                }
            }
        }elseif('app'==$addonCat){
            
            $mapp=model('App');
            $existAddon=file_exists($mapp->app_class_file($addonId))?true:false;
            if($existAddon){
                $appVersion=$mapp->app_class($addonId,false,'version');
                if($addonVer&&version_compare($addonVer, $appVersion,'>')){
                    $updateAddon=true;
                }
            }
        }
        
        $tips=$updateAddon?'更新':'下载';
        
        $this->set_html_tags(
            $tips.$catName,
            $tips.$catName,
            breadcrumb(array($tips.$catName))
        );
        
        $downloadData = array(
            'provider_id' => $provId,
            'addon_cat' => $addonCat,
            'addon_id' => $addonId,
            'addon_name' => $addonName,
            'exist_addon' => $existAddon,
            'update_addon' => $updateAddon,
            'cat_name' => $catName
        );
        $this->assign('downloadData',$downloadData);
        return $this->fetch();
    }
    
    public function installAction(){
        $this->check_usertoken();
        
        $provId=input('provider_id/d',0);
        $addonCat=input('addon_cat','','strtolower');
        $addonId=input('addon_id','','trim');
        $provData=null;
        $mprov=model('Provider');
        if($provId>0){
            
            $provData=$mprov->getById($provId);
            $check=$mprov->checkData($provData);
            if(!$check['success']){
                $this->error($check['msg']);
            }
        }
        $provId=empty($provData)?0:$provData['id'];
        $authkey=$mprov->getAuthkey($provData);
        $storeUrl=$mprov->getStoreUrl($provData);
        
        $timestamp=time();
        
        $clientinfo=clientinfo();
        $authsign=$mprov->createAuthsign($authkey,$clientinfo['url'],$storeUrl,$timestamp);
        
        $uriParams=array(
            'authsign'=>$authsign,
            'client_url'=>$clientinfo['url'],
            'timestamp'=>$timestamp,
            'addon'=>$addonCat.':'.$addonId,
        );
        
        if($addonCat=='app'){
            
            $blockNo=input('block_no/d',0);
            $uriParams['block_no']=$blockNo;
        }
        
        $storeData=\util\Tools::curl_store($provData?$provData['url']:'','/client/addon/download?'.http_build_query($uriParams));
        $storeData=json_decode($storeData,true);
        $storeData=is_array($storeData)?$storeData:array();
        $storeData['data']=is_array($storeData['data'])?$storeData['data']:array();
        
        if(!$storeData['code']){
            
            $this->error('下载失败'.($storeData['msg']?('：'.$storeData['msg']):''));
        }
        
        $result=array();
        $successUrl='';
        $successData=array();
        
        if($addonCat=='rule'){
            
            $rule=$storeData['data']['rule'];
            $rule=json_decode(base64_decode($rule),true);
            $result=$this->_install_rule($rule,$provId);
            $successUrl='mystore/rule';
        }elseif($addonCat=='plugin'){
            
            $plugin=$storeData['data']['plugin'];
            $plugin=json_decode(base64_decode($plugin),true);
            $result=$this->_install_plugin($plugin,$provId);
            $successUrl='Mystore/'.$plugin['type'].'App';
        }elseif($addonCat=='app'){
            
            $app=$storeData['data']['app'];
            $app=json_decode(base64_decode($app),true);
            $result=$this->_install_app($app,$provId);
            $successUrl='mystore/app';
            $successData=$result;
        }
        if($result['success']){
            $this->success('下载完成',$successUrl,$successData);
        }else{
            $this->error('安装失败'.($result['msg']?('：'.$result['msg']):''));
        }
    }
    
    
    public function _install_rule($rule,$provId=0,$isUpload=false){
        $rule=is_array($rule)?$rule:array();
        $rule=array_map('strip_tags', $rule);
        $store_id=intval($rule['store_id']);
        if(!$isUpload){
            if($store_id<=0){
                return return_result('规则id为空');
            }
        }
        if(empty($rule['name'])){
            return return_result('规则名称为空');
        }
        if(empty($rule['type'])){
            return return_result('规则类型为空');
        }
        if(empty($rule['module'])){
            return return_result('规则模块为空');
        }
        $rule['config']=base64_decode($rule['config']);
        if(empty($rule['config'])){
            return return_result('规则为空');
        }
        $newRule=array('type'=>$rule['type'],'module'=>$rule['module'],'store_id'=>$store_id,'name'=>$rule['name'],'addtime'=>time(),'uptime'=>($rule['uptime']>0?$rule['uptime']:time()),'config'=>$rule['config']);
        
        $mrule=model('Rule');
        
        if(!empty($rule['store_url'])){
            $newRule['provider_id']=model('Provider')->getIdByUrl($rule['store_url']);
        }else{
            $newRule['provider_id']=intval($provId);
        }
        
        if(!$isUpload){
            
            $ruleData=$mrule->where(array('store_id'=>$newRule['store_id'],'provider_id'=>$newRule['provider_id']))->find();
            if(empty($ruleData)){
                
                $mrule->isUpdate(false)->allowField(true)->save($newRule);
            }else{
                
                $mrule->strict(false)->where(array('id'=>$ruleData['id']))->update($newRule);
            }
        }else{
            
            $mrule->isUpdate(false)->allowField(true)->save($newRule);
        }
        return return_result('',true);
    }
	
	
    public function _install_plugin($plugin,$provId=0){
		$plugin=is_array($plugin)?$plugin:array();
		$plugin=array_map('strip_tags', $plugin);
		$plugin['code']=base64_decode($plugin['code']);
		if(empty($plugin['app'])){
		    return return_result('插件标识为空');
		}
		if(empty($plugin['name'])){
		    return return_result('插件名称为空');
		}
		if(empty($plugin['type'])){
		    return return_result('插件类型为空');
		}
		if(empty($plugin['module'])){
		    return return_result('插件模块为空');
		}
		if(empty($plugin['code'])){
		    return return_result('插件代码为空');
		}
		if(!empty($plugin['tpl'])){
			
			$plugin['tpl']=base64_decode($plugin['tpl']);
		}

		$newData=array('app'=>$plugin['app'],'name'=>$plugin['name'],'desc'=>$plugin['desc'],'addtime'=>time(),'uptime'=>$plugin['uptime']);
	
		
		if(!empty($plugin['store_url'])){
		    $newData['provider_id']=model('Provider')->getIdByUrl($plugin['store_url']);
		}else{
		    $newData['provider_id']=intval($provId);
		}
	
		$result=array();
		
		if($plugin['type']=='release'){
			
			$success=model('ReleaseApp')->addCms($newData,$plugin['code'],$plugin['tpl']);
			$success=$success?true:false;
			$result=return_result($success?'成功':'无效的插件',$success);
		}elseif($plugin['type']=='func'){
			
			$newData['module']=$plugin['module'];
			$success=model('FuncApp')->addFunc($newData,$plugin['code']);
			$success=$success?true:false;
			$result=return_result($success?'成功':'无效的插件',$success);
		}else{
		    $result=return_result('插件类型错误');
		}
		return $result;
	}
	
	
	private function _install_app($app,$provId=0){
	    $result=return_result('',false,array('blocks'=>0,'next_block_no'=>0));
	    
	    $app=is_array($app)?$app:array();
	    $app=array_map('strip_tags', $app);
	    
	    if(empty($app['app'])){
	        $result['msg']='应用标识为空';
	        return $result;
	    }
	    if(!preg_match('/^[\w\-]+$/',$app['app'])){
	        $result['msg']='应用标识不规范';
	        return $result;
	    }
	    if(empty($app['md5'])){
	        $result['msg']='应用md5码为空';
	        return $result;
	    }
	    $filePath=config('runtime_path').'/zip_app/'.$app['app'];
	    $result=\util\Tools::install_downloaded_zip($app, $filePath, config('apps_path').'/'.$app['app']);
	    return $result;
	}
}