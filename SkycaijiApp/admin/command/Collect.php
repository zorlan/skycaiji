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
            ->addOption('task_id', null, Option::VALUE_REQUIRED, 'collect:task_id')
            ->addOption('task_ids', null, Option::VALUE_REQUIRED, 'batch:task_ids')
        	->setDescription('collect task');
    }

    protected function execute(Input $input, Output $output){
    	
    	$cacheConfig=CacheModel::getInstance()->getCache('cli_cache_config','data');
    	if(is_array($cacheConfig)){
    		\think\Config::set($cacheConfig);
    	}
    	$op=$input->getArgument('op');
    	
    	static $loginOps=array('task','batch');
    	
    	if(in_array($op, $loginOps)){
    		
    		if ($input->hasOption('cli_user')){
    			
    			$cliUser=$input->getOption('cli_user');
    			$cliUser=base64_decode($cliUser);
    			$cliUser=explode('_', $cliUser);
    			if(!empty($cliUser[0])){
    				
    				$muser=new \skycaiji\admin\model\User();
    				$user=$muser->where('username',$cliUser[0])->find();
    				if(!empty($user)){
    					$user['username']=strtolower($user['username']);
    					
    					if($user['username']==$cliUser[0]&&$cliUser[1]==md5($user['username'].$user['password'])){
    						session('user_id',$user['uid']);
    					}
    				}
    			}
    		}

    		if(!session('?user_id')){
    			$this->error_msg('抱歉，必须传入账号信息！');
    		}
    	}
    	
    	$rootUrl=\think\Config::get('root_website').'/index.php?s=';
    	
    	if('task'==$op){
    		
			$taskId=0;
    		if ($input->hasOption('task_id')){
    			$taskId=$input->getOption('task_id');
    			$taskId=intval($taskId);
    		}

    		$curUrl=$rootUrl.'/admin/task/collect&backstage=1&id='.urlencode($taskId);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/task/collect");
    		
    		\think\App::run()->send();
    	}elseif('batch'==$op){
    		
			$taskIds='';
    		if ($input->hasOption('task_ids')){
    			$taskIds=$input->getOption('task_ids');
    		}
    		$curUrl=$rootUrl.'/admin/task/collectBatch&backstage=1&ids='.urlencode($taskIds);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/task/collectBatch");
    		\think\App::run()->send();
    	}elseif('backstage'==$op){
    		
    		set_time_limit(0);
			$curKey=CacheModel::getInstance()->getCache('admin_index_backstage_key', 'data');
    		do{
    			
				$cacheKey=CacheModel::getInstance()->getCache('admin_index_backstage_key', 'data');
				if(empty($curKey)||$curKey!=$cacheKey){
					
					
					$this->error_msg('密钥错误，请在后台运行');
				}
				
    			$mconfig=new \skycaiji\admin\model\Config();
				$caijiConfig=$mconfig->getConfig('caiji','data');

    			if($caijiConfig['server']!='cli'){
    				$this->error_msg('不是cli命令行模式');
    			}
    			if(empty($caijiConfig['auto'])){
    				$this->error_msg('未开启自动采集');
    			}
    			if($caijiConfig['run']!='backstage'){
    				$this->error_msg('不是后台运行方式');
    			}
    			
    			$url=$rootUrl.'/admin/api/collect&backstage=1';

    			try{
    				
    				\util\Curl::get($url,null,array('timeout'=>3));
    			}catch(\Exception $ex){
    				
    			}
    			
    			$waitTime=$caijiConfig['interval']*60;
    			$waitTime=$waitTime>0?$waitTime:60;
    			sleep($waitTime);
    			
    		}while(1==1);
    	}elseif('auto'==$op){
    		
    		$curUrl=$rootUrl.'/admin/api/collect&backstage=1';
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/api/collect");
    		\think\App::run()->send();
    	}
    }
    
    protected function error_msg($msg){
    	exit($msg);
    }
}