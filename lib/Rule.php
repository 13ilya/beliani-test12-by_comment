<?php
require_once 'PEAR.php';

class Rule
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    function Rule($db, $dbr, $offer, $id = 0, $lang='german')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Rule::Rule expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN rules");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->data->offer_id = $offer->data->offer_id;
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM rules WHERE rule_id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Rule::Rule : record $id does not exist");
                return;
            }
            $this->_isNew = false;
		$this->translated_error_message = $dbr->getOne("SELECT value
				FROM translation
				WHERE table_name = 'rules'
				AND field_name = 'error_message'
				AND language = '$lang'
				AND id = $id");
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
            $this->_error = PEAR::raiseError('Rule::update : no data');
        }
        if ($this->_isNew) {
            $this->data->rule_id = '';
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
            $where = "WHERE rule_id='" . $this->data->rule_id . "'";
        }
        $r = $this->_db->query("$command rules SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->rule_id = mysql_insert_id();
        }
        return $r;
    }

    static function listAll($db, $dbr, $offer, $lang="german", $inactive=0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Rule::list expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $offer_id = (int)$offer->data->offer_id;
		if (strlen($inactive)) $inactive = " and inactive=$inactive";
        $r = $db->query("SELECT rules.*
			, (SELECT value
				FROM translation
				WHERE table_name = 'rules'
				AND field_name = 'error_message'
				AND language = '$lang'
				AND id = rules.rule_id) translated_error_message 
			FROM rules WHERE offer_id=$offer_id
			$inactive");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($rule = $r->fetchRow()) {
            $list[] = $rule;
        }
        return $list;
    }

    static function delete($db, $dbr, $id)
    {
        $id = (int)$id;
        $db->query("DELETE FROM rules WHERE rule_id = $id");
    }

    function validate(&$errors)
    {
        $errors = array();
        if ($this->data->group_id == $this->data->linked_group_id) {
            $errors[] = 'Unable to link a group to itself';
        }
        if ($this->data->multiplier <= 0) {
            $errors[] = 'Multiplier must be greater than zero';
        }
        if (!strlen($this->data->error_message)) {
            $errors[] = 'Error message is required';
        }
        return !count($errors);
    }
}
?>