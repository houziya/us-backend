<?php
return function ()
{
    $redis = new Redis();
    $redis->connect(Us\Config\Tube\Redis\HOSTNAME, Us\Config\Tube\Redis\PORT, Us\Config\Tube\Redis\TIMEOUT, NUll, Us\Config\Tube\Redis\RETRY_INTERVAL);
    $redis->auth(Us\Config\Tube\Redis\AUTH);
    return $redis;
}
?>
