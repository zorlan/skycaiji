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

namespace skycaiji\admin\controller;

use plugin;
use skycaiji\admin\model\FuncApp;
class Develop extends BaseController {
	public static $typeList = array (
		'number' => '数字(number)',
		'text' => '字符(text)',
		'radio' => '开关(radio)',
		'textarea' => '文本(textarea)',
		'select_coll' => '选择采集字段(select)',
		'select_val' => '选择固定值(select)',
		'select_func' => '选择函数返回值(select)'
	);
	
	public static $packTypes=array(
		'nav'=>'后台导航'
	);
	
	public static $frameworks=array(
		'thinkphp'=>array('6.0','5.1','5.0'),
		'laravel'=>array('5.5','5.1'),
	);
	
	public function pluginAction(){
		$this->redirect('develop/releaseCms');
	}
	public function releaseCmsAction(){
		$mapp=model('ReleaseApp');
		if(request()->isPost()){
			$is_edit=input('edit');
			
			$name=input('name');
			if(empty($name)){
				$this->error('请输入发布插件名称');
			}
			if(!$is_edit){
				
				$cms_name=input('cms_name');
				$cms_name_custom=input('cms_name_custom');
				$identifier=input('identifier');
				$copyright=input('copyright');

				if(empty($cms_name)){
					$this->error('请选择CMS程序');
				}elseif($cms_name=='custom'){
					if(empty($cms_name_custom)){
						$this->error('请输入CMS程序名');
					}else{
						$cms_name=$cms_name_custom;
					}
				}
				
				if(!preg_match('/^[a-z][a-z0-9]*$/i', $cms_name)){
					$this->error('cms程序名必须由字母或数字组成且首位不能为数字！');
				}
				if(empty($identifier)){
					$this->error('请输入功能标识');
				}elseif(!preg_match('/^[a-z][a-z0-9]*$/i', $identifier)){
					$this->error('功能标识必须由字母或数字组成且首位不能为数字！');
				}
				if(empty($copyright)){
					$this->error('请输入作者版权');
				}elseif(!preg_match('/^[a-z][a-z0-9]*$/i', $copyright)){
					$this->error('作者版权必须由字母或数字组成且首位不能为数字！');
				}
				
				$appName=ucfirst(strtolower($cms_name)).ucfirst(strtolower($identifier)).ucfirst(strtolower($copyright));
			}else{
				
				$appName=ucfirst(input('app'));
			}
			
			$params=input('params/a',array());
			if(empty($params)||!is_array($params)){
				$this->error('请添加参数');
			}
			foreach ($params as $k=>$v){
				$params[$k]=json_decode(url_b64decode($v),true);
			}
			
			$this->_create_cms_app(array('name'=>$name,'app'=>$appName), $params,$is_edit);
			
		}else{
			$appName=input('app','');
			$appName=ucfirst($appName);
			$config=array();
			
			if($appName){
				$cmsData=$mapp->where(array('module'=>'cms','app'=>$appName))->find();
				
				if(!empty($cmsData)){
					
					$config['name']=$cmsData['name'];
					$config['app']=$appName;
					if(preg_match('/^([A-Z][a-z0-9]*)([A-Z][a-z0-9]*)([A-Z][a-z0-9]*)$/', $appName,$appInfo)){
						
						$config['is_edit']=true;
						$config['cms_name']=strtolower($appInfo[1]);
						$config['identifier']=strtolower($appInfo[2]);
						$config['copyright']=strtolower($appInfo[3]);
						
						$config['app_file']=realpath($mapp->appFileName($appName,'cms'));
					}
					$config['params']=array();
					
					$cmsClass=null;
					$is_old_plugin=false;
					try {
						if($mapp->appFileExists($appName,'cms')){
							
							$cmsClass=$mapp->appImportClass($appName,'cms');
						}elseif($mapp->oldFileExists($appName,'cms')){
							
							$is_old_plugin=true;
							$cmsClass=$mapp->oldImportClass($appName,'cms');
						}
					}catch (\Exception $ex){
						$cmsClass=null;
						$this->error($ex->getMessage());
					}
					if($cmsClass&&property_exists($cmsClass,'_params')&&is_array($cmsClass->_params)){
						foreach ($cmsClass->_params as $k=>$v){
							$param=array(
								'key'=>$k,
								'require'=>intval($v['require']),
								'name'=>$v['name'],
							);
							if($v['tag']=='select'){
								if(is_array($v['option'])){
									
									$param['type']='select_val';
									$param['select_val']='';
									foreach ($v['option'] as $vk=>$vv){
										$param['select_val'].=$vk.'='.$vv."\r\n";
									}
								}elseif($v['option']=='function:param_option_fields'){
									
									$param['type']='select_coll';
								}elseif(preg_match('/^function:(.+)$/', $v['option'],$select_func)){
									
									$param['type']='select_func';
									$param['select_func']=$select_func[1];
								}
							}else{
								$param['type']=$v['tag'];
							}
							$param['type_name']=self::$typeList[$param['type']];
							$config['params'][]=$param;
						}
					}
					
					if(empty($cmsClass)){
					    
					    $this->assign('noClass',1);
					}
				}
			}
			$this->set_html_tags(
			    '开发CMS发布插件',
			    '开发CMS发布插件 <small><a href="https://www.skycaiji.com/manual/doc/cms" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>',
			    breadcrumb(array(array('url'=>url('mystore/releaseApp'),'title'=>'CMS发布插件'),array('url'=>url('develop/releaseCms'),'title'=>'开发CMS发布插件')))
			);
			$this->assign('appName',$appName);
			$this->assign('config',$config);
			$this->assign('is_old_plugin',$is_old_plugin);
			return $this->fetch('releaseCms');
		}
	}
	/*添加参数*/
	public function cmsAddParamAction(){
		if(request()->isPost()){
		    $param=input('param/a',array());
			$param=\util\Funcs::array_array_map('trim', $param);
			if(empty($param['key'])){
				$this->error('请输入变量名');
			}
			if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/',$param['key'])){
				$this->error('变量名必须符合php命名规范');
			}
			if(empty($param['name'])){
				$this->error('请输入参数名称');
			}
			if(empty($param['type'])){
				$this->error('请选择参数类型');
			}
			if($param['type']=='select_func'){
				
				if(!preg_match('/^param_option_[a-zA-Z0-9_]+$/', $param['select_func'])){
					$this->error('函数名必须以param_option_开头且符合命名规范');
				}
			}elseif($param['type']=='select_val'){
				
				if(empty($param['select_val'])){
					$this->error('请输入选项值');
				}
			}
			$param['param_json']=json_encode($param);
			$param['type_name']=self::$typeList[$param['type']];
			$this->success('',null,$param);
		}else{
    		$objid=input('objid');
    		$param=input('param','','url_b64decode');
    		$param=$param?json_decode($param,true):'';

			$this->assign('objid',$objid);
			$this->assign('param',$param);
			$this->assign('typeList',self::$typeList);
			return $this->fetch('cmsAddParam');
		}
	}
	/*创建cms发布插件*/
	private function _create_cms_app($appData,$params,$is_edit=false){
		if(!preg_match('/^[a-z][a-z0-9]*$/i', $appData['app'])){
			$this->error('插件名错误！');
		}
		$appData['app']=ucfirst($appData['app']);
		
		$mapp=model('ReleaseApp');
		$cmsData=$mapp->where(array('module'=>'cms','app'=>$appData['app']))->find();
		
		if(!$is_edit&&!empty($cmsData)){
			
			$this->error('抱歉，已存在'.$appData['app'].'插件');
		}
		
		$_params=array();
		$newFuncs=array();

		$params=empty($params)?array():$params;
		foreach ($params as $k=>$v){
			$pkey=$v['key'];
			$_params[$pkey]=array(
				'name' => $v['name'],
				'require'=>intval($v['require'])
			);
			
			$v['type']=strtolower($v['type']);
			if(strpos($v['type'], 'select_')===0){
				
				$_params[$pkey]['tag']='select';
			}else{
				$_params[$pkey]['tag']=$v['type'];
			}
			
			if($v['type']=='select_coll'){
				
				$_params[$pkey]['option']='function:param_option_fields';
			}elseif($v['type']=='select_func'){
				
				$_params[$pkey]['option']='function:'.$v['select_func'];
				$newFuncs[$v['select_func']]=$v['select_func'];
			}elseif($v['type']=='select_val'){
				
				if(preg_match_all('/[^\r\n]+/', $v['select_val'],$select_val)){
					$_params[$pkey]['option']=array();
					foreach ($select_val[0] as $slv){
						if(strpos($slv,'=')!==false){
							
							list($slv_k,$slv_v)=explode('=', $slv);
							if(is_null($slv_k)){
								$slv_k=$slv_v;
							}
							$_params[$pkey]['option'][$slv_k]=$slv_v;
						}else{
							
							$_params[$pkey]['option'][$slv]=$slv;
						}
					}
				}
			}
		}
		
		$cmsClass=null;
		$is_old_plugin=false;
		try {
			if($mapp->appFileExists($appData['app'],'cms')){
				
				$cmsClass=$mapp->appImportClass($appData['app'],'cms');
			}elseif($mapp->oldFileExists($appData['app'],'cms')){
				
				$is_old_plugin=true;
				$cmsClass=$mapp->oldImportClass($appData['app'],'cms');
			}
		}catch (\Exception $ex){
			$cmsClass=null;
			$this->error($ex->getMessage());
		}
		
		$existsFuncs=array();
		if(!empty($cmsClass)){
			$existsFuncs=get_class_methods($cmsClass);
		}
		$_params=var_export($_params,true);
		$_params=preg_replace_callback('/^\s*/m', function($matches){
			$returnStr="\t";
			for($i=0;$i<(strlen($matches[0])/2);$i++){
				$returnStr.="\t";
			}
			return $returnStr;
		}, $_params);
		$_params=preg_replace('/\s+array\s*\(/i', ' array (', $_params);
		
		$funcPhp='';
		foreach ($newFuncs as $v){
			if(!in_array($v,$existsFuncs)){
				
				$funcPhp.="\r\n\tpublic function {$v}(){\r\n\t\t/*必须返回键值对数组*/\r\n\t\treturn array();\r\n\t}";
			}
		}
if(empty($cmsClass)){

$phpCode=<<<EOF
<?php
namespace plugin\\release\\cms;
class {$appData['app']} extends BaseCms{
	/*参数*/
	public \$_params ={$_params};

	{$funcPhp}
	
	/*导入数据*/
	public function runImport(\$params){
		/*
		 * -----这里开始写代码-----
		 * 数据库操作：\$this->db()，可参考thinkphp5的数据库操作
		 * 参数值列表：\$params，\$params[变量名] 调用参数的值
		 */
		
		
		
		/*
		 * 必须以数组形式返回：
		 * id（必填）表示入库返回的自增id或状态
		 * target（可选）记录入库的数据位置（发布的网址等）
		 * desc（可选）记录入库的数据位置附加信息
		 * error（可选）记录入库失败的错误信息
		 * 入库的信息可在“已采集数据”中查看
		 */
		return array('id'=>0,'target'=>'','desc'=>'','error'=>'');
	}
}
?>
EOF;
}else{
	
	$phpCode=null;
	if($is_old_plugin){
		
		$phpCode=$mapp->oldFileCode($appData['app'],'cms');
		
		$phpCode=preg_replace('/\bthinkphp\s*\d+(\.\d+){0,1}/i', 'thinkphp5', $phpCode);
		$phpCode=preg_replace('/\bnamespace\s+Release\\\Cms\;/i', 'namespace plugin\\release\\cms;', $phpCode);
		$phpCode=preg_replace('/\bclass\s+(\w+)Cms\s+extends\s+BaseCms\b/i', "class \\1 extends BaseCms", $phpCode);
	}else{
		$phpCode=file_get_contents($mapp->appFileName($appData['app'],'cms'));
	}
	
	$phpCode=preg_replace('/public\s*\$_params\s*\=[\s\S]+?\)\s*;/i', 'public $_params ='.$_params.';', $phpCode);
	
	
	if(!empty($funcPhp)){
		if(preg_match('/namespace[^\r\n]+?\{/', $phpCode)){
			
			$phpCode=preg_replace('/\}\s*\}\s*\?\>/',"\r\n".$funcPhp."\t\r\n}\r\n}\r\n?>",$phpCode);
		}else{
			
			$phpCode=preg_replace('/\}\s*\?\>/',"\r\n".$funcPhp."\r\n}\r\n?>",$phpCode);
		}
	}
}
		if(!empty($phpCode)){
			$success=$mapp->addCms(array('app'=>$appData['app'],'name'=>$appData['name']),$phpCode);
			if($success){
				$this->success('创建成功','develop/releaseCms?app='.$appData['app']);
			}else{
				$this->error('创建失败');
			}
		}else{
			$this->error('代码错误');
		}
	}
	
	
	/*开发应用*/
	public function appAction(){
		$app=input('app');
		$app=strtolower($app);
		$mapp=model('App');
		$appData=null;
		if($app){
			$appData=$mapp->getByApp($app);
		}
		if(request()->isPost()){
			
			$is_edit=input('edit');
			if($is_edit&&empty($appData)){
				$this->error('修改失败，该应用不存在！');
			}
			$framework=input('framework');
			$frameworkVersion=input('framework_version/a',array());
			$frameworkVersion=$frameworkVersion[$framework];
			
			$config=array(
				'name'=>input('name'),
				'version'=>input('version'),
				'desc'=>input('desc','','trim'),
				'author'=>input('author'),
				'website'=>input('website','','trim'),
				'phpv'=>input('phpv'),
				'agreement'=>input('agreement','','trim')
			);
			
			$install=input('install','','trim');
			$uninstall=input('uninstall','','trim');
			$upgrade=input('upgrade','','trim');
				
			if(!empty($framework)&&empty($frameworkVersion)){
				$this->error('请选择框架版本');
			}
			
			$packs=input('packs/a',array());
			if(empty($config['name'])){
				$this->error('请输入应用名称');
			}
			if(!$mapp->right_name($config['name'])){
				$this->error('应用名称只能由汉字、字母、数字和下划线组成');
			}
			if(!$is_edit){
				
				if(!$mapp->right_app($app)){
					$this->error('app标识不规范');
				}
				if($mapp->where('app',$app)->count()>0){
					
					$this->error('抱歉，已存在'.$app.'应用');
				}
			}
			if(!$mapp->right_version($config['version'])){
				$this->error('版本号格式错误');
			}
			
			if(empty($install)){
			    $this->error('请输入安装应用接口');
			}
			if(empty($uninstall)){
			    $this->error('请输入卸载应用接口');
			}
			if(empty($upgrade)){
			    $this->error('请输入升级应用接口');
			}
			
			if(is_array($packs)){
				foreach ($packs as $k=>$v){
					
					$v=json_decode(url_b64decode($v),true);
					$packs[$k]=array(
						'name'=>$v['name'],
						'type'=>$v['type'],
						'nav_link'=>$v['nav_link'],
						'target'=>$v['target'],
					);
				}
			}else{
				$packs=array();
			}
			
			$config['framework']=$framework;
			$config['framework_version']=$frameworkVersion;
			$config['packs']=$packs;
			
			$config=$mapp->clear_config($config);
			
			$provId=model('Provider')->getIdByUrl($config['website']);
				
			$tplAppPhp=file_get_contents(config('app_path').'/public/app/skycaiji_php.tpl');
			
			$tplParams=$config;
			$tplParams['app']=$app;
			$tplParams['install']=$install;
			$tplParams['uninstall']=$uninstall;
			$tplParams['upgrade']=$upgrade;
			foreach ($tplParams as $k=>$v){
				if(is_array($v)){
					
					$v=$this->_format_array($v,"\t");
				}
				$tplAppPhp=str_replace('{$'.$k.'}',$v, $tplAppPhp);
			}
			unset($tplParams);
			
			$tplAppPhp=preg_replace('/\{\$[^\{\}]+\}/', '', $tplAppPhp);
			if(!$is_edit){
				
				
				$createFiles = array (
					'index.php'=>file_get_contents(config('app_path').'/public/app/index_php.tpl'),
					$app.'.php'=>$tplAppPhp,
				);
	
				foreach ($createFiles as $filename=>$filecode){
					write_dir_file(config('apps_path')."/{$app}/{$filename}",$filecode);
				}
	
				$mapp->isUpdate(false)->allowField(true)->save(array(
					'app'=>$app,
					'addtime'=>time(),
					'uptime'=>time(),
					'provider_id'=>$provId
				));
				if($mapp->id>0){
					$mapp->set_config($app,$config);
					$this->success('应用创建成功','develop/app?app='.$app);
				}else{
					$this->success('应用创建失败');
				}
			}else{
				
				$appFilename=$mapp->app_class_file($app);
	
				$codeAppPhp=file_get_contents($appFilename);
	
				if(!empty($codeAppPhp)){
					
					$appClass=$mapp->app_class($app);
					$appConfig=array();
					if(is_object($appClass)){
						$appConfig=is_array($appClass->config)?$appClass->config:array();
					}
					$appConfig=array_merge($appConfig,$config);
						
					$replaceVars=array('config'=>$appConfig,'install'=>$install,'uninstall'=>$uninstall,'upgrade'=>$upgrade);
					$replaceVars=array_reverse($replaceVars);
					foreach ($replaceVars as $reVar=>$reCont){
						
						$matchVar='/[a-z]+\s*\$'.$reVar.'\s*=(?:([^\'\"\r\n]+?;)|([\s\S]+?[\]\)\'\"]\s*;))/i';
	
						if(!preg_match($matchVar,$codeAppPhp)){
							
							$codeAppPhp=preg_replace('/class\s*\w+\s*extends\s*skycaiji\s*\{/i', "$0\r\n\tpublic \$".$reVar."='';", $codeAppPhp);
						}
						if(is_array($reCont)){
							
							$reCont=$this->_format_array($reCont);
							$codeAppPhp=preg_replace($matchVar, 'public $'.$reVar.'='.$reCont.';', $codeAppPhp);
						}else{
							
							$codeAppPhp=preg_replace($matchVar, 'public $'.$reVar."='".addslashes($reCont)."';", $codeAppPhp);
						}
					}
	
					write_dir_file($appFilename, $codeAppPhp);
				}else{
					
					write_dir_file($appFilename, $tplAppPhp);
				}
	
				$mapp->strict(false)->where('id',$appData['id'])->update(array(
					'uptime'=>time(),
					'provider_id'=>$provId
				));
				if(version_compare($config['version'],$appData['config']['version'],'<=')===true){
					
					$mapp->set_config($app,$config);
				}
	
				$this->success('修改成功','develop/app?app='.$app);
			}
		}else{
		    $htmlTagNav='';
	
			if($appData){
			    $htmlTagNav=breadcrumb(array(array('url'=>url('app/manage?app='.$appData['app']),'title'=>$appData['config']['name']),array('url'=>url('develop/app?app='.$appData['app']),'title'=>'开发应用')));
			}else{
			    $htmlTagNav=breadcrumb(array(array('url'=>url('mystore/app'),'title'=>'应用程序'),array('url'=>url('develop/app'),'title'=>'开发应用')));
			}
			
			$this->set_html_tags(
			    '开发应用程序',
			    '开发应用程序 <small><a href="https://www.skycaiji.com/manual/doc/app" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>',
			    $htmlTagNav
			);
				
			$appClass=$mapp->app_class($app);
			
			if(is_object($appClass)){
				if(version_compare($appClass->config['version'], $appData['config']['version'],'>')===true){
					
					$this->assign('newest_version',$appClass->config['version']);
				}
				
				$appFrameworkPath=$appClass->appFrameworkPath();
				if(is_dir($appFrameworkPath)){
					
					$this->assign('appFrameworkPath',$appFrameworkPath);
				}
				
				$appData['app_class']=$mapp->get_class_vars($appClass);
			}
	
			$this->assign('appData',$appData);
			$this->assign('frameworks',self::$frameworks);
			$this->assign('packTypes',self::$packTypes);
			return $this->fetch();
		}
	}
	
	/*添加扩展*/
	public function appAddPackAction(){
		if(request()->isPost()){
		    $pack=input('pack/a',array(),'trim');
			$pack['name']=strip_tags($pack['name']);
			$pack['type']=strip_tags($pack['type']);
			$pack['target']=intval($pack['target']);
			$pack=\util\Funcs::array_array_map('trim', $pack);
			if(empty($pack['name'])){
				$this->error('请输入名称');
			}
			if(!model('App')->right_name($pack['name'])){
				$this->error('名称只能由汉字、字母、数字和下划线组成');
			}
				
			if(empty($pack['type'])){
				$this->error('请选择类型');
			}
			if(empty($pack['nav_link'])){
				$this->error('请输入链接');
			}
			$pack['pack_json']=json_encode($pack);
			$pack['type_name']=self::$packTypes[$pack['type']];
			$this->success('',null,$pack);
		}else{
			$objid=input('objid');
			$pack=input('pack','','url_b64decode');
			$pack=$pack?json_decode($pack,true):'';
	
			$this->assign('objid',$objid);
			$this->assign('pack',$pack);
			$this->assign('packTypes',self::$packTypes);
			return $this->fetch('appAddPack');
		}
	}
	/*下载安装框架*/
	public function installFrameworkAction(){
		$app=input('app');
	
		if(empty($app)){
			$this->error('应用app标识错误');
		}
		$mapp=model('App');
		$appClass=$mapp->app_class($app);
		if(!is_object($appClass)){
			$this->error('应用配置错误');
		}
	
		if(empty($appClass->config['framework'])){
			$this->error('框架不能为空');
		}
	
		if(empty($appClass->config['framework_version'])){
			$this->error('框架版本错误');
		}
		
		$appFrameworkPath=$appClass->appFrameworkPath();
		if(is_dir($appFrameworkPath)){
			$this->error('该应用已有框架，如需重新设置框架，请先删除：'.$appFrameworkPath);
		}
		
		$mprov=model('Provider');
		
		$timestamp=time();
		$clientinfo=clientinfo();
		
		$authsign=$mprov->createAuthsign($mprov->getAuthkey(null),$clientinfo['url'],'https://www.skycaiji.com',$timestamp);
		
		$uriParams=array(
		    'authsign'=>$authsign,
		    'client_url'=>$clientinfo['url'],
		    'timestamp'=>$timestamp,
		    'name'=>$appClass->config['framework'],
		    'version'=>$appClass->config['framework_version'],
		    'block_no'=>input('block_no/d',0)
		);
		
		$fileData=\util\Tools::curl_skycaiji('/client/download/framework?'.http_build_query($uriParams));
		$fileData=json_decode($fileData,true);
		$fileData=is_array($fileData)?$fileData:array();
		
		if(!$fileData['code']){
		    
		    $this->error($fileData['msg']);
		}
		$fileData=is_array($fileData['data'])?$fileData['data']:array();
		$fileData=json_decode(base64_decode($fileData['file']),true);
		$fileData=is_array($fileData)?$fileData:array();
		
		$filePath=config('runtime_path').'/zip_framework/'.$appClass->config['framework'].$appClass->config['framework_version'];
		
		$result=\util\Tools::install_downloaded_zip($fileData, $filePath, $appClass->appPath);
		
		if($result['success']){
		    $this->success('框架安装成功','develop/app?app='.$app,$result);
		}else{
		    $this->error($result['msg']);
		}
	}
	/*开发函数插件*/
	public function funcAction(){
		$mfuncApp=new FuncApp();
		if(request()->isPost()){
			if(input('?edit')){
				
				$app=input('app');
				$name=input('name');
				$name=$mfuncApp->format_str($name);
				$funcData=$mfuncApp->where('app',$app)->find();
				if(empty($funcData)){
					$this->error('插件不存在');
				}
				$mfuncApp->where('id',$funcData['id'])->update(array('name'=>$name,'uptime'=>time()));
				
				$this->success('修改成功','develop/func?app='.$app);
			}else{
				
				$module=input('module');
				$copyright=input('copyright');
				$identifier=input('identifier');
				$name=input('name');
				$methods=input('methods/a',array());
				
				if(empty($module)){
					$this->error('请选择类型');
				}
				
				$module=$mfuncApp->format_module($module);
				$copyright=$mfuncApp->format_copyright($copyright);
				$identifier=$mfuncApp->format_identifier($identifier);
				
				if(!$mfuncApp->right_module($module)){
					$this->error('类型错误');
				}
				if(!$mfuncApp->right_identifier($identifier)){
					$this->error('功能标识只能由字母或数字组成，且首个字符必须是字母！');
				}
				if(!$mfuncApp->right_copyright($copyright)){
					$this->error('作者版权只能由字母或数字组成，且首个字符必须是字母！');
				}
				
				$newMethods=array();
				foreach ($methods['method'] as $k=>$v){
				    if($mfuncApp->right_method($v)){
						
						foreach ($methods as $mk=>$mv){
							
							$newMethods[$mk][$k]=$mv[$k];
						}
					}
				}
				$methods=$newMethods;
				unset($newMethods);
				
				if(empty($methods['method'])){
					$this->error('请添加方法！');
				}
				
				$app=$mfuncApp->app_name($copyright,$identifier);
				
				$id=$mfuncApp->createApp($module,$app,array('name'=>$name,'methods'=>$methods));
				
				if($id>0){
					$this->success('创建成功','develop/func?app='.$app);
				}else{
					$this->error('创建失败');
				}
			}
		}else{
		    $module=input('module');
		    
		    $this->set_html_tags(
		        '开发函数插件',
		        '开发函数插件 <small><a href="https://www.skycaiji.com/manual/doc/func" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>',
		        breadcrumb(array(array('url'=>url('mystore/funcApp'),'title'=>'函数插件'),array('url'=>url('develop/func'),'title'=>'开发函数插件')))
		    );
		    
		    $app=input('app','');
		    if($app){
				
		        $funcData=$mfuncApp->where('app',$app)->find();
				if(!empty($funcData)){
					$funcClass=$mfuncApp->get_app_class($funcData['module'],$funcData['app']);
					$funcClass['name']=$funcData['name'];
					
					$this->assign('funcClass',$funcClass);
				}
			}
			$this->assign('app',$app);
			$this->assign('module',$module);
			$this->assign('modules',$mfuncApp->funcModules);
			return $this->fetch();
		}
	}
	
	/*导出插件*/
	public function exportAction(){
	    $type=input('type');
	    $module=input('module');
	    $app=input('app');
	    if(request()->isPost()){
	        $pwd=input('pwd','','trim');
	        $pluginData=$this->_export_plugin_data($type, $module, $app, $pwd);
	        if(empty($pluginData['success'])){
	            $this->error($pluginData['msg']);
	        }
	        \util\Tools::browser_export_scj($app.($pwd?'.加密':'').'.插件', $pluginData['plugin_txt']);
	    }else{
	        $this->set_html_tags(
	            '导出插件',
	            '导出插件至本地',
	            breadcrumb(array('导出插件'))
	        );
	        $this->assign('type',$type);
	        $this->assign('module',$module);
	        $this->assign('app',$app);
	        return $this->fetch();
	    }
	}
	
	
	public function editorAction(){
        $type=input('type','');
        $module=input('module','');
        $app=input('app','');
        $mReleApp=model('ReleaseApp');
        $mFuncApp=model('FuncApp');
        $isApp=false;
        $setTitle='';
        $setNav='';
        if(!empty($type)){
            if(!empty($app)){
                $isApp=true;
                $appcode='';
                if($type=='release'){
                    $setTitle='发布插件';
                    $appName=$app;
                    if($module=='diy'){
                        
                        if($mReleApp->isSystemApp($app,'diy')){
                            $this->error('不能编辑系统文件');
                        }
                        $setTitle.=' » 自定义';
                        if($mReleApp->appFileExists($app,'diy')){
                            $appcode=file_get_contents($mReleApp->appFileName($app,'diy'));
                        }
                    }else{
                        
                        if($mReleApp->isSystemApp($app,'cms')){
                            $this->error('不能编辑系统文件');
                        }
                        $releData=$mReleApp->where('app',$app)->find();
                        if(!empty($releData)){
                            if($mReleApp->appFileExists($releData['app'],$releData['module'])){
                                $appcode=file_get_contents($mReleApp->appFileName($releData['app'],$releData['module']));
                            }
                            if($releData['module']=='cms'){
                                $setTitle.=' » cms程序';
                                if($releData['name']){
                                    $appName.='（'.$releData['name'].'）';
                                }
                                $setNav=breadcrumb(array(array('url'=>url('develop/releaseCms?app='.$app),'title'=>$app),'编辑插件'));
                            }
                        }
                    }
                    $setTitle.=' » '.$appName;
                }elseif($type=='func'){
                    $setTitle='函数插件';
                    $appName=$app;
                    $funcData=$mFuncApp->where('app',$app)->find();
                    if(!empty($funcData)){
                        if(file_exists($mFuncApp->filename($funcData['module'],$funcData['app']))){
                            $appcode=file_get_contents($mFuncApp->filename($funcData['module'],$funcData['app']));
                        }
                        if($funcData['module']){
                            
                            $setTitle.=' » '.$mFuncApp->get_func_module_val($funcData['module'],'name');
                        }
                        if($funcData['name']){
                            $appName.='（'.$funcData['name'].'）';
                        }
                    }
                    $setTitle.=' » '.$appName;
                    $setNav=breadcrumb(array(array('url'=>url('develop/func?app='.$app),'title'=>$app),'编辑插件'));
                }
                $appcode=$appcode?:'';
                if($setTitle){
                    $setTitle='<span style="font-size:18px;">编辑插件：'.$setTitle.'</span>';
                }
            }
        }else{
            $type='release';
        }
        $appList=array();
        if($type=='release'){
            $appList=$mReleApp->order('app asc')->column('name','app');
            init_array($appList);
            $mRele=model('Release');
            
            $diyList=$mRele->where('module','diy')->column('config','id');
            if($diyList){
                foreach ($diyList as $k=>$v){
                    $diyApp='';
                    if($v){
                        $v=unserialize($v);
                        if(is_array($v)&&is_array($v['diy'])&&$v['diy']['type']=='app'&&$v['diy']['app']){
                            $diyApp=$v['diy']['app'];
                        }
                    }
                    if($diyApp&&!$mReleApp->isSystemApp($diyApp,'diy')){
                        $diyList[$k]=$diyApp;
                    }else{
                        unset($diyList[$k]);
                    }
                }
                if($diyList){
                    $diyList=array_unique($diyList);
                    sort($diyList);
                    $this->assign('diyList',$diyList);
                }
            }
        }elseif($type=='func'){
            $appList=$mFuncApp->order('app asc')->column('name','app');
        }
        init_array($appList);
        
        $setTitle=$setTitle?:'插件编辑器';
        $setNav=$setNav?:breadcrumb(array(array('url'=>url('develop/editor'),'title'=>'插件编辑器')));
        
        $this->set_html_tags(
            '插件编辑器',
            $setTitle,
            $setNav
        );
        
        $this->assign('config',array('type'=>$type,'module'=>$module,'app'=>$app));
        $this->assign('type',$type);
        $this->assign('module',$module);
        $this->assign('app',$app);
        $this->assign('isApp',$isApp);
        $this->assign('appList',$appList);
        $this->assign('appcode',$appcode);
        return $this->fetch();
	}
	
	public function editor_codeAction(){
	    $appcode=input('appcode','','trim');
	    $this->assign('appcode',$appcode);
	    return $this->fetch('editor_code');
	}
	
	public function editor_saveAction(){
	    $this->ajax_check_userpwd();
	    if(request()->isPost()){
	        $type=input('type','');
	        $module=input('module','');
	        $app=input('app','');
	        $appcode=input('appcode','','trim');
	        
	        $filename='';
	        if($type=='release'){
	            $mReleApp=model('ReleaseApp');
	            $module=$module=='diy'?'diy':'cms';
	            if($mReleApp->isSystemApp($app,$module)){
	                $this->error('不能编辑系统文件');
	            }
	            if(!$mReleApp->isRightApp($app,$module)){
	                $this->error('插件名称格式错误');
	            }
	            if($module=='cms'){
	                
	                $releData=$mReleApp->where(array('app'=>$app,'module'=>'cms'))->find();
	                if(empty($releData)){
	                    $this->error('插件不存在');
	                }
	            }
	            $filename=$mReleApp->appFileName($app,$module);
	        }elseif($type=='func'){
	            $mFuncApp=model('FuncApp');
	            
	            $funcData=$mFuncApp->where('app',$app)->find();
	            if(empty($funcData)||empty($funcData['module'])||empty($funcData['app'])){
	                $this->error('插件不存在');
	            }
	            $filename=$mFuncApp->filename($funcData['module'],$funcData['app']);
	        }else{
	            $this->error('类型错误');
	        }
	        if(empty($filename)){
	            $this->error('插件文件错误');
	        }
	        write_dir_file($filename, $appcode);
	        $uri=sprintf('develop/editor?type=%s&module=%s&app=%s',$type,$module,$app);
	        $this->success('操作成功',$uri);
	    }else{
	        $this->error('提交错误');
	    }
	}
	public function plugin_skycaijiAction(){
	    $op=input('op');
	    $scjPlugin = new \ReflectionClass('\\plugin\\skycaiji');
	    $scjMethods=$scjPlugin->getMethods(\ReflectionMethod::IS_PUBLIC);
	    if(empty($op)){
	        
            $scjMethods1=array();
            foreach ($scjMethods as $scjMethod){
                $methodName=$scjMethod->name;
                if(empty($methodName)||strpos($methodName,'__')===0){
                    
                    continue;
                }
                $methodCmt=$scjMethod->getDocComment();
                if($methodCmt){
                    $methodCmt=preg_replace('/^[\/\*\s]+/m', '', $methodCmt);
                    $methodCmt=trim($methodCmt);
                    $methodCmt=htmlspecialchars($methodCmt,ENT_QUOTES);
                    $methodCmt=preg_replace('/[\r\n]+/', '<br>', $methodCmt);
                }
                $scjMethods1[$methodName]=$methodCmt;
            }
            $scjMethods=$scjMethods1;
	        $this->success('','',$scjMethods);
	    }elseif($op=='method'){
	        $method=input('method','');
	        $code=file(config('plugin_path').'/skycaiji.php');
	        $methodCmt='';
	        $methodCode='';
	        foreach ($scjMethods as $scjMethod){
	            if($scjMethod->name==$method){
	                $methodCmt=$scjMethod->getDocComment();
	                $methodStart=$scjMethod->getStartLine();
	                $methodEnd=$scjMethod->getEndLine();
	                $methodCode=array_slice($code, $methodStart-1, $methodEnd-$methodStart+1);
	                $methodCode=is_array($methodCode)?implode('',$methodCode):'';
	                break;
	            }
	        }
	        
	        $this->assign('methodCmt',$methodCmt);
	        $this->assign('methodCode',$methodCode);
	        return $this->fetch('plugin_skycaiji_method');
	    }
	}
	
	
	private function _get_plugin_data($type,$app){
	    $mapp=null;
	    if($type=='release'){
	        $mapp=model('ReleaseApp');
	    }elseif($type=='func'){
	        $mapp=model('FuncApp');
	    }else{
	        return return_result('类型错误');
	    }
	    if(empty($app)){
	        return return_result('app标识错误');
	    }
	    $pluginDb=$mapp->where('app',$app)->find();
	    if(empty($pluginDb)){
	        return return_result('插件不存在');
	    }
	    
	    $pluginData=array(
	        'app'=>$pluginDb['app'],
	        'name'=>$pluginDb['name'],
	        'type'=>$type,
	        'module'=>$pluginDb['module'],
	        'uptime'=>$pluginDb['uptime'],
	        'store_url'=>''
	    );
	    
	    if(!empty($pluginDb['provider_id'])){
	        
	        $mprov=model('Provider');
	        $provData=$mprov->where('id',$pluginDb['provider_id'])->find();
	        $pluginData['store_url']=\skycaiji\admin\model\Provider::create_store_url($provData['url'],'client/addon/plugin',array('app'=>$pluginDb['app']));
	    }
	    
	    if(empty($pluginData['name'])){
	        return return_result('插件名称为空');
	    }
	    if(empty($pluginData['module'])){
	        return return_result('插件模块为空');
	    }
	    $appFile=config('plugin_path').'/'.$type.'/'.$pluginDb['module'].'/'.$pluginData['app'].'.php';
	    if($type=='release'){
	        $appTpl=config('plugin_path').'/'.$type.'/view/'.$pluginDb['module'].'/'.$pluginData['app'].'.html';
	        if(file_exists($appTpl)){
	            
	            $appTpl=file_get_contents($appTpl);
	            $pluginData['tpl']=base64_encode($appTpl);
	        }
	    }
	    if(file_exists($appFile)){
	        
	        $appFile=file_get_contents($appFile);
	        $pluginData['code']=base64_encode($appFile);
	    }
	    if(empty($pluginData['code'])){
	        return return_result('插件文件不存在');
	    }
	    
	    return return_result('',true,array('plugin'=>$pluginData));
	}
	
	public function _export_plugin_data($type,$module,$app,$pwd){
	    $reuslt=return_result('');
	    if($type=='release'&&$module=='diy'){
	        
	        $pluginResult=return_result('');
	        if(!model('ReleaseApp')->appFileExists($app,'diy')){
	            $pluginResult['msg']='插件不存在';
	        }else{
	            $pluginResult['success']=1;
	            $pluginResult['plugin']=array(
	                'app'=>$app,
	                'type'=>'release',
	                'module'=>'diy',
	                'code'=>'',
	            );
	            $filename=model('ReleaseApp')->appFileName($app,'diy');
	            $pluginResult['plugin']['code']=file_get_contents($filename);
	            $pluginResult['plugin']['code']=base64_encode($pluginResult['plugin']['code']);
	        }
	    }else{
	        $pluginResult=$this->_get_plugin_data($type,$app);
	    }
	    
	    if(empty($pluginResult['success'])){
	        if($type=='release'){
	            $reuslt['msg']=lang('rele_m_name_'.$module).'发布插件：'.$app;
	        }elseif($type=='func'){
	            $reuslt['msg']=model('FuncApp')->get_func_module_val($module,'name').'函数插件：'.$app;
	        }
	        $reuslt['msg'].=' » '.$pluginResult['msg'];
	    }else{
	        $pluginResult=$pluginResult['plugin'];
	        $pluginResult=base64_encode(serialize($pluginResult));
	        if(!empty($pwd)){
	            
	            $edClass=new \util\EncryptDecrypt();
	            $pluginResult=$edClass->encrypt(array('data'=>$pluginResult,'pwd'=>$pwd));
	            $pluginResult=base64_encode(serialize($pluginResult));
	        }
	        $reuslt['success']=true;
	        $reuslt['plugin_txt']='/*skycaiji-plugin-start*/'.$pluginResult.'/*skycaiji-plugin-end*/';
	    }
	    return $reuslt;
	}
	
	private function _format_array($arr,$headStr=''){
	    if(is_array($arr)){
	        $arr=var_export($arr,true);
	    }
	    $arr=preg_replace_callback('/^\s*/m', function($matches) use ($headStr){
	        
	        $returnStr="\t";
	        for($i=0;$i<(strlen($matches[0])/2);$i++){
	            $returnStr.="\t";
	        }
	        return $headStr.$returnStr;
	    }, $arr);
	        $arr=preg_replace('/\s+array\s*\(/i', 'array(', $arr);
	        return $arr;
	}
	
	private function _copy_files($fromPath,$toPath){
	    if(empty($fromPath)||empty($toPath)){
	        return false;
	    }
	    if(is_dir($fromPath)){
	        
	        $fileList=scandir($fromPath);
	        foreach( $fileList as $file ){
	            if('.'== $file || '..' == $file){
	                continue;
	            }
	            $fileName=$fromPath.'/'.$file;
	            if(!file_exists($fileName)){
	                continue;
	            }
	            $toFile=$toPath.'/'.$file;
	            if(is_dir( $fileName )){
	                mkdir($toFile,0777,true);
	                $this->_copy_files($fileName, $toFile);
	            }elseif(is_file($fileName)){
	                write_dir_file($toFile,file_get_contents($fileName));
	            }
	        }
	    }
	}
}