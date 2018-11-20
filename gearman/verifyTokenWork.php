<?php 
$worker= new GearmanWorker();
$worker->addServer('10.104.35.0', 4730);
$worker->addFunction('verify_token', 'verifyToken');

while (($ret = $worker->work()) || $worker->returnCode() == GEARMAN_IO_WAIT || $worker->returnCode() == GEARMAN_NO_JOBS)
{
	echo date("Y-m-d H:i:s")."return_code: " . $worker->returnCode() . "\n";
	if ($worker->returnCode() == GEARMAN_SUCCESS) {
    		continue;
	}
	if (!$worker->wait()){
		echo date("Y-m-d H:i:s")."return_code: " . $worker->returnCode() . "\n";
		if ($worker->returnCode() == GEARMAN_NO_ACTIVE_FDS) {
    		sleep(5);
    		continue;
		}
		break;
	}
}

function initRedis()
{
    $redis = new Redis();
    $redis->connect("10.66.83.117", 6379);
    $redis->auth('5821ecac-9143-4b29-8d98-f95177733bb8:88788D2E');
    return $redis;
}

function resetRedisHash($key, $field, $value, $times)
{
    for( $i=0; $i<$times; $i++ ){
        $result = $redis->hset($key, $field, $value);
        if ($result) {
            return true;
        }
    }
    return false;
}

function sendGetHttp($url)
{
    $requestUrl = $url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function sendPostHttp($url, $data)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $return = curl_exec($ch);
    curl_close($ch);
    return $return;
}

function doDeleteUserSession($uid, $type)
{
    $redis = initRedis();
    if ($redis->del("us_sessid_".$uid)) {
    	return json_encode(['verify' => 0, 'uid' => $uid]);
    }
    error_log(print_r(['uid' => $uid, 'verify' => 0, 'result' => 0, 'operation' => 'verify', 'type' => $type], true) . "\n", 3, "/usr/local/nginx/us/gearman/redis.log");
}

function doVerifyQQ($openId, $accessToken, $uid)
{
	$url = "https://graph.qq.com/oauth2.0/me?access_token=".$accessToken;
	$result = sendGetHttp($url);
	return (strpos($result, $openId)===false)?doDeleteUserSession($uid, 1):json_encode(['verify' => true, 'uid' => $uid]);
}

function doVerifySina($openId, $accessToken, $uid)
{
    $url = "https://api.weibo.com/oauth2/get_token_info";
    $data = ["access_token" => $accessToken];
    $result = sendPostHttp($url, $data);
    return (isset($result['uid']) && $result['uid']==$openId)?json_encode(['verify' => true, 'uid' => $uid]):doDeleteUserSession($uid, 2);
}

function doVerifyWeChat($openId, $accessToken, $uid)
{
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxf89011b9f95275bb&secret=12211ad00369cf2d0e06ae9f76b1b2f7&code=".$accessToken."&grant_type=authorization_code";
    $result = sendGetHttp($url);
    return (isset($result['unionid']) && $result['unionid']==$openId)?json_encode(['verify' => true, 'uid' => $uid]):doDeleteUserSession($uid, 3);
}

function verify($type, $openId, $accessToken, $uid)
{
	if (empty($type) || empty($openId) || empty($accessToken)) {
		return false;
	}
	switch ($type) {
		case 1:
		    return doVerifyQQ($openId, $accessToken, $uid);
		    break;
		case 2:
		    return doVerifySina($openId, $accessToken, $uid);
		    break;
		case 3:
		    return doVerifyWeChat($openId, $accessToken, $uid);
		    break;
		default:
		    throw new InvalidArgumentException('Invalid registration type '. $type);
	}
}
function verifyToken()
{
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    try {
        $redis = initRedis();
        $value = $redis->lpop("token");
        if (!$value) {
            return ;
        }
        //    $result = resetRedisHash("cache.token", $field, $value, $times);
        $data = explode("__", $value);
        $uid = $data[0];
        $type = $data[1];
        $openId = $data[2];
        $accessToken = $data[3];
        if (empty($uid) || empty($type) || empty($openId) || empty($accessToken)) {
        	echo $value;
        	return ;
        }
        echo verify($type, $openId, $accessToken, $uid)."\n";
        //     if (sendGetHttp($uid, $url)) {
        //         $res = $redis->hdel("cache.token", $field);
        //     }
    }
    catch (Excution $e) {
    	echo json_encode($e);
    }
}
?>