<?php
use PhpAmqpLib\Connection\AMQPStreamConnection;

return function ()
{
    return new AMQPStreamConnection(Us\Config\RabbitMQ\HOSTNAME, Us\Config\RabbitMQ\PORT, Us\Config\RabbitMQ\USERNAME, Us\Config\RabbitMQ\PASSWORD, Us\Config\RabbitMQ\VHOST);
}
?>
