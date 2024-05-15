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
        $config['page_render']=intval($config['page_render']);
        $config['url_repeat']=intval($config['url_repeat']);
        $config['url_no_name']=intval($config['url_no_name']);
        $config['url_encode']=intval($config['url_encode']);
        
        if(!is_array($config['regexp_flags'])){
            $config['regexp_flags']=array();
        }
        
        if(!is_array($config['request_headers'])){
            $config['request_headers']=array();
        }
        
        \util\Funcs::filter_key_val_list($config['request_headers']['custom_names'], $config['request_headers']['custom_vals']);
        
        \util\Funcs::filter_key_val_list($config['request_headers']['img_names'], $config['request_headers']['img_vals']);
        
        \util\Funcs::filter_key_val_list($config['request_headers']['file_names'], $config['request_headers']['file_vals']);
        
        
        if(is_array($config['front_urls'])){
            
            foreach ($config['front_urls'] as $k=>$v){
                $v=json_decode(url_b64decode($v),true);
                $v=$this->page_set_config('front_url',$v);
                if(empty($v['url'])){
                    unset($config['front_urls'][$k]);
                }else{
                    $config['front_urls'][$k]=$v;
                }
            }
        }
        
        if(!is_array($config['source_url'])){
            $config['source_url']=array();
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
        
        $config['source_config']=$this->page_set_config('source_url',$config['source_config']);
        
        
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
        $config['common_process']=\util\UnmaxPost::val('process/a',array(),null);
        $config['common_process']=$this->set_process($config['common_process']);
        
        if(is_array($config['level_urls'])){
            
            foreach ($config['level_urls'] as $k=>$v){
                $v=json_decode(url_b64decode($v),true);
                $v=$this->page_set_config('level_url',$v);
                $config['level_urls'][$k]=$v;
            }
        }
        
        if(is_array($config['relation_urls'])){
            
            foreach ($config['relation_urls'] as $k=>$v){
                $v=json_decode(url_b64decode($v),true);
                $v=$this->page_set_config('relation_url',$v);
                $config['relation_urls'][$k]=$v;
            }
        }
        
        $config=$this->page_set_config('url',$config);
        
        return $config;
    }
    /*初始化配置*/
    public function init($collData){
        $collData['config']=unserialize($collData['config']?:'');
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
            
            set_g_sc('task_file_headers',$this->config_params['headers']['file']);
        }
        if(!is_array(g_sc('task_img_headers'))){
            set_g_sc('task_img_headers',array());
        }
        if(!is_array(g_sc('task_file_headers'))){
            set_g_sc('task_file_headers',array());
        }
        \util\Param::set_gsc_use_cookie('',null);
        \util\Param::set_gsc_use_cookie('img',null);
        \util\Param::set_gsc_use_cookie('file',null);
    }
    
    public function initConfig($config){
        $config=model('Collector')->compatible_config($config);
        $sourceIsUrl=$config['source_is_url']?true:false;
        
        if($sourceIsUrl){
            
            $config['level_urls']=array();
            $config['area']='';
            $config['url_rule']='';
            
            $config['source_config']=array(
                'url_web'=>$config['url_web'],
                'content_signs'=>$config['content_signs']
            );
        }

        $config['charset']= $config['charset']=='custom' ? $config ['charset_custom'] : $config ['charset'];
        $config['charset']= empty($config['charset'])?'auto':$config['charset'];
        
        $config['encode']=$config['encode']=='custom' ? $config ['encode_custom'] : $config ['encode'];
        
        
        $config['regexp_flags']=is_array($config['regexp_flags'])?$config['regexp_flags']:array();
        $regexpFlags='';
        if(!in_array('case',$config['regexp_flags'])){
            
            $regexpFlags.='i';
        }
        if(in_array('unicode',$config['regexp_flags'])){
            
            $regexpFlags.='u';
        }
        $config['reg_regexp_flags']=$regexpFlags;
        
        
        if(is_array($config['front_urls'])){
            $config['new_front_urls']=array();
            foreach ($config['front_urls'] as $fuk=>$fuv){
                $fuv=$this->_page_init_config('front_url',$fuv);
                $config['front_urls'][$fuk]=$fuv;
                $config['new_front_urls'][$fuv['name']]=$fuv;
            }
        }
        
        $config['source_config']=$this->_page_init_config('source_url',$config['source_config']);
        $config=$this->_page_init_config('url',$config);
        
        
        if(is_array($config['level_urls'])){
            $config['new_level_urls']=array();
            foreach ($config['level_urls'] as $luk=>$luv){
                $luv=$this->_page_init_config('level_url',$luv);
                $config['level_urls'][$luk]=$luv;
                $config['new_level_urls'][$luv['name']]=$luv;
            }
        }
        
        $relation_urls=array();
        if(is_array($config['relation_urls'])){
            foreach ($config['relation_urls'] as $ruk=>$ruv){
                $ruv=$this->_page_init_config('relation_url',$ruv);
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
                $config['new_relation_urls']=\util\Funcs::array_key_merge($config['new_relation_urls'],$rurls);
            }
        }
        
        if(!empty($config['field_list'])){
            foreach ($config['field_list'] as $fk=>$fv){
                if('rule'==$fv['module']){
                    
                    $fv=$this->convert_rule_module_config($fv);
                }elseif('extract'==$fv['module']){
                    
                    if(!empty($fv['extract_rule'])){
                        
                        $fv=$this->convert_rule_module_config($fv,'extract_');
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
        
        $config['new_field_list']=\util\Funcs::array_key_merge($module_normal_fields, $module_extract_fields);
        $config['new_field_list']=\util\Funcs::array_key_merge($config['new_field_list'], $module_merge_fields);
        
        
        if(!empty($config['pagination'])&&is_array($config['pagination']['fields'])){
            
            $new_pn_fields=array(
                'normal'=>array(),
                'extract'=>array(),
                'merge'=>array(),
            );
            $pnFields=array();
            foreach ($config['pagination']['fields'] as $pnField){
                
                $pnFields[$pnField['field']]=$pnField;
            }
            if(!empty($pnFields['::all'])){
                
                $fieldAllParams=$pnFields['::all'];
                unset($pnFields['::all']);
                foreach ($config['new_field_list'] as $k=>$v){
                    
                    if(empty($pnFields[$k])){
                        
                        $fieldAllParams['field']=$k;
                        $pnFields[$k]=$fieldAllParams;
                    }
                }
            }
            $config['pagination']['fields']=$pnFields;
            unset($pnFields);
            
            foreach ($config['pagination']['fields'] as $pfk=>$pnField){
                $pnField['delimiter']=str_replace(array('\r','\n'), array("\r","\n"), $pnField['delimiter']);
                $config['pagination']['fields'][$pfk]=$pnField;
                if(!empty($module_normal_fields[$pnField['field']])){
                    
                    $new_pn_fields['normal'][$pnField['field']]=$pnField;
                }elseif(!empty($module_extract_fields[$pnField['field']])){
                    
                    $new_pn_fields['extract'][$pnField['field']]=$pnField;
                }elseif(!empty($module_merge_fields[$pnField['field']])){
                    
                    $new_pn_fields['merge'][$pnField['field']]=$pnField;
                }
            }
            
            $config['pagination']['new_fields']=\util\Funcs::array_key_merge($new_pn_fields['normal'],$new_pn_fields['extract']);
            $config['pagination']['new_fields']=\util\Funcs::array_key_merge($config['pagination']['new_fields'],$new_pn_fields['merge']);
        }
        
        return $config;
    }
    
    
    private function _page_init_config($pageType,$pageConfig){
        
        if(!is_array($pageConfig)){
            $pageConfig=array();
        }
        if(!$this->page_rule_is_null($pageType)){
            
            $pageConfig=$this->_page_init_rule($pageType, $pageConfig, false);
        }
        
        if(!empty($pageConfig['content_signs'])&&is_array($pageConfig['content_signs'])){
            foreach ($pageConfig['content_signs'] as $k=>$v){
                $pageConfig['content_signs'][$k]=$this->convert_rule_module_config($v);
            }
        }
        
        $pageConfig=$this->page_set_config($pageType,$pageConfig);
        
        
        if(!empty($pageConfig['pagination'])){
            $pageConfig['pagination']=$this->_page_init_rule($pageType, $pageConfig['pagination'], true);
        }
        
        return $pageConfig;
    }
    
    
    public function initConfigParams(){
        $config=$this->config;
        
        $signs=array();
        
        $headers=array('page'=>array(),'page_headers'=>array(),'img'=>array(),'file'=>array());
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
            $headers['page']=\util\Funcs::array_key_merge($headers['page'],$customHeaders);
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
            $headers['img']=\util\Funcs::array_key_merge($headers['img'],$imgHeaders);
            unset($imgHeaders);
        }
        
        
        if(empty($config['request_headers']['file_use_page'])){
            
            $headers['file']=empty($config['request_headers']['open'])?array():$headers['page_headers'];
        }elseif($config['request_headers']['file_use_page']=='y'){
            
            $headers['file']=$headers['page_headers'];
        }
        
        $fileHeaders=$this->arrays_to_key_val($config['request_headers']['file_names'], $config['request_headers']['file_vals']);
        if(!empty($fileHeaders)&&is_array($fileHeaders)){
            $headers['file']=\util\Funcs::array_key_merge($headers['file'],$fileHeaders);
            unset($fileHeaders);
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
        
        if(empty($config['request_headers']['file'])){
            
            $headers['file']=null;
        }
        
        if(!is_array($this->config_params)){
            $this->config_params=array();
        }
        
        $this->config_params['headers']=$headers;
        
        
        if(!empty($config['new_front_urls'])){
            foreach ($config['new_front_urls'] as $k=>$v){
                
                $signs[$this->page_source_merge('front_url',$k)]=array(
                    ''=>$this->parent_page_signs('front_url', $k, ''),
                    'pn:'=>$this->parent_page_signs('front_url', $k, 'pn:')
                );
            }
        }
        $signs['source_url']=array(
            ''=>$this->parent_page_signs('source_url', '', ''),
            'pn:'=>$this->parent_page_signs('source_url', '', 'pn:')
        );
        if(!empty($config['new_level_urls'])){
            foreach ($config['new_level_urls'] as $k=>$v){
                
                $signs[$this->page_source_merge('level_url',$k)]=array(
                    ''=>$this->parent_page_signs('level_url', $k, ''),
                    'pn:'=>$this->parent_page_signs('level_url', $k, 'pn:')
                );
            }
        }
        $signs['url']=array(
            ''=>$this->parent_page_signs('url', '', ''),
            'pn:'=>$this->parent_page_signs('url', '', 'pn:')
        );
        if(!empty($config['new_relation_urls'])){
            foreach ($config['new_relation_urls'] as $k=>$v){
                
                $signs[$this->page_source_merge('relation_url',$k)]=array(
                    ''=>$this->parent_page_signs('relation_url', $k, ''),
                    'pn:'=>$this->parent_page_signs('relation_url', $k, 'pn:')
                );
            }
        }
        $this->config_params['signs']=$signs;
    }
    
	/*采集,return false表示终止采集*/
	public function collect($num=10){
	    \util\Param::set_collector_collecting();
	    set_g_sc('collect_task_id',$this->collector['task_id']);
		if(!$this->show_opened_tools){
		    $opened_tools=array();
		    if(g_sc_c('caiji','robots')){
		        $opened_tools[]='遵守robots协议';
		    }
		    if($this->page_render_is_open()){
		        $opened_tools[]='页面渲染';
		    }
		    if(g_sc_c('download_img','download_img')){
		        $opened_tools[]='图片本地化';
		    }
		    if(g_sc_c('download_file','download_file')){
		        $opened_tools[]='文件本地化';
		    }
		    if(g_sc_c('proxy','open')){
		        $opened_tools[]='代理';
		    }
		    if(g_sc_c('translate','open')){
		        $opened_tools[]='翻译';
		    }
		    if(!empty($opened_tools)){
		        $this->echo_msg(array('已开启功能：%s',implode('、', $opened_tools)),'black');
		    }
		    if($num>0){
		        $this->echo_msg(array('预计采集%s条数据',$num),'black');
		    }
		    $this->show_opened_tools=true;
		}
	
		$this->collect_num=$num;
		$this->collected_field_list=array();
		
		$this->collFrontUrls();
		
		$sourceIsUrl=$this->source_is_url();
		if(!isset($this->original_source_urls)){
			
			$this->original_source_urls=array();
			foreach ( $this->config ['source_url'] as $k => $v ) {
				if(empty($v)){
					continue;
				}
				$return_s_urls = $this->source_url_convert( $v );
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
	
		if($sourceIsUrl){
			
			if(isset($this->used_source_urls['_source_is_url_'])){
				$this->echo_msg('所有起始页采集完毕','green');
				return 'completed';
			}
		}else{
			if(count($this->original_source_urls)<=count($this->used_source_urls)){
				$this->echo_msg('所有起始页采集完毕','green');
				return 'completed';
			}
		}
	
		$source_interval=g_sc_c('caiji','interval')*60;
		$time_interval_list=array();
	
		$source_urls=array();
		$mcacheSource=CacheModel::getInstance('source_url');
		if($sourceIsUrl){
			
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
				    $this->echo_msg(array('已采集起始页网址%s条，再次采集需等待%s <a href="%s" target="_blank">设置采集运行间隔</a>',$count_db_used,\skycaiji\admin\model\Config::wait_time_tips($sourceWaitTime),url('admin/task/set?show_config=1&id='.$this->collector['task_id'])),'black');
					if(count($this->original_source_urls)<=count($this->used_source_urls)){
						$this->echo_msg('所有起始页采集完毕','green');
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
		
		if($sourceIsUrl){
			
		    $source_urls=array_values($source_urls);
		    $this->cont_urls_list['_source_is_url_']=$this->page_convert_url_signs('url', '', false, $source_urls, array(), false);
			$source_urls=array('_source_is_url_'=>'_source_is_url_');
		}else{
		    
		    $source_urls=$this->page_convert_url_signs('source_url', '', false, $source_urls, array(), false);
		}
		$pageOpened=$this->page_opened_tips('source_url');
		$pnOpened=$this->page_opened_tips('source_url','',true);
		foreach ($source_urls as $key_source_url=>$source_url){
		    $this->cur_source_url=$source_url;
		    if(array_key_exists($source_url,$this->used_source_urls)){
		        
		        continue;
		    }
		    if($sourceIsUrl){
		        
		        $this->echo_msg('起始页已转换为内容页网址','black');
		        $this->_collect_fields();
		        if($this->collect_num>0&&count($this->collected_field_list)>=$this->collect_num){
		            break;
		        }
		    }else{
		        
		        $this->used_pagination_urls['source_url']=array();
		        $pageIsPn=false;
		        $pageCurUrl=$source_url;
		        $forContinue=false;
		        $forBreak=false;
		        do{
		            $pageCurMd5=md5($pageCurUrl);
		            $pagePnStr='';
		            if($pageIsPn){
		                $pagePnStr='分页';
		                
		                if(isset($this->used_pagination_urls['source_url'][$pageCurMd5])){
		                    
		                    $forContinue=true;
		                    break;
		                }
		            }
		            $this->used_pagination_urls['source_url'][$pageCurMd5]=1;
		            
		            $this->echo_url_msg(array('采集起始页%s',$pagePnStr),$pageCurUrl,$pageIsPn?$pnOpened:$pageOpened,$pageIsPn?'black':'green');
		            if(!empty($this->config['level_urls'])){
		                
		                
		                $this->echo_msg('开始分析多级网址','black');
		                $return_msg=$this->_collect_level($pageCurUrl,1);
		                if($return_msg=='completed'){
		                    return $return_msg;
		                }
		            }else{
		                
		                $cont_urls=$this->getContUrls($pageCurUrl,$pageIsPn);
		                $this->cont_urls_list[$pageCurUrl]=$this->_collect_unused_cont_urls($cont_urls);
		                $this->_collect_fields();
		            }
		            if($this->collect_num>0&&count($this->collected_field_list)>=$this->collect_num){
		                $forBreak=true;
		                break;
		            }
		            
		            $doWhile=false;
		            $nextPnUrl=$this->getPaginationNext('source_url', '', $pageIsPn, $pageCurUrl, '');
		            if(!empty($nextPnUrl)){
		                $pageIsPn=true;
		                $pageCurUrl=$nextPnUrl;
		                $doWhile=true;
		            }
		        }while($doWhile);
		        
		        if($forContinue){
		            continue;
		        }
		        if($forBreak){
		            break;
		        }
		    }
		}
		
		
		
		return $this->collected_field_list;
	}
	
	/*采集前置页*/
	public function collFrontUrls($resetColl=false){
	    if($resetColl){
	        
	        \util\Param::set_gsc_use_cookie('',null);
	        \util\Param::set_gsc_use_cookie('img',null);
	        \util\Param::set_gsc_use_cookie('file',null);
	    }elseif($this->front_collected){
	        
	        return;
	    }
	    $this->front_collected=true;
	    
	    if(!empty($this->config['front_urls'])){
	        foreach ($this->config['front_urls'] as $fuv){
	            if($this->cur_front_urls[$fuv['name']]){
	                
	                $frontUrl=$this->cur_front_urls[$fuv['name']];
	            }else{
	                $frontUrl=$fuv['url'];
	                if($frontUrl){
	                    $parentMatches=$this->parent_page_signs2matches($this->parent_page_signs('front_url',$fuv['name'],'url'));
	                    $frontUrl=$this->merge_match_signs($parentMatches, $frontUrl);
	                    $this->cur_front_urls[$fuv['name']]=$frontUrl;
	                }
	            }
	            if($frontUrl){
	                $pageOpened=$this->page_opened_tips('front_url',$fuv['name']);
	                $this->echo_url_msg(array('采集前置页“%s”',$fuv['name']),$frontUrl,$pageOpened);
	                $htmlInfo=$this->get_page_html($frontUrl,'front_url',$fuv['name'],false,true);
	                if($fuv['use_cookie']||$fuv['use_cookie_img']||$fuv['use_cookie_file']){
	                    
	                    $mUseCookie=\util\Funcs::get_cookies_from_header($htmlInfo['header']);
	                    if($fuv['use_cookie']){
	                        $gUseCookie=\util\Param::get_gsc_use_cookie();
	                        init_array($gUseCookie);
	                        \util\Param::set_gsc_use_cookie('',array_merge($gUseCookie,$mUseCookie));
	                        $this->echo_msg('获取前置页cookie并在全局抓取页面时使用','black');
	                    }
	                    if($fuv['use_cookie_img']){
	                        $gUseCookieImg=\util\Param::get_gsc_use_cookie('img');
	                        init_array($gUseCookieImg);
	                        \util\Param::set_gsc_use_cookie('img',array_merge($gUseCookieImg,$mUseCookie));
	                        $this->echo_msg('获取前置页cookie并在全局下载图片时使用','black');
	                    }
	                    if($fuv['use_cookie_file']){
	                        $gUseCookieFile=\util\Param::get_gsc_use_cookie('file');
	                        init_array($gUseCookieFile);
	                        \util\Param::set_gsc_use_cookie('file',array_merge($gUseCookieFile,$mUseCookie));
	                        $this->echo_msg('获取前置页cookie并在全局下载文件时使用','black');
	                    }
	                }
	            }
	        }
	    }
	}
	
	/*采集级别网址*/
	public function collLevelUrls($sourceUrl,$curLevel,$isPagination){
	    $curLevel=$curLevel>0?$curLevel:0;
	    $levelName='';
	    $nextLevel=0;
		if($curLevel>0){
			
			if(!empty($this->config['level_urls'])){
				
			    $curLevelUrl=$this->get_config('level_urls',$curLevel-1);
			    if(!empty($curLevelUrl)){
				    
			        $levelName=$curLevelUrl['name'];
			        if(!is_empty($this->get_config('level_urls',$curLevel))){
						
						$nextLevel=$curLevel+1;
					}
				}
			}
			$cont_urls=$this->getLevelUrls($sourceUrl,$curLevel,$isPagination);
		}else{
			
		    $cont_urls=$this->getContUrls($sourceUrl,$isPagination);
		}
		return array('urls'=>$cont_urls,'levelName'=>$levelName,'nextLevel'=>$nextLevel);
	}
	
	/*获取内容网址*/
	public function getContUrls($sourceUrl,$isPagination){
		if(empty($sourceUrl)){
			return $this->echo_error('未设置起始网址');
		}
		
		$source_type='';
		$source_name='';
		if(empty($this->config['level_urls'])){
		    
		    $source_type='source_url';
		}else{
		    
		    $source_type='level_url';
		    $lastLevel=$this->getLastLevel();
		    if(!empty($lastLevel['config'])){
		        $source_name=$lastLevel['config']['name'];
		    }
		}
		
		return $this->_get_urls($sourceUrl,$source_type,$source_name,$isPagination,'url','');
	}
	
	/*获取最后的多级页*/
	public function getLastLevel(){
	    $data=array('level'=>0,'config'=>null);
	    if(!empty($this->config['level_urls'])&&is_array($this->config['level_urls'])){
	        $lastNum=count($this->config['level_urls']);
	        $lastLevel=$this->get_config('level_urls',$lastNum-1);
	        $data['level']=$lastNum;
	        $data['config']=$lastLevel;
	    }
	    return $data;
	}
	
	/*获取多级网址*/
	public function getLevelUrls($parentUrl,$level,$isPagination){
		$level=$level>1?$level:1;
		$config=$this->get_config('level_urls',$level-1);
		if(empty($config)){
		    return $this->echo_error('未设置第'.($level).'级网址规则');
		}
		$levelName=$config['name'];
		if(empty($config['reg_url'])){
		    return $this->echo_error('未设置多级页:'.htmlspecialchars($levelName).'»提取网址规则');
		}
		
		$source_type='';
		$source_name='';
		
		
		if($level>1){
		    $source_type='level_url';
		    $source_name=$this->get_config('level_urls',$level-2,'name');
		}else{
		    $source_type='source_url';
		}
		
		
		if(empty($parentUrl)){
		    
		    if($source_type=='level_url'){
		        return $this->echo_error('未设置多级页“'.htmlspecialchars($source_name).'”网址');
		    }else{
		        return $this->echo_error('未设置起始页网址');
		    }
		}
		return $this->_get_urls($parentUrl,$source_type,$source_name,$isPagination,'level_url',$levelName);
	}
	
	/*获取分页链接*/
	public function getPaginationUrls($pageType,$pageName,$isPagination,$fromUrl,$html,$isTest=false){
	    if(!isset($this->pn_area_matches[$pageType])){
	        $this->pn_area_matches[$pageType]=array();
	    }
	    if(!isset($this->pn_area_matches[$pageType][$pageName])){
	        $this->pn_area_matches[$pageType][$pageName]=array();
	    }
	    if(!isset($this->pn_url_matches[$pageType])){
	        $this->pn_url_matches[$pageType]=array();
	    }
	    if(!isset($this->pn_url_matches[$pageType][$pageName])){
	        $this->pn_url_matches[$pageType][$pageName]=array();
	    }
	    $pn_urls=array();
	    $pnConfig=$this->get_page_config($pageType,$pageName,'pagination');
	    if($this->pagination_is_open(null,null,$pnConfig)){
	        
	        if(empty($html)){
	            $html=$this->get_page_html($fromUrl, $pageType, $pageName, $isPagination);
	        }
	        $openPnNum=false;
	        if($pnConfig['number']&&$pnConfig['number']['open']){
	            $openPnNum=true;
	        }
	        if(!empty($pnConfig['reg_url'])||$openPnNum){
	            
	            $allowColl=true;
	            if($pageType=='url'&&empty($pnConfig['new_fields'])){
	                
	                $allowColl=false;
	            }
	            if($allowColl){
	                $url_info=$this->match_url_info($fromUrl,$html);
	                $areaMatch=$this->rule_match_area($pageType, $pageName, true, $html, true);
	                $pn_area=$areaMatch['area'];
	                $this->pn_area_matches[$pageType][$pageName]=$areaMatch['matches'];
	                if(!empty($pn_area)||$openPnNum){
	                    
	                    
	                    if(!empty($pnConfig['url_complete'])){
	                        
	                        $pn_area=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche_p_a) use ($url_info){
	                            
	                            $matche_p_a[2]=\util\Tools::create_complete_url($matche_p_a[2], $url_info);
	                            return $matche_p_a[1].$matche_p_a[2].$matche_p_a[3];
	                        },$pn_area);
	                    }
	                    
	                    $urlsMatches=$this->rule_match_urls($pageType,$pageName,true,$pn_area,false,true);
	                    
	                    $m_pn_urls=$urlsMatches['urls'];
	                    
	                    
	                    foreach ($m_pn_urls as $purl){
	                        if($fromUrl!=$purl){
	                            
	                            $pn_urls[]=$purl;
	                        }
	                    }
	                    
	                    
	                    if(!empty($pn_urls)){
	                        $pn_urls=array_filter($pn_urls);
	                        $pn_urls=array_unique($pn_urls);
	                        $pn_urls=array_values($pn_urls);
	                        
	                        
	                        $this->pn_url_matches[$pageType][$pageName]=$urlsMatches['matches'];
	                    }else{
	                        if($isTest){
	                            return $this->echo_error('未获取到分页链接，请检查“匹配分页网址”或“从选定区域中提取网址”');
	                        }
	                    }
	                }else{
	                    if($isTest){
	                        return $this->echo_error('未获取到分页区域，请检查“从选定区域中提取网址”');
	                    }
	                }
	            }else{
	                if($isTest){
	                    return $this->echo_error('未设置分页字段');
	                }
	            }
	        }else{
	            if($isTest){
	                return $this->echo_error('未设置分页网址规则');
	            }
	        }
	    }else{
	        if($isTest){
	            return $this->echo_error('未开启分页');
	        }
	    }
	    return $pn_urls;
	}
	/*获取下一页分页*/
	public function getPaginationNext($pageType,$pageName,$isPagination,$fromUrl,$html){
	    $nextPnUrl='';
	    $pnConfig=$this->get_page_config($pageType,$pageName,'pagination');
	    if($pnConfig&&is_array($pnConfig)){
	        $pageSource=$this->page_source_merge($pageType,$pageName);
	        $usedPnNum=0;
	        if(is_array($this->used_pagination_urls[$pageSource])){
	            $usedPnNum=count($this->used_pagination_urls[$pageSource]);
	        }
	        if(empty($pnConfig['max'])||($usedPnNum<$pnConfig['max'])){
    	        
	            $pnUrls=$this->getPaginationUrls($pageType,$pageName,$isPagination,$fromUrl,$html);
    	        if(!empty($pnUrls)){
    	            
    	            foreach ($pnUrls as $purl){
    	                if((!$usedPnNum||!isset($this->used_pagination_urls[$pageSource][md5($purl)]))&&$fromUrl!=$purl){
    	                    
    	                    $nextPnUrl=$purl;
    	                    break;
    	                }
    	            }
    	        }
    	    }
    	    
    	    if(!empty($nextPnUrl)){
    	        $this->cur_pagination_urls[$pageSource]=$nextPnUrl;
    	    }
	    }
	    
	    return $nextPnUrl;
	}
	
	/*设置字段值*/
	public function setField($field_config,$cur_url,$htmlInfo,$cont_url){
	    $html=$htmlInfo['html'];
		$cur_url_md5=md5($cur_url);

		$field_process=$field_config['process'];
		$field_params=$field_config['field'];
		$module=strtolower($field_params['module']);
		
		$field_name=$field_params['name'];
		if(!isset($this->field_val_list[$field_name])){
		    
		    $this->field_val_list[$field_name]=array('values'=>array(),'imgs'=>array(),'files'=>array());
		}
		
		if(!empty($field_params['source'])&&in_array($module, array('rule','xpath','json','auto','sign'))){
			
			$field_source_url='';
			$source_echo_msg='——采集';
			
			list($pageType,$pageName)=$this->page_source_split($field_params['source']);
			if($pageType=='source_url'&&$this->source_is_url()){
			    
			    $pageType='url';
			    $pageName='';
			}
			
			if($pageType!='url'){
			    
			    if('front_url'==$pageType){
			        
			        if(is_empty($this->get_page_config('front_url',$pageName))){
			            
			            return;
			        }
			        $field_source_url=$this->cur_front_urls[$pageName];
			        $source_echo_msg.="前置页“{$pageName}”";
			    }elseif('source_url'==$pageType){
			        
			        $field_source_url=$this->cur_source_url;
			        $source_echo_msg.='起始页';
			    }elseif('relation_url'==$pageType){
			        
			        $field_source_url=$this->getRelationUrl($pageName, $cur_url, $html);
			        $source_echo_msg.="关联页“{$pageName}”";
			    }elseif('level_url'==$pageType){
			        
			        if(is_empty($this->get_page_config('level_url',$pageName))){
			            
			            return;
			        }
			        $field_source_url=$this->cur_level_urls[$pageName];
			        $source_echo_msg.="多级页“{$pageName}”";
			    }
			    if(empty($field_source_url)){
			        
			        return;
			    }
			    
			    $pageOpened=$this->page_opened_tips($pageType,$pageName);
		        $cur_url=$field_source_url;
		        
		        $this->echo_url_msg(array('%s',$source_echo_msg),$field_source_url,$pageOpened);
		        $htmlInfo=$this->get_page_html($field_source_url, $pageType, $pageName,false,true);
		        $html=$htmlInfo['html'];
			}
		}
		static $fieldArr=array('words','num','no','time','list');
		
		$url_info=$this->match_url_info($cur_url, $html, 'set_field');
	
		$val='';
		$field_func='field_module_'.$module;
		$is_list_data=false;
		$list_multi_str='';
		if(method_exists($this, $field_func)){
			
			if('extract'==$module){
				
				
				if(is_array($this->field_val_list[$field_params['extract']]['values'][$cur_url_md5])){
					
					$val=array();
					foreach ($this->field_val_list[$field_params['extract']]['values'][$cur_url_md5] as $k=>$v){
						$extract_field_val=array(
							'value'=>$v,
						    'img'=>$this->field_val_list[$field_params['extract']]['imgs'][$cur_url_md5][$k],
						    'file'=>$this->field_val_list[$field_params['extract']]['files'][$cur_url_md5][$k],
						);
						$val[$k]=$this->field_module_extract($field_params, $extract_field_val, $url_info);
					}
				}else{
					
					$extract_field_val=array(
						'value'=>$this->field_val_list[$field_params['extract']]['values'][$cur_url_md5],
					    'img'=>$this->field_val_list[$field_params['extract']]['imgs'][$cur_url_md5],
					    'file'=>$this->field_val_list[$field_params['extract']]['files'][$cur_url_md5],
					);
					$val=$this->field_module_extract($field_params, $extract_field_val, $url_info);
				}
				
				if($field_params['extract_module']=='rule'){
				    if($field_params['extract_rule_multi']&&$field_params['extract_rule_multi_type']=='list'){
				        $is_list_data=true;
				        $list_multi_str=$field_params['extract_rule_multi_str'];
				    }
				}elseif($field_params['extract_module']=='xpath'){
				    if($field_params['extract_xpath_multi']&&$field_params['extract_xpath_multi_type']=='list'){
				        $is_list_data=true;
				        $list_multi_str=$field_params['extract_xpath_multi_str'];
				    }
				}
			}elseif('merge'==$module){
				
				if(empty($this->first_loop_field)){
					
					$cur_field_val_list=array();
					foreach ($this->field_val_list as $k=>$v){
						$cur_field_val_list[$k]=array(
							'value'=>$v['values'][$cur_url_md5],
						    'img'=>$v['imgs'][$cur_url_md5],
						    'file'=>$v['files'][$cur_url_md5]
						);
					}
					$val=$this->field_module_merge($field_params,$cur_field_val_list);
				}else{
					
					$val=array();
					
					if(is_array($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5])){
    					foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
    						$cur_field_val_list=array();
    						foreach ($this->field_val_list as $k=>$v){
    							$cur_field_val_list[$k]=array(
    								'value'=>(is_array($v['values'][$cur_url_md5])?$v['values'][$cur_url_md5][$v_k]:$v['values'][$cur_url_md5]),
    							    'img'=>((is_array($v['imgs'][$cur_url_md5])&&is_array($v['imgs'][$cur_url_md5][$v_k]))?$v['imgs'][$cur_url_md5][$v_k]:$v['imgs'][$cur_url_md5]),
    							    'file'=>((is_array($v['files'][$cur_url_md5])&&is_array($v['files'][$cur_url_md5][$v_k]))?$v['files'][$cur_url_md5][$v_k]:$v['files'][$cur_url_md5])
    							);
    						}
    						$val[$v_k]=$this->field_module_merge($field_params,$cur_field_val_list);
    					}
					}
				}
			}elseif(in_array($module,$fieldArr)){
				
				if(empty($this->first_loop_field)){
					
					$val=$this->$field_func($field_params);
				}else{
					
					$val=array();
					
					if(is_array($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5])){
					    foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
					        $val[$v_k]=$this->$field_func($field_params);
					    }
					}
				}
			}elseif($module=='json'){
			    $val=$this->$field_func($field_params,$html,$cur_url);
			}elseif($module=='auto'){
			    $val=$this->$field_func($field_params,$htmlInfo,$cur_url);
			}elseif($module=='sign'){
			    
			    $val=$this->$field_func($field_params,empty($cont_url)?$cur_url:$cont_url);
			}else{
				$val=$this->$field_func($field_params,$html);
				
				if($module=='rule'){
				    if($field_params['rule_multi']&&$field_params['rule_multi_type']=='list'){
				        $is_list_data=true;
				        $list_multi_str=$field_params['rule_multi_str'];
				    }
				}elseif($module=='xpath'){
				    if($field_params['xpath_multi']&&$field_params['xpath_multi_type']=='list'){
				        $is_list_data=true;
				        $list_multi_str=$field_params['xpath_multi_str'];
				    }
				}
			}
		}
		
		if(!empty($list_multi_str)){
		    $list_multi_str=str_replace(array('\r','\n'), array("\r","\n"), $list_multi_str);
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
		    $val=isset($val)?$val:'';
			$loopIndex=$is_loop?$v_k:-1;
			$this->field_url_complete=true;
			$this->field_down_img=true;
			$this->field_stop_process=false;
			
			if($is_loop){
			    
			    if(!isset($this->field_val_list[$field_name]['values'][$cur_url_md5])){
			        $this->field_val_list[$field_name]['values'][$cur_url_md5]=array();
			        $this->field_val_list[$field_name]['imgs'][$cur_url_md5]=array();
			        $this->field_val_list[$field_name]['files'][$cur_url_md5]=array();
			    }
			}
			
			if($is_list_data){
			    
			    $val=$val?json_decode($val,true):array();
			    init_array($val);
			    foreach ($val as $v_vk=>$v_vv){
			        if(!empty($field_process)){
			            
			            $v_vv=$this->process_field($field_name,$v_vv,$field_process,$cur_url_md5,$loopIndex,$cont_url_md5);
			        }
			        if(!empty($this->config['common_process'])){
			            
			            $v_vv=$this->process_field($field_name,$v_vv,$this->config['common_process'],$cur_url_md5,$loopIndex,$cont_url_md5);
			        }
			        $val[$v_vk]=$v_vv;
			    }
			    $val=implode($list_multi_str, $val);
			}else{
			    if(!empty($field_process)){
			        
			        $val=$this->process_field($field_name,$val,$field_process,$cur_url_md5,$loopIndex,$cont_url_md5);
			    }
			    if(!empty($this->config['common_process'])){
			        
			        $val=$this->process_field($field_name,$val,$this->config['common_process'],$cur_url_md5,$loopIndex,$cont_url_md5);
			    }
			}
			
			if(isset($this->exclude_cont_urls[$cont_url_md5][$cur_url_md5])){
				
				if(empty($this->first_loop_field)){
					
					foreach ($this->field_val_list as $f_k=>$f_v){
						
						unset($this->field_val_list[$f_k]['values'][$cur_url_md5]);
						unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5]);
						unset($this->field_val_list[$f_k]['files'][$cur_url_md5]);
					}
					return;
				}else{
					
					if(isset($this->exclude_cont_urls[$cont_url_md5][$cur_url_md5][$loopIndex])){
						
						if(!$is_loop){
							
							foreach ($this->field_val_list as $f_k=>$f_v){
								
								unset($this->field_val_list[$f_k]['values'][$cur_url_md5]);
								unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5]);
								unset($this->field_val_list[$f_k]['files'][$cur_url_md5]);
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
								if(is_array($this->field_val_list[$f_k]['files'][$cur_url_md5])){
								    
								    unset($this->field_val_list[$f_k]['files'][$cur_url_md5][$v_k]);
								}
							}
							continue;
						}
					}
				}
			}
	
			if($this->field_url_complete){
    			
    			$val=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
    				
    			    $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
    			    return $matche[1].$matche[2].$matche[3];
    			},$val);
    			$val=preg_replace_callback('/(\bsrc\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
    			    $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
    				return $matche[1].$matche[2].$matche[3];
    			},$val);
			}
					
			if($is_loop){
				
				if(!isset($this->field_val_list[$field_name]['values'][$cur_url_md5])){
					$this->field_val_list[$field_name]['values'][$cur_url_md5]=array();
					$this->field_val_list[$field_name]['imgs'][$cur_url_md5]=array();
					$this->field_val_list[$field_name]['files'][$cur_url_md5]=array();
				}
				$this->field_val_list[$field_name]['values'][$cur_url_md5][$v_k]=$val;
			}else{
				
				$this->field_val_list[$field_name]['values'][$cur_url_md5]=$val;
			}
			if(!is_empty(g_sc_c('download_img','download_img'))&&!empty($val)&&$this->field_down_img){
				
				$valImgs=array();
				if(preg_match_all('/<img\b[^<>]*\bsrc\s*=\s*[\'\"](\w+\:[^\'\"]+?)[\'\"]/i',$val,$imgUrls)){
					
					$valImgs=is_array($imgUrls[1])?$imgUrls[1]:array();
				}
				if('extract'==$module&&'cover'==$field_params['extract_module']){
					
					$valImgs=array_merge($valImgs,array($val));
				}
				if(!empty($valImgs)){
				    $fieldImgs=array();
					if($is_loop){
						
					    $fieldImgs=$this->field_val_list[$field_name]['imgs'][$cur_url_md5][$v_k];
					}else{
					    
					    $fieldImgs=$this->field_val_list[$field_name]['imgs'][$cur_url_md5];
					}
					init_array($fieldImgs);
					$fieldImgs=array_merge($fieldImgs,$valImgs);
					$fieldImgs=array_unique($fieldImgs);
					$fieldImgs=array_values($fieldImgs);
					if($is_loop){
					    $this->field_val_list[$field_name]['imgs'][$cur_url_md5][$v_k]=$fieldImgs;
					}else{
					    $this->field_val_list[$field_name]['imgs'][$cur_url_md5]=$fieldImgs;
					}
				}
			}
		}
	}
	/*设置分页的字段列表值*/
	public function setPaginationFields($cont_url,$page_url){
		$contMd5=md5($cont_url);
		$pageMd5=md5($page_url);

		if(empty($page_url)){
		    return $this->echo_error('请输入分页网址');
		}
		if(!preg_match('/^\w+\:\/\//',$page_url)){
		    return $this->echo_error('分页网址不完整：'.htmlspecialchars($page_url));
		}
		
		$pnConfig=$this->config['pagination'];
		
		if(empty($pnConfig['max'])||(count((array)$this->used_pagination_urls['url'])<$pnConfig['max'])){
			
		    $this->collect_sleep(g_sc_c('caiji','interval_html'),true,true);
		    $pageOpened=$this->page_opened_tips('url','',true);
		    $this->echo_url_msg(array('——采集分页'),$page_url,$pageOpened);
			$htmlInfo=$this->get_page_html($page_url,'url','',true,true);
			if(empty($htmlInfo['html'])){
			    return $this->echo_error('未获取到分页源代码');
			}
			
			if(!isset($this->used_pagination_urls['url'][$pageMd5])){
				
			    $this->used_pagination_urls['url'][$pageMd5]=1;
				
				if(!empty($pnConfig['new_fields'])){
				    foreach ($pnConfig['new_fields'] as $v){
				        $this->setField($this->get_config('new_field_list',$v['field']),$page_url,$htmlInfo,$cont_url);
				    }
				}
			}
			
			
			$this->collect_stopped($this->collector['task_id']);
			
			$nextPnUrl=$this->getPaginationNext('url','',true,$page_url,$htmlInfo['html']);
			if(!empty($nextPnUrl)){
			    $this->setPaginationFields($cont_url,$nextPnUrl);
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
		    $html=$this->get_page_html($cont_url, 'url', '');
		}
		if(empty($html)){
			
			return '';
		}
		$relation_url=$this->get_page_config('relation_url',$name);
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
			    
			    $areaMatch=$this->rule_match_area('relation_url',$name,false,$html,true);
				$html=$areaMatch['area'];
				$this->page_area_matches['relation_url'][$name]=$areaMatch['matches'];
				if(empty($html)){
					
					return '';
				}
				$relationUrlsMatches=$this->rule_match_urls('relation_url',$name,false,$html,false,true);
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
				if(is_empty($this->get_page_config('relation_url',$page))){
					
					$pass=true;
					break;
				}
				$depth++;
				$depth_pages[$depth]=$page;
				$page=$this->get_page_config('relation_url',$page,'page');
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
				    $areaMatch=$this->rule_match_area('relation_url',$contPage,false,$html,true);
					$html=$areaMatch['area'];
					$this->page_area_matches['relation_url'][$contPage]=$areaMatch['matches'];
					if(empty($html)){
						
						return '';
					}
					$relationUrlsMatches=$this->rule_match_urls('relation_url',$contPage,false,$html,false,true);
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
					
				    $relationHtml=$this->get_page_html($relationUrl, 'relation_url', $pageName);
				    $pageName=$page;
				    $areaMatch=$this->rule_match_area('relation_url',$page,false,$relationHtml,true);
				    $relationHtml=$areaMatch['area'];
					$this->page_area_matches['relation_url'][$page]=$areaMatch['matches'];
					if(empty($relationHtml)){
						
						return '';
					}
					$relationUrlsMatches=$this->rule_match_urls('relation_url',$page,false,$relationHtml,false,true);
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
		    return $this->echo_error('请输入内容页网址');
		}
		if(!preg_match('/^\w+\:\/\//',$cont_url)){
		    return $this->echo_error(htmlspecialchars($cont_url).'网址不完整');
		}
		if(empty($this->config['new_field_list'])){
		    return $this->echo_error('未设置字段');
		}
		$htmlInfo=$this->get_page_html($cont_url, 'url', '', false, true);
		if(empty($htmlInfo['html'])){
		    $errorMsg='';
		    if(is_array($htmlInfo['error'])){
		        $errorMsg=$htmlInfo['error']['msg'];
		    }
		    if($htmlInfo['header']){
		        $errorMsg.=($errorMsg?', ':'').$htmlInfo['header'];
		    }
		    $errorMsg=($errorMsg?'：':'').$errorMsg;
		    return $this->echo_error('未抓取到源码'.$errorMsg);
		}
		
		foreach($this->config['new_field_list'] as $field_config){
			$this->setField($field_config,$cont_url,$htmlInfo,$cont_url);
		}
		
		$this->used_pagination_urls['url']=array();
		$nextPnUrl=$this->getPaginationNext('url','',false,$cont_url,$htmlInfo['html']);
		if(!empty($nextPnUrl)){
			
		    $this->setPaginationFields($cont_url,$nextPnUrl);
		}
		
		$val_list=array();
		if(!empty($this->field_val_list)){
			if(empty($this->first_loop_field)){
				
				foreach ($this->field_val_list as $fieldName=>$fieldVal){
				    $val_values='';
				    if(!empty($fieldVal['values'])){
				        $val_values=\util\Funcs::array_filter_keep0($fieldVal['values']);
				        $valDelimiter='';
				        $pnField=$this->get_config('pagination','new_fields',$fieldName);
				        if(is_array($pnField)){
				            $valDelimiter=$pnField['delimiter'];
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
					
					$val_files=array();
					if(!empty($fieldVal['files'])){
					    foreach ($fieldVal['files'] as $v){
					        if(!empty($v)){
					            if(is_array($v)){
					                $val_files=array_merge($val_files,$v);
					            }else{
					                $val_files[]=$v;
					            }
					        }
					    }
					    if(!empty($val_files)){
					        $val_files=array_unique($val_files);
					        $val_files=array_filter($val_files);
					        $val_files=array_values($val_files);
					    }
					}
					
					$val_list[$fieldName]=array('name'=>$fieldName,'value'=>$val_values,'img'=>$val_imgs,'file'=>$val_files);
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
								$val_files=$fieldVals['files'][$page_key][$loop_index];
							}else{
								
								$val_values=$fieldVals['values'][$page_key];
								$val_imgs=$fieldVals['imgs'][$page_key];
								$val_files=$fieldVals['files'][$page_key];
							}
							if(!empty($val_imgs)){
								$val_imgs=array_unique($val_imgs);
								$val_imgs=array_filter($val_imgs);
								$val_imgs=array_values($val_imgs);
							}
							if(!empty($val_files)){
							    $val_files=array_unique($val_files);
							    $val_files=array_filter($val_files);
							    $val_files=array_values($val_files);
							}
							$vals[$fieldName]=array('name'=>$fieldName,'value'=>$val_values,'img'=>$val_imgs,'file'=>$val_files);
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
		    $processList=$this->set_process($processList);
			foreach ($processList as $k=>$v){
				if('replace'==$v['module']){
				    $v['replace_from']=$this->correct_reg_pattern($v['replace_from']);
				}elseif('download'==$v['module']){
				    $v['download_file_must']=$this->correct_reg_pattern($v['download_file_must']);
				    $v['download_file_ban']=$this->correct_reg_pattern($v['download_file_ban']);
				}
				$processList[$k]=$v;
			}
		}
		return $processList;
	}
	
	
	/*统一：获取网址列表*/
	public function _get_urls($sourceUrl,$sourceType,$sourceName,$sourceIsPagination,$pageType,$pageName){
	    $pageTips='';
	    if($pageType=='level_url'){
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
	    
	    
	    $html=$this->get_page_html($sourceUrl, $sourceType, $sourceName, $sourceIsPagination);
		if(empty($html)){
		    $sourceTips='';
		    if($sourceType=='source_url'){
		        $sourceTips='起始页';
		    }elseif($sourceType=='level_url'){
		        $sourceTips='多级页“'.$sourceName.'”';
		    }
		    return $this->echo_error('未获取到'.htmlspecialchars($sourceTips).($sourceIsPagination?'分页':'').'源代码');
		}
		
		$url_info=$this->match_url_info($sourceUrl,$html);
		
		
		$areaMatch=$this->rule_match_area($pageType,$pageName,false,$html,true);
		$html=$areaMatch['area'];
		$this->page_area_matches[$pageType][$pageName]=$areaMatch['matches'];
		if(empty($html)){
		    return $this->echo_error('未获取到'.htmlspecialchars($pageTips).'区域');
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
	
		$contUrlsMatches=$this->rule_match_urls($pageType,$pageName,false,$html,$op_not_complete?false:$url_info,true);
		
		$cont_urls=$contUrlsMatches['urls'];
	
		if(empty($cont_urls)){
		    return $this->echo_error("未获取到".htmlspecialchars($pageTips)."网址");
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
			if(!empty($this->config['url_order'])){
				
			    if('shuffle'==$this->config['url_order']){
			        
			        shuffle($cont_urls);
				}elseif('reverse'==$this->config['url_order']){
			        
			        $cont_urls=array_reverse($cont_urls);
			    }
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
			
		    $db_cont_urls=$mcollected->collGetUrlByUrl($cont_urls);
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
			$this->echo_msg(array('%s采集到%s条网址，<span style="color:orange">%s</span>条重复，<span style="color:green">%s</span>条有效',$echo_str,$count_conts,$count_used,count($unused_cont_urls)),'black');
		}else{
		    $this->echo_msg(array('%s采集到<span style="color:green">%s</span>条有效网址',$echo_str,$count_conts),'black');
		}
		return $unused_cont_urls;
	}
	/*执行级别采集*/
	public function _collect_level($sourceUrl,$level=1){
	    $end_echo='</section>';
	
		$level=max(1,$level);
		$level_str='';
		
		$next_level_str=$level_str;
		if($level<=1){
		    
		    $this->cur_level_urls=array();
		}
		$this->echo_msg('','',true,'<section style="padding-left:20px;">');
	
		
		$level_name=$level.'级“'.$this->get_config('level_urls',$level-1,'name').'”';
		
		$level_data=$this->collLevelUrls($sourceUrl,$level,false);
		$this->echo_msg(array('%s采集到%s网址%s条',$level_str,$level_name,count((array)$level_data['urls'])),'black');
	
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
				    $this->echo_msg(array('已采集%s网址%s条，再次采集需等待%s <a href="%s" target="_blank">设置采集运行间隔</a>',$level_str.$level_name,$count_db_used,\skycaiji\admin\model\Config::wait_time_tips($levelWaitTime),url('admin/task/set?show_config=1&id='.$this->collector['task_id'])),'black');
					if(count($level_urls)<=$count_db_used){
					    $this->echo_msg(array('%s网址采集完毕！',$level_str.$level_name),'green',true,$end_echo);
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
			        
			        $this->echo_msg(array('%s已采集第%s级：%s',$level_str,$level,$level_url),'orange');
			        continue;
			    }
			    $levelConfig=$this->get_config('level_urls',$level-1);
			    $levelSource=$this->page_source_merge('level_url', $levelConfig['name']);
			    $this->cur_level_urls[$levelConfig['name']]=$level_url;
			    $this->used_pagination_urls[$levelSource]=array();
			    $pageIsPn=false;
			    $pageCurUrl=$level_url;
				$forContinue=false;
				$forBreak=false;
				do{
				    $pageCurKey=$level_key;
				    $pageCurMd5=md5($pageCurUrl);
				    $pagePnStr='';
				    if($pageIsPn){
				        $pageCurKey="level_{$level}:{$pageCurUrl}";
				        $pagePnStr='分页';
				        
				        if(isset($this->used_pagination_urls[$levelSource][$pageCurMd5])){
				            
				            $forContinue=true;
				            break;
				        }
				    }
				    $this->used_pagination_urls[$levelSource][$pageCurMd5]=1;
				    $pageOpened=$this->page_opened_tips('level_url',$levelConfig['name'],$pageIsPn);
				    $this->echo_url_msg(array('%s分析第%s级%s',$next_level_str,$level,$pagePnStr),$pageCurUrl,$pageOpened);
				    if($level_data['nextLevel']>0){
				        
				        $return_msg=$this->_collect_level($pageCurUrl,$level_data['nextLevel']);
				        if($return_msg=='completed'){
				            $this->echo_msg('','',true,$end_echo);
				            return $return_msg;
				        }
				    }else{
				        
				        $cont_urls=$this->getContUrls($pageCurUrl,$pageIsPn);
				        $cont_urls=$this->_collect_unused_cont_urls($cont_urls,$next_level_str);
				        $this->cont_urls_list[$pageCurKey]=$cont_urls;
				        $this->_collect_fields($next_level_str);
				    }
				    if($this->collect_num>0){
				        
				        if(count($this->collected_field_list)>=$this->collect_num){
				            
				            if($cur_level_i<count((array)$level_data['urls'])){
				                
				                $finished_source=false;
				            }
				            $forBreak=true;
				            break;
				        }
				    }
				    
				    $doWhile=false;
				    $nextPnUrl=$this->getPaginationNext('level_url', $levelConfig['name'], $pageIsPn, $pageCurUrl, '');
				    if(!empty($nextPnUrl)){
				        $pageIsPn=true;
				        $pageCurUrl=$nextPnUrl;
				        $doWhile=true;
				    }
				}while($doWhile);
				
				if($forContinue){
				    continue;
				}
				if($forBreak){
				    break;
				}
			}
		}
		if($finished_source){
			
			$source_key='level_'.($level-1).':'.$sourceUrl;
			$this->used_level_urls[$source_key]=1;
			$mcacheLevel->setCache(md5($source_key),$source_key);
			if($level<=1){
				
				$mcacheSource=CacheModel::getInstance('source_url');
				$this->used_source_urls[$sourceUrl]=1;
				$mcacheSource->setCache(md5($sourceUrl),$sourceUrl);
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
		$pageOpened=$this->page_opened_tips('url');
		
		$url_repeat=$this->config['url_repeat']?true:false;
		
		
		foreach ($this->cont_urls_list as $cont_key=>$cont_urls){
			$source_type=0;
			if('_source_is_url_'==$cont_key){
				$source_type=0;
			}elseif(strpos($cont_key,'level_')===0){
				$source_type=2;
			}else{
				$source_type=1;
			}
			
			if($cont_key){
    			if($source_type==2){
    				if(array_key_exists($cont_key,$this->used_level_urls)){
    					
    					continue;
    				}
    			}else{
    				if(array_key_exists($cont_key,$this->used_source_urls)){
    					
    					continue;
    				}
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
				if($url_repeat||$mcollected->collGetNumByUrl($cont_url)<=0){
				    
				    if(!empty($this->collected_field_list)){
						
					    $millisecond=g_sc_c('caiji','interval_html');
					    if($millisecond>0){
					        $this->collect_sleep($millisecond,true,true);
							
					        if(!$url_repeat&&$mcollected->collGetNumByUrl($cont_url)>0){
							    $this->echo_msg(array('已采集过网址：<a href="%s" target="_blank">%s</a>',$cont_url,$cont_url),'black');
								$this->used_cont_urls[$md5_cont_url]=1;
								continue;
							}
						}
					}
					if($mcacheCont->getCount($md5_cont_url)>0){
						
					    $this->used_cont_urls[$md5_cont_url]=1;
					    $this->echo_msg(array('其他任务正在采集网址：<a href="%s" target="_blank">%s</a>',$cont_url,$cont_url),'black');
						continue;
					}
					$mcacheCont->setCache($md5_cont_url, 1);
					
					$this->echo_url_msg(array('%s采集内容页',$echo_str),$cont_url,$pageOpened);
					$field_vals_list=$this->getFields($cont_url);
					
					
					$this->collect_stopped($this->collector['task_id']);
					
					$this->_collect_fields_vals($echo_str, $cont_url, $md5_cont_url, $field_vals_list, $url_repeat);
				}else{
					
				    $this->echo_msg(array('已采集过网址：<a href="%s" target="_blank">%s</a>',$cont_url,$cont_url),'black');
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
				
			    if($cont_key){
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
			}
			
			if($this->collect_num>0&&count($this->collected_field_list)>=$this->collect_num){
				break;
			}
		}
	}
	public function _collect_fields_vals($echo_str,$cont_url,$md5_cont_url,&$field_vals_list,$url_repeat){
	    $is_loop=empty($this->first_loop_field)?false:true;
	    $loopExcludeNum=0;
	    if($is_loop){
	        
	        if(isset($this->exclude_cont_urls[$md5_cont_url])){
	            
	            $loopExcludeNum=0;
	            foreach($this->exclude_cont_urls[$md5_cont_url] as $k=>$v){
	                
	                $loopExcludeNum+=count((array)$v);
	            }
	            $this->echo_msg(array('%s通过数据处理筛除了%s条数据',$echo_str,$loopExcludeNum),'black');
	        }
	    }
	    $mcollected=model('Collected');
	    if(!empty($field_vals_list)){
	        if(!$is_loop){
	            
	            $field_vals_list=array($field_vals_list);
	        }else{
	            
	            
	            $loop_cont_urls=array();
	            foreach ($field_vals_list as $k=>$field_vals){
	                $loop_cont_urls[$k]=$cont_url.'#'.md5(serialize($field_vals));
	            }
	            if(!empty($loop_cont_urls)){
	                $loop_exists_urls=$mcollected->collGetUrlByUrl($loop_cont_urls);
	                if(!empty($loop_exists_urls)){
	                    
	                    $loop_exists_urls=array_flip($loop_exists_urls);
	                    foreach ($loop_cont_urls as $k=>$loop_cont_url){
	                        if(isset($loop_exists_urls[$loop_cont_url])){
	                            
	                            unset($field_vals_list[$k]);
	                        }
	                    }
	                    $this->echo_msg(array('%s已过滤%s条重复数据',$echo_str,count((array)$loop_exists_urls)),'black');
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
	                    if(!empty($collected_data['title'])){
	                        
	                        if($mcollected->collGetNumByTitle($collected_data['title'])>0){
	                            
	                            $collected_error='标题重复：'.mb_substr($collected_data['title'],0,300,'utf-8');
	                        }
	                    }
	                }
	            }
	            if(empty($collected_error)){
	                if(!empty($this->config['field_content'])){
	                    
	                    $collected_data['content']=array();
	                    foreach($this->config['field_content'] as $fcField){
	                        $collected_data['content'][$fcField]=$field_vals[$fcField]['value'];
	                    }
	                    if(!empty($collected_data['content'])){
	                        
	                        ksort($collected_data['content']);
	                        $collected_data['content']=implode("\r\n", $collected_data['content']);
	                        if($mcollected->collGetNumByContent($collected_data['content'])>0){
	                            
	                            $collected_error='内容重复';
	                        }
	                    }else{
	                        $collected_data['content']='';
	                    }
	                }
	            }
	            if(empty($collected_error)){
	                
	                if(!is_empty(g_sc_c('caiji','real_time'))){
	                    
	                    
	                    $rtRele=g_sc('real_time_release');
	                    if($rtRele){
	                        $rtRele->doExport(array($collected_data));
	                        unset($collected_data['fields']);
	                        unset($collected_data['title']);
	                    }
	                }
	                
	                $this->collected_field_list[]=$collected_data;
	            }else{
	                
	                if(!$url_repeat){
	                    
	                    controller('ReleaseBase','event')->record_collected(
	                        $collected_data['url'],
	                        array('id'=>0,'error'=>$collected_error),array('task_id'=>$this->collector['task_id'],'module'=>$this->release['module'])
	                    );
	                }else{
	                    
	                    $this->echo_msg(array('%s',$collected_error));
	                }
	            }
	        }
	    }
	    
	    if($is_loop){
	        
	        
	        controller('ReleaseBase','event')->record_collected(
	            $cont_url,array('id'=>1,'target'=>'','desc'=>'循环入库'.($loopExcludeNum>0?('，数据处理筛除了'.$loopExcludeNum.'条数据'):'')),array('task_id'=>$this->collector['task_id'],'module'=>$this->release['module']),null,false
	        );
	    }
	}
}
?>