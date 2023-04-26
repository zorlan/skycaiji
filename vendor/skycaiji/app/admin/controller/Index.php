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
use skycaiji\admin\model\User;
use skycaiji\admin\model\CacheModel;
class Index extends CollectController{
	public function indexAction(){
		return $this->fetch();
	}
	public function loginAction(){
		if(request()->isPost()){
			$mcacheLogin=CacheModel::getInstance('login');
			$config_login=g_sc_c('site','login');
			init_array($config_login);
			$clientIpMd5=md5(request()->ip());
			$nowTime=time();
			if(!empty($config_login['limit'])){
				
			    try{
			        $ipLoginData=$mcacheLogin->getCache($clientIpMd5,'data');
			        if(!is_array($ipLoginData)){
			            $ipLoginData=array();
			        }
			    }catch (\Exception $ex){
			        
			        $this->error($ex->getMessage());
			    }
			    if(($nowTime-$ipLoginData['lastdate'])<($config_login['time']*3600)&&$ipLoginData['failed']>=$config_login['failed']){
					
					$this->error("您已登录失败{$ipLoginData['failed']}次，被锁定{$config_login['time']}小时");
				}
			}
			if(input('post.sublogin')){
			    $username=User::lower_username(input('post.username'));
				$pwd=trim(input('post.password'));
				if(g_sc_c('site','verifycode')){
					
					$verifycode=trim(input('post.verifycode'));
					$check=\util\Tools::check_verify($verifycode);
					if(!$check['success']){
						$this->error($check['msg']);
					}
				}
				
				$check=User::right_username($username);
				if(!$check['success']){
					$this->error($check['msg']);
				}
				$check=User::right_pwd($pwd);
				if(!$check['success']){
					$this->error($check['msg']);
				}
				
				$muser=new \skycaiji\admin\model\User();
				try{
				    $userData=$muser->where('username',$username)->find();
				}catch (\Exception $ex){
				    
				    $this->error($ex->getMessage());
				}
				if(empty($userData)||$userData['password']!=\skycaiji\admin\model\User::pwd_encrypt($pwd,$userData['salt'])){
					
					if(!empty($config_login['limit'])){
						
						$ipLoginData=$mcacheLogin->getCache($clientIpMd5,'data');
						if(!empty($ipLoginData)){
							
						    if(($nowTime-$ipLoginData['lastdate'])<($config_login['time']*3600)){
								
								$ipLoginData['failed']++;
							}else{
								
							    $ipLoginData['lastdate']=$nowTime;
								$ipLoginData['failed']=1;
							}
						}else{
							
						    $ipLoginData['lastdate']=$nowTime;
							$ipLoginData['failed']=1;
						}
						$ipLoginData['ip']=request()->ip();
						$mcacheLogin->setCache($clientIpMd5, $ipLoginData);
						$this->error(lang('user_error_login')."失败{$config_login['failed']}次将被锁定{$config_login['time']}小时，已失败{$ipLoginData['failed']}次");
					}
					$this->error(lang('user_error_login'));
				}
		
				if(input('post.auto')){
					
				    cookie('login_history',intval($userData['uid']).'|'.$muser->generate_key($userData),array('expire'=>3600*24*15));
				}
				
				$muser->setLoginSession($userData);
				
				$serverinfo=input('_serverinfo');
				if(empty($serverinfo)){
					$url=null;
					if(input('?_referer')){
						
						$url=input('_referer','','trim');
					}
					$url=empty($url)?'admin/backstage/index':$url;
					
					$this->success(lang('user_login_in'),$url);
				}else{
					
					$this->success(lang('user_login_in'),null,array('js'=>'window.parent.postMessage("login_success","*");'));
				}
			}else{
				$this->error(lang('user_error_sublogin'));
			}
		}else{
    		return $this->fetch('index');
		}
	}
	/*退出*/
	public function logoutAction(){
		\think\Cookie::delete('login_history');
		set_g_sc('user',null);
		model('User')->setLoginSession(null);
		$this->success(lang('op_success'),'admin/index/index');
	}
	/*验证码*/
	public function verifyAction(){
	    $len=g_sc_c('site','verifycode_len');
	    $len=intval($len);
	    $len=min(max(3,$len),20);
	    
		$config=array(
			'fontSize'=>30,	
		    'length'=>$len,	
			'fontttf'=>'5.ttf',
			'useCurve'=>true,
			'useNoise'=>true 
		);
		ob_clean();

		$captcha = new \think\captcha\Captcha($config);
		return $captcha->entry();
	}
	
	public function verify_img_errorAction(){
	    
	    $LocSystem=new \skycaiji\install\event\LocSystem();
	    $LocSystem=$LocSystem->environmentPhp();
	    $gd=$LocSystem['gd'];
	    $error='';
	    if(empty($gd['loaded'])){
	        
	        $error='php未启用gd模块';
	    }elseif(!empty($gd['lack'])){
	        
	        $error='php未启用gd模块(需支持'.$gd['lack'].')';
	    }
	    if($error){
	        $error='图片验证码错误：'.$error;
	        $this->error($error);
	    }else{
	        $this->success('');
	    }
	}
	
	/*找回密码*/
	public function find_passwordAction(){
		$username=trim(input('post.username'));
		if(empty($username)){
			$username=trim(input('username'));
			$username=base64_decode($username);
		}
		
		$step=max(1,input('step/d',1));
		$stepSname='find_password_step.'.md5($username);

		$stepSession=session($stepSname);
		$muser=model('User');
		if($step>1){
			
			if(strcasecmp(('step'.$step),$stepSession['step'])!==0){
				$this->error(lang('find_pwd_error_step'),'index/find_password');
			}
			
			if(empty($stepSession['user'])){
				$this->error(lang('find_pwd_error_none_user'));
			}
		}
		if(request()->isPost()){
			if(input('post.subForPwd')){
				if(empty($username)){
					$this->error(lang('find_pwd_error_username'));
				}
				if($step===1){
					
					if(g_sc_c('site','verifycode')){
						
						$verifycode=trim(input('verifycode'));
						$check=\util\Tools::check_verify($verifycode);
						if(!$check['success']){
							$this->error($check['msg']);
						}
					}

					/*获取用户信息*/
					
					$username_is_email=false;
					$check=\skycaiji\admin\model\User::right_email($username);
					if($check['success']){
						$username_is_email=true;
					}
					if($username_is_email){
						
						$emailCount=$muser->where(array('email'=>$username))->count();
						if($emailCount<=0){
							$this->error(lang('find_pwd_error_none_email'));
						}elseif($emailCount>1){
							$this->error(lang('find_pwd_error_multiple_emails'));
						}else{
							$userData=$muser->where(array('email'=>$username))->find();
						}
					}else{
						
						$userData=$muser->where(array('username'=>$username))->find();
					}
					if(empty($userData)){
						$this->error(lang('find_pwd_error_none_user'));
					}
					$userData=$userData->toArray();
					
					session($stepSname,array('step'=>'step2','user'=>$userData));
					
					$this->success(lang('redirecting'),'index/find_password?step=2&username='.rawurlencode(base64_encode($username)));
				}elseif($step===2){
					
					$yzm=trim(input('yzm'));
					
					$check=\skycaiji\admin\model\User::right_yzm($username, $yzm);
					if(!$check['success']){
						$this->error($check['msg']);
					}
					$stepSession['step']='step3';
					session($stepSname,$stepSession);

					$this->success(lang('redirecting'),'index/find_password?step=3&username='.rawurlencode(base64_encode($username)));
				}elseif($step===3){
					$pwd=trim(input('password'));
					$repwd=trim(input('repassword'));
					$check=\skycaiji\admin\model\User::right_pwd($pwd);
					if(!$check['success']){
						$this->error($check['msg']);
					}
					$check=\skycaiji\admin\model\User::right_repwd($pwd,$repwd);
					if(!$check['success']){
						$this->error($check['msg']);
					}
					
					$salt=\skycaiji\admin\model\User::rand_salt();
					$pwd=\skycaiji\admin\model\User::pwd_encrypt($pwd,$salt);
					
					$muser->strict(false)->where(array('username'=>$stepSession['user']['username']))->update(array('password'=>$pwd,'salt'=>$salt));
					session($stepSname,null);
					$this->success(lang('find_pwd_success'),'admin/index/index');
				}else{
					$this->error(lang('find_pwd_error_step'),'index/find_password');
				}
			}else{
				$this->error(lang('find_pwd_error_post'));
			}
		}else{
			if($step===2){
			    $emailStatus=return_result('',false);
				if(is_empty(g_sc_c('email'))){
					$emailStatus['msg']=lang('config_error_none_email');
				}else{
					$waitTime=60;
					$waitSname='send_yzm_wait';
					
					$nowTime=time();
					$passTime=abs($nowTime-session($waitSname));
					if($passTime<=$waitTime){
						$emailStatus['msg']=lang('find_pwd_email_wait',array('seconds'=>$waitTime-$passTime));
					}else{
						$expire=config('yzm_expire');
						$minutes=floor($expire/60);
						$yzm=mt_rand(100000,999999);
						session($waitSname,$nowTime);
						$mailReturn=\util\Tools::send_mail(g_sc_c('email'), $stepSession['user']['email'], $stepSession['user']['username'],lang('find_pwd_email_subject'),lang('find_pwd_email_body',array('yzm'=>$yzm,'minutes'=>$minutes)));
						if($mailReturn===true){
							$yzmSname='send_yzm.'.md5($username);
							session(array('name'=>$yzmSname,'expire'=>$expire));
							session($yzmSname,array('yzm'=>$yzm,'time'=>$nowTime));
							$emailStatus['success']=true;
							$emailStatus['msg']=lang('find_pwd_sended',array('email'=>preg_replace('/.{2}\@/', '**@', $stepSession['user']['email'])));
						}else{
							$emailStatus['msg']=lang('find_pwd_email_failed').'<br>'.$mailReturn;
						}
					}
				}
				
				$newPwd='skycaiji123';
				$newPwdEncrypt=\skycaiji\admin\model\User::pwd_encrypt($newPwd,$stepSession['user']['salt']);

				$this->assign('newPwd',$newPwd);
				$this->assign('newPwdEncrypt',$newPwdEncrypt);
				$this->assign('emailStatus',$emailStatus);
			}

			$this->assign('userData',$stepSession['user']);
			$this->assign('username',$username);
			$this->assign('step',$step);
			return $this->fetch();
		}
	}
	/*自动采集后台运行*/
	public function auto_backstageAction(){
	    $key=input('key');
	    $autoBsKey=\util\Param::get_auto_backstage_key();
	    if(empty($key)||$key!=$autoBsKey){
	        
	        
	        $this->error('密钥错误，请在后台执行');
	    }
	    
	    
	    $mconfig=new \skycaiji\admin\model\Config();
	    $caijiConfig=$mconfig->getConfig('caiji','data');
	    init_array($caijiConfig);
	    if(empty($caijiConfig['auto'])){
	        $this->error('请先开启自动采集');
	    }
	    if($caijiConfig['run']!='backstage'){
	        $this->error('不是后台运行方式');
	    }
	    
	    if($mconfig->server_is_cli(true,$caijiConfig['server'])){
	        
	        \util\Tools::cli_command_exec('collect auto_backstage');
	    }else{
	        
	        $curlCname='caiji_auto_curltime_'.$key;
	        if(input('?curltime')){
	            
	            $cacheCurl=cache($curlCname);
	            if(!empty($cacheCurl)&&$cacheCurl>input('curltime')){
	                
	                $this->error('终止过期进程');
	            }
	            cache($curlCname,input('curltime'));
	        }
	        
	        ignore_user_abort(true);
	        set_time_limit(0);
	        
	        CacheModel::getInstance()->setCache('collect_backstage_time',time());
	        
	        \skycaiji\admin\model\Collector::collect_run_auto();
	        
	        sleep(15);
	        
	        
	        try{
	            $maxTimes=0;
	            do {
	                
	                
	                
	                $autoBsKey=\util\Param::get_auto_backstage_key();
	                if(empty($key)||$key!=$autoBsKey){
	                    
	                    
	                    $this->error('密钥错误，终止进程');
	                }
	                
	                $caijiConfig=$mconfig->getConfig('caiji','data');
	                init_array($caijiConfig);
	                if(empty($caijiConfig['auto'])){
	                    $this->error('请先开启自动采集');
	                }
	                if($caijiConfig['run']!='backstage'){
	                    $this->error('不是后台运行方式');
	                }
	                
	                
	                
	                $curltime=time();
	                
	                @get_html(url('admin/index/auto_backstage?key='.$key.'&curltime='.$curltime,null,false,true),null,array('timeout'=>2));
	                
	                sleep(5);
	                
	                $cacheCurl=cache($curlCname);
	                
	                $continue=false;
	                $maxTimes++;
	                if((empty($cacheCurl)||$cacheCurl<$curltime)&&$maxTimes<=3){
	                    
	                    $continue=true;
	                }
	            }while($continue);
	        }catch(\Exception $ex){
	            
	        }
	        exit();
	        
	    }
	}
	/*访问执行采集*/
	public function caijiAction(){
	    if(is_empty(g_sc_c('caiji','auto'))){
	        $this->error('请先开启自动采集','admin/setting/caiji');
	    }
	    if(g_sc_c('caiji','run')!='visit'){
	        $this->error('不是访问触发方式');
	    }
	    $mcache=CacheModel::getInstance();
	    $collectVisitTime=$mcache->getCache('collect_visit_time','data');
	    $nowTime=time();
	    if($collectVisitTime&&$nowTime-$collectVisitTime<=15){
	        
	        $this->success('正在运行','admin/index/caiji',null,10);
	    }
	    
	    $mcache->setCache('collect_visit_time',$nowTime);
	    
	    \skycaiji\admin\model\Collector::collect_run_auto();
	    
	    $this->success('正在采集...','admin/index/caiji',null,10);
	}
	/*运行自动采集*/
	public function auto_collectAction(){
	    
	    if(!$this->_collect_check_key()){
	        $this->error('密钥错误');
	    }
	    
	    if(is_empty(g_sc_c('caiji','auto'))){
	        $this->error('请先开启自动采集');
	    }
	    
	    $this->collect_create_or_run(function(){
	        $taskIds=model('Task')->where("auto>0 and module='pattern'")->order('caijitime asc')->column('id');
	        if(empty($taskIds)){
	            $this->echo_msg_exit('没有可自动采集的任务 <a href="'.url('admin/task/list').'" target="_blank">设置</a>');
	        }
	        return $taskIds;
	    },null,true,\skycaiji\admin\model\Collector::url_backstage_run());
	}
	
	public function collect_processAction(){
	    ignore_user_abort(true);
	    $logFilename=\skycaiji\admin\model\Collector::echo_msg_filename();
	    if(empty($logFilename)){
	        
	        \util\Param::set_task_close_echo();
	    }
	    if(!IS_CLI){
	        
	        if(model('admin/Config')->server_is_cli()){
	            
	            if(!function_exists('proc_open')){
	                $this->echo_msg_exit('php函数proc_open被禁用');
	            }
	            
	            $urlParams=input('param.','','trim');
	            if(!empty($urlParams)&&is_array($urlParams)){
	                $urlParams=base64_encode(json_encode(input('param.')));
	                $urlParams=' --url_params '.$urlParams;
	            }else{
	                $urlParams='';
	            }
	            \util\Tools::cli_command_exec('collect collect_process'.$urlParams);
	            exit();
	        }
	    }
	    
	    
	    register_shutdown_function(function(){
	        \skycaiji\admin\model\Task::collecting_remove_all();
	        
	        $cpKeys=\skycaiji\admin\model\Collector::url_collector_process(true);
	        if(!empty($cpKeys)){
	            $collectorKey=$cpKeys['ckey'];
	            $processKey=$cpKeys['pkey'];
	            if($collectorKey){
	                $cpStatus=\skycaiji\admin\model\Collector::collecting_status_list($collectorKey);
	                if(!$cpStatus['main']){
	                    
	                    \skycaiji\admin\model\Collector::collecting_remove($collectorKey);
	                }else{
	                    
	                    \skycaiji\admin\model\Collector::collecting_remove($collectorKey,$processKey);
	                    $cpStatus['processes'][$processKey]=false;
	                    
	                    $isLock=false;
	                    foreach ($cpStatus['processes'] as $pstatus){
	                        if($pstatus){
	                            
	                            $isLock=true;
	                            break;
	                        }
	                    }
	                    if(!$isLock){
	                        
	                        \skycaiji\admin\model\Collector::collecting_remove($collectorKey);
	                    }
	                }
	            }
	        }
	        
	        $taskIds=g_sc('backstage_task_ids');
	        if(!empty($taskIds)&&is_array($taskIds)){
	            \skycaiji\admin\model\CacheModel::getInstance('backstage_task')->db()->strict(false)->where('cname','in',$taskIds)->update(array('ctype'=>1,'data'=>time()));
	        }
	    });
        
	    if(\skycaiji\admin\model\Collector::url_backstage_run()){
	        
	        \util\Param::set_task_close_echo();
	    }
	    
	    if(!$this->_collect_check_key()){
	        $this->echo_msg_exit('密钥错误');
	    }
	    
	    $cpKeys=\skycaiji\admin\model\Collector::url_collector_process(true);
	    if(empty($cpKeys)){
	        $this->echo_msg_exit('参数错误');
	    }
	    $collectorKey=$cpKeys['ckey'];
	    $processKey=$cpKeys['pkey'];
	    if(empty($collectorKey)||empty($processKey)){
	        $this->echo_msg_exit('参数错误');
	    }
	    
	    $processes=\skycaiji\admin\model\Collector::collecting_data($collectorKey);
	    if(empty($processes)||!is_array($processes)){
	        $this->echo_msg_exit('没有进程');
	    }
	    $taskIds=$processes[$processKey];
	    if(empty($taskIds)||!is_array($taskIds)){
	        $this->echo_msg_exit('进程无任务');
	    }
	    
	    \skycaiji\admin\model\Collector::collecting_lock($collectorKey, $processKey);
	    
	    $this->collect_tasks($taskIds,input('collect_num/d'),input('collect_auto'));
	}
	
	private function _collect_check_key(){
	    if(is_empty(session('user_login'))){
	        
	        if(!\util\Param::exist_cache_key(input('key'))){
	            
	            return false;
	        }
	    }
	    return true;
	}
	
	
	public function proc_open_execAction(){
	    $key=input('key');
	    if(empty($key)||$key!=\util\Param::get_proc_open_exec_key()){
	        $this->error('密钥错误');
	    }
	    $params=cache('proc_open_exec_params');
	    
	    \util\Param::set_proc_open_exec_key();
	    cache('proc_open_exec_params',null);
	    
	    $info=array();
	    if(!empty($params)&&is_array($params)){
	        $timeout=intval($params[2]);
	        $timeout=max($timeout,15);
	        set_time_limit($timeout);
	        \util\Funcs::close_session();
	        $info=\util\Tools::proc_open_exec($params[0],$params[1],$params[2],$params[3],$params[4]);
	    }
	    return json($info);
	}
}