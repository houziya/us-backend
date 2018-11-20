CREATE TABLE `c_menu_url` (
  `menu_id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(50) NOT NULL,
  `menu_url` varchar(255) NOT NULL,
  `module_id` int(11) NOT NULL,
  `is_show` tinyint(4) NOT NULL COMMENT '是否在sidebar里出现',
  `online` int(11) NOT NULL DEFAULT '1' COMMENT '在线状态，还是下线状态，即可用，不可用。',
  `shortcut_allowed` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '是否允许快捷访问',
  `menu_desc` varchar(255) DEFAULT NULL,
  `father_menu` int(11) NOT NULL DEFAULT '0' COMMENT '上一级菜单',
  PRIMARY KEY (`menu_id`),
  UNIQUE KEY `menu_url` (`menu_url`)
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8 COMMENT='功能链接（菜单链接）';

INSERT INTO `c_menu_url` VALUES ('1', '首页', 'Console/User/index', '1', '0', '1', '1', '后台首页', '0'), ('2', '账号列表', 'Console/User/users', '1', '1', '1', '1', '账号列表', '0'), ('3', '修改账号', 'Console/User/modify', '1', '0', '1', '0', '修改账号', '2'), ('4', '新建账号', 'Console/User/add', '1', '0', '1', '1', '新建账号', '2'), ('5', '个人信息', 'Console/User/profile', '1', '0', '1', '1', '个人信息', '0'), ('6', '账号组成员', 'Console/Group/members', '1', '0', '1', '0', '显示账号组详情及该组成员', '7'), ('7', '账号组管理', 'Console/Group/groups', '1', '1', '1', '1', '增加管理员', '0'), ('8', '修改账号组', 'Console/Group/modify', '1', '0', '1', '0', '修改账号组', '7'), ('9', '新建账号组', 'Console/Group/add', '1', '0', '1', '1', '新建账号组', '7'), ('10', '权限管理', 'Console/Group/role', '1', '1', '1', '1', '用户权限依赖于账号组的权限', '0'), ('11', '菜单模块', 'Console/Menu/module', '1', '1', '1', '1', '菜单里的模块123', '0'), ('12', '编辑菜单模块', 'Console/Menu/moduleModify ', '1', '0', '1', '0', '编辑模块', '11'), ('13', '添加菜单模块', 'Console/Menu/moduleAdd  ', '1', '0', '1', '1', '添加菜单模块', '11'), ('14', '功能列表', 'Console/Menu/menus', '1', '1', '1', '1', '菜单功能及可访问的链接', '0'), ('15', '增加功能', 'Console/Menu/add', '1', '0', '1', '1', '增加功能', '14'), ('16', '功能修改', 'Console/Menu/modify', '1', '0', '1', '0', '修改功能', '14'), ('17', '设置模板', 'Console/System/set', '1', '0', '1', '1', '设置模板', '0'), ('18', '便签管理', 'Console/Quick/list', '1', '1', '1', '1', 'quick note', '0'), ('19', '菜单链接列表', 'Console/Menu/list  ', '1', '0', '1', '0', '显示模块详情及该模块下的菜单', '11'), ('20', '登入', 'Console/User/login', '1', '0', '1', '1', '登入页面', '0'), ('21', '操作记录', 'Console/System/log', '1', '1', '1', '1', '用户操作的历史行为', '0'), ('22', '系统信息', 'Console/System/index', '1', '1', '1', '1', '显示系统相关信息', '0'), ('24', '添加便签', 'Console/Quick/add', '1', '0', '1', '1', '添加quicknote的内容', '18'), ('25', '修改便签', 'Console/Quick/modify', '1', '0', '1', '0', '修改quicknote的内容', '18'), ('26', '系统设置', 'Console/System/setting', '1', '0', '1', '0', '系统设置', '0'), ('105', '删除菜单模块', 'Console/Menu/moduleDel', '1', '0', '1', '1', '删除菜单模块', '11'), ('109', '活跃及新增', 'Console/Stat/addActive', '11', '1', '1', '0', '统计活跃及新增', '0'), ('111', '活动及照片', 'Console/Stat/picActivity', '11', '1', '1', '0', '统计活动以及照片', '0'), ('112', '应用内分享', 'Console/Stat/applicationsharing', '11', '1', '1', '0', '统计应用内的分享', '0'), ('113', 'H5分享相关', 'Console/Stat/sharerelated', '11', '1', '1', '0', '统计H5分享相关', '0'), ('114', '菜单更新', 'Console/Menu/lists', '1', '0', '1', '0', '菜单更新', '14'), ('115', '留存数据', 'Console/Stat/keep', '11', '1', '1', '0', '留存的数据123', '0'), ('124', '安装包列表', 'Console/Package/list', '16', '1', '1', '1', '安装包模块', '0'), ('126', '安装包添加', 'Console/Package/add', '1', '0', '1', '1', '安装包添加', '124'), ('127', '安装包升级', 'Console/Package/upgrade', '16', '1', '1', '1', '安装包升级', '0'), ('128', '安装包修改', 'Console/Package/modify', '1', '0', '1', '1', '安装包修改', '0'), ('129', '启动图配置', 'Console/Startimg/imgs', '16', '1', '1', '1', '启动图配置', '0'), ('130', '启动图添加', 'Console/Startimg/add', '1', '1', '1', '1', '启动图添加', '0'), ('131', '启动图修改', 'Console/Startimg/modify', '1', '1', '1', '1', '启动图修改', '0'), ('132', '启动图详情页', 'Console/Startimg/detail', '1', '1', '1', '1', '启动图详情页', '0'), ('133', 'ios启动图推送', 'Console/Startimg/pushConfig', '1', '1', '1', '1', 'ios启动图推送', '0'), ('134', 'android启动图推送', 'Console/Startimg/pushAndroidConfig', '1', '1', '1', '1', 'android启动图推送', '0'), ('135', 'banners图列表', 'Console/Banner/banners', '16', '1', '1', '1', 'banners图列表', '0'), ('136', 'banner图添加', 'Console/Banner/add', '1', '1', '1', '1', 'banner图添加', '0'), ('137', 'banner图修改', 'Console/Banner/modify', '1', '1', '1', '1', 'banner图修改', '0'), ('138', 'banner图详情', 'Console/Banner/detail', '1', '1', '1', '1', 'banner图详情', '0'), ('139', 'ios banner图推送', 'Console/Banner/pushConfig', '1', '1', '1', '1', 'ios banner图推送', '0'), ('140', '更换活动封面', 'Console/Template/replaceCover', '16', '1', '1', '0', '更换活动封面', '0'), ('141', 'ios安装包推送', 'Console/Package/pushConfig', '1', '1', '1', '1', '安装包推送', '0'), ('142', 'android安装包推送', 'Console/Package/pushAndroidConfig', '1', '1', '1', '1', 'android安装包推送', '0'), ('143', '举报审核', 'Console/Report/reports', '17', '1', '1', '0', '举报审核', '0'), ('144', '用户查询', 'Console/Quser/users', '17', '1', '1', '0', '用户查询', '0'), ('145', '更换默认头像', 'Console/Template/changeAvatar', '16', '1', '1', '1', '更换默认头像', '0'), ('146', '活动查询', 'Console/Event/events', '17', '1', '1', '1', '活动查询', '0');

CREATE TABLE `c_module` (
  `module_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `module_name` varchar(50) NOT NULL,
  `module_url` varchar(128) NOT NULL,
  `module_sort` int(11) unsigned NOT NULL DEFAULT '1',
  `module_desc` varchar(255) DEFAULT NULL,
  `module_icon` varchar(32) DEFAULT 'icon-th' COMMENT '菜单模块图标',
  `online` int(11) NOT NULL DEFAULT '1' COMMENT '模块是否在线',
  PRIMARY KEY (`module_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COMMENT='菜单模块';

INSERT INTO `c_module` VALUES ('1', '控制面板', 'Console/Menu/module', '0', '配置OSAdmin的相关功能', 'icon-folder-open', '1'), ('2', '测试模块', 'Console/Menu/test', '3', '测试abc', 'icon-volume-off', '1'), ('11', '数据统计', 'Console/Menu/act', '4', '这是现场活动的详情页123', 'icon-film', '1'), ('15', '测试', 'Console/Menu/test12', '5', '这是测试的。', 'icon-warning-sign', '1'), ('16', '配置', 'Console/Package/list', '2', '配置', 'icon-th-large', '1'), ('17', '活动查询', 'Console/Menu/event', '3', '活动查询', 'icon-th', '1');

CREATE TABLE c_package(
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    create_time timestamp NOT NULL COMMENT '创建时间',
    file_name varchar(32) NOT NULL COMMENT '安装包名',
    version varchar(255) NOT NULL COMMENT '版本号',
    description varchar(255) NOT NULL COMMENT '描述',
    operator varchar(32) NOT NULL COMMENT '上传人',
    code int(10) NOT NULL COMMENT '版本码', 
    platform tinyint(2) NOT NULL COMMENT '1-ios-0-android',
    package_size float(3,1) NOT NULL COMMENT '尺寸',
    PRIMARY KEY (id)
)ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='安装包模块';

CREATE TABLE c_package_upgrade (
    id int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
    skip_url varchar(255) NOT NULL COMMENT '跳转地址',
    update_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
    descs varchar(255) NOT NULL COMMENT '内容',
    platform tinyint(2) NOT NULL COMMENT '1-ios-0-android',
    code varchar(255) NOT NULL COMMENT '版本码',
PRIMARY KEY (id)) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='用于安装包升级管理';


CREATE TABLE `c_quick_note` (
  `note_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'note_id',
  `note_content` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '内容',
  `owner_id` int(10) unsigned NOT NULL COMMENT '谁添加的',
  PRIMARY KEY (`note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='用于显示的quick note';

CREATE TABLE c_startImg (
    id int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '启动图自增Id',
    images varchar(255) NOT NULL COMMENT '图片名',
    create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    operator varchar(50) NOT NULL COMMENT '上传人',
    skip_url varchar(255) NOT NULL COMMENT '跳转地址',
    action_type tinyint(2) NOT NULL COMMENT '1-web-2-现场',
    title varchar(50) NOT NULL COMMENT '标题',
    version varchar(255) NOT NULL COMMENT '版本号',
    code varchar(255) NOT NULL COMMENT '版本码',
    push tinyint(2) NOT NULL DEFAULT '1' COMMENT '正常状态',
    platform tinyint(2) NOT NULL DEFAULT '0' COMMENT '0-android-1-ios',
    duration tinyint(4) NOT NULL DEFAULT '3' COMMENT '时间控制默认3秒',
    can_skip tinyint(2) NOT NULL DEFAULT '0' COMMENT '0-不允许跳过启动图-1-允许跳过',
    PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


CREATE TABLE `c_sys_log` (
  `op_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(32) NOT NULL,
  `action` varchar(255) NOT NULL,
  `class_name` varchar(255) NOT NULL COMMENT '操作了哪个类的对象',
  `class_obj` varchar(32) NOT NULL COMMENT '操作的对象是谁，可能为对象的ID',
  `result` text NOT NULL COMMENT '操作的结果',
  `op_time` int(11) NOT NULL,
  PRIMARY KEY (`op_id`),
  KEY `op_time` (`op_time`),
  KEY `class_name` (`class_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='操作日志表';

CREATE TABLE `c_system` (
  `key_name` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `key_value` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='系统配置表';

INSERT INTO `c_system` VALUES ('timezone', '\"Asia/Shanghai\"');

CREATE TABLE `c_user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `real_name` varchar(255) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_desc` varchar(255) DEFAULT NULL,
  `login_time` int(11) DEFAULT NULL COMMENT '登录时间',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `login_ip` varchar(32) DEFAULT NULL,
  `user_group` int(11) NOT NULL,
  `template` varchar(32) NOT NULL DEFAULT 'default' COMMENT '主题模板',
  `shortcuts` text COMMENT '快捷菜单',
  `show_quicknote` int(11) NOT NULL DEFAULT '1' COMMENT '是否显示quicknote',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8 COMMENT='后台用户';

INSERT INTO `c_user` VALUES ('1', 'admin', 'e10adc3949ba59abbe56e057f20f883e', 'really', '13800138004', 'admin@osadmin.org', '初始的超级管理员!', '1448940166', '1', '124.207.133.230', '1', 'blacktie', '2,7,10,11,13,14,18,21,24', '1');

CREATE TABLE `c_user_group` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(32) DEFAULT NULL,
  `group_role` text CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT '初始权限为1,5,17,18,22,23,24,25',
  `owner_id` int(11) DEFAULT NULL COMMENT '创建人ID',
  `group_desc` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='账号组';

INSERT INTO `c_user_group` VALUES ('1', '超级管理员组', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,101,103,104,105,109,111,112,113,114,115,124,126,127,129,135,140,143,144,145,146', '1', '万能的不是神，是程序员'), ('2', '默认账号组', '1,5,17,18,20,22,23,24,25,101', '1', '默认账号组');

CREATE TABLE c_banner (
id int(11) unsigned NOT NULL AUTO_INCREMENT,
create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
images varchar(255) NOT NULL COMMENT '图片名',
action_type tinyint(2) NOT NULL COMMENT '1-Web-2-现场',
skip_url varchar(255) NOT NULL COMMENT '跳转地址',
title varchar(50) NOT NULL COMMENT '标题',
push tinyint(2) NOT NULL DEFAULT '1' COMMENT '1-正常-2-推送',
code varchar(255) NOT NULL COMMENT '版本码', 
operator varchar(50) NOT NULL COMMENT '上传人',
platform tinyint(2) NOT NULL DEFAULT '0' COMMENT '0-android-1-ios',
PRIMARY KEY (id)
 
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Banner图模块';

CREATE TABLE `c_moment_log` (
  `moment_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` text COLLATE utf8_unicode_ci NOT NULL COMMENT '活动动态相关数据，以 JSON 格式存储',
  `picture` text COLLATE utf8_unicode_ci NOT NULL COMMENT '活动照片相关的数据，以 JSON 格式存储',
  `user_id` int(11) NOT NULL COMMENT '后台用户id',
  PRIMARY KEY (`moment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT '后台上传活动照片';
