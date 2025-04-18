<?php
require_once 'PEAR.php';

		require_once 'lib/Warehouse.php';
		require_once 'lib/Article.php';
		require_once 'lib/Auction.php';
		require_once 'lib/Rma.php';
		require_once 'lib/Rma_Spec.php';
		require_once 'File/PDF.php';
		require_once 'HTTP/Download.php';
		require_once 'Archive/Tar.php';
		require_once 'lib/Order.php';
		require_once 'lib/Payment.php';

class Invoice
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    function Invoice($db, $dbr, $number = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Invoice::Invoice expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $number = (int)$number;
        if (!$number) {
            $r = $this->_db->query("EXPLAIN invoice");
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
			$q = "SELECT invoice.*, invoice_static.static, invoice_static.static_html 
				, invoice_static.static_shipping_list, invoice_static.static_shipping_list_html
				FROM invoice 
				left JOIN invoice_static ON invoice_static.invoice_number = invoice.invoice_number
				WHERE invoice.invoice_number=$number";
            $r = $this->_db->query($q);
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
				echo $q;
                $this->_error = PEAR::raiseError("Rule::Rule : record $id does not exist");
                return;
            }
            
            $this->data->static = get_file_path($this->data->static);
            $this->data->static_html = get_file_path($this->data->static_html);
            $this->data->static_shipping_list = get_file_path($this->data->static_shipping_list);
            $this->data->static_shipping_list_html = get_file_path($this->data->static_shipping_list_html);
            
            $this->_isNew = false;
			$subinvoices = $dbr->getRow("select count(*) master, sum(total_price) total_price
				, sum(total_shipping) total_shipping
				, sum(total_cod) total_cod
				from invoice 
				join auction on auction.invoice_number=invoice.invoice_number
				join auction mauction on mauction.auction_number=auction.main_auction_number
					and mauction.txnid=auction.main_txnid
				where mauction.invoice_number=$number
				");
			if ($subinvoices->master) {
				//$this->data += $subinvoices->total_price;
				$this->data->total_shipping += $subinvoices->total_shipping;
				$this->data->total_cod += $subinvoices->total_cod;
			}
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
            $this->_error = PEAR::raiseError('Invoice::update : no data');
        }
        if ($this->_isNew) {
            $this->data->invoice_number = '';
        }
        
        foreach ($this->data as $field => $value) {
            if (strpos($field, 'static')===0) continue;
            if ($field=='invoice_number' && $this->_isNew) continue;
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
            $where = "WHERE invoice_number='" . $this->data->invoice_number . "'";
        }
        $r = $this->_db->query("$command invoice SET $query $where");
        if (PEAR::isError($r)) {
			aprint_r($r);
			print_r($r);
			print_r($this);
			die();
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->invoice_number = mysql_insert_id();
			if (!$this->data->invoice_number) 
				$this->data->invoice_number = $this->_db->getOne("select max(invoice_number) from invoice");
        }
//	for static
        
        $query_static = [];
        foreach ($this->data as $field => $value) {
            if (strpos($field, 'static')===0) {
                $md5 = md5($value);
                $filename = set_file_path($md5);
                if ( ! is_file($filename)) {
                    file_put_contents($filename, $value);
                }

                $query_static[] = "`$field`='$md5'";
			} elseif ($field=='invoice_number') {
                $value = mysql_real_escape_string($value);
                $query_static[] = "`$field`='$value'";
			} 
        }
        $query_static = implode(', ', $query_static);
        
        $r = $this->_db->query("$command invoice_static SET $query_static $where");
        if (PEAR::isError($r)) {
			aprint_r($r);
			aprint_r($this);
			die();
            $this->_error = $r;
        }
        
        return $r;
    }
	
function make_static($db, $dbr, $auction_number, $txnid, $english, $outside_invoice=0, $shipping_list=0, $confirm=0) {
	return; // we dont save PDF anymore 2011-12-14
} // make_static

function make_static_shipping_list($db, $dbr, $auction_number, $txnid, $english, $outside_invoice=0) {
	$this->make_static($db, $dbr, $auction_number, $txnid, $english, 0, 1);
} // make_static

function make_static_HTML($db, $dbr, $auction_number, $txnid, $print_shipping_list=0, $nosent=0, $cant_wait=1) {
	global $siteURL;
	global $loggedUser;
	if (is_a($loggedUser,'User')) {
		$user = "&username=".urlencode($loggedUser->get('username'));
	}
	if ($cant_wait) {
		$static = '';
		$file = fopen ($siteURL."static_invoice_HTML.php?number=".$auction_number."&txnid=".$txnid."&nosent=".$nosent."&shipping_list=".$print_shipping_list.$user, "r");
		if (!$file) {
		    $static .=  "<p>Unable to open remote file.\n";
			echo 'nofile '.$siteURL."static_invoice_HTML.php?number=".$auction_number."&txnid=".$txnid."&nosent=".$nosent."&shipping_list=".$print_shipping_list.$user;
		    exit;
		}
		while (!feof ($file)) {
		    $static .= fgets ($file, 1024);
		}
	    fclose($file);
	}else{
		$static = '';
	}
    
	if ($print_shipping_list)
		$this->data->static_shipping_list_html = $static;
	else	
		$this->data->static_html = $static;
	$this->update();
} // make_static HTML

function make_static_shipping_list_HTML($db, $dbr, $auction_number, $txnid, $nosent=0, $cant_wait=1) {
	$this->make_static_HTML($db, $dbr, $auction_number, $txnid, 1, $nosent, $cant_wait);
} // make_static HTML

function getConfirmationPDF($db, $dbr, $auction_number, $txnid) {
	global $siteURL;
	global $loggedUser;
	global $smarty;
	if (is_a($loggedUser,'User')) {
		$user = "&username=".urlencode($loggedUser->get('username'));
		$username = $loggedUser->get('username');
	}
	$number=$auction_number;
	$txnid=$txnid;
	$shipping_list=0;
	$confirm=1;
	$static='';
	$fetch = 1;
	include 'static_invoice_HTML.php';
//	$html = file_get_contents($siteURL."static_invoice_HTML.php?number=$auction_number&txnid=$txnid&shipping_list=0&confirm=1&static=".$user);
	if (isset($_GET['html'])) die($html);
		require_once("dompdf/dompdf_config.inc.php");
		$dompdf = new DOMPDF();
		$dompdf->set_paper('A4', 'portrait');
		$dompdf->load_html(($html));
		$dompdf->render();
//		$dompdf->stream("static.pdf");
		return $dompdf->output();
} // make_static HTML

function getInvoicePDF($db, $dbr, $auction_number, $txnid) {
	global $siteURL;
	global $loggedUser;
	global $debug;
	if (is_a($loggedUser,'User')) {
		$user = "&username=".urlencode($loggedUser->get('username'));
	}
$time = getmicrotime();
if ($debug) { echo 'Inv 0: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	$auction = new Auction($db, $dbr, $auction_number, $txnid);
if ($debug) { echo 'Inv 1: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	$invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));
if ($debug) { echo 'Inv 2: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
if ($debug) { echo $invoice->data->invoice_number.'<br>';}
	if (0 && strlen($invoice->data->static)
			&& $auction->get('delivery_date_real')!='0000-00-00 00:00:00') {
		return $invoice->data->static;
	}
if ($debug) { echo 'Inv 3: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	if (!strlen($invoice->data->static_html)
		|| $auction->get('delivery_date_real')=='0000-00-00 00:00:00') {
		$invoice->data->static_html = file_get_contents($siteURL."static_invoice_HTML.php?number=$auction_number&txnid=$txnid"."&username=".$user);
	}
if ($debug) { echo 'Inv 4: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	if (isset($_GET['html'])) die($invoice->data->static_html);
	require_once("dompdf/dompdf_config.inc.php");
	$dompdf = new DOMPDF();
	$dompdf->set_paper('A4', 'portrait');
	$dompdf->load_html($invoice->data->static_html);
	$dompdf->render();
if ($debug) { echo 'Inv 5: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	$res = $dompdf->output();
	if (0 && $auction->get('delivery_date_real')!='0000-00-00 00:00:00') {
		$invoice->set('static', $res);
		$invoice->update();
	}
	return $res;
} // make_static HTML

function getShippingListPDF($db, $dbr, $auction_number, $txnid, $nosent=0, $print=0) {
	global $siteURL;
	global $loggedUser;
	global $debug;
	if (is_a($loggedUser,'User')) {
		$user = "&username=".urlencode($loggedUser->get('username'));
	}
$time = getmicrotime();
if ($debug) { echo 'Inv 0: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	$auction = new Auction($db, $dbr, $auction_number, $txnid);
if ($debug) { echo 'Inv 1: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	$invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));
if ($debug) { echo 'Inv 3: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	if (strlen($invoice->data->static_shipping_list)
			&& $auction->get('delivery_date_real')!='0000-00-00 00:00:00') {
		return $invoice->data->static_shipping_list;
	}
if ($debug) { echo 'Inv 4: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	if (!strlen($invoice->data->static_shipping_list_html)
		|| $auction->get('delivery_date_real')=='0000-00-00 00:00:00') {
		$invoice->data->static_shipping_list_html = file_get_contents($siteURL."static_invoice_HTML.php?number=$auction_number&txnid=$txnid&nosent=$nosent&shipping_list=1&print=$print&username=".$user);
	}
if ($debug) { echo 'Inv 5: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
//	echo base64_decode($invoice->data->static_shipping_list_html); die();
	if (isset($_GET['html'])) {
        echo $invoice->data->static_shipping_list_html;
        exit;
    }
	require_once("dompdf/dompdf_config.inc.php");
	$dompdf = new DOMPDF();
	$dompdf->set_paper('A4', 'portrait');
	$dompdf->load_html($invoice->data->static_shipping_list_html);
	$dompdf->render();
if ($debug) { echo 'Inv 6: '.round((getmicrotime()-$time),3).'<br>'; $time = getmicrotime();}
	$res = $dompdf->output();
	if ($auction->get('delivery_date_real')!='0000-00-00 00:00:00') {
		$invoice->set('static_shipping_list', $res);
		$invoice->update();
	}
	return $res;
} // make_static HTML

function getShippingListPDFs($db, $dbr, $ids, $nosent=0) {
	global $siteURL;
	global $loggedUser;
	if (is_a($loggedUser,'User')) {
		$user = "&username=".urlencode($loggedUser->get('username'));
	}
        
	$htmls = array();
	foreach($ids as $id) {
	  	list($auction_number, $txnid) = explode('_', $id);
		$auction = new Auction($db, $dbr, $auction_number, $txnid);
		$invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));
		if (0 && strlen($invoice->data->static_shipping_list)
				&& $auction->get('delivery_date_real')!='0000-00-00 00:00:00') {
			$htmls[] = $invoice->data->static_shipping_list;
			continue;
		}
		if (1 || !strlen($invoice->data->static_shipping_list_html)
			|| $auction->get('delivery_date_real')=='0000-00-00 00:00:00') {
			$invoice->data->static_shipping_list_html = file_get_contents($siteURL."static_invoice_HTML.php?number=$auction_number&txnid=$txnid&nosent=$nosent&shipping_list=1&username=".$user);
		}
		$htmls[] = $invoice->data->static_shipping_list_html;
	}
	$html = $htmls[0];
	foreach($htmls as $k=>$r) {
		if (!$k) continue;
		$htmls[$k] = substr($htmls[$k], strpos($htmls[$k], '<body>')+6);
		$htmls[$k] = substr($htmls[$k], 0, strpos($htmls[$k], '</body>'));
		$html = str_replace('</body>', '<p style="page-break-before: always">&nbsp;</p> '.$htmls[$k].'</body>', $html);
	}
//	echo base64_decode($invoice->data->static_shipping_list_html); die();
//	echo $html; die();
	require_once("dompdf/dompdf_config.inc.php");
	$dompdf = new DOMPDF();
	$dompdf->set_paper('A4', 'portrait');
	$dompdf->load_html($html);
	$dompdf->render();
	return $dompdf->output();
} // make_static HTML

function recalcOpenAmount() {
	$q = "update invoice set open_amount=IFNULL(fget_ITotal(invoice_number),0)
	-IFNULL((select sum(p.amount) from payment p join auction a 
		where a.auction_number=p.auction_number and a.txnid=p.txnid and a.invoice_number=invoice.invoice_number and rate=0),0) 
	where invoice_number=".$this->data->invoice_number;
#	echo $q.'<br>'; if ($this->data->invoice_number==96658) die();
	$r = $this->_db->query($q);
	if (PEAR::isError($r)) aprint_r($r);

	$q = "update auction set paid=IF(
		(select open_amount from invoice where invoice_number=".$this->data->invoice_number.")>0,0,1)
	where invoice_number=".$this->data->invoice_number;
//	echo $q.'<br>';
	$r = $this->_db->query($q);
	if (PEAR::isError($r)) aprint_r($r);
}

function getTotal() {
	$r = $this->_dbr->getOne("select fget_ITotal(".$this->data->invoice_number.")");
	if (PEAR::isError($r)) aprint_r($r);
	return $r;
}
}
?>