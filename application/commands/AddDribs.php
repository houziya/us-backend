<?php
/**
 * 给老用户组默认添加点滴   AddDribs
 */
use yii\db\Query;
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

try {
    addDribs::getDribsInGroup();
} catch (Exception $e) {
   var_dump($e->getMessage());
}

class addDribs
{
    /* 在小组内添加点滴 */
    public static function getDribsInGroup()
    {
        $connection = Yii::$app->db;
        $current = 0;
        $stat = [];
        $data = (new Query())->select('uid,nickname,tao_object_id')
            ->from('user')
            ->where(['status'=>0])
            ->andWhere(['not', ['tao_object_id'=>NULL]])
            ->orderBy('uid')
            ->all();
        foreach ($data as $item) {
            $info = GroupModel::getOwnerGroupListByUid($item['uid'], 0, 0x7FFFFFFF);
            if (!empty($info)) {
                foreach($info as $flag) {
                    $main = true;
                    $gid = $flag->to;
                    $dribs = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
                    $stat[] = $dribs;
                    foreach ($dribs as $temp) {
                        if(@$temp->properties->target) {
                            $main = false;
                        }
                    }
                    if ($main) {
                       $current += 1;
                       //Event::addObjectForDribs(Event::doAddDribs($item['uid']), $gid, $item['uid'], 1);
                       echo $item['uid'].'-'.$gid.' /n ';
                    }
                }
            }
        }
        echo ' 小组总数 : '.count($stat).' 当前已跑小组点滴数 : '.$current;
    }
}
