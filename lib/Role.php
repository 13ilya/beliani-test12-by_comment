<?php
require_once 'PEAR.php';
    require_once 'config.php';

class Role
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;

    function Role($db, $dbr, $role_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Role::Role expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        if (!$role_id) {
            $r = $this->_db->query("EXPLAIN role");
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
            $r = $this->_db->query("SELECT * FROM role WHERE id=$role_id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Role::Role : record $role_id does not exist");
                return;
			}	
            $this->_isNew = false;
            $this->sellers = $this->getSellers($db, $dbr, $this->username);
            $this->suppliers = $this->getSuppliers($db, $dbr, $this->username);
			$this->data->username = $role_id;
        }
    }

    function accessLevel($page) {
        return  (int)$this->_dbr->getOne("SELECT max( LEVEL ) FROM acl JOIN acl_php ON acl.page = acl_php.acl_page
			WHERE username = '".$this->data->username."'	AND acl_php.php_page = '$page'");
    }
    
    function accessLevelacl($page) {
        return  (int)$this->_dbr->getOne("SELECT LEVEL FROM acl WHERE username = '{$this->data->username}' AND page = '$page'");
    }
    
    function accessLevelsacl() {
        $r = $this->_dbr->getAssoc("SELECT page, LEVEL FROM acl WHERE username = '".$this->data->username."'");
        return  $r;
    }
    
    function setAccessLevel($page, $level) {
        $r = $this->_db->query("REPLACE INTO acl SET username='{$this->data->username}', page='$page', level='$level'");
            if (PEAR::isError($r)) {
                aprint_r($r);
                return;
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
		$this->username = $this->data->username;
		unset($this->data->username);
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
			$q = "INSERT INTO role SET $query ";
        } else {
			$q = "UPDATE role SET $query WHERE id = '" .mysql_escape_string($this->username) . "'";
        }
        $r = $this->_db->query($q);
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
			die();
        }
        return $r;
    }

    static function listAll($db, $dbr)
    {
        $list = $dbr->getAll("SELECT * FROM role order by name");
        return $list;
    }

    static function listArray($db, $dbr)
    {
        $ret = array();
        $list = Role::listAll($db, $dbr);
        foreach ($list as $user) {
            $ret[$user->id] = $user->name;
        }
        return $ret;
    }
    
    static function getSellers($db, $dbr, $username='') {
        $r = $db->query("SELECT si.username AS seller_name, acls.username
			FROM (select username from seller_information si
				UNION ALL select 'All_sellers'
				) si
			LEFT JOIN acl_sellers acls ON si.username = acls.seller_name
			AND (acls.username='".$username."' OR '".$username."'='') order by si.username");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
	}

    static function getMethods($db, $dbr, $username='') {
        $r = $db->query("SELECT sm.company_name, acl.username, sm.shipping_method_id
			FROM (select company_name, shipping_method_id from shipping_method
				UNION ALL select ' All methods', 0
				) sm
			LEFT JOIN acl_method acl ON sm.shipping_method_id = acl.shipping_method_id
			AND (acl.username='".$username."' OR '".$username."'='') order by sm.company_name");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
	}

    static function getWarehouses($db, $dbr, $username='') {
        $r = $dbr->getAll("SELECT distinct sm.name, acl.username, sm.warehouse_id
			FROM (select name, warehouse_id from warehouse
				UNION ALL select ' All warehouses', 0
				) sm
			LEFT JOIN acl_warehouse acl ON sm.warehouse_id = acl.warehouse_id
			AND (acl.username='".$username."' OR '".$username."'='') order by sm.name");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
	}

    static function getWarehouses2ship($db, $dbr, $username='') {
        $r = $dbr->getAll("SELECT distinct sm.name, acl.username, sm.warehouse_id
			FROM (select name, warehouse_id from warehouse
				UNION ALL select ' All warehouses', 0
				) sm
			LEFT JOIN acl_warehouse2ship acl ON sm.warehouse_id = acl.warehouse_id
			AND (acl.username='".$username."' OR '".$username."'='') order by sm.name");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
	}

    static function getSuppliers($db, $dbr, $username='') {
        $r = $db->query("SELECT opc.name, opc.id, opc.type, acls.company_id
			FROM (select name, id, type from op_company 
				UNION ALL select ' All suppliers', -1, NULL
				UNION ALL select ' All shipping companies', -2, 'shipping'
				) opc
			LEFT JOIN acl_suppliers acls ON opc.id = acls.company_id
			AND (acls.username='".$username."' OR '".$username."'='') order by opc.name");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
	}

    function setMethod($shipping_method_id) {
        $this->_db->query("REPLACE INTO acl_method SET username='{$this->data->username}', shipping_method_id=$shipping_method_id");
    }
    function setWarehouse($warehouse_id) {
        $this->_db->query("REPLACE INTO acl_warehouse SET username='{$this->data->username}', warehouse_id=$warehouse_id");
    }
    function setWarehouse2ship($warehouse_id) {
        $this->_db->query("REPLACE INTO acl_warehouse2ship SET username='{$this->data->username}', warehouse_id=$warehouse_id");
    }
    function setWarehouse4article($warehouse_id) {
        $this->_db->query("REPLACE INTO acl_warehouse4article SET username='{$this->data->username}', warehouse_id=$warehouse_id");
    }
    function setSupplier($company_id) {
        $this->_db->query("REPLACE INTO acl_suppliers SET username='{$this->data->username}', company_id=$company_id");
    }
    function setSellerAccount($seller) {
        $this->_db->query("REPLACE INTO acl_sellers SET username='{$this->data->username}', seller_name='$seller'");
    }
    

    function clearSellerAccount() {
        $this->_db->query("DELETE FROM acl_sellers WHERE username='{$this->data->username}'");
    }
    function clearMethods() {
        $this->_db->query("DELETE FROM acl_method WHERE username='{$this->data->username}'");
    }
    function clearWarehouses() {
        $this->_db->query("DELETE FROM acl_warehouse WHERE (acl_warehouse.warehouse_id=0 or not (select inactive from warehouse where warehouse_id=acl_warehouse.warehouse_id))
			and username='{$this->data->username}'");
    }
    function clearWarehouses2ship() {
        $this->_db->query("DELETE FROM acl_warehouse2ship WHERE (acl_warehouse2ship.warehouse_id=0 or not (select inactive from warehouse where warehouse_id=acl_warehouse2ship.warehouse_id))
             and username='{$this->data->username}'");
    }
    function clearWarehouses4article() {
        $this->_db->query("DELETE FROM acl_warehouse4article WHERE (acl_warehouse4article.warehouse_id=0 or not (select inactive from warehouse where warehouse_id=acl_warehouse4article.warehouse_id))
             and username='{$this->data->username}'");
    }
    function clearSupplier() {
        $this->_db->query("DELETE FROM acl_suppliers WHERE username='{$this->data->username}'");
    }
    
}
?>