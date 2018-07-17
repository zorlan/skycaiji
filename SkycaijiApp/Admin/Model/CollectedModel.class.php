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

namespace Admin\Model; class CollectedModel extends BaseModel{ public function __construct(){ $this->tableName='collected'; try { parent::__construct(); }catch (\Exception $ex){ $this->create_table(); parent::__construct(); } } public function getCountByUrl($urls){ if(is_array($urls)){ $urls=array_map('md5', $urls); return $this->where(array('urlMd5'=>array('in',$urls)))->count(); }else{ return $this->where(array('urlMd5'=>md5($urls)))->count(); } } public function getCountByTitle($title){ if(empty($title)){ return 0; } return $this->where(array('titleMd5'=>md5($title)))->count(); } public function getUrlByUrl($urls){ if(!is_array($urls)){ $urls=array($urls); } $urls=array_map('md5', $urls); return $this->field('`id`,`url`')->where(array('urlMd5'=>array('in',$urls)))->select(array('index'=>'id,url')); } public function create_table(){ if(empty($this->trueTableName)||empty($this->tableName)){ return false; } $tname=$this->trueTableName?$this->trueTableName:(C('DB_PREFIX').$this->tableName); $exists=$this->query("show tables like '{$tname}'"); if(empty($exists)){ $table=<<<EOT
CREATE TABLE `{$tname}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1000) NOT NULL DEFAULT '',
  `urlMd5` varchar(32) NOT NULL DEFAULT '',
  `target` varchar(1000) NOT NULL DEFAULT '',
  `task_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_urlmd5` (`urlMd5`),
  KEY `ix_taskid` (`task_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
EOT;
$this->execute($table); } } } ?>