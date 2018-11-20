<?php
use	\yii\db\Query;

class CStat{
    const OFFSET=3;
    private static $table_name = 'stat';
    private static $columns = array('id', 'stat_date', 'create_time', 'type' , 'data');
    
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
    
    public static function getDatas($start,$page_size,$start_time='',$end_time='',$type='') 
    {
        $db = new Query;
        $condition = array();
        if ($start_time != '') {
            $condition[]="stat_date>=$start_time";
        }
        if ($end_time !='') {
            $condition[]="stat_date<=$end_time";
        }
        if($type!=''){
            $condition[]=$type;
        }
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition)->OrderBy('stat_date desc')->limit($page_size)->offset($start) -> all();
        if (!empty($list)) {
            foreach ($list as &$item){
                $item['data']=json_decode($item['data'],true);
            }
        }
        
        if ($list) {
            return $list;
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
    
    public static function search($start_date,$end_date)
    {
        $db=new Query;
        $condition = array();
        if ($start_date != '') {
            $condition[]="stat_date>='$start_date'";
        }
        if ($end_date !='') {
            $condition[]="stat_date<='$end_date'";
        }
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
        
        $list = $db->from(self::getTableName())->where($condition)->all();
        foreach ($list as &$v) {
            $v['data']=json_decode($v['data'],true);
        }
        if ($list) {
            return $list;
        }
        return array();
    }
    
    public static function getCountByDate($start_date,$end_date,$type) 
    {
        $db=new Query;
        $condition = array();
        if ($start_date != '') {
            $condition[]="stat_date>=$start_date";
        }
        if ($end_date !='') {
            $condition[]="stat_date<=$end_date";
        }
        if ($type!='') {
            $condition[]=$type;
        }
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
// 		var_dump($condition);die;
        $num = $db->from(self::getTableName())->where($condition)->count();
        return $num;
    }
    
    public static function getDateCount($start_date,$type)
    {
        $db=new Query;
        $condition = array();
        if ($start_date != '') {
            $condition[]="stat_date=$start_date";
        }
        if ($type!='') {
            $condition[]=$type;
        }
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
        $num = $db->from(self::getTableName())->where($condition)->count();
        return $num;
    }
    
    public static function getDateDatas($start,$page_size,$start_time='',$type='')
    {
        $db = new Query; 
        $condition = array();
        if ($start_time != '') {
            $condition[]="stat_date=$start_time";
        }
        if($type!=''){
            $condition[]=$type;
        }
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition)->OrderBy('stat_date desc')->limit($page_size)->offset($start) -> all();
        if (!empty($list)) {
            foreach ($list as &$item){
                $item['data']=json_decode($item['data'],true);
            }
        }
    
        if ($list) {
            return $list;
        }
        return array ();
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
}
?>