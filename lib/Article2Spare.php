<?php
/**
 * RMA case
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';
require_once 'lib/Warehouse.php';
require_once 'lib/Config.php';

/**
 * RMA case
 * @package eBay_After_Sale
 */
class Article2Spare
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
    function Article2Spare($db, $dbr, $id = 0)
    {
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN ats");
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
            $r = $this->_db->query("SELECT * FROM ats WHERE id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Article2Spare : record $id does not exist");
                return;
            }
            $this->articles = Article2Spare::getArticles($db, $dbr, $id);
			$this->name = $this->_dbr->getOne("SELECT value from translation t
				where t.table_name = 'article'
				AND t.field_name = 'name'
				AND t.language = 'german'
				AND t.id = '".$this->data->article_id."'");
			$this->description = $this->_dbr->getOne("SELECT value from translation t
				where t.table_name = 'article'
				AND t.field_name = 'description'
				AND t.language = 'german'
				AND t.id = '".$this->data->article_id."'");
            $this->_isNew = false;
			$this->barcodes = $this->_dbr->getOne("
			select group_concat(ff separator '<br>') from (
				select distinct CONCAT(IFNULL(bm.id,IFNULL(opa.id,IFNULL(ats.id,IFNULL(rs.rma_spec_id,rsp.rma_spec_id)))),'/'
							,IFNULL(ats.article_id,IFNULL(bm.article_id,IFNULL(opa.article_id,IFNULL(rs.article_id,rsp.article_id))))
					,'/',b.id, '<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',max(boo.id),')\">') ff
				from barcode_object boo
				join barcode b on boo.barcode_id=b.id
				left join barcode_object bot on bot.barcode_id=b.id and bot.obj='ats'
				left join ats on bot.obj_id=ats.id
				left join barcode_object bom on bom.barcode_id=b.id and bom.obj='barcodes_manual'
				left join barcodes_manual bm on bom.obj_id=bm.id
				left join barcode_object boa on boa.barcode_id=b.id and boa.obj='op_article'
				left join op_article opa on opa.id=boa.obj_id
				left join barcode_object bor on bor.barcode_id=b.id and bor.obj='rma_spec'
				left join rma_spec rs on bor.obj_id=rs.rma_spec_id
				left join barcode_object bop on bop.barcode_id=b.id and bop.obj='rma_spec_problem'
				left join rma_spec rsp on bop.obj_id=rsp.rma_spec_id
				where bot.obj='ats' and bot.obj_id=$id and b.inactive=0
				group by b.id 
			)t
			");
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
            $this->_error = PEAR::raiseError('Article2Spare::update : no data');
        }
        foreach ($this->data as $field => $value) {
			{
	            if ($query) {
	                $query .= ', ';
	            }
	            if (($value!=='' && $value!==NULL) || $field=='comment')
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
//		echo "$command ww_order SET $query $where";
        $r = $this->_db->query("$command ats SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($r); print_r($this->data); die();
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
            $this->_error = PEAR::raiseError('Article2Spare::update : no data');
        }
		$id = (int)$this->data->id;
		$this->_db->query("DELETE FROM ats WHERE wwo_id=$id");
		$this->_db->query("DELETE FROM ats_item WHERE ats_id=$id");
        if (PEAR::isError($r)) {
            aprint_r($r); die();
        }
    }



    /**
    * @return array
    * @param object $db
    * @param object $offer
    * @desc Get all groups in an offer
    */
    static function listAll($db, $dbr, $warehouse_id=0)
    {
		if ($from_warehouse) $where.= " and ats.warehouse_id=$warehouse_id ";
		$q = "SELECT ats.*
		, CONCAT(ats.quantity, ' x ', ats.article_id, ': ', t.value) article
		, IFNULL(u.name, ats.username) username_name
		, group_concat(CONCAT(ats_item.quantity, ' x ', ats_item.article_id, ': ', ti.value) SEPARATOR '<br>') spareparts
		, w.name warehouse
		FROM ats
			left join ats_item on ats_item.ats_id=ats.id
			left JOIN translation t
				on t.table_name = 'article'
				AND t.field_name = 'name'
				AND t.language = 'german'
				AND t.id = ats.article_id
			left join warehouse w on ats.warehouse_id=w.warehouse_id
			left JOIN users u ON ats.username=u.username
			left JOIN translation ti
				on ti.table_name = 'article'
				AND ti.field_name = 'name'
				AND ti.language = 'german'
				AND ti.id = ats_item.article_id
		$where 
		group by ats.id";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
        return $r;
    }

    /**
    * @return unknown
    * @param object $db
    * @param int $id
    * @desc Get array of all articles in a group
    */
    static function getArticles($db, $dbr, $id)
    {
		$q = "select ats_item.*, article.picture_URL
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = ats_item.article_id) name
		FROM ats_item
		join article on article.article_id = ats_item.article_id and article.admin_id=0
		WHERE ats_item.ats_id = $id 
		";
//		echo $q;
        $r = $dbr->getAll($q);
		foreach($r as $k=>$dummy) {
//			$r[$k]->name = utf8_decode($r[$k]->name);
			$r[$k]->auctions = $dbr->getAll("select ats_item_auction.*, concat(auction_number,'/',txnid) auction_number_txnid
				from ats_item_auction where ats_item_id=".$r[$k]->id);
			for($i=count($r[$k]->auctions); $i<$r[$k]->quantity; $i++) {
			    $ret = new stdClass;
				$r[$k]->auctions[] = $ret;
			}
		}
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

    /**
    * @return void
    * @param object $db
    * @param integer $id
    * @param string $artid
    * @param float $high_price
    * @param float $article_price
    * @param float $shipping
    * @param integer $default
    * @param boolean $overstocked
    * @desc Add new article to group
    */
    static function addArticle($db, $dbr, 
		$ats_id, 
		$article_id,
		$qnt
	)
    {
        $ats_id = (int)$ats_id;
		$article_id = mysql_escape_string($article_id);
		$qnt = (int)$qnt;
        $r = $db->query("INSERT INTO ats_item SET 
		ats_id=$ats_id, 
		article_id = '$article_id',
		quantity = $qnt
		");
        if (PEAR::isError($r)) {
			aprint_r($r); die();
        }
		$r = mysql_insert_id();
		return $r;
    }

    /**
    * @return void
    * @param object $db
    * @param int $id
    * @param int $listid
    * @param float $high_price
    * @param ufloat $article_price
    * @param float $shipping
    * @param int $default
    * @param bool $overstocked
    * @param int $position
    * @desc Update article record
    */
    static function updateArticle($db, $dbr, 
		$id, 
		$qnt
	)
    {
        $id = (int)$id;
		if ($qnt==NULL) $qnt='quantity';
			else $qnt = (int)$qnt;
		$q = "UPDATE ats_item SET 
		quantity = $qnt
		WHERE id=$id";
//		echo $q.'<br>';
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			aprint_r($r); die();
        }
    }


}

?>