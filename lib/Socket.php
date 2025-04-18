<?php

namespace lib;

class Socket
{
 
    public $host;
    public $port = 6379;
 
    private $_redis;
    
    public function __construct() {
        $this->host = REDIS_HOST;
    }
 
    /**
     * Return redis queue
     * @return redis
     */
    private function _redis()
    {
        if ( ! $this->_redis) {
            $this->_redis = new \Redis();
            $this->_redis->connect($this->host, $this->port);
        }
        return $this->_redis;
    }

    /**
     * Publish data to channel
     * @param String $data
     */
    public function publishEvent($data)
    {
        $this->_redis()->publish('prolo-channel', $data);
    }
}