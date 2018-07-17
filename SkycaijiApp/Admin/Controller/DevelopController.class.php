<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

namespace Admin\Controller; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class DevelopController extends BaseController { public static $typeList = array ( 'number' => '数字(number)', 'text' => '字符(text)', 'radio' => '开关(radio)', 'textarea' => '文本(textarea)', 'select_coll' => '选择采集字段(select)', 'select_val' => '选择固定值(select)', 'select_func' => '选择函数返回值(select)' ); public function releaseCmsAction(){ $mapp=D('ReleaseApp'); if(IS_POST){ $is_edit=I('edit'); $name=I('name'); if(empty($name)){ $this->error('请输入发布应用名称'); } if(!$is_edit){ $cms_name=I('cms_name'); $cms_name_custom=I('cms_name_custom'); $identifier=I('identifier'); $copyright=I('copyright'); if(empty($cms_name)){ $this->error('请选择CMS程序'); }elseif($cms_name=='custom'){ if(empty($cms_name_custom)){ $this->error('请输入CMS程序名'); }else{ $cms_name=$cms_name_custom; } } if(!preg_match('/^[a-z][a-z0-9]*$/i', $cms_name)){ $this->error('cms程序名必须由字母或数字组成且首位不能为数字！'); } if(empty($identifier)){ $this->error('请输入应用功能标识'); }elseif(!preg_match('/^[a-z][a-z0-9]*$/i', $identifier)){ $this->error('应用功能标识必须由字母或数字组成且首位不能为数字！'); } if(empty($copyright)){ $this->error('请输入作者版权'); }elseif(!preg_match('/^[a-z][a-z0-9]*$/i', $copyright)){ $this->error('作者版权必须由字母或数字组成且首位不能为数字！'); } $appName=ucfirst(strtolower($cms_name)).ucfirst(strtolower($identifier)).ucfirst(strtolower($copyright)); }else{ $appName=ucfirst(I('app')); } $params=I('params/a'); if(empty($params)||!is_array($params)){ $this->error('请添加参数'); } foreach ($params as $k=>$v){ $params[$k]=json_decode(url_b64decode($v),true); } $this->create_cms_app(array('name'=>$name,'app'=>$appName), $params,$is_edit); }else{ $appName=I('app'); $appName=ucfirst($appName); $config=array(); if($appName){ $cmsData=$mapp->where(array('module'=>'cms','app'=>$appName))->find(); if(!empty($cmsData)){ $config['name']=$cmsData['name']; $config['app']=$appName; if(preg_match('/^([A-Z][a-z0-9]*)([A-Z][a-z0-9]*)([A-Z][a-z0-9]*)$/', $appName,$appInfo)){ $config['is_edit']=true; $config['cms_name']=strtolower($appInfo[1]); $config['identifier']=strtolower($appInfo[2]); $config['copyright']=strtolower($appInfo[3]); $config['app_file']=realpath(C('ROOTPATH').'/'.APP_PATH.'Release/Cms/'.$appName.'Cms.class.php'); } $config['params']=array(); try { $cmsClass=$this->get_cms_class($appName); }catch (\Exception $ex){ $this->error($ex->getMessage()); } foreach ($cmsClass->_params as $k=>$v){ $param=array( 'key'=>$k, 'require'=>intval($v['require']), 'name'=>$v['name'], ); if($v['tag']=='select'){ if($v['option']=='function:param_option_fields'){ $param['type']='select_coll'; }elseif(preg_match('/^function:(.+)$/', $v['option'],$select_func)){ $param['type']='select_func'; $param['select_func']=$select_func[1]; }elseif(is_array($v['option'])){ $param['type']='select_val'; $param['select_val']=''; foreach ($v['option'] as $vk=>$vv){ $param['select_val'].=$vk.'='.$vv."\r\n"; } } }else{ $param['type']=$v['tag']; } $param['type_name']=self::$typeList[$param['type']]; $config['params'][]=$param; } } } $GLOBALS['content_header']='开发CMS发布应用 <small><a href="http://www.skycaiji.com/manual/doc/cms" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>'; $GLOBALS['breadcrumb']=breadcrumb(array('开发工具','开发CMS发布应用')); $this->assign('config',$config); $this->display('releaseCms'); } } public function cmsAddParamAction(){ if(IS_POST){ $param=I('param/a'); $param=array_array_map('trim', $param); if(empty($param['key'])){ $this->error('请输入变量名'); } if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/',$param['key'])){ $this->error('变量名必须符合php命名规范'); } if(empty($param['name'])){ $this->error('请输入参数名称'); } if(empty($param['type'])){ $this->error('请选择参数类型'); } if($param['type']=='select_func'){ if(!preg_match('/^param_option_[a-zA-Z0-9_]+$/', $param['select_func'])){ $this->error('函数名必须以param_option_开头且符合命名规范'); } }elseif($param['type']=='select_val'){ if(empty($param['select_val'])){ $this->error('请输入选项值'); } } $param['param_json']=json_encode($param); $param['type_name']=self::$typeList[$param['type']]; $this->success($param); }else{ $objid=I('objid'); $param=I('param','','url_b64decode'); $param=$param?json_decode($param,true):''; $this->assign('objid',$objid); $this->assign('param',$param); $this->assign('typeList',self::$typeList); $this->display('cmsAddParam'); } } public function create_cms_app($appData,$params,$is_edit=false){ if(!preg_match('/^[a-z][a-z0-9]*$/i', $appData['app'])){ $this->error('应用名错误！'); } $mapp=D('ReleaseApp'); $cmsData=$mapp->where(array('module'=>'cms','app'=>$appData['app']))->find(); if(!$is_edit&&!empty($cmsData)){ $this->error('抱歉，已存在'.$appData['app'].'应用'); } $_params=array(); $newFuncs=array(); foreach ($params as $k=>$v){ $pkey=$v['key']; $_params[$pkey]=array( 'name' => $v['name'], 'require'=>intval($v['require']) ); $v['type']=strtolower($v['type']); if(strpos($v['type'], 'select_')===0){ $_params[$pkey]['tag']='select'; }else{ $_params[$pkey]['tag']=$v['type']; } if($v['type']=='select_coll'){ $_params[$pkey]['option']='function:param_option_fields'; }elseif($v['type']=='select_func'){ $_params[$pkey]['option']='function:'.$v['select_func']; $newFuncs[$v['select_func']]=$v['select_func']; }elseif($v['type']=='select_val'){ if(preg_match_all('/[^\r\n]+/', $v['select_val'],$select_val)){ $_params[$pkey]['option']=array(); foreach ($select_val[0] as $slv){ if(strpos($slv,'=')!==false){ list($slv_k,$slv_v)=explode('=', $slv); if(is_null($slv_k)){ $slv_k=$slv_v; } $_params[$pkey]['option'][$slv_k]=$slv_v; }else{ $_params[$pkey]['option'][$slv]=$slv; } } } } } $cmsClass=$this->get_cms_class($appData['app']); $existsFuncs=array(); if(!empty($cmsClass)){ $existsFuncs=get_class_methods($cmsClass); } $_params=var_export($_params,true); $funcPhp=''; foreach ($newFuncs as $v){ if(!in_array($v,$existsFuncs)){ $funcPhp.="\r\n\tpublic function {$v}(){\r\n\t\t/*必须返回键值对数组*/\r\n\t\treturn array();\r\n\t}"; } } if(empty($cmsClass)){ $phpCode=<<<EOF
<?php
namespace Release\Cms;
class {$appData['app']}Cms extends BaseCms{
/*参数*/
public \$_params={$_params};

	{$funcPhp}
	
	/*导入数据*/
	public function runImport(\$params){
		/*
		 * -----这里开始写代码-----
		 * 数据库操作：\$this->db()，可参考thinkphp3.2的数据库操作
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
}else{ $phpCode=file_get_contents(C('ROOTPATH').'/'.APP_PATH.'Release/Cms/'.$appData['app'].'Cms.class.php'); $phpCode=preg_replace('/public\s*\$_params\=[\s\S]+?\);/i', 'public $_params='.$_params.';', $phpCode); if(!empty($funcPhp)){ $phpCode=preg_replace('/\}\s*\?\>/',"\r\n".$funcPhp."\r\n}\r\n?>",$phpCode); } } if(!empty($phpCode)){ $success=$mapp->addCms(array('app'=>$appData['app'],'name'=>$appData['name']),$phpCode); if($success){ $this->success('创建成功',U('Develop/releaseCms?app='.$appData['app'])); }else{ $this->error('创建失败'); } }else{ $this->error('代码错误'); } } public function get_cms_class($appName){ $appName=ucfirst($appName); $appPath=C('ROOTPATH').'/'.APP_PATH.'Release/Cms/'.$appName.'Cms.class.php'; if(file_exists($appPath)){ $cmsClass='Release\\Cms\\'.$appName.'Cms'; $cmsClass=new $cmsClass(); return $cmsClass; }else{ return null; } } }