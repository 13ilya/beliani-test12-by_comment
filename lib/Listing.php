<?php
/**
 * Auction
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';
require_once 'Config.php';

class Listing
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

    function Listing($db, $dbr, $number = '')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Listing::Listing expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        
        if (!$number) {
            $r = $this->_db->query("EXPLAIN listings");
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
            $r = $this->_db->query("
                SELECT *
                FROM listings
                WHERE auction_number=$number
                ");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Listing::Listing : record $number does not exist");
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

    function update($old_auction_number='')
    {
		$old_auction_number = mysql_escape_string($old_auction_number);
		if (strlen($old_auction_number)) {
			$this->_db->query("delete from listings where auction_number=$old_auction_number");
			$this->_isNew = true;
			$showerror = true;
		}	
		$old_auction_number = $old_auction_number ? $old_auction_number : $this->data->auction_number;
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Auction::update : no data');
            return;
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
            $where = "WHERE auction_number=$old_auction_number";
        }
        $r = $this->_db->query("$command listings SET $query $where");
        $this->data->offer_name = $offer_name;
        $this->data->contact_name = $contact_name;
        $this->data->seller_email = $seller_email;
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        return $r;
    }


    /**
    * @return array
    * @param object $db
    * @param string $ebayTime eBay official time (actually GMT)
    * @desc Find fxed price (type 9) auctions not ended yet
    */
    static function findNotFinishedFixPrice($db, $dbr, $ebayTime, $auction_number=0, $seller='')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Listing::findNotFinishedFixPrice expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
// 20050607 {
		$auction_force_cond = "(listings.end_time <> '0000-00-00 00:00:00' 
            AND DATE_ADD(listings.end_time, INTERVAL 7 DAY) >= '".$ebayTime."' 
            AND (listings.auction_type = 9 or listings.auction_type = 7 
				or auction_type_vch = 'StoresFixedPrice'  or auction_type_vch = 'FixedPriceItem')
            AND NOT listings.finished)";
		if (strlen($seller)) $auction_force_cond .= " and username='$seller'";
/*		$auction_force_cond = "(
            listings.end_time >= DATE_ADD('".$ebayTime."' , INTERVAL 7 DAY)
            AND (listings.auction_type = 9 or listings.auction_type = 7 
				or auction_type_vch = 'StoresFixedPrice'  or auction_type_vch = 'FixedPriceItem')
            AND NOT listings.finished)";*/
		if ($auction_number) $auction_force_cond = "auction_number = $auction_number";
		$q = "
            SELECT username
			, auction_number
			, end_time2
			, start_time
			, offer_id
			, name_id
			, server
			, siteid
			, no_pickup
			, listing_fee
			, listing_fee1
			, listing_fee2
#			, details
#			, params
			, saved_id
			, allow_payment_1
			, allow_payment_2
			, allow_payment_3
			, allow_payment_4
			, allow_payment_cc
			, allow_payment_cc_sh
			, end_time
            FROM listings
            WHERE 
			$auction_force_cond
            ";
		echo($q);	
        $r = $dbr->getAll($q); 
// 20050607 }
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($r);
            return;
        }
        return $r;
    }
    
    static function findJustFinishedFixPriceForRestarting($db, $dbr, $ebayTime)
    {
        $r = $dbr->getAll("SELECT auction_number, siteid, saved_id, username 
            FROM listings
            WHERE listings.end_time >= '$ebayTime' 
            AND (listings.auction_type = 9 or listings.auction_type = 7 
				or auction_type_vch = 'StoresFixedPrice'  or auction_type_vch = 'FixedPriceItem')
            AND listings.finished=0 
			AND NOT listings.restarted
			group by saved_id
            "); 
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($r);
            return;
        }
        return $r;
    }

    static function findActive($db, $dbr, $ebayTime)
    {
        $r = $dbr->getAll("SELECT auction_number, siteid, saved_id, username 
            FROM listings
            WHERE listings.end_time >= '$ebayTime' 
            AND (listings.auction_type = 9 or listings.auction_type = 7 
				or auction_type_vch = 'StoresFixedPrice'  or auction_type_vch = 'FixedPriceItem')
            AND listings.finished=0 
            "); 
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($r);
            return;
        }
        return $r;
    }

    /**
    * @return array
    * @param object $db
    * @param string $ebayTime eBay official time (actually GMT)
    * @desc Find Dutch (type 2) auctions just ended
    */
    static function findJustFinishedDutch($db, $dbr, $ebayTime)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Listing::findNotFinishedFixPrice expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("
            SELECT * 
            FROM listings
            WHERE listings.end_time <> '0000-00-00 00:00:00' 
            AND listings.end_time <= '$ebayTime'
            AND (listings.auction_type = 2 or listings.auction_type_vch = 'Dutch')
            AND listings.finished=0
            ");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

    static function findByNumber($db, $dbr, $type, $number)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Listing::findNotFinishedFixPrice expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getAll("
            SELECT listings.*, offer.name as offer_name 
            FROM listings LEFT JOIN offer ON listings.offer_id = offer.offer_id
            WHERE listings.auction_number=$number 
            ");
			//and listings.auction_type_vch='$type'
        if (PEAR::isError($r)) {
           print_r($r);
            $this->_error = $r;
            return;
        }
        return $r;
    }

    function addComment($comment, $username, $date) 
    {
     return $this->_db->query("insert into auction_comment SET
     	    auction_number=".$this->data->auction_number.",
     	    txnid=0,
     	    `comment`='$comment',
	    create_date='$date', 
	    username='$username'");
    }

}
?>