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
require_once 'util.php';

class OPT
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
    function OPT($db, $dbr, $id = 0, $cached)
    {
$time = getmicrotime();
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('OPT::OPT expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN opt");
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
            $r = $this->_db->query("SELECT * FROM opt WHERE id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("OPT : record $id does not exist");
                return;
            }
# echo 'before: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
			$this->articles = array_merge(OPT::getArticles($this->_db, $this->_dbr, $id, '', $cached)
				, OPT::getArticles($this->_db, $this->_dbr, $id, 1, $cached));
//			print_r($this->articles); die();
			if (!$cached) {
				$last_year_sale = array();
				foreach($this->articles as $article) {
					$last_year_sale[$article->id]['All'] = $article->last_year_sale;
				}
				$this->setPar('last_year_sale', $last_year_sale);
				$effective_sold = array();
				foreach($this->articles as $article) {
					$effective_sold[$article->id]['All'] = $article->effective_sold;
				}
				$this->setPar('effective_sold', $effective_sold);
				$last_year_sold = array();
				foreach($this->articles as $article) {
					$last_year_sold[$article->id]['All'] = $article->last_year_sold;
				}
				$this->setPar('last_year_sold', $last_year_sold);
				$last_year_avg = array();
				foreach($this->articles as $article) {
					$last_year_avg[$article->id] = $article->last_year_avg;
				}
				$this->setPar('last_year_avg', $last_year_avg);
			}
# echo 'articles: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
//			print_r($this->articles);
			$this->warelist = OPT::getWarehouses($this->_db, $this->_dbr, $id);
# echo 'warelist: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
			$this->sellerlist = OPT::getSellers($this->_db, $this->_dbr, $id);
# echo 'sellerlist: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
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
            $this->_error = PEAR::raiseError('OPT::update : no data');
        }
        foreach ($this->data as $field => $value) {
			{
	            if ($query) {
	                $query .= ', ';
	            }
				$query .= "`$field`='".mysql_escape_string($value)."'";
			};
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE id='" . mysql_escape_string($this->data->id) . "'";
        }
//		echo "$command op_auto SET $query $where";
        $r = $this->_db->query("$command opt SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
			die();
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
            $this->_error = PEAR::raiseError('OPT::delete : no data');
        }
		$id = (int)$this->data->id;
        $r = $this->_db->query("DELETE FROM opt_article WHERE opt_id=$id");
        if (PEAR::isError($r)) aprint_r($r);
        $r = $this->_db->query("DELETE FROM opt WHERE id=$id");
        if (PEAR::isError($r)) aprint_r($r);
    }



    /**
    * @return array
    * @param object $db
    * @param object $offer
    * @desc Get all groups in an offer
    */
    static function getWarehouses($db, $dbr, $opt_id)
    {
		$r = $dbr->getAssoc("select 0, 'Total' name
			union
			select ow.warehouse_id, w.name from opt_warehouse ow
			join warehouse w on w.warehouse_id=ow.warehouse_id where opt_id=$opt_id
			order by IF(name='Total',0,name)");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

    function setWarehouses($warelist)
    {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$id = $this->data->id;
		$q = "delete from opt_warehouse where opt_id=$id";
		$r = $db->query($q);
		foreach ($warelist as $key=>$dummy) {
			$q = "insert into opt_warehouse set opt_id=$id, warehouse_id=$key";
			$r = $db->query($q);
			if (PEAR::isError($r)) { aprint_r($r); }
		}
	}

    static function getSellers($db, $dbr, $opt_id)
    {
		$r = $dbr->getAssoc("select 0, 'Total' username
			union
			select username, username from opt_seller os where opt_id=$opt_id
			order by IF(username='Total',0,username)");
        if (PEAR::isError($r)) aprint_r($r);
		return $r;
	}

    function setSellers($sellerlist)
    {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$id = $this->data->id;
		$q = "delete from opt_seller where opt_id=$id";
		$r = $db->query($q);
		foreach ($sellerlist as $key=>$dummy) {
			$q = "insert into opt_seller set opt_id=$id, username='$key'";
			$r = $db->query($q);
			if (PEAR::isError($r)) { aprint_r($r); }
		}
	}

    function setPar($par_code, $list)
    {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$id = $this->data->id;
		$par_id = (int)$dbr->getOne("select id from opt_article_par where code='$par_code'");
//		echo "<br>$par_id ($par_code)<br>";
//		print_r($list);
		foreach ($list as $opt_article_id=>$seller) {
			foreach ($seller as $username=>$rec) {
				foreach ($rec as $month_id=>$value) {
					$opt_article_par_value = $dbr->getRow("select * from opt_article_par_value 
						where opt_id=$id
						and opt_article_id=$opt_article_id
						and opt_article_par_id=$par_id
						and seller_username='$username'
						and month=$month_id");
					$opt_article_par_value_id = $opt_article_par_value->id;
					$opt_article_par_value_value = $opt_article_par_value->par_value;
					if ($opt_article_par_value_value!=$value) {
						if ($opt_article_par_value_id) {
							$q = "update opt_article_par_value set par_value='$value'
								where id=$opt_article_par_value_id
								";
						} else {
							$q = "insert into opt_article_par_value set opt_id=$id
								, opt_article_id=$opt_article_id
								, opt_article_par_id=$par_id
								, seller_username='$username'
								, month=$month_id
								, par_value='$value'
								";
						}
//					echo $q.'<br>';
						$r = $db->query($q);
						if (PEAR::isError($r)) { aprint_r($r); die();}
					}
				} // foreach month
			} // foreach seller
		} // foreach article
	}

	static function getPar($db, $dbr, $opt_id, $opt_article_id, $par_code)
    {
		$sellers = SellerInfo::listArrayActive($db, $dbr);
		$sellers['All'] = 'All sellers';
		foreach($sellers as $username=>$dummy) {
			$q = "select month, par_value from opt_article_par_value opv
				join opt_article_par op on opv.opt_article_par_id=op.id 
				where opv.opt_id=$opt_id 
				and opt_article_id=$opt_article_id
				and opv.opt_article_par_id=(select id from opt_article_par where code='$par_code')
				and opv.seller_username='$username'
				";
			$r = $dbr->getAssoc($q);
        	if (PEAR::isError($r)) aprint_r($r);
			$sellers[$username] = $r;
		}
		return $sellers;
	}

	static function getMonthRest($db, $dbr, $year)
    {
		$q = "select id, if ($year*12+id = year(now())*12+month(now()), DATEDIFF(LAST_DAY(NOW()), NOW()),
			if ($year*12+id < year(now())*12+month(now())
				,0
				,DAY(LAST_DAY(CONCAT(YEAR(NOW()),'-',id,'-01')))
			)) days_rest
			from month
				";
		$r = $dbr->getAssoc($q);
		return $r;
	}


    static function getArticles($db, $dbr, $opt_id, $cons='', $cached=0)
    {
$time = getmicrotime();
		if (!$dbr->getOne("select count(*) from opt_article where opt_id=$opt_id")) return array();
		require_once 'lib/Config.php';
		$config = Config::getAll($db, $dbr);
		global $supplier_filter;
		global $supplier_filter_str;
		if (strlen($supplier_filter))
			$supplier_filter_str1 = " and opc.id in ($supplier_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if ($cons){
			$article_ids = ' and a1.cons_id=a.article_id ';
			$article_ids1 = ' a1.cons_id ';
			$source = "(SELECT 
				0 as take_real
				, ac.ordering
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
				,'' picture_URL
				from article_cons ac left join article a1 on ac.article_id=a1.cons_id
				group by ac.article_id)";
			$key = 'cons_id';	
			$force_key = ' force key for join (cons_id) ';
		} else {
			$source = 'article';
			$article_ids = ' and a1.article_id=a.article_id ';
			$article_ids1 = ' a1.article_id ';
			$key = 'article_id';	
			$force_key = '';
		}
		
		$warehouses = OPT::getWarehouses($db, $dbr, $opt_id);
		$ware_set = array();
		foreach($warehouses as $ware_id=>$dummy) $ware_set[]=$ware_id;
		$ware_set = implode(",", $ware_set);
		$str1 = ''; $str2 = ''; $str3 = '';
		foreach ($warehouses as $id=>$warehouse) {
			$str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					join article a1 $force_key on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids
					and o.manual=0
					AND o.sent=0
					AND au.deleted = 0 
					and (o.reserve_warehouse_id=$id 
						or ($id=0 and o.reserve_warehouse_id in ($ware_set))
						)
					),0)) 
						+ (select IFNULL((select SUM(o.new_article_qnt)
					FROM (select * from orders where new_article_id is not null) o
					join article a1 $force_key on o.new_article_id=a1.article_id and a1.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids
					AND o.new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND o.sent=0
					AND au.deleted = 0 
					and (o.new_article_warehouse_id=$id 
						or ($id=0 and o.new_article_warehouse_id in ($ware_set))
						)
					),0))
						+ (select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					join article a1 $force_key on wwa.article_id=a1.article_id and a1.admin_id=0
					WHERE 1
					$article_ids
					and not wwa.taken
					and (wwa.reserved_warehouse=$id 
						or ($id=0 and wwa.reserved_warehouse in ($ware_set))
						)
					),0))
						as reserved_$id
			, (select IFNULL((select SUM(o.new_article_qnt)
					FROM orders o
					JOIN article a1 $force_key ON o.article_id=a1.article_id and a1.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					$article_ids 
		#			AND not o.sent
					and o.new_article and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 
					and (o.new_article_warehouse_id=$id 
						or ($id=0 and o.new_article_warehouse_id in ($ware_set))
						)
					),0)) as newarticle_$id
			,(select IFNULL((select sum(quantity) 
					from article_history 
					join article a1 $force_key on article_history.article_id=a1.article_id and a1.admin_id=0
					WHERE 1
					$article_ids
					and (article_history.warehouse_id=$id 
						or ($id=0 and article_history.warehouse_id in ($ware_set))
						)
					),0)) as inventar_$id
			,(select IFNULL((select sum(qnt_delivered) 
					from op_article 
					join article a1 $force_key on op_article.article_id=a1.article_id and a1.admin_id=0
						where 1
					$article_ids
						and add_to_warehouse
						and (op_article.warehouse_id=$id 
							or ($id=0 and op_article.warehouse_id in ($ware_set))
							)
					),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					left JOIN warehouse w ON `default`
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE 1
					$article_ids
					and rs.problem_id in (4,11) 
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id 
						or ($id=0 and IFNULL(auw.warehouse_id, w.warehouse_id) in ($ware_set))
						)
					),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE 1
					$article_ids
					and rs.add_to_stock = 0 and rs.back_wrong_delivery=1 
					and (rs.warehouse_id=$id 
						or ($id=0 and rs.warehouse_id in ($ware_set))
						)
					),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE 1
					$article_ids
					and rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					and (rs.warehouse_id=$id 
						or ($id=0 and rs.warehouse_id in ($ware_set))
						)
					),0))
				) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					join article a1 $force_key on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids
					and o.manual=0
					AND o.sent
					AND au.deleted = 0 
					and (o.send_warehouse_id=$id 
						or ($id=0 and o.send_warehouse_id in ($ware_set))
						)
					),0)) as sold_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
						and total_log.tableid=o.id and total_log.New_value=1
					join article a1 $force_key on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1
					$article_ids
					and o.manual=0
					AND total_log.updated >= DATE_SUB(NOW(), INTERVAL 
					".((int)Config::get($db, $dbr, 'items_sold_per_xx_months')*30)." DAY)
					AND au.deleted = 0 
					and (o.send_warehouse_id=$id 
						or ($id=0 and o.send_warehouse_id in ($ware_set))
						)
					),0)) as sold90_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					$article_ids
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_taken_in_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					$article_ids
				and (wwo_article.from_warehouse=$id or $id=0)),0)) as driver_taken_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					$article_ids
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_delivered_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					$article_ids
				and (wwo_article.to_warehouse=$id or $id=0)),0)) as driver_delivered_in_$id
			,(select IFNULL((select sum(ats.quantity)
				from ats
					JOIN article a1 $force_key ON ats.article_id=a1.article_id and a1.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					$article_ids 
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_out_$id
			,(select IFNULL((select sum(ats_item.quantity)
				from ats
					JOIN ats_item ON ats.id=ats_item.ats_id
					JOIN article a1 $force_key ON ats_item.article_id=a1.article_id and a1.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked 
					$article_ids 
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_in_$id
			";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id 
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id 
				+ ats_in_$id - ats_out_$id + newarticle_$id as pieces_$id
					  , inventar_$id+order_$id+rma_$id-sold_$id-reserved_$id 
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id
				+ ats_in_$id - ats_out_$id + newarticle_$id as available_$id";
		}

        $script = basename($_SERVER['SCRIPT_FILENAME']);
		$q = "select /* $script -> OPT::getArticles */ DATEDIFF(LAST_DAY(NOW()), NOW()) date_diff, t.*
				$str2
				from (
			SELECT opta.id
				 $str1
			, opc.active
			, a.take_real
			, a.ordering
			, a.supplier_article_id
			, oa.quantity_to_order
			, a.company_id
			, a.category_id
			, opc.name as supplier_name
			, concat(ifnull(opc.name,''),a.ordering) as supplier_name_ordering
			, ".($cons?"a.name":"(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id)")." as article_short_name
			, a.article_id
			, a.volume as article_volume
			, a.volume_per_single_unit as article_volume_per_single_unit
#			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id $article_ids
#				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
#				), 0) as order_in_prod_qnt
#			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id $article_ids
#				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2 
#				and (not opa.add_to_warehouse or opa.add_to_warehouse is null)
#				), 0) as order_on_way_qnt
			, a.desired_daily as sales_per_day
			, oa.container_id as container_id
			, oct.name as container
			, oct.volume as container_volume
			, a.desired_daily
			, opc.period
			,(select count(*) from article_rep where rep_id=a.article_id) as is_rp
			,IFNULL((SELECT min( opc.eda ) FROM op_article opa 
				JOIN op_order opo ON opa.op_order_id = opo.id
				LEFT JOIN op_order_container opc ON opc.order_id = opo.id 
				WHERE opc.eda >= now( ) and article_id  = a.article_id and (opc.arrival_date='0000-00-00' or opc.arrival_date is null))
				, (SELECT max( opc.eda ) FROM op_article opa 
				JOIN op_order opo ON opa.op_order_id = opo.id
				LEFT JOIN op_order_container opc ON opc.order_id = opo.id 
				WHERE opc.eda < now( ) and article_id  = a.article_id and (opc.arrival_date='0000-00-00' or opc.arrival_date is null))) 
				as mineda
			, opta.opened	
			, opta.comment
			, opta.multiply
			, opta.main
			, ".($cons?"'_cons'":"''")." as cons
			, a.picture_URL
			FROM $source a
			LEFT JOIN op_company opc ON a.company_id=opc.id
			LEFT JOIN op_company_category opcat ON a.category_id=opcat.id
			LEFT JOIN op_auto oa ON a.article_id=oa.article_id
			LEFT JOIN opt_article opta ON a.article_id=".($cons?'opta.cons_article_id':'opta.article_id')."
			LEFT JOIN op_container oct on oa.container_id=oct.id
			WHERE NOT a.admin_id $supplier_filter_str1
				and opta.opt_id=$opt_id
			)t 
			order by company_id, category_id, article_id";
//			echo $q."<br>";
        $r = $dbr->getAll($q);
# echo 'main qry: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		$year = $dbr->getOne("select `year` from opt where id=$opt_id");
		$op_eta_ptd_diff = (int)Config::get($db, $dbr, 'op_eta_ptd_diff');
		$warehouses = OPT::getWarehouses($db, $dbr, $opt_id);
		foreach($warehouses as $key=>$dummy) $warehouses[$key]=$key;
		$warehouses = implode(',', $warehouses);
		$usernames = OPT::getSellers($db, $dbr, $opt_id);
		foreach($usernames as $key=>$dummy) $usernames[$key]=$key;
		$usernames_array = $usernames;
		$usernames1 = implode("','", $usernames);
		$usernames = implode(',', $usernames);
# echo 'before subqries: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
		foreach ($r as $k=>$article) {
//			echo "$opt_id, $r[$k]->id, 'est_sale'<br>";
			$r[$k]->est_sale = OPT::getPar($db, $dbr, $opt_id, $r[$k]->id, 'est_sale');
			$r[$k]->planned_orders = OPT::getPar($db, $dbr, $opt_id, $r[$k]->id, 'planned_orders');
			if ($cached) {
				$r[$k]->last_year_sale = OPT::getPar($db, $dbr, $opt_id, $r[$k]->id, 'last_year_sale');
				$r[$k]->effective_sold = OPT::getPar($db, $dbr, $opt_id, $r[$k]->id, 'effective_sold');
				$r[$k]->last_year_sold = OPT::getPar($db, $dbr, $opt_id, $r[$k]->id, 'last_year_sold');
				$r[$k]->last_year_avg = OPT::getPar($db, $dbr, $opt_id, $r[$k]->id, 'last_year_avg');
			}
			$r[$k]->ware = $dbr->getAssoc("select `month`.id
				, (select IFNULL((select sum(quantity) 
					from article_history 
						join article a1 $force_key on article_history.article_id=a1.article_id and a1.admin_id=0
					WHERE 1
					and $article_ids1 = '".$r[$k]->article_id."'
					and (article_history.warehouse_id in ($warehouses))
					and date(article_history.date)<'".$year."-01-01'),0))
				+(select IFNULL((SELECT count(*) as quantity
						FROM rma r
						JOIN rma_spec rs ON r.rma_id=rs.rma_id
						join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
						left JOIN warehouse w ON `default`
						left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
							left join (select 
								SUBSTRING_INDEX(GROUP_CONCAT(Updated order by updated desc),',',1)  updated
								, SUBSTRING_INDEX(GROUP_CONCAT(username order by updated desc),',',1)  username
								, TableID 
								from total_log where 1
								and Table_name='rma_spec'
								and Field_name='problem_id'
								and New_value in (4,11)
								group by TableID
							) total_log on 1 and TableID=rs.rma_spec_id
						WHERE rs.problem_id in (4,11) 
						and $article_ids1 = '".$r[$k]->article_id."'
						and (IFNULL(auw.warehouse_id, w.warehouse_id) in ($warehouses))
						and DATE(total_log.updated)<'".$year."-01-01'
					),0))
				-
					(select IFNULL((SELECT count(*)
						FROM rma r
						JOIN rma_spec rs ON r.rma_id=rs.rma_id
							join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
							LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
						WHERE rs.add_to_stock = 0 and rs.back_wrong_delivery=1 
						and $article_ids1 = '".$r[$k]->article_id."'
						and (rs.warehouse_id in ($warehouses))
						and DATE(IFNULL(rs.return_date, '0000-00-00'))<'".$year."-01-01'
					),0))
				+
					(select IFNULL((SELECT count(*)
						FROM rma r
						JOIN rma_spec rs ON r.rma_id=rs.rma_id
						join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
							LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
						WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
						and $article_ids1 = '".$r[$k]->article_id."'
						and (rs.warehouse_id in ($warehouses))
						and DATE(IFNULL((select date(max(tl.updated)) from total_log tl where tl.Table_name='rma_spec' and tl.Field_name='add_to_stock'
					and tl.New_value in ('1') and tl.tableid=rs.rma_spec_id), rs.return_date))<'".$year."-01-01'
					),0))
				+(select IFNULL((select sum(qnt_delivered) 
						from op_article 
						join article a1 $force_key on op_article.article_id=a1.article_id and a1.admin_id=0
							join op_order on op_order.id=op_article.op_order_id
							where 1
							and $article_ids1 = '".$r[$k]->article_id."'
							and add_to_warehouse
							and (op_article.warehouse_id in ($warehouses))
							and DATE(op_article.add_to_warehouse_date)<'".$year."-01-01'
					),0))
				-(select IFNULL(
						(select SUM(o.quantity)
						FROM orders o
						join article a1 $force_key on o.article_id=a1.article_id and a1.admin_id=o.manual
						JOIN auction au ON o.auction_number = au.auction_number AND o.txnid = au.txnid
						left join total_log tl on tl.tableid=o.id and table_name='orders' and field_name='sent' and New_value=1
						WHERE 1
						and $article_ids1 = '".$r[$k]->article_id."'
						and o.manual=0
						AND o.sent
						AND au.deleted = 0 
						and (IFNULL(o.send_warehouse_id, au.send_warehouse_id) in ($warehouses))
						and tl.updated<'".$year."-01-01'
						)
					,0)) f1
				from `month` where `month`.id=1");
			if ($r[$k]->cons == '_cons') {
				$q = "select `month`.id
					, IFNULL((select sum(qnt)
						from wwo_article wwa
						join article a1 $force_key on wwa.article_id=a1.article_id and a1.admin_id=0
						where 1
						and (
select MONTH(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)=`month`.id
						and (
select YEAR(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)='$year'
						and wwa.to_warehouse in ($warehouses) and wwa.to_warehouse<>0
						and not wwa.delivered
						and $article_ids1 = '".$r[$k]->article_id."'),0) qnt
					from `month`";
//				echo $q.'<br>';	
				$r[$k]->wwo = $dbr->getAssoc($q);
				$q = "select `month`.id
					, IFNULL((select GROUP_CONCAT(CONCAT('<a target=\"_blank\" href=\"ware2ware_order.php?id=',wwa.wwo_id,'\">WWO#',wwa.wwo_id,'</a>') separator '<br>')
						from wwo_article wwa
						join article a1  force key for join (cons_id)  on wwa.article_id=a1.article_id and a1.admin_id=0
						where 1
						and (
select MONTH(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)=`month`.id
						and (
select YEAR(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)='$year'
						and wwa.to_warehouse in ($warehouses) and wwa.to_warehouse<>0
						and not wwa.delivered
						and $article_ids1 = '".$r[$k]->article_id."'),0) qnt
					from `month`";
//				echo $q.'<br>';	
				$r[$k]->wwo_links = $dbr->getAssoc($q);
				$q = "select `month`.id
					, IFNULL((select sum(opa.qnt_ordered)
						from op_order_container 
						left join op_order_container master on master.id=op_order_container.master_id
						left join op_order_container opc on opc.id = IFNULL(master.id, op_order_container.id)
						join op_article opa on op_order_container.id=opa.container_id
						join article a1 $force_key on opa.article_id=a1.article_id and a1.admin_id=0
						where 1
						and MONTH(opc.eda)=`month`.id
						and YEAR(opc.eda)='$year'
						and opc.planned_warehouse_id in ($warehouses)
						and opa.add_to_warehouse=0
						and $article_ids1 = '".$r[$k]->article_id."'),0) qnt
					from `month`";
//			echo $q.'<br>';	
				$r[$k]->good_shipped = $dbr->getAssoc($q);
				$q = "select `month`.id
					, IFNULL((select sum(qnt_ordered)
						from op_article opa
						join article a1 $force_key on opa.article_id=a1.article_id and a1.admin_id=0
						join op_order_container opoc on opoc.id=opa.container_id
						where 1
						and MONTH(DATE_ADD(IFNULL(opoc.edd,opoc.ptd), INTERVAL $op_eta_ptd_diff DAY))=`month`.id
						and YEAR(DATE_ADD(IFNULL(opoc.edd,opoc.ptd), INTERVAL $op_eta_ptd_diff DAY))='$year'
						and opoc.planned_warehouse_id in ($warehouses)
						and (NOT opa.add_to_warehouse OR opa.add_to_warehouse IS NULL)
						and $article_ids1 = '".$r[$k]->article_id."'
						and opoc.eda is null
						),0) qnt
					from `month`";
	//			echo $q.'<br>';	
				$r[$k]->theo_arrived = $dbr->getAssoc($q);
			if (!$cached) {
				$q = "select `month`.id
					,ROUND(SUM(fget_Article_sold_date_period(article.article_id, ',$usernames,', ',$warehouses,', CONCAT('".($year-1)."-',`month`.id,'-01')
						, DATE_ADD(CONCAT('".($year-1)."-',`month`.id,'-01'),INTERVAL 1 month))
					) 
					/ DATEDIFF(DATE_ADD(CONCAT('".($year-1)."-',id,'-01'), INTERVAL 1 month),CONCAT('".($year-1)."-',id,'-01'))
					,2) as last_year_sale
					from `month`
					join article on admin_id=0 and cons_id=".$r[$k]->article_id."
					group by `month`.id
					";
#			echo $q.'<br>';	
				$r[$k]->last_year_sale = $dbr->getAssoc($q);
				$q = "select `month`.id
					,SUM(fget_Article_sold_date_period(article.article_id, ',$usernames,', ',$warehouses,', CONCAT('".($year)."-',`month`.id,'-01')
						, DATE_ADD(CONCAT('".($year)."-',`month`.id,'-01'),INTERVAL 1 month))
					) 
					/ DATEDIFF(DATE_ADD(CONCAT('$year-',id,'-01'), INTERVAL 1 month),CONCAT('$year-',id,'-01'))
					as effective_sold
					from `month`
					join article on admin_id=0 and cons_id=".$r[$k]->article_id."
					group by `month`.id
					";
#			echo $q.'<br>';	
				$r[$k]->effective_sold = $dbr->getAssoc($q);
				$q = "select sum(fget_Article_sold_date_period(article_id, ',$usernames,', ',$warehouses,', '".($year-1)."-01-01'
						, '".($year-1)."-12-31')) as last_year_sold
						from article where admin_id=0 and cons_id=".$r[$k]->article_id."
					";
#			echo $q.'<br>';	
				$r[$k]->last_year_sold[0] = $dbr->getOne($q);
				foreach($usernames_array as $username) {
					$q = "select `month`.id
						,ROUND(SUM(fget_Article_sold_date_period(article.article_id, ',$username,', ',$warehouses,', CONCAT('".($year-1)."-',`month`.id,'-01')
							, DATE_ADD(CONCAT('".($year-1)."-',`month`.id,'-01'),INTERVAL 1 month))
						) 
						/ DATEDIFF(DATE_ADD(CONCAT('".($year-1)."-',id,'-01'), INTERVAL 1 month),CONCAT('".($year-1)."-',id,'-01'))
						,2) as last_year_avg
						from `month`
						join article on admin_id=0 and cons_id=".$r[$k]->article_id."
						group by `month`.id
						";
	#			echo $q.'<br>';	
					$r[$k]->last_year_avg[$username] = $dbr->getAssoc($q);
				}
			}
				$q = "select username, ROUND(SUM(
					fget_Article_sold_date_period(article.article_id, CONCAT(',',username,','), ',$warehouses,'
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)),INTERVAL 1 DAY)
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					)) / DATEDIFF(DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					,DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)),INTERVAL 1 DAY))
					, 2) as last2monthsavg
					from article 
					join seller_information on username in ('$usernames1')
					where admin_id=0 and cons_id=".$r[$k]->article_id."
					group by username
					";
#			echo $q.'<br>';	
				$r[$k]->last2monthsavg = $dbr->getAssoc($q);
				$q = "select username, ROUND(SUM(
					fget_Article_sold_date_period(article.article_id, CONCAT(',',username,','), ',$warehouses,'
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 7 MONTH)),INTERVAL 1 DAY)
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					)) / DATEDIFF(DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					,DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 7 MONTH)),INTERVAL 1 DAY))
					, 2) as last6monthsavg
					from article 
					join seller_information on username in ('$usernames1')
					where admin_id=0 and cons_id=".$r[$k]->article_id."
					group by username
					";
#			echo $q.'<br>';	
				$r[$k]->last6monthsavg = $dbr->getAssoc($q);
				$q = "select sum(fget_Article_sold_date_period(article_id, ',$usernames,', ',$warehouses,', '".($year)."-01-01'
						, '".($year)."-12-31')) as this_year_sold
						from article where admin_id=0 and cons_id=".$r[$k]->article_id."
					";
#			echo $q.'<br>';	
				$r[$k]->this_year_sold = $dbr->getOne($q);
			} else {
				$q = "select `month`.id
					, IFNULL((select sum(qnt)
						from wwo_article wwa
						join article a1 on wwa.article_id=a1.article_id and a1.admin_id=0
						where 1
						and (
select MONTH(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)=`month`.id
						and (
select YEAR(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)='$year'
						and wwa.to_warehouse in ($warehouses) and wwa.to_warehouse<>0
						and not wwa.delivered
						and $article_ids1 = '".$r[$k]->article_id."'),0) qnt
					from `month`";
//				echo $q.'<br>';	
				$r[$k]->wwo = $dbr->getAssoc($q);
# echo 'wwo: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select `month`.id
					, IFNULL((select GROUP_CONCAT(CONCAT('<a target=\"_blank\" href=\"ware2ware_order.php?id=',wwa.wwo_id,'\">WWO#',wwa.wwo_id,'</a>') separator '<br>')
						from wwo_article wwa
						join article a1  force key for join (cons_id)  on wwa.article_id=a1.article_id and a1.admin_id=0
						where 1
						and (
select MONTH(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)=`month`.id
						and (
select YEAR(DATE_ADD(max(Updated), interval ".$config['op_eta_wwo_diff']." DAY)) from total_log tl where tl.TableID=wwa.id 
and Table_name='wwo_article' and Field_name='article_id' #'taken' and New_value=1
)='$year'
						and wwa.to_warehouse in ($warehouses) and wwa.to_warehouse<>0
						and not wwa.delivered
						and $article_ids1 = '".$r[$k]->article_id."'),0) qnt
					from `month`";
//				echo $q.'<br>';	
				$r[$k]->wwo_links = $dbr->getAssoc($q);
# echo 'wwo_links: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select `month`.id
					, IFNULL((select sum(opa.qnt_ordered)
						from op_order_container 
						left join op_order_container master on master.id=op_order_container.master_id
						left join op_order_container opc on opc.id = IFNULL(master.id, op_order_container.id)
						join op_article opa on op_order_container.id=opa.container_id
						join article a1 on opa.article_id=a1.article_id and a1.admin_id=0
						where 1
						and MONTH(opc.eda)=`month`.id
						and YEAR(opc.eda)='$year'
						and opc.planned_warehouse_id in ($warehouses)
						and opa.add_to_warehouse=0
						and $article_ids1 = '".$r[$k]->article_id."'),0) qnt
					from `month`";
	//			echo $q.'<br>';	
				$r[$k]->good_shipped = $dbr->getAssoc($q);
# echo 'good_shipped: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select `month`.id
					, IFNULL((select sum(qnt_ordered)
						from op_article opa
						join article a1 on opa.article_id=a1.article_id and a1.admin_id=0
						join op_order_container opoc on opoc.id=opa.container_id
						where 1
						and MONTH(DATE_ADD(IFNULL(opoc.edd,opoc.ptd), INTERVAL $op_eta_ptd_diff DAY))=`month`.id
						and YEAR(DATE_ADD(IFNULL(opoc.edd,opoc.ptd), INTERVAL $op_eta_ptd_diff DAY))='$year'
						and opoc.planned_warehouse_id in ($warehouses)
						and (NOT opa.add_to_warehouse OR opa.add_to_warehouse IS NULL)
						and $article_ids1 = '".$r[$k]->article_id."'
						and opoc.eda is null
						),0) qnt
					from `month`";
	//			echo $q.'<br>';	
				$r[$k]->theo_arrived = $dbr->getAssoc($q);
# echo 'theo_arrived: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
			if (!$cached) {
				$q = "select `month`.id
					,ROUND(fget_Article_sold_date_period('".$r[$k]->article_id."', ',$usernames,', ',$warehouses,', CONCAT('".($year-1)."-',`month`.id,'-01')
						, DATE_ADD(CONCAT('".($year-1)."-',`month`.id,'-01'),INTERVAL 1 month))
					/ DATEDIFF(DATE_ADD(CONCAT('".($year-1)."-',id,'-01'), INTERVAL 1 month),CONCAT('".($year-1)."-',id,'-01'))
					,2) as last_year_sale
					from `month`";
//			echo $q.'<br>';	
				$r[$k]->last_year_sale = $dbr->getAssoc($q);
# echo 'last_year_sale: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select `month`.id
					,fget_Article_sold_date_period('".$r[$k]->article_id."', ',$usernames,', ',$warehouses,', CONCAT('".($year)."-',`month`.id,'-01')
						, DATE_ADD(CONCAT('".($year)."-',`month`.id,'-01'),INTERVAL 1 month))
					/ DATEDIFF(DATE_ADD(CONCAT('$year-',id,'-01'), INTERVAL 1 month),CONCAT('$year-',id,'-01'))
					as effective_sold
					from `month`";
#			echo $q.'<br>';	
				$r[$k]->effective_sold = $dbr->getAssoc($q);
# echo 'effective_sold: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select fget_Article_sold_date_period('".$r[$k]->article_id."', ',$usernames,', ',$warehouses,', '".($year-1)."-01-01'
						, '".($year-1)."-12-31') as last_year_sold
					";
#			echo $q.'<br>';	
				$r[$k]->last_year_sold[0] = $dbr->getOne($q);
# echo 'last_year_sold: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				foreach($usernames_array as $username) {
				$q = "select `month`.id
					,ROUND(fget_Article_sold_date_period('".$r[$k]->article_id."', ',$username,', ',$warehouses,', CONCAT('".($year-1)."-',`month`.id,'-01')
						, DATE_ADD(CONCAT('".($year-1)."-',`month`.id,'-01'),INTERVAL 1 month))
					/ DATEDIFF(DATE_ADD(CONCAT('".($year-1)."-',id,'-01'), INTERVAL 1 month),CONCAT('".($year-1)."-',id,'-01'))
					,2) as last_year_avg
					from `month`";
	#			echo $q.'<br>';	
					$r[$k]->last_year_avg[$username] = $dbr->getAssoc($q);
				}
			}
				$q = "select username, ROUND(
					fget_Article_sold_date_period('".$r[$k]->article_id."', CONCAT(',',username,','), ',$warehouses,'
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)),INTERVAL 1 DAY)
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					) / DATEDIFF(DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					,DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)),INTERVAL 1 DAY))
					, 2) as last2monthsavg
					from seller_information 
					where username in ('$usernames1')
					";
//			echo $q.'<br>';	
				$r[$k]->last2monthsavg = $dbr->getAssoc($q);
# echo 'last2monthsavg: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select username, ROUND(
					fget_Article_sold_date_period('".$r[$k]->article_id."', CONCAT(',',username,','), ',$warehouses,'
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 7 MONTH)),INTERVAL 1 DAY)
					, DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					) / DATEDIFF(DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH)),INTERVAL 1 DAY)
					,DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 7 MONTH)),INTERVAL 1 DAY))
					, 2) as last6monthsavg
					from seller_information 
					where username in ('$usernames1')
					";
//			echo $q.'<br>';	
				$r[$k]->last6monthsavg = $dbr->getAssoc($q);
# echo 'last6monthsavg: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
				$q = "select fget_Article_sold_date_period('".$r[$k]->article_id."', ',$usernames,', ',$warehouses,', '".($year)."-01-01'
						, '".($year)."-12-31') as this_year_sold
					";
#			echo $q.'<br>';	
				$r[$k]->this_year_sold = $dbr->getOne($q);
# echo 'this_year_sold: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
			}
		}
# echo 'after subqries: '.number_format(getmicrotime()-$time,3).'<br>';$time = getmicrotime();
//		print_r($r);
        return $r;
    }

}
?>