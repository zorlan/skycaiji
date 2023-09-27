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

class Task extends CollectController {
    public function indexAction(){
        return $this->fetch();
    }
    
    public function importAction(){
        $taskList=model('Task')->order('sort desc')->paginate(20,false,paginate_auto_config());
        $pagenav=$taskList->render();
        $taskList=$taskList->all();
        $this->assign('taskList',$taskList);
        $this->assign('pagenav',$pagenav);
        return $this->fetch();
    }
    /*任务列表*/
    public function listAction(){
    	$show=strtolower(input('show',''));
    	$mcache=CacheModel::getInstance();
    	if(empty($show)){
    	    
    	    $show=$mcache->getCache('action_task_list_show','data');
    	}
    	if(!in_array($show,array('list','folder'))){
    		$show='list';
    	}
    	$mcache->setCache('action_task_list_show',$show);
    	
    	$mtaskgroup=model('Taskgroup');
    	$mtask=model('Task');
    	$mtimer=model('TaskTimer');
		
    	
    	$search=array();
    	if($show=='folder'){
    		
    		$tgSelect=$mtaskgroup->getLevelSelect();
	    	$tgSelect=preg_replace('/<select[^<>]*>/i', "$0<option value=''>".lang('all')."</option>", $tgSelect);
	    	
	    	$cacheTgIds=cache('action_task_open_list_tg_ids');
	    	$cacheTgIds=is_array($cacheTgIds)?$cacheTgIds:array();
	    	
	    	$this->assign('tgSelect',$tgSelect);
	    	$this->assign('search',$search);
	    	$this->assign('cacheTgIds',$cacheTgIds);
	    	




    	}elseif($show=='list'){
	    	
	    	$sortBy=input('sort','');
			$orderKey=input('order','');
			
			\util\Param::set_cache_action_order_by('action_task_list_order', $orderKey, $sortBy);
			
			$sortBy=($sortBy=='asc')?'asc':'desc';
			$this->assign('sortBy',$sortBy);
			$this->assign('orderKey',$orderKey);
			
			$orderBy=empty($orderKey)?'sort desc':($orderKey.' '.$sortBy);
			
			$search['num']=input('num/d');
			if($search['num']<=0){
			    
			    $search['num']=$mcache->getCache('action_task_list_num','data');
			    $search['num']=intval($search['num']);
			    if($search['num']<=0){
			        $search['num']=30;
			    }
			}
			$mcache->setCache('action_task_list_num',$search['num']);
			
    		$search['tg_id']=input('tg_id');
    		$search['name']=input('name');
    		$search['module']=input('module');
    		$search['show']='list';
    		$limit=$search['num'];
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
    		$taskList=$this->_set_tasks($taskList);
    		$this->assign('taskList',$taskList);
	    	$this->assign('pagenav',$pagenav);
	    	$this->assign('todayDate',date('Y-m-d',time()));
	    	$tgSelect=$mtaskgroup->getLevelSelect();
	    	$tgSelect=preg_replace('/<select[^<>]*>/i', "$0<option value=''>".lang('all')."</option>", $tgSelect);
	    	$this->assign('tgSelect',$tgSelect);
    	}
    	$showChange=$show=='list'?'folder':'list';
    	$this->set_html_tags(
    	    lang('task_list'),
    	    lang('task_list').' <small><a href="'.url('task/list?show='.$showChange).'" title="切换'.lang('task_change_'.$showChange).'">'.lang('task_change_'.$showChange).'</a></small>',
    	    breadcrumb(array(array('url'=>url('task/list'),'title'=>lang('task_list'))))
    	);
	    return $this->fetch('list_'.$show);
    }
    
    public function tgOpenAction(){
    	$tgid=input('tg_id/d',0);
    	$mtaskgroup=model('Taskgroup');
    	$mtask=model('Task');
    	
    	if($tgid>0){
    	    
    	    $cacheTgIds=cache('action_task_open_list_tg_ids');
    	    $cacheTgIds=is_array($cacheTgIds)?$cacheTgIds:array();
    	    if(!in_array($tgid,$cacheTgIds)){
    	        $cacheTgIds[]=$tgid;
    	    }
    	    cache('action_task_open_list_tg_ids',$cacheTgIds);
    	}
    	
    	$subTgList=$mtaskgroup->where(array('parent_id'=>$tgid))->order('sort desc')->column('*');
    	$subTgList=is_array($subTgList)?array_values($subTgList):array();
    	$taskList=$mtask->where(array('tg_id'=>$tgid))->order('sort desc')->column('*');
    	$taskList=is_array($taskList)?array_values($taskList):array();
    	if(!empty($subTgList)||!empty($taskList)){
    		
    		foreach ($taskList as $tk=>$tv){
    			$tv['module']=lang('task_module_'.$tv['module']);
    			$tv['addtime']=date('Y-m-d',$tv['addtime']);
    			$tv['caijitime']=$tv['caijitime']>0?date('Y-m-d H:i',$tv['caijitime']):'无';
    			$taskList[$tk]=$tv;
    		}
    		$taskList=$this->_set_tasks($taskList);
    		$this->assign('taskList',$taskList);
    		$this->success('','',array('tgList'=>$subTgList,'taskList'=>$taskList));
    	}else{
    		$this->error();
    	}
    }
    
    public function tgCloseAction(){
        $tgid=input('tg_id/d',0);
        if($tgid>0){
            
            $cacheTgIds=cache('action_task_open_list_tg_ids');
            $cacheTgIds=is_array($cacheTgIds)?$cacheTgIds:array();
            foreach ($cacheTgIds as $k=>$v){
                if($v==$tgid){
                    unset($cacheTgIds[$k]);
                }
            }
            cache('action_task_open_list_tg_ids',$cacheTgIds);
        }
        $this->success('','');
    }
    
    private function _set_tasks($taskList){
        if($taskList){
            
            $mtask=model('Task');
            $tids=array();
            $timerTids=array();
            foreach ($taskList as $v){
                $tids[$v['id']]=$v['id'];
                if($mtask->auto_is_timer($v['auto'])){
                    
                    $timerTids[$v['id']]=$v['id'];
                }
            }
            if($timerTids){
                $mtimer=model('TaskTimer');
                $timerList=$mtimer->getTimers($timerTids);
                foreach ($timerList as $k=>$v){
                    $timerList[$k]=$mtimer->timer_info($v);
                }
                if($timerList){
                    foreach ($taskList as $k=>$v){
                        if($timerList[$v['id']]){
                            $v['_timer_info']=htmlspecialchars('定时：'.$timerList[$v['id']]);
                        }
                        $taskList[$k]=$v;
                    }
                }
            }
            
            $mcollected=model('Collected');
            $todayTime=strtotime(date('Y-m-d',time()));
            foreach ($taskList as $k=>$v){
                $taskId=$v['id'];
                if(empty($taskId)){
                    continue;
                }
                $collected=array();
                $collected['today']=$mcollected->where(array('task_id'=>array('=',$taskId),'addtime'=>array('>',$todayTime)))->count();
                $collected['total']=$mcollected->where('task_id',$taskId)->count();
                $v['_collected_info']=$collected;
                $taskList[$k]=$v;
            }
            
            $mrele=model('Release');
            if($tids){
                $releModules=$mrele->where('task_id','in',$tids)->column('module','task_id');
                foreach ($taskList as $k=>$v){
                    if($releModules[$v['id']]){
                        $v['_rele_module']=lang('rele_module_'.$releModules[$v['id']]);
                    }
                    if(empty($v['_rele_module'])){
                        $v['_rele_module']='';
                    }
                    $taskList[$k]=$v;
                }
            }
        }
        return $taskList;
    }
    public function saveAction(){
        $mtask=model('Task');
        $taskData=null;
        $id=input('id/d',0);
        if($id>0){
            $taskData=$mtask->getById($id);
        }
        $isAdd=true;
        if(!empty($taskData)){
            $taskData['config']=$mtask->compatible_config($taskData['config']);
            $isAdd=false;
        }
        if(request()->isPost()){
            
            $newData=input('param.');
            $validate=Loader::validate('Task');
            if(!$validate->scene($isAdd?'add':'edit')->check($newData)){
                
                $this->error($validate->getError());
            }
            if(input('?config.img_url')){
                $newData['config']['img_url']=input('config.img_url','','trim');
            }
            $newData['sort']=min(intval($newData['sort']),999999);
            $newData['config']=$this->_save_config($newData['config']);
            $newData['config']=serialize($newData['config']);
            $taskTimerData=$newData['task_timer'];
            unset($newData['task_timer']);
            if($isAdd){
                
                $newData['addtime']=time();
                $importTaskId=input('task_id/d',0);
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
                            $importRele['addtime']=time();
                            unset($importRele['id']);
                            model('Release')->isUpdate(false)->allowField(true)->save($importRele);
                        }
                    }
                    
                    $this->_save_common($taskData, $taskTimerData);
                    
                    $this->success(lang('op_success'),input('referer','','trim')?input('referer','','trim'):('task/save?id='.$tid));
                }else{
                    $this->error(lang('op_failed'));
                }
            }else{
                
                if($taskData['name']!=$newData['name']){
                    
                    if($mtask->where(array('name'=>$newData['name']))->count()>0){
                        $this->error(lang('task_error_has_name'));
                    }
                }
                unset($newData['id']);
                
                if($mtask->strict(false)->where(array('id'=>intval($taskData['id'])))->update($newData)>=0){
                    $taskData=$mtask->getById($taskData['id']);
                    
                    $this->_save_common($taskData, $taskTimerData);
                    
                    $this->success(lang('op_success'),'task/save?id='.$taskData['id']);
                }else{
                    $this->error(lang('op_failed'));
                }
            }
        }else{
            $mtaskgroup=model('Taskgroup');
            $tgSelect=$mtaskgroup->getLevelSelect();
            if($isAdd){
                $this->set_html_tags(
                    lang('task_add'),
                    lang('task_add'),
                    breadcrumb(array(array('url'=>url('task/list'),'title'=>lang('task_list')),array('url'=>url('task/save'),'title'=>lang('task_add'))))
                );
            }else{
                if(input('?show_config')){
                    
                    $taskData['_show_config']=1;
                }
                
                $this->set_html_tags(
                    '任务:'.$taskData['name'],
                    lang('task_edit').'：'.$taskData['name'].'（id:'.$taskData['id'].'）',
                    breadcrumb(array(array('url'=>url('task/list'),'title'=>lang('task_list')),array('url'=>url('task/save?id='.$taskData['id']),'title'=>$taskData['name'])))
                );
                
                $fieldList=array();
                $collData=model('Collector')->where(array('task_id'=>$taskData['id']))->find();
                if(!empty($collData)&&!empty($collData['config'])){
                    $collData['config']=unserialize($collData['config']?:'');
                    if(is_array($collData['config'])&&is_array($collData['config']['field_list'])){
                        foreach($collData['config']['field_list'] as $v){
                            $fieldList[]=$v['name'];
                        }
                        $fieldList=array_unique($fieldList);
                        $fieldList=array_filter($fieldList);
                    }
                }
                $mtimer=model('TaskTimer');
                $timerData=$mtimer->getTimer($taskData['id']);
                $taskData['_task_timer']=$timerData;
                $timerInfo=$mtimer->timer_info($timerData);
                $timerInfo=$timerInfo?('<br><b>定时：</b>'.$timerInfo):'';
                
                $this->assign('taskData',$taskData);
                $this->assign('timerInfo',$timerInfo);
                $this->assign('fieldList',$fieldList);
            }
            
            $proxyGroupId=g_sc_c('proxy','group_id');
            $proxyGroupId=intval($proxyGroupId);
            
            $gConfig=array(
                'num'=>intval(g_sc_c('caiji','num')),
                'num_interval'=>intval(g_sc_c('caiji','interval')),
                'num_interval_html'=>intval(g_sc_c('caiji','interval_html')),
                'same_url'=>g_sc_c('caiji','same_url')>0?'允许':'过滤',
                'same_title'=>g_sc_c('caiji','same_title')>0?'允许':'过滤',
                'real_time'=>g_sc_c('caiji','real_time')>0?'是':'否',
                'translate'=>g_sc_c('translate','open')>0?'1':'',
                'proxy'=>g_sc_c('proxy','open')>0?'1':'',
                'proxy_group_id'=>$proxyGroupId<=0?'全部':model('ProxyGroup')->getNameById($proxyGroupId),
                
                'download_img'=>g_sc_c('download_img','download_img')>0?'1':'',
                'img_path'=>g_sc_c('download_img','img_path')?g_sc_c('download_img','img_path'):(config('root_path').DS.'data'.DS.'images'),
                'img_url'=>g_sc_c('download_img','img_url')?g_sc_c('download_img','img_url'):(config('root_website').'/data/images'),
                'img_name'=>g_sc_c('download_img','img_name'),
                'name_custom_path'=>g_sc_c('download_img','name_custom_path')?g_sc_c('download_img','name_custom_path'):'无',
                'name_custom_name'=>lang('down_img_name_custom_name_'.g_sc_c('download_img','name_custom_name')),
                'num_interval_img'=>intval(g_sc_c('download_img','interval_img')),
                
                'img_watermark'=>g_sc_c('download_img','img_watermark')>0?'1':'',
                'img_wm_logo'=>g_sc_c('download_img','img_wm_logo'),
                'img_wm_right'=>g_sc_c('download_img','img_wm_right')?g_sc_c('download_img','img_wm_right'):'0',
                'img_wm_bottom'=>g_sc_c('download_img','img_wm_bottom')?g_sc_c('download_img','img_wm_bottom'):'0',
                'img_wm_opacity'=>g_sc_c('download_img','img_wm_opacity')?g_sc_c('download_img','img_wm_opacity'):'不透明',
                'img_funcs'=>g_sc_c('download_img','img_funcs'),
                
                'download_file'=>g_sc_c('download_file','download_file')>0?'1':'',
                'file_path'=>g_sc_c('download_file','file_path')?g_sc_c('download_file','file_path'):(config('root_path').DS.'data'.DS.'files'),
                'file_url'=>g_sc_c('download_file','file_url')?g_sc_c('download_file','file_url'):(config('root_website').'/data/files'),
                'file_name'=>g_sc_c('download_file','file_name'),
                'file_custom_path'=>g_sc_c('download_file','file_custom_path')?g_sc_c('download_file','file_custom_path'):'无',
                'file_custom_name'=>lang('down_file_name_custom_name_'.g_sc_c('download_file','file_custom_name')),
                'file_interval'=>intval(g_sc_c('download_file','file_interval')),
                'file_funcs'=>g_sc_c('download_file','file_funcs'),
            );
            init_array($gConfig['img_funcs']);
            init_array($gConfig['file_funcs']);
            
            
            $this->assign('gConfig',$gConfig);
            $this->assign('tgSelect',$tgSelect);
            $this->assign('proxyGroups',model('ProxyGroup')->getAll());
            if(request()->isAjax()){
                return view('save_ajax');
            }else{
                return $this->fetch('save');
            }
        }
    }
    
    public function import_rule_fileAction(){
        if(request()->isPost()){
            $result=controller('admin/Mystore')->_upload_addon(true,'rule_file',false,false);
            if($result['success']){
                $this->success($result['msg'],'',$result);
            }else{
                $this->error($result['msg'],'',$result);
            }
        }else{
            $this->error();
        }
    }
    
    private function _save_common($taskData,$taskTimerData){
        
        $ruleId=input('rule_id');
        if(!empty($taskData)&&!empty($ruleId)){
            $this->_import_rule($taskData, $ruleId);
        }
        
        model('TaskTimer')->addTimer($taskData['id'],$taskTimerData);
        
        
        $upResult=model('Config')->upload_img_watermark_logo('img_wm_logo_upload','task'.$taskData['id']);
        if($upResult['success']&&$upResult['file_name']){
            $taskData['config']['img_wm_logo']=$upResult['file_name'];
            model('Task')->strict(false)->where(array('id'=>intval($taskData['id'])))->update(array('config'=>serialize($taskData['config'])));
        }
    }
    
    private function _save_config($config=array()){
        
        $config=is_array($config)?$config:array();
        $config['num']=empty($config['num'])?'':$config['num'];
    	$config['img_path']=trim($config['img_path']);
    	$config['img_url']=trim($config['img_url']);
    	$config['proxy_group_id']=trim($config['proxy_group_id']);
    	
    	$mconfig=model('Config');
    	
    	if(!empty($config['img_path'])){
    		
    	    $checkImgPath=$mconfig->check_img_path($config['img_path']);
    		if(!$checkImgPath['success']){
    			$this->error($checkImgPath['msg']);
    		}
    	}
    	if(!empty($config['img_url'])){
    		
    	    $checkImgUrl=$mconfig->check_img_url($config['img_url']);
    		if(!$checkImgUrl['success']){
    			$this->error($checkImgUrl['msg']);
    		}
    	}
    	
    	$checkNamePath=$mconfig->check_img_name_path($config['name_custom_path']);
    	if($config['img_name']=='custom'){
    	    
    	    if(empty($config['name_custom_path'])){
    	        if(is_empty(g_sc_c('download_img','name_custom_path'))){
    	            
    	            $this->error('请输入图片名称自定义路径');
    	        }
    	    }else{
    	        if(!$checkNamePath['success']){
    	            $this->error($checkNamePath['msg']);
    	        }
    	    }
    	}else{
    	    
    	    if(!$checkNamePath['success']){
    	        $config['name_custom_path']='';
    	    }
    	}
    	
    	$checkNameName=$mconfig->check_img_name_name($config['name_custom_name']);
    	if($config['img_name']=='custom'){
    	    
    	    if(!empty($config['name_custom_name'])&&!$checkNameName['success']){
    	        $this->error($checkNameName['msg']);
    	    }
    	}else{
    	    
    	    if(!$checkNameName['success']){
    	        $config['name_custom_name']='';
    	    }
    	}
    	
    	
    	$checkWmLogo=$mconfig->check_img_watermark_logo('img_wm_logo_upload');
    	if(!$checkWmLogo['success']){
    	    $this->error($checkWmLogo['msg']);
    	}
    	if(!is_empty($config['img_wm_opacity'],true)){
    	    
    	    $config['img_wm_opacity']=min(100,max(0,$config['img_wm_opacity']));
    	}
    	
    	$config['file_path']=trim($config['file_path']);
    	$config['file_url']=trim($config['file_url']);
    	
    	if(!empty($config['file_path'])){
    	    
    	    $checkFilePath=$mconfig->check_file_path($config['file_path']);
    	    if(!$checkFilePath['success']){
    	        $this->error($checkFilePath['msg']);
    	    }
    	}
    	if(!empty($config['file_url'])){
    	    
    	    $checkFileUrl=$mconfig->check_file_url($config['file_url']);
    	    if(!$checkFileUrl['success']){
    	        $this->error($checkFileUrl['msg']);
    	    }
    	}
    	
    	$checkNamePath=$mconfig->check_file_name_path($config['file_custom_path']);
    	if($config['file_name']=='custom'){
    	    
    	    if(empty($config['file_custom_path'])){
    	        if(is_empty(g_sc_c('download_file','file_custom_path'))){
    	            
    	            $this->error('请输入文件名称自定义路径');
    	        }
    	    }else{
    	        if(!$checkNamePath['success']){
    	            $this->error($checkNamePath['msg']);
    	        }
    	    }
    	}else{
    	    
    	    if(!$checkNamePath['success']){
    	        $config['file_custom_path']='';
    	    }
    	}
    	
    	$checkNameName=$mconfig->check_file_name_name($config['file_custom_name']);
    	if($config['file_name']=='custom'){
    	    
    	    if(!empty($config['file_custom_name'])&&!$checkNameName['success']){
    	        $this->error($checkNameName['msg']);
    	    }
    	}else{
    	    
    	    if(!$checkNameName['success']){
    	        $config['file_custom_name']='';
    	    }
    	}
    	
    	init_array($config['img_funcs']);
    	$config['img_funcs']=array_values($config['img_funcs']);
    	init_array($config['file_funcs']);
    	$config['file_funcs']=array_values($config['file_funcs']);
    	
    	return $config;
    }
    
    private function _import_rule($taskData,$ruleId){
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
    			
    		    $result=controller('admin/Mystore')->_upload_addon(true,'rule_file',false,true);
    		    if(!$result['success']){
    		        $this->error($result['msg'],'task/save?id='.$taskData['id']);
    		    }else{
    		        $ruleData=$result['ruleData'];
    		    }
    		}
    		
    		if(!empty($ruleData)){
    			$name=$ruleData['name'];
    			$module=$ruleData['module'];
    			$config=$ruleData['config'];
    		}
    		
    		$referer=input('referer','','trim')?input('referer','','trim'):('task/save?id='.$taskData['id']);
			
    		if(empty($module)||(strcasecmp($module, $taskData['module'])!==0)){
    			$this->error('导入的规则模块错误',$referer);
    		}
    		if(empty($config)){
    			$this->error('导入的规则为空',$referer);
    		}
    		
    		
    		$collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$module))->find();
    		$newColl=array('name'=>$name,'module'=>$module,'task_id'=>$taskData['id'],'config'=>$config,'uptime'=>time());
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
    		
    	    $newsort=input('newsort/a',array());
			if(is_array($newsort)&&count($newsort)>0){
			    foreach ($newsort as $key=>$val){
			        $val=min(intval($val),999999);
			        $mtask->strict(false)->where('id',intval($key))->update(array('sort'=>$val));
				}
			}
    		$this->success(lang('op_success'),'task/list?show='.input('show'));
    	}
    }
    /*删除后台任务*/
    public function bkdeleteAction(){
        $taskId=input('id/d',0);
        $taskIds=input('ids','');
    	$mcache=CacheModel::getInstance('backstage_task');
    	if($taskId){
    	    
    	    $mcache->deleteCache($taskId);
    	}
    	if($taskIds){
    	    
    	    $taskIds=explode(',', $taskIds);
    	    $mcache->deleteCache($taskIds);
    	}
    	$this->success();
    }
    /*执行任务采集*/
    public function collectAction(){
    	$taskId=input('id/d',0);
    	if(empty($taskId)){
    	    $this->error('没有选中任务');
    	}
    	
    	$this->collect_create_or_run(function()use($taskId){
    	    if(model('Task')->where('id',$taskId)->count()<=0){
    	        $this->error('没有任务');
    	    }
    	    return array($taskId);
    	},null,null,\skycaiji\admin\model\Collector::url_backstage_run());
    }
    /*批量执行任务采集*/
    public function collectBatchAction(){
    	$taskIds=input('ids');
    	if(empty($taskIds)){
    	    $this->error('没有选中任务');
    	}
    	$taskIds=explode(',', $taskIds);
    	$taskIds=array_map('intval', $taskIds);
    	
    	$this->collect_create_or_run(function()use($taskIds){
    	    $taskList=model('Task')->where('id','in',$taskIds)->column('name','id');
    	    if(empty($taskList)){
    	        $this->error('没有任务');
    	    }
    	    $sortIds=array();
    	    foreach ($taskIds as $v){
    	        if(isset($taskList[$v])){
    	            $sortIds[$v]=$v;
    	        }
    	    }
    	    return $sortIds;
    	},null,null,\skycaiji\admin\model\Collector::url_backstage_run());
    }
    
}