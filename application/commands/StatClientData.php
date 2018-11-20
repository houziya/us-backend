<?php 
use yii\db\Query;
/**
 *
 * 活动相关数据统计  statEventData
 */
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try{
    StatClientData::statClient();
}catch(Exception $e){
    var_dump($e->getMessage());
}

class StatClientData
{
    const BASE_FILE_PATH = 'us-client-miner/';

    public static $clientAction = [
        'click_event_add' => 'cea',
        'click_event_invite' => 'cei',
        'click_event_share' => 'ces',
        'click_share_wxss' => 'cs_ws',
        'click_share_wxss_link' => 'cs_ws_l',
        'click_share_wxss_image' => 'cs_ws_i',
        'click_share_wxss_link_success' => 'cs_ws_ls',
        'click_share_wxss_image_success' => 'cs_ws_is',
        'click_share_wxst' => 'cs_wt',
        'click_share_wxst_link' => 'cs_wt_l',
        'click_share_wxst_image' => 'cs_wt_i',
        'click_share_wxst_link_success' => 'cs_wt_ls',
        'click_share_wxst_image_success' => 'cs_wt_is',
        'click_share_sina' => 'cs_s',
        'click_share_sina_link' => 'cs_s_l',
        'click_share_sina_image' => 'cs_s_i',
        'click_share_sina_link_success' => 'cs_s_ls',
        'click_share_sina_image_success' => 'cs_s_is',
        'click_share_qq' => 'cs_q',
        'click_share_qq_link' => 'cs_q_l',
        'click_share_qq_image' => 'cs_q_i',
        'click_share_qq_link_success' => 'cs_q_ls',
        'click_share_qq_image_success' => 'cs_q_is',
        'click_share_qq_space' => 'cs_q_s',
        'click_share_qq_space_link_success' => 'cs_q_sls',
        'click_share_url' => 'csu',
        'click_share_qrcode' => 'csq',
        'click_share_contacts' => 'csc',
        'click_share_email' => 'cse',
        'click_invite_wx' => 'ci_wx',
        'click_invite_wx_success' => 'ci_wxs',
        'click_invite_qq' => 'ci_qq',
        'click_invite_qq_success' => 'ci_qqs',
        'click_invite_email' => 'cie',
        'click_invite_qrcode' => 'ciq',
        'click_invite_url' => 'ciu',
        'click_invite_contacts' => 'cic',
        'upload_tencent_cloud_error@-999' => 'utce999',
        'upload_tencent_cloud_error@-1003' => 'utce1003',
        'upload_tencent_cloud_error@-1011' => 'utce1011',
        'upload_tencent_cloud_error@-1001' => 'utce1001',
        'event_image_saved_to_album' => 'eista',
        'share' => 'share',
        'invite' => 'invite'
    ];

    public static $totalShareAction = [
	   'click_share_wxss_link', 'click_share_wxss_image', 'click_share_wxst_link', 'click_share_wxst_image', 'click_share_sina_link',
	   'click_share_sina_image', 'click_share_qq_link', 'click_share_qq_image', 'click_share_url', 'click_share_qrcode', 'click_share_contacts',
	   'click_share_email', 'click_share_qq_space'
    ];

    public static $totalInviteAction = [
        'click_invite_wx', 'click_invite_qq',  'click_invite_email', 'click_invite_qrcode', 'click_invite_url', 'click_invite_contacts'
    ];

    public static function statClient()
    {
        $statDate = date("Y-m-d", strtotime("-1 day"));
        Execution::withFallback(
            function () use($statDate){
                $source = self::readClientLog($statDate);
                $data = self::doFilterData($source);
                if (self::doStoreClientData($statDate, $data)) {
                	echo $statDate." Mission Completed!\n";
                }
                else {
                    echo $statDate." Fail\n";
                }
            }
        );
    }

    private static function doFilterData($source)
    {
        if (Predicates::isEmpty($source)) {
            return false;
        }
        $response = [];
        foreach ($source as $action => $platformData) {
        	foreach ($platformData as $platform => $actionData) {
        	    $response[self::$clientAction[$action]][$platform]["uv"] = count($actionData['uv']['user']);
        	    $response[self::$clientAction[$action]][$platform]["pv"] = $actionData['pv'];
        	}
        }
        return $response;
    }

    private static function readClientLog($statDate)
    {
        if (Predicates::isEmpty($statDate)) {
            return false;
        }
        return self::doGetSource($statDate, self::doGetClientLogPath($statDate));
    }

    private static function doGetClientLogPath($statDate)
    {
        if (Predicates::isEmpty($statDate)) {
            return false;
        }
        return Us\Path\READ_LOG.self::BASE_FILE_PATH.date("Ym", strtotime($statDate)).'/'.date('d', strtotime($statDate)).'/';
    }

    private static function doGetSource($statDate, $path)
    {
        if (Predicates::isEmpty($statDate) || Predicates::isEmpty($path)) {
            return false;
        }
        $response = [];
        foreach (scandir($path) as $file) {
            if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
                continue;
            }
            $content = fopen(self::doGetClientLogPath($statDate).$file, "r");
            while (!feof($content)) {
                $line = fgets($content);
                $log = json_decode($line, true);
                if (Predicates::isEmpty($log)) {
                    continue;
                }
                foreach ($log as $uid => $userData) {
                    if (Predicates::isEmpty($userData)) {
                        continue;
                    }
                    foreach ($userData['l'] as $data) {
                        if ($userData['p']) {
                        	$timestamp = $data['t'];
                        } else {
                            $timestamp = $data['t']/1000;
                        }
                        if (Predicates::equals($statDate, date("Y-m-d", $timestamp))) {
                            $tmp = explode("@", $data['a']);
                            if (is_array($tmp) && $tmp[0]!="upload_tencent_cloud_error") {
                            	$data['a'] = $tmp[0];
                            }
                        	$response[$data['a']][$userData['p']]['uv']['user'][$uid] = 1;
                        	@$response[$data['a']][$userData['p']]['pv']++;
                        	if (in_array($data['a'], self::$totalShareAction)) {
                        	    $response["share"][$userData['p']]['uv']['user'][$uid] = 1;
                        	    @$response["share"][$userData['p']]['pv']++;
                        	}
                        	if (in_array($data['a'], self::$totalInviteAction)) {
                        	    $response["invite"][$userData['p']]['uv']['user'][$uid] = 1;
                        	    @$response["invite"][$userData['p']]['pv']++;
                        	}
                        }
                    }
                }
            }
        }
        return $response;
    }

    private static function doStoreClientData($statDate, $data)
    {
        if (Predicates::isEmpty($statDate) || Predicates::isEmpty($data)) {
            return false;
        }
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
                'stat_date' => date("Ymd", strtotime($statDate)),
                'create_time' => date("Y-m-d H:i:s"),
                'type' => 3,
                'data' => json_encode($data),
                ])->execute();
        return $res;
    }
}
?>