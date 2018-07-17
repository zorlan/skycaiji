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

namespace Home\Controller; use Think\Controller; use Admin\Model\ConfigModel; class IndexController extends Controller { public function success($message='',$jumpUrl='',$ajax=false){ parent::success($message,$jumpUrl,$ajax); exit(); } public function indexAction(){ if(!file_exists(C('ROOTPATH').'/'.APP_PATH.'/Install/Data/install.lock')){ $this->error('请先安装',U('Install/Index/index')); }else{ $mconfig=new ConfigModel(); $siteConf=$mconfig->get('site','data'); if(empty($siteConf['hidehome'])){ $this->display(); }else{ $this->redirect('Admin/Index/index'); } } } }