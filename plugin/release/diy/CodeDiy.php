<?php
namespace plugin\release\diy;
class CodeDiy extends BaseDiy{
	/**
	 * 数据库连接信息
	 * @var unknown
	 */
	public $connection = array(
	    'db_type'	 => '',
	    'db_host'    => '',
	    'db_name'    => '',
	    'db_user'    => '',
	    'db_pwd'     => '',
	    'db_port'    => 0,
		'db_prefix'  => '',
	    'db_charset' => ''
	);
	public function init_load(){
		//载入数据库信息
		foreach ($this->connection as $k=>$v){
			$this->connection[$k]=$this->releConfig['diy'][$k];
		}
	}
	/**
	 * 导入数据
	 * @param string $url 采集的页面网址
	 * @param array $fields 采集到的字段数据
	 */
	public function runImport($url,$fields){
		$return=eval($this->releConfig['diy']['code']);
		return $return;
	}
}
?>