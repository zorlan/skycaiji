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

namespace Admin\Event; use Admin\Event\ReleaseBaseEvent; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } abstract class ReleaseEvent extends ReleaseBaseEvent { public $release; public $config; public $task; public function init($release){ if(!is_array($release['config'])){ $release['config']=unserialize($release['config']); } $this->release=$release; $this->config=$release['config']; $this->task=D('Task')->getById($this->release['task_id']); if(empty($this->task)){ $this->error(L('task_error_empty_task')); } } public abstract function setConfig($config); public abstract function export($collFieldsList,$options=null); public function get_coll_fields($taskId,$taskModule){ static $fieldsList=array(); $key=$taskId.'__'.$taskModule; if(!isset($fieldsList[$key])){ $mcoll=D('Collector'); $collData=$mcoll->where(array('task_id'=>$taskId,'module'=>$taskModule))->find(); $collData['config']=unserialize($collData['config']); $collFields=array(); foreach ($collData['config']['field_list'] as $collField){ $collFields[]=$collField['name']; } $fieldsList[$key]=$collFields; } return $fieldsList[$key]; } } ?>