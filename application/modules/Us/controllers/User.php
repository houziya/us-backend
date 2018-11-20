<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
use yii\web\Cookie;
use yii\web\Request;
class UserController extends Controller_Abstract
{
    const PUSH_ENABLED = 1;
    const INVITE_ENABLED = 1;
    const COMMENT_ENABLED = 1;
    const GROUP_ENABLED = 1;
    const MEMBER_ENABLED = 1;
    const STORY_ENABLED = 1;

    /* 用户推送提醒开关初始化配置 */
    private static $userConfig = [
        'push_enabled' => self::PUSH_ENABLED, //更新故事
        'invite_enabled' => self::INVITE_ENABLED, //邀请
        'comment_enabled' => self::COMMENT_ENABLED, //评论赞
        'group_enabled' => self::GROUP_ENABLED,  //入群推送
        'member_enabled' => self::MEMBER_ENABLED, //小组成员
        'story_enabled' => self::STORY_ENABLED //接受故事
    ];

    private static $groupName = Us\REGISTER\DEFAULT_GROUP;

    public static  $tableUserRecord = Us\TableName\USER_RECORD_PLATFROMID;

    public function registerAction()
    {
        $user = Protocol::arguments();
        $result = Yii::$app->redis->hset($user->required('token')."_".$user->requiredInt('type'), 1, 1);
        Yii::$app->redis->expire($user->required('token')."_".$user->requiredInt('type'), 5);
        if (!$result) {
            Protocol::badRequest(null, "正在注册请耐心等待");
            return ;
        }
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            if (!$this->doVerifyRegister($user)) {
            	return ;
            }
            $model = $this->doCreateUser($user);
            if (Predicates::isNull($model)) {
                return;
            }
            $response = ['uid' => $model->uid,
                'nickname' => $model->nickname,
                'avatar' => $_FILES?$this->doUpdateUserAvatar($model->uid, $_FILES['file']):$model->avatar,
                'gender'=> $model->gender,
                'link' => $this->doSerializeRegisterLinkData($model),
                'session_key' => $model->session_key,
                'result' => true
            ];
            AdClick::register($user->required('device_id'));
            Protocol::ok(array_merge($response, self::$userConfig));
            $commit = true;
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

    private function doVerifyPhoneNumber($phone)
    {
        return preg_match("/1[34578]{1}\d{9}$/", $phone);
    }

    private function doVerifyRegister($user){
        switch ($user->requiredInt('type')) {
        	case Us\User\REGISTER_TYPE_PHONE:
        	    $result = $this->doVerifyRegisterFromPhone($user);
        	    break;
        	case Us\User\REGISTER_TYPE_QQ:
        	    $result = $this->doVerifyRegisterFromQQ($user);
        	    break;
        	case Us\User\REGISTER_TYPE_SINA:
        	    $result = $this->doVerifyRegisterFromThird($user);
        	    break;
    	    case Us\User\REGISTER_TYPE_WECHAT:
    	        $result = $this->doVerifyRegisterFromThird($user);
    	        break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $user->requiredInt('type'));
        }
        return $result;
    }

    private function doVerifyRegisterFromQQ($user)
    {
    	if (!$this->doVerifyOpenId($user->required('token'))) {
    	    Protocol::ok(null, null, null, Notice::get()->invalidQQ());
    	    return false;
    	}
    	if ($this->doVerifyRegisterAccount($user)) {
    	    Protocol::ok(null, null, null, Notice::get()->phoneNumberAlreadyExists());
    	    return false;
    	}
    	return true;
    }

    private function doVerifyOpenId($openId)
    {
        return (0 == preg_match('/^[0-9a-fA-F]{32}$/', $openId)) ? false : true;
    }

    private function doVerifyRegisterFromPhone($user)
    {
        if (!$this->doVerifyPhoneNumber($user->requiredInt('token'))) {
            Protocol::ok(null, null, null, Notice::get()->invalidPhone());
            return;
        }
        if ($this->doVerifyRegisterAccount($user)) {
            Protocol::ok(null, null, null, Notice::get()->phoneNumberAlreadyExists());
            return;
        }
        if (Us\Captcha\VERIFY) {          //验证验证码开关
            $captcha = $user->required('captcha');
            $token = $user->token;
            $captchaKey = Us\User\CAPTCHA_PHONE.$token;
            $captchaCache = Yii::$app->redis->get($captchaKey);
            $attemptsKey = $token . '.attempts';
            if ($captcha != $captchaCache) {
                $attempts = Yii::$app->redis->get($attemptsKey);
                if ($attempts > Us\User\ATTEMPTS_TIMES) {
                    Protocol::ok(null, null, null, Notice::get()->tooManyRequest());
                    return false;
                }
                Yii::$app->redis->set($attemptsKey, $attempts + 1);
                Yii::$app->redis->expire($attemptsKey, Us\Config\CAPTCHA_ATTEMPTS_EXPIRE);
                Protocol::ok(null, null, null, Notice::get()->invalidCaptcha());
                return false;
            }
            Yii::$app->redis->del($attemptsKey);
            Yii::$app->redis->del($captchaKey);
        }
        return true;
    }

    private function doVerifyRegisterAccount($user)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $user->required('token'), 'type' => $user->requiredInt('type'), 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $registered = $query->count();
        if (empty($registered)) {
            return false;
        }
        return true;
    }

    private function doVerifyRegisterFromThird($user)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $user->required('token'), 'type' => $user->requiredInt('type'), 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $registered = $query->one();
        if (!empty($registered)) {
            Protocol::ok(null, null, null, Notice::get()->thirdAccountAlreadyExists());
            return;
        }
        return true;
    }

    private function doCreateUser($user)
    {
        $model = $this->doPrepareModel($user);
        $userModel = $this->doUserModel($model);
        $resultAccount = $this->doUserAccountModel($userModel);
        $resultDevice = $this->doUserDeviceModel($userModel);
        $resultConfig = $this->doUserConfigModel($userModel);
        if ($userModel && $userModel && $resultAccount && $resultDevice && $resultConfig) {
            return $userModel;
        }
        else {
            return NULL;
        }
    }

    private function doAddGearmanWork($model)
    {
        $task = ['uid' => $model->uid, 'url' => $model->avatar_url];
        AsyncTask::submit(Us\Config\AVATAR_NODE, $task);
        return true;
    }

    private function doStoreRedisList($key, $value, $times)
    {
    	for ($i=0; $i<$times; $i++) {
    		$result = Yii::$app->redis->lpush($key, $value);
    		if ($result) {
    			return true;
    		}
    	}
    	return false;
    }

    private function doUserModel($user){
        $value = 4;
        $flag = true;
        $salt = bin2hex(openssl_random_pseudo_bytes($value, $flag));
        $connection = Yii::$app->db;
        $connection->createCommand()->insert(Us\TableName\USER, [
                'nickname' => $user->nickname,
                'avatar' => "default",
                'reg_time' => date("Y-m-d H:i:s"),
                'gender' => $user->gender,
                'status' => 0,
                'salt' => $salt,
                'user_login_id' => $user->type."@".$user->token,
        ])->execute();
        $user->uid = $connection->getLastInsertID();
        $taoObject = Tao::addObject("USER", "uid", $user->uid);
        $connection->createCommand()->update(Us\TableName\USER, ['tao_object_id' => $taoObject->id], ['uid' => $user->uid])->execute();
        /* 注册默认添加两个小组  */
        $this->doCreateGroup($user);
        $user->session_key = Session::reset($user->uid, Us\Config\SESSION_EXPIRE);
        $user->salt = $salt;
        !Predicates::equals($user->type, Us\User\REGISTER_TYPE_PHONE)?$this->doAddGearmanWork($user):null;
        return $user;
    }

    /* 新用户默认创建两个小组 */
    private function doCreateGroup($userInfo)
    {
        $defaultGroup = explode(',', self::$groupName);
        $defaultPic = explode(',', Us\REGISTER\DEFAULT_COVERPAGE);
        foreach($defaultGroup as $key=>$name) {
            $groupInfo = Group::createGroup($userInfo->nickname.'的'.$name, $userInfo->uid, $defaultPic[$key]);
            if(!Event::addObjectForDribs(Event::doAddDribs($userInfo->uid), $groupInfo['id'], $userInfo->uid, 1)) {
                return false;
            }
        }
        return true;
    }

    private function doStoreRedisString($key, $value, $times)
    {
    	for ($i=0; $i<$times; $i++) {
    		$result = Yii::$app->redis->set($key, $value);
    		if ($result) {
    			return true;
    		}
    	}
    	return false;
    }

    private function doUserDeviceModel($user){
        $this->doStoreRedisString(Us\User\US_DEVID.$user->uid, $user->device_id, 10);
        $connection = Yii::$app->db;
        $result = $connection->createCommand()->insert(Us\TableName\USER_DEVICE, [
                'uid' => $user->uid,
                'reg_ip' => $user->reg_ip,
                'log_ip' => $user->log_ip,
                'reg_device_id' => $user->device_id,
                'log_device_id' => $user->device_id,
                'platform' => $user->platform,
                'login_time' => $user->login_time,
                'distributor' => $user->distributor,
                'client_version' => $user->client_version,
                'os_version' => $user->os_version,
                ])->execute();

        $connection = Yii::$app->db;

        $resultHistory = $connection->createCommand()->insert(Us\TableName\USER_DEVICE_HISTORY, [
                'uid' => $user->uid,
                'log_ip' => $user->log_ip,
                'log_device_id' => $user->device_id,
                'platform' => $user->platform,
                'login_time' => $user->login_time,
                'distributor' => $user->distributor,
                'client_version' => $user->client_version,
                'os_version' => $user->os_version,
                ])->execute();

        if( $result && $resultHistory ){
        	return true;
        }
        else{
        	return false;
        }
    }

    private function doPrepareModel($user)
    {
        $model = new stdClass();
        if (@$user->optional('device_info')) {
            $deviceInfo = json_decode(urldecode($user->required('device_info')), true);
            Accessor::wrap($deviceInfo)->copyRequired(['model' => 'phone_model', 'client_version', 'os_version', 'product' => 'distributor'], $model);
            $user->copyRequired([
                'type', 'token', 'gender', 'nickname', 'platform', 'device_id', 'distributor', 'secret',
            ], $model);
        } else {
            $user->copyRequired([
                'type', 'token', 'gender', 'nickname', 'platform', 'device_id', 'distributor', 'client_version', 'secret',
                'os_version', 'phone_model'
            ], $model);
        }
        
        $model->avatar_url = (!Predicates::equals($model->type, Us\User\REGISTER_TYPE_PHONE))?$user->optional('avatar_url'):"";
        $model->os_version = Types::versionToLong($model->os_version);
        if (!Predicates::equals(strlen($model->client_version), 5)) {
            $model->os_version .= ".0";
        }
        $model->client_version = Types::versionToLong($model->client_version);
        $model->reg_ip = ip2long(Protocol::remoteAddress());
        $model->log_ip = ip2long(Protocol::remoteAddress());
        $model->avatar = Us\User\DEFAULT_AVATAR;
        $model->login_time = date("Y-m-d H:i:s");
        $model->reg_time = date("Y-m-d H:i:s");
        $model->phone_model = $this->doGetPhoneModelCode($model->phone_model);
        $model->distributor = $this->doGetDistributorCode($model->distributor);
        return $model;
    }

    private function doGetPhoneModelCode($phone_model)
    {
    	$key = Us\User\PHONE_MODEL;
    	$code = Yii::$app->redis->hget($key, $phone_model);
    	if (!$code) {
    	    $connection = Yii::$app->db;
    	    $connection->createCommand()->insert(Us\TableName\SYSTEM_CODE, [
    	            'type' => 0,
    	            'name' => $phone_model,
    	    ])->execute();
    	    $code = $connection->getLastInsertID();
    	    Yii::$app->redis->hset($key, $phone_model, $code);
    	}
    	return $code;
    }

    private function doGetDistributorCode($distributor)
    {
        $key = Us\User\DISTRIBUTOR;
        $code = Yii::$app->redis->hget($key, $distributor);
        if (!$code) {
            $connection = Yii::$app->db;
            $connection->createCommand()->insert(Us\TableName\SYSTEM_CODE, [
                    'type' => 1,
                    'name' => $distributor,
                    ])->execute();
            $code = $connection->getLastInsertID();
            Yii::$app->redis->hset($key, $distributor, $code);
        }
        return $code;
    }

    private function doUserConfigModel($user)
    {
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->insert(Us\TableName\USER_CONFIG, [
                'uid' => $user->uid,
                'type' => 0,      //个人开关
                'setting' => json_encode(self::$userConfig),
                ])->execute();
        return $res;
    }

    private function doSerializeRegisterLinkData($data)
    {
        switch ($data->type) {
        	case Us\User\REGISTER_TYPE_PHONE:
        	    return ['phone' => $data->token, 'qq' => new stdClass(), 'sina' => new stdClass(), 'weChat' => new stdClass()];
        	    break;
        	case Us\User\REGISTER_TYPE_QQ:
        	    return ['phone' => "", 'qq' => ['token' => $data->token], 'sina' => new stdClass(), 'weChat' => new stdClass()];
        	    break;
        	case Us\User\REGISTER_TYPE_SINA:
        	    return ['phone' => "", 'qq' => new stdClass(), 'sina' => ['token' => $data->token], 'weChat' => new stdClass()];
        	    break;
        	case Us\User\REGISTER_TYPE_WECHAT:
        	    return ['phone' => "", 'qq' => new stdClass(), 'sina' => new stdClass(), 'weChat' => ['token' => $data->token]];
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $data->type);
        }
    }

    private function doUserAccountModel($user)
    {
        $connection = Yii::$app->db;
        $key = $this->doPasswordDigest($user->salt, $user->secret);
        $res = $connection->createCommand()->insert(Us\TableName\USER_LOGIN, [
                'uid' => $user->uid,
                'type' => $user->type,
                'secret' => $user->type?$user->secret:$key,
                'token' => $user->token,
                'enabled' => Us\User\ACCOUNT_NORMAL,
                ])->execute();
        return $res;
    }

    private function doPasswordDigest($salt, $secret)
    {
    	for ($i=0; $i<Us\User\ENCRYPT_NUM; $i++) {
    	    $secret = sha1($salt.$secret);
    	}
    	return $secret;
    }

    private function doLinkAccountModel($user)
    {
        try {
            $connection = Yii::$app->db;
            $res = $connection->createCommand()->insert(Us\TableName\USER_LOGIN, [
                    'uid' => $user->uid,
                    'type' => $user->type,
                    'secret' => $user->secret,
                    'token' => $user->token,
                    'enabled' => Us\User\ACCOUNT_NORMAL
                    ])->execute();
        }
        catch (Exception $e) {
            $res = $connection->createCommand()->update(Us\TableName\USER_LOGIN, ['enabled' => Us\User\ACCOUNT_NORMAL], [
                    'token' => $user->token,
                    'uid' => $user->uid,
                    'type' => $user->type,
                    'enabled' => Us\User\ACCOUNT_UNLINK
                    ])->execute();
        }
        return $res;
    }

    private function doVerifyCaptchaCode($data)
    {
        if (Us\Captcha\VERIFY) {
            $key = Us\User\CAPTCHA_PHONE . $data->required('token');
         	if( Yii::$app->redis->get($key) && Yii::$app->redis->get($key)==$data->required('captcha') ){
        		return true;
         	}
        	return false;
        }
        return true;
    }

    public function verifyCaptchaAction()
    {
        $data = Protocol::arguments();
        $result = true;
        $content = NULL;
        if ($this->doVerifyRegisterAccount($data)) {
        	$content = Us\User\PHONE_CONTENT;
        }
        if (Us\Captcha\VERIFY && !$this->doVerifyCaptchaCode($data)) {
             $content = Us\User\CAPTCHA_CONTENT;
        }
        if (!$content) {
            $response = ['result' => true];
            Protocol::ok($response);
        }
        else{
            Protocol::ok(null, null, null, $content);
        }
    }

    public function sendCaptchaAction()
    {
        if (Us\Captcha\SEND) {
            $data = Protocol::arguments();
            if ($this->doVerifySendCaptcha($data)) {
            	if(SMS::sms_all_send("hoolai", $data->required('token'), Us\User\CAPTCHA_MESSAGE, '0', $data->required('type'))) {
            	    $response = ['result' => true];
            	    Protocol::ok($response);
            	    return ;
            	}
            }
            return ;
        }
        Protocol::badRequest(NULL, NULL, '发送验证码已关闭');
        return ;
    }

    private function doVerifyPhoneAccount($token)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $token, 'type' => 0, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        return $query->count();
    }

    private function doVerifySendCaptcha($data)
    {
        if ($this->doVerifyPhoneAccount($data->requiredInt('token'))) {
            Protocol::ok(null, null, null, Notice::get()->phoneNumberAlreadyExists());
            return false;
        }
    	if (!$this->doVerifySendCaptchaTimes($data)) {
    		return false;
    	}
    	return true;
    }
    private function doVerifySendCaptchaTimes($data)
    {
        $phone = $data->required('token');
        $attempts = Yii::$app->redis->incr(Us\User\CAPTCHA_PHONE.$phone.'attempts');
        Yii::$app->redis->expire(Us\User\CAPTCHA_PHONE.$phone.'attempts', Us\Config\CAPTCHA_ATTEMPTS_EXPIRE);
        if( $attempts > Us\User\ATTEMPTS_TIMES ){
            Protocol::ok(null, null, null, Notice::get()->tooManyRequest());
            return false;
        }
        return true;
    }
    
    /* 获取当前用户配置接口 */
    public function userConfigAction()
    {
        $data = Protocol::arguments();
        /* 验证设备的状态 */
        Auth::verifyDeviceStatus($data);
        $set = $this->doGetConfig($data->required('login_uid'));
        Protocol::ok($this->doGetUserSetting(self::$userConfig, $set));
    }

    public function loginAction()
    {
    	$data = Protocol::arguments();
    	$uid = $this->doGetAccount($data);
    	$user = $this->doGetUserData($uid);
    	if ($user['status']) {
    	    Protocol::ok(NULL, "帐号已被冻结");
    		return ;
    	}
    	if ($uid && $this->doVerifyUserStatus($data, $user)) {
    	    $result = $this->doVerifyUserDeviceInfo($uid, $data);
    	    $link = $this->doGetLinkAccount($uid);
    	    //$setting = $this->doGetConfig($uid);
    	    $setting = $this->doGetUserSetting(self::$userConfig, $this->doGetConfig($uid));
    	    $response = [
        	    'uid' => $uid,
        	    'nickname' => $user['nickname'],
        	    'avatar' => 'profile/avatar/'.$user['avatar'].".jpg",
        	    'gender'=> $user['gender'],
        	    'link' => $this->doSerializeLoginLinkData($link),
        	    'session_key' => Session::reset($uid, Us\Config\SESSION_EXPIRE),
        	    'result' => true,
    	    ];
    	    Protocol::ok(array_merge($response, $setting));
    	}
    }

    private function doGetUserSetting($userConfig, $setting)
    {
        foreach ($userConfig as $enabled=>$config) {
            if (array_key_exists($enabled, $setting)) {
                $userConfig[$enabled] = $setting[$enabled];
            } else {
                $userConfig[$enabled] = self::PUSH_ENABLED;
            }
        }
        return $userConfig;
    }

    private function doVerifyUserDeviceInfo($uid, $data)
    {
        return $this->doUpdateUserDevice($uid, $data);
    }

    private function doUpdateUserDevice($uid, $data)
    {
        $this->doStoreRedisString(Us\User\US_DEVID.$uid, $data->required('device_id'), 10);
        $connection = Yii::$app->db;
        $result = $connection->createCommand()->update(Us\TableName\USER_DEVICE,
                [
                    'log_device_id' => $data->required('device_id'),
                    'os_version' => Types::versionToLong($data->required('os_version')),
                    'client_version' => Types::versionToLong($data->required('client_version')),
                    'platform' => $data->required('platform'),
                    'log_ip' => ip2long(Protocol::remoteAddress()),
                    'login_time' => date("Y-m-d H:i:s"),
                    'phone_model' => $this->doGetPhoneModelCode($data->required('phone_model')),
                    'distributor' => $this->doGetPhoneModelCode($data->required('distributor')),
                ],
                ['uid' => $uid])->execute();
        if ($result) {
            $resultHistory = $connection->createCommand()->insert(Us\TableName\USER_DEVICE_HISTORY, [
                    'uid' => $uid,
                    'log_ip' => ip2long(Protocol::remoteAddress()),
                    'log_device_id' => $data->required('device_id'),
                    'platform' => $data->required('platform'),
                    'login_time' => date("Y-m-d H:i:s"),
                    'distributor' => $this->doGetPhoneModelCode($data->required('distributor')),
                    'phone_model' => $this->doGetPhoneModelCode($data->required('phone_model')),
                    'client_version' => Types::versionToLong($data->required('client_version')),
                    'os_version' => Types::versionToLong($data->required('os_version')),
                    ])->execute();
            return $resultHistory;
        }
        return false;
    }

    private function doGetAccountSecret($uid, $token, $type)
    {
        $query = new Query;
        $query->select('secret') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $token, 'type' => $type, 'uid' => $uid, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->one();
        if ( empty($userData)) {
            Protocol::ok(null, null, null, Notice::get()->invalidAccount());
            return false;
        }
        return $userData['secret'];
    }

    private function doGetSecretByUid($uid)
    {
        $query = new Query;
        $query->select('token, secret') ->from(Us\TableName\USER_LOGIN) ->where(['type' => 0, 'uid' => $uid, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->one();
        if (empty($userData)) {
            Protocol::ok(null, null, null, Notice::get()->invalidAccount());
            return false;
        }
        return $userData;
    }

    private function doGetAccount($user)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $user->required('token'), 'type' => $user->requiredInt('type'), 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->one();
        if ( empty($userData)) {
            switch ($user->requiredInt('type')) {
            	case Us\User\REGISTER_TYPE_PHONE:
            	    Protocol::ok(null, null, null, Notice::get()->invalidAccount());
            	    break;
            	case Us\User\REGISTER_TYPE_QQ:
            	    Protocol::ok(null, null, null, Notice::get()->thirdInvalidAccount());
            	    break;
            	case Us\User\REGISTER_TYPE_SINA:
            	    Protocol::ok(null, null, null, Notice::get()->thirdInvalidAccount());
            	    break;
            	case Us\User\REGISTER_TYPE_WECHAT:
            	    Protocol::ok(null, null, null, Notice::get()->thirdInvalidAccount());
            	    break;
            	default:
            	    throw new InvalidArgumentException('Invalid registration type '. $data->type);
            }
            return false;
        }
        return $userData['uid'];
    }

    private function doGetUserData($uid)
    {
        $query = new Query;
        $query->select('uid, nickname, avatar, gender, status, salt') ->from(Us\TableName\USER) ->where(['uid' => $uid]);
        $userData = $query->one();
        if (empty($userData)) {
            return false;
        }
        return $userData;
    }

    private function doVerifyUserStatus($data, $user)
    {
        if ($this->doVerifyUserBlocked($user['status'])) {
            if ($data->requiredInt('type') == Us\User\REGISTER_TYPE_PHONE ) {
        		if($this->doVerifyUserSecret($this->doPasswordDigest($user['salt'], $data->required('secret')), $this->doGetAccountSecret($user['uid'], $data->requiredInt('token'), $data->requiredInt('type')))){
        			return true;
        		}
        		return false;
            }
            else {
            	return true;
            }
    		return false;
        }
        return false;
    }

    private function doVerifyUserBlocked($status)
    {
        if (!Predicates::equals(intval($status), Us\User\STATUS_NORMAL)) {
            Protocol::ok(null, null, null, Notice::get()->userBlocked());
            return false;
        }
        return true;
    }

    private function doVerifyUserSecret($secret, $key)
    {
        if (!Predicates::equals($secret, $key)) {
            Protocol::ok(null, null, null, Notice::get()->invalidPassword());
            return false;
        }
        return true;
    }

    private function doVerifyUserOldSecret($secret, $key)
    {
        if (!Predicates::equals($secret, $key)) {
            Protocol::ok(null, null, null, Notice::get()->invalidOldPassword());
            return false;
        }
        return true;
    }

    private function doGetLinkAccount($uid)
    {
        $query = new Query;
        $query->select('token, type') ->from(Us\TableName\USER_LOGIN) ->where(['uid' => $uid, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->all();
        if (empty($userData)) {
            return false;
        }
        $result = [];
        foreach ($userData as $data) {
            $result[$data['type']] = $data['token'];
        }
        return $result;
    }

    private function doGetConfig($uid)
    {
        $query = new Query;
        $query->select('setting, type') ->from(Us\TableName\USER_CONFIG) ->where(['uid' => $uid]);
        $configData = $query->all();
        if (empty($configData)) {
            return false;
        }
    	$result = [];
        foreach ($configData as $value) {
            $data = json_decode($value['setting'], true);
            foreach ($data as $key => $status) {
                $result[$key] = $status;
            }
        }
        return $result;
    }

    private function doSerializeLoginLinkData($data)
    {
        if (empty($data)) {
        	return false;
        }
        $result = [
        	'phone' => "",
        	'qq' => new stdClass(),
        	'sina' => new stdClass(),
        	'weChat' => new stdClass()
        ];
        foreach ($data as $type => $token) {
            switch ($type) {
                case Us\User\REGISTER_TYPE_PHONE:
                    $result['phone'] = $token;
                    break;
            	case Us\User\REGISTER_TYPE_QQ:
            	    $result['qq'] = (object)['token' => $token];
            	    break;
            	case Us\User\REGISTER_TYPE_SINA:
            	    $result['sina'] = (object)['token' => $token];
            	    break;
            	case Us\User\REGISTER_TYPE_WECHAT:
            	    $result['weChat'] = (object)['token' => $token];
            	    break;
            	default:
            	    throw new InvalidArgumentException('Invalid registration type '. $type);
            }
        }
        return $result;
    }

    private function doResetSecret($uid, $token, $secret)
    {
    	$connection = Yii::$app->db;
    	if ($connection->createCommand()->update(Us\TableName\USER_LOGIN, ['secret' => $secret], ['token' => $token, 'uid' => $uid, 'enabled' => Us\User\ACCOUNT_NORMAL])->execute()) {
    		return true;
    	}
    	Protocol::ok(null, null, null, Notice::get()->identicalPassword());
    	return false;
    }

    public function resetPasswordAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            $user = $this->doGetUserData($data->requiredInt('login_uid'));
            $account = $this->doGetSecretByUid($data->requiredInt('login_uid'));
            if ($user && $this->doVerifyUserBlocked($user['status']) && $this->doVerifyUserOldSecret($this->doPasswordDigest($user['salt'], $data->required('old_secret')), $account['secret'])) {
                if ($this->doResetSecret($data->requiredInt('login_uid'), $account['token'], $this->doPasswordDigest($user['salt'], $data->required('new_secret')))) {
                    $response = ['result' => true];
                    Protocol::ok($response);
                    $commit = true;
                }
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

    private function doGetPhoneAccount($data)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $data->required('token'), 'type' => 0, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->one();
        if ( empty($userData)) {
            Protocol::ok(null, null, null, Notice::get()->phoneAccountNoExists());
            return false;
        }
        return $userData['uid'];
    }

    public function forgetPasswordAction()
    {
    	$data = Protocol::arguments();
    	$transaction = Yii::$app->db->beginTransaction();
    	$commit = false;
    	try {
        	if (Us\Captcha\VERIFY && !$this->doVerifyCaptchaCode($data)) {
        	    Protocol::ok(null, null, null, Notice::get()->invalidAccount());
        	    return ;
        	}
        	$uid = $this->doGetPhoneAccount($data);
    	    $user = $this->doGetUserData($uid);
        	if ($uid && $this->doResetSecret($uid, $data->required('token'), $this->doPasswordDigest($user['salt'], $data->required('secret')))) {
        	    $link = $this->doGetLinkAccount($uid);
        	    //$setting = $this->doGetConfig($uid);
        	    $setting = $this->doGetUserSetting(self::$userConfig, $this->doGetConfig($uid));
        	    $response = [
            	    'uid' => $uid,
            	    'nickname' => $user['nickname'],
            	    'avatar' => 'profile/avatar/'.$user['avatar'].".jpg",
            	    'gender'=> $user['gender'],
            	    'link' => $this->doSerializeLoginLinkData($link),
            	    'session_key' => Session::reset($uid, Us\Config\SESSION_EXPIRE),
            	    'result' => true
    	        ];
        	    Protocol::ok(array_merge($response, $setting));
        	    $commit = true;
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

    private function doLinkPhoneNumber($data)
    {
        if (!$this->doVerifyPhoneNumber($data->requiredInt('token'))) {
            Protocol::ok(null, null, null, Notice::get()->invalidPhone());
            return false;
        }
        if ($this->doVerifyCaptchaCode($data)) {
            $oldPhone = $this->doVerifyAccountToken($data->requiredInt('login_uid'), $data->requiredInt('type'));
    	    $user = new stdClass();
    	    $data->copyRequired(['type' ,'token', 'login_uid'=>'uid'], $user);
        	if (!$oldPhone) {
        	    $user->secret = $data->requiredInt('secret');
        	    return $this->doLinkAccountModel($user);
        	}
        	else if ($oldPhone!==$data->requiredInt('token')) {
        	    $user->secret = $this->doGetAccountSecret($data->requiredInt('login_uid'), $oldPhone, Us\User\REGISTER_TYPE_PHONE);
        	    $unlinkData = new stdClass();
        	    $data->copyRequired(['type', 'login_uid'], $unlinkData);
        	    $unlinkData->token = $oldPhone;
        		if ($this->doUnlinkAccount($unlinkData)) {
        		    return $this->doLinkAccountModel($user);
        		}
        	}
        }
        Protocol::ok(null, null, null, Notice::get()->invalidCaptcha());
        return false;
    }

    private function doVerifyAccountToken($uid, $type)
    {
        $query = new Query;
        $query->select('token') ->from(Us\TableName\USER_LOGIN) ->where(['type' => $type, 'enabled' => Us\User\ACCOUNT_NORMAL, 'uid' => $uid]);
        $userData = $query->one();
        if ( empty($userData)) {
            return false;
        }
        return $userData['token'];
    }

    private function doLinkThirdAccount($data)
    {
        $user = new stdClass();
        $data->copyRequired(['type', 'login_uid'=>'uid', 'token', 'secret'], $user);
        $this->doLinkAccountModel($user);
        return ['token' => $user->token, 'secret' => $user->secret];
    }

    public function linkAction()
    {
    	$data = Protocol::arguments();
    	Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
    	$transaction = Yii::$app->db->beginTransaction();
    	$commit = false;
    	try{
    	    if ($this->doVerifyRegisterAccount($data)) {
    	        Protocol::ok(null, null, null, Notice::get()->accountAlreadyExists());
    	        return ;
    	    }
        	$user = $this->doGetUserData($data->requiredInt('login_uid'));
        	if (!$user) {
        	    Protocol::ok(null, null, null, Notice::get()->invalidAccount());
        	    return ;
        	}
        	if ($this->doVerifyUserBlocked($user['status'])) {
        	    if ($this->doLinkAccount($data)) {
        	        $response = ['result' => true];
                	Protocol::ok($response);
                	$commit = true;
        	    }
        	}
    	}
    	finally {
    		if($commit) {
    			$transaction->commit();
    		}
    		else {
    			$transaction->rollback();
    		}
    	}
    }

    private function doLinkAccount($data)
    {
        switch ($data->requiredInt('type')) {
        	case Us\User\REGISTER_TYPE_PHONE:
                Protocol::badRequest(NULL, '验证失败');
                return;
        	    $result = $this->doLinkPhoneNumber($data);
        	    break;
        	case Us\User\REGISTER_TYPE_QQ:
        	    $result = $this->doLinkThirdAccount($data);
        	    break;
        	case Us\User\REGISTER_TYPE_SINA:
        	    $result = $this->doLinkThirdAccount($data);
        	    break;
        	case Us\User\REGISTER_TYPE_WECHAT:
        	    $result = $this->doLinkThirdAccount($data);
        	    break;
        	default:
        	    throw new InvalidArgumentException('Invalid registration type '. $data->requiredInt('type'));
        }
        return $result;
    }

    private function doGetUserAccountNum($uid)
    {
        $query = new Query;
        $query->select('token') ->from(Us\TableName\USER_LOGIN) ->where(['uid' => $uid, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->count();
        if ( empty($userData)) {
            return false;
        }
        return $userData;
    }

    private function doUnlinkAccount($data)
    {
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->update(Us\TableName\USER_LOGIN, ['enabled' => Us\User\ACCOUNT_UNLINK],
                ['uid' => $data->login_uid, 'type' => $data->type, 'token' => $data->token])
                ->execute();
        return $res;
    }

    public function unlinkAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            if(!$this->doVerifyUnlinkAccount($data)){
            	return ;
            }
            $user = $this->doGetUserData($data->requiredInt('login_uid'));
            if(!$user) {
                Protocol::ok(null, null, null, Notice::get()->invalidAccount());
                return ;
            }
            if($this->doUnlinkAccount($data)) {
                Protocol::ok();
                $commit = true;
            }
        }
        finally {
        	if($commit) {
        		$transaction->commit();
        	}
        	else {
        		$transaction->rollback();
        	}
        }
    }

    private function doVerifyUnlinkAccount($data)
    {
        if(!$this->doVerifyRegisterAccount($data)){
            Protocol::ok(null, null, null, Notice::get()->accountNoExists());
            return false;
        }
        if($this->doGetUserAccountNum($data->requiredInt('login_uid'))<Us\User\UNLINK_NUM){
            Protocol::badRequest(null, Notice::get()->unlinkLimit());
            return false;
        }
        return true;
    }

    private function doUploadAvatar($file, $uid, $category=0, $pictureType=0, $filetype=0, $event_id=0, $moment_id = 0, $fileName='', $url='')
    {
        return CosFile::uploadFile($file, $uid, $category, $pictureType, $filetype, $event_id, $moment_id, $fileName, $url);
    }

    public function getProfileAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        if($data->requiredInt('login_uid') == $data->requiredInt('uid')) {
            Protocol::ok();
            return ;
        }
        $user = $this->doGetUserData($data->requiredInt('uid'));
        if(!$user) {
            Protocol::ok(null, null, null, Notice::get()->invalidAccount());
            return ;
        }
        /* 一起加入的所有故事集合  */
        $event = Event::JoinTogetherByUid($data->requiredInt('login_uid'), $data->requiredInt('uid'));
        /* 一起加入的所有小组集合  */
        $groupInfo = Group::JoinTogetherGroupByUid($data->requiredInt('login_uid'), $data->requiredInt('uid'));
        $response = [
            'user' => [
                'uid' => $data->requiredInt('uid'),
                'nickname' => $user['nickname'],
                'avatar' => "profile/avatar/".$user['avatar'].".jpg",
                'gender'=> $user['gender'],
            ],
        ];
        if($event['nums']) {
            $response['event'] = [
                'type' => 0,
                'title' => $event['event_name'],
                'num' => $event['nums'],
                'content' => $event['nums']>1?"共同经历".$event['event_name']."等".$event['nums']."个故事":"共同经历".$event['event_name'].$event['nums']."个故事"
            ];
        } elseif ($groupInfo) {
            $response['event'] = [
                'type' => 1,
                'title' => $groupInfo['name'],
                'num' => $groupInfo['c'],
                'content' => $groupInfo['c'] > 1 ? '共同加入'.$groupInfo['name'].'等'.$groupInfo['c'].'个小组' : '共同加入'.$groupInfo['name'].$groupInfo['c'].'个小组',
            ];
        } else {
            if($data->optional('event_id')) {
                $list = GroupModel::getEventAssociatGroup($data->optional('event_id'), 0, 0x7FFFFFFF);
                foreach ($list as $tmp) {
                    if($data->requiredInt('uid') != $tmp->properties->oper) {
                        $oper[] = UserModel::getUserNickname($tmp->properties->oper);
                    }
                }
                $response['event'] = [
                    'type' => 2,
                    'title' => '',
                    'num' => 0,
                    'content' => Predicates::equals(1, intval($user['gender'])) ? '他是故事成员'.$oper[0].'的朋友' :'她是故事成员'.$oper[0].'的朋友',
                ];
            }
        }
        Protocol::ok($response);
    }

    public function updateProfileAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $user = $this->doGetUserData($data->requiredInt('login_uid'));
        if(!$user) {
            Protocol::ok(null, Notice::get()->invalidAccount());
            return ;
        }
        $nickname = Predicates::isNotEmpty($data->optional('nickname'))?$this->doUpdateUserNickname($data):"";
        $avatar = $_FILES?$this->doUpdateUserAvatar($data->requiredInt('login_uid'), $_FILES['file']):"";
        $gender = Predicates::isNotEmpty($data->optional('gender'))?$this->doUpdateUserGender($data):"";
        $response = [
            'avatar' => $avatar,
            'nickname' => $nickname,
            'gender' => $gender
        ];
        Protocol::ok($response);
        return ;
    }

    private function doUpdateUserGender($data)
    {
        if(Predicates::isNotEmpty($data->optionalInt('gender'))){
            $this->doUpdateOneUserData($data->requiredInt('login_uid'), 'gender', $data->optionalInt('gender'));
            return $data->optionalInt('gender');
        }
        return false;
    }

    private function doUpdateUserNickname($data)
    {
        if(Predicates::isNotEmpty($data->optional('nickname'))){
        	$this->doUpdateOneUserData($data->requiredInt('login_uid'), 'nickname', $data->optional('nickname'));
        	//$this->updateTubePush($data->requiredInt('login_uid'));
        	return $data->optional('nickname');
        }
        return false;
    }

    private function updateTubePush($uid)
    {
        $result = Event::getEventMomentId($uid);
        foreach ($result as $v) {
          Event::tubePush($v['event_id'], $v['moment_id'], Event::GROUP_EVENT_TYPE_MOMENT_DELETE);
        }
        return true;
    }

    private function doUpdateOneUserData($uid, $key, $value)
    {
    	if( Predicates::isEmpty($key) || Predicates::isEmpty($value) ){
    		return false;
    	}
    	$connection = Yii::$app->db;
    	return $connection->createCommand()->update(Us\TableName\USER, [$key => $value], ['uid' => $uid, 'status' => Us\User\STATUS_NORMAL])->execute();
    }

    private function doUpdateUserAvatar($uid, $file)
    {
        $avatarArray = $this->doUploadAvatar($file, $uid);
	    $this->doUpdateOneUserData($uid, 'avatar', $avatarArray['subUrlName'])?$avatarArray['subUrl']:new stdClass();
	    //$this->updateTubePush($uid);
	    return $avatarArray['subUrl'];
    }

    public function updateConfigAction()
    {
        $data = Protocol::arguments();
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            if ($this->doVerifySetting($data->required('setting'))) {
                $user = $this->doGetUserData($data->requiredInt('login_uid'));
                if(!$user) {
                    Protocol::ok(null, Notice::get()->invalidAccount());
                    return ;
                }
                $currentConfig = $this->doHandleJson($data->required('setting'), $data->requiredInt('login_uid'), $data->requiredInt('type'));
                if ($this->doUpdateConfig($data->requiredInt('login_uid'), $data->requiredInt('type'), $currentConfig)) {
                    $response = json_decode($currentConfig, true);
                    $commit = true;
                }
                else {
                    $response = $this->doGetOneUserConfig($data->requiredInt('login_uid'), $data->requiredInt('type'));
                }

                Protocol::ok($response);
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

    private function doHandleJson($setting, $uid , $type)
    {
        $data = json_decode($setting, true);
        $originalSetting = $this->doGetOneUserConfig($uid, $type);
        if (!empty($originalSetting)) {
            $originalSetting[key($data)] = $data[key($data)];
        } else {
            return false;
        }
        $result = json_encode($originalSetting);
        return $result;
    }

    public function getPushJsonAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        $pushJson = $this->getPushJson($data->requiredInt('login_uid'));
        if (!$pushJson) {
            $pushJson = '';
        }
        $result['p']['content'] = $pushJson;
        Protocol::ok($result['p']);
    }

    public function uploadPushJsonAction()
    {
        $data = Protocol::arguments();
        $this->setPushJson($data->optional('content',''), $data->requiredInt('login_uid'));
        Protocol::ok();
    }

    private function pushKey($uid)
    {
        return Us\User\PUSH_JSON.$uid;
    }

    private function getPushJson($uid)
    {
        return Yii::$app->redis->get($this->pushKey($uid));
    }

    private function setPushJson($content, $uid)
    {
        if (!empty($content)) {
            $result = Yii::$app->redis->set($this->pushKey($uid), $content);
        }
        return true;
    }

    private function doUpdateConfig($uid, $type, $setting)
    {
        $connection = Yii::$app->db;
    	return $connection->createCommand()->update(Us\TableName\USER_CONFIG, ['setting' => $setting], ['uid' => $uid, 'type' => $type])->execute();
    }

    private function doVerifySetting($setting)
    {
    	$settingArray = json_decode($setting, true);
    	if (!Predicates::isArray($settingArray)) {
    	    Protocol::ok(null, null, null, Notice::get()->invalidParameter());
    	    return ;
    	}
    	return true;
    }

    private function doGetOneUserConfig($uid, $type)
    {
        $query = new Query;
        $query->select('setting') ->from(Us\TableName\USER_CONFIG) ->where(['uid' => $uid, 'type' => $type]);
        $configData = $query->one();
        if (empty($configData)) {
            return false;
        }
        $result = json_decode($configData['setting'], true);
        return $result;
    }

    public function logoutAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);//验证设备和当前登录状态
        if (Session::delete($data->requiredInt('login_uid'))) {
            Protocol::ok();
        }
        else {
            Protocol::ok(null, Notice::get()->invalidParameter());
        }
    }

    public function webRegisterAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            $data = Protocol::arguments();
            $token = $this->doGetWeChatAccessToken($data->required('code'));
            if (!$token) {
                echo json_encode(["c" => 200, "p" => ["exist" => false], "n" => "", "h" => "code"]);
                return ;
            }
            $user = $this->doGetWeChatUserData($token['access_token'], $token['openid']);
            if (!$user){
            	echo json_encode(["c" => 200, "p" => ["exist" => false], "n" => "", "h" => ""]);
            	return ;
            }
            if ($this->doVerifyRegisterOther($user['unionid'], $data->required('type'))) {
                $model = $this->doCreateUserOther($data, $user);
                if ($model) {
                    /* H5 加入活动或小组*/
                    Group::doJoinEventOrGroup($model->uid, $data->required('target'), $data->required('invitation_code'));
                    $this->doStoreRedisString(Us\Event\INVITE.$model->uid, substr($data->required('invitation_code'), 3), 10);
                    $payload = ['uid' => $model->uid, 'nickname' => $model->nickname, 'avatar' => $user['headimgurl'], 'session_key' => $model->session_key];
                    echo json_encode($payload);
                    $commit = true;
                }
            }
            else {
            	$model = $this->doGetWebUserData($token);
            	if (!$model) {
            		echo json_encode(["c" => 200, "p" => ["exist" => true], "n" => "", "h" => ""]);
            		return ;
            	}
            	/* H5 加入活动或小组 */
            	Group::doJoinEventOrGroup($model['uid'], $data->required('target'), $data->required('invitation_code'));
            	$this->doStoreRedisString(Us\Event\INVITE.$model['uid'], $data->required('invitation_code'), 10);
            	$payload = ['uid' => $model['uid'], 'nickname' => $model['nickname'], 'avatar' => $model['avatar'], 'session_key' => Session::getSession($model['uid'])];
            	echo json_encode($payload);
            	$commit = true;
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

    private function doGetWebUserData($data)
    {
        return $this->doGetUserData($this->doGetWebUserUid($data['unionid']));
    }

    private function doGetWebUserUid($token)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $token, 'type' => Us\User\REGISTER_TYPE_WECHAT, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $userData = $query->one();
        if (empty($userData)) {
        	return false;
        }
        return $userData['uid'];
    }

    private function doGetWeChatAccessToken($code)
    {
    	$url = Us\Config\WECHAT_TOKEN."appid=".Us\Config\WECHAT_APPID."&secret=".Us\Config\WECHAT_SECRET."&grant_type=authorization_code&code=".$code;
    	$payload = Http::sendGet($url);
    	if (isset($payload['errcode'])) {
    		return false;
    	}
    	return $payload;
    }

    private function doRefreshAccessToken($refreshToken)
    {
    	$url = Us\Config\WECHAT_REFRESH_TOKEN."appid=".Us\Config\WECHAT_APPID."&grant_type=refresh_token&refresh_token=".$refreshToken;
    	$payload = Http::sendGet($url);
    	if (isset($payload['errcode'])) {
    	    return false;
    	}
    	return $payload;
    }

    private function doGetWeChatUserData($accessToken, $openId)
    {
    	$url = Us\Config\WECHAT_USER.$accessToken."&openid=".$openId."&lang=zh_CN";
    	$payload = Http::sendGet($url);
    	if (isset($payload['errcode'])) {
    	    return false;
    	}
    	return $payload;
    }

    private function doGetUniqueAccessToken()
    {
        $url = Us\Config\WECHAT_UNIQUE_TOKEN."appid=".Us\Config\WECHAT_APPID."&secret=".Us\Config\WECHAT_SECRET;
        $payload = Http::sendGet($url);
        if (isset($payload['errcode'])) {
            return false;
        }
        return $payload['access_token'];
    }

    private function doSetUniqueAccessToken()
    {
        $token = $this->doGetUniqueAccessToken();
        $this->dostoreRedisString(Us\User\WECHAT_TOKEN, $token, 10);
        return $token;
    }

    private function doVerifyRegisterOther($token, $type)
    {
        $query = new Query;
        $query->select('uid') ->from(Us\TableName\USER_LOGIN) ->where(['token' => $token, 'type' => $type, 'enabled' => Us\User\ACCOUNT_NORMAL]);
        $registered = $query->one();
        if (!empty($registered)) {
            return false;
        }
        return true;
    }

    private function doCreateUserOther($data, $user)
    {
    	$model = $this->doPrepareModelOther($data, $user);
    	$userModel = $this->doUserModel($model);
    	$resultAccount = $this->doUserAccountModel($userModel);
    	$resultDevice = $this->doUserDeviceModel($userModel);
    	$resultConfig = $this->doUserConfigModel($userModel);
    	if ($userModel && $userModel && $resultAccount && $resultDevice && $resultConfig) {
    	    return $userModel;
    	}
    	else {
    	    return NULL;
    	}
    }

    private function doPrepareModelOther($data, $user)
    {
        $model = new stdClass();
        $data->copyRequired(['type', 'platform', 'device_id', 'distributor', 'client_version', 'os_version', 'phone_model'], $model);
        Accessor::wrap($user)->copyRequired(['unionid' => 'token', 'nickname', 'sex' => 'gender', 'nickname'], $model);
        $model->avatar_url = $user['headimgurl']?$user['headimgurl']:"";
        $model->gender = ($model->gender == 2)?0:1;
        $model->os_version = Types::versionToLong($model->os_version);
        $model->client_version = Types::versionToLong($model->client_version);
        $model->reg_ip = ip2long(Protocol::remoteAddress());
        $model->log_ip = ip2long(Protocol::remoteAddress());
        $model->avatar = Us\User\DEFAULT_AVATAR;
        $model->login_time = date("Y-m-d H:i:s");
        $model->reg_time = date("Y-m-d H:i:s");
        $model->phone_model = $this->doGetPhoneModelCode($model->phone_model);
        $model->distributor = $this->doGetDistributorCode($model->distributor);
        $model->secret = $this->doGetUniqueAccessToken();
        return $model;
    }

    public function getJSSDKAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        $data = Protocol::arguments();
        $payload = $this->doGetSignature($data->required('url'));
        echo json_encode($payload);
    }

    private function doGetTicket($accessToken)
    {
    	$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$accessToken;
    	$payload = Http::sendGet($url);
	    if ($payload['errcode']) {
    	    return $this->doReGetTicket();
    	}
    	if ($payload['errcode']) {
    		return false;
    	}
    	return $payload;
    }

    private function doReGetTicket()
    {
    	$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$this->doSetUniqueAccessToken();
    	$payload =  Http::sendGet($url);
    	if ($payload['errcode']) {
    	    return false;
    	}
    	return $payload;
    }

    private function doGetApi_ticket()
    {
    	$ticketData = $this->doGetTicket($this->doGetAccessToken());
    	if ($ticketData['errcode']) {
    		return false;
    	}
    	return $ticketData['ticket'];
    }

    private function doGetAccessToken()
    {
    	$token = Yii::$app->redis->get(Us\User\WECHAT_TOKEN);
    	if (!$token) {
    	    $token = $this->doSetUniqueAccessToken();
    	}
    	return $token;
    }

    private function doGetSignature($url)
    {
        $value = 4;
        $flag = true;
        $noncestr = bin2hex(openssl_random_pseudo_bytes($value, $flag));
        $timestamp = time();
    	$str = "jsapi_ticket=".$this->doGetApi_ticket()."&noncestr=".$noncestr."&timestamp=".$timestamp."&url=".$url;
    	return ['noncestr' => $noncestr, 'timestamp' => $timestamp, 'signature' => sha1($str)];
    }

    public function gearmanUploadAvatarAction()
    {
        $data = Protocol::arguments();
        $avatarData = $this->doUploadAvatar('', $data->requiredInt('uid'), 0, 0, 0, 0, 0, '', $data->required('avatar_url'));
        if (!$avatarData) {
            echo $data->requiredInt('uid')."_".$data->required('avatar_url');
        }
        $result = $this->doUpdateThirdAvatarFromGearman($data->requiredInt('uid'), $avatarData['subUrlName']);
        if ($result) {
            $res = Push::pushUserAvatar($data->requiredInt('uid'), $avatarData['subUrl']);
        }
        echo json_encode($response = ['result' => $result, 'uid'=>$data->requiredInt('uid'), 'avatar' => $avatarData, 'push' => $res]);
    }

    private function doUpdateThirdAvatarFromGearman($uid, $avatar)
    {
        $connection = Yii::$app->db;
        $res = $connection->createCommand()->update(Us\TableName\USER, ['avatar' => $avatar], [
                'uid' => $uid,
                'avatar' => "default",
                'status' => Us\User\STATUS_NORMAL
        ])->execute();
        return $res;
    }

    //查询游客记录帐号
    private function getPlatfromId ($platfromid, $device)
    {
        $query = new Query;
        $select = self::$tableUserRecord.".id, ".self::$tableUserRecord.'.platfrom_id ';
        $where = self::$tableUserRecord.".platfrom_id = '$platfromid' and device = '$device'";
        $recordInfo = $query
        ->select($select)
        ->from(self::$tableUserRecord)
        ->where($where)
        ->one();
        return $recordInfo;
    }

    //更新游客记录帐号
    private function updatePlatfromId($connection, $platfromid, $client_version, $phone_model, $operator, $client_version_code, $device, $os_version,$ip, $mi_regid, $jailbroken, $idfa, $network, $platfrom_token, $create_time)
    {
        $result = $connection->createCommand()->update(self::$tableUserRecord,
            [
                'client_version' => $client_version,
                'model' => $phone_model,
                'client_version_code' => $client_version_code,
                'device' => $device,
                'os_version' => $os_version,
                'ip' => $ip,
                'mi_regid' => $mi_regid,
                'jailbroken' => $jailbroken,
                'idfa' => $idfa,
                'network' => $network,
                'token' => $platfrom_token,
                'create_time' =>$create_time
            ],
            ['platfrom_id' => $platfromid])->execute();


    }

    //写入游客记录帐号
    private  function insertPlatfromId($connection, $platfromid, $client_version, $phone_model, $operator, $client_version_code, $device, $os_version,$ip, $mi_regid, $jailbroken, $idfa, $network, $platfrom_token, $create_time)
    {
        $connection->createCommand()->insert(self::$tableUserRecord,
        [
            'platfrom_id' => $platfromid,
            'client_version' => $client_version,
            'model' => $phone_model,
            'operator' => $operator,
            'client_version_code' => $client_version_code,
            'device' => $device,
            'os_version' => $os_version,
            'ip' => $ip,
            'mi_regid' => $mi_regid,
            'jailbroken' => $jailbroken,
            'idfa' => $idfa,
            'network' => $network,
            'token' => $platfrom_token,
            'create_time' =>$create_time,
        ])->execute();
    }

    //记录游客账号的设备信息
    public function recordPlatfromIdAction()
    {
	    $transaction = Yii::$app->db->beginTransaction();
	    $commit = false;
	    try {
	        $platfromid = Protocol::optional('udid'); //设备ID
	        $client_version = Protocol::optional('client_version'); //客户端版本
	        $phone_model = Protocol::optional('model'); //手机型号
	        $client_version_code = Protocol::optional('client_version_code');
	        $operator = Protocol::optional('operator'); //运营商
	        $device = Protocol::optional('device'); //类型
	        $os_version = Protocol::optional('os_version'); //ios 系统版本
	        $ip = Protocol::optional('ip'); //内网
	        $mi_regid = Protocol::optional('mi_regid'); //小米 token
	        $jailbroken = Protocol::optional('jailbroken');  //是否越狱
	        $idfa = Protocol::optional('idfa');  //广告标识
	        $network = Protocol::optional('network');  //联网类型  WiFi 3G
	        $platfrom_token = Protocol::optional('token');  //设备token

	        if ($device == 'iOS') {
	            $device = 1;
	        } else {
	            $device = 2;
	        }
	        if ($jailbroken == '0') {
	            $jailbroken = 1;
	        } else {
	            $jailbroken = 2;
	        }

	        if (empty($platfromid)) {
	            Protocol::badRequest();
	            return false;
	        }
	        $create_time = date('Y-m-d H:i:s');
	        $connection = Yii::$app->db;
	        $recordInfo = self::getPlatfromId($platfromid, $device);
	        if ($recordInfo) {
	            self::updatePlatfromId($connection, $platfromid, $client_version, $phone_model, $operator, $client_version_code, $device, $os_version,$ip, $mi_regid, $jailbroken, $idfa, $network, $platfrom_token, $create_time) ;
	        } else {
	            self::insertPlatfromId($connection, $platfromid, $client_version, $phone_model, $operator, $client_version_code, $device, $os_version,$ip, $mi_regid, $jailbroken, $idfa, $network, $platfrom_token, $create_time);
	        }
	        $commit = true;
	        Protocol::ok();
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

    public function zhoujieAction()
    {
        die();
        $data = Protocol::arguments();
        $result = UserLoginModel::updateUserLoginEnabled($data->required('login_uid'), 3);
        echo $data->required('login_uid') . " is unlinked!<br>";
    }

    public function webLoginAction()
    {
        $data = Protocol::arguments();
        $token = $this->doGetWeChatAccessToken($data->required('code'));
        if (!$token) {
            Protocol::ok(array());
            return ;
        }
        $user = $this->doGetWeChatUserData($token['access_token'], $token['openid']);
        if (!$user) {
            Protocol::ok(array());
            return ;
        }
        $payload = [];
        if (!$this->doVerifyRegisterOther($user['unionid'], 3)) {
            $model = $this->doGetWebUserData($user);
            $result = $this->doVerifyUserDeviceInfo($model['uid'], $data);
            /* H5 加入活动或小组*/
            $status = Group::doJoinEventOrGroup($model['uid'], $data->required('target'), $data->required('invitation_code'));
            if (Predicates::isNull($status)) {
                Protocol::badRequest();
                return false;
            }
            $this->doStoreRedisString(Us\Event\INVITE.$model['uid'], $data->required('invitation_code'), 10);
            $session = Session::getSession($model['uid']);
            if (!$session) {
                $session = Session::reset($model['uid'], Us\Config\SESSION_EXPIRE);
            }
            $payload = ['uid' => $model['uid'], 'nickname' => $model['nickname'], 'avatar' => $model['avatar'], 'session_key' => $session];
        }
        Protocol::ok($payload);
    }

    public function commitByWeChatAction ()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        $data = Protocol::arguments();
//        Event::isLogin($data);
        $loginUid = $data->requiredInt('login_uid');//登录uid
        $eventId = $data->requiredInt('event_id');//活动Id
        $momentId = $data->required('moment_id');//现在id
        $pictureIds = $data->optional('picture_ids');//上传成功的picture_ids
        $platform = $data->optional('platform');//渠道 0-iphone1-android3-h5
        //$shootTime= Types::unix2SQLTimestamp($data->optionalInt('shoot_time',1000)/1000);//拍摄时间
        $pictureIds = json_decode($pictureIds, true);
        $mediaIds = [];
        foreach ($pictureIds as $mediaInfo) {
            $mediaIds[$mediaInfo['0']] = $mediaInfo['0'];
            $pictureIds[$mediaInfo['0']] = $mediaInfo;
        }
        $accessToken = $this->doGetAccessToken();
        $url = 'http://file.api.weixin.qq.com/cgi-bin/media/get?access_token='.$accessToken.'&media_id=';
        $prefix = 'wechat';
        $weChatFiles = DownloadFile::loadAll($url, $mediaIds, $prefix);
        $uploads = [];
        foreach ($weChatFiles as $mediaId => $srcPath) {
            $uploads[$mediaId] = CosFile::uploadWeChatImage($srcPath, $eventId, $momentId);
        }
        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($connection, $loginUid, $eventId, $momentId, $uploads, $pictureIds) {
            if (Event::doVerifyEventStatus(Event::EVENT_STATUS_LOCK, $eventId)){
                Protocol::forbidden(null, Notice::get()->eventLocked() . ":" . User::getUserNickname(Event::GetEventInfoByEvent($eventId, 'uid')));
            }
            //检测活动状态是否正常,登录人是不是活动成员,动态是不是登录人的
            Event::isEventMemberAndMyMoment($eventId, $loginUid, $momentId);

            if(count($uploads) > 0) {
                foreach ($uploads as $mediaId => $upload) {
                    $shootTime = Types::unix2SQLTimestamp($pictureIds[$mediaId][1]/1000);
                    $values[] = [$eventId, $momentId, $upload['subUrlName'], $shootTime, $upload['size'], $upload['data'], 0];
                }
                $insertPicture = $connection->createCommand()
                    ->batchInsert(Event::$tableMomentPicture,
                        ['event_id', 'moment_id', 'object_id', 'shoot_time', 'size', 'data', 'status'], $values
                    )->execute();
                if ($insertPicture) {
                    /*修改event和moment Live_id  */
                    $liveId = Event::updateLiveId($loginUid, $eventId, $momentId, Event::EVENT_LIVE_OPERATION_COMMIT, 1);
                    $object = Tao::addObject("MOMENT", "mid", $momentId, "uid", $loginUid, "eid", $eventId, "type", 0);
                    /*修改moment为正常状态 start */
                    Event::doEventMomentUpdate($eventId, $momentId, ['status'=>Event::MOMENT_STATUS_NORMAL, 'tao_object_id' => $object->id]);
                    /*修改moment为正常状态 end */
                    /*推送数据start  */
                    Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_NORMAL);
                    /*推送数据end  */
                    /*miPush推送数据start  */
                    MiPush::momentSendMessage($loginUid, $eventId, $momentId, "chuantu", "Us");
                    /*miPush推送数据end  */
                }
            }
        });
        Protocol::ok($accessToken);
    }

     // 红包游戏注册
    public function RedpacketRegisterAction()
    {
        header('Access-Control-Allow-Origin:' . Us\APP_URL_PREFIX);
        $transaction = Yii::$app->db->beginTransaction();
        $commit = false;
        try {
            $data = Protocol::arguments();
            $token = $this->doGetWeChatAccessToken($data->required('code'));
            if (!$token) {
                echo json_encode(["c" => 200, "p" => ["exist" => false], "n" => "", "h" => "code"]);
                return ;
            }
            $user = $this->doGetWeChatUserData($token['access_token'], $token['openid']);
            if (!$user){
                echo json_encode(["c" => 200, "p" => ["exist" => false], "n" => "", "h" => ""]);
                return ;
            }
            if ($this->doVerifyRegisterOther($user['unionid'], $data->required('type'))) {
                $model = $this->doCreateUserOther($data, $user);
                if ($model) {
                    Yii::$app->redis->zadd('us.packet.'.$data->required('invitation_code'), 0, $model['uid']);
                    $payload = ['uid' => $model->uid, 'nickname' => $model->nickname, 'avatar' => $user['headimgurl'], 'session_key' => $model->session_key];
                    echo json_encode($payload);
                    $commit = true;
                }
            }
            else {
                $model = $this->doGetWebUserData($token);
                if (!$model) {
                    echo json_encode(["c" => 200, "p" => ["exist" => true], "n" => "", "h" => ""]);
                    return ;
                }
                Yii::$app->redis->zadd('us.packet.'.$data->required('invitation_code'), 0, $model['uid']);
                $payload = ['uid' => $model['uid'], 'nickname' => $model['nickname'], 'avatar' => $model['avatar'], 'session_key' => Session::getSession($model['uid'])];
                echo json_encode($payload);
                $commit = true;
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

    // 红包游戏登录
    public function RedpacketLoginAction()
    {
        $data = Protocol::arguments();
        $token = $this->doGetWeChatAccessToken($data->required('code'));
        $payload = [];
        if (!$this->doVerifyRegisterOther($token['unionid'], 3)) {
            $model = $this->doGetWebUserData($token);
            $result = $this->doVerifyUserDeviceInfo($model['uid'], $data);
            Yii::$app->redis->zadd('us.packet.'.$data->required('invitation_code'), 0, $model['uid']);
            $session = Session::getSession($model['uid']);
            if (!$session) {
                $session = Session::reset($model['uid'], Us\Config\SESSION_EXPIRE);
            }
            $payload = ['uid' => $model['uid'], 'nickname' => $model['nickname'], 'avatar' => $model['avatar'], 'session_key' => $session];
        }
        Protocol::ok($payload);
    }

    private function doGetRelation($eventId, $loginUid, $uid)
    {
        $groupList = array_merge(GroupModel::getOwnerGroupListByUid($uid, 0, 0x7FFFFFFF), GroupModel::getMemberGroupListByUid($uid, 0, 0x7FFFFFFF));
        $group = array_merge(GroupModel::getOwnerGroupListByUid($loginUid, 0, 0x7FFFFFFF), GroupModel::getMemberGroupListByUid($loginUid, 0, 0x7FFFFFFF));
        $groupId = [];
       foreach($groupList as $temp) {
            foreach ($group as $flag) {
                if(Predicates::equals($temp->to, $flag->to)) {
                    $groupId[] = $temp->to;
                }
            }
        }
        $list = GroupModel::getEventAssociatGroup($eventId, 0, 0x7FFFFFFF);
        foreach ($list as $current) {
            $curr[] = $current->to;
        }
        if($groupId) {
            if(array_intersect($groupId, $curr)) {
                return true;
            } else {
                return NULL;
            }
        }
    }
}
?>
