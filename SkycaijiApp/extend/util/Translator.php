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
	public static $youdao_langs=array('zh'=>'zh-CHS','jp'=>'ja','en'=>'EN','kor'=>'ko','fra'=>'fr','ru'=>'ru','pt'=>'pt','spa'=>'es');
	/*翻译入口*/
	public static function translate($q,$from,$to){
		$transConf=$GLOBALS['config']['translate'];
		if(empty($from)||empty($to)){
			
			return $q;
		}
		if(empty($transConf['api'])){
			
			return $q;
		}
		$transConf['api']=strtolower($transConf['api']);
		if(empty($transConf[$transConf['api']])){
			
			return $q;
		}
		
		if('baidu'==$transConf['api']){
			$return=self::api_baidu($q, $from, $to);
			return $return['success']?$return['data']:$q;
		}elseif('youdao'==$transConf['api']){
			
			$from=self::$youdao_langs[$from];
			$to=self::$youdao_langs[$to];
			
			if(empty($from)||empty($to)){
				
				return $q;
			}
			
			$return=self::api_youdao($q, $from, $to);
			return $return['success']?$return['data']:$q;
		}else{
			
			return $q;
		}
	}
	
	/*百度翻译接口*/
	public static function api_baidu($q,$from,$to){
		$apiConf=$GLOBALS['config']['translate']['baidu'];
		
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
		$apiConf=$GLOBALS['config']['translate']['youdao'];
		
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
}

?>