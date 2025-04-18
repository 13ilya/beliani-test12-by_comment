<?php
require_once 'PEAR.php';
require_once 'config.php';
require_once 'util.php';

 /**
  * Barcode operation class
  *
  * Contains methods related to barcodes generation, check existing barcodes, retrieving basic and extended data
  * 
  * @author AlexJJ <alex@lingvo.biz>
  * 
  * @version 0.1
  * 
  * @param string $id barcode real ID (mysql database barcode or vbarcode id column value)
  *
  * @param string $data barcode basic data (compatibility)
  *
  * @param string $vdata barcode extended data (vbarcode table contents)
  *
  * @param string $_db database write/ read object identifier
  *
  * @param string $_dbr database read (only) object identifier
  *
  * @param string $_error contains error reports, concerning both mySQL and PHP execution
  *
  * @return void
  */

class Barcode
{
	public $id;
	public $data;
	public $vdata;
	protected $_db;
	protected $_dbr;
	public $_error;
	
    function __construct($db, $dbr, $barcode=false){
        if (!is_a($db, 'MDB2_Driver_mysql')){
            $this->_error = PEAR::raiseError('Barcode::Barcode expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        
		$this->_db = $db;
		$this->_dbr = $dbr;
        
		if (!$barcode){
            $r = $this->_db->query("EXPLAIN barcode");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()){
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        }
		else {
			$this->get_id($barcode);
        	
        	if ($this->id) {
	            $r = $this->_db->query("SELECT * FROM barcode WHERE id=".$this->id.";");
    	        if (PEAR::isError($r)){
        	        $this->_error = $r;
            	    return;
	            }
    	        $this->data = $r->fetchRow();
        	    if (!$this->data){
					$this->_error = PEAR::raiseError("Barcode::Barcode : the record $barcode does not exist");
					return;
				}
			}
			else {
				//$this->_error assigned previously in get_id($barcode)
				return;
			}
            $this->_isNew = false;
        }
    }
	
 /**
  * Returns real barcode ID (mySQL table id field).
  *
  * @param $barcode Initial complete barcode (00000x/0000x/00000x) OR barcode id (1-99999x), does not matter.
  *
  * @return int Returns the barcode ID.
  */
	function get_id($barcode){
       	$barcode = str_replace(array('\\',' '),array('/',''),$barcode);
		if (strpos($barcode,'/') !== false){
			$barcode = explode('/', $barcode);
			$barcode_id = $barcode[2];
			
        	if (!$barcode_id || !is_numeric($barcode_id)){
				$this->_error = PEAR::raiseError("Barcode::get_id : wrong identifier after barcode parsing");
				return;
       		}
       		else {
				$this->id = $barcode_id;
				$this->get_vdata();
        
        if ($barcode[1] == 0) {
          $barcode[1] = $this->vdata->article_id;
        }
				
				if (!$this->vdata->id){
					$this->_error = PEAR::raiseError("Barcode::get_id : wrong or unexisting barcode");
				}
				elseif (strlen($this->vdata->code) && $barcode[0].'/'.$barcode[1].'/' !== $this->vdata->code){
					$this->_error = PEAR::raiseError("Barcode::get_id : barcode parts mismatch");
					$this->id = false;
					return;
				}
			}
       	}
       	elseif (is_numeric($barcode)) {
			$this->id = (int)$barcode;
			$this->get_vdata();
			if (!is_object($this->vdata)){
				$this->_error = PEAR::raiseError("Barcode::get_id : barcode does not exist");
				$this->id = false;
			}
		}
       	else {
			$this->_error = PEAR::raiseError("Barcode::get_id : wrong identifier");
			return;
  		}
	}
	
 /**
  * Returns extended barcode parameters from mySQL vbarcode table.
  *
  * @return object collection of barcode parameters in $this->vdata variable.
  */
	function get_vdata(){
		global $dbr_spec;
		$dbr = $this->_dbr;
		
      	$vdata = $dbr_spec->getRow("SELECT * FROM vbarcode WHERE id=".$this->id.";");
			
		if (PEAR::isError($vdata)) {
			$this->_error = PEAR::raiseError($vdata);
			return;
		}
      	else $this->vdata = $vdata;
	}
	
 /**
  * Set a value for a specific field in barcode mySQL table
  *
  * @param $field mySQL table (barcode) field to be modified.
  *
  * @param $value mySQL table (barcode) field new value.
  *
  */
	function set($field, $value){
		if (isset($this->data->$field)) $this->data->$field = $value;
		if (isset($this->vdata->$field)) $this->vdata->$field = $value;
	}
	
 /**
  * Get the value of specific field in barcode mySQL table
  *
  * @param $field mySQL table (barcode) field to read.
  *
  */
	function get($field){
		if (isset($this->vdata->$field)) return $this->vdata->$field;
		elseif (isset($this->data->$field)) return $this->data->$field;
		else return null;
	}

 /**
  * Updates in batch mode the barcode mySQL table
  *
  * @return object results of PEAR::query.
  */
	function update(){
		if ($this->isNew){
        	if (!is_object($this->data)) $this->_error = PEAR::raiseError('Barcode::update : no data');
			
	        $query = '';
			foreach ($this->data as $field => $value) {
				$query .= "`$field`='".mysql_escape_string($value)."'";
			}
        	
			//if ($this->_isNew) $q = "INSERT INTO barcode SET $query;";
    	    //else $q = "UPDATE barcode SET $query WHERE id='".$this->id."';";
			$q = "INSERT INTO barcode SET $query;";
			
			$r = $this->_db->query($q);
			if (PEAR::isError($r)) {
				$this->_error = $r;
				aprint_r($r);
				die();
    	    }
    	    else {
    	    	$id = $this->_dbr->query("SELECT last_insert_id();");
    	    	
    	    	if (PEAR::isError($id) == false && $id>0) {
    	    		$this->id = $id;
					$origin_id = $this->_dbr->getOne("SELECT origin_id FROM barcode WHERE id=$id AND article_id=".$this->data->article_id." AND origin_key=".$this->data->origin_key.";");
					if (PEAR::isError($origin_id) == false && $origin_id>0){
						$origin_code = $this->_dbr->getOne("SELECT code FROM barcode_origin WHERE id=$origin_id;");
						if (PEAR::isError($origin_code) == false && strlen($origin_code)>0){
							$barcode_object = $this->_dbr->query("INSERT INTO barcode_object SET barcode_id=$id, obj_id=".$this->data->origin_key.", obj='".$origin_code."';");
							if (PEAR::isError($barcode_object) !== false){
								$this->_error = PEAR::raiseError('Barcode::create : barcode_object creation error');
							}
						}
						else {
    		    			$this->_error = PEAR::raiseError('Barcode::create : cannot obtain $origin_code');
    		    		}
					}
					else {
    	    			$this->_error = PEAR::raiseError('Barcode::create : returned ID/ barcode mismatch or creation error'); // на самом деле проверка правильности ID
    	    		}
    	    	}
    	    	else {
    	    		$this->_error = PEAR::raiseError('Barcode::create : no ID returned');
    	    	}
    	    }
        	return $r;
        }
        else {
        	$this->_error = PEAR::raiseError('Barcode::update : cannot create barcode if $barcode has been previously set');
        }
    }

    static function listAll($db, $dbr){
      /* Table for barcode warehouse, if use denormalization - barcode_dn */
      $vbw = 'vbarcode_warehouse';
      if ($GLOBALS['CONFIGURATION']['use_dn']) $vbw = 'barcode_dn';

		  $q1 = "select b.*, bw.last_warehouse_id, w1.name last_warehouse
  			, bw.state2filter, bw.state state1
  		from vbarcode b
  			left join {$vbw} bw on b.id=bw.id
  			left join warehouse w1 on w1.warehouse_id=bw.last_warehouse_id
			where 1
			$where";
        $list = $dbr->getAll($q1);
        return $list;
    }

    static function listAllParams($db, $dbr, $filter){
      /* Table for barcode warehouse, if use denormalization - barcode_dn */
      $vbw = 'vbarcode_warehouse';
      if ($GLOBALS['CONFIGURATION']['use_dn']) $vbw = 'barcode_dn';

  		if ((int)($filter['opa_id'])) {
  			$where .= " and b.barcode_object_obj_id = ".(int)($filter['opa_id']);
  		}
  		if ((int)($filter['manual'])) {
  			$where .= " and b.barcode_object_obj_id = ".(int)($filter['manual']);
  		}
  		if ((int)($filter['op_order_id'])) {
  			$where .= " and b.op_order_id = ".(int)($filter['op_order_id']);
  		}
  		if (strlen($filter['container_no'])) {
  			$where .= " and b.container_no = '".mysql_escape_string($filter['container_no'])."'";
  		}
  		if (strlen($filter['article_id']) && strlen($_GET['article_label'])) {
        if ($GLOBALS['CONFIGURATION']['use_dn']) {
          $where .= " and bw.article_id = ".(int)$filter['article_id'];
        }else{
          $where .= " and b.article_id = ".(int)$filter['article_id'];
        }
  			$filter['article'] = $filter['article_id'].': '
  				.$dbr->getOne("select value from translation where table_name='article' and field_name='name' 
  				and id='".mysql_escape_string($filter['article_id'])."' and language='german'");
  		} else {
  			$filter['article'] = '';
  		}
  		if (strlen($filter['code'])) {
  			$where .= " and CONCAT(b.barcode_object_obj_id,'/',b.article_id,'/',b.id) = '".mysql_escape_string($filter['code'])."'";
  		}
  		if (strlen($filter['created_on_from']) && strlen($filter['created_on_to'])) {
  			$ids = $dbr->getOne("select group_concat(tableid) from total_log where table_name='barcode' and field_name='id' 
  				and date(updated) between '".$filter['created_on_from']."' and '".$filter['created_on_to']."'");
  			if (!strlen($ids)) $ids=0;
  			$where .= " and b.id in ($ids)";
  		} else {
  			if (strlen($filter['created_on_from'])) {
  				$where .= " and date(b.created_on)>= '".mysql_escape_string($filter['created_on_from'])."'";
  			}
  			if (strlen($filter['created_on_to'])) {
  				$where .= " and date(b.created_on)<= '".mysql_escape_string($filter['created_on_to'])."'";
  			}
  		}
  		if (strlen($filter['created_by'])) {
  			$where .= " and b.created_by_username = '".mysql_escape_string($filter['created_by'])."'";
  		}
  		if (strlen($filter['inactive'])) {
  			$where .= " and b.inactive=".$filter['inactive'];
  		}
  		if ((int)($filter['barcode_object_obj_id'])) {
  			$where .= " and b.barcode_object_obj_id = ".(int)($filter['barcode_object_obj_id']);
  		}
  		if ((int)($filter['last_ware'])) {
  			$where .= " and bw.last_warehouse_id = ".(int)($filter['last_ware']);
  		}
  		if (count($filter['state'])) {
  			$where .= " and bw.state2filter in ('".implode("','",$filter['state'])."')";
  		}
  		if ($filter['inout']>0) {
  			$where .= " and bw.state2filter in ('".implode("','",array_keys($statesIN))."')";
  		}
  		if ($filter['inout']<0) {
  			$where .= " and bw.state2filter in ('".implode("','",array_keys($statesOUT))."')";
  		}
  		$q1 = "select b.*, bw.last_warehouse_id, w1.name last_warehouse
  			, bw.state2filter, bw.state state1
  			from vbarcode b
  			left join {$vbw} bw on b.id=bw.id
  			left join warehouse w1 on w1.warehouse_id=bw.last_warehouse_id
  			where 1
  			$where";
      $list = $dbr->getAll($q1);
      return $list;
    }

    static function listArray($db, $dbr){// REDO
        $ret = array();
        $list = Barcode::listAll($db, $dbr);
        foreach ($list as $barcode) {
            $ret[$barcode->id] = $barcode->code.$barcode->id;
        }
        return $ret;
    }
    
 /**
  * Returns obj db Record
  *
  * @param $id Barcode ID
  *
  * @return obj Returns the complete 'warehouse' entity, plus
  * ->state2filter - state code (barcode_state.code field)
  */
	static function sget_barcode_warehouse($db = null, $dbr = null, $id = 0) {
		$id = (int)$id;

        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
		$dbr->query("SET @set_barcode_id = ".$id);
		$q = "SELECT
				w.*,
				vbw.state2filter,
				vbw.state,
				vbw.reserved
			FROM vbarcode_warehouse vbw
				LEFT JOIN warehouse w ON w.warehouse_id = vbw.last_warehouse_id";
		$last_ware = $dbr->getRow($q);
		$dbr->query("SET @set_barcode_id = NULL");

		return $last_ware;
	}

 /**
  * Returns an extended data related to barcode LAST state and warehouse
  *
  * @return object - the complete 'warehouse' entity, plus
  * ->state2filter - state code (barcode_state.code field)
  * ->state - state description (with other entities relation, auction, wwo, op Order etc)
  */
	function get_barcode_warehouse() {
		return Barcode::sget_barcode_warehouse($this->_db, $this->_dbr, $this->id);
	}


 /**
  * Returns the complete last state of the barcode
  *
  * @return object - the list of states, warehouses and lod information
  * ->state2filter - state code (barcode_state.code field)
  * ->state - state description (with other entities relation, auction, wwo, op Order etc)
  */
	function get_barcode_state() {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$id = $this->id;
		$code = $dbr->getRow("select concat(barcode_object_obj_id,'/',article_id,'/',id) code
					, b.comment
					from vbarcode b
					where b.id=$id");
		$q = "select null state, t.*, IFNULL(ats.id,IFNULL(bm.id, IFNULL(rsp.rma_spec_id, IFNULL(o.id, IFNULL(wwa.id, IFNULL(rs.rma_spec_id, IFNULL(opa.id, IFNULL(bop.id,0)))))))) real_obj_id 
                from (
                    select barcode_object.*
                    from barcode_object 
                    where barcode_id=$id
                 ) t 
                left join ats on ats.id=t.obj_id and t.obj='ats'
                left join barcodes_manual bm on bm.id=t.obj_id and t.obj='barcodes_manual'
                left join op_article opa on opa.id=t.obj_id and t.obj='op_article'
                left join barcode_object bop on bop.barcode_id=$id and bop.obj='decompleted_article'
                left join orders o on o.id=t.obj_id and t.obj='orders'
                left join wwo_article wwa on wwa.id=t.obj_id and t.obj='wwo_article'
                left join rma_spec rs on rs.rma_spec_id=t.obj_id and t.obj='rma_spec' and rs.warehouse_id
                left join rma_spec rsp on rsp.rma_spec_id=t.obj_id and t.obj='rma_spec_problem' and rsp.problem_id=11
                where IFNULL(ats.id, IFNULL(bm.id, IFNULL(rsp.rma_spec_id, IFNULL(o.id, IFNULL(wwa.id, IFNULL(rs.rma_spec_id, IFNULL(opa.id, IFNULL(bop.id,0))))))))
        order by id desc limit 1";
		//echo $q;	
		$barcode = $dbr->getRow($q);
        if ( ! $barcode) {
            return false;
        }
        
        $barcode->last_ware = $this->get_barcode_warehouse();
        $barcode->last_ware = $barcode->last_ware->name;
        if ($barcode->obj=='ats') {
            $order = $dbr->getRow("select bm.*
                from ats bm
                where bm.id=".$barcode->obj_id);
            $barcode->state = "ATS #<a target='_blank' href='ats.php?id={$order->id}'>{$order->id}</a>";
            $barcode->state2filter = "ATS";
        }
        if ($barcode->obj=='barcodes_manual') {
            $order = $dbr->getRow("select bm.*
                from barcodes_manual bm
                where bm.id=".$barcode->obj_id);
            $barcode->state = "In stock manual <a target='_blank' href='op_order.php?id={$order->op_order_id}'>{$order->op_order_id}</a>";
            $barcode->state2filter = "In stock";
        }
        if ($barcode->obj=='op_article') {
            $order = $dbr->getRow("select opa.*
                from op_article opa
                where opa.id=".$barcode->obj_id);
            $barcode->state = "In stock OP <a target='_blank' href='op_order.php?id={$order->op_order_id}'>{$order->op_order_id}</a>";
            $barcode->state2filter = "In stock";
            if ($order->op_order_id) $barcode->updated = $order->add_to_warehouse_date;
            else $barcode->updated = $barcode->updated;
        }
        if ($barcode->obj=='orders') {
            $order = $dbr->getRow("select o.reserve_warehouse_id, o.send_warehouse_id, o.sent
                , IFNULL(mau.auction_number, au.auction_number) auction_number
                , IFNULL(mau.txnid, au.txnid) txnid
                from orders o
                join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
                left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
                where o.id=".$barcode->obj_id);
            if (!$order->sent) {
                $barcode->state = "reserved for Auftrag  <a target='_blank' href='auction.php?number={$order->auction_number}&txnid={$order->txnid}'>{$order->auction_number}/{$order->txnid}</a>";
                $barcode->state2filter = "reserved for Auftrag";
            } else {
                $barcode->state = "sent for Auftrag  <a target='_blank' href='auction.php?number={$order->auction_number}&txnid={$order->txnid}'>{$order->auction_number}/{$order->txnid}</a>";
                $barcode->state2filter = "sent for Auftrag";
                $barcode->updated = $dbr->getOne("select max(updated) from total_log 
                    where table_name='orders' and field_name='sent' and new_value=1 
                    and tableid=".$barcode->obj_id);
            }
        }
        if ($barcode->obj=='wwo_article') {
            $wwo = $dbr->getRow("select wwo.*, wwa.taken, wwa.delivered, delivered_not_added, taken_not_deducted
                from ww_order wwo
                join wwo_article wwa on wwa.wwo_id=wwo.id
                where wwa.id=".$barcode->obj_id);
            if ($wwo->delivered) {
                if ($wwo->delivered_not_added) {
                    $barcode->state = "not added to stock wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
                    $barcode->state2filter = "not added to stock wwo";
                } else {
                    $barcode->state = "delivered with wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
                    $barcode->state2filter = "delivered with wwo";
                    $barcode->updated = $dbr->getOne("select max(updated) from total_log 
                        where table_name='wwo_article' and field_name='delivered' and new_value=1 
                        and tableid=".$barcode->obj_id);
                }
            } elseif ($wwo->taken) {
                $barcode->state = "moving with wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
                $barcode->state2filter = "moving with wwo";
                $barcode->updated = $dbr->getOne("select max(updated) from total_log 
                    where table_name='wwo_article' and field_name='taken' and new_value=1 
                    and tableid=".$barcode->obj_id);
            } else {
                $barcode->state = "reserved for wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
                $barcode->state2filter = "reserved for wwo";
            }
        }
        if ($barcode->obj=='rma_spec') {
            $rma = $dbr->getRow("select rma.*, rma_spec.add_to_stock, rma_spec.back_wrong_delivery
                from rma 
                join rma_spec on rma_spec.rma_id=rma.rma_id
                where rma_spec.rma_spec_id=".$barcode->obj_id);
            $barcode->state = "back on stock ".($rma->add_to_stock?'ok':'broken')." for ticket <a target='_blank' href='search.php?what=rma_id&rma_id={$rma->rma_id}'>{$rma->rma_id}</a>";
            $barcode->state2filter = "back on stock ".($rma->add_to_stock?'ok':'broken')." for ticket";
        }
        if ($barcode->obj=='rma_spec_problem') {
            $rma = $dbr->getRow("select rma.*, rma_spec.add_to_stock, rma_spec.back_wrong_delivery
                from rma 
                join rma_spec on rma_spec.rma_id=rma.rma_id
                where rma_spec.rma_spec_id=".$barcode->obj_id);
            $barcode->state = "not send for ticket <a target='_blank' href='search.php?what=rma_id&rma_id={$rma->rma_id}'>{$rma->rma_id}</a>";
            $barcode->state2filter = "not send for ticket";
        }
        if ($barcode->obj=='decompleted_article') {
            $barcode->state = "Decompleted article";
            $barcode->state2filter = "decompleted_article";
        }
        if ($barcode->obj=='recompleted_article') {
            $barcode->state = "Recompleted article";
            $barcode->state2filter = "recompleted_article";
        }
        if ($barcode->obj=='barcode_inventory_detail') {
            $inventory = $dbr->getRow("select bi.*
                from barcode_inventory_detail bid
                    LEFT JOIN barcode_inventory bi ON bi.id = bid.barcode_inventory_id
                where bid.id=".$barcode->obj_id);
            $barcode->state = "Moved by inventory <a target='_blank' href='barcode_inventory_view.php?inventory_id={$inventory->id}'>{$inventory->id}</a>";
            $barcode->state2filter = "moved by inventory";
        }

        if (strlen($filter['state']) && $barcode->state2filter!=$filter['state']) {
            $barcode = false;
        }
		return $barcode;
	}

 /**
  * Returns the complete state history of the barcode, ordered by date desc
  *
  * @return object - the list of states, warehouses and lod information
  * ->state2filter - state code (barcode_state.code field)
  * ->state - state description (with other entities relation, auction, wwo, op Order etc)
  */
	function get_barcode_state_log() {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$id = $this->id;
		$code = $dbr->getRow("select concat(barcode_object_obj_id,'/',article_id,'/',id) code
					, b.comment
					from vbarcode b
					where b.id=$id");
		$q = "select null state, t.*, IFNULL(ats.id, IFNULL(bm.id, IFNULL(rsp.rma_spec_id, IFNULL(o.id, IFNULL(wwa.id, IFNULL(rs.rma_spec_id, IFNULL(opa.id, IFNULL(bop.id,0)))))))) real_obj_id 
				from (
					select barcode_object.*, total_log.updated, IFNULL(u.name, total_log.username) created_by
					from barcode_object 
					join total_log on tableid=barcode_object.id and table_name='barcode_object' and field_name='id'
					left join users u on u.system_username=total_log.username
					where barcode_id=$id
				 ) t 
				left join ats on ats.id=t.obj_id and t.obj='ats'
				left join barcodes_manual bm on bm.id=t.obj_id and t.obj='barcodes_manual'
				left join op_article opa on opa.id=t.obj_id and t.obj='op_article'
				left join barcode_object bop on bop.barcode_id=$id and bop.obj='decompleted_article'
				left join orders o on o.id=t.obj_id and t.obj='orders'
				left join wwo_article wwa on wwa.id=t.obj_id and t.obj='wwo_article'
				left join rma_spec rs on rs.rma_spec_id=t.obj_id and t.obj='rma_spec' and rs.warehouse_id
				left join rma_spec rsp on rsp.rma_spec_id=t.obj_id and t.obj='rma_spec_problem' and rsp.problem_id=11
				where IFNULL(ats.id, IFNULL(bm.id, IFNULL(rsp.rma_spec_id, IFNULL(o.id, IFNULL(wwa.id, IFNULL(rs.rma_spec_id, IFNULL(opa.id, IFNULL(bop.id,0))))))))
		/*union 
		select IF(new_value,'Inactivate','Activate') state, tl.id, null obj, null, tl.tableid, tl.updated, users.name, NULL
		from total_log tl
		join users on tl.username=users.system_username
		where tl.table_name='barcode' and tl.field_name='inactive' and tl.tableid=$id*/ #we use this method for 'last action' checking but inactivation should not affect this
			order by updated desc";
		//echo $q;	
		$list = $dbr->getAll($q);
		foreach($list as $k=>$barcode_object) {
			$list[$k]->last_ware = $this->get_barcode_warehouse();
			$list[$k]->last_ware = $list[$k]->last_ware->name;
			if ($barcode_object->obj=='ats') {
				$order = $dbr->getRow("select bm.*
					from ats bm
					where bm.id=".$barcode_object->obj_id);
				$list[$k]->state = "ATS #<a target='_blank' href='ats.php?id={$order->id}'>{$order->id}</a>";
				$list[$k]->state2filter = "ATS";
			}
			if ($barcode_object->obj=='barcodes_manual') {
				$order = $dbr->getRow("select bm.*
					from barcodes_manual bm
					where bm.id=".$barcode_object->obj_id);
				$list[$k]->state = "In stock manual <a target='_blank' href='op_order.php?id={$order->op_order_id}'>{$order->op_order_id}</a>";
				$list[$k]->state2filter = "In stock";
			}
			if ($barcode_object->obj=='op_article') {
				$order = $dbr->getRow("select opa.*
					from op_article opa
					where opa.id=".$barcode_object->obj_id);
				$list[$k]->state = "In stock OP <a target='_blank' href='op_order.php?id={$order->op_order_id}'>{$order->op_order_id}</a>";
				$list[$k]->state2filter = "In stock";
				if ($order->op_order_id) $list[$k]->updated = $order->add_to_warehouse_date;
				else $list[$k]->updated = $barcode_object->updated;
			}
			if ($barcode_object->obj=='orders') {
				$order = $dbr->getRow("select o.reserve_warehouse_id, o.send_warehouse_id, o.sent
					, IFNULL(mau.auction_number, au.auction_number) auction_number
					, IFNULL(mau.txnid, au.txnid) txnid
					from orders o
					join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
					left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
					where o.id=".$barcode_object->obj_id);
				if (!$order->sent) {
					$list[$k]->state = "reserved for Auftrag  <a target='_blank' href='auction.php?number={$order->auction_number}&txnid={$order->txnid}'>{$order->auction_number}/{$order->txnid}</a>";
					$list[$k]->state2filter = "reserved for Auftrag";
				} else {
					$list[$k]->state = "sent for Auftrag  <a target='_blank' href='auction.php?number={$order->auction_number}&txnid={$order->txnid}'>{$order->auction_number}/{$order->txnid}</a>";
					$list[$k]->state2filter = "sent for Auftrag";
					$list[$k]->updated = $dbr->getOne("select max(updated) from total_log 
						where table_name='orders' and field_name='sent' and new_value=1 
						and tableid=".$barcode_object->obj_id);
				}
			}
			if ($barcode_object->obj=='wwo_article') {
				$wwo = $dbr->getRow("select wwo.*, wwa.taken, wwa.delivered, delivered_not_added, taken_not_deducted
					from ww_order wwo
					join wwo_article wwa on wwa.wwo_id=wwo.id
					where wwa.id=".$barcode_object->obj_id);
				if ($wwo->delivered) {
					if ($wwo->delivered_not_added) {
						$list[$k]->state = "not added to stock wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
						$list[$k]->state2filter = "not added to stock wwo";
					} else {
						$list[$k]->state = "delivered with wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
						$list[$k]->state2filter = "delivered with wwo";
						$list[$k]->updated = $dbr->getOne("select max(updated) from total_log 
							where table_name='wwo_article' and field_name='delivered' and new_value=1 
							and tableid=".$barcode_object->obj_id);
					}
				} elseif ($wwo->taken) {
					$list[$k]->state = "moving with wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
					$list[$k]->state2filter = "moving with wwo";
					$list[$k]->updated = $dbr->getOne("select max(updated) from total_log 
						where table_name='wwo_article' and field_name='taken' and new_value=1 
						and tableid=".$barcode_object->obj_id);
				} else {
					$list[$k]->state = "reserved for wwo <a target='_blank' href='ware2ware_order.php?id={$wwo->id}'>{$wwo->id}</a>";
					$list[$k]->state2filter = "reserved for wwo";
				}
			}
			if ($barcode_object->obj=='rma_spec') {
				$rma = $dbr->getRow("select rma.*, rma_spec.add_to_stock, rma_spec.back_wrong_delivery
					from rma 
					join rma_spec on rma_spec.rma_id=rma.rma_id
					where rma_spec.rma_spec_id=".$barcode_object->obj_id);
				$list[$k]->state = "back on stock ".($rma->add_to_stock?'ok':'broken')." for ticket <a target='_blank' href='search.php?what=rma_id&rma_id={$rma->rma_id}'>{$rma->rma_id}</a>";
				$list[$k]->state2filter = "back on stock ".($rma->add_to_stock?'ok':'broken')." for ticket";
			}
			if ($barcode_object->obj=='rma_spec_problem') {
				$rma = $dbr->getRow("select rma.*, rma_spec.add_to_stock, rma_spec.back_wrong_delivery
					from rma 
					join rma_spec on rma_spec.rma_id=rma.rma_id
					where rma_spec.rma_spec_id=".$barcode_object->obj_id);
				$list[$k]->state = "not send for ticket <a target='_blank' href='search.php?what=rma_id&rma_id={$rma->rma_id}'>{$rma->rma_id}</a>";
				$list[$k]->state2filter = "not send for ticket";
			}
			if ($barcode_object->obj=='decompleted_article') {
				$list[$k]->state = "Decompleted article";
				$list[$k]->state2filter = "decompleted_article";
			}
			if ($barcode_object->obj=='recompleted_article') {
				$list[$k]->state = "Recompleted article";
				$list[$k]->state2filter = "recompleted_article";
			}
			if ($barcode_object->obj=='barcode_inventory_detail') {
				$inventory = $dbr->getRow("select bi.*
					from barcode_inventory_detail bid
						LEFT JOIN barcode_inventory bi ON bi.id = bid.barcode_inventory_id
					where bid.id=".$barcode_object->obj_id);
				$list[$k]->state = "Moved by inventory <a target='_blank' href='barcode_inventory_view.php?inventory_id={$inventory->id}'>{$inventory->id}</a>";
				$list[$k]->state2filter = "moved by inventory";
			}
			if (strlen($filter['state']) && $list[$k]->state2filter!=$filter['state']) {
				unset($list[$k]); continue;
			}
		}
		usort($list, 'sort_barcode_state_log');
		return $list;
	}

 /**
  * Returns code of the barcode
  *
  * @return string
  */
	function get_code() {
		return $this->get('barcode_object_obj_id').'/'.$this->get('article_id').'/'.$this->id;
	}


 /**
  * Check the availability of the barcode
  *
  * @param $obj string - barcode origin
  *
  * @return int Number of correct barcodes (1 or 0)
  */
    
    // @todo DON't WORKING !!!
    function checked_barcode($obj)
    {
        $barcode_id = (int)$this->id;
        $article_id = (int)$this->vdata->article_id;
        $opa_id = (int)$this->vdata->barcode_object_obj_id;
        
        if ($obj == 'wwo_article')
        {
            $where = " AND NOT fget_barcode_wwa_reserved(`b`.`id`) ";
        }
        else 
        {
            $where = " AND NOT fget_barcode_reserved(`b`.`id`) ";
        }

        return $this->_dbr->getOne("SELECT COUNT(*) FROM `barcode_object` `bo`
            JOIN `vbarcode` `b` ON `bo`.`barcode_id` = `b`.`id`
            WHERE `bo`.`obj_id` = '$opa_id' 
                AND `b`.`id` = '$barcode_id'
                AND `b`.`article_id` = '$article_id' 
                AND NOT `b`.`inactive` "
          . $where); // if the barcode exists
    }

    /**
  * Returns the reserved flag. Its a sum of all reservation and unreservation operation for the barcode
  *
  * @return int - 1 if it was reserved once, 0 if it was never reserved or unreserved
  */
	function get_reserved() {
		$res = $this->_dbr->getOne("select sum(bs.reserved) reserved
			from barcode b 
			left join barcode_origin bog on bog.id = b.origin_id 
			left join barcode_object bo on bo.barcode_id = b.id 
			left join orders decompleted_article on bo.obj_id = decompleted_article.id and bo.obj = 'decompleted_article' and decompleted_article.new_article_completed=0
			left join orders recompleted_article on bo.obj_id = recompleted_article.id and bo.obj = 'recompleted_article' and recompleted_article.new_article_completed=1
			left join wwo_article wwa on bo.obj_id = wwa.id and bo.obj = 'wwo_article'
			left join warehouse w_wwo_delivered on wwa.to_warehouse=w_wwo_delivered.warehouse_id and wwa.delivered
			left join ww_order wwo on wwo.id=wwa.wwo_id
			left join ats on bo.obj_id = ats.id and bo.obj = 'ats'
			left join barcodes_manual bm on bo.obj_id = bm.id and bo.obj = 'barcodes_manual'
			left join op_article opa on bo.obj_id = opa.id and bo.obj = 'op_article'
			left join rma_spec rs on bo.obj_id = rs.rma_spec_id and bo.obj = 'rma_spec'
			left join rma on rma.rma_id=rs.rma_id
			left join rma_spec rsp on bo.obj_id = rsp.rma_spec_id and bo.obj = 'rma_spec_problem'
			left join orders o on bo.obj_id = o.id and bo.obj = 'orders'
			left join barcode_state bs on bs.code=
			IF(((concat(bo.obj)))='barcodes_manual', 'manual'
				, IF(((concat(bo.obj)))='op_article', 'In stock'
					, IF(((concat(bo.obj)))='ats', 'ATS'
						, IF(((concat(bo.obj)))='wwo_article'
			, IF(((concat(wwa.delivered)))
				, IF(((concat(wwa.delivered_not_added)))
				, 'not added to stock wwo'
				, 'delivered with wwo')
				, IF(((concat(wwa.taken)))
				, 'moving with wwo'
				, 'reserved for wwo'))
						, IF(((concat(bo.obj)))='rma_spec'
			, IF(((concat(rs.rma_spec_id)))
				, IF(((concat(rs.add_to_stock)))
				, 'back on stock ok for ticket'
				, 'back on stock broken for ticket')
				, 'made for ticket - deleted')
						, IF(((concat(bo.obj)))='rma_spec_problem'
			, IF(((concat(rs.rma_spec_id)))
				, 'not send for ticket'
				, 'made for ticket - deleted')
						, IF(((concat(bo.obj)))='orders'
			, IF(((concat(o.sent)))
				, 'sent for Auftrag'
				, 'reserved for Auftrag')
						, IF(((concat(bo.obj)))='decompleted_article', 'Decompleted article'
						, IF(((concat(bo.obj)))='recompleted_article', 'Recompleted article'
				, '--')))))))))
		where b.id=".$this->get('id'));
		return $res;
	}

	/**
	 * Create barcodes in barcode and barcode_object mySQL tables
	 *
	 * @param $opa_id  is id field of mySQL table (op_article).
	 *
	 */
	function create_op_barcodes($db,$opa_id,$count)
	{
		if($count)
		{
			for ($i=0; $i<$count; $i++) {
				$q = "insert into barcode (code) value ('')";
				$db->query($q);
        $barcode_id = $db->getOne("SELECT LAST_INSERT_ID()");
				$q = "insert into barcode_object (`obj`,`obj_id`,`barcode_id`)
						  value ('op_article', $opa_id, $barcode_id)";
				$db->query($q);
			}
		}
		else {
			if ($opa_id) {
				$opa_row = $db->getRow("select * from op_article where id=$opa_id");
				$barcodes = $db->getAll("select * from barcode_object where obj='op_article' and obj_id=$opa_id");
				if (count($barcodes) < $opa_row->qnt_ordered) {
					for ($i = count($barcodes); $i < $opa_row->qnt_ordered; $i++) {
						$q = "insert into barcode (code) value ('')";
						$db->query($q);
						$barcode_id = mysql_insert_id();
						$q = "insert into barcode_object (`obj`,`obj_id`,`barcode_id`)
						  value ('op_article', $opa_id, $barcode_id)";
						$db->query($q);
					}
				}
			}
		}
	}
}

?>