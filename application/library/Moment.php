<?php

use Yaf\Dispatcher;
use Yaf\Registry;
use yii\db\Query;

class Moment
{
    const LIMIT_DATA = 20;
    const STATUS_LIKE_NORMAL = 0;//点赞
    const STATUS_LIKE_DELETE = 1;//取消点赞
    const STATUS_COMMENT_NORMAL = 0;//评论正常
    const STATUS_COMMENT_DELETE = 1;//评论删除状态
    const LIMIT = 10000;
    const LIMITCOUNT = 3;
    
    private static $groupList = [];
    /**
     * 通过动态id获取动态数据
     */
    public static function getFirstPictueByMomentIdOrderByshootTime ($momentId)
    {
        return (new Query)
        ->select("object_id")
        ->from(Us\TableName\MOMENT_PICTURE)
        ->where("moment_id = ".$momentId.' and (status = 0 or status = 2)')
        ->orderBy(['shoot_time' => SORT_ASC])
        ->one();
    }

    /**
     * 查看某人是否给某条动态点赞
     */
    public static function isToMomentPraiseByUid ($uid, $eventId = 0, $momentId)
    {
        $praise = (new Query())
        ->select("status")
        ->from(Us\TableName\MOMENT_LIKE)
        ->where('moment_id = '.$momentId.' and event_id = '.$eventId." and uid = ".$uid)
        ->one();
        return ($praise['status'] === "0") ? true : false;
    }
    
    
    /**
     * 赞推送
     */
    public static function likePush ($loginUid, $eventId, $momentId, $momentUid)
    {
        $eventInfo = Event::GetMomentInfoByEvent($eventId, $momentId);
        if ($eventInfo['status'] == 1) {
            return ;
        }
        $result = self::getTaoObjectId($momentId);
        $eventUser = Event::getEventUser($eventId);
        $commentAndLike = self::getCommentLikeUsrts($result['tao_object_id']);
        
        $pushUsers = [];
        foreach ($commentAndLike['arrayUid'] as $k => $uid) {
            $from  = self::checkRelation($uid, $loginUid, $eventUser);
            $to = self::checkRelation($uid, $momentUid, $eventUser);
            if ($from && $to) {
                $pushUsers[$uid] = $uid;
            }
        }
        //$pushUsers = self::getCommentLikeUsrts($result['tao_object_id'])['arrayUid'];
        $userInfo = UserModel::getUserListData([$loginUid], ['uid', 'nickname', 'avatar','status']);
        $pictures = self::getFirstPictueByMomentIdOrderByshootTime($momentId);
        if (Predicates::isEmpty($pictures)) {
            return ;
        }
        //把创建人加入pushuser
        $pushUsers[$eventInfo['uid']] = $eventInfo['uid'];
        //去掉发起人
        unset($pushUsers[$loginUid]);
        foreach ($pushUsers as $pushUser) {
            $payload = [
                'from_uid' => [
                    'uid' => $loginUid,
                    'nickname' => $userInfo[$loginUid]['nickname'],
                    'avatar' => $userInfo[$loginUid]['avatar'],
                ],
                'image_url' => 'event/moment/' . $pictures['object_id'] . '.jpg',
                'event_id' => $eventId,
                'moment_id' => $momentId,
                'p_id' => '',
                'type' => 2,//0:评论,1:删除评论,2:赞
            ];
            Push::pushTo($loginUid, $pushUser, 0, $payload);
        }
    }
    
    /**
     * 评论推送
     */
    public static function commentPush ($loginUid, $eventId, $momentId, $cId, $content, $status, $toUid, $type = 0, $momentUid)
    {
        $eventInfo = Event::GetMomentInfoByEvent($eventId, $momentId);
        if ($eventInfo['status'] == 1) {
            return;
        }
        $pictures = self::getFirstPictueByMomentIdOrderByshootTime($momentId);
        if (Predicates::isEmpty($pictures)) {
            return ;
        }
        $result = self::getTaoObjectId($momentId);
        $eventUser = Event::getEventUser($eventId);
        $commentAndLike = self::getCommentLikeUsrts($result['tao_object_id']);
        
        $pushUsers = [];
        if ($status == 0) {//pinglun
            foreach ($commentAndLike['arrayUid'] as $k => $uid) {
                $from  = self::checkRelation($uid, $loginUid, $eventUser);
                $to = self::checkRelation($uid, $momentUid, $eventUser);
                if ($from && $to) {
                    $pushUsers[$uid] = $uid;
                }
            }
        } else {
            if ($toUid != $momentUid) {
                $pushUsers[$toUid] = $toUid;
            }
        }
        $pushUsers[$momentUid] = $momentUid;
        //去掉发起人
        unset($pushUsers[$loginUid]);
        $fromUserInfo = UserModel::getUserListData([$loginUid], ['uid', 'nickname', 'avatar','status']);
        $toUserInfo = UserModel::getUserListData([$toUid], ['uid', 'nickname', 'avatar','status']);
        foreach ($pushUsers as $pushUser) {
            $payload = [
                'from_uid' => [
                    'uid' => $loginUid,
                    'nickname' => $fromUserInfo[$loginUid]['nickname'],
                    'avatar' => $fromUserInfo[$loginUid]['avatar'],
                ],
                'image_url' => 'event/moment/' . $pictures['object_id'] . '.jpg',
                'event_id' => $eventId,
                'moment_id' => $momentId,
                'content' => !$type ? $content : '', 
                'c_id' => $cId,
                'status' => $status,
                'to_uid' => [
                    'uid' => $status ? intval($toUid) : '',
                    'nickname' => $status ? $toUserInfo[$toUid]['nickname'] : '',
                    'avatar' => $status ? $toUserInfo[$toUid]['avatar'] : '',
                ],
                'type' => $type,
            ];
            Push::pushTo($loginUid, $pushUser, 0, $payload);
        }
    }
    
    public static function message ($uid, $cursor, $id, $limit = 10)
    {
        $search  = true;
        $data = [];
        $is_last = true;
        while ($search) {
            if ($id) {
                $where = 'to_uid = '.$uid.' and event_type = 0 and id > '.$cursor.' and id < '. $id;
            } else {
                $where = 'to_uid = '.$uid.' and event_type = 0 and id > '.$cursor;
            }
            $messages = (new Query())->select('id, to_uid, from_uid, unix_timestamp(event_time)*1000 as event_time, payload')
                ->from(Us\TableName\TUBE_USER_EVENT)
                ->where($where)
                ->limit($limit)
                ->orderBy(['id' => SORT_DESC])
                ->all();
            if (!$messages) {
                break;
            }
            foreach ($messages as $message) {
                $payload = json_decode($message['payload'], true);
                if ($payload['type'] != 1) {
                    $search = false;
                    break;
                }
                $id = $message['id'];
            }
            $data = array_merge($data, $messages);
        }
        if (count($data)) {
            $last_id = end($data);
            $count = (new Query())->select('*')
                    ->from(Us\TableName\TUBE_USER_EVENT)
                    ->where('to_uid = '.$uid.' and event_type = 0 and id > '.$cursor.' and id < '. $last_id['id'])
                    ->count();
            if ($count > 0) {
                $is_last = false;
            }
        }
        return ['list' => $data, 'is_last' => $is_last];
    }
    
    //删除空格
    public static function trimall($str)
    {
        return str_replace(array(" ","　","\t","\n","\r"),array("","","","",""),$str);
    }
    
    //获取动态对应的图数据库id
    public static function getTaoObjectId($momentId)
    {
        return (new Query())
        ->select('tao_object_id')
        ->from(Us\TableName\EVENT_MOMENT)
        ->where('id = '.$momentId.' and status = 0')
        ->one();
    }
    
    /*获取赞和评论用户*/
    public static function getCommentLikeUsrts($taoObjectId, $type = 0, $uid = null, $eventUid= null)
    {
        $momentComList = self::fetchComment($taoObjectId);
        $momentPraList = self::fetchLike($taoObjectId);
        $comUids = [];
        $linUids = [];
        if(!empty($momentComList[1])){
            if (is_array($momentComList[1])) {
                array_walk($momentComList[1], function(&$value, $key) use (&$comUids) {
                    $comUids[$value->properties->uid] = $value->properties->uid;
                    if (!empty($value->properties->to)) {
                        $comUids[$value->properties->to] = $value->properties->to ?  $value->properties->to : [];
                    }
                });
            } else {
                if (!empty($momentComList[1]->properties->uid)) {
                    $comUids[$momentComList[1]->properties->uid] = $momentComList[1]->properties->uid;
                }
                if (!empty($momentComList[1]->properties->to)) {
                    $comUids[$momentComList[1]->properties->to] = $momentComList[1]->properties->to;
                }
            }
        }
        if (!empty($uid)) {
            $comUids[] = $uid;
        }
        if (!empty($eventUid)) {
            $comUids[] = $eventUid;
        }
        if (!empty($momentPraList[1])) {
            array_walk($momentPraList[1], function(&$value, $key) use (&$linUids) {
                $linUids[$value->properties->uid] = $value->properties->uid;
            });
        }
        $uids[] = array_unique(array_merge($comUids, $linUids));
        switch ($type) {
            case 0:
                $pushUsers = [];
                foreach ($uids as $v){
                    foreach ($v as $val) {
                        $pushUsers[$val] = $val;
                    }
                }
                return ['arrayUid' => array_unique($pushUsers), 'momentComList' => $momentComList, 'momentPraList' => $momentPraList, 'linUids' => $linUids];
                break;
            case 1:
                return ['arrayUid' => $uids, 'momentComList' => $momentComList, 'momentPraList' => $momentPraList, 'linUids' => $linUids];
                break;
            default:
                return ['arrayUid' => $uids, 'momentComList' => $momentComList, 'momentPraList' => $momentPraList, 'linUids' => $linUids];
        }
    }
    
    public static function fetchLike($momentTaoObjectId)
    {
        $momentTaoObjectId = intval($momentTaoObjectId);
        if (($listCount = count($likes = Tao::getAssociationRange($momentTaoObjectId, "LIKED_BY", 0, Moment::LIMIT))) > Moment::LIMITCOUNT) {
            $count = Tao::countAssociation($momentTaoObjectId, "LIKED_BY");
        } else {
            $count = $listCount;
        }
        return [$count, $likes];
    }
    
    public static function fetchComment($momentTaoObjectId, $limit = 0)
    {
        $momentTaoObjectId = intval($momentTaoObjectId);
        $comments = Tao::getAssociationRange($momentTaoObjectId, "COMMENT", 0, Moment::LIMIT);
        if (Predicates::isNotEmpty($comments)) {
            if (($listCount = count($comments = Tao::getObjectArray(array_reduce($comments, function($carry, $item) { $carry[] = $item->to; return $carry;}, [])))) >  Moment::LIMITCOUNT) {
                $count = Tao::countAssociation($momentTaoObjectId, "COMMENT");
            } else {
                $count = $listCount;
            }
        } else {
            $count = 0;
            $comments = [];
        }
        return [$count, $comments];
    }
    
    /*排序*/
    public static function sortData($data)
    {
        foreach ($data as $key => $row)
        {
            $volume[$key]  = $row->version;
            $edition[$key] = $row->timestamp;
        }
        array_multisort( $edition, SORT_ASC, $volume, SORT_ASC, $data);
        return $data;
    }
                    
    /* 一个人是否对某条动态赞过 */
    public static function isLikeMomentByUid($eventId, $momentId, $uid, $momentTaoObjId = null, $userTaoObjId = null) {
        if(Predicates::isNull($momentTaoObjId) && Predicates::isNull($userTaoObjId)) {
            $taoMoment = (new Query())->from(Us\TableName\EVENT_USER . " as eu")
            ->select("em.tao_object_id, u.tao_object_id as user_tao_object_id")
            ->innerJoin(Us\TableName\EVENT_MOMENT . " as em", "eu.event_id = eu.event_id")
            ->innerJoin(Us\TableName\USER . " as u", "eu.member_uid = u.uid")
            ->where(["eu.event_id" => $eventId, "eu.member_uid" => $uid, "eu.is_deleted" => 0, "em.status" => 0, "em.id" => $momentId])
            ->one();
            if(!$taoMoment || Predicates::isEmpty($taoMoment) || Predicates::isNull($taoMoment)) {
                return false;
            }
            $momentTaoObjId = $taoMoment["tao_object_id"];
            $userTaoObjId = $taoMoment["user_tao_object_id"];
        }
        return Tao::associationExists($momentTaoObjId, "LIKED_BY", $userTaoObjId);
   }

   public static function getCommentListByMids($momentIds)
   {
        if (Predicates::isEmpty($momentIds)) {
            return -1;
        }
        $query = new Query;
        $query->select('tas.version, from_object_id as ftao, to_object_id as ttao, tas.update_time as updateTime, tas.create_time as timestamps, ob.data as properties') 
            ->from(Us\TableName\TAO_ASSOCIATION_STORE.' as tas')
            ->innerJoin(Us\TableName\TAO_OBJECT_STORE.' as ob', 'ob.object_id=tas.to_object_id')
            ->where(['association_type' => 3])
            ->andWhere(['in', 'from_object_id', $momentIds])
            ->orderBy('tas.version');
        $moment = $query->all();
        $response = [];
        if ($moment) {
            array_walk($moment, function(&$value, $key) use(&$response) {
                $value['from'] = $value['ftao'];
                unset($value['ftao']);
                if (@$response[$value['from']]['comment_count']<self::LIMITCOUNT) {
                    $value['to'] = $value['ttao'];
                    unset($value['ttao']);
                    $value['updateTime'] = strtotime($value['updateTime']) * 1000;
                    $value['id'] = $value['to'];
                    $value['timestamps'] = strtotime($value['timestamps']) * 1000;
                    $value['properties'] = json_decode($value['properties']);
                    $response[$value['from']][] = $value;
                    @$response['user'][] = $value['properties']->uid;
                }
                @$response[$value['from']]['comment_count'] ++;
            });
        }
        return $response;
   }

   public static function getLikeListByMids($momentIds)
   {
       if (Predicates::isEmpty($momentIds)) {
           return -1;
       }
       $query = new Query;
       $query->select('version, from_object_id as ftao, to_object_id as to, update_time as updateTime, create_time as timestamps, data as properties') ->from(Us\TableName\TAO_ASSOCIATION_STORE)
            ->where(['association_type' => 4])
            ->andWhere(['in', 'from_object_id', $momentIds])
            ->orderBy('version');
       $like = $query->all();
       $response = [];
       if ($like) {
           array_walk($like, function($value, $key) use(&$response) {
               $value['from'] = $value['ftao'];
               unset($value['ftao']);
               if (@$response[$value['from']]['like_count']<self::LIMITCOUNT) {
                   $value['updateTime'] = strtotime($value['updateTime']) * 1000;
                   $value['timestamps'] = strtotime($value['timestamps']) * 1000;
                   $value['properties'] = json_decode($value['properties']);
                   $response[$value['from']][] = $value;
                   @$response['user'][] = $value['properties']->uid;
               }
               @$response[$value['from']]['like_count'] ++;
           });
       }
       return $response;
   }

   public static function getEventPicAllInfo($eid, $cursor='', $limit=20)
   {
       if ($cursor) {
           $cursor = json_decode(base64_decode($cursor), true);
           $momentId = $cursor['moment_id'];
           $tubeId = $cursor['tube_id'];
       } else {
           $tubeId = $momentId = PHP_INT_MAX;
       }
       $connection = Yii::$app->db;
       $sql = "select e.uid as event_uid, e.status, m.uid, u.nickname, u.avatar, e.name, m.id as moment_id, m.event_id, m.tao_object_id ,
                unix_timestamp(m.create_time) * 1000 as create_time, unix_timestamp(e.start_time) * 1000 as event_start_time, e.cover_page as event_cover_page,
                p.object_id, p.size, p.id as picture_id, p.content, unix_timestamp(p.shoot_time) * 1000 as shoot_time
                from (select event_id, id, uid, create_time, tao_object_id from " . Us\TableName\EVENT_MOMENT ."
                where event_id = ".$eid." and status = 0 and id < " .$momentId . "
                order by create_time desc limit " . $limit . " ) as m
                inner join ". Us\TableName\EVENT . " as e on e.id = m.event_id
                inner join ". Us\TableName\USER . " as u on u.uid = m.uid
                inner join " . Us\TableName\MOMENT_PICTURE . " as p on m.id = p.moment_id and m.event_id = p.event_id where p.status = 0
                order by moment_id desc, shoot_time asc, picture_id asc";
       $list = $connection->createCommand($sql)->queryAll();
       
       $response = [];
       if ($list) {
           $lastMid = PHP_INT_MAX;
           array_walk($list, function(&$val, &$key) use(&$response, &$lastMid) {
               $tmpEvent = new stdClass();
               Accessor::wrap($val)->copyOptional(['event_uid', 'status', 'uid', 'nickname', 'avatar', 'name', 'moment_id', 'event_id', 'tao_object_id', 'create_time', 'event_start_time', 'event_cover_page', 'shoot_time'], $tmpEvent);
               $tmpEvent->event_cover_page = "event/coverpage/".$tmpEvent->event_cover_page.".jpg";
               $tmpEvent->avatar = "profile/avatar/".$tmpEvent->avatar.".jpg";
               $tmpEvent->name = isset($tmpEvent->name)?$tmpEvent->name:"";
               $tmpEvent->moment_type = 3;    //0-活动动态，3-点点滴滴动态
               $response[$val['tao_object_id']] = $tmpEvent;
               @$response['user'][] = $tmpEvent->uid;
               if ($tmpEvent->moment_id<=$lastMid) {
                   $lastMid = $tmpEvent->moment_id;
               }
           });
           array_walk($list, function(&$val, &$key) use(&$response) {
               $tmpPic = new stdClass();
               Accessor::wrap($val)->copyOptional(['picture_id', 'size', 'shoot_time', 'content'], $tmpPic);
               $tmpPic->content = $tmpPic->content?$tmpPic->content:"";
               $tmpPic->url = "event/moment/".$val['object_id'].".jpg";
               @$response[$val['tao_object_id']]->picture[] = $tmpPic;
           });
           $response['cursor'] = base64_encode(json_encode(['moment_id' => $lastMid, 'tube_id' => 0]));
       }
       return $response;
   }
   
    /**
     * 根据活动ID获取组成员
     */
   public static function getGroupUser($event_id)
   {
      $groupInEvent = Group::queryGroupProfileByEid($event_id);
      $groupGid = [];
      array_walk($groupInEvent, function(&$value, $key) use (&$groupGid) {
          $groupGid[] = self::GroupInUid($value['gid']);
      });
      return $groupGid;
   }
   
   /**
    * 根据gid获取组成员
    * @param unknown $gid
    */
   public static function GroupInUid($gid)
   {
       $groupOwner = GroupModel::getGroupOwner($gid, 0, 0x7FFFFFFF);
       $groupUid = GroupModel::getGroupMember($gid, 0, 0x7FFFFFFF);
       $guid = [];
       array_walk($groupUid, function(&$value, $key) use (&$guid) {
           $guid[] = $value->properties->uid;
       });
       return array_unique(array_merge(explode(',', $groupOwner), $guid));
   }
   
    public static function filterComment($loginUid, $momentUid, $commentData, $eventUser, $eventId)
    {
       if ($loginUid == $momentUid) {
           return $commentData;
       }
       $commentData = is_array($commentData) ? $commentData : array($commentData);
       $filterData = [];
       //$groupList = self::dataProcess(GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF), 'to');
       foreach ($commentData as $key => $val) {
           //评论人是login_uid
           if (@$val->properties->uid == $loginUid || @$val->properties->to == $loginUid) {
               $filterData[] = $val;
           } else {
               $from = self::checkRelation($loginUid, @$val->properties->uid, $eventUser);
               if (@$val->properties->to) {
                   $to = self::checkRelation($loginUid, @$val->properties->to, $eventUser);
                   if ($from && $to) {
                       $filterData[] = $val;
                   }
               } else {
                   $moment = self::checkRelation($loginUid, $momentUid, $eventUser);
                   if ($from && $moment) {
                       $filterData[] = $val;
                   }
               }
           }
       }
       return $filterData;
    }
    
    public static function filterPraise ($loginUid, $momentUid, $PraiseData, $eventUser, $eventId)
    {
        if ($loginUid == $momentUid) {
            return $PraiseData;
        }
        $filterData = [];
        //$groupList = self::dataProcess(GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF), 'to');
        foreach ($PraiseData as $key => $val) {
            //评论人是login_uid
            if ($val->properties->uid == $loginUid) {
                $filterData[] = $val;
            } else {
                $from = self::checkRelation($loginUid, $val->properties->uid, $eventUser);
                $moment = self::checkRelation($loginUid, $momentUid, $eventUser);
                if ($from && $moment) {
                    $filterData[] = $val;
                }
            }
        }
        return $filterData;
    }
    public static function checkRelation ($loginUid, $fromUid, $eventUser)
    {
       if (in_array($loginUid, $eventUser) && in_array($fromUid, $eventUser)) {
           return true;
       }
       $mergeGroups = array_intersect(self::getGroupListByUid($loginUid), self::getGroupListByUid($fromUid));
       if (count($mergeGroups) > 0) {
           return true;
       }
       return false;
    }
    
    public static function getGroupListByUid ($uid)
    {
        if (Predicates::isNotEmpty(@self::$groupList[$uid])) {
            return self::$groupList[$uid];
        }
        $ownerGroups = self::dataProcess(GroupModel::getOwnerGroupListByUid($uid, 0, 0x7FFFFFFF), 'to');
        $memberGroups = self::dataProcess(GroupModel::getMemberGroupListByUid($uid, 0, 0x7FFFFFFF), 'to');
        $groupList = array_merge($ownerGroups, $memberGroups);
        self::$groupList[$uid] = $groupList;
        return $groupList;
    }
    
    public static function dataProcess ($obj, $key) {
       $vals = [];
       array_walk($obj, function ($item, $index) use (&$vals, &$key) {
           $vals[] = $item->$key;
       });
       return $vals;
    }

   /**
    * 根据活动ID获取活动成员
    */
    public static function geteventUser($event_id)
    {
         return Event::getEventUser($event_id);
    }
    
    /**
     * 判断评论是否满足可见
     */
    public static function isVisibleComment($eventId, $loginUid, $momentUid, $comment, $like)
    {
        $groupUserUid = Moment::getGroupUser($eventId);  
        $eventUserUid = Moment::geteventUser($eventId);
        //组员二维转一维
        foreach ($groupUserUid as $key => $val) {
            $groupUserUids[] = $val;
        }
        //判断登录者与创建者是否为一人
        if ($loginUid == $momentUid) {
            return ['comList' => $comment, 'praList' => $like, 'momentPraListCount' => count($like), 'momentComListCount' => count($comment)];
        }
        $newComemt = [];
        $newLike = [];
        /*评论*/
        if (is_array($comment)) {
            array_walk($comment, function(&$value, $key) use (&$newComemt, &$eventUserUid, &$loginUid, &$momentUid, &$groupUserUids) {
                if (empty($value->properties->to)) {
                    $newComemt = Moment::judgment(@$value->properties->uid, $newComemt, $eventUserUid, $loginUid, $momentUid, $groupUserUids, @$value);
                }
            });
        } else {
            $newComemt = Moment::judgment($comment->properties->uid, $newComemt, $eventUserUid, $loginUid, $momentUid, $groupUserUids, $comment);
        }
        /*回复*/
        array_walk($comment, function(&$value, $key) use (&$newComemt, &$eventUserUid, &$loginUid, &$groupUserUids) {
            //评论被回复者的所有组
            if ($loginUid == @$value->properties->to) {
                $newComemt[] = $value;
            } elseif (in_array(@$value->properties->to, $eventUserUid) && in_array($loginUid, $eventUserUid) && in_array(@$value->properties->uid, $eventUserUid)){
                $newComemt[] = $value;
            } else {
                if (!empty($groupUserUids)) {
                    foreach ($groupUserUids as $k => $v) {
                        if (in_array($loginUid, $eventUserUid) && in_array(@$value->properties->uid, $eventUserUid) && (in_array(@$value->properties->to, $v) &&
                            in_array($loginUid, $v))){
                                $newComemt[] = $value;
                        } elseif (in_array($loginUid, $v) && in_array(@$value->properties->uid, $v) && in_array(@$value->properties->to, $v)){
                            $newComemt[] = $value;
                        } elseif (in_array($loginUid, $v) && in_array(@$value->properties->uid, $v) && in_array(@$value->properties->to, $eventUserUid)){
                            $newComemt[] = $value;
                        }
                    }
                }
            }
        });
        /*点赞*/
        array_walk($like, function(&$value, $key) use (&$newLike, &$eventUserUid, &$loginUid, &$momentUid, &$groupUserUids) {
            $newLike = Moment::judgment($value->properties->uid, $newLike, $eventUserUid, $loginUid, $momentUid, $groupUserUids, $value);
        });
        
        return ['praList' => $newLike, 'momentPraListCount' => count($newLike), 'comList' => $newComemt, 'momentComListCount' => count($newComemt)];
    }
    
    public static function judgment($data, $newComemt, $eventUserUid, $loginUid, $momentUid, $groupUserUids, $value)
    {
        if ($loginUid == @$data) {
            $newComemt[] = $value;
        } elseif (in_array($loginUid, $eventUserUid) && in_array(@$data, $eventUserUid) && in_array($momentUid, $eventUserUid)) {
            $newComemt[] = $value;
        } else {
            foreach ($groupUserUids as $k => $v) {
                if (in_array(@$data, $v) && in_array($loginUid, $v) && in_array($momentUid, $v)) {
                    $newComemt[] = $value;
                } elseif (in_array(@$data, $v) && in_array($loginUid, $v) && in_array($momentUid, $eventUserUid)) {
                    $newComemt[] = $value;
                } elseif (in_array(@$data, $eventUserUid) && in_array($loginUid, $eventUserUid) && in_array($momentUid, $v)) {
                    $newComemt[] = $value;
                }
            }
        }
        return $newComemt;
    }
    
 }
?>
