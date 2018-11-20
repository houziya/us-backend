<?php
use	\yii\db\Query;

class CStartImg{
    const OFFSET=3;
    private static $table_name = 'c_startImg';
    private static $columns = array( 'id' , 'images' , 'create_time' , 'operator' , 'code' , 'platform' , 'skip_url' , 'action_type' , 'title' , 'push' , 'duration' ,'can_skip');
    
    public static function getTableName()
    {
        return self::$table_name;
    }
    
    public static function addStartImg($startImg_data)
    {
        if (! $startImg_data || ! is_array ( $startImg_data )) {
            return false;
        }
        $db=new Query;
        $id = $db -> createCommand() -> insert ( self::getTableName(), $startImg_data ) -> execute();
        return $id; 
    }
    
    public static function updateStartImg($startImg_data,$img_id)
    {
        if (! $startImg_data || ! is_array ( $startImg_data )) {
            return false;
        }
    
        $db=new Query;
        $condition=array('id' => $img_id);
        $id = $db -> createCommand() -> update ( self::getTableName(), $startImg_data, $condition ) -> execute();
        return $id;
    }
    
    public static function updatePush($push,$input_data)
    {
        if (! $push || ! is_array ( $push )) {
            return false;
        }
    
        $db=new Query;
        $id = $db -> createCommand() -> update ( self::getTableName(), $push, $input_data ) -> execute();
        return $id;
    }
    
    public static function getDatas($start,$page_size) 
    {
        $db = new Query;
        $condition = array();
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> OrderBy('create_time desc')->limit($page_size)->offset($start) -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function getImgByName($title)
    {
        if ( $title == "" ) {
            return false;
        }
        $db = new Query;
        $condition['title'] = $title;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
        }
        return array ();
    }
    
    public static function search($condition='')
    {
        $db = new Query;
        $list = $db -> from(self::getTableName()) -> where($condition)-> all();
        if ($list) {
            return $list;
        }
        return array();
    }
    
    public static function updateSearch($img_id)
    {
        $db = new Query;
        $list = $db -> from(self::getTableName()) -> where("id != $img_id")-> all();
        if ($list) {
            return $list;
        }
        return array();
    }
    
    public static function find($img_id)
    {
        $db = new Query;
        $condition=array('id' => $img_id);
        $list = $db -> from(self::getTableName()) -> where($condition)-> one();
        if ($list) {
            return $list;
        }
        return array();
    }
    
    public static function findPush($push)
    {
        $db = new Query;
        $list = $db -> from(self::getTableName()) -> where($push)-> one();
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
    
    
}
?>