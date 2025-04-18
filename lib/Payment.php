<?php
require_once 'PEAR.php';

class Payment
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;

    function Payment($db, $dbr, $id = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Payment::Payment expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN payment");
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
            $r = $this->_db->query("SELECT * FROM payment WHERE payment_id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Payment::Payment : record $id does not exist");
                return;
            }
            $this->_isNew = false;
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

    static function find($db, $dbr, $criteria)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $where = array();
            $where1 = '';
            $where2 = '';
			$where3 = '';
			$join = '';
        if ($criteria['name']) {
            $where[] = "CONCAT(tr.value,' ',au_firstname_invoice.value,' ',au_name_invoice.value) like '%" . mysql_real_escape_string($criteria['name']) . "%'";
        }
        if ($criteria['minamount']) {
            $where[] = "payment.amount >='" . mysql_real_escape_string($criteria['minamount']) . "'";
        }
        if ($criteria['maxamount']) {
            $where[] = "payment.amount <='" . mysql_real_escape_string($criteria['maxamount']) . "'";
        }
        if ($criteria['mindatei']) {
            $where1 .= " and invoice.invoice_date >='" . mysql_real_escape_string($criteria['mindatei']) . "'";
            $where2 .= " and invoice.invoice_date >='" . mysql_real_escape_string($criteria['mindatei']) . "'";
            $where3 .= " and 0";
        }
        if ($criteria['maxdatei']) {
            $where1 .= " and invoice.invoice_date <='" . mysql_real_escape_string($criteria['maxdatei']) . "'";
            $where2 .= " and invoice.invoice_date <='" . mysql_real_escape_string($criteria['maxdatei']) . "'";
            $where3 .= " and 0";
        }
        if ($criteria['mindate']) {
            $where1 .= " and date(payment.payment_date) >='" . mysql_real_escape_string($criteria['mindate']) . "'";
            $where2 .= " and payment.invoice_date >='" . mysql_real_escape_string($criteria['mindate']) . "'";
            $where3 .= " and date(payment.payment_date) >='" . mysql_real_escape_string($criteria['mindate']) . "'";
        }
        if ($criteria['maxdate']) {
            $where1 .= " and date(payment.payment_date) <='" . mysql_real_escape_string($criteria['maxdate']) . "'";
            $where2 .= " and payment.invoice_date <='" . mysql_real_escape_string($criteria['maxdate']) . "'";
            $where3 .= " and date(payment.payment_date) <='" . mysql_real_escape_string($criteria['maxdate']) . "'";
        }
        if ($criteria['country']) {
            $where[] = "au_country_shipping.value ='" . mysql_real_escape_string($criteria['country']) . "'";
        }
        if ($criteria['username']) {
            $where[] = "auction.username in (" . $criteria['username'] . ")";
        }
        if ($criteria['state'] != 2 /*&& (!isset($criteria['paid_status']) || $criteria['paid_status'] == 1)*/) {
            $where1 .= " and IFNULL(payment.exported, auction.payment_export) = " . ($criteria['state']==1 ? 1 : 0);
            $where2 .= " and payment.exported = " . ($criteria['state']==1 ? 1 : 0);
            $where3 .= " and payment.exported = " . ($criteria['state']==1 ? 1 : 0);
        }
		if ($criteria['inv_status'] != 2) {
			$inv_where = " and auction.deleted = " . ($criteria['inv_status'] == 1 ? 1 : 0);
			$where1 .= $inv_where;
			$where2 .= $inv_where;
		}
		if (isset($criteria['paid_status'])) {
			$paid_where = " and auction.paid = " . ($criteria['paid_status'] == 1 ? 1 : 0);
			$where1 .= $paid_where;
			$where2 .= $paid_where;
		}
        if ($criteria['account']) {
//            $where1 .= " and IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account) =" 
//				. mysql_real_escape_string($criteria['account']);
//            $where2 .= " and IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number) =" 
//				. mysql_real_escape_string($criteria['account']);
            $where1 .= " and (payment.selling_account_number = ". mysql_real_escape_string($criteria['account'])
					. " or payment.account = " . mysql_real_escape_string($criteria['account']). ")";
            $where2 .= " and (payment.selling_account_number = ". mysql_real_escape_string($criteria['account'])
					. " or payment.account = " . mysql_real_escape_string($criteria['account']). ")";
            $where3 .= " and (payment.selling_account_number = ". mysql_real_escape_string($criteria['account'])
					. " or payment.account = " . mysql_real_escape_string($criteria['account']). ")";
        } 
        if (count($where)) {
            $where = ' and ' . implode(' AND ', $where);
        } else {
            $where = '';
        }
        $q = "select * from (SELECT
	1 as mode,
	IF(payment.rate IS NOT NULL and 0, CONCAT('RATED ', (select max(id) from rating 
		where auction_number=payment.auction_number AND txnid=payment.txnid)), 
		CONCAT(auction.auction_number, ' / ', auction.txnid)) number,
	payment.payment_id,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account)  account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)  selling_account,
	ABS(payment.amount) amount,
	payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type, 
	payment.payment_date,
	payment.exported,
	payment.listingfee,
	payment.username,
	payment.comment,
	`payment_saferpay`.`txn_id` AS `transaction_id`,
	auction.auction_number,
	auction.txnid,
	au_country_shipping.value country_shipping,
	NULL as ins_id,
	NULL as rma_spec_sol_id, 
	NULL as rma_id, 
	CONCAT(tr.value,' ',au_firstname_invoice.value,' ',au_name_invoice.value) as name_invoice,	
	auction.username ausername,
	IF(payment.rate IS NOT NULL and 0, (select max(id) from rating 
		where auction_number=payment.auction_number AND txnid=payment.txnid), NULL) rating_case_id
	, invoice.invoice_date
  , ".$criteria['paid_status']." paid_status
      , si.defshcountry
	FROM payment
        JOIN auction ON auction.auction_number=payment.auction_number AND auction.txnid=payment.txnid
        LEFT JOIN `payment_saferpay` ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` AND `payment_saferpay`.`txnid` = `auction`.`txnid`
		JOIN invoice on invoice.invoice_number=auction.invoice_number
		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		left join auction_par_varchar au_gender_invoice on auction.auction_number=au_gender_invoice.auction_number
			and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'	
		left join seller_information si on auction.username=si.username
		left join translation tr on tr.table_name='translate' and tr.field_name='translate'
			and tr.id=au_gender_invoice.value and tr.language=si.default_lang
	WHERE 1=1 $seller_filter_str $where $where1
	UNION ALL
	SELECT 
	2 as mode,
	CONCAT('INS ',insurance.id) number,
	payment.payment_id,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account)  account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)  selling_account,
	ABS(payment.amount) amount,
    payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type, 
	payment.payment_date,
	payment.exported,
	payment.listingfee,
	payment.username,
	payment.comment,
    `payment_saferpay`.`txn_id` AS `transaction_id`,
	auction.auction_number,
	auction.txnid,
	au_country_shipping.value country_shipping,
	insurance.id as ins_id,
	NULL as rma_spec_sol_id, 
	NULL as rma_id, 
	CONCAT(tr.value,' ',au_firstname_invoice.value,' ',au_name_invoice.value) as name_invoice,
	auction.username ausername,
	NULL rating_case_id
	, invoice.invoice_date
  , ".$criteria['paid_status']." paid_status
      , si.defshcountry
	FROM ins_payment payment JOIN insurance ON payment.ins_id=insurance.id
        JOIN auction ON auction.auction_number=insurance.auction_number AND auction.txnid=insurance.txnid
        LEFT JOIN `payment_saferpay` ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` AND `payment_saferpay`.`txnid` = `auction`.`txnid`
		JOIN invoice on invoice.invoice_number=auction.invoice_number
		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		left join auction_par_varchar au_gender_invoice on auction.auction_number=au_gender_invoice.auction_number
			and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'	
		left join seller_information si on auction.username=si.username
		left join translation tr on tr.table_name='translate' and tr.field_name='translate'
			and tr.id=au_gender_invoice.value and tr.language=si.default_lang
	WHERE 1=1 $seller_filter_str  $where $where1
        UNION ALL
	SELECT 
	3 as mode,
	CONCAT('CREDIT ',auction.invoice_number,' TICKET ',r.rma_id) number,
	NULL AS payment_id, 
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number) account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account) selling_account,
	ABS(IFNULL(payment.amount, 0)) as amount, 
    payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type, 
	payment.invoice_date AS payment_date, 
	payment.exported, 
	NULL AS listingfee, 
	NULL AS username, 
	NULL AS COMMENT , 
    `payment_saferpay`.`txn_id` AS `transaction_id`,
	auction.auction_number, 
	auction.txnid, 
	au_country_shipping.value country_shipping,
	NULL AS ins_id, 
	payment.rma_spec_sol_id, 
	r.rma_id, 
	CONCAT(tr.value,' ',au_firstname_invoice.value,' ',au_name_invoice.value) as name_invoice,
	auction.username ausername,
	NULL rating_case_id
	, invoice.invoice_date
  , ".$criteria['paid_status']." paid_status
      , si.defshcountry
	FROM rma_spec_solutions payment
	JOIN rma_solution sol ON payment.solution_id = sol.solution_id
	AND sol.sol_type = 'Money'
	JOIN rma_spec rs ON payment.rma_spec_id = rs.rma_spec_id
	JOIN rma r ON r.rma_id = rs.rma_id
	JOIN auction ON auction.auction_number = r.auction_number AND auction.txnid = r.txnid
    LEFT JOIN `payment_saferpay` ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` AND `payment_saferpay`.`txnid` = `auction`.`txnid`
	JOIN invoice on invoice.invoice_number=auction.invoice_number
		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		left join auction_par_varchar au_gender_invoice on auction.auction_number=au_gender_invoice.auction_number
			and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'	
		left join seller_information si on auction.username=si.username
		left join translation tr on tr.table_name='translate' and tr.field_name='translate'
			and tr.id=au_gender_invoice.value and tr.language=si.default_lang
	WHERE 1=1 $seller_filter_str  $where $where2
	union all 
SELECT distinct
	4 as mode,
	'Voucher',
	payment.payment_id,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account)  account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)  selling_account,
	ABS(payment.amount) amount,
    payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type, 
	payment.payment_date,
	payment.exported,
	null listingfee,
	payment.username,
	payment.comment,
    '' AS `transaction_id`,
	payment.code_name auction_number,
	spc.shop_id txnid,
	null country_shipping,
	NULL as ins_id,
	NULL as rma_spec_sol_id, 
	NULL as rma_id, 
	null as name_invoice,	
	si.username ausername,
	null rating_case_id
	, null invoice_date
  , ".$criteria['paid_status']." paid_status
      , si.defshcountry
	FROM shop_promo_payment payment
		join shop_promo_codes spc on spc.name=payment.code_name
		join shop auction on auction.id=spc.shop_id
        left join seller_information si on auction.username=si.username
	WHERE 1=1 $seller_filter_str $where $where3
	) t        
	";
		// in case if "UnPAID" filter selected
		$q2 = "
SELECT 5 as mode, auction.auction_number,
  auction.txnid,
  payment.payment_date,
  invoice_date,
  CONCAT(tr.value, ' ', au_first_name_invoice.value, ' ', au_name_invoice.value) as name_invoice,
  payment.vat_account_number vat_account,
  IF(SIGN(payment.amount)=-1, payment.selling_account_number,
	IF(SIGN(payment.account)=1, payment.account,
	  CASE
		WHEN auction.payment_method='bill_shp' THEN si.bill_account
		WHEN auction.payment_method IN ('cc_pck', 'cc_shp') THEN si.cc_account
		WHEN auction.payment_method IN ('pp_pck', 'pp_shp') THEN si.paypal_account
		ELSE si.default_account
	  END
	)
  ) account,
  IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number) selling_account,
  #fget_ITotal(invoice.invoice_number) - invoice.open_amount amount,
  ABS(payment.amount) amount,
  IFNULL(payment.exported, auction.payment_export) exported,
  payment.payment_id
  , ".$criteria['paid_status']." paid_status
      , si.defshcountry
      , payment.amount sign_amount
      , IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type,
  payment.comment,
  `payment_saferpay`.`txn_id` AS `transaction_id`
FROM payment
  RIGHT JOIN auction ON auction.auction_number=payment.auction_number and auction.txnid=payment.txnid
  JOIN invoice ON auction.invoice_number=invoice.invoice_number
  LEFT JOIN auction_par_varchar au_name_invoice ON auction.auction_number=au_name_invoice.auction_number
    and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
  LEFT JOIN auction_par_varchar au_first_name_invoice ON auction.auction_number=au_first_name_invoice.auction_number
    and auction.txnid=au_first_name_invoice.txnid and au_first_name_invoice.key='firstname_invoice'
  LEFT JOIN auction_par_varchar au_gender_invoice ON auction.auction_number=au_gender_invoice.auction_number
    and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
  LEFT JOIN seller_information si ON auction.username=si.username
  LEFT JOIN translation tr ON tr.table_name='translate' and tr.field_name='translate'
    and tr.id=au_gender_invoice.value and tr.language=si.default_lang
  LEFT JOIN `payment_saferpay` 
    ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` 
    AND `payment_saferpay`.`txnid` = `auction`.`txnid`
WHERE auction.main_auction_number=0 $seller_filter_str $where $where1
		";
//	echo "<pre>$q2</pre>";
		//var_dump($q2);die();
		// in case if "UnPAID" filter selected then use show order amount
		if(isset($criteria['paid_status']) && $criteria['paid_status'] == 0) {
			$r = $dbr->getAll($q2);
		} else {
			$r = $dbr->getAll($q);
		}
        
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    static function findIds($db, $dbr, $ids, $sort='payment_date', $direction=1, $paid_status=1)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        
//        $ids = mysql_real_escape_string($ids);
		$ids_array = explode(',', $ids);
		if (!count($ids_array)) return;
		$payment_ids_array = array();
		foreach($ids_array as $k=>$r) { 
			list($mode, $payment_ids_array[$k], $ids_array[$k]) = explode('-',$r);
			$ids_array[$k] = "'".$ids_array[$k];
			if (!strlen($payment_ids_array[$k])) unset($payment_ids_array[$k]);
		}
		$ids = implode(',', $ids_array);
		$payment_ids = implode(',', $payment_ids_array);
		if (!strlen($payment_ids)) $payment_ids = 0;
		
	$q = "select * from (SELECT 
	IF(payment.rate IS NOT NULL and 0, CONCAT('RATED ', (select max(id) from rating 
		where auction_number=payment.auction_number AND txnid=payment.txnid)), 
		CONCAT(auction.auction_number, ' / ', auction.txnid)) number,
	payment.payment_id,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account)  account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)  vat_selling_account_number,
	IF(IFNULL(accounts.sesam_code,'')='', vat.sesam_code, accounts.sesam_code) sesam_code,
	ABS(payment.amount) amount,
    payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type,
	payment.payment_date,
	payment.exported,
	payment.listingfee,
	payment.username,
	payment.comment,
	`payment_saferpay`.`txn_id` AS `transaction_id`,
	auction.auction_number,
	auction.txnid,
	NULL as ins_id,
	NULL as rma_spec_sol_id, 
	CONCAT(tr.value,' ',au_firstname_invoice.value,' ',au_name_invoice.value) as name_invoice,
	au_email_invoice.value as email_invoice,
	au_email_shipping.value as email_shipping,
	au_company_invoice.value company_invoice,
	au_zip_invoice.value zip_invoice,
	au_country_invoice.value country_invoice,   
	au_city_invoice.value city_invoice,   
	au_house_invoice.value house_invoice,   
	au_street_invoice.value street_invoice,   
			payment.vat_percent, payment.vat_account_number,  
			seller_information.selling_account, seller_information.currency,
			auction.siteid
			, au_state_invoice.value state_invoice
			, au_state_shipping.value state_shipping
			, invoice.invoice_number, invoice.invoice_date
            , seller_information.defshcountry 
			, '$paid_status' paid_status
			FROM payment 
			left join accounts on accounts.number=IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)
			JOIN auction on payment.auction_number=auction.auction_number and payment.txnid=auction.txnid
		left join auction_par_varchar au_same_address on auction.auction_number=au_same_address.auction_number 
			and auction.txnid=au_same_address.txnid and au_same_address.key='same_address'	
			LEFT JOIN `payment_saferpay` ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` AND `payment_saferpay`.`txnid` = `auction`.`txnid`
			JOIN invoice on invoice.invoice_number=auction.invoice_number
		left join auction_par_varchar au_state_invoice on auction.auction_number=au_state_invoice.auction_number 
			and auction.txnid=au_state_invoice.txnid and au_state_invoice.key='state_invoice'
		left join auction_par_varchar au_state_shipping on auction.auction_number=au_state_shipping.auction_number 
			and auction.txnid=au_state_shipping.txnid and au_state_shipping.key='state_shipping'
		left join auction_par_varchar au_email_invoice on auction.auction_number=au_email_invoice.auction_number 
			and auction.txnid=au_email_invoice.txnid and au_email_invoice.key='email_invoice'
		left join auction_par_varchar au_email_shipping on auction.auction_number=au_email_shipping.auction_number 
			and auction.txnid=au_email_shipping.txnid and au_email_shipping.key='email_shipping'
		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		left join auction_par_varchar au_gender_invoice on auction.auction_number=au_gender_invoice.auction_number
			and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
		left join auction_par_varchar au_company_invoice on auction.auction_number=au_company_invoice.auction_number 
			and auction.txnid=au_company_invoice.txnid and au_company_invoice.key='company_invoice'
		left join auction_par_varchar au_zip_invoice on auction.auction_number=au_zip_invoice.auction_number 
			and auction.txnid=au_zip_invoice.txnid and au_zip_invoice.key='zip_invoice'
		left join auction_par_varchar au_country_invoice on auction.auction_number=au_country_invoice.auction_number 
			and auction.txnid=au_country_invoice.txnid and au_country_invoice.key='country_invoice'
		left join auction_par_varchar au_city_invoice on auction.auction_number=au_city_invoice.auction_number 
			and auction.txnid=au_city_invoice.txnid and au_city_invoice.key='city_invoice'
		left join auction_par_varchar au_house_invoice on auction.auction_number=au_house_invoice.auction_number 
			and auction.txnid=au_house_invoice.txnid and au_house_invoice.key='house_invoice'
		left join auction_par_varchar au_street_invoice on auction.auction_number=au_street_invoice.auction_number 
			and auction.txnid=au_street_invoice.txnid and au_street_invoice.key='street_invoice'
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key=IF(au_same_address.value,'country_invoice','country_shipping')	
			LEFT JOIN country on REPLACE(au_country_shipping.value, 'United Kingdom', 'UK')=country.name
			JOIN seller_information on seller_information.username=auction.username
			JOIN vat on vat.country_code=IF(auction.payment_method>2,seller_information.defshcountry
				,IFNULL(country.code,au_country_shipping.value)) 
				and DATE(payment.payment_date) between vat.date_from and vat.date_to
				and vat.country_code_from = seller_information.defshcountry
		left join seller_information si on auction.username=si.username
		left join translation tr on tr.table_name='translate' and tr.field_name='translate'
			and tr.id=au_gender_invoice.value and tr.language=si.default_lang
		WHERE CONCAT(payment_id, '///') in ($ids)
			$seller_filter_str

	      UNION ALL

        SELECT 
	CONCAT('INS ',insurance.id) number,
	payment.payment_id,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account)  account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)  vat_selling_account_number,
	IF(IFNULL(accounts.sesam_code,'')='', vat.sesam_code, accounts.sesam_code) sesam_code,
	ABS(payment.amount) amount,
    payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type,
	payment.payment_date,
	payment.exported,
	payment.listingfee,
	payment.username,
	payment.comment,
	`payment_saferpay`.`txn_id` AS `transaction_id`,
	auction.auction_number,
	auction.txnid,
	insurance.id as ins_id,
	NULL as rma_spec_sol_id, 
	method.name as name_invoice, 
	method.email as email_invoice, 
	method.email as email_shipping, 
	method.company as company_invoice,
	method.zip as zip_invoice,
	'' house_invoice,   
	method.city as city_invoice,   
	method.country as country_invoice,   
	method.street as street_invoice,   
			payment.vat_percent, payment.vat_account_number,  
			seller_information.selling_account, seller_information.currency
			,auction.siteid
			, '' state_invoice
			, '' state_shipping
			, invoice.invoice_number, invoice.invoice_date
            , seller_information.defshcountry
			, '$paid_status' paid_status
			FROM ins_payment payment JOIN insurance ON payment.ins_id=insurance.id
			left join accounts on accounts.number=IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)
			JOIN shipping_method method ON method.shipping_method_id=insurance.shipping_method 
			JOIN auction on insurance.auction_number=auction.auction_number and insurance.txnid=auction.txnid
			LEFT JOIN `payment_saferpay` ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` AND `payment_saferpay`.`txnid` = `auction`.`txnid`
			JOIN invoice on invoice.invoice_number=auction.invoice_number
			JOIN seller_information on seller_information.username=auction.username
			JOIN vat on vat.country_code='INS' 
				and DATE(payment.payment_date) between vat.date_from and vat.date_to
				and vat.country_code_from = seller_information.defshcountry
			WHERE CONCAT(payment_id, '/', insurance.id, '//') in ($ids)
			$seller_filter_str

	      UNION ALL

        SELECT 
	CONCAT('CREDIT ',auction.invoice_number,' TICKET ',r.rma_id) number,
	rss.rma_spec_sol_id as payment_id,
	IF(SIGN(rss.amount)=-1, rss.account, rss.selling_account_number) account,
	payment.vat_account_number vat_account,
	IF(SIGN(rss.amount)=-1, rss.selling_account_number, rss.account) vat_selling_account_number,
	#'' sesam_code,

    IF(IFNULL(accounts.sesam_code,'')='', vat.sesam_code, accounts.sesam_code) sesam_code,

	ABS(IFNULL(rss.amount, 0)) as amount, 
    rss.amount sign_amount,
    IF(SIGN(rss.amount)=-1, 'refund', 'payment') AS payment_type,
	rss.invoice_date AS payment_date, 
	rss.exported, 
	NULL AS listingfee, 
	NULL AS username, 
	NULL AS COMMENT ,
	`payment_saferpay`.`txn_id` AS `transaction_id`, 
	auction.auction_number,
	auction.txnid,
	NULL as ins_id,
	rss.rma_spec_sol_id, 
	CONCAT(tr.value,' ',au_firstname_invoice.value,' ',au_name_invoice.value) as name_invoice,
	au_email_invoice.value as email_invoice,
	au_email_shipping.value as email_shipping,
	au_company_invoice.value company_invoice,
	au_zip_invoice.value zip_invoice,
	au_city_invoice.value city_invoice,   
	au_country_invoice.value country_invoice,   
	au_house_invoice.value house_invoice,   
	au_street_invoice.value street_invoice,   
			rss.vat_percent, rss.vat_account_number, 
			seller_information.selling_account, seller_information.currency
			,auction.siteid
			, '' state_invoice
			, '' state_shipping
			, invoice.invoice_number, invoice.invoice_date
            , seller_information.defshcountry
			, '$paid_status' paid_status
	FROM rma_spec_solutions rss
	JOIN rma_solution sol ON rss.solution_id = sol.solution_id
	AND sol.sol_type = 'Money'
	JOIN rma_spec rs ON rss.rma_spec_id = rs.rma_spec_id
	JOIN rma r ON r.rma_id = rs.rma_id
			JOIN auction on r.auction_number=auction.auction_number and r.txnid=auction.txnid
		left join auction_par_varchar au_same_address on auction.auction_number=au_same_address.auction_number 
			and auction.txnid=au_same_address.txnid and au_same_address.key='same_address'	
			LEFT JOIN `payment_saferpay` ON `payment_saferpay`.`auction_number` = `auction`.`auction_number` AND `payment_saferpay`.`txnid` = `auction`.`txnid`
			JOIN invoice on invoice.invoice_number=auction.invoice_number
		left join auction_par_varchar au_email_shipping on auction.auction_number=au_email_shipping.auction_number 
			and auction.txnid=au_email_shipping.txnid and au_email_shipping.key='email_shipping'
		left join auction_par_varchar au_email_invoice on auction.auction_number=au_email_invoice.auction_number 
			and auction.txnid=au_email_invoice.txnid and au_email_invoice.key='email_invoice'
		left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
			and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
		left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number
			and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		left join auction_par_varchar au_gender_invoice on auction.auction_number=au_gender_invoice.auction_number
			and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
		left join auction_par_varchar au_company_invoice on auction.auction_number=au_company_invoice.auction_number 
			and auction.txnid=au_company_invoice.txnid and au_company_invoice.key='company_invoice'
		left join auction_par_varchar au_zip_invoice on auction.auction_number=au_zip_invoice.auction_number 
			and auction.txnid=au_zip_invoice.txnid and au_zip_invoice.key='zip_invoice'
		left join auction_par_varchar au_city_invoice on auction.auction_number=au_city_invoice.auction_number 
			and auction.txnid=au_city_invoice.txnid and au_city_invoice.key='city_invoice'
		left join auction_par_varchar au_country_invoice on auction.auction_number=au_country_invoice.auction_number 
			and auction.txnid=au_country_invoice.txnid and au_country_invoice.key='country_invoice'
		left join auction_par_varchar au_house_invoice on auction.auction_number=au_house_invoice.auction_number 
			and auction.txnid=au_house_invoice.txnid and au_house_invoice.key='house_invoice'
		left join auction_par_varchar au_street_invoice on auction.auction_number=au_street_invoice.auction_number 
			and auction.txnid=au_street_invoice.txnid and au_street_invoice.key='street_invoice'
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key=IF(au_same_address.value,'country_invoice','country_shipping')	
			JOIN seller_information on seller_information.username=auction.username
		left join seller_information si on auction.username=si.username
		left join translation tr on tr.table_name='translate' and tr.field_name='translate'
			and tr.id=au_gender_invoice.value and tr.language=si.default_lang
            
        LEFT JOIN payment ON payment.auction_number=auction.auction_number and payment.txnid=auction.txnid
	    left join accounts on accounts.number=IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)
        LEFT JOIN country on REPLACE(au_country_shipping.value, 'United Kingdom', 'UK')=country.name
        LEFT JOIN vat on vat.country_code=IF(auction.payment_method>2, seller_information.defshcountry
				,IFNULL(country.code,au_country_shipping.value)) 
				and DATE(payment.payment_date) between vat.date_from and vat.date_to
				and vat.country_code_from = seller_information.defshcountry


		WHERE CONCAT('//', rss.rma_spec_sol_id,'/') in ($ids)
			$seller_filter_str
        GROUP BY rss.rma_spec_sol_id

union all
SELECT distinct
	'Voucher',
	payment.payment_id,
	IF(SIGN(payment.amount)=-1, payment.selling_account_number, payment.account)  account,
	payment.vat_account_number vat_account,
	IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)  vat_selling_account_number,
	IF(IFNULL(accounts.sesam_code,'')='', vat.sesam_code, accounts.sesam_code) sesam_code,
	ABS(payment.amount) amount,
    payment.amount sign_amount,
    IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type,
	payment.payment_date,
	payment.exported,
	0 listingfee,
	payment.username,
	payment.comment,
	'' AS `transaction_id`,
	payment.code_name auction_number,
	auction.id txnid,
	NULL as ins_id,
	NULL as rma_spec_sol_id, 
	'' as name_invoice,
	'' as email_invoice,
	'' as email_shipping,
	'' company_invoice,
	'' zip_invoice,
	'' city_invoice,   
	'' country_invoice,   
	'' house_invoice,   
	'' street_invoice,   
			payment.vat_percent, payment.vat_account_number,  
			seller_information.selling_account, seller_information.currency,
			auction.siteid
			, '' state_invoice
			, '' state_shipping
			, null invoice_number, null invoice_date
            , seller_information.defshcountry
			, '$paid_status' paid_status
	FROM shop_promo_payment payment
			left join accounts on accounts.number=IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)
		join shop_promo_codes spc on spc.name=payment.code_name
		join shop auction on auction.id=spc.shop_id
		left join seller_information on auction.username=seller_information.username
			JOIN vat on vat.country_code=seller_information.defshcountry
				and DATE(payment.payment_date) between vat.date_from and vat.date_to
				and vat.country_code_from = seller_information.defshcountry
		WHERE CONCAT(payment.payment_id,'///Voucher') in ($ids)
			$seller_filter_str
	) t group by payment_id order by $sort ".($direction==-1?'desc':'');

		// in case if "UnPAID" filter selected
		$q2 = "
SELECT auction.auction_number,
  auction.txnid,
  payment_date,
  invoice_date,
  CONCAT(tr.value, ' ', au_first_name_invoice.value, ' ', au_name_invoice.value) as name_invoice,
  au_email_invoice.value as email_invoice,
  au_email_shipping.value as email_shipping,
  payment.vat_account_number vat_account,
  IF(SIGN(payment.amount)=-1, payment.selling_account_number,
	IF(SIGN(payment.account)=1, payment.account,
	  CASE
		WHEN auction.payment_method='bill_shp' THEN si.bill_account
		WHEN auction.payment_method IN ('cc_pck', 'cc_shp') THEN si.cc_account
		WHEN auction.payment_method IN ('pp_pck', 'pp_shp') THEN si.paypal_account
		ELSE si.default_account
	  END
	)
  ) account,
  IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number) selling_account,
  #fget_ITotal(invoice.invoice_number) - invoice.open_amount amount,
  ABS(payment.amount) amount,
  payment.exported,
  auction.siteid,
  payment.comment,
  invoice.invoice_number
			, au_state_invoice.value state_invoice
			, au_state_shipping.value state_shipping
	,IF(IFNULL(accounts.sesam_code,'')='', vat.sesam_code, accounts.sesam_code) sesam_code
    , si.defshcountry
    , payment.amount sign_amount
    , IF(SIGN(payment.amount)=-1, 'refund', 'payment') AS payment_type

			, '$paid_status' paid_status
FROM payment
	left join accounts on accounts.number=IF(SIGN(payment.amount)=-1, payment.account, payment.selling_account_number)
  RIGHT JOIN auction ON auction.auction_number=payment.auction_number and auction.txnid=payment.txnid
		left join auction_par_varchar au_same_address on auction.auction_number=au_same_address.auction_number 
			and auction.txnid=au_same_address.txnid and au_same_address.key='same_address'	
		left join auction_par_varchar au_state_invoice on auction.auction_number=au_state_invoice.auction_number 
			and auction.txnid=au_state_invoice.txnid and au_state_invoice.key='state_invoice'
		left join auction_par_varchar au_state_shipping on auction.auction_number=au_state_shipping.auction_number 
			and auction.txnid=au_state_shipping.txnid and au_state_shipping.key='state_shipping'
  JOIN invoice ON auction.invoice_number=invoice.invoice_number
  left join auction_par_varchar au_email_invoice on auction.auction_number=au_email_invoice.auction_number 
    and auction.txnid=au_email_invoice.txnid and au_email_invoice.key='email_invoice'
  left join auction_par_varchar au_email_shipping on auction.auction_number=au_email_shipping.auction_number 
    and auction.txnid=au_email_shipping.txnid and au_email_shipping.key='email_shipping'
  LEFT JOIN auction_par_varchar au_name_invoice ON auction.auction_number=au_name_invoice.auction_number
    and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
  LEFT JOIN auction_par_varchar au_first_name_invoice ON auction.auction_number=au_first_name_invoice.auction_number
    and auction.txnid=au_first_name_invoice.txnid and au_first_name_invoice.key='firstname_invoice'
  LEFT JOIN auction_par_varchar au_gender_invoice ON auction.auction_number=au_gender_invoice.auction_number
    and auction.txnid=au_gender_invoice.txnid and au_gender_invoice.key='gender_invoice'
  LEFT JOIN seller_information si ON auction.username=si.username
  LEFT JOIN translation tr ON tr.table_name='translate' and tr.field_name='translate'
    and tr.id=au_gender_invoice.value and tr.language=si.default_lang
		left join auction_par_varchar au_country_shipping on auction.auction_number=au_country_shipping.auction_number 
			and auction.txnid=au_country_shipping.txnid and au_country_shipping.key=IF(au_same_address.value,'country_invoice','country_shipping')
			LEFT JOIN country on REPLACE(au_country_shipping.value, 'United Kingdom', 'UK')=country.name
			left JOIN vat on vat.country_code=IF(auction.payment_method>2,si.defshcountry
				,IFNULL(country.code,au_country_shipping.value)) 
				and DATE(payment.payment_date) between vat.date_from and vat.date_to
				and vat.country_code_from = si.defshcountry
WHERE auction.main_auction_number=0 AND auction.auction_number IN ($ids) and IFNULL(payment.payment_id,0) in ($payment_ids)
		";
		if($paid_status == 1) {
            
//	echo '<h1>1</h1><pre>';echo $q; die();
			$r = $dbr->getAll($q);
		} else {
//	echo '<h1>2</h1><pre>';echo $q2; die();
			$r = $dbr->getAll($q2);
		}
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
	return $r;					
    }

    static function setExported($db, $dbr, $ids, $value)
    {
		$ids_array = explode(',', $ids);
		if (!count($ids_array)) return;
		$payment_ids_array = array();
		$ids_array1 = array();
		foreach($ids_array as $k=>$r) { 
			list($mode, $payment_ids_array[$k], $ids_array[$k]) = explode('-',$r);
			if ($mode=="'5" && !$payment_ids_array[$k] && $ids_array[$k]) {
				$ids_array1[$k] = "'".$ids_array[$k];
		        $r = $db->query("UPDATE auction set payment_export = '$value' WHERE auction_number = ".$ids_array1[$k]);
				if (PEAR::isError($r)) {
					print_r($r); die();
	        	    return;
		        }
			} else {
				$ids_array1[$k] = "'".$ids_array[$k];
				if (!strlen($payment_ids_array[$k])) unset($payment_ids_array[$k]);
			}
		}
		$ids = implode(',', $ids_array1);
		$payment_ids = implode(',', $payment_ids_array);
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("UPDATE payment set exported = '$value' WHERE payment_id in ($payment_ids)".($ids && !strpos($ids, '/')?" and auction_number in ($ids)":''));
        $db->query("UPDATE ins_payment set exported = '$value' WHERE CONCAT(payment_id, '/', ins_id, '//') in ($ids)");
        $db->query("UPDATE rma_spec_solutions set exported = '$value' WHERE CONCAT('//', rma_spec_sol_id,'/') in ($ids)");
        $db->query("UPDATE shop_promo_payment set exported = '$value' WHERE  CONCAT(payment_id,'///Voucher') in ($ids)");
//		die();
    }

    static function findNotExported($db, $dbr)
    {
	  global $seller_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        return $dbr->getAll("SELECT * FROM payment 
			JOIN auction on payment.auction_number=auction.auction_number and payment.txnid=auction.txnid
		WHERE NOT exported 
		$seller_filter_str
		ORDER BY payment_date");
    }

    static function allExported($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("UPDATE payment SET exported = 1");
    }

    static function listAll($db, $dbr, $auction_number, $txnid, $what='')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            PEAR::raiseError('Payment::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		if ($what==1) $fee = ' and listingfee ';
		else $fee = ' and not listingfee';
		if ($what=='rate_payments') $rate = ' and rate ';
		else $rate = ' and not rate';
        $auction_number = mysql_real_escape_string($auction_number);
        $r = $db->query("SELECT * FROM payment WHERE auction_number = ".$auction_number." AND txnid= ".$txnid.$fee.$rate." ORDER BY payment_date");
        if (PEAR::isError($r)) {
			echo $r->message;
            return;
        }
        $list = array();
        while ($payment = $r->fetchRow()) {
            $list[] = $payment;
        }
        return $list;
    }

    static function total($db, $dbr, $auction_number, $txnid, $fee=0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if ($fee) $fee = ' and listingfee';
			 else $fee = ' and not listingfee';
        $r = $db->query("SELECT SUM(amount) AS total FROM payment WHERE auction_number = ".$auction_number
			." AND txnid=".$txnid.$fee);
        if (PEAR::isError($r)) {
			print_r($r->message);
            return;
        }
        $list = array();
        $payment = $r->fetchRow();
        return $payment->total;
    }

    function update ()
    {
        $query = '';
        foreach ($this->data as $field => $value) {
            if (($this->data->ins_id) && (($field=='rate') || ($field=='auction_number') || ($field=='txnid'))) continue;
            if (($this->data->auction_number) && ($field=='ins_id')) continue;
            if ($query) {
                $query .= ', ';
            }
			$query .= "`$field`='" . mysql_real_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE payment_id='" . mysql_real_escape_string($this->data->payment_id) . "'";
        }
		if ($this->data->ins_id)
	        $r = $this->_db->query("$command ins_payment SET $query $where");
		else	
	        $r = $this->_db->query("$command payment SET $query $where");
//	        echo "$command ins_payment SET $query $where";
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($this->_error);
        }
        return $r;
    }

}