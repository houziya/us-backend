<?php
spl_autoload_register(function($class){
    $dir = dirname(__FILE__);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    include($dir.DIRECTORY_SEPARATOR.$class);
});

use MiPush\IOSBuilder;
use MiPush\Sender;
use MiPush\Constants;
use MiPush\Stats;
use MiPush\Tracer;
use MiPush\Builder;
use MiPush\TargetedMessage;
class MiPush 
{
    const TO_COMMENT = 0;  //评论
    const REPLY_COMMENT = 1; //回复评论
    const LIKE = 2; //赞
    const JOINGROUP = 2;//加入小组
    const EVENTINTOGROUP = 1;//小组添加或创建活动
    const INVITEJOINGROUP = 3;//通过邀请进小组
    const INVITEJOINEVENT = 4;//通过邀请进故事
    const JOINEVENT = 5;//加入故事
    const CREATEMOMENT = 6;//创建动态，传图
    const COMMENT = 7;//评论赞
    
    public static function sendMessage ($users, $payload, $desc, $title = '')
    {
        if (!is_array($users)) {
            return false;
        }
        $userList = implode(',', $users);
        $query = new Query;
        $list = $query->select('uid, platform')
        ->from(Us\TableName\USER_DEVICE)
        ->where("uid in ($userList) ")
        ->all();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            if ($val['platform'] == 0) {
                $iosUser[] = $val['uid'];
            }
            if ($val['platform'] == 1) {
                $andRoidUser[] = $val['uid'];
            }
        }
        if ($iosUser) {
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            self::_android($andRoidUser, $payload, $desc);
        }
        return true;
    }
    
    private static function _ios ($aliasList, $payload, $desc, $sound = 'default')
    {
        Constants::setBundleId(Us\MI_PUSH\IOS_BUNDLE_ID);
        Constants::setSecret(Us\MI_PUSH\IOS_SECRET);
        Constants::useOfficial();
        $message = new IOSBuilder();
        $message->description(self::emojiToUnicode($desc));
        $message->soundUrl('sms_circles.caf');
        $message->badge('1');
        $message->extra('payload', json_encode($payload));
        $message->build();
        $sender = new Sender();
        $sender->sendToAliases($message,$aliasList)->getRaw();
    }
    
    private static function _android ($aliasList, $payload, $desc, $title = '',$sound = 'default')
    {
        Constants::setPackage(Us\MI_PUSH\ANDROID_BUNDLE_ID);
        Constants::setSecret(Us\MI_PUSH\ANDROID_SECRET);
        Constants::useOfficial();
        $sender = new Sender();
        // message1 演示自定义的点击行为
        $message1 = new Builder();
        $message1->title($title);  // 通知栏的title
        $message1->description(self::emojiToUnicode($desc)); // 通知栏的descption
        $message1->passThrough(0);  // 这是一条通知栏消息，如果需要透传，把这个参数设置成1,同时去掉title和descption两个参数
        $message1->payload(json_encode($payload)); // 携带的数据，点击后将会通过客户端的receiver中的onReceiveMessage方法传入。
        $message1->extra(Builder::notifyForeground, 1); // 应用在前台是否展示通知，如果不希望应用在前台时候弹出通知，则设置这个参数为0
        $message1->notifyId(2); // 通知类型。最多支持0-4 5个取值范围，同样的类型的通知会互相覆盖，不同类型可以在通知栏并存
        $message1->build();
        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget('alias1', TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message1);
        $sender->sendToAliases($message1,$aliasList)->getRaw();
    }
    
    /* 活动推送  */
    public static function momentSendMessage($loginUid, $eventId, $momentId, $payload, $title = "", $uploadPictureCount = 0)
    {
        $users = Event::getEventUser($eventId);//获取活动参与的人
        $groupList = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
        $groupsMembers = [];
        array_walk($groupList, function(&$value, $key) use (&$groupsMembers) {
            $groupMember = GroupModel::getGroupMember($value->to, 0, 0x7FFFFFFF);
            $groupOwner = GroupModel::getGroupOwner($value->to);
            foreach ($groupMember as $user) {
                $groupsMembers[$user->properties->uid] = $user->properties->uid;
            }
            $groupsMembers[$groupOwner] = $groupOwner;
        });
        unset($users[$loginUid]);
        unset($groupsMembers[$loginUid]);
        $users = array_unique(array_merge($users, $groupsMembers));
        if (empty($users)) {
            return false;
        }
        if (count($users) == 0) {
            return true;
        }
        /*desc start  */
        $eventInfo = Event::GetEventInfoByEvent($eventId);
        $nickname = User::getUserNickname($loginUid);
        if(Predicates::isEmpty($nickname)) {
            return;
        }
        if($eventInfo['start_time'] != 0) {
            if(Predicates::isEmpty($eventInfo['name'])) {
                return;
            }
            if($uploadPictureCount == 0 || Predicates::isEmpty($uploadPictureCount)) {
                $uploadPictureCount = Event::GetCountPictureByEvent($eventId, $loginUid, $momentId);
            }
            $desc = $nickname."在'".$eventInfo['name']."'故事中上传了".($uploadPictureCount ? $uploadPictureCount : 0)."张照片，点击查看";
        } else {
            $groupList = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
            foreach ($groupList as $group) {
                if(isset($group->properties->target)) {
                    if($group->properties->target == "system") {
                        $groupNode = $group->to;
                        break;
                    }
                }
            }
            if(Predicates::isEmpty($groupNode)) {
                return;
            }
            $groupInfo = GroupModel::getGroupData($groupNode);
            $desc = $nickname."上传了「".$groupInfo->name."」小组的点滴";
        }
        /*desc end  */
        $userList = implode(',', $users);
        $connection = Yii::$app->db;
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            $setting = json_decode($val['setting'], true);
            if (isset($setting['push_enabled'])) {
                if ($setting['push_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
            /* if (isset($setting['push_enabled'])) {
                if ($setting['push_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                }
            }
            if (isset($setting['push_enabled'])) {
                if ($setting['push_enabled'] == 1) {
                    $andRoidUser[] = $val['uid'];
                }
            } */
        }
        if ($iosUser) {
            error_log("momentSendMessage iosdUser ".$desc);
            error_log(json_encode($iosUser));
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            error_log("momentSendMessage android ".$desc);
            error_log(json_encode($andRoidUser));
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }
    
    /* 邀请推送  */
    public static function inviteSendMessage($eventId, $users, $payload, $title = "")
    {
        if (!is_array($users)) {
            return false;
        }
        /*desc start  */
        $EventName = Event::GetEventInfoByEvent($eventId, "name");
        if(Predicates::isEmpty($EventName)) {
            return;
        }
        $desc = "您已加入'".($EventName?$EventName:"")."'故事，点击查看故事详情";
        /*desc end  */
        $userList = implode(',', $users);
        $connection = Yii::$app->db;
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            $setting = json_decode($val['setting'], true);
            if (isset($setting['invite_enabled'])) {
                if ($setting['invite_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
            /* if (isset($setting['invite_enabled'])) {
                if ($setting['invite_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                }
            }
            if (isset($setting['invite_enabled'])) {
                if ($setting['invite_enabled'] == 1) {
                    $andRoidUser[] = $val['uid'];
                }
            } */
        }
        if ($iosUser) {
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }
    
    /* 接受邀请加入小组推送  */
    public static function inviteJoinGroupSendMessage($gid, $users, $payload = "", $title = "", $desc = "")
    {
        error_log("inviteJoinGroupSendMessage ".$gid);
        if (!is_array($users)) {
            return false;
        }
        /*desc start  */
        $group = Tao::getObject($gid);
        $group = $group->properties->name;
        if(Predicates::isEmpty($group)) {
            return;
        }
        $desc = "您已加入「".($group ? $group : "")."」小组，点击查看详情";
        /*desc end  */
        if(Predicates::isEmpty($payload)) {
            $payload = ["type"=>3, "group_id"=>$gid];
        }
        $userList = implode(',', $users);
        $connection = Yii::$app->db;
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            $setting = json_decode($val['setting'], true);
            if (isset($setting['invite_enabled'])) {
                if ($setting['invite_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
            /* if (isset($setting['invite_enabled'])) {
                if ($setting['invite_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                }
            }
            if (isset($setting['invite_enabled'])) {
                if ($setting['invite_enabled'] == 1) {
                    $andRoidUser[] = $val['uid'];
                }
            } */
        }
        error_log("inviteJoinGroupSendMessage ".$userList);
        if ($iosUser) {
            error_log("inviteJoinGroupSendMessage iosdUser ".$desc);
            error_log(json_encode($iosUser));
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            error_log("inviteJoinGroupSendMessage android ".$desc);
            error_log(json_encode($andRoidUser));
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }
    
    /* 评论赞 推送*/
    public static function commentSendMessage($uid, $type, $users, $payload, $title = "")
    {
        if (!is_array($users)) {
            return false;
        }
        if([$uid] == $users) {
            return false;
        }
        $connection = Yii::$app->db;
        /*desc -start-  */
        $userSql = 'select nickname from user where uid ='.$uid;
        $name = $connection->createCommand($userSql)->queryOne();  //获取nickname
        $desc = self::getUidByType($name['nickname'], $type);     //获取文案信息
        /* desc- end- */
        $userList = implode(',', $users);
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            if($val['uid'] == $uid) {
                continue;
            }
            $setting = json_decode($val['setting'], true);
            if (isset($setting['comment_enabled'])) {
                if ($setting['comment_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
            /* if (isset($setting['comment_enabled'])) {
                if ($setting['comment_enabled'] == 1) {
                    $andRoidUser[] = $val['uid'];
                }
            } */
        }
        if ($iosUser) {
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }    

    /* 加入活动给活动创建人推送  */
    public static function addEventSendMessage($loginUid, $eventId, $users, $payload, $title = "", $desc = "") {
        if(Predicates::isEmpty($desc)) {
            $eventName = Event::GetEventInfoByEvent($eventId, "name");
            $nickName = User::getUserNickname($loginUid);
            if(Predicates::isEmpty($eventName) || Predicates::isEmpty($nickName)) {
                return;
            }
            $desc = $nickName . " 已加入您的故事'" . $eventName . "'";
        }
        $userList = implode(',', $users);
        $connection = Yii::$app->db;
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            $setting = json_decode($val['setting'], true);
            if (isset($setting['group_enabled'])) {
                if ($setting['group_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
        }
        if ($iosUser) {
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }
    
    private static function getUidByType($nickname, $type)
    {
        switch($type) {
            case self::TO_COMMENT:
                return $nickname.'评论了你上传的照片，点击查看详情';
                break;
            case self::REPLY_COMMENT:
                return $nickname."回复了你，点击查看详情";
                break;
            case self::LIKE:
                return $nickname."赞了你上传的照片，点击查看详情";
                break;
            default:
                Protocol::badRequest('', '', '');
        }
    }
    
    /* 小组加入成员推送  */
    public static function joinGroupSendMessage($loginUid, $gid, $users = [], $joinTime, $payload, $title = "", $desc = "") {
        error_log("joinGroupSendMessage ".$gid);
        if(Predicates::isEmpty($desc)) {
            $nickName = User::getUserNickname($loginUid);
            $group = Tao::getObject($gid);
            $group = $group->properties->name;
            if(Predicates::isEmpty($group) || Predicates::isEmpty($nickName)) {
                return;
            }
            $desc = "{$nickName}加入了你的小组 「{$group}」";
        }
        if(Predicates::isEmpty($users)) {
            //$userMember = array_merge(GroupModel::getGroupMember($gid), Tao::getAssociationRange($gid, 'OWNED_BY', 0, 0x7FFFFFFF));
            $userMember = GroupModel::getGroupMember($gid);
            if(Predicates::isNotEmpty($userMember)) {
                foreach ($userMember as $user) {
                    if($user->type == "MEMBER_OF") {
                        if($user->properties->uid == $loginUid) {
                            continue;
                        }
                        if(isset($user->properties->receive_push_count)) {
                            if($user->properties->receive_push_count < 3) {
                                $users[] = $user->properties->uid;
                                $a = Tao::updateAssociationArray($gid, "MEMBER_OF", $user->to, ["uid", $user->properties->uid, "receive_push_count", $user->properties->receive_push_count + 1]);
                            }else {
                                if(!isset($user->properties->receive_push_time)) {
                                    Tao::updateAssociationArray($gid, "MEMBER_OF", $user->to, ["uid", $user->properties->uid, "receive_push_count", $user->properties->receive_push_count, "receive_push_time", $joinTime]);
                                    Event::doTableInsert(Us\TableName\TARGET_PUSH, ["uid" => $user->properties->uid, "object_id" => $gid, "type" =>1, "create_time" => date("Y-m-d H:i:s", $joinTime)]);
                                }
                            }
                        }else {
                            $users[] = $user->properties->uid;
                            Tao::updateAssociationArray($gid, "MEMBER_OF", $user->to, ["uid", $user->properties->uid, "receive_push_count", 1]);
                        }
                    }elseif ($user->type == "OWNED_BY") {
                        if($user->properties->uid == $loginUid) {
                            continue;
                        }
                        if(isset($user->properties->receive_push_count)) {
                            if($user->properties->receive_push_count < 3) {
                                $users[] = $user->properties->uid;
                                Tao::updateAssociationArray($gid, "OWNED_BY", $user->to, ["uid", $user->properties->uid, "receive_push_count", $user->properties->receive_push_count + 1]);
                            }else {
                                if(!isset($user->properties->receive_push_time)) {
                                    Tao::updateAssociationArray($gid, "OWNED_BY", $user->to, ["uid", $user->properties->uid, "receive_push_count", $user->properties->receive_push_count, "receive_push_time", $joinTime]);
                                    Event::doTableInsert(Us\TableName\TARGET_PUSH, ["uid" => $user->properties->uid, "object_id" => $gid, "type" =>1, "create_time" => date("Y-m-d H:i:s", $joinTime)]);
                                }
                            }
                        }else {
                            $users[] = $user->properties->uid;
                            Tao::updateAssociationArray($gid, "OWNED_BY", $user->to, ["uid", $user->properties->uid, "receive_push_count", 1]);
                        }
                    }
                }
            }
        }
        if(Predicates::isEmpty($users)) {
            return;
        }
        if(Predicates::isEmpty($payload)) {
            $payload = ["type"=>3, "group_id"=>$gid];
        }
        $userList = implode(',', $users);
        $connection = Yii::$app->db;
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            $setting = json_decode($val['setting'], true);
            if (isset($setting['member_enabled'])) {
                if ($setting['member_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
        }
        error_log("joinGroupSendMessage ".$userList);
        if ($iosUser) {
            error_log("joinGroupSendMessage iosdUser ".$desc);
            error_log(json_encode($iosUser));
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            error_log("joinGroupSendMessage androidUser ".$desc);
            error_log(json_encode($andRoidUser));
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }
    
    /* 组内创建添加活动给组内成员推送  */
    public static function addEventGroupSendMessage($loginUid, $gid, $eventId, $users = [], $payload, $type = 0, $title = "", $desc = "") {
        error_log("addEventGroupSendMessage ".$gid);
        $nickName = User::getUserNickname($loginUid);
        $group = Tao::getObject($gid);
        $group = $group->properties->name;
        $eventName = Event::GetEventInfoByEvent($eventId, "name");
        if(Predicates::isEmpty($group) || Predicates::isEmpty($nickName) || Predicates::isEmpty($eventName)) {
            return;
        }
        if($type == 0) {
            $desc = "{$nickName}在「{$group}」小组中创建了故事'{$eventName}'";
        }else {
            $desc = "{$nickName}在「{$group}」小组中添加了故事'{$eventName}'";
        }
        if(Predicates::isEmpty($users)) {
            $userMember = array_merge(GroupModel::getGroupMember($gid), Tao::getAssociationRange($gid, 'OWNED_BY', 0, 0x7FFFFFFF));
            if(Predicates::isNotEmpty($userMember)) {
                foreach ($userMember as $user) {
                    if(isset($user->properties->uid)) {
                        if($user->properties->uid == $loginUid) {
                            continue;
                        }
                        $users[] = $user->properties->uid;
                    }
                }
            }
        }
        if(Predicates::isEmpty($users)) {
            return;
        }
        if(Predicates::isEmpty($payload)) {
            $payload = ["type"=>3, "group_id"=>$gid];
        }
        $userList = implode(',', $users);
        $connection = Yii::$app->db;
        $sql = "select d.uid, d.platform, c.setting from ".Us\TableName\USER_DEVICE." as d  left join ".Us\TableName\USER_CONFIG." as c on d.uid = c.uid where d.uid in ($userList)";
        $list = $connection->createCommand($sql)->queryAll();
        $iosUser = array();
        $andRoidUser = array();
        foreach ($list as $val) {
            $setting = json_decode($val['setting'], true);
            if (isset($setting['story_enabled'])) {
                if ($setting['story_enabled'] == 1) {
                    $iosUser[] = $val['uid'];
                    $andRoidUser[] = $val['uid'];
                }
            }else {
                $iosUser[] = $val['uid'];
                $andRoidUser[] = $val['uid'];
            }
        }
        error_log("addEventGroupSendMessage ".$userList);
        if ($iosUser) {
            error_log("addEventGroupSendMessage iosdUser ".$desc);
            error_log(json_encode($iosUser));
            self::_ios($iosUser, $payload, $desc);
        }
        if ($andRoidUser) {
            error_log("addEventGroupSendMessage androidUser ".$desc);
            error_log(json_encode($andRoidUser));
            self::_android($andRoidUser, $payload, $desc, $title);
        }
        return true;
    }
    
    /* 提交rabbitMq Work */
    public static function submitWorks($payload) {
        AsyncTask::submit(Us\Config\MIPUSH_NODE, $payload);
    }
    
    /* 替换表情为Unicode */
    public static function emojiToUnicode($str) {
        return preg_replace_callback("/(:\\w+:)/", function ($matches) {
            if(!yii::$app->redis->exists(Us\Push\PUSH_EMOJI_JSON)){
                $jsonFile = APP_PATH . "/conf/document.json";
                $template = file_get_contents($jsonFile);
                yii::$app->redis->set(Us\Push\PUSH_EMOJI_JSON, $template);
                yii::$app->redis->expire(Us\Push\PUSH_EMOJI_JSON, 60*24);
            }else {
                $template = yii::$app->redis->get(Us\Push\PUSH_EMOJI_JSON);
            }
            $template = json_decode($template, true);
            if(isset($template[$matches[0]])) {
                return ($template[$matches[0]]);
            }else {
                return ($matches[0]);
            }
        }, $str);
    }
}
