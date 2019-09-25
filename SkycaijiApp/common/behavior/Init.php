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

namespace skycaiji\common\behavior;

use think\Request;
class Init{
	
	public function run(){
		if(!isset($GLOBALS['_sc'])){
			
			$GLOBALS['_sc']=array();
		}
		if(session_status()!==2){
			session_start();
		}
		if(isset($_GET['m'])&&isset($_GET['c'])&&isset($_GET['a'])){
			
			$tourl=config('root_website');
			if(stripos($tourl, '/index.php')===false){
				$tourl.='/index.php';
			}
			$tourl.="?s=/{$_GET['m']}/{$_GET['c']}/{$_GET['a']}";
			unset($_GET['m']);
			unset($_GET['c']);
			unset($_GET['a']);
			if(!empty($_GET)){
				foreach ($_GET as $k=>$v){
					$tourl.='&'.$k.'='.rawurlencode($v);
				}
			}
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: '.$tourl);
			exit();
		}
		load_data_config();
		
		if (preg_match ( '/MSIE\s*([\.\d]+)/i', $_SERVER ['HTTP_USER_AGENT'], $browserIe )) {
			$browserIe = doubleval($browserIe[1]);
			if($browserIe<9){
				
				$GLOBALS['_sc']['browser_is_old']=true;
			}
		}
		if(stripos(Request::instance()->root(),'/index.php')!==false&&isset($_GET['s'])){
			
			Request::instance()->root(config('root_url').'/index.php?s=');
			define('URL_IS_COMPATIBLE', true);
		}
	}
}

?>