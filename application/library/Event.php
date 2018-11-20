<?php

use Yaf\Dispatcher;
use Yaf\Registry;
use yii\db\Query;
use yii\db\Expression;
class Event
{
	const EVENT_ROLE_CREATE = 1;//活动创建者
	const EVENT_ROLE_MEMBER= 0;//活动参与者
	const PICTURE_STATUS_NORMAL = 0;//状态正常的图片(picture)
	const EVENT_STATUS_NORMAL = 0;//状态正常的活动(event)
	const EVENT_STATUS_DELETE = 1;//状态正常的活动(event)
	const EVENT_STATUS_LOCK = 2;//状态正常的活动(event)
	const MOMENT_STATUS_NORMAL = 0;//状态正常的现场(moment)
	const STATUS_EVENT_USER_NORMAL =0;//活动参与者记录正常状态
	const STATUS_MOMENT_DELETE = 1;//现场动态删除状态
	const STATUS_EVENT_DELETE = 1;//(活动)现场删除状态
	const STATUS_PICTURE_DELETE = 1;//现场图片删除状态
	const STATUS_PICTURE_LOCK_DELETE = 2;//现场图片锁定删除状态
	const STATUS_EVENT_USER_DELETE = 1;//活动参与者记录删除状态
	const STATUS_USER_NORMAL = 0;//用户正常状态
	const GROUP_EVENT_TYPE_MOMENT = 0;//现场上传通知推送类型
	const GROUP_EVENT_TYPE_MOMENT_NORMAL = 0;//现场上传通知推送正常类型
	const GROUP_EVENT_TYPE_MOMENT_DELETE = 1;//现场图片上传通知推送删除类型
	const EVENT_LIVE_OPERATION_CREATE = 1;//event_live operation 活动创建
	const EVENT_LIVE_OPERATION_MODIFY = 2;//event_live operation 活动修改
	const EVENT_LIVE_OPERATION_PICTURE_MODIFY = 3;//event_live operation 图片修改
	const EVENT_LIVE_OPERATION_PICTURE_DELETE = 4;//event_live operation 图片删除
	const EVENT_LIVE_OPERATION_COMMIT = 5;//event_live operation 现场上传修改
	const EVENT_LIVE_OPERATION_EVENT_EXIT = 6;//event_live operation 现场退出活动
	public static  $tableEvent = Us\TableName\EVENT;
	public static  $tableEventLive = Us\TableName\EVENT_LIVE;
	public static  $tableMomentPicture = Us\TableName\MOMENT_PICTURE;
	public static  $tableEventMoment = Us\TableName\EVENT_MOMENT;
	public static  $tableEventUser = Us\TableName\EVENT_USER;
	public static  $tableUser = Us\TableName\USER;
	public static  $tableTubeUserEvent = Us\TableName\TUBE_USER_EVENT;
	public static  $appUrl = Us\Config\INIT_DOMAIN;
	public static $allMembersAvatarVersion = [2, 3];//获取活动所有成员的avatar(包括已经退出活动的人)
	/* 俩人共同参与活动数量和最老活动的活动名称
	 * 返回数组
	 *  */
	public static function JoinTogetherByUid($t_uid=0,$uid=0, $version = 0, $platform = 0)
	{
		if($t_uid&&$uid){
			$emptyDatas = [
				'event_id' => '', 
				'create_time' => '', 
				'nums' => 0, 
				'event_name' => '',
			];	
			$connection = Yii::$app->db;
			$sql = "select event_id,create_time,count(*) as nums from ".self::$tableEventUser." where member_uid = ".$uid ." and is_deleted = 0 and event_id in (select event_id from ".self::$tableEventUser." as eu inner join ".self::$tableEvent." as e on eu.event_id = e.id  where member_uid =".$t_uid." and is_deleted = 0 and e.status != 1 and e.name is not null) order by event_id asc";
			$command = $connection->createCommand($sql);
			$datas = $command->queryOne();
			if(!empty($datas['event_id'])){
				/* 活动详情 */
				$event_detail = self::GetEventInfoByEvent($datas['event_id'], '*', $version, $platform);
				if(!empty($event_detail['name'])){
					$datas['event_name'] = $event_detail['name'];
				}else{
					$datas['event_name'] = '';
				}
			}else{
				return $emptyDatas;
			}
			return $datas;
		}else{
			return $emptyDatas;
		}
	}
	
	/* 返回某人最近参加的活动
	 * 返回数组
	*  */
	public static function JoinEventLateByUid($uid=0)
	{
		$select = "member_uid, name, unix_timestamp(start_time)*1000 as start_time, cover_page, gid, event_id, status";
		$query = new Query;
		$data = $query
		->select($select)
		->from(self::$tableEventUser)
		->leftJoin(self::$tableEvent, self::$tableEvent.".id = ".self::$tableEventUser.".event_id")
		->where(['member_uid'=>$uid, 'status !='.self::EVENT_STATUS_DELETE.' and is_deleted = 0'])
		->orderBy([self::$tableEventUser.'.create_time' => SORT_DESC])
		->one();
		$data['is_join'] = 2;
		$data['gender'] = 1;
		return $data;
	}
	
	/* 通过event_id查询活动信息
	 * 返回数组
	*  */
	public static function GetEventInfoByEvent($event_id, $select='*', $version = 0, $platform = 0)
	{
		$selectOld = $select;
		if($event_id){
			if($select=="*" || empty($select)) {
				$select = "uid, gid, name, tao_object_id, concat('event/coverpage/',`cover_page`) as cover_page, unix_timestamp(start_time)*1000 as start_time, unix_timestamp(end_time)*1000 as end_time,description as desc, invitation_code, live_id, status, create_time";
			}else{
				$select = $select;
			}
	    	$query = new Query;
	    	$event_detail = $query
	    	->select($select)
	    	->from('event')
	    	->where("id = ".$event_id)
	    	->one();

	    	if(isset($event_detail['cover_page'])){
// 	    		if (strpos($event_detail['cover_page'], ".jpg") === false){
//     	    		$event_detail['cover_page'] = User::translationDefaultPicture($event_detail['cover_page']).".jpg";
//     	    	}
    	    	$event_detail['cover_page'] = User::translationDefaultPicture($event_detail['cover_page'], $version, $platform).".jpg";
	    	}
	    	if($selectOld=="*" || is_null($selectOld)) {
	    		return $event_detail;
	    	}else {
	    		return $event_detail[$select];
	    	}
		}else{
			return array();
		}
	}
	
	/* 通过event_id,moment_id,picture_id查询照片信息
	 * 返回数组
	*  */
	public static function GetPictureInfoByEvent($event_id, $moment_id, $id, $selectField = "*", $isDir = NULL)
	{
		if($event_id){
			$select = $selectField;
			$query = new Query;
			$picture_detail = $query
			->select($select)
			->from(self::$tableMomentPicture)
			->where("id = ".$id)
			->one();
			if($selectField == "*"){
				return $picture_detail;
			}else	{
				if(($selectField =="object_id") && empty($isDir)) {
					return "event/moment/".$picture_detail[$selectField].".jpg";
				}else {
					return $picture_detail[$selectField];
				}
			}
		}else{
			return array();
		}
	}
	
	/* 通过event_id,moment_id查询动态信息
	 * 返回数组
	*  */
	public static function GetMomentInfoByEvent($event_id, $moment_id, $selectField ="*")
	{
		if($moment_id){
			$select = $selectField;
			$query = new Query;
			$moment = $query
			->select($select)
			->from(self::$tableEventMoment)
			->where("event_id = ".$event_id." and id=".$moment_id)
			->one();
			if($selectField == "*"){
				return $moment;
			}else	{
				return $moment[$selectField];
			}
		}else{
			return array();
		}
	}
	
	/* 通过event_id,moment_id查询照片列表
	 * 返回数组
	*  */
	public static function GetPictureListByEvent($event_id, $moment_id, $selectField ="*")
	{
		if($event_id){
			$select = $selectField;
			$query = new Query;
			$picture_list = $query
			->select($select)
			->from(self::$tableMomentPicture)
			->where("event_id = ".$event_id." and moment_id=".$moment_id." and status != ".self::STATUS_PICTURE_DELETE)
			->all();
				
			if(empty($picture_list)) {
				return $picture_list;
			}else	{
				return self::GetEventPictureUrl($picture_list, "url");
			}
		}else{
			return array();
		}
	}
	
	/* 通过event_id,moment_id查询照片列表及活动信息及动态信息
	 * 返回数组
	 *  */
	public static function getEventMomentPictureInfoByMoment($eventId, $momentId, $selectField = "*")
	{
	    $sql = "select {$selectField} from ".self::$tableMomentPicture." as p left join ".self::$tableEventMoment." as m on m.id = p.moment_id left join ".self::$tableEvent." as e on e.id = p.event_id left join ".self::$tableUser." as u on u.uid = m.uid where p.event_id = {$eventId} and p.moment_id = {$momentId} and p.status != 1 and e.status != 1 and m.status = 0 ORDER BY shoot_time ASC";
	    return Yii::$app->db->createCommand($sql)->queryAll();
	}
	
	/* 生成活动邀请码
	 * 返回邀请码
	 * $len长度
	 * $table会查询$table内的invitation_code是否存在
	*  */
	public static function CreateInvitationCode($len, $table = null, $chars = null) {
    	for($i = 0; $i <= 10; $i++) {
    	    $str = self::getRandStr($len, $chars = null);
    	    if (is_numeric($str)) {
    	        $str = self::getRandStr($len, $chars = null);
    	    } else {        
    	        break;
    	    }
    	}

    	for($i = 0; $i <= 100; $i++) {
        	if(!empty($table)) {
    	    	/* 判断邀请码是否冲突  */
    	    	$count = self::getTableWhereCount($table, "invitation_code = :invitation_code", [":invitation_code"=>$str]);
    	    	/*如果有重复的邀请码,就重新生成  */
    	    	if($count > 0){
    	    	    if($i < 10) {
    	    	        $str = self::getRandStr($len, $chars = null);
    	    	    } elseif ($i < 20) {
    	    	        $str = self::getRandStr($len+1, $chars = null);
    	    	    } elseif ($i < 30) {
    	    	        $str = self::getRandStr($len+2, $chars = null);
    	    	    } elseif ($i < 40) {
    	    	        $str = self::getRandStr($len+3, $chars = null);
    	    	    } elseif ($i < 50) {
    	    	        $str = self::getRandStr($len+4, $chars = null);
    	    	    } elseif ($i < 60) {
    	    	        $str = self::getRandStr($len+5, $chars = null);
    	    	    } else {
    	    	        throw new InvalidArgumentException("invitation_code is already exists");
    	    	    }
    	    	} else {
    	    	    return $str;
    	    	    break;
    	    	}
        	 } else {
        	    return $str;
        	    break;
        	}
    	}
	}
	
	/*生成随机码  */
	public static function getRandStr($len, $chars=null) {
	    if (is_null($chars)){
	        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	    }
	    mt_srand ( ( double ) microtime () * 10000 ); //optional for php 4.2.0 and up.随便数播种，4.2.0以后不需要了。
	    $charid = strtoupper ( md5 ( uniqid ( rand (), true ) ) ); //根据当前时间（微秒计）生成唯一id.
	    $uid = uniqid("", true);
	    $data = $charid;
	    $data .= $_SERVER['REQUEST_TIME'];
	    $data .= @$_SERVER['HTTP_USER_AGENT'];
	    $data .= @$_SERVER['REMOTE_ADDR'];
	    $data .= @$_SERVER['REMOTE_PORT'];
	    $hash = strtoupper(hash('ripemd128', $uid . $chars . md5($data)));
	    return substr($hash, 0, $len);
	}
	
	/*
	 * 获得指定表,指定条件的数据count 
	 * */
	public static function getTableWhereCount($table, $whereField, $whereArray) {
	    return (new Query())->from($table)->where($whereField, $whereArray)->count();
	}
	
	/*
	 * 返回大图片列表的活动
	 *  */
	public static function GetEventBigPicture($event_id, $limit=50){
	    if(!empty($event_id)){
	        $query = new Query;
	        $select = "object_id,".self::$tableMomentPicture.".event_id,".self::$tableMomentPicture.".content, nickname, avatar, ".self::$tableEventMoment.".uid";
	        $where = self::$tableMomentPicture.".event_id = ".$event_id." and ".self::$tableMomentPicture.".status != ".self::STATUS_PICTURE_DELETE." and ".self::$tableEventMoment.".status = ".self::MOMENT_STATUS_NORMAL." and ".self::$tableUser.".status = ".self::STATUS_USER_NORMAL;
	        $pictures = $query
	        ->select($select)
	        ->from(self::$tableUser)
	        ->leftjoin(self::$tableEventMoment,self::$tableUser.'.uid = '.self::$tableEventMoment.'.uid')
	        ->leftjoin(self::$tableMomentPicture,self::$tableMomentPicture.'.moment_id= '.self::$tableEventMoment.'.id')
	        ->where($where)
	        ->limit($limit)
            ->orderBy("shoot_time")
	        ->all();
	         
	        if(!empty($pictures)){
	            return self::GetEventPictureUrl($pictures, "object_id");
	        }else{
	            return $pictures;
	        }
	    }	    
	}
	
	/* 
	 * 返回活动图片列表
	*  */
	public static function GetEventPicture($event_id, $limit=50){
	    
		if(!empty($event_id)){
			$query = new Query;
			$select = "concat('event/moment/',`object_id`) as object_id, event_id, content";
			$where = "event_id = ".$event_id." and ".self::$tableMomentPicture.".status != ".self::STATUS_PICTURE_DELETE;
			$pictures = $query
			->select($select)
			->from(self::$tableMomentPicture)
			->where($where)
			->limit($limit)
			->all();

			if(!empty($pictures)){

			    return self::GetEventPictureUrl($pictures, "object_id");
			}else{
			    return $pictures;
			}
		}
	}
	
	/*
	 * 处理数组,图片路径拼event
	*  */
	public static function GetEventPictureUrl($list, $fields, $noShowWhere = null){
		/*过滤字段为空的情况start  */
		$newArray = [];
		$newSubArray = [];
		foreach ($list as $key=>$value) {
		    if(!empty($noShowWhere)) {
		        foreach ($noShowWhere as $wKey=>$wValue) {
		            if($value[$wKey] == $wValue) {
		                continue;
		            }
		        }
		    }
			foreach ($value as $sKey=>$sValue) {
				if(empty($value[$sKey])){
					if($value[$sKey] === "0"){
						$newSubArray[$sKey] = "0";
					}else{
						$newSubArray[$sKey] = "";
					}
				}else{
					if($sKey == $fields){
						if(strpos($sValue, ".jpg") !== false){
							$newSubArray[$sKey] = strval($sValue);
						}else{
							$newSubArray[$sKey] = $sValue.".jpg";
						}
					}else{
						$newSubArray[$sKey] = strval($sValue);
					}
				}
			}
			$newArray[] = $newSubArray;
		}
		return $newArray;
		/*过滤字段为空的情况end  */
	}
	
	/*查看查看某人在某个活动上传了多少张图片  */
	public static function GetCountPictureByEvent($eventId ,$loginUid, $momentId=0) {
		$query = new Query;
		$select = "*";
		if($momentId > 0) {
			$andWhere = " and ".self::$tableMomentPicture.".moment_id = ".$momentId." ";
		} else {
			$andWhere = "";
		}
		$where = self::$tableMomentPicture.".event_id = ".$eventId." and ".self::$tableMomentPicture.".status != 1 and ".self::$tableEventMoment.".uid = ".$loginUid." and ".self::$tableEventMoment.".status = 0".$andWhere;
		$count = $query
		->select($select)
		->from(self::$tableMomentPicture)
		->leftJoin(self::$tableEventMoment, self::$tableMomentPicture.".moment_id = ".self::$tableEventMoment.".id")
		->where($where)
		->count();
		return $count;
	}
	
	/*加入活动,H5分享邀请登录后调用  */
	public static function AddEvent($loginUid, $invitation_code) {
		$query = new Query;
		$event = $query
		->select("id, gid, name")
		->from(self::$tableEventUser)
		->leftJoin(self::$tableEvent, self::$tableEventUser.'.event_id = '. self::$tableEvent.'.id')
		->where(self::$tableEventUser.".invitation_code='". $invitation_code. "' and " .self::$tableEvent.'.status!='. self::EVENT_STATUS_DELETE)
        ->one();
		if(!empty($event)) {
			$countUser = $query
			->select("is_deleted")
			->from(self::$tableEventUser)
			->where(["member_uid" => $loginUid, "event_id"=>$event['id']])
            ->one();
            $pushTime = Types::unix2SQLTimestamp(time());
			$connection = Yii::$app->db;
			$isMiPush = false;
			if(!$countUser) {
				/* event_user插入创建活动的成员 */
				$resInsertUser = $connection -> createCommand() -> insert(self::$tableEventUser, [
						'event_id' => $event['id'],
						'member_uid' => $loginUid,
						'role' => self::EVENT_ROLE_MEMBER,//活动创建角色
						'invitation_code' => self::CreateInvitationCode(7, self::$tableEventUser),
				        'create_time' => $pushTime
				]) -> execute();
				$isMiPush = true;
			}else{
				if($countUser['is_deleted'] == self::STATUS_EVENT_USER_DELETE) {
					/*修改event_user is_deleted  */
					$resEventUser = $connection->createCommand()->update(self::$tableEventUser, [
							'is_deleted' => self::STATUS_EVENT_USER_NORMAL,
							'create_time' => $pushTime,
					        'receive_push_count' => 0,
					        'receive_push_time' => null
					], ['event_id'=>$event['id'], 'member_uid'=>$loginUid])->execute();
					$isMiPush = true;
				}
			}
			/*加入群  */
			Push::joinGroup($loginUid, $event['gid']);
			/*miPush推送数据start  */
			error_log($loginUid." add event ".$event['id']. "first");
			if($isMiPush) {
				//MiPush::inviteSendMessage($event['id'], array($loginUid), "yaoqing", "Us");
				$payload = [];
				$payload[] = ["uid" => $loginUid, "event_id" => $event['id'], "type" =>MiPush::INVITEJOINEVENT];
				/* 前三个加入活动的人给活动创建人推送加入信息 start */
				$eventMembers = self::getTableDataRows(self::$tableEventUser, "event_id = :event_id and is_deleted = :is_deleted", [":event_id" => $event['id'], ":is_deleted" => self::STATUS_EVENT_USER_NORMAL], "member_uid, receive_push_count, receive_push_time");
				if(Predicates::isNotEmpty($eventMembers)) {
				    foreach ($eventMembers as $member) {
				        if(($member['member_uid'] !== $loginUid) && ($member['receive_push_count'] < 3)) {
				            //不给自己推，并且接收次数小于三次的推
				            //$desc = User::getUserNickname($loginUid) . " 已加入您的故事'" . $event['name'] . "'";
				            //MiPush::addEventSendMessage($event['id'], [$member['member_uid']], "addEvent", "Us", $desc);
				            $payload[] = ["uid" => $loginUid, "event_id" => $event['id'], "to_uid" =>$member['member_uid'], "type" =>MiPush::JOINEVENT];
				            error_log($loginUid." add event ".$event['id']. "two");
				            self::doTableUpdate(self::$tableEventUser, ["receive_push_count" => new Expression("receive_push_count+1")], "event_id = :event_id and member_uid = :member_uid", [":event_id" => $event['id'], ":member_uid" => $member['member_uid']]);
				        }else {
				            if(($member['member_uid'] !== $loginUid) && (Predicates::isEmpty($member['receive_push_time']))) {
				                self::doTableUpdate(self::$tableEventUser, ["receive_push_time" => $pushTime], "event_id = :event_id and member_uid = :member_uid", [":event_id" => $event['id'], ":member_uid" => $member['member_uid']]);
				            }
				        }
				    }
				}
				MiPush::submitWorks($payload);
				/* 前三个加入活动的人给活动创建人推送加入信息 end */
			}
			/*miPush推送数据end  */
			return true;
		}else{
			return false;
		}
	}
	
	/*判断是否登录  */
	public static function isLogin($data) {
		$loginUid = $data->requiredInt('login_uid');//登录uid
		$sessionKey = $data->required('session_key');//当前session_key
		$isLogin = Session::verify($loginUid, $sessionKey);
		if(!$isLogin) {
			Protocol::temporaryRedirect('', '', 'please to login');//307 please to login
			return;
			//throw new UsException('307',"","please to login");
		}
	}
	
	/* 通过event_id,login_id查看活动是否可以修改
	 * 返回true/false
	*  */
	public static function isCanModifyEvent($loginUid, $eventId=0, $momentId=0, $pictureId=0, $version = 0, $platform = 0 )
	{
		if($momentId ==0 && $pictureId==0){
			$uid = self::GetEventInfoByEvent($eventId, "uid", $version, $platform);
			if($loginUid != $uid) {
				return false;
			}else{
				return true;
			}
		}else{
			return true;
		}
	}
	
	/* 通过eventId,loginId, momentId, pictureid查看动态或图片是否有权限修改
	 * 返回true/false
	 *  */
	public static function isCanModifyMomentOrPicutre($loginUid, $eventId=0, $momentId=0, $pictureId=0, $version = 0, $platform = 0 )
	{
	    if (!empty($momentId)){
	        /*判断动态删除修改权限  */
	        $eventUid = self::GetEventInfoByEvent($eventId, "uid", $version, $platform);
	        if($loginUid != $eventUid) {
	            $momentUid = self::GetMomentInfoByEvent($eventId, $momentId, "uid");
	            if($loginUid == $momentUid) {
	                /*动态创建人有仅限  */
	                return true;
	            } else {
	               return false;
	            }
	        } else {
	           /*活动创建人有权限  */
	           return true;
	        }
	    } else {
	        /*没有momentId默认都没有权限  */
	        return false;
	    }
	}
	
	/*获取某人在某活动最近上传的图片id  */
	public static function getPictureIdLast($uid, $eventId) {
		$query = new Query;
		$select = self::$tableMomentPicture.".id";
		$where = self::$tableMomentPicture.".event_id = ".$eventId." and uid=".$uid." and ".self::$tableMomentPicture.".status=".self::PICTURE_STATUS_NORMAL." and ".self::$tableEventMoment.".status=".self::MOMENT_STATUS_NORMAL;
		$pictures_id = $query
		->select($select)
		->from(self::$tableMomentPicture)
		->leftJoin(self::$tableEventMoment, self::$tableEventMoment.".id = ".self::$tableMomentPicture.".moment_id")
		->where($where)
		->orderBy([self::$tableEventMoment.'.create_time' => SORT_DESC])
		->one();
		return  $pictures_id;
	}

    /**
     * 通过邀请码获取活动信息
     */
    public static function getInviteCodeByEventIdAndMemberUid ($eventId, $memberUid)
    {
        $query = new Query;
        $inviteCode = $query
        ->select('invitation_code')
        ->from(self::$tableEventUser)
        ->where(['event_id' => $eventId, 'member_uid' => $memberUid])
        ->one();

        return $inviteCode['invitation_code'];
    }
    /**
     * 通过用户邀请码获取活动信息
     */
    public static function getEventIdByInviteCode($invitation_code)
    {
    	$query = new Query;
    	$inviteCode = $query
    	->select('event_id')
    	->from(self::$tableEventUser)
    	->where(['invitation_code' => $invitation_code])
    	->one();
    	return $inviteCode['event_id'];
    }

    public static function getEventMembersAvatar($eventId, $excludeDefaultAvatar = false, $version = null)
    {
        if(!empty($version) && $version >1) {
            /*获取活动所有成员的  包括已经退出活动的人*/
            return (new Query())->select('u.avatar, u.nickname, u.uid, u.gender, e.is_deleted')
                ->from(self::$tableEventUser . ' as e')->innerJoin(self::$tableUser . " as u", "u.uid = e.member_uid")
                ->leftJoin("(select max(create_time) as create_time, uid from ".self::$tableEventMoment." where status = ".self::MOMENT_STATUS_NORMAL." and event_id = " . $eventId . " group by uid) as m", "m.uid = e.member_uid")
                ->where("e.event_id = " . $eventId . ($excludeDefaultAvatar ? (" and u.avatar != 'default'") : ""))
                ->orderBy(["e.role" => SORT_DESC, "m.create_time" => SORT_DESC, "e.create_time" => SORT_DESC])
                ->all();
        } else {
            /*获取活动所有成员的  不包括已经退出活动的人*/
            return (new Query())->select('u.avatar, u.nickname, u.uid, u.gender, e.is_deleted')
                ->from(self::$tableEventUser . ' as e')->innerJoin(self::$tableUser . " as u", "u.uid = e.member_uid")
                ->leftJoin("(select max(create_time) as create_time, uid from ".self::$tableEventMoment." where status = ".self::MOMENT_STATUS_NORMAL." and event_id = " . $eventId . " group by uid) as m", "m.uid = e.member_uid")
                ->where("e.event_id = " . $eventId ." and " . "e.is_deleted = 0". ($excludeDefaultAvatar ? (" and u.avatar != 'default'") : ""))
                ->orderBy(["e.role" => SORT_DESC, "m.create_time" => SORT_DESC, "e.create_time" => SORT_DESC])
                ->all();
        }
    }
    
    /*修改活动live_id  */
    public static function updateLiveId($loginUid, $eventId, $momentId=0, $operation=0, $isReturnLive=0)
    {
    	$connection = Yii::$app->db;
    	$liveId = CosFile::randPicName($eventId, $momentId);//分享图片id
    	$resInsert = $connection->createCommand()->insert(self::$tableEventLive,[
    			'live_id' => $liveId,
    			'event_id' => $eventId,
    			'author' => $loginUid,
    			'operation' => $operation,
		])->execute();
    	$resUpdateLive = $connection->createCommand()->update(self::$tableEvent, [
    			'live_id'=>$liveId
		],['id'=>$eventId])->execute();
    	//Execution::autoTransaction($connection, function() use($connection, $eventId, $momentId, $table, $isReturnLive, $liveId) {
    		//$table0:修改event表;1:修改moment表;2:修改event表和moment表
    		//$isReturnLive 大于0的情况下需要返回$liveId
    	//});
    	/*是否需要返回liveId  */
    	if($isReturnLive > 0) {
    		return $liveId;
    	}
    }
    
    /**
     * 查询参加活动中发的动态
     */
    public static function getEventMomentId($uid)
    {
        if (!empty($uid)) {
            return (new Query())->select(''.self::$tableEventUser.'.event_id, '.self::$tableEventMoment.'.id as moment_id')->from(self::$tableEventUser)
            ->leftJoin(self::$tableEventMoment, ''.self::$tableEventUser.'.event_id ='.self::$tableEventMoment.'.event_id')
            ->where( ''.self::$tableEventUser.'.member_uid = '.$uid.' and '.self::$tableEventUser.' .is_deleted = 0 and '.self::$tableEventMoment.'.status = 0')
            ->orderBy([''.self::$tableEventMoment.'.create_time' => SORT_DESC])
            ->all();
        }
        return [];
    }
    
    /*推送数据动态  */
    public static function tubePush($eventId, $momentId, $uploadType, $groupType = 0, $version = 0, $platform = 0) {
    	$query = new Query;
    	$select = self::$tableEvent.".uid as event_uid, ".self::$tableEventMoment.".uid, nickname, concat('profile/avatar/',`avatar`) as avatar, name, moment_id, ".self::$tableMomentPicture.".event_id, unix_timestamp(".self::$tableMomentPicture.".create_time)*1000 as create_time, unix_timestamp(start_time)*1000 as start_time, concat('event/moment/',`object_id`) as object_id, size, concat('event/coverpage/',`cover_page`) as cover_page, ".self::$tableMomentPicture.".status, ".self::$tableMomentPicture.".id as picture_id";
    	$where = self::$tableMomentPicture.".event_id = ".$eventId." and ".self::$tableMomentPicture.".moment_id=".$momentId." and ".self::$tableMomentPicture.".status=".self::MOMENT_STATUS_NORMAL;
    	$pictures = $query
    	->select($select)
    	->from(self::$tableMomentPicture)
    	->leftJoin(self::$tableEventMoment, self::$tableEventMoment.".id = ".self::$tableMomentPicture.".moment_id")
    	->leftJoin(self::$tableEvent, self::$tableEvent.".id = ".self::$tableMomentPicture.".event_id")
    	->leftJoin(self::$tableUser,self::$tableUser.".uid = ".self::$tableEventMoment.".uid")
    	->where($where)
        ->all();
        if (!empty($pictures)){
            $total = 0;
        } else {
            $total = count($pictures);
        }
    	$pictures = array_slice($pictures, 0 , 9);
    	$momentIdArray = [];
    	$newArray =[];
    	$i = 0;
    	$j = 0;
    	foreach ($pictures as $key =>$item) {
    		if(!in_array($item['moment_id'],$momentIdArray)){
    			$newArray[$i]['nickname'] = $item['nickname']?$item['nickname']:'';
    			if(empty($item['avatar'])){
    				$newArray[$i]['avatar'] = "";
    			}else{
    				$newArray[$i]['avatar'] = $item['avatar'].".jpg";
    			}
    			$newArray[$i]['uid'] = $item['uid'];
    			$newArray[$i]['event_uid'] = $item['event_uid'];
    			$newArray[$i]['name'] = Predicates::isEmpty($item['name']) ? '' : $item['name'];
    			$newArray[$i]['cover_page'] = !empty($item['cover_page']) ? User::translationDefaultPicture($item['cover_page'], $version, $platform).".jpg" : "";
    			$newArray[$i]['moment_id'] = $item['moment_id'];
    			$newArray[$i]['event_id'] = $item['event_id'];
    			$newArray[$i]['create_time'] = $item['create_time'];
    			$newArray[$i]['start_time'] = $item['start_time'];
    			$newArray[$i]['total'] = $total;
    			$newArray[$i]['upload_type'] = $uploadType;
    			$j = $i;
    			$i++;
    		}
    		$pictureArray[$j]['picture_id'] = $item['picture_id'];
    		$pictureArray[$j]['size'] = $item['size'];
    		$pictureArray[$j]['status'] = $item['status'];
    		$pictureArray[$j]['url'] = !empty($item['object_id']) ? $item['object_id'].".jpg" : "";
    		$newArray[$j]['picture'][] = $pictureArray[$j];
    		$momentIdArray[] = $item['moment_id'];
    	}
    	if(!empty($newArray[0])) {
    		$newArray = $newArray[0];
    	}
    	if(($uploadType == self::GROUP_EVENT_TYPE_MOMENT_DELETE) && (empty($newArray[0]) && ($total==0))) {
    		$newArray['moment_id'] = $momentId;
    		$newArray['event_id'] = $eventId;
    		$newArray['uid'] = self::GetMomentInfoByEvent($newArray['event_id'], $newArray['moment_id'], "uid");
    		$newArray['total'] = $total;
    		$newArray['upload_type'] = $uploadType;
    	}
    	//$payloadArray = !empty($newArray[0]) ? $newArray[0] : "";
    	/*群推送  */
    	if($groupType === 0) {
    	    $groupId = self::GetEventInfoByEvent($eventId, "gid", $version, $platform);
    	    if(!empty($groupId) && !empty($newArray) && $total == 0) {
    	        $resPush = Push::pushToGroup($newArray['uid'], $groupId, self::GROUP_EVENT_TYPE_MOMENT, $newArray);
    	    }
    	}else {
    	    $node = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
            if ($node) {
                foreach ($node as $data){
                    //Group::pushGroupMoment($data->to, $loginUid, $eventId, 0);
                    $groupId = Tao::getObject($data->to)->properties->pgid;
                    if(!empty($groupId) && !empty($newArray) && $total == 0) {
                        Push::pushToGroup($newArray['uid'], $groupId, self::GROUP_EVENT_TYPE_MOMENT, $newArray);
                    }
                }
            }
    	}
    }
    
    /*获取现场  */
    public static function getEventMoment($eventId, $select="*", $param=[]) {
        $where = "status = ".self::MOMENT_STATUS_NORMAL." and event_id = ".$eventId;
    	if(!empty($param)) {
    		foreach ($param as $key=>$p) {
    			$where .= " and ".$key."=".$p;
    		}
    	}
    	/*查询某人活动的动态  */
    	$query = new Query;
    	$moments = $query
    	->select($select)
    	->from(self::$tableEventMoment)
    	->where($where)
    	->all();
    	return $moments;
    }
    
    /*获取某个活动下成员*/
    public static function getEventUser($eventId)
    {
        $where = "event_id=".$eventId;
        if (!empty($eventId)) {
            $users = [];
            $query = new Query;
            $event_user = $query
            ->select('member_uid')
            ->from(self::$tableEventUser)
            ->where(['event_id'=>$eventId, 'is_deleted'=>self::STATUS_EVENT_USER_NORMAL])
            ->all();
            foreach ($event_user as $user) {
                $users[$user['member_uid']] = $user['member_uid'];
            }
            return $users;
        }
        return [];
    }
    
    /* 
     * 向某表插入数据
     *  */
    public static function doTableInsert($table, $parameter, $isReturnInsertId = 0) {
        $connection = Yii::$app->db;
        $resInsert = $connection->createCommand()->insert($table, $parameter)->execute();
        if($isReturnInsertId > 0) {
            return $connection->getLastInsertID();
        } else {
            return $resInsert;
        }
    }
    
    /*
     * 向某表查询数据(可传指定字段名)
     *  */
    public static function getTableFields($table, $whereFileds, $whereArray, $fields = null) {
        if(Predicates::isArray($fields)) {
            $fields = implode(",", $fields);
            return (new Query())
            ->select(Predicates::isNull($fields) ? "*" : $fields)
            ->from($table)
            ->where($whereFileds, $whereArray)
            ->one();
        }else {
            return (new Query())
            ->select(Predicates::isNull($fields) ? "*" : $fields)
            ->from($table)
            ->where($whereFileds, $whereArray)
            ->one()[$fields];
        }
        
    }
    
    /*
     * 向某表查询数据(rows)
     *  */
    public static function getTableDataRows($table, $whereFileds, $whereArray, $select = null) {
        return (new Query())
        ->select(Predicates::isNull($select) ? "*" : $select)
        ->from($table)
        ->where($whereFileds, $whereArray)
        ->all();
    }
    
    /*
     * 某表更改数据
     *   */
    public static function doTableUpdate($table, $updateFileds, $whereFileds, $whereArray) {
        return Yii::$app->db->createCommand()->update($table, $updateFileds, $whereFileds, $whereArray)->execute();
    }
    
    /* 
     * moment_picture写入数据
     * */
    public static function doMomentPictureInsert($parameter) {
    	$connection = Yii::$app->db;
    	$res_insert = $connection->createCommand()->insert(self::$tableMomentPicture,[
    			'event_id' => $parameter['event_id'],
    			'moment_id' => $parameter['moment_id'],
    			'object_id' => $parameter['object_id'],
    			'content' => isset($parameter['content']) ? $parameter['content'] : "",
    			'shoot_time' => $parameter['shoot_time'],
    			'shoot_device' => isset($parameter['shoot_device']) ? $parameter['shoot_device'] : "",
    			'lat' => isset($parameter['lat']) ? $parameter['lat'] : "",
    			'lng' => isset($parameter['lng']) ? $parameter['lat'] : "",
    			'size' => isset($parameter['size']) ? $parameter['size'] : "",
    			'data' => isset($parameter['data']) ? $parameter['data'] : "",
    			'status' => self::STATUS_PICTURE_DELETE,//上传成功后是删除状态
    			])->execute();
    	return $connection->getLastInsertID();//返回pictureid
    }
    
    /*event更改数据  */
    public static function doEventUpdate($eventId, $updateFileds) {
    	return Yii::$app->db->createCommand()->update(self::$tableEvent, $updateFileds, 'id=:id',array(':id'=>$eventId))->execute();
    }
    
    /*moment_picture更改数据  */
    public static function doMomentPictureUpdate($eventId, $momentId, $pictureId = 0, $updateFileds) {
        if(!empty($pictureId)) {
            $whereArray = ["id"=>$pictureId, "moment_id"=>$momentId, "event_id"=>$eventId];
        } else {
            $whereArray = ["moment_id"=>$momentId, "event_id"=>$eventId];
        }
        return Yii::$app->db->createCommand()->update(self::$tableMomentPicture, $updateFileds, $whereArray)->execute();
    }
    
    /*event_moment更改数据  */
    public static function doEventMomentUpdate($eventId, $momentId, $updateFileds) {
        return Yii::$app->db->createCommand()->update(self::$tableEventMoment, $updateFileds, ["id"=>$momentId, "event_id"=>$eventId])->execute();
    }
    
    public static function doVerifyEventStatus($statusCode, $eventId, $invitationCode=null)
    {
    	if (is_null($statusCode)) {
    		return false;
    	}
    	return Predicates::equals(self::doVerifyEventLock($eventId, $invitationCode), $statusCode);
    }

    public static function doVerifyEventLock($eventId, $invitationCode)
    {
        if (empty($eventId) && empty($invitationCode)) {
            return -1;
        }
        return intval($eventId?self::doQueryEventStatus($eventId):self::doQueryEventStatusByCode($invitationCode));
    }

    private static function doQueryEventStatus($eventId)
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

    private static function doQueryEventStatusByCode($invitationCode)
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
    
    public static function inviteTitle($title)
    {
        return '「' . $title . "」的照片都在这 欢迎补充";
    }

    //用于统计 uidList eg: [1,2,3,4]
    public static function enabledEvent($date, $uidList)
    {
    	if (Predicates::isEmpty($date) || Predicates::isEmpty($uidList)) {
    	    return -1;
    	}
    	$connection = Yii::$app->db;
    	$sql = "select count(mp.id) as n, e.id as eid, uid from ".Us\TableName\MOMENT_PICTURE." as mp right join
	            (select id, uid from ".Us\TableName\EVENT." where create_time between '".$date."' and '".date("Y-m-d", strtotime("+1 day", strtotime($date)))."' and status=0 and
	            uid in (".self::makeStr($uidList).")) as e on e.id=mp.event_id and mp.create_time between '".$date."' and '".date("Y-m-d", strtotime("+1 day", strtotime($date)))."'
	            and mp.status=0 group by event_id, uid";
    	$command = $connection->createCommand($sql);
    	return $command->queryAll();
    }

    public static function makeStr($array)
    {
        if (Predicates::isEmpty($array)) {
            return $array;
        }
        $response = "";
        array_map(function($uid) use(&$response) {
            $response .= $uid.",";
        }, $array);
        $response = substr($response, 0, -1);
        return $response;
    }

    public static function eventPicture($date, $uidList)
    {
        if (Predicates::isEmpty($date) || Predicates::isEmpty($uidList)) {
            return -1;
        }
        $connection = Yii::$app->db;
        $sql = "select count(mp.id) as n, m.uid from ".Us\TableName\MOMENT_PICTURE." as mp right join 
                (select id,uid from ".Us\TableName\EVENT_MOMENT." as em where em.uid in (".self::makeStr($uidList).") and 
                em.status=0 and em.create_time between '".$date."' and '".date("Y-m-d", strtotime("+1 day", strtotime($date)))."') as m on m.id=mp.moment_id and 
                mp.status=0 and mp.create_time between '".$date."' and '".date("Y-m-d", strtotime("+1 day", strtotime($date)))."' group by mp.moment_id, uid";
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    public static function isEventMemberAndMyMoment($eventId, $loginUid, $momentId = 0, $commitPictureIds = "")
    {
        if (!self::isEventMember($eventId, $loginUid)){
            if(Predicates::isNotEmpty($commitPictureIds)) {
                //Protocol::ok(NULL, NULL, 'event is not found or not event user');
                return false;
            } else {
                Protocol::badRequest(NULL, NULL, 'event is not found or not event user');
            }
        }
        if (intval($momentId) > 0) {
            $momentInfo = Event::GetMomentInfoByEvent($eventId, $momentId);
            if (($momentInfo['uid'] != $loginUid)) {
                    Protocol::badRequest(NULL, NULL, 'event or moment is not found');
            } elseif (($momentInfo["status"] == Event::STATUS_MOMENT_DELETE)) {
                $key = "first_commit_picture_ids_{$eventId}_{$momentId}";
                yii::$app->redis->set($key, $commitPictureIds);
            } elseif (($momentInfo["status"] == Event::MOMENT_STATUS_NORMAL)) {
                $key = "first_commit_picture_ids_{$eventId}_{$momentId}";
                $pictureIds = yii::$app->redis->get($key);
                if ($pictureIds !== $commitPictureIds) {
                    Protocol::badRequest(NULL, NULL, 'event or moment is not found');
                }
           }
        }
        return true;
    }

    public static function isEventMember($eventId, $uid)
    {
        return (new Query())->from(self::$tableEvent . " as e")
            ->innerJoin(self::$tableEventUser . " as eu", "e.id = eu.event_id")
            ->where(["eu.event_id" => $eventId, "eu.member_uid" => $uid, "eu.is_deleted" => 0, "e.status" => [0, 2]])
            ->count() > 0;
    }
    
    public static function isEventMyMoment($eventId, $loginUid, $momentId, $commitPictureIds = "")
    {
        if (intval($momentId) > 0) {
            $momentInfo = Event::GetMomentInfoByEvent($eventId, $momentId);
            if (($momentInfo['uid'] != $loginUid)) {
                return false;
            } elseif (($momentInfo["status"] == Event::STATUS_MOMENT_DELETE)) {
                $key = "first_commit_picture_ids_{$eventId}_{$momentId}";
                yii::$app->redis->set($key, $commitPictureIds);
            } elseif (($momentInfo["status"] == Event::MOMENT_STATUS_NORMAL)) {
                $key = "first_commit_picture_ids_{$eventId}_{$momentId}";
                $pictureIds = yii::$app->redis->get($key);
                if ($pictureIds !== $commitPictureIds) {
                    return false;
                }
            }
        }
        return true;
    }
    
    public static function isNormalStatusEventAndMoment($eventId, $momentId)
    {
        return (new Query())->from(self::$tableEvent . " as e")
        ->innerJoin(self::$tableEventMoment . " as em", "e.id = em.event_id")
        ->where(["em.event_id" => $eventId, "em.id" => $momentId, "em.status" => 0, "e.status" => [0, 2]])
        ->count() > 0;
    }
    
    public static function isMomentCommentable($eventId, $momentId, $uid, $select = [], $sql = '')
    {
        if (!empty($sql)) {
            $sql = " and eu.is_deleted = 0 and eu.member_uid = " . $uid;
            $query = (new Query())->from(self::$tableEvent . " as e")
            ->innerJoin(self::$tableEventUser . " as eu", "e.id = eu.event_id")
            ->innerJoin(self::$tableEventMoment . " as em", "e.id = eu.event_id")
            ->where(["eu.event_id" => $eventId, "em.status" => 0, "em.id" => $momentId])
            ->andWhere('e.status != 1' . $sql);
            if (Predicates::isEmpty($select)) {
                return $query->count() > 0;
            } else {
                $select = array_reduce($select, function($carry, $item) { $carry .= "em.$item,"; return $carry; }, "");
                $select = substr($select, 0, strlen($select) - 1);
                return $query->select($select)->one();
            }
        } else {
            $query = new Query;
            $userTao = $query->select('tao_object_id as user_tao_object_id') ->from(Us\TableName\USER)
            ->where('status = 0 and uid = ' . $uid )->one();

            $query = new Query;
            $momentTao = $query->select('tao_object_id, uid') ->from(Us\TableName\EVENT_MOMENT)
            ->where('status = 0 and event_id = ' . $eventId . ' and id = ' . $momentId )->one();

            $query = new Query;
            $eventId = $query->select('id') ->from(Us\TableName\EVENT)
            ->where('status = 0 and id = ' . $eventId)->one();

            if ($eventId && $momentTao) {
                return array_merge($userTao, $momentTao);
            }
            return false;
        }
    
    }
    
    public static function isMomentLikable($eventId, $momentId, $uid, $select = [], $sql = '')
    {
        if (!empty($sql)) {
            $sql = " and eu.is_deleted = 0 and eu.member_uid = " . $uid;
            $query = (new Query())->from(self::$tableEventUser . " as eu")
            ->innerJoin(self::$tableEvent . " as e", "eu.event_id = e.id")
            ->innerJoin(self::$tableEventMoment . " as em", "eu.event_id = em.event_id")
            ->innerJoin(self::$tableUser . " as u", "eu.member_uid = u.uid")
            ->where(["eu.event_id" => $eventId, "em.status" => 0, "em.id" => $momentId])
            ->andWhere('e.status != 1' . $sql);
            if (Predicates::isEmpty($select)) {
                return $query->count() > 0;
            } else {
                $select = array_reduce($select, function($carry, $item) { $carry .= "em.$item,"; return $carry; }, "u.tao_object_id as user_tao_object_id,");
                $select = substr($select, 0, strlen($select) - 1);
                return $query->select($select)->one();
            }
        } else {
            $query = new Query;
            $userTao = $query->select('tao_object_id as user_tao_object_id') ->from(Us\TableName\USER)
            ->where('status = 0 and uid = ' . $uid )->one();
            
            $query = new Query;
            $momentTao = $query->select('tao_object_id, uid') ->from(Us\TableName\EVENT_MOMENT)
            ->where('status = 0 and event_id = ' . $eventId . ' and id = ' . $momentId )->one();
            
            $query = new Query;
            $eventId = $query->select('id') ->from(Us\TableName\EVENT)
            ->where('status = 0 and id = ' . $eventId)->one();
            
            if ($eventId && $momentTao) {
                return array_merge($userTao, $momentTao);
            }
            return false;
        }
    }

    public static function addEventObject($eventId, $gid, $uid, $type, $taoId=null)
    {
        $node = $taoId?$taoId:Tao::addObject('EVENT', "eid", $eventId);
        if ($node) {
            if (is_object($node)) {
                $connection = Yii::$app->db;
                $node = is_object($node)?$node->id:$node;
                $result = $connection->createCommand()->update(Us\TableName\EVENT, ['tao_object_id' => $node], ['id' => $eventId])->execute();
            }
            if ($gid) {
            	return GroupModel::addGroupAssociatEvent($gid, $node, $eventId, $uid, $type);
            }
        }
        return false;
    }

    public static function getEventList($eventList, $params=null)
    {
        $query = new Query;
        $query->select($params?$this->doFilterParams($params):"id, uid, name, cover_page, gid as pgid, start_time, create_time, description, tao_object_id")
            ->from(Us\TableName\EVENT)
            ->where(['status' => self::EVENT_STATUS_NORMAL])
            ->andWhere(['in', 'id', $eventList]);
        return $query->all();
    }

    public static function getAllEventList($eventList, $params=null)
    {
        $query = new Query;
        $query->select($params?$this->doFilterParams($params):"id, uid, name, cover_page, gid as pgid, start_time, create_time, description, tao_object_id, status")
            ->from(Us\TableName\EVENT)
            ->where(['in', 'status', [0, 2]])
            ->Where(['in', 'id', $eventList]);
        return $query->all();
    }

    private static function doFilterParams($params)
    {
        $response = "";
    	foreach ($params as $key) {
    	    $response .= $key.",";
    	}
    	return substr($response, 0, -1);
    }

    public static function getEventPic($eventList, $uid=null, $version = 0, $platform = 0)
    {
        $query = new Query;
        $query->select('id, start_time, name, cover_page, status, uid, create_time, invitation_code') ->from(Us\TableName\EVENT)
            ->where('status!='.self::EVENT_STATUS_DELETE)
            ->andWhere(['in', 'id', $eventList])
            ->orderBy('start_time desc, create_time desc');
        $event = $query->all();

        $query = new Query;
        $pic = $query->select('count(id) as pnum, event_id') ->from(Us\TableName\MOMENT_PICTURE)
            ->where('status!='. self::STATUS_PICTURE_DELETE)
            ->andWhere(['in', 'event_id', $eventList])
            ->groupBy('event_id')->all();

        if ($uid) {
            $query = new Query;
            $query->select('event_id, role') ->from(Us\TableName\EVENT_USER)
                ->where(['member_uid' => $uid, 'is_deleted' => self::STATUS_USER_NORMAL])
                ->andWhere(['in', 'event_id', $eventList]);
            $user = $query->all();
        }

        $response = [];
        $tmp = [];
        if ($event) {
            foreach ($event as $data) {
                $tmp['event'][$data['id']]['start_time'] = $data['start_time'];
                $tmp['event'][$data['id']]['create_time'] = $data['create_time'];
                $tmp['event'][$data['id']]['name'] = $data['name'];
                $tmp['event'][$data['id']]['event_id'] = $data['id'];
                $tmp['event'][$data['id']]['upload_count'] = 0;
                $tmp['event'][$data['id']]['cover_page'] = User::translationDefaultPicture('event/coverpage/'.$data['cover_page'], $version, $platform).'.jpg';
                $tmp['event'][$data['id']]['status'] = $data['status'];
                $tmp['event'][$data['id']]['invitation_code'] = $data['invitation_code'];
                $tmp['event'][$data['id']]['uid'] = $data['uid'];
                $tmp['event'][$data['id']]['role'] = 2;
            }
    
            foreach ($pic as $data) {
                $tmp['event'][$data['event_id']]['upload_count'] = $data['pnum'];
            }
            if ($uid && $user) {
            	foreach ($user as $data) {
            	    $tmp['event'][$data['event_id']]['role'] = $data['role'];
            	}
            }
            foreach ($tmp['event'] as $eid => $data) {
                $response['event'][] = $data;
            }
        }
        return $response;
    }

    public static function getEventPicNum($eventList)
    {
        if (!$eventList) {
        	return 0;
        }
        $connection = Yii::$app->db;
        $sql = "select count(mp.id) as num from ".Us\TableName\EVENT." inner join ".Us\TableName\MOMENT_PICTURE."
                 as mp on mp.event_id=".Us\TableName\EVENT.".id and ".Us\TableName\EVENT.".status!=1 and 
                 ".Us\TableName\EVENT.".id in (".implode(",", $eventList).") and mp.status!=1;";
        $command = $connection->createCommand($sql);
        $num = $command->queryAll();
        return $num[0]['num'];
    }

    public static function getEventTaoId($eventId)
    {
        $query = new Query;
        $query->select('tao_object_id') ->from(Us\TableName\EVENT)
            ->where(['id' => $eventId]);
        $node = $query->one();
        return $node['tao_object_id'];
    }

    public static function getEventListTaoId($eventList)
    {
        $query = new Query;
        $query->select('tao_object_id, id') ->from(Us\TableName\EVENT)
            ->where(['in', 'id', $eventList]);
        $node = $query->all();
        $response = [];
        if ($node) {
            foreach ($node as $data) {
                $response[$data['id']] = $data['tao_object_id'];
            }
        }
        return $response;
    }

    public static function JoinEventByUid($uid = 0)
    {
        $select = "event_id";
        $query = new Query;
        $data = $query
        ->select($select)
        ->from(self::$tableEventUser)
        ->leftJoin(self::$tableEvent, self::$tableEvent.".id = ".self::$tableEventUser.".event_id")
        ->where(['member_uid' => $uid])
        ->andWhere(self::$tableEventUser.".is_deleted !=".self::EVENT_STATUS_DELETE)
        ->andWhere(self::$tableEvent.".status !=" . self::STATUS_EVENT_DELETE)
        ->all();
        return $data;
    }
    
    public static function uidJoinGroup($loginUid)
    {
        
        $ownerGroup = GroupModel::getOwnerGroupListByUid($loginUid, 0, 0x7FFFFFFF);
        $joinGroup = GroupModel::getMemberGroupListByUid($loginUid, 0, 0x7FFFFFFF);
        $group = array_merge($ownerGroup, $joinGroup);
        $groupSelfGid = [];
        array_walk($group, function(&$value, $key) use (&$groupSelfGid) {
            $groupSelfGid[] = $value->to;
        });
            return $groupSelfGid;
    }

    /* 小组和点滴的关系：  type:1  target:system 区分故事 */
    public static function addObjectForDribs($eventId, $gid, $uid, $type, $taoId = null)
    {
        $node = $taoId?$taoId:Tao::addObject('EVENT', "eid", $eventId);
        if ($node) {
            if (is_object($node)) {
                $connection = Yii::$app->db;
                $node = is_object($node)?$node->id:$node;
                $result = $connection->createCommand()->update(Us\TableName\EVENT, ['tao_object_id' => $node], ['id' => $eventId])->execute();
            }
            Tao::addAssociation($gid, "OWNS", "OWNED_BY", $node, 'eid', $eventId, 'oper', 0, 'type', $type, 'target', 'system');
            return self::doTableInsert(self::$tableEventUser, ['event_id' => $eventId, 'member_uid' => $uid, 'role' => self::EVENT_ROLE_CREATE, 'invitation_code' => self::CreateInvitationCode(7, Event::$tableEventUser)]);
        }
        return false;
    }

    /* 点滴： 起始时间和结束时间都为1970 1 1 8:00 */
    public static function doAddDribs($loginUid, $title = null, $coverUrl = null, $data = null)
    {
        $eventInvitationCode = self::CreateInvitationCode(5, self::$tableEvent);
        return self::doTableInsert(self::$tableEvent, ['uid' => $loginUid, 'gid' => Push::createGroup(), 'name' => $title, 'cover_page' => isset($coverUrl) ? $coverUrl : "default", 'invitation_code' =>$eventInvitationCode, 'start_time' => Types::unix2SQLTimestamp(0), 'end_time' => Types::unix2SQLTimestamp(0), 'data' => isset($datas) ? $datas : ""], 1);
    }
}
?>
