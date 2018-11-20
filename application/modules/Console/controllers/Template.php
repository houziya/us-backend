<?php 
use Yaf\Controller_Abstract;

class TemplateController extends Controller_Abstract
{
    const DEFAULT_PIC = 'default1';       //默认图文件名
    const CLEAN_URL_CACHE = 'clean_url_cache'; //ios 定义 清除url缓存
    const CLEAN_COVERPAGE = 'clean_coverpage'; //android 定义 清除封面图缓存
    const CLEAN_ALL = 'clean_all';             //android 定义清除所有缓存
    const OPEN_USER_LOGIN = 'open_user_login'; //android 定义手机登录按钮 默认 1 打开登录按钮
    const CLEAN_AVATAR = 'clean_avatar';       //android 定义默认头像清除操作
    
    /* Replace active cover */
    public function replaceCoverAction()
    {
        CInit::config($this);
        if (Protocol::getMethod() == "POST") {
            try{
                $result = self::replacementProcess($_FILES['coverPage'], CosFile::CATEGORY_EVENT); //得到更换封面图的处理结果
                CSysLog::addLog (CUserSession::getUserName(), 'coverPage push', 'replaceCover', CUserSession::getUserId(), json_encode($result));
                /* 推送数据-start- */
                self::pushData(Us\Config\CMD_IOS, json_encode([self::CLEAN_URL_CACHE => 1])); //ios 推送数据
                self::pushData(Us\Config\CMD_AND, json_encode([self::CLEAN_COVERPAGE => 1, self::CLEAN_ALL=> 0, self::OPEN_USER_LOGIN=> 1])); //Android 推送数据
                /* 推送数据-end- */
                CCommon::exitWithSuccess ($this, '更新活动封面图成功！', 'Console/Template/replaceCover');
                return ;
            } catch(InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            }
        }
        $this->display('replaceCover');
    }
    
    /* Change the default Avatar  */
    public function changeAvatarAction()
    {
        CInit::config($this);
        if (Protocol::getMethod() == 'POST') {
            try{
                $result = self::replacementProcess($_FILES['avatar'], CosFile::CATEGORY_DEFAULT_AVATAR); //得到更换头像的处理结果
                CSysLog::addLog (CUserSession::getUserName(), 'avatar push', 'changeAvatar', CUserSession::getUserId(), json_encode($result));
                /* 推送数据-start- */
                self::pushData(Us\Config\CMD_IOS, json_encode([self::CLEAN_URL_CACHE => 1])); //ios 推送数据
                self::pushData(Us\Config\CMD_IOS, json_encode([self::CLEAN_AVATAR => 1]));    //Android 推送数据
                /* 推送数据-end- */
                CCommon::exitWithSuccess ($this, '更新默认头像成功！', 'Console/Template/changeAvatar');
                return ;
            } catch(InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            }
        }
        $this->display('changeAvatar');
    }
    
    /* 替换默认图流程 */
    private static function replacementProcess($files= '', $cosFileType)
    {
        if ($files['error'] === 0) {
            $delPic = CosFile::delFile(self::DEFAULT_PIC, $cosFileType, Us\Config\QCloud\BUCKET);  //删除原默认头像
            if ($delPic['code'] == 0) {
                $result=CosFile::uploadFile($files, 0, $cosFileType, 0, 0, 0, 0);  //上传新的默认头像
                if (!empty($result['url'])) {
                    return $result;
                } else {
                    throw new InvalidArgumentException(Console\ADMIN\UPLOAD_FAILED);
                }
            } else {
                throw new InvalidArgumentException(Console\ADMIN\DEFAULT_PIC_DEL_FAILED);
            }
        } else {
            throw new InvalidArgumentException(Console\ADMIN\NO_FILES_UPLOAD);
        }
    }
    
    /* 推送相关数据 */
    private static function pushData($node, $data)
    {
        $result = Push::zk($node, $data);
        if (!empty($result)) {
            return true;
        } else {
            throw new InvalidArgumentException(Console\ADMIN\PUSH_FAILED);
        }
    }
}
?>