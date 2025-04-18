<?php
/**
 * RMA case
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';
require_once 'util.php';
require_once 'lib/ArticleHistory.php';
require_once 'lib/Barcode.php';

/**
 * RMA case
 * @package eBay_After_Sale
 */
class Rma_Spec
{
    /**
    * Holds data record
    * @var object
    */
    var $data;
    private $_qc_numbers = [];
    
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
    function Rma_Spec(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id, $rma_spec_id = 0)
    {
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        $this->auction_number = $auction->data->auction_number;
        $this->txnid = (int)$auction->data->txnid;
        if (!$rma_spec_id) {
            $r = $this->_db->query("EXPLAIN rma_spec");
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
            $this->data->rma_id = $rma_id;
            $this->data->rma_spec_id = '';
        } else {
            $r = $this->_db->query("SELECT * FROM rma_spec WHERE rma_spec_id=$rma_spec_id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Rma::Rma : record $id does not exist");
                return;
            }
            $this->pics = Rma_Spec::getPics($db, $dbr, $rma_id, $rma_spec_id);
            $this->solutions = Rma_Spec::getSolutions($db, $dbr, $rma_id, $rma_spec_id);
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
        if ($field == 'qc_numbers') {
            $this->_qc_numbers[] = $value;
            return;
        }
        
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        } 
        else {
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
    * @return bool|object
    * @desc Update record
    */
    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Rma::update : no data');
        }
		$this->data->alt_order = (int)$this->data->alt_order;
        foreach ($this->data as $field => $value) {
			{
	            if ($query) {
	                $query .= ', ';
	            }
	            if ((($value!='' || $value=='0') && $value!=NULL)
						|| in_array($field,array('alt_order')))
					$query .= "`$field`='".($value)."'";
				else	
					$query .= "`$field`= NULL";
			};
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE rma_spec_id='" . mysql_escape_string($this->data->rma_spec_id) . "'";
        }
        $r = $this->_db->query("$command rma_spec SET $query $where");
        if (PEAR::isError($r)) {
           print_r($r);
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->rma_spec_id = mysql_insert_id();
        }
        
        if ($this->data->rma_spec_id && $this->_qc_numbers) {
            $this->_db->query("DELETE FROM `rma_qc_number` WHERE `rma_spec_id` = '{$this->data->rma_spec_id}'");
            foreach (array_unique($this->_qc_numbers) as $_qc_number) {
                $_qc_number = trim($_qc_number);
                if ($_qc_number) {
                    $this->_db->query("INSERT INTO `rma_qc_number` (`rma_spec_id`, `qc_number`) VALUES ('{$this->data->rma_spec_id}', '$_qc_number')");
                }
            }
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
		$rma_spec_id = (int)$this->data->rma_spec_id;
		$this->_db->query("DELETE FROM rma_spec_solution WHERE rma_spec_id=$rma_spec_id");
		$this->_db->query("DELETE FROM rma_pic WHERE rma_id={$this->data->rma_id} and rma_spec_id=$rma_spec_id and not is_file");
        $r = $this->_db->query("DELETE FROM rma_spec WHERE rma_spec_id=$rma_spec_id");
        $this->_db->query("DELETE FROM barcode_object WHERE obj='rma_spec' and obj_id IN ($rma_spec_id)");
		ArticleHistory::stockRecals($this->_db, $this->_dbr, $this->data->article_id);
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
    static function listAll($db, $dbr, $rma_id, $category=1)
    {
		$where = '';
	    switch ($category) {
    	    case 1:
				$where = ' ';
            	break;
    	    case 2:
				$where = ' and not alt_order and w.warehouse_id is null and rs.problem_id not in (4,11) ';
            	break;
    	    case 3:
				$where = ' and not alt_order and (not rs.ins_value OR rs.ins_value IS NULL) ';
            	break;
    	    case 4:
				$where = ' and alt_order ';
            	break;
		}
		$q = "SELECT 
		rs.*, 
		 IF(rs.admin_id=2, 
(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = 'german' 
AND shop_bonus.article_id = a.article_id limit 1), CONCAT(rs.article_id, ': ', (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id))) as article_name, 
		p.name as problem_name,
		s.name as solution_name, 
		s.sol_type, 
		w.name as warehouse_name,
		a.weight,
		a.weight_per_single_unit,
		a.volume,
		a.volume_per_single_unit,
		a.qc,
		IFNULL(users.name, rs.responsible_uname) responsible_name
		, DATE_ADD(CONCAT(rs.sell_date,' ',rs.sell_time),INTERVAL rs.sell_duration day) sell_end_datetime
        , IF(bonb1.id || bonb2.id, 'no barcode', b.id) barcode_id
        , CONCAT(IFNULL(boa.obj_id,IFNULL(bom.obj_id,CONCAT('(t)',bo.obj_id))),'/',rs.article_id,'/',b.id) barcode
        , b1.id problem_barcode_id
        , CONCAT(IFNULL(boa1.obj_id,IFNULL(bom1.obj_id,CONCAT('(t)',bop.obj_id))),'/',rs.article_id,'/',b1.id) problem_barcode
		, rma.auction_number, rma.txnid
		, au.id sell_auction_id
		, IF(au.paid and i.open_amount=0, fget_Currency(au.siteid),'') real_sell_currency
		, sum(payment.amount) real_sell_paid_amount
		, max(payment.payment_date) real_sell_paid_date
		, (select max(updated) from total_log where table_name='rma_spec' and field_name='add_to_stock' and tableid=rs.rma_spec_id) add_to_stock_log_updated
		, warehouse_wd.barcode_type barcode_type_warehouse_wd
		, warehouse_send.barcode_type barcode_type_warehouse_send
		, article.barcode_type barcode_type_article
		, emp.id emp_id
		, emp.email emp_email
		, emp.name emp_name
		, emp.name2 emp_name2
		, emp_assist.id assist_emp_id
		, emp_assist.email assist_emp_email
		, emp_assist.name assist_emp_name
		, emp_assist.name2 assist_emp_name2
        , GROUP_CONCAT(DISTINCT `rma_qc_number`.`qc_number` SEPARATOR '|') AS `qc_numbers`
		FROM ((((rma_spec rs 
			JOIN article a ON a.article_id=rs.article_id and IFNULL(a.admin_id, 0)=IFNULL(rs.admin_id, 0))
			 LEFT JOIN rma_problem p ON p.problem_id=rs.problem_id)
			 	LEFT JOIN rma_solution s ON s.solution_id=rs.solution_id)
					LEFT JOIN warehouse w ON w.warehouse_id=rs.warehouse_id)
					LEFT JOIN users ON rs.responsible_uname=users.username
		 LEFT JOIN rma ON rma.rma_id=rs.rma_id
         LEFT JOIN `rma_qc_number` ON `rma_qc_number`.`rma_spec_id` = `rs`.`rma_spec_id`
		 LEFT JOIN warehouse warehouse_send on rs.send_warehouse_id=warehouse_send.warehouse_id
         LEFT JOIN auction au on rs.sell_auction_number=au.auction_number and rs.sell_txnid=au.txnid
         LEFT JOIN invoice i on i.invoice_number=au.invoice_number and au.paid and i.open_amount=0
         LEFT JOIN payment on payment.auction_number=au.auction_number and payment.txnid=au.txnid and au.paid and i.open_amount=0
         LEFT JOIN warehouse warehouse_wd on rs.warehouse_id=warehouse_wd.warehouse_id
         LEFT JOIN article on rs.article_id=article.article_id and article.admin_id=0
         LEFT JOIN op_company on op_company.id=article.company_id
         LEFT JOIN employee emp on emp.id=op_company.emp_id
         LEFT JOIN employee emp_assist on emp_assist.id=op_company.emp_assist_id
            left join auction anom ON anom.main_auction_number = rma.auction_number and anom.main_txnid = rma.txnid
            left join orders o1 ON o1.article_id = a.article_id and o1.auction_number = anom.auction_number
            left join barcode_object bonb1 ON bonb1.obj='orders' and bonb1.obj_id = o1.id and bonb1.barcode_id IS NULL
            left join orders o2 ON o2.article_id = a.article_id and o2.auction_number = rma.auction_number
            left join barcode_object bonb2 ON bonb2.obj='orders' and bonb2.obj_id = o2.id and bonb2.barcode_id IS NULL
           
            left join barcode_object bo on bo.obj='rma_spec' and bo.obj_id=rs.rma_spec_id
            left join barcode b on b.id=bo.barcode_id
            left join barcode_object boa on boa.obj='op_article' and boa.barcode_id=b.id
            left join barcode_object bom on bom.obj='barcodes_manual' and bom.barcode_id=b.id
            left join barcode_object bop on bop.obj='rma_spec_problem' and bop.obj_id=rs.rma_spec_id
            left join barcode b1 on b1.id=bop.barcode_id
            left join barcode_object boa1 on boa1.obj='op_article' and boa1.barcode_id=b1.id
            left join barcode_object bom1 on bom1.obj='barcodes_manual' and bom1.barcode_id=b1.id
		WHERE rs.rma_id = $rma_id ".$where." 
		group by rs.rma_spec_id
		ORDER BY rs.article_id";
        $r = $dbr->query($q);
        if (PEAR::isError($r)) {
			echo $q;
            return;
        }
        
        $list = array();
        while ($article = $r->fetchRow()) {
			if ($category==1) {
    	        $article->solutions = Rma_Spec::getSolutions($db, $dbr, $article->rma_spec_id);
	            $article->pics = Rma_Spec::getPics($db, $dbr, $rma_id, $article->rma_spec_id);
			}
            
            $article->qc_numbers = explode('|', $article->qc_numbers);
            
            //// barcodes for 1st table
			$q = "select b.id, b.barcode
				from orders
				join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				join barcode_object bo on bo.obj='orders' and bo.obj_id=orders.id
				join vbarcode b on b.id=bo.barcode_id and b.inactive=0
				left join barcode_object bop on bop.obj='rma_spec_problem' and bop.obj_id<>{$article->rma_spec_id} and bop.barcode_id=b.id
				where IFNULL(mau.auction_number,au.auction_number)=$article->auction_number and IFNULL(mau.txnid,au.txnid)=$article->txnid
				and orders.article_id='$article->article_id' and bop.id is null
				group by b.id
					union 
				select b.id, CONCAT('(t){$article->rma_spec_id}/',rs.article_id,'/',b.id) 
				from barcode b 
				join rma_spec rs on rs.rma_spec_id={$article->rma_spec_id} and rs.problem_id=11
				where b.code like '{$article->rma_spec_id}/%' 
				";
			$barcodes = $dbr->getAssoc($q);
			if (count($barcodes)<=1) {
				$article->problem_barcodes = 0;
                if(count($barcodes) == 1){
                    $article->problem_barcode_id = key($barcodes);
                    $article->problem_barcode = current($barcodes);
                }
			} else {
				if (!$dbr->getOne("select count(*)
				from barcode b 
				join rma_spec rs on rs.rma_spec_id={$article->rma_spec_id} and rs.problem_id=11
				where b.code like '{$article->rma_spec_id}/%'
					")) {
					if (!$article->problem_barcode_id) {
						$newbarcodes = array('' => 'dont know yet'); // justina asked 
						foreach($barcodes as $kk=>$rr) {
							$newbarcodes[$kk] = $rr;
						}
						$barcodes = $newbarcodes;
					} // if dont know barcode yet
				}; // if there are barcodes
				$article->problem_barcodes = $barcodes;
			}
			$q = "select b.id, b.barcode
				from orders
				join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				join rma on rma.auction_number=IFNULL(mau.auction_number,au.auction_number) and rma.txnid=IFNULL(mau.txnid,au.txnid)
				join barcode_object bo on bo.obj='orders' and bo.obj_id=orders.id
				join vbarcode b on b.id=bo.barcode_id and b.inactive=0
				where rma.rma_id={$article->rma_id} and orders.article_id='{$article->article_id}' and orders.manual=0
					union 
				select b.id, CONCAT('(t){$article->rma_spec_id}/',rs.article_id,'/',b.id) 
				from barcode b 
				join rma_spec rs on rs.rma_spec_id={$article->rma_spec_id}
				where b.code like '{$article->rma_spec_id}/%'
				";
			$barcodes = $dbr->getAssoc($q);
			if (count($barcodes)<=1) {
				$article->barcodes = 0;
                if(count($barcodes) == 1){
                    $article->barcode_id = key($barcodes);
                    $article->barcode = current($barcodes);
                }
			} else {
				if (!$dbr->getOne("select count(*)
				from barcode b 
				join rma_spec rs on rs.rma_spec_id={$article->rma_spec_id}
				where b.code like '{$article->rma_spec_id}/%'
					")) {
					$barcodes[''] = 'unknown';
				};
				$article->barcodes = $barcodes;
			}
			$barcode = new Barcode($db, $dbr, $article->barcode_id);
			$article->barcode_warehouse = $barcode->get_barcode_warehouse();
			$article->barcode_warehouse_id = $article->barcode_warehouse->barcode_warehouse_id;
            $list[] = $article;
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
    static function listArray($db, $dbr, $rma_id)
    {
        $ret = array();
        $list = Rma::listAll($db, $dbr, $rma_id);
        foreach ((array)$list as $rma_spec) {
            $ret[$rma_spec->rma_spec_id] = $rma_spec->article_name;
        }
        return $ret;
    }

    static function listProblemsArray($db, $dbr, $alt=0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
        $r = $dbr->query("SELECT * 
		FROM rma_problem 
		where alt=$alt
		order by `order`");
        if (PEAR::isError($r)) {
            return;
        }
        while ($problem = $r->fetchRow()) {
            $ret[$problem->problem_id] = $problem->name;
        }
        return $ret;
    }

    static function listSolutionsArray($db, $dbr, $siteid=77, $alt=0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
        $r = $dbr->query("select solution_id, sol_type, position, 
			REPLACE(name, 'EUR', (select value from config_api where siteid='$siteid' and par_id=7)) as name 
			from rma_solution
			where alt=$alt
			order by position");
        if (PEAR::isError($r)) {
            return;
        }
        while ($solution = $r->fetchRow()) {
            $ret[$solution->solution_id] = $solution->name;
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
        if (empty($this->data->article_id)) {
            $errors[] = 'article_id is required';
        }
        return 1;
    }

	static function GetSolType($db, $dbr, $solution_id) {
        $r = $dbr->query("SELECT 
		sol_type
		FROM rma_solution
		WHERE solution_id=$solution_id");
        if (PEAR::isError($r)) {
            return;
        }
        $res = $r->fetchRow();
        return $res->sol_type;
	}	

    static function getPics($db, $dbr, $rma_id, $rma_spec_id, $is_file='NOT', $hidden=true)
    {
        $hidden_cond = $hidden ? '' : ' AND NOT rma_pic.hidden AND NOT IFNULL(rma_spec.hidden, 0) '; 
        $r = $dbr->query("SELECT rma_pic.*, IFNULL( users.name, rma_pic.username ) as fullusername 
		from rma_pic LEFT JOIN users ON rma_pic.username=users.username
		left join rma_spec on rma_pic.rma_spec_id=rma_spec.rma_spec_id   
		where rma_pic.rma_id=$rma_id and rma_pic.rma_spec_id=$rma_spec_id AND ".$is_file." is_file $hidden_cond 
		ORDER BY pic_id");
        if (PEAR::isError($r)) {
           print_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addPic($db, $dbr, $rma_id, $rma_spec_id,
		$name,
		$description,
		$pic, 
		$is_file=0
		)
    {
        $rma_id = (int)$rma_id;
		$rma_spec_id = mysql_escape_string($rma_spec_id);
		$name = mysql_escape_string($name);
		$description = mysql_escape_string($description);

        $md5 = md5($pic);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $pic);
        }

		global $loggedUser;
		$username = $loggedUser->get('username');
		$timediff = $loggedUser->get('timezone');
		$date = serverToLocal(date('Y-m-d H:i:s'), $timediff);
        $r = $db->query("insert into rma_pic set 
			rma_id=$rma_id, 
			rma_spec_id=$rma_spec_id,
			name='$name',
			description='$description',
			pic='$md5',
			is_file=$is_file,
			date='$date',
			username='$username',
			sent=0");
        if (PEAR::isError($r)) aprint_r($r);
    }

    static function deletePic($db, $dbr, $pic_id)
    {
        $pic_id = (int)$pic_id;
        $db->query("delete from rma_pic where pic_id=$pic_id");
    }

    static function hidePic($db, $dbr, $pic_id, $value=1)
    {
        $pic_id = (int)$pic_id;
        $db->query("update rma_pic set hidden=$value where pic_id=$pic_id");
    }

    static function getSolutions($db, $dbr, $rma_spec_id)
    {
		$q = "SELECT rss.*,
		REPLACE(rs.name, 'EUR', (select value from config_api where siteid=au.siteid and par_id=7)) as name,
		rs.sol_type,
		0 as absent
		, rss.alt_order_id, rss.alt_order, rss.alt_order_quantity
		, orders.auction_number, orders.txnid
		, (select u.name from total_log tl 
			join users u on tl.username=u.system_username
			where tl.table_name='rma_spec_solutions'
			and field_name='rma_spec_sol_id' and old_value is NULL and tl.TableID=rss.rma_spec_sol_id order by updated desc limit 1) whom
		, rs.target_url
		from rma_spec_solutions rss 
		join rma_spec rsp on rsp.rma_spec_id=rss.rma_spec_id
		join rma r on r.rma_id=rsp.rma_id
		join auction au on au.auction_number=r.auction_number and au.txnid=r.txnid
		join rma_solution rs on rss.solution_id=rs.solution_id
		left join orders on orders.id=rss.alt_order_id
		where rss.rma_spec_id=$rma_spec_id
		ORDER BY rma_spec_sol_id";
        $r = $dbr->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        $list = array();
        while ($article = $r->fetchRow()) {
			if ($article->sol_type=='Exchange') {
				if ((strpos($article->name, 'yyyyy') && ($article->invoice_date == '0000-00-00'))
					|| (strpos($article->name, 'xxxxx') && ($article->invoice_number == '')))
						$atricle->absent = 1; 
			}
			if ($article->sol_type!='Exchange') {
				if ((strpos($article->name, 'yyyyy') && ($article->invoice_date == '0000-00-00'))
					|| (strpos($article->name, 'xxxxx') && ($article->amount == 0)))
						$atricle->absent = 1;
			}
			$article->name = ($article->sol_type=='Exchange') ? str_replace('yyyyy', $article->invoice_date, str_replace('xxxxx', $article->invoice_number, $article->name))
				: str_replace('yyyyy', $article->invoice_date, str_replace('xxxxx', $article->amount, $article->name));
            $list[] = $article;
			$list[count($list)-1]->absent = $atricle->absent;
        }
        return $list;
    }

    static function getSolution($db, $dbr, $rma_spec_sol_id)
    {
        $r = $dbr->query("SELECT rss.*,
		rs.name,
		rs.sol_type,
		0 as absent
		from rma_spec_solutions rss join rma_solution rs on rss.solution_id=rs.solution_id
		where rma_spec_sol_id=$rma_spec_sol_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
			if ($article->sol_type=='Exchange') {
				if ((strpos($article->name, 'yyyyy') && ($article->invoice_date == '0000-00-00'))
					|| (strpos($article->name, 'xxxxx') && ($article->invoice_number == '')))
						$atricle->absent = 1; 
			}
			if ($article->sol_type!='Exchange') {
				if ((strpos($article->name, 'yyyyy') && ($article->invoice_date == '0000-00-00'))
					|| (strpos($article->name, 'xxxxx') && ($article->amount == 0)))
						$atricle->absent = 1;
			}
			$article->name = ($article->sol_type=='Exchange') ? str_replace('yyyyy', $article->invoice_date, str_replace('xxxxx', $article->invoice_number, $article->name))
				: str_replace('yyyyy', $article->invoice_date, str_replace('xxxxx', $article->amount, $article->name));
            $list[] = $article;
			$list[count($list)-1]->absent = $atricle->absent;
        }
        return $list;
    }

    static function addSolution($db, $dbr, $rma_spec_id,
		$solution_id,
		$invoice_number,
		$invoice_date,
		$amount,
		$account
		)
    {
		$rma_spec_id = mysql_escape_string($rma_spec_id);
		$solution_id = mysql_escape_string($solution_id);
		$invoice_number = "'".mysql_escape_string($invoice_number)."'"; if ($invoice_number=="''") $invoice_number='NULL';
		$invoice_date = "'".mysql_escape_string($invoice_date)."'"; if ($invoice_date=="''") $invoice_date='NULL';
		$amount = "'".mysql_escape_string($amount)."'"; if ($amount=="''") $amount='NULL';
		$r = $dbr->getRow("select au.*,r.rma_id
					from auction au 
					join rma r on au.auction_number=r.auction_number and au.txnid=r.txnid 
					join rma_spec rs on rs.rma_id=r.rma_id 
					where rs.rma_spec_id=$rma_spec_id");
		$auction = new Auction ($db, $dbr, $r->auction_number, $r->txnid);			
			$r = VAT::get_vat_attribs($db, $dbr, $auction, trim($invoice_date,"'"));
		    if (PEAR::isError($r)) {
		       aprint_r($r);
		    } else {
				if ($auction->get('customer_vat')) {
	    	        $vat_percent = 0;
		            $vat_account_number = $r->out_vat;
		            $selling_account_number = $r->out_selling;
				} else {
		            $vat_percent = $r->vat_percent;
		            $vat_account_number = $r->vat_account_number;
	    	        $selling_account_number = $r->selling_account_number;
				}
			};	
        $db->query("insert into rma_spec_solutions set 
			rma_spec_id=$rma_spec_id,
			solution_id=$solution_id,
			invoice_number=$invoice_number,
			invoice_date=$invoice_date,
			amount=$amount,
			vat_percent='$vat_percent',
            vat_account_number = '$vat_account_number',
	       	selling_account_number = '$selling_account_number',
	       	account = '$account'
			");
    }

    static function addSolutionAlt($db, $dbr, $rma_spec_id,
		$solution_id,
		$quantity,
		$order_id
		)
    {
		$rma_spec_id = mysql_escape_string($rma_spec_id);
		$solution_id = mysql_escape_string($solution_id);
		$quantity = "'".mysql_escape_string($quantity)."'"; if ($quantity=="''") $quantity='NULL';
		$order_id = "'".mysql_escape_string($order_id)."'"; if ($order_id=="''") $order_id='NULL';
		$q = "insert into rma_spec_solutions set 
			rma_spec_id=$rma_spec_id,
			solution_id=$solution_id,
			alt_order = 1,
	       	alt_order_quantity = $quantity,
	       	alt_order_id = $order_id
			";
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            print_r($r); die();
        }
    }

    static function deleteSolution($db, $dbr, $rma_spec_sol_id)
    {
		$rma_spec_sol_id = mysql_escape_string($rma_spec_sol_id);
        $db->query("delete from rma_spec_solutions where rma_spec_sol_id=$rma_spec_sol_id");
    }

    static function getSolutionPDF($db, $dbr, $rma_spec_sol_id) 
    {
        require_once __DIR__ . '/../plugins/function.barcodeurl.php';
	global $siteURL;
	global $english;
	global $smarty;
	$sol = $dbr->getRow("select auction_number, txnid, rs.rma_id, rss.* FROM rma_spec_solutions rss
	    JOIN rma_spec rs ON rss.rma_spec_id = rs.rma_spec_id
	    JOIN rma r ON r.rma_id = rs.rma_id
	    where rss.rma_spec_sol_id=$rma_spec_sol_id");
	$items = Rma_Spec::getSolution($db, $dbr, $rma_spec_sol_id);
	$item = $items[0];	    
 	$auction_number = $sol->auction_number;
 	$txnid = $sol->txnid;   
	$auction = new Auction($db, $dbr, $auction_number, $txnid);
	$sellerInfo = SellerInfo::singleton($db, $dbr, $auction->get('username'));
	if ($outside_invoice) {
	    $outside_addon = nl2br(trim(htmlspecialchars(substitute(substitute($sellerInfo->getTemplate('outside_invoice_addon'
			, $auction->getMyLang()), $auction->data),$sellerInfo->data))));
    	}
    $auctiondata = $auction->data;
    $auctiondata->seller_email = $sellerInfo->get('email');
	$auctiondata->vat_id = $sellerInfo->get('vat_id');
	if (strlen($sellerInfo->getTemplate('invoice_footer'
			, $auction->getMyLang()))) {
	    $sellerInfo_invoice_footer = nl2br(trim(htmlspecialchars(
			substitute($sellerInfo->getTemplate('invoice_footer'
				, $auction->getMyLang()), $auctiondata))));
	    $sellerInfo_invoice_footer = substitute($sellerInfo_invoice_footer, $sellerInfo->data);
	};	
    	$currency = siteToSymbol($auction->data->siteid);

	$tmp = 'tmp';
    	$filename = 'export.pdf';

        $pdf =File_PDF::factory('P', 'cm', 'A4');
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
        $pdf->setFont('arial','B', 8); $pdf->text(8, $y, $english[65]);
		$pdf->setFont('arial','', 8); $pdf->text(11, $y, $auction->get('auction_number')); 
        $pdf->setFont('arial','B', 8); $pdf->text(15, $y, $english[66]); 
		$pdf->setFont('arial','', 8); $pdf->text(18, $y, 'CREDIT '.$auction->get('invoice_number')); 

		$y+=0.5;
        $pdf->setFont('arial','B', 8); $pdf->text(1, $y, $english[67]); 
		$pdf->setFont('arial','', 8); $pdf->text(5, $y, $auction->get('tel_invoice')); 
	if ($auction->get('tel_shiping')) {
        $pdf->setFont('arial','B', 8); $pdf->text(8, $y, $english[68]); 
		$pdf->setFont('arial','', 8); $pdf->text(11, $y, $auction->get('tel_shiping')); 
	};	
        $pdf->setFont('arial','B', 8); $pdf->text(15, $y, $english[69]); 
	    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\s]+/', $sol->invoice_date);
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
			$title = $item->name.' for ticket '.$sol->rma_id;
	        $pdf->setXY(3, $y);	$pdf->multiCell(7, 0.5, $title, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, $currency.' '.-$item->amount, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, (strlen($item->vat_percent)?$item->vat_percent:0).'%', 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.(-$item->amount), 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();

		$y = $newy;
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[77]), 0, 'L');
		$vatvalue =  sprintf("%01.2f", ((-$item->amount) / (100 + $item->vat_percent) * $item->vat_percent));
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.$vatvalue, 0, 'L');
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setFont('arial','B', 8); 
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[78]), 0, 'L');
        $pdf->setFont('arial','', 8); 
		$price =  sprintf("%01.2f", (-$item->amount));
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
