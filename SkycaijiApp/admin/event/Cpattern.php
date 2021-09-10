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
class Cpattern extends CpatternEvent{
    /*处理post配置*/
    public function setConfig($config){
        $config['url_complete']=intval($config['url_complete']);
        
        $config['url_reverse']=intval($config['url_reverse']);
        $config['page_render']=intval($config['page_render']);
        $config['url_repeat']=intval($config['url_repeat']);
        
        if(!is_array($config['regexp_flags'])){
            $config['regexp_flags']=array();
        }
        
        if(!is_array($config['request_headers'])){
            $config['request_headers']=array();
        }
        
        \util\Funcs::filter_key_val_list($config['request_headers']['custom_names'], $config['request_headers']['custom_vals']);
        
        \util\Funcs::filter_key_val_list($config['request_headers']['img_names'], $config['request_headers']['img_vals']);
        
        
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
                $config['field_process'][$k]=$this->set_process($config['field_process'][$k]);
            }
        }
        $config['common_process']=input('process/a',array(),'trim');
        $config['common_process']=$this->set_process($config['common_process']);
        
        
        if(!empty($config['paging_fields'])){
            foreach ($config['paging_fields'] as $k=>$v){
                $config['paging_fields'][$k]=json_decode(url_b64decode($v),true);
            }
        }
        
        if(!empty($config['level_urls'])){
            
            foreach ($config['level_urls'] as $k=>$v){
                $v=json_decode(url_b64decode($v),true);
                $v=$this->set_config_url_web($v);
                $config['level_urls'][$k]=$v;
            }
        }
        
        if(!empty($config['relation_urls'])){
            
            foreach ($config['relation_urls'] as $k=>$v){
                $v=json_decode(url_b64decode($v),true);
                $v=$this->set_config_url_web($v);
                $config['relation_urls'][$k]=$v;
            }
        }
        
        $config=$this->set_config_url_web($config);
        
        return $config;
    }
    /*初始化配置*/
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
        $this->config=is_array($config)?$config:array();
        
        
        $cacheConfigParams=cache($keyConfig.'_params');
        if(empty($cacheConfigParams)||$cacheConfigParams['update_time']!=$collData['uptime']){
            
            $this->initConfigParams();
            cache($keyConfig.'_params',array('update_time'=>$collData['uptime'],'params'=>$this->config_params));
        }else{
            $this->config_params=$cacheConfigParams['params'];
        }
        if(!is_array($this->config_params)){
            $this->config_params=array();
        }
        
        if(is_array($this->config_params['headers'])){
            
            set_g_sc('task_img_headers',$this->config_params['headers']['img']);
        }
        if(!is_array(g_sc('task_img_headers'))){
            set_g_sc('task_img_headers',array());
        }
    }
    
    public function initConfig($config){
        $config=model('Collector')->compatible_config($config);

        $config['charset'] = $config['charset']=='custom' ? $config ['charset_custom'] : $config ['charset'];
        $config['charset']= empty($config['charset'])?'auto':$config['charset'];
        
        
        $config['regexp_flags']=is_array($config['regexp_flags'])?$config['regexp_flags']:array();
        $regexpFlags='';
        if(!in_array('case',$config['regexp_flags'])){
            
            $regexpFlags.='i';
        }
        if(in_array('unicode',$config['regexp_flags'])){
            
            $regexpFlags.='u';
        }
        $config['reg_regexp_flags']=$regexpFlags;
        
        $config=$this->_init_page_config($config, 'url');
        
        
        if(!empty($config['level_urls'])){
            $config['new_level_urls']=array();
            foreach ($config['level_urls'] as $luk=>$luv){
                $luv=$this->_init_page_config($luv, 'level_url');
                $config['level_urls'][$luk]=$luv;
                $config['new_level_urls'][$luv['name']]=$luv;
            }
        }
        
        $relation_urls=array();
        if(!empty($config['relation_urls'])){
            foreach ($config['relation_urls'] as $ruk=>$ruv){
                $ruv=$this->_init_page_config($ruv, 'relation_url');
                $config['relation_urls'][$ruk]=$ruv;
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
                $rFuPages=array();
                do{
                    if(empty($relation_urls[$rFuPage])){
                        
                        $passRelation=true;
                        break;
                    }
                    $rFuPage=$relation_urls[$rFuPage]['page'];
                    if($rFuPage==$rFuName||in_array($rFuPage, $rFuPages)){
                        
                        $passRelation=true;
                        break;
                    }
                    $rFuPages[]=$rFuPage;
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
                    $fv['reg_rule']=$this->correct_reg_pattern($fv['reg_rule']);
                    
                    $fv['reg_rule_merge']=$this->set_merge_default($fv['reg_rule'], $fv['rule_merge']);
                    if(empty($fv['reg_rule_merge'])){
                        
                        $fv['reg_rule_merge']=cp_sign('match');
                    }
                }elseif('extract'==$fv['module']){
                    
                    if(!empty($fv['extract_rule'])){
                        
                        $fv['reg_extract_rule']=$this->convert_sign_match($fv['extract_rule']);
                        $fv['reg_extract_rule']=$this->correct_reg_pattern($fv['reg_extract_rule']);
                        
                        $fv['reg_extract_rule_merge']=$this->set_merge_default($fv['reg_extract_rule'], $fv['extract_rule_merge']);
                        if(empty($fv['reg_extract_rule_merge'])){
                            
                            $fv['reg_extract_rule_merge']=cp_sign('match');
                        }
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
        
        $config['paging']=$this->_init_page_config($config['paging'], 'paging_url');
        
        
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
        
        return $config;
    }
    
    private function _init_page_config($pageConfig,$pageType){
        
        if(!is_array($pageConfig)){
            $pageConfig=array();
        }
        
        if(empty($pageConfig['area_module'])){
            
            $pageConfig['reg_area']=$this->convert_sign_match($pageConfig['area']);
            $pageConfig['reg_area']=$this->correct_reg_pattern($pageConfig['reg_area']);
            
            $pageConfig['reg_area_merge']=$this->set_merge_default($pageConfig['reg_area'], $pageConfig['area_merge']);
            if(empty($pageConfig['reg_area_merge'])){
                
                $pageConfig['reg_area_merge']=cp_sign('match');
            }
        }else{
            
            $pageConfig['reg_area']=$pageConfig['area'];
            
            $pageConfig['reg_area_merge']=$this->set_merge_default('(?P<match>.+)', $pageConfig['area_merge']);
        }
        $pageConfig['reg_area_module']=$pageConfig['area_module'];
        
        
        if(empty($pageConfig['url_rule_module'])){
            
            if($pageType=='level_url'||$pageType=='url'){
                
                if(!empty($pageConfig['url_rule'])){
                    $pageConfig['reg_url']=$this->convert_sign_match($pageConfig['url_rule']);
                    $pageConfig['reg_url']=$this->correct_reg_pattern($pageConfig['reg_url']);
                }else{
                    
                    $pageConfig['reg_url']='\bhref\s*=\s*[\'\"](?P<match>[^\'\"]*)[\'\"]';
                }
                
                $pageConfig['reg_url_merge']=$this->set_merge_default($pageConfig['reg_url'], $pageConfig['url_merge']);
            }else{
                
                $pageConfig['reg_url']=$this->convert_sign_match($pageConfig['url_rule']);
                $pageConfig['reg_url']=$this->correct_reg_pattern($pageConfig['reg_url']);
                
                $pageConfig['reg_url_merge']=$this->set_merge_default($pageConfig['reg_url'], $pageConfig['url_merge']);
            }
            if(empty($pageConfig['reg_url_merge'])){
                
                $pageConfig['reg_url_merge']=cp_sign('match');
            }
        }elseif('xpath'==$pageConfig['url_rule_module']){
            if($pageType=='level_url'||$pageType=='url'){
                
                if(!empty($pageConfig['url_rule'])){
                    $pageConfig['reg_url']=$pageConfig['url_rule'];
                }else{
                    
                    $pageConfig['reg_url']='//a';
                }
            }else{
                
                $pageConfig['reg_url']=$pageConfig['url_rule'];
            }
            
            $pageConfig['reg_url_merge']=$this->set_merge_default('(?P<match>.+)', $pageConfig['url_merge']);
        }elseif('json'==$pageConfig['url_rule_module']){
            $pageConfig['reg_url']=$pageConfig['url_rule'];
            
            $pageConfig['reg_url_merge']=$this->set_merge_default('(?P<match>.+)', $pageConfig['url_merge']);
        }
        $pageConfig['reg_url_module']=$pageConfig['url_rule_module'];
        
        
        if(!empty($pageConfig['url_must'])){
            
            $pageConfig['url_must']=$this->correct_reg_pattern($pageConfig['url_must']);
        }
        
        
        if(!empty($pageConfig['url_ban'])){
            
            $pageConfig['url_ban']=$this->correct_reg_pattern($pageConfig['url_ban']);
        }
        
        if($pageType=='level_url'||$pageType=='url'||$pageType=='relation_url'){
            
            $pageConfig=$this->set_config_url_web($pageConfig);
        }
        return $pageConfig;
    }
    
    
    
    public function initConfigParams(){
        $config=$this->config;
        
        $signs=array();
        
        $headers=array('page'=>array(),'page_headers'=>array(),'img'=>array());
        if(!empty($config['request_headers']['useragent'])){
            $headers['page']['useragent']=$config['request_headers']['useragent'];
        }
        if(!empty($config['request_headers']['cookie'])){
            $headers['page']['cookie']=$config['request_headers']['cookie'];
        }
        if(!empty($config['request_headers']['referer'])){
            $headers['page']['referer']=$config['request_headers']['referer'];
        }
        
        $customHeaders=$this->arrays_to_key_val($config['request_headers']['custom_names'], $config['request_headers']['custom_vals']);
        if(!empty($customHeaders)&&is_array($customHeaders)){
            $headers['page']=array_merge($headers['page'],$customHeaders);
            unset($customHeaders);
        }
        
        $headers['page_headers']=$headers['page'];
        
        
        if(empty($config['request_headers']['img_use_page'])){
            
            $headers['img']=empty($config['request_headers']['open'])?array():$headers['page_headers'];
        }elseif($config['request_headers']['img_use_page']=='y'){
            
            $headers['img']=$headers['page_headers'];
        }
        
        
        $imgHeaders=$this->arrays_to_key_val($config['request_headers']['img_names'], $config['request_headers']['img_vals']);
        if(!empty($imgHeaders)&&is_array($imgHeaders)){
            $headers['img']=array_merge($headers['img'],$imgHeaders);
            unset($imgHeaders);
        }
        
        if(empty($config['request_headers']['open'])){
            
            $headers['page']=null;
        }
        $openImgHeader=false;
        if(!empty($config['request_headers']['img'])){
            
            $openImgHeader=true;
        }else{
            
            if(!empty($config['request_headers']['open'])&&!empty($config['request_headers']['download_img'])){
                $openImgHeader=true;
            }
        }
        if(!$openImgHeader){
            $headers['img']=null;
        }
        
        if(!is_array($this->config_params)){
            $this->config_params=array();
        }
        
        $this->config_params['headers']=$headers;
        
        if(!empty($config['new_level_urls'])){
            foreach ($config['new_level_urls'] as $k=>$v){
                
                $signs['level_url:'.$k]=array(''=>$this->parent_page_signs('level_url', $k, ''));
            }
        }
        
        $signs['url']=array(''=>$this->parent_page_signs('url', '', ''));
        if(!empty($config['new_relation_urls'])){
            foreach ($config['new_relation_urls'] as $k=>$v){
                
                $signs['relation_url:'.$k]=array(''=>$this->parent_page_signs('relation_url', $k, ''));
            }
        }
        
        $this->config_params['signs']=$signs;
    }
    
	/*采集,return false表示终止采集*/
	public function collect($num=10){
		if(!defined('IS_COLLECTING')){
			define('IS_COLLECTING', 1);
		}
	
		if(!$this->show_opened_tools){
			$opened_tools=array();
			
			if(g_sc_c('caiji','robots')){
				$opened_tools[]='遵守robots协议';
			}
			if($this->config['page_render']){
				$opened_tools[]='页面渲染';
			}
			if(g_sc_c('download_img','download_img')){
				$opened_tools[]='图片本地化';
			}
			if(g_sc_c('proxy','open')){
				$opened_tools[]='代理';
			}
			if(g_sc_c('translate','open')){
				$opened_tools[]='翻译';
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
	
		$source_interval=g_sc_c('caiji','interval')*60;
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
				
				$nowTime=time();
				foreach ($this->original_source_urls as $sKey=>$sVal){
					if(!isset($cacheSources[$sKey])){
						
						$sortSources['undb'][$sKey]=$sVal;
					}else{
						
					    $time_interval=abs($nowTime-$cacheSources[$sKey]);
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
				    $sourceWaitTime=$source_interval-max($time_interval_list);
				    $this->echo_msg('起始页过滤了'.$count_db_used.'条已采集网址，再次采集需等待'.\skycaiji\admin\model\Config::wait_time_tips($sourceWaitTime).' <a href="'.url('Admin/Task/edit?id='.$this->collector['task_id']).'" target="_blank">设置运行间隔</a>','black');
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
	
	/*采集级别网址*/
	public function collLevelUrls($source_url,$curLevel=1){
	    $curLevel=$curLevel>0?$curLevel:0;
	    $levelName='';
	    $nextLevel=0;
		if($curLevel>0){
			
			if(!empty($this->config['level_urls'])){
				
				if(!empty($this->config['level_urls'][$curLevel-1])){
				    
				    $levelName=$this->get_config('level_urls',$curLevel-1,'name');
					if(!empty($this->config['level_urls'][$curLevel])){
						
						$nextLevel=$curLevel+1;
					}
				}
			}
			
			$cont_urls=$this->getLevelUrls($source_url,$curLevel);
		}else{
			
			$cont_urls=$this->getContUrls($source_url);
		}
		
		return array('urls'=>$cont_urls,'levelName'=>$levelName,'nextLevel'=>$nextLevel);
	}
	
	/*获取内容网址*/
	public function getContUrls($source_url){
		if(empty($source_url)){
			return $this->error('未设置起始网址');
		}
		
		
		$configNames=array('reg_area','reg_area_module','reg_area_merge','reg_url','reg_url_module','reg_url_merge','url_must','url_ban','url_web');
		
		$config=array();
		
		foreach ($configNames as $configName){
		    $config[$configName]=$this->config[$configName];
		}
		
		$source_type='';
		$source_name='';
		if(empty($this->config['level_urls'])){
		    
		    $source_type='source_url';
		}else{
		    
		    $source_type='level_url';
		    $lastLevel=$this->get_last_level();
		    if(!empty($lastLevel['config'])){
		        $source_name=$lastLevel['config']['name'];
		    }
		}
		
		return $this->_get_urls($source_url,$source_type,$source_name,'url',$config);
	}
	/*获取多级网址*/
	public function getLevelUrls($parent_url,$level=1){
		$level=$level>1?$level:1;
		$config=$this->config['level_urls'][$level-1];
		if(empty($config)){
			return $this->error('未设置第'.($level).'级网址规则');
		}
		if(empty($config['reg_url'])){
		    return $this->error('未设置多级页:'.$config['name'].'»提取网址规则');
		}
		
		$source_type='';
		$source_name='';
		
		
		if($level>1){
		    $source_type='level_url';
		    $source_name=$this->get_config('level_urls',$level-2,'name');
		}else{
		    $source_type='source_url';
		}
		
		
		if(empty($parent_url)){
		    
		    if($source_type=='level_url'){
		        return $this->error('未设置多级页“'.$source_name.'”网址');
		    }else{
		        return $this->error('未设置起始页网址');
		    }
		}
		return $this->_get_urls($parent_url,$source_type,$source_name,'level_url',$config);
	}

	/*获取分页链接*/
	public function getPagingUrls($from_url,$html,$is_test=false){
		$paging_urls=array();
		if($this->config['paging']['open']){
			
			if(empty($html)){
			    $html=$this->get_page_html($from_url, 'url', '');
			}
			
			if(!empty($this->config['paging']['reg_url'])){
				
				if(!empty($this->config['new_paging_fields'])){
					
					$base_url=$this->match_base_url($from_url, $html);
					$domain_url=$this->match_domain_url($from_url);
						
					$paging_area='';
					if(!empty($this->config['paging']['reg_area'])){
						
						if(empty($this->config['paging']['reg_area_module'])){
							
						    $paging_area=$this->get_rule_module_rule_data(array('rule'=>$this->config['paging']['reg_area'],'rule_merge'=>$this->config['paging']['reg_area_merge']), $html,null,true);
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
							
							$paging_area=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche_p_a) use ($base_url,$domain_url){
								
							    $matche_p_a[2]=\skycaiji\admin\event\Cpattern::create_complete_url($matche_p_a[2], $base_url, $domain_url);
								return $matche_p_a[1].$matche_p_a[2].$matche_p_a[3];
							},$paging_area);
						}

						$m_paging_urls=$this->rule_match_urls('paging_url',$this->config['paging'],$paging_area,true);
	
						
						foreach ($m_paging_urls as $purl){
							if($from_url!=$purl){
								
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
						return $this->error('未设置分页内容字段');
					}
				}
			}else{
				if($is_test){
					return $this->error('未设置分页网址规则');
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
		
		$field_name=$field_params['name'];
		if(!isset($this->field_val_list[$field_name])){
		    
		    $this->field_val_list[$field_name]=array('values'=>array(),'imgs'=>array());
		}
	
		if(!empty($field_params['source'])&&in_array($module, array('rule','xpath','json','auto','sign'))){
			
			$field_source_url='';
			$source_echo_msg='——采集';
			
			$pageName=$this->split_page_source($field_params['source']);
			$pageType=$pageName['type'];
			$pageName=$pageName['name'];
			
			if('source_url'==$pageType){
				
				$field_source_url=$this->cur_source_url;
				$source_echo_msg.='起始页';
			}elseif('relation_url'==$pageType){
				
				$field_source_url=$this->getRelationUrl($pageName, $cur_url, $html);
				$source_echo_msg.="关联页“{$pageName}”";
			}elseif('level_url'==$pageType){
				
			    if(empty($this->config['new_level_urls'][$pageName])){
					
					return;
				}
				if(empty($this->cur_level_urls[$pageName])){
					
					return;
				}
				$field_source_url=$this->cur_level_urls[$pageName];
				$source_echo_msg.="多级页“{$pageName}”";
			}
			if(empty($field_source_url)){
				
				return;
			}
			
			if($field_source_url!=$cur_url){
				$cur_url=$field_source_url;
				$this->echo_msg($source_echo_msg."：<a href='{$field_source_url}' target='_blank'>{$field_source_url}</a>",'black');
				$html=$this->get_page_html($field_source_url, $pageType, $pageName, true);
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
				
				if(empty($this->first_loop_field)){
					
					$val=$this->$field_func($field_params);
				}else{
					
					$val=array();
					
					foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
						$val[$v_k]=$this->$field_func($field_params);
					}
				}
			}elseif(in_array($module,$fieldArr2)){
				$val=$this->$field_func($field_params,$html,$cur_url);
			}elseif($module=='sign'){
			    
			    $val=$this->$field_func($field_params,empty($cont_url)?$cur_url:$cont_url);
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

		$cont_url_md5=empty($cont_url)?$cur_url_md5:md5($cont_url);
		
		foreach ($vals as $v_k=>$val){
			$loopIndex=$is_loop?$v_k:-1;
			if(!empty($field_process)){
				
			    $val=$this->process_field($field_name,$val,$field_process,$cur_url_md5,$loopIndex,$cont_url_md5);
			}
			if(!empty($this->config['common_process'])){
				
			    $val=$this->process_field($field_name,$val,$this->config['common_process'],$cur_url_md5,$loopIndex,$cont_url_md5);
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
	
			
			$val=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($base_url,$domain_url){
				
			    $matche[2]=\skycaiji\admin\event\Cpattern::create_complete_url($matche[2], $base_url, $domain_url);
			    return $matche[1].$matche[2].$matche[3];
			},$val);
			$val=preg_replace_callback('/(\bsrc\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($base_url,$domain_url){
			    $matche[2]=\skycaiji\admin\event\Cpattern::create_complete_url($matche[2], $base_url, $domain_url);
				return $matche[1].$matche[2].$matche[3];
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
			if(!is_empty(g_sc_c('download_img','download_img'))&&!empty($val)){
				
				$valImgs=array();
				if(preg_match_all('/<img\b[^<>]*\bsrc\s*=\s*[\'\"](\w+\:[^\'\"]+?)[\'\"]/i',$val,$imgUrls)){
					
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
			return $this->error('分页网址不完整：'.$page_url);
		}
		if(empty($this->config['paging']['max'])||(count((array)$this->used_paging_urls[$contMd5])<$this->config['paging']['max'])){
			
			$this->set_html_interval();
			$this->echo_msg("——采集分页：<a href='{$page_url}' target='_blank'>{$page_url}</a>",'black');
				
			$html=$this->get_page_html($page_url, 'url', '');
			if(empty($html)){
				return $this->error('未获取到分页源代码');
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
		    $html=$this->get_page_html($cont_url, 'url', '', true);
		}
		if(empty($html)){
			
			return '';
		}
		$relation_url=$this->config['new_relation_urls'][$name];
		if(empty($relation_url)){
			
			return '';
		}
		$urlMd5=md5($cont_url);
		
		
		if(!isset($this->page_url_matches['relation_url'])){
		    $this->page_url_matches['relation_url']=array();
		}
		
		if(!isset($this->page_area_matches['relation_url'])){
		    $this->page_area_matches['relation_url']=array();
		}
		
		if(empty($relation_url['page'])){
			
			if(!isset($this->relation_url_list[$name])){
			    
				$areaMatch=$this->rule_match_area('relation_url',$relation_url, $html,true);
				$html=$areaMatch['area'];
				$this->page_area_matches['relation_url'][$name]=$areaMatch['matches'];
				if(empty($html)){
					
					return '';
				}
				$relationUrlsMatches=$this->rule_match_urls('relation_url',$relation_url, $html,true,false,true);
				$relationUrl=$relationUrlsMatches['urls'];
				$relationUrl=(is_array($relationUrl)&&!empty($relationUrl))?reset($relationUrl):'';
				$this->relation_url_list[$name]=$relationUrl;
				$this->page_url_matches['relation_url'][$name]=$relationUrlsMatches['matches'][md5($relationUrl)];
			}else{
				$relationUrl=$this->relation_url_list[$name];
			}
		}else{
			
			$page=$relation_url['page'];
			$pass=false;
			$depth_pages=array();
			$depth=0;
			while(!empty($page)){
				
			    if($page==$name||in_array($page, $depth_pages)){
					
					$pass=true;
					break;
				}
				if(empty($this->config['new_relation_urls'][$page])){
					
					$pass=true;
					break;
				}
				$depth++;
				$depth_pages[$depth]=$page;
				$page=$this->get_config('new_relation_urls',$page,'page');
			}
			if($pass){
				
				return '';
			}
			$pageName='';
				
			krsort($depth_pages);
			$contPage=reset($depth_pages);
			$relationUrl='';
			if(isset($contPage)){
			    $pageName=$contPage;
				
				if(!isset($this->relation_url_list[$contPage])){
				    $areaMatch=$this->rule_match_area('relation_url',$this->config['new_relation_urls'][$contPage], $html,true);
					$html=$areaMatch['area'];
					$this->page_area_matches['relation_url'][$contPage]=$areaMatch['matches'];
					if(empty($html)){
						
						return '';
					}
					$relationUrlsMatches=$this->rule_match_urls('relation_url',$this->config['new_relation_urls'][$contPage], $html,true,false,true);
					$relationUrl=$relationUrlsMatches['urls'];
					$relationUrl=(is_array($relationUrl)&&!empty($relationUrl))?reset($relationUrl):'';
					$this->relation_url_list[$contPage]=$relationUrl;
					$this->page_url_matches['relation_url'][$contPage]=$relationUrlsMatches['matches'][md5($relationUrl)];
				}else{
					$relationUrl=$this->relation_url_list[$contPage];
				}
			}
			$depth_pages=array_slice($depth_pages, 1);
			$depth_pages=is_array($depth_pages)?$depth_pages:array();
			$depth_pages[]=$relation_url['name'];

			foreach ($depth_pages as $page){
				if(empty($relationUrl)){
					
					return '';
				}
				if(!isset($this->relation_url_list[$page])){
					
				    $relationHtml=$this->get_page_html($relationUrl, 'relation_url', $pageName, true);
				    $pageName=$page;
				    $areaMatch=$this->rule_match_area('relation_url',$this->config['new_relation_urls'][$page], $relationHtml,true);
				    $relationHtml=$areaMatch['area'];
					$this->page_area_matches['relation_url'][$page]=$areaMatch['matches'];
					if(empty($relationHtml)){
						
						return '';
					}
					$relationUrlsMatches=$this->rule_match_urls('relation_url',$this->config['new_relation_urls'][$page],$relationHtml,true,false,true);
					$relationUrl=$relationUrlsMatches['urls'];
					$relationUrl=(is_array($relationUrl)&&!empty($relationUrl))?reset($relationUrl):'';
					$this->relation_url_list[$page]=$relationUrl;
					$this->page_url_matches['relation_url'][$page]=$relationUrlsMatches['matches'][md5($relationUrl)];
				}else{
					$relationUrl=$this->relation_url_list[$page];
				}
			}
		}
	
		
		
		
		
		

		return $relationUrl;
	}
	
	/*获取内容页字段列表，这里是入口*/
	public function getFields($cont_url){
		$this->field_val_list=array();
		$this->first_loop_field=null;
		$this->relation_url_list=array();
		$this->cur_cont_url=$cont_url;
	
		if(empty($cont_url)){
			return $this->error('请输入内容页网址');
		}
		if(!preg_match('/^\w+\:\/\//',$cont_url)){
			return $this->error($cont_url.'网址不完整');
		}
		if(empty($this->config['new_field_list'])){
		    return $this->error('未设置字段');
		}
		$html=$this->get_page_html($cont_url, 'url', '');
		if(empty($html)){
			return $this->error('未获取到源代码');
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
				    $val_values='';
				    if(!empty($fieldVal['values'])){
				        $val_values=\util\Funcs::array_filter_keep0($fieldVal['values']);
				        $valDelimiter='';
				        if(!empty($this->config['new_paging_fields'][$fieldName])){
				            $valDelimiter=$this->get_config('new_paging_fields',$fieldName,'delimiter');
				        }
				        $val_values=implode($valDelimiter, $val_values);
				    }
					
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
	/*初始化数据处理，初始化config时使用*/
	public function initProcess($processList){
		if(!empty($processList)){
			foreach ($processList as $k=>$v){
				if('replace'==$v['module']){
				    $v['replace_from']=$this->correct_reg_pattern($v['replace_from']);
				}
				$processList[$k]=$v;
			}
		}
		return $processList;
	}
	
	
	/*统一：获取网址列表*/
	public function _get_urls($source_url,$source_type,$source_name,$page_type,$config){
	    $pageType='';
	    $pageName='';
	    $pageTips='';
	    if($page_type=='level_url'){
	        $pageType='level_url';
	        $pageName=$config['name'];
	        $pageTips='多级页“'.$pageName.'”';
	    }else{
	        $pageType='url';
	        $pageName='';
	        $pageTips='内容页';
	    }
	    
	    
	    if(!isset($this->page_url_matches[$pageType])){
	        $this->page_url_matches[$pageType]=array();
	    }
	    if(!isset($this->page_url_matches[$pageType][$pageName])){
	        $this->page_url_matches[$pageType][$pageName]=array();
	    }
	    
	    if(!isset($this->page_area_matches[$pageType])){
	        $this->page_area_matches[$pageType]=array();
	    }
	    if(!isset($this->page_area_matches[$pageType][$pageName])){
	        $this->page_area_matches[$pageType][$pageName]=array();
	    }
	    
	    
	    $html=$this->get_page_html($source_url, $source_type, $source_name);
		if(empty($html)){
		    $sourceTips='';
		    if($source_type=='source_url'){
		        $sourceTips='起始页';
		    }elseif($source_type=='level_url'){
		        $sourceTips='多级页“'.$source_name.'”';
		    }
		    return $this->error('未获取到'.$sourceTips.'源代码');
		}
		$base_url=$this->match_base_url($source_url, $html);
		$domain_url=$this->match_domain_url($source_url);
		
		$areaMatch=$this->rule_match_area($pageType,$config,$html,true);
		$html=$areaMatch['area'];
		$this->page_area_matches[$pageType][$pageName]=$areaMatch['matches'];
		if(empty($html)){
		    return $this->error('未获取到'.$pageTips.'区域');
		}
		
		
		if(isset($this->config['url_op'])){
		    
		    $op_not_complete=in_array('not_complete',$this->config['url_op'])?true:false;
		}else{
		    if(isset($this->config['url_complete'])){
		        
		        $op_not_complete=$this->config['url_complete']?false:true;
		    }else{
		        
		        $op_not_complete=false;
		    }
		}
	
		$contUrlsMatches=$this->rule_match_urls($pageType,$config,$html,true,$op_not_complete?false:array('base'=>$base_url,'domain'=>$domain_url),true);
		
		$cont_urls=$contUrlsMatches['urls'];
	
		if(empty($cont_urls)){
		    return $this->error("未获取到".$pageTips."网址");
		}else{
		    
		    $contUrlsMd5=array();
		    foreach ($cont_urls as $k=>$v){
		        $contUrlsMd5[]=md5($v);
		    }
		    foreach ($contUrlsMatches['matches'] as $k=>$v){
		        if(!in_array($k,$contUrlsMd5)){
		            unset($contUrlsMatches['matches'][$k]);
		        }
		    }
		    
			if(!empty($this->config['url_reverse'])){
				
				$cont_urls=array_reverse($cont_urls);
			}
			
			$this->page_url_matches[$pageType][$pageName]=$contUrlsMatches['matches'];
			return array_values($cont_urls);
		}
	}
	/*执行采集返回未使用的网址*/
	public function _collect_unused_cont_urls($cont_urls=array(),$echo_str=''){
		$mcollected=model('Collected');
		$count_conts=count((array)$cont_urls);
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
	    $end_echo='</section>';
	
		$level=max(1,$level);
		$level_str='';
		for($i=1;$i<$level;$i++){
			
		}
		$next_level_str=$level_str;
		if($level<=1){
			
			$this->cur_level_urls=array();
		}
		$this->echo_msg('','',true,'<section style="padding-left:30px;">');
	
		
		$level_name=$level.'级“'.$this->get_config('level_urls',$level-1,'name').'”';
		
		$level_data=$this->collLevelUrls($source_url,$level);
		$this->echo_msg($level_str.'抓取到'.$level_name.'网址'.count((array)$level_data['urls']).'条','black');
	
		$mcollected=model('Collected');
		$mcacheLevel=CacheModel::getInstance('level_url');
	
		if(!empty($level_data['urls'])){
			
			$level_urls=array();
			foreach ($level_data['urls'] as $level_url){
				$level_urls["level_{$level}:{$level_url}"]=$level_url;
			}
				
			$level_interval=g_sc_c('caiji','interval')*60;
			$time_interval_list=array();
			
			$cacheLevels=$mcacheLevel->db()->where(array('cname'=>array('in',array_map('md5', array_keys($level_urls)))))->column('dateline','cname');

			if(!empty($cacheLevels)){
				$count_db_used=0;
				$sortLevels=array('undb'=>array(),'db'=>array());
				
				$nowTime=time();
				foreach ($level_urls as $level_key=>$level_url){
					$md5_level_key=md5($level_key);
					if(!isset($cacheLevels[$md5_level_key])){
						
						$sortLevels['undb'][$level_key]=$level_url;
					}else{
						
					    $time_interval=abs($nowTime-$cacheLevels[$md5_level_key]);
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
				    $levelWaitTime=$level_interval-max($time_interval_list);
				    $this->echo_msg($level_str.$level_name.'过滤了'.$count_db_used.'条已采集网址，再次采集需等待'.\skycaiji\admin\model\Config::wait_time_tips($levelWaitTime).' <a href="'.url('Admin/Task/edit?id='.$this->collector['task_id']).'" target="_blank">设置运行间隔</a>','black');
					if(count($level_urls)<=$count_db_used){
					    $this->echo_msg($level_str.$level_name.'网址采集完毕！','green',true,$end_echo);
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
		$cur_level_i=0;
		if(!empty($level_data['urls'])){
			foreach ($level_data['urls'] as $level_key=>$level_url){
				$cur_level_i++;
				if(array_key_exists($level_key,$this->used_level_urls)){
					
				    $this->echo_msg($level_str.'已采集第'.$level.'级：'.$level_url,'orange');
					continue;
				}
				$levelConfig=$this->config['level_urls'][$level-1];
				$this->cur_level_urls[$levelConfig['name']]=$level_url;
				
				$isFormPost='';
				if($this->url_web_is_open($levelConfig['url_web'])&&$levelConfig['url_web']['form_method']=='post'){
				    $isFormPost='[POST模式] ';
				}
				
				$this->echo_msg("{$next_level_str}分析第{$level}级：".($isFormPost?($isFormPost.$level_url):"<a href='{$level_url}' target='_blank'>{$level_url}</a>"),'black');
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
						
						if($cur_level_i<count((array)$level_data['urls'])){
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
						if(empty($backstageDate)||g_sc('backstage_task_runtime')<$backstageDate['dateline']){
							
							set_g_sc(['backstage_task_ids',$this->collector['task_id']],null);
							$this->echo_msg('终止采集');
							$this->_echo_msg_end();
							exit();
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
					$loopExcludeNum=0;
					if($is_loop){
					    
					    if(isset($this->exclude_cont_urls[$md5_cont_url])){
					        
					        $loopExcludeNum=0;
					        foreach($this->exclude_cont_urls[$md5_cont_url] as $k=>$v){
					            
					            $loopExcludeNum+=count((array)$v);
					        }
					        $this->echo_msg($echo_str.'通过数据处理筛除了'.$loopExcludeNum.'条数据','black');
					    }
					}
					if(!empty($field_vals_list)){
						$is_real_time=false;
						if(!is_empty(g_sc_c('caiji','real_time'))&&!is_empty(g_sc('real_time_release'))){
							
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
									$this->echo_msg($echo_str.'已过滤'.count((array)$loop_exists_urls).'条重复数据','black');
								}
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
									
									
								    g_sc('real_time_release')->doExport(array($collected_data));
										
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
						    $cont_url,array('id'=>1,'target'=>'','desc'=>'循环入库'.($loopExcludeNum>0?('，数据处理筛除了'.$loopExcludeNum.'条数据'):'')),array('task_id'=>$this->collector['task_id'],'module'=>$this->release['module']),null,false
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