<?php 
use Yaf\Controller_Abstract;
use yii\db\Query;
class AdvertController extends Controller_Abstract
{
    const limitIp = 3;   //限制IP
    const SUCCESS_STATUS = 0;  //返回成功状态
    const ERROR_STATUS = -1001;  //返回失败状态
    const limitDay = 3;     //限制天数
    const ACTIVE_SUCCESS = 1; //已激活标识
    const SEND_POST = 1;    //curl POST 
    const SEND_GET = 0;     //curl  GET
    const APP_ID = 1408737962;   //定义的 appId
    const IOS = '0';
    const ANDROID = '1';

    private static $userMark = 'user_mark';

    private static $serverKey = 'us?hi@mo~ca%c.o/m';  //服务器key

    private static $channelInfo = 'channel_info';

    /* 广告点击 */
    public function adClickAction()
    {
        $data = Protocol::arguments();
        $connection = Yii::$app->db;
        /* 接收参数-start- */
        $idfa = $data->required('idfa');
        $clkTime = date('Y-m-d H:i:s', $data->required('clickTime'));
        $clkIp =$data->required('clickIp');
        $channelName = $data->required('channelName');
        $url = explode('?', $data->required('callBackUrl'));
        $appId = $data->required('appId');
//         if ($this->checkIpByredis($clkIp, $data->required('clickTime')) > self::limitIp) {  //同ip限制
//             return $this->jsonReturn('error', self::ERROR_STATUS, NULL);
//         }
        if (Predicates::equals($this->checkChannel($data), true)) {
            if ($this->handleDb($idfa) || $this->handleDb($idfa, self::ACTIVE_SUCCESS)) {
                return $this->jsonReturn('error', self::ERROR_STATUS, NULL);
            } else {
                Execution::autoTransaction($connection, function() use($connection, $idfa, $clkTime, $clkIp, $channelName, $url, $appId) {
                    $connection->createCommand()->insert(self::$userMark, ['idfa'=>$idfa, 'clk_time'=>$clkTime, 'clk_ip'=>$clkIp, 'channel_name'=>$channelName])->execute();
                    if (Predicates::equals($this->handleDbByName($channelName), false)) {
                        $connection->createCommand()->insert(self::$channelInfo, ['channel_Name'=>$channelName, 'channel_data'=>json_encode(['url'=>$url[0], 'appId'=>$appId])])->execute();
                    }
                    return $this->jsonReturn('success', self::SUCCESS_STATUS, NULL);
                });
            }
        }
    }

    /* US激活回调  */
    public function activeAction()
    {
        $data = Protocol::arguments();
        if (Predicates::equals($data->required('platform'), self::ANDROID)) {
            Protocol::ok('', '', '');
            return;
        }
        $idfa = $data->optional('idfa');
        $idfaInfo = $this->handleDb($idfa);
        $connection = Yii::$app->db;
        if (!Predicates::equals($idfaInfo, false)) {    //是点击对应渠道的设备
            $sendResult = $this->getCurlResult(self::SEND_GET, $data, $idfaInfo, $this->getUrl($idfa));  //curl回调请求
            if (isset($sendResult['code'])) {
                if (Predicates::equals($sendResult['code'], self::SUCCESS_STATUS)) {
                    Execution::autoTransaction($connection, function() use($connection, $idfa) {
                        $connection->createCommand()->update(self::$userMark, ['status'=>self::ACTIVE_SUCCESS], ['idfa'=>$idfa])->execute();
                        Protocol::ok('', '', 'Effective activation');  //请求ok  有效激活
                    });
                    return ;
                }
            }
        }
        Protocol::ok('', '', 'Is not active');  //不是对应渠道激活量或发送数据失败
    }

    /* 校验渠道商信息 */
    private function checkChannel($data)
    {
        $encryption= md5(self::$serverKey.$data->required('clickIp').$data->required('idfa').$data->optional('mac'));
        if (!Predicates::equals($encryption, $data->required('token'))) {
            return $this->jsonReturn('error', self::ERROR_STATUS, NULL);
        }
        return true;
    }

    /* 检验 ip个数  */
    private function checkIpByredis($clkIp, $clkTime)
    {
        Yii::$app->redis->incr(date('Y-m-d', $clkTime).'_'.$clkIp);
        Yii::$app->redis->expire(date('Y-m-d', $clkTime).'_'.$idfa, 86400);
        $getNums = Yii::$app->redis->get(date('Y-m-d', $clkTime).'_'.$idfa);
        return $getNums;
    }

    /* 操作数据库  根据idfa 状态 1/0 是否激活*/
    private function handleDb($idfa, $status = 0)
    {
        return (new Query)->from(self::$userMark)->where(['idfa'=>$idfa, 'status'=>$status])->one();
    }
    
    /* 操作数据库查询  根据channelName 渠道唯一*/
    private function handleDbByName($channelName)
    {
        return (new Query)->from(self::$channelInfo)->where(['channel_name'=>$channelName])->one();
    }

    /* 查询url */
    private function getUrl($idfa, $status = 0)
    {
        $connection = Yii::$app->db;
        $query = 'select c.channel_data from '.self::$userMark.
            ' as u inner join '.self::$channelInfo.  
            ' as c on u.channel_name=c.channel_name where u.idfa='."'{$idfa}'";
        $result =  $connection->createCommand($query)->queryOne();
        return json_decode($result['channel_data'], true);
    }

    /**
    * 定义返回 参数
    * 成功-0 ; 失败 -1
    */
    private function jsonReturn($data, $code, $msg = NULL)
    {
        echo json_encode(['data'=>$data, 'code'=>$code, 'msg'=>$msg]);
        return json_encode(['data'=>$data, 'code'=>$code, 'msg'=>$msg]);
    }

    /* 时间差值 --已废弃无激活时间限制*/
    private function getDiffTime($clktime)
    {
        $diffTime = (time() - $cliktime) / (60 * 60 * 24);
        if ($diffTime > self::limitDay) {
            return $this->jsonReturn('error', self::ERROR_STATUS, NULL);
        }
        return true;
    }

    /* 得到curl 请求结果 */
    private function getCurlResult($type, $data, $idfaInfo ,$urlInfo)
    {
        switch ($type) {
            case self::SEND_GET:
            return $this->toGet($data, $idfaInfo, $urlInfo);
            break;
            case self::SEND_POST:
            return $this->toPost($data, $idfaInfo, $urlInfo);
            break;
            default:
            Protocol::badRequest('', '', 'error request');
        }
    }

    /* curl -get 请求  */
    private function toGet($data, $idfaInfo ,$urlInfo)
    {
        /* md5 加密签名 */
        $sign = md5($urlInfo['appId'].time().Protocol::remoteAddress().self::$serverKey);
        if(Predicates::isNotEmpty($data->optional('bssid', NULL))) {
            $mac = strtoupper(md5($data->optional('bssid', NULL)));
        }
        /* 拼接send数据 -start- */
        $sendParams = '?appId='.$urlInfo['appId']
            .'&wifiMac='.$data->optional('bssid', NULL)
            .'&mac='.(isset($mac) ? $mac : NULL)
            .'&idfa='.$data->required('idfa')
            .'&sign='.$sign
            .'&clktime='.strtotime($idfaInfo['clk_time'])
            .'&acttime='.time()
            .'&clkip='.$idfaInfo['clk_ip']
            .'&ip='.Protocol::remoteAddress()
            .'&appVersion='.$data->required('app_version');
       /* send数据 -end-*/
       return Http::sendGet($urlInfo['url'].$sendParams);
    }
}