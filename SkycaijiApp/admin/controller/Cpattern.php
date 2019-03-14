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

namespace skycaiji\admin\controller;

/*采集器：规则采集*/
class Cpattern extends BaseController {
	/**
	 * 起始页网址
	 */
    public function sourceAction(){
    	$is_sub=input('sub');
    	if(request()->isPost()&&$is_sub){
    		$source=input('source/a','','trim');
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
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$field=input('post.field/a',null,'trim');
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
    			case 'page':if(empty($field['page']))$this->error('请选择页面！');if(empty($field['page_rule']))$this->error('规则不能为空！');break;
    			case 'num':
    				$randNum=0;
    				$field['num_start']=intval($field['num_start']);
    				$field['num_end']=intval($field['num_end']);
    				$field['num_end'] = max ( $field['num_start'], $field ['num_end'] );
    				break;
    			case 'words':if(empty($field['words']))$this->error('固定文字不能为空！');break;
    			case 'list':if(empty($field['list']))$this->error('随机抽取不能为空！');break;
    			case 'extract':if(empty($field['extract']))$this->error('请选择字段！');break;
    			case 'merge':if(empty($field['merge']))$this->error('字段组合不能为空！');break;
    		}
    		
			$modules = array (
				'rule' =>array('rule','rule_multi','rule_multi_type','rule_multi_str','rule_merge'),
				'auto' =>'auto',
				'xpath' =>array('xpath','xpath_multi','xpath_multi_type','xpath_multi_str','xpath_attr','xpath_attr_custom'),
				'json' =>array('json','json_arr','json_arr_implode'),
				'page' =>array('page','page_rule','page_rule_merge','page_rule_multi','page_rule_multi_str'),
				'words' =>'words',
				'num' => array('num_start','num_end'),
				'time' => array ('time_format','time_start','time_end','time_stamp'),
				'list' => 'list',
				'extract' =>array('extract','extract_module','extract_rule','extract_xpath','extract_xpath_attr','extract_xpath_attr_custom','extract_json','extract_json_arr','extract_json_arr_implode'),
				'merge' => 'merge'
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
    		$field['time_format']=$field['time_format']?$field['time_format']:'[年]/[月]/[日] [时]:[分]';
    		$this->assign('field',$field);
    		$this->assign('objid',$objid);
    		return $this->fetch();
    	}
    }
    /**
     * 数据处理
     */
    public function processAction(){
    	$type=input('type');

	    $this->assign('type',$type);
	    $op=input('op');
    	if(empty($type)){
    		
    		if(empty($op)){
    			$objid=input('objid');
    			$process=input('process','','url_b64decode');
    			$process=$process?json_decode($process,true):'';
    			$this->assign('objid',$objid);
    			$this->assign('process',$process);
    			return $this->fetch();
    		}elseif($op=='sub'){
    			
    			$process=input('process/a',null,'trim');
    			if(empty($process)){
    				$process='';
    			}else{
    				foreach($process as $k=>$v){
    					if(!empty($v['title'])){
    						$process[$k]['title']=str_replace(array("'",'"'),'',strip_tags($v['title']));
    					}
    				}
    			}
    			
    			$objid=input('objid','');
    			$this->success('',null,array('process'=>$process,'process_json'=>empty($process)?'':json_encode($process),'objid'=>$objid));
    		}
    	}elseif('common'==$type){
    		
    		if(empty($op)){
    			return $this->fetch();
    		}elseif($op=='load'){
    			
    			$process=input('process/a',null,'trim');
    			$this->assign('process',$process);
    			return $this->fetch('process_load');
    		}
    	}
    }
    /**
     * 内容分页
     * 添加分页字段
     */
    public function paging_fieldAction(){
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$pagingField=input('post.paging_field/a',null,'trim');
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
    		return $this->fetch();
    	}
    }
    /*多级网址规则*/
    public function level_urlAction(){
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$level_url=input('post.level_url/a',null,'trim');
    		if(empty($level_url['name'])){
    			$this->error('请输入名称');
    		}
    		$this->_check_name($level_url['name'],'多级名称');
    		
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
    /*关联网址规则*/
    public function relation_urlAction(){
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$relation_url=input('post.relation_url/a',null,'trim');
    		if(empty($relation_url['name'])){
    			$this->error('请输入名称');
    		}
    		$this->_check_name($relation_url['name'],'关联页名称');
    		
    		if(empty($relation_url['url_rule'])){
    			$this->error('请输入提取网址规则');
    		}
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
    /*测试*/
    public function testAction(){
    	set_time_limit(600);
    	
    	$coll_id=input('coll_id/d',0);
    	$mcoll=model('Collector');
    	$collData=$mcoll->where(array('id'=>$coll_id))->find();
    	if(empty($collData)){
    		$this->error(lang('coll_error_empty_coll'));
    	}
    	if(!in_array($collData['module'],config('allow_coll_modules'))){
    		$this->error(lang('coll_error_invalid_module'));
    	}
    	$this->assign('collData',$collData);
    	
    	
    	$eCpattern=controller('admin/Cpattern','event');
    	$eCpattern->init($collData);
    	
    	$op=input('op');
    	$taskData=model('Task')->getById($eCpattern->collector['task_id']);

    	model('Task')->loadConfig($taskData['config']);

    	$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Collector/set?task_id='.$taskData['id']),'title'=>lang('task').lang('separator').$taskData['name']),'测试'));
    	
    	if('source_urls'==$op){
    		
    		$source_urls=array();
    		foreach ($eCpattern->config['source_url'] as $k => $v) {
    			if(empty($v)){
    				continue;
    			}
    			$source_urls[$v] = $eCpattern->convert_source_url ( $v );
    		}
    		if(!$eCpattern->config['source_is_url']){
    			
    			$source_urls1=array();
    			foreach ($source_urls as $k=>$v){
    				if (is_array ( $v )) {
    					$source_urls1 = array_merge ( $source_urls1, $v );
    				} else {
    					$source_urls1 [] = $v;
    				}
    			}
    			$source_urls=$source_urls1;
    		}
    		$eCpattern->assign('source_urls',$source_urls);
    		$eCpattern->assign('config',$eCpattern->config);
    		return $eCpattern->fetch('cpattern:test_source_urls');
    	}elseif('cont_urls'==$op){
    		
    		$source_url=input('source_url','','trim');
    		$curLevel=input('level/d',0);
    		$curLevel=$curLevel>0?$curLevel:0;
    			
    		$levelData=$eCpattern->get_level_urls($source_url,$curLevel);
    		
    		$eCpattern->success('',null,array('urls'=>$levelData['urls'],'levelName'=>$levelData['levelName'],'nextLevel'=>$levelData['nextLevel']));
    	}elseif('cont_url'==$op){
    		
    		$GLOBALS['content_header']='测试抓取';
    		$cont_url=input('cont_url','','trim');
    		$test=input('test');
    		
    		$url_post=$eCpattern->config['url_post'];
    		
    		$input_urls=array();
    		foreach ($eCpattern->config['new_field_list'] as $field){
    			if('source_url'==strtolower($field['field']['source'])){
    				
    				$input_urls['source_url']=input('source_url');
    				$input_urls['source_url']=$input_urls['source_url']?$input_urls['source_url']:'';
    			}elseif(preg_match('/level_url:/i', $field['field']['source'])){
    				
    				foreach($eCpattern->config['level_urls'] as $levIx=>$levVal){
    					if($field['field']['source']==('level_url:'.$levVal['name'])){
    						
    						$level=$levIx+1;
    						$input_urls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>input('level_'.$level));
    						break;
    					}
    				}
    			}
    		}

    		$eCpattern->assign('cont_url',$cont_url);
    		$eCpattern->assign('url_post',$url_post);
    		$eCpattern->assign('input_urls',$input_urls);
    		$eCpattern->assign('test',$test);
    		if(request()->isAjax()){
    			return view('cpattern:test_cont_url_ajax');
    		}else{
    			return $eCpattern->fetch('cpattern:test_cont_url');
    		}
    	}elseif(in_array($op, array('get_fields','get_paging_urls','get_relation_urls','get_html'))){
    		
    		$cont_url=input('cont_url','','trim');
    		if(!preg_match('/^\w+\:\/\//',$cont_url)){
    			
    			$cont_url='http://'.$cont_url;
    		}
    		$html='get_fields'==$op?'':$eCpattern->get_html($cont_url,false,$eCpattern->config['url_post']);
    		if('get_fields'==$op){
    			
    			if(input('?source_url')){
    				
    				$eCpattern->cur_source_url=input('source_url');
    			}
    			foreach (input('param.') as $k=>$v){
    				
    				if(preg_match('/^level_(\d+)$/',$k,$mLevel)){
    					
    					$mLevel=intval($mLevel[1])-1;
    					$eCpattern->cur_level_urls[$eCpattern->config['level_urls'][$mLevel]['name']]=$v;
    				}
    			}
    			
    			$val_list=$eCpattern->getFields($cont_url);
    
    			if(empty($eCpattern->first_loop_field)){
    				
    				$val_list=array($val_list);
    			}

    			foreach ($val_list as $v_k=>$vals){
    				foreach ($vals as $k=>$v){
    					$vals[$k]=$v['value'];
    				}
    				$val_list[$v_k]=$vals;
    			}
    			$eCpattern->success('',null,$val_list);
    		}elseif('get_paging_urls'==$op){
    			
    			$paging_urls=$eCpattern->getPagingUrls($cont_url,$html,true);
    			if(empty($paging_urls)){
    				$eCpattern->error('没有抓取到分页链接');
    			}else{
    				$eCpattern->success('',null,$paging_urls);
    			}
    		}elseif('get_html'==$op){
    			if(empty($html)){
    				exit('没有抓取到源码');
    			}else{
    				
    				exit($html);
    			}
    		}elseif('get_relation_urls'==$op){
    			
    			$url_list=array();
    			foreach ($eCpattern->config['new_relation_urls'] as $k=>$v){
    				$url_list[$v['name']]=$eCpattern->getRelationUrl($v['name'], $cont_url, $html);
    			}
    			if(empty($url_list)){
    				$eCpattern->error('没有关联页');
    			}else{
    				$eCpattern->success('',null,$url_list);
    			}
    		}
    	}elseif('match'==$op){
    		
    		$GLOBALS['content_header']='模拟匹配';
    		if(request()->isPost()){
    			$type=strtolower(input('type'));
    			$content=input('content','','trim');
    			$match=input('match','','trim');
    			if(empty($content)){
    				$eCpattern->error('请输入网址或内容');
    			}
    			if(empty($match)){
    				$eCpattern->error('请输入规则');
    			}
    			if(preg_match('/^\w+\:\/\//', $content)){
    				
    				$content=$eCpattern->get_html($content);
    			}
    			if(empty($content)){
    				$eCpattern->error('内容空');
    			}
    			$val='';
    			switch ($type){
    				case 'rule':
    					
    					$match=$eCpattern->convert_sign_match($match);
    					$match=preg_replace('/\\\*([\'\/])/', "\\\\$1",$match);
    					$match=str_replace('(*)', '[\s\S]*?', $match); 
    
    					$rule_merge=$eCpattern->set_merge_default($match, '');
    					$val=$eCpattern->field_module_rule(array('reg_rule'=>$match,'rule_merge'=>$rule_merge), $content);
    					
    					if(empty($val)){
    						if(preg_match('/'.$match.'/i', $content,$val)){
    							$val=$val[0];
    						}
    					}
    					break;
    				case 'xpath':
    					$val=$eCpattern->field_module_xpath(array('xpath'=>$match,'xpath_attr'=>''), $content);
    					break;
    				case 'json':
    					$val=$eCpattern->field_module_json(array('json'=>$match,'json_arr_implode'=>"\r\n"), $content);
    					$val=trim($val);
    					break;
    			}
    			if(empty($val)){
    				$eCpattern->error('没有获取到数据');
    			}else{
    				$eCpattern->success($val);
    			}
    		}else{
    			if(request()->isAjax()){
    				return view('cpattern:test_match_ajax');
    			}else{
    				return $eCpattern->fetch('cpattern:test_match');
    			}
    		}
    	}elseif('elements'==$op){
    		
    		$cont_url=input('cont_url','','trim');
    		if(!preg_match('/^\w+\:\/\//',$cont_url)){
    			
    			$cont_url='http://'.$cont_url;
    		}
    		$html=$eCpattern->get_html($cont_url,false,$eCpattern->config['url_post']);
    		
    		$jsonData=null;
    		if(preg_match('/^\{[\s\S]*\}$/',$html)){
    			
    			$jsonData=json_decode($html,true);
    			$jsonData=$jsonData?$jsonData:null;
    		}
    		
    		
    		$publicUrl=config('root_website').'/public';
    		$jscss="\r\n<!-- 以下为蓝天采集器代码 -->\r\n".'<script src="%s/jquery/jquery.min.js?%s"></script>'
    			."\r\n".'<script src="%s/static/js/admin/cpattern_elements.js?%s"></script>'
    			."\r\n".'<link rel="stylesheet" href="%s/static/css/cpattern_elements.css?%s">';
    		$jscss=sprintf($jscss,$publicUrl,config('html_v'),$publicUrl,config('html_v'),$publicUrl,config('html_v'));
    		
    		if(empty($jsonData)){
    			
    			$html=preg_replace('/<script[^<>]*?>[\s\S]*?<\/script>/i', '', $html);
    			$html=preg_replace('/<meta[^<>]*charset[^<>]*?>/i', '', $html);
    			$html.=$jscss."\r\n".'<script>$(document).ready(function(){skycaijiCE.init();});</script>';
    			ob_clean();
    			header("Content-type:text/html;charset=utf-8");
    			exit($html);
    		}else{
    			
    			$GLOBALS['content_header']='分析元素';
    			
    			$eCpattern->assign('html',$html);
    			$eCpattern->assign('jscss',$jscss);
    			return $eCpattern->fetch('cpattern:test_elements');
    		}
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
}