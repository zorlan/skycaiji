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

/*日志驱动*/
namespace util;
use think\log\driver\File;
class Log extends File{
	public function save(array $log = [], $append = false){
		
		static $passList=array(
			'未定义','Undefined',
			'A session had already been started','DOMDocument::loadHTML',
			'MySQL server has gone away',"Error reading result set's header",
			'The /e modifier is deprecated'
		);
		
		foreach ($log as $type => $val) {
            foreach ($val as $key=>$msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                foreach ($passList as $passStr){
                	if(stripos($msg, $passStr)!==false){
                		
                		unset($val[$key]);
                		break;
                	}
                }
            }
            $log[$type]=$val;
		}
		
		parent::save($log,$append);
    }
}

?>