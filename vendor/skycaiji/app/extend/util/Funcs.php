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
	            if(self::is_null($v)){
	                
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
	    $arrs=array(&$arr1,&$arr2);
	    self::_filter_key_val_list($arrs);
	}
	public static function filter_key_val_list3(&$arr1,&$arr2,&$arr3){
	    $arrs=array(&$arr1,&$arr2,&$arr3);
	    self::_filter_key_val_list($arrs);
	}
	public static function filter_key_val_list4(&$arr1,&$arr2,&$arr3,&$arr4){
	    $arrs=array(&$arr1,&$arr2,&$arr3,&$arr4);
	    self::_filter_key_val_list($arrs);
	}
	public static function filter_key_val_list5(&$arr1,&$arr2,&$arr3,&$arr4,&$arr5){
	    $arrs=array(&$arr1,&$arr2,&$arr3,&$arr4,&$arr5);
	    self::_filter_key_val_list($arrs);
	}
	
	private static function _filter_key_val_list(&$arrs){
	    if(is_array($arrs)){
	        
	        $count=count($arrs);
	        for($i=0;$i<$count;$i++){
	            if(!is_array($arrs[$i])){
	                $arrs[$i]=array();
	            }
	        }
	        
	        foreach ($arrs[0] as $k=>$v){
	            if(self::is_null($v)){
	                
	                for($i=0;$i<$count;$i++){
	                    unset($arrs[$i][$k]);
	                }
	            }
	        }
	        
	        for($i=0;$i<$count;$i++){
	            $arrs[$i]=array_values($arrs[$i]);
	        }
	    }
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
	
	public static function html_clear_js($html){
	    if($html){
	        $html=preg_replace('/<script[^<>]*?>[\s\S]*?<\/script>/i', '', $html);
	        $html=preg_replace('/\bon[a-z]+\s*\=\s*[\'\"]/', "$0return;", $html);
	        $html=preg_replace('/<meta[^<>]*charset[^<>]*?>/i', '', $html);
	        $html=preg_replace('/<meta[^<>]*http-equiv\s*=\s*[\'\"]{0,1}refresh\b[\'\"]{0,1}[^<>]*?>/i', '', $html);
	    }
	    return $html;
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
	    if(file_exists($path)){
	        $fileList=scandir($path);
	        foreach( $fileList as $file ){
	            $fileName=realpath($path.'/'.$file);
	            if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
	                if(empty($passFiles)||!in_array($fileName, $passFiles)){
	                    
	                    self::clear_dir($fileName,$passFiles);
	                    @rmdir($fileName);
	                }
	            }elseif(is_file($fileName)){
	                if(empty($passFiles)||!in_array($fileName, $passFiles)){
	                    
	                    @unlink($fileName);
	                }
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
	
	
	public static function url_params_charset($url,$params,$charset=null){
	    
	    if($params&&is_array($params)){
	        if(!empty($charset)&&!in_array(strtolower($charset),array('auto','utf-8','utf8'))){
	            $params=\util\Funcs::convert_charset($params,'utf-8',$charset);
	        }
	        
	        $params=http_build_query($params);
	        $url.=strpos($url, '?')===false?'?':'&';
	        $url.=$params;
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
	
	public static function class_exists_clean($className){
	    ob_start();
	    $status=class_exists($className);
	    ob_end_clean();
	    $status=$status?true:false;
	    return $status;
	}
	
	public static function is_null($val){
	    if(!empty($val)||$val===0||$val==='0'||$val===0.0||$val==='0.0'){
	        return false;
	    }else{
	        return true;
	    }
	}
	
	public static function convert_charset($data,$from,$to){
	    static $utfChars=array('utf-8','utf8','utf8mb4');
	    if($from&&$to){
	        
	        $from=strtolower($from);
	        $to=strtolower($to);
    	    if($from!=$to){
    	        
    	        if(in_array($from,$utfChars)){
    	            $from='utf-8';
    	        }
    	        if(in_array($to,$utfChars)){
    	            $to='utf-8';
    	        }
    	        if($from!=$to){
    	            
    	            if(!empty($data)){
    	                
    	                if(is_array($data)){
    	                    
    	                    foreach ($data as $k=>$v){
    	                        $data[$k]=self::convert_charset($v, $from, $to);
    	                    }
    	                }else{
    	                    
    	                    $data=iconv($from,$to.'//IGNORE',$data);
    	                }
    	            }
    	        }
    	    }
	    }
	    return $data;
	}
	
	public static function get_url_suffix($url){
	    if(preg_match('/\.([a-zA-Z][\w\-]+)([\?\#]|$)/',$url,$suffix)){
	        $suffix=strtolower($suffix[1]);
	    }else{
	        $suffix='';
	    }
	    return $suffix;
	}
	
	public static function url_auto_encode($url,$charset){
	    if($url){
	        $url=preg_replace_callback('/[^\x21-\x7E]+/',function($mstr)use($charset){
	            
	            $mstr=$mstr[0];
	            if(!empty($charset)){
	                
	                $mstr=self::convert_charset($mstr,'utf-8',$charset);
	            }
	            $mstr=rawurlencode($mstr);
	            return $mstr;
	        },$url);
	    }
	    return $url;
	}
	
	public static function txt_auto_url_encode($txt,$charset){
	    if(!preg_match('/\%[a-z0-9A-Z]{2}/',$txt)){
	        
	        $txt=\util\Funcs::convert_charset($txt,'utf-8',$charset);
	        $txt=rawurlencode($txt);
	    }else{
	        
	        $txt=preg_replace_callback('/[^\w\-\_\.\~\+]+/',function($match){
	            $match=$match[0];
	            $match=\util\Funcs::convert_charset($match,'utf-8',$charset);
	            $match=rawurlencode($match);
	            return $match;
	        },$txt);
	    }
	    return $txt;
	}
	
	public static function is_right_url($url){
	    if($url&&preg_match('/^\w+\:\/\//', $url)){
	        return true;
	    }else{
	        return false;
	    }
	}
	
	public static function get_cookies_from_header($header,$convert2str=false){
	    $cookies=array();
	    if($header){
	        if(preg_match_all('/^\s*cookie\s*\:([^\r\n]+);/im', $header, $mcookies)){
	            
	            foreach ($mcookies[1] as $mcv){
	                if(preg_match_all('/([^\;]+?)\=([^\;]*)/',$mcv,$mcookie)){
	                    foreach ($mcookie[1] as $k=>$v){
	                        $v=trim($v);
	                        if($v){
	                            $cookies[$v]=$mcookie[2][$k];
	                        }
	                    }
	                }
	            }
	        }
	        if(preg_match_all('/\bset\-cookie\s*\:([^\;]+?)\=([^\;]*)/i', $header, $mcookies)){
	            
	            foreach ($mcookies[1] as $k=>$v){
	                $v=trim($v);
	                if($v){
	                    $cookies[$v]=$mcookies[2][$k];
	                }
	            }
	        }
	    }
	    if($convert2str&&$cookies){
	        
	        $cookie=array();
	        foreach ($cookies as $k=>$v){
	            $cookie[]=$k.'='.$v;
	        }
	        $cookie=implode(';', $cookie);
	        $cookies=$cookie;
	    }
	    return $cookies;
	}
}

?>