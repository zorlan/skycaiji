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

namespace Admin\Model; class CacheModel extends BaseModel { protected $category=''; public $cache_list=array(); public function __construct($category){ $this->category=strtolower($category); $name=$this->getModelName().'_'.$category; try { parent::__construct($name); }catch (\Exception $ex){ $this->create_table(); parent::__construct($name); } } protected function _initialize() { $this->tableName='cache'.($this->category?'_'.$this->category:''); } public function get($cname,$key=null){ if(!isset($this->cache_list[$cname])){ $cache=$this->where("`cname`='%s'",$cname)->find(); switch($cache['ctype']){ case 1:$cache['data']=intval($cache['data']);break; case 2:$cache['data']=unserialize($cache['data']);break; } $this->cache_list[$cname]=$cache; } return $key?$this->cache_list[$cname][$key]:$this->cache_list[$cname]; } public function set($cname,$value){ $data=array('cname'=>$cname,'ctype'=>0); if(is_array($value)){ $data['ctype']=2; $data['data']=serialize($value); }elseif(is_integer($value)){ $data['ctype']=1; $data['data']=intval($value); }else{ $data['data']=$value; } $data['dateline']=time(); $this->add($data,$options=array(),true); } public function expire($cname,$timeout=72000){ $cache=$this->get($cname); if(empty($cache)||abs(NOW_TIME-$cache['dateline']>$timeout)){ return true; }else{ return false; } } public function create_table(){ $this->_initialize(); if(empty($this->trueTableName)||empty($this->tableName)){ return false; } $tname=$this->trueTableName?$this->trueTableName:(C('DB_PREFIX').$this->tableName); $exists=$this->query("show tables like '{$tname}'"); if(empty($exists)){ $table=<<<EOT
CREATE TABLE `{$tname}` (
  `cname` varchar(32) NOT NULL,
  `ctype` tinyint(3) unsigned NOT NULL,
  `dateline` int(10) unsigned NOT NULL,
  `data` mediumblob NOT NULL,
  PRIMARY KEY (`cname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
		
EOT;
$this->execute($table); } } } ?>