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

namespace skycaiji\admin\event;

abstract class Collector extends \skycaiji\admin\controller\BaseController {
	
	/*防止执行采集时出现错误模板后终止采集*/
	public function error($msg = '', $url = null, $data = array(), $wait = 3, array $header = []){
		if(is_collecting()){
			
			$this->echo_msg($msg,'red');
			return null;
		}else{
			parent::error($msg,$url,$data,$wait,$header);
		}
	}
	/*采集器的输出内容需要重写，只有正在采集时才输出内容*/
	public function echo_msg($str,$color='red',$echo=true,$end_str=''){
		if(is_collecting()){
			static $pause_session=null;
			if(!isset($pause_session)){
				
				if(session_status()!==2){
					session_start();
				}
				\think\Session::pause();

				$pause_session=true;
			}
			
			parent::echo_msg($str,$color,$echo,$end_str);
		}
	}
	/**
	 * 优化设置页面post过来的config
	 * @param unknown $config
	 */
	public abstract function setConfig($config);

	/**
	 * 初始化配置
	 * @param unknown $config
	 */
	public abstract function init($config);
	
	/**
	 * 采集数据
	 * @param unknown $num 采集条数
	 */
	public abstract function collect($num=10);
	
	/**
	 * 测试数据
	public abstract function test();
	 */
	
	/*设置抓取页面间隔*/
	public function set_html_interval(){
		if(is_collecting()){
			
			if($GLOBALS['_sc']['c']['caiji']['html_interval']>0){
				
				sleep($GLOBALS['_sc']['c']['caiji']['html_interval']);
				
				
				return true;
			}
		}
	}
	
	/*获取内容*/
	public function get_content($html){
		try {
			$cread=new \util\Readability($html,'utf-8');
			$data=$cread->getContent();
		}catch (\Exception $ex){
			return null;
		}
		
		
		
		
		
		
		return trim($data['content']);
	}
	/*获取标题*/
	public function get_title($html){
		
		if(preg_match_all('/<h1\b[^<>]*?>(?P<content>[\s\S]+?)<\/h1>/i', $html,$title)){
			if (count($title['content'])>1){
				
				$title=null;
			}else{
				$title=strip_tags(reset($title['content']));
				if (preg_match('/^((\&nbsp\;)|\s)*$/i', $title)){
					$title=null;
				}
			}
		}else{
			$title=null;
		}
		if (empty($title)){
			$pattern = array (
				'<(h[12])\b[^<>]*?(id|class)=[\'\"]{0,1}[^\'\"<>]*(title|article)[^<>]*>(?P<content>[\s\S]+?)<\/\1>',
				'<title>(?P<content>[\s\S]+?)([\-\_\|][\s\S]+?)*<\/title>'
			);
			$title=$this->return_preg_match($pattern, $html);
		}
		return trim(strip_tags($title));
	}
	public function get_keywords($html){
		$patterns=array(
			'<meta[^<>]*?name=[\'\"]keywords[\'\"][^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"]',
			'<meta[^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"][^<>]*?name=[\'\"]keywords[\'\"]'
		);
		$data=$this->return_preg_match($patterns, $html);
		return trim(strip_tags($data));
	}
	public function get_description($html){
		$patterns=array(
			'<meta[^<>]*?name=[\'\"]description[\'\"][^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"]',
			'<meta[^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"][^<>]*?name=[\'\"]description[\'\"]'
		);
		$data=$this->return_preg_match($patterns, $html);
		return trim(strip_tags($data));
	}
	/**
	 * 匹配规则的值
	 * @param 规则 $pattern
	 * @param 来源内容 $content
	 * @param 返回值得键名 $reg_key
	 */
	public function return_preg_match($pattern,$content,$reg_key='content'){
		if(is_array($pattern)){
			
			foreach ($pattern as $patt){
				if(preg_match('/'.$patt.'/i', $content,$cont)){
					$cont=$cont[$reg_key];
					break;
				}else{
					$cont=false;
				}
			}
		}else{
			if(preg_match('/'.$pattern.'/i', $content,$cont)){
				$cont=$cont[$reg_key];
			}else{
				$cont=false;
			}
		}
		return empty($cont)?'':$cont;
	}
	/**
	 * 匹配根目录
	 * @param string $url 完整的网址
	 * @param string $html 页面源码
	 * @return Ambigous <NULL, string>
	 */
	public function match_base_url($url,$html){
		
	    if(!empty($html)&&preg_match('/<base\b[^<>]*\bhref\s*=\s*[\'\"](?P<base>[^\'\"]*)[\'\"]/i',$html,$base_url)){
		    $base_url=$base_url['base'];
		    if(!preg_match('/^\w+\:\/\//', $base_url)){
		        
		        $urlBase=$this->match_base_url($url, null);
		        $urlDomain=$this->match_domain_url($url);
		        $base_url=$this->create_complete_url($base_url, $urlBase, $urlDomain);
		    }
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

		return $base_url?$base_url:null;
	}
	/**
	 * 匹配域名
	 * @param string $url 完整的网址
	 * @return NULL|string
	 */
	public function match_domain_url($url){
		
	    $domain_url=null;
		if(preg_match('/^\w+\:\/\/([\w\-]+\.){1,}[\w]+/',$url,$domain_url)){
			$domain_url=rtrim($domain_url[0],'/');
		}else{
		    $domain_url=null;
		}
		return empty($domain_url)?null:$domain_url;
	}
	/**
	 * 生成完整网址
	 * @param string $url 要填充的网址
	 * @param string $base_url 根目录网址
	 * @param string $domain_url 域名
	 */
	public function create_complete_url($url,$base_url,$domain_url){
	    static $base_domain=array();
	    
		if(preg_match('/^\w+\:\/\//', $url)){
			
			return $url;
		}elseif(strpos($url,'//')===0){
			
		    $url=(stripos($base_url, 'https://')===0?'https:':'http:').$url;
		}elseif(strpos($url,'/')===0){
			
		    $curDomain='';
		    if($base_url){
		        $baseMd5=md5($base_url);
		        if(!isset($base_domain[$baseMd5])){
		            $base_domain[$baseMd5]=$this->match_domain_url($base_url);
		        }
		        $curDomain=$base_domain[$baseMd5];
		    }
		    $curDomain=empty($curDomain)?rtrim($domain_url,'/'):$curDomain;
		    $url=$curDomain.'/'.ltrim($url,'/');
		}elseif(stripos($url,'javascript')===0||stripos($url,'#')===0||$url==''){
			
			$url='';
		}elseif(!preg_match('/^\w+\:\/\//', $url)){
			
			$url=$base_url.'/'.ltrim($url,'/');
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
}
?>