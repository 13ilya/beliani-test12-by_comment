<?php

/**
 * Loading Area class
 *
 * Contains methods related to prepare get query, create template
 *
 * @version 0.1
 *
 * @param MDB2_Driver_mysql $_db database write/read object identifier
 *
 * @param MDB2_Driver_mysql $_dbr database read (only) object identifier
 *
 * @return void
 */
class LoadingArea {

    private $_db;
    private $_dbr;

    private $_id;
    private $_data;

    public static $ARTICLES_NAMES_LANGUAGES = ['english', 'usa', 'german', 'polish'];

    public function __construct(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id = 0) {
        $this->_db = $db;
        $this->_dbr = $dbr;

        $id = (int)$id;

        $this->_id = $id;
        if ( ! $this->_id) {
            $this->clear();
        }
        else {
            $this->_data = $this->_dbr->query("SELECT * FROM `ware_la` WHERE `id` = $id")->fetchRow();
            if ( ! $this->_data) {
                $this->clear();
            }

            $this->_data->warehouse = $this->_dbr->getOne("SELECT CONCAT(`country_code`, ': ', `name`)
                FROM `warehouse` WHERE `warehouse_id` = '{$this->warehouse_id}'");
        }
        unset($this->_data->id);
    }

    /**
     * Save loading area/ramp
     * @return boolean
     */
    public function save() {
        if ($this->_data->def == '1') {
            $this->_data->hall_id = 0;
            $def_id = $this->_dbr->getOne("SELECT `id` FROM `ware_la`
                    WHERE `warehouse_id` = '{$this->_data->warehouse_id}' AND `def` = '1'");
            if ($def_id && $def_id != $this->_id) {
                return false;
            }
        }

        if ( ! $this->_data->la_name || ! $this->_data->warehouse_id) {
            return false;
        }

        $name = mysql_real_escape_string($this->_data->la_name);

        for ($i = 1; $i < 1000; ++$i) {
            $name_id = $this->_dbr->getOne("SELECT `id` FROM `ware_la` WHERE
                    `warehouse_id` = '{$this->_data->warehouse_id}' AND `la_name` = '$name'");

            if ($name_id && ( ! $this->_id || $name_id != $this->_id)) {
                $name = "{$this->_data->la_name} ($i)";
                $name = mysql_real_escape_string($name);
            }
            else {
                if ($i > 1) {
                    $i--;
                    $this->_data->la_name .= " ($i)";
                }
                break;
            }
        }

        $query = array();
        foreach ($this->_data as $_key => $_value) {
            $query[] = " `$_key` = '" . mysql_real_escape_string($_value) . "' ";
        }

        if ( ! $query) {
            return false;
        }

        if ($this->_id && $this->_dbr->getOne("SELECT `id` FROM `ware_la` WHERE `id` = {$this->_id}")) {
            $query = "UPDATE `ware_la` SET " . implode(",", $query) . " WHERE `id` = {$this->_id}";
        }
        else {
            $query = "INSERT INTO `ware_la` SET " . implode(",", $query);
        }

        return $this->_db->query($query);
    }

    /**
     * Insert loading area/ramp
     * @return boolean
     */
    public function insert() {
        $query = array();
        foreach ($this->_data as $_key => $_value) {
            $query[] = " `$_key` = '" . mysql_real_escape_string($_value) . "' ";
        }

        if ( ! $query) {
            return false;
        }

        $query = "UPDATE `ware_la` SET " . implode(",", $query) . " WHERE `id` = {$this->_id}";
        return $this->_db->query($query);
    }

    /**
     * Clear loading area/ramp
     */
    public function clear() {
        $r = $this->_db->query("EXPLAIN ware_la");
        $this->_id = 0;
        $this->_data = new stdClass;
        while ($field = $r->fetchRow()) {
            if ($field->Field != 'id') {
                $this->_data->{$field->Field} = '';
            }
        }
    }

    /**
     * Get all data loading area/ramp
     * @param type $id
     */
    public function get($id) {
        $this->_id = (int)$id;
        $this->_data = $this->_db->query("SELECT * FROM `ware_la` WHERE `id` = {$this->_id}")->fetchRow();
        unset($this->_data->id);
    }

    public function __destruct() {
        ;
    }

    public function __toString() {
        return "<pre>" . print_r($this->_data, true) . "</pre>";
    }

    public function __set($name, $value) {
        switch ($name) {
            case 'id':
                $this->_id = $value;
                return;
        }

        if (isset($this->_data->$name)) {
            $this->_data->$name = $value;
            return;
        }
    }

    public function __get($name) {
        if (isset($this->_data->$name)) {
            return $this->_data->$name;
        }

        switch ($name) {
            case 'id' :
                return $this->_id;
            default :
                return '';
        }
    }

    /******************************************************************************************************************/

    public function get_contents($with_containers = true)
    {
        $response = [];

        $released_wwo = $this->_dbr->getAssoc("SELECT
                `wwo_article`.`wwo_id`, `wwo_article`.`wwo_id` AS `v`

            FROM `wwo_article`
            JOIN `ww_order` ON `wwo_article`.`wwo_id` = `ww_order`.`id`
            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `wwo_article`.`picking_order_id`
            WHERE `po`.`ware_la_id` = '" . $this->_id . "'
                AND `wwo_article`.`taken` = '0'
                AND `ww_order`.`deleted` = '0'
                AND `ww_order`.`closed` = '0'
            GROUP BY `wwo_article`.`wwo_id`");

        foreach ($released_wwo as $released)
        {
            $response[] = "Released: WWO $released";
        }

        $picking_order_ids = $this->_dbr->getOne("
            SELECT GROUP_CONCAT(`id`)
            FROM `picking_order`
            WHERE `ware_la_id` = '" . $this->_id . "'
        ");

        $shipping_methods = $this->_dbr->getAll("SELECT DISTINCT
                `route`.`name` AS `route_name`
                , CONCAT(`sm`.`country`, ': ', `sm`.`company_name`) AS `shipping_method`
                , (SELECT GROUP_CONCAT(`tn_orders`.`id`) FROM `tn_orders`
                    WHERE `tn_orders`.`order_id` = `o`.`id`) AS `numbers`

            FROM `orders` AS `o` FORCE INDEX (picking_order_id)

            JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                AND `au`.`txnid` = `o`.`txnid`

            LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                AND `au`.`txnid` = `ma`.`txnid`

            LEFT JOIN `route` ON IFNULL(`ma`.`route_id`, `au`.`route_id`) = `route`.`id`

            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `o`.`picking_order_id`
            LEFT JOIN `users` AS `u` ON `po`.`shipping_username` = `u`.`username`
            LEFT JOIN `shipping_method` AS `sm` ON `sm`.`shipping_method_id` =
                IF (IFNULL(`ma`.`shipping_method`, `au`.`shipping_method`), IFNULL(`ma`.`shipping_method`, `au`.`shipping_method`), `u`.`shipping_method`)

            WHERE `po`.`id` IN (" . ($picking_order_ids ? $picking_order_ids : -1) . ")

            AND IFNULL(`ma`.`deleted`, `au`.`deleted`) = 0

            AND `o`.`sent` = '0'
            AND `o`.`manual` = '0'
            #AND `o`.`hidden` = '0'");

        foreach ($shipping_methods as $method)
        {
            if ( ! $method->numbers)
            {
                if ($method->route_name)
                {
                    $response[] = $method->shipping_method . ": " . $method->route_name;
                }
                else
                {
                    $response[] = $method->shipping_method;
                }
            }
        }
        
        if ( ! $with_containers)
        {
            return $response;
        }

        $delievered_container = $this->_dbr->getAssoc("
            SELECT DISTINCT `container_no`, `container_no` `v` FROM (
                SELECT
                    `oc`.`container_no`, `pbab_la_pallet`.`id` AS `pallet_id`, pbabd_la.ware_la_id

                FROM `op_article`
                JOIN `ware_la` ON `ware_la`.`id` = `op_article`.`ware_la_id`
                    AND `ware_la`.`warehouse_id` = `op_article`.`warehouse_id`

                LEFT JOIN `op_order_container` `opc` ON `op_article`.`container_id` = `opc`.`id`
                LEFT JOIN `op_order_container` `master` ON `master`.`id` = `opc`.`master_id`
                LEFT JOIN `op_order_container` `oc` ON `oc`.`id` = IFNULL(`master`.`id`, `opc`.`id`)

                JOIN `vbarcode` ON `vbarcode`.`opa_id` = `op_article`.`id`
                left join `barcode_dn` `bw` on `vbarcode`.`id` = `bw`.`id`

                LEFT JOIN parcel_barcode_article_barcode AS pbab_la_pallet ON vbarcode.id = pbab_la_pallet.barcode_id AND NOT pbab_la_pallet.deleted
                LEFT JOIN parcel_barcode_article_barcode AS pbab_la ON vbarcode.id = pbab_la.barcode_id AND pbab_la.deleted
                LEFT JOIN parcel_barcode_article_barcode_deduct AS pbabd_la ON vbarcode.id = pbabd_la.barcode_id 
                    AND pbabd_la.id = pbab_la.deleted
                    AND pbabd_la.picking_order_id = 0 
                    AND ISNULL(pbabd_la.barcode_inventory_detail_id) 
                    AND pbabd_la.ware_la_id = `op_article`.`ware_la_id`

                WHERE `op_article`.`ware_la_id` = '" . $this->_id . "'
                    AND `bw`.`state2filter` = 'In stock'
                    AND NOT `vbarcode`.`inactive`
                    AND ISNULL(`vbarcode`.`parcel_barcode_id`)
            ) `t` 
            WHERE IFNULL(`pallet_id`, 0) != 0 AND `ware_la_id` = '" . $this->_id . "'
        ");

        foreach ($delievered_container as $delievered)
        {
            $response[] = $delievered;
        }

        return $response;
    }

    public function get_auctions() {

        foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
        {
            $values[] = " `t_{$_lang}`.`value` AS `article_name_{$_lang}` ";
            $translate[] = "
                LEFT JOIN `translation` AS `t_{$_lang}` ON `t_{$_lang}`.`table_name` = 'article'
                    AND `t_{$_lang}`.`field_name` = 'name'
                    AND `t_{$_lang}`.`language` = '{$_lang}'
                    AND `t_{$_lang}`.`id` = `o`.`article_id`
            ";
        }

        $picking_order_ids = $this->_dbr->getOne("
            SELECT GROUP_CONCAT(`id`)
            FROM `picking_order`
            WHERE `ware_la_id` = '" . $this->_id . "'
        ");

        $query = "SELECT
            IFNULL(`ma`.`auction_number`, `au`.`auction_number`) AS `auction_number`
            , IFNULL(`ma`.`source_seller_id`, `au`.`source_seller_id`) AS `source_seller_id`
            , IFNULL(`ma`.`txnid`, `au`.`txnid`) AS `txnid`
            , IFNULL(`ma`.`priority`, `au`.`priority`) AS `priority`
            , IFNULL(`ma`.`deleted`, `au`.`deleted`) AS `deleted`

            , (SELECT CONCAT('Released on ', DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d %H:%i'),
                    ' by ', IFNULL(`users`.`name`, `total_log`.`username`),
                    ' on ', `ware_la`.`la_name`)
                FROM `total_log`
                LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
                LEFT JOIN `orders` ON `orders`.`id` = `total_log`.`TableID`
                LEFT JOIN `picking_order` ON `picking_order`.`id` = `total_log`.`New_value`
                LEFT JOIN `ware_la` ON `ware_la`.`id` = `picking_order`.`ware_la_id`
                WHERE `total_log`.`Table_name` = 'orders'
                    AND `total_log`.`Field_name` = 'picking_order_id'
                    AND `total_log`.`New_value` > 0
                    AND `orders`.`id` = `o`.`id`
                ORDER BY `total_log`.`id` DESC LIMIT 1) AS `released_log`

            , CONCAT(`sm`.`country`, ': ', `sm`.`company_name`) AS `shipping_method`
            , `sm`.`shipping_method_id`
            , `o`.`picking_order_id`
            , `o`.`article_id`
            , " . implode(', ', $values) . "
            , `o`.`quantity`
            , `o`.`picking_order_id`
            , `o`.`repack`
            , `a`.`barcode_type`
            , `a`.`items_per_shipping_unit`

            , (SELECT GROUP_CONCAT(`tn_orders`.`id`) FROM `tn_orders`
                WHERE `tn_orders`.`order_id` = `o`.`id`) AS `numbers`
            , IFNULL(auo.country_code, mao.country_code) AS country_code
            , IFNULL(auo.seller_username, mao.seller_username) AS seller_username
            FROM `orders` AS `o` FORCE INDEX (picking_order_id)

            JOIN `article` AS `a` ON `o`.`article_id` = `a`.`article_id` AND `a`.`admin_id` = 0

            JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                AND `au`.`txnid` = `o`.`txnid`

            LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                AND `au`.`txnid` = `ma`.`txnid`

            " . implode("\n", $translate) . "

            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `o`.`picking_order_id`
            LEFT JOIN `users` AS `u` ON `po`.`shipping_username` = `u`.`username`
            LEFT JOIN `shipping_method` AS `sm` ON `sm`.`shipping_method_id` =
                IF (IFNULL(`ma`.`shipping_method`, `au`.`shipping_method`), IFNULL(`ma`.`shipping_method`, `au`.`shipping_method`), `u`.`shipping_method`)
            LEFT JOIN offer auo ON auo.offer_id = au.offer_id
            LEFT JOIN offer mao ON mao.offer_id = ma.offer_id
            WHERE `o`.`picking_order_id` IN (" . ($picking_order_ids ? $picking_order_ids : -1) . ")

            AND `o`.`sent` = '0'
            AND `o`.`manual` = '0'
            #AND `o`.`hidden` = '0'

            ORDER BY `released_log`";

        $result = $this->_dbr->getAll($query);

//        echo "<pre>$query</pre>";
        
        $articles = [];
        if ($result)
        {
            $articles = $this->get_articles_from_ramp();
        }

        $auctions = [];
        foreach ($result as $row) {
            if ($row->numbers || $row->deleted) {
                continue;
            }

            $row->article_name = '';
            foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
            {
                if ($row->{"article_name_{$_lang}"})
                {
                    $row->article_name = $row->{"article_name_{$_lang}"};
                    break;
                }
            }

            $index = "{$row->auction_number}_{$row->txnid}";

            if ( ! isset($auctions[$index])) {
                $labels = (int)$this->_dbr->getOne("SELECT count(*) FROM `auction_label`
                        WHERE `auction_number` = '{$row->auction_number}'
                            AND `txnid` = {$row->txnid}
                            AND IFNULL(`inactive`, 0) = 0");

                $auctions[$index] = [
                    'source_seller_id' => $row->source_seller_id,
                    'country_code' => $row->country_code,
                    'seller_username' => $row->seller_username,
                    'auction_number' => $row->auction_number,
                    'txnid' => $row->txnid,
                    'priority' => $row->priority,
                    'released_log' => $row->released_log,
                    'shipping_method' => $row->shipping_method,
                    'shipping_method_id' => $row->shipping_method_id,
                    'picking_order_id' => [],
                    'labels' => $labels,
                    'quantity' => 0,
                    'picking_quantity' => 0,
                    'articles' => [],
                ];
            }

            $auctions[$index]['picking_order_id'][] = $row->picking_order_id;

            $picking_quantity = 0;
            foreach ($articles as $key => $_article) {
                if ($_article->article_id != $row->article_id) {
                    continue;
                }

                if ($_article->auction_number !== false && $_article->txnid !== false)
                {
                    if ($_article->auction_number != $row->auction_number || $_article->txnid && $_article->txnid != $row->txnid)
                    {
                        continue;
                    }
                }

                if ($picking_quantity < $row->quantity && $_article->quantity) {
                    if ($_article->quantity > $row->quantity) {
                        $picking_quantity += $row->quantity;
                        $articles[$key]->quantity -= $row->quantity;
                    } else {
                        $picking_quantity += $_article->quantity;
                        $articles[$key]->quantity = 0;
                    }
                }
            }

            $auctions[$index]['quantity'] += $row->quantity;
            $auctions[$index]['picking_quantity'] += $picking_quantity;

            $auctions[$index]['articles'][] = [
                'repack' => $row->repack,
                'article_id' => $row->article_id,
                'article_name' => $row->article_name,
                'picking_order_id' => $row->picking_order_id,
                'barcode_type' => $row->barcode_type,
                'items_per_shipping_unit' => $row->items_per_shipping_unit,
                'quantity' => $row->quantity,
                'picking_quantity' => $picking_quantity,
            ];
        }

        $result = [
            0 => [],
            1 => [],
            2 => [],
        ];

        foreach ($auctions as $_auction) {
            if ($_auction['picking_quantity'] >= $_auction['quantity']) {
                $result[0][] = $_auction;
            }
            else if ( ! $_auction['picking_quantity']) {
                $result[2][] = $_auction;
            }
            else {
                $result[1][] = $_auction;
            }
        }

        return $result;
    }

    public function get_wwo() {

        foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
        {
            $values[] = " `t_{$_lang}`.`value` AS `article_name_{$_lang}` ";
            $translate[] = "
                LEFT JOIN `translation` AS `t_{$_lang}` ON `t_{$_lang}`.`table_name` = 'article'
                    AND `t_{$_lang}`.`field_name` = 'name'
                    AND `t_{$_lang}`.`language` = '{$_lang}'
                    AND `t_{$_lang}`.`id` = `wwo_article`.`article_id`
            ";
        }

        $query = "SELECT
            (SELECT CONCAT('Released on ', DATE_FORMAT(`total_log`.`Updated`, '%Y-%m-%d %H:%i'),
                    ' by ', IFNULL(`users`.`name`, `total_log`.`username`),
                    ' on ', `ware_la`.`la_name`)
                FROM `total_log`
                LEFT JOIN `users` ON `users`.`system_username` = `total_log`.`username`
                LEFT JOIN `wwo_port` ON `wwo_port`.`id` = `total_log`.`TableID`
                LEFT JOIN `ware_la` ON `ware_la`.`id` = `wwo_port`.`ware_la_id`
                WHERE `total_log`.`Table_name` = 'wwo_port'
                    AND `total_log`.`Field_name` = 'released'
                    AND `total_log`.`New_value` = 1
                    AND `wwo_article`.`wwo_id` = `wwo_port`.`wwo_id`
                ORDER BY `total_log`.`id` DESC LIMIT 1) AS `released_log`

            , `wwo_article`.`wwo_id`
            , `wwo_article`.`picking_order_id`
            , `wwo_article`.`article_id`
            , " . implode(', ', $values) . "
            , `wwo_article`.`qnt` AS `quantity`
            , 0 AS `picking_quantity`
            , `a`.`barcode_type`
            , `a`.`items_per_shipping_unit`
            , (SELECT COUNT(*) FROM `barcode_object`
                        WHERE `barcode_object`.`obj` = 'wwo_article' AND `barcode_object`.`obj_id` = `wwo_article`.`id`
            ) AS `bquantity`

            FROM `wwo_article`

            JOIN `ww_order` ON `wwo_article`.`wwo_id` = `ww_order`.`id`

            JOIN `article` AS `a` ON `wwo_article`.`article_id` = `a`.`article_id` AND `a`.`admin_id` = 0

            " . implode("\n", $translate) . "

            LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `wwo_article`.`picking_order_id`
            LEFT JOIN `users` AS `u` ON `po`.`shipping_username` = `u`.`username`

            WHERE `po`.`ware_la_id` = ?

                AND `wwo_article`.`taken` = '0'
                AND `ww_order`.`deleted` = '0'
                AND `ww_order`.`closed` = '0'

            ORDER BY `released_log`, `wwo_article`.`article_id`";

        $result = [];
        $wwo = $this->_dbr->getAll($query, null, [$this->_id]);

        $articles = [];
        if ($wwo)
        {
            $articles = $this->get_articles_from_ramp(true);
        }

        foreach ($wwo as $key => $_wwo) {
            $_wwo->article_name = '';
            foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
            {
                if ($_wwo->{"article_name_{$_lang}"})
                {
                    $_wwo->article_name = $_wwo->{"article_name_{$_lang}"};
                    break;
                }
            }

            if ($_wwo->bquantity >= $_wwo->quantity) {
                unset($wwo[$key]);
                continue;
            }

            $picking_quantity = 0;
            foreach ($articles as $akey => $_article) {
                if ($picking_quantity < $_wwo->quantity && $_article->quantity && $_article->article_id == $_wwo->article_id) {
                    if ($_article->quantity > $_wwo->quantity) {
                        $picking_quantity += $_wwo->quantity;
                        $articles[$akey]->quantity -= $_wwo->quantity;
                    } else {
                        $picking_quantity += $_article->quantity;
                        $articles[$akey]->quantity = 0;
                    }
                }
            }

            $wwo[$key]->picking_quantity += $picking_quantity;
            $result[$_wwo->wwo_id] = [
                0 => [],
                1 => [],
                2 => [],
            ];

        }

        foreach ($wwo as $_wwo) {
            if ($_wwo->picking_quantity >= $_wwo->quantity) {
                 $result[$_wwo->wwo_id][0][] = $_wwo;
            }
            else if ( ! $_wwo->picking_quantity) {
                 $result[$_wwo->wwo_id][2][] = $_wwo;
            }
            else {
                 $result[$_wwo->wwo_id][1][] = $_wwo;
            }
        }

        return $result;
    }

    public function get_parcels() {
        if ($this->_data->def) {
            return $this->get_parcels_loading_area();
        }
        else {
            return $this->get_parcels_from_ramp();
        }
    }

    private function get_parcels_loading_area() {
        $q1 = "select
            b.id,
            b.barcode,
            COUNT(b1.id) barcodes,
            IFNULL(SUM(pba.quantity), 0) articles
        from vparcel_barcode b
            LEFT JOIN vbarcode b1 ON b1.parcel_barcode_id=b.id
            LEFT JOIN parcel_barcode_article pba ON pba.parcel_barcode_id=b.id
        where b.warehouse_id = {$this->warehouse_id} and not IFNULL(b.ware_loc_id, 0) and b.inactive = 0
        GROUP BY b.id
        having barcodes > 0 or articles > 0";
        $list = $this->_dbr->getAll($q1);
        if ($debug) echo $q1;

        foreach($list as $k=>$r) {
            if ($r->barcodes){
                $q = "select b1.barcode from vbarcode b1 where b1.parcel_barcode_id={$r->id}";
                $list[$k]->barcodes = $this->_dbr->getAll($q);
            }else{
                $list[$k]->barcodes = '';
            }
            if ($r->articles) {
                $q = "select CONCAT(
                        IFNULL((select group_concat(rr separator '<br>') from (
                            select concat('<a target=\"_blank\" href=\"article.php?original_article_id=',article_id,'\">'
                                ,sum(quantity), 'x', article_id,': ',t.value,'</a>') rr, article_id
                            from parcel_barcode_article
                            join translation t on table_name='article' and field_name='name' and language='german' and t.id=article_id
                            where parcel_barcode_id={$r->id}
                            group by article_id
                            having sum(quantity)<>0
                            ) t
                            ),'')
                    ) ";
                $list[$k]->articles = $this->_dbr->getOne($q);
            }else{
                $list[$k]->articles = '';
            }
        } // foreach row

        return $list;
    }

    private function get_parcels_from_ramp() {
        $q1 = "SELECT `vb`.`id`
                , `vb`.`barcode`
                , COUNT(*) barcodes
                , IFNULL(SUM(pba.quantity), 0) articles
            FROM `vbarcode` AS `b`
            JOIN `vparcel_barcode` `vb` ON `vb`.`id` = `b`.`parcel_barcode_id`
            JOIN `op_article` AS `opa` ON `opa`.`id` = `b`.`opa_id`
            LEFT JOIN `parcel_barcode_article` `pba` ON `pba`.`parcel_barcode_id` = `vb`.`id`
            WHERE `b`.`inactive` = '0' AND `opa`.`ware_la_id` = '{$this->_id}'
                AND `b`.`ware_loc` IS NULL
            GROUP BY `vb`.`id`
            HAVING `barcodes` > 0 or `articles` > 0";

        $q2 = "SELECT b.id, `b`.`barcode`, COUNT(*) AS `barcodes`, SUM(pba.quantity) AS `articles`
            FROM `vparcel_barcode` `b`

            JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `b`.`id`

            WHERE `b`.`inactive` = '0'
                 AND NOT IFNULL(b.ware_loc_id, 0)
                 AND `pba`.`ware_la_id` = '{$this->_id}'

            GROUP BY `b`.`id`
            HAVING `articles`";

        $query = "$q1 UNION ALL $q2";

        $list = $this->_dbr->getAll($query);
        if ($debug) echo $q1;

        foreach($list as $k=>$r) {
            if ($r->barcodes){
                $q = "select b1.barcode from vbarcode b1 where b1.parcel_barcode_id={$r->id}";
                $list[$k]->barcodes = $this->_dbr->getAll($q);
            }else{
                $list[$k]->barcodes = '';
            }
            if ($r->articles) {
                $q = "select CONCAT(
                        IFNULL((select group_concat(rr separator '<br>') from (
                            select concat('<a target=\"_blank\" href=\"article.php?original_article_id=',article_id,'\">'
                                ,sum(quantity), 'x', article_id,': ',t.value,'</a>') rr, article_id
                            from parcel_barcode_article
                            join translation t on table_name='article' and field_name='name' and language='german' and t.id=article_id
                            where parcel_barcode_id={$r->id}
                            group by article_id
                            having sum(quantity)<>0
                            ) t
                            ),'')
                    ) ";
                $list[$k]->articles = $this->_dbr->getOne($q);
            }else{
                $list[$k]->articles = '';
            }
        } // foreach row

        return $list;
    }

    public function get_articles() {

        if ($this->_data->def) {
            return $this->get_articles_loading_area();
        }
        else {
            return $this->get_articles_from_ramp(-1);
        }

    }

    private function get_articles_loading_area() {
        /* Table for barcode warehouse, if use denormalization - barcode_dn */
        $vbw = 'vbarcode_warehouse';
        if (\Config::get(null, null, 'use_dn')) {
            $vbw = 'barcode_dn';
        }

        foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
        {
            $valuesA[] = " `b`.`article_name` AS `article_name_{$_lang}` ";
            $valuesC[] = " `t_{$_lang}`.`value` AS `article_name_{$_lang}` ";
            $translate[] = "
                LEFT JOIN `translation` AS `t_{$_lang}` ON `t_{$_lang}`.`table_name` = 'article'
                    AND `t_{$_lang}`.`field_name` = 'name'
                    AND `t_{$_lang}`.`language` = '{$_lang}'
                    AND `t_{$_lang}`.`id` = `pba`.`article_id`
            ";
        }

        $query[] = "SELECT `b`.`article_id`
            , 1 AS `quantity`
            , " . implode(', ', $valuesA) . "
            , `b`.`parcel_barcode`
            , `b`.`barcode`
            , `a`.`barcode_type`
            FROM {$vbw} AS bw
            JOIN `vbarcode` AS `b` ON b.id = bw.id
            LEFT JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `pbabd`.`barcode_id` = `b`.`id`
            JOIN `article` AS `a` ON `a`.`article_id` = `b`.`article_id` AND `a`.`admin_id` = 0
            LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
            WHERE IFNULL(`b`.`ware_loc`, '') = ''
                AND ISNULL(`b`.`parcel_barcode`)
                AND `bs`.`type` = 'in'
                AND `b`.`inactive` = 0
                AND `bw`.`last_warehouse_id` = {$this->warehouse_id}
                AND IFNULL(`pbabd`.`picking_order_id`, '0') = 0 ";

        $query[] = "SELECT `pba`.`article_id`
                    , SUM(`pba`.`quantity`) AS `quantity`
                    , " . implode(', ', $valuesC) . "
                    , `b`.`barcode` AS `parcel_barcode`
                    , '' AS `barcode`
                    , `a`.`barcode_type`
                FROM `vparcel_barcode` AS `b`
                JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `b`.`id`
                " . implode("\n", $translate) . "
                JOIN `parcel_barcode` AS `pb` ON `pb`.`id` = `pba`.`parcel_barcode_id`
                JOIN `article` AS `a` ON `a`.`article_id` = `pba`.`article_id` AND `a`.`admin_id` = 0
                WHERE
                    `b`.`warehouse_id` = {$this->warehouse_id} AND NOT IFNULL(`b`.`ware_loc_id`, 0) AND `b`.`inactive` = 0
                GROUP BY `pba`.`article_id`
                HAVING `quantity`";

        $query = implode("\n UNION ALL \n", $query);

        $articles = $this->_dbr->getAll($query);

        foreach ($articles as $key => $article)
        {
            $articles[$key]->article_name = '';
            foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
            {
                if ($article->{"article_name_{$_lang}"})
                {
                    $articles[$key]->article_name = $article->{"article_name_{$_lang}"};
                    break;
                }
            }
        }

        return $articles;
    }

    private function get_articles_from_ramp($is_wwo = false) {
        /* Table for barcode warehouse, if use denormalization - barcode_dn */
        $vbw = 'vbarcode_warehouse';
        $bt = 'b1';
        if (\Config::get(null, null, 'use_dn')) {
            $vbw = 'barcode_dn';
            $bt = 'bw';
        }

        $warehouse_id = (int)$this->_dbr->getOne("SELECT `warehouse_id` FROM `ware_la` WHERE `id` = '{$this->_id}'");

        $statesIN = $this->_dbr->getAssoc("SELECT code, title FROM barcode_state WHERE type='in' ORDER BY id");
        $statesIN = array_keys($statesIN);

        if ($is_wwo == -1) {
            $where1 = " AND IFNULL(`po`.`ware_la_id`, `pbabd`.`ware_la_id`) = '{$this->_id}' /*AND `po`.`delivered` = 1*/";
            $where2 = " AND `po`.`ware_la_id` = '{$this->_id}' /*AND `po`.`delivered` = 1*/";
        } else if ($is_wwo) {
            $where1 = " AND `po`.`wwo_id` != 0 AND `po`.`ware_la_id` = '{$this->_id}'/* AND `po`.`delivered` = 1*/";
            $where2 = " AND `po`.`wwo_id` != 0 AND `po`.`ware_la_id` = '{$this->_id}'/* AND `po`.`delivered` = 1*/";
        } else {
            $where1 = " AND `po`.`shipping_username` != '' AND `po`.`ware_la_id` = '{$this->_id}' /*AND `po`.`delivered` = 1*/";
            $where2 = " AND `po`.`shipping_username` != '' AND `po`.`ware_la_id` = '{$this->_id}' /*AND `po`.`delivered` = 1*/";
        }

        foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
        {
            $values[] = " `t_{$_lang}`.`value` AS `article_name_{$_lang}` ";
            $translate[] = "
                LEFT JOIN `translation` AS `t_{$_lang}` ON `t_{$_lang}`.`table_name` = 'article'
                    AND `t_{$_lang}`.`field_name` = 'name'
                    AND `t_{$_lang}`.`language` = '{$_lang}'
                    AND `t_{$_lang}`.`id` = `a`.`article_id`
            ";
        }

        $query[] = "SELECT `a`.`article_id`
                    , `b`.`barcode`
                    , `b`.`id` AS `barcode_id`
                    , `b`.`op_order_id`
                    , `b`.`new_op_order_id`
                    , " . implode(',', $values) . "
                    , CONCAT(`tl`.`Updated`, ' by ', IFNULL(`u`.`name`, `tl`.`username`)) AS `delivered_text`
                    , `tl`.`Updated` AS `delivered_date`
                    , 1 AS `quantity`
                    , 'A' AS `type`
                    , `pbabd`.`picking_order_id`
                    , bw.state2filter
                    , bw.reserved
                    , '0' AS `order_quantity`
                    , `po`.`delivered`
                FROM {$vbw} AS bw
                JOIN `vbarcode` AS `b` ON b.id = bw.id
                JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `pbabd`.`barcode_id` = `b`.`id`
                JOIN `article` AS `a` ON `b`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'A' AND `a`.`admin_id` = 0
                LEFT JOIN `total_log` AS `tl` ON `tl`.`table_name` = 'parcel_barcode_article_barcode_deduct'
                    AND `tl`.`TableID` = `pbabd`.`id` AND `tl`.`field_name` = 'id'
                LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`
                " . implode("\n", $translate) . "
                LEFT JOIN `picking_order` AS `po` ON `pbabd`.`picking_order_id` = `po`.`id`
                LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
                WHERE `b`.`inactive` = '0' AND `pbabd`.`delivered` = IF(ISNULL(po.ware_la_id), '0', '1')
                    AND `bw`.`last_warehouse_id` = '$warehouse_id'
                    AND `bs`.`type` = 'in'
                    AND (
                        EXISTS (SELECT NULL FROM `orders` AS `o` 
                            JOIN `auction` AS `a` ON `a`.`auction_number` = `o`.`auction_number`
                                AND `a`.`txnid` = `o`.`txnid`
                            WHERE `po`.`id` = `o`.`picking_order_id` 
                                AND NOT `o`.`sent`
                                AND NOT `a`.`deleted`)
                        OR
                        EXISTS (SELECT NULL FROM `wwo_article` AS `wwo` WHERE `po`.`id` = `wwo`.`picking_order_id`)
                        OR
                        EXISTS (SELECT NULL FROM `barcode_inventory_detail` AS `bid` WHERE `bid`.`id` = `pbabd`.`barcode_inventory_detail_id`)
                    )
                $where1
                GROUP BY `barcode`
                ";

        $query[] = "SELECT `a`.`article_id`
                    , `b`.`barcode`
                    , `b`.`id` AS `barcode_id`
                    , `b`.`op_order_id`
                    , `b`.`new_op_order_id`
                    , " . implode(',', $values) . "
                    , CONCAT(`tl`.`Updated`, ' by ', IFNULL(`u`.`name`, `tl`.`username`)) AS `delivered_text`
                    , `tl`.`Updated` AS `delivered_date`
                    , 1 AS `quantity`
                    , 'A' AS `type`
                    , '0' AS `picking_order_id`
                    , bw.state2filter
                    , bw.reserved
                    , '0' AS `order_quantity`
                    , '0' AS `delivered`
                FROM {$vbw} AS bw
                JOIN `vbarcode` AS `b` ON b.id = bw.id
                LEFT JOIN `parcel_barcode_article_barcode` AS `pbab` ON b.id = pbab.barcode_id AND pbab.deleted
                LEFT JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `pbabd`.`barcode_id` = `b`.`id`
                    AND pbabd.id = pbab.deleted
                    AND pbabd.picking_order_id = 0
                    AND ISNULL(pbabd.barcode_inventory_detail_id)

                JOIN `article` AS `a` ON `b`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'A' AND `a`.`admin_id` = 0
                LEFT JOIN `total_log` AS `tl` ON `tl`.`table_name` = 'parcel_barcode_article_barcode_deduct'
                    AND `tl`.`TableID` = `pbabd`.`id` AND `tl`.`field_name` = 'id'
                LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`

                " . implode("\n", $translate) . "

                LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
                WHERE `b`.`inactive` = '0' AND `pbabd`.`delivered` = 0
                    AND `bw`.`last_warehouse_id` = '$warehouse_id'
                    AND `bs`.`type` = 'in'
                    AND NOT EXISTS (SELECT NULL FROM `parcel_barcode_article_barcode` WHERE b.id = parcel_barcode_article_barcode.barcode_id AND NOT parcel_barcode_article_barcode.deleted)
                    AND `pbabd`.`ware_la_id` = '{$this->_id}'
                GROUP BY `barcode`
                ";

        $query[] = "SELECT `a`.`article_id`
                    , '' AS `barcode`
                    , `pb`.`id` AS `barcode_id`
                    , '' AS `op_order_id`
                    , '' AS `new_op_order_id`
                    , " . implode(',', $values) . "
                    , CONCAT(`tl`.`Updated`, ' by ', IFNULL(`u`.`name`, `tl`.`username`)) AS `delivered_text`
                    , `tl`.`Updated` AS `delivered_date`
                    , -SUM(`popb`.`quantity`) AS `quantity`
                    , 'C' AS `type`
                    , `popb`.`picking_order_id`
                    , '' state2filter
                    , '1' reserved
                    , '0' AS `order_quantity`
                    , `po`.`delivered`
                FROM `parcel_barcode_article_deduct` AS `popb`
                LEFT JOIN `vparcel_barcode` AS `pb` ON `popb`.`parcel_barcode_id` = `pb`.`id`
                JOIN `article` AS `a` ON `popb`.`article_id` = `a`.`article_id` and `a`.`barcode_type`='C' AND `a`.`admin_id` = 0
                LEFT JOIN `total_log` AS `tl` ON `tl`.`table_name` = 'parcel_barcode_article_deduct'
                    AND `tl`.`TableID` = `popb`.`id` AND `tl`.`field_name` = 'id'
                LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`
                " . implode("\n", $translate) . "
                LEFT JOIN `picking_order` AS `po` ON `popb`.`picking_order_id` = `po`.`id`
                WHERE IFNULL(`pb`.`inactive`, 0) = 0 AND `popb`.`delivered` = '1'
                    AND IFNULL(`pb`.`warehouse_id`, '$warehouse_id') = '$warehouse_id'
                    AND (
                        EXISTS (SELECT NULL FROM `orders` AS `o` 
                            JOIN `auction` AS `a` ON `a`.`auction_number` = `o`.`auction_number`
                                AND `a`.`txnid` = `o`.`txnid`
                            WHERE `po`.`id` = `o`.`picking_order_id` 
                                AND NOT `o`.`sent`
                                AND NOT `a`.`deleted`)
                        OR
                        EXISTS (SELECT NULL FROM `wwo_article` AS `wwo` WHERE `po`.`id` = `wwo`.`picking_order_id`)
                    )
                $where2
                GROUP BY `article_id`, `barcode_id`, `picking_order_id` HAVING `quantity` > 0";

        $query[] = "SELECT `a`.`article_id`
                    , '' AS `barcode`
                    , 0 AS `barcode_id`
                    , '' AS `op_order_id`
                    , '' AS `new_op_order_id`
                    , " . implode(',', $values) . "
                    , CONCAT(`tl`.`Updated`, ' by ', IFNULL(`u`.`name`, `tl`.`username`)) AS `delivered_text`
                    , `tl`.`Updated` AS `delivered_date`
                    , SUM(`pab`.`quantity`) AS `quantity`
                    , 'C' AS `type`
                    , 999999999999 AS `picking_order_id`
                    , '' state2filter
                    , '1' reserved
                    , '0' AS `order_quantity`
                    , '0' AS `delivered`
                FROM `parcel_barcode_article` AS `pab`
                JOIN `article` AS `a` ON `pab`.`article_id` = `a`.`article_id` and `a`.`barcode_type`='C' AND `a`.`admin_id` = 0
                LEFT JOIN `total_log` AS `tl` ON `tl`.`table_name` = 'parcel_barcode_article'
                    AND `tl`.`TableID` = `pab`.`id` AND `tl`.`field_name` = 'id'
                LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`
                " . implode("\n", $translate) . "
                WHERE `pab`.`ware_la_id` = '{$this->_id}'
                GROUP BY `a`.`article_id`
                HAVING `quantity` > 0";

        $query = implode("\n UNION ALL \n", $query);

        $articles = $this->_dbr->getAll($query);

        $A_articles = $this->getAArticles($articles, $statesIN);
        $C_articles = $this->getCArticles($articles);

//        echo "<pre>$query</pre>";
//        var_dump($articles);

        if ($is_wwo == -1) {
//            $query = "SELECT `a`.`article_id`
//                        , `b`.`barcode`
//                        , `b`.`id` AS `barcode_id`
//                        , `b`.`op_order_id`
//                        , `b`.`new_op_order_id`
//                        , " . implode(',', $values) . "
//                        , CONCAT('Article delivered on ', opa.add_to_warehouse_date, ' by ', opa.add_to_warehouse_uname) AS `delivered_text`
//                        , opa.add_to_warehouse_date AS `delivered_date`
//                        , 1 AS `quantity`
//                        , 'A' AS `type`
//                        , '0' AS `picking_order_id`
//                        , bw.state2filter
//                        , bw.reserved
//                        , '0' AS `order_quantity`
//                        , '0' AS `delivered`
//                    FROM `vbarcode` AS `b`
//                    JOIN {$vbw} AS bw ON b.id = bw.id
//                    JOIN `op_article` AS `opa` ON `opa`.`id` = `b`.`opa_id`
//                        AND bw.last_warehouse_id = opa.warehouse_id
//                    JOIN `article` AS `a` ON `b`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'A' AND `a`.`admin_id` = 0
//                    LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
//                    " . implode("\n", $translate) . "
//                    WHERE `b`.`inactive` = '0' AND `opa`.`ware_la_id` = '{$this->_id}'
//                        AND `bs`.`type` = 'in'
//                        AND NOT EXISTS (SELECT null FROM `parcel_barcode_article_barcode` `pbab`
//                            WHERE `pbab`.`barcode_id` = `b`.`id`)
//                    GROUP BY `barcode`";
//
//            $articles = $this->_dbr->getAll($query);
//            foreach ($articles as $key => $_article)
//            {
//                $_article->article_name = '';
//                foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
//                {
//                    if ($_article->{"article_name_{$_lang}"})
//                    {
//                        $articles[$key]->article_name = $_article->{"article_name_{$_lang}"};
//                        break;
//                    }
//                }
//            }
//
//            $A_articles = array_merge($A_articles, $articles);

//            $articles_from_ramps = $this->_dbr->getAssoc("SELECT
//                    `op_article`.`article_id`
//                    , SUM(`op_article`.`qnt_delivered`)
//                        - IFNULL((
//                            SELECT SUM(`pba`.`quantity`) FROM `parcel_barcode_article` `pba`
//                                WHERE `pba`.`article_id` = `op_article`.`article_id` AND `pba`.`ramp_id` = `op_article`.`ware_la_id`
//                                    AND NOT EXISTS (SELECT null FROM `parcel_barcode_article` `pba1`
//                                        WHERE `pba1`.`id` = `pba`.`id`)
//                        ), 0)
//                        - IFNULL((
//                            SELECT SUM(`pbad`.`quantity`) FROM `parcel_barcode_article_deduct` `pbad`
//                                WHERE `pbad`.`article_id` = `op_article`.`article_id` AND `pbad`.`ramp_id` = `op_article`.`ware_la_id`
//                                    AND NOT EXISTS (SELECT null FROM `parcel_barcode_article_deduct` `pbad1`
//                                        WHERE `pbad1`.`id` = `pbad`.`id`)
//                        ), 0) AS `quantity`
//
//                FROM `op_article`
//                JOIN `article` ON `article`.`article_id` = `op_article`.`article_id`
//                WHERE `op_article`.`ware_la_id` = '{$this->id}'
//                    AND `article`.`admin_id` = 0
//                    AND `article`.`barcode_type` = 'C'
//
//                GROUP BY `op_article`.`article_id`
//                HAVING `quantity`");
//
//            $query = [];
//            foreach ($articles_from_ramps as $article_id => $quantity) {
//                $art = new \Article($this->_db, $this->_dbr, $article_id, -1, 0);
//                $total = $art->getPieces($this->warehouse_id);
//                $total_pieces = $art->getNotOnFloor($this->warehouse_id);
//
//                $quantity = min(($total - $total_pieces), $quantity);
//
//                if ($quantity > 0) {
//                    $query[] = "SELECT `a`.`article_id`
//                        , '' `barcode`
//                        , '' AS `barcode_id`
//                        , `opa`.`op_order_id`
//                        , '' AS `new_op_order_id`
//                        , " . implode(',', $values) . "
//                        , CONCAT('Article delivered on ', opa.add_to_warehouse_date, ' by ', opa.add_to_warehouse_uname) AS `delivered_text`
//                        , opa.add_to_warehouse_date AS `delivered_date`
//                        , $quantity AS `quantity`
//                        , 'C' AS `type`
//                        , '0' AS `picking_order_id`
//                        , '' state2filter
//                        , '1' reserved
//                        , '0' AS `order_quantity`
//                        , '0' AS `delivered`
//                    FROM `op_article` AS `opa`
//                    JOIN `article` AS `a` ON `opa`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'C' AND `a`.`admin_id` = 0
//                    " . implode("\n", $translate) . "
//                    WHERE `opa`.`ware_la_id` = '{$this->_id}'
//                        AND `opa`.`article_id` = '$article_id'
//                    GROUP BY `article_id`";
//                }
//            }
//
//            if ($query)
//            {
//                $query = implode("\n UNION ALL \n", $query);
//                $articles = $this->_dbr->getAll($query);
//
//                $C_articles = array_merge($C_articles, $articles);
//            }
        }

        return array_merge($A_articles, $C_articles);
    }

    private function getCArticles($articles)
    {
        $C_articles = [];

        $auctions = [];
        $auctions_keys = [];
        foreach ($articles as $_article)
        {
            if ($_article->type == 'C')
            {
                $auctions_keys[$_article->article_id . '-' . $_article->picking_order_id] = true;
            }
        }

        foreach ($auctions_keys as $key => $dummy)
        {
            $key = explode('-', $key);
            $key = array_map('intval', $key);

            $query = "SELECT IFNULL(`ma`.`auction_number`, `au`.`auction_number`) AS `auction_number`
                    , IFNULL(`ma`.`txnid`, `au`.`txnid`) AS `txnid`
                    , `o`.`sent`, `o`.`quantity`
                    , (SELECT GROUP_CONCAT(`tn_orders`.`id`) FROM `tn_orders` WHERE `tn_orders`.`order_id` = `o`.`id`) AS `numbers`
                    , `o`.`article_id`
                    , `o`.`picking_order_id`

                FROM `orders` AS `o`

                JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                    AND `au`.`txnid` = `o`.`txnid`

                LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                    AND `au`.`txnid` = `ma`.`txnid`

                WHERE
                    `o`.`article_id` = '" . $key[0] . "'
                    AND `o`.`manual` = 0
                    AND `o`.`picking_order_id` = '" . $key[1] . "'
                ";
            foreach ($this->_dbr->getAll($query) as $_auction)
            {
                $auctions[] = $_auction;
            }
        }

        foreach ($articles as $_article)
        {
            if ( ! $_article->picking_order_id) {
                continue;
            }

            if ($_article->type != 'C') {
                continue;
            }

            $_article->article_name = '';
            foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
            {
                if ($_article->{"article_name_{$_lang}"})
                {
                    $_article->article_name = $_article->{"article_name_{$_lang}"};
                    break;
                }
            }

            $_article->auction_number = false;
            $_article->txnid = false;
            $_article->numbers = false;

            foreach ($auctions as $key => $auction)
            {
                if ($auction->article_id == $_article->article_id && $auction->picking_order_id == $_article->picking_order_id)
                {
                    $_temp_article = new stdClass;
                    $_temp_article = clone $_article;

                    $_temp_article->auction_number = $auction->auction_number;
                    $_temp_article->txnid = $auction->txnid;
                    $_temp_article->numbers = $auction->numbers;

                    if ($auction->quantity == $_article->quantity)
                    {
                        $_temp_article->order_quantity = $auction->quantity;

                        $_article = null;
                        unset($auctions[$key]);
                    }
                    else if ($auction->quantity < $_article->quantity)
                    {
                        $_article->quantity -= $auction->quantity;

                        $_temp_article->quantity = $auction->quantity;
                        $_temp_article->order_quantity = $auction->quantity;

                        unset($auctions[$key]);
                    }
                    else if ($auction->quantity > $_article->quantity)
                    {
                        $_temp_article->order_quantity = $_article->quantity;

                        $auctions[$key]->quantity -= $_article->quantity;

                        $_article = null;
                    }

                    $C_articles[] = $_temp_article;
                    $_temp_article = null;
                }
            }

            if ($_article)
            {
                $C_articles[] = $_article;
            }
        }

        foreach ($C_articles as $key => $_article)
        {
            if ($_article->picking_order_id) {
                $query = "SELECT IFNULL(`ma`.`auction_number`, `au`.`auction_number`) AS `auction_number`
                        , IFNULL(`ma`.`txnid`, `au`.`txnid`) AS `txnid`
                        , `o`.`sent`, `o`.`quantity`
                    FROM `orders` AS `o`

                    JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                        AND `au`.`txnid` = `o`.`txnid`

                    LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                        AND `au`.`txnid` = `ma`.`txnid`

                    WHERE
                        `o`.`article_id` = '{$_article->article_id}' AND `o`.`manual` = 0
                        AND `o`.`picking_order_id` = {$_article->picking_order_id}
                    LIMIT 1";

                $auction = $this->_dbr->getRow($query);
                if ($auction && $auction->sent && $auction->auction_number == $_article->auction_number && $auction->txnid == $_article->txnid) {
                    unset($C_articles[$key]);
                    continue;
                }

                $query = "SELECT
                        `wwo_article`.`wwo_id`
                        , `wwo_article`.`taken`
                        , `wwo_article`.`taken`
                        , `ww_order`.`deleted`
                        , `ww_order`.`closed`
                    FROM `wwo_article`
                    JOIN `ww_order` ON `wwo_article`.`wwo_id` = `ww_order`.`id`
                    LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `wwo_article`.`picking_order_id`
                    WHERE `wwo_article`.`article_id` = ?
                        AND `wwo_article`.`picking_order_id` = ?
                    ORDER BY `wwo_article`.`delivered_datetime` DESC
                    LIMIT 1";

                $wwo = $this->_dbr->getRow($query, null, [$_article->article_id, $_article->picking_order_id]);
                if ($wwo && ($wwo->deleted || $wwo->closed || $wwo->taken)) {
                    unset($C_articles[$key]);
                    continue;
                }

                $C_articles[$key]->wwo_id = isset($wwo->wwo_id) && $_article->reserved && ! $wwo->delivered ? $wwo->wwo_id : false;
            }
        }

        return $C_articles;
    }

    private function getAArticles($articles, $statesIN)
    {
        $A_articles = [];

        foreach ($articles as $_article)
        {
            $_article->article_name = '';
            foreach (self::$ARTICLES_NAMES_LANGUAGES as $_lang)
            {
                if ($_article->{"article_name_{$_lang}"})
                {
                    $_article->article_name = $_article->{"article_name_{$_lang}"};
                    break;
                }
            }

            if ($_article->type == 'A')
            {
                $where = '';
                if ($_article->picking_order_id)
                {
//                    $where = " AND `o`.`picking_order_id` = '{$_article->picking_order_id}' ";
                }

                $query = "SELECT IFNULL(`ma`.`auction_number`, `au`.`auction_number`) AS `auction_number`
                        , IFNULL(`ma`.`txnid`, `au`.`txnid`) AS `txnid`
                        , `o`.`sent`, `o`.`quantity`
                        , (SELECT GROUP_CONCAT(`tn_orders`.`id`) FROM `tn_orders`
                            WHERE `tn_orders`.`order_id` = `o`.`id`) AS `numbers`

                    FROM `orders` AS `o`

                    JOIN `barcode_object` AS `boo` ON `boo`.`obj` = 'orders' AND `boo`.`obj_id` = `o`.`id`
                    JOIN `barcode` AS `bc` ON `boo`.`barcode_id` = `bc`.`id`

                    JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                        AND `au`.`txnid` = `o`.`txnid`

                    LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                        AND `au`.`txnid` = `ma`.`txnid`

                    WHERE `o`.`article_id` = '{$_article->article_id}' AND `o`.`manual` = 0
                        $where
                        AND `bc`.`id` = {$_article->barcode_id}

                    LIMIT 1";

                $auction = $this->_dbr->getRow($query);

                if ($auction && $auction->sent && !in_array($_article->state2filter, $statesIN)) {
                    continue;
                }

                $_article->auction_number = false;
                $_article->txnid = false;
                $_article->numbers = false;
                if ($auction && $_article->reserved) {
                    $_article->auction_number = $auction->auction_number;
                    $_article->txnid = $auction->txnid;
                    $_article->order_quantity = $auction->quantity;
                    $_article->numbers = $auction->numbers;
                }

                $query = "SELECT `wwo`.*
                            FROM `barcode_object` AS `bo`
                            JOIN `vbarcode` AS `b` ON `bo`.`barcode_id` = `b`.`id`
                            JOIN `wwo_article` AS `wwo` ON `bo`.`obj_id` = `wwo`.`id`
                            WHERE
                                `bo`.`obj` = 'wwo_article'
                                AND `b`.`id` = '{$_article->barcode_id}'
                                AND `wwo`.`article_id` = '{$_article->article_id}'
                            ORDER BY delivered_datetime DESC
                            LIMIT 1";

                $wwo = $this->_dbr->getRow($query);
                if ($wwo && (($wwo->delivered && $wwo->to_warehouse != $this->warehouse_id) || ($wwo->taken && $wwo->from_warehouse == $this->warehouse_id))) {
                    continue;
                }

                $_article->wwo_id = isset($wwo->wwo_id) && $_article->reserved && ! $wwo->delivered ? $wwo->wwo_id : false;

                $A_articles[] = $_article;
            }
        }

        return $A_articles;
    }

    private function get_count_articles_from_ramp($ramp_id, $warehouse_id) {

        /* Table for barcode warehouse, if use denormalization - barcode_dn */
        $vbw = 'vbarcode_warehouse';
        $bt = 'b1';
        if (\Config::get(null, null, 'use_dn')) {
            $vbw = 'barcode_dn';
            $bt = 'bw';
        }

        $statesIN = $this->_dbr->getAssoc("SELECT code, title FROM barcode_state WHERE type='in' ORDER BY id");
        $statesIN = array_keys($statesIN);

        $where1 = " AND IFNULL(`po`.`ware_la_id`, `pbabd`.`ware_la_id`) = '{$ramp_id}' ";
        $where2 = " AND `po`.`ware_la_id` = '{$ramp_id}' ";

        $query[] = "SELECT `a`.`article_id`
                    , `b`.`id` AS `barcode_id`
                    , 1 AS `quantity`
                    , 'A' AS `type`
                    , `pbabd`.`picking_order_id`
                    , bw.state2filter
                    , bw.reserved
                FROM `vbarcode` AS `b`
                JOIN {$vbw} AS bw ON b.id = bw.id
                JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `pbabd`.`barcode_id` = `b`.`id`
                JOIN `article` AS `a` ON `b`.`article_id` = `a`.`article_id` and `a`.`barcode_type`='A' AND `a`.`admin_id` = 0
                LEFT JOIN `picking_order` AS `po` ON `pbabd`.`picking_order_id` = `po`.`id`
                LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
                WHERE `b`.`inactive` = '0' AND `pbabd`.`delivered` = IF(ISNULL(po.ware_la_id), '0', '1')
                    AND `bw`.`last_warehouse_id` = '$warehouse_id'
                    AND `bs`.`type` = 'in'
                    AND (
                        EXISTS (SELECT NULL FROM `orders` AS `o` 
                            JOIN `auction` AS `a` ON `a`.`auction_number` = `o`.`auction_number`
                                AND `a`.`txnid` = `o`.`txnid`
                            WHERE `po`.`id` = `o`.`picking_order_id` 
                                AND NOT `o`.`sent`
                                AND NOT `a`.`deleted`)
                        OR
                        EXISTS (SELECT NULL FROM `wwo_article` AS `wwo` WHERE `po`.`id` = `wwo`.`picking_order_id`)
                        OR
                        EXISTS (SELECT NULL FROM `barcode_inventory_detail` AS `bid` WHERE `bid`.`id` = `pbabd`.`barcode_inventory_detail_id`)
                    )
                $where1

                GROUP BY `b`.`id`
                ";

        $query[] = "SELECT `a`.`article_id`
                    , `b`.`id` AS `barcode_id`
                    , 1 AS `quantity`
                    , 'A' AS `type`
                    , `pbabd`.`picking_order_id`
                    , bw.state2filter
                    , bw.reserved
                FROM `vbarcode` AS `b`
                JOIN {$vbw} AS bw ON b.id = bw.id

                LEFT JOIN `parcel_barcode_article_barcode` AS `pbab` ON b.id = pbab.barcode_id AND pbab.deleted
                LEFT JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `pbabd`.`barcode_id` = `b`.`id`
                    AND pbabd.id = pbab.deleted
                    AND pbabd.picking_order_id = 0
                    AND ISNULL(pbabd.barcode_inventory_detail_id)

                JOIN `article` AS `a` ON `b`.`article_id` = `a`.`article_id` and `a`.`barcode_type`='A' AND `a`.`admin_id` = 0
                LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
                WHERE `b`.`inactive` = '0' AND `pbabd`.`delivered` = 0
                    AND `bw`.`last_warehouse_id` = '$warehouse_id'
                    AND `bs`.`type` = 'in'
                                        AND NOT EXISTS (SELECT NULL FROM `parcel_barcode_article_barcode` WHERE b.id = parcel_barcode_article_barcode.barcode_id AND NOT parcel_barcode_article_barcode.deleted)
                 AND `pbabd`.`ware_la_id` = '{$ramp_id}'

                 GROUP BY `b`.`id`";

        $query[] = "SELECT `a`.`article_id`
                    , 0 AS `barcode_id`
                    , SUM(`pab`.`quantity`) AS `quantity`
                    , 'C' AS `type`
                    , 999999999999 AS `picking_order_id`
                    , '' state2filter
                    , '1' reserved
                FROM `parcel_barcode_article` AS `pab`
                JOIN `article` AS `a` ON `pab`.`article_id` = `a`.`article_id` and `a`.`barcode_type`='C' AND `a`.`admin_id` = 0
                WHERE `pab`.`ware_la_id` = '{$ramp_id}'
                GROUP BY `a`.`article_id`
                HAVING `quantity` > 0";

        $query[] = "SELECT `a`.`article_id`
                    , `pb`.`id` AS `barcode_id`
                    , -SUM(`popb`.`quantity`) AS `quantity`
                    , 'C' AS `type`
                    , `popb`.`picking_order_id`
                    , '' state2filter
                    , '1' reserved
                FROM `parcel_barcode_article_deduct` AS `popb`
                LEFT JOIN `vparcel_barcode` AS `pb` ON `popb`.`parcel_barcode_id` = `pb`.`id`
                JOIN `article` AS `a` ON `popb`.`article_id` = `a`.`article_id` and `a`.`barcode_type`='C' AND `a`.`admin_id` = 0
                LEFT JOIN `picking_order` AS `po` ON `popb`.`picking_order_id` = `po`.`id`
                WHERE IFNULL(`pb`.`inactive`, 0) = 0 AND `popb`.`delivered` = '1'
                    AND IFNULL(`pb`.`warehouse_id`, '$warehouse_id') = '$warehouse_id'
                    AND (
                        EXISTS (SELECT NULL FROM `orders` AS `o` 
                            JOIN `auction` AS `a` ON `a`.`auction_number` = `o`.`auction_number`
                                AND `a`.`txnid` = `o`.`txnid`
                            WHERE `po`.`id` = `o`.`picking_order_id` 
                                AND NOT `o`.`sent`
                                AND NOT `a`.`deleted`)
                        OR
                        EXISTS (SELECT NULL FROM `wwo_article` AS `wwo` WHERE `po`.`id` = `wwo`.`picking_order_id`)
                    )

                $where2

                GROUP BY `article_id`, `barcode_id`, `picking_order_id` HAVING `quantity` > 0";

//        $query[] = "SELECT `a`.`article_id`
//                    , `b`.`id` AS `barcode_id`
//                    , 1 AS `quantity`
//                    , 'A' AS `type`
//                    , '0' AS `picking_order_id`
//                    , bw.state2filter
//                    , bw.reserved
//                FROM `vbarcode` AS `b`
//                JOIN {$vbw} AS bw ON b.id = bw.id
//                JOIN `op_article` AS `opa` ON `opa`.`id` = `b`.`opa_id`
//                    AND bw.last_warehouse_id = opa.warehouse_id
//                JOIN `article` AS `a` ON `b`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'A' AND `a`.`admin_id` = 0
//                LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
//                WHERE `b`.`inactive` = '0' AND `opa`.`ware_la_id` = '{$ramp_id}'
//                    AND `bw`.`last_warehouse_id` = '$warehouse_id'
//                    AND `bs`.`type` = 'in'
//                    AND NOT EXISTS (SELECT null FROM `parcel_barcode_article_barcode` `pbab`
//                        WHERE `pbab`.`barcode_id` = `b`.`id`)
//                GROUP BY `barcode`";
//
//        $articles_from_ramps = $this->_dbr->getAssoc("SELECT
//                `op_article`.`article_id`
//                , SUM(`op_article`.`qnt_delivered`)
//                    - IFNULL((
//                        SELECT SUM(`pba`.`quantity`) FROM `parcel_barcode_article` `pba`
//                            WHERE `pba`.`article_id` = `op_article`.`article_id` AND `pba`.`ramp_id` = `op_article`.`ware_la_id`
//                                AND NOT EXISTS (SELECT null FROM `parcel_barcode_article` `pba1`
//                                    WHERE `pba1`.`id` = `pba`.`id`)
//                    ), 0)
//                    - IFNULL((
//                        SELECT SUM(`pbad`.`quantity`) FROM `parcel_barcode_article_deduct` `pbad`
//                            WHERE `pbad`.`article_id` = `op_article`.`article_id` AND `pbad`.`ramp_id` = `op_article`.`ware_la_id`
//                                AND NOT EXISTS (SELECT null FROM `parcel_barcode_article_deduct` `pbad1`
//                                    WHERE `pbad1`.`id` = `pbad`.`id`)
//                    ), 0) AS `quantity`
//
//            FROM `op_article`
//            JOIN `article` ON `article`.`article_id` = `op_article`.`article_id`
//            WHERE `op_article`.`ware_la_id` = '{$ramp_id}'
//                AND `article`.`admin_id` = 0
//                AND `article`.`barcode_type` = 'C'
//
//            GROUP BY `op_article`.`article_id`
//            HAVING `quantity`");
//
//        foreach ($articles_from_ramps as $article_id => $quantity) {
//            $art = new \Article($this->_db, $this->_dbr, $article_id, -1, 0);
//            $total = $art->getPieces($warehouse_id);
//            $total_pieces = $art->getNotOnFloor($warehouse_id);
//
//            $quantity = min(($total - $total_pieces), $quantity);
//
//            if ($quantity > 0) {
//                $query[] = "SELECT `a`.`article_id`
//                    , '' AS `barcode_id`
//                    , $quantity AS `quantity`
//                    , 'C' AS `type`
//                    , '0' AS `picking_order_id`
//                    , '' state2filter
//                    , '1' reserved
//                FROM `op_article` AS `opa`
//                JOIN `article` AS `a` ON `opa`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'C' AND `a`.`admin_id` = 0
//                WHERE `opa`.`ware_la_id` = '{$ramp_id}'
//                    AND `opa`.`article_id` = '$article_id'
//                GROUP BY `article_id`";
//            }
//        }

        $query = implode("\n UNION ALL \n", $query);

        $articles = $this->_dbr->getAll($query);

        $A_articles = $this->getAArticles($articles, $statesIN);
        $C_articles = $this->getCArticles($articles);

        $count = count($A_articles);
        foreach ($C_articles as $_article) {
            $count += $_article->quantity;
        }

        return $count;
    }

    /**
     * Delte loading area/ramp
     */
    public function delete() {
        if ($this->_id) {
            $this->_db->query("DELETE FROM `ware_la` WHERE `id` = {$this->_id}");
        }
    }

    /******************************************************************************************************************/

    /**
     *
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param type $groups
     * @param type $ramp
     * @return array
     */
    static function Release(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $groups, $articles, $ramp) {
        $picking_ids = [];
        $ramp = mysql_real_escape_string($ramp);

        $warehouse_id = (int)$dbr->getOne("SELECT `warehouse_id` FROM `ware_la` WHERE `id` = $ramp");
        $warehouse_name = $dbr->getOne("SELECT CONCAT(`country_code`, ': ', `name`)
            FROM `warehouse` WHERE `warehouse_id` = $warehouse_id");

        $output = [];
        $released = [];

        foreach ($groups as $value) {
            list($auction_number, $txnid) = explode('_', $value);

            $articles_for_group = isset($articles[$value]) ? $articles[$value] : [];

            $auction_number = mysql_real_escape_string($auction_number);
            $txnid = mysql_real_escape_string($txnid);

            $query = "SELECT `id`, `article_id`, `reserve_warehouse_id` FROM `orders` WHERE
                        `auction_number` = '$auction_number'
                            AND `txnid` = '$txnid'
                            AND `manual` = '0'
                            AND IFNULL(`picking_order_id`, 0) = '0'
                            AND `sent` = 0
                            #AND `hidden` = 0
                UNION ALL
                    SELECT `orders`.`id`, `orders`.`article_id`, `orders`.`reserve_warehouse_id`
                    FROM `orders`
                    JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number`
                        AND `auction`.`txnid` = `orders`.`txnid`

                    WHERE `auction`.`deleted` = 0 
                        #AND `orders`.`hidden` = 0
                        AND $auction_number > 0
                        AND `auction`.`main_auction_number` = '$auction_number'
                        AND `auction`.`main_txnid` = $txnid
                        AND `orders`.`manual` = '0'
                        AND IFNULL(`orders`.`picking_order_id`, 0) = '0'
                        AND `orders`.`sent` = 0";

            foreach ($dbr->getAll($query) as $_order) {
                $order_id = (int)$_order->id;
                $article_id = (int)$_order->article_id;

                if ( !in_array($article_id, $articles_for_group)) {
                    continue;
                }

                if ($_order->reserve_warehouse_id != $warehouse_id) {
                    $output[] = "<span style='color:red'>Auftrag
                            <a href='/shipping_auction.php?number=$auction_number&txnid=$txnid'>$auction_number/$txnid</a>
                            article <a href='/article.php?original_article_id=$article_id'>$article_id</a> cannot be released because not reserved in <b>$warehouse_name</b></span>";
                    continue;
                }

                $on_stock = (int)$dbr->getOne("select fget_Article_stock($article_id, $warehouse_id)");
                if ($on_stock < 1) {
                    $output[] = "<span style='color:red'>Auftrag
                            <a href='/shipping_auction.php?number=$auction_number&txnid=$txnid'>$auction_number/$txnid</a>
                            article <a href='/article.php?original_article_id=$article_id'>$article_id</a> cannot be released because not on stock</span>";
                    continue;
                }

                if ($dbr->getOne("SELECT `cannot_release_on_ramp` FROM `article` WHERE `article_id` = '" . $article_id . "' AND `admin_id` = 0"))
                {
                    $output[] = "<span style='color:red'>
                            <a href='/shipping_auction.php?number=$auction_number&txnid=$txnid'>$auction_number/$txnid</a>
                            article <a href='/article.php?original_article_id=$article_id'>$article_id</a> cannot be released</span>";
                    continue;
                }

                if ( ! isset($picking_ids[$article_id])) {
                    $username = "select IFNULL(`main_auction`.`shipping_username`, `auction`.`shipping_username`)
                            from `orders`
                            JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND
                                `auction`.`txnid` = `orders`.`txnid`
                            LEFT JOIN `auction` AS `main_auction` ON `auction`.`main_auction_number` = `main_auction`.`auction_number`
                                AND `auction`.`txnid` = `main_auction`.`txnid`
                            WHERE `orders`.`id` = '$order_id'";

                    $username = $dbr->getOne($username);
                    $username = mysql_real_escape_string($username);

                    $picking_order_id = (int)$dbr->getOne("SELECT `id` FROM `picking_order`
                                WHERE `article_id` = '$article_id'
                                    AND `ware_la_id` = '$ramp'
                                    AND `shipping_username` = '$username'
                                    AND `delivered` = 0");

                    if ($picking_order_id) {
                        $picking_ids[$article_id] = $picking_order_id;
                        $db->query("UPDATE `orders` SET `picking_order_id` = '$picking_order_id' WHERE `id` = '$order_id'");
                    }
                    else {
                        $insert = "INSERT INTO `picking_order` (`article_id`, `ware_la_id`, `shipping_username`) VALUES ('$article_id', '$ramp', '$username')";
                        if ($db->query($insert)) {
                            $picking_order_id = (int)mysql_insert_id();
                            if ($picking_order_id) {
                                $picking_ids[$article_id] = $picking_order_id;
                                $db->query("UPDATE `orders` SET `picking_order_id` = '$picking_order_id' WHERE `id` = '$order_id'");
                            }
                        }
                    }
                }
                else {
                    $db->query("UPDATE `orders` SET `picking_order_id` = '{$picking_ids[$article_id]}' WHERE `id` = '$order_id'");
                }

                $output[] = "<span style='color:green'>Auftrag
                        <a href='/shipping_auction.php?number=$auction_number&txnid=$txnid'>$auction_number/$txnid</a>
                        article <a href='/article.php?original_article_id=$article_id'>$article_id</a> successfully released</span>";

                $released["{$auction_number}_{$txnid}"] = $dbr->getOne("SELECT
                    CONCAT('Released on ', DATE_FORMAT(`tl`.`Updated`, '%Y-%m-%d %H:%i'),
                    ' by ', IFNULL(`u`.`name`, `tl`.`username`),
                    ' on ', `la`.`la_name`)
                FROM `total_log` AS `tl`
                LEFT JOIN users u ON u.system_username=tl.username
                LEFT JOIN `orders` AS `o` ON `o`.`id` = `tl`.`TableID`
                LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `tl`.`New_value`
                LEFT JOIN `ware_la` AS `la` ON `la`.`id` = `po`.`ware_la_id`
                WHERE `tl`.`Table_name` = 'orders' and `tl`.`Field_name` = 'picking_order_id' AND `tl`.`New_value` > 0 AND
                    `o`.`id` = $order_id
                ORDER BY `tl`.`id` DESC LIMIT 1");
            }
        }

        global $loggedUser;
        $message = json_encode([
            'user' => $loggedUser->get('username'),
            'script' => __FILE__,
            'line' => __LINE__,
            'time' => time(),
            'date' => date('Y-m-d H:i:s'),
            'message' => 'reload',
            'action' => 'release',
        ]);

        $notification = \label\RedisProvider::getInstance(\label\RedisProvider::USAGE_NOTIFICATION);
        $notification->publish('prolo-channel', $message);

        return [
            'output' => $output,
            'released' => $released,
        ];
    }

    /**
     *
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param type $article_id
     * @param type $order
     * @return boolean
     */
    static function unRelease(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $article_id, $auction_number, $txnid) {
        $auction_number = mysql_real_escape_string($auction_number);
        $txnid = mysql_real_escape_string($txnid);

        $query = "SELECT `orders`.`id`, `orders`.`article_id`, `orders`.`picking_order_id`
                    , `picking_order`.`delivered`, `picking_order`.`ware_la_id`
                FROM `orders`
                JOIN `picking_order` ON `picking_order`.`id` = `orders`.`picking_order_id`
                WHERE
                        `orders`.`auction_number` = '$auction_number'
                        AND `orders`.`txnid` = '$txnid'
                        AND `orders`.`manual` = '0'
                        AND `orders`.`picking_order_id` != '0'
                        AND `orders`.`sent` = 0
                        #AND `hidden` = 0

                UNION ALL

                SELECT `orders`.`id`, `orders`.`article_id`, `orders`.`picking_order_id`
                    , `picking_order`.`delivered`, `picking_order`.`ware_la_id`
                FROM `orders`
                JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number`
                    AND `auction`.`txnid` = `orders`.`txnid`

                JOIN `picking_order` ON `picking_order`.`id` = `orders`.`picking_order_id`

                WHERE `auction`.`deleted` = 0 
                    #AND `orders`.`hidden` = 0 
                    AND $auction_number > 0
                    AND `auction`.`main_auction_number` = '$auction_number'
                    AND `auction`.`main_txnid` = '$txnid'
                    AND `orders`.`manual` = '0'
                    AND `orders`.`picking_order_id` != '0'
                    AND `orders`.`sent` = 0";

        $unrelease = false;
        foreach ($dbr->getAll($query) as $_order) {
            $order_id = (int)$_order->id;
            $picking_order_id = (int)$_order->picking_order_id;

//            if ($_order->delivered) {
//                continue;
//            }

            if ($article_id == $_order->article_id) {
                $unrelease = true;

                $db->query("UPDATE `orders` SET `picking_order_id` = NULL WHERE `id` = '$order_id'");
                $other_orders = (int)$dbr->getOne("SELECT COUNT(*) FROM `orders` WHERE `picking_order_id` = $picking_order_id");

                if ( ! $other_orders) {
                    $res = $db->query("DELETE FROM `picking_order` WHERE `id` = $picking_order_id");
                }

                $articles = $dbr->getAll("SELECT * FROM `parcel_barcode_article_barcode_deduct`
                        WHERE `picking_order_id` = '$picking_order_id'");
                foreach ($articles as $article) {
                    //$db->query("DELETE FROM `parcel_barcode_article_barcode_deduct` WHERE id = {$article->id}");

                    $db->query("UPDATE `parcel_barcode_article_barcode_deduct`
                        SET `picking_order_id` = '0',
                            `delivered` = '0',
                            `ware_la_id` = '" . (int)$_order->ware_la_id . "'
                        WHERE  `id` = '" . (int)$article->id . "'");

                    if ($dbr->getOne("SELECT `id` FROM `parcel_barcode_article_barcode`
                            WHERE `parcel_barcode_id` = '" . (int)$article->parcel_barcode_id . "'
                                AND `barcode_id` = '" . (int)$article->barcode_id . "'"))
                    {
                        $db->query("UPDATE `parcel_barcode_article_barcode`
                            SET `deleted` = '" . (int)$article->id . "',
                            WHERE `parcel_barcode_id` = '" . (int)$article->parcel_barcode_id . "'
                                AND `barcode_id` = '" . (int)$article->barcode_id . "'");
                    }
                    else
                    {
                        $db->query("INSERT INTO `parcel_barcode_article_barcode` (`parcel_barcode_id`, `barcode_id`, `deleted`)
                            VALUES ('" . (int)$article->parcel_barcode_id . "', '" . (int)$article->barcode_id . "', '" . (int)$article->id . "')");
                    }

//                        $db->query("DELETE FROM `parcel_barcode_article_barcode` WHERE `parcel_barcode_id` = '{$article->parcel_barcode_id}' AND `barcode_id` = '{$article->barcode_id}'");
//                        $db->query("INSERT INTO `parcel_barcode_article_barcode` (`parcel_barcode_id`, `barcode_id`) VALUES
//                            ('{$article->parcel_barcode_id}', '{$article->barcode_id}')");
                }

                $articles = $dbr->getAll("SELECT `parcel_barcode_id`, `article_id`, -SUM(`quantity`) AS `quantity`
                        FROM `parcel_barcode_article_deduct`
                        WHERE `picking_order_id` = '$picking_order_id'
                            AND `delivered` = '0'
                        GROUP BY `parcel_barcode_id`, `article_id`");
                foreach ($articles as $article) {
//                        $db->query("INSERT INTO `parcel_barcode_article` (`parcel_barcode_id`, `article_id`, `quantity`) VALUES
//                            ('{$article->parcel_barcode_id}', '{$article->article_id}', '{$article->quantity}')");
                        $db->query("INSERT INTO `parcel_barcode_article` (`parcel_barcode_id`, `article_id`, `quantity`, `ware_la_id`) VALUES
                            ('0', '{$article->article_id}', '{$article->quantity}', '" . (int)$_order->ware_la_id . "')");

                    $db->query("INSERT INTO `parcel_barcode_article_deduct`
                        (`picking_order_id`, `wwo_id`, `auction_id`, `parcel_barcode_id`, `article_id`, `quantity`) VALUES
                        ('$picking_order_id', 0, 0, '{$article->parcel_barcode_id}', '{$article->article_id}', '{$article->quantity}')");
                }
            }
        }

        global $loggedUser;
        $message = json_encode([
            'user' => $loggedUser->get('username'),
            'script' => __FILE__,
            'line' => __LINE__,
            'time' => time(),
            'date' => date('Y-m-d H:i:s'),
            'message' => 'reload',
            'action' => 'unrelease',
        ]);

        $notification = \label\RedisProvider::getInstance(\label\RedisProvider::USAGE_NOTIFICATION);
        $notification->publish('prolo-channel', $message);

        return $unrelease;
    }

    /**
     *
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param type $warehouse
     * @param type $hall_id
     * @return type
     */
    static function listAll(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $warehouse = 0, $hall_id = 0) {
        global $GLOBALS;

     /* Table for barcode warehouse, if use denormalization - barcode_dn */
        $vbw = 'vbarcode_warehouse';
        if (\Config::get(null, null, 'use_dn')) {
            $vbw = 'barcode_dn';
        }

        $warehouse = (int)$warehouse;
        $hall_id = (int)$hall_id;
        
        $where = array(' 1 ');
        if ($warehouse) {
            $where[] = " `las`.`warehouse_id` = $warehouse ";
            if ($hall_id) {
                $where[] = " `las`.`hall_id` = $hall_id ";
            }
        }

        $where = implode(' AND ', $where);

        $query = "SELECT `las`.*
            , CONCAT(`w`.`country_code`, ': ', `w`.`name`) AS `warehouse`
            , IF (`las`.`def`, '', CONCAT(`w`.`ware_char`, `las`.`hall_id`, '-L-')) AS `barcode`
            FROM `ware_la` AS `las`
            JOIN `warehouse` AS `w` ON `w`.`warehouse_id` = `las`.`warehouse_id`
            WHERE $where
            GROUP BY `las`.`id`
            ORDER BY `warehouse` ASC, `las`.`def` DESC, `las`.`id` ASC";
        $result = $dbr->getAll($query);

//        $articles_from_ramps = $dbr->getAll("SELECT
//                    SUM(`op_article`.`qnt_delivered`)
//                        - IFNULL((
//                            SELECT SUM(`pba`.`quantity`) FROM `parcel_barcode_article` `pba`
//                                WHERE `pba`.`article_id` = `op_article`.`article_id` AND `pba`.`ramp_id` = `op_article`.`ware_la_id`
//                        ), 0)
//                        - IFNULL((
//                            SELECT SUM(`pbad`.`quantity`) FROM `parcel_barcode_article_deduct` `pbad`
//                                WHERE `pbad`.`article_id` = `op_article`.`article_id` AND `pbad`.`ramp_id` = `op_article`.`ware_la_id`
//                        ), 0) AS `quantity`
//                    , `op_article`.`warehouse_id`
//                    , `op_article`.`ware_la_id`
//                    , `op_article`.`article_id`
//                FROM `op_article`
//                JOIN `article` AS `a` ON `op_article`.`article_id` = `a`.`article_id` AND `a`.`barcode_type` = 'C' AND `a`.`admin_id` = 0
//
//                WHERE IFNULL(`op_article`.`ware_la_id`, 0) != 0
//
//                GROUP BY `op_article`.`article_id`
//                HAVING `quantity`");

        $warehouses = [];
        $lr_barcode_counter = [];
        foreach ($result as $key => $item) {
            $list[$item->id] = $item;
            if ($item->def == '1') {
                $warehouses[] = (int)$item->warehouse_id;
            }
            else {
                $list[$item->id]->parcels2 = 0;

                $la = new \LoadingArea($db, $dbr, $item->id);
                $list[$item->id]->parcels = $la->get_count_articles_from_ramp($item->id, $item->warehouse_id);

                if ( ! isset($lr_barcode_counter[$item->warehouse_id])) {
                    $lr_barcode_counter[$item->warehouse_id] = 1;
                }
                $list[$item->id]->barcode .= $lr_barcode_counter[$item->warehouse_id];
                $lr_barcode_counter[$item->warehouse_id] += 1;

//                foreach ($articles_from_ramps as $_article) {
//                    if ($_article->warehouse_id == $item->warehouse_id && $_article->ware_la_id == $item->id) {
//                        $art = new \Article($db, $dbr, $_article->article_id, -1, 0);
//                        $total = $art->getPieces($item->warehouse_id);
//                        $total_pieces = $art->getNotOnFloor($item->warehouse_id);
//
//                        $list[$item->id]->parcels += min(($total - $total_pieces), $_article->quantity);
//                    }
//                }
            }
        }

        $query = "SELECT `opa`.`ware_la_id`
                , COUNT(DISTINCT `vb`.`id`) count
                , COUNT(*) barcodes
                , IFNULL(SUM(pba.quantity), 0) articles
            FROM `vbarcode` AS `b`
            JOIN `vparcel_barcode` `vb` ON `vb`.`id` = `b`.`parcel_barcode_id`
            JOIN `op_article` AS `opa` ON `opa`.`id` = `b`.`opa_id`
            LEFT JOIN `parcel_barcode_article` `pba` ON `pba`.`parcel_barcode_id` = `vb`.`id`
            WHERE `b`.`inactive` = '0' AND IFNULL(`opa`.`ware_la_id`, 0) != 0
                AND `b`.`ware_loc` IS NULL
            GROUP BY `opa`.`ware_la_id`
            HAVING `barcodes` > 0 or `articles` > 0";

        $result = $dbr->getAll($query);
        foreach ($result as $item) {
            $list[$item->ware_la_id]->parcels2 += $item->count;
        }

//        $query = "SELECT b.id, `pba`.`ware_la_id`, COUNT(*) AS `count`, SUM(pba.quantity) AS `quantity`
//            FROM `vparcel_barcode` `b`
//
//            JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `b`.`id`
//
//            WHERE `b`.`inactive` = '0'
//                 AND NOT IFNULL(b.ware_loc_id, 0)
//                 AND `pba`.`ramp_id` != 0
//
//            GROUP BY `b`.`id`
//            HAVING `quantity`";
//
//        $result = $dbr->getAll($query);
//        foreach ($result as $item) {
//            $list[$item->ware_la_id]->parcels2 += $item->count;
//        }

        $articles = [];
        $parcels = [];
        if ($warehouses) {
            $warehouses = implode(',', $warehouses);

            $query = "SELECT `bw`.`last_warehouse_id` AS `warehouse_id`, COUNT(*)
                FROM `vbarcode` AS `b`
                JOIN `{$vbw}` AS `bw` ON `b`.`id` = `bw`.`id`
                LEFT JOIN `parcel_barcode_article_barcode_deduct` AS `pbabd` ON `pbabd`.`barcode_id` = `b`.`id`
                LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
                WHERE IFNULL(`b`.`ware_loc`, '') = ''
                    AND ISNULL(`b`.`parcel_barcode`)
                    AND `bs`.`type` = 'in'
                    AND `b`.`inactive` = 0
                    AND `bw`.`last_warehouse_id` IN ($warehouses)
                    AND IFNULL(`pbabd`.`picking_order_id`, '0') = 0
                GROUP BY `bw`.`last_warehouse_id`";
            $articles = $dbr->getAssoc($query);

            $query = "SELECT `warehouse_id`, COUNT(`article_id`) FROM (
                    SELECT `b`.`warehouse_id`, `pba`.`article_id`
                        , SUM(`pba`.`quantity`) AS `quantity`
                    FROM `vparcel_barcode` AS `b`
                    JOIN `parcel_barcode_article` AS `pba` ON `pba`.`parcel_barcode_id` = `b`.`id`
                    WHERE
                        `b`.`warehouse_id` IN ($warehouses) AND NOT IFNULL(`b`.`ware_loc_id`, 0) AND `b`.`inactive` = 0
                    GROUP BY `b`.`warehouse_id`, `pba`.`article_id`
                    HAVING `quantity`
                ) AS `t`
                GROUP BY `warehouse_id`";
            $parcels = $dbr->getAssoc($query);

            $query = "SELECT
                    t.warehouse_id,
                    COUNT(t.id)
                FROM (
                        SELECT
                            b.warehouse_id,
                            b.id,
                            COUNT(b1.id) barcode_count,
                            SUM(pba.quantity) article_count
                        FROM vparcel_barcode b
                            LEFT JOIN vbarcode b1 ON b1.parcel_barcode_id = b.id
                            LEFT JOIN parcel_barcode_article pba ON pba.parcel_barcode_id = b.id
                        WHERE
                            b.warehouse_id IN ($warehouses)
                            and not IFNULL(b.ware_loc_id, 0)
                            and b.inactive = 0
                        GROUP BY b.id
                        HAVING article_count > 0 or barcode_count > 0
                    ) t
                GROUP BY t.warehouse_id";
            $parcels2 = $dbr->getAssoc($query);
        }

        foreach ($list as &$item) {
            if ($item->def == '1') {
                $item->parcels = 0;
                $item->parcels2 = 0;
                if (isset($articles[$item->warehouse_id])) {
                    $item->parcels += $articles[$item->warehouse_id];
                }
                if (isset($parcels[$item->warehouse_id])) {
                    $item->parcels += $parcels[$item->warehouse_id];
                }

                $item->parcels2 += $parcels2[$item->warehouse_id];
            }
        }

        return $list;
    }

    /**
     *
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param type $warehouse
     * @param type $hall_id
     * @return type
     */
    static function listRamps(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $warehouse = 0, $hall_id = 0) {
        $warehouse = (int)$warehouse;
        $hall_id = (int)$hall_id;

        $where = array(' 1 ');
        if ($warehouse) {
            $where[] = " `las`.`warehouse_id` = $warehouse ";
            if ($hall_id) {
                $where[] = " `las`.`hall_id` = $hall_id ";
            }
        }

        $where = implode(' AND ', $where);

        $query = "SELECT `las`.*
            , CONCAT(`w`.`country_code`, ': ', `w`.`name`) AS `warehouse`
            , IF (`las`.`def`, '', CONCAT(`w`.`ware_char`, `las`.`hall_id`, '-L-')) AS `barcode`
            FROM `ware_la` AS `las`
            JOIN `warehouse` AS `w` ON `w`.`warehouse_id` = `las`.`warehouse_id`
            WHERE $where
            GROUP BY `las`.`id`
            ORDER BY `warehouse` ASC, `las`.`def` DESC, `las`.`id` ASC";
        $list = $dbr->getAll($query);

        $lr_barcode_counter = [];
        foreach ($list as $key => $item) {
            if ( ! $item->def) {
                if ( ! isset($lr_barcode_counter[$item->warehouse_id])) {
                    $lr_barcode_counter[$item->warehouse_id] = 1;
                }
                $list[$key]->barcode .= $lr_barcode_counter[$item->warehouse_id];
                $lr_barcode_counter[$item->warehouse_id] += 1;
            }
        }

        return $list;
    }

    /**
     *
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param type $warehouse
     * @param type $title_id
     * @return type
     */
    static function listHalls(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $warehouse = 0, $title_id = 0) {
        $warehouse = (int)$warehouse;
        $title_id = (int)$hall_id;

        $where[] = " `wl`.`inactive` = 0 ";
        if ($warehouse) {
            $where[] = " `wl`.`warehouse_id` = $warehouse ";
            if ($title_id) {
                $where[] = " `wl`.`title_id` = $title_id ";
            }
        }
        else {
            $where[] = " `wl`.`warehouse_id` > 0 ";
        }

        $where = implode(' AND ', $where);

        $query = "SELECT `wl`.`warehouse_id`, `wl`.`hall`, `wh`.`title`
            FROM `ware_loc` AS `wl`
            LEFT JOIN `warehouse_halls` AS `wh` ON `wh`.`warehouse_id` = `wl`.`warehouse_id` AND `wh`.`title_id` = `wl`.`hall`
            WHERE $where
            GROUP BY `wl`.`warehouse_id`, `wl`.`hall`";

        return $dbr->getAll($query);
    }

}
