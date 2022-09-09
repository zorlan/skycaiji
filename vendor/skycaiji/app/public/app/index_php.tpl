<?php
//运行应用
$scjApp=explode(DIRECTORY_SEPARATOR, trim(__DIR__,'\/\\'));   
$scjApp=end($scjApp);
require_once $scjApp.'.php';
$scjApp=new $scjApp();
$scjApp->app()->run();
