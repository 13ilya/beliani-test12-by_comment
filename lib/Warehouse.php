<?php
require_once 'PEAR.php';

class Warehouse
{
    var $data;
    var $_db;
    var $_dbr;
    var $_error;
    var $_isNew;

    function Warehouse(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id = 0)
    {
        $this->_db = $db;
        $this->_dbr = $dbr;
        
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN warehouse");
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
        } 
        else {
            $r = $this->_db->query("SELECT * FROM warehouse WHERE warehouse_id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Warehouse::Warehouse : record $id does not exist");
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
            $this->_error = PEAR::raiseError('Warehouse::update : no data');
        }
        if ($this->_isNew) {
            $this->data->warehouse_id = '';
        }
        
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            
            if ($this->_isNew && $field =='warehouse_id' ) {
                continue;
            }
            
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE warehouse_id='" . mysql_escape_string($this->data->warehouse_id) . "'";
        }
        
        $r = $this->_db->query("$command warehouse SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        
        if ($this->_isNew) {
            $this->data->warehouse_id = mysql_insert_id();
        }
        
        return $this->data->warehouse_id;
    }
    
    /**
     * @description get emails for warehouse notifications according to new logic
     * @return string 
     */
    function getEmail()
    {
        $emails = $this->_db->getOne("SELECT fget_WarehouseEmail('" . $this->data->warehouse_id . "')");
        return $emails;
    }
    
    static function listAll(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $where=' and not w.inactive')
    {
		global $warehouse_filter_str;
		$q = "SELECT w.*
		    , country.color country_color
			, CONCAT(IFNULL(w.address1,''),' ', IFNULL(w.address2,''),' ',IFNULL(w.address3,'')) address
			, CONCAT(IFNULL(w.country_code,''),': ', IFNULL(w.name,'')) country_code_name
			, t.value country
			FROM warehouse w
			left join users on w.driver_username=users.username
			left join country on w.country_code=country.code
			left join translation t on t.id=country.id and t.table_name='country' and t.field_name='name' and t.language='english'
			where 1 
			$where $warehouse_filter_str 
			ORDER BY country_code_name";

        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            print_r($r);
            return;
        }
        return $r;
    }

    static function list4article(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $where=' and not inactive')
    {
		global $loggedUser, $warehouse_filter_str;
		$q="SELECT IFNULL(group_concat(warehouse_id), 0) FROM acl_warehouse4article WHERE username='".$loggedUser->get('role_id')."'";
		$warehouse4article=$dbr->getOne($q);
		if($warehouse4article){
			$warehouse_filter_str=' and warehouse_id in ('.$warehouse4article.')';
		}

		$q = "SELECT w.*
			, CONCAT(IFNULL(w.address1,''),' ', IFNULL(w.address2,''),' ',IFNULL(w.address3,'')) address
			, CONCAT(IFNULL(w.country_code,''),': ', IFNULL(w.name,'')) country_code_name
			, t.value country
			FROM warehouse w
			left join users on w.driver_username=users.username
			left join country on w.country_code=country.code
			left join translation t on t.id=country.id and t.table_name='country' and t.field_name='name' and t.language='english'
			where 1 
			and (users.username is null or
				(
					users.username is not null 
					and IFNULL((
						select sum(wwo_article.qnt)
							from wwo_article
							join ww_order on ww_order.id=wwo_article.wwo_id
							join warehouse on warehouse.driver_username=ww_order.driver_username
							where wwo_article.taken
							and warehouse.warehouse_id=w.warehouse_id
					),0) - IFNULL((
						select sum(wwo_article.qnt)
							from wwo_article
							join ww_order on ww_order.id=wwo_article.wwo_id
							join warehouse on warehouse.driver_username=ww_order.driver_username
							where wwo_article.taken
							and ww_order.from_warehouse=w.warehouse_id
					),0) <> 0
				)
			)
			$where $warehouse_filter_str 
			ORDER BY country_code_name";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            print_r($r);
            return;
        }
        return $r;
    }

    static function listArray(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $where=' and not w.inactive')
    {
        $ret = array();
        $list = Warehouse::listAll($db, $dbr, $where);
        foreach ((array)$list as $warehouse) {
            $ret[$warehouse->warehouse_id] = $warehouse->country_code.': '.$warehouse->name;
        }
        return $ret;
    }

    static function listArrayAll(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $where=' and not inactive')
    {
        $ret = $dbr->getAssoc("select w.warehouse_id, w.* from warehouse w where 1 $where");
        return $ret;
    }

    function validate(&$errors)
    {
        $errors = array();
		$ware_char = substr(trim($this->data->ware_char),0,1);
		if (strlen($ware_char)) {
			$exists = $this->_dbr->getOne("select count(*) from warehouse where inactive=0 and UPPER(ware_char)=UPPER('$ware_char')"
				.($this->data->warehouse_id?" and warehouse_id<>{$this->data->warehouse_id}":''));
			if ($exists) 
	            $errors[] = 'Char must be unique';
		}
        if (empty($this->data->name)) {
            $errors[] = 'Name is required';
        }
        if (empty($this->data->address1)) {
            $errors[] = 'Address is required';
        }
        return !count($errors);
    }

    function setDefault()
    {
        $this->_db->query("update warehouse set `default`=0");
        $this->set('default',1);
		$this->update();
    }

    function toggleInactive()
    {
        $this->set('inactive',!(int)$this->get('inactive'));
		$this->update();
    }

    static function getDefault($db, $dbr)
    {
        return $dbr->getOne("select warehouse_id from warehouse where `default`");
    }

    function delete()
    {
        $this->_db->query("delete from warehouse where warehouse_id=".$this->data->warehouse_id);
    }

	function getSold($article_id)
	{
		$q = "SELECT fget_Article_sold({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getNewArticle($article_id)
	{
		$q = "SELECT fget_Article_new_article({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getReserved($article_id)
	{
		$q = "SELECT fget_Article_reserved({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

	function getInventar($article_id)
	{
		$q = "SELECT fget_Article_reserved({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

	function getOrder($article_id)
	{
		$q = "SELECT fget_Article_Order({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

	function getRMA($article_id)
	{
		$q = "SELECT fget_Article_RMA({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

	function getDriver($article_id='')
	{
		
		$q = "SELECT fget_Article_driver({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

	function getAts($article_id='')
	{
		
		$q = "SELECT fget_Article_ats({$article_id}, {$this->data->warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

	function getPieces($article_id, $cached = 24)
	{
		$q = "SELECT fget_Article_stock_cache({$article_id}, {$this->data->warehouse_id}, {$cached})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

    static function getMobileMethods($db, $dbr, $warehouse_id) {
        $q = "select distinct sm.shipping_method_id, CONCAT(sm.country,': ',sm.company_name) company_name, mwm.def, mwm.dont_use
			from shipping_method sm
			LEFT JOIN mobile_ware_method mwm ON mwm.method_id = sm.shipping_method_id
				and mwm.warehouse_id=$warehouse_id
				where sm.deleted=0
			order by sm.country, sm.company_name
		";
        $list = $dbr->getAll($q);
        if (PEAR::isError($list)) {
			aprint_r($list);
            return;
        }
        return $list;
	}

    function setMobileMethod($method_id, $def, $dont_use) {
        $this->_db->query("REPLACE INTO mobile_ware_method SET warehouse_id={$this->data->warehouse_id}, method_id=$method_id, def='$def', dont_use='$dont_use'");
    }
    
    function clearMobileMethods() {
        $this->_db->query("DELETE FROM mobile_ware_method WHERE warehouse_id={$this->data->warehouse_id}");
    }
    
    /**
     * @description user's list for warehouse notifications
     * @param DB $db
     * @param DB $dbr
     * @param $warehouse_id
     * @return array of objects
     *
     */
    static function getListNotification($db, $dbr, $warehouse_id)
    {
        $users = $dbr->getAssoc("
            SELECT wn.user_id, users.name
            FROM warehouse_notif wn
            JOIN users ON wn.user_id = users.id
            WHERE wn.warehouse_id = " . $warehouse_id);
        return $users;
    }
    
    /**
     * @description add new users to warehouse notifications
     * @param DB $db
     * @param DB $dbr
     * @param $warehouse_id
     * @param $user_id
     * @return void
     */
    static function activeUserNotif($db, $dbr, $warehouse_id, $user_id)
    {
        $db->query("INSERT INTO warehouse_notif SET warehouse_id = " . $warehouse_id . ", user_id = " . $user_id);
    }
    
    /**
     * @description delete users from warehouse notifications
     * @param DB $db
     * @param DB $dbr
     * @param $warehouse_id
     * @param $user_id
     * @return void
     */
    static function disableUserNotif($db, $dbr, $warehouse_id, $user_id)
    {
        $db->query("DELETE FROM warehouse_notif WHERE warehouse_id = " . $warehouse_id . " AND user_id = " . $user_id);
    }

    /**
     * @description user's list for warehouse volume report
     * @param DB $db
     * @param DB $dbr
     * @param $warehouse_id
     * @return array of objects
     *
     */
    static function getListVolumeReport($db, $dbr, $warehouse_id)
    {
        $users = $dbr->getAssoc("
            SELECT users.id, wvru.username
            FROM warehouse_volume_report_user wvru
            JOIN users ON wvru.username = users.username
            WHERE wvru.warehouse_id = " . $warehouse_id);
        return $users;
    }
    
    /**
     * @description add new users to warehouse volume report
     * @param DB $db
     * @param DB $dbr
     * @param $warehouse_id
     * @param $user_id
     * @return void
     */
    static function activeUserVolumeReport($db, $dbr, $warehouse_id, $username)
    {
        $db->query("INSERT INTO warehouse_volume_report_user SET warehouse_id = " . $warehouse_id . ", username = '" . $username."'");
    }
    
    /**
     * @description delete users from warehouse volume report
     * @param DB $db
     * @param DB $dbr
     * @param $warehouse_id
     * @param $user_id
     * @return void
     */
    static function disableUserVolumeReport($db, $dbr, $warehouse_id, $username)
    {
        $db->query("DELETE FROM warehouse_volume_report_user WHERE warehouse_id = " . $warehouse_id . " AND username = '" . $username."'");
    }
}

/*
ALEXJJ, alex@lingvo.biz 
WAREHOUSE HALLS PLANS RELATED FUNCTIONS
*/

function warehouse_hall_exists($hall_id){
	global $dbr;
	
	$data_hall = $dbr->getOne("SELECT id FROM warehouse_halls WHERE id=$hall_id;");
	if (PEAR::isError($data_hall)) {
		aprint_r($data_hall);
	}
	elseif ($data_hall) return true;
	else return false;
}

function warehouse_hall_matrix($hall_id){
	global $dbr;
	
	$data_hall = $dbr->getRow("SELECT * FROM warehouse_halls WHERE id=$hall_id;");
    
	if (PEAR::isError($data_hall)) {
		aprint_r($data_hall);
	}
	elseif ($data_hall) return $data_hall;
	else return false;
}

function warehouse_get_real_hall_id($hall_title_id, $warehouse_id){
	global $dbr;
	
	$data_hall = $dbr->getOne("SELECT id FROM warehouse_halls WHERE title_id=".$hall_title_id." AND warehouse_id=".$warehouse_id.";");
	
	if (PEAR::isError($data_hall)) {
		aprint_r($data_hall);
	}
	elseif ($data_hall) return $data_hall;
	else return false;
}

function warehouse_get_hall_data($hall_id, $type='title'){
	global $dbr;
	
	$data_hall = $dbr->getOne("SELECT $type FROM warehouse_halls WHERE id=$hall_id;");
	
	if (PEAR::isError($data_hall)) {
		aprint_r($data_hall);
	}
	elseif ($data_hall) return $data_hall;
	else return false;
}

function warehouse_get_warehouse_id_by_code($warehouse_code){
	global $dbr;
	
	$data_warehouse = $dbr->getOne("SELECT warehouse_id FROM warehouse WHERE ware_char='$warehouse_code';");
	
	if (PEAR::isError($data_warehouse)) {
		aprint_r($data_warehouse);
	}
	elseif ($data_warehouse) return $data_warehouse;
	else return false;
}

function warehouse_get_warehouse_data($warehouse_id, $type='name'){
	global $dbr;
	
	$data_warehouse = $dbr->getOne("SELECT $type FROM warehouse WHERE warehouse_id=$warehouse_id;");
	
	if (PEAR::isError($data_warehouse)) {
		aprint_r($data_warehouse);
	}
	elseif ($data_warehouse) return $data_warehouse;
	else return false;
}

function warehouse_get_packet_codes(){
	global $dbr;
	$output = $dbr->getAssoc("SELECT id, code FROM tn_packets;");
	if (is_array($output)) return $output;
	else return false;
}

function warehouse_get_barcodes_colors(){
	global $dbr;
	$output = $dbr->getAssoc("SELECT digs, color FROM barcode_color;");
	if (is_array($output)) return $output;
	else return false;
}

function warehouse_cell_ware_list($hall_parameters, $form_data, $x=false, $y=false, $v=false, $compatible=false, $output_type='list'){
	$dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
	global $tn_packet_codes;
	
	if ($compatible) {
		$ware_loc_id_new = $dbr->getOne("SELECT id FROM warehouse_cells WHERE hall_id=".$form_data['hall_id']." AND row='".$form_data['row']."' AND bay='".$form_data['bay']."' AND level='".$form_data['level']."';");
		$ware_loc_id = $dbr->getOne("SELECT id FROM ware_loc WHERE warehouse_id=".$hall_parameters['warehouse_id']." AND hall=".$hall_parameters['title_id']." AND row='".$form_data['row']."' AND bay='".$form_data['bay']."' AND level='".$form_data['level']."';");
	} else {
		$ware_loc_id_new = $dbr->getOne("SELECT id FROM warehouse_cells WHERE hall_id=".$form_data['hall_id']." AND row='".$form_data['y'][$y][$x]."' AND bay='".$form_data['x'][$y][$x]."' AND level='".$form_data['v'][$y][$x][$v]."';");
		$ware_loc_id = $dbr->getOne("SELECT id FROM ware_loc WHERE warehouse_id=".$form_data['warehouse_id']." AND hall=".$form_data['title_id']." AND row='".$form_data['y'][$y][$x]."' AND bay='".$form_data['x'][$y][$x]."' AND level='".$form_data['v'][$y][$x][$v]."';");
	}

	$output = '';
	if (
		(PEAR::isError($ware_loc_id) == false)
		&& (PEAR::isError($ware_loc_id_new) == false)
		&& (
			($ware_loc_id>0)
			|| ($ware_loc_id_new>0))
	){
		$sql_restr = "pb.warehouse_cell_id='$ware_loc_id_new' OR pb.ware_loc_id=$ware_loc_id";
		
		$tn_packets = $dbr->getAssoc("SELECT pb.id, pb.tn_packet_id FROM parcel_barcode pb JOIN tn_packets tp ON tp.id=pb.tn_packet_id WHERE $sql_restr;");
		if (
			(PEAR::isError($tn_packets) == false)
			&& is_array($tn_packets)
		){
			if ($output_type == 'list') {
				foreach ($tn_packets as $tn_packet_id => $tn_packet_code){
					$tn_packet_title = $tn_packet_codes[$tn_packet_code].'/'.str_pad($tn_packet_id, 10, '0', STR_PAD_LEFT);
					$output .= '<a href="parcel_barcodes.php?filter[code]='.urlencode($tn_packet_title).'&dofilter=Filter" target="_tab">'.$tn_packet_title."</a><br />\n";
				}
				$output .= '<a href="/warehouse_location.php?cell_history=1&idNew='.$ware_loc_id_new.((!empty($ware_loc_id)) ? '&idOld='.$ware_loc_id : '').'">Log</a>';
				$output = '<p>'.$output."</p>\n";
			} else {
				$output = array();
				foreach ($tn_packets as $tn_packet_id => $tn_packet_code){
					$output[] = $tn_packet_id;
				}
			}
		}
	}
	return $output;
}

/**
 * Construct history of cell
 * @param int|false $cell_id_new
 * @param int|false $cell_id_old
 * @return string
 */
function warehouse_get_cell_log($cell_id_new = false, $cell_id_old = false)
{
	$dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
	
	$sql_str = "select group_concat(log order by updated separator '<br/>') log from (";
	if ($cell_id_old) $sql_str .= "select concat(tnp.code,'/',lpad(pb.id,10,0), ' moved to LA by ', IFNULL(u.name, tl.username), ' on ', tl.updated) log
				, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				where tl.table_name = 'parcel_barcode' and tl.field_name='ware_loc_id'
				and tl.old_value='$cell_id_old'
			union
			select concat(tnp.code,'/',lpad(pb.id,10,0), ' located by ', IFNULL(u.name, tl.username), ' on ', tl.updated) log
				, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				where tl.table_name = 'parcel_barcode' and tl.field_name='ware_loc_id'
				and tl.new_value='$cell_id_old'
			union
			";
	
	if ($cell_id_new) $sql_str .= "select concat(tnp.code,'/',lpad(pb.id,10,0), ' moved to LA by ', IFNULL(u.name, tl.username), ' on ', tl.updated) log
				, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				where tl.table_name = 'parcel_barcode' and tl.field_name='warehouse_cell_id'
				and tl.old_value='$cell_id_new'
			union
			select concat(tnp.code,'/',lpad(pb.id,10,0), ' located by ', IFNULL(u.name, tl.username), ' on ', tl.updated) log
				, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				where tl.table_name = 'parcel_barcode' and tl.field_name='warehouse_cell_id'
				and tl.new_value='$cell_id_new'
			union
			";
	$sql_str = substr($sql_str, 0, strlen($sql_str)-10);
	$sql_str .= ") t";
	
	$log = $dbr->getOne($sql_str);
	if ($log) {
		return $log;
	}
	return '';
}
?>