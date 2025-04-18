<?php
/**
 * @author ALEXJJ, alex@lingvo.biz
 */

class Horde {
    public $_db;
	public $_dbr;
    public $_error;

    public function __construct($db, $dbr){
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Horde __construct expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;
		$this->_dbr = $dbr;
    }
    
	public function get($field_id=false, $sql_table, $column_id, $column_value){
		$data = $this->_dbr->getOne("SELECT $field_id FROM $sql_table WHERE $column_id='$column_value';");
		
		if (PEAR::isError($data) === false) return $data;
		else {
			$this->_error = $data;
			return false;
		}

	}
	
	public function set($sql_table, $field_id, $field_value, $column_id, $column_value){
		$data = $this->_db->query("UPDATE $sql_table SET $field_id='$field_value' WHERE $column_id='$column_value';");
		
		if (PEAR::isError($data) === false) return $data;
		else {
			$this->_error = $data;
			return false;
		}
	}
	
	public function listAll($field_id='*', $sql_table, $column_id, $column_value){
		$data = $this->_dbr->getAll("SELECT $field_id FROM $sql_table WHERE $column_id='$column_value';");
		
		if (PEAR::isError($data) === false) return $data;
		else {
			$this->_error = $data;
			return false;
		}
	}
	public function getArray($fields_pair, $sql_table, $column_id, $column_value){
		$data = $this->_dbr->getAssoc("SELECT $fields_pair FROM $sql_table WHERE $column_id='$column_value';");
		
		if (PEAR::isError($data) === false) return $data;
		else {
			$this->_error = $data;
			return false;
		}
	}
}
?>
