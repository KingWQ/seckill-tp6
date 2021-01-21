<?php

namespace app\services\mq;

use app\services\redis\Predis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\facade\Cache;

class Mq
{
    private  $conn;

    public function __construct()
    {
        $this->conn = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest', '/',false,'AMQPLAIN',null,'en_US',60.0,60.0);
    }

    //发送端 简单模式-工作模式
    public  function publish(string $queue, array $body, string $exchange = '')
    {
        //1：连接
        if (!$this->conn->isConnected()) {
            $this->conn->reconnect();
        }
        //2：创建信道
        $channel = $this->conn->channel();

        //3：创建队列 并持久化
        $channel->queue_declare($queue, false, true, false, false);

        //4：消息持久化
        $message = new AMQPMessage(json_encode($body), [
            'content_type'  => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        //5：设置消息id防止重复消费
        $currId = uniqid();
        $headers = new AMQPTable(["correlation_id" => $currId]);
        $message->set('application_headers', $headers);
        $redis = Predis::getInstance();
        $redis->set('seckill_msg:'.$currId,  $currId, 3600);

        //6：发送消息
        $channel->basic_publish($message, $exchange, $queue);

        //7：关闭信道
        $channel->close();

        //8：关闭连接
        $this->conn->close();

        return true;
    }


}