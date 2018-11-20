<?php
use Yaf\Controller_Abstract;
use yii\db\Query;

class MomentModel
{
    public static function getMomentListByEid($eid)
    {
        if (Predicates::isEmpty($eid)) {
            return -1;
        }
        $query = new Query;
        $query->select('em.uid, em.create_time, em.tao_object_id as tao') ->from(Us\TableName\EVENT_MOMENT.' as em')
            ->innerJoin(Us\TableName\EVENT.' as e', 'e.id=em.event_id')
            ->where(['em.event_id' => $eid, 'em.status' => 0, 'e.status' => 0]);
        $moment = $query->all();
        $response = [];
        if ($moment) {
            array_walk($moment, function($value, $key) use(&$response) {
                @$response['mid'][] = $value['tao'];
            });
            $response['event'] = $moment;
        }
        return $response;
    }
}