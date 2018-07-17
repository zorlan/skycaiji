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

if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } function cp_sign($sign,$num=''){ $sign=strtolower($sign); if($sign=='match'){ return L('sign_match',array('num'=>$num)); }else{ } } function check_verify($verifycode){ if(empty($verifycode)){ return array('msg'=>L('verifycode_error'),'name'=>'verifycode'); } $verify = new \Think\Verify(array('reset'=>false)); if(!$verify->check($verifycode)){ return array('msg'=>L('verifycode_error'),'name'=>'verifycode'); } return array('success'=>true); } function a_c($module,$layer='Event'){ if(!empty($module)){ return A('Admin/C'.strtolower($module),$layer); } } function program_filemd5_list($path,&$md5FileList){ static $passPaths=array(); if(empty($passPaths)){ $passPaths['data']=realpath(C('ROOTPATH').'/data'); $passPaths['runtime']=realpath(C('ROOTPATH').'/'.APP_PATH.'Runtime'); $passPaths=array_filter($passPaths); } $fileList=scandir($path); foreach( $fileList as $file ){ $isPass=false; $fileName=realpath($path.'/'.$file); foreach ($passPaths as $passPath){ if($fileName==$passPath||stripos($fileName,$passPath)>0){ $isPass=true; } } if($isPass){ continue; } if(is_dir( $fileName ) && '.' != $file && '..' != $file ){ program_filemd5_list( $fileName,$md5FileList ); }elseif(is_file($fileName)){ $root=realpath(C('ROOTPATH')); $curFile=str_replace('\\', '/',str_replace($root, '', $fileName)); $md5FileList[]=array('md5'=>md5_file($fileName),'file'=>$curFile); } } } function check_usertoken(){ if($GLOBALS['usertoken']!=I('_usertoken_')){ return false; }else{ return true; } } function html_usertoken(){ return '<input type="hidden" name="_usertoken_" value="'.$GLOBALS['usertoken'].'" />'; }