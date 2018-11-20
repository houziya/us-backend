<?php
return function ()
{
    $redis = new Redis();
    $redis->connect('10.66.83.117', 6379, 1, NUll, 100);
    $redis->auth('5821ecac-9143-4b29-8d98-f95177733bb8:88788D2E');
    return $redis;
}
?>
