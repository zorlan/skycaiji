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

namespace skycaiji\admin\model;
class Task extends BaseModel{
	public function loadConfig($config){
		if(empty($config)){
			$config=array();
		}
		if(!is_array($config)){
			
			$config=unserialize($config);
		}
		
		static $global_config=null;
		if(!isset($global_config)){
			$global_config=$GLOBALS['config'];
		}

		if(!empty($global_config['caiji']['download_img'])){
			
			if($config['download_img']=='n'){
				
				$GLOBALS['config']['caiji']['download_img']=0;
			}else{
				$GLOBALS['config']['caiji']['download_img']=1;
			}
		}else{
			$GLOBALS['config']['caiji']['download_img']=0;
		}
		
		if(!empty($global_config['proxy']['open'])){
			
			if($config['proxy']=='n'){
				
				$GLOBALS['config']['proxy']['open']=0;
			}else{
				$GLOBALS['config']['proxy']['open']=1;
			}
		}else{
			$GLOBALS['config']['proxy']['open']=0;
		}
		
		$GLOBALS['config']['caiji']['img_path']=empty($config['img_path'])?$global_config['caiji']['img_path']:$config['img_path'];
		$GLOBALS['config']['caiji']['img_url']=empty($config['img_url'])?$global_config['caiji']['img_url']:$config['img_url'];
	}
}

?>