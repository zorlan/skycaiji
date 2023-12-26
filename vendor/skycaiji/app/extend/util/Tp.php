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


namespace util;
class Tp{
    
    public static function filter_log_msg($msg){
        static $passList=array(
            '未定义','Undefined array key','Undefined variable','Undefined index',
            'A session had already been started','DOMDocument::loadHTML',
            'MySQL server has gone away',"Error reading result set's header",
            'The /e modifier is deprecated',
            'Invalid argument supplied for foreach',
            'CURLOPT_FOLLOWLOCATION',
            'open_basedir restriction in effect',
            ']unlink(',']rmdir(',
            '[exception_exit_collect]',
        );
        static $passListLower=null;
        if(!isset($passListLower)){
            $passListLower=$passList;
            if(IS_CLI){
                $passListLower[]='session_start()';
            }
            $passListLower=array_map('strtolower', $passListLower);
        }
        if($msg){
            $msg=strtolower($msg);
            foreach ($passListLower as $passStr){
                
                if($passStr&&strpos($msg, $passStr)!==false){
                    
                    $msg='';
                    break;
                }
            }
        }
        return $msg?false:true;
    }
}
?>