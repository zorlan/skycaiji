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

namespace skycaiji\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use skycaiji\admin\model\CacheModel;

class Collect extends Command{
    protected function configure(){
        $this->setName('collect')
        	->addArgument('op', Argument::OPTIONAL, "op")
            ->addOption('cli_user', null, Option::VALUE_REQUIRED, 'cli user')
            ->addOption('task_id', null, Option::VALUE_REQUIRED, 'task:task_id')
            ->addOption('task_ids', null, Option::VALUE_REQUIRED, 'batch:task_ids')
            ->addOption('rele_id', null, Option::VALUE_REQUIRED, 'test:rele_id')
            ->addOption('logid', null, Option::VALUE_REQUIRED, 'logid')
        	->setDescription('collect task');
    }

    protected function execute(Input $input, Output $output){
    	
    	$cacheConfig=CacheModel::getInstance()->getCache('cli_cache_config','data');
    	if(is_array($cacheConfig)){
    		\think\Config::set($cacheConfig);
    	}
    	
    	\skycaiji\common\model\Config::set_url_compatible();
    	
    	$op=$input->getArgument('op');
    	
    	static $loginOps=array('task','batch','test');
    	
    	if(in_array($op, $loginOps)){
    		
    		if ($input->hasOption('cli_user')){
    			
    			$cliUser=$input->getOption('cli_user');
    			$cliUser=base64_decode($cliUser);
    			$cliUser=explode('_', $cliUser);
    			if(!empty($cliUser[0])){
    				
    				$muser=new \skycaiji\admin\model\User();
    				$user=$muser->getByUid($cliUser[0]);
    				if(!empty($user)){
    					
    				    if($cliUser[1]==$muser->generate_key($user)){
    					    $muser->setLoginSession($user);
    					}
    				}
    			}
    		}
    		$sUserlogin=session('user_login');
    		if(empty($sUserlogin)){
    			$this->error_msg('抱歉，必须传入账号信息！');
    		}
    	}
    	
    	
    	$logid='';
    	if ($input->hasOption('logid')){
    	    $logid=$input->getOption('logid');
    	    $logid=base64_decode($logid);
    	}
    	
    	$rootUrl=\think\Config::get('root_website').'/index.php?s=';
    	
    	
    	if('task'==$op){
    		
			$taskId=0;
    		if ($input->hasOption('task_id')){
    			$taskId=$input->getOption('task_id');
    			$taskId=intval($taskId);
    		}

    		$curUrl=$rootUrl.'/admin/task/collect&backstage=1&id='.urlencode($taskId);
    		$curUrl=$this->url_append_logid($curUrl,$logid);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/task/collect");
    		
    		\think\App::run()->send();
    	}elseif('batch'==$op){
    		
			$taskIds='';
    		if ($input->hasOption('task_ids')){
    			$taskIds=$input->getOption('task_ids');
    		}
    		$curUrl=$rootUrl.'/admin/task/collectBatch&backstage=1&ids='.urlencode($taskIds);
    		$curUrl=$this->url_append_logid($curUrl,$logid);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/task/collectBatch");
    		\think\App::run()->send();
    	}elseif('test'==$op){
    	    
    	    $releId=0;
    	    if ($input->hasOption('rele_id')){
    	        $releId=$input->getOption('rele_id');
    	        $releId=intval($releId);
    	    }
    	    
    	    $curUrl=$rootUrl.'/admin/release/test&backstage=1&id='.urlencode($releId);
    	    $curUrl=$this->url_append_logid($curUrl,$logid);
    	    \think\Request::create($curUrl);
    	    
    	    define('BIND_MODULE', "admin/release/test");
    	    \think\App::run()->send();
    	}elseif('backstage'==$op){
    		
    		set_time_limit(0);
			$curKey=CacheModel::getInstance()->getCache('collect_backstage_key', 'data');
			do{
			    
			    CacheModel::getInstance()->setCache('collect_backstage_time',time());
				$cacheKey=CacheModel::getInstance()->getCache('collect_backstage_key', 'data');
				if(empty($curKey)||$curKey!=$cacheKey){
					
					
					$this->error_msg('密钥错误，请在后台运行');
				}
				
    			$mconfig=new \skycaiji\admin\model\Config();
				$caijiConfig=$mconfig->getConfig('caiji','data');

				if(!$mconfig->server_is_cli(true,$caijiConfig['server'])){
    				$this->error_msg('不是cli命令行模式');
    			}
    			if(empty($caijiConfig['auto'])){
    				$this->error_msg('未开启自动采集');
    			}
    			if($caijiConfig['run']!='backstage'){
    				$this->error_msg('不是后台运行方式');
    			}
    			
    			$checkCollectWait=\skycaiji\admin\model\Config::check_collect_wait();
    			if(!$checkCollectWait){
    			    $url=$rootUrl.'/admin/api/collect&backstage=1';
    			    
    			    try{
    			        
    			        \util\Curl::get($url,null,array('timeout'=>3));
    			    }catch(\Exception $ex){
    			        
    			    }
    			}
    			
    			sleep(60);
    		}while(1==1);
    	}elseif('auto'==$op){
    		
    		$curUrl=$rootUrl.'/admin/api/collect&backstage=1';
    		$curUrl=$this->url_append_logid($curUrl,$logid);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/api/collect");
    		\think\App::run()->send();
    	}
    }
    
    protected function error_msg($msg){
    	exit($msg);
    }
    
    
    protected function url_append_logid($url,$logid){
        if(!empty($logid)){
            $url=$url.(strpos($url,'?')===false?'?':'&').'logid='.urlencode($logid);
        }
        return $url;
    }
}