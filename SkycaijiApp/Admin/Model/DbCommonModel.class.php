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

namespace Admin\Model; class DbCommonModel extends BaseModel{ public $name=''; public function __construct($name='',$tablePrefix='',$connection='') { parent::__construct($name,$tablePrefix,$connection); } public function getModelName(){ return ''; } } ?>