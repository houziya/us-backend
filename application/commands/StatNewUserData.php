<?php
use yii\db\Query;
/**
 * 
 * 新增用户相关数据统计  statNewUserAction
 */
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try{
    StatNewUserData::statNewUser();
}catch(Exception $e){
    var_dump($e->getMessage());
}

class StatNewUserData
{
   public static function statNewUser()
   {
       $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            $beginDate = date("Y-m-d", strtotime("-1 day", time()));
            //查询新增注册用户
            $newUser = self::doGetNewUserData($beginDate, date("Y-m-d"));
            if (empty($newUser)) {
                echo "no new Users!";
                return false;
            }
            //讲数据存入到db中
        	if (self::doStoreNewUserToDB($beginDate, $newUser)) {
        	    $commit = true;
        	    echo $beginDate.' new_user has done!';
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

    public static function doQueryNewUserData($beginDate)
    {
        $query = new Query;
        $query->select('user.uid as u, user.user_login_id as t, user.gender as g, user_device.platform as p')
              ->from(Us\TableName\USER)
              ->innerJoin('user_device', 'user.uid=user_device.uid')
              ->where(['between', 'reg_time', $beginDate, date("Y-m-d", strtotime("+1 day", strtotime($beginDate))), ['and', 'status' => Us\User\STATUS_NORMAL]])
              ->orderBy(['user.uid' => SORT_ASC]);
        return $query->all();
    }

    public static function doSerializeNewUserData($newUser)
    {
        if (empty($newUser)) {
        	return 0;
        }
        //初始化response
        $response = self::doInitNewUserResponse();
    	foreach ($newUser as $data) {
    	    //统计类型数据
    	    $type = explode("@", $data['t']);
    	    $typeCode = $type[0];
    	    $response['t'][self::doSerializeNewUserTypeData($typeCode)]++;
    	    //统计性别数据
    	    $response['g'][self::doSerializeNewUserGenderData($data['g'])]++;
    	    //统计渠道数据
    	    $response['p'][self::doSerializeNewUserPlatformData($data['p'])]++;
    	    $response['u'][$data['u']] = 1;
    	}
	    return $response;
    }

    public static function doInitNewUserResponse()
    {
        $response = [
            't' => [
                Us\User\REGISTER_TYPE_PHONE => 0, Us\User\REGISTER_TYPE_QQ => 0,
                Us\User\REGISTER_TYPE_SINA => 0, Us\User\REGISTER_TYPE_WECHAT => 0
            ],
            'g' => [Us\User\REGISTER_GENDER_FEMALE => 0, Us\User\REGISTER_GENDER_MALE => 0],
            'p' => [
                Us\User\REGISTER_PLATFORM_IOS => 0, Us\User\REGISTER_PLATFORM_ANDROID => 0,
                Us\User\REGISTER_PLATFORM_H5_IOS => 0, Us\User\REGISTER_PLATFORM_H5_ANDROID => 0,
                Us\User\REGISTER_PLATFORM_H5_OTHERS => 0
            ],
            'u' => [],
        ];
        return $response;
    }

    public static function doSerializeNewUserTypeData($type)
    {
        switch ($type) {
            case Us\User\REGISTER_TYPE_PHONE:
                return Us\User\REGISTER_TYPE_PHONE;
                break;
            case Us\User\REGISTER_TYPE_QQ:
                return Us\User\REGISTER_TYPE_QQ;
                break;
            case Us\User\REGISTER_TYPE_SINA:
                return Us\User\REGISTER_TYPE_SINA;
                break;
            case Us\User\REGISTER_TYPE_WECHAT:
                return Us\User\REGISTER_TYPE_WECHAT;
                break;
            default:
                throw new InvalidArgumentException('Invalid registration type '. $type);
        }
        return 0;
    }

    public static function doSerializeNewUserGenderData($gender)
    {
        switch ($gender) {
        	case Us\User\REGISTER_GENDER_FEMALE:
        	    return Us\User\REGISTER_GENDER_FEMALE;
        	    break;
        	case Us\User\REGISTER_GENDER_MALE:
        	    return Us\User\REGISTER_GENDER_MALE;
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $gender);
        }
        return 0;
    }

    public static function doSerializeNewUserPlatformData($platform)
    {
        switch ($platform) {
        	case Us\User\REGISTER_PLATFORM_IOS:
        	    return Us\User\REGISTER_PLATFORM_IOS;
        	    break;
        	case Us\User\REGISTER_PLATFORM_ANDROID:
        	    return Us\User\REGISTER_PLATFORM_ANDROID;
        	    break;
        	case Us\User\REGISTER_PLATFORM_H5_IOS:
        	    return Us\User\REGISTER_PLATFORM_H5_IOS;
        	    break;
        	case Us\User\REGISTER_PLATFORM_H5_ANDROID:
        	    return Us\User\REGISTER_PLATFORM_H5_ANDROID;
        	    break;
    	    case Us\User\REGISTER_PLATFORM_H5_OTHERS:
    	        return Us\User\REGISTER_PLATFORM_H5_OTHERS;
    	        break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $platform);
        }
        return 0;
    }

    public static function doGetNewUserData($beginDate)
    {
    	$newUser = self::doQueryNewUserData($beginDate);
    	return self::doSerializeNewUserData($newUser);
    }

    public static function reHmsetData($key, $data, $times, $expire)
    {
    	if (empty($key) || empty($data) || empty($times) || empty($expire)) {
    		return false;
    	}
    	for ($i=0; $i<$times; $i++) {
    	    $result = Yii::$app->redis->hMset($key, $data);
    		if ($result) {
    		    Yii::$app->redis->expire($key, $expire);
    			return true;
    		}
    	}
    	return false;
    }

    public static function doStoreNewUserToDB($statData, $newUser)
    {
        if (empty($newUser)) {
            return false;
        }
        unset($newUser['u']);
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
                'stat_date' => date("Ymd", strtotime($statData)),
                'create_time' => date("Y-m-d H:i:s"),
                'type' => 0,
                'data' => json_encode($newUser),
        ])->execute();
        return $res;
    }
}
?>