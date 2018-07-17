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

namespace Admin\Model; class ReleaseModel extends BaseModel{ public function getByTaskid($taskId){ static $dataList=array(); if(empty($taskId)){ return null; } if(!isset($dataList[$taskId])){ $dataList[$taskId]=$this->where(array('task_id'=>$taskId))->find(); } return $dataList[$taskId]; } } ?>