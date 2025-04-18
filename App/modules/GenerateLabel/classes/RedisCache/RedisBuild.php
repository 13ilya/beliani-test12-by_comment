<?php
namespace label\RedisCache;

use label\DB;
use label\RedisProvider;

/**
 * Class RedisBuild
 * Used to add function for recache in queue
 */
class RedisBuild
{
    /**
     * @var mixed[]
     */
    private $queue = [];

    /**
     * @var int
     */
    private $countJobs = 0;
    
    public function __construct()
    {
        \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));
    }
    
    /**
     * Push to queue
     * @param string $lang
     * @param string $url url without domain with leading slash
     * @return string[]
     */
    public function pushClearQueue($function, $shop = 0, $lang = '')
    {
        $jobs = [
            'action' => 'clear',
            'function' => $function,
            'shop' => $shop,
            'lang' => $lang,
        ];
        
        $keyQueue = md5(serialize($jobs));
        
        if (!isset($this->queue[$keyQueue])) {
            $this->queue[$keyQueue] = true;
            $identifier = str_pad(++$this->countJobs, 5, '0', STR_PAD_LEFT);
            $token = \Resque::enqueue('redis', '\label\RedisCache\RedisJob', $jobs);
            
            $message =
                '[' . $identifier . ']function:' . $function . '|token:' . $token;
        } else {
            $message = '[00000]duplicate';
        }
        
        return $message;
    }
    
    /**
     * Push to queue
     * @param string $lang
     * @param string $url url without domain with leading slash
     * @return string[]
     */
    public function pushQueue($function)
    {
        $jobs = [
            'action' => 'recache',
            'function' => $function,
        ];
        
        $keyQueue = md5(serialize($jobs));
        
        if (!isset($this->queue[$keyQueue])) {
            $this->queue[$keyQueue] = true;
            $identifier = str_pad(++$this->countJobs, 5, '0', STR_PAD_LEFT);
            $token = \Resque::enqueue('redis', '\label\RedisCache\RedisJob', $jobs);
            $message =
                '[' . $identifier . ']function:' . $function . '|token:' . $token;
        } else {
            $message = '[00000]duplicate';
        }
        
        return $message;
    }
}