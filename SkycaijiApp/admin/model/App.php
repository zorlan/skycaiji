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

class App extends BaseModel{
	/*检测是否是应用并获取配置文件实例化*/
	public function app_class($app,$includeClass=true){
		static $passPaths=array('.','..','common','admin','skycaiji','vendor');
		if($this->right_app($app)){
			
			$path=realpath(config('apps_path').'/'.$app);
			if(!empty($path)&&is_dir($path)){
				$appFilename=$this->app_class_file($app);
				if(!in_array($app,$passPaths)&&file_exists($appFilename)){
					
					if($includeClass){
						
						include $appFilename;
						$appClass=new $app();
					}else{
						
						$appFile=file_get_contents($appFilename);
						if(!empty($appFile)){
							$appClass=new \stdClass();
							if(preg_match('/public\s*\$config\s*=(\s*[\s\S]+?[\]\)]\s*\;)/i', $appFile,$config)){
								
								set_error_handler(null);
								
								$config=trim($config[1]);
								try {
									$config=@eval('return '.$config);
								}catch(\Exception $e){
									$config=array();
								}
								$appClass->config=is_array($config)?$config:array();
							}else{
								$appClass->config=array();
							}
						}
					}

					if($appClass){
						$appClass->config=$this->clear_config($appClass->config);
						return $appClass;
					}
				}
			}
		}
		return false;
	}
	public function getByApp($app){
		$data=$this->where('app',$app)->find();
		if(!empty($data)){
			$data=$data->toArray();
			$data['config']=$this->get_config($app);
		}else{
			$data=array();
		}
		return $data;
	}
	public function deleteByApp($app){
		if($app){
			$this->where('app',$app)->delete();
			$this->delete_config($app);
		}
	}
	/*应用配置文件名*/
	public function app_class_file($app){
		return realpath(config('apps_path')).DIRECTORY_SEPARATOR.$app.DIRECTORY_SEPARATOR.$app.'.php';
	}
	
	/*应用命名规范*/
	public function right_app($app){
		
		if(preg_match('/^[a-z]+[a-z\_0-9]*$/', $app)){
			return strlen($app)<3?false:true;
		}else{
			return false;
		}
	}

	/*版本号格式*/
	public function right_version($version){
		if(preg_match('/^\d+(\.\d{1,2}){1,2}$/', $version)){
			return true;
		}else{
			return false;
		}
	}
	/*名称只能由汉字、字母、数字和下划线组成*/
	public function right_name($name){
		if(preg_match('/^[\w+\x{4e00}-\x{9fa5}]+$/iu', $name)){
			return true;
		}else{
			return false;
		}
	}
	/*清理描述html*/
	public function clear_desc($desc){
		$desc=strip_tags($desc,'<p><br><b><i><a>');
		$desc=preg_replace('/<(p|br|b|i)\s+.*?>/i', "<$1>", $desc);
		$desc=preg_replace('/[\r\n]+/', ' ', $desc);
		$desc=trim($desc);
		return $desc;
	}
	/*清理配置信息*/
	public function clear_config($arr){
		$arr=is_array($arr)?$arr:array();
		$desc=$this->clear_desc($arr['desc']);
		$arr=$this->_array_map('strip_tags', $arr);
		$arr['desc']=$desc;
		if(!empty($arr['agreement'])){
			$arr['agreement']=preg_replace('/^[\s]+/m', '', $arr['agreement']);
		}
		return $arr;
	}
	/*缓存配置*/
	public function set_config($app,$config){
		if(empty($app)){
			return;
		}
		$config=is_array($config)?$config:array();
		$filename=$this->config_filename($app);
		$oldConfig=$this->get_config($app);
		$oldConfig=is_array($oldConfig)?$oldConfig:array();
		
		$config=array_merge($oldConfig,$config);
		$config=$this->clear_config($config);
		
		$config=var_export($config,true);
		$config='<?php return '.$config.'; ?>';
		
		write_dir_file($filename, $config);
	}
	/*读取配置*/
	public function get_config($app){
		$filename=$this->config_filename($app);
		$config=array();
		if(file_exists($filename)){
			
			$config=include $filename;
			$config=is_array($config)?$config:array();
		}
		$config=$this->clear_config($config);
		
		return $config;
	}
	public function delete_config($app){
		$filename=$this->config_filename($app);
		unlink($filename);
	}
	private function _array_map($callback, $arr1){
		if(is_array($arr1)){
			$arr=array();
			foreach ($arr1 as $k=>$v){
				if(!is_array($v)){
					$arr[$k]=call_user_func($callback, $v);
				}else{
					$arr[$k]=$this->_array_map($callback,$v);
				}
			}
		}
		return $arr;
	}
	public function config_filename($app){
		return config('apps_path').'/app/config/'.$app.'.php';
	}
	/*获取应用类的变量*/
	public function get_class_vars($appClass){
		if(!is_object($appClass)){
			return null;
		}
		$class=new \ReflectionClass($appClass);
		$vars=$class->getProperties();
		$values=array();
		if(is_array($vars)){
			foreach ($vars as $var){
				$var=$var->name;
				$values[$var]=$appClass->$var;
			}
		}
		return $values;
	}
}

?>