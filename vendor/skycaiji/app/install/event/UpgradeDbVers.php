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
use skycaiji\common\controller\BaseController;
use think\db\Query;
/*数据库升级操作*/
class UpgradeDbVers extends BaseController{
    /*键名小写*/
    public function keys_to_lower($arr){
        $arr=\util\Funcs::array_keys_to_lower($arr);
        return $arr;
    }
	/*判断存在索引*/
	public function check_exists_index($name,$indexes){
		if(empty($name)){
			return false;
		}
		$exists_index=false;
		foreach ($indexes as $k=>$v){
		    $v=$this->keys_to_lower($v);
			if(strcasecmp($name,$v['key_name'])==0){
				$exists_index=true;
				break;
			}
		}
		return $exists_index;
	}
	/*判断存在字段*/
	public function check_exists_field($name,$columns){
		if(empty($name)){
			return false;
		}
		
		$exists_column=false;
		foreach ($columns as $k=>$v){
		    $v=$this->keys_to_lower($v);
			if(strcasecmp($name,$v['field'])==0){
				$exists_column=true;
				break;
			}
		}
		return $exists_column;
	}
	/*修改字段类型*/
	public function modify_field_type($field,$type,$modifySql,$columns){
	    foreach ($columns as $v){
	        $v=$this->keys_to_lower($v);
			if(strcasecmp($field,$v['field'])==0){
				
				if(strcasecmp($type,$v['type'])!=0){
					
					db()->execute($modifySql);
				}
				break;
			}
		}
	}
	/*添加字段*/
	protected function table_add_columns($table,$columns){
	    if($table&&is_array($columns)&&$columns){
	        $db_prefix=config('database.prefix');
	        $dbColumns=db()->query("SHOW COLUMNS FROM `{$db_prefix}{$table}`");
	        foreach($columns as $cname=>$cset){
	            if($cname&&$cset){
	                if(!$this->check_exists_field($cname,$dbColumns)){
	                    
	                    db()->execute("alter table `{$db_prefix}{$table}` add `{$cname}` {$cset}");
	                }
	            }
	        }
	    }
	}
	/*添加索引*/
	protected function table_add_indexes($table,$indexes){
	    if($table&&is_array($indexes)&&$indexes){
	        $db_prefix=config('database.prefix');
	        $dbIndexes=db()->query("SHOW INDEX FROM `{$db_prefix}{$table}`");
	        foreach($indexes as $iname=>$iset){
	            if($iname&&$iset){
	                if(!$this->check_exists_index($iname,$dbIndexes)){
	                    
	                    db()->execute("ALTER TABLE `{$db_prefix}{$table}` ADD INDEX {$iname} ({$iset})");
	                }
	            }
	        }
	    }
	}
	
	public function upgrade_db_to_1_3(){
		
		$db_prefix=config('database.prefix');
		$proxy_table=$db_prefix.'proxy_ip';
		$exists=db()->query("show tables like '{$proxy_table}'");
		if(empty($exists)){
			
			$addTable=<<<EOF
CREATE TABLE `{$proxy_table}` (
  `ip` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL DEFAULT '',
  `pwd` varchar(100) NOT NULL DEFAULT '',
  `invalid` tinyint(4) NOT NULL DEFAULT '0',
  `failed` int(11) NOT NULL DEFAULT '0',
  `num` int(11) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;
			db()->execute($addTable);
		}
	
		/*修改表*/
		
		$columns_collected=db()->query("SHOW COLUMNS FROM `{$db_prefix}collected`");
		if(!$this->check_exists_field('titleMd5', $columns_collected)){
			
			db()->execute("alter table `{$db_prefix}collected` add `titleMd5` varchar(32) NOT NULL DEFAULT ''");
		}
	
		$indexes_collected=db()->query("SHOW INDEX FROM `{$db_prefix}collected`");
		if(!$this->check_exists_index('ix_titlemd5', $indexes_collected)){
			
			db()->execute("ALTER TABLE `{$db_prefix}collected` ADD INDEX ix_titlemd5 ( `titleMd5` )");
		}
		
		$columns_task=db()->query("SHOW COLUMNS FROM `{$db_prefix}task`");
		if(!$this->check_exists_field('config', $columns_task)){
			
			db()->execute("alter table `{$db_prefix}task` add `config` mediumtext");
		}
		
		$columns_collector=db()->query("SHOW COLUMNS FROM `{$db_prefix}collector`");
		$this->modify_field_type('config', 'mediumtext', "alter table `{$db_prefix}collector` modify column `config` mediumtext", $columns_collector);
		
		$columns_release=db()->query("SHOW COLUMNS FROM `{$db_prefix}release`");
		$this->modify_field_type('config', 'mediumtext', "alter table `{$db_prefix}release` modify column `config` mediumtext", $columns_release);
		
		$columns_rule=db()->query("SHOW COLUMNS FROM `{$db_prefix}rule`");
		$this->modify_field_type('config', 'mediumtext', "alter table `{$db_prefix}rule` modify column `config` mediumtext", $columns_rule);
	}
	public function upgrade_db_to_2_2(){
		$db_prefix=config('database.prefix');
		$provider_table=$db_prefix.'provider';
		$exists=db()->query("show tables like '{$provider_table}'");
		if(empty($exists)){
			
			$addTable=<<<EOF
CREATE TABLE `{$provider_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(255) NOT NULL DEFAULT '',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `enable` tinyint(4) NOT NULL DEFAULT '0',
  `sort` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `domain` (`domain`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;
			db()->execute($addTable);
		}
		
		
		$app_table=$db_prefix.'app';
		$exists=db()->query("show tables like '{$app_table}'");
		if(empty($exists)){
			
			$addTable=<<<EOF
CREATE TABLE `{$app_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `app` varchar(100) NOT NULL,
  `provider_id` int(11) NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `uptime` int(11) NOT NULL DEFAULT '0',
  `config` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;
			db()->execute($addTable);
		}
		
		
		
		/*修改表*/
		$columns_user=db()->query("SHOW COLUMNS FROM `{$db_prefix}user`");
		if(!$this->check_exists_field('salt', $columns_user)){
			
			db()->execute("alter table `{$db_prefix}user` add `salt` varchar(50) NOT NULL DEFAULT ''");
		}
		
		$columns_rule=db()->query("SHOW COLUMNS FROM `{$db_prefix}rule`");
		if(!$this->check_exists_field('provider_id', $columns_rule)){
			
			db()->execute("alter table `{$db_prefix}rule` add `provider_id` int(11) NOT NULL DEFAULT '0'");
		}

		$columns_rapp=db()->query("SHOW COLUMNS FROM `{$db_prefix}release_app`");
		if(!$this->check_exists_field('provider_id', $columns_rapp)){
			
			db()->execute("alter table `{$db_prefix}release_app` add `provider_id` int(11) NOT NULL DEFAULT '0'");
		}
		
		$indexes_release_app=db()->query("SHOW INDEX FROM `{$db_prefix}release_app`");
		if(!$this->check_exists_index('ix_app', $indexes_release_app)){
			
			db()->execute("ALTER TABLE `{$db_prefix}release_app` ADD unique ix_app ( `app` )");
		}
	}
	public function upgrade_db_to_2_3(){
		$db_prefix=config('database.prefix');
		$func_app_table=$db_prefix.'func_app';
		$exists=db()->query("show tables like '{$func_app_table}'");
		if(empty($exists)){
			
			$addTable=<<<EOF
CREATE TABLE `{$func_app_table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(20) NOT NULL DEFAULT '',
  `app` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `desc` text,
  `enable` tinyint(1) NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `uptime` int(11) NOT NULL DEFAULT '0',
  `provider_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_app` (`app`),
  UNIQUE KEY `module_app` (`module`,`app`),
  KEY `module_enable` (`module`,`enable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;
			db()->execute($addTable);
		}
		
		

		$columns_proxyip=db()->query("SHOW COLUMNS FROM `{$db_prefix}proxy_ip`");
		if(!$this->check_exists_field('type', $columns_proxyip)){
			
			db()->execute("alter table `{$db_prefix}proxy_ip` add `type` varchar(20) NOT NULL DEFAULT ''");
		}
		if(!$this->check_exists_field('addtime', $columns_proxyip)){
			
			db()->execute("alter table `{$db_prefix}proxy_ip` add `addtime` int(11) NOT NULL DEFAULT '0'");
		}
		if(!$this->check_exists_field('no', $columns_proxyip)){
			
			db()->execute("alter table `{$db_prefix}proxy_ip` add `no` bigint(20) NOT NULL");
		}
		
		$indexes_proxyip=db()->query("SHOW INDEX FROM `{$db_prefix}proxy_ip`");
		if(!$this->check_exists_index('no', $indexes_proxyip)){
			
			db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX no ( `no` )");
		}
		
		db()->execute("alter table `{$db_prefix}proxy_ip` modify `no` bigint auto_increment");
		
		if(!$this->check_exists_index('addtime_no', $indexes_proxyip)){
			
			db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX addtime_no ( `addtime`,`no` )");
		}
		if(!$this->check_exists_index('ix_num', $indexes_proxyip)){
			
			db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX ix_num ( `num` )");
		}
		if(!$this->check_exists_index('ix_time', $indexes_proxyip)){
			
			db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX ix_time ( `time` )");
		}
	}
	public function upgrade_db_to_2_4(){
	    $db_prefix=config('database.prefix');
	    $indexes_proxyip=db()->query("SHOW INDEX FROM `{$db_prefix}proxy_ip`");
	    
	    if($this->check_exists_index('ix_num', $indexes_proxyip)){
	        
	        db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` DROP INDEX ix_num");
	    }
	    if(!$this->check_exists_index('num_no', $indexes_proxyip)){
	        
	        db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX num_no ( `num`,`no` )");
	    }
	    
	    if($this->check_exists_index('ix_time', $indexes_proxyip)){
	        
	        db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` DROP INDEX ix_time");
	    }
	    if(!$this->check_exists_index('time_no', $indexes_proxyip)){
	        
	        db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX time_no ( `time`,`no` )");
	    }
	    
	    $columns_provider=db()->query("SHOW COLUMNS FROM `{$db_prefix}provider`");
	    if(!$this->check_exists_field('authkey', $columns_provider)){
	        
	        db()->execute("alter table `{$db_prefix}provider` add `authkey` varchar(255) NOT NULL DEFAULT ''");
	    }
	}
	public function upgrade_db_to_2_5(){
	    $db_prefix=config('database.prefix');
	    $task_timer_table=$db_prefix.'task_timer';
	    $exists=db()->query("show tables like '{$task_timer_table}'");
	    if(empty($exists)){
	        
	        $addTable=<<<EOF
CREATE TABLE `{$task_timer_table}` (
  `task_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(10) NOT NULL DEFAULT '',
  `data` varchar(10) NOT NULL DEFAULT '',
  KEY `ix_tid` (`task_id`),
  KEY `ix_name` (`name`,`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;
	        db()->execute($addTable);
	    }
	}
	public function upgrade_db_to_2_5_2(){
	    $db_prefix=config('database.prefix');
	    $proxy_group_table=$db_prefix.'proxy_group';
	    $exists=db()->query("show tables like '{$proxy_group_table}'");
	    if(empty($exists)){
	        
	        $addTable=<<<EOF
CREATE TABLE `{$proxy_group_table}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `sort` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;
	        db()->execute($addTable);
	    }
	    $columns_proxyip=db()->query("SHOW COLUMNS FROM `{$db_prefix}proxy_ip`");
	    if(!$this->check_exists_field('group_id', $columns_proxyip)){
	        
	        db()->execute("alter table `{$db_prefix}proxy_ip` add `group_id` int(11) NOT NULL DEFAULT '0'");
	    }
	    $indexes_proxyip=db()->query("SHOW INDEX FROM `{$db_prefix}proxy_ip`");
	    if(!$this->check_exists_index('gid_no', $indexes_proxyip)){
	        
	        db()->execute("ALTER TABLE `{$db_prefix}proxy_ip` ADD INDEX gid_no ( `group_id`,`no` )");
	    }
	    
	    
	    $lockFileOld1=config('root_path').'/SkycaijiApp/install/data/install.lock';
	    $lockFileOld2=config('app_path').'/install/data/install.lock';
	    if(file_exists($lockFileOld1)||file_exists($lockFileOld2)){
	        write_dir_file(config('root_path').'/data/install.lock', '1');
	    }
	}
	public function upgrade_db_to_2_6_1(){
	    $this->table_add_columns('collected', array('contentMd5'=>"varchar(32) NOT NULL DEFAULT ''"));
	    $this->table_add_indexes('collected', array('ix_contentmd5'=>"`contentMd5`"));
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
	            $v=$this->keys_to_lower($v);
	            $tbName=$v['name'];
	            if($tbName&&stripos($v['collation'],'utf8mb4')!==0){
	                
	                if($tbName==($db_prefix.'provider')){
	                    $dbIndexes=db()->query("SHOW INDEX FROM `{$tbName}`");
	                    if($this->check_exists_index('domain',$dbIndexes)){
	                        db()->execute("ALTER TABLE `{$tbName}` DROP INDEX `domain`");
	                        db()->execute("ALTER TABLE `{$tbName}` ADD INDEX `domain`(`domain`(191))");
	                    }
	                }
	                if($tbName==($db_prefix.'user')){
	                    $dbIndexes=db()->query("SHOW INDEX FROM `{$tbName}`");
	                    if($this->check_exists_index('username',$dbIndexes)){
	                        db()->execute("ALTER TABLE `{$tbName}` DROP INDEX `username`");
	                        db()->execute("ALTER TABLE `{$tbName}` ADD CONSTRAINT `username` UNIQUE(`username`(191))");
	                    }
	                    if($this->check_exists_index('email',$dbIndexes)){
	                        db()->execute("ALTER TABLE `{$tbName}` DROP INDEX `email`");
	                        db()->execute("ALTER TABLE `{$tbName}` ADD INDEX `email`(`email`(191))");
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	}
}
?>