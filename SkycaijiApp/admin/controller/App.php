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

class App extends BaseController {
	public function manageAction(){
		$app=input('app');
		$navid=input('navid',null);
		if(empty($app)){
			$this->error('app标识错误');
		}
		$mapp=model('App');
		$appData=$mapp->getByApp($app);
		if(empty($appData)){
			$this->error('应用未安装');
		}
		$appUrl=config('root_website').'/app/'.$app.'/';
		
		$navPacks=array();
		if(is_array($appData['config']['packs'])){
			$manageUrl=url('App/manage?app='.$app);
			$manageUrl.=strpos($manageUrl,'?')===false?'?':'&';
			foreach ($appData['config']['packs'] as $k=>$v){
				if($v['type']=='nav'){
					$v['nav_link']=str_replace(array('{app}','{apps}'), array(config('root_website').'/app/'.$app.'/',config('root_website').'/app/'),$v['nav_link']);
					if(!preg_match('/^\w+\:\/\//', $v['nav_link'])){
						
						$v['nav_link']=$appUrl.$v['nav_link'];
					}
					
					if(isset($navid)&&$navid==$k){
						
						$v['is_current']=true;
					}
					$navPacks[$k]=$v;
				}
			}
		}
		$provData=null;
		$mprov=model('Provider');
		if($appData['provider_id']>0){
			$provData=$mprov->where('id',$appData['provider_id'])->find();
		}
		
		$appClass=$mapp->app_class($app);
		if(is_object($appClass)){
			if(version_compare($appClass->config['version'], $appData['config']['version'],'>')===true){
				
				$this->assign('newest_version',$appClass->config['version']);
			}
			
			$appData['app_class']=$mapp->get_class_vars($appClass);
		}
		
		$this->assign('app',$app);
		$this->assign('appUrl',$appUrl);
		$this->assign('navid',$navid);
		$this->assign('navPacks',$navPacks);
		$this->assign('appData',$appData);
		$this->assign('provData',$provData);
		return $this->fetch();
	}
	/*协议*/
	public function agreementAction(){
		$app=input('app');
		$appClass=model('App')->app_class($app);
		$this->assign('app',$app);
		$this->assign('name',$appClass->config['name']);
		$this->assign('agreement',$appClass->config['agreement']);
		
		return $this->fetch('agreement');
	}
	/*安装*/
	public function installAction(){
		$app=input('app');
		$success=input('success');
		if(empty($app)){
			$this->error('app标识错误');
		}
		$mapp=model('App');
		
		if(!$mapp->right_app($app)){
			$this->error('抱歉，app标识不规范！');
		}
		if($mapp->where('app',$app)->count()>0){
			$this->success('该应用已安装！','Mystore/app');
		}
		$appClass=$mapp->app_class($app);
		if(!is_object($appClass)||empty($appClass->install)){
			$this->error('不存在安装接口！');
		}
		if(!empty($appClass->config['phpv'])){
			
			if(version_compare(PHP_VERSION, $appClass->config['phpv'],'<')){
				$this->error('抱歉，该应用要求PHP版本最低'.$appClass->config['phpv']);
			}
		}
		
		if($appClass->install!='1'){
			
			if(!$success){
				
				$apiUrl=config('root_url').'/app/'.$app.'/'.$appClass->install;
				$this->assign('app',$app);
				$this->assign('op','install');
				$this->assign('apiUrl',$apiUrl);
				return $this->fetch('apiop');
			}
		}
		$newData=array(
			'app'=>$app,
			'addtime'=>time(),
			'uptime'=>time(),
			'provider_id'=>model('Provider')->getIdByUrl($appClass->config['website'])
		);
		$mapp->isUpdate(false)->allowField(true)->save($newData);
		if($mapp->id>0){
			$mapp->set_config($app,$appClass->config);
			$this->success('恭喜！安装成功','Mystore/app');
		}else{
			$this->error('安装失败！');
		}
	}
	/*卸载应用*/
	public function uninstallAction(){
		$app=input('app');
		$success=input('success');
		if(empty($app)){
			$this->error('app标识错误');
		}
		$mapp=model('App');
		
		if($mapp->where('app',$app)->count()<=0){
			$this->success('该应用已卸载！','Mystore/app');
		}
		$appClass=$mapp->app_class($app);
		if(!is_object($appClass)){
			
			$mapp->deleteByApp($app);
			$this->success('卸载成功');
		}
		if(empty($appClass->uninstall)){
			$this->error('不存在卸载接口！');
		}
		
		if($appClass->uninstall!='1'){
			
			if(!$success){
				
				$apiUrl=config('root_url').'/app/'.$app.'/'.$appClass->uninstall;
				$this->assign('app',$app);
				$this->assign('op','uninstall');
				$this->assign('apiUrl',$apiUrl);
				return $this->fetch('apiop');
			}
		}
		
		$mapp->deleteByApp($app);
		
		$this->success('卸载成功，您可以手动删除app/'.$app.'目录彻底清除应用');
	}
	/*升级应用*/
	public function upgradeAction(){
		$app=input('app');
		$success=input('success');
		if(empty($app)){
			$this->error('app标识错误');
		}
		$mapp=model('App');
		
		$appData=$mapp->getByApp($app);
		if(empty($appData)){
			$this->success('请先安装应用！','Mystore/app');
		}
		$appClass=$mapp->app_class($app);
		if(!is_object($appClass)||empty($appClass->upgrade)){
			$this->error('不存在升级接口！');
		}
		$referer=\think\Request::instance()->server('HTTP_REFERER',null,null);
		if(version_compare($appClass->config['version'], $appData['config']['version'],'=')===true){
			
			$this->success('已升级！',$referer);
		}
		
		if($appClass->upgrade!='1'){
			
			if(!$success){
				
				$apiUrl=config('root_url').'/app/'.$app.'/'.$appClass->upgrade;
				$this->assign('app',$app);
				$this->assign('op','upgrade');
				$this->assign('apiUrl',$apiUrl);
				return $this->fetch('apiop');
			}
		}

		$mapp->strict(false)->where('app',$app)->update(array(
			'uptime'=>time(),
			'provider_id'=>model('Provider')->getIdByUrl($appClass->config['website'])
		));
		$mapp->set_config($app,$appClass->config);
		
		$this->success('恭喜！升级成功',$referer);
	}
	/*开启、关闭应用*/
	public function enableAction(){
		$app=input('app');
		$enable=input('enable/d',0);
		if(empty($app)){
			$this->error('app标识错误');
		}
		$mapp=model('App');

		$enable=$enable?1:0;

		$mapp->set_config($app,array('enable'=>$enable));

		$referer=\think\Request::instance()->server('HTTP_REFERER',null,null);
		$this->success('应用已'.($enable?'开启':'关闭'),$referer);
	}
}