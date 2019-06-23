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

namespace skycaiji\index\controller;
use skycaiji\common\controller\BaseController;
use skycaiji\admin\model\Config;
use think\Request;
class Index extends BaseController {
    public function indexAction(){
    	Request::instance()->root(config('root_url').'/index.php?s=');

		if(!file_exists(config('app_path').'/install/data/install.lock')){
    		
    		$this->error('请先安装','install/index/index');
    	}else{
    		$mconfig=new Config();
    		$siteConf=$mconfig->getConfig('site','data');
    		if(empty($siteConf['hidehome'])){
    			
    			return $this->fetch();
    		}else{
    			
    			$this->redirect('admin/index/index');
    		}
    	}
    }
}
