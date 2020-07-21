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

/*发布设置:本地cms*/
namespace skycaiji\admin\event;

class Rcms extends Release{
	protected $rele_cms_list=array();
	/**
	 * 设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
		$config['cms']=input('cms/a');
		$config['cms_app']=input('cms_app/a');
		if(empty($config['cms']['path'])){
			$this->error('cms路径不能为空');
		}
		if(empty($config['cms']['app'])){
			$this->error('cms插件不能为空');
		}
		if(empty($config['cms']['name'])){
			
			$config['cms']['name']=$this->cms_name($config['cms']['path']);
		}

		if(!model('ReleaseApp')->appFileExists($config['cms']['app'],'cms')){
			
			if(model('ReleaseApp')->oldFileExists($config['cms']['app'],'Cms')){
				
				$this->error(lang('release_upgrade'));
			}else{
				$this->error('抱歉，插件文件不存在');
			}
		}
		
		$releCms=model('ReleaseApp')->appImportClass($config['cms']['app'],'cms');
		$releCms->init($config['cms']['path']);
		$releCms->runCheck($config['cms_app']);
		
		return $config;
	}
	/*导出数据*/
	public function export($collFieldsList,$options=null){
		if(!model('ReleaseApp')->appFileExists($this->config['cms']['app'],'cms')){
			
			if(model('ReleaseApp')->oldFileExists($this->config['cms']['app'],'Cms')){
				
				$this->echo_msg(lang('release_upgrade'));
				exit();
			}else{
				$this->echo_msg('没有cms发布插件：'.$this->config['cms']['app']);
				exit();
			}
		}
		
		
		$releCms=md5($this->config['cms']['app'].'__cms__'.serialize($this->release));
		if(!isset($this->rele_cms_list[$releCms])){
			
			$this->rele_cms_list[$releCms]=model('ReleaseApp')->appImportClass($this->config['cms']['app'],'cms');
			$this->rele_cms_list[$releCms]->init(null,$this->release);
		}
		$releCms=$this->rele_cms_list[$releCms];
		$addedNum=0;
		
		foreach ($collFieldsList as $collFieldsKey=>$collFields){
		    $this->init_download_img($this->task,$collFields['fields']);
			$return=$releCms->runExport($collFields['fields']);
			if($return['id']>0){
				$addedNum++;
			}
			$this->record_collected($collFields['url'],$return,$this->release,$collFields['title']);
			
			unset($collFieldsList[$collFieldsKey]['fields']);
		}
		
		return $addedNum;
	}
	/*获取cms名字*/
	public function cms_name($cmsPath){
		list($cmsPath,$cmsPathName)=explode('@', $cmsPath);
		$cmsPath=realpath($cmsPath);
		if(empty($cmsPath)){
			return '';
		}
		static $cmsNames=array();
		$md5Path=md5($cmsPath);
		if(!isset($cmsNames[$md5Path])){
			$cmsName='';
			if(!empty($cmsPathName)){
				
				$cmsName=$cmsPathName;
			}else{
				
				$cmsFiles=$this->cms_files();
				foreach ($cmsFiles as $cms=>$cmsFile){
					if(is_array($cmsFile)){
						
						$hasCmsFile=true;
						foreach($cmsFile as $cmsFile1){
							$cmsFile1=realpath($cmsPath.'/'.$cmsFile1);
							if(empty($cmsFile1)||!file_exists($cmsFile1)){
								
								$hasCmsFile=false;
								break;
							}
						}
						if($hasCmsFile){
							
							$cmsName=$cms;
							break;
						}
					}else{
						
						$cmsFile=realpath($cmsPath.'/'.$cmsFile);
						if(!empty($cmsFile)&&file_exists($cmsFile)){
							
							$cmsName=$cms;
							break;
						}
					}
				}
			}
			$cmsNames[$md5Path]=$cmsName;
		}
		return $cmsNames[$md5Path];
	}
	/*获取cms名字列表*/
	public function cms_name_list($cmsPath,$return=false){
		$cmsPath=realpath($cmsPath);
		
		static $list=array();
		if($return){
			
			foreach ($list as $cms=>$files){
				$files=array_unique($files);
				$files=array_filter($files);
				$files=array_values($files);
				$list[$cms]=$files;
			}
			return empty($list)?array():$list;
		}
		if(!empty($cmsPath)){
			$cmsName=$this->cms_name($cmsPath);
			if(!empty($cmsName)){
				$list[$cmsName][]=$cmsPath;
			}
		}
	}
	/*cms文件*/
	public function cms_files(){
		static $files=array (
			'discuz'=>'source/class/discuz/discuz_core.php',
			'wordpress'=>'wp-includes/wp-db.php',
			'dedecms'=>'include/dedetemplate.class.php',
			'empirecms'=>'e/class/EmpireCMS_version.php',
			'phpcms'=>'phpcms/base.php',
			'destoon'=>'api/oauth/destoon.inc.php',
			'ecshop'=>'includes/cls_ecshop.php',
			'shopex'=>'plugins/app/shopex_stat/shopex_stat_modifiers.php',
			'espcms'=>'adminsoft/include/inc_replace_mailtemplates.php',
			'metinfo'=>'config/metinfo.inc.php',
			'twcms'=>'twcms/config/config.inc.php',
			'zblog'=>'zb_system/function/lib/zblogphp.php',
			'phpwind'=>'actions/pweditor/modifyattach.php',
			'xiunobbs'=>'xiunophp/xiunophp.php',
			'skyuc'=>'includes/modules/integrates/skyuc.php',
			'jieqicms'=>'themes/jieqidiv/theme.html',
			'hadsky'=>'app/hadskycloudserver/index.php',
			'mipcms'=>'app/article/Mipcms.php',
			'maccms'=>'application/extra/maccms.php',
			'typecho'=>'var/Typecho/Widget.php',
			'emlog'=>array('include/controller/log_controller.php','content/cache/logalias.php'),
			'drupal'=>'modules/simpletest/drupal_web_test_case.php',
			'hybbs'=>'Action/HYBBS.php',
			'sdcms'=>'app/sdcms.php',
			'feifei'=>'Runtime/Data/_fields/feifeicms.ff_tag.php',
			'catfish'=>'application/catfishajax/controller/Index.php',
			'pboot'=>'data/pbootcms.db',
			'yzmcms'=>'yzmphp/yzmphp.php',
			'chanzhi'=>'js/chanzhi.all.js',
		);
		return $files;
	}
}
?>