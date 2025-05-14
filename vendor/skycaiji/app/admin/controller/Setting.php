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
use util\Funcs;

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
            $config['swoole_host']=input('swoole_host');
            $config['swoole_port']=input('swoole_port');
            $config['swoole_php']=input('swoole_php');
            $config['api']=input('api/d',0);
            $config['api_type']=input('api_type');
            $config['api_key']=input('api_key');
            $config['api_interval']=input('api_interval/d',0);
            $config['process_num']=input('process_num/d',0);
            $config['num']=input('num/d',0);
            $config['interval']=input('interval/d',0);
            $config['timeout']=input('timeout/d',0);
            $config['interval_html']=input('interval_html/d',0);
            $config['same_url']=input('same_url/d',0);
            $config['same_title']=input('same_title/d',0);
            $config['same_content']=input('same_content/d',0);
            $config['real_time']=input('real_time/d',0);
            $config['retry']=input('retry/d',0);
            $config['wait']=input('wait/d',0);
            $config['ip_resolve']=input('ip_resolve');
            $config['max_redirs']=input('max_redirs/d',0);
            
            unset($config['download_img']);
            
            if($mconfig->server_is_cli(true,$config['server'])||$mconfig->server_is_swoole(true,$config['server'])){
                
                $this->ajax_check_userpwd();
                
                if(!function_exists('proc_open')){
                    if($mconfig->server_is_cli(true,$config['server'])){
                        $this->error('cli命令行模式需开启proc_open函数');
                    }elseif($mconfig->server_is_swoole_php(true,$config['server'],$config['swoole_php'])){
                        $this->error('swoole快捷启动需开启proc_open函数');
                    }
                }
            }
            
            if(in_array($config['swoole_port'], array(80,8080,443))){
                if($mconfig->server_is_swoole(true,$config['server'])){
                    $this->error('swoole端口号不能设置为'.$config['swoole_port']);
                }else{
                    $config['swoole_port']='';
                }
            }
            
            $mconfig->setConfig('caiji',$config);
            
            
            $this->_run_auto_backstage();
            
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
            
            
            $apiUrl=null;
            $apiParams=null;
            if($caijiConfig['api']){
                
                $apiUrl=array('s'=>'api_caiji');
                if($caijiConfig['api_type']=='safe'){
                    $apiUrl['sign']='签名';
                    $apiUrl['ts']='时间戳';
                    $apiParams[]='签名：md5(密钥+时间戳)';
                    $apiParams[]='时间戳：注意是11位数字不是年月日时间';
                }else{
                    if($caijiConfig['api_key']){
                        $apiUrl['key']=md5($caijiConfig['api_key']);
                    }
                }
                $apiUrl['tids']='任务id';
                $apiParams[]='任务id：可在任务中查看，多个id用逗号分隔';
                $apiParams=implode('<br>',$apiParams);
                foreach ($apiUrl as $k=>$v){
                    $apiUrl[$k]=$k.'='.$v;
                }
                $apiUrl=implode('&', $apiUrl);
                $apiUrl=config('root_website').'/?'.$apiUrl;
            }
            
            $this->assign('caijiConfig',$caijiConfig);
            $this->assign('phpExeFile',$phpExeFile);
            $this->assign('apiUrl',$apiUrl);
            $this->assign('apiParams',$apiParams);
        }
        return $this->fetch();
    }
    
    public function caiji_checkAction(){
        $data=array('server'=>g_sc_c('caiji','server'),'error'=>'');
        $mconfig=model('Config');
        if($mconfig->server_is_cli()){
            $phpResult=$mconfig->php_is_valid(g_sc_c('caiji','server_php'));
            if(empty($phpResult['success'])){
                $data['error']='PHP可执行文件错误'.($phpResult['msg']?('：'.$phpResult['msg']):'');
            }
        }elseif($mconfig->server_is_swoole()){
            $ss=new \util\SwooleSocket(g_sc_c('caiji','swoole_host'),g_sc_c('caiji','swoole_port'));
            $error=$ss->websocketError();
            if($error){
                $data['error']=$error;
            }
            $phpResult=$ss->checkPhp(g_sc_c('caiji','server'),g_sc_c('caiji','swoole_php'),true,true);
            if(!$phpResult['success']){
                if($data['error']){
                    $data['error'].='<br>';
                }
                $data['error'].=$phpResult['msg'];
            }
        }
        $this->success('','',$data);
    }
    
    public function _run_auto_backstage(){
        $this->_run_auto_start_up();
        
        $config=model('Config')->getConfig('caiji','data');
        init_array($config);
        if($config['auto']){
            if($config['run']=='backstage'){
                
                $bskey=\util\Param::set_auto_backstage_key();
                @get_html(url('admin/index/auto_backstage?key='.$bskey,null,false,true),null,array('timeout'=>2));
            }
        }
    }
    
    public function _run_auto_start_up($types=null){
        $mconfig=model('Config');
        if(empty($types)||in_array('page_render', $types)){
            
            $config=$mconfig->getConfig('page_render','data');
            \util\ChromeSocket::config_start($config);
        }
        if(empty($types)||in_array('swoole', $types)){
            
            $config=$mconfig->getConfig('caiji','data');
            init_array($config);
            if($mconfig->server_is_swoole(true,$config['server'])){
                
                $ss=new \util\SwooleSocket($config['swoole_host'],$config['swoole_port']);
                $ss->startWs($config['server'], $config['swoole_php'],true);
                if($ss->websocketError()){
                    
                    $ss->startWs($config['server'], $config['swoole_php'],true);
                }
            }
        }
    }
    /*图片本地化设置*/
    public function download_imgAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            $config=array();
            
            $config['download_img']=input('download_img/d',0);
            $config['data_image']=input('data_image/d',0);
            $config['url_real']=input('url_real/d',0);
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
            
            $config['img_funcs']=input('img_funcs/a',array());
            
            $config['img_watermark']=input('img_watermark/d',0);
            $config['img_wm_logo']=input('img_wm_logo','');
            $config['img_wm_right']=input('img_wm_right/d',0);
            $config['img_wm_bottom']=input('img_wm_bottom/d',0);
            $config['img_wm_opacity']=input('img_wm_opacity/d',0);
            $config['img_wm_opacity']=min(100,max(0,$config['img_wm_opacity']));
            
            
            $config['more_suffix']=\skycaiji\admin\model\Config::process_suffix($config['more_suffix']);
            
            
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
            
            
            $upResult=$mconfig->upload_img_watermark_logo('img_wm_logo_upload');
            if(!$upResult['success']){
                $this->error($upResult['msg']);
            }elseif($upResult['file_name']){
                $config['img_wm_logo']=$upResult['file_name'];
            }
            
            if(is_array($config['img_funcs'])){
                $config['img_funcs']=array_values($config['img_funcs']);
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
            
            $imgConfig=$mconfig->compatible_func_config($imgConfig,false);
            
            
            $imgWmError='';
            $LocSystem=new \skycaiji\install\event\LocSystem();
            $LocSystem=$LocSystem->environmentPhp();
            if(empty($LocSystem['gd']['loaded'])){
                
                $imgWmError='php未启用gd模块';
            }
            
            $this->assign('imgWmError',$imgWmError);
            $this->assign('imgConfig',$imgConfig);
            return $this->fetch('download_img');
        }
    }
    /*文件本地化设置*/
    public function download_fileAction(){
        $mconfig=model('Config');
        if(request()->isPost()){
            $config=array();
            
            $config['download_file']=input('download_file/d',0);
            $config['url_real']=input('url_real/d',0);
            $config['file_interval']=input('file_interval/d',0);
            
            $config['file_timeout']=input('file_timeout/d',0);
            $config['retry']=input('retry/d',0);
            $config['wait']=input('wait/d',0);
            $config['file_path']=trim(input('file_path',''));
            $config['file_url']=input('file_url','','trim');
            $config['file_name']=input('file_name','');
            $config['name_custom_path']=input('name_custom_path','');
            $config['name_custom_name']=input('name_custom_name','');
            $config['charset']=input('charset','');
            $config['charset_custom']=input('charset_custom','');
            $config['file_max']=input('file_max/d',0);
            $config['file_funcs']=input('file_funcs/a',array());
            
            
            
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
            
            
            $checkNamePath=$mconfig->check_file_name_path($config['name_custom_path']);
            if($config['file_name']=='custom'){
                
                if(empty($config['name_custom_path'])){
                    $this->error('请输入文件名称自定义路径');
                }
                if(!$checkNamePath['success']){
                    $this->error($checkNamePath['msg']);
                }
            }else{
                
                if(!$checkNamePath['success']){
                    $config['name_custom_path']='';
                }
            }
            
            $checkNameName=$mconfig->check_file_name_name($config['name_custom_name']);
            if($config['file_name']=='custom'){
                
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
            if(is_array($config['file_funcs'])){
                $config['file_funcs']=array_values($config['file_funcs']);
            }
            
            $mconfig->setConfig('download_file',$config);
            
            $this->success(lang('op_success'),'setting/download_file');
        }else{
            $this->set_html_tags(
                '文件本地化设置',
                '文件本地化设置',
                breadcrumb(array(array('url'=>url('setting/caiji'),'title'=>lang('setting_caiji')),array('url'=>url('setting/download_file'),'title'=>'文件本地化')))
            );
            $fileConfig=$mconfig->getConfig('download_file','data');
            init_array($fileConfig);
            
            if(empty($fileConfig)){
                
                $fileConfig['file_timeout']=0;
                $fileConfig['file_max']=0;
                $fileConfig['file_interval']=0;
                $fileConfig['wait']=0;
                $fileConfig['retry']=0;
            }
            
            $fileConfig=$mconfig->compatible_func_config($fileConfig,true);
            
            $this->assign('fileConfig',$fileConfig);
            return $this->fetch('download_file');
        }
    }
    
    
    /*代理设置*/
    public function proxyAction(){
        $mconfig=model('Config');
        $mproxy=model('ProxyIp');
        if(request()->isPost()){
            $config=array();
            $config['open']=input('open/d',0);
            $config['failed']=input('failed/d',0);
            $config['group_id']=input('group_id/d',0);
            $config['use']=strtolower(input('use'));
            $config['use_num']=input('use_num/d',0);
            $config['use_time']=input('use_time/d',0);
            
            if('num'==$config['use']&&$config['use_num']<=0){
                $this->error('每个IP使用多少次必须大于0');
            }
            if('time'==$config['use']&&$config['use_time']<=0){
                $this->error('每个IP使用多少分钟必须大于0');
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
            $this->assign('proxyGroups',model('ProxyGroup')->getAll());
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
            
            $config['caiji']=input('caiji/a',array());
            foreach (array('open','is_auto','failed_num','failed_interval','report_interval') as $k){
                $config['caiji'][$k]=intval($config['caiji'][$k]);
            }
            
            
            foreach (array('email','smtp','port') as $k){
                if(empty($config[$k])){
                    $this->error('请输入'.lang('set_email_'.$k));
                }
            }
            if(!preg_match('/[\w\-]+\@[\w\-\.]+/i', $config['email'])){
                $this->error(lang('set_email_email').'格式错误');
            }
            
            if($is_test){
                
                $return=\util\Tools::send_mail($config, $config['email'], $config['sender'],lang('set_email_test_subject'),lang('set_email_test_body'));
                if($return===true){
                    $this->success(lang('set_email_test_body'),'');
                }else{
                    $this->error($return,'');
                }
            }else{
                if(!empty($config['caiji']['email'])&&!preg_match('/[\w\-]+\@[\w\-\.]+/i', $config['caiji']['email'])){
                    $this->error('收件邮箱格式错误');
                }
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
            $config['wait_end_ms']=input('wait_end_ms/d',0);
            $config['wait_end_num']=input('wait_end_num/d',0);
            if(!in_array($config['tool'],array('chrome'))){
                $config['tool']='';
            }
            
            if(!empty($config['tool'])){
                $this->ajax_check_userpwd();
            }
            
            $mconfig->setConfig('page_render',$config);
            
            $error=\util\ChromeSocket::config_start($config);
            if($error){
                $this->error($error);
            }else{
                $this->success(lang('op_success'),'setting/page_render');
            }
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
            return $this->fetch('page_render');
        }
    }
    /*清理缓存目录*/
    public function cleanAction(){
        $clearPageRender=model('Config')->page_render_is_chrome()?true:false;
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
                
                if(in_array('all', $types)||in_array('table', $types)){
                    
                    $tables=config('database.prefix').'cache';
                    $passTables=array($tables,$tables.'_backstage_task',$tables.'_collecting');
                    $tables=db()->query("show tables like '{$tables}%';");
                    if($tables&&is_array($tables)){
                        foreach ($tables as $table){
                            if($table&&is_array($table)){
                                foreach ($table as $v){
                                    if(!in_array($v, $passTables)){
                                        
                                        db()->table($v)->where('1=1')->delete();
                                    }
                                }
                            }
                        }
                    }
                }
                
                if($clearPageRender&&(in_array('all', $types)||in_array('page_render', $types))){
                    
                    \util\ChromeSocket::config_clear();
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
            $this->assign('clearPageRender',$clearPageRender);
            return $this->fetch();
        }
    }
    
    public function test_phpAction(){
        if(request()->isPost()){
            $this->ajax_check_userpwd();
            $phpFile=input('php','','trim');
            $phpResult=model('Config')->php_is_valid($phpFile);
            if($phpResult['success']){
                $this->success($phpResult['msg_ver'].($phpResult['msg_ver']?' ':'').'测试成功，请保存配置以便生效');
            }else{
                $this->error($phpResult['msg']?$phpResult['msg']:'测试失败');
            }
        }
        $this->error('测试失败');
    }
    
    public function test_swoole_phpAction(){
        if(request()->isPost()){
            $this->ajax_check_userpwd();
            $mconfig=model('Config');
            $phpFile=input('php','','trim');
            
            $ss=new \util\SwooleSocket(g_sc_c('caiji','swoole_host'),g_sc_c('caiji','swoole_port'));
            $phpResult=$ss->checkPhp('swoole',$phpFile,false,true);
            if(!$phpResult['success']){
                $this->error($phpResult['msg']?:'测试失败');
            }else{
                $this->success($phpResult['msg'].($phpResult['msg']?' ':'').'测试成功，请保存配置以便生效');
            }
        }
        $this->error('测试失败');
    }
    
    public function restart_swooleAction(){
        if(request()->isPost()){
            $this->ajax_check_userpwd();
            $this->_run_auto_backstage();
            $this->success('操作完成','setting/caiji');
        }
        $this->error('重启失败');
    }
    
    
    public function chrome_testAction(){
        if(request()->isPost()){
            $this->ajax_check_userpwd();
            $chrome=input('chrome/a',array());
            
            $return=\util\ChromeSocket::execHeadless($chrome['filename'], $chrome['port'], $chrome, true);
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
                    }else{
                        $this->success('测试成功，请保存配置以便生效'.($info['output']?('，'.$info['output']):''));
                    }
                }elseif($info['error']){
                    $this->error($info['error']);
                }else{
                    $this->success('测试成功，请保存配置以便生效');
                }
            }
        }
        $this->error('测试失败');
    }
    
    public function page_render_statusAction(){
        if(request()->isPost()){
            $config=model('Config')->getConfig('page_render','data');
            init_array($config);
            init_array($config['chrome']);
            $chromeSocket=\util\ChromeSocket::config_init($config);
            $toolIsOpen=$chromeSocket?$chromeSocket->hostIsOpen():false;
            $serverIsLocal=$chromeSocket?$chromeSocket->serverIsLocal():false;
            
            
            $tabs=$chromeSocket?$chromeSocket->getTabs():null;
            if($tabs){
                foreach ($tabs as $k=>$v){
                    if(empty($v['url'])||!is_array($v)||$v['url']=='about:blank'){
                        
                        unset($tabs[$k]);
                    }
                }
            }
            $tabs=$tabs?count($tabs):0;
            
            $this->assign('config',$config);
            $this->assign('toolIsOpen',$toolIsOpen);
            $this->assign('serverIsLocal',$serverIsLocal);
            $this->assign('tabs',$tabs);
            return $this->fetch('page_render_status');
        }
    }
    
    public function page_render_clearAction(){
        \util\ChromeSocket::config_clear();
        $this->success('清理完成','setting/page_render');
    }
    
    public function page_render_restartAction(){
        $error=\util\ChromeSocket::config_restart();
        if($error){
            $this->error($error,'');
        }else{
            $this->success('重启完成','setting/page_render');
        }
    }
    
    public function page_render_apiAction(){
        $kname='page_render_api_key';
        $mcache=CacheModel::getInstance();
        $data=$mcache->getCache($kname,'data');
        init_array($data);
        if(request()->isPost()){
            $mcache->setCache($kname,array(
                'open'=>input('open/d',0),
                'key'=>input('key','','trim'),
            ));
            $this->success('操作成功','',array('js'=>"windowModal('API接口',ulink('setting/page_render_api'),{lg:1});"));
        }else{
            $uri='';
            if($data['key']){
                $uri.='key='.md5($data['key']);
            }
            $urls=array(
                'clear'=>url('admin/api/page_render?'.$uri.($uri?'&':'').'op=clear','',false,true),
                'restart'=>url('admin/api/page_render?'.$uri.($uri?'&':'').'op=restart','',false,true),
                'list'=>url('admin/api/page_render?'.$uri.($uri?'&':'').'op=list','',false,true),
                'close'=>url('admin/api/page_render?'.$uri.($uri?'&':'').'op=close&id=','',false,true),
                'stop'=>url('admin/api/page_render?'.$uri.($uri?'&':'').'op=stop','',false,true),
            );
            $config=model('Config')->getConfig('page_render','data');
            $chromeSocket=\util\ChromeSocket::config_init($config);
            $serverIsLocal=$chromeSocket?$chromeSocket->serverIsLocal():false;
            
            $this->assign('data',$data);
            $this->assign('urls',$urls);
            $this->assign('serverIsLocal',$serverIsLocal);
            
            return $this->fetch('page_render_api');
        }
    }
}