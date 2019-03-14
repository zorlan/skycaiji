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

class Setting extends BaseController {
	/*站点设置*/
    public function siteAction(){
    	$mconfig=model('Config');
    	if(request()->isPost()){
    		$config=array();
    		$config['verifycode']=input('verifycode/d',0);
    		$config['hidehome']=input('hidehome/d',0);
    		$config['closelog']=input('closelog/d',0);
    		$config['dblong']=input('dblong/d',0);
    		$config['login']=input('login/a');
    		if($config['login']['limit']){
    			
    			if(empty($config['login']['failed'])){
    				$this->error('请设置失败次数');
    			}
    			if(empty($config['login']['time'])){
    				$this->error('请设置锁定时间');
    			}
    		}
    		
    		$mconfig->setConfig('site',$config);
			$this->success(lang('op_success'),'Setting/site');
    	}else{
    		$GLOBALS['content_header']=lang('setting_site');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Setting/site'),'title'=>lang('setting_site'))));
    		$siteConfig=$mconfig->getConfig('site','data');
    		$this->assign('siteConfig',$siteConfig);
    	}
        return $this->fetch();
    }
    /*采集设置*/
    public function caijiAction(){
    	$mconfig=model('Config');
    	if(request()->isPost()){
    		$config=array();
    		$config['auto']=input('auto/d',0);
    		$config['run']=input('run');
    		$config['server']=input('server');
    		$config['num']=input('num/d',0);
    		$config['interval']=input('interval/d',0);
    		$config['timeout']=input('timeout/d',0);
    		$config['html_interval']=input('html_interval/d',0);
    		$config['real_time']=input('real_time/d',0);
    		$config['download_img']=input('download_img/d',0);
    		$config['img_path']=trim(input('img_path',''));
    		$config['img_url']=input('img_url','','trim');
    		$config['img_name']=input('img_name','');
    		$config['img_timeout']=input('img_timeout/d',0);
    		$config['img_interval']=input('img_interval/d',0);
    		$config['img_max']=input('img_max/d',0);

    		if($config['server']=='cli'){
    			
    			if(!function_exists('proc_open')){
    				$this->error('抱歉cli命令行模式需开启proc_open函数');
    			}
    		}
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
    		$mconfig->setConfig('caiji',$config);
    		if($config['auto']){
    			
    			remove_auto_collecting();
    			if($config['run']=='backstage'){
    				
    				@get_html(url('Admin/Index/backstage?autorun=1',null,false,true),null,array('timeout'=>3));
    			}
			}
    		
			$this->success(lang('op_success'),'Setting/caiji');
    	}else{
    		$GLOBALS['content_header']=lang('setting_caiji');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Setting/caiji'),'title'=>lang('setting_caiji'))));
    		$caijiConfig=$mconfig->getConfig('caiji','data');
    		$this->assign('caijiConfig',$caijiConfig);
    	}
        return $this->fetch();
    }
    /*代理设置*/
    public function proxyAction(){
    	$mconfig=model('Config');
    	$mproxy=model('Proxyip');
    	if(request()->isPost()){
    		$config=array();
    		$ip_list=input('ip_list','','trim');
    		$user_list=input('user_list','','trim');
    		$pwd_list=input('pwd_list','','trim');

    		$ip_list=empty($ip_list)?null:json_decode($ip_list,true);
    		$user_list=empty($user_list)?null:json_decode($user_list,true);
    		$pwd_list=empty($pwd_list)?null:json_decode($pwd_list,true);
    		
    		$config['open']=input('open/d',0);
    		$config['failed']=input('failed/d',0);
    		$config['use']=strtolower(input('use'));
    		$config['use_num']=input('use_num/d',0);
    		$config['use_time']=input('use_time/d',0);
    		
    		if('num'==$config['use']&&$config['use_num']<=0){
    			$this->error('每个IP使用多少次必须大于0');
    		}
    		if('time'==$config['use']&&$config['use_time']<=0){
    			$this->error('每个IP使用多少分钟必须大于0');
    		}
    		
    		
    		if(!empty($ip_list)&&is_array($ip_list)){
    			
    			$mproxy->where(array('ip'=>array('not in',$ip_list)))->delete();
    			
    			$ip_list=array_map('trim', $ip_list);
    			$user_list=array_map('trim', $user_list);
    			$pwd_list=array_map('trim', $pwd_list);
    			
    			foreach ($ip_list as $k=>$v){
    				if(empty($v)){
    					
    					continue;
    				}
    				$newData=array(
    					'ip'=>$v,
    					'user'=>$user_list[$k],
    					'pwd'=>$pwd_list[$k],
    					'invalid'=>0,
    					'failed'=>0,
    					'num'=>0,
    					'time'=>0,
    				);
    				if($mproxy->where(array('ip'=>$newData['ip']))->count()>0){
    					
    					unset($newData['invalid']);
    					
    					$mproxy->strict(false)->where(array('ip'=>$newData['ip']))->update($newData);
    				}else{
    					
    					$mproxy->db()->insert($newData,true);
    				}
    			}
    		}else{
				
    			$mproxy->where('1=1')->delete();
    		}
    		$mconfig->setConfig('proxy',$config);
			$this->success(lang('op_success'),'Setting/Proxy');
    	}else{
    		$GLOBALS['content_header']='代理设置';
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Setting/Proxy'),'title'=>'代理设置')));
    		$proxyConfig=$mconfig->getConfig('proxy','data');
    		$proxyConfig['ip_list']=$mproxy->column('*');
    		$this->assign('proxyConfig',$proxyConfig);
    	}
    	return $this->fetch();
    }
    /*批量添加代理*/
    public function proxyBatchAction(){
    	if(request()->isPost()){
    		$ips=input('ips');
    		$fmt=input('format');
    		
    		$fmt=str_replace(array('[ip]','[端口]','[用户名]','[密码]')
    			,array('(?P<ip>(\d+\.)+\d+)','(?P<port>\d+)','(?P<user>[^\s]+)','(?P<pwd>[^\s]+)')
    			,$fmt);
    		$ipList=array();
    		if(preg_match_all('/[^\r\n]+/',$ips,$m_ips)){
    			foreach ($m_ips[0] as $ip){
    				if(preg_match('/'.$fmt.'/',$ip,$ipInfo)){
    					$ipList[]=array(
    						'ip'=>$ipInfo['ip'].':'.$ipInfo['port'],
    						'user'=>$ipInfo['user'],
    						'pwd'=>$ipInfo['pwd'],
    					);
    				}
    			}
    		}
    		if(empty($ipList)){
    			$this->error('没有匹配到数据');
    		}else{
    			$this->success('',null,$ipList);
    		}
    	}else{
    		return $this->fetch('proxyBatch');
    	}
    }
    /*翻译设置*/
    public function translateAction(){
    	$mconfig=model('Config');
    	if(request()->isPost()){
    		$config=array();
    		$config['open']=input('open/d',0);
    		$config['api']=input('api','','strtolower');
    		$config['baidu']=input('baidu/a',null,'trim');
    		$config['youdao']=input('youdao/a',null,'trim');
    		if(!empty($config['api'])){
    			
	    		if(empty($config[$config['api']])){
	    			$this->error('请填写api配置');
	    		}
	    		foreach ($config[$config['api']] as $k=>$v){
	    			if(empty($v)){
	    				$this->error('请填写api配置');
	    			}
	    		}
    		}
    		
    		$mconfig->setConfig('translate',$config);
    		$this->success(lang('op_success'),'Setting/translate');
    	}else{
    		$GLOBALS['content_header']='翻译设置';
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Setting/translate'),'title'=>'翻译设置')));
    		$transConfig=$mconfig->getConfig('translate','data');

    		foreach ($transConfig['baidu'] as $k=>$v){
    			$transConfig['baidu'][$k]=htmlspecialchars($v,ENT_QUOTES);
    		}
    		foreach ($transConfig['youdao'] as $k=>$v){
    			$transConfig['youdao'][$k]=htmlspecialchars($v,ENT_QUOTES);
    		}
    		
    		$this->assign('transConfig',$transConfig);
    		return $this->fetch();
    	}
    }
    /*邮箱设置*/
    public function emailAction(){
    	$is_test=input('is_test/d',0);
    	$mconfig=model('Config');
    	if(request()->isPost()){
    		$config=array();
    		$config['sender']=input('sender');
    		$config['email']=input('email');
    		$config['pwd']=input('pwd');
    		$config['smtp']=input('smtp');
    		$config['port']=input('port');
    		$config['type']=input('type');
    		
    		if($is_test){
    			
    			$return=send_mail($config, $config['email'], $config['sender'],lang('set_email_test_subject'),lang('set_email_test_body'));
    			if($return===true){
    				$this->success(lang('set_email_test_body'),'');
    			}else{
    				$this->error($return,'');
    			}
    		}else{
    			$mconfig->setConfig('email',$config);
    			$this->success(lang('op_success'),'Setting/email');
    		}
    	}else{
    		$GLOBALS['content_header']=lang('setting_email');
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Setting/email'),'title'=>lang('setting_email'))));
    		$emailConfig=$mconfig->getConfig('email','data');
    		$this->assign('emailConfig',$emailConfig);
    	}
    	return $this->fetch();
    }
    /*页面渲染设置*/
    public function page_renderAction(){
    	$is_test=input('is_test/d',0);
    	$mconfig=model('Config');
    	if(request()->isPost()){
    		$config=array();
    		$config['tool']=strtolower(input('tool'));
    		$config['chrome']=input('chrome/a');
    		$config['timeout']=input('timeout/d');
    		if(!in_array($config['tool'],array('chrome'))){
    			$config['tool']='';
    		}
    		if(empty($config['tool'])){
    			
    			
    		}
    		
    		if($config['tool']=='chrome'){
    			if(version_compare(PHP_VERSION,'5.5','<')){
    				
    				$this->error('该功能仅支持php5.5及以上版本');
    			}
    			if($config['chrome']['port']==80){
    				$this->error('不能设置为80端口','');
    			}
    		}
    		$mconfig->setConfig('page_render',$config);
    		if($config['tool']=='chrome'){
    			set_time_limit(10);
    			$chromeSoket=new \util\ChromeSocket($config['chrome']['host'],$config['chrome']['port'],$config['timeout'],$config['chrome']['filename']);
    			try {
    				$chromeSoket->openHost();
    			}catch (\Exception $ex){
    				$this->error($ex->getMessage());
    			}
    		}
    		$this->success(lang('op_success'),'Setting/page_render');
    	}else{
    		$GLOBALS['content_header']='页面渲染设置 <small><a href="http://www.skycaiji.com/manual/doc/page_render" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>';
    		$GLOBALS['breadcrumb']=breadcrumb(array(array('url'=>url('Setting/page_render'),'title'=>'页面渲染设置')));
    		$config=$mconfig->getConfig('page_render','data');
    		$this->assign('config',$config);
    		
    		if($config['tool']=='chrome'){
    			
    			$cConfig=$config['chrome'];
    			$chromeSoket=new \util\ChromeSocket($cConfig['host'],$cConfig['port'],$config['timeout'],$cConfig['filename']);
    			$toolIsOpen=$chromeSoket->hostIsOpen();
    			$this->assign('toolIsOpen',$toolIsOpen);
    		}
    		return $this->fetch();
    	}
    }
    /*清理缓存目录*/
    public function cleanAction(){
    	set_time_limit(1000);
    	$path=realpath(config('root_path').'/runtime');
    	clear_dir($path);
    	$this->success();
    }
}