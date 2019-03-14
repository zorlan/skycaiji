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

namespace skycaiji\admin\controller;

use skycaiji\admin\model\CacheModel;
class Api extends BaseController{
	/*任务api发布*/
	public function taskAction(){
		define('CLOSE_ECHO_MSG', 1);
		$taskId=input('id/d',0);
		$apiurl=input('apiurl');
		$releData=model('Release')->where(array('task_id'=>$taskId))->find();
		$releData['config']=unserialize($releData['config']);
		if($apiurl!=$releData['config']['api']['url']){
			exit('api地址错误！');
		}
		header('Content-type:text/json');
		controller('admin/Task','controller')->_collect($taskId);
	}
	/*执行采集*/
	public function collectAction(){
		if(input('?backstage')){
			
			if(!IS_CLI){
				ignore_user_abort(true);
				
				if($GLOBALS['config']['caiji']['server']=='cli'){
					
					cli_command_exec('collect auto');
					exit();
				}
			}
		}
		define('IS_COLLECTING', 1);
		$mcache=CacheModel::getInstance();
		if($mcache->getCache('auto_collecting')){
			
			$this->error('有任务正在自动采集');
		}
		$mcache->setCache('auto_collecting',1);
		register_shutdown_function('remove_auto_collecting');
		
		if(input('?backstage')||!session('user_id')){
			
			define('CLOSE_ECHO_MSG', true);
		}
		ignore_user_abort(true);
		
		if($GLOBALS['config']['caiji']['timeout']>0){
			set_time_limit(60*$GLOBALS['config']['caiji']['timeout']);
		}else{
			set_time_limit(0);
		}
		
		if(empty($GLOBALS['config']['caiji']['auto'])){
			$this->error('请先开启自动采集','Admin/Setting/caiji');
		}
		$lastCollectTime=cache('last_collect_time');
		if($GLOBALS['config']['caiji']['interval']>0){
			
			$waitTime=(60*$GLOBALS['config']['caiji']['interval'])-abs(time()-$lastCollectTime);
			if($waitTime>0){
				$this->error('再次采集需等待'.(($waitTime<60)?($waitTime.'秒'):(sprintf("%.2f", $waitTime/60).'分钟')),'Admin/Api/collect',null,$waitTime);
			}
		}
		
		$mtask=model('Task');
		$taskList=$mtask->alias('t')->join(model('Collector')->get_table_name().' c','t.id=c.task_id')
			->field('t.*')->where("t.auto=1 and t.module='pattern'")->order('t.caijitime asc')->select();
		if(empty($taskList)){
			$this->error('没有可自动采集的任务');
		}
		$taskList=collection($taskList)->toArray();
		cache('last_collect_time',time());

		controller('admin/Task','controller')->_collect_batch($taskList);
		
		$this->echo_msg('所有任务执行完毕！','green');
	}
}