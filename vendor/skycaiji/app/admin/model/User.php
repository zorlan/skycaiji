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

namespace skycaiji\admin\model;

class User extends \skycaiji\common\model\User{
	/*用户组*/
	public static function right_groupid($groupid,$name='groupid'){
	    $result=return_result('',false,array('name'=>$name));
		$count=model('admin/Usergroup')->where('id',$groupid)->count();
		if(empty($count)||$count<=0){
			$result['msg']=lang('user_error_groupid');
		}else{
			$result['success']=true;
		}
		return $result;
	}
	/**
	 * 邮件激活码是否正确
	 * @param unknown $username
	 * @param unknown $yanzhengma
	 * @param unknown $name
	 * @return multitype:string
	 */
	public static function right_yzm($username,$yanzhengma,$name='yzm'){
		$yzmSname='send_yzm.'.md5($username);
		$yzmSession=session($yzmSname);
		
		$check=array('name'=>$name);
		if(empty($yzmSession)){
			$check['msg']=lang('yzm_error_please_send');
		}elseif(empty($yanzhengma)){
			$check['msg']=lang('yzm_error_please_input');
		}elseif(abs(time()-$yzmSession['time'])>config('yzm_expire')){
			$check['msg']=lang('yzm_error_timeout');
		}elseif(strcasecmp($yanzhengma, $yzmSession['yzm'])!==0){
			$check['msg']=lang('yzm_error_yzm');
		}else{
			$check['success']=true;
		}
		return $check;
	}

	/*检测用户名正确且是否存在*/
	public function checkUsername($username){
		$check=self::right_username($username);
		if($check['success']){
			if($this->where('username',$username)->count()){
				$check['msg']=lang('user_error_has_username');
				$check['success']=false;
			}
		}
		return $check;
	}
	/*添加用户时验证*/
	public function add_check($data){
		$check=self::right_groupid($data['groupid']);
		if(!$check['success']){
			return $check;
		}
		$check=$this->checkUsername($data['username']);
		if(!$check['success']){
			return $check;
		}
		$check=self::right_pwd($data['password']);
		if(!$check['success']){
			return $check;
		}
		$check=self::right_repwd($data['password'],$data['repassword']);
		if(!$check['success']){
			return $check;
		}
		$check=self::right_email($data['email']);
		if(!$check['success']){
			return $check;
		}
		return return_result('',true);
	}
	/*修改用户时验证*/
	public function edit_check($data){
		if(!empty($data['groupid'])){
			
			$check=self::right_groupid($data['groupid']);
			if(!$check['success']){
				return $check;
			}
		}
		if(!empty($data['password'])){
			
			$check=self::right_pwd($data['password']);
			if(!$check['success']){
				return $check;
			}
			$check=self::right_repwd($data['password'],$data['repassword']);
			if(!$check['success']){
				return $check;
			}
		}
		$check=self::right_email($data['email']);
		if(!$check['success']){
			return $check;
		}
		return return_result('',true);
	}
	
	/*用户名小写化*/
	public static function lower_username($username){
	    
	    $name=$username?$username:'';
	    $name=trim($name);
	    $name=strtolower($name);
	    return $name;
	}
	
	/*生成用户唯一标识*/
	public function generate_key($userData){
	    $key='';
	    if(!empty($userData)){
	        $username=self::lower_username($userData['username']);
	        $key=md5($username.':'.$userData['password']);
	    }
	    return $key;
	}
	
	/*设置登录session*/
	public function setLoginSession($userData){
	    if(empty($userData)){
	        
	        session('user_login',null);
	        session('is_admin',null);
	    }else{
	        
	        session('user_login',array(
	            'uid'=>$userData['uid'],
	            'key'=>$this->generate_key($userData)
	        ));
	        
	        $mUg=model('admin/Usergroup');
	        $isAdmin=null;
	        $userGroup=$mUg->getById($userData['groupid']);
	        if($mUg->is_admin($userGroup)){
                $isAdmin=true;
            }
            session('is_admin',$isAdmin);
	    }
	}
}

?>