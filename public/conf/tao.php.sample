<?php
return function ()
{
    $redis = new Redis();
    $redis->connect(Us\Config\Tao\Redis\HOSTNAME, Us\Config\Tao\Redis\PORT, Us\Config\Tao\Redis\TIMEOUT, NUll, Us\Config\Tao\Redis\RETRY_INTERVAL);
    $redis->auth(Us\Config\Tao\Redis\AUTH);
    return $redis;
}
?>
