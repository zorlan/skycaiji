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

namespace skycaiji\common\model;
use think\db\Query;

/**
 * 数据集表模型
 * 不继承model类直接调用db，防止不同表实例化的静态变量混淆
 */
class DatasetTable{
	private static $instances;
	public $dataset_id='';
	public $table_name='dataset_t';
	public function __construct($dataset_id=''){
	    $this->dataset_id=$dataset_id;
	    $this->table_name='dataset_t'.($this->dataset_id?('_'.$this->dataset_id):'');
	}
	/*获取实例化类*/
	public static function getInstance($dataset_id=''){
	    if(!isset(self::$instances[$dataset_id])){
	        self::$instances[$dataset_id] = new static($dataset_id);
		}
		return self::$instances[$dataset_id];
	}
	/**
	 * 获取数据库连接
	 * @return \think\db\Query
	 */
	public function db(){
		try {
			$db=db($this->table_name);
			$db->getPk();
		}catch (\Exception $ex){
			$this->create_table();
			$db=db($this->table_name);
		}
		return $db;
	}
	
	public function convertDate($date){
	    $date=strtotime($date);
	    $date=$date>0?date('Y-m-d H:i:s',$date):'';
	    return $date;
	}
	
	public function dbColumns(){
	    $dbColumns=$this->db()->query('SHOW COLUMNS FROM '.$this->fullTableName());
	    $columns=array();
	    if(!empty($dbColumns)){
	        foreach ($dbColumns as $dbColumn){
	            $dbColumn=\util\Funcs::array_keys_to_lower($dbColumn);
	            $columns[strtolower($dbColumn['field'])]=$dbColumn;
	        }
	    }
	    return $columns;
	}
	
	public function alertTableFields($fields,$dsData){
	    $oldFields=array();
	    if(!empty($dsData)){
	        
	        if(is_array($dsData['config'])&&$dsData['config']){
	            $oldFields=$dsData['config']['fields'];
	            init_array($oldFields);
	        }
	    }
	    foreach ($fields as $field){
	        $oldField=null;
	        if($field['name_original']){
	            
	            $oldField=$oldFields[\skycaiji\common\model\Dataset::field_db_name($field['name_original'])];
	        }
	        $this->alertTableField($field,$oldField);
	    }
	    $dbColumns=$this->dbColumns();
	    $hasId=false;
	    if($dbColumns){
	        foreach ($dbColumns as $dbKey=>$dbColumn){
	            if($dbKey=='id'){
	                
	                $hasId=true;
	                continue;
	            }
	            if(empty($fields[$dbKey])){
	                
	                $sql='alter table '.$this->fullTableName().' drop column `'.$dbKey.'`';
	                $this->db()->execute($sql);
	            }
	        }
	    }
	    if(!$hasId){
	        
	        $this->db()->execute('alter table '.$this->fullTableName().' add column id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
	    }
	}
	
	
	public function alertTableField($newField,$oldField){
	    if(empty($oldField)){
	        $oldField=$newField;
	    }
	    $upType=false;
	    if(($newField['type']!=$oldField['type'])||($newField['type']=='varchar'&&$newField['len']!=$oldField['len'])){
	        
	        $upType=true;
	        $typeSql=$this->getTypeSql($newField['type'],$newField['len']);
	    }else{
	        $typeSql=$this->getTypeSql($oldField['type'],$oldField['len']);
	    }
	    
	    $dbFname=\skycaiji\common\model\Dataset::field_db_name($oldField['name']);
	    
	    $hasField=false;
	    $dbColumns=$this->dbColumns();
	    if($dbColumns&&!empty($dbColumns[$dbFname])){
	        $hasField=true;
	    }
	    
	    $commentSql=" COMMENT '".addslashes($newField['name'])."'";
	    
	    if($hasField){
	        
	        try{
	            if($newField['name']!=$oldField['name']){
	                
	                $sql='alter table '.$this->fullTableName().' change `'.$dbFname.'` `'.\skycaiji\common\model\Dataset::field_db_name($newField['name']).'` '.$typeSql.$commentSql;
	                $this->db()->execute($sql);
	            }elseif($upType){
	                
	                $sql='alter table '.$this->fullTableName().' modify column `'.$dbFname.'` '.$typeSql.$commentSql;
	                $this->db()->execute($sql);
	            }
	        }catch(\Exception $ex){
	            $msg=$ex->getMessage();
	            if(stripos($msg,'Specified key was too long')!==false){
	                $msg.=sprintf('，请先删除<b>%s</b>字段的全部索引',$oldField['name']);
	            }
	            throw new \Exception($msg);
	        }
	    }else{
	        
	        $sql='alter table '.$this->fullTableName().' add column `'.\skycaiji\common\model\Dataset::field_db_name($newField['name']).'` '.$typeSql.$commentSql;
	        $this->db()->execute($sql);
	    }
	}
	
	public function getTypeSql($type,$len=null){
	    $sql='';
	    switch ($type){
	        case 'bigint':$sql=" bigint(20) DEFAULT '0'";break;
	        case 'double':$sql=" double";break;
	        case 'varchar':$len=intval($len);$sql=" varchar(".$len.") DEFAULT ''";break;
	        case 'mediumtext':$sql=" mediumtext";break;
	        case 'datetime':$sql=" datetime";break;
	    }
	    return $sql;
	}
	public function fullTableName(){
	    return config('database.prefix').$this->table_name;
	}
	
	public function convertErrorColumn($msg,$fields){
	    if($msg){
	        $msg=preg_replace_callback('/\b(key|column)(\s*[\'\"])([^\'\"]+)/i',function($match)use($fields){
	            if($fields[$match[3]]){
	                $match[3]=$fields[$match[3]]['name'];
	            }
	            return $match[1].$match[2].$match[3];
	        },$msg);
	    }
	    return $msg;
	}
	/**
	 * 创建表
	 * @return boolean
	 */
	public function create_table(){
	    $tname=$this->fullTableName();
		$exists=db()->query("show tables like '{$tname}'");
		if(empty($exists)){
			
			$dataset=model('Dataset')->getById($this->dataset_id);
			if(empty($dataset)){
			    throw new \Exception('无效的数据集');
			}
			$fields=model('Dataset')->filter_fields($dataset['config']['fields']);
			$table="CREATE TABLE `{$tname}`( `id` int(11) NOT NULL AUTO_INCREMENT,";
			foreach ($fields as $dbName=>$field){
			    $typeSql=$this->getTypeSql($field['type'],$field['len']);
			    if($typeSql){
			        
			        $table.='`'.$dbName.'`'.$typeSql." COMMENT '".addslashes($field['name'])."'";
			    }
			    $table.=',';
			}
			$table.='PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
			db()->execute($table);
		}
	}
	
	public function drop_table(){
	    $tname=$this->fullTableName();
	    db()->execute("drop table `{$tname}`");
	}
}

?>