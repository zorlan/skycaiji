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

class Upgrade extends BaseController{
	public $oldFilePath='';
	public $newFilePath='';
	public function __construct(){
		parent::__construct();
		set_time_limit(3600);
		$this->oldFilePath=config('root_path').'/data/program/backup/skycaiji'.$GLOBALS['_sc']['c']['version'];
		$this->newFilePath=config('root_path').'/data/program/upgrade/skycaiji'.$GLOBALS['_sc']['c']['version'];
	}
    /*检测更新*/
    public function newVersionAction(){
    	$version=get_html('https://www.skycaiji.com/upgrade/program/version?v='.SKYCAIJI_VERSION,null,null,'utf-8');
    	$version=json_decode($version,true);
    	$version=is_array($version)?$version:array();
    	$new_version=trim($version['new_version']);
    	$cur_version=$GLOBALS['_sc']['c']['version'];
		
		
    	if(version_compare($new_version,$cur_version,'>')){
    		
    		$version['is_new_version']=true;
    	}
    	
    	$cacheIx=cache('backstage_admin_index');
    	if(empty($cacheIx)||$cacheIx['ver']!=$version['admin_index_ver']){
    		$version['is_new_admin_index']=true;
    	}
    	
    	$this->success('',null,$version);
    }
    /*
     * 下载的文件完整性检测
     * 注意：执行该方法时，旧代码已经编译了，替换文件后还是执行的旧文件代码
     * 还要注意thinkphp缓存的问题
     * */
    public function downCompleteAction(){
    	$downFileList=$this->_getNewFiles();
    	$errorFiles=array();
    	
    	foreach ($downFileList as $file){
    		$filename=$this->newFilePath.$file['file'];
    		if(!file_exists($filename)){
    			
    			$errorFiles[]=$file['file'];
    		}else{
				
    			$filemd5=md5_file($filename);
    			if($filemd5!=$file['md5']){
    				$errorFiles[]=$file['file'];
    			}
    		}
    	}
    	if(!empty($errorFiles)){
    		
    		$errorFiles=array_unique($errorFiles);
    		$this->error('',null,$errorFiles);
    	}else{
    		
    		foreach ($downFileList as $file){
    			$content=file_get_contents($this->newFilePath.$file['file']);
    			write_dir_file(config('root_path').$file['file'],$content);
    		}
    		
			$upgradeDb=new \skycaiji\install\event\UpgradeDb();
    		$upgradeResult=$upgradeDb->run();
    		if($upgradeResult['success']){
    			$this->success();
    		}else{
    			$this->error();
    		}
    	}
    }
    
    /*下载文件*/
    public function downFileAction(){
    	$fileName=input('filename');
    	$filemd5=input('filemd5');

    	if(file_exists($this->newFilePath.$fileName)){
    		
    		if($filemd5==md5_file($this->newFilePath.$fileName)){
    			
    			$this->success();
    		}
    	}
    	$fileUrl='https://www.skycaiji.com/upgrade/program/getFile?filename='.rawurlencode(base64_encode($fileName));
    	
    	$curl=\util\Curl::get($fileUrl,null,array('timeout'=>100));
    	if($curl->isOk){
    		
    		$newFile=$curl->body;
    		$oldFile=file_get_contents(config('root_path').$fileName);
    		if(!empty($oldFile)){
    			
    			write_dir_file($this->oldFilePath.$fileName,$oldFile);
    		}
    		write_dir_file($this->newFilePath.$fileName,$newFile);
    		$newFilemd5=md5_file($this->newFilePath.$fileName);
    		if($newFilemd5==$filemd5){
    			
    			$this->success();
    		}else{
    			$this->error('文件校验失败：'.$fileName);
    		}
    	}else{
    		$this->error('文件下载失败：'.$fileName);
    	}
    	$this->error();
    }
	/*获取升级、更新的文件*/
    public function newFilesAction(){
    	$downFileList=$this->_getNewFiles();
    	if(empty($downFileList)){
    		$this->error();
    	}else{
    		$this->success('',null,array('files'=>$downFileList));
    	}
    }
    
    public function _getNewFiles(){
    	$md5Files=array();
    	program_filemd5_list(config('root_path'),$md5Files);
    	
    	$md5FileList=array();
    	foreach ($md5Files as $k=>$v){
    		
    		$md5FileList[md5($v['file'])]=$v;
    	}
    	unset($md5Files);
    	
    	
    	$newFileList=get_html('https://www.skycaiji.com/upgrade/program/files',null,array('timeout'=>100),'utf-8');
    	$newFileList=json_decode($newFileList,true);
    	
    	$downFileList=array();
    	
    	foreach ($newFileList as $newFile){
    		$filenameMd5=md5($newFile['file']);
    		if(isset($md5FileList[$filenameMd5])){
    			if($md5FileList[$filenameMd5]['md5']!=$newFile['md5']||$md5FileList[$filenameMd5]['file']!=$newFile['file']){
    				
    				$downFileList[]=$newFile;
    			}
    		}else{
    			
    			$downFileList[]=$newFile;
    		}
    	}
    	return empty($downFileList)?null:$downFileList;
    }
}