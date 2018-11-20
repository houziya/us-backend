<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class NoticeController extends Controller_Abstract
{
    public function MessageAction ()
    {
        $this->getView()->assign('type', $this->_request->getParam("type"));
        $this->getView()->assign('page_title', $this->_request->getParam("page_title"));
        $this->getView()->assign('message_detail', $this->_request->getParam("message_detail"));
        $this->getView()->assign('forward_url', $this->_request->getParam("forward_url"));
        $this->getView()->assign('forward_title', $this->_request->getParam("forward_title"));
    
        $this->display('message');
    }
}