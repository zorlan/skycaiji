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


namespace util;
class Db{
    
    public static function table_engine($table){
        $tableEngine='';
        $tableInfo=db()->query("SHOW TABLE STATUS LIKE '{$table}'");
        if($tableInfo){
            $tableInfo=$tableInfo[0];
            if($tableInfo){
                $tableInfo=\util\Funcs::array_keys_to_lower($tableInfo);
                $tableEngine=$tableInfo['engine'];
            }
        }
        $tableEngine=$tableEngine?strtolower($tableEngine):'';
        return $tableEngine;
    }
    
    
    public static function to_innodb($dbTable){
        static $dbVer=null;
        if(!isset($dbVer)){
            $dbVer=db()->query('SELECT VERSION() as v;');
            $dbVer=$dbVer[0]?$dbVer[0]['v']:'';
        }
        $allowFulltext=false;
        if(version_compare($dbVer,'5.6','>=')){
            
            $allowFulltext=true;
        }
        
        $tableEngine=\util\Db::table_engine($dbTable);
        if($tableEngine!='innodb'){
            $exeSqls=array();
            
            $tableColumns=db()->query("SHOW COLUMNS FROM `{$dbTable}`");
            $fieldLens=array();
            foreach($tableColumns as $tableColumn){
                $tableColumn=\util\Funcs::array_keys_to_lower($tableColumn);
                $fieldLens[$tableColumn['field']]=0;
                if(preg_match('/\(\s*(\d+)\s*\)/',$tableColumn['type'],$mlen)){
                    $fieldLens[$tableColumn['field']]=intval($mlen[1]);
                }
            }
            
            $tableIndexes=db()->query("SHOW INDEX FROM `{$dbTable}`");
            $indexList=array();
            foreach($tableIndexes as $tableIndex){
                $tableIndex=\util\Funcs::array_keys_to_lower($tableIndex);
                init_array($indexList[$tableIndex['key_name']]);
                $indexList[$tableIndex['key_name']][$tableIndex['seq_in_index']]=$tableIndex;
                ksort($indexList[$tableIndex['key_name']]);
            }
            
            foreach($indexList as $ixName=>$ixIndexes){
                $ixFulltext=false;
                $ixNew=false;
                $ixUnique=false;
                $ixColumns=array();
                foreach($ixIndexes as $ixIndex){
                    $ixColumns[]=$ixIndex['column_name'];
                    if(!$ixNew&&($ixIndex['sub_part']>191||(empty($ixIndex['sub_part'])&&$fieldLens[$ixIndex['column_name']]>191))){
                        
                        $ixNew=true;
                    }
                    if(empty($ixIndex['non_unique'])){
                        
                        $ixUnique=true;
                    }
                    if(strtolower($ixIndex['index_type'])=='fulltext'){
                        $ixFulltext=true;
                    }
                }
                if($ixFulltext){
                    
                    if(!$allowFulltext){
                        
                        db()->execute("ALTER TABLE `{$dbTable}` DROP INDEX {$ixName}");
                    }
                    continue;
                }
                if($ixNew&&$ixColumns){
                    
                    foreach($ixColumns as $ixck=>$ixcv){
                        if($fieldLens[$ixcv]>=191){
                            $ixColumns[$ixck]=$ixcv.'(191)';
                        }
                    }
                    $ixColumns=implode(',',$ixColumns);
                    
                    if($ixName=='PRIMARY'){
                        db()->execute("ALTER TABLE `{$dbTable}` DROP PRIMARY KEY");
                    }else{
                        db()->execute("ALTER TABLE `{$dbTable}` DROP INDEX {$ixName}");
                    }
                    
                    if($ixName=='PRIMARY'){
                        
                        $exeSqls[]="ALTER TABLE `{$dbTable}` ADD PRIMARY KEY ({$ixColumns})";
                    }elseif($ixUnique){
                        
                        $exeSqls[]="ALTER TABLE `{$dbTable}` ADD UNIQUE INDEX {$ixName}({$ixColumns})";
                    }else{
                        
                        $exeSqls[]="ALTER TABLE `{$dbTable}` ADD INDEX {$ixName}({$ixColumns})";
                    }
                }
            }
            
            db()->execute("ALTER TABLE `{$dbTable}` ENGINE=InnoDB");
            if($exeSqls){
                foreach($exeSqls as $exeSql){
                    db()->execute($exeSql);
                }
            }
        }
    }
}
?>