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

class User extends BaseModel{
	/*用户名是否正确*/
	public static function right_username($username,$name='username'){
		$return=array('name'=>$name);
		if(!preg_match('/^.{3,15}$/i', $username)){
			$return['msg']=lang('user_error_username');
		}else{
			$return['success']=true;
		}
		return $return;
	}
	/*邮箱是否正确*/
	public static function right_email($email,$name='email'){
		$return=array('name'=>$name,'field'=>'email');
		if(!preg_match('/^[^\s]+\@([\w\-]+\.){1,}\w+$/i', $email)){
			$return['msg']=lang('user_error_email');
		}else{
			$return['success']=true;
		}
		return $return;
	}
	/**
	 * 密码格式是否正确
	 * @param string $pwd
	 * @param string $name
	 * @return Ambigous <multitype:, multitype:string , multitype:boolean string >
	 */
	public static function right_pwd($pwd,$name='password'){
		$return=array('name'=>$name);
		if(!preg_match('/^[a-zA-Z0-9\!\@\#\$\%\^\&\*]{6,20}$/i', $pwd)){
			$return['msg']=lang('user_error_password');
		}else{
			$return['success']=true;
		}
		return $return;
	}
	/**
	 * 验证密码是否一致
	 * @param string $pwd
	 * @param string $repwd
	 * @param string $name
	 * @return multitype:string |multitype:boolean string
	 */
	public static function right_repwd($pwd,$repwd,$name='repassword'){
		if($pwd!=$repwd){
			return array('msg'=>lang('user_error_repassword'),'name'=>$name);
		}else{
			return array('success'=>true,'name'=>$name);
		}
	}
	/*用户组*/
	public static function right_groupid($groupid,$name='groupid'){
		$return=array('name'=>$name);
		$count=model('Usergroup')->where('id',$groupid)->count();
		if(empty($count)||$count<=0){
			$return['msg']=lang('user_error_groupid');
		}else{
			$return['success']=true;
		}
		return $return;
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
	/*获取随机盐*/
	public static function rand_salt($len=20){
		$salt="QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
		$salt=str_shuffle($salt);
		if($len>=strlen($salt)){
			return $salt;
		}else{
			return substr($salt,mt_rand(0,strlen($salt)-$len-1),$len);
		}
	}
	/*密码加密*/
	public static function pwd_encrypt($pwd,$salt=''){
		$pwd=sha1($pwd);
		if(!empty($salt)){
			$pwd.=$salt;
		}
		return md5($pwd);
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
		return array('success'=>true);
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
		return array('success'=>true);
	}
}

?>