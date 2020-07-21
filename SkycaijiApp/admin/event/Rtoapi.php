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

/*发布设置:调用接口*/
namespace skycaiji\admin\event;
class Rtoapi extends Release{
	protected $url_list=array();
	/**
	 * 设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
		$toapi=input('toapi/a','','trim');
		if(empty($toapi['url'])){
			$this->error('请输入接口地址');
		}
		if(empty($toapi['response']['id'])){
			$this->error('请输入响应id的健名');
		}
		
		
		$toapi['param_name']=is_array($toapi['param_name'])?$toapi['param_name']:array();
		$toapi['param_val']=is_array($toapi['param_val'])?$toapi['param_val']:array();
		$toapi['param_addon']=is_array($toapi['param_addon'])?$toapi['param_addon']:array();
		if(is_array($toapi['param_name'])){
			$toapi['param_name']=array_array_map('trim', $toapi['param_name']);
			foreach ($toapi['param_name'] as $k=>$v){
				if(empty($v)){
					
					unset($toapi['param_name'][$k]);
					unset($toapi['param_val'][$k]);
					unset($toapi['param_addon'][$k]);
				}
			}
		}
		
		$toapi['header_name']=is_array($toapi['header_name'])?$toapi['header_name']:array();
		$toapi['header_val']=is_array($toapi['header_val'])?$toapi['header_val']:array();
		if(is_array($toapi['header_name'])){
			foreach($toapi['header_name'] as $k=>$v){
				if(empty($v)){
					unset($toapi['header_name'][$k]);
					unset($toapi['header_val'][$k]);
				}
			}
		}
		
		$config['toapi']=$toapi;
		return $config;
	}
	/*导出数据*/
	public function export($collFieldsList,$options=null){
		$addedNum=0;
		if(empty($this->config['toapi']['url'])){
			$this->echo_msg('接口地址为空');
		}else{
			$urlMd5=md5($this->config['toapi']['url']);
			$url='';
			if(!isset($this->url_list[$urlMd5])){
				
				$url=$this->config['toapi']['url'];
				if(strpos($url, '/')===0){
					$url=config('root_website').$url;
				}elseif(!preg_match('/^\w+\:\/\//', $url)){
					$url='http://'.$url;
				}
				$this->url_list[$urlMd5]=$url;
			}else{
				$url=$this->url_list[$urlMd5];
			}
			$response=$this->config['toapi']['response'];
			$response=is_array($response)?$response:array();

			foreach ($collFieldsList as $collFieldsKey=>$collFields){
				
				$contTitle=$collFields['title'];
				$contUrl=$collFields['url'];
				$collFields=$collFields['fields'];
				$this->init_download_img($this->task,$collFields);
				
				$params=array();
				if(is_array($this->config['toapi']['param_name'])){
					
					foreach($this->config['toapi']['param_name'] as $k=>$pname){
						if(empty($pname)){
							
							continue;
						}
						$pval=$this->config['toapi']['param_val'][$k];
						if(empty($pval)){
							$params[$pname]=$pval;
						}elseif($pval=='custom'){
							$params[$pname]=$this->config['toapi']['param_addon'][$k];
						}elseif(preg_match('/^field\:(.+)$/ui',$pval,$fieldName)){
							
							$params[$pname]=$this->get_field_val($collFields[$fieldName[1]]);
						}
					}
				}
				if($this->config['toapi']['type']=='post'){
					
					$params=is_array($params)?$params:'';
				}else{
					
					$url.=(strpos($url,'?')===false?'?':'&').http_build_query($params);
					$params=null;
				}
				
				$headers=null;
				if(is_array($this->config['toapi']['header_name'])){
					$headers=array();
					foreach($this->config['toapi']['header_name'] as $k=>$hname){
						if(empty($hname)){
							
							continue;
						}
						$headers[$hname]=$this->config['toapi']['header_val'][$k];
					}
				}
				
				
				$charset=$this->config['toapi']['charset'];
				if($charset=='custom'){
					$charset=$this->config['toapi']['charset_custom'];
				}
				if(empty($charset)){
					$charset='utf-8';
				}
				
				$json=get_html($url,$headers,array(),$charset,$params);
				
				$json=json_decode($json,true);

				$returnData=array('id'=>'','target'=>'','desc'=>'','error'=>'');
				
				if(!empty($response['id'])&&isset($json[$response['id']])){
					
					foreach ($returnData as $k=>$v){
						
						if(isset($response[$k])){
							$returnData[$k]=$json[$response[$k]]?$json[$response[$k]]:'';
						}else{
							$returnData[$k]='';
						}
					}
					
					if($returnData['id']>0){
						$addedNum++;
						if($returnData['id']>1&&empty($returnData['target'])){
							
							$returnData['target']='编号：'.$returnData['id'];
						}
					}
				}else{
					
					$returnData['id']=0;
					$returnData['error']='发布接口无响应状态';
				}
				
				$this->record_collected($contUrl,$returnData,$this->release,$contTitle);
				
				unset($collFieldsList[$collFieldsKey]['fields']);
			}
		}
		return $addedNum;
	}
}
?>