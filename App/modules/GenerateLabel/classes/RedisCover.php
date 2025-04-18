<?php

namespace label;

/**
 * Class RedisCover
 * Used to create cover for redis to use it in our application
 *
 * If you want to use some another \Redis method you have to add it to self::$keyCommands or self::$cleanCommands
 * It depends on if your method uses $key variable
 * @method bool connect(string $host, int $port = 6379, float $timeout = 0.0)
 * @see \Redis::connect()
 * @method string|bool get(string $key)
 * @see \Redis::get()
 * @method bool set(string $key, string $value, int $timeout = 0)
 * @see \Redis::set()
 * @method int delete(int|array $key1, string $key2 = null, string $key3 = null)
 * @see \Redis::delete()
 * @method string[] keys(string $pattern)
 * @see \Redis::keys()
 * @method int rPush(string $key, string $value1, string $value2 = null, string $valueN = null)
 * @see \Redis::rPush()
 * @method hGetAll(string $key)
 * @see \Redis::hGetAll()
 * @method del(int|array $key1, string $key2 = null, string $key3 = null)
 * @see \Redis::del()
 * @method hSet(string $key, string $hashKey, string $value )
 * @see \Redis::hSet
 * @method setTimeout(string $key, int $ttl)
 * @see \Redis::setTimeout
 * @method int hDel(string $key, string $hashKey1, string $hashKey2 = null, string $hashKeyN = null )
 * @see \Redis::hDel
 * @method int hGet(string $key, string $hashKey)
 * @see \Redis::hGet
 * @method int publish(string $channel, string $message)
 * @see \Redis::publish
 */
class RedisCover
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @var \Redis
     */
    private $driver;

    /**
     * Methods that should recieve updated key with prefix
     * @var string[]
     */
    private static $keyCommands = [
        'get',
        'set',
        'delete',
        'keys',
        'rPush',
        'hGetAll',
        'del',
        'hSet',
        'setTimeout',
        'hDel',
        'hGet',
        'publish',
    ];

    /**
     * Methods should be runned directrly without any change
     * @var string[]
     */
    private static $cleanCommands = [
        'connect',
    ];

    /**
     * RedisCover constructor.
     * @param string $namespace
     * @throws \Exception if bad namespace passed
     */
    public function __construct($namespace)
    {
        if (strpos($namespace, '*')) {
            throw new \Exception('You can not use alike namespace');
        }

        $this->namespace = $namespace;
        $this->driver = new \Redis();
    }

    /**
     * Cover to process $key variables passed into methods
     * @param string $name
     * @param array $args
     * @return mixed
     * @throws \Exception if method was not declared before, see class declaration
     */
    public function __call($name, $args)
    {
        if (in_array($name, self::$keyCommands)) {
            if (is_array($args[0])) {
                foreach ($args[0] AS $i => $v) {
                    $args[0][$i] = $this->namespace . $v;
                }
            } else {
                if ($name != 'publish') {
                    $args[0] = $this->namespace . $args[0];
                }
            }
            if (($name === 'delete') || ($name === 'del')) {
                if (isset($args[1])) {
                    $args[1] = $this->namespace . $args[0];
                }
                if (isset($args[2])) {
                    $args[2] = $this->namespace . $args[0];
                }
            }
        } elseif (!in_array($name, self::$cleanCommands)) {
            throw new \Exception('You have to declare method ' . $name . ' to use it in cover.', 501);
        }
        $result = call_user_func_array([$this->driver, $name], $args);
        if ($name === 'keys') {
            // delete key prefix from result set
            $result = array_map(function($value) {return substr($value, strlen($this->namespace));}, $result);
        }
        return $result;
    }
}