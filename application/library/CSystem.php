<?php
use yii\db\Query;
class CSystem{
    
    private static $table_name = 'c_system';
    private static $columns = array('key_name', 'key_value');
    
    public static function getTableName()
    {
        return self::$table_name;
    }
    
    public static function set($key_name, $key_value) 
    {
        $db = new Query;
        $key_value= json_encode($key_value);
        $sql = "insert into ".self::getTableName() ." values ('$key_name' ,'$key_value') on duplicate key update key_value = '$key_value'";
        $id = $db -> createCommand() -> setSql($sql) -> execute();
        return $id;
    }
    
    public static function get($key_name) 
    {
        $db = new Query;
        $condition['key_name'] = $key_name;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return json_decode($list[0]['key_value']);
        }
        return null;
    }
}
?>