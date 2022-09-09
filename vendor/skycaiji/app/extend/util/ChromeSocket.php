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

/*谷歌浏览器*/
namespace util;

class ChromeSocket{
    protected $filename;
    protected $timeout=30;
    protected $isHttps=false;
    protected $host;
    protected $port;
    protected $address;
    protected $addressUrl;
    protected $options;
    protected $socket;
    protected $tabId;
    protected $isProxy=false;
    static protected $passType=array('Stylesheet'=>1,'Image'=>1,'Media'=>1,'Font'=>1);
    
    public function __construct($host,$port,$timeout=30,$filename='',$options=array()){
        if($host){
            $this->isHttps=stripos($host,'https://')===0?true:false;
            $host=preg_replace('/^\w+\:\/\//i', '', $host);
        }
        $this->host=empty($host)?'127.0.0.1':$host;
        $port=intval($port);
        $this->port=self::defaultPort($port);
        $this->address=$this->host.($this->port?(':'.$this->port):'');
        $this->addressUrl=($this->isHttps?'https':'http').'://'.$this->address;
        
        $timeout=intval($timeout);
        $this->timeout=$timeout<=0?30:$timeout;
        $this->filename=$filename?$filename:'chrome';
        $this->options=is_array($options)?$options:array();
    }
    public function __destruct(){
        if(!empty($this->tabId)){
            
            $this->closeTab($this->tabId);
        }
    }
    
    public static function defaultPort($port){
        $port=intval($port);
        $port=$port>0?$port:9222;
        return $port;
    }
    
    public function openHost(){
        if(!in_array(strtolower($this->host),array('localhost','127.0.0.1','0.0.0.0'))){
            
            return;
        }
        $return=self::execHeadless($this->filename,$this->port,$this->options,false,false);
        if(!empty($return['error'])){
            throw new \Exception($return['error']);
        }
    }
    
    public static function execHeadless($filename,$port,$options,$showInfo,$isTest){
        set_time_limit(15);
        $port=self::defaultPort($port);
        $options=is_array($options)?$options:array();
        $return=array('error'=>'','info'=>'');
        if(version_compare(PHP_VERSION,'5.5','<')){
            
            $return['error']='该功能仅支持php5.5及以上版本';
        }elseif(empty($port)){
            $return['error']='请设置端口';
        }elseif($port==80){
            $return['error']='不能设置为80端口';
        }elseif(!empty($options['user_data_dir'])&&!is_dir($options['user_data_dir'])){
            
            $return['error']='用户配置目录不存在！';
            if(\skycaiji\admin\model\Config::check_basedir_limited($options['user_data_dir'])){
                
                $return['error'].=lang('error_open_basedir');
            }
        }else{
            $hasProcOpen=function_exists('proc_open')?true:false;
            if($isTest&&!$hasProcOpen){
                
                $return['error']='需开启proc_open函数';
            }else{
                
                $command=$filename;
                if(empty($command)){
                    
                    $command='chrome';
                }else{
                    
                    $command=\skycaiji\admin\model\Config::cli_safe_filename($command);
                }
                $command.=' --headless --proxy-server';
                if(!empty($options['user_data_dir'])){
                    
                    $command=sprintf('%s --user-data-dir=%s',$command,$options['user_data_dir']);
                }
                if($isTest){
                    
                    $command=sprintf('%s',$command);
                }else{
                    $command=sprintf('%s --remote-debugging-port=%s',$command,$port);
                }
                if(!$hasProcOpen){
                    $return['error']='请开启proc_open函数或者手动执行命令：'.$command;
                }else{
                    
                    $return['info']=\util\Tools::proc_open_exec($command,$showInfo,10,$isTest?true:false);
                }
            }
        }
        return $return;
    }
    
    public function websocket($url='',$headers=array(),$options=array()){
        $headers=is_array($headers)?$headers:array();
        $headers=array_change_key_case($headers,CASE_LOWER);
        
        $options=is_array($options)?$options:array();
        $options['timeout']=$options['timeout']>0?$options['timeout']:$this->timeout;
        if(!empty($headers)){
            $options['headers']=is_array($options['headers'])?$options['headers']:array();
            $options['headers']=\util\Funcs::array_key_merge($options['headers'],$headers);
        }
        if(empty($url)){
            
            $url=sprintf('ws://%s/devtools/page/%s',$this->address,$this->tabId);
        }
        $this->loadWebsocket();
        $this->socket=new \WebSocket\Client($url,$options);
    }
    
    public function send($method,$params=array(),$id=0){
        if(empty($id)){
            static $no=1;
            $no++;
            $id=$no;
        }
        $data=array(
            'id'=>$id,
            'method'=>$method,
            'params'=>$params
        );
        $this->socket->send(json_encode($data));
        return $data;
    }
    
    public function getRenderHtml($url,$headers=array(),$options=array(),$fromEncode=null,$postData=null,$returnInfo=false){
        if(!preg_match('/^\w+\:\/\//', $url)){
            
            $url='http://'.$url;
        }
        if(!is_array($headers)){
            $headers=array();
        }
        
        $isPost=(isset($postData)&&$postData!==false)?true:false;
        
        $contentType=null;
        if($isPost){
            $contentType=\util\Funcs::array_val_in_keys($headers,array('content-type'),true);
        }
        
        $this->send('Network.enable');
        
        $curCookies=array();
        
        $sendData=$this->send('Network.getCookies',array('urls'=>array($url)));
        $data=$this->receiveById($sendData['id'],false);
        if($data['result']&&is_array($data['result']['cookies'])){
            foreach ($data['result']['cookies'] as $v){
                if($v['name']){
                    $curCookies[$v['name']]='';
                }
            }
        }
        if(!empty($headers['cookie'])){
            
            if(preg_match_all('/([^\;]+?)\=([^\;]*)/',$headers['cookie'],$mcookie)){
                foreach ($mcookie[1] as $k=>$v){
                    $v=trim($v);
                    if($v){
                        $curCookies[$v]=$mcookie[2][$k];
                    }
                }
            }
        }
        unset($headers['cookie']);
        
        if($this->isProxy&&!empty($options['proxy'])&&!empty($options['proxy']['user'])){
            
            $headers['Proxy-Authorization']='Basic '.base64_encode($options['proxy']['user'].':'.$options['proxy']['pwd']);
        }
        
        if(!empty($headers)){
            $this->send('Network.setExtraHTTPHeaders',array('headers'=>$headers));
        }
        if($options['useragent']){
            $this->send('Network.setUserAgentOverride',array('userAgent'=>$options['useragent']));
        }
        if(!empty($curCookies)){
            foreach ($curCookies as $k=>$v){
                $curCookies[$k]=array('name'=>$k,'value'=>$v,'url'=>$url);
            }
            $curCookies=array_values($curCookies);
            $this->send('Network.setCookies',array('cookies'=>$curCookies));
        }
        
        $fetchPatterns=array(array('urlPattern'=>'*','requestStage'=>'Request'));
        if($returnInfo){
            
            $fetchPatterns[]=array('urlPattern'=>'*','requestStage'=>'Response');
        }
        
        $this->send('Fetch.enable',array('patterns'=>$fetchPatterns));
        
        $this->send('Page.enable');
        if($isPost){
            
            if(!is_array($postData)){
                
                if(preg_match_all('/([^\&]+?)\=([^\&]*)/', $postData,$m_post_data)){
                    $new_post_data=array();
                    foreach($m_post_data[1] as $k=>$v){
                        $new_post_data[$v]=rawurldecode($m_post_data[2][$k]);
                    }
                    $postData=$new_post_data;
                }else{
                    $postData=array();
                }
            }
            
            $formHtml='';
            foreach ($postData as $k=>$v){
                $formHtml.='<input type="text" name="'.addslashes($k).'" value="'.addslashes($v).'">';
            }
            
            $postForm='var postForm=document.createElement("form");';
            if(!empty($postData)&&!empty($fromEncode)&&!in_array(strtolower($fromEncode),array('auto','utf-8','utf8'))){
                
                $postForm.='postForm.acceptCharset="'.addslashes($fromEncode).'";';
            }
            if(!empty($contentType)){
                $postForm.='postForm.enctype="'.addslashes($contentType).'";';
            }
            $postForm.='postForm.method="post";'
                .'postForm.action="'.addslashes($url).'";'
                .'postForm.innerHTML=`'.$formHtml.'`;'
                .'document.documentElement.appendChild(postForm);'
                .'postForm.submit();';
            $sendData=$this->send('Runtime.evaluate',array('expression'=>$postForm));
        }else{
            
            $sendData=$this->send('Page.navigate',array('url'=>$url));
        }
        $htmlInfo=array('code'=>0,'ok'=>'','header'=>'','resp_header'=>'','html'=>'');
        
        $complete=false;
        $startTime=time();
        while((time()-$startTime)<=$this->timeout){
            
            $data=$this->receive();
            if(!$data){
                
                break;
            }
            if($data['method']=='Page.loadEventFired'){
                
                $complete=true;
                break;
            }elseif($data['method']=='Fetch.requestPaused'){
                
                $dataParams=is_array($data['params'])?$data['params']:array();
                
                $fParams=array('requestId'=>$dataParams['requestId']);
                if(isset(self::$passType[$dataParams['resourceType']])){
                    $fParams['errorReason']='Aborted';
                    $this->send('Fetch.failRequest',$fParams);
                }else{
                    $this->send('Fetch.continueRequest',$fParams);
                }
                if($returnInfo){
                    if($dataParams['request']&&$dataParams['request']['url']==$url){
                        
                        $htmlInfo['code']=$dataParams['responseStatusCode'];
                        $htmlInfo['header']=$dataParams['request']['headers'];
                        $htmlInfo['resp_header']=$dataParams['responseHeaders'];
                    }
                }
            }
        }
        if($returnInfo){
            if(!is_array($htmlInfo['header'])){
                $htmlInfo['header']=array();
            }
        }
        $html=null;
        if($complete){
            
            $sendData=$this->send('Runtime.evaluate',array('expression'=>'document.documentElement.outerHTML'));
            $data=$this->receiveById($sendData['id'],false);
            if($data['result']&&$data['result']['result']){
                $html=$data['result']['result']['value'];
            }
            if(preg_match('/^\{(.+\:.+,*){1,}\}$/', strip_tags($html))){
                
                $html=strip_tags($html);
            }
            if($returnInfo){
                
                if(is_array($htmlInfo['resp_header'])){
                    foreach ($htmlInfo['resp_header'] as $v){
                        if(is_array($v)&&$v['name']){
                            $htmlInfo['header'][$v['name']]=$v['value'];
                        }
                    }
                }
                
                $sendData=$this->send('Network.getCookies',array('urls'=>array($url)));
                $data=$this->receiveById($sendData['id'],false);
                if($data['result']&&is_array($data['result']['cookies'])){
                    $hdCookie=\util\Funcs::array_val_in_keys($htmlInfo['header'],array('cookie'),true);
                    $hdCookie=$hdCookie?(rtrim($hdCookie,';').';'):'';
                    foreach ($data['result']['cookies'] as $v){
                        if($v['name']){
                            $hdCookie.=$v['name'].'='.$v['value'].';';
                        }
                    }
                    if($hdCookie){
                        $htmlInfo['header']['Cookie']=$hdCookie;
                    }
                }
            }
        }
        $html=$html?$html:'';
        if($returnInfo){
            $headers=array();
            if(is_array($htmlInfo['header'])){
                foreach ($htmlInfo['header'] as $k=>$v){
                    $headers[]=$k.': '.$v;
                }
            }
            unset($htmlInfo['resp_header']);
            $htmlInfo['code']=intval($htmlInfo['code']);
            $htmlInfo['ok']=($htmlInfo['code']>=200&&$htmlInfo['code']<300)?true:false;
            $htmlInfo['header']=implode("\r\n", $headers);
            $htmlInfo['html']=$html;
            return $htmlInfo;
        }else{
            return $html;
        }
    }
    
    public function receive(){
        try {
            $data=$this->socket->receive();
        }catch (\Exception $ex){
            
            $data=null;
        }
        return $data?json_decode($data,true):null;
    }
    
    public function receiveById($id,$returnAll=false){
        $startTime=time();
        $complete=false;
        $result=null;
        $all=array();
        while((time()-$startTime)<=$this->timeout){
            
            $data=$this->receive();
            if(!$data){
                
                break;
            }
            if($data['id']==$id){
                $result=$data;
                break;
            }
            if($data['method']=='Fetch.requestPaused'){
                
                $dataParams=is_array($data['params'])?$data['params']:array();
                
                $fParams=array('requestId'=>$dataParams['requestId']);
                if(isset(self::$passType[$dataParams['resourceType']])){
                    $fParams['errorReason']='Aborted';
                    $this->send('Fetch.failRequest',$fParams);
                }else{
                    $this->send('Fetch.continueRequest',$fParams);
                }
            }
            if($returnAll){
                $all[]=$data;
            }
        }
        if($returnAll){
            
            return array('all'=>$all,'result'=>$result);
        }else{
            return $result;
        }
    }
    
    public function getTabs(){
        $data=get_html($this->addressUrl.'/json');
        $data=empty($data)?array():json_decode($data,true);
        return $data;
    }
    
    public function newTab($proxyData=null){
        $tabId='';
        $verData=null;
        $proxyServer=null;
        if($proxyData&&is_array($proxyData)&&$proxyData['ip']){
            
            $proxyServer=($proxyData['type']?($proxyData['type'].'://'):'').$proxyData['ip'].($proxyData['port']?(':'.$proxyData['port']):'');
            $verData=$this->getVersion();
        }
        $this->isProxy=false;
        if(!empty($verData)&&!empty($verData['webSocketDebuggerUrl'])){
            
            $this->isProxy=true;
            $this->websocket($verData['webSocketDebuggerUrl']);
            $sendData=$this->send('Target.createBrowserContext',array('proxyServer'=>$proxyServer));
            $data=$this->receiveById($sendData['id'],false);
            $sendData=$this->send('Target.createTarget',array('url'=>'about:blank','browserContextId'=>$data['result']['browserContextId']));
            $data=$this->receiveById($sendData['id'],false);
            $tabId=$data['result']['targetId'];
        }else{
            
            $tabData=get_html($this->addressUrl.'/json/new');
            $tabData=empty($tabData)?array():json_decode($tabData,true);
            $tabId=$tabData['id'];
        }
        $tabId=$tabId?$tabId:'';
        $this->tabId=$tabId;
    }
    
    public function closeTab($id){
        get_html($this->addressUrl.'/json/close/'.$id,null,array('timeout'=>3));
    }
    
    public function getVersion(){
        $data=get_html($this->addressUrl.'/json/version');
        $data=empty($data)?array():json_decode($data,true);
        return $data;
    }
    
    public function hostIsOpen(){
        $data=$this->getVersion();
        if(!empty($data)&&!empty($data['webSocketDebuggerUrl'])){
            
            return true;
        }
        return false;
    }
    
    public function clearBrowser(){
        $verData=$this->getVersion();
        if(!empty($verData)&&!empty($verData['webSocketDebuggerUrl'])){
            
            
            $tabs=$this->getTabs();
            if($tabs){
                foreach ($tabs as $tab){
                    $this->closeTab($tab['id']);
                }
            }
            
            $this->websocket($verData['webSocketDebuggerUrl']);
            $this->send('Network.enable');
            $this->send('Network.clearBrowserCache');
            $this->send('Network.clearBrowserCookies');
            $this->send('Log.clear');
            $this->send('Storage.clearCookies');
        }
    }
    
    public function closeBrowser(){
        $verData=$this->getVersion();
        if(!empty($verData)&&!empty($verData['webSocketDebuggerUrl'])){
            
            $this->websocket($verData['webSocketDebuggerUrl']);
            $this->send('Browser.close');
        }
    }
    
    private function loadWebsocket(){
        
        static $loaded;
        if(!isset($loaded)){
            $loaded=true;
            
            \think\Loader::addNamespace('WebSocket',realpath(APP_PATH.'extend/websocket'));
        }
    }
}

?>