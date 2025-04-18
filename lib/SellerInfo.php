<?php
require_once 'PEAR.php';
require_once 'util.php';
require_once __DIR__.'/SellerNotification.php';
require_once __DIR__.'/Saved.php';

/**
 * Class SellerInfo
 *
 * all notification type for seller
 * @property SellerNotification $notification
 */
class SellerInfo
{
    var $data;
    var $notification;
    var $_db;
    var $_dbr;
    var $_error;
	var $mulang_fields;
    var $lang;

    function SellerInfo($db, $dbr, $username, $lang='')
    {
        $this->lang = $lang;
//		echo '<br>Constructor stasted... ';
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('SellerInfo::SellerInfo expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        if (!strlen($username)) {
            $r = $this->_db->query("EXPLAIN seller_information");
            if (PEAR::isError($r)) {
                aprint_r($r);
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
//				echo $field."<br>";
            }
            $this->_isNew = true;
        } else {
            $r = $this->_dbr->getRow("SELECT * FROM seller_information WHERE username='$username'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
				echo '<br>Error '; print_r($this->_error);
                return;
            }

            $this->data = $r;
			if ($lang=='') $lang=$r->default_lang;
            //get notification data
            $this->notification = new SellerNotification($this->data->id);
            foreach ($this->notification->getListNotification() as $v){
                $this->data->$v['code'] = $this->get($v['code']);
            }
            //end get notification data
			$this->data->country_name = countryCodeToCountry($this->data->country); 
            if (!$this->data) {
                $this->_error = PEAR::raiseError("SellerInfo::SellerInfo : record $username does not exist");
				echo '<br>Error '.$this->_error;
                return;
            }
			$id = (int)$this->data->id;
			$this->mulang_fields = array('data_security_explanation'
					   ,'return_address'
					   ,'complain_text'
					   ,'call_center_days'
					   ,'servicequitting_next_steps'
		   			   ,'thanks_top_message'
					   ,'termsandconditions'
					   ,'warranty_duration'
					   ,'billpay_header_top'
					   ,'billpay_header_right'
					   ,'billpay_header_bottom'
					   );
				$q = "SELECT field_name, value
				FROM translation
				WHERE table_name = 'seller_information'
				AND field_name in ('".implode("','", $this->mulang_fields)."')
				AND language = '$lang'
				AND id = $id";
				$mulang_fields_value = $dbr->getAssoc($q);
			foreach($this->mulang_fields as $fld) {
				$this->data->$fld = $mulang_fields_value[$fld];
			}

            $this->data->klarna_allowed_countries = $dbr->getCol("SELECT country_code FROM klarna_allowed_countries WHERE seller = '$username'");
			
            $this->_isNew = false;
            $this->username = $username;
        }
    }

    function authenticate($passhash)
    {
        return isset($this->data->username) &&
               isset($this->data->seller_password) &&
               md5($this->data->username.$this->data->seller_password) == $passhash;
    }

    function set ($field, $value)
    {
        if (isset($this->data->$field)) 
		{
            $this->data->$field = $value;
        }
    }

    function setTemplate($template_name, $country_code, $value)
	{
		$q = "REPLACE INTO templates SET value='".mysql_escape_string($value)."'
		 , template_id=(select id from template_names where name='".$template_name."'), country_code='".$country_code."'
		 , username='".$this->get('username')."'";
       	$r = $this->_db->query($q);
        if (PEAR::isError($r)) {
            $this->_error = $r;
			return false;
        }
       return true;
	}

    function get ($field)
    {
        if($this->notification && array_key_exists($field, $this->notification->getListUserByNotify())){
            $emails = $this->_dbr->getOne('select GROUP_CONCAT(distinct u.email) as emails 
from seller_notif as sn 
join users as u on u.id = sn.user_id and u.deleted=0
join seller_notif_type as snt on snt.id = sn.notif_type_id and code ="'.mysql_real_escape_string($field).'" 
where sn.seller_id='.$this->data->id);
            return $emails;
        }else if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function getTemplate($template_name, $country_code)
	{
		require_once 'lib/Config.php';
		if ($this->get('original_templates')) {
			$username = $this->get('username');
		} else {
			$username = Config::get($this->_db, $this->_dbr, 'aatokenSeller');
		}
       $subject = $this->_dbr->getOne("SELECT t.value
		 FROM translation t 
		 WHERE t.table_name='templates_".$username."' AND t.language='$country_code'
		 AND id=(select id from template_names where name='$template_name')
		 and field_name='subject'");
        if (PEAR::isError($subject)) {
			aprint_r($subject);
        }
	$q = "SELECT t.value
		 FROM translation t 
		 WHERE t.table_name='templates_".$username."' AND t.language='$country_code'
		 AND id=(select id from template_names where name='$template_name')
		 and field_name='body'";
       $body = $this->_dbr->getOne($q);
//	   echo $q;
        if (PEAR::isError($body)) {
			aprint_r($body);
        }
       return $subject."\n".$body;
	}

    function getSMS($template_name, $country_code)
	{
		require_once 'lib/Config.php';
		if ($this->get('original_templates')) {
			$username = $this->get('username');
		} else {
			$username = Config::get($this->_db, $this->_dbr, 'aatokenSeller');
		}
       $sms = $this->_dbr->getOne("SELECT t.value
		 FROM translation t 
		 WHERE t.table_name='templates_".$username."' AND t.language='$country_code'
		 AND id=(select id from template_names where name='$template_name')
		 and field_name='sms'");
       return $sms;
	}

    function update ()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('SellerInfo::update : no data');
        }
        foreach ($this->data as $field => $value) {
			if (
			    $field == 'country_name' || 
                $field == 'low_margin_alert_email' || 
                $field == 'klarna_allowed_countries'
            ) continue;
		    if (in_array($field, $this->mulang_fields)) continue;
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $r = $this->_db->query("INSERT seller_information SET $query ");

        } else {
            $r = $this->_db->query("UPDATE seller_information SET $query WHERE username = '" .mysql_escape_string($this->username) . "'");
        }
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
        }
        if ($this->_isNew) {
			$this->data->id = mysql_insert_id();
            //get notification data
            $this->notification = new SellerNotification($this->data->id);
            //end get notification data
		}
		//update notification
        if($this->notification)
            $this->notification->save();
        return $r;
    }

    static function listAll($db, $dbr, $channel='')
    {
	  global $seller_filter;
	  if (strlen($channel)) $where = " and seller_information.seller_channel_id in ($channel) ";
	  if (strlen($seller_filter)) $seller_filter1 = " and seller_information.username in ($seller_filter) ";
      
      $query = "SELECT seller_information.* 
				, s1.name system1
				, s2.name system2
				, s3.name system3
				, s4.name system4
				, s5.name system5
				, s6.name system6
				, s7.name system7
				, s8.name system8
				, concat(seller_information.defshcountry, ': ',seller_information.username, ' = ', seller_information.seller_name) fullname
				, ss.name source_seller
			FROM seller_information
			left join config_api_system s1 on s1.id=seller_information.config_api_system_id
			left join config_api_system s2 on s2.id=seller_information.config_api_system_id1
			left join config_api_system s3 on s3.id=seller_information.config_api_system_id2
			left join config_api_system s4 on s4.id=seller_information.config_api_system_id3
			left join config_api_system s5 on s5.id=seller_information.config_api_system_id4
			left join config_api_system s6 on s6.id=seller_information.config_api_system_id5
			left join config_api_system s7 on s7.id=seller_information.config_api_system_id6
			left join config_api_system s8 on s8.id=seller_information.config_api_system_id7
			left join source_seller ss on ss.id=seller_information.def_source_seller_id
			 where 1
			$seller_filter1 
			$where
			order by defshcountry, seller_name";
      
        $list = $dbr->getAll($query);
        return $list;
    }

    static function listAllActive($db, $dbr, $channel='')
    {
	  global $seller_filter;
	  if (!isset($seller_filter)) $seller_filter='';
	  if ($channel) $where = " and seller_channel_id in ($channel) ";
	  $seller_filter1 = '';
	  if (strlen($seller_filter)) $seller_filter1 = " (username in ($seller_filter) or 'All_sellers' in ($seller_filter)) and ";
	  	$q = "SELECT * FROM seller_information WHERE $seller_filter1 isActive = 1 
			$where
			order by defshcountry, seller_name";
        $list = $dbr->getAll($q);
        return $list;
    }

    static function listAllTemplates($db, $dbr, $system=0, $old=0)
    {
     $cond = $system ? 'system and not old' : 'NOT system and not old';
	 if ($old) $cond = 'old';
        $list = $dbr->getAll("SELECT * FROM template_names 
			where not hidden and $cond 
			order by `desc`");
        return $list;
    }

    static function listArray($db, $dbr, $channel='')
    {
        $ret = array();
        $list = SellerInfo::listAll($db, $dbr, $channel);
        foreach ($list as $user) {
            $ret[$user->username] = $user->defshcountry.': '.$user->username.' = '.$user->seller_name;
        }
        return $ret;
    }
    
    static function listArrayActive($db, $dbr, $channel='')
    {
        $ret = array();
        $list = SellerInfo::listAllActive($db, $dbr, $channel);
        foreach ($list as $user) {
            $ret[$user->username] = $user->defshcountry.': '.$user->username.' = '.$user->seller_name;
        }
        return $ret;
    }

    static function listArrayActiveShort($db, $dbr, $channel=0)
    {
        $ret = array();
        $list = SellerInfo::listAllActive($db, $dbr, $channel);
        foreach ($list as $user) {
            $ret[$user->username] = $user->username;
        }
        return $ret;
    }

    static function listArrayTemplates($db, $dbr, $system=0, $old=0)
    {
        $ret = array();
        $list = SellerInfo::listAllTemplates($db, $dbr, $system, $old);
        foreach ($list as $tmp) {
            $ret[$tmp->name] = $tmp->desc;
        }
        return $ret;
    }

    /**
     * get langs of seller
     *
     * @param string $username - seller's username
     *
     * @return lang's list
     */
    static function listArrayLangs($username){

        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $langs = $dbr->getAssoc("select v.value, v.description
			from  config_api_values v
			join seller_lang sl on sl.lang=v.value
				and sl.username='{$username}'
			where v.par_id=6 and not v.inactive
			and sl.useit=1
			order by sl.ordering");

        return $langs;

    }

    static function &singleton($db, $dbr, $username)
    {
        if (!isset($GLOBALS['SELLER_INFO_SINGLETON'][$username])) {
            $GLOBALS['SELLER_INFO_SINGLETON'][$username] = new SellerInfo($db, $dbr, $username);
        }
        return $GLOBALS['SELLER_INFO_SINGLETON'][$username];
    }

    function geteBay(&$devId, &$appId, &$certId)
	{
		global $ebay_name;
		switch (date('H')) {
			case 0:
			case 1:
			case 2: $fld = 'config_api_system_id';
				break;
			case 3:
			case 4:
			case 5: $fld = 'config_api_system_id1';
				break;
			case 6:
			case 7:
			case 8: $fld = 'config_api_system_id2';
				break;
			case 9:
			case 10:
			case 11: $fld = 'config_api_system_id3';
				break;
			case 12:
			case 13:
			case 14: $fld = 'config_api_system_id4';
				break;
			case 15:
			case 16:
			case 17: $fld = 'config_api_system_id5';
				break;
			case 18:
			case 19:
			case 20: $fld = 'config_api_system_id6';
				break;
			case 21:
			case 22:
			case 23: $fld = 'config_api_system_id7';
				break;
		}
		$r = $this->_dbr->getRow("select * from config_api_system where id=".$this->data->$fld);
		$devId = $r->devId;
		$appId = $r->appId;
		$certId = $r->certId;
		$ebay_name = $r->name;
	}

    function getPayPal(&$API_UserName, &$API_Password, &$API_Signature)
	{
//		$r = $this->_dbr->getRow("select * from config_api_system where id=".$this->data->config_api_system_id);
		$API_UserName = $this->get('paypal_email');
		$API_Password = $this->get('paypal_password');
		$API_Signature = $this->get('paypal_signature');
	}

    function getGoogleCheckout(&$MerchantID, &$MerchantKey)
	{
//		$r = $this->_dbr->getRow("select * from config_api_system where id=".$this->data->config_api_system_id);
		$MerchantID = $this->get('gc_merchant_id');
		$MerchantKey = $this->get('gc_merchant_key');
	}

    function getServer()
	{
		switch (date('H')) {
			case 0:
			case 1:
			case 2: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id);
				break;
			case 3:
			case 4:
			case 5: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id1);
				break;
			case 6:
			case 7:
			case 8: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id2);
				break;
			case 9:
			case 10:
			case 11: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id3);
				break;
			case 12:
			case 13:
			case 14: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id4);
				break;
			case 15:
			case 16:
			case 17: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id5);
				break;
			case 18:
			case 19:
			case 20: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id6);
				break;
			case 21:
			case 22:
			case 23: $fld = $this->_dbr->getOne("select server from config_api_system where id=".$this->data->config_api_system_id7);
				break;
		}
		return $fld; 
	}

    function getDefSMTP()
	{
		switch (date('H')) {
			case 0:
			case 1:
			case 2: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id.")");
				break;
			case 3:
			case 4:
			case 5: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id1.")");
				break;
			case 6:
			case 7:
			case 8: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id2.")");
				break;
			case 9:
			case 10:
			case 11: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id3.")");
				break;
			case 12:
			case 13:
			case 14: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id4.")");
				break;
			case 15:
			case 16:
			case 17: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id5.")");
				break;
			case 18:
			case 19:
			case 20: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id6.")");
				break;
			case 21:
			case 22:
			case 23: $fld = $this->_dbr->getRow("select * from smtp where id=(
			select def_smtp_id from config_api_system where id=".$this->data->config_api_system_id7.")");
				break;
		}
		return $fld; 
	}
    function getAltSMTPs()
	{
		switch (date('H')) {
			case 0:
			case 1:
			case 2: $fld = '';
				break;
			case 3:
			case 4:
			case 5: $fld = '1';
				break;
			case 6:
			case 7:
			case 8: $fld = '2';
				break;
			case 9:
			case 10:
			case 11: $fld = '3';
				break;
			case 12:
			case 13:
			case 14: $fld = '4';
				break;
			case 15:
			case 16:
			case 17: $fld = '5';
				break;
			case 18:
			case 19:
			case 20: $fld = '6';
				break;
			case 21:
			case 22:
			case 23: $fld = '7';
				break;
		}
		$fld = 'config_api_system_id'.$fld;
		return $this->_dbr->getAll("select * from smtp where id in (
			select alt_smtp_id_1 from config_api_system where id=".$this->data->$fld."
			union
			select alt_smtp_id_2 from config_api_system where id=".$this->data->$fld."
			)");
	}
    function setDefSMTP($id)
	{
		switch (date('H')) {
			case 0:
			case 1:
			case 2: $fld = '';
				break;
			case 3:
			case 4:
			case 5: $fld = '1';
				break;
			case 6:
			case 7:
			case 8: $fld = '2';
				break;
			case 9:
			case 10:
			case 11: $fld = '3';
				break;
			case 12:
			case 13:
			case 14: $fld = '4';
				break;
			case 15:
			case 16:
			case 17: $fld = '5';
				break;
			case 18:
			case 19:
			case 20: $fld = '6';
				break;
			case 21:
			case 22:
			case 23: $fld = '7';
				break;
		}
		$fld = 'config_api_system_id'.$fld;
		return $this->_db->query("update config_api_system set def_smtp_id=$id where id=".$this->data->$fld);
	}

	function getPayments() {
		$payment_methods = $this->_dbr->getAll("select 
			pm.id, pm.code,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'name'
				AND language = '".$this->lang."'
				AND id = pm.id) as name, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'shipping_name'
				AND language = '".$this->lang."'
				AND id = pm.id) as shipping_name, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'fee_name'
				AND language = '".$this->lang."'
				AND id = pm.id) as fee_name, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'afterwards_message'
				AND language = '".$this->lang."'
				AND id = pm.id) as afterwards_message, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'additional_fields'
				AND language = '".$this->lang."'
				AND id = pm.id) as additional_fields, 
			spm.allow,
			IF(IFNULL(spm.fee,0)=0,pm.fee,spm.fee) fee,
			spm.fee_amt,
			IFNULL(spm.icon,pm.icon) icon
		from payment_method pm
		left join seller_payment_method spm on spm.payment_method_id=pm.id and spm.seller_username='".$this->data->username."'
		where spm.allow
		order by pm.id
		");
		if (PEAR::isError($payment_methods)) { aprint_r($payment_methods); die();}
		return $payment_methods;
	}

	function getPaymentByCode($code) {
		$payment_method = $this->_dbr->getRow("select 
			pm.id, pm.code,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'name'
				AND language = '".$this->lang."'
				AND id = pm.id) as name, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'shipping_name'
				AND language = '".$this->lang."'
				AND id = pm.id) as shipping_name, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'fee_name'
				AND language = '".$this->lang."'
				AND id = pm.id) as fee_name, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'afterwards_message'
				AND language = '".$this->lang."'
				AND id = pm.id) as afterwards_message, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'additional_fields'
				AND language = '".$this->lang."'
				AND id = pm.id) as additional_fields, 
			spm.allow,
			IF(IFNULL(spm.fee,0)=0,pm.fee,spm.fee) fee,
			spm.fee_amt,
			IFNULL(spm.icon,pm.icon) icon
		from payment_method pm
		left join seller_payment_method spm on spm.payment_method_id=pm.id and spm.seller_username='".$this->data->username."'
		where spm.allow and pm.code='$code'
		order by pm.id
		");
		if (PEAR::isError($payment_method)) { aprint_r($payment_method); die();}
		return $payment_method;
	}

    static function getShipWarehouses($db, $dbr, $username='') {
        $r = $db->query("SELECT sm.name, acl.username, sm.warehouse_id
			FROM (select name, warehouse_id from warehouse
				UNION ALL select ' All warehouses', 0
				) sm
			LEFT JOIN seller_ship_warehouse acl ON sm.warehouse_id = acl.warehouse_id
			AND (acl.username='".$username."' OR '".$username."'='') order by sm.name");
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
    function setShipWarehouse($warehouse_id) {
        $this->_db->query("REPLACE INTO seller_ship_warehouse SET username='{$this->data->username}'
			, warehouse_id=$warehouse_id");
    }
    function clearShipWarehousess() {
        $this->_db->query("DELETE FROM seller_ship_warehouse WHERE username='{$this->data->username}'");
    }
    /**
     * Get Seller default Warehouses. Used when creating new SA
     * @return array
     */
    public function getDefaultWarehouses() 
    {
        return $this->_dbr->getCol("SELECT warehouse_id FROM seller_stop_empty_warehouse WHERE username='{$this->data->username}'");
    }
    /**
     * Get Seller local Warehouses.
     * @return array
     */
    public function getLocalWarehouses() 
    {
        return $this->_dbr->getCol("SELECT warehouse_id FROM seller_local_warehouse WHERE seller_username='{$this->data->username}'");
    }
    /**
     * Save Seller default Warehouses. Used when creating new SA
     * @param array $warehouses
     */
    public function saveDefaultWarehouses($warehouses) 
    {
        $warehouses = array_unique($warehouses);
        $this->_db->query("DELETE FROM seller_stop_empty_warehouse WHERE username='{$this->data->username}'");
        foreach ($warehouses as $warehouse_id) {
            $r = $this->_db->query("INSERT seller_stop_empty_warehouse 
                SET username='{$this->data->username}', warehouse_id = " . (int)$warehouse_id);
        }
    }
    /**
     *
     */
    public function getInternationalShipping()
    {
        $data = [];
        $InternationalShippingContainer = $this->_dbr->getAll("select par_key, par_value from ebay_seller_saved_params
            where seller_id={$this->data->id} and par_key like 'InternationalShippingContainer%'");

        $rows = Saved::convertSavedParamsToArray($InternationalShippingContainer);
        
        foreach ($rows as $row) {
            $data[] = $row;
        }
        
        return $data;
    }
    /** 
     *
     */
    public function saveInternationalShipping($service, $countries)
    {
        if ($service && is_array($countries)) {
            $service = mysql_escape_string($service);
            $row = $this->_dbr->getRow("SELECT * FROM ebay_seller_saved_params 
                WHERE seller_id = {$this->data->id}
                AND par_value = '$service'");

            if ($row) { // update
                $row_id = $this->_extractRowNumber($row->par_key);
                $par_key_string = "InternationalShippingContainer[$row_id][InternationalShippingCountry]";
                $this->_db->query("DELETE FROM ebay_seller_saved_params 
                    WHERE seller_id={$this->data->id}
                    AND par_key = '$par_key_string'");
                    
                foreach ($countries as $country_code) {
                    $country_code = mysql_escape_string($country_code);
                    $r = $this->_db->query("INSERT ebay_seller_saved_params 
                        SET seller_id={$this->data->id}, par_key='$par_key_string', par_value = '$country_code'");
                }
            } else { // insert
                $max_row = $this->_dbr->getOne("SELECT par_key FROM ebay_seller_saved_params 
                    WHERE seller_id = {$this->data->id}
                    AND par_key LIKE '%][InternationalShippingService]'
                    GROUP BY par_key
                    ORDER BY par_key DESC");
                $row_id = $this->_extractRowNumber($max_row) + 1;
                
                $par_key_string = "InternationalShippingContainer[$row_id][InternationalShippingService]";
                $r = $this->_db->query("INSERT ebay_seller_saved_params 
                    SET seller_id={$this->data->id}, par_key='$par_key_string', par_value='$service'");

                $par_key_string = "InternationalShippingContainer[$row_id][InternationalShippingCountry]";
                foreach ($countries as $country_code) {
                    $country_code = mysql_escape_string($country_code);
                    $r = $this->_db->query("INSERT ebay_seller_saved_params 
                        SET seller_id={$this->data->id}, par_key='$par_key_string', par_value = '$country_code'");
                }
            }
        }
    }
    /** 
     *
     */
    public function deleteInternationalShipping($service)
    {
        $row = $this->_dbr->getRow("SELECT * FROM ebay_seller_saved_params 
            WHERE seller_id = {$this->data->id}
            AND par_value = '$service'");

        $row_id = $this->_extractRowNumber($row->par_key);

        if ($row_id !== false) {
            $par_key_string = "InternationalShippingContainer[$row_id][%";
            $this->_db->query("DELETE FROM ebay_seller_saved_params 
                WHERE seller_id = {$this->data->id}
                AND par_key LIKE '$par_key_string'");
        }
    }
    /**
     * From string like this -> InternationalShippingContainer[0][InternationalShippingCountry]
     * will be extracted row id -> 0
     */
     private function _extractRowNumber($par_key) 
     {
        $parts = explode('[', $par_key);

        if (isset($parts[1])) {
            $parts1 = explode(']', $parts[1]);
            if (isset($parts1[0])) {
                return $parts1[0];    
            }
        }

        return false;
     }
    /**
     * Save Seller local Warehouses.
     * @param array $warehouses
     */
    public function saveLocalWarehouses($warehouses) 
    {
        $warehouses = array_unique($warehouses);
        $this->_db->query("DELETE FROM seller_local_warehouse WHERE seller_username='{$this->data->username}'");
        foreach ($warehouses as $warehouse_id) {
            $r = $this->_db->query("INSERT seller_local_warehouse 
                SET seller_username='{$this->data->username}', warehouse_id = " . (int)$warehouse_id);
        }
    }
	/**
	 * Return list of served countries in current language
	 * @return array with values stdClass with fields:
	 * 	-country_name string
	 */
	public function getServedCountries()
	{
		$result = $this->_dbr->getAll(
			'
				SELECT countryName.value AS country_name
				FROM seller_country
					LEFT JOIN translation countryName ON
						countryName.table_name = \'country\'
						AND countryName.field_name = \'name\'
						AND countryName.language = ?
						AND countryName.id = seller_country.country_id
				WHERE seller_country.seller_id = ?
			',
			null,
			array(
				$this->lang,
				$this->data->id,
            )
		);
		return $result;
	}

	function getSourceSeller($db, $dbr, $username){
		$q = "SELECT id, name FROM source_seller WHERE username='{$username}'";
		$list = $db->getAssoc($q);

		return $list;
	}

	function getSourceSellerById($db, $dbr, $id){
        $q = "SELECT ss.id, ss.name FROM source_seller ss
              JOIN seller_information si ON ss.username = si.username
              WHERE si.id = $id";
        $list = $db->getAssoc($q);

        return $list;
    }

    function getSourceSellerByIds($db, $dbr, $ids){
        $ids_str = implode(',',$ids);
        $q = "SELECT ss.id, ss.name FROM source_seller ss
              JOIN seller_information si ON ss.username = si.username
              WHERE si.id IN ($ids_str) ORDER BY ss.name";
        $list = $db->getAssoc($q);

        return $list;
    }
    /**
     * Save in `ebay_seller_saved_params`
     */
    public function saveEbaySavedParams($params, $sa_type_id = null) {
        $pars = [];
        foreach ($params as $key => $value) {
            if ($key == 'payment') {
                $this->_db->query("DELETE FROM `ebay_seller_saved_params` 
                    WHERE seller_id = {$this->data->id}
                    AND par_key LIKE 'payment[%'
                    AND sa_type_id " . ($sa_type_id ? "= $sa_type_id" : 'IS NULL'));
            } 
            
            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    if (is_array($value2)) {
                        foreach ($value2 as $key3 => $value3)
                        {
                            if (is_array($value3)) {
                                foreach ($value3 as $key4 => $value4) {
                                    $pars["{$key}[{$key2}][{$key3}][{$key4}]"] = $value4;
                                }
                            } elseif ($value3 != '') {
                                $pars["{$key}[{$key2}][{$key3}]"] = $value3;
                            }
                        }
                    } elseif ($value2 != '') {
                        $pars["{$key}[{$key2}]"] = $value2;
                    }
                }
            } elseif ($value != '') {
                $pars[$key] = $value;
            }
        }

        foreach ($pars as $par_key => $par_value) {
            $par_key = mysql_escape_string($par_key);
            $par_value = mysql_escape_string($par_value);
            
            $id = $this->_db->getOne("SELECT id FROM `ebay_seller_saved_params`
                WHERE seller_id = {$this->data->id}
                AND par_key = '$par_key'
                AND sa_type_id " . ($sa_type_id ? "= $sa_type_id" : 'IS NULL'));
                
            if ($id) {
                $this->_db->query("UPDATE `ebay_seller_saved_params` SET 
                    par_value = '$par_value'
                    WHERE id = $id");
            } else {
                $this->_db->query("INSERT INTO `ebay_seller_saved_params` SET 
                    seller_id = {$this->data->id},
                    par_key = '$par_key',
                    par_value = '$par_value',
                    sa_type_id " . ($sa_type_id ? "= $sa_type_id" : '= NULL'));
            }
        }
    }
    /**
     *
     */
     public function getEbaySavedParams($sa_type_id = null) {
        $q = "SELECT par_key, par_value FROM `ebay_seller_saved_params` 
            WHERE seller_id = {$this->data->id}";
            
        if ($sa_type_id) {
            $sa_type_id = (int)$sa_type_id;
            $q .= " AND sa_type_id = $sa_type_id";
        }
        
        $pars = $this->_dbr->getAssoc($q);

        $data = [];
        foreach ($pars as $par_key => $par_value) {
            $parts = explode('[', $par_key);
            $key1 = $parts[0];
            
            if (count($parts) == 1) {
                $data[$key1] = $par_value; 
            } elseif (count($parts) == 2) {
                $parts1 = explode(']', $parts[1]);
                $key2 = $parts1[0];
                
                $data[$key1][$key2] = $par_value;
             } elseif (count($parts) == 3) {
                $parts1 = explode(']', $parts[1]);
                $key2 = $parts1[0];
                
                $parts2 = explode(']', $parts[2]);
                $key3 = $parts2[0];
                
                $data[$key1][$key2][$key3] = $par_value;
             }
        }

        return $data;
     }
}
?>