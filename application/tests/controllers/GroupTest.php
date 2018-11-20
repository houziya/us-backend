<?php
define("APP_PATH", realpath(dirname(__FILE__) . '/../../../'));
require APP_PATH . '/application/tests/TestCase.php';
require APP_PATH . '/application/tests/library/Http.php';
use Yaf\Request\Simple;
/**
 * 用户组测试类
 */
class GroupTest extends TestCase 
{
    const URL = 'http://app.himoca.com:9986/Us/User/';
    const GROUP_URL = 'http://app.himoca.com:9986/Us/Group/';
    const NAME = 'meishi';
    const UPDATE_NAME = 'name';
    const VALUE = 'player';
    const GID = '177660';
    const TARGET_ID = '1414';
    const USER_DEV = '863151023872416';
    private static  $iniData = [
        'device_id' => self::USER_DEV,
        'client_version' => 6,
        'distributor' => 'anzhi',
        'os_version' => '4.4.4',
        'phone_model' => 'mx3',
        'platform' => 1,
        'version' => 6,
        'version_api' => 100,
    ];
    /**
     * user/login
     */
    public function testLogin ()
    {
        $url = self::URL.'login';
        $data = array(
                        'secret' => 'Uftc0k5S4hdOVVJQ0y-cANoFWO86PCKVAo0e04d4CBDLX7AS4NlMyd0e0wp0l6pHJPO5QCSd7xGyAjBhNEEC55M7OVT7He4Az3NN6OgpSxQMAOgACASGU',
                        'time' => time(),
                        'token' => 'owYrpt4tSV_91scczesbXR9NU0lI',
                        'type' => 3,
                    );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertTrue($result['p']['result']);
        $this->assertEquals('1414', $result['p']['uid']);
        return $result; 
    }
    
    /**
     * @depends clone testLogin
     */
    public function testCreate (array $content)
    {
        $url = self::GROUP_URL.'create';
        $data = array(
                        'login_uid' => intval($content['p']['uid']),
                        'session_key' => $content['p']['session_key'],
                        'name' => self::NAME,
                );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('meishi', $result['p']['name']);
        return $result;
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testJoin (array $content, array $group)
    {
        $url = self::GROUP_URL.'join';
        $data = array(
            'login_uid' => self::TARGET_ID,
            'session_key' => $content['p']['session_key'],
            'gid' => $group['p']['id'],
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('200', $result['c']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testUpdateProfile (array $content, array $group)
    {
        $url = self::GROUP_URL.'updateProfile';
        $data = array(
            'login_uid' => intval($content['p']['uid']),
            'session_key' => $content['p']['session_key'],
            'gid' => $group['p']['id'],
            'target' => self::UPDATE_NAME,
            'value' => self::VALUE,
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals($group['p']['id'], $result['p']['id']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testExpel (array $content, array $group)
    {
        $url = self::GROUP_URL.'expel';
        $data = array(
            'login_uid' => intval($content['p']['uid']),
            'session_key' => $content['p']['session_key'],
            'gid' => $group['p']['id'],
            'target' => self::TARGET_ID,
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('200', $result['c']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testProfile (array $content, array $group)
    {
        $url = self::GROUP_URL.'profile';
        $data = array(
            'login_uid' => intval($content['p']['uid']),
            'session_key' => $content['p']['session_key'],
            'gid' => $group['p']['id'],
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('200', $result['c']);
    }
    
    /**
     * @depends clone testLogin
     */
    public function testLists (array $content)
    {
        $url = self::GROUP_URL.'lists';
        $data = array(
            'login_uid' => intval($content['p']['uid']),
            'session_key' => $content['p']['session_key'],
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('200', $result['c']);
        $this->assertNotEmpty($result['p']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testQuit (array $content, array $group)
    {
        $url = self::GROUP_URL.'quit';
        $data = array(
            'login_uid' => intval($content['p']['uid']),
            'session_key' => $content['p']['session_key'],
            'gid' => $group['p']['id'],
        );
        $result = json_decode(Http::sendPost($url, array_merge($data, self::$iniData)), true);
        $this->assertEquals('200', $result['c']);
    }
    
}
