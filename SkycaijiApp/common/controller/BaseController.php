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

namespace skycaiji\common\controller;
use think\Controller;
class BaseController extends Controller {
	public function error($msg = '', $url = null, $data = array(), $wait = 3, array $header = []){
		$url=url_is_compatible($url);
		parent::error($msg,$url,$data,$wait,$header);
		exit();
	}
	public function success($msg = '', $url = null, $data = array(), $wait = 3, array $header = []){
		$url=url_is_compatible($url);
		parent::success($msg,$url,$data,$wait,$header);
		exit();
	}

	/**
	 * tp3.2默认跳转操作 支持错误导向和正确跳转
	 * @param Boolean $success 状态
	 * @param string $message 提示信息
	 * @param string $jumpUrl 页面跳转地址
	 * @param mixed $ajax 是否为Ajax方式
	 * @access private
	 * @return void
	 */
	public function dispatchJump($success=true,$message='',$url='',$ajax=false,$else=null) {
		$success=$success?1:0;
		
		if(input('_serverinfo')&&(true === $ajax || request()->isAjax() || input('_ajax')==1)){
			
			$data=is_array($else)?$else:array();
			$data['info']=$message;
			$data['status']=$success;
			$data['url']=$url;

			$callback=input('callback');
			if(!empty($callback)){
				jsonp($data)->send();
			}else{
				json($data)->send();
			}
		}else{
			
			$wait=null;
			$data=null;
			if(is_int($else)){
				$wait=$else;
			}elseif(is_array($else)){
				$data=$else;
			}
			if($success){
				$this->success($message,$url,$data,$wait);
			}else{
				$this->error($message,$url,$data,$wait);
			}
		}
		exit();
	}
}
