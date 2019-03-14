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

/*采集规则标签*/
function cp_sign($sign,$num=''){
	$sign=strtolower($sign);
	if($sign=='match'){
		return lang('sign_match',array('num'=>$num));
	}else{
		
	}
}
/*密码验证*/
function check_verify($verifycode){
	if(empty($verifycode)){
		return array('msg'=>lang('verifycode_error'),'name'=>'verifycode');
	}

	$verify = new \think\captcha\Captcha(array('reset'=>false));
	if(!$verify->check($verifycode)){
		return array('msg'=>lang('verifycode_error'),'name'=>'verifycode');
	}
	return array('success'=>true);
}

/*获取项目的文件md5列表*/
function program_filemd5_list($path,&$md5FileList){
	static $passPaths=array();
	if(empty($passPaths)){
		
		$passPaths['data']=realpath(config('root_path').'/data');
		$passPaths['runtime']=realpath(config('root_path').'/runtime');
		$passPaths=array_filter($passPaths);
	}
	$fileList=scandir($path);
	foreach( $fileList as $file ){
		$isPass=false;
		$fileName=realpath($path.'/'.$file);
		foreach ($passPaths as $passPath){
			
			if($fileName==$passPath||stripos($fileName,$passPath)>0){
				$isPass=true;
			}
		}
		if($isPass){
			continue;
		}

		if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
			program_filemd5_list( $fileName,$md5FileList );
		}elseif(is_file($fileName)){
			$root=realpath(config('root_path'));
			$curFile=str_replace('\\', '/',str_replace($root, '', $fileName));
			
			$md5FileList[]=array('md5'=>md5_file($fileName),'file'=>$curFile);
		}
	}
}
/*验证用户token*/
function check_usertoken(){
	if($GLOBALS['usertoken']!=input('_usertoken_')){
		return false;
	}else{
		return true;
	}
}
/*输出用户token*/
function html_usertoken(){
	
	return '<input type="hidden" name="_usertoken_" value="'.$GLOBALS['usertoken'].'" />';
}

/*判断正在执行采集任务*/
function is_collecting(){
	if(defined('IS_COLLECTING')){
		return true;
	}else{
		return false;
	}
}
/*移除自动采集》正在采集状态*/
function remove_auto_collecting(){
	\skycaiji\admin\model\CacheModel::getInstance()->db()->where('cname','auto_collecting')->delete();
}

/*cli命令行*/
function cli_command_exec($paramStr){
	
	if(config('cli_cache_config')){
		$cacheConfig=\skycaiji\admin\model\CacheModel::getInstance()->getCache('cli_cache_config','data');
		$cliConfig=array();
		foreach (config('cli_cache_config') as $key){
			$cliConfig[$key]=config($key);
		}
		if(serialize($cacheConfig)!=serialize($cliConfig)){
			
			\skycaiji\admin\model\CacheModel::getInstance()->setCache('cli_cache_config',$cliConfig);
		}
	}
	
	static $php_ext_path=null;
	
	if(!isset($php_ext_path)){
		$ini_all=ini_get_all();
		$php_ext_path=$ini_all['extension_dir']['local_value'];
	}
	
	if($php_ext_path){
		$phpPath=preg_replace('/[\/\\\]ext[\/\\\]*$/i', '', $php_ext_path);
		if(IS_WIN){
			
			$phpPath.=DIRECTORY_SEPARATOR.'php.exe';
		}
	}else{
		$phpPath='php';
	}
	 
	$commandStr=$phpPath;
	if(IS_WIN){
		$commandStr='"'.$commandStr.'"';
	}
	
	$cliUser=strtolower($GLOBALS['user']['username']);
	$cliUser=$cliUser.'_'.md5($cliUser.$GLOBALS['user']['password']);
	
	$paramStr.=' --cli_user '.base64_encode($cliUser);

	$commandStr.=' '.config('root_path').DIRECTORY_SEPARATOR.'caiji '.$paramStr;

	$descriptorspec = array(
			0 => array('pipe', 'r'),  
			1 => array('pipe', 'w'),  
			2 => array('pipe', 'w')
	);
	$pipes=array();
	$handle=proc_open($commandStr,$descriptorspec,$pipes);
	$hdStatus=proc_get_status($handle);
	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	
    
    exit();
}