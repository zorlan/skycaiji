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

use think\Loader;
class ReleaseApp extends \skycaiji\common\model\BaseModel{
	public function addCms($cms,$code='',$tpl=''){
		if(empty($cms['app'])){
			return false;
		}
		
		$cms['module']='cms';
		$cms['uptime']=$cms['uptime']>0?$cms['uptime']:time();
		
		if(!$this->isRightApp($cms['app'], 'cms')){
		    
		    return false;
		}
		$codeFmt=\util\Funcs::strip_phpcode_comment($code);
		
		if(!preg_match('/^\s*namespace\s+plugin\\\release\b/im',$codeFmt)){
			
			return false;
		}
		if(!preg_match('/class\s+'.$cms['app'].'\b/i',$codeFmt)){
			
			return false;
		}
		
		$cmsData=$this->where('app',$cms['app'])->find();
		$success=false;
		
		if(!empty($cmsData)){
			
			$this->strict(false)->where('app',$cms['app'])->update($cms);
			$success=true;
		}else{
			
		    $cms['addtime']=time();
			$this->isUpdate(false)->allowField(true)->save($cms);
			$cms['id']=$this->id;
			$success=$cms['id']>0?true:false;
		}
		if($success){
			$cmsAppPath=config('plugin_path').'/release';
			if(!empty($code)){
				
				write_dir_file($cmsAppPath.'/cms/'.ucfirst($cms['app']).'.php', $code);
			}
			if(!empty($tpl)){
				
				write_dir_file($cmsAppPath.'/view/cms/'.ucfirst($cms['app']).'.html', $tpl);
			}
		}
		return $success;
	}
	
	public function appFileName($appName,$module='cms'){
		$module=strtolower($module);
		$appName=ucfirst($appName);
		return config('plugin_path').'/release/'.$module.'/'.$appName.'.php';
	}
	public function appFileExists($appName,$module='cms'){
		$fileName=$this->appFileName($appName,$module);
		return file_exists($fileName)?true:false;
	}
	public function appImportClass($appName,$module='cms'){
		$cmsClass='\\plugin\\release\\'.strtolower($module).'\\'.ucfirst($appName);
		if(\util\Funcs::class_exists_clean($cmsClass)){
		    $cmsClass=new $cmsClass();
		}else{
		    $cmsClass=null;
		}
		return $cmsClass;
	}
	
	public function isSystemApp($appName,$module='cms'){
	    static $systemApps = array(
	        'cms'=>array('basecms'),
	        'diy'=>array('basediy','codediy','base','code'),
        ); 
        $appName=$appName?strtolower($appName):'';
        if (is_array($systemApps[$module])&&in_array($appName, $systemApps[$module])) {
            return true;
        } else {
            return false;
        }
	}
	
	public function isRightApp($app,$module){
	    if($module=='diy'){
	        return preg_match('/^[a-z][a-z0-9]+$/i', $app)?true:false;
	    }elseif($module=='cms'){
	        return preg_match('/^([A-Z][a-z0-9]*){3}$/',$app)?true:false;
	    }
	    return false;
	}
	/*导入v1.x版本发布插件*/
	public function oldImportClass($appName,$model='Cms'){
		$model=ucfirst($model);
		$appName=ucfirst($appName);
		$fileName=$this->oldFileName($appName,$model);
		$appName=$appName.$model;
		if(file_exists($fileName)){
			Loader::addNamespace('Release',realpath(APP_PATH.'Release'));
			Loader::import($appName,config('app_path').'/Release/'.$model.'/','.class.php');
			$oldClass='\\Release\\'.$model.'\\'.$appName;
			$oldClass=new $oldClass();
			return $oldClass?$oldClass:null;
		}else{
			return null;
		}
	}
	/*获取v1.x版本发布插件源码*/
	public function oldFileCode($appName,$model='Cms'){
		$fileName=$this->oldFileName($appName,$model);
		if(file_exists($fileName)){
			return file_get_contents($fileName);
		}else{
			return null;
		}
	}
	/*存在v1.x版本插件*/
	public function oldFileExists($appName,$model='Cms'){
		$fileName=$this->oldFileName($appName,$model);
		return file_exists($fileName)?true:false;
	}
	
	public function oldFileName($appName,$model='Cms'){
		$model=ucfirst($model);
		$appName=ucfirst($appName);
		return config('app_path').'/Release/'.$model.'/'.$appName.$model.'.class.php';
	}
}

?>