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

class Provider extends BaseController {
	/*第三方平台*/
	public function listAction(){
		$url=input('url');
		$title=input('title');
		
		$mprovider=model('Provider');
		
		$cond=array();
		if(!empty($url)){
			$cond['url']=array('like','%'.$url.'%');
		}
		if(!empty($title)){
			$cond['title']=array('like','%'.$title.'%');
		}
		
		$list=$mprovider->where($cond)->order('sort desc')->paginate(20,false,paginate_auto_config());
		$pagenav=$list->render();
		$list=$list->all();
		
		$GLOBALS['content_header']='第三方平台';
		$GLOBALS['breadcrumb']=breadcrumb(array('第三方平台'));
		
		$this->assign('list',$list);
		$this->assign('pagenav',$pagenav);
		
		return $this->fetch();
	}
	public function deleteAction(){
		$id=input('id/d');
		if(empty($id)){
			$this->error('id不存在');
		}
		$mprovider=model('Provider');
		$mprovider->where('id',$id)->delete();
		
		$this->success();
	}
	public function enableAction(){
		$id=input('id/d');
		$enable=input('enable/d');
		if(empty($id)){
			$this->error('id不存在');
		}
		$mprovider=model('Provider');
		$mprovider->strict(false)->where('id',$id)->update(array('enable'=>$enable));
		
		$this->success();
	}
	public function saveAction(){
		$id=input('id/d');
		$mprovider=model('Provider');
		if($id>0){
			$proData=$mprovider->where('id',$id)->find();
			if(!empty($proData)){
				$proData=$proData->toArray();
			}
			$this->assign('proData',$proData);
		}
		if(request()->isPost()){
			$url=input('url','','strip_tags');
			$title=input('title');
			$sort=input('sort/d',0);
			$enable=input('enable/d',0);
			
			$domain=\skycaiji\admin\model\Provider::matchDomain($url);
			if(empty($domain)){
				$this->error('网址格式错误');
			}
			
			if(empty($proData)||strcasecmp($proData['url'], $url)!==0){
				
				if($mprovider->where('url',$url)->count()>0){
					
					$this->error('该网址已存在');
				}
			}
			
			$domainCond=array(
				'domain'=>$domain
			);
			if(!empty($proData)){
				$domainCond['id']=array('<>',$proData['id']);
			}
			if($mprovider->where($domainCond)->count()>0){
				
				$this->error($domain.' 域名已存在');
			}
			
			if(empty($title)){
				$html=get_html($url,null,array('timeout'=>3));
				if(preg_match('/<title[^<>]*>(.*?)<\/title>/i', $html,$title)){
					$title=strip_tags($title[1]);
				}else{
					$title='';
				}
			}
			
			$newData=array(
    			'url'=>$url,
    			'title'=>$title,
				'domain'=>$domain,
				'enable'=>$enable,
    			'sort'=>$sort
    		);
			if(empty($proData)){
				
    			$mprovider->isUpdate(false)->allowField(true)->save($newData);
    			$this->success('添加成功','Provider/list');
			}else{
				
				$mprovider->strict(false)->where('id',$id)->update($newData);
				$this->success('修改成功','Provider/list');
			}
		}else{
			return $this->fetch();
		}
	}
	
	public function saveallAction(){
		$newsort=input('newsort/a');
		$mprovider=model('Provider');
		if(is_array($newsort)&&count($newsort)>0){
			foreach ($newsort as $key=>$val){
				$mprovider->strict(false)->where('id',intval($key))->update(array('sort'=>intval($val)));
			}
		}
		$this->success('保存成功','Provider/list');
	}
}