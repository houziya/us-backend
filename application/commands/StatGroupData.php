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
        UserGroupData::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class UserGroupData
{
    public static $controller = ['us', 'event', 'stat', 'system', 'content', 'moment', 'tube'];

    public static $blackList = ['/us/system/getdomaininfo', '/us/user/uploadpushjson', '/us/stat/adclick', 
        '/us/event/list', '/us/user/getpushjson', '/us/system/gettemporarytoken'
    ];
    
    public static $type = 6;
    
	public static function execute($date)
	{
	    $log = self::readLog($date);
	    $data = self::doSerializeErrorLog($log);
	    if (self::doCountGroupData($date, $data)) {
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
	                if (Predicates::equals(strtolower($item->path), '/us/user/weblogin') || Predicates::equals(strtolower($item->path), '/us/group/create') || Predicates::equals(strtolower($item->path), '/us/group/invite')) {
	                    if (Predicates::equals(intval($item->status), 200)) {
	                        @$users['times'][strtolower($item->path)]['pv']++;
	                    }
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
            }
            @$response['times']['gt']['pv'] = self::doGetGroupTotalData();//GroupTotal  
            @$response['times']['ptp']['pv'] = self::doGetPassTwoPersonData();//PassTwoPerson
            //@$response['times']['i']['pv'] = self::doGetInviteData();//invite
            @$response['times']['v']['pv'] = self::doGetValidData();//valid
            return $response;
        }

    private static function doGetGroupTotalData()
    {
       $connection = Yii::$app->db;
       $sql = "select count(object_type) as total from tao_object_store where object_type = ".self::$type;
       $command = $connection->createCommand($sql);
       $user = $command->queryAll();
       if (Predicates::isEmpty($user)) {
           return 0;
       }
       return @$user[0]['total'];
    }

    private static function doGetPassTwoPersonData()
    {
        $connection = Yii::$app->db;
        $sql = "select object_id from tao_object_store where object_type = ".self::$type;
        $command = $connection->createCommand($sql);
        $user = $command->queryAll();
        if (Predicates::isEmpty($user)) {
            return 0;
        }
        $num = 0;
        foreach ($user as $v) {
            foreach (@$v as $k1 => $v1) {
                $count = GroupModel::getCountMemberByGid($v1)+1;
                if ($count >2 ) {
                    $num++;
                }
            }
        }
        return $num;
    }
    
    // private static function doGetInviteData()
    // {
        // $connection = Yii::$app->db;
        // $sql = "select count(id) as count from group_user";
        // $command = $connection->createCommand($sql);
        // $user = $command->queryone();
        // if (Predicates::isEmpty($user)) {
            // return 0;
        // }
        // return $user['count'];
    // }
    
    private static function doGetValidData()
    {
        $connection = Yii::$app->db;
        $sql = "select object_id from tao_object_store where object_type = ".self::$type;
        $command = $connection->createCommand($sql);
        $user = $command->queryAll();
        if (Predicates::isEmpty($user)) {
            return 0;
        }
        $num = 0;
        foreach ($user as $v) {
            foreach (@$v as $k1 => $v1) {
                $count_group = GroupModel::getCountMemberByGid($v1)+1;
                $count_event = GroupModel::getCountEventByGid($v1);
                if ($count_group > 2 && $count_event > 0) {
                    $num++;
                }
            }
        }
        return $num;
    }
    
	private static function doCountGroupData($date, $data)
	{
	    return Execution::withFallback(
    	    function () use($date, $data){
    	        $connection = Yii::$app->db;
    	        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
    	                'stat_date' => date("Ymd", strtotime($date)),
    	                'create_time' => date("Y-m-d H:i:s"),
    	                'type' => 13,
    	                'data' => json_encode($data['times']),
    	                ])->execute();
    	       return $res;
	       }
	    );
	}
}
