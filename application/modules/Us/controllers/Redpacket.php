<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
use yii\web\Cookie;
use yii\web\Request;

class RedpacketController extends Controller_Abstract
{

    public function PacketcodeAction()
    {
        $data = Protocol::arguments();
        $code = substr(md5($data->required('invitation_code')), -8);
        $uid = substr(md5($data->required('uid')), -8);
        $payload = ['invitation_code' => substr(md5($code . $uid), substr($data->required('uid'), -1), 8)];
        Protocol::ok($payload);
    }

    public function scoresAction()
    {
        $data = Protocol::arguments();
        Yii::$app->redis->zadd('us.packet.' . $data->required('invitation_code'), $data->required('score'), $data->required('uid'));
        if (Yii::$app->redis->zscore('us.packet.' . $data->required('invitation_code'), $data->required('uid')) == $data->required('score')) {
            echo json_encode(['scores' => 'true']);
        } else {
            echo json_encode(['scores' => 'false']);
        }
    }

    public function LeaderboardAction()
    {
        $data = Protocol::arguments();
        $board = Yii::$app->redis->zrevrange('us.packet.' . $data->required('invitation_code'), 0, - 1, 'WITHSCORES');
        $uids = implode(',', array_keys($board));
        $query = new Query();
        $query->select('uid, nickname, avatar, gender, status, salt')->from(Us\TableName\USER)->where("uid in ($uids)");
        $model = $query->all();
        foreach ($model as $key => $value) {
            $model[$key]['score'] = strval($board[$value['uid']]);
        }
        $payload = ['us.packet.' . $data->required('invitation_code') => $model];
        Protocol::ok($payload);
    }
}

