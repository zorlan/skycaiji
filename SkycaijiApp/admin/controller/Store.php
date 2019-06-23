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

class Store extends BaseController {
	
	public function isLoginAction(){
		if(empty($GLOBALS['user'])){
			$this->dispatchJump(false,lang('user_error_is_not_admin'),url('Admin/Index/index',null,null,true));
		}else{
			$this->dispatchJump(true);
		}
	}
	public function indexAction(){
		$url=input('url','','strip_tags');
		if(!empty($url)&&!is_official_url($url)){
			
			$provData=model('Provider')->where('url',$url)->find();
			if(empty($provData)){
				$this->error($url.' 平台未添加');
			}
			if(empty($provData['enable'])){
				$this->error($url.' 已设置为拒绝访问');
			}
			$url=$provData['url'];
			
			$url.=strpos($url, '?')===false?'?':'&';
			$url.='clientinfo='.urlencode($GLOBALS['clientinfo']);

			$this->assign('provData',$provData);
		}
		if(empty($url)){
			$url='https://www.skycaiji.com/store';
		}
		
		if(!empty($url)){
			
		}

		$GLOBALS['content_header']=lang('store');
		$GLOBALS['breadcrumb']=breadcrumb(array(lang('store')));
		
		$this->assign('url',$url);
		return $this->fetch();
	}
	/*安装规则*/
	public function installRuleAction(){
		$mrule=model('Rule');
		$rule=json_decode(base64_decode(input('post.rule')),true);
		
		$store_id=intval($rule['store_id']);
		if(empty($store_id)){
			$this->dispatchJump(false,'规则id为空');
		}
		if(empty($rule['name'])){
			$this->dispatchJump(false,'名称为空');
		}
		if(empty($rule['type'])){
			$this->dispatchJump(false,'类型错误');
		}
		if(empty($rule['module'])){
			$this->dispatchJump(false,'模块错误');
		}
		$rule['config']=base64_decode($rule['config']);
		if(empty($rule['config'])){
			$this->dispatchJump(false,'规则为空');
		}
		if($store_id>0){
			$newRule=array('type'=>$rule['type'],'module'=>$rule['module'],'store_id'=>$store_id,'name'=>$rule['name'],'uptime'=>($rule['uptime']>0?$rule['uptime']:time()),'config'=>$rule['config']);
			
			$newRule['provider_id']=$this->_getStoreProvid($rule['store_url']);
			$ruleData=$mrule->where(array('store_id'=>$newRule['store_id'],'provider_id'=>$newRule['provider_id']))->find();
			if(empty($ruleData)){
				
				$newRule['addtime']=NOW_TIME;
				$mrule->isUpdate(false)->allowField(true)->save($newRule);
				$ruleId=$mrule->id;
			}else{
				
				$mrule->strict(false)->where(array('id'=>$ruleData['id']))->update($newRule);
				$ruleId=$ruleData['id'];
			}
			$this->dispatchJump(true,$ruleId);
		}else{
			$this->dispatchJump(false,'id错误');
		}
	}
	/*安装插件*/
	public function installPluginAction(){
		$plugin=json_decode(base64_decode(input('post.plugin')),true);
		$plugin['code']=base64_decode($plugin['code']);
		if(empty($plugin['app'])){
			$this->dispatchJump(false,'标识错误');
		}
		if(empty($plugin['name'])){
			$this->dispatchJump(false,'名称错误');
		}
		if(empty($plugin['type'])){
			$this->dispatchJump(false,'类型错误');
		}
		if(empty($plugin['module'])){
			$this->dispatchJump(false,'模块错误');
		}
		if(empty($plugin['code'])){
			$this->dispatchJump(false,'不是可用的程序');
		}
		if(!empty($plugin['tpl'])){
			
			$plugin['tpl']=base64_decode($plugin['tpl']);
		}
		
		$newData=array('app'=>$plugin['app'],'name'=>$plugin['name'],'desc'=>$plugin['desc'],'uptime'=>$plugin['uptime']);
		
		
		$newData['provider_id']=$this->_getStoreProvid($plugin['store_url']);
		
		if($plugin['type']=='release'){
			model('ReleaseApp')->addCms($newData,$plugin['code'],$plugin['tpl']);
			$this->dispatchJump(true);
		}else{
			$this->dispatchJump(false);
		}
	}
	/*安装应用程序*/
	public function installAppAction(){
		$app=json_decode(base64_decode(input('post.app')),true);
		if(empty($app['app'])){
			$this->dispatchJump(false,'app标识错误');
		}
		if(!preg_match('/^[\w\-]+$/',$app['app'])){
			$this->dispatchJump(false,'app标识不规范');
		}
		if(empty($app['data'])){
			$this->dispatchJump(false,'数据错误');
		}
		$app['data']=base64_decode($app['data']);
	
		$filePath=RUNTIME_PATH.'/cache_app_zip/'.$app['app'].'/';
	
		$complete=false;
		if($app['block']>0){
			
			$app['no']=intval($app['no']);
			write_dir_file($filePath.$app['no'],$app['data']);
				
			$blockComplete=true;
			for($i=1;$i<=$app['block'];$i++){
				if(!file_exists($filePath.$i)){
					
					$blockComplete=false;
					break;
				}
			}
			if($blockComplete){
				
				$data=null;
				for($i=1;$i<=$app['block'];$i++){
					$data.=file_get_contents($filePath.$i);
				}
				write_dir_file($filePath.$app['app'].'.zip',$data);
				$complete=true;
				unset($data);
			}
		}else{
			
			write_dir_file($filePath.$app['app'].'.zip',$app['data']);
			$complete=true;
		}
		if($complete){
			
			$error='';
			try {
				$zipClass=new \ZipArchive();
				if($zipClass->open($filePath.$app['app'].'.zip')===TRUE){
					$zipClass->extractTo(config('apps_path').'/'.$app['app']);
					$zipClass->close();
				}else{
					$error='解压失败';
				}
			}catch(\Exception $ex){
				$error='您的服务器不支持ZipArchive解压';
			}
			
			if($error){
				$this->dispatchJump(false,$error);
			}else{
				clear_dir($filePath);
				$this->dispatchJump(true);
			}
		}else{
			$this->dispatchJump(true);
		}
	}
	/*统一检测更新*/
	public function updateAction(){
		$storeIds=input('store_ids');
		$storeIds=explode(',', $storeIds);
		
		$storeApps=input('store_apps');
		$storeApps=explode(',', $storeApps);
		
		$storeIdList=array();
		foreach ($storeIds as $id){
			if(preg_match('/^(\w+)_(\w+)$/',$id,$id)){
				$storeIdList[$id[1]][$id[2]]=$id[2];
			}
		}
		
		$storeAppList=array();
		foreach ($storeApps as $app){
			if(preg_match('/^(\w+)_(\w+)$/',$app,$app)){
				$storeAppList[$app[1]][$app[2]]=$app[2];
			}
		}
		
		$provId=$this->_getStoreProvid(input('store_url'));
		
		$updateList=array('status'=>1,'data'=>array());
		
		if(!empty($storeIdList)){
			foreach ($storeIdList as $type=>$ids){
				$list=array();
				$cond=array('store_id'=>array('in',$ids),'provider_id'=>$provId,'type'=>$type);
				$list=model('Rule')->field('`id`,`store_id`,`uptime`')->where($cond)->column('uptime','store_id');
				$list=is_array($list)?$list:array();
				$updateList['data'][$type]=$list;
			}
		}
		
		if(!empty($storeAppList)){
			foreach ($storeAppList as $type=>$apps){
				if(empty($type)){
					continue;
				}
				$list=array();
				$cond=array('app'=>array('in',$apps),'provider_id'=>$provId);
				if($type=='release'||$type=='cms'){
					$list=model('ReleaseApp')->where($cond)->column('uptime','app');
				}elseif($type=='app'){
					foreach ($apps as $app){
						
						$appClass=model('App')->app_class($app,false);
						$list[$app]=$appClass->config['version'];
					}
				}
				$list=is_array($list)?$list:array();
				$updateList['data'][$type]=$list;
			}
		}
		return jsonp($updateList);
	}
	/*站点验证*/
	public function siteCertificationAction(){
		$op=input('op');
		if($op=='set_key'){
			
			$key=input('post.key');
			if(empty($key)){
				$this->dispatchJump(false,'密钥错误');
			}
			cache('site_certification',array('key'=>$key,'time'=>NOW_TIME));
			$this->dispatchJump(true);
		}else{
			$this->dispatchJump(false,'操作错误！');
		}
	}
	/*获取平台域名Id*/
	protected function _getStoreProvid($storeUrl=null){
		$referer=request()->server('HTTP_REFERER');
		if(!empty($referer)){
			$storeUrl=$referer;
		}
    	$provId=model('Provider')->getIdByUrl($storeUrl);
    	return $provId;
	}
}