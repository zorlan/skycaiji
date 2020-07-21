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

return array(
	'admincp'=>'后台',
	
	'sign_wildcard'=>'(*)',
	'sign_match'=>'[内容{:num}]',
	'tips_sign_wildcard'=>'通配符可匹配任意字符',
	'tips_sign_match'=>'匹配任意字符并保存为标签以供调用，等同于捕获组：(?&lt;content编号&gt;.*?)',
	'tips_sign_match_only'=>'匹配任意字符并保存为标签以供调用，等同于捕获组：(?&lt;content&gt;.*?)',
	'tips_sign_group'=>'捕获组：(?&lt;content编号&gt;[\s\S]*?)，匹配正则并保存为[内容]标签以供调用',
	'tips_sign_group_only'=>'捕获组：(?&lt;content&gt;[\s\S]*?)，匹配正则并保存为[内容]标签以供调用',
	
	'tips_regular'=>'可使用正则表达式',
	
	'setting'=>'设置',
	'setting_site'=>'站点设置',
	'set_site_verifycode'=>'开启图片验证码',
		
	
		
	'setting_caiji'=>'采集设置',
	'set_caiji_auto'=>'开启自动采集',
	'set_caiji_run'=>'自动采集运行方式',
	'set_caiji_interval'=>'每次采集间隔时间',
	'set_caiji_num'=>'每次采集数量',
	'set_caiji_timeout'=>'最大执行时间',
	
	'setting_email'=>'邮件发送设置',
	'set_email_sender'=>'发件人名称',
	'set_email_email'=>'发件人邮箱账号',
	'set_email_pwd'=>'发件人邮箱密码',
	'set_email_smtp'=>'SMTP服务器',
	'set_email_port'=>'SMTP端口',
	'set_email_port_tips'=>'TLS一般为25，SSL一般为465，咨询邮箱服务商获取',
	'set_email_type'=>'SMTP端口类型',
	'set_email_test_subject'=>'测试发送邮件',
	'set_email_test_body'=>'恭喜，发送邮件成功！',
		
		
	'config_error_none_email'=>'没有邮箱服务器配置，请在后台设置！',	


	'user'=>'用户',
	'user_list'=>'用户列表',
	'user_add'=>'添加用户',
	'user_edit'=>'编辑用户',
	'user_username'=>'用户名',
	'user_password'=>'密码',
	'user_repassword'=>'确认密码',
	'user_newpwd_tips'=>'如果您想修改密码，请在此输入新密码，否则留空',
	'user_email'=>'邮箱',
	'user_email_tips'=>'用于找回账号密码',
	'user_groupid'=>'用户组',

	'user_error_edit_not_allow'=>'只有创始人才能编辑他人的账号！',
	'user_error_delete_not_allow'=>'只有创始人才能删除账号！',
	'user_error_email'=>'邮箱格式错误！',
	'user_error_groupid'=>'不是允许的用户组！',
	'user_error_del_founder'=>'不能删除创始人账号！',

	'user_error_null_uid'=>'UID不能为空',
	'user_error_empty_user'=>'用户不存在',
	'user_error_login'=>'用户名或密码不正确！',
	'user_error_sublogin'=>'登录提交失败',
	'user_error_is_not_admin'=>'抱歉，请登录管理员账号！',
	'user_login_in'=>'登录中...',
	'user_auto_login'=>'正在自动登录...',
	
	'usertoken_error'=>'用户token错误，请刷新界面重新获取或清除浏览器缓存！',
	
	'task'=>'任务',
	'task_add'=>'添加任务',
	'task_edit'=>'编辑任务',
	'task_list'=>'任务列表',
	'task_change_list'=>'切换列表模式',
	'task_change_folder'=>'切换分组模式',

	'task_name'=>'任务名称',
	'task_tg'=>'任务分组',
	'task_sort'=>'排序',
	'task_sort_help'=>'数字越大越靠前',
	'task_module'=>'采集模块',
	'task_module_'=>'无',
	'task_module_pattern'=>'规则采集',
	'task_module_keyword'=>'关键词采集',
	'task_module_weixin'=>'微信采集',
	'task_auto'=>'自动采集',
	'task_addtime'=>'添加时间',
	'task_caijitime'=>'采集时间',
	'task_edit_collector'=>'下一步：编辑采集器',
	'task_root'=>'根目录',
	'task_loading'=>'正在载入数据',
	'task_none_data'=>'无数据',
	'task_caiji_ing'=>'正在采集',
	'task_set_task'=>'任务设置',
	'task_set_collector'=>'采集器设置',
	'task_set_release'=>'发布设置',
		
		
	'task_error_null_id'=>'请输入任务id',
	'task_error_empty_task'=>'不存在任务',
	'task_error_null_tgid'=>'请输入分组id',
	'task_error_empty_tg'=>'不存在分组',	
	'task_error_null_name'=>'请输入名称！',
	'task_error_has_name'=>'名称已经存在！',
	'task_error_null_module'=>'未设置采集模块',
		
	'taskgroup_add'=>'添加分组',
	'taskgroup_edit'=>'编辑分组',
	'taskgroup'=>'任务分组',
	'taskgroup_list'=>'分组列表',
	'taskgroup_name'=>'分组名称',
	'taskgroup_sort'=>'排序',
	'taskgroup_sort_help'=>'数字越大越靠前',
	'taskgroup_parent_id'=>'父分组',
		
	'tg_add_sub'=>'添加子分组',
	'tg_move'=>'移动分组',
	'tg_exist_sub'=>'存在子分组！',
	'tg_none'=>'分组不存在！',
	'tg_is_parent'=>'存在子分组，请先清空子分组，才能移动分组！',
	'tg_deleteall_has_sub'=>'有子分组的不能删除，需先清空子分组！',
	'tg_no_checked'=>'没有选中的记录！',
		
	'tg_error_null_name'=>'请输入名称！',	
	'tg_error_has_name'=>'名称已经存在！',	

		
		
	'coll_set'=>'采集器设置',		
	'coll_edit_task'=>'上一步：编辑任务',
	'coll_name'=>'采集规则名称',

	'coll_error_invalid_module'=>'无效的采集模块',
	'coll_error_empty_coll'=>'不存在采集器',
	'coll_error_empty_effective'=>'页面脚本不可用，保存失败！',


	'field_module_rule'=>'规则匹配',
	'field_module_auto'=>'自动获取',
	'field_module_xpath'=>'XPath匹配',
	'field_module_words'=>'固定文字',
	'field_module_num'=>'随机数字',
	'field_module_time'=>'时间',
	'field_module_list'=>'随机抽取',
	'field_module_json'=>'JSON提取',
	'field_module_merge'=>'字段组合',
	'field_module_extract'=>'字段提取',
    'field_module_sign'=>'[内容]标签',
	
	'process_module_html'=>'html标签过滤',
	'process_module_replace'=>'内容替换',
	'process_module_filter'=>'关键词过滤',
	'process_module_if'=>'条件判断',
	'process_module_translate'=>'翻译',
	'process_module_tool'=>'工具箱',
	'process_module_batch'=>'批量替换',
	'process_module_substr'=>'截取字符串',
	'process_module_func'=>'使用函数',
	'process_module_api'=>'调用接口',
		
	'p_m_if_1'=>'满足条件采集',	
	'p_m_if_2'=>'满足条件不采集',
	'p_m_if_3'=>'不满足条件采集',
	'p_m_if_4'=>'不满足条件不采集',
    
    
    'p_m_if_c_has'=>'包含',
    'p_m_if_c_nhas'=>'不包含',
    'p_m_if_c_eq'=>'等于',
    'p_m_if_c_neq'=>'不等于',
    'p_m_if_c_heq'=>'恒等于',
    'p_m_if_c_nheq'=>'不恒等于',
    'p_m_if_c_gt'=>'大于',
    'p_m_if_c_egt'=>'大于等于',
    'p_m_if_c_lt'=>'小于',
    'p_m_if_c_elt'=>'小于等于',
    'p_m_if_c_time_eq'=>'时间等于',
    'p_m_if_c_time_egt'=>'时间大于等于',
    'p_m_if_c_time_elt'=>'时间小于等于',
    'p_m_if_c_regexp'=>'正则表达式',
    'p_m_if_c_func'=>'使用函数',
    
		
	'rele_set'=>'发布设置',
	'rele_error_detect_null'=>'没有检测到本地CMS程序，您可以手动绑定数据',
	'rele_error_empty_rele'=>'发布设置不存在',
	'rele_error_null_module'=>'请选择发布方式',
	'rele_error_db'=>'数据库错误：',
	'rele_error_no_table'=>'该数据库没有表',
	'rele_success_db_ok'=>'数据库连接成功',
	'rele_module'=>'发布方式',
	'rele_module_cms'=>'本地CMS程序',
	'rele_module_db'=>'数据库',
	'rele_module_api'=>'生成API',
	'rele_module_toapi'=>'调用接口',
	'rele_module_file'=>'文件存储',
	'rele_module_diy'=>'自定义插件',
	'rele_btn_detect'=>'开始检测',
	'rele_cms_path'=>'CMS路径',

	'rele_db_type'=>'数据库类型',
	'rele_db_host'=>'数据库主机',
	'rele_db_name'=>'数据库名称',
	'rele_db_charset'=>'数据库编码',
	'rele_db_port'=>'数据库端口',
	'rele_db_user'=>'数据库用户',
	'rele_db_pwd'=>'数据库密码',
		
	'error_unknown_database'=>'未知的数据库',
	'error_null_input'=>'请输入{:str}',
	
	'collected'=>'已采集数据',	
	'collected_list'=>'已采集数据列表',

	'COLLECTED_RELE_'=>'无',
	'collected_rele_cms'=>'CMS',
	'collected_rele_db'=>'数据库',
	'collected_rele_file'=>'文件',
	'collected_rele_toapi'=>'接口',
	'collected_rele_api'=>'API',
	'collected_rele_diy'=>'插件',
		
	'verifycode'=>'验证码',
	'verifycode_error'=>'验证码错误！',


	'find_password'=>'找回密码',
	'find_pwd_username'=>'请输入邮箱/用户名',
	'find_pwd_yzm'=>'请输入激活码',
	'find_pwd_resend'=>'重新发送',
	'find_pwd_next_step'=>'下一步',
	'find_pwd_pwd'=>'请输入新密码',
	'find_pwd_repwd'=>'确认新密码',
	'find_pwd_sended'=>'已向邮箱{:email}发送了激活码！',
	'find_pwd_email_failed'=>'发送邮件失败，请检查后台发送邮件配置！',
	'find_pwd_email_wait'=>'需等待{:seconds}秒才能再次发送',
	'find_pwd_email_subject'=>'找回密码 - 蓝天采集器',
	'find_pwd_email_body'=>'您的激活码为：{:yzm}，有效时间{:minutes}分钟',
	'find_pwd_error_username'=>'请输入邮箱/用户名',
	'find_pwd_error_step'=>'步骤错误，请重新操作！',
	'find_pwd_error_post'=>'表单提交失败',
	'find_pwd_error_none_email'=>'邮箱不存在！',
	'find_pwd_error_multiple_emails'=>'存在多个用户使用此邮箱，请输入用户名！',
	'find_pwd_error_none_user'=>'用户不存在！',
	'find_pwd_success'=>'密码修改成功',
	
		
	'yzm_error_please_send'=>'请发送激活码',
	'yzm_error_please_input'=>'请输入激活码',
	'yzm_error_timeout'=>'激活码已过期！请重新发送',
	'yzm_error_yzm'=>'激活码错误',
	
		
	'admincp_style'=>'界面',	
	'admincp_sidebar_mini'=>'菜单最小化',
	'admincp_skins'=>'设置皮肤',
	'skin_blue'=>'蓝',
	'skin_black'=>'黑',
	'skin_purple'=>'紫',
	'skin_green'=>'绿',
	'skin_red'=>'红',
	'skin_yellow'=>'黄',
	'skin_blue_light'=>'蓝亮',
	'skin_black_light'=>'黑亮',
	'skin_purple_light'=>'紫亮',
	'skin_green_light'=>'绿亮',
	'skin_red_light'=>'红亮',
	'skin_yellow_light'=>'黄亮',
	
		
	'store'=>'云平台',

	'rule_collect'=>'采集规则',
		
		
	'empty_data'=>'数据不存在',
	'invalid_op'=>'无效的操作！',
	'submit'=>'提交',
	'search'=>'搜索',
	'op_success'=>'操作成功',
	'op_failed'=>'操作失败',
	'sort'=>'排序',
	'select'=>'选择',
	'select_all'=>'全选',
	'select_first'=>'请选择',
	'save'=>'保存',
	'op'=>'操作',
	'delete'=>'删除',
	'deleted'=>'已删除',
	'edit'=>'编辑',
	'test'=>'测试',
	'confirm_delete'=>'确定删除？',
	'delete_success'=>'删除成功',
	'none'=>'无',
	'caiji'=>'采集',
	'more'=>'更多',
	'yes'=>'是',
	'no'=>'否',
	'all'=>'全部',
	'login'=>'登录',
	'logout'=>'退出',
	'login_auto'=>'下次自动登录',
	'separator'=>'：',
	'redirecting'=>'跳转中...',
	'close'=>'关闭',
	'return_home'=>'{:time}秒钟后返回<a href="{:url}">页面</a>',
		
		
		
	'tips_match'=>'示例：&lt;div id=&quot;a&quot;&gt;[内容1]&lt;/div&gt;(*)&lt;div id=&quot;b&quot;&gt;[内容2]&lt;/div&gt;',
	'tips_matchn'=>'示例：[内容1] [内容2]',
	'tips_match_only'=>'示例：&lt;div id=&quot;content&quot;&gt;[内容]&lt;/div&gt;',
	'tips_match_url'=>'示例：&lt;a href=&quot;http://demo.com/[内容1]/[内容2]&quot;&gt;(*)&lt;/a&gt;',
	'tips_matchn_url'=>'示例：http://www.demo.com/[内容1]-[内容2].html',
		
	'release_upgrade'=>'插件版本过低，请升级插件 <a href="https://www.skycaiji.com/manual/doc/release_upgrade" target="_blank">升级教程</a>',
);
?>