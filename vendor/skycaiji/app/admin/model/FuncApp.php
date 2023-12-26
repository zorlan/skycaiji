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
class FuncApp extends \skycaiji\common\model\BaseModel{
	public $funcPath; 
	public $funcModules=array(
		'process'=>array (
			'name'=>'数据处理',
		    'loc'=>'任务»采集器设置»数据处理»使用函数',
		    'config'=>'allow_process_func',
		    'extend'=>'EXTEND_PROCESS_FUNC',
		),
		'processIf'=>array(
			'name'=>'条件判断',
		    'loc'=>'任务»采集器设置»数据处理»条件判断»使用函数',
		    'config'=>'allow_process_if',
		    'extend'=>'EXTEND_PROCESS_IF',
		),
	    'downloadImg'=>array(
	        'name'=>'下载图片',
	        'loc'=>'设置»采集设置»图片本地化»使用函数',
	    ),
	    'downloadFile'=>array(
	        'name'=>'下载文件',
	        'loc'=>'设置»采集设置»文件本地化»使用函数',
	    ),
	    'contentSign'=>array(
	        'name'=>'内容标签',
	        'loc'=>'任务»采集器设置»页面配置»提取内容标签»使用函数',
	        'config'=>'allow_process_func',
	        'extend'=>'EXTEND_PROCESS_FUNC',
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
		$data['enable']=intval($data['enable']);
		$data['enable']=$data['enable']>0?1:0;
		$data['uptime']=$data['uptime']>0?$data['uptime']:time();
		return $this->strict(false)->insert($data,false,true);
	}
	/*创建插件并入库*/
	public function createApp($module,$app,$appData=array()){
		$module=$this->format_module($module);
		$funcFile=$this->filename($module,$app);
		$funcTpl=file_get_contents(config('app_path').'/public/func_app/class.tpl');
		
		$appData['name']=$this->format_str($appData['name']);
		$name=$appData['name'];
		if(!empty($appData['name'])){
			$appData['name']="/**\r\n * ".$appData['name']."\r\n */";
		}else{
			$appData['name']='';
		}
		
		if(is_array($appData['methods'])){
			$methods='';
			$settedList=array();
			foreach ($appData['methods']['method'] as $k=>$v){
			    if($this->right_method($v)&&!in_array($v, $settedList)){
			        $settedList[]=$v;
					
					$methods.="\r\n    /**\r\n     * ".$this->format_str($appData['methods']['comment'][$k])."\r\n     */"
						."\r\n    public function {$v}(\$val){\r\n        return \$val;\r\n    }";
				}
			}
			$appData['methods']=$methods;
		}else{
			$appData['methods']='';
		}
		
		$funcTpl=str_replace(array('{$module}','{$classname}','{$name}','{$methods}'), array($module,$app,$appData['name'],$appData['methods']), $funcTpl);
		
		if(write_dir_file($funcFile,$funcTpl)){
		    $funcData=$this->where('app',$app)->find();
		    if(!empty($funcData)){
		        
		        $this->where('id',$funcData['id'])->update(array('name'=>$name,'uptime'=>time()));
		        return $funcData['id'];
		    }else{
		        
		        return $this->insertApp(array('module'=>$module,'app'=>$app,'name'=>$name,'enable'=>1));
		    }
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
		if(!$this->right_module($func['module'])){
			return false;
		}
		
		$func['uptime']=$func['uptime']>0?$func['uptime']:time();
		
		if(!preg_match('/^([A-Z][a-z0-9]*){2}$/',$func['app'])){
			
			return false;
		}
		
		$codeFmt=\util\Funcs::strip_phpcode_comment($code);
		
		if(!preg_match('/^\s*namespace\s+plugin\\\func\b/im',$codeFmt)){
			
			return false;
		}
		if(!preg_match('/class\s+'.$func['app'].'\b/i',$codeFmt)){
			
			return false;
		}
		
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
	public function get_app_class($module,$app,$options=array()){
	    $config=array();
	    
		$module=$this->format_module($module);
		
		$config['module']=$module;
		$config['app']=$app;
		$config['methods']=array();
		
		if(preg_match('/^(\w+?)([A-Z])(\w*)$/',$app,$mapp)){
		    $config['identifier']=$mapp[1];
		    $config['copyright']=$mapp[2].$mapp[3];
		}
		
		$filename=$this->filename($module,$app);
		if(file_exists($filename)){
			$class=$this->app_classname($module, $app);
			if(\util\Funcs::class_exists_clean($class)){
			    $config['filename']=$filename;
				$class=new $class();
				$reClass = new \ReflectionClass($class);
				
				$name=$reClass->getDocComment();
				$name=preg_replace('/^[\/\*\s]+/m', '', $name);
				$name=trim($name);
				$config['name']=$name;
				
				$methods=array();
				$reMethods=$reClass->getMethods(\ReflectionMethod::IS_PUBLIC);
				if(!empty($reMethods)){
				    $phpCode=array();
				    if(!empty($options['method_code'])||!empty($options['method_params'])){
				        $phpCode=file($filename);
				    }
					foreach ($reMethods as $reMethod){
					    $methodData=array();
					    
						$methodName=$reMethod->name;
						if(empty($methodName)||strpos($methodName,'__')===0){
							
							continue;
						}
						
						$methodCmt=$reMethod->getDocComment();
						if(empty($options['doc_comment'])){
						    
						    $methodCmt=preg_replace('/^[\/\*\s]+/m', '', $methodCmt);
						    $methodCmt=trim($methodCmt);
						}
						
						
						if(!empty($options['comment_cut'])){
						    
						    $methodCmtCut='';
						    if($methodCmt){
						        $methodCmt=preg_replace('/^[\/\*\s]+/m', '', $methodCmt);
						        $methodCmt=trim($methodCmt);
						        
						        $methodCmtCut=$methodCmt;
						        $methodCmtCut=htmlspecialchars($methodCmtCut,ENT_QUOTES);
						        $methodCmtCut=preg_replace('/[\r\n]+/', ' ', $methodCmtCut);
						        $methodCmtCut=trim($methodCmtCut);
						        $maxLen=50;
						        if(mb_strlen($methodCmtCut,'utf-8')>$maxLen){
						            
						            $methodCmtCut=mb_substr($methodCmtCut,0,$maxLen,'utf-8').'...';
						        }
						        
						        $methodCmt=htmlspecialchars($methodCmt,ENT_QUOTES);
						        $methodCmt=preg_replace('/[\r\n]+/', '\r\n', $methodCmt);
						        $methodCmt=trim($methodCmt);
						    }
						    $methodData['comment_cut']=$methodCmtCut;
						}
						$methodData['comment']=$methodCmt;
						
						if(!empty($options['method_code'])||!empty($options['method_params'])){
						    
						    $methodStart=$reMethod->getStartLine();
						    $methodEnd=$reMethod->getEndLine();
						    $methodCode=array_slice($phpCode, $methodStart-1, $methodEnd-$methodStart+1);
						    $methodCode=is_array($methodCode)?implode('',$methodCode):'';
						    if(!empty($options['method_code'])){
						        
						        $methodData['code']=$methodCode;
						    }
						    
						    if(!empty($options['method_params'])){
						        $methodParams='';
						        if(preg_match('/\bfunction\s+'.addslashes($methodName).'\s*\((.*?)\)\s*\{/i',$methodCode,$methodParams)){
						            $methodParams=$methodParams[1];
						        }else{
						            $methodParams='';
						        }
						        $methodData['params']=trim(htmlspecialchars($methodParams,ENT_QUOTES));
						    }
						}
						$methods[$methodName]=$methodData;
					}
				}
				$config['methods']=$methods;
			}
		}
		return $config;
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
	public function format_str($str){
	    $str=strip_tags($str);
	    $str=preg_replace('/(\/\*+)|(\*+\/)/', '', $str);
	    return $str;
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
	public function right_method($method){
	    if(preg_match('/^[a-z\_]\w*$/',$method)){
	        return true;
	    }else{
	        return false;
	    }
	}
	
	
	public function get_func_module_val($module,$key){
	    if(is_array($this->funcModules[$module])){
	        return $this->funcModules[$module][$key];
	    }else{
	        return null;
	    }
	}
	
	/**
	 * 执行插件函数
	 * @param string $module 模块
	 * @param string $funcNameFmt 函数/方法
	 * @param string $defaultVal 默认传入值
	 * @param string $paramsStr 输入的参数（有换行符）
	 * @param array $paramValList 所有参数值（调用参数时使用）
	 */
	public function execute_func($module,$funcName,$defaultVal,$paramsStr,$paramValList=null){
	    
	    static $func_class_list=array('process'=>array(),'processIf'=>array(),'contentSign'=>array(),'downloadImg'=>array(),'downloadFile'=>array());
	    static $func_param_num_list=array('process'=>array(),'processIf'=>array(),'contentSign'=>array(),'downloadImg'=>array(),'downloadFile'=>array());
	    static $func_param_rules=array(
	        'process'=>'/\[\x{5b57}\x{6bb5}\:.+?\]/u',
	        'processIf'=>'/\[\x{5b57}\x{6bb5}\:.+?\]/u',
	        'contentSign'=>null,
	        'downloadImg'=>'/\[\x{56fe}\x{7247}\:.+?\]/u',
	        'downloadFile'=>'/\[\x{6587}\x{4ef6}\:.+?\]/u',
	    );
	    $class_list=&$func_class_list[$module];
	    $param_num_list=&$func_param_num_list[$module];
	    $param_rule=$func_param_rules[$module];
	    
	    $options = $this->funcModules[$module];
	    
	    $result=return_result('',false,array('data'=>null));
	    
	    if(!empty($funcName)){
	        $funcNameFmt=strpos($funcName, ':')!==false?explode(':', $funcName):$funcName;
	        if(!is_array($funcNameFmt)){
	            
	            if(!function_exists($funcNameFmt)&&$funcNameFmt!='empty'){
	                
	                $result['msg']=$options['loc'].'»无效的函数：'.$funcNameFmt;
	            }elseif(!array_key_exists($funcNameFmt, config($options['config']))&&!array_key_exists($funcNameFmt, config($options['extend']))){
	                
	                $result['msg']=$options['loc'].'»未配置函数：'.$funcNameFmt;
	            }else{
	                $result['success']=true;
	            }
	        }else{
	            
	            $className=$funcNameFmt[0];
	            $methodName=$funcNameFmt[1];
	            if(!isset($class_list[$className])){
	                
	                $class=$this->app_classname($module,$className);
	                if(!\util\Funcs::class_exists_clean($class)){
	                    $class_list[$className]=1;
	                }else{
	                    $enable=$this->field('enable')->where(array('app'=>$className,'module'=>$module))->value('enable');
	                    if($enable){
	                        
	                        $class=new $class();
	                        $class_list[$className]=$class;
	                    }else{
	                        $class_list[$className]=2;
	                    }
	                }
	            }
	            if(is_object($class_list[$className])){
	                
	                if(!method_exists($class_list[$className], $methodName)){
	                    $result['msg']=$options['loc'].'»不存在方法：'.$className.'->'.$methodName;
	                }else{
	                    $result['success']=true;
	                }
	            }else{
	                $msg=$options['loc'].'»';
	                if($class_list[$className]==1){
	                    $msg.='不存在插件：';
	                }elseif($class_list[$className]==2){
	                    $msg.='已禁用插件：';
	                }else{
	                    $msg.='无效的插件：';
	                }
	                $result['msg']=$msg.$className;
	            }
	        }
	        if($result['success']){
	            $funcParams=\util\Funcs::txt_replace_params(true, true, $paramsStr, $defaultVal, $param_rule, $paramValList);
	            if(!empty($funcParams)&&is_array($funcParams)){
	                try {
	                    $callback=null;
	                    $paramNum=array();
	                    if(!is_array($funcNameFmt)){
	                        
	                        if($funcNameFmt=='empty'){
	                            
	                            $result['data']=empty($funcParams[0]);
	                        }else{
	                            $callback=$funcNameFmt;
	                            if(!isset($param_num_list[$funcNameFmt])){
	                                
	                                $refFunc=new \ReflectionFunction($funcNameFmt);
	                                $param_num_list[$funcNameFmt]=array(
	                                    'num'=>intval($refFunc->getNumberOfParameters()),
	                                    'required'=>intval($refFunc->getNumberOfRequiredParameters()),
	                                );
	                            }
	                            $paramNum=$param_num_list[$funcNameFmt];
	                        }
	                    }else{
	                        
	                        $callback=array($class_list[$funcNameFmt[0]],$funcNameFmt[1]);
	                        if(!isset($param_num_list[$funcName])){
	                            
	                            $refMethod=(new \ReflectionClass($class_list[$funcNameFmt[0]]))->getMethod($funcNameFmt[1]);
	                            $param_num_list[$funcName]=array(
	                                'num'=>intval($refMethod->getNumberOfParameters()),
	                                'required'=>intval($refMethod->getNumberOfRequiredParameters()),
	                            );
	                        }
	                        $paramNum=$param_num_list[$funcName];
	                    }
	                    if($callback){
	                        $paramCount=count($funcParams);
	                        if($paramCount<$paramNum['required']){
	                            
	                            $result['success']=false;
	                            $result['msg']=$options['loc'].'»'.$funcName.'»至少传入'.$paramNum['required'].'个参数';
	                        }elseif($paramCount>$paramNum['num']){
	                            
	                            $funcParams=array_slice($funcParams,0,$paramNum['num']);
	                        }
	                        if($result['success']){
	                            $result['data']=call_user_func_array($callback, $funcParams);
	                        }
	                    }
	                }catch (\Exception $ex){
	                    
	                    $result['success']=false;
	                    $result['msg']=$options['loc'].'»'.$funcName.'»运行错误：'.$ex->getMessage();
	                }
	            }
	        }
	    }
	    return $result;
	}
}

?>