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

use skycaiji\admin\model\DbCommon;
use think\db\Query;
class Release extends CollectController{
	/*发布设置*/
	public function setAction(){
		$taskId=input('task_id/d',0);
		$releaseId=input('release_id/d',0);
		$mtask=model('Task');
		$mrele=model('Release');
		$taskData=$mtask->getById($taskId);
		if(empty($taskData)){
			$this->error(lang('task_error_empty_task'));
		}
		$releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
		if(request()->isPost()){
			$newData=array('task_id'=>$taskData['id'],'addtime'=>time(),'config'=>array());
			if($releaseId>0){
				
				$importRele=$mrele->where(array('id'=>$releaseId))->find();
				$newData['module']=$importRele['module'];
				$newData['config']=$importRele['config'];
			}else{
				
				$newData['module']=input('module','','strtolower');
				if(empty($newData['module'])){
					$this->error(lang('rele_error_null_module'));
				}
				$releObj=controller('admin/R'.$newData['module'],'event');
				$newData['config']=$releObj->setConfig($newData['config']);
				$newData['config']=serialize($newData['config']);
			}
			if(empty($newData['module'])){
				$this->error(lang('rele_error_null_module'));
			}
			
			if(empty($releData)){
				
				$mrele->isUpdate(false)->allowField(true)->save($newData);
				$releId=$mrele->id;
			}else{
				
				$releId=$releData['id'];
				$mrele->strict(false)->where(array('id'=>$releData['id']))->update($newData);
			}
			if($releId>0){
				$this->success(lang('op_success'),'release/set?task_id='.$taskId);
    		}else{
    			$this->error(lang('op_failed'));
    		}
		}else{
		    $this->set_html_tags(
		        '任务:'.$taskData['name'].'_'.lang('rele_set'),
		        lang('rele_set'),
		        breadcrumb(array(array('url'=>url('task/save?id='.$taskData['id']),'title'=>lang('task').lang('separator').$taskData['name']),array('url'=>url('release/set?task_id='.$taskData['id']),'title'=>lang('rele_set'))))
		    );

			$this->assign('taskData',$taskData);
			
			if(!empty($releData)){
				
			    $releData=$releData->toArray();
			}else{
			    $releData=array();
			}
			
			$releData['config']=$mrele->compatible_config($releData['config']);

			$this->assign('config',$releData['config']);
			$this->assign('releData',$releData);
			
			$apiRootUrl=config('root_website');

			if(stripos(\think\Request::instance()->root()?:'','/index.php?s=')!==false){
				$apiRootUrl.='/index.php?s=';
			}elseif(stripos(\think\Request::instance()->root()?:'','/index.php')!==false){
				$apiRootUrl.='/index.php';
			}

			$releBase=new \skycaiji\admin\event\ReleaseBase();
			$collFields=$releBase->get_coll_fields($taskData['id'],$taskData['module']);
			
			$this->assign('apiRootUrl',$apiRootUrl);
			$this->assign('collFields',$collFields);
			return $this->fetch();
		}
	}
	/*导入配置*/
	public function importAction(){
		$page=max(1,input('p/d',0));
		$mrele=model('Release');
		$mtask=model('Task');
    	$limit=20;
    	$cond=array();
    	$taskCond=array();
    	$count=$mrele->where($cond)->count();
		$releList=$mrele->where($cond)->order('id desc')->paginate($limit,false,paginate_auto_config());

		$pagenav = $releList->render();
		$this->assign('pagenav',$pagenav);
		$releList=$releList->all();
		$releList=empty($releList)?array():$releList;
		if($count>0){
			$taskIds=array();
			foreach ($releList as $rele){
				$taskIds[$rele['task_id']]=$rele['task_id'];
			}
			if(!empty($taskIds)){
				
				$taskCond['id']=array('in',$taskIds);
				$taskNames=$mtask->where($taskCond)->column('name','id');
				$this->assign('taskNames',$taskNames);
			}
		}

		$this->assign('releList',$releList);
		return $this->fetch();
	}
	/*检测cms信息*/
	public function cmsDetectAction(){
		$acms=controller('admin/Rcms','event');
		$acms->cms_name_list(config('root_path'));
		$acms->cms_name_list(config('root_path').'/../');
		$prevPath=config('root_path').'/../';
		if(is_dir($prevPath)){
			
			$dp=dir($prevPath);
			
			while(($curPath=$dp->read())!=false){
				if($curPath!='.'&&$curPath!='..'){
					$curPath=$prevPath.$curPath;
					if(is_dir($curPath)){
						
						$acms->cms_name_list($curPath);
					}
				}
			}
			$dp->close();
		}
		$nextPath=config('root_path').'/';
		if(is_dir($nextPath)){
			
			$dp=dir($nextPath);
			while(($curPath=$dp->read())!=false){
				if($curPath!='.'&&$curPath!='..'){
					$curPath=$nextPath.$curPath;
					if(is_dir($curPath)){
						
						$acms->cms_name_list($curPath);
					}
				}
			}
			$dp->close();
		}
		$cmsList=$acms->cms_name_list(null,true);
		if(!empty($cmsList)){
			$this->success('',null,$cmsList);
		}else{
			$this->error(lang('rele_error_detect_null'));
		}
	}
	/*
	 * cms程序绑定数据
	 * 设置可不先入库进行测试绑定
	 * */
	public function cmsBindAction(){
	    $cmsSet=input('cms/a',array());
		$taskId=input('task_id/d',0);
		$cmsPath=$cmsSet['path'];
		if(empty($cmsPath)){
			$this->error('cms路径不能为空');
		}
		
		$acms=controller('admin/Rcms','event');
		$cmsName=$acms->cms_name($cmsPath);
		if(empty($cmsName)){
		    list($cmsPath,$cmsName)=explode('@', $cmsPath);
		    $msg='未知的cms程序，请确保路径存在，如需指定CMS程序请在路径结尾加上@CMS程序名，例如：@discuz';
		    if(\skycaiji\admin\model\Config::check_basedir_limited($cmsPath)){
		        
		        $msg.='<br/>注意：'.lang('error_open_basedir');
		    }
		    $this->error($msg);
		}
		$cmsApp=$cmsSet['app'];

		
		$cmsApps=model('ReleaseApp')->where(array('module'=>'cms','app'=>array('like',addslashes($cmsName).'%')))->order('uptime desc')->column('*');
		$cmsApps=is_array($cmsApps)?$cmsApps:array();
		if(!empty($cmsApps)){
			$cmsApps=array_values($cmsApps);
		}





		
		if(!empty($cmsApp)){
			$cmsApp=ucfirst($cmsApp);
			if(!model('ReleaseApp')->appFileExists($cmsApp,'cms')){
				
				if(model('ReleaseApp')->oldFileExists($cmsApp,'Cms')){
					
					$cmsError=lang('release_upgrade');
				}else{
					$cmsError='抱歉，插件文件不存在';
				}
				$this->assign('cmsError',$cmsError);
			}else{
				
				try {
					$releCms=model('ReleaseApp')->appImportClass($cmsApp,'cms');
					$releCms->init($cmsPath,array('task_id'=>$taskId));
					$releCms->runBind();
				} catch (\Exception $ex) {
					$releCms=null;
					$this->error($ex->getMessage());
				}
				$this->assign('releCms',$releCms);
			}
		}
		$this->assign('cmsName',$cmsName);
		$this->assign('cmsApps',$cmsApps);
		$this->assign('cmsApp',$cmsApp);
		return $this->fetch('cmsBind');
	}
	
	public function testAction(){
	    $releId=input('id/d',0);
	    $releData=model('Release')->getById($releId);
	    if(empty($releData)){
	        $this->error(lang('rele_error_empty_rele'));
	    }
	    $urlParams=null;
	    if($releData['module']=='toapi'){
	        
	        $urlParams=array('test_toapi'=>1);
	    }
	    $this->collect_create_or_run(function()use($releData){
	        return array($releData['task_id']);
	    },1,null,false,$urlParams);
	}
	
	/*读取数据库表*/
	public function dbTablesAction(){
		$releId=input('id/d',0);
		$mrele=model('Release');
		$releData=$mrele->where(array('id'=>$releId))->find();
		if(empty($releData)){
			$this->error(lang('rele_error_empty_rele'));
		}
		$config=unserialize($releData['config']?:'');
		$db_config=controller('admin/Rdb','event')->get_db_config($config['db']);
		try{
			$mdb=new DbCommon($db_config);
			$tables=$mdb->getTables();
		}catch(\Exception $ex){
			$msg=$this->trans_db_msg($ex->getMessage());
			$this->error($msg);
		}
		$this->assign('tables',$tables);
		$html=$this->fetch('dbTables');
		$this->success($html->getContent());
	}
	
	/*测试连接数据库*/
	public function dbConnectAction(){
		$op=input('op');
		$db=input('db/a',array(),'trim');
		
		$no_check=array('db_pwd');
		if('db_names'==$op){
			
			$no_check[]='db_name';
			unset($db['name']);
		}

		$db_config=controller('admin/Rdb','event')->get_db_config($db);
		foreach ($db_config as $k=>$v){
			if(empty($v)&&!in_array($k,$no_check)){
				$this->error(lang('error_null_input',array('str'=>lang('rele_'.$k))));
			}
		}
		$msgError=false;
		$msgSuccess=false;
		try{
			$mdb=new DbCommon($db_config);
			
			if(!$mdb->db()){
				$msgError='数据库连接错误';
			}else{
				if('db_names'==$op){
					
					$dbNames=array();
					if($db_config['db_type']=='mysql'){
						$dbsData=$mdb->db()->query('show databases');
						foreach ($dbsData as $dbDt){
							$dbNames[$dbDt['Database']]=$dbDt['Database'];
						}
					}elseif($db_config['db_type']=='oracle'){
						$dbsData=$mdb->db()->query('SELECT * FROM v$database');
						foreach ($dbsData as $dbDt){
							$dbNames[$dbDt['NAME']]=$dbDt['NAME'];
						}
					}elseif($db_config['db_type']=='sqlsrv'){
						$dbsData=$mdb->db()->query('select * from sysdatabases');
						foreach ($dbsData as $dbDt){
							$dbNames[$dbDt['name']]=$dbDt['name'];
						}
					}
					if(empty($dbNames)){
						$msgError='没有数据库';
					}else{
						sort($dbNames);
						$this->assign('dbNames',$dbNames);
						$html=$this->fetch('release/dbNames');
						$msgSuccess=$html->getContent();
					}
				}else{
					$dbTables=$mdb->getTables();
					$msgSuccess=lang('rele_success_db_ok');
				}
			}
		}catch(\Exception $ex){
			$msg=$this->trans_db_msg($ex->getMessage());
			$this->error($msg);
		}
		if($msgError){
			$this->error($msgError);
		}
		if($msgSuccess){
			$this->success($msgSuccess);
		}
	}
	
	/*数据表绑定数据*/
	public function dbTableBindAction(){
        $releId=input('id/d',0);
        $table=input('table','');
        $isDbTables=input('is_db_tables');
        $mrele=model('Release');
        $mtask=model('Task');
        $mcoll=model('Collector');
        $releData=$mrele->where(array('id'=>$releId))->find();
        if(empty($releData)){
            $this->error(lang('rele_error_empty_rele'));
        }
        $releData['config']=$mrele->compatible_config($releData['config']);
        $dbTables=$releData['config']['db_tables'];
        init_array($dbTables);
        
        $tbTables=array();
        if($isDbTables){
            foreach ($dbTables as $v){
                if(is_array($v)){
                    $tbTables[]=$v['table'];
                }
            }
        }else{
            $tbTables=explode(',', $table);
        }
        $tbTables=array_filter($tbTables);
        $tbTables=array_values($tbTables);
        if(empty($tbTables)){
            $this->error('请选择表');
        }
        init_array($tbTables);
        
        if(!$isDbTables){
            
            $dbTables=array();
            foreach ($tbTables as $v){
                $dbTables[]=array('table'=>$v);
            }
        }
        
        $dbTables1=array();
        foreach ($dbTables as $k=>$v){
            init_array($v);
            init_array($v['field']);
            $k='i_'.\util\Funcs::uniqid($v['table']);
            $dbTables1[$k]=$v;
        }
        $dbTables=$dbTables1;
        $seqList=array();
        $adb=controller('admin/Rdb','event');
        $dbConfig=$adb->get_db_config($releData['config']['db']);
        try {
            
            $mdb=new DbCommon($dbConfig);
            $tbFields=array();
            foreach ($tbTables as $tbName){
                $tbFields[$tbName]=$mdb->getFields($tbName);
            }
            
            $dbHasSeq=$mrele->db_has_sequence($releData['config']['db']['type']);
            if($dbHasSeq){
                
                $seqList=$mdb->db()->query('select * from user_sequences');
                foreach ($seqList as $k=>$v){
                    $seqList[$k]=$v['SEQUENCE_NAME'];
                }
                init_array($seqList);
            }
        }catch (\Exception $ex){
            $dbMsg=$this->trans_db_msg($ex->getMessage());
            $this->error($dbMsg);
        }
        $this->assign('tbTables',$tbTables);
        $this->assign('tbFields',$tbFields);
        $this->assign('dbTables',$dbTables);
        $this->assign('seqList',$seqList);
        $this->assign('dbHasSeq',$mrele->db_has_sequence($releData['config']['db']['type']));
        return $this->fetch('dbTableBind');
	}
	
	public function dbTableBindSingsAction(){
	    $collFields=array();
	    $autoIds=array();
	    $querySigns=array();
	    if(request()->isPost()){
	        $mrele=model('Release');
	        $tableKey=input('table_key','','trim');
	        $taskId=input('task_id/d',0);
	        $dbTables=trim_input_array('db_tables');
	        $dbTables=$mrele->config_db_tables($dbTables,true);
	        
	        $taskData=model('Task')->getById($taskId);
	        
	        if(!empty($taskData)){
	            $collFields=controller('admin/Rdb','event')->get_coll_fields($taskData['id'], $taskData['module']);
	        }
	        init_array($collFields);
	        foreach ($dbTables as $tbKey=>$dbTable){
	            if($tbKey==$tableKey){
	                
	                break;
	            }
	            if(empty($dbTable['op'])){
	                
	                if($dbTable['table']){
	                    $autoIds[$dbTable['table']]=$dbTable['table'];
	                }
	            }elseif($dbTable['op']=='query'){
	                
	                $tbQuery=$dbTable['query'];
	                foreach ($tbQuery['sign'] as $k=>$v){
	                    $v=$mrele->db_tables_query_sign($tbQuery['type'][$k],$tbQuery['field'][$k],$v);
	                    $querySigns[$v]=$v;
	                }
	            }
	        }
	    }
	    $collFields=array_values($collFields);
	    $autoIds=array_values($autoIds);
	    $querySigns=array_values($querySigns);
	    
	    $maxCount=max(count($collFields),count($autoIds),count($querySigns));
	    
	    $this->assign('maxCount',$maxCount);
	    $this->assign('collFields',$collFields);
	    $this->assign('autoIds',$autoIds);
	    $this->assign('querySigns',$querySigns);
	    return $this->fetch('dbTableBindSings');
	}
	
    /*翻译数据库错误信息*/
    public function trans_db_msg($msg){
    	$msg=lang('rele_error_db').str_replace('Unknown database', lang('error_unknown_database'), $msg);
    	return $msg;
    }
    
    public function toapiAppAction(){
        $taskId=input('task_id/d',0);
        $mtask=model('Task');
        $mrele=model('Release');
        $taskData=$mtask->getById($taskId);
        
        $appApi=null;
        $appParams=null;
        $releData=$mrele->where(array('task_id'=>$taskData['id']))->find();
        if(!empty($releData)){
            
            $config=$mrele->compatible_config($releData['config']);
            if(!empty($config['toapi'])&&$config['toapi']['module']=='app'){
                
                $appApi=$config['toapi']['app_api'];
                if($appApi){
                    $appApi=base64_decode($appApi);
                    $appApi=json_decode($appApi,true);
                }
                $appParams=$config['toapi']['app_params'];
            }
        }
        
        if(request()->isPost()){
            
            $appUrl=input('app_url','','trim');
            if(empty($appUrl)){
                $this->error('请输入接口地址');
            }
            $appApi=get_html($appUrl,[],[],'utf-8');
            if(!empty($appApi)){
                $appApi=json_decode($appApi,true);
            }
            init_array($appApi);
            if(empty($appApi)){
                $this->error('接口读取失败');
            }
            if(empty($appApi['code'])){
                $this->error($appApi['msg']?:'接口错误');
            }
            $appApi=$appApi['data'];
        }
        
        init_array($appApi);
        init_array($appParams);
        
        if(!empty($appApi)){
            
            $appApiParams=$appApi['params'];
            init_array($appApiParams);
            if(empty($appParams)){
                
                foreach ($appApiParams as $pk=>$pv){
                    $pv=$pv['default'];
                    if(!empty($pv)||is_numeric($pv)){
                        
                        $appParams[$pk]=$pv;
                    }
                }
            }
            
            $releBase=new \skycaiji\admin\event\ReleaseBase();
            $collFields=$releBase->get_coll_fields($taskData['id'],$taskData['module']);
            $this->assign('collFields',$collFields);
            $this->assign('appApi',base64_encode(json_encode($appApi)));
            $this->assign('appApiParams',$appApiParams);
            $this->assign('appParams',$appParams);
            return $this->fetch('toapiApp');
        }
    }
}