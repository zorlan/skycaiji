/*start*/

DROP TABLE IF EXISTS `skycaiji_cache_source_url`;

CREATE TABLE `skycaiji_cache_source_url` (
  `cname` varchar(32) NOT NULL,
  `ctype` tinyint(3) unsigned NOT NULL,
  `dateline` int(10) unsigned NOT NULL,
  `data` mediumblob NOT NULL,
  PRIMARY KEY (`cname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_cache_source_url` */

/*Table structure for table `skycaiji_collected` */

DROP TABLE IF EXISTS `skycaiji_collected`;

CREATE TABLE `skycaiji_collected` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1000) NOT NULL DEFAULT '',
  `urlMd5` varchar(32) NOT NULL DEFAULT '',
  `release` varchar(10) NOT NULL DEFAULT '',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `target` varchar(1000) NOT NULL DEFAULT '',
  `desc` varchar(1000) NOT NULL DEFAULT '',
  `error` varchar(1000) NOT NULL DEFAULT '',
  `addtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_urlmd5` (`urlMd5`),
  KEY `ix_taskid` (`task_id`),
  KEY `ix_addtime` (`addtime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_collected` */

/*Table structure for table `skycaiji_collector` */

DROP TABLE IF EXISTS `skycaiji_collector`;

CREATE TABLE `skycaiji_collector` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(50) NOT NULL,
  `module` varchar(10) NOT NULL DEFAULT '',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `uptime` int(11) NOT NULL DEFAULT '0',
  `config` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_collector` */

/*Table structure for table `skycaiji_config` */

DROP TABLE IF EXISTS `skycaiji_config`;

CREATE TABLE `skycaiji_config` (
  `cname` varchar(32) NOT NULL,
  `ctype` tinyint(3) unsigned NOT NULL,
  `dateline` int(10) unsigned NOT NULL,
  `data` mediumblob NOT NULL,
  PRIMARY KEY (`cname`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_config` */

insert  into `skycaiji_config`(`cname`,`ctype`,`dateline`,`data`) values ('version',0,0,'1.0'),('caiji',2,0,'a:6:{s:4:\"auto\";i:0;s:3:\"run\";s:0:\"\";s:3:\"num\";i:10;s:8:\"interval\";i:60;s:7:\"timeout\";i:60;s:12:\"download_img\";i:0;}'),('site',2,0,'a:1:{s:10:\"verifycode\";i:1;}');

/*Table structure for table `skycaiji_release` */

DROP TABLE IF EXISTS `skycaiji_release`;

CREATE TABLE `skycaiji_release` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(50) NOT NULL DEFAULT '',
  `module` varchar(10) NOT NULL DEFAULT '',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `config` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_release` */

/*Table structure for table `skycaiji_release_app` */

DROP TABLE IF EXISTS `skycaiji_release_app`;

CREATE TABLE `skycaiji_release_app` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(10) NOT NULL DEFAULT '',
  `app` varchar(50) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `desc` text,
  `addtime` int(11) NOT NULL DEFAULT '0',
  `uptime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_app` (`module`,`app`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_release_app` */

/*Table structure for table `skycaiji_rule` */

DROP TABLE IF EXISTS `skycaiji_rule`;

CREATE TABLE `skycaiji_rule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(20) NOT NULL DEFAULT '',
  `module` varchar(20) NOT NULL DEFAULT '',
  `store_id` int(11) NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `uptime` int(11) NOT NULL DEFAULT '0',
  `config` text,
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_rule` */

/*Table structure for table `skycaiji_task` */

DROP TABLE IF EXISTS `skycaiji_task`;

CREATE TABLE `skycaiji_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `tg_id` int(11) NOT NULL DEFAULT '0',
  `module` varchar(10) NOT NULL DEFAULT '',
  `auto` tinyint(4) NOT NULL DEFAULT '0',
  `sort` mediumint(9) NOT NULL DEFAULT '0',
  `addtime` int(11) NOT NULL DEFAULT '0',
  `caijitime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_task` */

/*Table structure for table `skycaiji_taskgroup` */

DROP TABLE IF EXISTS `skycaiji_taskgroup`;

CREATE TABLE `skycaiji_taskgroup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `sort` mediumint(9) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_taskgroup` */

/*Table structure for table `skycaiji_user` */

DROP TABLE IF EXISTS `skycaiji_user`;

CREATE TABLE `skycaiji_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `groupid` int(11) NOT NULL DEFAULT '0',
  `email` varchar(255) NOT NULL DEFAULT '',
  `regtime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `username` (`username`),
  KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_user` */

/*Table structure for table `skycaiji_usergroup` */

DROP TABLE IF EXISTS `skycaiji_usergroup`;

CREATE TABLE `skycaiji_usergroup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `level` int(11) NOT NULL DEFAULT '0',
  `founder` tinyint(4) NOT NULL DEFAULT '0',
  `admin` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

/*Data for the table `skycaiji_usergroup` */

insert  into `skycaiji_usergroup`(`id`,`name`,`level`,`founder`,`admin`) values (1,'创始人',9,1,1),(2,'管理员',8,0,1),(3,'普通用户',0,0,0);

/*end*/