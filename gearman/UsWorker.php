<?php
require_once(dirname(__FILE__) . "/Worker.php");

function withRedis($entry, $host = NULL, $port = NULL, $auth = NULL)
{
	try {
		$redis = Worker::redis($host, $port, $auth);
		$entry($redis);
	}
	catch (Exception $e) {
	    $redis->close();
	}
}

function uploadAvatar()
{
	withRedis(function ($redis) {
	    try{
	        $value = $redis->lpop("avatar");
	        if (!$value) {
	            echo "list is empty!\n";
	            return ;
	        }
	        echo date("Y-m-d H:i:s")." ".$value."\n";
	        $data = explode("__", $value);
	        $uid = $data[0];
	        $url = $data[1];
	        if (empty($uid) || empty($url)) {
	            echo $value;
	            return ;
	        }
	        echo Worker::sendGetRequest(Us\APP_URL.Us\Config\Gearman\AVATAR_URL.'uid='.$uid.'&avatar_url='.$url);
	    }
	    catch(Execution $e){
	        echo json_encode($e);
	    }
	   }
	);
}

function uploadAvatarEntry($job)
{
    uploadAvatar();
}
Worker::create()->subscribe('upload_avatar', 'uploadAvatarEntry', 'uploadAvatar')->loop();