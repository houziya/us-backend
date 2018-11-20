<?php 
use Yaf\Controller_Abstract;
use yii\db\Query;
use yii\db\Exception;
use yii\log\Logger;

class ReportController extends Controller_Abstract
{
    const PICTURE_STATUS_NORMAL = 0;//状态正常的图片(picture)
    public static $tableReport = Us\TableName\EVENT_REPORT;
    
    public function pictureReportAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $pictureId = $data->requiredInt('picture_id');//图片Id
//         $device_id= $data->optional('device_id');//设备号
//         $platform= $data->optional('platform');//渠道 0-iphone1-android
        /* 入库操作 */
        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use( $pictureId, $loginUid, $connection) {
            $sql="SELECT p.moment_id,m.uid,p.event_id FROM moment_picture p LEFT JOIN event_moment m on p.moment_id=m.id and p.status != 1 and m.status = 0 where p.id=$pictureId";
            $uid=$connection -> createCommand($sql) ->queryone();
            if (empty($uid)) {
                Protocol::notFound('','','picture is already deleted');
                return;
            }
            $data=json_encode(array('p_id' => $pictureId));
            $res_insert = $connection->createCommand()->insert(self::$tableReport,[
                    'reporter' => $loginUid,
                    'uid' => $uid['uid'],
                    'data' => $data,
                    'status' => self::PICTURE_STATUS_NORMAL,
                    ])->execute();
            $picture_id = $connection->getLastInsertID();
            /* 结果集 */
            if($res_insert){
                $result['n'] = "";
                $result['m'] = 'success';
            }else{
                //创建失败
                $result['n'] = '';
                $result['m'] = 'insert error';
            }
            
            $result['p']['picture_id'] = $pictureId;//现场id
            Protocol::ok($result['p'], $result['n'], $result['m']);
        });
            
    }
    
    public function testAction()
    {
    }
}