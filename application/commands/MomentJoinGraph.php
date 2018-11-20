<?php
/**
 * 创建动态添加图数据库   MomentJoinGraph
 */
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
   MomentJoinGraph::addObjectFromMoment();
} catch (Exception $e) {
   var_dump($e->getMessage());
}

class MomentJoinGraph{
    
    public static function addObjectFromMoment()
    {
        $connection = Yii::$app->db;
        $moment = (new Query())->select('id,event_id,uid,tao_object_id')->from('event_moment')->where(['tao_object_id'=>null])->all();
        foreach ($moment as $items) {
            if (Predicates::equals(NULL, $items['tao_object_id'])) {
                $current[] = $items['uid'];
                $object = Tao::addObject("MOMENT", "mid", $items['id'], "uid", $items['uid'], "eid", $items['event_id'], "type", 0);
                Event::doEventMomentUpdate($items['event_id'], $items['id'], ['tao_object_id' => $object->id]);
            }
        }
        echo count($current).':complete!';
    }
}