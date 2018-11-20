<?php
use Yaf\Controller_Abstract;
use yii\db\Query;

class UserModel
{
    const PARAM_EMPTY = -1;     //参数为空
    const PARAM_INVALID = -2;     //非法请求
    const PATH_AVATAR = "profile/avatar/";
    const SUFFIX_AVATAR = ".jpg";

    private static $userInfo = [];

    public static function getUserAvatar($uid)
    {
    	if (Predicates::isEmpty($uid)) {
    		return self::PARAM_EMPTY;
    	}
    	return self::PATH_AVATAR.self::doQueryUserByUid($uid, 'avatar').self::SUFFIX_AVATAR;
    }

    public static function getUidByNickname($nickname)
    {
        if (Predicates::isEmpty($nickname)) {
            return self::PARAM_EMPTY;
        }
        return self::doQueryUid('nickname', $nickname);
    }

    public static function getUserTaoId($uid)
    {
        if (Predicates::isEmpty($uid)) {
            return self::PARAM_EMPTY;
        }
        return self::doQueryUserByUid($uid, 'tao_object_id');
    }

    private static function doQueryUid($key, $value)
    {
        if (Predicates::isEmpty($key)) {
            return self::PARAM_EMPTY;
        }
        if ($key==='salt') {
            return self::PARAM_INVALID;
        }
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER) ->where([$key => $value, 'status' => Us\User\STATUS_NORMAL]);
        $user = $query->one();
        return Predicates::isEmpty($user)?false:$user['uid'];
    }

    public static function getUserNickname($uid)
    {
        if (Predicates::isEmpty($uid)) {
            return self::PARAM_EMPTY;
        }
        return self::doQueryUserByUid($uid, 'nickname');
    }

    public static function getUserGender($uid)
    {
        if (Predicates::isEmpty($uid)) {
            return self::PARAM_EMPTY;
        }
        return self::doQueryUserByUid($uid, 'gender');
    }

    public static function getUserRegType($uid)
    {
        if (Predicates::isEmpty($uid)) {
            return self::PARAM_EMPTY;
        }
        $userLoginId = self::doQueryUserByUid($uid, 'user_login_id');
        return Predicates::isNotEmpty($userLoginId)?self::doExtractData($userLoginId, '@', 0):false;
    }

    public static function getUserRegTime($uid)
    {
        if (Predicates::isEmpty($uid)) {
            return self::PARAM_EMPTY;
        }
        return self::doQueryUserByUid($uid, 'reg_time');
    }

    public static function getUserSomeData($uid, $param=null)
    {
    	if (Predicates::isEmpty($uid)) {
    	    return self::PARAM_EMPTY;
    	}
    	if (Predicates::isNotEmpty(@self::$userInfo[$uid])) {
    	    return $param?self::doFilterUserData(self::$userInfo[$uid], $param):self::$userInfo[$uid];
    	}
    	$user = self::doQueryUserSomeData($uid, $param);
    	self::$userInfo[$uid] = $user;
    	return $param?self::doFilterUserData($user, $param):$user;
    }

    private static function doFilterUserData($source, $params)
    {
        if (Predicates::isEmpty($source) || Predicates::isEmpty($params)) {
            return self::PARAM_EMPTY;
        }
        $response = [];
        foreach ($params as $key) {
            if (Predicates::equals($key, 'salt') || Predicates::equals($key, 'status')) {
                continue;
            }
            if (Predicates::equals($key, 'user_login_id')) {
                $response['type'] = $source['type'];
                continue;
            }
            $response[$key] = $source[$key];
        }
        return $response;
    }

    private static function doQueryUserByUid($uid, $key)
    {
        if (Predicates::isEmpty($uid) || Predicates::isEmpty($key)) {
        	return self::PARAM_EMPTY;
        }
        if ($key==='salt') {
            return self::PARAM_INVALID;
        }
        $query = new Query;
        $query->select($key) ->from(Us\TableName\USER) ->where(['uid' => $uid, 'status' => Us\User\STATUS_NORMAL]);
        $user = $query->one();
        return Predicates::isEmpty($user)?false:$user[$key];
    }

    private static function doQueryUserSomeData($uid, $param)
    {
        if (Predicates::isEmpty($uid)) {
            return self::PARAM_EMPTY;
        }
        $query = new Query;
        $query->select($param?self::doSerializeParamData($param):'uid, avatar, gender, user_login_id, reg_time') ->from(Us\TableName\USER) ->where(['uid' => $uid, 'status' => Us\User\STATUS_NORMAL]);
        $user = $query->one();
        if ($user) {
            if (!$param || in_array("user_login_id", $param)) {
        	    $user['type'] = self::doExtractData($user['user_login_id'], '@', 0);
        		unset($user['user_login_id']);
        	}
        	if (!$param || in_array("avatar", $param)) {
        	    $user['avatar'] = !Predicates::equals($user['avatar'], 'default')?self::PATH_AVATAR.$user['avatar'].self::SUFFIX_AVATAR:Us\User\DEFAULT_AVATAR;
        	}
        }
        return Predicates::isEmpty($user)?false:$user;
    }

    private static function doSerializeParamData($param)
    {
    	if (Predicates::isEmpty($param)) {
    	    return self::PARAM_EMPTY;
    	}
    	$str = "";
    	foreach ($param as $key) {
    	    if (Predicates::equals($key, 'salt')) {
    	    	continue;
    	    }
    	    if (Predicates::equals($key, 'type')) {
    	        $str .= "type,";
    	        continue;
    	    }
    	    $str .= $key.",";
    	}
    	return substr($str, 0, -1);
    }

    public static function getUserListData($uidList, $key=null)
    {
        if (Predicates::isEmpty($uidList)) {
            return self::PARAM_EMPTY;
        }
        $response = [];
        $list = [];
        $user = [];
        if (Predicates::isNotEmpty(self::$userInfo)) {
            foreach ($uidList as $uid) {
            	if (Predicates::isNotEmpty(@self::$userInfo[$uid])) {
            	    $response[$uid] = $key?self::doFilterUserData(self::$userInfo[$uid], $key):self::$userInfo[$uid];
            	} else {
            		$list[] = $uid;
            	}
            }
        } else {
            $user = self::doQueryUserListInfo($uidList);
        }
        if (Predicates::isNotEmpty($list)) {
            $user = self::doQueryUserListInfo($list);
        }
        if (Predicates::isNotEmpty($user)) {
            foreach ($user as $data) {
                $uid = $data['uid'];
                self::$userInfo[$uid] = $data;
                $response[$uid] = $key?self::doFilterUserData($data, $key):$data;
            }
        }
        return $response;
    }

    private static function doQueryUserListInfo($uidList, $key=null)
    {
        if (Predicates::isEmpty($uidList)) {
            return self::PARAM_EMPTY;
        }
        return $key?self::doQueryUserListWithKey($uidList, $key):self::doQueryUserListAllInfo($uidList);
    }

    private static function doExtractData($source, $char, $target=null)
    {
    	if (Predicates::isEmpty($source)) {
    	    return self::PARAM_EMPTY;
    	}
    	$tmpArray = explode($char, $source);
    	return Predicates::isNotEmpty($target)?$tmpArray[$target]:$tmpArray;
    }

    private static function doQueryUserListWithKey($uidList, $colunm)
    {
        if (Predicates::isEmpty($uidList) || Predicates::isEmpty($colunm)) {
            return self::PARAM_EMPTY;
        }
        $query = new Query;
        $query->select(self::doGetSelectStr($colunm)) ->from(Us\TableName\USER)
            ->where(['in', 'uid', $uidList, ['and', 'status' => Us\User\STATUS_NORMAL]]);
        $user = $query->all();
        if (!$user) {
            return "";
        }
        if (in_array("avatar", $colunm)) {
            foreach ($user as $key => $value) {
                $user[$key]['avatar'] = $value['avatar']?self::PATH_AVATAR.$value['avatar'].self::SUFFIX_AVATAR:Us\User\DEFAULT_AVATAR;
            }
        }
        if (in_array("user_login_id", $colunm)) {
            foreach ($user as $key => $value) {
                $user[$key]['type'] = $value['user_login_id']?self::doExtractData($value['login_uid_id'], '@', 0):"";
                unset($user[$key]['user_login_id']);
            }
        }
        return $user;
    }

    private static function doQueryUserListAllInfo($uidList)
    {
        if (Predicates::isEmpty($uidList)) {
            return self::PARAM_EMPTY;
        }
        $query = new Query;
        $query->select('uid, nickname, avatar, gender, reg_time, user_login_id as r') ->from(Us\TableName\USER)
            ->where(['in', 'uid', $uidList, ['and', 'status' => Us\User\STATUS_NORMAL]]);
        $user = $query->all();
        if (!$user) {
            return "";
        }
        foreach ($user as $key => $value) {
            $user[$key]['avatar'] = $value['avatar']?self::PATH_AVATAR.$value['avatar'].self::SUFFIX_AVATAR:Us\User\DEFAULT_AVATAR;
            $user[$key]['type'] = $value['r']?self::doExtractData($value['r'], '@', 0):"";
            unset($user[$key]['r']);
        }
        return $user;
    }

    public static function updateUserProfile($data, $param)
    {
    	if (Predicates::isEmpty($data) || Predicates::isEmpty($param)) {
    		return self::PARAM_EMPTY;
    	}
    	return self::updateUserData(self::doSerializeKVParamData($data), self::doSerializeKVParamData($param));
    }

    private static function updateUserData($param, $condition)
    {
        if (Predicates::isEmpty($condition)) {
            return self::PARAM_EMPTY;
        }
        $connection = Yii::$app->db;
        return $connection->createCommand()->update(Us\TableName\USER, $param, $condition)->execute();
    }

    private static function doSerializeKVParamData($param)
    {
        if (Predicates::isEmpty($param)) {
            return self::PARAM_EMPTY;
        }
        $str = "";
        foreach ($param as $key => $value) {
            if (Predicates::equals($key, 'salt') || Predicates::equals($key, 'login_user_id')) {
            	continue;
            }
            $str .= $key."='".trim($value)."' and ";
        }
        return substr($str, 0, -4);
    }

    private static function doGetSelectStr($key)
    {
        if (Predicates::isEmpty($key)) {
            return -1;
        }
        $select = "";
        foreach ($key as $column) {
            if (Predicates::equals($column, 'salt') || Predicates::equals($column, 'status')) {
                continue;
            }
            if (Predicates::equals($column, 'type')) {
                $select .= "user_login_id,";
                continue;
            }
            $select .= $column.",";
        }
        return substr($select, 0, -1);
    }

    public static function newUserList($statDate)
    {
        if (Predicates::isEmpty($statDate)) {
            return -1;
        }
        $beginDate = $statDate;
        $endDate = date("Y-m-d", strtotime("+1 day", strtotime($statDate)));
        return self::doQuerySomeTimeRegUser($beginDate, $endDate);
    }

    private static function doQuerySomeTimeRegUser($beginDate, $endDate=null)
    {
        if (Predicates::isEmpty($beginDate)) {
            return -1;
        }
        if (Predicates::isEmpty($beginDate)) {
            $endDate = $beginDate;
        }
        $query = new Query;
        $query->select(Us\TableName\USER.'.uid as u, gender as g, platform as p') ->from(Us\TableName\USER)
            ->innerJoin(Us\TableName\USER_DEVICE, Us\TableName\USER.".uid=".Us\TableName\USER_DEVICE.".uid")
            ->where(['between', 'reg_time', $beginDate, $endDate, ['and', 'status' => Us\User\STATUS_NORMAL]]);
        $user = $query->all();
        if (!$user) {
            return 0;
        }
        $response = [];
        foreach ($user as $data) {
            $response['user'][] = $data['u'];
            $response['hash'][$data['u']]['p'] = $data['p'];
            $response['hash'][$data['u']]['g'] = $data['g'];
            $response['hash'][$data['u']]['u'] = $data['u'];
        }
        return $response;
    }

    public static function getUsersDeviceInfo($userList)
    {
        if (Predicates::isEmpty($userList)) {
            return -1;
        }
        return self::doQueryUserDeviceData($userList);
    }

    private static function doQueryUserDeviceData($userList)
    {
        if (Predicates::isEmpty($userList)) {
            return -1;
        }
        $query = new Query;
        $query->select(Us\TableName\USER.'.uid as u, gender as g, platform as p') ->from(Us\TableName\USER)
            ->innerJoin(Us\TableName\USER_DEVICE, Us\TableName\USER.".uid=".Us\TableName\USER_DEVICE.".uid")
            ->where(['in', Us\TableName\USER.'.uid', $userList, ['and', 'status' => Us\User\STATUS_NORMAL]]);
        $user = $query->all();
        if (!$user) {
            return 0;
        }
        $response = [];
        foreach ($user as $data) {
            $response['hash'][$data['u']]['p'] = $data['p'];
            $response['hash'][$data['u']]['g'] = $data['g'];
            $response['hash'][$data['u']]['u'] = $data['u'];
        }
        return $response;
    }

    public static function changeDefaultAvatar($uid, $avatar)
    {
        if (Predicates::isEmpty($uid) || Predicates::isEmpty($avatar)) {
            return -1;
        }
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->update(Us\TableName\USER, ['avatar' => $avatar], [
            'uid' => $uid,
            'avatar' => "default",
            'status' => Us\User\STATUS_NORMAL
        ])->execute();
        return $res;
    }
}
