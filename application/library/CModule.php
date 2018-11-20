<?php
/**
 *CModule/
 */
use yii\db\Query;

class CModule
{
	public static $tableName = 'c_module';

	private static $columns = 'module_id, module_name, module_url, module_sort, module_desc, module_icon, online';

	public static function getAllModules($is_online=null)
	{	
		$conditionOrder = 'module_sort ASC, module_id ASC';
		$query = new Query;
		if(isset($is_online)){
			$conditionAnd=array("online"=>$is_online);
			$list = $query->select(self::$columns)
			->from(self::$tableName)
			->where($conditionAnd)
			->orderBy($conditionOrder)
			->all();			
		}
		$list = $query->select(self::$columns)
			->from(self::$tableName)
			->orderBy($conditionOrder)
			->all();
		if ($list) {
			return $list;
		}
        return array ();
	}
	
	public static function addModule($module_data)
	{
		if (! $module_data || ! is_array ( $module_data )) {
			return false;
		}
		$connection = Yii::$app->db;
        $connection->createCommand()->insert(self::$tableName, $module_data)->execute();
        $code = $connection->getLastInsertID();
        return $code;
	}
	
	public static function getModuleById($module_id)
	{
		if (! $module_id || ! is_numeric ( $module_id )) {
			return false;
		}
		$condition['module_id'] = $module_id;
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
	
	public static function getModuleByName($module_name)
	{
		if (! $module_name || is_numeric ( $module_name )) {
			return false;
		}
		$condition['module_name'] = $module_name;
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
	
	public static function getModuleMenu($module_id)
	{
		if (! $module_id || ! is_numeric ( $module_id )) {
			return false;
		}
		$connection = Yii::$app->db;
		$sql="select * from ".self::$tableName." m,".CMenuUrl::$tableName." u where m.module_id = $module_id and m.module_id = u.module_id order by m.module_id,u.menu_id";
		$command = $connection->createCommand($sql);
		$rows = $command->queryAll();
		if ($rows) {
			return $rows[0];
		}
		return array ();
	}
	
	public static function updateModuleInfo($module_id,$module_data)
	{
		if (! $module_data || ! is_array ( $module_data )) {
			return false;
		}
		$connection = Yii::$app->db;
		$condition=array("module_id"=>$module_id);
		$code = $connection->createCommand()->update(self::$tableName, $module_data, $condition)->execute();
		return $code;
	}
	
	public static function delModule($module_id)
	{
		if (! $module_id || ! is_numeric ( $module_id )) {
			return false;
		}
		$connection = Yii::$app->db;
		$condition = array("module_id"=>$module_id);
		$result = $connection->createCommand()->delete(self::$tableName, $condition )->execute();
		return $result;
	}
	
	public static function getModuleForOptions()
	{
		$module_options_array = array ();
		$module_list = self::getAllModules (1);
		foreach ( $module_list as $module ) {
			$module_options_array [$module ['module_id']] = $module ['module_name'];
		}
		return $module_options_array;
	}
	
	public static function getModuleByUrl($module_url)
	{
	    if (! $module_url || is_numeric ( $module_url )) {
	        return false;
	    }
	    $condition['module_url'] = $module_url;
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
}
