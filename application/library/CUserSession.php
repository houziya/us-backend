<?php
class CUserSession{

    const SESSION_NAME="c_user_info";

    public static function setSessionInfo($user_info)
    {
        Yii::$app->session[self::SESSION_NAME] = $user_info;
        return true;
    }

    public static function getSessionInfo()
    {
        $user_info = array();
        $user_info = @Yii::$app->session[self::SESSION_NAME];
        return $user_info;
    }

    public static function getUserName()
    {
        $user_name = '';
        $user_name = Yii::$app->session[self::SESSION_NAME]['user_name'];
        return $user_name;
    }

    public static function getUserId()
    {
        $admin_id = '';
        $admin_id = Yii::$app->session[self::SESSION_NAME]['user_id'];
        return $admin_id;
    }

    public static function getRealName()
    {
        $real_name = '';
        $real_name = Yii::$app->session[self::SESSION_NAME]['real_name'];
        return $real_name;
    }

    public static function getUserGroup()
    {
        $purviews = '';
        $purviews = Yii::$app->session[self::SESSION_NAME]['user_group'];
        return $purviews;
    }

    public static function getTemplate()
    {
        $template = '';
        $template = Yii::$app->session[self::SESSION_NAME]['template'];
        return $template;
    }

    public static function clear()
    {
        Yii::$app->session[self::SESSION_NAME] = null;
        return true; 
    }

    public static function reload()
    {
        $current_user_info=self::getSessionInfo();
        $user_info = CUser::getUserById($current_user_info['user_id']);

        if ($user_info['status']!=1) {
            CCommon::jumpUrl("login");
            return;
        }

        //读取该用户所属用户组将该组的权限保存在$_SESSION中
        $user_group = CUserGroup::getGroupById($user_info['user_group']);
        $user_info['group_id']=$user_group['group_id'];
        $user_info['user_role']=$user_group['group_role'];
        $user_info['shortcuts_arr']=explode(',',$user_info['shortcuts']);
        $menu = CMenuUrl::getMenuByUrl('Console/System/setting');
        if (strpos($user_group['group_role'],$menu['menu_id'])) {
            $user_info['setting']=1;
        }
        $user_info['login_time']=CCommon::getDateTime($user_info['login_time']);
        CUserSession::setSessionInfo( $user_info);
    }
}