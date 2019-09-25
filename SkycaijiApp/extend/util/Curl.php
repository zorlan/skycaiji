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
 
/*curl*/
namespace util;
class Curl{
	protected static $instance=null;
	public $header=null;
	public $isOk=null;
	public $body=null;
	
	/*实例*/
	private static function init(){
		if(!isset(self::$instance)){
			self::$instance=new static;
		}
		
		self::$instance->header=null;
		self::$instance->isOk=null;
		self::$instance->body=null;
		
		return self::$instance;
	}
	/**
	 * 请求
	 * @param string $url
	 * @param array $headers header格式必须为 “键: 值”
	 * @param array $options
	 * @param string $postData
	 * @return \util\Curl
	 */
	public static function request($url,$headers=array(),$options=array(),$postData=null){
		$instance=self::init();
		
		$headers=is_array($headers)?$headers:array();
		$options=is_array($options)?$options:array();

		$options['timeout']=intval($options['timeout']);
		$options['timeout']=$options['timeout']>0?$options['timeout']:20;
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, $options['timeout'] );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT , 10 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 1 );
		if($options['nobody']){
			
			curl_setopt($ch, CURLOPT_NOBODY, true);
		}
		if($options['useragent']){
			
			curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
		}
		
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		
		if(!empty($headers)&&count($headers)>0){
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		}
		
		if(isset($postData)){
			
			curl_setopt ( $ch, CURLOPT_POST, 1 );
			if(is_array($postData)){
				
				$postData=http_build_query($postData);
			}
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $postData );
		}
		if(!empty($options['proxy'])&&!empty($options['proxy']['ip'])){
			
			$proxyType=null;
			switch ($options['proxy']['type']){
				case 'socks4':$proxyType=CURLPROXY_SOCKS4;break;
				case 'socks5':$proxyType=CURLPROXY_SOCKS5;break;
				default:$proxyType=CURLPROXY_HTTP;break;
			}
			
			curl_setopt($ch, CURLOPT_PROXYTYPE,$proxyType); 

			curl_setopt($ch, CURLOPT_PROXY, $options['proxy']['ip']); 
			curl_setopt($ch, CURLOPT_PROXYPORT, $options['proxy']['port']); 
			if(!empty($options['proxy']['user'])){
				curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); 
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $options['proxy']['user'].':'.$options['proxy']['pwd']); 
			}
		}

		$body = curl_exec ( $ch );
		
		$headerPos=strpos($body, "\r\n\r\n");
		if($headerPos!==false){
			$headerPos=intval($headerPos)+strlen("\r\n\r\n");
		}
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$headerSize=intval($headerSize);
		if($headerSize<$headerPos){
			$headerSize=$headerPos;
		}
		
		$instance->header = substr($body, 0, $headerSize);
		$instance->body = substr($body, $headerSize);
		
		$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$code=intval($code);
		if($code>=200&&$code<300){
			$instance->isOk=true;
		}else{
			$instance->isOk=false;
		}
		curl_close ( $ch );
		
		return $instance;
	}
	

	public static function head($url,$headers=array(),$options=array()){
		$options=is_array($options)?$options:array();
		$options['nobody']=1;
		return self::request($url,$headers,$options);
	}
	public static function get($url,$headers=array(),$options=array()){
		return self::request($url,$headers,$options);
	}
	public static function post($url,$headers=array(),$options=array(),$data=null){
		return self::request($url,$headers,$options,$data?$data:'');
	}
}

?>