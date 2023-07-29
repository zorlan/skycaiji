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
	public $code=0;
	public $ok=false;
	public $header=null;
	public $body=null;
	public $error=array();
	public $info=array();
	
	/*实例*/
	private static function init(){
		if(!isset(self::$instance)){
			self::$instance=new static;
		}
		
		self::$instance->code=0;
		self::$instance->ok=false;
		self::$instance->header=null;
		self::$instance->body=null;
		self::$instance->error=array();
		self::$instance->info=array();
		
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
	public static function request($url,$rHeaders=array(),$rOptions=array(),$rPostData=null,$curRedirs=0){
	    $headers=$rHeaders;
	    $options=$rOptions;
	    $postData=$rPostData;
	    
		$instance=self::init();
		
		$isPost=false;
		if(isset($postData)&&$postData!==false){
		    
		    $isPost=true;
		}
		
		$headers=is_array($headers)?$headers:array();
		$options=is_array($options)?$options:array();

		$options['timeout']=intval($options['timeout']);
		$options['timeout']=$options['timeout']>0?$options['timeout']:20;
		
		if($isPost&&$options['content_type']){
		    
		    $options['content_type']=strtolower($options['content_type']);
		    $headers[]='content-type: '.$options['content_type'];
		}
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, $options['timeout'] );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT , 10 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		if($options['nobody']){
			
			curl_setopt($ch, CURLOPT_NOBODY, true);
		}
		if($options['useragent']){
			
			curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
		}
		$options['max_redirs']=intval($options['max_redirs']);
		if($options['max_redirs']){
		    
		    curl_setopt($ch, CURLOPT_MAXREDIRS, $options['max_redirs']);
		}
		
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		
		if(!empty($headers)&&count($headers)>0){
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		}
		if($options['range_size']){
		    
		    curl_setopt($ch, CURLOPT_RANGE, $options['range_size']);
		}
		
		if($options['custom_request']){
		    curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, $options['custom_request']);
		}
		
		if($isPost){
			
			curl_setopt ( $ch, CURLOPT_POST, 1 );
			if(empty($options['content_type'])||$options['content_type']=='application/x-www-form-urlencoded'){
			    if(is_array($postData)){
			        
			        $postData=http_build_query($postData);
			    }
			}elseif($options['content_type']=='application/json'){
			    
			    $postData=json_encode($postData);
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
		/*直接设置curl选项*/
		if($options['curlopts']&&is_array($options['curlopts'])){
		    foreach ($options['curlopts'] as $k=>$v){
		        curl_setopt($ch,$k,$v);
		    }
		}
		
		if($options['return_curl']){
		    
		    return $ch;
		}

		
		$instance->header=null;
		$instance->body=null;
		$instance->error=array();
		$instance->info=array();
		
		$body = curl_exec ( $ch );
		if($body){
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
		}
		
		$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$code=intval($code);
		$instance->code=$code;
		$instance->ok=($code>=200&&$code<300)?true:false;
		if(!isset($instance->header)){
		    $instance->header='';
		}
		if(!isset($instance->body)){
		    $instance->body='';
		}
		if(!$instance->ok){
		    $errorNo=curl_errno($ch);
		    if($errorNo){
		        
		        $instance->error=array('no'=>$errorNo,'msg'=>curl_error($ch));
		    }
		}
		if($options['return_info']){
		    
		    $instance->info=curl_getinfo($ch);
		    if(!is_array($instance->info)){
		        $instance->info=array();
		    }
		}
		
		if(!$instance->ok&&$instance->code>=300&&$instance->code<400){
		    
		    $info=empty($instance->info)?curl_getinfo($ch):$instance->info;
		    if(is_array($info)&&$info){
		        $rurl=$info['redirect_url']?:'';
		        if($rurl&&$rurl!=$url){
		            
		            $maxRedirs=max(3,$options['max_redirs']);
		            if($curRedirs<$maxRedirs){
		                
		                $curRedirs++;
		                curl_close($ch);
		                return self::request($rurl,$rHeaders,$rOptions,$rPostData,$curRedirs);
		            }
		        }
		    }
		}
		
		curl_close($ch);
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
		return self::request($url,$headers,$options,empty($data)?'':$data);
	}
}

?>