<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
use yii\db\Exception;
use yii\log\Logger;

class TestController extends Controller_Abstract
{
    static private  $parameter = [];
    static private $api = ["Moment/praise","Moment/list","Event/create","Event/momentList","Event/modify","Event/detail","Event/pictureDelete"];
    public function testAction() {
        echo 123;
        Event::AddEvent("1191", "520ED12");
        die;
        MiPush::addEventSendMessage("2555", ["1191"], "addEvent", "US");
        die;
        $rerultArray = [];
        $url = 'http://119.29.77.36:9990/Us/Moment/praise?device_id=fe0cc0a1d284cf70bf55fd9a657efcff37f29ba5&login_uid=1199&distributor=app_store&event_id=5823&moment_id=10468&platform=0&session_key=67ab9f7ed42bfcd8bb094de4ebbeb6a74c51d9e1&time=1450146183551&type=0&version=9&version_api=100';
        foreach (self::$api as $subApi) {
            $parame = "?device_id=fe0cc0a1d284cf70bf55fd9a657efcff37f29ba5&login_uid=1199&platform=0&session_key=67ab9f7ed42bfcd8bb094de4ebbeb6a74c51d9e1";
            $url = Console\ADMIN_URL."Us/".$subApi.$parame;
            $rerult = $this->httpCurl($url);
            $rerultArray[$subApi] = $rerult;
        }
        print_r($rerultArray);
    }
    
    public function pushAction() {
        $connection = Yii::$app->db;
        $sql = "select event_id, member_uid, receive_push_time from event_user where receive_push_time != '' order by event_id, create_time";
        $command = $connection->createCommand($sql);
        $members = $command->queryAll();
        foreach ($members as $member) {
            if((time() - strtotime($member['receive_push_time'])) >= 60*10) {
                $pushSql = "select member_uid,receive_push_time from event_user where create_time >= '".$member['receive_push_time']."' and event_id = ".$member['event_id'];
                $pushCommand = $connection->createCommand($pushSql);
                $pushMembers = $pushCommand->queryAll();
                if(Predicates::isNotEmpty($pushMembers)) {
                    $pushCount = count($pushMembers);
                    $pushNickname = User::getUserNickname($pushMembers[0]['member_uid']);
                    $eventName = Event::GetEventInfoByEvent($member['event_id'], "name");
                    $desc = $pushNickname."等".$pushCount."人已加入您的活动'".$eventName."'";
                    MiPush::addEventSendMessage($member['event_id'], [$member['member_uid']], "addEvent", "Us", $desc);
                    Event::doTableUpdate(Event::$tableEventUser, ['receive_push_time' => null], "member_uid = :uid and event_id = :event_id", [":uid" => $member['member_uid'], ":event_id" => $member['event_id']]);
                }
                unset($pushMembers);
                unset($pushNickname);
                unset($pushSql);
                unset($eventName);
            }
        }
    }
    
    public function praiseAction() {
        $this->$parameter = [];
        //$url = 'http://119.29.77.36:9990/Us/Moment/praise?device_id=fe0cc0a1d284cf70bf55fd9a657efcff37f29ba5&login_uid=1199&distributor=app_store&event_id=5823&moment_id=10468&platform=0&session_key=67ab9f7ed42bfcd8bb094de4ebbeb6a74c51d9e1&time=1450146183551&type=0&version=9&version_api=100';
        $url = Console\ADMIN_URL."";
    }
    private function httpCurl($url) {
        // 1. 初始化
        $ch = curl_init();
        // 2. 设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // 3. 执行并获取HTML文档内容
        $result = curl_exec($ch);
        // 4. 释放curl句柄
        curl_close($ch);
        return $result;
    }
    //硕士地睚课时是详加少许半 
}
?>