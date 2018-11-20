<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try{
    checkPicture::statNums();
}catch(Exception $e){
    var_dump($e->getMessage());
}

class checkPicture{
    const basePath = 'http://uspic-10006628.file.myqcloud.com/event/moment/';
    const NORMAL_PICTURE = 0;
    const FIELD = 'id, object_id';
    const CODE = 302;
    
    private static $momentPicture = Us\TableName\MOMENT_PICTURE;
    
    public static function statNums($start=0, $limit = 100)
    {
        $abnormalId = '';
            $result = (new Query())->select(self::FIELD)
            ->from(self::$momentPicture)
            ->where(['status' => self::NORMAL_PICTURE])
            ->offset($start)
            ->limit($limit)
            ->all();
            if (!$result) {
               return false;
            }
            foreach ($result as $k=>$item) {
                $code = self::doCurlRequest($item['object_id']);
                if ( !Predicates::equals($code, self::CODE) ) {
                    continue;
                }
                $abnormalId .= $item['id'].' ';
            }
        $myfile = fopen('/usr/local/nginx/logs/nginx/stat.txt', 'w');
        fwrite($myfile, $abnormalId);
        fclose($myfile);
        $second  = $limit + 100;
        self::statNums($second, 100);
    }
    
    private static function doCurlRequest($objectId)
    {
        $ch = curl_init(self::basePath.$objectId.'.jpg');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch,  CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $info;
    }
}

?>