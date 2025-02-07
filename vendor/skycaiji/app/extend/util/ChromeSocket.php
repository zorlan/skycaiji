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

/*Chrome/Chromium浏览器*/
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
    protected $startTime=0;
    static protected $passType=array('Image'=>1,'Media'=>1,'Font'=>1);
    
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
        if(in_array($port,array(80,8080,443))){
            
            $port=0;
        }
        $port=$port>0?$port:9222;
        return $port;
    }
    
    public function openHost(){
        if($this->serverIsLocal()){
            
            $return=self::execHeadless($this->filename,$this->port,$this->options,false);
            if(!empty($return['error'])){
                throw new \Exception($return['error']);
            }
        }
    }
    
    public function serverIsLocal(){
        if($this->options['server']=='remote'){
            return false;
        }
        return true;
    }
    
    public static function execHeadless($filename,$port,$options,$isTest){
        $port=self::defaultPort($port);
        $options=is_array($options)?$options:array();
        $return=array('error'=>'','info'=>'');
        if(empty($port)){
            $return['error']='请设置端口';
        }elseif(!empty($options['user_data_dir'])&&!is_dir($options['user_data_dir'])){
            
            $return['error']='用户配置目录不存在！';
            if(\skycaiji\admin\model\Config::check_basedir_limited($options['user_data_dir'])){
                
                $return['error'].=lang('error_open_basedir');
            }
        }else{
            $hasProcOpen=function_exists('proc_open')?true:false;
            
            
            $command=$filename;
            if(empty($command)){
                
                $command='chrome';
            }else{
                
                $command=\skycaiji\admin\model\Config::cli_safe_filename($command);
            }
            
            if($isTest&&!IS_WIN){
                
                $command.=' --version';
            }else{
                $command.=' --headless --proxy-server';
                if(!empty($options['user_data_dir'])){
                    
                    $command=sprintf('%s --user-data-dir=%s',$command,$options['user_data_dir']);
                }
                if($isTest&&$hasProcOpen){
                    
                    $command=sprintf('%s',$command);
                }else{
                    $command=sprintf('%s --remote-debugging-port=%s',$command,$port);
                }
            }
            if(!$hasProcOpen){
                $return['error']='页面渲染需开启proc_open或在服务器中执行命令：'.$command;
            }else{
                
                try{
                    $return['info']=\util\Tools::proc_open_exec_curl($command,$isTest?'all':true,10,$isTest?true:false);
                }catch (\Exception $ex){
                    $return['error']=$ex->getMessage();
                }
            }
        }
        return $return;
    }
    
    public function websocket($url='',$headers=array(),$options=array()){
        $this->startTime=time();
        
        $headers=is_array($headers)?$headers:array();
        $headers=\util\Funcs::array_keys_to_lower($headers);
        
        $options=is_array($options)?$options:array();
        $options['timeout']=$options['timeout']>0?$options['timeout']:$this->timeout;
        if(!empty($headers)){
            $options['headers']=is_array($options['headers'])?$options['headers']:array();
            $options['headers']=\util\Funcs::array_key_merge($options['headers'],$headers);
        }
        if(empty($url)){
            
            $url=sprintf('ws://%s/devtools/page/%s',$this->address,$this->tabId);
        }
        \util\Tools::load_websocket();
        $this->socket=new \WebSocket\Client($url,$options);
        $this->socket->setTimeout(3);
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
    
    public function mstime(){
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
    
    
    public function receiveHtml(&$locUrl,&$htmlInfo,$waitEnd,$returnInfo){
        $isStart=false;
        $isPageEnd=false;
        $pageFetchNum=0;
        $pageMstime=$this->mstime();
        
        while((time()-$this->startTime)<=$this->timeout){
            
            $data=$this->receive();
            if(!$data){
                
                break;
            }
            
            $method=$data['method'];
            
            if(!$isStart){
                if($method){
                    
                    $isStart=true;
                }
            }
            
            if($isStart){
                if($method=='Fetch.requestPaused'){
                    
                    $pageFetchNum++;
                    $dataParams=is_array($data['params'])?$data['params']:array();
                    
                    $fParams=array('requestId'=>$dataParams['requestId']);
                    if(isset(self::$passType[$dataParams['resourceType']])){
                        $fParams['errorReason']='Aborted';
                        $this->send('Fetch.failRequest',$fParams);
                    }else{
                        $this->send('Fetch.continueRequest',$fParams);
                    }
                    if($returnInfo){
                        if($dataParams['request']&&$locUrl==($dataParams['request']['url'].($dataParams['request']['urlFragment']?:''))){
                            
                            
                            $htmlInfo['code']=intval($dataParams['responseStatusCode']);
                            $htmlInfo['header']=$dataParams['request']['headers'];
                            $htmlInfo['resp_header']=is_array($dataParams['responseHeaders'])?$dataParams['responseHeaders']:array();
                            
                            if($htmlInfo['code']>=300&&$htmlInfo['code']<310){
                                foreach ($htmlInfo['resp_header'] as $k=>$v){
                                    if('location'==strtolower($v['name'])){
                                        $locUrl=$v['value'];
                                        $htmlInfo['code']=200;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }elseif($method=='Page.frameStartedLoading'){
                    
                    $isPageEnd=false;
                }elseif($method=='Page.domContentEventFired'||$method=='Page.loadEventFired'){
                    
                    $isPageEnd=true;
                    if($waitEnd){
                        
                        $pageMstime=$this->mstime();
                        $pageFetchNum=0;
                    }else{
                        
                        break;
                    }
                }
            }
            
            if($isPageEnd&&$waitEnd){
                
                $mstime=$this->mstime();
                $endMs=intval($this->options['wait_end_ms']);
                if($endMs<=0){
                    
                    $endMs=500;
                }
                $endNum=intval($this->options['wait_end_num']);
                if($endMs<=0){
                    
                    $endNum=2;
                }
                if(($mstime-$pageMstime)>$endMs){
                    
                    if($pageFetchNum<=$endNum){
                        
                        break;
                    }else{
                        
                        $pageMstime=$mstime;
                        $pageFetchNum=0;
                    }
                }
            }
        }
    }
    
    
    public function getRenderHtml($url,$headers=array(),$options=array(),$fromEncode=null,$postData=null,$returnInfo=false){
        $this->startTime=time();
        
        if(!preg_match('/^\w+\:\/\//', $url)){
            
            $url='http://'.$url;
        }
        
        if(stripos($url,'&amp;')!==false){
            
            if(!preg_match('/\&[^\;\&]+?\=/', $url)){
                
                $url=str_ireplace('&amp;', '&', $url);
            }
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
        
        $locUrl=$url;
        
        if(preg_match('/^\w+\:\/\/([\w\-]+\.){1,}[\w]+$/',$url)){
            
            $locUrl.='/';
        }
        $renderer=is_array($options['renderer'])?$options['renderer']:array();
        
        $rendererTypes=is_array($renderer['types'])?$renderer['types']:array();
        $renderer['elements']=is_array($renderer['elements'])?$renderer['elements']:array();
        $renderer['contents']=is_array($renderer['contents'])?$renderer['contents']:array();

        $htmlInfo=array('code'=>0,'ok'=>'','header'=>'','resp_header'=>'','html'=>'');
        
        $firstWaitEnd=false;
        if(!empty($rendererTypes)){
            
            static $waitEndTypes=array('wait_end','scroll_half','scroll_end','scroll_top','click','val');
            foreach ($rendererTypes as $v){
                if(in_array($v, $waitEndTypes)){
                    $firstWaitEnd=true;
                    break;
                }
            }
            if($rendererTypes[0]=='wait_end'){
                
                unset($rendererTypes[0]);
            }
        }
        $this->receiveHtml($locUrl,$htmlInfo,$firstWaitEnd,$returnInfo);
        
        static $scrollTypes=array('scroll_half','scroll_end','scroll_top');
        foreach ($rendererTypes as $rdKey=>$rdType){
            if(empty($rdType)){
                continue;
            }
            $rdElement=$renderer['elements'][$rdKey];
            $rdContent=$renderer['contents'][$rdKey];
            if($rdType=='wait_time'){
                
                $rdContent=intval($rdContent);
                if($rdContent>0){
                    sleep($rdContent);
                }
            }elseif(in_array($rdType,$scrollTypes)){
                
                if($rdType=='scroll_half'){
                    $rdContent='document.body.scrollHeight/2';
                }elseif($rdType=='scroll_end'){
                    $rdContent='document.body.scrollHeight';
                }elseif($rdType=='scroll_top'){
                    $rdContent=intval($rdContent).'px';
                }else{
                    $rdContent='';
                }
                if($rdContent){
                    $sendData=$this->send('Runtime.evaluate',array('expression'=>'window.scrollTo({top:'.$rdContent.',behavior:"smooth"});'));
                    $this->receiveHtml($locUrl,$htmlInfo,true,$returnInfo);
                }
            }elseif($rdType=='val'||$rdType=='click'){
                if($rdElement){
                    $rdElement=addslashes($rdElement);
                    $expression='';
                    if($rdType=='val'){
                        $rdContent=addslashes($rdContent);
                        $expression='(function(){'
                            .'var scjEle=document.evaluate("'.$rdElement.'",document,null,XPathResult.FIRST_ORDERED_NODE_TYPE,null).singleNodeValue;'
                            .'scjEle.value="'.$rdContent.'";'
                            .'})();';
                    }elseif($rdType=='click'){
                        $expression='(function(){'
                            .'var scjEle=document.evaluate("'.$rdElement.'",document,null,XPathResult.FIRST_ORDERED_NODE_TYPE,null).singleNodeValue;'
                            .'if(scjEle.tagName&&scjEle.tagName.toLowerCase()=="a"){scjEle.target="_self";}'
                            .'var scjForms=document.getElementsByTagName("form");if(scjForms.length>0){for(var i in scjForms){scjForms[i].target="_self";}}'
                            .'scjEle.click();})();';
                    }
                    if($expression){
                        $sendData=$this->send('Runtime.evaluate',array('expression'=>$expression));
                        $this->receiveHtml($locUrl,$htmlInfo,true,$returnInfo);
                    }
                }
            }
        }
        $html=null;
        
        $sendData=$this->send('Runtime.evaluate',array('expression'=>'document.documentElement.outerHTML'));
        $data=$this->receiveById($sendData['id'],false);
        if($data['result']&&$data['result']['result']){
            $html=$data['result']['result']['value'];
        }
        if($returnInfo){
            
            if(!is_array($htmlInfo['header'])){
                $htmlInfo['header']=array();
            }
        }
        if($html){
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
        $result=null;
        $all=array();
        while((time()-$this->startTime)<=$this->timeout){
            
            $data=$this->receive();
            if(!$data){
                
                break;
            }
            if($data['id']==$id){
                $result=$data;
                break;
            }
            if(!empty(self::$passType)){
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
        $data=$this->getHtml('/json');
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
            if(!is_array($data)){
                $data=array();
            }
            if(!is_array($data['result'])){
                $data['result']=array();
            }
            $sendData=$this->send('Target.createTarget',array('url'=>'about:blank','browserContextId'=>$data['result']['browserContextId']));
            $data=$this->receiveById($sendData['id'],false);
            if(!is_array($data)){
                $data=array();
            }
            if(!is_array($data['result'])){
                $data['result']=array();
            }
            $tabId=$data['result']['targetId'];
        }else{
            
            $tabData=$this->getHtml('/json/new');
            $tabData=empty($tabData)?array():json_decode($tabData,true);
            $tabId=$tabData['id'];
        }
        $tabId=$tabId?$tabId:'';
        $this->tabId=$tabId;
    }
    public function getHtml($uri,$timeout=null){
        $options=array('custom_request'=>'put');
        if($timeout){
            $options['timeout']=$timeout;
        }
        $data=get_html($this->addressUrl.$uri,null,$options);
        return $data;
    }
    
    public function closeTab($id){
        $this->getHtml('/json/close/'.$id,3);
    }
    
    public function getVersion(){
        $data=$this->getHtml('/json/version');
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
        if($this->serverIsLocal()){
            
            $verData=$this->getVersion();
            if(!empty($verData)&&!empty($verData['webSocketDebuggerUrl'])){
                
                $this->websocket($verData['webSocketDebuggerUrl']);
                $this->send('Browser.close');
            }
        }
    }
    
    
    public static function config_init($config){
        init_array($config);
        init_array($config['chrome']);
        $chromeSocket=null;
        if(model('admin/Config')->page_render_is_chrome(true,$config['tool'])){
            $chromeSocket=new \util\ChromeSocket($config['chrome']['host'],$config['chrome']['port'],$config['timeout'],$config['chrome']['filename'],$config['chrome']);
        }
        return $chromeSocket;
    }
    
    
    public static function config_start($config,$restart=false){
        init_array($config);
        $chromeSocket=self::config_init($config);
        $error='';
        if($chromeSocket){
            try {
                if($restart){
                    
                    $chromeSocket->closeBrowser();
                    $chromeSocket->openHost();
                }else{
                    if(!$chromeSocket->hostIsOpen()){
                        
                        $chromeSocket->openHost();
                    }
                }
            }catch (\Exception $ex){
                $error=$ex->getMessage();
            }
        }
        return $error;
    }
    
    public static function config_clear(){
        $config=model('admin/Config')->getConfig('page_render','data');
        $chromeSocket=self::config_init($config);
        if($chromeSocket){
            $chromeSocket->clearBrowser();
        }
    }
    
    public static function config_restart(){
        $config=model('admin/Config')->getConfig('page_render','data');
        $error=self::config_start($config,true);
        return $error;
    }
}

?>