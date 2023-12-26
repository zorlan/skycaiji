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
 
namespace util;

class EncryptDecrypt{
    protected $version='';
    protected $skycaiji='';
    public function __construct($version=null,$skycaiji=null){
        $this->version=$version?$version:'1';
        $this->skycaiji=$skycaiji?$skycaiji:SKYCAIJI_VERSION;
    }
    protected function checkError($isEncrypt,$method){
        $name=$isEncrypt?'加密':'解密';
        if(!extension_loaded('openssl')){
            throw new \Exception($name.'需要php开启openssl扩展');
        }
        if(!method_exists($this,$method)){
            throw new \Exception($name.'失败！请使用 v'.$this->skycaiji.' 及以上版本的采集器');
        }
    }
    
    public function encrypt($params){
        $method='encrypt_v'.$this->version;
        $this->checkError(true, $method);
        if(!is_array($params)){
            $params=array();
        }
        $data=$this->$method($params);
        return array(
            'encrypt_version'=>$this->version,
            'skycaiji_version'=>$this->skycaiji,
            'data'=>$data
        );
    }
    protected function encrypt_v1($params){
        return openssl_encrypt($params['data'], 'AES-256-CBC', $params['pwd'],0,'skycaiji');
    }
    
    public function decrypt($params){
        $method='decrypt_v'.$this->version;
        $this->checkError(false, $method);
        if(!is_array($params)){
            $params=array();
        }
        return $this->$method($params);
    }
    protected function decrypt_v1($params){
        return openssl_decrypt($params['data'], 'AES-256-CBC', $params['pwd'],0,'skycaiji');
    }
}

?>