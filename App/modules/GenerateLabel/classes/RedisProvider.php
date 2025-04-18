<?php

namespace label;

/**
 * Class RedisProvider
 * Used to provide redis connections to use it in our application
 */
class RedisProvider
{
    const USAGE_CACHE = 0;
    const USAGE_CACHE_LOCAL = 16;
    const USAGE_NOTIFICATION = 1;
    const USAGE_QUEUE = 2;

    const DATABASES_COUNT = 16;

    /**
     * @var \Redis[]
     */
    private static $drivers;

    /**
     * @param int $purpose purpose index to use this connection, could be one of USAGE_* const
     *      if $purpose >= 16 - used local redis database
     * @return \Redis
     */
    public static function getInstance($purpose)
    {
        $dbIndex = self::getDatabaseIndex($purpose % self::DATABASES_COUNT);
        if ($purpose >= self::DATABASES_COUNT) {
            $local = true;
        } else {
            $local = false;
        }

        if (!isset(self::$drivers[$purpose])) {
            $driver = new \Redis();
            /*
             * @todo check and log bad connection to redis
             */
            if ($local) {
                $driver->connect('127.0.0.1');
            } else {
                $driver->connect(REDIS_HOST);
            }
            if ($dbIndex !== 0) {
                $driver->select($dbIndex);
            }
            self::$drivers[$purpose] = $driver;
        }
        return self::$drivers[$purpose];
    }

    /**
     * Tells what database index used for particular purpose.
     * This method split databases on develop and heap (that are used on single host)
     * @param int $purpose purpose index to use this connection, could be one of USAGE_* const
     * @return int
     */
    public static function getDatabaseIndex($purpose)
    {
        if (APPLICATION_ENV === 'heap') {
            return (int)$purpose + 8;
        }
        return (int)$purpose;
    }

    /**
     * RedisProvider constructor.
     * Restrict creating new objects.
     */
    private function __construct() {}

    /**
     * RedisProvider cloning.
     * Restrict cloning into new objects.
     */
    private function __clone() {}
}