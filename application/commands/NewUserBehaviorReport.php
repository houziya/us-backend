<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
require_once APP_PATH . '/application/commands/LogParser.php';
require_once APP_PATH . '/application/commands/ClientLog.php';

try {
    if (count($argv) < 2) {
        $argv = [date("Y-m-d", strtotime("-1 day"))];
    } else {
        $argv = array_slice($argv, 1);
    }
    foreach($argv as $date) {
        NewUserBehaviorReport::execute($date);
    }
} catch (Exception $e) {
    var_dump($e);
}

class NewUserBehaviorReport 
{
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
        'click_share_email'
    ];
    
    public static $totalInviteAction = [
        'click_invite_wx', 'click_invite_qq',  'click_invite_email', 'click_invite_qrcode', 'click_invite_url', 'click_invite_contacts'
    ];

	public static function execute($date)
	{
	    $newUser = UserModel::newUserList($date);
	    $event = Event::enabledEvent($date, $newUser['user']);
	    $eventPic = Event::eventPicture($date, $newUser['user']);
	    $clientLog = self::userClientBehavior($date, $newUser['user']);
	    $webBehavior = self::webBehavior($date, $newUser['user']);
	    $data = self::doFliterBehavior($newUser['hash'], $event, $clientLog, $eventPic, $webBehavior);
	    if (self::storeBehaviorData($date, $data)) {
	        echo "Mission Completed!\n";
	        return ;
	    }
	    echo "Fail\n";
	}

    private static function userClientBehavior($date, $userList)
    {
        $clientLog = ClientLog::getLog($date, $userList);
        $response = [];
        foreach ($clientLog as $action => $actionData) {
        	foreach ($actionData as $platform => $platformData) {
        	    $response[self::$clientAction[$action]][$platform]["pv"] = $platformData["pv"];
        	    $response[self::$clientAction[$action]][$platform]["uv"]= count($platformData["uv"]["user"]);
        	}
        }
        return $response;
    }

    private static function storeBehaviorData($date, $response)
    {
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\STAT, [
                'stat_date' => date("Ymd", strtotime($date)),
                'create_time' => date("Y-m-d H:i:s"),
                'type' => 7,
                'data' => json_encode($response),
                ])->execute();
        return $res;
    }

    private static function doFliterBehavior($userHash, $event, $clientLog, $eventPic, $webBehavior)
    {
        $response = [];
    	foreach ($event as $data) {
    		if ($data['n']) {
    		    @$response['e_able']['g'][$userHash[$data['uid']]['g']]++;
    		    @$response['e_able']['p'][$userHash[$data['uid']]['p']]++;
    		}
    		@$response['e_sum']['g'][$userHash[$data['uid']]['g']]++;
		    @$response['e_sum']['p'][$userHash[$data['uid']]['p']]++;
    	}

    	foreach ($eventPic as $data) {
		    @$response['u_pv']['g'][$userHash[$data['uid']]['g']] += $data['n'];
		    @$response['u_pv']['p'][$userHash[$data['uid']]['p']] += $data['n'];
		    
    	}
    	
    	$tmp = [];
    	foreach ($eventPic as $data) {
    	    $tmp[$data['uid']] = 1;
    	}
    	
    	foreach ($tmp as $uid => $none) {
    	    @$response['u_uv']['g'][$userHash[$uid]['g']] ++;
		    @$response['u_uv']['p'][$userHash[$uid]['p']] ++;
    	}
    	
    	return array_merge($response, $clientLog, $webBehavior);
    }

    private static function doQueryEventPicture($date, $uidList)
    {
        $connection = Yii::$app->db;
        $sql = "select count(mp.id) as n, m.uid from moment_picture as mp right join 
                (select id,uid from event_moment as em where em.uid in (4100,4101,4102,4103,4104) and 
                em.status=0 and em.create_time between '2015-12-14' and '2015-12-15') as m on m.id=mp.moment_id and 
                mp.status=0 and mp.create_time between '2015-12-14' and '2015-12-15' group by mp.moment_id, uid";
        $command = $connection->createCommand($sql);
        return $command->queryAll();
    }

    private static function webBehavior($date, $uidList)
    {
    	$response = [];
    	$user = [];
    	$path = Us\Config\API_ACCESS_LOG_ROOT . '/' . date('Ym', strtotime(Preconditions::checkNotEmpty($date))) . '/' . date('d', strtotime($date)) . '/';
    	foreach (scandir($path) as $file) {
    	    if (Predicates::equals($file, '.') || Predicates::equals($file, '..')) {
    	        continue;
    	    }
    	    LogParser::parse($path . $file, function($item) use (&$user, $uidList) {
    	        if (count($item->params)>0 && Predicates::equals('/Us/Event/commit', $item->path) && in_array($item->params['login_uid'], $uidList) && Predicates::equals(intval($item->status), 200)) {
    	            $param = $item->params;
    	            if (@Predicates::equals(intval($param['platform']), Us\User\REGISTER_PLATFORM_H5_IOS)) {
            	        $pictureIdList = explode(",", $param['picture_ids']);
            	        @$user['u']['pv'] += count($pictureIdList);
            	        if (array_key_exists("login_uid", $param)) {
                	        $user['u']['uv']['user'][] = $param['login_uid'];
            	        }
    	            }
    	        }
    	    }, LogParser::FLAG_PARSE_QUERYSTRING);
    	}
    	foreach ($user as $action => $data) {
    	    $response[$action]["pv"] = $data["pv"];
    	    $response[$action]["uv"] = count($data["uv"]["user"]);
    	}
    	unset($user);
    	return $response;
    }
}