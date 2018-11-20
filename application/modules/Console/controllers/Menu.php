<?php 
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class MenuController extends Controller_Abstract
{
    public function IndexAction()
    {
        $request = Yaf\Dispatcher::getInstance()->getRequest();
        $this->getView()->assign("content", $request);
        $this->display('index');
    }

    /*菜单模块主页*/
    public function moduleAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $module = CModule::getAllModules();//所有模块信息
        $confirm = CAdmin::renderJsConfirm("icon-remove");
        $this->getView()->assign('confirm',$confirm);//确认信息
        $this->getView()->assign('menuModule',$module);
        $this->display('module');
    }

    /*菜单模块添加*/ 
    public function moduleAddAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $commit = false;
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $nameExits = CModule::getModuleByName($data->required('moduleName'));
                $urlExits = CModule::getModuleByUrl($data->required('moduleUrl'));
                if ($nameExits || $urlExits) {
                    throw new InvalidArgumentException(Console\ADMIN\MENU_URL_OR_MENU_NAME_CONFLICT);
                }
                $inputData=array ('module_name' => $data->required('moduleName'), 'module_url' => $data->required('moduleUrl'),'module_sort' =>$data->requiredInt('moduleSort'), 'module_desc' => $data->required('moduleDesc'), 'module_icon' =>$data->required('moduleIcon'));
                $moduleId = CModule::addModule ( $inputData );
                if ($moduleId > 0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'Module' , $moduleId, json_encode($inputData) );
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
                CCommon::exitWithSuccess($this, '添加模块成功', 'Console/Menu/module');
                return;
            }
        }

        $this->display('moduleAdd');
    }

    /* 菜单模块删除 */
    public function moduleDelAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        if (Protocol::getMethod() == "GET") {
        try{
            $transaction = Yii::$app->db->beginTransaction();
            $moduleId = $data->required('moduleId');
            $menus = CModule::getModuleMenu($moduleId);
                if (sizeof($menus) > 0) {
                    //throw new InvalidArgumentException(Console\ADMIN\HAVE_MENU);
                    CCommon::exitWithError ($this, '该模块下有菜单，请先删除菜单','Console/Menu/module');
                    return;			
                }else if (intval($moduleId) === 1) {
                    //throw new InvalidArgumentException(Console\ADMIN\CAN_NOT_DELETE_SYSTEM_MODULE);
                    CCommon::exitWithError ($this, '不能删除系统模块','Console/Menu/module');
                    return;
                } else {
                    $module= CModule::getModuleById($moduleId);
                    $result = CModule::delModule ($moduleId);
                    if ($result) {
                        CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'Module' ,$moduleId, json_encode($module) );
                        $commit=true;
                    } else {
                        CAdmin::alert($this,"error");
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
        }
        if ($commit) {
            CCommon::exitWithSuccess ($this, '已将菜单模块删除','Console/Menu/module');
            return;
        }
    }

    /* 菜单更新*/
    public function moduleModifyAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $moduleShowId = $data->required('moduleId');
        $modules = CModule::getModuleById ($moduleShowId );
        $commit = false;
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try{
                if ($modules['module_name'] != ($data->required('moduleName')) ) {
                    $nameExits = CModule::getModuleByName($data->required('moduleName'));
                    if ($nameExits) {
                        throw new InvalidArgumentException(Console\ADMIN\MENU_URL_OR_MENU_NAME_CONFLICT);
                    }
                }
                if ($modules['module_url'] != ($data->required('moduleUrl')) ) {
                    $urlExits = CModule::getModuleByUrl($data->required('moduleUrl'));
                    if ($urlExits) {
                        throw new InvalidArgumentException(Console\ADMIN\MENU_URL_OR_MENU_NAME_CONFLICT);
                    }
                }
                $moduleData=array ('module_name' => $data->required('moduleName'), 'module_url' => $data->required('moduleUrl'),'module_sort' =>$data->requiredInt('moduleSort'), 'module_desc' => $data->required('moduleDesc'), 'module_icon' => $data->required('moduleIcon'));
                $result = CModule::updateModuleInfo ($moduleShowId,$moduleData );
                if ($result > 0){
                    CSysLog::addLog(CUserSession::getUserName(), 'MODIFY', 'Module', $moduleShowId, json_encode($moduleData));
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
        }
        if ($commit) {
            CCommon::exitWithSuccess($this, '菜单模块更新完成', 'Console/Menu/module');
            return;
        }
        $moduleOnlineOptioins = array("已下线","在线");
        $this->getView()->assign('moduleOnlineOptions', $moduleOnlineOptioins);//模块状态
        $this->getView()->assign('modules', $modules); //模块信息

        $this->display('moduleModify');
    }

    /*模块内联菜单列表*/
    public function listAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        try {
            $transaction = Yii::$app->db->beginTransaction();
            if (Protocol::getMethod() == "GET") {
                $moduleId = $data->required('moduleId');
                $menuList = CMenuUrl::getListByModuleId($moduleId );
                if (!$menuList) {
                    throw new InvalidArgumentException(Console\ADMIN\MENU_ID_NOT_EXIST);
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
            CCommon::exitWithSuccess($this, '更新完成', 'Console/Menu/module');
            return;
        }
        $moduleOptions =  CModule::getModuleForOptions ();
        $this->getView()->assign('menu', $menuList);
        $this->getView()->assign('moduleOptions', $moduleOptions);
        if ($menuList) { 
            $this->display('list');
        } else {
            CCommon::exitWithError ($this, '该模块下没有菜单哦^_^','Console/Menu/module');
            return;
        }
    }
    
    public function listsAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        try {
            $transaction = Yii::$app->db->beginTransaction();
            if (Protocol::getMethod() == "POST"){
                foreach ($data->required('menu_ids') as $menuId){
                    if($menuId <= 100){
                        throw new InvalidArgumentException(Console\ADMIN\SYSMENU_NOT_CAN_MOVE);
                    }
                }
                $ids=implode(',',$data->required('menu_ids'));
                $updateData = array('module_id' =>$data->required('mid'));
                $result = CMenuUrl::batchUpdateMenus ($ids,$updateData);
                //var_dump($result);
                if ($result >0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'MenuUrl' ,$ids, json_encode($updateData));
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
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
            CCommon::exitWithSuccess($this, '更新完成', 'Console/Menu/module');
            return;
        }
        
    }


    /*主菜单列表*/
    public function menusAction()
    {   
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        if ($data->optional('method', '') == 'del') {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $menuId = $data->required('menuId');
                $menus = CMenuUrl::getMenuById($menuId);
                if ($menuId <= 100) {
                    throw new InvalidArgumentException(Console\ADMIN\CAN_NOT_DELETE_SYSTEM_MENU);//不能删除系统菜单
                } else if ($menus['shortcut_allowed']==1) {
                    throw new InvalidArgumentException(Console\ADMIN\CAN_NOT_DELETE_SHORTCUT_MENU);//不能删除快捷菜单
                } else {
                    $result = CMenuUrl::delMenu ($menuId);
                    if ($result) {
                        CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'menu', $menuId, json_encode($menus));
                        $commit = true;
                    } else {
                        CAdmin::alert($this, "error");
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
        }
        
        //START 数据库查询及分页数据
        try{
            $searchId = $menuName ='';
            if (Protocol::getMethod() == "POST") {
                $method = $menu_id  = $page_no = '';
                $searchId = $data->required('searchId');
                $menuName = $data->optional('menuName', '');
            } else {
                $method = $menu_id = $menuName = $page_no = '';
                $searchId = 0;
                $page_no = $data->optional('page_no', '');
            }
            $page_size = Console\ADMIN\PAGE_SIZE;
            $page_no = $page_no < 1 ? 1: $page_no;
            if ($menuName || $searchId) {
                $row_count = CMenuUrl::countSearch($searchId, $menuName);
                $total_page = $row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size);
                $total_page = $total_page < 1 ? 1 : $total_page;
                $page_no = $page_no > ( $total_page) ? ($total_page) : $page_no;
                $start = ($page_no - 1) * $page_size;
                $menus = CMenuUrl::search($searchId, $menuName, $start , $page_size);
                if (!$menus) {
                    throw new InvalidArgumentException(Console\ADMIN\MENU_AND_MODULE_NOT_SAME);
                }
            } else {
                $row_count = CMenuUrl::count ();
                $total_page = $row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size);
                $total_page = $total_page<1?1:$total_page;
                $page_no = $page_no >($total_page) ? ($total_page) : $page_no;
                $start = ($page_no - 1) * $page_size;
                $menus = CMenuUrl::getAllMenus ( $start , $page_size );
            }
        }catch (InvalidArgumentException $e) {
            CAdmin::alert($this, "error", $e->getMessage());
        }	    
        $pages = CPagination::showPager("menus", $page_no, $page_size, $row_count);
        	    
        $moduleOpt = CModule::getModuleForOptions ();
        $moduleOpt[0] = "全部";
        ksort($moduleOpt);
        $confirm = CAdmin::renderJsConfirm("icon-remove");

        $this->getView()->assign('confirm', $confirm);
        $this->getView()->assign('pages', $pages);
        $this->getView()->assign('menuInfo', $menus);
        $this->getView()->assign('moduleOpt', $moduleOpt);
        $this->getView()->assign('moduleOptId',$searchId);
        $this->getView()->assign('menuName',$menuName);

        $this->display("menus");
    }

    /*主菜单添加  */
    public function addAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $commit = false;
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $urlExits = CMenuUrl::getMenuByUrl($data->required('menuUrl'));
                if ($urlExits) {
                    throw new InvalidArgumentException(Console\ADMIN\MENU_URL_CONFLICT);
                }
                $nameExist = CMenuUrl::getMenuByName($data->required('menuName'));
                if ($nameExist) {
                    throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                }
                $inputData = array ('menu_name' => $data->required('menuName'),
                    'menu_url' => $data->required('menuUrl'),
                    'module_id' => $data->required('moduleId'),
                    'is_show' => $data->required('isShow'), 'online' =>1 , 
                    'shortcut_allowed'=>$data->required('shortcutAllowed'),
                    'menu_desc' => $data->required('menuDesc'), 
                    'father_menu'=>$data->required('fatherMenu'));
                $menuId = CMenuUrl::addMenu ($inputData );
                if ($menuId > 0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'menu' , $menuId, json_encode($inputData) );
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
                }
            } catch(InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                }
                else{
                   $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess($this, '添加功能成功', 'Console/Menu/menus');
                return;
            }
        }

        $moduleOpts = CModule::getModuleForOptions ();
        $fatherMenuOpts = CMenuUrl::getFatherMenuForOptions ();
        array_shift($fatherMenuOpts);
        $this->getView()->assign('moduleOpts', $moduleOpts);
        $this->getView()->assign('fatherMenuOpts', $fatherMenuOpts);

        $this->display("add");
    }
    
    /* 主菜单更新 */
    public function modifyAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        try {
            $transaction = Yii::$app->db->beginTransaction();
            $menuId = $data->required('menuId');
            $menu = CMenuUrl::getMenuById ($menuId );
            if (Protocol::getMethod() == "POST") {
                if ($menu['shortcut_allowed'] == 1) {
                    if($menu['menu_url'] != ($data->required('menuUrl'))){
                        throw new InvalidArgumentException(Console\ADMIN\NOT_MODIFY_SHORTCUT_MENU);
                    }
                }
                $menuExist = CMenuUrl::getMenuByUrl($data->required('menuUrl'));
                if(!empty($menuExist)){
                    if($menuId != $menuExist['menu_id']){
                        throw new InvalidArgumentException(Console\ADMIN\MENU_URL_CONFLICT);
                    }
                }
                if ($menu['menu_name'] != ($data->required('menuName'))) {
                    $nameExist = CMenuUrl::getMenuByName($data->required('menuName'));
                    if ($nameExist) {
                        throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                    }
                }
                $updateData = array ('menu_name' => $data->required('menuName'),
                        'menu_url' => $data->required('menuUrl'),
                        'module_id' => $data->optional('moduleId', 1),
                        'is_show' => $data->required('isShow'),
                        "online" => $data->required('online'),
                        'shortcut_allowed'=>$data->required('shortcutAllowed'),
                        'menu_desc' => $data->optional('menuDesc', ''),
                        'father_menu'=>$data->required('fatherMenu'));
                $result = CMenuUrl::updateMenuInfo($menuId,$updateData );
                if ($result >0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'menu' ,$menuId, json_encode($updateData) );
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
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
        if ($commit)
        {
            CCommon::exitWithSuccess($this, '菜单修改完成','Console/Menu/menus');
            return;
        }

        $moduleOpt = CModule::getModuleForOptions ();
        $isShowOpt = array("1"=>"显示", "0"=>"不显示");
        $onlineOpt = array("1"=>"下线", "0"=>"在线");
        $allowedOpt = array("1"=>"允许", "0"=>"不允许");
        $fatherMenuOpt = CMenuUrl::getFatherMenuForOptions ();
        array_shift($fatherMenuOpt);
        $this->getView()->assign('isShowOpt', $isShowOpt);
        $this->getView()->assign('onlineOpt', $onlineOpt);
        $this->getView()->assign('allowedOpt', $allowedOpt);
        $this->getView()->assign('fatherMenuOpt', $fatherMenuOpt);
        $this->getView()->assign('moduleOpt', $moduleOpt);
        $this->getView()->assign('menu', $menu);

        $this->display("modify");
    }
}
?>
