<?php
require_once 'PEAR.php';

if (!defined('ACCESS_DENY')) {
    define ('ACCESS_DENY',      0);
    define ('ACCESS_READONLY',  1);
    define ('ACCESS_FULL',      2);
}

class Acl
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;

    function Acl($db, $dbr, $acl_page)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Acl::Acl expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        if (!$username) {
            $r = $this->_db->query("EXPLAIN acl_page");
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
            $r = $this->_db->query("SELECT * FROM acl_page WHERE acl_page='$acl_page'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("User::User : record $username does not exist");
                return;
            }
            $this->_isNew = false;
            $this->acl_page = $acl_page;
            $this->php_pages = $this->getPHPPages($db, $dbr, $acl_page);
        }
    }

    function set ($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    function get ($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function update ()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('User::update : no data');
        }
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $r = $this->_db->query("INSERT INTO acl_page SET $query ");
        } else {
            $r = $this->_db->query("UPDATE acl_page SET $query WHERE acl_page = '" .mysql_escape_string($this->acl_page) . "'");
        }
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        return $r;
    }
    
    static function listAll($db, $dbr)
    {
        $r = $db->query("SELECT `a`.*, `p`.`php_page` 
                FROM `acl_page` AS `a`
                LEFT JOIN `acl_php` AS `p` ON `p`.`acl_page` = `a`.`acl_page`
                ORDER BY `acl_page`");
        
        $list = array();
        while ($acl = $r->fetchRow()) {
            $acl->acl_title = ucfirst(str_replace('_', ' ', $acl->acl_title));
            
            if ( ! isset($list[$acl->iid])) {
                $list[$acl->iid] = $acl;
            }
            $list[$acl->iid]->php_pages[] = $acl->php_page;
        }
        return $list;
    }

    static function listArray($db, $dbr)
    {
        $ret = array();
        $list = Acl::listAll($db, $dbr);
        foreach ($list as $acl) {
            $ret[$acl->acl_page] = $acl->acl_title;
        }
        return $ret;
    }
    
    static function getPHPPages($db, $dbr, $acl_page) {
        $r = $db->query("SELECT php_page
			FROM acl_php
			WHERE acl_page='".$acl_page."' order by php_page");
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
	}
}	
?>