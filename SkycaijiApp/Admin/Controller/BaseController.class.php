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

namespace Admin\Controller; use Think\Controller; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class BaseController extends Controller { public function success($message='',$jumpUrl='',$ajax=false){ parent::success($message,$jumpUrl,$ajax); exit(); } public function error_msg($message='',$jumpUrl='',$ajax=false) { if(!empty($_GET['callback'])){ $this->ajaxReturn(array('status'=>0,'info'=>$message,'url'=>$jumpUrl),'jsonp'); }else{ $this->error($message,$jumpUrl,$ajax); } exit(); } public function ajax_js($success=true,$message='',$js=''){ $this->ajaxReturn(array('status'=>$success?1:0,'info'=>$message,'js'=>$js),'json'); } protected static $echo_msg_head; public function echo_msg($str,$color='red',$echo=true,$end_str=''){ if(defined('CLOSE_ECHO_MSG')){ $echo=false; } if($echo){ if(!isset(self::$echo_msg_head)){ self::$echo_msg_head=true; header('X-Accel-Buffering:no'); @ini_set('output_buffering', 'Off'); ob_end_clean(); echo '<style type="text/css">body{padding:0 5px;font-size:14px;color:#000;}p{padding:0;margin:0;}a{color:#aaa;}</style>'; echo str_pad(' ', 1050); } echo '<p style="color:'.$color.';">'.$str.'</p>'.$end_str; ob_flush(); flush(); } } public function indexAction(){ if(strtolower(CONTROLLER_NAME)=='base'){ $this->redirect('Admin/Backstage/index'); } } }