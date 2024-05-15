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
            
            $this->_collect_output($e);
        }else{
            parent::renderForConsole($output,$e);
        }
    }
    
    protected function renderHttpException(\think\exception\HttpException $e){
        if(\util\Param::is_collector_collecting()){
            
            $this->_collect_output($e);
        }else{
            return parent::renderHttpException($e);
        }
    }
    protected function convertExceptionToResponse(\Exception $e){
        if(\util\Param::is_collector_collecting()){
            
            $this->_collect_output($e);
        }else{
            return parent::convertExceptionToResponse($e);
        }
    }
    private function _collect_output($exception){
        $msg=$exception?strip_tags($exception->getMessage()):'';
        if(strpos($msg,'[exception_exit_collect]')===false){
            
            \util\Tools::collect_output($msg);
        }
    }
}
?>