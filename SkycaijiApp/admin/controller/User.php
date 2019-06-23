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

class User extends BaseController {
    public function listAction(){
    	$muser=model('User');
    	$page=input('p/d',1);
    	$page=max(1,$page);
    	$limit=20;
    	$count=$muser->count();
    	$userList=$muser->order('uid asc')->paginate($limit,false,paginate_auto_config());
    	
    	$pagenav = $userList->render();
    	$this->assign('pagenav',$pagenav);
    	$userList=$userList->all();
    	
    	$GLOBALS['content_header']=lang('user_list');
    	$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('User/list'),'title'=>lang('user_list'))));
    	
    	$groupList=model('Usergroup')->column('*','id');
    	$this->assign('userList',$userList);
    	$this->assign('groupList',$groupList);
    	return $this->fetch();
    }
    
    public function addAction(){
    	$muser=model('User');
    	$musergroup=model('Usergroup');
    	if(request()->isPost()){
    		if(!check_usertoken()){
    			$this->error(lang('usertoken_error'));
    		}
    		
    		if($GLOBALS['config']['site']['verifycode']){
    			
    			$verifycode=trim(input('verifycode'));
    			$check=check_verify($verifycode);
    			if(!$check['success']){
    				$this->error($check['msg']);
    			}
    		}
    		
    		$newData=array(
    			'username'=>input('username'),
    			'password'=>input('password'),
    			'repassword'=>input('repassword'),
    			'email'=>input('email'),
    			'groupid'=>input('groupid/d',0)
    		);
    		$check=$muser->add_check($newData);
    		if(!$check['success']){
    			$this->error($check['msg']);
    		}
    		$newData['salt']=\skycaiji\admin\model\User::rand_salt();
    		$newData['password']=\skycaiji\admin\model\User::pwd_encrypt($newData['password'],$newData['salt']);
    		$newGroup=$musergroup->getById($newData['groupid']);
    		if($musergroup->user_level_limit($newGroup['level'])){
    			$this->error('您不能添加“'.$GLOBALS['user']['group']['name'].'”用户组');
    		}
    		$newData['regtime']=NOW_TIME;
    		$muser->isUpdate(false)->allowField(true)->save($newData);
    		if($muser->uid>0){
    			$this->success(lang('op_success'),'User/list');
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		$subGroupList=$musergroup->get_sub_level($GLOBALS['user']['groupid']);
    		$GLOBALS['content_header']=lang('user_add');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('User/list'),'title'=>lang('user_list')),lang('user_add')));
    		$this->assign('subGroupList',$subGroupList);
    		return $this->fetch();
    	}
    }
    public function editAction(){
    	$uid=input('uid/d',0);
    	if(empty($uid)){
    		$this->error(lang('user_error_null_uid'));
    	}
    	$muser=model('User');
    	$musergroup=model('Usergroup');
    	$userData=$muser->getByUid($uid);
    	if(empty($userData)){
    		$this->error(lang('user_error_empty_user'));
    	}
    	$userData['group']=$musergroup->getById($userData['groupid']);
    	
    	$isOwner=($GLOBALS['user']['uid']==$userData['uid'])?true:false;
    	if(!$isOwner&&$musergroup->user_level_limit($userData['group']['level'])){
    		
    		$this->error('您不能编辑“'.$userData['group']['name'].'”组的用户');
    	}
    	if(request()->isPost()){
    		if(!check_usertoken()){
    			$this->error(lang('usertoken_error'));
    		}
    		if($GLOBALS['config']['site']['verifycode']){
    			
    			$verifycode=trim(input('verifycode'));
    			$check=check_verify($verifycode);
    			if(!$check['success']){
    				$this->error($check['msg']);
    			}
    		}
    		
    		$newData=array(
    			'password'=>input('password'),
    			'repassword'=>input('repassword'),
    			'email'=>input('email'),
    			'groupid'=>input('groupid/d',0)
    		);
    		if(empty($newData['password'])){
    			
    			unset($newData['password']);
    			unset($newData['repassword']);
    		}
    		
    		$check=$muser->edit_check($newData);
    		if(!$check['success']){
    			$this->error($check['msg']);
    		}
    		if(!empty($newData['password'])){
    			$newData['salt']=\skycaiji\admin\model\User::rand_salt();
    			$newData['password']=\skycaiji\admin\model\User::pwd_encrypt($newData['password'],$newData['salt']);
    		}
    		$newGroup=$musergroup->getById($newData['groupid']);
    		if($musergroup->user_level_limit($newGroup['level'])){
    			$this->error('您不能改为“'.$GLOBALS['user']['group']['name'].'”用户组');
    		}
    		if($isOwner||empty($newData['groupid'])){
    			
	    		unset($newData['groupid']);
    		}
    		
    		$muser->strict(false)->where(array('uid'=>$uid))->update($newData);
    		$this->success(lang('op_success'),'User/list');
    		
    	}else{
    		$this->assign('userData',$userData);
    		$subGroupList=$musergroup->get_sub_level($GLOBALS['user']['groupid']);
    		$this->assign('subGroupList',$subGroupList);
    		$this->assign('isOwner',$isOwner);
    		$GLOBALS['content_header']=lang('user_edit');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('User/list'),'title'=>lang('user_list')),lang('user_edit')));
    		return $this->fetch();
    	}
    }
    public function deleteAction(){
    	$uid=input('uid/d',0);
    	if(empty($uid)){
    		$this->error(lang('user_error_null_uid'));
    	}
    	$muser=model('User');
    	$musergroup=model('Usergroup');
    	$userData=$muser->getByUid($uid);
    	if(empty($userData)){
    		$this->error(lang('user_error_empty_user'));
    	}
    	if($userData['uid']==$GLOBALS['user']['uid']){
    		
    		$this->error('不能删除自己');
    	}
    	$userData['group']=$musergroup->getById($userData['groupid']);
    	
    	if($musergroup->user_level_limit($userData['group']['level'])){
    		$this->error('您不能删除“'.$userData['group']['name'].'”组的用户');
    	}
    	$muser->where(array('uid'=>$uid))->delete();
    	$this->success(lang('op_success'),'User/list');
    }
}