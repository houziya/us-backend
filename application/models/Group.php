<?php
use Yaf\Controller_Abstract;
use yii\db\Query;

class GroupModel
{
    public static $params = ['name', 'owner', 'coverpage', 'updateTime'];
    private static $nodeList = [];

    public static function create($name, $owner)
    {
        $pgid = Push::createGroup();
        $groupNode = Tao::addObject("CIRCLE", "name", $name, "owner", $owner, "coverpage", "default", "pgid", $pgid, 'updateTime', time());
        if ($groupNode) {
            Push::joinGroup($owner, $pgid);
            if (Tao::addAssociation(UserModel::getUserTaoId($owner), "OWNS", "OWNED_BY", $groupNode->id, "uid", $owner)) {
                if (Tao::addAssociation(UserModel::getUserTaoId($owner), "MEMBER", "MEMBER_OF", $groupNode->id, "uid", $owner)) {
                    return ['id' => $groupNode->id, 'time' => $groupNode->timestamp];
                }
            }
        }
        return false;
    }

    public static function join($gid, $uid)
    {
        $joinTime = time();
        if (!self::verifyGroupUserExist($gid, $uid)){
            if (Tao::addAssociation(UserModel::getUserTaoId($uid), "MEMBER", "MEMBER_OF", $gid, "uid", $uid)) {
            	$node = self::getNodeData($gid);
            	/*小组内成员变动修改排序时间戳 */
            	GroupModel::updateProfile($gid, 'updateTime', time());
            	Group::pushGroupChanges ($gid, $uid, 2, "");
            	//MiPush::inviteJoinGroupSendMessage($gid, [$uid], "inviteJoinGroup", "Us");
            	//MiPush::joinGroupSendMessage($uid, $gid, [], $joinTime, 'joinGroup', "Us");
            	$payload = [];
            	$payload[] = ["uid" => $uid, "gid" => $gid, "join_time" => $joinTime, "type" =>MiPush::JOINGROUP];
            	$payload[] = ["uid" => $uid, "gid" => $gid, "type" =>MiPush::INVITEJOINGROUP];
            	MiPush::submitWorks($payload);
            	return Push::joinGroup($uid, $node->properties->pgid);
            }
        }
        return false;
    }

    public static function updateProfile($gid, $target, $value)
    {
        if (!in_array($target, self::$params)) {
           return -1;
        }
        $nodeData = self::getGroupData($gid);
        if (!isset($nodeData->updateTime)) {
            $nodeData->updateTime = time();
        }
        switch ($target) {
        	case 'name':
        	    $nodeData->name = $value;
        	    break;
        	case 'coverpage':
        	    $nodeData->coverpage = $value;
        	    break;
        	case 'owner':
        	    $nodeData->owner = $value;
        	    break;
    	    case 'updateTime':
    	        $nodeData->updateTime = $value;
    	        break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $target);
        }
        return Tao::updateObject($gid, 'name', $nodeData->name, 'owner', $nodeData->owner, 'coverpage', $nodeData->coverpage, 'pgid', $nodeData->pgid, 'updateTime', $nodeData->updateTime);
    }

    public static function getGroupOwner($gid)
    {
        $nodeData = Tao::getObject($gid);
        return intval($nodeData->properties->owner);
    }

    public static function getGroupMember($gid, $offset=0, $limit=20)
    {
        return Tao::getAssociationRange($gid, 'MEMBER_OF', $offset, $limit);
    }

    public static function getNodeData($nodeId)
    {
        $response = [];
        if (Predicates::isEmpty(self::$nodeList) || !@self::$nodeList[$nodeId]) {
           $node = Tao::getObject($nodeId);
           self::$nodeList[$nodeId] = $node;
        }
        return self::$nodeList[$nodeId];
    }

    public static function getNodeListData($nodes)
    {
        if (Predicates::isEmpty(self::$nodeList)) {
            foreach ($nodes as $node) {
                $data = Tao::getObject($node);
                self::$nodeList[$node] = $data;
            }
            return self::$nodeList;
        }
        $response = [];
        foreach ($nodes as $node) {
            $response[$node] = isset(self::$nodeList[$node])?self::$nodeList[$node]:Tao::getObject($node);
        }
        return $response;
    }

    public static function getGroupData($gid, $params=null)
    {
        if (Predicates::isEmpty(self::$nodeList) || !@self::$nodeList[$gid]) {
            $data = Tao::getObject($gid);
            self::$nodeList[$gid] = $data;
        }
        if (!Predicates::equals(self::$nodeList[$gid]->type, 'CIRCLE')) {
            return -1;
        }
        if ($params) {
            $response = [];
            foreach ($params as $target) {
                $response[$gid][$target] = self::$nodeList[$gid]->properties->$target;
            }
            return $response;
        }
        return self::$nodeList[$gid]->properties;
    }

    public static function getGroupListData($groupList, $params=null)
    {
        if (Predicates::isEmpty(self::$nodeList)) {
            foreach ($groupList as $node) {
                $data = Tao::getObject($node);
                self::$nodeList[$node] = $data;
            }
        }
        $response = [];
        if ($groupList) {
            foreach ($groupList as $node) {
                $data = @self::$nodeList[$node]?self::$nodeList[$node]:Tao::getObject($node);
                if (!Predicates::equals($data->type, 'CIRCLE')) {
                	continue;
                }
                if ($params) {
                    foreach ($params as $target) {
                        $response[$node][$target] = $data->properties->$target;
                        $response[$node][$target]->timestamp = $data->timestamp;
                    }
                } else {
                    $response[$node] = $data->properties;
                    $response[$node]->timestamp = $data->timestamp;
                }
            }
        }
        return $response;
    }

    public static function deleteMember($gid, $uid)
    {
        $node = self::getGroupData($gid);
        if (Predicates::equals(intval(GroupModel::getGroupOwner($gid)), intval($uid))) {
            if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'MEMBER', 'MEMBER_OF', $gid)) {
                if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'OWNS', 'OWNED_BY', $gid)) {
                    return Push::outGroup($uid, $node->pgid);
                }
            }
        }
        if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'MEMBER', 'MEMBER_OF', $gid)) {
            return Push::outGroup($uid, $node->pgid);
        }
        return false;
    }

    public static function verifyGroupMember($gid, $uid)
    {
        return Tao::associationExists(UserModel::getUserTaoId($uid), 'MEMBER', $gid);
    }

    public static function verifyGroupOwner($gid, $uid)
    {
        return Tao::associationExists(UserModel::getUserTaoId($uid), 'OWNS', $gid);
    }

    public static function getOwnerGroupListByUid($uid, $offset, $limit)
    {
    	return Tao::getAssociationRange(UserModel::getUserTaoId($uid), 'OWNS', $offset, $limit);
    }

    public static function getMemberGroupListByUid($uid, $offset, $limit)
    {
        return Tao::getAssociationRange(UserModel::getUserTaoId($uid), 'MEMBER', $offset, $limit);
    }

    public static function addGroupAssociatEvent($gid, $eid, $eventId, $uid, $type)
    {
    	return Tao::addAssociation($gid, "OWNS", "OWNED_BY", $eid, 'eid', $eventId, 'oper', $uid, 'type', $type);
    }

    public static function getGroupAssociatEvent($gid, $offset, $limit)
    {
        return Tao::getAssociationRange($gid, 'OWNS', $offset, $limit);
    }

    public static function getEventAssociatGroup($eid, $offset, $limit)
    {
        return Execution::withFallback(function() use ($eid, $offset, $limit) { return Tao::getAssociationRange(Event::getEventTaoId($eid), 'OWNED_BY', $offset, $limit); }, function() { return []; });
    }

    public static function getEventInfo($gid, $eid)
    {
    	return Tao::getAssociation($gid, 'OWNS', Event::getEventTaoId($eid));
    }

    public static function deleteGroupEvent($gid, $eid)
    {
    	return Tao::deleteAssociation($gid, 'OWNS', 'OWNED_BY', Event::getEventTaoId($eid));
    }

    public static function addAuditLog($uid, $type, $data)
    {
        $connection = Yii::$app->db;
        return $connection->createCommand()->insert(Us\TableName\USER_AUDIT_LOG, [
                'uid' => $uid,
                'type' => $type,
                'data' => json_encode($data),
        ])->execute();
    }

    public static function getCountMemberByGid ($gid)
    {
        return Tao::countAssociation($gid, 'MEMBER_OF');
    }

    public static function getCountOwnerByGid ($gid)
    {
        return Tao::countAssociation($gid, 'OWNED_BY');
    }

    public static function getCountEventByGid ($gid)
    {
        return Tao::countAssociation($gid, 'OWNS');
    }

    public static function verifyGroupEvent($eid, $gid)
    {
    	return Tao::associationExists(Event::getEventTaoId($eid), 'OWNED_BY', $gid);
    }

    public static function getOperEvent($gid, $uid)
    {
    	 $event = self::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
    	 if ($event) {
    	     $response = [];
    	 	foreach ($event as $data) {
    	 		if ($data->properties->oper==$uid) {
    	 		    $response[] = $data;
    	 		}
    	 	}
    	 }
    	 return $response;
    }

    public static function deleteOperEvent($gid, $uid)
    {
        $event = self::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        if ($event) {
            foreach ($event as $data) {
    	 		if ($data->properties->oper==$uid) {
    	 		    Tao::deleteAssociation($gid, 'OWNS', 'OWNED_BY', $data->to);
    	 		}
            }
        }
        return true;
    }
    
    public static function addGroupUser($gid, $uid, $code)
    {
        $connection = Yii::$app->db;
        return $connection->createCommand()->insert(Us\TableName\GROUP_USER, [
            'uid' => $uid,
            'gid' => $gid,
            'code' => $code,
            'expire_time' => date('Y-m-d H:i:s', time() + Us\Config\INVITE_EXPIRE)
        ])->execute();
    }
    
    public static function verifyGroupUserExist($gid, $uid)
    {
        if (!$gid) {
            return true;
        }
        if (self::verifyGroupMember($gid, $uid)) {
            return true;
        }
        return false;
    }

    public static function deleteAllEvent($eid, $uid)
    {
        $side = self::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
        if ($side) {
            foreach ($side as $data) {
                if (Predicates::equals(intval($data->properties->oper), intval($uid))) {
                    Tao::deleteAssociation($data->to, 'OWNS', 'OWNED_BY', $data->from);
                }
            }
            return true;
        }
        return true;
    }

    public static function getGroupIdByCode($code)
    {
        $query = new Query;
        $query->select('gid, uid, expire_time') ->from(Us\TableName\GROUP_USER)
            ->where(['code'=>$code]);
        $event = $query->one();
        if (strtotime($event['expire_time'])<time()) {
        	return false;
        }
        return $event['gid'];
    }

    public static function getGroupUidByCode($code)
    {
        $query = new Query;
        $query->select('gid, uid, expire_time') ->from(Us\TableName\GROUP_USER)
        ->where(['code'=>$code]);
        $event = $query->one();
        if (strtotime($event['expire_time'])<time()) {
            return false;
        }
        return $event['uid'];
    }

    public static function getGroupUserByCode($code)
    {
        $query = new Query;
        $query->select('gid, uid, expire_time') ->from(Us\TableName\GROUP_USER)
        ->where(['code'=>$code]);
        $event = $query->one();
        if (strtotime($event['expire_time'])<time()) {
        	return false;
        }
        return $event;
    }

    public static function deleteSpecialEvent($gid)
    {
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        $eid = 0;
        $a =  array_walk($eventList, function($group, $key) use(&$eid) {
            if (isset($group->properties->target)) {
                if (Predicates::equals($group->properties->target, 'system')) {
                    $eid = $group->to;
                	return ;
                }
            }
        });
        return Tao::deleteAssociation($gid, 'OWNS', 'OWNED_BY', $eid);
    }
}
