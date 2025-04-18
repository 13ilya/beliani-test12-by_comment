<?php
/**
 * RMA case
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';

/**
 * RMA case
 * @package eBay_After_Sale
 */
class Vat_State
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
    function Vat_State($db, $dbr, $id = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('VAT::VAT expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN vat_state");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->articles = array();
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM vat_state WHERE vat_id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("VAT::VAT : record $id does not exist");
                return;
            }
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
            $this->_error = PEAR::raiseError('VAT::update : no data');
        }
        if ($this->_isNew) {
            $this->data->vat_id = '';
        }
        foreach ($this->data as $field => $value) {
			if (isset($value)) {
	            if ($query) {
	                $query .= ', ';
	            }
//	            if ($value!='') 
					$query .= "`$field`='".mysql_escape_string($value)."'";
//				else	
//					$query .= "`$field`= NULL";
			};
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE vat_id='" . mysql_escape_string($this->data->vat_id) . "'";
        }
//		echo "$command vat_state SET $query $where"; //die();
        $r = $this->_db->query("$command vat_state SET $query $where");
        if (PEAR::isError($r)) {
           aprint_r($r);
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->vat_id = mysql_insert_id();
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
		$vat_id = (int)$this->data->vat_id;
        $r = $this->_db->query("DELETE FROM vat_state WHERE vat_id=$vat_id");
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
    static function listAll($db, $dbr, $from)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT vat_state.*
	   FROM vat_state 
	   where country_code='$from'
	   order by state, date_to, vat_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($vat = $r->fetchRow()) {
            $list[] = $vat;
        }
        return $list;
    }

    /**
    * @return unknown
    * @param unknown $db
    * @param unknown $offer
    * @desc Get all groups in an offer as array suitable
    * for use with Smarty's {html_optios}
    */
    static function listArray($db, $dbr)
    {
        $ret = array();
        $list = VAT::listAll($db, $dbr);
        foreach ((array)$list as $vat) {
            $ret[$vat->vat_id] = $vat->vat_num;
        }
        return $ret;
    }

    /**
    * @return bool
    * @param array $errors
    * @desc Validate record
    */
    function validate(&$errors)
    {
        $errors = array();
        if (empty($this->data->title)) {
            $errors[] = 'Title is required';
        }
        return 1;//!count($errors);
    }
	
	static function get_vat_percent($db, $dbr, $auction, $date=0) {
		$vat = $dbr->getOne("select vat_percent 
			from auction au
			left join auction_par_varchar aupv on au.auction_number=aupv.auction_number and au.txnid=aupv.txnid and aupv.key='country_shipping'
			join seller_information si on au.username=si.username
			join invoice i on au.invoice_number=i.invoice_number
				join seller_country sc on sc.seller_id=si.id 
				join country c on sc.country_id=c.id and (aupv.value=c.name 
					or REPLACE(aupv.value,'United Kingdom','UK')=c.name
					or REPLACE(aupv.value,'United Kingdom2','UK2')=c.name)
			join vat on vat.country_code=IF(au.payment_method>2,si.defshcountry,c.code)
			where DATE(".($date?"'$date'":"i.invoice_date").") between date_from and date_to 
			and vat.country_code_from=si.defshcountry
			and au.auction_number=".$auction->get('auction_number')." and au.txnid=".$auction->get('txnid')."
			LIMIT 0,1");
		if (!$vat) $vat=0;
		return $vat;
	}

	static function get_vat_attribs($db, $dbr, $obj, $date=0) {
		if (is_a($obj,'Insurance') && $date=='AU') {
			$q = "select ac1.number as vat_account,
				ac2.number as selling_account, vat.*
				from auction au
				left join auction_par_varchar aupv on au.auction_number=aupv.auction_number and au.txnid=aupv.txnid and aupv.key='country_shipping'
				join insurance ins on ins.auction_number=au.auction_number and ins.txnid=au.txnid
				join seller_information si on au.username=si.username
				join invoice i on au.invoice_number=i.invoice_number
				join seller_country sc on sc.seller_id=si.id 
				join country c on sc.country_id=c.id and (aupv.value=c.name 
					or REPLACE(aupv.value,'United Kingdom','UK')=c.name
					or REPLACE(aupv.value,'United Kingdom2','UK2')=c.name)
				join vat on vat.country_code='INS'
				join accounts ac1 on vat.vat_account_number = ac1.number
				join accounts ac2 on vat.selling_account_number = ac2.number
				where DATE(ins.date) between vat.date_from and vat.date_to
				and vat.country_code_from=si.defshcountry
				and ins.id=".$obj->get('id');
		} elseif (is_a($obj,'Insurance') && $date!='AU') {
			$q = "select ac1.number as vat_account,
				ac2.number as selling_account, vat.*
				from auction au
				left join auction_par_varchar aupv on au.auction_number=aupv.auction_number and au.txnid=aupv.txnid and aupv.key='country_shipping'
				join insurance ins on ins.auction_number=au.auction_number and ins.txnid=au.txnid
				join seller_information si on au.username=si.username
				join invoice i on au.invoice_number=i.invoice_number
				join seller_country sc on sc.seller_id=si.id 
				join country c on sc.country_id=c.id and (aupv.value=c.name 
					or REPLACE(aupv.value,'United Kingdom','UK')=c.name
					or REPLACE(aupv.value,'United Kingdom2','UK2')=c.name)
				join vat on vat.country_code='INS'
				join accounts ac1 on vat.vat_account_number = ac1.number
				join accounts ac2 on vat.selling_account_number = ac2.number
				where DATE(ins.date) between vat.date_from and vat.date_to
				and vat.country_code='INS'
				and vat.country_code_from=si.defshcountry
				and ins.id=".$obj->get('id');
		} elseif (is_a($obj,'Rma')) {
			$q = "select ac1.number as vat_account,
					ac2.number as selling_account, vat.*
				from auction au
				left join auction_par_varchar aupv on au.auction_number=aupv.auction_number and au.txnid=aupv.txnid and aupv.key='country_shipping'
				join rma on rma.auction_number=au.auction_number and rma.txnid=au.txnid
				join seller_information si on au.username=si.username
				join invoice i on au.invoice_number=i.invoice_number
				join seller_country sc on sc.seller_id=si.id 
				join country c on sc.country_id=c.id and (aupv.value=c.name 
					or REPLACE(aupv.value,'United Kingdom','UK')=c.name
					or REPLACE(aupv.value,'United Kingdom2','UK2')=c.name)
				join vat on vat.country_code=IF(au.payment_method>2,si.defshcountry,c.code)
				join accounts ac1 on vat.vat_account_number = ac1.number
				join accounts ac2 on vat.selling_account_number = ac2.number
				where vat.country_code_from=si.defshcountry
				and rma.rma_id=".$obj->get('rma_id')." limit 1";
		} elseif (is_a($obj,'SellerInfo')) {
			$q = "select ac1.number as vat_account,
					ac2.number as selling_account, vat.*
				from seller_information si 
				join warehouse w on si.default_warehouse_id = w.warehouse_id 
				join vat on vat.country_code=w.country_code
				join accounts ac1 on vat.vat_account_number = ac1.number
				join accounts ac2 on vat.selling_account_number = ac2.number
				where vat.country_code_from=w.country_code 
				and si.username='".$obj->get('username')."'
				and NOW() between vat.date_from and vat.date_to limit 1";
		} elseif (is_a($obj,'Auction') && $date) {
			$q = "select vat.vat_percent, ac1.number as vat_account,
					ac2.number as selling_account, vat.*
				from auction au
				left join auction_par_varchar aupv on au.auction_number=aupv.auction_number and au.txnid=aupv.txnid and aupv.key='country_shipping'
				join seller_information si on au.username=si.username
				join invoice i on au.invoice_number=i.invoice_number
				join seller_country sc on sc.seller_id=si.id 
				join country c on sc.country_id=c.id and (aupv.value=c.name 
					or REPLACE(aupv.value,'United Kingdom','UK')=c.name
					or REPLACE(aupv.value,'United Kingdom2','UK2')=c.name)
				join vat on vat.country_code=IF(au.payment_method>2,si.defshcountry,c.code)
				join accounts ac1 on vat.vat_account_number = ac1.number
				join accounts ac2 on vat.selling_account_number = ac2.number
				where DATE('".$date."') between vat.date_from and vat.date_to
				and vat.country_code_from=si.defshcountry
				and au.auction_number=".$obj->get('auction_number')." and au.txnid=".$obj->get('txnid')." limit 1";
		} elseif (is_a($obj,'Auction') && !$date) {
			$q = "select vat.vat_percent, ac1.number as vat_account,
					ac2.number as selling_account, vat.*
				from auction au
				left join auction_par_varchar aupv on au.auction_number=aupv.auction_number and au.txnid=aupv.txnid and aupv.key='country_shipping'
				join seller_information si on au.username=si.username
				join invoice i on au.invoice_number=i.invoice_number
				join seller_country sc on sc.seller_id=si.id 
				join country c on sc.country_id=c.id and (aupv.value=c.name 
					or REPLACE(aupv.value,'United Kingdom','UK')=c.name
					or REPLACE(aupv.value,'United Kingdom2','UK2')=c.name)
				join vat on vat.country_code=IF(au.payment_method>2,si.defshcountry,c.code)
				join accounts ac1 on vat.vat_account_number = ac1.number
				join accounts ac2 on vat.selling_account_number = ac2.number
				where DATE(i.invoice_date) between vat.date_from and vat.date_to
				and vat.country_code_from=si.defshcountry
				and au.auction_number=".$obj->get('auction_number')." and au.txnid=".$obj->get('txnid')." limit 1";
		}
//		echo $q;
			$vat_info = $dbr->getRow($q);
		return $vat_info;
	}

}
?>