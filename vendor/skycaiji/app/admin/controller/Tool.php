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

namespace skycaiji\admin\controller;
use skycaiji\admin\model\DbCommon;
class Tool extends BaseController {
    /*文件管理*/
    public function fileManagerAction(){
        if($this->request->isPost()){
            $this->ajax_check_userpwd();
            session('file_manager_date',date('Y-m-d'));
            $this->success('','tool/file');
        }else{
            $this->set_html_tags(
                '文件管理',
                '文件管理',
                breadcrumb(array(array('url'=>url('tool/fileManager'),'title'=>'文件管理')))
            );
            return $this->fetch('fileManager');
        }
    }
    
    private function _file_manager_date(){
        if(date('Y-m-d')!=session('file_manager_date')){
            
            $this->error('访问过期','tool/fileManager',array('window_top'=>1));
        }
    }
    
    private function _protected_paths($path){
        static $paths=array('app','files','images','program','program/upgrade','program/backup');
        static $pathList=null;
        if(!isset($pathList)){
            foreach ($paths as $v){
                $v=config('root_path').DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.$v;
                $v=realpath($v);
                if($v){
                    $pathList[]=$v;
                }
            }
        }
        $path=realpath($path);
        if($path&&in_array($path,$pathList)){
            return true;
        }else{
            return false;
        }
    }
    
    public function fileAction(){
        $this->_file_manager_date();
        
        $op=input('op');
        $file=input('file','');
        $file=str_replace('/', DIRECTORY_SEPARATOR, $file);
        $file=trim($file,'\/\\');
        $rootPath=config('root_path').DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR;
        $filePath=$rootPath.$file;
        $filePath=realpath($filePath);
        $fileName='';
        if($file){
            
            if(stripos($filePath, $rootPath)!==0){
                $this->error('禁止访问根目录外的文件');
            }
            
            $fileName='';
            if(preg_match('/([^\/\\\]+)$/',$filePath,$fileName)){
                $fileName=$fileName[1];
            }else{
                $fileName='';
            }
        }
        $files=array();
        if(empty($op)){
            if(is_dir($filePath)){
                
                $fileList=scandir($filePath);
                foreach ($fileList as $v){
                    if($v=='.'||$v=='..'||$v=='index.html'||$v=='install.lock'||preg_match('/\.php$/i', $v)){
                        
                        continue;
                    }
                    $fileInfo=array('dir'=>'','date'=>'');
                    $curFile=$filePath.DIRECTORY_SEPARATOR.$v;
                    if(is_dir($curFile)){
                        $fileInfo['dir']='1';
                    }
                    $fileInfo['date']=date("Y-m-d H:i:s",filemtime($curFile));
                    $files[$v]=$fileInfo;
                }
                
                $this->assign('file',$file);
                $this->assign('filePath',$filePath);
                $this->assign('files',$files);
                return $this->fetch('file');
            }else{
                
                $filePath=realpath($filePath);
                if(empty($filePath)){
                    $this->error('文件不存在');
                }
                
                $fileData=file_get_contents($filePath);
                $fileEncode=mb_detect_encoding($fileData,array('UTF-8','GB2312','GBK','BIG5','ASCII'));
                
                if($fileEncode&&strtoupper($fileEncode)=='UTF-8'){
                    
                    $fileData=json_encode(array('file'=>$file,'data'=>$fileData));
                    $this->assign('fileData',$fileData);
                    return $this->fetch('file_file');
                }else{
                    
                    $fileType='';
                    $fileInfo=array();
                    if(getimagesize($filePath)){
                        
                        $fileType='img';
                    }else{
                        $fileType=pathinfo($filePath,PATHINFO_EXTENSION);
                        $fileInfo['size']=filesize($filePath);
                        $fileInfo['name']=basename($filePath);
                        $fileInfo['file']=$filePath;
                        if($fileInfo['size']>1024*1024){
                            $fileInfo['size']=round($fileInfo['size']/(1024*1024),2).' MB';
                        }else{
                            $fileInfo['size']=round($fileInfo['size']/1024,2).' KB';
                        }
                        
                        $fileInfo['ctime']=date('Y-m-d H:i:s',filectime($filePath));
                        $fileInfo['mtime']=date('Y-m-d H:i:s',filemtime($filePath));
                    }
                    $this->assign('file',$file);
                    $this->assign('fileType',$fileType);
                    $this->assign('fileInfo',$fileInfo);
                    $this->assign('fileUrl',config('root_url').'/data/'.$file);
                    return $this->fetch('file_other');
                }
            }
        }elseif($op=='new'){
            
            if(is_dir($filePath)){
                
                $newType=input('new_type');
                if($this->request->isPost()){
                    $newName=input('new_name','');
                    if(!preg_match('/^[\w\-]+$/', $newName)){
                        $this->error('名称只能由字母、数字和下划线组成');
                    }
                    $newFile=$filePath.DIRECTORY_SEPARATOR.$newName;
                    if($newType=='folder'){
                        if(!is_dir($newFile)){
                            mkdir($newFile,0777,true);
                        }
                        $indexFile=$newFile.DIRECTORY_SEPARATOR.'index.html';
                        if(!file_exists($indexFile)){
                            write_dir_file($indexFile, '');
                        }
                    }elseif($newType=='txt'){
                        write_dir_file($newFile.'.txt', '');
                    }
                    $this->success('创建成功','tool/file?file='.rawurlencode($file));
                }else{
                    $this->assign('file',$file);
                    $this->assign('newType',$newType);
                    return $this->fetch('file_add');
                }
            }
        }elseif($op=='save'){
            
            if($this->request->isPost()){
                if(preg_match('/\.php$/i', $filePath)){
                    $this->error('禁止修改php文件');
                }
                $saveData=input('save_data','',null);
                file_put_contents($filePath, $saveData);
                $this->success('已修改','');
            }
        }elseif($op=='move'){
            
            if($this->request->isPost()){
                if($this->_protected_paths($filePath)){
                    $this->error('禁止移动系统文件夹：'.$filePath);
                }
                
                $moveTo=input('move_to','','trim');
                if(!preg_match('/^[\w\-\/\\\]+$/', $moveTo)||preg_match('/^[\/\\\]+$/', $moveTo)){
                    $this->error('目录只能由字母、数字和下划线组成');
                }
                $moveTo=str_replace('\\', '/', $moveTo);
                
                $movePath=$rootPath.$moveTo;
                if(!is_dir($movePath)){
                    mkdir($movePath,0777,true);
                }
                $movePath=realpath($movePath).DIRECTORY_SEPARATOR;
                $tips=is_dir($filePath)?'目录':'文件';
                
                if(file_exists($movePath.$fileName)){
                    $this->error('移动失败，已存在'.$tips);
                }
                if(rename($filePath, $movePath.$fileName)){
                    $this->success($tips.'已成功移动到：'.$movePath,'tool/file?file='.rawurlencode($moveTo));
                }else{
                    $this->error($tips.'移动失败');
                }
            }else{
                $this->assign('file',$file);
                $this->assign('cur',preg_replace('/[\/\\\]*[^\/\\\]+$/', '', $file));
                $this->assign('rootPath',$rootPath);
                return $this->fetch('file_move');
            }
        }elseif($op=='rename'){
            
            if($this->request->isPost()){
                if($this->_protected_paths($filePath)){
                    $this->error('禁止重命名系统文件夹：'.$filePath);
                }
                $rename=input('rename','','trim');
                if(!preg_match('/^[\w\-]+$/', $rename)){
                    $this->error('名称只能由字母、数字和下划线组成');
                }
                $newName=preg_replace_callback('/([^\/\\\]+)$/',function($match)use($rename){
                    $match=$match[0];
                    $match=explode('.',$match);
                    $match[0]=$rename;
                    return implode('.', $match);
                },$filePath);
                
                if(file_exists($newName)){
                    $this->error('文件名称已存在');
                }
                if(rename($filePath,$newName)){
                    $this->success('重命名成功','tool/file?file='.rawurlencode(preg_replace('/[^\/\\\]+$/', '', $file)));
                }else{
                    $this->error('重命名失败');
                }
            }else{
                $this->assign('file',$file);
                return $this->fetch('file_rename');
            }
        }elseif($op=='delete'){
            if($this->request->isPost()){
                if($this->_protected_paths($filePath)){
                    $this->error('禁止删除系统文件夹：'.$filePath);
                }
                
                if(is_dir($filePath)){
                    \util\Funcs::clear_dir($filePath);
                    rmdir($filePath);
                }else{
                    unlink($filePath);
                }
                $this->success('已删除','');
            }
        }elseif($op=='download'){
            $fileName=explode('.', $fileName);
            if(is_dir($filePath)){
                $zipFileName=dirname($filePath).DIRECTORY_SEPARATOR.'_____'.$fileName[0].'.zip';
                $error='';
                try{
                    $zip=new \ZipArchive();
                    if($zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                        $this->_addFileToZip($filePath, $fileName[0], $zip);
                        $zip->close();
                    }else{
                        $error='文件不存在';
                    }
                }catch (\Exception $ex){
                    $error=$ex->getMessage();
                }
                
                $filecont=null;
                $filesize=0;
                if(file_exists($zipFileName)){
                    $filecont=file_get_contents($zipFileName);
                    $filesize=filesize($zipFileName);
                    unlink($zipFileName);
                }
                if($error){
                    $this->error($error,'');
                }
                \util\Tools::browser_export_scj($fileName[0],$filecont,'zip',$filesize);
            }else{
                \util\Tools::browser_export_scj($fileName[0],file_get_contents($filePath),$fileName[1],filesize($filePath));
            }
        }
    }
    
    private function _addFileToZip($path, $current, $zip) {
        static $isWin=null;
        if(!isset($isWin)){
            $isWin=stripos($this->request->header('user-agent'),'windows')!==false?true:false;
        }
        
        $handler = opendir($path);
        while(($filename = readdir($handler)) !== false) {
            if ($filename != '.' && $filename != '..') {
                $curFile=$path.DIRECTORY_SEPARATOR.$filename;
                $curName=$current?($current.'/'.$filename):$current;
                
                $nameEncode=$curName;
                if($isWin){
                    $nameEncode=iconv('UTF-8', 'GBK//IGNORE', $nameEncode);
                }
                if (is_dir($curFile)){
                    $zip->addEmptyDir($nameEncode);
                    $this->_addFileToZip($curFile,$curName,$zip);
                }else{
                    $zip->addFile($curFile,$nameEncode);
                }
            }
        }
        @closedir($handler);
    }
    
    public function filesAction(){
        $this->_file_manager_date();
        
        $cur=input('cur','','trim');
        $cur=str_replace('/', DIRECTORY_SEPARATOR, $cur);
        $cur=trim($cur,'\/\\');
        $rootPath=config('root_path').DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR;
        $curPath=$rootPath.$cur;
        $curPath=realpath($curPath);
        if($cur){
            if(!is_dir($curPath)){
                $this->error('当前目录错误');
            }
            if(stripos($curPath, $rootPath)!==0){
                $this->error('当前目录错误');
            }
        }
        
        $files=input('files','','trim');
        $files=$files?explode(',', $files):array();
        init_array($files);
        $fileList=array();
        foreach ($files as $file){
            
            $fileName=$curPath.DIRECTORY_SEPARATOR.$file;
            $fileName=realpath($fileName);
            if(stripos($fileName, $rootPath)===0){
                
                if(!$this->_protected_paths($fileName)){
                    
                    $fileList[]=$fileName;
                }
            }
        }
        if(empty($fileList)){
            $this->error('请选择文件');
        }
        $op=input('op');
        if($op=='move'){
            $this->assign('cur',$cur);
            $this->assign('files',implode(',', $files));
            $this->assign('rootPath',$rootPath);
            return $this->fetch('files_move');
        }elseif($op=='move_post'){
            if($this->request->isPost()){
                $moveTo=input('move_to','','trim');
                if(!preg_match('/^[\w\-\/\\\]+$/', $moveTo)||preg_match('/^[\/\\\]+$/', $moveTo)){
                    $this->error('目录只能由字母、数字和下划线组成');
                }
                $moveTo=str_replace('\\', '/', $moveTo);
                
                $movePath=$rootPath.$moveTo;
                if(!is_dir($movePath)){
                    mkdir($movePath,0777,true);
                }
                $movePath=realpath($movePath).DIRECTORY_SEPARATOR;
                foreach ($fileList as $file){
                    $fileName='';
                    if(preg_match('/([^\/\\\]+)$/',$file,$fileName)){
                        $fileName=$fileName[1];
                    }else{
                        $fileName='';
                    }
                    if(file_exists($movePath.$fileName)){
                        
                        continue;
                    }
                    rename($file, $movePath.$fileName);
                }
                $this->success($tips.'已成功移动到：'.$movePath,'tool/file?file='.rawurlencode($moveTo));
            }else{
                $this->error('无效操作');
            }
        }elseif($op=='delete'){
            if($this->request->isPost()){
                foreach ($fileList as $file){
                    if(is_dir($file)){
                        \util\Funcs::clear_dir($file);
                        rmdir($file);
                    }else{
                        unlink($file);
                    }
                }
                $this->success('已删除','tool/file?file='.rawurlencode($cur));
            }else{
                $this->error('无效操作');
            }
        }
    }
	
	/*日志列表*/
	public function logsAction(){
		$logPath=realpath(config('runtime_path').'/log');
		$logList=array();
		if(!empty($logPath)){
			$paths=scandir($logPath);
			if(!empty($paths)){
				foreach ($paths as $path){
					if($path!='.'&&$path!='..'){
						$pathFiles=scandir($logPath.'/'.$path);
						if(!empty($pathFiles)){
							foreach ($pathFiles as $pathFile){
								if($pathFile!='.'&&$pathFile!='..'){
									$logList[$path][]=array(
										'name'=>$pathFile,
										'file'=>realpath($logPath.'/'.$path.'/'.$pathFile),
									);
								}
							}
						}
					}
				}
			}
		}
		$this->set_html_tags(
		    '错误日志',
		    '错误日志',
		    breadcrumb(array(array('url'=>url('tool/logs'),'title'=>'错误日志')))
		);
		$this->assign('logList',$logList);
		return $this->fetch();
	}
	/*读取日志*/
	public function logAction(){
	    config('dispatch_error_tmpl','common:error');
	    config('dispatch_success_tmpl','common:success');
	    
		$file=realpath(input('file'));
		$logPath=realpath(config('runtime_path').'/log');
		if(stripos($file,$logPath)===false){
			$this->error('不是日志文件','');
		}
		$log=file_get_contents($file);
		
		if(request()->isPost()){
		    if(input('upload')){
		        
		        \util\Tools::curl_skycaiji('/client/upload/log',null,array(),array('log'=>$log,'v'=>SKYCAIJI_VERSION,'php'=>constant('PHP_VERSION')));
		        $this->success('上报成功，感谢支持！','');
		    }
		}else{
		    
		    return $this->display('<pre>'.$log.'</pre>');
		}
	}
	
	/*文件校验*/
	public function checkfileAction(){
		set_time_limit(0);
		if(request()->isPost()){
			$check_file=file_get_contents(config('app_path').'/install/data/check_file');
			$check_file=unserialize($check_file?:'');
			if(empty($check_file)){
				$this->error('没有获取到校验文件');
			}
			if(!version_compare($check_file['version'],SKYCAIJI_VERSION,'=')){
				
				$this->error('校验文件版本与程序版本不一致');
			}
			
			if(empty($check_file['files'])){
				$this->error('没有文件');
			}
			
			$new_files=array();
			$new_files1=array();
			\util\Tools::program_filemd5_list(config('root_path'), $new_files1);
			foreach ($new_files1 as $k=>$v){
				$new_files[md5($v['file'])]=$v;
			}
			unset($new_files1);
			if(empty($new_files)){
				$this->error('没有获取到程序文件');
			}
			
			$error_files=array();
			
			foreach ($check_file['files'] as $old_file){
				$error_file='';
				$filenameMd5=md5($old_file['file']);
				if(isset($new_files[$filenameMd5])){
					if($new_files[$filenameMd5]['file']!=$old_file['file']){
						
						$error_file=$old_file['file'].' 不一致';
					}elseif($new_files[$filenameMd5]['md5']!=$old_file['md5']){
						$error_file=$old_file['file'].' 已修改';
					}
				}else{
					$error_file=$old_file['file'].' 不存在';
				}
				if(!empty($error_file)){
					$error_files[]=$error_file;
				}
			}
			if(empty($error_files)){
				
				$this->success();
			}else{
				$this->error('',null,array('files'=>$error_files));
			}
		}else{
		    $this->set_html_tags(
		        '校验文件',
		        '校验文件',
		        breadcrumb(array(array('url'=>url('tool/checkfile'),'title'=>'校验文件')))
		    );
			return $this->fetch();
		}
	}
	/*获取索引*/
	public function _get_indexes($tb_indexes){
		$indexes=array();
		if(!empty($tb_indexes)){
			foreach ($tb_indexes as $tb_index){
			    $tb_index=\util\Funcs::array_keys_to_lower($tb_index);
				
				if(empty($indexes[$tb_index['key_name']]['type'])){
					
					$index_type=strtolower($tb_index['index_type']);
					if(strcasecmp($tb_index['key_name'], 'primary')==0){
						
						$index_type='primary';
					}elseif(empty($tb_index['non_unique'])){
						
						$index_type='unique';
					}elseif($index_type=='fulltext'){
						
						$index_type='fulltext';
					}else{
						
						$index_type='index';
					}
				}
			
				$indexes[$tb_index['key_name']]['type']=$index_type;
				$indexes[$tb_index['key_name']]['field'][]='`'.$tb_index['column_name'].'`'.(empty($tb_index['sub_part'])?'':"({$tb_index['sub_part']})");
			}
		}
		return $indexes;
	}
	/*数据库校验*/
	public function checkdbAction(){
		if(request()->isPost()){
			set_time_limit(0);
			$repair=input('repair/d',0);
			
			$check_db=file_get_contents(config('app_path').'/install/data/check_db');
			if(empty($check_db)){
				$this->error('没有获取到校验文件');
			}
			$check_db=unserialize($check_db?:'');
			if(empty($check_db)){
				$this->error('没有获取到表');
			}
			
			if(!version_compare($check_db['version'],g_sc_c('version'),'=')){
				
				$this->error('校验文件版本与数据库版本不一致');
			}
			if(empty($check_db['tables'])){
				$this->error('没有表');
			}
			init_array($check_db['engines']);
			init_array($check_db['indexes']);
			
			$error_engines=array();
			$error_fields=array();
			$error_indexes=array();
			$table_primary=array();
			
			foreach ($check_db['tables'] as $table=>$fields){
			    $tb_indexes=$check_db['indexes'][$table];
			    $tb_engine=$check_db['engines'][$table];
			    $table=config('database.prefix').$table;
			    
			    $tableEngine=\util\Db::table_engine($table);
			    if($tb_engine!=$tableEngine){
			        
			        $error_engines[$table]=$tableEngine;
			    }
				
				$null_table=db()->query("show tables like '{$table}';");
				$null_table=empty($null_table)?true:false;

				$cur_fields=array();
				if(!$null_table){
					
					$cur_fields=DbCommon::fieldsInfo($table);
				}
				
				foreach ($fields as $field=>$field_set){
					if(serialize($field_set)!=serialize($cur_fields[$field])){
						
						$error_fields[$table][$field]=$field_set;
					}
					if($field_set['primary']){
						
						$table_primary[$table][$field_set['name']]='`'.$field_set['name'].'`';
					}
				}
				$tb_indexes=$this->_get_indexes($tb_indexes);
				
				
				if(!$null_table){
					
					$cur_indexes=db()->query("SHOW INDEX FROM `{$table}`");
					
					$cur_indexes=$this->_get_indexes($cur_indexes);
					
					
					foreach ($tb_indexes as $index_name=>$tb_index){
						$cur_index=$cur_indexes[$index_name];
						
						if(empty($cur_index)||strcasecmp($tb_index['type'],$cur_index['type'])!=0||strcasecmp(implode(',',$tb_index['field']),implode(',',$cur_index['field']))!=0){
							
							$error_indexes[$table][$index_name]=$tb_index;
						}
					}
				}
				
			}
			if(empty($error_engines)&&empty($error_fields)&&empty($error_indexes)){
				
				$this->success();
			}else{
				if(!$repair){
					
					
					foreach ($error_fields as $tb=>$tb_fields){
						foreach ($tb_fields as $k=>$v){
							$v['default']=is_null($v['default'])?NULL:$v['default'];
							$v['primary']=$v['primary']?'是':'否';
							$v['notnull']=$v['notnull']?'是':'否';
							$v['autoinc']=$v['autoinc']?'是':'否';
							$tb_fields[$k]=$v;
						}
						$error_fields[$tb]=$tb_fields;
					}
					foreach ($error_indexes as $tb=>$indexes){
						foreach ($indexes as $k=>$v){
							$index_field=implode(',', $v['field']);
							$index_field=str_replace('`', '', $index_field);
							$error_indexes[$tb][$k]['field']=$index_field;
						}
					}
					
					$this->error('',null,array('engines'=>empty($error_engines)?null:$error_engines,'fields'=>empty($error_fields)?null:$error_fields,'indexes'=>empty($error_indexes)?null:$error_indexes));
				}else{
					
					try {
					    
					    foreach ($error_engines as $tb=>$tb_engine){
					        if($tb_engine=='innodb'){
					            
					            continue;
					        }
					        \util\db::to_innodb($tb);
					    }
					    
						foreach ($error_fields as $tb=>$tb_fields){
							$primarys=$table_primary[$tb];
							
							$hasTable=db()->query("show tables like '{$tb}';");
							
							foreach ($tb_fields as $k=>$v){
								if($v['primary']){
									
									$v['notnull']=1;
								}
								if($v['notnull']){
									
									$v['default']=is_null($v['default'])?'':"DEFAULT '{$v['default']}'";
								}else{
									
									$v['default']=is_null($v['default'])?'DEFAULT NULL':"DEFAULT '{$v['default']}'";
								}
								$v['notnull']=$v['notnull']?'NOT NULL':'null';
								$v['autoinc']=$v['autoinc']?'AUTO_INCREMENT':'';
								
								$tb_fields[$k]=$v;
							}
							
							if(empty($hasTable)){
								
								$createSql="CREATE TABLE `{$tb}` (";
								foreach ($tb_fields as $k=>$v){
									$createSql.="`{$v['name']}` {$v['type']} {$v['notnull']} {$v['default']} {$v['autoinc']},\r\n";
								}
								if(empty($primarys)){
									
									$createSql=rtrim($createSql,',');
								}else{
									
									$createSql.='PRIMARY KEY ('.implode(',', $primarys).')';
								}
								
								$createSql.=' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
								db()->execute($createSql);
							}else{
								
								$cur_fields=db()->getTableFields($tb);
								foreach ($tb_fields as $k=>$v){
									$alterSql="ALTER TABLE {$tb} ";
									
									if(in_array($v['name'],$cur_fields)){
										
										$alterSql.=' MODIFY ';
									}else{
										
										$alterSql.=' ADD ';
									}
									
									$alterSql.=" `{$v['name']}` {$v['type']} {$v['notnull']} {$v['default']} {$v['autoinc']}";
									if(empty($primarys)&&$v['primary']){
										
										$alterSql.=' PRIMARY KEY';
									}
									
									db()->execute($alterSql);
								}
								
								if(!empty($primarys)){
									
									db()->execute("alter table {$tb} drop primary key,add primary key(".implode(',', $primarys).')');
								}
							}
						}
						
						foreach ($error_indexes as $tb=>$tb_indexes){
							foreach ($tb_indexes as $index_name=>$each_index){
								$each_index['type']=strtolower($each_index['type']);
								$add_sql=" add ";
								$drop_sql="alter table {$tb} drop ";
								switch ($each_index['type']){
									case 'primary':$add_sql.='primary key';$drop_sql.='primary key';break;
									case 'unique':$add_sql.="unique `{$index_name}`";$drop_sql.="index `{$index_name}`";break;
									case 'index':$add_sql.="index `{$index_name}`";$drop_sql.="index `{$index_name}`";break;
									case 'fulltext':$add_sql.="fulltext `{$index_name}`";$drop_sql.="index `{$index_name}`";break;
									default:$add_sql='';$drop_sql='';break;
								}
								if(!empty($add_sql)){
									
									$add_sql.=" (".implode(',',$each_index['field']).")";
								}
								
								
								if($each_index['type']=='primary'){
									
									try {
										if(!empty($drop_sql)&&!empty($add_sql)){
											db()->execute($drop_sql.','.$add_sql);
										}
									}catch (\Exception $ex){
										
									}
								}else{
									
									if(!empty($drop_sql)){
										
										try {
											db()->execute($drop_sql);
										}catch (\Exception $ex){
											
										}
									}
									if(!empty($add_sql)){
										
										$add_sql="alter table {$tb} ".$add_sql;
										try {
											db()->execute($add_sql);
										}catch (\Exception $ex){
											
										}
									}
								}
							}
						}
					}catch (\Exception $ex){
						$this->error($ex->getMessage());
					}
					$this->success('修复完毕,请再次校验！');
				}
			}
		}else{
		    $this->set_html_tags(
		        '校验数据库',
		        '校验数据库',
		        breadcrumb(array(array('url'=>url('tool/checkdb'),'title'=>'校验数据库')))
		    );
			return $this->fetch();
		}
	}
	
	public function checkTableAction(){
	    if($this->request->isPost()){
    	    $op=input('op','');
    	    if(empty($op)){
    	        
    	        $dbName=config('database.database');
    	        $dbTables=db()->getConnection()->getTables($dbName);
    	        init_array($dbTables);
    	        foreach ($dbTables as $k=>$v){
    	            if(stripos($v,config('database.prefix'))!==0){
    	                unset($dbTables[$k]);
    	            }
    	        }
    	        $dbTables=array_values($dbTables);
    	        $this->success('','',$dbTables);
    	    }elseif($op=='table'){
    	        
    	        $table=input('table');
    	        if(!preg_match('/^[\w\-]+$/', $table)){
    	            
    	            $this->error();
    	        }
    	        $cacheKey=md5('admin_tool_check_table_'.$table);
    	        $cacheCheckTable=cache($cacheKey);
    	        init_array($cacheCheckTable);
    	        if(empty($cacheCheckTable)||abs(time()-$cacheCheckTable['time'])>200){
    	            
    	            try{
    	                
    	                $dbConfig=config('database');
    	                $dbConfig['params'][\PDO::ATTR_EMULATE_PREPARES]=true;
    	                $checkList=db('',$dbConfig)->query('check table '.$table);
    	            }catch (\Exception $ex){}
    	            init_array($checkList);
    	            $checkTable='';
    	            foreach ($checkList as $v){
    	                $v=\util\Funcs::array_keys_to_lower($v);
    	                if(is_array($v)&&$v['msg_type']&&strtolower($v['msg_type'])=='error'){
    	                    $v['table']=preg_replace('/^'.$dbName.'\./i', '', $v['table']);
    	                    $checkTable=$v['table'];
    	                }
    	            }
    	            $cacheCheckTable=array('time'=>time(),'table'=>array('error'=>$checkTable));
    	            cache($cacheKey,$cacheCheckTable);
    	        }
    	        init_array($cacheCheckTable['table']);
    	        
    	        $this->success('','',array('table'=>$cacheCheckTable['table']));
    	    }elseif($op=='repair'){
    	        
    	        $table=input('table');
    	        if(!preg_match('/^[\w\-]+$/', $table)){
    	            
    	            $this->error();
    	        }
    	        try{
    	            
    	            db()->query('repair table '.$table);
    	        }catch (\Exception $ex){}
	            $cacheKey=md5('admin_tool_check_table_'.$table);
	            cache($cacheKey,null);
    	        $this->success();
    	    }
	    }else{
	        $this->error();
	    }
	}
	
	public function previewAction(){
	    if(request()->isPost()){
	        $data=input('data','','trim');
	        $preview=array('json'=>'','html'=>'');
	        if(preg_match('/^\w+\:\/\//', $data)){
	            
	            if(stripos($data,config('root_website'))===0){
	                $this->error('禁止访问当前主机网址');
	            }
	            $data=get_html($data);
	        }
	        if(!empty($data)){
	            $json=\util\Funcs::convert_html2json($data,true);
	            if(!empty($json)){
	                $preview['json']=$json;
	            }else{
	                $preview['html']=\util\Funcs::html_clear_js($data);
	            }
	        }
	        $this->success('','',$preview);
	    }else{
	        return $this->_preview();
	    }
	}
	public function preview_dataAction(){
	    $data=input('data','','trim');
	    $this->assign('data',$data);
	    return $this->_preview();
	}
	
	private function _preview(){
	    $this->set_html_tags(
	        '解析预览',
	        '解析预览',
	        breadcrumb(array(array('url'=>url('tool/preview'),'title'=>'解析预览')))
	    );
	    return $this->fetch('preview');
	}
}