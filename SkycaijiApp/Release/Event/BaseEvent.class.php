<?php
namespace Release\Event;
use Think\Controller;
use Admin\Controller\BaseController;
class BaseEvent extends BaseController{
	public function __construct(){
		parent::__construct();
		C('TMPL_EXCEPTION_FILE',APP_PATH.'Public/release_exception.tpl');//定义cms错误模板，ajax出错时方便显示
	}
	/*获取字段值*/
	public function get_field_val($collFieldVal){
		return A('Admin/Release','Event')->get_field_val($collFieldVal);
	}
}
?>