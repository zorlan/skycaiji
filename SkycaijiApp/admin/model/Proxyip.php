<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

namespace skycaiji\admin\model;

class Proxyip extends BaseModel {
	public $name='proxy_ip';
	public $setting;
	public function __construct($data=[]){
		parent::__construct($data);
		$this->setting=$GLOBALS['config']['proxy'];
	}
	/*转换成get_html中格式的ip*/
	public function to_proxy_ip($proxy_ip){
		$proxyIp=null;
		if(empty($proxy_ip)||empty($proxy_ip['ip'])){
			
			$proxyIp=null;
		}
		if(empty($proxy_ip['user'])){
			
			$proxyIp=$proxy_ip['ip'];
		}else{
			
			$proxyIp=array(
				$proxy_ip['ip'],
				$proxy_ip['user'],
				$proxy_ip['pwd']
			);
		}
		return $proxyIp;
	}
	/*获取可用的ip*/
	public function get_usable_ip(){
		if(!empty($this->setting['open'])){
			
			$cond=array();
			$cond['invalid']=0;
			if(!empty($this->setting['use'])){
				
				if($this->setting['use']=='num'){
					
					$cond['num']=array('lt',$this->setting['use_num']);
				}elseif($this->setting['use']=='time'){
					
					$cond['time']=array(array('eq',0),array('gt',time()-$this->setting['use_time']*60), 'or') ;
				}
			}else{
				
				$cond['num']=array('lt',1);
			}
			$proxyipData=$this->where($cond)->find();

			if(empty($proxyipData)){
				
				if(!empty($this->setting['use'])){
					
					if($this->setting['use']=='num'){
						$this->strict(false)->where('1=1')->update(array('num'=>0));
					}elseif($this->setting['use']=='time'){
						$this->strict(false)->where('1=1')->update(array('time'=>0));
					}
				}else{
					
					$this->strict(false)->where('1=1')->update(array('num'=>0));
				}
				$proxyipData=$this->where($cond)->find();
			}

			if(!empty($proxyipData)){
				
				$upData=array();
				if(!empty($this->setting['use'])){
					
					if($this->setting['use']=='num'){
						
						$upData['num']=$proxyipData['num']+1;
					}elseif($this->setting['use']=='time'){
						
						if(empty($proxyipData['time'])){
							
							$upData['time']=time();
						}
					}
				}else{
					
					$upData['num']=$proxyipData['num']+1;
				}
				$this->strict(false)->where(array('ip'=>$proxyipData['ip']))->update($upData);
			}
			return $proxyipData;
		}
		return null;
	}
	/*ip失败次数*/
	public function set_ip_failed($proxy_ip){
		if(empty($this->setting['failed'])||$this->setting['failed']<=0){
			
			return;
		}
		if(empty($proxy_ip)){
			return;
		}
		$upData=array();
		$upData['failed']=$proxy_ip['failed']+1;
		if($upData['failed']>=$this->setting['failed']){
			
			$upData['invalid']=1;
		}
		$this->strict(false)->where(array('ip'=>$proxy_ip['ip']))->update($upData);
	}
}

?>