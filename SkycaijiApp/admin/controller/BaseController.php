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

namespace skycaiji\admin\controller;

class BaseController extends \skycaiji\common\controller\BaseController{
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
	public function echo_msg($str,$color='red',$echo=true,$end_str=''){
		if(defined('CLOSE_ECHO_MSG')){
			$echo=false;
		}
		if($echo){
			if(!isset(self::$echo_msg_head)){
				self::$echo_msg_head=true;
				header('Content-type: text/html; charset=utf-8');
				header('X-Accel-Buffering: no'); 
				@ini_set('output_buffering','Off');
				
				ob_end_clean();
				@ob_implicit_flush(1);
				
				$outputSize=ini_get('output_buffering');
				$outputSize=intval($outputSize);

				if(preg_match('/\biis\b/i', $_SERVER["SERVER_SOFTWARE"])){
					
					if($outputSize<1024*1024*4){
						
						$outputSize=1024*1024*4;
						echo '<!-- iis默认需输出4mb数据才能实时显示-->';
					}
				}
				echo '<style type="text/css">body{padding:0 5px;font-size:14px;color:#000;}p{padding:0;margin:0;}a{color:#aaa;}</style>';
				
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
					echo str_pad(' ', $outputSize>1050?($outputSize+100):1050);
				}
			}
			echo '<p style="color:'.$color.';">'.$str.'</p>'.$end_str;
			if(ob_get_level()>0){
				ob_flush();
				flush();
			}
		}
	}
	/*保留旧的入口*/
	public function indexAction(){
		if(strtolower(request()->controller())=='base'){
			
    		$this->redirect('Admin/Backstage/index');
		}
	}
}