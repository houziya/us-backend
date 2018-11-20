<?php
use yii\db\Query;
use yii\db\Expression;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

class PushMessageCommands
{
    static function addEventMiPush() {
        $connection = Yii::$app->db;
        $sql = "select event_id, member_uid, receive_push_time from event_user where receive_push_time != '' order by event_id, create_time";
        $command = $connection->createCommand($sql);
        $members = $command->queryAll();
        foreach ($members as $member) {
            if((time() - strtotime($member['receive_push_time'])) >= 60*3) {
                $pushSql = "select member_uid,receive_push_time from event_user where create_time >= '".$member['receive_push_time']."' and event_id = ".$member['event_id'];
                $pushCommand = $connection->createCommand($pushSql);
                $pushMembers = $pushCommand->queryAll();
                if(Predicates::isNotEmpty($pushMembers)) {
                    $pushCount = count($pushMembers);
                    $pushNickname = User::getUserNickname($pushMembers[0]['member_uid']);
                    $eventName = Event::GetEventInfoByEvent($member['event_id'], "name");
                    if(Predicates::isEmpty($pushNickname) || Predicates::isEmpty($eventName)) {
                        continue;
                    }
                    $desc = $pushNickname."等".$pushCount."人已加入您的故事'".$eventName."'";
                    MiPush::addEventSendMessage($member['event_id'], [$member['member_uid']], "addEvent", "Us", $desc);
                    Event::doTableUpdate(Event::$tableEventUser, ['receive_push_time' => null, "receive_push_count" => new Expression("receive_push_count+1")], "member_uid = :uid and event_id = :event_id", [":uid" => $member['member_uid'], ":event_id" => $member['event_id']]);
                }
                unset($pushMembers);
                unset($pushNickname);
                unset($pushSql);
                unset($eventName);
            }
        }
    }
    
    static function joinGroupMiPush() {
        $time = date("Y-m-d H:i:s", time() - 60*3);
        $connection = Yii::$app->db;
        $sql = "select * from target_push where create_time <= '".$time."' order by object_id";
        $command = $connection->createCommand($sql);
        $datas = $command->queryAll();
        $groupMembers = [];
        $objectId = 0;
        foreach ($datas as $data) {
            if(!isset($groupMembers[$data["object_id"]])){
                //$userMember = array_merge(GroupModel::getGroupMember($data['object_id'], 0, 0x7FFFFFFF), Tao::getAssociationRange($data['object_id'], 'OWNED_BY', 0, 0x7FFFFFFF));
                $userMember = GroupModel::getGroupMember($data['object_id']);
                $groupMembers[$data["object_id"]] = $userMember;
            }
            if($data["object_id"] != $objectId) {
                unset($groupMembers[$objectId]);
                $objectId = $data["object_id"];
            }
            $userMember = $groupMembers[$data["object_id"]];
            $pushUsers = [];
            foreach ($userMember as $user) {
                if(strtotime($data["create_time"]) <= $user->timestamp) {
                    $pushUsers[] = $user->properties->uid;
                }
                if($user->properties->uid == $data['uid']) {
                    $to = $user->to;
                    $type = $user->type;
                    $pushCount = $user->properties->receive_push_count;
                }
            }
            if(!isset($to) || !isset($type) || !isset($pushCount)) {
                continue;
            }
            if(Predicates::isNotEmpty($pushUsers)) {
                $countMem = count($pushUsers);
                $nickName = User::getUserNickname($pushUsers[0]);
                $groupName = Tao::getObject($data['object_id']);
                $groupName = $groupName->properties->name;
                if(Predicates::isEmpty($nickName) || Predicates::isEmpty($groupName)) {
                    continue;
                }
                MiPush::joinGroupSendMessage($data['uid'], $data['object_id'], [$data['uid']], time(), "joinGroup", "Us", "{$nickName}等{$countMem}人加入了你的小组「{$groupName}」");
                Tao::updateAssociationArray($data['object_id'], $type, $to, ["uid", $data['uid'], "receive_push_count", $pushCount + 1]);
                unset($type);
                unset($to);
                unset($pushCount);
            }
        }
        //删除
        $connection->createCommand()->delete('target_push', 'create_time <= :create_time', [':create_time' => $time])->execute();
    }
    static function joinGroupMiPush_bak() {
        $connection = Yii::$app->db;
        $sql = "select * from tao_object_store where object_type = 6 and deleted = 0";
        $command = $connection->createCommand($sql);
        $datas = $command->queryAll();
        foreach ($datas as $group) {
            $userMember = array_merge(GroupModel::getGroupMember($group['object_id'], 0, 0x7FFFFFFF), Tao::getAssociationRange($group['object_id'], 'OWNED_BY', 0, 0x7FFFFFFF));
            foreach ($userMember as $user) {
                if(isset($user->properties->receive_push_time)) {
                    if(!empty($user->properties->receive_push_time && ((time() - $user->properties->receive_push_time) >= 60*3))) {
                        $pushUsers = [];
                        foreach ($userMember as $pushUser) {
                            if($user->properties->receive_push_time <= $pushUser->timestamp) {
                                $pushUsers[] = $pushUser->properties->uid;
                            }
                        }
                        if(Predicates::isNotEmpty($pushUsers)) {
                            //print_r($user->properties->uid."|".$group['object_id']);
                            //print_r($pushUsers);
                            $countMem = count($pushUsers);
                            $nickName = User::getUserNickname($pushUsers[0]);
                            $groupName = Tao::getObject($group['object_id']);
                            $groupName = $groupName->properties->name;
                            MiPush::joinGroupSendMessage($user->properties->uid, $group['object_id'], [$user->properties->uid], time(), "joinGroup", "Us", "{$nickName}等{$countMem}人加入了你的小组「{$groupName}」");
                            Tao::updateAssociationArray($group['object_id'], $user->type, $user->to, ["uid", $user->properties->uid, "receive_push_count", $user->properties->receive_push_count + 1]);
                        }
                    }
                }
            }
        }
    }
}
PushMessageCommands::addEventMiPush();
PushMessageCommands::joinGroupMiPush();
