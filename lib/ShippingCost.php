<?php
require_once 'PEAR.php';

class ShippingCost
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    function ShippingCost($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('ShippingCost::ShippingCost expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = $this->id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN shipping_cost");
            if (PEAR::isError($r)) {
                $this->_error = $r;
				print_r($r);
                return;
            }
            $this->countries = array();
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM shipping_cost WHERE id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
				print_r($r);
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("ShippingPlan::ShippingPlan : record $id does not exist");
				print_r($r);
                return;
            }
			$this->countries = $this->getCountries();
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
            $this->_error = PEAR::raiseError('ShippingCost::update : no data');
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
            $where = "WHERE id=$this->id";
        }
        $r = $this->_db->query("$command shipping_cost SET $query $where");
        if (PEAR::isError($r)) {
           print_r($r);
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->id = $this->id = mysql_insert_id();
        }
        return $r;
    }

    static function listAll($db, $dbr, $inactive=0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("select shipping_cost.* 
			, (select count(*) from shipping_plan where shipping_cost_id=shipping_cost.id)
				+
			(select count(*) from shipping_plan_country join shipping_plan on 
				shipping_plan.shipping_plan_id=shipping_plan_country.shipping_plan_id
				where diff_shipping_plan_id=shipping_cost.id) used
			from shipping_cost where inactive=$inactive order by name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function listArray($db, $dbr, $inactive=0)
    {
        $ret = array();
        $list = ShippingCost::listAll($db, $dbr, $inactive);
        foreach ((array)$list as $plan) {
            $ret[$plan->id] = $plan->name;
        }
        return $ret;
    }

	function delete()
    {
		$q = "update shipping_cost set inactive=1 WHERE id={$this->id}";
		echo $q;
        $r = $this->_db->query($q);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }

	function restore()
    {
        $r = $this->_db->query("update shipping_cost set inactive=0 WHERE id={$this->id}");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }

    function getCountries()
    {
		$db = $this->_db;
		$dbr = $this->_dbr;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("select sc.*, c.name as country ,
		(
			select CONCAT('By ', IFNULL(u.name, total_log.username), ' on ', date(total_log.updated))
			from total_log 
			left join users u on u.system_username=total_log.username
			where total_log.table_name ='shipping_cost_country' and total_log.tableid = sc.id
			order by total_log.updated desc limit 1
		) last_update,
		(
			select total_log.updated
			from total_log 
			where total_log.table_name ='shipping_cost_country' and total_log.tableid = sc.id
			order by total_log.updated desc limit 1
		) last_update_date
		from country c 
	   JOIN shipping_cost_country sc on sc.country_code=c.code 
	   WHERE sc.shipping_cost_id=".$this->id);
        if (PEAR::isError($r)) {
            $this->_error = $r;
				print_r($r);
            return;
        }
        return $r;
    }
	
	static function addCountry($db, $dbr, $id, $country_code,
		$real_shipping_cost, 
		$real_COD_cost,
		$real_island_cost,
		$real_additional_cost
		) {
        $shipping_cost_id = (int)$id;
        $country_code = mysql_escape_string($country_code);
        $real_shipping_cost = mysql_escape_string($real_shipping_cost);
        $real_COD_cost = mysql_escape_string($real_COD_cost);
        $real_island_cost = mysql_escape_string($real_island_cost);
        $real_additional_cost = mysql_escape_string($real_additional_cost);
		$r = $db->query("INSERT INTO shipping_cost_country SET shipping_cost_id=$shipping_cost_id, country_code='$country_code', 
		real_shipping_cost='$real_shipping_cost', 
		real_COD_cost='$real_COD_cost',
		real_island_cost='$real_island_cost',
		real_additional_cost='$real_additional_cost'
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            print_r($r);
            return;
        }
    }
    static function updateCountry($db, $dbr, $id, $country_code,
		$real_shipping_cost, 
		$real_COD_cost,
		$real_island_cost,
		$real_additional_cost
		) {
        $id = (int)$id;
        $country_code = mysql_escape_string($country_code);
        $real_shipping_cost = mysql_escape_string($real_shipping_cost);
        $real_COD_cost = mysql_escape_string($real_COD_cost);
        $real_island_cost = mysql_escape_string($real_island_cost);
        $real_additional_cost = mysql_escape_string($real_additional_cost);
        $r = $db->query("UPDATE shipping_cost_country SET 
		real_shipping_cost='$real_shipping_cost', 
		real_COD_cost='$real_COD_cost',
		real_island_cost='$real_island_cost',
		real_additional_cost='$real_additional_cost'
		WHERE shipping_cost_id=$id
		and country_code='$country_code'
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }
	static function deleteCountry($db, $dbr, $id, $country_code) {
        $id = (int)$id;
        $r = $db->query("DELETE FROM shipping_cost_country WHERE shipping_cost_id=$id and country_code='$country_code'");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }
}
?>