<?php
namespace app\services\redis;

ini_set('default_socket_timeout', -1);

class Predis
{
    private static $instance;

    static function getInstance(...$args)
    {
        if(!isset(self::$instance)){
            self::$instance = new static(...$args);
        }
        return self::$instance;
    }

    public $redis = "";

    private function __construct() {
        ini_set('default_socket_time', -1);

        if(!extension_loaded('redis')) {
            throw new \Exception("redis.so文件不存在");
        }

        try {
            $this->redis = new \Redis();
            $result = $this->redis->connect(env("redis.host"), env("redis.port"), env("redis.time_out"));
        } catch(\Exception $e) {
            throw new \Exception("redis服务异常");
        }

        if($result === false) {
            throw new \Exception("redis 链接失败");
        }

        $password = env("redis.pwd") ?? '';
        if( !empty($password) ){
            $result = $this->redis->auth('pwd');
            if($result === false) {
                throw new \Exception("redis 认证失败");
            }
        }

    }

    /**
     * @note 当类中不存在该方法时候，直接调用call 实现调用底层redis相关的方法
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->redis->$name(...$arguments);
    }


    public function get($key)
    {
        if(empty($key)) return '';
        return $this->redis->get($key);
    }

    public function set($key, $value, $time = 0)
    {
        if(empty($key)) return '';

        if(is_array($value)) {
            $value = json_encode($value);
        }

        if(!$time) {
            return $this->redis->set($key, $value);
        }
        return $this->redis->setex($key, $time, $value);
    }

    public function lPop($key)
    {
        if(empty($key)) return '';
        return $this->redis->lPop($key);
    }

    public function rPush($key, $value)
    {
        if(empty($key)) return '';
        return $this->redis->rPush($key, $value);
    }


}