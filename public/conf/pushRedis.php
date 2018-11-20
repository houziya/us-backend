<?php
return function ()
{
    $redis = new Redis();
    $redis->connect('10.143.76.120', 7379, 1, NUll, 100);
    $redis->auth('8354821a0d2b5b895386302e3e875de2fd2bec8ce7a56a8e8763fff8310e9190cc536903bf7dd6be45a169dc7ec90e77a9db51bf9a079a440a7c0feecdb20096');
    return $redis;
}
?>
