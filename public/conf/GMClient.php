<?php 
return function()
{
    $client = new GearmanClient();
    // add job server
    $config = [['host' => Us\Config\Gearman\HOSTNAME, 'port' => Us\Config\Gearman\PORT]];
    foreach ($config as $value)
    {
        $host = trim(strval($value['host']));
        $port = array_key_exists('port', $value) ? intval($value['port']) : 4730;
        $client->addServer($host, $port);
    }
    return $client;
}
?>
