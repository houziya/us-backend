<?php
use Yaf\Controller_Abstract;
use yii\db\Query;

class SystemController extends Controller_Abstract
{
    public function getTemporaryTokenAction()
    {
        $data = Protocol::arguments();
        if ($this->doVerifySession($data->requiredInt('login_uid'), $data->required('session_key'), Us\User\SESSION_KEY)) {
            switch ($data->requiredInt('type')) {
                case Us\User\TUBE_SESSION_KEY:
                    $session = explode(":", $this->doResetSession($data->requiredInt('login_uid'), Us\Config\TUBE_SESSION_EXPIRE, Us\User\TUBE_SESSION_KEY));
                    Protocol::ok(['tube_session' => $session[0], 'tube_key' => $session[1], 'expire' => Us\Config\TUBE_SESSION_EXPIRE]);
                    break;
                default:
                    Protocol::badRequest();
            }
        } else {
            Protocol::unauthorized(NULL, Notice::get()->unauthorized());
        }
    }

    public function getDomainInfoAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        $response = [
            'init_domain' => Us\Config\INIT_DOMAIN,
            'upload_domain' => Us\Config\UPLOAD_DOMAIN,
            'download_domain' => Us\Config\DOWNLOAD_DOMAIN,
            'spread_service' => explode(',', Us\Config\SPREAD_DOMAIN),
            'tube_service' => explode(',', Us\Config\TUBE_DOMAIN),
            'flags' => Us\Config\FLAG,
            'log_level' => Us\Config\LOG_LEVEL
        ];
        if (Protocol::required('platform') == '0' && Protocol::required('version') > Us\Config\IOS_CURRENT_VERSION) {
            $response["flags"] = Us\Config\REVIEW_FLAG;
        }
        if (Protocol::required('platform') == '0' && Protocol::required('version') == 9) {
            $response["js_patch"] = Us\APP_URL_PREFIX . '/js_patch.txt';
        }
        if (Protocol::required('version') == 14) {
            $response["js_patch"] = Us\APP_URL_PREFIX . '/patch/patch_14.js';
        }
        if (Protocol::optional('device_id', 0)) {
            AdClick::activate(Protocol::optional('device_id'));
        }
        Protocol::ok($response);
    }

    private function doVerifySession($uid, $session, $type)
    {
        switch ($type) {
            case Us\User\SESSION_KEY:
                return Session::verify($uid, $session);
                break;
            case Us\User\TUBE_SESSION_KEY:
                return Session::verifyTube($uid, $session);
                break;
            default:
                throw new InvalidArgumentException('Invalid registration type '. $type);
        }
    }

    private function doResetSession($uid, $expire, $type)
    {
        switch ($type) {
            case Us\User\SESSION_KEY:
                return Session::reset($uid, $expire);
            case Us\User\TUBE_SESSION_KEY:
                return Session::resetTube($uid, $expire);
            default:
                throw new InvalidArgumentException('Invalid registration type '. $type);
        }
        return new stdClass();
    }

    public function pushConfigAction() 
    {
        $response = [];
        $cfg = [
            "til" => ["fee9458c29cdccf10af7ec01155dc7f0"],
//             "edf" => 1,
            "etl" => $this->doReadJsonFile("/conf/event_layout.json"),
            "etd" => $this->doReadJsonFile("/conf/event_default_data.json"),
            "pbf" => [
                "message" => "使用Us将照片分享给朋友吧！",
                "enable" => 0
            ],
            "gsme" => Us\Config\LIMIT_EVENT,                       //添加已经故事到小组上限
            "ssmp" => 12,                                          //分享故事选择照片上限
            "pdsd" => 1000,      //照片选择器中的时间比较的毫秒精度
        ];
        //$response['cfg'] = Push::zk("/moca/spread/subscription/cfg", json_encode($cfg));
        
        $sysinfo_ios = [
               "app_info" => [
                       "type" => 1,
                       "code" => 14,
                       "version" => "1.2.0",
                       "desc" => "1.2.0新春版上线，新增小组功能",
               ],
//                "banner_info" => [
//                        "enable_version" => ["14", "15", "16"],
//                        "img_url" => "images/splash/honda-accord.jpg",
//                    ],
               "guide_link" => [
                    "party" => "http://us-api.himoca.com/share/specialshare.html?invitation_code=BE2B4&target=share",
                    "travel" => "http://us-api.himoca.com/share/specialshare.html?invitation_code=015BD&target=share",
                    "wedding" => "http://us-api.himoca.com/share/specialshare.html?invitation_code=5D95A&target=share"
                ],
//                "launch_info" => [
//                        "img_4_url" => "images/splash/3ac3e2c7-66d9-480c-aab7-35b15c8660ee.jpg",
//                        "img_5_url" => "images/splash/1ed7af9a-188e-4909-8421-399b6e09d9c2.jpg",
//                        "img_6_url" => "images/splash/1ed7af9a-188e-4909-8421-399b6e09d9c2.jpg",
//                        "img_6p_url" => "images/splash/1ed7af9a-188e-4909-8421-399b6e09d9c2.jpg",
//                        "action" => [
//                                "type" => 1,
//                                "url" => "http://us.himoca.com",
//                                "title" => "launch",
//                            ],
//                         "duration" => "3",
//                         "can_skip" => 0,
//                     ],
 
        ];
        //$response['sysinfo_ios'] = Push::zk("/moca/spread/subscription/sysinfo.ios", json_encode($sysinfo_ios));
        Protocol::ok($response);
    }

    public function pushAndroidConfigAction()
    {
        $sysinfo_android = [
            "app_info" => [
                "type" => 1,
                "code" => "1,2,3,4,5,6",
                "version" => "1.2.0",
                "desc" => "1.2.0新春版上线，新增小组功能",
                "app_url" => "http://us.himoca.com/apk/moca_us.apk"
            ],
            
            "launch_info" => [
                "enable_version" => [1, 2, 3, 4, 5, 6],
                "img_url" => "images/splash/3ac3e2c7-66d9-480c-aab7-35b15c8660ee.jpg",
                "action" => [
                    "type" => 1,
                    "url" => "http://us.himoca.com",
                    "title" => "launch",
                    ],
                "duration" => "3",
                "can_skip" => 0,
            ],
        ];
        //$response['sysinfo_android'] = Push::zk("/moca/spread/subscription/sysinfo.android", json_encode($sysinfo_android));
        Protocol::ok($response);
    }

    private function doReadJsonFile($fileName)
    {
        if (empty($fileName)) {
            return false;
        }
        $jsonFile = APP_PATH . $fileName;
        $template = trim(stripslashes(file_get_contents($jsonFile)));
        $template = preg_replace("/\s/","",$template);
        return $template;
    }
    
}
