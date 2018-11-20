<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class GroupController extends Controller_Abstract
{
    public function MembersAction ()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $group = CUserGroup::getGroupById ($data->required('group_id'));
        if (empty($group)) {
            CCommon::exitWithError($this, Console\ADMIN\GROUP_NOT_EXIST, "Console/Group/groups");
            return ;
        }
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            $commit = false;
            try {
                if (in_array(1, $data->required('user_ids'))) {
                    CCommon::exitWithError ($this, '不可更改初始管理员的账号组', 'Console/Group/groups');
                }
                $user_ids = implode(',', $data->required('user_ids'));
                $update_data = array ('user_group' => $data->required('user_group'));
                $result = CUser::batchUpdateUsers ($user_ids, $update_data);
                if ($result >= 0) {
                    CSysLog::addLog(CUserSession::getUserName(), 'MODIFY', 'User', $user_ids, json_encode($update_data));
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
                }
            } catch (InvalidArgumentException $e) {
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
                CCommon::exitWithSuccess($this, '更新完成', 'Console/Group/groups');
                return ;
            }
        }
        $user_infos = CUser::getUsersByGroup($data->required('group_id'));
        $groupOptions = CUserGroup::getGroupForOptions();
        $this->getView()->assign ('group', $group);
        $this->getView()->assign('user_infos', $user_infos);
        $this->getView()->assign('groupOptions', $groupOptions);
        $this::display('members');
    }

    public  function addAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            $commit = false;
            try {
                $exist = CUserGroup::getGroupByName($data->required('group_name'));
                if($exist){
                    throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                } else {
                    $input_data = array ('group_name' => $data->required('group_name'), 'group_desc' => $data->required('group_desc'), 'group_role' => "1,5,17,18,22,23,24,25,169" ,'owner_id' => CUserSession::getUserId() );
                    $group_id = CUserGroup::addGroup ( $input_data );
                    if ($group_id) {
                        CSysLog::addLog (CUserSession::getUserName(), 'ADD', 'UserGroup' ,$group_id, json_encode($input_data) );
                        $commit = true;
                    }
                }
            }catch (InvalidArgumentException $e) {
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
                CCommon::exitWithSuccess ($this, '账号组添加完成','Console/Group/groups');
                return;
            }
        }
        
        $this->getView()->assign("_POST" ,$_POST);
        $this::display('add');
    }

    public  function groupsAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        if ($data->optional('method', '') == 'del') {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $users = CUserGroup::getGroupUsers($data->required('group_id'));
                if (sizeof($users) > 0) {
                    throw new InvalidArgumentException(Console\ADMIN\HAVE_USER);
                }else if(intval($data->required('group_id')) === 1){
                    throw new InvalidArgumentException(Console\ADMIN\CAN_NOT_DO_FOR_SUPER_GROUP);
                }else{
                    $group = CUserGroup::getGroupById($data->required('group_id'));
                    $result = CUserGroup::delGroup($data->required('group_id'));
                    if ($result) {
                        CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'UserGroup', $data->required('group_id'), json_encode($group));
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
                CCommon::exitWithSuccess ($this, '已将账号组删除','Console/Group/groups');
                return;
            }
        }
        
        $groups = CUserGroup::getAllGroup();
        $confirm_html = CAdmin::renderJsConfirm("icon-remove");
        $this->getView()->assign('osadmin_action_confirm', $confirm_html);
        $this->getView()->assign('groups', $groups);
        $this->display('groups');
    }
    
    public function modifyAction ()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $commit = false;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $group = CUserGroup::getGroupById($data->required('group_id'));
            if(empty($group)){
                CCommon::exitWithError($this, Console\ADMIN\GROUP_NOT_EXIST, "Console/Group/groups");
                return ;
            }
            if (Protocol::getMethod() == "POST") {
                $update_data = array ('group_name' => $data->required('group_name'), 'group_desc' => $data->optional('group_desc', ''));
                $result = CUserGroup::updateGroupInfo($data->required('group_id'), $update_data);
                if ($result >= 0) {
                    CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'UserGroup' ,$data->required('group_id'), json_encode($update_data) );
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
            CCommon::exitWithSuccess($this, '账号组修改完成','Console/Group/groups');
            return;
        }
        $groupOptions = CUserGroup::getGroupForOptions();
        $this->getView()->assign('group', $group);
        $this->getView()->assign('groupOptions', $groupOptions);
        $this->display('modify');
    }
    
    public function roleAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $group_option_list = CGroupRole::getGroupForOptions();
        $group_info = CUserGroup::getGroupById($data->optional('group_id', 1));
        $role_list = CGroupRole::getGroupRoles($data->optional('group_id', 1));
        $group_role = $group_info['group_role'];
        $group_role_array = explode(',', $group_role);
        
        if (Protocol::getMethod() == "POST") {
            $commit = false;
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $menu_ids = $data->required('menu_ids');
                if ($data->optional('group_id', 1) == 1){
                    $temp = array();
                    foreach ($group_role_array as $group_role){
                        if ($group_role > 100) {
                            $temp[] = $group_role;
                        }
                    }
                    $admin_role = array_diff($group_role_array, $temp);
                    $menu_ids = array_merge($admin_role, $data->required('menu_ids'));
                    $menu_ids = array_unique($menu_ids);
                    asort($menu_ids);
                }
                $group_role = implode(',', $menu_ids);
                $group_data = array ('group_role' => $group_role );
                $result = CUserGroup::updateGroupInfo ($data->optional('group_id', 1), $group_data );
                if ($result >= 0) {
                    CSysLog::addLog(CUserSession::getUserName(), 'MODIFY', 'GroupRole' ,$data->optional('group_id', 1), json_encode($group_data) );
                    CUserSession::reload();
                    $commit = true;
                }else{
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
                CCommon::exitWithSuccess ($this, Console\ADMIN\SUCCESS_NEED_LOGIN,'Console/Group/role');
                return ;
            }
        }
        $this->getView()->assign('role_list', $role_list);
        $this->getView()->assign('group_id', $data->optional('group_id', 1));
        $this->getView()->assign('group_option_list', $group_option_list);
        $this->getView()->assign('group_role', $group_role_array);
        $this->display('role');
    }
}
