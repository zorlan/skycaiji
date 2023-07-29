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
 

namespace util;
class Tools{
    
    public static function url_is_compatible($url){
        static $urlConvert=null;
        if(defined('URL_IS_COMPATIBLE')&&$url){
            
            if(!isset($urlConvert)){
                
                config('url_convert',false);
                $urlConvert=1;
            }
            if(false === strpos($url, '://')){
                
                $url=str_replace('?', '&', $url);
            }
        }
        return $url;
    }
    
    public static function set_url_compatible(){
        \think\Url::root(config('root_url').'/index.php?s=');
        define('URL_IS_COMPATIBLE', true);
    }
    
    public static function load_data_config(){
        static $loaded=false;
        if(!$loaded){
            if(file_exists(config('root_path').'/data/config.php')){
                
                $dataConfig=include config('root_path').'/data/config.php';
                
                $dbConfig=array();
                foreach ($dataConfig as $k=>$v){
                    if(strpos($k, 'DB_')!==false){
                        
                        $dbConfig[$k]=$v;
                        unset($dataConfig[$k]);
                    }
                }
                
                
                $dbConfig=array(
                    'type'=>$dbConfig['DB_TYPE'],
                    'hostname'=>$dbConfig['DB_HOST'],
                    'hostport'=>$dbConfig['DB_PORT'],
                    'database'=>$dbConfig['DB_NAME'],
                    'password'=>$dbConfig['DB_PWD'],
                    'username'=>$dbConfig['DB_USER'],
                    'prefix'=>$dbConfig['DB_PREFIX'],
                );
                
                if(!empty($dbConfig)&&is_array($dbConfig)){
                    $dbConfig=array_merge(config('database'),$dbConfig);
                    config('database',$dbConfig);
                    config($dataConfig);
                    $loaded=true;
                }
            }
        }
    }
    
    public static function check_verify($verifycode){
        if(empty($verifycode)){
            return return_result(lang('verifycode_error'),false,array('name'=>'verifycode'));
        }
        $verify = new \think\captcha\Captcha(array('reset'=>false));
        if(!$verify->check($verifycode)){
            return return_result(lang('verifycode_error'),false,array('name'=>'verifycode'));
        }
        return return_result('',true);
    }
    
    public static function clear_runtime_dir($passFiles=null){
        \util\Funcs::clear_dir(config('runtime_path'),$passFiles);
        write_dir_file(config('runtime_path').'/index.html', '');
    }
    
    public static function program_filemd5_list($path,&$md5FileList){
        static $passPaths=array();
        if(empty($passPaths)){
            
            $passPaths['data']=realpath(config('root_path').'/data');
            $passPaths['runtime']=realpath(config('runtime_path'));
            $passPaths=array_filter($passPaths);
        }
        $fileList=scandir($path);
        foreach( $fileList as $file ){
            $isPass=false;
            $fileName=realpath($path.'/'.$file);
            foreach ($passPaths as $passPath){
                
                if($fileName==$passPath||stripos($fileName,$passPath)>0){
                    $isPass=true;
                }
            }
            if($isPass){
                continue;
            }
            
            if(is_dir( $fileName ) && '.' != $file && '..' != $file ){
                self::program_filemd5_list( $fileName,$md5FileList );
            }elseif(is_file($fileName)){
                $root=realpath(config('root_path'));
                $curFile=str_replace('\\', '/',str_replace($root, '', $fileName));
                
                $md5FileList[]=array('md5'=>md5_file($fileName),'file'=>$curFile);
            }
        }
    }
    /**
     * 发送邮件
     * @param array $emailConfig
     * @param string $to
     * @param string $name
     * @param string $subject
     * @param string $body
     * @param string $attachment
     * @return boolean
     */
    public static function send_mail($emailConfig,$to, $name, $subject = '', $body = '', $attachment = null){
        set_time_limit(60);
        
        $mail = new \PHPMailer();
        
        
        $mail->isSMTP();
        $mail->Host = $emailConfig['smtp'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['email'];
        $mail->Password = $emailConfig['pwd'];
        $mail->SMTPSecure = empty($emailConfig['type'])?'tls':$emailConfig['type'];
        $mail->Port = $emailConfig['port'];
        
        $mail->setFrom($emailConfig['email'], $emailConfig['sender']);
        $mail->addAddress($to, $name);
        
        $mail->isHTML(true);
        
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = '';
        
        if(is_array($attachment)){ 
            foreach ($attachment as $file){
                is_file($file) && $mail->AddAttachment($file);
            }
        }
        return $mail->Send() ? true : $mail->ErrorInfo;
    }
    
    public static function cp_page_tpl_vars($pageType){
        $vars = array('title' => '页面');
        if ('front_url' == $pageType) {
            $vars['title'] = '前置页';
            $vars['id'] = 'c_p_front_url';
            $vars['name'] = 'front_url';
        } elseif ('source_url' == $pageType) {
            $vars['title'] = '起始页';
            $vars['id'] = 'coll_pattern_source_url';
            $vars['name'] = 'config[source_config]';
        } elseif ('level_url' == $pageType) {
            $vars['title'] = '多级页';
            $vars['id'] = 'c_p_level_url';
            $vars['name'] = 'level_url';
        } elseif ('relation_url' == $pageType) {
            $vars['title'] = '关联页';
            $vars['id'] = 'c_p_relation_url';
            $vars['name'] = 'relation_url';
        } elseif ('url' == $pageType) {
            $vars['title'] = '内容页';
            $vars['id'] = 'coll_pattern_url';
            $vars['name'] = 'config';
        } elseif ('test'==$pageType) {
            $vars['title'] = '';
            $vars['id'] = 'win_coll_pattern_test';
            $vars['name'] = 'config';
        }
        return $vars;
    }
    
    public static function cli_command_exec($paramStr){
        
        if(config('cli_cache_config')){
            $cacheConfig=\skycaiji\admin\model\CacheModel::getInstance()->getCache('cli_cache_config','data');
            $cliConfig=array();
            foreach (config('cli_cache_config') as $key){
                $cliConfig[$key]=config($key);
            }
            if(serialize($cacheConfig)!=serialize($cliConfig)){
                
                \skycaiji\admin\model\CacheModel::getInstance()->setCache('cli_cache_config',$cliConfig);
            }
        }
        
        $commandStr=g_sc_c('caiji','server_php');
        if(empty($commandStr)){
            
            $commandStr=\skycaiji\admin\model\Config::detect_php_exe();
        }
        if(!empty($commandStr)){
            $commandStr=\skycaiji\admin\model\Config::cli_safe_filename($commandStr);
            
            $cliUser=intval(g_sc('user','uid')).'_'.model('User')->generate_key(g_sc('user'));
            
            $paramStr.=' --cli_user '.base64_encode($cliUser);
            
            $commandStr.=' '.config('root_path').DIRECTORY_SEPARATOR.'skycaiji '.$paramStr;
            
            \util\Funcs::close_session();
            
            
            self::proc_open_exec($commandStr);
        }
        
        exit();
    }
    
    public static function proc_open_exec_curl($commandStr,$showInfo=false,$timeout=10,$closeProc=false,$killProc=false){
        $params=array($commandStr,$showInfo,$timeout,$closeProc,$killProc);
        cache('proc_open_exec_params',$params);
        $json=get_html(url('admin/index/proc_open_exec?key='.\util\Param::set_proc_open_exec_key(),null,false,true),null,array('timeout'=>3));
        if($json){
            $json=json_decode($json,true);
        }
        init_array($json);
        return $json;
    }
    
    public static function proc_open_exec($commandStr,$showInfo=false,$timeout=10,$closeProc=false,$killProc=false){
        $info=array('status'=>'','output'=>'','error'=>'');
        $descriptorspec=array();
        if($showInfo===true){
            
            $descriptorspec[0]=array('pipe', 'r');
        }
        $showInfo=$showInfo==='all'?array('status','output','error'):explode(',',$showInfo);
        $timeout=intval($timeout);
        if($timeout<=0){
            $timeout=10;
        }
        if(!empty($commandStr)){
            $showOutput=in_array('output',$showInfo)?true:false;
            $showError=in_array('error',$showInfo)?true:false;
            if($showOutput||$showError){
                $descriptorspec[0]=array('pipe', 'r');
            }
            if($showOutput){
                $descriptorspec[1]=array('pipe', 'w');
            }
            if($showError){
                $descriptorspec[2]=array('pipe', 'w');
            }
            $pipes=array();
            $otherOptions=IS_WIN?array('suppress_errors'=>true,'bypass_shell'=>true):array();
            $handle=proc_open($commandStr,$descriptorspec,$pipes,null,null,$otherOptions);
            if(!empty($showInfo)){
                
                if(!is_resource($handle)){
                    
                    $info['error']='命令执行失败，请检查可执行文件是否存在，以及'.\util\Funcs::web_server_name().'服务器的用户权限';
                }else{
                    $nowtime=time();
                    if(in_array('status',$showInfo)){
                        
                        $info['status']=proc_get_status($handle);
                    }
                    if($showOutput&&is_resource($pipes[1])){
                        
                        if(function_exists('stream_set_blocking')){
                            stream_set_blocking($pipes[1],false);
                        }
                        if(function_exists('stream_set_timeout')){
                            stream_set_timeout($pipes[1],$timeout);
                        }
                        while(is_resource($pipes[1])&&!feof($pipes[1])){
                            $info['output'].=fgets($pipes[1]);
                            if((time()-$nowtime)>$timeout){
                                
                                break;
                            }
                        }
                    }
                    $nowtime=time();
                    if($showError&&is_resource($pipes[2])){
                        
                        if(function_exists('stream_set_blocking')){
                            stream_set_blocking($pipes[2],false);
                        }
                        if(function_exists('stream_set_timeout')){
                            stream_set_timeout($pipes[2],$timeout);
                        }
                        while(is_resource($pipes[2])&&!feof($pipes[2])){
                            $info['error'].=fgets($pipes[2]);
                            if((time()-$nowtime)>$timeout){
                                
                                break;
                            }
                        }
                    }
                    
                    foreach (array('output','error') as $key){
                        
                        if(!empty($info[$key])){
                            $encode=mb_detect_encoding($info[$key], array('ASCII','UTF-8','GB2312','GBK','BIG5'));
                            $info[$key]=\util\Funcs::convert_charset($info[$key],$encode,'utf-8');
                        }
                    }
                }
            }
            if(empty($descriptorspec)){
                proc_close($handle);
            }else{
                if(is_resource($pipes[0])){
                    fclose($pipes[0]);
                }
                if(is_resource($pipes[1])){
                    fclose($pipes[1]);
                }
                if(is_resource($pipes[2])){
                    fclose($pipes[2]);
                }
                if($closeProc&&is_resource($handle)){
                    proc_terminate($handle);
                    proc_close($handle);
                }
                if($killProc){
                    
                    
                    register_shutdown_function(function(){
                        try{
                            $curPid=getmypid();
                            $curPid=intval($curPid);
                            if($curPid>0){
                                
                                if(function_exists('posix_kill')){
                                    posix_kill($curPid, SIGTERM);
                                }else{
                                    $cmdPid='';
                                    if(IS_WIN){
                                        
                                        $cmdPid='taskkill /F /PID '.$curPid;
                                    }else{
                                        
                                        $cmdPid='kill -15 '.$curPid;
                                    }
                                    if($cmdPid){
                                        \util\Tools::proc_open_exec($cmdPid);
                                    }
                                }
                            }
                        }catch (\Exception $ex){}
                    });
                }
            }
        }
        return $info;
    }
    
    public static function curl_skycaiji($uri,$headers=null,$options=array(),$postData=null,$returnInfo=false){
        $url='://www.skycaiji.com'.$uri;
        $info=get_html('https'.$url,$headers,$options,'utf-8',$postData,true);
        $info=is_array($info)?$info:array();
        if(empty($info['ok'])){
            
            $info=get_html('http'.$url,$headers,$options,'utf-8',$postData,true);
            $info=is_array($info)?$info:array();
        }
        return $returnInfo?$info:$info['html'];
    }
    
    public static function curl_store($providerUrl,$uri,$headers=null,$options=array(),$postData=null){
        $html=null;
        if(empty($providerUrl)){
            
            $html=self::curl_skycaiji($uri,$headers,$options,$postData);
        }else{
            $html=get_html($providerUrl.$uri,$headers,$options,'utf-8',$postData);
        }
        return $html;
    }
    
    
    public static function install_downloaded_zip($fileData,$cachePath,$toPathName){
        $result=return_result('',false,array('blocks'=>0,'exist_blocks'=>0,'exist_size'=>0,'next_block_no'=>0));
        
        $fileData=is_array($fileData)?$fileData:array();
        
        $blocks=intval($fileData['blocks']);
        $blockNo=intval($fileData['block_no']);
        if($blocks<=0){
            $result['msg']='文件不存在';
            return $result;
        }
        
        $cachePath.='/'.md5($fileData['md5'].'_'.$fileData['size'].'_'.$fileData['blocks']).'/';
        
        $result['blocks']=$blocks;
        
        
        for($i=1;$i<$blockNo;$i++){
            if(file_exists($cachePath.$i)){
                $result['exist_blocks']+=1;
                $result['exist_size']+=filesize($cachePath.$i);
            }
        }
        
        if($blockNo<1){
            
            for($i=1;$i<=$blocks;$i++){
                if(!file_exists($cachePath.$i)){
                    $result['next_block_no']=$i;
                    break;
                }
            }
            $result['success']=true;
            return $result;
        }else{
            
            if(empty($fileData['block'])){
                $result['msg']='文件数据为空';
                return $result;
            }
            
            $fileData['block']=base64_decode($fileData['block']);
            
            write_dir_file($cachePath.$blockNo,$fileData['block']);
            
            
            $result['exist_blocks']+=1;
            $result['exist_size']+=filesize($cachePath.$blockNo);
            
            if($blockNo<$blocks){
                
                for($i=$blockNo+1;$i<=$blocks;$i++){
                    if(!file_exists($cachePath.$i)){
                        $result['next_block_no']=$i;
                        break;
                    }
                }
                $result['success']=true;
                return $result;
            }else{
                
                $downloaded=true;
                for($i=1;$i<=$blocks;$i++){
                    if(!file_exists($cachePath.$i)){
                        
                        $downloaded=false;
                        break;
                    }
                }
                if(!$downloaded){
                    $result['msg']='文件不完整，请重试';
                    return $result;
                }else{
                    
                    $downloadedData='';
                    for($i=1;$i<=$blocks;$i++){
                        $downloadedData.=file_get_contents($cachePath.$i);
                    }
                    write_dir_file($cachePath.'archive.zip',$downloadedData);
                    unset($downloadedData);
                    
                    $error='';
                    
                    if(!class_exists('ZipArchive')){
                        $error='您的服务器不支持ZipArchive解压';
                    }else{
                        try {
                            $zipClass=new \ZipArchive();
                            if($zipClass->open($cachePath.'archive.zip')===TRUE){
                                $zipClass->extractTo($toPathName);
                                $zipClass->close();
                            }else{
                                $error='文件解压失败';
                            }
                        }catch(\Exception $ex){
                            $error='文件解压失败：'.$ex->getMessage();
                        }
                    }
                    
                    if($error){
                        $result['msg']=$error;
                        return $result;
                    }else{
                        \util\Funcs::clear_dir($cachePath);
                    }
                }
            }
        }
        $result['success']=true;
        return $result;
    }
    
    
    /**
     * 匹配根目录
     * @param string $url 完整的网址
     * @param string $html 页面源码
     */
    public static function match_base_url($url,$html,$returnInfo=false){
        
        $base_tag_url=false;
        if(!empty($html)&&preg_match('/<base\b[^<>]*\bhref\s*=\s*[\'\"](?P<base>[^\'\"]*)[\'\"]/i',$html,$base_url)){
            $base_url=$base_url['base'];
            if(!preg_match('/^\w+\:\/\//', $base_url)){
                
                $url_info=array('cur_url'=>$url,'url_no_name'=>true);
                $url_info['base_url']=self::match_base_url($url, null);
                $url_info['domain_url']=self::match_domain_url($url);
                $base_url=self::create_complete_url($base_url,$url_info);
            }
            $base_tag_url=$base_url;
        }else{
            
            $base_url=preg_replace('/[\#\?].*$/', '', $url);
        }
        if(!preg_match('/\/$/', $base_url)){
            
            
            if(preg_match('/(^\w+\:\/\/[^\/]+)(.*$)/',$base_url,$mbase)){
                
                $mbase[2]=preg_replace('/[^\/]+$/', '', $mbase[2]);
                $base_url=$mbase[1].$mbase[2];
            }
        }
        $base_url=rtrim($base_url,'/');
        $base_url=$base_url?$base_url:null;
        if($returnInfo){
            return array('base_url'=>$base_url,'base_tag_url'=>$base_tag_url);
        }else{
            return $base_url;
        }
    }
    /**
     * 匹配域名
     * @param string $url 完整的网址
     * @return NULL|string
     */
    public static function match_domain_url($url,$cache=false){
        static $domainList=array();
        $domain_url=null;
        if($cache){
            $cache=md5($url);
            $domain_url=$domainList[$cache];
        }
        if(empty($domain_url)){
            if(preg_match('/^\w+\:\/\/([\w\-]+\.){1,}[\w]+/',$url,$domain_url)){
                $domain_url=rtrim($domain_url[0],'/');
            }else{
                $domain_url=null;
            }
            if($cache){
                $domainList[$cache]=$domain_url;
            }
        }
        return empty($domain_url)?null:$domain_url;
    }
    
    
    /**
     * 生成完整网址
     * @param string $url 要填充的网址
     * @param array $params 参数 
     */
    public static function create_complete_url($url,$params=array()){
        
        $base_url=$params['base_url'];
        
        if(preg_match('/^\w+\:/', $url)){
            
            if(preg_match('/^\w+\:\/\//', $url)){
                
                if($params['url_no_name']){
                    
                    if(strpos($url,'#')!==false){
                        $url=preg_replace('/\#.*/', '', $url);
                    }
                }
            }
            return $url;
        }else{
            
            if(strpos($url,'//')===0){
                
                $url=(stripos($base_url, 'https://')===0?'https:':'http:').$url;
            }elseif(strpos($url,'/')===0){
                
                $curDomain=self::match_domain_url($base_url,true);
                $curDomain=empty($curDomain)?rtrim($params['domain_url'],'/'):$curDomain;
                $url=$curDomain.'/'.ltrim($url,'/');
            }elseif(stripos($url,'javascript')===0||$url==''||strpos($url,'#')===0){
                
                $url=($params['base_tag_url']?$params['base_tag_url']:$params['cur_url']).(strpos($url,'#')===0?($params['url_no_name']?'':$url):'');
            }elseif(!preg_match('/^\w+\:\/\//', $url)){
                
                $url=$base_url.'/'.ltrim($url,'/');
            }
        }
        if($params['url_no_name']){
            
            if(strpos($url,'#')!==false){
                $url=preg_replace('/\#.*/', '', $url);
            }
        }
        if(!empty($url)&&preg_match('/\/(\.){1,2}\//', $url)){
            
            if(preg_match('/(^\w+\:\/\/(?:[\w\-]+\.){1,}[\w]+\/)([^\?\#]+)(.*$)/',$url,$murl)){
                
                $paths=explode('/', $murl[2]);
                $newPaths=array();
                foreach ($paths as $k=>$v){
                    if($v=='..'){
                        
                        array_pop($newPaths);
                    }elseif($v!='.'){
                        
                        $newPaths[]=$v;
                    }
                }
                $url=$murl[1].implode('/', $newPaths).$murl[3];
            }
        }
        return $url;
    }
    /**
     * 采集输出信息
     * @param mixed $strArgs
     * @param string $color
     */
    public static function collect_output($strArgs,$color='red',$exit=true){
        static $class=null;
        if(!isset($class)){
            $class=controller('admin/CollectController');
        }
        if($exit){
            $class->echo_msg_exit($strArgs,$color);
        }else{
            $class->echo_msg($strArgs,$color);
        }
    }
    
    public static function cp_rule_module_name($name,$namePre,$nameKey){
        if($name=='data-process'){
            return 'data-process="'.$namePre.$nameKey.'"';
        }else{
            return 'name="'.$name.'['.$namePre.$nameKey.']"';
        }
    }
}
?>