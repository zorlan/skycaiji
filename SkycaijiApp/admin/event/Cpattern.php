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

namespace skycaiji\admin\event;
use skycaiji\admin\model\CacheModel;
class Cpattern extends CpatternBase{
	/*采集,return false表示终止采集*/
	public function collect($num=10){
		if(!defined('IS_COLLECTING')){
			define('IS_COLLECTING', 1);
		}
		@session_start();
		\think\Session::pause();
	
		if(!$this->show_opened_tools){
			$opened_tools=array();
			if($this->config['page_render']){
				$opened_tools[]='页面渲染';
			}
			if($GLOBALS['config']['caiji']['download_img']){
				$opened_tools[]='图片本地化';
			}
			if($GLOBALS['config']['proxy']['open']){
				$opened_tools[]='代理';
			}
			if(!empty($opened_tools)){
				$this->echo_msg('开启功能：'.implode('、', $opened_tools),'black');
			}
			if($num>0){
				$this->echo_msg('预计采集'.$num.'条数据','black');
			}
				
			$this->show_opened_tools=true;
		}
	
		$this->collect_num=$num;
		$this->collected_field_list=array();
		
		$source_is_url=intval($this->config['source_is_url']);
		if(!isset($this->original_source_urls)){
			
			$this->original_source_urls=array();
			foreach ( $this->config ['source_url'] as $k => $v ) {
				if(empty($v)){
					continue;
				}
				$return_s_urls = $this->convert_source_url ( $v );
				if (is_array ( $return_s_urls )) {
					foreach ($return_s_urls as $r_s_u){
						$this->original_source_urls[md5($r_s_u)]=$r_s_u;
					}
				} else {
					$this->original_source_urls[md5($return_s_urls)]=$return_s_urls;
				}
			}
		}
		if(empty($this->original_source_urls)){
			$this->echo_msg('没有起始页网址！');
			return 'completed';
		}
	
		if($source_is_url){
			
			if(isset($this->used_source_urls['_source_is_url_'])){
				$this->echo_msg('所有起始页采集完毕！','green');
				return 'completed';
			}
		}else{
			if(count($this->original_source_urls)<=count($this->used_source_urls)){
				$this->echo_msg('所有起始页采集完毕！','green');
				return 'completed';
			}
		}
	
		$source_interval=$GLOBALS['config']['caiji']['interval']*60;
		$time_interval_list=array();
	
		$source_urls=array();
		$mcacheSource=CacheModel::getInstance('source_url');
		if($source_is_url){
			
			$source_urls=$this->original_source_urls;
		}else{
			$cacheSources=$mcacheSource->db()->where(array('cname'=>array('in',array_keys($this->original_source_urls))))->column('dateline','cname');
			if(!empty($cacheSources)){
				$count_db_used=0;
				$sortSources=array('undb'=>array(),'db'=>array());
				
				foreach ($this->original_source_urls as $sKey=>$sVal){
					if(!isset($cacheSources[$sKey])){
						
						$sortSources['undb'][$sKey]=$sVal;
					}else{
						
						$time_interval=abs(NOW_TIME-$cacheSources[$sKey]);
						if($time_interval<$source_interval){
							
							$this->used_source_urls[$sVal]=1;
							$count_db_used++;
							$time_interval_list[]=$time_interval;
						}else{
							$sortSources['db'][$sKey]=$sVal;
						}
					}
				}
				if($count_db_used>0){
					$this->echo_msg($count_db_used.'条已采集起始网址被过滤，下次采集需等待'.($source_interval-max($time_interval_list)).'秒，<a href="'
							.url('Admin/Setting/caiji').'" target="_blank">设置间隔</a>','black');
					if(count($this->original_source_urls)<=count($this->used_source_urls)){
						$this->echo_msg('所有起始页采集完毕！','green');
						return 'completed';
					}
				}
				$source_urls=array_merge($sortSources['undb'],$sortSources['db']);
				unset($sortSources);
				unset($cacheSources);
			}else{
				$source_urls=$this->original_source_urls;
			}
		}
		$mcollected=model('Collected');
		
		if($source_is_url){
			
			$this->cont_urls_list['_source_is_url_']=array_values($source_urls);
			$source_urls=array('_source_is_url_'=>'_source_is_url_');
		}
	
	
		foreach ($source_urls as $key_source_url=>$source_url){
			$this->cur_source_url=$source_url;
			if(array_key_exists($source_url,$this->used_source_urls)){
				
				continue;
			}
			if($source_is_url){
				$this->echo_msg("起始页已转换为内容页网址",'black');
			}else{
				$this->echo_msg("采集起始页：<a href='{$source_url}' target='_blank'>{$source_url}</a>",'green');
			}
			if($source_is_url){
				
				$this->_collect_fields();
			}else{
				
				if(!empty($this->config['level_urls'])){
					
					
					$this->echo_msg('开始分析多级网址','black');
					$return_msg=$this->_collect_level($source_url,1);
					if($return_msg=='completed'){
						return $return_msg;
					}
				}else{
					
					$cont_urls=$this->getContUrls($source_url);
					$this->cont_urls_list[$source_url]=$this->_collect_unused_cont_urls($cont_urls);
					$this->_collect_fields();
				}
			}
				
			if($this->collect_num>0&&count($this->collected_field_list)>=$this->collect_num){
				break;
			}
		}
		
		
		return $this->collected_field_list;
	}
	/**
	 * 优化设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
		$config['url_complete']=intval($config['url_complete']);
	
		$config['url_reverse']=intval($config['url_reverse']);
		$config['page_render']=intval($config['page_render']);
		$config['url_repeat']=intval($config['url_repeat']);
		
		if(!empty($config['request_headers'])){
			if(!is_array($config['request_headers']['custom_names'])){
				$config['request_headers']['custom_names']=array();
			}
			if(!is_array($config['request_headers']['custom_vals'])){
				$config['request_headers']['custom_vals']=array();
			}
				
			foreach ($config['request_headers']['custom_names'] as $k=>$v){
				if(empty($v)){
					
					unset($config['request_headers']['custom_names'][$k]);
					unset($config['request_headers']['custom_vals'][$k]);
				}
			}
			$config['request_headers']['custom_names']=array_values($config['request_headers']['custom_names']);
			$config['request_headers']['custom_vals']=array_values($config['request_headers']['custom_vals']);
		}
	
		
		foreach ($config['source_url'] as $k=>$v){
			if(preg_match('/[\r\n]/', $v)){
				
				if(preg_match_all('/^\w+\:\/\/[^\r\n]+/im',$v,$v_urls)){
					
					$v_urls=array_unique($v_urls[0]);
					$v_urls=array_values($v_urls);
					$config['source_url'][$k]=implode("\r\n", $v_urls);
				}else{
					unset($config['source_url'][$k]);
				}
			}else{
				
				if(!preg_match('/^\w+\:\/\/.+/i', $v)){
					
					unset($config['source_url'][$k]);
				}
			}
		}
		
		$config['source_url']=array_unique($config['source_url']);
		$config['source_url']=array_filter($config['source_url']);
		$config['source_url']=array_values($config['source_url']);
	
	
		if(!empty($config['field_list'])){
			
			foreach ($config['field_list'] as $k=>$v){
				$config['field_list'][$k]=json_decode(url_b64decode($v),true);
			}
		}
		if(!empty($config['field_process'])){
			
			foreach ($config['field_process'] as $k=>$v){
				$config['field_process'][$k]=json_decode(url_b64decode($v),true);
				$config['field_process'][$k]=$this->setProcess($config['field_process'][$k]);
			}
		}
		$config['common_process']=input('process/a',null,'trim');
		$config['common_process']=$this->setProcess($config['common_process']);
	
		
		if(!empty($config['paging_fields'])){
			foreach ($config['paging_fields'] as $k=>$v){
				$config['paging_fields'][$k]=json_decode(url_b64decode($v),true);
			}
		}
		
		if(!empty($config['level_urls'])){
			
			foreach ($config['level_urls'] as $k=>$v){
				$config['level_urls'][$k]=json_decode(url_b64decode($v),true);
			}
		}
		
		if(!empty($config['relation_urls'])){
			
			foreach ($config['relation_urls'] as $k=>$v){
				$config['relation_urls'][$k]=json_decode(url_b64decode($v),true);
			}
		}
	
		
		$config['url_post']=intval($config['url_post']);
		
		if(!empty($config['url_posts'])){
			if(!is_array($config['url_posts']['names'])){
				$config['url_posts']['names']=array();
			}
			if(!is_array($config['url_posts']['vals'])){
				$config['url_posts']['vals']=array();
			}
			if(!empty($config['url_posts']['names'])){
				foreach ($config['url_posts']['names'] as $k=>$v){
					if(empty($v)){
						
						unset($config['url_posts']['names'][$k]);
						unset($config['url_posts']['vals'][$k]);
					}
				}
			}
			$config['url_posts']['names']=array_values($config['url_posts']['names']);
			$config['url_posts']['vals']=array_values($config['url_posts']['vals']);
		}
	
		return $config;
	}
	public function init($collData){
		$collData['config']=unserialize($collData['config']);
		$this->collector=$collData;
		$releData=model('Release')->where(array('task_id'=>$collData['task_id']))->find();
		if(!empty($releData)){
			$releData=$releData->toArray();
		}
		$this->release=$releData;
		
		$keyConfig='collector_config_'.$collData['id'];
		$cacheConfig=cache($keyConfig);
		if(empty($cacheConfig)||$cacheConfig['update_time']!=$collData['uptime']){
			
			$config=$this->initConfig($collData['config']);
			cache($keyConfig,array('update_time'=>$collData['uptime'],'config'=>$config));
		}else{
			$config=$cacheConfig['config'];
		}
		$this->config=$config;
	}
	
	public function initConfig($config){
		$newConfig=array();
		$newConfig['charset'] = $config['charset']=='custom' ? $config ['charset_custom'] : $config ['charset'];
		$newConfig['charset']= empty($newConfig['charset'])?'auto':$newConfig['charset'];
		
		if(!empty($config['area'])){
			
			if(empty($config['area_module'])){
				
				$newConfig['reg_source_cont']=$this->convert_sign_match($config['area']);
			}
		}elseif(!empty($config['area_start'])||!empty($config['area_end'])) {
			
			$newConfig['reg_source_cont'] = $config['area_start'] . (!empty($config['area_end']) ? '(?P<match>[\s\S]+?)' : '(?P<match>[\s\S]+)') . $config['area_end'];
		}
		if(!empty($newConfig['reg_source_cont'])){
			
			$newConfig['reg_source_cont'] = str_replace ( '(*)', '[\s\S]*?', $newConfig['reg_source_cont'] );
			$newConfig['reg_source_cont'] = preg_replace ( '/\\\*([\'\/])/', "\\\\$1", $newConfig['reg_source_cont'] );
		}elseif(!empty($config['area_module'])){
			
			$newConfig['reg_source_cont']=$config['area'];
		}
	
		
		if(empty($config['url_rule_module'])){
			
			if(!empty($config['url_rule'])){
				$newConfig['reg_source_cont_url']=$this->convert_sign_match($config['url_rule']);
				$newConfig['reg_source_cont_url']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $newConfig['reg_source_cont_url']);
				$newConfig['reg_source_cont_url']=str_replace ( '(*)', '[\s\S]*?', $newConfig['reg_source_cont_url'] );
			}else{
				
				$newConfig['reg_source_cont_url']='\bhref=[\'\"](?P<match>[^\'\"\<\>]+?)[\'\"]';
			}
			
			$config['url_merge']=$this->set_merge_default($newConfig['reg_source_cont_url'], $config['url_merge']);
		}elseif('xpath'==$config['url_rule_module']){
			if(!empty($config['url_rule'])){
				$newConfig['reg_source_cont_url']=$config['url_rule'];
			}else{
				
				$newConfig['reg_source_cont_url']='//a';
			}
			
			$config['url_merge']=$this->set_merge_default('(?P<match>.+)', $config['url_merge']);
		}elseif('json'==$config['url_rule_module']){
			$newConfig['reg_source_cont_url']=$config['url_rule'];
			
			$config['url_merge']=$this->set_merge_default('(?P<match>.+)', $config['url_merge']);
		}
		
		if(!empty($config['url_must'])){
			
			$newConfig['url_must']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['url_must']);
			$newConfig['url_must']=str_replace('(*)', '[\s\S]*?', $newConfig['url_must']); 
		}
	
		
		if(!empty($config['url_ban'])){
			
			$newConfig['url_ban']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['url_ban']);
			$newConfig['url_ban']=str_replace('(*)', '[\s\S]*?', $newConfig['url_ban']); 
		}
		
		if(!empty($config['level_urls'])){
			$config['new_level_urls']=array();
			foreach ($config['level_urls'] as $luk=>$luv){
				
				if(!empty($luv['area'])){
					if(empty($luv['area_module'])){
						
						$luv['reg_area']=$this->convert_sign_match($luv['area']);
						$luv['reg_area']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['reg_area']);
						$luv['reg_area']=str_replace('(*)', '[\s\S]*?', $luv['reg_area']); 
					}else{
						
						$luv['reg_area']=$luv['area'];
					}
					$luv['reg_area_module']=$luv['area_module'];
				}
				
				if(empty($luv['url_rule_module'])){
					
					if(!empty($luv['url_rule'])){
						$luv['reg_url']=$this->convert_sign_match($luv['url_rule']);
						$luv['reg_url']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['reg_url']);
						$luv['reg_url']=str_replace ( '(*)', '[\s\S]*?', $luv['reg_url'] );
					}else{
						
						$luv['reg_url']='\bhref=[\'\"](?P<match>[^\'\"\<\>]+?)[\'\"]';
					}
					
					$luv['url_merge']=$this->set_merge_default($luv['reg_url'], $luv['url_merge']);
				}elseif('xpath'==$luv['url_rule_module']){
					if(!empty($luv['url_rule'])){
						$luv['reg_url']=$luv['url_rule'];
					}else{
						
						$luv['reg_url']='//a';
					}
					
					$luv['url_merge']=$this->set_merge_default('(?P<match>.+)', $luv['url_merge']);
				}elseif('json'==$luv['url_rule_module']){
					$luv['reg_url']=$luv['url_rule'];
					
					$luv['url_merge']=$this->set_merge_default('(?P<match>.+)', $luv['url_merge']);
				}
				$luv['reg_url_module']=$luv['url_rule_module'];
	
				
				if(!empty($luv['url_must'])){
					
					$luv['url_must']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['url_must']);
					$luv['url_must']=str_replace('(*)', '[\s\S]*?', $luv['url_must']); 
				}
	
				
				if(!empty($luv['url_ban'])){
					
					$luv['url_ban']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['url_ban']);
					$luv['url_ban']=str_replace('(*)', '[\s\S]*?', $luv['url_ban']); 
				}
	
				$config['level_urls'][$luk]=$luv;
				$config['new_level_urls'][$luv['name']]=$luv;
			}
		}
		
		$relation_urls=array();
		if(!empty($config['relation_urls'])){
			foreach ($config['relation_urls'] as $ruv){
				
				if(!empty($ruv['area'])){
					if(empty($ruv['area_module'])){
						
						$ruv['reg_area']=$this->convert_sign_match($ruv['area']);
						$ruv['reg_area']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $ruv['reg_area']);
						$ruv['reg_area']=str_replace('(*)', '[\s\S]*?', $ruv['reg_area']); 
					}else{
						
						$ruv['reg_area']=$ruv['area'];
					}
					$ruv['reg_area_module']=$ruv['area_module'];
				}
				
				if(empty($ruv['url_rule_module'])){
					
					$ruv['reg_url']=$this->convert_sign_match($ruv['url_rule']);
					$ruv['reg_url']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $ruv['reg_url']);
					$ruv['reg_url']=str_replace('(*)', '[\s\S]*?', $ruv['reg_url']); 
					
					$ruv['url_merge']=$this->set_merge_default($ruv['reg_url'], $ruv['url_merge']);
				}elseif(in_array($ruv['url_rule_module'],array('xpath','json'))){
					$ruv['reg_url']=$ruv['url_rule'];
					
					$ruv['url_merge']=$this->set_merge_default('(?P<match>.+)', $ruv['url_merge']);
				}
				$ruv['reg_url_module']=$ruv['url_rule_module'];
	
				$relation_urls[$ruv['name']]=$ruv;
			}
		}
		$relation_depth_urls=array();
		foreach ($relation_urls as $ruv){
			$rDepth=0;
			$rFuName=$ruv['page'];
			if(empty($rFuName)){
				
				$rDepth=0;
			}else{
				
				$passRelation=false;
				$rFuPage=$rFuName;
				do{
					if(empty($relation_urls[$rFuPage])){
						
						$passRelation=true;
						break;
					}
					$rFuPage=$relation_urls[$rFuPage]['page'];
					if($rFuPage==$rFuName){
						
						$passRelation=true;
						break;
					}
					$rDepth++;
				}while(!empty($rFuPage));
	
				if($passRelation){
					
					continue;
				}
			}
			$relation_depth_urls[$rDepth][$ruv['name']]=$ruv;
		}
		ksort($relation_depth_urls);
		$config['new_relation_urls']=array();
		foreach ($relation_depth_urls as $rurls){
			
			if(is_array($rurls)){
				$config['new_relation_urls']=array_merge($config['new_relation_urls'],$rurls);
			}
		}
	
		
		if(!empty($config['field_list'])){
			foreach ($config['field_list'] as $fk=>$fv){
				if('rule'==$fv['module']){
					
					$fv['reg_rule']=$this->convert_sign_match($fv['rule']);
					$fv['reg_rule']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $fv['reg_rule']);
					$fv['reg_rule']=str_replace('(*)', '[\s\S]*?', $fv['reg_rule']); 
					
					$fv['rule_merge']=$this->set_merge_default($fv['reg_rule'], $fv['rule_merge']);
				}elseif('extract'==$fv['module']){
					
					if(!empty($fv['extract_rule'])){
						
						$fv['reg_extract_rule']=$this->convert_sign_match($fv['extract_rule']);
						$fv['reg_extract_rule']=preg_replace('/\\\*([\'\/])/', "\\\\$1",$fv['reg_extract_rule']);
						$fv['reg_extract_rule']=str_replace('(*)', '[\s\S]*?', $fv['reg_extract_rule']); 
						
						$fv['extract_rule_merge']=$this->set_merge_default($fv['reg_extract_rule'], '');
					}
				}
				$config['field_list'][$fk]=$fv;
			}
		}
		
		if(!empty($config['field_process'])){
			foreach ($config['field_process'] as $k=>$v){
				$config['field_process'][$k]=$this->initProcess($v);
			}
		}
		
		if(!empty($config['common_process'])){
			$config['common_process']=$this->initProcess($config['common_process']);
		}
		
		
		if(!empty($config['paging']['area'])){
			if(empty($config['paging']['area_module'])){
				$config['paging']['reg_area']=$this->convert_sign_match($config['paging']['area']);
				$config['paging']['reg_area']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['reg_area']);
				$config['paging']['reg_area']=str_replace('(*)', '[\s\S]*?', $config['paging']['reg_area']); 
				
				$config['paging']['reg_area_merge']=$this->set_merge_default($config['paging']['reg_area'], '');
			}else{
				
				$config['paging']['reg_area']=$config['paging']['area'];
			}
			$config['paging']['reg_area_module']=$config['paging']['area_module'];
		}
		
		if(!empty($config['paging']['url_rule'])){
			if(empty($config['paging']['url_rule_module'])){
				
				$config['paging']['reg_url']=$this->convert_sign_match($config['paging']['url_rule']);
				$config['paging']['reg_url']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['reg_url']);
				$config['paging']['reg_url']=str_replace ( '(*)', '[\s\S]*?', $config['paging']['reg_url'] );
	
				
				$config['paging']['url_merge']=$this->set_merge_default($config['paging']['reg_url'], $config['paging']['url_merge']);
				if(empty($config['paging']['url_merge'])){
					
					$config['paging']['url_merge']=cp_sign('match');
				}
			}else{
				
				$config['paging']['reg_url']=$config['paging']['url_rule'];
				
				$config['paging']['url_merge']=$this->set_merge_default('(?P<match>.+)', $config['paging']['url_merge']);
			}
			$config['paging']['reg_url_module']=$config['paging']['url_rule_module'];
		}
	
		
		if(!empty($config['paging']['url_must'])){
			
			$config['paging']['url_must']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['url_must']);
			$config['paging']['url_must']=str_replace('(*)', '[\s\S]*?', $config['paging']['url_must']); 
		}
	
		
		if(!empty($config['paging']['url_ban'])){
			
			$config['paging']['url_ban']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['url_ban']);
			$config['paging']['url_ban']=str_replace('(*)', '[\s\S]*?', $config['paging']['url_ban']); 
		}
	
	
		
		$module_normal_fields=array();
		$module_extract_fields=array();
		$module_merge_fields=array();
		if(!empty($config['field_list'])){
			foreach ($config['field_list'] as $fk=>$fv){
				$fieldModule=strtolower($fv['module']);
				$fieldConfig=array('field'=>$fv,'process'=>$config['field_process'][$fk]);
				if('extract'==$fieldModule){
					
					$module_extract_fields[$fv['name']]=$fieldConfig;
				}elseif('merge'==$fieldModule){
					
					$module_merge_fields[$fv['name']]=$fieldConfig;
				}else{
					
					$module_normal_fields[$fv['name']]=$fieldConfig;
				}
			}
		}
		
		$config['new_field_list']=array_merge($module_normal_fields,$module_extract_fields,$module_merge_fields);
	
	
		
		$new_paging_fields=array(
				'normal'=>array(),
				'extract'=>array(),
				'merge'=>array(),
		);
		if(!empty($config['paging_fields'])){
			$pagingFields=array();
			foreach ($config['paging_fields'] as $pfield){
				
				$pagingFields[$pfield['field']]=$pfield;
			}
			if(!empty($pagingFields['::all'])){
				
				$fieldAllParams=$pagingFields['::all'];
				unset($pagingFields['::all']);
				foreach ($config['new_field_list'] as $k=>$v){
					
					if(empty($pagingFields[$k])){
						
						$fieldAllParams['field']=$k;
						$pagingFields[$k]=$fieldAllParams;
					}
				}
			}
			$config['paging_fields']=$pagingFields;
			unset($pagingFields);
			
			foreach ($config['paging_fields'] as $pfk=>$pfield){
				$pfield['delimiter']=str_replace(array('\r','\n'), array("\r","\n"), $pfield['delimiter']);
				$config['paging_fields'][$pfk]=$pfield;
				if(!empty($module_normal_fields[$pfield['field']])){
					
					$new_paging_fields['normal'][$pfield['field']]=$pfield;
				}elseif(!empty($module_extract_fields[$pfield['field']])){
					
					$new_paging_fields['extract'][$pfield['field']]=$pfield;
				}elseif(!empty($module_merge_fields[$pfield['field']])){
					
					$new_paging_fields['merge'][$pfield['field']]=$pfield;
				}
			}
		}
		
		$config['new_paging_fields']=array_merge($new_paging_fields['normal'],$new_paging_fields['extract'],$new_paging_fields['merge']);
	
		$config=array_merge($config,$newConfig);
		return $config;
	}
	/*采集级别网址*/
	public function collLevelUrls($source_url,$curLevel=1){
		$curLevel=$curLevel>0?$curLevel:0;
		if($curLevel>0){
			
			$nextLevel=0;
			if(!empty($this->config['level_urls'])){
				
				if(!empty($this->config['level_urls'][$curLevel-1])){
					
					if(!empty($this->config['level_urls'][$curLevel])){
						
						$nextLevel=$curLevel+1;
					}
				}
			}
			
			$cont_urls=$this->getLevelUrls($source_url,$curLevel);
		}else{
			
			$cont_urls=$this->getContUrls($source_url);
		}
		
		return array('urls'=>$cont_urls,'levelName'=>$this->config['level_urls'][$curLevel-1]['name'],'nextLevel'=>$nextLevel);
	}
	
	/*获取内容网址*/
	public function getContUrls($source_url){
		if(empty($source_url)){
			return $this->error('请输入起始网址');
		}
		
		$config=array(
				'reg_area'=>$this->config['reg_source_cont'],
				'reg_area_module'=>$this->config['area_module'],
				'reg_url'=>$this->config['reg_source_cont_url'],
				'reg_url_module'=>$this->config['url_rule_module'],
				'url_merge'=>$this->config['url_merge'],
				'url_must'=>$this->config['url_must'],
				'url_ban'=>$this->config['url_ban'],
		);
		return $this->_get_urls($source_url, $config);
	}
	/*获取多级网址*/
	public function getLevelUrls($parent_url,$level=1){
		$level=$level>1?$level:1;
		$config=$this->config['level_urls'][$level-1];
		if(empty($config)){
			return $this->error('没有'.($level).'级网址规则');
		}
		if(empty($config['reg_url'])){
			return $this->error('必须填写多级“提取网址规则”');
		}
	
		if(empty($parent_url)){
			return $this->error('请输入父级网址');
		}
		return $this->_get_urls($parent_url, $config,true);
	}

	/*获取分页链接*/
	public function getPagingUrls($from_url,$html,$is_test=false){
		$paging_urls=array();
		if($this->config['paging']['open']){
			
			if(empty($html)){
				$html=$this->get_html($from_url);
			}
				
			if(!empty($this->config['paging']['reg_url'])){
				
				if(!empty($this->config['new_paging_fields'])){
					
					$base_url=$this->match_base_url($from_url, $html);
					$domain_url=$this->match_domain_url($from_url);
						
					$paging_area='';
					if(!empty($this->config['paging']['reg_area'])){
						
						if(empty($this->config['paging']['reg_area_module'])){
							
							$sign_match=$this->sign_addslashes(cp_sign('match','(?P<num>\d*)'));
							if(preg_match_all('/'.$sign_match.'/i', $this->config['paging']['reg_area_merge'],$match_signs)){
								
								if(preg_match('/'.$this->config['paging']['reg_area'].'/i',$html,$m_paging_area)){
									
									$re_match=array();
									foreach($match_signs['num'] as $ms_k=>$ms_v){
										$re_match[$ms_k]=$m_paging_area['match'.$ms_v];
									}
									$paging_area=str_replace($match_signs[0], $re_match, $this->config['paging']['reg_area_merge']);
								}
							}else{
								if(preg_match('/'.$this->config['paging']['reg_area'].'/i',$html,$m_paging_area)){
									
									$paging_area=$m_paging_area[0];
								}
							}
						}elseif('json'==$this->config['paging']['reg_area_module']){
							$paging_area=$this->rule_module_json_data(array('json'=>$this->config['paging']['reg_area'],'json_arr'=>'jsonencode'),$html);
						}elseif('xpath'==$this->config['paging']['reg_area_module']){
							$paging_area=$this->rule_module_xpath_data(array('xpath'=>$this->config['paging']['reg_area'],'xpath_attr'=>'outerHtml'),$html);
						}
					}else{
						
						$paging_area=$html;
					}
					if(!empty($paging_area)){
						
	
						
						if(!empty($this->config['paging']['url_complete'])){
							
							$paging_area=preg_replace_callback('/(?<=\bhref\=[\'\"])([^\'\"]*)(?=[\'\"])/i',function($matche_p_a) use ($base_url,$domain_url){
								
								return \skycaiji\admin\event\Cpattern::create_complete_url($matche_p_a[1], $base_url, $domain_url);
							},$paging_area);
						}
	
						$m_paging_urls=$this->rule_match_urls($this->config['paging'],$paging_area,true);
	
						
						foreach ($m_paging_urls as $purl){
							if(!empty($this->config['paging']['url_must'])){
								
								if(!preg_match('/'.$this->config['paging']['url_must'].'/i', $purl)){
									continue;
								}
							}
							if(!empty($this->config['paging']['url_ban'])){
								
								if(preg_match('/'.$this->config['paging']['url_ban'].'/i', $purl)){
									continue;
								}
							}
								
							if($from_url==$purl){
								
								continue;
							}
								
							if(strpos($purl,' ')==false){
								
								$paging_urls[]=$purl;
							}
								
						}
	
						
						if(!empty($paging_urls)){
							$paging_urls=array_filter($paging_urls);
							$paging_urls=array_unique($paging_urls);
							$paging_urls=array_values($paging_urls);
							
						}else{
							if($is_test){
								return $this->error('未获取到分页链接，请检查分页链接规则');
							}
						}
					}else{
						if($is_test){
							return $this->error('未获取到分页区域，请检查分页区域规则');
						}
					}
				}else{
					if($is_test){
						return $this->error('请添加分页内容字段');
					}
				}
			}else{
				if($is_test){
					return $this->error('必须填写分页链接规则');
				}
			}
		}else{
			if($is_test){
				return $this->error('未开启分页');
			}
		}
		return $paging_urls;
	}
	
	/*设置字段值*/
	public function setField($field_config,$cur_url,$html,$cont_url){
		$cur_url_md5=md5($cur_url);

		$field_process=$field_config['process'];
		$field_params=$field_config['field'];
		$module=strtolower($field_params['module']);
	
		if(!empty($field_params['source'])&&in_array($module, array('rule','xpath','json','auto'))){
			
			$field_source_url='';
			$source_echo_msg='——采集';
			if('source_url'==$field_params['source']){
				
				$field_source_url=$this->cur_source_url;
				$source_echo_msg.='起始页';
			}elseif(preg_match('/^relation_url:(.+)$/i', $field_params['source'],$relationName)){
				
				$relationName=$relationName[1];
				$field_source_url=$this->getRelationUrl($relationName, $cur_url, $html);
				$source_echo_msg.="关联页“{$relationName}”";
			}elseif(preg_match('/^level_url:(.+)$/i', $field_params['source'],$levelName)){
				
				$levelName=$levelName[1];
				if(empty($this->config['new_level_urls'][$levelName])){
					
					return;
				}
				if(empty($this->cur_level_urls[$levelName])){
					
					return;
				}
				$field_source_url=$this->cur_level_urls[$levelName];
				$source_echo_msg.="多级页“{$levelName}”";
			}
			if(empty($field_source_url)){
				
				return;
			}
			
			if($field_source_url!=$cur_url){
				$cur_url=$field_source_url;
				$this->echo_msg($source_echo_msg."：<a href='{$field_source_url}' target='_blank'>{$field_source_url}</a>",'black');
				$html=$this->get_html($field_source_url,true);
			}
		}
		static $fieldArr1=array('words','num','time','list');
		static $fieldArr2=array('auto','json');
		static $baseUrls=array();
		static $domainUrls=array();
	
		$urlMd5=md5($cur_url);
		if(empty($baseUrls[$urlMd5])){
			$baseUrls[$urlMd5]=$this->match_base_url($cur_url, $html);
		}
		if(empty($domainUrls[$urlMd5])){
			$domainUrls[$urlMd5]=$this->match_domain_url($cur_url);
		}
		$base_url=$baseUrls[$urlMd5];
		$domain_url=$domainUrls[$urlMd5];
	
		$val='';
		$field_func='field_module_'.$module;
		if(method_exists($this, $field_func)){
			
			if('extract'==$module){
				
				
				if(is_array($this->field_val_list[$field_params['extract']]['values'][$cur_url_md5])){
					
					$val=array();
					foreach ($this->field_val_list[$field_params['extract']]['values'][$cur_url_md5] as $k=>$v){
						$extract_field_val=array(
							'value'=>$v,
							'img'=>$this->field_val_list[$field_params['extract']]['imgs'][$cur_url_md5][$k],
						);
						$val[$k]=$this->field_module_extract($field_params, $extract_field_val, $base_url, $domain_url);
					}
				}else{
					
					$extract_field_val=array(
						'value'=>$this->field_val_list[$field_params['extract']]['values'][$cur_url_md5],
						'img'=>$this->field_val_list[$field_params['extract']]['imgs'][$cur_url_md5],
					);
					$val=$this->field_module_extract($field_params, $extract_field_val, $base_url, $domain_url);
				}
			}elseif('merge'==$module){
				
				if(empty($this->first_loop_field)){
					
					$cur_field_val_list=array();
					foreach ($this->field_val_list as $k=>$v){
						$cur_field_val_list[$k]=array(
							'value'=>$v['values'][$cur_url_md5],
							'img'=>$v['imgs'][$cur_url_md5]
						);
					}
					$val=$this->field_module_merge($field_params,$cur_field_val_list);
				}else{
					
					$val=array();
					
					foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
						$cur_field_val_list=array();
						foreach ($this->field_val_list as $k=>$v){
							$cur_field_val_list[$k]=array(
								'value'=>(is_array($v['values'][$cur_url_md5])?$v['values'][$cur_url_md5][$v_k]:$v['values'][$cur_url_md5]),
								'img'=>(is_array($v['imgs'][$cur_url_md5][$v_k])?$v['imgs'][$cur_url_md5][$v_k]:$v['imgs'][$cur_url_md5])
							);
						}
						$val[$v_k]=$this->field_module_merge($field_params,$cur_field_val_list);
					}
				}
			}elseif(in_array($module,$fieldArr1)){
				
				if($module=='words'){
					
					$val=$this->$field_func($field_params);
				}else{
					
					if(empty($this->first_loop_field)){
						
						$val=$this->$field_func($field_params);
					}else{
						
						$val=array();
						
						foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
							$val[$v_k]=$this->$field_func($field_params);
						}
					}
				}
			}elseif(in_array($module,$fieldArr2)){
				$val=$this->$field_func($field_params,$html,$cur_url);
			}else{
				$val=$this->$field_func($field_params,$html);
			}
		}
	
		$vals=null;
		if(is_array($val)){
			
			$is_loop=true;
			$vals=array_values($val);
		}else{
			
			$is_loop=false;
			$vals=array($val);
		}
	
		$field_name=$field_params['name'];
		if(!isset($this->field_val_list[$field_name])){
			
			$this->field_val_list[$field_name]=array('values'=>array(),'imgs'=>array());
		}

		$cont_url_md5=empty($cont_url)?$cur_url_md5:md5($cont_url);
		
		foreach ($vals as $v_k=>$val){
			$loopIndex=$is_loop?$v_k:-1;
			if(!empty($field_process)){
				
				$val=$this->process_field($val,$field_process,$cur_url_md5,$loopIndex,$cont_url_md5);
			}
			if(!empty($this->config['common_process'])){
				
				$val=$this->process_field($val,$this->config['common_process'],$cur_url_md5,$loopIndex,$cont_url_md5);
			}
			if(isset($this->exclude_cont_urls[$cont_url_md5][$cur_url_md5])){
				
				if(empty($this->first_loop_field)){
					
					foreach ($this->field_val_list as $f_k=>$f_v){
						
						unset($this->field_val_list[$f_k]['values'][$cur_url_md5]);
						unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5]);
					}
					return;
				}else{
					
					if(isset($this->exclude_cont_urls[$cont_url_md5][$cur_url_md5][$loopIndex])){
						
						if(!$is_loop){
							
							foreach ($this->field_val_list as $f_k=>$f_v){
								
								unset($this->field_val_list[$f_k]['values'][$cur_url_md5]);
								unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5]);
							}
							return;
						}else{
							
							foreach ($this->field_val_list as $f_k=>$f_v){
								
								if(is_array($this->field_val_list[$f_k]['values'][$cur_url_md5])){
									
									unset($this->field_val_list[$f_k]['values'][$cur_url_md5][$v_k]);
								}
								if(is_array($this->field_val_list[$f_k]['imgs'][$cur_url_md5])){
									
									unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5][$v_k]);
								}
							}
							continue;
						}
					}
				}
			}
	
			
			$val=preg_replace_callback('/(?<=\bhref\=[\'\"])([^\'\"]*)(?=[\'\"])/i',function($matche) use ($base_url,$domain_url){
				
				return \skycaiji\admin\event\Cpattern::create_complete_url($matche[1], $base_url, $domain_url);
			},$val);
			$val=preg_replace_callback('/(?<=\bsrc\=[\'\"])([^\'\"]*)(?=[\'\"])/i',function($matche) use ($base_url,$domain_url){
				return \skycaiji\admin\event\Cpattern::create_complete_url($matche[1], $base_url, $domain_url);
			},$val);
					
			if($is_loop){
				
				if(!isset($this->field_val_list[$field_name]['values'][$cur_url_md5])){
					$this->field_val_list[$field_name]['values'][$cur_url_md5]=array();
					$this->field_val_list[$field_name]['imgs'][$cur_url_md5]=array();
				}
				$this->field_val_list[$field_name]['values'][$cur_url_md5][$v_k]=$val;
			}else{
				
				$this->field_val_list[$field_name]['values'][$cur_url_md5]=$val;
			}
			if(!empty($GLOBALS['config']['caiji']['download_img'])&&!empty($val)){
				
				$valImgs=array();
				if(preg_match_all('/<img[^<>]*\bsrc=[\'\"]*(\w+\:\/\/[^\'\"\s]+)[\'\"]*/i',$val,$imgUrls)){
					
					$valImgs=is_array($imgUrls[1])?$imgUrls[1]:array();
				}
				if('extract'==$module&&'cover'==$field_params['extract_module']){
					
					$valImgs=array_merge($valImgs,array($val));
				}
				
				$noImgVal=preg_replace_callback('/\{\[img\]\}(http[s]{0,1}\:\/\/[^\s]+?)\{\[\/img\]\}/i',function($matche) use (&$valImgs){
					$valImgs[]=$matche[1];
					return $matche[1];
				},$val);
	
				if($noImgVal!=$val){
					
					if($is_loop){
						$this->field_val_list[$field_name]['values'][$cur_url_md5][$v_k]=$noImgVal;
					}else{
						$this->field_val_list[$field_name]['values'][$cur_url_md5]=$noImgVal;
					}
				}

				if(!empty($valImgs)){
					$valImgs=array_unique($valImgs);
					$valImgs=array_values($valImgs);
					if($is_loop){
						
						$this->field_val_list[$field_name]['imgs'][$cur_url_md5][$v_k]=$valImgs;
					}else{
						
						$this->field_val_list[$field_name]['imgs'][$cur_url_md5]=$valImgs;
					}
				}
			}
		}
	}
	/*设置分页的字段列表值*/
	public function setPagingFields($cont_url,$page_url){
		$contMd5=md5($cont_url);
		$pageMd5=md5($page_url);
	
		if(empty($page_url)){
			return $this->error('请输入分页网址');
		}
		if(!preg_match('/^\w+\:\/\//',$page_url)){
			return $this->error($page_url.'网址不完整');
		}
		if(empty($this->config['paging']['max'])||(count($this->used_paging_urls[$contMd5])<$this->config['paging']['max'])){
			
			$this->set_html_interval();
			$this->echo_msg("——采集分页：<a href='{$page_url}' target='_blank'>{$page_url}</a>",'black');
				
			$html=$this->get_html($page_url);
			if(empty($html)){
				return $this->error('分页获取失败：'.$page_url);
			}
				
			if(!isset($this->used_paging_urls[$contMd5][$pageMd5])){
				
				$this->used_paging_urls[$contMd5][$pageMd5]=$page_url;
				
				foreach ($this->config['new_paging_fields'] as $v){
					$this->setField($this->config['new_field_list'][$v['field']],$page_url,$html,$cont_url);
				}
			}
			
			$paging_urls=$this->getPagingUrls($page_url,$html);
			if(!empty($paging_urls)){
				
				$nextUrl='';
				foreach ($paging_urls as $purl){
					if(!isset($this->used_paging_urls[$contMd5][md5($purl)])&&$cont_url!=$purl){
						
						$nextUrl=$purl;
						break;
					}
				}
				if(!empty($nextUrl)){
					$this->setPagingFields($cont_url,$nextUrl);
				}
			}
		}
	}

	/**
	 * 获取关联页网址
	 * @param unknown $name 关联页名称
	 * @param unknown $cont_url 内容页网址
	 * @param unknown $html 内容页源码
	 * @return string
	 */
	public function getRelationUrl($name,$cont_url,$html){
		if(empty($html)){
			$html=$this->get_html($cont_url,true);
		}
		if(empty($html)){
			
			return '';
		}
		$relation_url=$this->config['new_relation_urls'][$name];
		if(empty($relation_url)){
			
			return '';
		}
	
		if(empty($relation_url['page'])){
			
			if(!isset($this->relation_url_list[$cont_url][$name])){
				$html=$this->rule_match_area($relation_url, $html);
				if(empty($html)){
					
					return '';
				}
				$relationUrl=$this->rule_match_urls($relation_url, $html);
				$relationUrl=(is_array($relationUrl)&&!empty($relationUrl))?reset($relationUrl):'';
				$this->relation_url_list[$cont_url][$name]=$relationUrl;
			}else{
				$relationUrl=$this->relation_url_list[$cont_url][$name];
			}
		}else{
			
			$page=$relation_url['page'];
			$pass=false;
			$depth_pages=array();
			$depth=0;
			while(!empty($page)){
				
				if($page==$name){
					
					$pass=true;
					break;
				}
				if(empty($this->config['new_relation_urls'][$page])){
					
					$pass=true;
					break;
				}
				$depth++;
				$depth_pages[$depth]=$page;
				$page=$this->config['new_relation_urls'][$page]['page'];
			}
			if($pass){
				
				return '';
			}
				
			krsort($depth_pages);
			$contPage=reset($depth_pages);
			$relationUrl='';
			if(isset($contPage)){
				
				if(!isset($this->relation_url_list[$cont_url][$contPage])){
					$html=$this->rule_match_area($this->config['new_relation_urls'][$contPage], $html);
					if(empty($html)){
						
						return '';
					}
					$relationUrl=$this->rule_match_urls($this->config['new_relation_urls'][$contPage], $html);
					$relationUrl=(is_array($relationUrl)&&!empty($relationUrl))?reset($relationUrl):'';
					$this->relation_url_list[$cont_url][$contPage]=$relationUrl;
				}else{
					$relationUrl=$this->relation_url_list[$cont_url][$contPage];
				}
			}
			$depth_pages=array_slice($depth_pages, 1);
			$depth_pages=is_array($depth_pages)?$depth_pages:array();
			$depth_pages[]=$relation_url['name'];
	
			foreach ($depth_pages as $page){
				if(empty($relationUrl)){
					
					return '';
				}
				if(!isset($this->relation_url_list[$cont_url][$page])){
					
					$relationHtml=$this->get_html($relationUrl,true);
					$relationHtml=$this->rule_match_area($this->config['new_relation_urls'][$page], $relationHtml);
					if(empty($relationHtml)){
						
						return '';
					}
					$relationUrl=$this->rule_match_urls($this->config['new_relation_urls'][$page],$relationHtml);
					$relationUrl=(is_array($relationUrl)&&!empty($relationUrl))?reset($relationUrl):'';
	
					$this->relation_url_list[$cont_url][$page]=$relationUrl;
				}else{
					$relationUrl=$this->relation_url_list[$cont_url][$page];
				}
			}
		}
	
		
		
		
		
		

		return $relationUrl;
	}
	
	/*获取内容页字段列表，这里是入口*/
	public function getFields($cont_url){
		$this->field_val_list=array();
		$this->first_loop_field=null;
	
		if(empty($cont_url)){
			return $this->error('请输入内容页网址');
		}
		if(!preg_match('/^\w+\:\/\//',$cont_url)){
			return $this->error($cont_url.'网址不完整');
		}
		$html=$this->get_html($cont_url,false,$this->config['url_post']);
		if(empty($html)){
			return $this->error('抓取页面失败');
		}
		foreach($this->config['new_field_list'] as $field_config){
			$this->setField($field_config,$cont_url,$html,$cont_url);
		}
		$paging_urls=$this->getPagingUrls($cont_url,$html);
		if(!empty($paging_urls)){
			
			$this->setPagingFields($cont_url,reset($paging_urls));
		}
		
		$val_list=array();
		if(!empty($this->field_val_list)){
			if(empty($this->first_loop_field)){
				
				foreach ($this->field_val_list as $fieldName=>$fieldVal){
					$val_values=array_filter($fieldVal['values']);
					$val_values=implode($this->config['new_paging_fields'][$fieldName]['delimiter'], $val_values);
	
					$val_imgs=array();
					if(!empty($fieldVal['imgs'])){
						foreach ($fieldVal['imgs'] as $v){
							if(!empty($v)){
								if(is_array($v)){
									$val_imgs=array_merge($val_imgs,$v);
								}else{
									$val_imgs[]=$v;
								}
							}
						}
						if(!empty($val_imgs)){
							$val_imgs=array_unique($val_imgs);
							$val_imgs=array_filter($val_imgs);
							$val_imgs=array_values($val_imgs);
						}
					}
					$val_list[$fieldName]=array('value'=>$val_values,'img'=>$val_imgs);
				}
			}else{
				
				
				foreach ($this->field_val_list[$this->first_loop_field]['values'] as $page_key=>$page_vals){
					
					if(empty($page_vals)){
						
						continue;
					}
					foreach ($page_vals as $loop_index=>$loop_val){
						
						$vals=array();
						foreach ($this->field_val_list as $fieldName=>$fieldVals){
							if(is_array($fieldVals['values'][$page_key])){
								
								$val_values=$fieldVals['values'][$page_key][$loop_index];
								$val_imgs=$fieldVals['imgs'][$page_key][$loop_index];
							}else{
								
								$val_values=$fieldVals['values'][$page_key];
								$val_imgs=$fieldVals['imgs'][$page_key];
							}
							if(!empty($val_imgs)){
								$val_imgs=array_unique($val_imgs);
								$val_imgs=array_filter($val_imgs);
								$val_imgs=array_values($val_imgs);
							}
							$vals[$fieldName]=array('value'=>$val_values,'img'=>$val_imgs);
						}
						$val_list[]=$vals;
					}
				}
			}
		}
		return $val_list?$val_list:array();
	}
	/*设置数据处理，保存config时使用*/
	public function setProcess($processList){
		if(is_array($processList)){
			$processList=array_array_map('trim',$processList);
			foreach ($processList as $k=>$v){
				$v['module']=strtolower($v['module']);
				if(!empty($v['title'])){
					$v['title']=str_replace(array("'",'"'),'',strip_tags($v['title']));
				}
				if('html'==$v['module']){
					$v['html_allow']=$this->clear_tags($v['html_allow']);
					$v['html_allow']=implode(',', $v['html_allow']);
					$v['html_filter']=$this->clear_tags($v['html_filter']);
					$v['html_filter']=implode(',', $v['html_filter']);
				}elseif('filter'==$v['module']){
					if(preg_match_all('/[^\r\n]+/', $v['filter_list'],$filterList)){
						$filterList=array_filter(array_unique($filterList[0]));
						$v['filter_list']=implode("\r\n",$filterList);
					}
					$v['filter_list']=trim($v['filter_list']);
				}
				$processList[$k]=$v;
			}
			$processList=array_values($processList);
		}
		return $processList;
	}
	/*初始化数据处理，初始化config时使用*/
	public function initProcess($processList){
		if(!empty($processList)){
			foreach ($processList as $k=>$v){
				if('replace'==$v['module']){
					$v['replace_from']=preg_replace('/\\\*([\'\/])/', "\\\\$1", $v['replace_from']);
					$v['replace_from']=str_replace('(*)', '[\s\S]*?', $v['replace_from']); 
				}
				$processList[$k]=$v;
			}
		}
		return $processList;
	}
	
	/*统一：获取网址列表*/
	public function _get_urls($source_url,$config,$is_level=false){
		$is_level=$is_level?'多级':'';
	
		$html=$this->get_html($source_url);
		if(empty($html)){
			return $this->error($is_level.'页面为空');
		}
		$base_url=$this->match_base_url($source_url, $html);
		$domain_url=$this->match_domain_url($source_url);
		
		$html=$this->rule_match_area($config, $html);
		if(empty($html)){
			return $this->error("未提取到{$is_level}区域内容！");
		}
	
		$cont_urls=$this->rule_match_urls($config, $html);
		$cont_urls1=array();
	
		
		if(isset($this->config['url_op'])){
			
			$op_not_complete=in_array('not_complete',$this->config['url_op'])?true:false;
		}else{
			if(isset($this->config['url_complete'])){
				
				$op_not_complete=$this->config['url_complete']?false:true;
			}else{
				
				$op_not_complete=false;
			}
		}
	
		foreach ($cont_urls as $cont_url){
			if(!$op_not_complete){
				
				$cont_url=$this->create_complete_url($cont_url, $base_url, $domain_url);
			}
			if(!empty($config['url_must'])){
				
				if(!preg_match('/'.$config['url_must'].'/i', $cont_url)){
					continue;
				}
			}
	
			if(!empty($config['url_ban'])){
				
				if(preg_match('/'.$config['url_ban'].'/i', $cont_url)){
					continue;
				}
			}
			if(!empty($cont_url)){
				if(strpos($cont_url,' ')==false){
					
					
					
					$cont_urls1[]=$cont_url;
				}
			}
		}
		$cont_urls=$cont_urls1;
		unset($cont_urls1);
	
		if(empty($cont_urls)){
			return $this->error("未获取到".($is_level?$is_level:'内容')."网址！");
		}else{
			if(!empty($this->config['url_reverse'])){
				
				$cont_urls=array_reverse($cont_urls);
			}
			if(!empty($this->config['url_post'])){
				
				$postParams=array();
				if(!empty($this->config['url_posts']['names'])){
					foreach ($this->config['url_posts']['names'] as $k=>$v){
						if (!empty($v)){
							$postParams[]=$v.'='.rawurlencode($this->config['url_posts']['vals'][$k]);
						}
					}
				}
				if(!empty($postParams)){
					
					$postParams=implode('&', $postParams);
					foreach ($cont_urls as $k=>$v){
						$v.=strpos($v,'?')===false?'?':'&';
						$v.=$postParams;
						$cont_urls[$k]=$v;
					}
				}
			}
			return array_values($cont_urls);
		}
	}
	/*执行采集返回未使用的网址*/
	public function _collect_unused_cont_urls($cont_urls=array(),$echo_str=''){
		$mcollected=model('Collected');
		$count_conts=count($cont_urls);
		if($this->config['url_repeat']){
			
			$db_cont_urls=array();
		}else{
			
			$db_cont_urls=$mcollected->getUrlByUrl($cont_urls);
		}
		$unused_cont_urls=array();
		$count_used=0;
		if(!empty($cont_urls)){
			foreach ($cont_urls as $cont_url){
				if(array_key_exists(md5($cont_url), $this->used_cont_urls)){
					
					$count_used++;
				}elseif(in_array($cont_url, $db_cont_urls)){
					
					$count_used++;
				}else{
					
					$unused_cont_urls[md5($cont_url)]=$cont_url;
				}
			}
		}
		if($count_used>0){
			$count_used=min(count($cont_urls),$count_used);
			$this->echo_msg($echo_str.'采集到'.$count_conts.'条网址，<span style="color:orange">'.$count_used.'</span>条重复，<span style="color:green">'.(count($unused_cont_urls)).'</span>条有效','black');
		}else{
			$this->echo_msg($echo_str.'采集到<span style="color:green">'.$count_conts.'</span>条有效网址','black');
		}
		return $unused_cont_urls;
	}
	/*执行级别采集*/
	public function _collect_level($source_url,$level=1){
		$end_echo='</div>';
	
		$level=max(1,$level);
		$level_str='';
		for($i=1;$i<$level;$i++){
			
		}
		$next_level_str=$level_str;
		if($level<=1){
			
			$this->cur_level_urls=array();
		}
		$this->echo_msg('','',true,'<div style="padding-left:30px;">');
	
		$level_data=$this->collLevelUrls($source_url,$level);
		$this->echo_msg($level_str.'抓取到'.$level.'级“'.$this->config['level_urls'][$level-1]['name'].'”网址'.count($level_data['urls']).'条','black');
	
		$mcollected=model('Collected');
		$mcacheLevel=CacheModel::getInstance('level_url');
	
		if(!empty($level_data['urls'])){
			
			$level_urls=array();
			foreach ($level_data['urls'] as $level_url){
				$level_urls["level_{$level}:{$level_url}"]=$level_url;
			}
				
			$level_interval=$GLOBALS['config']['caiji']['interval']*60;
			$time_interval_list=array();
			
			$cacheLevels=$mcacheLevel->db()->where(array('cname'=>array('in',array_map('md5', array_keys($level_urls)))))->column('dateline','cname');
	
			if(!empty($cacheLevels)){
				$count_db_used=0;
				$sortLevels=array('undb'=>array(),'db'=>array());
				
				foreach ($level_urls as $level_key=>$level_url){
					$md5_level_key=md5($level_key);
					if(!isset($cacheLevels[$md5_level_key])){
						
						$sortLevels['undb'][$level_key]=$level_url;
					}else{
						
						$time_interval=abs(NOW_TIME-$cacheLevels[$md5_level_key]);
						if($time_interval<$level_interval){
							
							$this->used_level_urls[$level_key]=1;
							$count_db_used++;
							$time_interval_list[]=$time_interval;
						}else{
							$sortLevels['db'][$level_key]=$level_url;
						}
					}
				}
				if($count_db_used>0){
					$this->echo_msg($level_str.$count_db_used.'条已采集网址被过滤，下次采集需等待'.($level_interval-max($time_interval_list)).'秒，<a href="'
							.url('Admin/Setting/caiji').'" target="_blank">设置间隔</a>','black');
					if(count($level_urls)<=$count_db_used){
						$this->echo_msg($level_str.$level.'级“'.$this->config['level_urls'][$level-1]['name'].'”网址采集完毕！','green',true,$end_echo);
						return 'completed';
					}
				}
				$level_urls=array_merge($sortLevels['undb'],$sortLevels['db']);
				unset($sortLevels);
				unset($cacheLevels);
			}
			$level_data['urls']=$level_urls;
		}
	
		
		$finished_source=true;
		$cur_level_i=0;;
		if(!empty($level_data['urls'])){
			foreach ($level_data['urls'] as $level_key=>$level_url){
				$cur_level_i++;
				if(array_key_exists($level_key,$this->used_level_urls)){
					
					continue;
				}
				$this->cur_level_urls[$this->config['level_urls'][$level-1]['name']]=$level_url;
				$this->echo_msg("{$next_level_str}分析第{$level}级：<a href='{$level_url}' target='_blank'>{$level_url}</a>",'black');
				if($level_data['nextLevel']>0){
					
					$return_msg=$this->_collect_level($level_url,$level_data['nextLevel']);
					if($return_msg=='completed'){
						$this->echo_msg('','',true,$end_echo);
						return $return_msg;
					}
				}else{
					
					$cont_urls=$this->getContUrls($level_url);
					$cont_urls=$this->_collect_unused_cont_urls($cont_urls,$next_level_str);
					$this->cont_urls_list[$level_key]=$cont_urls;
					$this->_collect_fields($next_level_str);
				}
				if($this->collect_num>0){
					
					if(count($this->collected_field_list)>=$this->collect_num){
						
						if($cur_level_i<count($level_data['urls'])){
							$finished_source=false;
						}
						break;
					}
				}
			}
		}
		if($finished_source){
			
			$source_key='level_'.($level-1).':'.$source_url;
			$this->used_level_urls[$source_key]=1;
			$mcacheLevel->setCache(md5($source_key),$source_key);
			if($level<=1){
				
				$mcacheSource=CacheModel::getInstance('source_url');
				$this->used_source_urls[$source_url]=1;
				$mcacheSource->setCache(md5($source_url),$source_url);
			}
		}
		$this->echo_msg('','',true,$end_echo);
	}
	/*采集字段列表*/
	public function _collect_fields($echo_str=''){
		$mcollected=model('Collected');
		$mcacheSource=CacheModel::getInstance('source_url');
		$mcacheLevel=CacheModel::getInstance('level_url');
		$mcacheCont=CacheModel::getInstance('cont_url');
		
		
		foreach ($this->cont_urls_list as $cont_key=>$cont_urls){
			$source_type=0;
			if('_source_is_url_'==$cont_key){
				$source_type=0;
			}elseif(strpos($cont_key,'level_')===0){
				$source_type=2;
			}else{
				$source_type=1;
			}
				
			if($source_type==2){
				if(array_key_exists($cont_key,$this->used_level_urls)){
					
					continue;
				}
			}else{
				if(array_key_exists($cont_key,$this->used_source_urls)){
					
					continue;
				}
			}
				
			$finished_cont=true;
			$cur_c_i=0;
			foreach ($cont_urls as $cont_url){
				$cur_c_i+=1;
				$md5_cont_url=md5($cont_url);
				if(array_key_exists($md5_cont_url,$this->used_cont_urls)){
					
					continue;
				}
				if($this->config['url_repeat']||$mcollected->getCountByUrl($cont_url)<=0){
					
					if(!empty($this->collected_field_list)){
						
						
						if($this->set_html_interval()===true){
							
							if(!$this->config['url_repeat']&&$mcollected->getCountByUrl($cont_url)>0){
								$this->used_cont_urls[$md5_cont_url]=1;
								continue;
							}
						}
					}
						
					
					if(input('?backstage')){
						
						$backstageDate=CacheModel::getInstance('backstage_task')->db()->field('dateline')->where('cname',$this->collector['task_id'])->find();
						if(empty($backstageDate)||$GLOBALS['backstage_task_runtime']<$backstageDate['dateline']){
							
							unset($GLOBALS['backstage_task_ids'][$this->collector['task_id']]);
							exit('终止进程');
						}
					}
						
					if($mcacheCont->getCount($md5_cont_url)>0){
						
						$this->used_cont_urls[$md5_cont_url]=1;
						continue;
					}
					$mcacheCont->setCache($md5_cont_url, 1);
						
					$this->echo_msg($echo_str."采集内容页：<a href='{$cont_url}' target='_blank'>{$cont_url}</a>",'black');
					$field_vals_list=$this->getFields($cont_url);
	
					$is_loop=empty($this->first_loop_field)?false:true;
					if(!empty($field_vals_list)){
						$is_real_time=false;
						if(!empty($GLOBALS['config']['caiji']['real_time'])&&!empty($GLOBALS['real_time_release'])){
							
							$is_real_time=true;
						}
						if(!$is_loop){
							
							$field_vals_list=array($field_vals_list);
						}else{
							
							
							$loop_cont_urls=array();
							foreach ($field_vals_list as $k=>$field_vals){
								$loop_cont_urls[$k]=$cont_url.'#'.md5(serialize($field_vals));
							}
							if(!empty($loop_cont_urls)){
								$loop_exists_urls=$mcollected->getUrlByUrl($loop_cont_urls);
								if(!empty($loop_exists_urls)){
									
									$loop_exists_urls=array_flip($loop_exists_urls);
									foreach ($loop_cont_urls as $k=>$loop_cont_url){
										if(isset($loop_exists_urls[$loop_cont_url])){
											
											unset($field_vals_list[$k]);
										}
									}
									$this->echo_msg($echo_str.'已过滤'.count($loop_exists_urls).'条重复数据','black');
								}
							}
							if(isset($this->exclude_cont_urls[$md5_cont_url])){
								
								$excludeNum=0;
								foreach($this->exclude_cont_urls[$md5_cont_url] as $k=>$v){
									
									$excludeNum+=count($v);
								}
								
								$this->echo_msg($echo_str.'通过数据处理排除了'.$excludeNum.'条数据','black');
							}
							$field_vals_list=array_values($field_vals_list);
						}
	
						foreach ($field_vals_list as $field_vals){
							$collected_error='';
							$collected_data=array('url'=>$cont_url,'fields'=>$field_vals);
							if($is_loop){
								
								$collected_data['url'].='#'.md5(serialize($field_vals));
							}else{
								
								if(isset($this->exclude_cont_urls[$md5_cont_url])){
									
									$collected_error=reset($this->exclude_cont_urls[$md5_cont_url]);
									$collected_error=$this->exclude_url_msg($collected_error);
								}
							}
							if(empty($collected_error)){
								if(!empty($this->config['field_title'])){
									
									$collected_data['title']=$field_vals[$this->config['field_title']]['value'];
								}
								if(!empty($collected_data['title'])){
									
									if($mcollected->getCountByTitle($collected_data['title'])>0){
										
										$collected_error='标题重复：'.mb_substr($collected_data['title'],0,300,'utf-8');
									}
								}
							}
							if(empty($collected_error)){
								
								if($is_real_time){
									
									
									$GLOBALS['real_time_release']->export(array($collected_data));
										
									unset($collected_data['fields']);
									unset($collected_data['title']);
								}
								
								$this->collected_field_list[]=$collected_data;
							}else{
								
								if(!$this->config['url_repeat']){
									
									controller('ReleaseBase','event')->record_collected($collected_data['url'],
										array('id'=>0,'error'=>$collected_error),array('task_id'=>$this->collector['task_id'],'module'=>$this->release['module'])
									);
								}else{
									
									$this->echo_msg($collected_error);
								}
							}
						}
					}
					
					if($is_loop){
						
						
						controller('ReleaseBase','event')->record_collected(
						$cont_url,array('id'=>1,'target'=>'','desc'=>'循环入库'),array('task_id'=>$this->collector['task_id'],'module'=>$this->release['module']),null,false
						);
					}
				}else{
					
					$this->echo_msg('已采集过该<a href="'.$cont_url.'" target="_blank">网址</a>','black');
				}
				$this->used_cont_urls[$md5_cont_url]=1;
	
				if($this->collect_num>0){
					
					if(count($this->collected_field_list)>=$this->collect_num){
						
						if($cur_c_i<count($cont_urls)){
							
							$finished_cont=false;
						}
						break;
					}
				}
			}
				
			if($finished_cont){
				
				if($source_type==1){
					
					$mcacheSource->setCache(md5($cont_key),$cont_key);
				}elseif($source_type==2){
					
					$mcacheLevel->setCache(md5($cont_key),$cont_key);
				}
	
				if($source_type==2){
					
					$this->used_level_urls[$cont_key]=1;
				}else{
					
					$this->used_source_urls[$cont_key]=1;
				}
			}
				
			if($this->collect_num>0&&count($this->collected_field_list)>=$this->collect_num){
				break;
			}
		}
	}
}
?>