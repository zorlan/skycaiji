<?php
/* diy发布设置
 */
namespace Release\Diy;
use Think\Controller;
use Think\Hook;
use Admin\Event\ReleaseBaseEvent;
abstract class BaseDiy extends ReleaseBaseEvent{
	public $release;//发布对象数据
	public $releConfig;//发布配置
	public $connection;//数据库配置
	public $db;//数据库对象
	public function __construct(){
		parent::__construct();
		C('TMPL_EXCEPTION_FILE',APP_PATH.'Public/release_exception.tpl');//定义cms错误模板，ajax出错时方便显示
	}
	public function init($release=null){
		if(empty($release)){
			$release=array();
		}
		if(!empty($release)){
			//通过发布设置加载配置
			$releConfig=$release['config'];
			$this->releConfig=$releConfig;//发布数据库配置
			$this->release=$release;
		}else{
			E('发布错误：配置加载失败！');
		}
		$this->init_load();
		if(empty($this->connection)){
			E('发布错误：没有数据库配置');
		}
		//实例化数据库
		try {
			$this->db=M('',null,$this->connection);
		}catch (\Exception $ex){
			E('发布错误：'.$ex->getMessage());
		}
		$this->init_extend();
	}
	/*初始化载入*/
	public function init_load(){}
	public function init_extend(){}
	/**
	 * 导出数据
	 * @param string $url 采集的页面网址
	 * @param array $fields 采集到的字段数据
	 */
	public function runExport($url,$fields){
		//数据库编码
		$dbCharset=strtolower($this->connection['db_charset']);
		if(empty($dbCharset)||$dbCharset=='utf-8'||$dbCharset=='utf8'){
			//不转码
			$dbCharset=null;
		}
		if(!empty($dbCharset)){
			foreach ($fields as $k=>$v){
				$fields[$k]['value']=$this->utf8_to_charset($dbCharset, $v['value']);//值转码
			}
		}
		return $this->runImport($url,$fields);
	}
	/**
	 * 导入数据
	 * @param string $url 采集的页面网址
	 * @param array $fields 采集到的字段数据
	 */
	public abstract function runImport($url,$fields);
}
?>