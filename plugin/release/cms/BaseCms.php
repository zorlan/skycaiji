<?php
/* cms发布设置
 * 自定义cmsApp要求
 * 类名必须驼峰命名法不能用下划线
 */
namespace plugin\release\cms;

use skycaiji\admin\model\DbCommon;
abstract class BaseCms extends \skycaiji\admin\event\ReleaseBase{
	public $release;//发布对象数据
	public $releConfig;//发布配置
	public $cmsDb;//cms数据库配置
	protected $db;//数据库对象
	public $cmsApp;//当前Cms app
	public $cmsPath;//当前Cms路径
	public $_params;//发布设置cms绑定参数
	protected $paramHtmlList=array();//参数对应的html代码
	public function __construct(){
		parent::__construct();
		if(config('app_debug')!=true){
			config('exception_tmpl',config('app_path').'/public/release_exception.tpl');//定义cms错误模板，ajax出错时方便显示
		}
	}
	public function init($cmsPath=null,$release=null){
		$cmsDb=array();//cms程序数据库配置
		if(empty($release)){
			$release=array();
		}
		if(!empty($cmsPath)){
			//通过cms名和路径加载配置
			$release['config']['cms']['name']=$this->cms_name($cmsPath);
			$release['config']['cms']['path']=$cmsPath;
		}
		if(!empty($release)){
			//通过已入库的发布设置加载配置
			$releConfig=$release['config'];
			if(strpos($releConfig['cms']['path'], '@')!==false){
				//路径中指定了cms
				list($cmsPath,$cmsPathName)=explode('@', $releConfig['cms']['path']);//指定程序名
				$releConfig['cms']['path']=$cmsPath;//换成有效的路径
				$releConfig['cms']['name']=$cmsPathName;//重新设置cms
			}
			$cmsDb=$this->cmsDb($releConfig['cms']['name'], $releConfig['cms']['path']);
			$this->releConfig=$releConfig;//发布数据库配置
			$this->release=$release;
			$this->cmsPath=$release['config']['cms']['path'];
		}else{
			exception('发布错误：配置加载失败！');
		}
		if(empty($cmsDb)||empty($cmsDb['db_name'])){
			//数据库不为空情况下需判断db_name
			exception('发布错误：没有数据库配置');
		}
		$cmsDb['db_type']=empty($cmsDb['db_type'])?'mysql':strtolower($cmsDb['db_type']);
		$cmsDb['db_port']=$cmsDb['db_port']>0?$cmsDb['db_port']:3306;//设置默认端口
		$cmsDb['fields_strict']=false;//允许字段不存在
		
		$this->cmsDb=$cmsDb;//cms程序配置
		//实例化数据库
		try {
			$mdb=new DbCommon($cmsDb);
			$this->db=$mdb->db();
		}catch (\Exception $ex){
			exception('发布错误：'.$ex->getMessage());
		}
		$this->cmsApp=get_class($this);//类名
		$this->init_extend();//自定义执行操作
	}
	/*扩展初始化*/
	public function init_extend(){}
	
	/**
	 * 参照thinkphp5数据库操作
	 * @return Ambigous <\think\db\Query, NULL>
	 */
	public function db(){
		return $this->db;
	}
	/**
	 * 导出数据
	 * @param unknown $collFields 采集到的字段数据
	 */
	public function runExport($collFields){
		//数据库编码
		$dbCharset=strtolower($this->cmsDb['db_charset']);
		if(empty($dbCharset)||$dbCharset=='utf-8'||$dbCharset=='utf8'){
			//不转码
			$dbCharset=null;
		}
		//转换cms参数
		$cmsParams=array();
		foreach ($this->releConfig['cms_app']['param'] as $cmsParam=>$paramVal){
			if(strcasecmp('custom:',$paramVal)==0){
				//自定义
				$paramVal=$this->releConfig['cms_app']['custom'][$cmsParam];
			}elseif(preg_match('/^field\:(.+)$/i', $paramVal,$collField)){
				//采集器字段
				$paramVal=$this->get_field_val($collFields[$collField[1]]);
			}
			if(!empty($dbCharset)){
				//转码
				$paramVal=$this->utf8_to_charset($dbCharset, $paramVal);
			}
			
			$cmsParams[$cmsParam]=$paramVal;
		}

		if(!empty($this->_params)){
			//验证参数
			$errorMsg=false;//错误信息
			foreach ($this->_params as $pkey=>$pval){
				if($pval['require']){
					//必填项
					if(empty($cmsParams[$pkey])){
						$errorMsg='未获取到“'.$pval['name'].'”';
						break;
					}
				}
			}
			if(!empty($errorMsg)){
				return array('id'=>0,'error'=>$errorMsg);//返回错误信息
			}
		}
		return $this->runImport($cmsParams);
	}
	
	/**
	 * 导入数据
	 * @param unknown $params cms参数
	 */
	public abstract function runImport($params);
	
	public function runTest($collFields){
		//事务回滚仅对InnoDB类型表起作用
		$this->db()->startTrans();//开启事务
		$this->runExport($collFields);
		$this->db()->rollback();//回滚事务
	}
	/*运行绑定*/
	public function runBind(){}
	/*运行检测*/
	public function runCheck($config){
		//设置了参数是进行验证
		if(empty($this->_params)){
			return;
		}
		foreach ($this->_params as $pkey=>$pval){
			//必填参数
			if($pval['require']){
				if($config['param'][$pkey]==''){
					//没有值
					$this->error($pval['name'].'不能为空');
				}elseif('custom:'==$config['param'][$pkey]){
					//自定义
					if(!isset($config['custom'][$pkey])||preg_match('/^\s*$/', $config['custom'][$pkey])){
						//自定义不能为空
						$this->error($pval['name'].'不能为空');
					}
				}
			}
		}
	}
	
	/*显示绑定模板*/
	public function tplBind(){
		if(!empty($this->_params)){
			//设置了参数
			$paramTags=array();
			foreach ($this->_params as $paramKey=>$paramVal){
				$paramTags[$paramKey]=$this->convert_param2html($paramKey, $paramVal);
			}
			$this->assign('paramTags',$paramTags);
			$this->assign('_params',$this->_params);
			$tpl=$this->fetch(config('plugin_path').'/release/view/cms/BaseCms.html');
			return $tpl->getContent();
		}else{
			$sltCollField=$this->param_option_fields();
			$this->assign('sltCollField',$sltCollField);
			//没有参数调用模板，复杂需求下可使用模板
			$tpl=$this->fetch(config('plugin_path').'/release/view/cms/'.$this->cmsApp.'.html');
			return $tpl->getContent();
		}
	}
	
	/*获取cms配置，可在app中自定义该方法*/
	public function cmsDb($cmsName,$cmsPath){
		$cmsDb=array();
		if(!empty($cmsName)&&!empty($cmsPath)){
			$method='cms_db_'.$cmsName;
			if(method_exists($this, $method)){
				$cmsDb=$this->$method($cmsPath);
			}
		}
		return $cmsDb;
	}
	/*引入文件必须用include，include_once在多个实例化后会失效*/
	public function cms_db_discuz($cmsPath){
		$dbFile=realpath($cmsPath.'/config/config_global.php');
		//转换成thinkphp数据库配置
		include $dbFile;//导入本地cms配置文件
		$_config=$_config?$_config:array();
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $_config['db'][$_config['server']['id']]['dbuser'],
			'db_pwd'   => $_config['db'][$_config['server']['id']]['dbpw'],
			'db_host'  => $_config['db'][$_config['server']['id']]['dbhost'],
			'db_port'  => 3306,
			'db_name'  => $_config['db'][$_config['server']['id']]['dbname'],
			'db_charset'  => $_config['db'][$_config['server']['id']]['dbcharset'],
			'db_prefix'  => $_config['db'][$_config['server']['id']]['tablepre']
		);
		return $cmsDb;
	}
	public function cms_db_wordpress($cmsPath){
		$dbFile=realpath($cmsPath.'/wp-config.php');
		$configTxt=file_get_contents($dbFile);
		$cmsDb=array(
			'db_user'  => 'DB_USER',
			'db_pwd'   => 'DB_PASSWORD',
			'db_host'  => 'DB_HOST',
			'db_name'  => 'DB_NAME',
			'db_charset'  => 'DB_CHARSET',
		);
		//匹配定义的参数
		foreach ($cmsDb as $dbKey=>$dbDefine){
			if(preg_match('/define\s*\(\s*[\'\"]\s*'.$dbDefine.'\s*[\'\"]\s*\,\s*[\'\"](?P<val>[^\'\"]*?)[\'\"]\s*\)/i', $configTxt,$dbDefineVal)){
				$cmsDb[$dbKey]=$dbDefineVal['val'];	
			}else{
				$cmsDb[$dbKey]='';
			}
		}
		//匹配表前缀
		if(preg_match('/\$table_prefix\s*=\s*[\'\"](?P<val>[^\'\"]*?)[\'\"]/i', $configTxt,$tablePre)){
			$cmsDb['db_prefix']=$tablePre['val'];
		}else{
			$cmsDb['db_prefix']='';
		}
		$cmsDb['db_type']='mysql';
		$cmsDb['db_port']=3306;
		return $cmsDb;
	}
	public function cms_db_empirecms($cmsPath){
		$dbFile=realpath($cmsPath.'/e/config/config.php');
		define('InEmpireCMS', true);//必须定义才能引入配置
		include $dbFile;
		$ecms_config=$ecms_config?$ecms_config:array();
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $ecms_config['db']['dbusername'],
			'db_pwd'   => $ecms_config['db']['dbpassword'],
			'db_host'  => $ecms_config['db']['dbserver'],
			'db_port'  => $ecms_config['db']['dbport'],
			'db_name'  => $ecms_config['db']['dbname'],
			'db_charset'  => $ecms_config['db']['setchar'],
			'db_prefix'  => $ecms_config['db']['dbtbpre']
		);
		return $cmsDb;
	}
	public function cms_db_dedecms($cmsPath){
		$dbFile=realpath($cmsPath.'/data/common.inc.php');
		$cfg_dbuser=null;$cfg_dbpwd=null;$cfg_dbhost=null;$cfg_dbname=null;$cfg_db_language=null;$cfg_dbprefix=null;
		include $dbFile;
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $cfg_dbuser,
			'db_pwd'   => $cfg_dbpwd,
			'db_host'  => $cfg_dbhost,
			'db_port'  => 3306,
			'db_name'  => $cfg_dbname,
			'db_charset'  => $cfg_db_language,
			'db_prefix'  => $cfg_dbprefix
		);
		return $cmsDb;
	}
	public function cms_db_phpcms($cmsPath){
		$dbFile=realpath($cmsPath.'/caches/configs/database.php');
		$config=include $dbFile;
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $config['default']['username'],
			'db_pwd'   => $config['default']['password'],
			'db_host'  => $config['default']['hostname'],
			'db_port'  => $config['default']['port'],
			'db_name'  => $config['default']['database'],
			'db_charset'  => $config['default']['charset'],
			'db_prefix'  => $config['default']['tablepre']
		);
		return $cmsDb;
	}
	public function cms_db_metinfo($cmsPath){
		$dbFile=realpath($cmsPath.'/config/config_db.php');
		$config=parse_ini_file($dbFile);
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $config['con_db_id'],
			'db_pwd'   => $config['con_db_pass'],
			'db_host'  => $config['con_db_host'],
			'db_port'  => 3306,
			'db_name'  => $config['con_db_name'],
			'db_charset'  => $config['db_charset'],
			'db_prefix'  => $config['tablepre']
		);
		return $cmsDb;
	}
	/*获取cms名称*/
	public function cms_name($cmsPath){
		$acms=controller('admin/Rcms','event');
		return $acms->cms_name($cmsPath);//cms名称
	}
	/*转换参数成html标签*/
	public function convert_param2html($paramKey,$paramVal){
		if(!isset($this->paramHtmlList[$paramKey])){
			$html='';
			$tag=strtolower($paramVal['tag']);//html标签
			$func=null;//函数
			$options=array();//选项
			if(preg_match('/^function\:(.*)$/i', $paramVal['option'],$func)){
				//函数名
				$func=trim($func[1]);
			}elseif(!empty($paramVal['option'])){
				if(is_string($paramVal['option'])){
					//选项转换成数组
					$options=explode('|', $paramVal['option']);
					$optionList=array();
					foreach ($options as $option){
						if(strpos($option, '=')!==false){
							//有=号
							list($optionKey,$optionVal)=explode('=', $option);
							$optionList[$optionKey]=$optionVal;
						}
					}
					$options=$optionList;
				}elseif(is_array($paramVal['option'])){
					$options=$paramVal['option'];
				}
			}
			if('select'==$tag){
				$html.='<select name="_cms_app_param_" class="form-control"><option value="">不选择</option>';
				if(!empty($func)){
					//调用函数
					if(method_exists($this, $func)){
						$funcData=$this->$func();
						if(is_array($funcData)){
							//返回的是数组
							foreach ($funcData as $fdk=>$fdv){
								$html.="<option value=\"{$fdk}\">{$fdv}</option>";
							}
						}else{
							//返回的是字符串
							$html.=$funcData;
						}
					}
				}elseif(!empty($options)){
					//选项
					foreach ($options as $optionKey=>$optionVal){
						$html.="<option value=\"{$optionKey}\">{$optionVal}</option>";
					}
				}
				$html.='<option value="custom:">自定义内容</option></select>'
						.'<input class="form-control" style="display:none;" name="cms_app[custom]['.$paramKey.']" />';
			}elseif(in_array($tag,array('input','text','number'))){
				$html.='<input type="'.($tag=='input'?'text':$tag).'" name="_cms_app_param_" class="form-control" value="" />';
			}elseif('radio'==$tag){
				$html.='<label class="radio-inline"><input type="radio" name="_cms_app_param_" value="1" /> 是</label>';
				$html.='<label class="radio-inline"><input type="radio" name="_cms_app_param_" value="0" /> 否</label>';
			}elseif('textarea'==$tag){
				$html.='<textarea name="_cms_app_param_" class="form-control"></textarea>';
			}
			
			$this->paramHtmlList[$paramKey]=str_replace('_cms_app_param_', 'cms_app[param]['.$paramKey.']', $html);
		}
		return $this->paramHtmlList[$paramKey];
	}
	/*采集器字段选项*/
	public function param_option_fields(){
		if(empty($this->release)){
			return null;
		}
		$mtask=model('Task');
		$taskData=$mtask->getById($this->release['task_id']);
		if(empty($taskData)){
			return null;
		}
		$acms=controller('admin/Rcms','event');
		$collFields=$acms->get_coll_fields($taskData['id'],$taskData['module']);
		$sltCollField='';
		foreach($collFields as $collField){
			$sltCollField.="<option value=\"field:{$collField}\">采集字段：{$collField}</option>";
		}
		return $sltCollField;
	}
}
?>