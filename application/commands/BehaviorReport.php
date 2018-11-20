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
        BehaviorReport::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class BehaviorReport
{
    const DAY = 3600 * 24;
    const SPAN = 60;

    private static function activeUsers($date)
    {
        $users = [];
        $path = Us\Config\API_ACCESS_LOG_ROOT . '/' . date('Ym', strtotime(Preconditions::checkNotEmpty($date))) . '/' . date('d', strtotime($date)) . '/';
        foreach (scandir($path) as $file) {
            if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
                continue;
            }
            LogParser::parse($path . $file, function($item) use (&$users) {
                if (count($item->params) > 0 && array_key_exists("login_uid", $item->params)) {
                    $uid = $item->params["login_uid"];
                    $intUid = intval($uid);
                    if (strval($intUid) === $uid) {
                        $users[$intUid] = 1;
                    }
                }
            }, LogParser::FLAG_PARSE_QUERYSTRING);
        }
        return $users;
    }

    /*
     * json format 
     * {
     *  "0": [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0]],
     *  "1": [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0]],
     *  "2": [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0]],
     *  ......
     *  SPAN: [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0]]
     * }
     */
    private static function computeReturningUsers($now, $data)
    {
        return array_reduce($data, function($carry, $item) use ($now) {
            $distance = ($now - strtotime($item["reg_time"])) / self::DAY;
            $carry[$distance][intval($item["platform"])][intval($item["gender"])]++;
            return $carry;
        }, array_pad([], self::SPAN + 1, array_pad([], 5 /* 5 platforms */, array_pad([], /* two genders */ 2, 0))));
    }

    private static function hasActiveUser($statistic)
    {
        foreach ($statistic as $platform) {
            foreach ($platform as $gender) {
                if ($gender > 0) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    private static function serializeReturningUser($statistic)
    {
        $object = new StdClass;
        foreach ($statistic as $index => $value) {
            $index = strval($index);
            $object->$index = $value;
        }
        return json_encode($object);
    }

    private static function accumulateReturningUser($context, $statistic)
    {
        $context[0] = date("Ymd", $context[0]);
        Execution::autoTransaction(Yii::$app->db, function() use ($context, $statistic) {
            $sql = "SELECT data FROM " . Us\TableName\STAT . " WHERE type = 1 AND stat_date = " . $context[0] . " FOR UPDATE";
            if (!($data = Yii::$app->db->createCommand($sql)->queryScalar())) {
                $sql = "INSERT INTO " . Us\TableName\STAT . "(stat_date, type, data) VALUES(" . $context[0] . ", 1, '" . self::serializeReturningUser([$statistic]) . "')";
            } else {
                $data = Accessor::either(json_decode($data, true), []);
                $data[strval($context[1])] = $statistic;
                $sql = "UPDATE " . Us\TableName\STAT . " SET data = '" . self::serializeReturningUser($data) . "' WHERE type = 1 AND stat_date = " . $context[0];
            }
            error_log("BehaviorReport.php => " . $sql);
            Preconditions::checkArgument(Yii::$app->db->createCommand($sql)->execute() <= 1);
        });
    }

    public static function execute($date)
    {
        $startTime = date("Y-m-d 00:00:00", strtotime($date) - (self::SPAN * self::DAY));
        $endTime = $date . " 24:00:00";
        $result = array_reduce(array_chunk(self::activeUsers($date), 512, true), function($carry, $chunk) use ($startTime, $endTime) {
            $sql = "select u.reg_time, u.gender, d.platform from " . Us\TableName\USER . " as u inner join " . Us\TableName\USER_DEVICE
                . " as d on u.uid = d.uid where u.reg_time >= '" . $startTime . "' and u.reg_time < '" . $endTime . 
                "' and u.uid in (" . implode(", ", array_keys($chunk)). ")";
            return array_merge($carry, Yii::$app->db->createCommand($sql)->queryAll());
        }, []);
        $now = strtotime($endTime);
        array_reduce(self::computeReturningUsers($now, $result), function($carry, $statistic) {
            if (self::hasActiveUser($statistic)) {
                self::accumulateReturningUser($carry, $statistic);
            }
            $carry[0] -= self::DAY;
            ++$carry[1];
            return $carry;
        }, [$now - 1, 0]);
    }
}

?>
