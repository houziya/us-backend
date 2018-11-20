<?php
use yii\db\Query;

define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';
require_once APP_PATH . '/application/commands/LogParser.php';

try{
    AddMemberForOwner::execute();
}catch (Exception $e) {
    var_dump($e->getMessage());
}

class AddMemberForOwner
{
    public static function execute()
    {
        $lastUid = 1;
        $userList = self::queryUser($lastUid);
        while ($userList) {
            foreach ($userList as $uid) {
                $uid = $uid['uid'];
                $lastUid = $uid;
                $groupList = GroupModel::getOwnerGroupListByUid($uid, 0, 0x7FFFFFFF);
                if ($groupList) {
                    foreach ($groupList as $group) {
                        $gid = $group->to;
                        if (!GroupModel::verifyGroupMember($gid, $uid)) {
                            if (Tao::addAssociation(UserModel::getUserTaoId($uid), "MEMBER", "MEMBER_OF", $gid, "uid", $uid)) {
                                echo $uid."_1\n";
                            }
                        } else {
                            echo $uid."_2\n";
                        }
                    }
                }
            }
            $userList = self::queryUser($lastUid);
        }
    }

    private static function queryUser($uid=null)
    {
        $query = new Query;
        $uid = $uid?$uid:1;
        $query->select('uid') ->from(Us\TableName\USER) ->where(['>', 'uid', $uid]) -> orderBy('uid')->limit(500);
        $user = $query->all();
        return $user;
    }
}