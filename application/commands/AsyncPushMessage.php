<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
    AsyncPushMessage::execute();
} catch (Exception $e) {
    return false;
    //var_dump($e);
}

class AsyncPushMessage
{
    public static function execute(){
        AsyncTask::consume(Us\Config\MIPUSH_NODE, function($task){
            foreach ($task->payload as $subTask) {
                try{
                $uid = $subTask->uid;
                if(isset($subTask->gid)) {
                    $gid = $subTask->gid;
                }
                if($subTask->type === MiPush::EVENTINTOGROUP) {
                    $eventId = $subTask->event_id;
                    $etype = $subTask->e_type;
                    MiPush::addEventGroupSendMessage($uid, $gid, $eventId, [], "addEventIntoGroup", $etype, "Us");
                }elseif ($subTask->type === MiPush::JOINGROUP) {
                    $joinTime = $subTask->join_time;
                    MiPush::joinGroupSendMessage($uid, $gid, [], $joinTime, 'joinGroup', "Us");
                }elseif ($subTask->type === MiPush::INVITEJOINGROUP) {
                    MiPush::inviteJoinGroupSendMessage($gid, [$uid], "inviteJoinGroup", "Us");
                }elseif ($subTask->type === MiPush::INVITEJOINEVENT) {
                    MiPush::inviteSendMessage($subTask->event_id, [$uid], "yaoqing", "Us");
                }elseif ($subTask->type === MiPush::JOINEVENT) {
                    MiPush::addEventSendMessage($uid, $subTask->event_id, [$subTask->to_uid], "addEvent", "Us");
                }elseif ($subTask->type === MiPush::CREATEMOMENT) {
                    //创建动态
                    $uploadCount = Accessor::either(isset($subTask->upload_count) ? $subTask->upload_count : 0);
                    MiPush::momentSendMessage($uid, $subTask->event_id, $subTask->moment_id, "chuantu", "Us", $uploadCount);
                }elseif ($subTask->type === MiPush::COMMENT) {
                    //评论赞
                    MiPush::commentSendMessage($uid, $subTask->e_type, $subTask->users, '');
                }
                } catch (Exception $e) {
                    return true;
                } 
            }
            return true;
        }, 2, 1024);
    }
}