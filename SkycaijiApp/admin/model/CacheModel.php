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
/**
 * 数据缓存模型
 * 不继承model类直接调用db，防止不同表实例化的静态变量混淆
 */
class CacheModel{
	private static $instances;
	public $category='';
	public $table_name='cache';
	protected $cache_list=array();
	public function __construct($category=''){
		$this->category=$category;
		$this->table_name='cache'.($this->category?('_'.$this->category):'');
	}
	/*获取实例化类*/
	public static function getInstance($category=''){
		if(!isset(self::$instances[$category])){
			self::$instances[$category] = new static($category);
		}
		return self::$instances[$category];
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
	public function getCount($cname){
		return $this->db()->where('cname',$cname)->count();
	}
	/**
	 * 获取缓存
	 * @param string $cname 缓存名称
	 * @param string $key 缓存的数据键名
	 * @return mixed
	 */
	public function getCache($cname,$key=null){
		
		$cache=$this->db()->where('cname',$cname)->find();
		switch($cache['ctype']){
			case 1:$cache['data']=intval($cache['data']);break;
			case 2:$cache['data']=unserialize($cache['data']);break;
		}
		return $key?$cache[$key]:$cache;
	}
	/**
	 * 设置缓存
	 * @param string $cname 缓存名称
	 * @param string $value 缓存数据
	 */
	public function setCache($cname,$value){
		$data=array('cname'=>$cname,'ctype'=>0);
		if(is_array($value)){
			$data['ctype']=2;
			$data['data']=serialize($value);
		}elseif(is_integer($value)){
			$data['ctype']=1;
			$data['data']=intval($value);
		}else{
			$data['data']=$value;
		}
		$data['dateline']=time();
		$this->db()->insert($data,true);
	}
	/**
	 * 缓存是否过期
	 * @param string $cname 缓存名称
	 * @param int $timeout 过期时间
	 * @return boolean
	 */
	public function expire($cname,$timeout=72000){
		$cache=$this->getCache($cname);
		if(empty($cache)||abs(NOW_TIME-$cache['dateline']>$timeout)){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * 创建表
	 * @return boolean
	 */
	public function create_table(){
		$tname=config('database.prefix').$this->table_name;
		$exists=db()->query("show tables like '{$tname}'");
		if(empty($exists)){
			
$table=<<<EOT
CREATE TABLE `{$tname}` (
  `cname` varchar(32) NOT NULL,
  `ctype` tinyint(3) unsigned NOT NULL,
  `dateline` int(10) unsigned NOT NULL,
  `data` mediumblob NOT NULL,
  PRIMARY KEY (`cname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
EOT;
			db()->execute($table);
		}
	}
}

?>