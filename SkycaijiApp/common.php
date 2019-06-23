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


define('SKYCAIJI_VERSION', '2.2');
define('NOW_TIME', time());
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
/*多维数组array_map*/
function array_array_map($callback, $arr1, array $_ = null){
	if(is_array($arr1)){
		$arr=array();
		foreach ($arr1 as $k=>$v){
			if(!is_array($v)){
				$arr[$k]=call_user_func($callback, $v);
			}else{
				$arr[$k]=array_array_map($callback,$v,$_);
			}
		}
	}
	return $arr;
}
/*多维数组implode*/
function array_implode($glue, $pieces){
	$str='';
	foreach ($pieces as $v){
		if(is_array($v)){
			$str.=array_implode($glue,$v);
		}else{
			$str.=$glue.$v;
		}
	}
	return $str;
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
/*获取html代码*/
function get_html($url,$headers=null,$options=array(),$fromEncode='auto',$post_data=null){
	$headers=is_array($headers)?$headers:array();
	$options=is_array($options)?$options:array();
	if(!isset($options['useragent'])){
		$options['useragent']='Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70 Safari/537.36';
	}
	$options['timeout']=$options['timeout']>0?$options['timeout']:30;
	$options['verify']=false;

	if(!preg_match('/^\w+\:\/\//', $url)){
		
		$url='http://'.$url;
	}

	try {
		if(!isset($post_data)){
			
			$allow_get=true;
			if(!empty($options['max_bytes'])){
				
				$max_bytes=intval($options['max_bytes']);
				unset($options['max_bytes']);
				$request=\Requests::head($url,$headers,$options);
				if(preg_match('/\bContent-Length\s*:\s*(\d+)/i', $request->raw,$contLen)){
					
					$contLen=intval($contLen[1]);
					if($contLen>=$max_bytes){
						$allow_get=false;
					}
				}
			}
			if($allow_get){
				
				$request=\Requests::get($url,$headers,$options);
			}else{
				$request=null;
			}
		}else{
			
			if(!is_array($post_data)){
				
				if(preg_match_all('/([^\&]+)\=([^\&]*)/',$post_data,$m_post_data)){
					$new_post_data=array();
					foreach($m_post_data[1] as $k=>$v){
						$new_post_data[$v]=rawurldecode($m_post_data[2][$k]);
					}
					$post_data=$new_post_data;
				}else{
					$post_data='';
				}
			}
			$post_data=empty($post_data)?array():$post_data;
			if(!empty($post_data)&&!empty($fromEncode)&&!in_array(strtolower($fromEncode), array('auto','utf-8','utf8'))){
				
				foreach ($post_data as $k=>$v){
					$post_data[$k] = iconv ( 'utf-8', $fromEncode.'//IGNORE', $v );
				}
			}
			$request=\Requests::post($url,$headers,$post_data,$options);
		}
	} catch (\Exception $e) {
		$request=null;
	}
	if(!empty($request)){
		if(200==$request->status_code){
			
			$html=$request->body;
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
				if(preg_match('/charset=(?P<charset>[\w\-]+)/i', $request->headers['content-type'],$headerCharset)){
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
	return $html?$html:null;
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

/*清空目录，不删除根目录*/
function clear_dir($path,$passFiles=null){
	if(empty($path)){
		return;
	}
	$path=realpath($path);
	if(empty($path)){
		return;
	}
	if(!empty($passFiles)){
		$passFiles=array_map('realpath', $passFiles);
	}
	
	$fileList=scandir($path);
	foreach( $fileList as $file ){
		$fileName=realpath($path.'/'.$file);
		if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
			clear_dir($fileName,$passFiles);
			rmdir($fileName);
		}elseif(is_file($fileName)){
			if($passFiles&&in_array($fileName, $passFiles)){
				
				
			}else{
				unlink($fileName);
			}
		}
	}
	clearstatcache();
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