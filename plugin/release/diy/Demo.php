<?php
namespace plugin\release\diy;
/*自定义插件：类名首字母必须大写*/
class Demo extends BaseDiy{
	/*数据库连接信息*/
	public $connection = array(
	    'db_type'	 => 'mysql', //类型
	    'db_host'    => 'localhost', //服务器
	    'db_name'    => 'test', //库名称
	    'db_user'    => 'root', //用户名
	    'db_pwd'     => '', //密码
	    'db_port'    => 3306, //端口
		'db_prefix'  => '', //表前缀
	    'db_charset' => 'utf8', //编码
	);
	/**
	 * 导入数据
	 * @param string $url 采集的页面网址
	 * @param array $fields 采集到的字段数据列表
	 */
	public function runImport($url,$fields){
		/*
		 * -----这里开始写代码-----
		 * 数据库操作：$this->db() 可参考thinkphp5的数据库操作
		 * 获取字段值必须使用 $this->get_field_val($field);方法(可处理图片本地化等)，否则使用$field['value']调用字段原始值
		 */
		
		
		/*
		 * 必须以数组形式返回：
		 * id（必填）表示入库返回的自增id或状态
		 * target（可选）记录入库的数据位置（发布的网址等）
		 * desc（可选）记录入库的数据位置附加信息
		 * error（可选）记录入库失败的错误信息
		 * 入库的信息可在“已采集数据”中查看
		 */
		return array('id'=>0,'target'=>'','desc'=>'','error'=>'');
		
	}
}
?>