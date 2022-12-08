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
		
		$original_config=g_sc('c_original');
		
		
		set_g_sc(['c','caiji','interval'],empty($config['interval'])?$original_config['caiji']['interval']:$config['interval']);
		
		set_g_sc(['c','caiji','interval_html'],empty($config['interval_html'])?$original_config['caiji']['interval_html']:$config['interval_html']);
		
		
		if(empty($config['same_url'])){
		    
		    set_g_sc(['c','caiji','same_url'],$original_config['caiji']['same_url']);
		}else{
		    set_g_sc(['c','caiji','same_url'],$config['same_url']=='n'?0:1);
		}
		
		if(empty($config['same_title'])){
		    
		    set_g_sc(['c','caiji','same_title'],$original_config['caiji']['same_title']);
		}else{
		    set_g_sc(['c','caiji','same_title'],$config['same_title']=='n'?0:1);
		}
		
		if(empty($config['real_time'])){
		    
		    set_g_sc(['c','caiji','real_time'],$original_config['caiji']['real_time']);
		}else{
		    set_g_sc(['c','caiji','real_time'],$config['real_time']=='n'?0:1);
		}
		
		
		if(empty($config['download_img'])){
		    
		    set_g_sc(['c','download_img','download_img'],$original_config['download_img']['download_img']);
		}else{
		    set_g_sc(['c','download_img','download_img'],$config['download_img']=='n'?0:1);
		}
		
		
		if(empty($config['img_func'])){
		    
		    set_g_sc(['c','download_img','img_func'],$original_config['download_img']['img_func']);
		}else{
		    
		    set_g_sc(['c','download_img','img_func'],$config['img_func']=='n'?'':$config['img_func']);
		}
		
		
		if(empty($config['proxy'])){
		    
		    set_g_sc(['c','proxy','open'],$original_config['proxy']['open']);
		}else{
		    set_g_sc(['c','proxy','open'],$config['proxy']=='n'?0:1);
		}
		
		if(!is_numeric($config['proxy_group_id'])){
		    
		    set_g_sc(['c','proxy','group_id'],$original_config['proxy']['group_id']);
		}else{
		    
		    set_g_sc(['c','proxy','group_id'],$config['proxy_group_id']);
		}
		
		static $imgParams=array('img_path','img_url','img_name','name_custom_path','name_custom_name','interval_img','img_func_param');
		foreach ($imgParams as $imgParam){
		    
		    set_g_sc(['c','download_img',$imgParam],empty($config[$imgParam])?$original_config['download_img'][$imgParam]:$config[$imgParam]);
		}
		if(empty($config['img_name'])){
		    
		    set_g_sc(['c','download_img','name_custom_path'],$original_config['download_img']['name_custom_path']);
		    set_g_sc(['c','download_img','name_custom_name'],$original_config['download_img']['name_custom_name']);
		}
		
		
		$htmlMillisecond=g_sc_c('caiji','interval_html');
		if(empty($htmlMillisecond)&&$original_config['caiji']['html_interval']>0){
		    
		    $htmlMillisecond=$original_config['caiji']['html_interval']*1000;
		    set_g_sc(['c','caiji','interval_html'],$htmlMillisecond);
		}
		
		$imgMillisecond=g_sc_c('download_img','interval_img');
		if(empty($imgMillisecond)&&$original_config['download_img']['img_interval']>0){
		    
		    $imgMillisecond=$original_config['download_img']['img_interval']*1000;
		    set_g_sc(['c','download_img','interval_img'],$imgMillisecond);
		}
	}
	
	public function set_backstage($taskId){
	    if($taskId>0){
	        $curTime=time();
	        \skycaiji\admin\model\CacheModel::getInstance('backstage_task')->db()->strict(false)->insert(array(
	            'cname'=>$taskId,
	            'dateline'=>$curTime,
	            'ctype'=>0,
	            'data'=>$curTime
	        ),true);
	        set_g_sc(['backstage_task_ids',$taskId],$taskId);
	    }
	}
	
	public function set_backstage_end($taskId){
	    \skycaiji\admin\model\CacheModel::getInstance('backstage_task')->db()->strict(false)->where('cname',$taskId)->update(array('ctype'=>1,'data'=>time()));
	}
	
	public function auto_is_timer($auto){
	    $auto=intval($auto);
	    if($auto==2){
	        return true;
	    }else{
	        return false;
	    }
	}
	
	private static function collecting_file($taskId){
	    return config('runtime_path').'/collecting/task/'.$taskId;
	}
	
	public static function collecting_lock($taskId){
        
        $collFile=self::collecting_file($taskId);
        write_dir_file($collFile, '1');
        $fp=fopen($collFile, 'w');
        set_g_sc(['collecting_task',$taskId], $fp);
        flock(g_sc('collecting_task',$taskId), LOCK_EX | LOCK_NB);
	}
	
	
	public static function collecting_remove($taskId){
	    $collFile=self::collecting_file($taskId);
	    if(file_exists($collFile)){
	        $fp=g_sc('collecting_task',$taskId);
	        if(is_resource($fp)){
	            flock($fp,LOCK_UN);
	            fclose($fp);
	        }
	        unlink($collFile);
	    }
	}
	
	public static function collecting_remove_all(){
	    $list=g_sc('collecting_task');
	    if(!empty($list)){
	        foreach ($list as $taskId=>$fp){
	            self::collecting_remove($taskId);
	        }
	    }
	}
	
	
	public static function collecting_status($taskId){
        $collFile=self::collecting_file($taskId);
        $status='none';
        if(file_exists($collFile)){
            $fp=fopen($collFile, 'w');
            if(flock($fp, LOCK_EX | LOCK_NB)){
                
                $status='unlock';
                flock($fp,LOCK_UN);
                fclose($fp);
            }else{
                
                $status='lock';
            }
        }
        
        return $status;
	}
}

?>