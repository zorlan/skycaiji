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

/*采集规则标签*/
function cp_sign($sign,$id=''){
	$sign=strtolower($sign);
	if($sign=='match'){
	    if($id==':id'){
	        
	        $id='(?P<id>\w*)';
	    }
	    return lang('sign_match',array('id'=>$id));
	}else{
	    return '';
	}
}

/*输出用户token*/
function html_usertoken(){
	
    return '<input type="hidden" name="_usertoken_" value="'.g_sc('usertoken').'" />';
}
function url_usertoken(){
    return '_usertoken_='.rawurlencode(g_sc('usertoken'));
}


function trim_input_array($arrName){
    if(empty($arrName)){
        return null;
    }
    $data=input($arrName.'/a',array(),'trim');
    $data=\util\Funcs::array_array_map('trim', $data);
    return $data;
}
