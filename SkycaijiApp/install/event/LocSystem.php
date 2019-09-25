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
	
	public function environment(){
		$serverDataList=array(
			'os'=>array('操作系统','不限制',php_uname('s').' '.php_uname('r'),true),
			'php'=>array('PHP版本','5.4',phpversion())
		);
		
		if(version_compare($serverDataList['php'][1],$serverDataList['php'][2])<=0){
			$serverDataList['php'][3]=true;
		}else{
			$serverDataList['php'][3]=false;
		}
			
		
		$phpModuleList=array(
			array('curl',extension_loaded('curl')),
			array('mb_string',extension_loaded('mbstring')),
			array('pdo_mysql',extension_loaded('pdo_mysql')),
			array('gd',extension_loaded('gd')),
		);
			
		
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
		
		return array('server'=>$serverDataList,'php'=>$phpModuleList,'path'=>$pathFileList);
	}
}
?>