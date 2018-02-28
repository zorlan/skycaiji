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

namespace Admin\Model; use Think\Model; class UsergroupModel extends BaseModel{ public function get_sub_level($groupid){ $group=$this->getById($groupid); if(empty($group)){ return null; } return $this->where(array('level'=>array('LT',$group['level'])))->select(); } public function user_level_limit($level){ if($GLOBALS['user']['group']['level']<=$level){ return true; }else{ return false; } } } ?>