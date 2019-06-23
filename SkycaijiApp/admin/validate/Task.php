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

namespace skycaiji\admin\validate;
use think\Validate;
class Task extends Validate{
	protected $rule = [
		'name'=>'require|unique:task',
	];
	
	protected $message = [
		'name.require'=>'{%task_error_null_name}',
		'name.unique'=>'{%task_error_has_name}',
	];
	
	protected $scene = [
		'add'=>['name'],
        'edit'=>['name'=>'require'],
	];
}

?>