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

namespace skycaiji\admin\controller;

class CollectController extends \skycaiji\admin\controller\BaseController{
    /*输出内容函数*/
    private static $echo_msg_head=null;
    public function echo_msg($strArgs,$color='red',$echo=true,$end_str='',$div_style=''){
        if(\util\Param::is_task_close_echo()){
            $echo=false;
        }
        if($echo){
            $logFilename=\skycaiji\admin\model\Collector::echo_msg_filename();
            if(!empty($logFilename)){
                if(!isset(self::$echo_msg_head)){
                    self::$echo_msg_head=true;
                    
                    if(file_exists($logFilename)){
                        
                        unlink($logFilename);
                    }
                    if(!file_exists($logFilename)){
                        
                        write_dir_file($logFilename,'');
                    }
                    
                    try {
                        register_shutdown_function(array($this,'echo_msg_end'));
                    }catch (\Exception $ex){}
                    
                    $cssJs='<!DOCTYPE html><style type="text/css">'
                        .'body{padding:0;margin:10px;font-size:13px;color:#000;line-height:16px;}p{padding:0;margin:0;}a{color:#aaa;}'
                        .'.echo-msg-clear{width:100%;overflow:hidden;clear:both;}'
                        .'.echo-msg-lt{float:left;}'
                        .'.echo-msg-lurl{float:left;margin-right:3px;height:16px;max-width:70%;overflow:hidden;text-overflow:ellipsis;word-wrap:break-word;word-break:break-all;}'
                        .'</style>';
                    $this->_echo_msg_write($cssJs, $logFilename);
                }
                $this->_echo_msg_write($this->_echo_msg_str($strArgs,$color,$end_str,$div_style), $logFilename);
            }
        }
    }
    
    protected function _echo_msg_str($strArgs,$color='red',$end_str='',$div_style=''){
        $color=empty($color)?'red':$color;
        if(is_array($strArgs)){
            
            $strArg0=is_array($strArgs[0])?'':$strArgs[0];
            $strArgs=array_slice($strArgs, 1);
            foreach ($strArgs as $k=>$v){
                $v=is_array($v)?'':htmlspecialchars($v,ENT_QUOTES);
                $strArgs[$k]=$v;
            }
            $strArgs=vsprintf($strArg0, $strArgs);
        }
        return ('<div style="color:'.$color.';'.$div_style.'">'.$strArgs.'</div>'.$end_str);
    }
    
    public function echo_msg_exit($strArgs,$color='red',$echo=true,$end_str='',$div_style=''){
        $this->echo_msg($strArgs,$color,$echo,$end_str,$div_style);
        exit();
    }
    
    public function echo_msg_return($strArgs,$color='red',$echo=true,$end_str='',$div_style=''){
        $this->echo_msg($strArgs,$color,$echo,$end_str,$div_style);
        return 0;
    }
    
    
    private static $echo_msg_end=null;
    public function echo_msg_end(){
        if(!isset(self::$echo_msg_end)){
            self::$echo_msg_end=true;
            $this->echo_msg('','',true,\skycaiji\admin\model\Collector::echo_msg_end_js(),'display:none;');
        }
    }
    
    private function _echo_msg_write($txt,$logFilename){
        if(!file_exists($logFilename)){
            
            exit();
        }
        write_dir_file($logFilename,$txt.PHP_EOL,FILE_APPEND);
    }
    
    
    public function collect_create_or_run($createFunc,$collectNum=null,$collectAuto=null,$backstageRun=false,$urlParams=null){
        $collectorKey=input('collector_key','');
        if(empty($collectorKey)){
            
            $taskIds=call_user_func($createFunc);
            $processes=null;
            if(!empty($taskIds)){
                $processes=\skycaiji\admin\model\Collector::collect_create_processes($taskIds);
            }
            if(empty($processes)||!is_array($processes)){
                $this->error('运行失败，未生成进程','');
            }
            if($backstageRun){
                
                \skycaiji\admin\model\Collector::collect_run_processes($processes['collector_key'],$collectNum,$collectAuto,$backstageRun,$urlParams);
                $this->success('','');
            }else{
                
                $this->success('','',$processes);
            }
        }else{
            
            \skycaiji\admin\model\Collector::collect_run_processes($collectorKey,$collectNum,$collectAuto,false,$urlParams);
        }
    }
    
    public function collect_tasks($taskIds,$collectNum,$collectAuto){
        if(empty($collectNum)){
            $collectNum=g_sc_c('caiji','num');
        }
        $collectNum=intval($collectNum);
        $collectAuto=empty($collectAuto)?false:true;
        
        if(!empty($taskIds)){
            if(!is_array($taskIds)){
                $taskIds=array($taskIds);
            }
            if(count($taskIds)>1){
                
                if($collectNum>0){
                    $this->echo_msg(array('总共需采集%s条数据',$collectNum),'black');
                }
                $nowTime=time();
                $isEnd=false;
                foreach ($taskIds as $taskId){
                    $return=$this->_collect_task(true,$taskId,$collectNum,$collectAuto,$nowTime);
                    if($return===-1){
                        
                        $isEnd=true;
                        break;
                    }
                }
                if(!$isEnd){
                    $this->echo_msg('所有任务执行完毕','green');
                }
            }else{
                
                $taskId=reset($taskIds);
                $this->_collect_task(false,$taskId,$collectNum,$collectAuto);
            }
        }
        
        $this->echo_msg_end();
    }
    
    
    private static $collect_task_timeout=null;
    private function _collect_task($isBatch,$taskId,&$collectNum,$collectAuto,$nowTime=null){
        if(!isset(self::$collect_task_timeout)){
            self::$collect_task_timeout=true;
            if(g_sc_c('caiji','timeout')>0){
                set_time_limit(60*g_sc_c('caiji','timeout'));
            }else{
                set_time_limit(0);
            }
        }
        \util\Funcs::close_session();
        if(empty($nowTime)){
            $nowTime=time();
        }
        $mtask=model('Task');
        $mcoll=model('Collector');
        $mrele=model('Release');
        $taskData=$mtask->getById($taskId);
        if(empty($taskData)){
            
            return $this->_collect_echo_end($isBatch, lang('task_error_empty_task'));
        }
        $taskData=$taskData->toArray();
        $taskTips='任务：'.$taskData['name'].' » ';
        if(empty($taskData['module'])){
            
            return $this->_collect_echo_end($isBatch, $taskTips.lang('task_error_null_module'));
        }
        if(!in_array($taskData['module'],config('allow_coll_modules'))){
            
            return $this->_collect_echo_end($isBatch, $taskTips.lang('coll_error_invalid_module'));
        }
        $collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
        $releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
        if(empty($collData)){
            
            return $this->_collect_echo_end($isBatch, $taskTips.lang('coll_error_empty_coll'));
        }
        if(empty($releData)){
            
            return $this->_collect_echo_end($isBatch, $taskTips.lang('rele_error_empty_rele'));
        }
        $collData=$collData->toArray();
        $releData=$releData->toArray();
        $taskData['config']=unserialize($taskData['config']?:'');
        $mtask->loadConfig($taskData);
        $taskData['caijitime']=intval($taskData['caijitime']);
        
        $acoll='\\skycaiji\\admin\\event\\C'.strtolower($collData['module']);
        $acoll=new $acoll();
        $acoll->init($collData);
        $arele='\\skycaiji\\admin\\event\\R'.strtolower($releData['module']);
        $arele=new $arele();
        $arele->init($releData);
        $GLOBALS['_sc']['real_time_release']=&$arele;
        
        $releIsApi=false;
        if($releData['module']=='api'){
            $releIsApi=true;
            if($isBatch){
                return $this->_collect_echo_end($isBatch, $taskTips.'发布方式为生成api，跳过执行',$releIsApi);
            }else{
                
                set_g_sc(['c','caiji','real_time'],0);
                
                $cacheApiData=$arele->get_cache_fields();
                if($cacheApiData!==false){
                    
                    
                    $arele->json_exit($cacheApiData);
                }
            }
        }
        
        $curTime=time();
        
        if($collectAuto){
            
            
            if(g_sc_c('caiji','interval')>0){
                $waitTime=(60*g_sc_c('caiji','interval'))-abs($curTime-$taskData['caijitime']);
                if($waitTime>0){
                    $msg=sprintf('%s再次采集需等待%s <a href="%s" target="_blank">设置运行间隔</a>',$taskTips,\skycaiji\admin\model\Config::wait_time_tips($waitTime),url('admin/task/save?show_config=1&id='.$taskData['id']));
                    return $this->_collect_echo_end($isBatch,$msg,$releIsApi);
                }
            }
            
            $timerTrigger=model('TaskTimer')->timer_trigger($taskData,$nowTime);
            if($timerTrigger['is_timer']){
                if($timerTrigger['is_trigger']){
                    
                    if(abs($curTime-$taskData['caijitime'])<600&&date('i',$curTime)===date('i',$taskData['caijitime'])){
                        return $this->_collect_echo_end($isBatch,$taskTips.'一分钟内已触发定时采集',$releIsApi);
                    }
                }else{
                    
                    return $this->_collect_echo_end($isBatch,$taskTips.'未到定时采集时间',$releIsApi);
                }
            }
            if(!$releIsApi){
                
                if(\skycaiji\admin\model\Task::collecting_status($taskData['id'])=='lock'){
                    return $this->_collect_echo_end($isBatch,$taskTips.'正在其他进程中运行',$releIsApi);
                }
            }
        }
        
        \skycaiji\admin\model\Task::collecting_lock($taskId);
        
        $mtask->strict(false)->where('id',$taskData['id'])->update(array('caijitime'=>$curTime));
        
        
        $mtask->set_backstage($taskData['id']);
        
        $this->echo_msg(array('<div style="background:#efefef;padding:5px;margin:5px 0;text-align:center;">正在执行任务：%s</div>',$taskData['name']),'black');
        
        $all_field_list=array();
        
        $collectNum=intval($collectNum);
        $taskNum=intval($taskData['config']['num']);
        if($taskNum<=0||($collectNum>0&&$taskNum>$collectNum)){
            
            $taskNum=$collectNum;
        }
        
        if($taskNum>0){
            
            while($taskNum>0){
                $fieldNum=0;
                $field_list=$acoll->collect($taskNum);
                if($field_list=='completed'){
                    
                    break;
                }elseif(is_array($field_list)&&!empty($field_list)){
                    
                    $fieldNum=count($field_list);
                    $all_field_list=array_merge($all_field_list,$field_list);
                    $taskNum-=$fieldNum;
                    $collectNum-=$fieldNum;
                }
                if($taskNum>0){
                    $this->echo_msg(array('%s采集到%s条数据，还差%s条',$taskTips,$fieldNum,$taskNum),'orange');
                }
            }
        }else{
            
            do{
                $field_list=$acoll->collect($taskNum);
                if(is_array($field_list)&&!empty($field_list)){
                    
                    $all_field_list=array_merge($all_field_list,$field_list);
                }
            }while($field_list!='completed');
        }
        
        if(empty($all_field_list)){
            $this->echo_msg(array('%s没有采集到数据',$taskTips),'orange');
        }else{
            $this->echo_msg(array('%s采集到%s条数据',$taskTips,count($all_field_list)),'green');
            if(is_empty(g_sc_c('caiji','real_time'))){
                
                $addedNum=$arele->doExport($all_field_list);
                $this->echo_msg(array('成功发布%s条数据',$addedNum),'green');
            }
        }
        
        
        $mtask->set_backstage_end($taskData['id']);
        
        \skycaiji\admin\model\Task::collecting_remove($taskData['id']);
        
        $this->echo_msg(array('<div style="background:#efefef;padding:5px;margin:5px 0;text-align:center;">%s执行完毕</div>',$taskTips),'green');
        
        if($isBatch){
            $totalNum=g_sc_c('caiji','num');
            $totalNum=intval($totalNum);
            if($totalNum>0){
                
                if($collectNum>0){
                    $this->echo_msg(array('还差%s条数据',$collectNum),'orange');
                }else{
                    
                    return -1;
                }
            }
        }
    }
    private function _collect_echo_end($isBatch,$msg,$releIsApi=false){
        if($isBatch){
            $this->echo_msg($msg,'orange');
        }else{
            if($releIsApi){
                
                if(\util\Param::is_task_api_response()){
                    
                    json(array('error'=>$msg))->send();
                }
            }else{
                $this->echo_msg_exit($msg,'orange');
            }
            exit();
        }
        return false;
    }
}