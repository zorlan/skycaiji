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
/*单页采集模式*/
class CpatternSingle extends Cpattern{
    public function echo_error($msg = '', $url = null, $data = array(), $wait = 3, array $header = []){
        if($this->is_collecting()){
            return parent::echo_error($msg,$url,$data,$wait,$header);
        }else{
            $url=$url?$url:'';
            $msg=$this->_echo_msg_str($msg,'red');
            $txt=g_sc('collect_echo_msg_txt');
            $txt=$txt?($txt."\r\n".$msg):$msg;
            if(\util\Param::is_collector_single()){
                
                $txt=strip_tags($txt);
                $this->jsonSend($txt);
            }else{
                parent::error($txt,$url,$data,$wait,$header);
            }
        }
    }
    public function collectSingle($singleConfig){
        init_array($singleConfig);
        $curUrl=input('url','','trim');
        if($curUrl&&!\util\Funcs::is_right_url($curUrl)){
            $curUrl='http://'.$curUrl;
        }
        $mcollected=model('Collected');
        $isCollected=$mcollected->collGetNumByUrl($curUrl)>0?true:false;
        $urlRepeat=$this->config['url_repeat'];
        $field_vals_list=null;
        if($singleConfig['always']||$urlRepeat||!$isCollected){
            
            $sourceUrl=input('?source_url')?input('source_url','','trim'):null;
            if($sourceUrl&&!\util\Funcs::is_right_url($sourceUrl)){
                $sourceUrl='http://'.$sourceUrl;
            }
            $inputLevels=array();
            foreach (input('param.') as $k=>$v){
                
                if(preg_match('/^level(\d+)_url$/',$k,$mLevel)){
                    
                    $mLevel=intval($mLevel[1]);
                    $inputLevels[$mLevel]=input($k,'','trim');
                    if($inputLevels[$mLevel]&&!\util\Funcs::is_right_url($inputLevels[$mLevel])){
                        $inputLevels[$mLevel]='http://'.$inputLevels[$mLevel];
                    }
                }
            }
            $mcollected=model('Collected');
            
            set_g_sc('collect_task_id',$this->collector['task_id']);
            set_g_sc(['c','caiji','interval'],0);
            set_g_sc(['c','caiji','interval_html'],0);
            $this->collect_num=0;
            $this->collected_field_list=array();
            
            $this->collFrontUrls();
            $this->loadSingle('url', '', $curUrl, $sourceUrl, $inputLevels, null);
            
            $field_vals_list=$this->getFields($curUrl);
            if($urlRepeat||!$isCollected){
                
                $this->_collect_fields_vals('', $curUrl, md5($curUrl), $field_vals_list, $urlRepeat);
            }else{
                if(empty($this->first_loop_field)){
                    
                    $field_vals_list=array($field_vals_list);
                }
            }
        }else{
            
            $this->echo_msg(array('已采集过网址：<a href="%s" target="_blank">%s</a>',$cont_url,$cont_url),'black');
        }
        
        return array('data'=>$field_vals_list,'collected'=>$this->collected_field_list);
    }
	
	public function loadSingle($pageType,$pageName,$curUrl,$sourceUrl,$levelUrls,$contUrl){
	    
	    if(empty($curUrl)){
	        $this->echo_error('请输入网址');
	    }
	    if(!\util\Funcs::is_right_url($curUrl)){
	        $curUrl='http://'.$curUrl;
	    }
	    
	    
	    if($pageType=='front_url'){
	        $this->cur_front_urls[$pageName]=$curUrl;
	        $this->collFrontUrls(true);
	    }elseif($pageType=='source_url'){
	        $this->cur_source_url=$curUrl;
	    }elseif($pageType=='level_url'){
	        $this->cur_level_urls[$pageName]=$curUrl;
	    }elseif($pageType=='url'){
	        $this->cur_cont_url=$curUrl;
	    }
	    
	    if(isset($sourceUrl)){
	        
	        
	        $this->cur_source_url=$sourceUrl;
	        if(empty($this->cur_source_url)){
	            $this->echo_error('请输入起始页');
	        }
	    }
	    if($levelUrls){
	        init_array($levelUrls);
	        
	        ksort($levelUrls);
	        foreach ($levelUrls as $k=>$v){
	            $levelName=$this->get_config('level_urls',$k-1,'name');
	            $this->cur_level_urls[$levelName]=$v;
	            if(empty($v)){
	                $this->echo_error('请输入多级页：'.$levelName);
	            }
	        }
	    }
	    if(isset($contUrl)){
	        
	        
	        $this->cur_cont_url=$contUrl;
	        if(empty($this->cur_cont_url)){
	            $this->echo_error('请输入内容页');
	        }
	    }
	    
	    
	    if(!empty($this->cur_source_url)){
	        
	        
	        if($pageType!='front_url'&&$pageType!='source_url'){
	            if(empty($this->config['level_urls'])){
	                
	                $this->getContUrls($this->cur_source_url,false);
	            }else{
	                
	                $this->getLevelUrls($this->cur_source_url,1,false);
	            }
	        }
	        
	        $this->get_page_html($this->cur_source_url, 'source_url', '');
	    }
	    if(!empty($this->cur_level_urls)){
	        
	        
	        $levelIsEnd=false;
	        $levelCurNum=0;
	        $levelCount=count($this->config['level_urls']);
	        foreach ($this->config['level_urls'] as $k=>$v){
	            $levelCurNum++;
	            if(isset($this->cur_level_urls[$v['name']])){
	                if($k==0){
	                    
	                    if($this->cur_source_url){
	                        $this->getLevelUrls($this->cur_source_url,$k+1,false);
	                    }
	                }else{
	                    
	                    $prevLevelUrl=$this->config['level_urls'][$k-1];
	                    if($this->cur_level_urls[$prevLevelUrl['name']]){
	                        $this->getLevelUrls($this->cur_level_urls[$prevLevelUrl['name']],$k+1,false);
	                    }
	                }
	                
	                $this->get_page_html($this->cur_level_urls[$v['name']], 'level_url', $v['name']);
	            }
	            if($levelCurNum==$levelCount){
	                $levelIsEnd=true;
	            }
	            
	            if($pageType=='level_url'&&$pageName==$v['name']){
	                break;
	            }
	        }
	        if($levelIsEnd){
	            
	            $endLevel=$this->config['level_urls'][$levelCount-1];
	            if(isset($this->cur_level_urls[$endLevel['name']])){
	                if($pageType!='level_url'||$pageName!=$endLevel['name']){
	                    
	                    $this->getContUrls($this->cur_level_urls[$endLevel['name']],false);
	                    
	                    $this->get_page_html($this->cur_level_urls[$endLevel['name']], 'level_url', $endLevel['name']);
	                }
	            }
	        }
	    }
	}
	
	
	public function single_input_urls($isContUrl,$pageType,$pageName,$inputedUrls,&$input_urls){
	    $pageSigns=$this->parent_page_signs($pageType,$pageName);
	    $this->single_signs_input_urls($isContUrl,false,$pageSigns,$inputedUrls,$input_urls);
	    $pageSigns=$this->parent_page_signs($pageType,$pageName,'url_web');
	    $this->single_signs_input_urls($isContUrl,true,$pageSigns,$inputedUrls,$input_urls);
	    $pageSigns=$this->parent_page_signs($pageType,$pageName,'renderer');
	    $this->single_signs_input_urls($isContUrl,true,$pageSigns,$inputedUrls,$input_urls);
	    $pageSigns=$this->parent_page_signs($pageType,$pageName,'pn:');
	    $this->single_signs_input_urls($isContUrl,true,$pageSigns,$inputedUrls,$input_urls);
	}
	
	
	public function single_signs_input_urls($isContUrl,$inPageConfig,$pageSigns,$inputedUrls,&$input_urls){
	    $iptUrls=array();
	    if(!empty($pageSigns)){
	        if($inPageConfig){
	            
	            if(!empty($pageSigns['cur'])&&(!empty($pageSigns['cur']['url'])||!empty($pageSigns['cur']['area']))){
	                
	                $prevPageSource=$this->single_parent_page($isContUrl, $pageSigns['cur']['page_type'], $pageSigns['cur']['page_name']);
	                if($prevPageSource){
	                    
	                    list($prevPageType,$prevPageName)=$this->page_source_split($prevPageSource);
	                    if($prevPageType=='level_url'){
	                        if(is_array($this->config['level_urls'])){
	                            foreach ($this->config['level_urls'] as $k=>$v){
	                                if($v['name']==$prevPageName){
	                                    $curLevelNum=$k+1;
	                                    $iptUrls['level_url'][$curLevelNum]=array('level'=>$curLevelNum,'name'=>$v['name'],'url'=>$inputedUrls['level'.$curLevelNum.'_url']);
	                                    break;
	                                }
	                            }
	                        }
	                    }else{
	                        $iptUrls[$prevPageType]=$inputedUrls[$prevPageType]?$inputedUrls[$prevPageType]:'';
	                    }
	                }
	            }
	        }
	        
	        
	        if(!empty($pageSigns['source_url'])&&is_array($pageSigns['source_url'])){
	            if(!empty($pageSigns['source_url']['content'])){
	                
	                $iptUrls['source_url']=$inputedUrls['source_url']?$inputedUrls['source_url']:'';
	            }
	        }
	        
	        
	        if(!empty($pageSigns['level_url'])&&is_array($pageSigns['level_url'])){
	            
	            $signLevels=array_keys($pageSigns['level_url']);
	            if(is_array($this->config['level_urls'])){
	                foreach ($this->config['level_urls'] as $levIx=>$levVal){
	                    
	                    
	                    $level=$levIx+1;
	                    if(in_array($levVal['name'],$signLevels)){
	                        
	                        if($level==1){
	                            
	                            $iptUrls['source_url']=$inputedUrls['source_url']?$inputedUrls['source_url']:'';
	                        }else{
	                            
	                            $prevLevel=$level-1;
	                            $iptUrls['level_url'][$prevLevel]=array('level'=>$prevLevel,'name'=>$this->get_config('level_urls',$prevLevel-1,'name'),'url'=>$inputedUrls['level'.$prevLevel.'_url']);
	                        }
	                        
	                        $iptUrls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>$inputedUrls['level'.$level.'_url']);
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
	
	
	public function single_urls_parent($isContUrl,$curInputUrls,$inputedUrls,&$input_urls){
	    $levelNames=array();
	    if(is_array($curInputUrls)&&is_array($curInputUrls['level_url'])){
	        foreach ($curInputUrls['level_url'] as $v){
	            $levelNames[$v['name']]=$v['name'];
	        }
	    }
	    if($levelNames){
	        foreach ($levelNames as $levelName){
	            $mergeTypes=array(''=>false,'url_web'=>true,'renderer'=>true);
	            foreach ($mergeTypes as $mtk=>$mtv){
	                $pageSigns=$this->parent_page_signs('level_url',$levelName,$mtk);
	                $iptUrls=$this->single_signs_input_urls($isContUrl,$mtv,$pageSigns,$inputedUrls,$input_urls);
	                if(is_array($iptUrls['level_url'])){
	                    
	                    foreach ($iptUrls['level_url'] as $k=>$v){
	                        if(isset($input_urls['level_url'][$k])){
	                            unset($iptUrls['level_url'][$k]);
	                        }
	                    }
	                    if(!empty($iptUrls['level_url'])){
	                        $this->single_urls_parent($isContUrl,$iptUrls, $inputedUrls, $input_urls);
	                    }
	                }
	            }
	        }
	    }
	}
	
	
	public function single_parent_page($isContUrl,$pageType,$pageName){
	    $prevPageType='';
	    $prevPageName='';
	    
	    if($pageType=='url'){
	        
	        if(empty($this->config['level_urls'])){
	            
	            $prevPageType='source_url';
	            $prevPageName='';
	        }else{
	            
	            $endLevelNum=count($this->config['level_urls']);
	            $prevPageType='level_url';
	            $prevPageName=$this->config['level_urls'][$endLevelNum-1]['name'];
	        }
	    }elseif($pageType=='level_url'){
	        
	        $prevLevelNum=-1;
	        $prevLevel=null;
	        if(is_array($this->config['level_urls'])){
	            foreach ($this->config['level_urls'] as $k=>$v){
	                $prevLevelNum=$k;
	                if($v['name']==$pageName){
	                    
	                    break;
	                }
	                $prevLevel=$v;
	            }
	        }
	        if($prevLevelNum>-1){
	            if($prevLevelNum==0){
	                
	                $prevPageType='source_url';
	                $prevPageName='';
	            }else{
	                
	                $prevPageType='level_url';
	                $prevPageName=$prevLevel['name'];
	            }
	        }
	    }elseif($pageType=='relation_url'){
	        
	        if(!$isContUrl){
	            
	            $prevPageType='url';
	            $prevPageName='';
	        }
	    }
	    return $this->page_source_merge($prevPageType, $prevPageName);
	}
	
	public function single_get_input_urls($inputedUrls,$input_urls){
	    init_array($inputedUrls);
	    if(empty($inputedUrls['source_url'])){
	        
	        $inputedUrls['source_url']='';
	    }
	    
	    
	    if(is_array($this->config['new_field_list'])){
	        foreach ($this->config['new_field_list'] as $field){
	            list($fPageType,$fPageName)=$this->page_source_split($field['field']['source']);
	            if(empty($fPageType)){
	                
	                if($field['field']['module']=='sign'){
	                    
	                    if(empty($this->config['level_urls'])){
	                        $input_urls['source_url']=$inputedUrls['source_url'];
	                    }else{
	                        
	                        $endLevelNum=count($this->config['level_urls']);
	                        $endLevel=$this->config['level_urls'][$endLevelNum-1];
	                        $input_urls['level_url'][$endLevelNum]=array('level'=>$endLevelNum,'name'=>$endLevel['name'],'url'=>$inputedUrls['level'.$endLevelNum.'_url']);
	                    }
	                }
	            }elseif('source_url'==$fPageType){
	                
	                $input_urls['source_url']=$inputedUrls['source_url'];
	            }elseif('level_url'==$fPageType){
	                
	                if(is_array($this->config['level_urls'])){
	                    foreach($this->config['level_urls'] as $levIx=>$levVal){
	                        if($field['field']['source']==$this->page_source_merge('level_url',$levVal['name'])){
	                            
	                            $level=$levIx+1;
	                            if($field['field']['module']=='sign'){
	                                
	                                if($level==1){
	                                    
	                                    $input_urls['source_url']=$inputedUrls['source_url'];
	                                }else{
	                                    
	                                    $prevLevel=$level-1;
	                                    $input_urls['level_url'][$prevLevel]=array('level'=>$prevLevel,'name'=>$this->get_config('level_urls',$prevLevel-1,'name'),'url'=>$inputedUrls['level'.$prevLevel.'_url']);
	                                }
	                            }
	                            
	                            $input_urls['level_url'][$level]=array('level'=>$level,'name'=>$levVal['name'],'url'=>$inputedUrls['level'.$level.'_url']);
	                            break;
	                        }
	                    }
	                }
	            }elseif('relation_url'==$fPageType){
	                
	                $this->single_input_urls(true,'relation_url',$fPageName,$inputedUrls,$input_urls);
	            }
	        }
	    }
	    
	    $pageSigns=$this->parent_page_signs('url','','url_web');
	    $this->single_signs_input_urls(true,true,$pageSigns,$inputedUrls,$input_urls);
	    $pageSigns=$this->parent_page_signs('url','','renderer');
	    $this->single_signs_input_urls(true,true,$pageSigns,$inputedUrls,$input_urls);
	    $pageSigns=$this->parent_page_signs('url','','pn:');
	    $this->single_signs_input_urls(true,true,$pageSigns,$inputedUrls,$input_urls);
	    
	    $this->single_urls_parent(true, $input_urls, $inputedUrls, $input_urls);
	    if(is_array($input_urls['level_url'])){
	        
	        ksort($input_urls['level_url']);
	    }
	    if($this->source_is_url()){
	        
	        unset($input_urls['source_url']);
	    }
	    
	    return $input_urls;
	}
}
?>