<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
use yii\db\Exception;
use yii\log\Logger;

class EventController extends Controller_Abstract
{
    const EVENT_ROLE_CREATE = 1;//活动创建者
    const EVENT_ROLE_MEMBER= 0;//活动参与者
    const PICTURE_STATUS_NORMAL = 0;//状态正常的图片(picture)
    const EVENT_STATUS_NORMAL = 0;//状态正常的活动(event)
    const EVENT_STATUS_DELETE = 1;//被删除的活动(event)
    const EVENT_STATUS_LOCK = 2;
    const MOMENT_STATUS_NORMAL = 0;//状态正常的现场(moment)
    const STATUS_EVENT_USER_NORMAL =0;//活动参与者记录正常状态
    const STATUS_MOMENT_DELETE = 1;//现场动态删除状态
    const STATUS_EVENT_DELETE = 1;//(活动)现场删除状态
    const STATUS_PICTURE_DELETE = 1;//现场图片删除状态
    const STATUS_EVENT_USER_DELETE =1;//活动参与者记录删除状态
    const GROUP_EVENT_TYPE_MOMENT = 0;//现场上传通知推送类型
    const GROUP_EVENT_TYPE_MOMENT_NORMAL = 0;//现场上传通知推送正常类型
    const GROUP_EVENT_TYPE_MOMENT_DELETE = 1;//现场图片上传通知推送删除类型
    public static  $tableEvent = Us\TableName\EVENT;
    public static  $tableMomentPicture = Us\TableName\MOMENT_PICTURE;
    public static  $tableEventMoment = Us\TableName\EVENT_MOMENT;
    public static  $tableEventUser = Us\TableName\EVENT_USER;
    public static  $tableUser = Us\TableName\USER;
    public static  $tableTubeUserEvent = Us\TableName\TUBE_USER_EVENT;
    public static  $appUrl = Us\APP_URL;

    /*
     * 创建活动
     *
     **/
    public function createAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        /* 接收参数 */
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $sessionKey = $data->required('session_key');//session_key
        $title = mb_substr($data->optional('title',''), 0, 18);//标题
        $startTime = Types::unix2SQLTimestamp($data->requiredInt('start_time',1000)/1000);//开始时间
        @$file = Protocol::file('file');
        $device_id=$data->optional('device_id');//设备号
        $version = $data->required('version');
        $platform = $data->required('platform');
        if(Predicates::isNotEmpty($file)) {
            if(file_exists($file['tmp_name'])){
                //有file提交时候,去上传返回图片路径
                $coverUrl = CosFile::uploadFile($file, $loginUid, CosFile::CATEGORY_EVENT, CosFile::PICTURE_TYPE_ORIGINAL, CosFile::FILE_TYPE_PICTURE);
            }
            $datas = isset($coverUrl['data']) ? $coverUrl['data'] : "";
            $coverUrl = $coverUrl['subUrlName'];
        }
        $coverUrl = Accessor::either(isset($coverUrl) ? $coverUrl : "", "default");
        
        if (($data->required('version') >= 14 && $data->required('platform') == 0) || ($data->required('version') >= 7 && $data->required('platform') == 1)) {
            if (!in_array($data->requiredInt('gid'), Event::uidJoinGroup($data->requiredInt('login_uid')))) {
               Protocol::notFound(null, "您已不是小组成员，无法访问");
            }
        }
        /* 入库操作
         * event(活动表)写入创建的活动数据
         * */
        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {
            $eventInvitationCode = Event::CreateInvitationCode(5, Event::$tableEvent);//邀请码
            $groupId = Push::createGroup();
            /* event插入创建活动的数据 */
            $eventId = Event::doTableInsert(Event::$tableEvent, ['uid' => $loginUid, 'gid' => $groupId, 'name' => $title, 'cover_page' => isset($coverUrl) ? $coverUrl : "default", 'invitation_code' =>$eventInvitationCode, 'start_time' => $startTime, 'end_time' => Types::unix2SQLTimestamp(time()), 'data' => isset($datas) ? $datas : ""], 1);
            /*修改event Live_id  */
            Event::updateLiveId($loginUid, $eventId, $momentId=0, Event::EVENT_LIVE_OPERATION_CREATE, 0);
            if (($data->required('version') >= 14 && $data->required('platform') == 0) || ($data->required('version') >= 7 && $data->required('platform') == 1)) {
                if (Event::addEventObject($eventId, $data->requiredInt('gid'), $loginUid, 0)) {
                    /*小组内故事添加修改排序时间戳 */
                    GroupModel::updateProfile($data->requiredInt('gid'), 'updateTime', time());
                    Group::pushAddEvent($data->requiredInt('gid'), $loginUid, 1, 0, [$eventId]);
                }
            }
            /* event_user插入创建活动的成员 */
            $resInsertUser = Event::doTableInsert(Event::$tableEventUser, ['event_id' => $eventId, 'member_uid' => $loginUid, 'role' => self::EVENT_ROLE_CREATE, 'invitation_code' => Event::CreateInvitationCode(7, Event::$tableEventUser)]);
            /*加入群  */
            Push::joinGroup($loginUid,$groupId);
            $invitationCode = Event::getInviteCodeByEventIdAndMemberUid($eventId, $loginUid);
            $eventInfo = Event::GetEventInfoByEvent($eventId, '*', $data->requiredInt('version'), $data->requiredInt('platform'));
            $result['p']['event_id'] = $eventId;//活动id
            $result['p']['cover_url'] = !empty($coverUrl) ? User::translationDefaultPicture("event/coverpage/".$coverUrl, $version, $platform).".jpg" : "";//封面图
            $result['p']['share_link'] = self::$appUrl."s/".$eventInvitationCode."";
            $result['p']['invite_link'] = self::$appUrl."i/".$invitationCode."";
            $result['p']['invite_title'] = Event::inviteTitle($eventInfo['name']);
            //创建成功
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw new Exception($e);
        }
        Protocol::ok($result['p'], "", "success");
    }

    /*
     * 活动详情接口
     * */
    public function detailAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        /* 接收参数 */
        $eventId = Protocol::optionalInt('event_id');//活动id
        $loginUid = Protocol::optionalInt('login_uid');//login_uid
        $invitationCode = Protocol::optional('invitation_code');//活动邀请码
        $tag = Protocol::required('tag');//share是分享invite是邀请
        if( substr($invitationCode, 0, 3) == "us.") {
            $invitationCode = substr($invitationCode, 3);//去掉us.
        }
        if($tag == "invite"){
            $eventId = Event::getEventIdByInviteCode($invitationCode);
        }
        $device_id= Protocol::optional('device_id');//设备号
        $platform= Protocol::optional('platform');//渠道 0-iphone1-android

        $retried = false;
retry:
        if(empty($eventId) && empty($invitationCode)){
            Protocol::badRequest('','event_id or invitation_code is null','parameters is null');
            return;
        }else if(!empty($eventId)){
            $whereField = "id = :id and status != :status";
            $whereArray = [':id'=>$eventId, ":status"=>Event::EVENT_STATUS_DELETE];
        }else if (!empty($invitationCode)){
            $whereField = "invitation_code = :invitation_code and status != :status";
            $whereArray = [':invitation_code'=>$invitationCode, ":status"=>Event::EVENT_STATUS_DELETE];
        }

        /* 库查询
         * 活动详情查询
         *  */
        $select = 'id as event_id, name, concat("event/coverpage/",`cover_page`) as cover_page, unix_timestamp(`start_time`)*1000 as start_time, `description` as `desc`, invitation_code';
        $query = new Query;
        $eventDetail = $query
        ->select($select)
        ->from(self::$tableEvent)
        ->where($whereField, $whereArray)
        ->one();

        if(empty($eventDetail)){
            //没查询到该活动
            if (!$retried) {
                $eventId = Event::getEventIdByInviteCode($invitationCode);
                $retried = true;
                goto retry;
            }
            Protocol::forbidden('','','event is not found');
            return;
        }
        $eventId = $eventDetail["event_id"];
        $eventDetail['cover_page'] = User::translationDefaultPicture($eventDetail['cover_page'], Protocol::optional('version'), $platform).".jpg";
        $eventDetail['barcode'] = self::$appUrl."/Us/Event/detail?invitation_code=".$eventDetail['invitation_code'];
        /* 获取活动照片 */
        $selectPicture = self::$tableMomentPicture.".id as picture_id,".self::$tableMomentPicture.".event_id,".self::$tableMomentPicture.".moment_id, concat('event/moment/',`object_id`) as object_id,".self::$tableMomentPicture.".content, size, unix_timestamp(shoot_time)*1000 as shoot_time, nickname, concat('profile/avatar/',`avatar`) as avatar";
        $eventPicture = $query
        ->select($selectPicture)
        ->from(self::$tableMomentPicture)
        ->leftJoin(self::$tableEventMoment, self::$tableEventMoment.".id = ".self::$tableMomentPicture.".moment_id")
        ->leftJoin(self::$tableUser, self::$tableEventMoment.".uid = ".self::$tableUser.".uid")
        ->where(self::$tableMomentPicture.'.event_id='.$eventDetail['event_id']." and ".self::$tableMomentPicture.".status != ".Event::STATUS_PICTURE_DELETE." and ".self::$tableEventMoment.".status = ".Event::MOMENT_STATUS_NORMAL)
        ->orderBy(['shoot_time' => SORT_ASC])
        ->all();
        /*过滤字段为空的情况start  */
        $newArray = [];
        $newSubArray = [];
        foreach ($eventPicture as $key=>$value) {
            foreach ($value as $sKey=>$sValue) {
                if(empty($value[$sKey])){
                    $newSubArray[$sKey] = '';
                }else{
                    if($sKey == "object_id"){
                        $newSubArray[$sKey] = $sValue.".jpg";
                    }elseif($sKey == "avatar"){
                        $newSubArray[$sKey] = $sValue.".jpg";
                    }elseif($sKey == "moment_id"){
                        $comLinkDate = Moment::getCommentLikeUsrts(Moment::getTaoObjectId($sValue)['tao_object_id']);
                        $newSubArray['comment_count'] = $comLinkDate['momentComList'][0];
                        $newSubArray['like_count'] = $comLinkDate['momentPraList'][0];
                        $newSubArray[$sKey] = $sValue;
                    }else{
                        $newSubArray[$sKey] = $sValue;
                    }
                }
            }
            $newArray[] = $newSubArray;
        }
        /*过滤字段为空的情况end  */
        $eventDetail['pictures'] = $newArray;
        if($eventId == 0 ) {
            $eventId = $eventDetail['event_id'];
        }
        /* 获取活动参加者信息 */
        $user = array_map(function($avatar) { return ["a" => "profile/avatar/" . $avatar["avatar"], "u" => $avatar["uid"], "n" => $avatar["nickname"], 'g' => $avatar["gender"]]; }, Event::getEventMembersAvatar($eventId));
        $user = Event::GetEventPictureUrl($user, "a");
        $eventDetail['member'] = $user;

        $last_picture_id = Event::getPictureIdLast($loginUid, $eventDetail['event_id']);
        $last_picture_id = isset($last_picture_id['id']) ? $last_picture_id['id'] : "";
        $eventDetail['last_picture_id'] = !empty($last_picture_id) ? $last_picture_id : "";
        /*读取模板  */
        $jsonFile = APP_PATH . "/conf/event_layout.json";
        $template = trim(stripslashes(file_get_contents($jsonFile)));
        $template = preg_replace("/\s/","",$template);
        $template = json_decode($template,true);
        $invitationCode = Event::getInviteCodeByEventIdAndMemberUid($eventDetail['event_id'] , $loginUid);
        /* 结果集 */
        $result['p'] = $eventDetail;
        $result['p']['template'] = $template;
        $result['p']['share_link'] = self::$appUrl."s/".$eventDetail['invitation_code'];
        $result['p']['invite_link'] = self::$appUrl."i/". $invitationCode;
        if ($tag == "invite") {
            Yii::$app->redis->incr('st_invite_'.$eventId);
        } else {
            Yii::$app->redis->incr('st_share_'.$eventId);
        }
        Protocol::ok($result['p'], "", "success");
    }

    /*
     * 活动修改接口  */
    public function modifyAction() {
        /* 接收参数 */
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId= $data->requiredInt('event_id');//活动Id
        $title = mb_substr($data->optional('title',''), 0, 18);//标题
        $startTime = Types::unix2SQLTimestamp($data->optionalInt('start_time',1000)/1000);//标题
        $version = $data->requiredInt('version');
        $platform = $data->requiredInt('platform');
        @$file = Protocol::file('file');
        $device_id = $data->optional('device_id');//设备号

        if(!Event::isCanModifyEvent($loginUid, $eventId, $version, $platform)) {
            Protocol::ok("","","You don't permission to modify event");
            return;
        }
        if (!Predicates::equals($this->doVerifyEventStatus($data->requiredInt('event_id')), self::EVENT_STATUS_NORMAL)){
            Protocol::forbidden(null, Notice::get()->eventLocked());
            return ;
        }
        if(Predicates::isNotEmpty($file)) {
            if(file_exists($file['tmp_name'])){
                //有file提交时候,去上传返回图片路径
                $coverUrl = CosFile::uploadFile($file, $loginUid, CosFile::CATEGORY_EVENT, CosFile::PICTURE_TYPE_ORIGINAL, CosFile::FILE_TYPE_PICTURE);
                $coverUrl = $coverUrl['subUrlName'];
                $datas = isset($coverUrl['data']) ? $coverUrl['data'] : "";
            }
        } else {
            $coverUrl = "";
            $datas = "";
        }

        if(Predicates::isNotEmpty($title) && Predicates::isNotEmpty($coverUrl) && ($startTime > 1)){
            $updateFileds = [
                'name' => $title,
                'cover_page' => $coverUrl,
                'data' => $datas,
                'start_time' => $startTime,
            ];
        } else if (Predicates::isNotEmpty($title) && ($startTime > 1)){
            $updateFileds = [
                'name' => $title,
                'start_time' => $startTime,
            ];
        }else if (Predicates::isNotEmpty($title) && Predicates::isNotEmpty($coverUrl)){
            $updateFileds = [
                'name' => $title,
                'cover_page' => $coverUrl,
                'data' => $datas,
            ];
        }else if (Predicates::isNotEmpty($coverUrl) && ($startTime > 1)){
            $updateFileds = [
                'cover_page' => $coverUrl,
                'data' => $datas,
                'start_time' => $startTime,
            ];
        }else if (Predicates::isNotEmpty($title)){
            $updateFileds = [
                'name' => $title,
            ];
        } else if (Predicates::isNotEmpty($coverUrl)) {
            $updateFileds = [
                'cover_page' => $coverUrl,
                'data' => $datas,
            ];
        }else if ($startTime > 1) {
            $updateFileds = [
                'start_time' => $startTime,
            ];
        } else {
            $updateFileds = [];
        }
        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($connection, $updateFileds, $eventId, $loginUid, $coverUrl, $version, $platform) {
            /*修活动封面图和标题  */
            if(Predicates::isNotEmpty($updateFileds)) {
                $resEvent = $connection->createCommand()->update(self::$tableEvent, $updateFileds, 'id=:id',array(':id'=>$eventId))->execute();
                /*修改event Live_id  */
                $liveId = Event::updateLiveId($loginUid, $eventId, $momentId=0, Event::EVENT_LIVE_OPERATION_MODIFY, 1);
            }
            /*查询活动详情  */
            $eventInfo = Event::GetEventInfoByEvent($eventId, '*', $version, $platform);
            $invitationCode = Event::getInviteCodeByEventIdAndMemberUid($eventId, $loginUid);
            $result['p']['image_link'] = isset($liveId) ? "event/live/".$liveId.".jpg" : "";
            $result['p']['share_link'] = self::$appUrl."s/".$eventInfo['invitation_code']."";
            $result['p']['invite_link'] = self::$appUrl."i/".$invitationCode."";
            $result['p']['invite_title'] = Event::inviteTitle($eventInfo['name']);
            $result['p']['cover_url'] = Predicates::isNotEmpty($coverUrl) ? User::translationDefaultPicture("event/coverpage/".$coverUrl, $version, $platform).".jpg" : "";//封面图
            Protocol::ok($result['p'],'',"success");
        });
    }

    /*
     * 活动列表接口   */
    public function listAction()
    {
        $data = Protocol::arguments();
         Auth::verifyDeviceStatus($data);
        /* 接收参数 */
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $startTime = $data->optionalInt('start_time',1000);//开始时间
        $endTime = $data->optionalInt('end_time',4070880000*1000);//结束时间 默认2099-01-01 00:00:00的时间戳
        $deviceId= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $version = $data->required('version');//版本
        $versionApi= $data->optional('version_api');//接口版本号
        $startTime = Types::unix2SQLTimestamp($startTime/1000);
        $endTime = Types::unix2SQLTimestamp($endTime/1000);
        $invitationCode = $data->optional('invitation_code',"");//活动邀请码
        if( substr($invitationCode, 0, 3) == "us.") {
            $invitationCode = substr($invitationCode, 3);//去掉us.
        }

        /*过滤字段为空的情况end  */
        $join = 0;
        $inviteInfo = new stdClass();
        $catchCode = Yii::$app->redis->get(Us\Event\INVITE.$loginUid);
        if ($catchCode) {
            //优先显示h5参加的活动
            $inviteInfo = $this->_getInviteInfo($catchCode);
            if ($inviteInfo && $inviteInfo['member_uid'] != $loginUid) {
                $inviteInfo['avatar'] = 'profile/avatar/'.$inviteInfo['avatar'].'.jpg';
                $inviteInfo['cover_page'] = User::translationDefaultPicture('event/coverpage/'.$inviteInfo['cover_page'], $version, $platform).'.jpg';
                if ($invitationCode) {
                    if (substr($invitationCode, 0, 2) != 'US') {
                        //$this->_addEvent($loginUid, $inviteInfo['event_id']);
                        Event::AddEvent($loginUid, $invitationCode);
                    } else {
                        $inviteInfo = new stdClass();
                    }
                }
            } else {
                $inviteInfo = new stdClass();
            }
            Yii::$app->redis->del(Us\Event\INVITE.$loginUid);
        } elseif ($invitationCode) {
            $inviteInfo = $this->_getInviteInfo($invitationCode);
            if ($inviteInfo && $inviteInfo['member_uid'] != $loginUid) {
                //$join = $this->_addEvent($loginUid, $inviteInfo['event_id']);
                if (substr($invitationCode, 0, 2) != 'US') {
                    Event::AddEvent($loginUid, $invitationCode);
                    $inviteInfo['avatar'] = 'profile/avatar/'.$inviteInfo['avatar'].'.jpg';
                    $inviteInfo['cover_page'] = User::translationDefaultPicture('event/coverpage/'.$inviteInfo['cover_page'], $version, $platform).'.jpg';
                } else {
                    $inviteInfo = new stdClass();
                }
            } else {
                $inviteInfo = new stdClass();
            }
        }
        $newArray = self::_getEventList($loginUid, $startTime, $endTime, $version, $platform);
        /* 结果集 */
        $result['p']['list'] = $newArray;
        $result['p']['invite'] = $inviteInfo;
        Protocol::ok($result['p'], '', 'success');
    }

    /*
     * 活动现场接口  */
    public function createMomentAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        /* 接收参数 */
        $data = Protocol::arguments();
        if(isset($data->device_id)) {
            Auth::verifyDeviceStatus($data);
        }else {
            Event::isLogin($data);
        }
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId = $data->required('event_id');//活动Id
        $deviceId= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $version = $data->requiredInt('version');
        /* 入库操作 */
        Execution::autoTransaction(Yii::$app->db, function() use($eventId, $loginUid, $version, $platform) {
            if (Predicates::equals($this->doVerifyEventStatus($eventId), self::EVENT_STATUS_LOCK)){
                Protocol::forbidden(null, Notice::get()->eventLocked() . ':' . User::getUserNickname(Event::GetEventInfoByEvent($eventId, 'uid', $version, $platform)));
            }
            $eventName = Event::GetEventInfoByEvent($eventId, 'name', $version, $platform);
            if ($eventName) {
                if (!Group::verifyUserInGroupByEid($eventId, $loginUid) && !Event::isEventMember($eventId, $loginUid)) {
                    Protocol::badRequest(null, null, "user is not group");
                    return;
                }
            } else {
                if (!Group::verifyUserInGroupByEid($eventId, $loginUid) && !Event::isEventMember($eventId, $loginUid)) {
                    Protocol::notFound(null, "您已不是小组成员，无法访问");
                    return;
                }
            }
            $momentId = Event::doTableInsert(Event::$tableEventMoment, ['uid' => $loginUid, 'event_id' => $eventId, 'status' => self::STATUS_MOMENT_DELETE], 1);
            $result['p']['moment_id'] = $momentId;//现场id
            Protocol::ok($result['p'], "", "success");
        });
    }

    /*
     * 活动现场列表接口  */
    public function momentListAction()
    {
        /* 接收参数 */
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $eventId = $data->requiredInt('event_id');//活动Id
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $device_id = $data->optional('device_id');//设备号
        $platform = $data->optional('platform');//渠道 0-iphone1-android
        $version = $data->required('version');//版本号

        //获取某个活动所属小组成员
        $groupMembers = $this->getEventGroupMember($eventId, $loginUid);
        if (!Event::isEventMember($eventId, $loginUid) && !in_array($loginUid, $groupMembers)) {
            Protocol::badRequest(null, null, null, "您不是该故事成员或该故事已被移除，无法访问");
        }
        $eventInfo = Event::GetEventInfoByEvent($eventId, '*', $version, $platform);
        $query = new Query;
        $select = self::$tableMomentPicture.".id as pi, ".self::$tableMomentPicture.".moment_id as mi, ".self::$tableEventMoment.".uid as u, unix_timestamp(".self::$tableMomentPicture.".shoot_time)*1000 as t, ".self::$tableMomentPicture.".content as c, concat('event/moment/',`object_id`) as p, ".self::$tableMomentPicture.".event_id, ".self::$tableMomentPicture.".size as s";
        $where = self::$tableMomentPicture . ".event_id = " . $eventId . " and " . self::$tableMomentPicture . ".status != " . self::STATUS_PICTURE_DELETE . " and " . self::$tableEventMoment . ".status = " . self::MOMENT_STATUS_NORMAL;
        $pictures = $query
        ->select($select)
        ->from(self::$tableMomentPicture)
        ->leftJoin(self::$tableEventMoment, self::$tableEventMoment.".id = ".self::$tableMomentPicture.".moment_id")
        ->where($where)
        ->orderBy(['shoot_time' => SORT_ASC])
        ->all();

        /*活动成员start  */
        $user = array_map(function($avatar) { return ["a" => "profile/avatar/" . $avatar["avatar"] . ".jpg", "u" => $avatar["uid"], "n" => strval($avatar["nickname"]), "s" => $avatar["is_deleted"]]; }, Event::getEventMembersAvatar($eventId, false, $version));
        /*活动成员end  */

        /*过滤字段为空的情况start  */
        $newArray = Event::GetEventPictureUrl($pictures, "p");
        //$user = Event::GetEventPictureUrl($user, "a");
        /*过滤字段为空的情况end  */

        /*查询活动详情  */
        $evnetInvitationCode = isset($eventInfo['invitation_code']) ? $eventInfo['invitation_code'] : "";
        $invitationCode = Event::getInviteCodeByEventIdAndMemberUid($eventId, $loginUid);
        if (isset($eventInfo['tao_object_id'])) {
            $group = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
        } else {
            $group = [];
        }
        $result['p']['group'] = [];
        if ($group) {
            foreach ($group as $data) {
                $result['p']['group'][] = $data->to;
            }
        }
        /* 结果集 */
        $result['p']['picture'] = $newArray;//图片列表
        //$result['p']['moment'] = array_map(function($moments) use($loginUid) { return ["mi" => $moments["id"], "pc" => Event::getTableWhereCount(Us\TableName\MOMENT_LIKE, "event_id = :event_id and moment_id = :moment_id and status = :status", [":event_id"=>$moments["event_id"], ":moment_id"=>$moments["id"], ":status"=>0]), "cc" => Event::getTableWhereCount(Us\TableName\MOMENT_COMMENT, "event_id = :event_id and moment_id = :moment_id and is_deleted = :is_deleted", [":event_id"=>$moments["event_id"], ":moment_id"=>$moments["id"], "is_deleted"=>0]), "ps" => Moment::isToMomentPraiseByUid($loginUid, $moments["event_id"], $moments["id"]) ? 1 : 0]; }, Event::getEventMoment($eventId, "id, event_id"));//动态列表
        $userTaoObjectId = User::getUserInfo($loginUid, ["tao_object_id"])['tao_object_id'];
        $result['p']['moment'] = array_map(function($moments) use($loginUid, $userTaoObjectId) { return ["mi" => $moments["id"], "pc" => Moment::fetchLike($moments["tao_object_id"])[0], "cc" => Moment::fetchComment($moments["tao_object_id"])[0], "ps" => Moment::isLikeMomentByUid($moments["event_id"], $moments["id"], $loginUid, $moments['tao_object_id'], $userTaoObjectId) ? 1 : 0]; }, Event::getEventMoment($eventId, "id, event_id, tao_object_id"));//动态列表
        $result['p']['upload_count'] = Event::GetCountPictureByEvent($eventId, $loginUid);
        $result['p']['ci'] = isset($eventInfo['uid']) ? $eventInfo['uid'] : "";//活动现场创建人
        $result['p']['cover_page'] = isset($eventInfo['cover_page']) ? $eventInfo['cover_page'] : "";//活动现场创建人
        $result['p']['status'] = isset($eventInfo['status']) ? $eventInfo['status'] : "0";//活动现场创建人
        $result['p']['start_time'] = isset($eventInfo['start_time']) ? $eventInfo['start_time'] : "";//活动开始时间
        $result['p']['name'] = isset($eventInfo['name']) ? $eventInfo['name'] : "";//活动现场名称
        $result['p']['member'] = $user;//用户列表
        $result['p']['image_link'] = !empty($eventInfo['live_id']) ? "event/live/".$eventInfo['live_id'].".jpg" : "";
        $result['p']['share_link'] = self::$appUrl."s/".$evnetInvitationCode."";
        $result['p']['invite_link'] = self::$appUrl."i/".$invitationCode."";
        $result['p']['invite_title'] = Event::inviteTitle($eventInfo['name']);
        $result['p']['template_config'] = "0";//0代表单张模版;1代表读模版
        /* loginUid所在的组和活动所在的组的交集start */
        $groupSelfGid = Event::uidJoinGroup($loginUid);
        //$eventGroup = GroupModel::getEventAssociatGroup($eventId, 0, 100000);
        $eventGroupGid = [];
        array_walk($group, function(&$value, $key) use (&$eventGroupGid) {
            $eventGroupGid[] = $value->to;
        });
        $selfAndEventGroup = array_intersect($groupSelfGid, $eventGroupGid);
        $result['p']['group_intersect'] = $selfAndEventGroup;
        /* loginUid所在的组和活动所在的组的交集end */
        Protocol::ok($result['p'], "", "success");
    }
    
    private function getEventGroupMember($eventId, $loginUid)
    {
        $eventAssociatGroup = [];
        $groupMembers = [];
        $groupMember = [];
        $eventAssociatGroup = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
        foreach ($eventAssociatGroup as $k1 => $v1) {
            $groupMember[] = GroupModel::getGroupMember($v1->to, 0, 0x7FFFFFFF);
        }
        foreach ($groupMember as $k2 => $v2) {
            foreach ($v2 as $k3 => $v3) {
                $groupMembers[] = GroupModel::getGroupOwner($v3->from);
                $groupMembers[] = $v3->properties->uid;
            }
        }
        return $groupMembers;
    }

     /*
     * 活动现场照片举报接口  */
    public function pictureReportAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $pictureId = $data->requiredInt('picture_id');//图片Id
        $eventId = $data->requiredInt('event_id');//活动Id
        $momentId = $data->requiredInt('moment_id');//活动现场Id
        $device_id= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android

        Protocol::ok('','',"success");
    }

    /*
     * 活动现场退出接口  */
    public function exitAction()
    {
        /* 接收参数 */
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId = $data->requiredInt('event_id');//活动Id
        $device_id= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $version = $data->required('version');//版本号
        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($eventId, $loginUid, $connection, $version, $platform) {
            $groupList = Group::queryGroupListByEid($eventId, $loginUid);
            /* 修改活动参与者表对应的数据event_user */
            $resMember = $connection->createCommand()->update(self::$tableEventUser, [
                'is_deleted'=>self::STATUS_EVENT_USER_DELETE
            ], ['member_uid'=>$loginUid, 'event_id'=>$eventId])->execute();

            /*如果活动没有参与人现场状态变为删除状态  */
            $eventRole = Event::getTableFields(self::$tableEventUser, "event_id = :event_id and member_uid = :member_uid", [":event_id"=>$eventId,":member_uid"=>$loginUid], "role");
            $eventUserCount = Event::getTableWhereCount(self::$tableEventUser, "event_id = :event_id and is_deleted = :is_deleted", [":event_id"=>$eventId,":is_deleted"=>Event::STATUS_EVENT_USER_NORMAL]);
            if($eventUserCount <= 0){
                $resUser = $connection->createCommand()->update(self::$tableEvent, [
                    'status'=>self::STATUS_EVENT_DELETE
                ], ['id'=>$eventId])->execute();
            }

            if (Predicates::equals($this->doVerifyEventStatus($eventId), self::EVENT_STATUS_LOCK) && Predicates::equals(intval($eventRole), self::EVENT_ROLE_CREATE) && ($eventUserCount > 0)) {
                $this->doupdateEventStatus($eventId, self::EVENT_STATUS_NORMAL);
            }
            
            if (Predicates::equals($this->doVerifyEventStatus($eventId), Event::EVENT_STATUS_LOCK)){
                /*修改现在图片的状态  */
                if (Predicates::equals(intval($eventRole), self::EVENT_ROLE_MEMBER)) {
                    $sqlPicture = "update ".self::$tableMomentPicture." set status = ".Event::STATUS_PICTURE_LOCK_DELETE." where status = ". Event::PICTURE_STATUS_NORMAL ." and moment_id in (select id from ".Event::$tableEventMoment." where uid =".$loginUid." and event_id =".$eventId.")";
                    $resPicture = $connection->createCommand($sqlPicture)->execute();
                }
            }else {
                /*修改moment状态  */
                $resMoment = $connection->createCommand()->update(self::$tableEventMoment, [
                 'status'=>self::STATUS_MOMENT_DELETE
                ], ['uid'=>$loginUid, 'event_id'=>$eventId])->execute();

                /*修改现在图片的状态  */
                if (($this->doVerifyEventStatus($eventId) && Predicates::equals($eventRole, self::EVENT_ROLE_CREATE)) || !$this->doVerifyEventStatus($eventId)) {
                    $sqlPicture = "update ".self::$tableMomentPicture." set status = ".self::STATUS_PICTURE_DELETE." where moment_id in (select id from ".Event::$tableEventMoment." where uid =".$loginUid." and event_id =".$eventId.")";
                    $resPicture = $connection->createCommand($sqlPicture)->execute();
                }

                /*查询某人活动的动态  */
                $moments = Event::getEventMoment($eventId, "id", ["uid"=>$loginUid]);
                if(!empty($moments)) {
                    foreach ($moments as $momentId) {
                        Event::tubePush($eventId, $momentId['id'], Event::GROUP_EVENT_TYPE_MOMENT_DELETE, 0 ,$version, $platform);
                    }
                }
            }
            GroupModel::deleteAllEvent($eventId, $loginUid);
            /*退出群  */
            $groupId = Event::GetEventInfoByEvent($eventId, "gid", $version, $platform);
            Push::outGroup($loginUid, $groupId);
            /*修改event Live_id  */
            $liveId = Event::updateLiveId($loginUid, $eventId, $momentId=0, Event::EVENT_LIVE_OPERATION_EVENT_EXIT, 1);
            $result['p']['image_link'] = !empty($liveId) ? "event/live/".$liveId.".jpg" : "";
            $result['p']['group'] = [];
            if ($groupList) {
                $num = 0;
            	array_walk($groupList, function($data, $key) use (&$result, &$num){
                    $result['p']['group'][$num++] = Group::queryGroupProfileByGid($data['gid']);
            	});
            }
            Protocol::ok($result['p'], '', "success");
        });
    }

    //删除一张照片
    public function _deletePicture($loginUid, $eventId, $momentId, $pictureId, $type,  $query, $connection, $version, $platform) {
        /*判断有没有删除的权限start  */
        if(!Event::isCanModifyMomentOrPicutre($loginUid, $eventId, $momentId, 0, $version, $platform)) {
            return false;
        }
        /*判断有没有删除的权限end  */
        /*删除图片状态  */
        Event::doMomentPictureUpdate($eventId, $momentId, $pictureId, ['status'=>Event::STATUS_PICTURE_DELETE]);
        //删除腾讯云文件
        $objectId = Event::GetPictureInfoByEvent($eventId, $momentId, $pictureId, $selectField ="object_id", 1);
        /* if(!empty($objectId)) {
            CosFile::delFile($objectId, CosFile::CATEGORY_SCENE);
        } */
        /* 判断现场动态图片数量是否为0,如果动态图片为0,就把这条现场动态删除start  */
        $count = Event::getTableWhereCount(Event::$tableMomentPicture, "moment_id = :moment_id and event_id = :event_id and status = :status", [":moment_id"=>$momentId, ":event_id"=>$eventId, ":status"=>Event::PICTURE_STATUS_NORMAL]);
        if($count <=0 ) {
            /*修改moment状态  */
            Event::doEventMomentUpdate($eventId, $momentId, ['status'=>Event::STATUS_MOMENT_DELETE]);
        }
        /* 判断现场动态图片数量是否为0,如果动态图片为0,就把这条现场动态删除end */
        return true;
    }

    //更新一张照片时间
    public function _updatePictureCreateDate($loginUid, $eventId, $momentId, $pictureId, $type, $createDate, $query, $connection) {
        /*判断有没有删除的权限start  */
        if(!Event::isCanModifyMomentOrPicutre($loginUid, $eventId, $momentId)) {
            return false;
        }
        /*判断有没有删除的权限end */
        /*修改picture拍摄时间  */
        Event::doMomentPictureUpdate($eventId, $momentId, $pictureId, ['shoot_time'=>Types::unix2SQLTimestamp($createDate/1000)]);
        return true;
    }

    /*
     * 照片时间更新接口
     */
    public function pictureTimeUpdateAction(){
       //接收参数
       $data = Protocol::arguments();
       Auth::verifyDeviceStatus($data);
       $loginUid = $data->requiredInt('login_uid');//登录uid
       $eventId = $data->requiredInt('event_id');
       $version = $data->requiredInt('version');
       $platform = $data->requiredInt('platform');
       $modiData = urldecode($data->required('modified'));
       $modiList = json_decode($modiData,true);

       if (!Predicates::equals($this->doVerifyEventStatus($data->requiredInt('event_id')), self::EVENT_STATUS_NORMAL)){
           Protocol::forbidden(null, Notice::get()->eventLocked() . ':' . User::getUserNickname(Event::GetEventInfoByEvent($data->required('event_id'), 'uid', $version, $platform)));
           return;
       }

       $connection = Yii::$app->db;
       Execution::autoTransaction($connection, function() use($data, $loginUid, $eventId, $modiList, $connection, $version, $platform) {
           $groupList = Group::queryGroupListByEid($eventId);
           $modiCount = count($modiList);
           for ($i=0;  $i<$modiCount;  $i++) {
               $isUpdateLiveId = false;
               $query = new Query;
               $currModi = $modiList[$i];
               $type = $currModi['type']; //1删除0编辑
               if ( $type== 1) {
                   //删除
                   $pictureId = $currModi['picture_id'];
                   $momentId  = $currModi['moment_id'];
                   if (self::_deletePicture($loginUid, $eventId, $momentId, $pictureId, $type,  $query, $connection, $version, $platform)) {
                        //推送
                        Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_DELETE, 0, $version, $platform);
                        $isUpdateLiveId = true;
                   }
               } elseif ($type == 0) {
                   //编辑
                   $createDate = $currModi['create_date'];
                   $momentId   = $currModi['moment_id'];
                   $pictureId  = $currModi['picture_id'];
                   if(self::_updatePictureCreateDate($loginUid, $eventId, $momentId, $pictureId, $type, $createDate, $query, $connection)) {
                        $isUpdateLiveId = true;
                   }
               }
               if ($isUpdateLiveId) {
                   $liveId = Event::updateLiveId($loginUid ,$eventId, $momentId, Event::EVENT_LIVE_OPERATION_PICTURE_MODIFY, 1);
               }
               $result['p']['image_link'] = !empty($liveId) ? "event/live/".$liveId.".jpg" : "";
           }   //for end
           $result['p']['upload_count'] = Event::GetCountPictureByEvent($eventId, $loginUid);
           $result['p']['group'] = [];
           if ($groupList) {
               $num = 0;
               array_walk($groupList, function($data, $key) use (&$result, &$num){
                   $result['p']['group'][$num++] = Group::queryGroupProfileByGid($data['gid']);
               });
           }
           Protocol::ok($result['p'],'',"success");
       });

    }

    /*
     * 活动现场照片删除接口  */
    public function pictureDeleteAction()
    {
        /* 接收参数 */
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $pictureId = $data->requiredInt('picture_id');//图片Id
        $eventId = $data->requiredInt('event_id');//活动Id
        $momentId = $data->requiredInt('moment_id');//活动现场Id
        $deviceId= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $type = $data->optionalInt('type', 0);//0:代表从现场删除1:代表从动态删除
        $version = $data->requiredInt('version');
        Execution::autoTransaction(Yii::$app->db, function() use($data, $momentId, $eventId, $loginUid, $pictureId, $type, $version, $platform) {
            /*判断有没有删除的权限start  */
            if(!Event::isCanModifyMomentOrPicutre($loginUid, $eventId, $momentId)) {
                Protocol::badRequest("","","You don't permission to delete moment");
                return;
            }
            /*判断有没有删除的权限end  */
            
            /* if (Predicates::equals($this->doVerifyEventStatus($eventId), Event::EVENT_STATUS_LOCK) && $type ==1){
                /*活动锁定时从动态删除照片 修改图片的状态 start */
                //Event::doMomentPictureUpdate($eventId, $momentId, $pictureId, ['status'=>Event::STATUS_PICTURE_LOCK_DELETE]);
                /*活动锁定时从动态删除照片 修改图片的状态 end */
            if (Predicates::equals($this->doVerifyEventStatus($eventId), self::EVENT_STATUS_LOCK)){
                Protocol::forbidden( null, Notice::get()->eventLocked() . ':' . User::getUserNickname(Event::GetEventInfoByEvent($eventId, 'uid', $version, $platform)));
                return;
            }else {
                $groupList = Group::queryGroupListByEid($eventId);
                /*修改现场图片的状态 start */
                Event::doMomentPictureUpdate($eventId, $momentId, $pictureId, ['status'=>Event::STATUS_PICTURE_DELETE]);
                /*修改现场图片的状态 end */
                /* 判断现场图片数量是否为0  */
                $count = Event::getTableWhereCount(Event::$tableMomentPicture, "moment_id = :moment_id and event_id = :event_id and status = :status", [":moment_id"=>$momentId, ":event_id"=>$eventId, ":status"=>Event::PICTURE_STATUS_NORMAL]);
                if($count <= 0) {
                    /*修改moment状态  */
                    Event::doEventMomentUpdate($eventId, $momentId, ['status'=>Event::STATUS_MOMENT_DELETE]);
                }
                /*删除腾讯云上的照片  */
                $objectId = Event::GetPictureInfoByEvent($eventId, $momentId, $pictureId, $selectField ="object_id", 1);
                /* if(!empty($objectId)) {
                    CosFile::delFile($objectId, CosFile::CATEGORY_SCENE);
                } */
                /*修改event Live_id  */
                $liveId = Event::updateLiveId($loginUid, $eventId, $momentId, Event::EVENT_LIVE_OPERATION_PICTURE_DELETE, 1);
            }
            /*删除照片推送  */
            Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_DELETE, 0, $version ,$platform);
            $result['p']['image_link'] = !empty($liveId) ? "event/live/".$liveId.".jpg" : "";
            $result['p']['upload_count'] = Event::GetCountPictureByEvent($eventId, $loginUid);
            $result['p']['group'] = [];
            if ($groupList) {
                $num = 0;
                array_walk($groupList, function($data, $key) use (&$result, &$num){
                    $result['p']['group'][$num++] = Group::queryGroupProfileByGid($data['gid']);
                });
            }
            Protocol::ok($result['p'], '', "success");
        });
    }

    /*
     * 上传附件接口  */
    public function uploadAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        /* 接收参数 */
        $data = Protocol::arguments();
        if(isset($data->device_id)) {
            Auth::verifyDeviceStatus($data);
        }else {
            Event::isLogin($data);
        }
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId = $data->requiredInt('event_id');//活动Id
        $momentId = $data->requiredInt('moment_id');//现在id
        $size = $data->required('size');//图片尺寸
        $content= $data->optional('content');//内容
        $shootTime= Types::unix2SQLTimestamp($data->optionalInt('shoot_time',1000)/1000);//拍摄时间
        $shootDevice= $data->optional('shoot_device');//拍照设备
        $lat= $data->optional('lat');//纬度
        $lng= $data->optional('lng');//经度
        $file= Protocol::file('file');//上传文件信息
        $deviceId= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $version = $data->requiredInt('version');
        
        if (!Event::isEventMyMoment($eventId, $loginUid, $momentId)) {
            Protocol::badRequest(NULL, NULL, 'event is not found or not event user');
        }
        
        if (!$this->isUidJoinGroup($eventId, $loginUid) && !Event::isEventMember($eventId, $loginUid)) {
            Protocol::badRequest(NULL, NULL, 'event is not found or not event user');
        }
        
        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($momentId, $eventId, $loginUid, $shootDevice, $file, $connection, $content, $shootTime, $lat, $lng, $size) {
            if(Predicates::equals($this->doVerifyEventStatus($eventId), self::EVENT_STATUS_LOCK)){
                Protocol::forbidden(null, Notice::get()->eventLocked() . ":" . User::getUserNickname(Event::GetEventInfoByEvent($eventId, 'uid', $version, $platform)));
            }
            if(!Event::isEventMember($eventId, $loginUid)) {
                Protocol::badRequest(null, null, "not this event user");
            }
            $coverUrl = CosFile::uploadFile($file, $loginUid, CosFile::CATEGORY_SCENE, CosFile::PICTURE_TYPE_ORIGINAL, CosFile::FILE_TYPE_PICTURE, $eventId, $momentId);
            $data = $coverUrl['data'] ? $coverUrl['data'] : null;
            $coverUrl = $coverUrl['subUrlName'];
    
            $res_insert = $connection->createCommand()->insert(self::$tableMomentPicture,[
                'event_id' => $eventId,
                'moment_id' => $momentId,
                'object_id' => !empty($coverUrl) ? $coverUrl : "",
                'content' =>$content,
                'shoot_time' => $shootTime,
                'shoot_device' => $shootDevice,
                'lat' => $lat,
                'lng' => $lng,
                'size' => $size,
                'data' => !empty($data) ? $data : null,
                'status' => self::STATUS_PICTURE_DELETE,//上传成功后是删除状态
            ])->execute();
            $pictureId = $connection->getLastInsertID();
            /* 结果集 */
            $result['p']['url'] = !empty($coverUrl) ? "event/moment/".$coverUrl.".jpg" : "";//图片名字
            $result['p']['picture_id'] = $pictureId;//图片Id
            Protocol::ok($result['p'], "", "success");
        });
    }

    /*
     * 提交活动接口  */
    public function commitAction($data = '')
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        if (Predicates::isEmpty($data)) {
            $data = Protocol::arguments();
        }
        if (isset($data->device_id)) {
            Auth::verifyDeviceStatus($data);
        }else {
            Event::isLogin($data);
        }
        /* 接收参数 */
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId = $data->requiredInt('event_id');//活动Id
        $momentId = $data->required('moment_id');//现在id
        $pictureIds = $data->optional('picture_ids');//上传成功的picture_id
        $deviceId= $data->optional('device_id');//设备号
        $platform= $data->optional('platform');//渠道 0-iphone1-android
        $version = $data->requiredInt('version');
        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($momentId, $eventId, $loginUid, $pictureIds, $connection, $version, $platform) {
            if (Predicates::equals($this->doVerifyEventStatus($eventId), self::EVENT_STATUS_LOCK)){
                Protocol::forbidden(null, Notice::get()->eventLocked() . ":" . User::getUserNickname(Event::GetEventInfoByEvent($eventId, 'uid', $version, $platform)));
            }
            
            if (!Event::isEventMyMoment($eventId, $loginUid, $momentId, $pictureIds)) {
                Protocol::badRequest(NULL, NULL, 'event is not found or not event user');
            }

            if (!$this->isUidJoinGroup($eventId, $loginUid) && !Event::isEventMember($eventId, $loginUid)) {
                $result['p']['image_link'] = "";
                $result['p']['upload_count'] = Event::GetCountPictureByEvent($eventId, $loginUid);
                Protocol::ok($result['p'], NULL, 'event is not found or not event user');
                return;
            }

            $groupList = Group::queryGroupListByEid($eventId);
            /* 判断现场动态图片数量是否为0,如果动态图片为0,就把这条现场动态删除start  */
            $count = Event::getTableWhereCount(Event::$tableMomentPicture, "moment_id = :moment_id and event_id = :event_id", [":moment_id"=>$momentId, ":event_id"=>$eventId]);
            if($count > 0) {
                if(Predicates::isNotEmpty($pictureIds)) {
                    /*修改picture状态  */
                    $resPicture = $connection->createCommand()->update(Event::$tableMomentPicture, [
                        'status'=>Event::PICTURE_STATUS_NORMAL
                    ]," id in (".$pictureIds.") and moment_id = ".$momentId." and event_id = ".$eventId)->execute();
                    if($resPicture) {
                        /*修改event和moment Live_id  */
                        $liveId = Event::updateLiveId($loginUid, $eventId, $momentId, Event::EVENT_LIVE_OPERATION_COMMIT, 1);
                        $object = Tao::addObject("MOMENT", "mid", $momentId, "uid", $loginUid, "eid", $eventId, "type", 0);
                        /*修改moment为正常状态 start */
                        Event::doEventMomentUpdate($eventId, $momentId, ['status'=>Event::MOMENT_STATUS_NORMAL, 'tao_object_id' => $object->id]);
                        /*修改moment为正常状态 end */
                        /*推送数据start  */
                        Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_NORMAL, 0 ,$version, $platform);
                        Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_NORMAL, 1 ,$version, $platform);//小组成员推
                        /*推送数据end  */
                        /*miPush推送数据start  */
                        $uploadPictureCount = Event::GetCountPictureByEvent($eventId, $loginUid, $momentId);
                        $payload = [];
                        $payload[] = ["uid" => $loginUid, "event_id" => $eventId, "moment_id" => $momentId, "upload_count" => $uploadPictureCount, "type" =>MiPush::CREATEMOMENT];
                        MiPush::submitWorks($payload);
                        //MiPush::momentSendMessage($loginUid, $eventId, $momentId, "chuantu", "Us");
                        /*miPush推送数据end  */
                        /* $node = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
                        if ($node) {
                        	foreach ($node as $data){
                                Group::pushGroupMoment($data->to, $loginUid, $eventId, 0);
                        	}
                        } */
                    }
                }
            } else {
                /*修改moment为删除状态  */
                Event::doEventMomentUpdate($eventId, $momentId, ['status'=>Event::STATUS_MOMENT_DELETE]);
            }
            /* 判断现场动态图片数量是否为0,如果动态图片为0,就把这条现场动态删除end  */

            
            /* 结果集 */
            $result['p']['image_link'] = !empty($liveId) ? "event/live/".$liveId.".jpg" : "";
            $result['p']['upload_count'] = Event::GetCountPictureByEvent($eventId, $loginUid);
            if (!Event::isEventMember($eventId, $loginUid)) {
                $inviteCode = Event::getTableFields(Event::$tableEventUser, "event_id = :e_id and member_uid = :m_uid", [":e_id" => $eventId, 
                    ":m_uid" => Event::GetEventInfoByEvent($eventId, 'uid', $version, $platform)], "invitation_code");
                Event::AddEvent($loginUid, $inviteCode);
            }
            $result['p']['group'] = [];
            if ($groupList) {
                $num = 0;
                array_walk($groupList, function($data, $key) use (&$result, &$num){
                    $result['p']['group'][$num++] = Group::queryGroupProfileByGid($data['gid']);
                });
            }
            Protocol::ok($result['p'], NULL, 'success');
        });
    }

    /*
     * 邀请接口  */
    public function inviteAction()
    {
        /* 接收参数 */
        $data = Protocol::arguments();
        $invitationCode = $data->required('invitation_code');//活动邀请码
        if( substr($invitationCode, 0, 3) == "us.") {
            $invitationCode = substr($invitationCode, 3);//去掉us.
        }
        if (!Predicates::equals($this->doVerifyEventStatus($data->requiredInt('invitation_code')), self::EVENT_STATUS_NORMAL)){
            Protocol::forbidden(null, Notice::get()->eventLocked());
            return ;
        }
        $device_id= $data->required('device_id');//设备号
        $platform= $data->required('platform');//渠道 0-iphone1-android
        $loginUid =$data->optional('login_uid', 0);
        $transaction = Yii::$app->db->beginTransaction();
        $result = Us\User\JOIN_FALSE;
        try {
            $invitInfo = $this->_getInviteInfo($invitationCode);
            if (!$invitInfo) {
                Protocol::forbidden('', '', 'event is not found');
                return;
            }
            $invitInfo['start_time'] = Protocol::dateToUnixTimeStamp($invitInfo['start_time']);
            if ($loginUid == 0) {
                $result = Us\User\TOURISTS;
            } elseif ($loginUid != $invitInfo['uid']) {
                $result = Execution::withFallback(
                        function() use($loginUid, $invitInfo) {
                        	$gid = Event::GetEventInfoByEvent($invitInfo['event_id'], "gid", $data->required('version'), $platform);
                            /*加入群  */
                            Push::joinGroup($loginUid, $gid);
                            $this->_addEventUser(Yii::$app->db, $loginUid, $invitInfo['event_id']);
                            return Us\User\JOIN_OK;
                        },
                        function() use($loginUid, $invitInfo) {
                            $eventInfo = $this->_getEventInfo($loginUid, $invitInfo['event_id']);
                            if ($eventInfo) {
                                return Us\User\HAVE_JOIN;
                            } else {
                                return Us\User\JOIN_FALSE;
                            }
                        }
                );
            } else {
                $result = Us\User\HAVE_JOIN;
            }
        } finally {
            if ($result == Us\User\JOIN_FALSE) {
                $transaction->rollback();
            } else {
                $transaction->commit();
            }
        }
        $invitInfo['is_join'] = $result;
        Protocol::ok($invitInfo);
    }
    private function _addEventUser ($connection, $login_uid, $enven_id)
    {
        $resInsert = $connection->createCommand()->insert(self::$tableEventUser, [
                'event_id' => $enven_id,
                'member_uid' => $login_uid,
                'role' => 0,
                ])->execute();
    }
    private function _getInviteInfo ($invitationCode)
    {
        $query = new Query;
        $select = self::$tableEvent.".id as event_id, ".self::$tableEvent.'.name, '.self::$tableEvent.'.cover_page, '. 'unix_timestamp('.self::$tableEvent.'.start_time)*1000 as start_time, '.self::$tableUser.'.nickname, '.self::$tableUser.'.avatar, '.self::$tableEventUser.'.member_uid, '.self::$tableEvent.'.uid';
        $where = self::$tableEventUser.".invitation_code = '$invitationCode'";
        $inviteInfo = $query
        ->select($select)
        ->from(self::$tableEventUser)
        ->leftJoin(self::$tableEvent, self::$tableEvent.".id = ".self::$tableEventUser.".event_id")
        ->leftJoin(self::$tableUser, self::$tableUser.'.uid = '.self::$tableEventUser.".member_uid")
        ->where($where)
        ->one();
        return $inviteInfo;
    }

    private function _getEventInfo ($loginUid, $eventId)
    {
        $select = self::$tableEventUser.'.event_id, '.self::$tableEventUser.'.member_uid';
        $query = new Query;
        $eventUserDetail = $query
        ->select($select)
        ->from(self::$tableEventUser)
        ->where("event_id = '$eventId' and member_uid = '$loginUid'")
        ->one();
        return $eventUserDetail;
    }

    private function _updateEventUser ($connection, $login_uid, $event_id)
    {
        $resEvent = $connection->createCommand()->update(
                self::$tableEventUser,
                ['is_delete' => 0], ['event_id' => $event_id,'member_uid' => $login_uid])
                ->execute();
        return $resEvent;
    }

    private function _addEvent ($loginUid, $eventId)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = Execution::withFallback(
                    function() use($loginUid, $eventId) {
                        $gid = Event::GetEventInfoByEvent($eventId, "gid");
                        /*加入群  */
                        Push::joinGroup($loginUid, $gid);
                        $this->_addEventUser(Yii::$app->db, $loginUid, $eventId);
                        return Us\User\JOIN_OK;
                    },
                    function() use($loginUid, $eventId) {
                        $gid = Event::GetEventInfoByEvent($eventId, "gid");
                        Push::joinGroup($loginUid, $gid);
                        $this->_updateEventUser(Yii::$app->db, $loginUid, $eventId);
                        return Us\User\JOIN_OK;
                    },
                    function() use($loginUid, $eventId) {
                        if ($this->_getEventInfo($loginUid, $eventId)) {
                            return Us\User\HAVE_JOIN;
                        } else {
                            return Us\User\JOIN_FALSE;
                        }
                    }
            );
        } finally {
            if ($result == Us\User\JOIN_FALSE) {
                $transaction->rollback();
            } else {
                $transaction->commit();
            }
        }
        return $result;
    }
    private function _getEventList ($loginUid, $startTime, $endTime, $version, $platform)
    {
        /* 库查询 */
        $select = "uid, id as event_id, name, concat('event/coverpage/',`cover_page`) as cover_page, unix_timestamp(`start_time`)*1000 as start_time, status, unix_timestamp(".self::$tableEvent.".create_time)*1000 as create_time";
        $query = new Query;
        $eventList = $query
        ->select($select)
        ->from(self::$tableEventUser)
        ->leftJoin(self::$tableEvent, self::$tableEvent.".id = ".self::$tableEventUser.".event_id")
        ->where(['and', 'member_uid='.$loginUid, 'status != '.self::EVENT_STATUS_DELETE])
        ->andWhere('start_time>=:start_time',[':start_time'=>$startTime])
        ->andWhere('end_time<=:end_time',[':end_time'=>$endTime])
        ->andWhere('is_deleted=:is_deleted',[':is_deleted'=>self::STATUS_EVENT_USER_NORMAL])
        ->orderBy([self::$tableEventUser.'.create_time' => SORT_DESC])
        ->all();
        /*过滤字段为空的情况start  */
        $newArray = [];
        $newSubArray = [];
        foreach ($eventList as $key=>$value) {
            foreach ($value as $sKey=>$sValue) {
                if(is_null($value[$sKey])){
                    $newSubArray[$sKey] = '';
                }else{
                    if($sKey == "cover_page"){
                        $newSubArray[$sKey] = User::translationDefaultPicture($sValue, $version, $platform).".jpg";
                    }else{
                        $newSubArray[$sKey] = $sValue;
                    }
                }
            }
            $newSubArray['upload_count'] = Event::GetCountPictureByEvent($value['event_id'], $loginUid);
            $newArray[] = $newSubArray;
        }
        return $newArray;
    }
    
    /**
     * 邀请.分享重定向
     */
    public function redirectionAction()
    {
        $url = explode('/', $_SERVER["REQUEST_URI"]);
        if (isset($url)) {
            $code = str_replace("?", "&", $url[2]);
            $type = $url[1] === 'i' ? "invite" : "share";
            self::doRedirect($code, $type);
        }
    }

    private function doRedirect($code, $type)
    {
       Header("Location: " . Us\APP_URL . "share/share.html?invitation_code=" . $code . "&target=" . $type);
    }

    private function doQueryEventStatus($eventId)
    {
        if (empty($eventId)) {
            return -1;
        }
        $query = new Query;
        $query->select('status') ->from(Us\TableName\EVENT) ->where(['id' => $eventId]);
        $event = $query->one();
        if (empty($event)) {
            return -1;
        }
        return $event['status'];
    }

    private function doQueryEventStatusByCode($invitationCode)
    {
        if (empty($eventId)) {
            return -1;
        }
        $query = new Query;
        $query->select('status') ->from(Us\TableName\EVENT) ->where(['invitation_code' => $invitationCode]);
        $event = $query->one();
        if (empty($event)) {
            return -1;
        }
        return $event['status'];
    }

    private function doVerifyEventLockStatus($eventId)
    {
    	if (empty($eventId)) {
    		return Protocol::ok(null, null, null, Notice::get()->invalidEvent());;
    	}
    	$status = $this->doQueryEventStatus($eventId);
        switch (intval($status)) {
        	case self::EVENT_STATUS_NORMAL:
        	    return self::EVENT_STATUS_NORMAL;
        	    break;
        	case self::EVENT_STATUS_DELETE:
        	    return Protocol::ok(null, null, null, Notice::get()->eventNotExist());
        	    break;
        	case self::EVENT_STATUS_LOCK:
        	    return Protocol::ok(null, null, null, Notice::get()->eventLocked());
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $event['status']);
        }
    }

    public function lockEventAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            if (!$this->doVerifyEventLockStatus($data->requiredInt('event_id'))) {
                if ($this->doVerifyUserPower($data->requiredInt('event_id'), $data->requiredInt('login_uid'))) {
                    if ($this->doupdateEventStatus($data->requiredInt('event_id'), self::EVENT_STATUS_LOCK)) {
                        Protocol::ok();
                        $commit = true;
                    }
                }
                else {
                    Protocol::ok(null, null, Notice::get()->permissionDenied());
                }
            }
        }
        finally {
            if ($commit) {
                $transaction->commit();
            }
            else {
                $transaction->rollback();
            }
        }
    }

    private function doVerifyUserPower($eventId, $uid)
    {
        $role = $this->doQueryUserRole($eventId, $uid);
        switch ($role) {
        	case -1:
        	    return Protocol::ok(null, null, null, Notice::get()->quitEvent());
        	    break;
        	case self::EVENT_ROLE_CREATE:
        	    return self::EVENT_ROLE_CREATE;
        	    break;
        	case self::EVENT_ROLE_MEMBER:
        	    return self::EVENT_ROLE_MEMBER;
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $role);
        }
    }

    private function doQueryUserRole($eventId, $uid)
    {
    	if (empty($eventId) || empty($uid)) {
    		return -1;
    	}
    	$query = new Query;
    	$query->select('role') ->from(Us\TableName\EVENT_USER) ->where(['event_id' => $eventId, 'member_uid' => $uid, 'is_deleted' => self::STATUS_EVENT_USER_NORMAL]);
    	$event = $query->one();
    	if (empty($event)) {
    	    return -1;
    	}
    	return $event['role'];
    }

    private function doVerifyEventUnLockStatus($eventId)
    {
        if (empty($eventId)) {
            return -1;
        }
        $status = $this->doQueryEventStatus($eventId);
        switch ($status) {
        	case self::EVENT_STATUS_NORMAL:
        	    return Protocol::ok(null, null, null, Notice::get()->eventUnLocked());
        	    break;
        	case self::EVENT_STATUS_DELETE:
        	    return Protocol::ok(null, null, null, Notice::get()->eventNotExist());
        	    break;
        	case self::EVENT_STATUS_LOCK:
        	    return self::EVENT_STATUS_LOCK;
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $event['status']);
        }
    }

    private function doVerifyEventStatus($eventId, $invitationCode=null)
    {
        if (empty($eventId) && empty($invitationCode) ) {
            return -1;
        }
        return intval($eventId?$this->doQueryEventStatus($eventId):$this->doQueryEventStatusByCode($invitationCode));
    }

    public function unlockEventAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            if ($this->doVerifyEventUnLockStatus($data->requiredInt('event_id'))) {
                if ($this->doVerifyUserPower($data->requiredInt('event_id'), $data->requiredInt('login_uid'))) {
                    if ($this->doupdateEventStatus($data->requiredInt('event_id'), self::EVENT_STATUS_NORMAL)) {
                        Protocol::ok();
                        $commit = true;
                    }
                }
                else {
                    Protocol::ok(null, null, null, Notice::get()->permissionDenied());
                }
            }
        }
        finally {
            if ($commit) {
                $transaction->commit();
            }
            else {
                $transaction->rollback();
            }
        }
    }

    private function doUpdateEventStatus($eventId, $status)
    {
        if (empty($eventId) || !isset($status)) {
            return false;
        }
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->update(Us\TableName\EVENT, ['status' => $status], ['id' => $eventId])->execute();
        return $res;
    }

    /**
     * 文件直传
     */
    public function uploadPictureAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        /*获取签名 */
        $repeatSign = CosFile::getRepeatSign();
        if (Predicates::equals($this->doVerifyEventStatus($data->required('event_id')), self::EVENT_STATUS_LOCK)){
            Protocol::forbidden( null, Notice::get()->eventLocked() . ':' . User::getUserNickname(Event::GetEventInfoByEvent($data->required('event_id'), 'uid', $data->required('version'))));
            return ;
        }
        $this->limitSign($data->required('login_uid'));
        $pitrueId = 0;

        if (!$this->isUidJoinGroup($data->required('event_id'), $data->requiredInt('login_uid')) && !Event::isEventMember($data->required('event_id'), $data->requiredInt('login_uid'))) {
            Protocol::badRequest(NULL, NULL, 'user is not group');
        }
        
        if (!Event::isEventMyMoment($data->required('event_id'), $data->requiredInt('login_uid'), $data->required('moment_id'))) {
            Protocol::badRequest(NULL, NULL, 'event is not found or not event user');
        }
        $connection = Yii::$app->db;
        $tencentDic = CosFile::tenCentDir(CosFile::CATEGORY_SCENE);
        $fileName = CosFile::randPicName($data->required('event_id'), $data->required('moment_id'));
        $pitureId = $this->addData(CosFile::CATEGORY_SCENE, $data->required('event_id'), $data->required('moment_id'), $fileName,
            $data->optional('content', ''), $this->getUnix2SQLTimestamp(), $data->optional('shoot_device', ''), $data->optional('lat', ''),
            $data->optional('lng', ''), $data->required('size'));
        $uploadUrl = Us\Config\QCloud\COS_UPLOAD . Us\Config\QCloud\APP_ID . '/' . Us\Config\QCloud\BUCKET . '/event/moment/'.$fileName . '.jpg';
        $list['picture_id'] = $pitureId;
        $list['sign'] = $repeatSign;
        $list['path'] = $uploadUrl;
        Protocol::ok($list);
        return;
        Protocol::badRequest('', '', 'upload picture fail');
    }

    private function isUidJoinGroup($eventId, $loginUid)
    {
        $eventAssociatGroup = [];
        $eventIds = []; 
        $uidAssociatGroup = Event::uidJoinGroup($loginUid);
        if (!empty($uidAssociatGroup)) {
            foreach ($uidAssociatGroup as $k1 => $v1) {
                $datas[] = GroupModel::getGroupAssociatEvent($v1, 0, 0x7FFFFFFF);
            } 
            foreach ($datas as $k2 => $v2) {
                foreach ($v2 as $k3 => $v3) {
                    $eventIds[] = $v3->properties->eid;
                }
            }
            if (!in_array($eventId, array_unique($eventIds))) {
                return false;
            }
        }
        return true;
    }
    
    private function limitSign($loginUid)
    {
        $key = Us\User\SIGN . $loginUid;
        $pending = Yii::$app->redis->incr($key);
        Yii::$app->redis->expire($key, Us\Config\SIGN_EXPIRE);
        if ($pending > Us\Config\LIMIT_SIGN) {
             Protocol::tooManyRequest();
        }
    }

    private function addData($type, $event_id, $moment_id, $fileName, $content,$unix2SQLTimestamp,
        $shoot_device, $lat, $lng, $size)
    {
        return Event::doMomentPictureInsert(['type'=>Preconditions::checkNotEmpty($type),
            'event_id' => $event_id,
            'moment_id' => $moment_id,
            'object_id' => $fileName,
            'content' => $content,
            'shoot_time' => $unix2SQLTimestamp,
            'shoot_device' => $shoot_device,
            'lat' => $lat,
            'lng' => $lng,
            'size' => $size,
        ]);
    }

    private function getUnix2SQLTimestamp()
    {
        return Types::unix2SQLTimestamp(Protocol::required('shoot_time')/1000);//拍摄时间
    }

    /*
     * 提交文件
     */
    public function commitPictureAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $pictureName = Event::GetPictureInfoByEvent($data->required('event_id'), $data->required('moment_id'), $data->required('picture_id'), "object_id");
        if (Predicates::equals($this->doVerifyEventStatus($data->required('event_id')), self::EVENT_STATUS_LOCK)){
            Protocol::forbidden(null, Notice::get()->eventLocked() . ':' . User::getUserNickname(Event::GetEventInfoByEvent($data->required('event_id'), 'uid', $data->required('version'))));
            return;
        }
        if (!strpos(Us\Config\DOWNLOAD_DOMAIN, '://')) {
            $tencentWebUrl = 'http://' . Us\Config\DOWNLOAD_DOMAIN;
        } else {
            $tencentWebUrl = Us\Config\DOWNLOAD_DOMAIN;
        }
        switch (Us\Config\QCloud\TENCENT_UPLOAD_SOURCE) {
            case 0:
                $face = CosFile::face($tencentWebUrl . '/'. $pictureName);
                break;
            case 1:
                $face = FacePP::detect($tencentWebUrl . '/' . $pictureName);
                break;
        }
        Event::doMomentPictureUpdate($data->required('event_id'), $data->required('moment_id'), $data->required('picture_id'),
            ['data'=>$face]);
        $list['path'] = $pictureName;
        Protocol::ok($list);
        return;
    }
    /**
     * 提交动态
     */
    public function commitMomentAction()
    {
        $this->commitAction(Protocol::arguments());
    }

    private function getTencentUrl($dowloadUrl, $dowloadDir, $dowloadFileName)
    {
        return CosFile::getThird($dowloadUrl.$dowloadDir.$dowloadFileName.'.jpg');
    }
    
    public function testAction()
    {
        $uploadPictureCount = Event::GetCountPictureByEvent(2555, 1191, 202303);
        echo $uploadPictureCount;
        //Tao::getObject(268597);
        GroupModel::getNodeData(268597);
        exit;
        //删除
        //$connection->createCommand()->delete('target_push', 'create_time <= :create_time', [':create_time' => $time])->execute();
        print_r($userMember = array_merge(GroupModel::getGroupMember(257648), Tao::getAssociationRange(257648, 'OWNED_BY', 0, 0x7FFFFFFF)));
        $payload = [];
        $payload[] = ["uid" => 1191, "gid" => 265761, "join_time" => time(), "type" =>MiPush::JOINGROUP];
        $payload[] = ["uid" => 1191, "gid" => 265761, "type" =>MiPush::INVITEJOINGROUP];
        MiPush::submitWorks($payload);
    }
}
?>
