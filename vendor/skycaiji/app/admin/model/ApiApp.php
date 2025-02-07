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
class ApiApp extends \skycaiji\common\model\BaseModel{
    public $apiPath; 
    public $apiModules=array(
        'process'=>array (
            'name'=>'数据处理',
            'loc'=>'任务»采集器设置»数据处理»接口插件',
        ),
    );
    public function __construct($data = []){
        parent::__construct($data);
        $this->apiPath=config('plugin_path').DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR;
    }
    
    public function getConfigByApp($app){
        $config=array();
        $apiData=$this->where('app',$app)->find();
        if($apiData){
            $config=$this->compatible_config($apiData['config']);
        }
        init_array($config);
        return $config;
    }
    
    /*添加插件*/
    public function addApp($app,$code=''){
        if(empty($app['app'])){
            return false;
        }
        $app['module']=$this->format_module($app['module']);
        if(!$this->right_module($app['module'])){
            return false;
        }
        
        $app['uptime']=$app['uptime']>0?$app['uptime']:time();
        
        if(!preg_match('/^([A-Z][a-z0-9]*){2}$/',$app['app'])){
            
            return false;
        }
        
        $codeFmt=\util\Funcs::strip_phpcode_comment($code);
        
        if(!preg_match('/^\s*namespace\s+plugin\\\api\b/im',$codeFmt)){
            
            return false;
        }
        if(!preg_match('/class\s+'.$app['app'].'\b/i',$codeFmt)){
            
            return false;
        }
        
        $appData=$this->where('app',$app['app'])->find();
        $success=false;
        
        if(!empty($appData)){
            
            $this->strict(false)->where('app',$app['app'])->update($app);
            $success=true;
        }else{
            
            $app['enable']=1;
            $app['id']=$this->insertApp($app);
            $success=$app['id']>0?true:false;
        }
        if($success){
            $appPath=config('plugin_path').'/api';
            if(!empty($code)){
                
                write_dir_file($appPath.'/'.$app['module'].'/'.ucfirst($app['app']).'.php', $code);
            }
        }
        return $success;
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
	    
	    $appData['name']=$this->format_str($appData['name']);
	    $name=$appData['name'];
	    if(!empty($appData['name'])){
	        $appData['name']="/**\r\n * ".$appData['name']."\r\n */";
	    }else{
	        $appData['name']='';
	    }
	    
	    init_array($appData['ops']);
	    $_ops=$appData['ops'];
	    foreach ($_ops as $k=>$v){
	        $v=url_b64decode($v);
	        $v=json_decode($v,true);
	        init_array($v);
	        
	        if($v['module']=='variable'){
	            $variable=$v['config'];
	            init_array($variable);
	            $varModule=$variable['module']?:'value';
	            $variable=array(
	                'name'=>$variable['name'],
	                'desc'=>$variable['desc'],
	                'module'=>$variable['module'],
	                $varModule=>$variable[$varModule],
	                'func'=>$variable['func'],
	            );
	            $v['config']=$variable;
	        }
	        
	        $v=var_export($v,true);
	        $v=preg_replace_callback('/^(\s*)(array|\'|\)|\d+\s*\=\>)/m', function($matches){
	            $returnStr="\t\t";
	            for($i=0;$i<(strlen($matches[1])/2);$i++){
	                $returnStr.="\t";
	            }
	            return $returnStr.$matches[2];
	        },$v);
	        $v=preg_replace('/\s+array\s*\(/i', 'array (', $v);
	        
	        $_ops[$k]="\t\t".$v.", \r\n";
	    }
	    $_ops=" array (\r\n".rtrim(implode('',$_ops))."\r\n\t)";
	    
	    $isNew=true;
	    $apiFile=$this->app_filename($module,$app);
	    if(file_exists($apiFile)){
	        $appClass=$this->app_import_class($module, $app);
	        if(!empty($appClass)){
	            $isNew=false;
	        }
	    }
	    
	    $apiCode='';
	    if($isNew){
	        
	        $apiCode=file_get_contents(config('app_path').'/public/api_app/class.tpl');
	        $apiCode=str_replace(array('{$module}','{$classname}','{$name}','{$content}','{$ops}'), array($module,$app,$appData['name'],$appData['content'],$_ops), $apiCode);
	    }else{
	        
	        $apiCode=file_get_contents($apiFile);
	        if(preg_match('/\bpublic\s*\$_ops\s*\=[\s\S]+?\)\s*;/i',$apiCode)){
	            
	            $apiCode=preg_replace('/\bpublic\s*\$_ops\s*\=[\s\S]+?\)\s*;/i', 'public $_ops ='.$_ops.';', $apiCode);
	        }else{
	            
	            $apiCode=preg_replace('/^\s*class\s+'.$app.'\s*\{/mi', "$0\r\n\tpublic \$_ops ={$_ops};", $apiCode);
	        }
	        if(preg_match('/\bpublic\s*\$_content\s*\=[\s\S]+?EOF\;/i',$apiCode)){
	            
	            $apiCode=preg_replace('/\bpublic\s*\$_content\s*\=[\s\S]+?EOF\;/i', "public \$_content = <<<EOF\r\n{$appData['content']}\r\nEOF;", $apiCode);
	        }else{
	            
	            $apiCode=preg_replace('/^\s*class\s+'.$app.'\s*\{/mi', "$0\r\n\tpublic \$_content = <<<EOF\r\n{$appData['content']}\r\nEOF;", $apiCode);
	        }
	    }
	    
	    if(!write_dir_file($apiFile,$apiCode)){
	        
	        return false;
	    }
	    
	    $apiData=$this->where('app',$app)->find();
	    if(!empty($apiData)){
	        
	        $this->where('id',$apiData['id'])->update(array('name'=>$name,'uptime'=>time()));
	        return $apiData['id'];
	    }else{
	        
	        return $this->insertApp(array('module'=>$module,'app'=>$app,'name'=>$name,'enable'=>1));
	    }
	}
	
	public function check_variable_name($name){
	    $result=return_result('');
	    if(empty($name)){
	        $result['msg']='变量名称不能为空！';
	    }elseif(!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)){
	        $result['msg']='变量名称只能由汉字、字母、数字和下划线组成';
	    }else{
	        $result['success']=true;
	    }
	    return $result;
	}
	
	public function check_request_name($name){
	    $result=return_result('');
	    if(empty($name)){
	        $result['msg']='请求名称不能为空！';
	    }elseif(!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)){
	        $result['msg']='请求名称只能由汉字、字母、数字和下划线组成';
	    }else{
	        $result['success']=true;
	    }
	    return $result;
	}
	
	public function filter_variable_func($funcConfig){
	    init_array($funcConfig);
	    init_array($funcConfig['names']);
	    init_array($funcConfig['params']);
	    foreach ($funcConfig['names'] as $k=>$v){
	        if(empty($v)||!preg_match('/^[a-z\_]\w*$/i', $v)){
	            
	            unset($funcConfig['names'][$k]);
	            unset($funcConfig['params'][$k]);
	        }
	    }
	    $funcConfig['names']=array_values($funcConfig['names']);
	    $funcConfig['params']=array_values($funcConfig['params']);
	    return $funcConfig;
	}
	
	public function format_module($module){
	    $module=strtolower($module);
	    return $module;
	}
	public function format_appname($app){
	    return ucfirst($app);
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
	    if(empty($this->apiModules[$module])){
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
	
	
	public function app_filename($module,$app){
	    $module=$this->format_module($module);
	    $app=$this->format_appname($app);
	    return $this->apiPath.$module.DIRECTORY_SEPARATOR.$app.'.php';
	}
	
	public function app_classname($module,$app){
	    $module=$this->format_module($module);
	    $app=$this->format_appname($app);
	    return '\\plugin\\api\\'.$module.'\\'.$app;
	}
	
	public function app_name($copyright,$identifier){
	    $copyright=$this->format_copyright($copyright);
	    $identifier=$this->format_identifier($identifier);
	    return $identifier.$copyright;
	}
	
	public function app_file_exists($module,$app){
	    $fileName=$this->app_filename($module,$app);
	    return file_exists($fileName)?true:false;
	}
	
	public function app_import_class($module,$app){
	    $appClass=$this->app_classname($module,$app);
	    if(\util\Funcs::class_exists_clean($appClass)){
	        $appClass=new $appClass();
	    }else{
	        $appClass=null;
	    }
	    return $appClass;
	}
	
	
	public function get_api_module_val($module,$key){
	    if(is_array($this->apiModules[$module])){
	        return $this->apiModules[$module][$key];
	    }else{
	        return null;
	    }
	}
	
	public function get_app_content($appClass){
	    $content='';
	    if($appClass&&is_object($appClass)&&property_exists($appClass,'_content')){
	        $content=$appClass->_content;
	    }
	    return $content;
	}
	
	public function get_app_ops($appData,$appClass=null,$isUser=false,$isUserGlobal=false){
	    $ops=null;
	    if(!empty($appData)&&empty($appClass)){
	        $appClass=$this->app_import_class($appData['module'],$appData['app']);
	    }
	    if($appClass&&is_object($appClass)&&property_exists($appClass,'_ops')){
	        $ops=$appClass->_ops;
	        init_array($ops);
	        if($isUser){
	            
	            $userOps=array();
	            foreach ($ops as $k=>$v){
	                init_array($v);
	                if($v['module']=='variable'){
	                    
	                    init_array($v['config']);
	                    $vconfig=$v['config'];
	                    if($vconfig['module']=='user'){
	                        init_array($vconfig['user']);
	                        if($isUserGlobal){
	                            
	                            if(empty($vconfig['user']['global'])){
	                                continue;
	                            }
	                        }else{
	                            
	                            if(!empty($vconfig['user']['global'])){
	                                continue;
	                            }
	                        }
	                        if($vconfig['user']['tag']=='select'){
                                
                                $tagSelect=array();
                                if(preg_match_all('/[^\r\n]+/',$vconfig['user']['tag_select'],$matches)){
                                    foreach ($matches[0] as $match){
                                        if(strpos($match,'=')!==false){
                                            
                                            list($tsk,$tsv)=explode('=',$match,2);
                                            if(is_null($tsk)){
                                                $tsk=$tsv;
                                            }
                                            $tagSelect[$tsk]=$tsv;
                                        }else{
                                            
                                            $tagSelect[$match]=$match;
                                        }
                                    }
                                }
                                $vconfig['user']['tag_select']=$tagSelect;
                            }
                            if(!is_empty($vconfig['user']['default'],true)){
                                
                                if($vconfig['user']['tag']=='radio'){
                                    $vconfig['user']['default']=intval($vconfig['user']['default'])>0?1:0;
                                }
                            }
                            $vconfig['name_key']=md5($vconfig['name']);
                            $userOps[]=$vconfig;
	                    }
	                }
	            }
	            $ops=$userOps;
	        }
	    }
	    init_array($ops);
	    return $ops;
	}
	
	public function get_app_class($module,$app,$options=array()){
	    $config=array();
	    
	    $module=$this->format_module($module);
	    
	    $config['module']=$module;
	    $config['app']=$app;
	    $config['ops']=array();
	    $config['content']='';
	    
	    if(preg_match('/^(\w+?)([A-Z])(\w*)$/',$app,$mapp)){
	        $config['identifier']=$mapp[1];
	        $config['copyright']=$mapp[2].$mapp[3];
	    }
	    
	    $filename=$this->app_filename($module,$app);
	    if(file_exists($filename)){
	        $appClass=$this->app_import_class($module,$app);
	        if($appClass){
	            $config['filename']=$filename;
	            $config['ops']=$this->get_app_ops(null,$appClass);
	            $config['content']=$this->get_app_content($appClass);
	        }
	    }
	    
	    return $config;
	}
	
	public function compatible_config($config){
	    if(!is_array($config)){
	        $config=unserialize($config?:'');
	    }
	    init_array($config);
	    init_array($config['global']);
	    return $config;
	}
	
	/**
	 * 执行接口插件
	 * @param string $module 模块
	 * @param string $appName 接口app
	 * @param string $fieldVal 字段值
	 * @param string $appConfig 输入的配置
	 * @param array $paramValList 所有参数值（调用参数时使用）
	 */
	public function execute_app($module,$appName,$fieldVal,$appConfig,$paramValList=null,$isTest=false){
	    static $app_class_list=array('process'=>array());
	    static $app_config_globals=array('process'=>array());
	   
	    $class_list=&$app_class_list[$module];
	    $config_globals=&$app_config_globals[$module];
	    
	    $options=$this->apiModules[$module];
	    $result=return_result('',false,array('data'=>null));
	    
	    if(!isset($class_list[$appName])){
	        
	        $class=$this->app_classname($module,$appName);
	        if(!\util\Funcs::class_exists_clean($class)){
	            $class_list[$appName]=1;
	        }else{
	            $enable=$this->field('enable')->where(array('app'=>$appName,'module'=>$module))->value('enable');
	            if($enable){
	                
	                $class=new $class();
	                $class_list[$appName]=$class;
	            }else{
	                $class_list[$appName]=2;
	            }
	        }
	    }
	    $msg=$options['loc'].'»';
	    if(is_object($class_list[$appName])){
	        
	        $configGlobalSetted=true;
	        if(!isset($config_globals[$appName])){
	            
	            $configGlobalSetted=false;
	            $apiConfig=$this->getConfigByApp($appName);
	            $config_globals[$appName]=$apiConfig['global'];
	        }
	        
	        $opVals=array();
	        $ops=$this->get_app_ops(null,$class_list[$appName]);
	        
	        foreach ($ops as $op){
	            if($op['module']=='variable'){
	                init_array($op['config']);
	                $opConfig=$op['config'];
	                if($opConfig['module']=='user'){
	                    init_array($opConfig['user']);
	                    $userNameKey=md5($opConfig['name']);
	                    $userDefVal=$opConfig['user']['default'];
	                    if(!is_empty($userDefVal,true)){
	                        
	                        if($opConfig['user']['global']){
	                            
	                            if(!$configGlobalSetted){
	                                
	                                if(is_empty($config_globals[$appName][$userNameKey],true)){
	                                    $config_globals[$appName][$userNameKey]=$userDefVal;
	                                }
	                            }
	                        }else{
	                            
	                            if(is_empty($appConfig[$userNameKey],true)){
	                                $appConfig[$userNameKey]=$userDefVal;
	                            }
	                        }
	                    }
	                    if($opConfig['user']['required']&&in_array($opConfig['user']['tag'], array('text','select'))){
	                        
	                        $curUserVal='';
	                        if($opConfig['user']['global']){
	                            $curUserVal=$config_globals[$appName][$userNameKey];
	                        }else{
	                            $curUserVal=$appConfig[$userNameKey];
	                        }
	                        if(is_empty($curUserVal,true)){
	                            
	                            $result['msg']=$msg.$appName.'»未填写'.($opConfig['user']['global']?'全局':'').'配置：'.$opConfig['name'];
	                            return $result;
	                        }
	                    }
	                }
	            }
	        }
	        
	        foreach ($ops as $op){
	            $opVal='';
	            init_array($op['config']);
	            $opConfig=$op['config'];
	            $opMsg=sprintf('云端»仓库»接口插件»%s»开发»%s:%s»',$appName,lang('apiapp_op_'.$op['module']),$opConfig['name']);
	            if($op['module']=='variable'){
	                
	                $opModule=$opConfig['module']?$opConfig['module']:'value';
	                $opMethod='_variable_module_'.$opModule;
	                if(method_exists($this,$opMethod)){
	                    if($opModule=='user'){
	                        $opVal=$this->_variable_module_user($opConfig,$appConfig,$config_globals[$appName],$fieldVal,$paramValList);
	                    }elseif($opModule=='value'||$opModule=='extract'){
	                        
	                        $opVal=$this->$opMethod($opConfig,$opVals);
	                    }else{
	                        $opVal=$this->$opMethod($opConfig);
	                    }
	                }
	                
	                
	                init_array($opConfig['func']);
	                $opFunc=$opConfig['func'];
	                if(!empty($opFunc)&&!empty($opFunc['open'])){
	                    
	                    $opFunc=$this->filter_variable_func($opFunc);
	                    foreach ($opFunc['names'] as $fk=>$fv){
	                        $opVals['variable:###']=$opVal;
	                        $funcResult=$this->_op_variable_func($module,$appName,$class_list[$appName],$fv,$opFunc['params'][$fk],$opVals);
	                        if(!$funcResult['success']){
	                            $funcResult['msg']=$opMsg.$funcResult['msg'];
	                            if($isTest){
	                                $funcResult['data']=array('ops'=>$opVals);
	                            }
	                            return $funcResult;
	                            break;
	                        }else{
	                            $opVal=$funcResult['data'];
	                        }
	                    }
	                }
	                $opVals['variable:###']=$opVal;
	            }elseif($op['module']=='request'){
	                
	                $requestResult=$this->_op_request($opConfig,$opVals);
	                if(!$requestResult['success']){
	                    $requestResult['msg']=$opMsg.$requestResult['msg'];
	                    if($isTest){
	                        $requestResult['data']=array('ops'=>$opVals);
	                    }
	                    return $requestResult;
	                    break;
	                }else{
	                    $opVal=$requestResult['data'];
	                }
	            }
	            $opVals[$op['module'].':'.$opConfig['name']]=$opVal;
	        }
	        
	        $content=$this->get_app_content($class_list[$appName]);
	        if($content){
	            
	            $content=$class_list[$appName]->_content;
	            $content=$this->_op_replace_vars($content,$opVals);
	            $content=$this->_op_replace_requests($content,$opVals);
	        }
	        $result['success']=true;
	        if($isTest){
	            
	            $result['data']=array('content'=>$content,'ops'=>$opVals);
	        }else{
	            $result['data']=$content;
	        }
	    }else{
	        if($class_list[$appName]==1){
	            $msg.='不存在插件：';
	        }elseif($class_list[$appName]==2){
	            $msg.='已禁用插件：';
	        }else{
	            $msg.='无效的插件：';
	        }
	        $result['msg']=$msg.$appName;
	    }
	    return $result;
	}
	
	private function _op_variable_func($module,$appName,$appClass,$funcName,$funcParam,$opVals){
	    static $func_param_num_list=array('process'=>array());
	    $param_num_list=&$func_param_num_list[$module];
	    if(is_empty($funcParam,true)){
	        $funcParam=array($opVals['variable:###']);
	    }else{
	        static $txt_list=array();
	        $txtMd5=md5($funcParam);
	        if(!isset($txt_list[$txtMd5])){
	            if(preg_match_all('/[^\r\n]+/',$funcParam,$mtxt)){
	                $txt_list[$txtMd5]=$mtxt[0];
	            }else{
	                $txt_list[$txtMd5]=array();
	            }
	        }
	        $funcParam=$txt_list[$txtMd5];
	        foreach ($funcParam as $k=>$v){
	            $funcParam[$k]=$this->_op_replace_vars($v,$opVals,true);
	        }
	    }
	    $result=return_result('',false,array('data'=>null));
	    if(!empty($funcParam)&&is_array($funcParam)){
	        $options = $this->apiModules[$module];
	        $funcTips='函数:'.$funcName;
	        try {
	            $callback=null;
	            $paramNum=array();
	            if(method_exists($appClass,$funcName)){
	                
	                $callback=array($appClass,$funcName);
	                $appFuncName=$appName.':'.$funcName;
	                if(!isset($param_num_list[$appFuncName])){
	                    
	                    $refMethod=(new \ReflectionClass($appClass))->getMethod($funcName);
	                    $param_num_list[$appFuncName]=array(
	                        'num'=>intval($refMethod->getNumberOfParameters()),
	                        'required'=>intval($refMethod->getNumberOfRequiredParameters()),
	                    );
	                }
	                $paramNum=$param_num_list[$appFuncName];
	            }elseif(function_exists($funcName)){
	                
	                $callback=$funcName;
	                if(!isset($param_num_list[$funcName])){
	                    
	                    $refFunc=new \ReflectionFunction($funcName);
	                    $param_num_list[$funcName]=array(
	                        'num'=>intval($refFunc->getNumberOfParameters()),
	                        'required'=>intval($refFunc->getNumberOfRequiredParameters()),
	                    );
	                }
	                $paramNum=$param_num_list[$funcName];
	            }
	            if($callback){
	                $paramCount=count($funcParam);
	                if($paramCount<$paramNum['required']){
	                    
	                    $result['msg']=$funcTips.'»至少传入'.$paramNum['required'].'个参数';
	                }else{
	                    if($paramCount>$paramNum['num']){
	                        
	                        $funcParam=array_slice($funcParam,0,$paramNum['num']);
	                    }
	                    $result['data']=call_user_func_array($callback, $funcParam);
	                    $result['success']=true;
	                }
	            }else{
	                $result['msg']=$funcTips.='»不存在';
	            }
	        }catch (\Exception $ex){
	            
	            $result['success']=false;
	            $result['msg']=$funcTips.'»运行错误:'.$ex->getMessage();
	        }
	    }
	    return $result;
	}
	
	
	private function _op_replace_vars($data,$opVals,$def=false){
	    if($data){
	        $replace=array();
	        if(preg_match_all('/\[\x{53d8}\x{91cf}\:(.+?)\]/u',$data,$replace)){
	            $replace=$replace[1];
	            $replace=array_unique($replace);
            }
            init_array($replace);
            $replaceData=array();
            if($def){
                $replaceData['###']=$opVals['variable:###'];
            }
            foreach ($replace as $v){
                $replaceData['[变量:'.$v.']']=$opVals['variable:'.$v];
            }
            $data=str_replace(array_keys($replaceData), array_values($replaceData), $data);
        }
        return $data;
	}
	
	private function _op_replace_requests($data,$opVals){
	    if($data){
	        $replace=array();
	        if(preg_match_all('/\[\x{8bf7}\x{6c42}\:(.+?)\]/u',$data,$replace)){
	            $replace=$replace[1];
	            $replace=array_unique($replace);
	        }
	        init_array($replace);
	        $replaceData=array();
	        foreach ($replace as $v){
	            $replaceData['[请求:'.$v.']']=$opVals['request:'.$v];
	        }
	        $data=str_replace(array_keys($replaceData), array_values($replaceData), $data);
	    }
	    return $data;
	}
	
	private function _op_request($config,$varVals){
	    init_array($config);
	    static $retryCur=0;
	    $retryMax=intval($config['retry']);
	    $retryParams=null;
	    if($retryMax>0){
	        
	        $retryParams=array(0=>$config,1=>$varVals);
	    }
	    $result=return_result('',false,array('data'=>null));
	    
	    $url=$this->_op_replace_vars($config['url'],$varVals);
	    if(\util\Funcs::is_right_url($url)){
	        $charset=$config['charset']=='custom'?$config['charset_custom']:$config['charset'];
	        $charset=$charset?:'utf-8';
	        $url=\util\Funcs::url_auto_encode($url,$charset);
	        $curlopts=array();
	        
	        $encode=$config['encode']=='custom'?$config['encode_custom']:$config['encode'];
	        if($encode){
	            $curlopts[CURLOPT_ENCODING]=$encode;
	        }
	        
	        $postData=array();
	        init_array($config['param_names']);
	        init_array($config['param_vals']);
	        foreach ($config['param_names'] as $k=>$v){
                if(empty($v)){
                    continue;
                }
                $postData[$v]=$this->_op_replace_vars($config['param_vals'][$k],$varVals);
	        }
	        
	        $headers=array();
	        init_array($config['header_names']);
	        init_array($config['header_vals']);
	        foreach ($config['header_names'] as $k=>$v){
	            if(empty($v)){
	                continue;
	            }
	            $headers[$v]=$this->_op_replace_vars($config['header_vals'][$k],$varVals);
	        }
	        
	        if($config['content_type']){
	            $headers['content-type']=$config['content_type'];
	        }
	        
	        if($config['type']=='post'){
	            
	            $postData=empty($postData)?true:$postData;
	        }else{
	            
	            $url=\util\Funcs::url_params_charset($url,$postData,$charset);
	            $postData=null;
	        }
	        
	        $config['timeout']=intval($config['timeout']);
	        $config['timeout']=$config['timeout']>0?$config['timeout']:60;
	        
	        $cacheKey='';
	        $config['cache']=intval($config['cache'])*60;
	        if($config['cache']>0){
	            
	            $cacheKey=md5(serialize(array($url,$charset,$encode,$headers,$postData)));
	            $cacheData=\util\Tools::cache_file('api_request',$cacheKey);
	            init_array($cacheData);
	            if(!empty($cacheData)&&(abs(time()-$cacheData['time'])<=$config['cache'])){
	                
	                return $cacheData['data'];
	            }
	        }
	        
	        $htmlInfo=get_html($url,$headers,array('timeout'=>$config['timeout'],'return_body'=>1,'curlopts'=>$curlopts),$charset,$postData,true);
	        init_array($htmlInfo);
	        
	        $config['interval']=intval($config['interval']);
	        if($config['interval']>0){
	            usleep($config['interval']*1000);
	        }
	        if(!empty($htmlInfo['ok'])){
	            
	            $retryCur=0;
	            $result['success']=true;
	            $result['data']=$htmlInfo['html'];
	            if($cacheKey){
	                
	                \util\Tools::cache_file('api_request',$cacheKey,array('time'=>time(),'data'=>$result));
	            }
	        }else{
	            
	            if($retryMax>0&&$retryCur<$retryMax){
	                
	                $retryCur++;
	                
	                $config['wait']=intval($config['wait']);
	                if($config['wait']>0){
	                    sleep($config['wait']);
	                }
	                $result=$this->_op_request($retryParams[0], $retryParams[1]);
	            }else{
	                $retryCur=0;
	                if(is_array($htmlInfo)){
	                    
	                    if($htmlInfo['error']&&is_array($htmlInfo['error'])){
	                        $result['msg']='Curl Error '.$htmlInfo['error']['no'].': '.$htmlInfo['error']['msg'];
	                    }elseif($htmlInfo['html']){
	                        $result['msg']=$htmlInfo['html'];
	                    }
	                }
	            }
	        }
	    }else{
	        $result['msg']='无效网址：'.$url;
	    }
	    return $result;
	}
	
	
	private function _variable_module_value($config,$varVals){
	    $config=$config['value'];
	    init_array($config);
	    return $this->_op_replace_vars($config['value'],$varVals);
	}
	
	private function _variable_module_extract($config,$opVals){
	    $config=$config['extract'];
	    init_array($config);
	    $content=$config['source']?$opVals[$config['source']]:'';
	    $val='';
	    if($content){
	        static $cpatternBase=null;
	        if(!isset($cpatternBase)){
	            $cpatternBase=controller('CpatternBase','event');
	        }
    	    if($config['type']=='rule'){
    	        
    	        $val = $cpatternBase->rule_module_rule_data_get(array(
    	            'rule' => $config['rule'],
    	            'rule_merge' => $config['rule_merge'],
    	            'rule_multi' => $config['rule_multi'],
    	            'rule_multi_str' => $config['rule_multi_str'],
    	            'rule_flags'=>'iu',
    	        ), $content,array(),true);
    	    }elseif($config['type']=='xpath'){
    	        $val = $cpatternBase->rule_module_xpath_data(array(
    	            'xpath' => $config['xpath'],
    	            'xpath_attr' => $config['xpath_attr'],
    	            'xpath_multi' => $config['xpath_multi'],
    	            'xpath_multi_str' => $config['xpath_multi_str'],
    	        ), $content);
    	    }elseif($config['type']=='json'){
    	        $val = $cpatternBase->rule_module_json_data(array(
    	            'json' => $config['json'],
    	            'json_arr' => $config['json_arr'],
    	            'json_arr_implode' => $config['json_arr_implode'],
    	        ), $content);
    	    }
	    }
	    return $val;
	}
	
	private function _variable_module_time($config){
	    $config=$config['time'];
	    init_array($config);
	    $val='';
	    $nowTime=time();
	    $start=empty($config['start'])?$nowTime:strtotime($config['start']);
	    $end=empty($config['end'])?$nowTime:strtotime($config['end']);
	    $time=rand($start,$end);
	    if(empty($config['stamp'])){
	        
	        $fmt=empty($config['format'])?'Y-m-d H:i':
	        str_replace(array('[年]','[月]','[日]','[时]','[分]','[秒]'), array('Y','m','d','H','i','s'), $config['format']);
	        $val=date($fmt,$time);
	    }else{
	        $val=$time;
	    }
	    return $val;
	}
	
	private function _variable_module_num($config){
	    $config=$config['num'];
	    init_array($config);
	    
	    $start=intval($config['start']);
	    $end=intval($config['end']);
	    return rand($start, $end);
	}
	
	private function _variable_module_list($config){
	    $config=$config['list'];
	    init_array($config);
	    static $list=array();
	    $key=md5($config['data']);
	    if(!isset($list[$key])){
	        
	        if(preg_match_all('/[^\r\n]+/',$config['data'],$strList)){
	            $strList=$strList[0];
	        }
	        init_array($strList);
	        $list[$key]=$strList;
	    }
	    $strList=$list[$key];
	    $val='';
	    if(!empty($strList)){
	        if(empty($config['type'])){
	            
	            $randi=array_rand($strList,1);
	            $val=$strList[$randi];
	        }else{
	            static $keyIndexs=array();
	            $isAsc=$config['type']=='asc'?true:false;
	            $endIndex=count($strList)-1;
	            
	            if(isset($keyIndexs[$key])){
	                
	                $curIndex=intval($keyIndexs[$key]);
	            }else{
	                
	                $curIndex=$isAsc?0:$endIndex;
	            }
	            if($isAsc){
	                
	                if($curIndex>$endIndex){
	                    
	                    $curIndex=0;
	                }
	                $val=$strList[$curIndex];
	                $curIndex++;
	            }else{
	                
	                if($curIndex<0){
	                    
	                    $curIndex=$endIndex;
	                }
	                $val=$strList[$curIndex];
	                $curIndex--;
	            }
	            $keyIndexs[$key]=$curIndex;
	        }
	    }
	    return $val;
	}
	
	private function _variable_module_user($config,$appConfig,$globalConfig,$defaultVal,$paramValList){
	    $nameKey=md5($config['name']);
	    $config=$config['user'];
	    init_array($config);
	    init_array($appConfig);
	    init_array($globalConfig);
	    if($config['global']){
	        
	        return $globalConfig[$nameKey];
	    }else{
	        
	        $configStr=$appConfig[$nameKey];
	        $fieldRule='/\[\x{5b57}\x{6bb5}\:(.+?)\]/u';
	        $configStr=\util\Funcs::txt_replace_params(false, false, $configStr, $defaultVal, $fieldRule, $paramValList);
	        return $configStr;
	    }
	}
}

?>