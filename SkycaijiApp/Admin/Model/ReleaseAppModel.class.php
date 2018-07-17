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

namespace Admin\Model; class ReleaseAppModel extends BaseModel{ protected $tableName='release_app'; public function addCms($cms,$code='',$tpl=''){ if(empty($cms['app'])){ return false; } $cms['module']='cms'; $cms['uptime']=$cms['uptime']>0?$cms['uptime']:NOW_TIME; $cmsData=$this->where(array('module'=>'cms','app'=>$cms['app']))->find(); $success=false; if(!empty($cmsData)){ $this->where(array('module'=>'cms','app'=>$cms['app']))->save($cms); $success=true; }else{ $cms['addtime']=NOW_TIME; $success=$this->add($cms); } if($success){ $cmsAppPath=C('ROOTPATH').'/'.APP_PATH.'Release/'; if(!empty($code)){ file_put_contents($cmsAppPath.'Cms/'.ucfirst($cms['app']).'Cms.class.php', $code); } if(!empty($tpl)){ file_put_contents($cmsAppPath.'View/Cms/'.ucfirst($cms['app']).'Cms.html', $tpl); } } return $success; } } ?>