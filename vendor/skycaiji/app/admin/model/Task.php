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
    public function getById($id){
        $data=$this->where('id',$id)->find();
        if($data){
            $data=$data->toArray();
            if(!empty($data['config'])){
                $data['config']=unserialize($data['config']);
            }
            if(empty($data['config'])){
                $data['config']=array();
            }
        }else{
            $data=array();
        }
        return $data;
    }
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
		
		$config=$this->compatible_config($config);
		
		$original_config=g_sc('c_original');
		
		
		$this->set_c_num_names('caiji', array('interval'=>'num_interval','interval_html'=>'num_interval_html'), $config, $original_config);
		
		
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
		
		
		if(empty($config['download_file'])){
		    
		    set_g_sc(['c','download_file','download_file'],$original_config['download_file']['download_file']);
		}else{
		    set_g_sc(['c','download_file','download_file'],$config['download_file']=='n'?0:1);
		}
		
		if(empty($config['file_func'])){
		    
		    set_g_sc(['c','download_file','file_func'],$original_config['download_file']['file_func']);
		}else{
		    
		    set_g_sc(['c','download_file','file_func'],$config['file_func']=='n'?'':$config['file_func']);
		}
		
		
		if(empty($config['translate'])){
		    
		    set_g_sc(['c','translate','open'],$original_config['translate']['open']);
		}else{
		    set_g_sc(['c','translate','open'],$config['translate']=='n'?0:1);
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
		
		static $imgParams=array('img_path','img_url','img_name','name_custom_path','name_custom_name','img_func_param','img_wm_logo');
		foreach ($imgParams as $imgParam){
		    
		    set_g_sc(['c','download_img',$imgParam],empty($config[$imgParam])?$original_config['download_img'][$imgParam]:$config[$imgParam]);
		}
		if(empty($config['img_name'])){
		    
		    set_g_sc(['c','download_img','name_custom_path'],$original_config['download_img']['name_custom_path']);
		    set_g_sc(['c','download_img','name_custom_name'],$original_config['download_img']['name_custom_name']);
		}
		
		if(empty($config['img_watermark'])){
		    
		    set_g_sc(['c','download_img','img_watermark'],$original_config['download_img']['img_watermark']);
		}else{
		    set_g_sc(['c','download_img','img_watermark'],$config['img_watermark']=='n'?0:1);
		}
		$this->set_c_num_names('download_img', array('interval_img'=>'num_interval_img','img_wm_bottom'=>'img_wm_bottom','img_wm_right'=>'img_wm_right','img_wm_opacity'=>'img_wm_opacity'), $config, $original_config);
		
		
		static $fileParams=array('file_path','file_url','file_name','file_func_param');
		foreach ($fileParams as $fileParam){
		    
		    set_g_sc(['c','download_file',$fileParam],empty($config[$fileParam])?$original_config['download_file'][$fileParam]:$config[$fileParam]);
		}
		
		set_g_sc(['c','download_file','name_custom_path'],empty($config['file_custom_path'])?$original_config['download_file']['name_custom_path']:$config['file_custom_path']);
		set_g_sc(['c','download_file','name_custom_name'],empty($config['file_custom_name'])?$original_config['download_file']['name_custom_name']:$config['file_custom_name']);
		
		if(empty($config['file_name'])){
		    
		    set_g_sc(['c','download_file','name_custom_path'],$original_config['download_file']['name_custom_path']);
		    set_g_sc(['c','download_file','name_custom_name'],$original_config['download_file']['name_custom_name']);
		}
		$this->set_c_num_names('download_file', array('file_interval'=>'file_interval'), $config, $original_config);
    }
    
    private function set_c_num_names($cKey,$names,&$config,&$original_config){
        foreach ($names as $k=>$v){
            set_g_sc(['c',$cKey,$k],is_empty($config[$v],true)?$original_config[$cKey][$k]:$config[$v]);
        }
    }
    public function compatible_config($config){
        
        if(!empty($config)&&is_array($config)){
            
            $oldNumNames=array('interval'=>'num_interval','interval_html'=>'num_interval_html','interval_img'=>'num_interval_img');
            foreach($oldNumNames as $k=>$v){
                if(isset($config[$k])){
                    if(empty($config[$k])){
                        $config[$v]='';
                    }elseif($config[$k]==='-1'||$config[$k]===-1){
                        $config[$v]=0;
                    }else{
                        $config[$v]=$config[$k];
                    }
                }
            }
        }
        return $config;
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