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

namespace skycaiji\admin\event;
use skycaiji\admin\model\CacheModel;
class ReleaseBase extends CollectBase{
	/*已采集记录*/
	public function record_collected($url,$returnData,$release,$title=null,$echo=true){
		if($returnData['id']>0){
			
			model('Collected')->insert(array(
				'url' => $url,
				'urlMd5' => md5 ( $url ),
				'titleMd5'=>empty($title)?'':md5($title),
				'target' => $returnData['target'],
				'desc' => $returnData['desc']?$returnData['desc']:'',
				'error'=>'',
				'task_id' => $release ['task_id'],
				'release' => $release['module'],
				'addtime'=>time()
			));
			if(!empty($returnData['target'])){
			    $target=$returnData['target'];
			    $echoData=array('成功将<a href="%s" target="_blank">内容</a>发布至：',$url);
				if(preg_match('/^http(s){0,1}\:\/\//i',$target)){
				    $echoData[0].='<a href="%s" target="_blank">%s</a>';
				    $echoData[]=$target;
				    $echoData[]=$target;
				}else{
				    $echoData[0].='%s';
				    $echoData[]=$target;
				}
				$this->echo_msg($echoData,'green',$echo);
			}else{
			    $this->echo_msg(array('成功发布：<a href="%s" target="_blank">%s</a>',$url,$url),'green',$echo);
			}
		}else{
			
			if(!empty($returnData['error'])){
				
			    if(model('Collected')->collGetNumByUrl($url)<=0){
					
					model('Collected')->insert(array(
						'url' => $url,
						'urlMd5' => md5 ( $url ),
						'titleMd5'=>'',
						'target' => '',
						'desc'=>'',
						'error' => $returnData['error'],
						'task_id' => $release ['task_id'],
						'release' => $release['module'],
						'addtime'=>time()
					));
				}
				$this->echo_msg(array('%s',$returnData['error']),'red',$echo);
			}
		}
		
		
		static $mcacheCont=null;
		if(!isset($mcacheCont)){
			$mcacheCont=CacheModel::getInstance('cont_url');
		}
		$mcacheCont->deleteCache(md5($url));
	}
	
	/*获取字段值*/
	public function get_field_val($collFieldVal){
		if(empty($collFieldVal)){
			return '';
		}
		$val=$collFieldVal['value'];
		if(!is_empty(g_sc_c('download_img','download_img'))){
			
			if(!empty($collFieldVal['img'])){
				
				if(!is_array($collFieldVal['img'])){
					
					$collFieldVal['img']=array($collFieldVal['img']);
				}
				$total=count($collFieldVal['img']);
				if($total>0){
				    $this->echo_msg(array('正在下载：%s » %s张图片',$collFieldVal['name'],$total),'black');
				}
				$curI=0;
				foreach ($collFieldVal['img'] as $imgUrl){
				    $this->collect_stopped(g_sc('collect_task_id'));
					$newImgUrl=$this->download_img($imgUrl);
					if($newImgUrl!=$imgUrl){
						
						$val=str_replace($imgUrl, $newImgUrl, $val);
					}
					$curI++;
					if($curI<$total){
						
					    $this->collect_sleep(g_sc_c('download_img','interval_img'),true);
					}
				}
			}
		}
		return $val;
	}
	/*下载图片*/
	private $cache_img_list=array();
	public function download_img($url){
	    static $retryCur=0;
		static $imgPaths=array();
		static $imgUrls=array();
		
		$retryMax=intval(g_sc_c('download_img','retry'));
		$retryParams=null;
		if($retryMax>0){
		    
		    $retryParams=array(0=>$url);
		}
		
		$img_path=g_sc_c('download_img','img_path');
		$img_url=g_sc_c('download_img','img_url');
		
		if(!isset($imgPaths[$img_path])){
			if(empty($img_path)){
				
				$imgPaths[$img_path]=config('root_path').'/data/images/';
			}else{
				
				$imgPaths[$img_path]=rtrim($img_path,'\/\\').'/';
			}
		}
		$img_path=$imgPaths[$img_path];
		
		if(!isset($imgUrls[$img_url])){
			if(empty($img_url)){
				
				$imgUrls[$img_url]=config('root_website').'/data/images/';
			}else{
				
				$imgUrls[$img_url]=rtrim($img_url,'\/\\').'/';
			}
		}
		$img_url=$imgUrls[$img_url];
		
		if(empty($url)){
			return '';
		}
		
		$isDataImage=stripos($url, 'data:image/')===0?true:false;
		
		if(!preg_match('/^\w+\:\/\//',$url)&&!$isDataImage){
			
			return $url;
		}
		
		if($isDataImage&&is_empty(g_sc_c('download_img','data_image'))){
		    
		    return $url;
		}
		
		$mproxy=model('ProxyIp');
		$options=array();
		$proxyDbIp=null;
		if(!is_empty(g_sc_c('proxy','open'))){
		    
		    $proxyDbIp=$mproxy->get_usable_ip();
		    $proxyIp=$mproxy->to_proxy_ip($proxyDbIp);
		    if(empty($proxyIp)){
		        
		        $this->echo_msg(array('没有可用的代理IP，跳过下载<a href="%s" target="_blank">图片</a>',$url));
		        return $url;
		    }else{
		        
		        $options['proxy']=$proxyIp;
		    }
		}
		
		if(!$isDataImage){
		    
		    if(!is_empty(g_sc_c('caiji','robots'))){
		        
		        if(!$this->abide_by_robots($url,$options)){
		            $this->echo_msg(array('robots拒绝访问的网址：%s',$url));
		            return $url;
		        }
		    }
		}
		
		static $imgSuffixes=null;
		if(!isset($imgSuffixes)){
		    $imgSuffixes=array('jpg','jpeg','gif','png','bmp');
		    $moreSuffix=g_sc_c('download_img','more_suffix');
		    if(!empty($moreSuffix)){
		        $moreSuffix=explode(',', $moreSuffix);
		        if(is_array($moreSuffix)){
		            $imgSuffixes=array_merge($imgSuffixes,$moreSuffix);
		        }
		    }
		}
		$key=md5($url);
		if(!isset($this->cache_img_list[$key])){
			
		    $prop='';
		    $dataImageCode='';
		    if($isDataImage){
		        
		        if(preg_match('/^data\:image\/(.+?)\;base64\,(.*)$/i',$url,$prop)){
		            $dataImageCode=base64_decode(trim($prop[2]));
		            $prop=strtolower($prop[1]);
		        }else{
		            $prop='';
		        }
		    }else{
		        
		        if(preg_match('/\.([a-zA-Z][\w\-]+)([\?\#]|$)/',$url,$prop)){
		            $prop=strtolower($prop[1]);
		        }else{
		            $prop='';
		        }
		    }
		    if(!in_array($prop, $imgSuffixes)){
		        
		        $prop='';
		    }
			if(empty($prop)){
			    $prop='jpg';
			}
			
			$filename='';
			$imgurl='';
			$isExists=false;
			$imgname=g_sc_c('download_img','img_name');
			
			if('url'==$imgname){
				
				
				
				
				$imgname=substr($key,0,2).'/'.substr($key,-2,2).'/'.$key.'.'.$prop;
				$filename=$img_path.$imgname;
				$filename=$this->_convert_img_charset($filename);
				$isExists=file_exists($filename);
				
				if(!$isExists){
					
					
					$imgname=substr($key,0,2).'/'.substr($key,2).'.'.$prop;
					$filename=$img_path.$imgname;
					$filename=$this->_convert_img_charset($filename);
					$isExists=file_exists($filename);
				}
			}elseif('custom'==$imgname){
				
			    $customPath=g_sc_c('download_img','name_custom_path');
			    if(!is_null(g_sc_c('download_img','_name_custom_path'))){
			        $customPath=g_sc_c('download_img','_name_custom_path');
			    }
			    $customName=g_sc_c('download_img','name_custom_name');
			    if(!is_null(g_sc_c('download_img','_name_custom_name'))){
			        $customName=g_sc_c('download_img','_name_custom_name');
			    }
			    $customPath=model('Config')->convert_img_name_path($customPath,$url);
			    $customName=model('Config')->convert_img_name_name($customName,$url);
			    $imgname=$customPath.'/'.$customName.'.'.$prop;
			    $filename=$img_path.$imgname;
			    $filename=$this->_convert_img_charset($filename);
				$isExists=file_exists($filename);
			}else{
				
				$imgname=date('Y-m-d',time()).'/'.$key.'.'.$prop;
				$filename=$img_path.$imgname;
				$filename=$this->_convert_img_charset($filename);
				$isExists=file_exists($filename);
			}
			$imgurl=$img_url.$imgname;
			
			if(!$isExists){
				
			    if($isDataImage){
			        
			        if(!empty($dataImageCode)){
			            if(write_dir_file($filename,$dataImageCode)){
			                $this->cache_img_list[$key]=$imgurl;
			            }
			        }
			    }else{
			        
			        try {
			            $headers=array();
			            $useCookieImg=\util\Param::get_gsc_use_cookie(true,true);
			            if(!is_empty(g_sc('task_img_headers'))){
			                
			                $headers=g_sc('task_img_headers');
			                if(!is_array($headers)){
			                    $headers=array();
			                }
			            }
			            if(!empty($useCookieImg)){
			                
			                unset($headers['cookie']);
			                $headers['cookie']=$useCookieImg;
			            }
			            
			            if(!is_empty(g_sc_c('download_img','img_timeout'))){
			                
			                $options['timeout']=g_sc_c('download_img','img_timeout');
			            }
			            if(!is_empty(g_sc_c('download_img','img_max'))){
			                
			                $options['max_bytes']=intval(g_sc_c('download_img','img_max'))*1024*1024;
			            }
			            
			            $imgCodeInfo=get_html($url,$headers,$options,'utf-8',null,true);
			            if(!empty($imgCodeInfo['ok'])){
			                
			                $retryCur=0;
			                if(!empty($imgCodeInfo['html'])){
			                    
			                    if(preg_match('/\bcontent-type\s*\:\s*image\s*\/(\w+)/i', $imgCodeInfo['header'],$mImgProp)){
			                        
			                        $mImgProp=strtolower($mImgProp[1]);
			                        if($prop!=$mImgProp){
			                            
			                            if(in_array($mImgProp,$imgSuffixes)){
			                                
			                                static $sameImgProp=array('jpg','jpeg');
			                                if(!in_array($prop,$sameImgProp)||!in_array($mImgProp,$sameImgProp)){
			                                    
			                                    $imgname.='.'.$mImgProp;
			                                    $imgurl.='.'.$mImgProp;
			                                    $filename.='.'.$mImgProp;
			                                    
			                                    if(file_exists($filename)){
			                                        
			                                        $isExists=true;
			                                        $this->cache_img_list[$key]=$imgurl;
			                                    }
			                                }
			                            }
			                        }
			                    }
			                    if(!$isExists){
			                        if(write_dir_file($filename,$imgCodeInfo['html'])){
			                            $this->cache_img_list[$key]=$imgurl;
			                            
			                            $funcName=g_sc_c('download_img','img_func');
			                            if(!empty($funcName)){
			                                
			                                $paramVals=array(
			                                    '[图片:文件名]'=>$filename,
			                                    '[图片:路径]'=>$img_path,
			                                    '[图片:名称]'=>$imgname,
			                                    '[图片:链接]'=>$imgurl,
			                                    '[图片:网址]'=>$url
			                                );
			                                $return=model('FuncApp')->execute_func('downloadImg',$funcName,$filename,g_sc_c('download_img','img_func_param'),$paramVals);
			                                if($return['success']){
			                                    
			                                    if($return['data']&&preg_match('/^\w+\:\/\//',$return['data'])){
			                                        
			                                        $this->cache_img_list[$key]=$return['data'];
			                                    }
			                                }elseif($return['msg']){
			                                    
			                                    $this->echo_msg(array('%s',$return['msg']));
			                                }
			                            }
			                        }
			                    }
			                }
			            }else{
			                
			                if(!empty($proxyDbIp)){
			                    $this->echo_msg(array('代理IP：%s',$proxyDbIp['ip']),'black',true,'','display:inline;margin-right:5px;');
			                }
			                
			                $this->retry_first_echo($retryCur,'图片下载失败',$url,$imgCodeInfo);
			                
			                
			                if(!empty($proxyDbIp)){
			                    if($imgCodeInfo['code']!=404){
			                        
			                        $mproxy->set_ip_failed($proxyDbIp);
			                    }
			                }
			                
			                $this->collect_sleep(g_sc_c('download_img','wait'));
			                
			                if($this->retry_do_func($retryCur,$retryMax,'图片无效')){
			                    return $this->download_img($retryParams[0]);
			                }
			            }
			        }catch (\Exception $ex){
			            
			        }
			    }
			}else{
				
			    $this->cache_img_list[$key]=$imgurl;
			}
		}
		return empty($this->cache_img_list[$key])?$url:$this->cache_img_list[$key];
	}
	
	private function _convert_img_charset($filename){
	    static $charset=null;
	    if(!isset($charset)){
	        $charset=g_sc_c('download_img','charset');
	        $charset=empty($charset)?'':strtolower($charset);
	        if($charset=='custom'){
	            
	            $charset=g_sc_c('download_img','charset_custom');
	            $charset=empty($charset)?'':strtolower($charset);
	        }
	        if($charset=='utf-8'){
	            
	            $charset='';
	        }
	    }
	    if(!empty($charset)&&!empty($filename)){
	        $filename=\util\Funcs::convert_charset($filename,'utf-8',$charset);
	    }
	    return $filename;
	}
	/*获取采集器字段*/
	public function get_coll_fields($taskId,$taskModule){
		static $fieldsList=array();
		$key=$taskId.'__'.$taskModule;
		if(!isset($fieldsList[$key])){
			$mcoll=model('Collector');
			$collData=$mcoll->where(array('task_id'=>$taskId,'module'=>$taskModule))->find();
			if(!empty($collData)){
				$collData=$collData->toArray();
				$collData['config']=unserialize($collData['config']?:'');
				$collFields=array();
				if(is_array($collData['config']['field_list'])){
					foreach ($collData['config']['field_list'] as $collField){
						$collFields[]=$collField['name'];
					}
				}
				$fieldsList[$key]=$collFields;
			}
		}
		return $fieldsList[$key];
	}
	/*隐藏采集字段（删除字段）*/
	public function hide_coll_fields($hideFields,&$collFields){
	    
	    if(!empty($hideFields)&&is_array($hideFields)&&is_array($collFields)&&is_array($collFields['fields'])){
		    foreach ($hideFields as $hideField){
		        unset($collFields['fields'][$hideField]);
		    }
		}
	}
	
	/*utf8转换成其他编码*/
	public function utf8_to_charset($charset,$val){
	    $val=\util\Funcs::convert_charset($val,'utf-8',$charset);
		return $val;
	}
	
	/*其他编码转换成utf8*/
	public function charset_to_utf8($charset,$val){
	    $val=\util\Funcs::convert_charset($val,$charset,'utf-8');
	    return $val;
	}
	
	/**
	 * 任意编码转换成utf8
	 * @param mixed $val 字符串或数组
	 */
	public function auto_convert2utf8($val){
	    if(is_array($val)){
	        $val=\util\Funcs::array_array_map('auto_convert2utf8',$val);
	    }else{
	        $val=auto_convert2utf8($val);
	    }
	    return $val;
	}
	/*写入文件*/
	public function write_file($filename,$data){
		return write_dir_file($filename,$data);
	}
	
	/*初始化下载图片*/
	public function init_download_img($taskData,$collFields){
	    if(!is_empty(g_sc_c('download_img','download_img'))&&g_sc_c('download_img','img_name')=='custom'){
	        
	        if(empty($taskData)){
	            $taskData=array();
	        }
	        if(empty($collFields)){
	            $collFields=array();
	        }
	        
	        $name_custom_path=g_sc_c('download_img','name_custom_path');
	        $check=model('Config')->check_img_name_path($name_custom_path);
	        if($check['success']){
	            $name_custom_path=$this->_convert_img_params($name_custom_path, $taskData, $collFields);
	        }else{
	            $name_custom_path='temp';
	        }
	        
	        set_g_sc(['c','download_img','_name_custom_path'],$name_custom_path);
	        
	        
	        $name_custom_name=g_sc_c('download_img','name_custom_name');
	        $check=model('Config')->check_img_name_name($name_custom_name);
	        if($check['success']){
	            $name_custom_name=$this->_convert_img_params($name_custom_name, $taskData, $collFields);
	        }else{
	            $name_custom_name='';
	        }
	        
	        set_g_sc(['c','download_img','_name_custom_name'],$name_custom_name);
	    }else{
	        
	        set_g_sc(['c','download_img','_name_custom_path'],null);
	        set_g_sc(['c','download_img','_name_custom_name'],null);
	    }
	}
	
	private function _convert_img_params($str,$taskData,$collFields){
	    if(empty($taskData)){
	        $taskData=array();
	    }
	    if(empty($collFields)){
	        $collFields=array();
	    }
	    
	    $customParams=array(
	        '[任务名]'=>$taskData['name'],
	        '[任务ID]'=>$taskData['id']
	    );
	    
        if(preg_match_all('/\[([^\[\]]+?)\]/', $str,$mparams)){
            for($i=0;$i<count($mparams[0]);$i++){
                $param=$mparams[0][$i];
                if(preg_match('/^字段\:(.*)$/u',$mparams[1][$i],$mfield)){
                    $customParams[$param]=$collFields[$mfield[1]]['value'];
                }
            }
            foreach ($customParams as $k=>$v){
                
                $v=preg_replace('/[\/\s\r\n\~\`\!\@\#\$\%\^\&\*\(\)\+\=\{\}\[\]\|\\\\:\;\"\'\<\>\,\?]+/', '_', $v);
                if(mb_strlen($v,'utf-8')>100){
                    
                    $v=mb_substr($v,0,100,'utf-8');
                }
                $v=preg_replace('/\_{2,}/', '_', $v);
                $v=trim($v,'_');
                $customParams[$k]=$v;
            }
            $str=str_replace(array_keys($customParams),$customParams,$str);
        }
        
        return $str;
	}
}
?>