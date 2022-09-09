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

class Collector extends \skycaiji\common\model\BaseModel{
	
	public function add_new($data){
	    $data['addtime']=time();
	    $data['uptime']=time();
		$this->isUpdate(false)->allowField(true)->save($data);
		return $this->id;
	}
	
	public function edit_by_id($id,$data){
		unset($data['addtime']);
		$data['uptime']=time();
		
		$this->strict(false)->where(array('id'=>$id))->update($data);
	}
	
	
	public function compatible_config($config){
	    if(!is_array($config)){
	        $config=array();
	    }
	    
	    if(!isset($config['area'])){
	        
	        if(!empty($config['area_start'])||!empty($config['area_end'])) {
	            
	            $config['area']=$config['area_start'] . (!empty($config['area_end']) ? '(?<nr>[\s\S]+?)' : '(?<nr>[\s\S]+)') . $config['area_end'];
	        }
	    }
	    
	    if(!isset($config['url_web'])){
	        
	        if(!empty($config['url_post'])&&isset($config['url_posts'])){
	            
	            \util\Funcs::filter_key_val_list($config['url_posts']['names'], $config['url_posts']['vals']);
	            
	            $config['url_web']=array('open'=>1,'form_method'=>'post','form_names'=>$config['url_posts']['names'],'form_vals'=>$config['url_posts']['vals']);
	            
	            if(is_array($config['level_urls'])){
	                foreach ($config['level_urls'] as $k=>$v){
	                    $v['url_web']=$config['url_web'];
	                    $config['level_urls'][$k]=$v;
	                }
	            }
	        }
	    }
	    
	    if(isset($config['paging'])){
	        
	        if(is_array($config['paging'])){
	            
	            $config['pagination']=$config['paging'];
	            if(is_array($config['paging_fields'])){
	                
	                $config['pagination']['fields']=$config['paging_fields'];
	            }
	        }
	    }
	    
	    
	    if(is_array($config['field_process'])){
	        foreach($config['field_process'] as $k=>$v){
	            $config['field_process'][$k]=$this->_compatible_process_api($v);
	        }
	    }
	    if(is_array($config['common_process'])){
	        $config['common_process']=$this->_compatible_process_api($config['common_process']);
	    }
	    return $config;
	}
	
	private function _compatible_process_api($processes){
	    if(is_array($processes)){
	        foreach ($processes as $pk=>$pv){
	            if(is_array($pv)&&$pv['module']=='api'){
	                if(is_array($pv['api_headers'])&&!isset($pv['api_headers']['addon'])){
	                    
	                    $pv['api_headers']['addon']=$pv['api_headers']['val'];
	                    foreach ($pv['api_headers']['val'] as $vk=>$vv){
	                        if($vv){
	                            $pv['api_headers']['val'][$vk]='custom';
	                        }
	                    }
	                    $processes[$pk]=$pv;
	                }
	            }
	        }
	    }
	    return $processes;
	}
	
	public static function url_backstage_run(){
	    return input('backstage_run')?true:false;
	}
	
	public static function url_collector_process($returnArr=false){
	    $key=input('collector_process');
	    $key=$key?:'';
	    if($returnArr){
	        
	        if($key){
	            list($ckey,$pkey)=explode('-', $key);
	            return array('key'=>$key,'ckey'=>$ckey,'pkey'=>$pkey);
	        }else{
	            return array();
	        }
	    }else{
	        
	        return $key;
	    }
	}
	
	public static function echo_msg_end_js($isTimeout=false){
	    $vars='"'.self::url_collector_process().'"';
	    if($isTimeout){
	        $vars.=',1';
	    }
	    return '<script type="text/javascript" data-echo-msg-is-end="1">window.parent.window.collectorEchoMsg.end('.$vars.');</script>';
	}
	
	public static function echo_msg_filename($collectorProcess=null){
	    if(!isset($collectorProcess)){
	        
	        $collectorProcess=self::url_collector_process();
	    }
	    if(empty($collectorProcess)){
	        return false;
	    }
	    $key=md5($collectorProcess);
	    $filename=config('runtime_path').'/echo_msg/'.substr($key,0,2).'/'.substr($key,2);
	    return $filename;
	}
	
	
	private static function collecting_file($name){
	    $file=config('runtime_path').'/collecting/collector';
	    if($name){
	        $file.='/'.$name;
	    }
	    return $file;
	}
	
	public static function collecting_lock($collectorKey,$processKey){
	    if(!empty($collectorKey)&&!empty($processKey)){
	        $collFile=self::collecting_file($collectorKey.'/'.$processKey);
	        
	        if(file_exists($collFile)){
	            
	            $fp=fopen($collFile, 'w');
	            set_g_sc(['collecting_collector',$collectorKey,$processKey], $fp);
	            flock(g_sc('collecting_collector',$collectorKey,$processKey), LOCK_EX | LOCK_NB);
	        }
	    }
	}
	
	public static function collecting_remove($collectorKey,$processKey=null){
	    if(!empty($collectorKey)){
	        if(!isset($processKey)){
	            $dir=self::collecting_file($collectorKey);
	            \util\Funcs::clear_dir($dir);
	            if(is_dir($dir)){
	                @rmdir($dir);
	            }
	            CacheModel::getInstance('collecting')->deleteCache($collectorKey);
	        }else{
	            $collFile=self::collecting_file($collectorKey.'/'.$processKey);
	            if(file_exists($collFile)){
	                $fp=g_sc('collecting_collector',$collectorKey,$processKey);
	                if(is_resource($fp)){
	                    flock($fp,LOCK_UN);
	                    fclose($fp);
	                }
	                unlink($collFile);
	            }
	        }
	    }
	}
	
	public static function collecting_data($collectorKey){
	    $processes=array();
	    if(!empty($collectorKey)){
	        $processes=CacheModel::getInstance('collecting')->getCache($collectorKey,'data');
	    }
	    if(!is_array($processes)){
	        $processes=array();
	    }
	    return $processes;
	}
	
	
	
	public static function collecting_process_status($collectorKey,$processKey){
	    $status='none';
	    if(!empty($collectorKey)&&!empty($processKey)){
	        $collFile=self::collecting_file($collectorKey.'/'.$processKey);
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
	        
	    }
	    return $status;
	}
	
	
	public static function collecting_status_list($collectorKey){
	    $lockList=array('main'=>false,'processes'=>array());
	    if($collectorKey){
	        $mainLock=self::collecting_process_status($collectorKey, 'main');
	        $mainLock=$mainLock=='lock'?true:false;
	        
	        $processes=self::collecting_data($collectorKey);
	        if(!empty($processes)&&is_array($processes)){
	            foreach ($processes as $pkey=>$ptids){
	                $processLock=self::collecting_process_status($collectorKey, $pkey);
	                if($processLock=='lock'){
	                    $mainLock=true;
	                    $processLock=true;
	                }else{
	                    $processLock=false;
	                }
	                $lockList['processes'][$pkey]=$processLock;
	            }
	        }
	        $lockList['main']=$mainLock;
	    }
	    return $lockList;
	}
	/*采集密钥*/
	public static function collect_key($isProcess=false){
	   $key=\util\Funcs::uniqid($isProcess?'collect_process':'auto_collect');
	   \util\Param::set_temp_cahce_key($key);
	   return $key;
	}
	/*触发运行自动采集*/
	public static function collect_run_auto(){
	    try{
	        
	        get_html(url('admin/index/auto_collect?backstage_run=1&key='.self::collect_key(),null,false,true),null,array('timeout'=>3));
	    }catch(\Exception $ex){}
	}
	
	/*采集生成进程*/
	public static function collect_create_processes($taskIds){
	    $keys=array();
	    if(!empty($taskIds)){
	        
	        $collectorKey=\util\Funcs::uniqid('collector');
	        
	        self::collecting_remove($collectorKey);
	        $processNum=g_sc_c('caiji','process_num');
	        $processNum=intval($processNum);
	        if($processNum<=1){
	            $processNum=1;
	        }
	        $taskIds=array_values($taskIds);
	        $taskNum=count($taskIds);
	        if($taskNum<=$processNum){
	            
	            $processNum=$taskNum;
	        }
	        $processes=array();
	        
	        $pkey=0;
	        foreach ($taskIds as $taskId){
	            $pkey++;
	            if($pkey>$processNum){
	                
	                $pkey=1;
	            }
	            if(!is_array($processes[$pkey])){
	                $processes[$pkey]=array();
	            }
	            $processes[$pkey][$taskId]=$taskId;
	        }
	        foreach ($processes as $k=>$v){
	            $v=array_values($v);
	            $processes[$k]=$v;
	        }
	        
	        CacheModel::getInstance('collecting')->setCache($collectorKey,$processes);
	        
	        $collFile=self::collecting_file($collectorKey.'/main');
	        write_dir_file($collFile,'1');
	        
	        foreach ($processes as $pkey=>$ptids){
	            $collFile=self::collecting_file($collectorKey.'/'.$pkey);
	            write_dir_file($collFile,'1');
	        }
	        $processes=is_array($processes)?array_keys($processes):array();
	        $keys=array(
	            'collector_key'=>$collectorKey,
	            'process_keys'=>$processes,
	        );
	    }
	    return $keys;
	}
	/*采集运行进程*/
	public static function collect_run_processes($collectorKey,$collectNum=null,$collectAuto=null,$backstageRun=false,$urlParams=null){
	    ignore_user_abort(true);
	    \util\Funcs::close_session();
	    if(empty($collectorKey)){
	        return;
	    }
	    $processes=self::collecting_data($collectorKey);
	    if(!empty($processes)&&is_array($processes)){
	        if(!is_array($urlParams)){
	            $urlParams=array();
	        }
	        self::collecting_lock($collectorKey, 'main');
	        $mh=curl_multi_init();
	        $chList=array();
	        foreach ($processes as $pkey=>$ptids){
	            $allParams=array(
	                'key'=>self::collect_key(true),
	                'collector_process'=>$collectorKey.'-'.$pkey,
	            );
	            if(isset($collectNum)){
	                
	                $allParams['collect_num']=intval($collectNum);
	            }
	            if(isset($collectAuto)){
	                
	                $allParams['collect_auto']=$collectAuto?1:0;
	            }
	            if($backstageRun){
	                
	                $allParams['backstage_run']=1;
	            }
	            if($urlParams){
	                $allParams=array_merge($urlParams,$allParams);
	            }
	            $allParams=http_build_query($allParams);
	            $url=url('admin/index/collect_process?'.$allParams,null,false,true);
	            $chList[$pkey]=\util\Curl::get($url,null,array('return_curl'=>1,'timeout'=>3));
	            curl_multi_add_handle($mh, $chList[$pkey]);
	        }
	        
	        $running=null;
	        do {
	            curl_multi_exec($mh,$running);
	        } while ($running > 0);
	        
	        
	        foreach ($chList as $ch){
	            curl_multi_remove_handle($mh,$ch);
	        }
	        curl_multi_close($mh);
	    }
	}
}

?>