<?php
/**
 * 小组相关
 * @author yantao
 */

use yii\db\Query;

class Group
{
    const TARGET_GROUP = 'group';
    const TARGET_EVENT = 'event';
    
    public static function pushGroupChanges ($groupId, $userId, $type, $etype, $eid=null)
    {
        //type 2:新组员 1:新活动 (etype 创建活动0 添加活动1)
        $groupInfo = GroupModel::getNodeData($groupId);
        $eventNums = GroupModel::getCountEventByGid($groupId) - 1;
        $payload = [
            'groupid' => $groupId,
            'msgtime' => time() * 1000,
            'msgtype' => $type,
            'etype' => $etype,
            'membernum' => GroupModel::getCountMemberByGid($groupId),
            'storynum' => $eventNums < 0 ? 0 : $eventNums,
            'monentnum' => Group::countPic($groupId, $userId),
            'eid' => $eid
        ];
        Push::pushToGroup($userId, $groupInfo->properties->pgid, $type, $payload);
    }
    
    public static function pushGroupMoment ($groupId, $userId, $eventId, $uploadType, $version = 0, $platform = 0 )
    {
        //$uploadType 0创建 1添加
        $groupInfo = GroupModel::getGroupData($groupId);
        $userInfo = User::getUserInfo($userId, ['uid', 'nickname', 'avatar']);
        $eventInfo = (new Query)->select('name, cover_page')
        ->from(Us\TableName\EVENT)
        ->where(['id' => $eventId])
        ->one();
        $payload = [
            'group' => ['group_id'=> $groupId, 'group_name' => $groupInfo->name],
            'user' => ['user_id' => $userInfo['uid'],'avatar' => User::translationDefaultPicture('profile/avatar/'.$userInfo['avatar'], $version, $platform).'.jpg'],
            'event' => ['event_id' => $eventId, 'cover_page' => User::translationDefaultPicture('event/coverpage/'.$eventInfo['cover_page'], $version, $platform).'.jpg'],
            'upload_type' => $uploadType,
        ];
        Push::pushToGroup($userId, $groupInfo->pgid, 0, $payload);
    }
    
    /* web注册  H5分享邀请后调用*/
    public static function doJoinEventOrGroup($uid, $target, $code)
    {
        switch($target) {
            case self::TARGET_EVENT :
                return Event::AddEvent($uid, $code);
                break;
            case self::TARGET_GROUP :
                $result = (new Query)->select('gid,expire_time')
                    ->from(Us\TableName\GROUP_USER)
                    ->where(['code'=>$code])
                    ->one();
                if(Predicates::equals(false, $result)) {
                    return false;
                }
                /* 验证是否已经加入小组  */
                if (GroupModel::verifyGroupMember($result['gid'], $uid)) {
                    return true;
                }
                /* 验证邀请码失效时间 */
                if (strtotime($result['expire_time']) <= time()) {
                    return NULL;
                }
                if(GroupModel::join($result['gid'], $uid)) {
                    /*小组内成员添加修改排序时间戳 */
                    return GroupModel::updateProfile($result['gid'], 'updateTime', time());
                }
                return false;
                break;
            default :
                throw new InvalidArgumentException('Invalid target'. $target);
        }
    }
    
    public static function countPic ($gid, $uid, $version = 0, $platform = 0)
    {
        $eventId = [];
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        foreach ($eventList as $k=>$data) {
            if(@$data->properties->target) {
                if (($version <= 14 && $platform == 0)  || ($version <= 7 && $platform == 1)) {
                    unset($eventList[$k]);
                    continue;
                }
            }
            $eventId[] = $data->properties->eid;
        }
        $picList = Event::getEventPic($eventId, $uid, $version, $platform);
        $picCount = 0;
        if (@$picList['event']) {
            array_walk($picList['event'], function ($pic, $key) use (&$picCount) {
                $picCount += $pic['upload_count'];
            });
        }
        return $picCount;
    }

    public static function changeKey ($data, $key)
    {
        $newData = [];
        if (is_array($data)) {
            foreach ($data as $val) {
                $newKey = $val[$key];
                $newData[$newKey] = $val;
            }
        }
        return $newData;
    }

    public static function pushAddEvent($gid, $uid, $type, $etype, $eventList)
    {
        $eventList = is_array($eventList)?$eventList:json_decode($eventList, true);
        $eventInfo = Event::getAllEventList($eventList);
        foreach ($eventInfo as $key => $event) {
            $eventCreateTime[] = strtotime($event['start_time']);
        }
        array_multisort($eventCreateTime, SORT_ASC, $eventInfo);
        $payload = [];
        foreach ($eventInfo as $event) {
            self::pushGroupChanges ($gid, $uid, $type, $etype, $event['id']);
            //MiPush::addEventGroupSendMessage($uid, $gid, $event['id'], [], "addEventIntoGroup", $etype, "Us");
            $payload[] = ["uid" => $uid, "gid" => $gid, "event_id" => $event["id"], "type" =>MiPush::EVENTINTOGROUP, "e_type" => $etype];
        }
        MiPush::submitWorks($payload);
        return true;
    }
    
    /* 创建默认组  */
    public static function createGroup($name, $owner, $pic)
    {
        $pgid = Push::createGroup();
        $groupNode = Tao::addObject("CIRCLE", "name", $name, "owner", $owner, "coverpage", $pic, "pgid", $pgid, 'updateTime', time());
        if ($groupNode) {
            Push::joinGroup($owner, $pgid);
            if (Tao::addAssociation(UserModel::getUserTaoId($owner), "OWNS", "OWNED_BY", $groupNode->id, "uid", $owner, "type", "system")) {
                if (Tao::addAssociation(UserModel::getUserTaoId($owner), "MEMBER", "MEMBER_OF", $groupNode->id, "uid", $owner)) {
                    return ['id' => $groupNode->id, 'time' => $groupNode->timestamp];
                }
            }
        }
        return false;
    }
    
    /* 修改对应组的邀请码失效时间 */
    public static function doUpdateEffectiveCode($gid)
    {
        $connection = Yii::$app->db;
        $result = (new Query)->select('expire_time')
            ->from(Us\TableName\GROUP_USER)
            ->where(['gid'=>$gid])
            ->one();
        if($result) {
            Execution::autoTransaction($connection, function() use($gid, $connection) {
                $connection->createCommand()->update(Us\TableName\GROUP_USER, ['expire_time'=>date('Y-m-d H:i:s', time())], ['gid'=>$gid])->execute();
                return true;
            });
        }
        return true;
    }

    public static function queryGroupEventInfo($gid, $uid=null, $version = 0, $platform = 0)
    {
        $eventId = [];
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        $hash = [];
        foreach ($eventList as $data) {
            $eventId[] = $data->properties->eid;
            $hash[$data->properties->eid] = $data->properties->oper;
        }
        $tmp = Event::getEventPic($eventId, $uid, $version, $platform);
        $response = $tmp;
        if ($tmp) {
            foreach ($tmp['event'] as $key => $data) {
                @$response['e_num'] ++;
                @$response['p_num'] += $data['upload_count'];
                $response['event'][$key]['op_uid'] = $hash[$data['event_id']];
                $response['event'][$key]['create_time'] = strtotime($data['create_time'])*1000;
                $response['event'][$key]['start_time'] = strtotime($data['start_time'])*1000;
            }
        }
        return $response;
    }

    public static function queryGroupProfileByGid($gid, $seid=0)
    {
        $eventId = [];
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        $hash = [];
        foreach ($eventList as $data) {
            $eventId[] = $data->properties->eid;
        }
    	$event = Event::getEventPicNum($eventId);
    	$e_num = count($eventId)?count($eventId)-1:0;
    	return ['gid' => $gid, 'p_num' => $event, 'e_num' => $e_num, 'm_num' => GroupModel::getCountMemberByGid($gid), 'seid' => $seid];
    }

    public static function queryGroupProfileByEid($eid)
    {
    	$group = GroupModel::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
    	$response = [];
    	array_walk($group, function ($data, $key) use (&$response){
    	    $response[$key]['gid'] = $data->to;
    	    $event = self::queryGroupProfileByGid($data->to);
    	    $response[$key]['p_num'] = $event['p_num'];
    	    $response[$key]['e_num'] = $event['e_num'];
    	    $response[$key]['m_num'] = $event['m_num'];
    	});
    	return $response;
    }

    public static function queryEventProfileByGU($gid, $uid, $version = 0, $platform = 0)
    {
        $eventId = [];
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        $hash = [];
        foreach ($eventList as $data) {
            if ($data->properties->oper!=$uid) {
            	continue;
            }
            $eventId[] = $data->properties->eid;
        }
        $tmp = Event::getEventPic($eventId, $uid, $version, $platform);
        $response = [];
        if (isset($tmp['event'])) {
            array_walk($tmp['event'], function($data, $key) use (&$response){
                @$response['p_num'] += $data['upload_count'];
            });
        }
        $response['e_num'] = count($eventId);
        $response['event'] = $eventId;
        return $response;
    }

    public static function queryGroupListByEid($eid, $uid=null)
    {
        $group = GroupModel::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
        $response = [];
        array_walk($group, function ($data, $key) use (&$response, $uid){
            if (!$uid || $uid==$data->properties->oper) {
                $response[$key]['gid'] = $data->to;
            }
        });
        return $response;
    }

    public static function queryOperEvent($gid, $uid)
    {
        $eventId = [];
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        if ($eventList) {
            foreach ($eventList as $data) {
                if ($data->properties->oper!=$uid) {
                    continue;
                }
                $eventId[] = $data->properties->eid;
            }
        }
        return $eventId;
    }

    public static function verifyEventGroupExistUid($eid, $uid, $toUid=null, $momentId, $type, $field)
    {
        
        $groupList = GroupModel::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
        if (!$groupList) {
            return false;
        }
        $fromResult = false;
        array_walk($groupList, function ($value, $key) use ($uid, $type, $eid, $momentId, $field, &$fromResult) {
            if (GroupModel::verifyGroupMember($value->to, $uid)) {
                $fromResult = true;
                if ($type == 1) {
                    return Group::isLikeOrComment($type, $eid, $momentId, $uid, $field);
                } else {
                    return true;
                }
            }
        });
        if ($toUid) {
            $toResult = false;
             array_walk($groupList, function ($value, $key) use ($toUid, $type, $eid, $momentId, $uid, $field, &$toResult) {
                if (GroupModel::verifyGroupMember($value->to, $toUid) || Event::isEventMember($eid, $toUid)) {
                    $toResult = true;
                    if ($type == 1) {
                        return Group::isLikeOrComment($type, $eid, $momentId, $uid, $field);
                    } else {
                        return true;
                    }
                }
            });
            if ($fromResult && $toResult) {
                if ($type == 1) {
                    return Group::isLikeOrComment($type, $eid, $momentId, $uid, $field);
                } else {
                    return true;
                }
            }
            return false;
        }
        if ($fromResult) {
            return Group::isLikeOrComment($type, $eid, $momentId, $uid, $field);
        } else {
            return $fromResult;
        }
    }
    
    public static function getSpecialEvent($gid)
    {
        $eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
        $eid = 0;
        $a =  array_walk($eventList, function($group, $key) use(&$eid) {
            if (isset($group->properties->target)) {
                if (Predicates::equals($group->properties->target, 'system')) {
                    $eid = $group->properties->eid;
                	return ;
                }
            }
        });
        return $eid;
    }

    public static function isLikeOrComment($type, $eid, $momentId, $uid, $field, $sql = '')
    {
        if ($type == 1){
            return Event::isMomentCommentable($eid, $momentId, $uid, $field, $sql);
        } elseif ($type == 2) {
            return Event::isMomentLikable($eid, $momentId, $uid, $field, $sql);
        } else {
            return false;
        }
    }

    public static function verifySpecialEvent($eid)
    {
        $groupList = GroupModel::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
        $result = [];
        if ($groupList) {
            array_walk($groupList, function($group, $key) use(&$result) {
                if(@$group->properties->target) {
                    $result = $group->to;
                    return ;
                }
            });
        }
        if ($result) {
            $node = GroupModel::getGroupData($result, ['name']);
            $result = ['group_id' => $result, 'group_name' => $node[$result]['name']];
        }
        return $result;
    }

    public static function verifyUserInGroupByEid($eid, $uid)
    {
        $groupList = GroupModel::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
        $result = false;
        if ($groupList) {
            array_walk($groupList, function($group, $key) use(&$result, $uid) {
                if (GroupModel::verifyGroupMember($group->to, $uid)) {
                    $result = true;
                    return ;
                }
            });
        }
        return $result;
    }

    public static function getGroupEventOper($eid)
    {
        $groupList = GroupModel::getEventAssociatGroup($eid, 0, 0x7FFFFFFF);
        $response = [];
        if ($groupList) {
            array_walk($groupList, function($group, $key) use(&$response) {
                if ($group->properties->type==1) {
                    $response[] = $group->properties->oper;
                }
            });
        }
        return $response;
    }
    
    /* 通过UID一起加入的小组  */
    public static function JoinTogetherGroupByUid($loginUid, $uid)
    {
        $groupList = array_merge(GroupModel::getOwnerGroupListByUid($uid, 0, 0x7FFFFFFF), GroupModel::getMemberGroupListByUid($uid, 0, 0x7FFFFFFF));
        $group = array_merge(GroupModel::getOwnerGroupListByUid($loginUid, 0, 0x7FFFFFFF), GroupModel::getMemberGroupListByUid($loginUid, 0, 0x7FFFFFFF));
        $groupId = [];
        foreach($groupList as $temp) {
            foreach ($group as $flag) {
                if(Predicates::equals($temp->to, $flag->to)) {
                    $groupId[] = $temp->to;
                }
            }
        }
        if($groupId) {
            $info = GroupModel::getGroupListData($groupId);
            array_walk($info, function($v, $k) use(&$data, &$info){
                $data['name'] = $v->name;
                $data['c'] = count($info);
            });
                return $data;
        }
        return NULL;
    }
}
