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
class Collected extends \skycaiji\common\model\BaseModel{
	public function __construct($data=[]){
		try {
			parent::__construct($data);
			$this->getPk();
		}catch (\Exception $ex){
			
			$this->create_table();
			parent::__construct($data);
		}
	}
	
	/*采集时获取的数据*/
	public function collGetNumByUrl($urls){
	    $cond=array();
	    if(is_array($urls)){
	        $cond['urlMd5']=array('in',array_map('md5', $urls));
	    }else{
	        $cond['urlMd5']=md5($urls);
	    }
	    if(g_sc_c('caiji','same_url')){
	        
	        $cond=$this->_coll_cond_set_tid($cond);
	    }
	    return $this->where($cond)->count();
	}
	public function collGetNumByTitle($title){
		if(empty($title)){
			return 0;
		}
		$title=md5($title);
		$cond=array('titleMd5'=>$title);
		if(g_sc_c('caiji','same_title')){
		    
		    $cond=$this->_coll_cond_set_tid($cond);
		}
		return $this->where($cond)->count();
	}
	public function collGetNumByContent($content){
	    if(empty($content)){
	        return 0;
	    }
	    $content=md5($content);
	    $cond=array('contentMd5'=>$content);
	    if(g_sc_c('caiji','same_content')){
	        
	        $cond=$this->_coll_cond_set_tid($cond);
	    }
	    return $this->where($cond)->count();
	}
	public function collGetUrlByUrl($urls){
		if(!is_array($urls)){
			$urls=array($urls);
		}
		$urls=array_filter($urls);
		$urls=array_map('md5', $urls);
		
		$cond=array('urlMd5'=>array('in',$urls));
		if(g_sc_c('caiji','same_url')){
		    
		    $cond=$this->_coll_cond_set_tid($cond);
		}
		return $this->field('`id`,`url`')->where($cond)->column('url','id');
	}
	private function _coll_cond_set_tid($cond){
	    $cond=is_array($cond)?$cond:array();
	    $taskId=g_sc('collect_task_id');
	    if(!empty($taskId)){
	        $cond['task_id']=$taskId;
	    }
	    return $cond;
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
  `contentMd5` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ix_urlmd5` (`urlMd5`),
  KEY `ix_taskid` (`task_id`),
  KEY `ix_addtime` (`addtime`),
  KEY `ix_titlemd5` (`titleMd5`),
  KEY `ix_contentmd5` (`contentMd5`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
EOT;
			db()->execute($table);
		}
	}
}

?>