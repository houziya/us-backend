<?php
use Yaf\Bootstrap_Abstract;
use Yaf\Dispatcher;
use Yaf\Application;
use Yaf\Config\Ini;
use Yaf\Route\Rewrite;

class Bootstrap extends Bootstrap_Abstract
{
    public function _initAutoLoad(Dispatcher $dispatcher)
    {
        require(__DIR__ . '/../vendor/autoload.php');
        require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');
    }

    public function _initConstants(Dispatcher $dispatcher)
    {
        require(__DIR__ . '/../conf/constants.php');
    }

    public function _initYii(Dispatcher $dispatcher)
    {
        $config = require(__DIR__ . '/../conf/yii.php');
        new yii\web\Application($config);
    }

    public function _initDisableAutoRender(Dispatcher $dispatcher)
    {
        $dispatcher->autoRender(FALSE);
    }

    public function _initPlugins(Dispatcher $dispatcher)
    {
        $config = Application::app()->getConfig();
        if ($config->instrument->enabled == true) {
            $dispatcher->registerPlugin(new InstrumentationPlugin($config));
        }
        if ($config->trace->enabled == true) {
            $dispatcher->registerPlugin(new TracePlugin($config));
        }
//         if ($config->verifica->enabled == false) {
//             $dispatcher->registerPlugin(new Verification($config));
//         }
    }

    public function _initRoute(Dispatcher $dispatcher)
    {
        $config = Application::app()->getConfig();
        if ($config->content->generator->enabled == true) {
            $dispatcher->getRouter()->addRoute("coverpage", new Rewrite("event/coverpage/:fileName", ["module" => "Us", "controller" => "Content", "action" => "generateCoverPage"]));
            $dispatcher->getRouter()->addRoute("moment", new Rewrite("event/moment/:fileName", ["module" => "Us", "controller" => "content", "action" => "generateMoment"]));
            $dispatcher->getRouter()->addRoute("live", new Rewrite("event/live/:fileName", ["module" => "Us", "controller" => "content", "action" => "generateLive"]));
            $dispatcher->getRouter()->addRoute("avatar", new Rewrite("profile/avatar/:fileName", ["module" => "Us", "controller" => "Content", "action" => "generateAvatar"]));
            $dispatcher->getRouter()->addRoute("invite", new Rewrite("i/:code", ["module" => "Us", "controller" => "Event", "action" => "redirection"]));
            $dispatcher->getRouter()->addRoute("share", new Rewrite("s/:code", ["module" => "Us", "controller" => "Event", "action" => "redirection"]));
            $dispatcher->getRouter()->addRoute("gInvite", new Rewrite("gi/:code", ["module" => "Us", "controller" => "Group", "action" => "redirection"]));
            $dispatcher->getRouter()->addRoute("gShare", new Rewrite("gs/:code", ["module" => "Us", "controller" => "Group", "action" => "redirection"]));
            $dispatcher->getRouter()->addRoute("groupCoverpage", new Rewrite("group/coverpage/:fileName", ["module" => "Us", "controller" => "Content", "action" => "generateGroupCoverPage"]));
        }
    }

    public function _initCrossOriginAccessControl($dispatcher)
    {
        if (Predicates::isNotEmpty($origin = Protocol::origin()) && Predicates::isNotEmpty($url = @parse_url($origin)) && Predicates::isNotEmpty($host = @$url["host"])) {
            if (in_array($host, explode(",", Us\Config\ALLOWED_ORIGIN))) {
                header("Access-Control-Allow-Origin: $origin");
            }
        }
    }
}
?>
