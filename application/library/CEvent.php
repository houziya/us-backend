<?php
use Yaf\Controller_Abstract;
use yii\db\Query;
class CEvent{
	// 表名
	private static $table_name = 'event';
	private static $User_table_name = 'user';
	private static $Event_user_table_name = 'event_user';
	private static $Moment_picture_table_name = 'moment_picture';
	private static $Event_moment = 'event_moment';
	// 查询字段
	private static $columns = array('start_time', 'create_time' , 'uid' ,'id' ,'name','cover_page');
	private static $Event_user_columns = array('event_id', 'member_uid');
	private static $Moment_picture_columns = array('event_id', 'object_id');
	private static $User_columns = array('nickname');
	//状态定义
	const ACTIVE = 1;
	const DEACTIVE = 0;
	const OFFSET=3;
 	public static function getTableName()
 	{
		return self::$table_name;
	}
	
	public static function getUserByName($condition) 
	{
		$db = new Query;
		$list = $db -> select(self::$User_columns) -> from(self::$User_table_name) -> where($condition) -> all();
		if ($list) {
			return $list[0];
		}
		return array ();
	}
	
	public static function getAllEvents( $start ='' ,$page_size='' , $condition) 
	{
		$db=new Query;
		$list = $db -> select(self::$columns) -> from(self::getTableName()) -> where($condition) -> OrderBy('create_time desc') -> limit($page_size)-> offset($start) -> all();
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
	
	public static function MomentPictureCount($condition='')
	{
	    $db=new Query;
	    $num = $db -> select(self::$Moment_picture_columns) -> from(self::$Moment_picture_table_name) -> where($condition) -> count();
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
	
	public static function EventUserCount($condition='')
	{
	    $db=new Query;
	    $num = $db -> select(self::$Event_user_columns) -> from(self::$Event_user_table_name) -> where($condition) -> count();
	    return $num;
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
	
	public static function search($user_group ,$user_name, $start ='' ,$page_size='' ) 
	{
		$db=new \yii\db\Query;
		$limit ="";
		$where = "";
		if($page_size){
			$limit =" limit $start,$page_size ";
		}
		
		if($user_group >0  && $user_name!=""){
			$where = " where u.user_group = $user_group and u.user_name like '%$user_name%'";
		}else{
			if($user_group>0){
				$where = " where u.user_group=$user_group ";
			}
			if($user_name!=""){
				$where = " where u.user_name like '%$user_name%' ";
			}
		}
		
		$sql = "select * ,coalesce(g.group_name,'已删除') from ".self::getTableName()." u left join ".CUserGroup::getTableName()." g on u.user_group = g.group_id $where order by u.user_id desc $limit";
		$list = $db -> createCommand() -> setSql($sql) -> queryAll();
		if (!empty($list)) {
			foreach($list as &$item){
				$item['login_time'] = CCommon::getDateTime($item['login_time']);
			}
		}
		if ($list) {
			return $list;
		}
		
		return array ();
	}
	
	public static function getUsersByGroup( $group_id ) 
	{
		$db=new \yii\db\Query;
		$condition = array("user_group" => $group_id);
		$list = $db->select( self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
		if ($list) {
			foreach($list as &$item){
				if($item['login_time']==null){
					;
				}else{
					$item['login_time'] = CCommon::getDateTime($item['login_time']);
				}
			}
			
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
	
	public static function checkPassword($user_name, $password) 
	{
		$md5_pwd = md5 ( $password );
		$db=new \yii\db\Query;
		$condition = array("user_name" => $user_name,
							"password" => $md5_pwd,
						);
		$list = $db->select( self::$columns) -> from( self::getTableName()) -> where($condition) -> all();
		if ($list) {
			return $list [0];
		} else {
			return false;
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
	public static function batchUpdateUsers($user_ids,$user_data) 
	{
		if (! $user_data || ! is_array ( $user_data )) {
			return false;
		}
		if(!is_array($user_ids)){
			$user_ids=explode(',',$user_ids);
		}
		
		$db=new \yii\db\Query;
		$condition=array("user_id"=>$user_ids);
		$id = $db -> createCommand() ->update ( self::getTableName(), $user_data, $condition ) -> execute();
		return $id;
	}
	
	public static function modUser($user_id) 
	{
		if (! $user_id || ! is_numeric ( $user_id )) {
			return false;
		}
		$db = new Query;
		$condition = array("uid"=>$user_id);
		$tmp = array('status' => 1);
		$result = $db-> createCommand() -> update ( self::getTableName(),$tmp ,$condition ) -> execute();
		return $result;
	}
	
	public static function count($event_id='',$name='') 
	{
		$condition=array();
	    if ($event_id != '') {
	        $condition[]="id=$event_id";
	    }
	    if ($name != '') {
	        $condition[]="name like '%$name%'";
	    }
	    $condition[]="status != 1";
	    if (empty($condition)) {
	        $condition=array();
	    } else {
	        $condition=implode(' AND ',$condition);
	    }
		$db = new Query;
		$num = $db -> select(self::$columns)-> from(self::getTableName()) -> where($condition) -> count ();
		return $num;
	}
	
	public static function getCountByDate($start_date,$end_date)
	{
	    $db=new Query;
	    $condition = array();
	    if ($start_date != ' 00:00:00') {
	        $condition[]="create_time>='$start_date'";
	    }
	    if ($end_date !=' 00:00:00') {
	        $condition[]="create_time<='$end_date'";
	    }
	    if (empty($condition)) {
	        $condition=array();
	    } else {
	        $condition=implode(' AND ',$condition);
	    }
	    $num = $db->from(self::getTableName())-> where($condition)-> count();
	    return $num;
	}
	
	public static function showPager($link,&$page_no,$page_size,$row_count)
	{
	    $url="";
	    $params="";
	    if($link != ""){
	        $pos = strpos($link,"?");
	        if($pos ===false ){
	            $url = $link;
	        }else{
	            $url=substr($link,0,$pos);
	            $params=substr($link,$pos+1);
	        }
	    }
	     
	    $navibar = "<div class=\"pagination\"><ul>";
	    $offset=self::OFFSET;
	    //$page_size=10;
	    $total_page=$row_count%$page_size==0?$row_count/$page_size:ceil($row_count/$page_size);
	
	    $page_no=$page_no<1?1:$page_no;
	    $page_no=$page_no>($total_page)?($total_page):$page_no;
	    if ($page_no > 1){
	        $navibar .= "<li><a href=\"$url?page_no=1&$params\">首页</a></li>\n <li><a href=\"$url?page_no=".($page_no-1)."&$params \">上一页</a></li>\n";
	    }
	    /**** 显示页数 分页栏显示11页，前5条...当前页...后5条 *****/
	    $start_page = $page_no -$offset;
	    $end_page =$page_no+$offset;
	    if($start_page<1){
	        $start_page=1;
	    }
	    if($end_page>$total_page){
	        $end_page=$total_page;
	    }
	    for($i=$start_page;$i<=$end_page;$i++){
	        if($i==$page_no){
	            $navibar.= "<li><span>$i</span></li>";
	        }else{
	            $navibar.= "<li><a href=\" $url?page_no=$i&$params \">$i</a></li>";
	        }
	    }
	
	    if ($page_no < $total_page){
	        $navibar .= "<li><a href=\"$url?page_no=".($page_no+1)."&$params\">下一页</a></li>\n <li><a href=\"$url?page_no=$total_page&$params\">末页</a></li>\n ";
	    }
	    if($total_page>0){
	        $navibar.="<li><a>".$page_no ."/". $total_page."</a></li>";
	    }
	    $navibar.="<li><a>共".$row_count."条</a></li>";
	    $jump ="";
	    //$jump ="<li><form action='$url' method='GET' name='jumpForm'><input type='text' name='page_no' value='$page_no'></form></li>";
	
	    $navibar.=$jump;
	    $navibar.="</ul></div>";
	
	    return $navibar;
	}
	
	public static function getMomentPictureByEventId($eventId, $status, $limit = '', $momentStatus)
	{
	    if (empty($eventId)) {
	        return false;
	    }
	    $query = new \yii\db\Query;
	    $select = self::$Moment_picture_table_name.".object_id, 
	        ".self::$User_table_name.".nickname, 
	        ".self::$Moment_picture_table_name.".shoot_time, 
	        ".self::$Moment_picture_table_name.".id, 
	        ".self::$Moment_picture_table_name.".content, 
	        ".self::$Moment_picture_table_name.".moment_id,
	        ".self::$Moment_picture_table_name.".status";
	    $where = self::$Moment_picture_table_name.".event_id = ".$eventId." and ".self::$Moment_picture_table_name.".status != ".$status." and ".self::$Event_moment.".status = ".$momentStatus." and ".self::$User_table_name.".status = 0 limit ".$limit;
	    $pictures = $query
	    ->select($select)
	    ->from(self::$User_table_name)
	    ->leftjoin(self::$Event_moment,self::$User_table_name.'.uid = '.self::$Event_moment.'.uid')
	    ->leftjoin(self::$Moment_picture_table_name,self::$Moment_picture_table_name.'.moment_id= '.self::$Event_moment.'.id')
	    ->where($where)
	    ->all();
	    return $pictures;
	}
	
	public static function getTotalPicture($eventId, $status)
	{
	    if (empty($eventId)) {
	        return false;
	    }
	    $where = ['event_id'=>$eventId, 'status'=>$status];
	    $query = new \yii\db\Query;
	    $nums = $query->from(self::$Moment_picture_table_name)
	       ->where($where)
	       ->count();
	    return $nums;
	}
}
