<?php
use Yaf\Controller_Abstract;
use Yaf\Registry;
use yii\db\Query;
use yii\db\Expression;
class TubeController extends Controller_Abstract
{
    public static $tableTubeUserEvent = Us\TableName\TUBE_USER_EVENT;
    public static $tableTubeGroupEvent = Us\TableName\TUBE_GROUP_EVENT;
    public static $tableTubeGroupMembership = Us\TableName\TUBE_GROUP_MEMBERSHIP;
    
    public function pullAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $tubeGroupEventId = $data->optionalInt('group_event_id', PHP_INT_MAX);//上次事件Id
        $tubeUserEventId = $data->optionalInt('user_event_id', PHP_INT_MAX);//上次事件Id
        $limit = $data->optionalInt('limit', 20);
        $key = 'del_message_list_'.$loginUid;
        $cursor = intval(yii::$app->redis->get($key));
        
        if ($limit > 100) {
            $limit = 100;
        }
        $type = $data->optional('type'); //推送通知类型 0代表现场推送
        $deviceId= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android

        $groupEventExtraWhere = "";
        $userEventExtraWhere = "";

        $search  = true;
        $userEvents = [];
        $is_last = true;
        $userEventsCountWhere = '';
        while ($search) {
            //$userEventExtraWhere = 'tube_user_event.to_uid = '.$loginUid.' and tube_user_event.id > '.$cursor.' and tube_user_event.id < '. $tubeUserEventId;
            $userEventExtraWhere = ' and tube_user_event.id > '.$cursor;
            if (Predicates::isNotNull($type)) {
                $type = intval($type);
                if (!in_array($type, [0])) {
                    Protocol::invalidArgument();
                }
                $groupEventExtraWhere .= ' and tube_group_event.event_type = ' . $type;
                $userEventExtraWhere .= ' and tube_user_event.event_type = ' . $type;
                $userEventsCountWhere .= ' and tube_user_event.event_type = ' . $type;
            }
            $userEventSQL = "SELECT 'user' as source, id, to_uid as to_id, from_uid, event_type, UNIX_TIMESTAMP(event_time) * 1000 as event_time, payload from ". self::$tableTubeUserEvent . " WHERE to_uid = " . $loginUid . " and id < " . $tubeUserEventId . $userEventExtraWhere . " order by id desc limit " . $limit;
            $groupEventSQL = "SELECT 'group' as source, tube_group_event.id, tube_group_event.to_gid as to_id, tube_group_event.from_uid, tube_group_event.event_type, " . "UNIX_TIMESTAMP(tube_group_event.event_time) * 1000 as event_time, tube_group_event.payload from " . self::$tableTubeGroupEvent . " as tube_group_event INNER JOIN ". self::$tableTubeGroupMembership . " as tube_group_membership ON " . "tube_group_event.to_gid = tube_group_membership.gid and tube_group_event.id > tube_group_membership.event_id where tube_group_membership.uid = " . $loginUid . " and tube_group_membership.is_member = 1 and tube_group_membership.latest = 1 and tube_group_event.id < " . $tubeGroupEventId . $groupEventExtraWhere . " order by tube_group_event.id desc limit " . $limit;
            $userEvents = Yii::$app->db->createCommand($userEventSQL)->queryAll();
            $groupEvents = Yii::$app->db->createCommand($groupEventSQL)->queryAll();
            $groupEvents = [];
            if (!$userEvents || Predicates::isNull($userEvents) || count($userEvents) == 0) {
                $events = $groupEvents;
            } else if (!$groupEvents || Predicates::isNull($groupEvents) || count($groupEvents) == 0) {
                $events = $userEvents;
            } else {
                $events = array_merge($userEvents, $groupEvents);
                usort($events, function($lhs, $rhs) {
                    $lhs = strtotime($lhs["event_time"]);
                    $rhs = strtotime($rhs["event_time"]);
                    if ($lhs > $rhs) {
                        return -1;
                    } else if ($lhs < $rhs) {
                        return 1;
                    } else {
                        return 0;
                    }
                });
            }
            if (!$events) {
                break;
            }
            foreach ($userEvents as $message) {
                $payload = json_decode($message['payload'], true);
                if ($payload['type'] != 1) {
                    $search = false;
                    break;
                }
                $tubeUserEventId = $message['id'];
            }
            //$userEvents = array_merge($userEvents, $messages);
        }

        if (count($userEvents)) {
            $last_id = end($userEvents);
            $count = (new Query())->select('*')
            ->from(self::$tableTubeUserEvent)
            ->where('to_uid = '.$loginUid.$userEventsCountWhere.' and id > '.$cursor.' and id < '. $last_id['id'])
            ->count();
            if ($count > 0) {
                $is_last = false;
            }
        }
        
        if (!$userEvents || Predicates::isNull($userEvents) || count($userEvents) == 0) {
            $events = $groupEvents;
        } else if (!$groupEvents || Predicates::isNull($groupEvents) || count($groupEvents) == 0) {
            $events = $userEvents;
        } else {
            $events = array_merge($userEvents, $groupEvents);
            usort($events, function($lhs, $rhs) {
                $lhs = strtotime($lhs["event_time"]);
                $rhs = strtotime($rhs["event_time"]);
                if ($lhs > $rhs) {
                    return -1;
                } else if ($lhs < $rhs) {
                    return 1;
                } else {
                    return 0;
                }
            });
        }
        $lastType = NULL;
        $lastFrom = NULL;
        $lastTo = NULL;
        $batch = [];
        $result = [];
        array_walk($events, function(&$value, $key) use (&$lastType, &$lastFrom, &$lastTo, &$batch, &$result) {
            $type = $value["source"];
            $from = $value["from_uid"];
            $to = $value["to_id"];
            if ($lastType != $type || $lastFrom != $from || $lastTo != $to) {
                $result = self::encodeEvents($lastType, $lastFrom, $lastTo, $batch, $result);
                $lastType = $type;
                $lastFrom = $from;
                $lastTo = $to;
                $batch = [];
            }
            $batch[] = $value;
        });
        Protocol::ok(["event" => self::encodeEvents($lastType, $lastFrom, $lastTo, $batch, $result), "is_last" => $is_last]);
	}

    private static function encodeEventValue($from, $events)
    {
        return ["e" => array_map(function($event) {
            return [
        "i" => intval($event["id"]), "t" => intval($event["event_type"]), "ts" => intval($event["event_time"]), "p" => json_decode($event["payload"])];
        }, $events), "f" => $from];
    }

    private static function encodeEvents($type, $from, $to, $events, $result)
    {
        if (count($events) == 0) {
            return $result;
        }
        $event = new StdClass;
        if ($type === "user") {
            $key = "u@" . dechex($to);
        } else {
            $key = "g@" . dechex($to);
        }
        $result[] = [$key => self::encodeEventValue($from, $events)];
        return $result;
    }
    
    public function delMessageAction ()
    {
        $data = Protocol::arguments();
        $cursor = $data->requiredInt('id');
        $key = 'del_message_list_'.$data->requiredInt('login_uid');
        yii::$app->redis->set($key, $cursor);
        Protocol::ok(['result' => true]);
    }
}
?>
