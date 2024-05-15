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
	
	
	
	public function upgrade_db_to_2_7(){
	    $ver=db()->query('SELECT VERSION() as ver;');
	    if(is_array($ver)&&is_array($ver[0])){
	        $ver=$ver[0]['ver'];
	    }
	    if(empty($ver)||version_compare($ver,'5.5.3','<')){
	        throw new \Exception('请使用5.5.3及以上版本的mysql数据库');
	    }
	    
	    db()->execute('set names utf8mb4;');
	    $db_prefix=config('database.prefix');
	    $db_name=config('database.database');
	    $tableStatus=db()->query("SHOW TABLE STATUS FROM `{$db_name}` LIKE '{$db_prefix}%'");
	    foreach ($tableStatus as $v){
	        if(is_array($v)){
	            $v=\util\Funcs::array_keys_to_lower($v);
	            $tbName=$v['name'];
	            if($tbName&&stripos($v['collation'],'utf8mb4')!==0){
	                
	                if($tbName==($db_prefix.'provider')){
	                    $dbIndexes=db()->query("SHOW INDEX FROM `{$tbName}`");
	                    if($this->check_exists_index('domain',$dbIndexes)){
	                        db()->execute("ALTER TABLE `{$tbName}` DROP INDEX `domain`");
	                        db()->execute("ALTER TABLE `{$tbName}` ADD INDEX `domain`(`domain`(250))");
	                    }
	                }
	                if($tbName==($db_prefix.'user')){
	                    $dbIndexes=db()->query("SHOW INDEX FROM `{$tbName}`");
	                    if($this->check_exists_index('username',$dbIndexes)){
	                        db()->execute("ALTER TABLE `{$tbName}` DROP INDEX `username`");
	                        db()->execute("ALTER TABLE `{$tbName}` ADD CONSTRAINT `username` UNIQUE(`username`(250))");
	                    }
	                    if($this->check_exists_index('email',$dbIndexes)){
	                        db()->execute("ALTER TABLE `{$tbName}` DROP INDEX `email`");
	                        db()->execute("ALTER TABLE `{$tbName}` ADD INDEX `email`(`email`(250))");
	                    }
	                }
	                db()->execute("ALTER TABLE `{$tbName}` CONVERT TO CHARACTER SET utf8mb4;");
	            }
	        }
	    }
	    
	    $dataset_table=$db_prefix.'dataset';
	    $exists=db()->query("show tables like '{$dataset_table}'");
	    if(empty($exists)){
	        
$addTable=<<<EOF
CREATE TABLE `{$dataset_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `sort` mediumint(9) NOT NULL DEFAULT '0',
  `desc` text,
  `config` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	    
	    
	    $dataapi_table=$db_prefix.'dataapi';
	    $exists=db()->query("show tables like '{$dataapi_table}'");
	    if(empty($exists)){
	        
	        $addTable=<<<EOF
CREATE TABLE `{$dataapi_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `route` varchar(100) NOT NULL DEFAULT '',
  `sort` mediumint(9) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `desc` text,
  `ds_id` int(11) NOT NULL DEFAULT '0',
  `config` mediumtext,
  PRIMARY KEY (`id`),
  KEY `ix_sort` (`sort`),
  KEY `ix_ds_id` (`ds_id`),
  KEY `ix_route` (`route`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	    
	    
	    $collected_info_table=$db_prefix.'collected_info';
	    $exists=db()->query("show tables like '{$collected_info_table}'");
	    if(empty($exists)){
	        
$addTable=<<<EOF
CREATE TABLE `{$collected_info_table}` (
  `id` int(11) NOT NULL DEFAULT '0',
  `url` text,
  `target` text,
  `desc` text,
  `error` text,
  KEY `ix_id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	    
	    $collected_table=$db_prefix.'collected';
	    $collectedCount=db()->table($collected_table)->count();
	    if($collectedCount>0){
	        do{
	            $maxId=db()->table($collected_info_table)->max('id');
	            $maxId=intval($maxId);
	            $exists=db()->table($collected_table)->field('id')->where('id','>',$maxId)->limit(1)->find();
	            if(!empty($exists)){
	                
	                $sql="INSERT INTO `{$collected_info_table}` (`id`,`url`,`target`,`desc`,`error`) SELECT `id`,`url`,`target`,`desc`,`error` FROM {$collected_table} WHERE id>{$maxId} order by id asc limit 1000";
	                db()->execute($sql);
	            }else{
	                break;
	            }
	        }while(1==1);
	    }
	    
	    $this->table_add_columns('collected',array('status'=>"tinyint(1) NOT NULL DEFAULT '0'"));
	    $this->table_add_indexes('collected',array('ix_status'=>'status'));
	    $dbColumns=db()->query("SHOW COLUMNS FROM `{$collected_table}`");
	    if($this->check_exists_field('target', $dbColumns)){
	        db()->execute("update `{$collected_table}` set `status`=1 where `target`<>'';");
	    }
	    
	    foreach (array('url','target','desc','error') as $fname){
	        if($this->check_exists_field($fname, $dbColumns)){
	            db()->execute("ALTER TABLE `{$collected_table}` DROP COLUMN `{$fname}`;");
	        }
	    }
	}
	
	public function upgrade_db_to_2_7_1(){
	    $db_prefix=config('database.prefix');
	    $apiapp_table=$db_prefix.'api_app';
	    $exists=db()->query("show tables like '{$apiapp_table}'");
	    if(empty($exists)){
	        
	        $addTable=<<<EOF
CREATE TABLE `{$apiapp_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(20) NOT NULL DEFAULT '',
  `app` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `desc` text,
  `enable` tinyint(1) NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `uptime` int(11) NOT NULL DEFAULT '0',
  `provider_id` int(11) NOT NULL DEFAULT '0',
  `config` mediumtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_app` (`app`),
  UNIQUE KEY `module_app` (`module`,`app`),
  KEY `module_enable` (`module`,`enable`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	}
}
?>