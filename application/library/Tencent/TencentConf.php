<?php
namespace Tencent;

class TencentConf
{
    const PKG_VERSION = '1.0.0'; 

    const API_IMAGE_END_POINT = 'http://web.image.myqcloud.com/photos/v1/';
    const API_VIDEO_END_POINT = 'http://web.video.myqcloud.com/videos/v1/';
    const API_COSAPI_END_POINT = 'http://web.file.myqcloud.com/files/v1/';
    //请到http://console.qcloud.com/cos去获取你的appid、sid、skey
    const APPID = \Us\Config\QCloud\APP_ID;
    const SECRET_ID = \Us\Config\QCloud\SECRET_ID;
    const SECRET_KEY = \Us\Config\QCloud\SECRET_KEY;
    const USERID = \Us\Config\QCloud\USER_ID;

    public static function getUA() {
        return 'QcloudPHP/'.self::PKG_VERSION.' ('.php_uname().')';
    }
}


//end of script
