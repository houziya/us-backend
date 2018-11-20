<?php
    //日期
    define('Us\\User\\MINUTE', 60);
    define('Us\\User\\HOUR', 3600);
    define('Us\\User\\DAY', 86400);
    define('Us\\User\\WEEK', 604800);
    define('Us\\User\\MONTH', 2592000);
    define('Us\\User\\YEAR', 31536000);
    //注册来源
    define('Us\\User\\REGISTER_TYPE_PHONE', 0);
    define('Us\\User\\REGISTER_TYPE_QQ', 1);
    define('Us\\User\\REGISTER_TYPE_SINA', 2);
    define('Us\\User\\REGISTER_TYPE_WECHAT', 3);
    //验证码可验证次数
    define('Us\\User\\ATTEMPTS_TIMES', 2);
    //默认头像
    define('Us\\User\\DEFAULT_AVATAR', 'default_avatar');
    //验证码验证 文字提示
    define('Us\\User\\PHONE_CONTENT', '手机号已注册');
    define('Us\\User\\CAPTCHA_CONTENT', '验证码错误');
    //redis key
    define('Us\\User\\DISTRIBUTOR', 'distributor');    //渠道
    define('Us\\User\\PHONE_MODEL', 'phone_model');    //手机型号
    define('Us\\User\\CAPTCHA', 'captcha');
    define('Us\\User\\CAPTCHA_PHONE', 'phone:');
    define('Us\\User\\US_User', 'us.user');
    define('Us\\User\\US_MEMBERSHIP', 'us.membership');
    define('Us\\User\\US_Group', 'us.group');
    define('Us\\User\\TUBE_GROUP', 'us.group');
    define('Us\\User\\US_DEVID', 'us_devid_');     //统计-新增用户
    define('Us\\User\\SESSION', 'us.sessid.');     //session
    define('Us\\User\\TUBE_MEMBERSHIP', 'us.membership');
    define('Us\\Event\\INVITE', 'us.invite.');     //邀请码
    define('Us\\User\\TUBE_USER', 'us.user');
    define('Us\\User\\SIGN', 'sign.');     //sign
    define('Us\\User\\PUSH_JSON', 'push.json.');     //push json
    define('Us\\Push\\PUSH_EMOJI_JSON', 'push.emoji_unicode');     //emoji json
    
    //过期时间
    define('Us\\User\\TUBE_SESSION', 86400);    //tube_session_key 过期时间
    define('Us\\Config\\TUBE_SESSION_EXPIRE', "30");    //tube_session_key 过期时间
    define('Us\\Config\\SIGN_EXPIRE', 1);    //sign_key 过期时间
    define('Us\\Config\\INVITE_EXPIRE', 31536000);    //小组邀请过期时间
    
    // Session
    define('Us\\Config\\Session\\CLASSNAME', 'yii\\redis\\Session');
    define('Us\\Config\\Session\\HOSTNAME', '10.66.83.117');
    define('Us\\Config\\Session\\PORT', 6379);
    define('Us\\Config\\Session\\PASSWORD', '5821ecac-9143-4b29-8d98-f95177733bb8:88788D2E');
    //配置
    define('Us\\Config\\UPLOAD_DOMAIN', 'app.himoca.com:9990');    //上传域名
    define('Us\\Config\\DOWNLOAD_DOMAIN', 'uspic-10006628.file.myqcloud.com');    //下载图片域名
    define('Us\\Config\\INIT_DOMAIN', 'app.himoca.com:9990');    //默认域名
    define('Us\\Config\\SPREAD_DOMAIN', 'app.himoca.com');    //订阅域名
    define('Us\\Config\\TUBE_DOMAIN', '119.29.44.245:7237');    //spread/tube域名 队列
    define('Us\\Config\\LOG_LEVEL', 4);    //日志级别
    define('Us\\Config\\FLAG', 0);
    define('Us\\Config\\IOS_CURRENT_VERSION', "3");
    define('Us\\Config\\REVIEW_FLAG', "1");
    define('Us\\User\\SESSION_TUBE', 'tube.sessid.');     //tube session
    define('Us\\Config\\LIMIT_SIGN', 128);
    define('Us\\Config\\LIMIT_EVENT', 50);
    //校验session的类型
    define('Us\\User\\SESSION_KEY', 0);     //用户session
    define('Us\\User\\TUBE_SESSION_KEY', 1);    //用户tube-session
    //用户状态
    define('Us\\User\\STATUS_NORMAL', 0);
    //发送验证码信息文字
    define('Us\\User\\CAPTCHA_MESSAGE', 'us验证码:');
    //可解绑数
    define('Us\\User\\UNLINK_NUM', 2);
    //密码加密次数
    define('Us\\User\\ENCRYPT_NUM', 1000);
    //邀请状态
    define('Us\\User\\JOIN_OK', 1);
    define('Us\\User\\HAVE_JOIN', 2);
    define('Us\\User\\JOIN_FALSE', 3);
    define('Us\\User\\TOURISTS', 4);
    
    define('Us\\Config\\Database\\DBNAME', 'us');
    //表名
    define('Us\\TableName\\USER', 'us.user');
    define('Us\\TableName\\USER_LOGIN', 'us.user_login');
    define('Us\\TableName\\USER_CONFIG', 'us.user_config');
    define('Us\\TableName\\SYSTEM_CODE', 'us.system_code');
    define('Us\\TableName\\CAPTCHA', 'us.captcha');
    define('Us\\TableName\\USER_DEVICE', 'us.user_device');
    define('Us\\TableName\\USER_DEVICE_HISTORY', 'us.user_device_history');
    define('Us\\TableName\\EVENT', 'us.event');
    define('Us\\TableName\\EVENT_LIVE', 'us.event_live');
    define('Us\\TableName\\EVENT_MOMENT', 'us.event_moment');
    define('Us\\TableName\\EVENT_USER', 'us.event_user');
    define('Us\\TableName\\MOMENT_PICTURE', 'us.moment_picture');
    define('Us\\TableName\TUBE_USER_EVENT', 'us.tube_user_event');
    define('Us\TableName\US_GROUP', 'us.us_group');
    define('Us\TableName\TUBE_GROUP_MEMBERSHIP',"us.tube_group_membership");
    define('Us\TableName\TUBE_GROUP_EVENT',"us.tube_group_event");
    define('Us\\TableName\\USER_RECORD_PLATFROMID',"us.user_record_platfromid");
    define('Us\\TableName\\EVENT_REPORT', Us\Config\Database\DBNAME . '.report');
    define('Us\\TableName\\MOMENT_PRAISE', Us\Config\Database\DBNAME . '.moment_praise');
    define('Us\\TableName\\SPREAD_CHANNEL_STAT', Us\Config\Database\DBNAME . '.spread_channel_stat');
    define('Us\\TableName\\SPREAD_SUB_CHANNEL', Us\Config\Database\DBNAME . '.spread_sub_channel');
    define('Us\\TableName\\SPREAD_MAIN_CHANNEL', Us\Config\Database\DBNAME . '.spread_main_channel');
    define('Us\\TableName\\MOMENT_COMMENT', Us\Config\Database\DBNAME . '.moment_comment');
    define('Us\\TableName\\MOMENT_LIKE', Us\Config\Database\DBNAME . '.moment_like');
    define('Us\\TableName\\TARGET_PUSH', Us\Config\Database\DBNAME . '.target_push');
    define('Us\\TableName\\TAO_ASSOCIATION_STORE', Us\Config\Database\DBNAME . '.tao_association_store');
    define('Us\\TableName\\TAO_OBJECT_STORE', Us\Config\Database\DBNAME . '.tao_object_store');
    define('Us\\TableName\\GROUP_USER', Us\Config\Database\DBNAME . '.group_user');
    
    //前端URL常量
    define('Us\\APP_URL_PREFIX', 'http://app.himoca.com');
    define('Us\\APP_URL', 'http://app.himoca.com');
    
    //后台
    //OSAdmin常量
    define('Console\\ADMIN_URL', 'http://app.himoca.com:9982/');
    define('Console\\ADMIN_TITLE', 'Us.管理后台');
    define('Console\\COMPANY_NAME', '北京聚说科技有限公司');
    //COOKIE加密密钥
    define('Console\\ADMIN\\ENCRYPT_KEY', 'comeonusyoubest!');
    
    //页面设置
    define('Console\\ADMIN\\DEBUG' ,false);
    define('Console\\ADMIN\\PAGE_SIZE', 25 );
    define('Console\\ADMIN\\SUCCESS', '操作成功');
    define('Console\\ADMIN\\ERROR', '操作失败，服务器异常');
    define('Console\\ADMIN\\BE_PAUSED', '您被封停，请联系管理员');
    define('Console\\ADMIN\\USER_OR_PWD_WRONG', '用户名或密码错误');
    define('Console\\ADMIN\\SUCCESS_NEED_LOGIN', '操作成功，部分功能需要用户重新登录才可使用');
    
    //MiPush configuration
    define('Us\\MI_PUSH\\IOS_SECRET', 'NQAEPlJjAw8RasbftVhWpQ==');
    define('Us\\MI_PUSH\\IOS_BUNDLE_ID', "com.hoolai.us");
    define('Us\\MI_PUSH\\ANDROID_SECRET', '/zQlEWfUzZcahDqxrV2Irg==');
    define('Us\\MI_PUSH\\ANDROID_BUNDLE_ID', 'com.hoolai.us');
    
    //验证码开关
    define('Us\\ENABLED\\SEND_CAPTCHA', 1);                 //发送验证码开关；1-开0-关
    define('Us\\ENABLED\\VERIFY_CAPTCHA', 1);                 //验证验证码开关；1-开0-关
    
    // Redis for Tube
    define('Us\\Config\\Tube\\Redis\\HOSTNAME', '10.143.76.120');
    define('Us\\Config\\Tube\\Redis\\PORT', 7379);
    define('Us\\Config\\Tube\\Redis\\TIMEOUT', 1);
    define('Us\\Config\\Tube\\Redis\\RETRY_INTERVAL', 100);
    define('Us\\Config\\Tube\\Redis\\AUTH', '8354821a0d2b5b895386302e3e875de2fd2bec8ce7a56a8e8763fff8310e9190cc536903bf7dd6be45a169dc7ec90e77a9db51bf9a079a440a7c0feecdb20096');
    
    // Redis for Tao
    define('Us\\Config\\Tao\\Redis\\HOSTNAME', '10.143.76.120');
    define('Us\\Config\\Tao\\Redis\\PORT', 7381);
    define('Us\\Config\\Tao\\Redis\\TIMEOUT', 1);
    define('Us\\Config\\Tao\\Redis\\RETRY_INTERVAL', 100);
    define('Us\\Config\\Tao\\Redis\\AUTH', '55df80e6a6fbd58cc618559eeafdfa7147e0867dcf73e1e1136d8fd22d0786188be36342daffe90bc410cb02cd7a602941970ecc4ddad687426678cf66dc1b34');
    
    // Tencent COS credentials and configs
    define('Us\\Config\\QCloud\\APP_ID', '10006628');
    define('Us\\Config\\QCloud\\SECRET_ID', 'AKIDwBwXfOISF1LWSoaoqCuCS2cRCwzhCIvk');
    define('Us\\Config\\QCloud\\SECRET_KEY', 'LI5IrAYcMPHMDyrA02tRqSQyckV8yPvB');
    define('Us\\Config\\QCloud\\USER_ID', '342123045');
    define('Us\\Config\\QCloud\\BUCKET', 'uspic');
    define('Us\\Config\\QCloud\\COS_USER_AGENT', 'tencent-httputils/1.1');
    define('Us\\Config\\QCloud\\COS_UPLOAD', 'http://web.file.myqcloud.com/files/v1/');
    define('Us\\Config\\QCloud\\COS_SIGN_EXPIRE', 20);
    define('Us\\Config\\QCloud\\TENCENT_UPLOAD_SOURCE', 0); //0:YOUTU, 1:FACE++
    define('Us\\Config\\MIPUSH_NODE', "push.mi");
    define('Us\\Config\\AVATAR_NODE', "profile.avatar");
    
    //about group for register
    define('Us\\REGISTER\\DEFAULT_GROUP', '家人,朋友');
    define('Us\\REGISTER\\DEFAULT_COVERPAGE', 'family,friend');
    
    //统计路径
    define('Us\\Path\\WRITE_CLIENT_LOG', '/usr/local/nginx/logs/stat/');    //日志写入路径
    define('Us\\Path\\READ_LOG', '/usr/local/nginx/flume/');    //日志读取路径
    
    //默认图替换
    define('Us\\Config\\FORWARD_PROFILE_COVERPAGE_PICTURE', "default");
    define('Us\\Config\\FORWARD_EVENT_COVERPAGE_PICTURE', "default");
    define('Us\\Config\\FORWARD_GROUP_COVERPAGE_PICTURE', "default");
    define('Us\\Config\\FORWARD_GROUP_COVERPAGE_FRIEND', "default");
    define('Us\\Config\\FORWARD_GROUP_COVERPAGE_FAMILY', "default");
    
    // rabbitmq
    define('Us\\Config\\RabbitMQ\\HOSTNAME', '10.104.10.154');
    define('Us\\Config\\RabbitMQ\\PORT', 5672);
    define('Us\\Config\\RabbitMQ\\USERNAME', 'us-devel');
    define('Us\\Config\\RabbitMQ\\PASSWORD', 'ca604d1d5b65bc3451e2d382c93e2e3ed2fdd457006b6731bdcffbea1dccae7b');
    define('Us\\Config\\RabbitMQ\\VHOST', '/us/devel');
?>
