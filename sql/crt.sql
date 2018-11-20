CREATE TABLE captcha (
  id int(10) NOT NULL AUTO_INCREMENT,
  phone varchar(20) NOT NULL DEFAULT '0' COMMENT '电话号',
  start_time date NOT NULL COMMENT '开始发送',
  end_time date NOT NULL COMMENT '发送完毕',
  channel varchar(30) NOT NULL DEFAULT '' COMMENT '渠道',
  message varchar(30) NOT NULL DEFAULT '' COMMENT '发送信息',
  code varchar(4) NOT NULL DEFAULT '' COMMENT '验证码',
  type tinyint(2) NOT NULL DEFAULT '0' COMMENT '发送类型',
  PRIMARY KEY (id),
  KEY phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE event (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL DEFAULT '0' COMMENT '创建人',
  name varchar(100) DEFAULT NULL,
  cover_page varchar(50) NOT NULL DEFAULT '' COMMENT '活动封面',
  gid int(11) NOT NULL DEFAULT '0' COMMENT '群id',
  start_time timestamp NULL COMMENT '活动开始时间',
  end_time timestamp NULL COMMENT '活动结束时间',
  create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  description varchar(300) NOT NULL DEFAULT '' COMMENT '活动描述',
  invitation_code varchar(10) NOT NULL DEFAULT '' COMMENT '邀请码',
  status tinyint(1) NOT NULL DEFAULT '0' COMMENT '活动状态0-正常 1-删除',
  live_id varchar(50) DEFAULT NULL,
  data text COMMENT '人脸识别',
  enable tinyint(1) not null default '0' comment '有效活动(统计)0-无效1-有效',
  `tao_object_id` bigint(20) DEFAULT NULL COMMENT 'tao object id',
  PRIMARY KEY (id,uid),
  KEY idx_event_live_id (live_id),
  KEY idx_start_end_time (start_time,end_time),
  KEY idx_invitation_code (invitation_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE event_moment (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL COMMENT '用户id',
  event_id int(11) NOT NULL COMMENT 'id（目标活动id）',
  content varchar(255) NOT NULL DEFAULT '' COMMENT '动态内容',
  type tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '动态类型0现场',
  status tinyint(1) NOT NULL DEFAULT '0' COMMENT '动态状态 0正常,1删除',
  create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '动态创建时间',
  tao_object_id bigint(20) DEFAULT NULL COMMENT 'tao object id',
  PRIMARY KEY (id,uid),
  KEY idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE event_user (
  event_id int(11) NOT NULL COMMENT '活动id',
  member_uid int(11) NOT NULL COMMENT '活动成员',
  role tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-普通成员;1-创建者',
  create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '加入活动时间',
  is_deleted int(11) DEFAULT '0' COMMENT 'normal:0 deleted:1',
  invitation_code varchar(10) NOT NULL DEFAULT '' COMMENT '用户邀请码',
  receive_push_count int(11) NOT NULL DEFAULT '0' COMMENT 'receive imPush count',
  receive_push_time timestamp NULL DEFAULT NULL COMMENT 'receive imPush last time',
  PRIMARY KEY (event_id,member_uid),
  KEY idx_mem_uid_role (member_uid,role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY HASH (member_uid) PARTITIONS 8;

CREATE TABLE moment_picture (
  id int(11) NOT NULL AUTO_INCREMENT,
  event_id int(11) NOT NULL DEFAULT '0' COMMENT '活动id',
  moment_id int(11) NOT NULL DEFAULT '0' COMMENT '动态id',
  object_id varchar(50) NOT NULL DEFAULT '' COMMENT '对象名',
  content varchar(255) DEFAULT '' COMMENT '内容',
  size varchar(50) DEFAULT '' COMMENT '图片尺寸',
  lat double DEFAULT NULL COMMENT '纬度',
  lng double DEFAULT NULL COMMENT '经度',
  shoot_time timestamp NULL COMMENT '拍摄时间',
  shoot_device varchar(50) DEFAULT '' COMMENT '拍摄设备',
  create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  status tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已被删除0正常,1已删除,2锁定下删除',
  data text COMMENT '人脸检测json数据',
  shoot_time_ms bigint(13) NOT NULL DEFAULT '0' COMMENT '拍摄时间微妙',
  PRIMARY KEY (id, event_id),
  KEY idx_moment_picture_obj_id (object_id),
  KEY idx_moment_id (moment_id)
  KEY idx_create_time (create_time),
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY HASH (event_id) PARTITIONS 8;

CREATE TABLE stat (
  id int(11) NOT NULL AUTO_INCREMENT,
  stat_date int(11) NOT NULL DEFAULT '0' COMMENT '',
  create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '',
  type tinyint(3) NOT NULL DEFAULT '0' COMMENT '',
  data text NOT NULL COMMENT '',
  PRIMARY KEY (id),
  UNIQUE KEY stat_date (stat_date,type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE system_code (
  id int(8) NOT NULL AUTO_INCREMENT,
  type tinyint(2) NOT NULL DEFAULT '0' COMMENT '0-手机型号1-渠道',
  name varchar(32) NOT NULL DEFAULT '' COMMENT '名称',
  PRIMARY KEY (id),
  UNIQUE KEY type_2 (type,name),
  KEY type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE us_group (
  id int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE user (
  uid int(10) NOT NULL AUTO_INCREMENT,
  nickname varchar(60) NOT NULL DEFAULT '' COMMENT '昵称',
  avatar varchar(50) NOT NULL DEFAULT '' COMMENT '头像',
  gender tinyint(1) NOT NULL DEFAULT '0' COMMENT '性别0-女1-男',
  reg_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态0-正常',
  salt binary(8) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0',
  user_login_id varchar(64) NOT NULL DEFAULT '',
  tao_object_id bigint(20) DEFAULT NULL COMMENT 'tao object id',
  PRIMARY KEY (uid),
  KEY reg_time (reg_time)
) ENGINE=InnoDB AUTO_INCREMENT=1016 DEFAULT CHARSET=utf8 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE user_config (
  uid int(10) NOT NULL,
  type tinyint(2) NOT NULL DEFAULT '0' COMMENT '0个人配置',
  setting varchar(256) NOT NULL DEFAULT '' COMMENT 'json格式,配置内容',
  PRIMARY KEY (uid),
  KEY type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE user_device (
  uid int(10) NOT NULL DEFAULT '0' COMMENT '用户id',
  reg_ip varchar(30) NOT NULL DEFAULT '' COMMENT '注册ip',
  log_ip varchar(30) NOT NULL DEFAULT '' COMMENT '登录ip',
  reg_device_id varchar(64) NOT NULL DEFAULT '' COMMENT '注册设备id',
  log_device_id varchar(64) NOT NULL DEFAULT '' COMMENT '登录设备id',
  platform tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-iphone1-android',
  client_version int(4) NOT NULL DEFAULT '0' COMMENT '客户端版本号',
  os_version int(4) NOT NULL DEFAULT '0' COMMENT '手机操作系统版本',
  phone_model int(10) NOT NULL DEFAULT '0' COMMENT '手机型号',
  login_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  distributor int(10) NOT NULL DEFAULT '0' COMMENT '渠道',
  PRIMARY KEY (uid),
  KEY login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE user_device_history (
  id int(10) NOT NULL AUTO_INCREMENT,
  uid int(10) NOT NULL DEFAULT '0' COMMENT '用户id',
  log_ip varchar(30) NOT NULL DEFAULT '' COMMENT '登录ip',
  log_device_id varchar(64) NOT NULL DEFAULT '' COMMENT '登录设备id',
  platform tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-iphone1-android',
  client_version int(4) NOT NULL DEFAULT '0' COMMENT '客户端版本',
  os_version int(4) NOT NULL DEFAULT '0' COMMENT '手机操作系统',
  phone_model tinyint(2) NOT NULL DEFAULT '0' COMMENT '手机型号',
  login_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  distributor tinyint(2) NOT NULL DEFAULT '0' COMMENT '渠道',
  PRIMARY KEY (id,uid),
  KEY login_time (login_time)
) ENGINE=InnoDB AUTO_INCREMENT=881 DEFAULT CHARSET=utf8 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE user_login (
  type tinyint(2) NOT NULL DEFAULT '0' COMMENT '0-手机1-qq2-sina3-weChat',
  token varchar(32) NOT NULL DEFAULT '' COMMENT 'phone/open_id/auth_id',
  uid int(10) NOT NULL DEFAULT '0' COMMENT '用户id',
  secret varchar(512) NOT NULL DEFAULT '',
  enabled tinyint(1) NOT NULL DEFAULT '1' COMMENT '0-无效1-有效',
  PRIMARY KEY (type,token,uid),
  KEY token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY HASH (uid) PARTITIONS 8;

CREATE TABLE event_live (
  live_id varchar(128) NOT NULL COMMENT 'live id',
  event_id bigint NOT NULL COMMENT 'event id',
  author bigint NOT NULL DEFAULT 0 COMMENT 'author who triggered the live creation',
  operation int NOT NULL DEFAULT 0 COMMENT 'operation triggered the live creation',
  create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'time of creation',
  PRIMARY KEY(live_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY KEY(live_id) PARTITIONS 8;

CREATE TABLE `user_record_platfromid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platfrom_id` varchar(100) NOT NULL COMMENT '设备ID',
  `client_version` varchar(20) DEFAULT NULL COMMENT '客户端版本',
  `model` varchar(30) DEFAULT NULL COMMENT '手机型号',
  `client_version_code` varchar(10) DEFAULT NULL COMMENT '端人员开发使用版本码',
  `operator` varchar(20) DEFAULT NULL COMMENT '运营商',
  `device` int(1) DEFAULT NULL COMMENT '类型 1:ios  2:安卓',
  `os_version` varchar(10) DEFAULT NULL COMMENT '系统版本号',
  `ip` varchar(30) DEFAULT NULL COMMENT 'ip',
  `mi_regid` varchar(255) DEFAULT NULL COMMENT '小米 token',
  `jailbroken` int(1) DEFAULT NULL COMMENT '是否越狱 1：越狱  2非越狱',
  `idfa` varchar(100) DEFAULT NULL COMMENT '广告标识',
  `token` varchar(255) DEFAULT NULL COMMENT '设备token',
  `network` varchar(30) DEFAULT NULL COMMENT '联网类型',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_platform_id` (`platfrom_id`,`device`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE report(
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    data varchar(1024) not null default '' COMMENT 'p_id:图片id',
    create_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    reporter int(10) NOT NULL COMMENT '举报人',
    uid int(10) NOT NULL COMMENT '被举报人',
    report_type tinyint(2) NOT NULL DEFAULT 0 COMMENT '0-个人活动',
    status tinyint(2) NOT NULL DEFAULT 0 COMMENT '0-未处理-1被忽略-2-被删除',
    PRIMARY KEY (id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='举报审核管理';

CREATE TABLE `spread_channel_stat` (
  `summary_day` int(11) NOT NULL,
  `sid` int(11) NOT NULL COMMENT '分渠道商ID',
  `click` int(11) NOT NULL DEFAULT '0' COMMENT '点击量',
  `activation` int(11) NOT NULL DEFAULT '0' COMMENT '激活量',
  `with_ip_activation` int(11) NOT NULL DEFAULT '0' COMMENT '相同IP下违规激活用户数',
  `effective_activation` int(11) NOT NULL DEFAULT '0' COMMENT '有效激活',
  `registrations` int(11) NOT NULL DEFAULT '0' COMMENT '注册量',
  `with_device_activation` int(11) NOT NULL DEFAULT '0' COMMENT '相同设备违规注册用户数',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '渠道商可见状态0-不可见 1-可见',
  `sum_finally` float(5,2) NOT NULL,
  PRIMARY KEY (`summary_day`,`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='渠道商数据';

CREATE TABLE `spread_main_channel` (
  `cid` int(11) NOT NULL AUTO_INCREMENT COMMENT '主渠道ID',
  `main_channel_name` varchar(30) NOT NULL DEFAULT '' COMMENT '主渠道名称',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '所属渠道商ID',
  PRIMARY KEY (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='主渠道商';

CREATE TABLE `spread_sub_channel` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分渠道ID',
  `cid` int(11) NOT NULL DEFAULT '0' COMMENT '主渠道ID',
  `platform` tinyint(2) NOT NULL DEFAULT '0' COMMENT '1-android-0-ios',
  `sub_channel_name` varchar(30) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `channel_token` varchar(128) NOT NULL DEFAULT '0' COMMENT '渠道token',
  `channel_code` varchar(50) NOT NULL COMMENT '渠道号',
  `proportion` float(5,2) NOT NULL COMMENT '扣量比例',
  `unitPrice` float(5,2) NOT NULL COMMENT '结算单价',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='分渠道商';

CREATE TABLE `moment_comment_deprecated` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_uid` int(11) NOT NULL DEFAULT '0' COMMENT 'to user',
  `from_uid` int(11) NOT NULL DEFAULT '0' COMMENT 'from user',
  `event_id` int(11) NOT NULL DEFAULT '0' COMMENT 'event id',
  `content` text NOT NULL COMMENT 'comment content',
  `moment_id` int(11) NOT NULL DEFAULT '0' COMMENT 'moment id',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'create time',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:normal , 1:delete',
  PRIMARY KEY (`id`,`to_uid`),
  KEY `idx_mid_eid` (`event_id`,`moment_id`),
  KEY `to_uid` (`to_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY HASH (to_uid) PARTITIONS 8;

CREATE TABLE `moment_like_deprecated` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL COMMENT '用户id',
  `event_id` int(11) NOT NULL COMMENT '活动id',
  `moment_id` int(11) NOT NULL COMMENT '动态id',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `status` int(11) DEFAULT '0' COMMENT 'Praise:0 cancel praise:1',
  PRIMARY KEY (`id`,`uid`),
  KEY `idx_event_moment_id` (`event_id`,`moment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 PARTITION BY HASH (uid) PARTITIONS 8;

create table group_user(
    id int(11) not null AUTO_INCREMENT,
    gid int(11) not null default 0 comment 'group_tao_id',
    uid int(11) not null default 0,
    code varchar(16) not null default '' comment 'Invitation code',
    expire_time timestamp not null default 0,
    primary key(id),
    unique key(code)
)engine=InnoDB DEFAULT CHARSET=utf8;

create table user_audit_log(
    id int(11) not null auto_increment,
    uid int(11) not null default 0,
    create_time timestamp default CURRENT_TIMESTAMP,
    type tinyint(2) not null default 0 comment '0-退群,1-被逐出群',
    data varchar(1024) not null default '' comment '备注',
    primary key(id)
)engine=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tao_association_store` (
  `from_object_id` bigint(20) NOT NULL COMMENT 'from object id',
  `to_object_id` bigint(20) NOT NULL COMMENT 'to object id',
  `association_type` tinyint(4) NOT NULL COMMENT 'association type',
  `association_flags` int(11) NOT NULL DEFAULT '0' COMMENT 'association flags',
  `version` bigint(20) NOT NULL COMMENT 'current version of the association',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'timestamp of last update',
  `create_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'timestamp of creation',
  `data` mediumtext NOT NULL COMMENT 'json encoded key value pairs',
  PRIMARY KEY (`from_object_id`,`association_type`,`to_object_id`),
  KEY `association_type` (`association_type`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY HASH (from_object_id) PARTITIONS 64;


CREATE TABLE `tao_object_id` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'object id',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp of creation',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1

CREATE TABLE `tao_object_store` (
  `object_id` bigint(20) NOT NULL COMMENT 'object id',
  `version` bigint(20) NOT NULL COMMENT 'current version of the object',
  `object_type` tinyint(4) NOT NULL COMMENT 'object type',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'timestamp of last update',
  `create_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'timestamp of creation',
  `data` mediumtext NOT NULL COMMENT 'json encoded key value pairs',
  `deleted` tinyint(4) DEFAULT '0' COMMENT 'if object has been deleted',
  PRIMARY KEY (`object_id`),
  KEY `object_type` (`object_type`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY HASH (object_id) PARTITIONS 64;

CREATE TABLE `channel_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `channel_name` varchar(20) NOT NULL COMMENT '渠道商名称',
  `channel_data` varchar(255) NOT NULL COMMENT '渠道商相关数据',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `user_mark` (
  `idfa` varchar(64) NOT NULL DEFAULT '' COMMENT '设备唯一标识idfa',
  `channel_name` varchar(20) NOT NULL COMMENT '渠道商名称',
  `clk_ip` varchar(30) NOT NULL DEFAULT '' COMMENT '点击链接IP',
  `clk_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点击链接时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态0-应用未激活  1-应用已激活',
  PRIMARY KEY (`idfa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `target_push` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT 'uid',
  `object_id` int(11) NOT NULL DEFAULT '0' COMMENT 'object id',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:event , 1:group',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'create time',
  PRIMARY KEY (`id`),
  KEY `object_id_uid` (`object_id`,`uid`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
