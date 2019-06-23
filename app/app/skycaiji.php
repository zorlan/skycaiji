<?php
/*应用程序核心类*/
abstract class skycaiji{
	private static $appInstance=null;//应用实例
	public $app='';	//当前应用app标识
	public $appUrl='';	//当前应用的根网址
	public $appPath='';	//当前应用的根目录
	public $config=array();	//当前应用配置
	public $system=array(); //蓝天采集器配置
	public $systemUrl='';	//蓝天采集器根网址
	public $systemPath='';	//蓝天采集器根目录

	public $install='';	//安装链接
	public $uninstall='';	//卸载链接
	public $upgrade='';	//升级链接
	
	public function __construct(){
		$ds=DIRECTORY_SEPARATOR;
		$this->app=get_class($this);
		$this->systemPath=dirname(dirname(__DIR__));
		$this->appPath=$this->systemPath.$ds.'app'.$ds.$this->app;
		$this->appUrl=$this->root();
		$this->systemUrl=preg_replace('/[\/\\\]app[\/\\\]'.$this->app.'[\/\\\]*$/i', '', $this->appUrl);
		
		$this->install=ltrim($this->install,'/');
		$this->uninstall=ltrim($this->uninstall,'/');
		$this->upgrade=ltrim($this->upgrade,'/');
	}
	
	/*当前应用的实例*/
	public static function app(){
		if(!isset(self::$appInstance)){
			$appInstance=new static;//实例化
			//跳过检测的链接
			$passUrls=array('install'=>$appInstance->install,'uninstall'=>$appInstance->uninstall,'upgrade'=>$appInstance->upgrade);
			foreach ($passUrls as $k=>$v){
				if(empty($v)||$v=='1'){
					//1表示跳过操作
					unset($passUrls[$k]);
				}else{
					$v=$appInstance->appUrl.'/'.$v;
					$passUrls[$k]=strtolower($v);//必须小写
				}
			}
			$curUrl=$appInstance->url();//当前网址
			$curUrl=strtolower($curUrl);//必须小写
			
			$config=array();//已安装配置
			if(!in_array($curUrl,$passUrls)){
				//应用必须安装后才能操作
				$config=__DIR__.'/config/'.$appInstance->app.'.php';//已安装的配置文件
				if(!file_exists($config)){
					exit('未安装应用');
				}
				$config=include $config;
				$config=is_array($config)?$config:array();
				if(empty($config['enable'])){
					exit('未开启应用');
				}
			}
			//应用配置
			$appInstance->config=is_array($appInstance->config)?$appInstance->config:array();
			$appInstance->config=array_merge($appInstance->config,$config);//配置合并
			
			//蓝天采集系统配置
			$systemConfig=$appInstance->systemPath.'/data/config.php';
			if(file_exists($systemConfig)){
				$systemConfig=include $systemConfig;
			}
			$appInstance->system=is_array($systemConfig)?$systemConfig:array();
			
			self::$appInstance=$appInstance;
		}
		return self::$appInstance;
	}
	/**
	 * 运行框架
	 * 如果应用使用了其他框架，请在应用文件中重写run方法加载框架
	 */
	public function run(){
		if(!empty($this->config['framework'])){
			//使用框架

			if(empty($this->config['framework_path'])){
				$frameworkPath=$this->appFrameworkPath();
				if(is_dir($frameworkPath)){
					$this->config['framework_path']=$frameworkPath;
				}
			}
			if(empty($this->config['framework_path'])){
				exit('框架路径错误');
			}
			
			$frameworkPath=$this->config['framework_path'];
			
			if('thinkphp'==$this->config['framework']){
				$version='';
				if(file_exists($frameworkPath.'/base.php')&&preg_match('/\bdefine\s*\([\'\"]THINK_VERSION[\'\"],\s*[\'\"](.*?)[\'\"]\);/i', file_get_contents($frameworkPath.'/base.php'),$version)){
					$version=$version[1];
				}elseif(file_exists($frameworkPath.'/library/think/App.php')&&preg_match('/\bconst\s+VERSION\s*=\s*[\'\"](.*?)[\'\"]/i', file_get_contents($frameworkPath.'/library/think/App.php'),$version)){
					$version=$version[1];
				}elseif(file_exists($frameworkPath.'/src/think/App.php')&&preg_match('/\bconst\s+VERSION\s*=\s*[\'\"](.*?)[\'\"]/i', file_get_contents($frameworkPath.'/src/think/App.php'),$version)){
					$version=$version[1];
				}
				
				if(preg_match('/^5\.0\./', $version)){
					//5.0
					define('APP_PATH', $this->appPath . '/application/');
					require $frameworkPath . '/base.php';
					\think\App::run()->send();
				}elseif(preg_match('/^5\.1\./', $version)){
					//5.1
					define('APP_PATH', $this->appPath . '/application/');
					require $frameworkPath . '/base.php';
    				\think\Container::get('app')->path(APP_PATH)->run()->send();
				}elseif(preg_match('/^6\.0\./', $version)){
					//6.0
					require $this->appPath . '/vendor/autoload.php';
					$http = (new \think\App())->http;
					$response = $http->run();
					$response->send();
					$http->end($response);
				}
			}elseif('laravel'==$this->config['framework']){
				$version='';
		    	$frameworkPath.='/src/Illuminate';
		    	if(preg_match('/\bconst\s+VERSION\s*=\s*[\'\"](.*?)[\'\"]/i', file_get_contents($frameworkPath.'/Foundation/Application.php'),$version)){
		    		$version=$version[1];
		    	}else{
		    		$version='';
		    	}
		    	if(preg_match('/^5\.1\./', $version)){
		    		//5.1.x
		    		require $this->appPath.'/bootstrap/autoload.php';
					$app = require_once $this->appPath.'/bootstrap/app.php';
					$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
					$response = $kernel->handle(
					    $request = \Illuminate\Http\Request::capture()
					);
					$response->send();
					$kernel->terminate($request, $response);
		    	}elseif(preg_match('/^5\.5\./', $version)){
		    		//5.5.x
					define('LARAVEL_START', microtime(true));
					require $this->appPath.'/vendor/autoload.php';
					$app = require_once $this->appPath.'/bootstrap/app.php';
					$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
					$response = $kernel->handle(
						$request = \Illuminate\Http\Request::capture()
					);
					$response->send();
					$kernel->terminate($request, $response);
		    	}
			}
		}
	}
	/**
	 * 获取内置的框架路径
	 */
    public function appFrameworkPath(){
    	$frameworkPath='';
    	if(empty($this->config['framework_path'])){
    		//检测框架是否存在
    		switch ($this->config['framework']){
    			case 'thinkphp':
    				$frameworkPath=$this->appPath.'/vendor/topthink/framework';
    				if(!file_exists($frameworkPath)){
    					//不存在目录
    					$frameworkPath=$this->appPath.'/thinkphp';//使用旧形式的框架目录
    				}
    				break;
    			case 'laravel':$frameworkPath=$this->appPath.'/vendor/laravel/framework';break;
    		}
    	}
    	return $frameworkPath;
    }
    /**
     * 获取当前执行的文件
     */
    public function baseFile(){
    	$url='';
    	$script_name = basename($_SERVER['SCRIPT_FILENAME']);
    	if (basename($_SERVER['SCRIPT_NAME']) === $script_name) {
    		$url = $_SERVER['SCRIPT_NAME'];
    	} elseif (basename($_SERVER['PHP_SELF']) === $script_name) {
    		$url = $_SERVER['PHP_SELF'];
    	} elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name) {
    		$url = $_SERVER['ORIG_SCRIPT_NAME'];
    	} elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $script_name)) !== false) {
    		$url = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $script_name;
    	} elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0) {
    		$url = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
    	}
    	return $url;
    }
    /**
     * 获取当前完整URL
     */
    public function url(){
    	$url='';
    	if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
    		$url = $_SERVER['HTTP_X_REWRITE_URL'];
    	} elseif (isset($_SERVER['REQUEST_URI'])) {
    		$url = $_SERVER['REQUEST_URI'];
    	} elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
    		$url = $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    	} else {
    		$url = '';
    	}
    	return $url;
    }
    /**
     * 获取应用根目录
     * @return string
     */
    public function root(){
    	$file = $this->baseFile();
    	if ($file && 0 !== strpos($this->url(), $file)) {
    		$file = str_replace('\\', '/', dirname($file));
    	}
    	return rtrim($file, '/');
    }
    /**
     * 判断蓝天采集器是否管理员登录
     * @param string $jump 是否跳转至登录界面
     * @return boolean
     */
    public function isAdmin($jump=false){
    	if(session_status()!==2){
    		session_start();
    	}
    	if(isset($_SESSION['skycaiji'])&&!empty($_SESSION['skycaiji']['is_admin'])){
    		return true;
    	}else{
    		if($jump){
    			//跳转至登录页面
    			$url=$this->url();
    			$url=$this->systemUrl.'/index.php?s=admin&_referer='.rawurlencode($url);
    		
    			$html='<meta http-equiv="refresh" content="1;url='.$url.'">请登录管理员账号';
    			exit($html);
    		}else{
    			return false;
    		}
    	}
    }
    /**
     * 输出json格式状态信息
     * @param init $status 状态 1:成功 ,0:失败
     * @param string $info 提示内容
     * @param string $url 跳转网址
     */
    public function status($status=0,$info='',$url=''){
    	$data=array('status'=>$status,'info'=>$info,'url'=>$url);
    	$data=json_encode($data);
    	header('content-type:application/json;charset=utf-8');
    	exit($data);
    }
}

/**
 * 获取当前app实例化对象
 * 所有skycaiji类及子类中的方法都应该通过该函数来调用
 */
if(!defined('skycaiji_app')){
	function skycaiji_app(){
		return \skycaiji::app();
	}
}
