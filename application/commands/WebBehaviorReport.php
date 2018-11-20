<?php
/**
 *
 * @explain 活动分享，邀请，添加 计划任务接口   line:516 methodName:eventStatData
 * 数据来源接口 line:44 methodName:uploadLog
 */

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
require_once APP_PATH . '/application/commands/LogParser.php';

try{
    WebBehaviorReport::execute();
}catch (Exception $e) {
    var_dump($e->getMessage());
}

class WebBehaviorReport
{
    public static $action = ['/Us/Event/commit', '/Us/Event/invite', '/Us/Event/redirection'];
    public static $statAction = ['/Us/Event/commit' => 'u', '/Us/Event/invite' => 'i', '/Us/Event/redirection' => 's'];
    
    const FILE_PATH = 'us-api/';
    public static function execute()
    {
     	$statDate = date("Y-m-d", strtotime("-1 day"));
    	$data = self::webBehavior($statDate);
    	if (self::storeWebBehavior($statDate, $data)) {
    		echo "Mission Completed!\n";
    	}
    	else {
    		echo "Fail\n";
    	}
    }

    private static function webBehavior($date)
    {
    	$response = [];
    	$user = [];
    	$path = self::getPath($date);
    	foreach (scandir($path) as $file) {
    	    if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
    	        continue;
    	    }
    	    LogParser::parse($path . $file, function($item) use (&$user) {
    	        if (count($item->params) > 0 && in_array($item->path, self::$action) && Predicates::equals(intval($item->status), 200)) {
    	            $param = $item->params;
    	            if (@Predicates::equals(intval($param['platform']), Us\User\REGISTER_PLATFORM_H5_IOS)) {
            	        $pictureIdList = explode(",", $param['picture_ids']);
            	        @$user[self::$statAction[$item->path]]['pv'] += count($pictureIdList);
            	        if (array_key_exists("login_uid", $param)) {
                	        $user[self::$statAction[$item->path]]['uv']['user'][] = $param['login_uid'];
            	        }
    	            }
    	        }
    	    }, LogParser::FLAG_PARSE_QUERYSTRING);
    	}
    	foreach ($user as $action => $data) {
    	    $response[$action]["pv"] = $data["pv"];
    	    $response[$action]["uv"] = count($data["uv"]["user"]);
    	}
    	unset($user);
    	return $response;
    }

    private static function getPath($date)
    {
    	return Us\Path\READ_LOG.self::FILE_PATH.date("Ym", strtotime($date)).'/'.date('d', strtotime($date)).'/';
    }

    private static function storeWebBehavior($statDate, $data)
    {
        return Execution::autoTransaction(Yii::$app->db, function() use ($statDate, $data) {
            $connection = Yii::$app->db;
            $res = $connection->createCommand()->insert(Us\TableName\STAT, [
                    'stat_date' => date("Ymd", strtotime($statDate)),
                    'create_time' => date("Y-m-d H:i:s"),
                    'type' => 6,
                    'data' => json_encode($data),
                    ])->execute();
            return $res;
        });
    }
}
?>