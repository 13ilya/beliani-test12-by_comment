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
class op_Order
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
    function op_Order($db, $dbr, $id = 0)
    {
        global $debug;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Rma::Rma expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN op_order");
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
            $this->payments = array();
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM op_order WHERE id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("op_Order : record $id does not exist");
                return;
            }
if ($debug) echo '1.1: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
            $this->articles = op_Order::getArticles($db, $dbr, $id);
if ($debug) echo '1.2: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
            $this->payments = op_Order::getPayments($db, $dbr, $id);
if ($debug) echo '1.3: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
            $this->containers = op_Order::getContainers($db, $dbr, $id);
if ($debug) echo '1.4: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
            $this->containers_array = op_Order::getContainersArray($db, $dbr, $id);
if ($debug) echo '1.5: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
			$this->comments = op_Order::getComments($db, $dbr, $id);
if ($debug) echo '1.6: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
			$this->docs = op_Order::getDocs($db, $dbr, $id);
if ($debug) echo '1.7: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
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
            $this->_error = PEAR::raiseError('Rma::update : no data');
        }
        foreach ($this->data as $field => $value) {
			{
	            if ($query) {
	                $query .= ', ';
	            }
	            if ($value!='' && $value!=NULL)
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
		$q = "$command op_order SET $query $where";
        $r = $this->_db->query($q);
        /*if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
        }*/
        if ($this->_isNew && !$this->data->id) {
            $this->data->id = mysql_insert_id();
			if (!$this->data->id) $this->data->id = $this->_db->getOne("select max(id) from op_order");
			$this->_isNew = false;
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
		$obj_ids = $this->_dbr->getOne("SELECT GROUP_CONCAT(id) FROM op_article WHERE op_order_id=$id");
		$this->_db->query("DELETE FROM barcode_object WHERE obj = 'op_article' and obj_id IN ($obj_ids)");
		$this->_db->query("DELETE FROM op_article WHERE op_order_id=$id");
		$this->_db->query("DELETE FROM op_payment WHERE op_order_id=$id");
        $r = $this->_db->query("DELETE FROM op_order WHERE id=$id");
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
    static function listAll($db, $dbr, $mode, $supplier_id=0, $cons=0
		, $arvl_from='', $arvl_to=''
        , $eda_from='', $eda_to=''
		, $edd_from='', $edd_to=''
		, $ptd_from='', $ptd_to=''
		, $wwo='', $sub='', 
		$oldeda=0, $exclude_anyaarived=0, $exclude_allaarived=0,
		$dest_country_code='', $cont_status='', $trucking_company_id=0, $arrival_ware='', $date=''
		, $cont_statuses_from='', $cont_statuses_to='', $has_container_no='', $shipping_company_id=0, $shipping_line='')
    {
		global $supplier_filter_str;
		$where = ' ';
		$where .= $mode=='all' ? '' : 
		 ($mode=='closed' ? ' AND close_date is not null' : ' AND  close_date is null');
/*(SELECT sum( opa.qnt_ordered ) - sum( opa.qnt_delivered ) 
				FROM op_order opo
				JOIN op_article opa ON opo.id = opa.op_order_ids
				WHERE opo.id = op_order.id)>0 and */		 
		$having = 'having 1';
		if ($trucking_company_id) {
			if ($trucking_company_id==-1) {
				$where_cont .= " and IFNULL(opc.trucking_company_id,0) ";
				$where .= " and IFNULL(opc.trucking_company_id,0) ";
			} elseif ($trucking_company_id==-2) {
				$where_cont .= " and IFNULL(opc.trucking_company_id,0)=0 ";
				$where .= " and IFNULL(opc.trucking_company_id,0)=0 ";
			} else {
				$where_cont .= " and IFNULL(opc.trucking_company_id,$trucking_company_id) = $trucking_company_id ";
				$where .= " and IFNULL(opc.trucking_company_id,$trucking_company_id) = $trucking_company_id ";
			}
		}
		if ($exclude_anyaarived) {
			$having .= " and NOT MAX(IFNULL(opc.arrival_date,'0000-00-00'))>'0000-00-00'";
			$where_cont .= " and IFNULL(opc.arrival_date,'0000-00-00'))='0000-00-00' ";
			$where .= " and IFNULL(opc.arrival_date,'0000-00-00')='0000-00-00'";
		}
		if (strlen($has_container_no)) {
			$container_no = mysql_escape_string($container_no);
			$where_cont .= " and length(replace(opc.container_no,' ',''))".($has_container_no?'=':'<>')."11 ";
			$where .= " and length(replace(opc.container_no,' ',''))".($has_container_no?'=':'<>')."11 ";
		}
		if ($shipping_company_id) {
			$where_cont .= " and opc.shipping_company_id=$shipping_company_id ";
			$where .= " and opc.shipping_company_id=$shipping_company_id ";
		}
		if (strlen($shipping_line)) {
			$where_cont .= " and opc.shipping_line='$shipping_line' ";
			$where .= " and opc.shipping_line='$shipping_line' ";
		}
		if ($exclude_allaarived) {
			$having .= " and NOT MIN(IFNULL(opc.arrival_date,'0000-00-00'))>'0000-00-00'";
			$where_cont .= " and IFNULL(opc.arrival_date,'0000-00-00'))='0000-00-00' ";
			$where .= " and IFNULL(opc.arrival_date,'0000-00-00')='0000-00-00'";
		}
		if ((int)$arrival_ware) {
			$where_cont .= " and IFNULL(opc.planned_warehouse_id,0)=".(int)$arrival_ware;
			$where .= " and IFNULL(opc.planned_warehouse_id,0)=".(int)$arrival_ware;
		}
		if ($oldeda) {
			$where_or .= " or (opc.eda < NOW() and IFNULL(opc.arrival_date,'0000-00-00')='0000-00-00')";
		}
        if (strlen($arvl_from)) {
            $where .= " and opc.arrival_date >= '$arvl_from'";
        }
        if (strlen($arvl_to)) {
            $where .= " and opc.arrival_date <= '$arvl_to'";
        }
		if (strlen($eda_from)) {
			$where .= " and opc.eda >= '$eda_from'";
		}
		if (strlen($eda_to)) {
			$where .= " and opc.eda <= '$eda_to'";
		}
		if (strlen($edd_from)) {
			$where .= " and opc.edd >= '$edd_from'";
		}
		if (strlen($edd_to)) {
			$where .= " and opc.edd <= '$edd_to'";
		}
		if (strlen($ptd_from)) {
			$where .= " and opc.ptd >= '$ptd_from'";
		}
		if (strlen($ptd_to)) {
			$where .= " and opc.ptd <= '$ptd_to'";
		}
		if (!strlen($eda_from) && strlen($eda_to)) {
			$where .= " and IFNULL(opc.arrival_date,'0000-00-00')='0000-00-00'";
		}
		if ($supplier_id) {
			$where .= ' and op_company.id='.$supplier_id;
		}
		if (strlen($wwo)) {
			$where .= " and ".($wwo?'':'NOT')." exists (select null from ww_order where container_id=opc.id)";
		}
		if (strlen($sub)) {
			$where .= " and ".($sub?'':'NOT')." op_order_container.master_id";
		}
		if (strlen($dest_country_code)) {
//			$where .= " and exists (select null from op_order_container where dest_country_code='$dest_country_code'
//				and order_id=op_order.id)";
			$where .= " and c.code='$dest_country_code'";
		}
		if (strlen($cont_status)) {
			if (strlen($cont_statuses_from) || strlen($cont_statuses_to)) {
				if (!strlen($cont_statuses_from)) $cont_statuses_from='0000-00-00';
				if (!strlen($cont_statuses_to)) $cont_statuses_to='2999-01-01';
				$where .= " and exists (select null from total_log
					where table_name='op_order_container' and field_name='status_id'
					and new_value=$cont_status and tableid=opc.id 
					and date(updated) between '$cont_statuses_from' and '$cont_statuses_to')";
				$where_cont .= " and exists (select null from total_log
					where table_name='op_order_container' and field_name='status_id'
					and new_value=$cont_status and tableid=opc.id 
					and date(updated) between '$cont_statuses_from' and '$cont_statuses_to')";
			} else {
				$where .= " and opc.status_id=$cont_status";
				$where_cont .= " and status_id=$cont_status ";
			}
		}
		if ($cons) {
			$where .= " and exists (select null from op_article 
				join article on op_article.article_id=article.article_id and article.admin_id=0
				where op_article.op_order_id=op_order.id and IFNULL(article.cons_id,0)=$cons)";
		}
		if ($date) {
			$dateflds = "		
		, (select sum(qnt_delivered*purchase_price) 
			from op_article
			where op_order_id=op_order.id
				and add_to_warehouse
				and DATE(add_to_warehouse_date)<='$date') valuearriveddate
	  , (select sum( opp.amount  * ( 
	   	CASE opp.currency
		WHEN 'USD'
		THEN 1 
		WHEN 'EUR'
		THEN op_order.rateEURUS
		WHEN 'GBP'
		THEN op_order.rateGBPUS
		WHEN 'CHF'
		THEN op_order.rateCHFUS
		WHEN 'PLN'
		THEN op_order.ratePLNUS
		ELSE 0 
		END ) 
		) from op_payment opp where opp.op_order_id = op_order.id
		and DATE(opp.pay_date)<='$date') AS totalUSDpaiddate
		";
		}
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT op_order.*, 
	  (select sum( opp.amount  * ( 
	   	CASE opp.currency
		WHEN 'USD'
		THEN 1 
		WHEN 'EUR'
		THEN op_order.rateEURUS
		WHEN 'GBP'
		THEN op_order.rateGBPUS
		WHEN 'CHF'
		THEN op_order.rateCHFUS
		WHEN 'PLN'
		THEN op_order.ratePLNUS
		ELSE 0 
		END ) 
		) from op_payment opp where opp.op_order_id = op_order.id) AS totalUSDpaid
	   ,op_company.name as company_name ,
			GROUP_CONCAT(opc.id) container_ids,
			min(opc.EDA) mineda,
			min(opc.EDD) minedd,
			min(opc.PTD) minptd,
			min(opc.container_no) mincontainer_no,
			min(opc.arrival_date) minarrival_date,
			min(opc.bl_arrived_date) minbl_arrived_date,
			min(t.value) mincountry
		, (select sum(qnt_delivered*purchase_price) 
			from op_article
			where op_order_id=op_order.id
				and add_to_warehouse) valuearrived
		, (select sum(qnt_ordered*purchase_price) 
			from op_article
			where op_order_id=op_order.id) totalitemcost
		$dateflds
		FROM op_order 
		JOIN op_company ON op_order.company_id=op_company.id
		LEFT JOIN op_order_container ON op_order_container.order_id = op_order.id 
		left join op_order_container master on master.id=op_order_container.master_id
		left join op_order_container opc on IFNULL(master.id, op_order_container.id) = opc.id
		LEFT JOIN op_container_status ocs ON ocs.id = opc.status_id 
		LEFT JOIN op_demurrage od ON od.id = opc.demurrage
		LEFT JOIN warehouse wc ON opc.planned_warehouse_id = wc.warehouse_id
		left join country c on c.code=wc.country_code
		left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
		WHERE ((1=1 $where) $where_or)
		$supplier_filter_str
		GROUP BY op_order.id
		$having";
		if ($_GET['debug']) echo $q; //return; //die();
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
        $list = array();
		global $smarty;
		$cont_statuses = $dbr->getAssoc("select id, name from op_container_status");
		$smarty->assign('cont_statuses', $cont_statuses);
		$smarty->assign('warehouses', Warehouse::listArray($db, $dbr));
		$companiesShipping = op_Order::listCompaniesArray($db, $dbr, 'shipping');
		$smarty->assign('companiesShipping', $companiesShipping);
		$destination_terminals = $dbr->getAssoc("select id, name from op_destination_terminal");
		$smarty->assign('destination_terminals', $destination_terminals);
        while ($row = $r->fetchRow()) {
			if (strlen($row->container_ids)) {
				$q = "select max(op_order_container.master_id) real_master_id, max(master.order_id) master_order_id,
					opc.*
					, c.code dest_country_code
					, ocs.name status_name
					, t.value dest_country_name
					, od.name demurrage_name
					, wc.name planned_warehouse
					, DATE_FORMAT(opc.delivery_time, '%H:%i') delivery_time_hm
					, DATE_FORMAT(opc.pickup_time, '%H:%i') pickup_time_hm
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',seller_information.email,'%')
					order by el.`date` desc limit 1) importer_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_return_terminal.email,'%')
					order by el.`date` desc limit 1) return_terminal_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_destination_terminal.email,'%')
					order by el.`date` desc limit 1) destination_terminal_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_company.email,'%')
					order by el.`date` desc limit 1) trucking_company_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_address_resp.email,'%')
					order by el.`date` desc limit 1) resp_address_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',fget_WarehouseEmail(wc.warehouse_id),'%')
					order by el.`date` desc limit 1) planned_warehouse_last_sent
					, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='planned_warehouse_id'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) planned_warehouse_last_change
					, CONCAT(seller_information.username, ' = ', seller_information.seller_name) importer_name
					, opc1.name shipping_company_name
					, op_destination_terminal.name destination_terminal_name
					, op_destination_local_terminal.name destination_local_terminal_name
					, op_container.name container_name
					, op_content.content op_content
					, op_address_resp.name resp_address_name
					, users.name counted_by_name
					, op_company.name trucking_company_name
					, op_return_terminal.name return_terminal_name
					, op_return_local_terminal.name return_local_terminal_name
					, (select CONCAT(' changed from ', IFNULL(opcs.name, 'NONE'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))# order by tl.updated desc SEPARATOR '<br>')
						from total_log tl
						left join users u on u.system_username=tl.username
						left join op_container_status opcs on tl.old_value=opcs.id
						where tl.table_name='op_order_container' and tl.field_name='status_id'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) status_id_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))# order by tl.updated desc SEPARATOR '<br>')
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='ptd'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) ptd_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))# order by tl.updated desc SEPARATOR '<br>')
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='eda'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) eda_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))# order by tl.updated desc SEPARATOR '<br>')
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='bl_number'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) bl_number_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))# order by tl.updated desc SEPARATOR '<br>')
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='comment_transport'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) comment_transport_last_change
					, concat(op_driver.first_name, ' ', op_driver.last_name) driver
			, (select group_concat(concat('<a target=\"_blank\" href=\"ware2ware_order.php?id=',ww_order.id,'\">  WWO#', ww_order.id, '</a>') separator '<br>') from ww_order where ww_order.container_id=op_order_container.id) wwos
			, op_order_container.order_id original_order_id
			, op_order_container.id original_id
				from op_order_container
					left join op_order_container master on master.id=op_order_container.master_id
					left join op_order_container opc on IFNULL(master.id, op_order_container.id) = opc.id
					LEFT JOIN op_container_status ocs ON ocs.id = opc.status_id 
					left join op_content on op_content.id=opc.op_content_id
					LEFT JOIN op_demurrage od ON od.id = opc.demurrage
					LEFT JOIN warehouse wc ON opc.planned_warehouse_id = wc.warehouse_id
					left join country c on c.code=wc.country_code
					left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
					left join op_address_resp on op_address_resp.id=opc.resp_address_id
					left join seller_information on seller_information.username=opc.importer_id
					left join op_company on op_company.id=opc.trucking_company_id
					left join op_company opc1 on opc1.id=opc.shipping_company_id
					left join op_destination_terminal on op_destination_terminal.id=opc.destination_terminal_id
					left join op_destination_terminal op_destination_local_terminal on op_destination_local_terminal.id=opc.destination_local_terminal_id
					left join op_destination_terminal op_return_terminal on op_return_terminal.id=opc.return_terminal_id
					left join op_destination_terminal op_return_local_terminal on op_return_local_terminal.id=opc.return_local_terminal_id
					left join op_container on op_container.id=opc.container
					left join users on users.username = opc.counted_by
					left join op_driver on op_driver.id=opc.driver_id
				where opc.id in ({$row->container_ids}) and op_order_container.order_id={$row->id}
				group by opc.id
					ORDER BY id";
		        $containers = $dbr->getAll($q);
//				if ($row->id==977) die("<br>$q");
				foreach($containers as $k=>$container) {
					$containers[$k]->trucks = $dbr->getAll("select opct.*
					, DATE_FORMAT(opct.delivery_time, '%H:%i') delivery_time_hm
					, DATE_FORMAT(opct.pickup_time, '%H:%i') pickup_time_hm
		/*					, (select DATE(el.`date`)
							from email_log el
							where el.template='op_container_delivery_document' and el.notes*1=opc.id
							and el.auction_number=opc.order_id and el.txnid=-1
							and el.recipient like CONCAT('%',op_return_terminal.email,'%')
							order by el.`date` desc limit 1) return_terminal_last_sent
							, (select DATE(el.`date`)
							from email_log el
							where el.template='op_container_delivery_document' and el.notes*1=opc.id
							and el.auction_number=opc.order_id and el.txnid=-1
							and el.recipient like CONCAT('%',op_destination_terminal.email,'%')
							order by el.`date` desc limit 1) destination_terminal_last_sent*/
							, op_destination_terminal.name destination_terminal_name
							, op_return_terminal.name return_terminal_name
							, concat(op_driver.first_name, ' ', op_driver.last_name) driver
						from op_order_container_truck opct
							left join op_destination_terminal on op_destination_terminal.id=opct.destination_terminal_id
							left join op_destination_terminal op_return_terminal on op_return_terminal.id=opct.return_terminal_id
							left join op_driver on op_driver.id=opct.driver_id
						where opct.order_container_id = {$container->id}
					");
					$q = "select count(*) from (
						select pay_date, opc.EDD, 
						(TO_DAYS(NOW()) - TO_DAYS(pay_date)) dif
						from (select min(pay_date) pay_date, op_order_id  
						from op_payment where op_order_id=".$order->id." group by op_order_id) opp
						join op_order_container opc on opp.op_order_id=opc.order_id
						where opc.EDD is null and opc.id=".$container->id." 
						) t where dif>".Config::get($db, $dbr, 'op_no_edd');
					$first_payment = $dbr->getOne($q);
			//	echo " first_payment=$first_payment";
					if ($first_payment) $containers[$k]->bgcolor=Config::get($db, $dbr, 'op_no_edd_color');
					$EDArest = $dbr->getOne("
						select count(*) from (
						select opc.bl_arrived_date, opc.EDA, 
						-(TO_DAYS(NOW()) - TO_DAYS(IFNULL(opc.EDA,NOW()))) dif
						from op_order_container opc 
						where opc.bl_arrived_date is null and opc.EDA is not null and opc.id=".$container->id." 
						) t where dif<".Config::get($db, $dbr, 'op_no_bl'));
			//	echo " EDArest=$EDArest";
					if ($EDArest) $containers[$k]->bgcolor=Config::get($db, $dbr, 'op_no_bl_color');
					if ($container->edd) $containers[$k]->bgcolor='#eeeeee';
				}
				$smarty->assign('containers', $containers);
				$row->containers_array = $containers;
				$row->containers = $smarty->fetch('_op_containers.tpl');
				$row->containers_print = $smarty->fetch('_op_containers_print.tpl');
			}
            $list[] = $row;
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
    static function listArray($db, $dbr, $id)
    {
        $ret = array();
        $list = op_Order::listAll($db, $dbr, $id);
        foreach ((array)$list as $row) {
            $ret[$row->id] = $row->number;
        }
        return $ret;
    }

    static function addCompany($db, $dbr, $name, $public_name, $oscom_pic_URL, $oscom_URL, $person, $email, $phone, $container, $period, $shipping=NULL, $comment)
    {
		$name = mysql_escape_string($name);
		$public_name = mysql_escape_string($public_name);
		$person = mysql_escape_string($person);
		$email = mysql_escape_string($email);
		$phone = mysql_escape_string($phone); 
		$container = mysql_escape_string($container); 
		$comment = mysql_escape_string($comment); 
		$period = (int)mysql_escape_string($period); 
		$oscom_pic_URL = mysql_escape_string($oscom_pic_URL);
		$oscom_URL = mysql_escape_string($oscom_URL);
		$shipping = !strlen(mysql_escape_string($shipping)) ? 'NULL' : "'".mysql_escape_string($shipping)."'"; 
        $r = $db->query("INSERT INTO op_company SET 
		name='$name', 
		public_name='$public_name',
		person = '$person',
		email = '$email',
		phone = '$phone',
		container_id = '$container',
		comment = '$comment',
		oscom_pic_URL='$oscom_pic_URL',
		oscom_URL='$oscom_URL',
		period = $period,
		type = $shipping
		"); 
        if (PEAR::isError($r)) { aprint_r($r); die();}
//		shipping_cost_per_volume = '$sh_cost',
//		transport_cost_per_volume = '$tr_cost',
		$r = $dbr->getOne("SELECT max(id) FROM op_company");

		return $r;
    }

    static function listCompaniesArray($db, $dbr, $shipping=NULL, $active=' and active=1 ')
    {
		global $supplier_filter;
		global $supplier_filter_str;
		if (strlen($supplier_filter) && !$shipping)
			$supplier_filter_str1 = " and id in ($supplier_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
		$where='is null';
		if ($shipping=='shipping') 
			$where="='shipping'";
		$q = "SELECT * FROM op_company WHERE old=0 and type ".$where.$active.$supplier_filter_str1." order by name";
//		echo $q;	
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        while ($row = $r->fetchRow()) {
			if ($shipping=='person') {
	            if (strlen($row->person)) $ret[$row->id] = $row->person; 
			} else $ret[$row->id] = $row->name;
        }
        return $ret;
    }

	static function dupContainer($db, $dbr, $container_id) {
        $fields = $dbr->getAll("EXPLAIN op_order_container");
		$fields_array = array();
		foreach ($fields as $field) {
			if ($field->Field != 'id') $fields_array[] = $field->Field;
		}
		$r = $db->query("insert into op_order_container (`".implode('`,`', $fields_array)."`)
			select `".implode('`,`', $fields_array)."` 
			from op_order_container where id=$container_id");
        if (PEAR::isError($r)) {
			aprint_r($r);
            die();
        }
		$new_container_id = mysql_insert_id();
        $fields = $dbr->getAll("EXPLAIN op_article");
		$fields_array = array();
		foreach ($fields as $field) {
			if ($field->Field != 'id' && $field->Field != 'container_id') $fields_array[] = $field->Field;
		}
		$r = $db->query("insert into op_article (`".implode('`,`', $fields_array)."`, `container_id`)
			select `".implode('`,`', $fields_array)."`, $new_container_id from op_article where container_id=$container_id");
        if (PEAR::isError($r)) {
			aprint_r($r);
            die();
        }
	}

    static function listCompaniesAll($db, $dbr, $shipping=NULL, $mode='all', $modeall='not', $withdocs = 1)
    {
		global $debug; global $time;
		global $supplier_filter;
		global $supplier_filter_str;
		if (strlen($supplier_filter) && !$shipping)
			$supplier_filter_str1 = " and op_company.id in ($supplier_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
		if ($shipping==NULL) $where=' AND op_company.type is null ';
			elseif ($shipping=='shipping') $where=" AND op_company.type='shipping' ";
			elseif ($shipping=='all') $where="";
		if ($mode=='active') $where .= ' AND active ';
			elseif ($mode=='passive') $where=" AND not active ";
		$where .= " AND $modeall IFNULL(old, 0) ";
		$q = "SELECT op_company.*, op_container.name container_name, op_container.volume container_volume
				,op_port.name loading_port, op_comp_emp.emp_id emps, op_comp_assist.emp_id assists
				, IF(op_company.name like 'Consolidate%', 1, 0) cons
				, CONCAT(employee.name, ' ', employee.name2) emp
			FROM op_company 
			LEFT JOIN op_container ON op_company.container_id=op_container.id 
			LEFT JOIN op_port ON op_company.loading_port_id=op_port.id 
			LEFT JOIN op_company_emp op_comp_emp ON op_comp_emp.company_id = op_company.id AND op_comp_emp.type='purch'
			LEFT JOIN op_company_emp op_comp_assist ON op_comp_assist.company_id = op_company.id AND op_comp_assist.type='assist'
			LEFT JOIN employee ON op_comp_emp.emp_id=employee.id
			WHERE 1=1 $supplier_filter_str1
			".$where." order by op_company.type, name";
//		echo $q;
        $r = $db->query($q);
if ($debug) echo '6-1: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        while ($row = $r->fetchRow()) {
            if(!isset($list[$row->id])) {
                $list[$row->id] = $row;
                $list[$row->id]->articles = $dbr->getAll("SELECT CONCAT(a.article_id, ': ', t1.value) as article_name, a.article_id
            FROM article a
            join translation t1 on t1.table_name='article' and t1.field_name='name' and t1.id=a.article_id and t1.language='german'
            where a.company_id=".$row->id);
    if ($debug) echo '6-2: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
                /*if ($withdocs)*/ $list[$row->id]->docs = op_Order::getCompanyDocs($db, $dbr, $row->id);
    if ($debug) echo '6-3: '.(getmicrotime()-$time).'<br>';$time = getmicrotime();
                $list[$row->id]->artcount = count($list[$row->id]->articles);
            }

            $list[$row->id]->employees[$row->emps]     = $row->emps;
            $list[$row->id]->assistants[$row->assists] = $row->assists;
        }
        return $list;
    }

    static function listDestinationsArray($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
        $r = $db->query("SELECT * FROM op_destination order by name");
        if (PEAR::isError($r)) {
            return;
        }
        while ($row = $r->fetchRow()) {
            $ret[$row->id] = $row->name;
        }
        return $ret;
    }

    static function listCategoriesArray($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
        $r = $db->query("SELECT * FROM op_company_category order by name");
        if (PEAR::isError($r)) {
            return;
        }
        while ($row = $r->fetchRow()) {
            $ret[$row->id] = $row->name;
        }
        return $ret;
    }

    static function listContainerAll($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT * FROM op_container order by name");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($row = $r->fetchRow()) {
            $list[] = $row;
        }
        return $list;
    }

    static function listContainerArray($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $ret = array();
        $r = $db->query("SELECT * FROM op_container order by name");
        if (PEAR::isError($r)) {
            return;
        }
        while ($row = $r->fetchRow()) {
            $ret[$row->id] = $row->name;
        }
        return $ret;
    }

    static function listCategoriesAllCompany($db, $dbr, $company_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT a.category_id as category_id, occ.name as name
		FROM article a 
		JOIN op_company_category occ ON a.category_id=occ.id
		where a.company_id=$company_id");
        $list = array();
        while ($row = $r->fetchRow()) {
            $list[] = $row;
        }
        return $list;
    }

    static function listCategoriesAll($db, $dbr)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT occ.*
		FROM op_company_category occ");
        $list = array();
        while ($row = $r->fetchRow()) {
			$row->suppliers=$dbr->getAll("SELECT oc.company_id, oc.name as company_name
		FROM article a 
		JOIN op_company oc ON a.company_id=oc.id
		where a.category_id=".$row->id);
			$row->articles=$dbr->getAll("SELECT CONCAT(a.article_id, ': ', a.name) as article_name, a.article_id
		FROM article a where a.category_id=".$row->id);
			$row->artcount = count($row->articles);
            $list[] = $row;
        }
        return $list;
    }

    /**
    * @return bool
    * @param array $errors
    * @desc Validate record
    */
    function validate(&$errors)
    {
        $errors = array();
        if (empty($this->data->company_id)) {
            $errors[] = 'Company is required';
        }
        if (empty($this->data->number)) {
            $errors[] = 'Number is required';
        }
        if (empty($this->data->invoice_number)) {
            $errors[] = 'Invoice number is required';
        }
        return !count($errors);
    }

    /**
    * @return unknown
    * @param object $db
    * @param int $id
    * @desc Get array of all articles in a group
    */
    static function getArticles($db, $dbr, $id)
    {
		$q = "select t.*, 
            IF (add_to_warehouse_date != '0000-00-00 00:00:00', 
                IF(IFNULL(add_to_warehouse,0),
                    CONCAT('Article delivered on ', add_to_warehouse_date, ' by ', IFNULL(u.name, add_to_warehouse_uname), ' to ', warehouse_name),
                    CONCAT('Article removed on ', add_to_warehouse_date, ' by ', IFNULL(u.name, add_to_warehouse_uname))
                ), 
                ''
		) add_to_stock_text
		from (
		SELECT 
		opa.container_id,
		oc.container_no container,
		add_to_warehouse_uname,
		add_to_warehouse_date,
		opa.id as id,
		opa.qnt_ordered,
		opa.qnt_delivered,
		opa.add_to_warehouse,
		opa.warehouse_id,
		a.article_id,
                a.iid,
		(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) name,
		(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'description'
				AND language = 'german'
				AND id = a.article_id) description,
		a.volume,
		a.volume_per_single_unit,
		a.weight_per_single_unit,
		a.items_per_shipping_unit items_per_package,
		a.shipping_cost,
		a.take_real,
		(select CONCAT('Order ', op_order_id, ': Imported on ', import_date, ' by ', ifnull((select name from users where article_import.username=username), article_import.username)) 
			from article_import 
			where article_id=opa.article_id and op_order_id=opa.op_order_id
			and IFNULL(article_import.op_article_id,opa.id)=opa.id
			order by import_date desc
			limit 0, 1
			) as import_date,
		(select CONCAT('Order ', op_order_id, ': Imported on ', import_date, ' by ', ifnull((select name from users where article_import.username=username), article_import.username))
			from article_import 
			where article_id=opa.article_id	and article_import.country_code=wc.country_code
			and IFNULL(article_import.op_article_id,opa.id)=opa.id
			order by import_date desc
			limit 0, 1
			) as last_import_date,
		(select op_order_id
			from article_import 
			where article_id=opa.article_id
			order by import_date desc
			limit 0, 1
			) as last_op_order_id,
		a.supplier_article_id,
		(opo.shipping_cost_per_volume * a.volume) as shipping_cost_per_item,
		(opo.transport_cost_per_volume * a.volume) as transport_cost_per_item,
		(opo.shipping_cost_per_volume * a.volume 
			+ opo.transport_cost_per_volume * a.volume
			+ IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=wc.country_code
					and article_import.article_id=a.article_id
					order by import_date desc limit 1
					),a.purchase_price)
			) as subtotal,
		opa.purchase_price
		,w.name warehouse_name
		, (select auction_number
			from orders
			where spec_order_id=opo.id and spec_order
			and article_id=opa.article_id
			 and spec_order
			order by id limit 1) sp_auction_number 
		, (select txnid
			from orders
			where spec_order_id=opo.id and spec_order
			and article_id=opa.article_id
			 and spec_order
			order by id limit 1) sp_txnid
		, (select quantity
			from orders
			where spec_order_id=opo.id and spec_order
			and article_id=opa.article_id
			 and spec_order
			order by id limit 1) sp_Y
		, (select IF(sent, '#999999', '#008800')
			from orders
			where spec_order_id=opo.id and spec_order
			and article_id=opa.article_id
			 and spec_order
			order by id limit 1) sp_color
		, (select 
			GROUP_CONCAT(CONCAT('<font style=\"color:',IF(sent, '#999999', '#008800'),';\"><br>'
				,'<a style=\"color:', IF(sent, '#999999', '#008800'),';\" 
				href=\"auction.php?number=',IFNULL(mauo.auction_number,auo.auction_number),'&txnid=',IFNULL(mauo.txnid,auo.txnid),'\">'
				, o.quantity,' x SO for auftrag ',IFNULL(mauo.auction_number,auo.auction_number),'/',IFNULL(mauo.txnid,auo.txnid),'</a>'
				, IF(IFNULL(muo.name,uo.name) is null, '', 
					CONCAT(' - <font style=\"color:#FF00FF\">Responsible '
						,IFNULL(IFNULL(muo.name,uo.name),IFNULL(mauo.shipping_username,auo.shipping_username)),'</font>')
					)
				)
			SEPARATOR '<br>')
			from orders o
				join auction auo on auo.auction_number=o.auction_number and auo.txnid=o.txnid
				left join auction mauo on auo.main_auction_number=mauo.auction_number and auo.main_txnid=mauo.txnid
				left join users uo on uo.username=auo.shipping_username
				left join users muo on muo.username=mauo.shipping_username
			where spec_order_id=opo.id and spec_order
			and article_id=opa.article_id and spec_order_container_id=opa.container_id
			 and spec_order) sp_
			, (select count(*) from barcode_object bo 
			join barcode b on bo.barcode_id=b.id where b.inactive=0 and bo.obj='op_article' and bo.obj_id=opa.id) qnt_barcoded_old
            , (SELECT COUNT(*)
                FROM `vbarcode` `b`
                JOIN `barcode_dn` `bw` ON `b`.`id` = `bw`.`id`
                #LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
                WHERE 
                    `b`.`opa_id` = `opa`.`id`
                    AND NOT `b`.`inactive`
                    #AND `bs`.`type` = 'in'
            ) `qnt_barcoded`
			, 0 as sub
		, a.picture_URL,a.barcode_type
		FROM op_order opo 
			JOIN op_article opa ON opa.op_order_id=opo.id
			left JOIN warehouse w ON opa.warehouse_id=w.warehouse_id
			left JOIN op_order_container opc ON opa.container_id=opc.id
			left join op_order_container master on master.id=opc.master_id
			left join op_order_container oc on oc.id = IFNULL(master.id, opc.id)
			left JOIN warehouse wc ON oc.planned_warehouse_id=wc.warehouse_id
			JOIN article a ON a.article_id=opa.article_id AND NOT a.admin_id
		WHERE opo.id = $id 
		group by opa.id
		) t
			left join users u on add_to_warehouse_uname=u.username
		ORDER BY article_id";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
        $list = array();
		$warehouses = Warehouse::listArray($db, $dbr);
		$warehouses_c = Warehouse::listArray($db, $dbr, " AND `w`.`barcode_type` = 'C' AND NOT `w`.`inactive` ");
        
		$warehouses_all = Warehouse::listArray($db, $dbr, '');
        while ($article = $r->fetchRow()) {
			$article->barcodes = $dbr->getAll("select barcode.* from barcode_object 
				join barcode on barcode_object.barcode_id=barcode.id
				where obj = 'op_article' and obj_id=$article->id");
			$article->warehouses = $warehouses;
			$article->warehouses_c = $warehouses_c;
            if ($article->warehouse_id)
            {
                $article->warehouses[$article->warehouse_id] = $warehouses_all[$article->warehouse_id];
            }
			$article->picture_URL_200 = str_replace('_image.jpg','_x_200_image.jpg',$article->picture_URL);
            $list[] = $article;
        }
        return $list;
    }

    static function getArticle($db, $dbr, $id)
    {
		$q = "select t.*, 
            IF (add_to_warehouse_date != '0000-00-00 00:00:00', 
                IF(IFNULL(add_to_warehouse,0),
                    CONCAT('Article delivered on ', add_to_warehouse_date, ' by ', IFNULL(u.name, add_to_warehouse_uname), ' to ', warehouse_name),
                    CONCAT('Article removed on ', add_to_warehouse_date, ' by ', IFNULL(u.name, add_to_warehouse_uname))
                ), 
                ''
            ) add_to_stock_text
		from (
		SELECT 
		opa.container_id,
		add_to_warehouse_uname,
		add_to_warehouse_date,
		opo.id op_order_id,
		opa.id as id,
		opa.qnt_ordered,
		opa.qnt_delivered,
		opa.add_to_warehouse,
		opa.warehouse_id,
		a.article_id,
		(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) name,
		(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'description'
				AND language = 'german'
				AND id = a.article_id) description,
		a.volume,
		a.volume_per_single_unit,
		a.items_per_shipping_unit items_per_package,
		a.shipping_cost,
		a.take_real,
		(select CONCAT('Order ', op_order_id, ': Imported on ', import_date, ' by ', ifnull(u.name, article_import.username)) 
			from article_import 
			left join users u on article_import.username=u.username
			where article_id=opa.article_id and op_order_id=opa.op_order_id
			order by import_date desc
			limit 0, 1
			) as import_date,
		(select CONCAT('Order ', op_order_id, ': Imported on ', import_date, ' by ', ifnull(u.name, article_import.username))
			from article_import 
			left join users u on article_import.username=u.username
			where article_id=opa.article_id
			order by import_date desc
			limit 0, 1
			) as last_import_date,
		(select op_order_id
			from article_import 
			left join users u on article_import.username=u.username
			where article_id=opa.article_id
			order by import_date desc
			limit 0, 1
			) as last_op_order_id,
		a.supplier_article_id,
		(opo.shipping_cost_per_volume * a.volume) as shipping_cost_per_item,
		(opo.transport_cost_per_volume * a.volume) as transport_cost_per_item,
		(opo.shipping_cost_per_volume * a.volume 
			+ opo.transport_cost_per_volume * a.volume
			+ IFNULL((select article_import.purchase_price from article_import
					where article_import.country_code=wc.country_code
					and article_import.article_id=a.article_id
					order by import_date desc limit 1
					),a.purchase_price)
			) as subtotal,
		opa.purchase_price
		,w.name warehouse_name
		FROM op_order opo 
			JOIN op_article opa ON opa.op_order_id=opo.id
			left JOIN warehouse w ON opa.warehouse_id=w.warehouse_id
			left JOIN op_order_container oc ON opa.container_id=oc.id
			left JOIN warehouse wc ON oc.planned_warehouse_id=wc.warehouse_id
			JOIN article a ON a.article_id=opa.article_id AND NOT a.admin_id
		WHERE opa.id = $id 
		group by opa.id) t
			left join users u on add_to_warehouse_uname=u.username
		ORDER BY article_id";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        return $r->fetchRow();
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
		$op_order_id, 
		$article_id,
		$container_id, 
		$qnt_ordered,
		$qnt_delivered,
		$volume,
		$purchase_price,
		$add_to_warehouse
	)
    {
        $op_order_id = (int)$op_order_id;
		$article_id = mysql_escape_string($article_id);
		$container_id = mysql_escape_string($container_id); if ($container_id=='') $container_id='NULL';
		$qnt_ordered = mysql_escape_string($qnt_ordered); if ($qnt_ordered=='') $qnt_ordered='NULL';
		$qnt_delivered = mysql_escape_string($qnt_delivered); if ($qnt_delivered=='') $qnt_delivered='NULL';
		$volume = mysql_escape_string($volume); if ($volume=='') $volume='NULL';
		$purchase_price = mysql_escape_string($purchase_price); if ($purchase_price=='') $purchase_price='NULL';
		$add_to_warehouse = mysql_escape_string($add_to_warehouse); if ($add_to_warehouse=='') $add_to_warehouse='NULL';
        $db->query("INSERT INTO op_article SET 
		op_order_id=$op_order_id, 
		article_id = '$article_id',
		container_id = $container_id,
		qnt_ordered = $qnt_ordered,
		qnt_delivered = $qnt_delivered,
		volume = $volume,
		purchase_price = $purchase_price,
		add_to_warehouse = $add_to_warehouse
		");
        $db->query("update article SET purchase_price=$purchase_price
		where article_id = '$article_id' and admin_id=0 and IFNULL(purchase_price,0)=0
		");
        $db->query("update article SET total_item_cost=$purchase_price*".(1*Config::get($db, $dbr, 'OPOrder_artice_pric_factor'))."
		where article_id = '$article_id' and admin_id=0 and IFNULL(total_item_cost,0)=0
		");
		$r = $dbr->getOne("SELECT max(id) FROM op_article");

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
		$op_order_id, 
		$article_id,
		$container_id, 
		$qnt_ordered,
		$qnt_delivered,
		$volume,
		$purchase_price,
		$add_to_warehouse,
		$add_to_warehouse_date,
		$add_to_warehouse_uname,
		$warehouse_id, 
		$ware_la_id = 0
	)
    {
		if ($container_id=='') $container_id=null;
		if ($qnt_ordered=='') $qnt_ordered=null;
		if ($qnt_delivered=='') $qnt_delivered=null;
		if ($volume=='') $volume=null;
		if ($purchase_price=='') $purchase_price=null;
		if ($add_to_warehouse=='') $add_to_warehouse=0;
		if ($add_to_warehouse_date=='') $add_to_warehouse_date=null;
		if ($add_to_warehouse_uname=='') $add_to_warehouse_uname=null;
        if ( ! $ware_la_id) $ware_la_id = null;
		
        $query = "UPDATE op_article SET 
                op_order_id= ?, 
                article_id = ?,
                container_id = ?,
                qnt_ordered = ?,
                qnt_delivered = ?,
                volume = ?,
                purchase_price = ?,
                add_to_warehouse = ?,
                add_to_warehouse_date = ?,
                add_to_warehouse_uname = ?,
                warehouse_id=?,
                ware_la_id=?
            WHERE id=?";
        
        $db->execParam($query, [$op_order_id, $article_id, $container_id, $qnt_ordered, $qnt_delivered, $volume, $purchase_price, 
            $add_to_warehouse, $add_to_warehouse_date, $add_to_warehouse_uname, $warehouse_id, $ware_la_id, 
            $id]);
        
        $db->execParam("update article SET purchase_price=?
            where article_id = ? and admin_id=0 and IFNULL(purchase_price,0)=0", 
                [$purchase_price, $article_id]);
        
        $db->execParam("update article SET total_item_cost=?
            where article_id = ? and admin_id=0 and IFNULL(total_item_cost,0)=0", 
                [$purchase_price * (float)Config::get($db, $dbr, 'OPOrder_artice_pric_factor'), $article_id]);
    }

    static function getContainers($db, $dbr, $id)
    {
		$log_fields = ", fget_WarehouseEmail(planned_warehouse.warehouse_id) warehouse_email, (select CONCAT(el.`date`, ' by ', IFNULL(u.name, tl.username))
			from email_log el
			join total_log tl on tl.table_name='email_log' and tl.field_name='id'
			and tl.old_value is null and tl.TableID=el.id
			left join users u on u.system_username=tl.username
			where el.template='op_container_delivery_document' and el.notes*1=op_order_container.id
			and el.auction_number=op_order_container.order_id and el.txnid=-1
			and el.recipient like CONCAT('%',seller_information.email,'%')
			order by el.`date` desc limit 1) importer_last_sent
			, (select CONCAT(el.`date`, ' by ', IFNULL(u.name, tl.username))
			from email_log el
			join total_log tl on tl.table_name='email_log' and tl.field_name='id'
			and tl.old_value is null and tl.TableID=el.id
			left join users u on u.system_username=tl.username
			where el.template='op_container_delivery_document' and el.notes*1=op_order_container.id
			and el.auction_number=op_order_container.order_id and el.txnid=-1
			and el.recipient like CONCAT('%',op_return_terminal.email,'%')
			order by el.`date` desc limit 1) return_terminal_last_sent
			, (select CONCAT(el.`date`, ' by ', IFNULL(u.name, tl.username))
			from email_log el
			join total_log tl on tl.table_name='email_log' and tl.field_name='id'
			and tl.old_value is null and tl.TableID=el.id
			left join users u on u.system_username=tl.username
			where el.template='op_container_delivery_document' and el.notes*1=op_order_container.id
			and el.auction_number=op_order_container.order_id and el.txnid=-1
			and el.recipient like CONCAT('%',op_destination_terminal.email,'%')
			order by el.`date` desc limit 1) destination_terminal_last_sent
			, (select CONCAT(el.`date`, ' by ', IFNULL(u.name, tl.username))
			from email_log el
			join total_log tl on tl.table_name='email_log' and tl.field_name='id'
			and tl.old_value is null and tl.TableID=el.id
			left join users u on u.system_username=tl.username
			where el.template='op_container_delivery_document' and el.notes*1=op_order_container.id
			and el.auction_number=op_order_container.order_id and el.txnid=-1
			and el.recipient like CONCAT('%',op_company.email,'%')
			order by el.`date` desc limit 1) trucking_company_last_sent
			, (select CONCAT(el.`date`, ' by ', IFNULL(u.name, tl.username))
			from email_log el
			join total_log tl on tl.table_name='email_log' and tl.field_name='id'
			and tl.old_value is null and tl.TableID=el.id
			left join users u on u.system_username=tl.username
			where el.template='op_container_delivery_document' and el.notes*1=op_order_container.id
			and el.auction_number=op_order_container.order_id and el.txnid=-1
			and el.recipient like CONCAT('%',op_address_resp.email,'%')
			order by el.`date` desc limit 1) resp_address_last_sent
			, (select CONCAT(el.`date`, ' by ', IFNULL(u.name, tl.username))
			from email_log el
			join total_log tl on tl.table_name='email_log' and tl.field_name='id'
			and tl.old_value is null and tl.TableID=el.id
			left join users u on u.system_username=tl.username
			where el.template='op_container_delivery_document' and el.notes*1=op_order_container.id
			and el.auction_number=op_order_container.order_id and el.txnid=-1
			and el.recipient like CONCAT('%',warehouse_email,'%')
			order by el.`date` desc limit 1) planned_warehouse_last_sent
					, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='planned_warehouse_id'
						and tl.tableid=op_order_container.id
						order by tl.updated desc limit 1) planned_warehouse_last_change
			, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
			from total_log tl
			left join users u on u.system_username=tl.username
			where tl.table_name='op_order_container' and tl.field_name='agent_payment'
			and tl.tableid=op_order_container.id
			order by tl.updated desc limit 1) agent_payment_log
			, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
			from total_log tl
			left join users u on u.system_username=tl.username
			where tl.table_name='op_order_container' and tl.field_name='balance_payment'
			and tl.tableid=op_order_container.id
			order by tl.updated desc limit 1) balance_payment_log
			, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
			from total_log tl
			left join users u on u.system_username=tl.username
			where tl.table_name='op_order_container' and tl.field_name='local_charge_payment'
			and tl.tableid=op_order_container.id
			order by tl.updated desc limit 1) local_charge_payment_log
			, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
			from total_log tl
			left join users u on u.system_username=tl.username
			where tl.table_name='op_order_container' and tl.field_name='container_released'
			and tl.tableid=op_order_container.id
			order by tl.updated desc limit 1) container_released_log
			, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
			from total_log tl
			left join users u on u.system_username=tl.username
			where tl.table_name='op_order_container' and tl.field_name='etd_confirmed'
			and tl.tableid=op_order_container.id
			order by tl.updated desc limit 1) etd_confirmed_log
			, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
			from total_log tl
			left join users u on u.system_username=tl.username
			where tl.table_name='op_order_container' and tl.field_name='on_train'
			and tl.tableid=op_order_container.id
			order by tl.updated desc limit 1) on_train_log
			, (select CONCAT(' changed from ', IFNULL(opcs.name, 'NONE'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						left join op_container_status opcs on tl.old_value=opcs.id
						where tl.table_name='op_order_container' and tl.field_name='status_id'
						and tl.tableid=op_order_container.id
						order by tl.updated desc limit 1) status_id_last_change
			, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='ptd'
						and tl.tableid=op_order_container.id
						order by tl.updated desc limit 1) ptd_last_change
			, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='eda'
						and tl.tableid=op_order_container.id
						order by tl.updated desc limit 1) eda_last_change
			, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='bl_number'
						and tl.tableid=op_order_container.id
						order by tl.updated desc limit 1) bl_number_last_change
			, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='comment_transport'
						and tl.tableid=op_order_container.id
						order by tl.updated desc limit 1) comment_transport_last_change
			";
		$q = "select op_order_container.*
				, opc.master_id real_master_id
				, opc.id real_id
				, opc.order_id real_order_id
				, master.order_id master_order_id
				, c.code dest_country_code
				, c.name dest_country_name
				, DATE_FORMAT(op_order_container.delivery_time, '%H:%i') delivery_time_hm
				, op_content.content op_content
				, op_container.name op_container_name
				, op_container.volume op_container_volume
				, seller_information.username importer_username
				, seller_information.id importer_seller_id
				, seller_information.seller_name importer_name
				, seller_information.zip importer_zip
				, seller_information.town importer_town
				, seller_information.country importer_country
				, tc3.value importer_country_name
				, seller_information.street importer_street
				, CONCAT(seller_information.street, ', '
					, seller_information.zip, ' '
					, seller_information.town, ', '
					, seller_information.country
					) importer_address
				, seller_information.email importer_email
				, seller_information.phone importer_tel
				, seller_information.op_doc_text importer_text
				, seller_information.op_doc_stamp importer_stamp
				, planned_warehouse.name plan_ware_name
				, CONCAT(planned_warehouse.address1, ' '
					, planned_warehouse.address2, ' '
					, planned_warehouse.address3
					) plan_ware_address
				, planned_warehouse.address1 plan_ware_address1
				, planned_warehouse.address2 plan_ware_address2
				, planned_warehouse.address3 plan_ware_address3
				, planned_warehouse.phone plan_ware_tel
				, fget_WarehouseEmail(planned_warehouse.warehouse_id) plan_ware_email
				, planned_warehouse.country_code plan_ware_country_code
				, dest_port.name dest_port
				, op_address_resp.company resp_company
				, op_address_resp.name resp_name
				, op_address_resp.street resp_street
				, op_address_resp.zip resp_zip
				, op_address_resp.town resp_town
				, op_address_resp.country_code resp_country_code
				, op_address_resp.tel resp_tel
				, op_address_resp.email resp_email
				, op_address_resp.text resp_text
				, tc4.value resp_country
				, op_company.name trucking_company_name
				, op_company.email trucking_company_email
				, op_company.phone trucking_company_phone
				, op_company.person trucking_company_person
				, op_company.zip trucking_company_zip
				, op_company.town trucking_company_town
				, op_company.street trucking_company_street
				, op_company.country_code trucking_company_country_code
				, tc5.value trucking_company_country
				, op_destination_terminal.company destination_terminal_company
				, op_destination_terminal.name destination_terminal_name
				, op_destination_terminal.street destination_terminal_street
				, op_destination_terminal.zip destination_terminal_zip
				, op_destination_terminal.town destination_terminal_town
				, op_destination_terminal.country_code destination_terminal_country_code
				, op_destination_terminal.tel destination_terminal_tel
				, op_destination_terminal.email destination_terminal_email
				, op_destination_terminal.text destination_terminal_text
				, tc1.value destination_terminal_country
				, op_return_terminal.company return_terminal_company
				, op_return_terminal.name return_terminal_name
				, op_return_terminal.street return_terminal_street
				, op_return_terminal.zip return_terminal_zip
				, op_return_terminal.town return_terminal_town
				, op_return_terminal.country_code return_terminal_country_code
				, op_return_terminal.tel return_terminal_tel
				, op_return_terminal.email return_terminal_email
				, op_return_terminal.text return_terminal_text
				, tc2.value return_terminal_country
				, op_demurrage.name demurrage_name
				, shipping_company.name shipping_company_name
				, shipping_company.email shipping_company_email
				, truck_route.date truck_route_date
				, cars.name cars_name
				, (select CONCAT(IF(New_value is null, 'Was deleted', 'Was set'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))				
			from total_log tl
			left join users u on u.system_username=tl.username
			where table_name='op_order_container' and field_name='arrival_date' and tableid=op_order_container.id
			order by updated desc limit 1) arrival_status
			, (select group_concat(concat('<a target=\"_blank\" href=\"ware2ware_order.php?id=',ww_order.id,'\">  WWO#', ww_order.id, '</a>') separator '<br>') from ww_order where ww_order.container_id=op_order_container.id) wwos
			$log_fields
			from op_order_container opc
			left join op_order_container master on master.id=opc.master_id
			left join op_order_container on op_order_container.id = IFNULL(master.id, opc.id)
			left join op_company shipping_company on shipping_company.id=op_order_container.shipping_company_id
			left join op_demurrage on op_order_container.demurrage=op_demurrage.id
			left join op_content on op_content.id=op_order_container.op_content_id
			left join op_container on op_container.id=op_order_container.container
			left join op_address_resp on op_address_resp.id=op_order_container.resp_address_id
			left join seller_information on seller_information.username=op_order_container.importer_id
			left join op_company on op_company.id=op_order_container.trucking_company_id
			left join op_destination_terminal on op_destination_terminal.id=op_order_container.destination_terminal_id
			left join op_destination_terminal op_return_terminal on op_return_terminal.id=op_order_container.return_terminal_id
			left join warehouse planned_warehouse on planned_warehouse.warehouse_id=op_order_container.planned_warehouse_id
			left join country c on c.code=planned_warehouse.country_code
			left join translation tc on tc.id=c.id and tc.table_name='country' and tc.field_name='name' and tc.language='master'
			left join country c1 on c1.code=op_destination_terminal.country_code
			left join translation tc1 on tc1.id=c1.id and tc1.table_name='country' and tc1.field_name='name' and tc1.language='master'
			left join country c2 on c2.code=op_return_terminal.country_code
			left join translation tc2 on tc2.id=c2.id and tc2.table_name='country' and tc2.field_name='name' and tc2.language='master'
			left join country c3 on c3.code=seller_information.country
			left join translation tc3 on tc3.id=c3.id and tc3.table_name='country' and tc3.field_name='name' and tc3.language='master'
			left join country c4 on c4.code=op_address_resp.country_code
			left join translation tc4 on tc4.id=c4.id and tc4.table_name='country' and tc4.field_name='name' and tc4.language='master'
			left join country c5 on c5.code=op_company.country_code
			left join translation tc5 on tc5.id=c5.id and tc5.table_name='country' and tc5.field_name='name' and tc5.language='master'
			left join country c6 on c6.code=planned_warehouse.country_code
			left join op_port dest_port on dest_port.id=c6.dest_port_id
			left join truck_route on truck_route.container_id=op_order_container.id 
                AND op_order_container.planned_warehouse_id = truck_route.warehouse_id_to
                AND IFNULL(truck_route.terminal_id_from, 0) != 0
            left join cars on cars.id = truck_route.car_id 
			where opc.order_id=$id 
		ORDER BY id";
//		echo "<pre>" . htmlspecialchars($q) . "</pre>";
//        exit;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
		$warehouses = Warehouse::listArray($db, $dbr);
		$warehouses_all = Warehouse::listArray($db, $dbr, '');
		foreach ($r as $key=>$rec) {
			$r[$key]->warehouses = $warehouses;
			$r[$key]->warehouses[$r[$key]->planned_warehouse_id] = $warehouses_all[$r[$key]->planned_warehouse_id];
			$r[$key]->fees = $dbr->getAll("select oc.id as order_container_id
				, of.value
				, of.arrival_date
				, of.arrival_username
				, of.currency
				, of.agreed_price
				, of.percent
				, lv.value fee_caption
				, lv.key as fee_name
				, IFNULL(u.name, of.arrival_username) arrival_user
				, of.comment
				from list_value lv
				left join op_order_container oc on 1
				left join op_order_fee of on of.fee_name=lv.key and oc.id=of.order_container_id
				left join users u on of.arrival_username=u.username
				where oc.id=".$rec->id." and lv.par_name='op_order_fee'
				ORDER BY lv.ordering");
       		if (PEAR::isError($r[$key]->fees)) {
   	        	aprint_r($r[$key]->fees);
        	}
	        $r[$key]->same_as_containers_array = $dbr->getAssoc("select id, container_no from op_order_container opc1 
				where opc1.order_id=(select order_id from op_order_container where id=".$rec->id.")
				and id not in (select ".$rec->id."
				union select id from op_order_container opc2 where opc2.same_as_container_id=".$rec->id.")");
			$r[$key]->subs = $dbr->getAll("select * from op_order_container where master_id=".$rec->real_id);
			$q = "select group_concat(id) from (
				select id from op_order_container opc where master_id={$rec->real_id} and id<>{$rec->real_id}
				union
				select id from op_order_container opc where {$rec->real_master_id} and master_id={$rec->real_master_id} and id<>{$rec->real_id}
				union
				select id from op_order_container opc where {$rec->real_master_id} and id={$rec->real_master_id} and id<>{$rec->real_id}
				) t
				";
			$other_cids = $dbr->getOne($q);
//			echo $q;
			$q = "SELECT
					MAX(opa.add_to_warehouse) added,
					COUNT(b.id) barcodes
				FROM op_article opa
					LEFT JOIN barcode_object bo ON bo.obj = 'op_article' and bo.obj_id = opa.id
					LEFT JOIN barcode b ON b.id = bo.barcode_id
				WHERE opa.container_id = {$rec->real_id} and b.inactive = 0";
			$cont_info = $dbr->getRow($q);
			$r[$key]->added = $cont_info->added;
			$r[$key]->barcodes = $cont_info->barcodes;
//			print_r($other_cids); echo ' for container '.$rec->real_id.'<br>';
			if (strlen($other_cids)) {
				$qq = "select sum(a.volume_per_single_unit * opa.qnt_delivered)
					from op_order_container opc
					join op_article opa on opa.container_id=opc.id
					join article a on a.article_id=opa.article_id and a.admin_id=0
					where opc.id in ({$other_cids})";
				$r[$key]->other_volumes = $dbr->getOne($qq);
//				if ($rec->real_id==4327) echo nl2br($q."\n".$qq);
			} else {
				$r[$key]->other_volumes = 0;
			}
       		if (PEAR::isError($r[$key]->same_as_containers_array)) {
   	        	aprint_r($r[$key]->same_as_containers_array);
        	}
		}// foreach article
        return $r;
    }

    static function getContainersArray($db, $dbr, $id)
    {
        $r = $dbr->getAssoc("select opc.id, IF(opc.master_id, concat('(', master.container_no, ')'), opc.container_no)
			from op_order_container opc
			left join op_order_container master on master.id=opc.master_id
			where opc.order_id=$id 
		ORDER BY opc.id");
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }

    static function addContainer($db, $dbr, 
		$order_id, 
		$container_no,
		$content,
		$container,
		$bl_forwarded_to,
		$bl_arrived_date,
		$eda,
		$edd,
		$ptd,
		$destination_terminal_id,
		$trucking_company_id,
		$planned_warehouse_id,
		$arrival_date,
		$counted_by,
		$FOB,
		$demurrage,
		$status_id,
		$shipping_company,
		$shipping_line,
		$shipping_cost_per_volume,
		$dest_country_code,
		$bl_number,
		$ata_number,
		$comment_transport
		, $importer_id
		, $return_terminal_id
		, $weight
		, $carton_number
		, $turnin_number
		, $destination_terminal_send
		, $trucking_company_send
		, $planned_warehouse_send
		, $resp_address_id
		, $op_content_id
		, $delivery_date
		, $delivery_time
		, $return_terminal_send
		, $resp_address_send
		, $importer_send
		, $agent_payment
		, $balance_payment
		, $local_charge_payment
		, $container_released
		, $etd_confirmed
		, $on_train
		, $etd_train
		, $etd_train_time
		, $train_booking_number
		, $destination_local_terminal_id
		, $return_local_terminal_id
		, $vessel
		, $position
		, $driver_id
		, $pickup_date
		, $master_id
	)
    {
        $order_id = (int)$order_id;
		$container_no = "'".mysql_escape_string($container_no)."'"; if ($container_no=="''") $container_no='NULL';
		$container = "'".mysql_escape_string($container)."'"; if ($container=="''") $container='NULL';
		$content = "'".mysql_escape_string($content)."'"; if ($content=="''") $content='NULL';
		$bl_forwarded_to = "'".mysql_escape_string($bl_forwarded_to)."'"; if ($bl_forwarded_to=="''") $bl_forwarded_to='NULL';
		$bl_arrived_date = "'".mysql_escape_string($bl_arrived_date)."'"; if ($bl_arrived_date=="''") $bl_arrived_date='NULL';
		$eda = "'".mysql_escape_string($eda)."'"; if ($eda=="''") $eda='NULL';
		$edd = "'".mysql_escape_string($edd)."'"; if ($edd=="''") $edd='NULL';
		$ptd = "'".mysql_escape_string($ptd)."'"; if ($ptd=="''") $ptd='NULL';
		$destination_terminal_id = (int)mysql_escape_string($destination_terminal_id);
		$trucking_company_id = (int)mysql_escape_string($trucking_company_id);
		$planned_warehouse_id = (int)$planned_warehouse_id; if (!$planned_warehouse_id) $planned_warehouse_id='NULL';
		$arrival_date = "'".mysql_escape_string($arrival_date)."'"; if ($arrival_date=="''") $arrival_date='NULL';
		$counted_by = "'".mysql_escape_string($counted_by)."'"; if ($counted_by=="''") $counted_by='NULL';
		$FOB = "'".mysql_escape_string($FOB)."'"; if ($FOB=="''") $FOB='NULL';
		$demurrage = "'".mysql_escape_string($demurrage)."'"; if ($demurrage=="''") $demurrage='NULL';
		$status_id = "'".mysql_escape_string($status_id)."'"; if ($status_id=="''") $status_id='NULL';
		$shipping_company = "'".mysql_escape_string($shipping_company)."'"; if ($shipping_company=="''") $shipping_company='NULL';
		$shipping_line = "'".mysql_escape_string($shipping_line)."'"; if ($shipping_line=="''") $shipping_line='NULL';
		$shipping_cost_per_volume = "'".mysql_escape_string($shipping_cost_per_volume)."'"; if ($shipping_cost_per_volume=="''") $shipping_cost_per_volume='NULL';
		$dest_country_code = "'".mysql_escape_string($dest_country_code)."'"; if ($dest_country_code=="''") $dest_country_code='NULL';
		$comment_transport = "'".mysql_escape_string($comment_transport)."'"; if ($comment_transport=="''") $comment_transport='NULL';
		$bl_number = "'".mysql_escape_string($bl_number)."'"; if ($bl_number=="''") $bl_number='NULL';
		$ata_number = "'".mysql_escape_string($ata_number)."'"; if ($ata_number=="''") $ata_number='NULL';
		$importer_id = "'".mysql_escape_string($importer_id)."'";
		$return_terminal_id = (int) $return_terminal_id;
		$weight = 1*$weight;
		$carton_number = "'".mysql_escape_string($carton_number)."'";
		$turnin_number = "'".mysql_escape_string($turnin_number)."'";
		$destination_terminal_send = (int) $destination_terminal_send;
		$trucking_company_send = (int) $trucking_company_send;
		$planned_warehouse_send = (int) $planned_warehouse_send;
		$return_terminal_send = (int) $return_terminal_send;
		$resp_address_send = (int) $resp_address_send;
		$importer_send = (int) $importer_send;
		$resp_address_id = (int) $resp_address_id;
		$op_content_id = (int) $op_content_id;
		$delivery_date = "'".mysql_escape_string($delivery_date)."'"; if ($delivery_date=="''") $delivery_date='NULL';
		$delivery_time = "'".mysql_escape_string($delivery_time)."'"; if ($delivery_time=="''") $delivery_time='NULL';
		$agent_payment = (int) $agent_payment;
		$balance_payment = (int) $balance_payment;
		$local_charge_payment = (int) $local_charge_payment;
		$container_released = (int) $container_released;
		$etd_confirmed = (int) $etd_confirmed;
		$on_train = (int) $on_train;
		$etd_train =  "'".mysql_escape_string($etd_train)."'"; if ($etd_train=="''") $etd_train='NULL';
		$etd_train_time =  "'".mysql_escape_string($etd_train_time)."'"; if ($etd_train_time=="''") $etd_train_time='NULL';
		$train_booking_number = "'".mysql_escape_string($train_booking_number)."'"; if ($train_booking_number=="''") $train_booking_number='NULL';
		$destination_local_terminal_id = (int) $destination_local_terminal_id;
		$return_local_terminal_id = (int) $return_local_terminal_id;
		$vessel = "'".mysql_escape_string($vessel)."'"; if ($vessel=="''") $vessel='NULL';
		$position = (int) $position;
		$driver_id = (int) $driver_id;
		$pickup_date = "'".mysql_escape_string($pickup_date)."'"; if ($pickup_date=="''") $pickup_date='NULL';
		$master_id = (int) $master_id;
		if ($master_id) {
	        $r = $db->query("INSERT INTO op_order_container SET 
				order_id=$order_id, master_id=$master_id");
		} else {
	        $r = $db->query("INSERT INTO op_order_container SET 
		order_id=$order_id, 
		container_no = $container_no,
		content = $content,
		container = $container,
		bl_forwarded_to = $bl_forwarded_to,
		bl_arrived_date = $bl_arrived_date,
		eda = $eda,
		edd = $edd,
		ptd = $ptd,
		destination_terminal_id = $destination_terminal_id,
		trucking_company_id = $trucking_company_id,
		planned_warehouse_id = $planned_warehouse_id,
		#arrival_date = $arrival_date,
		counted_by = $counted_by,
		FOB = $FOB,
		demurrage = $demurrage,
		status_id = $status_id,
		shipping_company_id = $shipping_company,
		shipping_line = $shipping_line,
		shipping_cost_per_volume = $shipping_cost_per_volume,
#		dest_country_code = $dest_country_code,
		comment_transport = $comment_transport,
		bl_number = $bl_number,
		ata_number = $ata_number
		, importer_id = $importer_id
		, return_terminal_id = $return_terminal_id
		, weight = $weight
		, carton_number = $carton_number
		, turnin_number = $turnin_number
		, destination_terminal_send = $destination_terminal_send
		, trucking_company_send = $trucking_company_send
		, planned_warehouse_send = $planned_warehouse_send
		, return_terminal_send = $return_terminal_send
		, resp_address_send = $resp_address_send
		, importer_send = $importer_send
		, resp_address_id = $resp_address_id
		, op_content_id = $op_content_id
		, delivery_date = $delivery_date
		, delivery_time = $delivery_time
		, agent_payment = $agent_payment
		, balance_payment = $balance_payment
		, local_charge_payment = $local_charge_payment
		, container_released = $container_released
		, etd_confirmed = $etd_confirmed
		, on_train = $on_train
		, etd_train =  $etd_train
		, etd_train_time =  $etd_train_time
		, train_booking_number = $train_booking_number
		, destination_local_terminal_id = $destination_local_terminal_id
		, return_local_terminal_id = $return_local_terminal_id
		, vessel = $vessel
		, position = $position
		, driver_id = $driver_id
		, pickup_date = $pickup_date
			");
		}
        if (PEAR::isError($r)) {
            aprint_r($r); die();
        }
		$r = $dbr->getOne("SELECT max(id) FROM op_order_container");
		return $r;
    }

    static function updateContainer($db, $dbr, 
		$id, 
		$container_no,
		$content,
		$container,
		$same_as_container_id,
		$bl_forwarded_to,
		$bl_arrived_date,
		$eda,
		$edd,
		$ptd,
		$destination_terminal_id,
		$trucking_company_id,
		$planned_warehouse_id,
		$arrival_date,
		$counted_by,
		$FOB,
		$demurrage,
		$status_id,
		$shipping_company,
		$shipping_line,
		$shipping_cost_per_volume,
		$dest_country_code,
		$bl_number,
		$ata_number,
		$comment_transport
		, $importer_id
		, $return_terminal_id
		, $weight
		, $carton_number
		, $turnin_number
		, $destination_terminal_send
		, $trucking_company_send
		, $planned_warehouse_send
		, $resp_address_id
		, $op_content_id
		, $delivery_date
		, $delivery_time
	)
    {
		return;
        $id = (int)$id;
		$container_no = "'".mysql_escape_string($container_no)."'"; if ($container_no=="''") $container_no='NULL';
		$container = "'".mysql_escape_string($container)."'"; if ($container=="''") $container='NULL';
		$content = "'".mysql_escape_string($content)."'"; if ($content=="''") $content='NULL';
		$same_as_container_id = (int)mysql_escape_string($same_as_container_id);
		$bl_forwarded_to = "'".mysql_escape_string($bl_forwarded_to)."'"; if ($bl_forwarded_to=="''") $bl_forwarded_to='NULL';
		$bl_arrived_date = "'".mysql_escape_string($bl_arrived_date)."'"; if ($bl_arrived_date=="''") $bl_arrived_date='NULL';
		$eda = "'".mysql_escape_string($eda)."'"; if ($eda=="''") $eda='NULL';
		$edd = "'".mysql_escape_string($edd)."'"; if ($edd=="''") $edd='NULL';
		$ptd = "'".mysql_escape_string($ptd)."'"; if ($ptd=="''") $ptd='NULL';
		$destination_terminal_id = (int)mysql_escape_string($destination_terminal_id);
		$trucking_company_id = (int)mysql_escape_string($trucking_company_id);
		$planned_warehouse_id = (int)$planned_warehouse_id; if (!$planned_warehouse_id) $planned_warehouse_id='NULL';
		$arrival_date = "'".mysql_escape_string($arrival_date)."'"; if ($arrival_date=="''") $arrival_date='NULL';
		$counted_by = "'".mysql_escape_string($counted_by)."'"; if ($counted_by=="''") $counted_by='NULL';
		$FOB = "'".mysql_escape_string($FOB)."'"; if ($FOB=="''") $FOB='NULL';
		$demurrage = "'".mysql_escape_string($demurrage)."'"; if ($demurrage=="''") $demurrage='NULL';
		$status_id = "'".mysql_escape_string($status_id)."'"; if ($status_id=="''") $status_id='NULL';
		$shipping_company = "'".mysql_escape_string($shipping_company)."'"; if ($shipping_company=="''") $shipping_company='NULL';
		$shipping_line = "'".mysql_escape_string($shipping_line)."'"; if ($shipping_line=="''") $shipping_line='NULL';
		$shipping_cost_per_volume = "'".mysql_escape_string($shipping_cost_per_volume)."'"; if ($shipping_cost_per_volume=="''") $shipping_cost_per_volume='NULL';
		$dest_country_code = "'".mysql_escape_string($dest_country_code)."'"; if ($dest_country_code=="''") $dest_country_code='NULL';
		$comment_transport = "'".mysql_escape_string($comment_transport)."'"; if ($comment_transport=="''") $comment_transport='NULL';
		$bl_number = "'".mysql_escape_string($bl_number)."'"; if ($bl_number=="''") $bl_number='NULL';
		$ata_number = "'".mysql_escape_string($ata_number)."'"; if ($ata_number=="''") $ata_number='NULL';
		$importer_id = "'".mysql_escape_string($importer_id)."'";
		$return_terminal_id = (int) $return_terminal_id;
		$weight = 1*$weight;
		$carton_number = "'".mysql_escape_string($carton_number)."'";
		$turnin_number = "'".mysql_escape_string($turnin_number)."'";
		$destination_terminal_send = (int) $destination_terminal_send;
		$trucking_company_send = (int) $trucking_company_send;
		$planned_warehouse_send = (int) $planned_warehouse_send;
		$resp_address_id = (int) $resp_address_id;
		$op_content_id = (int) $op_content_id;
		$delivery_date = "'".mysql_escape_string($delivery_date)."'"; if ($delivery_date=="''") $delivery_date='NULL';
		$delivery_time = "'".mysql_escape_string($delivery_time)."'"; if ($delivery_time=="''") $delivery_time='NULL';
        $r = $db->query("UPDATE op_order_container SET 
		container_no = $container_no,
		content = $content,
		container = $container,
		same_as_container_id = $same_as_container_id,
		bl_forwarded_to = $bl_forwarded_to,
		bl_arrived_date = $bl_arrived_date,
		eda = $eda,
		edd = $edd,
		ptd = $ptd,
		destination_terminal_id = $destination_terminal_id,
		trucking_company_id = $trucking_company_id,
		planned_warehouse_id = $planned_warehouse_id,
		#arrival_date = $arrival_date,
		counted_by = $counted_by,
		FOB = $FOB,
		demurrage = $demurrage,
		status_id = $status_id,
		shipping_company_id = $shipping_company,
		shipping_line = $shipping_line,
		shipping_cost_per_volume = $shipping_cost_per_volume,
		dest_country_code = $dest_country_code,
		comment_transport = $comment_transport,
		bl_number = $bl_number,
		ata_number = $ata_number
		, importer_id = $importer_id
		, return_terminal_id = $return_terminal_id
		, weight = $weight
		, carton_number = $carton_number
		, turnin_number = $turnin_number
		, destination_terminal_send = $destination_terminal_send
		, trucking_company_send = $trucking_company_send
		, planned_warehouse_send = $planned_warehouse_send
		, resp_address_id = $resp_address_id
		, delivery_date = $delivery_date
		, delivery_time = $delivery_time
		WHERE id=$id");
        if (PEAR::isError($r)) {
            aprint_r($r); die();
        }
    }

    static function deleteContainer($db, $dbr, 
		$id 
	)
    {
        $id = (int)$id;
        $db->query("delete from op_order_container 
		WHERE id=$id");
    }

    static function getPayments($db, $dbr, $id)
    {
        $r = $db->query("SELECT 
		opp.*
		FROM op_payment opp
		WHERE opp.op_order_id = $id
		or opp.to_order_id = $id 
		ORDER BY opp.pay_date");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($row = $r->fetchRow()) {
            $list[] = $row;
        }
        return $list;
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
    static function addPayment($db, $dbr, 
		$op_order_id, 
		$pay_date,
		$amount,
		$currency,
		$username,
		$comment,
		$to_order_id
	)
    {
        $op_order_id = (int)$op_order_id;
        $to_order_id = (int)$to_order_id;
		$pay_date = mysql_escape_string($pay_date);
		$amount = mysql_escape_string($amount);  if ($amount=='') $amount='0';
		$currency = "'".mysql_escape_string($currency)."'"; if ($currency=="''") $currency='NULL';
		$username = "'".mysql_escape_string($username)."'"; if ($username=="''") $username='NULL';
		$comment = "'".mysql_escape_string($comment)."'"; if ($comment=="''") $comment='NULL';
        $db->query("INSERT INTO op_payment SET 
		op_order_id=$op_order_id, 
		pay_date = '$pay_date',
		amount = $amount,
		currency = $currency,
		username = $username,
		comment = $comment,
		to_order_id = $to_order_id
		");
		$r = $dbr->getOne("SELECT max(id) FROM op_payment");
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
    static function updatePayment($db, $dbr, 
		$id, 
		$amount,
		$currency,
		$username,
		$comment,
		$to_order_id
	)
    {
        $id = (int)$id;
        $to_order_id = (int)$to_order_id;
		$amount = mysql_escape_string($amount);  if ($amount=='') $amount='0';
		$currency = "'".mysql_escape_string($currency)."'"; if ($currency=="''") $currency='NULL';
		$username = "'".mysql_escape_string($username)."'"; if ($username=="''") $username='NULL';
		$comment = "'".mysql_escape_string($comment)."'"; if ($comment=="''") $comment='NULL';
		$q = "UPDATE op_payment SET 
		amount = $amount,
		currency = $currency,
		username = $username,
		comment = $comment,
		to_order_id = $to_order_id
		WHERE id=$id";
        $db->query($q);
    }

    static function findBy($db, $dbr, $what, $value1, $value2=0, $openclosed='')
    {
		$where = '';
//		echo $what.' '.$value1;
	    switch ($what) {
    	    case 'username':
				if (strlen($value1)) $where = " and op_order.username = '$value1'";
	            break;
    	    case 'company':
				if ((int)$value1) $where = ' and op_order.company_id = '.$value1;
	            break;
    	    case 'invoice':
				$where = " and op_order.invoice_number = '".$value1."'";
	            break;
    	    case 'container_id':
				$where = " and  exists (select null from op_order_container opc1
					left join op_order_container master1 on master1.id=opc1.master_id
					where IFNULL(master1.id, opc1.id) = $value1 and opc1.order_id=op_order.id)";
				$container_id = " and opc.id = $value1 ";
	            break;
    	    case 'container_no':
				$where = " and exists (select null from op_order_container opc1
					left join op_order_container master1 on master1.id=opc1.master_id
					where IFNULL(master1.container_no, opc1.container_no) like '%$value1%' and opc1.order_id=op_order.id)";
	            break;
    	    case 'train_booking_number':
				$where = " and exists (select null from op_order_container opc1
					left join op_order_container master1 on master1.id=opc1.master_id
					where IFNULL(master1.train_booking_number, opc1.train_booking_number) like '%$value1%' and opc1.order_id=op_order.id)";
	            break;
    	    case 'wwo':
				$where = " and exists (select null from ww_order wwo
					left join op_order_container opc on opc.id=wwo.container_id
					where wwo.id=$value1 and opc.order_id=op_order.id)";
	            break;
    	    case 'date':
				if ($value1)
					$where .= " and op_order.order_date >= '".$value1."'";
				if ($value2)
					$where .= " AND op_order.order_date <= '".$value2."'";
	            break;
		}
		if (strlen($openclosed)) {
			$where .= " and close_date is ".($openclosed?'NOT':'')." null";
		}
		$q = "SELECT op_order.*, op_company.name as company_name, 
			min(opc.EDA) mineda,
			min(opc.EDD) minedd,
			min(opc.PTD) minptd,
			min(opc.container_no) mincontainer_no,
			GROUP_CONCAT(opc.id) container_ids,
			min(opc.arrival_date) minarrival_date
		FROM op_order 
		JOIN op_company ON op_order.company_id=op_company.id
		LEFT JOIN op_order_container ON op_order_container.order_id = op_order.id 
		left join op_order_container master on master.id=op_order_container.master_id
		left join op_order_container opc on IFNULL(master.id, op_order_container.id) = opc.id
		LEFT JOIN warehouse w ON opc.planned_warehouse_id = w.warehouse_id
		left join country c on c.code=w.country_code
		left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
		WHERE 1 $where
		group by op_order.id";
        $r = $db->query($q);
        if (PEAR::isError($r)) {
           aprint_r($r);
        }
        $list = array();
		global $smarty;
		$cont_statuses = $dbr->getAssoc("select id, name from op_container_status");
		$smarty->assign('cont_statuses', $cont_statuses);
		$smarty->assign('warehouses', Warehouse::listArray($db, $dbr));
		$companiesShipping = op_Order::listCompaniesArray($db, $dbr, 'shipping');
		$smarty->assign('companiesShipping', $companiesShipping);
		$destination_terminals = $dbr->getAssoc("select id, name from op_destination_terminal");
		$smarty->assign('destination_terminals', $destination_terminals);
        while ($row = $r->fetchRow()) {
			if (strlen($row->container_ids)) {
				$q = "select max(op_order_container.master_id) real_master_id, max(master.order_id) master_order_id,
					opc.*
					, c.code dest_country_code
					, c.name dest_country_name
					, ocs.name status_name
					, t.value dest_country_name
					, od.name demurrage_name
					, wc.name planned_warehouse
					, DATE_FORMAT(opc.delivery_time, '%H:%i') delivery_time_hm
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',seller_information.email,'%')
					order by el.`date` desc limit 1) importer_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_return_terminal.email,'%')
					order by el.`date` desc limit 1) return_terminal_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_destination_terminal.email,'%')
					order by el.`date` desc limit 1) destination_terminal_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_company.email,'%')
					order by el.`date` desc limit 1) trucking_company_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_address_resp.email,'%')
					order by el.`date` desc limit 1) resp_address_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',fget_WarehouseEmail(wc.warehouse_id),'%')
					order by el.`date` desc limit 1) planned_warehouse_last_sent
					, (select CONCAT(tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='planned_warehouse_id'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) planned_warehouse_last_change
					, CONCAT(seller_information.username, ' = ', seller_information.seller_name) importer_name
					, opc1.name shipping_company_name
					, op_destination_terminal.name destination_terminal_name
					, op_destination_local_terminal.name destination_local_terminal_name
					, op_container.name container_name
					, op_content.content op_content
					, op_address_resp.name resp_address_name
					, users.name counted_by_name
					, op_company.name trucking_company_name
					, op_return_terminal.name return_terminal_name
					, op_return_local_terminal.name return_local_terminal_name
					, (select CONCAT(' changed from ', IFNULL(opcs.name, 'NONE'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						left join op_container_status opcs on tl.old_value=opcs.id
						where tl.table_name='op_order_container' and tl.field_name='status_id'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) status_id_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='ptd'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) ptd_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='eda'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) eda_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='bl_number'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) bl_number_last_change
					, (select CONCAT(' changed from ', IFNULL(tl.old_value,'NULL'), ' on ', tl.updated, ' by ', IFNULL(u.name, tl.username))
						from total_log tl
						left join users u on u.system_username=tl.username
						where tl.table_name='op_order_container' and tl.field_name='comment_transport'
						and tl.tableid=opc.id
						order by tl.updated desc limit 1) comment_transport_last_change
			, (select group_concat(concat('<a target=\"_blank\" href=\"ware2ware_order.php?id=',ww_order.id,'\">  WWO#', ww_order.id, '</a>') separator '<br>') from ww_order where ww_order.container_id=op_order_container.id) wwos
			, op_order_container.order_id original_order_id
			, op_order_container.id original_id
				from op_order_container
					left join op_order_container master on master.id=op_order_container.master_id
					left join op_order_container opc on IFNULL(master.id, op_order_container.id) = opc.id
					LEFT JOIN op_container_status ocs ON ocs.id = opc.status_id 
					left join op_content on op_content.id=opc.op_content_id
					LEFT JOIN op_demurrage od ON od.id = opc.demurrage
					LEFT JOIN warehouse wc ON opc.planned_warehouse_id = wc.warehouse_id
					left join country c on c.code=wc.country_code
					left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
					left join op_address_resp on op_address_resp.id=opc.resp_address_id
					left join seller_information on seller_information.username=opc.importer_id
					left join op_company on op_company.id=opc.trucking_company_id
					left join op_company opc1 on opc1.id=opc.shipping_company_id
					left join op_destination_terminal on op_destination_terminal.id=opc.destination_terminal_id
					left join op_destination_terminal op_destination_local_terminal on op_destination_local_terminal.id=opc.destination_local_terminal_id
					left join op_destination_terminal op_return_terminal on op_return_terminal.id=opc.return_terminal_id
					left join op_destination_terminal op_return_local_terminal on op_return_local_terminal.id=opc.return_local_terminal_id
					left join op_container on op_container.id=opc.container
					left join users on users.username = opc.counted_by
				where opc.id in ({$row->container_ids}) and op_order_container.order_id={$row->id} $container_id
				group by opc.id
					ORDER BY id";
		        $containers = $dbr->getAll($q);
		        $main_order_id=0;
				foreach($containers as $k=>$dummy) {
					if(!$containers[$k]->real_master_id){
						$main_order_id=$containers[$k]->order_id;
					}else{
						$containers[$k]->main_order_id=$main_order_id;
					}
					$containers[$k]->planned_warehouse = utf8_decode($containers[$k]->planned_warehouse);
				}
				$smarty->assign('containers', $containers);
				if ($what=='container_id') $row->containers = $smarty->fetch('_op_containers_readonly.tpl');
				else $row->containers = $smarty->fetch('_op_containers_short.tpl');
                
                $row->containers_data = $containers;
			}
            $list[] = $row;
        }
        return $list;
    }

    static function getComments($db, $dbr, $id)
    {
        $r = $db->query("SELECT op_comment.*, IFNULL(users.name, op_comment.username) username_name
		, users.deleted, IF(users.name is null, 1, 0) olduser, op_comment.username cusername
			 from op_comment LEFT JOIN users ON op_comment.username = users.username
		where op_order_id=$id
		ORDER BY create_date");
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

    function addComment($db, $dbr, 
		$username,
		$create_date,
		$comment
		)
    {
        $op_order_id = (int)$this->data->id;
		$username = mysql_escape_string($username);
		$create_date = mysql_escape_string($create_date);
//		$comment = mysql_escape_string($comment); // we escape it in js_backend
        $r = $db->query("insert into op_comment set 
			op_order_id=$op_order_id, 
			username='$username',
			create_date='$create_date',
			comment='$comment'");
    }

    static function getDocs($db, $dbr, $op_order_id)
    {
        $r = $db->query("SELECT op_doc.*, tl.updated, IFNULL(u.name, tl.username) username
				from op_doc 
				left join total_log tl on op_doc.doc_id=tl.tableid and table_name='op_doc' and field_name='doc_id'
				left join users u on u.system_username=tl.username
				where op_order_id=$op_order_id ORDER BY doc_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
			$article->containers = $dbr->getAssoc("SELECT op_container_id, op_container_id
				from op_doc_container 
				where op_container_id in (select id from op_order_container where order_id=$op_order_id)
				and op_doc_id=".$article->doc_id);
            $list[] = $article;
        }
        return $list;
    }

    static function addDoc($db, $dbr, 
		$op_order_id,
		$name,
		$description,
		$data,
		$type
		)
    {
        $md5 = md5($data);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }
        
		$name = mysql_escape_string($name);
		$description = mysql_escape_string($description);
		$type = (int)$type;
		$q = "insert into op_doc set 
			op_order_id=$op_order_id, 
			name='$name',
			description='$description',
			type_id='$type',
			data='$md5'";
        
//		$data = base64_encode($data);
//		$data = mysql_escape_string($data);
//		$type = (int)$type;
//		$q = "insert into op_doc set 
//			op_order_id=$op_order_id, 
//			name='$name',
//			description='$description',
//			type_id='$type',
//			data='$data'";
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
			die();
        }
    }


    static function deleteDoc($db, $dbr, $doc_id)
    {
        $doc_id = (int)$doc_id;
        $r = $db->query("delete from op_doc where doc_id=$doc_id");
    }

    static function getCompanyDocs($db, $dbr, $company_id)
    {
        $r = $db->query("SELECT company_doc.company_id,
			company_doc.name,
			company_doc.doc_id,
			company_doc.description 
			from company_doc where company_id=$company_id ORDER BY doc_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addCompanyDoc($db, $dbr, $company_id,
		$name,
		$description,
		$data
		)
    {
		$name = mysql_escape_string($name);
		$description = mysql_escape_string($description);
        
        $md5 = md5($data);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }
        
        $r = $db->query("insert into company_doc set 
			company_id=$company_id, 
			name='$name',
			description='$description',
			data='$md5'");

//        $data = base64_encode($data);
//		$data = mysql_escape_string($data);
//        $r = $db->query("insert into company_doc set 
//			company_id=$company_id, 
//			name='$name',
//			description='$description',
//			data='$data'");
    }

    static function deleteCompanyDoc($db, $dbr, $doc_id)
    {
        $doc_id = (int)$doc_id;
        $r = $db->query("delete from company_doc where doc_id=$doc_id");
    }
}


class op_Auto
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
    function op_Auto($db, $dbr, $id = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Rma::Rma expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN op_auto");
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
            $r = $this->_db->query("SELECT * FROM op_auto WHERE id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("op_Auto : record $id does not exist");
                return;
            }
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
            $this->_error = PEAR::raiseError('Rma::update : no data');
        }
        foreach ($this->data as $field => $value) {
			{
	            if ($query) {
	                $query .= ', ';
	            }
	            if ($value!='' && $value!=NULL)
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
//		echo "$command op_auto SET $query $where";
        $r = $this->_db->query("$command op_auto SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
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
            $this->_error = PEAR::raiseError('Offer::update : no data');
        }
		$id = (int)$this->data->id;
        $r = $this->_db->query("DELETE FROM op_auto WHERE id=$id");
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
    static function listAll($db, $dbr, $mode, $category_id=NULL, $company_id=NULL, $rep='', $soldfromdate='0000-00-00', $soldtodate='9999-12-31', $wares=array())
    {
		global $supplier_filter;
		global $supplier_filter_str;
		if (strlen($supplier_filter))
			$supplier_filter_str1 = " and opc.id in ($supplier_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		if ($mode=='all')
			$where='';
		elseif ($mode=='active')
			$where=' AND opc.active';
		elseif ($mode=='passive')
			$where=' AND NOT opc.active';
		elseif ($mode=='current')
			$where=' AND (SELECT sum( opa.qnt_ordered ) - sum( opa.qnt_delivered ) 
				FROM op_order opo
				JOIN op_article opa ON opo.id = opa.op_order_id
				WHERE opa.article_id = a.article_id)>0 
				AND opc.active';
		elseif ($mode=='bycategory' && $category_id)
			$where=' AND a.category_id='.$category_id;
		elseif ($mode=='bysupplier' && $company_id)
			$where=' AND a.company_id='.$company_id;
	if ($soldfromdate!='0000-00-00' || $soldtodate!='9999-12-31') $where .= " and exists (select null from auction au 
		join orders o1 on o1.auction_number=au.auction_number and o1.txnid=au.txnid
		left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
			and total_log.tableid=o1.id and total_log.New_value=1
		where total_log.updated between '$soldfromdate' and '$soldtodate' and o1.article_id=a.article_id) ";		

	$where_rep='';
	if ($rep=='non-rep') {
		$where_rep=' AND a.deleted=0 and not exists (select null from article_rep where rep_id=a.article_id) ';
		$source = 'article';
		$article_ids = ' = a.article_id';
		$key = 'article_id';	
	} elseif ($rep=='rep') {
		$where_rep=' AND a.deleted=0 and exists (select null from article_rep where rep_id=a.article_id) ';
		$source = 'article';
		$article_ids = ' = a.article_id';
		$key = 'article_id';	
	} elseif ($rep=='eol') {
		$where_rep=' and a.deleted=1 ';
		$source = 'article';
		$article_ids = ' = a.article_id';
		$key = 'article_id';	
	} elseif ($rep=='cons') {
		$where_rep=' and a.deleted=0 ';
		$article_ids = ' in (select aa.article_id from article aa where aa.cons_id=a.article_id)';
		$source = "(SELECT 
			0 as take_real
			, 0 as ordering
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
			, 0 as is_rp
			from article_cons ac join article a1 on ac.article_id=a1.cons_id
			group by a1.cons_id)";
		$key = 'cons_id';	
		$force_key = ' force key (cons_id) ';
	}

		$warehouses = Warehouse::listArray($db, $dbr);
		$warehouses[0] = 'Total';
		$str1 = ''; $str2 = ''; $str3 = '';
//		print_r($wares); die();
		foreach ($warehouses as $id=>$warehouse) {
			if ($id && !in_array($id, $wares)) continue;
			$str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					join article a1 $force_key on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id and o.manual=0
					AND o.sent=0
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
						+(select IFNULL((select SUM(o.new_article_qnt)
					FROM (select * from orders where new_article_id is not null) o
					join article a1 $force_key on o.new_article_id=a1.article_id and a1.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id 
					AND o.sent=0
					AND o.new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0)) 
						+(select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					join article a1 $force_key on wwa.article_id=a1.article_id and a1.admin_id=0
					WHERE a1.$key = a.article_id 
					and not wwa.taken
					and (wwa.reserved_warehouse=$id or $id=0)),0)) 
						as reserved_$id
			,(select IFNULL((select sum(quantity) 
					from article_history 
					join article a1 $force_key on article_history.article_id=a1.article_id and a1.admin_id=0
					WHERE a1.$key = a.article_id and article_history.warehouse_id is not null
					and (article_history.warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(qnt_delivered) 
					from op_article 
					join article a1 $force_key on op_article.article_id=a1.article_id and a1.admin_id=0
						where a1.$key=a.article_id and add_to_warehouse
						and (op_article.warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					left JOIN warehouse w ON w.default=1
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.problem_id in (4,11) and a1.$key = a.article_id
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					and a1.$key = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and a1.$key = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and a1.$key = a.article_id and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 $force_key on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					and a1.$key = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
					) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					join article a1 $force_key on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id and o.manual=0
					AND o.sent
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
						and total_log.tableid=o.id and total_log.New_value=1
					join article a1 $force_key on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id and o.manual=0
					AND total_log.updated >= DATE_SUB(NOW(), INTERVAL 
					".((int)Config::get($db, $dbr, 'items_sold_per_xx_months')*30)." DAY)
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold90_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					and a1.$key = a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_taken_in_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					and a1.$key = a.article_id
				and (wwo_article.from_warehouse=$id or $id=0)),0)) as driver_taken_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					and a1.$key = a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_delivered_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 $force_key ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					and a1.$key = a.article_id
				and (wwo_article.to_warehouse=$id or $id=0)),0)) as driver_delivered_in_$id
			,
			(select IFNULL((select sum(ats_item.quantity)
				from ats_item
				join ats on ats.id=ats_item.ats_id
				JOIN article aa ON ats_item.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked
					and aa.article_id=a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0))
			-(select IFNULL((select sum(ats.quantity)
				from ats 
					JOIN article aa ON ats.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked
					and aa.article_id=a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_$id
			, (select IFNULL((select SUM(o.new_article_qnt)
					FROM orders o
					JOIN article aa $force_key ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					and aa.article_id=a.article_id
		#			AND not o.sent
					and o.new_article and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
					- (select IFNULL((select SUM(1)
					FROM (select * from orders where lost_new_article=1) o
					JOIN article aa $force_key ON o.new_article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					and aa.article_id=a.article_id
					and o.new_article=1 and o.lost_new_article=1 and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
							as newarticle_$id
			";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id + ats_$id + newarticle_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id-reserved_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id + ats_$id + newarticle_$id as available_$id";
		}

        $script = basename($_SERVER['SCRIPT_FILENAME']);
		$q = "select /* $script -> op_Auto::getArticles */ t.*
				$str2
				from (
			SELECT oa.id
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
			, t_article_name.value as article_short_name
			, CONCAT(a.article_id, ': ', 
				(SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id)
				) as article_name
			, a.article_id
			, a.volume as article_volume
			, a.volume_per_single_unit as article_volume_per_single_unit
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id $article_ids
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id $article_ids
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2 
				and (not opa.add_to_warehouse or opa.add_to_warehouse is null)
				), 0) as order_on_way_qnt
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
			FROM $source a
			LEFT JOIN translation t_article_name ON t_article_name.table_name = 'article'
				AND t_article_name.field_name = 'name'
				AND t_article_name.language = 'german'
				AND t_article_name.id = a.article_id
			LEFT JOIN op_company opc ON a.company_id=opc.id
			LEFT JOIN op_company_category opcat ON a.category_id=opcat.id
			LEFT JOIN op_auto oa ON a.article_id=oa.article_id
			LEFT JOIN op_container oct on oa.container_id=oct.id
			WHERE NOT a.admin_id $supplier_filter_str1
			$where $where_rep
			)t 
			order by company_id, category_id, article_id";
//			echo $q."<br>";die();
		file_put_contents('last_itakka', $q);
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        return $r;
    }

/*    function listAutoArticles($db, $dbr, $company_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $r = $db->query("SELECT 
			a.article_id
			, a.name as article_name
			, a.volume as article_volume
			, a.pieces as article_in_stock
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)<2
				), 0) as order_in_prod_qnt
			, IFNULL((select sum(opa.qnt_ordered) from op_article opa where opa.article_id=a.article_id 
				and (select count(*) from op_payment opp where opp.op_order_id=opa.op_order_id)>=2
				and (not opa.add_to_warehouse or opa.add_to_warehouse is null)
				), 0) as order_on_way_qnt
			, a.desired_daily as sales_per_day
			, oc.period
			, a.desired_daily
		FROM article a 
		JOIN op_company oc on a.company_id=oc.id
		WHERE a.company_id=$company_id
		order by a.article_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($row = $r->fetchRow()) {
            $list[] = $row;
        }
        return $list;
     }
*/

    static function listByOffer($db, $dbr, $offer_id)
    {
		if (!$offer_id) return;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$key = 'article_id';
		$warehouses = Warehouse::listArray($db, $dbr);
		$warehouses[0] = 'Total';
		$str1 = ''; $str2 = ''; $str3 = '';
		foreach ($warehouses as $id=>$warehouse) {
			$str1 .= ", (select IFNULL((select SUM(o.quantity)
					FROM orders o
					join article a1 on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id and o.manual=0
					AND o.sent=0
					AND au.deleted = 0 and (o.reserve_warehouse_id=$id or $id=0)),0)) 
						+ (select IFNULL((select SUM(o.new_article_qnt)
					FROM (select * from orders where new_article_id is not null) o
					join article a1 on o.new_article_id=a1.article_id and a1.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE o.new_article_id = a.article_id 
					AND o.new_article and NOT o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					and not o.lost_new_article 
#					AND o.sent=0
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
						+ (select IFNULL((select SUM(wwa.qnt)
					FROM wwo_article wwa
					join article a1 on wwa.article_id=a1.article_id and a1.admin_id=0
					WHERE a1.$key = a.article_id 
					AND not wwa.taken
					AND (wwa.reserved_warehouse=$id or $id=0)),0))
						as reserved_$id
			,(select IFNULL((select sum(quantity) 
					from article_history 
					join article a1 on article_history.article_id=a1.article_id and a1.admin_id=0
					WHERE a1.$key = a.article_id and article_history.warehouse_id is not null
					and (article_history.warehouse_id=$id or $id=0)),0)) as inventar_$id
			,(select IFNULL((select sum(qnt_delivered) 
					from op_article 
					join article a1 on op_article.article_id=a1.article_id and a1.admin_id=0
						where a1.$key=a.article_id and add_to_warehouse
						and (op_article.warehouse_id=$id or $id=0)),0)) as order_$id
			,((select IFNULL((SELECT count(*) as quantity
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					left JOIN warehouse w ON w.default=1
					LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
					left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
					join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.problem_id in (4,11) and a1.$key = a.article_id
					and (IFNULL(auw.warehouse_id, w.warehouse_id)=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1 
					and a1.$key = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			-
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and a1.$key = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.add_to_stock = 1 and rs.back_wrong_delivery=1 
					and a1.$key = a.article_id and (rs.returned_warehouse_id=$id or $id=0)),0))
			+
			(select IFNULL((SELECT count(*)
					FROM rma r
					JOIN rma_spec rs ON r.rma_id=rs.rma_id
					join article a1 on rs.article_id=a1.article_id and a1.admin_id=0
					WHERE rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0 
					and a1.$key = a.article_id and (rs.warehouse_id=$id or $id=0)),0))
					) as rma_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					join article a1 on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id and o.manual=0
					AND o.sent
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold_$id
			,(select IFNULL((select SUM(o.quantity)
					FROM orders o
					left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
						and total_log.tableid=o.id and total_log.New_value=1
					join article a1 on o.article_id=a1.article_id and o.manual=a1.admin_id
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE a1.$key = a.article_id and o.manual=0
					AND total_log.updated >= DATE_SUB(NOW(), INTERVAL 
					".((int)Config::get($db, $dbr, 'items_sold_per_xx_months')*30)." DAY)
					AND au.deleted = 0 and (o.send_warehouse_id=$id or $id=0)),0)) as sold90_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken
					and a1.$key = a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_taken_in_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.taken and wwo_article.taken_not_deducted=0
					and a1.$key = a.article_id
				and (wwo_article.from_warehouse=$id or $id=0)),0)) as driver_taken_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered
					and a1.$key = a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0)) as driver_delivered_out_$id
			,(select IFNULL((select sum(wwo_article.qnt)
				from wwo_article
				join ww_order on ww_order.id=wwo_article.wwo_id
					JOIN article a1 ON wwo_article.article_id=a1.article_id and a1.admin_id=0
				LEFT JOIN users driver_users ON ww_order.driver_username=driver_users.username 
				left JOIN warehouse ON driver_users.driver_warehouse_id = warehouse.warehouse_id
				where wwo_article.delivered and wwo_article.delivered_not_added=0
					and a1.$key = a.article_id
				and (wwo_article.to_warehouse=$id or $id=0)),0)) as driver_delivered_in_$id
			,
			(select IFNULL((select sum(ats_item.quantity)
				from ats_item
				join ats on ats.id=ats_item.ats_id
				JOIN article aa ON ats_item.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked
					and aa.article_id=a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0))
			-(select IFNULL((select sum(ats.quantity)
				from ats 
					JOIN article aa ON ats.article_id=aa.article_id and aa.admin_id=0
				left JOIN warehouse ON ats.warehouse_id = warehouse.warehouse_id
				where ats.booked
					and aa.article_id=a.article_id
				and (warehouse.warehouse_id=$id or $id=0)),0)) as ats_$id
			, (select IFNULL((select SUM(o.new_article_qnt)
					FROM orders o
					JOIN article aa $force_key ON o.article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					and aa.article_id=a.article_id
		#			AND not o.sent
					and o.new_article and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
					- (select IFNULL((select SUM(1)
					FROM (select * from orders where lost_new_article=1) o
					JOIN article aa $force_key ON o.new_article_id=aa.article_id and aa.admin_id=0
					JOIN auction au ON o.auction_number = au.auction_number
					AND o.txnid = au.txnid
					WHERE 1 $stock_date_new
					and aa.article_id=a.article_id
					and o.new_article=1 and o.lost_new_article=1 and not o.new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
					AND au.deleted = 0 and (o.new_article_warehouse_id=$id or $id=0)),0))
							as newarticle_$id
			";
			$str2 .= ", inventar_$id+order_$id+rma_$id-sold_$id
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id + ats_$id + newarticle_$id as pieces_$id
			, inventar_$id+order_$id+rma_$id-sold_$id-reserved_$id 
				+driver_taken_in_$id-driver_taken_out_$id+driver_delivered_in_$id-driver_delivered_out_$id + ats_$id + newarticle_$id as available_$id";
		}

        $script = basename($_SERVER['SCRIPT_FILENAME']);
		$q = "select /* $script -> op_Auto::listByOffer */ t.*
				$str2
				from (
			SELECT a.*
				 $str1
			FROM (
			SELECT al.inactive, a.cons_id, 0 AS grouphead, og.position ogposition, og.offer_group_id, NULL AS title, 
			NULL AS description, og.main as main, og.additional as additional, oa.id, opc.active, 
			a.take_real, a.supplier_article_id, oa.quantity_to_order, a.company_id, opc.name AS supplier_name, 
			CONCAT( a.article_id, ': ', (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) ) AS article_name, a.article_id, a.volume AS article_volume, 
			a.volume_per_single_unit AS article_volume_per_single_unit, 
			-999 AS article_in_stock, 
			-999 AS article_reserved, 
			IFNULL( (
				SELECT sum( opa.qnt_ordered ) 
				FROM op_article opa
				WHERE opa.article_id = a.article_id
				AND (
					SELECT count( * ) 
					FROM op_payment opp
					WHERE opp.op_order_id = opa.op_order_id ) <2
			), 0) AS order_in_prod_qnt, 
			IFNULL( (
				SELECT sum( opa.qnt_ordered ) 
				FROM op_article opa
				WHERE opa.article_id = a.article_id
				AND (
					SELECT count( * ) 
					FROM op_payment opp
					WHERE opp.op_order_id = opa.op_order_id ) >=2
					AND (
					NOT opa.add_to_warehouse
					OR opa.add_to_warehouse IS NULL 
					)
			), 0) AS order_on_way_qnt, 
			a.desired_daily AS sales_per_day, oa.container_id AS container_id, oct.name AS container, 
			oct.volume AS container_volume, a.desired_daily, opc.period, 
			(SELECT count( * ) FROM article_rep WHERE rep_id = a.article_id) AS is_rp
			,IFNULL((SELECT min( opc.eda ) 
				FROM op_article opa 
				JOIN op_order opo ON opa.op_order_id = opo.id
				LEFT JOIN op_order_container opc ON opc.order_id = opo.id 
				WHERE opc.eda >= now( ) and article_id  = a.article_id 
				and (opc.arrival_date='0000-00-00' or opc.arrival_date is null))
				, (SELECT max( opc.eda ) FROM op_article opa 
				JOIN op_order opo ON opa.op_order_id = opo.id
				LEFT JOIN op_order_container opc ON opc.order_id = opo.id 
				WHERE opc.eda < now( ) and article_id  = a.article_id 
				and (opc.arrival_date='0000-00-00' or opc.arrival_date is null))) 
			as mineda
		FROM article a
			JOIN article_list al ON a.article_id = al.article_id AND NOT admin_id
			JOIN offer_group og ON og.offer_group_id = al.group_id AND og.offer_id =$offer_id and not og.additional and not og.base_group_id # last 2 conditions were added on 3.2.2015 for newauction bottom block
			LEFT JOIN op_company opc ON a.company_id = opc.id
			LEFT JOIN op_company_category opcat ON a.category_id = opcat.id
			LEFT JOIN op_auto oa ON a.article_id = oa.article_id
			LEFT JOIN op_container oct ON oa.container_id = oct.id
		WHERE NOT a.admin_id
		AND NOT a.deleted
	UNION ALL SELECT DISTINCT 
		0, NULL, 1 AS grouphead, og.position ogposition, og.offer_group_id, og.title, og.description, 
		og.main, og.additional, NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , 
		NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL , NULL, NULL 
		FROM article a
			JOIN article_list al ON a.article_id = al.article_id AND NOT admin_id
			JOIN offer_group og ON og.offer_group_id = al.group_id AND offer_id =$offer_id
			LEFT JOIN op_company opc ON a.company_id = opc.id
			LEFT JOIN op_company_category opcat ON a.category_id = opcat.id
			LEFT JOIN op_auto oa ON a.article_id = oa.article_id
			LEFT JOIN op_container oct ON oa.container_id = oct.id
			WHERE NOT a.admin_id
			AND NOT a.deleted
	)a ) t
		ORDER BY ogposition, offer_group_id, grouphead DESC";
//		echo $q; die();
		file_put_contents('last_itakka', $q);
        
		$r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        
		file_put_contents("lastAJAXOPO.txt", date('Y-m-d H:i:s').' '.$_SERVER[HTTP_HOST].$_SERVER['PHP_SELF'].' : '.$q."\n", FILE_APPEND);
        return $r;
    }

static function formatInventory($db, $dbr, $company_id)
{
require_once 'lib/ArticleHistory.php';
		$tmp = 'tmp';
	$filename = 'InventoryStatus.pdf';
        $pdf = &File_PDF::factory('L', 'cm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(false);
	$y = 1; $y_max = 19;
	$pdf->addPage();

	$reserve = (int)Config::get($db, $dbr, 'op_reserve');
	$autos = op_Auto::listAll($db, $dbr, 'bysupplier', NULL, $company_id, 'non-rep');
	$sups = op_Order::listCompaniesAll($db, $dbr, NULL, 'active');
	if (count($autos)) foreach ($autos as $id => $line) {
		if (($autos[$id]->desired_daily < 0)) {
		   $autos[$id]->sales_per_day = $autos[$id]->sales_in_period = 'STOP';
		} else {
			$autos[$id]->sales_per_day =  max(((int)(ArticleHistory::getSoldCountMonth($db, $dbr, $autos[$id]->article_id, 1)/0.3)/100), $autos[$id]->desired_daily);
			$autos[$id]->sales_in_period = $autos[$id]->sales_per_day*$autos[$id]->period;
		}	
		if ($autos[$id]->sales_per_day == 'STOP') $autos[$id]->quantity_to_order = 0;
		else
		  $autos[$id]->quantity_to_order = ($autos[$id]->sales_per_day * ($autos[$id]->period+$reserve)) 
			- ($autos[$id]->available_0
			+ $autos[$id]->order_in_prod_qnt
			+ $autos[$id]->order_on_way_qnt);
		if ($autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_color'); 
		elseif ($autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_noitems_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_noitems_color'); 
		$autos[$id]->volume_to_order = $autos[$id]->quantity_to_order * $autos[$id]->article_volume_per_single_unit;
		if ($autos[$id]->company_id && ($autos[$id]->volume_to_order>0)) {
			foreach ($sups as $idsup=>$sup) {
				if ($sups[$idsup]->id == $autos[$id]->company_id) {
					$sups[$idsup]->volume_to_order += $autos[$id]->volume_to_order;
				}	
			}
		}	
	}
	$pdf->setFont('arial','B', 12); $pdf->text(1, $y, $dbr->getOne("select name from op_company where id=$company_id")); 
	$y +=1;
	$pdf->line(1, $y, 27, $y);
        $pdf->setFont('arial','B', 9);
        $pdf->setXY(1, $y); $pdf->multiCell(6, 0.5, 'Supplier Article #', 0, 'L');
        $pdf->setXY(7, $y); $pdf->multiCell(2, 0.5, 'Last Months volume', 0, 'L');
        $pdf->setXY(9, $y); $pdf->multiCell(2, 0.5, 'In Stock', 0, 'L');	
        $pdf->setXY(11, $y); $pdf->multiCell(2, 0.5, 'Available', 0, 'L');	
        $pdf->setXY(13, $y); $pdf->multiCell(2, 0.5, 'Reserved', 0, 'L');	
        $pdf->setXY(15, $y); $pdf->multiCell(2, 0.5, 'Orders in prod.', 0, 'L');
        $pdf->setXY(17, $y); $pdf->multiCell(2, 0.5, 'Orders on the way', 0, 'L');	
        $pdf->setXY(19, $y); $pdf->multiCell(2, 0.5, 'Next EDA', 0, 'L');	
        $pdf->setXY(21, $y); $pdf->multiCell(2, 0.5, 'Sales per day', 0, 'L');	
        $pdf->setXY(23, $y); $pdf->multiCell(2, 0.5, 'Sales in period', 0, 'L');	
        $pdf->setXY(25, $y); $pdf->multiCell(2, 0.5, 'Quantity to order', 0, 'L');
		$y +=1.5;
	$pdf->line(1, $y, 27, $y);

    foreach ($autos as $aline) {
    	    if ($y > $y_max) {
    	$y = 1;    
	$pdf->addPage();
	$pdf->line(1, $y, 27, $y);
        $pdf->setFont('arial','B', 9);
        $pdf->setXY(1, $y); $pdf->multiCell(6, 0.5, 'Supplier Article #', 0, 'L');
        $pdf->setXY(7, $y); $pdf->multiCell(2, 0.5, 'Last Months volume', 0, 'L');
        $pdf->setXY(9, $y); $pdf->multiCell(2, 0.5, 'In Stock', 0, 'L');	
        $pdf->setXY(11, $y); $pdf->multiCell(2, 0.5, 'Available', 0, 'L');	
        $pdf->setXY(13, $y); $pdf->multiCell(2, 0.5, 'Reserved', 0, 'L');	
        $pdf->setXY(15, $y); $pdf->multiCell(2, 0.5, 'Orders in prod.', 0, 'L');
        $pdf->setXY(17, $y); $pdf->multiCell(2, 0.5, 'Orders on the way', 0, 'L');	
        $pdf->setXY(19, $y); $pdf->multiCell(2, 0.5, 'Next EDA', 0, 'L');	
        $pdf->setXY(21, $y); $pdf->multiCell(2, 0.5, 'Sales per day', 0, 'L');	
        $pdf->setXY(23, $y); $pdf->multiCell(2, 0.5, 'Sales in period', 0, 'L');	
        $pdf->setXY(25, $y); $pdf->multiCell(2, 0.5, 'Quantity to order', 0, 'L');
		$y +=1.5;
	$pdf->line(1, $y, 27, $y);
    	    }
        $pdf->setXY(1, $y); $pdf->multiCell(6, 0.5, $aline->supplier_article_id, 0, 'L');
        $pdf->setXY(7, $y); $pdf->multiCell(2, 0.5, $aline->sold90_0, 0, 'L');
        $pdf->setXY(9, $y); $pdf->multiCell(2, 0.5, $aline->pieces_0, 0, 'L');
        $pdf->setXY(11, $y); $pdf->multiCell(2, 0.5, $aline->available_0, 0, 'L');
        $pdf->setXY(13, $y); $pdf->multiCell(2, 0.5, $aline->reserved_0, 0, 'L');
        $pdf->setXY(15, $y); $pdf->multiCell(2, 0.5, $aline->order_in_prod_qnt, 0, 'L');
        $pdf->setXY(17, $y); $pdf->multiCell(2, 0.5, $aline->order_on_way_qnt, 0, 'L');
        $pdf->setXY(19, $y); $pdf->multiCell(2, 0.5, $aline->mineda, 0, 'L');
        $pdf->setXY(21, $y); $pdf->multiCell(2, 0.5, $aline->sales_per_day, 0, 'L');
        $pdf->setXY(23, $y); $pdf->multiCell(2, 0.5, $aline->sales_in_period, 0, 'L');
        $pdf->setXY(25, $y); $pdf->multiCell(2, 0.5, $aline->quantity_to_order, 0, 'L');
	$pdf->newLine();
	$y = $pdf->getY();
    }

	$autos = op_Auto::listAll($db, $dbr, 'bysupplier', NULL, $company_id, 'rep');
	if (count($autos)) foreach ($autos as $id => $line) {
		if ($autos[$id]->desired_daily < 0) $autos[$id]->sales_per_day = 'STOP';
		else
			$autos[$id]->sales_per_day =  max(((int)(ArticleHistory::getSoldCountMonth($db, $dbr, $autos[$id]->article_id, 1)/0.3)/100), $autos[$id]->desired_daily);
		$autos[$id]->sales_in_period = $autos[$id]->sales_per_day*$autos[$id]->period;
		  $autos[$id]->quantity_to_order = ($autos[$id]->sales_per_day * ($autos[$id]->period+$reserve)) 
			- ($autos[$id]->available_0
			+ $autos[$id]->order_in_prod_qnt
			+ $autos[$id]->order_on_way_qnt);
		if ($autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_color'); 
		elseif ($autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_noitems_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_noitems_color'); 
		$autos[$id]->volume_to_order = $autos[$id]->quantity_to_order * $autos[$id]->article_volume_per_single_unit;
		if ($autos[$id]->company_id && ($autos[$id]->volume_to_order>0)) {
			foreach ($sups as $idsup=>$sup) {
				if ($sups[$idsup]->id == $autos[$id]->company_id) {
					$sups[$idsup]->volume_to_order += $autos[$id]->volume_to_order;
				}	
			}
		}	
	}
        $pdf->multiCell(25, 0.5, 'Replacement parts:', 0, 'L');
	$y += 0.5;
	$pdf->line(1, $y, 27, $y);
    foreach ($autos as $aline) {
    	    if ($y > $y_max) {
    	$y = 1;    
	$pdf->addPage();
	$pdf->line(1, $y, 27, $y);
        $pdf->setFont('arial','B', 9);
        $pdf->setXY(1, $y); $pdf->multiCell(6, 0.5, 'Supplier Article #', 0, 'L');
        $pdf->setXY(7, $y); $pdf->multiCell(2, 0.5, 'Last Months volume', 0, 'L');
        $pdf->setXY(9, $y); $pdf->multiCell(2, 0.5, 'In Stock', 0, 'L');	
        $pdf->setXY(11, $y); $pdf->multiCell(2, 0.5, 'Available', 0, 'L');	
        $pdf->setXY(13, $y); $pdf->multiCell(2, 0.5, 'Reserved', 0, 'L');	
        $pdf->setXY(15, $y); $pdf->multiCell(2, 0.5, 'Orders in prod.', 0, 'L');
        $pdf->setXY(17, $y); $pdf->multiCell(2, 0.5, 'Orders on the way', 0, 'L');	
        $pdf->setXY(19, $y); $pdf->multiCell(2, 0.5, 'Next EDA', 0, 'L');	
        $pdf->setXY(21, $y); $pdf->multiCell(2, 0.5, 'Sales per day', 0, 'L');	
        $pdf->setXY(23, $y); $pdf->multiCell(2, 0.5, 'Sales in period', 0, 'L');	
        $pdf->setXY(25, $y); $pdf->multiCell(2, 0.5, 'Quantity to order', 0, 'L');
		$y +=1.5;
	$pdf->line(1, $y, 27, $y);
    	    }
        $pdf->setXY(1, $y); $pdf->multiCell(6, 0.5, $aline->supplier_article_id, 0, 'L');
        $pdf->setXY(7, $y); $pdf->multiCell(2, 0.5, $aline->sold90_0, 0, 'L');
        $pdf->setXY(9, $y); $pdf->multiCell(2, 0.5, $aline->pieces_0, 0, 'L');
        $pdf->setXY(11, $y); $pdf->multiCell(2, 0.5, $aline->available_0, 0, 'L');
        $pdf->setXY(13, $y); $pdf->multiCell(2, 0.5, $aline->reserved_0, 0, 'L');
        $pdf->setXY(15, $y); $pdf->multiCell(2, 0.5, $aline->order_in_prod_qnt, 0, 'L');
        $pdf->setXY(17, $y); $pdf->multiCell(2, 0.5, $aline->order_on_way_qnt, 0, 'L');
        $pdf->setXY(19, $y); $pdf->multiCell(2, 0.5, $aline->mineda, 0, 'L');
        $pdf->setXY(21, $y); $pdf->multiCell(2, 0.5, $aline->sales_per_day, 0, 'L');
        $pdf->setXY(23, $y); $pdf->multiCell(2, 0.5, $aline->sales_in_period, 0, 'L');
        $pdf->setXY(25, $y); $pdf->multiCell(2, 0.5, $aline->quantity_to_order, 0, 'L');
	$pdf->newLine();
	$y = $pdf->getY();
    }
    	    $pdf->close();
//	    $pdf->save($tmp . '/' . $filename, true);
//		$static = file_get_contents($tmp . '/' . $filename);
//		unlink($tmp . '/' . $filename);
    return $pdf->getOutput();
   }

}
?>