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

/*发布设置:diy*/
namespace skycaiji\admin\event;
class Rdiy extends Release{
	protected $rele_diy_list=array();
	/**
	 * 设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
	    $diy=\util\UnmaxPost::val('diy/a',array(),'trim');
		if(!in_array($diy['type'],array('app','code'))){
			$this->error('类型错误');
		}
		if($diy['type']=='app'){
			$diy['app']=strtolower(trim($diy['app'],'\/\\'));
			if(empty($diy['app'])){
				$this->error('请输入插件名称');
			}
			if(model('ReleaseApp')->isSystemApp($diy['app'],'diy')){
			    $this->error($diy['app'].'为系统保留名称，不能使用');
			}
			if(!model('ReleaseApp')->isRightApp($diy['app'],'diy')){
			    $this->error('插件名称必须以字母开头且由字母或数字组成');
			}
		}elseif($diy['type']=='code'){
			if(empty($diy['code'])){
				$this->error('请输入PHP代码');
			}
		}
		$config['diy']=$diy;
		return $config;
	}
	/*导出数据*/
	public function export($collFieldsList,$options=null){
		try {
			$appName='';
			$releDiy='';
			if($this->config['diy']['type']=='app'){
				
				$appName=strtolower($this->config['diy']['app']);
			}elseif($this->config['diy']['type']=='code'){
				$appName='CodeDiy';
			}
			if(!empty($appName)){
				if(model('ReleaseApp')->appFileExists($appName,'diy')){
					
					
					$releDiy=md5($appName.'__diy__'.serialize($this->release));
					if(!isset($this->rele_diy_list[$releDiy])){
						
						$this->rele_diy_list[$releDiy]=model('ReleaseApp')->appImportClass($appName,'diy');
						$this->rele_diy_list[$releDiy]->init($this->release);
					}
					$releDiy=$this->rele_diy_list[$releDiy];
				}elseif(model('ReleaseApp')->oldFileExists($appName,'diy')){
					
				    return $this->echo_msg_return(lang('release_upgrade'));
				}
			}
			if(empty($releDiy)){
			    return $this->echo_msg_return(array('没有自定义插件：%s',$appName));
			}
		}catch (\Exception $ex){
		    return $this->echo_msg_return(array('%s',$ex->getMessage()));
		}
		
		$addedNum=0;
		
		foreach ($collFieldsList as $collFieldsKey=>$collFields){
			$releDiy->db()->startTrans();
			$errorMsg=false;
			$contUrl=$collFields['url'];
			$this->init_download_config($this->task,$collFields['fields']);
			try {
				$return=$releDiy->runExport($contUrl,$collFields['fields']);
				if(empty($return)){
					
					continue;
				}
			}catch (\Exception $ex){
				$return=array();
				$errorMsg=$ex->getMessage();
				$this->echo_msg(array('%s',$ex->getMessage()));
				break;
			}
			$return=empty($return)?array('id'=>0):$return;
			if(!empty($errorMsg)){
				$return['error']=$errorMsg;
			}
			$returnData=array();
			if(!empty($return['error'])){
				
				$releDiy->db()->rollback();
				$returnData=array('id'=>0,'error'=>$errorMsg);
			}else{
				$releDiy->db()->commit();
				$returnData=$return;
				unset($returnData['error']);
				if($returnData['id']>0){
					$addedNum++;
				}else{
					$returnData['error']='数据插入失败';
				}
			}
			$this->record_collected($contUrl,$returnData,$this->release,array('title'=>$collFields['title'],'content'=>$collFields['content']));

			
			unset($collFieldsList[$collFieldsKey]['fields']);
		}
		return $addedNum;
	}
}
?>