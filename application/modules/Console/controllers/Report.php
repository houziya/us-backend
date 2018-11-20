<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
use yii\db\Query;
class ReportController extends Controller_Abstract
{
    private static $Picturetable_name = Us\TableName\MOMENT_PICTURE; 
    private static $Momenttable_name = Us\TableName\EVENT_MOMENT;
    private static $Reporttable_name = Us\TableName\EVENT_REPORT;
    
    public function ReportsAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $commit=false;
        if ($data->optional('method', '') == 'del' && ! empty ( $data->required('report_id') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $report = CReport::getReportById($data->required('report_id'));
                $report['data'] = json_decode($report['data'],true);
//                 $p_condition = array('id' => $report['data']['p_id']);
                $p_condition = "id = ".$report['data']['p_id']." and status = 0";
                $r_condition = array("id" => $data->required('report_id'));
                $value = array('status' => 2);//修改举报的状态
                $value2 = array('status' => 1);//修改动态和图片的状态
                $p_datas=CReport::getPicture($p_condition);
                if (empty($p_datas)) {
                    $report_result = CReport::updateReport($r_condition,$value,self::$Reporttable_name);
                    $commit=true;
                    CCommon::exitWithError ($this, '用户已把该照片删除','Console/Report/reports');
                    return;
                }
                $m_condition = array("moment_id" => @$p_datas['moment_id']);
                $m_datas=CReport::getPictureDatas($m_condition);
                $p_condition = array('id' => $report['data']['p_id']);
                $report_result = CReport::updateReport($r_condition,$value,self::$Reporttable_name);
                if (empty($m_datas)) {
                    $moment_result = CReport::updateReport(@$m_condition,$value2,self::$Momenttable_name);
                }
                $picture_result = CReport::updateReport($p_condition,$value2,self::$Picturetable_name);
                if ($report_result) {
                    CSysLog::addLog (CUserSession::getUserName(), 'UPDATE', 'Report', $data->required('report_id'), json_encode($report));
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
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
                CCommon::exitWithSuccess ($this, '图片已改为删除状态','Console/Report/reports');
                return;
            }
        }
        
        if ($data->optional('method', '') == 'ignore' && ! empty ( $data->required('report_id') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $report = CReport::getReportById($data->required('report_id'));
                $r_condition = array("id" => $data->required('report_id'));
                $value = array('status' => 1);//修改举报为忽略状态
                $report_result = CReport::updateReport($r_condition,$value,self::$Reporttable_name);
                if ($report_result) {
                    CSysLog::addLog (CUserSession::getUserName(), 'UPDATE', 'Report', $data->required('report_id'), json_encode($report));
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
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
                CCommon::exitWithSuccess ($this, '图片已改为忽略状态','Console/Report/reports');
                return;
            }
        }
        
        // START 数据库查询及分页数据
        $condition = array('status' => 0);
        $row_count=CReport::count($condition);
        $page_size=5;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $report_data=CReport::getDatas($start,$page_size,$condition);
        foreach ($report_data as $v) {
            $v['data'] = json_decode($v['data'],true);
            $p_condition = array('id' => $v['data']['p_id']);
            $c_condition = array('id' => $v['id']);
            $value = array('status' => 2);
            $pictures = CReport::getPicture($p_condition);
            if ($pictures['status'] != 0) {
                $report_result = CReport::updateReport($c_condition,$value,self::$Reporttable_name);
                $report_datas=CReport::getDatas($start,$page_size,$condition);
            } else {
                $report_datas=CReport::getDatas($start,$page_size,$condition);
            }
        }
        
        foreach ($report_datas as &$v) {
            $v['data'] = json_decode($v['data'],true);
            $r_condition = array('uid' => $v['reporter']);
            $u_condition = array('uid' => $v['uid']);
            $p_condition = array('id' => $v['data']['p_id']);
            $v['reporter_name'] = CReport::getUserName($r_condition);
            $v['uid_name'] = CReport::getUserName($u_condition);
            $v['data'] = CReport::getPicture($p_condition);
            $v['name'] = CReport::getEventName($p_condition);
            if (!empty($v['name'])) {
                $c_condition = array('uid' => $v['name']['uid']);
                $v['creater_name'] = CReport::getUserName($c_condition);
            }
            
        }
//         var_dump($report_datas);die;
        $page_html=CPagination::showPager("reports",$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,btn-primary");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('report_datas',$report_datas);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->display('reports');
    }
    
    public function WarningAction()
    {
        CInit::config($this);
        $data=Protocol::arguments();
        if ($data -> optional('start_date') !='') {
            $date = $data -> optional('start_date');
        } else {
            $date = date("Y-m-d",time()- ( 1  *  24  *  60  *  60 ));
        }
        $errors = Yii::$app -> redis -> get($date."_error:");
        $error = json_decode($errors,true);
        $err_datas=array();
//         $row_count = 0;
        while (list($a,$b) = @each($error)) {
            if (!empty($b)) {
                while (list($c,$d) = @each($b)) {
                    $err_datas[]=array($a=>array($c=>$d));
                }
            } 
        } 
//         echo $count; die;
//         echo '<pre>';
//         var_dump($err_datas);die;
        $this->getView()->assign('err_datas',$err_datas);
        $this->getView()->assign('date',$date);
        $this->display('warning');
    }
    
    public function hotAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
//         var_dump($_GET);die;
        $start_time=str_replace('-','',$data->optional('start_date'));
        if ($data->optional( 'start_date' ) != '') {
            $row_count=CStat::getDateCount ($start_time,'type=9');
        } else {
            $start_time=date('Ymd',time()- ( 1  *  24  *  60  *  60 ));
            $row_count=CStat::getDateCount ($start_time,'type=9');
        }
        // START 数据库查询及分页数据
        $page_size=1;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $temp=array();
        $val=array();
        $stat_datas=CStat::getDateDatas($start,$page_size,$start_time,'type=9');
//         echo '<pre>';
//         var_dump($stat_datas);die;
        //遍历数组，查找相关信息
        foreach (@$stat_datas as &$item) {
            foreach (@$item['data'] as $key => $value) {
                if ($data -> optional('start_time') !='' && $data -> optional('end_time') !='') {
                    if (intval($key) >= intval($data -> optional('start_time')) && intval($key) <= intval($data -> optional('end_time'))) {
                        $temp[]=array($key .':'.@$value['hot']);
                        $val[]=intval(@$value[@$value['hot']]);
                    }
                } elseif ($data -> optional('start_time') !='' && $data -> optional('end_time') =='') {
                    if (intval($key) >= intval($data -> optional('start_time'))) {
                        $temp[]=array($key .':'.@$value['hot']);
                        $val[]=intval(@$value[@$value['hot']]);
                    }
                } elseif ($data -> optional('start_time') =='' && $data -> optional('end_time') !='') {
                    if (intval($key) <= intval($data -> optional('end_time'))) {
                        $temp[]=array($key .':'.$value['hot']);
                        $val[]=intval(@$value[@$value['hot']]);
                    }
                } else {
                    $temp[]=array($key .':'.@$value['hot']);
                    $val[]=intval(@$value[@$value['hot']]);
                }
                
            }
        }
        // 显示分页栏
        $page_html=CStat::showPager("hot?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date')."&theme=".$data->optional('theme'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'temp', $temp );
        $this->getView ()->assign ( 'val', $val );
        $this->getView ()->assign ( 'start_time', $start_time );
        $this->display('hot');
    }
    
    public function timesAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
        //         var_dump($_GET);die;
        $start_time=str_replace('-','',$data->optional('start_date'));
        if ($data->optional( 'start_date' ) != '') {
            $row_count=CStat::getDateCount ($start_time,'type=8');
        } else {
            $start_time=date('Ymd',time()- ( 1  *  24  *  60  *  60 ));
            $row_count=CStat::getDateCount ($start_time,'type=8');
        }
        // START 数据库查询及分页数据
        $page_size=1;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $temp=array();
        $val=array();
        $imp=array();
        $tmp=array();
        $bmp=array();
        $stat_datas=CStat::getDateDatas($start,$page_size,$start_time,'type=8');
//         echo '<pre>';
//         var_dump($stat_datas);die;
        foreach (@$stat_datas as &$stat_data) {
            foreach (@$stat_data['data'] as $k => $v) {
                $temp[]=@$k;
                if ($data -> optional('classify') == '0' || $data -> optional('classify') == '') {
                    $val[]=@$v['pv'];
                } else {
                    $val[]=@$v['uv'];
                }
            }
        }
        
        if ($data -> optional('classify') == '0' || $data -> optional('classify') == '') {
            $tmp[]='当天接口调用次数';
            $imp[]='次数';
            $bmp[]='接口调用次数';
        } else {
            $tmp[]='当天接口调用人数';
            $imp[]='人数';
            $bmp[]='接口调用人数';
        }
        // 显示分页栏
        //$page_html=CStat::showPager("hot?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date')."&theme=".$data->optional('theme'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'temp', $temp );
        $this->getView ()->assign ( 'val', $val );
        $this->getView ()->assign ( 'imp', $imp );
        $this->getView ()->assign ( 'tmp', $tmp );
        $this->getView ()->assign ( 'bmp', $bmp );
        $this->getView ()->assign ( 'start_time', $start_time );
        $this->display('times');
    }
    
    public function lengthAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
        //         var_dump($_GET);die;
        $start_time=str_replace('-','',$data->optional('start_date'));
        if ($data->optional( 'start_date' ) != '') {
            $row_count=CStat::getDateCount ($start_time,'type=10');
        } else {
            $start_time=date('Ymd',time ()- ( 1  *  24  *  60  *  60 ));
            $row_count=CStat::getDateCount ($start_time,'type=10');
        }
        // START 数据库查询及分页数据
        $page_size=1;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $temp=array();
        $val=array();
        $imp=array();
        $tmp=array();
        $bmp=array();
        $stat_datas=CStat::getDateDatas($start,$page_size,$start_time,'type=10');
//                 echo '<pre>';
//                 var_dump($stat_datas);die;
        foreach (@$stat_datas as &$stat_data) {
            foreach (@$stat_data['data'] as $k => $v) {
                $temp[]=@$k;
                if ($data -> optional('classify') == '0' || $data -> optional('classify') == '') {
                    $val[]=round(@$v['elapsed'],2);
                } else {
                    $val[]=round(@$v['responseBodyLength'],2);
                }
            }
        }
        
        if ($data -> optional('classify') == '0' || $data -> optional('classify') == '') {
            $tmp[]='当天接口调用所需时间';
            $imp[]='运行时间';
            $bmp[]='接口运行时间';
        } else {
            $tmp[]='当天接口调用返回长度';
            $imp[]='返回长度';
            $bmp[]='接口返回长度';
        }
        //遍历数组，查找相关信息
        // 显示分页栏
        //$page_html=CStat::showPager("length?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date')."&theme=".$data->optional('theme'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'temp', $temp );
        $this->getView ()->assign ( 'val', $val );
        $this->getView ()->assign ( 'imp', $imp );
        $this->getView ()->assign ( 'tmp', $tmp );
        $this->getView ()->assign ( 'bmp', $bmp );
        $this->getView ()->assign ( 'start_time', $start_time );
        $this->display('length');
    }
}