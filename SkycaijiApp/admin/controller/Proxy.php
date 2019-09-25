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

class Proxy extends BaseController {
	public function listAction(){
   		$mproxy=model('Proxyip');
   		$cond=array();
		$search=array(
			'num'=>input('num/d',200),
			'ip'=>input('ip'),
			'user'=>input('user'),
			'pwd'=>input('pwd'),
			'type'=>input('?type')?input('type'):'all',
			'invalid'=>input('?invalid')?input('invalid'):'all',
		);
   		if(!empty($search['ip'])){
   			
   			$cond['ip']=array('like',addslashes($search['ip']).'%');
   		}
		if($search['type']!='all'){
			
			$cond['type']=$search['type'];
		}
		if($search['invalid']!='all'){
			
			$cond['invalid']=$search['invalid'];
		}
		if(!empty($search['user'])){
			
			$cond['user']=$search['user'];
		}
		if(!empty($search['pwd'])){
			
			$cond['pwd']=$search['pwd'];
		}
		
   		$count=$mproxy->where($cond)->count();
   		if($count>0){
   			
   			$dataList=$mproxy->where($cond)->order('addtime desc,no desc')->paginate($search['num'],false,paginate_auto_config());
   			 
   			$pagenav=$dataList->render();
   			$pagenav=str_replace('class="pagination', 'class="pagination pagination-sm no-margin pull-right', $pagenav);
   			
   			$this->assign('pagenav',$pagenav);
   			$dataList=$dataList->all();
   			$dataList=empty($dataList)?array():$dataList;
   			
   			
   			foreach ($dataList as $k=>$v){
   				$v=$v->toArray();
   				foreach ($v as $vk=>$vv){
   					$v[$vk]=htmlspecialchars($vv,ENT_QUOTES);
   				}
   				$dataList[$k]=$v;
   			}
			$this->assign('dataList',$dataList);
   		}
		
		$urlParams=input('param.');
		$urlParams=http_build_query($urlParams);
		
		$this->assign('proxyTypes',$mproxy->proxy_types());
   		$this->assign('search',$search);
   		$this->assign('urlParams',$urlParams);
   		return $this->fetch();
	}
	
	public function opAction(){
		$op=input('op');
		$listUrl=input('url_params','','trim');
		$listUrl='Proxy/list?'.ltrim($listUrl,'?');
		
		$mproxy=model('Proxyip');
		if($op=='delete'){
			$ip=input('ip');
			$mproxy->where('ip',$ip)->delete();
			$this->success('删除成功',$listUrl);
		}elseif($op=='delete_all'){
			
			$ips=input('ips','','trim');
			$ips=empty($ips)?array():json_decode($ips,true);
    		$ips=array_map('trim', $ips);
    		
			if(!empty($ips)){
				$mproxy->where('ip','in',$ips)->delete();
			}
			$this->success('删除成功',$listUrl);
		}elseif($op=='update_all'){
			
			$ips=input('ips','','trim');
			$ip_list=input('ip_list','','trim');
			$user_list=input('user_list','','trim');
			$pwd_list=input('pwd_list','','trim');
			$type_list=input('type_list','','trim');

			$ips=empty($ips)?array():json_decode($ips,true);
    		$ip_list=empty($ip_list)?array():json_decode($ip_list,true);
    		$user_list=empty($user_list)?array():json_decode($user_list,true);
    		$pwd_list=empty($pwd_list)?array():json_decode($pwd_list,true);
    		$type_list=empty($type_list)?array():json_decode($type_list,true);

    		$ips=array_map('trim', $ips);
    		$ip_list=array_map('trim', $ip_list);
    		$user_list=array_map('trim', $user_list);
    		$pwd_list=array_map('trim', $pwd_list);
    		$type_list=array_map('trim', $type_list);
    		
    		for($i=0;$i<count($ips);$i++){
    			
    			$mproxy->strict(false)->where('ip',$ips[$i])->update(array(
    				'ip'=>$ip_list[$i],
    				'user'=>$user_list[$i],
    				'pwd'=>$pwd_list[$i],
    				'type'=>$type_list[$i],
    			));
    		}
    		$this->success('修改成功',$listUrl);
		}
	}
	public function addAction(){
		$mproxy=model('Proxyip');
		$proxyTypes=$mproxy->proxy_types();
		if(request()->isPost()){
			$ip_list=input('ip_list/a','','trim');
			$user_list=input('user_list/a','','trim');
			$pwd_list=input('pwd_list/a','','trim');
			$type_list=input('type_list/a','','trim');
			
			if(!empty($ip_list)){
				foreach($ip_list as $k=>$v){
					$newData=array(
						'ip'=>$v,
						'user'=>$user_list[$k],
						'pwd'=>$pwd_list[$k],
						'type'=>$type_list[$k],
						'addtime'=>NOW_TIME
					);
					$mproxy->db()->strict(false)->insert($newData,true);
				}
				$this->success('添加成功');
			}else{
				$this->error('请添加ip');
			}
		}else{
			$this->assign('proxyTypes',$proxyTypes);
			return $this->fetch();
		}
		
	}
	
	/*批量添加代理*/
	public function batchAction(){
		$mproxy=model('Proxyip');
		$proxyTypes=$mproxy->proxy_types();
		if(request()->isPost()){
			$type=input('type');
			$ips=input('ips','',null);
			$fmt=input('format','','trim');
			$user=input('user','','trim');
			$pwd=input('pwd','','trim');
	
			$ipList=array();
			if(!empty($fmt)&&preg_match_all('/[^\r\n]+/',$ips,$mips)){
				foreach ($mips[0] as $ip){
					$ip=model('Proxyip')->get_format_ips($ip,$fmt,false);
					if(empty($ip)){
						continue;
					}
					$ipList[]=$ip;
				}
			}
			
			$ipList=$mproxy->ips_format2db($ipList,array(
				'type'=>$type,
				'user'=>$user,
				'pwd'=>$pwd
			));
			
			if(empty($ipList)){
				$this->error('没有匹配到数据');
			}
			
			if(input('is_test')){
				
				$ips='';
				$proxyTypes=array_flip($proxyTypes);
				foreach($ipList as $ip){
					$ips.=$ip['ip'].' - '.$proxyTypes[$ip['type']].($ip['user']?(' - '.$ip['user'].':'.$ip['pwd']):'').PHP_EOL;
				}
				$this->success($ips);
			}else{
				
				$mproxy->strict(false)->insertAll($ipList,true,500);
				$this->success('批量添加成功');
			}
		}else{
			$this->assign('proxyTypes',$proxyTypes);
			return $this->fetch();
		}
	}
	
	
	public function clearInvalidAction(){
		$mproxy=model('Proxyip');
		$mproxy->where('invalid',1)->delete();
		$this->success('清理完成','Setting/proxy');
	}

	
	public function testApiAction(){
		$config=input('config/a','','trim');
		$mproxy=model('Proxyip');
		
		$html=get_html($config['api_url']);
		$ips=$mproxy->get_format_ips($html,$config['api_format'],true);
		$ips=$mproxy->ips_format2db ( $ips, array (
			'type' => $config ['api_type'],
			'user' => $config ['api_user'],
			'pwd' => $config ['api_pwd'],
		) );
		
		
		$types=$mproxy->proxy_types();
		$types=array_flip($types);
		 
		$ipsStr='';
		foreach ($ips as $ip){
			if(empty($ip)){
				continue;
			}
			$ipsStr.=$ip['ip'].' - '.$types[$ip['type']].($ip['user']?(' - '.$ip['user'].':'.$ip['pwd']):'').PHP_EOL;
		}
		$ips=$ipsStr;
		unset($ipsStr);
		
		$this->assign('ips',$ips);
		return $this->fetch('testApi');
	}
}