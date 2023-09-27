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
	            $config['field_process'][$k]=$this->_compatible_processes($v);
	        }
	    }
	    if(is_array($config['common_process'])){
	        $config['common_process']=$this->_compatible_processes($config['common_process']);
	    }
	    
	    
	    
	    foreach (array('front_urls','level_urls','relation_urls') as $pagesKey){
	        if(is_array($config[$pagesKey])){
	            foreach ($config[$pagesKey] as $k=>$v){
	                if(is_array($v)&&is_array($v['content_signs'])){
	                    $v['content_signs']=$this->_compatible_content_signs($v['content_signs']);
	                    $config[$pagesKey][$k]=$v;
	                }
	            }
	        }
	    }
	    if(is_array($config['source_config'])&&is_array($config['source_config']['content_signs'])){
	        $config['source_config']['content_signs']=$this->_compatible_content_signs($config['source_config']['content_signs']);
	    }
	    if(is_array($config['content_signs'])){
	        $config['content_signs']=$this->_compatible_content_signs($config['content_signs']);
	    }
	    
	    return $config;
	}
	
	private function _compatible_content_signs($contentSigns){
	    if(is_array($contentSigns)){
	        foreach ($contentSigns as $k=>$v){
	            if(isset($v['func'])){
	                if($v['func']){
	                    $v['funcs']=array();
	                    $v['funcs'][]=array(
	                        'func'=>$v['func'],
	                        'func_param'=>$v['func_param']
	                    );
	                }
	                unset($v['func']);
	                unset($v['func_param']);
	                $contentSigns[$k]=$v;
	            }
	        }
	    }
	    return $contentSigns;
	}
	
	private function _compatible_processes($processes){
	    if(is_array($processes)){
	        $processes=array_values($processes);
	        $toolIsImg=array();
	        foreach ($processes as $pk=>$pv){
	            if(is_array($pv)){
	                if($pv['module']=='api'){
	                    
	                    if(is_array($pv['api_headers'])&&!isset($pv['api_headers']['addon'])){
	                        
	                        $pv['api_headers']['addon']=$pv['api_headers']['val'];
	                        foreach ($pv['api_headers']['val'] as $vk=>$vv){
	                            if($vv){
	                                $pv['api_headers']['val'][$vk]='custom';
	                            }
	                        }
	                        $processes[$pk]=$pv;
	                    }
	                    if(isset($pv['api_json_implode'])){
	                        
	                        $pv['api_json_arr_implode']=$pv['api_json_implode'];
	                        unset($pv['api_json_implode']);
	                        $processes[$pk]=$pv;
	                    }
	                }elseif($pv['module']=='tool'){
	                    
	                    if(is_array($pv['tool_list'])){
	                        foreach ($pv['tool_list'] as $k=>$v){
	                            if($v=='is_img'){
	                                $toolIsImg[]=$pk;
	                                unset($pv['tool_list'][$k]);
	                            }
	                        }
	                        $pv['tool_list']=array_values($pv['tool_list']);
	                        $processes[$pk]=$pv;
	                    }
	                }
	            }
	        }
	        if(!empty($toolIsImg)){
	            
	            $count=0;
	            foreach ($toolIsImg as $k){
	                array_splice($processes,$k+1+$count,0,array(array('title'=>'','module'=>'download','download_op'=>'is_img')));
	                $count++;
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
	            self::collecting_data($collectorKey,'delete');
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
	
	public static function collecting_data($collectorKey,$dataOp=null){
	    if($collectorKey){
	        $mcache=CacheModel::getInstance('collecting');
	        if(!isset($dataOp)){
	            
	            $processes=$mcache->getCache($collectorKey,'data');
	            init_array($processes);
	            return $processes;
	        }elseif($dataOp==='delete'){
	            
	            $mcache->deleteCache($collectorKey);
	        }elseif(is_array($dataOp)){
	            
	            $mcache->setCache($collectorKey,$dataOp);
	        }
	    }else{
	        
	        if(!isset($dataOp)){
	            
	            return array();   
	        }
	    }
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
	/*触发运行自动采集*/
	public static function collect_run_auto($rootUrl='',$taskIds=null){
	    try{
	        
	        $url='';
	        if($rootUrl){
	            $url=$rootUrl.'/admin/index/auto_collect';
	        }else{
	            $url=url('admin/index/auto_collect',null,false,true);
	        }
	        $url.=(strpos($url, '?')===false?'?':'&').'backstage_run=1&key='.\util\Param::set_cache_key('auto_collect');
	        if($taskIds&&is_array($taskIds)){
	            
	            $taskIds=implode(',', $taskIds);
	            $url.='&task_ids='.rawurlencode($taskIds);
	        }
	        get_html($url,null,array('timeout'=>3));
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
	        
	        self::collecting_data($collectorKey,$processes);
	        
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
	                'key'=>\util\Param::set_cache_key('collect_process'),
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
	            $chList[$pkey]=get_html($url,null,array('return_curl'=>1,'timeout'=>3));
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