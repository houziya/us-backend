<?php
use	\yii\db\Query;

class CPackage{
    const OFFSET=3;
    private static $table_name = 'c_package';
    private static $columns = array('id', 'create_time', 'file_name', 'version' , 'description' , 'operator' , 'code' , 'platform' , 'package_size');
    private static $table_name_upgrade = 'c_package_upgrade';
    private static $upgrade_columns = array('id', 'update_time', 'skip_url' , 'descs' , 'code' , 'platform');
    const IOS_PLATFORM = 1;
    const ANDROID_PLATFORM = 0;
    public static function getTableName()
    {
        return self::$table_name;
    }
    
    public static function addPackage($package_data)
    {
        if (! $package_data || ! is_array ( $package_data )) {
            return false;
        }
        
        $db=new Query;
        $id = $db -> createCommand() -> insert ( self::getTableName(), $package_data ) -> execute();
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
    
    public static function getSearch($condition='')
    {
        $db = new Query;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function getPackageByName($file_name)
    {
        if ( $file_name == "" ) {
            return false;
        }
        $db = new Query;
        $condition['file_name'] = $file_name;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
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
    
    public static function getPackageDatas($package_id)
    {
        $db = new Query;
        $condition['id'] = $package_id;
        $list = $db -> select(self::$columns)-> from(self::getTableName()) ->where($condition) -> all();
        if ($list) {
            return $list;
        }
        return array ();
    }
    
    public static function getPackageById($package_id)
    {
        if (! $package_id || ! is_numeric ( $package_id )) {
            return false;
        }
        $db = new Query;
        $condition['id'] = $package_id;
        $list = $db -> select (self::$columns) -> from(self::getTableName()) -> where($condition) -> all();
        if ($list) {
            return $list [0];
        }
        return array ();
    }
    
    public static function delPackage($package_id)
    {
        if (! $package_id || ! is_numeric ( $package_id )) {
            return false;
        }
        $db = new Query;
        $condition = array("id" => $package_id);
        $result = $db -> createCommand() -> delete ( self::getTableName(), $condition ) -> execute();
        return $result;
    }
    /* 得到所有安装包数据 */
    public static function getNewsDataByDb()
    {
        $query = new yii\db\Query;
        $codeList = $query->from(self::$table_name_upgrade)->all();
        foreach ($codeList as $v) {
            $forced = json_decode($v['code'], true);
            if (array_key_exists('a', $forced)) {
                $codeList[] = explode(',', $forced['a']);
            } else {
                $codeList[] = explode(',', $forced['f']);
            }
        }
        return $codeList;
    }
    
    //得到最新版本
    public static function getNewsVersionByDb($platform)
    {
        $connection = Yii::$app->db;
        $childSql = "select max(id) from ".self::$table_name." where platform=$platform";
        $sql = "select version from ".self::$table_name." where id in ($childSql)";
        $list = $connection->createCommand($sql)->queryOne();
        return $list['version'];
    }
    
    /* 得到所有版本号 */
    public static function doHandlePackageByType($platform)
    {
        $query = new yii\db\Query;
        $packageList = $query->from(self::$table_name)->where(['platform'=>$platform])->all();
        if (empty($packageList)) {
            $list = '';
        }
        foreach ($packageList as $v) {
            $list[] = $v['code'];
        }
        return $list;
    }
    
    /* 根据平台去存入最新升级数据 */
    public static function doHandleDbByType($data, $functionData)
    {
        switch($data->required("platform")) {
            case self::IOS_PLATFORM:
                return self::doHandleDb($functionData, 4);
                break;
            case self::ANDROID_PLATFORM:
                return self::doHandleDb($functionData, 5);
                break;
            default:
                Protocol::badRequest();
        }
    }
    
    /*操作结果数组  */
    public static function doHandleCodeByList($forcedCode, $list, $platform)
    {
        if (empty($forcedCode)) {
            $codeList = json_encode(['a'=>0, 'q'=>implode(',', $list), 'v'=>self::getNewsVersionByDb($platform)]);         //空字符 表示全为可选升级
        } elseif ($forcedCode == $list){
            $codeList = json_encode(['a'=>1, 'q'=>implode(',', $list), 'v'=>self::getNewsVersionByDb($platform)]);        //'1' 表示全为强制升级
        } else {
            $codeList = json_encode(['f'=>implode(',', $forcedCode), 'q'=>implode(',', $list), 'v'=>self::getNewsVersionByDb($platform)]);          //既存在可选，也存在强制, 则存入json字符串
        }
        return $codeList;
    }
    
    /* 最新修改数据入库 */
    public static function doHandleDb($functionData, $id)
    {
        $connection = Yii::$app->db;
        return  $connection->createCommand()->update(self::$table_name_upgrade, $functionData, ['id'=>$id])->execute();
    }
    
    public static function findPlatform($platform)
    {
        $db = new Query;
        $list = $db -> select(self::$upgrade_columns) -> from(self::$table_name_upgrade) -> where($platform)-> one();
        if ($list) {
            return $list;
        }
        return array();
    }
    
    public static function pushConfigAction($type,$app_code,$app_version,$app_desc,$banner_code,$banner_url,$banner_type,$banner_skip_url,$banner_title,$start_ios4,$start_ios5,$start_ios6,$start_ios6s,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data)
    {
        $response = [];
        $cfg = [
        "til" => ["fee9458c29cdccf10af7ec01155dc7f0"],
        "edf" => 1,
        "etl" => self::doReadJsonFile("/conf/event_layout.json"),
        "etd" => self::doReadJsonFile("/conf/event_default_data.json"),
        ];
        $response['cfg'] = Push::zk("/moca/spread/subscription/cfg", json_encode($cfg));
        $sysinfo_ios = [
        "app_info" => [
        "type" => $type,
        "code" => $app_code,
        "version" => $app_version,
        "desc" => $app_desc,
        ],
        "banner_info" => [
        "enable_version" => $banner_code,
        "img_url" => "images/splash/".$banner_url.".jpg",
    
        "action" => [
        "type" => $banner_type,
        "url" => $banner_skip_url,
        "title" => $banner_title,
        ],
    
        ],
    
        "launch_info" => [
        "img_4_url" => "images/splash/".$start_ios4.".jpg",
        "img_5_url" => "images/splash/".$start_ios5.".jpg",
        "img_6_url" => "images/splash/".$start_ios6.".jpg",
        "img_6s_url" => "images/splash/".$start_ios6s.".jpg",
        "action" => [
        "type" => $start_type,
        "url" => $start_skip_url,
        "title" => $start_title,
        ],
        "duration" => $start_duration,
        "can_skip" => $start_can_skip,
        ],
        
        "guide_link" => [
            "party" => $party_data,
            "travel" => $travel_data,
            "wedding" => $wedding_data
        ]
        ];
        $response['sysinfo_ios'] = Push::zk("/moca/spread/subscription/sysinfo.ios", json_encode($sysinfo_ios));
        return $response['sysinfo_ios'];
        
    }
    
    public static function pushAndroidConfigAction($type,$app_code,$app_version,$app_desc,$app_url,$start_code,$start_android,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data)
    {
        $sysinfo_android = [
            "app_info" => [
                "type" => $type,
                "code" => $app_code,
                "version" => $app_version,
                "desc" => $app_desc,
                'app_url' => $app_url,
            ],
    
            "launch_info" => [
                "enable_version" => $start_code,
                "img_url" => "images/splash/".$start_android.".jpg",
                "action" => [
                    "type" => $start_type,
                    "url" => $start_skip_url,
                    "title" => $start_title,
                ],
                "duration" => $start_duration,
                "can_skip" => $start_can_skip,
            ],
            
            "guide_link" => [
                "party" => $party_data,
                "travel" => $travel_data,
                "wedding" => $wedding_data
            ]
			
        ];
        $response['sysinfo_android'] = Push::zk("/moca/spread/subscription/sysinfo.android", json_encode($sysinfo_android));
        return $response['sysinfo_android']; 
    }
    
	public static function pushCfgAndroidConfigAction($message_data,$enable_data)
	{
		$response = [];
        $cfg = [
            "til" => ["fee9458c29cdccf10af7ec01155dc7f0"],
// //             "edf" => 1,
            "etl" => self::doReadJsonFile("/conf/event_layout.json"),
            "etd" => self::doReadJsonFile("/conf/event_default_data.json"),
            "pbf" => [
                "message" => $message_data,
                "enable" => $enable_data
            ],
            "gsme" => Us\Config\LIMIT_EVENT,                       //添加已经故事到小组上限
            "ssmp" => 12,                                          //分享故事选择照片上限
            "pdsd" => 1000,      //照片选择器中的时间比较的毫秒精度
        ];
		// echo json_encode($cfg);die;
        $response['cfg'] = Push::zk("/moca/spread/subscription/cfg", json_encode($cfg));
		return $response['cfg'];
	}
	
    public static function doReadJsonFile($fileName)
    {
        if (empty($fileName)) {
            return false;
        }
        $jsonFile = APP_PATH . $fileName;
        $template = trim(stripslashes(file_get_contents($jsonFile)));
        $template = preg_replace("/\s/","",$template);
        return $template;
    }
}
?>