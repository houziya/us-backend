<?php
/**
 *
 * @explain 活动分享，邀请，添加 计划任务接口   line:514 methodName:eventStatData
 * 数据来源接口 line:44 methodName:uploadLog
 */
use Yaf\Controller_Abstract;
use yii\db\Query;

class StatController extends Controller_Abstract
{
    /* 数据来源接口 */
    public function uploadLogAction()
    {
        $data = Protocol::arguments();
        $log = json_decode(urldecode($data->required('log')), true);
        $myfile = fopen($this->doGetPath($data->requiredInt('login_uid')), "w") or die("Unable to open file!");
        if (fwrite($myfile, json_encode([$data->requiredInt('login_uid') => ["p" => $data->requiredInt('platform'), "l" => $log]]))) {
            Protocol::ok();
        }
        fclose($myfile);
    }

    /*得到全路径下 */
    private function doGetPath($uid)
    {
        !file_exists(Us\Path\WRITE_CLIENT_LOG)?mkdir(Us\Path\WRITE_CLIENT_LOG, 0755, true):"";
     	return Us\Path\WRITE_CLIENT_LOG.$uid."_".substr(sha1(uuid_create()), 0, 10).".log";
    }

    public function setEventCountAction()
    {
        $key = date('Y-m-d', time());
        $hash = 'share_';
        Yii::$app->redis->set($hash.$key, Yii::$app->redis->incr($key));
        Protocol::ok("", "", "success");
        return;
    }

    public function getEventCountAction()
    {
         $hash = 'share_'.date('Y-m-d', time());
        var_dump(Yii::$app->redis->get($hash));
    }

    public function adClickAction()
    {
        AdClick::click(Protocol::required("d"), Protocol::required("t"));
        $this->redirect(Us\Config\IOS_DOWNLOAD_URL);
    }
}
