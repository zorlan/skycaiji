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

namespace skycaiji\admin\controller;

use skycaiji\admin\model\DbCommon;
class Release extends BaseController{
	/*发布设置*/
	public function setAction(){
		$taskId=input('task_id/d',0);
		$releaseId=input('release_id/d',0);
		$mtask=model('Task');
		$mrele=model('Release');
		$taskData=$mtask->getById($taskId);
		if(empty($taskData)){
			$this->error(lang('task_error_empty_task'));
		}
		$releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
		if(request()->isPost()){
			$newData=array('task_id'=>$taskData['id'],'addtime'=>NOW_TIME,'config'=>array());
			if($releaseId>0){
				
				$importRele=$mrele->where(array('id'=>$releaseId))->find();
				$newData['module']=$importRele['module'];
				$newData['config']=$importRele['config'];
			}else{
				
				$newData['module']=input('module','','strtolower');
				if(empty($newData['module'])){
					$this->error(lang('rele_error_null_module'));
				}
				$releObj=controller('admin/R'.$newData['module'],'event');
				$newData['config']=$releObj->setConfig($newData['config']);
				$newData['config']=serialize($newData['config']);
			}
			if(empty($newData['module'])){
				$this->error(lang('rele_error_null_module'));
			}
			
			if(empty($releData)){
				
				$mrele->isUpdate(false)->allowField(true)->save($newData);
				$releId=$mrele->id;
			}else{
				
				$releId=$releData['id'];
				$mrele->strict(false)->where(array('id'=>$releData['id']))->update($newData);
			}
			if($releId>0){
				$this->success(lang('op_success'),'Release/set?task_id='.$taskId);
    		}else{
    			$this->error(lang('op_failed'));
    		}
		}else{
			$GLOBALS['content_header']=lang('rele_set');
			$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Task/edit?id='.$taskData['id']),'title'=>lang('task').lang('separator').$taskData['name']),lang('rele_set')));

			$this->assign('taskData',$taskData);
			if(!empty($releData)){
				
				$releData['config']=unserialize($releData['config']);
				$config=$releData['config'];
				$this->assign('config',$config);
				$this->assign('releData',$releData);
			}
			$apiRootUrl=config('root_website');

			if(stripos(\think\Request::instance()->root(),'/index.php?s=')!==false){
				$apiRootUrl.='/index.php?s=';
			}elseif(stripos(\think\Request::instance()->root(),'/index.php')!==false){
				$apiRootUrl.='/index.php';
			}

			$releBase=new \skycaiji\admin\event\ReleaseBase();
			$collFields=$releBase->get_coll_fields($taskData['id'],$taskData['module']);
			
			$this->assign('apiRootUrl',$apiRootUrl);
			$this->assign('collFields',$collFields);
			return $this->fetch();
		}
	}
	/*导入配置*/
	public function importAction(){
		$page=max(1,input('p/d',0));
		$mrele=model('Release');
		$mtask=model('Task');
    	$limit=20;
    	$cond=array();
    	$taskCond=array();
    	$count=$mrele->where($cond)->count();
		$releList=$mrele->where($cond)->order('id desc')->paginate($limit,false,paginate_auto_config());

		$pagenav = $releList->render();
		$this->assign('pagenav',$pagenav);
		$releList=$releList->all();
		$releList=empty($releList)?array():$releList;
		if($count>0){
			$taskIds=array();
			foreach ($releList as $rele){
				$taskIds[$rele['task_id']]=$rele['task_id'];
			}
			if(!empty($taskIds)){
				
				$taskCond['id']=array('in',$taskIds);
				$taskNames=$mtask->where($taskCond)->column('name','id');
				$this->assign('taskNames',$taskNames);
			}
		}

		$this->assign('releList',$releList);
		return $this->fetch();
	}
	/*检测cms信息*/
	public function cmsDetectAction(){
		$acms=controller('admin/Rcms','event');
		$acms->cms_name_list(config('root_path'));
		$acms->cms_name_list(config('root_path').'/../');
		$prevPath=config('root_path').'/../';
		if(is_dir($prevPath)){
			
			$dp=dir($prevPath);
			
			while(($curPath=$dp->read())!=false){
				if($curPath!='.'&&$curPath!='..'){
					$curPath=$prevPath.$curPath;
					if(is_dir($curPath)){
						
						$acms->cms_name_list($curPath);
					}
				}
			}
			$dp->close();
		}
		$nextPath=config('root_path').'/';
		if(is_dir($nextPath)){
			
			$dp=dir($nextPath);
			while(($curPath=$dp->read())!=false){
				if($curPath!='.'&&$curPath!='..'){
					$curPath=$nextPath.$curPath;
					if(is_dir($curPath)){
						
						$acms->cms_name_list($curPath);
					}
				}
			}
			$dp->close();
		}
		$cmsList=$acms->cms_name_list(null,true);
		if(!empty($cmsList)){
			$this->success('',null,$cmsList);
		}else{
			$this->error(lang('rele_error_detect_null'));
		}
	}
	/*
	 * cms程序绑定数据
	 * 设置可不先入库进行测试绑定
	 * */
	public function cmsBindAction(){
		$cmsSet=input('cms/a');
		$taskId=input('task_id/d',0);
		$cmsPath=$cmsSet['path'];
		if(empty($cmsPath)){
			$this->error('cms路径不能为空');
		}
		
		$acms=controller('admin/Rcms','event');
		$cmsName=$acms->cms_name($cmsPath);
		if(empty($cmsName)){
			$this->error('未知的cms程序，请确保路径存在，如需指定CMS程序请在路径结尾加上@CMS程序名，例如：@discuz');
		}
		$cmsApp=$cmsSet['app'];

		
		$cmsApps=model('ReleaseApp')->where(array('module'=>'cms','app'=>array('like',addslashes($cmsName).'%')))->order('uptime desc')->column('*');
		$cmsApps=is_array($cmsApps)?$cmsApps:array();
		if(!empty($cmsApps)){
			$cmsApps=array_values($cmsApps);
		}





		
		if(!empty($cmsApp)){
			$cmsApp=ucfirst($cmsApp);
			if(!model('ReleaseApp')->appFileExists($cmsApp,'cms')){
				
				if(model('ReleaseApp')->oldFileExists($cmsApp,'Cms')){
					
					$cmsError=lang('release_upgrade');
				}else{
					$cmsError='抱歉，插件文件不存在';
				}
				$this->assign('cmsError',$cmsError);
			}else{
				
				try {
					$releCms=model('ReleaseApp')->appImportClass($cmsApp,'cms');
					$releCms->init($cmsPath,array('task_id'=>$taskId));
					$releCms->runBind();
				} catch (\Exception $ex) {
					$releCms=null;
					$this->error($ex->getMessage());
				}
				$this->assign('releCms',$releCms);
			}
		}
		$this->assign('cmsName',$cmsName);
		$this->assign('cmsApps',$cmsApps);
		$this->assign('cmsApp',$cmsApp);
		return $this->fetch('cmsBind');
	}
	public function testAction(){
		set_time_limit(600);
		$releId=input('id/d',0);
		
		$releData=model('Release')->getById($releId);
		if(empty($releData)){
			$this->echo_msg(lang('rele_error_empty_rele'));
    		exit();
		}
		
		$taskData=model('Task')->getById($releData['task_id']);
		if(empty($taskData)){
			$this->echo_msg(lang('task_error_empty_task'));
    		exit();
		}
		model('Task')->loadConfig($taskData['config']);
		
		$collData=model('Collector')->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
		if(empty($collData)){
			$this->echo_msg(lang('coll_error_empty_coll'));
    		exit();
		}
		
		$acoll=controller('admin/C'.$collData['module'],'event');
		$acoll->init($collData);
		$fieldsList=$acoll->collect(1);
		if(empty($fieldsList)||!is_array($fieldsList)){
			$this->echo_msg('没有采集到数据','orange');
		}else{
			
			$releObj=controller('admin/R'.strtolower($releData['module']),'event');
			$releObj->init($releData);
			if('api'==$releData['module']){
				
				$releObj->config['api']['cache_time']=0;
			}
			$releObj->export($fieldsList);
		}
	}
	/*读取数据库表*/
	public function dbTablesAction(){
		$releId=input('id/d',0);
		$mrele=model('Release');
		$releData=$mrele->where(array('id'=>$releId))->find();
		if(empty($releData)){
			$this->error(lang('rele_error_empty_rele'));
		}
		$config=unserialize($releData['config']);
		$db_config=controller('admin/Rdb','event')->get_db_config($config['db']);
		try{
			$mdb=new DbCommon($db_config);
			$tables=$mdb->getTables();
		}catch(\Exception $ex){
			$msg=$this->trans_db_msg($ex->getMessage());
			$this->error($msg);
		}
		$this->assign('tables',$tables);
		$html=$this->fetch('dbTables');
		$this->success($html->getContent());
	}
	
	/*测试连接数据库*/
	public function dbConnectAction(){
		$op=input('op');
		$db=input('db/a','','trim');
		
		$no_check=array('db_pwd');
		if('db_names'==$op){
			
			$no_check[]='db_name';
			unset($db['name']);
		}

		$db_config=controller('admin/Rdb','event')->get_db_config($db);
		foreach ($db_config as $k=>$v){
			if(empty($v)&&!in_array($k,$no_check)){
				$this->error(lang('error_null_input',array('str'=>lang('rele_'.$k))));
			}
		}
		$msgError=false;
		$msgSuccess=false;
		try{
			$mdb=new DbCommon($db_config);
			
			if(!$mdb->db()){
				$msgError='数据库连接错误';
			}else{
				if('db_names'==$op){
					
					$dbNames=array();
					if($db_config['db_type']=='mysql'){
						$dbsData=$mdb->db()->query('show databases');
						foreach ($dbsData as $dbDt){
							$dbNames[$dbDt['Database']]=$dbDt['Database'];
						}
					}elseif($db_config['db_type']=='oracle'){
						$dbsData=$mdb->db()->query('SELECT * FROM v$database');
						foreach ($dbsData as $dbDt){
							$dbNames[$dbDt['NAME']]=$dbDt['NAME'];
						}
					}elseif($db_config['db_type']=='sqlsrv'){
						$dbsData=$mdb->db()->query('select * from sysdatabases');
						foreach ($dbsData as $dbDt){
							$dbNames[$dbDt['name']]=$dbDt['name'];
						}
					}
					if(empty($dbNames)){
						$msgError='没有数据库';
					}else{
						sort($dbNames);
						$this->assign('dbNames',$dbNames);
						$html=$this->fetch('release/dbNames');
						$msgSuccess=$html->getContent();
					}
				}else{
					$dbTables=$mdb->getTables();
					$msgSuccess=lang('rele_success_db_ok');
				}
			}
		}catch(\Exception $ex){
			$msg=$this->trans_db_msg($ex->getMessage());
			$this->error($msg);
		}
		if($msgError){
			$this->error($msgError);
		}
		if($msgSuccess){
			$this->success($msgSuccess);
		}
	}
	/*数据表绑定数据*/
	public function dbTableBindAction(){
		$releId=input('id/d',0);
		$table=input('table');
		$tables=explode(',', $table);
		$tables=array_filter($tables);
		$tables=array_values($tables);
		if(empty($table)){
			$this->error('请选择表');
		}
		$mrele=model('Release');
		$mtask=model('Task');
		$mcoll=model('Collector');
		$releData=$mrele->where(array('id'=>$releId))->find();
		if(empty($releData)){
			$this->error(lang('rele_error_empty_rele'));
		}
		$config=unserialize($releData['config']);
		$adb=controller('admin/Rdb','event');
		$db_config=$adb->get_db_config($config['db']);
		
		try {
			
			$mdb=new DbCommon($db_config);
			$fields=array();
			$field_values=array();
			foreach ($tables as $tbName){
				$fields[$tbName]=$mdb->getFields($tbName);
				
				if(!empty($config['db_table']['field'][$tbName])){
					$tableFields=$config['db_table']['field'][$tbName];
					if(!empty($tableFields)){
						
						$issetFields=array();
						foreach ($fields[$tbName] as $k=>$v){
							if(isset($tableFields[$k])){
								$issetFields[$k]=$v;
							}
						}
						$fields[$tbName]=array_merge($issetFields,$fields[$tbName]);
					}
					
					$field_values[$tbName]['field']=$tableFields;
					$field_values[$tbName]['custom']=$config['db_table']['custom'][$tbName];
				}
			}
			
			$taskData=$mtask->getById($releData['task_id']);
			
			if(!empty($taskData)){
				$collFields=$adb->get_coll_fields($taskData['id'], $taskData['module']);
			}
		}catch (\Exception $ex){
			$dbMsg=$this->trans_db_msg($ex->getMessage());
			$this->error($dbMsg);
		}
		
		$this->assign('collFields',$collFields);
		$this->assign('tables',$tables);
		$this->assign('fields',$fields);
		$this->assign('field_values',$field_values);
		return $this->fetch('dbTableBind');
	}
    /*翻译数据库错误信息*/
    public function trans_db_msg($msg){
    	$msg=lang('rele_error_db').str_replace('Unknown database', lang('error_unknown_database'), $msg);
    	return $msg;
    }
}