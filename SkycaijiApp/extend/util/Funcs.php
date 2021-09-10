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
 
/*函数库*/
namespace util;
class Funcs{
    
	public static function web_server_name(){
	    $webServer=$_SERVER["SERVER_SOFTWARE"];
	    foreach (array('apache','nginx','iis') as $v){
	        if(preg_match('/\b'.$v.'\b/i',$webServer)){
	            $webServer=$v;
	            break;
	        }
	    }
	    return $webServer;
	}
	
	
	public static function array_filter_keep0($list){
	    if(is_array($list)){
	        foreach ($list as $k=>$v){
	            if(empty($v)&&$v!==0&&$v!=='0'){
	                
	                unset($list[$k]);
	            }
	        }
	    }else{
	        $list=array();
	    }
	    return $list;
	}
	
	
	public static function strip_phpcode_comment($code){
	    if($code){
	        $tokens=token_get_all($code);
	        $newCode='';
	        foreach ($tokens as $key=>$token){
	            if (!is_array($token)){
	                $newCode.=$token;
	            }else{
	                
	                if($token[0]==T_COMMENT||$token[0]==T_DOC_COMMENT){
	                    if(preg_match('/[\r\n]+$/', $token[1])){
	                        
	                        $newCode.=PHP_EOL;
	                    }
	                }else{
	                    $newCode.=$token[1];
	                }
	            }
	        }
	        $code=$newCode;
	    }
	    return $code;
	}
	
	
	public static function filter_key_val_list(&$arr1,&$arr2){
	    if(!is_array($arr1)){
	        $arr1=array();
	    }
	    if(!is_array($arr2)){
	        $arr2=array();
	    }
	    
	    foreach ($arr1 as $k=>$v){
	        if(empty($v)){
	            
	            unset($arr1[$k]);
	            unset($arr2[$k]);
	        }
	    }
	    $arr1=array_values($arr1);
	    $arr2=array_values($arr2);
	}
	
	
	public static function filter_key_val_list3(&$arr1,&$arr2,&$arr3){
	    if(!is_array($arr1)){
	        $arr1=array();
	    }
	    if(!is_array($arr2)){
	        $arr2=array();
	    }
	    if(!is_array($arr3)){
	        $arr3=array();
	    }
	    foreach ($arr1 as $k=>$v){
	        if(empty($v)){
	            
	            unset($arr1[$k]);
	            unset($arr2[$k]);
	            unset($arr3[$k]);
	        }
	    }
	    $arr1=array_values($arr1);
	    $arr2=array_values($arr2);
	    $arr3=array_values($arr3);
	}
	
	public static function array_array_map($callback, $arr1, array $_ = null){
	    if(is_array($arr1)){
	        $arr=array();
	        foreach ($arr1 as $k=>$v){
	            if(!is_array($v)){
	                $arr[$k]=call_user_func($callback, $v);
	            }else{
	                $arr[$k]=self::array_array_map($callback,$v,$_);
	            }
	        }
	    }
	    return $arr;
	}
	
	public static function array_implode($glue, $pieces){
	    $str='';
	    foreach ($pieces as $v){
	        if(is_array($v)){
	            $str.=self::array_implode($glue,$v);
	        }else{
	            $str.=$glue.$v;
	        }
	    }
	    return $str;
	}
	
	public static function convert_html2json($html,$returnStr=false){
	    static $jsonpRegExp='/^(\s*[\$\w\-]+\s*[\{\(])+(?P<json>[\s\S]+)(?P<end>[\}\]])\s*\)\s*[\;]{0,1}/i';
	    $json=json_decode($html,true);
	    if(!empty($json)){
	        
	        if($returnStr){
	            
	            $json=$html;
	        }
	    }elseif(preg_match($jsonpRegExp,$html,$json)){
	        
	        $json=trim($json['json']).$json['end'];
	        if(!$returnStr){
	            
	            $json=json_decode($json,true);
	        }
	    }
	    return $json?$json:null;
	}
	
	
	public static function clear_dir($path,$passFiles=null){
	    if(empty($path)){
	        return;
	    }
	    $path=realpath($path);
	    if(empty($path)){
	        return;
	    }
	    if(!empty($passFiles)){
	        $passFiles=array_map('realpath', $passFiles);
	    }
	    
	    $fileList=scandir($path);
	    foreach( $fileList as $file ){
	        $fileName=realpath($path.'/'.$file);
	        if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
	            self::clear_dir($fileName,$passFiles);
	            rmdir($fileName);
	        }elseif(is_file($fileName)){
	            if($passFiles&&in_array($fileName, $passFiles)){
	                
	                
	            }else{
	                unlink($fileName);
	            }
	        }
	    }
	    clearstatcache();
	}
	
	
	public static function array_get(&$arr,$keys){
	    $val=null;
	    if(is_array($arr)){
    	    if(is_array($keys)&&count($keys)>1){
    	        
    	        $curArr=&$arr;
    	        $endKey=array_slice($keys,-1,1);
    	        if(!empty($endKey)){
    	            
    	            $endKey=$endKey[0];
        	        $keys=array_slice($keys,0,-1);
        	        
        	        $isNotArr=false;
        	        
        	        foreach($keys as $key){
        	            if(!is_array($curArr[$key])){
        	                
        	                $isNotArr=true;
        	                break;
        	            }
        	            $curArr=&$curArr[$key];
        	        }
        	        
        	        if(!$isNotArr){
        	            
        	            $val=$curArr[$endKey];
        	        }
    	        }
    	    }else{
    	        if(is_array($keys)){
    	            
    	            $keys=array_values($keys);
    	            $keys=$keys[0];
    	        }
    	        $val=$arr[$keys];
    	    }
	    }
	    return $val;
	}
	
	
	
	public static function array_set(&$arr,$keys,$val){
	    if(is_array($keys)){
	        $curArr=&$arr;
	        $endKey=array_slice($keys,-1,1);
	        if(!empty($endKey)){
	            
	            $endKey=$endKey[0];
	            $keys=array_slice($keys,0,-1);
	            foreach($keys as $key){
	                if(!is_array($curArr[$key])){
	                    $curArr[$key]=array();
	                }
	                $curArr=&$curArr[$key];
	            }
	            
	            if(is_null($val)){
	                
	                unset($curArr[$endKey]);
	            }else{
	                $curArr[$endKey]=$val;
	            }
	        }
	    }else{
	        
	        if(is_null($val)){
	            
	            unset($arr[$keys]);
	        }else{
	            $arr[$keys]=$val;
	        }
	    }
	}
	
	
	public static function install_downloaded_zip($fileData,$cachePath,$toPathName){
	    $result=array('success'=>false,'msg'=>'','blocks'=>0,'next_block_no'=>0);
	    $fileData=is_array($fileData)?$fileData:array();
	    
	    $blocks=intval($fileData['blocks']);
	    $blockNo=intval($fileData['block_no']);
	    if($blocks<=0){
	        $result['msg']='文件不存在';
	        return $result;
	    }
	    
	    $cachePath.='/'.md5($fileData['md5'].'_'.$fileData['size'].'_'.$fileData['blocks']).'/';
	    
	    $result['blocks']=$blocks;
	    
	    if($blockNo<1){
	        
	        for($i=1;$i<=$blocks;$i++){
	            if(!file_exists($cachePath.$i)){
	                $result['next_block_no']=$i;
	                break;
	            }
	        }
	        $result['success']=true;
	        return $result;
	    }else{
	        
	        if(empty($fileData['block'])){
	            $result['msg']='文件数据为空';
	            return $result;
	        }
	        
	        $fileData['block']=base64_decode($fileData['block']);
	        
	        write_dir_file($cachePath.$blockNo,$fileData['block']);
	        
	        if($blockNo<$blocks){
	            
	            for($i=$blockNo+1;$i<=$blocks;$i++){
	                if(!file_exists($cachePath.$i)){
	                    $result['next_block_no']=$i;
	                    break;
	                }
	            }
	            $result['success']=true;
	            return $result;
	        }else{
	            
	            $downloaded=true;
	            for($i=1;$i<=$blocks;$i++){
	                if(!file_exists($cachePath.$i)){
	                    
	                    $downloaded=false;
	                    break;
	                }
	            }
	            if(!$downloaded){
	                $result['msg']='文件不完整，请重试';
	                return $result;
	            }else{
	                
	                $downloadedData='';
	                for($i=1;$i<=$blocks;$i++){
	                    $downloadedData.=file_get_contents($cachePath.$i);
	                }
	                write_dir_file($cachePath.'archive.zip',$downloadedData);
	                unset($downloadedData);
	                
	                $error='';
	                try {
	                    $zipClass=new \ZipArchive();
	                    if($zipClass->open($cachePath.'archive.zip')===TRUE){
	                        $zipClass->extractTo($toPathName);
	                        $zipClass->close();
	                    }else{
	                        $error='文件解压失败';
	                    }
	                }catch(\Exception $ex){
	                    $error='您的服务器不支持ZipArchive解压';
	                }
	                
	                if($error){
	                    $result['msg']=$error;
	                    return $result;
	                }else{
	                    self::clear_dir($cachePath);
	                }
	            }
	        }
	    }
	    $result['success']=true;
	    return $result;
	}
}

?>