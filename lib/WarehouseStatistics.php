<?php

require_once 'PEAR.php';

 /**
  * Warehouse Satatistics class
  *
  * Contains methods related to prepare get query, create template 
  * 
  * @version 0.1
  * 
  * @param MDB2_Driver_mysql $_db database write/ read object identifier
  *
  * @param MDB2_Driver_mysql $_dbr database read (only) object identifier
  *
  * @return void
  */

class WarehouseStatistics {
    
    const LIMIT_ROWS = 100000;
    const FILL_TABLE_MEMKEY = 'fill_pbtl';
    const FILL_TABLE_TTL = 3600;

    private $_db;
    private $_dbr;
    
    private $_total_log = array();
    private $_total_log_indexes = array();
    
    private $warehouses_users = array();
    
    private $ACTIONS = array();

    public function __construct(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr) {
        $this->_db = $db;
        $this->_dbr = $dbr;
        
        $this->_fill_parcel_barcode_table();
    }
    
    public function __destruct() {
        ;
    }

    /******************************************************************************************************************/
    
    public function get($get_data) {
        if ($get_data['actions'])
        {
            $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
            $this->_get_total_log($get_data);

            if ( ! $this->_total_log) {
                return [];
            }

            $statistics_wms = $this->_get_wms($get_data);
        }
        else 
        {
            $statistics_wms = array_fill_keys($get_data['warehouses_ids'], false);
        }
        
        $statistics_reports = $this->_get_reports($get_data);

        return [
            'wms' => $statistics_wms,
            'wo' => $statistics_reports['statistics'],
            'wo_date' => $statistics_reports['statistics_date'],
        ];
    }
    
    /******************************************************************************************************************/

    public function wms_aap($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article' ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " ISNULL(`total_log`.`old_value`) ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT `total_log`.updated
            , `pba`.`article_id`
            , `vpb`.`ware_loc_new`
            , `vpb`.`parcel`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `v`.`id`
            JOIN `vparcel_barcode` AS `vpb` ON `pba`.`parcel_barcode_id` = `vpb`.`id`
            JOIN `total_log` ON `pba`.`id` = `total_log`.`new_value`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `total_log`.`updated` ASC";

        $result_C = $this->_dbr->getAll($query);
        
        $articles_ids = array();
        foreach ($result_C as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `total_log`.`field_name` = 'parcel_barcode_id' ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " `tl`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `tl`.`field_name` = 'barcode_id' ";
        $where[] = " `tl`.`new_value` ";
        $where[] = " ISNULL(`total_log`.`old_value`) ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        
        $query = "SELECT `total_log`.updated
            , `vbarcode`.`article_id`
            , `vbarcode`.`ware_loc`
            , `vbarcode`.`parcel_barcode`
            , `vbarcode`.`barcode`
			, CONCAT(`w_l`.`ware_char`,
                `ware_loc`.`hall`,
                '-',
                `ware_loc`.`row`,
                '-',
                `ware_loc`.`bay`,
                '-',
                `ware_loc`.`level`) AS `ware_loc_old`
			, CONCAT(`tp`.`code`, '/', LPAD(`pb`.`id`, 10, 0)) AS `parcel_barcode_old`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `total_log` ON `v`.`id` = `total_log`.`new_value`
            JOIN `total_log` AS `tl` ON `tl`.`tableid` = `total_log`.`tableid`
            JOIN `vbarcode` ON `tl`.`new_value` = `vbarcode`.`id`
			LEFT JOIN `parcel_barcode` AS `pb` ON `pb`.`id` = `total_log`.`new_value`
			LEFT JOIN `ware_loc` ON `ware_loc`.`id` = `pb`.`ware_loc_id`
			LEFT JOIN `warehouse` `w_l` ON `ware_loc`.`warehouse_id` = `w_l`.`warehouse_id`
            LEFT JOIN `tn_packets` `tp` ON `tp`.`id` = `pb`.`tn_packet_id`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result_A = $this->_dbr->getAll($query);
        
        foreach ($result_A as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }

        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result_C as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            
            $statistics[] = array(
                'updated' => $_item->updated,
                'article_id' => $_item->article_id,
                'ware_loc' => $_item->ware_loc_new,
                'parcel_barcode' => $_item->parcel,
                'barcode' => 'None',
                'article_name' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['name'] : '',
                'article_volume' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0,
            );
        }
        
        foreach ($result_A as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            
            $statistics[] = array(
                'updated' => $_item->updated,
                'article_id' => $_item->article_id,
                'ware_loc' => $_item->ware_loc ? $_item->ware_loc : $_item->ware_loc_old,
                'parcel_barcode' => $_item->parcel_barcode ? $_item->parcel_barcode : $_item->parcel_barcode_old,
                'barcode' => $_item->barcode,
                'article_name' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['name'] : '',
                'article_volume' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0,
            );
        }

        return $statistics;
    }

    public function wms_dap($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `total_log`.`field_name` = 'parcel_barcode_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " `tl`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `tl`.`field_name` = 'barcode_id' ";
        $where[] = " `tl`.`old_value` ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT `total_log`.updated
            , `vbarcode`.`article_id`
#            , `vbarcode`.`ware_loc`
#            , `vbarcode`.`parcel_barcode`
            , `vbarcode`.`barcode`
			, CONCAT(`w_l`.`ware_char`,
                `ware_loc`.`hall`,
                '-',
                `ware_loc`.`row`,
                '-',
                `ware_loc`.`bay`,
                '-',
                `ware_loc`.`level`) AS `ware_loc`
			, CONCAT(`tp`.`code`, '/', LPAD(`pb`.`id`, 10, 0)) AS `parcel_barcode`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `total_log` ON `v`.`id` = `total_log`.`old_value`
            JOIN `total_log` AS `tl` ON `tl`.`tableid` = `total_log`.`tableid`
            JOIN `vbarcode` ON `tl`.`old_value` = `vbarcode`.`id`
			LEFT JOIN `parcel_barcode` AS `pb` ON `pb`.`id` = `total_log`.`old_value`
			LEFT JOIN `ware_loc` ON `ware_loc`.`id` = `pb`.`ware_loc_id`
			LEFT JOIN `warehouse` `w_l` ON `ware_loc`.`warehouse_id` = `w_l`.`warehouse_id`
            LEFT JOIN `tn_packets` `tp` ON `tp`.`id` = `pb`.`tn_packet_id`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $articles_ids = array();
        foreach ($result as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            
            $statistics[] = array(
                'updated' => $_item->updated,
                'article_id' => $_item->article_id,
                'ware_loc' => $_item->ware_loc,
                'parcel_barcode' => $_item->parcel_barcode,
                'barcode' => $_item->barcode,
                'article_name' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['name'] : '',
                'article_volume' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0,
            );
        }
        
        return $statistics;
    }

    public function wms_apl($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode' ";
        $where[] = " `total_log`.`field_name` = 'warehouse_cell_id' ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " (ISNULL(`total_log`.`old_value`) or `total_log`.`old_value` = '0') ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        $where[] = " `vb`.`warehouse_cell_id` ";
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT `total_log`.`updated`
            , `vb`.`id`
            , `vb`.`warehouse_cell_id`
            , `vb`.`ware_loc_new`
            , `vb`.`parcel`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `vparcel_barcode` AS `vb` ON `vb`.`id` = `v`.`id`
            JOIN `total_log` ON `vb`.`id` = `total_log`.`tableid`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `total_log`.`updated` ASC";

        $result = $this->_dbr->getAll($query);
        
        $_parcels_barecode_ids = array();
        $parcels = array();
        foreach ($result as $_barcode) {
            $_date = strtotime($_barcode->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_barcode->id = (int)$_barcode->id;
            $_barcode->warehouse_cell_id = (int)$_barcode->warehouse_cell_id;
            $_barcode->updated = strtotime($_barcode->updated);
            
            if ($_barcode->id) {
                $_parcels_barecode_ids[] = $_barcode->id;
            }
            
            $index = "{$_barcode->id}.{$_barcode->warehouse_cell_id}";
            if ( ! isset($parcels[$index])) {
                $parcels[$index] = array(
                    'id' => $_barcode->id,
                    'warehouse_cell_id' => $_barcode->warehouse_cell_id,
                    'ware_loc_new' => $_barcode->ware_loc_new,
                    'parcel' => $_barcode->parcel,
                    'updated' => $_barcode->updated,
                );
            }
            
            if ($parcels[$index]['updated'] < $_barcode->updated) {
                $parcels[$index]['updated'] = $_barcode->updated;
            }
            
            if ($parcels[$index]['updated'] < $_barcode->updated) {
                $parcels[$index]['updated'] = $_barcode->updated;
            }

            $parcel_object = new Parcel_Barcode($this->_db, $this->_dbr, $_barcode->id);
            $articles = $parcel_object->get_articles();
            $articles_number = 0;
            $articles_volume = 0.0;
            foreach ($articles as $article) {
                $articles_number += $article->quantity;
                $articles_volume += $article->volume;
            }
            $parcels[$index]['articles_number'] = $articles_number;
            $parcels[$index]['articles_volume'] = $articles_volume;
        }
        
        $statistics = array();
        foreach ($parcels as $_parcel) {

            $statistics[] = array(
                'barcode_id' => $_parcel['id'],
                'warehouse_cell_id' => $_parcel['warehouse_cell_id'],
                'ware_loc_new' => $_parcel['ware_loc_new'],
                'parcel' => $_parcel['parcel'],
                'updated' => date('Y-m-d H:i:s', $_parcel['updated']),
                'articles_number' => $_parcel['articles_number'],
                'articles_volume' => $_parcel['articles_volume'],
            );
        }
        
        return $statistics;
    }

    public function wms_dpl($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode' ";
        $where[] = " `total_log`.`field_name` = 'warehouse_cell_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " (ISNULL(`total_log`.`new_value`) or `total_log`.`new_value` = '0') ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT `total_log`.`updated`
            , `vb`.`id`
            , `vb`.`warehouse_cell_id`
            , `vb`.`ware_loc_new`
            , `vb`.`parcel`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `vparcel_barcode` AS `vb` ON `vb`.`id` = `v`.`id`
            JOIN `total_log` ON `vb`.`id` = `total_log`.`tableid`
            WHERE " . implode(' AND ', $where) . " ORDER BY `total_log`.`updated` ASC";

        $result = $this->_dbr->getAll($query);
        
        $_parcels_barecode_ids = array();
        $parcels = array();
        foreach ($result as $_barcode) {
            $_date = strtotime($_barcode->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                var_dump($result);
                continue;
            }
            
            $_barcode->id = (int)$_barcode->id;
            $_barcode->warehouse_cell_id = (int)$_barcode->warehouse_cell_id;
            $_barcode->updated = strtotime($_barcode->updated);
            
            if ($_barcode->id) {
                $_parcels_barecode_ids[] = $_barcode->id;
            }
            
            $index = "{$_barcode->id}.{$_barcode->warehouse_cell_id}";
            if ( ! isset($parcels[$index])) {
                $parcels[$index] = array(
                    'id' => $_barcode->id,
                    'warehouse_cell_id' => $_barcode->warehouse_cell_id,
                    'ware_loc_new' => $_barcode->ware_loc_new,
                    'parcel' => $_barcode->parcel,
                    'updated' => $_barcode->updated,
                );
            }
            
            if ($parcels[$index]['updated'] < $_barcode->updated) {
                $parcels[$index]['updated'] = $_barcode->updated;
            }
        }
        
        $_parcels_barecode_ids = array_values(array_unique($_parcels_barecode_ids));
        
        $articles = $this->_get_articles_for_parcel($_parcels_barecode_ids, $date);
        
        $articles_data = $this->_get_articles_params($articles['articles_ids']);
        $articles = $articles['return'];
        
        $statistics = array();
        foreach ($parcels as $_parcel) {
            $articles_volume = 0.0;
            if (isset($articles[$_parcel['id']]) && is_array($articles[$_parcel['id']])) {
                foreach ($articles[$_parcel['id']] as $_id) {
                    if (isset($articles_data[$_id])) {
                        $articles_volume += $articles_data[$_id]['volume'];
                    }
                }
            }

            $statistics[] = array(
                'barcode_id' => $_parcel['id'],
                'warehouse_cell_id' => $_parcel['warehouse_cell_id'],
                'ware_loc_new' => $_parcel['ware_loc_new'],
                'parcel' => $_parcel['parcel'],
                'updated' => date('Y-m-d H:i:s', $_parcel['updated']),
                'articles_number' => isset($articles[$_parcel['id']]) ? count($articles[$_parcel['id']]) : 0.0,
                'articles_volume' => $articles_volume,
            );
        }
        
        return $statistics;
    }

    public function wms_pa($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'tn_orders' ";
        $where[] = " `orders`.`reserve_warehouse_id` = {$get_data['warehouse']} ";
        
        $query = "SELECT `total_log`.`updated`
            , `orders`.`article_id`
            , `orders`.`auction_number`
            , `orders`.`txnid`
            , `orders`.`id` AS `order_id`
            , `auction`.`main_auction_number`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `tn_orders` ON `tn_orders`.`id` = `total_log`.`TableID`
            JOIN `orders` ON `orders`.`id` = `tn_orders`.`order_id`
            JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid` 
            WHERE " . implode(" AND \n ", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $statistics = array();
        $articles_ids = array();
        foreach ($result as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        $articles = $this->_get_articles_params($articles_ids);
        
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $statistics[] = array(
                'updated' => $_item->updated,
                'order_id' => (int)$_item->order_id,
                'barcode' => $this->_get_barcode_for_article((int)$_item->order_id),
                'article_id' => (int)$_item->article_id,
                'article_name' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['name'] : '',
                'article_volume' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0,
                'main_auction_number' => $_item->main_auction_number,
                'auction_number' => $_item->auction_number,
                'txnid' => $_item->txnid,
            );
        }
        
        return $statistics;
    }

    public function wms_sa($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'orders' ";
        $where[] = " `total_log`.`field_name` = 'sent' ";
        $where[] = " `total_log`.`New_value` = '1' ";
        $where[] = " `orders`.`send_warehouse_id` = {$get_data['warehouse']} ";
        
        $query = "SELECT `total_log`.`updated`
            , `orders`.`id` AS `order_id`
            , `orders`.`article_id`
            , `orders`.`auction_number`
            , `orders`.`txnid`
            , `auction`.`main_auction_number`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `orders` ON `orders`.`id` = `total_log`.`TableID`
            JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid` 
            WHERE " . implode(' AND ', $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $statistics = array();
        $articles_ids = array();
        foreach ($result as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        $articles = $this->_get_articles_params($articles_ids);
        
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $statistics[] = array(
                'updated' => $_item->updated,
                'order_id' => (int)$_item->order_id,
                'barcode' => $this->_get_barcode_for_article((int)$_item->order_id),
                'article_id' => (int)$_item->article_id,
                'article_name' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['name'] : '',
                'article_volume' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0,
                'main_auction_number' => $_item->main_auction_number,
                'auction_number' => $_item->auction_number,
                'txnid' => $_item->txnid,
            );
        }
        
        return $statistics;
    }

    public function wms_li($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'mobile_loading_list' ";
        $where[] = " `total_log`.`field_name` = 'id' ";
        $where[] = " `mll`.`warehouse_id` = {$get_data['warehouse']} ";
        
        $query = "SELECT `total_log`.`updated`
            , `mll`.`id` AS `list_id`
            , `mll`.`warehouse_id` AS `warehouse_id`
            , `mll`.`method_id` AS `method_id`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `mobile_loading_list` AS `mll` ON `mll`.`id` = `total_log`.`TableID`
            WHERE " . implode(' AND ', $where);
        
        $statistics = array();
        foreach ($this->_dbr->getAll($query) as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
//            $_item->tns = $this->_dbr->getAssoc("SELECT tracking_number f1, tracking_number f2
//                from mobile_loading_list_tn
//                where ll_id=".(int)$_item->list_id);
		    $statistics[] = $_item;
        }
        
        return $statistics;
    }
    
    public function wms_ma($get_data) {
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `total_log`.`field_name` = 'parcel_barcode_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " `tl`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `tl`.`field_name` = 'barcode_id' ";
        $where[] = " `tl`.`new_value` ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        
        $query = "SELECT `total_log`.updated
            , `vbarcode`.`article_id`
            , `vbarcode`.`ware_loc`
            , `vbarcode`.`parcel_barcode`
            , `vbarcode`.`barcode`
            , CONCAT(`w_l`.`ware_char`,
                `ware_loc`.`hall`,
                '-',
                `ware_loc`.`row`,
                '-',
                `ware_loc`.`bay`,
                '-',
                `ware_loc`.`level`) AS `ware_loc_old`
            , CONCAT(`tp`.`code`, '/', LPAD(`pb`.`id`, 10, 0)) AS `parcel_barcode_old`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `total_log` ON `v`.`id` = `total_log`.`new_value`
            JOIN `total_log` AS `tl` ON `tl`.`tableid` = `total_log`.`tableid`
            JOIN `vbarcode` ON `tl`.`new_value` = `vbarcode`.`id`
            LEFT JOIN `parcel_barcode` AS `pb` ON `pb`.`id` = `total_log`.`new_value`
            LEFT JOIN `ware_loc` ON `ware_loc`.`id` = `pb`.`ware_loc_id`
            LEFT JOIN `warehouse` `w_l` ON `ware_loc`.`warehouse_id` = `w_l`.`warehouse_id`
            LEFT JOIN `tn_packets` `tp` ON `tp`.`id` = `pb`.`tn_packet_id`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result_A = $this->_dbr->getAll($query);
        
        foreach ($result_A as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }

        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result_A as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            
            $statistics[] = array(
                'updated' => $_item->updated,
                'article_id' => $_item->article_id,
                'ware_loc' => $_item->ware_loc ? $_item->ware_loc : $_item->ware_loc_old,
                'parcel_barcode' => $_item->parcel_barcode ? $_item->parcel_barcode : $_item->parcel_barcode_old,
                'barcode' => $_item->barcode,
                'article_name' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['name'] : '',
                'article_volume' => isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0,
            );
        }

        return $statistics;
    }

    public function wms_mp($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode' ";
        $where[] = " `total_log`.`field_name` = 'warehouse_cell_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " v.warehouse_id = {$get_data['warehouse']} ";
        $where[] = " `vb`.`warehouse_cell_id` ";
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT `total_log`.`updated`
            , `vb`.`id`
            , `vb`.`warehouse_cell_id`
            , `vb`.`ware_loc_new`
            , `vb`.`parcel`
            , DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `vparcel_barcode` AS `vb` ON `vb`.`id` = `v`.`id`
            JOIN `total_log` ON `vb`.`id` = `total_log`.`tableid`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `total_log`.`updated` ASC";

        $result = $this->_dbr->getAll($query);
        
        $_parcels_barecode_ids = array();
        $parcels = array();
        foreach ($result as $_barcode) {
            $_date = strtotime($_barcode->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_barcode->id = (int)$_barcode->id;
            $_barcode->warehouse_cell_id = (int)$_barcode->warehouse_cell_id;
            $_barcode->updated = strtotime($_barcode->updated);
            
            if ($_barcode->id) {
                $_parcels_barecode_ids[] = $_barcode->id;
            }
            
            $index = "{$_barcode->id}.{$_barcode->warehouse_cell_id}";
            if ( ! isset($parcels[$index])) {
                $parcels[$index] = array(
                    'id' => $_barcode->id,
                    'warehouse_cell_id' => $_barcode->warehouse_cell_id,
                    'ware_loc_new' => $_barcode->ware_loc_new,
                    'parcel' => $_barcode->parcel,
                    'updated' => $_barcode->updated,
                );
            }
            
            if ($parcels[$index]['updated'] < $_barcode->updated) {
                $parcels[$index]['updated'] = $_barcode->updated;
            }
            
            if ($parcels[$index]['updated'] < $_barcode->updated) {
                $parcels[$index]['updated'] = $_barcode->updated;
            }

            $parcel_object = new Parcel_Barcode($this->_db, $this->_dbr, $_barcode->id);
            $articles = $parcel_object->get_articles();
            $articles_number = 0;
            $articles_volume = 0.0;
            foreach ($articles as $article) {
                $articles_number += $article->quantity;
                $articles_volume += $article->volume;
            }
            $parcels[$index]['articles_number'] = $articles_number;
            $parcels[$index]['articles_volume'] = $articles_volume;
        }
        
        $statistics = array();
        foreach ($parcels as $_parcel) {

            $statistics[] = array(
                'barcode_id' => $_parcel['id'],
                'warehouse_cell_id' => $_parcel['warehouse_cell_id'],
                'ware_loc_new' => $_parcel['ware_loc_new'],
                'parcel' => $_parcel['parcel'],
                'updated' => date('Y-m-d H:i:s', $_parcel['updated']),
                'articles_number' => $_parcel['articles_number'],
                'articles_volume' => $_parcel['articles_volume'],
            );
        }
        
        return $statistics;
    }

    /******************************************************************************************************************/
    
    public function wo_in_container($get_data) {
        $statistics = array();
        
        $date_to = mysql_real_escape_string($get_data['date_to']);
        $date_from = mysql_real_escape_string($get_data['date_from']);
        
        $query = "SELECT
                `op`.`op_order_id`,
                `op`.`article_id`,
                `op`.`add_to_warehouse_date`,
                `op`.`container_id`,
               IF(`opc`.`master_id`, concat('(', `opc_master`.`container_no`, ')'), `opc`.`container_no`) AS `container_name`
            FROM `op_article` AS `op`
                LEFT JOIN `op_order_container` AS `opc` ON `opc`.`id` = `op`.`container_id`
                LEFT JOIN `op_order_container` AS `opc_master` ON `opc_master`.`id` = `opc`.`master_id`
            WHERE `op`.`warehouse_id` = {$get_data['warehouse']} 
                AND `op`.`add_to_warehouse_date` >= '$date_from 00:00:00' 
                AND `op`.`add_to_warehouse_date` <= '$date_to 23:59:59'
                AND NOT `opc`.`master_id`
            GROUP BY `container_id`";
             
        $articles_ids = array();
        foreach ($this->_dbr->getAll($query) as $item) {
            $query = "SELECT
                    SUM(op.qnt_delivered) AS qnt_delivered,
                    SUM((op.qnt_delivered * a.volume_per_single_unit)) AS volume
                FROM op_article AS op
                    LEFT JOIN `op_order_container` AS `opc` ON `opc`.`id` = `op`.`container_id`
                    LEFT JOIN `op_order_container` AS `opc_master` ON `opc_master`.`id` = `opc`.`master_id`
                    LEFT JOIN article AS a ON a.article_id = op.article_id AND a.admin_id = 0
                WHERE 
                    IFNULL(opc_master.id, opc.id) = '{$item->container_id}'
                    AND `op`.`add_to_warehouse_date` >= '$date_from 00:00:00' 
                    AND `op`.`add_to_warehouse_date` <= '$date_to 23:59:59'
                    AND op.add_to_warehouse = 1";
            $container_data = $this->_dbr->getRow($query);

            $statistics[(int)$item->container_id] = array(
                'op_order_id' => (int)$item->op_order_id,
                'container_id' => (int)$item->container_id,
                'container_name' => $item->container_name,
                'add_to_warehouse_date' => date('Y-m-d H:i', strtotime($item->add_to_warehouse_date)),
                'articles' => array(),
                'articles_count' => $container_data->qnt_delivered,
                'articles_volume' => $container_data->volume,
            );
        }

        return $statistics;
    }

    public function wo_in_wwo($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);

        $query = "SELECT `wwo`.`wwo_id`, `wwo`.`article_id`, `wwo`.`qnt`, `total_log`.*, 
                (SELECT CONCAT('Delivered on ', `tl`.`Updated`, ' by ', IFNULL(`users`.`name`, `tl`.`username`))
                    FROM `total_log` AS `tl`
                    LEFT JOIN `users` ON `users`.`system_username` = `tl`.`username`
                    WHERE `tl`.`id` = `total_log`.`id` limit 1) AS `delivered_text`, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `wwo_article` AS `wwo` ON `wwo`.`id` = `total_log`.`TableID`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE `total_log`.`table_name` = 'wwo_article' AND `total_log`.`field_name` in ('to_warehouse', 'delivered')
                    AND " . implode(' AND ', $where) . " ORDER BY `total_log`.`id` DESC";
        
        $articles_ids = array();
        $delivered = array();
        foreach ($this->_dbr->getAll($query) as $item) {
            $_date = strtotime($item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $item->ID = (int)$item->ID;
            $item->TableID = (int)$item->TableID;
            $item->New_value = (int)$item->New_value;
            $item->Updated = strtotime($item->Updated);
            
            if ( ! isset($delivered[$item->TableID])) {
                $delivered[$item->TableID] = array(
                    'table_id' => $item->TableID,
                    'article_id' => (int)$item->article_id,
                    'article_qnt' => (int)$item->qnt,
                    'article_volume' => 0,
                    'article_name' => 0,
                    'wwo_id' => (int)$item->wwo_id,
                    'username' => $item->delivered_text,
                    'updated_delivered' => 0,
                    'id' => $item->ID,
                    'to_warehouse' => 0,
                    'updated_warehouse' => 0,
                    'delivered' => -1,
                );
            }
            
            $articles_ids[] = (int)$item->article_id;
            
            if ($item->Field_name == 'delivered') {
                $delivered[$item->TableID]['id'] = min($delivered[$item->TableID]['id'], $item->ID);
                
                if ($delivered[$item->TableID]['updated_delivered'] <= $item->Updated) {
                    $delivered[$item->TableID]['updated_delivered'] = $item->Updated;
                    $delivered[$item->TableID]['delivered'] = $item->New_value;
                    $delivered[$item->TableID]['username'] = $item->delivered_text;
                }
            }
            else {
                if ($delivered[$item->TableID]['updated_warehouse'] <= $item->Updated) {
                    $delivered[$item->TableID]['updated_warehouse'] = $item->Updated;
                    $delivered[$item->TableID]['to_warehouse'] = $item->New_value;
                }
            }
        }
        
        $articles = $this->_get_articles_params($articles_ids);

        $statistics = array();
        foreach ($delivered as $table_id => $_data) {
            if ($_data['delivered'] == -1) {
                $_data['delivered'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'delivered' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ( ! $_data['delivered']) {
                continue;
            }
            
            if ( ! $_data['to_warehouse']) {
                $_data['to_warehouse'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'to_warehouse' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ($_data['to_warehouse'] != $get_data['warehouse']) {
                continue;
            }
            
            if (isset($articles[$_data['article_id']])) {
                $_data['article_volume'] = $_data['article_qnt'] * $articles[$_data['article_id']]['volume'];
                $_data['article_name'] = $articles[$_data['article_id']]['name'];
            }
            
            $statistics[$_data['wwo_id']][$_data['article_id']] = $_data;
        }
        
        return $statistics;
    }

    public function wo_out_wwo($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);

        $query = "SELECT `wwo`.`wwo_id`, `wwo`.`article_id`, `wwo`.`qnt`, `total_log`.*, 
                (SELECT CONCAT('Taken on ', `tl`.`Updated`, ' by ', IFNULL(`users`.`name`, `tl`.`username`))
                    FROM `total_log` AS `tl`
                    LEFT JOIN `users` ON `users`.`system_username` = `tl`.`username`
                    WHERE `tl`.`id` = `total_log`.`id` limit 1) AS `taken_text`, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `wwo_article` AS `wwo` ON `wwo`.`id` = `total_log`.`TableID`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE `total_log`.`table_name` = 'wwo_article' AND `total_log`.`field_name` in ('from_warehouse', 'taken')
                    AND " . implode(' AND ', $where) . " ORDER BY `total_log`.`id` DESC";
        
        $articles_ids = array();
        $taken = array();
        foreach ($this->_dbr->getAll($query) as $item) {
            $_date = strtotime($item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $item->ID = (int)$item->ID;
            $item->TableID = (int)$item->TableID;
            $item->New_value = (int)$item->New_value;
            $item->Updated = strtotime($item->Updated);
            
            if ( ! isset($taken[$item->TableID])) {
                $taken[$item->TableID] = array(
                    'table_id' => $item->TableID,
                    'article_id' => (int)$item->article_id,
                    'article_volume' => 0,
                    'article_qnt' => (int)$item->qnt,
                    'wwo_id' => (int)$item->wwo_id,
                    'username' => $item->taken_text,
                    'updated_taken' => 0,
                    'id' => $item->ID,
                    'from_warehouse' => 0,
                    'updated_warehouse' => 0,
                    'taken' => -1,
                );
            }
            
            $articles_ids[] = (int)$item->article_id;
            
            if ($item->Field_name == 'taken') {
                $taken[$item->TableID]['id'] = min($taken[$item->TableID]['id'], $item->ID);
                
                if ($taken[$item->TableID]['updated_taken'] <= $item->Updated) {
                    $taken[$item->TableID]['updated_taken'] = $item->Updated;
                    $taken[$item->TableID]['taken'] = $item->New_value;
                    $taken[$item->TableID]['username'] = $item->taken_text;
                }
            }
            else {
                if ($taken[$item->TableID]['updated_warehouse'] <= $item->Updated) {
                    $taken[$item->TableID]['updated_warehouse'] = $item->Updated;
                    $taken[$item->TableID]['from_warehouse'] = $item->New_value;
                }
            }
        }
        
        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($taken as $table_id => $_data) {
            if ($_data['taken'] == -1) {
                $_data['taken'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'taken' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ( ! $_data['taken']) {
                continue;
            }
            
            if ( ! $_data['from_warehouse']) {
                $_data['from_warehouse'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'from_warehouse' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ($_data['from_warehouse'] != $get_data['warehouse']) {
                continue;
            }
            
            if (isset($articles[$_data['article_id']])) {
                $_data['article_volume'] = $_data['article_qnt'] * $articles[$_data['article_id']]['volume'];
                $_data['article_name'] = $articles[$_data['article_id']]['name'];
            }
            
            $statistics[$_data['wwo_id']][$_data['article_id']] = $_data;
        }
        
        return $statistics;
    }

    public function wo_out_orders($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);

        $query = "SELECT `orders`.`id`, `orders`.`quantity`, `orders`.`auction_number`, `orders`.`txnid`, 
                    `orders`.`article_id`, `orders`.`send_warehouse_id`, `auction`.`main_auction_number`, 
                    IFNULL(`users`.`name`, `total_log`.`username`) AS `username`, `total_log`.`updated`, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
                FROM `total_log`
                LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
                JOIN `orders` ON `orders`.`id` = `total_log`.`TableID`
                JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid` 
                WHERE " . implode(' AND ', $where) . " AND `table_name` = 'orders' 
                    AND `field_name` = 'sent' AND `new_value` = '1'
                    AND `orders`.`send_warehouse_id` = {$get_data['warehouse']} AND `orders`.`article_id` > 0";
                    
        $result = $this->_dbr->getAll($query);
        
        $articles_ids = array();
        foreach ($result as $_order) {
            $articles_ids[] = $_order->article_id;
        }
        
        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        
        foreach ($result as $_order) {
            $_date = strtotime($_order->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            if ($_order->main_auction_number) {
                $index = "{$_order->main_auction_number}/{$_order->txnid}";
            }
            else {
                $index = "{$_order->auction_number}/{$_order->txnid}";
            }
            
            if ( ! isset($statistics[$index])) {
                $statistics[$index] = array(
                    'id' => $_order->id,
                    'main_auction_number' => $_order->main_auction_number,
                    'auction_number' => $_order->auction_number,
                    'txnid' => $_order->txnid,
                    'articles' => array(),
                );
            }
            
            $statistics[$index]['articles'][] = array(
                'article_id' => $_order->article_id,
                'quantity' => (int)$_order->quantity,
                'article_name' => isset($articles[$_order->article_id]) ? $articles[$_order->article_id]['name'] : '',
                'article_volume' => isset($articles[$_order->article_id]) ? $_order->quantity * $articles[$_order->article_id]['volume'] : 0.0,
                'send_warehouse_id' => $_order->send_warehouse_id,
                'username' => $_order->username,
                'updated' => $_order->updated,
            );
        }
        
        return $statistics;
    }

    public function wo_emploees($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);

        $query = "SELECT DISTINCT IFNULL(`users`.`name`, `total_log`.`username`) AS `username`, 
            DATE_FORMAT(`total_log`.`updated`, '%Y-%m-%d') AS `updated`
                FROM `total_log`
                LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
                JOIN `user_timestamp` ON `user_timestamp`.`id` = `total_log`.`TableID`
                WHERE " . implode(' AND ', $where) . " 
                    AND `table_name` = 'user_timestamp' 
                    AND `field_name` = 'id' 
                    AND `user_timestamp`.`login` AND `user_timestamp`.`warehouse_id` = {$get_data['warehouse']}";
        return $this->_dbr->getAll($query);
    }

    /******************************************************************************************************************/
    
    private function _get_wms($get_data) {
        $statistics_wms_aap = array();
        $statistics_wms_dap = array();
        $statistics_wms_apl = array();
        $statistics_wms_dpl = array();
        $statistics_wms_pa = array();
        $statistics_wms_sa = array();
        $statistics_wms_li = array();
        $statistics_wms_ma = array();
        $statistics_wms_mp = array();
        
        if (in_array('aap', $get_data['actions'])) {
            $statistics_wms_aap = $this->_get_wms_aap($get_data);
        }
        if (in_array('dap', $get_data['actions'])) {
            $statistics_wms_dap = $this->_get_wms_dap($get_data);
        }
        if (in_array('apl', $get_data['actions'])) {
            $statistics_wms_apl = $this->_get_wms_apl($get_data);
        }
        if (in_array('dpl', $get_data['actions'])) {
            $statistics_wms_dpl = $this->_get_wms_dpl($get_data);
        }
        if (in_array('pa', $get_data['actions'])) {
            $statistics_wms_pa = $this->_get_wms_pa($get_data);
        }
        if (in_array('sa', $get_data['actions'])) {
            $statistics_wms_sa = $this->_get_wms_sa($get_data);
        }
        if (in_array('li', $get_data['actions'])) {
            $statistics_wms_li = $this->_get_wms_li($get_data);
        }
        if (in_array('ma', $get_data['actions'])) {
            $statistics_wms_ma = $this->_get_wms_ma($get_data);
        }
        if (in_array('mp', $get_data['actions'])) {
            $statistics_wms_mp = $this->_get_wms_mp($get_data);
        }

        $warehouses = array_merge(array_keys($statistics_wms_aap), 
                array_keys($statistics_wms_dap), 
                array_keys($statistics_wms_apl), 
                array_keys($statistics_wms_dpl), 
                array_keys($statistics_wms_pa), 
                array_keys($statistics_wms_sa), 
                array_keys($statistics_wms_li), 
                array_keys($statistics_wms_ma), 
                array_keys($statistics_wms_mp));
        $warehouses = array_values(array_unique($warehouses));
        
        $statistics = array();
        foreach ($warehouses as $warehouse_id) {
            $statistics[$warehouse_id] = array();
            
            $_statistics_wms_aap = isset($statistics_wms_aap[$warehouse_id]) ? $statistics_wms_aap[$warehouse_id] : array();
            $_statistics_wms_dap = isset($statistics_wms_dap[$warehouse_id]) ? $statistics_wms_dap[$warehouse_id] : array();
            $_statistics_wms_apl = isset($statistics_wms_apl[$warehouse_id]) ? $statistics_wms_apl[$warehouse_id] : array();
            $_statistics_wms_dpl = isset($statistics_wms_dpl[$warehouse_id]) ? $statistics_wms_dpl[$warehouse_id] : array();
            $_statistics_wms_pa = isset($statistics_wms_pa[$warehouse_id]) ? $statistics_wms_pa[$warehouse_id] : array();
            $_statistics_wms_sa = isset($statistics_wms_sa[$warehouse_id]) ? $statistics_wms_sa[$warehouse_id] : array();
            $_statistics_wms_li = isset($statistics_wms_li[$warehouse_id]) ? $statistics_wms_li[$warehouse_id] : array();
            $_statistics_wms_ma = isset($statistics_wms_ma[$warehouse_id]) ? $statistics_wms_ma[$warehouse_id] : array();
            $_statistics_wms_mp = isset($statistics_wms_mp[$warehouse_id]) ? $statistics_wms_mp[$warehouse_id] : array();
            
            $usernames = array_merge(array_keys($_statistics_wms_aap), 
                    array_keys($_statistics_wms_dap), 
                    array_keys($_statistics_wms_apl), 
                    array_keys($_statistics_wms_dpl), 
                    array_keys($_statistics_wms_pa), 
                    array_keys($_statistics_wms_sa), 
                    array_keys($_statistics_wms_li), 
                    array_keys($_statistics_wms_ma), 
                    array_keys($_statistics_wms_mp));
            $usernames = array_values(array_unique($usernames));
            sort($usernames);
            
            foreach ($usernames as $user) {
                $statistics[$warehouse_id][$user] = array(
                    'aap' => [
                        'number' => isset($_statistics_wms_aap[$user]['number']) ? $_statistics_wms_aap[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_aap[$user]['volume']) ? $_statistics_wms_aap[$user]['volume'] : 0,
                    ],
                    'dap' => [
                        'number' => isset($_statistics_wms_dap[$user]['number']) ? $_statistics_wms_dap[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_dap[$user]['volume']) ? $_statistics_wms_dap[$user]['volume'] : 0,
                    ],
                    'apl' => [
                        'number' => isset($_statistics_wms_apl[$user]['number']) ? $_statistics_wms_apl[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_apl[$user]['volume']) ? $_statistics_wms_apl[$user]['volume'] : 0,
                    ],
                    'dpl' => [
                        'number' => isset($_statistics_wms_dpl[$user]['number']) ? $_statistics_wms_dpl[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_dpl[$user]['volume']) ? $_statistics_wms_dpl[$user]['volume'] : 0,
                    ],
                    'pa' => [
                        'number' => isset($_statistics_wms_pa[$user]['number']) ? $_statistics_wms_pa[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_pa[$user]['volume']) ? $_statistics_wms_pa[$user]['volume'] : 0,
                    ],
                    'sa' => [
                        'number' => isset($_statistics_wms_sa[$user]['number']) ? $_statistics_wms_sa[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_sa[$user]['volume']) ? $_statistics_wms_sa[$user]['volume'] : 0,
                    ],
                    'ma' => [
                        'number' => isset($_statistics_wms_ma[$user]['number']) ? $_statistics_wms_ma[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_ma[$user]['volume']) ? $_statistics_wms_ma[$user]['volume'] : 0,
                    ],
                    'mp' => [
                        'number' => isset($_statistics_wms_mp[$user]['number']) ? $_statistics_wms_mp[$user]['number'] : 0,
                        'volume' => isset($_statistics_wms_mp[$user]['volume']) ? $_statistics_wms_mp[$user]['volume'] : 0,
                    ],
                    'li' => isset($_statistics_wms_li[$user]) ? $_statistics_wms_li[$user] : 0,
                );
            }
        }
        
        return $statistics;
    }
    
    private function _get_wms_aap($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article' ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " ISNULL(`total_log`.`old_value`) ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT `pba`.`article_id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `v`.`id`
            JOIN `total_log` ON `pba`.`id` = `total_log`.`new_value`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `username` ASC";
        
        $result_C = $this->_dbr->getAll($query);
        
        $articles_ids = array();
        foreach ($result_C as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `total_log`.`field_name` = 'parcel_barcode_id' ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " `tl`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `tl`.`field_name` = 'barcode_id' ";
        $where[] = " `tl`.`new_value` ";
        $where[] = " ISNULL(`total_log`.`old_value`) ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT `vbarcode`.`article_id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `total_log` ON `v`.`id` = `total_log`.`new_value`
            JOIN `total_log` AS `tl` ON `tl`.`tableid` = `total_log`.`tableid`
            JOIN `vbarcode` ON `tl`.`new_value` = `vbarcode`.`id`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `username` ASC";
        
        $result_A = $this->_dbr->getAll($query);
        
        foreach ($result_A as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }

        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result_C as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            $_item->warehouse_id = (int)$_item->warehouse_id;
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0;
        }
        
        foreach ($result_A as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            $_item->warehouse_id = (int)$_item->warehouse_id;
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0;
        }
        
        return $statistics;
    }
    
    private function _get_wms_dap($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `total_log`.`field_name` = 'parcel_barcode_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " `tl`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `tl`.`field_name` = 'barcode_id' ";
        $where[] = " `tl`.`old_value` ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT `vbarcode`.`article_id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `total_log` ON `v`.`id` = `total_log`.`old_value`
            JOIN `total_log` AS `tl` ON `tl`.`tableid` = `total_log`.`tableid`
            JOIN `vbarcode` ON `tl`.`old_value` = `vbarcode`.`id`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `username` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $articles_ids = array();
        foreach ($result as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }

        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            $_item->warehouse_id = (int)$_item->warehouse_id;
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0;
        }
        
        return $statistics;
    }
    
    private function _get_wms_apl($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);

        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode' ";
        $where[] = " `total_log`.`field_name` = 'warehouse_cell_id' ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " (ISNULL(`total_log`.`old_value`) or `total_log`.`old_value` = '0') ";
        $where[] = " `vb`.`warehouse_cell_id` ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT distinct `vb`.`id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `vparcel_barcode` AS `vb` ON `vb`.`id` = `v`.`id`
            JOIN `total_log` ON `vb`.`id` = `total_log`.`tableid`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n ", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $_parcels_barecode_ids = array();
        $parcels = array();
        foreach ($result as $_barcode) {
            if ($_barcode->id) {
                $_parcels_barecode_ids[] = $_barcode->id;
            }
        }
        $_parcels_barecode_ids = array_values(array_unique($_parcels_barecode_ids));
        
        $articles = $this->_get_articles_for_parcel($_parcels_barecode_ids, $date);

        $articles_data = $this->_get_articles_params($articles['articles_ids']);
        $articles = $articles['return'];
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $articles_volume = 0.0;
            
            if (isset($articles[$_item->id]) && is_array($articles[$_item->id])) {
                foreach ($articles[$_item->id] as $_id) {
                    if (isset($articles_data[$_id])) {
                        $articles_volume += $articles_data[$_id]['volume'];
                    }
                }
            }
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += $articles_volume;
        }
        
        return $statistics;
    }
    
    private function _get_wms_dpl($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);

        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode' ";
        $where[] = " `total_log`.`field_name` = 'warehouse_cell_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " (ISNULL(`total_log`.`new_value`) or `total_log`.`new_value` = '0') ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT distinct `vb`.`id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `vparcel_barcode` AS `vb` ON `vb`.`id` = `v`.`id`
            JOIN `total_log` ON `vb`.`id` = `total_log`.`tableid`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n ", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $_parcels_barecode_ids = array();
        $parcels = array();
        foreach ($result as $_barcode) {
            if ($_barcode->id) {
                $_parcels_barecode_ids[] = $_barcode->id;
            }
        }
        $_parcels_barecode_ids = array_values(array_unique($_parcels_barecode_ids));
        
        $articles = $this->_get_articles_for_parcel($_parcels_barecode_ids, $date);

        $articles_data = $this->_get_articles_params($articles['articles_ids']);
        $articles = $articles['return'];
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $articles_volume = 0.0;
            
            if (isset($articles[$_item->id]) && is_array($articles[$_item->id])) {
                foreach ($articles[$_item->id] as $_id) {
                    if (isset($articles_data[$_id])) {
                        $articles_volume += $articles_data[$_id]['volume'];
                    }
                }
            }
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += $articles_volume;
        }
        
        return $statistics;
    }
    
    private function _get_wms_pa($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'tn_orders' ";
        if ($get_data['warehouses_ids']) {
            $where[] = " `orders`.`reserve_warehouse_id` IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT `orders`.`article_id`, `orders`.`reserve_warehouse_id` AS `warehouse_id`, `total_log`.`username`, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `tn_orders` ON `tn_orders`.`id` = `total_log`.`TableID`
            JOIN `orders` ON `orders`.`id` = `tn_orders`.`order_id`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(' AND ', $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $articles_ids = array();
        foreach ($result as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            $_item->warehouse_id = (int)$_item->warehouse_id;
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0;
        }
        
        return $statistics;
    }
    
    private function _get_wms_sa($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'orders' ";
        $where[] = " `total_log`.`field_name` = 'sent' ";
        $where[] = " `total_log`.`New_value` = '1' ";
        $where[] = " `orders`.`send_warehouse_id` > 0 ";
        if ($get_data['warehouses_ids']) {
            $where[] = " `orders`.`send_warehouse_id` IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT `orders`.`article_id`, `orders`.`send_warehouse_id` AS `warehouse_id`, `total_log`.`username`, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `orders` ON `orders`.`id` = `total_log`.`TableID`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(' AND ', $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $statistics = array();
        $articles_ids = array();
        foreach ($result as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }
        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            $_item->warehouse_id = (int)$_item->warehouse_id;
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0;
        }
        
        return $statistics;
    }
    
    private function _get_wms_li($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'mobile_loading_list' ";
        $where[] = " `total_log`.`field_name` = 'id' ";
        if ($get_data['warehouses_ids']) {
            $where[] = " `mll`.`warehouse_id` IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT count(*) AS `li`, `mll`.`warehouse_id`, `total_log`.`username`, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `mobile_loading_list` AS `mll` ON `mll`.`id` = `total_log`.`TableID`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(' AND ', $where) . " GROUP BY `warehouse_id`, `username`";

        $statistics = array();
        foreach ($this->_dbr->getAll($query) as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $statistics[(int)$_item->warehouse_id][$_item->username] = (int)$_item->li;
        }
        
        return $statistics;
    }

    private function _get_wms_ma($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);

        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `total_log`.`field_name` = 'parcel_barcode_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " `tl`.`table_name` = 'parcel_barcode_article_barcode' ";
        $where[] = " `tl`.`field_name` = 'barcode_id' ";
        $where[] = " `tl`.`new_value` ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT `vbarcode`.`article_id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `total_log` ON `v`.`id` = `total_log`.`new_value`
            JOIN `total_log` AS `tl` ON `tl`.`tableid` = `total_log`.`tableid`
            JOIN `vbarcode` ON `tl`.`new_value` = `vbarcode`.`id`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n", $where) . " ORDER BY `username` ASC";
        
        $result_A = $this->_dbr->getAll($query);
        
        foreach ($result_A as $_item) {
            $articles_ids[] = (int)$_item->article_id;
        }

        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        
        foreach ($result_A as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_item->article_id = (int)$_item->article_id;
            $_item->warehouse_id = (int)$_item->warehouse_id;
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += isset($articles[$_item->article_id]) ? $articles[$_item->article_id]['volume'] : 0.0;
        }

        return $statistics;
    }

    private function _get_wms_mp($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);

        $where = $this->_set_where_total_log($get_data);
        $where[] = " `total_log`.`table_name` = 'parcel_barcode' ";
        $where[] = " `total_log`.`field_name` = 'warehouse_cell_id' ";
        $where[] = " `total_log`.`old_value` ";
        $where[] = " `total_log`.`new_value` ";
        $where[] = " `vb`.`warehouse_cell_id` ";
        
        if ($get_data['warehouses_ids']) {
            $where[] = " v.warehouse_id IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $date = mysql_real_escape_string($get_data['date_to']);
        
        $query = "SELECT distinct `vb`.`id`, `total_log`.`username`, v.warehouse_id, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
            JOIN `vparcel_barcode` AS `vb` ON `vb`.`id` = `v`.`id`
            JOIN `total_log` ON `vb`.`id` = `total_log`.`tableid`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE " . implode(" AND \n ", $where) . " ORDER BY `total_log`.`updated` ASC";
        
        $result = $this->_dbr->getAll($query);
        
        $_parcels_barecode_ids = array();
        $parcels = array();
        foreach ($result as $_barcode) {
            if ($_barcode->id) {
                $_parcels_barecode_ids[] = $_barcode->id;
            }
        }
        $_parcels_barecode_ids = array_values(array_unique($_parcels_barecode_ids));
        
        $articles = $this->_get_articles_for_parcel($_parcels_barecode_ids, $date);

        $articles_data = $this->_get_articles_params($articles['articles_ids']);
        $articles = $articles['return'];
        
        $statistics = array();
        foreach ($result as $_item) {
            $_date = strtotime($_item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $articles_volume = 0.0;
            
            if (isset($articles[$_item->id]) && is_array($articles[$_item->id])) {
                foreach ($articles[$_item->id] as $_id) {
                    if (isset($articles_data[$_id])) {
                        $articles_volume += $articles_data[$_id]['volume'];
                    }
                }
            }
            
            if ( ! isset($statistics[$_item->warehouse_id][$_item->username])) {
                $statistics[$_item->warehouse_id][$_item->username] = array(
                    'number' => 0,
                    'volume' => 0.0,
                );
            }
            
            $statistics[$_item->warehouse_id][$_item->username]['number'] += 1;
            $statistics[$_item->warehouse_id][$_item->username]['volume'] += $articles_volume;
        }
        
        return $statistics;
    }
    
    /******************************************************************************************************************/
    
    public function _get_reports($get_data) {
        $statistics_in_container = $this->_get_reports_in_container($get_data);
        $statistics_in_container_date = $statistics_in_container['statistics_date'];
        $statistics_in_container = $statistics_in_container['statistics'];
        $statistics_in_wwo = $this->_get_reports_in_wwo($get_data);
        $statistics_in_wwo_date = $statistics_in_wwo['statistics_date'];
        $statistics_in_wwo = $statistics_in_wwo['statistics'];
        $statistics_out_wwo = $this->_get_reports_out_wwo($get_data);
        $statistics_out_wwo_date = $statistics_out_wwo['statistics_date'];
        $statistics_out_wwo = $statistics_out_wwo['statistics'];
        $statistics_out_orders = $this->_get_reports_out_orders($get_data);
        $statistics_out_orders_date = $statistics_out_orders['statistics_date'];
        $statistics_out_orders = $statistics_out_orders['statistics'];
        $statistics_emploees = $this->_get_reports_emploees($get_data);
        $statistics_emploees_date = $statistics_emploees['statistics_date'];
        $statistics_emploees = $statistics_emploees['statistics'];
        
        $warehouses = array_merge(array_keys($statistics_in_container), 
                array_keys($statistics_in_wwo), 
                array_keys($statistics_out_wwo), 
                array_keys($statistics_out_orders));
        $warehouses = array_values(array_unique($warehouses));
        
        $statistics = [];
        $statistics_date = [];
        
        foreach ($warehouses as $warehouse_id) {
            $statistics[$warehouse_id] = array(
                'in_container' => [
                    'number' => isset($statistics_in_container[$warehouse_id]['number']) ? $statistics_in_container[$warehouse_id]['number'] : 0,
                    'volume' => isset($statistics_in_container[$warehouse_id]['volume']) ? $statistics_in_container[$warehouse_id]['volume'] : 0,
                ],
                'in_wwo' => [
                    'number' => isset($statistics_in_wwo[$warehouse_id]['number']) ? $statistics_in_wwo[$warehouse_id]['number'] : 0,
                    'volume' => isset($statistics_in_wwo[$warehouse_id]['volume']) ? $statistics_in_wwo[$warehouse_id]['volume'] : 0,
                ],
                'out_wwo' => [
                    'number' => isset($statistics_out_wwo[$warehouse_id]['number']) ? $statistics_out_wwo[$warehouse_id]['number'] : 0,
                    'volume' => isset($statistics_out_wwo[$warehouse_id]['volume']) ? $statistics_out_wwo[$warehouse_id]['volume'] : 0,
                ],
                'out_orders' => [
                    'number' => isset($statistics_out_orders[$warehouse_id]['number']) ? $statistics_out_orders[$warehouse_id]['number'] : 0,
                    'volume' => isset($statistics_out_orders[$warehouse_id]['volume']) ? $statistics_out_orders[$warehouse_id]['volume'] : 0,
                ],
                'emploees' => isset($statistics_emploees[$warehouse_id]) ? $statistics_emploees[$warehouse_id] : 0,
            );
            
            if ($get_data['daily_statistics'])
            {
                if ( ! isset($statistics_in_container_date[$warehouse_id]))
                {
                    $statistics_in_container_date[$warehouse_id] = [];
                }

                if ( ! isset($statistics_in_wwo_date[$warehouse_id]))
                {
                    $statistics_in_wwo_date[$warehouse_id] = [];
                }

                if ( ! isset($statistics_out_wwo_date[$warehouse_id]))
                {
                    $statistics_out_wwo_date[$warehouse_id] = [];
                }

                if ( ! isset($statistics_out_orders_date[$warehouse_id]))
                {
                    $statistics_out_orders_date[$warehouse_id] = [];
                }

                if ( ! isset($statistics_emploees_date[$warehouse_id]))
                {
                    $statistics_emploees_date[$warehouse_id] = [];
                }

                $dates = array_merge(
                        array_keys($statistics_in_container_date[$warehouse_id]), 
                        array_keys($statistics_in_wwo_date[$warehouse_id]), 
                        array_keys($statistics_out_wwo_date[$warehouse_id]), 
                        array_keys($statistics_out_orders_date[$warehouse_id]),
                        array_keys($statistics_emploees_date[$warehouse_id]));

                $dates = array_values(array_unique($dates));
                sort($dates);

                foreach ($dates as $date)
                {
                    $statistics_date[$warehouse_id][$date] = array(
                        'in_container' => [
                            'number' => isset($statistics_in_container_date[$warehouse_id][$date]['number']) ? $statistics_in_container_date[$warehouse_id][$date]['number'] : 0,
                            'volume' => isset($statistics_in_container_date[$warehouse_id][$date]['volume']) ? $statistics_in_container_date[$warehouse_id][$date]['volume'] : 0,
                        ],
                        'in_wwo' => [
                            'number' => isset($statistics_in_wwo_date[$warehouse_id][$date]['number']) ? $statistics_in_wwo_date[$warehouse_id][$date]['number'] : 0,
                            'volume' => isset($statistics_in_wwo_date[$warehouse_id][$date]['volume']) ? $statistics_in_wwo_date[$warehouse_id][$date]['volume'] : 0,
                        ],
                        'out_wwo' => [
                            'number' => isset($statistics_out_wwo_date[$warehouse_id][$date]['number']) ? $statistics_out_wwo_date[$warehouse_id][$date]['number'] : 0,
                            'volume' => isset($statistics_out_wwo_date[$warehouse_id][$date]['volume']) ? $statistics_out_wwo_date[$warehouse_id][$date]['volume'] : 0,
                        ],
                        'out_orders' => [
                            'number' => isset($statistics_out_orders_date[$warehouse_id][$date]['number']) ? $statistics_out_orders_date[$warehouse_id][$date]['number'] : 0,
                            'volume' => isset($statistics_out_orders_date[$warehouse_id][$date]['volume']) ? $statistics_out_orders_date[$warehouse_id][$date]['volume'] : 0,
                        ],
                        'emploees' => isset($statistics_emploees_date[$warehouse_id][$date]) ? $statistics_emploees_date[$warehouse_id][$date] : 0,
                    );
                }
            }
        }

        return [
            'statistics' => $statistics, 
            'statistics_date' => $statistics_date, 
        ];
    }
    
    private function _get_reports_in_container($get_data) {
        $statistics = [];
        $statistics_date = [];
        $containers = [];
        $containers_date = [];
        $articles_ids = [];
        
        $date_to = mysql_real_escape_string($get_data['date_to']);
        $date_from = mysql_real_escape_string($get_data['date_from']);
        
        $where1 = " `add_to_warehouse_date` >= '$date_from 00:00:00' AND `add_to_warehouse_date` <= '$date_to 23:59:59' ";
        $where[] = $where1;

        if ($get_data['warehouses_ids']) {
            $where[] = " `warehouse_id` IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        if ($get_data['employees_ids_usernames']) {
            $where[] = " `add_to_warehouse_uname` IN (" . implode(',', array_map(function($v) {
                return "'" . mysql_real_escape_string($v) . "'";
            }, $get_data['employees_ids_usernames'])) . ") ";
        }
        
        $query = "SELECT `op_article`.`warehouse_id`, 
                    DATE(`op_article`.`add_to_warehouse_date`) `date`, 
                    IF (`opc`.`master_id`, `opc`.`master_id`, `op_article`.`container_id`) AS `op_container_id`
            FROM `op_article` 
                LEFT JOIN `op_order_container` AS `opc` ON `opc`.`id` = `op_article`.`container_id`
                WHERE NOT `opc`.`master_id` AND " . implode(' AND ', $where) . "
                GROUP BY `warehouse_id`, `date`, `op_container_id`";
        
        foreach ($this->_dbr->getAll($query) as $item) {
            $item->warehouse_id = (int)$item->warehouse_id;
            $item->container_id = (int)$item->op_container_id;
            
            $query = "SELECT
                    DATE(`op`.`add_to_warehouse_date`) `date`, 
#                    SUM(op.qnt_delivered) AS qnt_delivered,
#                    SUM(op.qnt_ordered) AS qnt_ordered,
                    SUM((op.qnt_delivered * a.volume_per_single_unit)) AS volume
                FROM op_article AS op
                    LEFT JOIN `op_order_container` AS `opc` ON `opc`.`id` = `op`.`container_id`
                    LEFT JOIN `op_order_container` AS `opc_master` ON `opc_master`.`id` = `opc`.`master_id`
                    LEFT JOIN article AS a ON a.article_id = op.article_id AND a.admin_id = 0
                WHERE 
                    IFNULL(opc_master.id, opc.id) = '{$item->container_id}'
                    AND $where1
                    AND op.add_to_warehouse = 1
                GROUP BY `date`";
            $container_data = $this->_dbr->getAssoc($query);

//            $item->qnt_ordered = (int)$container_data->qnt_ordered;
//            $item->qnt_delivered = (int)$container_data->qnt_delivered;
            $item->volume = isset($container_data[$item->date]) ? (float)$container_data[$item->date] : 0.0;

            if ( ! isset($containers[$item->warehouse_id][$item->container_id]))
            {
                $containers[$item->warehouse_id][$item->container_id] = 0;
            }
            
            $containers[$item->warehouse_id][$item->container_id] += $item->volume;
            
            if ( ! isset($containers_date[$item->date][$item->warehouse_id][$item->container_id])) {
                $containers_date[$item->date][$item->warehouse_id][$item->container_id] = 0;
            }
            
            $containers_date[$item->date][$item->warehouse_id][$item->container_id] = $item->volume;
        }

        foreach ($containers as $warehouse_id => $container) {
            $statistics[$warehouse_id] = array(
                'number' => count($container),
                'volume' => array_sum($container),
            );
        }
        
        foreach ($containers_date as $date => $containers_data_date) {
            foreach ($containers_data_date as $warehouse_id => $container) {

                $statistics_date[$warehouse_id][$date] = array(
                    'number' => count($container),
                    'volume' => array_sum($container),
                );
            }
        }

        return [
            'statistics' => $statistics, 
            'statistics_date' => $statistics_date, 
        ];
    }

    private function _get_reports_in_wwo($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);

        // @todo It's magic, but without string "LEFT JOIN `users` ON ..." query don't working
        $query = "SELECT `wwo`.`article_id`, `wwo`.`wwo_id`, `wwo`.`qnt`, `total_log`.*, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `wwo_article` AS `wwo` ON `wwo`.`id` = `total_log`.`TableID`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE `total_log`.`table_name` = 'wwo_article' AND `total_log`.`field_name` in ('to_warehouse', 'delivered')
                    AND " . implode(' AND ', $where) . " ORDER BY `total_log`.`id` DESC";

        $articles_ids = [];
        $delivered = [];
        $delivered_date = [];
        foreach ($this->_dbr->getAll($query) as $item) {
            $_date = strtotime($item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_date = date('Y-m-d', $_date);
            
            $item->ID = (int)$item->ID;
            $item->TableID = (int)$item->TableID;
            $item->New_value = (int)$item->New_value;
            $item->Updated = strtotime($item->Updated);
            
            if ( ! isset($delivered[$item->TableID])) {
                $delivered[$item->TableID] = array(
                    'table_id' => $item->TableID,
                    'wwo_id' => (int)$item->wwo_id,
                    'article_id' => (int)$item->article_id,
                    'article_qnt' => (int)$item->qnt,
                    'article_volume' => 0,
                    'updated_delivered' => 0,
                    'id' => $item->ID,
                    'to_warehouse' => 0,
                    'updated_warehouse' => 0,
                    'delivered' => -1,
                );
            }
            
            if ( ! isset($delivered_date[$_date][$item->TableID])) {
                $delivered_date[$_date][$item->TableID] = array(
                    'table_id' => $item->TableID,
                    'wwo_id' => (int)$item->wwo_id,
                    'article_id' => (int)$item->article_id,
                    'article_qnt' => (int)$item->qnt,
                    'article_volume' => 0,
                    'updated_delivered' => 0,
                    'id' => $item->ID,
                    'to_warehouse' => 0,
                    'updated_warehouse' => 0,
                    'delivered' => -1,
                );
            }
            
            $articles_ids[] = (int)$item->article_id;
            
            if ($item->Field_name == 'delivered') {
                $delivered[$item->TableID]['id'] = min($delivered[$item->TableID]['id'], $item->ID);
                $delivered_date[$_date][$item->TableID]['id'] = min($delivered[$item->TableID]['id'], $item->ID);
                
                if ($delivered[$item->TableID]['updated_delivered'] <= $item->Updated) {
                    $delivered[$item->TableID]['updated_delivered'] = $item->Updated;
                    $delivered[$item->TableID]['delivered'] = $item->New_value;
                }
                
                if ($delivered_date[$_date][$item->TableID]['updated_delivered'] <= $item->Updated) {
                    $delivered_date[$_date][$item->TableID]['updated_delivered'] = $item->Updated;
                    $delivered_date[$_date][$item->TableID]['delivered'] = $item->New_value;
                }
            }
            else {
                if ($delivered[$item->TableID]['updated_warehouse'] <= $item->Updated) {
                    $delivered[$item->TableID]['updated_warehouse'] = $item->Updated;
                    $delivered[$item->TableID]['to_warehouse'] = $item->New_value;
                }
                if ($delivered_date[$_date][$item->TableID]['updated_warehouse'] <= $item->Updated) {
                    $delivered_date[$_date][$item->TableID]['updated_warehouse'] = $item->Updated;
                    $delivered_date[$_date][$item->TableID]['to_warehouse'] = $item->New_value;
                }
            }
        }
        
        $articles = $this->_get_articles_params($articles_ids);

        $delivered_statistics = [];
        foreach ($delivered as $table_id => $_data) {
            if ($_data['delivered'] == -1) {
                $_data['delivered'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'delivered' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ( ! $_data['delivered']) {
                continue;
            }
            
            if ( ! $_data['to_warehouse']) {
                $_data['to_warehouse'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'to_warehouse' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ($get_data['warehouses_ids'] && !in_array($_data['to_warehouse'], $get_data['warehouses_ids'])) {
                continue;
            }
            
            if (isset($articles[$_data['article_id']])) {
                $_data['article_volume'] = $_data['article_qnt'] * $articles[$_data['article_id']]['volume'];
            }
            
            $delivered_statistics[$_data['to_warehouse']][] = $_data;
        }

        $delivered_statistics_date = [];
        foreach ($delivered_date as $date => $delivered_date_data) {
            foreach ($delivered_date_data as $table_id => $_data) {
                if ($_data['delivered'] == -1) {
                    $_data['delivered'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                            WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                                `table_name` = 'wwo_article' AND `field_name` = 'delivered' 
                            ORDER BY `id` DESC LIMIT 1");
                }

                if ( ! $_data['delivered']) {
                    continue;
                }

                if ( ! $_data['to_warehouse']) {
                    $_data['to_warehouse'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                            WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                                `table_name` = 'wwo_article' AND `field_name` = 'to_warehouse' 
                            ORDER BY `id` DESC LIMIT 1");
                }

                if ($get_data['warehouses_ids'] && !in_array($_data['to_warehouse'], $get_data['warehouses_ids'])) {
                    continue;
                }

                if (isset($articles[$_data['article_id']])) {
                    $_data['article_volume'] = $_data['article_qnt'] * $articles[$_data['article_id']]['volume'];
                }

                $delivered_statistics_date[$date][$_data['to_warehouse']][] = $_data;
            }
        }
        
        $statistics = [];
        $statistics_date = [];
        foreach ($delivered_statistics as $warehouse_id => $delivered_data) {
            $statistics[$warehouse_id] = array(
                'number' => array(),
                'volume' => 0.0, 
            );
            
            foreach ($delivered_data as $_data) {
                $statistics[$warehouse_id]['number'][] = $_data['wwo_id'];
                $statistics[$warehouse_id]['volume'] += $_data['article_volume'];
            }
            
            $statistics[$warehouse_id]['number'] = count(array_unique($statistics[$warehouse_id]['number']));
        }
        
        foreach ($delivered_statistics_date as $date => $delivered_statistics_data) {
            foreach ($delivered_statistics_data as $warehouse_id => $delivered_data) {
                $statistics_date[$warehouse_id][$date] = array(
                    'number' => array(),
                    'volume' => 0.0, 
                );

                foreach ($delivered_data as $_data) {
                    $statistics_date[$warehouse_id][$date]['number'][] = $_data['wwo_id'];
                    $statistics_date[$warehouse_id][$date]['volume'] += $_data['article_volume'];
                }

                $statistics_date[$warehouse_id][$date]['number'] = count(array_unique($statistics_date[$warehouse_id][$date]['number']));
            }
        }
        
        return [
            'statistics' => $statistics, 
            'statistics_date' => $statistics_date, 
        ];
    }
    
    private function _get_reports_out_wwo($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);

        // @todo It's magic, but without string "LEFT JOIN `users` ON ..." query don't working
        $query = "SELECT `wwo`.`article_id`, `wwo`.`wwo_id`, `wwo`.`qnt`, `total_log`.*, DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log` 
            JOIN `wwo_article` AS `wwo` ON `wwo`.`id` = `total_log`.`TableID`
            LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
            WHERE `total_log`.`table_name` = 'wwo_article' AND `total_log`.`field_name` in ('from_warehouse', 'taken')
                    AND " . implode(' AND ', $where) . " ORDER BY `total_log`.`id` DESC";
        
        $articles_ids = [];
        $taken = [];
        $taken_date = [];
        foreach ($this->_dbr->getAll($query) as $item) {
            $_date = strtotime($item->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_date = date('Y-m-d', $_date);
            
            $item->ID = (int)$item->ID;
            $item->TableID = (int)$item->TableID;
            $item->New_value = (int)$item->New_value;
            $item->Updated = strtotime($item->Updated);
            
            if ( ! isset($taken[$item->TableID])) {
                $taken[$item->TableID] = array(
                    'table_id' => $item->TableID,
                    'wwo_id' => $item->wwo_id,
                    'article_id' => (int)$item->article_id,
                    'article_qnt' => (int)$item->qnt,
                    'article_volume' => 0,
                    'updated_taken' => 0,
                    'id' => $item->ID,
                    'from_warehouse' => 0,
                    'updated_warehouse' => 0,
                    'taken' => -1,
                );
            }
            
            if ( ! isset($taken_date[$_date][$item->TableID])) {
                $taken_date[$_date][$item->TableID] = array(
                    'table_id' => $item->TableID,
                    'wwo_id' => $item->wwo_id,
                    'article_id' => (int)$item->article_id,
                    'article_qnt' => (int)$item->qnt,
                    'article_volume' => 0,
                    'updated_taken' => 0,
                    'id' => $item->ID,
                    'from_warehouse' => 0,
                    'updated_warehouse' => 0,
                    'taken' => -1,
                );
            }
            
            $articles_ids[] = (int)$item->article_id;
            
            if ($item->Field_name == 'taken') {
                $taken[$item->TableID]['id'] = min($taken[$item->TableID]['id'], $item->ID);
                $taken_date[$_date][$item->TableID]['id'] = min($taken_date[$_date][$item->TableID]['id'], $item->ID);
                
                if ($taken[$item->TableID]['updated_taken'] <= $item->Updated) {
                    $taken[$item->TableID]['updated_taken'] = $item->Updated;
                    $taken[$item->TableID]['taken'] = $item->New_value;
                }
                
                if ($taken_date[$_date][$item->TableID]['updated_taken'] <= $item->Updated) {
                    $taken_date[$_date][$item->TableID]['updated_taken'] = $item->Updated;
                    $taken_date[$_date][$item->TableID]['taken'] = $item->New_value;
                }
            }
            else {
                if ($taken[$item->TableID]['updated_warehouse'] <= $item->Updated) {
                    $taken[$item->TableID]['updated_warehouse'] = $item->Updated;
                    $taken[$item->TableID]['from_warehouse'] = $item->New_value;
                }
                if ($taken_date[$_date][$item->TableID]['updated_warehouse'] <= $item->Updated) {
                    $taken_date[$_date][$item->TableID]['updated_warehouse'] = $item->Updated;
                    $taken_date[$_date][$item->TableID]['from_warehouse'] = $item->New_value;
                }
            }
        }
        
        $articles = $this->_get_articles_params($articles_ids);

        $taken_statistics = [];
        foreach ($taken as $table_id => $_data) {
            if ($_data['taken'] == -1) {
                $_data['taken'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'taken' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ( ! $_data['taken']) {
                continue;
            }
            
            if ( ! $_data['from_warehouse']) {
                $_data['from_warehouse'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                        WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                            `table_name` = 'wwo_article' AND `field_name` = 'from_warehouse' 
                        ORDER BY `id` DESC LIMIT 1");
            }
            
            if ($get_data['warehouses_ids'] && !in_array($_data['from_warehouse'], $get_data['warehouses_ids'])) {
                continue;
            }
            
            if (isset($articles[$_data['article_id']])) {
                $_data['article_volume'] = $_data['article_qnt'] * $articles[$_data['article_id']]['volume'];
            }
            
            $taken_statistics[$_data['from_warehouse']][] = $_data;
        }
        
        $taken_statistics_date = [];
        foreach ($taken_date as $date => $taken_data) {
            foreach ($taken_data as $table_id => $_data) {
                if ($_data['taken'] == -1) {
                    $_data['taken'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                            WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                                `table_name` = 'wwo_article' AND `field_name` = 'taken' 
                            ORDER BY `id` DESC LIMIT 1");
                }

                if ( ! $_data['taken']) {
                    continue;
                }

                if ( ! $_data['from_warehouse']) {
                    $_data['from_warehouse'] = (int)$this->_dbr->getOne("SELECT `New_value` FROM `total_log` 
                            WHERE `id` < {$_data['id']} AND `TableID` = {$table_id} AND
                                `table_name` = 'wwo_article' AND `field_name` = 'from_warehouse' 
                            ORDER BY `id` DESC LIMIT 1");
                }

                if ($get_data['warehouses_ids'] && !in_array($_data['from_warehouse'], $get_data['warehouses_ids'])) {
                    continue;
                }

                if (isset($articles[$_data['article_id']])) {
                    $_data['article_volume'] = $_data['article_qnt'] * $articles[$_data['article_id']]['volume'];
                }

                $taken_statistics_date[$date][$_data['from_warehouse']][] = $_data;
            }
        }
        
        $statistics = [];
        $statistics_date = [];
        foreach ($taken_statistics as $warehouse_id => $taken_data) {
            $statistics[$warehouse_id] = array(
                'number' => array(),
                'volume' => 0.0, 
            );
            
            foreach ($taken_data as $_data) {
                $statistics[$warehouse_id]['number'][] = $_data['wwo_id'];
                $statistics[$warehouse_id]['volume'] += $_data['article_volume'];
            }
            
            $statistics[$warehouse_id]['number'] = count(array_unique($statistics[$warehouse_id]['number']));
        }
        
        foreach ($taken_statistics_date as $date => $taken_statistics_data) {
            foreach ($taken_statistics_data as $warehouse_id => $taken_data) {
                $statistics_date[$warehouse_id][$date] = array(
                    'number' => array(),
                    'volume' => 0.0, 
                );

                foreach ($taken_data as $_data) {
                    $statistics_date[$warehouse_id][$date]['number'][] = $_data['wwo_id'];
                    $statistics_date[$warehouse_id][$date]['volume'] += $_data['article_volume'];
                }

                $statistics_date[$warehouse_id][$date]['number'] = count(array_unique($statistics_date[$warehouse_id][$date]['number']));
            }
        }
        
        return [
            'statistics' => $statistics, 
            'statistics_date' => $statistics_date, 
        ];
    }
   
    private function _get_reports_out_orders($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);
        if ($get_data['warehouses_ids']) {
            $where[] = " `orders`.`send_warehouse_id` IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        
        $query = "SELECT `orders`.`id`, `orders`.`quantity`, `orders`.`auction_number`, `orders`.`txnid`, 
                `orders`.`article_id`, `orders`.`article_id`, `orders`.`send_warehouse_id`, `auction`.`main_auction_number`,
                DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d') AS `_date`
            FROM `total_log`
            JOIN `orders` ON `orders`.`id` = `total_log`.`TableID`
            JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid` 
            WHERE " . implode(' AND ', $where) . " AND `table_name` = 'orders' 
                AND `field_name` = 'sent' AND `new_value` = '1'
                AND `orders`.`article_id` > 0";
        $result = $this->_dbr->getAll($query);
                  
        $articles_ids = array();
        foreach ($result as $_order) {
            $articles_ids[] = $_order->article_id;
            
//            $_date = strtotime($_order->_date);
//            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
//                continue;
//            }
//            
//            $statistics[$_order->send_warehouse_id] = array('number' => 0, 'volume' => 0);
        }
        
        $articles = $this->_get_articles_params($articles_ids);
        
        $statistics = array();
        $statistics_date = array();
        foreach ($result as $_order) {
            $_date = strtotime($_order->_date);
            if ( ! $_date || $_date < $get_data['date_from_int'] || $_date > $get_data['date_to_int']) {
                continue;
            }
            
            $_date = date('Y-m-d', $_date);
            
            if ($_order->main_auction_number) {
                $index = "{$_order->main_auction_number}/{$_order->txnid}";
            }
            else {
                $index = "{$_order->auction_number}/{$_order->txnid}";
            }
            
            if ( ! isset($statistics[$_order->send_warehouse_id]))
            {
                $statistics[$_order->send_warehouse_id] = [
                    'number' => [], 
                    'volume' => 0, 
                ];
            }
            
            if ( ! isset($statistics_date[$_order->send_warehouse_id][$_date]))
            {
                $statistics_date[$_order->send_warehouse_id][$_date] = [
                    'number' => [], 
                    'volume' => 0, 
                ];
            }
            
            $statistics[$_order->send_warehouse_id]['number'][] = $index;
            $statistics[$_order->send_warehouse_id]['volume'] += isset($articles[$_order->article_id]) ? $_order->quantity * $articles[$_order->article_id]['volume'] : 0;
            
            $statistics_date[$_order->send_warehouse_id][$_date]['number'][] = $index;
            $statistics_date[$_order->send_warehouse_id][$_date]['volume'] += isset($articles[$_order->article_id]) ? $_order->quantity * $articles[$_order->article_id]['volume'] : 0;
        }
        
        foreach ($statistics as $warehouse_id => $item) {
            $statistics[$warehouse_id]['number'] = count(array_unique($item['number']));
        }
        
        foreach ($statistics_date as $warehouse_id => $statistics_data) {
            foreach ($statistics_data as $date => $item) {
                $statistics_date[$warehouse_id][$date]['number'] = count(array_unique($item['number']));
            }
        }
        
        return [
            'statistics' => $statistics, 
            'statistics_date' => $statistics_date, 
        ];
    }
        
    private function _get_reports_emploees($get_data) {
        $this->_set_total_log_indexes($get_data['date_from'], $get_data['date_to_next']);
        
        $where = $this->_set_where_total_log_wo($get_data);
        if ($get_data['warehouses_ids']) {
            $where[] = " `user_timestamp`.`warehouse_id` IN (" . implode(',', $get_data['warehouses_ids']) . ") ";
        }
        else {
            $where[] = " `user_timestamp`.`warehouse_id` ";
        }
        
        $query = "
            SELECT DATE(`total_log`.`updated`) AS `_updated`, 
                `user_timestamp`.`warehouse_id`, 
                COUNT(DISTINCT(`total_log`.`username`)) AS `count`
            FROM `total_log` 
            JOIN `user_timestamp` ON `user_timestamp`.`id` = `total_log`.`TableID` 
            WHERE " . implode(' AND ', $where) . "
                AND `table_name` = 'user_timestamp' 
                AND `field_name` = 'id' 
                AND `user_timestamp`.`login` 
            GROUP BY `user_timestamp`.`warehouse_id`, `_updated`
        ";

        $statistics = [];
        $statistics_date = [];
        
        foreach ($this->_dbr->getAll($query) as $item)
        {
            if ( ! isset($statistics[$item->warehouse_id]))
            {
                $statistics[$item->warehouse_id] = 0;
            }
            $statistics[$item->warehouse_id] += (int)$item->count;
            
            if ( ! isset($statistics_date[$item->warehouse_id][$item->_updated]))
            {
                $statistics_date[$item->warehouse_id][$item->_updated] = 0;
            }
            
            $statistics_date[$item->warehouse_id][$item->_updated] += (int)$item->count;
        }
        
        return [
            'statistics' => $statistics, 
            'statistics_date' => $statistics_date, 
        ];
    }
        
    /******************************************************************************************************************/
    
    private function _get_articles_params($articles_ids) {
        $articles_ids = array_values(array_unique($articles_ids));
        $_a_ids = array();
        foreach ($articles_ids as $_a_id) {
            $_a_id = (int)$_a_id;
            if ($_a_id) {
                $_a_ids[] = $_a_id;
            }
        }
        
        if ($_a_ids) {
            return $this->_dbr->getAssoc("SELECT `a`.`article_id`, `a`.`weight`, `a`. `volume_per_single_unit` AS `volume`, 
                        (SELECT t.value
                            FROM translation AS t
                            WHERE t.table_name = 'article'
                            AND t.field_name = 'name'
                            AND t.language = 'german'
                            AND t.id = a.article_id) name 
                    FROM `article` AS `a`
                    WHERE `a`.`article_id` IN (" . implode(',', $_a_ids) . ") AND `a`.`admin_id` = 0");
        }

        return array();
    }
    
    /******************************************************************************************************************/

    private function _get_total_log($get_data) {
        if ($this->_total_log) {
            return $this->_total_log;
        }
        
        $this->_total_log = array();
        
        $where = $this->_set_where_total_log($get_data);
        $tables_names = array();
        
        if (in_array('aap', $get_data['actions'])) {
            $tables_names[] = " ((`table_name` = 'parcel_barcode_article_barcode' AND `field_name` = 'barcode_id') OR `table_name` = 'parcel_barcode_article') ";
        }
        
        if (in_array('dap', $get_data['actions'])) {
            $tables_names[] = " (`table_name` = 'parcel_barcode_article_barcode' AND (`field_name` = 'barcode_id' OR `field_name` = 'parcel_barcode_id')) ";
        }
        
        if (in_array('apl', $get_data['actions']) || in_array('dpl', $get_data['actions'])) {
            $tables_names[] = " (`table_name` = 'parcel_barcode' AND `field_name` = 'warehouse_cell_id') ";
        }

        if (in_array('pa', $get_data['actions'])) {
            $tables_names[] = " (`table_name` = 'tn_orders') ";
        }
        
        if (in_array('sa', $get_data['actions'])) {
            $tables_names[] = " (`table_name` = 'orders' AND `field_name` = 'sent') ";
        }
        
        if (in_array('li', $get_data['actions'])) {
            $tables_names[] = " (`table_name` = 'mobile_loading_list' AND `field_name` = 'id') ";
        }
        
        $query = "SELECT `table_name`, `field_name`, `old_value`, `new_value`, `username`, `TableID` FROM `total_log`
                WHERE " . implode(' AND ', $where) . " AND ( " . implode(' OR ', $tables_names) . " ) ";
        
        $result = $this->_dbr->query($query);
        if (PEAR::isError($result)) {
            var_dump($result);
            return;
        }
        
        while ($data = $result->fetchRow()) {
            $this->_total_log[$data->table_name][] = $data;
        }
        
        return $this->_total_log;
    }
    
    private function _set_total_log_indexes($date_from, $date_to) {
        $this->_total_log_indexes = ['from' => 0, 'to' => 0];
        
        $date_from = mysql_real_escape_string($date_from);
        $date_to = mysql_real_escape_string($date_to);
        
        $this->_total_log_indexes['from'] = (int)$this->_dbr->getOne("SELECT `total_log_id` FROM `total_log_id` 
                WHERE `updated` <= '$date_from' ORDER BY `id` DESC LIMIT 1");
        $this->_total_log_indexes['to'] = (int)$this->_dbr->getOne("SELECT `total_log_id` FROM `total_log_id` 
                WHERE `updated` >= '$date_to' ORDER BY `id` ASC LIMIT 1");
    }
    
    private function _set_where_total_log($get_data) {
        $where = array();
        
        $get_data['date_from'] = mysql_real_escape_string($get_data['date_from']);
        $get_data['date_to'] = mysql_real_escape_string($get_data['date_to']);
        $get_data['date_from'] = mysql_real_escape_string($get_data['date_from']);
        $get_data['username'] = mysql_real_escape_string($get_data['username']);
        
        if ($this->_total_log_indexes['from']) {
            $where[] = " `total_log`.`id` > '{$this->_total_log_indexes['from']}' ";
        }
        else {
            $where[] = " `total_log`.`updated` >= '{$get_data['date_from']} 00:00:00' ";
        }
        
        if (strtotime($get_data['date_to']) < strtotime('midnight')) {
            if ($this->_total_log_indexes['to']) {
                $where[] = " `total_log`.`id` <= '{$this->_total_log_indexes['to']}' ";
            }
            else {
                $where[] = " `total_log`.`updated` <= '{$get_data['date_to']} 23:59:59' ";
            }
        }
        
        if ($get_data['employees_ids_names'] && is_array($get_data['employees_ids_names'])) {
            $where[] = " `total_log`.`username` IN (" . implode(',', array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, $get_data['employees_ids_names'])) . ") ";
        }
        
        if ($get_data['username']) {
            $where[] = " `total_log`.`username` = '{$get_data['username']}' ";
        }
        
        return $where;
        
    }
    
    private function _set_where_total_log_wo($get_data) {
        $where = array();
        
        $get_data['date_from'] = mysql_real_escape_string($get_data['date_from']);
        $get_data['date_to'] = mysql_real_escape_string($get_data['date_to']);
        $get_data['date_from'] = mysql_real_escape_string($get_data['date_from']);
        $get_data['username'] = mysql_real_escape_string($get_data['username']);
        
        if ($this->_total_log_indexes['from']) {
            $where[] = " `total_log`.`id` > '{$this->_total_log_indexes['from']}' ";
        }
        else {
            $where[] = " `total_log`.`updated` >= '{$get_data['date_from']} 00:00:00' ";
        }
        
        if (strtotime($get_data['date_to']) < strtotime('midnight')) {
            if ($this->_total_log_indexes['to']) {
                $where[] = " `total_log`.`id` <= '{$this->_total_log_indexes['to']}' ";
            }
            else {
                $where[] = " `total_log`.`updated` <= '{$get_data['date_to']} 23:59:59' ";
            }
        }
        
        return $where;
        
    }
    
    private function _get_warehouse_by_parcel_date($parcel_ids, $date) {
        if ( ! $parcel_ids) {
            return array();
        }
        
        $query = "SELECT v.id, v.warehouse_id FROM (SELECT @total_log_updated:='$date' p) parm, vparcel_barcode_tlu v 
                WHERE v.id IN (" . implode(',', $parcel_ids) . ")";
        
        $return = array();
        $result = $this->_dbr->query($query);
        while ($data = $result->fetchRow()) {
            $return[(int)$data->id] = (int)$data->warehouse_id;
        }
        
        return $return;
    }
    
    private function _get_barcode_for_article($order_id) {
        return $this->_dbr->getOne("select CONCAT(IFNULL(ats.id,IFNULL(bm.id,IFNULL(opa.id,IFNULL(rs.rma_spec_id,rsp.rma_spec_id)))),'/'
							,IFNULL(ats.article_id,IFNULL(bm.article_id,IFNULL(opa.article_id,IFNULL(rs.article_id,rsp.article_id))))
                            ,'/',b.id)
				from barcode_object boo
				left join barcode b on boo.barcode_id=b.id
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
				where boo.obj='orders' and boo.obj_id=$order_id and IFNULL(b.inactive,0)=0");
    }
    
    private function _get_articles_for_parcel(array $parcels_barcodes_ids, $date) {
        if ( ! $parcels_barcodes_ids) {
            return array();
        }
        
        $query = "SELECT `field_name`, `old_value`, `new_value`, `TableID` FROM `parcel_barcode_total_log` 
            WHERE `Field_name` IN ('parcel_barcode_id', 'barcode_id') AND `TableID` IN (
                SELECT distinct(`TableID`) FROM `parcel_barcode_total_log` WHERE `field_name` = 'parcel_barcode_id' AND 
                    `updated` <= '$date' AND 
                    (`Old_value` IN (" . implode(',', $parcels_barcodes_ids) . ") OR `New_value` IN (" . implode(',', $parcels_barcodes_ids) . "))
            )";
        
        $_parcels = array();

        $result = $this->_dbr->query($query);
        while ($_data = $result->fetchRow()) {
            $_data->old_value = (int)$_data->old_value;
            $_data->new_value = (int)$_data->new_value;
            $_data->TableID = (int)$_data->TableID;

            if ( ! isset($_parcels[$_data->TableID][$_data->field_name])) {
                $_parcels[$_data->TableID][$_data->field_name] = array(
                    'old' => 0,
                    'new' => 0,
                    'count' => 0,
                );
            }

            $_parcels[$_data->TableID][$_data->field_name]['old'] = $_data->old_value;
            $_parcels[$_data->TableID][$_data->field_name]['new'] = $_data->new_value;
            $_parcels[$_data->TableID][$_data->field_name]['count'] += ($_data->old_value ? -1 : 1);
        }

        $_barcodes_ids = array();
        $_total_parcels = array();
        foreach ($_parcels as $table_id => $_data) {
            if (isset($_data['parcel_barcode_id']) && isset($_data['barcode_id']) && $_data['parcel_barcode_id']['count'] > 0) {
                $_total_parcels[$_data['parcel_barcode_id']['new']][] = $_data['barcode_id']['new'];
                $_barcodes_ids[] = $_data['barcode_id']['new'];
            }
        }
        $_barcodes_ids = array_values(array_unique($_barcodes_ids));

        $articles = array();
        $articles_ids = array();
        foreach ($this->_dbr->getAll('SELECT `id`, `article_id` 
                FROM `vbarcode` WHERE `id` IN  ( ' . implode(',', $_barcodes_ids) . ' )') as $item) {
            $articles[(int)$item->id] = (int)$item->article_id;
            $articles_ids[] = (int)$item->article_id;
        }
        $articles_ids = array_values(array_unique($articles_ids));
        
        $return = array();
        foreach ($_total_parcels as $_parcel_barcode_id => $_barcodes_ids) {
            foreach ($_barcodes_ids as $_barcode_id) {
                if (isset($articles[$_barcode_id])) {
                    $return[$_parcel_barcode_id][] = $articles[$_barcode_id];
                }
            }
        }
        
        return array(
            'articles_ids' => $articles_ids,
            'return' => $return,
        );
    }

    private function _fill_parcel_barcode_table() {
        $max_id = (int)$this->_dbr->getOne('SELECT `id` FROM `total_log` ORDER BY `id` DESC LIMIT 1');
        $parcel_max_id = (int)$this->_dbr->getOne('SELECT `id` FROM `parcel_barcode_total_log` ORDER BY `id` DESC LIMIT 1');

        $start = (int)floor($parcel_max_id / self::LIMIT_ROWS);
        $stop = (int)ceil($max_id / self::LIMIT_ROWS);

        $_id = 1;
        for ($page = $start; $page < $stop; ++$page) {
            $query = "REPLACE INTO `parcel_barcode_total_log`
                SELECT * FROM `total_log` WHERE 
                `id` > " . ($_id * self::LIMIT_ROWS * $page) . " AND `id` <= " . ($_id * self::LIMIT_ROWS * ($page + 1)) . " AND 
                `Table_name` =  'parcel_barcode_article_barcode'";

            $result = $this->_db->query($query);
            if (PEAR::isError($result)) {
                var_dump($result);
            }
        }
    }
    
    /******************************************************************************************************************/
    
    public function get_working_days($start, $end, $holidays = array()) {
        // do strtotime calculations just once
        $start = strtotime($start);
        $end = strtotime($end);

        $working_days = 0;
        for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
            if ((int)date('N', $day) <= 5) {
                $working_days++;
            }
        }

        return $working_days;
    }

    /******************************************************************************************************************/

    public function __set($name, $value) {
        switch ($name) {
            case 'actions':
                $this->ACTIONS = $value;
                break;
        }
    }

    public function __get($name) {
        switch ($name) {
            case 'users':
                return $this->warehouses_users;
        }
    }

}
