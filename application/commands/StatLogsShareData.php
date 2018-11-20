<?php
/**
 *
 * @explain 活动分享，邀请，添加 计划任务接口   line:516 methodName:eventStatData
 * 数据来源接口 line:44 methodName:uploadLog
 */

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
try{
    StatLogsShareData::eventStatData();
}catch (Exception $e) {
    var_dump($e->getMessage());
}

class StatLogsShareData
{
    const FILE_BASE_PATH = '/usr/local/nginx/logs/nginx/stat/';
    const STAT_EVENT_TYPE=3;
    const IOS_PLATFORM = 0;
    const ANDROID_PLATFORM = 1;

    /*私有路径  */
    public static function path($platform)
    {
        $basePath = self::FILE_BASE_PATH.date('Y-m-d').'/'.$platform.'/';  //数据存储基本路径
        if (!file_exists($basePath)) {
            mkdir(self::FILE_BASE_PATH.date('Y-m-d').'/');
            mkdir(self::FILE_BASE_PATH.date('Y-m-d').'/'.self::IOS_PLATFORM.'/');
            mkdir(self::FILE_BASE_PATH.date('Y-m-d').'/'.self::ANDROID_PLATFORM.'/');
        }
        return $basePath;
    }

    /* 数据库写入  记录时间 */
    public static function recordDate()
    {
        return date('Y-m-d', strtotime('-1 day', time()));
    }

    /* 数据查询路径  计划任务默认是查询上一天的数据*/
    public static function selectPath()
    {
       return self::FILE_BASE_PATH.self:: recordDate().'/';
    }

    /* 数据来源接口 */
    public function uploadLogAction()
    {
        $data = Protocol::arguments();
        $log = json_decode($data->required('log'), true);
        $myfile = fopen(self::doGetPath($data->requiredInt('platform'), $data->requiredInt('login_uid')), "w") or die("Unable to open file!");
        if (fwrite($myfile, json_encode([$data->requiredInt('login_uid') => $log]))) {
            Protocol::ok();
        }
        fclose($myfile);
    }

    /*得到全路径 */
    public static function doGetPath($platform, $uid)
    {
        $path = self::FILE_BASE_PATH.date('Y-m-d').'/'.$platform.'/';
        !is_dir($path) ? mkdir($path, 0777, true) : true;
        return $path.$uid."_".substr(sha1(uuid_create()), 0, 10).".log";
    }

    /**
    *@param $palt:手机平台,0 apple 1 andirod 
    *@explain 文件名列表
    *@return array() 目录下文件名
    *
    */
    public static function handleDir($dir)
    {
        $result = [];
        $path = scandir($dir);
        foreach ($path as $key => $value) {
            if (!in_array($value, array(".", ".."))) {
                if (is_dir($dir.DIRECTORY_SEPARATOR.$value)) {
                    $result[$value] = self::handleDir($dir.DIRECTORY_SEPARATOR.$value);
                } else {
                    $result[] = $value;
                }
            }
        }
        if (empty($result)) {
            Protocol::badRequest('', '', 'empty dir no files');
            return;
        }
        return $result;
    }

    /**
    * 
    * @explain 文件内容列表
    * @return array() 不同手机平台的文件内容列表
    */
    public static function traverseFile()
    {
        $files = self::handleDir(self::selectPath());
        if (empty($files)) {
            Protocol::badRequest('', '', 'openfiles faild');
            return;
        }
        foreach($files as $dirname => $fileList) {
            foreach($fileList as $file){ //遍历文件内容
                $currentPath = self::selectPath().$dirname.'/'.$file; //获取当前文件全路径
                $fileContent[$dirname][] = json_decode(file_get_contents($currentPath), true);
            }
        }
        return $fileContent;
    }

    /**
    *
    * @explain 操作文件内容后累加的列表
    * @return array()
    */
    public static function operationFile()
    {
        $payload = self::initData();//接入迭代数据
        $data = self::traverseFile(); //接入上一步处理后的文件列表
        if (empty($data)) {
            Protocol::badRequest('', '', 'openfiles faild');
            return;
        }
        foreach ($data as $platform=>$v) {
            foreach ($v as $getInfo) { //遍历文件内容
                if (empty($getInfo)) {
                    break;
                }
                foreach ($getInfo as $loginUid=>$statList) {
                    foreach ($statList as $currentCount=>$eventList) {
                        $eventList['uid'] = $loginUid;
                        $payload[$platform][$eventList['uid']][$eventList['a']]++; //当前用户累加计数
                    }
                }
            }
        }
        return $payload;
    }

    /**
    *
    *@explain 累加数据
    *@return array() 当前平台的总数据
    */
    public static function handleUserData()
    {
        foreach (self::operationFile() as $platform=>$sum) {
            foreach($sum as $nums) {
                foreach($nums as $event=>$data) {
                    $dataList[$platform][$event][] = $data;
                }
            }
        }
        return $dataList;
    }

    /**
    *
    *@explain 最终数据结果
    *@return array() 
    */
    public static function getStatResult()
    {
        foreach(self::handleUserData() as $platform => $handle) {
            foreach ($handle as $event =>$res) {
                  $statData[$platform][$event] =array_sum($res);
            }
        }
        $statData['t'] = self::fileOwnerTotal();
        return $statData;
    }

    /**
    *
    *@explain DB操作  计划任务接口
    *@param complete !数据写入事务处理
    */
    public static function eventStatData()
    {
       $commit = false;
       $transaction = Yii::$app->db->beginTransaction();
       try {
           $beginData = intval(date('Ymd', strtotime('-1 day', time())));
           if (self::doDataToDB(self::getStatResult(), $beginData)) {
              $commit = true;
              echo 'complete!';
           } else {
               Protocol::badRequest('', '', 'insert failed');
               return;
           }
       }
       finally {
           if ($commit) {
               $transaction->commit();
           }
           else {
               $transaction->rollback();
           }
       }
    }

    /**
    *
    *@explain to DB
    *@结果集
    */
    public static function doDataToDB($currentData,$beginData)
    {
        $connection = Yii::$app->db;
        $result = $connection->createCommand()->insert(Us\TableName\STAT, [
            'stat_date' => $beginData,
            'create_time' => date("Y-m-d H:i:s"),
            'type' => self::STAT_EVENT_TYPE,
            'data' => json_encode($currentData),
            ])->execute();   //将数据存入到db中
        return $result;
    }

    /**
    *
    *@explain 用户列表 
    *@return array() 所有用户，去除重复
    */
    public static function fileOwner()
    {
        $fileName = self::handleDir(self::selectPath()); //得到文件名列表
        foreach ($fileName as $k=>$userId) {
            foreach ($userId as $prefix) {
               $uidList[$k][] = strstr($prefix, '_', true);
               $fileUserList[$k] = array_unique($uidList[$k]);
            }
        }
        return $fileUserList;
    }

    /**
    *
    *@explain 用户总数
    *@return int 所有用户
    */
    public static function fileOwnerTotal()
    {
        $userTotal = '';
        foreach (self::fileOwner() as $v) {
            $userTotal += count($v);
        }
        return $userTotal;
    }

    /**
    *
    *@explain 迭代数据初始化
    *@return array() 初始化数据
    */
    public static function initData()
    {
        foreach (self::fileOwner() as $k => $mosaic) {
            foreach ($mosaic as $uid) {
                $payload[$k][$uid] = Yii::$app->params['event_type'];
            }
        }
        return $payload;
    }
}
