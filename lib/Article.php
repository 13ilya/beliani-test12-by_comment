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
require_once 'lib/Warehouse.php';
/**
 * Article
 * @package eBay_After_Sale
 */
class Article
{
	const DOCS_USAGE_SITE = 1;
	const DOCS_USAGE_MAIL = 2;
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
    function Article($db, $dbr, $id = '', $admin=-1, $complete=1, $lang='german')
    {
#		global $debug;
#		global $time;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Article::Article expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        
        $this->_db = $db;
        $this->_dbr = $dbr;
        $id = mysql_real_escape_string($id);
        
        if (!strlen($id)) {
            $r = $this->_db->query("EXPLAIN article");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
			if (($admin=='') && ($id == '')) {
				$this->data->article_id = '';
				$this->data->admin_id = $dbr->getOne(
		            "SELECT IFNULL(MAX(admin_id+1), 1) FROM article WHERE admin_id>0");
			}
			$this->data->admin_id = $admin==-1 ? 0 : $this->data->admin_id;
            $this->_isNew = true;
        } 
        else {
			$this->data->admin_id = $admin==-1 ? 0 : $this->data->admin_id;
            $r = $this->_db->query("SELECT * FROM article WHERE article_id='$id' AND admin_id=".$this->data->admin_id);
if ($debug) {echo 'new Article0: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            
            
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Article::Article : record $id does not exist");
                return;
            }
            
            $this->data->title = $this->_dbr->getOne("SELECT IFNULL(
                (SELECT value
                    FROM translation
                    WHERE table_name = 'article'
                    AND field_name = 'name'
                    #AND language = '$lang'
                    AND id = '{$this->data->article_id}'
                    order by IF(language = '$lang' AND value<>'',0,IF(value<>'',1,2)) limit 1
                    ), '')");
              $this->data->description = $this->_dbr->getOne("SELECT IFNULL(
                (SELECT value
                    FROM translation
                    WHERE table_name = 'article'
                    AND field_name = 'description'
                    #AND language = '$lang'
                    AND id = '{$this->data->article_id}'
                    order by IF(language = '$lang' AND value<>'',0,IF(value<>'',1,2)) limit 1
                    ), '')");
            
            $this->_isNew = false;
            if ($complete>=0) {
if ($debug) {echo 'new Article1: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$this->materials = Article::getMaterials($db, $dbr, $id, $lang);
				$this->used_as_materials = Article::getMaterialsVV($db, $dbr, $id, $lang);
if ($debug) {echo 'new Article2: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$this->aliases = Article::getAliases($db, $dbr, $id);
if ($debug) {echo 'new Article3: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
	            $r = $this->_dbr->getAll("select * from custom_number where id=".$this->data->custom_number_id);
if ($debug) {echo 'new Article4: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
	            if (PEAR::isError($r)) aprint_r($r);
				if (count($r)) {
					$r = $r[0];
					$this->data->custom_number_eu = $r->custom_number_eu;
					$this->data->custom_tarif_eu = $r->custom_tarif_eu;
					$this->data->custom_number_ch = $r->custom_number_ch;
					$this->data->custom_tarif_ch = $r->custom_tarif_ch;
					$this->data->custom_number_ca = $r->custom_number_ca;
					$this->data->custom_tarif_ca = $r->custom_tarif_ca;
					$this->data->custom_number_us = $r->custom_number_us;
					$this->data->custom_tarif_us = $r->custom_tarif_us;
				}	
			}
            if ($complete==1) {
				$this->docs = Article::getDocs($db, $dbr, $id, 0, 'doc', false);
if ($debug) {echo 'new Article5: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$this->pics = Article::getDocs($db, $dbr, $id, 0, 'pic', true);
if ($debug) {echo 'new Article6: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
//				$this->senddocs = Article::getDocs($db, $dbr, $id, 1);
				$this->senddocs = Article::getDocsTranslated($db, $dbr, $id, 1, 'doc', $lang, false);
if ($debug) {echo 'new Article7: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$this->parcels = Article::getParcels($db, $dbr, $id);
if ($debug) {echo 'new Article8: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$this->real_parcels = Article::getRealParcels($db, $dbr, $id);
if ($debug) {echo 'new Article9: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$this->reps = Article::getReps($db, $dbr, $id);
if ($debug) {echo 'new Article10: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
	    	}		
//			$this->suppliers = Article::getSuppliers($db, $dbr, $id);
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
    * @return void
    * @desc Deletes article
    */
    function delete()
    {
        $this->_db->query("DELETE FROM article WHERE article_id='" . mysql_real_escape_string($this->data->article_id) . "'");
    }
    
    /**
    * @return boolean|object
    * @desc Updates database record
    */
    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Article::update : no data');
        }
        foreach ($this->data as $field => $value) {
			if (in_array($field, array(
						'title'
						,'custom_number_ca'
						,'custom_tarif_ca'
						,'custom_number_us'
						,'custom_tarif_us')
						)) continue;
            if ($query) {
                $query .= ', ';
            }
            
            if ($field == 'group_id' && !$value) {
                $query .= "`$field`=NULL";
            } else {
                $query .= "`$field`='" . mysql_real_escape_string($value) . "'";
            }
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE article_id='" . mysql_real_escape_string($this->data->article_id) . "' 
			and admin_id='" . mysql_real_escape_string($this->data->admin_id) . "'";
        }
		$q = "$command article SET $query $where";
        $r = $this->_db->query($q);
//		echo $q;
        if (PEAR::isError($r)) {
			aprint_r($r);
            $this->_error = $r->message;
        }
        return $r;
    }
    /**
    * @return array
    * @param object $db
    * @desc Get array of all articles
    */
    static function listAll($db, $dbr, $attr='', $company_id=0, $category_id=0, $dead=0, $mode='all', $article_id='', $name='', $rep='')
    {
		global $supplier_filter;
		global $supplier_filter_str;
		$def_warehouse_id = (int)$dbr->getOne("select warehouse_id from warehouse where `default`");
		if (strlen($supplier_filter))
			$supplier_filter_str1 = " and (oc.id in (0, $supplier_filter) OR oc.id is null) ";
		if (!strlen($mode)) return;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Article::list expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
		$warehouses = Warehouse::listArray($db, $dbr);
//		$warehouses[0] = 'Total';
		$str1 = ''; $str2 = ''; $str3 = '';
		foreach ($warehouses as $id=>$warehouse) {
			$str1 .= ", 
					(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					AND o.sent=0
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0))
						+ (select IFNULL((select SUM(1/*o.new_article_qnt*/)
					FROM (select * from orders where new_article_id is not null) o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.new_article_id = a.article_id
					AND o.new_article AND not o.lost_new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
						+ (select IFNULL((select SUM(o.quantity)
					FROM wwo_article wwa
					WHERE wwa.article_id = a.article_id and not wwa.taken
					and (wwa.reserved_warehouse=$id or $id=0)),0))
					 as reserved_$id
			,(select IFNULL((select sum(quantity) from article_history 
					WHERE article_id = a.article_id 
					and (warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(qnt_delivered) from op_article 
						where article_id=a.article_id and add_to_warehouse
						and (warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					WHERE rs.problem_id in (4,11) and rs.article_id = a.article_id
					and (auw.warehouse_id=$id or (auw.warehouse_id is null and $def_warehouse_id=$id) or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					AND o.sent
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id
			,IFNULL((select purchase_price
					FROM article_import
					join warehouse on article_import.country_code = warehouse.country_code
					WHERE article_import.article_id = a.article_id 
					and (warehouse.warehouse_id=$id or $id=0)
					order by import_date desc limit 1), 
						(select purchase_price
					FROM article_import
					WHERE article_import.article_id = a.article_id 
					order by import_date desc limit 1)
					) as purchase_price_$id
			,IFNULL((select total_item_cost
					FROM article_import
					join warehouse on article_import.country_code = warehouse.country_code
					WHERE article_import.article_id = a.article_id 
					and (warehouse.warehouse_id=$id or $id=0)
					order by import_date desc limit 1),
						(select total_item_cost
					FROM article_import
					WHERE article_import.article_id = a.article_id 
					order by import_date desc limit 1)
					) as total_item_cost_$id
					";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id-reserved_$id as available_$id";
			$str3 .= ", pieces_$id*purchase_price_$id as total_$id
			, pieces_$id*total_item_cost_$id as totaltotal_item_cost_$id
			, available_$id*total_item_cost_$id as totaltotal_available_item_cost_$id";
		}
	$where_rep='';
	if ($rep=='non-rep') {
		$where_rep=' and not exists (select null from article_rep where rep_id=a.article_id) ';
	} elseif ($rep=='rep') {
		$where_rep=' and exists (select null from article_rep where rep_id=a.article_id) ';
	}
		if ($attr=='uncategoried')
			$attr = ' and (category_id is NULL or category_id = 0) ';
		elseif ($attr=='unsupplied')
			$attr = " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
			
		if ($company_id>0) {
			$filter = ' and a.company_id = '.$company_id;
		} else if ($company_id<0) {
			$filter = " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
		}
		if ($category_id>0) {
			$filter .= ' and category_id = '.$category_id;
		} else if ($category_id<0) {
			$filter .= " and (category_id IS NULL or category_id='' or category_id = 0)";
		}
		if ($dead===1)
			$filter .= ' and deleted';
		elseif ($dead===0)
			$filter .= ' and not deleted';
			
		if (trim(mysql_real_escape_string($article_id)))
			$filter .= " and a.article_id like '".mysql_real_escape_string($article_id)."' ";
		if (trim(mysql_real_escape_string($name)))
			$filter .= " and UPPER(a.name) like UPPER('%".mysql_real_escape_string($name)."%') ";
		if ($mode=='active') 
			$filter .= ' and oc.active ';
		elseif ($mode=='passive') 
			$filter .= ' and not oc.active ';
        
        $script = basename($_SERVER['SCRIPT_FILENAME']);
        
		$q = "select /* $script -> Article::listAll */ tt.*
				$str3
				from (
			select t.*
				$str2
				from (
			SELECT distinct a.article_id, a.supplier_article_id, a.deleted, a.total_item_cost, a.purchase_price
				$str1 
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2
				), 0) as order_on_way_qnt
			, oc.name as company_name
			, concat(ifnull(oc.name,''),a.ordering) as company_name_ordering
			, oc.period
			, oc.active as company_active
			,(select count(*) from article_rep where rep_id=a.article_id) as is_rp	
			, t_article_name.value as article_short_name
			, t_article_description.value as description
			FROM article a
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
			LEFT JOIN translation t_article_description ON t_article_description.table_name = 'article'
				AND t_article_description.field_name = 'description'
				AND t_article_description.language = 'german'
				AND t_article_description.id = a.article_id
			LEFT JOIN op_company oc on a.company_id=oc.id
			left join warehouse w on w.warehouse_id=a.warehouse_id and w.inactive=0
			WHERE a.admin_id=0 ".$attr.$filter.$where_rep." and a.article_id is not null and a.article_id<>''
			$supplier_filter_str1
			) t ) tt
			ORDER BY article_id";
//		echo $q;	die();
        $r = $dbr->getAll($q); 
		foreach ($r as $i=>$article) {
			$r[$i]->available_0 = 0;
			$r[$i]->inventar_0 = 0;
			$r[$i]->order_0 = 0;
			$r[$i]->rma_0 = 0;
			$r[$i]->sold_0 = 0;
			$r[$i]->pieces_0 = 0;
			$r[$i]->total_0 = 0;
			$r[$i]->totaltotal_item_cost_0 = 0;
			$r[$i]->totaltotal_available_item_cost_0 = 0;
			foreach ($warehouses as $id=>$warehouse) {
				$available = 'available_'.$id;
				$inventar = 'inventar_'.$id;
				$order = 'order_'.$id;
				$rma = 'rma_'.$id;
				$sold = 'sold_'.$id;
				$reserved = 'reserved_'.$id;
				$pieces = 'pieces_'.$id;
				$total = 'total_'.$id;
				$totaltotal_item_cost = 'totaltotal_item_cost_'.$id;
				$totaltotal_available_item_cost = 'totaltotal_available_item_cost_'.$id;
				$r[$i]->available_0 += $r[$i]->$available;
				$r[$i]->inventar_0 += $r[$i]->$inventar;
				$r[$i]->order_0 += $r[$i]->$order;
				$r[$i]->rma_0 += $r[$i]->$rma;
				$r[$i]->sold_0 += $r[$i]->$sold;
				$r[$i]->pieces_0 += $r[$i]->$pieces;
				$r[$i]->total_0 += $r[$i]->$total;
				$r[$i]->totaltotal_item_cost_0 += $r[$i]->$totaltotal_item_cost;
				$r[$i]->totaltotal_available_item_cost_0 += $r[$i]->$totaltotal_available_item_cost;
			}	
		} 
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($r);
            return;
        }
        return $r;
    }
    static function listAllParams(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $params)
    {
		/* to make it fast, for wares_ship=15 scan only these articles
select distinct article_id from orders where send_warehouse_id=15
union
select distinct article_id from orders where new_article_warehouse_id=15
union
select distinct article_id from op_article where warehouse_id=15
union
select distinct article_id from rma_spec where returned_warehouse_id=15 or warehouse_id=15 or 15=(select warehouse_id from warehouse where `default`)
union
select distinct article_id from wwo_article wwa
join ww_order wwo on wwo.id=wwa.wwo_id
LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
where wwa.from_warehouse=15 or wwa.to_warehouse=15 or w_driver.warehouse_id=15
union
select distinct article_id from ats where warehouse_id=15
union
select distinct ats_item.article_id from ats JOIN ats_item ON ats.id=ats_item.ats_id where warehouse_id=15
union
select distinct article_id from article_history where warehouse_id=15
		*/
		require_once 'lib/ShopCatalogue.php';
		$shopCatalogue = new Shop_Catalogue($db, $dbr, 1);
		global $supplier_filter;
		global $supplier_filter_str;
		if (strlen($supplier_filter))
			$supplier_filter_str1 = " and (oc.id in (0, $supplier_filter) OR oc.id is null) ";
//		if (!strlen($params['mode'])) return;
//		print_r($params);
	$andor = $params['andor'];
	$where_rep='';
	if (isset($params['attr']['non-rep'])) {
		$where_rep=' and not exists (select null from article_rep where rep_id=a.article_id) ';
	} elseif (isset($params['attr']['rep'])) {
		$where_rep=' and exists (select null from article_rep where rep_id=a.article_id) ';
	}
		if (strlen($params['pp0'])) {
			switch ($params['pp0']) {
				case 'pp0':
					$q = "select ai.article_id
				from article_import ai
				join article a on a.article_id=ai.article_id and a.admin_id=0
				left join article_rep ar on ar.rep_id=a.article_id
				where a.deleted=0 and ar.id is null
				group by article_id";
					break;
				case 'pp0_rep':
					$q = "select ai.article_id
				from article_import ai
				join article a on a.article_id=ai.article_id and a.admin_id=0
				left join article_rep ar on ar.rep_id=a.article_id
				where a.deleted=0 and ar.id is not null
				group by article_id";
					break;
				case 'pp0_cons':
					$q = "select a.cons_id article_id
				from article_import ai
				join article a on a.article_id=ai.article_id and a.admin_id=0
				left join article_rep ar on ar.rep_id=a.article_id
				where a.deleted=0 and a.cons_id is not null and a.cons_id<>''
				group by a.cons_id";
					break;
				case 'pp0_eol':
					$q = "select ai.article_id
				from article_import ai
				join article a on a.article_id=ai.article_id and a.admin_id=0
				left join article_rep ar on ar.rep_id=a.article_id
				where a.deleted=1
				group by article_id";
					break;
				case 'pp0_all':
					if (isset($params['cons'])) {
					$q = "select a.cons_id article_id
				from article_import ai
				join article a on a.article_id=ai.article_id and a.admin_id=0
				left join article_rep ar on ar.rep_id=a.article_id
				where a.deleted=0 and a.cons_id is not null and a.cons_id<>''
				group by a.cons_id";
					} else {
					$q = "select ai.article_id
				from article_import ai
				join article a on a.article_id=ai.article_id and a.admin_id=0
				left join article_rep ar on ar.rep_id=a.article_id
				where 1
				group by article_id";
					}
					break;
			}
			$pp0_ids = $dbr->getOne("select group_concat(concat(\"'\",article_id,\"'\")) from ($q
				having group_concat(ai.total_item_cost*ai.purchase_price order by ai.import_date desc) like '0.0000%') t");
			if (!strlen($pp0_ids)) $pp0_ids = '0';
			$filter .= " and a.article_id in ($pp0_ids)";
		}
		if (strlen($params['merchant_article_id'])) {
			$attr .= " and mi.merchant_article_id like '%".$params['merchant_article_id']."%'";
		}
		if ($params['eolif0_avail']) {
			$attr .= " and a.eolif0ava = ".$params['eolif0_avail'];
			if (strlen($params['eolif0_avail_eol'])) {
				$attr .= " and  a.deleted=".$params['eolif0_avail_eol'];
			}
		}
		if ($params['eolif0_stock']) {
			$attr .= " and a.eolif0 = ".$params['eolif0_stock'];
			if (strlen($params['eolif0_stock_eol'])) {
				$attr .= " and  a.deleted=".$params['eolif0_stock_eol'];
			}
		}
		if (isset($params['group'])) {
			$attr .= " and a.article_id in ('".implode("','", $params['group'])."') ";
		}
		if (isset($params['attr']['uncategoried']))
			$attr .= ' and (category_id is NULL or category_id = 0) ';
		elseif (isset($params['attr']['unsupplied']))
			$attr .= " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
		if (count($params['company_ids'])) {
			$filter .= ' and a.company_id in ('.implode(',',$params['company_ids']).")";
		} elseif ($params['cons_ids']) {
            $params['cons_ids'] = array_map('intval', array_unique($params['cons_ids']));
            $filter .= ' and a.cons_id in (' . implode(',', $params['cons_ids']) . ')';
		} else {
			if ($params['company_id']>0) {
				$filter_company = '  a.company_id = '.$params['company_id'];
			} else if ($params['company_id']==-1) {
				$filter_company = "  (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
			} else if ($params['company_id']==-2) $filter_company = '0';
				else $filter_company = ' (a.company_id in (SELECT id FROM op_company where active))';
			if ($params['inactive_company_id']>0) {
				$filter_icompany = '  a.company_id = '.$params['inactive_company_id'];
			} else if ($params['inactive_company_id']==-1) {
				$filter_icompany = "  (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
			} else if ($params['inactive_company_id']==-2) $filter_icompany = '0';
				else $filter_icompany = ' (a.company_id in (SELECT id FROM op_company where not active))';
			$filter .= " and ($filter_company OR $filter_icompany)";
		}
        
		if (in_array(-1,$params['category_id'])) {
			$params['category_id'][] = "''";
			$params['category_id'][] = "0";
		}
		if (!in_array(0,$params['category_id']) && is_array($params['category_id']) && count($params['category_id'])) {
			$filter .= " and category_id in (".implode(',',$params['category_id']).") ";
		}
		if (!in_array(0,$params['catalogue_id']) && is_array($params['catalogue_id']) && count($params['catalogue_id'])) {
			$cats = array();
			foreach ($params['catalogue_id'] as $catalogue_id) {
				$r = $shopCatalogue->listAll($catalogue_id);
				$cats[] = $catalogue_id;
				foreach($r as $cat) $cats[] .= (int)$cat->id;
			}
			$filter .= " and exists(select null
				from orders o1
				join auction au1 on o1.auction_number=au1.auction_number and o1.txnid=au1.txnid
				join saved_auctions sa1 on au1.saved_id=sa1.id
				where
				o1.article_id=a.article_id and o1.manual=a.admin_id and 
				REPLACE(SUBSTRING_INDEX( SUBSTRING_INDEX( SUBSTRING_INDEX( sa1.details, 's:17:\"shop_catalogue_id\";', -1 ) , ';', 1 ), ':', -1),'\"','') 
			in ('".implode("','",$cats)."')) ";
		}	
/*		if ($params['category_id']>0) {
			$filter .= ' and category_id = '.$params['category_id'];
		} else if ($params['category_id']<0) {
			$filter .= " and (category_id IS NULL or category_id='' or category_id = 0)";
		}*/
		$soldfromdate = trim(mysql_real_escape_string($params['soldfromdate']));
		$soldtodate = trim(mysql_real_escape_string($params['soldtodate']));
		if (strlen($soldfromdate) || strlen($soldtodate)) {
			$soldfromdate = strlen($soldfromdate) ? $soldfromdate : '0000-00-00';
			$soldtodate = strlen($soldtodate) ? $soldtodate : '9999-12-31';
			$filter .= " and exists (select null from auction au 
		join orders o1 on o1.auction_number=au.auction_number and o1.txnid=au.txnid
		left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
			and total_log.tableid=o1.id and total_log.New_value=1
		where total_log.updated between '$soldfromdate' and '$soldtodate' and o1.article_id=a.article_id) ";
			$addfield = ", (select sum(o1.quantity) from auction au 
		join orders o1 on o1.auction_number=au.auction_number and o1.txnid=au.txnid
		left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
			and total_log.tableid=o1.id and total_log.New_value=1
		where total_log.updated between '$soldfromdate' and '$soldtodate' and o1.article_id=a.article_id) soldperiod ";
		} 	
		$corrfromdate = trim(mysql_real_escape_string($params['corrfromdate']));
		$corrtodate = trim(mysql_real_escape_string($params['corrtodate']));
		if (strlen($corrfromdate) || strlen($corrtodate)) {
			$corrfromdate = strlen($corrfromdate) ? $corrfromdate : '0000-00-00';
			$corrtodate = strlen($corrtodate) ? $corrtodate : '9999-12-31';
			$filter .= " and exists (select null from article_history ah 
		where ah.article_id=a.article_id and ah.date between '$corrfromdate' and '$corrtodate') ";
		} 	
		$createfromdate = trim(mysql_real_escape_string($params['createfromdate']));
		$createtodate = trim(mysql_real_escape_string($params['createtodate']));
		if (strlen($createfromdate) || strlen($createtodate)) {
			$createfromdate = strlen($createfromdate) ? $createfromdate : '0000-00-00';
			$createtodate = strlen($createtodate) ? $createtodate : '9999-12-31';
			$filter .= " and exists (select null from total_log 
				where table_name='article' and field_name='article_id' and tableid=a.iid 
				and updated between '$createfromdate' and '$createtodate') ";
		} 	
		if ($params['nosa']) {
			$sa_articles = $dbr->getOne("select group_concat(distinct CONCAT(\"'\", al.article_id, \"'\"))
				from article_list al
				join offer_group og on og.offer_group_id=al.group_id
				join offer o on o.offer_id=og.offer_id
				join saved_params sp on sp.par_key='offer_id' and sp.par_value*1=og.offer_id
				join saved_auctions sa on sa.id=sp.saved_id
				where sa.inactive=0 and sa.old=0 and al.inactive=0 and og.deleted=0 and o.hidden=0 and o.old=0");
			$filter .= " and a.article_id not in ($sa_articles) ";
		} 	
		if($params['basegroups']){
            $basegroups = $dbr->getOne("select group_concat(distinct CONCAT(\"'\", al.article_id, \"'\"))
				from article_list al
				join offer_group og on og.offer_group_id=al.group_id
				WHERE al.group_id IS NOT NULL AND og.deleted = 0 AND og.offer_id = 0");
            $filter .= " and a.article_id in ($basegroups) ";
        }
		if (isset($params['cons'])) {
//			$article_ids = ' in (select aa.article_id from article aa where aa.cons_id=a.article_id)';
			$article_ids = ' and aa.cons_id=a.article_id ';
			if (count($params['waresStock'])==1 && $waresStock0=(int)$params['waresStock'][0]) {
				$ware2ship_where = "join (select distinct article_id from orders where send_warehouse_id=$waresStock0
					union
					select distinct article_id from orders where new_article_warehouse_id=$waresStock0
					union
					select distinct article_id from op_article where warehouse_id=$waresStock0
					union
					select distinct article_id from rma_spec where returned_warehouse_id=$waresStock0 
						or warehouse_id=$waresStock0 or $waresStock0=(select warehouse_id from warehouse where `default`)
					union
					select distinct article_id from wwo_article wwa
					join ww_order wwo on wwo.id=wwa.wwo_id
					LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
					left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
					where wwa.from_warehouse=$waresStock0 or wwa.to_warehouse=$waresStock0 or w_driver.warehouse_id=$waresStock0
					union
					select distinct article_id from ats where warehouse_id=$waresStock0
					union
					select distinct ats_item.article_id from ats JOIN ats_item ON ats.id=ats_item.ats_id where warehouse_id=$waresStock0
					union
					select distinct article_id from article_history where warehouse_id=$waresStock0
					) a2 on a1.article_id=a2.article_id and a1.admin_id=0
				";
			} else {
#				print_r($params); die();
			}
			$source = "(SELECT 
				0 as take_real
				, ac.ordering as ordering
				, 0 as admin_id
				, 0 as deleted
				, ac.name
				, NULL as supplier_article_id
				, NULL as category_id
				, ac.company_id
				, ac.article_id
				, 0 as volume
				, 0 as volume_per_single_unit
				, ac.desired_daily
				, 0 as warehouse_id
				, 0 as purchase_price
				, 0 as total_item_cost
				, 0 as iid
				, 0 as eolif0ava
				, 0 as eolif0
                , a1.cons_id as cons_id
				from article_cons ac join article a1 on ac.article_id=a1.cons_id
				$ware2ship_where
				group by ac.article_id)";
			$key = 'cons_id';	
			$force_key = ' force key (cons_id) ';
		} else {
			$source = 'article';
//			$article_ids = ' = a.article_id'; 
			$article_ids = ' and aa.article_id=a.article_id ';
			$key = 'article_id';	
			$force_key = '';
		} 
		if ($params['dead'])
			$filter .= ' and deleted';
		else
			$filter .= ' and not deleted';
			
		if (trim(mysql_real_escape_string($params['article_id']))) {
			if (strpos($params['article_id'], '%')) $filter .= " and a.article_id like '".mysql_real_escape_string($params['article_id'])."' ";
			else $filter .= " and a.article_id = '".mysql_real_escape_string($params['article_id'])."' ";
		}
		if (trim(mysql_real_escape_string($params['supplier_article_id'])))
			$filter .= " and a.supplier_article_id like '%".mysql_real_escape_string($params['supplier_article_id'])."%' ";
		if (trim(mysql_real_escape_string($params['name']))) {
			if (isset($params['cons'])) {
				$filter .= " and UPPER(a.name) like UPPER('%".mysql_real_escape_string($params['name'])."%')
					";
			} else {
				$filter .= " and exists(select NULL from translation 
					where table_name='article'
					and field_name='name'
					and id=a.article_id
					and UPPER(value) like UPPER('%".mysql_real_escape_string($params['name'])."%')
					) ";
			}
		}
		if ($params['mode']=='active') 
			$filter .= ' and oc.active ';
		elseif ($params['mode']=='passive') 
			$filter .= ' and not oc.active ';
		if ($params['warehouse_id']>0) {
			$filter_top = ' and (available_'.$params['warehouse_id'].'<>0 or pieces_'.$params['warehouse_id'].'<>0) ';
		} else {
			$filter_top = '';
		}
		$filter_top_not = '';
		$filter_pieces = array();
		$warehouses = Warehouse::listArray($db, $dbr);
		if (count($params['waresStock'])) {
			$warehouses = array();
			foreach($params['waresStock'] as $key=>$value)
				$warehouses[$value] = 1;
		}
		if (!count($params['waresStock'])) {
			$warehouses = array();
		}
		if ((int)$params['no_pic'] || (int)$params['no_name'] || (int)$params['no_desc']) {
			$filterOR = " and (0 ";
			if ((int)$params['no_pic'])
				$filterOR .= " OR IFNULL(TRIM(a.picture_URL),'')='' ";
			if ((int)$params['no_name'])
				foreach($params['langs'] as $lang_id)
					$filterOR .= " OR not exists (select null from translation where table_name='article' and field_name='name' 
						and id=a.article_id and language = '$lang_id' and `value`<>'') ";
			if ((int)$params['no_desc'])
				foreach($params['langs'] as $lang_id)
					$filterOR .= " OR not exists (select null from translation where table_name='article' and field_name='description' 
						and id=a.article_id and language = '$lang_id' and `value`<>'') ";
			$filterOR .= " ) ";
			$filter .= $filterOR;
		}
		$str1 = ''; $str2 = ''; $str3 = '';
		if (strlen($params['stock_date'])) {
			$stock_date_or = " and i.invoice_date<='".$params['stock_date']."'";
			$stock_date_ah = " and date(ah.date)<='".$params['stock_date']."'";
			$stock_date_opa = " and date(opa.add_to_warehouse_date)<='".$params['stock_date']."'";
			$stock_date_r1 = " and 
				(select date(max(tl.updated)) from total_log tl where tl.Table_name='rma_spec' and tl.Field_name='problem_id'
					and tl.New_value in ('4','11') and tl.tableid=rs.rma_spec_id)<='".$params['stock_date']."'";
			$stock_date_r2 = " and IFNULL(rs.return_date, '0000-00-00')<='".$params['stock_date']."'";
			$stock_date_r3 = " and IFNULL((select date(max(tl.updated)) from total_log tl where tl.Table_name='rma_spec' and tl.Field_name='add_to_stock'
					and tl.New_value in ('1') and tl.tableid=rs.rma_spec_id), rs.return_date)<='".$params['stock_date']."'";
			$stock_date_os = " and o.sent_datetime<='".$params['stock_date']."'";
#				(select date(max(tl.updated)) from total_log tl where tl.table_name='orders' and tl.field_name='sent' 
#					and tl.tableid=o.id and tl.new_value=1)<='".$params['stock_date']."'";
			$stock_date_wt = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='wwo_article' and tl.field_name='taken' 
					and tl.tableid=wwo_article.id and tl.new_value=1)<='".$params['stock_date']."'";
			$stock_date_wd = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='wwo_article' and tl.field_name='delivered' 
					and tl.tableid=wwo_article.id and tl.new_value=1)<='".$params['stock_date']."'";
			$stock_date_nar = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='orders' and tl.field_name='new_article' 
					and tl.tableid=o.id and tl.new_value=1)<='".$params['stock_date']."'";
			$stock_date_war = " and 
				(select date(min(tl.updated)) from total_log tl where tl.table_name='wwo_article' and tl.field_name='article_id' 
					and tl.tableid=wwa.id and tl.new_value=1)<='".$params['stock_date']."'";
			$stock_date_ats = " and date(ats.order_date)<='".$params['stock_date']."'";
		}
		foreach ($warehouses as $id=>$warehouse) {
			if ($id<0) continue;
			$str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN article aa $force_key ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number and o.txnid=au.txnid
					AND o.txnid = au.txnid
					LEFT JOIN invoice i ON au.invoice_number = i.invoice_number
					WHERE 1 $stock_date_or
					$article_ids 
					and o.manual=0
					and o.sent=0
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
						+ (select IFNULL((select SUM(1/*o.new_article_qnt*/)
					FROM (select * from orders where new_article_id is not null) o
					JOIN article aa $force_key ON o.new_article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_nar
					$article_ids 
					AND o.new_article AND not o.lost_new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
						+ (select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					JOIN article aa $force_key ON wwa.article_id=aa.article_id and aa.admin_id=0
					WHERE 1 $stock_date_war
					$article_ids 
					and not wwa.taken
					and (wwa.reserved_warehouse=$id or $id=0)),0))
							as reserved_$id
			, (select IFNULL((select SUM(o.new_article_qnt)
					FROM orders o
					JOIN article aa $force_key ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					$article_ids 
		#			AND not o.sent
					and o.new_article and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
					- (select IFNULL((select SUM(1)
					FROM (select * from orders where lost_new_article=1) o
					JOIN article aa $force_key ON o.new_article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					$article_ids 
					and o.new_article=1 and o.lost_new_article=1 and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
							as newarticle_$id
			,(select IFNULL((select sum(ah.quantity) from article_history ah
					JOIN article aa $force_key ON ah.article_id=aa.article_id and aa.admin_id=0
					WHERE 1 $stock_date_ah
					$article_ids 
					and (ah.warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(opa.qnt_delivered) from op_article opa
					JOIN article aa $force_key ON opa.article_id=aa.article_id and aa.admin_id=0
					where 1 
					$stock_date_opa
					$article_ids 
					and opa.add_to_warehouse
					and (opa.warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa $force_key ON rs.article_id=aa.article_id and aa.admin_id=0
					left JOIN warehouse w ON `default`
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					WHERE rs.problem_id in (4,11) $stock_date_r1
					$article_ids 
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id  or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa $force_key ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					$article_ids  $stock_date_r2
					and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa $force_key ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					$article_ids  $stock_date_r2
					and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa $force_key ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					$article_ids  $stock_date_r2
					and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					JOIN article aa $force_key ON rs.article_id=aa.article_id and aa.admin_id=0
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					$article_ids  $stock_date_r3
					and (rs.warehouse_id=$id or $id=0)),0))
			) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN article aa $force_key ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.manual=0
					$article_ids $stock_date_os
					AND o.sent
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa $force_key ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					$article_ids $stock_date_wt
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_taken_in_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa $force_key ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					$article_ids $stock_date_wt
				and (wwo_article.from_warehouse=$id or $id=0)),0)) as driver_taken_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa $force_key ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					$article_ids $stock_date_wd
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_delivered_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa $force_key ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					$article_ids $stock_date_wd
				and (wwo_article.to_warehouse=$id or $id=0)),0)) as driver_delivered_in_$id
			,(select IFNULL((select sum(ats.quantity)
				from ats
					JOIN article aa $force_key ON ats.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					$article_ids $stock_date_ats
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_out_$id
			,(select IFNULL((select sum(ats_item.quantity)
				from ats
					JOIN ats_item ON ats.id=ats_item.ats_id
					JOIN article aa $force_key ON ats_item.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					$article_ids $stock_date_ats
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_in_$id
			,IFNULL((select purchase_price
					FROM article_import
					join warehouse on article_import.country_code = warehouse.country_code
					WHERE article_import.article_id = a.article_id 
					and (warehouse.warehouse_id=$id or $id=0)
					order by import_date desc limit 1) ,
						(select purchase_price
					FROM article_import
					WHERE article_import.article_id = a.article_id 
					order by import_date desc limit 1)
					) as purchase_price_$id
			,IFNULL((select total_item_cost
					FROM article_import
					join warehouse on article_import.country_code = warehouse.country_code
					WHERE article_import.article_id = a.article_id 
					and (warehouse.warehouse_id=$id or $id=0)
					order by import_date desc limit 1), 
						(select total_item_cost
					FROM article_import
					WHERE article_import.article_id = a.article_id 
					order by import_date desc limit 1)
					) as total_item_cost_$id
			,IFNULL((select warehouse_place
					FROM article_warehouse_place
					WHERE article_id = a.article_id 
					and warehouse_id=$id
					),'') as warehouse_place_$id
			";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id
				+ ats_in_$id - ats_out_$id + newarticle_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id-reserved_$id 
				+ ats_in_$id - ats_out_$id + newarticle_$id as available_$id";
			$str3 .= ", pieces_$id*purchase_price_$id as total_$id
			, pieces_$id*total_item_cost_$id as totaltotal_item_cost_$id
			, available_$id*total_item_cost_$id as totaltotal_available_item_cost_$id";
			if (in_array($id,$params['waresStock'])) {
				$filter_pieces[] = '(available_'.$id.'<>0 or pieces_'.$id.'<>0)';
			}
//			$filter_top_not .= ' and pieces_'.$id.'=0';
		}
		if (in_array(0,$params['waresStock'])) {
			$filter_pieces = array();
		}
/*		if ($params['nowares']) {
			$filter_top .= $filter_top_not;
		} else {
			$filter_top = " and (0 $filter_top) ";
		}	*/
		if ($params['nowares']) {
			$params['wares'][] = 0;
		}	
		if (is_array($params['wares']) && count($params['wares'])) {
//			$filter .= ' and IFNULL(w.warehouse_id,0) in ('.implode(',',$params['wares']).')';
			$filter_ware_left =  ' (IFNULL(warehouse_id,0) in ('.implode(',',array_merge(array(-1),$params['wares'])).')) ';
		}
		if (is_array($filter_pieces) && count($filter_pieces)) {
			$filter_top .= "and (".implode(' or ',$filter_pieces).")";
			$filter_ware_right =  ' ('.implode(' or ',$filter_pieces).') ';
		}
		if (!strlen($filter_ware_left)) {
			if ($andor=='AND') $filter_ware_left='1'; else $filter_ware_left='0';
		}
		if (!strlen($filter_ware_right)) {
			if ($andor=='AND') $filter_ware_right='1'; else $filter_ware_right='0';
		}
        
        $script = basename($_SERVER['SCRIPT_FILENAME']);
        
		if ($andor!='ALL') $andor_where = " and ($filter_ware_left $andor $filter_ware_right) ";
			$q = "select /* $script -> Article::listAllParams */ tt.*
				$str3
				from (
			select t.*
				$str2
				from (
			SELECT distinct a.* 
				$str1
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa 
					JOIN article aa $force_key ON opa.article_id=aa.article_id and aa.admin_id=0
				where 1
					$article_ids 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa 
					JOIN article aa $force_key ON opa.article_id=aa.article_id and aa.admin_id=0
				where 1
					$article_ids 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2
				), 0) as order_on_way_qnt
			, oc.name as company_name
			, concat(ifnull(oc.name,''),a.ordering) as company_name_ordering
			, oc.period
			, oc.active as company_active
			,(select count(*) from article_rep where rep_id=a.article_id) as is_rp	
			, t_article_name.value as article_short_name
			, (select sum(o.quantity) from orders o
				JOIN article aa $force_key ON o.article_id=aa.article_id and aa.admin_id=0
				join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
				where 1 and o.manual=0
				$article_ids
				AND o.sent=0
				AND au.deleted = 0
				and IFNULL(o.reserve_warehouse_id,0)=0) reserved_null
			$addfield
			, mi.merchant_article_id
			FROM $source a
			LEFT JOIN merchant_item mi on mi.article_id=a.article_id
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
			LEFT JOIN op_company oc on a.company_id=oc.id
			left join warehouse w on w.warehouse_id=a.warehouse_id and w.inactive=0
			WHERE a.admin_id=0 
            {$attr} {$filter} {$where_rep} and a.article_id is not null and a.article_id != ''
            {$supplier_filter_str1}
			) t ) tt
			where 1 
            {$andor_where}
			";
            
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        
		foreach ($r as $i=>$article) {
			$r[$i]->available_0 = 0;
			$r[$i]->inventar_0 = 0;
			$r[$i]->order_0 = 0;
			$r[$i]->rma_0 = 0;
			$r[$i]->sold_0 = 0;
			$r[$i]->pieces_0 = 0;
			$r[$i]->total_0 = 0;
			$r[$i]->totaltotal_item_cost_0 = 0;
			$r[$i]->totaltotal_available_item_cost_0 = 0;
			$r[$i]->reserved_0 = 0;
			$r[$i]->warehouse_place = array();
			foreach ($warehouses as $id=>$warehouse) {
				if (!$id) continue;
				$available = 'available_'.$id;
				$inventar = 'inventar_'.$id;
				$order = 'order_'.$id;
				$rma = 'rma_'.$id;
				$sold = 'sold_'.$id;
				$reserved = 'reserved_'.$id;
				$pieces = 'pieces_'.$id;
				$total = 'total_'.$id;
				$totaltotal_item_cost = 'totaltotal_item_cost_'.$id;
				$totaltotal_available_item_cost = 'totaltotal_available_item_cost_'.$id;
				$warehouse_place = 'warehouse_place_'.$id;
				$r[$i]->$available = (int)$r[$i]->$available;
				$r[$i]->$inventar = (int)$r[$i]->$inventar;
				$r[$i]->$order = (int)$r[$i]->$order;
				$r[$i]->$rma = (int)$r[$i]->$rma;
				$r[$i]->$sold = (int)$r[$i]->$sold;
				$r[$i]->$reserved = (int)$r[$i]->$reserved;
				$r[$i]->$pieces = (int)$r[$i]->$pieces;
				$r[$i]->$total = (int)$r[$i]->$total;
				$r[$i]->$totaltotal_item_cost = (int)$r[$i]->$totaltotal_item_cost;
				$r[$i]->$totaltotal_available_item_cost = (int)$r[$i]->$totaltotal_available_item_cost;
				$r[$i]->warehouse_place[$id] = $r[$i]->$warehouse_place;
				$r[$i]->available_0 += $r[$i]->$available;
				$r[$i]->inventar_0 += $r[$i]->$inventar;
				$r[$i]->order_0 += $r[$i]->$order;
				$r[$i]->rma_0 += $r[$i]->$rma;
				$r[$i]->sold_0 += $r[$i]->$sold;
				$r[$i]->reserved_0 += $r[$i]->$reserved;
				$r[$i]->pieces_0 += $r[$i]->$pieces;
				$r[$i]->total_0 += $r[$i]->$total;
				$r[$i]->totaltotal_item_cost_0 += $r[$i]->$totaltotal_item_cost;
				$r[$i]->totaltotal_available_item_cost_0 += $r[$i]->$totaltotal_available_item_cost;
			}	
#			$r[$i]->volume_available_0 = number_format($r[$i]->available_0 * $r[$i]->volume,6);
			$r[$i]->volume_available_0 = number_format($r[$i]->pieces_0 * $r[$i]->volume_per_single_unit,6);
			$r[$i]->weight_available_0 = number_format($r[$i]->pieces_0 * $r[$i]->weight_per_single_unit,2);
			$r[$i]->purchase_price1_hist = $dbr->getOne("select CONCAT('<table border=\"1\">'
					,group_concat( tr separator '')
					,'</table>') from
				(select CONCAT('<tr><td>',date(import_date),'</td><td align=\"right\">',purchase_price,'</td></tr>') tr
						from article_import
						where article_id='".$r[$i]->article_id."'
						order by import_date desc limit 5
				) t");
		} 
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
//		print_r($r); die();
        return $r;
    }
    static function listByShippingPlan($db, $dbr, $shipping_plan_id='', $rep='')
    {
		global $supplier_filter;
		global $supplier_filter_str;
		if (strlen($supplier_filter))
			$supplier_filter_str1 = " and oc.id in ($supplier_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Article::list expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
	$where_rep='';
	if ($rep=='non-rep') {
		$where_rep=' and not exists (select null from article_rep where rep_id=a.article_id) ';
	} elseif ($rep=='rep') {
		$where_rep=' and exists (select null from article_rep where rep_id=a.article_id) ';
	}
		$warehouses = Warehouse::listArray($db, $dbr);
		$warehouses[0] = 'Total';
		$str1 = ''; $str2 = ''; $str3 = '';
		foreach ($warehouses as $id=>$warehouse) {
			$str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					and o.sent=0
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
						+ (select IFNULL((select SUM(1/*o.new_article_qnt*/)
					FROM (select * from orders where new_article_id is not null) o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.new_article_id = a.article_id
					AND o.new_article AND not o.lost_new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
						+ (select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					WHERE wwa.article_id=a.article_id
					and not wwa.taken
					and (wwa.reserved_warehouse=$id or $id=0)),0))
						as reserved_$id
			,(select IFNULL((select sum(quantity) from article_history 
					WHERE article_id = a.article_id 
					and (warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(qnt_delivered) from op_article 
					where article_id=a.article_id and add_to_warehouse
					and (warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					left JOIN warehouse w ON `default`
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					WHERE rs.problem_id in (4,11) and rs.article_id = a.article_id
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id  or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					AND o.sent
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id-reserved_$id as available_$id";
			$str3 .= ", pieces_$id*purchase_price as total_$id
			, pieces_$id*total_item_cost as totaltotal_item_cost_$id
			, available_$id*total_item_cost as totaltotal_available_item_cost_$id";
		}
        $script = basename($_SERVER['SCRIPT_FILENAME']);
        
			$q = "select /* $script -> Article::listByShippingPlan */ tt.*
				$str3
				from (
			select t.*
				$str2
				from (
			SELECT distinct a.* 
				$str1
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2
				), 0) as order_on_way_qnt
			, oc.name as company_name
			, oc.period
			, oc.active as company_active
			,(select count(*) from article_rep where rep_id=a.article_id) as is_rp	
			, t_article_name.value as article_short_name
			FROM article a
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
				LEFT JOIN op_company oc on a.company_id=oc.id
			WHERE a.admin_id=0 ".$attr.$filter.$where_rep." and a.article_id is not null
			AND (exists (select 1 from translation where
			  table_name='article' and field_name='shipping_plan_id' 
			  and id = a.article_id and value='$shipping_plan_id')  or $shipping_plan_id='')
			AND NOT a.deleted
			$supplier_filter_str1
			)t )tt
			ORDER BY article_id";
//			die($q);
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
        return $r;
    }
    static function listBySuppllier($db, $dbr, $supplier_id)
    {
        $r = $db->query("SELECT a.* 
			, t_article_name.value as article_short_name
		FROM article a 
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
		WHERE a.company_id=".$supplier_id);
        if (PEAR::isError($r)) {
//            $this->_error = $r;
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }
    static function listArrayBySuppllier($db, $dbr, $supplier_id)
    {
        $ret = array();
        $list = Article::listBySuppllier($db, $dbr, $supplier_id);
        foreach ((array)$list as $article) {
            $ret[$article->article_id] = $article->article_id . ' : ' .$article->article_short_name;
        }
        return $ret;
    }
    /**
    * @return unknown
    * @param unknown $db
    * @desc Return array of aricles suitable for
    * use with Smarty's {html_options}
    */
    static function listArray($db, $dbr, $attr='', $rep='', $eol='')
    {
		global $supplier_filter;
		global $supplier_filter_str;
#		global $debug;
#		global $time;
		if (strlen($supplier_filter))
			$supplier_filter_str1 = " and (oc.id in (0, $supplier_filter) OR oc.id is null) ";
		$where_rep='';
		if (strlen($eol)) {
			$where_rep=" and deleted=$eol ";
		}
		if ($rep=='non-rep') {
			$where_rep=' and not exists (select null from article_rep where rep_id=a.article_id) ';
		} elseif ($rep=='rep') {
			$where_rep=' and exists (select null from article_rep where rep_id=a.article_id) ';
		}
		if ($attr=='uncategoried') {
			$attr = ' and (category_id is NULL or category_id = 0) ';
		} elseif ($attr=='unsupplied') {
			$attr = " and (a.company_id IS NULL or a.company_id='' or a.company_id = 0)";
		} elseif ($attr=='uncons') {
			$attr = " and IFNULL(a.cons_id,0)=0 ";
		}
			
		$filter .= ' and not deleted';
			
			$q = "
			SELECT distinct a.article_id, CONCAT(a.article_id, ' : ', t_article_name.value) as article_short_name
			FROM article a
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
				LEFT JOIN op_company oc on a.company_id=oc.id
			WHERE a.admin_id=0 ".$attr.$filter.$where_rep." and a.article_id is not null and a.article_id<>''
			$supplier_filter_str1
			ORDER BY article_id";
if ($debug) {echo '6-5-0: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
		if ($debug) echo $q.'<br>';
        $r = $dbr->getAssoc($q); 
if ($debug) {echo '6-5-1: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
        if (PEAR::isError($r)) {
			print_r($r);
        }
        return $r;
    }
/*    function listArray($db, $dbr, $attr='', $rep='')
    {
        $ret = array();
        $list = Article::listAll($db, $dbr, $attr, 0, 0, 0, 'all', '', '', $rep);
        foreach ((array)$list as $article) {
            $ret[$article->article_id] = $article->article_id . ' : ' .$article->name;
        }
        return $ret;
    }*/
    /**
    * @return boolean
    * @param array $errors
    * @desc validates fields
    */
    function validate(&$errors)
    {
        $errors = array();
        if (!strlen($this->data->article_id)) {
            $errors[] = 'Article number is required';
        } elseif ($this->_isNew) {
            $id = mysql_real_escape_string($this->data->article_id);
            $r = $this->_db->getOne("SELECT COUNT(*) AS n FROM article WHERE admin_id=0 and article_id='$id'");
            if ($r) {
                $errors[] = 'Duplicate article number';
            }
            $r = $this->_db->getOne("SELECT COUNT(*) AS n FROM article_cons WHERE article_id='$id'");
            if ($r) {
                $errors[] = 'We already have a cons article with this ID';
            }
        }
        if (!is_numeric($this->data->items_per_shipping_unit) || $this->items_per_shipping_unit < 0) {
            $errors[] = 'Items per shipping unit must be a non-negative number';
        }
        if (!is_numeric($this->data->purchase_price) || $this->purchase_price < 0) {
            $errors[] = 'Purchase price must be a non-negative number';
        }
        if (!is_numeric($this->data->weight) || $this->weight < 0) {
            $errors[] = 'Weight must be a non-negative number';
        }
        
        if (!is_numeric($this->data->volume) || $this->volume < 0) {
            $errors[] = 'Volume must be a non-negative number';
        }
        return !count($errors);
    }
    static function getDocs($db, $dbr, $article_id, $send_it=0, $type='doc', $get_all = true)
    {
//        $r = $db->query("SELECT * from article_doc where article_id='$article_id' and send_it=$send_it and type='$type' ORDER BY doc_id");
        
        $select = $get_all ? '*' : ' doc_id, article_id, name, description, send_it, type, shop_it, auto_description, white_bg ';
        
        $r = $db->query("SELECT $select 
                from article_doc where article_id='$article_id' and send_it=$send_it and type='$type' ORDER BY doc_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
			$article->url = "/images/cache/undef_src_article_picid_{$article->doc_id}_image.jpg";
            $list[] = $article;
        }
        return $list;
    }
    static function addDoc($db, $dbr, $article_id,
		$name,
		$description,
		$data,
		$send_it,
		$type,
        $white_bg = 0
		)
    {   
        
        /**
         * @description save pdf hash
         * @var $md5_pdf
         */
        $md5_pdf = convertAndsavePDF(null, $data);
        
        $md5 = md5($data);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }
        
		$name = mysql_real_escape_string($name);
		$description = mysql_real_escape_string($description);
		$data = mysql_real_escape_string(base64_encode($data));
		$send_it = (int)$send_it;
        
        $db->query("insert into article_doc set 
            article_id='$article_id', 
            name='$name',
            description='$description',
            type='$type',
            data='$md5',
            send_it=$send_it,
            white_bg=$white_bg,
            data_pdf='$md5_pdf'");
        $id = $dbr->getOne("select max(doc_id) from article_doc 
            where article_id='$article_id' and
            name='$name' and
            description='$description'");	
		if ($type=='pic') {
            $field = $white_bg == 0 ? 'picture_URL' : 'wpicture_URL';
		    $r = $db->query("update article set {$field}='/images/cache/undef_src_article_picid_{$id}_image.jpg' where article_id='$article_id'");
		}
        
		if (PEAR::isError($r)) aprint_r($r);
		return $r;	
    }
    static function deleteDoc($db, $dbr, $doc_id, $lang='')
    {
        $doc_id = (int)$doc_id;
		$table_name = 'article_doc'; 
		$edit_id = $doc_id;
		if ($lang=='') {
			$langwhere = "";
		} else {
			$langwhere = " and language = '$lang'";
		}
		global $db_user,$db_pass,$db_host,$db_name;
		$db = dblogin($db_user,$db_pass,$db_host,$db_name);
	    $r = $db->query("delete from prologis_log.translation_files2 where id='$edit_id' 
			and table_name='$table_name' $langwhere");
		if (PEAR::isError($r)) { aprint_r($r); die();}
	    $r = $db->query("delete from translation where id=$edit_id 
			and table_name='$table_name' $langwhere");
		if (PEAR::isError($r)) { aprint_r($r); die();}
        if (!$dbr->getOne("select count(*) from translation where id=$edit_id and table_name='$table_name'")) {
			$r = $db->query("delete from article_doc where doc_id=$doc_id");
			if (PEAR::isError($r)) { aprint_r($r); die();}
		}
    }
    static function findIds($db, $dbr, $ids, $sort='article_id', $direction=1, $stock_date='')
    {
		if (strlen($stock_date)) {
			$stock_date_or = " and i.invoice_date<='".$stock_date."'";
			$stock_date_ah = " and date(ah.date)<='".$stock_date."'";
			$stock_date_opa = " and date(opa.add_to_warehouse_date)<='".$stock_date."'";
			$stock_date_r1 = " and 
				(select date(max(tl.updated)) from total_log tl where tl.Table_name='rma_spec' and tl.Field_name='problem_id'
					and tl.New_value in ('4','11') and tl.tableid=rs.rma_spec_id)<='".$stock_date."'";
			$stock_date_r2 = " and IFNULL(rs.return_date, '0000-00-00')<='".$stock_date."'";
			$stock_date_r3 = " and IFNULL((select date(max(tl.updated)) from total_log tl where tl.Table_name='rma_spec' and tl.Field_name='add_to_stock'
					and tl.New_value in ('1') and tl.tableid=rs.rma_spec_id), rs.return_date)<='".$stock_date."'";
			$stock_date_os = " and o.sent_datetime<='".$stock_date."'";
/*			$stock_date_os = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='orders' and tl.field_name='sent' 
					and tl.tableid=o.id and tl.new_value=1)<='".$stock_date."'";*/ 
			$stock_date_wt = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='wwo_article' and tl.field_name='taken' 
					and tl.tableid=wwo_article.id and tl.new_value=1)<='".$stock_date."'";
			$stock_date_wd = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='wwo_article' and tl.field_name='delivered' 
					and tl.tableid=wwo_article.id and tl.new_value=1)<='".$stock_date."'";
			$stock_date_nar = " and 
				(select date(max(tl.updated)) from total_log tl where tl.table_name='orders' and tl.field_name='new_article' 
					and tl.tableid=o.id and tl.new_value=1)<='".$stock_date."'";
			$stock_date_war = " and 
				(select date(min(tl.updated)) from total_log tl where tl.table_name='wwo_article' and tl.field_name='article_id' 
					and tl.tableid=wwa.id and tl.new_value=1)<='".$stock_date."'";
			$stock_date_ats = " and date(ats.order_date)<='".$stock_date."'";
		}
		if (strlen($sort)) {
			$order = " order by IF((select count(*) from article_rep where rep_id=ttt.article_id)>0, 1, 0), $sort".($direction<0?' desc': '');
		}
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
//        $ids = mysql_real_escape_string($ids);
		$warehouses = Warehouse::listArray($db, $dbr);
		$warehouses[0] = 'Total';
		$str1 = ''; $str2 = ''; $str3 = '';
		foreach ($warehouses as $id=>$warehouse) {
			$str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					LEFT JOIN invoice i ON au.invoice_number = i.invoice_number
					WHERE o.article_id = a.article_id and o.manual=0 
						$stock_date_or
					and o.sent=0 
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
						+ (select IFNULL((select SUM(1/*o.new_article_qnt*/)
					FROM (select * from orders where new_article_id is not null) o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_nar
					and o.new_article_id=a.article_id
					AND o.new_article AND not o.lost_new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
						+ (select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					WHERE 1 $stock_date_war
					and wwa.article_id=a.article_id
					and not wwa.taken
					and (wwa.reserved_warehouse=$id or $id=0)),0))
						as reserved_$id
			,(select IFNULL((select sum(quantity) from article_history ah
					WHERE article_id = a.article_id 
						$stock_date_ah
					and (warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(qnt_delivered) from op_article opa
						where article_id=a.article_id and add_to_warehouse
							$stock_date_opa
						and (warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					left JOIN warehouse w ON `default`
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					WHERE rs.problem_id in (4,11) and rs.article_id = a.article_id
						$stock_date_r1
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
						$stock_date_r2
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
						$stock_date_r2
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
						$stock_date_r2
					and rs.article_id = a.article_id and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
						$stock_date_r3
					and rs.article_id = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			) as rma_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					$stock_date_wt
					and aa.article_id = a.article_id 
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_taken_in_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					$stock_date_wt
					and aa.article_id = a.article_id 
				and (wwo_article.from_warehouse=$id or $id=0)),0)) as driver_taken_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					and aa.article_id = a.article_id  
					$stock_date_wd
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_delivered_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					and aa.article_id = a.article_id  
					$stock_date_wd
				and (wwo_article.to_warehouse=$id or $id=0)),0)) as driver_delivered_in_$id
			,(select IFNULL((select sum(ats.quantity)
				from ats
					JOIN article aa ON ats.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					and aa.article_id=a.article_id 
					$stock_date_ats
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_out_$id
			,(select IFNULL((select sum(ats_item.quantity)
				from ats
					JOIN ats_item ON ats.id=ats_item.ats_id
					JOIN article aa ON ats_item.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					and aa.article_id=a.article_id 
					$stock_date_ats
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_in_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					AND o.sent
					$stock_date_os
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id 
				+ ats_in_$id - ats_out_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id-reserved_$id 
				+ ats_in_$id - ats_out_$id as available_$id";
			$str3 .= ", pieces_$id*purchase_price as total_$id
			, pieces_$id*total_item_cost as totaltotal_item_cost_$id
			, available_$id*total_item_cost as totaltotal_available_item_cost_$id";
		}
        
        $script = basename($_SERVER['SCRIPT_FILENAME']);
        
		$q = "select /* $script -> Article::findIds */ ttt.*
				$str3
				from (
			select tt.*
				$str2
				from (
			select t.* 
			, t.article_in_stock*t.purchase_price as total
			, t.article_in_stock*t.total_item_cost as totaltotal_item_cost
		from (
			SELECT a.* 
				$str1
			, -
			(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					AND o.sent=0
					AND au.deleted = 0),0))
			+
			(select IFNULL((select sum(quantity) from article_history 
					WHERE article_id = a.article_id),0))
			+
			(select IFNULL((select sum(qnt_delivered) from op_article 
					where article_id=a.article_id and add_to_warehouse),0))
			+
			(select IFNULL((SELECT count(*) as quantity
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.problem_id in (4,11) and rs.article_id = a.article_id),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and rs.article_id = a.article_id),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					and rs.article_id = a.article_id),0))
			-
			(select IFNULL((select SUM(o.quantity)
					FROM orders o
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.article_id = a.article_id and o.manual=0
					AND o.sent
					AND au.deleted = 0),0)) as article_in_stock
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2
				), 0) as order_on_way_qnt
			, oc.name as company_name
			, oc.period
			, oc.active as company_active
			,(select count(*) from article_rep where rep_id=a.article_id) as is_rp	
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		FROM article a
				LEFT JOIN op_company oc on a.company_id=oc.id
		WHERE a.admin_id=0 and a.article_id in ($ids) and a.article_id is not null 
		and a.article_id<>'' 
		) t )tt )ttt
		$order, article_id";
//		echo $q; die();
        $r = $dbr->getAll($q);
		foreach ($r as $i=>$article) {
			$r[$i]->available_0 = 0;
			$r[$i]->inventar_0 = 0;
			$r[$i]->order_0 = 0;
			$r[$i]->rma_0 = 0;
			$r[$i]->sold_0 = 0;
			$r[$i]->pieces_0 = 0;
			$r[$i]->total_0 = 0;
			$r[$i]->totaltotal_item_cost_0 = 0;
			$r[$i]->totaltotal_available_item_cost_0 = 0;
			$r[$i]->reserved_0 = 0;
			foreach ($warehouses as $id=>$warehouse) {
				if (!$id) continue;
				$available = 'available_'.$id;
				$inventar = 'inventar_'.$id;
				$order = 'order_'.$id;
				$rma = 'rma_'.$id;
				$sold = 'sold_'.$id;
				$reserved = 'reserved_'.$id;
				$pieces = 'pieces_'.$id;
				$total = 'total_'.$id;
				$totaltotal_item_cost = 'totaltotal_item_cost_'.$id;
				$totaltotal_available_item_cost = 'totaltotal_available_item_cost_'.$id;
				$r[$i]->$available = (int)$r[$i]->$available;
				$r[$i]->$inventar = (int)$r[$i]->$inventar;
				$r[$i]->$order = (int)$r[$i]->$order;
				$r[$i]->$rma = (int)$r[$i]->$rma;
				$r[$i]->$sold = (int)$r[$i]->$sold;
				$r[$i]->$reserved = (int)$r[$i]->$reserved;
				$r[$i]->$pieces = (int)$r[$i]->$pieces;
				$r[$i]->$total = (int)$r[$i]->$total;
				$r[$i]->$totaltotal_item_cost = (int)$r[$i]->$totaltotal_item_cost;
				$r[$i]->$totaltotal_available_item_cost = (int)$r[$i]->$totaltotal_available_item_cost;
				$r[$i]->available_0 += $r[$i]->$available;
				$r[$i]->inventar_0 += $r[$i]->$inventar;
				$r[$i]->order_0 += $r[$i]->$order;
				$r[$i]->rma_0 += $r[$i]->$rma;
				$r[$i]->sold_0 += $r[$i]->$sold;
				$r[$i]->reserved_0 += $r[$i]->$reserved;
				$r[$i]->pieces_0 += $r[$i]->$pieces;
				$r[$i]->total_0 += $r[$i]->$total;
				$r[$i]->totaltotal_item_cost_0 += $r[$i]->$totaltotal_item_cost;
				$r[$i]->totaltotal_available_item_cost_0 += $r[$i]->$totaltotal_available_item_cost;
			}	
		} 
					if (PEAR::isError($r)) aprint_r($r);
					return $r;
    }
    static function getParcels($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$res = $dbr->getAll("SELECT * FROM article_parcel WHERE article_id='".$article_id."'");
		foreach ($res as $key=>$parcel) {
			$res[$key]->bandmass = (max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
				+ 2*($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h 
				- max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))) / 100;
			$res[$key]->dimension = $parcel->dimension_l*$parcel->dimension_h*$parcel->dimension_w / 1000000;
		}	
//		print_r($res);	
        return $res;
    }
    static function addParcel($db, $dbr, $article_id, $l, $h, $w, $weight)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("insert into article_parcel (article_id, dimension_l, dimension_h, dimension_w, weight_parcel)
			values ('$article_id', $l, $h, $w, $weight)");
    }
    static function deleteParcel($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$article_id = $dbr->getOne("select article_id from article_parcel where id=$id");
        $db->query("delete from article_parcel where id=$id");
        $r = $db->query("update article set volume=(select sum(dimension_h*dimension_l*dimension_w/1000000) from article_parcel where article_id=article.article_id)
			where article_id='$article_id' and admin_id=0");
		if (PEAR::isError($r)) { print_r($r); die(); }
        $r = $db->query("update article set volume_per_single_unit=volume/items_per_shipping_unit
			where article_id='$article_id' and admin_id=0 and items_per_shipping_unit>0");
		if (PEAR::isError($r)) { print_r($r); die(); }
    }
    static function getRealParcels($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$res = $dbr->getAll("SELECT * FROM article_real_parcel WHERE article_id='".$article_id."'");
		foreach ($res as $key=>$parcel) {
			$res[$key]->bandmass = (max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
				+ 2*($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h 
				- max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))) / 100;
			$res[$key]->dimension = $parcel->dimension_l*$parcel->dimension_h*$parcel->dimension_w / 1000000;
		}	
//		print_r($res);	
        return $res;
    }
    static function addRealParcel($db, $dbr, $article_id, $l, $h, $w, $weight, $price)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("insert into article_real_parcel (article_id, dimension_l, dimension_h, dimension_w, weight_parcel, price)
			values ('$article_id', $l, $h, $w, $weight, $price)");
    }
    static function deleteRealParcel($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("delete from article_real_parcel where id=$id");
    }
    static function getMaterials($db, $dbr, $article_id, $lang='german')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        return $dbr->getAll("SELECT article_sh_material.quantity as shm_quantity, article_sh_material.id, article_sh_material.hidePacking, article.* 
		,(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = '$lang'
				AND id = article.article_id) translated_name
	       FROM article_sh_material 
		JOIN article ON article_sh_material.sh_material_id = article.article_id AND article.admin_id=0
		WHERE article_sh_material.article_id='".$article_id."'
		order by article_sh_material.ordering");
    }
    static function getMaterialsVV($db, $dbr, $article_id, $lang='german')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        return $dbr->getAll("SELECT article_sh_material.quantity as shm_quantity, article_sh_material.id, article_sh_material.hidePacking, article.* 
		,(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = '$lang'
				AND id = article.article_id) translated_name
	       FROM article_sh_material 
		JOIN article ON article_sh_material.article_id = article.article_id AND article.admin_id=0
		WHERE article_sh_material.sh_material_id='".$article_id."'
		order by article_sh_material.ordering");
    }
    static function addMaterial($db, $dbr, $article_id, $newmaterial_id, $quantity, $hidePacking)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("insert into article_sh_material (article_id, sh_material_id, quantity, hidePacking)
			values ('$article_id', '$newmaterial_id', '$quantity', '$hidePacking')");
    }
    static function deleteMaterial($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("delete from article_sh_material where id=$id");
//		echo "delete from article_sh_material where id=$id";
    }
    static function changeMaterial($db, $dbr, $id, $field, $value)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("update article_sh_material set $field = '$value' where id=$id");
    }
    static function getReps($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        return $dbr->getAll("SELECT article_rep.id, article.*, article_rep.spare_quantity
		,(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = article.article_id) translated_name
		FROM article_rep
		JOIN article ON article_rep.rep_id = article.article_id AND article.admin_id=0
		WHERE article_rep.article_id='".$article_id."'");
    }
    static function addRep($db, $dbr, $article_id, $newrep_id, $spare_quantity)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $spare_quantity = (int)$spare_quantity;
        $newrep_id = (int)$newrep_id;
        $article_id = (int)$article_id;
        $db->query("insert into article_rep (article_id, rep_id, spare_quantity)
			values ('$article_id', '$newrep_id', '$spare_quantity')");
    }
    static function deleteRep($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("delete from article_rep where id=$id");
    }
    static function getSuppliers($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        return $dbr->getAll("SELECT article_supplier.id, op_company.* FROM article_supplier
		JOIN op_company ON article_supplier.company_id = op_company.id
		WHERE article_supplier.article_id='".$article_id."'");
    }
    static function addSupplier($db, $dbr, $article_id, $newsupplier_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("insert into article_supplier (article_id, company_id)
			values ('$article_id', '$newsupplier_id')");
    }
    static function deleteSupplier($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("delete from article_supplier where id=$id");
    }
    static function getImport($db, $dbr, $article_id, $from=0, $to=9999999, $sort='import_date desc') {
		$article_id = mysql_real_escape_string($article_id);
		$countries = $dbr->getAll("select distinct article_import.country_code, country.name
			from article_import
			join country on country.code=article_import.country_code
			where article_id='$article_id'");
		foreach ($countries as $k=>$r) {
	        $countries[$k]->prices = $dbr->getAll("SELECT ai.* 
				, IF(opc.id is null, op_order_container.container_no, CONCAT('(',opc.container_no,')')) container_no
				, opa.container_id
				from article_import ai
				left join op_article opa on opa.id=ai.op_article_id
				left join op_order_container on op_order_container.id=opa.container_id
				left join op_order_container master on master.id=op_order_container.master_id
				left join op_order_container opc on opc.id = IFNULL(master.id, op_order_container.id)
				where ai.article_id='$article_id'
				and ai.country_code='{$r->country_code}'
				order by ai.$sort LIMIT $from, $to");
			if (PEAR::isError($r)) print_r($r);
		}
		return $countries;
	}
    static function getImportCount($db, $dbr, $article_id) {
		$article_id = mysql_real_escape_string($article_id);
        $r = $dbr->getOne("SELECT count(*) from article_import where article_id='$article_id'");
			if (PEAR::isError($r)) { print_r($r); return 0; }
			else return $r;
	}
    static function getComments($db, $dbr, $id)
    {
		$q = "SELECT '' as prefix
			, article_comment.id
			, article_comment.create_date
			, article_comment.username
			, article_comment.username cusername
			, IFNULL(users.name, article_comment.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, article_comment.comment
			 from article_comment 
			 LEFT JOIN users ON article_comment.username = users.username
			where article_id='$id'
		UNION ALL
		select CONCAT('Alarm (',alarms.status,'):') as prefix
			, NULL as id
			, (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
			, alarms.username
			, alarms.username cusername
			, IFNULL(users.name, alarms.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, alarms.comment 
			from article
			join alarms on alarms.type='article' and alarms.type_id=article.iid
			LEFT JOIN users ON alarms.username = users.username
			where article.article_id='$id'
		ORDER BY create_date";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
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
		$comment
		)
    {
        $id = mysql_real_escape_string($id);
		$username = mysql_real_escape_string($username);
		$create_date = mysql_real_escape_string($create_date);
//		$comment = mysql_real_escape_string($comment); // we escape it in js_backend
        $r = $db->query("insert into article_comment set 
			article_id='$id', 
			username='$username',
			create_date='$create_date',
			comment='$comment'");
    }
    static function delComment($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("delete from article_comment where id=$id");
    }
	
    static function getAddresses($db, $dbr, $id)
    {
		$q = "SELECT article_address.*, country.name country
			 from article_address 
			 join country on country.code=article_address.country_code
			where article_id='$id'";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }
    static function addAddress($db, $dbr, $id
		, $company, $first_name, $last_name, $street, $zip, $country_code
		)
    {
        $id = mysql_real_escape_string($id);
        $r = $db->query("insert into article_address set 
			article_id='$id', 
			company='$company',
			first_name='$first_name',
			last_name='$last_name',
			street='$street',
			zip='$zip',
			country_code='$country_code'");
    }
    static function delAddress($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("delete from article_address where id=$id");
    }
	
	function getSold($warehouse_id=0)
	{
		$q = "SELECT fget_Article_sold({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getFromRamp($warehouse_id=0)
	{
        if ($this->data->barcode_type == 'A') {
            return 0;
        }
        
        $query[] = "SELECT -SUM(`pbad`.`quantity`) AS `quantity`
                FROM `vparcel_barcode` AS `pb`
                JOIN `parcel_barcode_article_deduct` AS `pbad` ON `pb`.`id` = `pbad`.`parcel_barcode_id` 
                    AND `pbad`.`picking_order_id`
                LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `pbad`.`picking_order_id`
                JOIN `orders` AS `o` ON `po`.`id` = `o`.`picking_order_id`
                WHERE `pb`.`warehouse_id` = $warehouse_id AND `pbad`.`article_id` = '{$this->data->article_id}' 
                GROUP BY `pb`.`parcel`
                HAVING `quantity`";
        $query[] = "SELECT -SUM(`pbad`.`quantity`) AS `quantity`
                FROM `parcel_barcode_article_deduct` AS `pbad` 
                LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `pbad`.`picking_order_id`
                JOIN `orders` AS `o` ON `po`.`id` = `o`.`picking_order_id`
                LEFT JOIN `ware_la` ON `ware_la`.`id` = `po`.`ware_la_id`
                WHERE `ware_la`.`warehouse_id` = $warehouse_id AND `pbad`.`article_id` = '{$this->data->article_id}' 
                    AND `pbad`.`parcel_barcode_id` = 0
                    AND `pbad`.`picking_order_id`
                GROUP BY `ware_la`.`id`
                HAVING `quantity`";
        $query = "SELECT SUM(quantity) FROM ( " . implode("\n UNION ALL \n", $query) . " ) t";
        $r = $this->_dbr->getOne($query);
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getNewArticle($warehouse_id=0)
	{
		$q = "SELECT fget_Article_new_article({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getMultiNewArticle($warehouse_ids)
	{
        $query1 = "select new_article_warehouse_id, SUM(o.new_article_qnt)
            FROM orders o
            JOIN auction au ON o.auction_number = au.auction_number
            AND o.txnid = au.txnid
            WHERE /*o.article_id='{$this->data->article_id}' AND*/ o.manual=0
            and o.new_article and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
            AND au.deleted = 0 and o.new_article_warehouse_id IN (" . implode(', ', $warehouse_ids) . ")
            GROUP BY new_article_warehouse_id";
        $ware_1 = $this->_dbr->getAssoc($query1);
        
        $query2 = "select new_article_warehouse_id, count(*)
            FROM orders o
            JOIN auction au ON o.auction_number = au.auction_number
            AND o.txnid = au.txnid
            WHERE /*o.new_article_id='{$this->data->article_id}' AND*/ lost_new_article=1 AND o.manual=0
            and o.new_article=1 and o.lost_new_article=1 and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
            AND au.deleted = 0 and o.new_article_warehouse_id IN (" . implode(', ', $warehouse_ids) . ")
            group by new_article_warehouse_id";
        $ware_2 = $this->_dbr->getAssoc($query2);
        
        $ware = array();
        foreach ($ware_1 as $id => $value) {
            $ware[$id] = (int)$value;
            if (isset($ware_2[$id])) {
                $ware[$id] -= (int)$ware_2[$id];
            }
        }
        
		return $ware;
	}
	
	function getReady2Ship($warehouse_id=0)
	{
	  global $seller_filter_str;
		$q = "select IFNULL(sum(o.quantity),0)
			from orders o
			join auction au on au.auction_number=o.auction_number and au.txnid=o.txnid
			left join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
			join invoice i on au.invoice_number=i.invoice_number
			where o.sent=0 and au.deleted=0
			$seller_filter_str
			and (
				au.paid=1 or au.payment_method in ('2','3','4')
				or
				mau.paid=1 or mau.payment_method in ('2','3','4')
			)
			and o.article_id='".$this->data->article_id."'
			 ".($warehouse_id?" and o.reserve_warehouse_id=$warehouse_id":'')."
			";
//		echo $q.'<br>';
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getAvgInventory($warehouse_id=0)
	{
		$q = "select (fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE(NOW()))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 1 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 2 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 3 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 4 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 5 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 6 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 7 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 8 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 9 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 10 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 11 month))
			+fget_Article_stock_date('".$this->data->article_id."', $warehouse_id, DATE_SUB(DATE(NOW()),interval 12 month))
			)/13 as avg";
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getInventar($warehouse_id=0)
	{
		$q = "SELECT fget_Article_Inventar({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getMultiInventar($warehouse_ids)
	{
		$q = "SELECT `warehouse_id`, SUM(`quantity`) AS `quantity` FROM `article_history` 
            WHERE `article_id` = '{$this->data->article_id}' 
			AND `warehouse_id` IN (" . implode(',', $warehouse_ids) . ")
            GROUP BY `warehouse_id`";
//		echo $q.'<br>';
		return $this->_dbr->getAssoc($q);
	}
	function getOrder($warehouse_id=0)
	{
		$q = "SELECT fget_Article_Order({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getRMA($warehouse_id=0)
	{
		$q = "SELECT fget_Article_RMA({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getMultiRMA($warehouse_ids)
	{
		$q = "select IFNULL((
		select sum(quantity) from (SELECT 1 as quantity
		, IFNULL(auw.warehouse_id, w.warehouse_id) warehouse_id
		FROM rma r
		join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		left JOIN warehouse w ON `default`
		LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
        WHERE rs.problem_id in (4,11) and rs.article_id = '{$this->data->article_id}'
        GROUP BY warehouse_id
			union all 
		SELECT -1 as quantity, warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
        and rs.article_id = '{$this->data->article_id}'
			union all 
		SELECT -1 as quantity, warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
        and rs.article_id = '{$this->data->article_id}'
			union all 
		SELECT 1 as quantity, returned_warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
        and rs.article_id = '{$this->data->article_id}'
			union all 
		SELECT 1 as quantity, warehouse_id
		FROM rma r
		join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
        and rs.article_id = '{$this->data->article_id}'
		) t where warehouse_id IN ( " . implode(',', $warehouse_ids) . " ),0)";
		echo "<pre>$q</pre><br>";
        exit;
        
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getDriver($warehouse_id=0)
	{
		$q = "SELECT fget_Article_driver({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getATS($warehouse_id=0)
	{
		$q = "SELECT fget_Article_ats({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
    
//    function getNotOnFloor($warehouse_id) {
//        global $GLOBALS;
//        
//        $vbw = 'vbarcode_warehouse';
//        $vb = 'b';
//        if ($GLOBALS['CONFIGURATION']['use_dn']) {
//            $vbw = 'barcode_dn';
//            $vb = 'bw';
//        }
//        
//        $query[] = "SELECT DISTINCT 1 AS `quantity`
//            FROM `vbarcode` AS `b`
//            LEFT JOIN `{$vbw}` AS `bw` ON `bw`.`id` = `b`.`id`
//            LEFT JOIN `parcel_barcode_article_barcode` AS `pbab` ON `b`.`id` = `pbab`.`barcode_id` AND `pbab`.`deleted` = 0
//            LEFT JOIN `vparcel_barcode` AS `pb` ON `pbab`.`parcel_barcode_id` = `pb`.`id`
//            LEFT JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `b`.`id` = `pbabd`.`barcode_id` AND `pbabd`.`picking_order_id` != 0
//            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `pbabd`.`picking_order_id`
//            LEFT JOIN `ware_la` ON `ware_la`.`id` = `po`.`ware_la_id` AND `ware_la`.`warehouse_id` = $warehouse_id
//            LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
//            LEFT JOIN `op_article` `opa` ON `opa`.`id`=`b`.`opa_id`
//            LEFT JOIN `ware_la` `opa_wl` ON `opa`.`ware_la_id`=`opa_wl`.`id`
//            WHERE IFNULL(`pb`.`warehouse_id`, `bw`.`last_warehouse_id`) = $warehouse_id
//                AND `{$vb}`.`article_id` = '{$this->data->article_id}' AND `b`.`inactive` = 0
//                AND `bs`.`type` = 'in'";
//        // C articles on parcels
//        $query[] = "SELECT SUM(`pba`.`quantity`) AS `quantity`
//                FROM `vparcel_barcode` AS `pb`
//                JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `pb`.`id`
//                WHERE `pb`.`warehouse_id` = $warehouse_id AND `pba`.`article_id` = '{$this->data->article_id}' 
//                GROUP BY `pb`.`parcel`
//                HAVING `quantity`";
//        // C articles picked in LA (from parcel)
//        $query[] = "SELECT -SUM(`pbad`.`quantity`) AS `quantity`
//                FROM `vparcel_barcode` AS `pb`
//                JOIN `parcel_barcode_article_deduct` AS `pbad` ON `pb`.`id` = `pbad`.`parcel_barcode_id` 
//                    AND `pbad`.`picking_order_id`
//                LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `pbad`.`picking_order_id`
//                LEFT JOIN `ware_la` ON `ware_la`.`id` = `po`.`ware_la_id` AND `ware_la`.`warehouse_id` = $warehouse_id
//                WHERE `pb`.`warehouse_id` = $warehouse_id AND `pbad`.`article_id` = '{$this->data->article_id}' 
//                GROUP BY `pb`.`parcel`
//                HAVING `quantity`";
//        // C articles picked in LA (from loading area)
//        $query[] = "SELECT -SUM(`pbad`.`quantity`) AS `quantity`
//                FROM `parcel_barcode_article_deduct` AS `pbad` 
//                LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `pbad`.`picking_order_id`
//                LEFT JOIN `ware_la` ON `ware_la`.`id` = `po`.`ware_la_id`
//                WHERE `ware_la`.`warehouse_id` = $warehouse_id AND `pbad`.`article_id` = '{$this->data->article_id}' 
//                    AND `pbad`.`parcel_barcode_id` = 0
//                    AND `pbad`.`picking_order_id`
//                GROUP BY `ware_la`.`id`
//                HAVING `quantity`";
//        $query = implode("\n UNION ALL \n", $query);
//        $query = "SELECT SUM(quantity) FROM ( $query ) t";
//        
//        return (int)$this->_dbr->getOne($query);
//    }
    
	function getPieces($warehouse_id=0, $cached = 0)
	{
		$q = "SELECT fget_Article_stock_cache({$this->data->article_id}, {$warehouse_id}, {$cached})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getReserved($warehouse_id=0, $cached = 0)
	{
		$q = "SELECT fget_Article_reserved({$this->data->article_id}, {$warehouse_id})";
		$r = $this->_db->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getWarehouses()
	{
		$warehouses = Warehouse::listAll($this->_db, $this->_dbr);
		foreach ($warehouses as $id=>$warehouse) {
			$warehouses[$id]->getInventar = $this->getInventar($warehouse->warehouse_id);
			$warehouses[$id]->getNewArticle = $this->getNewArticle($warehouse->warehouse_id);
			$warehouses[$id]->getRMA = $this->getRMA($warehouse->warehouse_id);
			$warehouses[$id]->getOrder = $this->getOrder($warehouse->warehouse_id);
			$warehouses[$id]->getSold = $this->getSold($warehouse->warehouse_id);
			$warehouses[$id]->getATS = $this->getATS($warehouse->warehouse_id);
			$warehouses[$id]->getDriver = $this->getDriver($warehouse->warehouse_id);		
			$warehouses[$id]->pieces = (int)$this->getPieces($warehouse->warehouse_id);
			$warehouses[$id]->reserved = (int)$this->getReserved($warehouse->warehouse_id);
			$warehouses[$id]->sold = (int)$this->getSold($warehouse->warehouse_id);
			$warehouses[$id]->available = $warehouses[$id]->pieces - $warehouses[$id]->reserved;
			$warehouses[$id]->ready2ship = (int)$this->getReady2Ship($warehouse->warehouse_id);
			$warehouses[$id]->warehouse_place = $this->_dbr->getOne("select warehouse_place
				from article_warehouse_place where warehouse_id={$warehouse->warehouse_id}
				and article_id='{$this->data->article_id}'");
			$warehouses[$id]->located = $this->_dbr->getOne("select count(*) from ware_loc where warehouse_id=".$warehouse->warehouse_id);
			//$warehouses[$id]->located = $this->_dbr->getOne("SELECT COUNT(*) FROM warehouse_cells WHERE hall_id IN (SELECT id FROM warehouse_halls WHERE warehouse_id=".$warehouse->warehouse_id.");");
		}
//		print_r($warehouses); //die();
		return $warehouses;
	}
	function getWarehouses4article()
	{
        $_t = xdebug_time_index();
        
		$warehouses = Warehouse::list4article($this->_db, $this->_dbr);
        $warehouses_ids = array();
        foreach ($warehouses as $warehouse) {
            $warehouses_ids[] = (int)$warehouse->warehouse_id;
        }
        $warehouses_ids = array_values(array_unique($warehouses_ids));
        
        if ( ! $warehouses_ids) {
            return $warehouses;
        }
        
		foreach ($warehouses as $id=>$warehouse) {
            $warehouses[$id]->getInventar = $this->getInventar($warehouse->warehouse_id);
			$warehouses[$id]->getNewArticle = $this->getNewArticle($warehouse->warehouse_id);
			$warehouses[$id]->getRMA = $this->getRMA($warehouse->warehouse_id);
			$warehouses[$id]->getOrder = $this->getOrder($warehouse->warehouse_id);
			$warehouses[$id]->getSold = $this->getSold($warehouse->warehouse_id);
			$warehouses[$id]->getATS = $this->getATS($warehouse->warehouse_id);
			$warehouses[$id]->getDriver = $this->getDriver($warehouse->warehouse_id);		
			$warehouses[$id]->pieces = (int)$this->getPieces($warehouse->warehouse_id);
			$warehouses[$id]->reserved = (int)$this->getReserved($warehouse->warehouse_id);
			$warehouses[$id]->sold = (int)$this->getSold($warehouse->warehouse_id);
			$warehouses[$id]->available = $warehouses[$id]->pieces - $warehouses[$id]->reserved;
			$warehouses[$id]->ready2ship = (int)$this->getReady2Ship($warehouse->warehouse_id);
			$warehouses[$id]->warehouse_place = $this->_dbr->getOne("select warehouse_place
				from article_warehouse_place where warehouse_id={$warehouse->warehouse_id}
				and article_id='{$this->data->article_id}'");
			$warehouses[$id]->located = $this->_dbr->getOne("select count(*) from ware_loc where warehouse_id=".$warehouse->warehouse_id);
////			//$warehouses[$id]->located = $this->_dbr->getOne("SELECT COUNT(*) FROM warehouse_cells WHERE hall_id IN (SELECT id FROM warehouse_halls WHERE warehouse_id=".$warehouse->warehouse_id.");");
		}
        
//		print_r($warehouses); //die();
		return $warehouses;
	}
    static function getAliases($db, $dbr, $id)
    {
        $aliases = $dbr->getAll("SELECT * from article_alias
			where article_id='$id'");
        if (PEAR::isError($aliases)) {
            return;
        }
        
        //var_dump (xdebug_time_index());
        
        $mulang_fields = array('name','description'); 
        $table_name = 'article_alias';
        
        $aliases_ids = array();
        foreach($aliases as $alias) {
            $aliases_ids[] = (int)$alias->id;
        }
        $aliases_ids = array_values(array_unique($aliases_ids));
        
        if ($aliases_ids) {
            $query = "SELECT `t`.*, 
                    SUBSTRING_INDEX(GROUP_CONCAT(`tl`.`Updated` ORDER BY `tl`.`Updated`),',',-1) AS `last_on`, 
                    SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(`u`.`name`, `tl`.`username`) ORDER BY `tl`.`Updated`), ',' ,-1) AS `last_by`
                FROM `translation` AS `t`
                LEFT JOIN `total_log` AS `tl` ON `tl`.`Table_name` = 'translation' AND `tl`.`TableID` = `t`.`iid`
                LEFT JOIN `users` AS `u` ON CONCAT(REPLACE(`u`.`username`, ' ', ''), '@localhost') = `tl`.`username`
                WHERE `t`.`id` IN (" . implode(',', $aliases_ids) . ") AND `t`.`table_name` = 'article_alias' AND 
                    `t`.`field_name` IN ( " . implode(',', array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'" ;}, $mulang_fields)) . " )
                    AND (`tl`.`field_name` = 'value' OR (`tl`.`field_name` = 'unchecked' AND `tl`.`old_value` = 1 AND `tl`.`new_value` = 0))
                GROUP BY `t`.`iid`";
            $result = $dbr->getAll($query);
                        
            foreach ($aliases as &$alias) {
                foreach ($result as $item) {
                    if ($alias->id == $item->id) {
                        $alias->{"{$item->field_name}_translations"}[$item->language] = $item;
                    }
                }
            }
        }
        
        return $aliases;
        
//        $mulang_fields = array('name','description'); $table_name = 'article_alias';
//		foreach($aliases as $key=>$alias){
//			$edit_id = $alias->id;
//			foreach ($mulang_fields as $fld) {
//				$r = $dbr->getAssoc("select language, iid from translation where id=$edit_id 
//						       and table_name='$table_name' and field_name='$fld'");
//				foreach($r as $language=>$iid) {
//					$r[$language] = $dbr->getRow("select * from translation where iid=$iid");
//					$r[$language]->last_on=
//						$dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
//							from translation 
//							left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
//							where 1
//							and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
//							and translation.iid = $iid
//							group by translation.id
//							order by translation.id+1
//							");
//					$r[$language]->last_by=
//						$dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_on
//							from translation 
//							left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
//							left join users on CONCAT(REPLACE(users.username,' ',''),'@localhost')=total_log.username
//							where 1
//							and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
//							and translation.iid = $iid
//							group by translation.id
//							order by translation.id+1
//							");
//				}
//				$fldname = $fld.'_translations';
//				$aliases[$key]->$fldname = $r;
//			}
//		}
//        return $aliases;
    }
    static function addAlias($db, $dbr, $id,
		$names,
		$descriptions
		)
    {
        $id = (int)$id;
        $r = $db->query("insert into article_alias set 
			article_id='$id'");
		$alias_id = mysql_insert_id();
		foreach($names as $lang=>$value) {
			$value = mysql_real_escape_string($value);
        	$r = $db->query("REPLACE INTO translation set value='$value'
				   , id=$alias_id 
			       , table_name='article_alias' , field_name='name'
			       , language = '$lang'");
    		if (PEAR::isError($r)) print_r($r);
		}
		foreach($descriptions as $lang=>$value) {
			$value = mysql_real_escape_string($value);
        	$r = $db->query("REPLACE INTO translation set value='$value'
				   , id=$alias_id 
			       , table_name='article_alias' , field_name='description'
			       , language = '$lang'");
			if (PEAR::isError($r)) print_r($r);
		}
    }
    static function updateAlias($db, $dbr, $alias_id,
		$names,
		$descriptions
		)
    {
        $alias_id = (int)$alias_id;
		mulang_fields_Update(array('name'), 'article_alias', $alias_id, array('name'=>$names));
		mulang_fields_Update(array('description'), 'article_alias', $alias_id, array('description'=>$descriptions));
/*
		foreach($names as $lang=>$value) {
			$value = mysql_real_escape_string($value);
        	$r = $db->query("REPLACE INTO translation set value='$value'
				   , id=$alias_id 
			       , table_name='article_alias' , field_name='name'
			       , language = '$lang'");
    		if (PEAR::isError($r)) print_r($r);
		}
		foreach($descriptions as $lang=>$value) {
			$value = mysql_real_escape_string($value);
        	$r = $db->query("REPLACE INTO translation set value='$value'
				   , id=$alias_id 
			       , table_name='article_alias' , field_name='description'
			       , language = '$lang'");
			if (PEAR::isError($r)) print_r($r);
		}*/
    }
    static function delAlias($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("delete from article_alias where id=$id");
        $r = $db->query("delete from translation where id=$id and table_name='article_alias'");
    }
	
    static function actAlias($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("update article_alias set inactive = NOT inactive where id=$id");
    }
	
    static function getDocsTranslated($db, $dbr, $article_id, $send_it=0, $type='doc', $lang='german', $get_all = true)
    {
        $select = $get_all ? '*' : ' doc_id, article_id, name, description, send_it, type, shop_it, auto_description ';
        
#		global $debug;
#		global $time;
        $docs = $dbr->getAll("SELECT $select from article_doc 
			where article_id='$article_id' and send_it=$send_it and type='$type' ORDER BY doc_id");
if ($debug) {echo 'new Article6-1: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
		global $smarty;
		$table_name = 'article_doc';
		$fld = 'name';
		$r = array();
		foreach ($docs as $key=>$doc) {
			$r[$doc->doc_id] = $dbr->getAssoc("select language, iid from translation where id={$doc->doc_id} 
					       and table_name='$table_name' and field_name='$fld'");
if ($debug) {echo 'new Article6-2: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
			foreach($r[$doc->doc_id] as $language=>$iid) {
				$r[$doc->doc_id][$language] = $dbr->getRow("select * from translation where iid=$iid");
if ($debug) {echo 'new Article6-3: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$docs[$key]->$language->iid = $iid;
				$docs[$key]->$language->name = $r[$doc->doc_id][$language]->value;
                $docs[$key]->$language->data = '';
                if ($get_all) {
                    $_file = $dbr->getOne("select md5 from prologis_log.translation_files2 tf
                        where tf.table_name='article_doc' and tf.field_name='data' and tf.language='$language' and tf.id='{$doc->doc_id}'");
                    $_file = get_file_path($_file);
                        
                    $docs[$key]->$language->data = base64_encode($_file);
                }
	   			$exts = explode('.', $r[$doc->doc_id][$language]->value); $ext = end($exts);
if ($debug) {echo 'new Article6-4: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$r[$doc->doc_id][$language]->ext = strtolower($ext);
				$docs[$key]->$language->ext = strtolower($ext);
				$docs[$key]->$language->description= $dbr->getOne("select `value` from translation
					where table_name='article_doc' and field_name='description' and language='$language' and id='{$doc->doc_id}'");
				$r[$doc->doc_id][$language]->last_on=
					$dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
						from translation 
						left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
						where 1
						and translation.iid = $iid
						group by translation.id
						order by translation.id+1
						");
if ($debug) {echo 'new Article6-5: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
				$r[$doc->doc_id][$language]->last_by=
					$dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_on
						from translation 
						left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
						left join users on CONCAT(REPLACE(users.username,' ',''),'@localhost')=total_log.username
						where 1
						and translation.iid = $iid
						group by translation.id
						order by translation.id+1
						");
if ($debug) {echo 'new Article6-6: '.round(microtime(true)-$time,3).'<br>';$time = microtime(true);}
			}			   
			$docs[$key]->type = $r[$doc->doc_id][$lang]->type;
			if (!$docs[$key]->type) $docs[$key]->type = $r[$doc->doc_id][$deflang]->type;
		}
//		print_r($r);
		$smarty->assign('data_translations'.$article_id.$type.$send_it, $r);
//		print_r($docs);
//		echo 'data_translations'.$type.$send_it;
        return $docs;
    }
    static function addLargeDocTranslated($db, $dbr, $article_id,
		$name,
		$description,
		$fn, $lang, $edit_id=0
		)
    {
		$name = mysql_real_escape_string($name);
		$description = mysql_real_escape_string($description);
        $send_it = 1; $type = 'doc';
		if (!$edit_id) {
			$r = $db->query("insert into article_doc set 
					article_id=$article_id, type='$type',
					send_it=$send_it");
			$edit_id = mysql_insert_id();
		}			
		$table_name = 'article_doc'; $fld = 'name';
		$iid = (int)$dbr->getOne("select iid from translation where id=$edit_id 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
		if ($iid) {
			$q = "update translation set value='$name' where iid='$iid'";
		} else {
			$q = "insert into translation set value='$name'
			, id=$edit_id 
			, table_name='$table_name' , field_name='$fld'
			, language = '$lang'";
		}
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		
		$fld = 'description';
		$iid = (int)$dbr->getOne("select iid from translation where id=$edit_id 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
		if ($iid) {
			$q = "update translation set value='$description' where iid='$iid'";
		} else {
			$q = "insert into translation set value='$description'
			, id=$edit_id 
			, table_name='$table_name' , field_name='$fld'
			, language = '$lang'";
		}
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		$fld = 'data';
		$iid = (int)$dbr->getOne("select iid from prologis_log.translation_files2 where id='$edit_id' 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
        $file_content = file_get_contents($fn);
        $md5 = md5($file_content);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $file_content);
        }
        
        if ($iid) {
			$r = $db->query("update prologis_log.translation_files2 set md5='$md5' where iid=$iid");
		} else {
			$r = $db->query("insert into prologis_log.translation_files2 set 
					id='$edit_id', 
					table_name='$table_name' , field_name='$fld',
					language = '$lang',
					md5='$md5'");
		}
		// add pdf content
        self::addPdfContent($db,$dbr,$name,$fn,$lang,$edit_id);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		return $edit_id;	
    }

    /**
     * @param $db
     * @param $dbr
     * @param $name
     * Adding pdf content
     */
    static function addPdfContent($db,$dbr,$name,$fn,$lang,$edit_id){
        $ext = array_pop(explode('.', $name));
        $ext = strtolower($ext);
        $table_name = 'article_doc';
        $fld = 'data_pdf';
        if(in_array($ext,['doc','docx'])){
            $file_content = file_get_contents($fn);
            $md5_pdf = convertAndsavePDF($name,$file_content);
            $iid_pdf = (int)$dbr->getOne("select iid from prologis_log.translation_files2 where id='$edit_id' 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
            if($iid_pdf){
                $r = $db->query("update prologis_log.translation_files2 set md5='{$md5_pdf}' where iid={$iid_pdf}");
            }else{
                $r = $db->query("insert into prologis_log.translation_files2 set 
					id='$edit_id', 
					table_name='$table_name' , field_name='$fld',
					language = '$lang',
					md5='$md5_pdf'");
            }
        }
    }

	function duplicate() {
		$new_id = $this->getNextId();
        $fields = $this->_dbr->getAll("EXPLAIN article");
		$q = "";
		foreach($fields as $field) {
			if ($q!='') $q .=', ';
			$field = $field->Field;
			if ($field=='article_id') {
				$q .= "`$field` = '$new_id'";
			} elseif ($field=='iid') {
				$q .= "`$field` = NULL";
			} else { 
				$q .= "`$field` = '".mysql_real_escape_string($this->data->$field)."'";
			}
		}
		$q = "insert into article set ".$q;
		$r = $this->_db->query($q);
		if (PEAR::isError($r)) { print_r($r); die();}
	// names, descriptions, costs etc
		$r = $this->_db->query("insert into translation (language, table_name, field_name, id, value)
			select language, table_name, field_name, $new_id as id, value
			from translation where table_name='article' and id='{$this->data->article_id}'");
		if (PEAR::isError($r)) { print_r($r); die();}
	// aliases
		foreach($this->aliases as $alias) {	
			$r = $this->_db->query("insert into article_alias (article_id,inactive)
				values ($new_id, {$alias->inactive})");
			if (PEAR::isError($r)) { print_r($r); die();}
			$new_alias_id = mysql_insert_id();
			$r = $this->_db->query("insert into translation (language, table_name, field_name, id, value)
				select language, table_name, field_name, $new_alias_id as id, value
				from translation where table_name='article_alias' and id='{$alias->id}'");
			if (PEAR::isError($r)) { print_r($r); die();}
		}
	// comments
		$r = $this->_db->query("insert into article_comment (article_id,comment,create_date,username)
			select $new_id,comment,create_date,username
			from article_comment where article_id='{$this->data->article_id}'");
		if (PEAR::isError($r)) { print_r($r); die();}
	// senddocs
		foreach($this->senddocs as $senddoc) {	
			$q = "insert into article_doc (article_id,name,description,data,send_it,type)
				values ($new_id
				, '".mysql_real_escape_string($senddoc->name)."'
				, '".mysql_real_escape_string($senddoc->description)."'
				, '".($senddoc->data)."'
				, '".($senddoc->send_it)."'
				, 'doc'
				)";
			$r = $this->_db->query($q);
			if (PEAR::isError($r)) { print_r($r); die();}
			$new_senddoc_id = mysql_insert_id();
			$q = "insert into translation (language, table_name, field_name, id, value)
				select language, table_name, field_name, $new_senddoc_id as id, value
				from translation where table_name='article_doc' and id='{$senddoc->doc_id}'";
			$r = $this->_db->query($q);
			if (PEAR::isError($r)) { print_r($r); die();}
		}
	// docs
		foreach($this->docs as $doc) {	
			$r = $this->_db->query("insert into article_doc (article_id,name,description,data,send_it,type)
				values ($new_id
				, '".mysql_real_escape_string($doc->name)."'
				, '".mysql_real_escape_string($doc->description)."'
				, '".($doc->data)."'
				, '".($doc->send_it)."'
				, 'doc'
				)");
			if (PEAR::isError($r)) { print_r($r); die();}
		}
	// pics
		foreach($this->pics as $doc) {	
			$r = $this->_db->query("insert into article_doc (article_id,name,description,data,send_it,type)
				values ($new_id
				, '".mysql_real_escape_string($doc->name)."'
				, '".mysql_real_escape_string($doc->description)."'
				, '".($doc->data)."'
				, '".($doc->send_it)."'
				, 'pic'
				)");
			if (PEAR::isError($r)) { print_r($r); die();}
			$new_pic_id = mysql_insert_id();
			$r = $this->_db->query("update article set picture_URL='/images/cache/undef_src_article_picid_{$new_pic_id}_image.jpg' where article_id='$new_id'");
		}
	// materials
		$r = $this->_db->query("insert into article_sh_material (article_id,sh_material_id,quantity)
			select $new_id,sh_material_id,quantity
			from article_sh_material where article_id='{$this->data->article_id}'");
		if (PEAR::isError($r)) { print_r($r); die();}
	// parcels
		$r = $this->_db->query("insert into article_parcel (article_id,dimension_l,dimension_h,dimension_w,weight_parcel)
			select $new_id,dimension_l,dimension_h,dimension_w,weight_parcel
			from article_parcel where article_id='{$this->data->article_id}'");
		if (PEAR::isError($r)) { print_r($r); die();}
	// reps
		$r = $this->_db->query("insert into article_rep (article_id,rep_id)
			select $new_id,rep_id
			from article_rep where article_id='{$this->data->article_id}'");
		if (PEAR::isError($r)) { print_r($r); die();}
		return $new_id;
	}
	function getSoldArray($warehouses)
	{
		$q = "select IFNULL((select SUM(o.quantity)
			FROM orders o
			$period_join
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
			WHERE o.article_id = '".$this->data->article_id."' and o.manual=0
			AND o.sent
			$period_where
			AND au.deleted = 0 and o.send_warehouse_id in (".implode(',',$warehouses).")
			),0)";
//		echo $q.'<br>';	
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	
	function getNewArticleArray($warehouses)
	{
		$q = "select IFNULL((select SUM(o.new_article_qnt)
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
			WHERE 1 and o.article_id='".$this->data->article_id."' and o.manual=0
			AND not o.sent
			and o.new_article and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
			AND au.deleted = 0 and o.new_article_warehouse_id in (".implode(',',$warehouses).")
			),0)";
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) {aprint_r($r); die();}
		return $r;
	}
	
	function getReservedArray($warehouses)
	{
		$q = "select IFNULL((select SUM(o.quantity)
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
			WHERE o.article_id = '".$this->data->article_id."' and o.manual=0
			AND o.sent=0
			AND au.deleted = 0 and o.reserve_warehouse_id in (".implode(',',$warehouses).")
			),0)
			+
			IFNULL((select SUM(1/*o.new_article_qnt*/)
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
			WHERE o.new_article_id ='".$this->data->article_id."'
			and new_article and not lost_new_article and not new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
			AND au.deleted = 0  and o.new_article_warehouse_id in (".implode(',',$warehouses).")
			),0)
			+
			IFNULL((select SUM(wwa.qnt)
			FROM wwo_article wwa
			WHERE wwa.article_id ='".$this->data->article_id."'
			and not wwa.taken
			and wwa.reserved_warehouse in (".implode(',',$warehouses).")
			),0)
			";
//		echo $q.'<br>';
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getInventarArray($warehouses)
	{
		$q = "select IFNULL((select sum(quantity) from article_history 
			where article_id='".$this->data->article_id."' 
			and warehouse_id=$warehouse_id in (".implode(',',$warehouses).")),0)";
//		echo $q.'<br>';
		return $this->_dbr->getOne($q);
	}
	function getOrderArray($warehouses)
	{
		$q = "select IFNULL((select sum(qnt_delivered) from op_article 
			join op_order on op_order.id=op_article.op_order_id
			where 
			article_id='".$this->data->article_id."' and add_to_warehouse
			and op_article.warehouse_id in (".implode(',',$warehouses).")),0)";
//		echo $q.'<br>';
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getRMAArray($warehouses)
	{
		$q = "select IFNULL((
		select sum(quantity) from (SELECT 1 as quantity
		, IFNULL(auw.warehouse_id, w.warehouse_id) warehouse_id
		FROM rma r
		join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		left JOIN warehouse w ON `default`
		LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
		WHERE rs.problem_id in (4,11) and rs.article_id = '".$this->data->article_id."'
			union all 
		SELECT -1 as quantity, warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
		and rs.article_id = '".$this->data->article_id."'
			union all 
		SELECT -1 as quantity, warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
		and rs.article_id = '".$this->data->article_id."'
			union all 
		SELECT 1 as quantity, returned_warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
		and rs.article_id = '".$this->data->article_id."'
			union all 
		SELECT 1 as quantity, warehouse_id
		FROM rma r
		JOIN rma_spec rs ON r.rma_id=rs.rma_id
		WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
		and rs.article_id = '".$this->data->article_id."'
		) t  where warehouse_id in (".implode(',',$warehouses).")
		),0)";
//		echo $q.'<br>';
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getDriverArray($warehouses)
	{
		$q = "select 
			(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
				JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					and aa.article_id='".$this->data->article_id."'
				and (warehouse.warehouse_id in (".implode(',',$warehouses)."))),0))
			-(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					and aa.article_id='".$this->data->article_id."'
				and (wwo_article.from_warehouse in (".implode(',',$warehouses)."))),0))
			-(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					and aa.article_id='".$this->data->article_id."'
				and (warehouse.warehouse_id in (".implode(',',$warehouses)."))),0))
			+(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article aa ON wwo_article.article_id=aa.article_id and aa.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					and aa.article_id='".$this->data->article_id."'
				and (wwo_article.to_warehouse in (".implode(',',$warehouses)."))),0))
			";
//		echo $q.'<br>';
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
	function getAtsArray($warehouses)
	{
		$q = "select 
			(select IFNULL((select sum(ats_item.quantity)
				from ats_item
				join ats on ats.id=ats_item.ats_id
				JOIN article aa ON ats_item.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked
					and aa.article_id='".$this->data->article_id."'
				and (warehouse.warehouse_id in (".implode(',',$warehouses)."))),0))
			-(select IFNULL((select sum(ats.quantity)
				from ats
					JOIN article aa ON ats.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked
					and aa.article_id='".$this->data->article_id."'
				and (warehouse.warehouse_id in (".implode(',',$warehouses)."))),0))
			";
//		echo $q.'<br>';
		$r = $this->_dbr->getOne($q);	
		if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}
    /**
     * Link $article_id Article to current Article. Linked Articles become group with ID = group_id.
     * If group exists - all new links to linked Articles will have group_id like linked.
     * @param int $article_id
     */
    function groupWith($article_id)
    {
        $article_id = (int)$article_id;
        if ($article_id) {
            $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
            
            $group_id = $this->data->group_id;
            if (!$group_id) {
                $group_id = $db->getOne("SELECT group_id FROM article 
                    WHERE admin_id = 0 AND article_id = " . $this->data->article_id);
            }
            
            if (!$group_id) {
                $max = $db->getOne("SELECT MAX(id) FROM article_group");
                $group_id = (int)$max + 1;
                $db->query("insert into article_group set id = $group_id");
            }
            
            $db->query("update article set group_id = $group_id 
                where admin_id = 0 and article_id = " . $article_id . " limit 1");
            $this->data->group_id = $group_id;
        }
    }
	function getPiecesArray($warehouses)
	{
		if (!count($warehouses)) return 0;
		return $this->getInventarArray($warehouses)
			+$this->getNewArticleArray($warehouses)
			+$this->getRMAArray($warehouses)
			+$this->getOrderArray($warehouses)
			-$this->getSoldArray($warehouses)
			+$this->getDriverArray($warehouses);
			+$this->getAtsArray($warehouses);
	}
	function printLabel($type) {
    	global $siteURL;
		if ($type==1) {
			$html = file_get_contents($siteURL."article_label.php?original_article_id=".$this->data->article_id);
//			echo $siteURL."article_label.php?original_article_id=".$this->data->article_id;
		} elseif ($type==2) {
			$html = file_get_contents($siteURL."article_label.php?pallet&original_article_id=".$this->data->article_id);
//			echo $siteURL."article_label.php?pallet&original_article_id=".$this->data->article_id;
		}
//		die($html);
		require_once("dompdf/dompdf_config.inc.php");
		$dompdf = new DOMPDF();
		$dompdf->set_paper('A4','landscape');
		$dompdf->load_html(($html));
		$dompdf->render();
		return $dompdf->output();
		$db = $this->_db;
    	global $english;
        $pdf = &File_PDF::factory('L', 'mm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(true);
	    $pdf->addPage();
        $pdf->setFillColor('rgb', 0, 0, 0);
        $pdf->setDrawColor('rgb', 0, 0, 0);
		$pdf->setLeftMargin(200);
		$pdf->setTopMargin(200);
        $pdf->setFont('arial','B', 180);
		$pdf->setXY(0, 30); $pdf->multiCell(290, 5, $this->data->article_id, 0, 'C');
		$y = 60; $x = 10;
		global $siteURL;
			$pic = imagecreatefromjpeg($siteURL.$this->data->picture_URL);
			if ($pic) {
				$destsx = 50 * imagesx($pic) / imagesy($pic) ;
				imagejpeg ($pic, 'tmppic/tmparticle.jpg'); 
	    	    $pdf->image('tmppic/tmparticle.jpg', 150-$destsx/2, $y, 0, 50);
				unlink('tmppic/tmparticle.jpg');
			}
		$y = 120; $x = 10;
		$langs = getLangsArray();
        $pdf->setFont('arial','B', 12);
		foreach($langs as $lang_id=>$lang_name) {
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, utf8_decode($lang_name), 1, 'C'); $x += 50;
		}
 		$y = max($y,$pdf->getY());
		$vline_y = $y;
		$x = 10; 
		$names = $dbr->getAssoc("SELECT language, value
						FROM translation
						WHERE table_name = 'article'
						AND field_name = 'name'
						AND id = '{$this->data->article_id}'
						");
		foreach($langs as $lang_id=>$lang_name) {
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, utf8_decode($names[$lang_id]), 0, 'C'); $x += 50;
	 		$nexty = max($nexty,$pdf->getY());
 		}
 		$y = $nexty; $x = 10; 
		$pdf->line($x,$y,$x+50*count($langs),$y);
		$names = $dbr->getAssoc("SELECT language, value
						FROM translation
						WHERE table_name = 'article'
						AND field_name = 'description'
						AND id = '{$this->data->article_id}'
						");
        $pdf->setFont('arial','', 10);
		foreach($langs as $lang_id=>$lang_name) {
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, utf8_decode($names[$lang_id]), 0, 'C'); $x += 50;
	 		$nexty = max($nexty,$pdf->getY());
		}
		$cnt = 0;
 		$x = 10; $y = $nexty;
		$pdf->line($x,$y,$x+50*count($langs),$y);
		foreach($langs as $lang_id=>$lang_name) {
			$pdf->line($x+$cnt*50,$vline_y,$x+$cnt*50,$y); $cnt++;
		}
			$pdf->line($x+$cnt*50,$vline_y,$x+$cnt*50,$y);
		$y = $nexty+10;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "length", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "width", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "height", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "weight", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "girth", 1, 'C');
 		$x = 10; $y += 5;
		foreach($this->parcels as $parcel) {
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "{$parcel->dimension_l}(cm) "
			.(number_format($parcel->dimension_l/2.54,2))."(in)", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "{$parcel->dimension_w}(cm) "
			.(number_format($parcel->dimension_w/2.54,2))."(in)", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "{$parcel->dimension_h}(cm) "
			.(number_format($parcel->dimension_h/2.54,2))."(in)", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "{$parcel->weight_parcel}(kg) "
			.(number_format($parcel->weight_parcel/0.453,2))."(p)", 1, 'C'); $x += 50;
			$pdf->setXY($x, $y); $pdf->multiCell(50, 5, "{$parcel->bandmass}(m) "
			.(number_format($parcel->bandmass/0.0254,2))."(in)", 1, 'C');
		}
	    $pdf->close();
	    return $pdf->getOutput();
	}
	/**
	 * Get subarticle for current article
	 * @param int|array $articles article id
	 * @return array articles id
	 */
	public static function getSubArticles($articles)
	{
		$db = \label\DB::getInstance(\label\DB::USAGE_READ);
		if (empty($articles)) {
			return array();
		}
		if (!is_array($articles)) {
			$articles = array($articles);
		}
		$query = '
			SELECT article_sh_material.sh_material_id
			FROM article_sh_material
			WHERE article_sh_material.article_id IN ('.implode(',', $articles).')';
		$rows = $db->getAll($query);
		$result = array();
		foreach ($rows as $row) {
			$result[] = $row->sh_material_id;
		}
		return $result;
	}
	/**
	 * Generate list of docs for some article/articles.
	 * @param int|array $articles article identifiers
	 * @param int $usage where docs will be used, use const DOCS_USAGE_*
	 * @return array
	 */
	public static function getDocsFor($articles, $usage = '')
	{
		$db = \label\DB::getInstance(\label\DB::USAGE_READ);
		$conditionUsage = '';
		if ($usage === self::DOCS_USAGE_SITE) {
			$conditionUsage = 'AND shop_it = 1';
		} elseif ($usage === self::DOCS_USAGE_MAIL) {
			$conditionUsage = 'AND send_it = 1';
		}
		$q = "
			SELECT article_doc.*, DATE(MAX(tl.updated)) updated
			FROM article_doc
				LEFT JOIN total_log tl ON
					tl.table_name = 'article_doc'
					AND tl.field_name = 'doc_id'
					AND tl.tableid = article_doc.doc_id
			WHERE 1
				".$conditionUsage."
				AND article_id IN (".implode(',', $articles).")
			GROUP BY doc_id";
		return $db->getAll($q);
	}
    /**
     * Get associated articles for current Article using table article_group
     * @return array
     */
    public function getAssociated()
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $associated = [];
        $group_id = $this->data->group_id;
        if ($group_id) {
            $associated = $dbr->getCol("SELECT `article_id` FROM `article` 
                WHERE `group_id` = $group_id AND `admin_id` = 0 AND `article_id` <> " . $this->data->article_id);
        }
        
        return $associated;
    }
    /**
     * Get next not used article_id
     * @return id
     */
    public function getNextId($admin_id = 0, $digits = 7)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        
        $min = (int)pow(10, $digits - 6);
        $where = "";
        if ($admin_id) {
        	$on = "and a1.admin_id = {$admin_id}";
        	$where = "and a.admin_id = {$admin_id}";
        }
        $id = $db->getOne("select
                IFNULL(min(a.article_id+1), {$min}) id
            from article a
                left join article a1 on a1.article_id = (a.article_id+1) {$on}
            where a.article_id >= {$min} and a1.article_id is null {$where}");
        
        return $id;
    }
}
?>