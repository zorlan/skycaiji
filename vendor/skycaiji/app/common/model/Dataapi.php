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

class Dataapi extends BaseModel{
    private static $cond_keys=array('sub','logic','field','op','name');
    private static $cond_ops=array(
        'eq'=>'=（等于）',
        'neq'=>'!=（不等于）',
        'gt'=>'&gt;（大于）',
        'egt'=>'&gt;=（大于等于）',
        'lt'=>'&lt;（小于）',
        'elt'=>'&lt;=（小于等于）',
        'like'=>'like（包含）',
        'nlike'=>'not like（不包含）',
        'in'=>'in（范围：值用,号分隔）',
        'nin'=>'not in（不在范围）',
        'between'=>'between（区间：值用,号分隔）',
        'nbetween'=>'not between（不在区间）',
    );
    private static $cond_sqls=array(
        'eq'=>'=',
        'neq'=>'!=',
        'gt'=>'>',
        'egt'=>'>=',
        'lt'=>'<',
        'elt'=>'<=',
        'like'=>'like',
        'nlike'=>'not like',
        'in'=>'in',
        'nin'=>'not in',
        'between'=>'between',
        'nbetween'=>'not between',
    );
    
    public function getById($id){
        $daData=$this->where('id',$id)->find();
        $daData=$this->get_da_data($daData);
        return $daData;
    }
    public function get_da_data($daData){
        $daData=$daData?$daData->toArray():array();
        if($daData){
            $daData['config']=unserialize($daData['config']);
            init_array($daData['config']);
        }
        return $daData;
    }
    public function get_cond_op($op){
        return $op?self::$cond_ops[$op]:self::$cond_ops;
    }
    public function get_cond_sql($op){
        return self::$cond_sqls[$op]?:'';
    }
    
    public function check_url_param_name($name){
        $result=return_result('');
        if(!preg_match('/^\w+$/i',$name)){
            $result['msg']='参数名只能由字母、数字和下划线组成！';
        }elseif(!preg_match('/^[a-z]/i',$name)){
            $result['msg']='参数名必须是字母开头！';
        }elseif(strlen($name)<=1){
            $result['msg']='参数名最少2个字符';
        }else{
            $result['success']=true;
        }
        return $result;
    }
    
    public function filter_conds($conds){
        init_array($conds);
        foreach (self::$cond_keys as $key){
            init_array($conds[$key]);
        }
        foreach (array('logic','field','op') as $k){
            foreach ($conds[$k] as $kk=>$kv){
                if(empty($kv)){
                    
                    foreach (self::$cond_keys as $key){
                        unset($conds[$key][$kk]);
                    }
                }
            }
        }
        foreach ($conds['name'] as $k=>$v){
            $conds['name'][$k]=empty($v)?'':$v;
        }
        foreach (self::$cond_keys as $key){
            $conds[$key]=array_values($conds[$key]);
        }
        return $conds;
    }
    
    public function get_group_conds($conds){
        $conds=$this->filter_conds($conds);
        $condSubs=array();
        $condIx=-1;
        foreach ($conds['logic'] as $k=>$v){
            $cond=array();
            foreach (self::$cond_keys as $key){
                $cond[$key]=$conds[$key][$k];
            }
            if(empty($cond['name'])){
                $cond['name']=$cond['field'];
            }
            if($cond['sub']){
                
                init_array($condSubs[$condIx]['subs']);
                $condSubs[$condIx]['subs'][]=$cond;
            }else{
                
                $condIx++;
                $condSubs[$condIx]=$cond;
            }
        }
        $condSubs=array_values($condSubs);
        foreach ($condSubs as $k=>$v){
            if($v['subs']){
                
                $vv=$v;
                unset($vv['subs']);
                array_unshift($v['subs'],$vv);
                $v['subs']=$this->_group_conds($v['subs']);
            }
            $condSubs[$k]=$v;
        }
        $condSubs=$this->_group_conds($condSubs);
        return $condSubs;
    }
    private function _group_conds($conds){
        init_array($conds);
        $groups=array();
        $group=array();
        foreach ($conds as $cond){
            if($cond['logic']!='and'){
                
                if($group){
                    $groups[]=$group;
                }
                $group=array();
            }
            $group[]=$cond;
        }
        
        if($group){
            $groups[]=$group;
        }
        return $groups;
    }
    
    public function do_query_conds($pageNo,$config,$dataset,$params){
        init_array($config);
        init_array($params);
        
        $pagePer=intval($config['page_per']);
        $pagePer=$pagePer>0?$pagePer:50;
        
        $groupConds=$this->get_group_conds($config['conds']);
        foreach ($groupConds as $kgc=>$group){
            
            foreach ($group as $kg=>$cond){
                if($cond['subs']){
                    
                    foreach ($cond['subs'] as $kcs=>$subConds){
                        
                        $subConds=$this->_conds_query($subConds,$params);
                        if(empty($subConds)){
                            unset($cond['subs'][$kcs]);
                        }else{
                            $cond['subs'][$kcs]=$subConds;
                        }
                    }
                    $cond=$cond['subs'];
                }else{
                    if(isset($params[$cond['name']])){
                        
                        $cond=$this->_cond_query($cond,$params);
                        $cond['is_cond']=true;
                    }else{
                        
                        $cond=null;
                    }
                }
                if(empty($cond)){
                    
                    $group=null;
                    break;
                }else{
                    $group[$kg]=$cond;
                }
            }
            if(empty($group)){
                unset($groupConds[$kgc]);
            }else{
                $groupConds[$kgc]=$group;
            }
        }
        $dataCount=0;
        $dataData=array();
        if(empty($groupConds)&&empty($config['default_list'])){
            throw new \Exception('没有符合的数据查询条件');
        }else{
            
            $dstDb=DatasetTable::getInstance($dataset['id']);
            $dstDb=$dstDb->db();
            $this->_conds_db($dstDb,$groupConds);
            $dataCount=$dstDb->count();
            if($dataCount>0){
                
                $this->_conds_db($dstDb,$groupConds);
                
                init_array($config['hide_fields']);
                if($config['hide_fields']){
                    $dstDb->field($config['hide_fields'],true);
                }
                if($config['order_field']){
                    $dstDb->order($config['order_field'],$config['order_sort']=='desc'?'desc':'asc');
                }
                $dataData=$dstDb->limit(($pageNo-1)*$pagePer,$pagePer)->select();
                if($dataData){
                    
                    $fields=$dataset['config']?$dataset['config']['fields']:null;
                    init_array($fields);
                    foreach ($fields as $k=>$v){
                        if($k==$v['name']){
                            
                            unset($fields[$k]);
                        }else{
                            $fields[$k]=$v['name'];
                        }
                    }
                    foreach ($dataData as $k=>$v){
                        foreach ($v as $vk=>$vv){
                            if(isset($fields[$vk])){
                                
                                $v[$fields[$vk]]=$vv;
                                unset($v[$vk]);
                            }
                        }
                        $dataData[$k]=$v;
                    }
                }
            }
        }
        return array('count'=>$dataCount,'data'=>$dataData,'pages'=>ceil($dataCount/$pagePer));
    }
    private function _conds_db($dstDb,$groupConds){
        if($groupConds){
            foreach ($groupConds as $group){
                init_array($group);
                if($group){
                    
                    $dstDb->whereOr(function($queryG)use($group){
                        foreach ($group as $cond){
                            init_array($cond);
                            if($cond){
                                
                                $queryG->where(function($queryC)use($cond){
                                    if($cond['is_cond']){
                                        
                                        $queryC->where($cond[0],$cond[1],$cond[2]);
                                    }else{
                                        
                                        foreach ($cond as $condSubs){
                                            init_array($condSubs);
                                            if($condSubs){
                                                
                                                $queryC->whereOr(function($queryCs)use($condSubs){
                                                    
                                                    foreach ($condSubs as $condSub){
                                                        $queryCs->where($condSub[0],$condSub[1],$condSub[2]);
                                                    }
                                                });
                                            }
                                        }
                                    }
                                });
                            }
                        }
                    });
                }
            }
        }
    }
    private function _conds_query($conds,$params){
        init_array($conds);
        foreach ($conds as $k=>$cond){
            if(isset($params[$cond['name']])){
                
                $conds[$k]=$this->_cond_query($cond,$params);
            }else{
                
                $conds=null;
                break;
            }
        }
        return $conds;
    }
    private function _cond_query($cond,$params){
        $val=$params[$cond['name']];
        if($cond['op']==='like'||$cond['op']==='nlike'){
            if(strpos($val,'%')===false&&!is_empty($val,true)){
                
                $val='%'.$val.'%';
            }
        }
        return array($cond['field'],$this->get_cond_sql($cond['op']),$val);
    }
}
?>