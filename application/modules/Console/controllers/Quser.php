<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class QuserController extends Controller_Abstract
{
    public function UsersAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $commit=false;
        if ($data->optional('method', '') == 'mod' && ! empty ( $data->required('uid') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($data->required('uid') == CUserSession::getUserId()) {
                    throw new InvalidArgumentException(Us\User\CAN_NOT_DO_SELF);
                } else {
                    $user = CQuser::getUserById($data->required('uid'));
                    if ($data -> optional('status') == '1') {
                        $result = CQuser::modUser($data->required('uid'));
                    } else {
                        $result = CQuser::modUser($data->required('uid'),0);
                    }
                    if ($result>=0) {
                        CSysLog::addLog (CUserSession::getUserName(),'MODIFY','QUser',$data->required('uid') ,json_encode($user) );
                        $commit=true;
                    } else {
                        CAdmin::alert("error");
                    }
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                if ($data -> optional('status') == '1') {
                    CCommon::exitWithSuccess ($this,'已冻结','Console/QUser/users');
                    return;
                } else {
                    CCommon::exitWithSuccess ($this,'已解冻','Console/QUser/users');
                    return;
                }
                
            }
        }
        
        if ($data->optional('method', '') == 'cancel' && ! empty ( $data->required('uid') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($data->required('uid') == CUserSession::getUserId()) {
                    throw new InvalidArgumentException(Us\User\CAN_NOT_DO_SELF);
                } else {
                    $user = CQuser::getUserById($data->required('uid'));
                    $result = CQuser::cancelUser($data->required('uid'));
                    if ($result>=0) {
                        CSysLog::addLog (CUserSession::getUserName(),'注销账号','QUser',$data->required('uid') ,json_encode($user) );
                        $commit=true;
                    } else {
                        CAdmin::alert("error");
                    }
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'已注销','Console/Quser/users');
                return;
            }
        }
        
        if ($data->optional('uid') != '' && $data->optional('nickname') != '') {
            $condition="uid = ".$data->optional('uid')." and nickname like '%".$data->optional('nickname')."%'";
            $row_count=CQuser::count($data->optional('uid'),$data->optional('nickname'));
        } elseif ($data->optional('uid') != '' && $data->optional('nickname') == '') {
            $condition=array('uid' => $data->optional('uid'));
            $row_count=CQuser::count($data->optional('uid'),$data->optional('nickname'));
        } elseif ($data->optional('uid') == '' && $data->optional('nickname') != '') {
            $row_count=CQuser::count($data->optional('uid'),$data->optional('nickname'));
            $condition="nickname like '%".$data->optional('nickname')."%'";
        } else {
            $condition='';
            $row_count=CQuser::count();
        }
        
        // START 数据库查询及分页数据
        $page_size=2;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $users=CQuser::getAllUsers($start,$page_size,$condition);
//         $uid=array(1396,1397,1398,1399,1400,1401,1402,1409,1410,1411);
        $uids=array( 
                'owYrptwkiqZ7tbyLtpg1pjy_QkQE',
                'owYrptzF6IECukSI5QjiyBME3V_w',
                'owYrpt5gcJfV7C_l02czxJac1-3w',
                'owYrptzqETV4S09K3gyjAjHll9j4',
                'owYrpt0EaX63792HpYyMTJ7hZv9U',
                'owYrpt8eCBOwb_Jl9RtO_Fe1Wz4E',
                'owYrpt6xRUv9PQi5Ar4gAP6qapAc',
                'owYrptxde-SXjAy64afDwOuqnHNw',
                'owYrptx-AXRFdd2Qw4QmDmw4QD4k',
                'owYrpt5SzJS1n5J-LEHfOv4OGS3Q',
                'owYrpt4tSV_91scczesbXR9NU0lI',
                'owYrpt3_ZEkCO5G87Z7JWwm7QOVY',
                'owYrptxrfbTVSCmHDpaHFtxHBjA8',
                'owYrpt8-isC7uq0tPAt5673JEZhk'
        ); 
        Yii::$app->redis->set('uid',json_encode($uids));
        $uids=json_decode(Yii::$app->redis->get('uid'),true);
//         echo '<pre>';
//         var_dump($uids);die;
        foreach ($users as &$user) {
            $u_condition=array('member_uid' => $user['uid'],'is_deleted' => 0);
            $event_users=CQuser::getEventUser($u_condition);
            $count_event=CQuser::getEventUserCount($u_condition);
            $user['event']=$count_event;
            $login_condition=array();
            if (!empty($event_users)) {
                $user['picture']=0;
                foreach ($event_users as $event_user) {
//                     $p_condition=array('event_id' => $event_user['event_id']);
//                     $p_condition="event_id = ".$event_user['event_id'].' and status !=1';
//                     $p_condition="uid = ".$user['uid'].' and status != 1';
//                     $count_picture=CQuser::getMomentPictureCount($p_condition);
                    $count_picture=CQuser::getUnionCount($user['uid']);
//                     var_dump($count_picture);die;
                    $user['picture']=$count_picture;
                    
                }
            }
            $d_condition=array('uid' => $user['uid']);
            $user_devices=CQuser::getUserDevice($d_condition);
            if (!empty($user_devices)) {
                foreach ($user_devices as &$user_device) {
                    $user['client_version']=Types::longToVersion($user_device['client_version']);
                    $user['os_version']=Types::longToVersion($user_device['os_version']);
                    $c_condition=array('id' => $user_device['phone_model'],'type' => 0);
                    $codes=CQuser::getSystemCode($c_condition);
                    if (!empty($codes)) {
                        foreach ($codes as &$code) {
                            $user['phone_model']=$code['name'];
                        }
                    }
                    
                }
            }
            
            $user_logins=CQuser::getUserLogin($d_condition);
            if (!empty($user_logins)) {
                foreach ($user_logins as &$user_login) {
                    $user['token']=$user_login['token'];
                    $user['type']=$user_login['type'];
                    $user['enabled']=$user_login['enabled'];
                }
            }
        }
//         echo '<pre>';
//         var_dump($users);die;
        $page_html=CStat::showPager("users?uid=".$data->optional('uid')."&nickname=".$data->optional('nickname'),$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,dj");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('users',$users);
        $this->getView()->assign('uids',$uids);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->display('users');
    }
    
    public function DetailAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        
        if ($data->optional('name') != '') {
            $condition=array('name' => $data->optional('name'),'member_uid' => $data->optional('member_uid'),'is_deleted' => 0);
            $row_count=CQuser::getEventUserCount($condition);
        } else {
            $condition=array('member_uid' => $data->optional('member_uid'),'is_deleted' => 0);
            $row_count=CQuser::getEventUserCount($condition);
//             var_dump($row_count);die;
        }
        // START 数据库查询及分页数据
        $page_size=15;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $events=CQuser::getAllEvents($start,$page_size,$condition);
        if (!empty($events)) {
            foreach ($events as &$event) {
                $u_condition="id = ".$event['event_id'].' and status != 1';
                $event_users=CQuser::getEvent($u_condition);
                $event['create_time']=$event_users['create_time'];//获取活动创建时间
                $event['uid']=$event_users['uid'];//创建人id
                $event['name']=$event_users['name'];//活动名称
                $event['cover_page']=$event_users['cover_page'];//活动封面图
                $p_condition="event_id = ".$event['event_id'].' and status != 1';
                $event['count_picture']=CQuser::getMomentPictureCount($p_condition);//获取该活动里的照片数
                $event['my_count_picture']=Event::GetCountPictureByEvent($event['event_id'],$data->optional('member_uid'));
                $event['exposure']=Yii::$app->redis->get('st_invite_'.$event['event_id'])+Yii::$app->redis->get('st_share_'.$event['event_id']);
            }
        }
        
        $commit=false;
        if ($data->optional('method', '') == 'mod' && ! empty ( $data->required('event_id') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $event = CQuser::getEventById($data->required('event_id'));
                $result = CQuser::modEvent($data->required('event_id'));
                $result2 = CQuser::modMoment($event_moment['id']);
                if ($result>=0) {
                    CSysLog::addLog (CUserSession::getUserName(),'MODIFY','QUser',$data->required('event_id') ,json_encode($event) );
                    $commit=true;
                } else {
                    CAdmin::alert("error");
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'已删除','Console/QUser/detail?member_uid='.$data->optional('member_uid'));
                return;
            }
        }
        
        $page_html=CStat::showPager("detail?member_uid=".$data->optional('member_uid'),$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,dj");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('events',$events);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->getView()->assign('_GET',$_GET);
        $this->display('detail'); 
        
    }
    
    public function cancelAction()
    {
        CInit::config($this);
        $data=Protocol::arguments();
//         if ($data->optional('name') != '') {
//             $condition=array('name' => $data->optional('name'),'member_uid' => $data->optional('member_uid'),'is_deleted' => 0);
//             $row_count=CQuser::getEventUserCount($condition);
//         } else {
//             $condition=array('member_uid' => $data->optional('member_uid'),'is_deleted' => 0);
//             $row_count=CQuser::getEventUserCount($condition);
//             //             var_dump($row_count);die;
//         }
    
        // START 数据库查询及分页数据
        $page_size=15;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $events=CQuser::getAllEvents($start,$page_size,$condition);
        if (!empty($events)) {
            foreach ($events as &$event) {
                $u_condition="id = ".$event['event_id'].' and status != 1';
                $event_users=CQuser::getEvent($u_condition);
                $event['create_time']=$event_users['create_time'];//获取活动创建时间
                $event['uid']=$event_users['uid'];//创建人id
                $event['name']=$event_users['name'];//活动名称
                $event['cover_page']=$event_users['cover_page'];//活动封面图
                $p_condition="event_id = ".$event['event_id'].' and status != 1';
                $event['count_picture']=CQuser::getMomentPictureCount($p_condition);//获取该活动里的照片数
                $event['my_count_picture']=Event::GetCountPictureByEvent($event['event_id'],$data->optional('member_uid'));
            }
        }
    
        $commit=false;
        if ($data->optional('method', '') == 'mod' && ! empty ( $data->required('event_id') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $event = CQuser::getEventById($data->required('event_id'));
                $result = CQuser::modEvent($data->required('event_id'));
                $result2 = CQuser::modMoment($event_moment['id']);
                if ($result>=0) {
                    CSysLog::addLog (CUserSession::getUserName(),'MODIFY','QUser',$data->required('event_id') ,json_encode($event) );
                    $commit=true;
                } else {
                    CAdmin::alert("error");
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'已删除','Console/QUser/detail?member_uid='.$data->optional('member_uid'));
                return;
            }
        }
        //         var_dump($events);die;
        $page_html=CStat::showPager("detail?member_uid=".$data->optional('member_uid'),$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,dj");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('events',$events);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->getView()->assign('_GET',$_GET);
        $this->display('detail');
    
    }
}