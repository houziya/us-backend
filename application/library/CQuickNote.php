<?php
/*
 *CQuickNote  Class By Console 
 */
use yii\db\Query;

class CQuickNote
{
    private static $tableName = 'c_quick_note';
    
    private static $columns = 'note_id,  note_content, owner_id';
    
    public static function getNotes($start ='', $page_size='')
    {
        $limit ="";
        if ($page_size) {
            $limit =" limit $start,  $page_size ";
        }
        $sql="select ".self::$columns." ,coalesce(u.user_name,'已删除') as owner_name from ".self::$tableName." q left join ".CUser::getTableName()." u on q.owner_id =  u.user_id order by q.note_id desc $limit";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $list = $command->queryAll();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function addNote($note_data)
    {
        if (! $note_data || ! is_array ( $note_data )) {
            return false;
        }
        $connection = Yii::$app->db;
        $connection->createCommand()->insert(self::$tableName,  $note_data)->execute();
        $code = $connection->getLastInsertID();
        return $code;
    }
    
    public static function getNoteById($note_id)
    {
        if (! $note_id || ! is_numeric ( $note_id )) {
            return false;
        }
        $condition['note_id'] = $note_id;
        $query = new Query;
        $list = $query->select(self::$columns)
            ->from(self::$tableName)
            ->where($condition)
            ->one();
        if ($list) {
        	return $list;
        }
        return array ();
    }
    
    public static function getRandomNote()
    {
        $sql="select min(note_id) min_id , max(note_id)  max_id  from ".self::$tableName;
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $list = $command->queryOne();
        if ($list) {
            $note_id=rand($list['min_id'], $list['max_id']);
            $query = new Query;
            $res = $query->select('note_id, note_content, owner_id')
                 ->from(self::$tableName)
                ->where(['note_id' => $note_id])
                ->one();
            if ($res) {
                return $res;
            }
        }
        return array ();
    }
    
    public static function count($condition = '')
    {
        $query = new Query;
        $num = $query->from(self::$tableName)->count();
        return $num;
    }
    
    public static function updateNote($note_id, $note_data)
    {
        if (! $note_data || ! is_array ( $note_data )) {
            return false;
        }
        $connection = Yii::$app->db;
        $condition=array("note_id"=>$note_id);
        $code = $connection->createCommand()->update(self::$tableName,  $note_data,  $condition)->execute();
        return $code;
    }
    
    public static function delNote($note_id)
    {
        if (! $note_id || ! is_numeric ( $note_id )) {
            return false;
        }
        $connection = Yii::$app->db;
        $condition = array("note_id" => $note_id);
        $result = $connection->createCommand()->delete(self::$tableName,  $condition )->execute();
        return $result;
    } 
}
