<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

namespace Admin\Model; use Think\Model; class ConfigModel extends BaseModel{ public function convertData($configItem){ switch($configItem['ctype']){ case 1:$configItem['data']=intval($configItem['data']);break; case 2:$configItem['data']=unserialize($configItem['data']);break; } return $configItem; } public function get($cname,$key=null){ static $dataList=array(); if(!isset($dataList[$cname])){ $item=$this->where("`cname`='%s'",$cname)->find(); $item=$this->convertData($item); $dataList[$cname]=$item; } return $key?$dataList[$cname][$key]:$dataList[$cname]; } public function set($cname,$value){ $data=array('cname'=>$cname,'ctype'=>0); if(is_array($value)){ $data['ctype']=2; $data['data']=serialize($value); }elseif(is_integer($value)){ $data['ctype']=1; $data['data']=intval($value); }else{ $data['data']=$value; } $data['dateline']=NOW_TIME; $this->add($data,$options=array(),true); } public function setVersion($version){ $version=trim(strtoupper($version),'V'); $this->set('version', $version); } } ?>