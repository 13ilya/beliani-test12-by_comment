<?php

/**
 * 
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'lib/Rma_Spec.php';
require_once 'lib/Rma.php';
require_once 'lib/Auction.php';
require_once 'lib/Insurance.php';
require_once 'lib/Payment.php';

require_once 'util.php';

require_once 'PEAR.php';

/**
 * Ticket
 * @package eBay_After_Sale
 */
class Rating
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
    function Rating($db, $dbr, $id = 0, $auction_number = 0, $txnid = 0, $timediff = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Rating::Rating expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
		$r = $this->_dbr->getOne("select id from rating where auction_number=$auction_number and txnid=$txnid");
		if ($r) $id=(int)$r;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN rating");
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
			$auction = new Auction ($db, $dbr, $auction_number, $txnid);
            if ((!PEAR::isError($auction)) && (!PEAR::isError($rma))) {
				$this->data->auction_number = $auction_number;
				$this->data->txnid = $txnid;
				$this->data->date = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
				$this->update();
				$this->_isNew = false;
			}
        } else {
            $r = $this->_db->query("SELECT * FROM rating WHERE id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Rating::Rating : record $id does not exist");
                return;
            }
            $this->data->resolve_username = $this->_dbr->getOne("select IFNULL(name, '".$this->data->resolve_username."') 
	    				  from users where username='".$this->data->resolve_username."'");
			$this->comments = Rating::getComments($db, $dbr, $id);
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
            $this->_error = PEAR::raiseError('Rating::update : no data');
        }
        foreach ($this->data as $field => $value) {
//			if (isset($value)||) 
			{
				if ($field=='id') continue;
	            if ($query) {
	                $query .= ', ';
	            }
	            if ((($value!='' || $value=='0') && $value!=NULL))
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
        $r = $this->_db->query("$command rating SET $query $where");
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
            $this->_error = PEAR::raiseError('Rating::update : no data');
        }
		$id = (int)$this->data->id;
		$this->_db->query("DELETE FROM rating_comment WHERE rating_id=$id");
        $r = $this->_db->query("DELETE FROM rating WHERE id=$id");
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
    static function listAll($db, $dbr, $auction_number, $txnid)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT * FROM rating WHERE auction_number=$auction_number and txnid=$txnid order by id");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($obj = $r->fetchRow()) {
			$obj->comments = Rating::getComments($db, $dbr, $obj->id);
            $list[] = $obj;
        }
        return $list;
    }

    static function getComments($db, $dbr, $id)
    {
		$q = "select t.*
			, u.deleted, IF(u.name is null, 1, 0) olduser
			, IFNULL(u.name, t.username) username, t.username cusername 
			, IFNULL(u.name, t.username) full_username
			, IFNULL(u.name, t.username) username_name
		FROM ( 
		SELECT null as prefix, rating_comment.id, rating_comment.create_date, rating_comment.username, rating_comment.comment, rating_comment.src 
			from rating_comment 
			where rating_comment.rating_id=$id
		UNION ALL
		SELECT CONCAT('Ticket #', rma_comment.rma_id, ': ') as prefix, NULL as id, rma_comment.create_date, rma_comment.username, rma_comment.comment, rma_comment.src
			from rma_comment JOIN rma ON rma_comment.rma_id = rma.rma_id 
			JOIN rating ON rma.auction_number = rating.auction_number AND rma.txnid = rating.txnid
			where rating.id=$id
		UNION ALL
		select CONCAT('Alarm (',alarms.status,'):') as prefix
			, NULL as id
			, (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
			, alarms.username
			, alarms.comment
			, '' src
			from rating
			join alarms on alarms.type='rating' and alarms.type_id=rating.id
			where rating.id=$id
		) t LEFT JOIN users u ON t.username=u.username
		ORDER BY t.create_date";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
		$article->comment_safe = strip_tags(str_replace("\n",'',str_replace("\r",'',str_replace('"', "``", str_replace("'", "`", str_replace("
", '', $article->comment))))));
            $list[] = $article;
        }
        return $list;
    }

    static function addComment($db, $dbr, $id,
		$username,
		$create_date,
		$comment, $src=''
		)
    {
        $id = (int)$id;
		$username = mysql_escape_string($username);
		$create_date = mysql_escape_string($create_date);
		$comment = mysql_escape_string($comment);
        $src = mysql_escape_string($src);
        $r = $db->query("insert into rating_comment set 
			rating_id=$id, 
			username='$username',
			create_date='$create_date',
			comment='$comment',
            src='$src'");
    }

    static function getPaymentPDF($db, $dbr, $rating_id, $payment_id) 
    {
        require_once __DIR__ . '/../plugins/function.barcodeurl.php';
        
	global $siteURL;
	global $english;
	global $smarty;
	$sol = $dbr->getRow("select * FROM payment
	    where payment_id=$payment_id");
    if (PEAR::isError($sol)) {
				aprint_r($sol);
    }
 	$auction_number = $sol->auction_number;
 	$txnid = $sol->txnid;   
	$auction = new Auction($db, $dbr, $auction_number, $txnid);
	$payments = Payment::findIds($db, $dbr, "'5-$payment_id-$payment_id//'");
	$payment = $payments[0];
	$sellerInfo = SellerInfo::singleton($db, $dbr, $auction->get('username'));
	if ($outside_invoice) {
	    $outside_addon = nl2br(trim(htmlspecialchars(substitute(substitute($sellerInfo->getTemplate('outside_invoice_addon'
			, $auction->getMyLang()/*SiteToCountryCode($auction->get('siteid'))*/), $auction->data),$sellerInfo->data))));
    	}
    $auctiondata = $auction->data;
    $auctiondata->seller_email = $sellerInfo->get('email');
	$auctiondata->vat_id = $sellerInfo->get('vat_id');
	if (strlen($sellerInfo->getTemplate('invoice_footer'
			, $auction->getMyLang()/*SiteToCountryCode($auction->get('siteid'))*/))) {
	    $sellerInfo_invoice_footer = nl2br(trim(htmlspecialchars(
			substitute($sellerInfo->getTemplate('invoice_footer'
				, $auction->getMyLang()/*SiteToCountryCode($auction->get('siteid'))*/), $auctiondata))));
	    $sellerInfo_invoice_footer = substitute($sellerInfo_invoice_footer, $sellerInfo->data);
	};	
    	$currency = siteToSymbol($auction->data->siteid);

	$tmp = 'tmp';
    	$filename = 'export.pdf';

        $pdf = &File_PDF::factory('P', 'cm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(true);
	    $pdf->addPage();
        $pdf->setFillColor('rgb', 0, 0, 0);
        $pdf->setDrawColor('rgb', 0, 0, 0);
        $pdf->setFont('arial','B', 8);
		$pdf->setLeftMargin(2);
		$y=1;
		ini_set('display_errors', 'off');
		$logo = imagecreatefromjpeg ($sellerInfo->get('logo_url'));
		if ($logo) {
			imagejpeg ( $logo, 'tmppic/tmplogo.jpg' );
	        $pdf->image('tmppic/tmplogo.jpg', 1, $y);
			unlink('tmppic/tmplogo.jpg');
		};
		ini_set('display_errors', 'on');
		$y+=0.5;
        $pdf->text(10, $y, $english[60]);
        $pdf->setFont('arial','', 8);
		$y+=0.5;
        $pdf->text(10, $y, $auction->get('company_shipping'));
		$y+=0.5;
        $pdf->text(10, $y, $english[$auction->get('gender_shipping')].' '.$auction->get('firstname_shipping').' '.$auction->get('name_shipping'));
		$y+=0.5;
        $pdf->text(10, $y, $auction->get('street_shipping').' '.$auction->get('house_shipping'));
		$y+=0.5;
        $pdf->text(10, $y, $auction->get('zip_shipping').' '.$auction->get('city_shipping'));
		$y+=0.5;
        $pdf->text(10, $y, $auction->get('country_shipping'));

        $pdf->setFont('arial','B', 8);
		$y+=1.5;
        $pdf->text(1, $y, $english[61]); $pdf->text(10, $y, $english[62]);
        $pdf->setFont('arial','', 8);
		$y+=0.5;
        $pdf->text(1, $y, $auction->get('company_invoice')); $pdf->text(10, $y, $sellerInfo->get('seller_name'));
		$y+=0.5;
        $pdf->text(1, $y, $english[$auction->get('gender_invoice')].' '.$auction->get('firstname_invoice').' '.$auction->get('name_invoice')); $pdf->text(10, $y, $sellerInfo->get('street'));
		$y+=0.5;
        $pdf->text(1, $y, $auction->get('street_invoice').' '.$auction->get('house_invoice')); $pdf->text(10, $y, $sellerInfo->get('zip').' '.$sellerInfo->get('town'));
		$y+=0.5;
        $pdf->text(1, $y, $auction->get('zip_invoice').' '.$auction->get('city_shipping'));  $pdf->text(10, $y, $sellerInfo->get('country_name'));
		$y+=0.5;
        $pdf->text(1, $y, $auction->get('country_invoice'));
		
        $pdf->setFont('arial','B', 12);
		$y+=1.5;
		ini_set('display_errors', 'off');
		$barcode = imagecreatefrompng ($siteURL.smarty_function_barcodeurll([
            'number' => $auction->get('auction_number') . '/' . $auction->get('txnid'), 
            'type' => 'int25'], $smarty));
		if ($barcode) {
			imagejpeg ( $barcode, 'tmppic/tmpbarcode.jpg' );
	        $pdf->text(1, $y, $english[63]); $pdf->image('tmppic/tmpbarcode.jpg', 10, $y-1);
			unlink('tmppic/tmpbarcode.jpg');
		};	
		ini_set('display_errors', 'on');
		
		$y+=1.5;
        $pdf->setFont('arial','B', 8); $pdf->text(1, $y, $english[64]); 
	    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\s]+/', $auction->get('end_time'));
		$pdf->setFont('arial','', 8); $pdf->text(5, $y, date('d F Y', mktime($h,$m,$s,$M,$D,$Y))); 
        $pdf->setFont('arial','B', 8); $pdf->text(8, $y, $english[65]); 
		$pdf->setFont('arial','', 8); $pdf->text(11, $y, $auction->get('auction_number')); 
        $pdf->setFont('arial','B', 8); $pdf->text(15, $y, $english[66]); 
		$pdf->setFont('arial','', 8); $pdf->text(18, $y, 'RATING '.$payment->payment_id); 

		$y+=0.5;
        $pdf->setFont('arial','B', 8); $pdf->text(1, $y, $english[67]); 
		$pdf->setFont('arial','', 8); $pdf->text(5, $y, $auction->get('tel_invoice')); 
	if ($auction->get('tel_shiping')) {
        $pdf->setFont('arial','B', 8); $pdf->text(8, $y, $english[68]); 
		$pdf->setFont('arial','', 8); $pdf->text(11, $y, $auction->get('tel_shiping')); 
	};	
        $pdf->setFont('arial','B', 8); $pdf->text(15, $y, $english[69]); 
	    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\s]+/', $payment->payment_date);
		$pdf->setFont('arial','', 8); $pdf->text(18, $y, date('d F Y', mktime($h,$m,$s,$M,$D,$Y))); 

		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setFont('arial','B', 8); 
        $pdf->setXY(1, $y);	$pdf->multiCell(2, 0.5, str_replace ( '<br>', "\n", $english[70]), 0, 'L');
        $pdf->setXY(3, $y);	$pdf->multiCell(7, 0.5, str_replace ( '<br>', "\n", $english[71]), 0, 'L');
        $pdf->setXY(10, $y);	$pdf->multiCell(3, 0.5, str_replace ( '<br>', "\n", $english[72]), 0, 'L');
        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, str_replace ( '<br>', "\n", $english[73]), 0, 'L');
        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, str_replace ( '<br>', "\n", $english[74]), 0, 'L');
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, str_replace ( '<br>', "\n", $english[75]), 0, 'L');
		$y+=1;
		$pdf->line(1, $y, 20, $y);

		$y+=0.2;
        $pdf->setFont('arial','', 8); 
		$newy = $y;
	        $pdf->setXY(1, $y);	$pdf->multiCell(2, 0.5, 1, 0, 'L');
			$title = 'Money paid/refund on '.$payment->payment_date.' for Rating case '.$rating_id.'.';
	        $pdf->setXY(3, $y);	$pdf->multiCell(7, 0.5, $title, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, $currency.' '.$payment->amount, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, (strlen($payment->vat_percent)?$payment->vat_percent:0).'%', 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.($payment->amount), 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();

		$y = $newy;
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[77]), 0, 'L');
		$vatvalue =  sprintf("%01.2f", (($payment->amount) / (100 + $payment->vat_percent) * $payment->vat_percent));
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.$vatvalue, 0, 'L');
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setFont('arial','B', 8); 
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[78]), 0, 'L');
        $pdf->setFont('arial','', 8); 
		$price =  sprintf("%01.2f", ($payment->amount));
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.$price, 0, 'L');
		if ($pdf->getY()>$y) $y = $pdf->getY();
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);

		$y = $pdf->getY();
		$y+=0.5;
        $pdf->setFont('arial','', 8); 
		$sellerInfo_invoice_footer = str_replace ( '<br>', '', $sellerInfo_invoice_footer);
		$sellerInfo_invoice_footer = str_replace ( '<br />', '', $sellerInfo_invoice_footer);
        $pdf->setXY(1, $y);	$pdf->multiCell(20, 0.5, $sellerInfo_invoice_footer, 0, 'L');
		if ($outside_addon) {
			$y = $pdf->getY();
			$y+=1;
			$outside_addon = str_replace ( '<br>', '', $outside_addon);
			$outside_addon = str_replace ( '<br />', '', $outside_addon);
	        $pdf->setXY(1, $y);	$pdf->multiCell(20, 0.5, $outside_addon, 0, 'L');
		};	

        $pdf->close();
	$pdf->save($tmp . '/' . $filename, true);
	$file = file_get_contents($tmp . '/' . $filename);
	unlink($tmp . '/' . $filename);
	return $file;
    }
}
?>