<?php 
/*翻译器*/
namespace Common\Util;
if(!defined('IN_SKYCAIJI')) {
	exit('NOT IN SKYCAIJI');
}
class Translator{
	public static $youdao_langs=array('zh'=>'zh-CHS','jp'=>'ja','en'=>'EN','kor'=>'ko','fra'=>'fr','ru'=>'ru','pt'=>'pt','spa'=>'es');//有道支持的语种
	/*翻译入口*/
	public static function translate($q,$from,$to){
		$transConf=$GLOBALS['config']['translate'];//翻译设置
		if(empty($from)||empty($to)){
			//没有设置翻译语言
			return $q;
		}
		if(empty($transConf['api'])){
			//没有设置api
			return $q;
		}
		$transConf['api']=strtolower($transConf['api']);
		if(empty($transConf[$transConf['api']])){
			//没有api配置
			return $q;
		}
		
		if('baidu'==$transConf['api']){
			$return=self::api_baidu($q, $from, $to);
			return $return['success']?$return['data']:$q;
		}elseif('youdao'==$transConf['api']){
			//转换成有道的标识
			$from=self::$youdao_langs[$from];
			$to=self::$youdao_langs[$to];
			
			if(empty($from)||empty($to)){
				//转换成空值表示没有该语种
				return $q;
			}
			
			$return=self::api_youdao($q, $from, $to);
			return $return['success']?$return['data']:$q;
		}else{
			//没有则返回原值
			return $q;
		}
	}
	
	/*百度翻译接口*/
	public static function api_baidu($q,$from,$to){
		$apiConf=$GLOBALS['config']['translate']['baidu'];//百度api配置
		
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
		$apiConf=$GLOBALS['config']['translate']['youdao'];//有道api配置
		
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