<?php

/**
 * RMA case
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'lib/Rma_Spec.php';
require_once 'lib/Rma.php';
require_once 'lib/Auction.php';
require_once 'lib/Invoice.php';
require_once 'lib/ShippingMethod.php';

require_once 'util.php';

require_once 'PEAR.php';

/**
 * RMA case
 * @package eBay_After_Sale
 */
class Insurance
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
    function Insurance($db, $dbr, $id = 0, $auction_number = 0, $txnid = 0, $rma_id=0, $timediff = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Insurance::Insurance expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN insurance");
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
            $this->data->id = '';
			$rma = new Rma ($db, $dbr, $rma_id, $auction_number, $txnid);
			$auction = new Auction ($db, $dbr, $auction_number, $txnid);
            if ((!PEAR::isError($auction)) && (!PEAR::isError($rma))) {
/*				$this->data->shipping_method = 
					$this->_dbr->getOne("select shipping_method 
					from tracking_numbers 
					where auction_number = $auction_number AND txnid=$txnid
					order by date_time limit 0, 1");*/
				$this->data->auction_number = $auction_number;
				$this->data->txnid = $txnid;
				$this->data->rma_id = $rma_id;
				$this->data->date = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
				$this->update();
				$this->import();
			}
        } else {
            $r = $this->_db->query("SELECT insurance.* , insurance_static.static_invoice_html, insurance_static.static_invoice_pdf 
				FROM insurance JOIN insurance_static ON insurance_static.id = insurance.id
				WHERE insurance.id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Insurance::Insurance : record $id does not exist");
                return;
            }
            
            $this->data->static_invoice_html = get_file_path($this->data->static_invoice_html);
            $this->data->static_invoice_pdf = get_file_path($this->data->static_invoice_pdf);
            
            $this->articles = Insurance::getArticles($db, $dbr, $id);
			$this->docs = Insurance::getDocs($db, $dbr, $id);
			$this->comments = Insurance::getComments($db, $dbr, $id);
			$this->sh_refunds = Insurance::getSHRefunds($db, $dbr, $id);
			$this->payments = Insurance::getPayments($db, $dbr, $id);
			$this->log = Insurance::getLogs($db, $dbr, $id);
			$this->printerlog = Insurance::getPrinterLogs($db, $dbr, $id);
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
            $this->_error = PEAR::raiseError('Insurance::update : no data');
        }
        foreach ($this->data as $field => $value) {
//			if (isset($value)||) 
			{
				if (strpos($field, 'static')===0) continue;
	            if ($query) {
	                $query .= ', ';
	            }
	            if ((($value!='' || $value=='0') && $value!=NULL) || $field=='closed_by' || $field=='closed')
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
        $r = $this->_db->query("$command insurance SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
        }
        if ($this->_isNew) {
            $this->data->id = mysql_insert_id();
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
			} elseif ($field=='id') {
				$query_static[] = "`$field`='$value'";
			}	
        }
        
        $query_static = implode(', ', $query_static);
        
        $r = $this->_db->query("$command insurance_static SET $query_static $where");
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r;
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
		$id = (int)$this->data->id;
		$this->_db->query("DELETE FROM ins_pic WHERE ins_article_id IN (
			select id FROM ins_article WHERE ins_id=$id)");
		$this->_db->query("DELETE FROM ins_article WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_doc WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_payment WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_log WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_printer_log WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_tracking_numbers WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_sh_refund WHERE ins_id=$id");
		$this->_db->query("DELETE FROM ins_comment WHERE ins_id=$id");
        $r = $this->_db->query("DELETE FROM insurance WHERE id=$id");
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
    static function listAll($db, $dbr, $auction)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $auction_number = $auction->data->auction_number;
        $txnid = $auction->data->txnid;
		$q = "SELECT insurance.*, users.name as responsible_name 
	FROM insurance LEFT JOIN users ON insurance.responsible_username=users.username 
	WHERE auction_number=$auction_number and txnid=$txnid order by id";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($obj = $r->fetchRow()) {
            $obj->articles = Insurance::getArticles($db, $dbr, $obj->id);
			$obj->docs = Insurance::getDocs($db, $dbr, $obj->id);
			$obj->comments = Insurance::getComments($db, $dbr, $obj->id);
			$obj->sh_refunds = Insurance::getSHRefunds($db, $dbr, $obj->id);
			$obj->payments = Insurance::getPayments($db, $dbr, $obj->id);
			$obj->log = Insurance::getLogs($db, $dbr, $obj->id);
			$obj->printerlog = Insurance::getPrinterLogs($db, $dbr, $obj->id);            
            $list[] = $obj;
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
        $list = Insurance::listAll($db, $dbr, $auction);
        foreach ((array)$list as $rma) {
            $ret[$rma->rma_id] = $rma->CRM_Ticket;
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

    function import()
    {
		$db = $this->_db;
		$dbr = $this->_dbr;
        $r = $db->query("select article_id, problem_id, cost, rma.rma_id, rma_spec_id
			FROM rma_spec
			JOIN rma ON rma_spec.rma_id = rma.rma_id
			WHERE rma.auction_number =".$this->data->auction_number.
			" AND txnid =".$this->data->txnid);
        if (PEAR::isError($r)) {
            return;
        }
        while ($article = $r->fetchRow()) {
			$article->pics = Rma_Spec::getPics($db, $dbr, $article->rma_id, $article->rma_spec_id);
			$ins_article_id = Insurance::addArticle($db, $dbr, 
			  	$this->data->id,
		  		$article->article_id,
		  		$article->problem_id,
		  		$article->cost);
			if (count($article->pics)) foreach ($article->pics as $pic) {
			    Insurance::addArtPic($db, $dbr, $ins_article_id,
				  $pic->name,
				  $pic->pic
				);
			}// foreach
        } // while
		$this->articles = Insurance::getArticles($db, $dbr, $this->data->id);


        $r = $db->query("select 
			rma_id 
			FROM rma 
			WHERE auction_number =".$this->data->auction_number.
			" AND txnid =".$this->data->txnid);
        if (PEAR::isError($r)) {
            return;
        }
        while ($rma = $r->fetchRow()) {
			$sh->shs = Rma::getSHRefunds($db, $dbr, $rma->rma_id);
			foreach ($sh->shs as $sh) {
				if (!$sh->sh_custom) {
					Insurance::addSHRefund($db, $dbr, $this->data->id,
						$sh->sh_tracking_id,
						$sh->sh_reason,
						$sh->sh_value,
						$sh->sh_decision,
						$sh->sh_accepted,
						$sh->sh_date,
						$sh->cancelled,
						$sh->amount_to_refund
						);
				} else	{
					Insurance::addSHRefundCustom($db, $dbr, $this->data->id,
						$sh->number,
						$sh->date_time,
						$sh->shipping_method_id,
						$sh->shipping_date,
						$sh->username,
						$sh->sh_reason,
						$sh->sh_value,
						$sh->sh_decision,
						$sh->sh_accepted,
						$sh->sh_date,
						$sh->cancelled,
						$sh->amount_to_refund
						);
				}; // if		
			}// foreach
        } // while
		$this->sh_refunds = Insurance::getSHRefunds($db, $dbr, $this->data->id);
    }

    static function getArticles($db, $dbr, $id)
    {
		$q = "select t.*, IFNULL(custom_title, article_name) custom_title1 from (
		SELECT ins_article.*, CONCAT(article.article_id, ': ', (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = auction.lang
				AND id = article.article_id)) article_name
		, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'insurance_price'
				AND language = auction.siteid
				AND id = article.article_id) insurance_price
		, article.weight
		, article.weight_per_single_unit
		, article.volume
		, article.volume_per_single_unit
		, rma_problem.name problem_name
		from ins_article 
		join insurance on ins_article.ins_id=insurance.id 
		join auction on auction.auction_number=insurance.auction_number and auction.txnid=insurance.txnid
		join article on ins_article.article_id=article.article_id and article.admin_id=0
		left join rma_problem on ins_article.problem_id=rma_problem.problem_id
		where ins_id=$id
		) t
		ORDER BY id";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
			$article->cost_history = $dbr->getAll("select updated, old_value, new_value, IFNULL(users.name,total_log.username) as user
				from total_log 
				left join users on CONCAT(REPLACE(users.username,' ',''),'@localhost')=total_log.username
				where 1
				and Table_name='ins_article'
				and Field_name='cost'
				#and old_value is not null
				and TableID=".$article->id."
				order by updated desc");
			$article->pics = Insurance::getArtPics($db, $dbr, $article->id);
            $list[] = $article;
        }
        return $list;
    }

    static function addArticle($db, $dbr, 
	  	$id,
  		$article_id,
  		$problem_id,
  		$cost
		)
    {
        $id = (int)$id;
		$article_id = mysql_escape_string($article_id);
		$problem_id = mysql_escape_string($problem_id);
		$cost= mysql_escape_string($cost);
        $r = $db->query("insert into ins_article set 
			ins_id=$id, 
			article_id='$article_id',
			problem_id='$problem_id',
			cost='$cost'");
		return $dbr->getOne("select max(id) from ins_article where ins_id=$id");	
    }

    static function updateArticle($db, $dbr, 
	  	$id,
  		$problem_id,
  		$cost
		)
    {
        $id = (int)$id;
		$problem_id = mysql_escape_string($problem_id);
		$cost= mysql_escape_string($cost);
        $r = $db->query("update ins_article set 
			problem_id='$problem_id',
			cost='$cost'
			where id=$id");
    }

    static function deleteArticle($db, $dbr, 
	  	$id
		)
    {
        $id = (int)$id;
        $r = $db->query("delete from ins_pic 
			where ins_article_id=$id");
        $r = $db->query("delete from ins_article 
			where id=$id");
    }

    static function getDocs($db, $dbr, $id)
    {
        $r = $db->query("SELECT ins_doc.*, IFNULL( users.name, ins_doc.username ) as fullusername 
	   from ins_doc LEFT JOIN users ON ins_doc.username=users.username  
	   where ins_id=$id
		ORDER BY doc_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addDoc($db, $dbr, $id,
		  $name,
		  $data
		)
    {
        $id = (int)$id;
		$name = mysql_escape_string($name);
        global $loggedUser;
        $timediff = $loggedUser->get('timezone');
        require_once 'util.php';
        
        $md5 = md5($data);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }
        
        $r = $db->query("insert into ins_doc set 
			ins_id=$id, 
			name='$name',
			data='$md5',
			username = '".$loggedUser->get('username')."',
			date = '".ServerTolocal(date("Y-m-d H:i:s"), $timediff)."'");
    }

    static function getArtPics($db, $dbr, $id)
    {
        $r = $db->query("SELECT * from ins_pic where ins_article_id=$id and hidden=0
            ORDER BY pic_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addArtPic($db, $dbr, $id,
		  $name,
		  $data
		)
    {
        $id = (int)$id;
		$name = mysql_escape_string($name);
        
        $md5 = md5($data);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }
        
        $r = $db->query("insert into ins_pic set 
			ins_article_id=$id, 
			name='$name',
			data='$md5'");
    }

    static function deleteArtPic($db, $dbr, $id
		)
    {
        $id = (int)$id;
        $r = $db->query("delete from ins_pic where
			pic_id=$id");
    }

    static function getComments($db, $dbr, $id)
    {
        $r = $db->query("SELECT t.*
					, u.deleted, IF(u.name is null, 1, 0) olduser 
			, IFNULL(u.name, t.username) username, t.username cusername 
			, IFNULL(u.name, t.username) full_username
			, IFNULL(u.name, t.username) username_name
			, t.create_date `date`
			from
			(
			select '' prefix
				, ins_id
				, create_date
				, username
				, comment
				, ins_comment.id
				, ins_comment.src
				, 0 cid
			from ins_comment where ins_id=$id
			UNION ALL
			select CONCAT('Alarm (',alarms.status,'):') as prefix
				, NULL as id
				, (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
				, alarms.username
				, alarms.comment
				, 0 id
				, ''
				, 0 cid
				from insurance
				join alarms on alarms.type='ins' and alarms.type_id=insurance.id
				where insurance.id=$id
			union all 
			select CONCAT('Ticket#',rma.rma_id) as prefix
                , NULL as id
                , rma.create_date
                , (select u.username
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='rma' and tableid=rma.rma_id
                    order by updated limit 1) cusername
                , '' as `comment`
				, 0 id
                , ''
				, rma.rma_id as cid
                from insurance
				join rma on insurance.auction_number=rma.auction_number and insurance.txnid=rma.txnid
                where insurance.id=$id
			) t LEFT JOIN users u ON t.username = u.username
			ORDER BY t.create_date");
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

    static function addComment($db, $dbr, $id,
		$username,
		$create_date,
		$comment,
		$src=''
		)
    {
        $id = (int)$id;
		$username = mysql_escape_string($username);
		$create_date = mysql_escape_string($create_date);
		$comment = mysql_escape_string($comment);
		$src = mysql_escape_string($src);
        $r = $db->query("insert into ins_comment set 
			ins_id=$id, 
			username='$username',
			create_date='$create_date',
			comment='$comment',
			src='$src'
			");
    }

    static function getSHRefunds($db, $dbr, $id)
    {
        $r = $db->query("select t.*, IFNULL(custom_title, 
				CONCAT(number, ', ', shipping_date, ', ', shipping_method)) custom_title from (
			SELECT rsr . * , tn .number , tn .date_time , tn .date_time shipping_date , sm.company_name AS shipping_method
			FROM ins_sh_refund rsr
			JOIN tracking_numbers tn ON rsr.sh_tracking_id = tn.id
			JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			WHERE rsr.ins_id = $id and rsr.sh_custom=0
			UNION SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date, sm.company_name AS shipping_method
			FROM ins_sh_refund rsr
			JOIN ins_tracking_numbers tn ON rsr.sh_tracking_id = tn.id
			JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			WHERE rsr.ins_id = $id and rsr.sh_custom=1
			) t");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addSHRefund($db, $dbr, $id,
		$sh_tracking_id,
		$sh_reason,
		$sh_value,
		$sh_decision,
		$sh_accepted,
		$sh_date,
		$sh_cancelled,
		$amount_to_refund
		)
    {
        $id = (int)$id;
		$sh_tracking_id = mysql_escape_string($sh_tracking_id);
		$sh_reason = mysql_escape_string($sh_reason);
		$sh_value = mysql_escape_string($sh_value);
		$sh_decision = mysql_escape_string($sh_decision); if ($sh_decision=='') $sh_decision='NULL';
		$sh_accepted = mysql_escape_string($sh_accepted); if ($sh_accepted=='') $sh_accepted='NULL';
		$sh_date = mysql_escape_string($sh_date);
		$amount_to_refund = mysql_escape_string($amount_to_refund);
		$q = "insert into ins_sh_refund set 
			ins_id=$id, 
		sh_tracking_id = '$sh_tracking_id',
		sh_reason = '$sh_reason',
		sh_value = '$sh_value',
		sh_decision = $sh_decision,
		sh_accepted = $sh_accepted,
		cancelled = $sh_cancelled,
		sh_date = '$sh_date',
		amount_to_refund = '$amount_to_refund'
		";
//		echo "$q<br>";
        $r = $db->query($q);
    }

   static  function addSHRefundCustom($db, $dbr, $id,
		$sh_tracking_number,
		$sh_date_time,
		$sh_shipping_method,
		$sh_shipping_date,
		$sh_username,
		$sh_reason,
		$sh_value,
		$sh_decision,
		$sh_accepted,
		$sh_date,
		$sh_cancelled,
		$amount_to_refund
		)
    {
        $id = (int)$id;
		$sh_tracking_number = mysql_escape_string($sh_tracking_number);
		$sh_date_time = "'".mysql_escape_string($sh_date_time)."'"; if ($sh_date_time=="''") $sh_date_time='NULL';
		$sh_shipping_method = "'".mysql_escape_string($sh_shipping_method)."'"; if ($sh_shipping_method=="''") $sh_shipping_method='NULL';
		$sh_shipping_date = "'".mysql_escape_string($sh_shipping_date)."'"; 
		$sh_username = "'".mysql_escape_string($sh_username)."'"; if ($sh_username=="''") $sh_username='NULL';
		$sh_reason = mysql_escape_string($sh_reason);
		$sh_value = mysql_escape_string($sh_value);
		$sh_decision = mysql_escape_string($sh_decision); if ($sh_decision=='') $sh_decision='NULL';
		$sh_accepted = mysql_escape_string($sh_accepted); if ($sh_accepted=='') $sh_accepted='NULL';
		$sh_date = mysql_escape_string($sh_date);
		$amount_to_refund = mysql_escape_string($amount_to_refund);
		$q = "insert into ins_tracking_numbers set 
			ins_id=$id, 
		date_time= $sh_date_time,
		number = '$sh_tracking_number',
		shipping_method = $sh_shipping_method,
		shipping_date = $sh_shipping_date,
		username = $sh_username
		";
//		echo "$q<br>";
        $r = $db->query($q); 
		$sh_tracking_id = $dbr->getOne('select max(id) from ins_tracking_numbers');
		$q = "insert into ins_sh_refund set 
			ins_id=$id, 
		sh_tracking_id = '$sh_tracking_id',
		sh_reason = '$sh_reason',
		sh_value = '$sh_value',
		sh_decision = $sh_decision,
		sh_accepted = $sh_accepted,
		cancelled = $sh_cancelled,
		sh_date = '$sh_date',
		amount_to_refund = '$amount_to_refund',
		sh_custom = 1
		";
//		echo "$q<br>";
        $r = $db->query($q);
    }

    static function updateSHRefund($db, $dbr, $id,
		$sh_tracking_id,
		$sh_reason,
		$sh_value,
		$sh_decision,
		$sh_accepted,
		$sh_cancelled,
		$amount_to_refund
		)
    {
        $id = (int)$id;
		$sh_tracking_id = mysql_escape_string($sh_tracking_id);
		$sh_reason = mysql_escape_string($sh_reason);
		$sh_value = mysql_escape_string($sh_value);
		$sh_decision = mysql_escape_string($sh_decision); if ($sh_decision=='') $sh_decision='NULL';
		$sh_accepted = mysql_escape_string($sh_accepted); if ($sh_accepted=='') $sh_accepted='NULL';
		$amount_to_refund = mysql_escape_string($amount_to_refund);
        $r = $db->query("update ins_sh_refund set 
			sh_reason = '$sh_reason',
			sh_value = '$sh_value',
			sh_decision = $sh_decision,
			cancelled = $sh_cancelled,
			amount_to_refund = '$amount_to_refund',
			sh_accepted = $sh_accepted
		where sh_tracking_id=$sh_tracking_id 
			and ins_id=$id
		");
    }

    static function getSHRefundsList($db, $dbr, $cancelled)
    {
        $r = $db->query("SELECT rsr . * , tn .number , tn .date_time , tn .date_time shipping_date , sm.company_name AS shipping_method
				, auction.name_shipping cust_name
				, CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
			FROM rma_sh_refund rsr
			JOIN tracking_numbers tn ON rsr.sh_tracking_id = tn.id
			JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			JOIN rma ON rma.rma_id = rsr.rma_id
			JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
			WHERE rsr.sh_custom=0 $cancelled
			UNION SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date, sm.company_name AS shipping_method
				, auction.name_shipping cust_name
				, CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
			FROM rma_sh_refund rsr
			JOIN rma_tracking_numbers tn ON rsr.sh_tracking_id = tn.id
			JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			JOIN rma ON rma.rma_id = rsr.rma_id
			JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
			WHERE rsr.sh_custom=1 $cancelled");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function getSHRefundsIDs($db, $dbr, $ids)
    {
        $r = $db->query("SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date , sm.company_name AS shipping_method
				, auction.name_shipping cust_name
				, CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
			FROM rma_sh_refund rsr
			JOIN tracking_numbers tn ON rsr.sh_tracking_id = tn.id
			JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			JOIN rma ON rma.rma_id = rsr.rma_id
			JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
			WHERE rsr.sh_custom=0 and rsr.sh_tracking_id in ($ids)
			UNION SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date, sm.company_name AS shipping_method
				, auction.name_shipping cust_name
				, CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
			FROM rma_sh_refund rsr
			JOIN rma_tracking_numbers tn ON rsr.sh_tracking_id = tn.id
			JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
			JOIN rma ON rma.rma_id = rsr.rma_id
			JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
			WHERE rsr.sh_custom=1 and rsr.sh_tracking_id in ($ids)");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function getPayments($db, $dbr, $id)
    {
        $r = $db->query("SELECT * from ins_payment where ins_id=$id
		ORDER BY payment_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addPayment($db, $dbr, $id,
  		$account,
		$amount,
		$username,
		$exported,
		$payment_date,
		$comment
		)
    {
        $id = (int)$id;
		$account = mysql_escape_string($account);
		$amount = mysql_escape_string($amount);
		$username = mysql_escape_string($username);
		$exported = mysql_escape_string($exported);
		$payment_date = mysql_escape_string($payment_date);
		$comment = mysql_escape_string($comment);
        $r = $db->query("insert into ins_comment set 
			ins_id=$id, 
			account='$account',
			amount='$amount',
			exported='$exported',
			username='$username',
			payment_date='$payment_date',
			comment='$comment'");
    }


    static function getLogs($db, $dbr, $id)
    {
        $r = $db->query("SELECT ins_log.*, email_log.template, email_log_content.content, ins_log.time `date`
			from ins_log 
			join insurance on insurance.id=ins_log.ins_id
			left join email_log on insurance.auction_number=email_log.auction_number
				and insurance.txnid=email_log.txnid 
				and ins_log.action=email_log.template
				and ins_log.time=email_log.date
			left join prologis_log.email_log_content on email_log_content.id=email_log.id
			where ins_log.ins_id=$id
		ORDER BY ins_log.time");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($log = $r->fetchRow()) {
			if (gzdecode(base64_decode($log->content))) {
				$log->content = gzdecode(base64_decode($log->content));
			}
			$log->content = str_replace("\r", '<br/>', str_replace('"', '&quot;', $log->content));
			$log->content = str_replace("\n"," ",$log->content);
			$log->content = str_replace("\r"," ",$log->content);
			$log->content = str_replace("'","\\'",$log->content);
            $list[] = $log;
        }
        return $list;
    }

    static function addLog($db, $dbr, $id,
		$username,
  		$time,
  		$action, $to='', $from='', $smtp_server=''
		)
    {
        $id = (int)$id;
		$username = mysql_escape_string($username);
		$time = mysql_escape_string($time);
		$action = mysql_escape_string($action);
		$smtp_server = mysql_escape_string($smtp_server);
        $r = $db->query("insert into ins_log set 
			ins_id=$id, 
			username='$username',
			time='$time',
			action='$action', recipient='$to', sender='$from', smtp_server='$smtp_server'");
    }

    static function getPrinterLogs($db, $dbr, $id)
    {
        $r = $db->query("SELECT * from ins_printer_log where ins_id=$id
		ORDER BY log_date");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addPrinterLog($db, $dbr, $id,
		$username,
  		$log_date,
  		$action
		)
    {
        $id = (int)$id;
		$username = mysql_escape_string($username);
		$log_date = mysql_escape_string($log_date);
		$action = mysql_escape_string($action);
        $r = $db->query("insert into ins_printer_log set 
			ins_id=$id, 
			username='$username',
			log_date='$log_date',
			action='$action'");
    }

    function getInvoicePDF($new=0, $currency='') {
		$html = $this->getInvoice(1, 0, $_POST['customseller'], $currency);
		require_once("dompdf/dompdf_config.inc.php");
		$dompdf = new DOMPDF();
		$dompdf->load_html($html);
		$dompdf->render();
		$content = $dompdf->output();
		return $content;
// NO MORE NEED
		
        if (!$new && strlen($this->data->static_invoice_pdf) && $this->data->close_date) {
            return $this->data->static_invoice_pdf;
        }
        
		global $siteURL;
		global $english;
		$db = $this->_db;
		$dbr = $this->_dbr;
		$static = '';
		$method = new ShippingMethod ($db, $dbr, $this->get('shipping_method'));
		$auction = new Auction($db, $dbr, $this->data->auction_number, $this->data->txnid);
		$rma = new Rma($db, $dbr, $auction, $this->data->rma_id);
	    $sellerInfo = new SellerInfo($db, $dbr, $auction->get('username'));
//		$vat_info = VAT::get_vat_attribs($db, $dbr, $this, 'AU');
//		$vat = $vat_info->vat_percent;
//		if (!(int)$vat) $vat=0; else $vat=(int)$vat;
		$vat = 0;
		$auction->data->seller_web_page = $sellerInfo->get('web_page');
	    $auctiondata = $auction->data;
	    $auctiondata->seller_email = $sellerInfo->get('email');
		$auctiondata->vat_id = $sellerInfo->get('vat_id');
		if (strlen($sellerInfo->getTemplate('insurance_invoice_footer'
				, $auction->getMyLang()/*SiteToCountryCode($auction->get('siteid'))*/))) {
		    $sellerInfo_invoice_footer = nl2br(trim(htmlspecialchars(
				substitute($sellerInfo->getTemplate('insurance_invoice_footer'
					, $auction->getMyLang()/*SiteToCountryCode($auction->get('siteid'))*/), $auctiondata))));
		    $sellerInfo_invoice_footer = substitute($sellerInfo_invoice_footer, $sellerInfo->data);
		};	
	    if (!strlen($currency)) $currency = siteToSymbol($auction->data->siteid);
		$amount = 0; $payment_total = 0;
		foreach ($this->articles as $art) {
		    if (($art->problem_id==3 || $art->problem_id==7 || $art->problem_id==8 || $art->problem_id==12) && !$art->hidden){
			$amount += $art->cost;
			$total_weight += $art->weight_per_single_unit;
			$total_volume += $art->volume_per_single_unit;
		    }
		}	
		if (count($this->sh_refunds)) foreach ($this->sh_refunds as $rec) $amount += $rec->amount_to_refund;
		$shipping_cost = $dbr->getOne("select sum(ac.effective_shipping_cost) from auction_calcs ac
			join insurance i on i.auction_number=ac.auction_number and i.txnid=ac.txnid
			where i.id=".$this->data->id);
		$shipping_cost = 0;
		$amount += $shipping_cost;
		if (count($this->payments)) foreach ($this->payments as $rec) {
//			$amount -= $rec->amount;
			$payment_total += $rec->amount;
		};	
//		$total_weight = 0;
//		$total_volume = 0;

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
//		ini_set('display_errors', 'off');
	   			$fn = basename($sellerInfo->get('logo_url'));
   			$exts = explode('.', $fn); $ext = strtolower(end($exts));
   			switch ($ext) {
   	  		       case 'jpeg':
   	  		       case 'jpg':
	       		       	    $logo = imagecreatefromjpeg($sellerInfo->get('logo_url'));
	       		       	    break;
   	  		       case 'gif':
	       		       	    $logo = imagecreatefromgif($sellerInfo->get('logo_url'));
	       		       	    break;
			       case 'bmp':
	       		       	    $logo = imagecreatefromwbmp($sellerInfo->get('logo_url'));
	       		       	    break;
      			} 					     
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
        $pdf->text(10, $y, $method->get('company'));
		$y+=0.5;
        $pdf->text(10, $y, $method->get('name'));
		$y+=0.5;
        $pdf->text(10, $y, $method->get('street'));
		$y+=0.5;
        $pdf->text(10, $y, $method->get('zip').' '.$method->get('city'));
		$y+=0.5;
        $pdf->text(10, $y, $method->get('country_name'));

        $pdf->setFont('arial','B', 8);
		$y+=1.5;
        $pdf->text(1, $y, $english[61]); $pdf->text(10, $y, $english[62]);
        $pdf->setFont('arial','', 8);
		$y+=0.5;
	
        $pdf->text(1, $y, $method->get('company')); 
		$y+=0.5;
        $pdf->text(1, $y, $method->get('name')); 
			$pdf->text(10, $y, (strlen($this->get('seller_name'))?$this->get('seller_name'):$sellerInfo->get('seller_name')));
		$y+=0.5;
        $pdf->text(1, $y, $method->get('street')); 
			$pdf->text(10, $y, (strlen($this->get('seller_street'))?$this->get('seller_street'):$sellerInfo->get('street')));
		$y+=0.5;
        $pdf->text(1, $y, $method->get('zip').' '.$auction->get('city')); 
			$pdf->text(10, $y, (strlen($this->get('seller_zip'))?$this->get('seller_zip'):$sellerInfo->get('zip'))
			.' '.(strlen($this->get('seller_town'))?$this->get('seller_town'):$sellerInfo->get('town')));
		$y+=0.5;
        $pdf->text(1, $y, $method->get('country_name')); 
			$pdf->text(10, $y, (strlen($this->get('seller_country_name'))?$this->get('seller_country_name'):$sellerInfo->get('country_name')));
		
        $pdf->setFont('arial','B', 12);
		$y+=1.5;
		ini_set('display_errors', 'off');
		$barcode = imagecreatefrompng ($siteURL."barcode.php?number=".$auction->get('auction_number')."&txnid=".$auction->get('txnid')."&type=int25");
		if ($barcode) {
			imagejpeg ( $barcode, 'tmppic/tmpbarcode.jpg' );
	        $pdf->text(1, $y, $english[63]); $pdf->image('tmppic/tmpbarcode.jpg', 10, $y-1);
			unlink('tmppic/tmpbarcode.jpg');
		};	
		ini_set('display_errors', 'on');
		
		$y+=1.5;
        $pdf->setFont('arial','B', 8); $pdf->text(1, $y, $english[64]); 
	    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\s]+/', $this->data->date);
		$pdf->setFont('arial','', 8); $pdf->text(5, $y, date('d F Y', mktime($h,$m,$s,$M,$D,$Y))); 
        $pdf->setFont('arial','B', 8); $pdf->text(8, $y, $english[65]); 
		$pdf->setFont('arial','', 8); $pdf->text(11, $y, $auction->get('auction_number')); 
        $pdf->setFont('arial','B', 8); $pdf->text(15, $y, $english[66]); 
		$pdf->setFont('arial','', 8); $pdf->text(18, $y, 'INS'.$this->data->id); 

		$y+=0.5;
        $pdf->setFont('arial','B', 8); $pdf->text(1, $y, $english[67]); 
		$pdf->setFont('arial','', 8); $pdf->text(5, $y, $auction->get('tel_invoice')); 

        $pdf->setFont('arial','B', 8); $pdf->text(8, $y, $english[113]); 
		$pdf->setFont('arial','', 8); $pdf->text(11, $y, $total_weight.' kg / '.$total_volume.' m3'); 

        $pdf->setFont('arial','B', 8); $pdf->text(15, $y, $english[69]); 
	    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\s]+/', $this->data->date);
		$pdf->setFont('arial','', 8); $pdf->text(18, $y, date('d F Y', mktime($h,$m,$s,$M,$D,$Y))); 

		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setFont('arial','B', 8); 
        $pdf->setXY(1, $y);	$pdf->multiCell(2, 0.5, str_replace ( '<br>', "\n", $english[70]), 0, 'L');
        $pdf->setXY(3, $y);	$pdf->multiCell(10, 0.5, str_replace ( '<br>', "\n", $english[71]), 0, 'L');
//        $pdf->setXY(10, $y);	$pdf->multiCell(3, 0.5, str_replace ( '<br>', "\n", $english[72]), 0, 'L');
        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, str_replace ( '<br>', "\n", $english[73]), 0, 'L');
        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, str_replace ( '<br>', "\n", $english[74]), 0, 'L');
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, str_replace ( '<br>', "\n", $english[75]), 0, 'L');
		$y+=1;
		$pdf->line(1, $y, 20, $y);

		$y+=0.2;
        $pdf->setFont('arial','', 8); 
		$newy = $y; //print_r($this->articles); die();
  		if (count($this->articles)) foreach ($this->articles as $item) {
		    if (($item->problem_id==3 || $item->problem_id==7 || $item->problem_id==8 || $item->problem_id==12) && !$item->hidden) {
			$y = $newy;
	        $pdf->setXY(1, $y);	$pdf->multiCell(2, 0.5, '1', 0, 'L');
			$title = $item->article_name;
	        $pdf->setXY(3, $y);	$pdf->multiCell(10, 0.5, $title, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
//	        $pdf->setXY(10, $y);	$pdf->multiCell(3, 0.5, $item->warehouse_place, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, $currency.' '.$item->cost, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, (strlen($vat)?$vat:0).'%', 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.($item->cost), 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
		} // if small defects or broken
		} // foreach
  		if (count($this->sh_refunds)) foreach ($this->sh_refunds as $item) {
			$y = $newy;
	        $pdf->setXY(1, $y);	$pdf->multiCell(2, 0.5, '1', 0, 'L');
			$title = $item->number.', '.$item->shipping_date.', '.$item->shipping_method;
	        $pdf->setXY(3, $y);	$pdf->multiCell(10, 0.5, $title, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
//	        $pdf->setXY(10, $y);	$pdf->multiCell(3, 0.5, $item->warehouse_place, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, $currency.' '.$item->amount_to_refund, 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, (strlen($vat)?$vat:0).'%', 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
	        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.($item->amount_to_refund), 0, 'L'); if ($pdf->getY()>$newy) $newy = $pdf->getY();
		}
		$y = $newy;
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[76]), 0, 'L');
        $pdf->setXY(13, $y);	$pdf->multiCell(2, 0.5, $currency.' '.$shipping_cost, 0, 'L');
        $pdf->setXY(15, $y);	$pdf->multiCell(2, 0.5, (strlen($vat)?$vat:0).'%', 0, 'L');
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.$shipping_cost, 0, 'L');
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[77]), 0, 'L');
		$vatvalue =  sprintf("%01.2f", (($amount) / (100 + $vat) * $vat));
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.$vatvalue, 0, 'L');
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
        $pdf->setFont('arial','B', 8); 
        $pdf->setXY(1, $y);	$pdf->multiCell(12, 0.5, str_replace ( '<br>', "\n", $english[78]), 0, 'L');
        $pdf->setFont('arial','', 8); 
		$price =  sprintf("%01.2f", ($amount));
        $pdf->setXY(17, $y);	$pdf->multiCell(4, 0.5, $currency.' '.$amount, 0, 'L');
		if ($pdf->getY()>$y) $y = $pdf->getY();
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);

		$y = $pdf->getY();
		$y+=0.5;
        $pdf->setFont('arial','', 8); 
		$sellerInfo_invoice_footer = str_replace ( '<br>', '', $sellerInfo_invoice_footer);
		$sellerInfo_invoice_footer = str_replace ( '<br />', '', $sellerInfo_invoice_footer);
        $pdf->setXY(1, $y);	$pdf->multiCell(20, 0.5, $sellerInfo_invoice_footer, 0, 'L');

	if (count($this->payments)) {
		$y = $pdf->getY();
		$y+=0.5;
		$pdf->setFont('arial','B', 8); $pdf->text(1, $y, $english[90]); 
		$pdf->setFont('arial','', 8);
	    foreach ($this->payments as $payment) {
			$y+=0.5;
			$pdf->text(1, $y, $payment->payment_date); 
			$pdf->text(4, $y, $currency.' '.$payment->amount); 
		};
	};

	    $pdf->close();
	    $pdf->save($tmp . '/' . $filename, true);
		$static = file_get_contents($tmp . '/' . $filename);
		$this->data->static_invoice_pdf  = $static;
		$this->update();
		unlink($tmp . '/' . $filename);
		return $static;
	}
	
    function getInvoice($new=0, $print=0, $seller='', $currency='') {
		if (!$new && strlen($this->data->static_invoice_html) && $this->data->close_date) {
            return $this->data->static_invoice_html;
        }
        
		global $siteURL;
		$static = '';
		$file = fopen ($siteURL."static_insurance_invoice_HTML.php?id=".$this->data->id."&print=".$print."&seller=".$seller."&currency=".$currency, "r");
		if (!$file) {
		    $static .=  "<p>Unable to open remote file.\n";
		    exit;
		}
		while (!feof ($file)) {
		    $static .= fgets ($file, 1024);
		}
		fclose($file);
		if (!strlen($seller)) $this->data->static_invoice_html = $static;
		$this->update();
		return $static;
	}
	
    function getLetterPDF() {
	    global $english;
	    global $siteURL;
		global $smarty;
		$db = $this->_db;
		$dbr = $this->_dbr;
		$method = new ShippingMethod ($db, $dbr, $this->get('shipping_method'));
		$allcountries = allCountries();
		$method->data->country_name = $allcountries[$method->data->country];
		$auction = new Auction($db, $dbr, $this->get('auction_number'), $this->get('txnid'));
		$sellerInfo = new SellerInfo($db, $dbr, $auction->get('username'));
		$sellerInfo = $sellerInfo->data;
		$date = date("j F Y");
		$letter_title = (substitute($english[109], $this->data));
		$letter_text = (substitute($english[110], $this->data));
		$letter_from = $english[111];
		$letter_to = $english[112];
		$signature_url = 'signature.jpg';
		$smarty->assign('method', $method);
		$smarty->assign('sellerInfo', $sellerInfo);
		$smarty->assign('letter_title', $letter_title);
		$smarty->assign('letter_text', $letter_text);
		$smarty->assign('letter_to', $letter_to);
		$smarty->assign('letter_from', $letter_from);
		$smarty->assign('signature_url', $signature_url);
		$html = $smarty->fetch('insurance_letter.tpl');
		require_once("dompdf/dompdf_config.inc.php");
		$dompdf = new DOMPDF();
		$dompdf->load_html($html);
		$dompdf->render();
		$content = $dompdf->output();
#		header ("Content-type: application/pdf");
#		header("Content-disposition: inline; filename=insurance invoice.pdf");
		return $content;

		$db = $this->_db;
		$dbr = $this->_dbr;
		$method = new ShippingMethod ($db, $dbr, $this->get('shipping_method'));
		$allcountries = allCountries();
		$method->data->country_name = $allcountries[$method->data->country];
		$auction = new Auction($db, $dbr, $this->get('auction_number'), $this->get('txnid'));
		$sellerInfo = new SellerInfo($db, $dbr, $auction->get('username'));
		$sellerInfo = $sellerInfo->data;
		$date = date("j F Y");
		$letter_title = substitute($english[109], $this->data);
		$letter_text = substitute($english[110], $this->data);
		$letter_from = $english[111];
		$letter_to = $english[112];
		$signature_url = 'signature.jpg';
		$tmp = 'tmp'; $filename = 'export.pdf';
        $pdf =File_PDF::factory('P', 'cm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(true);
	    $pdf->addPage();
        $pdf->setFillColor('rgb', 0, 0, 0);
        $pdf->setDrawColor('rgb', 0, 0, 0);
		$pdf->setLeftMargin(2);
		$y = $pdf->getY();
        $pdf->setFont('arial','B', 8);
		$pdf->text(1, $y, $letter_from);
		$x = $pdf->getX();
        $pdf->setFont('arial','', 8);
		$pdf->text($x, $y, (strlen($this->get('seller_name'))?$this->get('seller_name'):$sellerInfo->seller_name)
			.', '.(strlen($this->get('seller_street'))?$this->get('seller_street'):$sellerInfo->street)
			.', '.(strlen($this->get('seller_zip'))?$this->get('seller_zip'):$sellerInfo->zip)
				.' '.(strlen($this->get('seller_town'))?$this->get('seller_town'):$sellerInfo->town)
			.', '.(strlen($this->get('seller_country_name'))?$this->get('seller_country_name'):$sellerInfo->country));
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$y+=0.5;

        $pdf->setFont('arial','B', 8);
		$pdf->text(1, $y, $letter_to);
		$x = $pdf->getX();
        $pdf->setFont('arial','', 8);
		$pdf->text($x, $y, $method->data->company
			.', '.$method->data->name
			.', '.$method->data->street
			.', '.$method->data->zip.' '.$method->data->city
			.', '.$method->data->country_name);
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$y+=0.5;

		$pdf->text(1, $y, $sellerInfo->town
			.', '.$date);
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$y+=0.5;

        $pdf->setFont('arial','B', 8);
		$pdf->text(1, $y, $letter_title);
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$y+=0.5;

        $pdf->setFont('arial','', 8);
		$pdf->text(1, $y, $letter_text);
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$y+=0.5;

		$pdf->text(1, $y, 'Michael Widmer');
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$y+=0.5;

		ini_set('display_errors', 'off');
		$logo = imagecreatefromjpeg ($siteURL.'signature.jpg');
		if ($logo) {
			imagejpeg ( $logo, 'tmppic/tmplogo.jpg' );
	        $pdf->image('tmppic/tmplogo.jpg', 1, $y);
			unlink('tmppic/tmplogo.jpg');
		};
		ini_set('display_errors', 'on');
	    $pdf->close();
	    $pdf->save($tmp . '/' . $filename, true);
		$letter = file_get_contents($tmp . '/' . $filename);
		unlink($tmp . '/' . $filename);
		return $letter;
	}
	
    function getRMAPicturesPDF() {
		$db = $this->_db;
		$dbr = $this->_dbr;

        $tmp = 'tmp/';
		$ins_article_id = array();
		foreach ($this->articles as $spec) {
			if ((!$spec->hidden && ($spec->problem_id==3 || $spec->problem_id==7 || $spec->problem_id==8 || $spec->problem_id==12)))
            {
				$ins_article_id[] = $spec->id;
            }
		}
        
        if (!count($ins_article_id)) return 0;	
		$ids = @implode (',', $ins_article_id);
		$name = Insurance::exportIDs($db, $dbr, $ids, $hidden);
        
		return file_get_contents($name);
	}
	
    function getInvoiceCustomerPDF() {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$r = $db->query('select rma_id, auction_number, txnid 
			from rma where auction_number='.$this->data->auction_number.' and txnid='.$this->data->txnid);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		$row = $r->fetchRow();
		$auction = new Auction($db, $dbr, $this->data->auction_number, $this->data->txnid);
        if (PEAR::isError($r)) {
			aprint_r($auction);
            return;
        }
		$invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));
		return $invoice->getInvoicePDF($db, $dbr, $this->data->auction_number, $this->data->txnid);
	}
	
    static function findIds($db, $dbr, $ids)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ids = mysql_escape_string($ids);
        return $dbr->getAll("SELECT auction.username_buyer, rma_problem.name as problem_name,
			i.auction_number, i.txnid, i.responsible_username ins_responsible_uname, i.date create_date, i.rma_id,
			ia.*, article.name as article_name
			, CONCAT(au_firstname_invoice.value,' ', au_name_invoice.value) name_invoice
		FROM insurance i JOIN ins_article ia ON i.id = ia.ins_id
			JOIN article ON ia.article_id=article.article_id
			LEFT JOIN rma_problem ON ia.problem_id=rma_problem.problem_id
			JOIN auction ON i.auction_number=auction.auction_number and i.txnid=auction.txnid
					left join auction_par_varchar au_name_invoice on auction.auction_number=au_name_invoice.auction_number 
						and auction.txnid=au_name_invoice.txnid and au_name_invoice.key='name_invoice'
					left join auction_par_varchar au_firstname_invoice on auction.auction_number=au_firstname_invoice.auction_number 
						and auction.txnid=au_firstname_invoice.txnid and au_firstname_invoice.key='firstname_invoice'
		WHERE ia.id in ($ids)");
    }

    static function exportIDs($db, $dbr, $ids, $hidden=true) {
		$tmp = 'tmp';
		$inss = Insurance::findIds($db, $dbr, $ids);
        $pdf =File_PDF::factory('P', 'cm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(false);
        $y = 1; $y_max = 30;
        foreach ($inss as $ins) {
            $pdf->addPage();          
            $pics = Insurance::getArtPics($db, $dbr, $ins->id);
            $pdf->setFillColor('rgb', 0, 0, 0);
            $pdf->setDrawColor('rgb', 0, 0, 0);
            $pdf->setFont('arial','B', 9);
            $pdf->write(0.4, 'Article '.$ins->article_id.': '.$ins->article_name); $pdf->newLine();
            $pdf->write(0.4, 'Customer name: '.$ins->name_invoice); $pdf->newLine();
            $pdf->write(0.4, 'Insurance case #: '.$ins->ins_id); $pdf->newLine();
            $pdf->write(0.4, 'Ticket #: '.$ins->rma_id); $pdf->newLine();
            $pdf->write(0.4, 'Ticket date: '.$ins->create_date); $pdf->newLine();
            $pdf->write(0.4, 'Responsible for Ticket: '.$ins->ins_responsible_uname); $pdf->newLine();
            $pdf->write(0.4, 'Problem: '.$ins->problem_name); $pdf->newLine();
            if (!count($pics)) {
                $pdf->write(0.4, 'No pics'); $pdf->newLine();
            }

            foreach ($pics as $pic) {
                $picture = get_file_path($pic->data);
                $img = @imagecreatefromstring ($picture);
                if ($img) {
                    if ($pdf->getY()+6.5>$y_max) 
                        $pdf->addPage();
                    $destsy = 7;
                    $destsx = $destsy * imagesx($img) / imagesy($img) ;
                    $img2 = imagecreatetruecolor ($destsx, $destsy);
                    imagecopyresized ( $img2, $img, 0, 0, 0, 0, $destsx, $destsy, imagesx($img), imagesy($img));
                    $imgname = $tmp . '/' . $pic->pic_id . '.jpg';
                    $pdf->write(0.4, $pic->name); $pdf->newLine();
                    imagejpeg($img, $imgname);
                    $pdf->image($imgname, 1, $pdf->getY(), $destsx, $destsy); $pdf->newLine();
                    $pdf->setY($pdf->getY()+7);
                    unlink($imgname);
                    imagedestroy($img);
                    imagedestroy($img2);
                } else {
                    $pdf->write(0.4, $pic->name.' is not a valid picture!'); $pdf->newLine();
                }
            }
        }
    
        $pdf->close();
	    $filename = 'export.pdf';
	    $pdf->save($tmp . '/' . $filename, true);
		return $tmp . '/' . $filename;
	} // function exportIDs

    function getSelfPDF() {
	    global $english;
	    global $siteURL;
	    global $smarty;
		$db = $this->_db;
		$dbr = $this->_dbr;
		$method = new ShippingMethod ($db, $dbr, $this->get('shipping_method'));
		$allcountries = allCountries();
		$method->data->country_name = $allcountries[$method->data->country];
		$auction = new Auction($db, $dbr, $this->get('auction_number'), $this->get('txnid'));
	    switch ($auction->get('payment_method')) {
	        case 1:
	            $auction->data->shipping_method_str =  'Regular shipping, payment in advance';
	            break;
	        case 2:
	            $auction->data->shipping_method_str = 'Regular shipping, payment on delivery';
	            break;
	        case 3:
	            $auction->data->shipping_method_str = 'Pick up yourself, payment at pickup';
	            break;
	        case 4:
	            $auction->data->shipping_method_str = 'Pick up yourself, payment in advance';
	            break;
	    }
		$sellerInfo = new SellerInfo($db, $dbr, $auction->get('username'));
//		echo $auction->get('username').'>>';
		$currency = siteToSymbol($auction->data->siteid);
		$date = date("j F Y");
		$letter_title = substitute($english[109], $this->data);
		$letter_text = substitute($english[110], $this->data);
		$letter_from = $english[111];
		$letter_to = $english[112];
		$signature_url = 'signature.jpg';
		$html = $smarty->fetch("insurance_prn.tpl");
		file_put_contents("/home/prologistics.info/public_html/tmp/selfInsCase.html", $html);
		$comand = "wkhtmltopdf \"http://www.prologistics.info/tmp/selfInsCase.html\" /home/prologistics.info/public_html/tmp/selfInsCase.pdf";
		exec($comand);
		if (file_exists("/home/prologistics.info/public_html/tmp/selfInsCase.pdf")) {
			$res = file_get_contents('/home/prologistics.info/public_html/tmp/selfInsCase.pdf');
			unlink('/home/prologistics.info/public_html/tmp/selfInsCase.pdf');
			unlink('/home/prologistics.info/public_html/tmp/selfInsCase.html');
			return $res;
		}
//		die($html);
		require_once("dompdf/dompdf_config.inc.php");
		$dompdf = new DOMPDF();
		$dompdf->load_html($html);
		$dompdf->render();
		$content = $dompdf->output();
		return $content;
	}

}
?>