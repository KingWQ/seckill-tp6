thinkphp6 秒杀
===============

## 项目环境

* php语言框架 thinkphp6.0+
* 消息队列中间件 RabbitMQ
* 秒杀商品缓存：Redis
* 秒杀接口压测工具：JMeter

## 使用项目
##### 1. 安装thinkphp框架
~~~
composer install
~~~

##### 2. 导入数据 ( seckill.sql ), 并进行.env数据库配置
##### 3. redis安装 和 php的redis扩展安装，并进行.env数据库配置
##### 4. RabbitMQ安装 和 rabbitmq的php扩展库，php -m 有amqp说明安装成功
##### 5. 安装框架使用rabbitmq 封装的composer包
~~~
 composer require php-amqplib/php-amqplib
~~~
##### 6. 下载JMeter压测使用

## 业务梳理
##### 1. 预热缓存数据，把秒杀商品的 库存和秒杀时间 存入缓存中。
##### 2. 秒杀接口提交地址字段address 和 商品sku sku_id，并进行数据校验
##### 3. 在缓存里校验秒杀商品是否存在、是否有库存、是否在秒杀时间内
##### 4. 在缓存里判断用户是否已经秒杀过
##### 5. 把用户id、sku_id、address写入RabbitMQ的消息队列
##### 6：处理消息使用RabbitMQ的work工作模式，一个生成者多个消费者，用于处理在短时间的HTTP请求中无法处理的复杂任务。
##### 7：处理消息，RabbitMQ消息持久化和重复消息处理
##### 8：校验商品库存 高并发下 redis还在减库存, 这一步再利用redis原子性校验库存，避免库存不足走到数据库操作耗性能。
##### 9：MySQL事务秒杀下单：减库存（乐观锁，防止库存超卖）、订单表、订单子表。
##### 10：更新redis缓存：秒杀商品库存缓存、用户下单缓存


## JMeter压测
##### 1：安装JMeter进行压测
##### 2：在进行php-fpm进程数调优、mysql连接数调优、开启opcache, 同等配置情况下还是远远及go版下的秒杀



