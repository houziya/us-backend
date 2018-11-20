DROP TABLE c_banner;
DROP TABLE c_package;
DROP TABLE c_package_upgrade;
DROP TABLE c_startImg;

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
    PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='用于安装包升级管理';

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='启动图';

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='Banner图模块';

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

ALTER TABLE `moment_picture` CHANGE COLUMN `status` `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已被删除0正常,1已删除,2锁定下删除';
ALTER TABLE user_device modify column phone_model int(10);
ALTER TABLE user_device modify column distributor int(10);

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
  `sum_finally` int(11) NOT NULL DEFAULT '0' COMMENT '结算量',
  PRIMARY KEY (`summary_day`,`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='渠道商数据';

CREATE TABLE `spread_main_channel` (
  `cid` int(11) NOT NULL AUTO_INCREMENT COMMENT '主渠道ID',
  `main_channel_name` varchar(30) NOT NULL DEFAULT '' COMMENT '主渠道名称',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '所属渠道商ID',
  PRIMARY KEY (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='主渠道商';

ALTER TABLE stat modify column data text NOT NULL COMMENT '';

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
alter table event add enable tinyint(1) not null default '0' comment '有效活动(统计)0-无效1-有效';


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

alter table event_user add receive_push_count int(11) NOT NULL DEFAULT '0' COMMENT 'receive imPush count';
alter table event_user add receive_push_time timestamp  NULL DEFAULT NULL COMMENT 'receive imPush last time';
alter table user_config modify `setting`  varchar(256) NOT NULL DEFAULT '';
alter table spread_channel_stat modify sum_finally float(5,2) NOT NULL;

CREATE TABLE tao_object_id
(
  id BIGINT NOT NULL AUTO_INCREMENT COMMENT 'object id',
  create_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp of creation',
  PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE tao_version
(
  version BIGINT NOT NULL AUTO_INCREMENT COMMENT 'version number',
  create_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp of version',
  flags BIGINT NOT NULL DEFAULT 0 COMMENT 'flags',
  object_id BIGINT NOT NULL COMMENT 'object id',
  to_object_id BIGINT DEFAULT NULL COMMENT 'to object id',
  PRIMARY KEY(version)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE tao_object_store
(
  object_id BIGINT NOT NULL COMMENT 'object id',
  version BIGINT NOT NULL COMMENT 'current version of the object',
  object_type TINYINT NOT NULL COMMENT 'object type',
  update_time TIMESTAMP NOT NULL COMMENT 'timestamp of last update',
  create_time TIMESTAMP NOT NULL COMMENT 'timestamp of creation',
  data TEXT NOT NULL COMMENT 'json encoded key value pairs',
  deleted TINYINT DEFAULT 0 COMMENT 'if object has been deleted',
  PRIMARY KEY(object_id),
  INDEX (object_type),
  INDEX (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY HASH(object_id) PARTITIONS 64; 

CREATE TABLE tao_association_store
(
  from_object_id BIGINT NOT NULL COMMENT 'from object id',
  to_object_id BIGINT NOT NULL COMMENT 'to object id',
  association_type TINYINT NOT NULL COMMENT 'association type',
  association_flags INT NOT NULL DEFAULT 0 COMMENT 'association flags',
  version BIGINT NOT NULL COMMENT 'current version of the association',
  update_time TIMESTAMP NOT NULL COMMENT 'timestamp of last update',
  create_time TIMESTAMP NOT NULL COMMENT 'timestamp of creation',
  data TEXT NOT NULL COMMENT 'json encoded key value pairs',
  PRIMARY KEY(from_object_id, association_type, to_object_id),
  INDEX (association_type),
  INDEX (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY HASH(from_object_id) PARTITIONS 64;

CREATE TABLE `channel_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `channel_name` varchar(20) NOT NULL COMMENT '渠道商名称',
  `channel_data` varchar(255) NOT NULL COMMENT '渠道商相关数据',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_mark` (
  `idfa` varchar(64) NOT NULL DEFAULT '' COMMENT '设备唯一标识idfa',
  `channel_name` varchar(20) NOT NULL COMMENT '渠道商名称',
  `clk_ip` varchar(30) NOT NULL DEFAULT '' COMMENT '点击链接IP',
  `clk_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点击链接时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态0-应用未激活  1-应用已激活',
  PRIMARY KEY (`idfa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

alter table user add tao_object_id bigint(20) DEFAULT NULL COMMENT 'tao object id';
alter table event_moment add tao_object_id bigint(20) DEFAULT NULL COMMENT 'tao object id';
alter table event_moment drop live_id;
ALTER TABLE moment_picture ADD shoot_time_ms BIGINT(13)  DEFAULT 0 NOT NULL COMMENT '拍摄时间微妙';

CREATE TABLE `c_moment_log` (
  `moment_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` text COLLATE utf8_unicode_ci NOT NULL COMMENT '活动动态相关数据，以 JSON 格式存储',
  `picture` text COLLATE utf8_unicode_ci NOT NULL COMMENT '活动照片相关的数据，以 JSON 格式存储',
  `user_id` int(11) NOT NULL COMMENT '后台用户id',
  PRIMARY KEY (`moment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT '后台上传活动照片';

