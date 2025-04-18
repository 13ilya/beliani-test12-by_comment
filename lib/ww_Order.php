<?php
/**
 * RMA case
 * @package eBay_After_Sale
 */

/**
 * RMA case
 * @package eBay_After_Sale
 */
class ww_Order
{
    /**
    * Holds data record
    * @var object
    */
    public $data;
    
    /**
     * Id current WWO
     * @var Int
     */
    private $_id;
    
    /**
    * Reference to database
    * @var object
    */
    private $_db;
    private $_dbr;
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
    public function __construct($db, $dbr, $id = 0)
    {
        $this->_db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $this->_dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN ww_order");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            
            $this->articles = [];
            $this->comments = [];
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM ww_order WHERE id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("ww_Order : record $id does not exist");
                return;
            }
            
            $query = "SELECT *
                        , (SELECT CONCAT('by ', IFNULL(`u`.`name`, `tl`.`username`)
                                , ' on ', DATE_FORMAT(`tl`.`Updated`, '%Y-%m-%d %H:%i'))
                            FROM `total_log` AS `tl`
                            LEFT JOIN `users` AS `u` ON `u`.`system_username`=`tl`.`username`
                            WHERE 1 
                                AND `tl`.`Table_name` = 'wwo_port' 
                                AND `tl`.`Field_name` = 'released' 
                                AND `tl`.`New_value` = 1
                                AND `tl`.`TableID` = `wwo_port`.`id`
                            ORDER BY `tl`.`id` DESC LIMIT 1) released_log
                    FROM `wwo_port` 
                    WHERE `wwo_id` = ? AND `type` = ?";
            $this->ports = $this->_dbr->getAll($query, null, [$id, 'port']);
            
            $query = "SELECT * FROM `wwo_port` WHERE `wwo_id` = ? AND `type` = ?";
            $this->destinations = $this->_dbr->getAll($query, null, [$id, 'destination']);
            
            $this->articles = \ww_Order::getArticles($db, $dbr, $id);
            $this->articles2combine = $dbr->getAssoc("select wwa.id, concat(wwa.article_id, ': ', t.value) 
                from wwo_article wwa
                left join  translation t on table_name = 'article'
                    AND field_name = 'name'
                    AND language = 'german'
                    AND t.id = wwa.article_id
                where  wwa.wwo_id=$id
                and custom_pdf_combine_with=0
                and custom_pdf_ignore=0");
            foreach($this->articles as $k=>$article_rec) {
                $this->articles[$k]->articles2combine = $this->articles2combine;
                unset($this->articles[$k]->articles2combine[$article_rec->id]);
            }
            $this->comments = \ww_Order::getComments($db, $dbr, $id);
            $this->_isNew = false;
            $this->_id = $id;
        }
    }

    /**
    * @return void
    * @param string $field
    * @param mixed $value
    * @desc Set field value
    */
    public function set($field, $value)
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
    public function get($field)
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
            $this->_error = PEAR::raiseError('ww_Order::update : no data');
        }
        foreach ($this->data as $field => $value) {
            {
                if ($query) {
                    $query .= ', ';
                }
                if (($value!=='' && $value!==NULL) || $field=='comment' || $field=='deleted' 
                || $field=='seller' || $field=='exporter_id' || $field=='consignee_id' || $field=='notify_applicant_id' 
                || $field=='close_username' || $field=='container_id' || $field=='term_id' || $field=='username')
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
        $r = $this->_db->query("$command ww_order SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            print_r($r); print_r($this->data); die();
        }
        if ($this->_isNew) {
            $this->_id = $this->data->id = mysql_insert_id();
        }
        return $r;
    }
    
    /**
     * Update wwo_port table
     * 
     * @param Array $warehouses
     * @param Array $ramps
     * @param String $type
     */
    public function update_ports($warehouses, $ramps, $type, $primary = null) {
        if ( ! $this->_id) {
            return;
        }
        
        $this->_db->execParam('DELETE FROM `wwo_port` WHERE `wwo_id` = ? AND `type` = ? AND `released` = 0', [$this->_id, $type]);
        
        foreach ($warehouses as $key => $warehouse_id) {
            if ( ! $warehouse_id) {
                continue;
            }
            
            if (empty($ramps[$key])) {
                $ramps[$key] = null;
                $where = 'AND `ware_la_id` IS NULL';
            } else {
                $where = 'AND `ware_la_id` = ' . $ramps[$key];
            }
            
            if ( ! $this->_db->getOne("SELECT `id` FROM `wwo_port` 
                    WHERE 1 
                        AND `wwo_id` = ? 
                        AND `warehouse_id` = ? 
                        $where
                        AND `type` = ?", null, [$this->_id, $warehouse_id, $type])) 
            {
                $isset_destination = false;
                if ($type == 'destination')
                {
                    $isset_destination = (bool)$this->_db->getOne("SELECT `id` FROM `wwo_port` 
                        WHERE 1 
                            AND `wwo_id` = ? 
                            AND `warehouse_id` = ? 
                            AND `type` = 'destination'", null, [$this->_id, $warehouse_id]);
                }
                
                if ( ! $isset_destination)
                {
                    $this->_db->execParam('INSERT INTO `wwo_port` (`wwo_id`, `warehouse_id`, `ware_la_id`, `type`, `released`) 
                        VALUES (?, ?, ?, ?, ?)', [$this->_id, $warehouse_id, $ramps[$key], $type, 0]);

                    if ($type == 'destination' && $primary !== null && $primary == $key) {
                        $insert_id = $this->_db->queryOne('SELECT LAST_INSERT_ID() FROM wwo_port');
                        $this->_db->execParam('UPDATE `wwo_port` SET `primary` = ? WHERE `id` = ?', [1, $insert_id]);
                    }
                }
            }
        }
    }
    
    /**
    * @return void
    * @param object $db
    * @param object $group
    * @desc Delete group in an offer
    */
    function delete(){
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('ww_Order::update : no data');
        }
        $id = (int)$this->data->id;
        $this->_db->query("DELETE FROM wwo_article WHERE wwo_id=$id");
        $obj_ids = $this->_dbr->query("SELECT GROUP_CONCAT(id) FROM wwo_article WHERE wwo_id = $id");
        if ($obj_ids) {
            $this->_db->query("DELETE FROM barcode_object WHERE obj='wwo_article' and obj_id IN ($obj_ids)");
        }
        $this->_db->query("DELETE FROM wwo_comment WHERE wwo_id=$id");
        $r = $this->_db->query("DELETE FROM ww_order WHERE id=$id");
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
    static function listAll($db, $dbr, $mode, $from_warehouse=0, $to_warehouse=0, $inactive=0, $notcompleted=0)
    {
        $where = ' WHERE 1=1 ';
        $where .= $mode=='all' ? '' : 
         ($mode=='closed' ? ' AND closed=1' : ' AND closed=0 ');
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        if (strlen($inactive)) $where.= " and ".($inactive?'':'NOT')." wwo.deleted ";
        if ($notcompleted) $where.= " and exists (select null from wwo_article wwa 
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=wwo.id 
            and wwa.delivered=0) ";
        if ($from_warehouse) $where.= " and exists (select null from wwo_article wwa 
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=wwo.id 
            and wwa.from_warehouse=$from_warehouse)";
        if ($to_warehouse) $where.= " and exists (select null from wwo_article wwa 
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=wwo.id 
            and wwa.to_warehouse=$to_warehouse)";
        $q = "SELECT wwo.*, opc.container_no
        , IFNULL(u.name, wwo.username) username_name
        , IFNULL(u1.name, wwo.close_username) close_username_name
        , IF(wwo.closed,
            (select updated from total_log 
                where table_name='ww_order' and field_name='closed' and new_value='1' and tableid=wwo.id 
                order by updated desc limit 1) 
            , '') close_date
        , c.color
        FROM ww_order wwo
            left join wwo_port on wwo_port.wwo_id=wwo.id AND wwo_port.type = 'destination'
            left join warehouse wd on wd.warehouse_id=wwo_port.warehouse_id
            left join country c on c.code=wd.country_code
            left join op_order_container opc on opc.id=wwo.container_id
            left JOIN users u ON wwo.username=u.username
            left JOIN users u1 ON wwo.close_username=u1.username
        $where ";
        $r = $dbr->getAll($q);
//		echo $q;
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
    static function getArticles($db, $dbr, $id, $total=0)
    {
        $q = "select wwa.*
/*			, IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=w.country_code
                    and article_import.article_id=a.article_id
                    order by import_date desc limit 1
                    ),a.total_item_cost) price*/
            , u.name as driver
            , a.weight_per_single_unit*wwa.qnt kg
            , w1.name as ware_from
            , w2.name as ware_to
            , (SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = 'german'
                AND id = a.article_id) name
            , (select IF(new_value=1, CONCAT('Taken on ', Updated, ' by ', IFNULL(users.name, total_log.username)), '') 
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='taken' 
                and TableID=wwa.id order by Updated desc limit 1) taken_text
            , (select IF(new_value=1, CONCAT('Delivered on ', Updated, ' by ', IFNULL(users.name, total_log.username)), '') 
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='delivered' 
                and TableID=wwa.id order by Updated desc limit 1) delivered_text
            , (select IF(new_value=1, CONCAT('Called on ', Updated, ' by ', IFNULL(users.name, total_log.username)), '') 
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='called' 
                and TableID=wwa.id order by Updated desc limit 1) called_text
            , (select CONCAT('by ', IFNULL(users.name, total_log.username), ' on ', Updated)
            from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='article_id' and old_value is null
                and TableID=wwa.id order by Updated desc limit 1) story
            , (select CONCAT('Moved by ', IFNULL(users.name, total_log.username), ' on ', Updated, ' from WWO#', total_log.old_value)
            from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='wwo_id' and new_value=$id
                and TableID=wwa.id order by Updated desc limit 1) movedstory
            , (select IFNULL(users.username, total_log.username)
            from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='article_id' and old_value is null
                and TableID=wwa.id order by Updated desc limit 1) created_by
            , (select `value` from config where `name` = 'cod_color') cod_payment_color
            , (select group_concat(distinct CONCAT('<font ',o.id,' style=\"color:',IF(o.sent
                    , '#999999'
                    , IF(tn.number is not null
                                ,'#FF00FF'
                                ,'#008800')
                    ),';\"><a target=\"_blank\" style=\"',IF(auo.deleted, 'text-decoration:line-through;','')
                            ,'color:',IF(o.sent
                                , '#999999'
                                , IF(tn.number is not null
                                ,'#FF00FF'
                                ,'#008800')),';\"
                href=\"auction.php?number=',IFNULL(mauo.auction_number,auo.auction_number),'&txnid=',IFNULL(mauo.txnid,auo.txnid),'\">'
                , o.quantity, ' x reserved for ', if((mauo.payment_method = 2 or auo.payment_method = 2), concat('<span style=\"background-color: ',cod_payment_color,';\">'), '<span>') , 
                IFNULL(mauo.auction_number,auo.auction_number),'/',IFNULL(mauo.txnid,auo.txnid),'</span></a></font>',
                IF(IFNULL(muo.name,uo.name) is null, '', 
                    CONCAT(' - <font style=\"color:#FF00FF\">Responsible '
                        ,IFNULL(IFNULL(muo.name,uo.name),IFNULL(mauo.shipping_username,auo.shipping_username)),'</font>')
                    )
                ) SEPARATOR '<br>')
                from orders o
                left join tn_orders on o.id=tn_orders.order_id
                left join tracking_numbers tn on tn.id=tn_orders.tn_id
                join auction auo on auo.auction_number=o.auction_number and auo.txnid=o.txnid
                left join auction mauo on auo.main_auction_number=mauo.auction_number and auo.main_txnid=mauo.txnid
                left join users uo on uo.username=auo.shipping_username
                left join users muo on muo.username=mauo.shipping_username
                where wwa.id=o.wwo_order_id #and o.spec_order
                #and ((tn_orders.id is null and tn.id is null) or (tn_orders.id is not null and tn.id is not null))
                ) orders
            , (select SUM(o.quantity)
                from orders o
                where wwa.id=o.wwo_order_id 
                ) reserved_quantity
            , a.volume_per_single_unit volume
            , a.weight_per_single_unit weight
            , a.weight_per_single_unit*wwa.qnt weight_total
            , a.volume_per_single_unit*wwa.qnt volume_total
            , d.name destiny
            , unc_o.auction_number unc_auction_number
            , unc_o.txnid unc_txnid
            , w3.name reserved_warehouse_name
            , (select GROUP_CONCAT(distinct CONCAT('<span style=\"color:#', IF(bs.`type` = 'in', '00AA00', 'AAAAAA'), '\">', b.barcode, '</span>', IF(br.scanned, '<i> * </i>', ''), '<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',bo.id,')\">') SEPARATOR '<br>')  
                from barcode_object bo
                join vbarcode b on bo.barcode_id=b.id
                join barcode br on br.id=b.id
                join barcode_dn dn on dn.id = b.id
                join barcode_state bs on bs.code = dn.state2filter
                where bo.obj='wwo_article' and bo.obj_id=wwa.id) barcodes
            , a.barcode_type
            , (select sum(o.quantity)
                from orders o
                join tn_orders on o.id=tn_orders.order_id
                join tracking_numbers tn on tn.id=tn_orders.tn_id
                where wwa.id=o.wwo_order_id and o.sent=0 and o.article_id=wwa.article_id
                    ) qnt_r2sh
## special for warestock_color
            , wwa.qnt as quantity
            , w1.warehouse_id as reserve_warehouse_id
            , if(w_dest.country_code='CH', cn.custom_number_ch, 
                if(w_dest.country_code='US', cn.custom_number_us, 
                    if(w_dest.country_code='CA', cn.custom_number_ca, 
                        cn.custom_number_eu
                    )
                )
            ) cust_num_dest
            , if(w_load.country_code='CH', cn.custom_number_ch, 
                if(w_load.country_code='US', cn.custom_number_us, 
                    if(w_load.country_code='CA', cn.custom_number_ca, 
                        cn.custom_number_eu
                    )
                )
            ) cust_num_load
            , cn.description_goods
            , a.weight_per_single_unit*wwa.qnt
                +IFNULL((select sum(a1.weight_per_single_unit * wwo_article.qnt) kg from wwo_article 
                    JOIN article a1 ON a1.article_id=wwo_article.article_id AND NOT a1.admin_id where wwo_article.custom_pdf_combine_with=wwa.id),0) kg4csv
            , wwa.total_item_cost*wwa.qnt
                +IFNULL((select sum(qnt*total_item_cost) total from wwo_article where wwo_article.custom_pdf_combine_with=wwa.id),0) total
            , wwa.parcels+IFNULL((select sum(parcels) ct from wwo_article where wwo_article.custom_pdf_combine_with=wwa.id),0) ct
            , vat_load.vat_percent vat_percent_load
            , vat_dest.vat_percent vat_percent_dest
            , a.custom_number_id
            , w3.country_code reserved_warehouse_country_code
            , a.items_per_shipping_unit
            , REPLACE(a.picture_URL,'_image.jpg','_x_200_image.jpg') picture_URL_200
            , wwo_released.released
            , picking_order.delivered AS `picking_order_delivered`
            , (SELECT CONCAT('Auto-comment added on ', `total_log`.`updated`, ' by ', IFNULL(`users`.`name`, `total_log`.`username`))
                FROM `total_log`
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                WHERE `total_log`.`table_name` = 'wwo_article'
                    AND `total_log`.`field_name` = 'qnt_delivered'
                    AND `total_log`.`new_value` != 0
                    AND `total_log`.`tableid` = `wwa`.`id`
                ORDER BY `total_log`.`id` DESC
                LIMIT 1
            ) `auto_comment`
        FROM wwo_article wwa 
            join ww_order wwo on wwo.id=wwa.wwo_id
            left join wwo_port AS wwo_destination ON wwo_destination.wwo_id = wwo.id AND wwo_destination.type = 'destination'
            left join wwo_port ON wwo_port.wwo_id = wwo.id AND wwo_port.type = 'port'
            left join wwo_port AS wwo_released ON wwo_released.wwo_id = wwo.id AND wwo_released.type = 'port' AND wwo_released.warehouse_id = wwa.reserved_warehouse
            left join orders unc_o on unc_o.id=wwa.uncomplete_article_order_id
            JOIN article a ON a.article_id=wwa.article_id AND NOT a.admin_id
            left join picking_order ON picking_order.id = wwa.picking_order_id
            left JOIN users u ON wwa.username=u.username
            left JOIN warehouse w1 ON wwa.from_warehouse=w1.warehouse_id
            left JOIN warehouse w2 ON wwa.to_warehouse=w2.warehouse_id
            left JOIN warehouse w3 ON wwa.reserved_warehouse=w3.warehouse_id
            left join warehouse w_dest on wwo_destination.warehouse_id=w_dest.warehouse_id
            left join warehouse w_load on wwo_port.warehouse_id=w_load.warehouse_id
            left join custom_number cn on cn.id=a.custom_number_id
            left JOIN ww_destiny d ON wwa.destiny_id=d.id
            left join vat vat_load on vat_load.country_code=w_load.country_code and vat_load.country_code_from=w_load.country_code 
                and NOW() between vat_load.date_from and vat_load.date_to
            left join vat vat_dest on vat_dest.country_code=w_dest.country_code and vat_dest.country_code_from=w_dest.country_code 
                and NOW() between vat_dest.date_from and vat_dest.date_to
        WHERE wwa.wwo_id = $id 
        GROUP BY wwa.id
        ";

        if ($total) {
            $q = "select #t.country_code, 
            article_id
            , cn.name
            , cn.custom_number_ch
            , cn.custom_number_eu
            , cn.description_goods
            , IFNULL(country.name, cn.country_code) country_of_origin
            , custom_pdf_combine_with
            #, t.rate
                    , sum(total) total, sum(kg) kg, sum(qnt) qnt, sum(ct) ct
                    , sum(kg4csv) kg4csv
                    , ROUND(sum(total)/sum(qnt),2) price
                    , vat_percent_load, vat_percent_dest
            from ($q) t
            left join custom_number cn on cn.id = t.custom_number_id
            left join country on country.code=cn.country_code
            group by cn.id
            ";
        }
        $r = $dbr->getAll($q);
        
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        
        foreach ($r as $key => $article) {
            if ($article->released) {
                $released = $dbr->getOne("SELECT `id` FROM `picking_order`
                        WHERE `article_id` = '{$article->article_id}'
                        AND `wwo_id` = '$id' 
                        AND NOT `delivered`");
                if (  ! $released) {
                    $r[$key]->released = 0;
                }
            }
            
            if ($article->barcode_type == 'A')
            {
                $locations = article_get_location_A($article->article_id, $article->reserve_warehouse_id);
            }
            else
            {
                $locations = article_get_location_C($article->article_id, $article->reserve_warehouse_id);
            }

            $r[$key]->articles_locations = [];
            $locations = array_slice($locations, 0, 5);
            foreach ($locations as $_loc => $parcel_data)
            {
                if (stripos($_loc, '---') === false)
                {
                    $_loc = strip_tags($_loc);
                    $_quantity = array_sum($parcel_data);
                    $r[$key]->articles_locations[$_loc] = $_quantity;
                }
            }
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
        $wwo_id, 
        $article_id,
        $destiny,
        $qnt,
        $total_item_cost,
        $parcels,
        $username, 
        $comment, 
        $from_warehouse,
        $to_warehouse,
        $reserved_warehouse,
        $order_id=0
    )
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        if ( ! $from_warehouse) {
            $from_warehouse = $reserved_warehouse; 
        }

        if ( ! $to_warehouse) {
            $to_warehouse = (int)$dbr->getOne("select to_warehouse from wwo_article where wwo_id=$wwo_id order by id desc limit 1");
        }

        $query = "INSERT INTO `wwo_article` (`wwo_id`, `article_id`, `destiny_id`, `total_item_cost`, `qnt`, `parcels`,
            `username`, `comment`, `reserved_warehouse`, `from_warehouse`, `uncomplete_article_order_id`, `to_warehouse`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $db->execParam($query, [$wwo_id, (int)$article_id, (int)$destiny, (float)$total_item_cost, (int)$qnt, (int)$parcels, 
            (string)$username, $comment, (int)$reserved_warehouse, (int)$from_warehouse, (int)$order_id, (int)$to_warehouse]);
        
        return (int)$db->getOne('SELECT LAST_INSERT_ID() FROM wwo_article');
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
        $destiny,
        $qnt,
        $total_item_cost,
        $parcels,
        $username, 
        $comment, 
        $from_warehouse,
        $to_warehouse,
        $reserved_warehouse,
        $custom_pdf_combine_with,
        $custom_pdf_ignore,
        $broken,
        $dont_add_pcs
    )
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        
        $id = (int)$id;
        if ($destiny==NULL) $destiny='destiny_id';
            else $destiny = (int)$destiny;
        if ($qnt==NULL) $qnt='qnt';
            else $qnt = (int)$qnt;
        if ($total_item_cost==NULL) $total_item_cost='total_item_cost';
            else $total_item_cost = 1*$total_item_cost;
        if ($parcels==NULL) $parcels='parcels';
            else $parcels = (int)$parcels;
        $username = mysql_escape_string($username); 
        $comment = mysql_escape_string($comment); 
        if ($from_warehouse=='') $from_warehouse='from_warehouse'; else $from_warehouse = 'IF(taken = 1, from_warehouse, '.(int)$from_warehouse.')';
        if ($to_warehouse=='') $to_warehouse='to_warehouse'; else $to_warehouse = 'IF(delivered = 1, to_warehouse, '.(int)$to_warehouse.')'; 
        if ($reserved_warehouse=='') $reserved_warehouse='NULL'; else $reserved_warehouse = (int)$reserved_warehouse; 
        $q = "UPDATE wwo_article SET 
            qnt = $qnt,
            destiny_id = $destiny,
            parcels = $parcels,
            total_item_cost = $total_item_cost,
            username = '$username',
            comment = '$comment',
            from_warehouse = $from_warehouse,
            to_warehouse = $to_warehouse,
            reserved_warehouse = $reserved_warehouse,
            custom_pdf_combine_with = $custom_pdf_combine_with,
            custom_pdf_ignore = $custom_pdf_ignore,
            broken = $broken,
            dont_add_pcs = $dont_add_pcs
        WHERE id=$id";

        $r = $db->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r); die();
        }
    }

    static function getComments($db, $dbr, $id)
    {
        $r = $dbr->getAll("
            SELECT wwo_comment.id
                , wwo_comment.comment
                , wwo_comment.create_date
                , wwo_comment.username
                , '' AS prefix
                , IFNULL(users.name, wwo_comment.username) full_username
                , wwo_comment.username cusername
            FROM wwo_comment 
            LEFT JOIN users ON wwo_comment.username = users.username
            WHERE wwo_id = " . $id . "
            UNION ALL 
            SELECT NULL as id
                , alarms.comment
                , (SELECT updated from total_log WHERE table_name='alarms' AND tableid=alarms.id limit 1) AS create_date
                , users.username
                , CONCAT('Alarm (',alarms.status,'):') as prefix
                , users.name full_username
                , users.username cusername 
            FROM alarms
            LEFT JOIN users ON users.username = alarms.username
            WHERE alarms.type_id = " . $id . " 
                AND alarms.type = 'ww_order'
                ORDER BY create_date");
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }
    

    /**
     * 
     * @param type $db
     * @param type $dbr
     * @param type $id
     * @param type $total_item_cost
     * @param type $parcels
     * @param type $comment
     */
    static function updateReleasedArticle($db, $dbr, 
        $id, 
        $total_item_cost,
        $parcels,
        $comment, 
        $to_warehouse, 
        $dont_add_pcs
    )
    {
        $id = (int)$id;
        if ($total_item_cost==NULL) 
        {
            $total_item_cost='total_item_cost';
        }
        else 
        {
            $total_item_cost = 1*$total_item_cost;
        }
        if ($parcels==NULL) 
        {
            $parcels='parcels';
        }
        else 
        {
            $parcels = (int)$parcels;
        }
        $comment = mysql_escape_string($comment); 
        
        if ($to_warehouse=='') 
        {
            $to_warehouse='to_warehouse'; 
        }
        else 
        {
            $to_warehouse = 'IF(delivered = 1, to_warehouse, '.(int)$to_warehouse.')'; 
        }
        
        $q = "UPDATE wwo_article SET 
            parcels = $parcels,
            total_item_cost = $total_item_cost,
            comment = '$comment', 
            to_warehouse = $to_warehouse,
            dont_add_pcs = $dont_add_pcs
        WHERE id=$id";
//		echo $q.'<br>';
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r); die();
        }
    }

    function addComment($db, $dbr, 
        $username,
        $create_date,
        $comment
        )
    {
        $wwo_id = (int)$this->data->id;
        $username = mysql_escape_string($username);
        $create_date = mysql_escape_string($create_date);
        $comment = mysql_escape_string($comment);
        $r = $db->query("insert into wwo_comment set 
            wwo_id=$wwo_id, 
            username='$username',
            create_date='$create_date',
            comment='$comment'");
    }

    static function getDocs($db, $dbr, $wwo_id, $sig=0)
    {
        $r = $db->query("SELECT wwo_doc.doc_id
                , wwo_doc.name, wwo_doc.wwo_id, wwo_doc.description, wwo_doc.sig
                , tl.updated, IFNULL(u.name, tl.username) username
                from wwo_doc 
                left join total_log tl on wwo_doc.doc_id=tl.tableid and table_name='wwo_doc' and field_name='doc_id'
                left join users u on u.system_username=tl.username
                where wwo_id=$wwo_id and sig=$sig ORDER BY doc_id");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addDoc($db, $dbr, 
        $wwo_id,
        $name,
        $description,
        $data,
        $sig = 0
        )
    {
        $md5 = md5($data);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }

        $name = mysql_escape_string($name);
        $description = mysql_escape_string($description);
        $sig = (int)$sig;
        $r = $db->query("insert into wwo_doc set 
            wwo_id=$wwo_id, 
            name='$name',
            description='$description',
            data='$md5',
            sig=$sig
            ");

//		$data = mysql_escape_string($data);
//		$sig = (int)$sig;
//        $r = $db->query("insert into wwo_doc set 
//			wwo_id=$wwo_id, 
//			name='$name',
//			description='$description',
//			data='$data',
//			sig=$sig
//			");
        if (PEAR::isError($r)) {print_r($r); die();}
    }


    static function deleteDoc($db, $dbr, $doc_id)
    {
        $doc_id = (int)$doc_id;
        $r = $db->query("delete from wwo_doc where doc_id=$doc_id");
    }

    function ccsv($btn, $filter) {
        if (strpos($btn, 'custom')) {
            $articles = ww_Order::getArticles($this->_db, $this->_dbr, $this->data->id, 1);
        } else {
            $articles = $this->articles;
        }
        if (strpos($btn, 'destination')) $csv = "custom number of destination country";
        elseif (strpos($btn, 'loading')) $csv = "custom number of loading  country";
        $csv .= ";Description for custom declaration;brutto weight;netto weight; Total price without VAT; Total price with VAT;parcels;\n";
        foreach($articles as $item) {
            if (strpos($btn, 'destination')) $csv .= $item->cust_num_dest.";";
            elseif (strpos($btn, 'loading')) $csv .= $item->cust_num_load.";";
            $csv .= utf8_decode($item->description_goods).";";
            $csv .= number_format($item->kg4csv,3,',','').";";
            $csv .= number_format($item->kg4csv*\Config::get(null, null, 'wwo_bruttonetto'),3,',','').";";
            $csv .= number_format($item->total,2,',','').";";
        if (strpos($btn, 'destination')) $csv .= number_format($item->total*(1+$item->vat_percent_dest/100),2,',','').";";
        elseif (strpos($btn, 'loading')) $csv .= number_format($item->total*(1+$item->vat_percent_load/100),2,',','').";";
            $csv .= $item->ct.";";
            $csv .= "\n";
        }
        return $csv;
    }

    function pdf($from_warehouse='', $to_warehouse='', $username='', $btn='Print wwo PDF with volume, weight and comments', $filter) {
        if (!$this->data->id) return;
        global $smarty;
        $db = $this->_db;
        $dbr = $this->_db;
        global $english;
        foreach($this->articles as $k=>$r) {
            $res = warestock_color($dbr, $r->reserved_warehouse_country_code, $r);
            extract($res);
            $this->articles[$k]->warehouses_table = $warehouses_table;
            $this->articles[$k]->warestock_color = ' <font color="'.$warestock_color.'">'.$warestock.'</font>';
        }
        $smarty->assign('order', $this);
        $smarty->assign('total', $dbr->getRow("select sum(wwa.qnt*a.volume_per_single_unit) as volume
            , sum(wwa.qnt*a.weight_per_single_unit) as weight
            from wwo_article wwa
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=".$this->data->id));
        
        $withLocations = false;
        
        switch ($btn) {
            case 'Print wwo PDF with volume, weight and comments':
                $withLocations = true;
                //break skipped
            case 'Send wwo PDF with volume, weight and comments':
                $withComments = true;
                $withVolume = true;
                $withWeight = true;
                $withReservations = false;
                break;
            case 'Print wwo PDF with reservations and comments':
                $withLocations = true;
                $withComments = true;
                $withVolume = false;
                $withWeight = false;
                $withReservations = true;
                break;
            default:
                $withComments = false;
                $withVolume = false;
                $withWeight = false;
                $withReservations = false;
                break;
        }
        $smarty->assign('withLocations', $withLocations);
        $smarty->assign('withComments', $withComments);
        $smarty->assign('withVolume', $withVolume);
        $smarty->assign('withWeight', $withWeight);
        $smarty->assign('withReservations', $withReservations);
        $smarty->assign('container', $this->_dbr->getOne("select container_no
                                                          from op_order_container
                                                          where id=" . (int)$this->data->container_id));
        $ids = $this->_dbr->getOne("select group_concat(id) from (select distinct tl1.new_value id
                        from total_log tl1
                        join total_log tl2 on tl1.tableid=tl2.tableid
                        where tl1.table_name='wwo_article' and tl1.field_name='id' 
                        and tl2.table_name='wwo_article' and tl2.field_name='wwo_id' 
                        and tl2.old_value is null and tl2.new_value = '{$this->data->id}'
                    union select id from wwo_article where wwo_id={$this->data->id}) t");
        if (strlen($ids)) {
            $q = "select CONCAT('Last changed by ', IFNULL(u.name, tl.username),' on ', tl.updated)
                    from total_log tl
                    left join users u on u.system_username=tl.username
                    where table_name='wwo_article'
                    and tableid in ($ids)
                    order by updated desc limit 1";
            $last_article_change = $this->_dbr->getOne($q);
            if (PEAR::isError($last_article_change)) {print_r($last_article_change); die();}
            $smarty->assign('last_article_change', $last_article_change);
        }
        $smarty->assign('filter', $filter);
        $html = $smarty->fetch("ware2ware_order_prn.tpl");
//		echo $html; die();
        
        $fn = "ww_order_".time();
        file_put_contents("tmp/$fn.html", $html);
        $comand = "/usr/local/bin/wkhtmltopdf --dpi 300 \"tmp/$fn.html\" tmp/$fn.pdf";
        $r = exec($comand);
        if (file_exists("tmp/$fn.pdf")) {
            $content=file_get_contents("tmp/$fn.pdf");
            unlink("tmp/$fn.pdf");
            unlink("tmp/$fn.html");
        } else {
            var_dump($r);
            unlink("tmp/$fn.pdf");
            unlink("tmp/$fn.html");
            die($comand);
        }

        if (strlen($username) && ($btn=='Send wwo PDF with volume, weight and comments' 
                                || $btn=='Send wwo PDF without volume, weight and comments')) {
            $ret = new stdClass;
            $email = $dbr->getOne("select email from users where username='$username'");
            $ret->username = \Config::get($db, $dbr, 'aatokenSeller');
            $ret->email_invoice = $email;
            $ret->auction_number = $this->data->id;
            $ret->wwo_id = $this->data->id;
            $ret->wwo_id = $this->data->id;
            $ret->txnid = -7;
            $rec = new stdClass;
            $rec->data = $content;
            $rec->name = 'WWO#'.$this->data->id.'.pdf';
            $ret->attachments[] = $rec;
            standardEmail($db, $dbr, $ret, 'send_wwo_pdf'); #die();
            header("Location: ware2ware_order.php?id=".$this->data->id."&msgtext=".urlencode("Sent to $email"));
            exit;
        }
        return $content;
    }


    function pdf_invoice($btn='detailed') {
        global $smarty;
        $q = "select wwa.id, wwa.article_id, wwa.custom_pdf_combine_with
            , IF (wwa.dont_add_pcs = 0
                , wwa.qnt+IFNULL((select sum(qnt) qnt from wwo_article where wwo_article.custom_pdf_combine_with=wwa.id),0)
                , 0) qnt
            , wwa.total_item_cost price
            , wwa.total_item_cost*wwa.qnt
                +IFNULL((select sum(qnt*total_item_cost) total from wwo_article where wwo_article.custom_pdf_combine_with=wwa.id),0) total
            , u.name as driver
            , a.weight_per_single_unit*wwa.qnt
                +IFNULL((select sum(a1.weight_per_single_unit * wwo_article.qnt) kg from wwo_article 
                    JOIN article a1 ON a1.article_id=wwo_article.article_id AND NOT a1.admin_id where wwo_article.custom_pdf_combine_with=wwa.id),0)  kg
            , w1.name as ware_from
            , w2.name as ware_to
            , (SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = 'german'
                AND id = a.article_id) name
            , cn.custom_number_ch
            , cn.custom_number_eu
            , cn.description_goods
            , (select IF(new_value=1, CONCAT('Taken on ', Updated, ' by ', IFNULL(users.name, total_log.username)), '') 
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='taken' 
                and TableID=wwa.id order by Updated desc limit 1) taken_text
            , (select IF(new_value=1, CONCAT('Delivered on ', Updated, ' by ', IFNULL(users.name, total_log.username)), '') 
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='delivered' 
                and TableID=wwa.id order by Updated desc limit 1) delivered_text
            , (select IF(new_value=1, CONCAT('Called on ', Updated, ' by ', IFNULL(users.name, total_log.username)), '') 
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='called' 
                and TableID=wwa.id order by Updated desc limit 1) called_text
            , (select CONCAT('by ', IFNULL(users.name, total_log.username), ' on ', Updated)
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='article_id' and old_value is null
                and TableID=wwa.id order by Updated desc limit 1) story
            , (select IFNULL(users.username, total_log.username)
                from total_log 
                LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`
                where table_name='wwo_article' and Field_name='article_id' and old_value is null
                and TableID=wwa.id order by Updated desc limit 1) created_by
            , (select group_concat(distinct CONCAT('<font ',o.id,'style=\"color:',IF(sent, '#999999', '#008800'),';\">
                <a target=\"_blank\" style=\"color:',IF(sent, '#999999', '#008800'),';\"
                href=\"auction.php?number=',mauo.auction_number,'&txnid=',mauo.txnid,'\">'
                , o.quantity, ' x reserved for ', 
                mauo.auction_number,'/',mauo.txnid,'</a></font>',
                IF(IFNULL(muo.name,uo.name) is null, '', 
                    CONCAT(' - <font style=\"color:#FF00FF\">Responsible '
                        ,IFNULL(IFNULL(muo.name,uo.name),IFNULL(mauo.shipping_username,auo.shipping_username)),'</font>')
                    )
                ) SEPARATOR '<br>')
                from orders o
                join auction auo on auo.auction_number=o.auction_number and auo.txnid=o.txnid
                left join auction mauo on auo.main_auction_number=mauo.auction_number and auo.main_txnid=mauo.txnid
                left join users uo on uo.username=auo.shipping_username
                left join users muo on muo.username=mauo.shipping_username
                where wwa.id=o.wwo_order_id #and o.spec_order
                ) orders
            , (select SUM(o.quantity)
                from orders o
                where wwa.id=o.wwo_order_id 
                ) reserved_quantity
            , a.volume_per_single_unit volume
            , a.weight_per_single_unit weight
            , d.name destiny
            , wwo.exporter_id
            , a.custom_number_id
            , opar.country_code
            , wwa.parcels+IFNULL((select sum(parcels) ct from wwo_article where wwo_article.custom_pdf_combine_with=wwa.id),0) ct
            , wwo.currency rate
            , unc_o.auction_number unc_auction_number
            , unc_o.txnid unc_txnid
            , IFNULL(country.name, cn.country_code) country_of_origin
            , wwa.broken
            , wwa.dont_add_pcs
        FROM wwo_article wwa 
            left join orders unc_o on unc_o.id=wwa.uncomplete_article_order_id
            JOIN ww_order wwo ON wwo.id = wwa.wwo_id
            JOIN op_address_resp opar ON opar.id = wwo.exporter_id
            JOIN article a ON a.article_id=wwa.article_id AND NOT a.admin_id
            left join custom_number cn on cn.id = a.custom_number_id
            left join country on country.code=cn.country_code
            left JOIN users u ON wwa.username=u.username
            left JOIN warehouse w1 ON wwa.from_warehouse=w1.warehouse_id
            left JOIN warehouse w2 ON wwa.to_warehouse=w2.warehouse_id
            left JOIN ww_destiny d ON wwa.destiny_id=d.id
        WHERE wwa.wwo_id = ".$this->data->id."
        and custom_pdf_ignore=0 and custom_pdf_combine_with=0";
        if ($btn=='detailed') {
        } else {
            $q = "select t.country_code, article_id, cn.name
            , cn.custom_number_ch
            , cn.custom_number_eu
            , cn.description_goods
            , IFNULL(country.name, cn.country_code) country_of_origin
            , custom_pdf_combine_with
            , t.rate
                    , sum(total) total, sum(kg) kg, sum(qnt) qnt, sum(ct) ct
                    , ROUND(sum(total)/sum(qnt),2) price
            , t.broken
                from ($q) t
            left join custom_number cn on cn.id = t.custom_number_id
            left join country on country.code=cn.country_code
            group by cn.id, t.broken
            ";
        }
        
        $articles = $this->_dbr->getAll($q);

        foreach($articles as $k=>$dummy) {
            $articles[$k]->name1 = utf8_encode($articles[$k]->name);
//			$articles[$k]->article_id_name1 = ($articles[$k]->article_id==''?'':$articles[$k]->article_id.': ')./*utf8_encode*/($articles[$k]->name);
            $articles[$k]->article_id_name1 = $articles[$k]->description_goods;
/*			$articles[$k]->rate = $this->_dbr->getOne("SELECT value
                 FROM config_api ca
                 JOIN config_api_par cap ON ca.par_id = cap.id
                 AND cap.name = 'currency'
                 AND ca.siteid = '".CountryCodeToSite($articles[$k]->country_code)."'");*/
            $articles[$k]->price = round($articles[$k]->total/$articles[$k]->qnt,2);
        }
        $smarty->assign('config', \Config::getAll());
        $smarty->assign('articles', $articles);
        $broken_articles_count = 0;
        foreach($articles as $r) if ($r->broken) $broken_articles_count++;
        $smarty->assign('broken_articles_count', $broken_articles_count);
        $smarty->assign('articles_count', count($articles)-$broken_articles_count);
        $smarty->assign('container', $this->_dbr->getOne("select container_no from op_order_container where id=".(int)$this->data->container_id));
        
        $destination_ids = [0];
        foreach ($this->destinations as $destination) {
            $destination_ids[] = $destination->warehouse_id;
        }
        
        $destinations = $this->_dbr->getAll("select *
            from warehouse
            where warehouse_id IN (" . implode(',', $destination_ids) . ")");
        $smarty->assign('destinations', $destinations);
        
        $port_ids = [0];
        foreach ($this->ports as $port) {
            $port_ids[] = $port->warehouse_id;
        }
        
        $ports = $this->_dbr->getAll("select *
            from warehouse
            where warehouse_id IN (" . implode(',', $port_ids) . ")");
        $smarty->assign('ports', $ports);
        
        $country_origin = $this->_dbr->getOne("select country.name country
            from country 
            where country.code='".$this->data->country_code_origin."'");
        $smarty->assign('country_origin', $country_origin);
        $country_destination = $this->_dbr->getOne("select country.name country
            from country 
            where country.code='".$this->data->country_code_destination."'");
        $smarty->assign('country_destination', $country_destination);
        $exporter = $this->_dbr->getRow("select op_address_resp.*, country.name country
            from op_address_resp
            join country on country.code=op_address_resp.country_code
            where op_address_resp.id=".(int)$this->data->exporter_id);
        $smarty->assign('exporter', $exporter);
        $consignee = $this->_dbr->getRow("select op_address_resp.*, country.name country
            from op_address_resp
            join country on country.code=op_address_resp.country_code
            where op_address_resp.id=".(int)$this->data->consignee_id);
        $smarty->assign('consignee', $consignee);
        $notify_applicant = $this->_dbr->getRow("select op_address_resp.*, country.name country
            from op_address_resp
            join country on country.code=op_address_resp.country_code
            where op_address_resp.id=".(int)$this->data->notify_applicant_id);
        $smarty->assign('notify_applicant', $notify_applicant);
        $clause = $this->_dbr->getRow("select *
            from ww_clause
            where id=".(int)$this->data->clause_id);
        $smarty->assign('clause', $clause);
        $smarty->assign('ww_order', $this->data);
        $term = $this->_dbr->getOne("select name from ww_terms
            where id=".(int)$this->data->term_id);
        $smarty->assign('term', $term);
        $res = $smarty->fetch('ware2ware_invoice.tpl');
        $res = utf8_decode($res);
        return $res;
//		die($res);
        require_once('tcpdf/config/lang/eng.php');
        require_once('tcpdf/tcpdf.php');
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
#			$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetHeaderMargin(2);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->setLanguageArray($l);
        $pdf->setPrintFooter(true);
//			$pdf->setFontSubsetting(true);
//			$pdf->SetFont('dejavusans', '', 14, '', true);
        $pdf->AddPage();
        $pdf->writeHTML($res, true, false, true, false, '');
        $pdf->Output('wwo_invoice.pdf', 'I');
        return $res;
    }

    static function listAllextended($db, $dbr, $mode, $from_warehouse=0, $to_warehouse=0, $inactive=0, $notcompleted=0, $date=false, $car_id=false)
    {
        $where = ' WHERE 1=1 ';
        $where .= $mode=='all' ? '' : 
         ($mode=='closed' ? ' AND closed=1' : ' AND closed=0 ');
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        if (strlen($inactive)) $where.= " and ".($inactive?'':'NOT')." wwo.deleted ";
        if ($notcompleted) $where.= " and exists (select null from wwo_article wwa 
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=wwo.id 
            and wwa.delivered=0) ";
        if ($from_warehouse) $where.= " and exists (select null from wwo_article wwa 
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=wwo.id 
            and wwa.from_warehouse=$from_warehouse)";
        if ($to_warehouse) $where.= " and exists (select null from wwo_article wwa 
            join article a on wwa.article_id=a.article_id and a.admin_id=0
            where wwa.wwo_id=wwo.id 
            and wwa.to_warehouse=$to_warehouse)";
        if ($date) $where.= " and planned_arrival_date='".$date['year'].'-'.$date['month'].'-'.$date['day']."'";
        if ($car_id) $where.= " and car_id=".$car_id;
        
        $q = "SELECT * FROM (
            SELECT wwo.*, opc.container_no
                , IFNULL(u.name, wwo.username) username_name
                , IFNULL(u1.name, wwo.close_username) close_username_name
                , IF(wwo.closed,
                    (select updated from total_log 
                        where table_name='ww_order' and field_name='closed' and new_value='1' and tableid=wwo.id 
                        order by updated desc limit 1) 
                    , '') close_date
                , c.color
            FROM ww_order wwo
                left join wwo_port on wwo_port.wwo_id=wwo.id AND wwo_port.type = 'destination'
                left join warehouse wd on wd.warehouse_id=wwo_port.warehouse_id
                left join country c on c.code=wd.country_code
                left join op_order_container opc on opc.id=wwo.container_id
                left JOIN users u ON wwo.username=u.username
                left JOIN users u1 ON wwo.close_username=u1.username
            $where 
            ORDER BY `wwo`.`id`, `wwo_port`.`primary` DESC
        ) t GROUP BY t.id";
        
        $r = $dbr->getAll($q);
//		echo "<pre>$q</pre>";
        if (PEAR::isError($r)) {
            print_r($r);
            return;
        }
        return $r;
    }
}
