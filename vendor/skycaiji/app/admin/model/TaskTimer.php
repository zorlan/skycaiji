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
    	    $this->setCache($taskId, null);
    	    if($list){
    	        $this->saveAll($list);
    	    }
	    }
	}
	
	public function getTimer($taskId,$toInt=false){
	    $timers=$this->getCache($taskId);
	    if(!is_array($timers)){
	        
	        $timers=$this->db()->where('task_id',$taskId)->select();
	        $timers=$timers?collection($timers)->toArray():array();
	        $this->setCache($taskId,$timers);
	    }
	    $data=$this->_convert_timer($timers,$toInt);
	    return $data;
	}
	
	public function setCache($taskId,$data){
	    $key='tasktimer_'.$taskId;
	    $mcache=CacheModel::getInstance('');
	    if(!isset($data)||$data===null){
	        
	        $mcache->deleteCache($key);
	    }else{
	        $mcache->setCache($key,$data);
	    }
	}
	public function getCache($taskId){
	    $key='tasktimer_'.$taskId;
	    $cache=CacheModel::getInstance('')->getCache($key);
	    if($cache&&abs(time()-$cache['dateline'])<3600*24){
	        
	        $cache=$cache['data'];
	    }else{
	        $cache=null;
	    }
	    return $cache;
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
	private function _convert_timer($timers,$toInt=false){
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
	        if($toInt){
	            
	            if($k=='day'){
	                foreach ($v as $vk=>$vv){
	                    if(strpos($vv, 'w')!==0){
	                        
	                        $vv=intval($vv);
	                    }
	                    $v[$vk]=$vv;
	                }
	            }else{
	                $v=array_map('intval', $v);
	            }
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
	        
	        $timer=$this->getTimer($taskData['id'],true);
	        if(empty($timer)){
	            
	            $data['is_timer']=false;
	            $data['is_trigger']=false;
	        }else{
	            $trigger=true;
	            $data['is_timer']=true;
	            $rangMinute=1;
	            $timeNow=$timeNow?$timeNow:time();
	            $endTime=$timeNow;
	            $startTime=$endTime-60*$rangMinute;
	            $startDate=array(
	                'month'=>date('m',$startTime),
	                'day'=>date('d',$startTime),
	                'hour'=>date('H',$startTime),
	                'minute'=>date('i',$startTime),
	            );
	            $startDate=array_map('intval', $startDate);
	            $startDate['week']='w'.date('N',$startTime);
	            $endDate=array(
	                'month'=>date('m',$endTime),
	                'day'=>date('d',$endTime),
	                'hour'=>date('H',$endTime),
	                'minute'=>date('i',$endTime),
	            );
	            $endDate=array_map('intval', $endDate);
	            $endDate['week']='w'.date('N',$endTime);
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
	            if(!$trigger&&$taskData['caijitime']>0&&$taskData['config']&&$taskData['config']['other']&&$taskData['config']['other']['timer_again']){
    	            
	                $timerWeeks=array();
	                if($timer['day']){
	                    foreach($timer['day'] as $k=>$v){
	                        if(strpos($v,'w')===0){
	                            
	                            $timerWeeks[]=$v;
	                            unset($timer['day'][$k]);
	                        }
	                    }
	                }
	                $timerWeeks=array_values($timerWeeks);
	                $timer['day']=array_values($timer['day']);
	                
	                $nowDate=array(
	                    'year'=>date('Y',$timeNow),
    	                'month'=>date('m',$timeNow),
    	                'day'=>date('d',$timeNow),
    	                'hour'=>date('H',$timeNow),
    	                'minute'=>date('i',$timeNow),
	                );
	                $nowDate=array_map('intval', $nowDate);
    	            $lastDate=array();
    	            $lastDateKeys=array('month','day','hour','minute');
    	            for($i=0;$i<count($lastDateKeys);$i++){
    	                $ldKey=$lastDateKeys[$i];
    	                $isLtNow=false;
    	                
    	                if($timer[$ldKey]){
    	                    foreach($timer[$ldKey] as $tmv){
    	                        $isLtNow=$tmv;
    	                        if($tmv>=$nowDate[$ldKey]){
    	                            
    	                            $isLtNow=false;
    	                            break;
    	                        }
    	                    }
    	                }
    	                $lastDate[$ldKey]=$isLtNow?$isLtNow:$nowDate[$ldKey];
    	                if($ldKey=='month'&&!empty($timerWeeks)){
    	                    
    	                    $timerDays=date('t',strtotime($nowDate['year'].'-'.$lastDate['month']));
    	                    $timerDays=intval($timerDays);
    	                    for($tdi=1;$tdi<=$timerDays;$tdi++){
    	                        if(!in_array($tdi,$timer['day'])&&in_array('w'.date('N',strtotime($nowDate['year'].'-'.$nowDate['month'].'-'.$tdi)),$timerWeeks)){
    	                            
    	                            $timer['day'][]=$tdi;
    	                        }
    	                    }
    	                    sort($timer['day']);
    	                }
    	                $ldKeys=array_slice($lastDateKeys,$i+1);
    	                if($isLtNow){
    	                    if($ldKeys){
    	                        $maxDef=array('month'=>12,'day'=>31,'hour'=>23,'minute'=>59);
    	                        foreach ($ldKeys as $ldk){
    	                            
    	                            $lastDate[$ldk]=$timer[$ldk]?max($timer[$ldk]):$maxDef[$ldk];
    	                        }
    	                    }
    	                    break;
    	                }else{
    	                    if($ldKeys){
    	                        foreach ($ldKeys as $ldk){
    	                            
    	                            $lastDate[$ldk]=$nowDate[$ldk];
    	                        }
    	                    }
    	                }
    	            }
    	            
    	            
    	            $maxDay=date('t',strtotime($nowDate['year'].'-'.$lastDate['month']));
    	            if($lastDate['day']>$maxDay){
    	                $lastDate['day']=$maxDay;
    	            }
    	            
    	            $lastDate=sprintf('%d-%d-%d %d:%d',$nowDate['year'],$lastDate['month'],$lastDate['day'],$lastDate['hour'],$lastDate['minute']);
    	            
    	            if($taskData['caijitime']<strtotime($lastDate)){
    	                 
    	                $trigger=true;
    	            }
	            }
	            $data['is_trigger']=$trigger;
	        }
	    }
	    return $data;
	}
}

?>