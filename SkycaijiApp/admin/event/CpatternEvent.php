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
class CpatternEvent extends CpatternBase{
    public $collector;
    public $config;
    public $config_params;
    public $release;
    public $first_loop_field=null;
    public $field_val_list=array();
    public $collect_num=0;
    public $collected_field_list=array();
    public $used_source_urls=array();
    public $used_level_urls=array();
    public $used_cont_urls=array();
    public $original_source_urls=null;
    public $cont_urls_list=array();
    public $exclude_cont_urls=array();
    public $relation_url_list=array();
    public $used_paging_urls=array();
    public $cur_level_urls=array();
    public $cur_source_url='';
    public $cur_cont_url='';
    public $page_url_matches=array();
    public $page_area_matches=array();
    public $show_opened_tools=false;
    protected $cache_page_htmls=array();
    protected $cache_page_urls=array();
    
    
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
    /**
     * 规则匹配，$field_params传入规则参数
     * @param array $field_params
     * @param string $html
     * @return string
     */
    public function field_module_rule($field_params,$html){
        if(!empty($field_params['rule_multi'])&&'loop'==$field_params['rule_multi_type']){
            
            if(empty($this->first_loop_field)){
                
                $this->first_loop_field=$field_params['name'];
            }
        }
        
        $val = $this->get_rule_module_rule_data(array(
            'rule' => $field_params['reg_rule'],
            'rule_merge' => $field_params['reg_rule_merge'],
            'rule_multi' => $field_params['rule_multi'],
            'rule_multi_str' => $field_params['rule_multi_str'],
            'rule_multi_type' => $field_params['rule_multi_type']
        ), $html,array(),true);
        
        return $val;
    }
    /**
     * xpath规则，$field_params传入规则参数
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
    /**
     * json提取，$field_params传入规则参数
     * @param array $field_params
     * @param string $html
     * @return string
     */
    public function field_module_json($field_params,$html,$cur_url=''){
        static $jsonList=array();
        $jsonKey=!empty($cur_url)?md5($cur_url):md5($html);
        if(!isset($jsonList[$jsonKey])){
            $jsonList[$jsonKey]=\util\Funcs::convert_html2json($html);
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
                    if(preg_match('/<img\b[^<>]*\bsrc\s*=\s*[\'\"](?P<url>[^\'\"]+?)[\'\"]/i',$field_html,$cover)){
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
                
                $val = $this->field_module_rule(array(
                    'reg_rule'=>$field_params['reg_extract_rule'],
                    'reg_rule_merge'=>$field_params['reg_extract_rule_merge'],
                    'rule_multi'=>$field_params['extract_rule_multi'],
                    'rule_multi_str'=>$field_params['extract_rule_multi_str'],
                ), $field_html);
                
                break;
            case 'xpath':
                $val = $this->field_module_xpath(array(
                    'xpath' => $field_params['extract_xpath'],
                    'xpath_attr' => $field_params['extract_xpath_attr'],
                    'xpath_attr_custom' => $field_params['extract_xpath_attr_custom'],
                    'xpath_multi' => $field_params['extract_xpath_multi'],
                    'xpath_multi_str' => $field_params['extract_xpath_multi_str'],
                ), $field_html);
                break;
            case 'json':
                $val=$this->field_module_json(array('json'=>$field_params['extract_json'],'json_arr'=>$field_params['extract_json_arr'],'json_arr_implode'=>$field_params['extract_json_arr_implode']), $field_html);
                break;
        }
        return $val;
    }
    
    /*[内容]标签*/
    public function field_module_sign($field_params,$cont_url){
        $val='';
        $urlMd5=md5($cont_url);
        $sourceType=$field_params['source'];
        $sourceName='';
        if(preg_match('/^(level_url|relation_url):(.+)$/i', $sourceType,$sourceType)){
            $sourceName=$sourceType[2];
            $sourceType=$sourceType[1];
        }else{
            
            $sourceType='url';
            $sourceName='';
        }
        
        if(!empty($field_params['sign'])&&!empty($this->page_url_matches[$sourceType])){
            $urlMatches=null;
            $areaMatches=$this->get_page_area_match($sourceType,$sourceName);
            if($sourceType=='url'){
                
                $urlMatches=$this->get_page_url_match($sourceType,$sourceName,$urlMd5);
            }elseif($sourceType=='level_url'){
                
                if(!empty($this->cur_level_urls[$sourceName])){
                    $urlMatches=$this->get_page_url_match($sourceType,$sourceName,md5($this->cur_level_urls[$sourceName]));
                }else{
                    $urlMatches=null;
                }
            }elseif($sourceType=='relation_url'){
                
                $urlMatches=$this->get_page_url_match($sourceType,$sourceName);
            }
            if(!is_array($urlMatches)){
                $urlMatches=array();
            }
            if(!is_array($areaMatches)){
                $areaMatches=array();
            }
            
            if(empty($urlMatches)){
                
                $pageSource=$this->convert_to_page_source($sourceType,$sourceName);
                $urlSigns=$this->config_params['signs'][$pageSource]['url']['cur']['url'];
                
                $urlMatches=array();
                if(is_array($urlSigns)){
                    foreach ($urlSigns as $k=>$v){
                        $urlMatches['match'.$v['id']]='';
                    }
                }
            }
            
            $urlMatches=array_merge($areaMatches,$urlMatches);
            $val=$this->merge_match_signs($urlMatches, $field_params['sign']);
        }
        return $val;
    }
    /*自动获取*/
    public function field_module_auto($field_params,$html,$cur_url){
        switch (strtolower($field_params['auto'])){
            case 'title':$val=\util\HtmlParse::getTitle($html);break;
            case 'content':$val=\util\HtmlParse::getContent($html);break;
            case 'keywords':$val=\util\HtmlParse::getKeywords($html);break;
            case 'description':$val=\util\HtmlParse::getDescription($html);break;
            case 'url':$val=$cur_url;break;
            case 'html':$val=$html;break;
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
        $nowTime=time();
        $start=empty($field_params['time_start'])?$nowTime:strtotime($field_params['time_start']);
        $end=empty($field_params['time_end'])?$nowTime:strtotime($field_params['time_end']);
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
    public function process_f_insert($fieldVal,$params){
        $txt=$params['insert_txt'];
        if(empty($params['insert_loc'])){
            $fieldVal.=$txt;
        }elseif($params['insert_loc']=='head'){
            $fieldVal=$txt.$fieldVal;
        }elseif($params['insert_loc']=='rand'){
            $pattern='/<(?:p|br)[^<>]*>/i';
            if(preg_match_all($pattern,$fieldVal,$matches)){
                $count=count($matches[0]);
                $rand=rand(0,$count-1);
                $index=0;
                $fieldVal=preg_replace_callback($pattern, function($match)use($txt,$rand,&$index){
                    $val=$match[0];
                    if($index==$rand){
                        
                        $val.=$txt;
                    }
                    $index++;
                    return $val;
                }, $fieldVal);
            }else{
                $rand=rand(0,1);
                if($rand){
                    
                    $fieldVal=$txt.$fieldVal;
                }else{
                    $fieldVal.=$txt;
                }
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
            
            if(!is_empty(g_sc_c('download_img','download_img'))){
                
                $fieldVal=preg_replace('/(?<![\'\"])(\bhttp[s]{0,1}\:\/\/[^\s\'\"\<\>]+)(?![\'\"])/i','{[img]}'."$1".'{[/img]}',$fieldVal);
            }
        }
        return $fieldVal;
    }
    public function process_f_translate($fieldVal,$params){
        
        static $regEmpty='/^([\s\r\n]|\&nbsp\;)*$/';
        if(!is_empty(g_sc_c('translate'))&&!is_empty(g_sc_c('translate','open'))){
            
            if(!is_empty(g_sc_c('translate','pass_html'))){
                
                $htmlMd5List=array();
                $txtMd5List=array();
                
                
                static $tagRegs=array('/<\![\s\S]*?>/','/<(script|style)[^\r\n]*?>[\s\S]*?<\/\1>/i','/<[\/]*\w+\b[^\r\n]*?>/');
                foreach($tagRegs as $tagReg){
                    $fieldVal=preg_replace_callback($tagReg,function($mhtml)use(&$htmlMd5List){
                        $key='{'.md5($mhtml[0]).'}';
                        $htmlMd5List[$key]=$mhtml[0];
                        return $key;
                    },$fieldVal);
                }
                
                if(empty($htmlMd5List)){
                    
                    if(!empty($fieldVal)&&!preg_match($regEmpty, $fieldVal)){
                        
                        $fieldVal=$this->execute_translate($fieldVal, $params['translate_from'], $params['translate_to']);
                    }
                }else{
                    
                    
                    $fieldVal=preg_replace_callback('/([\s\S]*?)(\{[a-zA-Z0-9]{32}\})/i',function($mtxt)use(&$txtMd5List){
                        $key='['.md5($mtxt[1]).']';
                        $txtMd5List[$key]=$mtxt[1];
                        return $key.$mtxt[2];
                    },$fieldVal);
                        
                        foreach ($txtMd5List as $k=>$v){
                            
                            if(!empty($v)&&!preg_match($regEmpty, $v)){
                                
                                $txtMd5List[$k]=$this->execute_translate($v, $params['translate_from'], $params['translate_to']);
                            }
                        }
                        
                        if(!empty($txtMd5List)){
                            $fieldVal=str_replace(array_keys($txtMd5List), $txtMd5List, $fieldVal);
                        }
                        
                        if(!empty($htmlMd5List)){
                            $fieldVal=str_replace(array_keys($htmlMd5List), $htmlMd5List, $fieldVal);
                        }
                }
            }else{
                
                if(!empty($fieldVal)&&!preg_match($regEmpty, $fieldVal)){
                    
                    $fieldVal=$this->execute_translate($fieldVal, $params['translate_from'], $params['translate_to']);
                }
            }
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
                    $field_val_list['[字段:'.$k.']']=$v['values'][$curUrlMd5];
                }
            }else{
                
                $field_val_list=array();
                
                foreach ($this->field_val_list as $k=>$v){
                    $field_val_list['[字段:'.$k.']']=is_array($v['values'][$curUrlMd5])?$v['values'][$curUrlMd5][$loopIndex]:$v['values'][$curUrlMd5];
                }
            }
        }
        
        $result=$this->execute_plugin_func('process', $params['func_name'], $fieldVal, $params['func_param'], $field_val_list);
        if(isset($result)){
            $fieldVal=$result;
        }
        return $fieldVal;
    }
    public function process_f_filter($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
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
                                
                                $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=json_encode(array('field'=>$fieldName,'type'=>'filter','filter'=>$filterStr));
                            }else{
                                
                                if(!isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
                                    $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=array();
                                }
                                $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex]=json_encode(array('field'=>$fieldName,'type'=>'filter','filter'=>$filterStr));
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
                            
                            $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=json_encode(array('field'=>$fieldName,'type'=>'filter','filter'=>''));
                        }else{
                            
                            if(!isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
                                $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=array();
                            }
                            $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex]=json_encode(array('field'=>$fieldName,'type'=>'filter','filter'=>''));
                        }
                    }
                }
            }else{
                
                $fieldVal=str_ireplace($filterList, $params['filter_replace'], $fieldVal);
            }
        }
        return $fieldVal;
    }
    public function process_f_if($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        static $func_list=array();
        
        if(is_array($params['if_logic'])&&!empty($params['if_logic'])){
            
            $ifOrList=array();
            $ifAndList=array();
            
            foreach($params['if_logic'] as $ifk=>$iflv){
                if('or'==$iflv){
                    if(!empty($ifAndList)){
                        
                        $ifOrList[]=$ifAndList;
                    }
                    $ifAndList=array();
                    $ifAndList[]=$ifk;
                }elseif('and'==$iflv){
                    
                    $ifAndList[]=$ifk;
                }
            }
            if(!empty($ifAndList)){
                
                $ifOrList[]=$ifAndList;
            }
            if(is_array($ifOrList)&&!empty($ifOrList)){
                $isTrue=false;
                $breakCond='';
                
                foreach ($ifOrList as $ifAndList){
                    $ifAndResult=true;
                    foreach ($ifAndList as $ifIndex){
                        $ifLogic=$params['if_logic'][$ifIndex];
                        $ifCond=$params['if_cond'][$ifIndex];
                        if(empty($ifLogic)||empty($ifCond)){
                            
                            continue;
                        }
                        $ifVal=$params['if_val'][$ifIndex];
                        $result=false;
                        $breakCond=lang('p_m_if_c_'.$ifCond).':'.$ifVal;
                        switch($ifCond){
                            case 'regexp':
                                if(preg_match('/'.$ifVal.'/'.$this->config['reg_regexp_flags'], $fieldVal)){
                                    $result=true;
                                }
                                break;
                            case 'func':
                                $funcName=$params['if_addon']['func'][$ifIndex];
                                $isTurn=$params['if_addon']['turn'][$ifIndex];
                                $isTurn=$isTurn?true:false;
                                
                                $result=$this->execute_plugin_func('processIf', $funcName, $fieldVal, $ifVal);
                                $result=$result?true:false;
                                if($isTurn){
                                    $result=$result?false:true;
                                }
                                $breakCond=lang('p_m_if_c_'.$ifCond).':'.$funcName.($isTurn?'取反':'');
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
                        if(!$result){
                            
                            $ifAndResult=false;
                            break;
                        }
                    }
                    
                    if($ifAndResult){
                        
                        $isTrue=true;
                        break;
                    }
                }
                
                $exclude=null;
                
                switch ($params['if_type']){
                    case '1':$exclude=$isTrue?null:array('if'=>'1');break;
                    case '2':$exclude=$isTrue?array('if'=>'2'):null;break;
                    case '3':$exclude=!$isTrue?null:array('if'=>'3');break;
                    case '4':$exclude=!$isTrue?array('if'=>'4'):null;break;
                }
                
                if(!empty($exclude)){
                    $exclude['type']='if';
                    $exclude['field']=$fieldName;
                    $exclude['cond']=$breakCond;
                    $exclude=json_encode($exclude);
                    
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
        static $retryCur=0;
        $retryParams=null;
        $retryMax=intval($params['api_retry']);
        if($retryMax>0){
            
            $retryParams=array('val'=>$fieldVal,'params'=>$params);
        }
        
        $url=$params['api_url'];
        $result=null;
        if(!empty($url)){
            $isLoc=false;
            if(!preg_match('/^\w+\:\/\//', $url)&&strpos($url, '/')===0){
                
                $isLoc=true;
                $url=config('root_website').$url;
            }
            if(preg_match('/^\w+\:\/\//', $url)){
                
                
                
                $charset=$params['api_charset'];
                if($charset=='custom'){
                    $charset=$params['api_charset_custom'];
                }
                if(empty($charset)){
                    $charset='utf-8';
                }
                
                
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
                
                \util\Funcs::filter_key_val_list($params['api_headers']['name'], $params['api_headers']['val']);
                $headers=array();
                foreach ($params['api_headers']['name'] as $k=>$v){
                    if(empty($v)){
                        continue;
                    }
                    $headers[$v]=$params['api_headers']['val'][$k];
                }
                
                if($params['api_type']=='post'){
                    
                    $postData=empty($postData)?true:$postData;
                }else{
                    
                    if($postData){
                        $url.=(strpos($url,'?')===false?'?':'&').http_build_query($postData);
                    }
                    $postData=null;
                }
                
                $result=get_html($url,$headers,array(),$charset,$postData,true);
                $apiInterval=intval($params['api_interval']);
                if($apiInterval>0){
                    
                    usleep($apiInterval*1000);
                }
                if(!empty($result['ok'])){
                    
                    $retryCur=0;
                    $fieldVal=$this->rule_module_json_data(array('json'=>$params['api_json'],'json_arr'=>$params['api_json_arr'],'json_arr_implode'=>$params['api_json_implode']),$result['html']);
                }else{
                    if($retryMax<=0||($retryCur<=0&&is_collecting())){
                        
                        $echoMsg='<div class="clear"><span class="left">数据处理»调用接口失败：</span><a href="'.$url.'" target="_blank" class="lurl">'.$url.'</a></div>';
                        if(!is_collecting()){
                            $echoMsg=strip_tags($echoMsg);
                        }
                        $this->error($echoMsg);
                    }
                    
                    $failedWait=intval($params['api_wait']);
                    if($failedWait>0){
                        sleep($failedWait);
                    }
                    
                    if($retryMax>0&&is_array($retryParams)){
                        
                        if($retryCur<$retryMax){
                            
                            $retryCur++;
                            if(is_collecting()){
                                $this->echo_msg(($retryCur>1?'，':'重试：').'第'.$retryCur.'次','black',true,'','display:inline;');
                            }
                            return $this->process_f_api($retryParams['val'], $retryParams['params']);
                        }else{
                            $retryCur=0;
                            if(is_collecting()){
                                $this->echo_msg('接口无效','red',true,'','display:inline;margin-left:10px;');
                            }else{
                                
                                $this->error('数据处理»调用接口：'.$url.'，已重试'.$retryMax.'次，接口无效 ');
                            }
                        }
                    }
                }
            }
        }
        return $fieldVal;
    }
    /*数据处理*/
    public function process_field($fieldName,$fieldVal,$process,$curUrlMd5,$loopIndex,$contUrlMd5){
        if(empty($process)){
            return $fieldVal;
        }
        static $condFuncs=array('filter','if');
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
                if(in_array($params['module'],$condFuncs)){
                    
                    $fieldVal=$this->$funcName($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName);
                }elseif($params['module']=='func'){
                    $fieldVal=$this->$funcName($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5);
                }else{
                    $fieldVal=$this->$funcName($fieldVal,$params);
                }
            }
        }
        return $fieldVal;
    }
    
    /*正则规则匹配数据*/
    public function get_rule_module_rule_data($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        if(!is_array($configParams)){
            $configParams=array();
        }
        $configParams['rule_flags']=$this->config['reg_regexp_flags'];
        
        return $this->rule_module_rule_data($configParams,$html,$parentMatches,$whole,$returnMatch);
    }
    
    
    /*规则匹配区域*/
    public function rule_match_area($pageType,$config,$html,$returnMatch=false){
        $matches=array();
        
        $parentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$config['name'],'area'));
        
        $doMerge=false;
        if(!empty($config['reg_area'])){
            
            if(empty($config['reg_area_module'])){
                
                $valMatch=$this->get_rule_module_rule_data(array('rule'=>$config['reg_area'],'rule_merge'=>$config['reg_area_merge']),$html,$parentMatches,true,$returnMatch);
                if($returnMatch&&is_array($valMatch)){
                    
                    $html=$valMatch['val'];
                    $matches=$valMatch['matches'];
                }else{
                    $html=$valMatch;
                }
                $doMerge=false;
            }else{
                if('json'==$config['reg_area_module']){
                    
                    $html=$this->rule_module_json_data(array('json'=>$config['reg_area'],'json_arr'=>'jsonencode'),$html);
                }elseif('xpath'==$config['reg_area_module']){
                    
                    $html=$this->rule_module_xpath_data(array('xpath'=>$config['reg_area'],'xpath_attr'=>'outerHtml'),$html);
                }else{
                    $html='';
                }
                $matches=array('match'=>$html);
                $doMerge=true;
            }
        }else{
            
            $matches=array('match'=>$html);
            $doMerge=true;
        }
        
        if($doMerge&&!empty($config['reg_area_merge'])){
            
            if(!empty($parentMatches)){
                
                $parentMatches=array_merge($parentMatches,$matches);
                $html=$this->merge_match_signs($parentMatches, $config['reg_area_merge']);
            }else{
                $html=$this->merge_match_signs($matches, $config['reg_area_merge']);
            }
        }
        
        if($returnMatch){
            
            if(!is_array($matches)){
                $matches=array();
            }
            foreach ($matches as $k=>$v){
                
                if(stripos($k,'match')!==0){
                    unset($matches[$k]);
                }
            }
            return array('area'=>$html,'matches'=>$matches);
        }else{
            return $html;
        }
    }
    
    
    /*规则匹配网址*/
    public function rule_match_urls($pageType,$config,$html,$whole=false,$urlComplete=false,$returnMatch=false){
        $cont_urls=array();
        $cont_urls_matches=array();
        if(!empty($config['reg_url'])&&!empty($config['reg_url_merge'])){
            
            $parentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$config['name'],'url'));
            
            if(empty($config['reg_url_module'])){
                
                $cont_urls = $this->get_rule_module_rule_data(array(
                    'rule' => $config['reg_url'],
                    'rule_merge' => $config['reg_url_merge'],
                    'rule_multi' => true,
                    'rule_multi_type' => 'loop'
                ), $html,$parentMatches, $whole ,true);
                
                if(is_array($cont_urls)){
                    $cont_urls_matches=$cont_urls['matches'];
                    $cont_urls=$cont_urls['val'];
                }else{
                    $cont_urls=array();
                }
            }elseif('xpath'==$config['reg_url_module']||'json'==$config['reg_url_module']){
                
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
                
                
                foreach ($cont_urls as $k=>$v){
                    $v=array('match'=>$v);
                    $cont_urls_matches[$k]=$v;
                    if(!empty($parentMatches)){
                        
                        $v=array_merge($parentMatches,$v);
                    }
                    $cont_urls[$k]=$this->merge_match_signs($v, $config['reg_url_merge']);
                }
            }
        }
        
        if(!is_array($cont_urls)){
            $cont_urls=array();
        }
        if(!is_array($cont_urls_matches)){
            $cont_urls_matches=array();
        }
        $doComplete=false;
        $doMust=false;
        $doBan=false;
        if(!empty($urlComplete)&&is_array($urlComplete)){
            
            $doComplete=true;
        }
        if(!empty($config['url_must'])){
            $doMust=true;
        }
        if(!empty($config['url_ban'])){
            $doBan=true;
        }
        
        $urlMatchesMd5s=array();
        
        
        foreach ($cont_urls as $k=>$contUrl){
            $urlMatches=$cont_urls_matches[$k];
            if(!is_array($urlMatches)){
                $urlMatches=array();
            }
            foreach ($urlMatches as $umk=>$umv){
                
                if(stripos($umk,'match')!==0){
                    unset($urlMatches[$umk]);
                }
            }
            
            $urlMatchesMd5=md5(serialize($urlMatches));
            
            $doDelete=false;
            
            if(in_array($urlMatchesMd5,$urlMatchesMd5s)){
                
                $doDelete=true;
            }else{
                
                if($doComplete){
                    
                    $contUrl=$this->create_complete_url($contUrl, $urlComplete['base'], $urlComplete['domain']);
                    $cont_urls[$k]=$contUrl;
                }
                if($doMust){
                    
                    if(!preg_match('/'.$config['url_must'].'/'.$this->config['reg_regexp_flags'], $contUrl)){
                        $doDelete=true;
                    }
                }
                if(!$doDelete&&$doBan){
                    
                    if(preg_match('/'.$config['url_ban'].'/'.$this->config['reg_regexp_flags'], $contUrl)){
                        $doDelete=true;
                    }
                }
                if(!$doDelete&&empty($contUrl)){
                    
                    $doDelete=true;
                }
                if(!$doDelete&&strpos($contUrl,' ')!==false){
                    
                    $doDelete=true;
                }
            }
            if($doDelete){
                
                unset($cont_urls[$k]);
                unset($cont_urls_matches[$k]);
            }else{
                $urlMatchesMd5s[]=$urlMatchesMd5;
                $cont_urls_matches[$k]=$urlMatches;
            }
        }
        
        $requestPageType=$pageType;
        $requestPageName=$config['name'];
        
        if($requestPageType=='paging_url'){
            
            $urlWebConfig=$this->config['url_web'];
            $requestPageType='url';
            $requestPageName='';
        }else{
            $urlWebConfig=$config['url_web'];
        }
        
        if($this->url_web_is_open($urlWebConfig)){
            
            
            $formData=$this->arrays_to_key_val($urlWebConfig['form_names'], $urlWebConfig['form_vals']);
            if(!empty($formData)&&is_array($formData)){
                $urlsForms=array();
                $formParentMatches=$this->merge_str_signs(implode(' ',$formData));
                if(!empty($formParentMatches)){
                    
                    $formParentMatches=$this->parent_page_signs2matches($this->parent_page_signs($requestPageType,$requestPageName,'form'));
                }
                if(!is_array($formParentMatches)){
                    $formParentMatches=array();
                }
                
                foreach ($cont_urls as $k=>$v){
                    
                    $urlFormData=array();
                    $urlParentMatches=array_merge($formParentMatches,$cont_urls_matches[$k]);
                    foreach ($formData as $fk=>$fv){
                        $urlFormData[$fk]=$this->merge_match_signs($urlParentMatches,$fv);
                    }
                    $urlsForms[$k]=$urlFormData;
                }
                
                if(!empty($urlsForms)){
                    if($urlWebConfig['form_method']=='post'){
                        
                        foreach ($cont_urls as $k=>$v){
                            
                            $cont_urls[$k]=$v.'#post_'.md5(serialize($urlsForms[$k]));
                        }
                    }else{
                        
                        $charset=$urlWebConfig['charset']=='custom'?$urlWebConfig['charset_custom']:$urlWebConfig['charset'];
                        if(empty($charset)){
                            
                            $charset=$this->config['charset'];
                        }
                        $charset=strtolower($charset);
                        if(!empty($charset)&&!in_array($charset,array('auto','utf-8','utf8'))){
                            
                            foreach ($cont_urls as $k=>$v){
                                foreach ($urlsForms[$k] as $fk=>$fv){
                                    $urlsForms[$k][$fk]=iconv('utf-8',$charset.'//IGNORE',$fv);
                                }
                                $cont_urls[$k]=$v.(strpos($v,'?')===false?'?':'&').http_build_query($urlsForms[$k]);
                            }
                        }else{
                            
                            foreach ($cont_urls as $k=>$v){
                                $cont_urls[$k]=$v.(strpos($v,'?')===false?'?':'&').http_build_query($urlsForms[$k]);
                            }
                        }
                    }
                }
                unset($urlsForms);
            }
        }
        
        if($returnMatch){
            
            $return=array('urls'=>array(),'matches'=>array());
            foreach($cont_urls as $k=>$v){
                if(!in_array($v, $return['urls'])){
                    
                    $return['urls'][]=$v;
                    $return['matches'][md5($v)]=$cont_urls_matches[$k];
                }
            }
            return $return;
        }else{
            
            return array_values($cont_urls);
        }
        
    }
    
    
    /*将页面标签转换成match值*/
    public function parent_page_signs2matches($parentPageSigns){
        $matches=array();
        if(!empty($parentPageSigns)&&is_array($parentPageSigns)){
            if(!empty($parentPageSigns['cur'])&&is_array($parentPageSigns['cur'])){
                
                $curPage=$parentPageSigns['cur'];
                $this->_page_signs2matches('area', $curPage['area'], $curPage['page_type'], $curPage['page_name'], $matches);
                $this->_page_signs2matches('url', $curPage['url'], $curPage['page_type'], $curPage['page_name'], $matches);
            }
            
            if(!empty($parentPageSigns['level_url'])&&is_array($parentPageSigns['level_url'])){
                
                foreach ($parentPageSigns['level_url'] as $pageName=>$pageSigns){
                    $this->_page_signs2matches('area', $pageSigns['area'], 'level_url', $pageName, $matches);
                    $this->_page_signs2matches('url', $pageSigns['url'], 'level_url', $pageName, $matches);
                }
            }
            
            if(!empty($parentPageSigns['url'])&&is_array($parentPageSigns['url'])){
                
                $this->_page_signs2matches('area', $parentPageSigns['url']['area'], 'url', '', $matches);
                $this->_page_signs2matches('url', $parentPageSigns['url']['url'], 'url', '', $matches);
            }
            
            if(!empty($parentPageSigns['relation_url'])&&is_array($parentPageSigns['relation_url'])){
                
                foreach ($parentPageSigns['relation_url'] as $pageName=>$pageSigns){
                    $this->_page_signs2matches('area', $pageSigns['area'], 'relation_url', $pageName, $matches);
                    $this->_page_signs2matches('url', $pageSigns['url'], 'relation_url', $pageName, $matches);
                }
            }
        }
        return $matches;
    }
    
    private function _page_signs2matches($isAreaOrUrl,$signs,$pageType,$pageName,&$matches){
        if(is_array($signs)){
            if($isAreaOrUrl=='area'){
                
                if($pageType=='level_url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_area_match('level_url',$pageName,'match'.$sign['id']);
                    }
                }elseif($pageType=='url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_area_match('url','','match'.$sign['id']);
                    }
                }elseif($pageType=='relation_url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_area_match('relation_url',$pageName,'match'.$sign['id']);
                    }
                }
            }elseif($isAreaOrUrl=='url'){
                
                if($pageType=='level_url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_url_match('level_url',$pageName,md5($this->cur_level_urls[$pageName]),'match'.$sign['id']);
                    }
                }elseif($pageType=='url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_url_match('url','',md5($this->cur_cont_url),'match'.$sign['id']);
                    }
                }elseif($pageType=='relation_url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_url_match('relation_url',$pageName,null,'match'.$sign['id']);
                    }
                }
            }
        }
    }
    
    
    public function get_page_area_match($pageType,$pageName,$match=null){
        $keys=array($pageType,$pageName);
        if(isset($match)){
            $keys[]=$match;
        }
        return \util\Funcs::array_get($this->page_area_matches, $keys);
    }
    public function get_page_url_match($pageType,$pageName,$urlMd5=null,$match=null){
        $keys=array($pageType,$pageName);
        if(!empty($urlMd5)){
            
            $keys[]=$urlMd5;
        }
        if(isset($match)){
            $keys[]=$match;
        }
        return \util\Funcs::array_get($this->page_url_matches, $keys);
    }
    
    
    /*获取父级页面标签*/
    public function parent_page_signs($pageType,$pageName,$mergeType=null){
        $mergeType=empty($mergeType)?'':$mergeType;
        $pageSource=$this->convert_to_page_source($pageType, $pageName);
        
        if(!is_array($this->config_params['signs'])){
            $this->config_params['signs']=array();
        }
        if(!is_array($this->config_params['signs'][$pageSource])){
            $this->config_params['signs'][$pageSource]=array();
        }
        
        $foundPageSigns=$this->config_params['signs'][$pageSource][$mergeType];
        
        if(!isset($foundPageSigns)){
            
            $foundPageSigns=array('cur'=>null,'level_url'=>array(),'url'=>null,'relation_url'=>array());
            if($pageType=='relation_url'){
                
                
                $unknownPageSigns=$this->_page_signs_search($pageType,$pageName,$mergeType,$foundPageSigns);
                
                if(!empty($unknownPageSigns)){
                    $relationParentPages=$this->relation_parent_pages($pageName, $this->config['new_relation_urls']);
                    foreach ($relationParentPages as $relationParentPage){
                        if(empty($unknownPageSigns)){
                            
                            break;
                        }
                        
                        $unknownPageSigns=$this->_parent_page_signs_search('url',implode('',$unknownPageSigns),'relation_url',$relationParentPage,$foundPageSigns);
                        
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_search('area',implode('',$unknownPageSigns),'relation_url',$relationParentPage,$foundPageSigns);
                        }
                    }
                }
                if(!empty($unknownPageSigns)){
                    
                    
                    $unknownPageSigns=$this->_parent_page_signs_search('url',implode('',$unknownPageSigns),'url','',$foundPageSigns);
                    
                    if(!empty($unknownPageSigns)){
                        $unknownPageSigns=$this->_parent_page_signs_search('area',implode('',$unknownPageSigns),'url','',$foundPageSigns);
                    }
                }
            }elseif($pageType=='url'){
                
                $unknownPageSigns=$this->_page_signs_search($pageType,$pageName,$mergeType,$foundPageSigns);
            }
            
            if(!empty($this->config['new_level_urls'])){
                
                if($pageType=='level_url'){
                    
                    
                    $unknownPageSigns=$this->_page_signs_search($pageType,$pageName,$mergeType,$foundPageSigns);
                }
                if(!empty($unknownPageSigns)){
                    $levelNames=array_keys($this->config['new_level_urls']);
                    if($pageType=='level_url'){
                        
                        $levelNames1=array();
                        foreach($levelNames as $levelName){
                            if($pageName==$levelName){
                                
                                break;
                            }
                            $levelNames1[]=$levelName;
                        }
                        $levelNames=$levelNames1;
                    }
                    $levelNames=array_reverse($levelNames);
                    
                    foreach ($levelNames as $levelName){
                        if(empty($unknownPageSigns)){
                            
                            break;
                        }
                        
                        $unknownPageSigns=$this->_parent_page_signs_search('url',implode('',$unknownPageSigns),'level_url',$levelName,$foundPageSigns);
                        
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_search('area',implode('',$unknownPageSigns),'level_url',$levelName,$foundPageSigns);
                        }
                    }
                }
            }
            $foundSign=false;
            foreach ($foundPageSigns as $k=>$v){
                if(!empty($v)){
                    $foundSign=true;
                }
            }
            
            if(!$foundSign){
                $foundPageSigns=array();
            }
            $this->config_params['signs'][$pageSource][$mergeType]=$foundPageSigns;
        }
        
        if(!is_array($foundPageSigns)){
            $foundPageSigns=array();
        }
        
        return $foundPageSigns;
    }
    
    
    /*从当前页规则中找出未知的标签*/
    private function _page_signs_search($pageType,$pageName,$mergeType,&$foundPageSigns){
        static $inUrlRule=array('url','url_web','header','form');
        $unknownPageSigns=array();
        $pageConfig=array();
        if($pageType=='url'){
            $pageConfig=$this->config;
        }elseif($pageType=='level_url'){
            $pageConfig=$this->config['new_level_urls'][$pageName];
        }elseif($pageType=='relation_url'){
            $pageConfig=$this->config['new_relation_urls'][$pageName];
        }
        
        if(!empty($pageConfig)){
            $openUrlWeb=$this->url_web_is_open($pageConfig['url_web']);
            
            $pageSource=$this->convert_to_page_source($pageType, $pageName);
            
            $pageHeaderMerge='';
            if(empty($mergeType)||$mergeType=='url_web'||$mergeType=='header'){
                if($openUrlWeb){
                    
                    $pageHeaderMerge=$this->arrays_to_key_val($pageConfig['url_web']['header_names'], $pageConfig['url_web']['header_vals']);
                    $pageHeaderMerge=is_array($pageHeaderMerge)?implode(' ', $pageHeaderMerge):'';
                }
            }
            $pageFormMerge='';
            if(empty($mergeType)||$mergeType=='url_web'||$mergeType=='form'){
                if($openUrlWeb){
                    
                    $pageFormMerge=$this->arrays_to_key_val($pageConfig['url_web']['form_names'], $pageConfig['url_web']['form_vals']);
                    $pageFormMerge=is_array($pageFormMerge)?implode(' ', $pageFormMerge):'';
                }
            }
            
            if(!is_array($foundPageSigns['cur'])){
                $foundPageSigns['cur']=array();
            }
            
            $signMatch=$this->sign_addslashes(cp_sign('match',':id'));
            
            if(empty($mergeType)||in_array($mergeType,$inUrlRule)){
                
                $pageUrlMerge='';
                if(empty($mergeType)||$mergeType=='url'){
                    $pageUrlMerge=$pageConfig['reg_url_merge'];
                }
                $pageSigns=$this->signs_not_in_rule($pageConfig['reg_url'],$pageUrlMerge.$pageHeaderMerge.$pageFormMerge,true,false,true);
                if(is_array($pageSigns['unknown'])){
                    $unknownPageSigns=$pageSigns['unknown'];
                }
                if(is_array($pageSigns['found'])){
                    foreach ($pageSigns['found'] as $k=>$v){
                        if(preg_match('/^'.$signMatch.'$/i',$v,$msign)){
                            
                            $pageSigns['found'][$v]=array(
                                'sign'=>$v,
                                'id'=>$msign['id']
                            );
                        }else{
                            
                            unset($pageSigns['found'][$k]);
                        }
                    }
                    $foundPageSigns['cur']['url']=$pageSigns['found'];
                }
            }
            
            $pageAreaMerge='';
            if(empty($mergeType)||$mergeType=='area'){
                $pageAreaMerge=$pageConfig['reg_area_merge'];
            }
            $pageSigns=$this->signs_not_in_rule($pageConfig['reg_area'],$pageAreaMerge.implode('',$unknownPageSigns),true,false,true);
            if(is_array($pageSigns['unknown'])){
                $unknownPageSigns=$pageSigns['unknown'];
            }
            if(is_array($pageSigns['found'])){
                foreach ($pageSigns['found'] as $k=>$v){
                    if(preg_match('/^'.$signMatch.'$/i',$v,$msign)){
                        
                        if(is_array($foundPageSigns['cur']['url'])&&isset($foundPageSigns['cur']['url'][$v])){
                            
                            unset($pageSigns['found'][$k]);
                        }else{
                            $pageSigns['found'][$v]=array(
                                'sign'=>$v,
                                'id'=>$msign['id']
                            );
                        }
                    }else{
                        
                        unset($pageSigns['found'][$k]);
                    }
                }
                $foundPageSigns['cur']['area']=$pageSigns['found'];
            }
            
            if(!empty($foundPageSigns['cur'])){
                
                $foundPageSigns['cur']['page_type']=$pageType;
                $foundPageSigns['cur']['page_name']=$pageName;
            }
        }
        return $unknownPageSigns;
    }
    
    
    /*找出父页面规则中不存在的标签*/
    private function _parent_page_signs_search($ruleType,$mergeStr,$pageType,$pageName,&$foundPageSigns){
        $ruleStr='';
        if($pageType=='url'){
            $ruleStr=$this->config['reg_'.$ruleType];
        }elseif($pageType=='level_url'){
            if(!empty($this->config['new_level_urls'][$pageName])){
                $ruleStr=$this->get_config('new_level_urls',$pageName,'reg_'.$ruleType);
            }
        }elseif($pageType=='relation_url'){
            if(!empty($this->config['new_relation_urls'][$pageName])){
                $ruleStr=$this->get_config('new_relation_urls',$pageName,'reg_'.$ruleType);
            }
        }
        $pageSigns=$this->signs_not_in_rule($ruleStr,$mergeStr,true,false,true);
        $foundSigns=$pageSigns['found'];
        if(!empty($foundSigns)&&is_array($foundSigns)){
            
            $signMatch=$this->sign_addslashes(cp_sign('match',':id'));
            foreach ($foundSigns as $k=>$v){
                if(preg_match('/^'.$signMatch.'$/i',$v,$msign)){
                    
                    $foundSigns[$k]=array(
                        'sign'=>$v,
                        'id'=>$msign['id']
                    );
                }else{
                    
                    unset($foundSigns[$k]);
                }
            }
            if(!empty($foundSigns)){
                
                if($pageType=='url'){
                    
                    if(!is_array($foundPageSigns[$pageType])){
                        $foundPageSigns[$pageType]=array();
                    }
                    if(!is_array($foundPageSigns[$pageType][$ruleType])){
                        $foundPageSigns[$pageType][$ruleType]=array();
                    }
                    
                    foreach ($foundSigns as $k=>$v){
                        $foundPageSigns[$pageType][$ruleType][$k]=$v;
                    }
                }else{
                    
                    if(!is_array($foundPageSigns[$pageType][$pageName])){
                        $foundPageSigns[$pageType][$pageName]=array();
                    }
                    if(!is_array($foundPageSigns[$pageType][$pageName][$ruleType])){
                        $foundPageSigns[$pageType][$pageName][$ruleType]=array();
                    }
                    
                    foreach ($foundSigns as $k=>$v){
                        $foundPageSigns[$pageType][$pageName][$ruleType][$k]=$v;
                    }
                }
            }
        }
        return is_array($pageSigns['unknown'])?$pageSigns['unknown']:array();
    }
    
    /*数据源下拉框数据*/
    public function page_source_options(){
        $pageSources=array('source_url'=>'起始页','url'=>'内容页');
        
        if(is_array($this->config)){
            if(is_array($this->config['new_level_urls'])){
                foreach ($this->config['new_level_urls'] as $k=>$v){
                    $pageSources['level_url:'.$k]='多级页：'.$k;
                }
            }
            if(is_array($this->config['new_relation_urls'])){
                foreach ($this->config['new_relation_urls'] as $k=>$v){
                    $pageSources['relation_url:'.$k]='关联页：'.$k;
                }
            }
        }
        return $pageSources;
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
    
    
    public function get_config($key1,$key2=null,$key3=null){
        $keys=array($key1);
        if(isset($key2)){
            $keys[]=$key2;
            if(isset($key3)){
                $keys[]=$key3;
            }
        }
        return \util\Funcs::array_get($this->config, $keys);
    }
    
    /*获取最后的多级页*/
    public function get_last_level(){
        $data=array('level'=>0,'config'=>null);
        if(!empty($this->config['level_urls'])&&is_array($this->config['level_urls'])){
            $lastNum=count($this->config['level_urls']);
            $lastLevel=$this->config['level_urls'][$lastNum-1];
            $data['level']=$lastNum;
            $data['config']=$lastLevel;
        }
        return $data;
    }
    
    
    /*获取页面代码*/
    public function get_page_html($url,$pageType,$pageName,$openCache=false){
        $headers=array();
        $urlForm=array();
        
        $pageSource=$this->convert_to_page_source($pageType, $pageName);
        
        $charset=null;
        
        $urlWebConfig=array();
        
        if($pageType=='url'){
            $urlWebConfig=$this->config['url_web'];
        }elseif($pageType=='level_url'){
            $urlWebConfig=$this->get_config('new_level_urls',$pageName,'url_web');
        }elseif($pageType=='relation_url'){
            $urlWebConfig=$this->get_config('new_relation_urls',$pageName,'url_web');
        }
        
        $openUrlWeb=$this->url_web_is_open($urlWebConfig);
        
        if(!empty($pageSource)){
            
            if($openUrlWeb){
                
                $headers=$this->arrays_to_key_val($urlWebConfig['header_names'], $urlWebConfig['header_vals']);
                if(!empty($headers)){
                    $signs=$this->merge_str_signs(implode(' ',$headers));
                    if(!empty($signs)){
                        
                        $signs=$this->parent_page_signs($pageType, $pageName, 'header');
                        $signs=$this->parent_page_signs2matches($signs);
                        
                        foreach ($headers as $k=>$v){
                            $headers[$k]=$this->merge_match_signs($signs, $v);
                        }
                    }
                }
                
                if(!is_array($headers)){
                    $headers=array();
                }
                
                $globalHeaders=array();
                if(empty($urlWebConfig['header_global'])){
                    
                    $globalHeaders=$this->config_params['headers']['page'];
                }elseif($urlWebConfig['header_global']=='y'){
                    
                    $globalHeaders=$this->config_params['headers']['page_headers'];
                }
                if(!empty($globalHeaders)&&is_array($globalHeaders)){
                    $headers=array_merge($globalHeaders,$headers);
                }
            }else{
                
                $headers=$this->config_params['headers']['page'];
            }
            
            if(!is_array($headers)){
                $headers=array();
            }
        }
        
        $postData=null;
        if($openUrlWeb){
            
            $charset=$urlWebConfig['charset']=='custom'?$urlWebConfig['charset_custom']:$urlWebConfig['charset'];
            
            $formData=$this->arrays_to_key_val($urlWebConfig['form_names'], $urlWebConfig['form_vals']);
            if(!empty($formData)&&is_array($formData)){
                $signs=$this->merge_str_signs(implode(' ',$formData));
                if(!empty($signs)){
                    
                    $signs=$this->parent_page_signs($pageType, $pageName, 'form');
                    $signs=$this->parent_page_signs2matches($signs);
                    
                    foreach ($formData as $k=>$v){
                        $formData[$k]=$this->merge_match_signs($signs, $v);
                    }
                }
            }
            $formData=is_array($formData)?$formData:'';
            
            if($urlWebConfig['form_method']=='post'){
                
                $postData=$formData;
                $url=preg_replace('/\#post_\w{32}$/i', '', $url);
            }else{
                
                $postData=null;
            }
            unset($formData);
        }
        
        if(empty($charset)){
            
            $charset=$this->config['charset'];
        }

        $html=null;
        
        if($openCache){
            
            
            if(empty($this->cache_page_urls)){
                $this->cache_page_urls=array(
                    'source_url'=>$this->cur_source_url,
                    'level_urls'=>is_array($this->cur_level_urls)?$this->cur_level_urls:array()
                );
            }
            
            
            if(!is_array($this->cache_page_htmls)){
                $this->cache_page_htmls=array();
            }
            if(!is_array($this->cache_page_htmls[$pageType])){
                $this->cache_page_htmls[$pageType]=array();
            }
            if(!is_array($this->cache_page_htmls[$pageType][$pageName])){
                $this->cache_page_htmls[$pageType][$pageName]=array();
            }
            
            if($pageType=='source_url'&&$this->cur_source_url!=$this->cache_page_urls['source_url']){
                
                $this->cache_page_urls['source_url']=$this->cur_source_url;
                $this->cache_page_htmls=array();
            }
            
            if($pageType=='level_url'&&$this->cur_level_urls[$pageName]!=$this->cache_page_urls['level_urls'][$pageName]){
                
                $this->cache_page_urls['level_urls']=is_array($this->cur_level_urls)?$this->cur_level_urls:array();
                $this->cache_page_htmls['level_url'][$pageName]=array();
                $this->cache_page_htmls['url']=array();
                $this->cache_page_htmls['relation_url']=array();
            }
            
            $cacheKey=md5($url.' '.serialize($postData));
            if(isset($this->cache_page_htmls[$pageType][$pageName][$cacheKey])){
                
                $html=$this->cache_page_htmls[$pageType][$pageName][$cacheKey];
            }else{
                $html=$this->get_html($url,$postData,$headers,$charset);
                $this->cache_page_htmls[$pageType][$pageName][$cacheKey]=$html;
            }
        }else{
            $html=$this->get_html($url,$postData,$headers,$charset);
        }
        
        return $html;
    }
    
    
    
    /**
     * 获取源码
     * @param string $url 网址
     * @param bool|array $postData post数据
     * @param array $headers 请求头信息
     * @param string $charset 页面编码
     */
    public function get_html($url,$postData=false,$headers=array(),$charset=null){
        static $retryCur=0;
        $retryMax=intval(g_sc_c('caiji','retry'));
        $retryParams=null;
        if($retryMax>0){
            
            $retryParams=array('url'=>$url,'post'=>$postData,'headers'=>$headers,'charset'=>$charset);
        }
        
        if(!is_empty(g_sc_c('caiji','robots'))){
            
            if(!model('Collector')->abide_by_robots($url)){
                $this->error('robots拒绝访问的网址：'.$url);
                return null;
            }
        }
        
        if(empty($charset)){
            
            $charset=$this->config['charset'];
        }
        
        $pageRenderTool=null;
        if($this->config['page_render']){
            $pageRenderTool=g_sc_c('page_render','tool');
            if(empty($pageRenderTool)){
                
                $this->error('页面渲染未设置，请检查<a href="'.url('Setting/page_render').'" target="_blank">渲染设置</a>','Setting/page_render');
                return null;
            }
        }
        
        $html=null;
        $options=array();
        
        if(empty($headers)||!is_array($headers)){
            $headers=array();
        }else{
            if(!empty($headers['useragent'])){
                
                $options['useragent']=$headers['useragent'];
            }
            unset($headers['useragent']);
        }
        
        
        $mproxy=model('Proxyip');
        $proxyDbIp=null;
        if(!is_empty(g_sc_c('proxy','open'))){
            
            $proxyDbIp=$mproxy->get_usable_ip();
            $proxyIp=$mproxy->to_proxy_ip($proxyDbIp);
            
            if(!empty($proxyIp)){
                
                $options['proxy']=$proxyIp;
            }
        }
        if($pageRenderTool){
            
            if(!empty($options['useragent'])){
                
                $headers['user-agent']=$options['useragent'];
                unset($options['useragent']);
            }
            
            if($pageRenderTool=='chrome'){
                $chromeConfig=g_sc_c('page_render','chrome');
                try {
                    $chromeSocket=new \util\ChromeSocket($chromeConfig['host'],$chromeConfig['port'],g_sc_c('page_render','timeout'),$chromeConfig['filename'],$chromeConfig);
                    $chromeSocket->newTab();
                    $chromeSocket->websocket(null);
                    if(isset($postData)&&$postData!==false){
                        
                        $html=$chromeSocket->getRenderHtml($url,$headers,$options,$charset,$postData);
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
            $html=get_html($url,$headers,$options,$charset,$postData);
        }
        
        if($html===null||$html===false){
            
            if($retryCur<=0&&is_collecting()){
                
                $echoMsg='<div class="clear"><span class="left">访问网址失败：</span><a href="'.$url.'" target="_blank" class="lurl">'.$url.'</a></div>';
                $this->error($echoMsg);
            }
            
            if(!empty($proxyDbIp)){
                $mproxy->set_ip_failed($proxyDbIp);
            }
            
            $failedWait=intval(g_sc_c('caiji','wait'));
            if($failedWait>0){
                
                sleep($failedWait);
            }
            
            if($retryMax>0&&is_array($retryParams)){
                
                if($retryCur<$retryMax){
                    
                    $retryCur++;
                    if(is_collecting()){
                        $this->echo_msg(($retryCur>1?'，':'重试：').'第'.$retryCur.'次','black',true,'','display:inline;');
                    }
                    return $this->get_html($retryParams['url'],$retryParams['post'],$retryParams['headers'],$retryParams['charset']);
                }else{
                    $retryCur=0;
                    if(is_collecting()){
                        $this->echo_msg('网址无效','red',true,'','display:inline;margin-left:10px;');
                    }
                }
            }
            return null;
        }
        $retryCur=0;
        
        if($this->config['url_complete']){
            
            $base_url=$this->match_base_url($url, $html);
            $domain_url=$this->match_domain_url($url, $html);
            $html=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($base_url,$domain_url){
                
                $matche[2]=\skycaiji\admin\event\Cpattern::create_complete_url($matche[2], $base_url, $domain_url);
                return $matche[1].$matche[2].$matche[3];
            },$html);
                $html=preg_replace_callback('/(\bsrc\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($base_url,$domain_url){
                    $matche[2]=\skycaiji\admin\event\Cpattern::create_complete_url($matche[2], $base_url, $domain_url);
                    return $matche[1].$matche[2].$matche[3];
                },$html);
        }
        
        return $html;
    }
    
}
?>