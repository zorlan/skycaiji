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
use think\Request;
class Index extends BaseController {
    public function indexAction(){
        \util\Tools::set_url_compatible();

    	if(!file_exists(config('root_path').'/data/install.lock')){
    		
    		$this->error('请先安装','install/index/index');
    	}else{
    	    $mconfig=new \skycaiji\common\model\Config();
    	    $siteConf=$mconfig->getConfig('site','data');
    	    init_array($siteConf);
    		if(empty($siteConf['hidehome'])){
    			
    			return $this->fetch();
    		}else{
    			
    			$this->redirect('admin/index/index');
    		}
    	}
    }
}
