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

namespace skycaiji\admin\event;
use skycaiji\admin\model\DbCommon;
class Rdb extends Release{
    protected $db_conn_list=array();
    /**
     * 设置页面post过来的config
     * @param unknown $config
     */
    public function setConfig($config){
        $db=input('db/a',array(),'trim');
        foreach ($db as $k=>$v){
            if(empty($v)&&'pwd'!=$k){
                
                $this->error(lang('error_null_input',array('str'=>lang('rele_db_'.$k))));
            }
        }
        $config['db']=$db;
        $dbTables=trim_input_array('db_tables');
        $dbTables=model('Release')->config_db_tables($dbTables);
        $config['db_tables']=$dbTables;
        return $config;
    }
    
    private function _convert_val_signs($val,$charset,&$collFields,&$querySigns,&$autoIds){
        
        $error='';
        $val=preg_replace_callback('/\[([\x{4e00}-\x{9fa5}]+)\:(.*?)\]/u',function($match)use(&$error,&$collFields,&$querySigns,&$autoIds){
            $type=$match[1];
            $name=$match[2];
            $name=$name?trim($name):'';
            $returnVal='';
            if($type=='采集字段'){
                $returnVal=$this->get_field_val($collFields[$name]);
                $returnVal=is_null($returnVal)?'':$returnVal;
            }elseif($type=='查询'){
                $name=strtolower($name);
                $returnVal=$querySigns[$name];
                $returnVal=is_null($returnVal)?'':$returnVal;
            }elseif($type=='自增主键'){
                $name=strtolower($name);
                $returnVal=$autoIds[$name];
                $returnVal=$returnVal?:'';
                if(!$returnVal){
                    $error='没有自增主键'.$name;
                }
            }else{
                
                $returnVal=$match[0];
            }
            return $returnVal;
        }, $val);
        if($error){
            
            throw new \Exception($error);
        }
        if(!empty($charset)){
            
            $val=$this->utf8_to_charset($charset, $val);
        }
        return $val;
    }
    
    /*导出数据*/
    public function export($collFieldsList,$options=null){
        
        $db_config=$this->get_db_config($this->config['db']);
        $db_config['fields_strict']=false;
        $db_key=md5(serialize($db_config));
        if(empty($this->db_conn_list[$db_key])){
            
            $mdb=new DbCommon($db_config);
            $mdb=$mdb->db();
            $this->db_conn_list[$db_key]=$mdb;
        }else{
            $mdb=$this->db_conn_list[$db_key];
        }
        
        $addedNum=0;
        
        $dbCharset=strtolower($db_config['db_charset']);
        if(empty($dbCharset)||$dbCharset=='utf-8'||$dbCharset=='utf8'){
            
            $dbCharset=null;
        }
        $mrele=model('Release');
        static $whereCondStrs=array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','like'=>'like','nlike'=>'not like','in'=>'in','nin'=>'not in','between'=>'between','nbetween'=>'not between');
        static $queryTypeStrs=array('','max','min','count','sum','avg');
        static $mvalConds=array('in','nin','between','nbetween');
        
        $dbHasSeq=$mrele->db_has_sequence($db_config['db_type']);
        
        foreach ($collFieldsList as $collFieldsKey=>$collFields){
            
            $autoIds=array();
            $querySigns=array();
            
            $insertTables=array();
            $updateTables=array();
            
            $contTitle=$collFields['title'];
            $contUrl=$collFields['url'];
            $collFields=$collFields['fields'];
            $this->init_download_config($this->task,$collFields);
            
            $dbTables=$this->config['db_tables'];
            $errorMsg=false;
            $mdb->startTrans();
            foreach ($dbTables as $tbKey=>$dbTable){
                $table=$dbTable['table']?:'';
                $table=strtolower($table);
                try{
                    if(!$table){
                        continue;
                    }
                    $sqlWhereList=array();
                    if(!empty($dbTable['op'])){
                        
                        $tbWhere=$dbTable['where'];
                        foreach ($tbWhere['logic'] as $k=>$v){
                            if($k===0){
                                
                                $v='and';
                            }
                            $v=$v?:'and';
                            if($whereCondStrs[$tbWhere['cond'][$k]]){
                                
                                $whereVal=$tbWhere['val'][$k];
                                
                                $whereVal=$this->_convert_val_signs($whereVal,$dbCharset,$collFields,$querySigns,$autoIds);
                                if(in_array($tbWhere['cond'][$k],$mvalConds)){
                                    
                                    $whereVal=explode(',',$whereVal);
                                }
                                $sqlWhereList[]=array($v,$tbWhere['field'][$k],$whereCondStrs[$tbWhere['cond'][$k]],$whereVal);
                            }
                        }
                    }
                    if($dbTable['op']=='query'){
                        
                        $tbQuery=$dbTable['query'];
                        foreach ($tbQuery['type'] as $k=>$v){
                            $v=$v?:'';
                            if(in_array($v, $queryTypeStrs)){
                                $v=$v?($v.'('.$tbQuery['field'][$k].')'):$tbQuery['field'][$k];
                                $mdb=$mdb->table($table)->field($v.' as qval');
                                if($sqlWhereList){
                                    foreach ($sqlWhereList as $sqlWhere){
                                        if($sqlWhere[0]=='or'){
                                            $mdb=$mdb->whereOr($sqlWhere[1],$sqlWhere[2],$sqlWhere[3]);
                                        }else{
                                            $mdb=$mdb->where($sqlWhere[1],$sqlWhere[2],$sqlWhere[3]);
                                        }
                                    }
                                }
                                $v=$mdb->find();
                                $v=is_array($v)?$v['qval']:'';
                                $v=is_null($v)?'':$v;
                                $k=$mrele->db_tables_query_sign($tbQuery['type'][$k],$tbQuery['field'][$k],$tbQuery['sign'][$k]);
                                if($k){
                                    $v=$this->charset_to_utf8($dbCharset, $v);
                                    $querySigns[$k]=$v;
                                }
                            }
                        }
                    }elseif(empty($dbTable['op'])||$dbTable['op']=='update'){
                        
                        $sequenceName='';
                        $tbField=$dbTable['field'];
                        foreach ($tbField as $k=>$v){
                            $v=$this->_convert_val_signs($v,$dbCharset,$collFields,$querySigns,$autoIds);
                            $tbField[$k]=$v;
                        }
                        if($dbHasSeq){
                            
                            $tbSeq=$dbTable['sequence'];
                            $sequenceName=$tbSeq['seq'];
                            if($sequenceName&&$tbSeq['field']&&!$tbSeq['trigger']){
                                
                                $tbField[$tbSeq['field']]='#sequence:'.$sequenceName.'#';
                            }
                        }
                        
                        if(empty($tbField)){
                            $this->echo_msg('表'.$table.'字段必须绑定数据','orange');
                        }else{
                            if(empty($dbTable['op'])){
                                
                                $status=$mdb->table($table)->insert($tbField);
                                if($status>0){
                                    $insertTables[]=$table;
                                    if($dbHasSeq){
                                        
                                        $autoIds[$table]=$mdb->getLastInsID($sequenceName);
                                    }else{
                                        $autoIds[$table]=$mdb->getLastInsID();
                                    }
                                }else{
                                    
                                    throw new \Exception('新增失败');
                                }
                            }elseif($dbTable['op']=='update'){
                                
                                if(empty($sqlWhereList)){
                                    
                                    $this->echo_msg('表'.$table.'更新必须添加条件','orange');
                                }else{
                                    foreach ($sqlWhereList as $sqlWhere){
                                        if($sqlWhere[0]=='or'){
                                            $mdb=$mdb->whereOr($sqlWhere[1],$sqlWhere[2],$sqlWhere[3]);
                                        }else{
                                            $mdb=$mdb->where($sqlWhere[1],$sqlWhere[2],$sqlWhere[3]);
                                        }
                                    }
                                    $status=$mdb->table($table)->update($tbField);
                                    if($status>0){
                                        $updateTables[]=$table;
                                    }else{
                                        
                                        $this->echo_msg('表'.$table.'更新失败','orange');
                                    }
                                }
                            }
                        }
                    }
                }catch (\Exception $ex){
                    $errorMsg=$ex->getMessage();
                    $errorTbOp='';
                    switch ($dbTable['op']){
                        case 'update':$errorTbOp='更新';break;
                        case 'query':$errorTbOp='查询';break;
                        default:$errorTbOp='新增';break;
                    }
                    $errorTbOp='表'.$table.$errorTbOp.'：';
                    $errorMsg=$errorTbOp.($errorMsg?$errorMsg:'数据库操作失败');
                    break;
                }
            }
            
            $returnData=array('id'=>0);
            if(!empty($errorMsg)){
                
                $mdb->rollback();
                $returnData['error']=$errorMsg;
            }else{
                
                $mdb->commit();
                $firstTable='';
                $firstId=0;
                $firstOp='';
                if(count($insertTables)>0){
                    
                    $firstTable=reset($insertTables);
                    $firstId=intval($autoIds[$firstTable]);
                    $firstOp='新增';
                }elseif(count($updateTables)>0){
                    
                    $firstTable=reset($updateTables);
                    $firstOp='更新';
                }
                if($firstTable){
                    
                    $addedNum++;
                    $returnData['target']="{$db_config['db_type']}:{$db_config['db_name']}@table:{$firstTable}";
                    if($firstId>0){
                        $returnData['id']=$firstId;
                        $returnData['target'].="@id:{$firstId}";
                    }else{
                        $returnData['id']=1;
                        $returnData['target'].="@".$firstOp;
                    }
                }else{
                    $returnData['error']='没有成功的新增或更新操作';
                }
            }
            $this->record_collected($contUrl,$returnData,$this->release,$contTitle);
            
            unset($collFieldsList[$collFieldsKey]['fields']);
        }
        return $addedNum;
    }
    
    /*将发布配置中的数据库参数转换成tp数据库参数*/
    public function get_db_config($config_db){
        $db_config=array(
            'db_type'  => strtolower($config_db['type']),
            'db_user'  => $config_db['user'],
            'db_pwd'   => $config_db['pwd'],
            'db_host'  => $config_db['host'],
            'db_port'  => $config_db['port'],
            'db_charset'  => $config_db['charset'],
            'db_name'  => $config_db['name'],
            
        );
        
        if(strcasecmp($db_config['db_charset'], 'utf-8')===0){
            $db_config['db_charset']='utf8';
        }
        
        if('mysqli'==$db_config['db_type']){
            
            $db_config['db_type']='mysql';
        }elseif('oracle'==$db_config['db_type']){
            
            $db_config['db_dsn']="oci:host={$db_config['db_host']};dbname={$db_config['db_name']};charset={$db_config['db_charset']}";
        }elseif('sqlsrv'==$db_config['db_type']){
            
            $db_config['db_dsn']='sqlsrv:Database='.$db_config['db_name'].';Server='.$db_config['db_host'];
        }
        return $db_config;
    }
}
?>