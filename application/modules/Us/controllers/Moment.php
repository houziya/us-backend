<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
use yii\db\Exception;
use Moca\Tao\Object;

class MomentController extends Controller_Abstract
{
    public static $tableEvent = Us\TableName\EVENT;
    public static $tableEventUser = Us\TableName\EVENT_USER;
    public static $tableEventMoment = Us\TableName\EVENT_MOMENT;
    public static $tableMomentPicture = Us\TableName\MOMENT_PICTURE;
    public static $tableUser = Us\TableName\USER;
    public static $tableTubeEvent = Us\TableName\TUBE_GROUP_EVENT;

    /* 动态列表接口  */
    public function listAction()
    {
        $data = Protocol::arguments();
        //Protocol::ok(self::fetchMoment($data));
        Protocol::ok(self::group($data));
    }

    /* 我的动态接口  */
    public function myMomentAction()
    {
        $data = Protocol::arguments();
        $sql =" and uid = {$data->requiredInt('login_uid')} "; //查询关于我参加的活动时拼接  sql(只关于我自己的动态)
        Protocol::ok(self::group($data, $sql));
    }

    /* 活动动态删除接口  */
    public function deleteAction()
    {
        /* 接收参数 start */
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId = $data->requiredInt('event_id');//活动Id
        $momentId = $data->requiredInt('moment_id');//活动现场Id
        $device_id= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $version= $data->optional('version');
        /* 接收参数end */

        $connection = Yii::$app->db;
        Execution::autoTransaction(Yii::$app->db, function() use($connection, $momentId, $eventId, $loginUid, $version) {
            /*判断有没有删除的权限start  */
            if(!Event::isCanModifyMomentOrPicutre($loginUid, $eventId, $momentId)) {
                Protocol::badRequest("","","You don't permission to delete moment");
                return;
            }
            /*判断有没有删除的权限end  */
            if (Event::doVerifyEventStatus(Event::EVENT_STATUS_LOCK, $eventId)) {
                /*修改现在图片为删除状态 start */
                //Event::doTableUpdate(Event::$tableMomentPicture, ['status'=>Event::STATUS_PICTURE_LOCK_DELETE], "moment_id = :moment_id and event_id = :event_id and status = :status", [":moment_id"=>$momentId, ":event_id"=>$eventId, ":status"=>Event::PICTURE_STATUS_NORMAL]);
                Protocol::forbidden( null, Notice::get()->eventLocked() . ":" . User::getUserNickname(Event::GetEventInfoByEvent($eventId, 'uid', $version)));
                /*修改现在图片为删除状态 end */
            } else {
                /*删除动态及动态下所有评论和赞 start */
                Event::doEventMomentUpdate($eventId, $momentId, ['status'=>Event::STATUS_MOMENT_DELETE]);
                //Event::doTableUpdate(Us\TableName\MOMENT_LIKE, ['status' => Moment::STATUS_LIKE_DELETE], "moment_id = :moment_id and event_id = :event_id", [':moment_id' => $momentId, ':event_id' => $eventId]);
                //Event::doTableUpdate(Us\TableName\MOMENT_COMMENT, ['is_deleted' => Moment::STATUS_COMMENT_DELETE], "moment_id = :moment_id and event_id = :event_id", [':moment_id' => $momentId, ':event_id' => $eventId]);
                /*删除动态及动态下所有评论和赞 end */
                /*修改现在图片为删除状态 start */
                Event::doTableUpdate(Event::$tableMomentPicture, ['status'=>Event::STATUS_PICTURE_DELETE], "moment_id = :moment_id and event_id = :event_id and status = :status", [":moment_id"=>$momentId, ":event_id"=>$eventId, ":status"=>Event::PICTURE_STATUS_NORMAL]);
                /*修改现在图片为删除状态 end */
                /*推送删除动态start  */
                Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_DELETE);
                /*推送删除动态end  */
            }
            Protocol::ok('','',"success");
        });
    }

    public function taoCreateCommentAction()
    {
        self::taoCreateComment(Protocol::requiredInt("event_id"), Protocol::requiredInt("moment_id"), Protocol::requiredInt("login_uid"), Protocol::required("content"), Protocol::optional("to_uid"), Protocol::optional("type"));
    }

    private static function taoCreateComment($eventId, $momentId, $loginUid, $content, $toUid = null, $type)
    {
        if (!($taoMoment = Event::isMomentCommentable($eventId, $momentId, $loginUid, ["tao_object_id", "uid"], 'menber')) && !($taoMoment = Group::verifyEventGroupExistUid($eventId, $loginUid, $toUid, $momentId, 1, ["tao_object_id", "uid"]))) {
            Protocol::badRequest("","该条动态已经被删除/您已不是相关成员","Moment $momentId of event $eventId is not commentable by user $loginUid");
            return;
        }
        
        //Preconditions::checkArgument($taoMoment = Event::isMomentCommentable($eventId, $momentId, $loginUid, ["tao_object_id"]), "Moment $momentId of event $eventId is not commentable by user $loginUid");
        $properties = ["content", $content, "uid", $loginUid];
        if (Predicates::isNotEmpty($toUid) && $type != 0) {
            $properties[] = "to";
            $properties[] = $toUid;
        }
        $comment = Tao::addObjectArray("COMMENT", $properties);
        $association = Tao::addAssociation($taoMoment["tao_object_id"], "COMMENT", $comment->id, $comment->timestamp, "uid", $loginUid);
        Protocol::ok(["cid" => $comment->id],'',"success");
        Moment::commentPush($loginUid, $eventId, $momentId, $comment->id, $content, $type == 0 ? 0 : 1, $toUid, 0, $taoMoment["uid"]);
        //MiPush::commentSendMessage($loginUid, $type == 0 ? 0 : 1, [$toUid], '');
        $payload = [];
        $eType = ($type == 0) ? 0 : 1;
        $payload[] = ["uid" => $loginUid, "e_type" =>$eType, "users" =>[$toUid], "type" =>MiPush::COMMENT];
        MiPush::submitWorks($payload);
    }

    private static function taoDeleteComment($eventId, $momentId, $commentId, $loginUid, $type, $toUid)
    {
        if (!$taoMoment = Event::isMomentCommentable($eventId, $momentId, $loginUid, ["tao_object_id", "uid"])) {
            Protocol::badRequest("","该条动态已经被删除","Moment $momentId of event $eventId is not commentable by user $loginUid");
            return;
        }
        //         Preconditions::checkArgument($taoMoment = Event::isMomentCommentable($eventId, $momentId, $loginUid, ["tao_object_id"]), "Moment $momentId of event $eventId is not commentable by user $loginUid");
        $comment = Tao::getObject($commentId);
        Preconditions::checkArgument($comment->type === "COMMENT", "Object $commentId is not type of COMMENT");
        Preconditions::checkArgument($comment->properties->uid == $loginUid, "Comment $commentId is not created by user $loginUid");
        Tao::deleteAssociation($taoMoment["tao_object_id"], "COMMENT", $commentId);
        Tao::deleteObject($commentId);
        Protocol::ok('','',"success");
        Moment::commentPush($loginUid, $eventId, $momentId, $commentId, '', $type == 0 ? 0 : 1, $toUid, 1, $taoMoment["uid"]);
    }

    public function taoDeleteCommentAction()
    {
        self::taoDeleteComment(Protocol::requiredInt("event_id"), Protocol::requiredInt("moment_id"), Protocol::required("comment_id"), Protocol::requiredInt("login_uid"), Protocol::requiredInt("type"), Protocol::requiredInt("to_uid"));
    }

    private function taoLike($eventId, $momentId, $loginUid, $delete)
    {
        if (!($taoMoment = Event::isMomentLikable($eventId, $momentId, $loginUid, ["tao_object_id", "uid"], 'menber')) && !($taoMoment = Group::verifyEventGroupExistUid($eventId, $loginUid, NULL, $momentId, 1, ["tao_object_id", "uid"]))) {
            Protocol::badRequest("","该条动态已经被删除/您已不是相关成员","Moment $momentId of event $eventId is not commentable by user $loginUid");
            return;
        }
        //         Preconditions::checkArgument($taoMoment = Event::isMomentLikable($eventId, $momentId, $loginUid, ["tao_object_id"]), "Moment $momentId of event $eventId is not likable by user $loginUid");
        $association = Tao::getAssociation($taoMoment["user_tao_object_id"], "LIKES", $taoMoment["tao_object_id"]);
        //         $momentInfo = Event::GetMomentInfoByEvent($eventId,$momentId);
        if ($delete) {
            Preconditions::checkArgument(Tao::deleteAssociation($taoMoment["tao_object_id"], "LIKED_BY", $taoMoment["user_tao_object_id"]), "Failed to cancel like");
        } else {
            if ($association) {
                Tao::addAssociation($taoMoment["tao_object_id"], "LIKED_BY", $taoMoment["user_tao_object_id"], "uid", $loginUid);
            } else {
                Tao::addAssociation($taoMoment["tao_object_id"], "LIKED_BY", "LIKES", $taoMoment["user_tao_object_id"], "uid", $loginUid);
                Moment::likePush($loginUid, $eventId, $momentId, $taoMoment["uid"]);
                //MiPush::commentSendMessage($loginUid, 2, [$taoMoment["uid"]], '');
                $payload = [];
                $payload[] = ["uid" => $loginUid, "e_type" => 2, "users" =>[$taoMoment["uid"]], "type" =>MiPush::COMMENT];
                MiPush::submitWorks($payload);
            }
        }
        Protocol::ok('','',"success");
    
    }

    public function taoLikeAction()
    {
        self::taoLike(Protocol::requiredInt("event_id"), Protocol::requiredInt("moment_id"), Protocol::requiredInt("login_uid"), Protocol::requiredInt("type"));
    }

    /**
     * 动态详情
     */
    public function detailCommentAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $pictureInfo = Event::getEventMomentPictureInfoByMoment($data->requiredInt('event_id'), $data->requiredInt('moment_id'), "p.moment_id, p.event_id, unix_timestamp(shoot_time)*1000 as shoot_time, concat('event/moment/',`object_id`, '.jpg') as object_id, concat('event/coverpage/', `cover_page`, '.jpg') as cover_page, unix_timestamp(start_time)*1000 as start_time, unix_timestamp(m.create_time)*1000 as create_time, m.uid as m_uid, m.tao_object_id, p.size, p.content, p.id, m.id as m_id, e.uid as e_uid, e.name, u.nickname,  e.status, concat('profile/avatar/', u.avatar, '.jpg') as avatar");
        if(Predicates::isEmpty($pictureInfo)) {
            //查询sql语句里面已经验证活动和动态的状态
//             Protocol::badRequest(NULL, NULL, 'event or moment is not found');
            Protocol::notFound("", "该条动态已经被删除", 'event or moment is not found');
        }
        $result = [];
        array_walk($pictureInfo, function(&$value, $key) use (&$result, &$taoObjectId) {
            $result['picture'][] = ["picture_id" => $value['id'], "size" => $value['size'], "url" =>  $value['object_id'], "content" => Predicates::isNotEmpty($value['content']) ? $value['content'] : "", "shoot_time" => $value['shoot_time'],];
            $result['moment_id'] = $value['m_id'];
            $result['event_uid'] = $value['e_uid'];
            $result['uid'] = $value['m_uid'];
            $result['name'] = Predicates::isEmpty($value['name']) ? '' : $value['name'];
            $result["event_cover_page"] = $value['cover_page'];
            $result["event_start_time"] = $value['start_time'];
            $result['event_id'] = $value['event_id'];
            $result['create_time'] = $value['create_time'];
            $result['status'] = $value['status'];
            if ($group = Group::verifySpecialEvent($value['event_id'])) {
                $result['moment_type'] = 3;
                $result['group_id'] = $group['group_id'];
                $result['group_name'] = $group['group_name'];
            } else {
                $result['moment_type'] = 0;
            }
            $taoObjectId = $value['tao_object_id'];
        });
        
            $comLinkDate = Moment::getCommentLikeUsrts($taoObjectId, 0, $result['uid'], $result['event_uid']);
            $result['is_like'] = in_array($data->requiredInt('login_uid'), $comLinkDate['linUids']);
            $result['properties'] = UserModel::getUserListData($comLinkDate['arrayUid'], ['avatar', 'nickname']);
            
            $eventUser = Event::getEventUser($data->requiredInt('event_id'));
            $comLinkDate['momentComList'][1] = Moment::filterComment($data->required('login_uid'), $result['uid'], $comLinkDate['momentComList'][1], $eventUser, $data->requiredInt('event_id'));
            $comLinkDate['momentPraList'][1] = Moment::filterPraise($data->required('login_uid'), $result['uid'], $comLinkDate['momentPraList'][1], $eventUser, $data->requiredInt('event_id'));
            
            /*评论限制开始*/
//             $comLinkDate = Moment::isVisibleComment($data->requiredInt('event_id'), $data->requiredInt('login_uid'), $result['uid'], $comLinkDate['momentComList'][1], $comLinkDate['momentPraList'][1]);
//             $comLinkDate['momentComList'][1] = $comLinkDate['momentComList'];
//             $comLinkDate['momentPraList'][1] = $comLinkDate['momentPraList'];
            /*评论限制结束*/
            
            if (count($comLinkDate['momentComList'][1]) > 1){
                $comLinkDate['momentComList'][1] = Moment::sortData($comLinkDate['momentComList'][1]);
            }
            if (count($comLinkDate['momentPraList'][1]) > 1){
             $comLinkDate['momentPraList'][1] = Moment::sortData($comLinkDate['momentPraList'][1]);
            }
            $result['comment'] = is_array($comLinkDate['momentComList'][1]) ? $comLinkDate['momentComList'][1] : array($comLinkDate['momentComList'][1]);
            $result['comment_count'] = count($comLinkDate['momentComList'][1]);
            $result['like'] = $comLinkDate['momentPraList'][1];
            $result['like_count'] = count($comLinkDate['momentPraList'][1]);
            Protocol::ok($result, "", "success");
    }

    public function messageListAction ()
    {
        $data = Protocol::arguments();
        $id = $data->optional('id', 0);
        $key = 'del_message_list_'.$data->requiredInt('login_uid');
        $cursor = yii::$app->redis->get($key);
        if (!$cursor) {
            $cursor = 0;
        }
        $messages = Moment::message($data->requiredInt('login_uid'), $cursor, $id, 20);
        Protocol::ok($messages);
    }

    public function delMessageAction ()
    {
        $data = Protocol::arguments();
        $cursor = $data->requiredInt('id');
        $key = 'del_message_list_'.$data->requiredInt('login_uid');
        yii::$app->redis->set($key, $cursor);
        Protocol::ok(['result' => true]);
    }

    private static function fetchMoment($data, $sql = '')
    {
        $loginUid = Protocol::requiredInt('login_uid');//登录uid
        $cursor = Protocol::optional('cursor');//游标(分页参数)
        $limit = Protocol::optionalInt('limit', 20);
        if ($limit > 100) {
            $limit = 100;
        }
        $type = Protocol::optional('type'); //推送通知类型 0代表现场推送
        $deviceId = Protocol::optional('device_id');//设备号
        $platform = Protocol::optional('platform');//渠道 0-iphone1-android
        if (Predicates::isNotNull($cursor)) {
            $cursor = json_decode(base64_decode($cursor), true);
            $momentId = $cursor['moment_id'];
        } else {
            $momentId = PHP_INT_MAX;
        }
        $list = (new Query())->select("e.uid as event_uid, e.status, m.uid, u.nickname, u.avatar, e.name, m.id as moment_id, m.event_id, m.tao_object_id, " .
            "unix_timestamp(m.create_time) * 1000 as create_time, unix_timestamp(e.start_time) * 1000 as event_start_time, e.cover_page as event_cover_page, " .
            "p.object_id, p.size, p.id as picture_id, p.content, unix_timestamp(p.shoot_time) * 1000 as shoot_time")->from("(select event_id, id, uid, create_time, tao_object_id from " . self::$tableEventMoment .
            " where event_id in (select id from " . self::$tableEvent . " where event_id in (select event_id from " . self::$tableEventUser .
            " where member_uid = " . $loginUid . " and is_deleted = 0) and status != 1) and status = 0 " . $sql . " and id < " .
            $momentId . " order by create_time desc limit " . $limit . ") as m")
            ->innerJoin(self::$tableEvent ." as e", "e.id = m.event_id")
            ->innerJoin(self::$tableUser . " as u", "u.uid = m.uid")
            ->innerJoin(self::$tableMomentPicture . " as p", "m.id = p.moment_id and m.event_id = p.event_id")
            ->where("p.status = 0")->orderBy(["moment_id" => SORT_DESC, "shoot_time" => SORT_ASC, "picture_id" => SORT_ASC])->all();
        $lastMomentId = -PHP_INT_MAX;
        $pictures = [];
        $result = [];
        $lastMoment = null;
        $uids = [];
        foreach ($list as $moment) {
            $comLinkDate = [];
            $comLinkDate = Moment::getCommentLikeUsrts($moment['tao_object_id'], 1, '',$moment['event_uid']);
            $uids[] = $comLinkDate['arrayUid'];
            $momentId = $moment["moment_id"];
            $objectId = $moment["object_id"];
            $size = $moment["size"];
            $pictureId = $moment["picture_id"];
            $shootTimt = $moment["shoot_time"];
            $status = $moment["status"];
            $content = Predicates::isNotNull($moment['content']) ? $moment['content'] : "";
            $moment["avatar"] = "profile/avatar/" . $moment["avatar"] . ".jpg";
            $moment["event_cover_page"] = User::translationDefaultPicture("event/coverpage/" . $moment["event_cover_page"]) . ".jpg";
            if (count($comLinkDate['momentComList'][1]) > 1){
                    $comLinkDate['momentComList'][1] = array_slice(Moment::sortData($comLinkDate['momentComList'][1]), 0, Moment::LIMITCOUNT);
            }
            if (count($comLinkDate['momentPraList'][1]) > 1){
                    $comLinkDate['momentPraList'][1] = array_slice(Moment::sortData($comLinkDate['momentPraList'][1]), 0, Moment::LIMITCOUNT);
            }
            $moment['comment'] = is_array($comLinkDate['momentComList'][1]) ? $comLinkDate['momentComList'][1] : array($comLinkDate['momentComList'][1]);
            $moment['like'] = $comLinkDate['momentPraList'][1];
            $moment['comment_count'] = $comLinkDate['momentComList'][0];
            $moment['like_count'] = $comLinkDate['momentPraList'][0];
            $moment['is_like'] = in_array($loginUid, $comLinkDate['linUids']);
            if ($momentId != $lastMomentId) {
                unset($lastMoment["object_id"]);
                unset($lastMoment["size"]);
                unset($lastMoment["picture_id"]);
                unset($lastMoment["content"]);
                if (Predicates::isNotNull($lastMoment)) {
                    $lastMoment["picture"] = $pictures;
                    $result[] = $lastMoment;
                }
                $pictures = [];
            }
            $lastMomentId = $momentId;
            $lastMoment = $moment;
            $pictures[] = ["picture_id" => $pictureId, "size" => $size, "url" => "event/moment/" . $objectId . ".jpg", "content" => $content, 'shoot_time' => $shootTimt];
        }
        if (Predicates::isNotNull($lastMoment)) {
            $lastMoment["picture"] = $pictures;
            unset($lastMoment["object_id"]);
            unset($lastMoment["size"]);
            unset($lastMoment["picture_id"]);
            unset($lastMoment["content"]);
            $result[] = $lastMoment;
        }
        $pushUsers = [];
        foreach ($uids as $v){
            foreach($v as $va){
                foreach ($va as $val) {
                    $pushUsers[$val] = $val;
                }
            }
        }
        return ["list" => $result, "cursor" => base64_encode(json_encode(['moment_id' => $lastMomentId])), 'properties'=> $pushUsers ? UserModel::getUserListData($pushUsers, ['avatar', 'nickname']) : new StdClass];
    }
    
    public static function group($data, $where = '')
    {
        $data = Protocol::arguments();
        $loginUid = $data->requiredInt('login_uid');
        $version = $data->requiredInt('version');
        $platfrom = $data->requiredInt('platform');
        $cursor = Protocol::optional('cursor');//游标(分页参数)
        $limit = Protocol::optionalInt('limit', 20);
        if ($limit > 100) {
            $limit = 100;
        }
        if (Predicates::isNotNull($cursor)) {
            $cursor = json_decode(base64_decode($cursor), true);
            $momentId = $cursor['moment_id'];
            $tubeId = $cursor['tube_id'];
        } else {
            $tubeId = $momentId = PHP_INT_MAX;
        }
        $connection = Yii::$app->db;
        //我参与的活动不属于
        $allEventList = Event::JoinEventByUid($loginUid);
        $events = self::getMapByArray($allEventList, 'event_id');
        $groupAssociat = [];
        if (!$where && ($data->required('version') > 13 && $data->required('platform') == 0) || ($data->required('version') > 6 && $data->required('platform') == 1)) {
            //当前用户所有小组
            $groupList = self::getGroupList($loginUid);//Moment::getGroupListByUid($loginUid);
            //所有活动;
            $groupEvents = [];
            $groupMap = [];
            array_walk($groupList, function ($group, $key) use (&$groupEvents, &$groupMap) {
                $events = GroupModel::getGroupAssociatEvent($group->to, 0, 0x7FFFFFFF);
                array_walk($events, function ($event, $k) use (&$groupEvents, &$groupMap, $key) {
                    $groupEvents[] = $event->properties->eid;
                    $groupMap[$key][] = $event->properties->eid;
                });
            });
            $groups = self::getMapByObj($groupList, 'to');
            $groupInfo = GroupModel::getGroupListData($groups);
            $eventInfo = Group::changeKey(Event::getAllEventList(array_unique($groupEvents)), 'id');
            $groupAssociat = self::getGroupMoment($connection, $groupInfo, $eventInfo, $tubeId, $limit, $groupMap, $version, $platfrom);
            //活动去重
            $allEvent = array_unique(array_merge($groupEvents, $events));
        } else {
            $allEvent = $events;
        }
        if ($allEvent) {
            $moments = self::getMomentByEvents($loginUid, $connection, $allEvent, $momentId, $where, $limit, $version, $platfrom);
        } else {
            $moments = ['moments' =>[], 'properties'=>[]];
        }
        //排序
        $allData = array_merge($moments['moments'], $groupAssociat);
        $lastMomentId = $lastTubeId = -PHP_INT_MAX;
        $sliceData = [];
        if ($allData) {
            foreach ($allData as $data) {
                $createTime[] = intval($data['create_time']);
                $groupIds[] = $data['tube_id'];
            }
            array_multisort($createTime, SORT_DESC, $groupIds, SORT_DESC, $allData);
            $sliceData = array_slice($allData, 0, $limit);
            foreach ($sliceData as $k => $value) {
                if (@$value['moment_id']) {
                    $lastMomentId = $value['moment_id'];
                }
                if (@$value['tube_id']) {
                    $lastTubeId = $value['tube_id'];
                }
                //unset($sliceData[$k]['tube_id']);
            }
        }
        return ["list" => $sliceData, "cursor" => base64_encode(json_encode(['moment_id' => $lastMomentId, 'tube_id' => $lastTubeId])), 'properties'=> $moments['properties']];
    }
    
    private static function getGroupList ($loginUid)
    {
        $ownerGroupList = GroupModel::getOwnerGroupListByUid($loginUid, 0, 0x7FFFFFFF);
        $joinGroupList = GroupModel::getMemberGroupListByUid($loginUid, 0, 0x7FFFFFFF);
        $groupList = array_merge($joinGroupList, $ownerGroupList);
        $groupInfo = [];
        array_walk($groupList, function ($group, $k) use (&$groupInfo) {
            $groupInfo[$group->to] = $group;
        });
        return $groupInfo;
    }
    
    private static function getMapByObj ($obj, $key)
    {
        $vals = [];
        array_walk($obj, function ($item, $index) use (&$vals, &$key) {
            $vals[] = $item->$key;
        });
        return $vals;
    }
    
    private static function getMapByArray ($array, $key)
    {
        $vals = [];
        array_walk($array, function ($item, $index) use (&$vals, &$key) {
            $vals[] = $item[$key];
        });
        return $vals;
    }
    
    private static function getMomentByEvents ($loginUid, $connection, $allEvent, $momentId, $where, $limit, $version, $platform)
    {
        $sql = "select e.uid as event_uid, e.status, m.uid, u.nickname, u.avatar, e.name, m.id as moment_id, m.event_id, m.tao_object_id ,
                unix_timestamp(m.create_time) * 1000 as create_time, unix_timestamp(e.start_time) * 1000 as event_start_time, e.cover_page as event_cover_page,
                p.object_id, p.size, p.id as picture_id, p.content, unix_timestamp(p.shoot_time) * 1000 as shoot_time
                from (select event_id, id, uid, create_time, tao_object_id from " . self::$tableEventMoment ."
                where event_id in (".implode(',', $allEvent).") and status = 0 " . $where . " and id < " .$momentId . "
                order by create_time desc limit " . $limit . " ) as m
                inner join ".self::$tableEvent . " as e on e.id = m.event_id
                inner join ". self::$tableUser . " as u on u.uid = m.uid
                inner join " .self::$tableMomentPicture . " as p on m.id = p.moment_id and m.event_id = p.event_id where p.status = 0
                order by moment_id desc, shoot_time asc, picture_id asc";
        $list = $connection->createCommand($sql)->queryAll();
        $lastMomentId = -PHP_INT_MAX;
        $pictures = [];
        $result = [];
        $lastMoment = null;
        $uids = [];
        foreach ($list as $moment) {
            $comLinkDate = [];
            $comLinkDate = Moment::getCommentLikeUsrts($moment['tao_object_id'], 1, '',$moment['event_uid']);
            $eventUser = Event::getEventUser($moment['event_id']);
            $uids[] = $comLinkDate['arrayUid'];
            $momentId = $moment["moment_id"];
            $objectId = $moment["object_id"];
            $size = $moment["size"];
            $pictureId = $moment["picture_id"];
            $shootTimt = $moment["shoot_time"];
            $status = $moment["status"];
            $content = Predicates::isNotNull($moment['content']) ? $moment['content'] : "";
            $moment["avatar"] = "profile/avatar/" . $moment["avatar"] . ".jpg";
            $moment["event_cover_page"] = User::translationDefaultPicture("event/coverpage/" . $moment["event_cover_page"], $version, $platform) . ".jpg";
            $moment['is_like'] = in_array($loginUid, $comLinkDate['linUids']);
            $moment["name"] = $moment["name"]?$moment["name"]:"";

            if (count($comLinkDate['momentComList'][1]) < 50 && count($comLinkDate['momentComList'][1]) < 50) {
                $comLinkDate['momentComList'][1] = Moment::filterComment($loginUid, $moment['uid'], $comLinkDate['momentComList'][1], $eventUser, $moment['event_id']);
                $comLinkDate['momentPraList'][1] = Moment::filterPraise($loginUid, $moment['uid'], $comLinkDate['momentPraList'][1], $eventUser, $moment['event_id']);
                $moment['comment_count'] = count($comLinkDate['momentComList'][1]);
                $moment['like_count'] = count($comLinkDate['momentPraList'][1]);
            } else {
                /*评论限制开始*/
                $comLinkDate = Moment::isVisibleComment($moment['event_id'], $loginUid, $moment['uid'], $comLinkDate['momentComList'][1], $comLinkDate['momentPraList'][1]);
                //为空时判断//
                $comLinkDate['momentComList'][1] = $comLinkDate['comList'];
                $comLinkDate['momentPraList'][1] = $comLinkDate['praList'];
                $moment['comment_count'] = count($comLinkDate['momentComList'][1]);
                $moment['like_count'] = count($comLinkDate['momentPraList'][1]);
                /*评论限制结束*/
            }
            
            if ($moment['comment_count'] > 1){
                $comLinkDate['momentComList'][1] = array_slice(Moment::sortData($comLinkDate['momentComList'][1]), 0, Moment::LIMITCOUNT);
            }
            if ($moment['like_count'] > 1){
                $comLinkDate['momentPraList'][1] = array_slice(Moment::sortData($comLinkDate['momentPraList'][1]), 0, Moment::LIMITCOUNT);
            }
            
            $moment['comment'] = is_array($comLinkDate['momentComList'][1]) ? $comLinkDate['momentComList'][1] : array($comLinkDate['momentComList'][1]);
            $moment['like'] = $comLinkDate['momentPraList'][1];
            $moment['tube_id'] = 0;
            if ($group = Group::verifySpecialEvent($moment['event_id'])) {
                $moment['moment_type'] = 3;
                $moment['group_id'] = $group['group_id'];
                $moment['group_name'] = $group['group_name'];
            } else {
                $moment['moment_type'] = 0;
            }
            
            if ($momentId != $lastMomentId) {
                unset($lastMoment["object_id"]);
                unset($lastMoment["size"]);
                unset($lastMoment["picture_id"]);
                unset($lastMoment["content"]);
                if (Predicates::isNotNull($lastMoment)) {
                    $lastMoment["picture"] = $pictures;
                    $result[] = $lastMoment;
                }
                $pictures = [];
            }
            $lastMomentId = $momentId;
            $lastMoment = $moment;
            $pictures[] = ["picture_id" => $pictureId, "size" => $size, "url" => "event/moment/" . $objectId . ".jpg", "content" => $content, 'shoot_time' => $shootTimt];
        }
        if (Predicates::isNotNull($lastMoment)) {
            $lastMoment["picture"] = $pictures;
            unset($lastMoment["object_id"]);
            unset($lastMoment["size"]);
            unset($lastMoment["picture_id"]);
            unset($lastMoment["content"]);
            $result[] = $lastMoment;
        }
        $pushUsers = [];
        foreach ($uids as $v){
            foreach($v as $va){
                foreach ($va as $val) {
                    $pushUsers[$val] = $val;
                }
            }
        }
        return ["moments" => $result, 'moment_id' => $lastMomentId, 'properties'=> $pushUsers ? UserModel::getUserListData($pushUsers, ['avatar', 'nickname']) : new StdClass];
    }
    
    private static function getGroupMoment ($connection, $groupInfo, $eventInfo, $tubeId, $limit, $groupMap, $version, $platfrom)
    {
        $groupAssociat = [];
        $pgids = self::getMapByObj($groupInfo, 'pgid');
        if (!$pgids) {
            return $groupAssociat;
        }
        $pgids = implode(',', $pgids);
        $groupAssociat = $connection->createCommand(
                "select u.uid, unix_timestamp(t.event_time) * 1000 as create_time, t.payload, u.nickname, concat('profile/avatar/',`avatar`) as avatar, t.id as tube_id
         from (select id, from_uid, event_type, event_time, payload from ". self::$tableTubeEvent." where to_gid in (" . $pgids .")) as t inner join ". self::$tableUser . " as u on t.from_uid = u.uid
         where t.event_type = 1 and t.id < ".$tubeId." order by t.id desc limit " . $limit
        )->queryAll();
        if ($groupAssociat) {
            foreach ($groupAssociat as $k => $tubeInfo) {
                $info = json_decode($tubeInfo['payload'], true);
                $events = @$groupMap[$info['groupid']] ? $groupMap[$info['groupid']] : [];
                if (!in_array($info['eid'], $events)) {
                   unset($groupAssociat[$k]);
                } else {
                    $groupAssociat[$k]['group_id'] = $info['groupid'];
                    $groupAssociat[$k]['group_name'] = $groupInfo[$info['groupid']]->name;
                    $groupAssociat[$k]['event_id'] = $info['eid'];
                    $groupAssociat[$k]['event_start_time'] = strtotime($eventInfo[$info['eid']]['start_time']).'000';
                    $groupAssociat[$k]['event_cover_page'] = User::translationDefaultPicture('event/coverpage/'.$eventInfo[$info['eid']]['cover_page'], $version, $platfrom).'.jpg';
                    $groupAssociat[$k]['avatar'] = $groupAssociat[$k]['avatar'].'.jpg';
                    $groupAssociat[$k]['name'] = $eventInfo[$info['eid']]['name'];
                    if ($info['etype'] == 0) {
                        $groupAssociat[$k]['moment_type'] = 1;
                    }
                    if ($info['etype'] == 1) {
                        $groupAssociat[$k]['moment_type'] = 2;
                    }
                    unset($groupAssociat[$k]['payload']);
                }
            }
        }
        return $groupAssociat;
    }

    public function eventMomentListAction()
    {
        $data = Protocol::arguments();
        //Auth::verifyDeviceStatus($data);    //验证设备和当前登录状态
        $eid = Group::getSpecialEvent($data->requiredInt('gid'));
        $moment = MomentModel::getMomentListByEid($eid);
        $like = Moment::getLikeListByMids(@$moment['mid']);
        $comment = Moment::getCommentListByMids(@$moment['mid']);
        $list = Moment::getEventPicAllInfo($eid, $data->optional('cursor'), '10000000',20);
        Protocol::ok($this->fliterMoment($list, $like, $comment, $data->requiredInt('login_uid')));
    }

    public function fliterMoment($moment, $like, $comment, $loginUid)
    {
        if (Predicates::isEmpty($moment)) {
            return ['list'=>[]];
        }
        $response = [];
        $num = 0;
        $user = [];
        if (@$like['user']) {
            $user = array_unique(@$like['user']);
        }
        if (@$comment['user']) {
            $user = $user + array_unique(@$comment['user']);
        }
        if (@$moment['user']) {
            $user = $user + array_unique(@$moment['user']);
        }
        
        $response['properties'] = [];
        if ($user) {
            $response['properties'] = UserModel::getUserListData($user, ['avatar', 'nickname']);
        }
        if (isset($like['user'])) {
            unset($like['user']);
        }
        if (isset($comment['user'])) {
            unset($comment['user']);
        }
        if (isset($moment['user'])) {
            unset($moment['user']);
        }
        if ($moment['cursor']) {
            $response['cursor'] = $moment['cursor']?$moment['cursor']:"";
            unset($moment['cursor']);
        }
        array_walk($moment, function($val, $mid) use($like, $comment, &$response, &$num, $loginUid) {
            $tmp = $val;
            if (@$like[$mid]) {
                array_walk($like[$mid], function($l, $k) use(&$tmp, $loginUid) {
                    if (@$l['properties']->uid==$loginUid) {
                        $tmp->is_like = true;
                    }
                });
                if (@$like[$mid]['like_count']) {
                    $tmp->like_count = $like[$mid]['like_count'];
                    unset($like[$mid]['like_count']);
                } else {
                    $tmp->like_count = 0;
                }
                $tmp->like = $like[$mid];
                if (!isset($tmp->is_like)) {
                    $tmp->is_like = false;
                }
            } else {
                $tmp->like = [];
                $tmp->is_like = false;
                $tmp->like_count = 0;
            }
            
            if (@$comment[$mid]) {
                if (@$comment[$mid]['comment_count']) {
                    $tmp->comment_count = $comment[$mid]['comment_count'];
                    unset($comment[$mid]['comment_count']);
                } else {
                    $tmp->comment_count = 0;
                }
                $tmp->comment = $comment[$mid];
            } else {
                $tmp->comment = [];
                $tmp->comment_count = 0;
            }
            @$response['list'][] = $tmp;
        });
        return $response;
    }
}

?>
