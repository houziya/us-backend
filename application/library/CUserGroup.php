<?php
use \yii\db\Query;

class CUserGroup{
    // 表名
    private static $table_name = 'c_user_group';
    // 查询字段
    private static $columns = array('group_id', 'group_name', 'group_role', 'owner_id' , 'group_desc');

    public static function getTableName()
    {
        return self::$table_name;
    }

    //列表 
    public static function getAllGroup() 
    {
        $db = new Query;
        $columns = implode(self::$columns,',');
        $list = $db -> select($columns.',u.user_name as owner_name') -> from(self::getTableName()." g") -> leftjoin(CUser::getTableName()." u","g.owner_id = u.user_id") -> OrderBy('g.group_id') ->all();
        if ($list) {
            return $list;
        }
        return array ();
    }

    public static function addGroup($group_data) 
    {
        if (! $group_data || ! is_array ( $group_data )) {
            return false;
        }
        $db = new Query;
        $id = $db -> createCommand() -> insert ( self::getTableName(), $group_data ) -> execute();
        return $id;
    }

    public static function getGroupById($group_id) 
    {
        if (! $group_id || ! is_numeric ( $group_id )) {
            return false;
        }
        $db = new Query;
        $condition['group_id'] = $group_id;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
        }
        return array ();
    }

    public static function getGroupByName($group_name) 
    {
        if ( $group_name == "" ) {
            return false;
        }
        $db = new Query;
        $condition['group_name'] = $group_name;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
        }
        return array ();
    }

    public static function updateGroupInfo($group_id,$group_data) 
    {
        if (! $group_data || ! is_array ( $group_data )) {
            return false;
        }
        $db = new Query;
        $condition = array("group_id"=>$group_id);
        $id = $db -> createCommand() -> update ( self::getTableName(), $group_data,$condition ) -> execute();
        return $id;
    }

    public static function delGroup($group_id) 
    {
        if (! $group_id || ! is_numeric ( $group_id )) {
            return false;
        }
        $db = new Query;
        $condition = array("group_id" => $group_id);
        $result = $db -> createCommand() -> delete ( self::getTableName(), $condition ) -> execute();
        return $result;
    }

    public static function getGroupForOptions() 
    {
        $group_list = self::getAllGroup ();
        foreach ( $group_list as $group ) {
            $group_options_array [$group ['group_id']] = $group ['group_name'];
        }
        return $group_options_array;
    }

    public static function getGroupUsers($group_id) 
    {
        $db = new Query;
        $columns = implode(self::$columns,',');
        $list = $db -> select($columns.",u.user_id as user_id,u.user_name as user_name,u.real_name as real_name ")-> from(self::getTableName()." g,".CUser::getTableName()." u") ->where("g.group_id = $group_id and g.group_id = u.user_group order by g.group_id,u.user_id") -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
}
