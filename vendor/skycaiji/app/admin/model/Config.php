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
		if(!empty($configList)){
		    
		    init_array($configList);
		    $configList['download_img']=$this->compatible_func_config($configList['download_img'], false);
		    $configList['download_file']=$this->compatible_func_config($configList['download_file'], true);
		}
		return $configList;
	}
	
	
	public function compatible_func_config($config,$isFileConfig,$isTaskConfig=false){
	    
	    if($config&&is_array($config)){
    	    $funcName=$isFileConfig?'file_func':'img_func';
    	    if(isset($config[$funcName])){
    	        
    	        $funcsName=$isFileConfig?'file_funcs':'img_funcs';
    	        if(!$isTaskConfig){
    	            if($config[$funcName]){
    	                
    	                $config[$funcsName]=array(
    	                    array(
    	                        'func'=>$config[$funcName],
    	                        'func_param'=>$config[$funcName.'_param']
    	                    )
    	                );
    	            }
    	        }else{
    	            
    	            $taskFunc='';
    	            $taskFuncParam=$config[$funcName.'_param'];
    	            if(empty($config[$funcName])){
    	                
    	                $funcs=g_sc_c($isFileConfig?'download_file':'download_img',$funcsName);
    	                if($funcs&&is_array($funcs)){
    	                    $funcs=array_values($funcs);
    	                    if($funcs[0]&&is_array($funcs[0])){
    	                        
    	                        $taskFunc=$funcs[0]['func'];
    	                        $taskFuncParam=empty($taskFuncParam)?$funcs[0]['func_param']:$taskFuncParam;
    	                    }
    	                }
    	            }else if($config[$funcName]=='n'){
    	                
    	                $config[$funcsName.'_open']='n';
    	            }else{
    	                $taskFunc=$config[$funcName];
    	            }
    	            if($taskFunc){
    	                
    	                $config[$funcsName.'_open']='y';
    	                $config[$funcsName]=array(
    	                    array(
    	                        'func'=>$taskFunc,
    	                        'func_param'=>$taskFuncParam
    	                    )
    	                );
    	            }
    	        }
    	        
    	        unset($config[$funcName]);
    	        unset($config[$funcName.'_param']);
    	    }
	    }
	    return $config;
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
	
	
	public function server_is_swoole($isInput=false,$val=null){
	    $value='';
	    if(empty($isInput)){
	        
	        $value=g_sc_c('caiji','server');
	    }else{
	        
	        $value=$val;
	    }
	    $value=$value=='swoole'?true:false;
	    return $value;
	}
	
	public function server_is_swoole_php($isInput=false,$server=null,$swoolePhp=null){
	    if(empty($isInput)){
	        
	        $server=g_sc_c('caiji','server');
	        $swoolePhp=g_sc_c('caiji','swoole_php');
	    }
	    if($server=='swoole'&&!empty($swoolePhp)){
	        return true;
	    }else{
	        return false;
	    }
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
	    return $this->_check_file_path(true,$imgPath);
	}
	/*检查图片网址*/
	public function check_img_url($imgUrl){
	    return $this->_check_file_url(true,$imgUrl);
	}
	/*检查自定义图片名的路径设置*/
	public function check_img_name_path($path){
	    return $this->_check_file_name_path(true,$path);
	}
	/*转换自定义图片名的路径*/
	public function convert_img_name_path($path,$url){
	    return $this->_convert_file_name_path(true,$path,$url);
	}
	/*检查自定义图片名的名称设置*/
	public function check_img_name_name($name){
	    return $this->_check_file_name_name(true,$name);
	}
	/*转换自定义图片名的名称*/
	public function convert_img_name_name($name,$url){
        return $this->_convert_file_name_name(true,$name,$url);
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
	
	/*检查文件路径*/
	public function check_file_path($filePath){
	    return $this->_check_file_path(false,$filePath);
	}
	/*检查文件网址*/
	public function check_file_url($fileUrl){
	    return $this->_check_file_url(false,$fileUrl);
	}
	/*检查自定义文件名的路径设置*/
	public function check_file_name_path($path){
	    return $this->_check_file_name_path(false,$path);
	}
	/*转换自定义文件名的路径*/
	public function convert_file_name_path($path,$url){
	    return $this->_convert_file_name_path(false,$path,$url);
	}
	/*检查自定义文件名的名称设置*/
	public function check_file_name_name($name){
	    return $this->_check_file_name_name(false,$name);
	}
	/*转换自定义文件名的名称*/
	public function convert_file_name_name($name,$url){
	    return $this->_convert_file_name_name(false,$name,$url);
	}
	
	/*上传图片水印logo*/
	public function check_img_watermark_logo($formName,$fileName=''){
	    $result=return_result('',false,array('file_prop'=>'','file_data'));
	    $imgWmLogo=$_FILES[$formName];
	    if(!empty($imgWmLogo)&&!empty($imgWmLogo['tmp_name'])){
	        if(preg_match('/^image\/(jpg|jpeg|gif|png)$/i',$imgWmLogo['type'],$mprop)){
	            $mprop=strtolower($mprop[1]);
	            $imgWmLogo=file_get_contents($imgWmLogo['tmp_name']);
	            if(empty($imgWmLogo)){
	                $result['msg']='请上传有效的水印logo';
	            }else{
	                $result['success']=true;
	                $result['file_prop']=$mprop;
	                $result['file_data']=$imgWmLogo;
	            }
	        }else{
	            $result['msg']='仅支持上传 jpg、jpeg、gif、png 格式的水印logo';
	        }
	    }else{
	        $result['success']=true;
	    }
	    return $result;
	}
	public function upload_img_watermark_logo($formName,$fileName=''){
	    $result=$this->check_img_watermark_logo($formName,$fileName);
	    if($result['success']){
	        if($result['file_data']){
	            
	            $fileName=$fileName?$fileName:'logo';
	            $result['file_name']='/data/images/watermark/'.$fileName.'.'.$result['file_prop'];
	            write_dir_file(config('root_path').$result['file_name'], $result['file_data']);
	        }
	        
	    }else{
	        $result['msg']=$result['msg']?:'上传水印logo失败';
	    }
	    return $result;
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
	
	public function php_is_valid($phpFile,$getModules=false){
	    $result=return_result('',false,array('v'=>'','ver'=>'','m'=>'','swoole'=>''));
	    if(!function_exists('proc_open')){
	        $result['msg']='需开启proc_open函数';
	    }else{
	        $phpResult=$this->exec_php_version($phpFile);
	        if($phpResult===false){
	            $result['msg']='未检测到PHP可执行文件，请手动输入';
	        }elseif(is_array($phpResult)){
	            $result=array_merge($result,$phpResult);
	        }
	        if(empty($phpResult)||(!$phpResult['success']&&$phpResult['msg'])){
	            
	            $result['success']=false;
	            $result['msg']=$phpResult['msg']?:'php无效';
	        }
	        if($result['success']){
	            $result['v']=$result['msg'];
	            if($result['v']){
	                
	                if(preg_match('/\bPHP\s+(?P<ver>\d+(\.\d+){1,})/i',$result['v'],$mphpv)){
	                    $result['ver']=$mphpv['ver'];
	                }
	            }
	        }
	        if($getModules){
	            
	            if($result['success']){
	                $phpResult=$this->exec_php_m($phpFile);
	                if($phpResult===false){
	                    $result['msg']='未检测到PHP可执行文件，请手动输入';
	                }elseif(is_array($phpResult)){
	                    $result=array_merge($result,$phpResult);
	                }
	                if(empty($phpResult)||(!$phpResult['success']&&$phpResult['msg'])){
	                    
	                    $result['success']=false;
	                    $result['msg']=$phpResult['msg']?:'php无效';
	                }
	                if($result['success']){
	                    $result['m']=$result['msg'];
	                    if($result['m']){
	                        if(preg_match('/\bswoole\b/i', $result['m'])){
	                            
	                            $result['swoole']=true;
	                        }
	                    }
	                }
	            }
	        }
	    }
	    return $result;
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
                    $result['msg']=$info['output'];
                }else{
                    $result['success']=true;
                }
            }elseif($info['error']){
                $result['msg']=$info['error'];
            }
	    }
	    return $result;
	}
	
	
	public function exec_php_m($phpFile){
	    $result=false;
	    if(empty($phpFile)){
	        
	        $phpFile=self::detect_php_exe();
	    }
	    if(!empty($phpFile)){
	        $result=return_result('',false);
	        $phpFile=self::cli_safe_filename($phpFile);
	        $phpFile.=' -m';
	        $info=\util\Tools::proc_open_exec_curl($phpFile,'all',10,true);
	        $info=is_array($info)?$info:array();
	        $info['output']=trim($info['output']);
	        $info['error']=trim($info['error']);
	        
	        if(is_array($info['status'])&&$info['status']['running']){
	            
	            if($info['error']){
	                $result['msg']=$info['error'];
	            }elseif($info['output']){
	                $result['success']=true;
	                $result['msg']=$info['output'];
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
	
	
	public static function process_suffix($suffix,$returnArr=false){
        static $list=array();
        $key=md5($suffix);
        $data=array();
        if(!isset($list[$key])){
            if($suffix){
                
                if(preg_match_all('/\b[a-zA-Z]\w*\b/i',$suffix,$msuffix)){
                    $data=array_unique($msuffix[0]);
                    $data=array_values($data);
                    $data=array_map('strtolower', $data);
                }
            }
            $list[$key]=$data;
        }
        $data=$list[$key];
        return $returnArr?$data:implode(',',$data);
	}
	
	
	public static function process_tag_attr($tagAttr,$returnArr=false){
        static $list=array();
        $key=md5($tagAttr);
        $data=array();
        if(!isset($list[$key])){
            if($tagAttr){
                
                if(preg_match_all('/\b([a-zA-Z]\w*)\:([a-zA-Z]\w*)\b/i',$tagAttr,$mtag)){
                    
                    $data=array(0=>array(),1=>array(),2=>array());
                    for($i=0;$i<count($mtag[0]);$i++){
                        $mtag[0][$i]=strtolower($mtag[0][$i]);
                        if(!in_array($mtag[0][$i], $data[0])){
                            $data[0][]=$mtag[0][$i];
                            $data[1][]=strtolower($mtag[1][$i]);
                            $data[2][]=strtolower($mtag[2][$i]);
                        }
                    }
                }
            }
            $list[$key]=$data;
        }
        $data=$list[$key];
        return $returnArr?$data:implode(',',is_array($data[0])?$data[0]:array());
	}
	
	
	
	private function _check_file_path($isImg,$filePath){
	    $title=$isImg?'图片':'文件';
	    $result=return_result('',false);
	    if(!empty($filePath)){
	        
	        if(!preg_match('/(^\w+\:)|(^[\/\\\])/i', $filePath)){
	            $result['msg']=$title.'目录必须为绝对路径！';
	        }else{
	            if(!is_dir($filePath)){
	                $result['msg']=$title.'目录不存在！'.(self::check_basedir_limited($filePath)?lang('error_open_basedir'):'');
	            }else{
	                $filePath=realpath($filePath);
	                $root_path=rtrim(realpath(config('root_path')),'\\\/');
	                if(preg_match('/^'.addslashes($root_path).'\b/i',$filePath)){
	                    
	                    if(!preg_match('/^'.addslashes($root_path).'[\/\\\]data[\/\\\].+/i', $filePath)){
	                        $result['msg']=$title.'保存到本程序中，目录必须在data文件夹里';
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
	
	private function _check_file_url($isImg,$fileUrl){
	    $title=$isImg?'图片':'文件';
	    $result=return_result('',false);
	    if(!empty($fileUrl)){
	        if(!preg_match('/^\w+\:\/\//i',$fileUrl)){
	            $result['msg']=$title.'链接地址必须以http://或者https://开头';
	        }else{
	            $result['success']=true;
	        }
	    }
	    return $result;
	}
	
	private function _check_file_name_path($isImg,$path){
	    $title=$isImg?'图片':'文件';
	    static $check_list=array(); 
	    $pathMd5=md5($path);
	    if(!isset($check_list[$pathMd5])){
	        $result=return_result('',false);
	        if(!empty($path)){
	            if(!preg_match('/^(\w+|\-|\/|(\[(年|月|日|时|分|秒|前两位|后两位|任务名|任务ID)\])|(\[字段\:[^\/\[\]]+?\]))+$/u',$path)){
	                $result['msg']=$title.'名称自定义路径只能输入字母、数字、下划线、/ 或 使用标签';
	            }else{
	                if(preg_match('/^\/+$/', $path)){
	                    $result['msg']=$title.'名称自定义路径不能只由/组成';
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
	
	private function _convert_file_name_path($isImg,$path,$url){
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
	
	private function _check_file_name_name($isImg,$name){
	    $title=$isImg?'图片':'文件';
	    static $check_list=array(); 
	    $nameMd5=md5($name);
	    if(!isset($check_list[$nameMd5])){
	        $result=return_result('',false);
	        if(!empty($name)){
	            $pattern='/^(\w+|\-|(\[(年|月|日|时|分|秒|前两位|后两位|任务名|任务ID|'.$title.'网址MD5码|'.$title.'原名)\])|(\[字段\:[^\/\[\]]+?\]))+$/u';
	            if(!preg_match($pattern,$name)){
	                $result['msg']=$title.'名称自定义名称只能输入字母、数字、下划线 或 使用标签';
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
	
	private function _convert_file_name_name($isImg,$name,$url){
	    $title=$isImg?'图片':'文件';
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
	        
	        static $sameTags=array('[年]','[月]','[日]','[时]','[分]','[秒]','[前两位]','[后两位]');
	        $tags=$sameTags;
	        $tags[]='['.$title.'网址MD5码]';
	        $tags[]='['.$title.'原名]';
	        
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
}
?>