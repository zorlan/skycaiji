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

use think\Loader;
class Taskgroup extends BaseController {
    /**
     * 任务分组列表
     */
    public function listAction(){
    	$page=input('p/d',1);
    	$page=max(1,$page);
    	
    	$search['parent_id']=input('parent_id/d',0);
    	$search['name']=input('name');
    	$mtaskgroup=model('Taskgroup');

    	$cond=array();
    	if($search['parent_id']>0){
    		$cond['parent_id']=$search['parent_id'];
    	}
    	if(!empty($search['name'])){
    		$cond['name']=array('like','%'.addslashes($search['name']).'%');
    	}
    	
    	$this->assign('search',$search);

    	$limit=20;
    	if($cond){
    		
    		$count=$mtaskgroup->where($cond)->count();
	    	if($count>0){
    			$parentList=$mtaskgroup->where($cond)->order('sort desc')->paginate($limit,false,paginate_auto_config());
	    	}
    	}else{
    		
	    	$cond=array('parent_id'=>0);
	    	$count=$mtaskgroup->where($cond)->count();
	    	if($count>0){
	    		$parentList=$mtaskgroup->where($cond)->order('sort desc')->paginate($limit,false,paginate_auto_config());
	    		$parentIds=array();
	    		foreach ($parentList->all() as $item){
	    			$parentIds[$item['id']]=$item['id'];
	    		}
	    		$subList1=$mtaskgroup->where(array('parent_id'=>array('in',$parentIds)))->order('sort desc')->column('*');
	    		$subList=array();
	    		foreach ($subList1 as $item){
	    			$subList[$item['parent_id']][$item['id']]=$item;
	    		}
	    		unset($subList1);
	    	}
    	}
    	if(isset($parentList)){
    		$pagenav = $parentList->render();
    		$this->assign('pagenav',$pagenav);
    		$parentList=$parentList->all();
    	}else{
    		$parentList=null;
    	}
    	
    	
    	$this->assign('parentList',$parentList);
    	$this->assign('subList',$subList);
    	
    	$parentTgList=$mtaskgroup->where(array('parent_id'=>0))->order('sort desc')->column('name','id');
    	$this->assign('parentTgList',$parentTgList);
    	
    	$GLOBALS['content_header']=lang('taskgroup_list');
    	$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Taskgroup/list'),'title'=>lang('taskgroup_list'))));
    	return $this->fetch();
    }
    /**
     * 添加任务分组
     */
    public function addAction(){
    	$mtaskgroup=model('Taskgroup');
    	if(request()->isPost()){
    		$newData=input('param.');
    		$validate=Loader::validate('Taskgroup');
    		if(!$validate->scene('add')->check($newData)){
    			
    			$this->error($validate->getError());
    		}

    		$mtaskgroup->isUpdate(false)->allowField(true)->save($newData);
    		$tgid=$mtaskgroup->id;
    		if($tgid>0){
    			$this->success(lang('op_success'),input('referer','','trim')?input('referer','','trim'):('Taskgroup/edit?id='.$tgid));
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		$parentTgList=$mtaskgroup->where(array('parent_id'=>0))->order('sort desc')->column('name','id');
    		$this->assign('parentTgList',$parentTgList);
    		
    		$GLOBALS['content_header']=lang('taskgroup_add');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Taskgroup/list'),'title'=>lang('taskgroup_list')),lang('taskgroup_add')));
    		
    		if(request()->isAjax()){
    			return view('add_ajax');
    		}else{
    			return $this->fetch('add');
    		}
    		
    	}
    }
    /**
     * 编辑任务分组
     */
    public function editAction(){
    	$mtaskgroup=model('Taskgroup');
    	$id=input('id/d',0);
    	$tgData=$mtaskgroup->getById($id);
    	if(empty($tgData)){
    		$this->error(lang('tg_none'));
    	}
    	if(request()->isPost()){
    		$newData=input('param.');
    		$validate=Loader::validate('Taskgroup');
    		if(!$validate->scene('edit')->check($newData)){
    			
    			$this->error($validate->getError());
    		}
    		if($tgData['name']!=$newData['name']){
    			
    			if($mtaskgroup->where(array('name'=>$newData['name']))->count()>0){
    				$this->error(lang('tg_error_has_name'));
    			}
    		}
    		if($newData['parent_id']>0){
    			
    			$subCount=$mtaskgroup->where(array('parent_id'=>$tgData['id']))->count();
    			if($subCount>0){
    				$this->error(lang('tg_is_parent'));
    			}
    		}
    		if($newData['parent_id']==$tgData['id']){
    			
    			unset($newData['parent_id']);
    		}
    		unset($newData['id']);
    		
    		$result=$mtaskgroup->strict(false)->where(array('id'=>intval($tgData['id'])))->update($newData);
    		if($result>=0){
    			$this->success(lang('op_success'),'Taskgroup/edit?id='.$tgData['id']);
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		$parentTgList=$mtaskgroup->where(array('parent_id'=>0))->order('sort desc')->column('name','id');
    		$this->assign('parentTgList',$parentTgList);
    		$this->assign('tgData',$tgData);
    		
    		$GLOBALS['content_header']=lang('taskgroup_edit');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Taskgroup/list'),'title'=>lang('taskgroup_list')),lang('taskgroup_edit')));
    		
    		if(request()->isAjax()){
    			return view('add_ajax');
    		}else{
    			return $this->fetch('add');
    		}
    	}
    }
    /**
     * 任务分组操作
     */
    public function opAction(){
    	$id=input('id/d',0);
    	$op=input('op');
    	
    	$ops=array('item'=>array('delete','move'),'list'=>array('deleteall','saveall'));
    	if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])){
    		
    		$this->error(lang('invalid_op'));
    	}
    	
    	$mtaskgroup=model('Taskgroup');
    	if(in_array($op,$ops['item'])){
    		
	    	$tgData=$mtaskgroup->getById($id);
	    	if(empty($tgData)){
	    		$this->error(lang('empty_data'));
	    	}
	    	$this->assign('tgData',$tgData);
    	}
    	$this->assign('op',$op);
    	$mtask=model('Task');
    	if($op=='delete'){
    		
    		if($mtaskgroup->where(array('parent_id'=>$tgData['id']))->count()>0){
    			
    			$this->error(lang('tg_exist_sub'));
    		}else{
    			
    			$mtaskgroup->where(array('id'=>$id))->delete();
    			$mtask->strict(false)->where(array('tg_id'=>$id))->update(array('tg_id'=>0));
    			$this->success(lang('delete_success'));
    		}
    	}elseif($op=='move'){
    		
    		$parentTgList=$mtaskgroup->where(array('parent_id'=>0))->column('name','id');
    		if(request()->isPost()){
    			$parent_id=input('parent_id/d',0);

    			if($parent_id>0&&$parent_id!=$tgData['parent_id']){
	    			
	    			$subCount=$mtaskgroup->where(array('parent_id'=>$tgData['id']))->count();
	    			if($subCount>0){
	    				$this->error(lang('tg_is_parent'));
	    			}
    			}

    			if($tgData['id']!=$parent_id){
    				
    				$mtaskgroup->strict(false)->where(array('id'=>intval($tgData['id'])))->update(array('parent_id'=>$parent_id));
    			}
    			$this->success(lang('op_success'),input('referer','','trim'));
    		}else{
    			$this->assign('parentTgList',$parentTgList);
    			return $this->fetch();
    		}
    	}elseif($op=='deleteall'){
    		
    		$ids=input('ids/a');
    		if(is_array($ids)&&count($ids)>0){
    			$list=$mtaskgroup->where(array('id'=>array('in',$ids)))->column('*');
    			$deleteIds=array();
    			foreach ($list as $item){
    				
    				$subCount=$mtaskgroup->where(array('parent_id'=>$item['id']))->count();
    				if($subCount==0){
    					$deleteIds[$item['id']]=$item['id'];
    				}else{
    					$hasSub=true;
    				}
    			}
    			if($deleteIds){
    				$mtaskgroup->where(array('id'=>array('in',$deleteIds)))->delete();
    				$mtask->strict(false)->where(array('tg_id'=>array('in',$deleteIds)))->update(array('tg_id'=>0));
    			}
    		}
    		$this->success(lang($hasSub?'tg_deleteall_has_sub':'op_success'));
    	}elseif($op=='saveall'){
    		
    		$ids=input('ids/a');
    		$newsort=input('newsort/a');
			if(is_array($ids)&&count($ids)>0){
	    		$ids=array_map('intval', $ids);
	    		
	    		$updateSql=' UPDATE '.$mtaskgroup->getQuery()->getTable().' SET `sort` = CASE `id` ';
	    		foreach ($ids as $tgid){
	    			$updateSql.= sprintf(" WHEN %d THEN '%s' ", $tgid, intval($newsort[$tgid]));
	    		}
	    		$updateSql.='END WHERE `id` IN ('. implode(',',$ids).')';
	    		try{
	    			$mtaskgroup->execute($updateSql);
	    		}catch (\Exception $ex){
	    			$this->error(lang('op_failed'));
	    		}
			}
    		$this->success(lang('op_success'),'list');
    	}
    }
}