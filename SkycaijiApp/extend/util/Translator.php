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
 
/*翻译器*/
namespace util;
class Translator{
	public static $all_langs=array(
		'zh'=>'中文',
		'en'=>'英语',
		'fra'=>'法语',
		'jp'=>'日语',
		'kor'=>'韩语',
		'de'=>'德语',
		'ru'=>'俄语',
		'spa'=>'西班牙语',
		'pt'=>'葡萄牙语',
		'it'=>'意大利语',
		'ara'=>'阿拉伯语',
		'th'=>'泰语',
		'el'=>'希腊语',
		'nl'=>'荷兰语',
		'pl'=>'波兰语',
		'bul'=>'保加利亚语',
		'est'=>'爱沙尼亚语',
		'dan'=>'丹麦语',
		'fin'=>'芬兰语',
		'cs'=>'捷克语',
		'rom'=>'罗马尼亚语',
		'slo'=>'斯洛文尼亚语',
		'swe'=>'瑞典语',
		'hu'=>'匈牙利语',
		'tr'=>'土耳其语',
		'id'=>'印尼语',
		'ms'=>'马来西亚语',
		'vie'=>'越南语',
		'yue'=>'粤语',
		'wyw'=>'文言文',
		'cht'=>'繁体中文'
	);
	
	public static $allow_langs = array (
		'baidu' => array (
			'zh'=>'zh',
			'en'=>'en',
			'fra'=>'fra',
			'jp'=>'jp',
			'kor'=>'kor',
			'de'=>'de',
			'ru'=>'ru',
			'spa'=>'spa',
			'pt'=>'pt',
			'it'=>'it',
			'ara'=>'ara',
			'th'=>'th',
			'el'=>'el',
			'nl'=>'nl',
			'pl'=>'pl',
			'bul'=>'bul',
			'est'=>'est',
			'dan'=>'dan',
			'fin'=>'fin',
			'cs'=>'cs',
			'rom'=>'rom',
			'slo'=>'slo',
			'swe'=>'swe',
			'hu'=>'hu',
			'vie'=>'vie',
			'yue'=>'yue',
			'wyw'=>'wyw',
			'cht'=>'cht'
		), 
		'youdao' => array (
			'zh' => 'zh-CHS',
			'en' => 'en',
			'jp' => 'ja',
			'kor' => 'ko',
			'fra' => 'fr',
			'spa' => 'es',
			'pt' => 'pt', 
			'it' => 'it',
			'ru' => 'ru',
			'vie'=>'vi',
			'de'=>'de',
			'ara'=>'ar',
			'id'=>'id',
			'it'=>'it'
		), 
		'qq' => array (
			'zh' => 'zh',
			'en' => 'en',
			'jp' => 'jp',
			'kor' => 'kr',
			'de' => 'de',
			'fra' => 'fr',
			'spa' => 'es',
			'it' => 'it',
			'tr' => 'tr',
			'ru' => 'ru',
			'pt' => 'pt',
			'vie' => 'vi',
			'id' => 'id',
			'ms' => 'ms',
			'th' => 'th',
			'cht' => 'zh-TW'
		)  
	);
	/*翻译入口*/
	public static function translate($q,$from,$to){
		$transConf=$GLOBALS['_sc']['c']['translate'];
		if(empty($from)||empty($to)){
			
			return $q;
		}
		$apiType=strtolower($transConf['api']);
		if(empty($apiType)){
			
			return $q;
		}
		
		$allowLangs=self::$allow_langs[$apiType];
		if(empty($allowLangs)){
			
			return $q;
		}
		$from=$allowLangs[$from];
		$to=$allowLangs[$to];
		if(empty($from)||empty($to)){
			
			return $q;
		}
		if($from==$to){
			return $q;
		}
		
		
		if(!empty($transConf['interval'])&&$transConf['interval']>0){
			
			usleep($transConf['interval']*1000);
		}
		
		if('baidu'==$apiType){
			$return=self::api_baidu($q, $from, $to);
		}elseif('youdao'==$apiType){
			$return=self::api_youdao($q, $from, $to);
		}elseif('qq'==$apiType){
			$return=self::api_qq($q, $from, $to);
		}
		return $return['success']?$return['data']:$q;
	}
	
	/*百度翻译接口*/
	public static function api_baidu($q,$from,$to){
		$apiConf=$GLOBALS['_sc']['c']['translate']['baidu'];
		
		$salt = time ();
		$sign = $apiConf['appid'] . $q . $salt . $apiConf['key'];
		$sign = md5 ( $sign );
		$data = get_html ( 'https://api.fanyi.baidu.com/api/trans/vip/translate',
			null, null,'utf-8',array('from'=>$from,'to'=>$to,'appid'=>$apiConf['appid'],'salt'=>$salt,'sign'=>$sign,'q'=>$q));
		$data = json_decode ( $data );
		
		$return=array('success'=>false);
		if($data->error_code){
			$return['error']='error:'.$data->error_code.'-'.$data->error_msg;
		}else{
			$transData = '';
			foreach ( $data->trans_result as $trans ) {
				$transData .= $trans->dst."\r\n";
			}
			if ($transData) {
				$return['success']=true;
				$return['data']=$transData;
			}
		}
			
		return $return;
	}
	/*有道翻译接口*/
	public static function api_youdao($q,$from,$to){
		$apiConf=$GLOBALS['_sc']['c']['translate']['youdao'];
		
		$salt = time ();
		$sign = $apiConf['appkey'] . $q . $salt . $apiConf['key'];
		$sign = md5 ( $sign );
		$data = get_html ( 'https://openapi.youdao.com/api',
			null, null,'utf-8',array('from'=>$from,'to'=>$to,'appKey'=>$apiConf['appkey'],'salt'=>$salt,'sign'=>$sign,'q'=>$q));
		$data = json_decode ( $data );
		
		$return=array('success'=>false);
		if(!empty($data->errorCode)){
			$return['error']='error:'.$data->errorCode;
		}else{
			$transData = '';
			foreach ( $data->translation as $trans ) {
				$transData .= $trans."\r\n";
			}
			if ($transData) {
				$return['success']=true;
				$return['data']=$transData;
			}
		}
		return $return;
	}
	
	/*腾讯翻译接口*/
	public static function api_qq($q,$from,$to){
		$apiConf=$GLOBALS['_sc']['c']['translate']['qq'];
		
		$SecretId=$apiConf['secretid'];
		$SecretKey=$apiConf['secretkey'];
		
		
		
		$param=array();
		$param["Nonce"] = rand();
		$param["Timestamp"] = time();
		$param["Region"] = "ap-shanghai";
		$param["SecretId"] = $SecretId;
		$param["Action"] = "TextTranslate";
		$param["Version"] = "2018-03-21";
		$param["SourceText"] = $q;
		$param["Source"] = $from;
		$param["Target"] = $to;
		$param['ProjectId']='0';
		
		
		ksort($param);
		
		
		$signStr = "GETtmt.ap-shanghai.tencentcloudapi.com/?";
		foreach ( $param as $key => $value ) {
			$signStr = $signStr . $key . "=" . $value . "&";
		}
		$signStr = substr($signStr, 0, -1);
		
		
		$param['Signature'] = base64_encode(hash_hmac("sha1", $signStr,$SecretKey, true));
		
		$return=array('success'=>false);

		
		ksort($param);

		$url='';
		foreach ( $param as $key => $value ) {
			$url = $url . $key . "=" . urlencode($value) . "&";
		}
		$url=trim($url,'&');
		
		$data = get_html ( 'https://tmt.'.$param["Region"].'.tencentcloudapi.com/?'.$url, null, null,'utf-8');
		$data = json_decode ( $data,true );
		
		if(!empty($data['Response']['TargetText'])){
			$return['success']=true;
			$return['data']=$data['Response']['TargetText'];
		}
		return $return;
	}
	
	public static function get_api_langs($api){
		$allowLangs=self::$allow_langs[$api];
		if(!empty($allowLangs)&&is_array($allowLangs)){
			foreach($allowLangs as $k=>$v){
				if(empty(self::$all_langs[$k])){
					
					unset($allowLangs[$k]);
				}else{
					$allowLangs[$k]=self::$all_langs[$k];
				}
			}
		}
		return is_array($allowLangs)?$allowLangs:null;
	}
}

?>