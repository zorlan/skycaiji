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
			$global_config=$GLOBALS['_sc']['c'];
		}

		if(!empty($global_config['download_img']['download_img'])){
			
			if($config['download_img']=='n'){
				
				$GLOBALS['_sc']['c']['download_img']['download_img']=0;
			}else{
				$GLOBALS['_sc']['c']['download_img']['download_img']=1;
			}
		}else{
			$GLOBALS['_sc']['c']['download_img']['download_img']=0;
		}
		
		if(!empty($global_config['proxy']['open'])){
			
			if($config['proxy']=='n'){
				
				$GLOBALS['_sc']['c']['proxy']['open']=0;
			}else{
				$GLOBALS['_sc']['c']['proxy']['open']=1;
			}
		}else{
			$GLOBALS['_sc']['c']['proxy']['open']=0;
		}
		
		$GLOBALS['_sc']['c']['download_img']['img_path']=empty($config['img_path'])?$global_config['download_img']['img_path']:$config['img_path'];
		$GLOBALS['_sc']['c']['download_img']['img_url']=empty($config['img_url'])?$global_config['download_img']['img_url']:$config['img_url'];
	}
}

?>