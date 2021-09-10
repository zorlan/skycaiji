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
class Provider extends \skycaiji\common\model\BaseModel{
	
    public static function match_domain_http($url){
		$domain='';
		if(preg_match('/^\w+\:\/\/[\w\-]+(\.[\w\-]+)*(\:\d+){0,1}/', $url,$domain)){
			$domain=strtolower($domain[0]);
		}else{
			$domain='';
		}
		return $domain?$domain:'';
	}
	
	public static function match_domain_name($url){
	    $domain='';
	    if(preg_match('/^\w+\:\/\/(?P<domain>[\w\-]+(\.[\w\-]+)*)(\:\d+){0,1}/', $url,$domain)){
	        $domain=strtolower($domain['domain']);
	    }else{
	        $domain='';
	    }
	    return $domain?$domain:'';
	}
	
	
	public static function is_official_url($url){
	    $domain=self::match_domain_http($url);
	    if(!empty($domain)&&in_array($domain,config('allow_origins'))){
	        
	        return true;
	    }else{
	        return false;
	    }
	}
	
	
	public static function create_store_url($url,$urlPath,$urlParams=null){
	    if(empty($url)){
	        
	        $url='https://www.skycaiji.com';
	    }
	    $urlParams=$urlParams?('?'.http_build_query($urlParams)):'';
	    
	    $url=rtrim($url,'/').'/'.$urlPath.$urlParams;
	    return $url;
	}
	
	/*获取id*/
	public function getIdByUrl($url){
	    $domain=self::match_domain_http($url);
		if(self::is_official_url($domain)){
			
		    $domain=null;
		}
		$id=0;
		if(!empty($domain)){
		    $id=$this->where('domain',$domain)->value('id');
			$id=intval($id);
		}
		return $id;
	}
	
	public function getAuthkey($provData){
	    $authkey='';
	    if(!empty($provData)){
	        
	        $authkey=$provData['authkey'];
	    }else{
	        
	        $authkey=g_sc_c('store','authkey_store');
	    }
	    if(empty($authkey)){
	        
	        $authkey=g_sc_c('store','authkey');
	    }
	    return $authkey?$authkey:'';
	}
	
	public function getStoreUrl($provData){
	    $url='';
	    if(!empty($provData)){
	        
	        $url=$provData['url'];
	    }else{
	        
	        $url=self::create_store_url(null,'store');
	    }
	    return $url;
	}
	
	public function createAuthsign($authkey,$clientUrl,$storeUrl,$timestamp){
	    $data=array(
	        'authkey'=>$authkey?md5($authkey):'',
	        'client_domain'=>self::match_domain_name($clientUrl),
	        'store_domain'=>self::match_domain_name($storeUrl),
	        'timestamp'=>$timestamp,
	    );
	    ksort($data);
	    $data=md5(http_build_query($data));
	    return $data;
	}
	
	public function checkData($provData){
	    $result=array('success'=>false,'msg'=>'');
	    if(empty($provData)){
	        $result['msg']='未知的第三方平台';
	    }elseif(empty($provData['enable'])){
	        $result['msg']='未受信任的第三方平台：'.$provData['url'];
	    }else{
	        $result['success']=true;
	    }
	    return $result;
	}
	
	
	public function checkAuthkey($authkey,$sameAsPwd=false){
	    $result=array('success'=>false,'msg'=>'','data'=>array());
	    $authkey=$authkey?$authkey:'';
	    
	    if(!empty($authkey)&&!preg_match('/^[a-zA-Z0-9]{6,100}$/i', $authkey)){
            
            $result['msg']=lang('store_authkey_error');
        }else{
            
            $result['success']=true;
            if(!$sameAsPwd){
                
                $userData=g_sc('user');
                if($userData['password']==\skycaiji\admin\model\User::pwd_encrypt($authkey,$userData['salt'])){
                    $result['success']=false;
                    $result['data']['same_as_pwd']='检测到通信密钥与登录密码一致，这容易导致密码泄露，确定设置为该值？';
                }
            }
        }
	    return $result;
	}
	
	
	public function storeAuthResult(){
	    $storeUrl=input('store_url','','trim');
	    $authsign=input('authsign','','trim');
	    $timestamp=input('timestamp/d',0);
	    
	    $result=array('success'=>false,'msg'=>'','data'=>array());
	    
	    if(empty($storeUrl)){
	        
	        $storeUrl=request()->server('HTTP_REFERER');
	    }
	    $storeUrl=$storeUrl?$storeUrl:'';
	    
	    $provData=null;
	    $provId=$this->getIdByUrl($storeUrl);
	    if($provId>0){
	        
	        $provData=$this->getById($provId);
	        $provData=empty($provData)?array():$provData->toArray();
	        $check=$this->checkData($provData);
	        if(!$check['success']){
	            $result['msg']=$check['msg'];
	            return $result;
	        }
	    }
	    if(empty($provData)&&!self::is_official_url($storeUrl)){
	        
	        $result['msg']='未知的第三方来源：'.$storeUrl;
	        return $result;
	    }
	    
	    $authkey=$this->getAuthkey($provData);
	    
	    $clientinfo=clientinfo();
	    $clientSign=$this->createAuthsign($authkey,$clientinfo['url'],$storeUrl,$timestamp);
	    if($clientSign!=$authsign){
	        
	        $msg='<div style="font-weight:normal;">验证失败，客户端的<a href="'.url('setting/store','',true,true).'" target="_blank" style="font-weight:bold;">通信密钥</a>与';
	        $msg.='<a href="'.self::create_store_url($provData?$provData['url']:null,'client/go/authkey',array('clientinfo'=>g_sc('clientinfo'))).'" target="_blank" style="font-weight:bold;">';
	        $msg.=($provData?'第三方':'云').'平台</a>中的不一致</div>';
	        $result['msg']=$msg;
	        return $result;
	    }else{
	        
	        $nowTime=time();
	        if(abs($nowTime-$timestamp)>1000){
	            $result['msg']=sprintf('连接超时，请校对时间<br>平台端请求：%s<br>客户端响应：%s',date('Y-m-d H:i:s',$timestamp),date('Y-m-d H:i:s',$nowTime));
	            return $result;
	        }
	    }
	    
	    $result['success']=true;
	    $result['data']['provider_id']=$provId;
	    
	    return $result;
	}
}

?>