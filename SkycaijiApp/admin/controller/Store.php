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

class Store extends BaseController {
	
	public function isLoginAction(){
		if(empty($GLOBALS['user'])){
			$this->dispatchJump(false,lang('user_error_is_not_admin'),url('Admin/Index/index',null,null,true));
		}else{
			$this->dispatchJump(true);
		}
	}
	public function indexAction(){
		$GLOBALS['content_header']=lang('store');
		$GLOBALS['breadcrumb']=breadcrumb(array(lang('store')));
		
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
			$newRule=array('type'=>$rule['type'],'name'=>$rule['name'],'module'=>$rule['module'],'uptime'=>($rule['uptime']>0?$rule['uptime']:NOW_TIME),'config'=>$rule['config']);
			$ruleData=$mrule->where(array('type'=>$rule['type'],'store_id'=>$store_id))->find();
			if(empty($ruleData)){
				
				$newRule['store_id']=$store_id;
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
	/*规则更新时间*/
	public function ruleUpdateAction(){
		$storeIds=input('store_ids');
		$storeIdList=array('collect'=>array());
		foreach (array_keys($storeIdList) as $type){
			if(preg_match_all('/\b'.$type.'\_(\d+)/i', $storeIds,$typeIds)){
				$storeIdList[$type]=$typeIds[1];
			}
		}
		$uptimeList=array('status'=>1,'data'=>array());
		$mrule=model('Rule');
		if(!empty($storeIdList)){
			foreach ($storeIdList as $type=>$ids){
				if(!empty($ids)){
					$cond=array();
					$cond['type']=$type;
					$cond['store_id']=array('in',$ids);
					$uptimeList['data'][$type]=$mrule->field('`id`,`type`,`store_id`,`uptime`')->where($cond)->column('uptime','store_id');
				}
			}
		}
		return jsonp($uptimeList);
	}
	/*安装cms发布程序*/
	public function installCmsAction(){
		$cms=json_decode(base64_decode(input('post.cms')),true);
		$cms['code']=base64_decode($cms['code']);
		if(empty($cms['app'])){
			$this->dispatchJump(false,'插件id错误');
		}
		if(empty($cms['name'])){
			$this->dispatchJump(false,'插件名错误');
		}
		if(empty($cms['code'])){
			$this->dispatchJump(false,'不是可用的程序');
		}
		if(!empty($cms['tpl'])){
			
			$cms['tpl']=base64_decode($cms['tpl']);
		}
		
		model('ReleaseApp')->addCms(array('app'=>$cms['app'],'name'=>$cms['name'],'desc'=>$cms['desc'],'uptime'=>$cms['uptime'])
			,$cms['code'],$cms['tpl']);
		
		$this->dispatchJump(true);
	}
	/*cms发布插件更新时间*/
	public function cmsUpdateAction(){
		$storeApps=input('store_apps');
		if(preg_match_all('/\bcms\_(\w+)/i', $storeApps,$apps)){
			$apps=$apps[1];
		}
		$uptimeList=array('status'=>1,'data'=>array());
		if(!empty($apps)){
			$cond=array();
			$cond['module']='cms';
			$cond['app']=array('in',$apps);
			$uptimeList['data']=model('ReleaseApp')->where($cond)->column('uptime','app');
		}
		return jsonp($uptimeList);
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
}