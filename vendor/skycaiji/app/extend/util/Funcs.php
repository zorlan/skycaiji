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
class Funcs{
    
	public static function web_server_name(){
	    $webServer=$_SERVER["SERVER_SOFTWARE"];
	    foreach (array('apache','nginx','iis') as $v){
	        if(preg_match('/\b'.$v.'\b/i',$webServer)){
	            $webServer=$v;
	            break;
	        }
	    }
	    return $webServer;
	}
	
	
	public static function array_filter_keep0($list){
	    if(is_array($list)){
	        foreach ($list as $k=>$v){
	            if(empty($v)&&$v!==0&&$v!=='0'){
	                
	                unset($list[$k]);
	            }
	        }
	    }else{
	        $list=array();
	    }
	    return $list;
	}
	
	
	public static function strip_phpcode_comment($code){
	    if($code){
	        $tokens=token_get_all($code);
	        $newCode='';
	        foreach ($tokens as $key=>$token){
	            if (!is_array($token)){
	                $newCode.=$token;
	            }else{
	                
	                if($token[0]==T_COMMENT||$token[0]==T_DOC_COMMENT){
	                    if(preg_match('/[\r\n]+$/', $token[1])){
	                        
	                        $newCode.=PHP_EOL;
	                    }
	                }else{
	                    $newCode.=$token[1];
	                }
	            }
	        }
	        $code=$newCode;
	    }
	    return $code;
	}
	
	
	public static function filter_key_val_list(&$arr1,&$arr2){
	    if(!is_array($arr1)){
	        $arr1=array();
	    }
	    if(!is_array($arr2)){
	        $arr2=array();
	    }
	    
	    foreach ($arr1 as $k=>$v){
	        if(empty($v)){
	            
	            unset($arr1[$k]);
	            unset($arr2[$k]);
	        }
	    }
	    $arr1=array_values($arr1);
	    $arr2=array_values($arr2);
	}
	
	
	public static function filter_key_val_list3(&$arr1,&$arr2,&$arr3){
	    if(!is_array($arr1)){
	        $arr1=array();
	    }
	    if(!is_array($arr2)){
	        $arr2=array();
	    }
	    if(!is_array($arr3)){
	        $arr3=array();
	    }
	    foreach ($arr1 as $k=>$v){
	        if(empty($v)){
	            
	            unset($arr1[$k]);
	            unset($arr2[$k]);
	            unset($arr3[$k]);
	        }
	    }
	    $arr1=array_values($arr1);
	    $arr2=array_values($arr2);
	    $arr3=array_values($arr3);
	}
	
	public static function array_array_map($callback, $arr1, array $_ = null){
	    if(is_array($arr1)){
	        $arr=array();
	        foreach ($arr1 as $k=>$v){
	            if(!is_array($v)){
	                $arr[$k]=call_user_func($callback, $v);
	            }else{
	                $arr[$k]=self::array_array_map($callback,$v,$_);
	            }
	        }
	    }
	    return $arr;
	}
	
	public static function array_implode($glue, $pieces){
	    $str='';
	    foreach ($pieces as $v){
	        if(is_array($v)){
	            $str.=self::array_implode($glue,$v);
	        }else{
	            $str.=$glue.$v;
	        }
	    }
	    return $str;
	}
	
	public static function array_key_merge($arr1,$arr2){
	    if(!is_array($arr1)){
	        $arr1=array();
	    }
	    if(!is_array($arr2)){
	        $arr2=array();
	    }
	    foreach ($arr2 as $k=>$v){
	        $arr1[$k]=$v;
	    }
	    return $arr1;
	}
	
	
	public static function array_val_in_keys(&$arr,$lowerKeys,$deleteKeys=false){
	    $val=null;
	    if(is_array($lowerKeys)&&is_array($arr)){
	        foreach ($arr as $k=>$v){
	            if(in_array(strtolower($k), $lowerKeys)){
	                $val=$v;
	                if($deleteKeys){
	                    
	                    unset($arr[$k]);
	                }
	            }
	        }
	    }
	    return $val;
	}
	
	
	public static function convert_html2json($html,$returnStr=false){
	    static $jsonpRegExp='/^(\s*[\$\w\-]+\s*[\{\(])+(?P<json>[\s\S]+)(?P<end>[\}\]])\s*\)\s*[\;]{0,1}/i';
	    $html=isset($html)?$html:'';
        $json=json_decode($html,true);
        if(!empty($json)){
            
            if($returnStr){
                
                $json=$html;
            }
        }elseif(preg_match($jsonpRegExp,$html,$json)){
            
            $json=trim($json['json']).$json['end'];
            if(!$returnStr){
                
                $json=json_decode($json,true);
            }
        }
	    
	    return $json?$json:null;
	}
	
	
	public static function clear_dir($path,$passFiles=null){
	    if(empty($path)){
	        return;
	    }
	    $path=realpath($path);
	    if(empty($path)){
	        return;
	    }
	    if(!empty($passFiles)){
	        $passFiles=is_array($passFiles)?array_map('realpath',$passFiles):array();
	    }
	    
	    $fileList=scandir($path);
	    foreach( $fileList as $file ){
	        $fileName=realpath($path.'/'.$file);
	        if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
	            if(empty($passFiles)||!in_array($fileName, $passFiles)){
	                
	                self::clear_dir($fileName,$passFiles);
	                rmdir($fileName);
	            }
	        }elseif(is_file($fileName)){
	            if(empty($passFiles)||!in_array($fileName, $passFiles)){
	                
	                unlink($fileName);
	            }
	        }
	    }
	    clearstatcache();
	}
	
	public static function array_get(&$arr,$keys){
	    $val=null;
	    if(is_array($arr)){
    	    if(is_array($keys)&&count($keys)>1){
    	        
    	        $curArr=&$arr;
    	        $endKey=array_slice($keys,-1,1);
    	        if(!empty($endKey)){
    	            
    	            $endKey=$endKey[0];
        	        $keys=array_slice($keys,0,-1);
        	        
        	        $isNotArr=false;
        	        
        	        foreach($keys as $key){
        	            if(!is_array($curArr[$key])){
        	                
        	                $isNotArr=true;
        	                break;
        	            }
        	            $curArr=&$curArr[$key];
        	        }
        	        
        	        if(!$isNotArr){
        	            
        	            $val=$curArr[$endKey];
        	        }
    	        }
    	    }else{
    	        if(is_array($keys)){
    	            
    	            $keys=array_values($keys);
    	            $keys=$keys[0];
    	        }
    	        $val=$arr[$keys];
    	    }
	    }
	    return $val;
	}
	
	
	
	public static function array_set(&$arr,$keys,$val){
	    if(is_array($keys)){
	        $curArr=&$arr;
	        $endKey=array_slice($keys,-1,1);
	        if(!empty($endKey)){
	            
	            $endKey=$endKey[0];
	            $keys=array_slice($keys,0,-1);
	            foreach($keys as $key){
	                if(!is_array($curArr[$key])){
	                    $curArr[$key]=array();
	                }
	                $curArr=&$curArr[$key];
	            }
	            
	            if(is_null($val)){
	                
	                unset($curArr[$endKey]);
	            }else{
	                $curArr[$endKey]=$val;
	            }
	        }
	    }else{
	        
	        if(is_null($val)){
	            
	            unset($arr[$keys]);
	        }else{
	            $arr[$keys]=$val;
	        }
	    }
	}
	
	
	public static function uniqid($prefix=null){
	    $prefix=$prefix?$prefix:'';
	    $key=$prefix.uniqid().microtime().rand(1,1000000);
	    $key=md5($key);
	    return $key;
	}
	
	
	public static function close_session(){
	    static $closed=null;
	    if(!isset($closed)){
	        $closed=true;
	        if(session_status()!==PHP_SESSION_ACTIVE){
	            session_start();
	        }
	        session_write_close();
	    }
	}
	
	public static function url_params_charset($url,$params,$charset=null){
	    
	    if($params&&is_array($params)){
	        if(!empty($charset)&&!in_array(strtolower($charset),array('auto','utf-8','utf8'))){
	            foreach ($params as $k=>$v){
	                $params[$k]=iconv('utf-8',$charset.'//IGNORE',$v);
	            }
	        }
	        
	        foreach ($params as $k=>$v){
	            $params[$k]=$k.'='.rawurlencode($v);
	        }
	        $url.=strpos($url, '?')===false?'?':'&';
	        $url.=implode('&', $params);
	    }
	    return $url;
	}
	
	
	public static function txt_match_params($txt,$pregRule,$returnKey=null){
	    $params=array();
	    if($pregRule){
	        static $txt_params=array();
	        $key=md5($txt."\r\n".$pregRule);
	        if(!isset($txt_params[$key])){
	            if(preg_match_all($pregRule,$txt,$params)){
	                $txt_params[$key]=$params;
	            }else{
	                $txt_params[$key]=array();
	            }
            }
            $params=$txt_params[$key];
	    }
	    init_array($params);
	    return isset($returnKey)?$params[$returnKey]:$params;
	}
	
	
	public static function txt_replace_params($isMulti,$allowDef,$txt,$defaultVal,$paramRule,$paramVals){
	    if(!isset($txt)){
	        $txt='';
	    }
	    if(!isset($defaultVal)){
	        $defaultVal='';
	    }
	    if(empty($txt)&&strlen($txt)<=0){
	        
	        if($isMulti){
	            return $allowDef?array($defaultVal):array();
	        }else{
	            return $allowDef?$defaultVal:'';
	        }
	    }else{
	        init_array($paramVals);
	        $strList=array();
	        if($isMulti){
	            
	            static $txt_list=array();
	            $txtMd5=md5($txt);
	            if(!isset($txt_list[$txtMd5])){
	                if(preg_match_all('/[^\r\n]+/',$txt,$mtxt)){
	                    $txt_list[$txtMd5]=$mtxt[0];
	                }else{
	                    $txt_list[$txtMd5]=array();
	                }
	            }
	            $strList=$txt_list[$txtMd5];
	        }else{
	            
	            $strList=array($txt);
	        }
            init_array($strList);
            if(empty($paramRule)){
                
                foreach ($strList as $sk=>$sv){
                    $strList[$sk]=str_replace('###', $defaultVal, $sv);
                }
            }else{
                
                foreach ($strList as $sk=>$sv){
                    $paramData=self::txt_match_params($sv,$paramRule,0);
                    init_array($paramData);
                    if(!empty($paramData)){
                        $paramData=array_flip($paramData);
                    }
                    foreach ($paramData as $pk=>$pv){
                        
                        $paramData[$pk]=isset($paramVals[$pk])?$paramVals[$pk]:'';
                    }
                    $paramData['###']=$defaultVal;
                    $sv=str_replace(array_keys($paramData), array_values($paramData), $sv);
                    $strList[$sk]=$sv;
                }
            }
            return $isMulti?$strList:$strList[0];
	    }
	}
}

?>