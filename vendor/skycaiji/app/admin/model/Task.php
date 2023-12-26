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
		
		if(empty($config['same_content'])){
		    
		    set_g_sc(['c','caiji','same_content'],$original_config['caiji']['same_content']);
		}else{
		    set_g_sc(['c','caiji','same_content'],$config['same_content']=='n'?0:1);
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
		
		
		init_array($config['img_funcs']);
		$imgFuncs=array();
		if(empty($config['img_funcs_open'])){
		    
		    $imgFuncs=$original_config['download_img']['img_funcs'];
		    init_array($imgFuncs);
		    if(!empty($config['img_funcs'])){
		        $imgFuncs=array_merge($imgFuncs,$config['img_funcs']);
		    }
		}else{
		    
		    $imgFuncs=$config['img_funcs_open']=='n'?array():$config['img_funcs'];
		}
		set_g_sc(['c','download_img','img_funcs'],$imgFuncs);
		
		
		
		if(empty($config['download_file'])){
		    
		    set_g_sc(['c','download_file','download_file'],$original_config['download_file']['download_file']);
		}else{
		    set_g_sc(['c','download_file','download_file'],$config['download_file']=='n'?0:1);
		}
		
		
		init_array($config['file_funcs']);
		$fileFuncs=array();
		if(empty($config['file_funcs_open'])){
		    
		    $fileFuncs=$original_config['download_file']['file_funcs'];
		    init_array($fileFuncs);
		    if(!empty($config['file_funcs'])){
		        $fileFuncs=array_merge($fileFuncs,$config['file_funcs']);
		    }
		}else{
		    
		    $fileFuncs=$config['file_funcs_open']=='n'?array():$config['file_funcs'];
		}
		set_g_sc(['c','download_file','file_funcs'],$fileFuncs);
		
		
		
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
            $config=model('Config')->compatible_func_config($config,false,true);
            $config=model('Config')->compatible_func_config($config,true,true);
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
        
        $mconfig=model('Config');
        $emailConfig=$mconfig->getConfig('email','data');
        if(!empty($emailConfig)){
            init_array($emailConfig);
            $caijiConfig=$emailConfig['caiji'];
            init_array($caijiConfig);
            if(!empty($caijiConfig)&&$caijiConfig['open']){
                
                $toEmail=$caijiConfig['email']?:$emailConfig['email'];
                if(empty($caijiConfig['is_auto'])||!is_empty(input('collect_auto'))){
                    
                    $mcacheEmail=\skycaiji\admin\model\CacheModel::getInstance('email');
                    $mcollected=model('Collected');
                    $mtask=model('Task');
                    $timeNow=time();
                    $todayTime=strtotime(date('Y-m-d',$timeNow));
                    if($taskId>0&&!empty($caijiConfig['failed_num'])&&$caijiConfig['failed_num']>0){
                        
                        $failedInterval=intval($caijiConfig['failed_interval'])*60;
                        $taskKey='failed_task_'.$taskId;
                        $taskLastTime=$mcacheEmail->getCache($taskKey,'data');
                        $taskLastTime=intval($taskLastTime);
                        if($taskLastTime<=0){
                            
                            $taskLastTime=$todayTime;
                        }
                        if($taskLastTime>0&&(abs($timeNow-$taskLastTime)>$failedInterval)){
                            
                            
                            $taskFailedNum=$mcollected->where(array('task_id'=>$taskId,'addtime'=>array('GT',$taskLastTime),'error'=>array('<>','')))->count();
                            if($taskFailedNum>0&&$taskFailedNum>$caijiConfig['failed_num']){
                                
                                $mcacheEmail->setCache($taskKey,$timeNow);
                                $taskName=$mtask->where('id',$taskId)->value('name');
                                \util\Tools::send_mail(
                                    $emailConfig, $toEmail, $emailConfig['sender'],
                                    sprintf('任务%d在 %s 至 %s 失败了%d次',$taskId,date('m-d H:i:s',$taskLastTime),date('m-d H:i:s',$timeNow),$taskFailedNum),
                                    sprintf('<a href="%s" target="_blank">查看任务"%s"失败详细</a>',url('admin/collected/list?status=2&task_id='.$taskId,'',false,true),$taskName)
                                );
                            }
                        }
                    }
                    
                    $reportInterval=intval($caijiConfig['report_interval'])*60;
                    if($reportInterval>0){
                        
                        $reportKey='caiji_report_time';
                        $reportLastTime=$mcacheEmail->getCache($reportKey,'data');
                        $reportLastTime=intval($reportLastTime);
                        if($reportLastTime<=0){
                            
                            $reportLastTime=$todayTime;
                        }
                        if($reportLastTime>0&&(abs($timeNow-$reportLastTime)>$reportInterval)){
                            
                            $mcacheEmail->setCache($reportKey,$timeNow);
                            $report=array(
                                'today_success'=>0,'today_error'=>0,'today_tasks'=>array(),
                                'total_success'=>$mcollected->where("`target` <> ''")->count(),
                                'total_error'=>$mcollected->where("`error` <> ''")->count(),
                                'task_auto'=>$mtask->where('`auto`>0')->count(),
                                'task_other'=>$mtask->where('`auto`=0')->count(),
                                'caijitime'=>$mtask->where('`auto`>0')->max('caijitime'),
                                'autotime'=>CacheModel::getInstance()->getCache('collect_backstage_time','data'),
                            );
                            $todaySuccess=$mcollected->field('task_id,count(task_id)')->where(array('addtime'=>array('GT',$todayTime),'target'=>array('<>','')))->group('task_id')->column('count(task_id)','task_id');
                            $todayError=$mcollected->field('task_id,count(task_id)')->where(array('addtime'=>array('GT',$todayTime),'error'=>array('<>','')))->group('task_id')->column('count(task_id)','task_id');
                            
                            if($todaySuccess){
                                $report['today_success']=array_sum($todaySuccess);
                                foreach ($todaySuccess as $k=>$v){
                                    init_array($report['today_tasks'][$k]);
                                    $report['today_tasks'][$k]['success']=$v;
                                }
                            }
                            if($todayError){
                                $report['today_error']=array_sum($todayError);
                                foreach ($todayError as $k=>$v){
                                    init_array($report['today_tasks'][$k]);
                                    $report['today_tasks'][$k]['error']=$v;
                                }
                            }
                            if($report['today_tasks']){
                                
                                $taskNames=$mtask->where('id','in',array_keys($report['today_tasks']))->column('name','id');
                                if($taskNames){
                                    
                                    foreach ($taskNames as $k=>$v){
                                        $report['today_tasks'][$k]['name']=$v;
                                    }
                                }
                                unset($taskNames);
                            }
                            $report=view('task/caiji_report_email',array('report'=>$report))->getContent();
                            \util\Tools::send_mail(
                                $emailConfig, $toEmail, $emailConfig['sender'],
                                date('Y-m-d H:i:s',$timeNow).' 采集报表',
                                $report
                            );
                        }
                    }
                }
            }
        }
        
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