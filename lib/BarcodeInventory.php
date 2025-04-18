<?php
require_once 'PEAR.php';
require_once 'config.php';
require_once 'util.php';

/**
 * Barcode inventory operation class
 *
 * Contains methods related to create inventory barcodes list, cose inventory barcode list
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
class BarcodeInventory
{
    public $_id;
    public $data;
    public $vdata;
    protected $_db;
    protected $_dbr;
    public $_error;

    function __construct($db, $dbr, $inventory=false)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')){
            $this->_error = PEAR::raiseError('Barcode::Barcode expects its argument to be a MDB2_Driver_mysql object');
            return;
        }

        $this->_db = $db;
        $this->_dbr = $dbr;
        $this->_id=$inventory;

        if (!$inventory){
            $r = $this->_db->query("EXPLAIN barcode_inventory");
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
            $this->_id = (int) $inventory;

            if ($this->_id) {
                $r = $this->_db->query("SELECT * FROM barcode_inventory WHERE id=".$this->_id.";");
                if (PEAR::isError($r)){
                    $this->_error = $r;
                    return;
                }
                $this->data = $r->fetchRow();
                if (!$this->data){
                    $this->_error = PEAR::raiseError("BarcodeInventory::BarcodeInventory : the record $inventory does not exist");
                    return;
                }
            }
            else {
                return;
            }
            $this->_isNew = false;
        }
    }

    /**
     * Set a value for a specific field in barcode_inventory mySQL table
     *
     * @param $field mySQL table (barcode_inventory) field to be modified.
     *
     * @param $value mySQL table (barcode_inventory) field new value.
     *
     */
    function set($field, $value){
        if (isset($this->data->$field)) $this->data->$field = $value;
        if (isset($this->vdata->$field)) $this->vdata->$field = $value;
    }

    /**
     * Get the value of specific field in barcode_inventory mySQL table
     *
     * @param $field mySQL table (barcode_inventory) field to read.
     *
     */
    function get($field){
        if (isset($this->vdata->$field)) return $this->vdata->$field;
        elseif (isset($this->data->$field)) return $this->data->$field;
        else return null;
    }

    /**
     * Close barcode_inventory mySQL table
     *
     * @param $inventory_id mySQL table (barcode_inventory).
     *
     */

    function close(){
        $id = $this->_id;
        /* Table for barcode warehouse, if use denormalization - barcode_dn */
        $vbw = 'vbarcode_warehouse';
        $bt = 'b';
        if ($GLOBALS['CONFIGURATION']['use_dn']) {
            $vbw = 'barcode_dn';
            $bt = 'bw';
        }
        if($id)
        {
            $is_open=$this->get('status_is_open');
            if(is_null($is_open)) $is_open=2;

            if($is_open != 0){
                $query="select
                        group_concat(distinct b.id
                            SEPARATOR ',') barcode_ids
                    from
                        barcode_inventory bi
                            join
                        {$vbw} bw ON bw.last_warehouse_id = bi.warehouse_id
                            join
                        vbarcode b ON b.id = bw.id
                    where
                        bi.id = $id
                        and b.inactive=0";
                if($this->get('article_id')) $query.="
                        and {$bt}.article_id = ".$this->get('article_id');
                if($this->get('inventory_type') == 'parcel') $query.="
                        and b.parcel_barcode_id is not null";
                $query.="
                        and bw.state2filter in (SELECT code FROM barcode_state WHERE type='in' ORDER BY id)";
                $barcode_ids = $this->_db->getOne($query);

                if (PEAR::isError($barcode_ids)){
                    $this->_error = $barcode_ids;
                    return;
                }

                if($this->get('inventory_type') != 'parcel'){
                    $q = "update barcode_inventory_detail
                         set found = 1
                         where barcode_inventory_id = $id
                            and ISNULL(found)
                            and barcode_id in ($barcode_ids) ";
                    $r=$this->_db->query($q);
                    if (PEAR::isError($r)){
                        $this->_error = $r;
                        return;
                    }
                
                    $users = $this->_db->getAssoc("SELECT id,user_id FROM barcode_inventory_user WHERE inventory_id = $id");
                    foreach($users as $user_id){
                        $q1 = "INSERT IGNORE INTO barcode_inventory_detail
                            (barcode_inventory_id,user_id,barcode_id,found)
                         SELECT $id,$user_id,b.id,0
                         FROM barcode b
                         where id in ($barcode_ids) ";
                        $r=$this->_db->query($q1);
                        if (PEAR::isError($r)){
                            $this->_error = $r;
                            return;
                        }
                    }
                }else{
                    $q1 = "INSERT IGNORE INTO barcode_inventory_detail
                        (barcode_inventory_id,barcode_id,found)
                     SELECT $id,b.id,0
                     FROM barcode b
                     where id in ($barcode_ids) ";
                    $r=$this->_db->query($q1);
                    if (PEAR::isError($r)){
                        $this->_error = $r;
                        return;
                    }
                }
                
                $q2 = "update barcode_inventory
                     set status_is_open = 0
                     where id = $id ";
                $r = $this->_db->query($q2);
                if (PEAR::isError($r)){
                    $this->_error = $r;
                    return;
                }

                $this->_db->query("DELETE FROM stock_cache_date WHERE fordatetime = '".$this->get('close_datetime')."'");
                
                $q3 = "update barcode_inventory_user
                     set is_finish = 1
                     where inventory_id = $id ";
                $r = $this->_db->query($q3);
                if (PEAR::isError($r)){
                    $this->_error = $r;
                    return;
                }

                $this->set('close_datetime', $this->_db->getOne("SELECT close_datetime FROM barcode_inventory WHERE id = '".$id."' LIMIT 0,1"));

                if($this->get('article_id')){
                    $this->_db->query("INSERT INTO stock_cache_date (warehouse_id, article_id, pieces, reserved, updated, fordate, fordatetime, volume) VALUES (".$this->get('warehouse_id').", ".$this->get('article_id').", fget_Article_stock(".$this->get('article_id').", ".$this->get('warehouse_id')."), fget_Article_reserved(".$this->get('article_id').", ".$this->get('warehouse_id')."), '".$this->get('close_datetime')."', DATE('".$this->get('close_datetime')."'), '".$this->get('close_datetime')."', 0)");
                }else{
                    $articles = $this->_db->getAssoc("SELECT
                            distinct a.article_id k,
                            fget_Article_stock(a.article_id, ".$this->get('warehouse_id').") val
                        FROM article a
                        WHERE a.admin_id = 0 and a.barcode_type='A' and a.deleted = 0");
                    foreach($articles as $article_id => $stock){
                        $q = "INSERT INTO stock_cache_date (warehouse_id, article_id, pieces, reserved, updated, fordate, fordatetime, volume) VALUES (".$this->get('warehouse_id').", ".$article_id.", ".$stock.", 0, '".$this->get('close_datetime')."', DATE('".$this->get('close_datetime')."'), '".$this->get('close_datetime')."', 0)";
                        $this->_db->query($q);
                    }
                }
            }else{
                $this->_db->query("DELETE FROM barcode_inventory_detail WHERE barcode_inventory_id = {$id} and found = 0 and barcode_inventory_parcel_id is null");
                $this->_db->query("UPDATE barcode_inventory_detail SET found = Null WHERE barcode_inventory_id = {$id} and found = 1 and barcode_inventory_parcel_id is null");
                $this->_db->query("UPDATE barcode_inventory_user SET is_finish = 0 WHERE inventory_id = {$id}");
                $this->_db->query("UPDATE barcode_inventory SET status_is_open = 1 WHERE id = {$id}");
            }
        }
    }
}