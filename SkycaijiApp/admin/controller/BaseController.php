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

class BaseController extends \skycaiji\common\controller\BaseController{
    protected function _initialize(){
        
        if(request()->isPost()){
            $curController=strtolower(request()->controller());
            
            if($curController!='api'){
                $this->check_usertoken();
            }
        }
    }
    
	/*输出模板：防止ajax时乱码*/
	public function fetch($template = '', $vars = [], $replace = [], $config = []){
		if(request()->isAjax()){
			$config=is_array($config)?null:$config;
			return view($template, $vars, $replace,$config);
		}else{
			return parent::fetch($template, $vars, $replace, $config);
		}
	}
	
	/*输出内容函数*/
	private static $echo_msg_head=null;
	public function echo_msg($str,$color='red',$echo=true,$end_str='',$div_style=''){
		if(defined('CLOSE_ECHO_MSG')){
			$echo=false;
		}
		if($echo){
		    $color=empty($color)?'red':$color;
		    
		    $isBackstage=input('?backstage');
		    
		    $logid=input('logid','');
		    $logFilename=\skycaiji\admin\model\Collector::echo_msg_log_filename($logid);
		    $differSeconds=input('differ_seconds/d',0);
		    
			if(!isset(self::$echo_msg_head)){
				self::$echo_msg_head=true;
				
				if(file_exists($logFilename)){
				    
				    unlink($logFilename);
				}
				if(!file_exists($logFilename)){
				    
				    write_dir_file($logFilename,'');
				}
				
				try {
				    register_shutdown_function(array($this,'_echo_msg_end'));
				}catch (\Exception $ex){}
				
				
				if(!$isBackstage){
    				
    				header('Content-type: text/html; charset=utf-8');
    				header('X-Accel-Buffering: no'); 
    				@ini_set('output_buffering','Off');
    				
    				@ob_end_clean();
    				@ob_implicit_flush(1);
    				
    				$outputSize=ini_get('output_buffering');
    				$outputSize=intval($outputSize);
    
    				if(preg_match('/\biis\b/i', $_SERVER["SERVER_SOFTWARE"])){
    					
    					if($outputSize<1024*1024*4){
    						
    						$outputSize=1024*1024*4;
    						echo '<!-- iis默认需输出4mb数据才能实时显示-->';
    					}
    				}
				}
				
				$info=array(
				    'js_time'=>time()-$differSeconds,
				    'server'=>\util\Funcs::web_server_name(),
				    'is_backstage'=>$isBackstage,
				    'server_is_cli'=>model('Config')->server_is_cli(),
				);
				$info=json_encode($info);
				
				$cssJs='<style type="text/css">'
				    .'body{padding:0 5px;font-size:14px;color:#000;line-height:20px;}p{padding:0;margin:0;}a{color:#aaa;}'
				    .'.clear{width:100%;overflow:hidden;clear:both;}.left{float:left;}'
				    .'.lurl{float:left;margin-right:3px;height:20px;max-width:70%;overflow:hidden;text-overflow:ellipsis;word-wrap:break-word;word-break:break-all;}'
				    .'</style>'
				    .'<script type="text/javascript">window.parent.window.collectorEchoMsg("start",'.$info.');</script>';
				
				$this->_echo_msg_output($cssJs, $logFilename);
				
				if(!$isBackstage){
				    
    				$allowOutput=false;
    				if($outputSize>1024*1024){
    					
    					$mobileDetect=new \util\MobileDetect();
    					if(!$mobileDetect->isMobile()){
    						
    						$allowOutput=true;
    					}
    				}else{
    					$allowOutput=true;
    				}
    				if($allowOutput){
    				    if($outputSize<1024*100){
    				        
    				        echo str_pad(' ', $outputSize>1050?($outputSize+100):1050);
    				    }
    				}
				}
			}
			
			$txt='<div style="color:'.$color.';'.$div_style.'">'.$str.'</div>'.$end_str;
			
			$this->_echo_msg_output($txt, $logFilename);
			
			if(!$isBackstage){
			    
    			if(ob_get_level()>0){
    				ob_flush();
    				flush();
    			}
			}
		}
	}
	
	public function echo_msg_exit($str,$color='red',$echo=true,$end_str='',$div_style=''){
	    $this->echo_msg($str,$color,$echo,$end_str,$div_style);
	    exit();
	}
	
	
	private static $echo_msg_end=null;
	public function _echo_msg_end(){
	    if(!isset(self::$echo_msg_end)){
	        self::$echo_msg_end=true;
	        $this->echo_msg('','',true,\skycaiji\admin\model\Collector::echo_msg_end_js(),'display:none;');
	    }
	}
	
	private function _echo_msg_output($txt,$logFilename){
	    $isBackstage=input('?backstage');
	    if(!file_exists($logFilename)){
	        
	        if(!$isBackstage){
	            
	            echo \skycaiji\admin\model\Collector::echo_msg_end_js();
	        }
	        exit();
	    }
	    if($isBackstage){
	        
	        write_dir_file($logFilename,$txt.PHP_EOL,FILE_APPEND);
	    }else{
	        
	        echo $txt;
	    }
	}
	
	
	protected function _backstage_cli_collect($cliStr){
	    if(input('?backstage')){
	        
	        $logid=input('logid','');
	        ignore_user_abort(true);
	        if(empty($logid)){
	            
	            define('CLOSE_ECHO_MSG',true);
	        }
	        if(!IS_CLI){
	            
	            if(model('admin/Config')->server_is_cli()){
	                
	                if(!empty($logid)){
	                    $cliStr.=' --logid '.base64_encode($logid);
	                }
	                cli_command_exec('collect '.$cliStr);
	                exit();
	            }
	        }
	    }else{
	        
	        ignore_user_abort(false);
	        $sUserlogin=session('user_login');
	        if(empty($sUserlogin)){
	            
	            define('CLOSE_ECHO_MSG',true);
	        }
	    }
	}
	
	public function check_usertoken(){
	    if(g_sc('usertoken')!=input('_usertoken_')){
	        $this->error(lang('usertoken_error'),'');
	    }
	}
}