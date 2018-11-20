<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
require_once APP_PATH . '/application/commands/LogParser.php';

try {
    if (count($argv) < 2) {
        $argv = [date("Y-m-d")];
    } else {
        $argv = array_slice($argv, 1);
    }
    foreach($argv as $date) {
        WarningReport::execute($date);
    }
} catch (Exception $e) {
    //var_dump($e);
}

class WarningReport
{
    public static $controller = ['Us', 'Event', 'Stat', 'System', 'Content', 'Moment', 'Tube'];
    public static $phoneList = ['18645091956', '13810132360', '15901077983'];
    const ERROR_TIMES = 10;

	public static function execute($date)
	{
		$error = self::readLog($date);
		$data = self::doSerializeErrorLog($error);
		if (self::doStoreWarningData($date, $data)) {
			echo $date." Mission Completed!\n";
		} else {
			echo $date." Fail\n";
		}
	}

	private static function readLog($date)
	{
	    $error = [];
	    $path = Us\Config\API_ACCESS_LOG_ROOT . '/' . date('Ym', strtotime(Preconditions::checkNotEmpty($date))) . '/' . date('d', strtotime($date)) . '/';
	    $num = 0;
	    $times500 = 0;
	    foreach (scandir($path) as $file) {
	        if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
	            continue;
	        }
	        LogParser::parse($path . $file, function($item) use (&$error, &$num, &$times500) {
	            $functionArray = explode("/", $item->path);
	            if (!Predicates::equals(intval($item->status), 200) && in_array($functionArray[1], self::$controller)) {
	                if (!Predicates::equals(intval($item->status), 302) || !Predicates::equals($item->path, '/Us/Stat/adClick')) {
    	                $error[$item->path][$num]['referer'] = $item->referer;
    	                $error[$item->path][$num]['status'] = $item->status;
    	                $error[$item->path][$num++]['params'] = $item->params;
	                }
	                if (Predicates::equals(intval($item->status), 500)) {
	                    $times500 ++;
	                }
	            }
	        }, LogParser::FLAG_PARSE_QUERYSTRING);
	    }
	    if ($times500>=self::ERROR_TIMES) {
	        foreach (self::$phoneList as $phone) {
    	        SMS::sms_all_send("hoolai", $phone, Us\User\CAPTCHA_MESSAGE, '0', 110);
	        }
	    }
	    echo "500 times is ".$times500."\n";
	    return $error;
	}

	private static function doSerializeErrorLog($source)
	{
	    $response = [];
		foreach ($source as $action => $actionData) {
		    foreach ($actionData as $data) {
		        @$response[$action][$data['status']]['num'] ++;
		        $response[$action][$data['status']]['referer'] = $data['referer'];
		        $response[$action][$data['status']]['params'] = $data['params'];
		    }
		}
		return $response;
	}

	private static function doStoreWarningData($date, $data)
	{
		if (Yii::$app->redis->set($date."_error:", json_encode($data))) {
			return Yii::$app->redis->expire($date."_error:", Us\User\DAY * 3);
		}
		return false;
	}
}