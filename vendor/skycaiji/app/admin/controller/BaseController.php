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

namespace skycaiji\admin\controller;

class BaseController extends \skycaiji\common\controller\BaseController{
    protected function _initialize(){
        
        if(request()->isPost()){
            $curController=strtolower(request()->controller());
            
            if($curController!='api'){
                $this->check_usertoken();
            }
        }
    }
    
    public function check_usertoken(){
        if(g_sc('usertoken')!=input('_usertoken_')){
            $this->error(lang('usertoken_error'),'');
        }
    }
    
	/*输出模板：防止ajax时乱码*/
	public function fetch($template = '', $vars = [], $replace = [], $config = []){
		if(request()->isAjax()){
			$config=is_array($config)?null:$config;
			return view($template, $vars, $replace,$config);
		}else{
			return parent::fetch($template, $vars, $replace, $config);
		}
	}
	
	public function set_html_tags($title,$name=null,$nav=null){
        if(isset($title)){
            set_g_sc('html_tag_title', $title);
        }
        if(isset($name)){
            set_g_sc('html_tag_name', $name);
        }
        if(isset($nav)){
            set_g_sc('html_tag_nav', $nav);
        }
	}
	
	
	public function ajax_check_userpwd(){
	    if(!input('?_userpwd_')){
	        $this->error('','',array('_userpwd_'=>true));
	    }
	    $userpwd=input('_userpwd_','');
	    if(empty($userpwd)){
	        $this->error('请输入密码','',array('_userpwd_'=>true));
	    }
	    $user=g_sc('user');
	    if(empty($user)){
	        $this->error('请先登录');
	    }
	    if(\skycaiji\admin\model\User::pwd_encrypt($userpwd,$user['salt'])!=$user['password']){
	        
	        $this->error('密码错误','',array('_userpwd_'=>true));
	    }
	}
}