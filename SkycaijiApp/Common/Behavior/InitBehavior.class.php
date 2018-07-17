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

namespace Common\Behavior; use Think\Behavior; if(!defined('IN_SKYCAIJI')) { exit('NOT IN SKYCAIJI'); } class InitBehavior extends Behavior { public function run(&$params){ load_data_config(); if (preg_match ( '/MSIE\s*([\.\d]+)/i', $_SERVER ['HTTP_USER_AGENT'], $browserIe )) { $browserIe = doubleval($browserIe[1]); if($browserIe<9){ $GLOBALS['browser_is_old']=true; } } } } ?>