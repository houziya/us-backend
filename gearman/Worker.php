<?php 
define("APP_PATH", realpath(dirname(__FILE__) . '/../'));
require APP_PATH . '/conf/constants.php';

class Worker
{
    private $worker;
    private $idle = [];

    public static function redis($host = NULL, $port = NULL, $auth = NULL)
    {
        $redis = new Redis();
        if (is_null($host)) {
            $host = Us\Config\Redis\HOSTNAME;
        }
        if (is_null($port)) {
            $port = Us\Config\Redis\PORT;
        }
        if (is_null($auth)) {
        	$auth = Us\Config\Redis\AUTH;
        }
        if (!$redis->connect($host, $port)) {
            throw new Exception("Could not connect to redis");
        }
        if (!$redis->auth($auth)) {
            throw new Exception("Could not authenticate");
        }
        return $redis;
    }

    public static function create($host = NULL, $port = NULL)
    {
    	return new Worker($host, $port);
    }

    public function __construct($host = NULL, $port = NULL)
    {
        set_time_limit(0);
        ini_set('memory_limit', Us\Config\MEMORY_LIMIT);
        if (is_null($host)) {
        	$host = Us\Config\Gearman\HOSTNAME;
        }
        if (is_null($port)) {
        	$port = Us\Config\Gearman\PORT;
        }
        $worker = new GearmanWorker();
        $worker->addServer($host, $port);
        $worker->setTimeout(10000);
        $this->worker = $worker;
    }

    public function subscribe($type, $entry, $idle = NULL)
    {
    	$this->worker->addFunction($type, $entry);
    	if (is_null($idle)) {
    		$this->idle[] = $idle;
    	}
    	return $this;
    }

    public function loop()
    {
    	while (@$this->worker->work() || $this->worker->returnCode() == GEARMAN_TIMEOUT) {
    		switch ($this->worker->returnCode()) {
    			case GEARMAN_TIMEOUT:
    			    foreach ($this->idle as $entry) {
    			    	try {
    			    		$entry();
    			    	}
    			    	catch(Exception $e) {
    			    	    echo "Caught exception when dispatching idle event. \n" . var_export($ex, true) . "\n";
    			    	}
    			    }
    			case GEARMAN_SUCCESS:
    			    continue;
    			default:
    			    echo "Failed to dispatch work with return code: " . $this->worker->returnCode() . "\n";
    			    break;
    		}
    	}
    }

    public static function sendGetRequest($url)
    {
        $requestUrl = $url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    } //大大dd在在在
}
?>