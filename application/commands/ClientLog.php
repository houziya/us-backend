<?php
class ClientLog {
    const BASE_FILE_PATH = 'us-client-miner/';

    public static $totalShareAction = [
        'click_share_wxss_link', 'click_share_wxss_image', 'click_share_wxst_link', 'click_share_wxst_image', 'click_share_sina_link',
        'click_share_sina_image', 'click_share_qq_link', 'click_share_qq_image', 'click_share_url', 'click_share_qrcode', 'click_share_contacts',
        'click_share_email'
    ];

    public static $totalInviteAction = [
        'click_invite_wx', 'click_invite_qq',  'click_invite_email', 'click_invite_qrcode', 'click_invite_url', 'click_invite_contacts'
    ];

	private static function getPath($statDate)
	{
	    return Us\Path\READ_LOG.self::BASE_FILE_PATH.date("Ym", strtotime($statDate)).'/'.date('d', strtotime($statDate)).'/';
	}

	public static function getLog($statDate, $uidList=null, $actionList=null)
	{
	    if (Predicates::isEmpty($statDate)) {
	        return false;
	    }
	    $response = [];
	    $path = self::getPath($statDate);
	    foreach (scandir($path) as $file) {
	        if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
	            continue;
	        }
	        $content = fopen($path.$file, "r");
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
	                if (is_array($uidList) && !in_array($uid, $uidList)) {
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
	                        if (is_array($actionList)) {
    	                        if (in_array($data['a'], $actionList)) {
    	                            $response[$data['a']][$userData['p']]['uv']['user'][$uid] = 1;
    	                            @$response[$data['a']][$userData['p']]['pv']++;
    	                        }
	                        } else {
    	                        $response[$data['a']][$userData['p']]['uv']['user'][$uid] = 1;
    	                        @$response[$data['a']][$userData['p']]['pv']++;
	                        }
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
}
?>