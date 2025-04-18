<?php

namespace label\DBManager;

use label\DB;

/**
 * Class Table
 * Used to work with single table for synchronization.
 */
class Table
{
    private static $copyableLimit = 104857600;//1024 * 1024 * 100 = 100 Mb

    private $name;
    private $metadata;
    private $lastSyncAttempt;
    private $lastSyncSuccess;

    /**
     * Table constructor.
     * @param string $name table name
     * @param \MDB2_Driver_mysql $dbr_remote connection to remote database
     */
    public function __construct($name, \MDB2_Driver_mysql $dbr_remote)
    {
        $this->name = $name;
        $this->metadata = $dbr_remote->getRow(
            '
                SHOW TABLE STATUS 
                FROM ' . DB_NAME . ' 
                WHERE Name = ?
            ',
            null,
            [$name]
        );

        $dbr = DB::getInstance(DB::USAGE_READ);
        $syncMetadata = $dbr->getRow(
            '
                SELECT last_sync_attempt, last_sync_success
                FROM sync_production_tables
                WHERE name = ?',
            null,
            [$name]
        );
        $this->lastSyncAttempt = $syncMetadata->last_sync_attempt;
        $this->lastSyncSuccess = $syncMetadata->last_sync_success;
    }

    /**
     * Magic method to work with getters
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get'.ucfirst($name);
        if(method_exists($this, $getter)) {
            return $this->$getter();
        }
        throw new \BadMethodCallException();
    }

    /**
     * Get approximate size of the table
     * @return int size in bytes
     */
    public function getSize()
    {
        return $this->metadata->Data_length;
    }

    /**
     * Get approximate count of rows in the table
     * @return int
     */
    public function getRowsCount()
    {
        return $this->metadata->Rows;
    }

    /**
     * Get table name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get time when was last attempt to sync
     * @return string YYYY-MM-DD HH:MM:SS
     */
    public function getLastSyncSuccess()
    {
        return $this->lastSyncSuccess;
    }

    /**
     * Get time when was last successful sync
     * @return string YYYY-MM-DD HH:MM:SS
     */
    public function getLastSyncAttempt()
    {
        return $this->lastSyncAttempt;
    }

    /**
     * Check if table can be copied from remote server
     * @return bool
     */
    public function getCopyable()
    {
        return ($this->metadata->Data_length <= self::$copyableLimit);
    }
}