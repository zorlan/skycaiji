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

class Proxyip extends BaseModel {
	public $name='proxy_ip';
	public $setting;
	public function __construct($data=[]){
		parent::__construct($data);
		$this->setting=$GLOBALS['_sc']['c']['proxy'];
	}
	/*数据库ip转换成get_html格式的ip*/
	public function to_proxy_ip($proxyDbIp){
		if(empty($proxyDbIp)||empty($proxyDbIp['ip'])){
			
			return null;
		}
		$ip=explode(':',$proxyDbIp['ip']);
		if(empty($ip[0])){
			return null;
		}
		$proxyIp=array(
			'ip'=>$ip[0],
			'port'=>$ip[1],
			'type'=>$proxyDbIp['type'],
		);
		if(!empty($proxyDbIp['user'])){
			
			$proxyIp['user']=$proxyDbIp['user'];
			$proxyIp['pwd']=$proxyDbIp['pwd'];
		}
		return $proxyIp;
	}
	/*获取可用的ip*/
	public function get_usable_ip(){
		if(!empty($this->setting['open'])){
			
			$cond=array();
			if(!empty($this->setting['use'])){
				
				if($this->setting['use']=='num'){
					
					$cond['num']=array('lt',$this->setting['use_num']);
				}elseif($this->setting['use']=='time'){
					
					$cond['time']=array(array('eq',0),array('gt',time()-$this->setting['use_time']*60), 'or') ;
				}
			}else{
				
				$cond['num']=array('lt',1);
			}
			$cond['invalid']=0;
			$proxyipData=$this->where($cond)->find();
			
			if(empty($proxyipData)&&!empty($this->setting['api']['open'])){
				
				$apiInsert=strtolower($this->setting['api']['insert']);
				if(empty($apiInsert)){
					
					if($this->where('invalid',0)->count()<=0){
						
						$this->add_api_ips($this->setting);
						$proxyipData=$this->where($cond)->find();
					}
				}elseif($apiInsert=='end'){
					
					$this->add_api_ips($this->setting);
					$proxyipData=$this->where($cond)->find();
				}
			}

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
	/*代理类型*/
	public function proxy_types(){
		return array('http(s)'=>'','socks4'=>'socks4','socks5'=>'socks5');
	}
	/*匹配格式的ip*/
	public function get_format_ips($html,$format,$multiple=false){
		if(empty($html)||empty($format)){
			return null;
		}
		$format=$this->convert_format($format);
		
		if(!$multiple){
			
			$ip=null;
			if(preg_match('/'.$format.'/i',$html,$mip)){
				$ip=array(
					'ip'=>$mip['ip'],
					'port'=>$mip['port'],
					'user'=>$mip['user'],
					'pwd'=>$mip['pwd'],
				);
			}
			return $ip;
		}else{
			
			$ips=array();
			if(preg_match_all('/'.$format.'/i',$html,$mips)){
				for($i=0;$i<count($mips[0]);$i++){
					$ips[$mips['ip'][$i].':'.$mips['port'][$i]]=array(
						'ip'=>$mips['ip'][$i],
						'port'=>$mips['port'][$i],
						'user'=>$mips['user'][$i],
						'pwd'=>$mips['pwd'][$i],
					);
				}
			}
			return $ips;
		}
		return null;
	}
	
	public function convert_format($format){
		static $list=array();
		$md5=md5($format);
		if(!isset($list[$md5])){
			$format=preg_replace('/\\\*([\'\/])/',"\\\\$1",$format);
			$format=str_replace(array('[ip]','[端口]','[用户名]','[密码]','(*)')
				,array('(?P<ip>(\d+\.){3}\d+)','(?P<port>\d+)','(?P<user>[^\s\\\'\"\<\>\,]*)','(?P<pwd>[^\s\\\'\"\<\>\,]*)','[\s\S]*?')
				,$format);
			
			$list[$md5]=$format;
		}else{
			$format=$list[$md5];
		}
		return $format;
	}
	
	public function ips_format2db($ipList,$default=array()){
		$ipList=is_array($ipList)?$ipList:array();
		$default=is_array($default)?$default:array();
		foreach ($ipList as $k=>$ip){
			if(empty($ip)||empty($ip['ip'])){
				unset($ipList[$k]);
				continue;
			}
			if(empty($ip['user'])){
				$ip['user']=$default['user'];
				
				$ip['pwd']=$default['pwd'];
			}
			$ip['ip']=$ip['ip'].':'.$ip['port'];
			$ip['type']=$default['type'];
			$ip['addtime']=NOW_TIME;
			unset($ip['port']);
			$ipList[$k]=$ip;
		}
		return $ipList;
	}
	
	
	private function add_api_ips(){
		$config=$this->setting;
		if(!is_array($config)||empty($config['api'])||empty($config['api']['open'])||!is_array($config['apis'])){
			return;
		}
		foreach ($config['apis'] as $api){
			if(empty($api['api_url'])||!preg_match('/^\w+\:\/\//',$api['api_url'])||empty($api['api_format'])){
				
				continue;
			}
			$timeout=intval($api['api_interval']);
			$timeout=$timeout>0?$timeout*60:60;
			$cname=md5($api['api_url']);
			$mcahce=CacheModel::getInstance('proxy_api');
			if($mcahce->expire($cname,$timeout)){
				
				$mcahce->setCache($cname, 1);
				$html=get_html($api['api_url']);
				$ips=$this->get_format_ips($html, $api['api_format'],true);
				$ips = $this->ips_format2db ( $ips, array (
					'type' => $api ['api_type']?$api ['api_type']:'',
					'user' => $api ['api_user']?$api ['api_user']:'',
					'pwd' => $api ['api_pwd']?$api ['api_pwd']:'',
				) );
				
				if(!empty($ips)){
					
					$this->strict(false)->insertAll($ips,true,500);
				}
			}
		}
	}
}

?>