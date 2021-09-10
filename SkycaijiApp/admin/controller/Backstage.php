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
		$serverData=array(
			'os'=>php_uname('s').' '.php_uname('r'),
			'php'=>PHP_VERSION,
			'db'=>config('database.type'),
		    'version'=>g_sc_c('version')?g_sc_c('version'):constant("SKYCAIJI_VERSION"),
			'server'=>$_SERVER["SERVER_SOFTWARE"],
			'upload_max'=>ini_get('upload_max_filesize')
		);
		
		if(stripos($serverData['db'],'mysql')!==false){
			$dbVersion=db()->query('SELECT VERSION() as v;');
			$serverData['db'].=' '.($dbVersion[0]?$dbVersion[0]['v']:'');
		}
		
		$runInfo['auto_status']='良好';
		/*设置采集状态*/
		if(g_sc_c('caiji','auto')){
			
			$lastTime=cache('last_collect_time');
			$autoTaskCount=model('Task')->where('auto',1)->count();
			if($autoTaskCount<=0){
				
				$serverData['caiji']='<a href="'.url('Admin/Task/list').'">未设置自动采集任务</a>';
				$runInfo['auto_status']='无任务';
			}else{
			    
			    $runInfo['auto_status']='运行良好';
				if($lastTime>0){
				    $serverData['caiji']='最近采集：'.date('Y-m-d H:i:s',$lastTime).' &nbsp;';
				}
				if(g_sc_c('caiji','run')=='backstage'){
					
				    $collectBackstageTime=CacheModel::getInstance()->getCache('collect_backstage_time','data');
				    $collectBackstageTime=intval($collectBackstageTime);
				    if((time()-$collectBackstageTime)>60*3){
				        
				        $runInfo['auto_status']='停止运行';
				        $serverData['caiji'].='<p class="help-block">自动采集停止了，请重新<a href="'.url('Admin/Setting/caiji').'">保存设置</a>以便激活采集</p>';
				    }
				}
				
				$serverData['caiji'].='<a href="javascript:;" id="a_collect_now">实时采集</a>';
			}
		}else{
			$runInfo['auto_status']='未开启';
			$serverData['caiji']='<a href="'.url('Admin/Setting/caiji').'">未开启自动采集</a>';
		}
		
		$upgradeDb=false;
		if(version_compare(model('Config')->getVersion(),SKYCAIJI_VERSION,'<')){
			
			$upgradeDb=true;
		}
		
		$LocSystem=new \skycaiji\install\event\LocSystem();
		$systemData=$LocSystem->environment();
		
		$systemWarning=array('php'=>array(),'path_write'=>array(),'path_read'=>array());
		
		if(is_array($systemData['php'])){
			foreach ($systemData['php'] as $k=>$v){
				if(empty($v['loaded'])){
					
				    $systemWarning['php'][$v['name']]=$v['name'];
				}elseif(!empty($v['lack'])){
				    
				    $systemWarning['php'][$v['name']]=$v['name'].' (需支持'.$v['lack'].')';
				}
			}
		}
		
		if(model('Config')->server_is_cli()||g_sc_c('page_render','tool')){
		    
		    $procFuncs=array('proc_open','proc_get_status','proc_terminate','proc_close');
		    foreach ($procFuncs as $procFunc){
		        if(!function_exists($procFunc)){
		            $systemWarning['php'][$procFunc]=$procFunc;
		        }
		    }
		}
		
		if(is_array($systemData['path'])){
			foreach ($systemData['path'] as $k=>$v){
				if(empty($v[1])){
					
					$systemWarning['path_write'][$v[0]]=$v[0];
				}
				if(empty($v[2])){
					
					$systemWarning['path_read'][$v[0]]=$v[0];
				}
			}
		}
		
		$hasSystemWarning=false;
		foreach ($systemWarning as $k=>$v){
			if(!empty($v)){
				$hasSystemWarning=true;
			}
		}
		if(!$hasSystemWarning){
			$systemWarning=null;
		}
		
		
		$adminIndexData=cache('backstage_admin_index');
		
		
		$timeout=time()-(3600*24*30);
		$mcacheSource=CacheModel::getInstance('source_url');
		$mcacheSource->db()->where('dateline','<',$timeout)->delete();
		$mcacheLevel=CacheModel::getInstance('level_url');
		$mcacheLevel->db()->where('dateline','<',$timeout)->delete();

		$timeout=time()-(3600*24);
		$mcacheCont=CacheModel::getInstance('cont_url');
		$mcacheCont->db()->where('dateline','<',$timeout)->delete();
		
		$openBasedir=null;
		if(!cache('ignore_open_basedir_tips')){
		    
		    $openBasedir=ini_get('open_basedir');
		    if(!empty($openBasedir)){
		        $openBasedir=explode(IS_WIN?';':':', $openBasedir);
		        $openBasedir=implode('、', $openBasedir);
		    }
		}
		
		set_g_sc('p_name','后台管理');
		set_g_sc('p_nav',breadcrumb(array(array('url'=>url('Backstage/index'),'title'=>'首页'))));
		
		$this->assign('runInfo',$runInfo);
		$this->assign('serverData',$serverData);
		$this->assign('upgradeDb',$upgradeDb);
		$this->assign('systemWarning',$systemWarning);
		$this->assign('adminIndexData',$adminIndexData);
		$this->assign('openBasedir',$openBasedir);
		
		return $this->fetch('backstage/index');
	}
	
	
	public function checkUpAction(){
	    $info=array(
	        'pageRenderInvalid'=>false,
	        'phpInvalid'=>false,
	    );
	    
	    $mconfig=model('Config');
	    
	    if($mconfig->server_is_cli()){
	        
	        $phpvInfo=$mconfig->exec_php_version(g_sc_c('caiji','server_php'));
	        if(empty($phpvInfo)||!$phpvInfo['success']){
	            $info['phpInvalid']=true;
	        }
	    }
	    
	    $autoTaskIds=model('Task')->where('auto',1)->column('id');
	    if(!empty($autoTaskIds)){
	        foreach ($autoTaskIds as $autoTaskId){
	            $collConfig=model('Collector')->where('task_id',$autoTaskId)->value('config');
	            
	            $collConfig=unserialize($collConfig);
	            if($collConfig['page_render']){
	                
	                $pageRender=g_sc_c('page_render');
	                if(empty($pageRender['tool'])){
	                    
	                    $info['pageRenderInvalid']=true;
	                }elseif($mconfig->page_render_is_chrome()){
	                    
	                    $chromeSoket=new \util\ChromeSocket($pageRender['chrome']['host'],$pageRender['chrome']['port'],$pageRender['timeout'],$pageRender['chrome']['filename'],$pageRender['chrome']);
	                    $info['pageRenderInvalid']=$chromeSoket->hostIsOpen()?false:true;
	                }
	                break;
	            }
	        }
	    }
	    
	    $this->success('','',$info);
	}
	
	/*实时采集*/
	public function collectAction(){
		remove_auto_collecting();
		controller('admin/Api','controller')->collectAction();
	}
	
	/*检测更新*/
	public function newVersionAction(){
	    $version=curl_skycaiji('/client/info/version?v='.SKYCAIJI_VERSION);
	    $version=json_decode($version,true);
	    $version=is_array($version)?$version:array();
	    $new_version=trim($version['new_version']);
	    $cur_version=g_sc_c('version');
	    
	    
	    if(version_compare($new_version,$cur_version,'>')){
	        
	        $version['is_new_version']=true;
	    }
	    
	    $cacheIx=cache('backstage_admin_index');
	    if(empty($cacheIx)||$cacheIx['ver']!=$version['admin_index_ver']){
	        $version['is_new_admin_index']=true;
	    }
	    
	    $this->success('',null,$version);
	}
	
	
	/*获取推送消息*/
	public function adminIndexAction(){
		$refresh=input('refresh');
		$data=cache('backstage_admin_index');
		$data=is_array($data)?$data:array();
		if($refresh||empty($data['html'])){
			
			$data=curl_skycaiji('/client/info/push?v='.SKYCAIJI_VERSION);
			$data=json_decode($data,true);
			$data=is_array($data)?$data:array();
			
			$data=array(
				'ver'=>$data['ver'],
				'html'=>$data['html']
			);
			cache('backstage_admin_index',$data);
		}
		return json($data);
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
			
			
			$count0=$mcache->db()->where('ctype',0)->count();
			$count1=$mcache->db()->where('ctype',1)->count();

			$this->assign('list',$list);
			$this->assign('cacheList',$cacheList);
			$this->assign('taskType',$taskType);
			$this->assign('pagenav',$pagenav);
			$html=$this->fetch('bk_task_list')->getContent();

			$this->success('',null,array('html'=>$html,'count0'=>$count0,'count1'=>$count1));
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
	
	
	public function ignoreOpenBasedirAction(){
	    cache('ignore_open_basedir_tips',1);
	    $this->success();
	}
	
	
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
			echo "ok:{$lv}<br>";
		}
	}
	
	
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