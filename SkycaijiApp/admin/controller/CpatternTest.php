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

class CpatternTest extends BaseController {
    public $eCpattern=null;
    public function __construct($request = null){
        parent::__construct($request);
        $this->eCpattern= new \skycaiji\admin\event\Cpattern();
    }
    /*浏览器*/
    public function browserAction(){
        $collData=$this->_test_init();
        
        $test_url=input('test_url','','trim');
        if(empty($test_url)){
            $this->error('请输入网址');
        }
        if(!preg_match('/^\w+\:\/\//',$test_url)){
            
            $test_url='http://'.$test_url;
        }
        
        $pageSource=input('page_source','');
        
        $pageName=$this->eCpattern->split_page_source($pageSource);
        $pageType=$pageName['type'];
        $pageName=$pageName['name'];
        
        if(!empty($pageType)){
            
            $inputedUrls=input('get.','','trim');
            $input_urls=array();
            $this->_page_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
            $this->_input_urls_parent($pageType=='url'?true:false, $input_urls, $inputedUrls, $input_urls);

            $testUrl=$inputedUrls;
            $testUrl['coll_id']=$collData['id'];
            $testUrl['test']='get_elements';
            $testUrl='cpattern_test/test_url?'.http_build_query($testUrl);
            
            if(isset($input_urls['source_url'])&&empty($input_urls['source_url'])){
                $this->error('请输入起始页',$testUrl);
            }
            if(is_array($input_urls['level_url'])){
                foreach ($input_urls['level_url'] as $k=>$v){
                    if(empty($v['url'])){
                        $this->error('请输入多级页：'.$v['name'],$testUrl);
                    }
                }
            }
            if(isset($input_urls['url'])&&empty($input_urls['url'])){
                $this->error('请输入内容页',$testUrl);
            }
            $this->_get_test_content('get_elements', $collData);
        }
        
        $html=$this->eCpattern->get_page_html($test_url, $pageType, $pageName);
        
        $jsonHtml=\util\Funcs::convert_html2json($html,true);
        
        $config=$this->eCpattern->config;
        $config=is_array($config)?$config:array();
        
        
        if(empty($jsonHtml)){
            
            $html=preg_replace('/<script[^<>]*?>[\s\S]*?<\/script>/i', '', $html);
            $html=preg_replace('/<meta[^<>]*charset[^<>]*?>/i', '', $html);
            $html=preg_replace('/<meta[^<>]*http-equiv\s*=\s*[\'\"]{0,1}refresh\b[\'\"]{0,1}[^<>]*?>/i', '', $html);
            header("Content-type:text/html;charset=utf-8");
            $this->assign('html',$html);
            $this->assign('config',$config);
            
            return $this->fetch('cpattern:browser');
        }else{
            
            set_g_sc('p_title','分析网页');
            set_g_sc('p_name','分析网页');
            $this->assign('jsonHtml',$jsonHtml);
            return $this->fetch('cpattern:browser_json');
        }
    }
    /*设置多级网址测试数量*/
    public function level_numAction(){
        $num=input('num/d',0);
        
        $mcache=CacheModel::getInstance();
        $mcache->setCache('cpattern_test_level_num',$num);
        
        $this->success('设置成功，请重新测试');
    }
    /*测试*/
    private function _test_init(){
        set_time_limit(600);
        $coll_id=input('coll_id/d',0);
        $collData=model('Collector')->where(array('id'=>$coll_id))->find();
        if(empty($collData)){
            $this->error(lang('coll_error_empty_coll'));
        }
        if(!in_array($collData['module'],config('allow_coll_modules'))){
            $this->error(lang('coll_error_invalid_module'));
        }
        $this->assign('collData',$collData);
        
        
        $this->eCpattern->init($collData);
        
        $taskData=model('Task')->getById($this->eCpattern->collector['task_id']);
        model('Task')->loadConfig($taskData);
        
        set_g_sc('p_nav',breadcrumb(array(
            array(
                'url' => url('Collector/set?task_id=' . $taskData['id']),
                'title' => lang('task') . lang('separator') . $taskData['name']
            ),
            array(
                'url' =>url('cpattern_test/' . $this->request->action() . '?coll_id=' . $coll_id),
                'title' => '测试'
            )
        )));
        
        return $collData;
    }
    
    
    public function source_urlsAction(){
        $collData=$this->_test_init();
        $source_urls=array();
        if(is_array($this->eCpattern->config['source_url'])){
            foreach ($this->eCpattern->config['source_url'] as $k => $v) {
                if(empty($v)){
                    continue;
                }
                $source_urls[$v] = $this->eCpattern->convert_source_url ( $v );
            }
        }
        if(!$this->eCpattern->config['source_is_url']){
            
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
        
        $mcache=CacheModel::getInstance();
        $testNum=$mcache->getCache('cpattern_test_level_num','data');
        $testNum=intval($testNum);
        if($testNum<=0){
            $testNum=3;
        }
        $this->assign('testNum',$testNum);
        
        $this->assign('source_urls',$source_urls);
        $this->assign('config',$this->eCpattern->config);
        return $this->fetch('cpattern:test_source_urls');
    }
    
    
    public function cont_urlsAction(){
        $collData=$this->_test_init();
        
        $source_url=input('source_url','','trim');
        $curLevel=input('level/d',0);
        $curLevel=$curLevel>0?$curLevel:0;
        
        $parentUrl=input('parent_url','','trim');
        $parentLevel=input('parent_level/d',0);
        
        
        $cacheKeyPre="cache_cpattern_test_cont_urls_{$collData['id']}_";
        $curLevelUrls=array();
        $cacheParentData=array();
        
        if(!empty($parentUrl)&&!empty($parentLevel)){
            $cacheParentData=cache($cacheKeyPre.$parentLevel);
            if(!is_array($cacheParentData)){
                $cacheParentData=array();
            }
            
            $cacheParentData=$cacheParentData[md5($parentUrl)];
            if(!is_array($cacheParentData)){
                $cacheParentData=array();
            }
            
            if(is_array($cacheParentData['page_area_matches'])){
                $this->eCpattern->page_area_matches=$cacheParentData['page_area_matches'];
            }
            if(is_array($cacheParentData['page_url_matches'])){
                $this->eCpattern->page_url_matches=$cacheParentData['page_url_matches'];
            }
            
            if(is_array($cacheParentData['cur_level_urls'])){
                $curLevelUrls=$cacheParentData['cur_level_urls'];
            }
            
            
            $curLevelUrls[$this->eCpattern->get_config('level_urls',$parentLevel-1,'name')]=$source_url;
        }else{
            
            cache($cacheKeyPre.'0',null);
            if(is_array($this->eCpattern->config['level_urls'])){
                foreach ($this->eCpattern->config['level_urls'] as $k=>$v){
                    cache($cacheKeyPre.($k+1),null);
                }
            }
        }
        
        $this->eCpattern->cur_level_urls=$curLevelUrls;
        
        $levelData=$this->eCpattern->collLevelUrls($source_url,$curLevel);
        
        
        $cachePageData=cache($cacheKeyPre.$curLevel);
        if(!is_array($cachePageData)){
            $cachePageData=array();
        }
        
        $cachePageData[md5($source_url)]=array(
            'page_area_matches'=>$this->eCpattern->page_area_matches,
            'page_url_matches'=>$this->eCpattern->page_url_matches,
            'cur_level_urls'=>$this->eCpattern->cur_level_urls,
        );
        cache($cacheKeyPre.$curLevel,$cachePageData,1200);
        
        $isPost=false;
        $urlWeb=$this->eCpattern->get_config('new_level_urls',$levelData['levelName'],'url_web');
        if($this->eCpattern->url_web_is_open($urlWeb)&&$urlWeb['form_method']=='post'){
            $isPost=true;
        }
        
        $this->success('', null, array(
            'sourceUrl'=>$source_url,
            'urls' => $levelData['urls'],
            'levelName' => $levelData['levelName'],
            'level' => $curLevel,
            'isPost'=>$isPost,
            'nextLevel' => $levelData['nextLevel'],
        ));
    }
    
    
    public function test_urlAction(){
        $collData=$this->_test_init();
        
        set_g_sc('p_title','测试抓取');
        set_g_sc('p_name','测试抓取');
        $test_url=input('test_url','','trim');
        $test=input('test');
        
        $this->assign('test_url',$test_url);
        $this->assign('test',$test);
        
        $urlParams=input('param.','','trim');
        $urlParams=base64_encode(serialize($urlParams));
        
        $this->assign('pageSources',$this->eCpattern->page_source_options());
        $this->assign('urlParams',$urlParams);
        if(request()->isAjax()){
            return view('cpattern:test_test_url_ajax');
        }else{
            return $this->fetch('cpattern:test_test_url');
        }
    }
    
    
    public function input_urlAction(){
        $collData=$this->_test_init();
        $test=input('test','');
        $pageSource=input('page_source','');
        $urlParams=input('url_params','','trim');
        $inputedUrls=trim_input_array('inputed_urls');
        
        $pageName=$this->eCpattern->split_page_source($pageSource);
        $pageType=$pageName['type'];
        $pageName=$pageName['name'];
        
        if($urlParams){
            $urlParams=unserialize(base64_decode($urlParams));
        }
        if(!is_array($urlParams)){
            $urlParams=array();
        }
        if(!is_array($inputedUrls)){
            $inputedUrls=array();
        }
        foreach ($inputedUrls as $k=>$v){
            if(empty($v)){
                
                unset($inputedUrls[$k]);
            }
        }
        
        $inputedUrls=array_merge($urlParams,$inputedUrls);
        if(empty($inputedUrls['source_url'])){
            
            $inputedUrls['source_url']='';
        }
        
        $input_urls=array();
        
        if($test=='get_fields'){
            
            if(is_array($this->eCpattern->config['new_field_list'])){
                foreach ($this->eCpattern->config['new_field_list'] as $field){
                    if(empty($field['field']['source'])){
                        
                        if($field['field']['module']=='sign'){
                            
                            if(empty($this->eCpattern->config['level_urls'])){
                                $input_urls['source_url']=$inputedUrls['source_url'];
                            }else{
                                
                                $endLevelNum=count($this->eCpattern->config['level_urls']);
                                $endLevel=$this->eCpattern->config['level_urls'][$endLevelNum-1];
                                $input_urls['level_url'][$endLevelNum]=array('level'=>$endLevelNum,'name'=>$endLevel['name'],'url'=>$inputedUrls['level_'.$endLevelNum]);
                            }
                        }
                    }elseif('source_url'==strtolower($field['field']['source'])){
                        
                        $input_urls['source_url']=$inputedUrls['source_url'];
                    }elseif(preg_match('/^level_url:/i', $field['field']['source'])){
                        
                        if(is_array($this->eCpattern->config['level_urls'])){
                            foreach($this->eCpattern->config['level_urls'] as $levIx=>$levVal){
                                if($field['field']['source']==('level_url:'.$levVal['name'])){
                                    
                                    $level=$levIx+1;
                                    if($field['field']['module']=='sign'){
                                        
                                        if($level==1){
                                            
                                            $input_urls['source_url']=$inputedUrls['source_url'];
                                        }else{
                                            
                                            $prevLevel=$level-1;
                                            $input_urls['level_url'][$prevLevel]=array('level'=>$prevLevel,'name'=>$this->eCpattern->get_config('level_urls',$prevLevel-1,'name'),'url'=>$inputedUrls['level_'.$prevLevel]);
                                        }
                                    }
                                    
                                    $input_urls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>$inputedUrls['level_'.$level]);
                                    break;
                                }
                            }
                        }
                    }elseif(preg_match('/^relation_url\:(.*)$/i',$field['field']['source'],$mRelationName)){
                        
                        $mRelationName=$mRelationName[1];
                        $this->_page_input_urls(true,'relation_url',$mRelationName,$inputedUrls,$input_urls);
                    }
                }
            }
            
            $pageSigns=$this->eCpattern->parent_page_signs($pageType,$pageName,'url_web');
            $this->_page_signs_input_urls(true,true,$pageSigns,$inputedUrls,$input_urls);
        }elseif($test=='get_html'||$test=='get_elements'){
            
            if(!empty($pageType)){
                
                $this->_page_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
            }
        }elseif($test=='get_signs'){
            if(empty($pageType)){
                
                $input_urls['source_url']=$inputedUrls['source_url'];
                
                if(is_array($this->eCpattern->config['level_urls'])){
                    foreach($this->eCpattern->config['level_urls'] as $levIx=>$levVal){
                        
                        $level=$levIx+1;
                        $input_urls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>$inputedUrls['level_'.$level]);
                    }
                }
            }else{
                
                $this->_page_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
            }
        }elseif($test=='get_relation_urls'){
            
            $this->_page_input_urls(true,'url','',$inputedUrls,$input_urls);
            if(is_array($this->eCpattern->config['relation_urls'])){
                foreach ($this->eCpattern->config['relation_urls'] as $relationUrl){
                    $this->_page_input_urls(true,'relation_url',$relationUrl['name'],$inputedUrls,$input_urls);
                }
            }
        }elseif($test=='get_paging_urls'){
            
            $this->_page_input_urls(true,'url','',$inputedUrls,$input_urls);
        }
        
        $this->_input_urls_parent($pageType=='url'?true:false, $input_urls, $inputedUrls, $input_urls);
        
        if(is_array($input_urls['level_url'])){
            
            ksort($input_urls['level_url']);
        }
        
        $this->assign('input_urls',$input_urls);
        return $this->fetch('cpattern:test_input_url');
    }
    
    
    private function _page_input_urls($isContUrl,$pageType,$pageName,$inputedUrls,&$input_urls){
        $pageSigns=$this->eCpattern->parent_page_signs($pageType,$pageName);
        $this->_page_signs_input_urls($isContUrl,false,$pageSigns,$inputedUrls,$input_urls);
        $pageSigns=$this->eCpattern->parent_page_signs($pageType,$pageName,'url_web');
        $this->_page_signs_input_urls($isContUrl,true,$pageSigns,$inputedUrls,$input_urls);
    }
    
    
    private function _page_signs_input_urls($isContUrl,$isUrlWeb,$pageSigns,$inputedUrls,&$input_urls){
        $iptUrls=array();
        if(!empty($pageSigns)){
            if($isUrlWeb){
                
                if(!empty($pageSigns['cur'])&&(!empty($pageSigns['cur']['url'])||!empty($pageSigns['cur']['area']))){
                    
                    if($pageSigns['cur']['page_type']=='url'){
                        
                        if(empty($this->eCpattern->config['level_urls'])){
                            
                            $iptUrls['source_url']=$inputedUrls['source_url']?$inputedUrls['source_url']:'';
                        }else{
                            
                            $endLevelNum=count($this->eCpattern->config['level_urls']);
                            $endLevel=$this->eCpattern->config['level_urls'][$endLevelNum-1];
                            $iptUrls['level_url'][$endLevelNum]=array('level'=>$endLevelNum,'name'=>$endLevel['name'],'url'=>$inputedUrls['level_'.$endLevelNum]);
                        }
                    }elseif($pageSigns['cur']['page_type']=='level_url'){
                        
                        $prevLevelNum=-1;
                        $prevLevel=null;
                        if(is_array($this->eCpattern->config['level_urls'])){
                            foreach ($this->eCpattern->config['level_urls'] as $k=>$v){
                                $prevLevelNum=$k;
                                if($v['name']==$pageSigns['cur']['page_name']){
                                    
                                    break;
                                }
                                $prevLevel=$v;
                            }
                        }
                        if($prevLevelNum>-1){
                            if($prevLevelNum==0){
                                
                                $iptUrls['source_url']=$inputedUrls['source_url']?$inputedUrls['source_url']:'';
                            }else{
                                
                                $iptUrls['level_url'][$prevLevelNum]=array('level'=>$prevLevelNum,'name'=>$prevLevel['name'],'url'=>$inputedUrls['level_'.$prevLevelNum]);
                            }
                        }
                    }elseif($pageSigns['cur']['page_type']=='relation_url'){
                        
                        if(!$isContUrl){
                            
                            $iptUrls['url']=$inputedUrls['url']?$inputedUrls['url']:'';
                        }
                    }
                }
            }
            
            if(!empty($pageSigns['level_url'])&&is_array($pageSigns['level_url'])){
                
                $signLevels=array_keys($pageSigns['level_url']);
                if(is_array($this->eCpattern->config['level_urls'])){
                    foreach ($this->eCpattern->config['level_urls'] as $levIx=>$levVal){
                        
                        
                        $level=$levIx+1;
                        if(in_array($levVal['name'],$signLevels)){
                            
                            if($level==1){
                                
                                $iptUrls['source_url']=$inputedUrls['source_url']?$inputedUrls['source_url']:'';
                            }else{
                                
                                $prevLevel=$level-1;
                                $iptUrls['level_url'][$prevLevel]=array('level'=>$prevLevel,'name'=>$this->eCpattern->get_config('level_urls',$prevLevel-1,'name'),'url'=>$inputedUrls['level_'.$prevLevel]);
                            }
                            
                            $iptUrls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>$inputedUrls['level_'.$level]);
                        }
                    }
                }
            }
            if(!$isContUrl){
                
                if(!empty($pageSigns['url'])||(!empty($pageSigns['relation_url'])&&is_array($pageSigns['relation_url']))){
                    
                    $iptUrls['url']=$inputedUrls['url']?$inputedUrls['url']:'';
                }
            }
        }
        
        if(isset($iptUrls['source_url'])){
            $input_urls['source_url']=$iptUrls['source_url'];
        }
        if(is_array($iptUrls['level_url'])){
            foreach ($iptUrls['level_url'] as $k=>$v){
                $input_urls['level_url'][$k]=$v;
            }
        }
        if(isset($iptUrls['url'])){
            $input_urls['url']=$iptUrls['url'];
        }
        
        return $iptUrls;
    }
    
    
    private function _input_urls_parent($isContUrl,$curInputUrls,$inputedUrls,&$input_urls){
        $levelNames=array();
        if(is_array($curInputUrls)&&is_array($curInputUrls['level_url'])){
            foreach ($curInputUrls['level_url'] as $v){
                $levelNames[$v['name']]=$v['name'];
            }
        }
        if($levelNames){
            foreach ($levelNames as $levelName){
                $pageSigns=$this->eCpattern->parent_page_signs('level_url',$levelName);
                $iptUrls=$this->_page_signs_input_urls($isContUrl,false,$pageSigns,$inputedUrls,$input_urls);
                if(is_array($iptUrls['level_url'])){
                    
                    foreach ($iptUrls['level_url'] as $k=>$v){
                        if(isset($input_urls['level_url'][$k])){
                            unset($iptUrls['level_url'][$k]);
                        }
                    }
                    if(!empty($iptUrls['level_url'])){
                        $this->_input_urls_parent($isContUrl,$iptUrls, $inputedUrls, $input_urls);
                    }
                }
                $pageSigns=$this->eCpattern->parent_page_signs('level_url',$levelName,'url_web');
                $iptUrls=$this->_page_signs_input_urls($isContUrl,true,$pageSigns,$inputedUrls,$input_urls);
                if(is_array($iptUrls['level_url'])){
                    
                    foreach ($iptUrls['level_url'] as $k=>$v){
                        if(isset($input_urls['level_url'][$k])){
                            unset($iptUrls['level_url'][$k]);
                        }
                    }
                    if(!empty($iptUrls['level_url'])){
                        $this->_input_urls_parent($isContUrl,$iptUrls, $inputedUrls, $input_urls);
                    }
                }
            }
        }
    }
    public function get_fieldsAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_fields',$collData);
    }
    public function get_signsAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_signs',$collData);
    }
    public function get_htmlAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_html',$collData);
    }
    public function get_paging_urlsAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_paging_urls',$collData);
    }
    public function get_relation_urlsAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_relation_urls',$collData);
    }
    public function get_elementsAction(){
        $collData=$this->_test_init();
        $params=input('get.','','trim');
        $this->success('','cpattern_test/browser?'.http_build_query($params));
    }
    
    private function _get_test_content($testName,$collData){
        
        $test_url=input('test_url','','trim');
        if(empty($test_url)){
            $this->error('请输入网址');
        }
        if(!preg_match('/^\w+\:\/\//',$test_url)){
            
            $test_url='http://'.$test_url;
        }
        
        $pageSource=input('page_source','url');
        
        $pageType='';
        $pageName='';
        
        if($testName=='get_html'||$testName=='get_elements'||$testName=='get_signs'){
            
            $pageName=$this->eCpattern->split_page_source($pageSource);
            $pageType=$pageName['type'];
            $pageName=$pageName['name'];
        }else{
            
            $pageType='url';
            $pageName='';
        }
        
        if($pageType=='url'){
            
            $this->eCpattern->cur_cont_url=$test_url;
        }

        if(input('?source_url')){
            
            $this->eCpattern->cur_source_url=input('source_url','','trim');
            if(empty($this->eCpattern->cur_source_url)){
                $this->error('请输入起始页');
            }
        }
        $inputLevels=array();
        foreach (input('param.') as $k=>$v){
            
            if(preg_match('/^level_(\d+)$/',$k,$mLevel)){
                
                $mLevel=intval($mLevel[1])-1;
                $inputLevels[$mLevel]=input($k,'','trim');
            }
        }
        ksort($inputLevels);
        foreach ($inputLevels as $k=>$v){
            $levelName=$this->eCpattern->get_config('level_urls',$k,'name');
            $this->eCpattern->cur_level_urls[$levelName]=$v;
            if(empty($v)){
                $this->error('请输入多级页：'.$levelName);
            }
        }
        if(input('?url')){
            
            $this->eCpattern->cur_cont_url=input('url','','trim');
            if(empty($this->eCpattern->cur_cont_url)){
                $this->error('请输入内容页');
            }
        }
        
        
        if(!empty($this->eCpattern->cur_source_url)){
            
            if(empty($this->eCpattern->config['level_urls'])){
                
                $this->eCpattern->getContUrls($this->eCpattern->cur_source_url);
            }else{
                
                $this->eCpattern->getLevelUrls($this->eCpattern->cur_source_url,1);
            }
        }
        if(!empty($this->eCpattern->cur_level_urls)){
            
            
            foreach ($this->eCpattern->config['level_urls'] as $k=>$v){
                if(isset($this->eCpattern->cur_level_urls[$v['name']])){
                    if($k==0){
                        
                        if($this->eCpattern->cur_source_url){
                            $this->eCpattern->getLevelUrls($this->eCpattern->cur_source_url,$k+1);
                        }
                    }else{
                        
                        $prevLevelUrl=$this->eCpattern->config['level_urls'][$k-1];
                        if($this->eCpattern->cur_level_urls[$prevLevelUrl['name']]){
                            $this->eCpattern->getLevelUrls($this->eCpattern->cur_level_urls[$prevLevelUrl['name']],$k+1);
                        }
                    }
                }
            }
            
            
            $endLevelNum=count($this->eCpattern->config['level_urls']);
            $endLevel=$this->eCpattern->config['level_urls'][$endLevelNum-1];
            if(isset($this->eCpattern->cur_level_urls[$endLevel['name']])){
                $this->eCpattern->getContUrls($this->eCpattern->cur_level_urls[$endLevel['name']]);
            }
        }
        
        if('get_fields'==$testName){
            $val_list=$this->eCpattern->getFields($test_url);
            if(empty($this->eCpattern->first_loop_field)){
                
                $val_list=array($val_list);
            }
            
            $md5Url=md5($test_url);
            $msg='';
            if(isset($this->eCpattern->exclude_cont_urls[$md5Url])){
                if(empty($this->eCpattern->first_loop_field)){
                    
                    $msg=reset($this->eCpattern->exclude_cont_urls[$md5Url]);
                    $msg=$this->eCpattern->exclude_url_msg($msg);
                    $this->error('中断采集 &gt; '.$msg);
                }else{
                    
                    $num=0;
                    foreach ($this->eCpattern->exclude_cont_urls[$md5Url] as $k=>$v){
                        $num+=count((array)$v);
                    }
                    $msg='通过数据处理筛除了'.$num.'条数据';
                }
            }
            
            foreach ($val_list as $v_k=>$vals){
                foreach ($vals as $k=>$v){
                    $vals[$k]=$v['value'];
                }
                $val_list[$v_k]=$vals;
            }
            
            $returnData=array('val_list'=>$val_list);
            
            if(count($val_list)>1){
                
                $mcache=CacheModel::getInstance();
                $returnData['loop_table']=$mcache->getCache('cp_test_loop_tb_'.$collData['id'],'data');
                $returnData['loop_table']=empty($returnData['loop_table'])?null:$returnData['loop_table'];
            }
            
            $this->success($msg,null,$returnData);
        }elseif('get_signs'==$testName){
            $matchList=array();
            if(empty($pageType)){
                
                $this->eCpattern->cur_cont_url=$test_url;
                if(!empty($this->eCpattern->config['new_relation_urls'])){
                    
                    foreach ($this->eCpattern->config['new_relation_urls'] as $name=>$v){
                        $this->eCpattern->getRelationUrl($name, $test_url, '');
                    }
                }
                
                if(!empty($this->eCpattern->config['new_level_urls'])){
                    
                    foreach($this->eCpattern->config['new_level_urls'] as $name=>$v){
                        $matches=array(
                            'name'=>'多级页：'.$name,
                            'page_source'=>'level_url:'.$name,
                            'area'=>$this->eCpattern->get_page_area_match('level_url',$name),
                            'url'=>$this->eCpattern->get_page_url_match('level_url',$name,md5($this->eCpattern->cur_level_urls[$name]))
                        );
                        $matchList[]=$matches;
                    }
                }
                
                $matchList[]=array(
                    'name'=>'内容页',
                    'page_source'=>'url',
                    'area'=>$this->eCpattern->get_page_area_match('url',''),
                    'url'=>$this->eCpattern->get_page_url_match('url','',md5($this->eCpattern->cur_cont_url))
                );
                
                if(!empty($this->eCpattern->config['new_relation_urls'])){
                    
                    foreach($this->eCpattern->config['new_relation_urls'] as $name=>$v){
                        $matches=array(
                            'name'=>'关联页：'.$name,
                            'page_source'=>'relation_url:'.$name,
                            'area'=>$this->eCpattern->get_page_area_match('relation_url',$name),
                            'url'=>$this->eCpattern->get_page_url_match('relation_url',$name)
                        );
                        $matchList[]=$matches;
                    }
                }
                $this->success('以下是所有页面的内容标签',null,$matchList);
            }else{
                
                $signs=$this->eCpattern->parent_page_signs($pageType,$pageName);
                
                if(is_array($signs['cur'])){
                    $matchList[]=$this->_get_matches_from_signs($signs['cur']['page_type'], $signs['cur']['page_name'], $signs['cur']);
                }
                
                if(is_array($signs['level_url'])){
                    foreach ($signs['level_url'] as $levelName=>$levelSings){
                        $matchList[]=$this->_get_matches_from_signs('level_url', $levelName, $levelSings);
                    }
                }
                
                if(is_array($signs['url'])){
                    $matchList[]=$this->_get_matches_from_signs('url', '', $signs['url']);
                }
                
                if(is_array($signs['relation_url'])){
                    foreach ($signs['relation_url'] as $relationName=>$relationSings){
                        $matchList[]=$this->_get_matches_from_signs('relation_url', $relationName, $relationSings);
                    }
                }
                $this->success('以下是当前页规则中调用的其他页面'.cp_sign('match').'标签',null,$matchList);
            }
        }elseif('get_paging_urls'==$testName){
            
            $paging_urls=$this->eCpattern->getPagingUrls($test_url,'',true);
            if(empty($paging_urls)){
                $this->error('没有抓取到分页链接');
            }else{
                $this->success('',null,$paging_urls);
            }
        }elseif('get_relation_urls'==$testName){
            
            $url_list=array();
            if(is_array($this->eCpattern->config['new_relation_urls'])){
                foreach ($this->eCpattern->config['new_relation_urls'] as $k=>$v){
                    $url_list[$v['name']]=$this->eCpattern->getRelationUrl($v['name'], $test_url, '');
                }
            }
            if(empty($url_list)){
                $this->error('没有关联页');
            }else{
                $this->success('',null,$url_list);
            }
        }elseif('get_html'==$testName){
            $html=$this->eCpattern->get_page_html($test_url, $pageType, $pageName);
            
            if(empty($html)){
                exit('没有抓取到源码');
            }else{
                
                exit($html);
            }
        }elseif('get_elements'==$testName){
            
        }
    }
    
    
    private function _get_matches_from_signs($pageType,$pageName,$signs){
        $matches=array('name'=>'','page_source'=>'','area'=>array(),'url'=>array());
        $urlMd5='';
        if($pageType=='url'){
            $matches['name']='内容页';
            $matches['page_source']='url';
            $urlMd5=md5($this->eCpattern->cur_cont_url);
        }elseif($pageType=='level_url'){
            $matches['name']='多级页：'.$pageName;
            $matches['page_source']='level_url:'.$pageName;
            $urlMd5=md5($this->eCpattern->cur_level_urls[$pageName]);
        }elseif($pageType=='relation_url'){
            $matches['name']='关联页：'.$pageName;
            $matches['page_source']='relation_url:'.$pageName;
        }
        
        if(is_array($signs)){
            if(is_array($signs['area'])){
                foreach ($signs['area'] as $sign){
                    $match='match'.$sign['id'];
                    $matches['area'][$match]=$this->eCpattern->get_page_area_match($pageType,$pageName,$match);
                }
            }
            if(is_array($signs['url'])){
                foreach ($signs['url'] as $sign){
                    $match='match'.$sign['id'];
                    $matches['url'][$match]=$this->eCpattern->get_page_url_match($pageType,$pageName,$urlMd5?$urlMd5:null,$match);
                }
            }
        }
        return $matches;
    }
    
    
    public function matchAction(){
        $collData=$this->_test_init();
        set_g_sc('p_title','模拟匹配');
        set_g_sc('p_name','模拟匹配');
        if(request()->isPost()){
            $inputType=input('input_type','content');
            $type=strtolower(input('type'));
            $field=input('field/a',array(),'trim');
            if($inputType=='url'){
                
                $url=input('url','','trim');
                $charset=input('charset','');
                $charsetCustom=input('charset_custom','');
                $formMethod=input('form_method','');
                $formNames=trim_input_array('form_names');
                $formVals=trim_input_array('form_vals');
                $headerGlobal=input('header_global','');
                $headerNames=trim_input_array('header_names');
                $headerVals=trim_input_array('header_vals');
                
                if(empty($url)){
                    $this->error('请输入网址');
                }
                
                $charset=$charset=='custom'?$charsetCustom:$charset;
                
                $headers=array();
                if(empty($headerGlobal)){
                    
                    $headers=$this->eCpattern->config_params['headers']['page'];
                }elseif($headerGlobal=='y'){
                    
                    $headers=$this->eCpattern->config_params['headers']['page_headers'];
                }
                $headers=array_merge(is_array($headers)?$headers:array(),$this->eCpattern->arrays_to_key_val($headerNames, $headerVals));
                
                
                $formData=$this->eCpattern->arrays_to_key_val($formNames, $formVals);
                
                $postData=false;
                if($formMethod=='post'){
                    
                    $postData=$formData;
                }else{
                    
                    $postData=false;
                    
                    if(!empty($charset)&&strtolower($charset)!='utf-8'){
                        foreach ($formData as $k=>$v){
                            $formData[$k]=iconv('utf-8',$charset.'//IGNORE',$v);
                        }
                    }
                    
                    foreach ($formData as $k=>$v){
                        $formData[$k]=$k.'='.rawurlencode($v);
                    }
                    $url.=strpos($url, '?')===false?'?':'&';
                    $url.=implode('&', $formData);
                }
                
                $content=$this->eCpattern->get_html($url,$postData,$headers,$charset);
            }else{
                
                $content=input('content','','trim');
                if(empty($content)){
                    $this->error('请输入内容');
                }
            }
            
            $val='';
           
            if($type=='rule'){
                
                $rule=$this->eCpattern->convert_sign_match($field['rule']);
                $rule=$this->eCpattern->correct_reg_pattern($rule);
                
                $rule_merge=$this->eCpattern->set_merge_default($rule, $field['rule_merge']);
                if(empty($rule_merge)){
                    
                    $rule_merge=cp_sign('match');
                }
                
                $val=$this->eCpattern->get_rule_module_rule_data(array(
                    'rule' => $rule,
                    'rule_merge' => $rule_merge,
                    'rule_multi' => $field['rule_multi'],
                    'rule_multi_str' => $field['rule_multi_str']
                ), $content,array(),true);
                
            }elseif($type=='xpath'){
                $val = $this->eCpattern->rule_module_xpath_data(array(
                    'xpath' => $field['xpath'],
                    'xpath_attr' => $field['xpath_attr'],
                    'xpath_multi' => $field['xpath_multi'],
                    'xpath_multi_str' => $field['xpath_multi_str'],
                ), $content);
            }elseif($type=='json'){
                $val = $this->eCpattern->rule_module_json_data(array(
                    'json' => $field['json'],
                    'json_arr' =>  $field['json_arr'],
                    'json_arr_implode' =>  $field['json_arr_implode'],
                ), $content);
                $val = trim($val);
            }
            $this->success($val);
        }else{
            if(request()->isAjax()){
                return view('cpattern:test_match_ajax');
            }else{
                return $this->fetch('cpattern:test_match');
            }
        }
    }
    
    public function loop_tableAction(){
        $collId=input('coll_id/d',0);
        $op=input('op');
        
        $cname='cp_test_loop_tb_'.$collId;
        
        $mcache=CacheModel::getInstance();
        $data=$mcache->getCache($cname,'data');
        if(empty($data)&&!is_array($data)){
            $data=array();
        }
        
        $field=input('field','');
        $width=input('width/d',0);
        $data[$field]=array('width'=>$width);
        
        $mcache->setCache($cname,$data);
        $this->success();
    }
}