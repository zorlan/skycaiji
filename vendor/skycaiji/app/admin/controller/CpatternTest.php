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
        $this->eCpattern= new \skycaiji\admin\event\CpatternSingle();
    }
    /*浏览器*/
    public function browserAction(){
        return $this->_get_browser(true);
    }
    /*设置多级网址测试数量*/
    public function level_numAction(){
        $num=input('num/d',0);
        
        $mcache=CacheModel::getInstance();
        $mcache->setCache('cpattern_test_level_num',$num);
        
        $this->success('设置成功，请重新测试');
    }
    /*测试*/
    private function _test_init($clearCache=true,$skipCollect=false){
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
        
        $this->set_html_tags(null,null,breadcrumb(array(
            array(
                'url' => url('collector/set?task_id=' . $taskData['id']),
                'title' => lang('task') . lang('separator') . $taskData['name']
            ),
            array(
                'url' =>url('cpattern_test/' . $this->request->action() . '?coll_id=' . $coll_id),
                'title' => '测试'
            )
        )));
        
        $cacheFrontKey="cache_cpattern_test_coll_front_urls_{$collData['id']}";
        if($skipCollect){
            
            if($clearCache){
                
                cache($cacheFrontKey,'');
            }
        }else{
            
            
            if(!empty($this->eCpattern->config['front_urls'])){
                
                $keyUseCookie=\util\Param::key_gsc_use_cookie();
                $keyUseCookieImg=\util\Param::key_gsc_use_cookie('img');
                $keyUseCookieFile=\util\Param::key_gsc_use_cookie('file');
                
                $cacheFrontData=cache($cacheFrontKey);
                if($clearCache||empty($cacheFrontData)||($cacheFrontData['update_time']!=$collData['uptime'])){
                    
                    $this->eCpattern->collFrontUrls();
                    $cacheFrontData=array(
                        'page_area_matches'=>$this->eCpattern->page_area_matches,
                        'page_url_matches'=>$this->eCpattern->page_url_matches,
                        'page_content_matches'=>$this->eCpattern->page_content_matches,
                        'pn_area_matches'=>$this->eCpattern->pn_area_matches,
                        'pn_url_matches'=>$this->eCpattern->pn_url_matches,
                        'cur_front_urls'=>$this->eCpattern->cur_front_urls,
                        $keyUseCookie=>\util\Param::get_gsc_use_cookie(),
                        $keyUseCookieImg=>\util\Param::get_gsc_use_cookie('img'),
                        $keyUseCookieFile=>\util\Param::get_gsc_use_cookie('file'),
                        'update_time'=>$collData['uptime']
                    );
                    cache($cacheFrontKey,$cacheFrontData,1200);
                }
                if(!empty($cacheFrontData)){
                    
                    if(is_array($cacheFrontData['page_area_matches'])){
                        $this->eCpattern->page_area_matches=$cacheFrontData['page_area_matches'];
                    }
                    if(is_array($cacheFrontData['page_url_matches'])){
                        $this->eCpattern->page_url_matches=$cacheFrontData['page_url_matches'];
                    }
                    if(is_array($cacheFrontData['page_content_matches'])){
                        $this->eCpattern->page_content_matches=$cacheFrontData['page_content_matches'];
                    }
                    
                    if(is_array($cacheFrontData['pn_area_matches'])){
                        $this->eCpattern->pn_area_matches=$cacheFrontData['pn_area_matches'];
                    }
                    if(is_array($cacheFrontData['pn_url_matches'])){
                        $this->eCpattern->pn_url_matches=$cacheFrontData['pn_url_matches'];
                    }
                    
                    if(is_array($cacheFrontData['cur_front_urls'])){
                        $this->eCpattern->cur_front_urls=$cacheFrontData['cur_front_urls'];
                    }
                    
                    if($cacheFrontData[$keyUseCookie]){
                        \util\Param::set_gsc_use_cookie('',$cacheFrontData[$keyUseCookie]);
                    }
                    if($cacheFrontData[$keyUseCookieImg]){
                        \util\Param::set_gsc_use_cookie('img',$cacheFrontData[$keyUseCookieImg]);
                    }
                    if($cacheFrontData[$keyUseCookieFile]){
                        \util\Param::set_gsc_use_cookie('file',$cacheFrontData[$keyUseCookieFile]);
                    }
                }
            }
        }
        return $collData;
    }
    
    
    public function front_urlsAction(){
        $collData=$this->_test_init();
        $frontDataList=array();
        foreach ($this->eCpattern->cur_front_urls as $fname=>$furl){
            $frontData=array('name'=>$fname,'url'=>$furl);
            $htmlInfo=$this->eCpattern->get_page_html($furl,'front_url',$fname,false,true);
            $frontData['html']=$htmlInfo['html'];
            $frontData['header']=$htmlInfo['header'];
            $frontData['content_sign']=array();
            $contentSign=$this->eCpattern->get_page_content_match('front_url', $fname);
            if(is_array($contentSign)){
                foreach ($contentSign as $csk=>$csv){
                    $csk=preg_replace('/^match/', '', $csk);
                    $frontData['content_sign'][cp_sign('match', $csk)]=$csv;
                }
            }
            $frontDataList[]=$frontData;
        }
        $this->set_html_tags('测试抓取前置页','测试抓取前置页'.$this->_opened_tools());
        $this->assign('frontDataList',$frontDataList);
        return $this->fetch('cpattern:test_front_urls');
    }
    
    
    public function source_urlsAction(){
        $collData=$this->_test_init();
        $sourceIsUrl=$this->eCpattern->source_is_url();
        $source_urls=array();
        if(is_array($this->eCpattern->config['source_url'])){
            foreach ($this->eCpattern->config['source_url'] as $k => $v) {
                if(empty($v)){
                    continue;
                }
                $vurls=$this->eCpattern->source_url_convert( $v );
                if(!is_array($vurls)){
                    $vurls=array($vurls);
                }
                if($sourceIsUrl){
                    
                    $vurls=$this->eCpattern->page_convert_url_signs('url', '', false, $vurls, array(), false);
                }else{
                   
                   $vurls=$this->eCpattern->page_convert_url_signs('source_url', '', false, $vurls, array(), false);
                }
                $source_urls[$v]=$vurls;
            }
        }
        
        if(!$sourceIsUrl){
            
            $source_urls1=array();
            foreach ($source_urls as $v){
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
        
        $sourceUrlOpened=$this->_page_opened_tips('source_url');
        
        $this->assign('testNum',$testNum);
        $this->assign('source_urls',$source_urls);
        $this->assign('sourceIsUrl',$sourceIsUrl);
        $this->assign('sourceUrlOpened',$sourceUrlOpened);
        $this->assign('config',$this->eCpattern->config);
        $this->assign('openedTools',$this->_opened_tools(false));
        return $this->fetch('cpattern:test_source_urls');
    }
    private function _page_opened_tips($pageType,$pageName=''){
        return $this->eCpattern->page_opened_tips($pageType,$pageName,false,true);
    }
    
    private function _opened_tools($isHead=true){
        $opened_tools=array();
        if($this->eCpattern->page_render_is_open()){
            $opened_tools[]='页面渲染';
        }
        if(g_sc_c('proxy','open')){
            $opened_tools[]='代理';
        }
        if(!empty($opened_tools)){
            $opened_tools='已开启功能：'.implode('、', $opened_tools);
            if($isHead){
                
                $opened_tools=' <small>'.$opened_tools.'</small>';
            }
        }else{
            $opened_tools='';
        }
        return $opened_tools;
    }
    
    public function cont_urlsAction(){
        $collData=$this->_test_init(false);
        
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
            if(is_array($cacheParentData['page_content_matches'])){
                $this->eCpattern->page_content_matches=$cacheParentData['page_content_matches'];
            }
            if(is_array($cacheParentData['pn_area_matches'])){
                $this->eCpattern->pn_area_matches=$cacheParentData['pn_area_matches'];
            }
            if(is_array($cacheParentData['pn_url_matches'])){
                $this->eCpattern->pn_url_matches=$cacheParentData['pn_url_matches'];
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
        
        $levelData=$this->eCpattern->collLevelUrls($source_url,$curLevel,false);
        
        
        $cachePageData=cache($cacheKeyPre.$curLevel);
        if(!is_array($cachePageData)){
            $cachePageData=array();
        }
        
        $cachePageData[md5($source_url)]=array(
            'page_area_matches'=>$this->eCpattern->page_area_matches,
            'page_url_matches'=>$this->eCpattern->page_url_matches,
            'page_content_matches'=>$this->eCpattern->page_content_matches,
            'pn_area_matches'=>$this->eCpattern->pn_area_matches,
            'pn_url_matches'=>$this->eCpattern->pn_url_matches,
            'cur_level_urls'=>$this->eCpattern->cur_level_urls
        );
        cache($cacheKeyPre.$curLevel,$cachePageData,1200);
        
        $urls=$levelData['urls'];
        init_array($urls);
        
        
        $urlMsgLinks=array();
        foreach ($urls as $k=>$v){
            $k=\util\Tools::echo_url_msg_link($v);
            if($k){
                $urlMsgLinks[$v]=$k;
            }
        }
        
        $this->success('', null, array(
            'sourceUrl'=>$source_url,
            'urls' => $levelData['urls'],
            'urlMsgLinks' => $urlMsgLinks,
            'urlOpened'=>$this->_page_opened_tips('url'),
            'levelName' => $levelData['levelName'],
            'level' => $curLevel,
            'nextLevel' => $levelData['nextLevel'],
            'levelOpened'=>$this->_page_opened_tips('level_url',$levelData['levelName']),
        ));
    }
    
    
    public function test_urlAction(){
        $collData=$this->_test_init(true,true);
        
        $this->set_html_tags('测试抓取','测试抓取'.$this->_opened_tools());
        
        $test_url=input('test_url','','trim');
        $test=input('test');
        
        $this->assign('test_url',$test_url);
        $this->assign('test',$test);
        
        $urlParams=input('param.','','trim');
        $urlParams=base64_encode(serialize($urlParams));
        
        $urlOpened=$this->_page_opened_tips('url');
        
        $pageSource=input('page_source','');
        if($pageSource=='source_url'&&$this->eCpattern->source_is_url()){
            $pageSource='url';
        }
        
        $this->assign('pageSource',$pageSource);
        $this->assign('urlOpened',$urlOpened);
        $this->assign('pageSources',$this->eCpattern->page_source_options());
        $this->assign('urlParams',$urlParams);
        if(request()->isAjax()){
            return view('cpattern:test_test_url_ajax');
        }else{
            return $this->fetch('cpattern:test_test_url');
        }
    }
    
    
    public function input_urlAction(){
        $collData=$this->_test_init(true,true);
        $test=input('test','');
        $pageSource=input('page_source','');
        $urlParams=input('url_params','','trim');
        $inputedUrls=trim_input_array('inputed_urls');
        
        list($pageType,$pageName)=$this->eCpattern->page_source_split($pageSource);
        
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
            $input_urls=$this->eCpattern->single_get_input_urls($inputedUrls, $input_urls);
        }elseif($test=='get_html'||$test=='get_browser'){
            
            if(!empty($pageType)){
                
                $this->eCpattern->single_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
            }
        }elseif($test=='get_signs'){
            if(empty($pageType)){
                
                $input_urls['source_url']=$inputedUrls['source_url'];
                
                if(is_array($this->eCpattern->config['level_urls'])){
                    foreach($this->eCpattern->config['level_urls'] as $levIx=>$levVal){
                        
                        $level=$levIx+1;
                        $input_urls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>$inputedUrls['level'.$level.'_url']);
                    }
                }
            }else{
                
                $this->eCpattern->single_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
                if(input('?signs_cur_all')){
                    
                    $prevPageSource=$this->eCpattern->single_parent_page($pageType=='url'?true:false,$pageType,$pageName);
                    if($prevPageSource){
                        
                        list($prevPageType,$prevPageName)=$this->eCpattern->page_source_split($prevPageSource);
                        if($prevPageType=='level_url'){
                            if(is_array($this->eCpattern->config['level_urls'])){
                                foreach ($this->eCpattern->config['level_urls'] as $k=>$v){
                                    if($v['name']==$prevPageName){
                                        $curLevelNum=$k+1;
                                        $input_urls['level_url'][$curLevelNum]=array('level'=>$curLevelNum,'name'=>$v['name'],'url'=>$inputedUrls['level'.$curLevelNum.'_url']);
                                        break;
                                    }
                                }
                            }
                        }else{
                            $input_urls[$prevPageType]=$inputedUrls[$prevPageType]?$inputedUrls[$prevPageType]:'';
                        }
                        
                        $this->eCpattern->single_input_urls($prevPageType=='url'?true:false,$prevPageType,$prevPageName,$inputedUrls,$input_urls);
                    }
                }
            }
        }elseif($test=='get_relation_urls'){
            
            $this->eCpattern->single_input_urls(true,'url','',$inputedUrls,$input_urls);
            if(is_array($this->eCpattern->config['relation_urls'])){
                foreach ($this->eCpattern->config['relation_urls'] as $relationUrl){
                    $this->eCpattern->single_input_urls(true,'relation_url',$relationUrl['name'],$inputedUrls,$input_urls);
                }
            }
        }elseif($test=='get_pagination'){
            
            $this->eCpattern->single_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
        }
        
        if($test!='get_fields'){
            
            $this->eCpattern->single_urls_parent($pageType=='url'?true:false, $input_urls, $inputedUrls, $input_urls);
            if(is_array($input_urls['level_url'])){
                
                ksort($input_urls['level_url']);
            }
            if($this->eCpattern->source_is_url()){
                
                unset($input_urls['source_url']);
            }
        }
        
        $pageOpenedList=array();
        foreach ($input_urls as $iu_type=>$iu_urls){
            if($this->eCpattern->page_is_list($iu_type)){
                $pageOpenedList[$iu_type]=array();
                foreach ($iu_urls as $v){
                    $pageOpenedList[$iu_type][$v['name']]=$this->_page_opened_tips($iu_type,$v['name']);
                }
            }else{
                $pageOpenedList[$iu_type]=$this->_page_opened_tips($iu_type);
            }
        }
        
        $pageOpened=$this->_page_opened_tips($pageType,$pageName);
        
        $this->assign('input_urls',$input_urls);
        $this->assign('pageOpenedList',$pageOpenedList);
        $this->assign('pageOpened',$pageOpened);
        return $this->fetch('cpattern:test_input_url');
    }
    
    public function get_fieldsAction(){
        set_g_sc('is_test_echo_msg', 1);
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
    public function get_paginationAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_pagination',$collData);
    }
    public function get_relation_urlsAction(){
        $collData=$this->_test_init();
        $this->_get_test_content('get_relation_urls',$collData);
    }
    public function get_browserAction(){
        $this->_get_browser();
    }
    private function _get_browser($isBrowser=false){
        $collData=$this->_test_init();
        
        $test_url=input('test_url','','trim');
        if(!\util\Funcs::is_right_url($test_url)){
            
            $test_url='http://'.$test_url;
        }
        $pageSource=input('page_source','');
        list($pageType,$pageName)=$this->eCpattern->page_source_split($pageSource);
        $inputedUrls=input('get.','','trim');
        init_array($inputedUrls);
        $errorUrl=$inputedUrls;
        $errorUrl['coll_id']=$collData['id'];
        $errorUrl['test']='get_browser';
        $errorUrl='cpattern_test/test_url?'.http_build_query($errorUrl);
        if(empty($test_url)){
            $this->error('请输入网址',$errorUrl);
        }
        if(empty($pageType)){
            $this->error('请选择页面类型',$errorUrl);
        }
        if(!$isBrowser){
            
            $params=input('get.','','trim');
            $this->success('','cpattern_test/browser?'.http_build_query($params));
        }else{
            
            if(!empty($pageType)){
                
                $input_urls=array();
                $this->eCpattern->single_input_urls($pageType=='url'?true:false,$pageType,$pageName,$inputedUrls,$input_urls);
                $this->eCpattern->single_urls_parent($pageType=='url'?true:false, $input_urls, $inputedUrls, $input_urls);
                
                if(isset($input_urls['source_url'])&&empty($input_urls['source_url'])){
                    $this->error('请输入起始页',$errorUrl);
                }
                if(is_array($input_urls['level_url'])){
                    foreach ($input_urls['level_url'] as $k=>$v){
                        if(empty($v['url'])){
                            $this->error('请输入多级页：'.$v['name'],$errorUrl);
                        }
                    }
                }
                if(isset($input_urls['url'])&&empty($input_urls['url'])){
                    $this->error('请输入内容页',$errorUrl);
                }
                $this->_get_test_content('get_browser', $collData);
            }
            
            $html=$this->eCpattern->get_page_html($test_url, $pageType, $pageName);
            $jsonHtml=\util\Funcs::convert_html2json($html,true);
            
            
            if(empty($jsonHtml)){
                
                $html=\util\Funcs::html_clear_js($html);
                
                
                $scjNames=array();
                $html=preg_replace_callback('/(<[a-zA-Z]+\b[^<>]*)(>)/', function($match)use(&$scjNames){
                    do{
                        $scjName=\util\Funcs::uniqid();
                    }while($scjNames[$scjName]);
                    $scjNames[$scjName]=1;
                    return $match[1].' skycaiji-no="'.$scjName.'"'.$match[2];
                }, $html);
                
                $configUnset=array();
                $configSetted=array();
                if(!$this->eCpattern->get_config('url_complete')){
                    $configUnset[]='自动补全网址';
                }
                if($this->eCpattern->renderer_is_open($pageType,$pageName)){
                    $configSetted[]='页面渲染';
                }
                if(g_sc_c('proxy','open')){
                    $configSetted[]='代理';
                }
                
                $htmlTxt=str_replace(array('&','<','>'), array('&amp;','&lt;','&gt;'), $html);
                
                header("Content-type:text/html;charset=utf-8");
                
                $this->assign('configTips',array('setted'=>$configSetted,'unset'=>$configUnset));
                $this->assign('html',$html);
                $this->assign('htmlTxt',$htmlTxt);
                return $this->fetch('cpattern:browser');
            }else{
                
                $this->set_html_tags('分析网页','分析网页');
                $this->assign('jsonHtml',$jsonHtml);
                return $this->fetch('cpattern:browser_json');
            }
        }
    }
    private function _get_test_content($testName,$collData){
        $test_url=input('test_url','','trim');
        
        $pageType='';
        $pageName='';
        $pageSource=input('page_source','url');
        if(in_array($testName,array('get_html','get_browser','get_signs','get_pagination'))){
            
            list($pageType,$pageName)=$this->eCpattern->page_source_split($pageSource);
        }else{
            
            $pageType='url';
            $pageName='';
        }
        
        $inputLevels=array();
        foreach (input('param.') as $k=>$v){
            
            if(preg_match('/^level(\d+)_url$/',$k,$mLevel)){
                
                $mLevel=intval($mLevel[1]);
                $inputLevels[$mLevel]=input($k,'','trim');
            }
        }
        
        $this->eCpattern->loadSingle(
            $pageType,$pageName,$test_url,
            input('?source_url')?input('source_url','','trim'):null,
            $inputLevels,
            input('?url')?input('url','','trim'):null
        );
        
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
                
                $data=array();
                $this->eCpattern->cur_cont_url=$test_url;
                if(!empty($this->eCpattern->config['new_relation_urls'])){
                    
                    foreach ($this->eCpattern->config['new_relation_urls'] as $name=>$v){
                        $this->eCpattern->getRelationUrl($name, $test_url, '');
                    }
                }
                
                if(input('?signs_cur_all')){
                    
                    $curMatch=$this->_get_matches_from_signs('url', '', 'all');
                    $curMatch['name']='当前'.$this->eCpattern->page_source_name('url', '');
                    $data['cur']=$curMatch;
                }
                
                
                if(!empty($this->eCpattern->config['new_front_urls'])){
                    
                    foreach($this->eCpattern->config['new_front_urls'] as $name=>$v){
                        $matchList[]=$this->_get_matches_from_signs('front_url', $name, 'all');
                    }
                }
                
                $matchList[]=$this->_get_matches_from_signs('source_url', '', 'all');
                
                if(!empty($this->eCpattern->config['new_level_urls'])){
                    
                    foreach($this->eCpattern->config['new_level_urls'] as $name=>$v){
                        $matchList[]=$this->_get_matches_from_signs('level_url', $name, 'all');
                    }
                }
                
                $matchList[]=$this->_get_matches_from_signs('url', '', 'all');
                
                if(!empty($this->eCpattern->config['new_relation_urls'])){
                    
                    foreach($this->eCpattern->config['new_relation_urls'] as $name=>$v){
                        $matchList[]=$this->_get_matches_from_signs('relation_url', $name, 'all');
                    }
                }
                
                $data['list']=$matchList;
                
                $this->success('以下是所有页面的内容标签',null,$data);
            }else{
                
                if(input('?signs_cur_all')&&$pageType=='url'){
                    $this->eCpattern->get_page_html($test_url, $pageType, $pageName);
                }
                
                $data=array();
                
                $signs=$this->eCpattern->parent_page_signs($pageType,$pageName);
                if(input('?signs_cur_all')){
                    
                    $curMatch=$this->_get_matches_from_signs($pageType, $pageName, 'all');
                    $curMatch['name']='当前'.$this->eCpattern->page_source_name($pageType, $pageName);
                    $data['cur']=$curMatch;
                }
                
                
                if(is_array($signs['front_url'])){
                    foreach ($signs['front_url'] as $frontName=>$frontSings){
                        $matchList[]=$this->_get_matches_from_signs('front_url', $frontName, $frontSings);
                    }
                }
                
                
                if(is_array($signs['source_url'])){
                    $matchList[]=$this->_get_matches_from_signs('source_url', '', $signs['source_url']);
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
                
                $data['list']=$matchList;
                
                $this->success('以下是当前页规则中调用的其他页面'.cp_sign('match').'标签',null,$data);
            }
        }elseif('get_pagination'==$testName){
            
            if(empty($pageType)){
                $this->error('请选择页面类型');
            }elseif(!$this->eCpattern->page_has_pagination($pageType)){
                $this->error('该页面类型不支持分页');
            }
            
            $pnType=input('pagination_type');
            $pnUrls=null;
            $pnUrlMsgLinks=array();
            if(empty($pnType)){
                $pnUrls=$this->eCpattern->getPaginationUrls($pageType,$pageName,false,$test_url,'',true);
                foreach ($pnUrls as $k=>$v){
                    $k=\util\Tools::echo_url_msg_link($v);
                    if($k){
                        $pnUrlMsgLinks[$v]=$k;
                    }
                }
            }elseif($pnType=='next'){
                
                $pnUrls=$this->eCpattern->getPaginationUrls($pageType,$pageName,false,$test_url,'',true);
                if(!empty($pnUrls)){
                    $this->eCpattern->used_pagination_urls[$pageSource]=array();
                    $curPnUrl=reset($pnUrls);
                    $pnUrls=array();
                    $pnUrls[]=array('cur'=>$test_url,'next'=>$curPnUrl);
                    $loopMax=10;
                    $loopNum=0;
                    do{
                        $loopNum++;
                        
                        $doWhile=false;
                        $nextPnUrl=$this->eCpattern->getPaginationNext($pageType, $pageName, true, $curPnUrl, '');
                        if(!empty($nextPnUrl)){
                            $pnUrls[]=array('cur'=>$curPnUrl,'next'=>$nextPnUrl);
                            $curPnMd5=md5($curPnUrl);
                            $this->eCpattern->used_pagination_urls[$pageSource][$curPnMd5]=1;
                            $curPnUrl=$nextPnUrl;
                            $doWhile=true;
                        }
                        if($loopNum>=10){
                            
                            $doWhile=false;
                        }
                    }while($doWhile);
                    
                    foreach ($pnUrls as $k=>$v){
                        $msgLinkCur=\util\Tools::echo_url_msg_link($v['cur']);
                        if($msgLinkCur){
                            $pnUrlMsgLinks[$v['cur']]=$msgLinkCur;
                        }
                        $msgLinkNext=\util\Tools::echo_url_msg_link($v['next']);
                        if($msgLinkNext){
                            $pnUrlMsgLinks[$v['next']]=$msgLinkNext;
                        }
                    }
                }
            }
            
            if(empty($pnUrls)){
                $this->error('没有抓取到分页链接');
            }else{
                $pnUrls=is_array($pnUrls)?$pnUrls:array();
                $this->success('',null,array('pagination_type'=>$pnType,'urls'=>$pnUrls,'pn_links'=>$pnUrlMsgLinks));
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
            if(empty($pageType)){
                $this->error('请选择页面类型');
            }
            
            $htmlInfo=$this->eCpattern->get_page_html($test_url, $pageType, $pageName, false, true);
            
            if(empty($htmlInfo['html'])){
                $errorMsg='';
                if(is_array($htmlInfo['error'])){
                    $errorMsg=$htmlInfo['error']['msg'];
                }
                if($htmlInfo['header']){
                    $errorMsg.=($errorMsg?', ':'').$htmlInfo['header'];
                }
                $errorMsg=($errorMsg?'：':'').$errorMsg;
                exit('未抓取到源码'.$errorMsg);
            }else{
                
                exit(' '.$htmlInfo['html']);
            }
        }elseif('get_browser'==$testName){
            
        }
    }
    
    private function _get_matches_from_signs($pageType,$pageName,$signs){
        $matches=array(
            'name'=>$this->eCpattern->page_source_name($pageType, $pageName),
            'page_source'=>$this->eCpattern->page_source_merge($pageType, $pageName),
            'area'=>array(),'url'=>array(),'content'=>array()
        );
        $urlMd5='';
        if($pageType=='source_url'){
            $urlMd5=md5($this->eCpattern->cur_cont_url);
        }elseif($pageType=='level_url'){
            $urlMd5=md5($this->eCpattern->cur_level_urls[$pageName]);
        }elseif($pageType=='url'){
            $urlMd5=md5($this->eCpattern->cur_cont_url);
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
            if(is_array($signs['content'])){
                foreach ($signs['content'] as $sign){
                    $match='match'.$sign['id'];
                    $matches['content'][$match]=$this->eCpattern->get_page_content_match($pageType,$pageName,$match);
                }
            }
        }elseif($signs==='all'){
            
            $matches['area']=$this->eCpattern->get_page_area_match($pageType,$pageName);
            $matches['url']=$this->eCpattern->get_page_url_match($pageType,$pageName,$urlMd5?$urlMd5:null);
            $matches['content']=$this->eCpattern->get_page_content_match($pageType,$pageName);
        }
        return $matches;
    }
    
    public function matchAction(){
        if(request()->isPost()){
            $this->_test_init();
            $inputType=input('input_type','content');
            $type=strtolower(input('type'));
            $field=input('field/a',array(),'trim');
            if($inputType=='url'){
                
                $pageSource=input('page_source','');
                $url=input('url','','trim');
                $config=input('config/a',array(),'trim');
                
                if(empty($pageSource)){
                    $this->error('请选择页面类型');
                }
                if(empty($url)){
                    $this->error('请输入网址');
                }
                
                list($pageType,$pageName)=$this->eCpattern->page_source_split($pageSource);
                $config=$this->eCpattern->page_set_config($pageType, $config);
                init_array($config);
                
                $eCpConfig1=null;
                $eCpConfig2=null;
                
                if($pageType=='front_url'||$pageType=='level_url'||$pageType=='relation_url'){
                    foreach ($this->eCpattern->config[$pageType.'s'] as $k=>$v){
                        if($v&&is_array($v)&&$v['name']==$pageName){
                            $eCpConfig1=&$this->eCpattern->config[$pageType.'s'][$k];
                            $eCpConfig2=&$this->eCpattern->config['new_'.$pageType.'s'][$v['name']];
                            break;
                        }
                    }
                }elseif($pageType=='source_url'){
                    if($this->eCpattern->source_is_url()){
                        $eCpConfig1=&$this->eCpattern->config;
                    }else{
                        $eCpConfig1=&$this->eCpattern->config['source_config'];
                    }
                }elseif($pageType=='url'){
                    $eCpConfig1=&$this->eCpattern->config;
                }
                if($eCpConfig1){
                    $eCpConfig1=array_merge($eCpConfig1,$config);
                }
                if($eCpConfig2){
                    $eCpConfig2=array_merge($eCpConfig2,$config);
                }
                
                $content=$this->eCpattern->get_page_html($url, $pageType, $pageName);
            }else{
                
                $content=input('content','','trim');
                if(empty($content)){
                    $this->error('请输入内容');
                }
            }
            
            $val='';
            
            if($type=='rule'){
                
                $val=$this->eCpattern->rule_module_rule_data_get(array(
                    'rule' => $field['rule'],
                    'rule_merge' => $field['rule_merge'],
                    'rule_multi' => $field['rule_multi'],
                    'rule_multi_str' => $field['rule_multi_str'],
                    'rule_flags'=>$this->eCpattern->config['reg_regexp_flags'],
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
            }
            $this->success($val);
        }else{
            $this->_test_init(true,true);
            $this->set_html_tags('模拟匹配','模拟匹配'.$this->_opened_tools());
            
            $defConfig=array('charset'=>'','encode'=>'','page_render'=>'');
            foreach($defConfig as $k=>$v){
                $defConfig[$k]=$this->eCpattern->get_config($k);
                $defConfig[$k]=htmlspecialchars($defConfig[$k]);
            }
            $defConfig['request_headers_open']=$this->eCpattern->get_config('request_headers','open');
            
            $this->assign('defConfig',$defConfig);
            $this->assign('pageSources',$this->eCpattern->page_source_options());
            
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