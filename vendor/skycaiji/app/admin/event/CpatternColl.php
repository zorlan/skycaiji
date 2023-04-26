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
    public $page_content_matches=array();
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
                $limit=100;
                $batch=ceil($total/$limit);
                for($i=1;$i<=$batch;$i++){
                    
                    $list=array_slice($usedContUrls,($i-1)*$limit,$limit);
                    if(!empty($list)){
                        CacheModel::getInstance('cont_url')->deleteCache($list);
                    }
                }
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
        if($isPagination){
            
            $config=$this->get_page_config($pageType,$pageName,'pagination');
            $parentMatches=array();
        }else{
            $config=$this->get_page_config($pageType,$pageName);
            $parentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$pageName,'area'));
        }
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
        if(!empty($config['reg_url'])&&!empty($config['reg_url_merge'])){
            
            $parentMatches=$isPagination?array():$this->parent_page_signs2matches($this->parent_page_signs($pageType,$pageName,'url'));
            
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
        
        if(!is_array($cont_urls)){
            $cont_urls=array();
        }
        if(!is_array($cont_urls_matches)){
            $cont_urls_matches=array();
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
                    
                    $contUrl=\util\Tools::create_complete_url($contUrl, $completeUrlInfo);
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
        
        return $this->page_convert_url_signs($pageType, $pageName, $isPagination, $cont_urls, $cont_urls_matches, $returnMatch);
    }
    
    /*正则规则匹配数据*/
    public function get_rule_module_rule_data($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        if(!is_array($configParams)){
            $configParams=array();
        }
        $configParams['rule_flags']=$this->config['reg_regexp_flags'];
        
        return $this->rule_module_rule_data($configParams,$html,$parentMatches,$whole,$returnMatch);
    }
    
    /*页面转换网址标签参数*/
    public function page_convert_url_signs($pageType,$pageName,$isPagination,$cont_urls,$cont_urls_matches,$returnMatch=false){
        $urlPostKeys=array();
        $urlRenderKeys=array();
        
        $pnConfig=$isPagination?$this->get_page_config($pageType,$pageName,'pagination'):null;
        $urlWebConfig=$this->get_page_config($pageType,$pageName,'url_web');
        if($this->page_url_web_opened($urlWebConfig,$pnConfig)){
            
            
            $formData=$this->arrays_to_key_val($urlWebConfig['form_names'], $urlWebConfig['form_vals']);
            if(!empty($formData)&&is_array($formData)){
                $urlsForms=array();
                $formParentMatches=$this->merge_str_signs(implode(' ',$formData));
                if(!empty($formParentMatches)){
                    
                    $formParentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$pageName,'form'));
                }
                if(!is_array($formParentMatches)){
                    $formParentMatches=array();
                }
                
                foreach ($cont_urls as $k=>$v){
                    
                    $urlFormData=array();
                    $urlParentMatches=array_merge($formParentMatches,is_array($cont_urls_matches[$k])?$cont_urls_matches[$k]:array());
                    foreach ($formData as $fk=>$fv){
                        $urlFormData[$fk]=$this->merge_match_signs($urlParentMatches,$fv);
                    }
                    $urlsForms[$k]=$urlFormData;
                }
                
                if(!empty($urlsForms)){
                    if($urlWebConfig['form_method']=='post'){
                        
                        foreach ($cont_urls as $k=>$v){
                            
                            $urlPostKeys[$k]=md5(serialize($urlsForms[$k]));
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
        }
        
        $renderConfig=$this->get_page_config($pageType,$pageName,'renderer');
        if($this->renderer_is_open(null,null,$renderConfig,$pnConfig)){
            
            if(!empty($renderConfig['types'])){
                
                $renderParentMatches=$this->merge_str_signs(implode(' ',$renderConfig['contents']));
                if(!empty($renderParentMatches)){
                    
                    $renderParentMatches=$this->parent_page_signs2matches($this->parent_page_signs($pageType,$pageName,'renderer'));
                }
                init_array($renderParentMatches);
                foreach ($cont_urls as $k=>$v){
                    
                    $renderContParentMatches=array_merge($renderParentMatches,is_array($cont_urls_matches[$k])?$cont_urls_matches[$k]:array());
                    $renderContent=array();
                    foreach ($renderConfig['contents'] as $rck=>$rcv){
                        
                        $renderContent[$rck]=$rcv?$this->merge_match_signs($renderContParentMatches,$rcv):$rcv;
                    }
                    
                    $urlRenderKeys[$k]=md5(serialize(array('types'=>$renderConfig['types'],'elements'=>$renderConfig['elements'],'contents'=>$renderContent)));
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
                    
                    $cont_urls[$k]=$v.'#'.$vUrl.$vUrlKey;
                }
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
            $signTypes=array('area','url','content');
            if(!empty($parentPageSigns['cur'])&&is_array($parentPageSigns['cur'])){
                
                $curPage=$parentPageSigns['cur'];
                foreach($signTypes as $signType){
                    $this->_page_signs2matches($signType, $curPage[$signType], $curPage['page_type'], $curPage['page_name'], $matches);
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
            
            $unknownPageSigns=$this->_page_signs_search($pageType,$pageName,$mergeType,$foundPageSigns);
            if($pageType=='relation_url'){
                
                
                if(!empty($unknownPageSigns)){
                    $relationParentPages=$this->relation_parent_pages($pageName, $this->config['new_relation_urls']);
                    foreach ($relationParentPages as $relationParentPage){
                        if(empty($unknownPageSigns)){
                            
                            break;
                        }
                        
                        $unknownPageSigns=$this->_parent_page_signs_rule('url',implode('',$unknownPageSigns),'relation_url',$relationParentPage,$foundPageSigns);
                        
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_rule('area',implode('',$unknownPageSigns),'relation_url',$relationParentPage,$foundPageSigns);
                        }
                        
                        if(!empty($unknownPageSigns)){
                            $unknownPageSigns=$this->_parent_page_signs_content('relation_url',$relationParentPage,$unknownPageSigns,$foundPageSigns);
                        }
                    }
                }
                if(!empty($unknownPageSigns)){
                    
                    
                    $unknownPageSigns=$this->_parent_page_signs_rule('url',implode('',$unknownPageSigns),'url','',$foundPageSigns);
                    
                    if(!empty($unknownPageSigns)){
                        $unknownPageSigns=$this->_parent_page_signs_rule('area',implode('',$unknownPageSigns),'url','',$foundPageSigns);
                    }
                    
                    if(!empty($unknownPageSigns)){
                        $unknownPageSigns=$this->_parent_page_signs_content('url','',$unknownPageSigns,$foundPageSigns);
                    }
                }
            }
            if($pageType!='front_url'&&$pageType!='source_url'){
                
                if(!empty($this->config['new_level_urls'])){
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
                            
                            $unknownPageSigns=$this->_parent_page_signs_rule('url',implode('',$unknownPageSigns),'level_url',$levelName,$foundPageSigns);
                            
                            if(!empty($unknownPageSigns)){
                                $unknownPageSigns=$this->_parent_page_signs_rule('area',implode('',$unknownPageSigns),'level_url',$levelName,$foundPageSigns);
                            }
                            
                            if(!empty($unknownPageSigns)){
                                $unknownPageSigns=$this->_parent_page_signs_content('level_url',$levelName,$unknownPageSigns,$foundPageSigns);
                            }
                        }
                    }
                }
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
        $pageConfig=$this->get_page_config($pageType,$pageName);
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
            
            $ruleWhole=$this->page_rule_is_null($pageType)?false:true;
            if(empty($mergeType)||$mergeType=='content_sign'||$mergeType=='renderer'||in_array($mergeType,$inUrlRule)){
                
                $pageRendererMerge='';
                if(empty($mergeType)||$mergeType=='renderer'){
                    
                    if($this->renderer_is_open(null,null,$pageConfig['renderer'])){
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
                
                $openUrlWeb=$this->page_url_web_opened($pageConfig['url_web']);
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
                    if(!$this->page_rule_is_null($pageType)){
                        
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
                if(!$this->page_rule_is_null($pageType)){
                    
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
    
    
    public function renderer_is_open($pageType,$pageName='',$rendererConfig=null,$paginationConfig=null){
        $opened=$this->get_config('page_render');
        if($pageType){
            
            $rendererConfig=$this->get_page_config($pageType,$pageName,'renderer');
            if($paginationConfig){
                
                $paginationConfig=$this->get_page_config($pageType,$pageName,'pagination');
            }
        }
        
        if($paginationConfig&&is_array($paginationConfig)&&$paginationConfig['use_renderer']){
            
            $opened=$paginationConfig['use_renderer']=='y'?true:false;
        }else{
            if($rendererConfig&&is_array($rendererConfig)&&!empty($rendererConfig['open'])){
                $opened=$rendererConfig['open']=='y'?true:false;
            }
        }
        return $opened;
    }
    
    /*页面是否是post模式*/
    public function page_is_post($pageType,$pageName='',$isPagination=false){
        $urlWebConfig=$this->get_page_config($pageType,$pageName,'url_web');
        $pnConfig=$isPagination?$this->get_page_config($pageType,$pageName,'pagination'):null;
        if($this->page_url_web_opened($urlWebConfig,$pnConfig)&&$urlWebConfig['form_method']=='post'){
            return true;
        }else{
            return false;
        }
    }
    
    
    public function page_rule_is_null($pageType){
        if($pageType=='front_url'||$pageType=='source_url'||($pageType=='url'&&$this->source_is_url())){
            
            return true;
        }else{
            return false;
        }
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
    
    
    
    /*获取页面代码*/
    public function get_page_html($url,$pageType,$pageName,$isPagination=false,$returnInfo=false){
        $pageName=$pageName?$pageName:'';
        $headers=array();
        $pageSource=$this->page_source_merge($pageType, $pageName);
        
        $urlWebConfig=$this->get_page_config($pageType,$pageName,'url_web');
        $pnConfig=$isPagination?$this->get_page_config($pageType,$pageName,'pagination'):null;
        
        $openUrlWeb=$this->page_url_web_opened($urlWebConfig,$pnConfig);
        
        if(!empty($pageSource)){
            
            $useCookie=\util\Param::get_gsc_use_cookie(false,true);
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
                if(!is_array($globalHeaders)){
                    $globalHeaders=array();
                }
                
                if(!empty($useCookie)){
                    unset($globalHeaders['cookie']);
                    $globalHeaders['cookie']=$useCookie;
                }
                
                if(!empty($globalHeaders)){
                    $headers=\util\Funcs::array_key_merge($globalHeaders,$headers);
                }
            }else{
                
                $headers=$this->config_params['headers']['page'];
                if(!is_array($headers)){
                    $headers=array();
                }
                
                if(!empty($useCookie)){
                    unset($headers['cookie']);
                    $headers['cookie']=$useCookie;
                }
            }
            if(!is_array($headers)){
                $headers=array();
            }
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
        
        
        $rendererConfig=$this->get_page_config($pageType,$pageName,'renderer');
        if($this->renderer_is_open(null,null,$rendererConfig,$pnConfig)){
            $filterUrl=true;
            $signs=$this->merge_str_signs(implode(' ',$rendererConfig['contents']));
            if(!empty($signs)){
                
                $signs=$this->parent_page_signs($pageType, $pageName, 'renderer');
                $signs=$this->parent_page_signs2matches($signs);
                
                foreach ($rendererConfig['contents'] as $k=>$v){
                    $rendererConfig['contents'][$k]=$this->merge_match_signs($signs, $v);
                }
            }
            $otherConfig['renderer']=$rendererConfig;
        }
        
        if($filterUrl){
            $url=preg_replace('/\#(post_|render_|post_render_){1,}\w{32}$/i', '', $url);
        }
        
        $htmlInfo=array();
        $html=null;
        if($isPagination){
            
            $htmlInfo=$this->get_html($url,$postData,$headers,$charset,$otherConfig,true);
            $html=$htmlInfo['html'];
        }else{
            
            
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
                $this->cache_page_htmls=array(
                    'front_url'=>is_array($this->cache_page_htmls['front_url'])?$this->cache_page_htmls['front_url']:array()
                );
            }
            
            if($pageType=='level_url'&&$this->cur_level_urls[$pageName]!=$this->cache_page_urls['level_urls'][$pageName]){
                
                $this->cache_page_urls['level_urls']=is_array($this->cur_level_urls)?$this->cur_level_urls:array();
                $this->cache_page_htmls['level_url'][$pageName]=array();
                $this->cache_page_htmls['url']=array();
                $this->cache_page_htmls['relation_url']=array();
            }
            
            $cacheKey=md5($url.' '.serialize($postData));
            if(isset($this->cache_page_htmls[$pageType][$pageName][$cacheKey])){
                
                $htmlInfo=$this->cache_page_htmls[$pageType][$pageName][$cacheKey];
            }else{
                $htmlInfo=$this->get_html($url,$postData,$headers,$charset,$otherConfig,true);
                $this->cache_page_htmls[$pageType][$pageName][$cacheKey]=$htmlInfo;
            }
            
            if(!is_array($htmlInfo)){
                $htmlInfo=array();
            }
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
                            
                            if(!empty($contentSign['func'])){
                                
                                $csMatchSign=cp_sign('match',$contentSign['identity']);
                                $result=$this->execute_plugin_func('contentSign', $contentSign['func'], $val, $contentSign['func_param'], null, ' @ '.$pageSourceName.' '.$csMatchSign);
                                if(isset($result)){
                                    $val=$result;
                                }
                            }
                            $contentMatches['match'.$contentSign['identity']]=$val;
                        }
                    }
                }
                $this->page_content_matches[$pageType][$pageName]=$contentMatches;
            }
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
                $chromeConfig=g_sc_c('page_render','chrome');
                init_array($chromeConfig);
                try {
                    $options['renderer']=$otherConfig['renderer'];
                    $chromeSocket=new \util\ChromeSocket($chromeConfig['host'],$chromeConfig['port'],g_sc_c('page_render','timeout'),$chromeConfig['filename'],$chromeConfig);
                    $chromeSocket->newTab($options['proxy']);
                    $chromeSocket->websocket(null);
                    $htmlInfo=$chromeSocket->getRenderHtml($url,$headers,$options,$charset,$postData,true);
                }catch (\Exception $ex){
                    $ex='页面渲染失败：'.$ex->getMessage().' 请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>';
                    if(!is_empty(g_sc_c('proxy','open'))){
                        
                        $ex.=' <a href="'.url('setting/proxy').'" target="_blank">代理设置</a>';
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
            
            init_array($options['curlopts']);
            $confMaxRedirs=g_sc_c('caiji','max_redirs');
            $confMaxRedirs=intval($confMaxRedirs);
            if($confMaxRedirs>0){
                
                $options['curlopts'][CURLOPT_MAXREDIRS]=$confMaxRedirs;
            }
            $htmlInfo=get_html($url,$headers,$options,$charset,$postData,true);
        }
        init_array($htmlInfo);
        $html=$htmlInfo['html'];
        if(empty($html)||!$htmlInfo['ok']){
            
            if(!empty($proxyDbIp)){
                $this->echo_msg(array('代理IP：%s',$proxyDbIp['ip']),'black',true,'','display:inline;margin-right:5px;');
            }
            
            $this->retry_first_echo($retryCur,'访问网址失败',$url,$htmlInfo);
            
            
            if(!empty($proxyDbIp)){
                if($htmlInfo['code']!=404){
                    
                    $mproxy->set_ip_failed($proxyDbIp);
                }
            }
            
            $this->collect_sleep(g_sc_c('caiji','wait'));
            
            if($this->retry_do_func($retryCur,$retryMax,'网址无效')){
                return $this->get_html($retryParams[0],$retryParams[1],$retryParams[2],$retryParams[3],$retryParams[4],$retryParams[5]);
            }
            
            return $returnInfo?$htmlInfo:null;
        }
        $retryCur=0;
        
        if($this->config['url_complete']){
            
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
            return $htmlInfo;
        }else{
            return $html;
        }
    }
}
?>