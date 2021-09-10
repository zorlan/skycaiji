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


define('SKYCAIJI_VERSION', '2.4');
\think\Loader::addNamespace('plugin', realpath(ROOT_PATH.'plugin'));
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
		$url=url_is_compatible($url);
		return \think\Url::build($url, $vars, $suffix, $domain);
	}
}

/*兼容url设置*/
function url_is_compatible($url){
	static $urlConvert=null;
	if(defined('URL_IS_COMPATIBLE')&&$url){
		
		if(!isset($urlConvert)){
			
			config('url_convert',false);
			$urlConvert=1;
		}
		if(false === strpos($url, '://')){
			
			$url=str_replace('?', '&', $url);
		}
	}
	return $url;
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

function is_empty($val){
    return empty($val);
}

/*写入文件*/
function write_dir_file($filename,$data,$flags=null,$content=null){
	$dir = dirname($filename);
	if(!is_dir($dir)){
		mkdir($dir,0777,true);
	}
	return file_put_contents($filename,$data,$flags,$content);
}

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
	if(strcasecmp($encode, 'utf-8')!==0){
		$str=iconv($encode,'utf-8//IGNORE',$str);
	}
	return $str;
}

/**
 * 发送邮件
 * @param array $emailConfig
 * @param string $to
 * @param string $name
 * @param string $subject
 * @param string $body
 * @param string $attachment
 * @return boolean
 */
function send_mail($emailConfig,$to, $name, $subject = '', $body = '', $attachment = null){
	set_time_limit(60);
	
	$mail = new \PHPMailer();
	

	$mail->isSMTP();
	$mail->Host = $emailConfig['smtp'];
	$mail->SMTPAuth = true;
	$mail->Username = $emailConfig['email'];
	$mail->Password = $emailConfig['pwd'];
	$mail->SMTPSecure = empty($emailConfig['type'])?'tls':$emailConfig['type'];
	$mail->Port = $emailConfig['port'];

	$mail->setFrom($emailConfig['email'], $emailConfig['sender']);
	$mail->addAddress($to, $name);
	
	$mail->isHTML(true);
	
	$mail->Subject = $subject;
	$mail->Body    = $body;
	$mail->AltBody = '';
	
	if(is_array($attachment)){ 
		foreach ($attachment as $file){
			is_file($file) && $mail->AddAttachment($file);
		}
	}
	return $mail->Send() ? true : $mail->ErrorInfo;
}

/*客户端信息*/
function clientinfo(){
	$info=array(
		'url'=>config('root_website'),
		'v'=>constant('SKYCAIJI_VERSION'),
	);
	return $info;
}
/**
 * 获取html代码
 * @param string $url
 * @param string $headers 键值对形式
 * @param array $options
 * @param string $fromEncode
 * @param array $postData 通过isset判断是否是post模式
 * @param bool $returnInfo 是否返回信息
 */
function get_html($url,$headers=null,$options=array(),$fromEncode='auto',$postData=null,$returnInfo=false){
	$headers=is_array($headers)?$headers:array();
	$options=is_array($options)?$options:array();
	$fromEncode=strtolower($fromEncode);
	if(isset($headers['useragent'])){
	    
	    $options['useragent']=$headers['useragent'];
	    unset($headers['useragent']);
	}
	if(empty($options['useragent'])){
		$options['useragent']='Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70 Safari/537.36';
	}
	$options['timeout']=$options['timeout']>0?$options['timeout']:30;

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

	$curl=null;
	try {
	    if(isset($postData)&&$postData!==false){
	        
	        if(!empty($postData)&&!empty($fromEncode)&&!in_array($fromEncode, array('auto','utf-8','utf8'))){
	            
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
	            foreach ($postData as $k=>$v){
	                $postData[$k] = iconv ( 'utf-8', $fromEncode.'//IGNORE', $v );
	            }
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
					}
				}
			}
			if($allow_get){
				
				$curl=\util\Curl::get($url,$headers,$options);
			}else{
				$curl=null;
			}
		}
	} catch (\Exception $e) {
		$curl=null;
	}
	$html=null;
	
	if(!empty($curl)){
		if($curl->isOk){
			
			$html=$curl->body;
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
				if ($fromEncode!='utf-8'){
					$html = iconv ( $fromEncode, 'utf-8//IGNORE', $html );
				}
			}
		}
	}
	$html=isset($html)?$html:false;
	if(!$returnInfo){
	    return $html;
	}else{
	    if(empty($curl)){
	        return array('header'=>'','ok'=>($html===false?false:true),'html'=>$html);
	    }else{
	        return array('header'=>$curl->header,'ok'=>$curl->isOk,'html'=>$html);
	    }
	}
}

/*载入配置文件*/
function load_data_config(){
	static $loaded=false;
	if(!$loaded){
		if(file_exists(config('root_path').'/data/config.php')){
			
			$dataConfig=include config('root_path').'/data/config.php';
			
			$dbConfig=array();
			foreach ($dataConfig as $k=>$v){
				if(strpos($k, 'DB_')!==false){
					
					$dbConfig[$k]=$v;
					unset($dataConfig[$k]);
				}
			}
			
			
			$dbConfig=array(
				'type'=>$dbConfig['DB_TYPE'],
				'hostname'=>$dbConfig['DB_HOST'],
				'hostport'=>$dbConfig['DB_PORT'],
				'database'=>$dbConfig['DB_NAME'],
				'password'=>$dbConfig['DB_PWD'],
				'username'=>$dbConfig['DB_USER'],
				'prefix'=>$dbConfig['DB_PREFIX'],
			);
			
			if(!empty($dbConfig)&&is_array($dbConfig)){
				$dbConfig=array_merge(config('database'),$dbConfig);
				config('database',$dbConfig);
				config($dataConfig);
				$loaded=true;
			}
		}
	}
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