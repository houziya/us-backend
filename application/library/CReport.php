<?php
use	\yii\db\Query;

class CReport{
    const OFFSET=3;
    private static $table_name = Us\TableName\EVENT_REPORT;
    private static $Picturetable_name = Us\TableName\MOMENT_PICTURE;
    private static $Eventtable_name = Us\TableName\EVENT;
    private static $Usertable_name = Us\TableName\USER;
    private static $columns = array('id', 'create_time' , 'data' , 'reporter' , 'uid' , 'status');
    private static $User_columns = array('nickname');
    private static $Event_columns = array('name' , 'uid');
    private static $Picture_columns = array('object_id' , 'event_id' ,'moment_id' , 'status');
    
    public static function getTableName()
    {
        return self::$table_name;
    }
    
    public static function getDatas($start,$page_size,$condition) 
    {
        $db = new Query;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition)->OrderBy('create_time desc')->limit($page_size)->offset($start) -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function getUserName($condition)
    {
        $db = new Query;
//         var_dump(self::$Usertable_name);die;
        $list = $db -> select (self::$User_columns) -> from(self::$Usertable_name) -> where($condition) -> one();
        if ($list) {
            return $list;
        }
        
        return array();
    }
    
    public static function getPicture($condition)
    {
        $db = new Query;
        $list = $db -> select (self::$Picture_columns) -> from(self::$Picturetable_name) -> where($condition) -> one();
        if ($list) {
            return $list;
        }
        
        return array();
    }
    
    public static function getPictureDatas($condition)
    {
        $db = new Query;
        $list = $db -> select (self::$Picture_columns) -> from(self::$Picturetable_name) -> where($condition) -> all();
        if ($list) {
            return $list;
        }
        
        return array();
    }
    
    public static function getEventName($condition)
    {
        $db = new Query;
        $data = self::getPicture($condition);
        $condition = array('id' => $data['event_id']);
        $list = $db -> select (self::$Event_columns) -> from(self::$Eventtable_name) -> where($condition) -> one();
        if ($list) {
            return $list;
        }
        
        return array();
    }
    
    public static function getSearch($condition='')
    {
        $db = new Query;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function getPackageByName($file_name)
    {
        if ( $file_name == "" ) {
            return false;
        }
        $db = new Query;
        $condition['file_name'] = $file_name;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
        }
        return array ();
    }
    
    public static function find($date,$type)
    {
        $db = new Query;
        $condition=array();
        if($date != '') {
        $condition[]="stat_date='$date'";
        }
        if ($type!='') {
            $condition[]=$type;
        }
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
//         var_dump($condition);die;
        $list = $db -> from(self::getTableName()) -> where($condition)-> one();
        if (!empty($list)) {
                $list['data']=json_decode($list['data'],true);
        }
        
        
        if ($list) {
            return $list;
        }
        return array();
    }
    
    public static function count($condition='') 
    {
        $db = new Query;
        $num = $db -> from(self::getTableName()) -> where($condition)-> count();
        return $num;
    }
    
    public static function getPackageDatas($package_id)
    {
        $db = new Query;
        $condition['id'] = $package_id;
        $list = $db -> select(self::$columns)-> from(self::getTableName()) ->where($condition) -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function getReportById($report_id)
    {
        if (! $report_id || ! is_numeric ( $report_id )) {
            return false;
        }
        $db = new Query;
        $condition['id'] = $report_id;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
        }
        return array ();
    }
    
    public static function updateReport($condition,$value,$tableName)
    {
        if (! $condition || ! is_array ( $condition )) {
            return false;
        }
        $db = new Query;
        $result = $db -> createCommand() -> update ( $tableName, $value,$condition ) -> execute();
        return $result;
    }
    
}
?>