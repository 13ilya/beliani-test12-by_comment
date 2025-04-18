<?php
require_once 'PEAR.php';
require_once 'config.php';
require_once 'util.php';

 /**
  * Parcel Barcode operation class
  * Contains methods related to check existing parcel barcodes, retrieving basic and extended data
  * @author Borodin Vasiliy <slayer-001@mail.ru>
  * @param string $id parcel barcode real ID (mysql database parcel_barcode or vparcel_barcode id column value)
  * @param string $data parcel_barcode basic data (compatibility)
  * @param string $vdata parcel_barcode extended data (vparcel_barcode table contents)
  * @param string $_db database write/read object identifier
  * @param string $_dbr database read (only) object identifier
  * @param string $_error contains error reports, concerning both mySQL and PHP execution
  * @return void
*/

class Parcel_Barcode
{
	public $id;
	public $data;
	public $vdata;
	protected $_db;
	protected $_dbr;
	public $_error;
	
    function __construct($db, $dbr, $parcel_barcode){ // Parcel can be id or string ([a-zA-Z]{1,2}\/[0-9]{10})
        if (!is_a($db, 'MDB2_Driver_mysql')){
            $this->_error = PEAR::raiseError('Parcel_Barcode::Parcel barcode expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        
		$this->_db = $db;
		$this->_dbr = $dbr;
        
		if (!$parcel_barcode){
            $this->_error = PEAR::raiseError("Parcel_Barcode::Need parcel barcode");
			return;
        }
		else {
			$this->get_vdata($parcel_barcode);
        	
        	if ($this->id) {
	            $r = $this->_db->query("SELECT * FROM parcel_barcode WHERE id=".$this->id.";");
    	        if (PEAR::isError($r)){
        	        $this->_error = $r;
            	    return;
	            }
    	        $this->data = $r->fetchRow();
        	    if (!$this->data){
					$this->_error = PEAR::raiseError("Parcel_Barcode::The record $parcel_barcode does not exist");
					return;
				}
			}
			else {
				return;
			}
        }
    }

 /**
  * Returns extended barcode parameters from mySQL vbarcode table.
  *
  * @param $parcel_barcode Initial complete parcel barcode ([a-zA-Z]{1,2}\/[0-9]{10}) OR parcel barcode id (1-99999x), does not matter.
  *
  * @return object collection of barcode parameters in $this->vdata variable.
  */

    function get_vdata($parcel_barcode){
    	global $dbr_spec;
		
		if (preg_match('/^[a-zA-Z]{1,2}\/[0-9]{10}$/', $parcel_barcode)){
			$vdata = $dbr_spec->getRow("SELECT * FROM vparcel_barcode WHERE parcel='$parcel_barcode'");
		}else{
			if (preg_match('/^[0-9]+$/', $parcel_barcode)){
				$id=(int)$parcel_barcode;
				$vdata = $dbr_spec->getRow("SELECT * FROM vparcel_barcode WHERE id='".$id."'");
			}else{
				$this->_error = PEAR::raiseError("Wrong pallet format (entered $pallet, must be X(x)/NNNNNNNNNN)!");
				return;
			}
		}
			
		if (PEAR::isError($vdata)) {
			$this->_error = PEAR::raiseError($vdata);
			return;
		}else{
			$this->vdata = $vdata;
			$this->id = $vdata->id;
		}
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
  * Set a value for a specific field in barcode mySQL table
  *
  * @param $field mySQL table (barcode) field to be modified.
  *
  * @param $value mySQL table (barcode) field new value.
  *
  */

	function set($field, $value){
    	if(!empty($value)){
			switch($field){
				case 'ware_loc_id':
					$q="SELECT warehouse_id FROM ware_loc WHERE id='".$value."'";
					$warehouse_id=$this->_db->getOne($q);
					if($warehouse_id == $this->get('warehouse_id')){
						$q="UPDATE parcel_barcode SET ware_loc_id='".$value."' WHERE id='".$this->id."'";
						$this->_db->query($q);
					}else{
						$this->_error = PEAR::raiseError("Parcel_Barcode::Can`t change warehouse_id for parcel barcode");
					}
					break;

				case 'warehouse_cell_id':
					$q="SELECT h.warehouse_id
					FROM warehouse_cells c
						LEFT JOIN warehouse_halls h on h.id = c.hall_id
					WHERE c.id='".$value."'";
					$warehouse_id=$this->_db->getOne($q);
					if($warehouse_id == $this->get('warehouse_id')){
						$q="UPDATE parcel_barcode SET warehouse_cell_id='".$value."' WHERE id='".$this->id."'";
						$this->_db->query($q);
					}else{
						$this->_error = PEAR::raiseError("Parcel_Barcode::Can`t change warehouse_id for parcel barcode");
					}
					break;

				case 'ware_loc':
					if (preg_match('/^[A-Z]{1}[0-9]{1,2}-[0-9]{2}-[0-9]{3}-[0-9]{2}$/', $value)){
						$warehouse_code = substr($value,0,1);
						if (is_numeric($warehouse_id = warehouse_get_warehouse_id_by_code($warehouse_code))){
							$ware_cells=explode('-', $value);
							$hall_title_id = str_replace($warehouse_code,'',$ware_cells[0]);
							$hall_id = warehouse_get_real_hall_id($hall_title_id, $warehouse_id);
							$q="SELECT id FROM warehouse_cells WHERE hall_id='".$hall_id."' and row='".$ware_cells[1]."' and bay='".$ware_cells[2]."' and level='".$ware_cells[3]."'";
							$cell_id=$this->_db->getOne($q);
							$q="SELECT id FROM ware_loc WHERE warehouse_id='".$warehouse_id."' and hall='".$hall_title_id."' and row='".$ware_cells[1]."' and bay='".$ware_cells[2]."' and level='".$ware_cells[3]."'";
							$ware_loc_id=$this->_db->getOne($q);
							if($cell_id){
								$this->set('warehouse_cell_id', $cell_id);
							}else{
								$this->_error = PEAR::raiseError("Parcel_Barcode::Cant get cell_id for this location");
							}
							if($ware_loc_id){
								$this->set('ware_loc_id', $ware_loc_id);
							}else{
								$this->_error = PEAR::raiseError("Parcel_Barcode::Cant get ware_loc_id for this location");
							}
						}else{
							$this->_error = PEAR::raiseError("Parcel_Barcode::Wrong or non-existing warehouse's ID.");
						}
					}else{
						$this->_error = PEAR::raiseError("Parcel_Barcode::Wrong warehouse's location format (entered $location, must be XN(N)-NN-NNN-NN)!");
					}
					break;

				case 'inactive':
					if($value == 0 || $value == 1){
						$q="UPDATE parcel_barcode SET inactive='".$value."' WHERE id='".$this->id."'";
						$this->_db->query($q);
					}else{
						$this->_error = PEAR::raiseError("Parcel_Barcode::'Inactive' can be just 1 or 0");
					}
					break;
					
				default:
					$this->_error = PEAR::raiseError("Parcel_Barcode::Can`t set $field for parcel barcode");
			}
		}else{
			$this->_error = PEAR::raiseError("Parcel_Barcode::Value can`t be empty");
		}
	}

 /**
  * Get the weight value of this parcel
  *
  * @return int
  *
  */

	function get_weight(){
		$pallet_weight =$this->_dbr->getOne("select tp.weight from tn_packets tp where tp.code='".$this->vdata->barcode1."'");

		return $pallet_weight;
	}

	function get_parcels_weight($db, $dbr, $parcels_ids){
		$parcels_ids = implode(',', $parcels_ids);
		$packages_weight = $dbr->getOne("SELECT SUM(tp.weight) as weight FROM parcel_barcode pb
JOIN tn_packets tp on tp.id=pb.tn_packet_id
WHERE pb.id IN ($parcels_ids)");

		return $packages_weight;
	}

 /**
  * Returns all articles, assigned at current parcel
  *
  * @return object - the list of article (name, id), op order, quantity, barcode, username, weight, datetime, state
  *
  */

	function get_articles(){
		$query = "select 0 as used
					, pbab.parcel_barcode_id id
					, b.article_name  
					, b.article_id
					, b.ware_loc location 
					, 'pbab' as tablename
					, b.op_order_id
					, b.new_op_order_id
					, 1 quantity
					, b.parcel_barcode parcel_barcode
					, concat(b.barcode_object_obj_id,'/',b.article_id,'/',b.id) barcode
					, pbab.parcel_barcode_id AS parcel_barcode_id
					, a.weight_per_single_unit AS weight
					, a.volume_per_single_unit AS volume
                    , CONCAT(`tl`.`Updated`, ' by ', IFNULL(`u`.`name`, `tl`.`username`)) AS `updated_text`
                    , `dn`.`reserved`
				from vbarcode b
                    LEFT JOIN barcode_dn dn ON dn.id=b.id
					join parcel_barcode_article_barcode pbab on pbab.barcode_id=b.id AND `pbab`.`deleted` = 0
					join article a on b.article_id=a.article_id and a.barcode_type='A' AND a.admin_id = 0
					LEFT JOIN total_log tl ON tl.table_name='parcel_barcode_article_barcode' AND tl.TableID=pbab.id AND tl.field_name='id'
					LEFT JOIN users u ON u.system_username=tl.username
				where b.parcel_barcode='{$this->vdata->parcel}' and pbab.parcel_barcode_id={$this->id}
                GROUP BY b.id
			union all
				select 	0 as used
					, parcel_barcode_id id
					, t.value article_name  
					, t.id article_id
					, CONCAT((select ware_char from warehouse where warehouse_id = ".$this->vdata->warehouse_id." ),ware_loc.hall,'-',ware_loc.row,'-',ware_loc.bay,'-',ware_loc.level)  location 
					, 'pba' as tablename
					, '' op_order_id
					, '' new_op_order_id
					, SUM(pba.quantity) quantity
					, concat(tp.code,'/', lpad(pb.id,10,0)) parcel_barcode
					, '' barcode
					, parcel_barcode_id AS parcel_barcode_id
					, a.weight_per_single_unit AS weight
					, a.volume_per_single_unit AS volume
                    , CONCAT(`tl`.`Updated`, ' by ', IFNULL(`u`.`name`, `tl`.`username`)) AS `updated_text`
                    , '' AS `reserved`
				from parcel_barcode_article pba
					join translation t on table_name='article' and field_name='name' and language='german' and t.id=pba.article_id
					left join article a on pba.article_id=a.article_id and a.company_id>0
					left join parcel_barcode pb on pb.id=pba.parcel_barcode_id
					join tn_packets tp on tp.id=pb.tn_packet_id
					left join ware_loc on ware_loc.id=pb.ware_loc_id
					left JOIN total_log tl ON tl.table_name='parcel_barcode_article' AND tl.TableID=pba.id AND tl.field_name='id'
					left JOIN users u ON u.system_username=tl.username
                where parcel_barcode_id={$this->id}
	            GROUP BY t.id
	            having sum(quantity)<>0";
                
		return $this->_dbr->getAll($query);
	}

	function get_articles_old(){
		$q = "select 0 as used,
					pbab.parcel_barcode_id id,
					b.article_name  ,
					b.article_id,
					b.ware_loc location ,
					'pba' as tablename,
					b.op_order_id,
					1 quantity,
					b.parcel_barcode parcel_barcode,
					concat(b.barcode_object_obj_id,'/',b.article_id,'/',b.id) barcode,
					u.username  username ,
					pbab.parcel_barcode_id AS parcel_barcode_id,
					a.weight_per_single_unit AS weight,
					bw.state2filter,
					tl.Updated ,
					bw.state
				from vbarcode b
					left join vbarcode_warehouse bw on b.id=bw.id
					left join warehouse w1 on w1.warehouse_id=bw.last_warehouse_id
					join parcel_barcode_article_barcode pbab on pbab.barcode_id=b.id AND `pbab`.`deleted` = 0
					LEFT JOIN total_log tl ON tl.table_name='parcel_barcode_article_barcode' AND tl.TableID=pbab.id AND tl.field_name='id'
					LEFT JOIN users u ON u.system_username=tl.username
					join article a on b.article_id=a.article_id and a.barcode_type='A'
				where b.parcel_barcode='{$this->vdata->parcel}'
			union all
				select 	0 as used,
					parcel_barcode_id id,
					t.value article_name  ,
					t.id article_id,
					CONCAT((select ware_char from warehouse where warehouse_id = ".$this->vdata->warehouse_id." ),ware_loc.hall,'-',ware_loc.row,'-',ware_loc.bay,'-',ware_loc.level)  location ,
					'pba' as tablename,
					'' op_order_id,
					pba.quantity quantity,
					concat(tp.code,'/', lpad(pb.id,10,0)) parcel_barcode,
					'' barcode,
					u.username  username ,
					parcel_barcode_id AS parcel_barcode_id,
					a.weight_per_single_unit AS weight,
					'' state2filter,
					tl.Updated ,
					'' state
				from parcel_barcode_article pba
					join translation t on table_name='article' and field_name='name' and language='german' and t.id=pba.article_id
					left join article a on pba.article_id=a.article_id and a.company_id>0
					left JOIN total_log tl ON tl.table_name='parcel_barcode_article' AND tl.TableID=pba.id AND tl.field_name='id'
					left JOIN users u ON u.system_username=tl.username
					left join parcel_barcode pb on pb.id=pba.parcel_barcode_id
					join tn_packets tp on tp.id=pb.tn_packet_id
					join ware_loc on ware_loc.id=pb.ware_loc_id
				where parcel_barcode_id=".$this->id;
		$articles = $this->_dbr->getAll($q);

        echo "<pre>$q</pre>";
        
		return $articles;
	}

 /**
  * Returns the complete log of ware loc of current parcel
  *
  * @return object - the list of states, location and username
  *
  */

	function get_wareloc_log(){
		$q = "select 'Unlocated from ' as action, CONCAT(w.ware_char,warehouse_halls.title_id,'-',warehouse_cells.row,'-',warehouse_cells.bay,'-',warehouse_cells.level) ware_loc
				, IFNULL(u.name, tl.username) username, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				join warehouse_cells on warehouse_cells.id=tl.old_value*1
				join warehouse_halls on warehouse_cells.hall_id=warehouse_halls.id
				join warehouse w on w.warehouse_id=warehouse_halls.warehouse_id
				where tl.table_name = 'parcel_barcode' and tl.field_name='warehouse_cell_id' and (ISNULL(tl.New_value) or tl.New_value = '0')
				and pb.id={$this->id}
			union
			select 'Located to ' as action, CONCAT(w.ware_char,warehouse_halls.title_id,'-',warehouse_cells.row,'-',warehouse_cells.bay,'-',warehouse_cells.level) ware_loc
				, IFNULL(u.name, tl.username) username, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				join warehouse_cells on warehouse_cells.id=tl.new_value*1
				join warehouse_halls on warehouse_cells.hall_id=warehouse_halls.id
				join warehouse w on w.warehouse_id=warehouse_halls.warehouse_id
				where tl.table_name = 'parcel_barcode' and tl.field_name='warehouse_cell_id' and (ISNULL(tl.Old_value) or tl.Old_value = '0')
				and pb.id={$this->id}
			union
			select 'Moved to ' as action, CONCAT(w.ware_char,warehouse_halls.title_id,'-',warehouse_cells.row,'-',warehouse_cells.bay,'-',warehouse_cells.level) ware_loc
				, IFNULL(u.name, tl.username) username, tl.updated
				from total_log tl
				join parcel_barcode pb on pb.id=tl.Tableid*1
				left join tn_packets tnp on tnp.id=pb.tn_packet_id
				left join users u on u.system_username=tl.username
				join warehouse_cells on warehouse_cells.id=tl.new_value*1
				join warehouse_halls on warehouse_cells.hall_id=warehouse_halls.id
				join warehouse w on w.warehouse_id=warehouse_halls.warehouse_id
				where tl.table_name = 'parcel_barcode' and tl.field_name='warehouse_cell_id' and tl.Old_value and tl.New_value
				and pb.id={$this->id}
			order by updated desc";
		$list = $this->_dbr->getAll($q);

		return $list;
	}

 /**
  * Returns the complete log of added/removed items from/to current parcel
  *
  * @return object - the list of Article/Barcode, states, username and datetime
  *
  */

	function get_assigned_log(){
		$q="select CONCAT('Article ', pba.article_id, ': ', t.value, ' x ', abs(pba.quantity)
			, IF(pba.quantity>0, ' Was added by ', ' Was removed by '), IFNULL(u.name, tl.username), ' on ', tl.updated) log
			, tl.updated
			from total_log tl
			join parcel_barcode_article pba on pba.id=tl.Tableid*1
			join translation t on t.table_name='article' and t.field_name='name' and t.id=pba.article_id and t.language='german'
			left join users u on u.system_username=tl.username
			where tl.table_name = 'parcel_barcode_article'
			and pba.parcel_barcode_id = '{$this->id}' and pba.quantity<>0
		union
			select distinct CONCAT('Barcode ', b1.barcode_object_obj_id,'/',b1.article_id,'/',b1.id
			, ' Was removed by ', IFNULL(u.name, tl.username), ' on ', tl.updated, IFNULL(CONCAT(' for WWO#', pbd.wwo_id),'')) log
			, tl.updated
			from total_log tl
			join total_log tl1 on tl1.tableid=tl.tableid and tl1.table_name = 'parcel_barcode_article_barcode' and tl1.field_name='parcel_barcode_id'
					and tl.updated=tl1.updated
				join vbarcode b1 on tl.old_value*1=b1.id
			left join users u on u.system_username=tl.username
			left join parcel_barcode_article_barcode_deduct pbd on pbd.parcel_barcode_id=1*tl1.old_value and pbd.wwo_id<>0 and pbd.barcode_id=b1.id
			where tl.table_name = 'parcel_barcode_article_barcode' and tl.field_name='barcode_id'
			and tl1.old_value = '{$this->id}'
		union 
			select distinct CONCAT('Barcode ', b1.barcode
				, ' Was removed by ', IFNULL(u.name, tl1.username), ' on ', tl1.updated, IF(pbd.wwo_id > 0, CONCAT(' for WWO#', pbd.wwo_id), '')) log
				, tl1.updated
			from parcel_barcode_article_barcode pbab
				join total_log tl1 on tl1.tableid=pbab.id and tl1.table_name = 'parcel_barcode_article_barcode' and tl1.field_name='deleted' and tl1.new_value<>'0'
				join vbarcode b1 on pbab.barcode_id=b1.id
				left join users u on u.system_username=tl1.username
				left join parcel_barcode_article_barcode_deduct pbd on pbd.id=pbab.deleted
			where pbab.parcel_barcode_id = '{$this->id}'
		union 
			select CONCAT('Barcode ', b1.barcode, IF(tl1.Old_value, CONCAT(' Was moved in from ', tp.code, '/', lpad(pb.id,10,0), ' '), ' Was added by '), IFNULL(u.name, tl1.username), ' on ', tl1.updated) log
			, tl1.updated
			from parcel_barcode_article_barcode pbab
				join total_log tl1 on tl1.tableid=pbab.id and tl1.table_name = 'parcel_barcode_article_barcode' and tl1.field_name='parcel_barcode_id'
				left join parcel_barcode pb on pb.id=tl1.Old_value*1
				left join tn_packets tp on tp.id=pb.tn_packet_id
				join vbarcode b1 on pbab.barcode_id=b1.id
				left join users u on u.system_username=tl1.username
			where tl1.new_value = '{$this->id}'
		union 
			select CONCAT('Barcode ', b1.barcode, IF(tl1.Old_value, CONCAT(' Was moved out to ', tp.code, '/', lpad(pb.id,10,0), ' '), ' Was added by '), IFNULL(u.name, tl1.username), ' on ', tl1.updated) log
			, tl1.updated
			from parcel_barcode_article_barcode pbab
				join total_log tl1 on tl1.tableid=pbab.id and tl1.table_name = 'parcel_barcode_article_barcode' and tl1.field_name='parcel_barcode_id'
				join parcel_barcode pb on pb.id=tl1.new_value*1
				join tn_packets tp on tp.id=pb.tn_packet_id
				join vbarcode b1 on pbab.barcode_id=b1.id
				left join users u on u.system_username=tl1.username
			where tl1.Old_value = '{$this->id}'
		order by updated";
		$list = $this->_dbr->getAll($q);

		return $list;
	}
}

?>