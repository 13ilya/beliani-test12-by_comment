<?php
require_once 'PEAR.php';

class Account
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    function Account($db, $dbr, $number = '')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Account::Account expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $number = mysql_escape_string($number);
        if (!strlen($number)) {
            $r = $this->_db->query("EXPLAIN accounts");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM accounts WHERE number='$number'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Account::Account : record $number does not exist");
                return;
            }
            $this->_isNew = false;
        }
    }

    function set($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function delete()
    {
        $this->_db->query("DELETE FROM accounts WHERE number='" . mysql_escape_string($this->data->number) . "'");
    }

    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Offer::update : no data');
        }
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE number='" . mysql_escape_string($this->data->number) . "'";
        }
        $r = $this->_db->query("$command accounts SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        return $r;
    }

    static function listAll($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Account::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $db->query("SELECT * FROM accounts order by number");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($account = $r->fetchRow()) {
            $list[] = $account;
        }
        return $list;
    }

    static function listArray($db, $dbr, $onlyActive = false)
    {
        $list = array();
        $all = Account::listAll($db, $dbr);
        foreach ($all as $account) {
            if (!$onlyActive || $account->active_or_passive == 'A') {
                $list[$account->number] = $account->number.', '.$account->name;
            }
        }
        return $list;
    }

    static function setDefault($db, $dbr, $number)
    {
        $db->query("UPDATE accounts SET is_default=0");
        $db->query("UPDATE accounts SET is_default=1 WHERE number='$number'");
    }

    static function getDefault($db, $dbr)
    {
        return $dbr->getOne('select min(number) from accounts where is_default');
    }

    function validate(&$errors)
    {
        $errors = array();
        if (!strlen($this->data->number)) {
            $errors[] = 'Account number is required';
        }
        if (!strlen($this->data->name)) {
            $errors[] = 'Name is required';
        }
        if ($this->_isNew) {
            $r = $this->_db->query("SELECT * FROM accounts WHERE number='" . mysql_escape_string($this->data->number) . "'");
            if ($r->numRows()) {
                $errors[] = 'Duplicate account number';
            }
        }
        return !count($errors);
    }
}
?>