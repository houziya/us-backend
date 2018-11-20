<?php
use Yaf\Controller_Abstract;

class GroupController extends Controller_Abstract
{
    /* 小组邀请码长度 */
    const CODE_LENGTH = 10;
    const CODE_PREFIX_LENGTH = 2;
    const CODE_PREFIX = 'US';
    
    public function createAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            $group = GroupModel::create($data->required('name'), $data->requiredInt('login_uid'));
            if ($group) {
                /* 创建小组后默认加入一个点点滴滴 */
                Event::addObjectForDribs(Event::doAddDribs($data->requiredInt('login_uid')), $group['id'], $data->requiredInt('login_uid'), 1);
                Protocol::ok(['id' => $group['id'], 'time' => $group['time']*1000, 'name' => $data->required('name')]);
            }
        });
    }

    public function joinAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            if (!$this->doVerifyGroupMember($data->required('gid'), $data->requiredInt('login_uid'))) {
                if (GroupModel::join($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                    Protocol::ok();
                    return ;
                }
            }
            Protocol::badRequest(null, "您已加入小组");
        });
    }

    public function updateProfileAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            if ($this->doVerifyGroupOwner($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                $coverPage = ($data->required('target')=='coverpage')?CosFile::uploadFile($_FILES['file'], $data->requiredInt('login_uid'), CosFile::CATEGORY_GROUP_COVERPAGE):$data->required('value');
                if (is_array($coverPage)) {
                    $coverPage = $coverPage['subUrlName'];
                }
                $group = GroupModel::updateProfile($data->required('gid'), $data->required('target'), $coverPage);
                if ($group) {
                    Protocol::ok(['id' => $group->id, 'name' => $group->properties['name'], 'coverpage' => 'group/coverpage/'.$group->properties['coverpage'].'.jpg']);
                    return ;
                }
            }
            Protocol::badRequest(null, Notice::get()->permissionDenied());
        });
    }

    private function doVerifyGroupOwner($gid, $uid)
    {
    	if (GroupModel::verifyGroupOwner($gid, $uid)) {
    		return true;
    	}
    	return false;
    }

    public function expelAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            if ($this->doVerifyGroupOwner($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                if ($this->doVerifyGroupMember($data->requiredInt('gid'), $data->requiredInt('target'))) {
                    //被删除的活动
                    $event = Group::queryOperEvent($data->requiredInt('gid'), $data->requiredInt('target'));
                    $res = GroupModel::addAuditLog($data->requiredInt('target'), 1, ['gid' => $data->requiredInt('gid'), "oper" => $data->requiredInt('login_uid')]);
                    if (GroupModel::deleteMember($data->requiredInt('gid'), $data->requiredInt('target'))) {
                        if (GroupModel::deleteOperEvent($data->requiredInt('gid'), $data->requiredInt('target'))) {
                            EventModel::exitSpecialEvent(Group::getSpecialEvent($data->requiredInt('gid')), $data->requiredInt('target'));
                            $response = Group::queryGroupProfileByGid($data->requiredInt('gid'));
                            $response['event'] = $event;
                            Protocol::ok($response);
                            return;
                        }
                    }
                }
                Protocol::badRequest(null, Notice::get()->permissionDenied());
            }
            Protocol::badRequest(null, Notice::get()->permissionDenied());
        });
    }

    public function quitAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            $seid = Group::getSpecialEvent($data->requiredInt('gid'));
            if ($this->doVerifyGroupOwner($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                if ($this->ownerQuit($data->requiredInt('login_uid'), $data->requiredInt('gid'))) {
                    Protocol::ok(Group::queryGroupProfileByGid($data->requiredInt('gid'), $seid));
                    $res = GroupModel::addAuditLog($data->requiredInt('login_uid'), 0, ['gid' => $data->requiredInt('gid')]);
                    return ;
                }
            }
            if ($this->doVerifyGroupMember($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                if (GroupModel::deleteMember($data->required('gid'), $data->requiredInt('login_uid'))) {
                    if (GroupModel::deleteOperEvent($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                        EventModel::exitSpecialEvent($seid, $data->requiredInt('login_uid'));
                        Protocol::ok(Group::queryGroupProfileByGid($data->requiredInt('gid'), $seid));
                    }
                }
            } else {
                Protocol::ok();
            }
            $res = GroupModel::addAuditLog($data->requiredInt('login_uid'), 0, ['gid' => $data->requiredInt('gid')]);
        });
    }

    private function ownerQuit($uid, $gid)
    {
        $nextOwner = $this->doGetNextOwner($gid, $uid);
        if ($nextOwner) {
        	if (Tao::addAssociation(UserModel::getUserTaoId($nextOwner), "OWNS", "OWNED_BY", $gid, "uid", $nextOwner)) {
        		if (GroupModel::updateProfile($gid, "owner", $nextOwner)) {
        			if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'MEMBER', 'MEMBER_OF', $gid)) {
        			    if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'OWNS', 'OWNED_BY', $gid)) {
            			    if (GroupModel::deleteOperEvent($gid, $uid)) {
            			        EventModel::exitSpecialEvent($seid, $uid);
            			        return $seid;
            			    }
        			    }
        			}
        		}
        	}
        	return false;
        } else {
            $res = GroupModel::addAuditLog($uid, 3, ['gid' => $gid, "oper" => $uid]);
            if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'OWNS', 'OWNED_BY', $gid)) {
                if (Tao::deleteAssociation(UserModel::getUserTaoId($uid), 'MEMBER', 'MEMBER_OF', $gid)) {
                    if (GroupModel::deleteOperEvent($gid, $uid)) {
                        /* 修改邀请码失效时间 */
                        Group::doUpdateEffectiveCode($gid);
                        EventModel::exitSpecialEvent(Group::getSpecialEvent($gid), $uid, 1);
                        if (GroupModel::deleteSpecialEvent($gid)) {
                            return Tao::deleteObject($gid);
                        }
                    }
                }
            }
        }
        return false;
    }

    private function doGetNextOwner($gid, $uid)
    {
        $member = GroupModel::getGroupMember($gid, 0, 50);
        if ($member) {
            $nextOwner = 0;
            $nextTime = 10000000001;
            array_map(function($v) use(&$nextOwner, &$nextTime, $uid){
                if ($v->timestamp<$nextTime && $v->properties->uid!=$uid) {
                    $nextTime = $v->timestamp;
                    $nextOwner = $v->properties->uid;
                }
            }, $member);
            return $nextOwner;
        }
        return 0;
    }

    private function doVerifyGroupMember($gid, $uid)
    {
        return Execution::withFallback(function() use ($gid, $uid) { return GroupModel::verifyGroupMember($gid, $uid) ? true : false; }, function() { return false; });
    }

    public function profileAction()
    {
        $data = Protocol::arguments();
        //Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            if (GroupModel::verifyGroupUserExist($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                Protocol::ok($this->doQueryGroupInfo($data->requiredInt('gid'), $data->requiredInt('login_uid'), $data->requiredInt('version'), $data->requiredInt('platform')));
            	return ;
            }
            Protocol::notFound(null, "您已不是小组成员，无法访问");
        });
    }

    private function doQueryGroupInfo($gid, $uid=null, $version, $platform)
    {
        $group = GroupModel::getGroupData($gid);
        if (!$group) {
        	return new stdClass();
        }
        $event = $this->doQueryEventInfo($gid, $uid, $version, $platform);
        $coverPage = 'group/coverpage/'.$group->coverpage.'.jpg';
    	return $response = [
	       'group' => [
	           'name' => $group->name, 'coverpage' => $coverPage, 'gid' => $gid, 'owner' => $group->owner,
	           'e_num' => isset($event['e_num'])?$event['e_num']:0,
	           'p_num' => isset($event['p_num'])?$event['p_num']:0,
	           'eid' => Group::getSpecialEvent($gid)?Group::getSpecialEvent($gid):4408,
            ],
	       'member' => $this->doQueryGroupMember($gid),
	       'event' => [
	           'list' => isset($event['event'])?$event['event']:array(),
    	   ],
    	];
    }

    private function doQueryGroupMember($gid)
    {
        $member = GroupModel::getGroupMember($gid, 0, 0x7FFFFFFF);
        $node = GroupModel::getNodeData($gid);
        $owner = $node->properties->owner;
        $response[] = $owner;
        $hash[$owner] = $node->timestamp;
        array_walk($member, function($data, $key) use(&$response, &$hash) {
            $response[] = $data->properties->uid;
            $hash[$data->properties->uid] = $data->timestamp;
        });
        $tmp = UserModel::getUserListData($response, ['uid', 'nickname', 'avatar']);
        foreach ($tmp as $uid => $data) {
            $tmp[$uid]['create_time'] = $hash[$uid];
        }
         usort($tmp, function ($a, $b) {
        	if ($a['create_time']==$b['create_time']) {
        		return 0;
        	}
        	return ($a['create_time']<$b['create_time'])?1:-1;
        });
        $memeberList = [];
        foreach ($tmp as $key => $data) {
        	if ($owner==$data['uid']) {
        		$ownerData = $data;
        		continue;
        	}
        	$memeberList[] = $data;
        }
        array_unshift($memeberList, $ownerData);
        return $memeberList;
    }

    private function doQueryEventInfo($gid, $uid, $version = 0, $platform = 0)
    {
        $eventId = [];
    	$eventList = GroupModel::getGroupAssociatEvent($gid, 0, 0x7FFFFFFF);
    	$hash = [];
    	$specialEid = 0;
    	foreach ($eventList as $k=>$data) {
    	    if(@$data->properties->target) {
    	        $specialEid = $data->properties->eid;
    	        if (($version <= 14 && $platform == 0)  || ($version <= 7 && $platform == 1) || ($platform == 2)) {
	                unset($eventList[$k]);
	                continue;
    	        }
    	    }
	        $eventId[] = $data->properties->eid;
    		$hash[$data->properties->eid] = $data->properties->oper;
    	}
    	$tmp = Event::getEventPic($eventId, $uid, $version, $platform);
    	$response = $tmp;
    	if ($tmp) {
        	foreach ($tmp['event'] as $key => $data) {
        	    @$response['p_num'] += $data['upload_count'];
        	    if ($data['event_id']==$specialEid) {
        	        unset($response['event'][$key]);
        	        continue;
        	    }
        	    @$response['e_num'] ++;
        	    $response['event'][$key]['op_uid'] = $hash[$data['event_id']];
        	    $response['event'][$key]['create_time'] = strtotime($data['create_time'])*1000;
        	    $response['event'][$key]['start_time'] = strtotime($data['start_time'])*1000;
        	}
    	}
    	return $response;
    }

    public function deleteEventAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            if (GroupModel::verifyGroupEvent($data->requiredInt('eid'), $data->requiredInt('gid'))) {
                if ($this->doVerifyOper($data->requiredInt('login_uid'), $data->requiredInt('gid'), $data->requiredInt('eid')) || $this->doVerifyGroupOwner($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                    if (GroupModel::deleteGroupEvent($data->requiredInt('gid'), $data->requiredInt('eid'))) {
                        Protocol::ok(Group::queryGroupProfileByGid($data->requiredInt('gid')));
                        return ;
                    }
                    Protocol::notFound(NULL, '你已被该小组移除');
                    return ;
                } else {
                    Protocol::badRequest(null, Notice::get()->permissionDenied());
                    return ;
                }
            } else {
                Protocol::ok(NULL, '该故事已被移除');
            }
        });
    }

    public function addEventAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            if ($this->doVerifyGroupMember($data->requiredInt('gid'), $data->requiredInt('login_uid')) || $this->doVerifyGroupOwner($data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                if (count(json_decode($data->required('eid'), true)) > Us\Config\LIMIT_EVENT) {
                    Protocol::badRequest(null, "超过添加上限");
                    return ;
                }
                if ($this->doAddEventList($data->required('eid'), $data->requiredInt('gid'), $data->requiredInt('login_uid'))) {
                    /*小组内故事添加修改排序时间戳 */
                    GroupModel::updateProfile($data->requiredInt('gid'), 'updateTime', time());
                    Group::pushAddEvent($data->requiredInt('gid'), $data->requiredInt('login_uid'), 1, 1, $data->required('eid'));
                    Protocol::ok(Group::queryGroupProfileByGid($data->requiredInt('gid')));
            	    return ;
            	}
            }
            Protocol::notFound(null, '你已被该小组移除');
        });
    }

    public function doPushAddEvent($gid, $uid, $type, $eventList)
    {
        $eventList = is_array($eventList)?$eventList:json_decode($eventList, true);
    	foreach ($eventList as $eid) {
    	    Group::pushGroupChanges ($gid, $uid, $type, $eid);
    	}
    }

    private function doAddEventList($eventList, $gid, $uid)
    {
    	$event = json_decode($eventList, true);
    	$eventTaoId = Event::getEventListTaoId($event);
    	foreach ($event as $eid) {
    	    if (!$eventTaoId[$eid] || !$this->doVerifyGroupExistEvent($eid, $gid)) {
        	    Event::addEventObject($eid, $gid, $uid, 1, $eventTaoId[$eid]);
    	    }
    	}
    	return true;
    }

    private function doVerifyGroupExistEvent($eid, $gid)
    {
        return Execution::withFallback(function() use ($eid, $gid) { return GroupModel::verifyGroupEvent($eid, $gid) ? true : false; }, function() { return false; });
    }

    public function doVerifyOper($uid, $gid, $eid)
    {
    	$params = Execution::withFallback(function() use ($gid, $eid) { return GroupModel::getEventInfo($gid, $eid); }, function() { return false; });
    	if ($params && Predicates::equals(intval($params->properties->oper), $uid)) {
    		return true;
    	}
    	return false;
    }

    public function testAction()
    {
        $a = GroupModel::getGroupAssociatEvent(268460, 0, 0x7FFFFFFF);
        var_dump($a);
    }

    public function shareAction()
    {
        $data = Protocol::arguments();
        Execution::autoTransaction(Yii::$app->db, function() use($data) {
            $gid = GroupModel::getGroupIdByCode($data->required('code'));
            if ($gid) {
                Protocol::ok($this->doQueryGroupInfo($gid, null, $data->required('version'), $data->requiredInt('platform')));
                return ;
            }
            Protocol::badRequest(null, "邀请函已过期");
        });
    }
    /**
     * 小组列表
     */
    public function listsAction()
    {
        $data = Protocol::arguments();
        //Auth::verifyDeviceStatus($data);
        /* 判定前缀区分故事邀请码 与小组邀请码 */
        $code = trim($data->optional('invitation_code'));
        $version = $data->required('version');
        $platform = $data->required('platform');
        if (Predicates::equals(self::CODE_LENGTH, strlen($code)) && Predicates::equals(self::CODE_PREFIX, substr($code, 0, 2))) {
            /* 如果是小组邀请码 则加入小组 */
            Group::doJoinEventOrGroup($data->requiredInt('login_uid'), Group::TARGET_GROUP, substr($code, self::CODE_PREFIX_LENGTH));
            $gid= GroupModel::getGroupIdByCode(substr($code, self::CODE_PREFIX_LENGTH));
            /*小组内故事添加修改排序时间戳 */
            GroupModel::updateProfile($gid, 'updateTime', time());
        }
        if(!isset($gid)) {
            $gid = '';
        }
        Execution::autoTransaction(Yii::$app->db, function() use($data, $gid, $code, $version, $platform) {
            $loginUid = $data->requiredInt('login_uid');
   //         $ownerGroup = GroupModel::getOwnerGroupListByUid($data->requiredInt('login_uid'), 0, 0x7FFFFFFF);
            $group = GroupModel::getMemberGroupListByUid($data->requiredInt('login_uid'), 0, 0x7FFFFFFF);
   //         $group = array_merge($ownerGroup, $joinGroup);
            $groupIds = [];
            array_walk($group, function ($item) use (&$groupIds) {
                $groupIds[] = $item->to;
            });
            $groupData = GroupModel::getGroupListData($groupIds);
            $tmp = [];
            array_walk($group, function ($groupNode, $key) use (&$tmp, &$groupData, &$loginUid, &$version, &$platform) {
                $eventNums = GroupModel::getCountEventByGid($groupNode->to) - 1;
                $tmp[$key] = [
                        'id' => $groupNode->to,
                        'name' => $groupData[$groupNode->to]->name,
                        'coverpage' => 'group/coverpage/'.$groupData[$groupNode->to]->coverpage.'.jpg',
                        'time' => $groupData[$groupNode->to]->timestamp * 1000,
                        'jointime' => $groupNode->timestamp * 1000,
                        'updatetime' => (isset($groupData[$groupNode->to]->updateTime) ? $groupData[$groupNode->to]->updateTime : $groupData[$groupNode->to]->timestamp) * 1000,
                        'member' => GroupModel::getCountMemberByGid($groupNode->to),
                        'history' => $eventNums < 0 ? 0 : $eventNums,
                        'moment' => Group::countPic($groupNode->to, $loginUid, $version, $platform),
                ];
            });
            usort($tmp, function($a, $b){
                if ($a['updatetime']==$b['updatetime']) {
                    return 0;
                }
                return ($a['updatetime']<$b['updatetime'])?1:-1;
            });
            $groupList = [];
            $num = 0;
            array_walk($tmp, function($node, $key) use (&$groupList, &$num){
                $groupList['list'][$num++] = $node;
            });
            if (Predicates::equals(self::CODE_LENGTH, intval(strlen($code)))) {
                $groupList['inviteId'] = $gid;
            }
            Protocol::ok(empty($groupList) ? new StdClass : $groupList);
        });
    }

    /**
     * 小组分享,邀请
     */
    public function redirectionAction()
    {
        $url = explode('/', $_SERVER["REQUEST_URI"]);
        if (isset($url)) {
            $code = str_replace("?", "&", $url[2]);
            $type = $url[1] === 'gi' ? "invite" : "share";
            Header("Location: " . Us\APP_URL . "share/group.html?invitation_code=" . $code . "&target=" . $type);
        }
    }
    
    /**
     * 小组邀请
     */
    public function inviteAction()
    {
        $data = Protocol::arguments();
        Auth::verifyDeviceStatus($data);
        $groupTitle = GroupModel::getGroupData($data->required('gid'))->name;
        $inviteInfo = [];
        Execution::autoTransaction(Yii::$app->db, function() use($data, $groupTitle) {
            if ($this->doVerifyGroupMember($data->requiredInt('gid'), $data->requiredInt('login_uid')) || !empty(GroupModel::verifyGroupOwner($data->required('gid'),
                $data->requiredInt('login_uid')))) {
                $code = Event::CreateInvitationCode(8);
                GroupModel::addGroupUser($data->requiredInt('gid'), $data->requiredInt('login_uid'),$code);
                $eventCount = GroupModel::getCountEventByGid($data->requiredInt('gid')) - 1 ;
                $pictureCout = Group::countPic($data->requiredInt('gid'), $data->requiredInt('login_uid'));
                $userNickname = UserModel::getUserNickname($data->requiredInt('login_uid'));
                switch ($data->requiredInt('type')){
                    case 0:
                       $inviteInfo['title'] = '' . $userNickname . '邀请你加入「' . $groupTitle . '」小组';
                       $inviteInfo['introduction'] = '这里记录了我们的' . $eventCount < 0 ? 0 : $eventCount . '个故事' . $pictureCout . '个瞬间';
                        break;
                    case 1:
                       $inviteInfo['title'] = '邀请你加入小组「' . $groupTitle . '」';
                       $inviteInfo['introduction'] = '我们的' . $eventCount < 0 ? 0 : $eventCount . '个故事' . $pictureCout . '个瞬间';
                        break;
                    case 2:
                       $inviteInfo['title'] = '';
                       $inviteInfo['introduction'] = '' . $userNickname . '邀请你加入Us小组「' . $groupTitle . '」，这里记录了我们的' . $eventCount < 0 ? 0 : $eventCount . '个故事，' . $pictureCout . '个瞬间，点击链接即可加入';
                        break;
                    default:
                       $inviteInfo['title'] = '' . $userNickname . '邀请你加入「' . $groupTitle . '」小组';
                       $inviteInfo['introduction'] = '这里记录了我们的' . $eventCount < 0 ? 0 : $eventCount . '个故事' . $pictureCout . '个瞬间';
                }
                $inviteInfo['url'] = Us\APP_URL . "gi/" .$code;
                Protocol::ok($inviteInfo);
                return;
            }
            Protocol::badRequest(null, Notice::get()->permissionDenied());
        });
    }
}

