<?php

class UsException extends Exception
{
    private $usCode;
    private $usNotice;
    private $usMessage;
    private $usPayload;
    public function __construct($code, $notice = NULL, $message = NULL, $payload = NULL, $hint = NULL)
    {
        $this->usCode = $code;
        $this->usNotice = $notice;
        $this->usMessage = $message;
        $this->usPayload = $payload;
        $this->usHint = $hint;
    }

    public function send()
    {
        Protocol::jsonReturn($this->usCode, $this->usPayload, $this->usNotice, $this->usMessage, $this->usHint);
    }
}

class HttpException extends Exception
{
    private $httpCode;
    private $httpMessage;

    public function __construct($code, $exception = NULL, $message = "")
    {
        parent::__construct($message, $code, $exception);
        $this->httpMessage = $message;
        $this->httpCode = $code;
    }

    public function send()
    {
        http_response_code($this->httpCode);
        echo $this->httpMessage;
    }
}

try {
    define("APP_PATH",  realpath(dirname(__FILE__) . '/../')); /* 指向public的上一级 */
    $app = new Yaf\Application(APP_PATH . "/conf/application.ini");
    $app->bootstrap()->run();
} catch (UsException $e) {
    $e->send();
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
} catch (HttpException $e) {
    $e->send();
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
} catch (InvalidArgumentException $e) {
    $app = Yaf\Application::app();
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    if (Predicates::isNull($app) || !$app->getConfig()->development) {
        Protocol::jsonReturn(Protocol::STATUS_BAD_REQUEST, NULL, $e->getMessage());
    } else {
        echo $e->getTraceAsString();
    }
} catch (Exception $e) {
    $app = Yaf\Application::app();
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    if (Predicates::isNull($app) || !$app->getConfig()->development) {
        Protocol::jsonReturn(Protocol::STATUS_INTERNAL_ERROR, NULL, Notice::get()->internalError());
    } else {
        echo $e->getTraceAsString();
    }
}

?>
