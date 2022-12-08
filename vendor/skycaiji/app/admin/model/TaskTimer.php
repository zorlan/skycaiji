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

namespace skycaiji\admin\model;

class TaskTimer extends \skycaiji\common\model\BaseModel{
	
	public function addTimer($taskId,$timerData){
	    if(is_array($timerData)){
    	    $list=array();
    	    foreach ($timerData as $tName=>$tData){
    	        if(empty($tData)){
    	            
    	            $list[]=array('task_id'=>$taskId,'name'=>$tName,'data'=>'');
    	        }else{
    	            
    	            $tData=explode(',', $tData);
    	            $tData=array_filter($tData);
    	            sort($tData);
    	            foreach ($tData as $v){
    	                $list[]=array('task_id'=>$taskId,'name'=>$tName,'data'=>$v);
    	            }
    	        }
    	    }
    	    $this->where('task_id',$taskId)->delete();
    	    if($list){
    	        $this->saveAll($list);
    	    }
	    }
	}
	
	public function getTimer($taskId){
	    $timers=$this->where('task_id',$taskId)->select();
	    $data=$this->_convert_timer($timers);
	    return $data;
	}
	public function getTimers($taskIds){
	    $timers=$this->where(array('task_id'=>array('in',$taskIds)))->select();
	    $list=array();
	    foreach ($timers as $v){
	        $taskId=$v['task_id'];
	        if(!is_array($list[$taskId])){
	            $list[$taskId]=array();
	        }
	        $list[$taskId][]=$v->toArray();
	    }
	    foreach ($list as $k=>$v){
	        $list[$k]=$this->_convert_timer($v);
	    }
	    return $list;
	}
	private function _convert_timer($timers){
	    $timer=array();
	    foreach ($timers as $v){
	        $name=$v['name'];
	        $data=$v['data'];
	        if(!is_array($timer[$name])){
	            $timer[$name]=array();
	        }
	        $timer[$name][$data]=$data;
	    }
	    $isAll=true;
	    foreach ($timer as $k=>$v){
	        
	        if(is_array($v)){
	            $v=array_filter($v);
	            sort($v);
	        }
	        if(!empty($v)){
	            $isAll=false;
	        }
	        $timer[$k]=$v;
	    }
	    if($isAll){
	        $timer=array();
	    }
	    return $timer;
	}
	public function timer_info($timerData){
	    $timerInfo=[];
	    if($timerData){
	        if($timerData['month']&&is_array($timerData['month'])){
	            $timerInfo[]=implode('/', $timerData['month']).'月';
	        }
	        if($timerData['day']&&is_array($timerData['day'])){
	            $timerDay=$timerData['day'];
	            $timerWeek=array();
	            static $weekList=array('w1'=>'一','w2'=>'二','w3'=>'三','w4'=>'四','w5'=>'五','w6'=>'六','w7'=>'日');
	            foreach ($timerDay as $k=>$v){
	                if(strpos($v,'w')===0){
	                    
	                    $timerWeek[]=$weekList[$v];
	                    unset($timerDay[$k]);
	                }
	            }
	            $timerDayTips='';
	            if($timerDay){
	                $timerDayTips.=implode('/',$timerDay).'日';
	            }
	            if($timerWeek){
	                $timerDayTips.=($timerDay?'或者':'').'星期'.implode('/',$timerWeek);
	            }
	            $timerInfo[]=$timerDayTips;
	        }
	        if($timerData['hour']&&is_array($timerData['hour'])){
	            $timerInfo[]=implode('/',$timerData['hour']).'时';
	        }
	        if($timerData['minute']&&is_array($timerData['minute'])){
	            $timerInfo[]=implode('/',$timerData['minute']).'分';
	        }
	    }
	    $timerInfo=$timerInfo?implode('，', $timerInfo):'';
	    return $timerInfo;
	}
	
	
	
	public function timer_trigger($taskData,$timeNow=null){
	    $data=array('is_timer'=>false,'is_trigger'=>false);
	    if($taskData&&model('Task')->auto_is_timer($taskData['auto'])){
	        
	        $timer=$this->getTimer($taskData['id']);
	        if(empty($timer)){
	            
	            $data['is_timer']=false;
	            $data['is_trigger']=false;
	        }else{
	            $trigger=true;
	            $data['is_timer']=true;
	            $rangMinute=1;
	            $endTime=$timeNow?$timeNow:time();
	            $startTime=$endTime-60*$rangMinute;
	            $startDate=array(
	                'month'=>date('m',$startTime),
	                'day'=>date('d',$startTime),
	                'hour'=>date('H',$startTime),
	                'week'=>'w'.date('N',$startTime),
	                'minute'=>date('i',$startTime),
	            );
	            $endDate=array(
	                'month'=>date('m',$endTime),
	                'day'=>date('d',$endTime),
	                'hour'=>date('H',$endTime),
	                'week'=>'w'.date('N',$endTime),
	                'minute'=>date('i',$endTime),
	            );
	            if($startDate['month']!=$endDate['month']||$startDate['day']!=$endDate['day']||$startDate['hour']!=$endDate['hour']){
	                
	                $startDate['minute_range']=array($startDate['minute'],60);
	                $endDate['minute_range']=array(0,$endDate['minute']);
	            }else{
	                $startDate['minute_range']=array($startDate['minute'],$endDate['minute']);
	                $endDate['minute_range']=array($startDate['minute'],$endDate['minute']);
	            }
	            $dates=array($startDate,$endDate);
	            foreach ($dates as $date){
	                if(!empty($timer['month'])&&!in_array($date['month'], $timer['month'])){
	                    $trigger=false;
	                }elseif(!empty($timer['day'])&&!in_array($date['day'], $timer['day'])&&!in_array($date['week'], $timer['day'])){
	                    $trigger=false;
	                }elseif(!empty($timer['hour'])&&!in_array($date['hour'], $timer['hour'])){
	                    $trigger=false;
	                }elseif(!empty($timer['minute'])){
	                    if(!empty($date['minute_range'])){
	                        
	                        $triggerMinute=false;
	                        for($mri=$date['minute_range'][0];$mri<=$date['minute_range'][1];$mri++){
	                            if(in_array($mri, $timer['minute'])){
	                                $triggerMinute=true;
	                                break;
	                            }
	                        }
	                        if(!$triggerMinute){
	                            $trigger=false;
	                        }
	                    }else{
	                        if(!in_array($date['minute'], $timer['minute'])){
	                            $trigger=false;
	                        }
	                    }
	                }
	                if(!$trigger){
	                    break;
	                }
	            }
	            $data['is_trigger']=$trigger;
	        }
	    }
	    return $data;
	}
}

?>