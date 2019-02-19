ThinkPHP 5.0 Oracle驱动
===============

首先在`php.ini`开启 `php_pdo_oci` 扩展

然后，配置应用的数据库配置文件`database.php`的`type`参数为：

~~~
'type'  =>  '\think\oracle\Connection',
~~~


