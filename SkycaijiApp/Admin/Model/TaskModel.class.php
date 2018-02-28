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

namespace Admin\Model; use Think\Model; class TaskModel extends BaseModel{ protected $_validate = array( array('name','require','{%task_error_null_name}'), array('name','','{%task_error_has_name}',0,'unique',1), ); } ?>