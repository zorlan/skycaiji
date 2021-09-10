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

namespace skycaiji\admin\controller;

/*采集器：规则采集*/
class Cpattern extends BaseController {
	/**
	 * 起始页网址
	 */
    public function sourceAction(){
    	if(request()->isPost()&&input('is_submit')){
    	    $source=input('source/a',array(),'trim');
    		if($source['type']=='custom'){
    			
    			if(preg_match_all('/^\w+\:\/\/[^\r\n]+/im',$source['urls'],$urls)){
    				$urls=array_unique($urls[0]);
    			}else{
    				$this->error('请输入正确的网址');
    			}
    		}elseif($source['type']=='batch'){
    			if(!preg_match('/^\w+\:\/\/[^\r\n]+$/i',$source['url'])){
    				$this->error('请输入正确的网址格式');
    			}
    			
	    		if(stripos($source['url'],cp_sign('match'))===false){
	    			$this->error('请在网址格式中添加 '.cp_sign('match').' 才能批量生成网址！');
	    		}
	    		if(empty($source['param'])){
	    			$this->error('请选择参数类型');
	    		}
	    		$urls=array();
	    		$urlFmt=$source['url'];
	    		if($source['param']=='num'){
	    			
	    			$source['param_num_start']=intval($source ['param_num_start']);
	    			$source['param_num_end']=intval($source ['param_num_end']);
	    			$source['param_num_end'] = max ( $source ['param_num_start'], $source ['param_num_end'] );
	    			$source['param_num_inc'] = max ( 1, intval($source ['param_num_inc']));
	    			$source['param_num_desc']=$source['param_num_desc']?1:0;
	    			
	    			if($source['param_num_desc']){
	    				
	    				for($i=$source['param_num_end'];$i>=$source['param_num_start'];$i--){
	    					$urls[]=str_replace(cp_sign('match'), $source['param_num_start']+($i-$source['param_num_start'])*$source['param_num_inc'], $source['url']);
	    				}
	    			}else{
		    			for($i=$source['param_num_start'];$i<=$source['param_num_end'];$i++){
		    				$urls[]=str_replace(cp_sign('match'), $source['param_num_start']+($i-$source['param_num_start'])*$source['param_num_inc'], $source['url']);
		    			}
	    			}
	    			$urlFmt=str_replace(cp_sign('match'),"{param:num,{$source['param_num_start']}\t{$source['param_num_end']}\t{$source['param_num_inc']}\t{$source['param_num_desc']}}",$urlFmt);
	    		}elseif($source['param']=='letter'){
	    			
	    			$letter_start=ord($source['param_letter_start']);
	    			$letter_end=ord($source['param_letter_end']);
	    			$letter_end=max($letter_start,$letter_end);
	    			$source['param_letter_desc']=$source['param_letter_desc']?1:0;
	    			
	    			if($source['param_letter_desc']){
	    				
		    			for($i=$letter_end;$i>=$letter_start;$i--) {
		    				$urls[]=str_replace(cp_sign('match'), chr($i), $source['url']);
						}
	    			}else{
		    			for($i=$letter_start;$i<=$letter_end;$i++) {
		    				$urls[]=str_replace(cp_sign('match'), chr($i), $source['url']);
						}
	    			}
	    			$urlFmt=str_replace(cp_sign('match'),"{param:letter,{$source['param_letter_start']}\t{$source['param_letter_end']}\t{$source['param_letter_desc']}}",$urlFmt);
	    		}elseif($source['param']=='custom'){
	    			
	    			if(preg_match_all('/[^\r\n]+/', $source['param_custom'],$cusParams)){
	    				$cusParams=array_unique($cusParams[0]);
	    				foreach ($cusParams as $cusParam){
	    					$urls[]=str_replace(cp_sign('match'), $cusParam, $source['url']);
	    				}
	    				$urlFmt=str_replace(cp_sign('match'),"{param:custom,".implode("\t", $cusParams)."}",$urlFmt);
	    			}
	    		}
    		}elseif($source['type']=='large'){
    			
    			if(preg_match_all('/^\w+\:\/\/[^\r\n]+/im',$source['large_urls'],$urls)){
    				$urls=array_unique($urls[0]);
    			}else{
    				$this->error('请输入正确的网址');
    			}
    		}elseif($source['type']=='api'){
    			
    			if(!preg_match('/^\w+\:\/\//i',$source['api'])){
    				$this->error('请输入正确的api网址');
    			}
    			$urlFmt=$source['api'].'{json:'.$source['api_json'].'}';
    		}
    		
    		if($urls||$urlFmt){
    			$urls=$urls?array_values($urls):'';
    			$this->success('',null,array('uid'=>$source['uid'],'url'=>$urlFmt,'urls'=>$urls));
    		}else{
    			$this->error('未生成网址！');
    		}
    	}else{
    		
    		$url=input('url','','trim');
    		if($url){
    			$source['uid']=input('uid','');
    			
    			if(preg_match('/\{param\:(\w+)\,([^\}]*)\}/i',$url,$param)){
    				
    				$source['url']= preg_replace('/\{param\:(\w+)\,([^\}]*)\}/i', cp_sign('match'), $url);
    				$source['type']='batch';
    				$source['param']=strtolower($param[1]);
    				$param_val=explode("\t", $param[2]);
    				if($source['param']=='num'){
    					$source['param_num_start']=intval($param_val[0]);
    					$source['param_num_end']=intval($param_val[1]);
    					$source['param_num_inc']=intval($param_val[2]);
    					$source['param_num_desc']=intval($param_val[3]);
    				}elseif($source['param']=='letter'){
    					$source['param_letter_start']=strtolower($param_val[0]);
    					$source['param_letter_end']=strtolower($param_val[1]);
    					$source['param_letter_desc']=intval($param_val[2]);
    				}elseif($source['param']=='custom'){
    					
    					$source['param_custom']=implode("\r\n", $param_val);
    				}
    			}elseif(preg_match('/\{json\:([^\}]*)\}/i',$url,$json)){
    				
    				$source['type']='api';
    				$source['api']=preg_replace('/\{json\:([^\}]*)\}/i','',$url);
    				$source['api_json']=$json[1];
    			}elseif(preg_match('/[\r\n]/', $url)){
    				
    				$source['type']='large';
    				$source['large_urls']=$url;
    			}else{
    				
    				$source['type']='custom';
    				$source['urls']=$url;
    			}
    			$this->assign('source',$source);
    		}
    		return $this->fetch();
    	}
    }
    /**
     * 字段
     */
    public function fieldAction(){
    	if(request()->isPost()&&input('is_submit')){
    		$objid=input('post.objid');
    		$field=input('post.field/a',array(),'trim');
    		if(empty($field['name'])){
    			$this->error('请输入字段名称');
    		}
    		$this->_check_name($field['name'],'字段名称');
    		
    		$field['module']=strtolower($field['module']);
    		
    		switch ($field['module']){
    			case 'rule':if(empty($field['rule']))$this->error('规则不能为空！');break;
    			case 'auto':if(empty($field['auto']))$this->error('请选择自动获取的类型');break;
    			case 'xpath':if(empty($field['xpath']))$this->error('XPath规则不能为空！');break;
    			case 'json':if(empty($field['json']))$this->error('提取规则不能为空！');break;
    			case 'num':
    				$randNum=0;
    				$field['num_start']=intval($field['num_start']);
    				$field['num_end']=intval($field['num_end']);
    				$field['num_end'] = max ( $field['num_start'], $field ['num_end'] );
    				break;
    			
    			case 'list':if(empty($field['list']))$this->error('随机抽取不能为空！');break;
    			case 'extract':if(empty($field['extract']))$this->error('请选择字段！');break;
    			case 'merge':if(empty($field['merge']))$this->error('字段组合不能为空！');break;
    			case 'sign':
    			    if($field['source']=='source_url')$this->error('抱歉，起始页无法使用'.lang('field_module_sign'));
    			    if(empty($field['sign']))$this->error('请输入'.lang('field_module_sign'));
    			    break;
    		}
    		
			$modules = array (
				'rule' =>array('rule','rule_multi','rule_multi_type','rule_multi_str','rule_merge'),
				'auto' =>'auto',
				'xpath' =>array('xpath','xpath_multi','xpath_multi_type','xpath_multi_str','xpath_attr','xpath_attr_custom'),
				'json' =>array('json','json_arr','json_arr_implode','json_loop'),
				'words' =>'words',
				'num' => array('num_start','num_end'),
				'time' => array ('time_format','time_start','time_end','time_stamp'),
				'list' => 'list',
			    'extract' =>array('extract','extract_module','extract_rule','extract_rule_merge','extract_rule_multi','extract_rule_multi_str','extract_xpath','extract_xpath_attr','extract_xpath_attr_custom','extract_xpath_multi','extract_xpath_multi_str','extract_json','extract_json_arr','extract_json_arr_implode'),
				'merge' => 'merge',
			    'sign' => 'sign'
			);
    		$returnField=array('name'=>$field['name'],'source'=>$field['source'],'module'=>$field['module']);
    		
    		if(is_array($modules[$field['module']])){
    			foreach($modules[$field['module']] as $mparam){
    				$returnField[$mparam]=$field[$mparam];
    			}
    		}else{
    			$returnField[$modules[$field['module']]]=$field[$modules[$field['module']]];
    		}
    		$this->success('',null,array('field'=>$returnField,'objid'=>$objid));
    	}else{
    		$field=input('field','','url_b64decode');
    		$objid=input('objid');
    		$field=$field?json_decode($field,true):array();
    		if(!is_array($field)){
    		    $field=array();
    		}
    		$field['time_format']=$field['time_format']?$field['time_format']:'[年]/[月]/[日] [时]:[分]';
    		
    		
    		$sortField=array();
    		foreach(array('source','module') as $k){
    		    if(isset($field[$k])){
    		        $sortField[$k]=$field[$k];
    		        unset($field[$k]);
    		    }
    		}
    		
    		foreach ($field as $k=>$v){
    		    $sortField[$k]=$v;
    		}
    		$field=$sortField;
    		
    		$this->assign('field',$field);
    		$this->assign('objid',$objid);
    		return $this->fetch();
    	}
    }
    /*复制字段*/
    public function clone_fieldAction(){
        if(request()->isPost()){
            $field=input('field','','url_b64decode');
            $field=$field?json_decode($field,true):array();
            $process=input('process','','url_b64decode');
            $process=$process?json_decode($process,true):'';
            
            $this->success('',null,array('field'=>$field,'process'=>$process));
        }else{
            $this->error('复制失败');
        }
    }
    
    /*数据处理*/
    public function processAction(){
    	$type=input('type');

	    $this->assign('type',$type);
	    $op=input('op');
	    
	    
	    $transApiLangs=null;
	    if(!is_empty(g_sc_c('translate'))&&!is_empty(g_sc_c('translate','open'))){
	    	
	        $transApiLangs=\util\Translator::get_api_langs(g_sc_c('translate','api'));
	    	$transApiLangs=$transApiLangs?$transApiLangs:null;
	    }
	    $this->assign('transApiLangs',$transApiLangs);
	    
    	if(empty($type)){
    		
    		if(empty($op)){
    			$objid=input('objid');
    			$process=input('process','','url_b64decode');
    			$process=$process?json_decode($process,true):'';
    			$this->assign('objid',$objid);
    			$this->assign('process',$process);
    			return $this->fetch();
    		}elseif($op=='sub'){
    			
    		    $process=trim_input_array('process');
    			if(empty($process)){
    				$process='';
    			}else{
    			    $process=controller('admin/Cpattern','event')->set_process($process);
    			}
    			$objid=input('objid','');
    			$this->success('',null,array('process'=>$process,'process_json'=>empty($process)?'':json_encode($process),'objid'=>$objid));
    		}
    	}elseif('common'==$type){
    		
    		if(empty($op)){
    			return $this->fetch();
    		}elseif($op=='load'){
    			
    		    $process=trim_input_array('process');
    			$this->assign('process',$process);
    			return $this->fetch('process_load');
    		}
    	}
    }
    /*复制数据处理*/
    public function clone_processAction(){
        $op=input('op','');
        if(empty($op)||$op=='copy'){
            
            if(request()->isPost()){
                
                $process=trim_input_array('process');
                if(is_array($process)){
                    
                    $process=reset($process);
                }else{
                    $process=array();
                }
                
                $msg='';
                if($op=='copy'){
                    
                    cache('cpattern_clone_process_data',$process);
                    $msg='已拷贝，可在任意数据处理中粘贴';
                }else{
                    $msg='已复制';
                }
                
                $this->success($msg,null,$process);
            }else{
                $this->error('无效的操作');
            }
        }elseif($op=='paste'){
            
            
            $process=cache('cpattern_clone_process_data');
            
            if(!empty($process)){
                $this->success('已粘贴',null,$process);
            }else{
                $this->error('请先拷贝一个处理内容');
            }
        }else{
            $this->error('无效的操作');
        }
    }
    /**
     * 内容分页
     * 添加分页字段
     */
    public function paging_fieldAction(){
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$pagingField=trim_input_array('post.paging_field');
    		if(empty($pagingField['field'])){
    			$this->error('请选择字段');
    		}
    		$this->success('',null,array('paging_field'=>$pagingField,'objid'=>$objid));
    	}else{
    		$pagingField=input('paging_field','','url_b64decode');
    		$objid=input('objid');
    		$pagingField=$pagingField?json_decode($pagingField,true):'';
    		$this->assign('pagingField',$pagingField);
    		$this->assign('objid',$objid);
    		$this->assign('isLoop',input('is_loop'));
    		return $this->fetch();
    	}
    }
    /*多级网址规则*/
    public function level_urlAction(){
    	if(request()->isPost()&&input('is_submit')){
    		$objid=input('post.objid');
    		$level_url=trim_input_array('post.level_url');
    		if(empty($level_url['name'])){
    			$this->error('请输入名称');
    		}
    		$this->_check_name($level_url['name'],'多级页名称');
    		
    		$level_url=controller('admin/Cpattern','event')->set_config_url_web($level_url);
    		
    		$this->success('',null,array('level_url'=>$level_url,'objid'=>$objid));
    	}else{
    	    $level_url=input('level_url','','url_b64decode');
    		$objid=input('objid');
    		$level_url=$level_url?json_decode($level_url,true):array();
    		$this->assign('level_url',$level_url);
    		$this->assign('objid',$objid);
    		return $this->fetch();
    	}
    }
    /*复制多级页*/
    public function clone_level_urlAction(){
        if(request()->isPost()){
            $levelUrl=input('level_url','','url_b64decode');
            $levelUrl=$levelUrl?json_decode($levelUrl,true):array();
            
            $this->success('',null,array('level_url'=>$levelUrl));
        }else{
            $this->error('复制失败');
        }
    }
    /*关联网址规则*/
    public function relation_urlAction(){
        if(request()->isPost()&&input('is_submit')){
    		$objid=input('post.objid');
    		$relation_url=trim_input_array('post.relation_url');
    		if(empty($relation_url['name'])){
    			$this->error('请输入名称');
    		}
    		$this->_check_name($relation_url['name'],'关联页名称');
    		
    		if(empty($relation_url['url_rule'])){
    			$this->error('请输入提取网址规则');
    		}
    		
    		$relation_url=controller('admin/Cpattern','event')->set_config_url_web($relation_url);
    		
    		$this->success('',null,array('relation_url'=>$relation_url,'objid'=>$objid));
    	}else{
    		$relation_url=input('relation_url','','url_b64decode');
    		$objid=input('objid');
    		$relation_url=$relation_url?json_decode($relation_url,true):array();
    		$this->assign('relation_url',$relation_url);
    		$this->assign('objid',$objid);
    		return $this->fetch();
    	}
    }
    /*复制关联页*/
    public function clone_relation_urlAction(){
        if(request()->isPost()){
            $relationUrl=input('relation_url','','url_b64decode');
            $relationUrl=$relationUrl?json_decode($relationUrl,true):array();
            
            $this->success('',null,array('relation_url'=>$relationUrl));
        }else{
            $this->error('复制失败');
        }
    }
    /*编辑规则：简单模式*/
    public function easymodeAction(){
    	$taskId=input('task_id/d',0);
    	
    	$mcoll=model('Collector');
    	
    	$taskData=model('Task')->getById($taskId);
    	
    	$collId=$mcoll->where('task_id',$taskId)->value('id');
    	$collData=$mcoll->where(array('id'=>$collId))->find();
    	if(empty($collData)){
    	    $collData=array();
    	}else{
    	    $collData=$collData->toArray();
    	}
    	
    	$eCpattern=controller('admin/Cpattern','event');
    	$eCpattern->init($collData);
    	
    	$pageSources=$eCpattern->page_source_options();
    	
    	set_g_sc('p_title','简单模式_任务:'.$taskData['name']);
    	
    	$this->assign('taskId',$taskId);
    	$this->assign('collId',$collId);
    	$this->assign('pageSources',$pageSources);
    	return $this->fetch();
    }
    /*获取父级页面的标签列表*/
    public function parentPageSignsAction(){
        if(request()->isPost()){
            $level_urls=input('level_urls/a',array(),'url_b64decode');
            $relation_urls=input('relation_urls/a',array(),'url_b64decode');
            $urlConfig=input('url_config/a',array(),'trim');
            $pageConfig=input('page_config/a',array(),'trim');
            $isAreaOrUrl=input('is_area_or_url','');
            
            $pageType=input('page_type','','trim');
            
            $eCpattern=controller('admin/Cpattern','event');
            
            $levelSigns=array();
            $relationSigns=array();
            $urlSigns=array();
            $pageSigns=array();
            
            $relationUrls=array();
            
            foreach ($level_urls as $k=>$v){
                $v=$v?json_decode($v,true):array();
                $levelSigns[$v['name']]=array(
                    'area'=>$this->_get_rule_signs($v['area'],'',true,false),
                    'url'=>$this->_get_rule_signs('',$v['url_rule'],false,true),
                );
            }
            
            if($pageType=='relation_url'){
                
                foreach ($relation_urls as $k=>$v){
                    $v=$v?json_decode($v,true):array();
                    $relationSigns[$v['name']]=array(
                        'area'=>$this->_get_rule_signs($v['area'],'',true,false),
                        'url'=>$this->_get_rule_signs('',$v['url_rule'],false,true),
                    );
                    $relationUrls[$v['name']]=$v;
                }
            }
            
            if($pageType=='relation_url'||$pageType=='url'){
                if($pageType=='url'&&$isAreaOrUrl=='area'){
                    
                    $urlSigns=array(
                        'area'=>$this->_get_rule_signs($urlConfig['area'],'',true,false),
                    );
                }else{
                    $urlSigns=array(
                        'area'=>$this->_get_rule_signs($urlConfig['area'],'',true,false),
                        'url'=>$this->_get_rule_signs('',$urlConfig['url_rule'],false,true),
                    );
                }
            }
            
            if($isAreaOrUrl=='area'){
                $pageSigns=array(
                    'area'=>$this->_get_rule_signs($pageConfig['area'],'',true,false),
                );
            }else{
                $pageSigns=array(
                    'area'=>$this->_get_rule_signs($pageConfig['area'],'',true,false),
                    'url'=>$this->_get_rule_signs('',$pageConfig['url_rule'],false,true),
                );
            }
            
            if($pageType=='level_url'){
                
                if($pageConfig['name']&&isset($levelSigns[$pageConfig['name']])){
                    
                    $newLevelSigns=array();
                    foreach($levelSigns as $k=>$v){
                        if($pageConfig['name']==$k){
                            
                            $newLevelSigns['_cur_']=$pageSigns;
                            break;
                        }else{
                            $newLevelSigns[$k]=$v;
                        }
                    }
                    $levelSigns=$newLevelSigns;
                }else{
                    
                    $levelSigns['_cur_']=$pageSigns;
                }
            }elseif($pageType=='relation_url'){
                
                $newRelationSigns=array();
                $newRelationSigns['_cur_']=$pageSigns;
                $relationUrls[$pageConfig['name']]=$pageConfig;
                $relationParentPages=$eCpattern->relation_parent_pages($pageConfig['name'],$relationUrls);
                foreach ($relationParentPages as $relationParentPage){
                    
                    $newRelationSigns[$relationParentPage]=$relationSigns[$relationParentPage];
                }
                $relationSigns=$newRelationSigns;
            }
            
            $levelSigns=array_reverse($levelSigns,true);
            
            $allSigns=array();
            
            foreach ($relationSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前关联页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>'关联页:'.$k,'signs'=>$v);
                }
            }
            if($pageType=='relation_url'||$pageType=='url'){
                $allSigns[]=array('name'=>($pageType=='url'?'当前':'').'内容页','signs'=>$urlSigns,'cur'=>($pageType=='url'?true:false));
            }
            foreach ($levelSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前多级页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>'多级页:'.$k,'signs'=>$v);
                }
            }
            $existSigns=array();
            
            foreach ($allSigns as $ask=>$asv){
                $asv=is_array($asv)?$asv:array();
                $signs=$asv['signs'];
                $newSigns=array('area'=>array(),'url'=>array(),'area_global'=>array(),'url_global'=>array());
                if(!is_array($signs)){
                    $signs=array();
                }
                if(!is_array($signs['area'])){
                    $signs['area']=array();
                }
                if(!is_array($signs['url'])){
                    $signs['url']=array();
                }
                
                foreach ($signs['url'] as $k=>$v){
                    if(!in_array($v, $existSigns)){
                        
                        $existSigns[]=$v;
                        $newSigns['url_global'][]=$v;
                    }
                }
                foreach ($signs['area'] as $k=>$v){
                    if(!in_array($v, $existSigns)){
                        
                        $existSigns[]=$v;
                        $newSigns['area_global'][]=$v;
                    }
                }
                
                $newSigns['area']=$signs['area'];
                $newSigns['url']=$signs['url'];
                
                $asv['signs']=$newSigns;
                
                $allSigns[$ask]=$asv;
            }
            
            $this->success('',null,$allSigns);
        }else{
            $this->error();
        }
    }
    /*名称命名规范*/
    public function _check_name($name,$nameStr=''){
        if(!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)){
            $this->error(($nameStr?$nameStr:'名称').'只能由汉字、字母、数字和下划线组成');
            return false;
        }else{
            return true;
        }
    }
    
    
    private function _get_rule_signs($areaRule,$urlRule,$areaDef=true,$urlDef=true){
        $eCpattern=controller('admin/Cpattern','event');
        
        $areaRule=$areaRule?$areaRule:'';
        $urlRule=$urlRule?$urlRule:'';
        
        $areaRule=$eCpattern->convert_sign_match($areaRule);
        $urlRule=$eCpattern->convert_sign_match($urlRule);
        
        $areaSigns=$eCpattern->rule_str_signs($areaRule);
        $urlSigns=$eCpattern->rule_str_signs($urlRule);
        
        if(empty($areaSigns)){
            $areaSigns=$areaDef?array(cp_sign('match')):array();
        }
        if(empty($urlSigns)){
            $urlSigns=$urlDef?array(cp_sign('match')):array();
        }
        
        foreach ($areaSigns as $k=>$v){
            if(in_array($v,$urlSigns)){
                
                unset($areaSigns[$k]);
            }
        }
        return array_merge($areaSigns,$urlSigns);
    }
}