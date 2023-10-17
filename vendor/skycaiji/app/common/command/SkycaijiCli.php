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

namespace skycaiji\common\command;

class SkycaijiCli{
    protected $rootPath;
    protected $swooleSocket;
    
    public function __construct($rootPath){
        $this->rootPath=$rootPath;
    }
    
    public function start(){
        global $argv;
        if($argv&&$argv[1]==='swoole'){
            $this->start_swoole();
        }else{
            $this->start_cli();
        }
    }
    
    public function start_swoole(){
        $host=$this->getopt('--host');
        $host=explode(':',$host);
        if(!preg_match('/^\w+(\.\w+)+$/',$host[0])){
            
            $host[0]='';
        }
        $host[0]=empty($host[0])?'127.0.0.1':$host[0];
        $host[1]=intval($host[1]);
        if(in_array($host[1],array(80,8080,443))){
            
            $host[1]=0;
        }
        $host[1]=$host[1]>0?$host[1]:9501;
        
        $this->swooleSocket=null;
        $ws=new \Swoole\WebSocket\Server($host[0], $host[1]);
        
        $processNum=$this->getopt('--process');
        $processNum=max(0,intval($processNum));
        $ws->set(array(
            'daemonize'=>true,
            'worker_num' => 5+$processNum,
        ));
        define('CUR_SWOOLE_PROCESS', $processNum);
        $ws->on('WorkerStart',function($ws,$worker_id){
            
            define('SKYCAIJI_PATH', $this->rootPath.DIRECTORY_SEPARATOR);
            
            define('VENDOR_PATH', SKYCAIJI_PATH.'vendor'.DIRECTORY_SEPARATOR);
            define('APP_PATH', VENDOR_PATH.'skycaiji'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR);
            define('RUNTIME_PATH', SKYCAIJI_PATH.'runtime'.DIRECTORY_SEPARATOR);
            define('APP_NAMESPACE', 'skycaiji');
            
            require VENDOR_PATH.'skycaiji'.DIRECTORY_SEPARATOR.'tp'.DIRECTORY_SEPARATOR.'base.php';
            \think\App::initCommon();
            
            $mconfig=new \skycaiji\common\model\Config();
            $caijiConfig=$mconfig->getConfig('caiji','data');
            
            $this->swooleSocket=new \util\SwooleSocket($caijiConfig['swoole_host'],$caijiConfig['swoole_port']);
        });
        $ws->on('Open',function($ws,$request){
            $this->swooleSocket->wsOnOpen($ws,$request);
        });
        $ws->on('Message',function($ws,$frame){
            $this->swooleSocket->wsOnMsg($ws,$frame);
        });
        $ws->on('Request',function($request,$response) {
            global $ws;
            $this->swooleSocket->wsOnRequest($request,$response,$ws);
        });
        $ws->on('Close',function($ws,$fd){
            $this->swooleSocket->wsOnClose($ws,$fd);
        });
        
        echo "ok\r\n";
        
        $ws->start();
    }
    
    public function start_cli(){
        define('SKYCAIJI_PATH', $this->rootPath.DIRECTORY_SEPARATOR);
        
        define('VENDOR_PATH', SKYCAIJI_PATH.'vendor'.DIRECTORY_SEPARATOR);
        define('APP_PATH', VENDOR_PATH.'skycaiji'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR);
        define('RUNTIME_PATH', SKYCAIJI_PATH.'runtime'.DIRECTORY_SEPARATOR);
        define('APP_NAMESPACE', 'skycaiji');
        
        require VENDOR_PATH.'skycaiji'.DIRECTORY_SEPARATOR.'tp'.DIRECTORY_SEPARATOR.'console.php';
    }
    
    protected function getopt($key){
        global $argv;
        $val='';
        $hasKey=false;
        foreach ($argv as $k=>$v){
            if($hasKey){
                if(strpos($v,'-')!==0){
                    
                    $val=$v;
                }
                break;
            }
            if($v===$key){
                $hasKey=true;
            }
        }
        return $val;
    }
}

?>