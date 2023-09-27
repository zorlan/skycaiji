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


class UnmaxPost extends \think\Request{
    public $post;
    
    public static function val($key = '', $default = null, $filter = ''){
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }
        $method='post';
        $request=self::instance();
        if (isset($has)) {
            return $request->has($key, $method, $default);
        } else {
            return $request->$method($key, $default, $filter);
        }
    }
    
    
    public static function init_post_data($postName){
        $postData=input('?'.$postName)?input($postName,'','trim'):'';
        if(!is_empty($postData,true)){
            $postData=explode('&',$postData);
            $data=array();
            foreach ($postData as $v){
                $v=explode('=',$v,2);
                $key=isset($v[0])?trim(urldecode($v[0])):'';
                $val=isset($v[1])?$v[1]:'';
                parse_str('data='.$val,$val);
                $val=isset($val['data'])?$val['data']:'';
                
                $isAutoArr=false;
                if(preg_match('/\[\]$/', $key)){
                    $isAutoArr=true;
                }
                
                $key=str_replace('[', '|', $key);
                $key=str_replace(']', '|', $key);
                $key=trim($key,'|');
                $key=preg_replace('/[\|]+/', '|', $key);
                $key=explode('|', $key);
                $keyData=&$data;
                foreach ($key as $ki=>$kk){
                    $isArr=false;
                    if(($ki+1)<count($key)){
                        
                        $isArr=true;
                    }else if($isAutoArr){
                        
                        $isArr=true;
                    }
                    if($isArr){
                        if(!isset($keyData[$kk])||!is_array($keyData[$kk])){
                            
                            $keyData[$kk]=array();
                        }
                    }else{
                        if(!isset($keyData[$kk])){
                            $keyData[$kk]='';
                        }
                    }
                    $keyData=&$keyData[$kk];
                }
                if($isAutoArr){
                    
                    $keyData[]=$val;
                }else{
                    
                    $keyData=$val;
                }
            }
            $request=self::instance();
            $request->post=$data;
        }
    }
}
?>