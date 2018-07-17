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

namespace Install\Controller; use Think\Controller; use Install\Event\UpgradeDbEvent; use Think\Storage; use Admin\Model\ConfigModel; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class UpgradeController extends Controller { public function success($message='',$jumpUrl='',$ajax=false){ parent::success($message,$jumpUrl,$ajax); exit(); } public function __construct(){ parent::__construct(); session_start(); $mconfig=new ConfigModel(); $dbVersion=$mconfig->getVersion(); if(version_compare($dbVersion,SKYCAIJI_VERSION,'>=')){ $this->success('已完成升级',U('Admin/Index/index')); } } public function dbAction(){ $updb=new UpgradeDbEvent(); $result=$updb->upgrade(); if($result['success']){ $this->success($result['msg'],U('Admin/Index/index')); }else{ $this->error($result['msg'],U('Admin/Index/index')); } } public function adminAction(){ $updb=new UpgradeDbEvent(); $result=$updb->upgrade(); if($result['success']){ $this->success(); }else{ $this->error(); } } }