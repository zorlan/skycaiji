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

class Usergroup extends BaseModel{
	/*获取当前组的下属等级组*/
	public function get_sub_level($groupid){
		$group=$this->getById($groupid);
		if(empty($group)){
			return null;
		}
		return $this->where('level','LT',$group['level'])->column('*');
	}
	/*等级限制：判断当前用户组等级小于等于传入的等级*/
	public function user_level_limit($level){
		if($GLOBALS['_sc']['user']['group']['level']<=$level){
			return true;
		}else{
			return false;
		}
	}
	/*是管理员账号*/
	public function is_admin($userGroup){
		if(empty($userGroup)){
			return false;
		}
		if(!empty($userGroup['founder'])||!empty($userGroup['admin'])){
			return true;
		}
		return false;
	}
}

?>