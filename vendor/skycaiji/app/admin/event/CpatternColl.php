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
class CpatternColl extends CpatternBase{
    public $collector;
    public $config;
    public $config_params;
    public $release;
    public $front_collected=false;
    public $front_cookie='';
    public $first_loop_field=null;
    public $field_val_list=array();
    public $collect_num=0;
    public $collected_field_list=array();
    public $used_source_urls=array();
    public $used_level_urls=array();
    public $used_cont_urls=array();
    public $used_pagination_urls=array();
    public $original_source_urls=null;
    public $cont_urls_list=array();
    public $exclude_cont_urls=array();
    public $relation_url_list=array();
    public $cur_front_urls=array();
    public $cur_source_url='';
    public $cur_level_urls=array();
    public $cur_cont_url='';
    public $cur_pagination_urls=array();
    public $page_content_matches=array();
    public $page_url_matches=array();
    public $page_area_matches=array();
    public $pn_url_matches=array();
    public $pn_area_matches=array();
    public $show_opened_tools=false;
    public $render_pn_sockets=array();
    protected $cache_page_htmls=array();
    protected $cache_page_urls=array();
    protected $cache_pn_htmls=array();
    protected $cache_pn_urls=array();
    protected $field_url_complete=true;
    protected $field_down_img=true;
    protected $field_stop_process=false;
    /*对象销毁时处理*/
    public function __destruct(){
        
        $usedContUrls=array();
        if(!empty($this->used_cont_urls)){
            $usedContUrls=array_keys($this->used_cont_urls);
            init_array($usedContUrls);
        }
        if($this->cur_cont_url){
            $usedContUrls[]=md5($this->cur_cont_url);
        }
        if(!empty($usedContUrls)){
            $total=count($usedContUrls);
            $limit=100;
            $batch=ceil($total/$limit);
            for($i=1;$i<=$batch;$i++){
                
                $list=array_slice($usedContUrls,($i-1)*$limit,$limit);
                if(!empty($list)){
                    CacheModel::getInstance('cont_url')->deleteCache($list);
                }
            }
        }
        
        if($this->render_pn_sockets){
            
            foreach ($this->render_pn_sockets as $socket){
                $socket=null;
            }
        }
    }
    
    
    public function match_url_info($url,$html,$cacheKey=false){
        static $cacheList=array();
        $cacheMd5=null;
        $info=array();
        if($cacheKey){
            
            init_array($cacheList[$cacheKey]);
            $cacheMd5=md5($url);
            $info=$cacheList[$cacheKey][$cacheMd5];
        }
        if(empty($info)){
            
            $info=array('cur_url'=>$url,'url_no_name'=>$this->config['url_no_name']);
            $baseInfo=\util\Tools::match_base_url($url,$html,true);
            $info=array_merge($info,$baseInfo);
            $info['domain_url']=\util\Tools::match_domain_url($url);
            if($cacheKey){
                
                $cacheList[$cacheKey][$cacheMd5]=$info;
            }
        }
        init_array($info);
        return $info;
    }
    
    
    
    /*规则匹配区域*/
    public function rule_match_area($pageType,$pageName,$isPagination,$html,$returnMatch=false){
        $matches=array();
        $config=$this->get_page_config($pageType,$pageName,$isPagination?'pagination':null);
        $parentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$pageName,($isPagination?'pn:':'').'area'));
        if(!is_array($config)){
            $config=array();
        }
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
    public function rule_match_urls($pageType,$pageName,$isPagination,$html,$completeUrlInfo=false,$returnMatch=false){
        $cont_urls=array();
        $cont_urls_matches=array();
        $config=$this->get_page_config($pageType,$pageName,$isPagination?'pagination':null);
        if(!is_array($config)){
            $config=array();
        }
        
        $parentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$pageName,($isPagination?'pn:':'').'url'));
        if(!empty($config['reg_url'])&&!empty($config['reg_url_merge'])){
            
            $config['reg_url_merge']=$this->pn_replace_cur_url($pageType,$pageName,$isPagination,$config['reg_url_merge']);
            if(empty($config['reg_url_module'])){
                
                $cont_urls = $this->get_rule_module_rule_data(array(
                    'rule' => $config['reg_url'],
                    'rule_merge' => $config['reg_url_merge'],
                    'rule_multi' => true,
                    'rule_multi_type' => 'loop'
                ), $html,$parentMatches, true ,true);
                
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
        
        init_array($cont_urls);
        init_array($cont_urls_matches);
        
        
        $pnNum=array(
            'nums'=>false,
            'has'=>$this->pn_number_exists($config['reg_url_merge']),
            'ix'=>0,
            'count'=>0,
        );
        if($isPagination&&$config['number']['open']){
            
            $pnNum['nums']=\util\Funcs::increase_nums($config['number']['start'],$config['number']['end'],$config['number']['inc'],$config['number']['desc'],$config['number']['len']);
            $pnNum['count']=count($pnNum['nums']);
            if($pnNum['nums']){
                if(empty($config['number']['url_mode'])){
                    
                    if(empty($config['reg_url'])||$config['reg_url']=='^.{0}'){
                        
                        $cont_urls=array();
                        $cont_urls_matches=array();
                        foreach ($pnNum['nums'] as $k=>$v){
                            $cont_urls[]=$this->merge_match_signs($parentMatches, $config['reg_url_merge']);
                            $cont_urls_matches[]=array('match@pn_number'=>$v);
                        }
                    }
                }elseif($config['number']['url_mode']=='one_num'){
                    
                    if(count($cont_urls)>1){
                        reset($cont_urls);
                        $cont_urls=current($cont_urls);
                        $cont_urls=array($cont_urls);
                        if(count($cont_urls_matches)>1){
                            reset($cont_urls_matches);
                            $cont_urls_matches=current($cont_urls_matches);
                            $cont_urls_matches=array($cont_urls_matches);
                        }
                    }
                }
            }
        }
        
        $doComplete=false;
        $doMust=false;
        $doBan=false;
        if(!empty($completeUrlInfo)&&is_array($completeUrlInfo)){
            
            $doComplete=true;
        }
        if(!empty($config['url_must'])){
            $doMust=true;
        }
        if(!empty($config['url_ban'])){
            $doBan=true;
        }
        
        $urlCharset='';
        if(!empty($this->config['url_encode'])){
            
            $urlWebConfig=$this->get_page_config($pageType,$pageName,'url_web');
            $urlCharset=$this->page_url_web_charset($urlWebConfig);
            if(empty($urlCharset)||in_array($urlCharset,array('auto','utf-8','utf8'))){
                $urlCharset='';
            }
        }
        
        $contUrlsCount=count($cont_urls);
        $oldContUrls=array();
        $urlMatchesMd5s=array();
        
        foreach ($cont_urls as $k=>$contUrl){
            if(!isset($contUrl)){
                $contUrl='';
            }
            if($pnNum['nums']){
                $oldContUrls[$k]=$contUrl;
            }
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
            
            if(in_array($urlMatchesMd5,$urlMatchesMd5s)||empty($contUrl)||strpos($contUrl,' ')!==false){
                
                $doDelete=true;
            }else{
                
                if($pnNum['nums']){
                    
                    if($pnNum['has']){
                        
                        $contUrl=$this->pn_number_replace($contUrl, $pnNum['nums'][$pnNum['ix']]);
                    }
                    $urlMatches['@pn_number']=$pnNum['nums'][$pnNum['ix']];
                    unset($urlMatches['match@pn_number']);
                    $pnNum['ix']++;
                }
                if(!$doDelete){
                    $doDelete=$this->_rule_match_urls_url($contUrl,$config,$completeUrlInfo,$urlCharset,$doComplete,$doMust,$doBan);
                }
            }
            if($doDelete){
                
                unset($cont_urls[$k]);
                unset($cont_urls_matches[$k]);
                unset($oldContUrls[$k]);
            }else{
                $cont_urls[$k]=$contUrl;
                $urlMatchesMd5s[]=$urlMatchesMd5;
                $cont_urls_matches[$k]=$urlMatches;
            }
        }
        
        if($pnNum['nums']){
            
            $totalCount=count($cont_urls);
            if($contUrlsCount!==$totalCount){
                
                $pnNum['ix']=0;
                foreach ($cont_urls as $k=>$contUrl){
                    if($pnNum['has']){
                        
                        $contUrl=$this->pn_number_replace($oldContUrls[$k], $pnNum['nums'][$pnNum['ix']]);
                    }
                    init_array($cont_urls_matches[$k]);
                    $cont_urls_matches[$k]['@pn_number']=$pnNum['nums'][$pnNum['ix']];
                    $pnNum['ix']++;
                    if($pnNum['has']){
                        
                        $doDelete=$this->_rule_match_urls_url($contUrl,$config,$completeUrlInfo,$urlCharset,$doComplete,$doMust,$doBan);
                        if($doDelete){
                            unset($cont_urls[$k]);
                            unset($cont_urls_matches[$k]);
                        }else{
                            $cont_urls[$k]=$contUrl;
                        }
                    }
                }
            }
            
            if($cont_urls&&($config['number']['url_mode']=='mult_num'||$config['number']['url_mode']=='one_num')){
                
                if($pnNum['count']<$totalCount){
                    
                    $contUrlNum=0;
                    foreach ($cont_urls as $k=>$v){
                        $contUrlNum++;
                        if($contUrlNum>$pnNum['count']){
                            unset($cont_urls[$k]);
                            unset($cont_urls_matches[$k]);
                        }
                    }
                }elseif($pnNum['count']>$totalCount){
                    
                    $contUrlEnd=array_keys($cont_urls);
                    $contUrlEnd=end($contUrlEnd);
                    
                    for($i=1;$i<=($pnNum['count']-$totalCount);$i++){
                        $contUrl=$cont_urls[$contUrlEnd];
                        $contUrlMatches=$cont_urls_matches[$contUrlEnd];
                        if($pnNum['has']){
                            
                            $contUrl=$this->pn_number_replace($oldContUrls[$contUrlEnd], $pnNum['nums'][$pnNum['ix']]);
                        }
                        init_array($contUrlMatches);
                        $contUrlMatches['@pn_number']=$pnNum['nums'][$pnNum['ix']];
                        $pnNum['ix']++;
                        $doDelete=false;
                        if($pnNum['has']){
                            
                            $doDelete=$this->_rule_match_urls_url($contUrl,$config,$completeUrlInfo,$urlCharset,$doComplete,$doMust,$doBan);
                        }
                        if(!$doDelete){
                            
                            $cont_urls[]=$contUrl;
                            $cont_urls_matches[]=$contUrlMatches;
                        }
                    }
                }
            }
        }
        
        return $this->page_convert_url_signs($pageType, $pageName, $isPagination, $cont_urls, $cont_urls_matches, $returnMatch);
    }
    
    private function _rule_match_urls_url(&$contUrl,$config,$completeUrlInfo,$urlCharset,$doComplete,$doMust,$doBan){
        $doDelete=false;
        if($doComplete){
            
            $contUrl=\util\Tools::create_complete_url($contUrl, $completeUrlInfo);
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
        
        if(!$doDelete&&!empty($this->config['url_encode'])){
            
            $contUrl=\util\Funcs::url_auto_encode($contUrl, $urlCharset);
        }
        
        if(!$doDelete&&strpos($contUrl,' ')!==false){
            
            $doDelete=true;
        }
        return $doDelete;
    }
    
    /*正则规则匹配数据*/
    public function get_rule_module_rule_data($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        if(!is_array($configParams)){
            $configParams=array();
        }
        $configParams['rule_flags']=$this->config['reg_regexp_flags'];
        
        return $this->rule_module_rule_data($configParams,$html,$parentMatches,$whole,$returnMatch);
    }
    
    
    public function page_convert_data_signs($pageType,$pageName,$mergeType,$data,$returnMatch=false){
        
        if(!empty($data)&&is_array($data)){
            $signs=$this->merge_str_signs(implode(' ',$data));
            if(!empty($signs)){
                
                $signs=$this->parent_page_signs2matches($this->parent_page_signs($pageType, $pageName, $mergeType));
                
                if(!$returnMatch){
                    foreach ($data as $k=>$v){
                        $data[$k]=$this->merge_match_signs($signs, $v);
                    }
                }else{
                    
                    init_array($signs);
                    return $signs;
                }
            }
        }
        return $returnMatch?array():$data;
    }
    
    /*页面转换网址标签参数*/
    public function page_convert_url_signs($pageType,$pageName,$isPagination,$cont_urls,$cont_urls_matches,$returnMatch=false){
        $urlPostKeys=array();
        $urlRenderKeys=array();
        $urlPostList=array();
        $urlRenderList=array();
        $echoMsg=\util\Param::is_task_close_echo()?false:true;
        
        $pageUrlWeb=$this->get_page_config($pageType,$pageName,'url_web');
        $pnConfig=null;
        $urlWebConfig=null;
        if($isPagination){
            
            $pnConfig=$this->get_page_config($pageType,$pageName,'pagination');
            $urlWebConfig=$this->pagination_url_web_config($pageUrlWeb,$pnConfig);
        }else{
            
            $urlWebConfig=$pageUrlWeb;
        }
        if($this->page_url_web_opened($pageUrlWeb,$pnConfig)){
            
            $urlsForms=array();
            if($this->page_url_web_opened($pageUrlWeb,$pnConfig,true)){
                
                $formData=$this->arrays_to_key_val($pageUrlWeb['form_names'], $pageUrlWeb['form_vals']);
                if(!empty($formData)&&is_array($formData)){
                    if(!$isPagination){
                        
                        $formParentMatches=$this->page_convert_data_signs($pageType, $pageName, 'form', $formData, true);
                        foreach ($cont_urls as $k=>$v){
                            
                            $urlFormData=array();
                            $urlParentMatches=is_array($cont_urls_matches[$k])?array_merge($formParentMatches,$cont_urls_matches[$k]):$formParentMatches;
                            foreach ($formData as $fk=>$fv){
                                $urlFormData[$fk]=$this->merge_match_signs($urlParentMatches,$fv);
                            }
                            $urlsForms[$k]=$urlFormData;
                        }
                    }else{
                        
                        $formData=$this->page_convert_data_signs($pageType, $pageName, 'form', $formData);
                        foreach ($cont_urls as $k=>$v){
                            $urlsForms[$k]=$formData;
                        }
                    }
                }
            }
            if($this->pagination_url_web_opened($pnConfig)){
                
                $formData=$this->arrays_to_key_val($pnConfig['url_web']['form_names'], $pnConfig['url_web']['form_vals']);
                if(!empty($formData)&&is_array($formData)){
                    $formData=$this->pn_replace_cur_url($pageType,$pageName,$isPagination,$formData);
                    $formParentMatches=$this->page_convert_data_signs($pageType, $pageName, 'pn:form', $formData, true);
                    $hasPnNum=$this->pn_number_exists($formData);
                    foreach ($cont_urls as $k=>$v){
                        
                        $urlFormData=array();
                        $urlParentMatches=is_array($cont_urls_matches[$k])?array_merge($formParentMatches,$cont_urls_matches[$k]):$formParentMatches;
                        foreach ($formData as $fk=>$fv){
                            $fv=$this->merge_match_signs($urlParentMatches,$fv);
                            if($hasPnNum){
                                $fv=$this->pn_number_replace($fv,$urlParentMatches['@pn_number']);
                            }
                            $urlFormData[$fk]=$fv;
                        }
                        $urlsForms[$k]=is_array($urlsForms[$k])?array_merge($urlsForms[$k],$urlFormData):$urlFormData;
                    }
                }
            }
            
            if(!empty($urlsForms)){
                if($urlWebConfig['form_method']=='post'){
                    
                    foreach ($cont_urls as $k=>$v){
                        
                        $urlPostKeys[$k]=md5(serialize($urlsForms[$k]));
                        if($echoMsg){
                            $urlPostList[$k]=$urlsForms[$k];
                        }
                    }
                }else{
                    
                    $charset=$this->page_url_web_charset($urlWebConfig);
                    if(!empty($charset)&&!in_array($charset,array('auto','utf-8','utf8'))){
                        
                        foreach ($urlsForms as $k=>$v){
                            $urlsForms[$k]=\util\Funcs::convert_charset($v, 'utf-8', $charset);
                        }
                    }
                    
                    foreach ($cont_urls as $k=>$v){
                        $vName='';
                        if(strpos($v,'#')!==false){
                            
                            if(preg_match('/(^.*?)\#(.*$)/',$v,$mv)){
                                $v=$mv[1];
                                $vName='#'.$mv[2];
                            }
                        }
                        $cont_urls[$k]=$v.(strpos($v,'?')===false?'?':'&').http_build_query($urlsForms[$k]).$vName;
                        unset($urlsForms[$k]);
                    }
                }
            }
            unset($urlsForms);
        }
        
        unset($pageUrlWeb);
        unset($urlWebConfig);
        
        
        $pageRenderer=$this->get_page_config($pageType,$pageName,'renderer');
        $rendererConfig=array();
        if($isPagination){
            $rendererConfig=$this->pagination_renderer_config($pageRenderer,$pnConfig);
        }else{
            $rendererConfig=$pageRenderer;
        }
        
        if($this->renderer_is_open(null,null,$pageRenderer,$pnConfig)){
            
            if($this->renderer_is_open(null,null,$pageRenderer,$pnConfig,true)){
                
                if(!empty($pageRenderer['types'])){
                    
                    if(!$isPagination){
                        
                        $renderParentMatches=$this->page_convert_data_signs($pageType, $pageName, 'renderer', $pageRenderer['contents'], true);
                        foreach ($cont_urls as $k=>$v){
                            
                            $renderContParentMatches=is_array($cont_urls_matches[$k])?array_merge($renderParentMatches,$cont_urls_matches[$k]):$renderParentMatches;
                            $renderContent=array();
                            foreach ($pageRenderer['contents'] as $rck=>$rcv){
                                
                                $renderContent[$rck]=$this->merge_match_signs($renderContParentMatches,$rcv);
                            }
                            $urlRenderList[$k]=array('types'=>$pageRenderer['types'],'elements'=>$pageRenderer['elements'],'contents'=>$renderContent);
                        }
                    }else{
                        
                        $renderContent=$this->page_convert_data_signs($pageType, $pageName, 'renderer', $pageRenderer['contents']);
                        foreach ($cont_urls as $k=>$v){
                            $urlRenderList[$k]=array('types'=>$pageRenderer['types'],'elements'=>$pageRenderer['elements'],'contents'=>$renderContent);
                        }
                    }
                }
            }
            
            if($this->pagination_renderer_opened($pnConfig)){
                
                if(!empty($pnConfig['renderer']['types'])){
                    
                    $pnConfig['renderer']['contents']=$this->pn_replace_cur_url($pageType,$pageName,$isPagination,$pnConfig['renderer']['contents']);
                    $renderParentMatches=$this->page_convert_data_signs($pageType, $pageName, 'pn:renderer', $pnConfig['renderer']['contents'], true);
                    $hasPnNum=$this->pn_number_exists($pnConfig['renderer']['contents']);
                    foreach ($cont_urls as $k=>$v){
                        
                        $renderContParentMatches=is_array($cont_urls_matches[$k])?array_merge($renderParentMatches,$cont_urls_matches[$k]):$renderParentMatches;
                        $renderContent=array();
                        foreach ($pnConfig['renderer']['contents'] as $rck=>$rcv){
                            
                            $rcv=$this->merge_match_signs($renderContParentMatches,$rcv);
                            if($hasPnNum){
                                $rcv=$this->pn_number_replace($rcv,$renderContParentMatches['@pn_number']);
                            }
                            $renderContent[$rck]=$rcv;
                        }
                        $renderContent=array('types'=>$pnConfig['renderer']['types'],'elements'=>$pnConfig['renderer']['elements'],'contents'=>$renderContent);
                        if($urlRenderList[$k]){
                            
                            init_array($urlRenderList[$k]['types']);
                            init_array($urlRenderList[$k]['elements']);
                            init_array($urlRenderList[$k]['contents']);
                            init_array($renderContent['types']);
                            init_array($renderContent['elements']);
                            init_array($renderContent['contents']);
                            foreach ($renderContent['types'] as $kk=>$kv){
                                $urlRenderList[$k]['types'][]=$kv;
                                $urlRenderList[$k]['elements'][]=$renderContent['elements'][$kk];
                                $urlRenderList[$k]['contents'][]=$renderContent['contents'][$kk];
                            }
                        }else{
                            $urlRenderList[$k]=$renderContent;
                        }
                    }
                }
            }
            if(!empty($urlRenderList)){
                
                foreach ($urlRenderList as $k=>$v){
                    $urlRenderKeys[$k]=md5(serialize($v));
                }
                if(!$echoMsg){
                    unset($urlRenderList);
                }
            }
        }
        if(!empty($urlPostKeys)||!empty($urlRenderKeys)){
            foreach ($cont_urls as $k=>$v){
                $urlPostKeys[$k]=$urlPostKeys[$k]?:'';
                $urlRenderKeys[$k]=$urlRenderKeys[$k]?:'';
                $vUrl='';
                $vUrlKey='';
                if($urlPostKeys[$k]){
                    $vUrl.='post_';
                    $vUrlKey=$urlPostKeys[$k];
                }
                if($urlRenderKeys[$k]){
                    $vUrl.='render_';
                    if($vUrlKey){
                        $vUrlKey=md5($vUrlKey.$urlRenderKeys[$k]);
                    }else{
                        $vUrlKey=$urlRenderKeys[$k];
                    }
                }
                if($vUrl){
                    
                    $vUrl='#'.$vUrl.$vUrlKey;
                    $cont_urls[$k]=$v.$vUrl;
                    if($echoMsg){
                        \util\Param::set_echo_url_msg($vUrl, array('post'=>$urlPostList[$k],'renderer'=>$urlRenderList[$k]));
                    }
                }
            }
        }
        if($returnMatch){
            
            $return=array('urls'=>array(),'matches'=>array());
            foreach($cont_urls as $k=>$v){
                $v=stripslashes($v);
                if(!in_array($v, $return['urls'])){
                    
                    $return['urls'][]=$v;
                    $return['matches'][md5($v)]=$cont_urls_matches[$k];
                }
            }
            return $return;
        }else{
            
            $cont_urls=array_values($cont_urls);
            init_array($cont_urls);
            $cont_urls=array_map('stripslashes',$cont_urls);
            return $cont_urls;
        }
    }
    
    
    /*将页面标签转换成match值*/
    public function parent_page_signs2matches($parentPageSigns){
        $matches=array();
        if(!empty($parentPageSigns)&&is_array($parentPageSigns)){
            $signTypes=array('area','url','content');
            if(!empty($parentPageSigns['cur'])&&is_array($parentPageSigns['cur'])){
                
                $curPage=$parentPageSigns['cur'];
                if($curPage['is_pagination']){
                    
                    if(is_array($curPage['area'])){
                        foreach ($curPage['area'] as $sign){
                            $matches['match'.$sign['id']]=\util\Funcs::array_get($this->pn_area_matches,array($curPage['page_type'],$curPage['page_name'],'match'.$sign['id']));
                        }
                    }
                    if(is_array($curPage['url'])){
                        $curUrlMd5=$this->page_source_merge($curPage['page_type'],$curPage['page_name']);
                        $curUrlMd5=md5($this->cur_pagination_urls[$curUrlMd5]);
                        foreach ($curPage['url'] as $sign){
                            $matches['match'.$sign['id']]=\util\Funcs::array_get($this->pn_url_matches,array($curPage['page_type'],$curPage['page_name'],$curUrlMd5,'match'.$sign['id']));
                        }
                    }
                }else{
                    foreach($signTypes as $signType){
                        $this->_page_signs2matches($signType, $curPage[$signType], $curPage['page_type'], $curPage['page_name'], $matches);
                    }
                }
            }
            
            $pageTypes=array('front_url','source_url','level_url','url','relation_url');
            
            foreach ($pageTypes as $pageType){
                if(!empty($parentPageSigns[$pageType])&&is_array($parentPageSigns[$pageType])){
                    if($this->page_is_list($pageType)){
                        
                        foreach ($parentPageSigns[$pageType] as $pageName=>$pageSigns){
                            foreach($signTypes as $signType){
                                $this->_page_signs2matches($signType, $pageSigns[$signType], $pageType, $pageName, $matches);
                            }
                        }
                    }else{
                        foreach($signTypes as $signType){
                            $this->_page_signs2matches($signType, $parentPageSigns[$pageType][$signType], $pageType, '', $matches);
                        }
                    }
                }
            }
        }
        return $matches;
    }
    
    private function _page_signs2matches($signType,$signs,$pageType,$pageName,&$matches){
        if(is_array($signs)){
            if($signType=='area'){
                
                foreach ($signs as $sign){
                    $matches['match'.$sign['id']]=$this->get_page_area_match($pageType,$pageName,'match'.$sign['id']);
                }
            }elseif($signType=='url'){
                
                if($pageType=='level_url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_url_match('level_url',$pageName,md5($this->cur_level_urls[$pageName]?:''),'match'.$sign['id']);
                    }
                }elseif($pageType=='url'){
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_url_match('url','',md5($this->cur_cont_url),'match'.$sign['id']);
                    }
                }else{
                    foreach ($signs as $sign){
                        $matches['match'.$sign['id']]=$this->get_page_url_match($pageType,$pageName,null,'match'.$sign['id']);
                    }
                }
            }elseif($signType=='content'){
                
                foreach ($signs as $sign){
                    $matches['match'.$sign['id']]=$this->get_page_content_match($pageType,$pageName,'match'.$sign['id']);
                }
            }
        }
    }
    
    
    /*获取父级页面标签*/
    public function parent_page_signs($pageType,$pageName,$mergeType=null){
        $mergeType=empty($mergeType)?'':$mergeType;
        $isPn=false;
        if(strpos($mergeType,'pn:')===0){
            
            $isPn=true;
        }
        $pageSource=$this->page_source_merge($pageType, $pageName);
        
        if(!is_array($this->config_params['signs'])){
            $this->config_params['signs']=array();
        }
        if(!is_array($this->config_params['signs'][$pageSource])){
            $this->config_params['signs'][$pageSource]=array();
        }
        $foundPageSigns=$this->config_params['signs'][$pageSource][$mergeType];
        if(!isset($foundPageSigns)){
            
            $foundPageSigns=array('cur'=>null,'front_url'=>array(),'source_url'=>null,'level_url'=>array(),'url'=>null,'relation_url'=>array());
            if(!$isPn||($isPn&&$this->page_has_pagination($pageType)&&$this->pagination_is_open($pageType,$pageName))){
                
                
                $unknownPageSigns=$this->_page_signs_search($pageType,$pageName,$mergeType,$foundPageSigns);
                if($pageType=='relation_url'){
                    
                    
                    if(!empty($unknownPageSigns)){
                        $relationParentPages=$this->relation_parent_pages($pageName, $this->config['new_relation_urls']);
                        foreach ($relationParentPages as $relationParentPage){
                            if(empty($unknownPageSigns)){
                                
                                break;
                            }
                            
                            $unknownPageSigns=$this->_parent_page_signs_content('relation_url',$relationParentPage,$unknownPageSigns,$foundPageSigns);
                            
                            if(!empty($unknownPageSigns)){
                                $unknownPageSigns=$this->_parent_page_signs_rule('url',implode('',$unknownPageSigns),'relation_url',$relationParentPage,$foundPageSigns);
                            }
                            
                            if(!empty($unknownPageSigns)){
                                $unknownPageSigns=$this->_parent_page_signs_rule('area',implode('',$unknownPageSigns),'relation_url',$relationParentPage,$foundPageSigns);
                            }
                        }
                    }
                }
                if($pageType=='relation_url'||($pageType=='url'&&$isPn)){
                    
                    if(!empty($unknownPageSigns)){
                        
                        
                        $unknownPageSigns=$this->_parent_page_signs_content('url','',$unknownPageSigns,$foundPageSigns);
                        
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_rule('url',implode('',$unknownPageSigns),'url','',$foundPageSigns);
                        }
                        
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_rule('area',implode('',$unknownPageSigns),'url','',$foundPageSigns);
                        }
                    }
                }
                if($pageType!='front_url'&&$pageType!='source_url'){
                    
                    if(!empty($this->config['new_level_urls'])){
                        if(!empty($unknownPageSigns)){
                            $levelNames=array_keys($this->config['new_level_urls']);
                            if($pageType=='level_url'&&!$isPn){
                                
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
                                
                                $unknownPageSigns=$this->_parent_page_signs_content('level_url',$levelName,$unknownPageSigns,$foundPageSigns);
                                
                                if(!empty($unknownPageSigns)){
                                    $unknownPageSigns=$this->_parent_page_signs_rule('url',implode('',$unknownPageSigns),'level_url',$levelName,$foundPageSigns);
                                }
                                
                                if(!empty($unknownPageSigns)){
                                    $unknownPageSigns=$this->_parent_page_signs_rule('area',implode('',$unknownPageSigns),'level_url',$levelName,$foundPageSigns);
                                }
                            }
                        }
                    }
                }
                if(($pageType!='front_url'&&$pageType!='source_url')||($pageType=='source_url'&&$isPn)){
                    
                    
                    if(!$this->source_is_url()){
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_content('source_url','',$unknownPageSigns,$foundPageSigns);
                        }
                    }
                }
                
                if(!empty($this->config['new_front_urls'])){
                    $frontNames=array_keys($this->config['new_front_urls']);
                    if($pageType=='front_url'){
                        
                        $frontNames1=array();
                        foreach($frontNames as $frontName){
                            if($pageName==$frontName){
                                
                                break;
                            }
                            $frontNames1[]=$frontName;
                        }
                        $frontNames=$frontNames1;
                    }
                    $frontNames=array_reverse($frontNames);
                    
                    foreach ($frontNames as $frontName){
                        if(empty($unknownPageSigns)){
                            
                            break;
                        }else{
                            $unknownPageSigns=$this->_parent_page_signs_content('front_url',$frontName,$unknownPageSigns,$foundPageSigns);
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
        $isPn=false;
        $mergeType=empty($mergeType)?'':$mergeType;
        if(strpos($mergeType,'pn:')===0){
            
            $mergeType=str_replace('pn:','',$mergeType);
            $isPn=true;
        }
        $unknownPageSigns=array();
        $pageConfig=null;
        $pConfig=$this->get_page_config($pageType,$pageName);
        $pnConfig=null;
        if($isPn){
            if($this->page_has_pagination($pageType)){
                
                $pnConfig=$this->get_page_config($pageType,$pageName,'pagination');
                $pageConfig=$pnConfig;
            }
        }else{
            $pageConfig=$pConfig;
        }
        if(!empty($pageConfig)){
            
            if(!is_array($foundPageSigns['cur'])){
                $foundPageSigns['cur']=array();
            }
            $signMatch=$this->sign_addslashes(cp_sign('match',':id'));
            
            $pageContentSignMerge='';
            if(empty($mergeType)||$mergeType=='content_sign'){
                $contentSigns=is_array($pageConfig['content_signs'])?$pageConfig['content_signs']:array();
                foreach ($contentSigns as $v){
                    if($v['identity']&&!empty($v['func'])){
                        
                    }
                }
                $pageContentSignRule='';
                foreach ($contentSigns as $v){
                    if($v['identity']){
                        $pageContentSignRule.=cp_sign('match',$v['identity']);
                    }
                }
                $pageContentSignRule=$this->convert_sign_match($pageContentSignRule);
                $pageSigns=$this->signs_not_in_rule($pageContentSignRule,$pageContentSignMerge,false,false,true);
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
                    $foundPageSigns['cur']['content']=$pageSigns['found'];
                }
            }
            $foundContentIsArr=is_array($foundPageSigns['cur']['content'])?true:false;
            
            $ruleWhole=$this->page_rule_is_null($pageType,$isPn)?false:true;
            if(empty($mergeType)||$mergeType=='content_sign'||$mergeType=='renderer'||in_array($mergeType,$inUrlRule)){
                
                $pageRendererMerge='';
                if(empty($mergeType)||$mergeType=='renderer'){
                    
                    if($this->renderer_is_open(null,null,$pConfig['renderer'],$pnConfig)){
                        if(is_array($pageConfig['renderer']['types'])&&is_array($pageConfig['renderer']['contents'])){
                            $pageRendererMerge=array();
                            foreach ($pageConfig['renderer']['types'] as $k=>$v){
                                if($this->renderer_type_has_option($v, 'content')){
                                    $pageRendererMerge[]=$pageConfig['renderer']['contents'][$k];
                                }
                            }
                            $pageRendererMerge=implode(' ', $pageRendererMerge);
                        }
                    }
                }
                
                $openUrlWeb=$this->page_url_web_opened($pConfig['url_web'],$pnConfig);
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
                $pageUrlMerge='';
                if(empty($mergeType)||$mergeType=='url'){
                    if(!$this->page_rule_is_null($pageType,$isPn)){
                        
                        $pageUrlMerge=$pageConfig['reg_url_merge'];
                    }elseif($pageType=='front_url'){
                        
                        $pageUrlMerge=$pageConfig['url'];
                    }elseif($pageType=='source_url'){
                        
                        if(is_array($this->config ['source_url'])){
                            $pageUrlMerge=implode("\r\n", $this->config ['source_url']);
                        }
                    }
                }
                
                $pageSigns=$this->signs_not_in_rule($pageConfig['reg_url'],$pageUrlMerge.$pageHeaderMerge.$pageFormMerge.$pageRendererMerge.implode('',$unknownPageSigns),$ruleWhole,false,true);
                if(is_array($pageSigns['unknown'])){
                    $unknownPageSigns=$pageSigns['unknown'];
                }
                if(is_array($pageSigns['found'])){
                    foreach ($pageSigns['found'] as $k=>$v){
                        if(preg_match('/^'.$signMatch.'$/i',$v,$msign)){
                            
                            if($foundContentIsArr&&isset($foundPageSigns['cur']['content'][$v])){
                                
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
                    $foundPageSigns['cur']['url']=$pageSigns['found'];
                }
            }
            $foundUrlIsArr=is_array($foundPageSigns['cur']['url'])?true:false;
            
            $pageAreaMerge='';
            if(empty($mergeType)||$mergeType=='area'){
                if(!$this->page_rule_is_null($pageType,$isPn)){
                    
                    $pageAreaMerge=$pageConfig['reg_area_merge'];
                }
            }
            $pageSigns=$this->signs_not_in_rule($pageConfig['reg_area'],$pageAreaMerge.implode('',$unknownPageSigns),$ruleWhole,false,true);
            if(is_array($pageSigns['unknown'])){
                $unknownPageSigns=$pageSigns['unknown'];
            }
            if(is_array($pageSigns['found'])){
                foreach ($pageSigns['found'] as $k=>$v){
                    if(preg_match('/^'.$signMatch.'$/i',$v,$msign)){
                        
                        if(($foundContentIsArr&&isset($foundPageSigns['cur']['content'][$v]))||($foundUrlIsArr&&isset($foundPageSigns['cur']['url'][$v]))){
                            
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
                if($isPn){
                    $foundPageSigns['cur']['is_pagination']=true;
                }
            }
        }
        return $unknownPageSigns;
    }
    
    /*找出父页面规则中不存在的标签*/
    private function _parent_page_signs_rule($ruleType,$mergeStr,$pageType,$pageName,&$foundPageSigns){
        $ruleStr=$this->get_page_config($pageType,$pageName,'reg_'.$ruleType);
        $ruleStr=$ruleStr?$ruleStr:'';
        $ruleWhole=$this->page_rule_is_null($pageType)?false:true;
        $pageSigns=$this->signs_not_in_rule($ruleStr,$mergeStr,$ruleWhole,false,true);
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
    
    /*找出父页面提取内容中不存在的标签*/
    private function _parent_page_signs_content($pageType,$pageName,$unknownSigns,&$foundPageSigns){
        $unknownSigns=is_array($unknownSigns)?$unknownSigns:array();
        $contentSigns=$this->get_page_config($pageType,$pageName,'content_signs');
        $contentSigns=is_array($contentSigns)?$contentSigns:array();
        
        $foundSigns=array();
        foreach ($contentSigns as $v){
            if($v['identity']){
                $sign=cp_sign('match',$v['identity']);
                if(isset($unknownSigns[$sign])){
                    unset($unknownSigns[$sign]);
                    $foundSigns[$sign]=$sign;
                }
            }
        }
        
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
                
                if($this->page_is_list($pageType)){
                    
                    if(!is_array($foundPageSigns[$pageType][$pageName])){
                        $foundPageSigns[$pageType][$pageName]=array();
                    }
                    if(!is_array($foundPageSigns[$pageType][$pageName]['content'])){
                        $foundPageSigns[$pageType][$pageName]['content']=array();
                    }
                    
                    foreach ($foundSigns as $k=>$v){
                        $foundPageSigns[$pageType][$pageName]['content'][$k]=$v;
                    }
                }else{
                    
                    if(!is_array($foundPageSigns[$pageType])){
                        $foundPageSigns[$pageType]=array();
                    }
                    if(!is_array($foundPageSigns[$pageType]['content'])){
                        $foundPageSigns[$pageType]['content']=array();
                    }
                    
                    foreach ($foundSigns as $k=>$v){
                        $foundPageSigns[$pageType]['content'][$k]=$v;
                    }
                }
            }
        }
        return is_array($unknownSigns)?$unknownSigns:array();
    }
    
    
    public function get_page_content_match($pageType,$pageName,$match=null){
        $keys=array($pageType,$pageName);
        if(isset($match)){
            $keys[]=$match;
        }
        return \util\Funcs::array_get($this->page_content_matches, $keys);
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
    
    /*数据源下拉框数据*/
    public function page_source_options(){
        $configisArr=is_array($this->config)?true:false;
        $pageSources=array();
        if($configisArr&&is_array($this->config['new_front_urls'])){
            foreach ($this->config['new_front_urls'] as $k=>$v){
                $pageSources[$this->page_source_merge('front_url', $k)]=$this->page_source_name('front_url', $k);
            }
        }
        if(!$this->source_is_url()){
            
            $pageSources['source_url']='起始页';
            if($configisArr&&is_array($this->config['new_level_urls'])){
                foreach ($this->config['new_level_urls'] as $k=>$v){
                    $pageSources[$this->page_source_merge('level_url', $k)]=$this->page_source_name('level_url', $k);
                }
            }
        }
        $pageSources['url']='内容页';
        if($configisArr&&is_array($this->config['new_relation_urls'])){
            foreach ($this->config['new_relation_urls'] as $k=>$v){
                $pageSources[$this->page_source_merge('relation_url', $k)]=$this->page_source_name('relation_url', $k);
            }
        }
        return $pageSources;
    }
    
    
    public function page_opened_tips($pageType,$pageName='',$isPagination=false,$returnHtml=false){
        $tips='';
        if($this->page_is_post($pageType,$pageName,$isPagination)){
            $tips.=$returnHtml?'<span class="label label-default label-custom-opened">post</span> ':'[post] ';
        }
        if($this->renderer_is_open($pageType,$pageName,null,$isPagination)){
            $tips.=$returnHtml?'<span class="label label-default label-custom-opened">渲染</span> ':'[渲染] ';
        }
        return $tips;
    }
    
    
    public function page_render_is_open(){
        static $pages=array('front_url','level_url','relation_url');
        $opened=false;
        foreach ($pages as $page){
            if(!$opened){
                
                $pageData=$this->get_config('new_'.$page.'s');
                if(is_array($pageData)){
                    foreach ($pageData as $k=>$v){
                        $opened=$this->renderer_is_open($page,$k);
                        if($opened){
                            
                            break;
                        }
                    }
                }
            }
        }
        
        if(!$opened){
            $opened=$this->renderer_is_open('source_url');
        }
        
        if(!$opened){
            $opened=$this->renderer_is_open('url');
        }
        return $opened;
    }
    
    public function pagination_is_open($pageType,$pageName='',$paginationConfig=null){
        if($pageType){
            $paginationConfig=$this->get_page_config($pageType,$pageName,'pagination');
        }
        if($paginationConfig&&is_array($paginationConfig)&&$paginationConfig['open']){
            return true;
        }else{
            return false;
        }
    }
    
    public function renderer_is_open($pageType,$pageName='',$rendererConfig=null,$paginationConfig=null,$onlyUseRenderer=false){
        $opened=$this->get_config('page_render');
        if($pageType){
            
            $rendererConfig=$this->get_page_config($pageType,$pageName,'renderer');
            if($paginationConfig){
                
                $paginationConfig=$this->get_page_config($pageType,$pageName,'pagination');
            }
        }
        
        if(!empty($paginationConfig)&&is_array($paginationConfig)&&$paginationConfig['use_renderer']){
            
            $opened=$paginationConfig['use_renderer']=='y'?true:false;
        }else{
            if(!empty($rendererConfig)&&is_array($rendererConfig)&&$rendererConfig['open']){
                
                $opened=$rendererConfig['open']=='y'?true:false;
            }
        }
        if(!$onlyUseRenderer){
            
            $pnOpened=$this->pagination_renderer_opened($paginationConfig);
            if(isset($pnOpened)){
                
                $opened=$pnOpened;
            }
        }
        return $opened;
    }
    
    public function pagination_renderer_opened($paginationConfig){
        
        $opened=null;
        if(!empty($paginationConfig)&&is_array($paginationConfig)&&is_array($paginationConfig['renderer'])&&$paginationConfig['renderer']['open_pn']){
            
            $opened=$this->get_config('page_render');
            if($paginationConfig['renderer']['open']){
                
                $opened=$paginationConfig['renderer']['open']=='y'?true:false;
            }
        }
        return $opened;
    }
    
    public function pagination_renderer_config($renderConfig,$paginationConfig){
        init_array($renderConfig);
        init_array($paginationConfig);
        if($this->renderer_is_open(null,null,$renderConfig,$paginationConfig)){
            
            if($this->pagination_renderer_opened($paginationConfig)){
                
                foreach ($paginationConfig['renderer'] as $k=>$v){
                    $renderConfig[$k]=$v;
                }
            }
        }
        init_array($renderConfig);
        return $renderConfig;
    }
    
    /*页面是否是post模式*/
    public function page_is_post($pageType,$pageName='',$isPagination=false){
        $urlWebConfig=$this->get_page_config($pageType,$pageName,'url_web');
        $pnConfig=null;
        if($isPagination){
            $pnConfig=$this->get_page_config($pageType,$pageName,'pagination');
        }
        $isPost=false;
        if($this->page_url_web_opened($urlWebConfig,$pnConfig)){
            if($isPagination){
                $urlWebConfig=$this->pagination_url_web_config($urlWebConfig,$pnConfig);
            }
            if(is_array($urlWebConfig)&&$urlWebConfig['form_method']=='post'){
                $isPost=true;
            }
        }
        return $isPost;
    }
    
    
    public function page_rule_is_null($pageType,$isPagination=false){
        if($isPagination){
            
            if($this->page_has_pagination($pageType)){
                
                return false;
            }else{
                return true;
            }
        }else{
            if($pageType=='front_url'||$pageType=='source_url'||($pageType=='url'&&$this->source_is_url())){
                
                return true;
            }else{
                return false;
            }
        }
    }
    
    
    public function page_url_web_opened($urlWebConfig,$paginationConfig=null,$onlyUseUrlWeb=false){
        $opened=false;
        if($paginationConfig&&is_array($paginationConfig)&&$paginationConfig['use_url_web']){
            
            $opened=$paginationConfig['use_url_web']=='y'?true:false;
        }else{
            
            if($urlWebConfig&&is_array($urlWebConfig)&&!empty($urlWebConfig['open'])){
                $opened=true;
            }
        }
        if(!$onlyUseUrlWeb){
            
            $pnOpened=$this->pagination_url_web_opened($paginationConfig);
            if(isset($pnOpened)){
                
                $opened=$pnOpened;
            }
        }
        return $opened;
    }
    
    public function pagination_url_web_opened($paginationConfig){
        $opened=null;
        if($paginationConfig&&is_array($paginationConfig)&&is_array($paginationConfig['url_web'])&&$paginationConfig['url_web']['open']){
            $opened=true;
        }
        return $opened;
    }
    
    
    public function pagination_url_web_config($urlWebConfig,$paginationConfig){
        $config=array();
        if($this->page_url_web_opened($urlWebConfig,$paginationConfig,true)){
            
            $config=$urlWebConfig;
        }
        init_array($config);
        if($this->pagination_url_web_opened($paginationConfig)){
            
            foreach ($paginationConfig['url_web'] as $k=>$v){
                
                $config[$k]=$v;
            }
        }
        return $config;
    }
    
    
    public function page_url_web_charset($urlWebConfig){
        $charset='';
        if($this->page_url_web_opened($urlWebConfig)){
            $charset=$urlWebConfig['charset']=='custom'?$urlWebConfig['charset_custom']:$urlWebConfig['charset'];
        }
        if(empty($charset)){
            
            $charset=$this->config['charset'];
        }
        $charset=strtolower($charset);
        return $charset;
    }
    
    public function page_url_web_encode($urlWebConfig){
        $encode='';
        if($this->page_url_web_opened($urlWebConfig)){
            $encode=$urlWebConfig['encode']=='custom'?$urlWebConfig['encode_custom']:$urlWebConfig['encode'];
        }
        if(empty($encode)){
            
            $encode=$this->config['encode'];
        }
        $encode=isset($encode)?strtolower($encode):'';
        return $encode;
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
    
    /*获取页面配置*/
    public function get_page_config($pageType,$pageName='',$prop=null){
        $pageName=$pageName?$pageName:'';
        if($pageType=='source_url'){
            
            if($this->source_is_url()){
                $pageType='url';
            }
        }
        $key1=null;
        $key2=null;
        $key3=null;
        switch ($pageType){
            case 'front_url':$key1='new_front_urls';$key2=$pageName;$key3=$prop;break;
            case 'source_url':$key1='source_config';$key2=$prop;$key3=null;break;
            case 'url':
                if(!isset($prop)){
                    
                    return $this->config;
                }else{
                    $key1=$prop;
                    $key2=null;
                    $key3=null;
                }
                break;
            case 'level_url':$key1='new_level_urls';$key2=$pageName;$key3=$prop;break;
            case 'relation_url':$key1='new_relation_urls';$key2=$pageName;$key3=$prop;break;
            default:return null;break;
        }
        return $this->get_config($key1,$key2,$key3);
    }
    
    
    /*起始页设为了内容页*/
    public function source_is_url(){
        return $this->get_config('source_is_url')?true:false;
    }
    
    public function pn_number_exists($data){
        if($data){
            if(is_array($data)){
                $data=implode('',$data);
            }
            $data=strpos($data,'[分页序号]')!==false?true:false;
        }else{
            $data=false;
        }
        return $data;
    }
    
    public function pn_number_replace($str,$val){
        if($str){
            $val=isset($val)?$val:'';
            $str=str_replace('[分页序号]', $val, $str);
        }
        return $str;
    }
    
    
    public function pn_replace_cur_url($pageType,$pageName,$isPagination,$data){
        if($isPagination&&$data){
            
            if(is_array($data)){
                foreach ($data as $k=>$v){
                    $data[$k]=$this->pn_replace_cur_url($pageType,$pageName,$isPagination,$v);
                }
            }elseif(is_string($data)&&strpos($data,'[当前网址]')!==false){
                $curUrl=$this->cur_page_source_url($pageType, $pageName);
                $data=str_replace('[当前网址]', $curUrl, $data);
            }
        }
        return $data;
    }
    
    public function cur_page_source_url($pageType,$pageName){
        $curUrl='';
        switch ($pageType){
            case 'front_url':$curUrl=$this->cur_front_urls[$pageName];break;
            case 'source_url':$curUrl=$this->cur_source_url;break;
            case 'level_url':$curUrl=$this->cur_level_urls[$pageName];break;
            case 'url':$curUrl=$this->cur_cont_url;break;
        }
        $curUrl=$curUrl?:'';
        return $curUrl;
    }
    
    
    
    /*获取页面代码*/
    public function get_page_html($url,$pageType,$pageName,$isPagination=false,$returnInfo=false){
        $cacheKey=md5($url);
        
        $pageName=$pageName?$pageName:'';
        $headers=array();
        $pageSource=$this->page_source_merge($pageType, $pageName);
        
        $pageUrlWeb=$this->get_page_config($pageType,$pageName,'url_web');
        $pnConfig=null;
        $urlWebConfig=null;
        $pnNumberCur='';
        if($isPagination){
            
            $pnConfig=$this->get_page_config($pageType,$pageName,'pagination');
            $urlWebConfig=$this->pagination_url_web_config($pageUrlWeb,$pnConfig);
            if($pnConfig['number']['open']){
                
                $pnNumberCur=\util\Funcs::array_get($this->pn_url_matches,array($pageType,$pageName,md5($this->cur_pagination_urls[$pageSource]),'@pn_number'));
            }
        }else{
            
            $urlWebConfig=$pageUrlWeb;
        }
        
        $openUrlWeb=$this->page_url_web_opened($pageUrlWeb,$pnConfig);
        $openPageUw=$this->page_url_web_opened($pageUrlWeb,$pnConfig,true);
        
        if(!empty($pageSource)){
            
            $useCookie=\util\Param::get_gsc_use_cookie('',true);
            if($openUrlWeb){
                
                if($openPageUw){
                    
                    $headers=$this->arrays_to_key_val($pageUrlWeb['header_names'], $pageUrlWeb['header_vals']);
                    $headers=$this->page_convert_data_signs($pageType, $pageName, 'header', $headers);
                    init_array($headers);
                }
                
                if($isPagination&&$this->pagination_url_web_opened($pnConfig)){
                    
                    $pnHeaders=$this->arrays_to_key_val($pnConfig['url_web']['header_names'], $pnConfig['url_web']['header_vals']);
                    $pnHeaders=$this->pn_replace_cur_url($pageType, $pageName, $isPagination, $pnHeaders);
                    $pnHeaders=$this->page_convert_data_signs($pageType, $pageName, 'pn:header', $pnHeaders);
                    init_array($pnHeaders);
                    if(!empty($pnHeaders)){
                        
                        if($this->pn_number_exists($pnHeaders)){
                            foreach ($pnHeaders as $k=>$v){
                                $pnHeaders[$k]=$this->pn_number_replace($v, $pnNumberCur);
                            }
                        }
                        $headers=\util\Funcs::array_key_merge($headers,$pnHeaders);
                    }
                    unset($pnHeaders);
                }
                
                $globalHeaders=array();
                if(empty($urlWebConfig['header_global'])){
                    
                    $globalHeaders=$this->config_params['headers']['page'];
                }elseif($urlWebConfig['header_global']=='y'){
                    
                    $globalHeaders=$this->config_params['headers']['page_headers'];
                }
                init_array($globalHeaders);
                
                if(!empty($useCookie)){
                    unset($globalHeaders['cookie']);
                    $globalHeaders['cookie']=$useCookie;
                }
                if(!empty($globalHeaders)){
                    $headers=\util\Funcs::array_key_merge($globalHeaders,$headers);
                }
            }else{
                
                $headers=$this->config_params['headers']['page'];
                init_array($headers);
                
                if(!empty($useCookie)){
                    unset($headers['cookie']);
                    $headers['cookie']=$useCookie;
                }
            }
            init_array($headers);
        }
        
        $otherConfig=array('curlopts'=>array());
        
        $charset=$this->page_url_web_charset($urlWebConfig);
        $encode=$this->page_url_web_encode($urlWebConfig);
        if($encode){
            $otherConfig['curlopts'][CURLOPT_ENCODING]=$encode;
        }
        
        $filterUrl=false;
        
        $postData=null;
        if($openUrlWeb){
            
            
            $formData=null;
            if($openPageUw){
                
                $formData=$this->arrays_to_key_val($pageUrlWeb['form_names'], $pageUrlWeb['form_vals']);
                $formData=$this->page_convert_data_signs($pageType, $pageName, 'form', $formData);
                init_array($formData);
            }
            
            if($isPagination&&$this->pagination_url_web_opened($pnConfig)){
                
                $pnFormData=$this->arrays_to_key_val($pnConfig['url_web']['form_names'], $pnConfig['url_web']['form_vals']);
                $pnFormData=$this->pn_replace_cur_url($pageType, $pageName, $isPagination, $pnFormData);
                $pnFormData=$this->page_convert_data_signs($pageType, $pageName, 'pn:form', $pnFormData);
                init_array($pnFormData);
                if(!empty($pnFormData)){
                    
                    if($this->pn_number_exists($pnFormData)){
                        foreach ($pnFormData as $k=>$v){
                            $pnFormData[$k]=$this->pn_number_replace($v, $pnNumberCur);
                        }
                    }
                    $formData=\util\Funcs::array_key_merge($formData,$pnFormData);
                }
                unset($pnFormData);
            }
            
            $formData=is_array($formData)?$formData:'';
            
            if($urlWebConfig['form_method']=='post'){
                
                $filterUrl=true;
                $postData=$formData;
                if($urlWebConfig['content_type']){
                    $headers['content-type']=$urlWebConfig['content_type'];
                }
            }else{
                
                $postData=null;
            }
            unset($formData);
        }
        
        unset($pageUrlWeb);
        unset($urlWebConfig);
        
        
        $pageRenderer=$this->get_page_config($pageType,$pageName,'renderer');
        $rendererConfig=array();
        if($isPagination){
            $rendererConfig=$this->pagination_renderer_config($pageRenderer,$pnConfig);
        }else{
            $rendererConfig=$pageRenderer;
        }
        
        if(!$isPagination){
            
            $this->render_pn_sockets[$pageSource]=null;
        }
        
        if($this->renderer_is_open(null,null,$pageRenderer,$pnConfig)){
            
            $filterUrl=true;
            $rendererData=array();
            if($this->renderer_is_open(null,null,$pageRenderer,$pnConfig,true)){
                
                $pageRenderer['contents']=$this->page_convert_data_signs($pageType, $pageName, 'renderer', $pageRenderer['contents']);
                $rendererData=array(
                    'types'=>$pageRenderer['types'],
                    'elements'=>$pageRenderer['elements'],
                    'contents'=>$pageRenderer['contents']
                );
            }
            if($isPagination&&$this->pagination_renderer_opened($pnConfig)){
                
                $pnConfig['renderer']['contents']=$this->page_convert_data_signs($pageType, $pageName, 'pn:renderer', $pnConfig['renderer']['contents']);
                $pnConfig['renderer']['contents']=$this->pn_replace_cur_url($pageType, $pageName, $isPagination, $pnConfig['renderer']['contents']);
                
                if($this->pn_number_exists($pnConfig['renderer']['contents'])){
                    foreach ($pnConfig['renderer']['contents'] as $k=>$v){
                        $pnConfig['renderer']['contents'][$k]=$this->pn_number_replace($v, $pnNumberCur);
                    }
                }
                
                if(is_array($pnConfig['renderer']['types'])){
                    init_array($pnConfig['renderer']['elements']);
                    init_array($pnConfig['renderer']['contents']);
                    init_array($rendererData['types']);
                    init_array($rendererData['elements']);
                    init_array($rendererData['contents']);
                    foreach ($pnConfig['renderer']['types'] as $k=>$v){
                        $rendererData['types'][]=$v;
                        $rendererData['elements'][]=$pnConfig['renderer']['elements'][$k];
                        $rendererData['contents'][]=$pnConfig['renderer']['contents'][$k];
                    }
                }
            }
            $rendererConfig=array_merge($rendererConfig,$rendererData);
            $otherConfig['renderer']=$rendererConfig;
            
            if($pageSource&&$this->page_has_pagination($pageType)&&$this->pagination_is_open($pageType,$pageName)){
                
                $otherConfig['render_pn_page_source']=$pageSource;
                if($isPagination&&!\util\Tools::echo_url_msg_pn_id($url)){
                    
                    unset($otherConfig['render_pn_page_source']);
                }
                if(!$isPagination||empty($otherConfig['render_pn_page_source'])){
                    
                    $this->render_pn_sockets[$pageSource]=null;
                }
                $url=\util\Tools::echo_url_msg_pn_id($url,true);
            }
        }
        
        unset($pageRenderer);
        unset($rendererConfig);
        
        if($filterUrl){
            $url=\util\Tools::echo_url_msg_id($url, true);
        }
        
        $cacheKey=($cacheKey.' '.serialize(array($postData,$headers,$otherConfig['renderer'])));
        
        $htmlInfo=array();
        $html=null;
        
        if($isPagination){
            if($cacheKey!=$this->cache_pn_urls[$pageSource]){
                
                $this->cache_pn_urls[$pageSource]=$cacheKey;
                $this->cache_pn_htmls[$pageSource]=null;
            }
            if(isset($this->cache_pn_htmls[$pageSource])){
                
                $htmlInfo=$this->cache_pn_htmls[$pageSource];
            }else{
                $htmlInfo=$this->get_html($url,$postData,$headers,$charset,$otherConfig,true);
                $this->cache_pn_htmls[$pageSource]=$htmlInfo;
            }
        }else{
            if($cacheKey!=$this->cache_page_urls[$pageSource]){
                
                $this->cache_page_urls[$pageSource]=$cacheKey;
                $this->cache_page_htmls[$pageSource]=null;
            }
            if(isset($this->cache_page_htmls[$pageSource])){
                
                $htmlInfo=$this->cache_page_htmls[$pageSource];
            }else{
                $htmlInfo=$this->get_html($url,$postData,$headers,$charset,$otherConfig,true);
                $this->cache_page_htmls[$pageSource]=$htmlInfo;
            }
        }
        
        init_array($htmlInfo);
        $html=$htmlInfo['html'];
        
        
        if(!isset($this->page_content_matches[$pageType])){
            $this->page_content_matches[$pageType]=array();
        }
        if(!isset($this->page_content_matches[$pageType][$pageName])){
            $this->page_content_matches[$pageType][$pageName]=array();
        }
        if($html){
            
            $contentMatches=array();
            $contentSigns=$this->get_page_config($pageType,$pageName,'content_signs');
            if(!empty($contentSigns)&&is_array($contentSigns)){
                $pageSourceName=$this->page_source_name($pageType, $pageName);
                foreach ($contentSigns as $contentSign){
                    if(is_array($contentSign)&&$contentSign['identity']){
                        $module=strtolower($contentSign['module']);
                        $val='';
                        if($module=='rule'){
                            $val = $this->get_rule_module_rule_data(array(
                                'rule' => $contentSign['reg_rule'],
                                'rule_merge' => $contentSign['reg_rule_merge'],
                                'rule_multi' => $contentSign['rule_multi'],
                                'rule_multi_str' => $contentSign['rule_multi_str'],
                                'rule_multi_type' => $contentSign['rule_multi_type']
                            ), $html,array(),true);
                        }elseif($module=='xpath'){
                            $val = $this->rule_module_xpath_data($contentSign,$html);
                        }elseif($module=='json'){
                            $val=$this->rule_module_json_data($contentSign,$html);
                        }
                        
                        if(!empty($contentSign['funcs'])&&is_array($contentSign['funcs'])){
                            
                            $csMatchSign=cp_sign('match',$contentSign['identity']);
                            foreach ($contentSign['funcs'] as $csFunc){
                                if(is_array($csFunc)&&!empty($csFunc['func'])){
                                    
                                    $result=$this->execute_plugin_func('contentSign', $csFunc['func'], $val, $csFunc['func_param'], null, ' @ '.$pageSourceName.' '.$csMatchSign);
                                    if(isset($result)){
                                        $val=$result;
                                    }
                                }
                            }
                        }
                        $contentMatches['match'.$contentSign['identity']]=$val;
                    }
                }
            }
            $this->page_content_matches[$pageType][$pageName]=$contentMatches;
        }
        if($returnInfo){
            return $htmlInfo;
        }else{
            return $html;
        }
    }
    
    
    
    /**
     * 获取源码
     * @param string $url 网址
     * @param bool|array $postData post数据
     * @param array $headers 请求头信息
     * @param string $charset 网页编码
     * @param array $otherConfig 其他配置
     * @param string $returnInfo 返回数据信息
     * @return string|array
     */
    public function get_html($url,$postData=false,$headers=array(),$charset=null,$otherConfig=array(),$returnInfo=false){
        static $retryCur=0;
        $retryMax=intval(g_sc_c('caiji','retry'));
        $retryParams=null;
        if($retryMax>0){
            
            $retryParams=array(0=>$url,1=>$postData,2=>$headers,3=>$charset,4=>$otherConfig,5=>$returnInfo);
        }
        
        if(!\util\Funcs::is_right_url($url)){
            $this->echo_error('网址缺少http(s)前缀：'.htmlspecialchars($url));
            return null;
        }
        
        $pageOpened='';
        if(isset($postData)&&$postData!==false){
            
            $pageOpened.='[post] ';
        }
        
        if(empty($charset)){
            
            $charset=$this->config['charset'];
        }
        $pageRenderTool=null;
        if($this->renderer_is_open(null,null,$otherConfig['renderer'])){
            $pageRenderTool=g_sc_c('page_render','tool');
            if(empty($pageRenderTool)){
                
                $this->echo_error('页面渲染未设置，请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>','setting/page_render');
                return null;
            }
            $pageOpened.='[渲染] ';
        }
        $htmlInfo=array();
        $html=null;
        $options=array();
        
        if(empty($headers)||!is_array($headers)){
            $headers=array();
        }else{
            $hdUseragent=\util\Funcs::array_val_in_keys($headers,array('useragent','user-agent'),true);
            if($hdUseragent){
                $options['useragent']=$hdUseragent;
            }
            $hdCookie=\util\Funcs::array_val_in_keys($headers,array('cookie'),true);
            if(isset($hdCookie)){
                $headers['cookie']=$hdCookie;
            }
        }
        $mproxy=model('ProxyIp');
        $proxyDbIp=null;
        if(!is_empty(g_sc_c('proxy','open'))){
            
            $proxyDbIp=$mproxy->get_usable_ip();
            $proxyIp=$mproxy->to_proxy_ip($proxyDbIp);
            if(empty($proxyIp)){
                
                $this->echo_error('没有可用的代理IP');
                return null;
            }else{
                $options['proxy']=$proxyIp;
            }
        }
        
        if(!is_empty(g_sc_c('caiji','robots'))){
            
            if(!$this->abide_by_robots($url,$options)){
                $this->echo_error('robots拒绝访问的网址：'.htmlspecialchars($url));
                return null;
            }
        }
        
        if($pageRenderTool){
            
            if($pageRenderTool=='chrome'){
                try {
                    $options['renderer']=$otherConfig['renderer'];
                    
                    $chromeSocket=null;
                    if($otherConfig['render_pn_page_source']&&$this->render_pn_sockets[$otherConfig['render_pn_page_source']]){
                        
                        $chromeSocket=$this->render_pn_sockets[$otherConfig['render_pn_page_source']];
                        if($chromeSocket->hasTab($chromeSocket->getTabId())){
                            
                            $options['render_pn_renderer']=true;
                        }else{
                            
                            $chromeSocket->newTab($options['proxy']);
                        }
                    }else{
                        $chromeConfig=g_sc_c('page_render','chrome');
                        init_array($chromeConfig);
                        $chromeSocket=new \util\ChromeSocket($chromeConfig['host'],$chromeConfig['port'],g_sc_c('page_render','timeout'),$chromeConfig['filename'],$chromeConfig);
                        $chromeSocket->newTab($options['proxy']);
                        $chromeSocket->websocket(null);
                        if($otherConfig['render_pn_page_source']){
                            
                            $this->render_pn_sockets[$otherConfig['render_pn_page_source']]=$chromeSocket;
                        }
                    }
                    $htmlInfo=$chromeSocket->getRenderHtml($url,$headers,$options,$charset,$postData,true);
                }catch (\Exception $ex){
                    $ex='页面渲染失败：'.$ex->getMessage().' 请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>';
                    if(!is_empty(g_sc_c('proxy','open'))){
                        
                        $ex.=' <a href="'.(is_empty(g_sc('c_original','proxy','open'))?url('admin/task/set?id='.$this->collector['task_id']):url('setting/proxy')).'" target="_blank">代理设置</a>';
                    }
                    $this->echo_error($ex);
                    return null;
                }
            }else{
                $this->echo_error('渲染工具不可用，请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>','setting/page_render');
                return null;
            }
        }else{
            $options['curlopts']=$otherConfig['curlopts'];
            if(isset($otherConfig['return_head'])){
                $options['return_head']=$otherConfig['return_head'];
            }
            if(isset($otherConfig['return_info'])){
                $options['return_info']=$otherConfig['return_info'];
            }
            init_array($options['curlopts']);
            
            $options['max_redirs']=g_sc_c('caiji','max_redirs');
            $htmlInfo=get_html($url,$headers,$options,$charset,$postData,true);
        }
        init_array($htmlInfo);
        $html=$htmlInfo['html'];
        if((empty($html)&&empty($options['return_head']))||!$htmlInfo['ok']){
            
            if(!empty($proxyDbIp)){
                $this->echo_msg(array('代理IP：%s',$proxyDbIp['ip']),'black',true,'','display:inline;margin-right:5px;');
            }
            
            $this->retry_first_echo($retryCur,'访问网址失败',$url,$htmlInfo);
            
            
            if(!empty($proxyDbIp)){
                if($htmlInfo['code']!=404){
                    
                    $mproxy->set_ip_failed($proxyDbIp);
                }
            }
            
            $caijiWait=g_sc_c('caiji','wait');
            if($caijiWait){
                $this->collect_sleep($caijiWait);
            }else{
                $this->collect_stopped($this->collector['task_id'],10);
            }
            
            if($this->retry_do_func($retryCur,$retryMax,'网址无效')){
                return $this->get_html($retryParams[0],$retryParams[1],$retryParams[2],$retryParams[3],$retryParams[4],$retryParams[5]);
            }
            
            return $returnInfo?$htmlInfo:null;
        }
        $retryCur=0;
        
        if($this->config['url_complete']&&$html){
            
            $url_info=$this->match_url_info($url,$html);
            
            $html=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
                
                $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
                return $matche[1].$matche[2].$matche[3];
            },$html);
            $html=preg_replace_callback('/(\bsrc\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
                $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
                return $matche[1].$matche[2].$matche[3];
            },$html);
        }
        if($returnInfo){
            $htmlInfo['html']=$html;
            $htmlInfo['cookie']='';
            $htmlInfo['cookie_data']=\util\Funcs::get_cookies_from_header('cookie:'.$headers['cookie']."\r\n".$htmlInfo['header']);
            if($htmlInfo['cookie_data']){
                foreach ($htmlInfo['cookie_data'] as $k=>$v){
                    $htmlInfo['cookie'].=$k.'='.$v.';';
                }
            }
            return $htmlInfo;
        }else{
            return $html;
        }
    }
}
?>