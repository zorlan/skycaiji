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

class Collector extends BaseModel{
	
	public function add_new($data){
		$data['addtime']=NOW_TIME;
		$data['uptime']=NOW_TIME;
		$this->isUpdate(false)->allowField(true)->save($data);
		return $this->id;
	}
	
	public function edit_by_id($id,$data){
		unset($data['addtime']);
		$data['uptime']=NOW_TIME;
		
		$this->strict(false)->where(array('id'=>$id))->update($data);
	}
	
}

?>