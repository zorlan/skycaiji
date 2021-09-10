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

namespace skycaiji\common\model;

class Config extends BaseModel {
    protected $pk = 'cname';
    
    /*转换数据*/
    public function convertData($configItem){
        if(!empty($configItem)){
            switch($configItem['ctype']){
                case 1:$configItem['data']=intval($configItem['data']);break;
                case 2:$configItem['data']=unserialize($configItem['data']);break;
            }
        }
        return $configItem;
    }
    /**
     * 获取
     * @param string $cname 名称
     * @param string $key 数据键名
     * @return mixed
     */
    public function getConfig($cname,$key=null){
        
        $item=$this->where('cname',$cname)->find();
        if(!empty($item)){
            $item=$item->toArray();
            $item=$this->convertData($item);
        }else{
            $item=array();
        }
        return $key?$item[$key]:$item;
    }
    /**
     * 设置
     * @param string $cname 名称
     * @param string $value 数据
     */
    public function setConfig($cname,$value){
        $data=array('cname'=>$cname,'ctype'=>0);
        if(is_array($value)){
            $data['ctype']=2;
            $data['data']=serialize($value);
        }elseif(is_integer($value)){
            $data['ctype']=1;
            $data['data']=intval($value);
        }else{
            $data['data']=$value;
        }
        $data['dateline']=time();
        $this->insert($data,true);
        
        
        $this->cacheConfigList();
    }
    /*缓存所有配置*/
    public function cacheConfigList(){
        static $arrKeys=array('caiji','download_img','page_render','proxy','translate','site','email');
        
        $keyConfig='cache_config_all';
        $configDbList=$this->column('*');
        $configDbList=empty($configDbList)?array():$configDbList;
        $configList=array();
        foreach ($configDbList as $configItem){
            $configItem=$this->convertData($configItem);
            $configList[$configItem['cname']]=$configItem['data'];
        }
        
        foreach ($arrKeys as $k){
            if(!is_array($configList[$k])){
                
                $configList[$k]=array();
            }
        }
        
        cache($keyConfig,array('list'=>$configList));
    }
    /*获取数据库的版本*/
    public function getVersion(){
        $dbVersion=$this->where("`cname`='version'")->find();
        if(!empty($dbVersion)){
            $dbVersion=$this->convertData($dbVersion);
            $dbVersion=$dbVersion['data'];
        }
        return $dbVersion;
    }
    /*设置版本号*/
    public function setVersion($version){
        $version=trim(strtoupper($version),'V');
        $this->setConfig('version', $version);
    }
    /*设置验证码状态*/
    public function setVerifycode($open){
        $siteConfig=$this->getConfig('site','data');
        $siteConfig=is_array($siteConfig)?$siteConfig:array();
        $siteConfig['verifycode']=$open?1:0;
        $this->setConfig('site', $siteConfig);
    }
    
    /*设置url兼容模式*/
    public static function set_url_compatible(){
        \think\Url::root(config('root_url').'/index.php?s=');
        define('URL_IS_COMPATIBLE', true);
    }
}
?>