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
class Task extends \skycaiji\common\model\BaseModel{
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
		    $global_config=g_sc('c');
		}
		
		set_g_sc(['c','caiji','interval'],empty($config['interval'])?g_sc('c_caiji_interval'):$config['interval']);
		
		set_g_sc(['c','caiji','interval_html'],empty($config['interval_html'])?$global_config['caiji']['interval_html']:$config['interval_html']);
		
		
		if(empty($config['real_time'])){
		    
		    set_g_sc(['c','caiji','real_time'],$global_config['caiji']['real_time']);
		}else{
		    set_g_sc(['c','caiji','real_time'],$config['real_time']=='n'?0:1);
		}
		
		
		if(empty($config['download_img'])){
		    
		    set_g_sc(['c','download_img','download_img'],$global_config['download_img']['download_img']);
		}else{
		    set_g_sc(['c','download_img','download_img'],$config['download_img']=='n'?0:1);
		}
		
		
		if(empty($config['img_func'])){
		    
		    set_g_sc(['c','download_img','img_func'],$global_config['download_img']['img_func']);
		}else{
		    
		    set_g_sc(['c','download_img','img_func'],$config['img_func']=='n'?'':$config['img_func']);
		}
		
		
		if(empty($config['proxy'])){
		    
		    set_g_sc(['c','proxy','open'],$global_config['proxy']['open']);
		}else{
		    set_g_sc(['c','proxy','open'],$config['proxy']=='n'?0:1);
		}
		
		
		static $imgParams=array('img_path','img_url','img_name','name_custom_path','name_custom_name','interval_img','img_func_param');
		foreach ($imgParams as $imgParam){
		    
		    set_g_sc(['c','download_img',$imgParam],empty($config[$imgParam])?$global_config['download_img'][$imgParam]:$config[$imgParam]);
		}
		if(empty($config['img_name'])){
		    
		    set_g_sc(['c','download_img','name_custom_path'],$global_config['download_img']['name_custom_path']);
		    set_g_sc(['c','download_img','name_custom_name'],$global_config['download_img']['name_custom_name']);
		}
	}
	
	public function backstage_task($taskId){
	    set_g_sc('backstage_task_runtime',time());
	    
	    if($this->where('id',$taskId)->count()>0){
	        
	        $mcache=\skycaiji\admin\model\CacheModel::getInstance('backstage_task');
	        $mcache->db()->strict(false)->insert(array(
	            'cname'=>$taskId,
	            'dateline'=>g_sc('backstage_task_runtime'),
	            'ctype'=>0,
	            'data'=>''
	        ),true);
	        
	        if(is_null(g_sc('backstage_task_ids'))){
	            set_g_sc('backstage_task_ids',array());
	        }
	        set_g_sc(['backstage_task_ids',$taskId],$taskId);
	        
	        static $registered=false;
	        if(!$registered){
	            $registered=true;
	            register_shutdown_function(function(){
	                
	                if(!is_empty(g_sc('backstage_task_ids'))&&is_array(g_sc('backstage_task_ids'))){
	                    $mcache=\skycaiji\admin\model\CacheModel::getInstance('backstage_task');
	                    $mcache->db()->strict(false)->where('cname','in',g_sc('backstage_task_ids'))->update(array('ctype'=>1,'data'=>time()));
	                }
	            });
	        }
	    }
	}
}

?>