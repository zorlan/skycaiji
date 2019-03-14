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

namespace skycaiji\admin\model;
use think\Db;
/*动态操作数据库*/


class DbCommon{
	public $config=null;
    public function __construct($config=array()) {
    	
    	$this->config=array(
    		'type'=>strtolower($config['db_type']),
    		'database'=>$config['db_name'],
    		'username'=>$config['db_user'],
    		'password'=>$config['db_pwd'],
    		'hostname'=>$config['db_host'],
    		'hostport'=>$config['db_port'],
    		'charset'=>$config['db_charset'],
    		'prefix'=>$config['db_prefix'],
    		'dsn'=>$config['db_dsn'],
    		'resultset_type'=>'array', 
    		'break_reconnect'=>true, 
    		'params'=>array(),
    	);
    	if(!empty($GLOBALS['config']['site']['dblong'])){
    		
    		$this->config['params'][\PDO::ATTR_PERSISTENT]=true;
    	}
    	
    	if(isset($config['fields_strict'])){
    		
    		$this->config['fields_strict']=$config['fields_strict'];
    	}
    	
    	if($this->config['type']=='mysqli'){
    		
    		$this->config['type']='mysql';
    	}elseif($this->config['type']=='sqlserver'){
    		
    		$this->config['type']='sqlsrv';
    	}
    }
    /**
     * 获取数据库连接
     * @param string $table
     * @param string $force
     * @param string $compatible 兼容的数据库模型
     * @return \think\db\Query|NULL
     */
    public function db($table='',$force=false,$compatible=false){
    	if(!empty($this->config)){
    		$config=$this->config;
    		if($config['type']=='oracle'){
    			
    			$config['type']='\think\oracle\Connection';
    		}
    		if($compatible){
    			
				
    			
    			$db=Db::connect($config, $force);
    		}else{
    			$db=Db::connect($config, $force);
    		}
    		if($table){
    			$db=$db->name($table);
    		}
    		return $db;
    	}else{
    		return null;
    	}
    }
    /**
     * 获取数据库中的所有表
     */
    public function getTables(){
    	$list=array();
    	if('oracle'==$this->config['type']){
    		$tables=$this->db()->query("select table_name from user_tables");
    		if(!empty($tables)){
    			foreach ($tables as $table){
    				$list[$table['TABLE_NAME']]=$table['TABLE_NAME'];
    			}
    		}
    	}else{
    		$tables=$this->db()->getTables($this->config['database']);
    		if(!empty($tables)){
    			foreach ($tables as $table){
    				$list[$table]=$table;
    			}
    		}
    	}
	    return $list;
    }
    /*获取表的所有字段信息*/
    public function getFields($tableName=''){
    	$tb_fields=array();
    	if(!empty($tableName)){
    		if('sqlsrv'==$this->config['type']){
    			
    			list($tableName) = explode(' ', $tableName);
    			$tableNames      = explode('.', $tableName);
    			$tableName       = isset($tableNames[1]) ? $tableNames[1] : $tableNames[0];
    			
    			$sql = "SELECT column_name, data_type, character_maximum_length, column_default, is_nullable
	    			FROM    information_schema.tables AS t
	    			JOIN    information_schema.columns AS c
	    			ON  t.table_catalog = c.table_catalog
	    			AND t.table_schema  = c.table_schema
	    			AND t.table_name    = c.table_name
	    			WHERE   t.table_name = '$tableName'";
    			
    			$result = $this->db()->query($sql);
    			if ($result) {
    				foreach ($result as $key => $val) {
    					$val = array_change_key_case($val,CASE_LOWER);
    					$tb_fields[$val['column_name']] = [
	    					'name'    => $val['column_name'],
	    					'type'    => $val['data_type'].($val['character_maximum_length']?('('.$val['character_maximum_length'].')'):''),
	    					'notnull' => ('yes' == strtolower($val['is_nullable']))?false:true, 
	    					'default' => $val['column_default'],
	    					'primary' => false,
	    					'autoinc' => false,
    					];
    				}
    			}
    			
    			$sql = "SELECT column_name FROM information_schema.key_column_usage WHERE table_name='{$tableName}'";
    			$result = $this->db()->query($sql);
    			if ($result) {
    				foreach ($result as $key => $val) {
    					$tb_fields[$val['column_name']]['primary'] = true;
    				}
    			}
				
    			$sql = "SELECT column_name FROM information_schema.columns WHERE TABLE_NAME='{$tableName}' AND COLUMNPROPERTY(OBJECT_ID('{$tableName}'),COLUMN_NAME,'IsIdentity')=1";
    			$result = $this->db()->query($sql);
    			if ($result) {
    				foreach ($result as $key => $val) {
    					$tb_fields[$val['column_name']]['autoinc'] = true;
    				}
    			}
    		}else{
    			$fields=$this->db()->getFields($tableName);
    			if(!empty($fields)){
    				foreach ($fields as $k=>$v){
    					$tb_fields[$k]=array_change_key_case($v,CASE_LOWER);
    				}
    			}
    		}
    	}
    	return $tb_fields;
    }
    /*获取表的字段信息*/
    public static function fieldsInfo($tableName){
    	
    	if(empty($tableName)){
    		return array();
    	}
    	$tableName = '`'.$tableName.'`';
    	$tbFields=db()->query('SHOW COLUMNS FROM '.$tableName);
    	$fields=array();
    	if(!empty($tbFields)){
    		foreach ($tbFields as $tbField){
    			$tbField=array_change_key_case($tbField,CASE_LOWER);
    			$fields[$tbField['field']]=array(
    				'name'    => $tbField['field'],
    				'type'    => $tbField['type'],
    				'notnull' => (strtolower($tbField['null']) == 'yes')?false:true, 
    				'default' => $tbField['default'],
    				'primary' => (strtolower($tbField['key']) == 'pri'),
    				'autoinc' => (strtolower($tbField['extra']) == 'auto_increment'),
    			);
    		}
    	}
    	return $fields;
    }
}

?>