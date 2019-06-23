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

namespace skycaiji\admin\model;
/*第三方服务商*/
class Provider extends BaseModel{
	/*匹配域名*/
	public static function matchDomain($url){
		$domain=null;
		if(preg_match('/^\w+\:\/\/[\w\-]+(\.[\w\-]+)*(\:\d+){0,1}/', $url,$domain)){
			$domain=rtrim($domain[0],'/');
		}else{
			$domain=null;
		}
		return $domain;
	}
	/*获取id*/
	public function getIdByUrl($url){
		$url=self::matchDomain($url);
		if(is_official_url($url)){
			
			$url=null;
		}
		$id=0;
		if(!empty($url)){
			$id=model('Provider')->where('domain',$url)->value('id');
			$id=intval($id);
		}
		return $id;
	}
}

?>