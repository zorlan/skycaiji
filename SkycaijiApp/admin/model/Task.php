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
    public function loadConfig($taskData){
        $config=$taskData['config'];
		if(empty($config)){
			$config=array();
		}
		if(!is_array($config)){
			
			$config=unserialize($config);
		}
		if(!is_array($config)){
		    $config=array();
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
		
		static $imgParams=array('img_path','img_url','img_name','name_custom_path','name_custom_name');
		foreach ($imgParams as $imgParam){
		    
		    $GLOBALS['_sc']['c']['download_img'][$imgParam]=empty($config[$imgParam])?$global_config['download_img'][$imgParam]:$config[$imgParam];
		}
		if(empty($config['img_name'])){
		    
		    $GLOBALS['_sc']['c']['download_img']['name_custom_path']=$global_config['download_img']['name_custom_path'];
		    $GLOBALS['_sc']['c']['download_img']['name_custom_name']=$global_config['download_img']['name_custom_name'];
		}
	}
}

?>