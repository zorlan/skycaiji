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
class CpatternBase extends Collector{
	public $collector;
	public $config;
	public $release;
	public $first_loop_field=null;
	public $field_val_list=array();
	public $collect_num=0;
	public $collected_field_list=array();
	public $used_source_urls=array();
	public $used_level_urls=array();
	public $used_cont_urls=array();
	public $original_source_urls=null;
	public $level_urls_list=array();
	public $cont_urls_list=array();
	public $exclude_cont_urls=array();
	public $relation_url_list=array();
	public $used_paging_urls=array();
	public $cur_level_urls=array();
	public $cur_source_url='';
	public $html_cache_list=array();
	public $show_opened_tools=false;
	
	public function setConfig($config){}
	public function init($config){}
	public function collect($num=10){}
	
	/*对象销毁时处理*/
	public function __destruct(){
		if(!empty($this->used_cont_urls)){
			
			$usedContUrls=array_keys($this->used_cont_urls);
			if(!empty($usedContUrls)&&is_array($usedContUrls)){
				$total=count($usedContUrls);
				$limit=800;
				$batch=ceil($total/$limit);
				for($i=1;$i<=$batch;$i++){
					
					$list=array_slice($usedContUrls,($i-1)*$limit,$limit);
					if(!empty($list)){
						CacheModel::getInstance('cont_url')->db()->where('cname','in',$list)->delete();
					}
				}
			}
		}
	}
	
	/*规则匹配区域*/
	public function rule_match_area($config,$html){
		if(!empty($config['reg_area'])){
			if(empty($config['reg_area_module'])){
				
				if(preg_match('/'.$config['reg_area'].'/i',$html,$area_cont)){
					if(isset($area_cont['match'])){
						$html=$area_cont['match'];
					}else{
						$html=$area_cont[0];
					}
				}else{
					$html='';
				}
			}elseif('json'==$config['reg_area_module']){
				$html=$this->rule_module_json_data(array('json'=>$config['reg_area'],'json_arr'=>'jsonencode'),$html);
			}elseif('xpath'==$config['reg_area_module']){
				$html=$this->rule_module_xpath_data(array('xpath'=>$config['reg_area'],'xpath_attr'=>'outerHtml'),$html);
			}else{
				$html='';
			}
		}
		return $html;
	}
	/**
	 * 规则匹配网址
	 * @param array $config 配置参数
	 * @param string $html 源码
	 * @param bool $whole 完全匹配模式
	 * 
	 */
	public function rule_match_urls($config,$html,$whole=false){
		$cont_urls=array();
		if(!empty($config['reg_url'])&&!empty($config['url_merge'])){
			
			$sign_match=$this->sign_addslashes(cp_sign('match','(?P<num>\d*)'));
			if(preg_match_all('/'.$sign_match.'/i', $config['url_merge'],$match_signs)){
				
				$url_merge=true;
				if(empty($config['reg_url_module'])){
					
					if(preg_match('/\(\?P<match\d*>/i', $config['reg_url'])){
						
						if(preg_match_all('/'.$config['reg_url'].'/i',$html,$cont_urls,PREG_SET_ORDER)){
							if($config['url_merge']==cp_sign('match')){
								
								$url_merge=false;
								foreach ($cont_urls as $k=>$v){
									$cont_urls[$k]=$v['match'];
								}
							}
						}else{
							$cont_urls=array();
						}
					}else{
						
						if($whole){
							
							if(preg_match_all('/'.$config['reg_url'].'/i',$html,$cont_urls)){
								$cont_urls=$cont_urls[0];
							
								if($config['url_merge']==cp_sign('match')){
									
									$url_merge=false;
								}else{
									
									foreach ($cont_urls as $k=>$v){
										$cont_urls[$k]=array(
											'match'=>$v
										);
									}
								}
							}else{
								$cont_urls=array();
							}
						}
					}
				}elseif(in_array($config['reg_url_module'],array('xpath','json'))){
					
					if('xpath'==$config['reg_url_module']){
						
						$cont_urls=$this->rule_module_xpath_data ( array (
								'xpath' => $config['reg_url'],
								'xpath_attr' => 'href',
								'xpath_multi'=>true,
								'xpath_multi_type'=>'loop'
						),$html);
						$cont_urls=is_array($cont_urls)?$cont_urls:array();
					}elseif('json'==$config['reg_url_module']){
						
						$cont_urls=$this->rule_module_json_data(array('json'=>$config['reg_url'],'json_arr'=>'_original_'),$html);
						if(empty($cont_urls)){
							$cont_urls=array();
						}elseif(!is_array($cont_urls)){
							$cont_urls=array($cont_urls);
						}
					}
						
					if($config['url_merge']==cp_sign('match')){
						
						$url_merge=false;
					}else{
						
						foreach ($cont_urls as $k=>$v){
							$cont_urls[$k]=array(
								'match'=>$v
							);
						}
					}
				}
		
				if($url_merge){
					
					foreach ($cont_urls as $k=>$v){
						$re_match=array();
						foreach($match_signs['num'] as $ms_k=>$ms_v){
							
							$re_match[$ms_k]=$v['match'.$ms_v];
						}
						
						$cont_urls[$k]=str_replace($match_signs[0], $re_match, $config['url_merge']);
					}
				}
			}
		}
		$cont_urls=is_array($cont_urls)?array_unique($cont_urls):array();
		$cont_urls=array_values($cont_urls);
		return $cont_urls;
	}
	
	
	public function match_rule($html,$rule,$merge,$multi=false,$multi_str=''){
		$val='';
		$sign_match=$this->sign_addslashes(cp_sign('match','(?P<num>\d*)'));
		if(!empty($rule)&&preg_match_all('/'.$sign_match.'/i',$merge,$match_signs)){
			
			$multiStr='';
			if(!empty($multi)){
				
				preg_match_all('/'.$rule.'/i',$html,$match_conts,PREG_SET_ORDER);
				$multiStr=str_replace(array('\r','\n'), array("\r","\n"), $multi_str);
			}else{
				if(preg_match('/'.$rule.'/i', $html,$match_cont)){
					$match_conts=array($match_cont);
				}
			}
			$curI=0;
			foreach ($match_conts as $match_cont){
				$curI++;
				
				$re_match=array();
				foreach($match_signs['num'] as $ms_k=>$ms_v){
					$re_match[$ms_k]=$match_cont['match'.$ms_v];
				}
				$val.=($curI<=1?'':$multiStr).str_replace($match_signs[0], $re_match, $merge);
			}
		}
		return $val;
	}
	
	/**
	 * 规则匹配，方法可调用，$field_params传入规则参数
	 * @param array $field_params 
	 * @param string $html
	 * @return string
	 */
	public function field_module_rule($field_params,&$html){
		
		$val='';
		$sign_match=$this->sign_addslashes(cp_sign('match','(?P<num>\d*)'));
		if(!empty($field_params['reg_rule'])&&preg_match_all('/'.$sign_match.'/i', $field_params['rule_merge'],$match_signs)){
			
			$multiStr='';
			$is_loop=false;
			if(!empty($field_params['rule_multi'])){
				
				preg_match_all('/'.$field_params['reg_rule'].'/i',$html,$match_conts,PREG_SET_ORDER);
				$is_loop='loop'==$field_params['rule_multi_type']?true:false;
				if($is_loop){
					if(empty($this->first_loop_field)){
						
						$this->first_loop_field=$field_params['name'];
					}
					$val=array();
				}else{
					$multiStr=str_replace(array('\r','\n'), array("\r","\n"), $field_params['rule_multi_str']);
				}
			}else{
				if(preg_match('/'.$field_params['reg_rule'].'/i', $html,$match_cont)){
					$match_conts=array($match_cont);
				}
			}
			
			$curI=0;
			if(is_array($match_conts)){
				foreach ($match_conts as $match_cont){
					$curI++;
					
					$re_match=array();
					foreach($match_signs['num'] as $ms_k=>$ms_v){
						$re_match[$ms_k]=$match_cont['match'.$ms_v];
					}
					$contVal=str_replace($match_signs[0], $re_match, $field_params['rule_merge']);
					if($is_loop){
						
						$val[]=$contVal;
					}else{
						
						$val.=($curI<=1?'':$multiStr).$contVal;
					}
				}
			}
		}
		return $val;
	}
	/**
	 * xpath规则，方法可调用，$field_params传入规则参数
	 * @param array $field_params
	 * @param string $html
	 * @return string
	 */
	public function field_module_xpath($field_params,$html){
		if(!empty($field_params['xpath_multi'])){
			
			if('loop'==$field_params['xpath_multi_type']){
				
				if(empty($this->first_loop_field)){
					
					$this->first_loop_field=$field_params['name'];
				}
			}
		}
		return $this->rule_module_xpath_data($field_params,$html);
	}
	public function rule_module_xpath_data($field_params,$html){
		$vals='';
		if(!empty($field_params['xpath'])){
			$dom=new \DOMDocument;
			$libxml_previous_state = libxml_use_internal_errors(true);
			@$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html;charset=utf-8">'.$html);
			
			$dom->normalize();
			
			$xPath = new \DOMXPath($dom);
			
			$xpath_attr=strtolower($field_params['xpath_attr']);
			$xpath_attr='custom'==$xpath_attr?strtolower($field_params['xpath_attr_custom']):$xpath_attr;
				
			$normal_attr=true;
			if(in_array($xpath_attr,array('innerhtml','outerhtml','text'))){
				
				$normal_attr=false;
			}
			$xpath_q=trim($field_params['xpath']);
			if(!empty($xpath_attr)){
				
				if(preg_match('/\/\@[\w\-]+$/', $xpath_q)){
					
					$xpath_q=preg_replace('/\@[\w\-]+$/', '', $xpath_q);
				}
				if($normal_attr){
					
					$xpath_q=$xpath_q.(preg_match('/\/$/', $xpath_q)?'':'/').'@'.$xpath_attr;
				}
			}else{
				
				if(!preg_match('/\/\@[\w\-]+$/', $xpath_q)){
					
					$xpath_attr='innerhtml';
					$normal_attr=false;
				}
			}
			
			$nodes = $xPath->query($xpath_q);
			
			$multiStr='';
			$is_loop=false;
			if(!empty($field_params['xpath_multi'])){
				
				$is_loop='loop'==$field_params['xpath_multi_type']?true:false;
				if($is_loop){




					$vals=array();
				}else{
					
					$multiStr=str_replace(array('\r','\n'), array("\r","\n"), $field_params['xpath_multi_str']);
				}
			}
			
			$curI=0;
			foreach ($nodes as $node){
				$curI++;
				$val=($curI<=1?'':$multiStr);
				if($normal_attr){
					
					$val.=$node->nodeValue;
				}else{
					
					switch ($xpath_attr){
						case 'innerhtml':
							$nchilds  = $node->childNodes;
							foreach ($nchilds as $nchild){
								$val .= $nchild->ownerDocument->saveHTML($nchild);
							}
							break;
						case 'outerhtml':$val.=$node->ownerDocument->saveHTML($node);break;
						case 'text':
							
							
							$nchilds  = $node->childNodes;
							foreach ($nchilds as $nchild){
								$val .= $nchild->ownerDocument->saveHTML($nchild);
							}
							$val=$this->filter_html_tags($val, array('style','script','object'));
							$val=strip_tags($val);
							break;
					}
				}

				if($is_loop){
					
					$vals[]=$val;
				}else{
					$vals.=$val;
				}
				
				if(empty($field_params['xpath_multi'])){
					
					break;
				}
			}

			libxml_clear_errors();
			
		}
		return $vals;
	}
	
	/*自动获取*/
	public function field_module_auto($field_params,&$html,$cur_url){
		switch (strtolower($field_params['auto'])){
			case 'title':$val=$this->get_title($html);break;
			case 'content':$val=$this->get_content($html);break;
			case 'keywords':$val=$this->get_keywords($html);break;
			case 'description':$val=$this->get_description($html);break;
			case 'url':$val=$cur_url;break;
		}
		return $val;
	}
	public function field_module_words($field_params){
		
		return $field_params['words'];
	}
	public function field_module_num($field_params){
		
		$start=intval($field_params['num_start']);
		$end=intval($field_params['num_end']);
		return rand($start, $end);
	}
	public function field_module_time($field_params){
		$val='';
		$start=empty($field_params['time_start'])?NOW_TIME:strtotime($field_params['time_start']);
		$end=empty($field_params['time_end'])?NOW_TIME:strtotime($field_params['time_end']);
		$time=rand($start, $end);
		if(empty($field_params['time_stamp'])){
			
			$fmt=empty($field_params['time_format'])?'Y-m-d H:i':
			str_replace(array('[年]','[月]','[日]','[时]','[分]','[秒]'), array('Y','m','d','H','i','s'), $field_params['time_format']);
			$val=date($fmt,$time);
		}else{
			$val=$time;
		}
		return $val;
	}
	public function field_module_list($field_params){
		static $list=array();
		$key=md5($field_params['list']);
		if(!isset($list[$key])){
			
			if(preg_match_all('/[^\r\n]+/', $field_params['list'],$str_list)){
				$str_list=$str_list[0];
			}else{
				$str_list=array();
			}
			$list[$key]=$str_list;
		}
		$str_list=$list[$key];
		$val='';
		if(!empty($str_list)){
			$randi=array_rand($str_list,1);
			$val=$str_list[$randi];
		}
		return $val;
	}
	public function field_module_merge($field_params,$val_list){
		$val='';
		
		if(preg_match_all('/\[\x{5b57}\x{6bb5}\:(.+?)\]/u', $field_params['merge'],$match_fields)){
			$val=$field_params['merge'];
			
			for($i=0;$i<count($match_fields[0]);$i++){
				$val=str_replace($match_fields[0][$i],$val_list[$match_fields[1][$i]]['value'],$val);
			}
		}
		return $val;
	}
	
	/**
	 * json提取，方法可调用，$field_params传入规则参数
	 * @param array $field_params 
	 * @param string $html
	 * @return string
	 */
	public function field_module_json($field_params,$html,$cur_url=''){
		static $jsonList=array();
		$jsonKey=!empty($cur_url)?md5($cur_url):md5($html);
		if(!isset($jsonList[$jsonKey])){
			$jsonList[$jsonKey]=convert_html2json($html);
		}
		$jsonArrType=$field_params['json_arr'];
		if($field_params['json_loop']){
			
			$field_params['json_arr']='_original_';
		}
		$val=$this->rule_module_json_data($field_params,$jsonList[$jsonKey]);
		if($field_params['json_loop']){
			
			if(is_array($val)){
				$field_params['json_arr']=$jsonArrType;
				foreach ($val as $k=>$v){
					$val[$k]=$this->rule_module_json_data_convert($v,$field_params);
				}

				if(empty($this->first_loop_field)){
					
					$this->first_loop_field=$field_params['name'];
				}
			}
		}
		return $val;
	}
	public function rule_module_json_data($field_params,$jsonArrOrStr){
		$jsonArr=array();
		if(is_array($jsonArrOrStr)){
			$jsonArr=&$jsonArrOrStr;
		}else{
			
			$jsonArr=convert_html2json($jsonArrOrStr);
			unset($jsonArrOrStr);
		}
		$val='';
		if(!empty($jsonArr)){
			if(!empty($field_params['json'])){
				
				$jsonFmt=str_replace(array('"',"'",'[',' '), '', $field_params['json']);
				$jsonFmt=str_replace(']','.',$jsonFmt);
				$jsonFmt=trim($jsonFmt,'.');
				$jsonFmt=explode('.', $jsonFmt);
				$jsonFmt=array_values($jsonFmt);
				if(!empty($jsonFmt)){
					
					$val=$jsonArr;
					$prevKey='';
					foreach ($jsonFmt as $i=>$key){
						if($prevKey=='*'){
							
							$new_field_params=$field_params;
							$new_field_params['json']=array_slice($jsonFmt, $i);
							$new_field_params['json']=implode('.', $new_field_params['json']);
							
							foreach ($val as $vk=>$vv){
								
								$val[$vk]=$this->rule_module_json_data($new_field_params,$vv);
							}
							break;
						}else{
							if($key!='*'){
								
								$val=$val[$key];
							}
						}
						$prevKey=$key;
					}
				}
			}
		}
		
		return $this->rule_module_json_data_convert($val, $field_params);
	}
	public function rule_module_json_data_convert($val,$field_params){
		if(is_array($val)){
			
			$json_arr=strtolower($field_params['json_arr']);
			if(empty($json_arr)){
				$json_arr='implode';
			}
			switch ($json_arr){
				case 'implode':$arrImplode=str_replace(array('\r','\n'), array("\r","\n"), $field_params['json_arr_implode']);$val=array_implode($arrImplode,$val);break;
				case 'jsonencode':$val=json_encode($val);break;
				case 'serialize':$val=serialize($val);break;
				case '_original_': break;
			}
		}
		return $val;
	}
	
	/*字段提取内容*/
	public function field_module_extract($field_params,$extract_field_val,$base_url,$domain_url){
		$field_html=$extract_field_val['value'];
		if(empty($field_html)){
			return '';
		}
		$val='';
		$extract_module=strtolower($field_params['extract_module']);
		switch ($extract_module){
			case 'cover':
				
				if(!empty($extract_field_val['img'])){
					$val=reset($extract_field_val['img']);
				}else{
					if(preg_match('/<img[^<>]*\bsrc=[\'\"](?P<url>[^\'\"]+?)[\'\"]/i',$field_html,$cover)){
						$cover=$cover['url'];
						$cover=$this->create_complete_url($cover, $base_url, $domain_url);
						$val=$cover;
					}
				}
				break;
			case 'phone':
				
				$field_html=$this->filter_html_tags($field_html,'style,script,object');
				$field_html=strip_tags($field_html);
				if(preg_match('/\d{11}/', $field_html,$phone)){
					$val=$phone[0];
				}
				break;
			case 'email':
				$field_html=$this->filter_html_tags($field_html,'style,script,object');
				$field_html=strip_tags($field_html);
				if(preg_match('/[\w\-]+\@[\w\-\.]+/i', $field_html,$email)){
					$val=$email[0];
				}
				break;
			case 'rule':
				
				$val=$this->field_module_rule(array('reg_rule'=>$field_params['reg_extract_rule']), $field_html);
				if(empty($val)){
					
					if(preg_match('/'.$field_params['reg_extract_rule'].'/i', $field_html,$val)){
						$val=$val[0];
					}
				}
				break;
			case 'xpath':
				$val=$this->field_module_xpath(array('xpath'=>$field_params['extract_xpath'],'xpath_attr'=>$field_params['extract_xpath_attr'],'xpath_attr_custom'=>$field_params['extract_xpath_attr_custom']), $field_html);
				break;
			case 'json':
				$val=$this->field_module_json(array('json'=>$field_params['extract_json'],'json_arr'=>$field_params['extract_json_arr'],'json_arr_implode'=>$field_params['extract_json_arr_implode']), $field_html);
				break;
		}
		return $val;
	}
	/*数据处理方法*/
	public function process_f_html($fieldVal,$params){
		$htmlAllow=array_filter(explode(',',$params['html_allow']));
		$htmlFilter=array_filter(explode(',',$params['html_filter']));
		if(!empty($htmlAllow)){
			
			$htmlAllowStr='';
			foreach ($htmlAllow as $v){
				$htmlAllowStr.='<'.$v.'>';
			}
			$fieldVal=strip_tags($fieldVal,$htmlAllowStr);
		}
		if(!empty($htmlFilter)){
			
			if(in_array('all', $htmlFilter)){
				
				$fieldVal=$this->filter_html_tags($fieldVal, array('style','script','object'));
				$fieldVal=strip_tags($fieldVal);
			}else{
				$fieldVal=$this->filter_html_tags($fieldVal, $htmlFilter);
			}
		}
		return $fieldVal;
	}
	public function process_f_replace($fieldVal,$params){
		return preg_replace('/'.$params['replace_from'].'/ui',$params['replace_to'], $fieldVal);
	}
	public function process_f_tool($fieldVal,$params){
		
		if(in_array('format', $params['tool_list'])){
			
			$fieldVal=$this->filter_html_tags($fieldVal,array('style','script'));
			$fieldVal=preg_replace('/\b(id|class|style|width|height|align)\s*=\s*([\'\"])[^\<\>\'\"]+?\\2(?=\s|$|\/|>)/i', ' ', $fieldVal);
		}
		if(in_array('trim', $params['tool_list'])){
			
			$fieldVal=trim($fieldVal);
		}
		if(in_array('is_img', $params['tool_list'])){
			
			if(!empty($GLOBALS['_sc']['c']['download_img']['download_img'])){
				
				$fieldVal=preg_replace('/(?<![\'\"])(\bhttp[s]{0,1}\:\/\/[^\s\'\"\<\>]+)(?![\'\"])/i','{[img]}'."$1".'{[/img]}',$fieldVal);
			}
		}
		return $fieldVal;
	}
	public function process_f_translate($fieldVal,$params){
		
		if(!empty($GLOBALS['_sc']['c']['translate'])&&!empty($GLOBALS['_sc']['c']['translate']['open'])){
			
			$fieldVal=\util\Translator::translate($fieldVal, $params['translate_from'], $params['translate_to']);
		}
		return $fieldVal;
	}
	public function process_f_batch($fieldVal,$params){
		
		static $batch_list=array();
		if(!empty($params['batch_list'])){
			$listMd5=md5($params['batch_list']);
			if(!isset($batch_list[$listMd5])){
				
				if(preg_match_all('/([^\r\n]+?)\=([^\r\n]+)/', $params['batch_list'],$mlist)){
					$batch_re=$mlist[1];
					$batch_to=$mlist[2];
					$batch_list[$listMd5]=array($batch_re,$batch_to);
				}
			}else{
				$batch_re=$batch_list[$listMd5][0];
				$batch_to=$batch_list[$listMd5][1];
			}
			$batch_re=is_array($batch_re)?$batch_re:array();
			$batch_to=is_array($batch_to)?$batch_to:array();
			if(!empty($batch_re)&&count($batch_re)==count($batch_to)){
				
				$fieldVal=str_replace($batch_re, $batch_to, $fieldVal);
			}
		}
		return $fieldVal;
	}
	public function process_f_substr($fieldVal,$params){
		$params['substr_len']=intval($params['substr_len']);
		if($params['substr_len']>0){
			if(mb_strlen($fieldVal,'utf-8')>$params['substr_len']){
				
				$fieldVal=mb_substr($fieldVal,0,$params['substr_len'],'utf-8').$params['substr_end'];
			}
		}
		return $fieldVal;
	}
	public function process_f_func($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5){
		
		$field_val_list=null;
		if(preg_match('/\[\x{5b57}\x{6bb5}\:(.+?)\]/u',$params['func_param'])){
			
			if(empty($this->first_loop_field)){
				
				$field_val_list=array();
				foreach ($this->field_val_list as $k=>$v){
					$field_val_list[$k]=$v['values'][$curUrlMd5];
				}
			}else{
				
				$field_val_list=array();
				
				foreach ($this->field_val_list as $k=>$v){
					$field_val_list[$k]=is_array($v['values'][$curUrlMd5])?$v['values'][$curUrlMd5][$loopIndex]:$v['values'][$curUrlMd5];
				}
			}
		}
		
		$result=$this->execute_plugin_func('process', $params['func_name'], $fieldVal, $params['func_param'], $field_val_list);
		if(isset($result)){
			$fieldVal=$result;
		}
		return $fieldVal;
	}
	public function process_f_filter($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5){
		static $key_list=array();
		if(!empty($params['filter_list'])){
			$listMd5=md5($params['filter_list']);
			if(!isset($key_list[$listMd5])){
				$filterList=explode("\r\n", $params['filter_list']);
				$filterList=array_filter($filterList);
				$key_list[$listMd5]=$filterList;
			}else{
				$filterList=$key_list[$listMd5];
			}
			$filterList=is_array($filterList)?$filterList:array();
			
			
			if(!empty($params['filter_pass'])){
				if($params['filter_pass']=='1'){
					
					foreach ($filterList as $filterStr){
						if(stripos($fieldVal,$filterStr)!==false){
							
							$fieldVal='';
							break;
						}
					}
				}elseif($params['filter_pass']=='2'){
					
					foreach ($filterList as $filterStr){
						if(stripos($fieldVal,$filterStr)!==false){
							
							if(!isset($this->exclude_cont_urls[$contUrlMd5])){
								$this->exclude_cont_urls[$contUrlMd5]=array();
							}
							
							if(empty($this->first_loop_field)){
								
								$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]='filter:'.$filterStr;
							}else{
								
								if(!isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
									$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=array();
								}
								$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex]='filter:'.$filterStr;
							}
							break;
						}
					}
				}elseif($params['filter_pass']=='3'){
					
					$hasKey=false;
					foreach ($filterList as $filterStr){
						if(stripos($fieldVal,$filterStr)!==false){
							
							$hasKey=true;
							break;
						}
					}
					if(!$hasKey){
						$fieldVal='';
					}
				}elseif($params['filter_pass']=='4'){
					
					$hasKey=false;
					foreach ($filterList as $filterStr){
						if(stripos($fieldVal,$filterStr)!==false){
							
							$hasKey=true;
							break;
						}
					}
					if(!$hasKey){
						
						if(!isset($this->exclude_cont_urls[$contUrlMd5])){
							$this->exclude_cont_urls[$contUrlMd5]=array();
						}
						
						if(empty($this->first_loop_field)){
							
							$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]='filter:';
						}else{
							
							if(!isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
								$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=array();
							}
							$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex]='filter:';
						}
					}
				}
			}else{
				
				$fieldVal=str_ireplace($filterList, $params['filter_replace'], $fieldVal);
			}
		}
		return $fieldVal;
	}
	public function process_f_if($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5){
		static $func_list=array();
		
		if(is_array($params['if_logic'])&&!empty($params['if_logic'])){
			
			$resultOr=array();
			$resultAnd=array();
			foreach($params['if_logic'] as $ifk=>$iflv){
				if(empty($iflv)||empty($params['if_cond'][$ifk])){
					
					continue;
				}
				$ifVal=$params['if_val'][$ifk];
				$ifCond=$params['if_cond'][$ifk];
				$result=false;
				switch($ifCond){
					case 'regexp':
						if(preg_match('/'.$ifVal.'/', $fieldVal)){
							$result=true;
						}
						break;
					case 'func':
						$funcName=$params['if_addon']['func'][$ifk];
						$isTurn=$params['if_addon']['turn'][$ifk];
						$isTurn=$isTurn?true:false;
						
						$result=$this->execute_plugin_func('processIf', $funcName, $fieldVal, $ifVal);
						$result=$result?true:false;
						if($isTurn){
							$result=$result?false:true;
						}
						
						break;
					case 'has':$result=stripos($fieldVal,$ifVal)!==false?true:false;break;
					case 'nhas':$result=stripos($fieldVal,$ifVal)===false?true:false;break;
					case 'eq':$result=$fieldVal==$ifVal?true:false;break;
					case 'neq':$result=$fieldVal!=$ifVal?true:false;break;
					case 'heq':$result=$fieldVal===$ifVal?true:false;break;
					case 'nheq':$result=$fieldVal!==$ifVal?true:false;break;
					case 'gt':$result=$fieldVal>$ifVal?true:false;break;
					case 'egt':$result=$fieldVal>=$ifVal?true:false;break;
					case 'lt':$result=$fieldVal<$ifVal?true:false;break;
					case 'elt':$result=$fieldVal<=$ifVal?true:false;break;
					case 'time_eq':
					case 'time_egt':
					case 'time_elt':
						$fieldTime=is_numeric($fieldVal)?$fieldVal:strtotime($fieldVal);
						$valTime=is_numeric($ifVal)?$ifVal:strtotime($ifVal);
						if($ifCond=='time_eq'){
							
							$result=$fieldTime==$valTime?true:false;
						}elseif($ifCond=='time_egt'){
							
							$result=$fieldTime>=$valTime?true:false;
						}elseif($ifCond=='time_elt'){
							
							$result=$fieldTime<=$valTime?true:false;
						}
						break;
				}
				if('or'==$iflv){
					if(!empty($resultAnd)){
						
						$resultOr[]=$resultAnd;
					}
					$resultAnd=array();
					$resultOr[]=$result;
				}elseif('and'==$iflv){
					
					$resultAnd[]=$result;
				}
			}
			if(!empty($resultAnd)){
				
				$resultOr[]=$resultAnd;
			}
			if(is_array($resultOr)&&!empty($resultOr)){
				$isTrue=false;
				foreach ($resultOr as $results){
					if(is_array($results)){
						
						$andResult=true;
						foreach ($results as $result){
							if(!$result){
								
								$andResult=false;
								break;
							}
						}
						$results=$andResult;
					}
					if($results){
						
						$isTrue=true;
						break;
					}
				}
		
				$exclude='';
		
				switch ($params['if_type']){
					case '1':$exclude=$isTrue?'':'if:1';break;
					case '2':$exclude=$isTrue?'if:2':'';break;
					case '3':$exclude=!$isTrue?'':'if:3';break;
					case '4':$exclude=!$isTrue?'if:4':'';break;
				}
		
				if($exclude){
					
					if(!isset($this->exclude_cont_urls[$contUrlMd5])){
						$this->exclude_cont_urls[$contUrlMd5]=array();
					}
					
					if(empty($this->first_loop_field)){
						
						$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=$exclude;
					}else{
						
						if(!isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
							$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=array();
						}
						$this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex]=$exclude;
					}
				}
			}
		}
		return $fieldVal;
	}
	/*调用接口*/
	public function process_f_api($fieldVal,$params){
		$url=$params['api_url'];
		$result=null;
		if(!empty($url)){
			$isLoc=false;
			if(!preg_match('/^\w+\:\/\//', $url)&&strpos($url, '/')===0){
				
				$isLoc=true;
				$url=config('root_website').$url;
			}
			if(preg_match('/^\w+\:\/\//', $url)){
				
				$postData=array();
				if(is_array($params['api_params'])){
					foreach ($params['api_params']['name'] as $k=>$v){
						if(empty($v)){
							continue;
						}
						$val=$params['api_params']['val'][$k];
						$addon=$params['api_params']['addon'][$k];
						switch ($val){
							case 'field':$val=$fieldVal;break;
							case 'timestamp':$val=time();break;
							case 'time':$addon=$addon?$addon:'Y-m-d H:i:s';$val=date($addon,time());break;
							case 'custom':$val=$addon;break;
						}
						$postData[$v]=$val;
					}
				}
				if($params['api_type']=='post'){
					
					$postData=empty($postData)?true:$postData;
				}else{
					
					if($postData){
						$url.=(strpos($url,'?')===false?'?':'&').http_build_query($postData);
					}
					$postData=null;
				}
				if($isLoc){
					
					$result=get_html($url,null,array(),'utf-8',$postData);
				}else{
					
					$result=$this->get_html($url,false,$postData);
				}
				if(isset($result)){
					$fieldVal=$this->rule_module_json_data(array('json'=>$params['api_json'],'json_arr'=>$params['api_json_arr'],'json_arr_implode'=>$params['api_json_implode']),$result);
				}
			}
		}
		return $fieldVal;
	}
	/*数据处理*/
	public function process_field($fieldVal,$process,$curUrlMd5,$loopIndex,$contUrlMd5){
		if(empty($process)){
			return $fieldVal;
		}
		static $funcs=array('func','filter','if');
		foreach ($process as $params){
			
			if(empty($this->first_loop_field)){
				
				if(isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
					return $fieldVal;
				}
			}else{
				
				if(isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex])){
					return $fieldVal;
				}
			}
			$funcName='process_f_'.$params['module'];
			if(method_exists($this, $funcName)){
				if(in_array($params['module'],$funcs)){
					$fieldVal=$this->$funcName($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5);
				}else{
					$fieldVal=$this->$funcName($fieldVal,$params);
				}
			}
		}
		return $fieldVal;
	}
	
	
	/**
	 * 拼接默认设置
	 * @param unknown $reg 规则
	 * @param unknown $merge 拼接字符串
	 */
	public function set_merge_default($reg,$merge){
		if(empty($merge)){
			$merge='';
			if(!empty($reg)){
				
				if(preg_match_all('/\<match(?P<num>\d*)\>/i', $reg,$match_signs)){
					foreach ($match_signs['num'] as $snum){
						$merge.=cp_sign('match',$snum);
					}
				}
			}
		}
		return $merge;
	}
	/**
	 * 转换起始网址
	 * @param string $url
	 * @return multitype:mixed |unknown
	 */
	public function convert_source_url($url){
		$urls=array();
		if(preg_match('/\{param\:(?P<type>[a-z]+)\,(?P<val>.*?)\}/i', $url,$match)){
			
			$fmtUrl=preg_replace('/\{param\:.*?\}/i', '__set:param__', $url);
			$type=strtolower($match['type']);
			$val=explode("\t", $match['val']);
			if($type=='num'){
				
				$num_start = intval($val[0]);
				$num_end = intval($val[1]);
				$num_end = max ($num_start,$num_end);
				$num_inc = max ( 1, intval($val[2]));
				$num_desc =$val[3]?1:0;
	
				if($num_desc){
					
					for($i=$num_end;$i>=$num_start;$i--){
						$urls[]=str_replace('__set:param__', $num_start+($i-$num_start)*$num_inc, $fmtUrl);
					}
				}else{
					for($i=$num_start;$i<=$num_end;$i++){
						$urls[]=str_replace('__set:param__', $num_start+($i-$num_start)*$num_inc, $fmtUrl);
					}
				}
			}elseif($type=='letter'){
				
				$letter_start=ord($val[0]);
				$letter_end=ord($val[1]);
				$letter_end=max($letter_start,$letter_end);
				$letter_desc=$val[2]?1:0;
	
				if($letter_desc){
					
					for($i=$letter_end;$i>=$letter_start;$i--) {
						$urls[]=str_replace('__set:param__', chr($i), $fmtUrl);
					}
				}else{
					for($i=$letter_start;$i<=$letter_end;$i++) {
						$urls[]=str_replace('__set:param__', chr($i), $fmtUrl);
					}
				}
			}elseif($type=='custom'){
				
				foreach ($val as $v){
					$urls[]=str_replace('__set:param__', $v, $fmtUrl);
				}
			}
			return $urls;
		}if(preg_match('/\{json\:([^\}]*)\}/i',$url,$match)){
			
			$url=preg_replace('/\{json\:([^\}]*)\}/i','',$url);
			$jsonRule=trim($match[1]);
			if(is_null($jsonRule)||$jsonRule==''){
				$jsonRule='*';
			}
			$jsonData=$this->get_html($url);
			if(!empty($jsonData)){
				
				$urls=$this->rule_module_json_data(array('json'=>$jsonRule,'json_arr'=>'_original_'),$jsonData);
				if(empty($urls)){
					$urls=array();
				}
				if(!is_array($urls)){
					$urls=array($urls);
				}
				
				foreach ($urls as $k=>$v){
					if(!is_string($v)||!preg_match('/^\w+\:\/\//i', $v)){
						
						unset($urls[$k]);
					}
				}
				if(!empty($urls)&&is_array($urls)){
					$urls=array_unique($urls);
					$urls=array_values($urls);
				}
				return $urls;
			}
		}elseif(preg_match('/[\r\n]/', $url)){
			
			if(preg_match_all('/^\w+\:\/\/[^\r\n]+/im',$url,$urls)){
				
				$urls=array_unique($urls[0]);
				$urls=array_values($urls);
			}else{
				$urls=array();
			}
			return $urls;
		}else{
			
			return $url;
		}
	}
	/*排除内容网址的提示信息*/
	public function exclude_url_msg($val){
		$val=explode(':', $val);
		$type='';
		if(is_array($val)){
			$type=$val[0];
			$val=$val[1];
		}else{
			$type=$val;
			$val='';
		}
		$msg='排除网址';
		if($type=='filter'){
			
			if(empty($val)){
				$msg='关键词过滤';
			}else{
				$msg='关键词过滤：'.$val;
			}
		}elseif($type=='if'){
			$msg='条件';
			
			switch ($val){
				case '1':$msg.='假';break;
				case '2':$msg.='真';break;
				case '3':$msg.='假';break;
				case '4':$msg.='真';break;
			}
			if(lang('?p_m_if_'.$val)){
				$msg.='：'.lang('p_m_if_'.$val);
			}
		}
		return $msg;
	}
	
	/*转换(*)通配符*/
	public function convert_sign_wildcard($str){
		return str_replace(lang('sign_wildcard'), '[\s\S]*?', $str);
	}
	/*转换[参数]*/
	public function convert_sign_match($str){
		$str=preg_replace('/\(\?<(content|match)/i', '(?P<match', $str);
		$sign_match=$this->sign_addslashes(cp_sign('match','(?P<num>\d*)'));
		$str=preg_replace_callback('/(\={0,1})(\s*)([\'\"]{0,1})'.$sign_match.'\3/', function($matches){
			$ruleStr=$matches[1].$matches[2].$matches[3].'(?P<match'.$matches['num'].'>';
			if(!empty($matches[1])&&!empty($matches[3])){
				
				$ruleStr.='[^\<\>]*?)';
			}else{
				$ruleStr.='[\s\S]*?)';
			}
			$ruleStr.=$matches[3];
			return $ruleStr;
		}, $str);
		return $str;
	}
	public function sign_addslashes($str){
		$str=str_replace(array('[',']'), array('\[','\]'), $str);
		return $str;
	}
	/*过滤html标签*/
	public function filter_html_tags($content,$tags){
		$tags=$this->clear_tags($tags);
		$arr1=$arr2=array();
		foreach ($tags as $tag){
			$tag=strtolower($tag);
			if($tag=='script'||$tag=='style'||$tag=='object'){
				$arr1[$tag]=$tag;
			}else{
				$arr2[$tag]=$tag;
			}
		}
	
		if($arr1){
			$content=preg_replace('/<('.implode('|', $arr1).')[^<>]*>[\s\S]*?<\/\1>/i', '', $content);
		}
	
		if($arr2){
			$content=preg_replace('/<[\/]*('.implode('|', $arr2).')[^<>]*>/i', '', $content);
		}
		return $content;
	}
	/*过滤标签*/
	public function clear_tags($tags){
		if(!is_array($tags)){
			$tags = preg_replace('/[\s\,\x{ff0c}]+/u', ',', $tags);
			$tags=explode(',', $tags);
		}
		if(!empty($tags)&&is_array($tags)){
			
			$tags=array_filter($tags);
			$tags=array_unique($tags);
			$tags=array_values($tags);
		}else{
			$tags=array();
		}
		return $tags;
	}
	/**
	 * 执行数据处理》使用函数
	 * @param string $module 模块
	 * @param string $funcName 函数/方法
	 * @param string $fieldVal 字段值
	 * @param string $paramsStr 输入的参数（有换行符）
	 * @param array $fieldValList 所有字段值（调用字段时使用）
	 */
	public function execute_plugin_func($module,$funcName,$fieldVal,$paramsStr,$fieldValList=null){
		
		static $func_class_list=array('process'=>array(),'processIf'=>array());
		$class_list=&$func_class_list[$module];
	
		$options = array (
			'process' => array (
				'name' => '使用函数',
				'config'=>'allow_process_func',
				'extend'=>'EXTEND_PROCESS_FUNC',
			),
			'processIf' => array (
				'name' => '条件判断》使用函数',
				'config'=>'allow_process_if',
				'extend'=>'EXTEND_PROCESS_IF',
			)
		);
		$options=$options[$module];
		$result=null;
		if(!empty($funcName)){
			$success=false;
			if(strpos($funcName, ':')!==false){
				$funcName=explode(':', $funcName);
			}
			if(!is_array($funcName)){
				
				if(!function_exists($funcName)){
					
					$this->error('数据处理》'.$options['name'].'》无效的函数：'.$funcName);
				}elseif(!array_key_exists($funcName, config($options['config']))&&!array_key_exists($funcName, config($options['extend']))){
					
					$this->error('数据处理》'.$options['name'].'》未配置函数：'.$funcName);
				}else{
					$success=true;
				}
			}else{
				
				$className=$funcName[0];
				$methodName=$funcName[1];
				if(!isset($class_list[$className])){
					
					$enable=model('FuncApp')->field('enable')->where(array('app'=>$className,'module'=>$module))->value('enable');
					if($enable){
						
						$class=model('FuncApp')->app_classname($module,$className);
						if(!class_exists($class)){
							
							$class_list[$className]=1;
						}else{
							$class=new $class();
							$class_list[$className]=$class;
						}
					}else{
						$class_list[$className]=2;
					}
				}
				if(is_object($class_list[$className])){
					
					if(!method_exists($class_list[$className], $methodName)){
						$this->error('数据处理》'.$options['name'].'》不存在方法：'.$className.'-&gt;'.$methodName);
					}else{
						$success=true;
					}
				}else{
					$msg='数据处理》'.$options['name'].'》';
					if($class_list[$className]==1){
						$msg.='不存在插件：';
					}elseif($class_list[$className]==2){
						$msg.='已禁用插件：';
					}else{
						$msg.='无效的插件：';
					}
					$this->error($msg.$className);
				}
			}
				
			if($success){
				static $func_param_list=array();
				$funcParam=null;
				if(empty($paramsStr)){
					
					$funcParam=array($fieldVal);
				}else{
					$fparamMd5=md5($paramsStr);
					if(!isset($func_param_list[$fparamMd5])){
						if(preg_match_all('/[^\r\n]+/',$paramsStr,$mfuncParam)){
							$func_param_list[$fparamMd5]=$mfuncParam[0];
						}
					}
					$funcParam=$func_param_list[$fparamMd5];
					if($funcParam){
						foreach ($funcParam as $k=>$v){
							$v=str_replace('###', $fieldVal, $v);
							
							if($fieldValList&&preg_match_all('/\[\x{5b57}\x{6bb5}\:(.+?)\]/u',$v,$match_fields)){
								for($i=0;$i<count($match_fields[0]);$i++){
									$v=str_replace($match_fields[0][$i],$fieldValList[$match_fields[1][$i]],$v);
								}
							}
							$funcParam[$k]=$v;
						}
					}
				}
				if(!empty($funcParam)&&is_array($funcParam)){
					try {
						if(!is_array($funcName)){
							
							$result=call_user_func_array($funcName, $funcParam);
						}else{
							
							$result=call_user_func_array(array($class_list[$funcName[0]],$funcName[1]), $funcParam);
						}
					}catch (\Exception $ex){
						
					}
				}
			}
		}
		return $result;
	}
	/**
	 * 获取源码
	 * @param string $url 网址
	 * @param bool $openCache 开启缓存网页数据，$postData有数据时最好关闭，$postData为true根据$url缓存
	 * @param bool|array $postData 开启post模式或者传递post数组，true会将$url中的get参数转换成post数组
	 */
	public function get_html($url,$openCache=false,$postData=false){
		if(!empty($GLOBALS['_sc']['c']['caiji']['robots'])){
			
			if(!model('Collector')->abide_by_robots($url)){
				$this->error('robots拒绝访问的网址：'.$url);
				return null;
			}
		}
		
		$urlMd5=md5($url);
		if($openCache&&!empty($this->html_cache_list[$urlMd5])){
			
			return $this->html_cache_list[$urlMd5];
		}
		$is_post=$postData?true:false;
		
		$pageRenderTool=null;
		if($this->config['page_render']){
			$pageRenderTool=$GLOBALS['_sc']['c']['page_render']['tool'];
			if(empty($pageRenderTool)){
				
				$this->error('页面渲染未设置，请检查<a href="'.url('Setting/page_render').'" target="_blank">渲染设置</a>','Setting/page_render');
				return null;
			}
		}
		
		$html=null;
		$headers=array();
		$options=array();
		
		if(!empty($GLOBALS['_sc']['task_request_headers'])){
			
			$headers=$GLOBALS['_sc']['task_request_headers']['headers'];
			if(!empty($headers['useragent'])){
				
				$options['useragent']=$headers['useragent'];
			}
			unset($headers['useragent']);
		}
		
		$mproxy=model('Proxyip');
		$proxyDbIp=null;
		if(!empty($GLOBALS['_sc']['c']['proxy']['open'])){
			
			$proxyDbIp=$mproxy->get_usable_ip();
			$proxyIp=$mproxy->to_proxy_ip($proxyDbIp);
			
			if(!empty($proxyIp)){
				
				$options['proxy']=$proxyIp;
			}
		}
		$urlPost=null;
		if($is_post){
			
			$urlPost=strpos($url, '?');
			if($urlPost!==false){
				$urlPost=substr($url, $urlPost+1);
				$url=preg_replace('/\?.*$/', '', $url);
			}else{
				$urlPost='';
			}
			if(is_string($postData)){
				
				if($urlPost){
					$urlPost.=$postData?('&'.$postData):'';
				}else{
					$urlPost=$postData;
				}
			}elseif(is_array($postData)){
				
				if($urlPost){
					if(preg_match_all('/([^\&]+?)\=([^\&]*)/',$urlPost,$mUrlPost)){
						$urlPostData=array();
						foreach($mUrlPost[1] as $k=>$v){
							$urlPostData[$v]=rawurldecode($mUrlPost[2][$k]);
						}
						$urlPost=$urlPostData;
						unset($urlPostData);
					}
				}
				$urlPost=is_array($urlPost)?$urlPost:array();
				$urlPost=array_merge($urlPost,$postData);
				unset($postData);
			}
		}
		if($pageRenderTool){
			
			if(!empty($options['useragent'])){
				
				$headers['user-agent']=$options['useragent'];
				unset($options['useragent']);
			}
			
			if($pageRenderTool=='chrome'){
				$chromeConfig=$GLOBALS['_sc']['c']['page_render']['chrome'];
				try {
					$chromeSocket=new \util\ChromeSocket($chromeConfig['host'],$chromeConfig['port'],$GLOBALS['_sc']['c']['page_render']['timeout'],$chromeConfig['filename']);
					$chromeSocket->newTab();
					$chromeSocket->websocket(null);
					if($is_post){
						
						$html=$chromeSocket->getRenderHtml($url,$headers,$options,$this->config['charset'],$urlPost);
					}else{
						$html=$chromeSocket->getRenderHtml($url,$headers,$options);
					}
				}catch (\Exception $ex){
					$this->error('页面渲染失败，请检查<a href="'.url('Setting/page_render').'" target="_blank">渲染设置</a>','Setting/page_render');
					return null;
				}
			}else{
				$this->error('渲染工具不可用，请检查<a href="'.url('Setting/page_render').'" target="_blank">渲染设置</a>','Setting/page_render');
				return null;
			}
		}else{
			if($is_post){
				$urlPost=$urlPost?$urlPost:'';
			}else{
				$urlPost=null;
			}
			$html=get_html($url,$headers,$options,$this->config['charset'],$urlPost);
		}
		
		if($html==null){
			
			if(!empty($proxyDbIp)){
				$mproxy->set_ip_failed($proxyDbIp);
			}
			return null;
		}
		
		if($this->config['url_complete']){
			
			$base_url=$this->match_base_url($url, $html);
			$domain_url=$this->match_domain_url($url, $html);
			$html=preg_replace_callback('/(?<=\bhref\=[\'\"])([^\'\"]*)(?=[\'\"])/i',function($matche) use ($base_url,$domain_url){
				
				return \skycaiji\admin\event\Cpattern::create_complete_url($matche[1], $base_url, $domain_url);
			},$html);
			$html=preg_replace_callback('/(?<=\bsrc\=[\'\"])([^\'\"]*)(?=[\'\"])/i',function($matche) use ($base_url,$domain_url){
				return \skycaiji\admin\event\Cpattern::create_complete_url($matche[1], $base_url, $domain_url);
			},$html);
		}
		if($openCache){
			$this->html_cache_list[$urlMd5]=$html;
		}
		return $html;
	}
}
?>