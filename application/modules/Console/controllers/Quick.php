<?php 
use Yaf\Controller_Abstract;
class QuickController extends Controller_Abstract
{
    /*便签列表*/
    public function listAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        /* 分页数据 */
        if (Protocol::getMethod() == "GET") {
            $page_no = $data->optional('page_no','');
        } else {
            $page_no = '';
        }
        $row_count = CQuickNote::count ();
        $page_size = Console\ADMIN\PAGE_SIZE;
        $page_no = $page_no < 1 ? 1 : $page_no;
        $total_page = $row_count % $page_size==0 ? $row_count / $page_size : ceil($row_count / $page_size);
        $total_page = $total_page<1?1:$total_page;
        $page_no = $page_no > ($total_page) ? ($total_page) : $page_no;
        $start = ($page_no - 1) * $page_size;
        $pageHtml = CPagination::showPager("list", $page_no, Console\ADMIN\PAGE_SIZE, $row_count);//分页获取
        $quickNotes = CQuickNote::getNotes($start,$page_size);//获取数据
        
        /*用户信息 */
        $currentUserInfo=CUserSession::getSessionInfo();
        $userGroup = $currentUserInfo['user_group']; //当前用户的所属群组
        $currentUserId = $currentUserInfo['user_id'];//当前用户的用户ID       
        $commit = false;
        if ($data->optional('method', '') == 'del') {
            $transaction = Yii::$app->db->beginTransaction();
            try{
                $note = CQuickNote::getNoteById($data->required('noteId'));
                if ($userGroup == 1 || $note['owner_id'] == $currentUserId) {
                    $result = CQuickNote::delNote($data->required('noteId'));
                    if ($result) {
                            CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'QuickNotes', $data->required('noteId'), json_encode($note));
                            $commit = true;
                        } else {
                            CAdmin::alert($this, "error");
                        }
                } else {
                    throw new InvalidArgumentException(Console\ADMIN\QUICKNOTE_NOT_OWNER);
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
                CCommon::exitWithSuccess ($this, '便签已删除','Console/Quick/list');
                return;
            }
            
        }
        $confirm = CAdmin::renderJsConfirm("icon-remove");
        $this->getView()->assign('confirm', $confirm);//确认信息
        $this->getView()->assign('quickNotes', $quickNotes);
        $this->getView()->assign('userGroup', $userGroup);
        $this->getView()->assign('currentUserId', $currentUserId);
        $this->getView()->assign('pageHtml', $pageHtml);
        
        $this->display('list');
    }
    /* 添加便签 */
    public function addAction()
    {
        CInit::config($this);
        $commit = false;
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            $transaction = Yii::$app->db->beginTransaction();
            try{
                $noteContent = strip_tags($data->required('noteContent'));
                $inputData = array ('note_content' =>$noteContent  , 'owner_id' => CUserSession::getUserId());
                $row = CQuickNote::addNote ( $inputData );
                if ($row > 0 ) {
                    CSysLog::addLog (CUserSession::getUserName(), 'ADD', 'QuickNotes', $row, json_encode($inputData));
                    $commit = true;
                } else {
                    CAdmin::alert($this, "error");
                }
            }catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction->commit();
                } else {
                    $transaction->rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this, '添加便签成功','Console/Quick/list');
                return;
            }
        }
        $this->display('add');
    }
    /* 修改便签 */
    public function modifyAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        $commit = false;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $quickNote = CQuickNote::getNoteById ($data->required('noteId'));
            if(empty($quickNote)){
                CCommon::exitWithError($this, Console\ADMIN\NOTE_NOT_EXIST, "Console/Quick/list");
                return ;
            }
            if (Protocol::getMethod() == "POST") {
                
                /*用户信息 */
                $currentUserInfo=CUserSession::getSessionInfo();
                $userGroup = $currentUserInfo['user_group']; //当前用户的所属群组
                $currentUserId = $currentUserInfo['user_id'];//当前用户的用户ID
               if ( $userGroup ==1 || $quickNote['owner_id'] == $currentUserId) {
                   $noteContent = strip_tags($data->required('noteContent'));
                   $updateData = array ('note_content' => $noteContent);
                   $result = CQuickNote::updateNote( $data->required('noteId'),$updateData );
                   if ($result >= 0) {
                       CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'QuickNotes' ,$data->required('noteId'), json_encode($updateData) );
                       $commit = true;
                   } else {
                       CAdmin::alert($this, "error");
                   }
               } else {
                   throw new InvalidArgumentException(Console\ADMIN\QUICKNOTE_NOT_OWNER);
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
            CCommon::exitWithSuccess($this, '便签修改完成','Console/Quick/list');
            return;
        }
        $this->getView()->assign('quickNote', $quickNote);
        $this->display('modify');
    }
    
}

?>