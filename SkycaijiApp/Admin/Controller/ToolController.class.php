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

namespace Admin\Controller; use Think\Controller; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class ToolController extends BaseController { public function logsAction(){ $logPath=C('APPPATH').'/Runtime/Logs'; $logList=array(); $paths=scandir($logPath); foreach ($paths as $path){ if($path!='.'&&$path!='..'){ $pathFiles=scandir($logPath.'/'.$path); foreach ($pathFiles as $pathFile){ if($pathFile!='.'&&$pathFile!='..'){ $logList[$path][]=array( 'name'=>$pathFile, 'file'=>realpath($logPath.'/'.$path.'/'.$pathFile), ); } } } } $GLOBALS['content_header']='错误日志'; $GLOBALS['breadcrumb']=breadcrumb(array('错误日志')); $this->assign('logList',$logList); $this->display(); } public function logAction(){ $file=I('file'); $log=file_get_contents($file); exit($log); } }