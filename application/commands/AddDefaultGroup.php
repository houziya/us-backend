<?php
/**
 * 给老数据默认添加两个小组   AddDefaultGroup
 */
use yii\db\Query;
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
    defaultGroup::addGroupFromUid();
} catch (Exception $e) {
   var_dump($e->getMessage());
}
class defaultGroup
{
    public static function addGroupFromUid()
    {
        $connection = Yii::$app->db;
        $data = (new Query())->select('uid,nickname,tao_object_id')
            ->from('user')
            ->where(['status'=>0])
            ->andWhere(['not', ['tao_object_id'=>NULL]])
            ->orderBy('uid')
            ->all();
        foreach ($data as $item) {
            $add = true;
            $info = GroupModel::getOwnerGroupListByUid($item['uid'], 0, 0x7FFFFFFF);
            if(!empty($info)) {
                foreach($info as $flag) {
                    if (@$flag->properties->type) {
                        $add = false;
                    }
                }
                if ($add) {
                    self::doCreateGroup($item);
                    echo $item['uid'].'/n';
                }
            } else {
                self::doCreateGroup($item);
                echo $item['uid'].'/n';
            }
        }
    }
    
    public static function doCreateGroup($item)
    {
        $defaultGroup = explode(',', Us\REGISTER\DEFAULT_GROUP);
        $defaultPic = explode(',', Us\REGISTER\DEFAULT_COVERPAGE);
        foreach($defaultGroup as $key=>$name) {
            $groupInfo = Group::createGroup($item['nickname'].'的'.$name, $item['uid'], $defaultPic[$key]);
            if(!Event::addObjectForDribs(Event::doAddDribs($item['uid']), $groupInfo['id'], $item['uid'], 1)) {
                return false;
            }
        }
        return true;
    }
}
?>
