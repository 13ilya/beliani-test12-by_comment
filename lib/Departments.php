<?php

require_once 'PEAR.php';

/**
 * Departments operation class
 *
 * @version 0.1
 * 
 * @param MDB2_Driver_mysql $_db database write/ read object identifier
 *
 * @param MDB2_Driver_mysql $_dbr database read (only) object identifier
 * 
 * @param int department id 
 *
 * @return void
 */
class Departments
{

    public
            $id;
    protected
            $_db;
    protected
            $_dbr;

    function __construct()
    {
        $this->_db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $this->_dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    }

    static function listAll()
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $result = $dbr->getAll("SELECT `id`, `name` FROM `emp_department` ORDER BY `name` ASC");
        if (PEAR::isError($result)) {
            var_dump($result);
            return;
        }
        
        return $result;
    }
    
    /**
     * @description get associative array
     * @param object $db
     * @param object $dbr
     * @param string $where
     * @return associative array
     */
    static function listAllAssoc(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $where = '') 
    {
        $result = $dbr->getAssoc("SELECT `id`, `name` FROM `emp_department` WHERE 1 " . $where . " ORDER BY `name` ASC");
        if (PEAR::isError($result)) {
            var_dump($result);
            return;
        }

        return $result;
    }

}
