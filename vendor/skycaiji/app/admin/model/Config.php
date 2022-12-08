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

class Config extends \skycaiji\common\model\Config {
    
	public function getConfigList(){
		$keyConfig='cache_config_all';
		$cacheConfig=cache($keyConfig);
		if(!is_array($cacheConfig)){
		    $cacheConfig=array();
		}
		$configList=$cacheConfig['list'];
		return is_array($configList)?$configList:array();
	}
	
	
	public function server_is_cli($isInput=false,$val=null){
	    $value='';
	    if(empty($isInput)){
	        
	        $value=g_sc_c('caiji','server');
	    }else{
	        
	        $value=$val;
	    }
	    $value=$value=='cli'?true:false;
	    return $value;
	}
	
	
	public function page_render_is_chrome($isInput=false,$val=null){
	    $value='';
	    if(empty($isInput)){
	        
	        $value=g_sc_c('page_render','tool');
	    }else{
	        
	        $value=$val;
	    }
	    $value=$value=='chrome'?true:false;
	    return $value;
	}
	
	/*检查图片路径*/
	public function check_img_path($imgPath){
		$result=return_result('',false);
		if(!empty($imgPath)){
			
			if(!preg_match('/(^\w+\:)|(^[\/\\\])/i', $imgPath)){
				$result['msg']='图片目录必须为绝对路径！';
			}else{
				if(!is_dir($imgPath)){
				    $result['msg']='图片目录不存在！'.(self::check_basedir_limited($imgPath)?lang('error_open_basedir'):'');
				}else{
					$imgPath=realpath($imgPath);
					$root_path=rtrim(realpath(config('root_path')),'\\\/');
					if(preg_match('/^'.addslashes($root_path).'\b/i',$imgPath)){
						
						if(!preg_match('/^'.addslashes($root_path).'[\/\\\]data[\/\\\].+/i', $imgPath)){
							$result['msg']='图片保存到本程序中，目录必须在data文件夹里';
						}else{
							$result['success']=true;
						}
					}else{
						$result['success']=true;
					}
				}
			}
		}
		return $result;
	}

	/*检查图片网址*/
	public function check_img_url($imgUrl){
		$result=return_result('',false);
		if(!empty($imgUrl)){
			if(!preg_match('/^\w+\:\/\//i',$imgUrl)){
				$result['msg']='图片链接地址必须以http://或者https://开头';
			}else{
				$result['success']=true;
			}
		}
		return $result;
	}
	/*检查自定义图片名的路径设置*/
	public function check_img_name_path($path){
		static $check_list=array(); 
		$pathMd5=md5($path);
		if(!isset($check_list[$pathMd5])){
			$result=return_result('',false);
			if(!empty($path)){
				if(!preg_match('/^(\w+|\-|\/|(\[(年|月|日|时|分|秒|前两位|后两位|任务名|任务ID)\])|(\[字段\:[^\/\[\]]+?\]))+$/u',$path)){
					$result['msg']='图片名称自定义路径只能输入字母、数字、下划线、/ 或 使用标签';
				}else{
					if(preg_match('/^\/+$/', $path)){
						$result['msg']='图片名称自定义路径不能只由/组成';
					}else{
						$result['success']=true;
					}
				}
			}
			$check_list[$pathMd5]=$result;
		}else{
		    $result=$check_list[$pathMd5];
		}
		return $result;
	}
	/*转换自定义图片名的路径*/
	public function convert_img_name_path($path,$url){
	    if(!empty($path)){
    		$md5=md5($url);
    		static $tags=array('[年]','[月]','[日]','[时]','[分]','[秒]','[前两位]','[后两位]');
    		$nowTime=time();
    		$tagsRe=array(
    		    date('Y',$nowTime),
    		    date('m',$nowTime),
    		    date('d',$nowTime),
    		    date('H',$nowTime),
    		    date('i',$nowTime),
    		    date('s',$nowTime),
    			substr($md5,0,2),
    			substr($md5,-2,2),
    		);
    		$path=str_replace($tags, $tagsRe, $path);
    		$path=preg_replace('/[\s\r\n\~\`\!\@\#\$\%\^\&\*\(\)\+\=\{\}\[\]\|\\\\:\;\"\'\<\>\,\?]+/', '_', $path);
    		$path=preg_replace('/\_{2,}/', '_', $path);
    		$path=preg_replace('/\/{2,}/', '/', $path);
    		$path=trim($path,'_');
    		$path=trim($path,'/');
	    }
		if(empty($path)){
		    $path='temp';
		}
		return $path;
	}
	/*检查自定义图片名的名称设置*/
	public function check_img_name_name($name){
	    static $check_list=array(); 
	    $nameMd5=md5($name);
	    if(!isset($check_list[$nameMd5])){
	        $result=return_result('',false);
	        if(!empty($name)){
	            if(!preg_match('/^(\w+|\-|(\[(年|月|日|时|分|秒|前两位|后两位|任务名|任务ID|图片网址MD5码|图片原名)\])|(\[字段\:[^\/\[\]]+?\]))+$/u',$name)){
	                $result['msg']='图片名称自定义名称只能输入字母、数字、下划线 或 使用标签';
	            }else{
	               $result['success']=true;
	            }
	        }
	        $check_list[$nameMd5]=$result;
	    }else{
	        $result=$check_list[$nameMd5];
	    }
	    
	    return $result;
	}
	/*转换自定义图片名的名称*/
	public function convert_img_name_name($name,$url){
        $md5=md5($url);
        if(!empty($name)){
            $urlname='';
            if(preg_match('/([^\/]+?)(\.[a-zA-Z][\w\-]+){0,1}([\?\#]|$)/', $url,$urlname)){
                
                $urlname=$urlname[1];
                if(mb_strlen($urlname,'utf-8')>100){
                    
                    $urlname=mb_substr($urlname,0,100,'utf-8');
                }
            }else{
                $urlname='';
            }
            
            if(empty($urlname)){
                
                $urlname=$md5;
            }
            
            static $tags=array('[年]','[月]','[日]','[时]','[分]','[秒]','[前两位]','[后两位]','[图片网址MD5码]','[图片原名]');
            $nowTime=time();
            $tagsRe=array(
                date('Y',$nowTime),
                date('m',$nowTime),
                date('d',$nowTime),
                date('H',$nowTime),
                date('i',$nowTime),
                date('s',$nowTime),
                substr($md5,0,2),
                substr($md5,-2,2),
                $md5,
                $urlname
            );
            $name=str_replace($tags, $tagsRe, $name);
            $name=preg_replace('/[\/\s\r\n\~\`\!\@\#\$\%\^\&\*\(\)\+\=\{\}\[\]\|\\\\:\;\"\'\<\>\,\?]+/', '_', $name);
            $name=preg_replace('/\_{2,}/', '_', $name);
            $name=trim($name,'_');
        }
        if(empty($name)){
            $name=$md5;
        }
        return $name;
    }
    
	/*从采集设置中提取出图片本地化设置*/
	public function get_img_config_from_caiji($caijiConfig){
		$config=array();
		if(!empty($caijiConfig)){
			
			static $vars=array('download_img','img_path','img_url','img_name','img_timeout','img_interval','img_max');
			foreach ($vars as $var){
				if(isset($caijiConfig[$var])){
					$config[$var]=$caijiConfig[$var];
				}
			}
		}
		return $config;
	}
	
	/*检测出php可执行文件路径*/
	public static function detect_php_exe(){
		static $php_filename=null;
		
		if(!isset($php_filename)){
			$ds=DIRECTORY_SEPARATOR;
			$ini_all=ini_get_all();
			$php_ext_path=$ini_all['extension_dir']['local_value'];
			if($php_ext_path){
				$php_ext_path=preg_replace('/[\/\\\]+/', '/', $php_ext_path);
				$phpPaths=explode('/', $php_ext_path);
				$phpPath='';
				if(IS_WIN){
					
					foreach ($phpPaths as $v){
						$phpPath.=$v.$ds;
						if(is_file($phpPath.'php-cli.exe')){
							$php_filename=$phpPath.'php-cli.exe';
							break;
						}elseif(is_file($phpPath.'php.exe')){
							$php_filename=$phpPath.'php.exe';
							break;
						}
					}
				}else{
					
					foreach ($phpPaths as $v){
						$phpPath.=$v.$ds;
						if(is_file($phpPath.'bin'.$ds.'php')){
							$php_filename=$phpPath.'bin'.$ds.'php';
							break;
						}
					}
				}
			}
			if(empty($php_filename)){
			    $php_filename='php';
			}
		}
		return $php_filename;
	}
	
	public function exec_php_version($phpFile){
	    $result=false;
	    if(empty($phpFile)){
	        
	        $phpFile=self::detect_php_exe();
	    }
	    if(!empty($phpFile)){
	        $result=return_result('',false);
	        $phpFile=self::cli_safe_filename($phpFile);
            $phpFile.=' -v';
            $info=\util\Tools::proc_open_exec_curl($phpFile,'all',10,true);
            $info=is_array($info)?$info:array();
            $info['output']=trim($info['output']);
            $info['error']=trim($info['error']);
            
            if(is_array($info['status'])&&$info['status']['running']){
                
                if($info['error']){
                    $result['msg']=$info['error'];
                }elseif($info['output']){
                    $result['success']=true;
                    $result['msg']=('测试成功，PHP信息：'.$info['output']);
                }else{
                    $result['success']=true;
                }
            }elseif($info['error']){
                $result['msg']=$info['error'];
            }
	    }
	    return $result;
	}
	
	public static function cli_safe_filename($filename){
	    if(!empty($filename)){
	        if(IS_WIN){
	            
	            $filename='"'.$filename.'"';
	        }else{
	            
	            if(preg_match('/(?<!\\\)\s/', $filename)){
	                $filename=preg_replace('/(?<!\\\)(\s)/', "\\\\$1", $filename);
	            }
	        }
	    }
	    return $filename;
	}
	
	/*open_basedir目录保护，检查目录是否受限*/
	public static function check_basedir_limited($path){
	    $openBasedir=ini_get('open_basedir');
	    if(empty($openBasedir)){
	        return false;
	    }
	    if(empty($path)){
	        return false;
	    }
	    if(file_exists($path)){
	        
	        return false;
	    }
	    $path=str_replace('\\', '/', $path);
	    $path=rtrim($path,'/').'/';
	    
	    $openBasedir=explode(IS_WIN?';':':', $openBasedir);
	    if(is_array($openBasedir)){
	        foreach ($openBasedir as $dir){
	            if(empty($dir)){
	                continue;
	            }
	            $dir=str_replace('\\', '/', $dir);
	            $dir=rtrim($dir,'/').'/';
	            if(stripos($path, $dir)===0){
	                
	                return false;
	            }
	        }
	    }
	    return true;
	}
	
	public static function wait_time_tips($seconds){
	    $seconds=intval($seconds);
	    if($seconds>0){
	        if($seconds<60){
	            $seconds.='秒';
	        }else{
	            $seconds=$seconds/60;
	            $seconds=substr(sprintf("%.3f", $seconds), 0, -2);
	            $seconds.='分钟';
	        }
	    }else{
	        $seconds='';
	    }
	    return $seconds;
	}
}
?>