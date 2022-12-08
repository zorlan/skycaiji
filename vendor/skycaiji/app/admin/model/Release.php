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

class Release extends \skycaiji\common\model\BaseModel{
    
    public function compatible_config($config){
        if(!is_array($config)){
            $config=unserialize($config?:'');
        }
        init_array($config);
        foreach (config('release_modules') as $v){
            init_array($config[$v]);
        }
        
        if(!empty($config['db_table'])){
            
            $confDbTable=$config['db_table'];
            init_array($confDbTable);
            init_array($confDbTable['field']);
            init_array($confDbTable['custom']);
            $dbTables=array();
            foreach ($confDbTable['field'] as $table=>$field){
                $custom=$confDbTable['custom'][$table];
                init_array($field);
                init_array($custom);
                $sequence=array();
                foreach ($field as $fk=>$fv){
                    if(strcasecmp('custom:',$fv)==0){
                        
                        $fv=$custom[$fk];
                        
                        $fv=preg_replace_callback('/auto_id\@([^\s\#]+)[\#]{0,1}/i',function($mname){
                            $mname=trim($mname[1]);
                            return '[自增主键:'.$mname.']';
                        },$fv);
                        
                        $fv=preg_replace_callback('/sequence\@([^\s]+)/i',function($mname)use(&$sequence,$fk){
                            $mname=trim($mname[1]);
                            $sequence['field']=$fk;
                            $sequence['seq']=$mname;
                            $sequence['trigger']='';
                            return null;
                        },$fv);
                    }elseif(preg_match('/^field\:(.+)$/ui',$fv,$collField)){
                        
                        $fv='[采集字段:'.$collField[1].']';
                    }
                    if(is_null($fv)){
                        unset($field[$fk]);
                    }else{
                        $field[$fk]=$fv;
                    }
                }
                
                $dbTables[]=array(
                    'table'=>$table,
                    'field'=>$field,
                    'sequence'=>$sequence,
                );
            }
            $dbTables=$this->config_db_tables($dbTables);
            unset($config['db_table']);
            $config['db_tables']=$dbTables;
        }
        return $config;
    }
    
    public function config_db_tables($dbTables,$keepIndex=false){
        
        init_array($dbTables);
        foreach ($dbTables as $tbKey=>$dbTable){
            init_array($dbTable['field']);
            
            foreach ($dbTable['field'] as $fk=>$fv){
                if(is_null($fv)||$fv===''){
                    
                    unset($dbTable['field'][$fk]);
                    continue;
                }
            }
            
            $tbWhere=$dbTable['where'];
            init_array($tbWhere);
            \util\Funcs::filter_key_val_list4($tbWhere['field'], $tbWhere['cond'], $tbWhere['logic'], $tbWhere['val']);
            \util\Funcs::filter_key_val_list4($tbWhere['cond'], $tbWhere['field'], $tbWhere['logic'], $tbWhere['val']);
            \util\Funcs::filter_key_val_list4($tbWhere['logic'], $tbWhere['field'], $tbWhere['cond'], $tbWhere['val']);
            $dbTable['where']=$tbWhere;
            
            $tbQuery=$dbTable['query'];
            init_array($tbQuery);
            \util\Funcs::filter_key_val_list3($tbQuery['field'], $tbQuery['type'],  $tbQuery['sign']);
            $dbTable['query']=$tbQuery;
            init_array($dbTable['sequence']);
            $dbTables[$tbKey]=$dbTable;
        }
        if(!$keepIndex){
            
            $dbTables=array_values($dbTables);
        }
        return $dbTables;
    }
    
    
    public function db_tables_query_sign($type,$field,$sign=''){
        if(empty($sign)){
            $sign=($type?($type.'_'):'').$field;
        }
        $sign=strtolower($sign);
        return $sign;
    }
    
    public function db_has_sequence($dbType){
        $dbType=strtolower($dbType?:'');
        if($dbType=='oracle'){
            return true;
        }
        return false;
    }
}

?>