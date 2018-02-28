<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

return array( 'URL_MODEL'=>0, 'URL_CASE_INSENSITIVE' =>true, 'LANG_SWITCH_ON' => true, 'LANG_AUTO_DETECT' => true, 'LANG_LIST' => 'zh-cn', 'DEFAULT_LANG' => 'zh-cn', 'VAR_LANGUAGE' => 'l', 'HTML_V'=>'20180228', 'ROOTPATH'=>realpath(dirname(THINK_PATH)), 'ROOTURL'=>(is_ssl()?'https':'http').'://'.trim($_SERVER['HTTP_HOST'],'/').__ROOT__, 'URL_ROUTER_ON'=>true, 'URL_ROUTE_RULES'=>array( 'api_task/:id/:apiurl'=>'Admin/Index/apiTask' ), 'ACTION_SUFFIX' => 'Action', );