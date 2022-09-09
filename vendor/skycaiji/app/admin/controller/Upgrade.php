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
        $this->oldFilePath=config('root_path').'/data/program/backup/skycaiji'.g_sc_c('version');
        $this->newFilePath=config('root_path').'/data/program/upgrade/skycaiji'.g_sc_c('version');
    }
    /*执行下载*/
    public function downloadAction(){
        $versionFile=input('version_file','');
        if($versionFile=='zip'){
            
            $zipData=$this->_getZip();
            if($zipData['code']){
                
                $this->success('','',$zipData['data']['file']);
            }else{
                
                $this->error($zipData['msg']?$zipData['msg']:'');
            }
        }else{
            
            $downFileList=$this->_getNewFiles();
            if(empty($downFileList)){
                $this->error();
            }else{
                $this->success('','',array('files'=>$downFileList));
            }
        }
    }
    
    public function _getZip($blockNo=0){
        $blockNo=intval($blockNo);
        $url='/client/upgrade/zip?block_no='.$blockNo;
        $fileData=\util\Tools::curl_skycaiji($url);
        $fileData=json_decode($fileData,true);
        $fileData=is_array($fileData)?$fileData:array();
        $fileData['data']=is_array($fileData['data'])?$fileData['data']:array();
        if($fileData['data']['file']){
            $fileData['data']['file']=json_decode(base64_decode($fileData['data']['file']),true);
        }
        return $fileData;
    }
    
    public function _getNewFiles(){
        $md5Files=array();
        \util\Tools::program_filemd5_list(config('root_path'),$md5Files);
        
        $md5FileList=array();
        foreach ($md5Files as $k=>$v){
            
            $md5FileList[md5($v['file'])]=$v;
        }
        unset($md5Files);
        
        
        $newFileList=\util\Tools::curl_skycaiji('/client/upgrade/files',null,array('timeout'=>100));
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
    
    
    public function downFileAction(){
        $fileName=input('filename');
        $filemd5=input('filemd5');
        
        if(file_exists($this->newFilePath.$fileName)){
            
            if($filemd5==md5_file($this->newFilePath.$fileName)){
                
                $this->success();
            }
        }
        
        $curlInfo=\util\Tools::curl_skycaiji('/client/upgrade/file?filename='.rawurlencode(base64_encode($fileName)),null,array('timeout'=>100),null,true);
        if($curlInfo['ok']){
            
            $newFile=$curlInfo['html'];
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
    
    public function downZipAction(){
        $blockNo=input('block_no/d',0);
        
        $zipData=$this->_getZip($blockNo);
        if(!$zipData['code']){
            
            $this->error($zipData['msg']);
        }
        $fileData=$zipData['data']['file'];
        
        $filePath=config('runtime_path').'/zip_upgrade/skycaiji'.g_sc_c('version');
        $result=\util\Tools::install_downloaded_zip($fileData, $filePath, $this->newFilePath);
        
        if($result['success']){
            $this->success('下载成功','',$result);
        }else{
            $this->error($result['msg']);
        }
    }
    /*
     * 下载的文件完整性检测
     * 注意：执行该方法时，旧代码已经编译了，替换文件后还是执行的旧文件代码
     * 还要注意thinkphp缓存的问题
     * */
    public function downCompleteAction(){
        $this->check_usertoken();
        
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
            
            $this->_upgradeDb();
        }
    }
    
    public function downZipCompleteAction(){
        $this->check_usertoken();
        
        $this->_downZipComplete($this->newFilePath);
        
        $this->_upgradeDb();
    }
    private function _downZipComplete($path){
        static $programPath=null;
        if(!isset($programPath)){
            $programPath=realpath($this->newFilePath);
        }
        $path=realpath($path);
        if($path!=false){
            $fileList=scandir($path);
            foreach($fileList as $file){
                $fileName=realpath($path.'/'.$file);
                if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
                    $this->_downZipComplete($fileName);
                }elseif(is_file($fileName)){
                    $curFile=str_replace('\\', '/',str_replace($programPath, '', $fileName));
                    $curFile='/'.ltrim($curFile,'/');
                    $oldFileName=config('root_path').$curFile;
                    if(md5_file($fileName)!=md5_file($oldFileName)){
                        
                        $oldContent=file_get_contents($oldFileName);
                        write_dir_file($this->oldFilePath.$curFile,$oldContent);
                        $newContent=file_get_contents($fileName);
                        write_dir_file($oldFileName,$newContent);
                    }
                }
            }
        }
    }
    
    private function _upgradeDb(){
        $upgradeDb=new \skycaiji\install\event\UpgradeDb();
        $upgradeResult=$upgradeDb->run();
        if($upgradeResult['success']){
            $this->success();
        }else{
            $this->error();
        }
    }
}