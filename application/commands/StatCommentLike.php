<?php
use yii\db\Query;
/**
 *
 * 评论和赞的相关数据统计  statCommentLikeData 
 */
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try {
    if (count($argv) < 2) {
        $argv = [date("Y-m-d", strtotime("-1 day"))];
    } else {
        $argv = array_slice($argv, 1);
    }
    foreach($argv as $date) {
        CommentLikeStatData::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class CommentLikeStatData
{
    public static function execute($date)
    {
        Execution::withFallback(
            function () use ($date){
                $endDate = date("Y-m-d", strtotime("+1 day", strtotime($date)));
                $statData = self::doGetCommentLikedata($date, $endDate);
                if (self::doCommentLikeDataToDB($date, $statData)) {
                    echo $date.' CommentLike_data has done!';
                } else {
                    echo "Fail\n";
                }
            }
        );
    }

    private static function doGetCommentLikedata($statDate, $endDate)
    {
        return [
	       'l' => self::doCommentLikeTotalData($statDate, $endDate,5),
	       'c' => self::doCommentLikeTotalData($statDate, $endDate,2)
        ];
    }
    
    private static function doCommentLikeTotalData($statDate, $endDate,$type)
    {
        $connection = Yii::$app->db;
        $sql = "select count(tas.from_object_id) as num,u.gender as g from tao_association_store as tas inner join user as u on u.tao_object_id = tas.from_object_id and tas.create_time between '".$statDate."' and '".$endDate."' and tas.association_type = ".$type." group by u.gender;";
        $command = $connection->createCommand($sql);
        $user = $command->queryAll();
        if (Predicates::isEmpty($user)) {
            return 0;
        }
        $response = [];
        foreach ($user as $data) {
            @$response['g'][$data['g']] += $data['num'];
        }
        unset($user);
        return $response;
    }
    
    private static function doCommentLikeDataToDB($statDate, $eventData)
    {
        if (empty($statDate) || empty($eventData)) {
            return false;
        }
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
            'stat_date' => date("Ymd", strtotime($statDate)),
            'create_time' => date("Y-m-d H:i:s"),
            'type' => 12,
            'data' => json_encode($eventData),
        ])->execute();
        return $res;
    }
}
?>
