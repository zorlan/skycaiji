<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
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
    	if ($input->hasOption('cli_user')){
			
    		$cliUser=$input->getOption('cli_user');
    		$cliUser=base64_decode($cliUser);
    		$cliUser=explode('_', $cliUser);
    		$muser=new \skycaiji\admin\model\User();
    		$user=$muser->where('username',$cliUser[0])->find();
    		if(!empty($user)){
    			$user['username']=strtolower($user['username']);
    			
    			if($user['username']==$cliUser[0]&&$cliUser[1]==md5($user['username'].$user['password'])){
    				session('user_id',$user['uid']);
    			}
    		}
    	}
    	$op=$input->getArgument('op');
    	
    	if('task'==$op){
    		
			$taskId=0;
    		if ($input->hasOption('task_id')){
    			$taskId=$input->getOption('task_id');
    			$taskId=intval($taskId);
    		}

    		$curUrl=\think\Config::get('root_website').'/admin/task/collect?backstage=1&id='.urlencode($taskId);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/task/collect");
    		
    	}elseif('auto'==$op){
    		
    		$curUrl=\think\Config::get('root_website').'/admin/api/collect?backstage=1';
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/api/collect");
    	}elseif('batch'==$op){
    		
			$taskIds='';
    		if ($input->hasOption('task_ids')){
    			$taskIds=$input->getOption('task_ids');
    		}
    		$curUrl=\think\Config::get('root_website').'/admin/task/collectBatch?backstage=1&ids='.urlencode($taskIds);
    		\think\Request::create($curUrl);
    		
    		define('BIND_MODULE', "admin/task/collectBatch");
    	}
    	
    	\think\App::run()->send();
    }
}