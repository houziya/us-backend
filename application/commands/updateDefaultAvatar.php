<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
    updateDefaultAvatar::execute();
} catch (Exception $e) {
    var_dump($e);
}

class updateDefaultAvatar
{
    public static function execute(){
        AsyncTask::consume(Us\Config\AVATAR_NODE, function($task){
            $payload = $task->payload;
            $uid = $payload->uid;
            $url = $payload->url;
            if (!$uid || !$url) {
                echo $uid."_".$url."=parameter\n";
                return true;
            }
            $avatarData = CosFile::uploadFile('', $uid, 0, 0, 0, 0, 0, '', $url);
            if (!$avatarData) {
                echo $uid."_".$url."=uploadFail\n";
                return false;
            }
            $result = UserModel::changeDefaultAvatar($uid, $avatarData['subUrlName']);
            $res = false;
            if ($result) {
                $res = Push::pushUserAvatar($uid, $avatarData['subUrl']);
            }
            echo json_encode($response = ['result' => $result, 'uid'=>$uid, 'avatar' => $avatarData, 'push' => $res])."\n";
            return true;
        }, 2, 1024);
    }
}