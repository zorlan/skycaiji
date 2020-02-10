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

namespace skycaiji\install\controller;
use skycaiji\common\controller\BaseController;
use skycaiji\install\event\UpgradeDb;
class Upgrade extends BaseController{
	public function __construct(){
		parent::__construct();
		if(session_status()!==2){
			session_start();
		}

		$mconfig=model('admin/Config');
		$dbVersion=$mconfig->getVersion();
		if(version_compare($dbVersion,SKYCAIJI_VERSION,'>=')){
			
			$this->success('已完成升级',url('Admin/Index/index'));
		}
	}
    /*升级数据库*/
    public function dbAction(){
    	$updb=new UpgradeDb();
    	$result=$updb->upgrade();
    	if($result['success']){
    		$this->success($result['msg'],url('Admin/Index/index'));
    	}else{
    		$this->error($result['msg'],url('Admin/Index/index'));
    	}
    }
    /*延续后台升级操作»升级数据库*/
    public function adminAction(){
    	$updb=new UpgradeDb();
    	$result=$updb->upgrade();
    	if($result['success']){
    		$this->success();
    	}else{
    		$this->error();
    	}
    }
}