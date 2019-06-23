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
/*采集到的数据库*/
class Collected extends BaseModel{
	
	public function __construct($data=[]){
		try {
			parent::__construct($data);
			$this->getPk();
		}catch (\Exception $ex){
			
			$this->create_table();
			parent::__construct($data);
		}
	}
	/**
	 * 获取url的数量
	 * @param array|string $urls 
	 */
	public function getCountByUrl($urls){
		if(is_array($urls)){
			$urls=array_map('md5', $urls);
			return $this->where('urlMd5','in',$urls)->count();
		}else{
			return $this->where('urlMd5',md5($urls))->count();
		}
	}
	/*获取标题的数量*/
	public function getCountByTitle($title){
		if(empty($title)){
			return 0;
		}
		return $this->where('titleMd5',md5($title))->count();
	}
	
	public function getUrlByUrl($urls){
		if(!is_array($urls)){
			$urls=array($urls);
		}
		$urls=array_map('md5', $urls);
		return $this->field('`id`,`url`')->where(array('urlMd5'=>array('in',$urls)))->column('url','id');
	}
	/**
	 * 创建表
	 * @return boolean
	 */
	public function create_table(){
		$tname=$this->get_table_name();
		$exists=db()->query("show tables like '{$tname}'");
		
		if(empty($exists)){
			
$table=<<<EOT
CREATE TABLE `{$tname}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1000) NOT NULL DEFAULT '',
  `urlMd5` varchar(32) NOT NULL DEFAULT '',
  `release` varchar(10) NOT NULL DEFAULT '',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `target` varchar(1000) NOT NULL DEFAULT '',
  `desc` varchar(1000) NOT NULL DEFAULT '',
  `error` varchar(1000) NOT NULL DEFAULT '',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `titleMd5` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ix_urlmd5` (`urlMd5`),
  KEY `ix_taskid` (`task_id`),
  KEY `ix_addtime` (`addtime`),
  KEY `ix_titlemd5` (`titleMd5`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
EOT;
			db()->execute($table);
		}
	}
}

?>