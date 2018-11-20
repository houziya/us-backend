<?php
define("APP_PATH", realpath(dirname(__FILE__) . '/../../../'));
require APP_PATH . '/application/tests/TestCase.php';
require APP_PATH . '/application/tests/library/Http.php';
use Yaf\Request\Simple;
use yii\db\Query;

class MomentTest extends TestCase
{
    /* url 访问配置 */
    const  URL_PORT = 'http://app.himoca.com:9987/Us/';
    const LOGIN_URL = self::URL_PORT.'User/';
    const URL = self::URL_PORT.'event/';
    const MOMENT_URL = self::URL_PORT.'Moment/';
    const SYSTEM_URL = self::URL_PORT.'System/';
    const REPORT_URL = self::URL_PORT.'Report/';
    const TUBE_URL = self::URL_PORT.'Tube/';

    /* 测试用户相关配置 */
    const USER_CARD = '1016';
    const USER_TOKEN = 'owYrpt8mItzoLCdPJtf3tF0aHQ4Y';
    const USER_DEV = 'd41d8cd98f00b204e9800998ecf8427e3a7b1c9d';

    /* 断言配置 */
    const EVENT_STATUS_NORMAL = '0';
    const SUCCESS_STATUS = '200';
    const basePath = 'http://uspic-10006628.file.myqcloud.com/';
    const TUBE_TYPE = 1;

    /* 照片相关 */
    const NORMAL_PIC = '6356-13923-88d5e54c-71ed-469b-b1b1-65884de523a7';
    const UPLOAD_PIC_NUM = '1';
    const DEL_PIC_NUM = '0';
    const UPDATE_PIC_TIME = 1451606400;
    const COMMENT_CONTENT = 'new unitTest!';
    const COMMENT_TYPE = 0;
    const LIKE_TYPE = 0;

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
    public function testLogin()
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
        return $moment;
    }

    /**
     * @depends clone testLogin
     * @depends clone testCreate
     * @depends clone testCreateMoment
     */
    public function testUploadPicture($userInfo, $event, $moment)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'moment_id' => $moment['p']['moment_id'],
            'size' => '3024x4032',
            'shoot_time' => time()
            ];
        $uploadPic = json_decode(Http::sendPost(self::URL.'uploadPicture', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $uploadPic['c']);
        return $uploadPic;
    }

    /**
     * @depends clone testLogin
     * @depends clone testCreate
     * @depends clone testCreateMoment
     * @depends clone testUploadPicture
     */
    public function testCommitPicture($userInfo, $event, $moment, $uploadPic)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'moment_id' =>$moment['p']['moment_id'],
            'picture_id' => $uploadPic['p']['picture_id']
            ];
        $commitPic = json_decode(Http::sendPost(self::URL.'commitPicture', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $commitPic['c']);
        return $commitPic;
    }

    /**
     * @depends clone testLogin
     * @depends clone testCreate
     * @depends clone testCreateMoment
     * @depends clone testUploadPicture
     */
    public function testCommit($userInfo, $event, $moment, $uploadPic)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'moment_id' =>$moment['p']['moment_id'],
            'picture_ids' => $uploadPic['p']['picture_id']
            ];
        $commit = json_decode(Http::sendPost(self::URL.'commit', array_merge($data, self::$iniData)), true);
        /* 模拟上传正常图片 -start-*/
        $connection = Yii::$app->db;
        $connection->createCommand()->update('moment_picture', ['object_id'=>self::NORMAL_PIC,'status'=>0], ['id'=>$uploadPic['p']['picture_id']])->execute();
        /* -end- */ 
        $this->assertEquals(self::SUCCESS_STATUS, $commit['c']);
        $this->assertEquals(self::UPLOAD_PIC_NUM, $commit['p']['upload_count']);
        return $commit;
    }

    /**
     * @depends clone testLogin
     * @depends clone testUploadPicture
     */
    public function testPictureReport($userInfo, $uploadPic)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'picture_id' => $uploadPic['p']['picture_id']
            ];
        $picReport = json_decode(Http::sendPost(self::REPORT_URL.'pictureReport', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $picReport['c']);
    }

    /**
     * @depends clone testLogin
     * @depends clone testCreate
     * @depends clone testCreateMoment
     * @depends clone testUploadPicture
     */
    public function testPictureTimeUpdate($userInfo, $event, $moment, $uploadPic)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'modified' => urlencode(json_encode([['create_date'=>self::UPDATE_PIC_TIME, 'type'=>0, 'moment_id'=>$moment['p']['moment_id'], 'picture_id'=>$uploadPic['p']['picture_id']]]))
            ];
        $picTimeUpdate = json_decode(Http::sendPost(self::URL.'pictureTimeUpdate', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $picTimeUpdate['c']);
    }

    /**
     * @depends clone testLogin
     */
    public function testDynamicList(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key']
            ];
        $list = json_decode(Http::sendPost(self::MOMENT_URL.'list', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $list['c']);
        foreach ($list['p']['list'] as $item) {
            /* 断言头像是否存在  */
            $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($item['avatar']));
            /* 断言封面图是否存在  */
            $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($item['event_cover_page']));
            foreach($item['picture'] as $flag) {
                $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($flag['url']));   //断言活动图片存在性
            }
        }
        return $list;
    }

    /**
     * @depends clone testLogin
     */
    public function testMyMoment(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key']
            ];
        $myMoment = json_decode(Http::sendPost(self::MOMENT_URL.'myMoment',array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $myMoment['c']);
        foreach ($myMoment['p']['list'] as $item) {
            /* 断言头像是否存在  */
            $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($item['avatar']));
            /* 断言封面图是否存在  */
            $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($item['event_cover_page']));
            /* 断言是否是自己的动态 */
            $this->assertEquals(self::USER_CARD, $item['uid']);
            foreach($item['picture'] as $flag) {
                $this->assertEquals(self::SUCCESS_STATUS, self::doCurlRequest($flag['url']));   //断言活动图片存在性
            }
        }
    }

    /**
     * @depends clone testLogin
     */
    public function testTube(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key']
        ];
        $tube = json_decode(Http::sendPost(self::TUBE_URL.'pull', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $tube['c']);
    }

    /**
     * @depends clone testLogin
     * @depends clone testDynamicList
     */
    public function testTaoLike($userInfo, $list)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $list['p']['list'][0]['event_id'], //赞最新的活动
            'moment_id' => $list['p']['list'][0]['moment_id'], //赞最新活动下的动态
            'type' => self::LIKE_TYPE
            ];
        $like = json_decode(Http::sendPost(self::MOMENT_URL.'taoLike', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $like['c']);
    }

    /**
     * @depends clone testLogin
     * @depends clone testDynamicList
     */
    public function testTaoCreateComment($userInfo, $list)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $list['p']['list'][0]['event_id'], //评论最新的活动
            'moment_id' => $list['p']['list'][0]['moment_id'], //评论最新活动下的动态
            'to_uid' => self::USER_CARD,
            'content' => self::COMMENT_CONTENT
            ];
        $createComment = json_decode(Http::sendPost(self::MOMENT_URL.'taoCreateComment', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $createComment['c']);
        return $createComment;
    }

    /**
     * @depends clone testLogin
     * @depends clone testDynamicList
     * @depends clone testTaoCreateComment
     */
    public function testDeleteComment($userInfo, $list, $createMoment)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $list['p']['list'][0]['event_id'],
            'moment_id' => $list['p']['list'][0]['moment_id'],
            'comment_id' => $createMoment['p']['cid'],
            'to_uid' => self::USER_CARD,
            'type' => self::COMMENT_TYPE
            ];
        $deleteComment = json_decode(Http::sendPost(self::MOMENT_URL.'taoDeleteComment', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $deleteComment['c']);
    }
    
    /**
     * @depends clone testLogin 
     */
    public function testGetDomainInfo(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key']
            ];
        $mainInfo = json_decode(Http::sendPost(self::SYSTEM_URL.'GetDomainInfo', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $mainInfo['c']);
    }

    /**
     * @depends clone testLogin
     */
    public function testGetTemporaryToken(array $userInfo)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'type' => self::TUBE_TYPE
            ];
        $temporaryToken = json_decode(Http::sendPost(self::SYSTEM_URL.'getTemporaryToken', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $temporaryToken['c']);
    }

    /**
     * @depends clone testLogin
     * @depends clone testCreate
     * @depends clone testCreateMoment
     * @depends clone testUploadPicture
     */
    public function testPictureDelete($userInfo, $event, $moment, $uploadPic)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'moment_id' =>$moment['p']['moment_id'],
            'picture_id' => $uploadPic['p']['picture_id']
            ];
        $picDelete = json_decode(Http::sendPost(self::URL.'pictureDelete', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $picDelete['c']);
    }

    /**
     * @depends clone testLogin
     * @depends clone testCreate
     * @depends clone testCreateMoment 
     */
    public function testDelete($userInfo, $event, $createMoment)
    {
        $data = [
            'login_uid' => intval($userInfo['p']['uid']),
            'session_key' => $userInfo['p']['session_key'],
            'event_id' => $event['p']['event_id'],
            'moment_id' => $createMoment['p']['moment_id']
            ];
        $deleteMoment = json_decode(Http::sendPost(self::MOMENT_URL.'delete', array_merge($data, self::$iniData)), true);
        $this->assertEquals(self::SUCCESS_STATUS, $deleteMoment['c']);
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
