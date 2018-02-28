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

if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } function cp_sign($sign,$num=''){ $sign=strtolower($sign); if($sign=='match'){ return L('sign_match',array('num'=>$num)); }else{ } } function check_verify($verifycode){ if(empty($verifycode)){ return array('msg'=>L('verifycode_error'),'name'=>'verifycode'); } $verify = new \Think\Verify(array('reset'=>false)); if(!$verify->check($verifycode)){ return array('msg'=>L('verifycode_error'),'name'=>'verifycode'); } return array('success'=>true); } function a_c($module,$layer='Event'){ if(!empty($module)){ return A('Admin/C'.strtolower($module),$layer); } }