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

namespace skycaiji\admin\behavior;

use skycaiji\admin\controller\BaseController;
class Init{
	
	public function run(){
		
		$curController=strtolower(request()->controller());
		if('store'==$curController){
			
			$httpOrigin=strtolower($_SERVER['HTTP_ORIGIN']);
			$httpOrigin=rtrim($httpOrigin,'/');
			
			$allowOrigins=array('http://www.skycaiji.com','https://www.skycaiji.com');
			
			$allowOrigin='';
			if(in_array($httpOrigin,$allowOrigins)){
				
				$allowOrigin=$httpOrigin;
			}else{
				
				if(model('Provider')->where(array('domain'=>$httpOrigin,'enable'=>1))->count()>0){
					
					$allowOrigin=$httpOrigin;
				}
			}
			
			
			header('Access-Control-Allow-Origin:'.$allowOrigin);
			
			header('Access-Control-Allow-Credentials:true');
			
			header('Access-Control-Allow-Methods:POST,GET');
			
			
		}
		/*自动登录*/
		$muser=model('User');
		$s_userid=session('user_id');
		if(!IS_CLI&&empty($s_userid)){
			
			$login_history=cookie('login_history');
			if(!empty($login_history)){
				$login_history=explode('|', $login_history);
				$user=$muser->where('username',$login_history[0])->find();
				if(!empty($user)){
					$user['username']=strtolower($user['username']);
					
					if($user['username']==$login_history[0]&&$login_history[1]==md5($user['username'].$user['password'])){
						session('user_id',$user['uid']);
					}
				}
			}
			$s_userid=session('user_id');
		}
		
		if($s_userid>0){
			$GLOBALS['user']=$muser->getByUid($s_userid);
			if(!empty($GLOBALS['user'])){
				$GLOBALS['user']=$GLOBALS['user']->toArray();
				$GLOBALS['user']['group']=model('Usergroup')->getById($GLOBALS['user']['groupid']);
				if(!empty($GLOBALS['user']['group'])){
					$GLOBALS['user']['group']=$GLOBALS['user']['group']->toArray();
					if(model('Usergroup')->is_admin($GLOBALS['user']['group'])){
						session('is_admin',true);
					}else{
						session('is_admin',null);
					}
				}
			}
		}
		
		if(!empty($GLOBALS['user'])&&session('is_admin')){
			/*是管理员，进行下列操作*/
			if('index'==$curController&&'index'==strtolower(request()->action())){
				
				$url=null;
				if(input('?_referer')){
					
					$url=input('_referer','','trim');
				}
				$url=empty($url)?url('Admin/Backstage/index',null,null,true):$url;
				
				$baseContr=new BaseController();
				$baseContr->success(lang('user_auto_login'),$url);
			}
			config('dispatch_error_tmpl','common:error_admin');
			config('dispatch_success_tmpl','common:success_admin');
		}else{
			
			if(!in_array($curController, array('index','api'))){
				
				$baseContr=new BaseController();
				$baseContr->dispatchJump(false,lang('user_error_is_not_admin'),url('Admin/Index/index',null,null,true));
				exit();
			}
		}
		/*通用操作,全局变量*/
		$mconfig=model('Config');
		$latestDate=$mconfig->max('dateline');
		$keyConfig='cache_config_all';
		$cacheConfig=cache($keyConfig);
		$configList=array();
		if(empty($cacheConfig)||$cacheConfig['update_time']!=$latestDate){
			
			$configDbList=$mconfig->column('*');
			$configDbList=empty($configDbList)?array():$configDbList;
			foreach ($configDbList as $configItem){
				$configItem=$mconfig->convertData($configItem);
				$configList[$configItem['cname']]=$configItem['data'];
			}
			cache($keyConfig,array('update_time'=>$latestDate,'list'=>$configList));
		}else{
			$configList=$cacheConfig['list'];
		}
		$GLOBALS['config']=$configList;
		
		if(!empty($GLOBALS['config']['site']['closelog'])){
			
			\think\Log::init(array('type'=>'test','level'=>array()));
		}
		if(!empty($GLOBALS['config']['site']['dblong'])){
			
			$dbParams=config('database.params');
			$dbParams[\PDO::ATTR_PERSISTENT]=true;
			config('database.params',$dbParams);
		}
		
		$GLOBALS['clientinfo']=clientinfo();
		if(!empty($GLOBALS['clientinfo'])){
			$GLOBALS['clientinfo']=base64_encode(json_encode($GLOBALS['clientinfo']));
		}
		
		
		$usertoken=session('usertoken');
		if(empty($usertoken)){
			
			$usertoken=rand(1, 9999999).'_'.date('Y-m-d');
			$usertoken=md5($usertoken);
			session('usertoken',$usertoken);
		}
		$GLOBALS['usertoken']=$usertoken;
	}
}

?>