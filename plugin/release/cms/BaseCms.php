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
	public function cms_db_emlog($cmsPath){
		$configFile=realpath($cmsPath.'/config.php');
		$cmsDb=array('db_type'=>'mysql','db_charset'=>'utf8');
		if(file_exists($configFile)){
			$configFile=file_get_contents($configFile);
			if($configFile){
				$dbKeys=array('db_host'=>'DB_HOST','db_user'=>'DB_USER','db_pwd'=>'DB_PASSWD','db_prefix'=>'DB_PREFIX','db_name'=>'DB_NAME');
				foreach($dbKeys as $k=>$v){
					if(preg_match('/define\s*\(\s*[\'\"]'.$v.'[\'\"]\s*,\s*[\'\"](?P<val>[^\'\"]+)[\'\"]\s*\)/i',$configFile,$val)){
						$cmsDb[$k]=$val['val'];
					}
				}
			}
		}
		return $cmsDb;
	}
	public function cms_db_drupal($cmsPath){
		$dbFile=realpath($cmsPath.'/sites/default/settings.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=file_get_contents($dbFile);
			if(preg_match('/\$databases\s*\=\s*array[\s\S]+?\)\s*\;/i',$dbFile,$dbFile)){
				$dbFile=$dbFile[0];
				$dbParams=array('db_host'=>'host','db_user'=>'username','db_pwd'=>'password','db_port'=>'port','db_name'=>'database','db_prefix'=>'prefix');
				foreach ($dbParams as $k=>$v){
					if(preg_match('/\''.$v.'\'\s*\=\s*\>\s*[\'\"](?P<val>.*)[\'\"]/i',$dbFile,$dbMatch)){
						$cmsDb[$k]=$dbMatch['val'];
					}
				}
				$cmsDb['db_charset']='utf8';
			}
		}
		return $cmsDb;
	}
	public function cms_db_chanzhi($cmsPath){
		$dbFile=realpath($cmsPath.'/../system/config/my.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=file_get_contents($dbFile);
			$dbParams=array('db_host'=>'host','db_user'=>'user','db_pwd'=>'password','db_port'=>'port','db_name'=>'name','db_prefix'=>'prefix');
			foreach ($dbParams as $k=>$v){
				if(preg_match('/\$config->db->'.$v.'\s*=\s*[\'\"](?P<val>.*)[\'\"]/i',$dbFile,$dbMatch)){
					$cmsDb[$k]=$dbMatch['val'];
				}
			}
			$cmsDb['db_charset']='utf8';
		}
		return $cmsDb;
	}
	public function cms_db_feifei($cmsPath){
		$dbFile=realpath($cmsPath.'/Runtime/Conf/config.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=include $dbFile;
			if(is_array($dbFile)){
				$cmsDb['db_host']=$dbFile['db_host'];
				$cmsDb['db_user']=$dbFile['db_user'];
				$cmsDb['db_pwd']=$dbFile['db_pwd'];
				$cmsDb['db_charset']=$dbFile['db_charset'];
				$cmsDb['db_port']=$dbFile['db_port'];
				$cmsDb['db_name']=$dbFile['db_name'];
				$cmsDb['db_prefix']=$dbFile['db_prefix'];
			}
		}
		return $cmsDb;
	}
	public function cms_db_hybbs($cmsPath){
		$dbFile=realpath($cmsPath.'/Conf/config.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=include $dbFile;
			if(is_array($dbFile)){
				$cmsDb['db_host']=$dbFile['SQL_IP'];
				$cmsDb['db_user']=$dbFile['SQL_USER'];
				$cmsDb['db_pwd']=$dbFile['SQL_PASS'];
				$cmsDb['db_charset']=$dbFile['SQL_CHARSET'];
				$cmsDb['db_port']=$dbFile['SQL_PORT'];
				$cmsDb['db_name']=$dbFile['SQL_NAME'];
				$cmsDb['db_prefix']=$dbFile['SQL_PREFIX'];
	
				$this->siteurl=rtrim($dbFile['DOMAIN_NAME'],'\/\\').'/';
			}
		}
		return $cmsDb;
	}
	public function cms_db_sdcms($cmsPath){
		$dbFile=realpath($cmsPath.'/config.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=include $dbFile;
			if(is_array($dbFile)){
				$dbFile=$dbFile['DEFAULT_DB'];
				$cmsDb['db_host']=$dbFile['DB_HOST'];
				$cmsDb['db_user']=$dbFile['DB_USER'];
				$cmsDb['db_pwd']=$dbFile['DB_PASS'];
				$cmsDb['db_port']=$dbFile['DB_PORT'];
				$cmsDb['db_name']=$dbFile['DB_BASE'];
				$cmsDb['db_prefix']=$dbFile['DB_PREFIX'];
			}
		}
		return $cmsDb;
	}
	public function cms_db_catfish($cmsPath){
		$dbFile=realpath($cmsPath.'/application/database.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=include $dbFile;
			if(is_array($dbFile)){
				$cmsDb['db_host']=$dbFile['hostname'];
				$cmsDb['db_user']=$dbFile['username'];
				$cmsDb['db_pwd']=$dbFile['password'];
				$cmsDb['db_charset']=$dbFile['charset'];
				$cmsDb['db_port']=$dbFile['hostport'];
				$cmsDb['db_name']=$dbFile['database'];
				$cmsDb['db_prefix']=$dbFile['prefix'];
			}
		}
		return $cmsDb;
	}
	public function cms_db_pboot($cmsPath){
		$dbFile=realpath($cmsPath.'/config/database.php');
		$cmsDb=array();
		if(file_exists($dbFile)){
			$dbFile=include $dbFile;
			$dbFile=$dbFile['database'];
			if(is_array($dbFile)){
				//使用sqlite必须开启pdo_sqlite
				$cmsDb['db_type']=stripos($dbFile['type'], 'sqlite')!==false?'sqlite':'mysql';
				$cmsDb['db_name']=$cmsDb['db_type']=='sqlite'?($cmsPath.$dbFile['dbname']):$dbFile['dbname'];
				$cmsDb['db_host']=$dbFile['host'];
				$cmsDb['db_user']=$dbFile['user'];
				$cmsDb['db_pwd']=$dbFile['passwd'];
				$cmsDb['db_charset']='utf8';
				$cmsDb['db_port']=$dbFile['port'];
				$cmsDb['db_prefix']='ay_';//固定的前缀
			}
		}
		return $cmsDb;
	}
	public function cms_db_typecho($cmsPath){
		$configFile=realpath($cmsPath.'/config.inc.php');
		$cmsDb=array();
		if(file_exists($configFile)){
			$configFile=file_get_contents($configFile);
			if($configFile){
				if(preg_match('/\s*new\s*Typecho_Db\s*\([^,\(\)]+,\s*[\'\"](?P<pre>[^\'\"]+)[\'\"]/i',$configFile,$prefix)){
					//匹配前缀
					$cmsDb['db_prefix']=$prefix['pre'];
				}
				if(preg_match('/\$db->addServer\s*\((?P<db>[\s\S]+?)\)\s*,/i',$configFile,$db)){
					//匹配数组
					$db=$db['db'];
					$dbKeys=array('db_host'=>'host','db_user'=>'user','db_pwd'=>'password','db_charset'=>'charset','db_port'=>'port','db_name'=>'database');
					foreach($dbKeys as $k=>$v){
						if(preg_match('/[\'\"]'.$v.'[\'\"]\s*=\s*>\s*[\'\"](?P<val>[^\'\"]+)[\'\"]/i',$db,$val)){
							$cmsDb[$k]=$val['val'];
						}
					}
				}
			}
		}
		return $cmsDb;
	}
	public function cms_db_maccms($cmsPath){
		$config=include $cmsPath.'/application/database.php';
		$cmsDb=array(
			'db_type'  => $config['type'],
			'db_user'  => $config['username'],
			'db_pwd'   => $config['password'],
			'db_host'  => $config['hostname'],
			'db_port'  => $config['hostport'],
			'db_name'  => $config['database'],
			'db_charset'  => $config['charset'],
			'db_prefix'  => $config['prefix']
		);
		return $cmsDb;
	}
	public function cms_db_yzmcms($cmsPath){
		$config=include $cmsPath.'/common/config/config.php';
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $config['db_user'],
			'db_pwd'   => $config['db_pwd'],
			'db_host'  => $config['db_host'],
			'db_port'  => $config['db_port'],
			'db_name'  => $config['db_name'],
			'db_charset'  => 'utf8',
			'db_prefix'  => $config['db_prefix']
		);
		return $cmsDb;
	}
	public function cms_db_xiunobbs($cmsPath){
		$dbFile=realpath($cmsPath.'/conf/conf.php');
		//转换成thinkphp数据库配置
		$config=include $dbFile;
	
		$config=$config['db'][$config['db']['type']]['master'];
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $config['user'],
			'db_pwd'   => $config['password'],
			'db_host'  => $config['host'],
			'db_port'  => 3306,
			'db_name'  => $config['name'],
			'db_charset'  => $config['charset'],
			'db_prefix'  => $config['tablepre']
		);
	
		return $cmsDb;
	}
	public function cms_db_hadsky($cmsPath){
		$dbFile=realpath($cmsPath.'/puyuetian/mysql/config.php');
		//转换成thinkphp数据库配置
		$_G=null;
		include $dbFile;
		$config=$_G['MYSQL'];
		if(preg_match('/set names (\w+)/i', $config['CHARSET'],$charset)){
			$config['CHARSET']=$charset[1];
		}else{
			$config['CHARSET']='utf8';
		}
	
		$cmsDb=array(
			'db_type'  => 'mysql',
			'db_user'  => $config['USERNAME'],
			'db_pwd'   => $config['PASSWORD'],
			'db_host'  => $config['LOCATION'],
			'db_port'  => 3306,
			'db_name'  => $config['DATABASE'],
			'db_charset'  => $config['CHARSET'],
			'db_prefix'  => $config['PREFIX']
		);
	
		return $cmsDb;
	}
	public function cms_db_mipcms($cmsPath){
		$dbFile=realpath($cmsPath.'/app/database.php');
		//转换成thinkphp数据库配置
		$config=include $dbFile;
	
		$cmsDb=array(
			'db_type'  => $config['type'],
			'db_user'  => $config['username'],
			'db_pwd'   => $config['password'],
			'db_host'  => $config['hostname'],
			'db_port'  => $config['hostport'],
			'db_name'  => $config['database'],
			'db_charset'  => $config['charset'],
			'db_prefix'  => $config['prefix']
		);
	
		return $cmsDb;
	}
	public function cms_db_zblog($cmsPath){
		$dbFile=realpath($cmsPath.'/zb_users/c_option.php');
		//转换成thinkphp数据库配置
		$config=include $dbFile;
		$cmsDb=array(
			'db_type'  => $config['ZC_DATABASE_TYPE'],
			'db_user'  => $config['ZC_MYSQL_USERNAME'],
			'db_pwd'   => $config['ZC_MYSQL_PASSWORD'],
			'db_host'  => $config['ZC_MYSQL_SERVER'],
			'db_port'  => $config['ZC_MYSQL_PORT'],
			'db_name'  => $config['ZC_MYSQL_NAME'],
			'db_charset'  => $config['ZC_MYSQL_CHARSET'],
			'db_prefix'  => $config['ZC_MYSQL_PRE']
		);
		return $cmsDb;
	}
	public function cms_db_twcms($cmsPath){
		$dbFile=realpath($cmsPath.'/twcms/config/config.inc.php');
		//转换成thinkphp数据库配置
		include_once $dbFile;//导入本地cms配置文件
		$config=$_ENV['_config'][db];
		$cmsDb=array(
			'db_type'  => $config['type'],
			'db_user'  => $config['master']['user'],
			'db_pwd'   => $config['master']['password'],
			'db_host'  => $config['master']['host'],
			'db_port'  => 3306,
			'db_name'  => $config['master']['name'],
			'db_charset'  => $config['master']['charset'],
			'db_prefix'  => $config['master']['tablepre']
		);
		return $cmsDb;
	}
	public function cms_db_destoon($cmsPath){
		define('IN_DESTOON',true);
		$dbFile=realpath($cmsPath.'/config.inc.php');
		//转换成thinkphp数据库配置
		$CFG=null;
		include $dbFile;//导入本地cms配置文件
		$cmsDb=array(
			'db_type'  => $CFG['database'],
			'db_user'  => $CFG['db_user'],
			'db_pwd'   => $CFG['db_pass'],
			'db_host'  => $CFG['db_host'],
			'db_port'  => 3306,
			'db_name'  => $CFG['db_name'],
			'db_charset'  => $CFG['db_charset'],
			'db_prefix'  => $CFG['tb_pre']
		);
		$this->siteurl=$CFG['url'];
		return $cmsDb;
	}
}
?>