<?php
define("APP_PATH", realpath(dirname(__FILE__) . '/../../../'));
require APP_PATH . '/application/tests/TestCase.php';
require APP_PATH . '/application/tests/library/Http.php';
use Yaf\Request\Simple;
use yii\db\Query;

class ActivityTest extends TestCase
{
    /* url 访问配置 */
    const  URL_PORT = 9987;
    const URL = 'http://app.himoca.com:'.self::URL_PORT.'/Us/event/';
    const LOGIN_URL = 'http://app.himoca.com:'.self::URL_PORT.'/Us/User/';

    /* 测试用户相关配置 */
    const USER_CARD = '1016';
    const USER_TOKEN = 'owYrpt8mItzoLCdPJtf3tF0aHQ4Y';
    const USER_DEV = 'd41d8cd98f00b204e9800998ecf8427e3a7b1c9d';
    
    /* 断言配置 */
    const EVENT_STATUS_NORMAL = '0';
    const SUCCESS_STATUS = '200';
    const basePath = 'http://uspic-10006628.file.myqcloud.com/';
    
    private static  $iniData = [
        'device_id' => self::USER_DEV,
        'client_version' => 9,
        'distributor' => 'app_store',
        'os_version' => '9.2',
        'phone_model' => 'iPhone6,2',
        'platform' => 0,
        'version' => 1,
        'version_api' => 100,
    ];
    
    /**
     * user/login
     */
    public function testLogin ()
    {
        $data = [
            'secret' => '50b944294e6fb3edb35b30ab04a70a24d6b539ec',
            'time' => time(),
            'token' => self::USER_TOKEN,
            'type' => 3,
            ];
        $userInfo = json_decode(Http::sendPost(self::LOGIN_URL.'login', array_merge($data, self::$iniData)), true);
        $this->assertTrue($userInfo['p']['result']);
        $this->assertEquals(self::USER_CARD, $userInfo['p']['uid']);
        return $userInfo;
    }
    
    /**
     * @depends clone testLogin
     */    
    public function testCreate(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'start_time' => time(),
            'title' => 'new event'.substr(uuid_create(), 0, 11),
            ];
        $event = json_decode(self::sendPost(self::URL.'create', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $event['c']);
        $user = (new Query())->select('uid')->from('event')->where(['id' => $event['p']['event_id']])->one();
        $this->assertEquals(self::USER_CARD, $user['uid']);
        return $event;
    }
    
    /**
     * @depends clone testLogin
     */
    public function testList(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'start_time' => 1,
            'time' => time()
            ];
        $list = json_decode(self::sendPost(self::URL.'list', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $list['c']);
        $this->assertArrayHasKey('invite', $list['p']);
        foreach ($list['p']['list'] as  $item) {
            $this->assertEquals(self::EVENT_STATUS_NORMAL, $item['status']);
        }
        return $list;
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testModify($userInfo, $event) 
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'title' => 'After'.substr(uuid_create(), 0, 13)
            ];
        $modifyAfter = json_decode(self::sendPost(self::URL.'modify', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $modifyAfter['c']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testCreateMoment($userInfo, $event)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id']
            ];
         $moment = json_decode(self::sendPost(self::URL.'CreateMoment', array_merge($data, self::$iniData)), true);
         $this->assertEquals(self::SUCCESS_STATUS, $moment['c']);
         $momentInfo = (new Query())->select('uid, event_id')->from('event_moment')->where(['id' => $moment['p']['moment_id']])->one();
         $this->assertEquals(self::USER_CARD, $momentInfo['uid']);
         $this->assertEquals($event['p']['event_id'], $momentInfo['event_id']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testDetail($userInfo, $event)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'invitation_code' => substr(strrchr($event['p']['invite_link'], "/"), 1),
            'tag' => 'invite'
        ];
        $detail = json_decode(self::sendPost(self::URL.'detail', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $detail['c']);
        $detailInfo = (new Query())
            ->select('uid')
            ->from('event')
            ->where(['id' => $detail['p']['event_id'], 'invitation_code'=> $detail['p']['invitation_code']])
            ->one();
        $this->assertEquals(self::USER_CARD, $detailInfo['uid']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testMomentList($userInfo, $event)
    {
          $data = [
              'login_uid' => intval($userInfo['p']['uid']),
              'session_key' => $userInfo['p']['session_key'],
              'event_id' => $event['p']['event_id']
            ];
          $momentList = json_decode(self::sendPost(self::URL.'momentList', array_merge($data, self::$iniData)), true);
          /* 断言接口调用是否成功 */
          $this->assertEquals(self::SUCCESS_STATUS, $momentList['c']);
          /* 断言活动封面图是否存在 */
          $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($momentList['p']['cover_page']));
          /* 断言活动所属人 */
          $this->assertEquals(self::USER_CARD, $momentList['p']['ci']);
          /* 断言长图是否存在 */
          //$this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($momentList['p']['image_link']));
          /* 断言有效成员头像是否存在 */
          foreach ($momentList['p']['member'] as $item) {
              $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($item['a']));
          }
          /* 断言活动成员图片是否存在*/
          if (!empty($momentList['p']['picture'])) {
            foreach ($momentList['p']['picture'] as $attr) {
                $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($attr['p']));
            }
          }
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testLockEvent($userInfo, $event)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id']
        ];
        $eventLock = json_decode(self::sendPost(self::URL.'lockEvent', array_merge($data, self::$iniData)), true);
        /* 断言接口调用是否成功 */
        $this->assertEquals(self::SUCCESS_STATUS, $eventLock['c']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate
     */
    public function testUnlockEvent($userInfo, $event)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id']
        ];
        $eventUnlock = json_decode(self::sendPost(self::URL.'unLockEvent', array_merge($data, self::$iniData)), true);
        /* 断言接口调用是否成功 */
        $this->assertEquals(self::SUCCESS_STATUS, $eventUnlock['c']);
    }
    
    /**
     * @depends clone testLogin
     * @depends clone testCreate 
     */
    public function testExit($userInfo, $event)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id']
        ];
        $exitEvent = json_decode(self::sendPost(self::URL.'exit', array_merge($data, self::$iniData)), true);
        /* 断言是否调用ok */
        $this->assertEquals(self::SUCCESS_STATUS, $exitEvent['c']);
        /*判断生成的长图是否存在 */
        //$this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($exitEvent['p']['image_link']));
        $this->assertNotEmpty($exitEvent['p']['image_link']);
    }
    
    private static function sendPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95Safari/537.36 SE 2.X MetaSr 1.0');
        $return = curl_exec($ch);
        curl_close($ch);
        return $return;
    }
    
    private static function doCurlRequest($category)
    {
        $ch = curl_init(self::basePath.$category);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch,  CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $info;
    }
}

?>