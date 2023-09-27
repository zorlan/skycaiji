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
		/*自动登录*/
		$muser=model('User');
		
		$s_userlogin=session('user_login');
		if(!IS_CLI&&empty($s_userlogin)){
			
			$loginHistory=cookie('login_history');
			if(!empty($loginHistory)){
				$loginHistory=explode('|', $loginHistory);
				$user=$muser->getByUid($loginHistory[0]);
				if(!empty($user)){
					
				    if($loginHistory[1]==$muser->generate_key($user)){
					    $muser->setLoginSession($user);
					}
				}
				unset($user);
			}
			$s_userlogin=session('user_login');
		}
		$s_userlogin=is_array($s_userlogin)?$s_userlogin:array();
		set_g_sc('user_login', $s_userlogin);
		
		$s_userid=intval($s_userlogin['uid']);
		
		$isAdmin=null;
		$user=null;
		if($s_userid>0){
		    $userKeyIsRight=false;
			$user=$muser->getByUid($s_userid);
			if(!empty($user)){
			    $user=$user->toArray();
			    $user['group']=model('Usergroup')->getById($user['groupid']);
			    if(!empty($user['group'])){
			        $user['group']=$user['group']->toArray();
			        if(model('Usergroup')->is_admin($user['group'])){
					    $isAdmin=true;
					}
				}
				if($s_userlogin['key']==$muser->generate_key($user)){
				    
				    $userKeyIsRight=true;
				}
			}
			if(!$userKeyIsRight){
			    
			    $user=null;
			}
		}
		if(empty($user)){
		    
		    $muser->setLoginSession(null);
		}
		
		set_g_sc('user',$user);
		
		session('is_admin',$isAdmin);
		
		if(!is_empty(g_sc('user'))&&$isAdmin){
			/*是管理员，进行下列操作*/
			if('index'==$curController&&'index'==strtolower(request()->action())){
				
				$url=null;
				if(input('?_referer')){
					
					$url=input('_referer','','trim');
				}
				$url=empty($url)?url('admin/backstage/index',null,null,true):$url;
				
				$baseContr=new BaseController();
				$baseContr->success(lang('user_auto_login'),$url);
			}
			config('dispatch_error_tmpl','common:error_admin');
			config('dispatch_success_tmpl','common:success_admin');
		}else{
			
			if(!in_array($curController, array('index','api'))){
				
				$baseContr=new BaseController();
				$baseContr->dispatchJump(false,lang('user_error_is_not_admin'),url('admin/index/index',null,null,true));
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
		set_g_sc('c',$configList);
		set_g_sc('c_original',$configList);
		
		$timezone=g_sc_c('site','timezone');
		if(!empty($timezone)){
		    
		    if(strpos($timezone, 'UTC')===0){
		        
		        
		        $timezone=str_replace(array('UTC+','UTC-'), array('Etc/GMT-','Etc/GMT+'), $timezone);
		    }
		    date_default_timezone_set($timezone);
		}
		
		if(!is_empty(g_sc_c('site','closelog'))){
			
			\think\Log::init(array('type'=>'test','level'=>array()));
		}
		if(!is_empty(g_sc_c('site','dblong'))){
			
			$dbParams=config('database.params');
			$dbParams[\PDO::ATTR_PERSISTENT]=true;
			config('database.params',$dbParams);
		}
		
		
		if(is_empty(g_sc_c('download_img'))){
		    set_g_sc(['c','download_img'],$mconfig->get_img_config_from_caiji(g_sc_c('caiji')));
		}
		
		set_g_sc('clientinfo',clientinfo());
		if(!is_empty(g_sc('clientinfo'))){
		    set_g_sc('clientinfo',base64_encode(json_encode(g_sc('clientinfo'))));
		}
		
		
		$usertoken=session('usertoken');
		if(empty($usertoken)){
			
			$usertoken=rand(1, 9999999).'_'.date('Y-m-d');
			$usertoken=md5($usertoken);
			session('usertoken',$usertoken);
		}
		set_g_sc('usertoken',$usertoken);
		
		\util\Tools::close_session();
	}
}

?>