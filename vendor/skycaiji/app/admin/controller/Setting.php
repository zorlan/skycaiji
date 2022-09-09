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

class Setting extends BaseController {
    /*站点设置*/
    public function siteAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            $config=array();
            $config['verifycode']=input('verifycode/d',0);
            $config['verifycode_len']=input('verifycode_len/d',0);
            $config['hidehome']=input('hidehome/d',0);
            $config['closelog']=input('closelog/d',0);
            $config['dblong']=input('dblong/d',0);
            $config['login']=input('login/a',array());
            $config['closetrans']=input('closetrans/d',0);
            $config['timezone']=input('timezone','');
            
            $config['verifycode_len']=min(max(3,$config['verifycode_len']),20);
            
            if($config['login']['limit']){
                
                if(empty($config['login']['failed'])){
                    $this->error('请设置失败次数');
                }
                if(empty($config['login']['time'])){
                    $this->error('请设置锁定时间');
                }
            }
            
            $mconfig->setConfig('site',$config);
            $this->success(lang('op_success'),'setting/site');
        }else{
            $this->set_html_tags(
                lang('setting_site'),
                lang('setting_site'),
                breadcrumb(array(array('url'=>url('setting/site'),'title'=>lang('setting_site'))))
                );
            $siteConfig=$mconfig->getConfig('site','data');
            init_array($siteConfig);
            $this->assign('showTime','服务器时间：'.date('Y-m-d H:i:s').' , 服务器的协调世界时(UTC)：'.gmdate('Y-m-d H:i:s'));
            $this->assign('siteConfig',$siteConfig);
        }
        return $this->fetch();
    }
    public function site_timezoneAction(){
        if(request()->isPost()){
            $offset=input('offset/d',0);
            $jsTime=input('time',0);
            $jsTime=$jsTime?intval(substr($jsTime,0,-3)):0;
            if($jsTime<=0){
                $this->error('时间错误');
            }
            if(abs(time()-$jsTime)>60){
                $this->error('抱歉，您的服务器时间与实际时间不符！');
            }
            $timezone='';
            if($offset==0){
                $timezone='UTC';
            }else{
                
                $timezone='UTC'.($offset>0?'-':'+').abs($offset);
            }
            $this->success('','',array('timezone'=>$timezone));
        }else{
            $this->error('自动调整失败');
        }
    }
    
    /*采集设置*/
    public function caijiAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            
            if($mconfig->where('cname','download_img')->count()<=0){
                
                $caijiConfig=$mconfig->getConfig('caiji','data');
                init_array($caijiConfig);
                $imgConfig=$mconfig->get_img_config_from_caiji($caijiConfig);
                if(!empty($imgConfig)){
                    
                    $mconfig->setConfig('download_img',$imgConfig);
                }
            }
            
            $config=array();
            $config['robots']=input('robots/d',0);
            $config['auto']=input('auto/d',0);
            $config['run']=input('run');
            $config['server']=input('server');
            $config['server_php']=input('server_php');
            $config['process_num']=input('process_num/d',0);
            $config['num']=input('num/d',0);
            $config['interval']=input('interval/d',0);
            $config['timeout']=input('timeout/d',0);
            $config['interval_html']=input('interval_html/d',0);
            $config['same_url']=input('same_url/d',0);
            $config['same_title']=input('same_title/d',0);
            $config['real_time']=input('real_time/d',0);
            $config['retry']=input('retry/d',0);
            $config['wait']=input('wait/d',0);
            
            unset($config['download_img']);
            
            if($mconfig->server_is_cli(true,$config['server'])){
                
                $this->ajax_check_userpwd();
                
                if(!function_exists('proc_open')){
                    $this->error('抱歉cli命令行模式需开启proc_open函数');
                }
            }
            
            
            $mconfig->setConfig('caiji',$config);
            if($config['auto']){
                
                if($config['run']=='backstage'){
                    
                    $bskey=\util\Funcs::uniqid('auto_backstage');
                    \util\Param::set_auto_backstage_key($bskey);
                    @get_html(url('admin/index/auto_backstage?key='.$bskey,null,false,true),null,array('timeout'=>3));
                }
            }
            $this->success(lang('op_success'),'setting/caiji');
        }else{
            $this->set_html_tags(
                lang('setting_caiji'),
                lang('setting_caiji'),
                breadcrumb(array(array('url'=>url('setting/caiji'),'title'=>lang('setting_caiji'))))
                );
            $caijiConfig=$mconfig->getConfig('caiji','data');
            init_array($caijiConfig);
            if($caijiConfig['html_interval']>0){
                
                $caijiConfig['interval_html']=intval($caijiConfig['html_interval'])*1000;
            }
            
            $phpExeFile=\skycaiji\admin\model\Config::detect_php_exe();
            
            $this->assign('caijiConfig',$caijiConfig);
            $this->assign('phpExeFile',$phpExeFile);
        }
        return $this->fetch();
    }
    /*图片本地化设置*/
    public function download_imgAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            $config=array();
            
            $config['download_img']=input('download_img/d',0);
            $config['data_image']=input('data_image/d',0);
            $config['interval_img']=input('interval_img/d',0);
            
            $config['img_timeout']=input('img_timeout/d',0);
            $config['more_suffix']=input('more_suffix','');
            $config['retry']=input('retry/d',0);
            $config['wait']=input('wait/d',0);
            $config['img_path']=trim(input('img_path',''));
            $config['img_url']=input('img_url','','trim');
            $config['img_name']=input('img_name','');
            $config['name_custom_path']=input('name_custom_path','');
            $config['name_custom_name']=input('name_custom_name','');
            $config['charset']=input('charset','');
            $config['charset_custom']=input('charset_custom','');
            $config['img_max']=input('img_max/d',0);
            
            $config['img_func']=input('img_func','');
            $config['img_func_param']=input('img_func_param','');
            
            
            if(!empty($config['more_suffix'])){
                if(preg_match_all('/\b[a-zA-Z]\w+\b/i', $config['more_suffix'],$msuffix)){
                    
                    $msuffix=$msuffix[0];
                    $msuffix=implode(',', $msuffix);
                    $config['more_suffix']=strtolower($msuffix);
                }else{
                    $config['more_suffix']='';
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
            
            
            $checkNamePath=$mconfig->check_img_name_path($config['name_custom_path']);
            if($config['img_name']=='custom'){
                
                if(empty($config['name_custom_path'])){
                    $this->error('请输入图片名称自定义路径');
                }
                if(!$checkNamePath['success']){
                    $this->error($checkNamePath['msg']);
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
            
            if($config['charset']=='custom'&&empty($config['charset_custom'])){
                $this->error('请输入系统编码自定义内容');
            }
            
            $mconfig->setConfig('download_img',$config);
            
            $this->success(lang('op_success'),'setting/download_img');
        }else{
            $this->set_html_tags(
                '图片本地化设置',
                '图片本地化设置',
                breadcrumb(array(array('url'=>url('setting/caiji'),'title'=>lang('setting_caiji')),array('url'=>url('setting/download_img'),'title'=>'图片本地化')))
                );
            $imgConfig=$mconfig->getConfig('download_img','data');
            init_array($imgConfig);
            if(empty($imgConfig)){
                
                $caijiConfig=$mconfig->getConfig('caiji','data');
                init_array($caijiConfig);
                $imgConfig=$mconfig->get_img_config_from_caiji($caijiConfig);
            }
            
            if(!is_array($imgConfig)){
                $imgConfig=array();
            }
            
            if(empty($imgConfig)){
                
                
                $imgConfig['img_timeout']=0;
                $imgConfig['img_max']=0;
                $imgConfig['interval_img']=0;
                $imgConfig['wait']=0;
                $imgConfig['retry']=0;
            }else{
                
                if($imgConfig['img_interval']>0){
                    
                    $imgConfig['interval_img']=$imgConfig['img_interval']*1000;
                }
            }
            
            $this->assign('imgConfig',$imgConfig);
            return $this->fetch('download_img');
        }
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
            $type_list=input('type_list','','trim');
            
            $ip_list=empty($ip_list)?array():json_decode($ip_list,true);
            $user_list=empty($user_list)?array():json_decode($user_list,true);
            $pwd_list=empty($pwd_list)?array():json_decode($pwd_list,true);
            $type_list=empty($type_list)?array():json_decode($type_list,true);
            
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
                
                $ip_list=array_map('trim', $ip_list);
                $user_list=array_map('trim', $user_list);
                $pwd_list=array_map('trim', $pwd_list);
                $type_list=array_map('trim', $type_list);
                
                
                $nowTime=time();
                for($k=count($ip_list);$k>=0;$k--){
                    $v=$ip_list[$k];
                    if(empty($v)){
                        
                        continue;
                    }
                    $newData=array(
                        'ip'=>$v,
                        'user'=>$user_list[$k],
                        'pwd'=>$pwd_list[$k],
                        'type'=>$type_list[$k],
                        'invalid'=>0,
                        'failed'=>0,
                        'num'=>0,
                        'time'=>0,
                        'addtime'=>$nowTime,
                    );
                    if($mproxy->where(array('ip'=>$newData['ip']))->count()>0){
                        
                        unset($newData['invalid']);
                        
                        $mproxy->strict(false)->where(array('ip'=>$newData['ip']))->update($newData);
                    }else{
                        
                        $mproxy->db()->insert($newData,true);
                    }
                }
            }
            
            
            $config['api']=input('api/a',array(),'trim');
            $config['apis']=input('apis/a',array(),'trim');
            $config['apis']=is_array($config['apis'])?$config['apis']:array();
            foreach ($config['apis'] as $k=>$v){
                if(empty($v['api_url'])||!preg_match('/^\w+\:\/\//',$v['api_url'])){
                    
                    unset($config['apis'][$k]);
                }
            }
            $config['apis']=array_values($config['apis']);
            
            $mconfig->setConfig('proxy',$config);
            $this->success(lang('op_success'),'setting/proxy');
        }else{
            $this->set_html_tags(
                '代理设置',
                '代理设置',
                breadcrumb(array(array('url'=>url('setting/caiji'),'title'=>lang('setting_caiji')),array('url'=>url('setting/proxy'),'title'=>'代理')))
                );
            $proxyConfig=$mconfig->getConfig('proxy','data');
            init_array($proxyConfig);
            $proxyConfig['ip_count']=$mproxy->count();
            $this->assign('proxyConfig',$proxyConfig);
            $this->assign('proxyTypes',$mproxy->proxy_types());
        }
        return $this->fetch();
    }
    /*翻译设置*/
    public function translateAction(){
        $mconfig=model('Config');
        $apiTypes=array('baidu','youdao','qq','google');
        if(request()->isPost()){
            $config=array();
            $config['open']=input('open/d',0);
            $config['api']=input('api','','strtolower');
            $config['pass_html']=input('pass_html/d',0);
            $config['interval']=input('interval/d',0);
            $config['retry']=input('retry/d',0);
            $config['wait']=input('wait/d',0);
            
            foreach ($apiTypes as $v){
                $config[$v]=input($v.'/a',array(),'trim');
            }
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
            $this->success(lang('op_success'),'setting/translate');
        }else{
            $this->set_html_tags(
                '翻译设置',
                '翻译设置',
                breadcrumb(array(array('url'=>url('setting/caiji'),'title'=>lang('setting_caiji')),array('url'=>url('setting/translate'),'title'=>'翻译')))
                );
            $transConfig=$mconfig->getConfig('translate','data');
            init_array($transConfig);
            $apiLangs=array();
            foreach ($apiTypes as $api){
                $transConfig[$api]=is_array($transConfig[$api])?$transConfig[$api]:array();
                foreach ($transConfig[$api] as $k=>$v){
                    $transConfig[$api][$k]=htmlspecialchars($v,ENT_QUOTES);
                }
                $apiLangs[$api]=\util\Translator::get_api_langs($api);
                $apiLangs[$api]=is_array($apiLangs[$api])?implode(', ',$apiLangs[$api]):'';
            }
            
            $this->assign('transConfig',$transConfig);
            $this->assign('apiLangs',$apiLangs);
            return $this->fetch();
        }
    }
    /*云端设置*/
    public function storeAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            $sameAsPwd=input('same_as_pwd');
            
            $config=array();
            $config['authkey']=input('authkey','','trim');
            $config['authkey_store']=input('authkey_store','','trim');
            
            $mprov=model('Provider');
            $check=$mprov->checkAuthkey($config['authkey'],$sameAsPwd);
            if(!$check['success']){
                $this->error($check['msg'],'',$check['data']);
            }
            $check=$mprov->checkAuthkey($config['authkey_store'],$sameAsPwd);
            if(!$check['success']){
                $this->error($check['msg'],'',$check['data']);
            }
            
            $mconfig->setConfig('store',$config);
            $this->success(lang('op_success'),'setting/store');
        }else{
            $this->set_html_tags(
                lang('setting_store'),
                lang('setting_store'),
                breadcrumb(array(array('url'=>url('setting/store'),'title'=>lang('setting_store'))))
                );
            $storeConfig=$mconfig->getConfig('store','data');
            init_array($storeConfig);
            $provCount=model('Provider')->count();
            $provCount=intval($provCount);
            
            $this->assign('storeConfig',$storeConfig);
            $this->assign('provCount',$provCount);
        }
        return $this->fetch();
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
                
                $return=\util\Tools::send_mail($config, $config['email'], $config['sender'],lang('set_email_test_subject'),lang('set_email_test_body'));
                if($return===true){
                    $this->success(lang('set_email_test_body'),'');
                }else{
                    $this->error($return,'');
                }
            }else{
                $mconfig->setConfig('email',$config);
                $this->success(lang('op_success'),'setting/email');
            }
        }else{
            $this->set_html_tags(
                lang('setting_email'),
                lang('setting_email'),
                breadcrumb(array(array('url'=>url('setting/email'),'title'=>lang('setting_email'))))
                );
            $emailConfig=$mconfig->getConfig('email','data');
            init_array($emailConfig);
            $this->assign('emailConfig',$emailConfig);
        }
        return $this->fetch();
    }
    /*页面渲染设置*/
    public function page_renderAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            $config=array();
            $config['tool']=strtolower(input('tool'));
            $config['chrome']=input('chrome/a',array());
            $config['timeout']=input('timeout/d');
            if(!in_array($config['tool'],array('chrome'))){
                $config['tool']='';
            }
            
            if(!empty($config['tool'])){
                $this->ajax_check_userpwd();
            }
            
            $mconfig->setConfig('page_render',$config);
            if($config['tool']=='chrome'){
                $chromeSoket=new \util\ChromeSocket($config['chrome']['host'],$config['chrome']['port'],$config['timeout'],$config['chrome']['filename'],$config['chrome']);
                $this->_chrome_start($chromeSoket);
            }
            $this->success(lang('op_success'),'setting/page_render');
        }else{
            $this->set_html_tags(
                '页面渲染设置',
                '页面渲染设置 <small><a href="https://www.skycaiji.com/manual/doc/page_render" target="_blank"><span class="glyphicon glyphicon-info-sign"></span></a></small>',
                breadcrumb(array(array('url'=>url('setting/caiji'),'title'=>lang('setting_caiji')),array('url'=>url('setting/page_render'),'title'=>'页面渲染')))
            );
            $config=$mconfig->getConfig('page_render','data');
            init_array($config);
            init_array($config['chrome']);
            $this->assign('config',$config);
            
            if($mconfig->page_render_is_chrome(true,$config['tool'])){
                
                $chromeSoket=new \util\ChromeSocket($config['chrome']['host'],$config['chrome']['port'],$config['timeout'],$config['chrome']['filename'],$config['chrome']);
                $toolIsOpen=$chromeSoket->hostIsOpen();
                $this->assign('toolIsOpen',$toolIsOpen);
            }
            return $this->fetch('page_render');
        }
    }
    /*清理缓存目录*/
    public function cleanAction(){
        if(request()->isPost()){
            set_time_limit(1000);
            $types=input('types/a');
            if(!is_array($types)){
                $types=array();
            }
            
            $runtimePath=config('runtime_path');
            if(is_dir($runtimePath)){
                if(in_array('all', $types)){
                    
                    \util\Tools::clear_runtime_dir();
                }else{
                    $systemPaths=array(
                        $runtimePath.'/log',
                        $runtimePath.'/cache',
                        $runtimePath.'/temp',
                        $runtimePath.'/classmap',
                        $runtimePath.'/schema',
                    );
                    if(in_array('system', $types)){
                        
                        foreach ($systemPaths as $systemPath){
                            \util\Funcs::clear_dir($systemPath);
                        }
                    }
                    if(in_array('data', $types)){
                        
                        \util\Tools::clear_runtime_dir($systemPaths);
                    }
                }
            }
            
            if(in_array('all', $types)||in_array('data', $types)){
                
                $error='';
                try{
                    $cacheTimeout=time()-(3600*24);
                    $cacheTables=array('source_url','level_url','cont_url','collecting','temp');
                    foreach ($cacheTables as $cacheTable){
                        CacheModel::getInstance($cacheTable)->db()->where('dateline','<',$cacheTimeout)->delete();
                    }
                }catch (\Exception $ex){
                    $error=$ex->getMessage();
                }
                if($error){
                    $this->error($error);
                }
            }
            
            $this->success('清理完成','backstage/index');
        }else{
            return $this->fetch();
        }
    }
    /*测试php*/
    public function test_phpAction(){
        if(request()->isPost()){
            $this->ajax_check_userpwd();
            
            if(!function_exists('proc_open')){
                $this->error('需开启proc_open函数');
            }else{
                $phpFile=input('php','','trim');
                
                $phpvInfo=model('Config')->exec_php_version($phpFile);
                if($phpvInfo===false){
                    $this->error('未检测到PHP可执行文件，请手动输入');
                }else{
                    if($phpvInfo['success']){
                        $this->success($phpvInfo['msg']?$phpvInfo['msg']:'测试成功');
                    }else{
                        $this->error($phpvInfo['msg']?$phpvInfo['msg']:'测试失败');
                    }
                }
            }
        }
        $this->error('测试失败');
    }
    
    public function chrome_testAction(){
        if(request()->isPost()){
            $this->ajax_check_userpwd();
            $chrome=input('chrome/a',array());
            
            $return=\util\ChromeSocket::execHeadless($chrome['filename'], $chrome['port'], $chrome, 'all', true);
            if(!empty($return['error'])){
                
                $this->error($return['error']);
            }else{
                
                $info=is_array($return['info'])?$return['info']:array();
                if(is_array($info['status'])&&$info['status']['running']){
                    
                    if($info['error']){
                        
                        $info['error']=preg_replace('/[^\r\n]*(WARNING|ERROR)\:[^\r\n]*/i', '', $info['error']);
                        $info['error']=trim($info['error']);
                    }
                    if($info['error']){
                        $this->error($info['error']);
                    }elseif($info['output']){
                        $this->success('测试成功：'.$info['output']);
                    }else{
                        $this->success('测试成功');
                    }
                }elseif($info['error']){
                    $this->error($info['error']);
                }
            }
        }
        $this->error('测试失败');
    }
    
    public function chrome_cleanAction(){
        $config=model('Config')->getConfig('page_render','data');
        init_array($config);
        init_array($config['chrome']);
        $chromeSoket=new \util\ChromeSocket($config['chrome']['host'],$config['chrome']['port'],$config['timeout'],$config['chrome']['filename'],$config['chrome']);
        $chromeSoket->clearBrowser();
        $this->success('清理完成','');
    }
    
    public function chrome_restartAction(){
        $config=model('Config')->getConfig('page_render','data');
        init_array($config);
        init_array($config['chrome']);
        $chromeSoket=new \util\ChromeSocket($config['chrome']['host'],$config['chrome']['port'],$config['timeout'],$config['chrome']['filename'],$config['chrome']);
        $this->_chrome_start($chromeSoket);
        $this->success('重启完成','setting/page_render');
    }
    
    private function _chrome_start($chromeSoket){
        if($chromeSoket){
            try {
                $chromeSoket->closeBrowser();
                $chromeSoket->openHost();
            }catch (\Exception $ex){
                $this->error($ex->getMessage());
            }
        }else{
            $this->error('失败');
        }
    }
}