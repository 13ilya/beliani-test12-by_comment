<?php
error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);
ini_set('display_errors',1);
/**
 * Auction
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';
require_once 'lib/op_Order.php';
require_once 'lib/Config.php';
require_once 'lib/SellerInfo.php';
require_once 'lib/Order.php';
require_once 'File/PDF.php';

/**
 * Stages ocnstansts
 */
 
define('STAGE_LISTED',                          1);
define('STAGE_NO_WINNER',                       2);
define('STAGE_WON',                             3);
define('STAGE_WINNER_MAIL_RESENT_1',            4);
define('STAGE_WINNER_MAIL_RESENT_2',            5);
define('STAGE_NO REPLY',                        6);
define('STAGE_ORDERED',                         7);
define('STAGE_PAYMENT_INSTRUCTION_RESENT_1',    8);
define('STAGE_PAYMENT_INSTRUCTION_RESENT_2',    9);
define('STAGE_PAID',                           10);
define('STAGE_READY_TO_PICKUP',                11);
define('STAGE_READY_TO_PICKUP_RESENT_1',       12);
define('STAGE_READY_TO_PICKUP_RESENT_2',       13);
define('STAGE_WAIT_FOR_RATING',                14);
define('STAGE_RELISTED',                       15);


/**
 * Auction
 * @package eBay_After_Sale
 */
class Auction
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

    var $_allpars_varchar;
    var $_allpars_text;
    var $_allpars;

    private $_null_fields = [];

    /**
    * @return Auction
    * @param object $db
    * @param string $number
    * @desc Constructor
    */
    function Auction($db, $dbr, $number="", $txnid = "")
    {
	  global $seller_filter_str;
//	  global $debug;
      $debug = 0;
      
      
	  if ($debug)	echo 'Inside AUCTION: '.$number.'/'.$txnid.'<br>';
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::Auction expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        if (!is_a($dbr, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::Auction expects its argument to be a MDB2_Driver_mysql object');
//			print_r($this->_error); die();
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
		$this->_allpars_varchar = $this->_dbr->getAssoc("select distinct `key` f1,`key` f2 from auction_par_varchar");
		$this->_allpars_text = $this->_dbr->getAssoc("select distinct `key` f1,`key` f2 from auction_par_text");
		$allpars = $this->_dbr->getAll("EXPLAIN auction");
		foreach($allpars as $field) {
            if ($field->Null == 'YES' && strpos($field->Type, 'int') !== false) {
                $this->_null_fields[] = $field->Field;
            }
			$this->_allpars[$field->Field] = '';
		}
        if (($txnid==="")&&($number==="")) {
			if ($debug) { echo 'NEW!!!!!!!!!!!!!!!!<br>';}
            $r = $this->_db->query("EXPLAIN auction");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->tracking_numbers = array();
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->data->process_stage = STAGE_LISTED;
            $this->_isNew = true;
        } 
        else if (isset($number) && isset($txnid)) {
            $q = "
                SELECT auction.*, 
                    IFNULL(offer.name,
                    (select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
                        join auction a1 on a1.offer_id=o1.offer_id
                        where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
                    seller_information.contact_name AS contact_name, 
                    seller_information.email AS seller_email,
                    IF($number=0,'0000-00-00 00:00:00', fget_delivery_date_real($number,$txnid)) real_delivery_date_real,
                    shop_promo_codes.code AS promo_code
                FROM auction 
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
                LEFT JOIN seller_information ON auction.username=seller_information.username 
                LEFT JOIN shop_promo_codes ON auction.code_id=shop_promo_codes.id 
                WHERE auction.auction_number=$number
                AND auction.txnid=$txnid";
            
            $this->data = $this->_dbr->getRow($q);
			if ($debug) { echo $q.'<br>EXISTED: '; print_r($this->data); echo '<br>';}
            if (PEAR::isError($this->data)) {
                $this->_error = $this->data;
				aprint_r($this->data);
				die();
            }
			$this->data->delivery_date_real = $this->data->real_delivery_date_real;
			if ((int)$this->data->main_auction_number)
				$pars = $this->_dbr->getAssoc("select `key`,`value` from auction_par_varchar
					where auction_number=".$this->data->main_auction_number." and txnid=".$this->data->main_txnid);
			else 
				$pars = $this->_dbr->getAssoc("select `key`,`value` from auction_par_varchar
					where auction_number=$number and txnid=$txnid");
			foreach($pars as $key=>$value){
                if(in_array($key, ['tel_invoice', 'cel_invoice'])){
                    $value = str_replace([' ', "\n", "\r\n", "\t"], ['','','',''], $value);
                }
                $this->data->$key = $value;
            }
			$pars = $this->_dbr->getAssoc("select `key`,`value` from auction_par_text
				where auction_number=$number and txnid=$txnid");
			foreach($pars as $key=>$value) $this->data->$key = $value;
            if (!($this->data->auction_number==='0' || $this->data->auction_number>0)) {
                $this->_error = PEAR::raiseError("Auction $number / $txnid does not exist");
                aprint_r($this->_error);
//               return;
            }
			global $seller_accounts;
//			print_r($seller_accounts); echo "<br>".$this->data->username."<br>";
//			echo $seller_accounts[$this->data->username];
            if ($seller_accounts[$this->data->username]!="'".$this->data->username."'") {
                $this->_error = PEAR::raiseError("No access");
//                return;
            }
            $q = "
                SELECT n.*, m.company_name, IFNULL(u.name, n.username) as fullusername
                    , IFNULL(u1.name, n.called_back_by) as called_back_by_name
                    , IFNULL(mto.company_name, m.company_name) as called_back_to_name
                    , IFNULL(u2.name, n.stop_by) as stop_by_name
                    , IFNULL(mstopto.company_name, m.company_name) as stop_to_name
                    , IFNULL(u3.name, n.monitored_by) as monitored_by_name
                    , IFNULL(u4.name, n.solved_by) as solved_by_name
                    , p.name as packet_name, p.id packet_id
                    , p.article_id as packet_article_id
                    , REPLACE(REPLACE(REPLACE(m.tracking_url, '[[number]]', n.number), '[[zip]]', '".mysql_real_escape_string($this->data->zip_shipping)."'),'[[country_code2]]', '".countryToCountryCode($this->data->country_shipping)."') tracking_url
                    , orders.quantity AS article_quantity
                FROM tracking_numbers n 
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=n.shipping_method
                LEFT JOIN shipping_method mto 
                    ON mto.shipping_method_id=n.called_back_to
                LEFT JOIN shipping_method mstopto 
                    ON mstopto.shipping_method_id=n.stop_to
                LEFT JOIN tn_packets p ON p.id=n.packet_id
                LEFT JOIN users u ON n.username=u.username 
                LEFT JOIN users u1 ON n.called_back_by=u1.username 
                LEFT JOIN users u2 ON n.stop_by=u2.username 
                LEFT JOIN users u3 ON n.monitored_by=u3.username 
                LEFT JOIN users u4 ON n.solved_by=u4.username 
                LEFT JOIN orders ON orders.id=n.packing_material_order_id
                WHERE n.auction_number='$number' AND n.txnid='$txnid'";
            $this->tracking_numbers = $this->_dbr->getAll($q);
//			if ($number==280310845543) 
//				echo $q;
//			aprint_r($q);
			if (PEAR::isError($this->tracking_numbers)) print_r($this->tracking_numbers); 
			foreach ($this->tracking_numbers as $key=>$value) {
				$this->tracking_numbers[$key]->tn_cb = $dbr->getAll("select cb.*
					, IFNULL(u1.name, cb.called_back_by) as called_back_by_name
					, mto.company_name as called_back_to_name
					, p.name as packet_name, p.id packet_id
					from tn_callback cb
               		LEFT JOIN shipping_method mto 
                    	ON mto.shipping_method_id=cb.called_back_to
                	LEFT JOIN tn_packets p ON p.id=cb.packet_id
                	LEFT JOIN users u1 ON cb.called_back_by=u1.username 
					WHERE cb.tn_id = ".$value->id);
				if (PEAR::isError($this->tracking_numbers[$key]->tn_cb)) aprint_r($this->tracking_numbers[$key]->tn_cb);	
			}	
            $this->data->subinvoices = $this->_dbr->getAll("
                SELECT *
                FROM auction 
				left join invoice on auction.invoice_number=invoice.invoice_number
                WHERE $number>0 and main_auction_number=$number AND txnid=$txnid");
            $this->_isNew = false;
/*            $r = $this->_dbr->getOne("select IFNULL(name, '".$this->data->no_emails_by."') 
	    				  from users where username='".$this->data->no_emails_by."'");
            if (PEAR::isError($r)) {
				aprint_r($r);
            } else {
              	if (strlen($r)) $this->data->no_emails_by = $r; 
				else $this->data->no_emails_by = $r.' welostit'; 
            }*/
        }
        
        $this->_formatInvoiceTel();
        $this->_formatInvoiceCel();
        $this->_formatShippingTel();
        $this->_formatShippingCel();
    }

    /**
    * @return void
    * @param string $field
    * @param mixed $value
    * @desc Set field value
    */
    function set($field, $value)
    {
        if (isset($this->data->$field)
			 || in_array($field,$this->_allpars_varchar)
			 || in_array($field,$this->_allpars_text)
		) {
            $this->data->$field = $value;
        }
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
    * @return boolean|object
    * @desc Update database record
    */
    function update($old_auction_number='')
    {
		global $debug;
		if ($debug) $time = getmicrotime();
		if ($debug) echo 'Auction 0: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
		$old_auction_number = mysql_escape_string($old_auction_number);
		if (strlen($old_auction_number)) {
			$this->_db->query("delete from auction where auction_number=$old_auction_number AND txnid='" . $this->data->txnid . "'");
			$this->_isNew = true;
			$showerror = true;
		}	
		$old_auction_number = $old_auction_number<>'' ? $old_auction_number : $this->data->auction_number;
        $offer_name = $this->data->offer_name;
		$contact_name = $this->data->contact_name;
		$seller_email = $this->data->seller_email;
        unset($this->data->offer_name);
        unset($this->data->contact_name);
        unset($this->data->seller_email);
###        unset($this->data->id);
        unset($this->data->supervisor_email);
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Auction::update : no data');
            return;
        }
//		print_r($this->_allpars); print_r($this->data); die('here');
        if ($this->_isNew) {
			if (strlen($this->data->username)) {
				$seller = new SellerInfo($this->_db, $this->_dbr, $this->data->username);
				if (!(int)$this->data->source_seller_id) {
					$this->data->source_seller_id = $seller->get('def_source_seller_id');
				}
			}
			if ($this->data->txnid==3) $src=''; else $src='_auction';
			$this->_db->query("insert ignore into source_seller_customer (`customer_id`, `src`, source_seller_id)
					values ({$this->data->customer_id}, '$src', {$this->data->source_seller_id})");
			$this->data->hiderating = 1;
            
            clearRatingCache($this->data->auction_number, $this->data->txnid);
		}
		if ($this->_isNew && $this->data->offer_id) {
			$offer = $this->_dbr->getRow("select available, IF(available_weeks, date_add(NOW(), INTERVAL available_weeks week), available_date) real_available_date 
				from offer where offer_id=".$this->data->offer_id);
			$this->data->available=$offer->available;
			$this->data->available_date=$offer->real_available_date;
		}

        foreach ($this->data as $field => $value) {
			if ($field=='id') continue;
			if (in_array($field,$this->_allpars_varchar)) {
				$value = mysql_escape_string($value);
                if(in_array($field, ['tel_invoice', 'cel_invoice'])){
                    $value = str_replace([' ', "\n", "\r\n", "\t"], ['','','',''], $value);
                }
	            $q = "REPLACE INTO auction_par_varchar SET `value`='$value', `key`='$field', auction_number=$old_auction_number, txnid=".$this->data->txnid;
				$r = $this->_db->query($q);
				if ($debug) echo 'Auction 1: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
		        if (PEAR::isError($r)) aprint_r($r);
				continue;
			}
			if (in_array($field,$this->_allpars_text)) {
				$value = mysql_escape_string($value);
	            $q = "REPLACE INTO auction_par_text SET `value`='$value', `key`='$field', auction_number=$old_auction_number, txnid=".$this->data->txnid;
				$r = $this->_db->query($q);
				if ($debug) echo 'Auction 2: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
		        if (PEAR::isError($r)) aprint_r($r);
				continue;
			}
			if (!isset($this->_allpars[$field])) continue;
			if ($field=='dontsend_marked_as_Shipped' && $this->_isNew) 
				$value = $seller->get("dontsend_marked_as_Shipped");
			if ($field=='subinvoices') continue;
			if ($field=='tracking_numbers') continue;
			if ($field=='tracking_list') continue;
			if ($field=='bank') continue;
			if ($field=='currency') continue;
            if ($query) {
                $query .= ', ';
            }
			if (PEAR::isError($value)) {echo $field; print_r($value);}
            
            if (in_array($field, $this->_null_fields) && $value == '') {
                $value = 'NULL';
            } else {
                $value = "'" . mysql_escape_string($value) . "'";
            }
            
            $query .= "`$field`=$value";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
			if (strlen($this->data->username)) {
				if (!$seller->get("dontsend_order_received") && $this->data->auction_number) {
					$rec = new stdClass;
					$rec->auction_number = $this->data->auction_number;
					$rec->txnid = $this->data->txnid;
					$rec->email_invoice = $seller->get("order_received_email");
					$rec->username = Config::get($this->_db, $this->_dbr, 'aatokenSeller');
					if ($rec->email_invoice) standardEmail($this->_db, $this->_dbr, $rec, 'order_received');
				}
			} // send email to seller
        } else {
            $command = "UPDATE";
            $where = "WHERE auction_number='" . $old_auction_number . "' AND txnid='" . $this->data->txnid . "'";
        }
        $r = $this->_db->query("$command auction SET $query $where");
		if ($debug) echo "$command auction SET $query $where<br>";
		$auction_id = mysql_insert_id();
		if ($debug) echo 'mysql_insert_id()='.$auction_id.' Auction 3: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
//		echo "$command auction SET $query $where";
        $this->data->offer_name = $offer_name;
        $this->data->contact_name = $contact_name;
        $this->data->seller_email = $seller_email;
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($this->_allpars);
			echo $r->getMessage();
//			print_r($r); die();
			if ($showerror) aprint_r($r);
        } else { $this->_isNew = false; }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @desc Get all Uactions
    */
    static function listAll($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT * FROM auction WHERE username in $seller_filter");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($auction = $r->fetchRow()) {
            $list[] = $auction;
        }
        return $list;
    }

    static function getSaveID()
    {
        $r = $this->_dbr->getAll("SELECT id FROM saved_auctions where auction_number='".$this->data->auction_number."'");
        if (count($r) < 1) {
			return;
		};
        return $r[0]->id;
//		return 	"SELECT id FROM saved_auctions where auction_number='".$this->data->auction_number."'";
    }

    /**
    * @return array
    * @param object $db
    * @desc Find auctions prepared to pickup
    */
    static function findPrepareToPickup($db, $dbr, $type, $days)
    {
	  global $seller_filter_str;
		if (strlen($seller_filter)) $seller_filter_str = " and username in ($seller_filter) ";
		if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $days = (int)$days;
		$q = "SELECT auction.*
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
			, fget_Currency(auction.siteid) currency
			FROM auction
            WHERE payment_method = '$type'
            AND (
				DATE_ADD(delivery_date_customer, INTERVAL $days DAY) >= NOW()
            )
			AND delivery_date_customer='0000-00-00 00:00:00'
            AND ready_to_pickup=0
			and main_auction_number=0
			$seller_filter_str
            AND auction.deleted=0";
        $list = $dbr->getAll($q);
//		echo $q;
        if (PEAR::isError($list)) {
            return;
        }
        return $list;
    }
    
    static function findPrepareToPickupCount($db, $dbr, $type, $days)
    {
	  global $seller_filter_str;
		if (strlen($seller_filter)) $seller_filter_str = " and username in ($seller_filter) ";
		if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $days = (int)$days;
		$q = "SELECT count(*) FROM auction
            WHERE payment_method = '$type'
            AND (
				DATE_ADD(delivery_date_customer, INTERVAL $days DAY) >= NOW()
            )
			AND delivery_date_customer='0000-00-00 00:00:00'
            AND ready_to_pickup=0
			and main_auction_number=0
			$seller_filter_str
            AND auction.deleted=0";
        $list = $dbr->getOne($q);
//		echo $q;
        if (PEAR::isError($list)) {
            return;
        }
        return $list;
    }

    static function findWaitToPrepare($db, $dbr, $type)
    {
	  global $seller_filter_str;
		if (strlen($seller_filter)) $seller_filter_str = " and username in ($seller_filter) ";
        $list = $dbr->getAll(
            "SELECT auction.*
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
			, invoice.invoice_date FROM auction JOIN invoice ON auction.invoice_number=invoice.invoice_number
            WHERE auction.payment_method =  '$type'
            AND ready_to_pickup = 0
			AND NOT delivery_date_customer='0000-00-00 00:00:00'
			and main_auction_number=0
			$seller_filter_str
            AND auction.deleted = 0"
        );
        return $list;
    }

    static function findReadyToPickup($db, $dbr, $type)
    {
	  global $seller_filter_str;
		if (strlen($seller_filter)) $seller_filter_str = " and username in ($seller_filter) ";
		$q = "SELECT auction.* 
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
		, fget_Currency(auction.siteid) currency
			FROM auction
			join orders on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
            WHERE auction.payment_method = '$type' 
			and auction.main_auction_number=0
			$seller_filter_str
            AND auction.deleted=0
			group by auction.auction_number, auction.txnid
			having min(orders.ready2pickup)=1
            AND min(orders.sent)=0
			";
//		echo $q;
        $list = $dbr->getAll($q);
        return $list;
    }

    static function findReadyToPickupCount($db, $dbr, $type)
    {
	  global $seller_filter_str;
		if (strlen($seller_filter)) $seller_filter_str = " and auction.username in ($seller_filter) ";
		$q = "SELECT count(*) FROM (select null from auction
			join orders on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
            WHERE payment_method = '$type' 
			and auction.main_auction_number=0
			$seller_filter_str
            AND auction.deleted=0
			group by auction.auction_number, auction.txnid
			having min(orders.ready2pickup)=1
            AND min(orders.sent)=0
			) t
			";
        $list = $dbr->getOne($q);
        return $list;
    }

    /**
    * @return unknown
    * @param object $db
    * @param int $days
    * @desc Find auctions that are not picked up for x days
    */
    static function findNotPickedUp($db, $dbr, $days, $type)
    {
	  global $seller_filter_str;
		if (strlen($seller_filter)) $seller_filter_str = " and username in ($seller_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $list = $dbr->getAll("SELECT auction.* 
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
		FROM auction 
		WHERE payment_method = '$type'
		$seller_filter_str
		AND ready_to_pickup = 1 AND pickedup = 0
		AND DATE_ADD(fget_delivery_date_real(auction.auction_number, auction.txnid), INTERVAL $days DAY) <= NOW() 
			and main_auction_number=0
		AND auction.deleted = 0");
        if (PEAR::isError($list)) {
            var_dump($list);
            return;
        }
        return $list;
    }

    static function findPaidArticles($db, $dbr, $criteria, $from=0, $to=9999999, $sort)
    {
//		var_dump($criteria);
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $where = array();
        if ($criteria['name']) {
            $where[] = "CONCAT(au_firstname_invoice.value,' ', au_name_invoice.value) like '%" . mysql_escape_string($criteria['name']) . "%'";
        }
        if ($criteria['minamount']) {
            $where[] = "p.amount >='" . mysql_escape_string($criteria['minamount']) . "'";
        }
        if ($criteria['maxamount']) {
            $where[] = "p.amount <='" . mysql_escape_string($criteria['maxamount']) . "'";
        }
        if ($criteria['mindate']) {
            $where[] = "p.payment_date >='" . mysql_escape_string($criteria['mindate']) . "'";
        }
        if ($criteria['maxdate']) {
            $where[] = "p.payment_date <='" . mysql_escape_string($criteria['maxdate']) . "'";
        }
        if ($criteria['country']) {
            $where[] = "au_country_shipping.value ='" . mysql_escape_string($criteria['country']) . "'";
        }
        if ($criteria['username']) {
            $where[] = "auction.username in (" . $criteria['username'] . ")";
        }
        if ($criteria['state'] != 2) {
            $where[] = "p.exported = " . ($criteria['state']==1 ? 1 : 0);
        }
        if ($criteria['account']) {
            $where[] = "(p.selling_account_number = ". mysql_escape_string($criteria['account'])
					. " or p.account = " . mysql_escape_string($criteria['account']). ")";
        } 
        if (count($where)) {
            $where = ' and ' . implode(' AND ', $where);
        } else {
            $where = '';
        }
		$q = "SELECT ca.value as currency,
					auction.auction_number, 
					auction.txnid, 
					auction.siteid, 
					max( p.payment_date ) AS paid_date, 
					IFNULL(article.article_id, suba.article_id) article_id, 
		(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = IFNULL(article.article_id, suba.article_id)) name,
		(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'description'
				AND language = 'german'
				AND id = IFNULL(article.article_id, suba.article_id)) description,
					IFNULL(orders.price, subo.price) price, 
					IFNULL(article_list.article_list_id, subal.article_list_id) article_list_id,
					au_country_shipping.value country_shipping,
					p.amount
				FROM auction
					left join auction subau on subau.main_auction_number=auction.auction_number 
						and subau.main_txnid=auction.txnid
					left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
						and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
					left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number 
						and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
					left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
						and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'	
					JOIN payment p ON p.auction_number = auction.auction_number
						AND p.txnid = auction.txnid
					left JOIN orders ON orders.auction_number = auction.auction_number
						AND orders.txnid = auction.txnid
					left JOIN orders subo ON subo.auction_number = subau.auction_number
						AND subo.txnid = subau.txnid
					LEFT JOIN article_list ON article_list.article_list_id = orders.article_list_id
					left JOIN article_list subal ON subal.article_list_id = subo.article_list_id
					LEFT JOIN article ON article_list.article_id = article.article_id
					left JOIN article suba ON subal.article_id = suba.article_id
					left join config_api ca on auction.siteid=ca.siteid and ca.par_id=7
				WHERE auction.paid=1 $where
					$seller_filter_str
			and auction.main_auction_number=0
					AND auction.deleted=0
					AND IFNULL(orders.manual,subo.manual)=0
				GROUP BY auction.auction_number, auction.txnid
					, IFNULL(article.article_id, suba.article_id), IFNULL(orders.price, subo.price)
				HAVING 1 
			$sort
			LIMIT $from, $to";
        $list = $dbr->getAll($q);
		
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    /**
    * @return unknown
    * @param unknown $db
    * @desc Find paid auctions
    */
    static function findPaid($db, $dbr, $datefrom=0, $dateto=0, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = '';
		$where1 = '';
		if ($datefrom) {
			$where .= "	AND p.payment_date >= '".$datefrom."' ";
			$where1 .= "	AND max(p.payment_date) >= '".$datefrom."' ";
		}	
		if ($dateto) {
			$where .= "	AND p.payment_date <= '".$dateto." 23:59:59' ";
			$where1 .= "	AND max(p.payment_date) <= '".$dateto." 23:59:59' ";
		}
		$q = "SELECT 
				auction.auction_number ,
					auction.siteid, 
				auction.end_time ,
				auction.username ,
				auction.no_emails ,
				auction.freeze_date ,
				auction.txnid ,
				au_name_invoice.value name_invoice,
				au_firstname_invoice.value firstname_invoice,
				au_email_invoice.value email_invoice,
				IFNULL(users.name, auction.responsible_uname) responsible_uname,
		IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
				max(p.payment_date) as paid_date 
				, fget_Currency(auction.siteid) currency
				, fget_AType(auction.auction_number, auction.txnid) src
			FROM auction 
				left JOIN offer ON offer.offer_id = auction.offer_id 
				JOIN payment p ON p.auction_number = auction.auction_number 
			        AND p.txnid = auction.txnid 
				LEFT JOIN users ON users.username=auction.responsible_uname 
				left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
					and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
				left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number 
					and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
				left join auction_par_varchar au_email_invoice on auction.auction_number=au_email_invoice.auction_number 
					and auction.txnid=au_email_invoice.txnid and au_email_invoice.key='email_invoice'
            WHERE auction.paid 
				$seller_filter_str
				and main_auction_number=0
				AND NOT auction.deleted
				$where
			group by 
				auction_number ,
				end_time ,
				username ,
				no_emails ,
				freeze_date ,
				txnid ,
				responsible_uname ,
				offer.name
				HAVING 1 $where1
			$sort
			LIMIT $from, $to";
//		echo $q;		
        $list = $dbr->getAll($q);
		
        if (PEAR::isError($list)) {
            print_r($list);
//            $this->_error = $list;
            return;
        }
        return $list;
    }

    static function findPaidCount($db, $dbr, $datefrom=0, $dateto=0)
    {
	  global $seller_filter_str;
		$where = '';
		if ($datefrom)
			$where .= "	AND (TO_DAYS((SELECT max(p.payment_date) FROM payment p WHERE p.auction_number = auction.auction_number AND p.txnid = auction.txnid ))) >= TO_DAYS('".$datefrom."') ";
		if ($dateto)
			$where .= "	AND (TO_DAYS((SELECT max(p.payment_date) FROM payment p WHERE p.auction_number = auction.auction_number AND p.txnid = auction.txnid ))) <= TO_DAYS('".$dateto."') ";
        $cnt = $dbr->getOne("SELECT count(*)
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			WHERE auction.paid 
			and main_auction_number=0
  	  		$seller_filter_str
			$where
			AND NOT auction.deleted");
        return $cnt;
    }

    static function findDeleted($db, $dbr, $deleted_uname, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*
				, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE auction.deleted
		and main_auction_number=0
		and (deleted_by='$deleted_uname' or '$deleted_uname'='')
		$seller_filter_str
	   $sort LIMIT $from, $to");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findDeletedCount($db, $dbr)
    {
	  global $seller_filter_str;
        $cnt = $dbr->getOne("SELECT count(*)
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			WHERE auction.deleted
			and main_auction_number=0
			$seller_filter_str
			");
        return $cnt;
    }

    /**
    * @return array
    * @param object $db
    * @param int $days
    * @desc Find won auctions without order placed for x days
    */
    static function findUncompleted($db, $dbr, $days, $username_filter="''")
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if ($username_filter!="''") $username_filter = " and auction.username in ($username_filter) ";
			else $username_filter = '';
		$q = "SELECT auction.*,
		IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as description,
		IFNULL(users.name, auction.responsible_uname) responsible_uname
		FROM auction 
		LEFT JOIN offer ON offer.offer_id=auction.offer_id 
		LEFT JOIN users ON users.username=auction.responsible_uname 
			WHERE (payment_method = '0' or payment_method='') AND end_time <> '0000-00-00 00:00:00' 
			and main_auction_number=0
			$seller_filter_str
			$username_filter
			AND DATE_ADD(end_time, INTERVAL $days DAY) <= NOW() AND auction.deleted = 0
			AND process_stage <> ".STAGE_NO_WINNER;
//		echo $q;
        $list = $dbr->getAll($q);
        if (PEAR::isError($list)) {
			aprint_r($list);
            return;
        }
        return $list;
    }

    /**
    * @return array
    * @param object $db
    * @desc Find active auctions
    */
    static function findActive($db, $dbr, $seller='', $type='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT 
				offer_name.name alias, 
				au_server.value as server, 
				auction.auction_number, 
				auction.txnid, 
				auction.username, 
				auction.offer_id, 
				auction.saved_id, 
				auction.start_time, 
				auction.end_time,
				'A' as type,
				siteid
			FROM auction
			LEFT JOIN offer_name ON auction.name_id = offer_name.id
			left join auction_par_varchar au_server on auction.auction_number=au_server.auction_number 
						and auction.txnid=au_server.txnid and au_server.key='server'
	      	JOIN seller_information ON seller_information.username=auction.username 
	      	WHERE (process_stage=". STAGE_LISTED ." OR process_stage=". STAGE_RELISTED .") 
			and main_auction_number=0
	      		AND NOT auction.deleted AND auction.txnid>0 
			".(strlen($seller)?" and auction.username='$seller' ":'')."
			".(strlen($type)?" and 'A'='$type' ":'')."
			$seller_filter_str
				union all
			SELECT 
				offer_name.name alias, 
				auction.server, 
				auction.auction_number, 
				NULL as txnid, 
				auction.username, 
				auction.offer_id, 
				auction.saved_id, 
				auction.start_time, 
				auction.end_time,
				'F' as type,
				siteid
			FROM listings auction
			LEFT JOIN offer_name ON auction.name_id = offer_name.id
	      	JOIN seller_information ON seller_information.username=auction.username 
	      	WHERE IFNULL(finished,0) = 0 and 
			IFNULL(quantity,0) > 0 
			and end_time>now()
			".(strlen($seller)?" and auction.username='$seller' ":'')."
			".(strlen($type)?" and 'F'='$type' ":'')."
			$seller_filter_str
	      ";
        $list = $dbr->getAll($q);
//	      AND seller_information.isActive
        if (PEAR::isError($list)) {
			aprint_r($list);
            return;
        }
        return $list;
    }

    /**
    * @return array
    * @param object $db
    * @param int $days
    * @desc Find auctions for which buyers have not given rating within x days
    */
    static function findNoRating($db, $dbr, $days)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $res = $db->query("SELECT  auction.* 
		FROM auction LEFT JOIN seller_information ON auction.username = seller_information.username 
		WHERE fget_ASent(auction.auction_number, auction.txnid)=1 AND NOT auction.rating_reminder_sent 
			and main_auction_number=0
		AND NOT auction.rating_received AND DATE_ADD(fget_delivery_date_real(auction.auction_number, auction.txnid), INTERVAL $days DAY) <= NOW() 
		AND (auction.pickedup OR auction.shipping_method) AND seller_information.isActive = 1 
		$seller_filter_str
		AND NOT auction.deleted 
		GROUP BY auction.auction_number ");
        return $res;
    }

    /**
    * @return array
    * @param object $db
    * @param int $days
    * @desc Find auctions unpaid for x days
    */
    static function findUnpaid($db, $dbr, $days, $type)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        if ($type == 1 || $type == 2 || $type ==3) {
            $date = 'fget_delivery_date_real(auction.auction_number, auction.txnid)';
        } else {
            $date = 'invoice.invoice_date';
        }
		$q = "SELECT auction.*
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
		, invoice.invoice_date, invoice.total_price+invoice.total_shipping as total
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
	    , invoice.open_amount, invoice.total_price + invoice.total_shipping - invoice.open_amount paid_amount
		FROM auction 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
            JOIN invoice ON invoice.invoice_number = auction.invoice_number 
            WHERE auction.paid = 0
			and main_auction_number=0
                AND auction.payment_method = '$type'
                AND ((DATE_ADD($date, INTERVAL $days DAY) <= NOW() 
				) or $date = '0000-00-00 00:00:00')
				$seller_filter_str
				and main_auction_number=0
                AND auction.deleted = 0";
        $r = $dbr->getAll($q);
//		echo $q;
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

	static function findSecondChance($db, $dbr, $hours){
		$q = "SELECT auction.*
			FROM auction 
            WHERE txnid=1
#			and auction_number = 280312664810
			and DATE_SUB(NOW(), INTERVAL $hours HOUR) between 
				DATE_SUB(end_time, INTERVAL 0 HOUR) and DATE_ADD(end_time, INTERVAL 1 HOUR)
		";
//			and DATE_SUB(NOW(), INTERVAL $hours HOUR) between 
//				DATE_SUB(end_time, INTERVAL 1 HOUR) and DATE_ADD(end_time, INTERVAL 1 HOUR)

        $r = $dbr->getAll($q);
		return $r;
	}
	
    static function findUnpaid1_date($db, $dbr, $date_to_compare, $type, $from=0, $to=9999999, $sort, $method_filter="''", $username_filter="''")
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($method_filter!="''" && $method_filter!="") $method_filter = " and tn.shipping_method in ($method_filter) ";
			else $method_filter = '';
		if ($username_filter!="''") $username_filter = " and auction.username in ($username_filter) ";
			else $username_filter = '';
        if ($type == 1) {
            $date = 'invoice.invoice_date'; //'el.date';
        } elseif ($type == 2 || $type ==3) {
            $date = 'fget_delivery_date_real(auction.auction_number, auction.txnid)';
        } else {
            $date = 'invoice.invoice_date';
        }
        if ($type == 1) 
			$reddays = Config::get($db, $dbr, 'ship_unpaid');
		elseif ($type == 2) 
			$reddays = Config::get($db, $dbr, 'cod_unpaid');
		else 	
			$reddays = 0;
        
		$q = "SELECT distinct auction.auction_number
		, auction.txnid
		, auction.end_time
		, auction.username
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, invoice.invoice_date, invoice.total_price+invoice.total_shipping as total
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
	    , invoice.open_amount, invoice.total_price + invoice.total_shipping + invoice.total_cod - invoice.open_amount paid_amount
		, IF(DATE_ADD($date, INTERVAL $reddays DAY) <= NOW(), 1, 0) too_old
		, max(invoice.invoice_date) date_confirmation
		, $date display_date
		, GROUP_CONCAT(tn.number ORDER BY tn.id SEPARATOR '<br>') tracking_number
		, GROUP_CONCAT(m.company_name ORDER BY tn.id SEPARATOR '<br>') shipping_company
		, fget_Currency(auction.siteid) currency
		, auction.siteid
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, au_zip_shipping.value as zip_shipping
		, au_city_shipping.value as city_shipping
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
		FROM auction 
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
			left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number
				and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
			left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number
				and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
            JOIN invoice ON invoice.invoice_number = auction.invoice_number 
		LEFT JOIN tracking_numbers tn ON tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
		LEFT JOIN shipping_method m ON m.shipping_method_id = tn.shipping_method
            WHERE auction.payment_method = '$type'
                AND DATE($date) <= '$date_to_compare'
                AND DATE($date) > '0000-00-00'
				$seller_filter_str
				$method_filter
				$username_filter
                AND auction.deleted = 0
		and IFNULL((select sum(amount) from payment where auction_number=auction.auction_number and txnid=auction.txnid
			and payment_date <= '$date_to_compare'),0)
			< (invoice.total_price + invoice.total_shipping + invoice.total_cod + invoice.total_cc_fee)
				and main_auction_number=0
				group by auction.auction_number, auction.txnid
			$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findUnpaid1($db, $dbr, $days, $type, $from=0, $to=9999999, $sort, $method_filter="''", $username_filter="''")
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($method_filter!="''" && $method_filter!="") $method_filter = " and tn.shipping_method in ($method_filter) ";
			else $method_filter = '';
		if ($username_filter!="''") $username_filter = " and auction.username in ($username_filter) ";
			else $username_filter = '';
        if ($type == 1) {
            $date = 'invoice.invoice_date'; //'el.date';
        } elseif ($type == 2 || $type ==3) {
            $date = 'fget_delivery_date_real(auction.auction_number, auction.txnid)';
        } else {
            $date = 'invoice.invoice_date';
        }
        if ($type == 1) 
			$reddays = Config::get($db, $dbr, 'ship_unpaid');
		elseif ($type == 2) 
			$reddays = Config::get($db, $dbr, 'cod_unpaid');
		else 	
			$reddays = 0;
		$q = "SELECT distinct auction.auction_number
        , auction.id auction_id
		, auction.txnid
		, auction.end_time
		, auction.username
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, invoice.invoice_date, invoice.total_price+invoice.total_shipping as total
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
	    , invoice.open_amount, invoice.total_price + invoice.total_shipping + invoice.total_cod - invoice.open_amount paid_amount
		, IF(DATE_ADD($date, INTERVAL $reddays DAY) <= NOW(), 1, 0) too_old
		, max(invoice.invoice_date) date_confirmation
		, $date display_date
		, GROUP_CONCAT(tn.number ORDER BY tn.id SEPARATOR '<br>') tracking_number
		, GROUP_CONCAT(m.company_name ORDER BY tn.id SEPARATOR '<br>') shipping_company
		, fget_Currency(auction.siteid) currency
		, auction.siteid
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, CONCAT(au_firstname_invoice.value,' ',au_name_invoice.value) as name_billing
		, au_company_shipping.value as company_shipping
		, au_company_invoice.value as company_billing
		, au_zip_shipping.value as zip_shipping
		, au_city_shipping.value as city_shipping
		, au_name_invoice.value as last_name_billing
        , au_name_shipping.value as last_name_shipping
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
		FROM auction 
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
            left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number
				and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
            left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number
				and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
			left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number
				and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
			left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number
				and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
            left join auction_par_varchar au_company_shipping on auction.auction_number=au_company_shipping.auction_number
				and auction.txnid=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
            left join auction_par_varchar au_company_invoice on auction.auction_number=au_company_invoice.auction_number
				and auction.txnid=au_company_invoice.txnid and au_company_invoice.key='company_invoice'
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
            JOIN invoice ON invoice.invoice_number = auction.invoice_number 
		LEFT JOIN tracking_numbers tn ON tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
		LEFT JOIN shipping_method m ON m.shipping_method_id = tn.shipping_method
            WHERE auction.paid = 0
                AND auction.payment_method = '$type'
                AND (DATE_ADD($date, INTERVAL $days DAY) <= NOW())
				$seller_filter_str
				$method_filter
				$username_filter
                AND auction.deleted = 0
				and invoice.open_amount>0
				and main_auction_number=0
				group by auction.auction_number, auction.txnid
			$sort
			LIMIT $from, $to";
//		echo $q;	
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findUnpaidCount($db, $dbr, $days, $type, $method_filter="", $username_filter="''")
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($method_filter!="") $method_filter = " and tn.shipping_method in ($method_filter) ";
			else $method_filter = '';
		if ($username_filter!="''") $username_filter = " and auction.username in ($username_filter) ";
			else $username_filter = '';
        if ($type == 1) {
            $date = 'el.date';
        } elseif ($type == 2 || $type ==3) {
            $date = 'fget_delivery_date_real(auction.auction_number, auction.txnid)';
        } else {
            $date = 'invoice.invoice_date';
        }
        if ($type == 1) 
			$reddays = Config::get($db, $dbr, 'ship_unpaid');
		elseif ($type == 2) 
			$reddays = Config::get($db, $dbr, 'cod_unpaid');
		else 	
			$reddays = 0;
        $r = $dbr->getOne(
            "SELECT count(*) FROM (SELECT distinct auction.*, invoice.invoice_date, invoice.total_price+invoice.total_shipping as total
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
	    , invoice.open_amount, invoice.total_price + invoice.total_shipping + invoice.total_cod - invoice.open_amount paid_amount
		, IF(DATE_ADD($date, INTERVAL $reddays DAY) <= NOW(), 1, 0) too_old, max(el.date) date_confirmation
		FROM auction 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
            JOIN invoice ON invoice.invoice_number = auction.invoice_number 
			LEFT JOIN email_log el ON el.template = 'order_confirmation'
					AND el.auction_number = auction.auction_number
					AND el.txnid = auction.txnid
			LEFT JOIN tracking_numbers tn ON tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
            WHERE auction.paid = 0
                AND auction.payment_method = '$type'
                AND (DATE_ADD($date, INTERVAL $days DAY) <= NOW())
				$seller_filter_str
				$method_filter
				$username_filter
                AND auction.deleted = 0
				and invoice.open_amount>0
				and main_auction_number=0
				group by auction.auction_number, auction.txnid) t"
        );
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @param int $days
    * @desc Find auctions COD unpaid for x days
    */
    static function findUnpaidCOD($db, $dbr, $days)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findPaid expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $db->query("SELECT auction.*, invoice.invoice_date, invoice.total_price+invoice.total_shipping as total
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction 
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number 
		WHERE auction.paid = 0 AND auction.payment_method = '2' AND DATE_ADD(invoice.invoice_date, INTERVAL $days DAY) <= NOW() 
			and main_auction_number=0
		$seller_filter_str
		AND auction.deleted = 0");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($auction = $r->fetchRow()) {
            $auction_number = $auction->auction_number;
            $pr = $db->query("SELECT SUM(amount) as total FROM payment WHERE auction_number='$auction_number'");
            $pr = $pr->fetchRow();
            if ($pr->total < $auction->total) {
                $auction->open_amount = $auction->total - $pr->total;
                $list[] = $auction;
            }
        }
        return $list;
    }

    /**
    * @return array
    * @param object $db
    * @desc Finds auctions listed, but not finished 
    */
    static function findListed($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findListed expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.* 
		FROM auction  
		WHERE auction.process_stage = " . STAGE_LISTED . " 
		$seller_filter_str
		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
     * @return array
     * @param object $db
     * @desc Find auction ready to ship
     */
    static function findReadyToShip($db, $dbr, $type, $days = 0, $count = 0, $username_filter = "''", $from = 0, $to = 9999999, $sort)
    {
        global $seller_filter_str;
        global $loggedUser;
        $days = (int)$days;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        if ($username_filter != "''") $username_filter = " and auction.username in ($username_filter) ";
        else $username_filter = '';
        if ($type == 2 || $type == 'cc_pck') {
            $date = 'auction.confirmation_date';
        } elseif ($type == 'cc_shp') {
            $date = 'invoice.invoice_date';
        } else {
            $date = "IFNULL(( p.payment_date ), auction.end_time)";
        }
        if ($type == 1) {
            $reddays = Config::get($db, $dbr, 'ship_ready_to_ship');
        } elseif ($type == 2) {
            $reddays = Config::get($db, $dbr, 'cod_ready_to_ship');
        } elseif ($type == 'cc_shp') {
            $reddays = Config::get($db, $dbr, 'cc_ready_to_ship');
        } else {
            $reddays = 0;
        }
        $q = "
			SELECT SQL_CALC_FOUND_ROWS auction.id, 
				auction.auction_number,
				auction.txnid,
				auction.end_time,
				auction.username,
				auction.siteid,
				IFNULL(
					offer.name,
					(
						SELECT GROUP_CONCAT(o1.name SEPARATOR '<br><br>') 
						FROM offer o1
							JOIN auction a1 ON a1.offer_id=o1.offer_id
						WHERE 
							a1.main_auction_number=auction.auction_number 
							AND a1.main_txnid=auction.txnid
					)
				) AS offer_name,
				IF(
					delivery_date_customer='0000-00-00', 
					'0000-00-00', 
					IF(
						TO_DAYS(NOW())>=TO_DAYS(delivery_date_customer), 
						CONCAT(
							'<span style=\"color:#FF0000;font-weight:bold;text-decoration:blink\">', 
							delivery_date_customer, 
							'</span>'),
						CONCAT(
							'<span style=\"color:#FF0000;font-weight:bold;\">', 
							delivery_date_customer, 
							'</span>')

					)
				) delivery_date_customer_colored,
				SUM(p.amount) paid_amount, 
				IFNULL(
					MAX( p.payment_date ), 
					auction.confirmation_date
				) paid_date,
				(
					SELECT MIN(log_date) 
					FROM printer_log 
					WHERE 
						auction_number=auction.auction_number 
						AND txnid=auction.txnid
						AND printer_log.username='" . $loggedUser->data->username . "'
				) print_date,
				CONCAT(
					au_firstname_shipping.value, ' ',
					au_name_shipping.value, '<br>',
					au_zip_shipping.value, ' ',
					au_city_shipping.value, '<br>',
					au_country_shipping.value
				) AS name_shipping,
				invoice.open_amount,
				invoice.invoice_date,
				IF(
					(
						DATE_ADD($date, INTERVAL $reddays DAY) <= NOW()
						AND TO_DAYS(NOW())>=IFNULL(TO_DAYS(delivery_date_customer),0)
					)
					, 'red'
					, ''
				) too_old,
				auction.confirmation_date date_confirmation,
				IFNULL(u1.name, auction.responsible_uname) responsible_full_uname,
				CONCAT(
					'<a href=\'shipping_auction.php?number=', auction.auction_number,'&txnid=', auction.txnid, '\'>',
					IFNULL(u2.name, auction.shipping_resp_username),'</a>'
				) shipping_resp_full_username,
				IFNULL(u3.name, auction.shipping_username) shipping_full_username,
				(
					SELECT MAX(updated) 
					FROM total_log 
					WHERE 
						table_name='auction' 
						AND field_name='shipping_username' 
						AND new_value IS NOT NULL 
						AND tableid=auction.id
				) shipping_username_updated,
				CONCAT(
					IFNULL(
						(
							SELECT GROUP_CONCAT(
								CONCAT(
									IF(
										orders.manual=0,
										CONCAT('<a onmouseover=\"get_info(\'',orders.article_id,'\')\" onmouseout=\"UnTip()\" target=\"_blank\" href=\"article.php?original_article_id=',orders.article_id,'\">'),
										''),
									'<b>',
									orders.article_id, '</b>: ',
									IFNULL(orders.custom_title,''),
									IF(orders.manual=0,'</a>',''),
									IF(wwo_order_id, CONCAT('<br><a style=\'color:#FF00FF\' target=\'_blank\' href=\'ware2ware_order.php?id=',wwo_article.wwo_id,'\'>wwo:',wwo_article.wwo_id,'</a>'),''),
									IF(
										spec_order_id, 
										CONCAT('<br><a target=\'_blank\' href=\'op_order.php?id=',spec_order_id,'\'>ops:',spec_order_id,'</a>'),
										''
									)
								) 
								SEPARATOR '<br>'
								)
							FROM orders 
								LEFT JOIN wwo_article ON wwo_article.id=orders.wwo_order_id
							WHERE sent=0 
								AND orders.auction_number=auction.auction_number 
								AND orders.txnid=auction.txnid
						),
						''),
					'<br>', 
					IFNULL(
						(
							SELECT GROUP_CONCAT(
								CONCAT(
									IF(
										nsaorder.manual=0,
										CONCAT('<a onmouseover=\"get_info(\'',nsaorder.article_id,'\')\" onmouseout=\"UnTip()\" target=\"_blank\" href=\"article.php?original_article_id=',nsaorder.article_id,'\">')
										,''
									),
									'<b>',
									nsaorder.article_id, 
									'</b>: ',
									IF(IFNULL(nsaorder.custom_title,'')='',t_a.value,IFNULL(nsaorder.custom_title,'')), 
									IF(nsaorder.manual=0,'</a>',''),
									IF(wwo_order_id, CONCAT('<br><a target=\'_blank\' style=\'color:#FF00FF\' href=\'ware2ware_order.php?id=',wwo_article.wwo_id,'\'>wwo:',wwo_article.wwo_id,'</a>'),''),
									IF(spec_order_id, CONCAT('<br><a target=\'_blank\' href=\'op_order.php?id=',spec_order_id,'\'>ops:',spec_order_id,'</a>'),'')
								) 
								SEPARATOR '<br>'
							)
							FROM orders nsaorder
								LEFT JOIN auction au ON 
									au.auction_number=nsaorder.auction_number 
									AND au.txnid=nsaorder.txnid
								LEFT JOIN auction mau ON 
									mau.auction_number=au.main_auction_number 
									AND mau.txnid=au.main_txnid
								LEFT JOIN translation t_a ON 
									t_a.id=nsaorder.article_id 
									AND t_a.table_name='article' 
									AND t_a.field_name='name'
									AND t_a.language=mau.lang
								LEFT JOIN wwo_article ON wwo_article.id=nsaorder.wwo_order_id
							WHERE sent=0 
								AND mau.auction_number=auction.auction_number 
								AND mau.txnid=auction.txnid
						),
						''
					)
				) not_sent_articles,
				IFNULL(
					(
						SELECT SUM(
							orders.quantity *
							IFNULL(
								(
									SELECT article_import.total_item_cost 
									FROM article_import
									WHERE 
										article_import.country_code=w.country_code
										AND article_import.article_id=article.article_id
									ORDER BY import_date DESC 
									LIMIT 1
								),
								article.total_item_cost
							)
						)
						FROM orders 
							LEFT JOIN warehouse w ON orders.send_warehouse_id=w.warehouse_id
							LEFT JOIN article ON 
								orders.article_id=article.article_id 
								AND article.admin_id=0
						WHERE 
							sent=0 
							AND orders.auction_number=auction.auction_number 
							AND orders.txnid=auction.txnid
					)
					,0
				) 
				+ IFNULL(
					(
						SELECT SUM(
							orders.quantity *
							IFNULL (
								(
									SELECT article_import.total_item_cost 
									FROM article_import
									WHERE 
										article_import.country_code=w.country_code
										AND article_import.article_id=article.article_id
									ORDER BY import_date DESC 
									LIMIT 1
								),
								article.total_item_cost
							)
						) 
						FROM orders 
							LEFT JOIN warehouse w ON orders.send_warehouse_id=w.warehouse_id
							LEFT JOIN auction au ON 
								au.auction_number=orders.auction_number 
								AND au.txnid=orders.txnid
							LEFT JOIN auction mau ON 
								mau.auction_number=au.main_auction_number 
								AND mau.txnid=au.main_txnid
							LEFT JOIN article ON 
								orders.article_id=article.article_id 
								AND article.admin_id=0
						WHERE 
							sent=0 
							AND mau.auction_number=auction.auction_number 
							AND mau.txnid=auction.txnid
					),
					0
				) total_item_cost,
				IFNULL(
					(
						SELECT SUM(
							orders.quantity *
							IFNULL(
								(
									SELECT article_import.purchase_price 
									FROM article_import
									WHERE 
										article_import.country_code=w.country_code
										AND article_import.article_id=article.article_id
									ORDER BY import_date DESC 
									LIMIT 1
								),
								article.purchase_price
							)
						) FROM orders 
						LEFT JOIN warehouse w ON orders.send_warehouse_id=w.warehouse_id
						LEFT JOIN article ON 
							orders.article_id=article.article_id 
							AND article.admin_id=0
						WHERE sent=0 
							AND orders.auction_number=auction.auction_number 
							AND orders.txnid=auction.txnid
					),
					0
				)+IFNULL(
					(
						SELECT SUM(
							orders.quantity*
							IFNULL(
								(
									SELECT article_import.purchase_price 
									FROM article_import
									WHERE 
										article_import.country_code=w.country_code
										AND article_import.article_id=article.article_id
									ORDER BY import_date DESC 
									LIMIT 1
								),
								article.purchase_price
							)
						) 
						FROM orders 
							LEFT JOIN warehouse w ON orders.send_warehouse_id=w.warehouse_id
							LEFT JOIN auction au ON 
								au.auction_number=orders.auction_number 
								AND au.txnid=orders.txnid
							LEFT JOIN auction mau ON 
								mau.auction_number=au.main_auction_number 
								AND mau.txnid=au.main_txnid
							LEFT JOIN article ON 
								orders.article_id=article.article_id 
								AND article.admin_id=0
						WHERE 
							sent=0 
							AND mau.auction_number=auction.auction_number 
							AND mau.txnid=auction.txnid
					),
					0
				) purchase_price,
				IF(
					(
						auction.delivery_date_customer < IFNULL(MAX( p.payment_date ), auction.confirmation_date) 
						AND auction.delivery_date_customer<>'0000-00-00'
					),
					DATEDIFF(NOW(), auction.delivery_date_customer),
					DATEDIFF(NOW(), IFNULL(MAX( p.payment_date ), auction.confirmation_date))
				) days_due,
				route.name route
			FROM auction
				LEFT JOIN route ON route.id=auction.route_id
				LEFT JOIN auction_par_varchar au_name_shipping ON 
					auction.auction_number=au_name_shipping.auction_number 
					AND auction.txnid=au_name_shipping.txnid 
					AND au_name_shipping.key='name_shipping'
				LEFT JOIN auction_par_varchar au_firstname_shipping ON 
					auction.auction_number=au_firstname_shipping.auction_number 
					AND auction.txnid=au_firstname_shipping.txnid 
					AND au_firstname_shipping.key='firstname_shipping'
				LEFT JOIN auction_par_varchar au_zip_shipping ON 
					auction.auction_number=au_zip_shipping.auction_number 
					AND auction.txnid=au_zip_shipping.txnid 
					AND au_zip_shipping.key='zip_shipping'
				LEFT JOIN auction_par_varchar au_city_shipping ON 
					auction.auction_number=au_city_shipping.auction_number 
					AND auction.txnid=au_city_shipping.txnid 
					AND au_city_shipping.key='city_shipping'
				LEFT JOIN auction_par_varchar au_country_shipping ON 
					auction.auction_number=au_country_shipping.auction_number 
					AND auction.txnid=au_country_shipping.txnid AND au_country_shipping.key='country_shipping'
				LEFT JOIN offer ON offer.offer_id = auction.offer_id
				LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
				LEFT JOIN payment p ON 
					p.auction_number = auction.auction_number 
					AND p.txnid = auction.txnid
				LEFT JOIN users u1 ON auction.responsible_uname = u1.username
				LEFT JOIN users u2 ON auction.shipping_resp_username = u2.username
				LEFT JOIN users u3 ON auction.shipping_username = u3.username
			WHERE
				TO_DAYS(NOW()) - IFNULL(TO_DAYS($date), 0) >= $days 
				" . (($type != 2) ? "AND invoice.open_amount<=0" : "") . "
				AND auction.payment_method = '$type'
				$seller_filter_str
				$username_filter
				AND auction.deleted = 0
				AND main_auction_number=0
				AND (
					EXISTS(
						SELECT null 
						FROM orders 
						WHERE 
							auction_number=auction.auction_number 
							AND txnid=auction.txnid 
							AND sent=0
					)
					OR EXISTS (
						SELECT null 
						FROM orders 
							JOIN auction sau ON 
								sau.auction_number=orders.auction_number 
								AND sau.txnid=orders.txnid
						WHERE 
							auction.auction_number=sau.main_auction_number 
							AND auction.txnid=sau.main_txnid 
							AND sent=0
					)
				)
				AND NOT fget_ASent(auction.auction_number, auction.txnid)
			GROUP BY auction.auction_number, auction.txnid
			LIMIT $from, $to
			";
        if ($count) {
            $q = "select count(*) from ($q) t";
            $r = $dbr->getOne($q);
        } else {
            $r = $dbr->getAll($q);
        }
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findReady2ShipAll($db, $dbr, $warehouse_id, $article_id, $cons='')
    {
	  global $seller_filter_str;
	  global $loggedUser;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($cons=='cons') $article_id = $dbr->getOne("select GROUP_CONCAT(article_id SEPARATOR '\',\'') from article
			where cons_id='$article_id'");
		$q = "select SQL_CALC_FOUND_ROWS
			IFNULL(mauction.auction_number,auction.auction_number) auction_number
			, IFNULL(mauction.txnid,auction.txnid) txnid
			, auction.offer_id
			, auction.txnid
			, auction.end_time
			, auction.siteid
			, fget_Currency(auction.siteid) currency
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, SUM(o.quantity) quantity
			, auction.confirmation_date date_confirmation
			, IFNULL(u1.name, IFNULL(mauction.responsible_uname,auction.responsible_uname)) responsible_full_uname
			, CONCAT('<a href=\'shipping_auction.php?number=', auction.auction_number,'&txnid=', auction.txnid, '\'>'
				,IFNULL(u2.name, IFNULL(mauction.shipping_resp_username,auction.shipping_resp_username)),'</a>') shipping_resp_full_username
			, IFNULL(u3.name, auction.shipping_username) shipping_full_username
			, (select max(updated) from total_log where table_name='auction' and field_name='shipping_username' 
				and new_value is not null and tableid=auction.id) shipping_username_updated
			, sum(p.amount) paid_amount
			, IFNULL(max( p.payment_date ), 'FREE') paid_date
			, (select min(log_date) from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					and printer_log.username='".$loggedUser->data->username."') print_date
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
			, invoice.open_amount
			, invoice.invoice_date
			from orders o
			join auction auction on auction.auction_number=o.auction_number and auction.txnid=o.txnid
			left join auction mauction on auction.main_auction_number=mauction.auction_number 
				and auction.main_txnid=mauction.txnid
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
            LEFT JOIN offer ON offer.offer_id = auction.offer_id
			join invoice on auction.invoice_number=invoice.invoice_number
			LEFT JOIN payment p ON p.auction_number = auction.auction_number AND p.txnid = auction.txnid
            LEFT JOIN users u1 ON IFNULL(mauction.responsible_uname, auction.responsible_uname) = u1.username
            LEFT JOIN users u2 ON IFNULL(mauction.shipping_resp_username, auction.shipping_resp_username) = u2.username
            LEFT JOIN users u3 ON IFNULL(mauction.shipping_username, auction.shipping_username) = u3.username
			where o.sent=0 and auction.deleted=0
			$seller_filter_str
			and (
				IFNULL(mauction.paid, auction.paid)=1 
					or IFNULL(mauction.payment_method, auction.payment_method) in ('2','3','4')
			)
			and o.article_id in ('".$article_id."')
			 ".($warehouse_id?" and o.reserve_warehouse_id=$warehouse_id":'')."
			 group by auction.id
			";
	    $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByShipUser($db, $dbr, $pars)
    {
        global $debug;
        if ($debug) {
            $time = getmicrotime();
        }
        $open_amount = $pars['open_amount'];
        $print = $pars['print'];
        $username = $pars['username'];
        $shipping_mode = $pars['shipping_mode'];
        $date_from = $pars['date_from'];
        $date_to = $pars['date_to'];
        $country_shipping = $pars['country_shipping'];
        $wares_res = $pars['wares_res'];
        $route_id = $pars['route_id'];
        $ina_route_id = $pars['ina_route_id'];
        $route_unassigned = $pars['route_unassigned'];
        $gmap_bonus = $pars['gmap_bonus'];
        $gmap_date = $pars['gmap_date'];
        $gmap_task = $pars['gmap_task'];
        $gmap_confirm = $pars['gmap_confirm'];
        $timestamp = $pars['timestamp'];
        $timestamp_username = mysql_escape_string($pars['timestamp_username']);
        $exported = $pars['exported'];
        $exported_format = $pars['exported_format'];
        $ids = $pars['ids'];
        $article_id = $pars['article_id'];
        $repack = $pars['repack'];
        $released = $pars['released'];
        $loading_ramp = $pars['loading_ramp'];
        $main_route_id = $pars['main_route_id'];
        $route_date_from = $pars['route_date_from'];
        $route_date_to = $pars['route_date_to'];
        $routes_responsible_uname = $pars['routes_responsible_uname'];
        $find_shipped = ($pars['find_shipped'] == 1) ? 1 : 0;
        $packed_status = $pars['packed_status'];
        
        if (strlen($username)) $where = " and auction.shipping_username in ('$username'/*,'_driver'*/) ";
        elseif (!count($ids)) {
            $where = " and auction.shipping_username is not null ";
            if (!count($pars)) {
                echo " too slow if we dont set any other filter";
                return; // too slow if we dont set any other filter
            }
        }
        // for by country
        $country_code = $pars['country_code'];
        $warehouse_id = $pars['warehouse_id'];
        if (strlen($country_code)) $where .= " and au_sh_country.value='" . countryCodeToCountry($country_code) . "' ";
        $warehouse_id = (int)$warehouse_id;
        if (strlen($shipping_username)) $where .= " and auction.shipping_username='$shipping_username' ";
        if (strlen($country_code) || $warehouse_id) {
            $join = " 		, (select GROUP_CONCAT(concat(w.name,': ',ROUND(awd.distance),' km, ',ROUND(awd.duration),' mins') SEPARATOR '<br>')
		from warehouse w 
		left JOIN auction_warehouse_distance awd ON w.warehouse_id=awd.warehouse_id 
		where (w.country_code='$country_code' or '$country_code'='') and (w.warehouse_id = $warehouse_id or $warehouse_id=0)
		and awd.auction_number=auction.auction_number and awd.txnid=auction.txnid
		) warehouses
			";
        }

        global $seller_filter_str;
        global $warehouses;
        $warehouses_array = Warehouse::listArray($db, $dbr);
        global $loggedUser;
        global $smarty;
        require_once 'lib/EmailLog.php';
        require_once 'lib/Config.php';
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return $error;
        }
        if (strlen($open_amount)) {
            $where .= " and invoice.open_amount" . ($open_amount ? '>' : '<=') . "0";
        }
        if ($shipping_mode == -1) {
            #$where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=0 ";
			$join1 .= "\n join orders on orders.auction_number=IFNULL(so.auction_number, auction.auction_number) and orders.txnid=IFNULL(so.txnid, auction.txnid)" . ($find_shipped == 0 ? " and orders.sent=0" : "");
			$join_so = "and so.sent=0";
        } elseif ($shipping_mode == -2) {
            #$where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=0 ";
			$join1 .= "\n join orders on orders.auction_number=IFNULL(so.auction_number, auction.auction_number) and orders.txnid=IFNULL(so.txnid, auction.txnid)" . ($find_shipped == 0 ? " and orders.sent=0" : "");
			$join_so = "and so.sent=0";
        } elseif ($shipping_mode == -3) {
            $where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=0
						and auction.delivery_date_customer<=NOW()
						and auction.delivery_date_customer<>'0000-00-00' ";
        } elseif ($shipping_mode == 1) {
            $where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=1 ";
        } elseif ($shipping_mode == -4) {
            $where .= " and auction.payment_method=2 and invoice.open_amount>0 ";
        } elseif ($shipping_mode == -5) {
            $where .= " and auction.payment_method=2 and invoice.open_amount<=0 ";
        }
		if (strlen($date_from))
            $where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid)>='$date_from' ";
        if (strlen($date_to))
            $where .= " and date(fget_delivery_date_real(auction.auction_number, auction.txnid))<='$date_to' ";
        if (!empty($route_date_from)){
            $where .= " and IFNULL(route.start_date,mroute.start_date)>='{$route_date_from}' ";
        }
        if (!empty($route_date_to)){
            $where .= " and IFNULL(route.start_date,mroute.start_date)<='{$route_date_to}' ";
        }
        if (!empty($routes_responsible_uname)){
            $where .= " and route.responsible_uname='{$routes_responsible_uname}' ";
        }

        if ($route_id == -2) {
            if ($ina_route_id) {
                if ($route_unassigned) {
                    $where .= empty($main_route_id) ?
                        " and IFNULL(IFNULL(mroute.id, route.id),0) in (0, $ina_route_id) " :
                        " and IFNULL(route.id,0) in (0, $ina_route_id) ";
                          
                } else {
                    $where .= empty($main_route_id) ?
                        " and IFNULL(mroute.id, route.id) in ($ina_route_id) " :
                        " and route.id in ($ina_route_id) ";
                }
            } else {
                if ($route_unassigned) {
                    $where .= empty($main_route_id) ?
                        " and IFNULL(IFNULL(mroute.id, route.id),0) = 0 " :
                        " and IFNULL(route.id,0) = 0 ";
                } else {
                    $where .= " and 0 ";
                }
            }
        } elseif ($route_id == -1) {
            if ($route_unassigned) {
                $where .= " ";
            } else {
                $where .= empty($main_route_id) ?
                    " and IFNULL(IFNULL(mroute.id, route.id),0) " :
                    " and IFNULL(route.id,0) ";
            }
        } elseif ($route_id > 0) {
            if ($route_unassigned) {
                if (strlen($ina_route_id)) $where .= empty($main_route_id) ? 
                    "  and (IFNULL(mroute.id, route.id) in ($route_id, $ina_route_id) or IFNULL(IFNULL(mroute.id, route.id),0) = 0) " :
                    "  and route.id in ($route_id, $ina_route_id) or IFNULL(route.id,0) = 0) ";
                $where .= empty($main_route_id) ? 
                    " and (IFNULL(mroute.id, route.id) in ($route_id) or IFNULL(IFNULL(mroute.id, route.id),0) = 0)" :
                    " and (route.id in ($route_id) or IFNULL(route.id,0) = 0)";
            } else {
                    if (strlen($ina_route_id)) $where .= empty($main_route_id) ?
                        " and IFNULL(mroute.id, route.id) in ($route_id, $ina_route_id) " :
                        " and route.id in ($route_id, $ina_route_id) ";
                    else $where .= empty($main_route_id) ?
                        " and IFNULL(mroute.id, route.id) in ($route_id) " :
                        " and route.id in ($route_id) ";
            }
        }
        if (strlen($gmap_confirm)) {
            if ($route_id > 0 || $route_id == -2)
                $where .= " and (auction.route_id = $route_id
					or " . ($gmap_confirm ? '' : 'NOT') . " shipping_order_datetime_confirmed) ";
        }
        if (strlen($gmap_bonus)) {
            if ($route_id > 0 || $route_id == -2)
                $where .= " and (IFNULL(mroute.id, route.id) = $route_id or " . ($gmap_bonus ? '' : 'NOT') . "
					exists (select null from orders 
					left join shop_bonus sb on sb.article_id=orders.article_id and orders.manual=2
					where orders.manual=2
					and orders.auction_number=auction.auction_number and orders.txnid=auction.txnid
					and sb.show_in_table
						/*article_id in (select bonus_id from route_delivery_type_bonus where delivery_type_id=0)*/
						)) ";
        }
        if (strlen($gmap_task)) {
            if ($route_id > 0 || $route_id == -2)
                $where .= " and (IFNULL(mroute.id, route.id) = $route_id or " . ($gmap_task ? '' : 'NOT') . "
					exists (select null from orders where manual 
					and auction_number=auction.auction_number and txnid=auction.txnid
					and article_id = '')) ";
        }
        $where1 = '';
        if (strlen($gmap_date)) {
            if ($route_id > 0 || $route_id == -2)
                $where1 .= " and (IFNULL(mroute.id, route.id) = $route_id or days_due" . ($gmap_date ? '<' : '>=') . " 0 ) ";
        }
        if (strlen($timestamp)) {
            if ($route_id > 0 || $route_id == -2)
                $where1 .= " and " . ($timestamp ? '' : 'not') . " exists (select null from printer_log
					where auction_number=auction.auction_number and txnid=auction.txnid
						and action='Print packing PDF'
						" . (strlen($timestamp_username) ? " and username='$timestamp_username'" : '') . "
						) ";
        }
        if (strlen($exported)) {
            if ($route_id > 0 || $route_id == -2) {
                $where1 .= " and " . ($exported ? '' : 'not') . " exists (select null from auction_sh_comment
					where auction_number=auction.auction_number and txnid=auction.txnid
						" . (strlen($pars['exported_mindate']) ? "and date(create_date)>='" . mysql_escape_string($pars['exported_mindate']) . "'" : '') . "
						" . (strlen($pars['exported_maxdate']) ? "and date(create_date)<='" . mysql_escape_string($pars['exported_maxdate']) . "'" : '') . "
						" . (strlen($exported_format) ? "and `comment`='exported to " . $exported_format . "'"
                        : " and `comment` like 'exported to %'") . ") ";
            }
        }
        if (count($ids)) {
            $where .= " and auction.id in (" . implode(',', $ids) . ")";
        }
        if (strlen($article_id)) {
            $where .= " and exists (select null from orders where article_id='$article_id' and manual=0
				and sent=0 and auction_number=auction.auction_number and txnid=auction.txnid
				union select null from orders where article_id='$article_id' and manual=0 
				and sent=0 and auction_number=sau.auction_number and txnid=sau.txnid) 
				 ";
        }
        
        $country_shipping = CountryCodeToCountry($country_shipping);
        if (strlen($country_shipping))
            $where .= " and au_sh_country.value='$country_shipping' ";
        if ($where == " and auction.shipping_username is not null " && !strlen($where1)) {
            echo " too slow if we dont set any other filter 2";
            return; // too slow if we dont set any other filter
        }
        
        if ($released == 'YES') {
            $where .= " and rl_orders.picking_order_id != 0 ";
            if ($loading_ramp) {
                $where .= " and picking_order.ware_la_id = $loading_ramp ";
            }
        }
        else if ($released == 'NO') {
            $where .= " and rl_orders.picking_order_id = 0 ";
        }

        if ($repack) {
            if($repack==1)
            {
                $join_orders_repack_condition = '
                LEFT JOIN orders orders_all ON
                    auction.auction_number = orders_all.auction_number
                    AND auction.txnid = orders_all.txnid
                    AND orders_all.repack = 1
                LEFT JOIN orders orders_sub ON
                    sau.auction_number = orders_sub.auction_number
                    AND sau.txnid = orders_sub.txnid
                    AND orders_sub.repack = 1
                ';
                $where_orders_repack_condition = '
                AND (orders_all.repack = 1 OR orders_sub.repack = 1)';
            }
            else
            {
                if($repack==2)
                {
                    $join_orders_repack_condition = '
                        LEFT JOIN orders orders_all ON
                            auction.auction_number = orders_all.auction_number
                            AND auction.txnid = orders_all.txnid
                            AND orders_all.repack = 0
                        LEFT JOIN orders orders_sub ON
                            sau.auction_number = orders_sub.auction_number
                            AND sau.txnid = orders_sub.txnid
                            AND orders_sub.repack = 0
                        ';
                    $where_orders_repack_condition = '
                        AND (orders_all.repack = 0 AND orders_sub.repack = 0)';

                }
                else
                {
                    $join_orders_repack_condition = '
                            LEFT JOIN orders orders_all ON
                                auction.auction_number = orders_all.auction_number
                                AND auction.txnid = orders_all.txnid
                            LEFT JOIN orders orders_sub ON
                                sau.auction_number = orders_sub.auction_number
                                AND sau.txnid = orders_sub.txnid
                            ';
                    $where_orders_repack_condition = '';


                }

            }
        }
        else
        {
            $join_orders_repack_condition = '';
            $where_orders_repack_condition = '';
        }

        // hwElBLhY/1416-justyna-filter-for-packed-unpacked-auftrags
        if($packed_status!=3){
            $join1 .= "\n LEFT JOIN tn_orders ON tn_orders.order_id = rl_orders.id";
            if($packed_status==1){
                $where_packed_status_condition = ' AND tn_orders.id IS NULL AND rl_orders.sent=0';
            }else
            if($packed_status==2){
                $where_packed_status_condition = ' AND tn_orders.id IS NOT NULL AND rl_orders.sent=0';
            }
        }

        $q = "SELECT auction.*
		, (select group_concat(concat('<a href=\"doc.php?number=',auction.auction_number,'&doc_id=',ad.doc_id,'\">',SUBSTRING(ad.name,1,5),'...','</a>') order by `date` separator '<br>')
			from auction_doc ad where ad.auction_number=auction.auction_number and ad.txnid=auction.txnid) docs
#		, spc.shipping_cost, spc.real_shipping_cost
		, IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					where auction_calcs.auction_number = auction.auction_number AND auction_calcs.txnid = auction.txnid
					),0)
			+ IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					left join auction au1 on auction_calcs.auction_number = au1.auction_number 
						and auction_calcs.txnid=au1.txnid
					where au1.main_auction_number = auction.auction_number AND au1.main_txnid = auction.txnid
					),0) real_shipping_cost
		from (select
			rma_au.auction_number auction_number_basic, rma_au.txnid txnid_basic,
			rma_ru.auction_number auction_number_route, 
			IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid))
					 as offer_name,
				IF(auction.delivery_date_customer='0000-00-00' or fget_ASent(auction.auction_number, auction.txnid)=1, auction.delivery_date_customer, 
					IF(TO_DAYS(NOW())>=TO_DAYS(auction.delivery_date_customer), 
						CONCAT('<span id=\"shipping-date-',auction.auction_number,'-',auction.txnid,'\" style=\"color:#008800;font-weight:bold;\">', auction.delivery_date_customer, '</span>'),
						CONCAT('<span id=\"shipping-date-',auction.auction_number,'-',auction.txnid,'\" style=\"color:#FF0000;font-weight:bold;\">', auction.delivery_date_customer, '</span>')
				)) delivery_date_customer_colored,
				sum(p.amount) paid_amount, 
				IFNULL(max( p.payment_date ), 'FREE') paid_date
                
				, (SELECT CONCAT('Released on ', DATE_FORMAT(`tl`.`Updated`, '%Y-%m-%d %H:%i'), 
                        ' by ', IFNULL(`u`.`name`, `tl`.`username`), 
                        ' on ', `la`.`la_name`)
                    FROM `total_log` AS `tl`
                    LEFT JOIN users u ON u.system_username=tl.username
                    LEFT JOIN `orders` AS `o` ON `o`.`id` = `tl`.`TableID`
                    LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `tl`.`New_value`
                    LEFT JOIN `ware_la` AS `la` ON `la`.`id` = `po`.`ware_la_id`
                    WHERE `tl`.`Table_name` = 'orders' and `tl`.`Field_name` = 'picking_order_id' AND `tl`.`New_value` > 0 AND 
                        `o`.`id` = `rl_orders`.`id` AND `o`.`picking_order_id` != 0
                    ORDER BY `tl`.`id` DESC LIMIT 1) released_log
                
				, (select CONCAT(log_date,'<br>by ',username) from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					order by log_date limit 1
					/*and printer_log.username='" . $loggedUser->data->username . "'*/) first_print_date
				, (select min(log_date) from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					and printer_log.username='" . $loggedUser->data->username . "') first_print_date_user
				, (select GROUP_CONCAT(CONCAT(log_date,' by ',username) order by log_date separator '<br>') from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					) print_date
				, (select GROUP_CONCAT(CONCAT(log_date) order by log_date separator '<br>') from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					and printer_log.username='" . $loggedUser->data->username . "') print_date_user
				,IF(auction.priority
					+IFNULL((SELECT max(priority) FROM shop_bonus b
						join orders o on b.article_id=o.article_id and o.manual=2
						where o.auction_number=auction.auction_number and o.txnid=auction.txnid),0)
					, 'green', IF(fget_ASent(auction.auction_number, auction.txnid)=0, '', 'gray')) as colour
					,IFNULL(u1.name, auction.shipping_username) shipping_person
					,IFNULL(u2.name, auction.shipping_resp_username) responsible_username
					,IF(max( p.payment_date ) <= DATE_SUB(NOW(), INTERVAL " . Config::get($db, $dbr, 'ship_ready_to_ship') . " DAY),'red','') too_old
			, auction.auction_number
			, auction.txnid
			, (select count(*) from printer_log where printer_log.auction_number=auction.auction_number
				and printer_log.txnid=auction.txnid /*and printer_log.username='" . $loggedUser->data->username . "'*/) as printed
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.shipping_resp_username
			, auction.siteid
			, auction.id
			, auction.route_id
#			, IF(route.deleted, null, auction.route_label) route_label
			, auction.route_label
			, route.name route_name
			, route.deleted route_deleted
			, auction.route_delivery_type
			, auction.route_delivery_type route_delivery_type_id
			, route_delivery_type.name route_delivery_type_name
			, route_delivery_type.code route_delivery_type_code
			, IFNULL(route_delivery_type.minutes, auction.route_delivery_other_minutes) route_delivery_type_minutes
			, concat(auction.route_id, LPAD(100000*ROUND(auction.route_label,1),11,'0')) route_id_label
			, auction.route_distance
			, auction.route_duration
			, car_speed.speed car_speed
			, car_speed.factor car_factor
			, route.driver_amt
			, concat(auction.route_distance, ' km, ', auction.route_duration, ' mins'
				, IFNULL(CONCAT('<br><br>Back to warehouse ', IFNULL(CONCAT(wr_end.address1, ' ', wr_end.address2, ' ', wr_end.address3), route.end_address)
					,': ',route.last_route_distance, ' km, <b>', route.last_route_duration, ' mins</b>'),'')
				) route_distance_duration
			, CONCAT(auction.route_duration_distance_text
				, IFNULL(CONCAT('<br><br>Back to warehouse ', IFNULL(CONCAT(wr_end.address1, ' ', wr_end.address2, ' ', wr_end.address3), route.end_address)
					,': ',route.last_route_distance, ' km, <b>', route.last_route_duration, ' mins</b>'),'')
				) route_duration_distance_text
			, route.last_route_distance
			, route.start_date
			, datediff(route.start_date, now()) warning
			, route.start_time
			, route.start_warehouse_id
			, route.end_warehouse_id
			, route.start_address
			, route.end_address
			, route.max_delivery_time
			, cars.tracking_account
			, cars.tracking_password
			, cars.tracking_imei
			, cars.name car_name
			, cars.weight car_weight
			, cars.height_int car_height_int
			, cars.width_int car_width_int
			, cars.length_int car_length_int
			, (cars.height_int*cars.width_int*cars.length_int)/1000000 car_volume
			, trailer.weight trailer_weight
			, trailer.height_int trailer_height_int
			, trailer.width_int trailer_width_int
			, trailer.length_int trailer_length_int
			, (trailer.height_int*trailer.width_int*trailer.length_int)/1000000 trailer_volume
			, auction.shipping_order_date
			, auction.shipping_order_time
			, IF(auction.shipping_order_date='0000-00-00','', concat(auction.shipping_order_date, ' ', IFNULL(auction.shipping_order_time,'')
				, IF(auction.shipping_order_datetime_confirmed, '', '<br/><b>Not confirmed</b>'))) shipping_order_datetime
			, concat(auction.shipping_order_date, ' ', IFNULL(auction.shipping_order_time,'')) shipping_order_datetime1
			, auction.delivery_comment
			, auction.shipping_order_datetime_confirmed
			, auction.route_proposition_time_text
			, auction.route_ignore
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, auction.priority_comment
			, fget_ACustomer(auction.auction_number, auction.txnid) customer
			, TO_DAYS(NOW())-TO_DAYS(IF(auction.delivery_date_customer='0000-00-00' || auction.delivery_date_customer<NOW(), (select tl.updated from total_log tl 
				where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id order by tl.updated limit 1)
				, auction.delivery_date_customer)) days_due
			, TO_DAYS(NOW())-TO_DAYS(IF(auction.delivery_date_customer='0000-00-00' || auction.delivery_date_customer<NOW(), (select tl.updated from total_log tl 
				where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id order by tl.updated desc limit 1)
				, auction.delivery_date_customer)) days_assigned
			, TO_DAYS(auction.delivery_date_customer) - TO_DAYS(NOW()) days_due1
			, CONCAT(IF(auction.rma_id, CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',auction.rma_id,'&number=',auction.auction_number,'&txnid=',auction.txnid,'\'>',auction.rma_id,'</a>'), ''),'<br>'
				, IFNULL((select group_concat(
					CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',rma_id,'&number=',rma.auction_number,'&txnid=',rma.txnid,'\'>',rma_id,'</a>')
					 SEPARATOR '<br>') 
				from rma where rma.auction_number=auction.auction_number and rma.txnid=auction.txnid)
				,'')) rma_id
			, (select concat(create_date, ' by ', IFNULL(users.name, auction_sh_comment.username)) from auction_sh_comment
				left join users on users.username=auction_sh_comment.username
				where auction_sh_comment.comment='Exported to dhl'
				and auction_sh_comment.auction_number = auction.auction_number
				and auction_sh_comment.txnid = auction.txnid
				limit 1) as exported_to_dhl
			, CONCAT(IF(auction.payment_method=2,'COD: ','')
				, IF(invoice.open_amount>0,concat('<b>',invoice.open_amount,'</b>'),invoice.open_amount)) open_amount_tag
			, IF(au_same_address.value, 
				concat(
				IFNULL(au_invoice_company.value,''),'<br>',
				IFNULL(au_firstname_invoice.value,''), ' ', IFNULL(au_name_invoice.value,''),'<br>',
				IFNULL(au_invoice_street.value,''),' ',IFNULL(au_invoice_house.value,''),'<br>',
				IFNULL(au_zip_invoice.value,''), ' ', IFNULL(au_city_invoice.value,''),'<br>',
				IFNULL(au_invoice_country.value,''),'<br>',
				IFNULL(au_invoice_tel.value,''),'<br>',
				IFNULL(au_invoice_cel.value,''))
				, concat(
				IFNULL(au_sh_company.value,''),'<br>',
				IFNULL(au_firstname_shipping.value,''), ' ', IFNULL(au_name_shipping.value,''),'<br>',
				IFNULL(au_sh_street.value,''),' ',IFNULL(au_sh_house.value,''),'<br>',
				IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,''),'<br>',
				IFNULL(au_sh_country.value,''),'<br>',
				IFNULL(au_sh_tel.value,''),'<br>',
				IFNULL(au_sh_cel.value,''))
			) all_shipping
			, IF(au_same_address.value, 
				concat(
				IFNULL(au_invoice_street.value,''),' ',
				IFNULL(au_invoice_house.value,''),' ',
				IFNULL(au_zip_invoice.value,''), ' ', IFNULL(au_city_invoice.value,''),' ',
				IFNULL(au_invoice_country.value,''))
				, concat(
				IFNULL(au_sh_street.value,''),' ',
				IFNULL(au_sh_house.value,''),' ',
				IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,''),' ',
				IFNULL(au_sh_country.value,''))
			) all_shipping_address_only
			, concat(
				IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,'')
			) ZIPCity
			, wr_start.country_code wr_start_country_code
			, au_sh_country.value shipping_country
			, CONCAT(IF(auction.rma_id, CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',auction.rma_id,'&number=',auction.auction_number,'&txnid=',auction.txnid,'\'>',auction.rma_id,'</a>'), ''),'<br>'
				, IFNULL((select group_concat(
					CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',rma_id,'&number=',rma.auction_number,'&txnid=',rma.txnid,'\'>',rma_id,'</a>')
					 SEPARATOR '<br>') 
				from rma where rma.auction_number=auction.auction_number and rma.txnid=auction.txnid)
				,'')) rma
			, auction.task_type
			, auction.rma_id pure_rma_id
			, auction.exchange_auction_id
			, auction.lang
			, auction.rma_weight_use
			, auction.exchange_weight_use
			, route.car_id
			, invoice.open_amount
			, au_sh_tel.value sh_tel
			, (select max(priority) FROM shop_bonus 
				join orders on orders.article_id=shop_bonus.article_id and orders.manual=2
				where orders.auction_number=auction.auction_number and orders.txnid=auction.txnid
				) priority_all
			$join
		FROM auction
		left join auction sau on sau.main_auction_number=auction.auction_number and sau.main_txnid=auction.txnid and sau.deleted=0
		left join orders so on sau.auction_number=so.auction_number and sau.txnid=so.txnid $join_so
		$join_orders_repack_condition
		left join auction_par_varchar au_same_address on auction.auction_number=au_same_address.auction_number 
			and auction.txnid=au_same_address.txnid and au_same_address.key='same_address'
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number 
			and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
		left join auction_par_varchar au_sh_country on auction.auction_number=au_sh_country.auction_number
			and auction.txnid=au_sh_country.txnid and au_sh_country.key='country_shipping'
		left join auction_par_varchar au_sh_street on auction.auction_number=au_sh_street.auction_number
			and auction.txnid=au_sh_street.txnid and au_sh_street.key='street_shipping'
		left join auction_par_varchar au_sh_house on auction.auction_number=au_sh_house.auction_number
			and auction.txnid=au_sh_house.txnid and au_sh_house.key='house_shipping'
		left join auction_par_varchar au_sh_tel on auction.auction_number=au_sh_tel.auction_number
			and auction.txnid=au_sh_tel.txnid and au_sh_tel.key='tel_shipping'
		left join auction_par_varchar au_sh_cel on auction.auction_number=au_sh_cel.auction_number
			and auction.txnid=au_sh_cel.txnid and au_sh_cel.key='cel_shipping'
		left join auction_par_varchar au_sh_company on auction.auction_number=au_sh_company.auction_number
			and auction.txnid=au_sh_company.txnid and au_sh_company.key='company_shipping'

		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number 
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		left join auction_par_varchar au_city_invoice on auction.auction_number=au_city_invoice.auction_number 
			and auction.txnid=au_city_invoice.txnid and au_city_invoice.key='city_invoice'
		left join auction_par_varchar au_zip_invoice on auction.auction_number=au_zip_invoice.auction_number 
			and auction.txnid=au_zip_invoice.txnid and au_zip_invoice.key='zip_invoice'
		left join auction_par_varchar au_invoice_country on auction.auction_number=au_invoice_country.auction_number
			and auction.txnid=au_invoice_country.txnid and au_invoice_country.key='country_invoice'
		left join auction_par_varchar au_invoice_street on auction.auction_number=au_invoice_street.auction_number
			and auction.txnid=au_invoice_street.txnid and au_invoice_street.key='street_invoice'
		left join auction_par_varchar au_invoice_house on auction.auction_number=au_invoice_house.auction_number
			and auction.txnid=au_invoice_house.txnid and au_invoice_house.key='house_invoice'
		left join auction_par_varchar au_invoice_tel on auction.auction_number=au_invoice_tel.auction_number
			and auction.txnid=au_invoice_tel.txnid and au_invoice_tel.key='tel_invoice'
		left join auction_par_varchar au_invoice_cel on auction.auction_number=au_invoice_cel.auction_number
			and auction.txnid=au_invoice_cel.txnid and au_invoice_cel.key='cel_invoice'
		left join auction_par_varchar au_invoice_company on auction.auction_number=au_invoice_company.auction_number
			and auction.txnid=au_invoice_company.txnid and au_invoice_company.key='company_invoice'

            LEFT JOIN offer ON offer.offer_id = auction.offer_id
            LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
            LEFT JOIN users u1 ON auction.shipping_username = u1.username
            LEFT JOIN users u2 ON auction.shipping_resp_username = u2.username
			LEFT JOIN payment p ON p.auction_number = auction.auction_number AND p.txnid = auction.txnid
			LEFT JOIN route ON route.id = auction.route_id 
			LEFT JOIN route mroute ON route.main_route_id = mroute.id 
			LEFT JOIN warehouse wr_start ON route.start_warehouse_id = wr_start.warehouse_id
			LEFT JOIN warehouse wr_end ON route.end_warehouse_id = wr_end.warehouse_id
			left join route_delivery_type on route_delivery_type.id=auction.route_delivery_type
			LEFT JOIN cars ON route.car_id = cars.id
			LEFT JOIN car_speed ON car_speed.id = cars.speed_id
			LEFT JOIN cars AS `trailer` ON route.trailer_car_id = trailer.id
			left join rma on rma.rma_id=auction.rma_id
			left join auction rma_au on rma_au.rma_id=rma.rma_id and rma_au.txnid<>4
			left join auction rma_ru on rma_ru.rma_id=rma.rma_id and rma_ru.txnid=4
            left join orders AS rl_orders on rl_orders.auction_number = IFNULL(sau.auction_number, auction.auction_number) 
                and rl_orders.txnid=IFNULL(sau.txnid, auction.txnid)
            " . ($find_shipped == 0 ? " and rl_orders.sent=0" : " ") . "
                and rl_orders.hidden=0 
                AND rl_orders.manual = 0
            left join picking_order on rl_orders.picking_order_id = picking_order.id
			$join1
            WHERE 1 $where
            AND auction.deleted = 0 
			and auction.main_auction_number=0
			$where_orders_repack_condition
			$where_packed_status_condition
			GROUP BY auction.auction_number, auction.txnid
			) auction
			where 1 
			$where1
			ORDER BY concat(auction.auction_number, auction.txnid)
			";
        
//      file_put_contents("lastAJAX2.txt", $q);
        if ($debug) {
            echo "<pre>" . htmlspecialchars($q) . "</pre>";
            echo '<br>'; //if (!strlen($username)) die();
        }
//		print_r($warehouses);
        global $dbr_spec;
        $r = $dbr_spec->getAll($q);
        
        if ($debug) {
            echo 'shipping_username Q1: ' . round(getmicrotime() - $time, 4) . '<br>';
            $time = getmicrotime();
        }
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        $configs = Config::getAll($db, $dbr);
        $routes_array = get_routes_array($dbr);
        $route_delivery_type_array = $dbr->getAll("SELECT *
			FROM route_delivery_type
			ORDER BY name");
        $warehouse_options = '';
        foreach ($warehouses as $warehouse_id => $warehouse_name) {
            if (strlen($warehouses_array[$warehouse_id]))
                $warehouse_options .= '<option value="' . $warehouse_id . '">' . $warehouses_array[$warehouse_id] . '</option>';
        }

        if ($debug) {
            echo 'shipping_username Q2: ' . round(getmicrotime() - $time, 4) . '<br>';
            $time = getmicrotime();
        }
        foreach ($r as $key => $auction) {
            
            $r[$key]->shipping_order_comment = $dbr->getOne("SELECT GROUP_CONCAT(`tcomments`.`comments` SEPARATOR '<br /><br />') FROM (
                SELECT GROUP_CONCAT(`comment` SEPARATOR '<br /><br />') AS `comments`
                    FROM `auction_sh_comment`
                    WHERE `auction_number`=? AND `txnid`=?

                UNION ALL	

                SELECT GROUP_CONCAT( CONCAT('Warehouse: ', `comment`) SEPARATOR '<br /><br />' ) AS `comments`
                FROM `auction_comment`
                WHERE `auction_number`=? AND `auction_comment`.`txnid`=? AND `src` = 'warehouse'
                ) `tcomments` LIMIT 1", 
                    null, [$auction->auction_number, $auction->txnid, $auction->auction_number, $auction->txnid]);
            
            if ($auction->txnid == 4) {
                $tplname = 'shipping_order_datetime_pickup_approvement';
            } else {
                $tplname = 'shipping_order_datetime_approvement';
            }
            if ($auction->days_due <= $configs['shipping_order_period3_days']) $r[$key]->days_due_color = $configs['shipping_order_period3_color'];
            if ($auction->days_due <= $configs['shipping_order_period2_days']) $r[$key]->days_due_color = $configs['shipping_order_period2_color'];
            if ($auction->days_due <= $configs['shipping_order_period1_days']) $r[$key]->days_due_color = $configs['shipping_order_period1_color'];
            $r[$key]->days_due = "<font color='" . $r[$key]->days_due_color . "'>" . $r[$key]->days_due . "</font>";
            if ($r[$key]->txnid == 4) {
                if ($wares_res) {
                    unset($r[$key]);
                    continue;
                }
                
                $r[$key]->articles = '';
                $r[$key]->weight = '';
                $r[$key]->volume = '';
                if ($r[$key]->task_type > 0) {
                    $r[$key]->articles .= 'Article to pick up:<br>';
                    $articles = $dbr->getAll("SELECT rma_spec.article_id, max(translation.value) name
						, (a.weight_per_single_unit) weight
						, (a.volume_per_single_unit) volume
						FROM rma_spec
						LEFT JOIN translation ON table_name='article' AND field_name='name' AND translation.id=rma_spec.article_id
							AND language='" . $r[$key]->lang . "'
						JOIN article a ON a.article_id=rma_spec.article_id AND a.admin_id=0
						WHERE rma_id=" . $r[$key]->pure_rma_id . "
						AND rma_spec.warehouse_id
						AND NOT alt_order
						GROUP BY rma_spec.rma_spec_id");
                    if (count($articles)) {
                        $r[$key]->articles .= '<input type="checkbox" value="1" id="rma_weight_use_checkbox[' . $r[$key]->id . ']"' . ($r[$key]->rma_weight_use ? ' checked' : '') . '
							onClick="change_auction_db(\'rma_weight_use\', ' . $r[$key]->id . ', this.checked, \'rma_weight_use_checkbox[' . $r[$key]->id . ']\', \'checked\')"/> Use weight<br/>';
                    }
                    foreach ($articles as $article) {
                        $r[$key]->articles .= '<a  onmouseover=\"get_info(\'' . $article->article_id . '\')\" onmouseout=\"UnTip()\" target="_blank" href="article.php?original_article_id=' . $article->article_id . '">' . $article->article_id . ': ' . $article->name . '</a><br>';
                        if ($r[$key]->rma_weight_use) {
                            $r[$key]->weight += $article->weight;
                            $r[$key]->volume += $article->volume;
                        }
                    }
                }
                if ($r[$key]->task_type == 2 && $r[$key]->exchange_auction_id) {
                    $r[$key]->articles .= '<br>Article to deliver:<br>';
                    $articles = $dbr->getAll("SELECT orders.quantity, orders.article_id, max(translation.value) name
						, (a.weight_per_single_unit*orders.quantity) weight
						, (a.volume_per_single_unit*orders.quantity) volume
						FROM orders
						LEFT JOIN translation ON table_name='article' AND field_name='name' AND translation.id=orders.article_id
							AND language='" . $r[$key]->lang . "'
						JOIN auction ON orders.auction_number=auction.auction_number AND orders.txnid=auction.txnid
						JOIN article a ON a.article_id=orders.article_id AND a.admin_id=orders.manual
						WHERE auction.id=" . $r[$key]->exchange_auction_id . " AND a.admin_id=0
						GROUP BY orders.id");
                    
                    if (count($articles)) {
                        $r[$key]->articles .= '<input type="checkbox" value="1" id="exchange_weight_use_checkbox[' . $r[$key]->id . ']"' . ($r[$key]->exchange_weight_use ? ' checked' : '') . '
							onClick="change_auction_db(\'exchange_weight_use\', ' . $r[$key]->id . ', this.checked, \'exchange_weight_use_checkbox[' . $r[$key]->id . ']\', \'checked\')"/> Use weight<br/>';
                    }
                    foreach ($articles as $article) {
                        $r[$key]->articles .= $article->quantity . ' x <a class="splink1" target="_blank" href="article.php?original_article_id=' . $article->article_id . '">' . $article->article_id . ': ' . $article->name . '</a><br>';
                        if ($r[$key]->exchange_weight_use) {
                            $r[$key]->weight += $article->weight;
                            $r[$key]->volume += $article->volume;
                        }
                    }
                }
            } else {
                $q = "SELECT fget_Article_stock(orders.article_id, route.start_warehouse_id) stock
						, orders.sent, IFNULL(route.start_warehouse_id,0) start_warehouse_id
					FROM orders
					JOIN auction au ON au.auction_number=orders.auction_number AND au.txnid=orders.txnid
					JOIN auction mau ON mau.auction_number=au.main_auction_number AND mau.txnid=au.main_txnid
					JOIN route ON route.id = IFNULL(au.route_id,mau.route_id)
					JOIN article a ON a.article_id=orders.article_id AND a.admin_id=orders.manual
					WHERE ((orders.auction_number=" . $r[$key]->auction_number . " AND orders.txnid=" . $r[$key]->txnid . ")
					OR (au.main_auction_number=" . $r[$key]->auction_number . " AND au.main_txnid=" . $r[$key]->txnid . "))
					AND orders.manual=0
					AND NOT a.hide_in_route";
                //			echo "$q<br>";
                $stocks = $db->getAll($q);
                $font = count($stocks) ? 'green' : 'black';
                #			echo $r[$key]->auction_number.'<br>';
                foreach ($stocks as $stock) {
                    if ($stock->stock <= 0 && !$stock->sent) $font = 'black';
                    #				echo ': $stock->stock='.$stock->stock.' $stock->sent='.$stock->sent.' font='.$font.'<br>';
                }
                if ($font == 'green') $r[$key]->offer_name .= '<br><font color="green">all in stock</font>';
                $q = "SELECT sum(a.weight_per_single_unit*orders.quantity) weight
						, sum(a.volume_per_single_unit*orders.quantity) volume
					FROM orders
					JOIN auction au ON au.auction_number=orders.auction_number AND au.txnid=orders.txnid
					JOIN article a ON a.article_id=orders.article_id AND a.admin_id=orders.manual
					LEFT JOIN translation t ON a.article_id=t.id AND t.table_name='article' AND t.field_name='name' AND t.language='german'
					WHERE ((orders.auction_number=" . $r[$key]->auction_number . " AND orders.txnid=" . $r[$key]->txnid . ")
					OR (au.main_auction_number=" . $r[$key]->auction_number . " AND au.main_txnid=" . $r[$key]->txnid . "))
					";
                //print $q."\n\n\n\nALEXJJ<br><br><br>\n\n\n";
                $auall = $dbr->getRow($q);
                $r[$key]->weight_all = round($auall->weight, 2);
                $r[$key]->volume_all = $auall->volume;
                $q = "select sum(a.weight_per_single_unit*orders.quantity) weight
						, sum(a.volume_per_single_unit*orders.quantity) volume
						, group_concat(IF(a.admin_id and a.article_id='',CONCAT('<b>',a.name,'</b>'),'')
							separator '<br>') task_name
					from orders 
					join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
					join article a on a.article_id=orders.article_id and a.admin_id=orders.manual
					left join translation t on a.article_id=t.id and t.table_name='article' and t.field_name='name' and t.language='german'
					where ((orders.auction_number=" . $r[$key]->auction_number . " and orders.txnid=" . $r[$key]->txnid . ")
					OR (au.main_auction_number=" . $r[$key]->auction_number . " and au.main_txnid=" . $r[$key]->txnid . "))
				    " . ($find_shipped == 0 ? " and orders.sent=0" : "");
                $au = $dbr->getRow($q);
                $r[$key]->weight = round($au->weight, 2);
                $r[$key]->volume = $au->volume;
                $q = "select orders.quantity, a.admin_id, a.article_id, orders.id, orders.reserve_warehouse_id,
				CONCAT(
					IF(orders.manual=0,CONCAT(orders.quantity, ' x <a target=\"_blank\" class=\"splink2\" href=\"article.php?original_article_id=',orders.article_id,'\"'
					,IF(tno.id is not null, 'style=\"color:#FF00FF;\"', ''),'>'),'')
									,IF(a.admin_id and a.article_id='', CONCAT('Driver task: <b>',a.description,'</b>'), 
									concat('<b>',IFNULL(sb.id,a.article_id), '</b>: '
									, IF(IFNULL(orders.custom_title,'')='' or orders.manual=2,IFNULL(t1.value,IFNULL(t.value,a.name)),orders.custom_title))),IF(orders.manual=0,'</a>','')
					) a_href,
                    
                    IF (`picking_order`.`delivered`
                        , (SELECT CONCAT('Delivered on ', DATE_FORMAT(`tl`.`Updated`, '%Y-%m-%d %H:%i'), 
                                ' by ', IFNULL(`u`.`name`, `tl`.`username`), 
                                ' on ', `la`.`la_name`, 
                                IF (o.sent = '1', '', CONCAT(' <button class=\"unrelease-button\" data-auction=\"{$r[$key]->auction_number}\" data-txnid=\"{$r[$key]->txnid}\" data-article=\"', `o`.article_id, '\">Unrelease</button> '))
                            )
                            FROM `total_log` AS `tl`
                            LEFT JOIN users u ON u.system_username=tl.username
                            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `tl`.`TableID`
                            LEFT JOIN `orders` AS `o` ON `o`.`picking_order_id` = `po`.`id`
                            LEFT JOIN `ware_la` AS `la` ON `la`.`id` = `po`.`ware_la_id`
                            WHERE `tl`.`Table_name` = 'picking_order' and `tl`.`Field_name` = 'delivered' AND `tl`.`New_value` > 0 
                                AND `po`.`id` = IFNULL(`picking_order`.`id`, 0) 
                            ORDER BY `tl`.`id` DESC LIMIT 1) 
                        , (SELECT CONCAT('Released on ', DATE_FORMAT(`tl`.`Updated`, '%Y-%m-%d %H:%i'), 
                                ' by ', IFNULL(`u`.`name`, `tl`.`username`), 
                                ' on ', `la`.`la_name`, 
                                ' <button class=\"unrelease-button\" data-auction=\"{$r[$key]->auction_number}\" data-txnid=\"{$r[$key]->txnid}\" data-article=\"', `o`.article_id, '\">Unrelease</button> ')
                            FROM `total_log` AS `tl`
                            LEFT JOIN users u ON u.system_username=tl.username
                            LEFT JOIN `orders` AS `o` ON `o`.`id` = `tl`.`TableID`
                            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `tl`.`New_value`
                            LEFT JOIN `ware_la` AS `la` ON `la`.`id` = `po`.`ware_la_id`
                            WHERE `tl`.`Table_name` = 'orders' and `tl`.`Field_name` = 'picking_order_id' AND `tl`.`New_value` > 0 
                                AND `o`.`id` = `orders`.`id` AND `o`.`picking_order_id` != 0 
                            ORDER BY `tl`.`id` DESC LIMIT 1) 
                    ) AS released_log,

					a.barcode_type
					, IF(a.admin_id and a.article_id='', CONCAT('Driver task: <b>',a.description,'</b>'), 
									concat(orders.quantity, ' x <b>',orders.article_id, '</b>: '
									, IF(IFNULL(orders.custom_title,'')='' or orders.manual=2,IFNULL(t1.value,IFNULL(t.value,a.name)),orders.custom_title))) a_nohref
					, IF(wwo.id, CONCAT(wwa.qnt, ' x WWO#',wwo.id,CONCAT(' (',wwo.comment,')'),';'
						, IFNULL((select updated
							from total_log
							where table_name='wwo_article' and field_name='delivered' and TableID=wwa.id
							and new_value=1 order by updated desc limit 1), IFNULL(wwo.planned_arrival_date, ''))
						, IF(wwa.delivered,' <span style=\"color:green\">arrived</span>','')), '') state_wwo
					, wwo.id wwo_id
                    , IF(orders.picking_list_id, CONCAT('Picking list #',orders.picking_list_id), '') state_pl
                    , orders.picking_list_id pl_id
                    , concat(warehouse.country_code, ': ',warehouse.name) reserved_warehouse_name
                    , warehouse.warehouse_id
					from orders 
					left join tn_orders tno on tno.order_id=orders.id
					LEFT JOIN wwo_article wwa ON orders.wwo_order_id=wwa.id
					LEFT JOIN ww_order wwo ON wwa.wwo_id=wwo.id
					join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
					join article a on a.article_id=orders.article_id and a.admin_id=orders.manual
					left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
					left join translation t on a.article_id=t.id and t.table_name='article' and t.field_name='name' and t.language=IFNULL(mau.lang,au.lang)
					left join shop_bonus sb on sb.article_id=orders.article_id and orders.manual=2
					left join article sba on sba.article_id=sb.article_id and sba.admin_id=2
					left join translation t1 on sb.id=t1.id and t1.table_name='shop_bonus' and t1.field_name='title' and t1.language=IFNULL(mau.lang,au.lang)
					LEFT JOIN warehouse on warehouse.warehouse_id = orders.reserve_warehouse_id
                    LEFT JOIN `picking_order` ON `picking_order`.`id` = `orders`.`picking_order_id`

                    where ((orders.auction_number='{$r[$key]->auction_number}' and orders.txnid='{$r[$key]->txnid}')
                    OR (au.main_auction_number='{$r[$key]->auction_number}' and au.main_txnid='{$r[$key]->txnid}'))
					and ((orders.manual=0". ($find_shipped == 0 ? " and orders.sent=0" : "") . ") or (orders.manual=2 and sb.show_in_table))
					and not au.deleted
					and not IFNULL(sba.hide_in_route, a.hide_in_route)
					group by orders.id
					";

                $articles = $dbr->getAll($q);
                
                if ($debug) {
                    echo "<pre>" . htmlspecialchars($q) . "</pre>";
                    var_dump($articles);
                }
                
                $wares_res_isset = false;

                $isset_released = 0;
                $released_by_warehouses = [];
                
                $r[$key]->articles = '';
                foreach ($articles as $au) {
                    if ($wares_res && $au->warehouse_id && !in_array($au->warehouse_id,$wares_res)) {
                        continue;
                    }
                    
                    if (!$au->admin_id && $au->warehouse_id) {
                        $wares_res_isset = true;
                    }
                        
                    $warestock = '';
                    $warestock_color = '';
                    if (!$au->admin_id) {
                        $q = "select warehouse.name, warehouse.warehouse_id
                                , fget_Article_stock('{$au->article_id}', warehouse.warehouse_id) stock
                                , fget_Article_reserved('{$au->article_id}', warehouse.warehouse_id) reserved
                                from warehouse where 1 
                                    and warehouse_id = {$au->reserve_warehouse_id}";
                        $w = $db->getRow($q);
                        $warestock = "<b>" . (int)$w->stock . "</b>";
                        $warestock_color = $w->reserved ? 'orange' : '';
                    }
                    
                    if (!$au->admin_id && strlen($auction->wr_start_country_code)) {
                        $q = "select warehouse.name, warehouse.warehouse_id
							, fget_Article_stock('{$au->article_id}', warehouse.warehouse_id) stock
							, fget_Article_reserved('{$au->article_id}', warehouse.warehouse_id) reserved
							from warehouse where 1 
								and country_code='{$auction->wr_start_country_code}' 
								and not inactive
							order by trim(warehouse.name)";
                        $wares = $db->getAll($q);
                        $warehouses_table = '<table border="1">';
                        foreach ($wares as $w) {
                            if ($au->reserve_warehouse_id == $w->warehouse_id) {
//								$warestock = ($w->stock-$w->reserved)." of <b>".(int)$w->stock."</b> in ".$w->name." warehouse";
                                $warestock = "<b>" . (int)$w->stock . "</b>";
                                $warestock_color = $w->reserved ? 'orange' : '';
                            }
                            $warehouses_table .= '<tr><td>';
                            if (($w->stock - $w->reserved) >= $au->quantity)
                                $warehouses_table .= '<span style="color:green;font-size:xx-small">' . $auction->wr_start_country_code . ' ' . $w->name . ': ' . $w->stock . '</span>';
                            elseif (($w->stock - $w->reserved) > 0)
                                $warehouses_table .= '<span style="color:black;font-size:xx-small">' . $auction->wr_start_country_code . ' ' . $w->name . ': ' . $w->stock . '</span>';
                            else
                                $warehouses_table .= '<span style="color:red;font-size:xx-small">' . $auction->wr_start_country_code . ' ' . $w->name . ': ' . $w->stock . '</span>';
                            $warehouses_table .= '</td></tr>';
                        }
                        $warehouses_table .= '</table>';
                        $q = "select distinct wwo.id, planned_arrival_date
								, ROUND((select sum(wwa1.qnt*a1.volume) from ww_order wwo1
								join wwo_article wwa1 on wwa1.wwo_id=wwo1.id
								join article a1 on wwa1.article_id=a1.article_id and a1.admin_id=0
								where wwo1.id=wwo.id),2) wwo_volume
								, c.volume car_volume
								, CONCAT(' (',wwo.comment,')') comment
							from ww_order wwo
							left join cars c on c.id=wwo.car_id
							join wwo_article wwa on wwa.wwo_id=wwo.id
							join article a on wwa.article_id=a.article_id and a.admin_id=0
							where /*wwa.article_id='{$au->article_id}' and*/ wwa.to_warehouse={$auction->start_warehouse_id}
							and not wwo.blocked and not closed";
                        $wwos = $dbr->getAll($q);
                        $wwo_list = 'WWO List:<br>';
                        foreach ($wwos as $w) {
                            if ($print)
                                $wwo_list .= 'WWO#' . $w->id . $w->comment . '
								Planned on ' . $w->planned_arrival_date . ' <font color="' . ($w->wwo_volume > $w->car_volume ? 'red' : 'green') . '">' . $w->wwo_volume . 'm3</font><br>';
                            else
                                $wwo_list .= '<a href="ware2ware_order.php?id=' . $w->id . '" target="_blank">WWO#' . $w->id . $w->comment . '</a>
								Planned on ' . $w->planned_arrival_date . ' <font color="' . ($w->wwo_volume > $w->car_volume ? 'red' : 'green') . '">' . $w->wwo_volume . 'm3</font>'
                                    . '. Add <input type="text" id="wwo_text_' . $w->id . '_' . $au->id . '" size="3"> pcs
								from <select id="wwo_select_' . $w->id . '_' . $au->id . '">' . $warehouse_options . '</select> to this WWO
								<input type="button" value="Add" id="wwo_btn_' . $w->id . '_' . $au->id . '" onClick="add_to_wwo(' . $w->id . ', ' . $au->id . ')"/><br>';
                            //						$wwo_list .= 'LOG:';
                        }
                    } else {
                        $warehouses_table = '';
                        $wwo_list = '';
                        $warehouse_options_reserve = '';
                    }
                    if (!$au->admin_id) {
                        $warehouse_options_reserve = '';
                        foreach ($warehouses as $warehouse_id => $warehouse_name) {
                            if (strlen($warehouses_array[$warehouse_id])) {
                                $warehouse_options_reserve .= '<option value="' . $warehouse_id . '"'
                                    . ($warehouse_id == $au->reserve_warehouse_id ? ' selected' : '') . '>' . $warehouses_array[$warehouse_id] . '</option>';
                            }
                        }
                        if ($print)
                            $warehouse_options_reserve = '<br>' . $warehouses_array[$au->reserve_warehouse_id] . '<br>';
                        else
                            $warehouse_options_reserve = '<br><select id="reserve_warehouse_id_select[' . $au->id . ']" onChange="change_orders_db(\'reserve_warehouse_id\',' . $au->id . ',this.value)"><option value="0">---</option>'
                                . $warehouse_options_reserve
                                . '</select><br>';
                    } else {
                    }
                    if ($print) {
                        $r[$key]->articles .= $au->a_nohref;
                        if ($au->barcode_type == 'A') $r[$key]->articles .= ' <b>A</b>';

                        if ($au->released_log) $r[$key]->articles .= " <span class='released-article-{$r[$key]->auction_number}-{$r[$key]->txnid} reserve-warehouse-" . (int)$au->reserve_warehouse_id . "' style='font-size:smaller'>({$au->released_log})</span>";
                        
                        if ($warestock) {
                            $r[$key]->articles .= ' <font color="' . $warestock_color . '">' . $warestock . '</font><span style="font-size:smaller">'.$au->reserved_warehouse_name.'</span>';
                        }
                        
                        if ($route_id && !$au->admin_id)
                        {
                            if ($au->barcode_type == 'A')
                            {
                                $locations = article_get_location_A($au->article_id, $au->warehouse_id);
                            }
                            else
                            {
                                $locations = article_get_location_C($au->article_id, $au->warehouse_id);
                            }
                            
                            $locations = array_slice($locations, 0, 5);
                            foreach ($locations as $_loc => $parcel_data)
                            {
                                if (stripos($_loc, '---') === false)
                                {
                                    $_loc = strip_tags($_loc);
                                    $_quantity = array_sum($parcel_data);
                                    $r[$key]->articles .= '<br />' . $_loc . ' <small>(' . $_quantity . ')</small>';
                                }
                            }
                            
                            //$r[$key]->articles .= ' <font color="' . $warestock_color . '">' . $warestock . '</font><span style="font-size:smaller">'.$au->reserved_warehouse_name.'</span>';
                        }
                    } else {
                        $r[$key]->articles .= $au->a_href;
                        if ($au->barcode_type == 'A') $r[$key]->articles .= ' <b class="spt1">A</b>';
                        
                        if ($au->released_log) $r[$key]->articles .= " <span class='released-article-{$r[$key]->auction_number}-{$r[$key]->txnid} reserve-warehouse-" . (int)$au->reserve_warehouse_id . "' style='font-size:smaller'>({$au->released_log})</span>";
                        
                        $r[$key]->articles .= (strlen($au->state_wwo) ? "<br><table><tr><td nowrap><a target='_blank' style='font-size:10px;color:#FF00FF' href='ware2ware_order.php?id={$au->wwo_id}'>{$au->state_wwo}</a></td></tr></table>" : '');
                        $r[$key]->articles .= (strlen($au->state_pl) ? "<br><table><tr><td nowrap><a target='_blank' style='font-size:10px;color:#FF00FF' href='picking_list.php?id={$au->pl_id}'>{$au->state_pl}</a></td></tr></table>" : '');
                        $r[$key]->articles .= '<div name="article_addon[]" style="display:none">'
                            . $warehouse_options_reserve
                            . $warehouses_table
                            . $wwo_list
                            . '</div>';
                    }
                    
                    if (!$au->admin_id) {
                        $r[$key]->articles .= "<input type='hidden' class='article_{$r[$key]->auction_number}_{$r[$key]->txnid}' value='{$au->article_id}'>";
                    }
                    $r[$key]->articles .= '<br>';
                    
                    if ($au->released_log)
                    {
                        $released_by_warehouses[(int)$au->reserve_warehouse_id . '|' . $au->reserved_warehouse_name][] = $au->article_id;
                        $isset_released++;
                    }
                }
                
                if ($wares_res && ! $wares_res_isset) {
                    unset($r[$key]);
                    continue;
                }
                
                $out_released = '';
                if ($isset_released > 1)
                {
                    $out_released .= "<br/><div id='wrapper-unrelease-warehouse-{$r[$key]->auction_number}-{$r[$key]->txnid}'>";
                    if (count(array_keys($released_by_warehouses)) > 1) 
                    {
                        $out_released .= "Unrelease from warehouse: ";
                        $out_released .= "<select id='unrelease-warehouse-{$r[$key]->auction_number}-{$r[$key]->txnid}'>";
                        
                        foreach ($released_by_warehouses as $name => $articles)
                        {
                            $name = explode('|', $name, 2);
                            $articles = implode(',', $articles);
                            
                            $out_released .= "<option value='{$name[0]}' data-articles='$articles'>{$name[1]}</option>";
                        }
                        
                        $out_released .= "</select> ";
                    }
                    
                    $out_released .= "<button class='unrelease-all-button' data-auction='{$r[$key]->auction_number}' data-txnid='{$r[$key]->txnid}'>Unrelease all articles</button>";
                    $out_released .= "</div>";
                }
                
                $r[$key]->articles .= $out_released;
                
            } // articles for genetal orders

            $r[$key]->task_name = $au->task_name;
            /*				$routes_options = '';
                            foreach($routes_array as $route_id=>$route_name) {
                                $routes_options .= '<option value="'.$route_id.'"'
                                .($route_id==$auction->get('route_id')?' selected':'').'>'.$route_name.'</option>';
                            }
                                .'Route <select id="route_id_'.$auction->get('id').'" name="route_id"><option label="" value="">---</option>'
                                .$routes_options
                                  .'</select>Label <input id="route_label_'.$auction->get('id').'" name="route_label" value="'.$auction->get('route_label').'" type="text" size="3">'
                                .'<input type="button" value="Update" id="route_btn_'.$auction->get('id').'" onClick="changeRoute('.$auction->get('id').')"/>'*/
#			$r[$key]->route = $au->articles;
#			$r[$key]->delivery_comment = $au->articles;
#			$r[$key]->delivery_date_confirmed = $au->articles;
            if (!$print) {
                $r[$key]->delivery_comment = '<a id="delivery_comment_a[' . $r[$key]->id . ']"
					href="javascript:change_auction(\'delivery_comment\',' . $r[$key]->id . ',\'textarea\', 0)">' . (strlen(trim($r[$key]->delivery_comment)) ? nl2br($r[$key]->delivery_comment) : '...') . '</a>
					<textarea style="display:none" id="delivery_comment_textarea[' . $r[$key]->id . ']">' . ($r[$key]->delivery_comment) . '</textarea>
					<input type="button" id="delivery_comment_button[' . $r[$key]->id . ']" style="display:none" value="Update" onClick="change_auction(\'delivery_comment\',' . $r[$key]->id . ',\'textarea\', 1)"/>';
            }
            $r[$key]->route_label_notags = $r[$key]->route_label;
            if ($print)
                $r[$key]->route_label = nl2br($r[$key]->route_label);
            else
                $r[$key]->route_label = '<a id="route_label_a[' . $r[$key]->id . ']"
				href="javascript:change_auction(\'route_label\',' . $r[$key]->id . ',\'text\', 0)">' . (strlen(trim($r[$key]->route_label)) ? nl2br($r[$key]->route_label) : '...') . '</a>
				<input type="text" size="3" style="display:none" id="route_label_text[' . $r[$key]->id . ']" value="' . $r[$key]->route_label . '">
				<input type="button" id="route_label_button[' . $r[$key]->id . ']" style="display:none" value="Update" onClick="change_auction(\'route_label\',' . $r[$key]->id . ',\'text\', 1)"/>';
            $routes_options = '';
            foreach ($routes_array as $route_id => $route_name) {
                $routes_options .= '<option value="' . $route_id . '"'
                    . ($route_id == $r[$key]->route_id ? ' selected' : '') . '>' . $route_name . '</option>';
            }
            if ($print) {
                $r[$key]->route = $routes_array[$r[$key]->route_id];
                list($dummy, $r[$key]->day) = explode('day ', $routes_array[$r[$key]->route_id]);
                $r[$key]->day = (int)$r[$key]->day;
            } else {
                $r[$key]->route = ($r[$key]->route_deleted ? $r[$key]->route_name . ' DELETED<br>' : '') . '<select id="route_id_select[' . $r[$key]->id . ']" onChange="change_auction_db(\'route_id\',' . $r[$key]->id . ',this.value,\'route_id_select[' . $r[$key]->id . ']\',\'value\')"><option value="0">---</option>'
                    . $routes_options
                    . '</select>';
            }
            $route_delivery_type_options = '';
            foreach ($route_delivery_type_array as $route_delivery_type_rec) {
                $route_delivery_type_options .= '<option value="' . $route_delivery_type_rec->id . '"'
                    . ($route_delivery_type_rec->id == $r[$key]->route_delivery_type ? ' selected' : '') . '>' . $route_delivery_type_rec->name . ' (+' . $route_delivery_type_rec->minutes . ' minutes)</option>';
                if ($route_delivery_type_rec->id == $r[$key]->route_delivery_type)
                    $route_delivery_type_print = $route_delivery_type_rec->name . ' (+' . $route_delivery_type_rec->minutes . ' minutes)';
            }
            if ($print)
                $r[$key]->route_delivery_type = $route_delivery_type_print;
            else
                $r[$key]->route_delivery_type = '<select id="route_delivery_type_select[' . $r[$key]->id . ']" onChange="change_auction_db(\'route_delivery_type\',' . $r[$key]->id . ',this.value)"><option value="0">---</option>'
                    . $route_delivery_type_options
                    . '</select>';
                if($r[$key]->route_delivery_type_id == '5' && $r[$key]->route_delivery_type_minutes > 0){
                    $r[$key]->route_delivery_type .= "<div style='text-align:right; padding-top:10px; color:gray'>{$r[$key]->route_delivery_type_minutes} min</div>";
                }
            if (!$print) {
                $r[$key]->shipping_order_datetime = '
			<SCRIPT LANGUAGE="JavaScript">
			var cal_shipping_order_date' . $r[$key]->id . ' = new CalendarPopup();
			cal_shipping_order_date' . $r[$key]->id . '.setReturnFunction("setMultipleValues_shipping_order_date' . $r[$key]->id . '");
			function setMultipleValues_shipping_order_date' . $r[$key]->id . '(y,m,d) {
				 change_auction_db(\'shipping_order_date\', ' . $r[$key]->id . ', y+\'-\'+m+\'-\'+d, \'shipping_order_date_cal[' . $r[$key]->id . ']\', \'value\');
				}
			</SCRIPT>
			<input type="text" name="shipping_order_date" id="shipping_order_date_cal[' . $r[$key]->id . ']" size="10" value="' . $r[$key]->shipping_order_date . '"/>
			<A HREF="#" onClick="cal_shipping_order_date' . $r[$key]->id . '.select(document.getElementById(\'shipping_order_date_cal[' . $r[$key]->id . ']\'),\'anchor_shipping_order_date' . $r[$key]->id . '\',\'yyyy-MM-dd\'); return false;"
			TITLE="cal_shipping_order_date' . $r[$key]->id . '.select(document.getElementById(\'shipping_order_date_cal[' . $r[$key]->id . ']\'),\'anchor_shipping_order_date' . $r[$key]->id . '\',\'yyyy-MM-dd\'); return false;"
			NAME="anchor_shipping_order_date' . $r[$key]->id . '" ID="anchor_shipping_order_date' . $r[$key]->id . '">...</A>';
                $r[$key]->shipping_order_datetime .= '<br><a id="shipping_order_time_a[' . $r[$key]->id . ']"
					href="javascript:change_auction(\'shipping_order_time\',' . $r[$key]->id . ',\'text\', 0)">' . (strlen(trim($r[$key]->shipping_order_time)) ? nl2br($r[$key]->shipping_order_time) : '...') . '</a>
					<input type="text" size="8" style="display:none" id="shipping_order_time_text[' . $r[$key]->id . ']" value="' . ($r[$key]->shipping_order_time ? $r[$key]->shipping_order_time : '') . '">
					<input type="button" id="shipping_order_time_button[' . $r[$key]->id . ']" style="display:none" value="Update" onClick="change_auction(\'shipping_order_time\',' . $r[$key]->id . ',\'text\', 1)"/><br>';
                $shipping_order_datetime_confirmed_row = $dbr->getRow("SELECT tl.updated, IFNULL(u.name, tl.username) username
					FROM total_log tl
					LEFT JOIN users u ON u.system_username=tl.username
					WHERE table_name='auction' AND field_name='shipping_order_datetime_confirmed' AND tableid=" . $r[$key]->id . "
					ORDER BY updated DESC LIMIT 1");
                if ($r[$key]->shipping_order_datetime_confirmed) {
                    $r[$key]->shipping_order_datetime .= '<div id=\'shipping_order_datetime_confirmed_div[' . $r[$key]->id . ']\'>'
                        . "<b>Confirmed</b> by {$shipping_order_datetime_confirmed_row->username} on {$shipping_order_datetime_confirmed_row->updated}" .
                        '</div>
				<input type="button" id=\'shipping_order_datetime_confirmed_button[' . $r[$key]->id . ']\' value="Unconfirm"
					onClick="change_auction_db(\'shipping_order_datetime_confirmed\', ' . $r[$key]->id . ', this.value, \'shipping_order_datetime_confirmed_button[' . $r[$key]->id . ']\', \'value\', \'shipping_order_datetime_confirmed_div[' . $r[$key]->id . ']\')">';
                } else {
                    $r[$key]->shipping_order_datetime .= '<div id=\'shipping_order_datetime_confirmed_div[' . $r[$key]->id . ']\'>';
                    if ($shipping_order_datetime_confirmed_row->username)
                        $r[$key]->shipping_order_datetime .= "<span style='color:red'>declined</span> by {$shipping_order_datetime_confirmed_row->username} on {$shipping_order_datetime_confirmed_row->updated}";
                    else
                        $r[$key]->shipping_order_datetime .= "<span style='color:black'><b>waiting</b></span>";
                    $r[$key]->shipping_order_datetime .= '</div>';
                    $r[$key]->shipping_order_datetime .= '
					<input  type="button" id=\'shipping_order_datetime_confirmed_button[' . $r[$key]->id . ']\' value="Delivery time approved"
						onClick="check_route_overload(this, ' . $r[$key]->auction_number . ',' . $r[$key]->txnid . ',' . $r[$key]->id . ')">';
                }
                if(!empty($r[$key]->max_delivery_time) && (strtotime($r[$key]->start_date.' '.$r[$key]->max_delivery_time) < strtotime($r[$key]->route_proposition_time_text))) {
                    $r[$key]->shipping_order_datetime .= '<font color="red">' . $r[$key]->route_proposition_time_text . '</font>';
                }else{
                    $r[$key]->shipping_order_datetime .= '<font color="gray">' . $r[$key]->route_proposition_time_text . '</font>';
                }

                $main_auction = new Auction($db, $dbr, $r[$key]->auction_number, $r[$key]->txnid);
                $countries = $dbr->getAssoc("select country.code, country.* from country");
                $smarty->assign("countries", $countries);
                $smarty->assign('main_auction', $main_auction);
                $r[$key]->shipping_order_datetime .= $smarty->fetch("shipping_order_emails_buttons.tpl");
            } // if not HTML or PDF
            else {
                $r[$key]->shipping_order_datetime = $r[$key]->shipping_order_date . ' ' . $r[$key]->shipping_order_time . '<br>';
                $shipping_order_datetime_confirmed_row = $dbr->getRow("SELECT tl.updated, IFNULL(u.name, tl.username) username
					FROM total_log tl
					LEFT JOIN users u ON u.system_username=tl.username
					WHERE table_name='auction' AND field_name='shipping_order_datetime_confirmed' AND tableid=" . $r[$key]->id . "
					ORDER BY updated DESC LIMIT 1");
                if ($r[$key]->shipping_order_datetime_confirmed) {
                    $r[$key]->shipping_order_datetime .= "<b>Confirmed</b> by {$shipping_order_datetime_confirmed_row->username} on {$shipping_order_datetime_confirmed_row->updated}";
                } else {
                    if ($shipping_order_datetime_confirmed_row->username)
                        $r[$key]->shipping_order_datetime .= "<span style='color:red'>declined</span> by {$shipping_order_datetime_confirmed_row->username} on {$shipping_order_datetime_confirmed_row->updated}";
                    else
                        $r[$key]->shipping_order_datetime .= "<span style='color:black'><b>waiting</b></span>";
                }
                $r[$key]->shipping_order_datetime .= '<br><font color="gray">' . $r[$key]->route_proposition_time_text . '</font>';
                $log = EmailLog::listAll($db, $dbr, $r[$key]->auction_number, $r[$key]->txnid
                    , "and email_log.template='$tplname'", 'limit 1', 'desc');
                if (count($log)) {
                    $logentry = $log[0];
                    $log_row = $dbr->getRow("SELECT tl.updated, IFNULL(u.name, tl.username) username
						FROM total_log tl
						LEFT JOIN users u ON u.system_username=tl.username
						WHERE table_name='email_log' AND field_name='id' AND tableid=" . $logentry->id . "
						ORDER BY updated DESC LIMIT 1");
                    $r[$key]->shipping_order_datetime .= 'Email for delivery time approvement<br>Was sent by ' . $log_row->username . ' on ' . $log_row->updated;
                }
                $log = EmailLog::listAll($db, $dbr, $r[$key]->auction_number, $r[$key]->txnid
                    , "and email_log.template='shipping_order_datetime_late'", 'limit 1', 'desc');
                if (count($log)) {
                    $logentry = $log[0];
                    $log_row = $dbr->getRow("SELECT tl.updated, IFNULL(u.name, tl.username) username
						FROM total_log tl
						LEFT JOIN users u ON u.system_username=tl.username
						WHERE table_name='email_log' AND field_name='id' AND tableid=" . $logentry->id . "
						ORDER BY updated DESC LIMIT 1");
                    $r[$key]->shipping_order_datetime .= 'Email than driver is<br>Was sent by ' . $log_row->username . ' on ' . $log_row->updated;
                }
            } // if export to HTML and PDF
            if ($debug) {
                echo 'shipping_username Q3: ' . round(getmicrotime() - $time, 4) . '<br>';
                $time = getmicrotime();
            }
            $route_log = $dbr->getAll("
				select tl.*, CONCAT('by ', IFNULL(u.name, tl.username), ' on ', tl.updated) user_updated
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='auction' and tableid={$auction->id}
				and field_name in ('route_id'
				, 'shipping_order_datetime_confirmed'
				, 'shipping_order_delivery_confirmed')
				order by tl.updated");
            $smarty->assign("route_log", $route_log);
            if ($debug) {
                echo 'shipping_username Q4: ' . round(getmicrotime() - $time, 4) . '<br>';
                $time = getmicrotime();
            }
            $english = Auction::getTranslation($db, $dbr, $auction->siteid);
            $smarty->assign("english", $english);
            $all_routes_array = $dbr->getAssoc("SELECT id,name FROM route");
            $smarty->assign("auction_number", $r[$key]->auction_number);
            $smarty->assign("all_routes_array", $all_routes_array);
            $smarty->assign('actions_show_from', count($route_log) - Config::get($db, $dbr, 'shipping_actions_per_page'));
            if (!$print) {
                $r[$key]->shipping_order_datetime .= $smarty->fetch('_shipping_auction_route_log.tpl');
            }

            if ($print) {
                $r[$key]->all_shipping = $r[$key]->all_shipping;
            } else {
                $r[$key]->all_shipping = '<div><a href="gmap.php?shipping_username=' . $username . '&shipping_mode=' . $shipping_mode . '&id[]=' . $r[$key]->id . '">' . $r[$key]->all_shipping . '</a></div>';
                $r[$key]->all_shipping .= '<br/><input type="checkbox" value="1" id="route_ignore_checkbox[' . $r[$key]->id . ']"' . ($r[$key]->route_ignore ? ' checked' : '') . '
					onClick="change_auction_db(\'route_ignore\', ' . $r[$key]->id . ', this.checked, \'route_ignore_checkbox[' . $r[$key]->id . ']\', \'checked\', \'auction_row[' . $r[$key]->id . ']\', \'style.color\')"/> Ignore';
            }
        }

        if ($debug) {
            echo 'shipping_username Q5: ' . round(getmicrotime() - $time, 4) . '<br>';
            $time = getmicrotime();
        }
        if ($shipping_mode == -2) foreach ($r as $key => $auction) {
            $order = Order::listAll($db, $dbr, $auction->auction_number, $auction->txnid);
            foreach ($order as $article) {
                if ($article->manual) continue;
                if (!$article->article_id) continue;
                $article_obj = new Article($db, $dbr, $article->article_id, 0, 0);
#		echo 'new Article: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
                $cnt = 0;
                $warehouse_id_array = array();
                foreach ($warehouses as $warehouse_id => $dummy) {
                    $warehouse_id_array[] = $warehouse_id;
                }
//					echo '+'.$article_obj->getPieces($warehouse_id)."($warehouse_id)";
                $cnt += $article_obj->getPiecesArray($warehouse_id_array);
#		echo 'getPieces: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
#				}
//				echo $article->article_id.':'.$cnt.'<'.$article->quantity.'<br>';
                if ($cnt < $article->quantity) {
                    unset($r[$key]);
                    break;
                }
            }
        }
        if ($debug) {
            echo 'shipping_username Q6: ' . round(getmicrotime() - $time, 4) . '<br>';
            $time = getmicrotime();
        }
        if (PEAR::isError($r)) {
            aprint_r($r);
            return $r;
        }
        

        return $r;
    }

	static function findTasks($db, $dbr, $pars)
    {
	  	global $loggedUser;
		require_once 'lib/Config.php';
		$where = " 1 ";
		$username = $pars['username'];
		$auction_number = $pars['auction_number'];
		$task_status = $pars['task_status'];
		$route_id = $pars['route_id'];
		$task_type = $pars['task_type'];
        $route_date_from = $pars['route_date_from'];
        $route_date_to = $pars['route_date_to'];
        $country = $pars['country'];
        $auction_numbers = $pars['auction_numbers'];
        $driver = $pars['driver'];
//		$deleted = $pars['deleted'];
		if (strlen($username)) $where .= " and auction.shipping_username in ('$username'/*,'_driver'*/) ";
			else $where .= " and auction.shipping_username is not null ";
//		if (strlen($deleted)) $where .= " and auction.deleted = $deleted ";
		if (strlen($auction_number)) $where .= " and auction.auction_number = $auction_number ";
		if (strlen($route_id)) $where .= " and auction.route_id = $route_id ";
		if (strlen($task_status)) switch ($task_status) {
				case 'open':
					$where .= " and not fget_ASent(auction.auction_number, auction.txnid) and auction.deleted = 0 ";
				break;
				case 'shipped':
					$where .= " and fget_ASent(auction.auction_number, auction.txnid) ";
				break;
				case 'deleted':
					$where .= " and auction.deleted = 1 ";
				break;
			}
		if (strlen($task_type)) $where .= " and auction.task_type = '$task_type' ";
        if (!empty($route_date_from) && !empty($route_date_to)) $where .= " and route.start_date BETWEEN '$route_date_from' and '$route_date_to' ";
        if (!empty($auction_numbers)) $where .= " and auction.auction_number IN ($auction_numbers) ";
        if (!empty($country)) $where .= " and au_sh_country.value = '$country' ";
        if (!empty($driver)) {
            $where .= " and route_driver.driver_username = '$driver'";
        }

		$q=            "SELECT 
			auction.auction_number
			, auction.txnid
			, IFNULL(u1.name, auction.shipping_username) shipping_person
			, (select count(*) from printer_log where printer_log.auction_number=auction.auction_number
				and printer_log.txnid=auction.txnid and printer_log.username='".$loggedUser->data->username."') as printed
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.shipping_resp_username
			, auction.siteid
			, auction.id
			, auction.route_id
			, auction.rma_id rma_id_original
			, auction.route_label
			, auction.route_delivery_type
			, route_delivery_type.name route_delivery_type_name
			, concat(auction.route_id, LPAD(100000*ROUND(auction.route_label,1),11,'0')) route_id_label
			, auction.route_distance
			, auction.route_duration
			, concat(auction.route_distance, ' km, ', auction.route_duration, ' mins'
				, IFNULL(CONCAT('<br><br>Back to warehouse: ',route.last_route_distance, ' km, ', route.last_route_duration, ' mins'),'')
				) route_distance_duration
			, CONCAT(auction.route_duration_distance_text
				, IFNULL(CONCAT('<br><br>Back to warehouse: ',route.last_route_distance, ' km, ', route.last_route_duration, ' mins'),'')
				) route_duration_distance_text
			, route.start_date
			, route.start_time
			, route.start_warehouse_id
			, route.end_warehouse_id
			, route.name route_name
			, car_speed.speed car_speed
			, car_speed.factor car_factor
			, route.driver_amt
			, cars.name car_name
			, cars.weight car_weight
			, cars.height_int car_height_int
			, cars.width_int car_width_int
			, cars.length_int car_length_int
			, cars.height_int*cars.width_int*cars.length_int/1000000 car_volume
			, trailer.weight trailer_weight
			, trailer.height_int trailer_height_int
			, trailer.width_int trailer_width_int
			, trailer.length_int trailer_length_int
			, (trailer.height_int*trailer.width_int*trailer.length_int)/1000000 trailer_volume
			, auction.shipping_order_date
			, auction.shipping_order_time
			, IF(auction.shipping_order_date='0000-00-00','', concat(auction.shipping_order_date, ' ', IFNULL(auction.shipping_order_time,'')
				, IF(auction.shipping_order_datetime_confirmed, '', '<br/><b>Not confirmed</b>'))) shipping_order_datetime
			, concat(auction.shipping_order_date, ' ', IFNULL(auction.shipping_order_time,'')) shipping_order_datetime1
			, auction.delivery_comment
			, auction.shipping_order_datetime_confirmed
			, auction.route_proposition_time_text
			, auction.route_ignore
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, auction.priority_comment
			, fget_ACustomer(auction.auction_number, auction.txnid) customer
			, TO_DAYS(NOW())-TO_DAYS(IF(auction.delivery_date_customer='0000-00-00', (select tl.updated from total_log tl 
				where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1)
				, auction.delivery_date_customer)) days_due
			, CONCAT(IF(auction.rma_id, CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',auction.rma_id,'&number=',auction.auction_number,'&txnid=',auction.txnid,'\'>',auction.rma_id,'</a>'), ''),'<br>'
				, IFNULL((select group_concat(
					CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',rma_id,'&number=',rma.auction_number,'&txnid=',rma.txnid,'\'>',rma_id,'</a>')
					 SEPARATOR '<br>') 
				from rma where rma.auction_number=auction.auction_number and rma.txnid=auction.txnid)
				,'')) rma_id
			, (select concat(create_date, ' by ', IFNULL(users.name, auction_sh_comment.username)) from auction_sh_comment
				left join users on users.username=auction_sh_comment.username
				where auction_sh_comment.comment='Exported to dhl'
				and auction_sh_comment.auction_number = auction.auction_number
				and auction_sh_comment.txnid = auction.txnid
				limit 1) as exported_to_dhl
			, IF(invoice.open_amount>0,concat('<b>',invoice.open_amount,'</b>'),invoice.open_amount) open_amount_tag
			, concat(
				IFNULL(au_sh_company.value,''),'<br>',
				IFNULL(au_firstname_shipping.value,''), ' ', IFNULL(au_name_shipping.value,''),'<br>',
				IFNULL(au_sh_street.value,''),' ',IFNULL(au_sh_house.value,''),'<br>',
				IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,''),'<br>',
				IFNULL(au_sh_country.value,''),'<br>',
				IFNULL(au_sh_tel.value,''),'<br>',
				IFNULL(au_sh_cel.value,'')
			) all_shipping
			, concat(
				IFNULL(au_sh_street.value,''),' ',
				IFNULL(au_sh_house.value,''),' ',
				IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,''),' ',
				IFNULL(au_sh_country.value,'')
			) all_shipping_address_only
			, concat(
				IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,'')
			) ZIPCity
			,(select Group_CONCAT(u.name SEPARATOR '<br>')
                from route_driver as rd
                JOIN users as u on u.username = rd.driver_username
                where rd.route_id = auction.route_id
                  
                ) as routedrivers_name
		FROM auction
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number 
			and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
		left join auction_par_varchar au_sh_country on auction.auction_number=au_sh_country.auction_number
			and auction.txnid=au_sh_country.txnid and au_sh_country.key='country_shipping'
		left join auction_par_varchar au_sh_street on auction.auction_number=au_sh_street.auction_number
			and auction.txnid=au_sh_street.txnid and au_sh_street.key='street_shipping'
		left join auction_par_varchar au_sh_house on auction.auction_number=au_sh_house.auction_number
			and auction.txnid=au_sh_house.txnid and au_sh_house.key='house_shipping'
		left join auction_par_varchar au_sh_tel on auction.auction_number=au_sh_tel.auction_number
			and auction.txnid=au_sh_tel.txnid and au_sh_tel.key='tel_shipping'
		left join auction_par_varchar au_sh_cel on auction.auction_number=au_sh_cel.auction_number
			and auction.txnid=au_sh_cel.txnid and au_sh_cel.key='cel_shipping'
		left join auction_par_varchar au_sh_company on auction.auction_number=au_sh_company.auction_number
			and auction.txnid=au_sh_company.txnid and au_sh_company.key='company_shipping'
            LEFT JOIN offer ON offer.offer_id = auction.offer_id
            LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
            LEFT JOIN users u1 ON auction.shipping_username = u1.username
            LEFT JOIN users u2 ON auction.shipping_resp_username = u2.username
			LEFT JOIN payment p ON p.auction_number = auction.auction_number AND p.txnid = auction.txnid
			LEFT JOIN route ON route.id = auction.route_id
			left join route_delivery_type on route_delivery_type.id=auction.route_delivery_type
			LEFT JOIN cars ON route.car_id = cars.id
			LEFT JOIN car_speed ON car_speed.id = cars.speed_id
            LEFT JOIN cars AS `trailer` ON route.trailer_car_id = trailer.id
			LEFT JOIN route_driver on route_driver.route_id = route.id
            WHERE $where
			 and auction.txnid=4
			and main_auction_number=0 group by auction.auction_number
			";
        file_put_contents('trace_findtasks.txt',$q);
//		echo $q; echo '<br>'; //die();
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		$configs = Config::getAll($db, $dbr);
		$routes_array = get_routes_array_not_closed();
		$route_delivery_type_array = $dbr->getAll("select *
			from route_delivery_type
			order by name");
		foreach ($r as $key=>$auction) {
			$r[$key]->delivery_comment = '<a id="delivery_comment_a['.$r[$key]->id.']" 
				href="javascript:change_auction(\'delivery_comment\','.$r[$key]->id.',\'textarea\', 0)">'.(strlen(trim($r[$key]->delivery_comment))?nl2br($r[$key]->delivery_comment):'...').'</a>
				<textarea style="display:none" id="delivery_comment_textarea['.$r[$key]->id.']">'.($r[$key]->delivery_comment).'</textarea>
				<input type="button" id="delivery_comment_button['.$r[$key]->id.']" style="display:none" value="Update" onClick="change_auction(\'delivery_comment\','.$r[$key]->id.',\'textarea\', 1)"/>';
			$r[$key]->route_label = '<a id="route_label_a['.$r[$key]->id.']" 
				href="javascript:change_auction(\'route_label\','.$r[$key]->id.',\'text\', 0)">'.(strlen(trim($r[$key]->route_label))?nl2br($r[$key]->route_label):'...').'</a>
				<input type="text" size="3" style="display:none" id="route_label_text['.$r[$key]->id.']" value="'.$r[$key]->route_label.'">
				<input type="button" id="route_label_button['.$r[$key]->id.']" style="display:none" value="Update" onClick="change_auction(\'route_label\','.$r[$key]->id.',\'text\', 1)"/>';
			$routes_options = '';
			foreach($routes_array as $route_id=>$route_name) {
					$routes_options .= '<option value="'.$route_id.'"'
					.($route_id==$r[$key]->route_id?' selected':'').'>'.$route_name.'</option>';
			}
			$r[$key]->route = '<select id="route_id_select['.$r[$key]->id.']" onChange="change_auction_db(\'route_id\','.$r[$key]->id.',this.value)"><option value="0">---</option>'
					.$routes_options
					.'</select>';
			$route_delivery_type_options = '';
			foreach($route_delivery_type_array as $route_delivery_type_rec) {
					$route_delivery_type_options .= '<option value="'.$route_delivery_type_rec->id.'"'
					.($route_delivery_type_rec->id==$r[$key]->route_delivery_type?' selected':'').'>'.$route_delivery_type_rec->name.' (+'.$route_delivery_type_rec->minutes.' minutes)</option>';
			}
			$r[$key]->route_delivery_type = '<select id="route_delivery_type_select['.$r[$key]->id.']" onChange="change_auction_db(\'route_delivery_type\','.$r[$key]->id.',this.value)"><option value="0">---</option>'
					.$route_delivery_type_options
					.'</select>';
			$r[$key]->shipping_order_datetime = '
		<SCRIPT LANGUAGE="JavaScript">
		var cal_shipping_order_date'.$r[$key]->id.' = new CalendarPopup();
		cal_shipping_order_date'.$r[$key]->id.'.setReturnFunction("setMultipleValues_shipping_order_date'.$r[$key]->id.'");
		function setMultipleValues_shipping_order_date'.$r[$key]->id.'(y,m,d) {
			 change_auction_db(\'shipping_order_date\', '.$r[$key]->id.', y+\'-\'+m+\'-\'+d, \'shipping_order_date_cal['.$r[$key]->id.']\', \'value\');
			}
		</SCRIPT>
	
		<input type="text" name="shipping_order_date" id="shipping_order_date_cal['.$r[$key]->id.']" size="10" value="'.$r[$key]->shipping_order_date.'"/>
		<A HREF="#" onClick="cal_shipping_order_date'.$r[$key]->id.'.select(document.getElementById(\'shipping_order_date_cal['.$r[$key]->id.']\'),\'anchor_shipping_order_date'.$r[$key]->id.'\',\'yyyy-MM-dd\'); return false;" 
		TITLE="cal_shipping_order_date'.$r[$key]->id.'.select(document.getElementById(\'shipping_order_date_cal['.$r[$key]->id.']\'),\'anchor_shipping_order_date'.$r[$key]->id.'\',\'yyyy-MM-dd\'); return false;" 
		NAME="anchor_shipping_order_date'.$r[$key]->id.'" ID="anchor_shipping_order_date'.$r[$key]->id.'">...</A>';
			$r[$key]->shipping_order_datetime .= '<br><a id="shipping_order_time_a['.$r[$key]->id.']" 
				href="javascript:change_auction(\'shipping_order_time\','.$r[$key]->id.',\'text\', 0)">'.(strlen(trim($r[$key]->shipping_order_time))?nl2br($r[$key]->shipping_order_time):'...').'</a>
				<input type="text" size="8" style="display:none" id="shipping_order_time_text['.$r[$key]->id.']" value="'.($r[$key]->shipping_order_time?$r[$key]->shipping_order_time:'').'">
				<input type="button" id="shipping_order_time_button['.$r[$key]->id.']" style="display:none" value="Update" onClick="change_auction(\'shipping_order_time\','.$r[$key]->id.',\'text\', 1)"/><br>';
			$shipping_order_datetime_confirmed_row = $dbr->getRow("select tl.updated, IFNULL(u.name, tl.username) username
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='auction' and field_name='shipping_order_datetime_confirmed' and tableid=".$r[$key]->id."
				order by updated desc limit 1");
			if ($r[$key]->shipping_order_datetime_confirmed) {
				$r[$key]->shipping_order_datetime .= '<div id=\'shipping_order_datetime_confirmed_div['.$r[$key]->id.']\'>'
					."<b>Confirmed</b> by {$shipping_order_datetime_confirmed_row->username} on {$shipping_order_datetime_confirmed_row->updated}".
					'</div>
			<input type="button" id=\'shipping_order_datetime_confirmed_button['.$r[$key]->id.']\' value="Unconfirm" 
				onClick="change_auction_db(\'shipping_order_datetime_confirmed\', '.$r[$key]->id.', this.value, \'shipping_order_datetime_confirmed_button['.$r[$key]->id.']\', \'value\', \'shipping_order_datetime_confirmed_div['.$r[$key]->id.']\')">';
			} else {
				$r[$key]->shipping_order_datetime .= '<div id=\'shipping_order_datetime_confirmed_div['.$r[$key]->id.']\'>';
				if ($shipping_order_datetime_confirmed_row->username)
					$r[$key]->shipping_order_datetime .= "unconfirmed by {$shipping_order_datetime_confirmed_row->username} on {$shipping_order_datetime_confirmed_row->updated}";
				$r[$key]->shipping_order_datetime .= '</div>';
				$r[$key]->shipping_order_datetime .= '
				<input  type="button" id=\'shipping_order_datetime_confirmed_button['.$r[$key]->id.']\' value="Delivery time approved" 
					onClick="change_auction_db(\'shipping_order_datetime_confirmed\', '.$r[$key]->id.', this.value, \'shipping_order_datetime_confirmed_button['.$r[$key]->id.']\', \'value\', \'shipping_order_datetime_confirmed_div['.$r[$key]->id.']\')">';
			}
			$r[$key]->shipping_order_datetime .= '<font color="gray">'.$r[$key]->route_proposition_time_text.'</font>';
			$r[$key]->all_shipping = '<div><a href="gmap.php?shipping_username='.$username.'&shipping_mode='.$shipping_mode.'&id[]='.$r[$key]->id.'">'.$r[$key]->all_shipping.'</a></div>';
			$r[$key]->all_shipping .= '<br/><input type="checkbox" value="1" id="route_ignore_checkbox['.$r[$key]->id.']"'.($r[$key]->route_ignore?' checked':'').'
				onClick="change_auction_db(\'route_ignore\', '.$r[$key]->id.', this.checked, \'route_ignore_checkbox['.$r[$key]->id.']\', \'checked\', \'auction_row['.$r[$key]->id.']\', \'style.color\')"/> Ignore';
		}
        return $r;
    }

	static function findByShipCountry($db, $dbr, $country_code, $shipping_username, $warehouse_id, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
	  global $warehouses;
	  global $loggedUser;
        $days = (int)$days;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = '';
		if (strlen($country_code)) $where .= " and au_sh_country.value='".countryCodeToCountry($country_code)."' ";
		$warehouse_id = (int)$warehouse_id;
		$shipping_username = mysql_escape_string($shipping_username);
		if (strlen($shipping_username)) $where .= " and auction.shipping_username='$shipping_username' ";
		$join = " (w.country_code='$country_code' or '$country_code'='') and (w.warehouse_id = $warehouse_id or $warehouse_id=0) ";
		$q=            "SELECT SQL_CALC_FOUND_ROWS 
			w.name warehouse_name
			, CONCAT(ROUND(awd.distance),' km<br/>',ROUND(awd.duration),' mins') distance
			, IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					where auction_calcs.auction_number = auction.auction_number AND auction_calcs.txnid = auction.txnid
					),0)
			+ IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					left join auction au1 on auction_calcs.auction_number = au1.auction_number 
						and auction_calcs.txnid=au1.txnid
					where au1.main_auction_number = auction.auction_number AND au1.main_txnid = auction.txnid
					),0) real_shipping_cost

			,IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
				IF(delivery_date_customer='0000-00-00' or fget_ASent(auction.auction_number, auction.txnid)=1, delivery_date_customer, 
					IF(TO_DAYS(NOW())>=TO_DAYS(delivery_date_customer), 
						CONCAT('<span style=".'"color:#FF0000;font-weight:bold;text-decoration:blink"'.">', delivery_date_customer, '</span>'),
						CONCAT('<span style=".'"color:#FF0000;font-weight:bold;"'.">', delivery_date_customer, '</span>')
				)) delivery_date_customer_colored
				,IF(priority, 'green', IF(fget_ASent(auction.auction_number, auction.txnid)=0, '', 'gray')) as colour
					,IFNULL(u1.name, auction.shipping_username) shipping_person
					,IFNULL(u2.name, auction.shipping_resp_username) responsible_username
					,IF( p.payment_date  <= DATE_SUB(NOW(), INTERVAL ".Config::get($db, $dbr, 'ship_ready_to_ship')." DAY),'red','') too_old
			, p.paid_amount
			, IFNULL(p.payment_date, 'FREE') paid_date
			, (select min(log_date) from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					and printer_log.username='".$loggedUser->data->username."') print_date
			, auction.auction_number
			, auction.txnid
			, (select count(*) from printer_log where printer_log.auction_number=auction.auction_number
				and printer_log.txnid=auction.txnid and printer_log.username='".$loggedUser->data->username."') as printed
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.shipping_resp_username
			, auction.siteid
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, auction.priority_comment
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
			, au_zip_shipping.value as zip_shipping
			, fget_ACustomer(auction.auction_number, auction.txnid) customer
			, TO_DAYS(NOW())-TO_DAYS(IF(auction.delivery_date_customer='0000-00-00', (select tl.updated from total_log tl 
				where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1)
				, auction.delivery_date_customer)) days_due
			, CONCAT(IF(auction.rma_id, CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',auction.rma_id,'&number=',auction.auction_number,'&txnid=',auction.txnid,'\'>',auction.rma_id,'</a>'), ''),'<br>'
				, IFNULL((select group_concat(
					CONCAT('<a target=\'_blank\' href=\'rma.php?rma_id=',rma_id,'&number=',rma.auction_number,'&txnid=',rma.txnid,'\'>',rma_id,'</a>')
					 SEPARATOR '<br>') 
				from rma where rma.auction_number=auction.auction_number and rma.txnid=auction.txnid)
				,'')) rma_id
			, au_sh_country.value sh_country
 			, (select concat(create_date, ' by ', IFNULL(users.name, auction_sh_comment.username)) from auction_sh_comment
				left join users on users.username=auction_sh_comment.username
				where auction_sh_comment.comment='Exported to dhl'
				and auction_sh_comment.auction_number = auction.auction_number
				and auction_sh_comment.txnid = auction.txnid
				limit 1) as exported_to_dhl
FROM auction
		join (select min(sent) sent, auction_number, txnid
			from orders group by auction_number, txnid) o 
		on o.auction_number=auction.auction_number and o.txnid=auction.txnid
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number 
			and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
		left join auction_par_varchar au_sh_country on auction.auction_number=au_sh_country.auction_number
			and auction.txnid=au_sh_country.txnid and au_sh_country.key='country_shipping'
            LEFT JOIN offer ON offer.offer_id = auction.offer_id
            LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
            JOIN users u1 ON auction.shipping_username = u1.username
            LEFT JOIN users u2 ON auction.shipping_resp_username = u2.username
	    LEFT JOIN (select sum(amount) paid_amount, max(payment_date ) payment_date, auction_number, txnid
		from payment group by auction_number, txnid) p 
		ON p.auction_number = auction.auction_number AND p.txnid = auction.txnid
            JOIN warehouse w ON $join
            left JOIN auction_warehouse_distance awd 
		ON w.warehouse_id=awd.warehouse_id 
		and awd.auction_number=auction.auction_number and awd.txnid=auction.txnid
            WHERE IFNULL(auction.shipping_username,'')<>'' and o.sent=0
and main_auction_number=0
AND auction.deleted = 0 
$where
			$sort
			LIMIT $from, $to
			";
//		echo $q; echo '<br>'; //die();
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findWaitToShip($db, $dbr, $type)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findWaitToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$q = "SELECT auction.*
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				,IF(TO_DAYS(NOW())=TO_DAYS(delivery_date_customer), 
					CONCAT('<span style=".'"color:#FF0000;font-weight:bold;text-decoration:blink"'.">', delivery_date_customer, '</span>'),
					IF(TO_DAYS(NOW())<TO_DAYS(delivery_date_customer),
						CONCAT('<span style=".'"color:#FF0000;font-weight:bold;"'.">', delivery_date_customer, '</span>')
						,delivery_date_customer)) delivery_date_customer_colored,
			(SELECT max(p.payment_date) FROM payment p WHERE p.auction_number = auction.auction_number AND p.txnid = auction.txnid ) as paid_date 
	    , (select sum(p.amount) from payment p where p.auction_number=auction.auction_number and p.txnid=auction.txnid) paid_amount 
		, invoice.open_amount
		, invoice.invoice_date
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
            FROM auction
            LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
            LEFT JOIN offer
            ON offer.offer_id = auction.offer_id
            WHERE (TO_DAYS(NOW()) <= TO_DAYS(auction.delivery_date_customer)) 
            AND auction.shipping_method = 0
            AND (auction.payment_method in ('$type'))
			$seller_filter_str
			and main_auction_number=0
            AND auction.deleted = 0";

        $r = $dbr->getAll($q);
//        echo $q;
        if (PEAR::isError($r)) {
           aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @desc Find auction not ready to ship
    */
    static function findNotReadyToShip($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE (TO_DAYS(NOW()) - TO_DAYS(auction.delivery_date_customer)  < 0) AND auction.paid = 1
			and main_auction_number=0
		AND auction.shipping_method = 0 AND auction.payment_method in ('1', '2') 
		$seller_filter_str
		AND auction.deleted = 0");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @param string $what Where to seek
    * @param string $term What to seek
    * @desc Find auctions by field value
    */
    static function findBy($db, $dbr, $what, $term)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $term = mysql_escape_string($term);
        if (!strlen($term)) {
            return array();
        }
		$keylist = '';
		$term = trim($term);
		if ($what=='invoice_number') {
			$keylist = " from auction ";
	        $where = " and auction.invoice_number = $term";
		} elseif ($what=='email' && (int)$term && $term==(''.(1*$term))) {
			$keylist = " from auction ";
	        $where = " and auction.customer_id=".(int)$term;
		} elseif ($what=='email') {
			$keylist = " from (select * from auction_par_varchar aupv where TRIM(UPPER(aupv.value)) like TRIM(UPPER('%$term%'))
				 and aupv.key in ('{$what}_invoice','{$what}_shipping','{$what}')) aupv
				 join auction on (auction.auction_number=aupv.auction_number and auction.txnid=aupv.txnid)";
	        $where = "";
		} elseif ($what=='name') {
			$term = str_replace(' ','%',$term);
			$keylist = " from (select aupv1.auction_number, aupv1.txnid 
					from auction_par_varchar aupv1 
					join auction_par_varchar aupv2 on aupv1.auction_number=aupv2.auction_number and aupv1.txnid=aupv2.txnid 
					where aupv1.key = 'firstname_invoice' and aupv2.key = 'name_invoice'
					and UPPER(concat(aupv1.value,' ',aupv2.value)) like UPPER('%$term%')
				union select aupv1.auction_number, aupv1.txnid 
					from auction_par_varchar aupv1 
					join auction_par_varchar aupv2 on aupv1.auction_number=aupv2.auction_number and aupv1.txnid=aupv2.txnid 
					where aupv1.key = 'firstname_shipping' and aupv2.key = 'name_shipping'
					and UPPER(concat(aupv1.value,' ',aupv2.value)) like UPPER('%$term%')
					) aupv
				 join auction on auction.auction_number=aupv.auction_number and auction.txnid=aupv.txnid";
	        $where = "";
		} elseif ($what=='tel') {
			$term = str_replace(' ','%',$term);
			$keylist = " from (select aupv1.auction_number, aupv1.txnid 
					from auction_par_varchar aupv1 
					where aupv1.key = 'tel_invoice_'
					and UPPER(aupv1.value) like UPPER('%$term%')
				union select aupv1.auction_number, aupv1.txnid 
					from auction_par_varchar aupv1 
					where aupv1.key = 'cel_invoice_' 
					and UPPER(aupv1.value) like UPPER('%$term%')
				union select aupv1.auction_number, aupv1.txnid 
					from auction_par_varchar aupv1 
					where aupv1.key = 'tel_shipping_'
					and UPPER(aupv1.value) like UPPER('%$term%')
				union select aupv1.auction_number, aupv1.txnid 
					from auction_par_varchar aupv1 
					where aupv1.key = 'cel_shipping_' 
					and UPPER(aupv1.value) like UPPER('%$term%')
				 ) aupv
				 join auction on auction.auction_number=aupv.auction_number and auction.txnid=aupv.txnid";
	        $where = "";
		} else	{
			$keylist = " from (select * from auction_par_varchar aupv where TRIM(UPPER(aupv.value)) like TRIM(UPPER('%$term%'))
				 and aupv.key in ('{$what}_invoice','{$what}_shipping')) aupv
				 join auction on auction.auction_number=aupv.auction_number and auction.txnid=aupv.txnid";
	        $where = "";
		}	
		$q = "SELECT distinct auction.auction_number
		, auction.txnid
		, auction.username
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.invoice_number
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_Currency(auction.siteid) currency
		, auction.siteid
		, invoice.open_amount
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		, auction.deleted
		$keylist
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON auction.invoice_number=invoice.invoice_number
		WHERE 1
			and main_auction_number=0
			$where 
			$seller_filter_str
		";
//		echo $q; //die();	
        $r = $dbr->getAll($q);//AND process_stage >= " . STAGE_ORDERED);
//		print_r($db, $dbr);
//		print_r($r);
//		echo "SELECT auction.*, offer.name as offer_name FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id WHERE $where ";
        if (PEAR::isError($r)) {
            aprint_r($r);
			return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @desc Find not shipped
    */
    static function findNotShipped($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE ((TO_DAYS(NOW()) - TO_DAYS(auction.delivery_date_customer) > 1) OR auction.delivery_date_customer='0000-00-00') 
			and main_auction_number=0
		AND auction.paid = 1
		AND auction.shipping_method = 0 
		AND auction.payment_method in ('1', '2') 
		$seller_filter_str
		AND auction.deleted = 0");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @desc Find picked up
    */
    static function findPickedUp($db, $dbr, $type)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*, invoice.invoice_date
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction 
		join orders on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON auction.invoice_number=invoice.invoice_number
		WHERE main_auction_number=0
		and invoice.open_amount>0
		$seller_filter_str
		AND auction.deleted = 0 
		AND auction.payment_method='$type'
		group by auction.auction_number, auction.txnid
			having min(orders.ready2pickup)=1
            AND min(orders.sent)=1
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @param string $ebayTime eBay official time (actually GMT)
    * @desc Find type 1 auctions finished in last minute
    */
    static function findJustFinished($db, $dbr, $ebayTime)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findListed expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("
            SELECT auction.*, i.open_amount, pm.email_template_resend, pm.email_template_resend2
            FROM auction
			left join invoice i on auction.invoice_number=i.invoice_number
			left join payment_method pm on auction.payment_method=pm.code
            WHERE end_time <> '0000-00-00 00:00:00' 
			and main_auction_number=0
            AND end_time <= '". $ebayTime."'
            AND auction.offer_id AND auction.process_stage in (" . STAGE_LISTED . ", " . STAGE_RELISTED . ") 
			$seller_filter_str
			AND NOT auction.deleted AND txnid
            ");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return $r;
        }
        return $r;
    }

    

    /**
    * @return array
    * @param object $db
    * @param int $stage
    * @param int $days
    * @desc Find auctions being in certain processing stage for x days
    */
    static function findStage($db, $dbr, $stage, $days)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $stage = mysql_escape_string($stage);
		$q = "SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, pm.email_template_resend, pm.email_template_resend2
			, i.open_amount
		FROM auction 
			left join payment_method pm on auction.payment_method=pm.code
		LEFT JOIN offer ON offer.offer_id = auction.offer_id  
		left join invoice i on i.invoice_number=auction.invoice_number
		WHERE auction.process_stage = '". $stage. "' AND DATE_ADD(status_change, INTERVAL $days DAY) <= NOW() 
			and main_auction_number=0
		$seller_filter_str
		AND NOT auction.deleted";
//		echo "<br>$q<br>";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
           aprint_r($r);
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @desc Finds auctions without associated offer
    */
    static function findOrphaned($db, $dbr, $seller='', $type='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("SELECT 
				offer_name.name alias, 
				au_server.value as server, 
				auction.auction_number, 
				auction.txnid, 
				auction.username, 
				auction.offer_id, 
				auction.saved_id, 
				auction.start_time, 
				auction.end_time,
				'A' as type
			FROM auction
			LEFT JOIN offer_name ON auction.name_id = offer_name.id
			left join auction_par_varchar au_server on auction.auction_number=au_server.auction_number 
						and auction.txnid=au_server.txnid and au_server.key='server'
	      	JOIN seller_information ON seller_information.username=auction.username 
	      	WHERE (process_stage=". STAGE_LISTED ." OR process_stage=". STAGE_RELISTED .") 
	      		AND NOT auction.deleted AND auction.txnid>0 
				AND NOT auction.offer_id 
			".(strlen($seller)?" and auction.username='$seller' ":'')."
			".(strlen($type)?" and 'A'='$type' ":'')."
				$seller_filter_str
		union all
			SELECT 
				offer_name.name alias, 
				auction.server, 
				auction.auction_number, 
				NULL as txnid, 
				auction.username, 
				auction.offer_id, 
				auction.saved_id, 
				auction.start_time, 
				auction.end_time,
				'F' as type
			FROM listings auction
			LEFT JOIN offer_name ON auction.name_id = offer_name.id
	      	JOIN seller_information ON seller_information.username=auction.username 
	      	WHERE IFNULL(finished,0) = 0 and 
				IFNULL(quantity,0) > 0 
				and end_time>now()
				AND NOT auction.offer_id 
			".(strlen($seller)?" and auction.username='$seller' ":'')."
			".(strlen($type)?" and 'F'='$type' ":'')."
				$seller_filter_str
	      ");

//	      AND seller_information.isActive
//        $r = $dbr->getAll("SELECT auction.* FROM auction  WHERE NOT auction.offer_id AND NOT auction.deleted AND auction.txnid>0");
        if (PEAR::isError($r)) {
           aprint_r($r);
            return;
        }
        return $r;
    }

    /**
    * @return void
    * @param string $number Tracking number
    * @param int $method Shipping method ID
    * @desc Adds tracking number
    */
    function addTrackingNumber($number, $method, $date_time, $username='', $packet='NULL')
    {
		if (!$method) return 0;
		$method_obj = new ShippingMethod($this->_db, $this->_dbr, $method);
        $number = trim(str_replace(' ', '', $number));
        $percent_flag = strpos($number, '%');
        if($percent_flag !== false){
            $number = ltrim($number, '%');
        }
        $number_len = strlen($number);
        $tn_cut_start = isset($method_obj->data->tn_cut_start) ? (int) $method_obj->data->tn_cut_start : 0;
        $tn_cut_end = isset($method_obj->data->tn_cut_end) ? (int) $method_obj->data->tn_cut_end : 0;
        
		if ($method==111 && $number_len>14) { //  "DPD, Depot 119/120"
			$number = substr($number, strpos($number,'0118'));
        }
        if ($method==150 && $number_len>14) { //  "DPD Prenzlau Wayfair"
            $number = substr($number, strpos($number,'094459'));
        }
        if ($tn_cut_start > 0 || $tn_cut_end > 0) {
            if($tn_cut_start > 0){
                $number = substr($number, $tn_cut_start);
            }
            if($tn_cut_end > 0){
                $number = substr($number, 0, -$tn_cut_end);
            }
            
            $label = $this->_dbr->getRow("select * from auction_label
				where auction_number = " . $this->data->auction_number . "
					and txnid=" . $this->data->txnid . "
					and tracking_number='$number'
			");
            $pdf = $label->doc;
        }
		if (!$number_len) return 0;
		$tnid = $this->_dbr->getOne("select tn.id
			from tracking_numbers tn
			join tn_orders tno on tn.id=tno.tn_id
			join orders o on tno.order_id=o.id
			join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
			left join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
			where IFNULL(mau.auction_number, au.auction_number)={$this->data->auction_number} and ifnull(mau.txnid, au.txnid)={$this->data->txnid}
			and tn.shipping_method=$method
			and tn.number='$number' limit 1");
		if (!$tnid) {
	        $r = $this->_db->query(
	            "INSERT INTO tracking_numbers SET date_time = '".mysql_escape_string($date_time)."', number='".$number
				."', auction_number = ".$this->data->auction_number
				.", shipping_method=".$method
				.", txnid=".$this->data->txnid
				.", username='".$username."'"
				.", packet_id=$packet"
	        );
	        if (PEAR::isError($r)) {
	           aprint_r($r); die();
	            return;
	        }
			$tnid = mysql_insert_id();
		}
		if (strlen($pdf)) {
			$q = "update tracking_numbers set doc_name='Label.pdf', doc='".mysql_escape_string (($pdf))."'
			where id=".$tnid;
			$r = $this->_db->query($q);
		}
        if (PEAR::isError($r)) {
           aprint_r($r);
            return;
        }
		return $tnid;
    }

    /**
    * @return void
    * @desc Deletes auction
    */
    function delete()
    {
        $r = $this->_db->query("DELETE FROM auction WHERE auction_number = ".$this->data->auction_number." 
			AND txnid=".$this->data->txnid);
    }

    function setDeleted()
    {
     	     global $loggedUser;
//		echo 'this->data->delivery_date_real='.$this->data->delivery_date_real;
/*  	  if ($this->data->delivery_date_real=='0000-00-00 00:00:00') */{
	    require_once 'lib/Order.php';
    	require_once 'lib/Article.php';
	    require_once 'lib/ArticleHistory.php';
    	require_once 'lib/AuctionLog.php';
//		echo $this->data->auction_number.'/'.$this->data->txnid;
	    $order = Order::listAll($this->_db, $this->_dbr, $this->data->auction_number, $this->data->txnid);
//		echo '<br>'.count($order);
	    if (count($order)) foreach ($order as $item) {
			if (!$item->article_id) continue;
	        $article = new Article($this->_db, $this->_dbr, $item->article_id);
	       if ($article->get('admin_id')) {
//	       	  echo '<br>Item '.$item->article_id.' is admin';
	        continue;
	       }; 
			if ($this->data->deleted) $itemquantity = -$item->quantity; // to undelete
				else $itemquantity = $item->quantity; // to delete
	    }
		if ($this->data->deleted) {
			AuctionLog::Log($this->_db, $this->_dbr,  $this->data->auction_number, $this->data->txnid,
				$loggedUser ? $loggedUser->get('username') : 'customer', 'Restored');			
			$this->data->deleted = 0;
		} else {
			AuctionLog::Log($this->_db, $this->_dbr,  $this->data->auction_number, $this->data->txnid,
				$loggedUser ? $loggedUser->get('username') : 'customer', 'Deleted');			
			$this->data->deleted = 1;
		}	
		$this->data->deleted_by = $loggedUser->get('username');
		require_once 'util.php';
		$timediff = $loggedUser->get('timezone');
		$this->data->deleted_date = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
		$this->update();
	  }	
	  $this->_db->query("update auction set deleted=".$this->data->deleted." 
	  	where main_auction_number=".$this->data->auction_number."
	  	and main_txnid=".$this->data->txnid);
    }

    /**
    * @return array
    * @param object $db
    * @desc Find auctions without rating
    */
    static function findNotRated($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $stage = mysql_escape_string($stage);
        $r = $dbr->getCol("
        SELECT concat(auction.auction_number, '/', txnid) 
		FROM auction 
		WHERE (NOT rating_received OR NOT rating_given) 
			and main_auction_number=0
		$seller_filter_str
		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
            return;
        }
        return $r;
    }

    static function findNotRatedReceived($db, $dbr, $sellerusername)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        //$stage = mysql_escape_string($stage);
        $r = $dbr->getCol("
        SELECT concat(auction.auction_number, '/', auction.txnid) FROM auction 
			WHERE (NOT auction.rating_received) 
			and main_auction_number=0
			$seller_filter_str
			AND auction.username='".$sellerusername."'");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findNotRatedGiven($db, $dbr, $sellerusername)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("
        SELECT IF(listings.auction_type_vch,  listings.auction_type_vch, 'Chinese') type, auction.auction_number, auction.txnid 
			FROM auction LEFT JOIN listings ON auction.auction_number=listings.auction_number
			WHERE (auction.rating_received=1 AND NOT auction.rating_given) AND NOT auction.deleted
			and main_auction_number=0
			$seller_filter_str
			AND auction.username='".$sellerusername."'");
        if (PEAR::isError($r)) {
            return;
        }
        return $r;
    }

    /**
    * @return boolean
    * @param array $errors
    * @desc Validate fields
    */
    static function validate(&$errors)
    {
        $errors = array();
        if (!$this->data->auction_number) {
            $errors[] = 'Auction number is required';
        } elseif (!$this->data->txnid) {
            $errors[] = 'Transaction ID is required';
        } elseif ($this->_isNew) {
            $number = mysql_escape_string($this->data->auction_number);
            $r = $this->_db->query(
                "SELECT COUNT(*) AS n FROM auction WHERE auction_number={$this->data->auction_number} 
					AND txnid={$this->data->txnid}"
             );
            $r = $r->fetchRow();
            if ($r->n) {
                $errors[] = 'Duplicate auction number';
            }
        }
        return !count($errors);
    }

	 /**
     * @return boolean
     * @param object $db
     * @desc 
     */
	 static function isShipped($db, $dbr){
         if (!is_a($db, 'MDB2_Driver_mysql')) {
             return;
         }
         $r1 = $this->_db->query(
            "SELECT COUNT(*) AS n1 FROM tracking_numbers WHERE auction_number={$this->data->auction_number} 
					AND txnid={$this->data->txnid}"
         );
         $r1 = $r1->fetchRow();
		 if ($r1->n1) {
             $r2 = $this->_db->query(
                "SELECT COUNT(*) AS n2 FROM tracking_numbers WHERE auction_number={$this->data->auction_number} 
					AND txnid={$this->data->txnid} AND shipping_date = '0000-00-00 00:00:00'"
             );
             $r2 = $r2->fetchRow();
             return !$r2->n2;
         }
		 return 0;
     }
     
    static function findDelayedShiping($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction  
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE shipping_email_delay > 0 
			and main_auction_number=0
		AND DATE_ADD(fget_delivery_date_real(auction.auction_number, auction.txnid), INTERVAL shipping_email_delay HOUR) >=  NOW() 
		$seller_filter_str
		AND auction.deleted=0");
        if (PEAR::isError($r)) {
            return;
        }
        return $r;
    }

    static function countForSearch($db, $dbr, $date='', $username='')
    {
        $r = $dbr->getAssoc("SELECT name, CONCAT(count0, '/', count1) from count_cache where username='$username'");
        if (PEAR::isError($r)) {
            return;
        }
		$res = array();
		foreach($r as $varname => $varvolume) {
            list ($count0, $count1) = explode('/', $varvolume);
			$res[$varname][0] = $count0;
			$res[$varname][1] = $count1;
//			echo $varvolume.': ('.$count0.'-'.$count1.')'.$res[$varname][0].'-'.$res[$varname][1].'<br>';
		};
		return $res;
    }

    static function calc_countForSearch($db, $dbr, $date='', $username='')
    {
	$time = getmicrotime();
	  set_time_limit(0);
	  $r = $dbr->getAssoc("select username f1, username f2 from users where username='$username' or '$username'=''");
	  global $seller_filter;
	  global $seller_accunts;
	  global $seller_filter_str;
echo 'Start: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime(); $time1 = getmicrotime();
	  foreach ($r as $username) {
	$seller_accounts = $dbr->getAssoc("select 'dummy', CONCAT( '''', 'dummy', '''' ) from dual union all 
		SELECT seller_information.username, CONCAT( '''', seller_information.username, '''' ) 
			FROM seller_information 
			left join acl_sellers on seller_information.username=acl_sellers.seller_name 
			left join users u on acl_sellers.username=u.role_id 
		WHERE (u.username = '$username'
			or exists (SELECT 1 FROM `acl_sellers` 
				left join users u1 on acl_sellers.username=u1.role_id 
			WHERE u1.username = '$username' and acl_sellers.seller_name = 'All_sellers')
		)
		");
		$seller_filter = implode(',', $seller_accounts);
		$seller_filter_str = " and auction.username in ($seller_filter) ";
        $uncompleted = array();
        $days = (int)Config::get($db, $dbr, 'uncompleted');
        $uncompleted[0] = $dbr->getOne("SELECT COUNT(*) FROM auction 
			WHERE payment_method = '' AND auction.deleted = 0 AND end_time <> '0000-00-00 00:00:00' AND end_time <= NOW()
			and main_auction_number=0
			$seller_filter_str
			AND process_stage <> ".STAGE_NO_WINNER);
// echo 'uncompleted[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $uncompleted[1] = $dbr->getOne("SELECT COUNT(*) FROM auction 
			WHERE payment_method = '' AND DATE_ADD(end_time, INTERVAL $days DAY) <= NOW() AND auction.deleted = 0 AND end_time <> '0000-00-00 00:00:00'
			and main_auction_number=0
			$seller_filter_str
			AND process_stage <> ".STAGE_NO_WINNER);
// echo 'uncompleted[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $ship_unpaid = array();
        $ship_unpaid[0] = Auction::findUnpaidCount($db, $dbr, -1, 1);
// echo 'ship_unpaid[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $ship_unpaid[1] = Auction::findUnpaidCount($db, $dbr, Config::get($db, $dbr, 'ship_unpaid'), 1);
// echo 'ship_unpaid[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cod_unpaid = array();
        $cod_unpaid[0] = Auction::findUnpaidCount($db, $dbr, -1, 2);
// echo 'cod_unpaid[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cod_unpaid[1] = Auction::findUnpaidCount($db, $dbr, Config::get($db, $dbr, 'cod_unpaid'), 2);
// echo 'cod_unpaid[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$cc_unpaid[0] = count(Auction::findbyPSPNotBooked($db, $dbr));
		$cc_unpaid[1] = count(Auction::findbyPSPNotBooked($db, $dbr,"'VERIFIED'",Config::get($db, $dbr, 'cc_unpaid')));
        $pickup_unpaid = array();
        $pickup_unpaid[0] = count(Auction::findUnpaid($db, $dbr, -1, 4));
// echo 'pickup_unpaid[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $pickup_unpaid[1] = count(Auction::findUnpaid($db, $dbr, Config::get($db, $dbr, 'pickup_unpaid'), 4));
// echo 'pickup_unpaid[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_unpaid = array();
        $cash_unpaid[0] = count(Auction::findUnpaid($db, $dbr, -1, 3));
// echo 'cash_unpaid[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_unpaid[1] = count(Auction::findUnpaid($db, $dbr, Config::get($db, $dbr, 'cash_unpaid'), 3));
// echo 'cash_unpaid[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		
        $ship_ready_to_ship[0] = count(Auction::findReadyToShip($db, $dbr, 1, 0, 0));
// echo 'ship_ready_to_ship[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $days = (int)Config::get($db, $dbr, 'ship_ready_to_ship');
        $ship_ready_to_ship[1] = count(Auction::findReadyToShip($db, $dbr, 1, $days, 0));
// echo 'ship_ready_to_ship[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        
        $cod_ready_to_ship[0] =  Auction::findReadyToShip($db, $dbr, 2, 0, 1);
// echo 'cod_ready_to_ship[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $days = (int)Config::get($db, $dbr, 'cod_ready_to_ship');
        $cod_ready_to_ship[1] = Auction::findReadyToShip($db, $dbr, 2, $days, 1);
// echo 'cod_ready_to_ship[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cc_ready_to_ship[0] =  Auction::findReadyToShip($db, $dbr, "cc_shp", 0, 1);
        $days = (int)Config::get($db, $dbr, 'cc_ready_to_ship');
        $cc_ready_to_ship[1] = Auction::findReadyToShip($db, $dbr, "cc_shp", $days, 1);
        
        $ship_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, 1));
// echo 'ship_wait_to_ship[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cod_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, 2));
        $cc_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, "cc_shp"));
// echo 'cod_wait_to_ship[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $pickup_ready_to_prepare[0] = count(Auction::findPrepareToPickup($db, $dbr, 4, 9999999));
// echo 'pickup_ready_to_prepare[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $pickup_ready_to_prepare[1] = Auction::findPrepareToPickupCount($db, $dbr, 4,  Config::get($db, $dbr, 'ready_to_prepare'));
// echo 'pickup_ready_to_prepare[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_ready_to_prepare[0] = Auction::findPrepareToPickupCount($db, $dbr, 3, 9999999);
// echo 'cash_ready_to_prepare[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_ready_to_prepare[1] = Auction::findPrepareToPickupCount($db, $dbr, 3,  Config::get($db, $dbr, 'cash_ready_to_prepare'));
// echo 'cash_ready_to_prepare[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
         $pickup_wait_to_prepare[0] = count(Auction::findWaitToPrepare($db, $dbr, 4));
// echo 'pickup_wait_to_prepare[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
         $cash_wait_to_prepare[0] = count(Auction::findWaitToPrepare($db, $dbr, 3));
// echo 'cash_wait_to_prepare[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $pickup_ready_to_pickup[0] = Auction::findReadyToPickupCount($db, $dbr, 4);
// echo 'pickup_ready_to_pickup[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_ready_to_pickup[0] = Auction::findReadyToPickupCount($db, $dbr, 3);
// echo 'cash_ready_to_pickup[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $pickup_not_pickedup[0] = Auction::findPrepareToPickupCount($db, $dbr, 3, 999999);
// echo 'pickup_not_pickedup[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
	    $pickup_not_pickedup[1] = Auction::findPrepareToPickupCount($db, $dbr, 3, Config::get($db, $dbr, 'not_pickedup'));
// echo 'pickup_not_pickedup[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_not_pickedup[0] = count(Auction::findNotPickedUp($db, $dbr, 99999, 3));
// echo 'cash_not_pickedup[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_not_pickedup[1] = count(Auction::findNotPickedUp($db, $dbr, Config::get($db, $dbr, 'cash_not_pickedup'), 3));
// echo 'cash_not_pickedup[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $pickup_pickedup[0] = count(Auction::findPickedUp($db, $dbr, 4));
// echo 'pickup_pickedup[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $cash_pickedup[0] = count(Auction::findPickedUp($db, $dbr, 3));
// echo 'cash_pickedup[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
         $deleted[0] = $dbr->getOne("SELECT COUNT(*) FROM auction WHERE deleted=1 $seller_filter_str");
// echo 'deleted[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $rma_opened[0] = Auction::findOpenedRMACount($db, $dbr, 99999);
// echo 'rma_opened[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $rma_opened[1] = $rma_opened[0]-Auction::findOpenedRMACount($db, $dbr, Config::get($db, $dbr, 'open_tickets'), 1);
// echo 'rma_opened[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $listingfees_opened[0] = count(Auction::findOpenedListingFee($db, $dbr, 99999));
// echo 'listingfees_opened[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
        $listingfees_closed[0] = count(Auction::findClosedListingFee($db, $dbr));
// echo 'listingfees_closed[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$bl_edd[0] = count(Auction::findBLEDD($db, $dbr));
// echo 'bl_edd[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$bl_edd[1] = count(Auction::findBLEDD($db, $dbr, Config::get($db, $dbr, 'bl_edd')));
// echo 'bl_edd[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$trucking_eda[0] = count(Auction::findTruckingEDA($db, $dbr));
// echo 'trucking_eda[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$trucking_eda[1] = count(Auction::findTruckingEDA($db, $dbr, Config::get($db, $dbr, 'trucking_eda')));
// echo 'trucking_eda[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$notsold1[0] = count(Auction::findNotSold($db, $dbr, 1, $date));
// echo 'notsold1[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$notsold2[0] = count(Auction::findNotSold($db, $dbr, 2, $date));
// echo 'notsold2[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$ins_open[0] = count(Auction::findInsuranceOpened($db, $dbr));
// echo 'ins_open[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$ins_open[1] = $ins_open[0]-count(Auction::findInsuranceOpened($db, $dbr, Config::get($db, $dbr, 'ins_open')));
// echo 'ins_open[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$sh_open[0] = count(Auction::findShipmentOpened($db, $dbr));
// echo 'sh_open[0]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$sh_open[1] = $sh_open[0]-count(Auction::findShipmentOpened($db, $dbr, Config::get($db, $dbr, 'sh_open')));
// echo 'sh_open[1]: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
		$ppp_not_booked[0] = count(Auction::findbyPPPNotBooked($db, $dbr));
        $inst_shp_ready_to_ship[0] = count(Auction::findReadyToShip($db, $dbr, 'inst_shp', 0, 0));
        $inst_shp_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, 'inst_shp'));
        $inst_shp_unpaid[0] = Auction::findUnpaidCount($db, $dbr, 0, 'inst_shp');
        $bill_ready_to_ship[0] = count(Auction::findReadyToShip($db, $dbr, 'bill_shp', 0, 0));
        $bill_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, 'bill_shp'));
        $bill_unpaid[0] = count(Auction::findbyPSPNotBooked($db, $dbr, "'VERIFIED'", 0, 'bill'));
        $invoice_ready_to_ship[0] = count(Auction::findReadyToShip($db, $dbr, 'invoice', 0, 0));
        $invoice_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, 'invoice'));
        $invoice_unpaid[0] = count(Auction::findbyPSPNotBooked($db, $dbr, "'VERIFIED'", 0, 'bill'));
        $ebay_shp_ready_to_ship[0] = count(Auction::findReadyToShip($db, $dbr, 'ebay_shp', 0, 0));
        $ebay_shp_wait_to_ship[0] = count(Auction::findWaitToShip($db, $dbr, 'ebay_shp'));
        $ebay_shp_unpaid[0] = count(Auction::findUnpaid1($db, $dbr, 0, 'ebay_shp',0,9999999,''));
         $data = compact(
		 	"uncompleted",
			"ship_unpaid",
			"cod_unpaid",
			"pickup_unpaid",
			"cash_unpaid",
			"ship_ready_to_ship",
			"cod_ready_to_ship",
			"cc_ready_to_ship",
			"ship_wait_to_ship",
			"cod_wait_to_ship",
			"cc_wait_to_ship",
			"pickup_ready_to_prepare",
			"cash_ready_to_prepare",
			"pickup_wait_to_prepare",
			"cash_wait_to_prepare",
			"pickup_ready_to_pickup",
			"cash_ready_to_pickup",
			"pickup_not_pickedup",
			"cash_not_pickedup",
			"pickup_pickedup",
			"cash_pickedup", 
			"deleted", 
			"ticket_opened", 
			"listingfees_opened", 
			"listingfees_closed",
			"bl_edd",
			"trucking_eda",
			"notsold1",
			"notsold2",
			"ins_open",
			"sh_open",
			"ppp_not_booked",
			"cc_unpaid",
            "inst_shp_ready_to_ship",
            "inst_shp_wait_to_ship",
            "inst_shp_unpaid",
            "bill_ready_to_ship",
            "bill_wait_to_ship",
            "bill_unpaid",
            "invoice_ready_to_ship",
            "invoice_wait_to_ship",
            "invoice_unpaid",
            "ebay_shp_ready_to_ship",
            "ebay_shp_wait_to_ship",
            "ebay_shp_unpaid"
			);
		$db->query("DELETE FROM count_cache where username='$username'");
		foreach ($data as $varname => $varvalue) {
			$db->query("INSERT INTO count_cache SET name='".$varname."', 
			count0='".(isset($varvalue[0]) ? $varvalue[0] : 'NULL')."', 
			count1='".(isset($varvalue[1]) ? $varvalue[1] : 'NULL')."',
			username='$username'
			");
		};
	  }; //  for every user
echo 'Total: '.(getmicrotime()-$time1).'<br>';
    }

    static function findBLEDD($db, $dbr, $days=99999)
    {
	  global $seller_filter_str;
        $r = $dbr->getAll("SELECT auction.auction_number, auction.txnid, auction.username, auction.end_time, 
			auction.freeze_date
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
			listingfees.open_date as listingfees_open_date, 
			listingfees.close_date as listingfees_close_date, 
			listingfees.amount as open_amount
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join listingfees on auction.auction_number=listingfees.auction_number and auction.txnid=listingfees.txnid
		WHERE listingfees.close_date is null and (UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( listingfees.open_date )) <= $days*24*60*60
		$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findTruckingEDA($db, $dbr, $days=99999)
    {
	  global $seller_filter_str;
        $r = $dbr->getAll("SELECT auction.auction_number, auction.txnid, auction.username, auction.end_time, 
			auction.freeze_date
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
			listingfees.open_date as listingfees_open_date, 
			listingfees.close_date as listingfees_close_date, 
			listingfees.amount as open_amount
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join listingfees on auction.auction_number=listingfees.auction_number and auction.txnid=listingfees.txnid
		WHERE listingfees.close_date is null and (UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( listingfees.open_date )) <= $days*24*60*60
		$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findOpenedListingFee($db, $dbr, $days=99999)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (!(int)$days) $days=9999;
        $r = $dbr->getAll("SELECT auction.auction_number, auction.txnid, auction.username, auction.end_time, 
			auction.freeze_date
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
			listingfees.open_date as listingfees_open_date, 
			listingfees.close_date as listingfees_close_date, 
			listingfees.amount as open_amount
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join listingfees on auction.auction_number=listingfees.auction_number and auction.txnid=listingfees.txnid
		WHERE listingfees.close_date is null and (UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( listingfees.open_date )) <= $days*24*60*60
			and main_auction_number=0
			$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findClosedListingFee($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (!(int)$days) $days=9999;
        $r = $dbr->getAll("SELECT auction.auction_number, auction.txnid, auction.username, auction.end_time, 
			auction.freeze_date
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name,
			listingfees.open_date as listingfees_open_date, 
			listingfees.close_date as listingfees_close_date, 
			listingfees.amount as open_amount
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join listingfees on auction.auction_number=listingfees.auction_number and auction.txnid=listingfees.txnid
		WHERE listingfees.close_date is not null
			and main_auction_number=0
			$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findOpenedRMA($db, $dbr, $days=99999, $date_to_complain=0)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($date_to_complain) $date_to_complain=' OR UNIX_TIMESTAMP( rma.date_to_complain ) > UNIX_TIMESTAMP( ) ';
		else $date_to_complain='';
		if (!(int)$days) $days=9999;
		$q = "SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, rma.create_date as rma_create_date, rma.date_to_complain as rma_date_to_complain, 
		rma.rma_id, f_rma_last_change(rma.rma_id) last_change 
		, fget_Currency(auction.siteid) currency
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
		WHERE rma.close_date is null and ((UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( rma.create_date )) <= $days*24*60*60 $date_to_complain)
			and main_auction_number=0
			$seller_filter_str
		";
//		echo $q; die();
        $r = $dbr->getAll($q);
//		time() - strtotime($date) > $days*24*60*60
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findOpenedRMACount($db, $dbr, $days=99999, $date_to_complain=0)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($date_to_complain) $date_to_complain=' OR UNIX_TIMESTAMP( rma.date_to_complain ) > UNIX_TIMESTAMP( ) ';
		else $date_to_complain='';
		if (!(int)$days) $days=9999;
		$q = "SELECT count(*)
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
		WHERE rma.close_date is null and ((UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( rma.create_date )) <= $days*24*60*60 $date_to_complain)
			and main_auction_number=0
			$seller_filter_str
		";
        $r = $dbr->getOne($q);
//		time() - strtotime($date) > $days*24*60*60
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findBySeller($db, $dbr, $sellername, $pars, $from=0, $to=9999999, $sort)
    {
		$open_amount = $pars['open_amount'];
		$print = $pars['print'];
		$username = $pars['username'];
		$shipping_mode = $pars['shipping_mode'];
		$date_from = $pars['date_from'];
		$date_to = $pars['date_to'];
		$country_shipping = $pars['country_shipping'];
		$wares_res = $pars['wares_res'];
		$route_id = $pars['route_id'];
		$route_unassigned = $pars['route_unassigned'];
		$gmap_bonus = $pars['gmap_bonus'];
		$gmap_date = $pars['gmap_date'];
		$gmap_task = $pars['gmap_task'];
		$gmap_confirm = $pars['gmap_confirm'];
		$timestamp = $pars['timestamp'];
		$exported = $pars['exported'];
		$exported_format = $pars['exported_format'];
		$ids = $pars['ids'];
		$article_id = $pars['article_id'];
		$deleted = $pars['deleted'];
		$where = '';
		if (strlen($username)) $where .= " and auction.shipping_username in ('$username'/*,'_driver'*/) ";
			else $where .= " and auction.shipping_username is not null ";
// for by country
		$country_code = $pars['country_code'];
		$warehouse_id = $pars['warehouse_id'];
		if (strlen($country_code)) $where .= " and au_sh_country.value='".countryCodeToCountry($country_code)."' ";
		$warehouse_id = (int)$warehouse_id;
		if (strlen($shipping_username)) $where .= " and auction.shipping_username='$shipping_username' ";
		if (strlen($country_code) || $warehouse_id) {
			$join = " 		, (select GROUP_CONCAT(concat(w.name,': ',ROUND(awd.distance),' km, ',ROUND(awd.duration),' mins') SEPARATOR '<br>')
		from warehouse w 
		left JOIN auction_warehouse_distance awd ON w.warehouse_id=awd.warehouse_id 
		where (w.country_code='$country_code' or '$country_code'='') and (w.warehouse_id = $warehouse_id or $warehouse_id=0)
		and awd.auction_number=auction.auction_number and awd.txnid=auction.txnid
		) warehouses
			";
		}

	  global $seller_filter_str;
	  global $warehouses;
	  $warehouses_array = Warehouse::listArray($db, $dbr);
	  global $loggedUser;
	  global $smarty;
	require_once 'lib/EmailLog.php';
	require_once 'lib/Config.php';
        $days = (int)$days;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (strlen($deleted)) {
			$deleted = (int)$deleted;
			$where .= " and auction.deleted = $deleted ";
		}
		if (strlen($open_amount)) {
			$where .= " and invoice.open_amount".($open_amount?'>':'<=')."0";
		}
		if ($shipping_mode==-1) 
			$where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=0 ";
		elseif ($shipping_mode==-2) 
			$where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=0 ";
		elseif ($shipping_mode==-3) 
			$where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=0 
						and auction.delivery_date_customer<=NOW()
						and auction.delivery_date_customer<>'0000-00-00' ";
		elseif ($shipping_mode==1) 
			$where .= " /*and auction.payment_method<>2*/ and fget_ASent(auction.auction_number, auction.txnid)=1 ";
		elseif ($shipping_mode==-4) 
			$where .= " and auction.payment_method=2 and invoice.open_amount>0 ";
		elseif ($shipping_mode==-5) 
			$where .= " and auction.payment_method=2 and invoice.open_amount<=0 ";
		if (strlen($date_from)) 
			$where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid)>='$date_from' ";
		if (strlen($date_to)) 
			$where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid)<='$date_to' ";
		if ($route_id==-2) {
			if ($route_unassigned) {
				$where .= " and IFNULL(auction.route_id,0) = 0 ";
			} else {
				$where .= " and 0 ";
			}
		} elseif ($route_id==-1) {
			if ($route_unassigned) {
				$where .= " ";
			} else {
				$where .= " and IFNULL(auction.route_id,0) ";
			}
		} elseif ($route_id>0) {
			if ($route_unassigned) {
				$where .= " and (auction.route_id = $route_id or IFNULL(auction.route_id,0) = 0)";
			} else {
				$where .= " and auction.route_id = $route_id ";
			}
		}
		if (strlen($gmap_confirm)) {
			if ($route_id>0 || $route_id==-2) 
				$where .= " and (auction.route_id = $route_id 
					or ".($gmap_confirm?'':'NOT')." shipping_order_datetime_confirmed) ";
		}
		if (strlen($gmap_bonus)) {
			if ($route_id>0 || $route_id==-2) 
				$where .= " and (auction.route_id = $route_id or ".($gmap_bonus?'':'NOT')." 
					exists (select null from orders where manual=2
					and auction_number=auction.auction_number and txnid=auction.txnid
					and /*article_id in (select bonus_id from route_delivery_type_bonus where delivery_type_id=0)*/
											sb.show_in_table
					)) ";
		}
		if (strlen($gmap_task)) {
			if ($route_id>0 || $route_id==-2) 
				$where .= " and (auction.route_id = $route_id or ".($gmap_task?'':'NOT')." 
					exists (select null from orders where manual 
					and auction_number=auction.auction_number and txnid=auction.txnid
					and article_id = '')) ";
		}
		if (strlen($gmap_date)) {
			if ($route_id>0 || $route_id==-2) 
				$where .= " and (auction.route_id = $route_id or days_due".($gmap_date?'<':'>=')." 0 ) ";
		}
		if (strlen($timestamp)) {
			if ($route_id>0 || $route_id==-2) 
				$where .= " and ".($timestamp?'':'not')." exists (select null from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
						and action='Print packing PDF') ";
		}
		if (strlen($pars['exported'])) {
			if ($route_id>0 || $route_id==-2) 
				$where .= " and ".($pars['exported']?'':'not')." exists (select null from auction_sh_comment 
					where auction_number=auction.auction_number and txnid=auction.txnid
						".(strlen($pars['exported_mindate'])? "and date(create_date)>='".mysql_escape_string($pars['exported_mindate'])."'":'')."
						".(strlen($pars['exported_maxdate'])? "and date(create_date)<='".mysql_escape_string($pars['exported_maxdate'])."'":'')."
						".(strlen($exported_format)?"and `comment`='exported to ".$exported_format."'"
						:" and `comment` like 'exported to %'").") ";
		}
		if (count($ids)) {
			$where .= " and auction.id in (".implode(',',$ids).")";
		}
		if (strlen($article_id)) {
			$where .= " and exists (select null from orders where article_id='$article_id' and manual=0 and sent=0
				and auction_number=auction.auction_number and txnid=auction.txnid) ";
		}
		if (strlen($wares_res) && (int)$wares_res) 
			$where .= " and exists (select null from orders where reserve_warehouse_id=$wares_res 
				and auction_number=auction.auction_number and txnid=auction.txnid) ";
		$country_shipping = CountryCodeToCountry($country_shipping);
		if (strlen($country_shipping)) 
			$where .= " and au_sh_country.value='$country_shipping' ";

        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$q = "SELECT SQL_CALC_FOUND_ROWS auction.id, auction.siteid, auction.printed, auction.deleted, auction.invoice_number, auction.auction_number, auction.txnid, auction.end_time,
				IF(fget_ASent(auction.auction_number, auction.txnid)=0 and auction.delivery_date_customer<>'0000-00-00'
				 , CONCAT('<font color=\"red\"><b>', auction.delivery_date_customer, '</b></font>'), fget_delivery_date_real(auction.auction_number, auction.txnid)) delivery_date_real, IFNULL(users.name, auction.responsible_uname) responsible_uname
				, IFNULL(offer.name,
						(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
							join auction a1 on a1.offer_id=o1.offer_id
							where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				 , IFNULL(invoice.open_amount,0) open_amount, IF(fget_ASent(auction.auction_number, auction.txnid)=0, '', 'gray') as colour
				,(select min(payment_date) from payment where auction_number=auction.auction_number and txnid=auction.txnid) as first_payment_date
				,(select min(log_date) from printer_log where auction_number=auction.auction_number and txnid=auction.txnid) as first_printing_date
			, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN users ON users.username = auction.responsible_uname
		WHERE auction.username='$sellername' 
			and main_auction_number=0
			$where
			$seller_filter_str
			$sort
			LIMIT $from, $to";

//		echo $q; //die();
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			echo $q; die();
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findBySellerCount($db, $dbr, $sellername, $shipping_mode)
    {
	  global $seller_filter_str;
		if ($shipping_mode==-1) 
			$where .= " and fget_ASent(auction.auction_number, auction.txnid)=0 ";
		elseif ($shipping_mode==1) 
			$where .= " and (fget_ASent(auction.auction_number, auction.txnid)=1 or auction.deleted=1) ";
        $cnt = $dbr->getOne("SELECT count(*) FROM auction 
		JOIN invoice ON auction.invoice_number = invoice.invoice_number
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE auction.username='$sellername'
			and main_auction_number=0
			$where
			$seller_filter_str
		");
        return $cnt;
    }

    static function findBySellerPrice($db, $dbr, $sellername, $winning_bid, $siteid, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$q = "SELECT auction.winning_bid, auction.username, auction.siteid, auction.printed, auction.deleted, auction.invoice_number, auction.auction_number, auction.txnid, auction.end_time,
				IF(fget_ASent(auction.auction_number, auction.txnid)=0 and auction.delivery_date_customer<>'0000-00-00'
				 , CONCAT('<font color=\"red\"><b>', auction.delivery_date_customer, '</b></font>'), fget_delivery_date_real(auction.auction_number, auction.txnid)) delivery_date_real, IFNULL(users.name, auction.responsible_uname) responsible_uname
				, IFNULL(offer.name,
						(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
							join auction a1 on a1.offer_id=o1.offer_id
							where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				 , IFNULL(invoice.open_amount,0) open_amount, IF(fget_ASent(auction.auction_number, auction.txnid)=0, '', 'gray') as colour
				,(select min(payment_date) from payment where auction_number=auction.auction_number and txnid=auction.txnid) as first_payment_date
				,(select min(log_date) from printer_log where auction_number=auction.auction_number and txnid=auction.txnid) as first_printing_date
			, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN users ON users.username = auction.responsible_uname
		WHERE auction.username='$sellername' 
			and main_auction_number=0
			and exists (select null from orders where price=$winning_bid and auction_number=auction.auction_number and txnid=auction.txnid)
			and auction.siteid=$siteid
			$seller_filter_str
			$sort
			LIMIT $from, $to";
//		echo $q;	
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
		aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findBySellerPriceCount($db, $dbr, $sellername, $winning_bid, $siteid)
    {
	  global $seller_filter_str;
        $cnt = $dbr->getOne("SELECT count(*) FROM auction 
		JOIN invoice ON auction.invoice_number = invoice.invoice_number
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE auction.username='$sellername'
			and main_auction_number=0
			and exists (select null from orders where price=$winning_bid and auction_number=auction.auction_number and txnid=auction.txnid)
			and auction.siteid=$siteid
			$seller_filter_str
		");
        return $cnt;
    }

    static function findByBuyer($db, $dbr, $buyername)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.auction_number
		, auction.txnid
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, IFNULL(invoice.open_amount,0) open_amount
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
        LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number 
		WHERE UPPER(auction.username_buyer)=UPPER('$buyername')
			and main_auction_number=0
			$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findByOffer($db, $dbr, $offer_id, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.auction_number
		, auction.txnid
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid
		, auction.winning_bid
		, auction.deleted
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, CONCAT('<a target=\'_blank\' href=\'offer.php?id=',auction.offer_id,'\'>',auction.offer_id,'</a>') a_offer_id,
		invoice.total_price + invoice.total_shipping + invoice.total_cod as total_price,
		(SELECT sum(
			ac.price_sold 
			- ac.ebay_listing_fee 
			- ac.additional_listing_fee 
			- ac.ebay_commission 
			- ac.vat 
			- ac.purchase_price 
			+ ac.shipping_cost 
			- ac.effective_shipping_cost 
			+ ac.COD_cost 
			- ac.effective_COD_cost)
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid) as total_profit
			, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE auction.offer_id=$offer_id
			$seller_filter_str
			$sort
			LIMIT $from, $to
		");
//		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByOfferCount($db, $dbr, $offer_id)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getOne("SELECT count(*)
		FROM auction 
		WHERE auction.offer_id=$offer_id
			$seller_filter_str
		");
//		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByShop($db, $dbr, $shop_id, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (!$shop_id) return;
		$shop = $dbr->getRow("select url, name, id from shop where id=".(int)$shop_id);
		$q="SELECT auction.auction_number
		, auction.txnid
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid
		, auction.winning_bid
		, auction.deleted
		,i.total_price + i.total_shipping + i.total_cod + i.total_cc_fee as total_price
		,i.total_price + i.total_shipping + i.total_cod + i.total_cc_fee - i.open_amount as paid_amount
		, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, '{$shop->name}' shop_name
		,IF(fget_ASent(auction.auction_number, auction.txnid)=0, '', 'gray') as colour
		, au_city_invoice.value as city_invoice
		, au_zip_invoice.value as zip_invoice
		, CONCAT(au_firstname_invoice.value,' ',au_name_invoice.value) as name_invoice
		FROM auction 
		join auction_par_varchar au_server on auction.auction_number=au_server.auction_number 
			and auction.txnid=au_server.txnid and au_server.key='server' and au_server.value in ('{$shop->url}', 'www.{$shop->url}')
		left join invoice i on auction.invoice_number=i.invoice_number
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		left join auction_par_varchar au_zip_invoice on auction.auction_number=au_zip_invoice.auction_number 
			and auction.txnid=au_zip_invoice.txnid and au_zip_invoice.key='zip_invoice'
		left join auction_par_varchar au_city_invoice on auction.auction_number=au_city_invoice.auction_number 
			and auction.txnid=au_city_invoice.txnid and au_city_invoice.key='city_invoice'
		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number 
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		WHERE 1
			and main_auction_number=0
			$seller_filter_str
			$sort
			LIMIT $from, $to
		";
//		echo $q;
        $r = $dbr->getAll($q);
//		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByShopCount($db, $dbr, $shop_id)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getOne("SELECT count(*)
		FROM auction 
		left join auction_par_varchar au_server on auction.auction_number=au_server.auction_number 
			and auction.txnid=au_server.txnid and au_server.key='server'
		LEFT JOIN shop ON au_server.value like CONCAT('%',shop.url,'%')
		WHERE 1
			and main_auction_number=0
			and (shop.id=$shop_id or $shop_id=0) 
			and (shop.id is not null or auction.txnid=3)
			$seller_filter_str
		");
//		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
            return;
        }
        return $r;
    }

/*    static function findByOfferName($db, $dbr, $offer_name)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*, offer.name as offer_name, 
		invoice.total_price + invoice.total_shipping + invoice.total_cod as total_price,
		(SELECT sum(
			ac.price_sold 
			- ac.ebay_listing_fee 
			- ac.ebay_commission 
			- ac.vat 
			- ac.purchase_price 
			+ ac.shipping_cost 
			- ac.effective_shipping_cost 
			+ ac.COD_cost 
			- ac.effective_COD_cost)
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid) as total_profit
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE offer.name='$offer_name'
		AND NOT auction.deleted");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }*/

    static function findRMAByOfferName($db, $dbr, $offer_name, $day=0, $problem_id=0, $method=0, $endtimeday=0, $mode, $pics=1, $mindate, $maxdate, $ss, $from=0, $to=9999999, $sort = 'DESC', $offer_id = 0, $source_seller_ids = [], $article_id = '')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = '';
        
        if ($offer_id && is_array($offer_id)) {
            $offer_id = implode(',', array_map('intval', $offer_id));
            $where .= " and offer.offer_id IN ($offer_id) ";
        }
        else if ($offer_id) {
            $offer_id = (int)$offer_id;
            $where .= " and offer.offer_id='$offer_id' ";
        }
        else if ($offer_name) {
            $offer_name = mysql_real_escape_string($offer_name);
            $where .= " and offer.name='$offer_name' ";
        }
        
        if ($source_seller_ids) {
            if (is_array($source_seller_ids)) {
                $where .= " AND IFNULL(mau.source_seller_id, auction.source_seller_id) IN ( " . implode(',', $source_seller_ids) . " ) ";
            }
            else {
                $where .= " AND auction.username = '$source_seller_ids' ";
            }
        }
        
        if ($pics != '') {
            $where .= ($pics==1 ? " exists " : " not exists ")
				." (select null from rma_pic where rma_pic.rma_id=rma.rma_id 
					and rma_pic.is_file=0 and rma_pic.hidden=0)";
        }
		if (is_array($day)) {
			if ($day['date_from']!="''" && $day['date_to']!="''") {
				$where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid) 
					between ".$day['date_from']." and ".$day['date_to']." ";
			}
			if ($mode!='all') {
				$q = "
					select auction.id, IFNULL(CONCAT(auction.id, ',', mau.id), auction.id) from orders 
						join article on orders.article_id=article.article_id and article.admin_id=orders.manual
						join auction on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
						left join auction mau on auction.main_auction_number=mau.auction_number and auction.main_txnid=mau.txnid
						left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
						and total_log.tableid=orders.id and total_log.New_value=1
						where orders.manual=0 and updated between ".$day['date_from']." and ".$day['date_to']."
						group by IFNULL(mau.id, auction.id)
						having count(distinct auction.id) ".($mode=='single'?'=':'>')." 1
						";
				$multi_au = implode(',', $dbr->getAssoc($q));
				if (strlen($multi_au)) $where .= " and auction.id in ($multi_au)"; else $multi_au = ' and 0 ';
			}
		} elseif ($day) {
			$where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid) 
				between DATE_SUB(NOW(), interval $day DAY) and NOW() ";
		}
        if ($mindate) {
            $where .= " and rma.create_date >='" . mysql_escape_string($mindate) . "'";
        }
        if ($maxdate) {
            $where .= " and rma.create_date <='" . mysql_escape_string($maxdate) . "'";
        }
		if ($problem_id) {
			$where .= " and exists(select null from rma_spec where rma_id=rma.rma_id and problem_id=$problem_id) ";
		}
		if ($ss) {
			$where .= " and auction.source_seller_id=$ss ";
		}
		if ($method) {
			$where .= " and exists (select null from tracking_numbers tn 
				where auction.auction_number=tn.auction_number AND auction.txnid=tn.txnid
				and tn.shipping_method = $method )";
		}
		if (strlen($article_id)) {
			$where .= " and rma_spec.article_id = $article_id";
		}
		if ($endtimeday)
			$where .= " and rma.create_date between DATE_SUB(DATE(NOW()), interval $endtimeday DAY) and NOW() ";
		$q = "SELECT SQL_CALC_FOUND_ROWS auction.auction_number
		, auction.txnid
		, mau.auction_number as main_auction_number
		, mau.txnid as main_txnid
		, rma.rma_id
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid 
		, auction.winning_bid
		, auction.deleted
		, CONCAT('<a target=\'_blank\' href=\'offer.php?id=',auction.offer_id,'\'>',auction.offer_id,'</a>') a_offer_id
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as total_price
		, IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as open_amount
		, IFNULL((SELECT sum(ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price
					+ ac.shipping_cost -ac.vat_shipping - ac.effective_shipping_cost 
					+ ac.COD_cost -ac.vat_COD - ac.effective_COD_cost
					- ac.packing_cost/ac.curr_rate), 2))
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid), 0) as total_profit
		,(SELECT GROUP_CONCAT(email_log.date ORDER BY email_log.date DESC SEPARATOR '<br>') FROM email_log WHERE email_log.template like '%mass_email%' 
		and email_log.auction_number = auction.auction_number AND email_log.txnid = auction.txnid) as massemail_datetime
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		, offer_name.name as alias_name
		, fget_Currency(auction.siteid) currency
		FROM rma 
		join rma_spec on rma_spec.rma_id=rma.rma_id
		join auction on rma.auction_number=auction.auction_number and rma.txnid=auction.txnid
		left join auction mau on auction.main_auction_number=mau.auction_number and auction.main_txnid=mau.txnid
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = IFNULL(mau.offer_id, auction.offer_id) 
		LEFT JOIN offer_name ON auction.name_id = offer_name.id
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE 1 $where
			$seller_filter_str
                
                GROUP BY auction.id
			$sort
			LIMIT $from, $to
		";
        $r = $dbr->getAll($q);

//		echo "<pre>$q</pre>";
        
        if (PEAR::isError($r)) {
			aprint_r($r);
			$sort = '';
        } else  return $r;
    }

    static function findByOfferName($db, $dbr, $offer_name, $day=0, $endtimeday=0, $mode='all', $username=array(), $ss, $from=0, $to=9999999, $sort = 'DESC', $offer_id = 0, $source_seller_ids = [], $article_id = '')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = '';
        
        if ($offer_id && is_array($offer_id)) {
            $offer_id = implode(',', array_map('intval', $offer_id));
            $where .= " and offer.offer_id IN ($offer_id) ";
        }
        else if ($offer_id) {
            $offer_id = (int)$offer_id;
            $where .= " and offer.offer_id='$offer_id' ";
        }
        else if ($offer_name) {
            $offer_name = mysql_real_escape_string($offer_name);
            $where .= " and offer.name='$offer_name' ";
        }
        
		if (is_array($day)) {
			if ($day['date_from']!="''" && $day['date_to']!="''") {
				$where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid) 
					between ".$day['date_from']." and ".$day['date_to']." ";
			}
			if ($mode!='all') {
				$q = "
					select auction.id, IFNULL(CONCAT(auction.id, ',', mau.id), auction.id) from orders 
						join article on orders.article_id=article.article_id and article.admin_id=orders.manual
						join auction on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
						left join auction mau on auction.main_auction_number=mau.auction_number and auction.main_txnid=mau.txnid
						left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
						and total_log.tableid=orders.id and total_log.New_value=1
						where orders.manual=0 and updated between ".$day['date_from']." and ".$day['date_to']."
						group by IFNULL(mau.id, auction.id)
						having count(distinct auction.id) ".($mode=='single'?'=':'>')." 1
						";
				$multi_au = implode(',', $dbr->getAssoc($q));
				if (strlen($multi_au)) $where .= " and auction.id in ($multi_au)"; else $multi_au = ' and 0 ';
//				echo $q.'<br>'.$multi_au.'<br>';
			}
		} elseif ($day) {
			$where .= " and fget_delivery_date_real(auction.auction_number, auction.txnid) 
				between DATE_SUB(NOW(), interval $day DAY) and NOW() ";
		}
		if ($endtimeday)
			$where .= " and auction.end_time between DATE_SUB(NOW(), interval $endtimeday DAY) and NOW() ";
		if (count($username))
			$where .= " and auction.username in ('".implode("','",$username)."')";
		
        if ($source_seller_ids) {
            if (is_array($source_seller_ids)) {
                $where .= " AND IFNULL(mau.source_seller_id, auction.source_seller_id) IN ( " . implode(',', $source_seller_ids) . " ) ";
            }
            else {
                $where .= " AND auction.username = '$source_seller_ids' ";
            }
        }
        
		if ($ss) {
			$where .= " and auction.source_seller_id=$ss ";
		}
        
		if (strlen($article_id)) {
			$where .= " and $article_id in (o.article_id, mo.article_id)";
		}

		$q = "SELECT SQL_CALC_FOUND_ROWS auction.auction_number
		, auction.txnid
		, mau.auction_number as main_auction_number
		, mau.txnid as main_txnid
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid 
		, auction.winning_bid
		, auction.deleted
		, CONCAT('<a target=\'_blank\' href=\'offer.php?id=',auction.offer_id,'\'>',auction.offer_id,'</a>') a_offer_id
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as total_price
		, IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as open_amount
		, IFNULL((SELECT sum(ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price
					+ ac.shipping_cost -ac.vat_shipping - ac.effective_shipping_cost 
					+ ac.COD_cost -ac.vat_COD - ac.effective_COD_cost
					- ac.packing_cost/ac.curr_rate), 2))
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid), 0) as total_profit
		,(SELECT GROUP_CONCAT(email_log.date ORDER BY email_log.date DESC SEPARATOR '<br>') FROM email_log WHERE email_log.template like '%mass_email%' 
		and email_log.auction_number = auction.auction_number AND email_log.txnid = auction.txnid) as massemail_datetime
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		, offer_name.name as alias_name
		, fget_Currency(auction.siteid) currency
		FROM auction 
		left join orders o on o.auction_number=auction.auction_number and o.txnid=auction.txnid and o.manual=0
		left join auction mau on auction.main_auction_number = mau.auction_number and auction.main_txnid=mau.txnid
		left join orders mo on mo.auction_number=mau.auction_number and mo.txnid=mau.txnid and mo.manual=0
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN offer_name ON auction.name_id = offer_name.id
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE 1 $where
			$seller_filter_str
			$sort
			LIMIT $from, $to
		";
		echo "<pre>$q</pre>";
        
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
			$sort = '';
        } 
        else {
            return $r;
        }
        
        
        $r = $dbr->getAll("SELECT SQL_CALC_FOUND_ROWS auction.auction_number
		, auction.txnid
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid 
		, auction.winning_bid
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as total_price
		, IFNULL((SELECT sum(ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price
					+ ac.shipping_cost -ac.vat_shipping - ac.effective_shipping_cost 
					+ ac.COD_cost -ac.vat_COD - ac.effective_COD_cost
					- ac.packing_cost/ac.curr_rate), 2))
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid), 0) as total_profit
		,(SELECT GROUP_CONCAT(email_log.date ORDER BY email_log.date DESC SEPARATOR '<br>') FROM email_log WHERE email_log.template like '%mass_email%'  
		and email_log.auction_number = auction.auction_number AND email_log.txnid = auction.txnid ) as massemail_datetime
		, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, offer_name.name as alias_name
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN offer_name ON auction.name_id = offer_name.id
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE offer.name='$offer_name' $where
			$seller_filter_str
			$sort
			LIMIT $from, $to
		");
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findByOfferNameCount($db, $dbr, $offer_name)
    {
	  global $seller_filter_str;
        $cnt = $dbr->getOne("SELECT count(*)
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE offer.name='$offer_name'
			$seller_filter_str
		AND NOT auction.deleted");
        return $cnt;
    }

    static function findByAlias($db, $dbr, $name_id, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.auction_number
		, auction.txnid
		, auction.end_time
		, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
		, auction.responsible_uname
		, auction.siteid 
		, auction.winning_bid
		, auction.deleted
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, offer_name.name as alias_name, 
		CONCAT('<a target=\'_blank\' href=\'offer.php?id=',auction.offer_id,'\'>',auction.offer_id,'</a>') a_offer_id,
		IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as total_price,
		IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as open_amount,
		IFNULL((SELECT sum(ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price
					+ ac.shipping_cost -ac.vat_shipping - ac.effective_shipping_cost 
					+ ac.COD_cost -ac.vat_COD - ac.effective_COD_cost
					- ac.packing_cost/ac.curr_rate), 2))
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid), 0) as total_profit
		,(SELECT GROUP_CONCAT(email_log.date ORDER BY email_log.date DESC SEPARATOR '<br>') FROM email_log WHERE email_log.template like '%mass_email%'  
		and email_log.auction_number = auction.auction_number AND email_log.txnid = auction.txnid ) as massemail_datetime
		, fget_Currency(auction.siteid) currency
		, au_city_shipping.value as city_shipping
		, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		LEFT JOIN offer_name ON auction.name_id = offer_name.id
		WHERE name_id in (select id from offer_name 
		      where name=(
		      	    select name from offer_name where id=$name_id
				  )
				  )
			$seller_filter_str
			$sort
			LIMIT $from, $to
		");
        if (PEAR::isError($r)) {
			aprint_r($r);
			$sort = '';
        } 
        return $r;
    }

    static function findByAliasCount($db, $dbr, $name_id)
    {
	  global $seller_filter_str;
        $cnt = $dbr->getOne("SELECT count(*)
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
		WHERE name_id in (select id from offer_name 
		      where name=(
		      	    select name from offer_name where id=$name_id
				  )
				  )
			$seller_filter_str
				  ");
        return $cnt;
    }

    static function findByShippingCountry($db, $dbr, $country, $seller='', $from_date='', $to_date='', $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (strlen($seller)) $where .= " and auction.username='$seller'";
		if (strlen($from_date)) $where .= " and DATE(fget_delivery_date_real(auction.auction_number, auction.txnid)) >='$from_date'";
		if (strlen($to_date)) $where .= " and DATE(fget_delivery_date_real(auction.auction_number, auction.txnid)) <='$to_date'";
		$q = "SELECT SQL_CALC_FOUND_ROWS IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, fget_Currency(auction.siteid) currency
			, auction.auction_number
			, auction.txnid
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.siteid
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE au_country_shipping.value in ('".countryCodeToCountry($country)."', '$country')
			and main_auction_number=0
			and fget_ASent(auction.auction_number, auction.txnid)=1
			$where
			$seller_filter_str
			$sort
			LIMIT $from, $to
		";
        $r = $dbr->getAll($q);
//		echo $q.'<br>';
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByShippingCountryCount($db, $dbr, $country)
    {
	  global $seller_filter_str;
        $cnt = $dbr->getOne("SELECT count(*)
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE auction.country_shipping='".countryCodeToCountry($country)."'
			and main_auction_number=0
			$seller_filter_str
		");
        return $cnt;
    }

    static function findShippedNoStatic($db, $dbr, $datefrom=0, $dateto=0, $from=0, $to=9999999, $sort, $mode='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = '';
		if ($datefrom)
			$where .= " AND TO_DAYS(fget_delivery_date_real(auction.auction_number, auction.txnid))>=TO_DAYS('".$datefrom."') ";
		if ($dateto)
			$where .= " AND TO_DAYS(fget_delivery_date_real(auction.auction_number, auction.txnid))<=TO_DAYS('".$dateto."') ";
        $r = $dbr->getAll("SELECT auction.*, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		JOIN invoice ON auction.invoice_number = invoice.invoice_number
		JOIN invoice_static ON invoice_static.invoice_number = invoice.invoice_number
		WHERE fget_ASent(auction.auction_number, auction.txnid)=1
			and main_auction_number=0
		$where
		AND NOT auction.deleted
		AND (invoice_static.static".$mode." IS NULL OR invoice_static.static".$mode."='')
			$seller_filter_str
		$sort
		LIMIT $from, $to");
		
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findShipped($db, $dbr, $datefrom=0, $dateto=0, $seller='', $country_shipping='', $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = $where1 = '';
		if ($datefrom) {
			$where .= " AND fget_delivery_date_real(auction.auction_number, auction.txnid)>='".$datefrom." 00:00:00' ";
			$where1 .= " AND updated>='".$datefrom." 00:00:00' ";
		}
		if ($dateto) {
			$where .= " AND fget_delivery_date_real(auction.auction_number, auction.txnid)<='".$dateto." 23:59:59' ";
			$where1 .= " AND updated<='".$dateto." 23:59:59' ";
		}
		if (strlen($seller)) {
			$where .= " AND auction.username='$seller' ";
			$where1 .= " AND auction.username='$seller' ";
		}
		if (strlen($country_shipping)) {
			$where .= " and au_country_shipping.value in ('".countryCodeToCountry($country_shipping)."', '$country_shipping') ";
		}
        $q = "SELECT SQL_CALC_FOUND_ROWS auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, GROUP_CONCAT(tn.number ORDER BY tn.id SEPARATOR '<br>') tracking_number
		, GROUP_CONCAT(m.company_name ORDER BY tn.id SEPARATOR '<br>') shipping_company
		, fget_Currency(auction.siteid) currency
		, fget_AType(auction.auction_number, auction.txnid) src
		FROM auction 
		join (
			select IFNULL(mauction.auction_number,auction.auction_number) auction_number
			, IFNULL(mauction.txnid,auction.txnid) txnid 
			from total_log 
			join orders on total_log.tableid=orders.id
			join auction on auction.auction_number=orders.auction_number 
			and auction.txnid=orders.txnid
			left join auction mauction on auction.main_auction_number=mauction.auction_number 
			and auction.main_txnid=mauction.txnid
			where total_log.table_name='orders' and total_log.field_name='sent' 
			and total_log.New_value=1 and orders.manual=0
			$where1
		) t on t.auction_number=auction.auction_number and t.txnid=auction.txnid
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		LEFT JOIN tracking_numbers tn ON tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
		LEFT JOIN shipping_method m ON m.shipping_method_id = tn.shipping_method
		WHERE fget_ASent(auction.auction_number, auction.txnid)=1
			and main_auction_number=0
		$where
		AND NOT auction.deleted and auction.txnid<>4
		$seller_filter_str
		group by auction.auction_number, auction.txnid
		$sort
		LIMIT $from, $to";
		$r = $dbr->getAll($q);
//		echo $q;
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findShippedCount($db, $dbr, $datefrom=0, $dateto=0)
    {
	  global $seller_filter_str;
		$where = $where1 = '';
		if ($datefrom) {
			$where .= " AND fget_delivery_date_real(auction.auction_number, auction.txnid)>='".$datefrom." 00:00:00' ";
			$where1 .= " AND updated>='".$datefrom." 00:00:00' ";
		}
		if ($dateto) {
			$where .= " AND fget_delivery_date_real(auction.auction_number, auction.txnid)<='".$dateto." 23:59:59' ";
			$where1 .= " AND updated<='".$dateto." 23:59:59' ";
		}
        $cnt = $dbr->getOne("SELECT count(*)
		FROM auction 
		join (
			select IFNULL(mauction.auction_number,auction.auction_number) auction_number
			, IFNULL(mauction.txnid,auction.txnid) txnid 
			from total_log 
			join orders on total_log.tableid=orders.id
			join auction on auction.auction_number=orders.auction_number 
			and auction.txnid=orders.txnid
			left join auction mauction on auction.main_auction_number=mauction.auction_number 
			and auction.main_txnid=mauction.txnid
			where total_log.table_name='orders' and total_log.field_name='sent' 
			and total_log.New_value=1 
			$where1
		) t on t.auction_number=auction.auction_number and t.txnid=auction.txnid
		LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE fget_ASent(auction.auction_number, auction.txnid)=1
			and main_auction_number=0
		$where
		$seller_filter_str
		AND NOT auction.deleted");
        return $cnt;
    }

    /**
     * 
     * @global type $debug
     * @global Smarty $smarty
     * @param type $db
     * @param type $dbr
     * @param type $datefrom
     * @param type $dateto
     * @param type $sellername
     * @param type $mode
     * @return \stdClass
     */
    static function findStarted(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $datefrom = 0, $dateto = '2999-12-31', $sellername = '', $mode = 'Ricardo')
    {
        global $debug;
        global $smarty;
        require_once __DIR__ . '/../plugins/function.imageurl.php';
        
		$where = '';
		if (strlen($sellername))
			$where .= " and sp.par_value = '$sellername' ";

        $q = "SELECT distinct 
        sa.id
        , IFNULL(master_sa.details, sa.details) details, sa.details orig_details
        , sa.inactive
        , sa.last_repeat
        , sa.repeat_days
        , sa.scheduled
        , sa.nrepeats
        , sa.old
        , sa.stop_empty
        , sa.details_google
        , sa.use_notsold
        , sa.marked
        , sa.shop_catalogue_id
        , sa.responsible_uname
        , r.start_date, r.days, r.duration, r.featured, r.id as repid, r.weekdays, r.start_at
        , sp.par_value username
        , offer_name.name gallery_ricardo_alias_master
        , offer_namefr.name gallery_ricardo_alias_masterfr
        , (select CONCAT(doc_id, '-', pic_type)
            from saved_master_pics
            where saved_id=master_sa.id
            ORDER BY ordering limit 1) masterpicID
        , (select group_concat(CONCAT(doc_id, '-', pic_type) ORDER BY ordering)
            from saved_master_pics
            where saved_id=sa.id LIMIT 1) as masterpics
        , (select text_value
            from saved_available
            where channel_id=2
            and upto>datediff(
                IF(offer.available_weeks, date_add(NOW(), INTERVAL offer.available_weeks week), offer.available_date)
            , now())
            order by upto limit 1) AvailabilityID
        , offer.available
        , spc.shipping_cost
        , spcf.shipping_cost fshipping_cost
            FROM saved_auctions sa 
                left join saved_params sp_master_sa on sa.id = sp_master_sa.saved_id and sp_master_sa.par_key='master_sa'
                left join sa_all master_sa on sp_master_sa.par_value=master_sa.id
            JOIN saved_params sp ON sa.id=sp.saved_id and sp.par_key='username'
            left JOIN saved_params sp_alias_master ON sp_alias_master.saved_id=sa.id and sp_alias_master.par_key='gallery_ricardo_alias_master'
            left JOIN saved_params sp_alias_masterfr ON sp_alias_masterfr.saved_id=sa.id and sp_alias_masterfr.par_key='gallery_ricardo_alias_masterfr'
            JOIN saved_params sp_offer ON sp_offer.saved_id=sa.id and sp_offer.par_key='offer_id'
            join offer on offer.offer_id=sp_offer.par_value
                    join saved_params sp_username on sa.id=sp_username.saved_id and sp_username.par_key='username'
                    join saved_params sp_site on sa.id=sp_site.saved_id and sp_site.par_key='siteid'
                    left join seller_information si on si.username=sp_username.par_value 
                    left join translation tf
                        on tf.language=sp_site.par_value
                        and tf.id=sp_offer.par_value
                        and tf.table_name='offer' and tf.field_name='fshipping_plan_id'
                    left join translation t
                        on t.language=sp_site.par_value
                        and t.id=sp_offer.par_value
                        and t.table_name='offer' and t.field_name='shipping_plan_id'
                    left join shipping_plan_country spc on spc.shipping_plan_id=t.value and spc.country_code = si.defshcountry
                    left join shipping_plan_country spcf on spcf.shipping_plan_id=tf.value and spcf.country_code = si.defshcountry
            LEFT JOIN repetition r ON r.auction_id=sa.id 
                left join offer_name on sp_alias_master.par_value = offer_name.id
                left join offer_name offer_namefr on sp_alias_masterfr.par_value = offer_namefr.id
            WHERE NOT IFNULL(sa.inactive,0) 
                and NOT IFNULL(r.inactive,0) 
                AND r.days 
                #and sa.id=16805
                $where
            ORDER BY id, start_at DESC";
        
        if ($debug) { 
            $mem = exec("ps aux|grep ".getmypid()."|grep -v grep|awk {'print $6'}");
            echo '1: '.round(getmicrotime()-$time,2).' mem='.$mem.'<br>'; 
            file_put_contents('findStarted', date('Y-m-d H:i:s').' 1: '.round(getmicrotime()-$time,2).' mem='.$mem."\n".$q."\n", FILE_APPEND); 
            $time = getmicrotime(); 
            echo "<pre>$q</pre><br>"; 
        }
        
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        
		$rs = array();
		$pics = array();
            
        if ($debug) { 
            $mem = exec("ps aux|grep ".getmypid()."|grep -v grep|awk {'print $6'}");
            echo '2: '.round(getmicrotime()-$time,2).'  mem='.$mem.'<br>'; 
            file_put_contents('findStarted', '2: '.round(getmicrotime()-$time,2).' mem='.$mem."\n", FILE_APPEND); $time = getmicrotime();
        }
        
        $saved_ids = [];
        foreach ($r as $auction) {
            $saved_ids[] = (int)$auction->id;
        }
        
        $all_galleries = [];
        if ($saved_ids) {
            $saved_ids = implode(',', $saved_ids);
            $query = "select saved_gallery.saved_id, offer_name.name, saved_gallery.gallery, offer_namefr.name namefr
				from saved_gallery_ricardo saved_gallery
				join offer_name on saved_gallery.name_id=offer_name.id
				left join offer_name offer_namefr on saved_gallery.name_idfr=offer_namefr.id
				where not IFNULL(saved_gallery.inactive,0) and saved_gallery.saved_id IN ($saved_ids)";
            
            foreach ($dbr->getAll($query) as $_galery) {
                $all_galleries[$_galery->saved_id][] = $_galery;
            }
        }
        
		foreach ($r as $auction) {
			$vars = unserialize($auction->details);
			$vars_orig = unserialize($auction->orig_details);
			if (!$vars) {
				$auction->details = $dbr->getOne("select details from saved_auctions where id=".$auction->id);
				$vars = unserialize($auction->details);
			}
			if (!$vars) {
				echo 'cannot unserialize SA#'.$auction->id; 
                die();
			}

            if ($debug) { 
                $mem = exec("ps aux|grep ".getmypid()."|grep -v grep|awk {'print $6'}");
                echo '3: '.round(getmicrotime()-$time,2).'  mem='.$mem.'<br>'; 
                file_put_contents('findStarted', '3: SA#'.$auction->id.' '.round(getmicrotime()-$time,2).' mem='.$mem."\n", FILE_APPEND); 
                $time = getmicrotime();
            }
            
			$lastRicardoDescrNum = 6;
			$date = $datefrom;
            $nextdate=0;
			$galleries = isset($all_galleries[$auction->id]) ? $all_galleries[$auction->id] : [];
            
			$inactivedescription = 'inactivedescription'.$mode;
			while ($date <= $dateto)
			{	
                $date_int = strtotime($date);
                $enddate = strtotime("{$auction->duration} days", $date_int);
                        
				if ($auction->weekdays & (1<< date('w', $enddate))) {
                    $nextdate = strtotime("+1 day", $date_int);
			    } 
                else {
					switch ($lastRicardoDescrNum) {
					    case 1: 
							if ($vars_orig[$inactivedescription][2])
								if ($vars_orig[$inactivedescription][3])
									if ($vars_orig[$inactivedescription][4])
										if ($vars_orig[$inactivedescription][5])
											if ($vars_orig[$inactivedescription][6])
												if ($vars_orig[$inactivedescription][1])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 1;
											else $DescrNumRicardo = 6;
										else $DescrNumRicardo = 5;
									else $DescrNumRicardo = 4;
								else $DescrNumRicardo = 3;
							else $DescrNumRicardo = 2;
							break;
					    case 2:
							if ($vars_orig[$inactivedescription][3])
								if ($vars_orig[$inactivedescription][4])
									if ($vars_orig[$inactivedescription][5])
										if ($vars_orig[$inactivedescription][6])
											if ($vars_orig[$inactivedescription][1])
												if ($vars_orig[$inactivedescription][2])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 2;
											else $DescrNumRicardo = 1;
										else $DescrNumRicardo = 6;
									else $DescrNumRicardo = 5;
								else $DescrNumRicardo = 4;
							else $DescrNumRicardo = 3;
							break;
					    case 3:
							if ($vars_orig[$inactivedescription][4])
								if ($vars_orig[$inactivedescription][5])
									if ($vars_orig[$inactivedescription][6])
										if ($vars_orig[$inactivedescription][1])
											if ($vars_orig[$inactivedescription][2])
												if ($vars_orig[$inactivedescription][3])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 3;
											else $DescrNumRicardo = 2;
										else $DescrNumRicardo = 1;
									else $DescrNumRicardo = 6;
								else $DescrNumRicardo = 5;
							else $DescrNumRicardo = 4;
							break;
					    case 4:
							if ($vars_orig[$inactivedescription][5])
								if ($vars_orig[$inactivedescription][6])
									if ($vars_orig[$inactivedescription][1])
										if ($vars_orig[$inactivedescription][2])
											if ($vars_orig[$inactivedescription][3])
												if ($vars_orig[$inactivedescription][4])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 4;
											else $DescrNumRicardo = 3;
										else $DescrNumRicardo = 2;
									else $DescrNumRicardo = 1;
								else $DescrNumRicardo = 6;
							else $DescrNumRicardo = 5;
							break;
					    case 5:
							if ($vars_orig[$inactivedescription][6])
								if ($vars_orig[$inactivedescription][1])
									if ($vars_orig[$inactivedescription][2])
										if ($vars_orig[$inactivedescription][3])
											if ($vars_orig[$inactivedescription][4])
												if ($vars_orig[$inactivedescription][5])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 5;
											else $DescrNumRicardo = 4;
										else $DescrNumRicardo = 3;
									else $DescrNumRicardo = 2;
								else $DescrNumRicardo = 1;
							else $DescrNumRicardo = 6;
							break;
					    case 6:
							if ($vars_orig[$inactivedescription][1])
								if ($vars_orig[$inactivedescription][2])
									if ($vars_orig[$inactivedescription][3])
										if ($vars_orig[$inactivedescription][4])
											if ($vars_orig[$inactivedescription][5])
												if ($vars_orig[$inactivedescription][6])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 6;
											else $DescrNumRicardo = 5;
										else $DescrNumRicardo = 4;
									else $DescrNumRicardo = 3;
								else $DescrNumRicardo = 2;
							else $DescrNumRicardo = 1;
							break;
						default:
							if ($vars_orig[$inactivedescription][1])
								if ($vars_orig[$inactivedescription][2])
									if ($vars_orig[$inactivedescription][3])
										if ($vars_orig[$inactivedescription][4])
											if ($vars_orig[$inactivedescription][5])
												if ($vars_orig[$inactivedescription][6])
													$DescrNumRicardo=0;
												else $DescrNumRicardo = 6;
											else $DescrNumRicardo = 5;
										else $DescrNumRicardo = 4;
									else $DescrNumRicardo = 3;
								else $DescrNumRicardo = 2;
							else $DescrNumRicardo = 1;
							break;
					}// switch		
					
                    $lastRicardoDescrNum = $DescrNumRicardo;
					$gallery = array_shift($galleries);
					array_push ( $galleries, $gallery);
                    
                    $nextdate = strtotime("{$auction->days} days", $date_int);

                    if ($debug) { 
                        $mem = exec("ps aux|grep ".getmypid()."|grep -v grep|awk {'print $6'}");
                        echo '4: '.round(getmicrotime()-$time,2).'  mem='.$mem.'<br>'; 
                        file_put_contents('findStarted', '4: '.round(getmicrotime()-$time,2) . ' $nextdate='.date('Y-m-d',$nextdate).' mem='.$mem."\n", FILE_APPEND); 
                        $time = getmicrotime();
                    }
                    
                    $rec = new stdClass;
                    $rec->auction_number = $auction->id;
                    $rec->txnid = $auction->repid;
                    $rec->RefNo = "{$auction->id}/{$auction->repid}/{$date}";
                    $rec->saved_id = $auction->id;
                    $rec->start_time = "{$date} {$auction->start_at}";
                    $rec->end_time = date('Y-m-d',$enddate);
                    $rec->username = $auction->username;
                    $rec->duration = $auction->duration;
                    $rec->featured = $auction->featured;
                    $rec->description = $vars_orig['description'.$mode][$DescrNumRicardo];
                    $rec->AvailabilityID = $auction->AvailabilityID;
                    $rec->available = $auction->available;
                    $rec->fshipping_cost = $auction->fshipping_cost;
                    $rec->shipping_cost = $auction->shipping_cost;
                    $rec->masterpics = $auction->masterpics;

                    $rec->descriptionFr = $vars_orig['description'.$mode.'Fr'][$DescrNumRicardo];
                    if ($vars_orig['master_sa']) {
                        $gallery_ricardo_alias_master = $auction->gallery_ricardo_alias_master;
                        $rec->name = $gallery_ricardo_alias_master;
                        $gallery_ricardo_alias_masterfr = $auction->gallery_ricardo_alias_masterfr;
                        $rec->nameFr = $gallery_ricardo_alias_masterfr;

                        $masterpicID = explode('-', $auction->masterpicID);
                        $rec->gallery = 'https://www.beliani.ch'.smarty_function_imageurl([
                            'src'=>"sa",
                            'picid'=>$masterpicID[0],
                            'type'=>$masterpicID[1],
                            'x'=>1800,
                            'addlogo'=>'logo'], $smarty);
                        
                        $pic[] = $rec->gallery;
                    } 
                    else {
                        $rec->name = $gallery->name;
                        $rec->nameFr = $gallery->namefr;
                        $rec->gallery = $gallery->gallery;
                        $pic[] = $gallery->gallery;
                    }

                    $rec->details = $vars;
                    $rec->details_orig = $vars_orig;
                    $res[] = $rec;		

                    if ($debug) { 
                        $mem = exec("ps aux|grep ".getmypid()."|grep -v grep|awk {'print $6'}");
                        echo '5: '.round(getmicrotime()-$time,2).'  mem='.$mem.'<br>'; 
                        file_put_contents('findStarted', '5: '.round(getmicrotime()-$time,2).' mem='.$mem."\n", FILE_APPEND); 
                        $time = getmicrotime();
                    }
				}
                
				$date = date('Y-m-d', $nextdate);
			} 
		}
        
        return $res;
    }

    static function findByCalcListingFee($db, $dbr, $fee_dn=0)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
/*        $r = $dbr->getAll("SELECT auction.*, offer.name as offer_name, SUM(auction_calcs.ebay_listing_fee) sum_listing_fee
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		left join auction_calcs ON auction_calcs.auction_number = auction.auction_number and  auction_calcs.txnid = auction.txnid 
		GROUP BY auction.auction_number, auction.txnid 
		HAVING sum_listing_fee>$fee_dn");*/
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE 1
			and main_auction_number=0
		and auction.listing_fee>".$fee_dn
			.$seller_filter_str
		);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findByFeatures($db, $dbr,
				$gallery,
				$gal_featured,
				$bold,
				$highlight,
				$featured,
				$super,
				$private,
				$fixedprice,
				$galleryURL=''
				, $from=0, $to=9999999, $sort
			)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $where = " where apt.value <> '' and ( 0 ";
        if ($gallery) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:7:"gallery";'."', -1 ) LIKE 's%'";
        if ($gal_featured) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:12:"gal_featured";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($bold) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:4:"bold";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($highlight) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:9:"highlight";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($featured) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:8:"featured";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($super) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:5:"super";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($private) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:7:"private";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($fixedprice) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:10:"fixedprice";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if (strlen($galleryURL)) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:7:"gallery";'."', -1 ) LIKE '".'s:'.strlen($galleryURL).':"'.$galleryURL.'";%'."'";
        if ($where == " where apt.value <> ' and ( 0 ") return array();
        $where .= " ) ";

        $r = $dbr->getAll("SELECT 
		IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, CONCAT('<a target=\'_blank\' href=\'offer.php?id=',auction.offer_id,'\'>',auction.offer_id,'</a>') a_offer_id,
		IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as total_price,
		IFNULL(invoice.total_price + invoice.total_shipping + invoice.total_cod, 0) as open_amount,
		IFNULL((SELECT sum(ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price
					+ ac.shipping_cost -ac.vat_shipping - ac.effective_shipping_cost 
					+ ac.COD_cost -ac.vat_COD - ac.effective_COD_cost
					- ac.packing_cost/ac.curr_rate), 2))
			FROM auction_calcs ac 
			WHERE ac.auction_number = auction.auction_number
			AND ac.txnid = auction.txnid), 0) as total_profit
		, fget_Currency(auction.siteid) currency	
			, auction.auction_number
			, auction.txnid
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.siteid
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		FROM auction
		JOIN auction_par_text apt on apt.auction_number=auction.auction_number and apt.txnid=auction.txnid and apt.key='details'
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
	   $where 
			and main_auction_number=0
		$seller_filter_str
	   $sort LIMIT $from, $to");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByFeaturesCount($db, $dbr,
				$gallery,
				$gal_featured,
				$bold,
				$highlight,
				$featured,
				$super,
				$private,
				$fixedprice,
				$galleryURL='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $where = " where apt.value <> '' and ( 0 ";
        if ($gallery) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:7:"gallery";'."', -1 ) LIKE 's%'";
        if ($gal_featured) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:12:"gal_featured";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($bold) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:4:"bold";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($highlight) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:9:"highlight";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($featured) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:8:"featured";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($super) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:5:"super";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($private) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:7:"private";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if ($fixedprice) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:10:"fixedprice";'."', -1 ) LIKE '".'s:1:"1";%'."'";
        if (strlen($galleryURL)) 
           $where .= " OR SUBSTRING_INDEX( apt.value, '".'s:7:"gallery";'."', -1 ) LIKE '".'s:'.strlen($galleryURL).':"'.$galleryURL.'";%'."'";
        if ($where == " where apt.value <> ' and ( 0 ") return 0;
        $where .= " ) ";

        $r = $dbr->getOne("SELECT count(*) FROM auction 
		JOIN auction_par_text apt on apt.auction_number=auction.auction_number and apt.txnid=auction.txnid and apt.key='details'
	   $where
			and main_auction_number=0
		$seller_filter_str
	   "); 
        if (PEAR::isError($r)) {
            $this->_error = $r;
            aprint_r($r);
            return 0;
        }
        return $r;
    }

    static function findRMABySeller($db, $dbr, $rma_by_seller_username, $rma_by_seller_open)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($rma_by_seller_open=='') {
			$open_where = '';
		} elseif ($rma_by_seller_open==1) {
			$open_where = "and IFNULL(rma.close_date, '0000-00-00 00:00:00')='0000-00-00 00:00:00'";
		} else {
			$open_where = "and IFNULL(rma.close_date, '0000-00-00 00:00:00')<>'0000-00-00 00:00:00'";
		}
        $r = $dbr->getAll("SELECT auction.auction_number
			, auction.txnid
			, auction.username
			, auction.end_time
			, rma.close_date
			, rma.create_date
			, invoice.open_amount
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, IFNULL(users.name, rma.responsible_uname) responsible_uname,
				rma.rma_id, rma.create_date as rma_create_date
				, f_rma_last_change(rma.rma_id) last_change
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
				, IFNULL(
					(select CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number)
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					where co.obj_id=offer.offer_id 
						and co.obj='offer')
					, (select GROUP_CONCAT(CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number) SEPARATOR '<br><br>')
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					join auction subs on subs.offer_id=co.obj_id and co.obj='offer' and co.level=4
					where subs.main_auction_number=auction.auction_number and subs.main_txnid=auction.txnid
					)) offer_classifier
			FROM auction 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			LEFT JOIN invoice ON invoice.invoice_number = auction.invoice_number
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
				LEFT JOIN users ON users.username=rma.responsible_uname
			WHERE 1
			and main_auction_number=0
			$open_where
			and (auction.username='$rma_by_seller_username' OR '$rma_by_seller_username'='')
			$seller_filter_str
		");
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }

    static function findOpenedByRMA($db, $dbr, $username, $mode)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($mode=='') {
			$open_where = '';
		} elseif ($mode==1) {
			$open_where = "and IFNULL(rma.close_date, '0000-00-00 00:00:00')='0000-00-00 00:00:00'";
		} else {
			$open_where = "and IFNULL(rma.close_date, '0000-00-00 00:00:00')<>'0000-00-00 00:00:00'";
		}
        $r = $dbr->getAll("SELECT auction.*
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, IFNULL(users.name, rma.responsible_uname) responsible_uname,
				rma.rma_id, rma.create_date as rma_create_date
				, f_rma_last_change(rma.rma_id) last_change
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
				, IFNULL(
					(select CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number)
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					where co.obj_id=offer.offer_id 
						and co.obj='offer')
					, (select GROUP_CONCAT(CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number) SEPARATOR '<br><br>')
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					join auction subs on subs.offer_id=co.obj_id and co.obj='offer' and co.level=4
					where subs.main_auction_number=auction.auction_number and subs.main_txnid=auction.txnid
					)) offer_classifier
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
				LEFT JOIN users ON users.username=rma.responsible_uname
		WHERE 1
		$open_where
		and (rma.responsible_uname='$username' OR '$username'='' 
			OR ('$username'='0' AND NOT EXISTS (
			select * from users where username=rma.responsible_uname
		)))
			and main_auction_number=0
		$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findClosedByRMA($db, $dbr, $username, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(users.name, rma.supervisor_uname) responsible_uname,
		rma.rma_id, rma.create_date as rma_create_date 
				, f_rma_last_change(rma.rma_id) last_change
				, fget_Currency(auction.siteid) currency
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
				LEFT JOIN users ON users.username=rma.supervisor_uname 
		WHERE rma.close_date is not null and (rma.supervisor_uname='".$username."' OR '$username'='' 
		OR ('$username'='0' AND NOT EXISTS (
		select * from users where username=rma.supervisor_uname
		))) 
			and main_auction_number=0
		$seller_filter_str
		$sort LIMIT $from, $to");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return $r;
        }
        return $r;
    }

    static function findClosedByRMACount($db, $dbr, $username)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getOne("SELECT count(*)
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
		WHERE rma.close_date is not null and (rma.supervisor_uname='".$username."' OR '$username'='' 
		OR ('$username'='0' AND NOT EXISTS (
		select * from users where username=rma.supervisor_uname
		)))
			and main_auction_number=0
			$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return 0;
        }
        return $r;
    }

    static function findRMAbyProblem($db, $dbr, $openclosed, $problem, $solution, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$whereopen = ($openclosed==-1)?' and rma.close_date is not null ' : (($openclosed==1)?' and rma.close_date is null ':'');
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(users.name, rma.supervisor_uname) responsible_uname,
		rma.rma_id, rma.create_date as rma_create_date 
				, f_rma_last_change(rma.rma_id) last_change
				, fget_Currency(auction.siteid) currency
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
			join rma_spec on rma.rma_id=rma_spec.rma_id
			LEFT JOIN users ON users.username=rma.supervisor_uname 
		WHERE (rma_spec.problem_id=$problem OR $problem=0) $whereopen
			and (exists (select null from rma_spec_solutions rss where rss.rma_spec_id=rma_spec.rma_spec_id
							and rss.solution_id=$solution
				) or $solution=0)
			and main_auction_number=0
			$seller_filter_str
		$sort LIMIT $from, $to");
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findRMAbyProblemCount($db, $dbr, $openclosed, $problem, $solution)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$whereopen = ($openclosed==-1)?' and rma.close_date is not null ' : (($openclosed==1)?' and rma.close_date is null ':'');
        $r = $dbr->getOne("SELECT count(*)
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
			join rma_spec on rma.rma_id=rma_spec.rma_id
		WHERE (rma_spec.problem_id=$problem OR $problem=0) 
			and (exists (select null from rma_spec_solutions rss where rss.rma_spec_id=rma_spec.rma_spec_id
							and rss.solution_id=$solution
				) or $solution=0)
			$whereopen
			and main_auction_number=0
			$seller_filter_str
			");
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
            return 0;
        }
        return $r;
    }

    static function findClosedButUnapproved($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findClosedButUnapproved expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, rma.rma_id, rma.create_date as rma_create_date  
				, f_rma_last_change(rma.rma_id) last_change
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
			FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
		WHERE rma.close_date is not null and rma.approval_date is null
			and main_auction_number=0
			$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    /**
     * @param MDB2_Driver_Common $db
     * @param MDB2_Driver_Common $dbr
     * @param Auction|array $auctions can be Auction or array of Auction
     * @param int $sum 1 or 0 - is sum have to be counted
     * @return array
     */
    public static function getCalcs($db, $dbr, $auctions, $sum = 0, $cache = 0)
    {
        global $seller_filter;
        $lang = 'german';
        if (count($seller_filter)) $seller_filter_str1 = " and au.username in ($seller_filter) ";
        if (!is_array($auctions)) {
            $auctions = array($auctions->data);
        }
        $list = array(); 
        foreach ($auctions as $auction) {
			$cached_list = array();
			if ($cache) {
				// try to get from redis
				$function = "getCalcs({$auction->auction_number}/{$auction->txnid},$sum)";
				$chached_ret = cacheGet($function, 0, '');
				if ($chached_ret) {
//					echo 'return '.$function.'<br>';
					foreach($chached_ret as $rec) $list[] = $rec;
					continue;
				}
			}
			// define variables for the query
            $auction_number = $auction->auction_number;
            $txnid = $auction->txnid;
			$lang = $auction->lang;
			$curr_rate = 1*$dbr->getOne("select curr_rate from auction au 
				LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number AND mau.txnid = au.main_txnid
				LEFT JOIN auction_calcs ac ON IFNULL(mau.auction_number, au.auction_number) = ac.auction_number
						AND IFNULL(mau.txnid, au.txnid) = ac.txnid 
				where au.auction_number = $auction_number AND au.txnid = $txnid 
				limit 1
				");
	// SQL to calculate brutto
			$brutto_sql = "(
							IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
						+ IF(a.admin_id=4
							,IFNULL(i.total_shipping
								, IFNULL(ac.shipping_cost,0)
							) 
						, 0)
							- IFNULL(ac.effective_shipping_cost,0)
						+ IF(a.admin_id=4
							,IFNULL(i.total_cod
								,IFNULL(ac.COD_cost,0)
							) 
							- IFNULL(ac.effective_COD_cost,0)
						, 0)
							- IFNULL(ac.packing_cost,0)/{$curr_rate}
							 -IFNULL(ac.vat_shipping,0)
							  -IFNULL(ac.vat_COD,0)
							)
					";
            $qry = "SELECT IFNULL(IF(a.admin_id=4,'Shipping cost',
				IF(a.admin_id=2, 
                    (SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
                    WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = IFNULL(mau.lang, au.lang)
                    AND shop_bonus.article_id = a.article_id)
                                    ,
                                        IF(a.admin_id=3,
                    IFNULL((SELECT IF(shop_promo_codes.descr_is_name, shop_promo_codes.name, translation.value)
                        FROM shop_promo_codes left join translation on translation.id = shop_promo_codes.id
						and table_name = 'shop_promo_codes' AND field_name = 'name' AND language = '$lang'
                    WHERE 1
                    AND shop_promo_codes.article_id = a.article_id limit 1),
                    (SELECT IF(shop_promo_codes.descr_is_name, shop_promo_codes.name, translation.value)
                        FROM translation join shop_promo_codes on translation.id = shop_promo_codes.id
                    WHERE table_name = 'shop_promo_codes' AND field_name = 'name' AND language = IFNULL(mau.lang, au.lang)
                    AND shop_promo_codes.article_id = '$auction->code_id' limit 1))
						,IF(a.article_id='', a.name, 
					IFNULL((SELECT value
					FROM translation
					WHERE table_name = 'article'
					AND field_name = 'name'
					AND language = IFNULL(mau.lang, au.lang)
					AND id = a.article_id), a.name))
				))),IF(a.admin_id=3,(select name FROM shop_promo_codes where shop_promo_codes.article_id = a.article_id limit 1)
					,'Shipping cost')) as name
				, IF(a.admin_id=2,
				(SELECT id FROM shop_bonus where shop_bonus.article_id = a.article_id)
				, IF(a.admin_id=3,
				(SELECT id FROM shop_promo_codes where shop_promo_codes.article_id = a.article_id limit 1)
				,a.article_id)) real_id
				, IF(a.admin_id=3,-ac.purchase_price*(1+ac.vat_percent/100),0) income_voucher_cost
				, IF(a.admin_id=3,-ROUND(ac.purchase_price*{$curr_rate}*(1+ac.vat_percent/100), 2),0) income_voucher_cost_EUR
					, a.admin_id as article_admin, o.auction_number, o.txnid, o.article_id, 
					ac.purchase_price as purchase_price, ROUND(ac.purchase_price*{$curr_rate}, 2) as purchase_price_EUR, 
			        ac.price_sold as price_sold, ROUND(ac.price_sold*{$curr_rate}, 2) as price_sold_EUR,
	        		ac.ebay_listing_fee as ebay_listing_fee, ROUND(ac.ebay_listing_fee*{$curr_rate}, 2) as ebay_listing_fee_EUR,
	        		ac.additional_listing_fee as additional_listing_fee, ROUND(ac.additional_listing_fee*{$curr_rate}, 2) as additional_listing_fee_EUR,
			        ac.ebay_commission as ebay_commission, ROUND(ac.ebay_commission*{$curr_rate}, 2) as ebay_commission_EUR,
			        ac.vat as vat, ROUND(ac.vat*{$curr_rate}, 2) as vat_EUR,
	        		(ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat) as netto_sales_price,
	        		ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat)*{$curr_rate}, 2) as netto_sales_price_EUR,
	        		(ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price) as brutto_income,
	        		ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price)*{$curr_rate}, 2) as brutto_income_EUR,
			        (ac.shipping_cost-ac.vat_shipping) as shipping_cost, ROUND((ac.shipping_cost-ac.vat_shipping)*{$curr_rate}, 2) as shipping_cost_EUR,
	        		ac.effective_shipping_cost as effective_shipping_cost, ROUND(ac.effective_shipping_cost*{$curr_rate}, 2) as effective_shipping_cost_EUR,
			        ac.vat_shipping, ROUND(ac.vat_shipping*{$curr_rate}, 2) as vat_shipping_EUR, 
			        (ac.COD_cost-ac.vat_COD) as COD_cost, ROUND((ac.COD_cost-ac.vat_COD)*{$curr_rate}, 2) as COD_cost_EUR,
	        		ac.effective_COD_cost as effective_COD_cost, ROUND(ac.effective_COD_cost*{$curr_rate}, 2) as effective_COD_cost_EUR,
					ac.vat_COD, ROUND(ac.vat_COD*{$curr_rate}, 2) as vat_COD_EUR, 
	        		ac.packing_cost as packing_cost_EUR, ROUND(ac.packing_cost/{$curr_rate}, 2) as packing_cost,
					(ac.shipping_cost-ac.vat_shipping-ac.effective_shipping_cost
						+ac.COD_cost-ac.vat_COD-effective_COD_cost) as income_shipping_cost,
					ROUND((ac.shipping_cost-ac.vat_shipping-ac.effective_shipping_cost
						+ac.COD_cost-ac.vat_COD-effective_COD_cost)*{$curr_rate}, 2) as income_shipping_cost_EUR,
			        ROUND({$brutto_sql}, 2) as brutto_income_2,
			        ROUND({$brutto_sql}*{$curr_rate}, 2) as brutto_income_2_EUR,
			        ROUND({$brutto_sql}/o.quantity, 2) as brutto_income_3,
			        ROUND({$brutto_sql}*{$curr_rate}/o.quantity, 2) as brutto_income_3_EUR,
					o.quantity, 
					o.price,
					au.siteid, au.username, si.seller_channel_id
				, (select CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number)
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					where co.obj_id=a.iid and co.obj='article') classifier
				, (select CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number)
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					where obj='offer' and obj_id=offer.offer_id) offer_classifier
				, ss.name source_seller
				, {$curr_rate} curr_rate
                                , ac.iid
				FROM orders o JOIN auction au ON o.auction_number = au.auction_number
						AND o.txnid = au.txnid 
					left join offer on au.offer_id=offer.offer_id
				LEFT JOIN auction mau ON (mau.auction_number = au.main_auction_number
					AND mau.txnid = au.main_txnid)
					join seller_information si on au.username=si.username
					left JOIN article_list al ON o.article_list_id = al.article_list_id
					LEFT JOIN offer_group og ON og.offer_group_id = al.group_id
						AND au.offer_id = og.offer_id
					left JOIN article a ON a.article_id = o.article_id AND a.admin_id=o.manual
					LEFT JOIN auction_calcs ac ON o.id = ac.order_id
						AND o.auction_number = ac.auction_number
						AND o.txnid = ac.txnid 
						and ac.article_id=o.article_id
				left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
				left join source_seller ss on ss.id=ifnull(mau.source_seller_id,au.source_seller_id)
                JOIN invoice i ON i.invoice_number = au.invoice_number
				WHERE au.auction_number = $auction_number
				AND au.txnid = $txnid 
				and o.hidden=0 
				and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
				$seller_filter_str1
				ORDER BY o.ordering, og.offer_id, og.offer_group_id, al.article_list_id";
//			echo $qry;	//die();
            $r = $db->query($qry);
            if (PEAR::isError($r)) {
                aprint_r($r);
                return;
            }
            while ($article = $r->fetchRow()) {
                $article->site_country = countryCodeToCountry(siteToCountryCode($article->siteid));
                $article->txnid_type = $article->txnid == 3 ? 'Shop' :
                    ($article->txnid <= 1 ? 'Auction' :
                        ($article->txnid > 3 ? 'Fixed' :
                            ($article->txnid == 2 ? ($article->seller_channel_id == 3 ? 'Amazon' : 'Fixed')
                                : '')));
                $article->revenue = $article->price_sold + $article->shipping_cost + $article->COD_cost + $article->vat_COD + $article->vat_shipping + $article->income_voucher_cost;
                $article->revenue_EUR = $article->price_sold_EUR + $article->shipping_cost_EUR + $article->COD_cost_EUR + $article->vat_COD_EUR + $article->vat_shipping_EUR + $article->income_voucher_cost_EUR;
                $list[] = $article;
                $cached_list[] = $article;
            } // while article
			// save the result to redis
			if ($cache) {
				cacheSet($function, 0, '', $cached_list);
			}
        } // foreach austion
        if ($sum) {
            $row = null;
            foreach ($list as $article) {
                $row->purchase_price += $article->purchase_price;
                $row->purchase_price_EUR += $article->purchase_price_EUR;
                $row->price_sold += $article->price_sold;
                $row->price_sold_EUR += $article->price_sold_EUR;
                $row->ebay_listing_fee += $article->ebay_listing_fee;
                $row->ebay_listing_fee_EUR += $article->ebay_listing_fee_EUR;
                $row->additional_listing_fee += $article->additional_listing_fee;
                $row->additional_listing_fee_EUR += $article->additional_listing_fee_EUR;
                $row->ebay_commission += $article->ebay_commission;
                $row->ebay_commission_EUR += $article->ebay_commission_EUR;
                $row->vat += $article->vat;
                $row->vat_EUR += $article->vat_EUR;
                $row->netto_sales_price += $article->netto_sales_price;
                $row->netto_sales_price_EUR += $article->netto_sales_price_EUR;
                $row->brutto_income += $article->brutto_income;
                $row->brutto_income_EUR += $article->brutto_income_EUR;
                $row->shipping_cost += $article->shipping_cost;
                $row->shipping_cost_EUR += $article->shipping_cost_EUR;
                $row->effective_shipping_cost += $article->effective_shipping_cost;
                $row->effective_shipping_cost_EUR += $article->effective_shipping_cost_EUR;
                $row->vat_shipping += $article->vat_shipping;
                $row->vat_shipping_EUR += $article->vat_shipping_EUR;
                $row->COD_cost += $article->COD_cost;
                $row->COD_cost_EUR += $article->COD_cost_EUR;
                $row->effective_COD_cost += $article->effective_COD_cost;
                $row->effective_COD_cost_EUR += $article->effective_COD_cost_EUR;
                $row->vat_COD += $article->vat_COD;
                $row->vat_COD_EUR += $article->vat_COD_EUR;
                $row->packing_cost_EUR += $article->packing_cost_EUR;
                $row->packing_cost += $article->packing_cost;
                $row->income_shipping_cost += $article->income_shipping_cost;
                $row->income_shipping_cost_EUR += $article->income_shipping_cost_EUR;
                $row->brutto_income_2 += $article->brutto_income_2;
                $row->brutto_income_2_EUR += $article->brutto_income_2_EUR;
                $row->brutto_income_3 += $article->brutto_income_3;
                $row->brutto_income_3_EUR += $article->brutto_income_3_EUR;
                $row->revenue += $article->revenue;
                $row->revenue_EUR += $article->revenue_EUR;
                $row->curr_rate = $article->curr_rate;
                $row->income_voucher_cost_EUR += $article->income_voucher_cost_EUR;
                $row->income_voucher_cost += $article->income_voucher_cost;
            }
            $row->article_admin = 1;
            $row->sum = 1;
            $row->name = 'Total:';
            $list[] = $row;
        }; // if sum
        return $list;
    }

    function GetShippingPlan($db, $dbr, $country_code)
    {
//	  global $seller_filter;
//	if (strlen($seller_filter)) $seller_filter_str1 = " and au.username in ($seller_filter) ";
		$auction_number = $this->data->auction_number;
		$txnid = $this->data->txnid;
		$lang = $this->data->siteid;
		$seller_channel_id = $this->_dbr->getOne("select seller_channel_id from seller_information
			where username='".$this->data->username."'");
		if ($seller_channel_id==3) {
			$type = 'a';
		} else {
			switch($txnid) {
				case 1: $type = '';
				break;
				case 3: $type = 's';
				break;
				case 0: $type = '';
				break;
				default: $type = 'f';
				break;
			}
		}
$q = "SELECT	spc.shipping_plan_id shipping_plan_id
			FROM auction au 
				JOIN offer of ON au.offer_id = of.offer_id
				JOIN shipping_plan_country spc ON spc.shipping_plan_id = (SELECT value
				FROM translation
				WHERE table_name = 'offer'
				AND field_name = '".$type."shipping_plan_id'
				AND language = '$lang'
				AND id = of.offer_id)
			WHERE au.auction_number = $auction_number
			AND au.txnid = $txnid
			AND spc.country_code = '".$country_code."'
			";
        $r = $dbr->getAll($q);
//echo $q;
            if (count($r) < 1) {
        	    $this->_error = $r;
            	return;
			};
		$res = $r[0];
        return $res->shipping_plan_id;
    }

    function GetListingFee($db, $dbr)
    {
		$auction_number = $this->data->auction_number;
		$txnid = $this->data->txnid;
        $r = $this->_dbr->getAll("SELECT * 
			FROM listingfees
			WHERE auction_number = $auction_number
			AND txnid = $txnid");
//		$res = $r[0];
        return $r;
    }

    function OpenListingFee($db, $dbr, $date, $amount, $username)
    {
		$auction_number = $this->data->auction_number;
		$txnid = $this->data->txnid;
        $db->query("INSERT INTO listingfees SET
			auction_number = $auction_number,
			txnid = $txnid,
			open_username='".$username."',
			amount = $amount,
			open_date = '".$date."'");
/*			amount = (SELECT IFNULL(IFNULL(l.listing_fee, au.listing_fee)*au.quantity/
				(select sum( au1.quantity) from auction au1 where au1.auction_number = au.auction_number), 0) listing_fee*/
    }

    static function CloseListingFee($db, $dbr, $id, $date, $username)
    {
        $db->query("UPDATE listingfees SET
			close_username='".$username."',
			close_date='".$date."'
			WHERE id=$id");
    }

    static function DeleteListingFee($db, $dbr, $id)
    {
        $db->query("DELETE FROM listingfees WHERE id=$id");
    }

    static function findNotSold($db, $dbr, $step, $date, $offer_id=0)
    {
		set_time_limit(0); 
	  global $seller_filter;
	  $seller_filter_str1 = '';
	if (is_a($loggedUser, 'User') && count($seller_filter)) $seller_filter_str1 = " and a.username in ($seller_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
	   switch ($step) {
            case 1: $end_time='end_time';
                break;
            case 2: $end_time='end_time2';
                break;
            default:
                return;
        };
	$mindaysforcomplain = Config::get($db, $dbr, 'relist_complains_until');	
	$inactiveDays = 120;	
	if ($offer_id) $where=" and exists (select null from offer_name ofn1
				join offer_name ofn2 on ofn1.name=ofn2.name 
				where not ofn1.deleted and not ofn2.deleted 
				and ofn1.offer_id=$offer_id and ofn2.offer_id=a.offer_id) ";	
	$where .= $seller_filter_str1;
	$uri = (string)$_SERVER['SCRIPT_NAME']."?".(string)$_SERVER['QUERY_STRING'];
	$uri = preg_replace('/&auction_number=.+/', '', $uri); 
	if ($step==1) 
		$qry = "select distinct a.*, DATEDIFF( NOW( ) , a.end_time1) diff from 
			(
			SELECT a1.saved_id, a1.deleted deleted, a1.solved, IF(IFNULL(a1.complained, 0),'complain','not sold') as reason
				, IF(a1.solved OR a1.relist_failed=1 OR DATEDIFF( NOW( ) , a1.end_time1)>$inactiveDays, 'gray', '') solvedcolor
				, 'A' type, 1 AS auction_type, 'Chinese' AS auction_type_vch
				, a2.auction_number, a2.txnid txnid2, a1.auction_number auction_number_prev, a1.txnid, a1.siteid, a1.quantity, a1.username
			  	, CONCAT(IF(a1.solved, '<font color=gray>', IF(NOT a1.deleted,'<font color=green>','<font>')),offer.name,'</font>') as offer_name, a1.offer_id
				, a1.start_time1, a2.start_time2, a1.start_time1 start_time
				, a1.end_time1, a2.end_time2, a1.end_time1 as end_time, a1.end_time end_time_
				, a1.listing_fee1, a2.listing_fee2, a1.listing_fee
				, a1.winning_bid first_winning_bid, a2.winning_bid second_winning_bid, a1.winning_bid
			  	, IF (a1.solved
			    	, 'Solved'
					, IF(a1.relist_failed=1
	  			    	, CONCAT('listing error<br><a target=''_blank'' href=''http://',cas.server,'/relist_auction.php?number=', a1.auction_number, '&txnid=', a1.txnid, '''> try again </a>')
					  	, IF(DATEDIFF( NOW( ) , a1.end_time1) >$inactiveDays
		  			        , 'INACTIVE'
						    , IF(LEFT(l.auction_type_vch, 1)='S'
							    , 'Relist NOT possible'
							  	, IF(a2.auction_number is not null 
									, CONCAT('<a href=''search.php?what=number&number=', a2.auction_number, '''>', a2.auction_number, '</a>')
									, CONCAT('<a target=''_blank'' href=''http://',cas.server,'/relist_auction.php?number=', a1.auction_number, '&txnid=', a1.txnid, '''> Relist </a>')
								)
							)
				  		)
					)
			  	) second_auction_link
			FROM auction a1
			LEFT JOIN offer ON offer.offer_id = a1.offer_id 
			LEFT JOIN listings l ON a1.auction_number = l.auction_number
			LEFT JOIN auction a2 ON a2.auction_number = (SELECT auction_number FROM auction
				WHERE auction_number_prev = a1.auction_number AND auction_number <> a1.auction_number LIMIT 1 )
			LEFT JOIN seller_information si ON a1.username = si.username
			LEFT JOIN config_api_system cas ON cas.id = si.config_api_system_id
			WHERE l.auction_number IS NULL 
				and (a1.process_stage = ".STAGE_NO_WINNER.")
			and a1.auction_number=a1.auction_number_prev	
			and a2.auction_number is null # and a1.auction_number_prev is null
	UNION 
			SELECT a1.saved_id, 0 deleted, a1.solved, 'not sold' as reason
				, IF(a1.solved OR a1.relist_failed=1 OR DATEDIFF( NOW( ) , a1.end_time1)>$inactiveDays, 'gray', '') solvedcolor
				, LEFT(a1.auction_type_vch, 1) type, a1.auction_type, a1.auction_type_vch
				, a2.auction_number, NULL as txnid2, a1.auction_number auction_number_prev, NULL as txnid, a1.siteid, a1.quantity, a1.username
			  	, offer.name as offer_name, a1.offer_id
				, a1.start_time1, a2.start_time2, a1.start_time1 start_time
				, a1.end_time1, a2.end_time2, a1.end_time1 as end_time, a1.end_time end_time_
				, a1.listing_fee1, a2.listing_fee2, a1.listing_fee
				, 0 as first_winning_bid, 0 as second_winning_bid, 0 as winning_bid
			  	, IF (a1.solved
			    	, 'Solved'
					, IF(a1.relist_failed=1
	  			    	, CONCAT('listing error<br><a target=''_blank'' href=''http://',cas.server,'/relist_auction.php?number=', a1.auction_number, '''> try again </a>')
					  	, IF(DATEDIFF( NOW( ) , a1.end_time1) >$inactiveDays
		  			        , 'INACTIVE'
						    , IF(LEFT(a1.auction_type_vch, 1)='S'
							    , 'Relist NOT possible'
							  	, IF(a2.auction_number is not null 
								    , CONCAT('<a href=''search.php?what=fix_number&fix_number=', a2.auction_number, '''>', a2.auction_number, '</a>')
									, CONCAT('<a target=''_blank'' href=''http://',cas.server,'/relist_auction.php?number=', a1.auction_number, '''> Relist </a>')
								)
							)
				  		)
					)
			  	) second_auction_link
			FROM listings a1
			LEFT JOIN offer ON offer.offer_id = a1.offer_id 
			LEFT JOIN listings a2 ON a2.auction_number = (SELECT auction_number FROM listings
				WHERE auction_number_prev = a1.auction_number AND auction_number <> a1.auction_number LIMIT 1 )
			LEFT JOIN seller_information si ON a1.username = si.username
			LEFT JOIN config_api_system cas ON cas.id = si.config_api_system_id
			WHERE a1.finished=0 
			and a2.auction_number is null and a1.auction_number=a1.auction_number_prev
				and not exists (select 1 from auction a where a.auction_number = a1.auction_number)
			) a
		WHERE (1=1 OR ((a.txnid<>0 OR a.txnid IS NULL) 
		AND not exists (select auction_number from orders where orders.auction_number = a.auction_number)
		)) 
		AND a.quantity<=1 AND (UNIX_TIMESTAMP('$date') > UNIX_TIMESTAMP( a.end_time1 )) AND a.end_time1<>'0000-00-00 00:00:00' 
		$where
	union all 
			SELECT a.saved_id, a.deleted deleted, a.solved, 'complain' as reason
				, IF(a.solved OR a.relist_failed=1 OR DATEDIFF( NOW( ) , a.end_time1)>$inactiveDays, 'gray', '') solvedcolor
				, IFNULL(LEFT(l.auction_type_vch, 1), 'A') type, 1 AS auction_type, 'Chinese' AS auction_type_vch
				, a2.auction_number, a2.txnid txnid2, a.auction_number auction_number_prev, a.txnid, a.siteid, a.quantity, a.username
			  	, CONCAT(IF(a.solved, '<font color=gray>', IF(NOT a.deleted,'<font color=green>','<font>')),offer.name,'</font>') as offer_name, a.offer_id
				, a.start_time1, a2.start_time2, a.start_time1 start_time
				, a.end_time1, a2.end_time2, a.end_time1 as end_time, a.end_time end_time_
				, a.listing_fee1, a2.listing_fee2, a.listing_fee
				, a.winning_bid first_winning_bid, a2.winning_bid second_winning_bid, a.winning_bid
			  	, IF (a.solved
			    	, 'Solved'
					, IF(a.relist_failed=1
	  			    	, CONCAT('listing error<br><a target=''_blank'' href=''http://',cas.server,'/relist_auction.php?number=', a.auction_number, '&txnid=', a.txnid, '''> try again </a>')
					  	, IF(DATEDIFF( NOW( ) , a.end_time1) >$inactiveDays
		  			        , 'INACTIVE'
						    , IF(LEFT(l.auction_type_vch, 1)='S'
							    , 'Relist NOT possible'
						  		, IF(TO_DAYS(NOW())-TO_DAYS(a.complain_set_date)<=$mindaysforcomplain
									, 'Wait complain'
								  	, IF(a2.auction_number is not null 
										, CONCAT('<a href=''search.php?what=number&number=', a2.auction_number, '''>', a2.auction_number, '</a>')
										, CONCAT('<a target=''_blank'' href=''http://',cas.server,'/relist_auction.php?number=', a.auction_number, '&txnid=', a.txnid, '''> Relist </a>')
									)
								)
							)
				  		)
					)
			  	) second_auction_link
				, DATEDIFF( NOW( ) , a.end_time1) diff
			FROM auction a
			LEFT JOIN offer ON offer.offer_id = a.offer_id 
			LEFT JOIN listings l ON a.auction_number = l.auction_number
			LEFT JOIN auction a2 ON a2.auction_number = (SELECT auction_number FROM auction
				WHERE auction_number_prev = a.auction_number AND auction_number <> a.auction_number LIMIT 1 )
			LEFT JOIN seller_information si ON a.username = si.username
			LEFT JOIN config_api_system cas ON cas.id = si.config_api_system_id
			WHERE a.complained=1 and a.txnid 
				and ((a.relist_failed=2 and a.auction_number_prev = a.auction_number) OR (a.relist_failed<>2))
			and a2.auction_number is null and a.auction_number_prev is null
				$where
			order by end_time1
			";
	elseif ($step==2)		
		$qry = "select distinct a.*, DATEDIFF( NOW( ) , a.end_time2) diff from 
			(
			SELECT a.saved_id, aprev.deleted prev_deleted, a.deleted, a.solved, IF(IFNULL(a.complained, 0),'complain','not sold') as reason
				, 'A' type, 1 AS auction_type, 'Chinese' AS auction_type_vch
				, a.auction_number, a.txnid, a.auction_number_prev, a.siteid, a.quantity, a.username
			  	, CONCAT(IF(a.solved, '<font color=gray>', IF(NOT a.deleted,'<font color=green>','<font>')),offer.name,'</font>') as offer_name, a.offer_id
				, IFNULL(aprev.start_time1, a.start_time1) start_time1, a.start_time2, a.start_time start_time
				, IFNULL(aprev.end_time1, a.end_time1) end_time1, a.end_time2, a.end_time2 as end_time, a.end_time end_time_
				, IFNULL(aprev.listing_fee1, a.listing_fee1) listing_fee1, a.listing_fee2, a.listing_fee
				, aprev.winning_bid first_winning_bid, a.winning_bid second_winning_bid, a.winning_bid
			  	, CONCAT('<a ',IF(a.deleted, 'style=''text-decoration:line-through''', ''),
					' href=''search.php?what=number&number=', a.auction_number, '''>', a.auction_number, '</a>') second_auction_link
			FROM auction a
			LEFT JOIN offer ON offer.offer_id = a.offer_id 
			LEFT JOIN listings l ON a.auction_number = l.auction_number
			LEFT JOIN auction aprev ON aprev.auction_number = a.auction_number_prev
			WHERE l.auction_number IS NULL
				and (a.process_stage = ".STAGE_NO_WINNER." )
				and a.auction_number_prev<>a.auction_number
	UNION 
			SELECT distinct l.saved_id, aprev.deleted prev_deleted, 0 deleted, l.solved, 'not sold' as reason
				, LEFT(l.auction_type_vch, 1) type, l.auction_type, l.auction_type_vch
				, l.auction_number, NULL AS txnid, l.auction_number_prev, l.siteid, l.quantity, l.username
				, offer.name as offer_name, l.offer_id
				, IFNULL(lprev.start_time1, l.start_time1) start_time1, l.start_time2, l.start_time
				, IFNULL(lprev.end_time1, l.end_time1) end_time1, l.end_time2, l.end_time2 end_time, l.end_time end_time_
				, IFNULL(lprev.listing_fee1, l.listing_fee1) listing_fee1, l.listing_fee2, l.listing_fee
				, 0 as first_winning_bid, 0 as second_winning_bid, 0 as winning_bid
				, CONCAT('<a href=''search.php?what=fix_number&fix_number=', l.auction_number, '''>', l.auction_number, '</a>') second_auction_link
			FROM listings l
			LEFT JOIN offer ON offer.offer_id = l.offer_id 
			LEFT JOIN listings lprev ON l.auction_number_prev = lprev.auction_number
			LEFT JOIN auction aprev ON l.auction_number_prev = aprev.auction_number
			WHERE l.finished=0 
			and not exists (select 1 from auction a where a.auction_number = l.auction_number)
			and l.auction_number_prev<>l.auction_number
		) a
		WHERE not exists (select auction_number from orders where orders.auction_number = a.auction_number)
		AND a.quantity<=1 AND (UNIX_TIMESTAMP('$date') > UNIX_TIMESTAMP( a.end_time2 )) AND a.end_time2<>'0000-00-00 00:00:00' 
		$where
	union all 
		SELECT a.saved_id, aprev.deleted prev_deleted, a.deleted, a.solved, 'complain' as reason
			, IFNULL(LEFT(l.auction_type_vch, 1), 'A') type, 1 AS auction_type, 'Chinese' AS auction_type_vch
			, a.auction_number, a.txnid, a.auction_number_prev, a.siteid, a.quantity, a.username
			, CONCAT(IF(a.solved, '<font color=gray>', IF(NOT a.deleted,'<font color=green>','<font>')),offer.name,'</font>') as offer_name, a.offer_id
			, IFNULL(aprev.start_time1, a.start_time1) start_time1, a.start_time2, a.start_time start_time
			, IFNULL(aprev.end_time1, a.end_time1) end_time1, a.end_time2, a.end_time2 as end_time, a.end_time end_time_
			, IFNULL(aprev.listing_fee1, a.listing_fee1) listing_fee1, a.listing_fee2, a.listing_fee
			, aprev.winning_bid first_winning_bid, a.winning_bid second_winning_bid, a.winning_bid
		  	, CONCAT('<a ',IF(a.deleted, 'style=''text-decoration:line-through''', ''),
				' href=''search.php?what=number&number=', a.auction_number, '''>', a.auction_number, '</a>') second_auction_link
			, DATEDIFF( NOW( ) , a.end_time2) diff
			FROM auction a
			LEFT JOIN offer ON offer.offer_id = a.offer_id 
			LEFT JOIN listings l ON a.auction_number = l.auction_number
			LEFT JOIN auction aprev ON aprev.auction_number = a.auction_number_prev
			WHERE a.complained=1 and a.txnid
				and a.auction_number_prev<>a.auction_number
				$where
			order by end_time2
			"; 
/*first part 
			SELECT a.deleted, a.solved, 'not sold' as reason
				, LEFT(l.auction_type_vch, 1) type, l.auction_type, l.auction_type_vch
				, a.auction_number, a.txnid, l.auction_number_prev, a.siteid, l.quantity, l.username
			    , offer.name as offer_name, a.offer_id
				, aprev.start_time1, l.start_time2, l.start_time
				, aprev.end_time1, l.end_time2, a.end_time2 as end_time, l.end_time as end_time_
  			    , aprev.listing_fee1, l.listing_fee2, l.listing_fee
				, aprev.winning_bid first_winning_bid, a.winning_bid second_winning_bid, a.winning_bid
			    , CONCAT('<a href=''search.php?what=number&number=', l.auction_number, '''>', l.auction_number, '</a>') second_auction_link
			FROM auction a
			LEFT JOIN offer ON offer.offer_id = a.offer_id 
			JOIN listings l ON a.auction_number = l.auction_number 
			LEFT JOIN auction aprev ON aprev.auction_number = a.auction_number_prev
			WHERE l.finished=0  and l.relist_failed<>2 and l.auction_number_prev<>l.auction_number
	UNION 
*/
/*		if ($offer_id) 
		{ 
		echo $qry; die();
		}*/
//		echo $qry;//die();
		global $dbr_spec;
        $r = $dbr_spec->getAll($qry);
        if (PEAR::isError($r)) {
//            $this->_error = $r;
            print_r($r);
            return;

        }
        return $r;
    }

    static function getBoughtItems($db, $dbr, $username, $auction_number=0, $txnid=0)
    {
	  global $seller_filter;
	if (count($seller_filter)) $seller_filter_str1 = " and au.username in ($seller_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (strlen($username)) {
			$where = "and au.username_buyer = '".$username."'";
		} else {
			$where = "and au.auction_number = ".$auction_number." AND au.txnid=".$txnid;
		}		
	        $q = "SELECT au.auction_number, au.txnid, a.article_id
				, translation.value as name
				, au.invoice_number, au.end_time, o.quantity
				FROM auction au
				JOIN orders o ON au.auction_number = o.auction_number AND au.txnid = o.txnid
				JOIN article_list al ON o.article_list_id = al.article_list_id
				JOIN article a ON a.article_id = o.article_id
					AND o.manual = a.admin_id
				join translation on table_name = 'article'
					AND field_name = 'name'
					AND language = 'german'
					AND translation.id = a.article_id
				WHERE 1
				$where
				AND NOT au.deleted and o.manual=0
				$seller_filter_str1
			union all
				SELECT au.auction_number, au.txnid, a.article_id
				, translation.value as name
				, au.invoice_number, au.end_time, o.quantity
				FROM auction sau
				left join auction au on au.auction_number=sau.main_auction_number and au.txnid=sau.main_txnid
				JOIN orders o ON au.auction_number = o.auction_number AND au.txnid = o.txnid
				JOIN article_list al ON o.article_list_id = al.article_list_id
				JOIN article a ON a.article_id = o.article_id
					AND o.manual = a.admin_id
				join translation on table_name = 'article'
					AND field_name = 'name'
					AND language = 'german'
					AND translation.id = a.article_id
				WHERE 1
				$where
				AND NOT au.deleted and o.manual=0
				$seller_filter_str1
				ORDER BY article_id, auction_number, txnid";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            print_r($r); 
            return;
        }
        return $r;
    }

    static function findRating($db, $dbr, $open='', $resp)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($open!='') $where = " and ".($open?'NOT':'')." IFNULL(rating.closed, 0)";
		$days_inactivity = Config::get($db, $dbr, 'days_inactivity');
		$days_rating_remove = Config::get($db, $dbr, 'days_rating_remove');
		$q = "SELECT distinct auction.*, 
			rating.id as rating_id,
			rating.date as rating_date,
			responsible_username as rating_responsible_username,
			customer_email_date,
			customer_email_username,
			request_deleting_date,
			request_deleting_username,
			answer_date,
			answer_username,
			rate_date,
			rate_username,
			rating.close_date as rating_close_date,
		    IF(rating.resolved=1, 'Yes', 'No') resolved
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, IF(rating.closed, rating.close_date, null) rating_close_date
			, min(auction_feedback.datetime) rating_received_date_first
			,(select DATEDIFF(NOW(), max(create_date)) from ( 
				select create_date, rating_id, 'rating' `type` from rating_comment 
				union select create_date, rma_id, 'rma' from rma_comment 
				)t where (t.rating_id=rating.id and t.type='rating')
				or (t.rating_id=rma.rma_id and t.type='rma') 
			) inactivity
			, seller_information.possible_rating_remove - DATEDIFF(NOW(), min(auction_feedback.datetime)) as take_rating_back_left
			, DATE_ADD(auction.rating_received_date, INTERVAL seller_information.possible_rating_remove DAY) as take_rating_back
			, IFNULL(users.name, rating.responsible_username) responsible_username
			FROM auction 
			JOIN seller_information ON seller_information.username = auction.username
			JOIN rating ON rating.auction_number = auction.auction_number AND rating.txnid = auction.txnid 
			LEFT JOIN users ON rating.responsible_username = users.username
			left JOIN rma ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid 
			left JOIN auction_feedback ON auction_feedback.auction_number = auction.auction_number AND auction_feedback.txnid = auction.txnid 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE 1=1
			and main_auction_number=0
		and (IFNULL(rating.responsible_username,0)='$resp' or '$resp'='')
		".$where
		.$seller_filter_str
		." group by auction.auction_number, auction.txnid";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
		foreach($r as $k=>$dummy){
			if ($r[$k]->inactivity > $days_inactivity) $r[$k]->inactivity = '<font color="red">'.$r[$k]->inactivity.'</font>';
			if ($r[$k]->take_rating_back_left < $days_rating_remove) $r[$k]->take_rating_back_left = '<font color="red">'.$r[$k]->take_rating_back_left.'</font>';
		}
        return $r;
    }

    static function findInsurance($db, $dbr, $company=0, $open='', $rating='', $shipping_username='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($open!='') $where = " and ".($open?'':'NOT')." ins.closed=0 ";
		if ((int)($company)) {
			$where .= ' AND ins.shipping_method='.$company;
		};	
		if ((int)($rating)) {
			$where .= ' AND auction.rating_received='.$rating;
		};	
		if (strlen($shipping_username)) {
			$where .= " AND auction.shipping_username='$shipping_username'";
		};	
        $r = $dbr->getAll("SELECT distinct 
			CONCAT('<a target=\"_blank\" href=\"auction.php?number=',ins.auction_number,'&txnid=',ins.txnid,'\">',ins.auction_number,'/',ins.txnid,'</a>'
				) auction_number_href,
			(select group_concat(number separator '<br>') from tracking_numbers tn where tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
				) tracking_numbers,
			auction.*, sm.company_name as shipping_method_name, ins.rma_id,
				ins.id ins_id, ins.date create_date, ins.close_date, ins.responsible_username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				,(select max(time) from ins_log where ins_id=ins.id and action='send_announce_insurance') as shipping_company_email_date
				,(select max(time) from ins_log where ins_id=ins.id and action='send_insurance') as insurance_mail_date
				,(select IF(users.shipping_company, CONCAT('<font color=".'"green"'."><b>', create_date, '</b></font>'), create_date) 
				from ins_comment join users on ins_comment.username = users.username
				where ins_id=ins.id order by create_date desc limit 0, 1) as last_comment
				,IFNULL((select sum(cost) from ins_article where ins_id=ins.id and problem_id in (3, 7, 8) and not IFNULL(hidden, 0)), 0)
			 	  +IFNULL((select sum(amount_to_refund) from ins_sh_refund where ins_id=ins.id), 0)
				  -IFNULL((select sum(amount) from ins_payment where ins_id=ins.id), 0) ins_open_amount
							, TO_DAYS(NOW())-TO_DAYS(auction.end_time) days_due
			FROM auction JOIN insurance ins ON ins.auction_number = auction.auction_number AND ins.txnid = auction.txnid 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			LEFT JOIN shipping_method sm ON sm.shipping_method_id = ins.shipping_method
		WHERE 1=1
			and main_auction_number=0
		".$where
		.$seller_filter_str);
        if (PEAR::isError($r)) {
		aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findInsuranceBy($db, $dbr, $user, $open='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if (strlen($user)) {
			$where = " AND ins.responsible_username='$user' ";
		};	
		if ($open!='') $where .= " and ".($open?'':'NOT')." ins.closed=0 ";
		
        $r = $dbr->getAll("SELECT distinct 
			CONCAT('<a target=\"_blank\" href=\"auction.php?number=',ins.auction_number,'&txnid=',ins.txnid,'\">',ins.auction_number,'/',ins.txnid,'</a>'
				) auction_number_href,
			(select group_concat(number separator '<br>') from tracking_numbers tn where tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
				) tracking_numbers,
			auction.*, sm.company_name as shipping_method_name, ins.rma_id,
				ins.id ins_id, ins.date create_date, ins.close_date, ins.responsible_username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				,(select max(time) from ins_log where ins_id=ins.id and action='send_announce_insurance') as shipping_company_email_date
				,(select max(time) from ins_log where ins_id=ins.id and action='send_insurance') as insurance_mail_date
				,(select IF(users.shipping_company, CONCAT('<font color=".'"green"'."><b>', create_date, '</b></font>'), create_date) 
				from ins_comment join users on ins_comment.username = users.username
				where ins_id=ins.id order by create_date desc limit 0, 1) as last_comment
				,IFNULL((select sum(cost) from ins_article where ins_id=ins.id and problem_id in (3, 7, 8) and not IFNULL(hidden, 0)), 0)
			 	  +IFNULL((select sum(amount_to_refund) from ins_sh_refund where ins_id=ins.id), 0)
				  -IFNULL((select sum(amount) from ins_payment where ins_id=ins.id), 0) ins_open_amount
				, IFNULL(
					(select CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number)
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					where co.obj_id=offer.offer_id 
						and co.obj='offer')
					, (select GROUP_CONCAT(CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number) SEPARATOR '<br><br>')
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					join auction subs on subs.offer_id=co.obj_id and co.obj='offer' and co.level=4
					where subs.main_auction_number=auction.auction_number and subs.main_txnid=auction.txnid
					)) offer_classifier
			FROM auction JOIN insurance ins ON ins.auction_number = auction.auction_number AND ins.txnid = auction.txnid 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			LEFT JOIN shipping_method sm ON sm.shipping_method_id = ins.shipping_method
		WHERE 1=1
			and main_auction_number=0
		".$where
		.$seller_filter_str);
        if (PEAR::isError($r)) {
		aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findInsuranceByName($db, $dbr, $name='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("SELECT distinct 
			CONCAT('<a target=\"_blank\" href=\"auction.php?number=',ins.auction_number,'&txnid=',ins.txnid,'\">',ins.auction_number,'/',ins.txnid,'</a>'
				) auction_number_href,
			(select group_concat(number separator '<br>') from tracking_numbers tn where tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
				) tracking_numbers,
			auction.*, sm.company_name as shipping_method_name,
				ins.id ins_id, ins.date create_date, ins.close_date, ins.responsible_username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				,(select max(time) from ins_log where ins_id=ins.id and action='insurance_letter') as shipping_company_email_date
				,(select max(time) from ins_log where ins_id=ins.id and action='send_announce_insurance') as shipping_company_email_date
				,(select max(time) from ins_log where ins_id=ins.id and action='send_insurance') as insurance_mail_date
				,(select IF(users.shipping_company, CONCAT('<font color=".'"green"'."><b>', create_date, '</b></font>'), create_date) 
				from ins_comment join users on ins_comment.username = users.username
				where ins_id=ins.id order by create_date desc limit 0, 1) as last_comment
				,IFNULL((select sum(cost) from ins_article where ins_id=ins.id and problem_id in (3, 7, 8) and not IFNULL(hidden, 0)), 0)
			 	  +IFNULL((select sum(amount_to_refund) from ins_sh_refund where ins_id=ins.id), 0)
				  -IFNULL((select sum(amount) from ins_payment where ins_id=ins.id), 0) ins_open_amount
			FROM auction 
			left join auction_par_varchar aupv on auction.auction_number=aupv.auction_number and auction.txnid=aupv.txnid
				 and aupv.key in ('firstname_shipping','firstname_invoice','name_shipping','name_invoice','company_shipping','company_invoice') 
			JOIN insurance ins ON ins.auction_number = auction.auction_number AND ins.txnid = auction.txnid 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			LEFT JOIN shipping_method sm ON sm.shipping_method_id = ins.shipping_method
		WHERE 1=1 
			and main_auction_number=0
		AND UPPER(aupv.value) like UPPER('%$name%') "
		.$seller_filter_str);
        if (PEAR::isError($r)) {
		aprint_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findInsuranceOpened($db, $dbr, $days=99999, $sh_company='', $decision='', $accepted='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($accepted=='0') $accepted = ' and (rma_spec.ins_accepted IS NULL OR NOT rma_spec.ins_accepted)';
		if ($accepted=='1') $accepted = ' and (rma_spec.ins_accepted)';
		if ($decision=='0') $decision = ' and (rma_spec.ins_decision IS NULL OR NOT rma_spec.ins_decision)';
		if ($decision=='1') $decision = ' and (rma_spec.ins_decision)';
		if (!(int)$days) $days=9999;
		if ((int)($sh_company)) {
			$shipping_method_id = ' AND rma_spec.ins_shipping_method_id='.$sh_company;
		};	
        $r = $dbr->getAll("SELECT distinct 
			CONCAT('<a target=\"_blank\" href=\"auction.php?number=',ins.auction_number,'&txnid=',ins.txnid,'\">',ins.auction_number,'/',ins.txnid,'</a>'
				) auction_number_href,
			(select group_concat(number separator '<br>') from tracking_numbers tn where tn.auction_number=auction.auction_number and tn.txnid=auction.txnid
				) tracking_numbers,
			auction.*, rma.rma_id, rma.create_date rma_create_date
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			FROM auction JOIN rma ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid 
			JOIN rma_spec ON rma.rma_id = rma_spec.rma_id
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE rma_spec.ins_value AND (UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( rma_spec.ins_date )) <= $days*24*60*60
			and main_auction_number=0
		".$decision.$accepted.$shipping_method_id
		.$seller_filter_str);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findShipmentOpened($db, $dbr, $days=99999, $sh_company='', $decision='', $accepted='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findOpenedRMA expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($accepted=='0') $accepted = ' and (rma_sh_refund.sh_accepted IS NULL OR NOT rma_sh_refund.sh_accepted)';
		if ($accepted=='1') $accepted = ' and (rma_sh_refund.sh_accepted)';
		if ($decision=='0') $decision = ' and (rma_sh_refund.sh_decision IS NULL OR NOT rma_sh_refund.sh_decision)';
		if ($decision=='1') $decision = ' and (rma_sh_refund.sh_decision)';
		if (!(int)$days) $days=9999;
		if ((int)($sh_company)) {
			$shipping_method_id = ' AND tracking_numbers.shipping_method='.$sh_company;
		};	
        $r = $dbr->getAll("SELECT distinct auction.*, rma.rma_id, rma.create_date rma_create_date
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			FROM auction JOIN rma ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid 
			JOIN rma_sh_refund ON rma.rma_id = rma_sh_refund.rma_id
			JOIN tracking_numbers ON tracking_numbers.id = rma_sh_refund.sh_tracking_id
			LEFT JOIN offer ON offer.offer_id = auction.offer_id 
		WHERE rma_sh_refund.sh_value AND (UNIX_TIMESTAMP( ) - UNIX_TIMESTAMP( rma_sh_refund.sh_date )) <= $days*24*60*60
			and main_auction_number=0
		".$decision.$accepted.$shipping_method_id
		.$seller_filter_str);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

/*    static function findByTrackingNumber($db, $dbr, $tnumber)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $tnumber = mysql_escape_string($tnumber);
        $r = $dbr->getAll("SELECT distinct auction.*, offer.name as offer_name 
			FROM auction 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			JOIN tracking_numbers tn ON tn.auction_number = auction.auction_number AND tn.txnid = auction.txnid 
		WHERE tn.number LIKE '".$tnumber."%'");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }*/

    static function findByTrackingNumber($db, $dbr, $tnumber, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $tnumber = mysql_escape_string($tnumber);
        $r = $dbr->getAll("SELECT distinct 
			IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, fget_Currency(auction.siteid) currency
			, auction.auction_number
			, auction.txnid
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.siteid
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
			, invoice.open_amount
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		FROM auction
        JOIN invoice ON invoice.invoice_number = auction.invoice_number 
		left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
			and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
			and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		JOIN tracking_numbers tn ON tn.auction_number = auction.auction_number AND tn.txnid = auction.txnid 
		WHERE tn.number LIKE '".$tnumber."%'
			and main_auction_number=0
		$seller_filter_str
			$sort
			LIMIT $from, $to
		");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByTrackingNumberCount($db, $dbr, $tnumber)
    {
	  global $seller_filter_str;
        $cnt = $dbr->getOne("select count(*) from (SELECT distinct auction.*
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			FROM auction 
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			JOIN tracking_numbers tn ON tn.auction_number = auction.auction_number AND tn.txnid = auction.txnid 
		WHERE tn.number LIKE '".$tnumber."%' 
			and main_auction_number=0
		$seller_filter_str
		) t");
        return $cnt;
    }

    /**
    * Search in auctions by label 
    * @return object - result of search
    * @param string $lnumber
    */
    static function findByLabelNumber($lnumber, $from=0, $to=9999999, $sort)
    {
        global $seller_filter_str;
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $lnumber = mysql_escape_string($lnumber);
        $r = $dbr->getAll("SELECT distinct 
           IFNULL(offer.name,
               (select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
                   join auction a1 on a1.offer_id=o1.offer_id
                   where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
           , fget_Currency(auction.siteid) currency
           , auction.auction_number
           , auction.txnid
           , auction.end_time
           , fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
           , auction.responsible_uname
           , auction.siteid
           , auction.username
           , auction.winning_bid
           , auction.offer_id
           , au_city_shipping.value as city_shipping
           , CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
           , invoice.open_amount
           , fget_ACustomer(auction.auction_number, auction.txnid) customer
        FROM auction
           JOIN invoice ON invoice.invoice_number = auction.invoice_number 
           left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
                and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
           left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
                and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
            left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
                and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
            LEFT JOIN offer ON offer.offer_id = auction.offer_id
            LEFT JOIN auction_label al ON auction.auction_number = al.auction_number and auction.txnid = al.txnid
        WHERE al.tracking_number = '{$lnumber}'
            and main_auction_number=0
            $seller_filter_str
            $sort
        LIMIT $from, $to
        ");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    /**
    * Getting count of search result by label
    * @return int - count of finded by label
    * @param string $lnumber
    */
    static function findByLabelNumberCount($lnumber)
    {
        global $seller_filter_str;
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $lnumber = mysql_escape_string($lnumber);
        $cnt = $dbr->getOne("select count(*) from (SELECT distinct auction.*
            , IFNULL(offer.name,
                (select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
                    join auction a1 on a1.offer_id=o1.offer_id
                    where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
        FROM auction 
            LEFT JOIN offer ON offer.offer_id = auction.offer_id
            LEFT JOIN auction_label al ON auction.auction_number = al.auction_number and auction.txnid = al.txnid
        WHERE al.tracking_number = '{$lnumber}'
            and main_auction_number=0
        $seller_filter_str
        ) t");
        return $cnt;
    }

	static function makeSubinvoiceFor($main_auction_number, $main_txnid)
	{
        $r = $this->_db->query("UPDATE article_history 
			set comment='Auction ".$main_auction_number." / ".$main_txnid." (subinvoice ".$this->data->auction_number." / ".$this->data->txnid.")' 
			WHERE comment like 'Auction ".$this->data->auction_number." / ".$this->data->txnid."'");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $r = $this->_db->query("UPDATE article_history 
			set comment='Reserved by Auction ".$main_auction_number." / ".$main_txnid." (subinvoice ".$this->data->auction_number." / ".$this->data->txnid.")' 
			WHERE comment like 'Reserved by Auction ".$this->data->auction_number." / ".$this->data->txnid."'");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $r = $this->_db->query("UPDATE auction SET main_auction_number=".$main_auction_number."
			, main_txnid=".$main_txnid.", deleted=1 WHERE auction_number=".$this->data->auction_number." AND txnid=".$this->data->txnid);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
	}

	static function releaseSubinvoice($db, $dbr, $rnumber, $rtxnid)
	{
        $r = $db->query("UPDATE article_history 
			set comment='Auction ".$rnumber." / ".$rtxnid."' 
			WHERE comment like 'Auction % (subinvoice ".$rnumber." / ".$rtxnid.")'");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $r = $db->query("UPDATE article_history 
			set comment='Reserved by Auction ".$rnumber." / ".$rtxnid."' 
			WHERE comment like 'Reserved by Auction % (subinvoice ".$rnumber." / ".$rtxnid.")'");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $r = $db->query("UPDATE auction SET main_auction_number=NULL, main_txnid=NULL, deleted=0 WHERE auction_number=".$rnumber." AND txnid=".$rtxnid);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
	}
	
    static function findByOpenAmount($db, $dbr, $amount_from='', $mode = 'open', $amount_to = '', $currency = '')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $amount = mysql_escape_string($amount);
		$fld1 = array(); $fld2 = array();
		if ($mode != 'paid') {
			if (strlen($amount_from)) $fld1[] = 'invoice.open_amount >= '.$amount_from;
			if (strlen($amount_to)) $fld1[] = 'invoice.open_amount <= '.$amount_to;
		}
		if ($mode != 'open') {
			if (strlen($amount_from)) $fld2[] = " (IFNULL(invoice.total_price,0) + IFNULL(invoice.total_shipping,0) + IFNULL(invoice.total_cod,0) + IFNULL(invoice.total_cc_fee,0) - IFNULL(invoice.open_amount,0)) >= ".$amount_from;
			if (strlen($amount_to)) $fld2[] = " (IFNULL(invoice.total_price,0) + IFNULL(invoice.total_shipping,0) + IFNULL(invoice.total_cod,0) + IFNULL(invoice.total_cc_fee,0) - IFNULL(invoice.open_amount,0)) <= ".$amount_to;
		}
		$where_a = array();
		if (count($fld1)) $where_a[] = "(".implode(' and ',$fld1).")";
		if (count($fld2)) $where_a[] = "(".implode(' and ',$fld2).")";
		if (count($where_a)) $where .= " and (".implode(' or ',$where_a).")";
		if (strlen($currency)) {
			$sites = $dbr->getAssoc("SELECT ca.siteid f1, ca.siteid f2
				FROM config_api ca 
				JOIN config_api_par cap ON ca.par_id=cap.id
				left join config_api_values cav on ca.par_id=cav.par_id and cav.value=ca.value
				WHERE 1
				AND cap.name = 'currency'
				and IFNULL(cav.value, ca.value)='$currency'");
			$where .= " and auction.siteid in (".implode(',',$sites).")";
		}
		$q = "SELECT auction.auction_number, auction.txnid, invoice.total_price, invoice.total_shipping, invoice.total_cod
			, invoice.open_amount
			, IFNULL(invoice.total_price,0) + IFNULL(invoice.total_shipping,0) + IFNULL(invoice.total_cod,0) + IFNULL(invoice.total_cc_fee,0) - IFNULL(invoice.open_amount,0) as paid
			, auction.end_time
			, fget_delivery_date_real(auction.auction_number, auction.txnid) delivery_date_real
			, auction.responsible_uname
			, auction.siteid
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		, fget_ACustomer(auction.auction_number, auction.txnid) customer
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			FROM auction 
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
			left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			JOIN invoice ON auction.invoice_number = invoice.invoice_number
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			where 1
			and main_auction_number=0
			$where
		$seller_filter_str";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r); 
            return;
        }
        return $r;
    }

    static function findReserved($db, $dbr, $days=999, $from=0, $to=9999999, $sort, $where = '')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT SQL_CALC_FOUND_ROWS auction.auction_number, auction.txnid
			, auction.siteid
			, auction.end_time
			, invoice.open_amount
			, fget_Currency(auction.siteid) currency
			, auction.delivery_date_customer
			, IFNULL(users.name, auction.responsible_uname) responsible_username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, 
			#(select min(date) from email_log where template = 'order_confirmation'
			#		AND auction_number = auction.auction_number
			#		AND txnid = auction.txnid)	
			invoice.invoice_date date_confirmation
		FROM auction 
		JOIN invoice ON auction.invoice_number = invoice.invoice_number
                left join orders on auction.auction_number = orders.auction_number and auction.txnid = orders.txnid and orders.manual=0
                left join article on orders.article_id=article.article_id and article.admin_id=orders.manual
                left join op_company_emp oce on oce.company_id = article.company_id
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		LEFT JOIN users ON users.username = auction.responsible_uname
		where 1
                $where
		#and (select min(date) from email_log where template = 'order_confirmation'
		#			AND auction_number = auction.auction_number
		#			AND txnid = auction.txnid) <= DATE_SUB(NOW(), INTERVAL $days DAY) 
		and invoice.invoice_date <= DATE_SUB(NOW(), INTERVAL $days DAY) 
		and orders.sent=0
		and auction.deleted=0 
		and auction.txnid<>4
			and main_auction_number=0
		group by auction.id
		$seller_filter_str 
		$sort LIMIT $from, $to
		";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findReservedCount($db, $dbr, $days=999)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT count(*)
		FROM auction 
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		where auction.end_time <= DATE_SUB(NOW(), INTERVAL $days DAY) 
		and fget_ASent(auction.auction_number, auction.txnid)=0
		and auction.deleted=0
		and txnid<>4
			and main_auction_number=0
		$seller_filter_str 
		";
        $r = $dbr->getOne($q);
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findByArticleID($db, $dbr, $article_id='')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $article_id = mysql_escape_string($article_id);
        $r = $dbr->getAll("SELECT 
			IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, auction.auction_number
			, auction.txnid
			, auction.end_time
			, tl.updated delivery_date_real
			, auction.responsible_uname
			, auction.siteid
			, auction.username
			, auction.winning_bid
			, auction.offer_id
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
		FROM orders o
		left join total_log tl on tl.tableid=o.id and table_name='orders' and field_name='sent' and New_value=1
		JOIN auction ON o.auction_number = auction.auction_number
			AND o.txnid = auction.txnid
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
			left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
			and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		WHERE o.article_id='$article_id'
		$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findExportTN($db, $dbr, $export_trnum, $from=0, $to=9999999, $sort) {
	  global $seller_filter;
	if (count($seller_filter)) $seller_filter_str1 = " and au.username in ($seller_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        if ($export_trnum==1)
           $where = ' and (tn.doc is not null and LENGTH(tn.doc)>0)'; 
        if ($export_trnum==-1)
           $where = ' and (tn.doc is null)'; 
	$q = "SELECT tn.number, i.invoice_date, au.auction_number, au.txnid, 
		au_country_invoice.value as country_invoice, au_country_shipping.value as country_shipping, 
		tn.id, tn.doc_name, au.customer_vat
		, fget_Currency(au.siteid) currency
		FROM tracking_numbers tn
		JOIN auction au ON tn.auction_number = au.auction_number
			AND tn.txnid = au.txnid
		left join auction_par_varchar au_country_invoice on au.auction_number=au_country_invoice.auction_number 
			and au.txnid=au_country_invoice.txnid and au_country_invoice.key='country_invoice'
		left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
			and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
		JOIN invoice i ON au.invoice_number=i.invoice_number
		WHERE (au.customer_vat <> '' OR NOT au.eu) 
			$where
			$seller_filter_str1
			$sort
			LIMIT $from, $to
		";
//	echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
//            $this->_error = $r;
			aprint_r( $r);
            return;
        }
		foreach ($r as $key=>$tn) {
			$r[$key]->doc_url = "<a href='doc.php?tnid=".$tn->id."'>".$tn->doc_name."</a>";
		}
        return $r;
	}
	
    static function findExportTNCount($db, $dbr, $export_trnum)
    {
	  global $seller_filter;
	if (count($seller_filter)) $seller_filter_str1 = " and au.username in ($seller_filter) ";
        if ($export_trnum==1)
           $where = ' and (tn.doc is not null)'; 
        if ($export_trnum==-1)
           $where = ' and (tn.doc is null)'; 
	$q = "SELECT count(*)
		FROM tracking_numbers tn
		JOIN auction au ON tn.auction_number = au.auction_number
			AND tn.txnid = au.txnid
		JOIN invoice i ON au.invoice_number=i.invoice_number
		WHERE (au.customer_vat <> '' OR NOT au.eu) 
			$where
			$seller_filter_str1
		";
//	echo $q;
        $cnt = $dbr->getOne($q);
        return $cnt;
    }

    static function getTrackingNumberList($db, $dbr, $pars)
    {
	  global $seller_filter_str;
	  global $method_filter_str;
	  $method_filter_str = str_replace('shipping_method_id', 'IFNULL(shipping_method_id,0)', $method_filter_str);
		if (count($pars['seller_filter'])) $seller_filter_str1 = " and auction.username in ($seller_filter) ";  
		if (strlen($pars['sellerlist'])) $seller_filter_str1 .= " and auction.username in (".$pars['sellerlist'].") ";  
		if (strlen($pars['userlist']))	$seller_filter_str1 .= " and auction.username in ($userlist) ";
		if (!strlen($pars['from'])) $pars['from'] = '0000-00-00';
		if (!strlen($pars['to'])) $pars['to'] = '2999-01-01';
		if ($pars['what']=='pnshipping_date') {
			$what = "date_time between '".$pars['from']."' and '".$pars['to']."' and IFNULL(shipping_date, '0000-00-00 00:00:00')='0000-00-00 00:00:00' ";
		} elseif ($pars['what']=='shipping_date') {
			$what = "(
					(tn.auction_number is not null and (select min(tl.updated)
					from total_log tl
					join orders o on tl.table_name='orders' and tl.tableid=o.id
					join tn_orders tno on tno.order_id=o.id
					where tno.tn_id=tn.id) between '".$pars['from']."' and '".$pars['to']."')
					or
					(tn.auction_number is null and (select max(tl.updated)
					from total_log tl
					join orders o on tl.table_name='orders' and tl.tableid=o.id
					where IFNULL(mauction.auction_number, auction.auction_number)=o.auction_number 
						and IFNULL(mauction.txnid, auction.txnid)=o.txnid
						and o.manual=0) between '".$pars['from']."' and '".$pars['to']."')
					)";
		} else {
			$what = "$what between '".$pars['from']."' and '".$pars['to']."'";
		}
		$where = '';
		if (strlen($pars['shipping_company'])) $where .= " AND auction.shipping_username = '".$pars['shipping_company']."' ";
		if (strlen($pars['shipping_method'])) $where .= " AND tn.shipping_method = '".$pars['shipping_method']."' ";
		if (strlen($pars['shipping_country'])) $where .= " AND au_country_shipping.value = '".$pars['shipping_country']."' ";
		if (strlen($pars['offer_id'])) $where .= " AND auction.offer_id in 
			(select offer_id from offer where name=(select name from offer where offer_id='".$pars['offer_id']."') ";
		$q="#select t.*, DATEDIFF(shipping_username_date, shipping_date1) days_due from (
			SELECT auction.offer_id
				, tn.id
				, tn.date_time
				, tn.number
				, tn.shipping_method
				, tn.shipping_date
				, tn.username
				, tn.doc
				, tn.doc_name
				, tn.called_back_date
				, tn.called_back_by
				, tn.called_back
				, tn.called_back_to
				, tn.stop_date
				, tn.stop_by
				, tn.stopped
				, tn.stop_to
				, tn.packet_id
				, tn.shipping_number
				, tn.monitored
				, tn.solved
				, tn.monitored_date
				, tn.monitored_by
				, tn.solved_date
				, tn.solved_by
				, tn.pickup_date
				, tn.weight
				, tn.sent
				, IFNULL(mauction.auction_number, auction.auction_number) auction_number
				, IFNULL(mauction.txnid, auction.txnid) txnid
				, sm.company_name AS shipping_method_name
				, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as cust_name
				, CONCAT(au_zip_shipping.value, ','
					, au_street_shipping.value, ' ', au_house_shipping.value, ','
					, au_city_shipping.value, ','
					, au_country_shipping.value) cust_addr
				, au_zip_shipping.value zip_shipping
				, au_street_shipping.value street_shipping
				, au_house_shipping.value house_shipping
				, au_city_shipping.value city_shipping
				, au_country_shipping.value country_shipping
				, CONCAT(au_gps_lat_shipping.value, ',', au_gps_lng_shipping.value) gps
				, IFNULL(mauction.id, auction.id) auction_id
/*				, IF(tn.id is null, 
					(select max(tl.updated)
					from total_log tl
					join orders o on tl.table_name='orders' and tl.tableid=o.id
					where IFNULL(mauction.auction_number, auction.auction_number)=o.auction_number 
						and IFNULL(mauction.txnid, auction.txnid)=o.txnid
						and o.manual=0)
					, (select min(tl.updated)
					from total_log tl
					join orders o on tl.table_name='orders' and tl.field_name='sent' 
						and tl.new_value=1 and tl.tableid=o.id 
					join tn_orders tno on tno.order_id=o.id
					where tno.tn_id=tn.id)) shipping_date1
				, (select max(tl.updated)
					from total_log tl
					where tl.table_name='auction' and tl.tableid=IFNULL(mauction.id, auction.id)
						and tl.field_name='shipping_username') shipping_username_date
				, IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					where auction_calcs.auction_number = auction.auction_number AND auction_calcs.txnid = auction.txnid
					),0)
					+ IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					join auction a1 on auction_calcs.auction_number = a1.auction_number AND auction_calcs.txnid = a1.txnid
					where a1.main_auction_number = auction.auction_number AND a1.main_txnid = auction.txnid
					),0)
					 real_shipping_cost*/
			FROM auction
			LEFT JOIN auction mauction ON mauction.auction_number = auction.main_auction_number AND mauction.txnid = auction.main_txnid
			left JOIN tracking_numbers tn  ON tn.auction_number = auction.auction_number AND tn.txnid = auction.txnid
			left JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number 
				and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
			left join auction_par_varchar au_street_shipping on auction.auction_number=au_street_shipping.auction_number 
				and auction.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
			left join auction_par_varchar au_house_shipping on auction.auction_number=au_house_shipping.auction_number 
				and auction.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
			left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
				and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
				and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
				
            left join auction_par_varchar au_gps_lat_shipping on auction.auction_number=au_gps_lat_shipping.auction_number 
				and auction.txnid=au_gps_lat_shipping.txnid and au_gps_lat_shipping.key='gps_lat'
            left join auction_par_varchar au_gps_lng_shipping on auction.auction_number=au_gps_lng_shipping.auction_number 
				and auction.txnid=au_gps_lng_shipping.txnid and au_gps_lng_shipping.key='gps_lng'
				
				
			left JOIN offer ON auction.offer_id = offer.offer_id
			WHERE $what
			$where
			$seller_filter_str1
			$method_filter_str
			group by IFNULL(mauction.id, auction.id), tn.id
			order by concat(IFNULL(mauction.auction_number, auction.auction_number),'/',IFNULL(mauction.txnid, auction.txnid))
#			) t
			";
//		echo $q;	
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		foreach($r as $k=>$dummy) {
			$q = "select sum(a.weight_per_single_unit*o.quantity) weight
					, sum(a.volume_per_single_unit*o.quantity) volume
				, IF('".$r[$k]->id."'='', 
					fget_Adelivery_date_real(".$r[$k]->auction_number.",".$r[$k]->txnid.")
					, (select min(tl.updated)
					from total_log tl
					join orders o on tl.table_name='orders' and tl.field_name='sent' 
						and tl.new_value=1 and tl.tableid=o.id 
					join tn_orders tno on tno.order_id=o.id
					where tno.tn_id='".$r[$k]->id."')) shipping_date1
				, (select max(tl.updated)
					from total_log tl
					where tl.table_name='auction' and tl.tableid=".$r[$k]->auction_id."
						and tl.field_name='shipping_username') shipping_username_date
				, IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					where auction_calcs.auction_number = ".$r[$k]->auction_number." AND auction_calcs.txnid = ".$r[$k]->txnid."
					),0)
					+ IFNULL((select sum(effective_shipping_cost)
					from auction_calcs 
					join auction a1 on auction_calcs.auction_number = a1.auction_number AND auction_calcs.txnid = a1.txnid
					where a1.main_auction_number = ".$r[$k]->auction_number." AND a1.main_txnid = ".$r[$k]->txnid."
					),0)
					 real_shipping_cost
				from orders o 
				join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
				join article a on a.article_id=o.article_id and a.admin_id=o.manual
				where (o.auction_number=".$r[$k]->auction_number." and o.txnid=".$r[$k]->txnid.")
				OR (au.main_auction_number=".$r[$k]->auction_number." and au.main_txnid=".$r[$k]->txnid.")";
			$au = $dbr->getRow($q);
			$r[$k]->weight = $au->weight;
			$r[$k]->volume = $au->volume;
			$r[$k]->shipping_date1 = $au->shipping_date1;
			$r[$k]->shipping_username_date = $au->shipping_username_date;
			$r[$k]->real_shipping_cost = $au->real_shipping_cost;
			$r[$k]->days_due = $dbr->getOne("select DATEDIFF('".$r[$k]->shipping_username_date."', '".$r[$k]->shipping_date1."') ");
		}
        return $r;
    }

    static function getTrackingNumberListIDs($db, $dbr, $ids, $pars)
    {
/*	  	$q = "SELECT auction.auction_number, tn.number, sm.company_name AS shipping_method
				, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as cust_name
				, CONCAT(au_zip_shipping.value, ',', au_street_shipping.value, ' ', au_house_shipping.value, ',', au_city_shipping.value, ',', au_country_shipping.value) cust_addr
				, au_zip_shipping.value zip_shipping
				, au_street_shipping.value street_shipping
				, au_house_shipping.value house_shipping
				, au_city_shipping.value city_shipping, au_country_shipping.value country_shipping
			from auction 
			left join tracking_numbers tn ON tn.auction_number = auction.auction_number AND tn.txnid = auction.txnid
			left JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number 
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number 
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_zip_shipping on auction.auction_number=au_zip_shipping.auction_number 
				and auction.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
			left join auction_par_varchar au_street_shipping on auction.auction_number=au_street_shipping.auction_number 
				and auction.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
			left join auction_par_varchar au_house_shipping on auction.auction_number=au_house_shipping.auction_number 
				and auction.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
			left join auction_par_varchar au_city_shipping on auction.auction_number=au_city_shipping.auction_number 
				and auction.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
				and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
			WHERE $what
			$where
			and concat(auction.id,'/',ifnull(tn.id,'')) in ('$ids')
			$seller_filter_str
			$method_filter_str
			order by concat(auction.auction_number,'/',auction.txnid)
			";
//		echo $q; die();
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            return;
        }
		foreach($r as $k=>$dummy) {
			$au = $dbr->getRow("select sum(a.weight_per_single_unit*o.quantity) weight
					, sum(a.volume_per_single_unit*o.quantity) volume
				from orders o 
				join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
				join article a on a.article_id=o.article_id and a.admin_id=o.manual
				where (o.auction_number=".$r[$k]->auction_number." and o.txnid=".$r[$k]->txnid.")
				OR (au.main_auction_number=".$r[$k]->auction_number." and au.main_txnid=".$r[$k]->txnid.")");
			$r[$k]->weight = $au->weight;
			$r[$k]->volume = $au->volume;
		}
        return $r;*/
    }

    static function findByRating($db, $dbr, $rating='1, 2, 3', $status, $seller, 
				$supplier, $sa, $article,
				$from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$where = '';
		if (strlen($seller)) $where .= " and auction.username='$seller' ";
		if (strlen($status)) $where .= " and ".($status?'NOT':'')." IFNULL(rc.closed, 0)";
		if (strlen($supplier) && $supplier) $where .= " and exists (select null from auction sau 
			join orders o on o.auction_number=sau.auction_number and o.txnid=sau.txnid
			join article a on a.article_id=o.article_id and a.admin_id=o.manual
			where o.manual=0 and a.company_id=$supplier
			and main_auction_number=auction.auction_number and main_txnid=auction.txnid
			union
			select null from orders o 
			join article a on a.article_id=o.article_id and a.admin_id=o.manual
			where o.manual=0 and a.company_id=$supplier
			and o.auction_number=auction.auction_number and o.txnid=auction.txnid
			) ";
		if (strlen($sa) && $sa) $where .= " and (exists (select null from auction sau where saved_id=$sa
			and main_auction_number=auction.auction_number and main_txnid=auction.txnid ) or auction.saved_id=$sa)
			";
		if (strlen($article)) $where .= " and exists (select null from auction sau 
			join orders o on o.auction_number=sau.auction_number and o.txnid=sau.txnid
			where o.manual=0 and o.article_id='$article'
			and main_auction_number=auction.auction_number and main_txnid=auction.txnid
			union
			select null from orders o 
			where o.manual=0 and o.article_id='$article'
			and o.auction_number=auction.auction_number and o.txnid=auction.txnid) ";
        $r = $dbr->getAll("SELECT SQL_CALC_FOUND_ROWS auction . * , 
			aut.value rating_text_received_new,
		IF(rating_received=1, 'green',
			IF(rating_received=2, 'gray',
			IF(rating_received=3, 'red',''))) rating_received_color,
		IF(rc.resolved=1, 'Yes', 'No') resolved,
		rc.id as rating_id
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IF(rc.closed, rc.close_date, null) rating_close_date
		, fget_Currency(auction.siteid) currency
		, af.`datetime` rate_date
		, IFNULL(users.name, rc.responsible_username) responsible_username
				, IFNULL(
					(select CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number)
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					where co.obj_id=offer.offer_id 
						and co.obj='offer')
					, (select GROUP_CONCAT(CONCAT(c1.number,'.',c2.number,'.',c3.number,'.',c4.number) SEPARATOR '<br><br>')
					from classifier c4
					join classifier c3 on c3.id=c4.parent_id
					join classifier c2 on c2.id=c3.parent_id
					join classifier c1 on c1.id=c2.parent_id
					join classifier_obj co on c4.id=co.classifier_id
					join auction subs on subs.offer_id=co.obj_id and co.obj='offer' and co.level=4
					where subs.main_auction_number=auction.auction_number and subs.main_txnid=auction.txnid
					)) offer_classifier
		FROM auction 
		left join auction_par_text aut on auction.auction_number=aut.auction_number and auction.txnid=aut.txnid
			and aut.`key`='rating_text_received'
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		LEFT JOIN rating rc ON rc.auction_number = auction.auction_number AND rc.txnid = auction.txnid
		LEFT JOIN users ON rc.responsible_username = users.username
		left join auction_feedback af on af.auction_number=auction.auction_number and af.txnid=auction.txnid 
		WHERE rating_received in ($rating) 
			and main_auction_number=0
			$where
		$seller_filter_str
		$sort LIMIT $from, $to
		");
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findByRatingCount($db, $dbr, $rating='1, 2, 3')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getOne("SELECT count(*)
		FROM auction 
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		WHERE rating_received in ($rating)
			and main_auction_number=0
		$seller_filter_str
		");
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findByTempRating($db, $dbr, $rating='1, 3')
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $dbr->getAll("SELECT auction . * , 
		IF(temp_rating_received=1, 'green',
			IF(temp_rating_received=2, 'gray',
			IF(temp_rating_received=3, 'red',''))) temp_rating_received_color
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		FROM auction 
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		WHERE temp_rating_received in ($rating)
			and main_auction_number=0
		$seller_filter_str
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r( $r);
            return;
        }
        return $r;
    }

    function getMyLang() {
		$langfn = $this->data->lang;
//		echo $this->data->auction_number.'&&'.$this->data->txnid;
		if (!strlen($langfn) && strlen($this->data->auction_number)) {
			$type = $this->_dbr->getOne("select fget_AType({$this->data->auction_number}, {$this->data->txnid})");
	        if (PEAR::isError($type)) {
				aprint_r( $type);die();
        	}
			if ((int)$this->data->customer_id) {
				$langfn = $this->_dbr->getOne("SELECT lang FROM customer{$type}
					where id= ".$this->data->customer_id);
		        if (PEAR::isError($langfn)) {
					aprint_r( $langfn);die();
	    	    }
			}
		}
		if (!strlen($langfn)) {
			$langfn = $this->_dbr->getOne("SELECT value
				FROM config_api ca
				JOIN config_api_par cap ON ca.par_id = cap.id
				AND cap.name = 'language'
				AND ca.siteid = '".$this->data->siteid."'");
		}
        if (PEAR::isError($langfn)) {
			aprint_r( $langfn);die();
        }
		return $langfn; 
	} 

    static function getLang($db, $dbr, $siteid) {
	 $langfn = $dbr->getOne("SELECT value
	 FROM config_api ca
	 JOIN config_api_par cap ON ca.par_id = cap.id
	 AND cap.name = 'language'
	 AND ca.siteid = '$siteid'");
        if (PEAR::isError($langfn)) {
			aprint_r( $langfn);
            return;
        }
	 return $langfn; 
	} 

    static function getCurr($db, $dbr, $siteid) {
	 $langfn = $dbr->getOne("SELECT cav.description
	 FROM config_api ca
	 JOIN config_api_par cap ON ca.par_id = cap.id
	 AND cap.name = 'currency'
	 AND ca.siteid = '$siteid'
	join config_api_values cav on cav.par_id=ca.par_id and ca.value=cav.value
	 ");
        if (PEAR::isError($langfn)) {
			aprint_r( $langfn);
            return;
        }
	 return $langfn; 
	} 

    static function getCurrCode($db, $dbr, $siteid) {
	 $langfn = $dbr->getOne("SELECT cav.value
	 FROM config_api ca
	 JOIN config_api_par cap ON ca.par_id = cap.id
	 AND cap.name = 'currency'
	 AND ca.siteid = '$siteid'
	join config_api_values cav on cav.par_id=ca.par_id and ca.value=cav.value
	 ");
        if (PEAR::isError($langfn)) {
			aprint_r( $langfn);
            return;
        }
	 return $langfn; 
	} 

    function getMySeller() 
    {
		return new SellerInfo($this->_db, $this->_dbr,$this->get('username'), $this->getMyLang());
    }

    function getMyInvoice() 
    {
		return 	$invoice = new Invoice($this->_db, $this->_dbr, $this->get('invoice_number'));
    }

    function getMyTranslation() 
    {
		$q = "select id, `value` from translation where language='{$this->data->lang}' 
			and table_name='translate' and field_name='translate'";
		$r = $this->_dbr->getAssoc($q);
		if (PEAR::isError($r)) {aprint_r($r); die();}	
		return $r; 
    }

    static function getTranslation($db, $dbr, $siteid, $langfn='') 
    {
		if (!strlen($langfn)) $langfn = Auction::getLang($db, $dbr, $siteid);
		if (!strlen($langfn)) $langfn='english';
		$q = "select id, `value` from translation where language='$langfn' 
			and table_name='translate' and field_name='translate'";
		$r = $dbr->getAssoc($q);
		if (PEAR::isError($r)) {aprint_r($r); die();}	
		return $r; 
    }

    static function getTranslationShop($db, $dbr, $siteid, $langfn='') 
    {
	 if (!strlen($langfn)) $langfn = Auction::getLang($db, $dbr, $siteid);
	 if (!strlen($langfn)) $langfn='english';
	 $r = $dbr->getAssoc("select id, `value` from translation where language='$langfn' 
		and table_name='translate_shop' and field_name='translate_shop'");
/*	 if (PEAR::isError($r)) {aprint_r($r); die();}	
	 $german = unserialize($r);
		if ($german===false){
			$german = mb_unserialize($r);
		}
	var_dump($german);	*/
//	 $german = unserialize(file_get_contents($langfn.'_shop'));
     return $r; 
    }

//    static function getTranslation($db, $dbr, $siteid) 
/*	{
	 $langfn = Auction::getLang($db, $dbr, $siteid);
	 if (!strlen($langfn)) $langfn='english';
	 $german = unserialize(file_get_contents($langfn));
//	 if (count($german)) foreach ($german as $i => $s) 
//	            if (strlen($s)) $english[$i] = $s;
     return $german; 
    };*/

    function getComments($prefix = '') 
    {
        $prefix = strtolower($prefix);

        $q = "select t.*, IFNULL(u.name, t.username) username 
        , IFNULL(u.name, t.username) full_username
        from (";
        $unions = Array();
        if (!$prefix || $prefix == 'warehouse') {
            $unions[] = "select IF(ac.src = 'warehouse', 'Warehouse:', IF(ac.src = 'sms', 'Sms:', '')) as prefix
                , ac.id
                , ac.create_date
                , ac.username cusername
                , ac.username username
                , ac.`comment`
                , ac.src
            from auction_comment ac 
            where ac.auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid.($prefix == 'warehouse' ? "
            having prefix = 'Warehouse:'" : "");
        }
        if (!$prefix || $prefix == 'shipping order') {
            $unions[] = "select 'Shipping Order:' as prefix
                , NULL id
                , ac.create_date
                , ac.username cusername
                , ac.username username
                , ac.`comment`
                , ac.src
            from auction_sh_comment ac 
            where ac.auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid;
        }
        if (!$prefix || $prefix == 'delivery') {
            $unions[] = "(select 'Delivery:' as prefix
                , NULL id
                , total_log.updated create_date
                , IFNULL(u.username,'Customer') cusername
                , IFNULL(u.username,'Customer') username
                , au.delivery_comment
                , ''
            from auction au
            join total_log on table_name='auction' and field_name='delivery_comment'
                and TableId=au.id
            left join users u on u.system_username=total_log.username
            where au.auction_number=".$this->data->auction_number
             ." and au.txnid=".$this->data->txnid." 
             and IFNULL(au.delivery_comment,'')<>''
             order by updated desc limit 1)";
        }
        if (!$prefix || $prefix == 'alarm') {
            $unions[] = "select CONCAT('Alarm (',alarms.status,'):') as prefix
                , NULL as id
                , (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
                , alarms.username cusername
                , alarms.username
                , alarms.comment 
                , ''
            from auction
                join alarms on alarms.type='auction' and alarms.type_id=auction.id
            where auction.id=".$this->data->id;
        }
        if (!$prefix || $prefix == 'priority') {
            $unions[] = "select CONCAT('Priority:') as prefix
                , NULL as id
                , (select updated from total_log where table_name='auction' and field_name='priority_comment'
                    and tableid=auction.id order by updated desc limit 1) as create_date
                , (select u.username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='auction' and field_name='priority_comment' and tableid=auction.id
                    order by updated desc limit 1) cusername
                , (select IFNULL(u.name, tl.username) username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='auction' and field_name='priority_comment' and tableid=auction.id
                    order by updated desc limit 1) username
                , auction.priority_comment 
                , ''
                from auction
                where priority and auction.id=".$this->data->id;
        }
        if (!$prefix || $prefix == 'mass email') {
            $unions[] = "select 'Mass email: ' as prefix
                , NULL as id
                , email_log.date as create_date
                , IFNULL(u.username, total_log.username) cusername
                , IFNULL(u.name, total_log.username) username
                , email_log_content.subject
                , ''
                from email_log
                join total_log on email_log.id=total_log.tableid and table_name='email_log'
                left join users u on u.system_username=total_log.username
                join prologis_log.email_log_content on email_log.id=email_log_content.id
                where email_log.auction_number=".$this->data->auction_number." and email_log.txnid=".$this->data->txnid."
                    and email_log.template='mass_email'
                    and total_log.Old_value is null";
        }
        if (!$prefix || $prefix == 'customer') {
            $unions[] = "select CONCAT('Customer:') as prefix
                , NULL as id
                , el.date as create_date
                , 'Customer'
                , 'Customer'
                , SUBSTRING_INDEX(elc.content, 'Seller:',1)
                , ''
                from auction
                join email_log el on el.auction_number=auction.auction_number and el.txnid=auction.txnid
                join prologis_log.email_log_content elc on el.id=elc.id
                where 1 and auction.id=".$this->data->id."
                and el.template='improvent_email'";
        }
        if (!$prefix || $prefix == 'payment') {
            $unions[] = "select CONCAT('Payment:') as prefix
                , NULL as id
                , IFNULL(tl.updated, p.payment_date) as create_date
                , p.username
                , IFNULL(u.name, p.username)
                , p.comment
                , ''
                from auction
                join payment p on p.auction_number=auction.auction_number and p.txnid=auction.txnid
                left join total_log tl on tl.table_name='payment' and tl.field_name='payment_id' and tl.tableid=p.payment_id
                left join users u on u.username=p.username
                where 1 and auction.id=".$this->data->id;
        }
        if (!$prefix || $prefix == 'pp payment') {
            $unions[] = "select CONCAT('PP Payment:') as prefix
                , NULL as id
                , ppp.payment_date as create_date
                , ''
                , ''
                , CONCAT('ID: ', ppp.txn_id)
                , ''
                from auction
                join payment_paypal ppp on ppp.item_number=auction.auction_number 
                    and ppp.auction_buyer_id=auction.username_buyer 
                where 1 and auction.id=".$this->data->id;
        }
        if (!$prefix || $prefix == 'ticket') {
            $unions[] = "select CONCAT('Ticket#',rma.rma_id) as prefix
                , NULL as id
                , rma.create_date
                , (select u.username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='rma' and tableid=rma.rma_id
                    order by updated limit 1) cusername
                , (select IFNULL(u.name, tl.username) username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='rma' and tableid=rma.rma_id
                    order by updated limit 1) username
                , '' as `comment`
                , ''
                from rma
                where rma.auction_number=".$this->data->auction_number." and rma.txnid=".$this->data->txnid;
        }
        if (!$prefix || $prefix == 'ins') {
            $unions[] = "select CONCAT('INS#',insurance.id) as prefix
                , NULL as id
                , insurance.date
                , (select u.username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='insurance' and tableid=insurance.id
                    order by updated limit 1) cusername
                , (select IFNULL(u.name, tl.username) username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='insurance' and tableid=insurance.id
                    order by updated limit 1) username
                , '' as `comment`
                , ''
                from insurance
                where insurance.auction_number=".$this->data->auction_number." and insurance.txnid=".$this->data->txnid;
        }
        $q .= implode("
            UNION ALL
            ", $unions);
        $q .= ") t LEFT JOIN users u ON t.username=u.username  
         order by t.create_date";

        $r = $this->_dbr->getAll($q);
        if (PEAR::isError($r)) {aprint_r($r); die();}   
        return $r;
    }

    function getShippingComments() 
    {
		$q = "select t.*, IFNULL(u.name, t.username) username 
		, IFNULL(u.name, t.username) full_username
		from (
	 	select '' as prefix
			, ac.id
			, ac.create_date
     	    , ac.username cusername
     	    , ac.username username
			, ac.`comment`
			, ac.hidden
			, (select new_value from total_log tl1 where 
				tl1.tableid=auction.id and tl1.table_name='auction'
				and tl1.field_name='shipping_username' and tl1.updated<ac.create_date
				order by updated desc limit 1
			) last_recipient
			, ac.src
		from auction_sh_comment ac 
		join auction on auction.auction_number=ac.auction_number and auction.txnid=ac.txnid
		where ac.auction_number=".$this->data->auction_number
		 ." and ac.txnid=".$this->data->txnid." 
		UNION ALL
	 	(select 'Delivery:' as prefix
			, NULL id
			, total_log.updated create_date
     	    , IFNULL(u.username,'Customer') cusername
     	    , IFNULL(u.username,'Customer') username
			, au.delivery_comment
			, 0 hidden
			, NULL last_recipient
			, ''
		from auction au
		join total_log on table_name='auction' and field_name='delivery_comment'
			and TableId=au.id
		left join users u on u.system_username=total_log.username
		where au.auction_number=".$this->data->auction_number
		 ." and au.txnid=".$this->data->txnid." 
		 order by updated desc limit 1)
		UNION ALL
	 	select 'Warehouse:' as prefix
			, ac.id
			, ac.create_date
     	    , ac.username cusername
     	    , ac.username username
			, ac.`comment`
			, 0 hidden
			, NULL last_recipient
			, ''
		from auction_comment ac
		where ac.auction_number=".$this->data->auction_number
		 ." and ac.txnid=".$this->data->txnid." 
		 AND ac.src = 'warehouse'
		UNION ALL
		select CONCAT('Alarm (',alarms.status,'):') as prefix
			, NULL as id
			, (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
			, alarms.username cusername
			, alarms.username
			, alarms.comment 
			, NULL 
			, NULL last_recipient
			, ''
			from auction
			join alarms on alarms.type='sh_auction' and alarms.type_id=auction.id
			where auction.id=".$this->data->id."
		UNION ALL
		select CONCAT('Priority:') as prefix
			, NULL as id
			, (select updated from total_log where table_name='auction' and field_name='priority_comment'
				and tableid=auction.id order by updated desc limit 1) as create_date
			, (select u.username
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='auction' and field_name='priority_comment' and tableid=auction.id
				order by updated desc limit 1) cusername
			, (select IFNULL(u.name, tl.username) username
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='auction' and field_name='priority_comment' and tableid=auction.id
				order by updated desc limit 1) username
			, auction.priority_comment 
			, NULL 
			, null last_recipient
			, ''
			from auction
			where priority and auction.id=".$this->data->id."
		) t LEFT JOIN users u ON t.username=u.username  
		 order by t.create_date";
	 $r = $this->_dbr->getAll($q);
//	 echo $q;
	 if (PEAR::isError($r)) {aprint_r($r); die();}	
		global $loggedUser;
		global $warehouses;
		foreach($r as $k=>$dummy) $r[$k]->comment = nl2br(str_replace('\n','<br>',$r[$k]->comment));
		foreach($r as $id=>$comment) {
			$warehouses_comment = $this->_dbr->getAssoc("
				SELECT distinct sm.warehouse_id f1, sm.warehouse_id f2 FROM 
						warehouse sm
						left join `acl_warehouse` on sm.warehouse_id=`acl_warehouse`.warehouse_id
						left join users u on acl_warehouse.username=u.role_id 
				WHERE (u.username = '".$r[$id]->cusername."'
					or exists (SELECT 1 FROM `acl_warehouse` 
						left join users u1 on acl_warehouse.username=u1.role_id 
					WHERE u1.username = '".$r[$id]->cusername."' and acl_warehouse.warehouse_id = 0)
				)
				");
			$warehouses_comment_recipient = $this->_dbr->getAssoc("
				SELECT distinct sm.warehouse_id f1, sm.warehouse_id f2 FROM 
						warehouse sm
						left join `acl_warehouse` on sm.warehouse_id=`acl_warehouse`.warehouse_id
						left join users u on acl_warehouse.username=u.role_id 
				WHERE (u.username = '".$r[$id]->last_recipient."'
					or exists (SELECT 1 FROM `acl_warehouse` 
						left join users u1 on acl_warehouse.username=u1.role_id 
					WHERE u1.username = '".$r[$id]->last_recipient."' and acl_warehouse.warehouse_id = 0)
				)
				");
//			echo '$r[$id]->last_recipient='; var_dump($r[$id]->last_recipient); echo '<br>';
			if (!count(array_intersect($warehouses, $warehouses_comment))
				|| (!count(array_intersect($warehouses, $warehouses_comment_recipient)) && $r[$id]->last_recipient!=NULL)) {
				if ($r[$id]->cusername!=$loggedUser->get('username') && $r[$id]->last_recipient!=$loggedUser->get('username')) {
					$r[$id]->cusername = $r[$id]->username = $r[$id]->comment = 'hidden';
					$r[$id]->prefix = '';
				}
			}
		}
     return $r;
    }

    function addComment($comment, $username, $date, $src='') 
    {
//	$comment = mysql_real_escape_string($comment); // we escape it in js_backend
	$src = mysql_escape_string($src);
     return $this->_db->query("insert into auction_comment SET
     	    auction_number=".$this->data->auction_number.",
     	    txnid=".$this->data->txnid.",
     	    `comment`='$comment',
	    create_date='$date', 
	    username='$username'
		, src='$src'
		");
    }

    /**
     * delete comment
    */
    function deleteComment($comment)
    {
        $this->_db->query("
          DELETE FROM auction_comment
          WHERE 
            auction_number=".$this->data->auction_number."
     	    AND txnid=".$this->data->txnid."
     	    AND `comment`='$comment' ");
    }

    function addShippingComment($comment, $username, $date, $src='')
    {
	$comment = mysql_escape_string($comment);
	$src = mysql_escape_string($src);
     return $this->_db->query("insert into auction_sh_comment SET
     	    auction_number=".$this->data->auction_number.",
     	    txnid=".$this->data->txnid.",
     	    `comment`='$comment',
	    create_date='$date', 
	    username='$username'
		, src='$src'
		");
    }

    function delComment($id) 
    {
     return $this->_db->query("delete from auction_comment where 
     	    id=$id");
    }

    function delShippingComment($id) 
    {
     $this->_db->query("delete from auction_sh_comment where 
     	    id=$id");
		return "delete from auction_sh_comment where 
     	    id=$id";
    }

    static function getDocs($db, $dbr, $auction_number, $txnid) 
    {
     return $dbr->getAll("select ad.*,IFNULL( u.name, ad.username ) as fullusername 
		from auction_doc ad LEFT JOIN users u ON ad.username=u.username  
		 where ad.auction_number=$auction_number and ad.txnid=$txnid order by `date`");
    }

    static function addDoc($db, $dbr, $auction_number, $txnid,
								$name,
								$description,
								$pic, $username) 
    {
        $md5 = md5($pic);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $pic);
        }
        
		$r = $db->query("insert into auction_doc (auction_number, txnid, name, data, date, username)
				values ($auction_number, $txnid, '$name', '$md5', NOW(), '$username')");
//		 $r = $db->query("insert into auction_doc (auction_number, txnid, name, data, date, username)
//				values ($auction_number, $txnid, '$name', '$pic', NOW(), '$username')");
         if (PEAR::isError($r)) aprint_r($r->message);
	     return $r;
    }

    function delDoc($id) 
    {
     return $this->_db->query("delete from auction_doc where 
     	    doc_id=$id");
    }

	function getSERVICEQUITTUNG_PDF($tnid=0) {
//		echo 'here<br>';print_r($this->tracking_numbers);
		global $smarty;
		$tnid = (int)$tnid;
		$smarty->assign('tnid', $tnid);
		$db = $this->_db;
		$dbr = $this->_dbr;
    	$sellerInfo = new SellerInfo($db, $dbr,$this->get('username'), $this->getMyLang());
		$english = Auction::getTranslation($db, $dbr, $this->get('siteid'), $this->getMyLang());
		$smarty->assign('english', $english);
		$english_shop = Auction::getTranslationShop($db, $dbr, $this->get('siteid'), $this->getMyLang());
		$smarty->assign('english_shop', $english_shop);
		$this->data->translated_country_shipping
				= translate($db, $dbr, ($this->get('country_shipping')), $this->getMyLang(), 'country', 'name');
		$smarty->assign('auction', $this);
		$sellerInfo->data->translated_country 
			= translate($db, $dbr, countryCodeToCountry($sellerInfo->get('country')), $this->getMyLang(), 'country', 'name');
		$smarty->assign('sellerInfo', $sellerInfo);
		$next_steps = trim(substitute($sellerInfo->data->servicequitting_next_steps, $this->data));
		$next_steps = substitute($next_steps, $sellerInfo->data);
		$smarty->assign('next_steps', $next_steps);
		$method = $dbr->getRow("
			select t.value customSERVICEQUITTING, sm.sendcustomSERVICEQUITTING
			from tracking_numbers tn 
			join shipping_method sm on sm.shipping_method_id=tn.shipping_method
			join translation t on t.table_name='method' and t.field_name='customSERVICEQUITTING'
			and t.language='".$this->getMyLang()."' and t.id=sm.shipping_method_id
			where tn.id=$tnid");
		if ($tnid && $method->sendcustomSERVICEQUITTING) {
			$html = $method->customSERVICEQUITTING;
			$html = substitute($html, $this->data);
			$html = substitute($html, $sellerInfo->data);
		} else {
			$html = $smarty->fetch("servicequitting.tpl");
		}
		if (strlen($html)) {
			require_once("dompdf/dompdf_config.inc.php");
			$dompdf = new DOMPDF();
			$html_with_encoding = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>'.$html.'</body></html>';
			$dompdf->load_html($html_with_encoding);
			$dompdf->render();
			$content = $dompdf->output();
		}
		return $content;
	}

function calcAuction()
{
//		echo 'start func: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
	require_once 'lib/ShippingPlan.php';
	$db = $this->_db;
	$lang = $this->getMyLang();
	$siteid = $this->data->siteid;
	$curr_code = $dbr->getOne("SELECT ca.value FROM config_api ca
		LEFT join config_api_values cav on ca.par_id=cav.par_id and ca.value=cav.value
		where ca.par_id =7 and siteid='$siteid'");
	$fxrates = getRates();
//	echo 'getrates: '.round(getmicrotime()-$time,2).'<br>';$time = getmicrotime();
	$curr_rate = $fxrates[$curr_code.'US']/$fxrates['EURUS'];
    $auction_number = $this->data->auction_number;
    $txnid = $this->data->txnid;
	$seller_channel_id = $this->_dbr->getOne("select seller_channel_id from seller_information
			where username='".$this->data->username."'");
	if ($seller_channel_id==3) {
			$type = 'a';
	} else {
		switch($txnid) {
			case 1: $type = '';
			break;
			case 3: $type = 's';
			break;
			case 0: $type = '';
			break;
			default: $type = 'f';
			break;
		}
	}

	$where = " AND au.auction_number = $auction_number AND au.txnid = $txnid";
	$where_d = " AND auction_number = $auction_number AND txnid = $txnid";

	$invoice = new Invoice($db, $dbr, $this->data->invoice_number);
    $total_sum = $invoice->get('total_price');
	if (!$total_sum) $total_sum=0;
	$total_shipping = $invoice->get('total_shipping'); if (!strlen($total_shipping)) $total_shipping=0;
	$total_cod = $invoice->get('total_cod'); if (!strlen($total_cod)) $total_cod=0;

// calc ebay listing fee for the auction
        $listing_fee = $dbr->getOne("SELECT IFNULL(IFNULL(l.listing_fee, au.listing_fee)*au.quantity/
				(select sum( au1.quantity) from auction au1 where au1.auction_number = au.auction_number), 0) listing_fee
			FROM auction au
				LEFT JOIN listings l ON au.auction_number = l.auction_number
			WHERE 1=1 $where");
	    if (PEAR::isError($listing_fee)) {
			aprint_r($listing_fee);
        }
		if (!$listing_fee) $listing_fee = 0;

// calc total quantity for the main group of the auction
        $r = $dbr->getRow("SELECT IFNULL(sum(o.quantity), 0) main_quantity, IFNULL(sum(o.quantity*o.price), 0) main_sum
			FROM orders o 
				join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
				join offer_group og on au.offer_id=og.offer_id
				left JOIN article_list al ON o.article_list_id = al.article_list_id
					 and al.group_id=og.offer_group_id
			WHERE 1=1 and og.main=1 $where");
	    if (PEAR::isError($r)) aprint_r($r);
		$main_quantity = $r->main_quantity; $main_sum = $r->main_sum;
		if (!$main_quantity) $main_quantity = 'o.quantity';
		if (!$main_sum) $main_sum = '(o.quantity*o.price)';
	
// calc total sum for the auction
        $delres = $db->query("delete from auction_calcs WHERE 1=1 $where_d");
        if (PEAR::isError($delres)) aprint_r($delres);
		$subinvoices = (int)$dbr->getOne("select count(*) from auction where main_auction_number=$auction_number and txnid=$txnid");
		$shipping_cost_formula = "
			IF(si.free_shipping_total or (si.free_shipping and si.defshcountry=c1.code and $total_sum>si.free_shipping_above), 0, 
				IF(o.manual in (3,2), 0, IF(au.txnid=3 and o.manual=4, i.total_shipping, IF($subinvoices and au.txnid=3 and o.manual<>4, 0
					, IF (i.is_custom_shipping, 
					IF($total_sum = 0, 
						0, 
						o.price*o.quantity*(i.total_shipping)
						/$total_sum
					),
				IF( (SELECT sum( o1.quantity ) 
					FROM orders o1
					JOIN auction au1 ON o1.auction_number = au1.auction_number
						AND o1.txnid = au1.txnid
					JOIN offer_group og1 ON au1.offer_id = og1.offer_id
					left JOIN article_list al1 ON al1.group_id = og1.offer_group_id
						AND al1.article_list_id = o1.article_list_id
					WHERE og1.offer_group_id = og.offer_group_id
					AND au.auction_number = au1.auction_number
					AND au.txnid = au1.txnid
					) >= og.noshipping and og.noshipping>0, 
					0,
					IF (og.additional = 1, 
						IF(al.noship, 0, IFNULL(spca.shipping_cost, 0)), 
						IF (og.main = 1, 
							IFNULL(spco.shipping_cost, 0)"
				.(isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping) ? " + IFNULL(spco.island_cost, 0)" : "")." + al.additional_shipping_cost, 
							IFNULL(al.additional_shipping_cost, 0)
						)
					) *o.quantity
				)
				)))))";
        $qry = "insert into auction_calcs 
			(
				auction_number, 
				txnid,
				article_list_id,
				price_sold, 
				ebay_listing_fee, 
				ebay_commission,
				vat,
				purchase_price, 
				shipping_cost,
				effective_shipping_cost,
				COD_cost,
				effective_COD_cost,
				curr_code,
				curr_rate,
				packing_cost,
				vat_shipping,
				vat_COD,
				order_id,
				vat_percent
			)
			(SELECT distinct
			ttt.auction_number, 
			ttt.txnid,
			ttt.article_list_id,
			ttt.price_sold, 
			ttt.ebay_listing_fee, 
			ttt.ebay_commission,
			ttt.vat,
			ttt.purchase_price, 
			ttt.shipping_cost,
			ttt.effective_shipping_cost,
			ttt.COD_cost,
			ttt.effective_COD_cost,
			ttt.curr_code,
			ttt.curr_rate,
			ttt.packing_cost,
			(IFNULL(ttt.vat_percent, 0))*ttt.shipping_cost/(100+IFNULL(ttt.vat_percent, 0)) as vat_shipping,
			(IFNULL(ttt.vat_percent, 0))*ttt.COD_cost/(100+IFNULL(ttt.vat_percent, 0)) as vat_COD,
			ttt.order_id,
				vat_percent
			FROM (SELECT
                IF(IFNULL(mau.customer_vat,au.customer_vat)<>'', 0, v.vat_percent) vat_percent,
				o.quantity,
				o.auction_number, 
				o.txnid,
				o.article_list_id,
				IF (
					og.additional =1, o.price*o.quantity, o.price*o.quantity
					) AS price_sold, 
				IF (og.main = 1, $listing_fee*o.quantity/$main_quantity, 0) AS ebay_listing_fee, 
				IF(ss.provision, o.price*o.quantity*ss.provision/100,
					IF($main_sum=0 or o.manual, 0, IF (o.hidden, 0, 
						IF(au.txnid in (0,2,3)
						, o.price*o.quantity*`fget_Fee_Percent_channel`(au.winning_bid*au.quantity
							+ IF(exists (select null from saved_fee_algo sfa
							join seller_information si on si.seller_channel_id=sfa.seller_channel_id
							where sfa.fieldname='winning_bid'
							and si.username='Amazon'
							and `type`='withship'
							limit 1), ($shipping_cost_formula), 0)
						, IF(si.seller_channel_id=4 and o.txnid=2, 3, si.seller_channel_id))/$total_sum			
					, IFNULL(IF (og.main = 1, 
						(au.winning_bid*au.quantity * 0.11 / 115) * 100
						/*IF (au.winning_bid <=25, 
							au.winning_bid * 0.0525, 
							IF (au.winning_bid <=1000, 
								1.31 + ( au.winning_bid -25 ) * 0.0275, 
								1.31 + 26.81 + ( au.winning_bid -1000 ) * 0.015)
						)*/, 0)*o.quantity*o.price/$main_sum, 0)))))
				AS ebay_commission,
                IF(IFNULL(mau.customer_vat,au.customer_vat)<>'', 0, (IFNULL(v.vat_percent, 0))*o.price*o.quantity/(100+IFNULL(v.vat_percent, 0))) as vat,
				IF(o.manual=3
					, -(select min(sold_for_amount) from shop_promo_codes where article_id=o.article_id)/(1+v.vat_percent/100)
					, IFNULL(
						(select article_import.total_item_cost from article_import
							join country on country.code=article_import.country_code
							where article_import.country_code=si.defcalcpricecountry
							and article_import.article_id=a.article_id and o.manual=0
							order by import_date desc limit 1)
						,(select article_import.total_item_cost from article_import
								where article_import.article_id=a.article_id and o.manual=0
								order by import_date desc limit 1)
/*						, IFNULL(
							(select article_import.total_item_cost from article_import
								where article_import.country_code=w.country_code
								and article_import.article_id=a.article_id and o.manual=0
								order by import_date desc limit 1)
							,(select article_import.total_item_cost from article_import
								where article_import.article_id=a.article_id and o.manual=0
								order by import_date desc limit 1)
						)*/
					)*o.quantity/$curr_rate) as purchase_price, 

				$shipping_cost_formula as shipping_cost,

				IF(IFNULL(ss.free_shipping,0) OR o.hidden, 0, IFNULL(IF (og.additional = 1, 
					IF(spca.estimate, IFNULL(spca.real_additional_cost,0), IFNULL(scc_art.real_additional_cost, 0)), 
					IF (og.main = 1, 
						IF(spco.estimate, IFNULL(spco.real_shipping_cost,0), IFNULL(scc_off.real_shipping_cost, 0))"
				.(isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping) 
					? " + IF(spco.estimate, IFNULL(spco.real_island_cost,0), IFNULL(scc_off.real_island_cost, 0))" : "").", 
						0
					)
				)*o.quantity, 0))	AS effective_shipping_cost,

				IF(o.manual=4, i.total_cod+IFNULL(i.total_cc_fee,0), IF(o.manual in (1,2,3), 0, IFNULL(IF(au.payment_method='2', 
					IF (i.is_custom_cod, 
						IF($total_sum = 0, 
							0, 
							o.price*o.quantity*(i.total_cod)/$total_sum
						),
						IF (og.main = 1, 
							IF($total_cod=0, spco.COD_cost, o.price*o.quantity*spco.COD_cost/$total_cod), 
						0)), 
					0
				), 0))) as COD_cost,
				
				IF(o.hidden, 0, IFNULL(IF((au.payment_method='2' AND og.main = 1), 
					IF(spco.estimate, spco.real_COD_cost, scc_off.real_COD_cost), 0), 0)) 
				as effective_COD_cost,

				'$curr_code' as curr_code,
				$curr_rate as curr_rate,
				(IFNULL((SELECT SUM(asm.total_item_cost) 
											FROM article asm 
											JOIN article_sh_material asmsm
												ON asmsm.sh_material_id = asm.article_id
											WHERE asmsm.article_id = a.article_id), 0)
					*o.quantity
				) as packing_cost
				, o.id order_id
			FROM orders o
				left join warehouse w on o.send_warehouse_id=w.warehouse_id
				JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
				left JOIN auction mau ON mau.auction_number = au.main_auction_number
					AND mau.txnid = au.main_txnid
				left join source_seller ss on ss.id=IFNULL(mau.source_seller_id,au.source_seller_id)
				left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number 
					and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
				JOIN invoice i ON au.invoice_number = i.invoice_number	
				JOIN seller_information si ON au.username=si.username
				LEFT JOIN country c1 ON IF(au_country_shipping.value='United Kingdom','UK',au_country_shipping.value) = c1.name AND au.payment_method not in (3,4)
				JOIN country c2 ON si.defshcountry=c2.code
				LEFT JOIN vat v on v.country_code=IFNULL(c1.code, c2.code) and DATE(i.invoice_date) between v.date_from and v.date_to
					and v.country_code_from=si.defshcountry
				LEFT JOIN offer of ON au.offer_id = of.offer_id
				left JOIN article_list al ON o.article_list_id = al.article_list_id
				LEFT JOIN offer_group og ON au.offer_id = og.offer_id
					AND al.group_id = og.offer_group_id
				LEFT JOIN article a ON a.article_id = al.article_id AND a.admin_id=o.manual
				LEFT JOIN shipping_plan sp_off ON sp_off.shipping_plan_id =  ( IFNULL( (
				     SELECT value
				     FROM translation
				     WHERE table_name = 'offer'
				     AND field_name = '".$type."shipping_plan_id'
				     AND language = '$siteid'
				     AND id = of.offer_id
				     ), of.shipping_plan_id ) )
				LEFT JOIN shipping_plan sp_art ON sp_art.shipping_plan_id =  ( IFNULL( (
				     SELECT value
				     FROM translation
				     WHERE table_name = 'article'
				     AND field_name = 'shipping_plan_id'
				     AND language = '$siteid'
				     AND id = a.article_id
				     ), a.shipping_plan_id ) )
				LEFT JOIN shipping_cost sc_off ON sp_off.shipping_cost_id = sc_off.id
				LEFT JOIN shipping_cost sc_art ON sp_art.shipping_cost_id = sc_art.id
				LEFT JOIN shipping_cost_country scc_off ON scc_off.shipping_cost_id = sc_off.id AND scc_off.country_code = IFNULL(c1.code, c2.code)
				LEFT JOIN shipping_cost_country scc_art ON scc_art.shipping_cost_id = sc_art.id AND scc_art.country_code = IFNULL(c1.code, c2.code)
				LEFT JOIN shipping_plan_country spco ON spco.shipping_plan_id = sp_off.shipping_plan_id AND spco.country_code = IFNULL(c1.code, c2.code)
				LEFT JOIN shipping_plan_country spca ON spca.shipping_plan_id = sp_art.shipping_plan_id AND spca.country_code = IFNULL(c1.code, c2.code)
			WHERE 1=1 $where $where_al AND NOT au.deleted) ttt
			) ";
//			echo $qry; die();
        $r = $db->query($qry);
	    if (PEAR::isError($r)) aprint_r($r);
		$q = "select o.id order_id, o.article_list_id, au.payment_method, og.main, og.additional, 
			spco.estimate, sp_off.shipping_plan_id, sp_off.curr_code, 
			spco.real_shipping_cost, spco.real_COD_cost, spco.real_island_cost, spco.real_additional_cost, 
			o.quantity,
			spca.estimate a_estimate, sp_art.shipping_plan_id a_shipping_plan_id, sp_art.curr_code a_curr_code,
			spca.real_shipping_cost a_real_shipping_cost, spca.real_COD_cost a_real_COD_cost, 
			spca.real_island_cost a_real_island_cost, spca.real_additional_cost a_real_additional_cost, 
			IFNULL(c1.code, c2.code) country_code 
			, ss.free_shipping
			FROM orders o
				JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
				left JOIN auction mau ON mau.auction_number = au.main_auction_number
					AND mau.txnid = au.main_txnid
				left join source_seller ss on ss.id=IFNULL(mau.source_seller_id,au.source_seller_id)
				left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number 
					and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
				JOIN invoice i ON au.invoice_number = i.invoice_number	
				JOIN seller_information si ON au.username=si.username
				LEFT JOIN country c1 ON IF(au_country_shipping.value='United Kingdom','UK',au_country_shipping.value) = c1.name AND au.payment_method not in (3,4)
				JOIN country c2 ON si.defshcountry=c2.code
				LEFT JOIN vat v on v.country_code=IFNULL(c1.code, c2.code) and DATE(i.invoice_date) between v.date_from and v.date_to
					and v.country_code_from=si.defshcountry
				LEFT JOIN offer of ON au.offer_id = of.offer_id
				left JOIN article_list al ON o.article_list_id = al.article_list_id
				LEFT JOIN offer_group og ON au.offer_id = og.offer_id
					AND al.group_id = og.offer_group_id
				JOIN article a ON a.article_id = al.article_id AND a.admin_id=o.manual
				LEFT JOIN shipping_plan sp_off ON sp_off.shipping_plan_id = ( IFNULL( (
				     SELECT value
				     FROM translation
				     WHERE table_name = 'offer'
				     AND field_name = '".$type."shipping_plan_id'
				     AND language = '$siteid'
				     AND id = of.offer_id
				     ), of.shipping_plan_id ) )
				LEFT JOIN shipping_plan sp_art ON sp_art.shipping_plan_id = ( IFNULL( (
				     SELECT value
				     FROM translation
				     WHERE table_name = 'article'
				     AND field_name = 'shipping_plan_id'
				     AND language = '$siteid'
				     AND id = a.article_id
				     ), a.shipping_plan_id ) )
				LEFT JOIN shipping_cost sc_off ON sp_off.shipping_cost_id = sc_off.id
				LEFT JOIN shipping_cost sc_art ON sp_art.shipping_cost_id = sc_art.id
				LEFT JOIN shipping_cost_country scc_off ON scc_off.shipping_cost_id = sc_off.id AND scc_off.country_code = IFNULL(c1.code, c2.code)
				LEFT JOIN shipping_cost_country scc_art ON scc_art.shipping_cost_id = sc_art.id AND scc_art.country_code = IFNULL(c1.code, c2.code)
				LEFT JOIN shipping_plan_country spco ON spco.shipping_plan_id = sp_off.shipping_plan_id AND spco.country_code = IFNULL(c1.code, c2.code)
				LEFT JOIN shipping_plan_country spca ON spca.shipping_plan_id = sp_art.shipping_plan_id AND spca.country_code = IFNULL(c1.code, c2.code)
			WHERE 1=1 $where $where_al AND NOT au.deleted";
//		echo $q;	
		$inserted = $dbr->getAll($q);
	    if (PEAR::isError($inserted)) aprint_r($inserted);
		foreach ($inserted as $row) {
			if ($row->free_shipping) continue;
			if ($row->estimate) {
//				die('here');
				if (($row->payment_method==2 && $row->main)) { 
				$effective_COD_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
							    * $row->real_COD_cost;
				} else $effective_COD_cost = 0;
				if ($row->additional) {
					$effective_shipping_cost = $fxrates[$row->a_curr_code.'US']/$fxrates[$curr_code.'US']
								 * $row->a_real_additional_cost;
				}
				elseif ($row->main) {
					$real_shipping_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
								 * $row->real_shipping_cost;
					if (isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping)) {
						$real_island_cost = $fxrates[$owr->curr_code.'US']/$fxrates[$curr_code.'US']
								 * $row->real_island_cost;
					} else $real_island_cost = 0;	 
					$effective_shipping_cost = $real_shipping_cost + $real_island_cost;
				} else 
					$effective_shipping_cost = 0;
			} else { // not estimate
				$cod_shipping_plan_id = ShippingPlan::findLeaf($db, $dbr, $row->shipping_plan_id, $row->country_code, 'cod_diff_shipping_plan_id');
				$shipping_plan_id = ShippingPlan::findLeaf($db, $dbr, $row->shipping_plan_id, $row->country_code, 'diff_shipping_plan_id');
				if (!$shipping_plan_id) $shipping_plan_id = $row->shipping_plan_id;
				if (($row->payment_method==2 && $row->main)) { 
					$r = $dbr->getRow("select real_cod_cost, sc.curr_code 
						from shipping_cost_country scc_off
						JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id   
						WHERE scc_off.shipping_cost_id=$cod_shipping_plan_id and country_code='$row->country_code'
						");
	  			        if (PEAR::isError($r)) aprint_r($r);
					$effective_COD_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS'] 
							    * $r->real_cod_cost;
				} else $effective_COD_cost = 0;
				if ($row->additional) {
					$r = $dbr->getRow("select real_additional_cost, sc.curr_code
						from shipping_cost_country scc_off 
						JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id   
						JOIN shipping_plan sp_off ON sp_off.shipping_cost_id = scc_off.shipping_cost_id
						WHERE sp_off.shipping_plan_id=$row->a_shipping_plan_id and country_code='$row->country_code'
					 ");
	  			        if (PEAR::isError($r)) aprint_r($r);
					$effective_shipping_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS'] 
								 * $r->real_additional_cost;
				}
				elseif ($row->main) {
					$r = $dbr->getRow("select real_shipping_cost, sc.curr_code
						from shipping_cost_country scc_off 
						JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id   
						WHERE scc_off.shipping_cost_id=$shipping_plan_id and country_code='$row->country_code'
					 ");
	  			        if (PEAR::isError($r)) aprint_r($r);
					$real_shipping_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS'] 
								 * $r->real_shipping_cost;
					if (isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping)) {
					        $r = $dbr->getRow("select real_island_cost, sc.curr_code
						   from shipping_cost_country scc_off 
						   JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id   
						   JOIN shipping_plan sp_off ON sp_off.shipping_cost_id = scc_off.shipping_cost_id
						   WHERE sp_off.shipping_plan_id=$row->shipping_plan_id and country_code='$row->country_code'
					 	   ");
	  			        	if (PEAR::isError($r)) aprint_r($r);
						$real_island_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS'] 
								 * $r->real_island_cost;
					} else $real_island_cost = 0;	 
					$effective_shipping_cost = $real_shipping_cost + $real_island_cost;
				} else 
					$effective_shipping_cost = 0;
			}		
			$effective_shipping_cost *= $row->quantity;
			$updated = $db->query("update auction_calcs set 
				effective_shipping_cost = $effective_shipping_cost,
				effective_COD_cost = $effective_COD_cost
				WHERE 1=1 $where_d $where_al_d
				AND order_id = $row->order_id
				");
		    if (PEAR::isError($updated)) aprint_r($updated);
		}; // foreach calculated
//		echo 'Calculated';
}

    static function findRMAsellHTML($db, $dbr, $rma_sell_html, $rma_sell_auction, $paid, $rma_sell_auction_number, $finish_mode)
    {
	  global $seller_filter_str;
	  if (strlen($rma_sell_html)) $rma_sell_html = $rma_sell_html ? " and rma_spec.sell_html is not null " : " and rma_spec.sell_html is null ";
	  if (strlen($rma_sell_auction)) $rma_sell_auction = $rma_sell_auction ? " and (rma_spec.sell_auction is not null and not rma_spec.sell_auction='') " 
	  	: " and (rma_spec.sell_auction is null or rma_spec.sell_auction='') ";
	  if (strlen($rma_sell_auction_number)) $rma_sell_auction_number = $rma_sell_auction_number ? " and (rma_spec.sell_auction_number is not null and not rma_spec.sell_auction_number='') " 
	  	: " and (rma_spec.sell_auction_number is null or rma_spec.sell_auction_number='') ";
	  if (strlen($paid)) 
	  	if ($paid) $paid = " and open_amount = 0 ";
		else $paid = " and IFNULL(open_amount,1)  !=  0 ";
	  if (strlen($finish_mode)) $finish=" and DATE_ADD(CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time),INTERVAL rma_spec.sell_duration day) "
	  		.($finish_mode?'<':'>=').' NOW()';
	$q = "
select tt.*, sum(open_amount) open_amount from (
select t.* from (
	SELECT distinct auction.auction_number, auction.txnid
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(users.name, rma.supervisor_uname) responsible_uname,
		rma.rma_id, rma.create_date as rma_create_date , rma_spec.sell_auction 
		, CONCAT('<a href=\"http://allegro.pl/show_item.php?item=',rma_spec.sell_auction,'\">',rma_spec.sell_auction,'</a>') a_sell_auction
		, DATE_ADD(CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time),INTERVAL rma_spec.sell_duration day) sell_end_datetime
		, CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time) sell_datetime
		, rma_spec.sell_duration
		, IFNULL(users_au.name, auction.responsible_uname) au_responsible_uname
		, invoice.open_amount
		, au_i.id au_i_id
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
			join rma_spec on rma.rma_id=rma_spec.rma_id
			LEFT JOIN users ON users.username=rma.supervisor_uname 
		left join auction au_i on au_i.auction_number=rma_spec.sell_auction_number and au_i.txnid=rma_spec.sell_txnid
            left JOIN invoice ON invoice.invoice_number = au_i.invoice_number 
			LEFT JOIN users users_au ON users_au.username=auction.responsible_uname 
		WHERE rma_spec.sell_channel is not null
			and auction.main_auction_number=0
			$rma_sell_html
			$rma_sell_auction
			$rma_sell_auction_number
			$paid
			$finish
			$seller_filter_str
) t
group by t.rma_id, t.au_i_id
) tt
group by tt.rma_id
			";
      $r = $dbr->getAll($q);
	  echo $q;
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }

    static function findRMAsellauction($db, $dbr, $notnull, $paid, $finish_mode)
    {
	  global $seller_filter_str;
	  $notnull = $notnull ? " not " : " ";
	  $paid = $paid==1?" and sell_paid_date ": ($paid==''?"": " and IFNULL(sell_paid_date, '0000-00-00')='0000-00-00' ");
	  if (strlen($finish_mode)) $finish=" and DATE_ADD(CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time),INTERVAL rma_spec.sell_duration day) "
	  		.($finish_mode?'<':'>=').' NOW()';
	  $q = "SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(users.name, rma.supervisor_uname) responsible_uname,
		rma.rma_id, rma.create_date as rma_create_date, rma_spec.sell_auction
		, rma_spec.sell_price_sold, rma_spec.sell_paid_amount, rma_spec.sell_date
		,IFNULL((SELECT IFNULL(cav.description, ca.value)
			FROM config_api ca 
			JOIN config_api_par cap ON ca.par_id=cap.id
			left join config_api_values cav on ca.par_id=cav.par_id and cav.value=ca.value
			WHERE ca.value = rma_spec.sell_currency
			AND cap.name = 'currency' limit 1),rma_spec.sell_currency) sell_currency
		, CONCAT('<a href=\"http://allegro.pl/show_item.php?item=',rma_spec.sell_auction,'\">',rma_spec.sell_auction,'</a>') a_sell_auction
		, DATE_ADD(CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time),INTERVAL rma_spec.sell_duration day) sell_end_datetime
		, CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time) sell_datetime
		, rma_spec.sell_duration
		, IFNULL(users_au.name, auction.responsible_uname) responsible_uname
		, invoice.open_amount
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
            JOIN invoice ON invoice.invoice_number = auction.invoice_number 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
			join rma_spec on rma.rma_id=rma_spec.rma_id
			LEFT JOIN users ON users.username=rma.supervisor_uname 
			LEFT JOIN users users_au ON users_au.username=auction.responsible_uname 
		WHERE rma_spec.sell_channel is not null and (rma_spec.sell_auction is $notnull null or $notnull rma_spec.sell_auction='')
			and main_auction_number=0
			$paid
			$finish
			$seller_filter_str";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }

    static function findRMAsellunsold($db, $dbr, $days)
    {
	  global $seller_filter_str;
        $r = $dbr->getAll("SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(users.name, rma.supervisor_uname) responsible_uname,
		rma.rma_id, rma.create_date as rma_create_date 
		, DATE_ADD(CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time),INTERVAL rma_spec.sell_duration day) sell_end_datetime
		, CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time) sell_datetime
		, rma_spec.sell_duration
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
			join rma_spec on rma.rma_id=rma_spec.rma_id
			LEFT JOIN users ON users.username=rma.supervisor_uname 
		WHERE rma_spec.sell_channel is not null and DATEDIFF( NOW( ) , rma_spec.sell_date)<=$days
			and rma_spec.sell_date_sold is null
			and main_auction_number=0
			$seller_filter_str");
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }

    static function findRMAsellunpaid($db, $dbr, $days)
    {
	  global $seller_filter_str;
	  	$q = "SELECT auction.*
		, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
		, IFNULL(users.name, rma.supervisor_uname) responsible_uname,
		rma.rma_id, rma.create_date as rma_create_date 
		, DATE_ADD(CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time),INTERVAL rma_spec.sell_duration day) sell_end_datetime
		, CONCAT(rma_spec.sell_date,' ',rma_spec.sell_time) sell_datetime
		, rma_spec.sell_duration
		FROM auction LEFT JOIN offer ON offer.offer_id = auction.offer_id 
			join rma on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
			join rma_spec on rma.rma_id=rma_spec.rma_id
			LEFT JOIN users ON users.username=rma.supervisor_uname 
		WHERE rma_spec.sell_channel is not null #and DATEDIFF( NOW( ) , rma_spec.sell_date_sold)<=$days
			and IFNULL(sell_paid_date, '0000-00-00')='0000-00-00'
			and main_auction_number=0
			$seller_filter_str";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }
	
	function stock_recalc()
	{
		$this->_db->query("call sp_Stock_recalc_by_Articles(".$this->data->auction_number.", ".$this->data->txnid.")");
	}
	
	function getTotalWeight()
	{
		return $this->_dbr->getOne("select sum(a.weight_per_single_unit*o.quantity) 
			from orders o 
			join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
			join article a on a.article_id=o.article_id and a.admin_id=o.manual
			where (o.auction_number=".$this->data->auction_number." and o.txnid=".$this->data->txnid.")
			OR (au.main_auction_number=".$this->data->auction_number." and au.main_txnid=".$this->data->txnid.")");
	}

	function getTotalVolume()
	{
		return $this->_dbr->getOne("select sum(a.volume_per_single_unit*o.quantity) 
			from orders o join article a on a.article_id=o.article_id and a.admin_id=o.manual
			join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
			where (o.auction_number=".$this->data->auction_number." and o.txnid=".$this->data->txnid.")
			OR (au.main_auction_number=".$this->data->auction_number." and au.main_txnid=".$this->data->txnid.")");
	}

	function getEventDate($template, $pos)
	{
		$log_table = $this->_dbr->getOne('select log_table from template_names where name = "' . $template . '" and not hidden');
		if (!$log_table)
			 $log_table = 'email_log';
	
		return $this->_dbr->getOne("select date from $log_table 
				where auction_number=".$this->data->auction_number." 
				and txnid=".$this->data->txnid." and template='$template'
				order by date limit $pos,1");
	}

    static function findCustom($db, $dbr, $paid, $buyer, $src, $deleted = 'AND auction.deleted = 0')
    {
	  global $seller_filter_str;
	  global $loggedUser;
        $days = (int)$days;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$where = '';
		if (strlen($buyer)) $where = " and auction.customer_id=$buyer";
		$q=            "SELECT auction.auction_number
			, auction.main_auction_number
			, auction.username
			, auction.txnid
			, auction.deleted
			, auction.end_time
			, auction.delivery_date_customer
			, auction.responsible_uname
			, customer.password
			, customer.email
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, IF(TO_DAYS(NOW())=TO_DAYS(delivery_date_customer), 
					CONCAT('<span style=".'"color:#FF0000;font-weight:bold;text-decoration:blink"'.">', delivery_date_customer, '</span>'),
					IF(TO_DAYS(NOW())>TO_DAYS(delivery_date_customer),
						CONCAT('<span style=".'"color:#FF0000;font-weight:bold;text-decoration:blink"'.">', delivery_date_customer, '</span>')
						,delivery_date_customer)) delivery_date_customer_colored,
				sum(p.amount) paid_amount, 
				max( p.payment_date ) paid_date
				, (select min(log_date) from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					and printer_log.username='".$loggedUser->data->username."') print_date
				, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping	
				, invoice.open_amount as open_amount	
				, fget_ITotal(invoice.invoice_number) total_amount
				, IFNULL(max(el.date),auction.end_time) date_confirmation
			    , (select SUM(ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                        + IFNULL(invoice.total_shipping, IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0))
						+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
						- IFNULL(ac.packing_cost,0))/IFNULL(ac.curr_rate,0), 2))
					from auction_calcs ac
					join auction au on au.auction_number = ac.auction_number AND au.txnid = ac.txnid 
					where (auction.auction_number=au.auction_number and auction.txnid=au.txnid)
					) 
					+
			        (select SUM(ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                        + IFNULL(invoice.total_shipping, IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0))
						+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
						- IFNULL(ac.packing_cost,0))/IFNULL(ac.curr_rate,0), 2))
					from auction_calcs ac
					join auction au on au.auction_number = ac.auction_number AND au.txnid = ac.txnid 
					where (au.main_auction_number=auction.auction_number and au.main_txnid=auction.txnid)
					) as brutto_income_2
            FROM auction
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
            LEFT JOIN offer
            ON offer.offer_id = auction.offer_id
            JOIN customer".$src." customer ON auction.customer_id = customer.id
            LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
			LEFT JOIN payment p ON p.auction_number = auction.auction_number AND p.txnid = auction.txnid
			LEFT JOIN email_log el ON el.template = 'order_confirmation'
					AND el.auction_number = auction.auction_number
					AND el.txnid = auction.txnid
            WHERE (auction.paid = '$paid' or '$paid'='') 
				$deleted
			and IFNULL(auction.customer_id,0) 
			and fget_AType(auction.auction_number, auction.txnid)='$src'
			and main_auction_number=0
			$where
			$seller_filter_str
			GROUP BY auction.auction_number, auction.txnid
			";
//		echo $q; echo '<br>'; die();
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
/*		foreach ($r as $k=>$dummy) {
			$source='_auction';
			$txnid=$r[$k]->txnid;
			if ($txnid==0) {
				if ($r[$k]->main_auction_number>0) $aunumber=$r[$k]->main_auction_number;
					else $aunumber=$r[$k]->auction_number;
//				echo "aunumber=$aunumber, =$txnid";	
				$auserv = new Auction($db, $dbr, $aunumber, $txnid);
				if ($server = $auserv->get('server')) {
					$shopname = $dbr->getOne("select name from shop where url='$server'");
					if (strlen($shopname)) {
						$source='';
					}
				}
			}
			if ($src!=$source)
				unset($r[$k]);
		}*/
        return $r;
    }

    static function findByComment($db, $dbr, $comment_src, $comment)
    {
		global $seller_filter_str;
		$q = array();
		if ($comment_src=='' || $comment_src=='Auf') {
		  	$q[] = "select au.auction_number, au.txnid,
				IF(au.txnid=4
					,CONCAT('<a href=\"route_task.php?auction_number=',au.auction_number,'\">Auftrag ',au.auction_number,'/',au.txnid,'</a>')
					,CONCAT('<a href=\"auction.php?number=',au.auction_number, '&txnid=',au.txnid,'\">Auftrag ',au.auction_number,'/',au.txnid,'</a>')
				) as object_url
				, max(ac.create_date) last_date
				from auction au
				join auction_comment ac on ac.comment like '%$comment%' and ac.auction_number=au.auction_number and ac.txnid=au.txnid
				group by au.id
				";
		}
		if ($comment_src=='' || $comment_src=='Rma') {
		  	$q[] = "select rma.auction_number, rma.txnid,
				CONCAT('<a href=\"rma.php?rma_id=',rma.rma_id,'&number=',rma.auction_number, '&txnid=',rma.txnid,'\">Ticket ',rma.rma_id,'</a>') as object_url
				, max(rc.create_date) last_date
				from rma
				join rma_comment rc where rc.comment like '%$comment%' and rc.rma_id=rma.rma_id
				";
		}
		if ($comment_src=='' || $comment_src=='Shp') {
		  	$q[] = "select au.auction_number, au.txnid,
				CONCAT('<a href=\"shipping_auction.php?number=',au.auction_number, '&txnid=',au.txnid,'\">Auftrag ',au.auction_number,'/',au.txnid,'</a>') as object_url
				, max(ac.create_date) last_date
				from auction au
				join auction_sh_comment ac where ac.comment like '%$comment%' and ac.auction_number=au.auction_number and ac.txnid=au.txnid
				";
		}
		if ($comment_src=='' || $comment_src=='Rat') {
		  	$q[] = "select rating.auction_number, rating.txnid,
				CONCAT('<a href=\"rating_case.php?id=',rating.id,'\">Rating case ',rating.id,'</a>') as object_url
				, max(rc.create_date) last_date
				from rating 
				join rating_comment rc where rc.comment like '%$comment%' and rc.rating_id=rating.id
				";
		}
		if ($comment_src=='' || $comment_src=='Ins') {
		  	$q[] = "select insurance.auction_number, insurance.txnid,
				CONCAT('<a href=\"insurance.php?id=',insurance.id,'\">INS case ',insurance.id,'</a>') as object_url
				, max(ic.create_date) last_date
				from insurance 
				join ins_comment ic where ic.comment like '%$comment%' and ic.ins_id=insurance.id
				";
		}
		$q = "select * from (".implode(" union ", $q).") t";
//		echo $q; echo '<br>'; die();
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findByAlarm($db, $dbr, $alarm_type, $alarm_username, $status='all', $date='')
    {
		global $seller_filter_str;
		global $siteURL;
		$q = array();
		if (strlen($alarm_username)) $where .= " and alarms.username='$alarm_username'";

                
		if (strlen($date)) $where .= " and DATE(alarms.date)='$date'";
		if (strlen($status)) $where .= " and alarms.status='$status'";
				
		if ($alarm_type=='' || $alarm_type=='WWO') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}ware2ware_order.php?id=',ww_order.id,'\">WWO ',alarms.type_id,'</a>') as object_url
				, 'WWO' as real_obj
				, ww_order.id as real_id
				, ww_order.id as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from ww_order 
				join alarms on alarms.type='ww_order' and alarms.type_id=ww_order.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='OPC') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}op_order.php?id=',op_order_container.order_id,'&container_id=',alarms.type_id,'\">OP Order Container ',alarms.type_id,'</a>') as object_url
				, 'OP Order' as real_obj
				, op_order_container.order_id as real_id
				, op_order_container.container_no as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from op_order_container 
				join alarms on alarms.type='op_order_container' and alarms.type_id=op_order_container.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='OFF') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}offer.php?id=',alarms.type_id,'\">Offer ',alarms.type_id,'</a>') as object_url
				, 'Offer' as real_obj
				, offer.offer_id as real_id
				, offer.name as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from offer 
				join alarms on alarms.type='offer' and alarms.type_id=offer.offer_id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='ART') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a class=\"splink3\" href=\"{$siteURL}article.php?original_article_id=',article.article_id,'\">Article ',article.article_id,'</a>') as object_url
				, 'Article' as real_obj
				, article.article_id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from article 
				join alarms on alarms.type='article' and alarms.type_id=article.iid
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='SA') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}newauction.php?edit=',alarms.type_id,'\">SA ',alarms.type_id,'</a>') as object_url
				, 'SA' as real_obj
				, saved_auctions.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from saved_auctions 
				join alarms on alarms.type='saved_auctions' and alarms.type_id=saved_auctions.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='Auf') {
		  	$q[] = "select auction.auction_number, auction.txnid, alarms.id as alarm_id,
				IF(auction.txnid=4 
					, CONCAT('<a href=\"{$siteURL}route_task.php?auction_number=',auction.auction_number,'\">Driver task ',auction.auction_number,'</a>')
					, CONCAT('<a href=\"{$siteURL}auction.php?number=',auction.auction_number, '&txnid=',auction.txnid,'\">Auftrag ',auction.auction_number,'/',auction.txnid,'</a>') 
				) as object_url
				, IF(auction.txnid=4, 'Driver task', 'Auftrag ')  as real_obj
				, IF(auction.txnid=4, auction.auction_number, concat(auction.auction_number,'/',auction.txnid))  as real_id
				, IFNULL(offer.name,
					(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
						join auction a1 on a1.offer_id=o1.offer_id
						where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from auction
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
				join alarms on alarms.type='auction' and alarms.type_id=auction.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='Rma') {
		  	$q[] = "select auction.auction_number, auction.txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}rma.php?rma_id=',rma.rma_id,'&number=',rma.auction_number, '&txnid=',rma.txnid,'\">Ticket ',rma.rma_id,'</a>') as object_url
				, 'Ticket' as real_obj
				, rma.rma_id as real_id
				, IFNULL(offer.name,
					(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
						join auction a1 on a1.offer_id=o1.offer_id
						where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, 'Ticket' type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from rma
				join auction on rma.auction_number=auction.auction_number and rma.txnid=auction.txnid
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
				join alarms on alarms.type='rma' and alarms.type_id=rma.rma_id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='Shp') {
		  	$q[] = "select auction.auction_number, auction.txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}shipping_auction.php?number=',auction.auction_number, '&txnid=',auction.txnid,'\">Shipping order ',auction.auction_number,'/',auction.txnid,'</a>') as object_url
				, 'Shipping order' as real_obj
				, CONCAT(auction.auction_number,'/',auction.txnid) as real_id
				, IFNULL(offer.name,
					(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
						join auction a1 on a1.offer_id=o1.offer_id
						where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from auction
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
				join alarms on alarms.type='sh_auction' and alarms.type_id=auction.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='Rat') {
		  	$q[] = "select auction.auction_number, auction.txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}rating_case.php?id=',rating.id,'\">Rating case ',rating.id,'</a>') as object_url
				, 'Rating case' as real_obj
				, rating.id as real_id
				, IFNULL(offer.name,
					(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
						join auction a1 on a1.offer_id=o1.offer_id
						where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from rating 
				join auction on rating.auction_number=auction.auction_number and rating.txnid=auction.txnid
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
				join alarms on alarms.type='rating' and alarms.type_id=rating.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='Ins') {
		  	$q[] = "select auction.auction_number, auction.txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}insurance.php?id=',insurance.id,'\">INS case ',insurance.id,'</a>') as object_url
				, 'INS case' as real_obj
				, insurance.id as real_id
				, IFNULL(offer.name,
					(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
						join auction a1 on a1.offer_id=o1.offer_id
						where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from insurance 
				join auction on insurance.auction_number=auction.auction_number and insurance.txnid=auction.txnid
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
				join alarms on alarms.type='ins' and alarms.type_id=insurance.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='newsmail') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}news_email.php?id=',alarms.type_id,'\">Newsmail ',alarms.type_id,'</a>') as object_url
				, 'Newsmail' as real_obj
				, shop_spam.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from shop_spam 
				join alarms on alarms.type='newsmail' and alarms.type_id=shop_spam.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='employee') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',employee.id,'\">Employee ',employee.name,' ',employee.name2,'</a>') as object_url
				, 'Employee' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from employee 
				join alarms on alarms.type='employee' and alarms.type_id=employee.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
                
                /**
                 * @description alarms if is end employment
                 * @var $alarm_type
                 * @var $q
                 * @var $siteURL
                 */
                if ($alarm_type=='' || $alarm_type=='employee_endemployment') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',employee.id,'\">Employee ',employee.name,' ',employee.name2,'</a>') as object_url
				, 'Employee end of employment' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, users.name username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from employee 
				join alarms on alarms.type='employee_endemployment' and alarms.type_id=employee.id
                                LEFT JOIN users ON users.username = alarms.username
				where 1 and users.deleted=0 and employee.inactive = 0 $where
				";
		}
                
		if ($alarm_type=='' || $alarm_type=='employee_CH_Work') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',employee.id,'\">Employee ',employee.name,' ',employee.name2,'</a>') as object_url
				, 'Employee CH Work permission' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, users.name username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from employee 
				join alarms on alarms.type='employee_CH_Work' and alarms.type_id=employee.id and alarms.username is null
                JOIN users ON users.not_logout_email_get=1
				where 1 and users.deleted=0 and employee.inactive = 0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='employee_Doctor') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',employee.id,'\">Employee ',employee.name,' ',employee.name2,'</a>') as object_url
				, 'Employee Doctor\'s examination' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, users.name username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from employee 
				join alarms on alarms.type='employee_Doctor' and alarms.type_id=employee.id and alarms.username is null
                JOIN users ON users.not_logout_email_get=1
				where 1 and users.deleted=0 and employee.inactive = 0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='employee_BHP') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',employee.id,'\">Employee ',employee.name,' ',employee.name2,'</a>') as object_url
				, 'Employee BHP' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, users.name username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from employee 
				join alarms on alarms.type='employee_BHP' and alarms.type_id=employee.id and alarms.username is null
                JOIN users ON users.not_logout_email_get=1
				where 1 and users.deleted=0 and employee.inactive = 0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='employee_EKUZ') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',employee.id,'\">Employee ',employee.name,' ',employee.name2,'</a>') as object_url
				, 'Employee EKUZ' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, users.name username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from employee 
				join alarms on alarms.type='employee_EKUZ' and alarms.type_id=employee.id and alarms.username is null
                JOIN users ON users.not_logout_email_get=1
				where 1 and users.deleted=0 and employee.inactive = 0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='emp_soft') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}employee.php?id=',emp_soft.emp_id,'\">Employee ',employee.name,' ',employee.name2,', soft ',soft.name,'</a>') as object_url
				, 'Employee soft' as real_obj
				, employee.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from emp_soft 
				join soft on soft.id=emp_soft.soft_id
				join employee on employee.id=emp_soft.emp_id
				join alarms on alarms.type='emp_soft' and alarms.type_id=emp_soft.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='op_company') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}op_suppliers.php?company_id=',alarms.type_id,'\">Supplier ',alarms.type_id,'</a>') as object_url
				, 'Supplier' as real_obj
				, op_company.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from op_company 
				join alarms on alarms.type='op_company' and alarms.type_id=op_company.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='fork_lift') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}fork_lift.php\">Fork lift  ',alarms.type_id,'</a>') as object_url
				, 'Fork lift' as real_obj
				, fork_lift.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from fork_lift 
				join alarms on alarms.type='fork_lift' and alarms.type_id=fork_lift.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='customer') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}customer.php?id=',customer.id,'&src=\">Shop customer  ',alarms.type_id,'</a>') as object_url
				, 'Shop customer' as real_obj
				, customer.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from customer 
				join alarms on alarms.type='customer' and alarms.type_id=customer.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='customer_auction') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}customer.php?id=',customer.id,'&src=_auction\">Auction customer  ',alarms.type_id,'</a>') as object_url
				, 'Auction customer' as real_obj
				, customer.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from customer_auction customer 
				join alarms on alarms.type='customer_auction' and alarms.type_id=customer.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
		if ($alarm_type=='' || $alarm_type=='customer_jour') {
		  	$q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
				CONCAT('<a href=\"{$siteURL}customer.php?id=',customer.id,'&src=_jour\">Journalist customer  ',alarms.type_id,'</a>') as object_url
				, 'Journalist customer' as real_obj
				, customer.id as real_id
				, '' as offer_name
				, alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
				, IFNULL(users.name, alarms.username) username
				, alarms.type, alarms.type_id, alarms.status
				, users.email as email_invoice
				from customer_jour customer
				join alarms on alarms.type='customer_jour' and alarms.type_id=customer.id
                LEFT JOIN users ON users.username = alarms.username 
				where 1 and users.deleted=0 $where
				";
		}
        if ($alarm_type=='' || $alarm_type=='cars') {
            $q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
                CONCAT('<a href=\"{$siteURL}car.php?id=',cars.id,'\">Car  ',alarms.type_id,'</a>') as object_url
                , 'Car' as real_obj
                , cars.id as real_id
                , '' as offer_name
                , alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
                , IFNULL(users.name, alarms.username) username
                , alarms.type, alarms.type_id, alarms.status
                , users.email as email_invoice
                from cars
                join alarms on alarms.type='cars' and alarms.type_id=cars.id
                LEFT JOIN users ON users.username = alarms.username 
                where 1 and users.deleted=0 $where
                ";
        }
        /**
         * issue alarms
         */
        if ($alarm_type=='' || $alarm_type=='issuelog') {
            $q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
                CONCAT('<a href=\"{$siteURL}react/logs/issue_logs/',issuelog.id,'/\">Issue  ',alarms.type_id,'</a>') as object_url
                , 'Issue' as real_obj
                , issuelog.id as real_id
                , '' as offer_name
                , alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
                , IFNULL(users.name, alarms.username) username
                , alarms.type, alarms.type_id, alarms.status
                , users.email as email_invoice
                from issuelog
                join alarms on alarms.type='issuelog' and alarms.type_id=issuelog.id
                LEFT JOIN users ON users.username = alarms.username 
                where 1 and users.deleted=0 $where
                ";
        }
        /**
         * issue routes
         */
        if ($alarm_type=='' || $alarm_type=='route') {
            $q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
                CONCAT('<a href=\"{$siteURL}route.php?id=',route.id,'\">Route  ',alarms.type_id,'</a>') as object_url
                , 'Route' as real_obj
                , route.id as real_id
                , '' as offer_name
                , alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
                , IFNULL(users.name, alarms.username) username
                , alarms.type, alarms.type_id, alarms.status
                , users.email as email_invoice
                from route
                join alarms on alarms.type='route' and alarms.type_id=route.id
                LEFT JOIN users ON users.username = alarms.username 
                where 1 and users.deleted=0 $where
                ";
        }
        if ($alarm_type=='' || $alarm_type=='picking_list') {
            $q[] = "select '' auction_number, '' txnid, alarms.id as alarm_id,
                CONCAT('<a href=\"{$siteURL}picking_list.php?id=',picking_list.id,'\">Picking list  ',alarms.type_id,'</a>') as object_url
                , 'Picking list' as real_obj
                , picking_list.id as real_id
                , '' as offer_name
                , alarms.comment, alarms.date, DATEDIFF(alarms.date,NOW()) days
                , IFNULL(users.name, alarms.username) username
                , alarms.type, alarms.type_id, alarms.status
                , users.email as email_invoice
                from picking_list
                join alarms on alarms.type='picking_list' and alarms.type_id=picking_list.id
                LEFT JOIN users ON users.username = alarms.username 
                where 1 and users.deleted=0 $where
                ";
        }
		$q = "select * from (".implode(" union ", $q).") t";
//		echo $q; echo '<br>'; die();
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findCustomResp($db, $dbr, $paid, $resp)
    {
	  global $seller_filter_str;
	  global $loggedUser;
	  	if (strlen($resp)) $where = " and auction.responsible_uname='$resp' ";
        $days = (int)$days;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Auction::findReadyToShip expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$q=            "SELECT auction.auction_number
			, auction.txnid
			, auction.end_time
			, auction.delivery_date_customer
			, auction.responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, IF(TO_DAYS(NOW())=TO_DAYS(delivery_date_customer), 
					CONCAT('<span style=".'"color:#FF0000;font-weight:bold;text-decoration:blink"'.">', delivery_date_customer, '</span>'),
					IF(TO_DAYS(NOW())>TO_DAYS(delivery_date_customer),
						CONCAT('<span style=".'"color:#FF0000;font-weight:bold;text-decoration:blink"'.">', delivery_date_customer, '</span>')
						,delivery_date_customer)) delivery_date_customer_colored,
				sum(p.amount) paid_amount, 
				IFNULL(max( p.payment_date ), 'FREE') paid_date
				, (select min(log_date) from printer_log 
					where auction_number=auction.auction_number and txnid=auction.txnid
					and printer_log.username='".$loggedUser->data->username."') print_date
				, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping	
            FROM auction
			left join auction_par_varchar au_name_shipping on auction.auction_number=au_name_shipping.auction_number
				and auction.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
			left join auction_par_varchar au_firstname_shipping on auction.auction_number=au_firstname_shipping.auction_number
				and auction.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
            LEFT JOIN offer
            ON offer.offer_id = auction.offer_id
            LEFT JOIN invoice ON auction.invoice_number = invoice.invoice_number
			LEFT JOIN payment p ON p.auction_number = auction.auction_number AND p.txnid = auction.txnid
            WHERE auction.txnid=0 and (auction.paid = '$paid' or '$paid'='') AND auction.deleted = 0
			and main_auction_number=0
			$where
			$seller_filter_str
			GROUP BY auction.auction_number, auction.txnid
			";
        $r = $dbr->getAll($q);
//		echo $q; echo '<br>';
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

	function getLastPaymentDate() {
		return $this->_dbr->getOne("select payment_date from payment 
			where auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid." and listingfee=0
			order by payment_id desc limit 1");
	}

	function getLastPaymentAccount() {
		return $this->_dbr->getOne("select account from payment 
			where auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid." and listingfee=0
			order by payment_id desc limit 1");
	}

	function getLastPaymentDateFree() {
		return $this->_dbr->getOne("select payment_date from payment 
			where auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid." and listingfee=1
			order by payment_id desc limit 1");
	}

	function getLastPaymentAccountFree() {
		return $this->_dbr->getOne("select account from payment 
			where auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid." and listingfee=1
			order by payment_id desc limit 1");
	}

	function getLastPaymentVatAccountFree() {
		return $this->_dbr->getOne("select vat_account_number from payment 
			where auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid." and listingfee=1
			order by payment_id desc limit 1");
	}

	function getLastPaymentSellingAccountFree() {
		return $this->_dbr->getOne("select selling_account_number from payment 
			where auction_number=".$this->data->auction_number." and txnid=".$this->data->txnid." and listingfee=1
			order by payment_id desc limit 1");
	}
	
	function mass_doc() {
		$spam_tasks = $this->_dbr->getAll("select * from spam");
		foreach ($spam_tasks as $task) {
			$task_sellers = $this->_dbr->getAssoc("select seller_name f1, seller_name f2 from spam_seller where spam_id=".$task->spam_id);
				if (!in_array($this->data->username,$task_sellers)) {
					continue;
				}	
				$docs = [];
                
                $query = "select * from spam_doc where spam_id={$task->spam_id}";
                foreach ($this->_dbr->getAll($query) as $_doc) {
                    $_doc->data = get_file_path($_doc->data);
                    $docs[] = $_doc;
                }
                
				$auction4email = new Auction($this->_db, $this->_dbr, $this->data->auction_number, $this->data->txnid);
				$auction4email->data->docs = $docs;
				standardEmail($this->_db, $this->_dbr, $auction4email, 'mass_doc');
		}
	}
	
	function mass_doc_win() {
		$spam_tasks = $this->_dbr->getAll("select * from spam_win");
		foreach ($spam_tasks as $task) {
			$task_sellers = $this->_dbr->getAssoc("select seller_name f1, seller_name f2 from spam_win_seller where spam_win_id=".$task->spam_win_id);
				if (!in_array($this->data->username,$task_sellers)) {
					continue;
				}	
                
                $docs = [];
                
                $query = "select * from spam_win_doc where spam_win_id={$task->spam_win_id}";
                foreach ($this->_dbr->getAll($query) as $_doc) {
                    $_doc->data = get_file_path($_doc->data);
                    $docs[] = $_doc;
                }
                
				$auction4email = new Auction($this->_db, $this->_dbr, $this->data->auction_number, $this->data->txnid);
				$auction4email->data->docs = $docs;
				standardEmail($this->_db, $this->_dbr, $auction4email, 'mass_doc_win');
		}
	}

	function mass_doc_rma() {
		$spam_tasks = $this->_dbr->getAll("select * from spam_rma");
		foreach ($spam_tasks as $task) {
			$task_sellers = $this->_dbr->getAssoc("select seller_name f1, seller_name f2 from spam_rma_seller where spam_rma_id=".$task->spam_rma_id);
				if (!in_array($this->data->username,$task_sellers)) {
					continue;
				}	
				$docs = [];
                
                $query = "select * from spam_rma_doc where spam_rma_id={$task->spam_rma_id}";
                foreach ($this->_dbr->getAll($query) as $_doc) {
                    $_doc->data = get_file_path($_doc->data);
                    $docs[] = $_doc;
                }
                
				$auction4email = new Auction($this->_db, $this->_dbr, $this->data->auction_number, $this->data->txnid);
				$auction4email->data->docs = $docs;
				standardEmail($this->_db, $this->_dbr, $auction4email, 'mass_doc_after_ticket_open');
		}
	}

    /**
     * 
     * @global Smarty $smarty
     * @global type $debug
     * @param type $datefrom
     * @param type $dateto
     * @param type $sellername
     * @param type $type
     * @return \stdClass
     */
	static function export_to_ricardo($datefrom, $dateto, $sellername, $type = 'xls') {
        global $smarty, $debug;

        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        require_once __DIR__ . '/../Spreadsheet/Excel/Writer.php';
        require_once __DIR__ . '/../plugins/function.imageurl.php';

        versions();
        
        if ($type == 'xls') {
            $fn = __DIR__ . '/../tmppic/temp' . rand(100000, 999999) . '.xls';
            $workbook = new Spreadsheet_Excel_Writer($fn);
            $workbook->setTempDir(__DIR__ . '/../tmppic/');
            $workbook->setVersion(8);
            $sheet = $workbook->addWorksheet();
            $sheet->setInputEncoding('UTF-8');
        }

        $RicardoWarrantyValue = $dbr->getAssoc("select `key`,`keyid` from list_value where par_name='WarrantyValue'");
        $RicardoStateValue = $dbr->getAssoc("select `key`,`keyid` from list_value where par_name='StateValue'");
        $RicardoAvailabilityValue = $dbr->getAssoc("select `key`,`value` from list_value where par_name='AvailabilityValue'");
        $RicardoShippingConditionsDe = $dbr->getAssoc("select `key`,`value` from list_value where par_name='ShippingConditionsDe'");
        $RicardoShippingConditionsFr = $dbr->getAssoc("select `key`,`value` from list_value where par_name='ShippingConditionsFr'");
        $RicardoPaymentConditionsDe = $dbr->getAssoc("select `key`,`value` from list_value where par_name='PaymentConditionsDe'");
        $RicardoPaymentConditionsFr = $dbr->getAssoc("select `key`,`value` from list_value where par_name='PaymentConditionsFr'");

        $types = ['1' => 'Auktion', '2' => 'Fixpreis-Auktion'];
        $csv = '';
        $csv1 = '';
        $csvs = array();

        $fields = array("Id", "FolderId", "StartPrice", "BuyNowPrice", "AvailabilityId", "Duration", "FeaturedHomePage", "Shipping", "Payment", "Warranty"
            , "Quantity", "Increment", "CategoryNr", "CategoryName", "Condition", "ShippingCost", "PaymentCondition", "ReminderThreshold", "ResellCount", "BuyNow", "BuyNowCost"
            , "TemplateName", "StartDate", "StartImmediately", "EndDate", "HasFixedEndDate", "InternalReference", "TemplateId", "PackageSizeId", "PromotionId"
            , "Hihglight", "IsCarsBikesAccessoriesArticle"
            , "Descriptions[0].LanguageNr", "Descriptions[0].ProductTitle", "Descriptions[0].ProductDescription", "Descriptions[0].ProductSubtitle"
            , "Descriptions[0].PaymentDescription", "Descriptions[0].ShippingDescription", "Descriptions[0].WarrantyDescription", "Descriptions[0].FullPathCategory"
            , "Descriptions[0].IsEmpty", "Descriptions[0].ArticleUrl"
            , "Descriptions[1].LanguageNr", "Descriptions[1].ProductTitle", "Descriptions[1].ProductDescription", "Descriptions[1].ProductSubtitle"
            , "Descriptions[1].PaymentDescription", "Descriptions[1].ShippingDescription", "Descriptions[1].WarrantyDescription", "Descriptions[1].FullPathCategory"
            , "Descriptions[1].IsEmpty", "Descriptions[1].ArticleUrl"
            , "DraftImages[0]", "DraftImages[1]", "DraftImages[2]", "DraftImages[3]", "DraftImages[4]", "DraftImages[5]", "DraftImages[6]", "DraftImages[7]", "DraftImages[8]", "DraftImages[9]"
            , "IsFixedPrice", "HeaderAndFooter", "ArticleCondition", "Availability", "PaymentCode", "IsCumulativeShipping", "ArticleUrl"
        );

        foreach ($fields as $key => $field) {
            if ($type == 'xls')
                $sheet->writeString(0, $key, $field);
            $csv .= '"' . $field . '";';
            $csv1 .= '"' . $field . '";';
        }

        $csv .= "\n";
        $csv1 .= "\n";
        $csvs[] = $csv1;
        $csv1 = '';
        $arrname = $_POST['action'] . '_group';

        $n = 0;
        $pics = array();
        $local_pics = array();
        if ($debug) {
            $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
            echo 'before Auction::findStarted: ' . xdebug_time_index() . " mem=$mem<br>\n";
            file_put_contents('findStarted', 'before Auction::findStarted: ' . xdebug_time_index() . " mem=$mem\n$q\n", FILE_APPEND);
        }

        $results = \Auction::findStarted($db, $dbr, $datefrom, $dateto, $sellername);
        $seller = new \SellerInfo($db, $dbr, $sellername);

        foreach ($results as $auction) {
            $n++;

            if ($debug) {
                $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
                echo "($n / " . count($results) . ") -> each SA: {$auction->saved_id} " . xdebug_time_index() . " mem=$mem<br>\n";
                file_put_contents('findStarted', "each SA: {$auction->saved_id} " . xdebug_time_index() . " mem=$mem\n$q\n", FILE_APPEND);
            }

            $details = $auction->details_orig;
            if ($details['Ricardo']['Channel'] == 2) {
                $shipping_cost_seller = $auction->fshipping_cost;

                $channel = $seller->get('seller_channel_id');
                switch ($channel) {
                    case 1:
                        if ($details['fixedprice']) {
                            $shipping_plan_fn = 'fshipping_plan';
                            $shipping_plan_id_fn = 'fshipping_plan_id';
                            $shipping_plan_free_fn = 'fshipping_plan_free';
                        } else {
                            $shipping_plan_fn = 'shipping_plan';
                            $shipping_plan_id_fn = 'shipping_plan_id';
                            $shipping_plan_free_fn = 'shipping_plan_free';
                        }
                        break;
                    case 2:
                        if ($details['Ricardo']['Channel'] == 2) {
                            $shipping_plan_fn = 'fshipping_plan';
                            $shipping_plan_id_fn = 'fshipping_plan_id';
                            $shipping_plan_free_fn = 'fshipping_plan_free';
                        } else {
                            $shipping_plan_fn = 'shipping_plan';
                            $shipping_plan_id_fn = 'shipping_plan_id';
                            $shipping_plan_free_fn = 'shipping_plan_free';
                        }
                        break;
                    case 3:
                        $shipping_plan_id_fn = 'sshipping_plan_id';
                        $shipping_plan_fn = 'sshipping_plan';
                        $shipping_plan_free_fn = 'sshipping_plan_free';
                        break;
                    case 4:
                        $shipping_plan_id_fn = 'sshipping_plan_id';
                        $shipping_plan_fn = 'sshipping_plan';
                        $shipping_plan_free_fn = 'sshipping_plan_free';
                        break;
                    case 5:
                        if ($details['Allegro']['Channel'] == 2) {
                            $shipping_plan_fn = 'fshipping_plan';
                            $shipping_plan_id_fn = 'fshipping_plan_id';
                            $shipping_plan_free_fn = 'fshipping_plan_free';
                        } else {
                            $shipping_plan_fn = 'shipping_plan';
                            $shipping_plan_id_fn = 'shipping_plan_id';
                            $shipping_plan_free_fn = 'shipping_plan_free';
                        }
                        break;
                }
                
                $sellerUsername = $seller->get('username');
                $shopPrice = isset($details[$sellerUsername]['BuyItNowPrice']) ? $details[$sellerUsername]['BuyItNowPrice'] : $details['ShopPrice'];
    
                $q = "select 
                        IF((si.free_shipping AND si.free_shipping_above <= '{$shopPrice}') or si.free_shipping_total or IFNULL(t_o.value,0) or offer.{$shipping_plan_fn}_free, 0, spc.shipping_cost) shipping_cost
                                            from saved_auctions sa
                            join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
                            join offer on offer.offer_id=sp_offer.par_value
                            join seller_information si on si.username='" . $details['username'] . "'
                                            left join translation t_o
                                                on t_o.language='" . $details['siteid'] . "'
                                                and t_o.id=sp_offer.par_value
                                                and t_o.table_name='offer' and t_o.field_name='{$shipping_plan_fn}_free_tr'
                                            join translation
                                                on translation.language='" . $details['siteid'] . "'
                                                and translation.id=sp_offer.par_value
                                                and translation.table_name='offer' and translation.field_name='{$shipping_plan_fn}_id'
                                            join shipping_plan_country spc on spc.shipping_plan_id=translation.value
                                            and spc.country_code = si.defshcountry
                                            where sa.id=" . $details['saved_id'];

                $shipping_cost_seller = $dbr->getOne($q);
            } else {
                $shipping_cost_seller = $auction->shipping_cost;
            }

            if (!isset($pics[$auction->gallery . $auction->auction_number])) {
                $exts = explode('.', $auction->gallery);
                $ext = end($exts);
                //$pic = file_get_contents($auction->gallery);
                $pics[$auction->gallery . $auction->auction_number] = basename($auction->gallery, '.' . $ext) . "{$auction->auction_number}.{$ext}";
            }
            if (!isset($details['Ricardo']['Channel']))
                $details['Ricardo']['Channel'] = 1;

            $details['Ricardo']['AvailabilityID'] = $details['Ricardo']['AvailabilityId'] = $auction->available ? 0 : $auction->AvailabilityID;
            $master_sa = (int) $details['master_sa'];
            if ($master_sa) {
                $masterpics = explode(',', $auction->masterpics);
            } else {
                $masterpics = [];
            }
            
            $main_masterpic_id = $masterpics[0];
            $main_masterpic_id = explode('-', $main_masterpic_id);
            $main_masterpic_id = (int)$main_masterpic_id[0];
            
            $exists_color_masterpics = [];
            $exists_white_masterpics = [];
            foreach ($masterpics as $_key => $_value) {
                $_pic_id = explode('-', $_value);
                $_pic_type = $_pic_id[1];
                $_pic_id = (int)$_pic_id[0];
                
                if ($_pic_id != $main_masterpic_id) {
                    if ($_pic_type == 'color') {
                        $exists_color_masterpics[$_pic_id] = true;
                    } else {
                        $exists_white_masterpics[$_pic_id] = true;
                    }
                }
            }
            
            $exists_color_masterpics = count(array_values($exists_color_masterpics));
            $exists_white_masterpics = count(array_values($exists_white_masterpics));

            $main_masterpic_color = $exists_color_masterpics >= $exists_white_masterpics ? 'color' : 'whitesh';

            foreach ($masterpics as $_key => $_value) {
                $_pic_id = explode('-', $_value);
                $_pic_id = (int)$_pic_id[0];
                
                if ($_pic_id != $main_masterpic_id) {
                    if (stripos($_value, $main_masterpic_color) === false) {
                        unset($masterpics[$_key]);
                    }
                }
            }
            
            $masterpics = array_values($masterpics);
            
            $details['Ricardo']['IsFixedPrice'] = $details['Ricardo']['Channel'] == 2 ? 'True' : 'False';
            if ($debug) {
                $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
                echo 'before fields: ' . xdebug_time_index() . " mem=$mem<br>\n";
                file_put_contents('findStarted', 'before fields: ' . xdebug_time_index() . " mem=$mem\n$q\n", FILE_APPEND);
            }

            foreach ($fields as $key => $field) {
                switch ($field) {
                    case 'PackageSizeId':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 5);
                        $csv .= '"5"';
                        $csv1 .= '"5"';
                        break;
                    case 'CategoryType':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, 'Normal');
                        $csv .= '"Normal"';
                        $csv1 .= '"Normal"';
                        break;
                    case 'Lng1':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, 'de');
                        $csv .= '"de"';
                        $csv1 .= '"de"';
                        break;
                    case 'Lng2':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, 'fr');
                        $csv .= '"fr"';
                        $csv1 .= '"fr"';
                        break;
                    case 'Warranty':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'Condition':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $RicardoStateValue[$details['Ricardo']['StateValue']]);
                        $csv .= '"' . $RicardoStateValue[$details['Ricardo']['StateValue']] . '"';
                        $csv1 .= '"' . $RicardoStateValue[$details['Ricardo']['StateValue']] . '"';
                        break;
                    case 'Category':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $details['Ricardo'][$field]);
                        $csv .= '"' . $details['Ricardo'][$field] . '"';
                        $csv1 .= '"' . $details['Ricardo'][$field] . '"';
                        break;
                    case 'Increment':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, ($details['Ricardo']['Channel'] == '1') ? $details['Ricardo']['PriceIncrement'] : 0);
                        $csv .= '"' . number_format(($details['Ricardo']['Channel'] == '1' ? $details['Ricardo']['PriceIncrement'] : 0), 2, '.', '') . '"';
                        $csv1 .= '"' . number_format(($details['Ricardo']['Channel'] == '1' ? $details['Ricardo']['PriceIncrement'] : 0), 2, '.', '') . '"';
                        break;
                    case 'CategoryNr':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $details['Ricardo']['CategoryID']);
                        $csv .= '"' . $details['Ricardo']['CategoryID'] . '"';
                        $csv1 .= '"' . $details['Ricardo']['CategoryID'] . '"';
                        break;
                    case 'AvailabilityId':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $details['Ricardo'][$field]);
                        $csv .= '"' . $details['Ricardo'][$field] . '"';
                        $csv1 .= '"' . $details['Ricardo'][$field] . '"';
                        break;
                    case 'Descriptions[0].WarrantyDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $details['Ricardo']['Warranty']);
                        $csv .= '"' . $details['Ricardo']['Warranty'] . '"';
                        $csv1 .= '"' . $details['Ricardo']['Warranty'] . '"';
                        break;
                    case 'Descriptions[1].WarrantyDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, ((int) $details['GermanRicardoWarranty'] ?
                                            $details['Ricardo']['Warranty'] : $details['Ricardo']['WarrantyFr']));
                        $csv .= '"' . (((int) $details['GermanRicardoWarranty'] ?
                                $details['Ricardo']['Warranty'] : $details['Ricardo']['WarrantyFr'])) . '"';
                        $csv1 .= '"' . (((int) $details['GermanRicardoWarranty'] ?
                                $details['Ricardo']['Warranty'] : $details['Ricardo']['WarrantyFr'])) . '"';
                        break;
                    case 'Descriptions[0].ProductSubtitle':
                        if (!$details['RicardoSubtitleInactive']) {
                            if ($type == 'xls')
                                $sheet->writeString($n, $key, $details['Ricardo']['Subtitle']);
                            $csv .= '"' . $details['Ricardo']['Subtitle'] . '"';
                            $csv1 .= '"' . $details['Ricardo']['Subtitle'] . '"';
                        }
                        break;
                    case 'Descriptions[1].ProductSubtitle':
                        if (!$details['RicardoSubtitleInactive'])
                            if ($type == 'xls')
                                $sheet->writeString($n, $key, ((int) $details['GermanRicardoSubtitle'] ?
                                                $details['Ricardo']['Subtitle'] : $details['Ricardo']['SubtitleFr']));
                        $csv .= '"' . ((int) $details['GermanRicardoSubtitle'] ?
                                $details['Ricardo']['Subtitle'] : $details['Ricardo']['SubtitleFr']) . '"';
                        $csv1 .= '"' . ((int) $details['GermanRicardoSubtitle'] ?
                                $details['Ricardo']['Subtitle'] : $details['Ricardo']['SubtitleFr']) . '"';
                        break;
                    case 'PromoSubtitle':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, strlen($details['Ricardo']['Subtitle']) ? 1 : 0);
                        $csv .= '"' . (strlen($details['Ricardo']['Subtitle']) ? 1 : 0) . '"';
                        $csv1 .= '"' . (strlen($details['Ricardo']['Subtitle']) ? 1 : 0) . '"';
                        break;
                    case 'Descriptions[0].ProductTitle':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, substr($auction->name, 0, Config::get($db, $dbr, 'offer_limit_ricardo')));
                        $csv .= '"' . substr($auction->name, 0, Config::get($db, $dbr, 'offer_limit_ricardo')) . '"';
                        $csv1 .= '"' . substr($auction->name, 0, Config::get($db, $dbr, 'offer_limit_ricardo')) . '"';
                        break;
                    case 'Descriptions[1].ProductTitle':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, substr((strlen($auction->nameFr) ? $auction->nameFr : $auction->name), 0, Config::get($db, $dbr, 'offer_limit_ricardo')));
                        $csv .= '"' . substr((strlen($auction->nameFr) ? $auction->nameFr : $auction->name), 0, Config::get($db, $dbr, 'offer_limit_ricardo')) . '"';
                        $csv1 .= '"' . substr((strlen($auction->nameFr) ? $auction->nameFr : $auction->name), 0, Config::get($db, $dbr, 'offer_limit_ricardo')) . '"';
                        break;
                    case 'Descriptions[0].ProductDescription':
                        if (!$seller->get('ricardo_descr_header_inactive'))
                            $header = $seller->get('ricardo_descr_header');
                        if (!$seller->get('ricardo_descr_footer_inactive'))
                            $footer = $seller->get('ricardo_descr_footer');
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $header . ' ' . $auction->description . ' ' . $footer);
                        $csv .= '"' . str_replace('"', '""', $header . ' ' . $auction->description . ' ' . $footer) . '"';
                        $csv1 .= '"' . str_replace('"', '""', $header . ' ' . $auction->description . ' ' . $footer) . '"';
                        break;
                    case 'Descriptions[1].ProductDescription':
                        if (!$seller->get('ricardo_descr_header_inactive'))
                            $header = (int) $details['GermanRicardo'] ? $seller->get('ricardo_descr_header') : $seller->get('ricardo_descr_headerfr');
                        if (!$seller->get('ricardo_descr_footer_inactive'))
                            $footer = (int) $details['GermanRicardo'] ? $seller->get('ricardo_descr_footer') : $seller->get('ricardo_descr_footerfr');
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, ((int) $details['GermanRicardo'] ?
                                            $header . ' ' . $auction->description . ' ' . $footer : $header . ' ' . $auction->descriptionFr . ' ' . $footer));
                        $csv .= '"' . str_replace('"', '""', ((int) $details['GermanRicardo'] ?
                                        $header . ' ' . $auction->description . ' ' . $footer : $header . ' ' . $auction->descriptionFr . ' ' . $footer)) . '"';
                        $csv1 .= '"' . str_replace('"', '""', ((int) $details['GermanRicardo'] ?
                                        $header . ' ' . $auction->description . ' ' . $footer : $header . ' ' . $auction->descriptionFr . ' ' . $footer)) . '"';
                        break;
                    case 'Quantity':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 1);
                        $csv .= '"1"';
                        $csv1 .= '"1"';
                        break;
                    case 'IsUserTheme':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'ThemeID':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, -1);
                        $csv .= '"-1"';
                        $csv1 .= '"-1"';
                        break;
                    case 'WarrantyID':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'InternalReference':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $auction->RefNo);
                        $csv .= '"' . $auction->saved_id . '"';
                        $csv1 .= '"' . $auction->saved_id . '"';
                        break;
                    case 'DraftImages[0]':
                        if (isset($masterpics[0])) {
                            $masterpics[0] = explode('-', $masterpics[0]);
                            
                            $url = 'https://www.beliani.ch' . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $masterpics[0][0],
                                'type' => $masterpics[0][1],
                                'x'=>1800,
                                'addlogo' => 'logo'], $smarty);
                            if ($type == 'xls')
                                $sheet->writeString($n, $key, $url);
                            $csv .= '"' . $url . '"';
                            $csv1 .= '"' . $url . '"';
                        } else {
                            if ($type == 'xls')
                                $sheet->writeString($n, $key, $auction->gallery);
                            $csv .= '"' . $auction->gallery . '"';
                            $csv1 .= '"' . $auction->gallery . '"';
                        }
                        break;
                    case 'Img2':
                    case 'Img3':
                    case 'Img4':
                    case 'Img5':
                    case 'Img6':
                    case 'Img7':
                    case 'Img8':
                    case 'Img9':
                    case 'Img10':
                    case 'DraftImages[1]':
                    case 'DraftImages[2]':
                    case 'DraftImages[3]':
                    case 'DraftImages[4]':
                    case 'DraftImages[5]':
                    case 'DraftImages[6]':
                    case 'DraftImages[7]':
                    case 'DraftImages[8]':
                    case 'DraftImages[9]':
                        $i = (int) str_replace('DraftImages[', '', str_replace(']', '', $field));
                        if (isset($masterpics[$i])) {
                            $masterpics[$i] = explode('-', $masterpics[$i]);
                            $url = 'https://www.beliani.ch' . smarty_function_imageurl([
                                'src' => "sa",
                                'picid' => $masterpics[$i][0],
                                'x'=>1800,
                                'type' => $masterpics[$i][1]], $smarty);
                            if ($type == 'xls')
                                $sheet->writeString($n, $key, $url);
                            $csv .= '"' . $url . '"';
                            $csv1 .= '"' . $url . '"';
                        }
                        break;
                    case 'StartPrice':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, ($details['Ricardo']['Channel'] == '1') ? $details['Ricardo']['startprice'] : 0);
                        $csv .= '"' . number_format(($details['Ricardo']['Channel'] == '1' ? $details['Ricardo']['startprice'] : 0), 2, '.', '') . '"';
                        $csv1 .= '"' . number_format(($details['Ricardo']['Channel'] == '1' ? $details['Ricardo']['startprice'] : 0), 2, '.', '') . '"';
                        break;
                    case 'BuyNowPrice':
                    case 'BuyNowCost':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $details['Ricardo']['BuyItNowPrice']);
                        $csv .= '"' . number_format($details['Ricardo']['BuyItNowPrice'], 2, '.', '') . '"';
                        $csv1 .= '"' . number_format($details['Ricardo']['BuyItNowPrice'], 2, '.', '') . '"';
                        break;
                    case 'BuyNowPrice':
                        if ($details['Ricardo']['BuyItNowPrice'] > 0) {
                            if ($type == 'xls')
                                $sheet->writeNumber($n, $key, 1);
                            $csv .= '"1"';
                            $csv1 .= '"1"';
                        } else {
                            if ($type == 'xls')
                                $sheet->writeNumber($n, $key, 0);
                            $csv .= '"0"';
                            $csv1 .= '"0"';
                        }
                        break;
                    case 'PaymentCode':
                        if ($details['Ricardo']['BuyItNowPrice'] > 3000) {
                            $PaymentID = (int) $details['Ricardo']['PaymentConditionCC']['PaymentCondition1'];
                            $PaymentID = 1;
                        } else {
                            $PaymentID = (int) $details['Ricardo']['PaymentConditionCC']['PaymentCondition1'] + 2 * (int) $details['Ricardo']['PaymentConditionCC']['visa'];
                            $PaymentID = 1 + 2;
                        }
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $PaymentID);
                        $csv .= '"' . $PaymentID . '"';
                        $csv1 .= '"' . $PaymentID . '"';
                        break;
                    case 'PaymentValue':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $RicardoPaymentConditionsDe[$details['Ricardo']['PaymentCondition']]);
                        $csv .= '"' . $RicardoPaymentConditionsDe[$details['Ricardo']['PaymentCondition']] . '"';
                        $csv1 .= '"' . $RicardoPaymentConditionsDe[$details['Ricardo']['PaymentCondition']] . '"';
                        break;
                    case 'Descriptions[0].PaymentDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $details['Ricardo']['PaymentConditionDescription']);
                        $csv .= '"' . $details['Ricardo']['PaymentConditionDescription'] . '"';
                        $csv1 .= '"' . $details['Ricardo']['PaymentConditionDescription'] . '"';
                        break;
                    case 'Descriptions[1].PaymentDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, ((int) $details['GermanRicardoPaymentCondition'] ?
                                            $details['Ricardo']['PaymentConditionDescription'] : $details['Ricardo']['PaymentConditionDescriptionFr']));
                        $csv .= '"' . ((int) $details['GermanRicardoPaymentCondition'] ?
                                $details['Ricardo']['PaymentConditionDescription'] : $details['Ricardo']['PaymentConditionDescriptionFr']) . '"';
                        $csv1 .= '"' . ((int) $details['GermanRicardoPaymentCondition'] ?
                                $details['Ricardo']['PaymentConditionDescription'] : $details['Ricardo']['PaymentConditionDescriptionFr']) . '"';
                        break;
                    case 'PaymentFlags':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 8192);
                        $csv .= '"8192"';
                        $csv1 .= '"8192"';
                        break;
                    case 'PaymentDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $details['Ricardo']['PaymentConditionCC']['PaymentCondition1']);
                        $csv .= '"' . $details['Ricardo']['PaymentConditionCC']['PaymentCondition1'] . '"';
                        $csv1 .= '"' . $details['Ricardo']['PaymentConditionCC']['PaymentCondition1'] . '"';
                        break;
                    case 'PayByCard':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, (int) $details['Ricardo']['PaymentConditionCC']['visa'] ? 'PayU' : '');
                        $csv .= '"' . ((int) $details['Ricardo']['PaymentConditionCC']['visa'] ? 'PayU' : '') . '"';
                        $csv1 .= '"' . ((int) $details['Ricardo']['PaymentConditionCC']['visa'] ? 'PayU' : '') . '"';
                        break;
                    case 'AvailabilityValue': // !!!!
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $RicardoAvailabilityValue[$details['Ricardo']['AvailabilityID']]);
                        $csv .= '"' . $RicardoAvailabilityValue[$details['Ricardo']['AvailabilityID']] . '"';
                        $csv1 .= '"' . $RicardoAvailabilityValue[$details['Ricardo']['AvailabilityID']] . '"';
                        break;
                    case 'Shipping':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $details['Ricardo']['ShippingCondition']);
                        $csv .= '"' . $details['Ricardo']['ShippingCondition'] . '"';
                        $csv1 .= '"' . $details['Ricardo']['ShippingCondition'] . '"';
                        break;
                    case 'DeliveryValue':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $RicardoShippingConditionsDe[$details['Ricardo']['ShippingCondition']]);
                        $csv .= '"' . $RicardoShippingConditionsDe[$details['Ricardo']['ShippingCondition']] . '"';
                        $csv1 .= '"' . $RicardoShippingConditionsDe[$details['Ricardo']['ShippingCondition']] . '"';
                        break;
                    case 'Descriptions[1].ShippingDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $details['Ricardo']['ShippingConditionDescription']);
                        $csv .= '"' . str_replace('"', '""', $details['Ricardo']['ShippingConditionDescription']) . '"';
                        $csv1 .= '"' . str_replace('"', '""', $details['Ricardo']['ShippingConditionDescription']) . '"';
                        break;
                    case 'Descriptions[1].ShippingDescription':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, ((int) $details['GermanRicardoShippingCondition'] ?
                                            $details['Ricardo']['ShippingConditionDescription'] : $details['Ricardo']['ShippingConditionDescriptionFr']));
                        $csv .= '"' . str_replace('"', '""', ((int) $details['GermanRicardoShippingCondition'] ?
                                        $details['Ricardo']['ShippingConditionDescription'] : $details['Ricardo']['ShippingConditionDescriptionFr'])) . '"';
                        $csv1 .= '"' . str_replace('"', '""', ((int) $details['GermanRicardoShippingCondition'] ?
                                        $details['Ricardo']['ShippingConditionDescription'] : $details['Ricardo']['ShippingConditionDescriptionFr'])) . '"';
                        break;
                    case 'ShippingCost':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $shipping_cost_seller);
                        $csv .= '' . number_format($shipping_cost_seller, 2, '.', '') . '';
                        $csv1 .= '' . number_format($shipping_cost_seller, 2, '.', '') . '';
                        break;
                    case 'PromoBold':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $details['Ricardo']['bold']);
                        $csv .= '"' . (int) $details['Ricardo']['bold'] . '"';
                        $csv1 .= '"' . (int) $details['Ricardo']['bold'] . '"';
                        break;
                    case 'FeaturedHomePage':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $details['Ricardo']['super']);
                        $csv .= '"' . (int) $details['Ricardo']['super'] . '"';
                        $csv1 .= '"' . (int) $details['Ricardo']['super'] . '"';
                        break;
                    case 'FeaturedCategory':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $auction->featured);
                        $csv .= '"' . (int) $auction->featured . '"';
                        $csv1 .= '"' . (int) $auction->featured . '"';
                        break;
                    case 'PromoShowcase':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'PromoGallery':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, (strlen($pics[$auction->gallery . $auction->auction_number]) ? 1 : 0));
                        $csv .= '"' . (strlen($pics[$auction->gallery . $auction->auction_number]) ? 1 : 0) . '"';
                        $csv1 .= '"' . (strlen($pics[$auction->gallery . $auction->auction_number]) ? 1 : 0) . '"';
                        break;
                    case 'Highlight':
                        $details['Ricardo']['highlight'] = $details['Ricardo']['highlight'] ? 'True' : 'False';
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $details['Ricardo']['highlight']);
                        $csv .= '"' . (int) $details['Ricardo']['highlight'] . '"';
                        $csv1 .= '"' . (int) $details['Ricardo']['highlight'] . '"';
                        break;
                    case 'StartDate':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, $auction->start_time);
                        $csv .= '"' . $auction->start_time . '"';
                        $csv1 .= '"' . $auction->start_time . '"';
                        break;
                    case 'Duration':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, $auction->duration * 24 * 60);
                        $csv .= '"' . ($auction->duration * 24 * 60) . '"';
                        $csv1 .= '"' . ($auction->duration * 24 * 60) . '"';
                        break;
                    case 'ReactTimes':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'Reminder':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'ReminderMins':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'IsMetallicColor':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'ResellCount':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 0);
                        $csv .= '"0"';
                        $csv1 .= '"0"';
                        break;
                    case 'HasFixedEndDate':
                    case 'StartImmediately':
                    case 'IsCarsBikesAccessoriesArticle':
                    case 'Descriptions[0].IsEmpty':
                    case 'Descriptions[1].IsEmpty':
                    case 'IsCumulativeShipping':
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, 'False');
                        $csv .= '"False"';
                        $csv1 .= '"False"';
                        break;
                    case 'Descriptions[0].LanguageNr':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 2);
                        $csv .= '"2"';
                        $csv1 .= '"2"';
                        break;
                    case 'Descriptions[1].LanguageNr':
                        if ($type == 'xls')
                            $sheet->writeNumber($n, $key, 3);
                        $csv .= '"3"';
                        $csv1 .= '"3"';
                        break;
                    default:
                        if ($type == 'xls')
                            $sheet->writeString($n, $key, '');
                        $csv .= '';
                        $csv1 .= '';
                        break;
                } // switch
                $csv .= ";";
                $csv1 .= ";";
            } // foreach field

            if ($debug) {
                $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
                echo 'after fields: ' . xdebug_time_index() . " mem=$mem<br>\n";
                file_put_contents('findStarted', 'before fields: ' . xdebug_time_index() . " mem=$mem\n$q\n", FILE_APPEND);
            }


            $csv .= "\n";
            $csvs[] = $csv1;
            $csv1 = '';
            if ($debug) {
                $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
                echo "ricardo: {$auction->saved_id} " . xdebug_time_index() . " mem=$mem<br>\n";
                file_put_contents('findStarted', "ricardo: {$auction->saved_id} " . xdebug_time_index() . " mem=$mem\n$q\n", FILE_APPEND);
            }
        } // foreach auction

        if ($type == 'xls') {
            $workbook->close();
            $xls = file_get_contents($fn);
            unlink($fn);
        }

        if ($type == 'xls') {
            $ret = new stdClass;
            $ret->pics = $pics;
            $ret->xls = $xls;
            $ret->count = count($results);
        } 
        else {
            $ret->xls = utf8_decode($csv);
            $ret->csvs = array_map('utf8_decode', $csvs);
            $ret->count = count($results);
        }

        if ($debug) 
            file_put_contents('findStarted', 'FINISHED 2  ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        return $ret;
    }

	function psPayments($status="''") {
		$r = $this->_dbr->getAll("select psp.*
				, NOW()/*DATE_ADD(psp.payment_date, INTERVAL 9 HOUR)*/ payment_date_MEZ  
				, DATE_ADD(psp.authorisation_date, INTERVAL 9 HOUR) authorisation_date_MEZ  
				, IFNULL(u.name, psp.booked_by) booked_by_name
				, u.username booked_by_username
				, ad.description avs_descr
			from payment_saferpay psp
			join auction au on psp.auction_number=au.auction_number 
				and psp.txnid=au.txnid 
			join seller_information si on si.username=au.username
            LEFT JOIN users u ON psp.booked_by=u.username 
            LEFT JOIN avs_description ad ON ad.code=psp.avs_code 
			where 1
			and (au.payment_method like 'cc_%' or au.payment_method like 'ideal_%' or au.payment_method like 'pofi_%' or au.payment_method like 'master_%')
			and psp.auction_number=".$this->data->auction_number."
			and psp.txnid=".$this->data->txnid."
			#and psp.payment_status='Completed'
			");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	function billPayments($status="''") {
		$r = $this->_dbr->getAll("select psp.*
				, NOW()/*DATE_ADD(psp.payment_date, INTERVAL 9 HOUR)*/ payment_date_MEZ  
				, DATE_ADD(psp.authorisation_date, INTERVAL 9 HOUR) authorisation_date_MEZ  
				, IFNULL(u.name, psp.booked_by) booked_by_name
				, ad.description avs_descr
			from payment_saferpay psp
			join auction au on psp.auction_number=au.auction_number 
				and psp.txnid=au.txnid 
			join seller_information si on si.username=au.username
            LEFT JOIN users u ON psp.booked_by=u.username 
            LEFT JOIN avs_description ad ON ad.code=psp.avs_code 
			where 1
			and au.payment_method like 'bill_%'
			and psp.auction_number=".$this->data->auction_number."
			and psp.txnid=".$this->data->txnid."
			#and psp.payment_status='Completed'
			");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	function gcPayments() {
		$r = $this->_dbr->getAll("select psp.*
				, IFNULL(u.name, psp.booked_by) booked_by_name
			from payment_google psp
			join auction au on psp.auction_number=au.auction_number 
				and psp.txnid=au.txnid 
			join seller_information si on si.username=au.username
            LEFT JOIN users u ON psp.booked_by=u.username 
			where 1
			and psp.auction_number=".$this->data->auction_number."
			and psp.txnid=".$this->data->txnid."
			");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	function bsPayments() {
		$r = $this->_dbr->getAll("select psp.*
				, IFNULL(u.name, psp.booked_by) booked_by_name
			from payment_bean psp
			join auction au on psp.auction_number=au.auction_number 
				and psp.txnid=au.txnid 
			join seller_information si on si.username=au.username
            LEFT JOIN users u ON psp.booked_by=u.username 
			where 1
			and psp.auction_number=".$this->data->auction_number."
			and psp.txnid=".$this->data->txnid."
			");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	function p24Payments() {
		$r = $this->_dbr->getAll("select psp.*
				, IFNULL(u.name, psp.booked_by) booked_by_name
			from payment_p24 psp
			join auction au on psp.auction_number=au.auction_number 
				and psp.txnid=au.txnid 
			join seller_information si on si.username=au.username
            LEFT JOIN users u ON psp.booked_by=u.username 
			where 1
			and psp.auction_number=".$this->data->auction_number."
			and psp.txnid=".$this->data->txnid."
			");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	static function findbyPSPNotBooked($db, $dbr, $status="'VERIFIED'", $days=0, $kind='cc') {
	  global $seller_filter_str;
	  	$q = "select * from (
			select auction.*, sum(psp.amount) sum_amount, min(DATE_ADD(psp.authorisation_date, INTERVAL 0 HOUR)) authorisation_date_MEZ
			, i.total_price + i.total_shipping + i.total_cod + i.total_cc_fee - i.open_amount as paid_amount
			, i.open_amount
			, (select max(el.date) from email_log el 
				where el.template = 'order_confirmation'
							AND el.auction_number = auction.auction_number
							AND el.txnid = auction.txnid
			) date_confirmation
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') 
						from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			from payment_saferpay psp
						join auction on psp.auction_number=auction.auction_number 
							and psp.txnid=auction.txnid 
						LEFT JOIN offer ON offer.offer_id = auction.offer_id 
						left join invoice i on i.invoice_number=auction.invoice_number
						join seller_information si on si.username=auction.username
						where 1
						and auction.payment_method like '{$kind}_%'
						$seller_filter_str
						#and psp.payment_status='Completed'
						and IFNULL(psp.booked,0)=0
						group by auction.auction_number
			) t where 1
			 and sum_amount>paid_amount 
			and	DATE_ADD(authorisation_date_MEZ, INTERVAL $days DAY) <= NOW()
			and main_auction_number=0
			";
//		echo $q;
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

    static function findCCPaidUnshipped_date($db, $dbr, $date, $kind = 'cc')
    {
        global $seller_filter_str;

        $q = "select auction.*
            , SUM(p.amount) AS paid_amount
            , IFNULL((select SUM(orders.quantity*
                        IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=article.article_id
                    order by import_date desc limit 1
                    ),article.total_item_cost)
                ) from orders 
                left join warehouse w on orders.send_warehouse_id=w.warehouse_id
                left join wwo_article on wwo_article.id=orders.wwo_order_id
                left join article on orders.article_id=article.article_id and article.admin_id=0
                where sent=0 
                and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
                ),0)
            +IFNULL((select SUM(orders.quantity*
                    IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=article.article_id
                    order by import_date desc limit 1
                    ),article.total_item_cost)
                ) from orders 
                left join warehouse w on orders.send_warehouse_id=w.warehouse_id
                left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
                left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
                left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
                    and t_a.language=mau.lang
                left join wwo_article on wwo_article.id=orders.wwo_order_id
                left join article on orders.article_id=article.article_id and article.admin_id=0
                where sent=0 
                and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
                ),0) total_item_cost
            , IFNULL((select SUM(orders.quantity*
                    IFNULL((select article_import.purchase_price from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=article.article_id
                    order by import_date desc limit 1
                    ),article.purchase_price)
                ) from orders 
                left join warehouse w on orders.send_warehouse_id=w.warehouse_id
                left join wwo_article on wwo_article.id=orders.wwo_order_id
                left join article on orders.article_id=article.article_id and article.admin_id=0
                where sent=0 
                and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
                ),0)
            +IFNULL((select SUM(orders.quantity*
                    IFNULL((select article_import.purchase_price from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=article.article_id
                    order by import_date desc limit 1
                    ),article.purchase_price)
                ) from orders 
                left join warehouse w on orders.send_warehouse_id=w.warehouse_id
                left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
                left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
                left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
                    and t_a.language=mau.lang
                left join wwo_article on wwo_article.id=orders.wwo_order_id
                left join article on orders.article_id=article.article_id and article.admin_id=0
                where sent=0 
                and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
                ),0) purchase_price
         from (
                select auction.*
                , sum(psp.amount) psp_paid_amount
                , i.total_price + i.total_shipping + i.total_cod + i.total_cc_fee as invoice_total
                , i.open_amount
                , (select max(el.date) from email_log el 
                    where el.template = 'order_confirmation'
                                AND el.auction_number = auction.auction_number
                                AND el.txnid = auction.txnid
                ) date_confirmation
                , IFNULL(offer.name,
                    (select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
                        join auction a1 on a1.offer_id=o1.offer_id
                        where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
                from payment_saferpay psp
                join auction on psp.auction_number=auction.auction_number 
                    and psp.txnid=auction.txnid 
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
                left join invoice i on i.invoice_number=auction.invoice_number
                join seller_information si on si.username=auction.username
                where 1
                and auction.payment_method like '{$kind}_%' 
                $seller_filter_str
                #and psp.payment_status='Completed'
                and	DATE(authorisation_date) <= '$date'
                and 
                (
                    auction.delivery_date_real > '$date'
                    OR auction.delivery_date_real = '0000-00-00 00:00:00'
                )
                #and IFNULL(psp.booked,0)=0
                group by auction.auction_number
            ) auction 

                JOIN payment p ON p.auction_number = auction.auction_number 
                    AND p.txnid = auction.txnid 
            where 1
             and psp_paid_amount>=invoice_total
            and main_auction_number=0
            group by auction.auction_number
            ";
//		echo "<pre>$q</pre>";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r))
            aprint_r($r);
        return $r;
    }

    static function findAuftragPaidUnshipped_date($db, $dbr, $date, $username_filter = "''")
    {
        global $seller_filter_str;

        if ($username_filter != "''")
        {
            $username_filter = " and auction.username in ($username_filter) ";
        }
        else
        {
            $username_filter = '';
        }

        $q = "select auction.*
                        , SUM(p.amount) AS paid_amount
            , IFNULL((select SUM(orders.quantity*
                        IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=article.article_id
                    order by import_date desc limit 1
                    ),article.total_item_cost)
                ) from orders 
                left join warehouse w on orders.send_warehouse_id=w.warehouse_id
                left join wwo_article on wwo_article.id=orders.wwo_order_id
                left join article on orders.article_id=article.article_id and article.admin_id=0
                where 1#sent=0 
                and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
                ),0)
            +IFNULL((select SUM(orders.quantity*
                    IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=article.article_id
                    order by import_date desc limit 1
                    ),article.total_item_cost)
                ) from orders 
                left join warehouse w on orders.send_warehouse_id=w.warehouse_id
                left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
                left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
                left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
                    and t_a.language=mau.lang
                left join wwo_article on wwo_article.id=orders.wwo_order_id
                left join article on orders.article_id=article.article_id and article.admin_id=0
                where 1#sent=0 
                and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
                ),0) total_item_cost
            , IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				LEFT JOIN total_log ON orders.id=total_log.tableid 
                    AND total_log.table_name = 'orders'
                    AND total_log.field_name = 'sent'
				where 
                    1#sent=0 
                    AND (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where 1#sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
         from (
                select auction.*
                , i.total_price + i.total_shipping + i.total_cod + i.total_cc_fee as invoice_total
                , i.open_amount
                , (select max(el.date) from email_log el 
                    where el.template = 'order_confirmation'
                                AND el.auction_number = auction.auction_number
                                AND el.txnid = auction.txnid
                ) date_confirmation
                , IFNULL(offer.name,
                    (select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
                        join auction a1 on a1.offer_id=o1.offer_id
                        where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
                from auction 
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
                left join invoice i on i.invoice_number=auction.invoice_number
                join seller_information si on si.username=auction.username
                where 1
                $username_filter
                $seller_filter_str
                and auction.delivery_date_real > '$date'
                group by auction.auction_number

                UNION

                select auction.*
                , i.total_price + i.total_shipping + i.total_cod + i.total_cc_fee as invoice_total
                , i.open_amount
                , (select max(el.date) from email_log el 
                    where el.template = 'order_confirmation'
                                AND el.auction_number = auction.auction_number
                                AND el.txnid = auction.txnid
                ) date_confirmation
                , IFNULL(offer.name,
                    (select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
                        join auction a1 on a1.offer_id=o1.offer_id
                        where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
                from auction 
                LEFT JOIN offer ON offer.offer_id = auction.offer_id 
                left join invoice i on i.invoice_number=auction.invoice_number
                join seller_information si on si.username=auction.username
                where 1
                $username_filter
                $seller_filter_str
                and auction.delivery_date_real = '0000-00-00 00:00:00'
                group by auction.auction_number
            ) auction 

                JOIN payment p ON p.auction_number = auction.auction_number 
                    AND p.txnid = auction.txnid 
            where 1

            and main_auction_number=0
            group by auction.auction_number
            HAVING paid_amount>=invoice_total
            ";
//        echo "<pre>$q</pre>";
//        exit;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r))
            aprint_r($r);
        return $r;
    }

    function sfPayments() {
		$r = $this->_dbr->getAll("select ppp.*
				, booked_payment_id booked
				, IF(IFNULL(booked_payment_id,0),tl.updated,NULL)  booked_date
				, IFNULL(u.name, tl.username) booked_by_name
			from payment_sofort ppp
			left join total_log tl on table_name='payment_sofort' and field_name='booked_payment_id' and TableID=ppp.id
            LEFT JOIN users u ON tl.username=u.system_username 
			where ppp.auction_number=".$this->data->auction_number."
			and ppp.txnid=".$this->data->txnid."
			and ppp.checked
			order by tl.updated desc limit 1
			");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

    function klarnaPayments() {
        $r = $this->_dbr->getAll("SELECT ppp.*  
			FROM payment_klarna ppp
            LEFT JOIN users u ON ppp.booked_by=u.username
			WHERE ppp.auction_number=" . $this->data->auction_number . "
			AND ppp.txnid=" . $this->data->txnid . "
			AND ppp.klarna_checkout_complete = 1
			ORDER BY ppp.payment_date DESC");
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }

    function payeverPayments() {
        $r = $this->_dbr->getAll("SELECT ppp.*  
			FROM payment_payever ppp
            LEFT JOIN users u ON ppp.booked_by=u.username
			WHERE ppp.auction_number=" . $this->data->auction_number . "
			AND ppp.txnid=" . $this->data->txnid . "
			ORDER BY ppp.payment_date DESC");
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }

    function cmcicPayments() {
        $r = $this->_dbr->getAll("SELECT ppp.*  
			FROM payment_cmcic ppp
            LEFT JOIN users u ON ppp.booked_by=u.username
			WHERE ppp.auction_number=" . $this->data->auction_number . "
			AND ppp.txnid=" . $this->data->txnid . "
			AND ppp.cmcic_result = 1
			ORDER BY ppp.payment_date DESC");
        if (PEAR::isError($r)) aprint_r($r);
        return $r;
    }

	function ppPayments($status="'VERIFIED'") {
		$r = $this->_dbr->getAll("select ppp.*
				, DATE_ADD(ppp.payment_date, INTERVAL 9 HOUR) payment_date_MEZ  
				, IFNULL(u.name, ppp.booked_by) booked_by_name
			from payment_paypal ppp
			join auction au on ppp.item_number=au.auction_number 
				and (ppp.auction_buyer_id=au.username_buyer 
				or ppp.auction_txnid=au.txnid)
#				and DATE_ADD(ppp.auction_closing_date, INTERVAL 9 HOUR)=au.end_time
			join seller_information si on si.username=au.username
            LEFT JOIN users u ON ppp.booked_by=u.username 
			where ppp.item_number=".$this->data->auction_number."
			and ppp.receiver_email=si.paypal_email
			and ppp.payment_status in ('Completed','Pending')
			and ppp.verification_status in ($status)");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	static function findbyPPPNotBooked($db, $dbr, $status="'VERIFIED'", $solved='') {
	  global $seller_filter_str;
	  	$q = "select auction.*
			, IFNULL((select SUM(orders.quantity*
						IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.total_item_cost from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.total_item_cost)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) total_item_cost
			, IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and (orders.auction_number=auction.auction_number and orders.txnid=auction.txnid)
				),0)
			+IFNULL((select SUM(orders.quantity*
					IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=w.country_code
					and article_import.article_id=article.article_id
					order by import_date desc limit 1
					),article.purchase_price)
				) from orders 
				left join warehouse w on orders.send_warehouse_id=w.warehouse_id
				left join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				left join translation t_a on t_a.id=orders.article_id and t_a.table_name='article' and t_a.field_name='name'
					and t_a.language=mau.lang
				left join wwo_article on wwo_article.id=orders.wwo_order_id
				left join article on orders.article_id=article.article_id and article.admin_id=0
				where sent=0 
				and ((mau.auction_number=auction.auction_number and mau.txnid=auction.txnid))
				),0) purchase_price
		 from (
			select auction.*, sum(ppp.mc_gross) sum_mc_gross
			, i.total_price + i.total_shipping + i.total_cod - i.open_amount as paid_amount
			, i.open_amount
			, (select max(el.date) from email_log el 
				where el.template = 'order_confirmation'
							AND el.auction_number = auction.auction_number
							AND el.txnid = auction.txnid
			) date_confirmation
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, CONCAT('<input type=\"button\" id=\"solve',ppp.id,'\" value=\"',IF(ppp.solved,'Mark as unsolved','Solve'),'\" onClick=\"solve_paypal(this, ',ppp.id,')\"/>')
				as solve_action
			from payment_paypal ppp
						join auction on ppp.item_number=auction.auction_number 
							and ppp.auction_buyer_id=auction.username_buyer 
#							and DATE_ADD(ppp.auction_closing_date, INTERVAL 9 HOUR)=auction.end_time
						LEFT JOIN offer ON offer.offer_id = auction.offer_id 
						left join invoice i on i.invoice_number=auction.invoice_number
						join seller_information si on si.username=auction.username
							and ppp.receiver_email=si.paypal_email
						where 1
						$seller_filter_str
						and ppp.verification_status in ($status)
						and ppp.payment_status in ('Completed','Pending')
						and IFNULL(ppp.booked,0)=0
						".(strlen($solved)?" and ppp.solved=$solved ":'')."
						group by auction.auction_number
			) auction where 1
			 and sum_mc_gross>0
			";
//		echo $q;
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}
	
	static function listRatings($db, $dbr, $auction_number, $txnid, $type) {
		$q = "select 
				af.id as rating_".$type."_id, 
				af.code as rating_".$type.", 
				af.`datetime` as rating_".$type."_date,
				af.`text` as rating_text_".$type.",
				if(af.txnid=3,
					IF(af.code in (4,5),'green',
						IF(af.code=3,'black',
							IF(af.code in (1,2),
								IF(max(rating.resolved),'#FF7777','red'),
							'')
						)
					),  
					IF(af.code=1,'green',
						IF(af.code=2,'black',
							IF(af.code=3,
								IF(max(rating.resolved),'#FF7777','red'),
							'')
						)
					)
				) rating_".$type."_color
				, af.iid
				, af.hidden
				from auction_feedback af
				left join rating on rating.auction_number=$auction_number and rating.txnid=$txnid 
				where af.auction_number=$auction_number and af.txnid=$txnid and af.type='$type'
		 group by af.id,af.text,af.code,af.type		
		 order by  af.`datetime`";
//		echo $q;	
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) aprint_r($r);
		return $r;	
	}

	static function findSecondChanced($db, $dbr, $criteria,$order='end_time',$dir='desc'){
		$where='';
//		print_r($criteria);
		foreach ($criteria as $key=>$value) {
			switch ($key) {	
				case 'saved_id':
					$where .= " and auction.saved_id = $value";
					break;
				case 'base_auction_number':
					$where .= " and  SUBSTRING_INDEX( SUBSTRING_INDEX( auction_comment.comment, ' Second chance auction of ', -1 ) , '(', 1 ) like '$value%'";
					break;
				case 'seller':
					$where .= " and auction.username = '$value'";
					break;
				case 'buyer':
					$where .= " and auction_comment.`comment` like  '%($value)'";
					break;
			}
		}	
		$q = "select 
			auction.username_buyer
			,auction.username seller
			,auction.start_time
			,auction.end_time
			, IF(POSITION('(' in auction_comment.comment)>0
				,SUBSTRING_INDEX( SUBSTRING_INDEX( auction_comment.comment, '(', -1 ) , ')', 1 )
				,'') expected_buyer
			, auction_comment.*
			, SUBSTRING_INDEX( SUBSTRING_INDEX( auction_comment.comment, ' Second chance auction of ', -1 ) , '(', 1 ) base_auction_number
			, auction.winning_bid, auction.old_price, seller_information.seller_name
			, offer_name.name as alias, au_server.value as server
			from auction_comment 
			left join auction on auction.auction_number=auction_comment.auction_number and auction.txnid=auction_comment.txnid
				left join auction_par_varchar au_server on auction.auction_number=au_server.auction_number 
									and auction.txnid=au_server.txnid and au_server.key='server'
			left join offer_name on auction.name_id=offer_name.id
			left join seller_information on auction.username=seller_information.username
			where auction_comment.`comment` like ' Second chance auction of %'
			$where
			order by $order $dir
		";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getPerson(){
		$q = "select `key`, `value` from auction_par_varchar where auction_number=".$this->get('auction_number')
		." and txnid=".$this->get('txnid');
		$r = $this->_dbr->getAssoc($q);
        if (PEAR::isError($r)) aprint_r($r);
		$r['country_invoice_code']=CountryToCountryCode($r['country_invoice']);
		$r['country_shipping_code']=CountryToCountryCode($r['country_shipping']);
		return $r;
	}

	static function export_to_amazon($db, $dbr, $datefrom, $dateto, $sellername){
			require_once 'Spreadsheet/Excel/Writer.php'; 
			$fn = 'tmppic/temp'.rand(100000, 999999).'.xls';
			$workbook = new Spreadsheet_Excel_Writer($fn);
    		$workbook->setTempDir('tmppic/');
			$workbook->setVersion(8);
			$sheet = $workbook->addWorksheet();
			$sheet->setInputEncoding('UTF-8');
			$text = '';
			$n=0;
            $results = \Auction::findStarted($db, $dbr, $datefrom, $dateto, $sellername, 'Amazon');
//			print_r($results); die();
			foreach ($results as $auction) {
				$n++;
//				print_r($auction); 
				$details = /*unserialize*/($auction->details);
				$category_id = $details['amazon']['Category'];
				$category = $dbr->getRow("select * from amazon_category where id=$category_id");
				$category7 = $dbr->getRow("select * from amazon_category where id=7");
				$not_spec_cnt = $dbr->getOne("select count(*) from amazon_pars where category_id=$category_id and not spec");
				$not_spec_cnt += count($fields)-3;
				$spec_cnt = $dbr->getOne("select count(*) from amazon_pars where category_id=$category_id and spec");
				$fields = array(
					$category7->sku_name, //SKU
					$category7->id_name, //StandardProductID
					$category7->title_name, //ProductName
					$category7->rbn_name."1", //RecommendedBrowseNode1
					$category7->rbn_name."2", // RecommendedBrowseNode2
					$category7->launchdate_name,
					$category7->descr_name, // Description
					$category7->SaleStartDate_name, // SaleStartDate
					$category7->SaleEndDate_name, // SaleEndDate
					$category7->Sale_price_name, // SaleEndDate
				);
				if ($text=='') {
					$text .= "TemplateType=".$category->template
						."	".$category->version."	This row for Amazon.com use only.  Do not modify or delete."
						.str_repeat("	",$not_spec_cnt).$category->spectitle.str_repeat("	",$spec_cnt-1)."\r\n";
					$pars = $dbr->getAssoc("select name f1, name f2 from amazon_pars where category_id=$category_id order by spec, ordering");
					$field_pars = array_merge($fields, $pars);
					foreach ($field_pars as $key=>$field) {
						$text .= $field."	";
					}
					$text .= "\r\n";
				}	
//				echo $text; print_r($field_pars); die();
				$i=0;
				foreach ($field_pars as $k=>$field) {
					$fldName = 'amazon'.
						str_replace('Url','URL',
							str_replace(' ','',
								str_replace(' ','',
									ucwords(
										str_replace('-',' ',$field )))));
					switch ($field) { 
				        case $category->sku_name:
					       #$sheet->writeNumber($n,$key, $details['offer_id']);
						   $value = $details['offer_id'];
			            break;
				        case $category->id_name:
					       #$sheet->writeString($n,$key, 
						   #		$dbr->getOne("select ean_code from offer where offer_id=".$details['offer_id']));
						   $value = $dbr->getOne("select ean_code from offer where offer_id=".$details['offer_id']);
			            break;
				        case $category->title_name:
					       #$sheet->writeString($n,$key, 
						   #		$dbr->getOne("select `name` from offer_name where id=".$details['amazon']['Title']));
						   $value = $dbr->getOne("select `name` from offer_name where id=".$details['amazonTitle']);
						   $value = substr($value,0,Config::get($db, $dbr, 'offer_limit_amazon'));
			            break;
				        case $category->descr_name:
					       #$sheet->writeString($n,$key, $auction->description);
						   $value = str_replace('	','',str_replace("\n",'',str_replace("\r",'',nl2br($auction->description))));
			            break;
				        case $category->SaleStartDate_name:
					       #$sheet->writeString($n,$key, '2009-01-01');
						   $value = '2009-01-01';
			            break;
					    case $category->SaleEndDate_name:
					       #$sheet->writeString($n,$key, '2015-12-31');
						   $value = '2015-12-31';
			            break;
					    case $category->Sale_price_name:
					       #$sheet->writeString($n,$key, '2015-12-31');
						   $value = $details['amazon']['SalesPrice'];
			            break;
						case $category->launchdate_name:
					       #$sheet->writeString($n,$key, $auction->start_time);
						   $value = str_replace(' ', 'T', $auction->start_time);
						   $value = substr($auction->start_time, 0, strpos($auction->start_time,' '));
			            break;
						default:
					       #$sheet->writeString($n,$key, $details[$fldName]);
						   $value = $details['amazon'][$field];
			            break;
					} // switch
					$text .= $value."	";
//					echo $fldName.'<br>';
					$i++;
				} // foreach field	
//				print_r($details); die();
				$text .= "\r\n";
			};	// foreach auction
			$res = $workbook->close();     
//			var_dump($res);   
			$csv = file_get_contents($fn);
		    $ret = new stdClass;
		    $ret->pics = $pics;
			$ret->xls = $csv;
			$ret->fn = $fn;
			$ret->text = $text;
			$ret->count = count($results);
			unlink($fn);
			return $ret;
	}

    static function findStatus($db, $dbr, $statuses, $username, $from=0, $to=9999999, $sort, $source_seller=0)
    {
	  global $seller_filter_str;
		if (strlen($username)) $seller_filter_str = " and auction.username = '$username' ";
        if ($source_seller) $seller_filter_str = " and auction.source_seller_id = $source_seller ";
		if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if (!count($statuses)) return;
		$qarray = array();
		foreach($statuses as $status)
			switch ($status) {
				case 'ticket_opened':
					$qarray[] = "select rma.rma_id, rma.auction_number, rma.txnid, '$status' status, auction.deleted
							, DATE(rma.create_date) end_time
							, rma.responsible_uname
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
							, DATEDIFF(NOW(),(select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1)) last_comment_date_due
							, TO_DAYS(NOW())-TO_DAYS(rma.create_date) days_due
						from rma
						join auction on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
						where IFNULL(rma.close_date, '0000-00-00')='0000-00-00'
						$seller_filter_str
						and not auction.deleted";
				break;
				case 'uncompleted':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted, auction.winning_bid
							, DATE(auction.end_time) end_time
							, TO_DAYS(NOW())-TO_DAYS(auction.end_time) days_due
							, auction.responsible_uname
							, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
							, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
						from auction 
						where (payment_method = '0' or payment_method='') AND end_time <> '0000-00-00 00:00:00' and auction.main_auction_number=0
						$seller_filter_str
						AND DATE_ADD(end_time, INTERVAL 0 DAY) <= NOW() 
						AND auction.deleted = 0
						AND process_stage <> 2";
				break;
				case 'unpaid':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted
							, DATE(auction.end_time) end_time
							, TO_DAYS(NOW())-TO_DAYS(auction.end_time) days_due
							, auction.responsible_uname
							, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
							, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
						from auction 
						left join invoice on invoice.invoice_number = auction.invoice_number
						where auction.paid = 0 and invoice.open_amount>0
						$seller_filter_str
						and not auction.deleted
						and main_auction_number=0
						AND IFNULL(auction.payment_method,'')<>''";
				break;
				case 'paidNOTassigned':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted
							, (select DATE(tl.updated) from total_log tl 
								where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1) end_time
							, TO_DAYS(NOW())
								-TO_DAYS((select max(payment_date) from payment 
								where auction_number=auction.auction_number and txnid=auction.txnid)) days_due
							, DATE(fget_delivery_date_real(auction.auction_number, auction.txnid)) delivery_date_real
							, auction.delivery_date_customer
							, auction.shipping_username responsible_uname
							, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
							, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
							, concat(
								IFNULL(au_firstname_shipping.value,''), ', ',
								IFNULL(au_name_shipping.value,''), ', ',
								IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,'')) customer
							, (select GROUP_CONCAT(number) from tracking_numbers where tracking_numbers.auction_number = auction.auction_number
									and tracking_numbers.txnid = auction.txnid) tns
						from auction 
						left join auction_par_varchar au_name_shipping on au_name_shipping.auction_number = auction.auction_number
							and au_name_shipping.txnid = auction.txnid and au_name_shipping.key='name_shipping'
						left join auction_par_varchar au_firstname_shipping on au_firstname_shipping.auction_number = auction.auction_number
							and au_firstname_shipping.txnid = auction.txnid and au_firstname_shipping.key='firstname_shipping'
						left join auction_par_varchar au_city_shipping on au_city_shipping.auction_number = auction.auction_number
							and au_city_shipping.txnid = auction.txnid and au_city_shipping.key='city_shipping'
						left join auction_par_varchar au_zip_shipping on au_zip_shipping.auction_number = auction.auction_number
							and au_zip_shipping.txnid = auction.txnid and au_zip_shipping.key='zip_shipping'
						WHERE  IFNULL(auction.confirmation_date, '0000-00-00') <= NOW() 
						$seller_filter_str
						AND fget_ASent(auction.auction_number, auction.txnid)=0
						AND auction.paid and IFNULL(auction.shipping_username,'')=''
						AND auction.shipping_method = 0
						#AND auction.payment_method <> 0
						AND auction.deleted = 0
						and auction.main_auction_number=0";
				break;
				case 'ready_to_ship':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted
							, (select DATE(tl.updated) from total_log tl 
								where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1) end_time
							, TO_DAYS(NOW())
								-TO_DAYS(
									IF(auction.delivery_date_customer='0000-00-00'
										,(select tl.updated from total_log tl 
								where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1)
										,auction.delivery_date_customer)
								) days_due
							, DATE(fget_delivery_date_real(auction.auction_number, auction.txnid)) delivery_date_real
							, auction.delivery_date_customer
							, auction.shipping_username responsible_uname
							, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
							, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
							, concat(
								IFNULL(au_firstname_shipping.value,''), ', ',
								IFNULL(au_name_shipping.value,''), ', ',
								IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,'')) customer
							, (select GROUP_CONCAT(number) from tracking_numbers where tracking_numbers.auction_number = auction.auction_number
									and tracking_numbers.txnid = auction.txnid) tns
						from auction 
						left join auction_par_varchar au_name_shipping on au_name_shipping.auction_number = auction.auction_number
							and au_name_shipping.txnid = auction.txnid and au_name_shipping.key='name_shipping'
						left join auction_par_varchar au_firstname_shipping on au_firstname_shipping.auction_number = auction.auction_number
							and au_firstname_shipping.txnid = auction.txnid and au_firstname_shipping.key='firstname_shipping'
						left join auction_par_varchar au_city_shipping on au_city_shipping.auction_number = auction.auction_number
							and au_city_shipping.txnid = auction.txnid and au_city_shipping.key='city_shipping'
						left join auction_par_varchar au_zip_shipping on au_zip_shipping.auction_number = auction.auction_number
							and au_zip_shipping.txnid = auction.txnid and au_zip_shipping.key='zip_shipping'
						WHERE  IFNULL(auction.confirmation_date, '0000-00-00') <= NOW() 
						$seller_filter_str
						AND fget_ASent(auction.auction_number, auction.txnid)=0
						AND auction.paid
						AND auction.shipping_method = 0
						#AND auction.payment_method <> '0'
						#AND auction.payment_method <> ''
						AND auction.deleted = 0
						and auction.main_auction_number=0
						and IFNULL(auction.shipping_username,'')<>''";
				break;
				case 'ins':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted
							, DATE(insurance.date) end_time
							, TO_DAYS(NOW())-TO_DAYS(auction.end_time) days_due
							, insurance.responsible_username responsible_uname
							, (select DATE(create_date) from ins_comment where ins_comment.ins_id=insurance.id 
								order by create_date desc limit 1) last_comment_date
							, (select comment from ins_comment where ins_comment.ins_id=insurance.id 
								order by create_date desc limit 1) last_comment
							, insurance.id ins_id
							, shipping_method.company_name shipping_username
							,IFNULL((select sum(cost) from ins_article where ins_id=insurance.id and problem_id in (3, 7, 8) and not IFNULL(hidden, 0)), 0)
						 	  +IFNULL((select sum(amount_to_refund) from ins_sh_refund where ins_id=insurance.id), 0)
							  -IFNULL((select sum(amount) from ins_payment where ins_id=insurance.id), 0) ins_open_amount
							,(select max(time) from ins_log where ins_id=insurance.id and action='send_announce_insurance') as send_announce_insurance_date
							,(select max(time) from ins_log where ins_id=insurance.id and action='send_insurance') as claim_date
						from insurance
						join auction on auction.auction_number=insurance.auction_number and auction.txnid=insurance.txnid
						left join shipping_method on shipping_method.shipping_method_id = insurance.shipping_method
						where IFNULL(insurance.close_date, '0000-00-00')='0000-00-00'
						and not auction.deleted";
				break;
				case 'shipped':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted
							, (select DATE(tl.updated) from total_log tl 
								where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1) end_time
							, TO_DAYS(fget_delivery_date_real(auction.auction_number, auction.txnid))-TO_DAYS((select tl.updated from total_log tl 
								where tl.table_name='auction' and tl.field_name='shipping_username' and tl.tableid=auction.id limit 1)) days_due
							, DATE(fget_delivery_date_real(auction.auction_number, auction.txnid)) delivery_date_real
							, auction.shipping_username responsible_uname
							, (select DATE(create_date) from auction_sh_comment where auction_sh_comment.auction_number=auction.auction_number 
								and auction_sh_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
							, (select comment from auction_sh_comment where auction_sh_comment.auction_number=auction.auction_number 
								and auction_sh_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
							, concat(
								IFNULL(au_firstname_shipping.value,''), ', ',
								IFNULL(au_name_shipping.value,''), ', ',
								IFNULL(au_zip_shipping.value,''), ' ', IFNULL(au_city_shipping.value,'')) customer
							, (select GROUP_CONCAT(number  separator '<br>') from tracking_numbers where tracking_numbers.auction_number = auction.auction_number
									and tracking_numbers.txnid = auction.txnid) tns
						from auction 
						left join auction_par_varchar au_name_shipping on au_name_shipping.auction_number = auction.auction_number
							and au_name_shipping.txnid = auction.txnid and au_name_shipping.key='name_shipping'
						left join auction_par_varchar au_firstname_shipping on au_firstname_shipping.auction_number = auction.auction_number
							and au_firstname_shipping.txnid = auction.txnid and au_firstname_shipping.key='firstname_shipping'
						left join auction_par_varchar au_city_shipping on au_city_shipping.auction_number = auction.auction_number
							and au_city_shipping.txnid = auction.txnid and au_city_shipping.key='city_shipping'
						left join auction_par_varchar au_zip_shipping on au_zip_shipping.auction_number = auction.auction_number
							and au_zip_shipping.txnid = auction.txnid and au_zip_shipping.key='zip_shipping'
						where fget_ASent(auction.auction_number, auction.txnid)=1
						$seller_filter_str
						AND auction.deleted = 0
						and auction.main_auction_number=0";
				break;
				case 'ticket_closed':
					$qarray[] = "select rma.rma_id, rma.auction_number, rma.txnid, '$status' status, auction.deleted
							, DATE(rma.create_date) end_time
							, rma.close_date
							, -TO_DAYS(rma.create_date)+TO_DAYS(rma.close_date) days_due
							, rma.responsible_uname
							, (select DATE(create_date) from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment_date
							, (select comment from rma_comment where rma_comment.rma_id=rma.rma_id order by create_date desc limit 1) last_comment
						from rma
						join auction on auction.auction_number=rma.auction_number and auction.txnid=rma.txnid
						where IFNULL(rma.close_date, '0000-00-00')<>'0000-00-00'
						$seller_filter_str
						and not auction.deleted";
				break;
				case 'deleted':
					$qarray[] = "select auction.auction_number, auction.txnid, '$status' status, auction.deleted
							, DATE(auction.end_time) end_time
							, DATE(auction.deleted_date) deleted_date
							, TO_DAYS(NOW())-TO_DAYS(auction.end_time) days_due
							, auction.responsible_uname
							, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
							, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
								and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
						from auction 
						left join invoice on invoice.invoice_number = auction.invoice_number
						where 1
						$seller_filter_str
						AND auction.deleted = 1";
				break;
			}
		$q = "SELECT SQL_CALC_FOUND_ROWS t.*
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as  offer_name
			, invoice.open_amount
	    	, fget_ITotal(invoice.invoice_number) - invoice.open_amount paid_amount
			, fget_ITotal(invoice.invoice_number) total_amount
			, IFNULL(users.name, t.responsible_uname) responsible_uname
			from (
			".implode(' union all ', $qarray)."
			) t
			join auction on auction.auction_number=t.auction_number and auction.txnid=t.txnid
			left join invoice on auction.invoice_number=invoice.invoice_number 
			left join offer on offer.offer_id=auction.offer_id
			left join users on users.username=t.responsible_uname
			where 1
			$seller_filter_str
			$sort
			LIMIT $from, $to";
        $list = $dbr->getAll($q);
//		echo $q.'<br>';
        if (PEAR::isError($list)) {
			print_r($list);
            return;
        }
        return $list;
    }

    /**
     * Return information was that auction shipped or not
     * @param int[] $auctionNumbers auction identifiers
     * @return array associative array: key - auction number, value - 1 if was shipped, 0 otherwise
     */
    public static function areShipped($auctionNumbers)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        return $dbr->getAssoc(
            '
                SELECT auction_number, shipping_method
                FROM auction
                WHERE auction_number IN (?'.str_repeat(', ?', count($auctionNumbers)-1).')
            ',
            null,
            $auctionNumbers
        );
    }
    

    static function findArticle($db, $dbr, $key, $username_filter="''", $filter, $mode, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if ($filter['shipped']==1) {
			$where .= " and orders.sent=1 ";
		} elseif ($filter['shipped']=='0') {
			$where .= " and orders.sent=0 ";
		}
		if ($filter['deleted']==1) {
			$where .= " and auction.deleted=1 ";
		} elseif ($filter['deleted']=='0') {
			$where .= " and auction.deleted=0 ";
		}
		if ($filter['type']=='auction') {
			$where .= " and auction.txnid=1 ";
		} elseif ($filter['type']=='fix') {
			$where .= " and auction.txnid>10 ";
		} elseif ($filter['type']=='shop') {
			$where .= " and fget_AType(auction.auction_number,auction.txnid)='' ";
		}
		if ($filter['massemailsent']==1) {
			$where .= " and exists (SELECT null FROM email_log WHERE email_log.template like '%mass_email%' 
			and email_log.auction_number = IFNULL(mau.auction_number, auction.auction_number)
			AND email_log.txnid = IFNULL(mau.txnid, auction.txnid)) ";
		} elseif ($filter['massemailsent']=='0') {
			$where .= " and not exists (SELECT null FROM email_log WHERE email_log.template like '%mass_email%' 
			and email_log.auction_number = IFNULL(mau.auction_number, auction.auction_number)
			AND email_log.txnid = IFNULL(mau.txnid, auction.txnid)) ";
		}
		if ($username_filter!="''") $username_filter = " and auction.username in ($username_filter) ";
			else $username_filter = '';
		if ($sort=='ORDER BY currency') $sort='ORDER BY currency';
        $key = mysql_escape_string($key);
		switch ($mode) {
			case 'id': $where .= " and orders.article_id like '$key'";
			break;
			case 'name': $where .= " and translation.value like '%$key%'";
			break;
		}
		$q = "SELECT SQL_CALC_FOUND_ROWS * from (
				select IFNULL(mau.auction_number, auction.auction_number) auction_number, IFNULL(mau.txnid, auction.txnid)txnid
			, IFNULL(mau.end_time, auction.end_time) end_time
			, auction.deleted
			, IFNULL(mau.no_emails, auction.no_emails) no_emails
			, IFNULL(mau.delivery_date_real, auction.delivery_date_real) delivery_date_real
			, IFNULL(mau.username, auction.username) username
			, IFNULL(mau.winning_bid, auction.winning_bid) winning_bid
			, IFNULL(mau.delivery_date_customer, auction.delivery_date_customer) delivery_date_customer
			, invoice.open_amount
			, fget_ITotal(invoice.invoice_number) total_amount
			,IF(IFNULL(mau.priority,auction.priority), 'green', IF(fget_ASent(IFNULL(mau.auction_number,auction.auction_number), IFNULL(mau.txnid,auction.txnid))=0, '', 'gray')) as colour
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
					join auction a1 on a1.offer_id=o1.offer_id
					where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, fget_Currency(IFNULL(mau.siteid, auction.siteid)) currency
			, fget_ACustomer(IFNULL(mau.auction_number, auction.auction_number), IFNULL(mau.txnid, auction.txnid)) customer
			, IFNULL(users.name, auction.shipping_username) as company_shipping
			, au_city_shipping.value as city_shipping
			, CONCAT(au_firstname_shipping.value,' ',au_name_shipping.value) as name_shipping
			, IF(invoice.open_amount=0, IFNULL(max(p.payment_date), 'FREE'),'') as paid_date 
			, ROUND((SELECT sum(
			        ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
						+ IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0) 
						+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
						- IFNULL(ac.packing_cost,0)/IFNULL(ac.curr_rate,0)), 2)
				)
				FROM auction_calcs ac 
				join auction auc on ac.auction_number = auc.auction_number
					AND ac.txnid = auc.txnid
				WHERE (auc.auction_number = IFNULL(mau.auction_number, auction.auction_number)
					AND auc.txnid = IFNULL(mau.txnid, auction.txnid))
				),2) 
			+ ROUND((SELECT sum(
			        ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
						+ IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0) 
						+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
						- IFNULL(ac.packing_cost,0)/IFNULL(ac.curr_rate,0)), 2)
				)
				FROM auction_calcs ac 
				join auction auc on ac.auction_number = auc.auction_number
					AND ac.txnid = auc.txnid
				WHERE (auc.main_auction_number = IFNULL(mau.auction_number, auction.auction_number)
					AND auc.main_txnid = IFNULL(mau.txnid, auction.txnid))
				),2) as total_profit
		,(SELECT GROUP_CONCAT(email_log.date ORDER BY email_log.date DESC SEPARATOR '<br>') FROM email_log WHERE email_log.template like '%mass_email%' 
			and email_log.auction_number = IFNULL(mau.auction_number, auction.auction_number) 
			AND email_log.txnid = IFNULL(mau.txnid, auction.txnid)) as massemail_datetime
		FROM auction
		LEFT JOIN auction mau ON auction.main_auction_number=mau.auction_number and auction.main_txnid=mau.txnid
		LEFT JOIN users ON users.username=IFNULL(mau.shipping_username, auction.shipping_username)
		left JOIN payment p ON p.auction_number = IFNULL(mau.auction_number, auction.auction_number)
			 AND p.txnid = IFNULL(mau.txnid, auction.txnid)
		JOIN invoice ON invoice.invoice_number = IFNULL(mau.invoice_number, auction.invoice_number)
		JOIN orders ON orders.auction_number = auction.auction_number and orders.txnid = auction.txnid
		join article on orders.article_id=article.article_id and article.admin_id=0 and orders.manual=0
				join translation on table_name = 'article'
					AND field_name = 'name'
					AND translation.id = article.article_id
		LEFT JOIN offer ON offer.offer_id = auction.offer_id
		left join auction_par_varchar au_name_shipping on IFNULL(mau.auction_number, auction.auction_number)=au_name_shipping.auction_number 
			and IFNULL(mau.txnid, auction.txnid)=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
		left join auction_par_varchar au_firstname_shipping on IFNULL(mau.auction_number, auction.auction_number)=au_firstname_shipping.auction_number 
			and IFNULL(mau.txnid, auction.txnid)=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
		left join auction_par_varchar au_city_shipping on IFNULL(mau.auction_number, auction.auction_number)=au_city_shipping.auction_number 
			and IFNULL(mau.txnid, auction.txnid)=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
		left join auction_par_varchar au_company_shipping on IFNULL(mau.auction_number, auction.auction_number)=au_company_shipping.auction_number 
			and IFNULL(mau.txnid, auction.txnid)=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
		where 1=1 $where 
		$username_filter
		$seller_filter_str
		group by auction.auction_number, auction.txnid
		) tt
		$sort
			LIMIT $from, $to";
//		echo $q; die();
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findUnshipped($db, $dbr, $days, $username, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, invoice.open_amount
			, IF(auction.priority, (select 
				CONCAT('Priority set on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='auction' and field_name='priority' and tableid=auction.id
					order by updated desc limit 1), NULL) priority_on_by
			, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
			, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
			, datediff(NOW(), auction.end_time) end_time_days
			, IF(auction.priority, 'green', '') prioritycolor
			from auction
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			where 1
			and main_auction_number=0
			and fget_ASent(auction.auction_number, auction.txnid)=0
			and auction.end_time<NOW() and auction.invoice_number
			and auction.deleted=0
			and ('$username'='' or '$username'=auction.username)
			and datediff(NOW(), auction.end_time)>=$days
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findSaved($db, $dbr, $saved_id, $saved_name, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if ($saved_id) {
			$where = " and auction.saved_id=$saved_id";
		} else {
			$where = " and saved_params.par_value='$saved_name'";
		}
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, invoice.open_amount
			, IF(auction.priority, (select 
				CONCAT('Priority set on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='auction' and field_name='priority' and tableid=auction.id
					order by updated desc limit 1), NULL) priority_on_by
			, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
			, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
			, datediff(NOW(), auction.end_time) end_time_days
			, IF(auction.priority, 'green', '') prioritycolor
			, concat(auction.saved_id, ': ', saved_params.par_value) saved_id_name
			from auction
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			LEFT JOIN saved_params ON saved_params.par_key = 'auction_name' and saved_params.saved_id=auction.saved_id
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			where 1
		$seller_filter_str
		$where
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
		foreach($r as $k=>$dummy) {
			$r[$k]->saved_id_name = utf8_decode($r[$k]->saved_id_name);
		}
        return $r;
    }

 /**
  * Returns the list of all auftrags with special comment in paymen
  * is used for searching By transaction ID or payment comment (search.php) and gc_number
  * current state: search in PayPal, Saferpay (all kinds), simple payments
  * @return array of objects - the list of auftrags
  *
  */
    static function findPaymentComment($db, $dbr, $payment_comment, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$payment_ids = $dbr->getAssoc("select 0 f1,0 f2 union select payment_id, payment_id from payment where `comment` like '%$payment_comment%'");
		$flds = "auction.auction_number
			, auction.txnid
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, invoice.open_amount
			, IF(auction.priority, (select 
				CONCAT('Priority set on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='auction' and field_name='priority' and tableid=auction.id
					order by updated desc limit 1), NULL) priority_on_by
			, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
			, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
			, datediff(NOW(), auction.end_time) end_time_days
			, IF(auction.priority, 'green', '') prioritycolor
			";
		$q = "SELECT SQL_CALC_FOUND_ROWS * from (
			select $flds
			from auction
			left join auction_par_varchar au_gc_number on `key`='gc_number'
					and au_gc_number.auction_number=auction.auction_number and au_gc_number.txnid=auction.txnid
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			JOIN payment_paypal pp on pp.txn_id = '$payment_comment' and pp.item_number=auction.auction_number
			where 1
			and main_auction_number=0
		$seller_filter_str
			union select $flds
			from auction
			left join auction_par_varchar au_gc_number on `key`='gc_number'
					and au_gc_number.auction_number=auction.auction_number and au_gc_number.txnid=auction.txnid
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			JOIN payment pp on pp.payment_id in (".implode(',',$payment_ids).") and pp.auction_number=auction.auction_number and pp.txnid=auction.txnid
			where 1
			and main_auction_number=0
		$seller_filter_str
			union select $flds
			from auction
			left join auction_par_varchar au_gc_number on `key`='gc_number'
					and au_gc_number.auction_number=auction.auction_number and au_gc_number.txnid=auction.txnid
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			JOIN payment_saferpay pp on pp.txn_id = '$payment_comment' and pp.auction_number=auction.auction_number and pp.txnid=auction.txnid
			where 1
			and main_auction_number=0
		$seller_filter_str
			union select $flds
			from auction
			left join auction_par_varchar au_gc_number on `key`='gc_number'
					and au_gc_number.auction_number=auction.auction_number and au_gc_number.txnid=auction.txnid
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			where 1
			and main_auction_number=0
			and au_gc_number.`value` = '$payment_comment'
		$seller_filter_str
		) t $sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r( $r);
            return;
        }
        return $r;
    }

    static function findSpecOrder($db, $dbr, $shipped='', $ops='', $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$where = '';
		if ($shipped!='') $where .= " and ".($shipped?'':'NOT')." o.sent";
		if ($ops!='') $where .= " and ".($ops?'':'NOT')." o.spec_order_id";
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.end_time
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, invoice.open_amount
			, IF(auction.priority, (select 
				CONCAT('Priority set on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='auction' and field_name='priority' and tableid=auction.id
					order by updated desc limit 1), NULL) priority_on_by
			, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
			, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
			, datediff(NOW(), auction.end_time) end_time_days
			, IF(auction.priority, 'green', '') prioritycolor
			, CONCAT('<a class=\"splink4\" target=\'_blank\' href=\'article.php?original_article_id=',o.article_id,'\'>',o.article_id, IFNULL(CONCAT(': ', tl.value),''),'</a>') article_id
			, opo.id spec_order_id
			, CONCAT('<a target=\'_blank\' href=\'auction.php?number=',auction.auction_number,'&txnid=',auction.txnid,'#invoice\'>',o.spec_order_comment,'</a>') spec_order_comment
			from auction
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			JOIN orders o ON o.auction_number = auction.auction_number and o.txnid = auction.txnid
			left JOIN translation tl ON tl.table_name='article' and tl.field_name='name' and tl.language='german' and tl.id=o.article_id
			left JOIN op_order opo ON o.spec_order_id = opo.id
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname 
			where o.spec_order
			$where
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findNewArticleCompleted($db, $dbr, $new_article_completed, $from=0, $to=9999999, $sort)
    {
	    global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        /* Table for barcode warehouse, if use denormalization - barcode_dn */
        $vbw = 'vbarcode_warehouse';
        if ($GLOBALS['CONFIGURATION']['use_dn']) $vbw = 'barcode_dn';
		$where = " and o.new_article_completed=".$new_article_completed;
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.end_time
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, invoice.open_amount
			, IF(auction.priority, (select 
				CONCAT('Priority set on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='auction' and field_name='priority' and tableid=auction.id
					order by updated desc limit 1), NULL) priority_on_by
			, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
			, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
			, datediff(NOW(), auction.end_time) end_time_days
			, IF(auction.priority, 'green', '') prioritycolor
			, CONCAT('<a class=\"splink5\" target=\'_blank\' href=\'article.php?original_article_id=',o.article_id,'\'>',o.article_id, IFNULL(CONCAT(': ', tl.value),''),'</a>') article_id
			, opo.id spec_order_id
			, CONCAT('<a target=\'_blank\' href=\'auction.php?number=',auction.auction_number,'&txnid=',auction.txnid,'#invoice\'>',o.spec_order_comment,'</a>') spec_order_comment
			, o.new_article_id, o.new_article_qnt
			, IF(o.new_article_completed, (select 
				CONCAT('Article completed ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='orders' and field_name='new_article_completed' and tableid=o.id
					order by updated desc limit 1), NULL) completed_on_by
			, CONCAT('<a class=\"splink6\" target=\'_blank\' href=\'article.php?original_article_id=',o.new_article_id,'\'>',o.new_article_id, IFNULL(CONCAT(': ', t2.value),''),'</a>') new_article_id_name
			, new_w.name new_article_warehouse
            , IFNULL(new_b_w.name, new_w.name) new_barcode_warehouse
			from auction
			JOIN invoice ON invoice.invoice_number = auction.invoice_number
			JOIN orders o ON o.auction_number = auction.auction_number and o.txnid = auction.txnid
			join warehouse new_w on new_w.warehouse_id=o.new_article_warehouse_id
			left JOIN translation tl ON tl.table_name='article' and tl.field_name='name' and tl.language='german' and tl.id=o.article_id
			left JOIN translation t2 ON t2.table_name='article' and t2.field_name='name' and t2.language='german' and t2.id=o.new_article_id
			left JOIN op_order opo ON o.spec_order_id = opo.id
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=auction.responsible_uname
            LEFT JOIN barcode_object bo ON bo.obj = 'decompleted_article' and bo.obj_id = o.id
            LEFT JOIN {$vbw} wb ON wb.id = bo.barcode_id
            LEFT JOIN warehouse new_b_w ON new_b_w.warehouse_id=wb.last_warehouse_id
			where o.new_article and o.new_article_id and o.new_article_qnt 
			and NOT IFNULL(o.new_article_not_deduct,0)
			and lost_new_article=0 # asked by Hanna Wichrowska  2015-02-14
			$where
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }
	
    static function findUnshippedBonus($db, $dbr, $unshipped_bonus_country, $bonus_id, $username_filter="''", $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if (!strlen($unshipped_bonus_country)) return array();
		$where = '';
		if ($username_filter!="''") $where .= " and auction.username in ($username_filter) ";
		if (count($bonus_id)) $where .= " and sb.id in (".implode(',',$bonus_id).")";
		
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			IFNULL(mau.auction_number, auction.auction_number) auction_number
			, IFNULL(mau.txnid, auction.txnid) txnid
			, IFNULL(mau.end_time, auction.end_time) end_time
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, invoice.open_amount
			, IF(auction.priority, (select 
				CONCAT('Priority set on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
					from total_log tl
					left join users u on u.system_username=tl.username
					where table_name='auction' and field_name='priority' and tableid=auction.id
					order by updated desc limit 1), NULL) priority_on_by
			, (select DATE(create_date) from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment_date
			, (select comment from auction_comment where auction_comment.auction_number=auction.auction_number 
				and auction_comment.txnid=auction.txnid order by create_date desc limit 1) last_comment
			, datediff(NOW(), auction.end_time) end_time_days
			, IF(auction.priority, 'green', '') prioritycolor
			from auction
			left join auction mau on mau.auction_number=auction.main_auction_number and mau.txnid=auction.txnid
			join orders o on o.auction_number=auction.auction_number and o.txnid=auction.txnid
			join article a on a.article_id=o.article_id and a.admin_id=o.manual
			join shop_bonus sb on a.article_id=sb.article_id
			JOIN invoice ON invoice.invoice_number = IFNULL(mau.invoice_number, auction.invoice_number)
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=IFNULL(mau.responsible_uname, auction.responsible_uname) 
			where 1
			and a.admin_id=2 and sb.country_code='$unshipped_bonus_country'
			and fget_ASent(IFNULL(mau.auction_number, auction.auction_number), IFNULL(mau.txnid, auction.txnid))=0 
			and IFNULL(mau.deleted, auction.deleted)=0
			$where
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }
	
    static function findBydelivery_date_customer($db, $dbr, $date_from, $date_to, $username_filter, $from=0, $to=9999999, $sort) {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$where = '';
		if (strlen($date_from)) $where .= " and IFNULL(mau.delivery_date_customer, auction.delivery_date_customer) >= '$date_from' ";
		if (strlen($date_to)) $where .= " and IFNULL(mau.delivery_date_customer, auction.delivery_date_customer) <= '$date_to' ";
		if ($username_filter!="''") $where .= " and auction.username in ($username_filter) ";
		
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			IFNULL(mau.auction_number, auction.auction_number) auction_number
			, IFNULL(mau.txnid, auction.txnid) txnid
			, IFNULL(mau.end_time, auction.end_time) end_time
			, auction.username
			, auction.priority
			, auction.priority_comment
			, IFNULL(users.name, auction.responsible_uname) responsible_uname
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			, IFNULL(mau.delivery_date_customer, auction.delivery_date_customer) delivery_date_customer
			from auction
			left join auction mau on mau.auction_number=auction.main_auction_number and mau.txnid=auction.txnid
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN users ON users.username=IFNULL(mau.responsible_uname, auction.responsible_uname) 
			where 1
			and IFNULL(mau.deleted, auction.deleted)=0
			$where
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }
	
	static function voucherDoubles() {
		$q = "select auction.auction_number, count(*), min(spc.usage) `usage`
			from auction
			join shop_promo_codes spc on spc.id=auction.code_id
			where auction.deleted=0
			group by code_id
			having count(*)>`usage`";
	}
	
	static function findByPromo($db, $dbr, $promo_id, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.end_time
			, auction.username
			from auction
			join shop_promo_logo_email splm on splm.auction_number=auction.auction_number and splm.txnid=auction.txnid
			where 1
			and promo_logo_id=$promo_id
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

	static function findByPayMethod($db, $dbr, $payment_method, $date_from, $date_to, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if (!strlen($date_from)) $date_from='0000-00-00';
		if (!strlen($date_to)) $date_to='2999-12-31';
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.end_time
			, auction.username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			from auction
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			JOIN payment p ON p.auction_number = auction.auction_number
						AND p.txnid = auction.txnid
			where 1
			and auction.payment_method='$payment_method'
			and p.payment_date >= '$date_from'
			and p.payment_date <= '$date_to'
		$seller_filter_str
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

	static function findByNoBarcodes($db, $dbr, $warehouse_id=0, $article_id='', $number='', $packed_username='', $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$where = '';
		if ($warehouse_id) $where .= ' and o.send_warehouse_id='.$warehouse_id;
		if (strlen($article_id)) $where .= " and o.article_id='$article_id'";
		if (strlen($number)) {
			list($auction_number, $txnid) = explode('/',$number);
			$where .= " and mau.auction_number=$auction_number";
			if (strlen($txnid)) {
				$where .= " and mau.txnid=$txnid";
			}
		}
		if (strlen($packed_username)) {
			$where .= " and tn.username='$packed_username'";
		}
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			auction.auction_number
			, auction.txnid
			, auction.end_time
			, auction.username
			, auction.article_id
			, auction.send_warehouse_id
			, w.name warehouse
			, auction.quantity
			, t.value article
			, group_concat(distinct users.name separator '<br>') packed_username
			, auction.id auction_id
			, auction.order_id
			, auction.order_id id
			, auction.boid
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=auction.auction_number and a1.main_txnid=auction.txnid)) as offer_name
			from (select IFNULL(mau.auction_number, sau.auction_number) auction_number, o.article_id, o.send_warehouse_id, o.quantity
				, IFNULL(mau.txnid, sau.txnid) txnid
				, IFNULL(mau.end_time, sau.end_time) end_time
				, IFNULL(mau.username, sau.username) username
				, IFNULL(mau.offer_id, sau.offer_id) offer_id
				, IFNULL(mau.lang, sau.lang) lang
				, tn.username tn_username
				, IFNULL(mau.id, sau.id) id
				, o.id order_id
				, bo.id boid
				from orders o
				join barcode_object bo on bo.obj='orders' and bo.obj_id=o.id and bo.barcode_id is null
				join auction sau on sau.auction_number=o.auction_number and sau.txnid=o.txnid
				left join auction mau on mau.auction_number=sau.main_auction_number and mau.txnid=sau.main_txnid
			LEFT JOIN tn_orders tno ON tno.order_id=o.id
			LEFT JOIN tracking_numbers tn ON tno.tn_id=tn.id
				where 1
				#and o.no_barcodes=1
				$where) auction
			left join users on users.username=auction.tn_username
			LEFT JOIN offer ON offer.offer_id = auction.offer_id
			LEFT JOIN warehouse w ON w.warehouse_id=auction.send_warehouse_id
			LEFT JOIN translation t ON t.table_name='article' and t.field_name='name' and t.language=auction.lang and t.id=auction.article_id
			where 1
		$seller_filter_str
		group by auction.order_id
		$sort
			LIMIT $from, $to";
		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }


	static function findByBonus($db, $dbr, $bonus_id, $username, $date_from, $date_to, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$where = '';
		if (strlen($username)) $where1 .= " and auction.username='".mysql_escape_string($username)."' ";
		if (strlen($date_from)) {
			$having .= " AND  max(updated)>='".mysql_escape_string($date_from)." 00:00:00' ";
		}
		if (strlen($date_to)) {
			$having .= " AND  max(updated)<='".mysql_escape_string($date_to)." 23:59:59' ";
		}
		if ($bonus_id) $where = "and sb.id=$bonus_id ";
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			au.auction_number
			, au.txnid
			, au.end_time
			, au.username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=au.auction_number and a1.main_txnid=au.txnid)) as offer_name
			from auction au
	left join offer on offer.offer_id=au.offer_id
	join (
			select distinct IFNULL(mauction.auction_number,auction.auction_number) auction_number
			, IFNULL(mauction.txnid,auction.txnid) txnid 
			from total_log 
			join orders on total_log.tableid=orders.id
			join auction on auction.auction_number=orders.auction_number 
			and auction.txnid=orders.txnid
			left join auction mauction on auction.main_auction_number=mauction.auction_number 
			and auction.main_txnid=mauction.txnid
			where total_log.table_name='orders' and total_log.field_name='sent' 
			and total_log.New_value=1  and orders.manual=0
			$where1
			group by IFNULL(mauction.auction_number,auction.auction_number)
			, IFNULL(mauction.txnid,auction.txnid)
			having 1 $having
		) t on t.auction_number= au.auction_number and t.txnid= au.txnid
	left join orders o on o.auction_number=au.auction_number and o.txnid=au.txnid
	left join shop_bonus sb  on o.article_id=sb.article_id and o.manual=2
			where 1
		$seller_filter_str
		$where
		group by au.id
		$sort
			LIMIT $from, $to";
		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

	static function findByEmp($db, $dbr, $company, $from=0, $to=9999999, $sort)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$where = '';
		if ($company) $where = "and (a.company_id=$company or ma.company_id=$company)";
		$q = "SELECT SQL_CALC_FOUND_ROWS 
			IFNULL(mau.auction_number, au.auction_number) auction_number
			, IFNULL(mau.txnid, au.txnid) txnid
			, IFNULL(mau.end_time, au.end_time) end_time
			, au.username
			, IFNULL(offer.name,
				(select GROUP_CONCAT(o1.name SEPARATOR '<br><br>') from offer o1
				join auction a1 on a1.offer_id=o1.offer_id
				where a1.main_auction_number=au.auction_number and a1.main_txnid=au.txnid)) as offer_name
			, group_concat(distinct o.article_id) article_id
			, (select sum(amount) from payment where auction_number = IFNULL(mau.auction_number, au.auction_number)
				and txnid = IFNULL(mau.txnid, au.txnid)) paid_amount
			, CONCAT(au_firstname_invoice.value, ' ', au_name_invoice.value) fullname
			from auction au
			left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
			left join orders o on au.auction_number=o.auction_number and au.txnid=o.txnid
					left join auction_par_varchar au_name_invoice on IFNULL(mau.auction_number, au.auction_number)=au_name_invoice.auction_number 
						and IFNULL(mau.txnid, au.txnid)=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
					left join auction_par_varchar au_firstname_invoice on IFNULL(mau.auction_number, au.auction_number)=au_firstname_invoice.auction_number 
						and IFNULL(mau.txnid, au.txnid)=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
			left join article a on a.article_id=o.article_id and a.admin_id=o.manual and o.manual=0
			left join orders mo on mau.auction_number=mo.auction_number and mau.txnid=mo.txnid
			left join article ma on ma.article_id=mo.article_id and ma.admin_id=mo.manual and mo.manual=0
			left join offer on offer.offer_id=au.offer_id
			left join invoice i on i.invoice_number=IFNULL(mau.invoice_number, au.invoice_number)
			where 1 and IFNULL(mau.emp_id, au.emp_id)
		$seller_filter_str
		$where
		group by IFNULL(mau.id,au.id)
		$sort
			LIMIT $from, $to";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

 /**
  * Returns a list of all goods of this auftrag
  *
  * @return array of objects Returns the list of articles docs
  */
	function getManuals() {	
		return $this->_dbr->getAll("select o.article_id, d.doc_id
			from orders o 
			join article_doc d on d.article_id=o.article_id
			where o.manual=0 and o.auction_number=".$this->get('auction_number')." and o.txnid=".$this->get('txnid')." and d.shop_it
			union
			select o.article_id, d.doc_id
			from orders o 
			join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
			join article_doc d on d.article_id=o.article_id
			where o.manual=0 and au.main_auction_number=".$this->get('auction_number')." and au.main_txnid=".$this->get('txnid')." and d.shop_it
			");
	}


    public static function labelLog($number, $txnid)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $query = "SELECT `tl`.`updated`, IFNULL(`u`.`name`, `tl`.`username`) AS `username`, `al`.*, 
                    (
                        SELECT CONCAT(`pl`.`log_date`, '|', IFNULL(`u`.`name`, `pl`.`username`)) 
                        FROM `printer_log` `pl`
                        LEFT JOIN `users` `u` ON `u`.`username` = `pl`.`username`
                        WHERE `pl`.`auction_number` = `al`.`auction_number` 
                            AND `pl`.`txnid` = `al`.`txnid` 
                            AND `pl`.`action` LIKE CONCAT('%', IF (`al`.`tracking_number` != '', `al`.`tracking_number`, `al`.`filename`))
                        ORDER BY `pl`.`iid` DESC 
                        LIMIT 1
                    ) AS `printer_log`
                FROM `auction_label` `al`
                LEFT JOIN `total_log` `tl` ON `tl`.`table_name`='auction_label' 
                    AND `field_name` = 'id' 
                    AND `tl`.`TableID` = `al`.`id`
                LEFT JOIN `users` `u` ON `u`.`system_username` = `tl`.`username`
                    
                WHERE `al`.`auction_number` = '" . (int)$number . "' 
                    AND `al`.`txnid` = '" . (int)$txnid . "'
                ORDER BY `al`.`id`
        ";
        
        $result = [];
        foreach ($db->getAll($query) as $item)
        {
            $item->printer_log = explode('|', $item->printer_log, 2);
            $item->printer_username_log = $item->printer_log[1];
            $item->printer_log = $item->printer_log[0];
            
            $result[] = $item;
        }
        
        return $result;
    }

    public function getLabelsCount($shipping_method_id)
    {
        $auction_number = $this->data->auction_number;
        $txnid = $this->data->txnid;

        $q = "SELECT COUNT(*) FROM auction_label 
              WHERE auction_number = $auction_number 
              AND txnid = $txnid
              AND shipping_method_id = $shipping_method_id
              AND inactive IS NULL";
        $result = $this->_dbr->getOne($q);

        return $result;
    }
    /**
     * Format invoice tel for admin part
     */
    private function _formatInvoiceTel()
    {
        if (!$this->_countries) {
            $this->_countries = $this->_dbr->getAssoc("select country.code, country.* from country");
        }
        
        $phone_prefix = !empty($this->_countries[$this->data->tel_country_code_invoice]['phone_prefix'])
            ? '+' . $this->_countries[$this->data->tel_country_code_invoice]['phone_prefix'] . ' '
            : '';
        $this->data->tel_invoice_formatted = $phone_prefix . $this->data->tel_invoice;
    }
    /**
     * Format invoice cel phone for admin part
     */
    private function _formatInvoiceCel()
    {
        if (!$this->_countries) {
            $this->_countries = $this->_dbr->getAssoc("select country.code, country.* from country");
        }
        
        $phone_prefix = !empty($this->_countries[$this->data->cel_country_code_invoice]['phone_prefix']) 
            ? '+' . $this->_countries[$this->data->cel_country_code_invoice]['phone_prefix'] . ' '
            : '';
        $this->data->cel_invoice_formatted = $phone_prefix . $this->data->cel_invoice;
    }
    /**
     * Format shipping tel for admin part
     */
    private function _formatShippingTel()
    {
        if (!$this->_countries) {
            $this->_countries = $this->_dbr->getAssoc("select country.code, country.* from country");
        }
        
        if ($this->data->same_address) {
            $phone_prefix = !empty($this->_countries[$this->data->tel_country_code_invoice]['phone_prefix']) 
                ? '+' . $this->_countries[$this->data->tel_country_code_invoice]['phone_prefix'] . ' '
                : '';
            $this->data->tel_shipping_formatted = $phone_prefix . $this->data->tel_invoice;
        } else {
            $phone_prefix = !empty($this->_countries[$this->data->tel_country_code_shipping]['phone_prefix'])
                ? '+' . $this->_countries[$this->data->tel_country_code_shipping]['phone_prefix'] . ' '
                : '';
            $this->data->tel_shipping_formatted = $phone_prefix . $this->data->tel_shipping;
        }
    }
    /**
     * Format shipping mobile for admin part
     * @return string
     */
    private function _formatShippingCel()
    {
        if (!$this->_countries) {
            $this->_countries = $this->_dbr->getAssoc("select country.code, country.* from country");
        }
        
        if ($this->data->same_address) {
            $phone_prefix = !empty($this->_countries[$this->data->cel_country_code_invoice]['phone_prefix']) 
                ? '+' . $this->_countries[$this->data->cel_country_code_invoice]['phone_prefix'] . ' '
                : '';
            $this->data->cel_shipping_formatted = $phone_prefix . $this->data->cel_invoice;
        } else {
            $phone_prefix = !empty($this->_countries[$this->data->cel_country_code_shipping]['phone_prefix'])
                ? '+' . $this->_countries[$this->data->cel_country_code_shipping]['phone_prefix'] . ' '
                : '';
            $this->data->cel_shipping_formatted = $phone_prefix . $this->data->cel_shipping;
        }
    }
    /**
     *
     */
    public static function copyExistingToNew($from_auction_number, $from_txnid, $to_auction_number)
    {
        $from_auction_number = (int)$from_auction_number;
        $from_txnid = (int)$from_txnid;
        $to_auction_number = (int)$to_auction_number;
    
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $not_main = (bool)$dbr->getOne("SELECT auction_number 
            FROM auction
            WHERE `auction_number` = $from_auction_number 
            AND `txnid` = $from_txnid
            AND `main_auction_number` != 0"); 
        
        $exist = (bool)$dbr->getOne("SELECT auction_number 
            FROM auction
            WHERE auction_number = $to_auction_number 
            AND txnid = $from_txnid"); 
            
        $shipped = (bool)$dbr->getOne("SELECT id 
            FROM orders
            WHERE auction_number = $from_auction_number 
            AND txnid = $from_txnid
            AND manual = 0
            AND sent = 1"); 
            
        if (!$exist && $not_main && !$shipped) {
            $tables = [
                'auction' => ['id'],
                'auction_par_varchar' => ['iid'],
                'auction_par_text' => ['iid'],
                'tracking_numbers' => ['id'],
                'orders' => ['id'],
                'auction_comment' => ['id'],
                'auction_calcs' => ['iid'],
                'auction_sh_comment' => ['id'],
                'auction_warehouse_distance' => ['id'],
                'auction_feedback' => ['iid'],
                //'payment' => [''],
                //'rma' => [],
                //'rating' => [],
            ];
            
            try {
                $db->beginTransaction();

                foreach ($tables as $table => $ignore) {
                    $fields_to_copy = [];
                    
                    $explain = $dbr->getAll("EXPLAIN $table");

                    foreach ($explain as $field) {
                        if (!in_array($field->Field, $ignore)) {
                            $fields_to_copy[] = $field->Field;
                        }
                    }
                    
                    $res = $dbr->getAll("SELECT `" . implode('` ,`', $fields_to_copy) . "` FROM $table 
                        WHERE `auction_number` = '$from_auction_number' 
                        AND `txnid` = '$from_txnid'");

                    foreach ($res as $row) {
                        $row->auction_number = $to_auction_number;
                        
                        $q = "INSERT INTO `$table` SET";
                        foreach ($fields_to_copy as $k => $field) {
                            $value = $row->$field === null ? 'NULL' : "'{$row->$field}'";
                            $q .= ($k ? ', ': ' ') . "`$field` = $value";
                        }
                        
                        $r = $db->query($q);
                        
                        if (PEAR::isError($r)) {
                            throw new Exception($r->getMessage());
                        }
                    }
                }
                
                $db->commit();
                return $to_auction_number;
            } catch (Exception $e) {
                $db->rollback();
                die($e->getMessage());
            }
        } else {
            if (!$not_main) 
                $error = 'Only subauction can be duplicated!';
            if ($exist) 
                $error = 'This Auction is already exist!';
            if ($shipped) 
                $error = 'Shipped Auction can\'t be duplicated!';
                
            die($error);
        }
        
        return false;
    }
}

?>