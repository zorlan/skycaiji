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
use think\db\Query;
class Backstage extends BaseController{
	public function indexAction(){
		$runInfo=array();
		
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
		$mconfig=model('Config');
		$runInfo['auto_status']='良好';
		/*设置采集状态*/
		if(g_sc_c('caiji','auto')){
			
			$autoTaskCount=model('Task')->where('`auto`>0')->count();
			if($autoTaskCount<=0){
				
				$serverData['caiji']='<a href="'.url('admin/task/list').'">未设置自动采集任务</a>';
				$runInfo['auto_status']='无任务';
			}else{
			    
			    $runInfo['auto_status']='运行良好';
			    $taskCaijitime=model('Task')->where('`auto`>0')->max('caijitime');
			    if($taskCaijitime>0){
			        $serverData['caiji']='最近采集：'.date('Y-m-d H:i:s',$taskCaijitime).' &nbsp;';
				}
				if(g_sc_c('caiji','run')=='backstage'){
					
				    $collectBackstageTime=CacheModel::getInstance()->getCache('collect_backstage_time','data');
				    $collectBackstageTime=intval($collectBackstageTime);
				    if((time()-$collectBackstageTime)>60*5){
				        
				        $runInfo['auto_status']='停止运行';
				        $serverData['caiji'].='<p class="help-block">自动采集已停止 <a href="javascript:;" id="a_run_auto_backstage">点击激活</a></p>';
				    }else{
				        $runInfo['auto_status']='<span style="font-size:16px;">';
				        if($mconfig->server_is_cli()){
				            $runInfo['auto_status'].='cli';
				        }elseif($mconfig->server_is_swoole()){
				            $runInfo['auto_status'].='swoole';
				        }else{
				            $runInfo['auto_status'].='web';
				        }
				        $runInfo['auto_status'].='后台运行</span>';
				        $runInfo['auto_status1']='<small>'.date('m-d H:i:s',$collectBackstageTime).'</small>';
				    }
				}elseif(g_sc_c('caiji','run')=='visit'){
				    
				    $collectVisitTime=CacheModel::getInstance()->getCache('collect_visit_time','data');
				    $runInfo['auto_status']='访问触发';
				    if($collectVisitTime){
				        $runInfo['auto_status1']='<small>'.date('m-d H:i:s',$collectVisitTime).'</small>';
				    }
				}
				$serverData['caiji'].='<a href="javascript:;" id="a_collect_now">实时采集</a>';
			}
		}else{
			$runInfo['auto_status']='未开启';
			$serverData['caiji']='<a href="'.url('admin/setting/caiji').'">未开启自动采集</a>';
		}
		
		$upgradeDb=false;
		if(version_compare($mconfig->getVersion(),SKYCAIJI_VERSION,'<')){
			
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
		
		if($mconfig->server_is_cli()){
		    
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
		
		$openBasedir=null;
		if(!cache('ignore_open_basedir_tips')){
		    
		    $openBasedir=ini_get('open_basedir');
		    if(!empty($openBasedir)){
		        $openBasedir=explode(IS_WIN?';':':', $openBasedir);
		        $openBasedir=implode('、', $openBasedir);
		    }
		}
		
		$this->set_html_tags(null,'后台管理',breadcrumb(array(array('url'=>url('backstage/index'),'title'=>'首页'))));
		
		$this->assign('runInfo',$runInfo);
		$this->assign('serverData',$serverData);
		$this->assign('upgradeDb',$upgradeDb);
		$this->assign('systemWarning',$systemWarning);
		$this->assign('adminIndexData',$adminIndexData);
		$this->assign('openBasedir',$openBasedir);
		
		return $this->fetch('backstage/index');
	}
	
	public function run_auto_backstageAction(){
	    controller('admin/Setting')->_run_auto_backstage();
	    $this->success('操作完成');
	}
	/*实时采集*/
	public function collectAction(){
	    controller('admin/Index','controller')->auto_collectAction();
	}
	
	
	public function checkUpAction(){
	    
	    $info=array(
	        'pageRenderInvalid'=>false,
	        'phpInvalid'=>false,
	        'swooleInvalid'=>false,
	        'swoolePhpInvalid'=>false,
	        'repairTables'=>'',
	    );
	    
	    try{
	        
	        $cacheTimeout=time()-(3600*24*7);
	        CacheModel::getInstance('source_url')->db()->where('dateline','<',$cacheTimeout)->delete();
	        CacheModel::getInstance('level_url')->db()->where('dateline','<',$cacheTimeout)->delete();
	        CacheModel::getInstance('collecting')->db()->where('dateline','<',$cacheTimeout)->delete();
	        
	        $cacheTimeout=time()-(3600*24);
	        CacheModel::getInstance('cont_url')->db()->where('dateline','<',$cacheTimeout)->delete();
	        
	        $mconfig=model('Config');
	        
	        if($mconfig->server_is_cli()){
	            
	            $phpResult=$mconfig->php_is_valid(g_sc_c('caiji','server_php'));
	            if(empty($phpResult['success'])){
	                $info['phpInvalid']=true;
	            }
	            if($phpResult['ver']){
	                
	                $info['cliPhpVersion']=$phpResult['ver'];
	            }
	        }elseif($mconfig->server_is_swoole()){
	            
	            $ss=new \util\SwooleSocket(g_sc_c('caiji','swoole_host'),g_sc_c('caiji','swoole_port'));
	            if($ss->websocketError()){
	                $info['swooleInvalid']=true;
	            }else{
	                
	                $ssData=$ss->sendReceive('php_ver');
	                if($ssData['php_ver']){
	                    $info['swoolePhpVersion']=$ssData['php_ver'];
	                }
	            }
	            
	            if(empty($info['swoolePhpVersion'])&&$mconfig->server_is_swoole_php()){
	                
	                $phpResult=$mconfig->php_is_valid(g_sc_c('caiji','swoole_php'));
	                if(empty($phpResult['success'])){
	                    $info['swoolePhpInvalid']=true;
	                }
	                if($phpResult['ver']){
	                    
	                    $info['swoolePhpVersion']=$phpResult['ver'];
	                }
	            }
	        }
	        
	        if(model('Task')->where('`auto`>0')->count()>0){
	            
	            if($mconfig->page_render_is_chrome()){
	                
	                $pageRender=g_sc_c('page_render');
	                init_array($pageRender['chrome']);
	                $chromeSoket=new \util\ChromeSocket($pageRender['chrome']['host'],$pageRender['chrome']['port'],$pageRender['timeout'],$pageRender['chrome']['filename'],$pageRender['chrome']);
	                $info['pageRenderInvalid']=$chromeSoket->hostIsOpen()?false:true;
	            }
	        }
	        
	        
	        $cacheTongji=cache('admin_check_up_tongji');
	        $cacheTongji=is_array($cacheTongji)?$cacheTongji:array();
	        $tongji=array();
	        if(empty($cacheTongji)||abs(time()-$cacheTongji['time'])>60){
	            
	            $mcollected=model('Collected');
	            $todayTime=strtotime(date('Y-m-d',time()));
	            $tongji['today_success']=$mcollected->where(array('addtime'=>array('GT',$todayTime),'status'=>1))->count();
	            $tongji['today_error']=$mcollected->where(array('addtime'=>array('GT',$todayTime),'status'=>0))->count();
	            $tongji['total_success']=$mcollected->where('status',1)->count();
	            $tongji['total_error']=$mcollected->where('status',0)->count();
	            $cacheTongji=array('time'=>time(),'data'=>$tongji);
	            cache('admin_check_up_tongji',$cacheTongji);
	        }else{
	            $tongji=$cacheTongji['data'];
	        }
	        $tongji=is_array($tongji)?$tongji:array();
	        $tongji['task_auto']=model('Task')->where('`auto`>0')->count();
	        $tongji['task_other']=model('Task')->where('`auto`=0')->count();
	        $info['tongji']=$tongji;
	        
	        
	        $dbName=config('database.database');
	        $dbTables=db()->getConnection()->getTables($dbName);
	        if(!empty($dbTables)){
	            
	            $dbTables1=array();
	            foreach ($dbTables as $k=>$v){
	                $v=strtolower($v);
	                if(stripos($v,config('database.prefix'))!==false){
	                    $dbTables1[$v]=$v;
	                }
	            }
	            $dbTables=$dbTables1;
	            $checkList=db()->query('check table '.implode(',',$dbTables));
	            $dbTables=array();
	            foreach ($checkList as $v){
	                if(is_array($v)&&$v['Msg_type']&&strtolower($v['Msg_type'])=='error'){
	                    $v['Table']=preg_replace('/^'.$dbName.'\./i', '', $v['Table']);
	                    $dbTables[$v['Table']]=$v['Table'];
	                }
	            }
	            if($dbTables){
	                $info['repairTables']=implode(',',$dbTables);
	            }
	        }
	    }catch (\Exception $ex){
	        
	    }
	    
	    $this->success('','',$info);
	}
	
	public function repairTablesAction(){
	    if(request()->isPost()){
	        $tables=input('tables','');
	        $tables=explode(',', $tables);
	        $tables=array_unique($tables);
	        $tables=array_values($tables);
	        if($tables){
	            db()->query('repair table '.implode(',', $tables));
	            $this->success('修复完成','backstage/index');
	        }
	    }
	    $this->error('修复失败');
	}
	
	/*检测更新*/
	public function newVersionAction(){
	    $version=\util\Tools::curl_skycaiji('/client/info/version?v='.SKYCAIJI_VERSION);
	    $version=json_decode($version,true);
	    $version=is_array($version)?$version:array();
	    $new_version=trim($version['new_version']?:0);
	    $cur_version=g_sc_c('version');
	    
	    
	    if(version_compare($new_version,$cur_version,'>')){
	        
	        $version['is_new_version']=true;
	    }
	    
	    if($version['version_file']=='zip'){
	        if(!class_exists('ZipArchive')){
	            
	            $version['version_file']='';
	        }
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
			
		    $data=\util\Tools::curl_skycaiji('/client/info/push?v='.SKYCAIJI_VERSION);
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
		}elseif('tasks0'==$op||'tasks1'==$op){
			
			$taskType=('tasks0'==$op)?0:1;
			
			$list=$mcache->db()->where('ctype',$taskType)->order('dateline desc')->paginate(10,false,paginate_auto_config());
			$pagenav=$list->render();
			$list=$list->all();
			$cacheList=array();
			if($list){
				
				foreach ($list as $k=>$v){
					$v['cname']=intval($v['cname']);
					$v['data']=intval($v['data']);
					if($taskType){
					    $v['endtime']=$v['data'];
						$v['enddate']=date('Y-m-d H:i:s',$v['endtime']);
					}else{
					    $v['startdate']=date('Y-m-d H:i:s',$v['data']);
					}
					$cacheList[$v['cname']]=$v;
				}
				
				$list=model('Task')->where('id','in',array_keys($cacheList))->column('*','id');

				$nullIds=array();
				
				$list1=array();
				foreach ($cacheList as $k=>$v){
					if(!isset($list[$k])){
						
						$nullIds[$k]=$k;
						unset($cacheList[$k]);
					}else{
						
						$list1[$k]=$list[$k];
					}
				}
				$list=$list1;
				
				if(!empty($nullIds)&&is_array($nullIds)){
					
				    $mcache->deleteCache($nullIds);
				}
				
				if(is_array($cacheList)){
				    if($taskType){
    					
    					foreach ($cacheList as $k=>$v){
    						$cond=array(
    							'task_id'=>$k,
    							'addtime'=>array('between',array($v['dateline'],$v['endtime']))
    						);
    						$v['collected_count']=model('Collected')->where($cond)->count();
    						$cacheList[$k]=$v;
    					}
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
			$error='';
			$taskStatus='';
			if($taskId<=0){
			    $error='任务id错误';
			}else{
    			$cache=$mcache->db()->where('cname',$taskId)->find();
    			if(empty($cache)){
    			    $error='任务已停止运行';
    			}else{
        			
        			$cond=array('task_id'=>$taskId);
        			
        			$taskStatus=$cache['ctype'];
        			if(empty($taskStatus)){
        			    $taskStatus='';
        			    
        			    $cond['addtime']=array('>=',$cache['dateline']);
        		        
        		        $collStatus=\skycaiji\admin\model\Task::collecting_status($taskId);
        		        if($collStatus){
        		            if($collStatus=='none'){
        		                $taskStatus='已断开';
        		            }elseif($collStatus=='unlock'){
        		                $taskStatus='运行中断'.(g_sc_c('caiji','server')?'':(strtolower($_SERVER['SERVER_SOFTWARE']).'超时'));
        		            }
        		        }
        			}else{
        				
        			    $taskStatus='已结束';
        				$cond['addtime']=array('between',array($cache['dateline'],intval($cache['data'])));
        			}
        			
        			$list=model('Collected')->where($cond)->order('addtime desc')->paginate(10,false,paginate_auto_config());
        			$pagenav=$list->render();
        			$list=$list->all();
        			$list=model('Collected')->getInfoDatas($list);
        			
        			$this->assign('list',$list);
        			$this->assign('pagenav',$pagenav);
    			}
			}
			$this->assign('taskId',$taskId);
			$this->assign('taskStatus',$error?'已终止':$taskStatus);
			$this->assign('error',$error);
			return $this->fetch('bk_task_collected');
		}elseif('status'==$op){
		    
		    $taskIds=input('tids/a',array(),'intval');
		    $statusList=array();
		    if(!empty($taskIds)){
		        foreach ($taskIds as $taskId){
		            $cache=$mcache->db()->where('cname',$taskId)->find();
		            $taskStatus=$cache?$cache['ctype']:'';
	                if(empty($taskStatus)){
	                    
	                    $taskStatus='';
	                    
	                    $collStatus=\skycaiji\admin\model\Task::collecting_status($taskId);
	                    if($collStatus){
	                        if($collStatus=='none'){
	                            $taskStatus='已断开';
	                        }elseif($collStatus=='unlock'){
	                            $taskStatus='运行中断'.(g_sc_c('caiji','server')?'':(strtolower($_SERVER['SERVER_SOFTWARE']).'超时'));
	                        }
	                    }
	                }else{
	                    
	                    $taskStatus='已结束';
	                }
		            
		            $statusList[$taskId]=$taskStatus;
		        }
		    }
		    $this->success('','',$statusList);
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
	
	public function admincpAction(){
	    if($this->request->isPost()){
	        $op=input('op');
	        $val=input('val');
	        $mconfig=model('Config');
	        $config=$mconfig->getConfig('admincp','data');
	        init_array($config);
	        if($op=='mini'||$op=='narrow'||$op=='check_skip'){
	            $config[$op]=intval($val);
	        }elseif($op=='skin'){
	            if(preg_match('/^[\w\-\_]+$/', $val)){
	                $config[$op]=$val;
	            }
	        }
	        
	        $allowConfig=array('skin'=>'','mini'=>'','narrow'=>'','check_skip'=>'');
	        foreach ($allowConfig as $k=>$v){
	            $allowConfig[$k]=isset($config[$k])?$config[$k]:'';
	        }
	        $mconfig->setConfig('admincp',$allowConfig);
	        $this->success();
	    }else{
	        $this->error();
	    }
	}
}