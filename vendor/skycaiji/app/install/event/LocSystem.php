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

namespace skycaiji\install\event;
use skycaiji\common\controller\BaseController;

class LocSystem extends BaseController{
	/*环境检测*/
    public function environmentServer(){
        $serverDataList=array(
            'os'=>array('操作系统','不限制',php_uname('s').' '.php_uname('r'),true),
            'php'=>array('PHP版本','5.4',phpversion())
        );
        
        if(version_compare($serverDataList['php'][1],$serverDataList['php'][2])<=0){
            $serverDataList['php'][3]=true;
        }else{
            $serverDataList['php'][3]=false;
        }
        return $serverDataList;
    }
    public function environmentPhp(){
        $loadedGd=array('name'=>'gd','loaded'=>extension_loaded('gd'),'lack'=>null);
        if($loadedGd['loaded']){
            
            $gdFuncs=get_extension_funcs('gd');
            $gdFuncs=is_array($gdFuncs)?$gdFuncs:array();
            if(!in_array('imagettftext', $gdFuncs)){
                $loadedGd['lack']='freetype';
            }
        }
        
        $phpModuleList=array(
            'curl'=>array('name'=>'curl','loaded'=>extension_loaded('curl')),
            'mb_string'=>array('name'=>'mb_string','loaded'=>extension_loaded('mbstring')),
            'pdo_mysql'=>array('name'=>'pdo_mysql','loaded'=>extension_loaded('pdo_mysql')),
            'gd'=>$loadedGd
        );
        return $phpModuleList;
    }
    public function environmentPath(){
        
        $pathFiles=array('./data','./data/config.php','./data/images','./data/app','./data/program/upgrade','./data/program/backup','./app','./plugin','./runtime');
        $pathFileList=array();
        foreach ($pathFiles as $pathFile){
            $filename=config('root_path').'/'.$pathFile;
            if(!file_exists($filename)){
                
                if(preg_match('/\w+\.\w+/', $pathFile)){
                    
                    write_dir_file($filename, null);
                }else{
                    
                    mkdir($filename,0777,true);
                }
            }
            $pathFileList[]=array(
                $pathFile,
                is_writeable($filename),
                is_readable($filename)
            );
        }
        return $pathFileList;
    }
	public function environment(){
		return array('server'=>$this->environmentServer(),'php'=>$this->environmentPhp(),'path'=>$this->environmentPath());
	}
	
	public function phpModuleIsAllowed($name){
	    $modules=$this->environmentPhp();
	    $module=$modules[$name];
	     
	    $isAllowed=false;
	    if(is_array($module)){
	        if(!empty($module['loaded'])){
	            
	            $isAllowed=empty($module['lack'])?true:false;
	        }
	    }
	    return $isAllowed;
	}
}
?>