<?php

/**
 * 
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';

/**
 * 
 * @package eBay_After_Sale
 */
class Rma {

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
    function Rma(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $auction, $id = 0) {
        $this->_db = $db;
        $this->_dbr = $dbr;
        $id = (int) $id;
        $this->auction_number = $auction->data->auction_number;
        $this->txnid = $auction->data->txnid;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN rma");
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
            $this->_isNew = true;
            $this->data->rma_id = '';
            $this->data->auction_number = $this->auction_number;
            $this->data->txnid = $this->txnid;
        } else {
            $r = $this->_db->query("SELECT * FROM rma WHERE rma_id=$id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Rma::Rma : record $id does not exist");
                return;
            }
            $this->articles = \Rma_Spec::listAll($db, $dbr, $id);
            $this->docs = \Rma_Spec::getPics($db, $dbr, $id, 0, '');
            $this->comments = Rma::getComments($db, $dbr, $id);
            $this->sh_refunds = Rma::getSHRefunds($db, $dbr, $id);
            $this->_isNew = false;
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
        } else
            $this->data->$field = $value;
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
     * @return bool|object
     * @desc Update record
     */
    function update() {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Rma::update : no data');
        }
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            if ((($value != '' || $value == '0') && $value != NULL) || $field == 'txnid')
                $query .= "`$field`='" . mysql_escape_string($value) . "'";
            else
                $query .= "`$field`= NULL";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE rma_id='" . mysql_escape_string($this->data->rma_id) . "'";
        }
        $r = $this->_db->query("$command rma SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->rma_id = mysql_insert_id();
            $email = $this->_dbr->getOne("select group_concat(users.email) from auction 
                join rma_notif on rma_notif.auction_id=auction.id
                join users on users.username=rma_notif.username
                where auction.auction_number=" . $this->data->auction_number
                    . " and auction.txnid=" . $this->data->txnid);
            if (strlen($email) && !$this->_dbr->getOne("select count(*) from email_log where template='ticket_notification'
                 and auction_number=" . $this->data->auction_number . " and txnid=" . $this->data->txnid)) {
                $this->data->email_invoice = $email;
                standardEmail($this->_db, $this->_dbr, $this->data, 'ticket_notification');
            }
            
            $picking_order_id = $this->_dbr->getAll("
                SELECT `orders`.`picking_order_id`, `orders`.`id` 
                FROM `orders`
                WHERE `orders`.`auction_number` = '" . $this->data->auction_number . "' 
                    AND `orders`.`txnid` = '" . $this->data->txnid . "'
                    AND `orders`.`manual` = 0

                UNION ALL 

                SELECT `orders`.`picking_order_id`, `orders`.`id` 
                FROM `orders`
                JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number`
                WHERE `auction`.`main_auction_number` = '" . $this->data->auction_number . "' 
                    AND `auction`.`txnid` = '" . $this->data->txnid . "'
                    AND `orders`.`manual` = 0
            ");
            
            foreach ($picking_order_id as $_po_id)
            {
                $this->_db->query("DELETE FROM `parcel_barcode_article_barcode_deduct` WHERE `picking_order_id` = '" . (int)$_po_id->picking_order_id . "'");
                $this->_db->query("DELETE FROM `parcel_barcode_article_deduct` WHERE `picking_order_id` = '" . (int)$_po_id->picking_order_id . "'");
                
                $this->_db->query("UPDATE `orders` SET `picking_order_id` = NULL WHERE `id` = '" . (int)$_po_id->id . "'");
            }
        }
        return $r;
    }

    /**
     * @return void
     * @param object $db
     * @param object $group
     * @desc Delete group in an offer
     */
    function delete() {
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Rma::delete : no data');
            return 0;
        }
        $rma_id = (int) $this->data->rma_id;
        $inscnt = (int) $this->_dbr->getOne("select count(*) FROM insurance WHERE rma_id=$rma_id");
        if ($inscnt) {
            $this->_error = PEAR::raiseError('This Ticket is used by Insurance case. Please delete it first');
            return 0;
        }
        $rma_id = (int) $this->data->rma_id;
        foreach ($this->articles as $article) {
            $rma_spec = new \Rma_Spec($this->_db, $this->_dbr, $rma_id, $article->rma_spec_id);
            $rma_spec->delete();
        };
        $this->_db->query("DELETE FROM rma_pic WHERE rma_id=$rma_id");
        $r = $this->_db->query("DELETE FROM rma WHERE rma_id=$rma_id");
        if (PEAR::isError($r)) {
            $msg = $r->getMessage();
            adminEmail($msg);
            $this->_error = $r;
        }
        return 1;
    }

    /**
     * @return array
     * @param object $db
     * @param object $offer
     * @desc Get all groups in an offer
     */
    static function listAll(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $auction) {
        $auction_number = $auction->data->auction_number;
        $txnid = $auction->data->txnid;
        $q = "SELECT rma.*, users.name as responsible_name 
               FROM rma LEFT JOIN users ON rma.responsible_uname=users.username 
               WHERE auction_number=$auction_number and txnid=$txnid order by CRM_Ticket";
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($rma = $r->fetchRow()) {
            $rma->articles = \Rma_Spec::listAll($db, $dbr, $rma->rma_id);
            $list[] = $rma;
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
    static function listArray(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $auction) {
        $ret = array();
        $list = Rma::listAll($db, $dbr, $auction);
        foreach ((array) $list as $rma) {
            $ret[$rma->rma_id] = $rma->CRM_Ticket;
        }
        return $ret;
    }

    /**
     * @return bool
     * @param array $errors
     * @desc Validate record
     */
    function validate(&$errors) {
        $errors = array();
        if (empty($this->data->title)) {
            $errors[] = 'Title is required';
        }
        return 1;
    }

    static function find(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $criteria, $rep = '') {
        global $seller_filter_str;
        global $supplier_filter;
        global $supplier_filter_str;
        if (strlen($supplier_filter))
            $supplier_filter_str1 = " and article.company_id in ($supplier_filter) ";
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $where = array();
        if ($criteria['seller_channel_id']) {
            $where[] = "si.seller_channel_id = " . (int) $criteria['seller_channel_id'];
        }
        if ($criteria['aname']) {
            $where[] = " ta.value like '%" . $criteria['aname'] . "%'";
        }
        if ($criteria['minart']) {
            $where[] = "rma_spec.article_id >='" . mysql_escape_string($criteria['minart']) . "'";
        }
        if ($criteria['maxart']) {
            $where[] = "rma_spec.article_id <='" . mysql_escape_string($criteria['maxart']) . "'";
        }
        if ($criteria['mindate']) {
            $where[] = "rma.create_date >='" . mysql_escape_string($criteria['mindate']) . "'";
        }
        if ($criteria['maxdate']) {
            $where[] = "rma.create_date <='" . mysql_escape_string($criteria['maxdate']) . "'";
        }
        if ($criteria['category']) {
            $where[] = "article.category_id = " . $criteria['category'];
        }
        if ($criteria['supplier']) {
            if ($rep == 'cons') {
                $where[] = "article_cons.company_id = " . $criteria['supplier'];
            } else {
                $where[] = "article.company_id = " . $criteria['supplier'];
            }
        }

        $company_ids = '';
        if ($criteria['emp_id']) {
            $company_ids .= $dbr->getOne("
                SELECT group_concat(company_id) id FROM op_company_emp
                WHERE type='purch' AND emp_id=" . $criteria['emp_id']);
        }
        if ($criteria['emp_assist_id']) {
            $company_ids .= $dbr->getOne("
        SELECT group_concat(company_id) id FROM op_company_emp
        WHERE type='assist' AND emp_id=" . $criteria['emp_assist_id']);
        }
        if (strlen($company_ids)) {
            $where[] = " article.company_id IN ({$company_ids}) ";
        }

        if ($criteria['problem']) {
            if (is_array($criteria['problem']))
                $where[] = "rma_spec.problem_id in (" . implode(',', $criteria['problem']) . ')';
            else
                $where[] = "rma_spec.problem_id = " . $criteria['problem'];
        }
        if ($criteria['shipping_username']) {
            $where[] = "auction.shipping_username = '" . $criteria['shipping_username'] . "'";
        }
        if ($criteria['seller_username']) {
            $where[] = "auction.username = '" . $criteria['seller_username'] . "'";
        }
        if ($criteria['source_seller_id']) {
            $where[] = "auction.source_seller_id = '" . $criteria['source_seller_id'] . "'";
        }
        if ($criteria['country_shipping']) {
            $where[] = "apv_country_shipping.value = '" . countryCodeToCountry($criteria['country_shipping']) . "'";
        }
        if ($criteria['defshcountry']) {
            $where[] = "si.defshcountry = '" . $criteria['defshcountry'] . "'";
        }
        if (strlen($criteria['method'])) {
            $where[] = "exists (select null from tracking_numbers tn 
                where auction.auction_number=tn.auction_number AND auction.txnid=tn.txnid
                and tn.shipping_method = " . $criteria['method'] . ")";
        }

        if (strlen($criteria['offer_ids'])) {
            $where[] = " IFNULL(sau.offer_id, auction.offer_id) in (" . $criteria['offer_ids'] . ") ";
        }

        if (strlen($criteria['offer_names_by_cat'])) {
            $where[] = " of.name IN ({$criteria['offer_names_by_cat']}) ";
        } else if (strlen($criteria['articles_ids'])) {
            $where[] = " rma_spec.article_id in (" . $criteria['articles_ids'] . ") ";
        }

        if ($criteria['pics'] != '') {
            $where[] = ($criteria['pics'] == 1 ? " exists " : " not exists ")
                    . " (select null from rma_pic where rma_pic.rma_id=rma.rma_id 
                    and rma_pic.is_file=0 and rma_pic.hidden=0)";
        }
        if ($criteria['state'] != 2) {
            $where[] = ($criteria['state'] == 1 ? " rma_spec.exported = 1 " : " IFNULL(rma_spec.exported,0) = 0 ");
        }

        $orders_ids = [];
        if ($criteria['container_no']) 
        {
            $orders_ids = $dbr->getAssoc("SELECT `opc`.`order_id`, `opc`.`order_id` `v`
                FROM `op_order_container` `opc`
                LEFT JOIN `op_order_container` `master` ON `master`.`id`=`opc`.`master_id`
                WHERE IFNULL(`master`.`container_no`, `opc`.`container_no`) LIKE '" . mysql_real_escape_string($criteria['container_no']) . "' ");
            $orders_ids = array_map('intval', $orders_ids);
        }

        if ($criteria['order_id']) 
        {
            $orders_ids[] = (int)$criteria['order_id'];
        }

        $join = '';
        if ($orders_ids)
        {
            $join .= "
                JOIN `orders` ON IFNULL(`sau`.`auction_number`, `auction`.`auction_number`) = `orders`.`auction_number`
                    AND IFNULL(`sau`.`txnid`, `auction`.`txnid`) = `orders`.`txnid`
                    AND `orders`.`article_id` = `article`.`article_id`
                    AND `orders`.`manual` = 0

                JOIN `barcode_object` ON `barcode_object`.`obj` = 'orders'
                    AND `barcode_object`.`obj_id` = `orders`.`id`
                    
                JOIN `vbarcode` ON `barcode_object`.`barcode_id` = `vbarcode`.`id`
            ";
//            $join .= "JOIN `barcode_object` ON `barcode_object`.`obj` IN ('rma_spec','rma_spec_problem') 
//                    AND `barcode_object`.`obj_id` = `rma_spec`.`rma_spec_id`
//                JOIN `vbarcode` ON `barcode_object`.`barcode_id` = `vbarcode`.`id`\n";

            $where[] = "vbarcode.op_order_id IN ( " . implode(',', $orders_ids) . " )";
        }
        
        $source_article_name = "(SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = 'german'
                AND id = article.article_id)";
        $source_article_id = 'rma_spec.article_id';
        if ($rep == '') {
            
        } elseif ($rep == 'non-rep') {
            $where[] = ' not exists (select null from article_rep where rep_id=rma_spec.article_id) ';
        } elseif ($rep == 'rep') {
            $where[] = ' exists (select null from article_rep where rep_id=rma_spec.article_id) ';
        } elseif ($rep == 'cons') {
            $source_article_name = 'article_cons.name';
            $source_article_id = 'article_cons.article_id';
        }
        if (count($where)) {
            $where = 'WHERE ' . implode(' AND ', $where);
        } else {
            $where = '';
        }
        $q = "SELECT rma.*, IFNULL(rma_spec.exported,0) exported, IF(rma_spec.exported, 
            CONCAT('Yes, by ', rma_spec.exported_by_username, ' on ', IFNULL(rma_spec.export_date, '')), 'No') exported_text, 
            $source_article_id article_id
            , rma_spec.rma_spec_id, 
            auction.end_time as auction_date, 
            $source_article_name article_name
            , rma_problem.name as problem_name 
            , concat('|', group_concat(concat(rma_problem.name, '+', $source_article_id) separator '|'),'|') problem_article
            #, tn.username AS packed_by
        FROM rma
        JOIN auction ON auction.auction_number=rma.auction_number AND auction.txnid=rma.txnid
        LEFT JOIN auction sau ON sau.main_auction_number=auction.auction_number AND sau.main_txnid=auction.txnid
        left JOIN auction_par_varchar apv_country_shipping ON apv_country_shipping.key = 'country_shipping' 
            AND auction.txnid=apv_country_shipping.txnid 
            AND auction.auction_number=apv_country_shipping.auction_number
        join seller_information si on si.username=auction.username
        LEFT JOIN offer as of ON IFNULL(sau.offer_id, auction.offer_id)=of.offer_id 
        #LEFT JOIN tracking_numbers tn ON auction.auction_number=tn.auction_number AND auction.txnid=tn.txnid
        JOIN rma_spec ON rma.rma_id=rma_spec.rma_id
        JOIN article ON rma_spec.article_id=article.article_id and article.admin_id=0
        JOIN translation ta ON ta.table_name='article' and ta.field_name='name' and ta.id=article.article_id and ta.language='german'
        left join article_cons on article_cons.article_id=article.cons_id
        JOIN rma_problem ON rma_spec.problem_id=rma_problem.problem_id
        $join
        $where 
        $seller_filter_str
        $supplier_filter_str1
        group by rma_spec_id
        $group
        ORDER BY rma_spec.article_id
        ";
        $r = $dbr->getAll($q);

        if (PEAR::isError($r)) {
            print_r($r);
            die();
        }

        return $r;
    }

    static function findOffers(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $criteria, $rep = '') {
        global $seller_filter_str;

        $where = array();
        if ($criteria['seller_channel_id']) {
            $where[] = "si.seller_channel_id = " . (int) $criteria['seller_channel_id'];
        }
        if ($criteria['mindate']) {
            $where[] = "rma.create_date >='" . mysql_escape_string($criteria['mindate']) . "'";
        }
        if ($criteria['maxdate']) {
            $where[] = "rma.create_date <='" . mysql_escape_string($criteria['maxdate']) . "'";
        }
        if ($criteria['shipping_username']) {
            $where[] = "auction.shipping_username = '" . $criteria['shipping_username'] . "'";
        }
        if (strlen($criteria['siteid'])) {
            $where[] = "auction.siteid = " . $criteria['siteid'];
        }
        if (strlen($criteria['method'])) {
            $where[] = "exists (select null from tracking_numbers tn 
                where auction.auction_number=tn.auction_number AND auction.txnid=tn.txnid
                and tn.shipping_method = " . $criteria['method'] . ")";
        }
        if ($criteria['pics'] != '') {
            $where[] = ($criteria['pics'] == 1 ? " exists " : " not exists ")
                    . " (select null from rma_pic where rma_pic.rma_id=rma.rma_id 
                    and rma_pic.is_file=0 and rma_pic.hidden=0)";
        }
        if (is_array($criteria['problem_ids']) && count($criteria['problem_ids'])) {
            $where[] = "IFNULL(sell_channel,0)=0 and IFNULL(rma_spec.problem_id,0) in (" . implode(',', $criteria['problem_ids']) . ")";
        }
        if (strlen($criteria['ss'])) {
            $where[] = " auction.source_seller_id=" . $criteria['ss'];
            $where1 .= " and auction.source_seller_id=" . $criteria['ss'];
        }
        if (strlen($criteria['seller_username'])) {
            $where[] = " IFNULL(main_auction.username, auction.username) = '{$criteria['seller_username']}' ";
        }

        $company_ids = '';
        $articles_ids = [];
        if ($criteria['emp_id']) {
            $articles_ids[] = $dbr->getOne("SELECT group_concat(`article_id`) FROM `article` 
                WHERE `company_id` IN (SELECT company_id id FROM op_company_emp WHERE type='purch' AND emp_id={$criteria['emp_id']})");
        }
        if ($criteria['emp_assist_id']) {
            $articles_ids[] = $dbr->getOne("SELECT group_concat(`article_id`) FROM `article` 
                WHERE `company_id` IN (SELECT company_id id FROM op_company_emp WHERE type='assist' AND emp_id={$criteria['emp_assist_id']})");
        }

        if ($articles_ids) {
            $articles_ids = implode(',', $articles_ids);
            $where[] = " rma_spec.article_id IN ($articles_ids) ";
        }

        if ($criteria['offer_names_by_cat']) {
            $where[] = " of.name IN ({$criteria['offer_names_by_cat']}) ";
        } 
        else if ($criteria['articles_ids']) {
            $where[] = " rma_spec.article_id IN ({$criteria['articles_ids']}) ";
        }

        if ($rep == '') {
            
        } 
        else if ($rep == 'non-rep') {
            $where[] = ' not exists (select null from article_rep where rep_id=rma_spec.article_id) ';
        } 
        else if ($rep == 'rep') {
            $where[] = ' exists (select null from article_rep where rep_id=rma_spec.article_id) ';
        }

        if ($where) {
            $where = ' AND ' . implode(' AND ', $where);
        } 
        else {
            $where = '';
        }

        $q = "
            select max(t.offer_id) offer_id, offer.name offer_name
            , (select max(o1.offer_id) from offer o1 where o1.name=offer.name) max_offer_id
            , rma_id, auction_number, txnid
            
            , group_concat(offers_ids) as offers_ids
            
            ,SUM(last_30) last_30
            ,SUM(last_90) last_90
            ,SUM(last_180) last_180
            , COUNT(*) since_beginning
            , " . $dbr->getOne("select GROUP_CONCAT(CONCAT(\"SUM(IF(problems like '%,\",problem_id,\",%', 1, 0)) problem_\",problem_id))
                from rma_problem") . "
            , (select GROUP_CONCAT(c.number order by co.level separator '.' )
                from classifier_obj co
                join classifier c on co.classifier_id=c.id
                where obj='offer' and obj_id=max(t.offer_id)) classifier
            from (
            select 
            
                IFNULL(subau.offer_id, auction.offer_id) AS offer_id
                , rma.rma_id AS `rma_id`
                , auction.auction_number, auction.txnid
                , group_concat(distinct of.offer_id) as offers_ids
                , CONCAT(',', group_concat(rma_spec.problem_id), ',') problems
                            , IF(DATEDIFF(NOW(),rma.create_date)<=30,1,0) last_30
                            , IF(DATEDIFF(NOW(),rma.create_date)<=90,1,0) last_90
                            , IF(DATEDIFF(NOW(),rma.create_date)<=180,1,0) last_180
                FROM rma
                    JOIN auction ON auction.auction_number=rma.auction_number AND auction.txnid=rma.txnid
                    LEFT JOIN auction as subau ON subau.main_auction_number=auction.auction_number AND subau.txnid=auction.txnid
                    LEFT JOIN offer as of ON IFNULL(subau.offer_id, auction.offer_id)=of.offer_id 
                    join seller_information si on si.username=auction.username
                    left JOIN rma_spec ON rma.rma_id=rma_spec.rma_id
                    
                    WHERE 1 

                        $where 
                        $seller_filter_str
            group by auction.id
            ) t 
            JOIN offer ON offer.offer_id=t.offer_id
            group by offer.name
        ";
        $res = $dbr->getAll($q);
        foreach ($res as $k => $r) {
            $q = "select CONCAT(SUM(last_30),',',SUM(last_90),',',SUM(last_180),',',COUNT(*))
                from (
                SELECT offer.offer_id, offer.name offer_name
                , IF(DATEDIFF(NOW(),auction.end_time)<=30,1,0) last_30
                , IF(DATEDIFF(NOW(),auction.end_time)<=90,1,0) last_90
                , IF(DATEDIFF(NOW(),auction.end_time)<=180,1,0) last_180
                        FROM offer 
                        JOIN auction ON offer.offer_id=auction.offer_id
                        where offer.name='{$r->offer_name}'
                        $where1
                ) t        group by offer_name
                ";

            $res_all_row = $dbr->getOne($q);
            list($last_30_all, $last_90_all, $last_180_all, $since_beginning_all) = explode(',', $res_all_row);

            $res[$k]->last_30_all = $last_30_all;
            $res[$k]->last_90_all = $last_90_all;
            $res[$k]->last_180_all = $last_180_all;
            $res[$k]->since_beginning_all = $since_beginning_all;
            $res[$k]->last_30_perc = number_format($res[$k]->last_30 / $last_30_all * 100, 2);
            $res[$k]->last_90_perc = number_format($res[$k]->last_90 / $last_90_all * 100, 2);
            $res[$k]->last_180_perc = number_format($res[$k]->last_180 / $last_180_all * 100, 2);
            $res[$k]->since_beginning_perc = number_format($res[$k]->since_beginning / $since_beginning_all * 100, 2);
            $res[$k]->problem_1_perc = number_format($res[$k]->problem_1 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_3_perc = number_format($res[$k]->problem_3 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_5_perc = number_format($res[$k]->problem_5 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_6_perc = number_format($res[$k]->problem_6 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_7_perc = number_format($res[$k]->problem_7 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_8_perc = number_format($res[$k]->problem_8 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_9_perc = number_format($res[$k]->problem_9 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_11_perc = number_format($res[$k]->problem_11 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_12_perc = number_format($res[$k]->problem_12 / $res[$k]->since_beginning * 100, 2);
            $res[$k]->problem_13_perc = number_format($res[$k]->problem_13 / $res[$k]->since_beginning * 100, 2);
        }
        return $res;
    }

    static function findIds(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $ids) {
        $ids = mysql_escape_string($ids);
        return $dbr->getAll("SELECT auction.username_buyer, rma_problem.name as problem_name,
            rma.auction_number, rma.txnid, rma.responsible_uname rma_responsible_uname, rma.create_date, 
            rma_spec.*, article.supplier_article_id
            ,(SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = 'german'
                AND id = article.article_id)  as article_name
        FROM rma JOIN rma_spec ON rma.rma_id = rma_spec.rma_id
            JOIN article ON rma_spec.article_id=article.article_id and article.admin_id=0
            LEFT JOIN rma_problem ON rma_spec.problem_id=rma_problem.problem_id
            JOIN auction ON rma.auction_number=auction.auction_number and rma.txnid=auction.txnid
        WHERE rma_spec_id in ($ids)");
    }

    static function setExported(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $ids, $value, $username = '', $date = '0000-00-00') {
        $ids = mysql_escape_string($ids);
        $value = mysql_escape_string($value);
        $username = mysql_escape_string($username);
        $db->getAll("UPDATE rma_spec set exported = '$value', 
            exported_by_username = '$username',
            export_date = '$date' 
            WHERE rma_spec_id in ($ids)");
    }

    static function getComments(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id) {
        $r = $db->query("select t.*, u.deleted, IF(u.name is null, 1, 0) olduser, IFNULL(u.name, t.username) username, t.username cusername 
            , IFNULL(u.name, t.username) full_username
            , IFNULL(u.name, t.username) username_name
        from (
        SELECT null as prefix, rma_comment.id, rma_comment.create_date, rma_comment.username, rma_comment.comment, rma_comment.src
            from rma_comment
            where rma_comment.rma_id=$rma_id
        UNION ALL
        SELECT CONCAT('Rating case #', rating_comment.rating_id, ': ') as prefix, NULL as id, rating_comment.create_date, rating_comment.username, rating_comment.comment 
            , '' src
            from rating_comment 
            JOIN rating ON rating_comment.rating_id = rating.id
            JOIN rma ON rma.auction_number = rating.auction_number AND rma.txnid = rating.txnid
            where rma.rma_id=$rma_id
        UNION ALL
        select CONCAT('Alarm (',alarms.status,'):') as prefix
            , NULL as id
            , (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
            , alarms.username
            , alarms.comment 
            , '' src
            from rma
            join alarms on alarms.type='rma' and alarms.type_id=rma.rma_id
            where rma.rma_id=$rma_id
        ) t LEFT JOIN users u ON t.username=u.username
        ORDER BY t.create_date");
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

    static function addComment(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id, $username, $create_date, $comment, $src = ''
    ) {
        $rma_id = (int) $rma_id;
        $username = mysql_escape_string($username);
        $create_date = mysql_escape_string($create_date);
        //$comment = mysql_escape_string($comment); // we already have it escaped in js_backend
        $pic = mysql_escape_string($pic);
        $src = mysql_escape_string($src);
        $q = "insert into rma_comment set 
            rma_id=$rma_id, 
            username='$username',
            create_date='$create_date',
            comment='$comment',
            src='$src'";
        $r = $db->query($q);
        return $q;
    }

    static function delComment(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id) {
        $id = (int) $id;
        $db->query("delete from rma_comment where id=$id");
    }

    static function getSHRefunds(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id) {
        $r = $db->query("SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date , 
                sm.company_name AS shipping_method, '' as username, tn.shipping_method as shipping_method_id
            FROM rma_sh_refund rsr
            JOIN tracking_numbers tn ON rsr.sh_tracking_id = tn.id
            JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
            WHERE rsr.rma_id = $rma_id and rsr.sh_custom=0
            UNION SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date, sm.company_name AS shipping_method, 
                tn.username, tn.shipping_method as shipping_method_id
            FROM rma_sh_refund rsr
            JOIN rma_tracking_numbers tn ON rsr.sh_tracking_id = tn.id
            JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
            WHERE rsr.rma_id = $rma_id and rsr.sh_custom=1");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addSHRefund(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id, $sh_tracking_id, $sh_reason, $sh_value, $sh_decision, $sh_accepted, $sh_date, $amount_to_refund
    ) {
        $rma_id = (int) $rma_id;
        $sh_tracking_id = mysql_escape_string($sh_tracking_id);
        $sh_reason = mysql_escape_string($sh_reason);
        $sh_value = mysql_escape_string($sh_value);
        $sh_decision = mysql_escape_string($sh_decision);
        if ($sh_decision == '')
            $sh_decision = 'NULL';
        $sh_accepted = mysql_escape_string($sh_accepted);
        if ($sh_accepted == '')
            $sh_accepted = 'NULL';
        $sh_date = mysql_escape_string($sh_date);
        $amount_to_refund = mysql_escape_string($amount_to_refund);
        $r = $db->query("insert into rma_sh_refund set 
            rma_id=$rma_id, 
        sh_tracking_id = '$sh_tracking_id',
        sh_reason = '$sh_reason',
        sh_value = '$sh_value',
        sh_decision = $sh_decision,
        sh_accepted = $sh_accepted,
        amount_to_refund = '$amount_to_refund',
        sh_date = '$sh_date'
        ");
    }

    static function addSHRefundCustom(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id, $sh_tracking_number, $sh_date_time, $sh_shipping_method, $sh_shipping_date, $sh_username, $sh_reason, $sh_value, $sh_decision, $sh_accepted, $sh_date, $amount_to_refund) {
        $rma_id = (int) $rma_id;
        $sh_tracking_number = mysql_escape_string($sh_tracking_number);
        $sh_date_time = "'" . mysql_escape_string($sh_date_time) . "'";
        if ($sh_date_time == "''")
            $sh_date_time = 'NULL';
        $sh_shipping_method = "'" . mysql_escape_string($sh_shipping_method) . "'";
        if ($sh_shipping_method == "''")
            $sh_shipping_method = 'NULL';
        $sh_shipping_date = "'" . mysql_escape_string($sh_shipping_date) . "'";
        $sh_username = "'" . mysql_escape_string($sh_username) . "'";
        if ($sh_username == "''")
            $sh_username = 'NULL';
        $sh_reason = mysql_escape_string($sh_reason);
        $sh_value = mysql_escape_string($sh_value);
        $sh_decision = mysql_escape_string($sh_decision);
        if ($sh_decision == '')
            $sh_decision = 'NULL';
        $sh_accepted = mysql_escape_string($sh_accepted);
        if ($sh_accepted == '')
            $sh_accepted = 'NULL';
        $sh_date = mysql_escape_string($sh_date);
        $amount_to_refund = mysql_escape_string($amount_to_refund);
        $r = $db->query("insert into rma_tracking_numbers set 
            rma_id=$rma_id, 
        date_time= $sh_date_time,
        number = '$sh_tracking_number',
        shipping_method = $sh_shipping_method,
        shipping_date = $sh_shipping_date,
        username = $sh_username
        ");
        $sh_tracking_id = $dbr->getOne('select max(id) from rma_tracking_numbers');
        $r = $db->query("insert into rma_sh_refund set 
            rma_id=$rma_id, 
        sh_tracking_id = '$sh_tracking_id',
        sh_reason = '$sh_reason',
        sh_value = '$sh_value',
        sh_decision = $sh_decision,
        sh_accepted = $sh_accepted,
        sh_date = '$sh_date',
        amount_to_refund = '$amount_to_refund',
        sh_custom = 1
        ");
    }

    static function updateSHRefund(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $rma_id, $sh_tracking_id, $sh_reason, $sh_value, $sh_decision, $sh_accepted, $sh_cancelled, $amount_to_refund
    ) {
        $rma_id = (int) $rma_id;
        $sh_tracking_id = mysql_escape_string($sh_tracking_id);
        $sh_reason = mysql_escape_string($sh_reason);
        $sh_value = mysql_escape_string($sh_value);
        $sh_decision = mysql_escape_string($sh_decision);
        if ($sh_decision == '')
            $sh_decision = 'NULL';
        $sh_accepted = mysql_escape_string($sh_accepted);
        if ($sh_accepted == '')
            $sh_accepted = 'NULL';
        $amount_to_refund = mysql_escape_string($amount_to_refund);
        $r = $db->query("update rma_sh_refund set 
            sh_reason = '$sh_reason',
            sh_value = '$sh_value',
            sh_decision = $sh_decision,
            cancelled = $sh_cancelled,
            amount_to_refund = '$amount_to_refund',
            sh_accepted = $sh_accepted
        where sh_tracking_id=$sh_tracking_id 
            and rma_id=$rma_id
        ");
    }

    static function getSHRefundsList(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $cancelled) {
        $r = $db->query("SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date , sm.company_name AS shipping_method
                , auction.name_shipping cust_name
                , CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
            FROM rma_sh_refund rsr
            JOIN tracking_numbers tn ON rsr.sh_tracking_id = tn.id
            JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
            JOIN rma ON rma.rma_id = rsr.rma_id
            JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
            WHERE rsr.sh_custom=0 $cancelled
            UNION SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date, sm.company_name AS shipping_method
                , auction.name_shipping cust_name
                , CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
            FROM rma_sh_refund rsr
            JOIN rma_tracking_numbers tn ON rsr.sh_tracking_id = tn.id
            JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
            JOIN rma ON rma.rma_id = rsr.rma_id
            JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
            WHERE rsr.sh_custom=1 $cancelled");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function getSHRefundsIDs(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $ids) {
        $r = $db->query("SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date , sm.company_name AS shipping_method
                , auction.name_shipping cust_name
                , CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
            FROM rma_sh_refund rsr
            JOIN tracking_numbers tn ON rsr.sh_tracking_id = tn.id
            JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
            JOIN rma ON rma.rma_id = rsr.rma_id
            JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
            WHERE rsr.sh_custom=0 and rsr.sh_tracking_id in ($ids)
            UNION SELECT rsr . * , tn .number , tn .date_time , tn.shipping_date, sm.company_name AS shipping_method
                , auction.name_shipping cust_name
                , CONCAT(auction.zip_shipping, ',', auction.street_shipping, ',', auction.city_shipping, ',', auction.country_shipping) cust_addr
            FROM rma_sh_refund rsr
            JOIN rma_tracking_numbers tn ON rsr.sh_tracking_id = tn.id
            JOIN shipping_method sm ON tn.shipping_method = sm.shipping_method_id
            JOIN rma ON rma.rma_id = rsr.rma_id
            JOIN auction ON rma.auction_number = auction.auction_number AND rma.txnid = auction.txnid
            WHERE rsr.sh_custom=1 and rsr.sh_tracking_id in ($ids)");
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    function getPicturesPDF($hidden = true) {
        $db = $this->_db;
        $dbr = $this->_dbr;
        $tmp = 'tmp/';
        $rma_spec_id = array();
        foreach ($this->articles as $spec) {
            if ($hidden || (!$spec->hidden && ($spec->problem_id == 3 || $spec->problem_id == 7 || $spec->problem_id == 8)))
                $rma_spec_id[] = $spec->rma_spec_id;
        }
        if (!count($rma_spec_id))
            return 0;
        $ids = @implode(',', $rma_spec_id);
        $name = RMA::exportIDs($db, $dbr, $ids, $hidden);
        return file_get_contents($name);
    }

    static function exportIDs(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $ids, $hidden = true, $pics_cond = '', $request) {
        $tmp = 'tmp/';
        $rmas = RMA::findIds($db, $dbr, $ids);
        $pdf = File_PDF::factory('P', 'cm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(false);
        $y = 1;
        $y_max = 30;
        foreach ($rmas as $rma) {
            $pics = \Rma_Spec::getPics($db, $dbr, $rma->rma_id, $rma->rma_spec_id, 'NOT', $hidden);
            if ($pics_cond === '1' && !count($pics))
                continue;
            if ($pics_cond === '0' && count($pics))
                continue;
            $pdf->addPage();
            $pdf->setFillColor('rgb', 0, 0, 0);
            $pdf->setDrawColor('rgb', 0, 0, 0);
            $pdf->setFont('arial', 'B', 9);
            if (isset($request['with_article_name'])) {
                $pdf->write(0.4, 'Article ' . $rma->article_id . ': ' . $rma->article_name);
                $pdf->newLine();
            }
            $pdf->write(0.4, 'Supplier art. #: ' . $rma->supplier_article_id);
            $pdf->newLine();
            $pdf->write(0.4, 'Ticket #: ' . $rma->rma_id);
            $pdf->newLine();
            $pdf->write(0.4, 'Ticket date: ' . $rma->create_date);
            $pdf->newLine();
            $pdf->write(0.4, 'Responsible for Ticket: ' . $rma->rma_responsible_uname);
            $pdf->newLine();
            if (isset($request['with_problem'])) {
                $pdf->write(0.4, 'Problem: ' . $rma->problem_name);
                $pdf->newLine();
            }
            if (!count($pics)) {
                $pdf->write(0.4, 'No pics');
                $pdf->newLine();
            }
            foreach ($pics as $pic) {
                ini_set('display_errors', 'off');
                $img = imagecreatefromstring(get_file_path($pic->pic));
                ini_set('display_errors', 'on');
                if ($img) {
                    if ($pdf->getY() + 6.5 > $y_max) {
                        $pdf->setY(28);
                        $pdf->setX(18);
                        $pdf->cell(0, 2, 'Page ' . $pdf->getPageNo(), 0, 0, 'C');
                        $pdf->addPage();
                    }
                    $destsy = 7;
                    $destsx = $destsy * imagesx($img) / imagesy($img);
                    $img2 = imagecreatetruecolor($destsx, $destsy);
                    imagecopyresized($img2, $img, 0, 0, 0, 0, $destsx, $destsy, imagesx($img), imagesy($img));
                    $imgname = $tmp . '/' . $pic->pic_id . '.jpg';
                    $pdf->write(0.4, $pic->name);
                    $pdf->newLine();
                    imagejpeg($img, $imgname);
                    $pdf->image($imgname, 1, $pdf->getY(), $destsx, $destsy);
                    $pdf->newLine();
                    $pdf->setY($pdf->getY() + 7);
                    unlink($imgname);
                    imagedestroy($img);
                    imagedestroy($img2);
                } else {
                    $pdf->write(0.4, $pic->name . ' is not a valid picture!');
                    $pdf->newLine();
                }
            }
            $pdf->setY(28);
            $pdf->setX(18);
            $pdf->cell(0, 2, 'Page ' . $pdf->getPageNo(), 0, 0, 'C');
        }
        $pdf->close();
        $filename = 'export.pdf';
        $pdf->save($tmp . '/' . $filename, true);
        return $tmp . '/' . $filename;
    }

}
