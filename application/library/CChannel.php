<?php
use	\yii\db\Query;
 
class CChannel{
    const OFFSET=3;
    private static $channel_table_name = Us\TableName\SPREAD_CHANNEL_STAT;
    private static $main_table_name = Us\TableName\SPREAD_MAIN_CHANNEL;
    private static $sub_table_name = Us\TableName\SPREAD_SUB_CHANNEL;
    private static $channel_columns = array('click', 'activation', 'width_ip_activation' , 'effective_activation' , 'registrations' , 'with_device_activation' , 'status' , 'create_time');
    private static $main_columns = array('cid', 'main_channel_name');
    private static $sub_columns = array('id','cid' , 'platform' , 'sub_channel_name' , 'channel_code' , 'link' , 'proportion' , 'unitPrice');
    public static function getTableName()
    {
        return self::$channel_table_name;
    }
    
    public static function unionCount($platform,$start_date = '', $end_date='') 
    {
        $db = new Query;
        $condition = array();
        if (Predicates::isNotEmpty($start_date)) {
            $condition[]=self::getTableName().".summary_day >= '$start_date'";
        }
        if (Predicates::isNotEmpty($end_date)) {
            $condition[]=self::getTableName().".summary_day <= '$end_date'";
        }
        $condition[]="platform=$platform";
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
        $num = $db -> select(self::getTableName().'.*,'.self::$sub_table_name.'.cid,'.self::$sub_table_name.'.platform,'.self::$sub_table_name.'.sub_channel_name,'.self::$sub_table_name.'.channel_code,'.self::$sub_table_name.'.proportion,'.self::$sub_table_name.'.unitPrice')
                   -> from(self::getTableName())
                   -> innerjoin(self::$sub_table_name,self::getTableName().'.sid = '.self::$sub_table_name.'.id')
                   -> where($condition)
                   -> count();
        return $num;
    }
    
    public static function getUnionDatas($platform, $start_date = '', $end_date = '', $start = '', $page_size='')
    {
        $db = new Query;
        $condition = array();
        if (Predicates::isNotEmpty($start_date)) {
            $condition[]=self::getTableName().".summary_day >= '$start_date'";
        }
        if (Predicates::isNotEmpty($end_date)) {
            $condition[]=self::getTableName().".summary_day <= '$end_date'";
        }
        $condition[]="platform=$platform";
        if (empty($condition)) {
            $condition=array();
        } else {
            $condition=implode(' AND ',$condition);
        }
//         var_dump($condition);die;
        $list = $db -> select(self::getTableName().'.*,'.self::$sub_table_name.'.cid,'.self::$sub_table_name.'.platform,'.self::$sub_table_name.'.sub_channel_name,'.self::$sub_table_name.'.channel_code,'.self::$sub_table_name.'.proportion,'.self::$sub_table_name.'.unitPrice')
        -> from(self::getTableName())
        -> innerjoin(self::$sub_table_name,self::getTableName().'.sid = '.self::$sub_table_name.'.id')
        -> where($condition)
        -> OrderBy('activation desc')
        -> offset($start)
        -> limit($page_size)
        -> all();
        
        if ($list) {
            return $list;
        }
        return array();
    }  
    
    public static function updateChannel($start_date = '', $end_date = '', $channel_data)
    {
        if (Predicates::isEmpty($start_date)) {
			return false;
		}
		return Yii::$app->db->createCommand()->update(self::getTableName(), $channel_data, "summary_day = :summary_day", [':summary_day'=>$start_date])->execute();
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