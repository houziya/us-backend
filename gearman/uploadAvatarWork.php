<?php
define("APP_PATH", realpath(dirname(__FILE__) . '/../'));
require APP_PATH . '/conf/constants.php';
$worker= new GearmanWorker();
$worker->addServer(Us\Config\Gearman\HOSTNAME, Us\Config\Gearman\PORT);
$worker->addFunction('upload_avatar', 'uploadAvatar');

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
    $redis->connect(Us\Config\Redis\HOSTNAME, Us\Config\Redis\PORT);
    $redis->auth(Us\Config\Redis\AUTH);
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

function sendGetHttp($uid, $url)
{
    $requestUrl = Us\APP_URL.Us\Config\Gearman\AVATAR_URL.'uid='.$uid.'&avatar_url='.$url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $result = curl_exec($ch);
    curl_close($ch);
    echo $result;
}

function uploadAvatar()
{
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    try{
        $redis = initRedis();
        $value = $redis->lpop("avatar");
        if (!$value) {
        	return ;
        }
    //     $result = resetRedisHash("cache.avatar", $value, 1, 10);
        echo date("Y-m-d H:i:s")." ".$value."\n";
        $data = explode("__", $value);
        $uid = $data[0];
        $url = $data[1];
        if (empty($uid) || empty($url)) {
        	echo $value;
        	return ;
        }
        echo sendGetHttp($uid, $url);
    //     if (sendGetHttp($uid, $url)) {
    //         $res = $redis->hdel("cache.avatar", $value);
    //     }
    }
    catch(Execution $e){
    	echo json_encode($e);
    }
}

?>