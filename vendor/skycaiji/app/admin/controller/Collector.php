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
        if(request()->isPost()){
            
            \util\UnmaxPost::init_post_data('_post_data_');
            $taskId=\util\UnmaxPost::val('task_id/d',0);
        }
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
    	    $effective=\util\UnmaxPost::val('effective');
    	    $effectiveEdit=\util\UnmaxPost::val('effective_edit');
    		if(empty($effective)){
    			
    			$this->error(lang('coll_error_empty_effective'));
    		}
    		$name=trim(\util\UnmaxPost::val('name'));
    		$module=trim(\util\UnmaxPost::val('module'));
    		$module=strtolower($module);
    		if(!in_array($module,config('allow_coll_modules'))){
    			$this->error(lang('coll_error_invalid_module'));
    		}
    		$config=\util\UnmaxPost::val('config/a',array(),'trim');
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
    		    $tabLink=trim(\util\UnmaxPost::val('tab_link'),'#');
    		    $tabLink=$tabLink?('&tab_link='.$tabLink):'';
    		    $isEasymode=\util\UnmaxPost::val('easymode');
    		    $isEasymode=$isEasymode?'&easymode=1':'';
    		    $this->success(lang('op_success'),'collector/set?task_id='.$taskId.$tabLink.$isEasymode);
    		}else{
    			$this->error(lang('op_failed'));
    		}
    	}else{
    		if(!empty($collData)){
    		    $collData['config']=unserialize($collData['config']?:'');
	    		if(!is_array($collData['config'])){
	    		    $collData['config']=array();
	    		}
	    		$collData['config']=$mcoll->compatible_config($collData['config']);
    		}else{
    		    $collData=array();
    		}
    		
    		$htmlTagName=lang('coll_set').lang('separator').lang('task_module_'.$taskData['module']);
	    	if(input('easymode')){
	    	    $htmlTagName.=' <small><a href="'.url('collector/set?task_id='.$taskId).'" onclick="if(window.top){window.top.location.href=$(this).attr(\'href\');return false;}" title="切换普通模式">普通模式</a></small>';
	    	}else{
	    	    $htmlTagName.=' <small><a href="'.url('cpattern/easymode?task_id='.$taskId).'" title="切换简单模式">简单模式</a></small>';
	    	}
	    	$this->set_html_tags(
	    	    '任务:'.$taskData['name'].'_'.lang('coll_set'),
	    	    $htmlTagName,
	    	    breadcrumb(array(array('url'=>url('task/save?id='.$taskData['id']),'title'=>lang('task').lang('separator').$taskData['name']),array('url'=>url('collector/set?task_id='.$taskData['id']),'title'=>lang('coll_set'))))
	    	);
	    	
	    	$this->assign('collData',$collData);
	    	$this->assign('taskData',$taskData);
	    	
	    	
	    	$tabLink=input('tab_link','');
	    	if(empty($tabLink)||$tabLink=='coll_pattern_coll'){
	    	    
	    	    $tabLink='';
	    	}
	    	$curTab=array($tabLink=>' class="active"');
	    	$curTabCont=array($tabLink=>' in active');
	    	$this->assign('curTab',$curTab);
	    	$this->assign('curTabCont',$curTabCont);
	    	
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
    	$config=unserialize($collData['config']?:'');
    	if(empty($config)){
    		$this->error('规则不存在');
    	}
    	$taskData=model('Task')->getById($collData['task_id']);
    	$name=$collData['name']?$collData['name']:$taskData['name'];
    	$module=strtolower($collData['module']);
    	
    	set_time_limit(600);
    	$collector=array(
    	    'name'=>$name,
    	    'module'=>$module,
    	    'config'=>serialize($config),
    	);
    	$exportName='规则_'.$name;
    	$exportTxt='/*skycaiji-collector-start*/'.base64_encode(serialize($collector)).'/*skycaiji-collector-end*/';
    	
    	
    	$funcList=array('contentSign'=>array(),'process'=>array(),'processIf'=>array());
    	
    	$contentSignFuncPages=array('front_urls','source_url','level_urls','url','relation_urls');
    	foreach ($contentSignFuncPages as $page){
    	    $pageConfigList=array();
    	    if($page=='source_url'){
    	        
    	        $pageConfigList=array(
    	            $config['source_config']
    	        );
    	    }elseif($page=='url'){
    	        
    	        $pageConfigList=array(
    	            $config
    	        );
    	    }else{
    	        $pageConfigList=$config[$page];
    	    }
    	    if(is_array($pageConfigList)){
    	        foreach ($pageConfigList as $pageConfig){
    	            if(is_array($pageConfig['content_signs'])){
    	                foreach ($pageConfig['content_signs'] as $v){
    	                    if(is_array($v)&&$v['func']){
    	                        $funcList['contentSign'][$v['func']]=$v['func'];
    	                    }
    	                }
    	            }
    	        }
    	    }
    	}
    	
    	$processList=is_array($config['field_process'])?$config['field_process']:array();
    	if(is_array($config['common_process'])){
    	    
    	    $processList[]=$config['common_process'];
    	}
    	if($processList){
    	    foreach ($processList as $process){
    	        if(is_array($process)){
    	            foreach ($process as $v){
    	                if(is_array($v)){
    	                    if($v['module']=='func'){
    	                        $funcList['process'][$v['func_name']]=$v['func_name'];
    	                    }elseif($v['module']=='if'){
    	                        if(is_array($v['if_addon'])&&is_array($v['if_addon']['func'])){
    	                            $funcList['processIf']=array_merge($funcList['processIf'],$v['if_addon']['func']);
    	                        }
    	                    }
    	                }
    	            }
    	        }
    	    }
    	}
    	
    	$hasFunc=false;
    	foreach ($funcList as $funcModule=>$funcs){
    	    if(is_array($funcs)){
    	        foreach ($funcs as $k=>$v){
    	            if($v&&strpos($v,':')!==false){
    	                
    	                $hasFunc=true;
    	                $v=explode(':', $v);
    	                $v=$v[0];
    	                $funcs[$k]=$v;
    	            }else{
    	                
    	                unset($funcs[$k]);
    	            }
    	        }
    	        $funcs=array_unique($funcs);
    	        $funcs=array_values($funcs);
    	        $funcList[$funcModule]=$funcs;
    	    }
    	}
    	
    	if($hasFunc){
    	    
    	    if(!input('?export_func')){
    	        
    	        $tips='导出规则';
    	        $this->set_html_tags($tips,$tips,breadcrumb(array($tips)));
    	        $this->assign('coll_id',$coll_id);
    	        return $this->fetch();
    	    }else{
    	        $export_func=input('export_func/d');
    	        if($export_func&&is_array($funcList)){
    	            
    	            $exportName.='（含插件）';
    	            foreach ($funcList as $funcModule=>$funcs){
    	                if(is_array($funcs)){
    	                    foreach ($funcs as $func){
    	                        $pluginData=controller('admin/Develop','controller')->get_plugin_data('func',$func);
    	                        if(empty($pluginData['success'])){
    	                            $tips=model('FuncApp')->get_func_module_val($funcModule,'name').'函数：'.$func.' » ';
    	                            $this->error($tips.$pluginData['msg']);
    	                        }
    	                        $pluginData=$pluginData['plugin'];
    	                        $exportTxt.="\r\n".'/*skycaiji-plugin-start*/'.base64_encode(serialize($pluginData)).'/*skycaiji-plugin-end*/';
    	                    }
    	                }
    	            }
    	        }
    	    }
    	}
    	
    	ob_start();
    	header("Expires: 0" );
    	header("Pragma:public" );
    	header("Cache-Control:must-revalidate,post-check=0,pre-check=0" );
    	header("Cache-Control:public");
    	header("Content-Type:application/octet-stream" );
    	
    	header("Content-transfer-encoding: binary");
    	header("Accept-Length: " .mb_strlen($exportTxt));
    	if (preg_match("/MSIE/i", $_SERVER["HTTP_USER_AGENT"])) {
    	    header('Content-Disposition: attachment; filename="'.urlencode($exportName).'.scj"');
    	}else{
    	    header('Content-Disposition: attachment; filename="'.$exportName.'.scj"');
    	}
    	echo $exportTxt;
    	ob_end_flush();
    }
    
    
    public function echo_msgAction(){
        $collectorKey='';
        $processKey='';
        $cpKeys=\skycaiji\admin\model\Collector::url_collector_process(true);
        if(!empty($cpKeys)){
            
            $collectorKey=$cpKeys['ckey'];
            $processKey=$cpKeys['pkey'];
        }
        $op=input('op');
        $mcache=CacheModel::getInstance();
        $cIntervalName='echo_msg_interval';
        if($op=='set_interval'){
            
            $interval=input('interval/d',2);
            $mcache->setCache($cIntervalName,$interval);
            $this->success('设置成功','');
        }elseif($op=='run'){
            
            $interval=$mcache->getCache($cIntervalName,'data');
            $interval=intval($interval);
            if($interval<=0){
                $interval=2;
            }
            $this->assign('interval',$interval);
            $this->assign('server_is_cli',model('Config')->server_is_cli());
            $this->assign('server',\util\Funcs::web_server_name());
            return $this->fetch('echo_msg_run');
        }elseif($op=='read'){
            
            $data=array('line'=>0,'html'=>'','js'=>'');
            if($collectorKey&&$processKey){
                $list=array();
                $line=input('line/d');
                $line=empty($line)?0:($line+1);
                
                $filename=\skycaiji\admin\model\Collector::echo_msg_filename();
                if(file_exists($filename)){
                    
                    $max=500;
                    $maxHasNext=false;
                    $num=1;
                    if(class_exists('SplFileObject')){
                        
                        $sfo = new \SplFileObject($filename, 'r');
                        if($sfo->seek($line)==0){
                            while (!$sfo->eof()) {
                                if($num<=$max){
                                    
                                    $txt=$sfo->current();
                                    $list[$sfo->key()]=$txt;
                                    $sfo->next();
                                }else{
                                    
                                    $maxHasNext=true;
                                    break;
                                }
                                $num++;
                            }
                        }
                        $sfo=null;
                        unset($sfo);
                    }else{
                        
                        $fp = fopen($filename,'r');
                        $fpIx=0;
                        while (!feof($fp)) {
                            if($fpIx>=$line){
                                
                                if($num<=$max){
                                    
                                    $list[$fpIx]=fgets($fp);
                                }else{
                                    
                                    $maxHasNext=true;
                                    break;
                                }
                                $num++;
                            }else{
                                fgets($fp);
                            }
                            $fpIx++;
                        }
                        fclose($fp);
                        unset($fp);
                    }
                    if(!$maxHasNext){
                        
                        $isEnd=false;
                        foreach ($list as $k=>$txt){
                            if(!$isEnd&&strpos($txt,'echo-msg-is-end')!==false){
                                
                                $isEnd=true;
                            }
                            $list[$k]=$txt;
                        }
                        if($isEnd){
                            
                            if(file_exists($filename)){
                                unlink($filename);
                            }
                        }else{
                            
                            if(\skycaiji\admin\model\Collector::collecting_process_status($collectorKey, $processKey)!='lock'){
                                
                                if(!empty($list)){
                                    $lastKey=array_keys($list);
                                    $lastKey=end($lastKey);
                                    $list[$lastKey]=($list[$lastKey]?$list[$lastKey]:'').\skycaiji\admin\model\Collector::echo_msg_end_js(true);
                                }elseif($line>0){
                                    
                                    $list[$line]=\skycaiji\admin\model\Collector::echo_msg_end_js(true);
                                }
                            }
                        }
                    }
                }elseif($line>0){
                    
                    
                    $list[$line]=\skycaiji\admin\model\Collector::echo_msg_end_js();
                }
                
                $list=array_filter($list);
                if(!empty($list)){
                    
                    $data['line']=max(array_keys($list));
                    $data['line']=intval($data['line']);
                    if($data['line']<0){
                        $data['line']=0;
                    }
                    
                    $js='';
                    foreach ($list as $k=>$v){
                        if(stripos($v, '<script')!==false){
                            
                            $v=preg_replace_callback('/<script[\s\S]+?script>/i', function($matches)use(&$js){
                                $js.=$matches[0];
                                return '';
                            },$v);
                            $list[$k]=$v;
                        }
                    }
                    $data['html']=implode("\r\n", $list);
                    $data['js']=$js;
                    
                    
                    if(stripos($data['html'], '_usertoken_')!==false){
                        
                        $data['html']=preg_replace('/<input[^<>]*?_usertoken_[^<>]*?>/i', html_usertoken(), $data['html']);
                    }
                }
            }
            
            $this->success('','',$data);
        }elseif($op=='status'){
            
            $statusList=\skycaiji\admin\model\Collector::collecting_status_list($collectorKey);
            if(!is_array($statusList)){
                $statusList=array();
            }
            $this->success('','',$statusList);
        }elseif($op=='stop'){
            
            if($collectorKey){
                $processes=\skycaiji\admin\model\Collector::collecting_data($collectorKey);
                if(!empty($processes)){
                    foreach ($processes as $pkey=>$ptids){
                        $filename=\skycaiji\admin\model\Collector::echo_msg_filename($collectorKey.'-'.$pkey);
                        if(file_exists($filename)){
                            unlink($filename);
                        }
                    }
                }
            }
            $this->success('','');
        }
    }
    
    public function echo_url_msgAction(){
        $data=input('data','','trim');
        $data=json_decode($data,true);
        init_array($data['post']);
        init_array($data['renderer']);
        
        
        $urlWeb=array();
        if(!empty($data['post'])){
            $urlWeb['open']=1;
            $urlWeb['form_names']=array_keys($data['post']);
            $urlWeb['form_vals']=array_values($data['post']);
        }
        
        
        $renderer=$data['renderer'];
        if(!empty($renderer)){
            $renderer['open']='y';
        }
        $data=array(
            'url_web'=>$urlWeb,
            'renderer'=>$renderer
        );
        
        $this->set_html_tags('查看网址信息','查看网址信息');
        $this->assign('data',$data);
        return $this->fetch();
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