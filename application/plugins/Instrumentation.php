<?php
use Yaf\Plugin_Abstract;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;
use Yaf\Config_Abstract;
use Yaf\Application;

class InstrumentationPlugin extends Plugin_Abstract
{
    public function __construct(Config_Abstract $config)
    {
    }

    public function preDispatch(Request_Abstract $request, Response_Abstract $response)
    {
        $this->start = microtime(true);
    }

    public function postDispatch(Request_Abstract $request, Response_Abstract $response)
    {
        yii::info($request->getRequestURI() . ' : ' . strval(intval((microtime(true) - $this->start) * 1000000)) . ' us', 'profiling');
    }
}

?>
