<?php
use yii\db\Query;
/**
 *
 * 获取用户头像
 */
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try {
    if (count($argv) < 2) {
        $argv = [date("Y-m-d", strtotime("-1 day"))];
    } else {
        $argv = array_slice($argv, 1);
    }
    foreach($argv as $date) {
        UserInfo::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class UserInfo
{
	private static $avatar = 'default';
	 
    public static function execute($date)
    {
        Execution::withFallback(
            function () use ($date){
                $endDate = date("Y-m-d", strtotime("+1 day", strtotime($date)));
                $data = self::doUserInfoTotalDefaultAvatar();
                if ($data) {
                    echo $date.' UserInfo_data has done!';
                } else {
                    echo "Fail\n";
                }
            }
        );
    }

    private static function doUserInfoTotalDefaultAvatar()
    {
        $connection = Yii::$app->db;
        $sql = "select l.uid ,u.avatar,l.secret from user as u inner join user_login as l on u.uid = l.uid and type = 3 where u.avatar= '".self::$avatar."'";
        $command = $connection->createCommand($sql);
        $user = $command->queryAll();
		// var_dump($user);die;
        if (Predicates::isEmpty($user)) {
            return 0;
        }
		$accessToken = self:: doGetAccessToken();
		// var_dump($accessToken);die;
		// $list = self::doUserOpenId($accessToken);
		foreach (@$user as $data) {
			$playload[] = array('payload' => self :: doGetWeChatUserData($accessToken, $data['secret']), 'uid' => $data['uid']);
		}
		
		foreach (@$playload as $v) {
			if (isset($v['payload']['headimgurl'])) {
				$result[] = self :: gearmanUploadAvatar($v['uid'], $v['payload']['headimgurl']);
			}
		}
		// $result = self :: gearmanUploadAvatar(1489, 'cc');
		// var_dump($result);die;
		if (isset($result)) {
			return $result;
		} else {
			return array();
		}
    }
	
	private static function doUserOpenId($accessToken)
	{
		$url = Us\Config\WECHAT_OPENID.$accessToken;
		$payload = Http::sendGet($url);
		if (empty($payload)) {
			return false;
		}
		return $payload;
	}
	
	private static function doGetWeChatUserData($accessToken, $openId)
    {
    	$url = Us\Config\WECHAT_USER.$accessToken."&openid=".$openId."&lang=zh_CN";
		// var_dump($url);die;
    	$payload = Http::sendGet($url);
    	if (isset($payload['errcode'])) {
    	    return false;
    	}
    	return $payload;
    }
	
	private static function doGetAccessToken()
    {
    	$token = Yii::$app->redis->get(Us\User\WECHAT_TOKEN);
		// var_dump($token);die;
		$token = '';
    	if (!$token) {
    	    $token = self :: doSetUniqueAccessToken();
    	}
		// var_dump($token);die;
    	return $token;
    }
	
	private static function doSetUniqueAccessToken()
    {
        $token = self :: doGetUniqueAccessToken();
        self :: dostoreRedisString(Us\User\WECHAT_TOKEN, $token, 10);
        return $token;
    }
	
	private static function doGetUniqueAccessToken()
    {
        $url = Us\Config\WECHAT_UNIQUE_TOKEN."appid=".Us\Config\WECHAT_APPID."&secret=".Us\Config\WECHAT_SECRET;
        $payload = Http::sendGet($url);
        if (isset($payload['errcode'])) {
            return false;
        }
        return $payload['access_token'];
    }
	
	private static function doStoreRedisString($key, $value, $times)
    {
    	for ($i=0; $i<$times; $i++) {
    		$result = Yii::$app->redis->set($key, $value);
    		if ($result) {
    			return true;
    		}
    	}
    	return false;
    }
	
	private static function gearmanUploadAvatar($uid, $avatar_url)
    {
		// $url = 'http://wx.qlogo.cn/mmopen/txibc7M8iar79QWe6Jksv7pFjOEye0U6zbNMVONVia276jMZjyFia4Yfm89aAfibMMVFB8xGCWhychUL80wcwZFOytm0kZBBpwUda/0';
		$avatarData = CosFile::uploadFile('', $uid, 0, 0, 0, 0, '', '', $avatar_url);
        $result = self :: doUpdateThirdAvatarFromGearman($uid, $avatarData['subUrlName']);
        if ($result) {
            $res = Push :: pushUserAvatar($uid, $avatarData['subUrl']);
        }
		$response = ['result' => $result, 'uid'=>$uid, 'avatar' => $avatarData, 'push' => @$res];
        return $response;
    }
	
	private static function doUpdateThirdAvatarFromGearman($uid, $avatar)
    {
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->update(Us\TableName\USER, ['avatar' => $avatar], [
				'uid' => $uid,
                'avatar' => 'default',
                'status' => Us\User\STATUS_NORMAL
        ])->execute();
        return $res;
    }
	
}
?>
