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

namespace Admin\Model; class CollectorModel extends BaseModel{ public function add_new($data){ $data['addtime']=NOW_TIME; $data['uptime']=NOW_TIME; return $this->add($data); } public function edit_by_id($id,$data){ unset($data['addtime']); $data['uptime']=NOW_TIME; $this->where(array('id'=>$id))->save($data); } } ?>