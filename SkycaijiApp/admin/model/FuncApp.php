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
class FuncApp extends BaseModel{
	protected $tableName='func_app';
	
	public $funcPath; 
	public $funcModules=array(
		'process'=>array (
			'name'=>'数据处理',
			'loc'=>'数据处理》使用函数'
		),
		'processIf'=>array(
			'name'=>'条件判断',
			'loc'=>'数据处理》条件判断》使用函数'
		)
	);
	public function __construct($data = []){
		parent::__construct($data);
		$this->funcPath=config('plugin_path').DIRECTORY_SEPARATOR.'func'.DIRECTORY_SEPARATOR;
	}
	/*添加入库*/
	public function insertApp($data){
		$data=is_array($data)?$data:array();
		$data['module']=$this->format_module($data['module']);
		$data['name']=strip_tags($data['name']);
		$data['addtime']=time();
		$data['enable']=0;
		$data['uptime']=$data['uptime']>0?$data['uptime']:time();
		return $this->strict(false)->insert($data,false,true);
	}
	/*创建插件并入库*/
	public function createApp($module,$app,$appData=array()){
		$module=$this->format_module($module);
		$funcFile=$this->filename($module,$app);
		$funcTpl=file_get_contents(config('app_path').'/public/func_app/class.tpl');
		
		$name=$appData['name'];
		if(!empty($appData['name'])){
			$appData['name']="/**\r\n * ".$appData['name']."\r\n */";
		}else{
			$appData['name']='';
		}
		
		if(is_array($appData['methods'])){
			$methods='';
			foreach ($appData['methods']['method'] as $k=>$v){
				if(preg_match('/^[a-z\_]\w*/',$v)){
					
					$methods.="\r\n    /**\r\n     * ".strip_tags($appData['methods']['comment'][$k])."\r\n     */"
						."\r\n    public function {$v}(\$val){\r\n        return \$val;\r\n    }";
				}
			}
			$appData['methods']=$methods;
		}else{
			$appData['methods']='';
		}
		
		$funcTpl=str_replace(array('{$module}','{$classname}','{$name}','{$methods}'), array($module,$app,$appData['name'],$appData['methods']), $funcTpl);
		
		if(write_dir_file($funcFile,$funcTpl)){
			return $this->insertApp(array('module'=>$module,'app'=>$app,'name'=>$name));
		}else{
			return false;
		}
	}
	/*添加插件*/
	public function addFunc($func,$code=''){
		if(empty($func['app'])){
			return false;
		}
		$func['module']=$this->format_module($func['module']);
		$func['uptime']=$func['uptime']>0?$func['uptime']:time();
		$funcData=$this->where('app',$func['app'])->find();
		$success=false;
		
		if(!empty($funcData)){
			
			$this->strict(false)->where('app',$func['app'])->update($func);
			$success=true;
		}else{
			
			$func['id']=$this->insertApp($func);
			$success=$func['id']>0?true:false;
		}
		if($success){
			$funcAppPath=config('plugin_path').'/func';
			if(!empty($code)){
				
				write_dir_file($funcAppPath.'/'.$func['module'].'/'.ucfirst($func['app']).'.php', $code);
			}
		}
		return $success;
	}
	public function filename($module,$app){
		$module=$this->format_module($module);
		return $this->funcPath."{$module}/{$app}.php";
	}
	/*获取插件文件类的属性*/
	public function get_app_class($module,$app){
		$module=$this->format_module($module);
		$filename=$this->funcPath."{$module}/{$app}.php";
		if(file_exists($filename)){
			$class=$this->app_classname($module, $app);
			if(class_exists($class)){
				$copyright='';
				$identifier='';
				if(preg_match('/^(\w+?)([A-Z])(\w*)$/',$app,$mapp)){
					$identifier=$mapp[1];
					$copyright=$mapp[2].$mapp[3];
				}
				$class=new $class();

				$reClass = new \ReflectionClass($class);
				$name=$reClass->getDocComment();
				$name=preg_replace('/^[\/\*\s]+/m', '', $name);
				$name=trim($name);
				
				$reMethods=$reClass->getMethods(\ReflectionMethod::IS_PUBLIC);
				$methods=array();
				if(!empty($reMethods)){
					foreach ($reMethods as $reMethod){
						$comment=$reMethod->getDocComment();
						$comment=preg_replace('/^[\/\*\s]+/m', '', $comment);
						$comment=trim($comment);
						
						$methods[$reMethod->name]=array('comment'=>$comment);
					}
				}
				return array (
					'module' => $module,
					'app' => $app,
					'filename' => $filename,
					'copyright' => $copyright,
					'identifier' => $identifier,
					'name' => $name,
					'methods' => $methods
				);
			}
		}
		return array();
	}
	public function app_classname($module,$app){
		return '\\plugin\\func\\'.$module.'\\'.$app;
	}
	/*转换成app名称*/
	public function app_name($copyright,$identifier){
		$copyright=$this->format_copyright($copyright);
		$identifier=$this->format_identifier($identifier);
		return $identifier.$copyright;
	}
	public function format_module($module){
		return $module;
	}
	public function format_copyright($copyright){
		return ucfirst(strtolower($copyright));
	}
	public function format_identifier($identifier){
		return ucfirst(strtolower($identifier));
	}
	
	public function right_module($module){
		if(empty($this->funcModules[$module])){
			return false;
		}else{
			return true;
		}
	}
	public function right_copyright($copyright){
		if(preg_match('/^[a-z]+[a-z0-9]*$/i',$copyright)){
			return true;
		}else{
			return false;
		}
	}
	public function right_identifier($identifier){
		if(preg_match('/^[a-z]+[a-z0-9]*$/i',$identifier)){
			return true;
		}else{
			return false;
		}
	}
	/*获取所有插件类*/
	public function get_class_list($module){
		$apps=$this->get_app_list($module);
		$classList=array();
		foreach($apps as $app){
			$class=$this->get_app_class($module,$app);
			if(!empty($class)){
				$classList[$app]=$class;
			}
		}
		return $classList;
	}
	public function get_app_list($module){
		$apps=scandir($this->funcPath.$module);
		$appList=array();
		if(!empty($apps)){
			foreach($apps as $app){
				if(preg_match('/(\w+)\.php/i',$app,$mapp)){
					$appList[$app]=$mapp[1];
				}
			}
		}
		return $appList;
	}
	
}

?>