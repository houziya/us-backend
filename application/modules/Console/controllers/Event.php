<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
use \yii\db\Query;

class EventController extends Controller_Abstract
{
    const STATUS_MOMENT_DELETE = 1;//现场动态删除状态
    const STATUS_PICTURE_DELETE = 1;
    const EVENT_STATUS_LOCK = 2;
    public static $tableMomentPicture = Us\TableName\MOMENT_PICTURE;
    public static $tableEventMoment = Us\TableName\EVENT_MOMENT;
    public static $tableEventUser = Us\TableName\EVENT_USER;
    public static $tableUser = Us\TableName\USER;
    public static $tableEvent = Us\TableName\EVENT;
    public static $tableMomentLog = 'c_moment_log';

    public function EventsAction()
    {
        CInit::config($this);
        $data=Protocol::arguments();
        if ($data->optional('event_id') != '' && $data->optional('name') != '') {
            $row_count=CEvent::count($data->optional('event_id'),$data->optional('name'));
            $condition="id = ".$data->optional('event_id')." and name like '%".$data->optional('name')."%' and status != 1" ;
        } elseif ($data->optional('event_id') != '' && $data->optional('name') == '') {
            $row_count=CEvent::count($data->optional('event_id'),$data->optional('name'));
            $condition=array("id" => $data->optional('event_id'));
        } elseif ($data->optional('event_id') == '' && $data->optional('name') != '') {
            $row_count=CEvent::count($data->optional('event_id'),$data->optional('name'));
            $condition="name like '%".$data->optional('name')."%'";
        } else {
            $condition="status != 1";
            $row_count=CEvent::count();
        }
//         var_dump($row_count);die;
        $page_size=1;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $events=CEvent::getAllEvents($start,$page_size,$condition);
//         die;
        if (!empty($events)) {
            foreach ($events as &$event) {
                $event['start_time']=date('Y-m-d',strtotime($event['start_time']));
                $u_condition=array('uid' => $event['uid'],'status'=>0);
                $user=CEvent::getUserByName($u_condition);
                $event['nickname']=@$user['nickname'];
                $m_condition=array('event_id' => $event['id'],'is_deleted'=>0);
                $count_users=CEvent::EventUserCount($m_condition);
                $event['count_users']=$count_users;
                $members=CEvent::getEventUser($m_condition);
                $p_condition="event_id=".$event['id']." and status != 1";
                $count_pictures=CEvent::MomentPictureCount($p_condition);
                $event['count_pictures']=$count_pictures;
                $event['exposure']=Yii::$app->redis->get('st_invite_'.$event['id'])+Yii::$app->redis->get('st_share_'.$event['id']);
                if (!empty($members)) {
                    foreach ($members as $member) {
                        $c_condition=array('uid' => $member['member_uid'],'status'=>0);
                        $users=CEvent::getUserByName($c_condition);
                        $event['members'][]=array(@$users['nickname'],@$member['member_uid']);
                    }
                }
            }
        }
//         echo '<pre>';
//         var_dump($events);die;
        $page_html=CStat::showPager("events?event_id=".$data->optional('event_id')."&name=".$data->optional('name'),$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,dj");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('events',$events);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->display('events');
    }
    
    public function ListAction()
    {
        CInit::config($this);
        $data=Protocol::arguments();
        $start_time=$data->optional('start_date').' 00:00:00';
        $end_time=$data->optional('end_date').' 00:00:00';
        if ($data->optional('start_date') != ' 00:00:00' || $data->optional('end_date') !=' 00:00:00') {
            $row_count=CEvent::getCountByDate($start_time,$end_time);
            if ($start_time != ' 00:00:00') {
    	        $condition[]="create_time>='$start_time'";
    	    }
    	    if ($end_time !=' 00:00:00') {
    	        $condition[]="create_time<='$end_time'";
    	    }
    	    
    	    if (empty($condition)) {
    	        $condition=array();
    	    } else {
    	        $condition[]="status != 1";
    	        $condition=implode(' AND ',$condition);
    	    }
        } else {
            $condition='';
            $row_count=CEvent::count();
        }
        $page_size=25;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $events=CEvent::getAllEvents($start,$page_size,$condition);
        if (!empty($events)) {
            foreach ($events as &$event) {
                $u_condition=array('uid' => $event['uid'],'status'=>0);
                $user=CEvent::getUserByName($u_condition);
                $event['uid']=$user['nickname'];//查到活动创建人
                $m_condition=array('event_id' => $event['id'],'is_deleted'=>0);
                $members=CEvent::getEventUser($m_condition);
                $count_pictures=CEvent::MomentPictureCount($m_condition);
                $event['count_pictures']=$count_pictures;
                $event['create_time']=date('Y-m-d',strtotime($event['create_time']));
            }
        }
//         var_dump($events);die;
        $page_html=CEvent::showPager("list?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,dj");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('events',$events);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->display('list');
    }
    
    /* 活动详情图 */
    public function eventDetailAction()
    {
        CInit::config($this);
        $data=Protocol::arguments();
        $eventId = $data->requiredInt('eventId');
        if (Protocol::getMethod() == "GET") {
            /* 分页数据-start- */
            $row_count = CEvent::getTotalPicture($data->requiredInt('eventId'), 0) + CEvent::getTotalPicture($data->requiredInt('eventId'), 2) ;
            $page_size=12;
            $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
            $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
            $total_page=$total_page < 1 ? 1 : $total_page;
            $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
            $start=($page_no - 1) * $page_size;
            $pageHtml=CStat::showPager("eventDetail?eventId=$eventId", $page_no, $page_size, $row_count);
            $pictureList = CEvent::getMomentPictureByEventId($data->requiredInt('eventId'), 1, "$start, $page_size", 0); //图片列表
            if (empty($pictureList)) {
                CAdmin::alert($this, "success", Console\ADMIN\EVENT_NO_PICTURE); //活动下没有图片
            }
            /* 分页数据-end- */
        }
        
        /* 删除图片-start- */
        if(isset($_GET['method']) && $_GET['method'] == 'del') {
            /* 修改图片状态 */
            Event::doMomentPictureUpdate($data->requiredInt('eventId'), $data->requiredInt('mid'), $data->requiredInt('pid') , ['status'=>1]);
            /* 获取要删除的文件名 */
            $objectId = Event::GetPictureInfoByEvent($data->requiredInt('eventId'), $data->requiredInt('mid'), $data->requiredInt('pid'), $selectField ="object_id", 1);
            if(!empty($objectId)) {
                CosFile::delFile($objectId, CosFile::CATEGORY_SCENE);  //删除腾讯云文件
                CCommon::exitWithSuccess($this, '图片删除成功！', "Console/Event/eventDetail?eventId=$eventId");
                return;
            } else {
                CAdmin::alert($this, "error", Console\ADMIN\DEFAULT_PIC_DEL_FAILED); //图片删除失败
            }
        }
        /* 删除图片-end- */ 
        
        $confirm = CAdmin::renderJsConfirm("remove","确定要删除此照片吗？");
        $this->getView()->assign('confirm', $confirm);
        $this->getView()->assign('pictureList', $pictureList);
        $this->getView()->assign('pageHtml',$pageHtml);
        $this->display('eventDetail');
    }

    /** 上传活动照片 */
    public function appendPictureAction()
    {
        CInit::config($this);
        $loginId = CUserSession::getUserId();
        $data = Protocol::arguments();
        $userId = $data->optional('userId');
        $eventId = $data->optional('eventId');

        if ($this->appendLogs($loginId)) {
            $row_count = count($this->appendLogs($loginId));
            $page_size=12;
            $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
            $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
            $total_page=$total_page < 1 ? 1 : $total_page;
            $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
            $start=($page_no - 1) * $page_size;
            $logs = $this->appendLogs($loginId, $start, $page_size);
            $pageHtml=CStat::showPager("appendPicture?", $page_no, $page_size, $row_count);

            $this->getView()->assign('pageHtml',$pageHtml);
            $this->getView()->assign('logs', $logs);
            $this->getView()->assign('flag', 1);
            $this->getView()->assign('pageNo', $page_no);
            $this->getView()->assign('pageSize', $page_size);
        }

        $this->getView()->assign('userId', $userId);
        $this->getView()->assign('eventId', $eventId);
        $this->display('appendPicture');
    }

    public function ajaxCreateMomentAction()
    {
        $data = Protocol::arguments();

        if (!$this->queryUserAndEventStatus($data->requiredInt('userId'), $data->requiredInt('eventId'))) {
            echo json_encode(['momentId' => null]); return;
        }
        $momentId = Event::doTableInsert(Event::$tableEventMoment, ['uid' => $data->requiredInt('userId'), 'event_id' => $data->requiredInt('eventId'), 'status' => self::STATUS_MOMENT_DELETE], 1);
        echo json_encode(['momentId' => $momentId]); return;
    }

    public function uploadPictureAction()
    {
        $data = Protocol::arguments();//
        $userId = $data->requiredInt('userId');//登录uid
        $eventId = $data->requiredInt('eventId');//活动Id
        $momentId = $data->requiredInt('momentId');
        $file = Protocol::file('file');//上传文件信息
        $pictureList = '';

        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($connection, $userId, $eventId,$momentId,  $data, $file, $pictureList) {
            $coverUrl = CosFile::uploadFile($file, $userId, CosFile::CATEGORY_SCENE, CosFile::PICTURE_TYPE_ORIGINAL, CosFile::FILE_TYPE_PICTURE, $eventId, $momentId);
            $coverUrl = $coverUrl['subUrlName'];

            $connection->createCommand()->insert(self::$tableMomentPicture,[
                'event_id' => $eventId,
                'moment_id' => $momentId,
                'object_id' => !empty($coverUrl) ? $coverUrl : "",
                'shoot_time' => Types::unix2SQLTimestamp($data->optionalInt('shoot_time')/1000),
                'size' => $data->required('size'),
                'status' => self::STATUS_PICTURE_DELETE,//上传成功后是删除状态
            ])->execute();
            $pictureList = $connection->getLastInsertID();

            //$payload = ['userId' => $userId, 'eventId' => $eventId, 'momentId' => $momentId, 'pictureList' => $pictureList];

            echo json_encode(['pictureList' => $pictureList]); return;
        });
    }

    // 后台上传活动图片 提交动态
    public function commitMomentAction()
    {
        $data = Protocol::arguments();
        $userId = $data->requiredInt('userId');
        $eventId = $data->requiredInt('eventId');
        $momentId = $data->requiredInt('momentId');
        $version = $data->requiredInt('version');
        $pictureList = $data->required('pictureList');

        $connection = Yii::$app->db;
        Execution::autoTransaction($connection, function() use($connection,$userId, $eventId, $momentId, $pictureList, $version) {
            if (!$this->queryUserAndEventStatus($userId, $eventId)) {
                Protocol::badRequest(NULL, NULL, 'failure');
            }

            /* 判断现场动态图片数量是否为0,如果动态图片为0,就把这条现场动态删除start  */
            $count = Event::getTableWhereCount(Event::$tableMomentPicture, "moment_id = :moment_id and event_id = :event_id", [":moment_id" => $momentId, ":event_id" => $eventId]);
            if ($count > 0) {
                if (Predicates::isNotEmpty($pictureList)) {
                    /*修改picture状态  */
                    $resPicture = $connection->createCommand()->update(Event::$tableMomentPicture, [
                        'status' => Event::PICTURE_STATUS_NORMAL
                    ], " id in (" . implode(',', $pictureList) . ") and moment_id = {$momentId} and event_id = {$eventId}")->execute();
                    if ($resPicture) {
                        /*修改event和moment  */
                        $object = Tao::addObject("MOMENT", "mid", $momentId, "uid", $userId, "eid", $eventId, "type", 0);
                        /*修改moment为正常状态 start */
                        Event::doEventMomentUpdate($eventId, $momentId, ['status' => Event::MOMENT_STATUS_NORMAL, 'tao_object_id' => $object->id]);
                        /*修改moment为正常状态 end */
                        /*推送数据start  */
                        Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_NORMAL, 0, $version);
                        Event::tubePush($eventId, $momentId, Event::GROUP_EVENT_TYPE_MOMENT_NORMAL, 1, $version);//小组成员推
                        /*推送数据end  */
                        /*miPush推送数据start  */
                        MiPush::momentSendMessage($userId, $eventId, $momentId, "chuantu", "Us");
                        /*miPush推送数据end  */
                    }
                }
            } else {
                /*修改moment为删除状态  */
                Event::doEventMomentUpdate($eventId, $momentId, ['status' => Event::STATUS_MOMENT_DELETE]);
            }
            /* 判断现场动态图片数量是否为0,如果动态图片为0,就把这条现场动态删除end  */

            /* 结果集 */
            $result['p']['eventId'] = $eventId;
            $result['p']['momentId'] = $momentId;
            $result['p']['total_upload_count'] = Event::GetCountPictureByEvent($eventId, $userId);
            $result['p']['last_upload_count'] = Event::GetCountPictureByEvent($eventId, $userId, $momentId);

            $this->addMomentLog($userId, $eventId, $momentId);
            Protocol::ok($result['p'], NULL, 'success');
        });
    }

    private function addMomentLog($userId, $eventId, $momentId){
        $content = json_encode(['userId' => $userId, 'eventId' => $eventId]);
        $connection = Yii::$app->db;
        $connection->createCommand()->insert(self::$tableMomentLog, ['moment_id' => $momentId, 'content' => $content, 'user_id' => CUserSession::getUserId()])->execute();
    }

    public function ajaxVerifyStatusAction()
    {
        $data = Protocol::arguments();
        $userId = $data->requiredInt('userId');
        $eventId = $data->requiredInt('eventId');

        if ($this->queryUserAndEventStatus($userId, $eventId)) {
            echo json_encode(['status' => 0]); return;// 合法的数据状态
        } else {
            echo json_encode(['status' => 1]); return;// 非法的数据状态
        }
    }

    private function queryUserAndEventStatus($userId, $eventId)
    {
        //$data = Protocol::arguments();
        //$userId = $data->requiredInt('userId');
        //$eventId = $data->requiredInt('eventId');

        $connection = Yii::$app->db;
        $sql = 'SELECT * FROM';
        $sql .= ' (SELECT event_id,member_uid,is_deleted FROM event_user WHERE event_id=:eventId AND member_uid=:userId AND is_deleted=0) eu';
        $sql .= ' INNER JOIN (SELECT uid,`status` FROM `user` WHERE status=0) u ON (eu.member_uid=u.uid)';
        $sql .= ' INNER JOIN (SELECT id,`status` FROM `event` WHERE `status`=0) e ON (eu.event_id=e.id)';
        return $connection->createCommand($sql, ['eventId' => $eventId, 'userId' => $userId])->queryAll();
    }

    // 后台上传活动照片操作记录
    private function appendLogs($loginId, $offset=1, $rows=1)
    {
        $connection = Yii::$app->db;
        $sql = "SELECT em.id momentId,create_time,real_name realName,COUNT(mp.id) picNumber,event_id eventId,uid userId FROM";
        $sql .= " (SELECT moment_id,user_id FROM c_moment_log WHERE user_id=:user_id) cml";
        $sql .= " INNER JOIN (SELECT user_id,real_name FROM c_user) cu ON (cml.user_id=cu.user_id)";
        $sql .= " INNER JOIN (SELECT id,uid,event_id,create_time FROM event_moment WHERE status=0) em ON (cml.moment_id=em.id)";
        $sql .= " INNER JOIN (SELECT id,moment_id FROM moment_picture WHERE status=0) mp ON (em.id=mp.moment_id) GROUP BY mp.moment_id ORDER BY mp.moment_id DESC";
        $condition['user_id'] = $loginId;
        if ($rows > 1) {
            $sql .= " LIMIT :offset,:rows";
            $condition['offset'] = $offset;
            $condition['rows'] = $rows;
        }
        return $connection->createCommand($sql, $condition)->queryAll();
    }

    // 后台上传活动照片详情
    public function appendDetailAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        $flag = $data->optional('flag') ? $data->optionalInt('flag') : 0;
        $query = new Query();

        if ($data->optional('handle') && $data->optional('handle') === 'delPic') {
            Event::doMomentPictureUpdate($data->requiredInt('eventId'), $data->requiredInt('momentId'), $data->requiredInt('pictureId') , ['status'=>1]);
            $objectId = Event::GetPictureInfoByEvent($data->requiredInt('eventId'), $data->requiredInt('momentId'), $data->requiredInt('pictureId'), $selectField ="object_id", 1);
            if (!empty($objectId)) {
                CosFile::delFile($objectId, CosFile::CATEGORY_SCENE);  //删除腾讯云文件
                CCommon::exitWithSuccess($this, '图片删除成功！', "Console/Event/appendDetail?momentId={$data->requiredInt('momentId')}&flag=1");
                return;
            } else {
                CAdmin::alert($this, "error", Console\ADMIN\DEFAULT_PIC_DEL_FAILED); //图片删除失败
            }
        }
        if ($flag == 1) {
            $pictureList = $query->select('*')->from(self::$tableMomentPicture)->where(['moment_id' => $data->requiredInt('momentId'), 'status' => 0])->all();
            $info = $query->select('uid,event_id')->from(self::$tableEventMoment)->where(['id' => $data->requiredInt('momentId')])->one();
            $pictureList = ['pictureList' => $pictureList, 'userId' => $info['uid'], 'eventId' => $info['event_id']];
            $this->getView()->assign('result', $pictureList);
        } else if ($flag == 0) {
            $this->getView()->assign('userId', $data->requiredInt('userId'));
            $this->getView()->assign('eventId', $data->requiredInt('eventId'));
        }

        $confirm = CAdmin::renderJsConfirm("imgRemove","确定要删除此照片吗？");
        $this->getView()->assign('confirm', $confirm);
        $this->getView()->assign('flag', $flag);
        $this->display('appendDetail');
    }

}