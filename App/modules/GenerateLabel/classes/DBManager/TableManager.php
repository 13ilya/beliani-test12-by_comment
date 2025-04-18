<?php

namespace label\DBManager;

use label\DB;

/**
 * Class TableManager
 * Class used to work with set of tables for synchronization.
 */
class TableManager
{
    private static $dbrRemote;

    /**
     * Return list of tables from remote server
     * @return Table[]
     */
    public static function getTablesList()
    {
        $tablesList = self::getRemoteConnection()->getAll('
            SHOW FULL TABLES
            FROM prologis2
            WHERE Table_type = \'BASE TABLE\'
        ');

        $list = [];
        foreach ($tablesList as $table) {
            $list[] = new Table($table->Tables_in_prologis2, self::getRemoteConnection());
        }
        return $list;
    }

    /**
     * Copy table from remote server
     * @param string $name
     * @return bool was it success or not (true is not guarantee that migration was made successfully)
     * @todo make not procedure, but function in sql, and return if transaction was successfully finished
     */
    public static function copy($name)
    {
        self::registerSyncAttempt($name);

        $tmpFile = TMP_DIR . '/copy_table_' . time() . '_' . $name . '.sql';

        $file = fopen($tmpFile, 'w');

        fwrite(
            $file,
            '
                DELIMITER $$

                DROP PROCEDURE IF EXISTS `single_transaction`;

                CREATE PROCEDURE `single_transaction`()
                BEGIN
                    DECLARE `_rollback` BOOL DEFAULT 0;
                    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET `_rollback` = 1;
                    START TRANSACTION;

                    DELETE FROM `' . $name . '`;' . "\n\n"
        );
        self::writeInsertRows($name, $file);
        fwrite(
            $file,
            '
                    IF `_rollback` THEN
                        ROLLBACK;
                    ELSE
                        COMMIT;
                    END IF;
                END$$

                DELIMITER ;

                CALL single_transaction();

                DROP PROCEDURE `single_transaction`;'
        );

        fclose($file);

        $success = self::applySql($tmpFile);

        if ($success) {
            self::registerSyncSuccess($name);
        }

        return $success;
    }

    /**
     * Run prepared sql
     * @param string $filePath path to prepared sql file
     * @return bool was it success or not
     */
    private static function applySql($filePath)
    {
        $command = 'mysql -h ' . DB_HOST . ' -u' . DB_WRITE_USER . ' -p' . DB_WRITE_PASSWORD . ' ' . DB_NAME . ' < ' . $filePath;
        $unused = null;
        $outputCode = null;
        exec($command, $unused, $outputCode);
        if ($outputCode == 0) {
            return true;
        }
        return false;
    }

    /**
     * Make and write into file sql to insert rows in table
     * @param string $name table name
     * @param resource $file pointer to file
     */
    private static function writeInsertRows($name, $file)
    {
        $columnsRaw = self::getRemoteConnection()->getAll('SHOW COLUMNS IN `' . $name . '`');
        $columns = array_map(function($table){return $table->Field;}, $columnsRaw);
        $rows = self::getRemoteConnection()->getAll('SELECT * FROM `' . $name . '`', null, [], null, MDB2_FETCHMODE_ORDERED);
        if (count($rows) > 0) {
            $lineNum = 0;
            foreach ($rows as $row) {
                $row = array_map(function($element){return mysql_real_escape_string($element);}, $row);
                if ($lineNum === 0) {
                    $line = 'INSERT INTO `' . $name . '` (`' . implode('`, `', $columns). '`) VALUES ';
                } else {
                    if ($lineNum !== 1) {
                        $line = ',' . "\n";
                    } else {
                        $line = "\n";
                    }
                    $line .= '(\'' . implode('\', \'', $row) . '\')';
                }
                $lineNum++;
                fwrite($file, $line);
            }
            fwrite($file, ';' . "\n\n");
        }
    }

    /**
     * Register attempt to sync tables
     * @param string $name
     */
    private static function registerSyncAttempt($name)
    {
        $db = DB::getInstance(DB::USAGE_WRITE);
        $db->exec('
            INSERT INTO sync_production_tables
            (name, last_sync_attempt)
            VALUES (\'' . $name . '\', NOW())
            ON DUPLICATE KEY UPDATE last_sync_attempt = NOW()
        ');
    }

    /**
     * Register success of sync table
     * @param string $name
     */
    private static function registerSyncSuccess($name)
    {
        $db = DB::getInstance(DB::USAGE_WRITE);
        $db->exec('
            UPDATE sync_production_tables
            SET last_sync_success = NOW()
            WHERE name = \'' . $name . '\'
        ');
    }

    /**
     * Connects to remote database and returns that connection
     * @return \MDB2_Driver_mysql
     */
    private static function getRemoteConnection()
    {
        if (!isset(self::$dbrRemote)) {
            self::$dbrRemote = dblogin(DB_PRODUCTION_READ_USER, DB_PRODUCTION_READ_PASSWORD, DB_PRODUCTION_READ_HOST, DB_PRODUCTION_READ_NAME);
        }
        return self::$dbrRemote;
    }
}