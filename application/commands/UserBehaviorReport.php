<?php
use yii\db\Query;
/**
 *
 * 行为分析报告  BehaviorReport
 */

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
require_once APP_PATH . '/application/commands/LogParser.php';

try {
    if (count($argv) < 2) {
        $argv = [date("Y-m-d", strtotime("-1 day"))];
    } else {
        $argv = array_slice($argv, 1);
    }
    foreach($argv as $date) {
        UserBehaviorReport::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class UserBehaviorReport
{
    public static $controller = ['us', 'event', 'stat', 'system', 'content', 'moment', 'tube'];

    public static $blackList = ['/us/system/getdomaininfo', '/us/user/uploadpushjson', '/us/stat/adclick', 
        '/us/event/list', '/us/user/getpushjson', '/us/system/gettemporarytoken'
    ];
	public static function execute($date)
	{
	    $log = self::readLog($date);
	    $data = self::doSerializeErrorLog($log);
	    if (self::doStoreUserBehaviorData($date, $data)) {
	    	echo "Mission Completed!\n";
	    } else {
	        echo "Fail!\n";
	    }
	}

	private static function readLog($date)
	{
	    $users = [];
	    $path = Us\Config\API_ACCESS_LOG_ROOT . '/' . date('Ym', strtotime(Preconditions::checkNotEmpty($date))) . '/' . date('d', strtotime($date)) . '/';
	    $num = 0;
	    foreach (scandir($path) as $file) {
	        if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
	            continue;
	        }
	        LogParser::parse($path . $file, function($item) use (&$users, &$num, $file) {
	            $functionArray = explode("/", $item->path);
	            if (in_array(strtolower($functionArray[1]), self::$controller)) {
    	            @$users['times'][strtolower($item->path)]['pv']++;
    	            @$users['unit'][$file][strtolower($item->path)]++;
    	            if (count($item->params) > 0 && array_key_exists("login_uid", $item->params)) {
        	            @$users['times'][strtolower($item->path)]['uv']['user'][$item->params['login_uid']] = 1;
        	            $users['elapsed'][strtolower($item->path)][$num]['elapsed'] = $item->elapsed;
        	            $users['elapsed'][strtolower($item->path)][$num++]['responseBodyLength'] = $item->responseBodyLength;
    	            }
	            }
	        }, LogParser::FLAG_PARSE_QUERYSTRING);
	    }
	    return $users;
	}

	private static function doSerializeErrorLog($source)
	{
	    $response = [];
		foreach ($source['times'] as $function => $functionData) {
			@$response['times'][$function]['pv'] = $functionData['pv'];
			@$response['times'][$function]['uv'] = count($functionData['uv']['user']);
		}
		foreach ($source['unit'] as $file => $fileData) {
		    $num = 0;
		    $response['unit'][substr($file, 0, -4)] = $fileData;
		    foreach ($fileData as $function => $pv) {
		        @$response['unit'][substr($file, 0, -4)]['total'] += $pv;
		        if (!in_array($function, self::$blackList)) {
		        	if ($pv>$num) {
		        	    $response['unit'][substr($file, 0, -4)]['hot'] = $function;
		        	    $num = $pv;
		        	}
		        }
		    }
		}
		foreach ($source['elapsed'] as $function => $functionData) {
		    foreach ($functionData as $data) {
    		    @$response['elapsed'][$function]['elapsed'] += $data['elapsed'];
    		    @$response['elapsed'][$function]['responseBodyLength'] += $data['responseBodyLength'];
		    }
		    $response['elapsed'][$function]['elapsed'] = $response['elapsed'][$function]['elapsed']/count($functionData);
		    $response['elapsed'][$function]['responseBodyLength'] = $response['elapsed'][$function]['responseBodyLength']/count($functionData);
		}
		return $response;
	}

	private static function doStoreUserBehaviorData($date, $data)
	{
	    return Execution::withFallback(
    	    function () use($date, $data){
    	        $connection = Yii::$app->db;
    	        $res1 = $connection->createCommand()->insert(Us\TableName\STAT, [
    	                'stat_date' => date("Ymd", strtotime($date)),
    	                'create_time' => date("Y-m-d H:i:s"),
    	                'type' => 8,
    	                'data' => json_encode($data['times']),
    	                ])->execute();
    	        $res2 = $connection->createCommand()->insert(Us\TableName\STAT, [
    	                'stat_date' => date("Ymd", strtotime($date)),
    	                'create_time' => date("Y-m-d H:i:s"),
    	                'type' => 9,
    	                'data' => json_encode($data['unit']),
    	                ])->execute();
    	       $res3 = $connection->createCommand()->insert(Us\TableName\STAT, [
    	               'stat_date' => date("Ymd", strtotime($date)),
    	               'create_time' => date("Y-m-d H:i:s"),
    	               'type' => 10,
    	               'data' => json_encode($data['elapsed']),
    	               ])->execute();
    	       return $res1 && $res2 && $res3;
	       }
	    );
	}
}
