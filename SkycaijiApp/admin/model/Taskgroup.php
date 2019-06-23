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
class Taskgroup extends BaseModel{
	/**
	 * 获取分级分组
	 */
	public function getLevelList(){
		static $list=null;
		
		if(!isset($list)){
			$level1List=$this->where('`parent_id`=0')->order('sort desc')->column('*');
			$level1Ids=array();
			foreach ($level1List as $level1){
				$level1Ids[$level1['id']]=$level1['id'];
			}
			$level2List=array();
			$cond=array();
			if(!empty($level1Ids)){
				$cond['parent_id']=array('in',$level1Ids);
			}
			
			$subList=$this->where($cond)->order('sort desc')->column('*');
			foreach ($subList as $sub){
				$level2List[$sub['parent_id']][$sub['id']]=$sub;
			}
			$list=array('level1'=>$level1List,'level2'=>$level2List);
		}
		
		return $list;
	}
	/**
	 * 获取分级分组select代码
	 */
	public function getLevelSelect($sltName='tg_id'){
		$list=$this->getLevelList();
		$html='<select name="'.$sltName.'" class="form-control">';
		$html.='<option value="0">'.lang('none').'</option>';
		foreach($list['level1'] as $tg1){
			$html.="<option value='{$tg1['id']}'>{$tg1['name']}</option>";
			if(!empty($list['level2'][$tg1['id']])){
				foreach ($list['level2'][$tg1['id']] as $tg2){
					$html.="<option value='{$tg2['id']}'>-----{$tg2['name']}</option>";
				}
			}
		}
		$html.='</select>';
		return $html;
	}
}

?>