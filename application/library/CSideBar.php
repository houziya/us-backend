<?php
class CSideBar {
    //显示可见菜单
    const SHOW_MENU = 1;

    public static function getTree()
    {
        $user_info = CUserSession::getSessionInfo ();
        //功能菜单
        $data = array ();
        $data = CModule::getAllModules(1);
    
        $user_info = CUserSession::getSessionInfo();
        //用户的权限
        $access = CMenuUrl::getMenuByRole ( $user_info ['user_role'] );
        foreach ( $data as $k => $module ) {
            $list = CMenuUrl::getlistByModuleId ($module ['module_id'],'sidebar' );
            if (! $list) {
                unset ( $data [$k] );
                continue;
            }
            //去除无权限访问的
            foreach ( $list as $key => $value ) {
                if (! in_array ( $value ['menu_url'], @$access )) {
                    unset ( $list [$key] );
                }
            }
            $data [$k] ['menu_list'] = $list;
        }
        return $data;
    }

    public static function getMenuShortCuts() 
    {
        $user_info = CUserSession::getSessionInfo ();
        //功能菜单
        $data = array ();
        $data = CModule::getAllModule ();
        $user_info = CUserSession::getSessionInfo();
        //用户的权限
        $access = CMenuUrl::getMenuByRole ( $user_info ['user_role'] );

        foreach ( $data as $k => $module ) {
            $list = CMenuUrl::getlistByModuleId ('shortcut' , $module ['module_id']);
             if (! $list) {
                unset ( $data [$k] );
                continue;
            }
            //去除无权限访问的
            foreach ( $list as $key => $value ) {
                if (! in_array ( $value ['menu_url'], $access )) {
                    unset ( $list [$key] );
                }
            }
            $data [$k] ['menu_list'] = $list;
        }
        return $data;
    }
}