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

 namespace Admin\Event; use Admin\Model\CacheModel; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class RapiEvent extends ReleaseEvent{ public function setConfig($config){ $api=I('api/a'); $api['url']=trim($api['url'],'\/\\'); $api['cache_time']=intval($api['cache_time']); if(empty($api['url'])){ $this->error('请输入api地址'); } if(!preg_match('/^[a-zA-Z0-9\-\_]+$/i', $api['url'])){ $this->error('api地址只能由字母、数字、下划线组成'); } $config['api']=$api; return $config; } public function export($collFieldsList,$options=null){ if(empty($collFieldsList)){ $collFieldsList=array(); } $this->set_cache_fields($collFieldsList); $this->ajaxReturn($collFieldsList); } public function set_cache_fields($collFieldsList){ if(!empty($this->config['api']['cache_time'])){ $mcache=new CacheModel('task_api'); if($mcache->expire($this->release['task_id'],$this->config['api']['cache_time']*60)){ $mcache->set($this->release['task_id'], $collFieldsList); } } } public function get_cache_fields(){ if(!empty($this->config['api']['cache_time'])){ $mcache=new CacheModel('task_api'); if(!$mcache->expire($this->release['task_id'],$this->config['api']['cache_time']*60)){ $data=$mcache->get($this->release['task_id'],'data'); return empty($data)?array():$data; }else{ return false; } }else{ return false; } } } ?>