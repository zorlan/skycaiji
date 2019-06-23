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

namespace skycaiji\admin\event; 
abstract class Release extends ReleaseBase{
	public $release;
	public $config;
	public $task;
	/*发布时初始化*/
	public function init($release){
		if(!is_array($release['config'])){
			$release['config']=unserialize($release['config']);
		}
		$this->release=$release;
		$this->config=$release['config'];
		
		$this->task=model('Task')->getById($this->release['task_id']);
		if(empty($this->task)){
			$this->error(lang('task_error_empty_task'));
		}
	}
	/**
	 * 优化设置页面post过来的config
	 * @param unknown $config 页面配置
	 */
	public abstract function setConfig($config);
	/**
	 * 导出数据
	 * @param unknown $collFieldsList 采集到的字段数据列表
	 * @param unknown $options 选项
	 */
	public abstract function export($collFieldsList,$options=null);
}
?>