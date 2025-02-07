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

namespace skycaiji\install\event;
use think\db\Query;
/*数据库升级操作*/
class UpgradeDb extends UpgradeDbVers{
	/*后台更新时升级入口：当所有更新文件下载替换完毕，最后需要升级数据库*/
	public function run(){
	    \util\Tools::clear_runtime_dir();
		$url=url('install/upgrade/admin',null,true,true);
		header('Location:'.$url);
		exit();
	}
	/*正常的升级入口*/
	public function upgrade(){
		set_time_limit(0);
		\util\Tools::clear_runtime_dir();
		\util\Tools::load_data_config();
		$result=$this->execute_upgrade();
		if($result['success']){
			$mconfig=model('common/Config');
			$mconfig->setVersion($this->get_skycaiji_version());
		}
		return $result;
	}
	/*获取版本号*/
	public function get_skycaiji_version(){
		$newProgramConfig=file_get_contents(config('app_path').'/common.php');
		if(preg_match('/[\'\"]SKYCAIJI_VERSION[\'\"]\s*,\s*[\'\"](?P<v>[\d\.]+?)[\'\"]/i', $newProgramConfig,$programVersion)){
			$programVersion=$programVersion['v'];
		}else{
			$programVersion='';
		}
		return $programVersion;
	}
	
	public function execute_upgrade(){
		$mconfig=model('common/Config');
		$dbVersion=$mconfig->getVersion();
		$fileVersion=$this->get_skycaiji_version();
		
		if(empty($dbVersion)){
		    return return_result('未获取到数据库中的版本号');
		}
		if(empty($fileVersion)){
		    return return_result('未获取到项目文件的版本号');
		}
		
		if(version_compare($dbVersion,$fileVersion)>=0){
		    
		    return return_result('数据库已是最新版本，无需更新',true);
		}
		/*找出更新函数*/
		$methods=get_class_methods($this);
		$upgradeDbMethods=array();
		foreach ($methods as $method){
			if(preg_match('/^upgrade_db_to(?P<ver>(\_\d+)+)$/',$method,$toVer)){
				
				$toVer=str_replace('_', '.', trim($toVer['ver'],'_'));
				if(version_compare($toVer,$dbVersion)>=1){
					
					if(version_compare($toVer,$fileVersion)<=0){
						
						$upgradeDbMethods[$toVer]=$method;
					}
				}
			}
		}
		if(empty($upgradeDbMethods)){
		    return return_result('暂无更新',true);
		}
		ksort($upgradeDbMethods);
		foreach ($upgradeDbMethods as $newVer=>$upMethod){
			try {
				$this->$upMethod();
				
				$mconfig->setVersion($newVer);
			}catch (\Exception $ex){
			    return return_result($ex->getMessage());
			}
		}
		
		\util\Tools::clear_runtime_dir();
		
		return return_result('升级完毕',true);
	}
	
	
	public function upgrade_db_to_2_9(){
	    
	    $dbTables=db()->getConnection()->getTables(config('database.database'));
	    init_array($dbTables);
	    $dbPrefix=config('database.prefix');
	    
	    $allowTbs=array('api_app','app','cache','collected','collected_info','collector','config','dataapi','dataset','func_app','provider','proxy_group','proxy_ip','release','release_app','rule','task','task_timer','taskgroup','user','usergroup');
	    foreach ($allowTbs as $k=>$v){
	        $allowTbs[$k]=strtolower($dbPrefix.$v);
	    }
	    foreach ($dbTables as $k=>$v){
	        $v=strtolower($v);
	        if(!in_array($v,$allowTbs)&&stripos($v,$dbPrefix.'cache_')!==0){
	            
	            unset($dbTables[$k]);
	        }
	    }
	    $dbTables=array_values($dbTables);
	    foreach ($dbTables as $dbTable){
	        \util\Db::to_innodb($dbTable);
	    }
	}
}
?>