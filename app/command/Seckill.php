<?php
declare (strict_types=1);

namespace app\command;

use app\services\redis\Predis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\facade\Db;

class Seckill extends Command
{
    protected function configure()
    {
        $this->setName('seckill')->setDescription('秒杀队列服务端');
    }

    protected function execute(Input $input, Output $output)
    {
        $conn    = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest', '/');
        $channel = $conn->channel();
        $channel->queue_declare("tp6_seckill", false, true, false, false);
        $channel->basic_consume("tp6_seckill", '', false, false, false, false, function ($msg) use ($output) {
            //0：获取队列消息id
            $msgHeaders = $msg->get('application_headers')->getNativeData();
            $corrId     = $msgHeaders['correlation_id'];
            $msgKey     = "seckill_msg:" . $corrId;

            //1：判断队列消息 是否重复消费
            $redis = Predis::getInstance();
            if (!$redis->get($msgKey)) {
                //$output->writeln("repeat " . $corrId . PHP_EOL);
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                return;
            }

            $msgData = json_decode($msg->body, true);

            //2：数据库校验商品库存 高并发下 redis还在减库存 所以有可能会走到这一步
            // 防止库存超卖 在使用redis情况，也在秒杀下单时 数据库查一下真正的库存  下单时用乐观锁进行减库存
            $stock = $redis->get("seckill_sku:" . $msgData["sku_id"]);
            if (empty($stock) || $stock <= 0) {
                //$output->writeln(" no stock " . $corrId . PHP_EOL);
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                $redis->del($msgKey);
                return;
            }


            //3: 秒杀下单
            Db::startTrans();
            try {
                //1: 乐观锁减库存
                $row = Db::name('product_skus')
                    ->where('id', $msgData["sku_id"])
                    ->where('stock', '>=', '1')
                    ->dec('stock')
                    ->update();
                if($row != 1){
                    throw new Exception('库存不足');
                }

                //2:写入订单表
                $no        = make_no();
                $orderData = [
                    'no'           => $no,
                    'user_id'      => $msgData['user_id'],
                    'address'      => $msgData['address'],
                    'total_amount' => 1,
                    'created_at'   => date('Y-m-d H:i:s')
                ];
                $orderId   = Db::name('orders')->insert($orderData);

                //3：写入订单子表
                $itemData = [
                    'order_id'       => $orderId,
                    'product_id'     => 1,
                    'product_sku_id' => $msgData["sku_id"],
                    'amount'         => 1,
                    'price'          => 1,
                ];
                Db::name('order_items')->insert($itemData);

                $output->writeln("success-" . $msgData['user_id'] . PHP_EOL);

                // 提交事务
                Db::commit();
                $redis->decr("seckill_sku:" . $msgData["sku_id"]);
                $redis->set("seckill_order_" . $msgData["sku_id"] . ':' . $msgData["user_id"], $orderId);

                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                $redis->del($msgKey);

            } catch (\Exception $e) {
                //$output->writeln($e->getMessage() . PHP_EOL);
                Db::rollback();
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                $redis->del($msgKey);
            }

        });

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        //关闭信道
        $channel->close();
        //关闭连接
        $conn->close();
    }
}
