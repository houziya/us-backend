<?php
use yii\db\Query;

class Push
{
	public static function pushTo($from, $to, $type, $payload)
	{
	    //Execution::autoTransaction(Yii::$app->db, function() use($from, $to, $type, $payload) {
    	    $eventTime = time();
     		$pushId = self::doStorePushUserData($from, $to, $type, $payload, date('Y-m-d H:i:s', $eventTime));
    		if (self::doPublishPushData($pushId, $from, $to, $type, $payload, $eventTime, Us\User\TUBE_USER)) {
    		    return true;
    		}
	    	//throw new Exception('redis');
	    //});
	}

	public static function pushToGroup($from, $to, $type, $payload)
	{
	    //Execution::autoTransaction(Yii::$app->db, function() use($from, $to, $type, $payload) {
	        $eventTime = time();
	        $pushId = self::doStorePushGroupData($from, $to, $type, $payload, date('Y-m-d H:i:s', $eventTime));
	        if (self::doPublishPushData($pushId, $from, $to, $type, $payload, $eventTime, Us\User\TUBE_GROUP)) {
	            return true;
	        }
	        //throw new Exception('redis');
	    //});
	}

	private static function doStorePushUserData($from, $to, $type, $payload, $eventTime)
	{
	    $connection = Yii::$app->db;
	    $connection->createCommand()->insert(Us\TableName\TUBE_USER_EVENT, [
            'from_uid' => $from,
            'to_uid' => intval($to),
            'event_type' => $type,
            'event_time' => $eventTime,
            'payload' => json_encode($payload),
        ])->execute();
	    return $connection->getLastInsertID();
	}

	private static function doStorePushGroupData($from, $to, $type, $payload, $eventTime)
	{
	    $connection = Yii::$app->db;
	    $connection->createCommand()->insert(Us\TableName\TUBE_GROUP_EVENT, [
	            'from_uid' => $from,
	            'to_gid' => $to,
	            'event_type' => $type,
	            'event_time' => $eventTime,
	            'payload' => json_encode($payload),
	            ])->execute();
	    return $connection->getLastInsertID();
	}

	private static function doPublishPushData($pushId, $from, $to, $type, $payload, $eventTime, $node)
	{
	    $pushArray = [
	       'f' => $from,
	       't' => $to,
	       'i' => $pushId,
	       'ty' => $type,
	       'ts' => $eventTime*1000,
	       'p' => $payload
	    ];
	    return self::rePublish($node, json_encode($pushArray), 10);
	}

	private static function rePublish($key, $value, $times)
	{
		for ($i=0; $i<$times; $i++) {
			$result = Yii::$app->tube->publish($key, $value);
			if ($result!==false) {
				return true;
			}
		}
		return false;
	}

	public static function createGroup()
	{
	    $connection = Yii::$app->db;
	    $connection->createCommand()->insert(Us\TableName\US_GROUP, [])->execute();
	    return $connection->getLastInsertID();
	}

	public static function joinGroup($uid, $gid)
	{
		if (empty($uid) || empty($gid)) {
			return false;
		}
		//Execution::autoTransaction(Yii::$app->db, function() use($uid, $gid) {
		    self::doUpdateMembership($uid, $gid);
		    $groupEvent = self::doGetGroupEvent($uid, $gid);
		    if ($groupEvent) {
		        $eventId = $groupEvent['id'];
		    }
		    else{
		        $eventId = -1;
		    }
	        $pushId = self::doAddMembership($uid, $gid, $eventId, 1);
    		if ($pushId) {
    			if (self::doPublishPushGroupMemberData($pushId, $uid, $gid, 1)){
    				return true;
    			}
    		}
        	//throw new Exception('redis');
		//});
	}

	public static function outGroup($uid, $gid)
	{
	    if (empty($uid) || empty($gid)) {
	        return false;
	    }
	    //Execution::autoTransaction(Yii::$app->db, function() use($uid, $gid) {
	        self::doUpdateMembership($uid, $gid);
	        $groupEvent = self::doGetGroupEvent($uid, $gid);
	        if ($groupEvent) {
	            $eventId = $groupEvent['id'];
	        }
	        else{
	            $eventId = -1;
	        }
	        $pushId = self::doAddMembership($uid, $gid, $eventId, 0);
	        if ($pushId) {
	            if (self::doPublishPushGroupMemberData($pushId, $uid, $gid, 0)){
	            	return true;
	            }
	        }
	        //throw new Exception('redis');
	    //});
	}

	private static function doUpdateMembership($uid, $gid)
	{
	    $connection = Yii::$app->db;
	    return $connection->createCommand()->update(Us\TableName\TUBE_GROUP_MEMBERSHIP, ['latest' => 0], ['uid' => $uid, 'gid' => $gid])->execute();
	}

	private static function doGetGroupEvent($uid, $gid)
	{
	    $query = new Query;
	    $query->select('id') 
	          ->from(Us\TableName\TUBE_GROUP_EVENT)
	          ->where(['to_gid' => $gid])
	          ->orderBy(['id' => SORT_DESC]);
	    return $query->one();
	}

	private static function doAddMembership($uid, $gid, $eventId, $type)
	{
	    $connection = Yii::$app->db;
	    $connection->createCommand()->insert(Us\TableName\TUBE_GROUP_MEMBERSHIP, [
	            'uid' => $uid,
	            'gid' => $gid,
	            'event_id' => $eventId,
	            'is_member' => $type,
	            'latest' => 1,
	            'membership_time' => date("Y-m-d H:i:s"),
	     ])->execute();
	    return $connection->getLastInsertID();
	}

	private static function doPublishPushGroupMemberData($pushId, $uid, $gid, $type)
	{
	    $pushArray = [
    	    'u' => $uid,
    	    't' => $type,
    	    'i' => $pushId,
    	    'g' => $gid,
	    ];
	    return self::rePublish(Us\User\TUBE_MEMBERSHIP, json_encode($pushArray), 10);
	}

	public static function zk($node, $param){
	    $zk = new Zookeeper(Us\Config\ZK);
	    $result = Execution::withFallback(
	       function() use($node, $param, $zk) {
	           return $zk->set($node, $param);
	       },
	       function() use($node, $param, $zk) {
	           $authority = [
        	       [
                        'perms' => Zookeeper:: PERM_ALL,
                        'scheme' => 'world' ,
                        'id' => 'anyone' 
                   ]
        	   ];
	           return $zk->create($node, $param, $authority);
	       }
	    );
	    return $result;
	}

	public static function pushUserAvatar($uid, $avatar)
	{
	    if (empty($uid) || empty($avatar)) {
	        return -1;
	    }
	    return self::pushTo($uid, $uid, 2, ['avatar' => $avatar]);
	}
}
?>
