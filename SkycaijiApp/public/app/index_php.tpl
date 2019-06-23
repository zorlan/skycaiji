<?php
//运行应用
$skyApp=explode(DIRECTORY_SEPARATOR, trim(__DIR__,'\/\\'));   
$skyApp=end($skyApp);
require_once $skyApp.'.php';
$skyApp=new $skyApp();
$skyApp->app()->run();
