<?php

/**
 * Article
 * @package eBay_After_Sale
 */
/**
 * @ignore
 * 
 */
require_once 'PEAR.php';

/**
 * Article
 * @package eBay_After_Sale
 */
class ArticleCons {

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
     * @return Article
     * @param object $db
     * @param string $id
     * @desc Constrtuctor
     */
    function ArticleCons($db, $dbr, $id = 0) {
        $this->_db = & $db;
        $this->_dbr = & $dbr;
        $id = (int)$id;
        if (! $id) {
            $r = $this->_db->query("EXPLAIN article_cons");
            if (PEAR::isError($r)) {
                aprint_r($r);
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } 
        else {
            $r = $this->_db->query("SELECT * FROM article_cons WHERE article_id='$id'");
            if (PEAR::isError($r)) {
                aprint_r($r);
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = & PEAR::raiseError("Article::Article : record $id does not exist");
                return;
            }
            $this->_isNew = false;
            $this->reps = ArticleCons::getReps($id);
        }
    }

    /**
     * @return void
     * @param string $field
     * @param mixed $value
     * @desc Set field value
     */
    function set($field, $value) {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    /**
     * @return string
     * @param string $field
     * @desc Get field value
     */
    function get($field) {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    /**
     * @return void
     * @desc Deletes article
     */
    function delete() {
        $this->_db->query("DELETE FROM article_cons WHERE article_id='" . mysql_escape_string($this->data->article_id) . "'");
    }

    /**
     * @return boolean|object
     * @desc Updates database record
     */
    function update() {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = & PEAR::raiseError('Article::update : no data');
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
            $where = "WHERE article_id='" . mysql_escape_string($this->data->article_id) . "'";
        }
        $r = $this->_db->query("$command article_cons SET $query $where");
        if (PEAR::isError($r)) {
            aprint_r($r);
            $this->_error = $r;
        }
        return $r;
    }

    /**
     * @return array
     * @param object $db
     * @desc Get array of all articles
     */
    static function listAll(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $attr = '', $company_id = 0, $category_id = 0, $dead = 0, $mode = 'all', $article_id = '', $name = '') {
        global $supplier_filter;
        global $supplier_filter_str;
        if (strlen($supplier_filter))
            $supplier_filter_str1 = " and oc.id in ($supplier_filter) ";

        $article_ids = ' and aa.cons_id=a.article_id ';
        $warehouses = Warehouse::listArray($db, $dbr);
        $warehouses[0] = 'Total';
        $str1 = '';
        $str2 = '';
        $str3 = '';
        foreach ($warehouses as $id => $warehouse) {
            $str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN article aa force key for join (cons_id) ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids 
					and o.manual=0
					AND o.sent=0
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
						+ (select IFNULL((select SUM(1/*o.new_article_qnt*/)
					FROM (select * from orders where new_article_id is not null) o
					JOIN article aa force key for join (cons_id) ON o.new_article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids 
					AND o.sent=0
					AND o.new_article AND not o.lost_new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0)) 
						+ (select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					JOIN article aa force key for join (cons_id) ON wwa.article_id=aa.article_id and aa.admin_id=0
					WHERE 1
					$article_ids 
					and not wwa.taken
					and (wwa.reserved_warehouse=$id or $id=0)),0)) 
						as reserved_$id
			,(select IFNULL((select sum(ah.quantity) from article_history ah
					JOIN article aa force key for join (cons_id) ON ah.article_id=aa.article_id and aa.admin_id=0
					WHERE 1
					$article_ids
					and (ah.warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(opa.qnt_delivered) from op_article opa
					JOIN article aa force key for join (cons_id) ON opa.article_id=aa.article_id and aa.admin_id=0
					where 1
					$article_ids
					and opa.add_to_warehouse
					and (opa.warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa force key for join (cons_id) ON rs.article_id=aa.article_id and aa.admin_id=0
					left JOIN warehouse w ON `default`
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					WHERE rs.problem_id in (4,11) 
					$article_ids
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id  or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa force key for join (cons_id) ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					$article_ids 
					and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa force key (cons_id) ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					$article_ids  $stock_date_r2
					and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa force key (cons_id) ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					$article_ids  $stock_date_r2
					and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa force key for join (cons_id) ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					$article_ids 
					and (rs.warehouse_id=$id or $id=0)),0))
			) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN article aa force key for join (cons_id) ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids 
					and o.manual=0
					AND o.sent
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa force key for join (cons_id) ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					$article_ids
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_taken_in_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa force key for join (cons_id) ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					$article_ids
				and (wwo_article.from_warehouse=$id or $id=0)),0)) as driver_taken_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa force key for join (cons_id) ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					$article_ids
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_delivered_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa force key for join (cons_id) ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					$article_ids
				and (wwo_article.to_warehouse=$id or $id=0)),0)) as driver_delivered_in_$id
			,(select IFNULL((select sum(ats.quantity)
				from ats
					JOIN article aa force key (cons_id) ON ats.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					$article_ids 
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_out_$id
			,(select IFNULL((select sum(ats_item.quantity)
				from ats
					JOIN ats_item ON ats.id=ats_item.ats_id
					JOIN article aa force key (cons_id) ON ats_item.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					$article_ids 
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_in_$id
					";
            $str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id 
				+ ats_in_$id - ats_out_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id-reserved_$id 
				+ ats_in_$id - ats_out_$id as available_$id";
        }
        $where_rep = '';
        if ($rep == 'non-rep') {
            $where_rep = ' and not exists (select null from article_rep where rep_id=a.article_id) ';
        } elseif ($rep == 'rep') {
            $where_rep = ' and exists (select null from article_rep where rep_id=a.article_id) ';
        }
        if ($attr == 'uncategoried')
            $attr = ' and (category_id is NULL or category_id = 0) ';
        elseif ($attr == 'unsupplied')
            $attr = " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";

        if ($company_id > 0) {
            $filter = ' and a.company_id = ' . $company_id;
        } else if ($company_id < 0) {
            $filter = " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
        }
        if ($category_id > 0) {
            $filter .= ' and category_id = ' . $category_id;
        } else if ($category_id < 0) {
            $filter .= " and (category_id IS NULL or category_id='' or category_id = 0)";
        }
        /* 		if ($dead)
          $filter .= ' and deleted';
          else
          $filter .= ' and not deleted';
         */
        if (trim(mysql_escape_string($article_id)))
            $filter .= " and a.article_id like '%" . mysql_escape_string($article_id) . "%' ";
        if (trim(mysql_escape_string($name)))
            $filter .= " and UPPER(a.name) like UPPER('%" . mysql_escape_string($name) . "%') ";

        if ($mode == 'active')
            $filter .= ' and oc.active ';
        elseif ($mode == 'passive')
            $filter .= ' and not oc.active ';
        $q = "select t.*
				$str2
				from (
			SELECT distinct a.*
				$str1 
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa 
					JOIN article aa force key (cons_id) ON opa.article_id=aa.article_id and aa.admin_id=0
				where 1
				$article_ids
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa 
					JOIN article aa force key (cons_id) ON opa.article_id=aa.article_id and aa.admin_id=0
				where 1
				$article_ids
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2
				), 0) as order_on_way_qnt
			, oc.name as company_name
			, oc.period
			, oc.active as company_active
			FROM article_cons a
			LEFT JOIN op_company oc on a.company_id=oc.id
			WHERE 1=1 " . $attr . $filter . $where_rep . " and a.article_id is not null
			$supplier_filter_str1
			) t 
			ORDER BY article_id";

        $list = $dbr->getAll($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    static function listBySuppllier($db, $dbr, $supplier_id) {
        $r = $db->query("SELECT a.* FROM article_cons a 
		WHERE a.company_id=" . $supplier_id);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function listArrayBySuppllier($db, $dbr, $supplier_id) {
        $ret = array();
        $list = Article::listBySuppllier($db, $dbr, $supplier_id);
        foreach ((array) $list as $article) {
            $ret[$article->article_id] = $article->article_id . ' : ' . $article->name;
        }
        return $ret;
    }

    /**
     * @return unknown
     * @param unknown $db
     * @desc Return array of aricles suitable for
     * use with Smarty's {html_options}
     */
    static function listArray($db, $dbr, $attr = '') {
        /*        $ret = array();
          $list = ArticleCons::listAll($db, $dbr, $attr);
          foreach ((array)$list as $article) {
          $ret[$article->article_id] = $article->article_id . ' : ' .$article->name;
          } */
        global $supplier_filter;
        global $supplier_filter_str;
        if (strlen($supplier_filter))
            $supplier_filter_str1 = " and oc.id in ($supplier_filter) ";
        if ($attr == 'uncategoried')
            $attr = ' and (category_id is NULL or category_id = 0) ';
        elseif ($attr == 'unsupplied')
            $attr = " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
        $ret = $dbr->getAssoc("
			select article_id, CONCAT(article_id, ' : ', a.name)
			FROM article_cons a
			LEFT JOIN op_company oc on a.company_id=oc.id
			WHERE 1=1 " . $attr . $filter . $where_rep . " and a.article_id is not null
			$supplier_filter_str1
			ORDER BY article_id			
		");
        return $ret;
    }

    /**
     * @return boolean
     * @param array $errors
     * @desc validates fields
     */
    function validate(&$errors) {
        $errors = array();
        if (!strlen($this->data->article_id)) {
            $errors[] = 'Article number is required';
        } elseif ($this->_isNew) {
            $id = mysql_escape_string($this->data->article_id);
            $r = $this->_db->query("SELECT COUNT(*) AS n FROM article_cons WHERE article_id='$id'");
            $r = $r->fetchRow();
            if ($r->n) {
                $errors[] = 'Duplicate article number';
            }
        }
        if (empty($this->data->name)) {
            $errors[] = 'Name is required';
        }
        if (!(int) $this->_db->getOne("select iid from article_cons where article_id='" . mysql_real_escape_string($this->data->article_id) . "'")) {
            $have_article = (int) $this->_db->getOne("select iid from article where admin_id=0 
				and article_id='" . mysql_real_escape_string($this->data->article_id) . "'");
            if ($have_article) {
                $errors[] = "We already have an article with this ID";
            }
        }
        return !count($errors);
    }

    /**
     * Get all reps
     * 
     * @param mixed $article_id
     * @return type
     */
    static function getReps($article_id) {
        $db = label\DB::getInstance(label\DB::USAGE_WRITE);
        $dbr = label\DB::getInstance(label\DB::USAGE_READ);
        
        $warehouses = \Warehouse::listArray($db, $dbr);
        $warehouses[0] = 'Total';
        
        $sub_query_1 = '';
        $sub_query_2 = '';
        
        foreach ($warehouses as $id => $warehouse) {
            $sub_query_1 .= " , (select IFNULL((select SUM(o.quantity)
                FROM orders o
                join article a1 on o.article_id=a1.article_id and o.manual=a1.admin_id
                JOIN auction au ON o.auction_number = au.auction_number
                AND o.txnid = au.txnid
                WHERE a1.article_id = a.article_id and o.manual=0
                AND o.sent
                AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
                    + (select IFNULL((select SUM(1/*o.new_article_qnt*/)
                FROM (select * from orders where new_article_id is not null) o
                join article a1 on o.new_article_id=a1.article_id and a1.admin_id=0
                JOIN auction au ON o.auction_number = au.auction_number
                AND o.txnid = au.txnid
                WHERE a1.article_id = a.article_id
                AND o.sent=0
                AND o.new_article AND not o.lost_new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
                AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0)) 
                    + (select IFNULL((select SUM(wwa.qnt)
                FROM wwo_article wwa
                join article a1 on wwa.article_id=a1.article_id and a1.admin_id=0
                WHERE a1.article_id = a.article_id
                and not wwa.taken
                and (wwa.reserved_warehouse=$id or $id=0)),0)) 
                        as reserved_$id
                    ,(select IFNULL((select sum(quantity) 
                from article_history 
                join article a1 on article_history.article_id=a1.article_id and a1.admin_id=0
                WHERE a1.article_id = a.article_id 
                and (article_history.warehouse_id=$id or $id=0)),0)) as inventar_$id
                    ,(select IFNULL((select sum(qnt_delivered) 
                from op_article 
                join article a1 on op_article.article_id=a1.article_id and a1.admin_id=0
                            where a1.article_id=a.article_id and add_to_warehouse
                            and (op_article.warehouse_id=$id or $id=0)),0)) as order_$id
                ,((select IFNULL((SELECT count(*) as quantity
                        FROM rma r
                        join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
                        JOIN rma_spec rs ON r.rma_id=rs.rma_id
                        left JOIN warehouse w ON `default`
                        LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
                        left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
                        join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
                        WHERE rs.problem_id in (4,11) and a1.article_id = a.article_id
                        and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id or $id=0)),0))
                -
                (select IFNULL((SELECT count(*)
                        FROM rma r
                        JOIN rma_spec rs ON r.rma_id=rs.rma_id
                        join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
                        WHERE rs.add_to_stock = 0 and rs.back_wrong_delivery=1 
                        and a1.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
                +
                (select IFNULL((SELECT count(*)
                        FROM rma r
                        JOIN rma_spec rs ON r.rma_id=rs.rma_id
                        join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
                        WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
                        and a1.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
                ) as rma_$id
                    ,(select IFNULL((select SUM(o.quantity)
                FROM orders o
                join article a1 on o.article_id=a1.article_id and o.manual=a1.admin_id
                JOIN auction au ON o.auction_number = au.auction_number
                AND o.txnid = au.txnid
                WHERE a1.article_id = a.article_id and o.manual=0
                AND o.sent
                AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id
                    ,(select IFNULL(sum(o.quantity),0)
                from orders o
                join article a1 on o.article_id=a1.article_id and o.manual=a1.admin_id
                join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
                join invoice i on au.invoice_number=i.invoice_number
                where o.sent=0 and au.deleted=0
                    and (au.paid=1 or (au.payment_method in ('2','3','4')))
                    and a1.article_id = a.article_id and o.manual=0
                    and (o.reserve_warehouse_id=$id or $id=0)) as ready2ship_$id
                ";
            
            $sub_query_2 .= " , inventar_$id+order_$id+rma_$id-sold_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id-reserved_$id as available_$id ";
        }
        
        if (is_array($article_id)) {
            $article_id = implode(', ', array_map('intval', $article_id));
        }
        else {
            $article_id = (int)$article_id;
        }
        
        $query = "select t.*
				$sub_query_2
				from (
			SELECT distinct a.*
				$sub_query_1  
			, t_article_name.value as article_short_name
			, opc.name supplier
			FROM article a
			left join op_company opc on a.company_id=opc.id
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
            WHERE a.cons_id IN ($article_id) and a.admin_id=0) t";

        return $dbr->getAll($query);
    }

    static function addRep(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $article_id, $newrep_id) {
        $db->query("update article set cons_id='$article_id' where article_id='$newrep_id'");
    }

    static function deleteRep(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id) {
        $db->query("update article set cons_id=NULL where article_id='$id'");
    }

    /**
     * Get next not used article_id
     * @return id
     */
    public function getNextId()
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        
        $id = $db->getOne("select
                IFNULL(min(a.article_id+1), 100) id
            from article_cons a
                left join article_cons a1 on a1.article_id = (a.article_id+1)
            where a.article_id >= 100 and a1.article_id is null");
        
        return $id;
    }
}

