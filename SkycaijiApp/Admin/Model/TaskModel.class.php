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

namespace Admin\Model; class TaskModel extends BaseModel{ protected $_validate = array( array('name','require','{%task_error_null_name}'), array('name','','{%task_error_has_name}',0,'unique',1), ); public function loadConfig($config){ if(!is_array($config)){ $config=unserialize($config); } if(empty($config)){ return; } if(!empty($GLOBALS['config']['caiji']['download_img'])){ if($config['download_img']=='n'){ $GLOBALS['config']['caiji']['download_img']=0; } } if(!empty($GLOBALS['config']['proxy']['open'])){ if($config['proxy']=='n'){ $GLOBALS['config']['proxy']['open']=0; } } } } ?>