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

namespace Admin\Model; use Think\Model; class CollectedModel extends BaseModel{ public function __construct(){ $this->tableName='collected'; try { parent::__construct(); }catch (\Exception $ex){ $this->create_table(); parent::__construct(); } } public function getCountByUrl($urls){ if(is_array($urls)){ $urls=array_map('md5', $urls); return $this->where(array('urlMd5'=>array('in',$urls)))->count(); }else{ return $this->where(array('urlMd5'=>md5($urls)))->count(); } } public function create_table(){ $tname=$this->trueTableName?$this->trueTableName:(C('DB_PREFIX').$this->tableName); $table=<<<EOT
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
M()->execute($table); } } ?>