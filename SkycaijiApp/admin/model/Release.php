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

namespace skycaiji\admin\model;

class Release extends BaseModel{
	public function getByTaskid($taskId){
		static $dataList=array();
		if(empty($taskId)){
			return null;
		}
		if(!isset($dataList[$taskId])){
			$taskData=$this->where(array('task_id'=>$taskId))->find();
			if(!empty($taskData)){
				$dataList[$taskId]=$taskData->toArray();
			}else{
				$dataList[$taskId]=array();
			}
		}
		return $dataList[$taskId];
	}
}

?>