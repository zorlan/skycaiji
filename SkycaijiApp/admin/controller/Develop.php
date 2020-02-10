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
					$this->error('请输入插件功能标识');
				}elseif(!preg_match('/^[a-z][a-z0-9]*$/i', $identifier)){
					$this->error('插件功能标识必须由字母或数字组成且首位不能为数字！');
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
			
			$params=input('params/a');
			if(empty($params)||!is_array($params)){
				$this->error('请添加参数');
			}
			foreach ($params as $k=>$v){
				$params[$k]=json_decode(url_b64decode($v),true);
			}
			
			$this->create_cms_app(array('name'=>$name,'app'=>$appName), $params,$is_edit);
			
		}else{
			$appName=input('app');
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
					if(is_array($cmsClass->_params)){
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
				}
			}
			
			$GLOBALS['_sc']['p_name']='开发CMS发布插件 <small><a href="https://www.skycaiji.com/manual/doc/cms" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>';
			$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/ReleaseApp'),'title'=>'CMS发布插件'),array('url'=>url('Develop/releaseCms'),'title'=>'开发CMS发布插件')));
				
			$this->assign('config',$config);
			$this->assign('is_old_plugin',$is_old_plugin);
			return $this->fetch('releaseCms');
		}
	}
	/*添加参数*/
	public function cmsAddParamAction(){
		if(request()->isPost()){
			$param=input('param/a');
			$param=array_array_map('trim', $param);
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
	public function create_cms_app($appData,$params,$is_edit=false){
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
				$this->success('创建成功','Develop/releaseCms?app='.$appData['app']);
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
			$frameworkVersion=input('framework_version/a');
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
				
			$packs=input('packs/a');
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
					$this->success('应用创建成功','Develop/app?app='.$app);
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
	
				$this->success('修改成功','Develop/app?app='.$app);
			}
		}else{
			$GLOBALS['_sc']['p_name']='开发应用程序 <small><a href="https://www.skycaiji.com/manual/doc/app" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>';
	
			if($appData){
				$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('App/manage?app='.$appData['app']),'title'=>$appData['config']['name']),array('url'=>url('Develop/app?app='.$appData['app']),'title'=>'开发应用')));
			}else{
				$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/app'),'title'=>'应用程序'),array('url'=>url('Develop/app'),'title'=>'开发应用')));
			}
				
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
			$pack=input('pack/a','','trim');
			$pack['name']=strip_tags($pack['name']);
			$pack['type']=strip_tags($pack['type']);
			$pack['target']=intval($pack['target']);
			$pack=array_array_map('trim', $pack);
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
		$op=input('op');
	
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
	
		$eachSize=1024*100;
		$fileUrl='https://www.skycaiji.com/download/framework/'.$appClass->config['framework'].'/'.$appClass->config['framework_version'].'.zip';

		$filePath=RUNTIME_PATH.'/cache_framework/'.$appClass->config['framework'].$appClass->config['framework_version'].'/';
	
		if('files'==$op){
			
			$fileHeader=get_headers($fileUrl,true);
			if(!preg_match('/\s+20\d\s+ok/i',$fileHeader[0])){
				$this->error('文件获取失败');
			}
			$fileSize=$fileHeader['Content-Length'];
			$list=array();
			$count=ceil($fileSize/$eachSize);
			for($i=0;$i<$count;$i++){
				
				$list[$i]=array('id'=>$i+1,'start'=>$i*$eachSize,'end'=>($i+1)*$eachSize);
				if($list[$i]['end']>=$fileSize){
					$list[$i]['end']=$fileSize;
				}
				$list[$i]['end']-=1;
			}
			$this->success(true,'',array('size'=>$fileSize,'list'=>$list));
		}elseif('down'==$op){
			$fileSize=input('size/d');
			$startSize=input('start_size/d');
			$endSize=input('end_size/d');
			$id=input('id/d');
				
			$fileCont=file_get_contents($filePath.$id);
				
			if(!empty($fileCont)){
				
				$this->success();
			}else{
				$blockData=$this->_down_file($fileUrl,null,"{$startSize}-{$endSize}");
				if(empty($blockData)){
					
					$this->error();
				}else{
					write_dir_file($filePath.$id, $blockData);
					$this->success();
				}
			}
		}elseif('install'==$op){
			$fileSize=input('size/d');
			$count=ceil($fileSize/$eachSize);
			$is_end=true;
			for($i=1;$i<=$count;$i++){
				
				if(!file_exists($filePath.$i)){
					$is_end=false;
					break;
				}
			}
			if($is_end){
				
				$error='';
				$allData='';
				for($i=1;$i<=$count;$i++){
					$allData.=file_get_contents($filePath.$i);
				}
				write_dir_file($filePath.'framework.zip', $allData);
	
				try {
					$zipClass=new \ZipArchive();
					if($zipClass->open($filePath.'framework.zip')===TRUE){
						$zipClass->extractTo($appClass->appPath);
						$zipClass->close();
					}else{
						$error='解压失败';
					}
				}catch(\Exception $ex){
					$error='您的服务器不支持ZipArchive解压';
				}
				if(!empty($error)){
					$error.='，请自行将文件'.$filePath.'framework.zip 解压到'.$appClass->appPath.'里';
				}
				if($error){
					$this->error($error);
				}else{
					clear_dir($filePath);
					$this->success('安装成功','Develop/app?app='.$app);
				}
			}
			$this->error();
		}
	}
	/*开发函数插件*/
	public function funcAction(){
		$mfuncApp=new FuncApp();
		if(request()->isPost()){
			if(input('?edit')){
				
				$app=input('app');
				$name=input('name');
				$name=strip_tags($name);
				$funcData=$mfuncApp->where('app',$app)->find();
				if(empty($funcData)){
					$this->error('插件不存在');
				}
				$mfuncApp->where('id',$funcData['id'])->update(array('name'=>$name,'uptime'=>time()));
				
				$this->success('修改成功','Develop/func?app='.$app);
			}else{
				
				$module=input('module');
				$copyright=input('copyright');
				$identifier=input('identifier');
				$name=input('name');
				$methods=input('methods/a');
				
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
					if(preg_match('/^[a-z\_]\w*/',$v)){
						
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
					$this->success('创建成功','Develop/func?app='.$app);
				}else{
					$this->error('创建失败');
				}
			}
		}else{
			$GLOBALS['_sc']['p_name']='开发函数插件 <small><a href="https://www.skycaiji.com/manual/doc/func" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>';
			$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Mystore/funcApp'),'title'=>'函数插件'),array('url'=>url('Develop/func'),'title'=>'开发函数插件')));
			
			if(input('?app')){
				
				$funcData=$mfuncApp->where('app',input('app'))->find();
				if(!empty($funcData)){
					$funcClass=$mfuncApp->get_app_class($funcData['module'],$funcData['app']);
					$funcClass['name']=$funcData['name'];
					
					$this->assign('funcClass',$funcClass);
				}
			}
			
			$this->assign('modules',$mfuncApp->funcModules);
			return $this->fetch();
		}
	}
	
	/*保存到云端*/
	public function save2storeAction(){
		$type=input('type');
		$app=input('app');
		$pluginData=$this->_get_plugin_data($type,$app);
		$this->assign('pluginData',$pluginData);
		return $this->fetch();
	}
	/*导出插件*/
	public function exportAction(){
		$type=input('type');
		$app=input('app');
		$pluginData=$this->_get_plugin_data($type,$app);

		set_time_limit(600);
		$txt='/*skycaiji-plugin-start*/'.base64_encode(serialize($pluginData)).'/*skycaiji-plugin-end*/';
		$name=$pluginData['app'];
		ob_start();
		header("Expires: 0" );
		header("Pragma:public" );
		header("Cache-Control:must-revalidate,post-check=0,pre-check=0" );
		header("Cache-Control:public");
		header("Content-Type:application/octet-stream" );
		
		header("Content-transfer-encoding: binary");
		header("Accept-Length: " .mb_strlen($txt));
		if (preg_match("/MSIE/i", $_SERVER["HTTP_USER_AGENT"])) {
			header('Content-Disposition: attachment; filename="'.urlencode($name).'.skycaiji"');
		}else{
			header('Content-Disposition: attachment; filename="'.$name.'.skycaiji"');
		}
		echo $txt;
		ob_end_flush();
	}
	protected function _get_plugin_data($type,$app){
		$mapp=null;
		if($type=='release'){
			$mapp=model('ReleaseApp');
		}elseif($type=='func'){
			$mapp=model('FuncApp');
		}else{
			$this->error('类型错误');
		}
		if(empty($app)){
			$this->error('app标识错误');
		}
		$pluginDb=$mapp->where('app',$app)->find();
		if(empty($pluginDb)){
			$this->error('插件不存在');
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
			
			$provData=model('Provider')->where('id',$pluginDb['provider_id'])->find();
			$pluginData['store_url']=$provData['url'].'/client/plugin/detail?app='.$pluginDb['app'];
		}
		
		if(empty($pluginData['name'])){
			$this->error('插件名称为空');
		}
		if(empty($pluginData['module'])){
			$this->error('插件模块为空');
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
			$this->error('插件文件不存在');
		}
		return $pluginData;
	}
	
	public function _format_array($arr,$headStr=''){
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
	
	public function _copy_files($fromPath,$toPath){
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
	/*获取内容*/
	public function _down_file($url, $header=null,$size) {
		$useragents=array(
				"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; AcooBrowser; .NET CLR 1.1.4322; .NET CLR 2.0.50727)",
		);
		static $useragent;
		if(empty($useragent)){
			$useragent=$useragents[array_rand($useragents)];
		}
	
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, 100 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 0 );
		curl_setopt ( $ch, CURLOPT_USERAGENT, $useragent);
	
		
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
	
		if (! empty ( $header )) {
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
		}
		
		curl_setopt($ch, CURLOPT_RANGE, $size);
	
		$bytes = curl_exec ( $ch );
		curl_close ( $ch );
		return $bytes;
	}
}