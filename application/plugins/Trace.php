<?php
use Yaf\Plugin_Abstract;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;
use Yaf\Config_Abstract;
use Yaf\Application;
use yii\log\Logger;

class TracePlugin extends Plugin_Abstract
{
    private $config = NULL;

    private static function prettyPrint(Request_Abstract $request, Response_Abstract $response)
    {
        $pretty = $request->getMethod() . ' ' . $request->getRequestURI();
        $query = $request->getQuery();
        if ($query) {
            $pretty = $pretty . "\nQuery " . print_r($query, true);
        }
        $post = $request->getPost();
        if ($post) {
            $pretty = $pretty . "\nPost " . print_r($post, true);
        }
        $body = $response->getBody();
        if ($body) {
            $pretty = $pretty . "\nResponse " . $body;
        }
        return $pretty;
    }

    public function __construct(Config_Abstract $config)
    {
        $this->config = $config;
    }

    public function postDispatch(Request_Abstract $request, Response_Abstract $response)
    {
        $message = TracePlugin::prettyPrint($request, $response);
        $category = $request->getModuleName() . '\\' . $request->getControllerName() . '::' . $request->getActionName();
        yii::getLogger()->log($message, Logger::LEVEL_TRACE, $category);
    }
}

?>
