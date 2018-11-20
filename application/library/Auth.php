<?php 
use yii\db\Query;

class Auth
{
    public static function verifyDeviceStatus($data)
    {
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $sessionKey = $data->required('session_key');//当前session_key
        $deviceId = $data->required('device_id');//设备id
        $GetSessionKey = Session::getSession($loginUid); //redis中获取session_key
        $GetDeviceId = Yii::$app->redis->get(Us\User\US_DEVID.$loginUid); //redis中获取device_id
        $payload = new stdClass();
        if (!empty($GetSessionKey)) {
            if ($sessionKey != $GetSessionKey) {
                if ($GetDeviceId != $deviceId) {
                    Protocol::temporaryRedirect($payload, '', 'device remote login');//307 账号其他设备登录
                    return false;
                } else {
                    Protocol::unauthorized($payload, '', 'device unauthorized');//401 失效
                    return  false;
                }
            }
        } 
        else {
                Protocol::unauthorized($payload, '', 'device unauthorized');//401 失效
                return  false;
        }
    }
} 
?>
