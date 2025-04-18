<?php
namespace label;

/**
 * Class DB
 * Used for work with database.
 * Currently used artful interpretation of singleton
 * @package label
 */
class DB
{
    const USAGE_READ = 1;
    const USAGE_WRITE = 2;

    private static $instances = array();

    /**
     * DB constructor.
     * Restrict creating new objects.
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * Return instance for access to database
     * @param int $usage currently for read or write, use const USAGE_*
     * @return \MDB2_Driver_mysql
     * @throws \Exception if instance not found
     */
    public static function getInstance($usage)
    {
        if (isset(self::$instances[$usage])) {
            return self::$instances[$usage];
        }
        throw new \Exception('Can not find db instance '.$usage);
    }

    /**
     * Set instance for access to database
     *
     * @param int $usage
     * @param \MDB2_Driver_mysql $instance
     */
    public static function setInstance($usage, \MDB2_Driver_mysql $instance)
    {
        self::$instances[$usage] = $instance;
    }
}