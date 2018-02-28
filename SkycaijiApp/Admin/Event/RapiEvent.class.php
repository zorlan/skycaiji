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

 namespace Admin\Event; use Think\Controller; use Admin\Controller\BaseController; use Admin\Model\CollectedModel; use Think\Storage; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class RapiEvent extends ReleaseEvent{ public function setConfig($config){ $api=I('api/a'); $api['url']=trim($api['url'],'\/\\'); if(empty($api['url'])){ $this->error('请输入api地址'); } if(!preg_match('/^[a-zA-Z0-9\-\_]+$/i', $api['url'])){ $this->error('api地址只能由字母、数字、下划线组成'); } $config['api']=$api; return $config; } public function export($collFieldsList,$options=null){ if(empty($collFieldsList)){ $collFieldsList=array(); } $this->ajaxReturn($collFieldsList); } } ?>