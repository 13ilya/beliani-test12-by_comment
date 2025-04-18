<?php

/**
 * RMA case
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'lib/Insurance.php';
require_once 'util.php';

require_once 'PEAR.php';

/**
 * RMA case
 * @package eBay_After_Sale
 */
class Ins_Article
{
    /**
    * Holds data record
    * @var object
    */
    var $data;
    /**
    * Reference to database
    * @var object
    */
    var $_db;
var $_dbr;
    /**
    * Error, if any
    * @var object
    */
    var $_error;
    /**
    * True if object represents a new account being created
    * @var boolean
    */
    var $_isNew;

    /**
    * @return Rma
    * @param object $db
    * @param object $auction
    * @param int $id
    * @desc Constructor
    */
    function Ins_Article($db, $dbr, $id = 0, $ins_article_id = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Insurance::Insurance expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $ins_article_id = (int)$ins_article_id;
        if (!$ins_article_id) {
            $r = $this->_db->query("EXPLAIN ins_article");
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
            $this->data->id = '';
            $this->data->ins_id = $id;
        } else {
            $r = $this->_db->query("SELECT * FROM ins_article
				WHERE id=$ins_article_id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Insurance::Insurance : record $id does not exist");
                return;
            }
			$this->pics = Insurance::getArtPics($db, $dbr, $ins_article_id);
            $this->_isNew = false;
        }
    }

    /**
    * @return void
    * @param string $field
    * @param mixed $value
    * @desc Set field value
    */
    function set($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        } else $this->data->$field = $value;
    }

    /**
    * @return string
    * @param string $field
    * @desc Get field value
    */
    function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    /**
    * @return bool|object
    * @desc Update record
    */
    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Insurance::update : no data');
        }
        foreach ($this->data as $field => $value) {
			{
	            if ($query) {
	                $query .= ', ';
	            }
	            if ((($value!='' || $value=='0') && $value!=NULL) || $field=='hidden' || $field=='closed')
					$query .= "`$field`='".mysql_escape_string($value)."'";
				else	
					$query .= "`$field`= NULL";
			};
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE id='" . mysql_escape_string($this->data->id) . "'";
        }
//		echo "$command ins_article SET $query $where"; die();
        $r = $this->_db->query("$command ins_article SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
        }
        if ($this->_isNew) {
            $this->data->id = mysql_insert_id();
        }
        return $r;
    }
    /**
    * @return void
    * @param object $db
    * @param object $group
    * @desc Delete group in an offer
    */
	function delete(){
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Offer::update : no data');
        }
		$id = (int)$this->data->ins_article_id;
		$this->_db->query("DELETE FROM ins_pic WHERE ins_article_id=$id");
        $r = $this->_db->query("DELETE FROM ins_article WHERE id=$id");
        if (PEAR::isError($r)) {
            $msg = $r->getMessage();
            adminEmail($msg);
            $this->_error = $r;
        }
    }



    /**
    * @return array
    * @param object $db
    * @param object $offer
    * @desc Get all groups in an offer
    */
    function listAll($db, $dbr, $ins_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT * FROM ins_article WHERE ins_id=$ins_id order by id");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($obj = $r->fetchRow()) {
			$obj->pics = Insurance::getArtPics($db, $dbr, $obj->id);
            $list[] = $obj;
        }
        return $list;
    }
}
?>