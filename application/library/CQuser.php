<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
class CQuser{
	// 表名
	private static $table_name = 'user';
	private static $Event_table_name = 'event';
	private static $Event_user_table_name = 'event_user';
	private static $User_device_table_name = 'user_device';
	private static $Moment_picture_table_name = 'moment_picture';
	private static $System_code_table_name = 'system_code';
	private static $User_login_table_name = 'user_login';
	private static $Event_moment_table_name = 'event_moment';
	// 查询字段
	private static $columns = array('uid', 'nickname', 'avatar', 'gender', 'reg_time' , 'status' , 'user_login_id');
	private static $Event_columns = array('create_time', 'uid' , 'id' , 'name' ,'cover_page');
	private static $Event_user_columns = array('event_id', 'member_uid' , 'is_deleted');
	private static $User_device_columns = array('client_version', 'phone_model','uid','os_version');
	private static $Moment_picture_columns = array('event_id', 'object_id');
	private static $System_code_columns = array('name', 'id');
	private static $User_login_columns = array('type', 'token', 'uid' , 'enabled');
	private static $Event_moment_columns = array('id');
	//状态定义
	const ACTIVE = 1;
	const DEACTIVE = 0;
 	public static function getTableName()
 	{
		return self::$table_name;
	}
	
	public static function getUserById($user_id) 
	{
		if (! $user_id || ! is_numeric ( $user_id )) {
			return false;
		}
		
		$db=new Query;
		$condition = array('uid' => $user_id);
		$list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
		if ($list) {
			return $list[0];
		}
		
		return array ();
	}
	
	public static function getAllUsers( $start ='' ,$page_size='' , $condition) 
	{
		$db=new Query;
		$list = $db -> select(self::$columns) -> from(self::getTableName()) -> where($condition) -> OrderBy('reg_time desc') -> offset($start) -> limit($page_size) -> all();
		
		if ($list) {
			return $list;
		}
		
		return array ();
	}
	
	public static function getAllEvents($start ='' ,$page_size='' ,$condition)
	{
	    $db=new Query;
	    $list = $db -> select(self::$Event_user_columns) -> from(self::$Event_user_table_name) -> where($condition) -> offset($start)-> limit($page_size) ->all();
	    if ($list) {
	        return $list;
	    }
	    return array ();
	}
	
	public static function getEvent($condition)
	{
	    $db=new Query;
	    $list = $db -> select(self::$Event_columns) -> from(self::$Event_table_name) -> where($condition) ->all();
	    if ($list) {
	        return $list[0];
	    }
	}
	
	public static function getEventMoment($condition)
	{
	    $db=new Query;
	    $list = $db -> select(self::$Event_moment_columns) -> from(self::$Event_moment_table_name) -> where($condition) ->all();
	    if ($list) {
	        return $list[0];
	    }
	}
	
	public static function getUserLogin($condition='')
	{
	    $db=new Query;
	    $list = $db -> select(self::$User_login_columns) -> from(self::$User_login_table_name) -> where($condition) -> all();
	    if ($list) {
	        return $list;
	    }
	     
	    return array ();
	}
	
	public static function getEventUserCount($condition='')
	{
	    $db=new Query;
	    $num = $db -> select(self::$Event_user_columns) -> from(self::$Event_user_table_name) -> where($condition) -> count();
	    return $num;
	}
	
	public static function getMomentPictureCount($condition='')
	{
	    $db=new Query;
	    $num = $db -> select(self::$Moment_picture_columns) -> from(self::$Moment_picture_table_name) -> where($condition) -> count();
	    return $num;
	
	}
	
	public static function getEventMomentCount($condition='')
	{
	    $db=new Query;
	    $num = $db -> select(self::$Event_moment_columns) -> from(self::$Event_moment_table_name) -> where($condition) -> count();
	    return $num;
	
	}
	
	public static function getUnionCount($uid)
	{
	    $db=new Query;
	    $condition=self::$Event_moment_table_name.".uid = ".$uid.' and '.self::$Event_moment_table_name.'.status != 1 and '.self::$Moment_picture_table_name.'.status != 1';
	    $num = $db -> select('*')
	               -> from(self::$Moment_picture_table_name)
	               -> innerjoin(self::$Event_moment_table_name,self::$Moment_picture_table_name.'.moment_id = '.self::$Event_moment_table_name.'.id') 
	               -> where($condition)
	               -> count();
	    return $num;
	
	}
	
	public static function getSystemCode($condition='')
	{
	    $db=new Query;
	    $list = $db -> select(self::$System_code_columns) -> from(self::$System_code_table_name) -> where($condition) -> all();
	    if ($list) {
	        return $list;
	    }
	    return array ();
	}
	
	public static function getEventUser($condition='')
	{
	    $db=new Query;
	    $list = $db -> select(self::$Event_user_columns) -> from(self::$Event_user_table_name) -> where($condition) -> all();
	     
	    if ($list) {
	        return $list;
	    }
	     
	    return array ();
	}
	
	public static function getUserDevice($condition='')
	{
	    $db=new Query;
	    $list = $db -> select(self::$User_device_columns) -> from(self::$User_device_table_name) -> where($condition) -> all();
	    if ($list) {
	        return $list;
	    }
	     
	    return array ();
	}
	
	public static function checkLogin() 
	{
		$user_info = CUserSession::getSessionInfo ();
		if (empty ( $user_info )) {
			CCommon::jumpUrl("Console/User/login");
			
			return true;
		}
	}
	
	public static function checkActionAccess() 
	{
		$action_url = CCommon::getActionUrl();
		$user_info = CUserSession::getSessionInfo();
		$role_menu_url = CMenuUrl::getMenuByRole ( $user_info['user_role']);
		return true;
		$search_result = in_array ( $action_url, $role_menu_url );
		if (! $search_result) {
			CCommon::exitWithMessage ('您当前没有权限访问该功能，如需访问请联系管理员开通权限','index.php' );
			return true;
		}
	}
	
	public static function updateUser($user_id,$user_data) 
	{
		if (! $user_data || ! is_array ( $user_data )) {
			return false;
		}
		
		$db=new \yii\db\Query;
		$condition=array("user_id"=>$user_id);
		$id = $db -> createCommand() ->update ( self::getTableName(), $user_data, $condition ) -> execute();
		return $id;
	}
	
	/**
	* 批量修改用户，如批量修改用户分组
	* user_ids 可以为无key数组，也可以为1,2,3形势的字符串
	*/
	public static function modUser($user_id,$status=1) 
	{
		if (! $user_id || ! is_numeric ( $user_id )) {
			return false;
		}
		$db = new Query;
		$condition = array("uid"=>$user_id);
		$tmp = array('status' => $status);
		$result = $db-> createCommand() -> update ( self::getTableName(),$tmp ,$condition ) -> execute();
		return $result;
	}
	
	public static function modMoment($moment_id)
	{
	if (! $moment_id || ! is_numeric ( $moment_id )) {
			return false;
		}
	    $db = new Query;
	    $condition = array("id"=>$moment_id);
	    $tmp = array('status' => 1);
	    $result = $db-> createCommand() -> update ( self::$Event_moment_table_name,$tmp ,$condition ) -> execute();
	    return $result;
	}
	
	public static function count($uid='',$name='') 
	{
		$condition=array();
	    if ($uid != '') {
	        $condition[]="uid=$uid";
	    }
	    if ($name != '') {
	        $condition[]="nickname like '%$name%'";
	    }
	    if (empty($condition)) {
	        $condition=array();
	    } else {
	        $condition=implode(' AND ',$condition);
	    }
		$db = new Query;
		$num = $db -> select(self::$columns)-> from(self::getTableName()) -> where($condition) -> count ();
		return $num;
	}
	
	public static function cancelUser($uid)
	{
	    if (! $uid || ! is_numeric ( $uid )) {
	        return false;
	    }
	    $db = new Query;
	    $condition = "uid = $uid";
	    $tokens = $db-> select ('user_login_id') 
	                 -> from(self::$table_name)
	                 -> where($condition) 
	                 -> one();
        if (!empty($tokens)) {
                $tokens['type'] = substr($tokens['user_login_id'],0,strpos($tokens['user_login_id'],'@'));
                $tokens['tokens'] = substr($tokens['user_login_id'],strpos($tokens['user_login_id'],'@'));
                $tokens['token'] = str_replace('@','',$tokens['tokens']);
                $imp = array("uid" => $uid);
                $tmp = array("enabled"=> 0);
                $result = $db-> createCommand() 
                             -> update ( self::$User_login_table_name ,$tmp ,$imp )
                             -> execute();
        } else {
            return false;
        }
	    return $result;
	}
}
