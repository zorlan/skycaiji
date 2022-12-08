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

namespace skycaiji\admin\model;
class ProxyGroup extends \skycaiji\common\model\BaseModel{
    public function getAll($cond=null,$order='sort desc'){
        $list=$this->where($cond)->order($order)->column('*');
        init_array($list);
        return $list;
    }
    public function getNameById($id){
        $name=$this->where('id',$id)->column('name','id');
        $name=$name[$id];
        $name=$name?:'';
        return $name;
    }
}
?>