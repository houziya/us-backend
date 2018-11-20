<?php

use Yaf\Controller_Abstract;
use yii\db\Query;

class AdClick
{
    const TABLENAME_SUB_CHANNEL = 'spread_sub_channel';

    private static $DISTRIBUTORS = null;
    
    private static $CHANNEL_INFO = null;
    
    public static function getChannels ()
    {
        if (Predicates::isNull(self::$DISTRIBUTORS)) {
            $query = new Query;
            $channelInfo = $query->from(self::TABLENAME_SUB_CHANNEL)->all();
            foreach ($channelInfo as $key => $val) {
                $channels[$val['channel_code']] = $val['channel_token'];
            }
            self::$DISTRIBUTORS = $channels;
        }
        return self::$DISTRIBUTORS;
    }

    public static function getChannelInfo ()
    {
        if (Predicates::isNull(self::$CHANNEL_INFO)) {
            $query = new Query;
            $channelInfo = $query->from(self::TABLENAME_SUB_CHANNEL)->all();
            foreach ($channelInfo as $key => $val) {
                $channels[$val['channel_code']] = ['id' => $val['id'], 'proportion' => $val['proportion']];
            }
            self::$CHANNEL_INFO = $channels;
        }
        return self::$CHANNEL_INFO;
    }
    
    private static $TODAY = null;

    private static function today()
    {
        if (Predicates::isNull(self::$TODAY)) {
            self::$TODAY = date('Ymd',time());
        }
        return self::$TODAY;
    }

    private static function key($k)
    {
        return $k . "_" . self::today();
    }

    private static function join($arg1, $arg2)
    {
        return $arg1 . "_" . $arg2;
    }

    private static function address()
    {
        return dechex(ip2long(Protocol::remoteAddress()));
    }

    public static function click($distributor, $token) {
        $ip = self::address();
        $distributor = Protocol::required("d");
        
        $channels = self::getChannels();
        //渠道token验证
        if (!array_key_exists($distributor, $channels) || !Predicates::equals(Protocol::required("t"), $channels[$distributor])) {
            return;
        }
        $redis = Yii::$app->redis;
        $redis->incr(self::key("tc"));
        $redis->incr(self::key(self::join("dc", $distributor)));
        $ipKey = self::key(self::join($ip, "c"));
        $redis->zIncrBy($ipKey, 1, $distributor);
        $redis->zIncrBy($ipKey, 1, "t");
        $redis->expire($ipKey, 3600 * 24);
        return;
    }

    private static function distributorOf($ip)
    {
        $ipKey = self::key(self::join($ip, "c"));
        $redis = Yii::$app->redis;
        $top = $redis->zRevRange($ipKey, 0, 1);
        if (Predicates::isEmpty($top) || ($top[0] == "t" && count($top) == 1)) {
          return null;
        } else {
          return $top[0] == "t" ? $top[1] : $top[0];
        }
    }

    public static function activate($platform_id)
    {
        $redis = Yii::$app->redis;
        $ip = self::address();
        $distributor = self::distributorOf($ip);
        if (Predicates::isNull($distributor)) {
            return false;
        }
        $redis->incr(self::key("ta"));
        $ipKey = self::join($ip, "a");
        $count = $redis->incr($ipKey);
        
        $platform_count = $redis->incr(self::key($platform_id));
        if ($platform_count == 1) {
            //日激活
            $redis->incr(self::key(self::join("da", $distributor)));
            if ($count < 6) {
                //日有效激活
                $redis->incr(self::key(self::join("dv", $distributor)));
            } else {
                //重复ip
                $redis->incr(self::key(self::join("dvi", $distributor)));
            }
        }
    }

    public static function register($platform_id) {
        $ip = self::address();
        $redis = Yii::$app->redis;
        $distributor = self::distributorOf($ip);
        if (Predicates::isNull($distributor)) {
            return '';
        }
        $platform_count = $redis->incr(self::key($platform_id.'_reg'));
        $ipKey = self::join($ip, "r");
        $count = $redis->incr($ipKey);
        if ($platform_count == 1) {
            if ($count < 6) {
                //有效注册
                $redis->incr(self::key(self::join("dr", $distributor)));
            }
        } else {
            //重复设备
            $redis->incr(self::key(self::join("drp", $distributor)));
        }
        return $distributor;
    }
}

?>
