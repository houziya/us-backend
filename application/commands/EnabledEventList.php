<?php
use yii\db\Query;
/**
 *
 * 行为分析报告  BehaviorReport
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
        EnabledEventList::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class EnabledEventList
{
	public static function execute($date)
	{
	    Execution::autoTransaction(Yii::$app->db, function() use ($date) {
	        $response = self::enabledEvent($date);
	        if (self::doStoreEnabledEvent($date, $response)) {
	        	echo "Mission Complete!\n";
	        } else {
	            echo "Fail!\n";
	        }
	    });
	}

	private static function enabledEvent($date)
	{
		$statEvent = self::doQueryStatEvent($date);
		$previousEvent = self::doQueryPreviousEvent($date);
		$source = self::fliterEnabledEvent($statEvent, $previousEvent);
		return self::doQueryUserNum($source, $date);
	}

	private static function doQueryStatEvent($date)
	{
	    $connection = Yii::$app->db;
	    $sql = "select distinct event_id as eid, event.uid from moment_picture as mp inner join event on
    	            event.id=mp.event_id and event.status=0 and mp.status=0 and
    	            mp.create_time between '".$date."' and '".date("Y-m-d", strtotime("+1 day", strtotime($date)))."'";
	    $command = $connection->createCommand($sql);
	    $event = $command->queryAll();
	    if (Predicates::isEmpty($event)) {
	        return 0;
	    }
	    $response = [];
	    foreach ($event as $data) {
	        @$response['list'][] = $data['eid'];
	        @$response['hash'][$data['eid']] = $data['uid'];
	    }
	    return $response;
	}

    private static function doQueryPreviousEvent($date)
    {
        $connection = Yii::$app->db;
        $sql = "select distinct event_id as eid from moment_picture as mp inner join event on
    	            event.id=mp.event_id and event.status=0 and mp.status=0 and mp.create_time < '".$date."';";
        $command = $connection->createCommand($sql);
        $noEvent = $command->queryAll();
        if (Predicates::isEmpty($noEvent)) {
            return 0;
        }
        $response = [];
        foreach ($noEvent as $data) {
            @$response[] = $data['eid'];
        }
        return $response;
    }

    private static function fliterEnabledEvent($stat, $previous)
    {
        if (Predicates::isEmpty($stat) || Predicates::isEmpty($previous)) {
            return 0;
        }
        $len = count($stat['list']);
        for ($i=0; $i<$len; $i++) {
            if (in_array($stat['list'][$i], $previous)) {
                unset($stat['hash'][$stat['list'][$i]]);
                unset($stat['list'][$i]);
            }
        }
        $response['listStr'] = "";
        foreach ($stat['list'] as $eid) {
            $response['listStr'] .= $eid.",";
        }
        foreach ($stat['hash'] as $eid => $uid) {
            $response['uidList'][] = $uid;
        }
        $response['listStr'] = substr($response['listStr'], 0, -1);
        $response['hash'] = $stat['hash'];
        unset($stat);
        unset($previous);
        return $response;
    }

    private static function doQueryUserNum($source, $date)
    {
        if (Predicates::isEmpty($date) || Predicates::isEmpty($source)) {
            return 0;
        }
        $connection = Yii::$app->db;
        $sql = "select count(member_uid) as num, event_id from event_user where event_id in
    	            (".$source['listStr'].") and is_deleted=0 and create_time <'".date("Y-m-d", strtotime("+1 day", strtotime($date)))."' group by event_id";
        $command = $connection->createCommand($sql);
        $eventUser = $command->queryAll();
        $userData = UserModel::getUserListData($source['uidList'], ['uid', 'reg_time']);
        $uidList = [];
        foreach ($userData as $data) {
            $uidList[$data['uid']] = $data['reg_time'];
        }
        foreach ($eventUser as $data) {
            $response['sum'][$data['num']][] = $data['event_id'];
            if (date("Y-m-d", strtotime($uidList[$source['hash'][$data['event_id']]])) == $date) {
                $response['new'][$data['num']][] = $data['event_id'];
            }
            //@$response[$data['num']]++;
        }
        return $response;
    }

    private static function doStoreEnabledEvent($date, $response)
    {
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
                'stat_date' => date("Ymd", strtotime($date)),
                'create_time' => date("Y-m-d H:i:s"),
                'type' => 11,
                'data' => json_encode($response),
                ])->execute();
        return $res;
    }
}