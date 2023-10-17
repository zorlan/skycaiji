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
        ->addOption('url_params', null, Option::VALUE_REQUIRED, 'url params')
        ->addOption('cli_user', null, Option::VALUE_REQUIRED, 'cli user')
        ->setDescription('collect task');
    }
    
    protected function execute(Input $input, Output $output){
        
        \util\Tools::cli_cache_config();
        
        \util\Tools::set_url_compatible();
        
        $op=$input->getArgument('op');
        
        $rootUrl=\think\Config::get('root_website').'/index.php?s=';
        
        \util\Tools::close_session();
        
        
        if('cli'==$op){
            
            $curUrl=$rootUrl.'/admin/index/cli&key='.\util\Param::set_url_cache_key('cli').$this->get_url_params_str($input);
            \think\Request::create($curUrl);
            
            define('BIND_MODULE', "admin/index/cli");
            \think\App::run()->send();
        }elseif('auto_backstage'==$op){
            
            set_time_limit(0);
            $curKey=\util\Param::get_auto_backstage_key();
            do{
                
                CacheModel::getInstance()->setCache('collect_backstage_time',time());
                $autoBsKey=\util\Param::get_auto_backstage_key();
                if(empty($curKey)||$curKey!=$autoBsKey){
                    
                    
                    $this->error_msg('密钥错误，请在后台运行');
                }
                
                $mconfig=new \skycaiji\admin\model\Config();
                $caijiConfig=$mconfig->getConfig('caiji','data');
                init_array($caijiConfig);
                if(!$mconfig->server_is_cli(true,$caijiConfig['server'])){
                    $this->error_msg('不是cli命令行模式');
                }
                if(empty($caijiConfig['auto'])){
                    $this->error_msg('未开启自动采集');
                }
                if($caijiConfig['run']!='backstage'){
                    $this->error_msg('不是后台运行方式');
                }
                
                \skycaiji\admin\model\Collector::collect_run_auto($rootUrl);
                
                sleep(15);
            }while(1==1);
        }elseif('collect_process'==$op){
            $curUrl=$rootUrl.'/admin/index/collect_process'.$this->get_url_params_str($input);
            \think\Request::create($curUrl);
            
            define('BIND_MODULE', "admin/index/collect_process");
            \think\App::run()->send();
        }
    }
    
    protected function error_msg($msg){
        exit($msg);
    }
    
    protected function get_url_params_str($input){
        $urlParams='';
        if ($input->hasOption('url_params')){
            $urlParams=$input->getOption('url_params');
            if($urlParams){
                $urlParams=json_decode(base64_decode($urlParams),true);
            }
            if(!empty($urlParams)&&is_array($urlParams)){
                $urlParams='&'.http_build_query($urlParams);
            }else{
                $urlParams='';
            }
        }
        return $urlParams;
    }
}
