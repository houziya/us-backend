<?php
use yii\db\Query;
/**
 *
 * 活动相关数据统计  statEventData
 */
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try {
    if (count($argv) < 2) {
        $argv = [date("Y-m-d", strtotime("-1 day"))];
    } else {
        $argv = array_slice($argv, 1);
    }
    foreach($argv as $date) {
        EventStatData::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class EventStatData
{
    public static function execute($date)
    {
        Execution::withFallback(
            function () use ($date){
                $endDate = date("Y-m-d", strtotime("+1 day", strtotime($date)));
                $statData = self::doGetEventMetadata($date, $endDate);
                self::doChangeEventEnable($date, $endDate);
                if (self::doStoreEventDataToDB($date, $statData)) {
                    echo $date.' event_data has done!';
                } else {
                    echo "Fail\n";
                }
            }
        );
    }

    private static function doChangeEventEnable($beginDate, $statData)
    {
        $connection = Yii::$app->db;
        $sql = "select distinct e.id from event as e inner join moment_picture as mp on 
                mp.create_time between '".$beginDate."' and '".$statData."' and mp.status=0 and mp.event_id=e.id and e.status=0";
        $command = $connection->createCommand($sql);
        $event = $command->queryAll();
        if (Predicates::isEmpty($event)) {
            echo "no enable event!\n";
            return 0;
        }
        foreach ($event as $data) {
        	$eventList[] = $data['id'];
        }
        unset($event);
        return $connection->createCommand()->update(Us\TableName\EVENT, ['enable' => 1], ['in', 'id', $eventList])->execute();
    }

    private static function doGetEventMetadata($statDate, $endDate)
    {
        return [
	       's' => self::doQueryEventTotalData($statDate, $endDate),
	       'e' => self::doQueryEventEabledData($statDate, $endDate),
	       'p' => [
	           'pv' => self::doQueryEventPicData($statDate, $endDate),
	           'uv' => self::doQueryEventPicUserData($statDate, $endDate)
           ],
           'op' => self::doQueryUserUpload($statDate, $endDate),
           'eu' => self::doQueryStatEventUser($statDate, $endDate)
        ];
    }
    
    private static function doQueryEventTotalData($statDate, $endDate)
    {
        $connection = Yii::$app->db;
	    $sql = "select count(u.eid) as num, u.gender as g, ud.platform as p from user_device as ud inner join 
	            (select event.id as eid, event.uid, user.gender from event inner join user 
	            on user.uid=event.uid and event.create_time between '".$statDate."' and '".$endDate."' and event.status=0 and user.status=0) as u 
	            on u.uid=ud.uid group by ud.platform, u.gender;";
	    $command = $connection->createCommand($sql);
	    $user = $command->queryAll();
	    if (Predicates::isEmpty($user)) {
	    	return 0;
	    }
	    $response = [];
	    foreach ($user as $data) {
	        @$response['p'][$data['p']] += $data['num'];
	        @$response['g'][$data['g']] += $data['num'];
	    }
	    unset($user);
	    return $response;
    }
    
    private static function doQueryEventEabledData($statDate, $endDate)
    {
        $connection = Yii::$app->db;
//         $sql = "select count(um.id) as num, g, ud.platform as p from user_device as ud inner join 
//                 (select user.gender as g, em.id, em.uid from user inner join 
//                 (select distinct e.id, e.uid from event as e inner join moment_picture as mp 
//                 on mp.create_time between'".$statDate."' and '".$endDate."' and mp.status=0 and mp.event_id=e.id and e.status=0) as em 
//                 on user.uid=em.uid and user.status=0) as um 
//                 on um.uid=ud.uid group by g, p";
        $sql = "select distinct event_id as eid from moment_picture as mp inner join event on
    	            event.id=mp.event_id and event.status=0 and mp.status=0 and
    	            mp.create_time between '".$statDate."' and '".$endDate."'";
        $command = $connection->createCommand($sql);
        $event = $command->queryAll();
        $tmp = [];
        foreach ($event as $data) {
            $tmp[] = $data['eid'];
        }
        $sql = "select distinct event_id as eid from moment_picture as mp inner join event on
    	            event.id=mp.event_id and event.status=0 and mp.status=0 and mp.create_time < '".$statDate."';";
        $command = $connection->createCommand($sql);
        $noEvent = $command->queryAll();
        foreach ($noEvent as $data) {
            $no[] = $data['eid'];
        }
        $len = count($tmp);
        for ($i=0; $i<$len; $i++) {
            if (in_array($tmp[$i], $no)) {
                unset($tmp[$i]);
            }
        }
        $str = "";
        foreach ($tmp as $data) {
            $str .= $data.",";
        }
        $str = substr($str, 0, -1);
        $sql = "select count(id) as num, gender as g, platform as p from event inner join 
                (select user.uid, gender, platform from user inner join 
                user_device as ud on ud.uid=user.uid and status=0) as u on 
                u.uid=event.uid and event.status=0 and event.id in (".$str.") group by g, p";
        $command = $connection->createCommand($sql);
        $enableEvent = $command->queryAll();
        if (Predicates::isEmpty($enableEvent)) {
	    	return 0;
	    }
	    $response = [];
	    foreach ($enableEvent as $data) {
	    	@$response['p'][$data['p']] += $data['num'];
	    	@$response['g'][$data['g']] += $data['num'];
	    }
	    unset($enableEvent);
	    return $response;
    }

    private static function doQueryEventPicData($statDate, $endDate)
    {
        $connection = Yii::$app->db;
        $sql = "select count(u.id) as num, u.gender as g, ud.platform as p from user_device as ud inner join
                (select m.id, user.gender, m.uid from user inner join
                (select mp.id, em.uid from moment_picture as mp inner join 
                event_moment as em on 
                em.id=mp.moment_id and em.create_time between '".$statDate."' and '".$endDate."' and em.status=0 and mp.create_time between '".$statDate."' and '".$endDate."' and mp.status=0) as m on 
                m.uid=user.uid and user.status=0) as u on u.uid=ud.uid group by p, g";
        $command = $connection->createCommand($sql);
        $picture = $command->queryAll();
        if (Predicates::isEmpty($picture)) {
            return 0;
        }
        $response = [];
        foreach ($picture as $data) {
            @$response['p'][$data['p']] += $data['num'];
            @$response['g'][$data['g']] += $data['num'];
        }
        unset($picture);
        return $response;
    }

    private static function doQueryEventPicUserData($statDate, $endDate)
    {
        $connection = Yii::$app->db;
        $sql = "select count(i) as num, g, ud.platform as p from user_device as ud inner join 
                (select gender as g, user.uid as i from user inner join 
                (select distinct em.uid from event_moment as em inner join event as e 
                on e.id=em.event_id and e.status=0 and em.create_time between '".$statDate."' and '".$endDate."' and em.status=0) as u 
                on u.uid=user.uid and user.status=0) as eu on eu.i=ud.uid group by p, g";
        $command = $connection->createCommand($sql);
        $picture = $command->queryAll();
        if (Predicates::isEmpty($picture)) {
            return 0;
        }
        $response = [];
        foreach ($picture as $data) {
            @$response['p'][$data['p']] += $data['num'];
            @$response['g'][$data['g']] += $data['num'];
        }
        unset($picture);
        return $response;
    }

    private static function doStoreEventDataToDB($statDate, $eventData)
    {
        if (empty($statDate) || empty($eventData)) {
            return false;
        }
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
            'stat_date' => date("Ymd", strtotime($statDate)),
            'create_time' => date("Y-m-d H:i:s"),
            'type' => 2,
            'data' => json_encode($eventData),
        ])->execute();
        return $res;
    }

    private static function doQueryUserUpload($statDate, $endDate)
    {
        $connection = Yii::$app->db;
        $sql = "select em.uid, em.event_id, count(m.id) as num from event_moment as em inner join 
                (select event_id, mp.id, event.uid, mp.moment_id from event inner join moment_picture as mp on 
                mp.create_time between '".$statDate."' and '".$endDate."' and mp.status=0 and event.status=0 and mp.event_id=event.id ) 
                as m on m.event_id=em.event_id and em.status=0 and em.create_time between '".$statDate."' and '".$endDate."' 
                and em.uid!=m.uid and m.moment_id=em.id group by em.event_id, uid";
        $command = $connection->createCommand($sql);
        $picture = $command->queryAll();
        if (Predicates::isEmpty($picture)) {
            return 0;
        }
        $response = [];
        foreach ($picture as $data) {
            @$response['pv'] += $data['num'];
            @$response['uv'] ++;
        }
        return $response;
    }

    private static function doQueryStatEventUser($statDate, $endDate)
    {
        $connection = Yii::$app->db;
        $sql = "select count(member_uid) as num from event_user as eu inner join event on 
                event.status=0 and event.id=eu.event_id and eu.is_deleted=0 and eu.create_time 
                between '".$statDate."' and '".$endDate."'";
        $command = $connection->createCommand($sql);
        $user = $command->queryOne();
        if (Predicates::isEmpty($user)) {
            return 0;
        }
        return $user['num'];
    }
}
?>
