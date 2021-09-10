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
use skycaiji\admin\model\CacheModel;
use skycaiji\admin\model\FuncApp;

class Collector extends BaseController {
    public function indexAction(){
        return $this->fetch();
    }
    
    public function setAction(){
    	$taskId=input('task_id/d',0);
    	$mtask=model('Task');
	    $mcoll=model('Collector');
    	$taskData=$mtask->getById($taskId);
    	if(empty($taskData)){
    		$this->error(lang('task_error_empty_task'));
    	}
    	if(empty($taskData['module'])){
    		
    		$this->error(lang('task_error_null_module'));
    	}
    	if(!in_array($taskData['module'],config('allow_coll_modules'))){
    		$this->error(lang('coll_error_invalid_module'));
    	}
    	$collData=$mcoll->where(array('task_id'=>$taskData['id'],'module'=>$taskData['module']))->find();
    	if(request()->isPost()){
    	    $effective=input('effective');
    	    $effectiveEdit=input('effective_edit');
    		if(empty($effective)){
    			
    			$this->error(lang('coll_error_empty_effective'));
    		}
    		$name=trim(input('name'));
    		$module=trim(input('module'));
    		$module=strtolower($module);
    		if(!in_array($module,config('allow_coll_modules'))){
    			$this->error(lang('coll_error_invalid_module'));
    		}
    		$config=input('post.config/a',array(),'trim');
    		$config=\util\Funcs::array_array_map('trim',$config);
    		
    		$acoll=controller('admin/C'.$module,'event');
    		$config=$acoll->setConfig($config);
    		
    		$newColl=array('name'=>$name,'module'=>$module,'task_id'=>$taskId,'config'=>serialize($config),'uptime'=>time());
    		$collId=0;
    		if(empty($collData)){
    		    
    			$collId=$mcoll->add_new($newColl);
    		}else{
    		    
    		    $collId=$collData['id'];
    		    if(empty($effectiveEdit)){
    		        
    		        $this->error(lang('coll_error_empty_effective'));
    		    }
    			$mcoll->edit_by_id($collId,$newColl);
    		}
    		if($collId>0){
    			$tab_link=trim(input('tab_link'),'#');
    			$this->success(lang('op_success'),'Collector/set?task_id='.$taskId.($tab_link?'&tab_link='.$tab_link:'').(input('?easymode')?'&easymode=1':''));
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		if(!empty($collData)){
	    		$collData['config']=unserialize($collData['config']);
	    		if(!is_array($collData['config'])){
	    		    $collData['config']=array();
	    		}
	    		$collData['config']=$mcoll->compatible_config($collData['config']);
    		}else{
    		    $collData=array();
    		}
    		set_g_sc('p_title',lang('coll_set').'_任务:'.$taskData['name']);
    		set_g_sc('p_name',lang('coll_set').lang('separator').lang('task_module_'.$taskData['module']));
	    	if(input('?easymode')){
	    	    set_g_sc('p_name', g_sc('p_name').' <small><a href="'.url('Collector/set?task_id='.$taskId).'" onclick="if(window.top){window.top.location.href=$(this).attr(\'href\');return false;}" title="切换普通模式">普通模式</a></small>');
	    	}else{
	    	    set_g_sc('p_name', g_sc('p_name').' <small><a href="'.url('Cpattern/easymode?task_id='.$taskId).'" title="切换简单模式">简单模式</a></small>');
	    	}
	    	
	    	set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Task/edit?id='.$taskData['id']),'title'=>lang('task').lang('separator').$taskData['name']),array('url'=>url('Collector/set?task_id='.$taskData['id']),'title'=>lang('coll_set')))));
	    	$this->assign('collData',$collData);
	    	$this->assign('taskData',$taskData);
	    	return $this->fetch();
    	}
    }
    /*列表*/
    public function listAction(){
    	$page=max(1,input('p/d',0));
    	$module=input('module');
    	$cond=array();
    	$taskCond=array();
    	if(!empty($module)){
    		$cond=array('module'=>$module);
    	}
    	
    	$mcoll=model('Collector');
    	$limit=20;
    	$count=$mcoll->where($cond)->count();
    	$collList=$mcoll->where($cond)->paginate($limit,false,paginate_auto_config()); 

    	$pagenav = $collList->render();
    	$this->assign('pagenav',$pagenav);
    	$collList=$collList->all();
    	$collList=empty($collList)?array():$collList;
    	if($count>0){
    		$taskIds=array();
    		foreach ($collList as $coll){
    			$taskIds[$coll['task_id']]=$coll['task_id'];
    		}
    		if(!empty($taskIds)){
    			
    			$taskCond['id']=array('in',$taskIds);
    			$taskNames=model('Task')->where($taskCond)->column('name','id');
    			$this->assign('taskNames',$taskNames);
    		}
    	}
    	
    	$this->assign('collList',$collList);
		return $this->fetch('list'.(input('tpl')?'_'.input('tpl'):''));
    }
    /*导出规则*/
    public function exportAction(){
    	$coll_id=input('coll_id/d',0);
    	$mcoll=model('Collector');
    	$collData=$mcoll->where(array('id'=>$coll_id))->find();
    	if(empty($collData)){
    		$this->error(lang('coll_error_empty_coll'));
    	}
    	$config=unserialize($collData['config']);
    	if(empty($config)){
    		$this->error('规则不存在');
    	}
    	$taskData=model('Task')->getById($collData['task_id']);
    	$name=($collData['name']?$collData['name']:$taskData['name']);
    	$module=strtolower($collData['module']);
    	
    	set_time_limit(600);
    	$collector=array(
    		'name'=>$name,
    		'module'=>$module,
    		'config'=>serialize($config),
    	);
    	$txt='/*skycaiji-collector-start*/'.base64_encode(serialize($collector)).'/*skycaiji-collector-end*/';
    	$name='规则_'.$name;
    	ob_start();
    	header("Expires: 0" );
    	header("Pragma:public" );
    	header("Cache-Control:must-revalidate,post-check=0,pre-check=0" );
    	header("Cache-Control:public");
    	header("Content-Type:application/octet-stream" );
    	
    	header("Content-transfer-encoding: binary");
    	header("Accept-Length: " .mb_strlen($txt));
    	if (preg_match("/MSIE/i", $_SERVER["HTTP_USER_AGENT"])) {
    		header('Content-Disposition: attachment; filename="'.urlencode($name).'.scj"');
    	}else{
    		header('Content-Disposition: attachment; filename="'.$name.'.scj"');
    	}
    	echo $txt;
    	ob_end_flush();
    }
    
    
    public function echo_msgAction(){
        $op=input('op');
        $mcache=CacheModel::getInstance();
        $cBackstageName='echo_msg_backstage';
        $cIntervalName='echo_msg_interval';
        if($op=='set_backstage'){
            
            $isBackstage=input('backstage/d',0);
            $isBackstage=$isBackstage>0?1:0;
            $mcache->setCache($cBackstageName,$isBackstage);
            if(model('Config')->server_is_cli()){
                
                $isBackstage=$isBackstage?'cli后台':'web服务器';
            }else{
                
                $isBackstage=$isBackstage?'后台':'实时';
            }
            
            $this->success('已设置为'.$isBackstage.'模式，请重试采集','');
        }elseif($op=='set_interval'){
            
            $interval=input('interval/d',2);
            $mcache->setCache($cIntervalName,$interval);
            $this->success('设置成功','');
        }elseif($op=='collect'){
            
            $jsTime=input('js_time',0);
            $jsTime=substr($jsTime,0,-3);
            $jsTime=intval($jsTime);
            
            $differSeconds=time()-$jsTime;
            
            $isBackstage=$mcache->getCache($cBackstageName,'data');
            $interval=$mcache->getCache($cIntervalName,'data');
            $interval=intval($interval);
            if($interval<=0){
                $interval=2;
            }
            $this->assign('is_backstage',$isBackstage);
            $this->assign('interval',$interval);
            $this->assign('server_is_cli',model('Config')->server_is_cli());
            $this->assign('differ_seconds',$differSeconds);
            return $this->fetch('echo_msg_collect');
        }elseif($op=='read'){
            
            $list=array();
            $logid=input('logid','');
            $line=input('line/d');
            $line=empty($line)?0:($line+1);
            
            $filename=\skycaiji\admin\model\Collector::echo_msg_log_filename($logid);
            if(file_exists($filename)){
                $max=30;
                $num=0;
                if(class_exists('SplFileObject')){
                    
                    $sfo = new \SplFileObject($filename, 'r');
                    if($sfo->seek($line)==0){
                        while (!$sfo->eof()&&$num<$max) {
                            $txt=$sfo->current();
                            $list[$sfo->key()]=$txt;
                            $sfo->next();
                            $num++;
                        }
                    }
                    $sfo=null;
                    unset($sfo);
                }else{
                    
                    $fp = fopen($filename,'r');
                    while (!feof($fp)&&$num<$max) {
                        $txt=fgets($fp);
                        if($num>=$line){
                            
                            $list[$num]=$txt;
                        }
                        $num++;
                    }
                    fclose($fp);
                    unset($fp);
                }
               
                $isEnd=false;
                foreach ($list as $k=>$txt){
                    if(!$isEnd&&strpos($txt,'data-echo-msg-is-end')!==false){
                        
                        $isEnd=true;
                    }
                    $txt=preg_replace('/<[^<>]*\bsection\b[^<>]*>/i', '', $txt);
                    $list[$k]=$txt;
                }
                if($isEnd){
                    if(file_exists($filename)){
                        unlink($filename);
                    }
                }
            }elseif($line>0){
                
                $list[$line]=\skycaiji\admin\model\Collector::echo_msg_end_js();
            }
            
            $list=array_filter($list);
            $data=array('list'=>$list,'line'=>0);
            if(!empty($list)){
                
                $data['line']=max(array_keys($list));
                $data['line']=intval($data['line']);
                if($data['line']<0){
                    $data['line']=0;
                }
            }
            
            $this->success('','',$data);
        }elseif($op=='stop'){
            
            $logid=input('logid','');
            $filename=\skycaiji\admin\model\Collector::echo_msg_log_filename($logid);
            if(file_exists($filename)){
                unlink($filename);
            }
            $this->success('','');
        }
    }
    
    public function plugin_funcAction(){
        $module=input('module');
        if(empty($module)){
            $this->error('模块错误');
        }
        $mfuncApp=new FuncApp();
        
        $cacheName='cache_plugin_func_method_'.$module;
        $cacheFuncs=cache($cacheName);
        
        $enableApps=$mfuncApp->where(array('module'=>$module,'enable'=>1))->column('uptime','app');
        
        foreach ($enableApps as $k=>$v){
            $appFilename=$mfuncApp->filename($module, $k);
            if(file_exists($appFilename)){
                $v.=','.filemtime($appFilename);
            }
            $enableApps[$k]=$v;
        }
        ksort($enableApps);
        $enableApps=md5(serialize($enableApps));
        
        $apps=array();
        if(empty($cacheFuncs)||$enableApps!=$cacheFuncs['key']||abs(time()-$cacheFuncs['time'])>3600){
            
            $appList=$mfuncApp->where(array('module'=>$module,'enable'=>1))->column('uptime','app');
            $apps=array();
            if(!empty($appList)){
                foreach ($appList as $k=>$v){
                    $appClass=$mfuncApp->get_app_class($module,$k,array('comment_cut'=>1,'method_params'=>1));
                    if(!empty($appClass['methods'])&&is_array($appClass['methods'])){
                        $apps[$k]=$appClass;
                    }
                }
            }
            cache($cacheName,array('list'=>$apps,'time'=>time(),'key'=>md5(serialize($appList))));
        }else{
            $apps=$cacheFuncs['list'];
        }
        $this->success('',null,$apps);
    }
}