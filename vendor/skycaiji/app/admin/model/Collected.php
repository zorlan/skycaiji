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
    public function collected_info_tname(){
        return config('database.prefix').'collected_info';
    }
    public function getInfoDatas($collectedList){
        init_array($collectedList);
        $ids=array();
        foreach ($collectedList as $k=>$v){
            $ids[$v['id']]=$v['id'];
            if($v&&!is_array($v)){
                $v=$v->toArray();
                $collectedList[$k]=$v;
            }
        }
        if($ids){
            $infoList=db()->table($this->collected_info_tname())->where('id','in',$ids)->column('*','id');
            foreach ($collectedList as $k=>$v){
                $info=$infoList[$v['id']];
                if(is_array($info)&&$info){
                    $v=array_merge($v,$info);
                    $v['target']=$this->convertTarget($v['release'],$v['target']);
                    $collectedList[$k]=$v;
                }
            }
        }
        return $collectedList;
    }
    
    public function convertTarget($release,$target){
        if($target&&$release=='dataset'){
            
            if(preg_match('/\@(\d+)\:(\d+)/',$target,$mid)){
                $target=sprintf('<a href="%s" target="_blank">%s</a>',url('dataset/db?ds_id='.$mid[1].'&id='.$mid[2]),$target);
            }
        }
        return $target;
    }
    public function addInfo($infoData){
        init_array($infoData);
        if($infoData){
            db()->table($this->collected_info_tname())->insert($infoData);
        }
    }
    
    public function deleteByCond($cond){
        init_array($cond);
        if($cond){
            $idSql=$this->db()->fetchSql(true)->field('id')->where($cond)->select();
            db()->execute('delete from '.$this->collected_info_tname().' where id in ('.$idSql.')');
            $this->where($cond)->delete();
        }
    }
	/*采集时获取的数据*/
	public function collGetNumByUrl($urls){
	    $cond=array();
	    if(is_array($urls)){
	        $cond['urlMd5']=array('in',array_map('md5', $urls));
	    }else{
	        
	        $url=preg_replace_callback('/^\w+\:\/\//', function($match){
	            $match=strtolower($match[0]);
	            if($match=='http://'){
	                $match='https://';
	            }elseif($match=='https://'){
	                $match='http://';
	            }
	            return $match;
	        }, $urls);
	        $cond['urlMd5']=array(array('eq',md5($urls)),array('eq',md5($url)),'or');
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
	    init_array($urls);
		$urls=array_filter($urls);
		$dbUrls=array();
		if($urls){
		    $urls1=array();
		    foreach ($urls as $k=>$v){
		        $urls1[md5($v)]=$v;
		        unset($urls[$k]);
		    }
		    $urls=$urls1;
		    unset($urls1);
		    $cond=array('urlMd5'=>array('in',array_keys($urls)));
		    if(g_sc_c('caiji','same_url')){
		        
		        $cond=$this->_coll_cond_set_tid($cond);
		    }
		    $dbUrls=$this->field('`id`,`urlMd5`')->where($cond)->column('urlMd5','id');
		    if(!empty($dbUrls)){
		        foreach ($dbUrls as $k=>$v){
		            $v=$urls[$v];
		            if($v){
		                $dbUrls[$k]=$v;
		            }else{
		                unset($dbUrls[$k]);
		            }
		        }
		    }
		}
		return $dbUrls;
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
  `urlMd5` varchar(32) NOT NULL DEFAULT '',
  `release` varchar(10) NOT NULL DEFAULT '',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `titleMd5` varchar(32) NOT NULL DEFAULT '',
  `contentMd5` varchar(32) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_urlmd5` (`urlMd5`),
  KEY `ix_taskid` (`task_id`),
  KEY `ix_addtime` (`addtime`),
  KEY `ix_titlemd5` (`titleMd5`),
  KEY `ix_contentmd5` (`contentMd5`),
  KEY `ix_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOT;
			db()->execute($table);
		}
		
		$tname=$this->collected_info_tname();
		$exists=db()->query("show tables like '{$tname}'");
		if(empty($exists)){
		    
$table=<<<EOT
CREATE TABLE `{$tname}` (
  `id` int(11) NOT NULL DEFAULT '0',
  `url` text,
  `target` text,
  `desc` text,
  `error` text,
  KEY `ix_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOT;
			db()->execute($table);
		}
	}
}

?>