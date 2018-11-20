<?php

class LogItem
{
    public $remoteAddr;
    public $requestLength;
    public $responseBodyLength;
    public $elapsed;
    public $timestamp;
    public $method;
    public $path;
    public $queryString;
    public $params;
    public $httpVersion;
    public $status;
    public $referer;
    public $userAgent;
    public $serverName;
    public $serverAddress;
    public $forwardFor;
    public $realIp;
    
    public function __construct($match, $flags, $index = 0) {
        $this->remoteAddr = $match[++$index];
        $this->requestLength = $match[++$index];
        $this->responseBodyLength = $match[++$index];
        $this->elapsed = $match[++$index];
        $this->timestamp = $match[++$index];
        $this->method = $match[++$index];
        $tmp = parse_url($match[++$index]);
        @$this->path = $tmp["path"];
        $this->queryString = @$tmp["query"];
        if ($flags & LogParser::FLAG_PARSE_QUERYSTRING && Predicates::isNotEmpty($this->queryString)) {
            parse_str($this->queryString, $this->params);
        }
        $this->httpVersion = $match[++$index];
        $this->status = $match[++$index];
        $this->referer = $match[++$index];
        $this->userAgent = $match[++$index];
        $this->serverName = $match[++$index];
        $this->serverAddress = $match[++$index];
        $this->forwardFor = $match[++$index];
        $this->realIp = $match[++$index];
    }
}

class LogParser
{
   const FLAG_PARSE_QUERYSTRING = 1;
   public static function parse($path, $callback, $flags = 0)
   {
        $file = fopen($path, "r");
        try {
            while (false !== ($row = fgets($file, 8192))) {
                $match = [];
                preg_match('#^([0-9.]*) ([0-9]*) ([0-9]*) ([0-9.]*)\[s\] - - \[(.*)\] "([A-Z]*) (.*) HTTP/([0-9.]*)" ([0-9]*) (.*) "-" "(.*)" (.*) ([0-9.]*) (.)* (.)*$#isU', trim($row), $match);
                $callback(new LogItem($match, $flags));
            }
            if (!feof($file)) {
                throw new Exception("Unexpected error while parsing log file '" . $path . "'");
            }
        } finally {
            fclose($file);
        }
   }
}

?>
