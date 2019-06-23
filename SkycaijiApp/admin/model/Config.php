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

class Config extends BaseModel {
    protected $pk = 'cname';
    
	public function convertData($configItem){
		if(!empty($configItem)){
			switch($configItem['ctype']){
				case 1:$configItem['data']=intval($configItem['data']);break;
				case 2:$configItem['data']=unserialize($configItem['data']);break;
			}
		}
		return $configItem;
	}
	/**
	 * 获取
	 * @param string $cname 名称
	 * @param string $key 数据键名
	 * @return mixed
	 */
	public function getConfig($cname,$key=null){
		$item=$this->where('cname',$cname)->find();
		if(!empty($item)){
			$item=$item->toArray();
			$item=$this->convertData($item);
		}
		return $key?$item[$key]:$item;
	}
	/**
	 * 设置
	 * @param string $cname 名称
	 * @param string $value 数据
	 */
	public function setConfig($cname,$value){
		$data=array('cname'=>$cname,'ctype'=>0);
		if(is_array($value)){
			$data['ctype']=2;
			$data['data']=serialize($value);
		}elseif(is_integer($value)){
			$data['ctype']=1;
			$data['data']=intval($value);
		}else{
			$data['data']=$value;
		}
		$data['dateline']=time();
		$this->insert($data,true);
	}
	/*设置版本号*/
	public function setVersion($version){
		$version=trim(strtoupper($version),'V');
		$this->setConfig('version', $version);
	}
	/*获取数据库的版本*/
	public function getVersion(){
		$dbVersion=$this->where("`cname`='version'")->find();
		if(!empty($dbVersion)){
			$dbVersion=$this->convertData($dbVersion);
			$dbVersion=$dbVersion['data'];
		}
		return $dbVersion;
	}
	/*检查图片路径*/
	public function check_img_path($imgPath){
		$return=array('success'=>false,'msg'=>'');
		if(!empty($imgPath)){
			
			if(!preg_match('/(^\w+\:)|(^[\/\\\])/i', $imgPath)){
				$return['msg']='图片目录必须为绝对路径！';
			}else{
				if(!is_dir($imgPath)){
					$return['msg']='图片目录不存在！';
				}else{
					$imgPath=realpath($imgPath);
					$root_path=rtrim(realpath(config('root_path')),'\\\/');
					if(preg_match('/^'.addslashes($root_path).'\b/i',$imgPath)){
						
						if(!preg_match('/^'.addslashes($root_path).'[\/\\\]data[\/\\\].+/i', $imgPath)){
							$return['msg']='图片保存到本程序中，目录必须在data文件夹里';
						}else{
							$return['success']=true;
						}
					}else{
						$return['success']=true;
					}
				}
			}
		}
		return $return;
	}

	/*检查图片网址*/
	public function check_img_url($imgUrl){
		$return=array('success'=>false,'msg'=>'');
		if(!empty($imgUrl)){
			if(!preg_match('/^\w+\:\/\//i',$imgUrl)){
				$return['msg']='图片链接地址必须以http://或者https://开头';
			}else{
				$return['success']=true;
			}
		}
		return $return;
	}
}
?>