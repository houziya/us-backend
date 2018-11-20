<?php
define("APP_PATH", realpath(dirname(__FILE__) . '/../../../'));
require APP_PATH . '/application/tests/TestCase.php';
require APP_PATH . '/application/tests/library/Http.php';
use Yaf\Request\Simple;
/**
 * 首页控制器测试类
 */
class IndexTest extends TestCase 
{
    const URL = 'http://app.himoca.com:9984/Us/User/';
    
    private static  $iniData = [
                'device_id' => '2c568dc8ec0a64f143dc2af0bf55bf384afc9959',
                'client_version' => 1,
                'distributor' => 'app_store',
                'os_version' => '9.2',
                'phone_model' => 'iPhone5,2',
                'platform' => 0,
                'version' => 1,
                'version_api' => 100,
            ];
    /**
     * user/login
     */
    public function testLogin ()
    {
//         $request = new Yaf\Request\Simple("CLI", "Us", "User", "login", 
//                 array(
//                         'device_id' => '2c568dc8ec0a64f143dc2af0bf55bf384afc9959',
//                         'client_version' => 1,
//                         'distributor' => 'app_store',
//                         'os_version' => '9.2',
//                         'phone_model' => 'iPhone5,2',
//                         'platform' => 0,
//                         'secret' => '031a08c38b30a2a760b2b7bf0a59825L',
//                         'time' => time(),
//                         'token' => 'owYrptyjS_APzW3smCKd3hqWybMs',
//                         'type' => 3,
//                         'version' => 1,
//                         'version_api' => 100,
//                     )
//         );
//         $response = $this->_application->getDispatcher()
//                 ->returnResponse(true)
//                 ->dispatch($request)
//                 ->clearBody();
//         $content = json_decode(ob_get_contents(), true);
//         $this->assertTrue($content['p']['result']);
//         $this->assertEquals('1048', $content['p']['uid']);
//         return $content;
        $url = self::URL.'login';
        $data = array(
                        'secret' => '031a08c38b30a2a760b2b7bf0a59825L',
                        'time' => time(),
                        'token' => 'owYrptyjS_APzW3smCKd3hqWybMs',
                        'type' => 3,
                    );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertTrue($result['p']['result']);
        $this->assertEquals('1048', $result['p']['uid']);
        return $result;
    }
    
    /**
     * @depends clone testLogin
     */
    public function testGetProfile (array $content)
    {
//         $request = new Yaf\Request\Simple("CLI", "Us", "User", "getProfile",
//                 array(
//                         'device_id' => '2c568dc8ec0a64f143dc2af0bf55bf384afc9959',
//                         'client_version' => 1,
//                         'distributor' => 'app_store',
//                         'os_version' => '9.2',
//                         'phone_model' => 'iPhone5,2',
//                         'platform' => 0,
//                         'time' => time(),
//                         'version' => 1,
//                         'version_api' => 100,
//                         'login_uid' => intval($content['p']['uid']),
//                         'session_key' => $content['p']['session_key'],
//                         'uid' => 1049,
//                 )
//         );
//         $response = $this->_application->getDispatcher()
//         ->returnResponse(true)
//         ->dispatch($request);
//         $user_info = json_decode(ob_get_contents(), true);
//         var_dump($user_info);
        $url = self::URL.'getProfile';
        $data = array(
                        'login_uid' => intval($content['p']['uid']),
                        'session_key' => $content['p']['session_key'],
                        'uid' => 1049,
                );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('1049', $result['p']['user']['uid']);
    }
    
    /**
     * 更新个人资料
     * @depends clone testLogin
     */
    public function testUpdateProfile (array $content)
    {
        $url = self::URL.'updateProfile';
        $data = array(
                'login_uid' => intval($content['p']['uid']),
                'session_key' => $content['p']['session_key'],
                'nickname' => 'test',
            );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('test', $result['p']['nickname']);
    }
    
    /**
     * 修改开关
     * @depends clone testLogin
     */
    public function testUpdateConfig (array $content)
    {
        $url = self::URL.'updateConfig';
        $data = array(
                'login_uid' => intval($content['p']['uid']),
                'session_key' => $content['p']['session_key'],
                'setting' => json_encode(['invite_enabled' => 1]),
                'type' => 0,
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('1', $result['p']['invite_enabled']);
    }
}
