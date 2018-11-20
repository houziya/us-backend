<?php
/**
 * 用户添加图数据库   UserJoinGraph
 */
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
   UserJoinGraph::addObjectFromUser();
} catch (Exception $e) {
   var_dump($e->getMessage());
}

class UserJoinGraph{
    
    public static function addObjectFromUser()
    {
        $connection = Yii::$app->db;
        $data = (new Query())->select('uid,nickname,tao_object_id')->from('user')->where(['tao_object_id'=>null])->all();
        foreach($data as $item) {
            if (Predicates::equals(NULL, $item['tao_object_id'])) {
                $current[] = $item['uid'];
                $taoObject = Tao::addObject("USER", "uid", $item['uid']);
                $connection->createCommand()->update(Us\TableName\USER, ['tao_object_id' => $taoObject->id], ['uid' => $item['uid']])->execute();
            }
        }
        echo count($current).':commplete!';
    }
}