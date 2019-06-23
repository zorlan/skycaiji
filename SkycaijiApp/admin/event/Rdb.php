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

/*发布设置:本地cms*/
namespace skycaiji\admin\event;
use skycaiji\admin\model\DbCommon;
class Rdb extends Release{
	protected $db_conn_list=array();
	/**
	 * 设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
		$db=input('db/a','','trim');
		foreach ($db as $k=>$v){
			if(empty($v)&&'pwd'!=$k){
				
				$this->error(lang('error_null_input',array('str'=>lang('rele_db_'.$k))));
			}
		}
		$config['db']=$db;
		$config['db_table']=input('db_table/a','','trim');
		
		if(is_array($config['db_table'])&&is_array($config['db_table']['field'])){
			foreach($config['db_table']['field'] as $tbName=>$tbFields){
				if(is_array($tbFields)){
					foreach ($tbFields as $tbField=>$fieldVal){
						if(empty($fieldVal)){
							
							unset($config['db_table']['field'][$tbName][$tbField]);
							unset($config['db_table']['custom'][$tbName][$tbField]);
							continue;
						}
					}
				}
			}
		}
		return $config;
	}
	/*导出数据*/
	public function export($collFieldsList,$options=null){
		
		$db_config=$this->get_db_config($this->config['db']);
		$db_key=md5(serialize($db_config));
		if(empty($this->db_conn_list[$db_key])){
			
			$mdb=new DbCommon($db_config);
			$mdb=$mdb->db();
			$this->db_conn_list[$db_key]=$mdb;
		}else{
			$mdb=$this->db_conn_list[$db_key];
		}
		
		$addedNum=0;
		
		$dbCharset=strtolower($db_config['db_charset']);
		if(empty($dbCharset)||$dbCharset=='utf-8'||$dbCharset=='utf8'){
			
			$dbCharset=null;
		}
		
		
		foreach ($collFieldsList as $collFieldsKey=>$collFields){
			
			$mdb->startTrans();

			$contTitle=$collFields['title'];
			$contUrl=$collFields['url'];
			$collFields=$collFields['fields'];
			$tableFields=array();
			foreach ($this->config['db_table']['field'] as $tbName=>$tbFields){
				foreach ($tbFields as $tbField=>$fieldVal){
					if(empty($fieldVal)){
						
						unset($tbFields[$tbField]);
						continue;
					}
					if(strcasecmp('custom:',$fieldVal)==0){
						
						$fieldVal=$this->config['db_table']['custom'][$tbName][$tbField];
					}elseif(preg_match('/^field\:(.+)$/ui',$fieldVal,$collField)){
						
						$fieldVal=$this->get_field_val($collFields[$collField[1]]);
						$fieldVal=is_null($fieldVal)?'':$fieldVal;
					}

					if(!empty($dbCharset)){
						
						$fieldVal=$this->utf8_to_charset($dbCharset, $fieldVal);
					}
					
					$tbFields[$tbField]=$fieldVal;
				}
				$tableFields[$tbName]=$tbFields;
			}
			if(!empty($tableFields)){
				if('oracle'==$db_config['db_type']){
					
					$pdoOracle=new \PDO($db_config['db_dsn'], $db_config['db_user'], $db_config['db_pwd'],array());
					$pdoOracle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				}
				
				$errorMsg=false;
				
				$autoidList=array();
				foreach ($tableFields as $table=>$fields){
					$table=strtolower($table);
					foreach ($fields as $k=>$v){
						
						$fields[$k]=preg_replace_callback('/auto_id\@([^\s\#]+)[\#]{0,1}/i',function($autoidTbName)use($autoidList){
							$autoidTbName=trim($autoidTbName[1]);
							$autoidTbName=strtolower($autoidTbName);
							return $autoidList[$autoidTbName];
						},$v);
					}
					try {
						if('oracle'==$db_config['db_type']){
							
							$insertSql='insert into '.$table.' ';
							$insertKeys=array();
							$insertVals=array();
							$sequenceName='';
							foreach ($fields as $k=>$v){
								if(preg_match('/^sequence\@([^\s]+)$/i', $v,$m_sequence)){
									
									$sequenceName=$m_sequence[1];
									continue;
								}
								$insertKeys[]=$k;
								$insertVals[]="'".str_replace("'", "''", $v)."'";
							}
							$insertSql.='('.implode(',', $insertKeys).') values ('.implode(',', $insertVals).')';

							if($pdoOracle->exec($insertSql)){
								
								if(!empty($sequenceName)){
									
									$autoId=$pdoOracle->query("select {$sequenceName}.CURRVAL as id FROM DUAL");
									if($autoId){
										$autoId=$autoId->fetch();
										$autoidList[$table]=$autoId[0];
									}
								}
								if(empty($autoidList[$table])){
									
									$autoidList[$table]=1;
								}
							}else{
								$autoidList[$table]=0;
							}
						}else{
							
							$autoidList[$table]=$mdb->table($table)->insert($fields,false,true);
						}
					}catch (\Exception $ex){
						$errorMsg=$ex->getMessage();
						$this->echo_msg($errorMsg);
						$errorMsg=!empty($errorMsg)?$errorMsg:($table.'表入库失败');
						break;
					}
					if($autoidList[$table]<=0){
						
						break;
					}
				}
				$returnData=array('id'=>0);
				if(!empty($errorMsg)){
					
					$mdb->rollback();
					$returnData['error']=$errorMsg;
				}else{
					
					$mdb->commit();
					reset($autoidList);
					list($firstTable,$firstId) = each($autoidList);
					$firstId=intval($firstId);
					if($firstId>0){
						$addedNum++;
						$returnData['id']=$firstId;
						$returnData['target']="{$db_config['db_type']}:{$db_config['db_name']}@table:{$firstTable}@id:{$firstId}";
					}else{
						$returnData['error']='数据插入失败';
					}
				}
				$this->record_collected($contUrl,$returnData,$this->release,$contTitle);
			}
			
			unset($collFieldsList[$collFieldsKey]['fields']);
		}
		
		return $addedNum;
	}
	
	/*将发布配置中的数据库参数转换成thinkphp数据库参数*/
    public function get_db_config($config_db){
    	$db_config=array(
    		'db_type'  => strtolower($config_db['type']),
    		'db_user'  => $config_db['user'],
    		'db_pwd'   => $config_db['pwd'],
    		'db_host'  => $config_db['host'],
    		'db_port'  => $config_db['port'],
    		'db_charset'  => $config_db['charset'],
    		'db_name'  => $config_db['name'],
    		
    	);
    	
    	if(strcasecmp($db_config['db_charset'], 'utf-8')===0){
    		$db_config['db_charset']='utf8';
    	}
    	
    	if('mysqli'==$db_config['db_type']){
    		
    		$db_config['db_type']='mysql';
    	}elseif('oracle'==$db_config['db_type']){
    		
    		$db_config['db_dsn']="oci:host={$db_config['db_host']};dbname={$db_config['db_name']};charset={$db_config['db_charset']}";
    	}elseif('sqlsrv'==$db_config['db_type']){
    		
    		$db_config['db_dsn']='sqlsrv:Database='.$db_config['db_name'].';Server='.$db_config['db_host'];
    	}
    	return $db_config;
    }
}
?>