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

class Config extends BaseModel {
    protected $pk = 'cname';
    
	public function convertData($configItem){
		if(!empty($configItem)){
			switch($configItem['ctype']){
				case 1:$configItem['data']=intval($configItem['data']);break;
				case 2:$configItem['data']=unserialize($configItem['data']);break;
			}
		}
		return $configItem;
	}
	/**
	 * 获取
	 * @param string $cname 名称
	 * @param string $key 数据键名
	 * @return mixed
	 */
	public function getConfig($cname,$key=null){
		
		$item=$this->where('cname',$cname)->find();
		if(!empty($item)){
			$item=$item->toArray();
			$item=$this->convertData($item);
		}
		return $key?$item[$key]:$item;
	}
	/**
	 * 设置
	 * @param string $cname 名称
	 * @param string $value 数据
	 */
	public function setConfig($cname,$value){
		$data=array('cname'=>$cname,'ctype'=>0);
		if(is_array($value)){
			$data['ctype']=2;
			$data['data']=serialize($value);
		}elseif(is_integer($value)){
			$data['ctype']=1;
			$data['data']=intval($value);
		}else{
			$data['data']=$value;
		}
		$data['dateline']=time();
		$this->insert($data,true);
		
		
		$this->cacheConfigList();
	}
	/*缓存所有配置*/
	public function cacheConfigList(){
		$keyConfig='cache_config_all';
		$configDbList=$this->column('*');
		$configDbList=empty($configDbList)?array():$configDbList;
		$configList=array();
		foreach ($configDbList as $configItem){
			$configItem=$this->convertData($configItem);
			$configList[$configItem['cname']]=$configItem['data'];
		}
		cache($keyConfig,array('list'=>$configList));
	}
	public function getConfigList(){
		$keyConfig='cache_config_all';
		$cacheConfig=cache($keyConfig);
		$configList=$cacheConfig['list'];
		return is_array($configList)?$configList:array();
	}
	
	/*设置版本号*/
	public function setVersion($version){
		$version=trim(strtoupper($version),'V');
		$this->setConfig('version', $version);
	}
	/*获取数据库的版本*/
	public function getVersion(){
		$dbVersion=$this->where("`cname`='version'")->find();
		if(!empty($dbVersion)){
			$dbVersion=$this->convertData($dbVersion);
			$dbVersion=$dbVersion['data'];
		}
		return $dbVersion;
	}
	/*检查图片路径*/
	public function check_img_path($imgPath){
		$return=array('success'=>false,'msg'=>'');
		if(!empty($imgPath)){
			
			if(!preg_match('/(^\w+\:)|(^[\/\\\])/i', $imgPath)){
				$return['msg']='图片目录必须为绝对路径！';
			}else{
				if(!is_dir($imgPath)){
					$return['msg']='图片目录不存在！';
				}else{
					$imgPath=realpath($imgPath);
					$root_path=rtrim(realpath(config('root_path')),'\\\/');
					if(preg_match('/^'.addslashes($root_path).'\b/i',$imgPath)){
						
						if(!preg_match('/^'.addslashes($root_path).'[\/\\\]data[\/\\\].+/i', $imgPath)){
							$return['msg']='图片保存到本程序中，目录必须在data文件夹里';
						}else{
							$return['success']=true;
						}
					}else{
						$return['success']=true;
					}
				}
			}
		}
		return $return;
	}

	/*检查图片网址*/
	public function check_img_url($imgUrl){
		$return=array('success'=>false,'msg'=>'');
		if(!empty($imgUrl)){
			if(!preg_match('/^\w+\:\/\//i',$imgUrl)){
				$return['msg']='图片链接地址必须以http://或者https://开头';
			}else{
				$return['success']=true;
			}
		}
		return $return;
	}
	
	public function check_img_name_path($path){
		static $check_list=array(); 
		$pathMd5=md5($path);
		if(!isset($check_list[$pathMd5])){
			$return=array('success'=>false,'msg'=>'');
			if(!empty($path)){
				if(!preg_match('/^(\w+|\-|\/|(\[(年|月|日|时|分|秒|前两位|后两位|任务名|任务ID)\])|(\[字段\:[^\/\[\]]+?\]))+$/u',$path)){
					$return['msg']='图片名称自定义路径只能输入字母、数字、下划线、/ 或 使用标签';
				}else{
					if(preg_match('/^\/+$/', $path)){
						$return['msg']='图片名称自定义路径不能只由/组成';
					}else{
						$return['success']=true;
					}
				}
			}
			$check_list[$pathMd5]=$return;
		}else{
		    $return=$check_list[$pathMd5];
		}
		return $return;
	}
	
	public function convert_img_name_path($path,$url){
	    if(!empty($path)){
    		$md5=md5($url);
    		static $tags=array('[年]','[月]','[日]','[时]','[分]','[秒]','[前两位]','[后两位]');
    		$tagsRe=array(
    			date('Y',NOW_TIME),
    			date('m',NOW_TIME),
    			date('d',NOW_TIME),
    		    date('H',NOW_TIME),
    		    date('i',NOW_TIME),
    		    date('s',NOW_TIME),
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
	
	public function check_img_name_name($name){
	    static $check_list=array(); 
	    $nameMd5=md5($name);
	    if(!isset($check_list[$nameMd5])){
	        $return=array('success'=>false,'msg'=>'');
	        if(!empty($name)){
	            if(!preg_match('/^(\w+|\-|(\[(年|月|日|时|分|秒|前两位|后两位|任务名|任务ID|图片网址MD5码|图片原名)\])|(\[字段\:[^\/\[\]]+?\]))+$/u',$name)){
	                $return['msg']='图片名称自定义名称只能输入字母、数字、下划线 或 使用标签';
	            }else{
	               $return['success']=true;
	            }
	        }
	        $check_list[$nameMd5]=$return;
	    }else{
	        $return=$check_list[$nameMd5];
	    }
	    
	    return $return;
	}
	
	public function convert_img_name_name($name,$url){
        $md5=md5($url);
        if(!empty($name)){
            $urlname='';
            if(preg_match('/([^\/]+?)\./', $url,$urlname)){
                $urlname=$urlname[1];
            }else{
                $urlname='';
            }
            if(empty($urlname)){
                
                $urlname=$md5;
            }
            
            static $tags=array('[年]','[月]','[日]','[时]','[分]','[秒]','[前两位]','[后两位]','[图片网址MD5码]','[图片原名]');
            $tagsRe=array(
                date('Y',NOW_TIME),
                date('m',NOW_TIME),
                date('d',NOW_TIME),
                date('H',NOW_TIME),
                date('i',NOW_TIME),
                date('s',NOW_TIME),
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
	
	public function detect_php_exe(){
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
			}else{
				$php_filename='php';
			}
		}
		return $php_filename;
	}
}
?>