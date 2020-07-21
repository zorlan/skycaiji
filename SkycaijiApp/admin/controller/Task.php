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
use skycaiji\admin\model\CacheModel;

class Task extends BaseController {
    public function indexAction(){
        return $this->fetch();
    }
    /*任务列表*/
    public function listAction(){
    	$page=input('p/d',1);
    	$page=max(1,$page);
    	$show=strtolower(input('show','list'));
    	if(!in_array($show,array('list','folder','import'))){
    		$show='list';
    	}
    	
    	$mtaskgroup=model('Taskgroup');
    	$mtask=model('Task');
		
    	
    	
    	if($show=='folder'){
    		
    		$tgSelect=$mtaskgroup->getLevelSelect();
	    	$tgSelect=preg_replace('/<select[^<>]*>/i', "$0<option value=''>".lang('all')."</option>", $tgSelect);
	    	$this->assign('tgSelect',$tgSelect);




    	}elseif($show=='list'){
	    	
	    	$sortBy=input('sort','desc');
			$sortBy=($sortBy=='asc')?'asc':'desc';
			$orderKey=input('order');
			
			$this->assign('sortBy',$sortBy);
			$this->assign('orderKey',$orderKey);
			
			$orderBy=!empty($orderKey)?($orderKey.' '.$sortBy):'sort desc';
			
    		$search['tg_id']=input('tg_id');
    		$search['name']=input('name');
    		$search['module']=input('module');
    		$search['show']='list';
    		$limit=20;
    		$cond=array();
    		if(!empty($search['name'])){
    			$cond['name']=array('like','%'.addslashes($search['name']).'%');
    		}
    		if(!empty($search['module'])){
    			$cond['module']=$search['module'];
    		}
    		$this->assign('search',$search);
    		
    		if(is_numeric($search['tg_id'])){
    			
	    		if($search['tg_id']>0){
	    			
	    			$tgData=$mtaskgroup->getById($search['tg_id']);
	    			if(empty($tgData)){
	    				$this->error(lang('task_error_empty_tg'));
	    			}
	    			
	    			$subTgList=$mtaskgroup->where(array('parent_id'=>$tgData['id']))->column('name','id');
	    			$subTgList[$tgData['id']]=$tgData['name'];
	    			
	    			$cond['tg_id']=array('in',array_keys($subTgList));
	    			
	    			$this->assign('tgList',$subTgList);
	    		}else{
	    			
	    			$cond['tg_id']=0;
	    		}
	    		$taskList=$mtask->where($cond)->order($orderBy)->paginate($limit,false,paginate_auto_config());
	    		$pagenav=$taskList->render();
	    		$taskList=$taskList->all();
    		}else{
	    		$taskList=$mtask->where($cond)->order($orderBy)->paginate($limit,false,paginate_auto_config());
	    		$pagenav=$taskList->render();
	    		$taskList=$taskList->all();
	    		if(!empty($taskList)){
	    			
		    		$tgIds=array();
		    		foreach($taskList as $task){
		    			$tgIds[$task['tg_id']]=$task['tg_id'];
		    		}
		    		$tkTgList=$mtaskgroup->where(array('id'=>array('in',$tgIds)))->column('name','id');
		    		$this->assign('tgList',$tkTgList);
    			}
    		}
    		
    		$count=$mtask->where($cond)->count();

    		$this->assign('taskList',$taskList);
	    	$this->assign('pagenav',$pagenav);
	    	$tgSelect=$mtaskgroup->getLevelSelect();
	    	$tgSelect=preg_replace('/<select[^<>]*>/i', "$0<option value=''>".lang('all')."</option>", $tgSelect);
	    	$this->assign('tgSelect',$tgSelect);
    	}elseif($show=='import'){
    		
    		$count=$mtask->count();
    		$limit=20;
    		$taskList=$mtask->order('sort desc')->paginate($limit,false,paginate_auto_config());
    		$pagenav=$taskList->render();
    		$taskList=$taskList->all();
    		$this->assign('taskList',$taskList);
	    	$this->assign('pagenav',$pagenav);
    	}
    	$showChange=$show=='list'?'folder':'list';
    	$GLOBALS['_sc']['p_name']=lang('task_list').' <small><a href="'.url('Task/list?show='.$showChange).'">'.lang('task_change_'.$showChange).'</a></small>';
    	$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Task/list'),'title'=>lang('task_list'))));
	    return $this->fetch('list_'.$show);
    }
    /*任务列表，打开文件夹*/
    public function openListAction(){
    	
    	$tgid=input('tg_id/d',0);
    	$mtaskgroup=model('Taskgroup');
    	$mtask=model('Task');
    	
    	$subTgList=$mtaskgroup->where(array('parent_id'=>$tgid))->order('sort desc')->column('*');
    	$taskList=$mtask->where(array('tg_id'=>$tgid))->order('sort desc')->column('*');
    	if(!empty($subTgList)||!empty($taskList)){
    		
    		foreach ($taskList as $tk=>$tv){
    			$tv['module']=lang('task_module_'.$tv['module']);
    			$tv['addtime']=date('Y-m-d',$tv['addtime']);
    			$tv['caijitime']=$tv['caijitime']>0?date('Y-m-d H:i',$tv['caijitime']):'无';
    			$taskList[$tk]=$tv;
    		}
    		$this->success('',null,array('tgList'=>$subTgList,'taskList'=>$taskList));
    	}else{
    		$this->error();
    	}
    }
    /**
     * 添加任务
     */
    public function addAction(){
    	$mtask=model('Task');
    	if(request()->isPost()){
    		$newData=input('param.');
    		$importTaskId=input('task_id/d',0);
    		$validate=Loader::validate('Task');
    		if(!$validate->scene('add')->check($newData)){
    			
    			$this->error($validate->getError());
    		}
    		if(input('?config.img_url')){
    			$newData['config']['img_url']=input('config.img_url','','trim');
    		}
    		$newData['config']=$this->_save_config($newData['config']);
    		$newData['config']=serialize($newData['config']);
    		$newData['addtime']=NOW_TIME;
    		
    		$importColl=null;
    		$importRele=null;
    		if($importTaskId>0){
    			
    			$importTask=$mtask->where('id',$importTaskId)->find();
    			if(!empty($importTask)){
    				$importTask=$importTask->toArray();
    				
    				$importColl=model('Collector')->where(array('task_id'=>$importTask['id'],'module'=>$importTask['module']))->find();
    				$importRele=model('Release')->where(array('task_id'=>$importTask['id']))->find();

    				$newData['tg_id']=$newData['tg_id']>0?$newData['tg_id']:$importTask['tg_id'];
    				$newData['module']=$importTask['module'];
    				$newData['config']=$importTask['config'];
    			}
    		}
    		
    		$mtask->isUpdate(false)->allowField(true)->save($newData);
    		$tid=$mtask->id;
    		if($tid>0){
    			$taskData=$mtask->getById($tid);
    			
    			if($importTaskId>0){
    				
    				if(!empty($importColl)){
    					
    					$importColl=$importColl->toArray();
    					$importColl['task_id']=$taskData['id'];
    					unset($importColl['id']);
    					model('Collector')->add_new($importColl);
    				}
    				if(!empty($importRele)){
    					
    					$importRele=$importRele->toArray();
    					$importRele['task_id']=$taskData['id'];
    					$importRele['addtime']=NOW_TIME;
    					unset($importRele['id']);
						model('Release')->isUpdate(false)->allowField(true)->save($importRele);
    				}
    			}
    			
    			/*导入规则*/
    			$ruleId=input('rule_id');
    			if(!empty($taskData)&&!empty($ruleId)){
    				$this->_import_rule($taskData, $ruleId);
    			}
    			$this->success(lang('op_success'),input('referer','','trim')?input('referer','','trim'):('Task/edit?id='.$tid));
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		$mtaskgroup=model('Taskgroup');
    		$tgSelect=$mtaskgroup->getLevelSelect();

    		$GLOBALS['_sc']['p_name']=lang('task_add');
			$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Task/list'),'title'=>lang('task_list')),array('url'=>url('Task/add'),'title'=>lang('task_add'))));
    		
    		$this->assign('tgSelect',$tgSelect);
    		
    		if(request()->isAjax()){
    			return view('add_ajax');
    		}else{
    			return $this->fetch('add');
    		}
    	}
    }
    /**
     * 编辑任务
     */
    public function editAction(){
    	$id=input('id/d',0);
    	$mtask=model('Task');
    	$taskData=$mtask->getById($id);
    	if(empty($id)){
    		$this->error(lang('task_error_null_id'));
    	}
    	if(empty($taskData)){
    		$this->error(lang('task_error_empty_task'));
    	}
    	
    	if(request()->isPost()){
    		$newData=input('param.');
    		
    		$validate=Loader::validate('Task');
    		if(!$validate->scene('edit')->check($newData)){
    			
    			$this->error($validate->getError());
    		}
    		if(input('?config.img_url')){
    			$newData['config']['img_url']=input('config.img_url','','trim');
    		}
    		$newData['config']=$this->_save_config($newData['config']);
    		$newData['config']=serialize($newData['config']);
    		if($taskData['name']!=$newData['name']){
    			
    			if($mtask->where(array('name'=>$newData['name']))->count()>0){
    				$this->error(lang('task_error_has_name'));
    			}
    		}
    		unset($newData['id']);
    		
    		if($mtask->strict(false)->where(array('id'=>intval($taskData['id'])))->update($newData)>=0){
    			$taskData=$mtask->getById($taskData['id']);
    			/*导入规则*/
    			$ruleId=input('rule_id');
    			if(!empty($taskData)&&!empty($ruleId)){
    				$this->_import_rule($taskData, $ruleId);
    			}
    			$this->success(lang('op_success'),'Task/edit?id='.$taskData['id']);
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		$taskData=$taskData->getData();
    		$taskData['config']=unserialize($taskData['config']);
    		$taskData['config']=is_array($taskData['config'])?$taskData['config']:array();
    		
    		$mtaskgroup=model('Taskgroup');
    		$tgSelect=$mtaskgroup->getLevelSelect();
    		
    		$GLOBALS['_sc']['p_name']=lang('task_edit').'：'.$taskData['name'];
    		$GLOBALS['_sc']['p_nav']=breadcrumb(array(array('url'=>url('Task/list'),'title'=>lang('task_list')),array('url'=>url('Task/edit?id='.$taskData['id']),'title'=>$taskData['name'])));
    		
    		$fieldList=array();
    		$collData=model('Collector')->where(array('task_id'=>$taskData['id']))->find();
    		if(!empty($collData)&&!empty($collData['config'])){
    		    $collData['config']=unserialize($collData['config']);
    		    if(is_array($collData['config'])&&is_array($collData['config']['field_list'])){
    		        foreach($collData['config']['field_list'] as $v){
    		            $fieldList[]=$v['name'];
    		        }
    		        $fieldList=array_unique($fieldList);
    		        $fieldList=array_filter($fieldList);
    		    }
    		}
    		
    		$this->assign('tgSelect',$tgSelect);
    		$this->assign('taskData',$taskData);
    		$this->assign('fieldList',$fieldList);
    		if(request()->isAjax()){
    			return view('add_ajax');
    		}else{
    			return $this->fetch('add');
    		}
    	}
    }
    /*保持更多设置*/
    public function _save_config($config=array()){
    	$config=is_array($config)?$config:array();
    	$config['num']=intval($config['num']);
    	$config['img_path']=trim($config['img_path']);
    	$config['img_url']=trim($config['img_url']);

    	if(!empty($config['img_path'])){
    		
    		$checkImgPath=model('Config')->check_img_path($config['img_path']);
    		if(!$checkImgPath['success']){
    			$this->error($checkImgPath['msg']);
    		}
    	}
    	if(!empty($config['img_url'])){
    		
    		$checkImgUrl=model('Config')->check_img_url($config['img_url']);
    		if(!$checkImgUrl['success']){
    			$this->error($checkImgUrl['msg']);
    		}
    	}
    	return $config;
    }
    /*导入规则*/
    public function _import_rule($taskData,$ruleId){
    	$mtask=model('Task');
    	$mrule=model('Rule');
    	$mcoll=model('Collector');
    	
    	list($ruleType,$ruleId)=explode(':', $ruleId);
    	$ruleId=intval($ruleId);
    	$ruleType=strtolower($ruleType);
    	if(!empty($taskData)){
    		$name=null;
    		$module=null;
    		$config=null;
    		if('rule'==$ruleType){
    			
    			$ruleData=$mrule->getById($ruleId);
    		}elseif('collector'==$ruleType){
    			
    			$ruleData=$mcoll->getById($ruleId);
    		}elseif('file'==$ruleType){
    			
				$file=$_FILES['rule_file'];
				$fileTxt=file_get_contents($file['tmp_name']);
	    		if(preg_match('/\/\*skycaiji-collector-start\*\/(?P<coll>[\s\S]+?)\/\*skycaiji-collector-end\*\//i',$fileTxt,$ruleMatch)){
	    			$ruleData=unserialize(base64_decode(trim($ruleMatch['coll'])));
				}
    		}
    		
    		if(!empty($ruleData)){
    			$name=$ruleData['name'];
    			$module=$ruleData['module'];
    			$config=$ruleData['config'];
    		}
    		
    		$referer=input('referer','','trim')?input('referer','','trim'):url('Task/edit?id='.$taskData['id']);
			
    		if(empty($module)||(strcasecmp($module, $taskData['module'])!==0)){
    			$this->error('导入的规则模块错误',$referer);
    		}
    		if(empty($config)){
    			$this->error('导入的规则为空',$referer);
    		}
    		
    		
    		$collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$module))->find();
    		$newColl=array('name'=>$name,'module'=>$module,'task_id'=>$taskData['id'],'config'=>$config,'uptime'=>NOW_TIME);
    		if(empty($collData)){
    			$mcoll->add_new($newColl);
    		}else{
    			$mcoll->edit_by_id($collData['id'],$newColl);
    		}
    	}
    }
    
    public function opAction(){
    	$id=input('id/d',0);
    	$op=input('op');
    	
    	$ops=array('item'=>array('delete','auto'),'list'=>array('saveall'));
    	if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])){
    		
    		$this->error(lang('invalid_op'));
    	}
    	$mtask=model('Task');
    	if(in_array($op,$ops['item'])){
    		
    		$taskData=$mtask->getById($id);
    		if(empty($taskData)){
    			$this->error(lang('empty_data'));
    		}
    	}
    	$this->assign('op',$op);
    	if($op=='delete'){
    		
    		$mtask->where(array('id'=>$id))->delete();
    		model('Collector')->where('task_id',$id)->delete();
    		model('Release')->where('task_id',$id)->delete();
    		
    		$this->success(lang('delete_success'));
    	}elseif($op=='auto'){
    		$auto = min(1,input('auto/d',0));
    		$mtask->strict(false)->where(array('id'=>$taskData['id']))->update(array('auto'=>$auto));
    		$this->success(lang('op_success'));
    	}elseif($op=='saveall'){
    		
    		$newsort=input('newsort/a');
			if(is_array($newsort)&&count($newsort)>0){
				foreach ($newsort as $key=>$val){
					$mtask->strict(false)->where('id',intval($key))->update(array('sort'=>intval($val)));
				}
			}
    		$this->success(lang('op_success'),'Task/list?show='.input('show'));
    	}
    }
    /*删除后台任务*/
    public function bkdeleteAction(){
    	$taskId=input('id/d',0);
    	$mcache=CacheModel::getInstance('backstage_task');
    	$mcache->db()->where('cname',$taskId)->delete();
    	$this->success();
    }
    /*执行任务采集*/
    public function collectAction(){
    	$taskId=input('id/d',0);
    	if(input('?backstage')){
    		
    		if(!IS_CLI){
    			ignore_user_abort(true);
    			
    			if($GLOBALS['_sc']['c']['caiji']['server']=='cli'){
    				
    				cli_command_exec('collect task --task_id '.$taskId);
    				exit();
    			}
    		}
    		ignore_user_abort(true);
    		define('CLOSE_ECHO_MSG',true);
    		$this->_backstage_task($taskId);
    	}else{
    		ignore_user_abort(false);
    	}
    	$this->_collect($taskId);
    }
    /*批量执行任务采集*/
    public function collectBatchAction(){
    	$taskIds=input('ids');
    	if(empty($taskIds)){
    		$this->echo_msg('没有选中任务');
    		exit();
    	}
    	$taskIds=explode(',', $taskIds);
    	$taskIds=array_map('intval', $taskIds);
    	
    	if(input('?backstage')){
    		
    		if(!IS_CLI){
    			ignore_user_abort(true);
    			
    			if($GLOBALS['_sc']['c']['caiji']['server']=='cli'){
    				
    				cli_command_exec('collect batch --task_ids '.implode(',',$taskIds));
    				exit();
    			}
    		}
    		ignore_user_abort(true);
			define('CLOSE_ECHO_MSG',true);
    	}else{
    		ignore_user_abort(false);
    	}
    	
    	if($GLOBALS['_sc']['c']['caiji']['timeout']>0){
    		set_time_limit(60*$GLOBALS['_sc']['c']['caiji']['timeout']);
    	}else{
    		set_time_limit(0);
    	}
    	
    	$taskList=model('Task')->where('id','in',$taskIds)->column('*','id');
    	if(empty($taskList)){
    		$this->echo_msg('没有任务');
    		exit();
    	}
    	
    	$sortTasks=array();
    	foreach ($taskIds as $v){
    		$sortTasks[$v]=$taskList[$v];
    	}
    	
    	$taskList=$sortTasks;
    	unset($sortTasks);
    	
    	$this->_collect_batch($taskList);

    	$this->echo_msg('所有任务采集完毕！','green');
    }
    /*将任务标记为后台运行*/
    public function _backstage_task($taskId){
    	$GLOBALS['_sc']['backstage_task_runtime']=time();
    	
    	if(model('Task')->where('id',$taskId)->count()>0){
    		
    		$mcache=CacheModel::getInstance('backstage_task');
    		$mcache->db()->strict(false)->insert(array(
    				'cname'=>$taskId,
    				'dateline'=>$GLOBALS['_sc']['backstage_task_runtime'],
    				'ctype'=>0,
    				'data'=>''
    		),true);
    		
    		if(!isset($GLOBALS['_sc']['backstage_task_ids'])){
    			$GLOBALS['_sc']['backstage_task_ids']=array();
    		}
    		$GLOBALS['_sc']['backstage_task_ids'][$taskId]=$taskId;
    		
    		static $registered=false;
    		if(!$registered){
    			register_shutdown_function(function(){
    				
    				if(!empty($GLOBALS['_sc']['backstage_task_ids'])&&is_array($GLOBALS['_sc']['backstage_task_ids'])){
    					$mcache=\skycaiji\admin\model\CacheModel::getInstance('backstage_task');
    					$mcache->db()->strict(false)->where('cname','in',$GLOBALS['_sc']['backstage_task_ids'])->update(array('ctype'=>1,'data'=>time()));
    				}
    			});
    			$registered=true;
    		}
    	}
    }
    /*单个任务采集*/
    public function _collect($taskId){
    	static $setted_timeout=null;
    	if(!isset($setted_timeout)){
    		if($GLOBALS['_sc']['c']['caiji']['timeout']>0){
    			set_time_limit(60*$GLOBALS['_sc']['c']['caiji']['timeout']);
    		}else{
    			set_time_limit(0);
    		}
    		$setted_timeout=1;
    	}
    	
    	$mtask=model('Task');
    	$taskData=$mtask->getById($taskId);
    	if(empty($taskData)){
    		$this->echo_msg(lang('task_error_empty_task'));
    		exit();
    	}
    	$taskData=$taskData->toArray();
    	if(empty($taskData['module'])){
    		
    		$this->echo_msg(lang('task_error_null_module'));
    		exit();
    	}
    	if(!in_array($taskData['module'],config('allow_coll_modules'))){
    		$this->echo_msg(lang('coll_error_invalid_module'));
    		exit();
    	}
    	$taskData['config']=unserialize($taskData['config']);
    	model('Task')->loadConfig($taskData);
    	
    	$mcoll=model('Collector');
    	$collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
		if(empty($collData)){
			$this->echo_msg(lang('coll_error_empty_coll'));
    		exit();
		}
		$collData=$collData->toArray();
		
		$mrele=model('Release');
		$releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
    	if(empty($releData)){
			$this->echo_msg(lang('rele_error_empty_rele'));
    		exit();
		}
		$releData=$releData->toArray();
		$mtask->strict(false)->where('id',$taskData['id'])->update(array('caijitime'=>NOW_TIME));
		$acoll=controller('admin/C'.strtolower($collData['module']),'event');
		$acoll->init($collData);
		$arele=controller('admin/R'.strtolower($releData['module']),'event');
		$arele->init($releData);
		$GLOBALS['_sc']['real_time_release']=&$arele;

		if('api'==$releData['module']){
			
			$GLOBALS['_sc']['c']['caiji']['real_time']=0;
			
			
			$cacheApiData=$arele->get_cache_fields();
			if($cacheApiData!==false){
				
				
				json($cacheApiData)->send();
				exit();
			}
		}
		
		
		$all_field_list=array();
		
		$caijiNum=intval($GLOBALS['_sc']['c']['caiji']['num']);
		$taskNum=intval($taskData['config']['num']);

		if($taskNum<=0||($caijiNum>0&&$taskNum>$caijiNum)){
			
			$taskNum=$caijiNum;
		}
		
		$caijiLimit=false;
		if($taskNum>0){
			$caijiLimit=true;
		}
		if($caijiLimit){
			
			while($taskNum>0){
				$field_list=$acoll->collect($taskNum);
				if($field_list=='completed'){
					
					break;
				}elseif(is_array($field_list)&&!empty($field_list)){
					
					$all_field_list=array_merge($all_field_list,$field_list);
					$taskNum-=count((array)$field_list);
				}
				if($taskNum>0){
					$this->echo_msg('采集到'.count((array)$field_list).'条数据，还差'.$taskNum.'条','orange');
				}
			}
		}else{
			
			do{
				$field_list=$acoll->collect($taskNum);
				if(is_array($field_list)&&!empty($field_list)){
					
					$all_field_list=array_merge($all_field_list,$field_list);
				}
			}while($field_list!='completed');
		}
		
		if(empty($all_field_list)){
			$this->echo_msg('没有采集到数据','orange');
		}else{
			$this->echo_msg('采集到'.count((array)$all_field_list).'条数据','green');
			if(empty($GLOBALS['_sc']['c']['caiji']['real_time'])){
				
				$addedNum=$arele->export($all_field_list);
				$this->echo_msg('成功发布'.$addedNum.'条数据','green');
			}
		}
    }
    /*批量任务采集*/
    public function _collect_batch($taskList=array()){
    	$mtask=model('Task');
    	$mcoll=model('Collector');
    	$mrele=model('Release');
    	$caijiNum=intval($GLOBALS['_sc']['c']['caiji']['num']);
    	$caijiLimit=false;
    	if($caijiNum>0){
    		$caijiLimit=true;
    		$this->echo_msg('总共需采集'.$caijiNum.'条数据','black');
    	}
    	foreach ($taskList as $taskData){
    		$mtask->strict(false)->where('id',$taskData['id'])->update(array('caijitime'=>time()));
    		$collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
    		$releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
    		if(empty($collData)||empty($releData)){
    			
    			$this->echo_msg('任务：'.$taskData['name'].' 设置不完整','orange');
    			continue;
    		}
    		$collData=$collData->toArray();
    		$releData=$releData->toArray();
    		if($releData['module']=='api'){
    			
    			$this->echo_msg('任务：'.$taskData['name'].'，发布方式为API接口，跳过执行','orange');
    			continue;
    		}
    		if(input('?backstage')){
				
    			$this->_backstage_task($taskData['id']);
    		}
    		
    		$taskData['config']=unserialize($taskData['config']);
    		
    		$mtask->loadConfig($taskData);
    		
    		$acoll='\\skycaiji\\admin\\event\\C'.strtolower($collData['module']);
    		$acoll=new $acoll();
    		$acoll->init($collData);
    		$arele='\\skycaiji\\admin\\event\\R'.strtolower($releData['module']);
    		$arele=new $arele();
    		$arele->init($releData);
    		$GLOBALS['_sc']['real_time_release']=&$arele;
    	
    		$this->echo_msg('<div style="background:#efefef;padding:5px;margin:5px 0;text-align:center;">正在执行任务：'.$taskData['name'].'</div>','black');
    		$all_field_list=array();
    	
    		$taskNum=intval($taskData['config']['num']);
    		if($taskNum<=0||($caijiLimit&&$taskNum>$caijiNum)){
    			
    			$taskNum=$caijiNum;
    		}
    	
    		if($taskNum>0){
    			
    			while($taskNum>0){
    				$field_list=$acoll->collect($taskNum);
    				if($field_list=='completed'){
    					
    					break;
    				}elseif(is_array($field_list)&&!empty($field_list)){
    					
    					$all_field_list=array_merge($all_field_list,$field_list);
    					$taskNum-=count((array)$field_list);
    					$caijiNum-=count((array)$field_list);
    				}
    			}
    		}else{
    			do{
    				$field_list=$acoll->collect($taskNum);
    				if(is_array($field_list)&&!empty($field_list)){
    					
    					$all_field_list=array_merge($all_field_list,$field_list);
    				}
    			}while($field_list!='completed');
    		}
    		
    		if(empty($all_field_list)){
    			$this->echo_msg('任务：'.$taskData['name'].' 没有采集到数据','orange');
    		}else{
    			$this->echo_msg('任务：'.$taskData['name'].' 采集到'.count((array)$all_field_list).'条数据','green');
    	
    			if(empty($GLOBALS['_sc']['c']['caiji']['real_time'])){
    				
    				$addedNum=$arele->export($all_field_list);
    				$this->echo_msg('成功发布'.$addedNum.'条数据','green');
    			}
    		}
    		$this->echo_msg('<div style="background:#efefef;padding:5px;margin:5px 0;text-align:center;color:green;">任务：'.$taskData['name'].' 执行完毕</div>','green');
    		if($caijiLimit){
    			
    			if($caijiNum>0){
    				$this->echo_msg('还差'.$caijiNum.'条数据','orange');
    			}else{
    				
    				break;
    			}
    		}
    	}
    }
}