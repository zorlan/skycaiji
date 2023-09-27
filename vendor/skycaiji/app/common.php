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


define('SKYCAIJI_VERSION', '2.6');
\think\Loader::addNamespace('plugin', realpath(SKYCAIJI_PATH.'plugin'));
\think\Loader::addNamespace('util',realpath(APP_PATH.'extend/util'));

error_reporting(E_ERROR | E_PARSE); 

if (!function_exists('url')) {
	/**
	 * Url生成，重新定义
	 * @param string        $url 路由地址
	 * @param string|array  $vars 变量
	 * @param bool|string   $suffix 生成的URL后缀
	 * @param bool|string   $domain 域名
	 * @return string
	 */
    function url($url = '', $vars = '', $suffix = true, $domain = false){
        if($vars){
            
            if(is_array($vars)){
                $vars=http_build_query($vars);
            }
            $url.=(strpos($url,'?')!==false?'&':'?').$vars;
            $vars='';
        }else{
            $vars='';
        }
        
        $url=\util\Tools::url_is_compatible($url);
        $query=null;
        if($domain){
            
            if(preg_match('/^(.*?)(\?.*)$/',$url,$query)){
                $url=$query[1];
                $query=$query[2];
            }else{
                $query='';
            }
        }
        $url=\think\Url::build($url, $vars, $suffix, $domain);
        if($domain){
            $url.=$query;
        }
        return $url;
    }
}

function set_g_sc($keys,$val){
    \util\Funcs::array_set($GLOBALS['_sc'], $keys, $val);
}

function g_sc($key1,$key2=null,$key3=null){
    $keys=array($key1);
    if(isset($key2)){
        $keys[]=$key2;
        if(isset($key3)){
            $keys[]=$key3;
        }
    }
    return \util\Funcs::array_get($GLOBALS['_sc'], $keys);
}

function g_sc_c($key1,$key2=null,$key3=null){
    $keys=array($key1);
    if(isset($key2)){
        $keys[]=$key2;
        if(isset($key3)){
            $keys[]=$key3;
        }
    }
    return \util\Funcs::array_get($GLOBALS['_sc']['c'], $keys);
}

function is_empty($val,$notContainZero=false){
    if(empty($val)){
        
        if($notContainZero){
            
            if($val===0||$val==='0'){
                return false;
            }
        }
        return true;
    }else{
        return false;
    }
}
/*写入文件*/
function write_dir_file($filename,$data,$flags=0,$content=null){
    static $existsOpcache=null;
	$dir = dirname($filename);
	if(!is_dir($dir)){
		mkdir($dir,0777,true);
	}
	$status=file_put_contents($filename,$data,$flags,$content);
	if(!isset($existsOpcache)){
	    $existsOpcache=function_exists('opcache_reset');
	}
	if($existsOpcache){
	    
	    if(strpos($filename, '.php')!==false){
	        
	        opcache_reset();
	    }
	}
	return $status;
}
/*url编码传输*/
function url_b64encode($string) {
	$data = base64_encode($string);
	$data = str_replace(array('+','/','='),array('-','_',''),$data);
	return $data;
}
function url_b64decode($string) {
	$data = str_replace(array('-','_'),array('+','/'),$string);
	$mod4 = strlen($data) % 4;
	if ($mod4) {
		$data .= substr('====', $mod4);
	}
	return base64_decode($data);
}
/*面包屑导航*/
function breadcrumb($arr){
	$return='';
	if(!empty($arr)&&is_array($arr)){
		foreach ($arr as $v){
			if(is_string($v)){
				$return.='<li>'.$v.'</li>';
			}elseif(!empty($v['url'])){
				$return.='<li><a href="'.$v['url'].'">'.$v['title'].'</a></li>';
			}
		}
	}
	return $return;
}
/*任意编码转换成utf8*/
function auto_convert2utf8($str){
	$encode = mb_detect_encoding($str, array('ASCII','UTF-8','GB2312','GBK','BIG5'));
	$str=\util\Funcs::convert_charset($str,$encode,'utf-8');
	return $str;
}
/*客户端信息*/
function clientinfo(){
	$info=array(
		'url'=>config('root_website'),
		'v'=>constant('SKYCAIJI_VERSION'),
	);
	return $info;
}
/*默认全局过滤方法:config('default_filter')*/
function default_filter_func($str){
	
	return htmlspecialchars($str,ENT_QUOTES);
}
/*生成分页配置*/
function paginate_auto_config($path='',$queryParamsOrAuto=true){
	
	if(empty($path)){
		
		$path=request()->pathinfo();
	}
	
	if($queryParamsOrAuto==true){
		
		$params=input('param.');
	}else{
		
		$params=is_array($queryParamsOrAuto)?$queryParamsOrAuto:array();
	}
	
	$params[config('paginate.var_page')]='-_-PAGE-_-';
	$params=http_build_query($params);
	$path.=(strpos($path,'?')!==false?'&':'?').$params;
	
	$path=url($path);
	$path=str_replace('-_-PAGE-_-', '[PAGE]', $path);
	
	return array('path'=>$path);
}
/*返回结果*/
function return_result($msg,$success=false,$params=null){
    $result=array('success'=>$success?true:false,'msg'=>$msg);
    if(!empty($params)&&is_array($params)){
        $result=array_merge($result,$params);
    }
    return $result;
}
/*初始化数组*/
function init_array(&$data){
    if(!is_array($data)){
        $data=array();
    }
}
/**
 * 获取html代码
 * @param string $url
 * @param array $headers 键值对形式
 * @param array $options
 * @param string $fromEncode
 * @param array $postData 通过isset判断是否是post模式
 * @param bool $returnInfo 是否返回信息
 */
function get_html($url,$headers=array(),$options=array(),$fromEncode='auto',$postData=null,$returnInfo=false){
    $headers=is_array($headers)?$headers:array();
    $options=is_array($options)?$options:array();
    $fromEncode=strtolower($fromEncode);
    $userAgent=\util\Funcs::array_val_in_keys($headers,array('useragent','user-agent'),true);
    if($userAgent){
        
        $options['useragent']=$userAgent;
    }
    if(empty($options['useragent'])){
        $options['useragent']='Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70 Safari/537.36';
    }
    $options['timeout']=$options['timeout']>0?$options['timeout']:30;
    if(isset($headers['content-type'])){
        
        $options['content_type']=$headers['content-type'];
        unset($headers['content-type']);
    }
    
    $curlHeaders=array();
    if(!empty($headers)){
        foreach ($headers as $k=>$v){
            $curlHeaders[]=$k.': '.$v;
        }
    }
    $headers=$curlHeaders;
    unset($curlHeaders);
    
    if(!preg_match('/^\w+\:\/\//', $url)){
        
        $url='http://'.$url;
    }
    
    
    init_array($options['curlopts']);
    $confIpResolve=g_sc_c('caiji','ip_resolve');
    if($confIpResolve){
        
        $options['curlopts'][CURLOPT_IPRESOLVE]=$confIpResolve=='ipv6'?CURL_IPRESOLVE_V6:CURL_IPRESOLVE_V4;
    }
    $msg='';
    $curl=null;
    try {
        if(isset($postData)&&$postData!==false){
            
            $isAppJson=$options['content_type']=='application/json'?true:false;
            if(!empty($postData)&&$isAppJson){
                
                $postDataJson=array();
                foreach ($postData as $k=>$v){
                    if(!is_array($v)&&(strpos($v,'{')===0||strpos($v,'[')===0)){
                        
                        $vJson=\util\Funcs::convert_html2json($v);
                        if(!empty($vJson)){
                            $v=$vJson;
                        }
                    }
                    $k=isset($k)?$k:'';
                    if($k==='###'){
                        
                        $postDataJson=is_array($v)?$v:array();
                    }else{
                        $k=explode('.', $k);
                        $postDataJsonKey=&$postDataJson;
                        foreach ($k as $kv){
                            
                            if(!is_array($postDataJsonKey[$kv])){
                                $postDataJsonKey[$kv]=array();
                            }
                            $postDataJsonKey=&$postDataJsonKey[$kv];
                        }
                        $postDataJsonKey=$v;
                    }
                }
                $postData=$postDataJson;
                unset($postDataJson);
            }
            if(!$isAppJson&&!empty($postData)&&!empty($fromEncode)&&!in_array($fromEncode, array('auto','utf-8','utf8'))){
                
                if(!is_array($postData)){
                    
                    if(preg_match_all('/([^\&]+?)\=([^\&]*)/',$postData,$m_post_data)){
                        $new_post_data=array();
                        foreach($m_post_data[1] as $k=>$v){
                            $new_post_data[$v]=rawurldecode($m_post_data[2][$k]);
                        }
                        $postData=$new_post_data;
                    }else{
                        $postData=array();
                    }
                }
                $postData=is_array($postData)?$postData:array();
                $postData=\util\Funcs::convert_charset($postData, 'utf-8', $fromEncode);
            }
            $curl=\util\Curl::post($url,$headers,$options,$postData);
        }else{
            
            $allow_get=true;
            if(!empty($options['max_bytes'])){
                
                $max_bytes=intval($options['max_bytes']);
                unset($options['max_bytes']);
                $curl=\util\Curl::head($url,$headers,$options);
                if(preg_match('/\bContent-Length\s*:\s*(\d+)/i', $curl->header,$contLen)){
                    
                    $contLen=intval($contLen[1]);
                    if($contLen>=$max_bytes){
                        $allow_get=false;
                        $msg='超出限制大小';
                    }
                }
            }
            if($allow_get){
                
                if(!empty($options['return_head'])){
                    
                    if(empty($curl)){
                        $curl=\util\Curl::head($url,$headers,$options);
                    }
                }else{
                    
                    $curl=\util\Curl::get($url,$headers,$options);
                }
            }else{
                $curl=null;
            }
        }
    } catch (\Exception $e) {
        $curl=null;
    }
    
    if($options['return_curl']){
        
        return $curl;
    }
    
    $html=null;
    if(!empty($curl)){
        if($curl->ok){
            
            $html=$curl->body;
        }else{
            
            if($options['return_body']){
                
                $html=$curl->body;
            }
        }
        if($html){
            if ($fromEncode == 'auto') {
                
                $htmlCharset='';
                if(preg_match ( '/<meta[^<>]*?content=[\'\"]text\/html\;\s*charset=(?P<charset>[^\'\"\<\>]+?)[\'\"]/i', $html, $htmlCharset ) || preg_match ( '/<meta[^<>]*?charset=[\'\"](?P<charset>[^\'\"\<\>]+?)[\'\"]/i', $html, $htmlCharset )){
                    $htmlCharset=strtolower(trim($htmlCharset['charset']));
                    if('utf8'==$htmlCharset){
                        $htmlCharset='utf-8';
                    }
                }else{
                    $htmlCharset='';
                }
                $headerCharset='';
                if(preg_match('/\bContent-Type\s*:[^\r\n]*charset=(?P<charset>[\w\-]+)/i', $curl->header,$headerCharset)){
                    $headerCharset=strtolower(trim($headerCharset['charset']));
                    if('utf8'==$headerCharset){
                        $headerCharset='utf-8';
                    }
                }else{
                    $headerCharset='';
                }
                if(!empty($htmlCharset)&&!empty($headerCharset)&&strcasecmp($htmlCharset,$headerCharset)!==0){
                    
                    $zhCharset=array('gb18030','gbk','gb2312');
                    if(in_array($htmlCharset,$zhCharset)&&in_array($headerCharset,$zhCharset)){
                        
                        $fromEncode='gb18030';
                    }else{
                        
                        $autoEncode = mb_detect_encoding($html, array('ASCII','UTF-8','GB2312','GBK','BIG5'));
                        if(strcasecmp($htmlCharset,$autoEncode)==0){
                            $fromEncode=$htmlCharset;
                        }elseif(strcasecmp($headerCharset,$autoEncode)==0){
                            $fromEncode=$headerCharset;
                        }else{
                            $fromEncode=$autoEncode;
                        }
                    }
                }elseif(!empty($htmlCharset)){
                    
                    $fromEncode=$htmlCharset;
                }elseif(!empty($headerCharset)){
                    
                    $fromEncode=$headerCharset;
                }else{
                    
                    $fromEncode = mb_detect_encoding($html, array('ASCII','UTF-8','GB2312','GBK','BIG5'));
                }
                
                $fromEncode=empty($fromEncode)?null:$fromEncode;
            }
            $fromEncode=trim($fromEncode);
            
            if(!empty($fromEncode)){
                
                $fromEncode=strtolower($fromEncode);
                switch ($fromEncode){
                    case 'utf8':$fromEncode='utf-8';break;
                    case 'cp936':$fromEncode='gbk';break;
                    case 'cp20936':$fromEncode='gb2312';break;
                    case 'cp950':$fromEncode='big5';break;
                }
                $html=\util\Funcs::convert_charset($html,$fromEncode,'utf-8');
            }
        }
    }
    if(!isset($html)){
        $html='';
    }
    if($returnInfo){
        $info=array('code'=>0,'ok'=>false,'header'=>'','html'=>$html);
        if(!empty($curl)){
            $info['code']=$curl->code;
            $info['ok']=$curl->ok;
            $info['header']=$curl->header;
            
            $info['error']=$curl->error;
            $info['info']=$curl->info;
        }
        if($msg){
            
            $info['msg']=$msg;
        }
        return $info;
    }else{
        return $html;
    }
}