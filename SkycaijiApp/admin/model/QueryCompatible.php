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

namespace skycaiji\admin\model;
use think\db\Query;
use think\db\Connection;
/**
 * 兼容tp5和tp3.2的数据库操作模型
 */
class QueryCompatible extends Query{
	/*重写父类的private属性*/
	protected static $event = [];
	protected static $readMaster = [];
	protected function checkMultiField($field, $logic){
		return isset($this->options['multi'][$logic][$field]) && count($this->options['multi'][$logic][$field]) > 1;
	}
	/*---------------------------*/
	public function __construct(Connection $connection = null, $model = null){
		parent::__construct($connection,$model);
	}	
	public function where($field, $op = null, $condition = null){
		$newSql=$this->_tp3_parse_sql(func_get_args());
		if(empty($newSql)){
			return parent::where($field,$op,$condition);
		}else{
			return parent::where($newSql);
		}
	}
	public function query($sql, $bind = [], $master = false, $class = false){
		$newSql=$this->_tp3_parse_sql(func_get_args());
		if(empty($newSql)){
			return parent::query($sql,$bind,$master,$class);
		}else{
			return parent::query($newSql);
		}
	}
	public function execute($sql, $bind = []){
		$newSql=$this->_tp3_parse_sql(func_get_args());
		if(empty($newSql)){
			return parent::execute($sql,$bind);
		}else{
			return parent::execute($newSql);
		}
	}
	/**
	 * tp3的方法必须在这里定义，防止与tp5存在的方法冲突
	 * @see \think\db\Query::__call()
	 */
	public function __call($method, $args){
		switch ($method){
			case 'getField':return $this->_tp3_getField($args);break;
			case 'add':return $this->_tp3_add($args);break;
			case 'addAll':return $this->_tp3_addAll($args);break;
			case 'save':return $this->_tp3_save($args);break;
		}
	}
	/*getField($field,$sepa=null)*/
	private function _tp3_getField($args){
		$field=$args[0];
		$sepa=isset($args[1])?$args[1]:null;
		if(strpos($field,',')!==false){
			
			if(is_numeric($sepa)&&$sepa>0){
				return $this->limit($sepa)->column($field);
			}elseif($sepa===false){
				$field=explode(',', $field);
				return $this->value($field[0]);
			}else{
				return $this->column($field);
			}
		}else{
			
			if($sepa===true){
				return $this->column($field);
			}elseif(is_numeric($sepa)){
				$sepa=intval($sepa);
				if($sepa>0){
					return $this->limit($sepa)->column($field);
				}else{
					return $this->column($field);
				}
			}else{
				return $this->value($field);
			}
		}
	}
	/*add($data='',$options=array(),$replace=false)*/
	private function _tp3_add($args){
		$data=isset($args[0])?$args[0]:'';
		$options=isset($args[1])?$args[1]:array();
		$replace=isset($args[2])?$args[2]:false;
		if(empty($data)){
			$data=array();
		}
		if(empty($options)){
			return $this->insert($data,$replace,true);
		}else{
			$this->_tp5_exception('add', 'insert');
		}
	}
	/*addAll($dataList,$options=array(),$replace=false)*/
	private function _tp3_addAll($args){
		$dataList=$args[0];
		$options=isset($args[1])?$args[1]:array();
		$replace=isset($args[2])?$args[2]:false;
		if(empty($options)){
			$this->insertAll($dataList,$replace);
			return $this->getLastInsID();
		}else{
			$this->_tp5_exception('addAll', 'insertAll');
		}
	}
	/*save($data='',$options=array())*/
	private function _tp3_save($args){
		$data=isset($args[0])?$args[0]:'';
		$options=isset($args[1])?$args[1]:array();
		if(empty($options)){
       		$options = $this->options;
       		if(empty($options['where'])){
       			
				$this->_tp5_exception('save', 'update');
       		}
			return $this->update($data);
		}else{
			$this->_tp5_exception('save', 'update');
		}
	}
	/*tp3解析sql语句*/
	private function _tp3_parse_sql($args=array()){
		$sql=$args[0];
		$newSql=null;
		if(is_array($args)&&count($args)>1&&is_string($sql)&&strpos($sql,'%')!==false){
			array_shift($args);

			foreach ($args as $k=>$v){
				if(!is_scalar($v)){
					
					$args=null;
					break;
				}else{
					$args[$k]=addslashes($v);
				}
			}
			if(!empty($args)){
				
				$newSql=vsprintf($sql,$args);
				if($newSql==$sql){
					
					$newSql=null;
				}
			}
		}
		return $newSql;
	}
	private function _tp5_exception($oldMethod,$newMethod){
		throw new \Exception("tp5数据库操作已弃用{$oldMethod}方法，请使用{$newMethod}方法");
		exit();
	}
}

?>