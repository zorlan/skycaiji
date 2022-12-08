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

namespace skycaiji\common\exception;

class CommonHandle extends \think\exception\Handle {
    
    public function renderForConsole(\think\console\Output $output, \Exception $e)
    {
        if(\util\Param::is_collector_collecting()&&IS_CLI){
            
            controller('admin/CollectController')->echo_msg_exit(strip_tags($e->getMessage()));
        }else{
            parent::renderForConsole($output,$e);
        }
    }
    
    protected function renderHttpException(\think\exception\HttpException $e){
        if(\util\Param::is_collector_collecting()){
            
            controller('admin/CollectController')->echo_msg_exit(strip_tags($e->getMessage()));
        }else{
            return parent::renderHttpException($e);
        }
    }
    protected function convertExceptionToResponse(\Exception $exception){
        if(\util\Param::is_collector_collecting()){
            
            controller('admin/CollectController')->echo_msg_exit(strip_tags($exception->getMessage()));
        }else{
            return parent::convertExceptionToResponse($exception);
        }
    }
}
?>