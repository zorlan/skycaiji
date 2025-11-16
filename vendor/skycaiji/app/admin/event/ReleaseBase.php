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
	public function record_collected($url,$returnData,$release,$insertData=null,$echo=true){
	    if($this->is_collecting()){
	        
    	    $returnData['id']=isset($returnData['id'])?$returnData['id']:0;
    	    $returnData['target']=isset($returnData['target'])?$returnData['target']:'';
    	    $returnData['desc']=isset($returnData['desc'])?$returnData['desc']:'';
    	    $returnData['error']=isset($returnData['error'])?$returnData['error']:'';
    	    
    	    $mcollected=model('Collected');
    		if($returnData['id']>0){
    			
    			$title='';
    			$content='';
    		    if(is_array($insertData)){
    		        $title=$insertData['title'];
    		        $content=$insertData['content'];
    		    }else{
    		        
    		        $title=$insertData;
    		    }
    		    $collectedId=$mcollected->insert(array(
    				'urlMd5' => md5 ( $url ),
    			    'titleMd5'=>empty($title)?'':md5($title),
    			    'contentMd5'=>empty($content)?'':md5($content),
    				'task_id' => $release['task_id'],
    				'release' => $release['module'],
    				'addtime'=>time(),
    			    'status'=>1
    			),false,true);
    			if($collectedId>0){
    			    $mcollected->addInfo(array(
    			        'id' => $collectedId,
    			        'url' => $url,
    			        'target' => $returnData['target'],
    			        'desc' => $returnData['desc']?$returnData['desc']:'',
    			        'error'=>'',
    			    ));
    			}
    			if(!empty($returnData['target'])){
    			    $target=$returnData['target'];
    			    $echoData=array('成功将<a href="%s" target="_blank">内容</a>发布至'.lang('collected_rele_'.$release['module']).'：',$url);
    				if(preg_match('/^http(s){0,1}\:\/\//i',$target)){
    				    $echoData[0].='<a href="%s" target="_blank">%s</a>';
    				    $echoData[]=$target;
    				    $echoData[]=$target;
    				}else{
    				    $target=$mcollected->convertTarget($release['module'],$returnData['target']);
    				    if($target==$returnData['target']){
    				        
    				        $echoData[0].='%s';
    				        $echoData[]=$target;
    				    }else{
    				        $echoData[0].=$target;
    				    }
    				}
    				$this->echo_msg($echoData,'green',$echo);
    			}else{
    			    $this->echo_msg(array('成功发布：<a href="%s" target="_blank">%s</a>',$url,$url),'green',$echo);
    			}
    		}else{
    			
    			if(!empty($returnData['error'])){
    				
    			    if($mcollected->collGetNumByUrl($url,0)<=0){
    					
    			        $collectedId=$mcollected->insert(array(
    						'urlMd5' => md5 ( $url ),
    						'titleMd5'=>'',
    						'contentMd5'=>'',
    						'task_id' => $release['task_id'],
    						'release' => $release['module'],
    			            'addtime'=>time(),
    			            'status'=>0
    			        ),false,true);
    			        if($collectedId>0){
    			            $mcollected->addInfo(array(
    			                'id' => $collectedId,
    			                'url' => $url,
    			                'target' => '',
    			                'desc'=>'',
    			                'error' => $returnData['error'],
    			            ));
    			        }
    				}
    				$this->echo_msg(array('发布失败：%s',$returnData['error']),'red',$echo);
    			}
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
		if(!is_empty(g_sc_c('download_file','download_file'))){
		    
		    if(!empty($collFieldVal['file'])){
		        
		        if(!is_array($collFieldVal['file'])){
		            
		            $collFieldVal['file']=array($collFieldVal['file']);
		        }
		        $total=count($collFieldVal['file']);
		        if($total>0){
		            $this->echo_msg(array('正在下载：%s » %s个文件',$collFieldVal['name'],$total),'black');
		        }
		        $curI=0;
		        foreach ($collFieldVal['file'] as $fileUrl){
		            $this->collect_stopped(g_sc('collect_task_id'));
		            $newFileUrl=$this->download_file($fileUrl);
		            if($newFileUrl!=$fileUrl){
		                
		                $val=str_replace($fileUrl, $newFileUrl, $val);
		            }
		            $curI++;
		            if($curI<$total){
		                
		                $this->collect_sleep(g_sc_c('download_file','file_interval'),true);
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
		
		$originalUrl=$url;
		
		$retryMax=intval(g_sc_c('download_img','retry'));
		
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
		    $imgSuffixes=array('jpg','jpeg','gif','png','bmp','webp','wbmp');
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
		    
		    $headers=array();
		    $useCookieImg=\util\Param::get_gsc_use_cookie('img',true);
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
		    }else{
		        $options['timeout']=300;
		    }
		    if(!is_empty(g_sc_c('download_img','img_max'))){
		        
		        $options['max_bytes']=intval(g_sc_c('download_img','img_max'))*1024*1024;
		    }
		    
			
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
		        
		        if(!is_empty(g_sc_c('download_img','url_real'))){
		            
		            $realOptions=$options;
		            $realOptions['return_head']=true;
		            $realOptions['return_info']=true;
		            $imgCodeInfo=get_html($url,$headers,$realOptions,'utf-8',null,true);
		            if(!empty($imgCodeInfo['ok'])){
		                unset($options['max_bytes']);
		                if($imgCodeInfo['info']&&$imgCodeInfo['info']['url']){
		                    
		                    
		                    $url=$imgCodeInfo['info']['url'];
		                }
		            }else{
		                return $this->_down_retry($proxyDbIp, $originalUrl, $retryCur, $retryMax, $imgCodeInfo, true);
		            }
		        }
		        $prop=\util\Funcs::get_url_suffix($url);
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
				$filename=$this->_convert_file_charset($filename,true);
				$isExists=file_exists($filename);
				
				if(!$isExists){
					
					
					$imgname=substr($key,0,2).'/'.substr($key,2).'.'.$prop;
					$filename=$img_path.$imgname;
					$filename=$this->_convert_file_charset($filename,true);
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
			    $filename=$this->_convert_file_charset($filename,true);
				$isExists=file_exists($filename);
			}else{
				
				$imgname=date('Y-m-d',time()).'/'.$key.'.'.$prop;
				$filename=$img_path.$imgname;
				$filename=$this->_convert_file_charset($filename,true);
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
			                            $imgCodeInfo=null;
			                            if(!is_empty(g_sc_c('download_img','img_watermark'))){
			                                
			                                $imgWmLogo=g_sc_c('download_img','img_wm_logo');
			                                if($imgWmLogo){
			                                    
			                                    $imgWmLogo=config('root_path').$imgWmLogo;
			                                    if(file_exists($imgWmLogo)){
			                                        $imgInfoWm=getimagesize($imgWmLogo);
			                                        $imgInfoImg=getimagesize($filename);
			                                        $imgPropWm=str_replace('image/','',strtolower($imgInfoWm['mime']?:''));
			                                        $imgPropImg=str_replace('image/','',strtolower($imgInfoImg['mime']?:''));
			                                        $imgPropWm=$imgPropWm=='jpg'?'jpeg':$imgPropWm;
			                                        $imgPropImg=$imgPropImg=='jpg'?'jpeg':$imgPropImg;
			                                        if($imgPropWm&&$imgPropImg){
			                                            
    			                                        $funcImage='image'.$imgPropImg;
    			                                        if(function_exists('imagecreatefromstring')){
    			                                            if(function_exists($funcImage)){
    			                                                
    			                                                $icfWm=imagecreatefromstring(file_get_contents($imgWmLogo));
    			                                                $icfImg=imagecreatefromstring(file_get_contents($filename));
    			                                                
    			                                                $cWmRight=intval(g_sc_c('download_img','img_wm_right'));
    			                                                $cWmBottom=intval(g_sc_c('download_img','img_wm_bottom'));
    			                                                $cWmOpacity=intval(g_sc_c('download_img','img_wm_opacity'));
    			                                                $cWmOpacity=min(100,max(0,$cWmOpacity));
    			                                                
    			                                                $cWmRight=$imgInfoImg[0]-$imgInfoWm[0]-$cWmRight;
    			                                                $cWmBottom=$imgInfoImg[1]-$imgInfoWm[1]-$cWmBottom;
    			                                                
    			                                                if($cWmOpacity>0){
    			                                                    imagecopymerge($icfImg,$icfWm,$cWmRight,$cWmBottom,0,0,$imgInfoWm[0],$imgInfoWm[1],100-$cWmOpacity);
    			                                                }else{
    			                                                    
    			                                                    imagecopy($icfImg,$icfWm,$cWmRight,$cWmBottom,0,0,$imgInfoWm[0],$imgInfoWm[1]);
    			                                                }
    			                                                call_user_func($funcImage,$icfImg,$filename);
    			                                                imagedestroy($icfWm);
    			                                                imagedestroy($icfImg);
    			                                            }else{
    			                                                $this->echo_msg('添加水印失败，不存在函数：'.$funcImage);
    			                                            }
    			                                        }else{
    			                                            $this->echo_msg('添加水印失败，不存在函数：imagecreatefromstring');
    			                                        }
			                                        }
			                                    }else{
			                                        $this->echo_msg('不存在水印logo：'.$imgWmLogo);
			                                    }
			                                }
			                            }
			                            
			                            
			                            $imgFuncs=g_sc_c('download_img','img_funcs');
			                            if(!empty($imgFuncs)&&is_array($imgFuncs)){
			                                $paramVals=array(
			                                    '[图片:文件名]'=>$filename,
			                                    '[图片:路径]'=>$img_path,
			                                    '[图片:名称]'=>$imgname,
			                                    '[图片:网址]'=>$url
			                                );
			                                $mfuncApp=model('FuncApp');
			                                foreach ($imgFuncs as $imgFunc){
			                                    $paramVals['[图片:链接]']=$this->cache_img_list[$key];
			                                    if($imgFunc&&is_array($imgFunc)&&$imgFunc['func']){
			                                        
			                                        $return=$mfuncApp->execute_func('downloadImg',$imgFunc['func'],$filename,$imgFunc['func_param'],$paramVals);
			                                        if($return['success']){
			                                            
			                                            $checkResult=model('Config')->check_img_url($return['data']);
			                                            if($return['data']&&$checkResult['success']){
			                                                
			                                                $this->cache_img_list[$key]=$return['data'];
			                                            }
			                                        }elseif($return['msg']){
			                                            
			                                            $this->echo_msg_exit(array('%s',$return['msg']));
			                                        }
			                                    }
			                                }
			                            }
			                        }
			                    }
			                }
			            }else{
			                
			                return $this->_down_retry($proxyDbIp, $originalUrl, $retryCur, $retryMax, $imgCodeInfo, true);
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
	/*下载文件*/
	private $cache_file_list=array();
	public function download_file($url){
	    static $retryCur=0;
	    static $filePaths=array();
	    static $fileUrls=array();
	    
	    $originalUrl=$url;
	    
	    $retryMax=intval(g_sc_c('download_file','retry'));
	    
	    $file_path=g_sc_c('download_file','file_path');
	    $file_url=g_sc_c('download_file','file_url');
	    
	    if(!isset($filePaths[$file_path])){
	        if(empty($file_path)){
	            
	            $filePaths[$file_path]=config('root_path').'/data/files/';
	        }else{
	            
	            $filePaths[$file_path]=rtrim($file_path,'\/\\').'/';
	        }
	    }
	    $file_path=$filePaths[$file_path];
	    
	    if(!isset($fileUrls[$file_url])){
	        if(empty($file_url)){
	            
	            $fileUrls[$file_url]=config('root_website').'/data/files/';
	        }else{
	            
	            $fileUrls[$file_url]=rtrim($file_url,'\/\\').'/';
	        }
	    }
	    $file_url=$fileUrls[$file_url];
	    
	    if(empty($url)){
	        return '';
	    }
	    
	    $mproxy=model('ProxyIp');
	    $options=array();
	    $proxyDbIp=null;
	    if(!is_empty(g_sc_c('proxy','open'))){
	        
	        $proxyDbIp=$mproxy->get_usable_ip();
	        $proxyIp=$mproxy->to_proxy_ip($proxyDbIp);
	        if(empty($proxyIp)){
	            
	            $this->echo_msg(array('没有可用的代理IP，跳过下载<a href="%s" target="_blank">文件</a>',$url));
	            return $url;
	        }else{
	            
	            $options['proxy']=$proxyIp;
	        }
	    }
	    
        
        if(!is_empty(g_sc_c('caiji','robots'))){
            
            if(!$this->abide_by_robots($url,$options)){
                $this->echo_msg(array('robots拒绝访问的网址：%s',$url));
                return $url;
            }
        }
	    
	    $key=md5($url);
	    if(!isset($this->cache_file_list[$key])){
	        $headers=array();
	        $useCookieFile=\util\Param::get_gsc_use_cookie('file',true);
	        if(!is_empty(g_sc('task_file_headers'))){
	            
	            $headers=g_sc('task_file_headers');
	            if(!is_array($headers)){
	                $headers=array();
	            }
	        }
	        if(!empty($useCookieFile)){
	            
	            unset($headers['cookie']);
	            $headers['cookie']=$useCookieFile;
	        }
	        
	        if(!is_empty(g_sc_c('download_file','file_timeout'))){
	            
	            $options['timeout']=g_sc_c('download_file','file_timeout');
	        }else{
	            $options['timeout']=1800;
	        }
	        if(!is_empty(g_sc_c('download_file','file_max'))){
	            
	            $options['max_bytes']=intval(g_sc_c('download_file','file_max'))*1024*1024;
	        }
	        $getRealUrl=g_sc_c('download_file','url_real');
	        
	        $prop=\util\Funcs::get_url_suffix($url);
	        static $urlProps=array('htm','html','php','asp','jsp');
	        if(in_array($prop, $urlProps)||empty($prop)){
	            $getRealUrl=true;
	        }
	        if($getRealUrl){
	            
	            $realOptions=$options;
	            $realOptions['return_head']=true;
	            $realOptions['return_info']=true;
	            $fileCodeInfo=get_html($url,$headers,$realOptions,'utf-8',null,true);
	            if(!empty($fileCodeInfo['ok'])){
	                unset($options['max_bytes']);
	                if($fileCodeInfo['info']&&$fileCodeInfo['info']['url']){
	                    
	                    
	                    $url=$fileCodeInfo['info']['url'];
	                    $prop=\util\Funcs::get_url_suffix($url);
	                }
	            }else{
	                return $this->_down_retry($proxyDbIp, $originalUrl, $retryCur, $retryMax, $fileCodeInfo, false);
	            }
	        }
	        
	        if($prop){
	            $prop='.'.$prop;
	        }
	        
	        $filefull='';
	        $fileurl='';
	        $isExists=false;
	        $filename=g_sc_c('download_file','file_name');
	        
	        if('url'==$filename){
	            
	            
	            
	            $filename=substr($key,0,2).'/'.substr($key,2).$prop;
	            $filefull=$file_path.$filename;
	            $filefull=$this->_convert_file_charset($filefull);
	            $isExists=file_exists($filefull);
	        }elseif('custom'==$filename){
	            
	            $customPath=g_sc_c('download_file','name_custom_path');
	            if(!is_null(g_sc_c('download_file','_name_custom_path'))){
	                $customPath=g_sc_c('download_file','_name_custom_path');
	            }
	            $customName=g_sc_c('download_file','name_custom_name');
	            if(!is_null(g_sc_c('download_file','_name_custom_name'))){
	                $customName=g_sc_c('download_file','_name_custom_name');
	            }
	            $customPath=model('Config')->convert_file_name_path($customPath,$url);
	            $customName=model('Config')->convert_file_name_name($customName,$url);
	            $filename=$customPath.'/'.$customName.$prop;
	            $filefull=$file_path.$filename;
	            $filefull=$this->_convert_file_charset($filefull);
	            $isExists=file_exists($filefull);
	        }else{
	            
	            $filename=date('Y-m-d',time()).'/'.$key.$prop;
	            $filefull=$file_path.$filename;
	            $filefull=$this->_convert_file_charset($filefull);
	            $isExists=file_exists($filefull);
	        }
	        $fileurl=$file_url.$filename;
	        
	        if(!$isExists){
	            
                try {
                    $options['return_info']=true;
                    $fileCodeInfo=get_html($url,$headers,$options,'utf-8',null,true);
                    if(!empty($fileCodeInfo['ok'])){
                        
                        $retryCur=0;
                        if(!empty($fileCodeInfo['html'])){
                            
                            $mFileProp=\util\Funcs::get_url_suffix($fileCodeInfo['info']['url']);
                            if($mFileProp){
                                
                                $mFileProp='.'.$mFileProp;
                                if($prop!=$mFileProp){
                                    
                                    $filename.=$mFileProp;
                                    $fileurl.=$mFileProp;
                                    $filefull.=$mFileProp;
                                    
                                    if(file_exists($filefull)){
                                        
                                        $isExists=true;
                                        $this->cache_file_list[$key]=$fileurl;
                                    }
                                }
                            }
                            if(!$isExists){
                                if(write_dir_file($filefull,$fileCodeInfo['html'])){
                                    $this->cache_file_list[$key]=$fileurl;
                                    
                                    $fileFuncs=g_sc_c('download_file','file_funcs');
                                    if(!empty($fileFuncs)&&is_array($fileFuncs)){
                                        $paramVals=array(
                                            '[文件:文件名]'=>$filefull,
                                            '[文件:路径]'=>$file_path,
                                            '[文件:名称]'=>$filename,
                                            '[文件:网址]'=>$url
                                        );
                                        $mfuncApp=model('FuncApp');
                                        foreach ($fileFuncs as $fileFunc){
                                            $paramVals['[文件:链接]']=$this->cache_file_list[$key];
                                            if($fileFunc&&is_array($fileFunc)&&$fileFunc['func']){
                                                
                                                $return=$mfuncApp->execute_func('downloadFile',$fileFunc['func'],$filefull,$fileFunc['func_param'],$paramVals);
                                                if($return['success']){
                                                    
                                                    $checkResult=model('Config')->check_file_url($return['data']);
                                                    if($return['data']&&$checkResult['success']){
                                                        
                                                        $this->cache_file_list[$key]=$return['data'];
                                                    }
                                                }elseif($return['msg']){
                                                    
                                                    $this->echo_msg_exit(array('%s',$return['msg']));
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }else{
                        
                        return $this->_down_retry($proxyDbIp, $originalUrl, $retryCur, $retryMax, $fileCodeInfo, false);
                    }
                }catch (\Exception $ex){
                    
                }
	        }else{
	            
	            $this->cache_file_list[$key]=$fileurl;
	        }
	    }
	    return empty($this->cache_file_list[$key])?$url:$this->cache_file_list[$key];
	}
	
	private function _convert_file_charset($filename,$isImg=false){
	    static $charset=null;
	    $type=$isImg?'download_img':'download_file';
	    if(!isset($charset)){
	        $charset=g_sc_c($type,'charset');
	        $charset=empty($charset)?'':strtolower($charset);
	        if($charset=='custom'){
	            
	            $charset=g_sc_c($type,'charset_custom');
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
	
	private function _down_retry($proxyDbIp,$url,&$retryCur,$retryMax,$htmlInfo,$isImg){
	    $type=$isImg?'图片':'文件';
	    
	    if(empty($htmlInfo['code'])&&!empty($htmlInfo['msg'])){
	        
	        $this->echo_msg($htmlInfo['msg'].'：'.$url,'black');
	        return $url;
	    }
	    
	    
	    if(!empty($proxyDbIp)){
	        $this->echo_msg(array('代理IP：%s',$proxyDbIp['ip']),'black',true,'','display:inline;margin-right:5px;');
	    }
	    
	    
	    $this->retry_first_echo($retryCur,$type.'下载失败',$url,$htmlInfo);
	    
	    
	    if(!empty($proxyDbIp)){
	        if($htmlInfo['code']!=404){
	            
	            model('ProxyIp')->set_ip_failed($proxyDbIp);
	        }
	    }
	    
	    $this->collect_sleep(g_sc_c(($isImg?'download_img':'download_file'),'wait'));
	    
	    if($this->retry_do_func($retryCur,$retryMax,$type.'无效')){
	        if($isImg){
	            return $this->download_img($url);
	        }else{
	            return $this->download_file($url);
	        }
	    }
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
	
	/*初始化下载配置*/
	public function init_download_config($taskData,$collFields){
	    init_array($taskData);
	    init_array($collFields);
	    if(!is_empty(g_sc_c('download_img','download_img'))&&g_sc_c('download_img','img_name')=='custom'){
	        
	        
	        $name_custom_path=g_sc_c('download_img','name_custom_path');
	        $check=model('Config')->check_img_name_path($name_custom_path);
	        if($check['success']){
	            $name_custom_path=$this->_convert_download_params($name_custom_path, $taskData, $collFields);
	        }else{
	            $name_custom_path='temp';
	        }
	        
	        set_g_sc(['c','download_img','_name_custom_path'],$name_custom_path);
	        
	        
	        $name_custom_name=g_sc_c('download_img','name_custom_name');
	        $check=model('Config')->check_img_name_name($name_custom_name);
	        if($check['success']){
	            $name_custom_name=$this->_convert_download_params($name_custom_name, $taskData, $collFields);
	        }else{
	            $name_custom_name='';
	        }
	        
	        set_g_sc(['c','download_img','_name_custom_name'],$name_custom_name);
	    }else{
	        
	        set_g_sc(['c','download_img','_name_custom_path'],null);
	        set_g_sc(['c','download_img','_name_custom_name'],null);
	    }
	    
	    if(!is_empty(g_sc_c('download_file','download_file'))&&g_sc_c('download_file','file_name')=='custom'){
	        
	        
	        $name_custom_path=g_sc_c('download_file','name_custom_path');
	        $check=model('Config')->check_file_name_path($name_custom_path);
	        if($check['success']){
	            $name_custom_path=$this->_convert_download_params($name_custom_path, $taskData, $collFields);
	        }else{
	            $name_custom_path='temp';
	        }
	        
	        set_g_sc(['c','download_file','_name_custom_path'],$name_custom_path);
	        
	        
	        $name_custom_name=g_sc_c('download_file','name_custom_name');
	        $check=model('Config')->check_file_name_name($name_custom_name);
	        if($check['success']){
	            $name_custom_name=$this->_convert_download_params($name_custom_name, $taskData, $collFields);
	        }else{
	            $name_custom_name='';
	        }
	        
	        set_g_sc(['c','download_file','_name_custom_name'],$name_custom_name);
	    }else{
	        
	        set_g_sc(['c','download_file','_name_custom_path'],null);
	        set_g_sc(['c','download_file','_name_custom_name'],null);
	    }
	}
	
	private function _convert_download_params($str,$taskData,$collFields){
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