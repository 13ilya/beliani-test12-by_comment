<?php

require_once 'PEAR.php';

/**
 * Employees operation class
 *
 * @version 0.1
 * 
 * @param MDB2_Driver_mysql $_db database write/ read object identifier
 *
 * @param MDB2_Driver_mysql $_dbr database read (only) object identifier
 * 
 * @param int employee id 
 *
 * @return void
 */
class Employees {

    protected $_db;
    protected $_dbr;
    
    private $_id;
    protected $_data;
    /**
     * Manager's type: manager
     */
    const MANAGER =  'purch';
    /**
     * Manager's type: assistant manager
     */
    const MANAGER_ASSIST = 'assist';

    function __construct(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $employee = false) {
        $this->_db = $db;
        $this->_dbr = $dbr;
        
        if ( ! $employee) {
            $result = $this->_db->query("EXPLAIN `employee`");
            $this->_id = 0;
            $this->_data = new stdClass;
            while ($field = $result->fetchRow()) {
                if ($field->Field != 'id') {
                    $this->_data->{$field->Field} = '';
                }
            }
        }
        else if (is_string ($employee)) {
            $employee = mysql_real_escape_string($employee);
            $this->_data = $this->_db->query("SELECT * FROM `employee` WHERE `username` = '$employee'")->fetchRow();
            $this->_id = $this->_data->id;
            unset($this->_data->id);
        }
        else if (is_int ($employee)) {
            $this->_data = $this->_db->query("SELECT * FROM `employee` WHERE `id` = '$employee'")->fetchRow();
            $this->_id = $this->_data->id;
            unset($this->_data->id);
        }
    }
    
    public function status() {
        $query = "SELECT `config`, `title_state` FROM `timestamp_states` WHERE `id` = (SELECT `status_id` FROM `emp_vacation_sick` 
            WHERE DATE(NOW()) BETWEEN `date_from` AND `date_to` AND `username` = '{$this->_data->username}' AND 
            ((`direct_sv_applied` AND `main_sv_applied`))  LIMIT 1)";
        $status = $this->_dbr->getAssoc($query);
        
        if ( ! $status || ! $status[array_shift(array_keys($status))]) {
            $query_sick = "(SELECT `status_id` FROM `emp_vacation_sick` 
                WHERE DATE(NOW()) BETWEEN `date_from` AND `date_to` AND `username` = '{$this->_data->username}' AND (`direct_sv_applied` AND `main_sv_applied`) LIMIT 1)";
            $query_login = "(SELECT IF((SELECT `login` FROM `user_timestamp` WHERE `username` = '{$this->_data->username}' ORDER BY `time` DESC LIMIT 1) = 1, '1', '2'))";
            $query = "SELECT `config`, `title` FROM `timestamp_states` WHERE `id` = (SELECT IFNULL($query_sick, $query_login))";
            $status = $this->_dbr->getAssoc($query);
        }

        $color = array_keys($status);
        $color = array_shift($color);
        return [
            'status' => ucfirst(str_replace('_', ' ', $status[$color])),
            'color' => Config::get($this->_db, $this->_dbr, $color)
        ];
    }

    public function setActive($status) {
        $status = (int)$status;
        $this->_db->query("UPDATE `employee` SET `inactive` = $status WHERE `id` = {$this->_id}");
    }
    
    //------------------------------------------------------------------------------------------------------------------

    static function listAll(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $inactive = ' `e`.`inactive` = 0 ') {
		$query = "SELECT `e`.`id`, `u`.`name`, `e`.`username`, 
                    `e`.`name` AS `fname`, `e`.`name2` AS `lname`, 
                    `e`.`department_id`, `u`.`system_username` 
                FROM `employee` AS `e` 
                INNER JOIN `users` AS `u` ON `e`.`username` = `u`.`username`" . 
                ($inactive ? " WHERE $inactive " : '') . 
                " ORDER BY `e`.`username` ASC";
        
        $result = $dbr->getAll($query);
        if (PEAR::isError($result)) {
            var_dump($result);
            return;
        }
        
        return $result;
    }
    
    //------------------------------------------------------------------------------------------------------------------

    public function __set($name, $value) {
        switch ($name) {
            case 'id':
                $this->_id = $value;
                return;
        }
        
        if (isset($this->_data->$name)) {
            $this->_data->$name = $value;
            return;
        }
    }

    public function __get($name) {
        if (isset($this->_data->$name)) {
            return $this->_data->$name;
        }
        
        switch ($name) {
            case 'data' :
                $data = $this->_data;
                $data->id = $this->_id;
                return $data;
            default :
                return '';
        }
    }
    /**
     * get employee by company
     * @param integer $id Company id
     * @param string $type Manager's type
     * @return stdClass
     */
    public static function getByCompanyId($id, $type = self::MANAGER)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        return $dbr->getRow("select * from employee where id in (
                                select emp_id from op_company_emp where company_id=$id AND type='$type')");
    }

}

