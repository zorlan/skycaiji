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
			$GLOBALS['_sc']['user']=$muser->getByUid($s_userid);
			if(!empty($GLOBALS['_sc']['user'])){
				$GLOBALS['_sc']['user']=$GLOBALS['_sc']['user']->toArray();
				$GLOBALS['_sc']['user']['group']=model('Usergroup')->getById($GLOBALS['_sc']['user']['groupid']);
				if(!empty($GLOBALS['_sc']['user']['group'])){
					$GLOBALS['_sc']['user']['group']=$GLOBALS['_sc']['user']['group']->toArray();
					if(model('Usergroup')->is_admin($GLOBALS['_sc']['user']['group'])){
						session('is_admin',true);
					}else{
						session('is_admin',null);
					}
				}
			}
		}
		
		if(!empty($GLOBALS['_sc']['user'])&&session('is_admin')){
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
		$configList=$mconfig->getConfigList();
		if(empty($configList)){
			$mconfig->cacheConfigList();
			$configList=$mconfig->getConfigList();
		}
		$GLOBALS['_sc']['c']=$configList;
		
		if(!empty($GLOBALS['_sc']['c']['site']['closelog'])){
			
			\think\Log::init(array('type'=>'test','level'=>array()));
		}
		if(!empty($GLOBALS['_sc']['c']['site']['dblong'])){
			
			$dbParams=config('database.params');
			$dbParams[\PDO::ATTR_PERSISTENT]=true;
			config('database.params',$dbParams);
		}
		
		
		if(empty($GLOBALS['_sc']['c']['download_img'])){
			$GLOBALS['_sc']['c']['download_img']=$mconfig->get_img_config_from_caiji($GLOBALS['_sc']['c']['caiji']);
		}
		
		$GLOBALS['_sc']['clientinfo']=clientinfo();
		if(!empty($GLOBALS['_sc']['clientinfo'])){
			$GLOBALS['_sc']['clientinfo']=base64_encode(json_encode($GLOBALS['_sc']['clientinfo']));
		}
		
		
		$usertoken=session('usertoken');
		if(empty($usertoken)){
			
			$usertoken=rand(1, 9999999).'_'.date('Y-m-d');
			$usertoken=md5($usertoken);
			session('usertoken',$usertoken);
		}
		$GLOBALS['_sc']['usertoken']=$usertoken;
	}
}

?>