<?php
define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
require APP_PATH . '/application/commands/CommandBase.php';

// crontab /usr/local/php/bin/php /usr/local/nginx/us/application/commands/example.php
error_log(print_r(time(), true) . "\n", 3, "/usr/local/nginx/us/yantao.log");

function sendGetHttp($method)
{
    $requestUrl = 'http://app.himoca.com:9990/Us/Stat/'.$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $result = curl_exec($ch);
    curl_close($ch);
    echo $result;
}


sendGetHttp('statNewUser');
sendGetHttp('statEventData');
sendGetHttp('statDau');
