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

namespace skycaiji\common\model;

class Dataset extends BaseModel{
    public function getById($id){
        $data=$this->where('id',$id)->find();
        $data=$this->get_ds_data($data);
        return $data;
    }
    public function get_ds_data($dsData){
        $dsData=$dsData?$dsData->toArray():array();
        if($dsData){
            $dsData['config']=unserialize($dsData['config']);
            init_array($dsData['config']);
        }
        return $dsData;
    }
    public function get_config_field($dsData,$fname){
        $field=array();
        if($dsData['config']&&$dsData['config']['fields']){
            $field=$dsData['config']['fields'][$fname];
        }
        init_array($field);
        return $field;
    }
    
    public function check_field_name($name){
        $result=return_result('');
        if(empty($name)){
            $result['msg']='字段名称不能为空！';
        }elseif(strcasecmp($name,'id')===0){
            $result['msg']='字段名称不能设为id';
        }elseif(!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)){
            $result['msg']='字段名称只能由汉字、字母、数字和下划线组成';
        }elseif(strlen($name)<=1){
            $result['msg']='字段名称最少2个字符';
        }else{
            $result['success']=true;
        }
        return $result;
    }
    
    public function check_field_type($type){
        $result=return_result('');
        static $types=array('bigint','double','varchar','mediumtext','datetime');
        if(!in_array($type, $types)){
            $result['msg']='请选择数据类型';
        }else{
            $result['success']=true;
        }
        return $result;
    }
    
    public function filter_fields($fields){
        init_array($fields);
        $newFields=array();
        foreach ($fields as $v){
            init_array($v);
            $check=$this->check_field_name($v['name']);
            if($check['success']){
                $check=$this->check_field_type($v['type']);
                if($check['success']){
                    $key=$this->field_db_name($v['name']);
                    $newFields[$key]=$v;
                }
            }
        }
        return $newFields;
    }
    
    public static function field_db_name($name){
        $name=strtolower($name);
        if(!preg_match('/^[a-z]\w{0,30}$/i',$name)){
            
            $md5=md5($name);
            $name='';
            if(preg_match('/[a-z]/i',$md5,$mfirst)){
                $name=$mfirst[0];
            }
            $name=$name?:'a';
            $name.=substr($md5,-4,4);
        }
        $name=strtolower($name);
        return $name;
    }
}
?>