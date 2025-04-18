<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 09.10.2017
 * Time: 8:09
 */

class ModelTableFactory
{
    /**
     * @var ModelTable[]
     */
    protected static $instances;

    /**
     * @param string $tableName
     *
     * @return ModelTable
     */
    public static function getTableInstance($tableName)
    {
        if (!isset(static::$instances[$tableName])) {
            static::$instances[$tableName] = new ModelTable($tableName);
        }

        return static::$instances[$tableName];
    }
}