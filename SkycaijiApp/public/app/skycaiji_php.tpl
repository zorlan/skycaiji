<?php
/*应用配置类文件*/
include dirname(__DIR__).'/app/skycaiji.php';
class {$app} extends skycaiji{
	/*应用配置*/
	public $config=array(
		'framework'=>'{$framework}',	//框架名称
		'framework_version'=>'{$framework_version}',	//框架版本
		'name'=>'{$name}',	//应用名称
		'desc'=>'{$desc}',	//描述
		'version'=>'{$version}',	//版本号
		'author'=>'{$author}',	//作者
		'website'=>'{$website}',	//站点
		'packs'=>{$packs},	//扩展
	);
	
	public $install='{$install}'; //安装接口
	public $uninstall='{$uninstall}'; //卸载接口
	public $upgrade='{$upgrade}'; //升级接口
}
