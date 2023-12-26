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

namespace util;

class SwooleSocket{
    protected $timeout=30;
    protected $host;
    protected $port;
    protected $socket;
    protected $startTime=0;
    
    public function __construct($host,$port,$timeout=30,$options=array()){
        $this->host=$this->defaultHost($host);
        $this->port=$this->defaultPort($port);
        $timeout=intval($timeout);
        $this->timeout=$timeout<=0?30:$timeout;
    }
    
    public function server(){
        $ws=new \Swoole\WebSocket\Server($this->host, $this->port);
        $ws->set(array('daemonize'=>true));
        return $ws;
    }
    
    public function websocket($headers=array(),$options=array()){
        $this->startTime=time();
        \util\Tools::cli_cache_config(true);
        
        $headers=is_array($headers)?$headers:array();
        $headers=array_change_key_case($headers,CASE_LOWER);
        
        $options=is_array($options)?$options:array();
        $options['timeout']=$options['timeout']>0?$options['timeout']:$this->timeout;
        if(!empty($headers)){
            $options['headers']=is_array($options['headers'])?$options['headers']:array();
            $options['headers']=\util\Funcs::array_key_merge($options['headers'],$headers);
        }
        
        \util\Tools::load_websocket();
        $this->socket=new \WebSocket\Client('ws://'.$this->getHostPort(),$options);
        $this->socket->setTimeout(3);
    }
    public function cmdStr(){
        $processNum=g_sc_c('caiji','process_num');
        $processNum=intval($processNum);
        $cmd='swoole --host '.$this->getHostPort();
        if($processNum>0){
            $cmd.=' --process '.$processNum;
        }
        return $cmd;
    }
    public function processNumChanged($process1,$process2){
        $process1=max(0,intval($process1));
        $process2=max(0,intval($process2));
        return $process1==$process2?false:true;
    }
    
    public function websocketError($php=null){
        $error='';
        $php=$php?:g_sc_c('caiji','swoole_php');
        $php=$php?:'php';
        $msg='请使用“swoole快捷启动”或在服务器命令行中执行：<b>'.$php.' '.htmlspecialchars(config('root_path').DIRECTORY_SEPARATOR).'skycaiji '.htmlspecialchars($this->cmdStr()).'</b>';
        try{
            $this->websocket();
            
            $data=$this->sendReceive('is_open');
            if(empty($data)||empty($data['is_open'])){
                $error='swoole服务未开启，'.$msg;
            }
        }catch (\Exception $ex){
            $exMsg=$ex->getMessage();
            $encode=mb_detect_encoding($exMsg, array('ASCII','UTF-8','GB2312','GBK','BIG5'));
            $exMsg=\util\Funcs::convert_charset($exMsg,$encode,'utf-8');
            $error='swoole错误：'.$exMsg.'<br>'.$msg;
        }
        
        return $error;
    }
    
    public function send($method,$params=array(),$id=0){
        \util\Tools::cli_cache_config(true);
        
        $this->startTime=time();
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
    
    public function sendReceive($method,$params=array(),$id=0){
        $data=$this->send($method,$params,$id);
        $data=$this->receiveById($data['id']);
        return $data;
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
    public function getHostPort(){
        return $this->host.':'.$this->port;
    }
    
    public function defaultHost($host){
        if(!preg_match('/^\w+(\.\w+)+$/',$host)){
            
            $host='';
        }
        $host=empty($host)?'127.0.0.1':$host;
        return $host;
    }
    
    public function defaultPort($port){
        $port=intval($port);
        if(in_array($port,array(80,8080,443))){
            
            $port=0;
        }
        $port=$port>0?$port:9501;
        return $port;
    }
    
    public function startWs($server,$swoolePhp,$restart=false){
        $error=$this->websocketError();
        if($error){
            
            if(model('Config')->server_is_swoole_php(true,$server,$swoolePhp)){
                
                $sskey=\util\Param::set_url_cache_key('swoole_server');
                @get_html(url('admin/index/swoole_server?key='.$sskey,null,false,true),null,array('timeout'=>3));
            }
        }else{
            
            if($restart){
                
                $this->sendReceive('restart');
            }
        }
    }
    
    public function checkPhp($server,$swoolePhp,$allowPhpEmpty=false,$checkSwoole=false){
        $return=return_result('');
        $mconfig=model('Config');
        if(empty($swoolePhp)){
            if($allowPhpEmpty){
                
                $return['success']=true;
            }else{
                $return['msg']='未设置php可执行文件';
            }
        }
        if(!$return['success']){
            if($mconfig->server_is_swoole_php(true,$server,$swoolePhp)){
                
                if(!function_exists('proc_open')){
                    $return['msg']='需开启proc_open函数';
                }else{
                    $phpResult=$mconfig->php_is_valid($swoolePhp);
                    if(empty($phpResult['success'])){
                        $return['msg']=$phpResult['msg'];
                    }else{
                        if($checkSwoole){
                            if(empty($phpResult['swoole'])){
                                $return['msg']='php未安装swoole模块';
                            }else{
                                $return['success']=true;
                                $return['msg']=$phpResult['msg_ver'];
                            }
                        }else{
                            $return['success']=true;
                            $return['msg']=$phpResult['msg_ver'];
                        }
                    }
                }
            }
        }
        if(!$return['success']&&$return['msg']){
            $return['msg']='swoole快捷启动：'.$return['msg'];
        }
        return $return;
    }
    
    
    private function killProcess($pid, $sig, $wait = 0)
    {
        \Swoole\Process::kill($pid, $sig);
        
        if ($wait) {
            $start = time();
            do {
                if (!$this->isRunning($pid)) {
                    break;
                }
                sleep(10);
            } while (time() < $start + $wait);
        }
        
        return $this->isRunning($pid);
    }
    
    
    private function isRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }
        $status=false;
        try {
            $status=\Swoole\Process::kill($pid, 0);
        } catch (\Exception $e) {
            $status=false;
        }
        return $status;
    }
    
    public function doRequest($method,$params=array()){
        init_array($postData);
        $postData = array(
            'method' => $method,
            'params'=> $params
        );
        $postData=base64_encode(json_encode($postData));
        $html=get_html('http://'.$this->getHostPort().'?_swoole_request_data='.rawurlencode($postData),null,array('timeout'=>10),'utf-8');
    }
    
    public function wsIsChange(){
        
        \util\Tools::cli_cache_config();
        \util\Tools::set_url_compatible();
        \util\Tools::close_session();
        
        $mconfig=new \skycaiji\admin\model\Config();
        $caijiConfig=$mconfig->getConfig('caiji','data');
        init_array($caijiConfig);
        $version=$mconfig->getConfig('version','data');
        
        if(version_compare(SKYCAIJI_VERSION, $version)!==0||$this->getHostPort()!=($this->defaultHost($caijiConfig['swoole_host']).':'.$this->defaultPort($caijiConfig['swoole_port']))){
            
            return true;
        }
        return false;
    }
    
    public function wsShutdown($ws){
        $ws->shutdown();
        $this->killProcess(getmypid(),SIGTERM,10);
        $this->wsExit();
    }
    
    public function wsExit(){
        throw new \Exception('[exception_exit_collect]');
    }
    
    protected $wsTimer;
    
    public function wsOnOpen($ws,$request){
        
        if(empty($this->wsTimer)){
            
            $this->wsTimer=\Swoole\Timer::tick(60000,function()use($ws){
                if($this->wsIsChange()){
                    
                    $this->wsShutdown($ws);
                }
            });
        }
    }
    public function wsOnClose($ws,$fd){
        
    }
    public function wsOnMsg($ws,$frame){
        $data=$frame->data;
        if($data){
            $data=json_decode($data,true);
        }
        $data=$this->commonWsOn($data,$ws);
        $data=json_encode($data);
        $ws->push($frame->fd, $data);
    }
    public function wsOnRequest($request,$response,$ws){
        $data=$request->get['_swoole_request_data'];
        $data=json_decode(base64_decode($data),true);
        $data=$this->commonWsOn($data,$ws,true);
        $response->end();
        $this->wsExit();
    }
    protected function commonWsOn($data,$ws,$isRequest=false){
        init_array($data);
        if($this->wsIsChange()){
            
            $this->wsShutdown($ws);
            $data['shutdown']=1;
        }else{
            $method='ws_'.($isRequest?'r':'m').'_'.$data['method'];
            if($method&&method_exists($this,$method)){
                $data['ws']=1;
                init_array($data['params']);
                $data=$this->$method($data,$ws);
            }
        }
        return $data;
    }
    
    protected function ws_m_is_open($data,$ws){
        
        $data['is_open']=1;
        return $data;
    }
    protected function ws_m_restart($data,$ws){
        $mconfig=new \skycaiji\admin\model\Config();
        $caijiConfig=$mconfig->getConfig('caiji','data');
        if($this->processNumChanged(CUR_SWOOLE_PROCESS, $caijiConfig['process_num'])){
            
            $this->wsShutdown($ws);
        }else{
            $ws->reload();
        }
        return $data;
    }
    protected function ws_m_shutdown($data,$ws){
        $this->wsShutdown($ws);
        $data['shutdown']=1;
        return $data;
    }
    protected function ws_m_php_ver($data,$ws){
        
        $data['php_ver']=phpversion();
        return $data;
    }
    protected function ws_m_collect_process($data,$ws){
        
        $urlParams=$data['params']['url_params'];
        if(!empty($urlParams)&&is_array($urlParams)){
            $urlParams='&'.http_build_query($urlParams);
        }else{
            $urlParams='';
        }
        
        $rootUrl=\think\Config::get('root_website').'/index.php?s=';
        
        $curUrl=$rootUrl.'/admin/index/collect_process'.$urlParams;
        \think\Request::create($curUrl);
        
        define('BIND_MODULE', "admin/index/collect_process");
        \think\App::run()->send();
        
        
        
        $this->wsExit();
    }
    protected function ws_m_auto_backstage($data,$ws){
        $rootUrl=\think\Config::get('root_website').'/index.php?s=';
        $curKey=\util\Param::get_auto_backstage_key();
        do{
            
            \skycaiji\admin\model\CacheModel::getInstance()->setCache('collect_backstage_time',time());
            $autoBsKey=\util\Param::get_auto_backstage_key();
            if(empty($curKey)||$curKey!=$autoBsKey){
                
                
                $data['error']='密钥错误，请在后台运行';
                return $data;
            }
            
            $mconfig=new \skycaiji\admin\model\Config();
            $caijiConfig=$mconfig->getConfig('caiji','data');
            init_array($caijiConfig);
            if(!$mconfig->server_is_swoole(true,$caijiConfig['server'])){
                $data['error']='不是swoole模式';
                return $data;
            }
            if(empty($caijiConfig['auto'])){
                $data['error']='未开启自动采集';
                return $data;
            }
            if($caijiConfig['run']!='backstage'){
                $data['error']='不是后台运行方式';
                return $data;
            }
            
            \skycaiji\admin\model\Collector::collect_run_auto($rootUrl);
            
            sleep(15);
        }while(1==1);
        
        
        
        $this->wsExit();
    }
}

?>