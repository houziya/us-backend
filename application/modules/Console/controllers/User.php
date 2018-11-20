<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class UserController extends Controller_Abstract
{
    public function IndexAction()
    {
        CInit::config($this);
        $user_info = CUserSession::getSessionInfo();
        $sidebar = CSideBar::getTree ();
        foreach($sidebar as $sideInfo) {
            foreach($sideInfo['menu_list'] as $infos) {
                if($infos['shortcut_allowed'] ==1) {
                    $newInfos[] = $infos['menu_id'];
                }
            }
        }
        if (!empty($newInfos)) {
            $newData = implode(',',$newInfos);
            $menus = CMenuUrl::getMenuByIds($newData);
        } else {
            $menus = '';
        }
        $this->getView()->assign("menus",$menus);
        $this->display('index');
    }
    
    public function LoginAction()
    {
        if (isset($_COOKIE['c_remember'])) {
            CCommon::exitWithSuccess ($this,'欢迎您再次登入','Console/User/index');
            return;
        }
        
        CInit::config($this);
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            if($data->optional('verify_code')){ 
                if (strtolower($data->required('verify_code')) != strtolower(Yii::$app->session['c_verify_code'])) {
                   CAdmin::alert($this, "error", Us\User\CAPTCHA_CONTENT);
                }else{
                    $user_info = CUser::checkPassword ($data->required('user_name'), $data->required('password'));
                    if ($user_info) {
                        if($user_info['status']==1){
                            CUser::loginDoSomething($user_info['user_id'], $this);
                            if($data->optional('remember', false)){
                                $encrypted = CEncrypt::encrypt($user_info['user_id']);
                                CUser::setCookieRemember(urlencode($encrypted),30);
                            }
                            $ip = Protocol::remoteAddress();
                            CSysLog::addLog($data->required('user_name'), 'LOGIN', 'User' ,CUserSession::getUserId(),json_encode(array("IP" => $ip)));
                            CCommon::jumpUrl('Console/User/index');
                    
                        }else{
                            $message = '账户已被停用';
                            $alert_html="<div class=\"alert alert-error\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>$message</div>";
                            CAdmin::alert($this, "error", Console\ADMIN\BE_PAUSED);
                        }
                    } else {
                        CSysLog::addLog ($data->required('user_name'), 'LOGIN', 'User', '', json_encode(Console\ADMIN\USER_OR_PWD_WRONG));
                        CAdmin::alert($this, "error", Console\ADMIN\USER_OR_PWD_WRONG);
                    }
                    $this->getView()->assign("user_info", $user_info);
                }
            }else{
//             if (strtolower($data->required('verify_code')) != strtolower(Yii::$app->session['c_verify_code'])) {
//                     CAdmin::alert($this, "error", Us\User\CAPTCHA_CONTENT);
//                 }else{
                    $user_info = CUser::checkPassword ($data->required('user_name'), $data->required('password'));
                    if ($user_info) {
                        if($user_info['status']==1){
                            CUser::loginDoSomething($user_info['user_id'], $this);
                            if($data->optional('remember', false)){
                                $encrypted = CEncrypt::encrypt($user_info['user_id']);
                                CUser::setCookieRemember(urlencode($encrypted),30);
                            }
                            $ip = Protocol::remoteAddress();
                            CSysLog::addLog($data->required('user_name'), 'LOGIN', 'User' ,CUserSession::getUserId(),json_encode(array("IP" => $ip)));
                            CCommon::jumpUrl('Console/User/index');
                            
                        }else{
                            $message = '账户已被停用';
                            $alert_html="<div class=\"alert alert-error\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>$message</div>";
                            CAdmin::alert($this, "error", Console\ADMIN\BE_PAUSED);
                        }
                    } else {
                        CSysLog::addLog ($data->required('user_name'), 'LOGIN', 'User', '', json_encode(Console\ADMIN\USER_OR_PWD_WRONG));
                        CAdmin::alert($this, "error", Console\ADMIN\USER_OR_PWD_WRONG);
                    }
                    $this->getView()->assign("user_info", $user_info);
                 }
        }
        $this->getView()->assign("_POST", $_POST);
        $this->getView()->assign('page_title','登入');
        $this->display('login'); 
    
    }
    public function VerifyCodeAction()
    {
        header("Content-type: image/png");
        $im = imagecreatetruecolor(80, 28);
        $english = array(2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I',
                'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm', 'n', 'p', 'q', 'r', 's',
                't', 'u', 'v', 'w', 'x', 'y', 'z'
        );
        $chinese = array();
        //$chinese = array("人","出","来","友","学","孝","仁","义","礼","廉","忠","国","中","易","白","者","火 ","土","金","木","雷","风","龙","虎","天","地", "生","晕","菜","鸟","田","三","百","钱","福","爱","情","兽","虫","鱼","九","网","新","度","哎","唉","啊","哦","仪","老","少","日","月","星","于","文","琦","搜","狐","卓","望");
        $chars = array_merge($english, $chinese);
        // 创建颜色
        $fontcolor = imagecolorallocate($im, 0x6c, 0x6c, 0x6c);
        //$bg = imagecolorallocate($im, rand(0,85), rand(85,170), rand(170,255));
        $bg = imagecolorallocate($im, 0xfc, 0xfc, 0xfc);
        imagefill($im, 0, 0, $bg);
        // 设置文字
        $text = "";
        for ($i = 0; $i < 6; $i++) {
            $text .= trim($chars[rand(0,count($chars)-1)]);
        }
        Yii::$app->session['c_verify_code'] = $text;
        $font = 'assets/font/tahoma.ttf';
        $gray = ImageColorAllocate($im, 200,200,200);
        // 添加文字
        imagettftext($im, 15, 0, 1, 23, $fontcolor, $font, $text);
        //加入干扰象素
        $r = rand()%50;
        for ($i = 0; $i < 150; $i++) {
            $x = sqrt($i) * 2 + $r;
            imagesetpixel($im, abs(sin($i)*80) , abs(cos($i)*28) , $gray);
            imagesetpixel($im, $x , $i , $gray);
            imagesetpixel($im, rand()%80 , rand()%28 , $gray);
        }
        // 输出图片
        imagepng($im);
        imagedestroy($im);
    }

    public function AddAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction=Yii::$app->db->beginTransaction();
            $commit=false;
            try{
                $exist=CUser::getUserByName($data->required('user_name'));
                if($exist){
                    throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                }else if($data->required('password')=="" || $data->required('real_name')=="" || $data->required('mobile') =="" || $data->required('email') =="" || $data->required('user_group') <= 0 ){
                    throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                }else{
                    $input_data = array ('user_name' => $data->required('user_name'), 'password' => md5 ( $data->required('password') ), 'real_name' => $data->required('real_name'), 'mobile' => $data->required('mobile'), 'email' => $data->required('email'), 'user_desc' => $data->optional('user_desc'), 'user_group' => $data->required('user_group') );
                    $user_id = CUser::addUser ( $input_data );
                    if ($user_id) {
                        $input_data['password']="";
                        CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'User' ,$user_id, json_encode($input_data) );
                        $commit = true;
                    }else{
                        CAdmin::alert("error");
                    }
                }
            }catch(InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            }finally {
                if ($commit) {
                    $transaction -> commit();
                }else {
                    $transaction -> rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'账号添加成功','Console/User/users');
                return;
            }
        }
        $group_options = CUserGroup::getGroupForOptions();
        $this->getView()->assign("_POST" ,$_POST);
        $this->getView()->assign('group_options',$group_options);
        $this->display('add');
    }
    public function UsersAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit=false;
        if ($data->optional('method', '') == 'pause' && ! empty ( $data->required('user_id'))) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $user_data=array("status"=>0);
                if ($data->required('user_id') == CUserSession::getUserId()) {
                    throw new InvalidArgumentException(Us\User\CAN_NOT_DO_SELF);
                }else {
                    if ($data->required('user_id')==1) {
                        CCommon::exitWithSuccess ($this,'不能封停初始管理员','Console/User/users');
                    }
                    $result = CUser::updateUser ($data->required('user_id'),$user_data );
                    if ($result>=0) {
                        CSysLog::addLog (CUserSession::getUserName(),'PAUSE','User',$data->required('user_id') ,json_encode($user_data) );
                        $commit=true;
                    }else {
                        CAdmin::alert("error");
                    }
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this,"error",$e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'已封停','Console/User/users');
                return;
            }
        }
        if ($data->optional('method', '') == 'play' && ! empty ( $data->required('user_id'))) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $user_data=array("status"=>1);
                $result = CUser::updateUser ($data->required('user_id'),$user_data );
                if ($result>=0) {
                    CSysLog::addLog(CUserSession::getUserName(),'PLAY' ,'User',$data->required('user_id') ,json_encode($user_data) );
                    $commit=true;
                }else{
                    CAdmin::alert("error");
                }
            } catch(InvalidArgumentException $e) {
                CAdmin::alert($this,"error",$e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'已解封','Console/User/users');
                return;
            }
            
        }
        
        if ($data->optional('method', '') == 'del' && ! empty ( $data->required('user_id') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($data->required('user_id') == CUserSession::getUserId()) {
                    throw new InvalidArgumentException(Us\User\CAN_NOT_DO_SELF);
                } else {
                    if($data->required('user_id')==1){
                        CCommon::exitWithSuccess ($this,'不能删除初始管理员','Console/User/users' );
                    }
                    $user = CUser::getUserById($data->required('user_id'));
                    $result = CUser::delUser ( $data->required('user_id') );
                    if ($result>=0) {
                        $user['password']=null;
                        CSysLog::addLog (CUserSession::getUserName(),'DELETE','User',$data->required('user_id') ,json_encode($user) );
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
                CCommon::exitWithSuccess ($this,'已删除','Console/User/users');
                return;
            }
        }
        //START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional('page_no', '')<1?1:$data->optional('page_no', '');
        if($data->optional('search')){
            if($current_user_info['user_id'] != 1){
                $row_count=(CUser::countSearch($data->optional('user_group'),$data->optional('user_name')))-1;
            }else{
                $row_count=CUser::countSearch($data->optional('user_group'),$data->optional('user_name'));
            }
            $total_page=$row_count%$page_size==0?$row_count/$page_size:ceil($row_count/$page_size);
            $total_page=$total_page<1?1:$total_page;
            $page_no=$page_no>($total_page)?($total_page):$page_no;
            $start =($page_no - 1) * $page_size;
            $user_infos=CUser::search($data->optional('user_group'),$data->optional('user_name'),$start , $page_size);
        }else{
            if($current_user_info['user_id'] !=1){
                $row_count=(CUser::count ())-1;
            } else {
                $row_count=CUser::count ();
            }
            $total_page=$row_count%$page_size==0?$row_count/$page_size:ceil($row_count/$page_size);
            $total_page=$total_page<1?1:$total_page;
            $page_no=$page_no>($total_page)?($total_page):$page_no;
            $start=($page_no - 1) * $page_size;
            $user_infos=CUser::getAllUsers($start,$page_size);
        }
        $page_html=CPagination::showPager("user?user_group=".$data->optional('user_group')."&user_name=".$data->optional('user_name')."&search=".$data->optional('search'),$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,icon-remove");
        // 设置模板变量
        $group_options=CUserGroup::getGroupForOptions();
        $group_options[0]="全部";
        ksort($group_options);
        $this->getView()->assign('group_options',$group_options);
        $this->getView()->assign('current_user_info',$current_user_info);
        $this->getView()->assign('user_infos',$user_infos);
        $this->getView()->assign('_GET',$_GET);
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->display('users');
         
    }
    
    public function ModifyAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $user=CUser::getUserById($data->required('user_id'));
        $commit=false;
        if (empty($user)) {
            CCommon::exitWithError(Us\User\USER_NOT_EXIST,"Console/User/users");
        }
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if($data->required('real_name') == "" || $data -> required('mobile') == "" || $data ->required('email') == "" || ($data->required('user_id') != 1 && $data->required('user_group') <= 0)) {
                    throw new InvalidArgumentException(Console\ADMIN\NEED_PARAM);
                } else {
                    $update_data=array('real_name' => $data->required('real_name'), 'mobile' => $data->required('mobile'),
                            'email' => $data->required('email'), 'user_desc' => $data->optional('user_desc') );
                    if ($data->required('user_id') > 1 ){
                        $update_data["user_group"]=$data->required('user_group');
                    }
                    if (! empty ( $data->optional('password') )) {
                        $update_data=array_merge ($update_data,array('password' => md5($data->required('password'))));
                    }
                    $result=CUser::updateUser($data->required('user_id'),$update_data);
                    if ($result>=0) {
                        $current_user=CUserSession::getSessionInfo();
                        $ip=CCommon::getIp();
                        $update_data['ip']=$ip;
                        CSysLog::addLog(CUserSession::getUserName(),'MODIFY','User',$data->required('user_id'),json_encode($update_data));
                        CCommon::exitWithSuccess($this,'更新完成','Console/User/users');
                        $commit=true;
                    } else {
                        CAdmin::alert("error");
                    }
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this,"error",$e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess($this,'更新完成','Console/User/users');
                return;
            }
        }
        $group_options=CUserGroup::getGroupForOptions();
        $this->getView()->assign('user',$user);
        $this->getView()->assign('group_options',$group_options);
        $this->display('modify');
    }
    
    public function ProfileAction()
    {
        CInit::config($this);
//         $user_name = $password = $real_name = $mobile = $email = $user_desc = $change_password = $show_quicknote = $old = $new= '';
        $data=Protocol::arguments();
        $current_user_id=CUserSession::getUserId();
        $commit=false;
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($data->optional('change_password')) {
                    $ret=CUser::checkPassword(CUserSession::getUserName(),$data->required('old'));
                    if ($ret) {
                        if (strlen($data->required('new')) < 6) {
                            CAdmin::alert("error",Us\User\PWD_TOO_SHORT);
                        }else{
                            $user_data['password']=md5($data->required('new'));
                            CUser::updateUser($current_user_id,$user_data);
                            CSysLog::addLog (CUserSession::getUserName(),'MODIFY','User',$current_user_id );
                            $commit=true;
                        }
                    }else{
                        CAdmin::alert("error",Console\ADMIN\OLD_PWD_WRONG);
                    }
                }else{
                    $user_data['real_name']=$data->required('real_name');
                    $user_data['mobile']=$data->required('mobile');
                    $user_data['email']=$data->required('email');
                    $user_data['user_desc']=$data->required('user_desc');
                    $user_data['show_quicknote']=$data->required('show_quicknote');
                    CUser::updateUser ($current_user_id,$user_data);
                    CUserSession::reload();
                    CSysLog::addLog(CUserSession::getUserName(),'MODIFY','User',$current_user_id,json_encode($user_data) );
                    $commit=true;
                }
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this,"error",$e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                if ($data->optional('change_password')) {
                    CCommon::exitWithSuccess ($this,Console\ADMIN\PWD_UPDATE_SUCCESS,'Console/User/index');
                    return;
                } else {
                    CCommon::exitWithSuccess ($this,'资料修改成功','Console/User/index');
                    return;
                }
            }
        }
        $quicknoteOptions=array("1"=>"显示","0"=>"不显示");
        //更新Session里的用户信息
        $this->getView()->assign("change_password",$data->optional('change_password'));
        $this->getView()->assign("user_info",CUserSession::getSessionInfo());
        $this->getView()->assign("quicknoteOptions",$quicknoteOptions);
        $this->display ('profile');
    }
    
    public function LogoutAction()
    {
        CInit::config($this);
        if(array_key_exists(CUserSession::SESSION_NAME,Yii::$app->session)){
            CSysLog::addLog ( CUserSession::getUserName(), 'LOGOUT','User' ,CUserSession::getUserId());
        }
        CUser::logout();
        CCommon::exitWithSuccess($this,"您已安全退出！","Console/User/login");
    }
    
    public function errorAction()
    {
        $this->display('error');
    }
    
    public function weixinAction()
    {
        $this -> display('weixin');
    }
}
