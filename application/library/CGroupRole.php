<?php
class CGroupRole {
    public static function getGroupRoles($group_id)
    {
        if (! $group_id || ! is_numeric ( $group_id )) {
            return false;
        }
        $data = CModule::getAllModules (1);
        foreach ( $data as $k => $module ) {
            $list = CMenuUrl::getListByModuleId ($module ['module_id'] ,"role");
            foreach ( $list as $menu ) {
                $data [$k] ['menu_info'][$menu ['menu_id']] = $menu ['menu_name'];
            }
        }
        return $data;
    }
    
    public static function getGroupForOptions()
    {
        $group_list = CUserGroup::getAllGroup ();
        foreach ( $group_list as $group ) {
           $group_options_array [$group ['group_id']] = $group ['group_name'];
        }
        return $group_options_array;
    }
}