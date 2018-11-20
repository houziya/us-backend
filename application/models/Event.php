<?php
use Yaf\Controller_Abstract;
use yii\db\Query;

class EventModel
{
    public static function deleteEventUser($eid, $uid)
    {
        $connection = Yii::$app->db;
        return $connection->createCommand()->update(Us\TableName\EVENT_USER, ['is_deleted'=>1], ['member_uid'=>$uid, 'event_id'=>$eid])->execute();
    }
    
    public static function deleteEventPicture($eid, $uid)
    {
        $connection = Yii::$app->db;
        $sqlPicture = "update ".Us\TableName\MOMENT_PICTURE." set status = 1 where moment_id in (select id from ".Us\TableName\EVENT_MOMENT." where uid =".$uid." and event_id =".$eid.")";
        return $connection->createCommand($sqlPicture)->execute();
    }

    public static function deleteEventMoment($eid, $uid)
    {
        $connection = Yii::$app->db;
        return $connection->createCommand()->update(Us\TableName\EVENT_MOMENT, ['status'=>1], ['uid'=>$uid, 'event_id'=>$eid])->execute();
    }

    public static function deleteEvent($eid)
    {
        $connection = Yii::$app->db;
        return $connection->createCommand()->update(Us\TableName\EVENT, ['status' => 1], ['id'=>$eid])->execute();
    }

    public static function exitSpecialEvent($eid, $uid, $type=0)
    {
        if (!$type) {
            self::deleteEventUser($eid, $uid);
            self::deleteEventMoment($eid, $uid);
            self::deleteEventPicture($eid, $uid);
            return true;
        } else {
            self::deleteEventUser($eid, $uid);
            self::deleteEventMoment($eid, $uid);
            self::deleteEventPicture($eid, $uid);
            return self::deleteEvent($eid);
        }
    }
}