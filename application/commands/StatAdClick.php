<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

AdClickCommand::Summary();

class AdClickCommand
{
    const TABLENAME_SUB_CHANNEL = 'spread_sub_channel';
    
    private static function getChannels ()
    {
        $query = new Query;
        $channelInfo = $query->from(self::TABLENAME_SUB_CHANNEL)->all();
        return $channelInfo;
    }
    private static $KEY_SUFFIX;

    private static function yesterday()
    {
        if (is_null(self::$KEY_SUFFIX)) {
            self::$KEY_SUFFIX = date("Ymd", time() - (3600 * 20));
        }
        return self::$KEY_SUFFIX;
    }

    private static function join($arg1, $arg2)
    {
        return $arg1 . "_" . $arg2;
    }

    private static function key($prefix, $suffix)
    {
        return $prefix . "_" . $suffix;
    }

    private static function log($msg)
    {
        echo $msg . "\n";
        //MocaLog::Write('adclick', 'summary', $msg, 1);
    }

    private static function cleanup($day)
    {
        self::log("Cleanup existing adclick summary data for " . $day);
        Yii::$app->db->createCommand("delete from spread_channel_stat where summary_day = " . $day)->execute();
    }

    private static function get($key, $default = 0) {
        $redis = Yii::$app->redis;
        $result = $redis->get($key);
        if ($result === false) {
            return $default;
        } else {
            return $result;
        }
    }

    private static function doSummary($day)
    {
//         self::log("Computing adclick summary for " . $day . " ...");
//         $click = self::get(self::key("tc", $day));
//         $activation = self::get(self::key("ta", $day));
//         $validActivation = self::get(self::key("tv", $day));
//         $registration = self::get(self::key("tr", $day));
        $detail = [];
        foreach (AdClick::getChannels() as $distributor => $token) {
            $detail[$distributor] = [
                self::get(self::key(self::join("dc", $distributor), $day)),//日点击
                self::get(self::key(self::join("da", $distributor), $day)),//日激活
                self::get(self::key(self::join("dvi", $distributor), $day)),//相同IP
                self::get(self::key(self::join("dv", $distributor), $day)),//日有效激活
                self::get(self::key(self::join("dr", $distributor), $day)),//日注册
                self::get(self::key(self::join("drp", $distributor), $day))//日相同设备注册
            ];
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            self::cleanup($day);
            foreach ($detail as $distributor => $item) {
                    $channels = AdClick::getChannelInfo();
                    $sum_finally = $channels[$distributor]['proportion'] * $item[4];
                    $sid = $channels[$distributor]['id'];
                    Yii::$app->db->createCommand("insert into spread_channel_stat (summary_day, sid, click, activation, with_ip_activation, effective_activation, registrations, with_device_activation, sum_finally) values (".$day.", ".$sid.", ".$item[0].", ".$item[1].", ".$item[2].", ".$item[3].", ".$item[4].", ".$item[5].", ".$sum_finally.")")->execute();
            }
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollback();
            self::log($e->getMessage() . "\n" . $e->getTraceAsString());
        }
        self::log("AdClick summary " . $day . " is computed!");
    }

//     private static function getRegUsers()
//     {
//         $yesterday = date('Y-m-d', strtotime('-1 day', time()));
//         $beginDate = date("Y-m-d", strtotime("-1 day", time()));
//         $endDate = date('Y-m-d', time());
//         $query = new Query;
//         $regUsers = $query->select(Us\TableName\USER_DEVICE.'.rep_ip ,'.Us\TableName\USER_DEVICE.'.reg_device_id')
//         ->from(Us\TableName\USER)
//         ->innerJoin(Us\TableName\USER.'.uid = '.Us\TableName\USER_DEVICE.'.uid')
//         ->where(['between', Us\TableName\USER.'.reg_time', $beginDate, $endDate, ['and', Us\TableName\USER.'.status' => Us\User\STATUS_NORMAL]])
//         ->all();
//         return $regUsers;
//     }

//     private static function regUserStat ()
//     {
//         $regUsers = self::getRegUsers();
//         foreach ($regUsers as $key => $val)
//         {
//             $platform[$val['reg_device_id']] = $val['reg_device_id'];
//         }
//     }
    /* 计算每日点击统计数据 */
    public static function Summary() {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        self::doSummary(self::yesterday());
    }

    public function actionSummary2() {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        self::doSummary(20150923);
        self::doSummary(20150924);
        self::doSummary(20150925);
        self::doSummary(20150926);
    }
}

?>
