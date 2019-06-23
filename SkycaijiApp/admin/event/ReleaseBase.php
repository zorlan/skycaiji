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
use skycaiji\admin\model\CacheModel;
class ReleaseBase extends \skycaiji\admin\controller\BaseController{
	/*已采集记录*/
	public function record_collected($url,$returnData,$release,$title=null,$echo=true){
		if($returnData['id']>0){
			
			model('Collected')->insert(array(
				'url' => $url,
				'urlMd5' => md5 ( $url ),
				'titleMd5'=>empty($title)?'':md5($title),
				'target' => $returnData['target'],
				'desc' => $returnData['desc']?$returnData['desc']:'',
				'error'=>'',
				'task_id' => $release ['task_id'],
				'release' => $release['module'],
				'addtime'=>time()
			));
			if(!empty($returnData['target'])){
				$target=$returnData['target'];
				if(preg_match('/^http(s){0,1}\:\/\//i',$target)){
					$target='<a href="'.$target.'" target="_blank">'.$target.'</a>';
				}
				$this->echo_msg("成功将<a href='{$url}' target='_blank'>内容</a>发布至：{$target}",'green',$echo);
			}else{
				$this->echo_msg("成功发布：<a href='{$url}' target='_blank'>{$url}</a>",'green',$echo);
			}
		}else{
			
			if(!empty($returnData['error'])){
				
				
				if(model('Collected')->getCountByUrl($url)<=0){
					
					model('Collected')->insert(array(
						'url' => $url,
						'urlMd5' => md5 ( $url ),
						'titleMd5'=>'',
						'target' => '',
						'desc'=>'',
						'error' => $returnData['error'],
						'task_id' => $release ['task_id'],
						'release' => $release['module'],
						'addtime'=>time()
					));
				}

				$this->echo_msg($returnData['error']."：<a href='{$url}' target='_blank'>{$url}</a>",'red',$echo);
			}
		}
		
		
		static $mcacheCont=null;
		if(!isset($mcacheCont)){
			$mcacheCont=CacheModel::getInstance('cont_url');
		}
		$mcacheCont->db()->where('cname',md5($url))->delete();
	}
	
	/*获取字段值*/
	public function get_field_val($collFieldVal){
		if(empty($collFieldVal)){
			return '';
		}
		$val=$collFieldVal['value'];
		if(!empty($GLOBALS['config']['caiji']['download_img'])){
			
			if(!empty($collFieldVal['img'])){
				
				if(!is_array($collFieldVal['img'])){
					
					$collFieldVal['img']=array($collFieldVal['img']);
				}
				$total=count($collFieldVal['img']);
				if($total>0){
					$this->echo_msg('正在下载图片','black');
				}
				$curI=0;
				foreach ($collFieldVal['img'] as $imgUrl){
					$newImgUrl=$this->download_img($imgUrl);
					if($newImgUrl!=$imgUrl){
						
						$val=str_replace($imgUrl, $newImgUrl, $val);
					}
					$curI++;
					if($curI<$total){
						
						if(!empty($GLOBALS['config']['caiji']['img_interval'])){
							sleep($GLOBALS['config']['caiji']['img_interval']);
						}
					}
				}
			}
		}
		return $val;
	}
	/*下载图片*/
	public function download_img($url){
		static $imgPaths=array();
		static $imgUrls=array();
		
		$img_path=$GLOBALS['config']['caiji']['img_path'];
		$img_url=$GLOBALS['config']['caiji']['img_url'];
		
		if(!isset($imgPaths[$img_path])){
			if(empty($img_path)){
				
				$imgPaths[$img_path]=config('root_path').'/data/images/';
			}else{
				
				$imgPaths[$img_path]=rtrim($img_path,'\/\\').'/';
			}
		}
		$img_path=$imgPaths[$img_path];
		
		if(!isset($imgUrls[$img_url])){
			if(empty($img_url)){
				
				$imgUrls[$img_url]=config('root_website').'/data/images/';
			}else{
				
				$imgUrls[$img_url]=rtrim($img_url,'\/\\').'/';
			}
		}
		$img_url=$imgUrls[$img_url];
		
		if(empty($url)){
			return '';
		}
		if(!preg_match('/^\w+\:\/\//',$url)){
			
			return $url;
		}
		static $imgList=array();
		$key=md5($url);
		if(!isset($imgList[$key])){
			
			if(preg_match('/\.(jpg|jpeg|gif|png|bmp)\b/i',$url,$prop)){
				$prop=strtolower($prop[1]);
			}else{
				$prop='jpg';
			}
			$filename='';
			$imgurl='';
			$imgname='';
			
			if('url'==$GLOBALS['config']['caiji']['img_name']){
				
				$imgname=substr($key,0,2).'/'.substr($key,-2,2).'/';
			}else{
				
				$imgname=date('Y-m-d',NOW_TIME).'/';
			}
			$imgname.=$key.'.'.$prop;
			$filename=$img_path.$imgname;
			$imgurl=$img_url.$imgname;
			
			if(!file_exists($filename)){
				
				$mproxy=model('Proxyip');
				try {
					$options=array();
					if(!empty($GLOBALS['config']['caiji']['img_timeout'])){
						
						$options['timeout']=$GLOBALS['config']['caiji']['img_timeout'];
					}
					if(!empty($GLOBALS['config']['proxy']['open'])){
						
						$proxy_ip=$mproxy->get_usable_ip();
						$proxyIp=$mproxy->to_proxy_ip($proxy_ip);
						if(!empty($proxyIp)){
							
							$options['proxy']=$proxyIp;
						}
					}
					if(!empty($GLOBALS['config']['caiji']['img_max'])){
						
						$options['max_bytes']=intval($GLOBALS['config']['caiji']['img_max'])*1024*1024;
					}
					
					$imgCode=get_html($url,null,$options,'utf-8');
					
					if(!empty($imgCode)){
						
						if(write_dir_file($filename,$imgCode)){
							$imgList[$key]=$imgurl;
						}
					}
					
				}catch (\Exception $ex){
					
				}
			}else{
				
				$imgList[$key]=$imgurl;
			}
		}
		return empty($imgList[$key])?$url:$imgList[$key];
	}
	/*获取采集器字段*/
	public function get_coll_fields($taskId,$taskModule){
		static $fieldsList=array();
		$key=$taskId.'__'.$taskModule;
		if(!isset($fieldsList[$key])){
			$mcoll=model('Collector');
			$collData=$mcoll->where(array('task_id'=>$taskId,'module'=>$taskModule))->find();
			if(!empty($collData)){
				$collData=$collData->toArray();
				$collData['config']=unserialize($collData['config']);
				$collFields=array();
				if(is_array($collData['config']['field_list'])){
					foreach ($collData['config']['field_list'] as $collField){
						$collFields[]=$collField['name'];
					}
				}
				$fieldsList[$key]=$collFields;
			}
		}
		return $fieldsList[$key];
	}
	/*隐藏采集字段（删除字段）*/
	public function hide_coll_fields($hideFields,&$collFields){
		if(!empty($hideFields)&&!empty($collFields)&&is_array($hideFields)){
			foreach ($collFields as $k=>$v){
				foreach ($hideFields as $hideField){
					unset($collFields[$k]['fields'][$hideField]);
				}
			}
		}
	}
	
	/*utf8转换成其他编码*/
	public function utf8_to_charset($charset,$val){
		static $chars=array('utf-8','utf8','utf8mb4');
		if(!in_array(strtolower($charset),$chars)){
			if(!empty($val)){
				$val=iconv('utf-8',$charset.'//IGNORE',$val);
			}
		}
		return $val;
	}
	/*任意编码转换成utf8*/
	public function auto_convert2utf8($arr){
		$arr=array_array_map('auto_convert2utf8',$arr);
		return $arr;
	}
	/*写入文件*/
	public function write_file($filename,$data){
		return write_dir_file($filename,$data);
	}
}
?>