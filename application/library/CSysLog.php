<?php
use	\yii\db\Query;

class CSysLog{
	
	private static $table_name = 'c_sys_log';
	private static $columns = array('op_id', 'user_name', 'action', 'class_name' , 'class_obj', 'result' , 'op_time');
	
	public static function getTableName()
	{
		return self::$table_name;
	}
	
	public static function addLog($user_name, $action, $class_name , $class_obj ,$result = "") 
	{
		$now_time=time();
		$insert_data = array ('user_name' => $user_name, 'action' => $action, 'class_name' => $class_name ,'class_obj' => $class_obj , 'result' => $result ,'op_time' => $now_time);
		$db = new Query;
		$id = $db -> createCommand() -> insert ( self::getTableName(), $insert_data ) -> execute();
		return $id;
	}
	
	public static function getLogs($class_name,$user_name,$start ,$page_size,$start_date='',$end_date='') 
	{
		$db = new Query;
		$condition = array();
		$where = array();
		if ($class_name != '') {
		    if ($class_name=='ALL') {
		        $class_name='';
		    }else{
			$where[] ="class_name = '$class_name'";
		    }
		}	
		if ($user_name != '') {
			$where[] ="user_name like '%$user_name%'";
		}
		if ($start_date != '' && $end_date != '') {
			$where[]="op_time >=$start_date AND op_time<= $end_date";
		}
		if (empty($where)) {
			$where = array();
		}else {
			$where = implode(' AND ',$where);
		}
// 		$condition["ORDER"] = " op_id desc";
// 		$condition['LIMIT'] = array($start,$page_size);
//         $limit=$start.','.$page_size;
		$list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($where)->OrderBy('op_id desc')->limit($page_size)->offset($start) -> all();
		if (!empty($list)) {
			foreach ($list as &$item){
				$item['op_time']=CCommon::getDateTime($item['op_time']);
			}
		}
		if ($list) {
			return $list;
		}
		return array ();
	}
	
	public static function count($class_name='',$user_name=0) 
	{
		$db = new Query;
		$where = array();
		if($class_name != ''){
		    if($class_name=='ALL'){
		        $class_name=='';
		    }else{
			$where[]= "class_name='$class_name'";
		    }
		}
		if($user_name != ''){
// 			$sub_condition['user_name'] = $user_name;
            $where[]="user_name like '%$user_name%'";
		}
		
		if (empty($where)) {
			$where = array();
		}else {
			$where = implode(' AND ',$where);
		}
// 		$sql = "select count(*) from ".self::getTableName()." where class_name=$class_name and user_name=$user_name";
		$num = $db -> from(self::getTableName()) -> where($where) -> count();
		return $num;
	}
	
	public static function getCountByDate($class_name,$user_name,$start_date,$end_date) 
	{
		$db=new Query;
		$where = array();
		if($class_name != ''){
		    if($class_name=='ALL'){
		        $class_name='';
		    }else{
			$where[]= "class_name='$class_name'";
		    }
		}
		if($user_name != ''){
			$where[]="user_name like '%$user_name%'";
		}
// 		$where["op_time"] = array($start_date,$end_date);
        $where[]="op_time >=$start_date AND op_time<= $end_date";
		$where=implode(' AND ',$where);
// 		var_dump($where);die;
		$num = $db -> from(self::getTableName())->where($where)->count ();
// 		echo $num;die;
		return $num;
	}
}
?>