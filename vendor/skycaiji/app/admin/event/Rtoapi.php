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

/*发布设置:调用接口*/
namespace skycaiji\admin\event;
class Rtoapi extends Release{
    protected $url_list=array();
    /**
     * 设置页面post过来的config
     * @param unknown $config
     */
    public function setConfig($config){
        $toapi=\util\UnmaxPost::val('toapi/a',array(),'trim');
        if($toapi['module']=='app'){
            if(empty($toapi['app_url'])){
                $this->error('请输入接口地址');
            }
        }else{
            if(empty($toapi['url'])){
                $this->error('请输入接口地址');
            }
        }
        
        
        $toapi['param_name']=is_array($toapi['param_name'])?$toapi['param_name']:array();
        $toapi['param_val']=is_array($toapi['param_val'])?$toapi['param_val']:array();
        if(is_array($toapi['param_name'])){
            $toapi['param_name']=\util\Funcs::array_array_map('trim', $toapi['param_name']);
            foreach ($toapi['param_name'] as $k=>$v){
                if(empty($v)){
                    
                    unset($toapi['param_name'][$k]);
                    unset($toapi['param_val'][$k]);
                }
            }
        }
        
        $toapi['header_name']=is_array($toapi['header_name'])?$toapi['header_name']:array();
        $toapi['header_val']=is_array($toapi['header_val'])?$toapi['header_val']:array();
        if(is_array($toapi['header_name'])){
            $toapi['header_name']=\util\Funcs::array_array_map('trim', $toapi['header_name']);
            foreach($toapi['header_name'] as $k=>$v){
                if(empty($v)){
                    
                    unset($toapi['header_name'][$k]);
                    unset($toapi['header_val'][$k]);
                }
            }
        }
        $toapi['interval']=intval($toapi['interval']);
        $toapi['wait']=intval($toapi['wait']);
        $toapi['retry']=intval($toapi['retry']);
        
        $config['toapi']=$toapi;
        return $config;
    }
    
    
    /*导出数据*/
    public function export($collFieldsList,$options=null){
        $apiUrl='';
        $apiConfig=array();
        $paramVals=array();
        $headerVals=array();
        if($this->config['toapi']['module']=='app'){
            
            $apiUrl=$this->config['toapi']['app_url'];
            $appApi=$this->config['toapi']['app_api'];
            if(!empty($appApi)){
                $appApi=base64_decode($appApi);
                $appApi=$appApi?json_decode($appApi,true):'';
            }
            init_array($appApi);
            $apiConfig['type']=$appApi['type']?:'post';
            $apiConfig['charset']=$appApi['charset']?:'';
            $apiConfig['encode']=$appApi['encode']?:'';
            $apiConfig['response']=is_array($appApi['response'])?$appApi['response']:array();
            $headerVals=is_array($appApi['headers'])?$appApi['headers']:array();
            
            if($appApi['content_type']){
                $headerVals['content-type']=$appApi['content_type'];
            }
            
            $paramVals=$this->config['toapi']['app_params'];
            $appCustomParams=$this->config['toapi']['app_custom_params'];
            
            if($appCustomParams&&is_array($appCustomParams)){
                
                foreach($appCustomParams as $k=>$v){
                    if($paramVals[$k]){
                        
                        if(is_array($paramVals[$k])){
                            
                            $paramCustomIndex=array_search('@skycaiji_custom',$paramVals[$k]);
                            if(!is_empty($paramCustomIndex,true)){
                                
                                unset($paramVals[$k][$paramCustomIndex]);
                                $paramVals[$k]=array_values($paramVals[$k]);
                                static $appCustomList=array();
                                $vk=md5($v);
                                if(!isset($appCustomList[$vk])){
                                    if(preg_match_all('/[^\r\n]+/',$v,$vm)){
                                        $appCustomList[$vk]=$vm[0];
                                    }else{
                                        $appCustomList[$vk]=array();
                                    }
                                }
                                $paramVals[$k]=array_merge($paramVals[$k],$appCustomList[$vk]);
                            }
                        }else{
                            if($paramVals[$k]=='@skycaiji_custom'){
                                
                                $paramVals[$k]=$v;
                            }
                        }
                    }
                }
            }
        }else{
            
            $apiUrl=$this->config['toapi']['url'];
            $apiConfig['type']=$this->config['toapi']['type']?:'';
            $apiConfig['charset']=$this->config['toapi']['charset'];
            if($apiConfig['charset']=='custom'){
                $apiConfig['charset']=$this->config['toapi']['charset_custom'];
            }
            $apiConfig['encode']=$this->config['toapi']['encode'];
            if($apiConfig['encode']=='custom'){
                $apiConfig['encode']=$this->config['toapi']['encode_custom'];
            }
            
            if(is_array($this->config['toapi']['param_name'])){
                
                foreach($this->config['toapi']['param_name'] as $k=>$v){
                    if(empty($v)){
                        
                        continue;
                    }
                    $paramVals[$v]=$this->config['toapi']['param_val'][$k];
                }
            }
            
            
            if($this->config['toapi']['content_type']){
                $headerVals['content-type']=$this->config['toapi']['content_type'];
            }
            if(is_array($this->config['toapi']['header_name'])){
                
                foreach($this->config['toapi']['header_name'] as $k=>$v){
                    if(empty($v)){
                        
                        continue;
                    }
                    $headerVals[$v]=$this->config['toapi']['header_val'][$k];
                }
            }
            $apiConfig['response']=$this->config['toapi']['response'];
            
        }
        return $this->_export($collFieldsList,$options,$apiUrl,$apiConfig,$paramVals,$headerVals);
    }
    private function _export($collFieldsList,$options,$url,$apiConfig,$paramVals,$headerVals){
        $addedNum=0;
        if(empty($url)){
            $this->echo_msg('接口地址为空');
        }else{
            $testToapi=input('?test_toapi');
            
            init_array($paramVals);
            init_array($headerVals);
            
            $apiUrlMd5=md5($url);
            $apiUrl='';
            if(!isset($this->url_list[$apiUrlMd5])){
                
                $apiUrl=$url;
                if(strpos($apiUrl, '/')===0){
                    $apiUrl=config('root_website').$apiUrl;
                }elseif(!preg_match('/^\w+\:\/\//', $apiUrl)){
                    $apiUrl='http://'.$apiUrl;
                }
                $this->url_list[$apiUrlMd5]=$apiUrl;
            }else{
                $apiUrl=$this->url_list[$apiUrlMd5];
            }
            
            $apiResponse=is_array($apiConfig['response'])?$apiConfig['response']:array();
            
            if(empty($apiResponse['module'])){
                $apiResponse['id']=$apiResponse['id']?:'id';
                $apiResponse['target']=$apiResponse['target']?:'target';
                $apiResponse['desc']=$apiResponse['desc']?:'desc';
                $apiResponse['error']=$apiResponse['error']?:'error';
            }
            
            $apiCharset=$apiConfig['charset'];
            if(empty($apiCharset)){
                $apiCharset='utf-8';
            }
            
            $curlopts=array();
            
            if(!empty($apiConfig['encode'])){
                $curlopts[CURLOPT_ENCODING]=$apiConfig['encode'];
            }
            
            $retryWait=intval($this->config['toapi']['wait']);
            $retryMax=intval($this->config['toapi']['retry']);
            static $cpatternBase=null;
            if(!isset($cpatternBase)){
                $cpatternBase=controller('CpatternBase','event');
            }
            foreach ($collFieldsList as $collFieldsKey=>$collFields){
                
                $contTitle=$collFields['title'];
                $contContent=$collFields['content'];
                $contUrl=$collFields['url'];
                $collFields=$collFields['fields'];
                $this->init_download_config($this->task,$collFields);
                
                $postData=$this->_replace_fields($paramVals,$collFields);
                $url=$this->_replace_fields($apiUrl,$collFields);
                $url=\util\Funcs::url_auto_encode($url, $apiCharset);
                
                if($apiConfig['type']=='post'){
                    
                    $postData=is_array($postData)?$postData:'';
                }else{
                    
                    $url=\util\Funcs::url_params_charset($url, $postData, $apiCharset);
                    $postData=null;
                }
                
                $headerData=$this->_replace_fields($headerVals,$collFields);
                
                $retryCur=0;
                do{
                    $doWhile=false;
                    $htmlInfo=get_html($url,$headerData,array('timeout'=>60,'return_body'=>1,'curlopts'=>$curlopts),$apiCharset,$postData,true);
                    init_array($htmlInfo);
                    $html=$htmlInfo['html']?:'';
                    $this->collect_sleep($this->config['toapi']['interval'],true);
                    $returnData=array('id'=>'','target'=>'','desc'=>'','error'=>'');
                    
                    if(empty($apiResponse['module'])){
                        
                        $json=json_decode($html,true);
                        foreach ($returnData as $k=>$v){
                            if(isset($apiResponse[$k])){
                                $returnData[$k]=$cpatternBase->rule_module_json_data(array(
                                    'json' => $apiResponse[$k],
                                    'json_merge_data' =>  '',
                                    'json_arr' =>  '',
                                    'json_arr_implode' =>  '',
                                ), $json);
                            }
                        }
                    }elseif($apiResponse['module']=='xpath'){
                        
                        foreach ($returnData as $k=>$v){
                            if(isset($apiResponse[$k])){
                                $returnData[$k]=$cpatternBase->rule_module_xpath_data(array(
                                    'xpath' => $apiResponse[$k],
                                    'xpath_attr' => 'innerHtml',
                                    'xpath_multi' => '',
                                    'xpath_multi_str' => '',
                                ), $html);
                            }
                        }
                    }elseif($apiResponse['module']=='rule'){
                        
                        foreach ($returnData as $k=>$v){
                            if(isset($apiResponse[$k])){
                                $returnData[$k]=$cpatternBase->rule_module_rule_data_get(array(
                                    'rule' => $apiResponse[$k],
                                    'rule_merge' => '',
                                    'rule_multi' => '',
                                    'rule_multi_str' => '',
                                    'rule_flags'=>'iu',
                                ), $html,array(),true);
                            }
                        }
                    }
                    if(!is_empty($apiResponse['id'],true)&&$html&&isset($returnData['id'])){
                        
                        if($returnData['id']&&$returnData['id']>0){
                            $addedNum++;
                            if($returnData['id']>1&&empty($returnData['target'])){
                                
                                $returnData['target']='编号：'.$returnData['id'];
                            }
                        }
                    }else{
                        
                        $this->retry_first_echo($retryCur,'发布接口调用失败',null,$htmlInfo);
                        
                        $this->collect_sleep($retryWait);
                        
                        if($this->retry_do_func($retryCur,$retryMax,'发布接口无效')){
                            $doWhile=true;
                        }
                        
                        $returnData['id']=0;
                        $returnData['error']='未获取到响应状态';
                    }
                }while($doWhile);
                
                $this->record_collected($contUrl,$returnData,$this->release,array('title'=>$contTitle,'content'=>$contContent));
                
                if($testToapi){
                    $html='<form id="win_form_preview" method="post" target="_blank" action="'.url('tool/preview_data').'">'.html_usertoken()
                        .'<p>发布接口响应内容：<a href="javascript:;" onclick="document.getElementById(\'win_form_preview\').submit();">解析</a></p>'
                        .'<textarea name="data" style="width:100%;margin:5px 0;" rows="20">'.htmlspecialchars($html).'</textarea></form>';
                    $this->echo_msg($html,'black');
                }
                
                
                unset($collFieldsList[$collFieldsKey]['fields']);
            }
        }
        return $addedNum;
    }
    
    private function _replace_fields($data,$collFields){
        if(is_array($data)){
            foreach ($data as $k=>$v){
                $data[$k]=$this->_replace_fields($v,$collFields);
            }
        }else{
            $data=preg_replace_callback('/\[\x{91c7}\x{96c6}\x{5b57}\x{6bb5}\:(.+?)\]/u',function($match)use($collFields){
                $match=$match[1];
                return $this->get_field_val($collFields[$match]);
            },$data);
        }
        return $data;
    }
}
?>