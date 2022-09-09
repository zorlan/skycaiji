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
        $toapi=input('toapi/a',array(),'trim');
        if(empty($toapi['url'])){
            $this->error('请输入接口地址');
        }
        if(empty($toapi['response']['id'])){
            $this->error('请输入响应id的健名');
        }
        
        
        $toapi['param_name']=is_array($toapi['param_name'])?$toapi['param_name']:array();
        $toapi['param_val']=is_array($toapi['param_val'])?$toapi['param_val']:array();
        $toapi['param_addon']=is_array($toapi['param_addon'])?$toapi['param_addon']:array();
        if(is_array($toapi['param_name'])){
            $toapi['param_name']=\util\Funcs::array_array_map('trim', $toapi['param_name']);
            foreach ($toapi['param_name'] as $k=>$v){
                if(empty($v)){
                    
                    unset($toapi['param_name'][$k]);
                    unset($toapi['param_val'][$k]);
                    unset($toapi['param_addon'][$k]);
                }
            }
        }
        
        $toapi['header_name']=is_array($toapi['header_name'])?$toapi['header_name']:array();
        $toapi['header_val']=is_array($toapi['header_val'])?$toapi['header_val']:array();
        $toapi['header_addon']=is_array($toapi['header_addon'])?$toapi['header_addon']:array();
        if(is_array($toapi['header_name'])){
            $toapi['header_name']=\util\Funcs::array_array_map('trim', $toapi['header_name']);
            foreach($toapi['header_name'] as $k=>$v){
                if(empty($v)){
                    
                    unset($toapi['header_name'][$k]);
                    unset($toapi['header_val'][$k]);
                    unset($toapi['header_addon'][$k]);
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
        $addedNum=0;
        if(empty($this->config['toapi']['url'])){
            $this->echo_msg('接口地址为空');
        }else{
            $testToapi=input('?test_toapi');
            
            
            $apiUrlMd5=md5($this->config['toapi']['url']);
            $apiUrl='';
            if(!isset($this->url_list[$apiUrlMd5])){
                
                $apiUrl=$this->config['toapi']['url'];
                if(strpos($apiUrl, '/')===0){
                    $apiUrl=config('root_website').$apiUrl;
                }elseif(!preg_match('/^\w+\:\/\//', $apiUrl)){
                    $apiUrl='http://'.$apiUrl;
                }
                $this->url_list[$apiUrlMd5]=$apiUrl;
            }else{
                $apiUrl=$this->url_list[$apiUrlMd5];
            }
            $apiResponse=$this->config['toapi']['response'];
            $apiResponse=is_array($apiResponse)?$apiResponse:array();
            
            $apiCharset=$this->config['toapi']['charset'];
            if($apiCharset=='custom'){
                $apiCharset=$this->config['toapi']['charset_custom'];
            }
            if(empty($apiCharset)){
                $apiCharset='utf-8';
            }
            
            $paramVals=array();
            $paramFields=array();
            if(is_array($this->config['toapi']['param_name'])){
                
                foreach($this->config['toapi']['param_name'] as $k=>$v){
                    if(empty($v)){
                        
                        continue;
                    }
                    $paramVals[$v]=$this->config['toapi']['param_val'][$k];
                    if($paramVals[$v]=='custom'){
                        
                        $paramVals[$v]=$this->config['toapi']['param_addon'][$k];
                    }elseif(preg_match('/^field\:(.+)$/ui',$paramVals[$v],$mField)){
                        
                        $paramVals[$v]='';
                        $paramFields[$v]=$mField[1];
                    }
                }
            }
            
            $headerVals=array();
            $headerFields=array();
            
            if($this->config['toapi']['content_type']){
                $headerVals['content-type']=$this->config['toapi']['content_type'];
            }
            if(is_array($this->config['toapi']['header_name'])){
                
                foreach($this->config['toapi']['header_name'] as $k=>$v){
                    if(empty($v)){
                        
                        continue;
                    }
                    $headerVals[$v]=$this->config['toapi']['header_val'][$k];
                    if($headerVals[$v]=='custom'){
                        
                        $headerVals[$v]=$this->config['toapi']['header_addon'][$k];
                    }elseif(preg_match('/^field\:(.+)$/ui',$headerVals[$v],$mField)){
                        
                        $headerVals[$v]='';
                        $headerFields[$v]=$mField[1];
                    }
                }
            }
            
            $apiWait=intval($this->config['toapi']['wait']);
            $apiRetry=intval($this->config['toapi']['retry']);
            foreach ($collFieldsList as $collFieldsKey=>$collFields){
                
                $contTitle=$collFields['title'];
                $contUrl=$collFields['url'];
                $collFields=$collFields['fields'];
                $this->init_download_img($this->task,$collFields);
                
                $postData=$paramVals;
                if(!empty($paramFields)){
                    
                    foreach ($paramFields as $k=>$v){
                        $postData[$k]=$this->get_field_val($collFields[$v]);
                    }
                }
                $url=$apiUrl;
                if($this->config['toapi']['type']=='post'){
                    
                    $postData=is_array($postData)?$postData:'';
                }else{
                    
                    $url=\util\Funcs::url_params_charset($url, $postData, $apiCharset);
                    $postData=null;
                }
                
                $headerData=$headerVals;
                if(!empty($headerFields)){
                    
                    foreach ($headerFields as $k=>$v){
                        $headerData[$k]=$this->get_field_val($collFields[$v]);
                    }
                }
                
                $curRetry=0;
                do{
                    $doWhile=false;
                    $html=get_html($url,$headerData,array(),$apiCharset,$postData);
                    $this->collect_sleep($this->config['toapi']['interval'],true);
                    $json=json_decode($html,true);
                    $returnData=array('id'=>'','target'=>'','desc'=>'','error'=>'');
                    if(!empty($apiResponse['id'])&&isset($json[$apiResponse['id']])){
                        
                        foreach ($returnData as $k=>$v){
                            
                            if(isset($apiResponse[$k])){
                                $returnData[$k]=$json[$apiResponse[$k]]?$json[$apiResponse[$k]]:'';
                            }else{
                                $returnData[$k]='';
                            }
                        }
                        if($returnData['id']>0){
                            $addedNum++;
                            if($returnData['id']>1&&empty($returnData['target'])){
                                
                                $returnData['target']='编号：'.$returnData['id'];
                            }
                        }
                    }else{
                        
                        if($curRetry<=0){
                            
                            $this->echo_msg('发布设置»调用接口失败');
                        }
                        $this->collect_sleep($apiWait);
                        
                        if($apiRetry>0){
                            
                            if($curRetry<$apiRetry){
                                
                                $curRetry++;
                                if($this->is_collecting()){
                                    $this->echo_msg(array('%s第%s次',$curRetry>1?' / ':'重试：',$curRetry),'black',true,'','display:inline;'.($curRetry==$apiRetry?'margin-right:5px;':''));
                                }
                                $doWhile=true;
                            }else{
                                $curRetry=0;
                            }
                        }
                        $returnData['id']=0;
                        $returnData['error']='发布接口无响应状态';
                    }
                }while($doWhile);
                
                $this->record_collected($contUrl,$returnData,$this->release,$contTitle);
                
                if($testToapi){
                    $this->echo_msg('<p>获取到响应数据：</p><textarea name="data" style="width:100%;margin:5px 0;" rows="5">'.htmlspecialchars($html).'</textarea>','black');
                }
                
                
                unset($collFieldsList[$collFieldsKey]['fields']);
            }
        }
        return $addedNum;
    }
}
?>