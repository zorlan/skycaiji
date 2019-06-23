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
use skycaiji\admin\model\DbCommon;
class Tool extends BaseController {
	/*文件管理*/
	public function fileManagerAction(){
		$GLOBALS['content_header']='文件管理';
		$GLOBALS['breadcrumb']=breadcrumb(array('文件管理'));
		return $this->fetch('fileManager');
	}
	/*elfinder文件管理器*/
	public function elfinderAction(){
		$op=input('op');
		if(empty($op)){
			
			$elfinderUrl=config('root_website').'/vendor/studio-42/elfinder';
			$this->assign('elfinderUrl',$elfinderUrl);
			return $this->fetch();
		}elseif('connect'==$op){
			
			
			\elFinder::$netDrivers['ftp'] = 'FTP';
			
			
			
			$opts = array(
				
				'roots' => array(
					
					array(
						'driver'        => 'LocalFileSystem',           
						'path'          => config('root_path').'/data',                 
						'URL'           => config('root_website').'/data', 
						'uploadDeny'    => array('all'),                
						'uploadAllow'   => array('image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'text/plain'), 
						'uploadOrder'   => array('deny', 'allow'),      
						'attributes' => array (
							array (
								'pattern' => '/\.php$/i',
								'read' => false,
								'write' => false,
								'hidden' => true,
								'locked' => false
							)
						)
					)
				)
			);
			
			$connector = new \elFinderConnector(new \elFinder($opts));
			$connector->run();
		}
	}
	
	/*日志列表*/
	public function logsAction(){
		$logPath=realpath(config('root_path').'/runtime/log');
		$logList=array();
		if(!empty($logPath)){
			$paths=scandir($logPath);
			if(!empty($paths)){
				foreach ($paths as $path){
					if($path!='.'&&$path!='..'){
						$pathFiles=scandir($logPath.'/'.$path);
						if(!empty($pathFiles)){
							foreach ($pathFiles as $pathFile){
								if($pathFile!='.'&&$pathFile!='..'){
									$logList[$path][]=array(
										'name'=>$pathFile,
										'file'=>realpath($logPath.'/'.$path.'/'.$pathFile),
									);
								}
							}
						}
					}
				}
			}
		}
		$GLOBALS['content_header']='错误日志';
		$GLOBALS['breadcrumb']=breadcrumb(array('错误日志'));
		$this->assign('logList',$logList);
		return $this->fetch();
	}
	/*读取日志*/
	public function logAction(){
		$file=realpath(input('file'));
		$logPath=realpath(config('root_path').'/runtime/log');
		if(stripos($file,$logPath)===false){
			$this->error('不是日志文件');
		}
		$log=file_get_contents($file);
		exit('<pre>'.$log.'</pre>');
	}
	/*文件校验*/
	public function checkfileAction(){
		set_time_limit(0);
		if(request()->isPost()){
			$check_file=file_get_contents(config('app_path').'/install/data/check_file');
			$check_file=unserialize($check_file);
			if(empty($check_file)){
				$this->error('没有获取到校验文件');
			}
			if(!version_compare($check_file['version'],SKYCAIJI_VERSION,'=')){
				
				$this->error('校验文件版本与程序版本不一致');
			}
			
			if(empty($check_file['files'])){
				$this->error('没有文件');
			}
			
			$new_files=array();
			$new_files1=array();
			program_filemd5_list(config('root_path'), $new_files1);
			foreach ($new_files1 as $k=>$v){
				$new_files[md5($v['file'])]=$v;
			}
			unset($new_files1);
			if(empty($new_files)){
				$this->error('没有获取到程序文件');
			}
			
			$error_files=array();
			
			foreach ($check_file['files'] as $old_file){
				$error_file='';
				$filenameMd5=md5($old_file['file']);
				if(isset($new_files[$filenameMd5])){
					if($new_files[$filenameMd5]['file']!=$old_file['file']){
						
						$error_file=$old_file['file'].' 不一致';
					}elseif($new_files[$filenameMd5]['md5']!=$old_file['md5']){
						$error_file=$old_file['file'].' 已修改';
					}
				}else{
					$error_file=$old_file['file'].' 不存在';
				}
				if(!empty($error_file)){
					$error_files[]=$error_file;
				}
			}
			if(empty($error_files)){
				
				$this->success();
			}else{
				$this->error('',null,array('files'=>$error_files));
			}
		}else{
			$GLOBALS['content_header']='校验文件';
			$GLOBALS['breadcrumb']=breadcrumb(array('校验文件'));
			return $this->fetch();
		}
	}
	/*获取索引*/
	public function _get_indexes($tb_indexes){
		$indexes=array();
		if(!empty($tb_indexes)){
			foreach ($tb_indexes as $tb_index){
				$tb_index=array_change_key_case($tb_index,CASE_LOWER);
				
				if(empty($indexes[$tb_index['key_name']]['type'])){
					
					$index_type=strtolower($tb_index['index_type']);
					if(strcasecmp($tb_index['key_name'], 'primary')==0){
						
						$index_type='primary';
					}elseif(empty($tb_index['non_unique'])){
						
						$index_type='unique';
					}elseif($index_type=='fulltext'){
						
						$index_type='fulltext';
					}else{
						
						$index_type='index';
					}
				}
			
				$indexes[$tb_index['key_name']]['type']=$index_type;
				$indexes[$tb_index['key_name']]['field'][]='`'.$tb_index['column_name'].'`'.(empty($tb_index['sub_part'])?'':"({$tb_index['sub_part']})");
			}
		}
		return $indexes;
	}
	/*数据库校验*/
	public function checkdbAction(){
		if(request()->isPost()){
			set_time_limit(0);
			$repair=input('repair/d',0);
			
			$check_db=file_get_contents(config('app_path').'/install/data/check_db');
			if(empty($check_db)){
				$this->error('没有获取到校验文件');
			}
			$check_db=unserialize($check_db);
			if(empty($check_db)){
				$this->error('没有获取到表');
			}
			
			if(!version_compare($check_db['version'],$GLOBALS['config']['version'],'=')){
				
				$this->error('校验文件版本与数据库版本不一致');
			}
			if(empty($check_db['tables'])){
				$this->error('没有表');
			}
			
			$error_fields=array();
			$error_indexes=array();
			$table_primary=array();
			foreach ($check_db['tables'] as $table=>$fields){
				$tb_indexes=$check_db['indexes'][$table];
				$table=config('database.prefix').$table;
				
				
				$null_table=db()->query("show tables like '{$table}';");
				$null_table=empty($null_table)?true:false;

				$cur_fields=array();
				if(!$null_table){
					
					$cur_fields=DbCommon::fieldsInfo($table);
				}
				
				foreach ($fields as $field=>$field_set){
					if(serialize($field_set)!=serialize($cur_fields[$field])){
						
						$error_fields[$table][$field]=$field_set;
					}
					if($field_set['primary']){
						
						$table_primary[$table][$field_set['name']]='`'.$field_set['name'].'`';
					}
				}
				$tb_indexes=$this->_get_indexes($tb_indexes);
				
				
				if(!$null_table){
					
					$cur_indexes=db()->query("SHOW INDEX FROM `{$table}`");
					
					$cur_indexes=$this->_get_indexes($cur_indexes);
					
					
					foreach ($tb_indexes as $index_name=>$tb_index){
						$cur_index=$cur_indexes[$index_name];
						
						if(empty($cur_index)||strcasecmp($tb_index['type'],$cur_index['type'])!=0||strcasecmp(implode(',',$tb_index['field']),implode(',',$cur_index['field']))!=0){
							
							$error_indexes[$table][$index_name]=$tb_index;
						}
					}
				}
				
			}
			if(empty($error_fields)&&empty($error_indexes)){
				
				$this->success();
			}else{
				if(!$repair){
					
					
					foreach ($error_fields as $tb=>$tb_fields){
						foreach ($tb_fields as $k=>$v){
							$v['default']=is_null($v['default'])?NULL:$v['default'];
							$v['primary']=$v['primary']?'是':'否';
							$v['notnull']=$v['notnull']?'是':'否';
							$v['autoinc']=$v['autoinc']?'是':'否';
							$tb_fields[$k]=$v;
						}
						$error_fields[$tb]=$tb_fields;
					}
					foreach ($error_indexes as $tb=>$indexes){
						foreach ($indexes as $k=>$v){
							$index_field=implode(',', $v['field']);
							$index_field=str_replace('`', '', $index_field);
							$error_indexes[$tb][$k]['field']=$index_field;
						}
					}
					
					$this->error('',null,array('fields'=>empty($error_fields)?null:$error_fields,'indexes'=>empty($error_indexes)?null:$error_indexes));
				}else{
					
					try {
						foreach ($error_fields as $tb=>$tb_fields){
							$primarys=$table_primary[$tb];
							
							$hasTable=db()->query("show tables like '{$tb}';");
							
							foreach ($tb_fields as $k=>$v){
								if($v['primary']){
									
									$v['notnull']=1;
								}
								if($v['notnull']){
									
									$v['default']=is_null($v['default'])?'':"DEFAULT '{$v['default']}'";
								}else{
									
									$v['default']=is_null($v['default'])?'DEFAULT NULL':"DEFAULT '{$v['default']}'";
								}
								$v['notnull']=$v['notnull']?'NOT NULL':'null';
								$v['autoinc']=$v['autoinc']?'AUTO_INCREMENT':'';
								
								$tb_fields[$k]=$v;
							}
							
							if(empty($hasTable)){
								
								$createSql="CREATE TABLE `{$tb}` (";
								foreach ($tb_fields as $k=>$v){
									$createSql.="`{$v['name']}` {$v['type']} {$v['notnull']} {$v['default']} {$v['autoinc']},\r\n";
								}
								if(empty($primarys)){
									
									$createSql=rtrim($createSql,',');
								}else{
									
									$createSql.='PRIMARY KEY ('.implode(',', $primarys).')';
								}
								
								$createSql.=' ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
								db()->execute($createSql);
							}else{
								
								$cur_fields=db()->getTableFields($tb);
								foreach ($tb_fields as $k=>$v){
									$alterSql="ALTER TABLE {$tb} ";
									
									if(in_array($v['name'],$cur_fields)){
										
										$alterSql.=' MODIFY ';
									}else{
										
										$alterSql.=' ADD ';
									}
									
									$alterSql.=" `{$v['name']}` {$v['type']} {$v['notnull']} {$v['default']} {$v['autoinc']}";
									if(empty($primarys)&&$v['primary']){
										
										$alterSql.=' PRIMARY KEY';
									}
									
									db()->execute($alterSql);
								}
								
								if(!empty($primarys)){
									
									db()->execute("alter table {$tb} drop primary key,add primary key(".implode(',', $primarys).')');
								}
							}
						}
						
						foreach ($error_indexes as $tb=>$tb_indexes){
							foreach ($tb_indexes as $index_name=>$each_index){
								$each_index['type']=strtolower($each_index['type']);
								$add_sql=" add ";
								$drop_sql="alter table {$tb} drop ";
								switch ($each_index['type']){
									case 'primary':$add_sql.='primary key';$drop_sql.='primary key';break;
									case 'unique':$add_sql.="unique `{$index_name}`";$drop_sql.="index `{$index_name}`";break;
									case 'index':$add_sql.="index `{$index_name}`";$drop_sql.="index `{$index_name}`";break;
									case 'fulltext':$add_sql.="fulltext `{$index_name}`";$drop_sql.="index `{$index_name}`";break;
									default:$add_sql='';$drop_sql='';break;
								}
								if(!empty($add_sql)){
									
									$add_sql.=" (".implode(',',$each_index['field']).")";
								}
								
								
								if($each_index['type']=='primary'){
									
									try {
										if(!empty($drop_sql)&&!empty($add_sql)){
											db()->execute($drop_sql.','.$add_sql);
										}
									}catch (\Exception $ex){
										
									}
								}else{
									
									if(!empty($drop_sql)){
										
										try {
											db()->execute($drop_sql);
										}catch (\Exception $ex){
											
										}
									}
									if(!empty($add_sql)){
										
										$add_sql="alter table {$tb} ".$add_sql;
										try {
											db()->execute($add_sql);
										}catch (\Exception $ex){
											
										}
									}
								}
							}
						}
					}catch (\Exception $ex){
						$this->error($ex->getMessage());
					}
					$this->success('修复完毕,请再次校验！');
				}
			}
		}else{
			$GLOBALS['content_header']='校验数据库';
			$GLOBALS['breadcrumb']=breadcrumb(array('校验数据库'));
			return $this->fetch();
		}
	}
	public function json_treeAction(){
		if(request()->isPost()){
			$url=input('url','','trim');
			$html=input('html','','trim');
			$json='';
			$eCpattern=controller('admin/Cpattern','event');
			if(!empty($url)){
				
				$html=get_html($url);
			}
			if(!empty($html)){
				
				if(preg_match($eCpattern::$jsonpRegExp,$html,$json)){
					
					$json=trim($json['json']).'}';
				}
			}
			
			$this->success('','',array('json'=>$json));
		}else{
			$GLOBALS['content_header']='JSON解析';
			$GLOBALS['breadcrumb']=breadcrumb(array('JSON解析'));
			return $this->fetch();
		}
	}
}