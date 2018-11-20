<?php 
class SMS
{
    private static function doGetCaptchaCode($phone)
    {
    	$code = Yii::$app->redis->get(Us\User\CAPTCHA_PHONE.$phone);
    	$num = Yii::$app->redis->get(Us\User\CAPTCHA_FAILED.$phone);
    	if(!$num && !$code) {
    	    $code = rand(1000, 9999);
    	}
    	else if($num && !$code) {
    	    $code =  rand(1000, 9999);
    	}
    	Yii::$app->redis->set(Us\User\CAPTCHA_PHONE.$phone, $code);
    	Yii::$app->redis->expire(Us\User\CAPTCHA_PHONE.$phone, (Us\User\HOUR)/2);
    	return $code;
    }

    private static function doSendCaptcha($code, $phone)
    {
        $productId = Us\Config\SMS_PRODUCT_ID;
        $templateId = Us\Config\SMS_TEMPLATE_ID;
        $productKey = Us\Config\SMS_PRODUCT_KEY;
        $requestTime = time() * 1000;
        $accessToken = md5($productId.".".$requestTime.".".$productKey);

        $url = Us\Config\SMS_URL;
        $url .= "params=".$code."&productId=".$productId."&mobile=".$phone."&templateId=".$templateId."&accessToken=".$accessToken."&requestTime=".$requestTime;

        $start_time = date("Y-m-d H:i:s");
        $result = file_get_contents($url);
        $end_time = date("Y-m-d H:i:s");
        
        $resArr = json_decode($result,true);
        if($resArr['code']=="SUCCESS"){
            $result = [
                'start_time' => $start_time,
                'end_time' => $end_time,
            ];
            return $result;
        }
        return false;
    }

    private static function doStoreCaptcha($data){
        if( empty($data) ){
            //加日志
        	return false;
        }
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\CAPTCHA, [
                'phone' => $data['phone'],
                'code' => $data['code'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'channel' => $data['channel'],
                'message' => $data['message'],
                'type' => $data['type']
        ])->execute();
        return $res;
    }
    /*
     * 国际国内短信发送
    */
    public static function sms_all_send($sp_name,$phone,$message,$channel,$send_type=0)
    {
        $code = self::doGetCaptchaCode($phone);
        //报警短信
        if ($type==110) {
            $code = 110;
        }
        switch ($sp_name) {
        	case "hoolai":
        	    $data = self::doSendCaptcha($code, $phone);
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $sp_name);
        }
        if(empty($data)){
            Protocol::ok(null, null, null, Notice::get()->tooManyCaptchaPort());
        	return false;
        }
        $data['phone'] = $phone;
        $data['code'] = $code;
        $data['channel'] = $sp_name;
        $data['type'] = $send_type;
        $data['message'] = $message.$code;
        if(self::doStoreCaptcha($data)) {
    		return true;
        }
        return false;
    }
}
?>
