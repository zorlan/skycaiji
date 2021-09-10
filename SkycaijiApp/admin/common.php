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
/*密码验证*/
function check_verify($verifycode){
	if(empty($verifycode)){
		return array('msg'=>lang('verifycode_error'),'name'=>'verifycode');
	}

	$verify = new \think\captcha\Captcha(array('reset'=>false));
	if(!$verify->check($verifycode)){
		return array('msg'=>lang('verifycode_error'),'name'=>'verifycode');
	}
	return array('success'=>true);
}

/*获取项目的文件md5列表*/
function program_filemd5_list($path,&$md5FileList){
	static $passPaths=array();
	if(empty($passPaths)){
		
		$passPaths['data']=realpath(config('root_path').'/data');
		$passPaths['runtime']=realpath(config('root_path').'/runtime');
		$passPaths=array_filter($passPaths);
	}
	$fileList=scandir($path);
	foreach( $fileList as $file ){
		$isPass=false;
		$fileName=realpath($path.'/'.$file);
		foreach ($passPaths as $passPath){
			
			if($fileName==$passPath||stripos($fileName,$passPath)>0){
				$isPass=true;
			}
		}
		if($isPass){
			continue;
		}

		if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
			program_filemd5_list( $fileName,$md5FileList );
		}elseif(is_file($fileName)){
			$root=realpath(config('root_path'));
			$curFile=str_replace('\\', '/',str_replace($root, '', $fileName));
			
			$md5FileList[]=array('md5'=>md5_file($fileName),'file'=>$curFile);
		}
	}
}
/*输出用户token*/
function html_usertoken(){
	
    return '<input type="hidden" name="_usertoken_" value="'.g_sc('usertoken').'" />';
}
function url_usertoken(){
    return '_usertoken_='.rawurlencode(g_sc('usertoken'));
}

/*判断正在执行采集任务*/
function is_collecting(){
	if(defined('IS_COLLECTING')){
		return true;
	}else{
		return false;
	}
}
/*移除自动采集»正在采集状态*/
function remove_auto_collecting(){
	\skycaiji\admin\model\CacheModel::getInstance()->db()->where('cname','auto_collecting')->delete();
}

/*cli命令行*/
function cli_command_exec($paramStr){
	
	if(config('cli_cache_config')){
		$cacheConfig=\skycaiji\admin\model\CacheModel::getInstance()->getCache('cli_cache_config','data');
		$cliConfig=array();
		foreach (config('cli_cache_config') as $key){
			$cliConfig[$key]=config($key);
		}
		if(serialize($cacheConfig)!=serialize($cliConfig)){
			
			\skycaiji\admin\model\CacheModel::getInstance()->setCache('cli_cache_config',$cliConfig);
		}
	}
	
	$commandStr=g_sc_c('caiji','server_php');
	if(empty($commandStr)){
	    
	    $commandStr=\skycaiji\admin\model\Config::detect_php_exe();
	}
    if(!empty($commandStr)){
        $commandStr=\skycaiji\admin\model\Config::cli_safe_filename($commandStr);
        
        $cliUser=intval(g_sc('user','uid')).'_'.model('User')->generate_key(g_sc('user'));
        
        $paramStr.=' --cli_user '.base64_encode($cliUser);
        
        $commandStr.=' '.config('root_path').DIRECTORY_SEPARATOR.'caiji '.$paramStr;
        
        if(session_status()!==PHP_SESSION_ACTIVE){
            session_start();
        }
        session_write_close();
        
        proc_open_exec($commandStr,false);
    }
    
    exit();
}

function proc_open_exec($commandStr,$returnInfo=false,$timeout=10,$closeProc=false){
    $info=array('status'=>'','output'=>'','error'=>'');
    $timeout=intval($timeout);
    if($timeout<=0){
        $timeout=10;
    }
    if(!empty($commandStr)){
        $descriptorspec = array(
            0 => array('pipe', 'r'),  
            1 => array('pipe', 'w'),  
            2 => array('pipe', 'w')
        );
        $pipes=array();
        $otherOptions=IS_WIN?array('suppress_errors'=>true,'bypass_shell'=>true):array();
        $handle=proc_open($commandStr,$descriptorspec,$pipes,null,null,$otherOptions);
        if($returnInfo){
            
            if(!is_resource($handle)){
                
                $info['error']='命令执行失败，请检查可执行文件是否存在，以及'.\util\Funcs::web_server_name().'服务器的用户权限';
            }else{
                $returnInfo=$returnInfo=='all'?array('status','output','error'):explode(',',$returnInfo);
                $nowtime=time();
                if(in_array('status',$returnInfo)){
                    
                    $info['status']=proc_get_status($handle);
                }
                if(in_array('output',$returnInfo)){
                    
                    if(function_exists('stream_set_blocking')){
                        stream_set_blocking($pipes[1],false);
                    }
                    if(function_exists('stream_set_timeout')){
                        stream_set_timeout($pipes[1],$timeout);
                    }
                    while(is_resource($pipes[1])&&!feof($pipes[1])){
                        $info['output'].=fgets($pipes[1]);
                        if((time()-$nowtime)>$timeout){
                            
                            break;
                        }
                    }
                }
                $nowtime=time();
                if(in_array('error',$returnInfo)){
                    
                    if(function_exists('stream_set_blocking')){
                        stream_set_blocking($pipes[2],false);
                    }
                    if(function_exists('stream_set_timeout')){
                        stream_set_timeout($pipes[2],$timeout);
                    }
                    while(is_resource($pipes[2])&&!feof($pipes[2])){
                        $info['error'].=fgets($pipes[2]);
                        if((time()-$nowtime)>$timeout){
                            
                            break;
                        }
                    }
                }
                
                foreach (array('output','error') as $key){
                    
                    if(!empty($info[$key])){
                        $encode=mb_detect_encoding($info[$key], array('ASCII','UTF-8','GB2312','GBK','BIG5'));
                        if($encode!='UTF-8'){
                            $info[$key] = iconv ( $encode, 'utf-8//IGNORE', $info[$key] );
                        }
                    }
                }
            }
        }
        if(is_resource($pipes[0])){
            fclose($pipes[0]);
        }
        if(is_resource($pipes[1])){
            fclose($pipes[1]);
        }
        if(is_resource($pipes[2])){
            fclose($pipes[2]);
        }
        if($closeProc&&is_resource($handle)){
            proc_terminate($handle);
            proc_close($handle);
        }
    }
    return $info;
}


function trim_input_array($arrName){
    if(empty($arrName)){
        return null;
    }
    $data=input($arrName.'/a',array(),'trim');
    $data=\util\Funcs::array_array_map('trim', $data);
    return $data;
}


function curl_skycaiji($uri,$headers=null,$options=array(),$postData=null,$returnInfo=false){
    $url='://www.skycaiji.com'.$uri;
    $info=get_html('https'.$url,$headers,$options,'utf-8',$postData,true);
    $info=is_array($info)?$info:array();
    if(empty($info['ok'])){
        
        $info=get_html('http'.$url,$headers,$options,'utf-8',$postData,true);
        $info=is_array($info)?$info:array();
    }
    return $returnInfo?$info:$info['html'];
}

function curl_store($providerUrl,$uri,$headers=null,$options=array(),$postData=null){
    $html=null;
    if(empty($providerUrl)){
        
        $html=curl_skycaiji($uri,$headers,$options,$postData);
    }else{
        $html=get_html($providerUrl.$uri,$headers,$options,'utf-8',$postData);
    }
    return $html;
}