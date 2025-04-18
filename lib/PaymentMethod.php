<?php
require_once 'PEAR.php';
require_once 'util.php';

class PaymentMethod
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    function PaymentMethod($db, $dbr, $id = 0, $lang)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('PaymentMethod::PaymentMethod expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN payment_method");
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
            $r = $this->_db->query("SELECT * FROM payment_method WHERE id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("PaymentMethod::PaymentMethod : record $id does not exist");
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

    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('PaymentMethod::update : no data');
        }
        foreach ($this->data as $field => $value) {
			if ($field == 'country_name') continue;
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
            $where = "WHERE id='" . mysql_escape_string($this->data->shipping_method_id) . "'";
        }
        $r = $this->_db->query("$command payment_method SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r; //print_r($r);
        }
        if ($this->_isNew) {
            $this->data->offer_id = mysql_insert_id();
        }
        return $r;
    }

    static function listAll($db, $dbr, $lang)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("SELECT * FROM payment_method where 1");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            print_r($r);
            return;
        }
        return $r;
    }

    static function listArray($db, $dbr, $lang)
    {
        $ret = array();
        $list = PaymentMethod::listAll($db, $dbr, $lang);
        foreach ((array)$list as $method) {
            $ret[$method->id] = $method->name;
        }
        return $ret;
    }

    function validate(&$errors)
    {
        $errors = array();
        if (empty($this->data->company_name)) {
            $errors[] = 'Company name is required';
        }
        if (empty($this->data->phone)) {
            $errors[] = 'Phone is required';
        }
        return !count($errors);
    }
}
?>