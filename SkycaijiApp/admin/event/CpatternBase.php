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

class CpatternBase extends Collector{
    
    public function setConfig($config){}
    public function init($config){}
    public function collect($num=10){}
    
    /*正则规则匹配数据*/
    public function rule_module_rule_data($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        $val=null;
        $matches=array();
        if(!is_array($parentMatches)){
            $parentMatches=array();
        }
        if(!empty($configParams['rule'])&&!empty($configParams['rule_merge'])){
            
            if(empty($configParams['rule_flags'])){
                $configParams['rule_flags']='';
            }
            
            $ruleSigns=$this->rule_str_signs($configParams['rule']);
            
            if(!empty($configParams['rule_multi'])){
                
                if(preg_match_all('/'.$configParams['rule'].'/'.$configParams['rule_flags'],$html,$matchConts,PREG_SET_ORDER)){
                    if(empty($ruleSigns)){
                        
                        if($whole){
                            
                            foreach ($matchConts as $k=>$v){
                                $v['match']=$v[0];
                                $matchConts[$k]=$v;
                            }
                        }else{
                            
                            $matchConts=array();
                        }
                    }
                    
                    foreach ($matchConts as $k=>$v){
                        if($returnMatch){
                            
                            $matches[$k]=$v;
                        }
                        if(!empty($parentMatches)){
                            
                            $v=array_merge($parentMatches,$v);
                        }
                        $matchConts[$k]=$this->merge_match_signs($v,$configParams['rule_merge']);
                    }
                    if($configParams['rule_multi_type']=='loop'){
                        
                        $val=$matchConts;
                    }else{
                        
                        $multiStr=$configParams['rule_multi_str'];
                        if(!empty($multiStr)){
                            $multiStr=str_replace(array('\r','\n'), array("\r","\n"), $multiStr);
                        }
                        $val=implode($multiStr, $matchConts);
                    }
                }
                
            }else{
                
                if(preg_match('/'.$configParams['rule'].'/'.$configParams['rule_flags'],$html,$matchCont)){
                    if(empty($ruleSigns)){
                        
                        if($whole){
                            
                            
                            $matchCont['match']=$matchCont[0];
                        }else{
                            
                            $matchCont=array();
                        }
                    }
                    if(!empty($matchCont)){
                        
                        if(!empty($parentMatches)){
                            
                            $parentMatches=array_merge($parentMatches,$matchCont);
                            $val=$this->merge_match_signs($parentMatches,$configParams['rule_merge']);
                        }else{
                            $val=$this->merge_match_signs($matchCont,$configParams['rule_merge']);
                        }
                    }
                }else{
                    
                    $matchCont=array();
                }
                if($returnMatch){
                    
                    $matches=$matchCont;
                }
            }
        }
        if($returnMatch){
            return array('val'=>$val,'matches'=>$matches);
        }else{
            return $val;
        }
    }
    /*拼接替换标签*/
    public function merge_match_signs($matches,$merge){
        if(!is_array($matches)){
            
            $matches=array();
        }
        $val='';
        if(!empty($merge)){
            
            $mergeSigns=$this->merge_str_signs($merge,true);
            if(!empty($mergeSigns)){
                
                $signVals=array();
                foreach($mergeSigns['id'] as $k=>$v){
                    $signVals[$k]=isset($matches['match'.$v])?$matches['match'.$v]:'';
                }
                $val=str_replace($mergeSigns[0], $signVals, $merge);
            }else{
                
                $val=$merge;
            }
        }else{
            
            if(isset($merge)){
                
                $val=$merge;
            }
        }
        return $val;
    }
    
    
    public function rule_module_xpath_data($configParams,$html){
        $vals='';
        if(!empty($configParams['xpath'])){
            $dom=new \DOMDocument;
            $libxml_previous_state = libxml_use_internal_errors(true);
            @$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html;charset=utf-8">'.$html);
            
            $dom->normalize();
            
            $xPath = new \DOMXPath($dom);
            
            $xpath_attr=strtolower($configParams['xpath_attr']);
            $xpath_attr='custom'==$xpath_attr?strtolower($configParams['xpath_attr_custom']):$xpath_attr;
            
            $normal_attr=true;
            if(in_array($xpath_attr,array('innerhtml','outerhtml','text'))){
                
                $normal_attr=false;
            }
            $xpath_q=trim($configParams['xpath']);
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
            if(!empty($configParams['xpath_multi'])){
                
                $is_loop='loop'==$configParams['xpath_multi_type']?true:false;
                if($is_loop){
                    $vals=array();
                }else{
                    
                    $multiStr=str_replace(array('\r','\n'), array("\r","\n"), $configParams['xpath_multi_str']);
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
                
                if(empty($configParams['xpath_multi'])){
                    
                    break;
                }
            }
            
            libxml_clear_errors();
            
        }
        return $vals;
    }
    
    public function rule_module_json_data($configParams,$jsonArrOrStr){
        $jsonArr=array();
        if(is_array($jsonArrOrStr)){
            $jsonArr=&$jsonArrOrStr;
        }else{
            
            $jsonArr=\util\Funcs::convert_html2json($jsonArrOrStr);
            unset($jsonArrOrStr);
        }
        $val='';
        if(!empty($jsonArr)){
            if(!empty($configParams['json'])){
                
                $jsonFmt=str_replace(array('"',"'",'[',' '), '', $configParams['json']);
                $jsonFmt=str_replace(']','.',$jsonFmt);
                $jsonFmt=trim($jsonFmt,'.');
                $jsonFmt=explode('.', $jsonFmt);
                $jsonFmt=array_values($jsonFmt);
                if(!empty($jsonFmt)){
                    
                    $val=$jsonArr;
                    $prevKey='';
                    foreach ($jsonFmt as $i=>$key){
                        if($prevKey=='*'){
                            
                            $newConfigParams=$configParams;
                            $newConfigParams['json']=array_slice($jsonFmt, $i);
                            $newConfigParams['json']=implode('.', $newConfigParams['json']);
                            
                            foreach ($val as $vk=>$vv){
                                
                                $val[$vk]=$this->rule_module_json_data($newConfigParams,$vv);
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
        
        return $this->rule_module_json_data_convert($val, $configParams);
    }
    public function rule_module_json_data_convert($val,$configParams){
        if(is_array($val)){
            
            $json_arr=strtolower($configParams['json_arr']);
            if(empty($json_arr)){
                $json_arr='implode';
            }
            switch ($json_arr){
                case 'implode':$arrImplode=str_replace(array('\r','\n'), array("\r","\n"), $configParams['json_arr_implode']);$val=\util\Funcs::array_implode($arrImplode,$val);break;
                case 'jsonencode':$val=json_encode($val);break;
                case 'serialize':$val=serialize($val);break;
                case '_original_': break;
            }
        }
        return $val;
    }
    
    
    /**
     * 拼接默认设置
     * @param string $reg 规则
     * @param string $merge 拼接字符串
     */
    public function set_merge_default($reg,$merge){
        if(empty($merge)){
            $merge='';
            if(!empty($reg)){
                
                $merge=$this->rule_str_signs($reg);
                $merge=implode('', $merge);
            }
        }
        return $merge;
    }
    
    
    /*获取正则规则里的标签列表*/
    public function rule_str_signs($rule,$returnIds=false){
        $ruleSigns=array();
        if(!empty($rule)){
            static $rule_signs_list=array();
            $key=md5($rule);
            $ruleSigns=$rule_signs_list[$key];
            if(!isset($ruleSigns)){
                if(preg_match_all('/\<match(?P<id>\w*)\>/i', $rule, $ruleSigns)){
                    
                    foreach ($ruleSigns['id'] as $k=>$v){
                        $ruleSigns[0][$k]=cp_sign('match',$v);
                    }
                    $rule_signs_list[$key]=$ruleSigns;
                }else{
                    $rule_signs_list[$key]=array();
                }
            }
        }
        
        if(!$returnIds){
            
            if(is_array($ruleSigns[0])){
                $ruleSigns=$ruleSigns[0];
                $ruleSigns=array_unique($ruleSigns);
                $ruleSigns=array_values($ruleSigns);
            }else{
                $ruleSigns=array();
            }
            return $ruleSigns;
        }else{
            
            return $ruleSigns;
        }
        return $ruleSigns;
    }
    
    /*获取拼接字符串中的标签*/
    public function merge_str_signs($merge,$returnIds=false){
        $mergeSigns=array();
        if(!empty($merge)){
            static $merge_signs_list=array();
            $key=md5($merge);
            $mergeSigns=$merge_signs_list[$key];
            if(!isset($mergeSigns)){
                
                $signMatch=$this->sign_addslashes(cp_sign('match',':id'));
                if(preg_match_all('/'.$signMatch.'/i',$merge,$mergeSigns)){
                    
                    $merge_signs_list[$key]=$mergeSigns;
                }else{
                    $merge_signs_list[$key]=array();
                }
            }
        }
        if(!$returnIds){
            
            if(is_array($mergeSigns[0])){
                $mergeSigns=$mergeSigns[0];
                $mergeSigns=array_unique($mergeSigns);
                $mergeSigns=array_values($mergeSigns);
            }else{
                $mergeSigns=array();
            }
            return $mergeSigns;
        }else{
            
            return $mergeSigns;
        }
    }
    
    /*排除内容网址的提示信息*/
    public function exclude_url_msg($val){
        try{
            $val=json_decode($val,true);
        }catch (\Exception $ex){
            $val=array();
        }
        if(!is_array($val)){
            $val=array();
        }
        $type=$val['type'];
        $msg='排除网址';
        if($type=='filter'){
            
            if(empty($val['filter'])){
                $msg='字段:'.$val['field'].'»关键词过滤:未检测到关键词';
            }else{
                $msg='字段:'.$val['field'].'»关键词过滤:'.$val['filter'];
            }
        }elseif($type=='if'){
            $msg='字段:'.$val['field'].'»条件';
            
            switch ($val['if']){
                case '1':$msg.='假';break;
                case '2':$msg.='真';break;
                case '3':$msg.='假';break;
                case '4':$msg.='真';break;
            }
            $msg.='(';
            if(lang('?p_m_if_'.$val['if'])){
                $msg.=lang('p_m_if_'.$val['if']);
            }
            if(!empty($val['cond'])){
                $msg.='»'.$val['cond'];
            }
            $msg.=')';
        }
        return $msg;
    }
    /*修正规则中的正则表达式*/
    public function correct_reg_pattern($str){
        $str=preg_replace('/\\\*([\'\/])/', "\\\\$1",$str);
        $str=$this->convert_sign_wildcard($str);
        return $str;
    }
    /*转换(*)通配符*/
    public function convert_sign_wildcard($str){
        return str_replace(lang('sign_wildcard'), '[\s\S]*?', $str);
    }
    /*转换[内容]标签*/
    public function convert_sign_match($str){
        $str=preg_replace('/\(\?<(content|match|nr)/i', '(?P<match', $str);
        $sign_match=$this->sign_addslashes(cp_sign('match',':id'));
        $str=preg_replace_callback('/(\={0,1})(\s*)([\'\"]{0,1})'.$sign_match.'\3/', function($matches){
            $ruleStr=$matches[1].$matches[2].$matches[3].'(?P<match'.$matches['id'].'>';
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
    /*保存数据处理时过滤配置参数*/
    public function set_process($processList){
        if(is_array($processList)){
            $processList=\util\Funcs::array_array_map('trim',$processList);
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
                }elseif('api'==$v['module']){
                    
                    if(!is_array($v['api_params'])){
                        $v['api_params']=array();
                    }
                    \util\Funcs::filter_key_val_list3($v['api_params']['name'],$v['api_params']['val'],$v['api_params']['addon']);
                    
                    if(!is_array($v['api_headers'])){
                        $v['api_headers']=array();
                    }
                    \util\Funcs::filter_key_val_list($v['api_headers']['name'], $v['api_headers']['val']);
                }
                $processList[$k]=$v;
            }
            $processList=array_values($processList);
        }
        return $processList;
    }
    /*保存页面配置时过滤表单数据和头信息*/
    public function set_config_url_web($pageConfig){
        
        if(!is_array($pageConfig)){
            $pageConfig=array();
        }
        if(!is_array($pageConfig['url_web'])){
            $pageConfig['url_web']=array();
        }
        
        $pageConfig['url_web']['open']=intval($pageConfig['url_web']['open']);
        
        $formMethod=$pageConfig['url_web']['form_method'];
        $pageConfig['url_web']['form_method']=empty($formMethod)?'':strtolower($formMethod);
        
        $headerGlobal=$pageConfig['url_web']['header_global'];
        $pageConfig['url_web']['header_global']=empty($headerGlobal)?'':strtolower($headerGlobal);
        
        
        \util\Funcs::filter_key_val_list($pageConfig['url_web']['form_names'], $pageConfig['url_web']['form_vals']);
        \util\Funcs::filter_key_val_list($pageConfig['url_web']['header_names'], $pageConfig['url_web']['header_vals']);
        
        return $pageConfig;
    }
   
    
    /*获取关联页的父级页面名称*/
    public function relation_parent_pages($curName,$configList,$high2lowSort=false){
        $parentPages=array();
        if(!is_array($configList)){
            $configList=array();
        }
        
        $pageName=$curName;
        
        $depth=0;
        
        do{
            $pageConfig=$configList[$pageName];
            if(empty($pageConfig)){
                
                break;
            }else{
                $parentPage=$pageConfig['page'];
                if($parentPage==$pageName||in_array($parentPage,$parentPages)){
                    
                    break;
                }else{
                    
                    if(!empty($parentPage)){
                        
                        $parentPages[$depth]=$parentPage;
                    }
                    $pageName=$parentPage;
                }
            }
            $depth++;
        }while(!empty($pageName));
        
        if($high2lowSort){
            
            krsort($parentPages);
        }
        
        return array_values($parentPages);
    }
    
    
    
    /*不在规则中的标签列表*/
    public function signs_not_in_rule($ruleStr,$mergeStr,$whole,$keyIsMatch=false,$returnFound=false){
        $ruleSignsIds=$this->rule_str_signs($ruleStr,true);
        $ruleSignsIds=$ruleSignsIds['id'];
        
        $mergeSignsIds=$this->merge_str_signs($mergeStr,true);
        $mergeSignsIds=$mergeSignsIds['id'];
        
        $unknownSigns=array();
        $foundSigns=array();
        if(!empty($mergeSignsIds)){
            
            if(empty($ruleSignsIds)){
                
                foreach ($mergeSignsIds as $v){
                    if($whole){
                        
                        $sign=$keyIsMatch?('match'.$v):cp_sign('match',$v);
                        if($v!=''){
                            
                            $unknownSigns[$sign]=$sign;
                        }else{
                            if($returnFound){
                                
                                $foundSigns[$sign]=$sign;
                            }
                        }
                    }
                }
            }else{
                
                foreach ($mergeSignsIds as $v){
                    $sign=$keyIsMatch?('match'.$v):cp_sign('match',$v);
                    if(!in_array($v, $ruleSignsIds)){
                        
                        $unknownSigns[$sign]=$sign;
                    }else{
                        if($returnFound){
                            
                            $foundSigns[$sign]=$sign;
                        }
                    }
                }
            }
        }
        if($returnFound){
            
            return array('unknown'=>$unknownSigns,'found'=>$foundSigns);
        }else{
            
            return $unknownSigns;
        }
    }
    
    /*转换成数据源*/
    public function convert_to_page_source($pageType,$pageName){
        $pageSource=$pageType;
        if($pageSource=='level_url'||$pageSource=='relation_url'){
            $pageSource.=':'.$pageName;
        }
        return $pageSource;
    }
    /*分解数据源为type和name*/
    public function split_page_source($pageSource){
        $type='';
        $name='';
        if(preg_match('/^(\w+)\:(.*)$/',$pageSource,$mpage)){
            $type=$mpage[1];
            $name=$mpage[2];
        }else{
            $type=$pageSource;
            $name='';
        }
        return array('type'=>$type,'name'=>$name);
    }
    /*多个数组合并成键值对*/
    public function arrays_to_key_val($arr1,$arr2){
        if(!is_array($arr1)){
            $arr1=array();
        }
        if(!is_array($arr2)){
            $arr2=array();
        }
        
        static $list=array();
        $key=md5(serialize($arr1).' '.serialize($arr2));
        
        $data=$list[$key];
        if(!isset($data)){
            $data=array();
            foreach ($arr1 as $k=>$v){
                if(!empty($v)){
                    
                    $data[$v]=$arr2[$k];
                }
            }
            $list[$key]=$data;
        }
        
        return is_array($data)?$data:array();
    }
    
    /*数据处理:翻译*/
    public function execute_translate($q,$from,$to){
        static $retryCur=0;
        $transConf=g_sc_c('translate');
        $transConf['interval']=intval($transConf['interval']);
        $transConf['wait']=intval($transConf['wait']);
        $transConf['retry']=intval($transConf['retry']);
        
        $retryMax=$transConf['retry'];
        
        $return=\util\Translator::translate($q, $from, $to,true);
        
        if(is_array($return)){
            
            
            if($transConf['interval']>0){
                
                usleep($transConf['interval']*1000);
            }
            
            if(!empty($return['success'])){
                
                $retryCur=0;
                $return=$return['data'];
            }else{
                
                if($retryMax<=0||($retryCur<=0&&is_collecting())){
                    
                    $this->error('数据处理»翻译失败');
                }
                
                if($transConf['wait']>0){
                    
                    sleep($transConf['wait']);
                }
                
                if($retryMax>0){
                    
                    if($retryCur<$retryMax){
                        
                        $retryCur++;
                        if(is_collecting()){
                            
                            $this->echo_msg(($retryCur>1?'，':'重试：').'第'.$retryCur.'次','black',true,'','display:inline;');
                        }
                        
                        return $this->execute_translate($q,$from,$to);
                    }else{
                        $retryCur=0;
                        if(is_collecting()){
                            $this->echo_msg('翻译无效','red',true,'','display:inline;margin-left:10px;');
                        }else{
                            
                            $this->error('数据处理»翻译：已重试'.$retryMax.'次，翻译无效');
                        }
                    }
                }
            }
        }
        return $return;
    }
    
    /**
     * 执行数据处理»使用函数
     * @param string $module 模块
     * @param string $funcName 函数/方法
     * @param string $fieldVal 字段值
     * @param string $paramsStr 输入的参数（有换行符）
     * @param array $fieldValList 所有字段值（调用字段时使用）
     */
    public function execute_plugin_func($module,$funcName,$fieldVal,$paramsStr,$fieldValList=null){
        $return=model('FuncApp')->execute_func($module,$funcName,$fieldVal,$paramsStr,$fieldValList);
        if(empty($return['success'])&&!empty($return['msg'])){
            
            $this->error($return['msg']);
        }
        return $return['data'];
    }
    
    public function url_web_is_open($urlWebConfig){
        if(is_array($urlWebConfig)&&!empty($urlWebConfig['open'])){
            return true;
        }else{
            return false;
        }
    }
}
?>