<?php
class CInit
{
    public static function config ($obj)
    {
        if (!isset(Yii::$app->session['c_timezone'])) {
            $timezone = CSystem::get('timezone');
            Yii::$app->session['c_timezone'] = $timezone;
        }
        date_default_timezone_set(Yii::$app->session['c_timezone']);
        
        $no_need_login_page = array("Console/User/block","Console/User/login","Console/User/logout",);
        
        //如果不需要登录就可以访问的话
        $action_url = CCommon::getActionUrl();
        if( CAdmin::checkNoNeedLogin($action_url, $no_need_login_page) ){
            //for login.php logout.php etc....;
        }else{
            if (empty(@Yii::$app->session[CUserSession::SESSION_NAME])) {
                $user_id = CUser::getCookieRemember();
                if ($user_id > 0) {
                    CUser::loginDoSomething($user_id, $obj);
                }
            }
            CUser::checkLogin();
            CUser::checkActionAccess($obj); 
            $current_user_info = '';
            $current_user_info = CUserSession::getSessionInfo();
            $menu = CMenuUrl::getMenuByUrl(CCommon::getActionUrl());
            //如果非ajax请求
            if (stripos($_SERVER['SCRIPT_NAME'],"/ajax") === false) {
                //显示菜单、导航条、模板
                $sidebar = CSideBar::getTree ();
                //是否显示quick note
                if ($current_user_info['show_quicknote']) {
                    CAdmin::showQuickNote($obj);
                }
                $obj->getView()->assign('page_title', $menu['menu_name']);
                $obj->getView()->assign('content_header', $menu);
                $obj->getView()->assign('sidebar', $sidebar);
                $obj->getView()->assign('current_module_id', $menu['module_id']);
                if (array_key_exists('setting',$current_user_info)) {
                    $setting = $current_user_info['setting'];
                } else {
                    $setting = 0;
                }
                $obj->getView()->assign('setting', $setting);
                $obj->getView()->assign('user_info', CUserSession::getSessionInfo());
            }
        }
        $referver_url = '';
        $complete_referver_url = '';
        if (@Yii::$app->session['url']['current_url'])
        {
            $referver_url = @Yii::$app->session['url']['current_url'];
        }
        Yii::$app->session['url'] = array('referver_url' => $referver_url, 'current_url' =>$action_url);
        $obj->getView()->assign ('templates', yii::$app->params['console_templates']);
        $sidebarStatus = @$_COOKIE['sidebarStatus'] == null ? "yes" : $_COOKIE['sidebarStatus'];
        $obj->getView()->assign ('sidebarStatus', $sidebarStatus);
    }
}
