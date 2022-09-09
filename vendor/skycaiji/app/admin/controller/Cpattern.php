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

use skycaiji\admin\model\CacheModel;

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
                $this->success('',null,array('objid'=>$source['objid'],'url'=>$urlFmt,'urls'=>$urls));
            }else{
                $this->error('未生成网址！');
            }
        }else{
            $sourceUrl=input('source_url','','trim');
            if($sourceUrl){
                $source['objid']=input('objid','');
                
                if(preg_match('/\{param\:(\w+)\,([^\}]*)\}/i',$sourceUrl,$param)){
                    
                    $source['url']= preg_replace('/\{param\:(\w+)\,([^\}]*)\}/i', cp_sign('match'), $sourceUrl);
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
                }elseif(preg_match('/\{json\:([^\}]*)\}/i',$sourceUrl,$json)){
                    
                    $source['type']='api';
                    $source['api']=preg_replace('/\{json\:([^\}]*)\}/i','',$sourceUrl);
                    $source['api_json']=$json[1];
                }elseif(preg_match('/[\r\n]/', $sourceUrl)){
                    
                    $source['type']='large';
                    $source['large_urls']=$sourceUrl;
                }else{
                    
                    $source['type']='custom';
                    $source['urls']=$sourceUrl;
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
    		    $field=input('field','');
    			$objid=input('objid');
    			$process=input('process','','url_b64decode');
    			$process=$process?json_decode($process,true):'';
    			$this->assign('field',$field);
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
    public function pagination_fieldAction(){
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$pnField=trim_input_array('post.pagination_field');
    		if(empty($pnField['field'])){
    			$this->error('请选择字段');
    		}
    		$this->success('',null,array('pagination_field'=>$pnField,'objid'=>$objid));
    	}else{
    		$pnField=input('pagination_field','','url_b64decode');
    		$objid=input('objid');
    		$pnField=$pnField?json_decode($pnField,true):'';
    		$this->assign('pnField',$pnField);
    		$this->assign('objid',$objid);
    		$this->assign('isLoop',input('is_loop'));
    		return $this->fetch();
    	}
    }
    
    /*前置页规则*/
    public function front_urlAction(){
        if(request()->isPost()&&input('is_submit')){
            $objid=input('post.objid');
            $front_url=trim_input_array('post.front_url');
            if(empty($front_url['name'])){
                $this->error('请输入名称');
            }
            $this->_check_name($front_url['name'],'前置页名称');
            
            if(empty($front_url['url'])){
                $this->error('请输入网址');
            }
            
            if(!preg_match('/^\w+\:\/\/[^\r\n]+/i',$front_url['url'])){
                $this->error('请输入正确的网址格式');
            }
            
            $front_url['use_cookie']=intval($front_url['use_cookie']);
            $front_url['use_cookie_img']=intval($front_url['use_cookie_img']);
            
            
            $front_url=controller('admin/Cpattern','event')->page_set_config('front_url',$front_url);
            
            $this->success('',null,array('front_url'=>$front_url,'objid'=>$objid));
        }else{
            $front_url=input('front_url','','url_b64decode');
            $objid=input('objid');
            $front_url=$front_url?json_decode($front_url,true):array();
            $this->assign('front_url',$front_url);
            $this->assign('objid',$objid);
            return $this->fetch();
        }
    }
    /*复制前置页*/
    public function clone_front_urlAction(){
        if(request()->isPost()){
            $frontUrl=input('front_url','','url_b64decode');
            $frontUrl=$frontUrl?json_decode($frontUrl,true):array();
            
            $this->success('',null,array('front_url'=>$frontUrl));
        }else{
            $this->error('复制失败');
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
    		
    		
    		$level_url=controller('admin/Cpattern','event')->page_set_config('level_url',$level_url);
    		
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
    		
    		
    		$relation_url=controller('admin/Cpattern','event')->page_set_config('relation_url',$relation_url);
    		
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
    
    /*编辑内容标签*/
    public function content_signAction(){
        if(request()->isPost()&&input('is_submit')){
            $objid=input('post.objid');
            $contentSign=trim_input_array('post.content_sign');
            if(empty($contentSign['identity'])){
                $this->error('请输入标识名');
            }
            if(!preg_match('/^[a-z0-9\_]+$/i', $contentSign['identity'])){
                $this->error('标识名只能由数字、字母和下划线组成');
            }
            switch ($contentSign['module']){
                case 'rule':if(empty($contentSign['rule']))$this->error('规则不能为空！');break;
                case 'xpath':if(empty($contentSign['xpath']))$this->error('xpath规则不能为空！');break;
                case 'json':if(empty($contentSign['json']))$this->error('json提取规则不能为空！');break;
            }
            $this->success('',null,array('content_sign'=>$contentSign,'objid'=>$objid));
        }else{
            $objid=input('objid');
            $contentSign=input('content_sign','','url_b64decode');
            $contentSign=$contentSign?json_decode($contentSign,true):array();
            $contentSign=is_array($contentSign)?$contentSign:array();
            
            $page_type=input('page_type','');
            $page_config=input('page_config','','trim');
            if($page_config){
                
                $pageConfig=array();
                parse_str($page_config,$pageConfig);
                
                $pageTypeConfig=is_array($pageConfig[$page_type])?$pageConfig[$page_type]:array();
                $pageTypeConfig['content_signs']=is_array($pageTypeConfig['content_signs'])?$pageTypeConfig['content_signs']:array();
                foreach ($pageTypeConfig['content_signs'] as $k=>$v){
                    $pageTypeConfig['content_signs'][$k]=json_decode(url_b64decode($v),true);
                }
                $pageConfig[$page_type]=$pageTypeConfig;
                $page_config=array('objid'=>$pageConfig['objid'],$page_type=>$pageConfig[$page_type]);
            }
            $page_config=is_array($page_config)?$page_config:array();
            
            
            $this->assign('objid',$objid);
            $this->assign('content_sign',$contentSign);
            $this->assign('page_type',$page_type);
            $this->assign('page_config',$page_config);
            return $this->fetch();
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
    	
    	$this->set_html_tags('任务:'.$taskData['name'].'_简单模式');
    	
    	$this->assign('taskId',$taskId);
    	$this->assign('collId',$collId);
    	return $this->fetch();
    }
    
    public function page_signs_sortAction(){
        $mcache=CacheModel::getInstance();
        $key='cpattern_page_signs_sort';
        $sort=$mcache->getCache($key,'data');
        $sort=$sort=='asc'?'desc':'asc';
        $mcache->setCache($key,$sort);
        $this->success('已将页面设为'.($sort=='asc'?'升序':'倒序').'排列');
    }
    /*获取父级页面的标签列表*/
    public function page_signsAction(){
        if(request()->isPost()){
            $front_urls=input('front_urls/a',array(),'url_b64decode');
            $level_urls=input('level_urls/a',array(),'url_b64decode');
            $relation_urls=input('relation_urls/a',array(),'url_b64decode');
            $sourceConfig=input('source_config/a',array(),'trim');
            $urlConfig=input('url_config/a',array(),'trim');
            $pageConfig=input('page_config/a',array(),'trim');
            $mergeType=input('merge_type','');
            $sourceIsUrl=input('source_is_url/d',0);

            $mergeCsIdentity='';
            if(strpos($mergeType,'content_sign:')===0){
                
                $mergeCsIdentity=str_replace('content_sign:', '', $mergeType);
                $mergeCsIdentity=cp_sign('match',$mergeCsIdentity);
            }
            
            $pageType=input('page_type','','trim');
            
            if($sourceIsUrl){
                
                $level_urls=array();
                $urlConfig['area']='';
                $urlConfig['url_rule']='';
                $sourceConfig=$urlConfig;
                $pageType='url';
            }
            
            $eCpattern=controller('admin/Cpattern','event');
            
            $frontSigns=array();
            $levelSigns=array();
            $relationSigns=array();
            $sourceSigns=array();
            $urlSigns=array();
            $pageSigns=array();
            $relationUrls=array();
            
            foreach ($front_urls as $k=>$v){
                $v=$v?json_decode($v,true):array();
                $frontSigns[$v['name']]=array(
                    'area'=>'',
                    'url'=>'',
                    'content'=>$this->_get_content_signs($v['content_signs'])
                );
            }
            
            if($pageType!='front_url'){
                $sourceSigns=array('area'=>'','url'=>'');
                if($pageType!='source_url'||($mergeType!='area'&&$mergeType!='url')){
                    
                    $sourceSigns['content']=$this->_get_content_signs($sourceConfig['content_signs']);
                }
            }
            
            if($pageType!='front_url'&&$pageType!='source_url'){
                foreach ($level_urls as $k=>$v){
                    $v=$v?json_decode($v,true):array();
                    $levelSigns[$v['name']]=array(
                        'area'=>$this->_get_rule_signs($v['area']),
                        'url'=>$this->_get_rule_signs($v['url_rule']),
                        'content'=>$this->_get_content_signs($v['content_signs'])
                    );
                }
            }
            
            if($pageType=='url'||$pageType=='relation_url'){
                
                $urlSigns=array('area'=>$sourceIsUrl?'':$this->_get_rule_signs($urlConfig['area']));
                if($pageType!='url'||$mergeType!='area'){
                    
                    $urlSigns['url']=$sourceIsUrl?'':$this->_get_rule_signs($urlConfig['url_rule']);
                }
                if($pageType!='url'||($mergeType!='area'&&$mergeType!='url')){
                    
                    $urlSigns['content']=$this->_get_content_signs($urlConfig['content_signs']);
                }
            }
            
            if($pageType=='relation_url'){
                foreach ($relation_urls as $k=>$v){
                    $v=$v?json_decode($v,true):array();
                    $relationSigns[$v['name']]=array(
                        'area'=>$this->_get_rule_signs($v['area']),
                        'url'=>$this->_get_rule_signs($v['url_rule']),
                        'content'=>$this->_get_content_signs($v['content_signs'])
                    );
                    $relationUrls[$v['name']]=$v;
                }
            }
            
            if($pageType=='front_url'){
                
                $pageSigns=array('area'=>'','url'=>'');
            }else{
                $pageSigns=array('area'=>$this->_get_rule_signs($pageConfig['area']));
                if($mergeType!='area'){
                    
                    $pageSigns['url']=$this->_get_rule_signs($pageConfig['url_rule']);
                }
            }
            if($mergeType!='area'&&$mergeType!='url'){
                
                $pageSigns['content']=$this->_get_content_signs($pageConfig['content_signs']);
            }
            
            if($pageType=='front_url'){
                
                if($pageConfig['name']&&isset($frontSigns[$pageConfig['name']])){
                    
                    $newFrontSigns=array();
                    foreach($frontSigns as $k=>$v){
                        if($pageConfig['name']==$k){
                            
                            $newFrontSigns['_cur_']=$pageSigns;
                            break;
                        }else{
                            $newFrontSigns[$k]=$v;
                        }
                    }
                    $frontSigns=$newFrontSigns;
                }else{
                    
                    $frontSigns['_cur_']=$pageSigns;
                }
            }elseif($pageType=='level_url'){
                
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
            
            $frontSigns=array_reverse($frontSigns,true);
            $levelSigns=array_reverse($levelSigns,true);
            
            $allSigns=array();
            
            foreach ($relationSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前关联页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>$eCpattern->page_source_name('relation_url',$k),'signs'=>$v);
                }
            }
            if($pageType=='relation_url'||$pageType=='url'){
                $allSigns[]=array('name'=>($pageType=='url'?'当前':'').'内容页','signs'=>$urlSigns,'cur'=>($pageType=='url'?true:false));
            }
            foreach ($levelSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前多级页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>$eCpattern->page_source_name('level_url',$k),'signs'=>$v);
                }
            }
            if($pageType!='front_url'&&!$sourceIsUrl){
                $allSigns[]=array('name'=>($pageType=='source_url'?'当前':'').'起始页','signs'=>$sourceSigns,'cur'=>($pageType=='source_url'?true:false));
            }
            foreach ($frontSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前前置页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>$eCpattern->page_source_name('front_url',$k),'signs'=>$v);
                }
            }
            
            
            foreach ($allSigns as $ask=>$asv){
                if($asv['cur']){
                    if($mergeCsIdentity&&is_array($asv['signs'])&&is_array($asv['signs']['content'])){
                        $curContSigns=array();
                        foreach ($asv['signs']['content'] as $k=>$v){
                            if($v==$mergeCsIdentity){
                                break;
                            }
                            $curContSigns[]=$v;
                        }
                        $asv['signs']['content']=$curContSigns;
                    }
                    $allSigns[$ask]=$asv;
                }
            }
            
            $existSigns=array();
            
            foreach ($allSigns as $ask=>$asv){
                $signs=array('area'=>array(),'url'=>array(),'content'=>array(),'area_global'=>array(),'url_global'=>array(),'content_global'=>array());
                
                $asv=is_array($asv)?$asv:array();
                $asv['signs']=is_array($asv['signs'])?$asv['signs']:array();
                $signs['area']=is_array($asv['signs']['area'])?$asv['signs']['area']:array();
                $signs['url']=is_array($asv['signs']['url'])?$asv['signs']['url']:array();
                $signs['content']=is_array($asv['signs']['content'])?$asv['signs']['content']:array();
                
                
                foreach (array('content','url','area') as $k){
                    foreach ($signs[$k] as $v){
                        if(!in_array($v, $existSigns)){
                            
                            $existSigns[]=$v;
                            $signs[$k.'_global'][]=$v;
                        }
                    }
                }
                
                $asv['signs']=$signs;
                
                $allSigns[$ask]=$asv;
            }
            
            $mcache=CacheModel::getInstance();
            $sort=$mcache->getCache('cpattern_page_signs_sort','data');
            $sort=$sort?$sort:'desc';
            
            if($sort=='asc'){
                
                krsort($allSigns);
                $allSigns=array_values($allSigns);
            }
            
            
            
            $this->success('',null,array('sort'=>$sort,'signs'=>$allSigns));
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
    
    
    private function _get_content_signs($contentSigns){
        if(is_array($contentSigns)){
            $csSigns=array();
            foreach ($contentSigns as $v){
                if(is_array($v)&&$v['identity']){
                    $csSigns[$v['identity']]=cp_sign('match',$v['identity']);
                }
            }
            $contentSigns=array_values($csSigns);
        }else{
            $contentSigns=array();
        }
        return $contentSigns;
    }
    
    
    private function _get_rule_signs($rule){
        $eCpattern=controller('admin/Cpattern','event');
        $rule=$rule?$rule:'';
        $rule=$eCpattern->convert_sign_match($rule);
        $signs=$eCpattern->rule_str_signs($rule);
        if(empty($signs)){
            $signs=array(cp_sign('match'));
        }
        return $signs;
    }
}