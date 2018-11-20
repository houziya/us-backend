<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
use yii\db\Query;
class ChannelController extends Controller_Abstract {
    
    private static $spreadMainChannel = Us\TableName\SPREAD_MAIN_CHANNEL;
    
    private static $spreadSubChannel = Us\TableName\SPREAD_SUB_CHANNEL;
    
    private static $spreadChannelStat = Us\TableName\SPREAD_CHANNEL_STAT;
    
    public function listAction() 
    {
        CInit::config ( $this ); 
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments ();
        $start_time = date("Ymd",strtotime(Accessor::either(Protocol::optional('start_date', ""), date("Y-m-d"))));
        $end_time = date("Ymd",strtotime(Accessor::either(Protocol::optional('start_date', ""), date("Y-m-d"))));
        if ($data -> optional('start_date') != '' || $data -> optional('platform') !='') {
            $platform=$data -> optional('platform');
            if ($data -> optional('platform') == 1) {
                $row_count=CChannel::unionCount($platform,$start_time,$end_time);
            } else {
                $row_count=CChannel::unionCount($platform,$start_time,$end_time);
            }
            
        } else {
            $platform=0;
            $row_count=CChannel::unionCount($platform,$start_time,$end_time);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional('page_no','') < 1 ? 1 : $data->optional('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $channels=CChannel::getUnionDatas($platform,$start_time,$end_time,$start,$page_size);
        $total = [];
        @$total['platform'] = $platform;
        if (!empty($channels)) {
            foreach ($channels as &$channel) {
                $channel['settlement_amount'] = round($channel['sum_finally']);
//                     $fmp=$channel['platform'];
                    @$total['click'] = $total['click'] ? $total['click'] : 0+$channel['click'];
                    @$total['activation'] += $channel['activation'];
                    @$total['with_ip_activation'] += $channel['with_ip_activation'];
                    @$total['effective_activation'] += $channel['effective_activation'];
                    @$total['registrations'] += $channel['registrations'];
                    @$total['with_device_activation'] += $channel['with_device_activation'];
                    @$total['settlement_amount'] += $channel['settlement_amount'];
                    @$total['platform'] = $channel['platform'];
                }
        }
        $page_html=CChannel::showPager("list?start_date=".$data->optional('start_date').'&platform='.$data->optional('platform'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count);
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', $page_size );
        $this->getView ()->assign ( 'row_count', $row_count );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'channels', $channels );
        $this->getView ()->assign ( 'total', $total );
        $this->getView ()->assign ( 'date', $start_time );
        $this->display ('list');
    }
    
    /*
     * 渠道数据修改状态,渠道商可见
     */
    public function updateStatusAction() {
        CInit::config($this);
        $count=CChannel::unionCount(Protocol::required('platform'), Protocol::required('start_date'), Protocol::required('start_date'));
        if($count <= 0) {
            CCommon::exitWithError ($this,'没有相应数据','Console/Channel/list');
            return;
        }
        if (Predicates::isNotEmpty(Protocol::required('start_date'))) {
            $transaction = Yii::$app->db->beginTransaction();
            $commit = false;
            try {
                $result = CChannel::updateChannel (Protocol::required('start_date'), Protocol::required('start_date'), ["status"=>1]);
                if ($result) {
                    CSysLog::addLog (CUserSession::getUserName(),'MOD','Channel',Protocol::required('start_date') ,json_encode(["status"=>1]) );
                    $commit=true;
                }else {
                    CCommon::exitWithError ($this,'已经修改成功','Console/Channel/list');
                    return;
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this,"error",$e->getMessage());
                return;
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'数据已发送','Console/Channel/list');
                return;
            } else {
                CCommon::exitWithError ($this,'数据发送失败','Console/Channel/list');
                return ;
            }
        }
    }
    
    /*
     * 渠道数据(渠道人员专用) 
    */
    public function channelAction() {
        $date = Accessor::either(Protocol::optional('date', ""), date("Y-m-d"));
        CInit::config($this);
        $uid = CUserSession::getUserId();
        $channel = (new Query())
        ->select("sum_finally, unitPrice, sub_channel_name, `sum_finally`*`unitPrice` as total_fee")
        ->from(Us\TableName\SPREAD_CHANNEL_STAT." as s")->leftJoin(Us\TableName\SPREAD_SUB_CHANNEL." as c", "s.sid = c.id")
        ->leftJoin(Us\TableName\SPREAD_MAIN_CHANNEL." as m", "m.cid = c.cid")
        ->where("m.uid = ".$uid." and s.status = 1 and s.summary_day = ".date("Ymd",strtotime($date))."")
        ->all();
        //传送数据
        $this->getView()->assign('page_no', "");
        $this->getView()->assign('page_html', "");
        $this->getView()->assign('date', $date);
        $this->getView()->assign('list', $channel);
        $this->display('channel');
    }
    
    /* 主渠道商列表 */
    public function mainSponsorsAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        /* 删除-start- */
        if ($data->optional('method', '') == 'del') {    //删除接口
            if (empty(self::getChannelByCid($data->required('cid'), self::$spreadSubChannel))) {
                if (!empty(self::delChannel(['cid'=>$data->required('cid')], self::$spreadMainChannel))) {
                    CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'mainSponsors', $data->required('cid'), json_encode(self::delChannel(['cid'=>$data->required('cid')], self::$spreadMainChannel)));
                } else {
                    CAdmin::alert($this, "error", Console\ADMIN\CHANNEL_DELETE_FAILED); //删除失败
                }
            } else {
                CAdmin::alert($this, "error", Console\ADMIN\MAIN_CHANNEL_HAVE_SUB_CHANNEL); //主渠道下存在分渠道不允许删除
            }
        }
        /* 删除-end- */
        
        /* 分页数据-start- */
        if (Protocol::getMethod() == "POST") {
            $row_count = (new Query)->from(self::$spreadMainChannel)->where(['cid'=>$data->optional('cid')])->count();
        } else {
            $row_count = (new Query)->from(self::$spreadMainChannel)->count();
        }
        $page_size = Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        $pageHtml=CStat::showPager("mainSponsors", $page_no, $page_size, $row_count);
        if (Protocol::getMethod() == "POST") {
            $sponsors = (new Query)->from(self::$spreadMainChannel)
            ->limit($page_size)
            ->offset($start)
            ->where(['cid'=>$data->optional('cid')])
            ->all();  //查询所有主渠道列表
        } else {
            $sponsors = (new Query)->from(self::$spreadMainChannel)
            ->limit($page_size)
            ->offset($start)->all();  //查询所有主渠道列表
        }
        /* 分页-end- */
        foreach ($sponsors as $k=>$item) {
            $subInfos = (new Query)->from(self::$spreadSubChannel)->where(['cid'=> $item['cid']])->all();
            if (!empty($subInfos)) {
                $sponsors[$k]['sub'] = 1;
            } else {
                $sponsors[$k]['sub'] = 0;
            }
        }
        $confirm = CAdmin::renderJsConfirm("icon-remove");
        $this->getView()->assign('pageHtml', $pageHtml);
        $this->getView()->assign('confirm', $confirm);
        $this->getView()->assign('sponsors', $sponsors);
        $this->display('mainSponsors');
    }
    
    /* 主渠道商添加  */
    public function mainSponsorsAddAction()
    {
        CInit::config($this);
        $commit = false;
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $data=array ('main_channel_name' => $data->required('channelName'), 'uid' => $data->required('userId'));
                $result = self::addChannel($data, self::$spreadMainChannel);
                if ($result) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'mainSponsors' , $result, json_encode($data) );
                    $commit = true;
                } else {
                    throw new InvalidArgumentException(Console\ADMIN\MAIN_CHANNEL_ADD_FAILED);
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
                CCommon::exitWithSuccess($this, '添加主渠道成功！', 'Console/Channel/mainSponsors');
                return;
            }
        }
         
        $this->getView()->assign('users', self::getGroupsAndUsers());
        $this->display('mainSponsorsAdd');
    }
    
    /* 更新主渠道信息 */
    public function mainSponsorsModifyAction()
    {
        CInit::config($this);
        $commit = false;
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $inputData = ['main_channel_name' => $data->required('channelName'), 'uid' => $data->required('userId')];
                if ( self::updateMainChannel($data->required('cid'), $inputData)) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'mainSponsorsModify' , self::updateMainChannel($data->required('cid'), $inputData), json_encode($inputData) );
                    $commit = true;
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", Console\ADMIN\MAIN_CHANNEL_ADD_FAILED);
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess($this, '修改主渠道信息成功！', 'Console/Channel/mainSponsors');
                return;
            }
        }
        $mainSponsorsInfo = self::getChannelByCid($data->required('cid'), self::$spreadMainChannel);
        $this->getView()->assign('mainSponsorsInfo', $mainSponsorsInfo);
        $this->getView()->assign('users', self::getGroupsAndUsers());
        $this->display('mainSponsorsModify');
    }
    
    /* 得到组和用户信息  */
    public function getGroupsAndUsers()
    {
        $groupOptions=CUserGroup::getGroupForOptions(); //获取所有权限组
        if (!$groupOptions) {
            return false;
        }
        foreach ($groupOptions as $key=>$groups) {
            $users = CUser::getUsersByGroup($key);
            $user[$groups][] = $users;
        }
        return $user;
    }
    
    /* 添加主渠道 */
    public function addChannel( $channelData, $tableName )
    {
        if (! $channelData || ! is_array ( $channelData )) {
            return false;
        }
        $connection = Yii::$app->db;
        $connection->createCommand()->insert($tableName, $channelData)->execute();
        $code = $connection->getLastInsertID();
        return $code;
    }
    
    /* 删除主渠道  */
    public static function delChannel($condition, $tableName)
    {
        if (! $condition ) {
            return false;
        }
        $connection = Yii::$app->db;
        $result = $connection->createCommand()->delete($tableName, $condition )->execute();
        return $result;
    }
    
    /* 通过渠道ID查询渠道信息  */
    public static function getChannelByCid($cid, $tableName)
    {
        $sponsors = (new Query)->from($tableName)
        ->where(['cid'=>$cid])
        ->all();
        if (empty($sponsors)) {
            return false;
        }
        return $sponsors;
    }
    
    /* 修改主渠道信息  */
    public static function updateMainChannel($cid, $data)
    {
        if (! $data || ! is_array ( $data )) {
            return false;
        }
        $connection = Yii::$app->db;
        $condition=array("cid"=>$cid);
        $code = $connection->createCommand()->update(self::$spreadMainChannel, $data, $condition)->execute();
        return $code;
    }
    
    /* 得到所有渠道信息  --根据表名--*/
    public function getAllChannel($tableName)
    {
        $sponsors = (new Query)->from($tableName)->all();
        if (empty($sponsors)) {
            return false;
        }
        return $sponsors;
    }

    public function subSponsorsAction()
    {
        CInit::config($this);
        $conn = Yii::$app->db;
        /* 通过主渠道商显示分渠道商列表 -start- */
        if (Protocol::optional('method', '') == 'link' && Protocol::getMethod() == "GET") {
            $data = Protocol::arguments();
            $cid = $data->requiredInt('cid');    //主渠道商ID
            /* 分页数据-start- */
            if (Protocol::optional('search') == 1) {
                if (empty(Protocol::optional('subId'))) {
                    $row_count = (new Query)->from(self::$spreadSubChannel)->where(['cid'=>$cid,])->count();
                    $andWhere = ' ';
                } else {
                    $row_count = (new Query)->from(self::$spreadSubChannel)->where(['cid'=>$cid, 'id'=>Protocol::optional('subId')])->count();
                    $andWhere = " and subchel.id=".Protocol::optional('subId');
                }
            } else {
                $row_count = (new Query)->from(self::$spreadSubChannel)->where(['cid'=>$cid])->count(); //分渠道商总数据条数
                $andWhere = ' ';
            }
            
            $page_size = Console\ADMIN\PAGE_SIZE;
            $page_no = $data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
            $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
            $total_page=$total_page < 1 ? 1 : $total_page;
            $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
            $start=($page_no - 1) * $page_size;
            $pageHtml=CStat::showPager("subSponsors?method=link&cid=$cid", $page_no, $page_size, $row_count); //分页数据输出
            $where = "limit $start, $page_size";
            /* 分页-end- */
            /*分渠道商 主渠道商关联查询  */
                $sql = "select * from (select * from spread_sub_channel) as subchel left join (select * from spread_main_channel) as mainchel on (subchel.cid = mainchel.cid) where mainchel.cid=$cid $andWhere $where ";
                $subchannellist = $conn->createCommand($sql)->queryAll();
                foreach ($subchannellist as $key => $val) {
                    $subchannellist[$key]['order'] = $key;
                    $subchannellist[$key]['link'] = Us\APP_URL . "/Us/Stat/adClick?d=" . $val['channel_code'] . "&t=" . $val['channel_token'];
                }
                if ($subchannellist) {
                    $this->getView()->assign('subchannellist', $subchannellist);
                }
            
            /* 删除分渠道商-start- */
            if (Protocol::optional('medium', '') == 'del') {
                if (!empty(self::delChannel(['id'=>$data->required('id')], self::$spreadSubChannel))) {
                    CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'subSponsors', $data->required('id'), json_encode(self::delChannel(['id'=>$data->required('id')], self::$spreadSubChannel)));
                    CCommon::exitWithSuccess($this, '删除分渠道成功！', "Console/Channel/subSponsors?method=link&cid=$cid");
                    return;
                } else {
                    CAdmin::alert($this, "error", Console\ADMIN\CHANNEL_DELETE_FAILED); //删除失败
                }
            }
            /* 删除分渠道商-end- */
            $this->getView()->assign('pageHtml', $pageHtml);  //分页数据
        }
        /* 分渠道商数据-end-*/
        $confirm = CAdmin::renderJsConfirm("icon-remove");
        $this->getView()->assign('confirm', $confirm);
        $this->display('subSponsors');
    }
    
    /* 分渠道商添加 */
    public function subSponsorsAddAction()
    {
        CInit::config($this);
        if (Protocol::getMethod() == "POST") {
            $subchanneldata=Protocol::arguments();
            $cid = $subchanneldata->requiredInt('cid');
            /* 接收表单提交的数据 -start- */
            $map['cid'] = $subchanneldata['main_channel_name'];
            $map['platform'] = $subchanneldata['platform'];
            $map['sub_channel_name'] = $subchanneldata['sub_channel_name'];
            $map['channel_code'] = $subchanneldata['channel_code'];
            $map['channel_token'] = $subchanneldata['channel_token'];
            //$map['link'] = Us\Config\US_API_URL . "/Us/Stat/adClick?d=" . $map['channel_code'] . "&t=" . $map['channel_token'];
            $map['proportion'] = $subchanneldata['proportion'];
            $map['unitPrice'] = $subchanneldata['unitPrice'];
            /* 入库数据 -end- */
            $commit = false;
            $transaction = Yii::$app->db->beginTransaction();
            try {
                /* 查看分渠道名称是否冲突 */
                $subName = (new Query)-> from (self::$spreadSubChannel)->where(['sub_channel_name'=>$subchanneldata->required('sub_channel_name')])->one();
                if (!empty($subName)) {
                    throw new InvalidArgumentException(Console\ADMIN\SUB_CHANNEL_NAME_CONFILCT);
                }
                /* 执行插入数据库操作 */
                $result = self::addChannel($map, self::$spreadSubChannel);
                if ($result) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'subSponsors' , $result, json_encode($map) );
                    $commit = true;
                } else {
                    throw new InvalidArgumentException(Console\ADMIN\SUB_CHANNEL_ADD_FAILED);
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
                CCommon::exitWithSuccess($this, '添加分渠道成功！', "Console/Channel/subSponsors?method=link&cid=$cid");
                return;
            }
        }
        $this->getView()->assign('mainChannel', self::getAllChannel(self::$spreadMainChannel) );
        $this->display('subSponsorsAdd');
    }
    
    /* 分渠道商修改 */
    public function subSponsorsModifyAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        $connection = Yii::$app->db;
        if (Protocol::optional('method', '') == 'mod' && Protocol::getMethod() == "GET") {
            $subSponsors = (new Query)->from(self::$spreadSubChannel)->where(['id'=> $data->requiredInt('id')])->one();
            $this->getView()->assign('subSponsors', $subSponsors);
        }
        $cid = $data->requiredInt('cid');
        if (Protocol::getMethod() == "POST") {
            $commit = false;
            $transaction = Yii::$app->db->beginTransaction();
            try{
                /* 修改数据 -start- */
                $map['cid'] = $data->required('main_channel_name');
                $map['platform'] = $data->requiredInt('platform');
                $map['sub_channel_name'] = $data->required('sub_channel_name');
                $map['proportion'] = $data->required('proportion');
                $map['unitPrice'] = $data->required('unitPrice');
                /* 修改数据 -end- */
                
                $code = $connection->createCommand()->update(self::$spreadSubChannel, $map, ['id'=>$data->requiredInt('id')])->execute();
                if ($code > 0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'subSponsorsModify' , $code, json_encode($map) );
                    $commit = true;
                } else {
                    throw new InvalidArgumentException(Console\ADMIN\SUB_CHANNEL_MODIFY_FAILED);
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
                CCommon::exitWithSuccess($this, '修改分渠道成功！', "Console/Channel/subSponsors?method=link&cid=$cid");
                return;
            }
        }
        $this->getView()->assign('mainChannel', self::getAllChannel(self::$spreadMainChannel) );
        $this->display('subSponsorsModify');
    }
    
    /* --主渠道详情信息-- */
    public function subSponsorsAddressAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        if(Protocol::getMethod() == "GET") {
            $sub = (new Query)->from(self::$spreadSubChannel)->where(['id'=> $data->requiredInt('id')])->one();
            $this->getView()->assign('subLink', Us\APP_URL . "Us/Stat/adClick?d=" . $sub['channel_code'] . "&t=" . $sub['channel_token']);
            $this->getView()->assign('token',  $sub['channel_token']);
        }
        $this->display('subSponsorsAddress');
    }
    
    /* --修改扣量比例-- */
    public function subSponsorsUpdateAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        /* 查询分渠道商信息-- */
        $subInfo = (new Query)->from(self::$spreadSubChannel)->where(['id'=> $data->requiredInt('sid')])->one();
        $summaryDay = $data->requiredInt('ctime');
        $platform = $data->optional('platform');
        $startDate = $data->optional('start_date');
        if (Protocol::getMethod() == "POST") {
            $commit = false;
            $connection = Yii::$app->db;
            $transaction = Yii::$app->db->beginTransaction();
            try{
                $updateData['proportion'] = $data->required('proportion');
                $code = $connection->createCommand()->update(self::$spreadSubChannel, $updateData, ['id'=>$data->requiredInt('sid')])->execute();
                if ($code > 0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'subSponsorsModify' , $code, json_encode($updateData) );
                    /* 查询渠道商数据-- */
                    $subStat = (new Query)->from(self::$spreadChannelStat)->where(['sid'=> $data->requiredInt('sid'), 'summary_day'=>$summaryDay])->one();
                    /* 计算新数据 --*/
                    $newData['sum_finally'] = ($subStat['registrations'] - $subStat['with_device_activation']) * $updateData['proportion'];
                    /* 条件-- */
                    $condition['sid'] = $data->requiredInt('sid');
                    $condition['summary_day'] = $summaryDay;
                    /* 执行修改结算量-- */
                    $result = $connection->createCommand()->update(self::$spreadChannelStat, $newData, $condition)->execute();
                    if (!empty($result)) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'ChannelStatModify' , $result, json_encode($newData) );
                        $commit = true;
                    } else {
                        throw new InvalidArgumentException(Console\ADMIN\CHANNEL_STAT_SUM_FAILED);
                    }
                } else {
                    throw new InvalidArgumentException(Console\ADMIN\SUB_CHANNEL_MODIFY_FAILED);
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
                CCommon::exitWithSuccess($this, '修改渠道扣量比例成功！', "Console/Channel/list?platform=$platform&start_date=$startDate");
                return;
            }
        }
        $this->getView()->assign('subName',  $subInfo['sub_channel_name']);  //渠道名称
        $this->getView()->assign('proportion',  $subInfo['proportion']);     //渠道扣量比例
        $this->display('subSponsorsUpdate');
    }
    
    /* 修改分渠道单价 */
    public function modifyPriceAction()
    {
        CInit::config($this);
        $commit = false;
        $connection = Yii::$app->db;
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                /* 统一修改分渠道扣量比例-start- */
                $code = $connection->createCommand()->update(self::$spreadSubChannel, ['unitPrice'=>$data->required('price')], ['cid'=>$data->requiredInt('cid')])->execute();
                if ($code > 0 ) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'modifyPrice' , $code, json_encode(['unitPrice'=>$data->required('price')]) );
                    $commit = true;
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", Console\ADMIN\PRICE_CAN_NOT_EMPTY);
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess($this, '修改分渠道单价成功！', 'Console/Channel/subSponsors?method=link&cid='.$data->requiredInt('cid'));
                return;
            }
        }
        /* 查询主渠道商信息-- */
        $mainInfo = (new Query)->from(self::$spreadMainChannel)->where(['cid'=> $data->requiredInt('cid')])->one();
        $this->getView()->assign('mainName',  $mainInfo['main_channel_name']);  //主渠道名称
        $this->display('modifyPrice');
    }
}
?>
