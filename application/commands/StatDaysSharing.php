<?php 
/**
 *
 * 日分享数据统计  StatDaysSharing
 */
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
   StatDaysSharing();
} catch (Exception $e) {
   var_dump($e->getMessage());
}

/* 日分享数据  */
function StatDaysSharing()
{
    $share = Yii::$app->redis->get(Us\User\SHARE.date('Y-m-d', strtotime('-1 day', time())));
    if (empty($share)) {
        return false;
    }
    $commit = false;
    $connection = Yii::$app->db;
    $transaction = Yii::$app->db->beginTransaction();
    try {
        $result = $connection->createCommand()->insert(Us\TableName\STAT, [
            'stat_date' => date('Ymd', strtotime('-1 day', time())),
            'create_time' => date("Y-m-d H:i:s"),
            'type' => 4,
            'data' => json_encode(['s'=>$share]),
        ])->execute();
        if ($result) {
            $commit = true;
            echo "StatSharing Auto-complete! \n";
        }
    }
    finally {
        if ($commit) {
            $transaction->commit();
        }
        else {
            $transaction->rollback();
        }
    }
}
?>
