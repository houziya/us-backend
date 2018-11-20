<?php 
use Yaf\Dispatcher;
use Yaf\Registry;
use yii\db\Query;

class User
{
    //获取单个用户的nickname
	public static function getUserNickname($uid)
	{
	    if (empty($uid)) {
	        return -1;
	    }
	    $user = self::doQueryUserInfo($uid, ['nickname']);
	    return isset($user['nickname']) ? $user['nickname'] : "";
	}

	//获取单个用户的avatar
	public static function getUserAvatar($uid)
	{
	    if (empty($uid)) {
	        return -1;
	    }
	    $user = self::doQueryUserInfo($uid, ['avatar']);
	    return $user['avatar']?$user['avatar']:Us\User\DEFAULT_AVATAR;
	}

	//获取单个用户的gender
	public static function getUserGender($uid)
	{
	    if (empty($uid)) {
	        return -1;
	    }
	    $user = self::doQueryUserInfo($uid, ['gender']);
	    return $user['gender'];
	}

	//获取单个用户的reg_time
	public static function getUserRegTime($uid)
	{
	    if (empty($uid)) {
	        return -1;
	    }
	    $user = self::doQueryUserInfo($uid, ['reg_time']);
	    return $user['reg_time'];
	}

	//获取单个用户的一些信息($key是数组) eg:$key=['avatar','nickname']
	public static function getUserInfo($uid, $key=null)
	{
	    if (empty($uid)) {
	        return -1;
	    }
	    return self::doQueryUserInfo($uid, $key);
	}

	//获取一些用户的一些信息($uidList,$key是数组) eg:$uidList=[1, 2];$key=['avatar','nickname']
	public static function getUserListInfo($uidList, $key=null)
	{
		if (empty($uidList)) {
	        return -1;
	    }
	    return self::doQueryUserListInfo($uidList, $key);
	}

	private static function doQueryUserListInfo($uidList, $key)
	{
		if (empty($uidList)) {
			return -1;
		}
		return $key?self::doQueryUserListWithKey($uidList, $key):self::doQueryUserListAllInfo($uidList);
	}

	private static function doQueryUserListWithKey($uidList, $colunm)
	{
	    if (empty($uidList) || empty($colunm)) {
	        return -1;
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
	            $user[$key]['avatar'] = $value['avatar']?"profile/avatar/".$value['avatar'].".jpg":Us\User\DEFAULT_AVATAR;
	        }
	    }
	    if (in_array("user_login_id", $colunm)) {
	        foreach ($user as $key => $value) {
    	        if ($value['user_login_id']) {
                	$regData = explode("@", $value['user_login_id']);
                	$user[$key]['type'] = $regData[0];
                }
                else {
                    $user[$key]['type'] = '';    //注册类型
                }
                unset($user[$key]['user_login_id']);
	        }
	    }
	    return $user;
	}

	private static function doQueryUserListAllInfo($uidList)
	{
	    if (empty($uidList)) {
	        return -1;
	    }
	    $query = new Query;
	    $query->select('uid, nickname, avatar, gender, reg_time, user_login_id as r') ->from(Us\TableName\USER)
	       ->where(['in', 'uid', $uidList, ['and', 'status' => Us\User\STATUS_NORMAL]]);
	    $user = $query->all();
	    if (!$user) {
	        return "";
	    }
        foreach ($user as $key => $value) {
            $user[$key]['avatar'] = $value['avatar']?"profile/avatar/".$value['avatar'].".jpg":Us\User\DEFAULT_AVATAR;
            if ($value['r']) {
            	$regData = explode("@", $value['r']);
            	$user[$key]['type'] = $regData[0];
            }
            else {
                $user[$key]['type'] = '';    //注册类型
            }
            unset($user[$key]['r']);
	    }
	    return $user;
	}

	private static function doQueryUserInfo($uid, $key=null)
	{
		if (empty($uid)) {
			return -1;
		}
		return $key?self::doQueryUserWithKey($uid, $key):self::doQueryUserAllInfo($uid);
	}

	private static function doQueryUserWithKey($uid, $colunm)
	{
		if (empty($uid) || empty($colunm)) {
			return -1;
		}
		$query = new Query;
		$query->select(self::doGetSelectStr($colunm)) ->from(Us\TableName\USER) ->where(['uid' => $uid, 'status' => Us\User\STATUS_NORMAL]);
		$user = $query->one();
		if (!$user) {
		    return "";
		}
		if (isset($user['avatar'])) {
		    $user['avatar'] = $user['avatar']?"profile/avatar/".$user['avatar'].".jpg":Us\User\DEFAULT_AVATAR;
		}
		return $user;
	}

	private static function doGetSelectStr($key)
	{
	    if (empty($key)) {
	    	return -1;
	    }
	    $select = "";
	    foreach ($key as $column) {
	        if ($column=='salt') {
	        	continue;
	        }
	        $select .= $column.",";
	    }
	    return substr($select, 0, -1);
	}

	private static function doQueryUserAllInfo($uid)
	{
	    if (empty($uid)) {
	        return -1;
	    }
	    $query = new Query;
	    $query->select('uid, nickname, avatar, gender, reg_time, user_login_id') ->from(Us\TableName\USER) ->where(['uid' => $uid, 'status' => Us\User\STATUS_NORMAL]);
	    $user = $query->one();
	    if (!$user) {
	        return "";
	    }
	    $user['avatar'] = $user['avatar']?"profile/avatar/".$user['avatar'].".jpg":Us\User\DEFAULT_AVATAR;
	    return $user;
	}
	
    public static function translationDefaultPicture ($url, $version = 0, $platform = 0)
    {
        if (($version < 14 && $platform == 0)  || ($version < 7 && $platform == 1)) {
            return $url;
        }
        $item = explode('/', $url);
        switch ($item[0].'/'.$item[1]) {
            case 'profile/coverpage':
                if ($item[2] == 'default') {
                    $item[2] = Us\Config\FORWARD_PROFILE_COVERPAGE_PICTURE;
                }
                return implode('/', $item);
                break;
            case 'event/coverpage':
                if ($item[2] == 'default') {
                    $item[2] = Us\Config\FORWARD_EVENT_COVERPAGE_PICTURE;
                }
                return implode('/', $item);
                break;
            case 'group/coverpage':
                if ($item[2] == 'default') {
                    $item[2] = Us\Config\FORWARD_GROUP_COVERPAGE_PICTURE;
                }
                if ($item[2] == 'friend') {
                    $item[2] = Us\Config\FORWARD_GROUP_COVERPAGE_FRIEND;
                }
                if ($item[2] == 'family') {
                    $item[2] = Us\Config\FORWARD_GROUP_COVERPAGE_FAMILY;
                }
                return implode('/', $item);
                break;
            default:
                return $url;
        }
    }
}
?>