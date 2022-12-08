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
   		$mproxy=model('ProxyIp');
   		$cond=array();
		$search=array(
			'num'=>input('num/d',200),
			'ip'=>input('ip'),
			'user'=>input('user'),
			'pwd'=>input('pwd'),
			'type'=>input('?type')?input('type'):'all',
		    'invalid'=>input('?invalid')?input('invalid'):'all',
		    'group_id'=>input('?group_id')?input('group_id'):'all',
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
		if($search['group_id']!='all'){
		    
		    $cond['group_id']=$search['group_id'];
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
   			if($pagenav){
   			    $pagenav=str_replace('class="pagination', 'class="pagination pagination-sm no-margin pull-right', $pagenav);
   			}
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
		
		$this->assign('proxyGroups',model('ProxyGroup')->getAll());
		$this->assign('proxyTypes',$mproxy->proxy_types());
   		$this->assign('search',$search);
   		$this->assign('urlParams',$urlParams);
   		return $this->fetch();
	}
	
	private function _input_str2json($name){
	    $data=input($name,'','trim');
	    $data=empty($data)?array():json_decode($data,true);
	    init_array($data);
	    $data=array_map('trim', $data);
	    return $data;
	}
	
	public function opAction(){
		$op=input('op');
		$listUrl=input('url_params','','trim');
		$listUrl='proxy/list?'.ltrim($listUrl,'?');
		
		$mproxy=model('ProxyIp');
		if($op=='delete'){
			$ip=input('ip');
			$mproxy->where('ip',$ip)->delete();
			$this->success('删除成功',$listUrl);
		}elseif($op=='delete_all'){
			
		    $ips=$this->_input_str2json('ips');
			if(!empty($ips)){
				$mproxy->where('ip','in',$ips)->delete();
			}
			$this->success('删除成功',$listUrl);
		}elseif($op=='update_all'){
			
		    $ips=$this->_input_str2json('ips');
			$paramNames=array('ip'=>'ip_list','user'=>'user_list','pwd'=>'pwd_list','type'=>'type_list','group_id'=>'gid_list');
			$paramDatas=array();
			foreach($paramNames as $paramField=>$paramName){
			    $paramDatas[$paramField]=$this->_input_str2json($paramName);
			}
    		for($i=0;$i<count($ips);$i++){
    			
    			$proxyData=array();
    		    foreach ($paramDatas as $paramField=>$paramData){
    		        $proxyData[$paramField]=$paramData[$i];
    		    }
    		    $mproxy->strict(false)->where('ip',$ips[$i])->update($proxyData);
    		}
    		$this->success('修改成功',$listUrl);
		}
	}
	public function addAction(){
		$mproxy=model('ProxyIp');
		$proxyTypes=$mproxy->proxy_types();
		if(request()->isPost()){
		    $ip_list=input('ip_list/a',array(),'trim');
		    $user_list=input('user_list/a',array(),'trim');
		    $pwd_list=input('pwd_list/a',array(),'trim');
		    $type_list=input('type_list/a',array(),'trim');
		    $gid_list=input('gid_list/a',array(),'intval');
			
			if(!empty($ip_list)){
			    $nowTime=time();
				foreach($ip_list as $k=>$v){
					$newData=array(
						'ip'=>$v,
						'user'=>$user_list[$k],
						'pwd'=>$pwd_list[$k],
						'type'=>$type_list[$k],
					    'group_id'=>$gid_list[$k],
					    'addtime'=>$nowTime
					);
					$mproxy->db()->strict(false)->insert($newData,true);
				}
				$this->success('添加成功');
			}else{
				$this->error('请添加ip');
			}
		}else{
		    $this->assign('proxyGroups',model('ProxyGroup')->getAll());
			$this->assign('proxyTypes',$proxyTypes);
			return $this->fetch();
		}
	}
	
	/*批量添加代理*/
	public function batchAction(){
		$mproxy=model('ProxyIp');
		$proxyTypes=$mproxy->proxy_types();
		if(request()->isPost()){
			$type=input('type');
			$ips=input('ips','',null);
			$fmt=input('format','','trim');
			$user=input('user','','trim');
			$pwd=input('pwd','','trim');
			$groupId=input('group_id/d',0);
	
			$ipList=array();
			if(!empty($fmt)&&preg_match_all('/[^\r\n]+/',$ips,$mips)){
				foreach ($mips[0] as $ip){
					$ip=model('ProxyIp')->get_format_ips($ip,$fmt,false);
					if(empty($ip)){
						continue;
					}
					$ipList[]=$ip;
				}
			}
			
			$ipList=$mproxy->ips_format2db($ipList,array(
				'type'=>$type,
				'user'=>$user,
				'pwd'=>$pwd,
			    'group_id'=>$groupId,
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
		    $this->assign('proxyGroups',model('ProxyGroup')->getAll());
			$this->assign('proxyTypes',$proxyTypes);
			return $this->fetch();
		}
	}
	
	/*清理无效ip*/
	public function clearInvalidAction(){
		$mproxy=model('ProxyIp');
		$mproxy->where('invalid',1)->delete();
		$this->success('清理完成','setting/proxy');
	}

	/*测试代理接口*/
	public function testApiAction(){
	    $config=input('config/a',array(),'trim');
		$mproxy=model('ProxyIp');
		
		$html=get_html($config['api_url']);
		$ips=$mproxy->get_format_ips($html,$config['api_format'],true);
		$ips=$mproxy->ips_format2db ( $ips, array (
			'type' => $config ['api_type'],
			'user' => $config ['api_user'],
		    'pwd' => $config ['api_pwd'],
		    'group_id' => $config ['api_group_id'],
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
	
	
	public function groupsAction(){
	    $mgroup=model('ProxyGroup');
	    $mip=model('ProxyIp');
	    if(request()->isPost()){
	        $groupIds=input('group_id/a',array(),'intval');
	        $groupSorts=input('group_sort/a',array(),'intval');
	        $groupNames=input('group_name/a',array(),'trim');
	        \util\Funcs::filter_key_val_list3($groupNames,$groupIds,$groupSorts);
	        
	        $upDatas=array();
	        $addDatas=array();
	        $addNames=array();
	        foreach ($groupIds as $k=>$groupId){
	            $groupData=array('name'=>$groupNames[$k],'sort'=>$groupSorts[$k]);
	            if($groupId>0){
	                
	                $upDatas[$groupId]=$groupData;
	            }else{
	                $addDatas[]=$groupData;
	                $addNames[$groupData['name']]=$groupData['name'];
	            }
	        }
	        if($upDatas){
	            
	            $dbNames=$mgroup->where('id','in',array_keys($upDatas))->column('name','id');
	            foreach ($dbNames as $dbId=>$dbName){
	                $upName=$upDatas[$dbId]['name'];
	                if($dbName!=$upName&&$mgroup->where('name',$upName)->count()>0){
	                    
	                   unset($upDatas[$dbId]);
	                }
	            }
	            
	            foreach ($upDatas as $upId=>$upData){
	                $mgroup->strict(false)->where('id',$upId)->update($upData);
	            }
	        }
	        if($addDatas){
	            
	            $dbNames=$mgroup->where('name','in',$addNames)->column('name','id');
	            if($dbNames){
	                
	                foreach ($addDatas as $k=>$addData){
	                    if(in_array($addData['name'], $dbNames)){
	                        unset($addDatas[$k]);
	                    }
	                }
	            }
	            $mgroup->strict(false)->insertAll($addDatas);
	        }
	        $this->success('操作成功');
	    }else{
	        $groups=$mgroup->order('sort desc')->column('id,name,sort');
	        init_array($groups);
	        $groups=array_values($groups);
	        foreach ($groups as $k=>$v){
	            $v['_ip_num']=$mip->where('group_id',$v['id'])->count();
	            $groups[$k]=$v;
	        }
	        $this->assign('groups',$groups);
	        return $this->fetch();
	    }
	}
	public function delete_groupAction(){
	    $id=input('id/d',0);
	    if($id>0){
	        model('ProxyGroup')->where('id',$id)->delete();
	        model('ProxyIp')->where('group_id',$id)->update(array('group_id'=>0));
	    }
	    $this->success('删除成功');
	}
}