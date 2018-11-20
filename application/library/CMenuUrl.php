<?php
/**
 *CModule 后台菜单URL类 /
 */
use yii\db\Query;

class CMenuUrl
{
    public static $tableName = 'c_menu_url';

    private static $columns = 'menu_id, menu_name, menu_url, module_id, is_show, online,shortcut_allowed, menu_desc, father_menu';

    const SESSION_NAME = 'c_menuurl_list';

    public static function getMenuByRole($user_role,$online=1) {
        $url_array = array ();
        if(empty($user_role)) {
            return false;
        }
        $sql ="select * from ".self::$tableName." me ,".CModule::$tableName." mo where me.menu_id in ($user_role) and me.online=$online and me.module_id = mo.module_id and  mo.online=1";
        $connection = Yii::$app->db;
        $command = $connection->createCommand($sql);
        $list = $command->queryAll();
        if ($list) {
            foreach ( $list as $menu_info ) {
                $url_array [] = $menu_info ['menu_url'];
            }
            return $url_array;
        }
        return array ();
    }
    
    public static function getMenuByUrl($url) {
        $url_array = array ();
        $condition = array("menu_url" => $url);
        $query = new Query;
        $menu = $query->select(self::$columns)
            ->from(self::$tableName)
            ->where($condition)
            ->one();
        if ($menu) {
            $module = CModule::getModuleById($menu['module_id']);
            $menu['module_id']=$module['module_id'];
            $menu['module_name']=$module['module_name'];
            $menu['module_url']=$module['module_url'];
            if($menu['father_menu']>0){
                $father_menu=self::getMenuById($menu['father_menu']);
                $menu['father_menu_url'] = $father_menu['menu_url'];
                $menu['father_menu_name'] = $father_menu['menu_name'];
            }
            return $menu;
        }
        return array ();
    }
    
    public static function getListByModuleId($module_id,$type="all" ) {
        if (! $module_id || ! is_numeric ( $module_id )) {
            return array ();
        }
        switch ($type) {
            case "sidebar":
                $sub_condition["is_show"] = 1;
                $sub_condition["online"] =1;
                break;
            case "role":
                $sub_condition["online"] =1;
                break;
            case "navibar":
                $sub_condition["is_show"] = 1;
                $sub_condition["online"] =1;
                break;
            default:
        }
        $sub_condition ["module_id"] = $module_id;
        $query = new Query;
        $list = $query->select(self::$columns)
            ->from(self::$tableName)
            ->where($sub_condition)
            ->all();
        if ($list) {
            return $list;
        }
        return array ();
    }

    public static function getFatherMenuForOptions() {
        $menu_options_array=array("0"=>"无");
        $modules = CModule::getAllModules();
        foreach ($modules as $module) {
            $list = self::getListByModuleId($module['module_id'],'navibar');
            foreach ($list as $menu) {
                $menu_options_array[$module['module_name']][$menu['menu_id']]=$menu['menu_name'];
            }
        }
        return $menu_options_array;
    }

    public static function addMenu($function_data) {
        if (! $function_data || ! is_array ( $function_data )) {
            return false;
        }
        $connection = Yii::$app->db;
        $connection->createCommand()->insert(self::$tableName, $function_data)->execute();
        $code = $connection->getLastInsertID();
        self::clearSession();
        return $code;
    }

    public static function getAllMenus($start ='', $page_size='')
    {
        $condition =array();
        if($page_size){
            $condition =$start.','.$page_size;
        }
        $query = new Query;
        $list = $query->select(self::$columns)
            ->from(self::$tableName)
            ->limit($page_size)
            ->offset($start)
            ->all();
        $session_list = self::getSessionMenus();
        foreach ($list as &$menu) {
            if ($menu['father_menu']>0) {
                $menu['father_menu_name'] = $session_list[$menu['father_menu']]['menu_name'];
            }
        }
        if ($list) {
            return $list;
        }
        return array ();
    }

    public static function clearSession()
    {
    	unset($_SESSION[self::SESSION_NAME]);
    }

    public static function getSessionMenus() 
    {
        if (array_key_exists(self::SESSION_NAME,$_SESSION)) {
            return $_SESSION[self::SESSION_NAME];
        } else {
            $query = new Query;
            $list = $query->select(self::$columns)
            ->from(self::$tableName)
            ->all();
            $new_list=array();
            foreach ($list as $menu) {
            	$new_list[$menu['menu_id']] = $menu;
            }
            foreach ($new_list as $menu_id =>&$menu) {
                if ($menu['father_menu']>0) {
                    $menu['father_menu_name'] = $new_list[$menu['father_menu']]['menu_name'];
                }
            }
            if ($new_list) {
                $_SESSION[self::SESSION_NAME] = $new_list;
            }
            return $new_list;
        }
    }

    public static function search($module_id,$menu_name,$start,$page_size)
    {
        $limit ="";
        $where = "";
        if ($page_size) {
            $limit =" limit $start,$page_size ";
        }
        if ($module_id >0  && $menu_name!="") {
            $where = " where me.module_id=$module_id and me.menu_name like '%{$menu_name}%'";
        } else{
            if ($module_id>0) {
                $where = " where me.module_id=$module_id ";
            }
            if ($menu_name!="") {
                $where = " where me.menu_name like '%{$menu_name}%' ";
            }
        }
        $connection = Yii::$app->db;
        $sql = "select * ,coalesce(mo.module_name,'已删除') from ".self::$tableName." me left join ".CModule::$tableName." mo on me.module_id = mo.module_id $where order by me.module_id,me.menu_id $limit";
        $command = $connection->createCommand($sql);
        $list = $command->queryAll();
        $session_list = self::getSessionMenus();
        
        foreach ($list as &$menu) {
            if ($menu['father_menu']>0) {
                $menu['father_menu_name'] = $session_list[$menu['father_menu']]['menu_name'];
            }
        }
        if ($list) {
            return $list;
        }
        return array ();
    }

    public static function count($condition = '')
    {
        $query = new Query;
        $num = $query->from(self::$tableName)->count();
        return $num;
    }

    public static function countSearch($module_id,$menu_name)
    {
        $condition = array();
        if ($module_id >0  && $menu_name!="") {
            $condition['module_id'] = $module_id;
            $condition = array('like','menu_name',$menu_name);
        } else {
            if($module_id>0) {
                $condition['module_id'] = $module_id;
            }
            if($menu_name!="") {
                $condition=array('like','menu_name',$menu_name);
            }
        }
        $query = new Query;
        $num = $query->from(self::$tableName)
        	->where($condition)
        	->count();
        return $num;
    }

    public static function delMenu($menu_id)
    {
        if (! $menu_id || ! is_numeric ( $menu_id )) {
            return false;
        }
        $connection = Yii::$app->db;
        $condition = array("menu_id"=>$menu_id);
        $result = $connection->createCommand()->delete(self::$tableName, $condition )->execute();
        return $result;
    }

    public static function getMenuById($menu_id)
    {
        if (! $menu_id || ! is_numeric ( $menu_id )) {
            return false;
        }
        $condition = array("menu_id" => $menu_id);
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

    public static function getMenuByIds($menu_ids,$online=null,$shortcut_allowed=null)
    {
        $url_array = array ();
        $privi=explode(',',$menu_ids);
        $sub_condition['menu_id']=$privi;
        if (isset($online)) {
            $sub_condition['online']=$online;
        }
        if (isset($shortcut_allowed)) {
            $sub_condition['shortcut_allowed']=$shortcut_allowed;
        }
        $query = new Query;
        $list = $query->select(self::$columns)
            ->from(self::$tableName)
            ->where($sub_condition)
            ->all();
        if ($list) {
        	return $list;
        }
        return array ();
    }

    public static function updateMenuInfo($menu_id,$function_data)
    {
        if (! $function_data || ! is_array ( $function_data )) {
        	return false;
        }
        $connection = Yii::$app->db;
        $condition=array("menu_id"=>$menu_id);
        $code = $connection->createCommand()->update(self::$tableName, $function_data, $condition)->execute();
        return $code;
    }

    /**
     * 批量修改菜单，如批量修改所属模块
     * menu_ids 可以为无key数组，也可以为1,2,3形势的字符串
     */
    public static function batchUpdateMenus($menu_ids, $function_data)
    {
        if (! $function_data || ! is_array ( $function_data )) {
            return false;
        }
        if (!is_array($menu_ids)) {
            $menu_ids=explode(',',$menu_ids);
        }
        $connection = Yii::$app->db;
        $condition=array("menu_id"=>$menu_ids);
        $code = $connection->createCommand()->update(self::$tableName, $function_data, $condition)->execute();
        return $code;
    }
    
    public static function getMenuByName($menu_name)
    {
        if (! $menu_name || is_numeric ( $menu_name )) {
            return false;
        }
        $condition = array("menu_name" => $menu_name);
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
