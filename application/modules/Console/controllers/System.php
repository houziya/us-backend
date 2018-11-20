<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;

class SystemController extends Controller_Abstract
{
    public function IndexAction()
    {
        CInit::config($this);
        $sys_info=CCommon::getSysInfo();
        $this->getView()->assign('sys_info',$sys_info);
        $this->display('index');
    }
    
    public function LogAction()
    {
        CInit::config($this);
        //         $page_no = $user_name = $class_name =$start_date = $end_date ="";
        $current_user_info = CUserSession::getSessionInfo();
        $infos=explode(',',$current_user_info['user_role']);
        if(!in_array(21,$infos)){
            CCommon::exitWithError($this,'您还没有查看记录的权限','Console/User/index');
            return;
        }
        $data=Protocol::arguments();
        $temp='';
            if ($data->optional('class_name') == 'ALL') {
                $class_name='';
            }
            $start_time=strtotime($data->optional('start_date'));
            $end_time=strtotime($data->optional('end_date'));
            //START 数据库查询及分页数据
            if ($data->optional('start_date') != '' && $data->optional('end_date') !='') {
                $row_count=CSysLog::getCountByDate($data->optional('class_name'),$data->optional('user_name'),$start_time,$end_time);
            }else{
                $row_count=CSysLog::count($data->optional('class_name'),$data->optional('user_name'));
            
            }
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional('page_no','')<1?1:$data->optional('page_no','');
        $total_page=$row_count%$page_size==0?$row_count/$page_size:ceil($row_count/$page_size);
        $total_page=$total_page<1?1:$total_page;
        $page_no=$page_no>($total_page)?($total_page):$page_no;
        $start=($page_no-1)*$page_size;
        //END
        $sys_logs=CSysLog::getLogs($data->optional('class_name'),$data->optional('user_name'),$start,$page_size,$start_time,$end_time);
//         echo $row_count;die;
        $loadedClz=array();
        $namePool=array();
        foreach ($sys_logs as &$log){
            if(array_key_exists($log['action'],yii::$app->params['console_command_for_log'])){
                $log['action']=yii::$app->params['console_command_for_log'][$log['action']];
            }
            $class_obj=$log['class_obj'];
//             var_dump($log['class_name']);die;
            if(array_key_exists($log['class_name'],yii::$app->params['consloe_class_for_log'])){
                $log['class_name']=yii::$app->params['consloe_class_for_log'][$log['class_name']];
            }
            if($log['class_obj']==""){
                $log['class_obj']='null';
            }
            if(empty($log['result'])){
                $log['result'] = '成功';
            }else{
                $result =json_decode(@$log['result'],true);
                if(is_array($result)){
                    $temp=null;
                    foreach($result as $key => $value){
                        $temp[$key]=$value;
                    }
                    $log['result']=@implode(';',@$temp);
                }else{
                    $log['result']=$result;
                }
            }
        }
        // 显示分页栏
        $page_html=CPagination::showPager("log?class_name=".$data->optional('class_name')."&user_name=".$data->optional('user_name')."&start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count);
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_size',Console\ADMIN\PAGE_SIZE);
        $this->getView()->assign('row_count',$row_count);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign( '_GET',$_GET);
        $this->getView()->assign('class_options',yii::$app->params['consloe_class_for_log']);
        $this->getView()->assign('sys_logs',$sys_logs);
        $this->display('log');
        
    }
    
    public function SettingAction()
    {
        CInit::config($this);
//         $new_timezone = '';
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $current_user_id=CUserSession::getUserId();
        $timezone=CSystem::get('timezone');
        if (Protocol::getMethod() == "POST") {
            CSystem::set('timezone',$data->required('new_timezone'));
            Yii::$app->session['osa_timezone']=$data->required('new_timezone');
            CCommon::exitWithSuccess ($this,'时区设置成功','Console/User/index'); 
            return;
        }
        
        $timezone_options=array(
                "America/New_York"=>"纽约",
                "Europe/London"=>"伦敦,卡萨布拉卡",
                "Asia/Shanghai"=>"北京,新加坡,香港",
                "Asia/Tokyo"=>"东京,首尔",
        );
        
        //更新Session里的用户信息
        
        $this->getView()->assign("user_info",CUserSession::getSessionInfo());
        $this->getView()->assign("timezone",$timezone);
        $this->getView()->assign("timezone_options",$timezone_options);
        $this->display ('setting');
                
    }
    
    public function SetAction()
    {
        CInit::config($this);
//      $t = '';
        $data=Protocol::arguments();
        $current_user_id=CUserSession::getUserId();
        if($data->optional('t')==null){
            $t="default";
        }
        $ret=CUser::setTemplate(CUserSession::getUserId(),$data->optional('t'));
        $_SESSION[CUserSession::SESSION_NAME]['template']=$data->optional('t');
//         var_dump($data->optional('t'));die;
        $rand=rand(0,10000);
//         var_dump($_SERVER);die;
        $back_url=@Yii::$app->session['url']['referver_url']."#".$rand;
        header( "Location: ".Console\ADMIN_URL."$back_url" );
    }
}