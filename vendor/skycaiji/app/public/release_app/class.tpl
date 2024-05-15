<?php
namespace plugin\release\cms;
class {$classname} extends BaseCms{
	/*参数*/
	public $_params ={$params};

	{$funcs}
	
	/*导入数据*/
	public function runImport($params){
		/*
		 * -----这里开始写代码-----
		 * 数据库操作：$this->db()，可参考thinkphp5的数据库操作
		 * 参数值列表：$params，$params[变量名] 调用参数的值
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