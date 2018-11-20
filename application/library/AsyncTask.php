<?php
namespace Moca\AsyncTask
{

class Task
{
    public $id;
    public $tag;
    public $payload;

    public function __construct($id, $tag, $payload)
    {
        $this->id = $id;
        $this->tag = $tag;
        $this->payload = $payload;
    }
}

}

namespace {
class AsyncTask
{
    private static function channel() {
        return Yii::$app->rabbitmq->channel();
    }

    public static function submit($tag, $payload)
    {
        $msg = new PhpAmqpLib\Message\AMQPMessage(json_encode($payload, JSON_UNESCAPED_UNICODE), ['delivery_mode' => 2]);
        self::channel()->basic_publish($msg, '', $tag);
    }

    public static function consume($tag, $callback, $batchSize = 1, $maxRequests = PHP_INT_MAX)
    {
        $channel = self::channel();
        $channel->basic_qos(null, $batchSize, null);
        $count = 0;
        $channel->basic_consume($tag, '', false, false, false, false, function($message) use ($callback, $tag, &$count) {
            $deliveryTag = $message->delivery_info['delivery_tag'];
            $commit = false;
            try {
                $commit = $callback(new Moca\AsyncTask\Task($deliveryTag, $tag, json_decode($message->body)));
            } finally {
                ++$count;
                $channel = $message->delivery_info['channel'];
                if ($commit) {
                    $channel->basic_ack($deliveryTag);
                } else {
                    $channel->basic_nack($deliveryTag, false, true);
                }
            }
        });
        while($count < $maxRequests && count($channel->callbacks)) {
            $channel->wait();
        }
    }
}
}

?>
