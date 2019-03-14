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
class Backstage extends BaseController{
	public function indexAction(){
		$runInfo=array();
		$mcollected=model('Collected');
		$todayTime=strtotime(date('Y-m-d',time()));
		$runInfo['today_success']=$mcollected->where(array('addtime'=>array('GT',$todayTime),'target'=>array('<>','')))->count();
		$runInfo['today_error']=$mcollected->where(array('addtime'=>array('GT',$todayTime),'error'=>array('<>','')))->count();
		$runInfo['total_success']=$mcollected->where("`target` <> ''")->count();
		$runInfo['total_error']=$mcollected->where("`error` <> ''")->count();
		
		$runInfo['task_auto']=model('Task')->where('`auto`=1')->count();
		$runInfo['task_other']=model('Task')->where('`auto`=0')->count();
		
		/*服务器信息*/
		$serverInfo=array(
			'os'=>php_uname('s').' '.php_uname('r'),
			'php'=>PHP_VERSION,
			'db'=>config('database.type'),
			'version'=>$GLOBALS['config']['version']?$GLOBALS['config']['version']:constant("SKYCAIJI_VERSION"),
			'server'=>$_SERVER["SERVER_SOFTWARE"],
			'upload_max'=>ini_get('upload_max_filesize')
		);
		
		if(stripos($serverInfo['db'],'mysql')!==false){
			$dbVersion=db()->query('SELECT VERSION() as v;');
			$serverInfo['db'].=' '.($dbVersion[0]?$dbVersion[0]['v']:'');
		}
		
		$runInfo['auto_status']='良好';
		/*设置采集状态*/
		if($GLOBALS['config']['caiji']['auto']){
			
			$lastTime=cache('last_collect_time');
			$taskAutoCount=model('Task')->where('auto',1)->count();
			if($taskAutoCount<=0){
				
				$serverInfo['caiji']='<a href="'.url('Admin/Task/list').'">未设置自动采集任务</a>';
				$runInfo['auto_status']='无任务';
			}else{
				
				if($lastTime>0){
					$runInfo['auto_status']='运行良好';
					$serverInfo['caiji']='最近采集：'.date('Y-m-d H:i:s',$lastTime).' &nbsp;';
					if($GLOBALS['config']['caiji']['run']=='backstage'){
						
						if(NOW_TIME-$lastTime>60*($GLOBALS['config']['caiji']['interval']+15)){
							
							$serverInfo['caiji'].='<p class="help-block">自动采集似乎停止了，请<a href="'.
								url('Admin/Setting/caiji').'">重新保存设置</a>以便激活采集</p>';
							$runInfo['auto_status']='停止运行';
						}
					}
				}
				$serverInfo['caiji'].='<a href="javascript:;" id="a_collect_now">实时采集</a>';
			}
		}else{
			$runInfo['auto_status']='已停止';
			$serverInfo['caiji']='<a href="'.url('Admin/Setting/caiji').'">未开启自动采集</a>';
		}
		
		$upgradeDb=false;
		if(version_compare(model('Config')->getVersion(),SKYCAIJI_VERSION,'<')){
			
			$upgradeDb=true;
		}
		
		
		$timeout=NOW_TIME-(3600*24*30);
		$mcacheSource=CacheModel::getInstance('source_url');
		$mcacheSource->db()->where('dateline','<',$timeout)->delete();
		$mcacheLevel=CacheModel::getInstance('level_url');
		$mcacheLevel->db()->where('dateline','<',$timeout)->delete();

		$timeout=NOW_TIME-(3600*24);
		$mcacheCont=CacheModel::getInstance('cont_url');
		$mcacheCont->db()->where('dateline','<',$timeout)->delete();
		
		$GLOBALS['content_header']='后台管理';
		$GLOBALS['breadcrumb']=breadcrumb(array('首页'));
		
		$this->assign('runInfo',$runInfo);
		$this->assign('serverInfo',$serverInfo);
		$this->assign('upgradeDb',$upgradeDb);
		
		return $this->fetch('backstage/index');
	}
	/*实时采集*/
	public function collectAction(){
		remove_auto_collecting();
		controller('admin/Api','controller')->collectAction();
	}
	/*获取推送消息*/
	public function adminIndexAction(){
		$callback=input('?'.config('var_jsonp_handler'))?input(config('var_jsonp_handler')):config('default_jsonp_handler');
		$html=get_html('http://www.skycaiji.com/Store/Client/adminIndex?v='.SKYCAIJI_VERSION.'&'.config('var_jsonp_handler').'='.rawurlencode($callback),null,null,'utf-8');
		header('Content-Type:application/json;charset=utf-8');
		exit($html);
	}
	/*后台任务操作*/
	public function backstageTaskAction(){
		$op=input('op');
		$mcache=CacheModel::getInstance('backstage_task');
		if(empty($op)){
			
			$count0=$mcache->db()->where('ctype',0)->count();
			$count1=$mcache->db()->where('ctype',1)->count();
			
	    	$this->assign('count0',$count0);
	    	$this->assign('count1',$count1);
			return $this->fetch('bk_task');
		}elseif('count'==$op){
			
			$count=$mcache->db()->where('ctype',0)->count();
			$count=intval($count);
			$this->success('','',array('count'=>$count));
		}elseif('task0'==$op||'task1'==$op){
			
			$taskType=('task0'==$op)?0:1;
			
			$list=$mcache->db()->where('ctype',$taskType)->order('dateline desc')->paginate(10,false,paginate_auto_config());
			$pagenav=$list->render();
			$list=$list->all();
			$cacheList=array();
			if($list){
				
				foreach ($list as $k=>$v){
					$v['cname']=intval($v['cname']);
					if($taskType){
						$v['endtime']=intval($v['data']);
						$v['enddate']=date('Y-m-d H:i:s',$v['endtime']);
					}
					$cacheList[$v['cname']]=$v;
				}
				
				$list=model('Task')->where('id','in',array_keys($cacheList))->column('*','id');

				$nullIds=array();
				
				$list1=array();
				foreach ($cacheList as $k=>$v){
					if(!isset($list[$k])){
						
						$nullIds[$k]=$k;
					}else{
						
						$list1[$k]=$list[$k];
					}
				}
				$list=$list1;
				
				if(!empty($nullIds)&&is_array($nullIds)){
					
					$mcache->db()->where('cname','in',$nullIds)->delete();
				}
				
				if($taskType&&is_array($cacheList)){
					
					foreach ($cacheList as $k=>$v){
						$cond=array(
							'task_id'=>$k,
							'addtime'=>array('between',array($v['dateline'],$v['endtime']))
						);
						$cacheList[$k]['collected_count']=model('Collected')->where($cond)->count();
					}
				}
			}

			$count=$mcache->db()->where('ctype',$taskType)->count();

			$this->assign('list',$list);
			$this->assign('cacheList',$cacheList);
			$this->assign('taskType',$taskType);
			$this->assign('pagenav',$pagenav);
			$html=$this->fetch('bk_task_list')->getContent();

			$this->success('',null,array('html'=>$html,'count'=>$count));
		}elseif('collected'==$op){
			$taskId=input('tid/d');
			if($taskId<=0){
				$this->error('任务id错误');
			}
			$cache=$mcache->db()->where('cname',$taskId)->find();
			if(empty($cache)){
				$this->error('后台任务不存在');
			}
			
			$cond=array('task_id'=>$taskId);
			
			$taskStatus=$cache['ctype'];
			if(empty($taskStatus)){
				
				$cond['addtime']=array('>=',$cache['dateline']);
			}else{
				
				$cond['addtime']=array('between',array($cache['dateline'],intval($cache['data'])));
			}
			
			$list=model('Collected')->where($cond)->order('addtime desc')->paginate(10,false,paginate_auto_config());
			$pagenav=$list->render();
			$list=$list->all();
			
			$this->assign('list',$list);
			$this->assign('pagenav',$pagenav);
			$this->assign('taskStatus',$taskStatus);
			$this->assign('taskId',$taskId);
			return $this->fetch('bk_task_collected');
		}
	}
	
	/*生成js语言包文件*/
	public function createJsLangAction(){
		$langs=array();
		$langs['zh-cn']='zh-cn';
		
		foreach($langs as $lk=>$lv){
			
			$module_file=config('app_path').'/admin/lang/'.$lv.'.php';
			$module_lang=include $module_file;
			$module_lang=is_array($module_lang)?$module_lang:array();
			
			$common_file=config('app_path').'/lang/'.$lv.'.php';
			$common_lang=include $common_file;
			$common_lang=is_array($common_lang)?$common_lang:array();
	
			$tpl_lang=array_merge($common_lang,$module_lang);
	
			$tpl_lang='var tpl_lang='.json_encode($tpl_lang).';';
	
			write_dir_file(config('root_path').'/public/static/js/langs/'.$lv.'.js',$tpl_lang);
			echo "ok{$lv}<br>";
		}
	}
	/* 排查重复的语言变量 */
	public function checkRepeatLangAction() {
		$file = config ( 'app_path' ) . '/admin/lang/zh-cn.php';
		$txt = file_get_contents ( $file );
		$repeatList = array ();
		if (preg_match_all ( '/[\'\"](\w+)[\'\"]\s*\=\s*\>\s*/', $txt, $keys )) {
			$keys = $keys [1];
			foreach ( $keys as $i => $key ) {
				if (in_array ( $key, array_slice ( $keys, $i + 1 ) )) {
					$repeatList [] = $key;
				}
			}
		}
		print_r ( $repeatList );
	}
}