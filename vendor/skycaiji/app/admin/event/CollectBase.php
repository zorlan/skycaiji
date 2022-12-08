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

namespace skycaiji\admin\event;

class CollectBase extends \skycaiji\admin\controller\CollectController {
	/*防止执行采集时出现错误模板后终止采集*/
	public function echo_error($msg = '', $url = null, $data = array(), $wait = 3, array $header = []){
		if($this->is_collecting()){
			
			$this->echo_msg($msg,'red');
			return null;
		}else{
		    $url=$url?$url:'';
		    
		    $msg=$this->_echo_msg_str($msg,'red');
		    $txt=g_sc('collect_echo_msg_txt');
		    $txt=$txt?($txt."\r\n".$msg):$msg;
		    parent::error($txt,$url,$data,$wait,$header);
		}
	}
	/*采集器的输出内容需要重写，只有正在采集时才输出内容*/
	public function echo_msg($strArgs,$color='red',$echo=true,$end_str='',$div_style=''){
	    if($this->is_collecting()){
			parent::echo_msg($strArgs,$color,$echo,$end_str,$div_style);
		}else{
		    
		    $msg=$this->_echo_msg_str($strArgs,$color,$end_str,$div_style);
		    $txt=g_sc('collect_echo_msg_txt');
		    $txt=$txt?($txt."\r\n".$msg):$msg;
		    set_g_sc('collect_echo_msg_txt',$txt);
		}
	}
	
	/*判断采集器正在执行中*/
	public function is_collecting(){
	    return \util\Param::is_collector_collecting();
	}
	
	
	public function collect_stopped($taskId,$interval=5){
	    if($this->is_collecting()){
    	    $lastData=g_sc('collect_stopped_last_data');
    	    init_array($lastData);
    	    $nowTime=time();
    	    $interval=intval($interval);
    	    $interval=$interval>0?$interval:5;
    	    if($lastData['id']!=$taskId||($nowTime-$lastData['time'])>$interval){
    	        
    	        set_g_sc('collect_stopped_last_data',array('id'=>$taskId,'time'=>$nowTime));
    	        if($taskId>0){
    	            $stop=false;
    	            if(!\skycaiji\admin\model\Collector::url_backstage_run()){
    	                
    	                $logFilename=\skycaiji\admin\model\Collector::echo_msg_filename();
    	                if(!empty($logFilename)){
    	                    
    	                    if(!file_exists($logFilename)){
    	                        $stop=true;
    	                    }
    	                }
    	            }
    	            if(!$stop){
    	                
    	                if(\skycaiji\admin\model\CacheModel::getInstance('backstage_task')->getCount($taskId)<=0){
    	                    
    	                    $stop=true;
    	                }
    	            }
    	            if($stop){
    	                $this->echo_msg('已终止运行');
    	                $this->echo_msg_end();
    	                exit();
    	            }
    	        }
    	    }
	    }
	}
	
	/*间隔执行sleep*/
	public function collect_sleep($num,$isMillisecond=false,$isHtmlInterval=false){
	    $num=intval($num);
	    if($num>0){
    	    if($this->is_collecting()){
    	        
    	        $taskId=g_sc('collect_task_id');
    	        if($taskId>0){
            	    $interval=10;
            	    if($isMillisecond){
            	        
            	        $interval=$interval*1000;
            	    }
            	    if($num>$interval){
            	        
            	        if($isHtmlInterval){
            	            
            	            $this->echo_msg(array('暂停%s%s后继续执行',$num,$isMillisecond?('毫秒（'.floatval($num/1000).'秒）'):'秒'),'black');
            	        }
            	        $zheng=floor($num/$interval);
            	        $yu=$num%$interval;
            	        for($i=1;$i<=$zheng;$i++){
            	            if($isMillisecond){
            	                usleep($interval*1000);
            	            }else{
            	                sleep($interval);
            	            }
            	            $this->collect_stopped($taskId);
            	        }
            	        if($yu>0){
            	            if($isMillisecond){
            	                usleep($yu*1000);
            	            }else{
            	                sleep($yu);
            	            }
            	            $this->collect_stopped($taskId);
            	        }
            	    }else{
            	        
            	        if($isMillisecond){
            	            usleep($num*1000);
            	        }else{
            	            sleep($num);
            	        }
            	        $this->collect_stopped($taskId);
            	    }
    	        }
    	    }
	    }
	}
	
	/*遵守robots协议*/
	public function abide_by_robots($url,$options=array()){
	    static $robotsList=array();
	    $domain=null;
	    if(preg_match('/^(\w+\:\/\/[^\/\\\]+)(.*)$/i',$url,$domain)){
	        $url='/'.ltrim($domain[2],'\/\\');
	        $domain=rtrim($domain[1],'\/\\');
	    }
	    if(empty($domain)){
	        
	        return true;
	    }
	    
	    $robots=array();
	    if(isset($robotsList[$domain])){
	        $robots=$robotsList[$domain];
	    }else{
	        $robotsTxt=get_html($domain.'/robots.txt',null,$options);
	        
	        if(!empty($robotsTxt)){
	            
	            $robotsTxt=preg_replace('/\#[^\r\n]*$/m', '', $robotsTxt);
	            
	            $rule=null;
	            if(preg_match('/\bUser-agent\s*:\s*skycaiji\s+(?P<rule>[\s\S]+?)(?=((\bUser-agent\s*\:)|\s*$))/i',$robotsTxt,$rule)){
	                
	                $rule=$rule['rule'];
	            }elseif(preg_match('/\bUser-agent\s*:\s*\*\s+(?P<rule>[\s\S]+?)(?=((\bUser-agent\s*\:)|\s*$))/i',$robotsTxt,$rule)){
	                
	                $rule=$rule['rule'];
	            }else{
	                $rule=null;
	            }
	            if(!empty($rule)){
	                
	                
	                static $replace=array('\\','/','.','*','?','~','!','@','#','%','&','(',')','[',']','{','}','+','=','|',':',',');
	                static $replaceTo=array('\\\\','\/','\.','.*','\?','\~','\!','\@','\#','\%','\&','\(','\)','\[','\]','\{','\}','\+','\=','\|','\:','\,');
	                
	                $allow=array();
	                $disallow=array();
	                
	                if(preg_match_all('/\bAllow\s*:([^\r\n]+)/i',$rule,$allow)){
	                    $allow=array_unique($allow[1]);
	                }else{
	                    $allow=array();
	                }
	                if(preg_match_all('/\bDisallow\s*:([^\r\n]+)/i',$rule,$disallow)){
	                    $disallow=array_unique($disallow[1]);
	                }else{
	                    $disallow=array();
	                }
	                
	                $robots=array(
	                    'allow'=>$allow,
	                    'disallow'=>$disallow
	                );
	                
	                foreach ($robots as $k=>$v){
	                    foreach ($v as $vk=>$vv){
	                        $vv=trim($vv);
	                        if(empty($vv)||$vv=='/'){
	                            
	                            unset($v[$vk]);
	                        }else{
	                            $vv=str_replace($replace, $replaceTo, $vv);
	                            if(strpos($vv,'\/')===0){
	                                
	                                $vv='^'.$vv;
	                            }
	                            $v[$vk]=$vv;
	                        }
	                    }
	                    $robots[$k]=$v;
	                }
	            }
	        }
	        $robotsList[$domain]=$robots;
	    }
	    if(empty($robots)){
	        
	        return true;
	    }
	    if(!empty($robots['allow'])){
	        foreach ($robots['allow'] as $v){
	            if(preg_match('/'.$v.'/', $url)){
	                
	                return true;
	                break;
	            }
	        }
	    }
	    
	    if(!empty($robots['disallow'])){
	        foreach ($robots['disallow'] as $v){
	            if(preg_match('/'.$v.'/', $url)){
	                
	                return false;
	                break;
	            }
	        }
	    }
	    return true;
	}
	
	public function retry_first_echo($retryCur,$msg,$url=null,$htmlInfo=null){
	    if($retryCur<=0){
	        $msg=$msg?:'';
	        if(is_array($htmlInfo)&&$htmlInfo['error']&&is_array($htmlInfo['error'])){
	            
	            $msg.='»Curl Error '.$htmlInfo['error']['no'].': '.$htmlInfo['error']['msg'];
	        }
	        $msg=htmlspecialchars($msg);
	        if($url){
	            $url=htmlspecialchars($url);
	            $msg='<div class="echo-msg-clear"><span class="echo-msg-lt">'.$msg.'：</span><a href="'.$url.'" target="_blank" class="echo-msg-lurl">'.$url.'</a></div>';
	        }
	        $this->echo_msg($msg);
	    }
	}
	
	public function retry_do_func(&$retryCur,$retryMax,$echoMsg,$echoError=null){
	    $do=false;
	    if($retryMax>0){
	        
	        if($retryCur<$retryMax){
	            
	            $retryCur++;
	            $this->echo_msg(array('%s第%s次',$retryCur>1?' / ':'重试：',$retryCur),'black',true,'','display:inline;');
	            $do=true;
	        }else{
	            $retryCur=0;
	            if($this->is_collecting()){
	                
	                if($echoMsg){
	                    $this->echo_msg(' / '.htmlspecialchars($echoMsg),'black',true,'','display:inline;margin-right:5px;');
	                }
	            }else{
	                
	                if($echoError){
	                    $this->echo_error(htmlspecialchars($echoError));
	                }
	            }
	            
	        }
	    }
	    return $do;
	}
}
?>