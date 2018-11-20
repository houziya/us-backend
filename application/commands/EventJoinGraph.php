<?php
/**
 * 创建活动添加图数据库   EventJoinGraph
 */
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
   EventJoinGraph::addObjectFromEvent();
} catch (Exception $e) {
   var_dump($e->getMessage());
}

class EventJoinGraph
{
    
    public static function addObjectFromEvent()
    {
        $data = (new Query())->select('id, uid, tao_object_id')->from('event')->where(['tao_object_id'=>null])->all();
        foreach ($data as $item) {
            if (Predicates::equals(NULL, $item['tao_object_id'])) {
                $current[] = $item['uid'];
                $result = Event::addEventObject($item['id'], 0, $item['uid'], 0);
            }
        }
        echo count($current).':complete!';
    }
}