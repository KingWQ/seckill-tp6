<?php

namespace app\controller;

use app\BaseController;
use app\services\mq\Mq;
use app\services\redis\Predis;
use think\facade\Cache;
use think\facade\Db;

class Order extends BaseController
{
    //高并发可能出现502 504 500错误
    public function seckill()
    {
        $userId  = rand(1, 100);
        $skuId   = $this->request->param('sku_id');
        $address = $this->request->param('address');

        if (empty($address)) {
            return show_json(4001, '地址不能为空');
        }
        if (empty($skuId)) {
            return show_json(4002, '商品sku_id不能为空');
        }

        //1：缓存中校验库存
        $redis = Predis::getInstance();
        $stock = $redis->get("seckill_sku:" . $skuId);
        if (is_null($stock)) {
            return show_json(4003, '该商品不存在');
        }
        if ($stock < 1) {
            return show_json(4005, '该商品已售完');
        }

        //2: 校验秒杀是否开始
        $cacheTime = $redis->get("seckill_sku_expire:".$skuId);
        $expireTime = json_decode($cacheTime,true);

        if (time() < strtotime($expireTime['start_at'])) {
            return show_json(4006, '秒杀尚未开始');
        }
        if (time() > strtotime($expireTime['end_at'])) {
            return show_json(4007, '秒杀已经结束');
        }

        //3：判断用户是否已经秒杀过 因为写到的随机用户id 在这里判断
        $orderId = $redis->get("seckill_order_".$skuId.':' . $userId);
        if ($orderId) {
            return show_json(4008, '你已经秒杀过');
        }

        $data = [
            'task_name' => 'seckill_order',
            'user_id'   => $userId,
            'sku_id'    => $skuId,
            'address'   => $address,
            'time'      => date('Y-m-d H:i:s'),
        ];
        (new Mq())->publish('tp6_seckill', $data, '');

        return show_json(2000, '开始秒杀');
    }

    public function setStock()
    {
        $redis= Predis::getInstance();
        $redis->set("seckill_sku:1" ,10);
        $expireTime = [
            'start_at'=>"2021-01-15 00:00:00",
            'end_at'=>"2021-02-15 00:00:00",
        ];
        $redis->set("seckill_sku_expire:1" ,json_encode($expireTime));

    }

    public function getStock()
    {
        $redis= Predis::getInstance();
        dump($redis->get("seckill_sku:1"));
    }
}
