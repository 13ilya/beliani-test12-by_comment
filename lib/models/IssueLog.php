<?php
/**
 * @author Ilia Kovalenko
 * Date: 17.11.2017
 */

class IssueLog extends CoreModel
{
    /**
     * @var string The table's name that will be used by this model.
     */
    protected static $tableName = 'issuelog';

    /**
     * @description Returns single Issue by id
     *
     * @param int $issue_id
     * @param int $master
     * @return array
     * @throws Exception
     */
    public static function findById($issue_id, $master = 0)
    {
        global $loggedUser;
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        $result = [];
        $select = " 
                  issuelog.*,
                  is_type.access,
                  GROUP_CONCAT(DISTINCT isd.department_id SEPARATOR ', ') as department_id
                  , IF(auction.auction_number, auction.auction_number, issuelog.obj_id) AS `number`
                  , IF(auction.txnid, auction.txnid, '') AS `txnid`
                  , users.name AS user_name
                  , users.email AS resp_email
                  , users_solving.name AS solving_user_name
                  , GROUP_CONCAT(DISTINCT empd.name SEPARATOR ', ') AS department_name
                  , tl_rec.Updated AS added_time_to_recurring
                  , users_tl_rec.name AS added_person_to_recurring,
                  (
                      SELECT comments.content
                      FROM comments
                      WHERE comments.obj_id = issuelog.id
                            AND comments.obj IN ('issuelog', 'issuelog_comment', 'issuelog_corrective')
                            AND comments.content != ''
                      ORDER BY comments.id DESC
                      LIMIT 1
                    ) AS last_comment
                  , (
                      SELECT comments.id
                      FROM comments
                      WHERE comments.obj_id = issuelog.id
                            AND comments.obj IN ('issuelog', 'issuelog_comment', 'issuelog_corrective')
                            AND comments.content != ''
                      ORDER BY comments.id DESC
                      LIMIT 1
                    ) AS last_comment_id
                  , (
                      SELECT CONCAT('by ',users.`name`,' on ',tl_com.Updated)
                      FROM total_log tl_com
                        JOIN users ON users.id = tl_com.username_id
                      WHERE tl_com.TableID = MAX(comments.id)
                            AND " . get_table_name('comments', 'tl_com') . "
                            AND " . get_field_name('id', 'tl_com') . "
                    ) AS last_comment_added
                  , (
                      SELECT CONCAT('by ',users.`name`,' on ',tl_com_cont.Updated)
                      FROM total_log tl_com_cont
                        JOIN users ON users.id = tl_com_cont.username_id
                      WHERE tl_com_cont.TableID = MAX(comments.id)
                            AND " . get_table_name('comments', 'tl_com_cont') . "
                            AND " . get_field_name('content', 'tl_com_cont') . "
                      ORDER BY tl_com_cont.Updated DESc
                      LIMIT 1
                    ) AS last_comment_updated
                  
                  , tl.Updated AS added_time
                  , users_tl.name AS added_person
                  , added_person_employee.id AS added_person_employee_id
                  , added_person_department.name AS added_person_department_name
                  , users_tl.email AS added_email
                  , users_tl_st.name AS change_by
                  , tl_st.Updated AS change_time
                  , (SELECT GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(tl_st_open.Updated, ' ', 1))) AS open_dates
                  , (SELECT GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(tl_st_not_open.Updated, ' ', 1))) AS not_open_dates,
                    (SELECT COUNT(*) FROM issue_checkpoint ic WHERE ic.issuelog_id = issuelog.id) as checkpoints_total,
                    (SELECT COUNT(*) FROM issue_checkpoint ic WHERE ic.issuelog_id = issuelog.id AND ic.done = 1) as checkpoints_done,
                    (SELECT COUNT(*) FROM close_notif WHERE close_notif.obj_id = issuelog.id AND close_notif.username = '" . $loggedUser->get('username') . "' AND close_notif.obj = 'issuelog') as close_notif,
                    MD5(CONCAT(MD5(issuelog.id),'issuesaltDg4gdrfhejD')) as share_hash,
                    CASE issuelog.obj
                        WHEN 'shop_cat' THEN 'Shop catalogue: '
                        WHEN 'shop_catalogue' THEN 'Shop catalogue: '
                        WHEN 'shipping_plan' THEN 'Shipping plan: '
                        WHEN 'sa' THEN 'SA: '
                        WHEN 'offer' THEN 'Offer: '
                        WHEN 'article' THEN 'Article: '
                        WHEN 'supplier' THEN 'Supplier: '
                        WHEN 'auction' THEN 'Auftrag: '
                        WHEN 'rma' THEN 'Ticket: '
                        WHEN 'ww_order' THEN 'WWO: '
                        WHEN 'op_order' THEN 'OP Order: '
                        WHEN 'route' THEN 'Route: '
                        WHEN 'manual' THEN 'Manual'
                        WHEN 'rating' THEN 'Rating: '
                        WHEN 'insurance' THEN 'Insurance: '
                        WHEN 'shippingInvoiceResults' THEN 'Shipping invoice: '
                        WHEN 'monitoredShippingPrices' THEN 'Monitored shipping price: '
                        WHEN 'wwo' THEN 'WWO: '
                        WHEN 'car' THEN 'Car: '
                        WHEN 'fork_lift' THEN 'Forklift'
                    END AS where_did,
                    CASE issuelog.obj
                        WHEN 'shop_cat' THEN CONCAT('shop_cat.php?cat_id=',issuelog.obj_id)
                        WHEN 'shop_catalogue' THEN CONCAT('shop_cat.php?cat_id=',issuelog.obj_id)
                        WHEN 'shipping_plan' THEN CONCAT('shipping_plan.php?id=',issuelog.obj_id)
                        WHEN 'sa' THEN CONCAT('react/condensed/condensed_sa/',issuelog.obj_id,'/')
                        WHEN 'offer' THEN CONCAT('offer.php?id=',issuelog.obj_id)
                        WHEN 'article' THEN CONCAT('article.php?original_article_id=',issuelog.obj_id)
                        WHEN 'supplier' THEN CONCAT('op_suppliers.php?company_id=',issuelog.obj_id)
                        WHEN 'auction' THEN CONCAT('auction.php?number=',auction.auction_number,'&txnid=',auction.txnid)
                        WHEN 'rma' THEN CONCAT('rma.php?rma_id=',issuelog.obj_id,'&number=',rma.auction_number,'&txnid=',rma.txnid)
                        WHEN 'ww_order' THEN CONCAT('ware2ware_order.php?id=',issuelog.obj_id)
                        WHEN 'op_order' THEN CONCAT('op_order.php?id=',issuelog.obj_id)
                        WHEN 'route' THEN CONCAT('route.php?id=',issuelog.obj_id)
                        WHEN 'rating' THEN CONCAT('rating_case.php?id=',issuelog.obj_id)
                        WHEN 'insurance' THEN CONCAT('insurance.php?id=',issuelog.obj_id)
                        WHEN 'shippingInvoiceResults' THEN CONCAT('react/shipping_pages/invoice_settings/result/',issuelog.obj_id,'/')
                        WHEN 'monitoredShippingPrices' THEN CONCAT('react/logs/shipping_price_monitor/',issuelog.obj_id,'/')
                        WHEN 'wwo' THEN CONCAT('ware2ware_order.php?id=',issuelog.obj_id)
                        WHEN 'car' THEN CONCAT('car.php?id=',issuelog.obj_id)
                        WHEN 'fork_lift' THEN CONCAT('fork_lift.php?id=',issuelog.obj_id)
                    END AS url,
                    CASE issuelog.obj
                        WHEN 'supplier' THEN sup.name
                        WHEN 'car' THEN CONCAT('Car: ', issuelog.obj_id, ' (', (SELECT cars.name FROM cars WHERE cars.id = issuelog.obj_id LIMIT 1) ,')')
                        WHEN 'fork_lift' THEN CONCAT('Forklift: ', issuelog.obj_id, ' (', (SELECT fork_lift.model FROM fork_lift WHERE fork_lift.id = issuelog.obj_id LIMIT 1) ,')')
                        WHEN 'shippingInvoiceResults' THEN CONCAT ('Shipping invoice ', ail.name)
                    END AS url_text,
                    ipd.user_id as userid,ipd.days
                ";

        $joins = "LEFT JOIN users ON users.username = issuelog.resp_username
                  LEFT JOIN users users_solving ON users_solving.username = issuelog.solving_resp_username
                  LEFT JOIN auction ON auction.id = issuelog.obj_id AND issuelog.obj = 'auction'
                  LEFT JOIN rma ON rma.rma_id = issuelog.obj_id AND issuelog.obj = 'rma'
                  LEFT JOIN auction auction_rma ON rma.auction_number = auction_rma.auction_number AND rma.txnid = auction_rma.txnid
                  LEFT JOIN orders ON auction.auction_number = orders.auction_number and auction.txnid = orders.txnid
                  LEFT JOIN orders orders_rma ON rma.auction_number = orders_rma.auction_number and rma.txnid = orders_rma.txnid
                  LEFT JOIN auction_par_varchar ON auction.auction_number = auction_par_varchar.auction_number AND auction.txnid = auction_par_varchar.txnid AND auction_par_varchar.`key` = 'country_shipping'
                  LEFT JOIN auction_par_varchar apv_rma ON rma.auction_number = apv_rma.auction_number AND rma.txnid = apv_rma.txnid AND apv_rma.`key` = 'country_shipping'
                  LEFT JOIN comments ON issuelog.id = comments.obj_id AND comments.obj IN ('issuelog', 'issuelog_comment', 'issuelog_corrective')
                  LEFT JOIN total_log tl ON 
                    tl.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl') . "
                    AND " . get_field_name('id', 'tl') . "
                  LEFT JOIN total_log tl_rec ON 
                    tl_rec.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl_rec') . "
                    AND " . get_field_name('recurring', 'tl_rec') . "
                  LEFT JOIN total_log tl_st ON 
                    tl_st.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl_st') . "
                    AND " . get_field_name('status', 'tl_st') . "
                  LEFT JOIN total_log tl_st_open ON 
                    tl_st_open.TableID = issuelog.id 
                    AND tl_st_open.New_value = 'open'
                    AND " . get_table_name('issuelog', 'tl_st_open') . "
                    AND " . get_field_name('status', 'tl_st_open') . "
                  LEFT JOIN total_log tl_st_not_open ON 
                    tl_st_not_open.TableID = issuelog.id AND 
                    tl_st_not_open.New_value != 'open'
                    AND " . get_table_name('issuelog', 'tl_st_not_open') . "
                    AND " . get_field_name('status', 'tl_st_not_open') . "
                  LEFT JOIN users users_tl ON users_tl.id = tl.username_id
                  LEFT JOIN employee AS added_person_employee ON added_person_employee.username = users_tl.username
                  LEFT JOIN emp_department AS added_person_department ON added_person_department.id = added_person_employee.department_id
                  LEFT JOIN users users_tl_rec ON users_tl_rec.id = tl_rec.username_id
                  LEFT JOIN users users_tl_st ON users_tl_st.id = tl_st.username_id
                  LEFT JOIN issuelog_type ON issuelog_type.issuelog_id = issuelog.id
                  LEFT JOIN issue_type is_type ON is_type.id = issuelog_type.type_id
                  LEFT JOIN op_company sup ON sup.id = issuelog.obj_id AND issuelog.obj = 'supplier'
                  LEFT JOIN issuelog_dep isd ON isd.issuelog_id = issuelog.id
                  LEFT JOIN emp_department empd ON empd.id IN (isd.department_id)
                  LEFT JOIN autoship_import_log ail ON ail.id = issuelog.obj_id
                  LEFT JOIN issue_ping_days ipd ON ipd.issuelog_id = issuelog.id
              ";

        $q = "SELECT /*issuelog single*/
                " . $select . "
            FROM issuelog
                " . $joins . "
            WHERE issuelog.id = " . $issue_id . "
                GROUP BY issuelog.id
                ORDER BY issuelog.id DESC
                LIMIT 1
            ";

        if ($master) {
            $result['issue_list'] = $db->getAll($q);
        } else {
            $result['issue_list'] = $dbr->getAll($q);
        }

        foreach ($result['issue_list'] as $key => $issue) {
            /**
             * Get ping days
             */
            $q = "SELECT IFNULL(days, 0)
                    FROM prologis2.issue_ping_days
                    WHERE issuelog_id = " . $issue->id . " LIMIT 1";
            $result['issue_list'][$key]->ping_days = $dbr->getOne($q);

            /**
             * Count pending days, get issue types
             */
            $q = "SELECT issuelog_type.type_id id, issue_type.name
                    FROM issuelog_type
                    LEFT JOIN issue_type ON issue_type.id = issuelog_type.type_id
                    WHERE issuelog_id = " . $issue->id;
            $types = $dbr->getAll($q);

            $result['issue_list'][$key]->issue_type = [];
            if ($types) {
                $result['issue_list'][$key]->issue_type = $types;
            }
            $result['issue_list'][$key]->days_passed = 0;
            $open_dates = explode(',', $issue->open_dates);
            $open_dates = array_diff($open_dates, ['']);
            $open_date = min($open_dates);
            $not_open_dates = explode(',', $issue->not_open_dates);
            $not_open_dates = array_diff($not_open_dates, ['']);
            if (count($not_open_dates) < count($open_dates)) {
                $close_date = date('Y-m-d');
            } else {
                $close_date = max($not_open_dates);
            }
            if ($open_date && $close_date) {
                $datetime1 = new DateTime($open_date);
                $datetime2 = new DateTime($close_date);
                $interval = $datetime1->diff($datetime2);
                $result['issue_list'][$key]->days_passed = $interval->format('%a');
            }

            // Convert department_id field to array
            if (isset($result['issue_list'][$key]->department_id)) {
                $result['issue_list'][$key]->department_id = explode(', ', $result['issue_list'][$key]->department_id);
            }

            /*
             *  Getting additional fields via IssueLog model
             */
            $issue = new self($issue->id);
            $fields = $issue->getAdditionalFields();
            if (count($fields) > 0) {
                foreach ($fields as $field_key => $field) {
                    $result['issue_list'][$key]->$field_key = $field;
                }
            }
        }

        return $result;
    }

    /**
     * @description Returns the Issues by filter conditions
     * @return IssueLog[] An array with instances of the Issues.
     * @var $params array of filter params
     */
    public static function findByFilterParams($params)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $result = $where = [];

        if ($params['status']) {
            if (is_array($params['status'])) {
                $st = implode(',', array_map(static function ($v) use ($dbr){
                    return "'" . $dbr->escape($v) . "'";
                }, $params['status']));
                $where[] = " issuelog.status IN (" . $st . ")";
            } else {
                $where[] = " issuelog.status = '" . $params['status'] . "'";
            }
        }
        if ($params['status_not']) {
            if (is_array($params['status_not'])) {
                $st = implode(',', array_map(static function ($v) use ($dbr){
                    return "'" . $dbr->escape($v) . "'";
                }, $params['status_not']));
                $where[] = " issuelog.status NOT IN (" . $st . ")";
            } else {
                $where[] = " issuelog.status != '" . $params['status_not'] . "'";
            }
        }
        if ($params['type_id']) {
            $types = implode(',', $params['type_id']);
            $where[] = " issuelog_type.type_id IN ($types) ";
        }
        if ($params['issue_type']) {
            $issue_default_type = Config::getValue('default_issue_type');

            if (is_array($params['issue_type'])) {
                $issue_types = $params['issue_type'];
            } else {
                $issue_types = explode(',', $params['issue_type']);
            }

            if (in_array($issue_default_type, $issue_types)) {
                $where[] = " (issuelog_type.type_id IN (" . implode(',', $issue_types) . ")  OR issuelog_type.type_id IS NULL)";
            } else {
                $where[] = " issuelog_type.type_id IN (" . implode(',', $issue_types) . ")";
            }
        }
        if ($params['show_with_inactive'] == 1) {
            $where[] = " (issuelog.inactive = 1 OR issuelog.inactive = 0) ";
        } else {
            $where[] = " issuelog.inactive = 0 ";
        }
        if ($params['where']) {
            $where[] = " issuelog.obj = '" . $params['where'] . "'";
        }
        if ($params['where_did']) {
            $where[] = " issuelog.obj = '" . $params['where_did'] . "'";
        }
        if ($params['id']) {
            $where[] = " issuelog.id = '" . $params['id'] . "'";
        }
        if ($params['obj']) {
            $where[] = " issuelog.obj = '" . $params['obj'] . "'";
        }
        if ($params['obj_id']) {
            $where[] = " issuelog.obj_id = " . $params['obj_id'];
        }
        if ($params['date_from'] && $params['date_to']) {
            $where[] = "tl.Updated >= '" . $params['date_from'] . " 00:00:00'";
            $where[] = "tl.Updated <= '" . $params['date_to'] . " 23:59:59'";
        }
        if ($params['date_from'] && !$params['date_to']) {
            $where[] = "tl.Updated >= '" . $params['date_from'] . " 00:00:00'";
        }
        if (!$params['date_from'] && $params['date_to']) {
            $where[] = "tl.Updated <= '" . $params['date_to'] . " 23:59:59'";
        }
        if ($params['department_id']) {
            if (is_array($params['department_id'])) {
                $where[] = "isd.department_id IN (" . implode(', ', $params['department_id']) . ")";
            } else {
                $where[] = "isd.department_id IN (" . (int)$params['department_id'] . ")";
            }
        }
        if ($params['solving_resp_person']) {
            $where[] = "issuelog.solving_resp_username = '" . $params['solving_resp_person'] . "'";
        }
        if ($params['solving_resp_username']) {
            if (is_array($params['solving_resp_username'])) {
                /**
                 * https://trello.com/c/rqg8VFzU/7466-justyna-issue-inactive-users
                 * filter by NONE
                 */
                if (isset($params['solving_resp_username']['none'])) {
                    $where[] = "users_solving.deleted = 1";

                    unset($params['solving_resp_username']['none']);
                }
                $solving_resp_usernames = "'" . implode("','", $params['solving_resp_username']) . "'";
                $where[] = "issuelog.solving_resp_username in ($solving_resp_usernames)";
            } elseif ($params['solving_resp_username'] === 'none') {
                $where[] = "users_solving.deleted = 1";
            } else {
                $where[] = "issuelog.solving_resp_username = '{$params['solving_resp_username']}'";
            }
        }
        if ($params['resp_person']) {
            $where[] = "issuelog.resp_username = '" . $params['resp_person'] . "'";
        }
        if ($params['resp_username']) {
            if (is_array($params['resp_username'])) {
                /**
                 * https://trello.com/c/rqg8VFzU/7466-justyna-issue-inactive-users
                 * filter by NONE
                 */
                if (isset($params['resp_username']['none'])) {
                    $where[] = "users.deleted = 1";

                    unset($params['resp_username']['none']);
                }
                $resp_usernames = "'" . implode("','", $params['resp_username']) . "'";
                $where[] = "issuelog.resp_username in ($resp_usernames)";
            } elseif ($params['resp_username'] === 'none') {
                $where[] = "users.deleted = 1";
            } else {
                $where[] = "issuelog.resp_username = '{$params['resp_username']}'";
            }
        }
        if ($params['seller']) {
            $where[] = "(auction.username = '" . $params['seller'] . "' OR auction_rma.username = '" . $params['seller'] . "')";
        }
        if ($params['source_seller']) {
            $where[] = "(auction.source_seller_id = '" . $params['source_seller'] . "' OR auction_rma.source_seller_id = '" . $params['source_seller'] . "')";
        }
        if ($params['shipping_method']) {
            $where[] = "(auction.shipping_method = '" . $params['shipping_method'] . "' OR auction_rma.shipping_method = '" . $params['shipping_method'] . "' OR ais.shipping_method_id = '" . $params['shipping_method'] . "')";
        }
        if ($params['warehouse_shipped_from']) {
            $where[] = "(orders.send_warehouse_id = '" . $params['warehouse_shipped_from'] . "' OR orders_rma.send_warehouse_id = '" . $params['warehouse_shipped_from'] . "')";
        }
        if ($params['shipping_country']) {
            $where[] = "(auction_par_varchar.value = '" . $params['shipping_country'] . "' OR apv_rma.value = '" . $params['shipping_country'] . "')";
        }
        if ($params['by_comment']) {
            $str = $dbr->escape(trim($params['by_comment']));
            $where[] = "(issuelog.issue LIKE '%" . $str . "%' OR comments.content LIKE '%" . $str . "%')";
        }
        if ($params['supplier_id'] && $params['supplier_id'] > 0) {
            $where[] = "( sup.id = " . (int)$params['supplier_id'] . " )";
        }
        if ($params['added_person']) {
            /**
             * https://trello.com/c/rqg8VFzU/7466-justyna-issue-inactive-users
             * filter by NONE
             */
            if ($params['added_person'] === 'none') {
                $where[] = "users_tl_rec.deleted = 1";
            } else {
                $where[] = "users_tl.username = '" . $params['added_person'] . "'";
            }
        }
        if (isset($params['recurring'])) {
            if ($params['recurring'] == 0 || $params['recurring'] == 1) {
                $where[] = "issuelog.recurring = " . (int)$params['recurring'];
            }
        }
        // filter should show only Issues older than $params['older_than_days']
        if (isset($params['older_than_days'])) {
            $params['older_than_days'] = (int)$params['older_than_days'];

            $date_older = (new DateTime('NOW'))
                ->modify('-' . $params['older_than_days'] . ' day')
                ->format('Y-m-d');
            $total_log_id = $dbr->getOne("
                SELECT MAX(total_log_id) FROM total_log_id
                    WHERE Updated <= '" . $date_older . "'
                ");
            $where[] = "tl.ID < '" . $total_log_id . "'";
        }
        /**
         * https://trello.com/c/XCTm1Nqu/7930-sabina-issue-log-position
         */
        if ($params['position']) {
            $where[] = "ep.id = '" . (int)$params['position'] . "'";
        }

        $select = "
                  issuelog.id,
                  issuelog.obj,
                  issuelog.obj_id,
                  issuelog.status,
                  issuelog.recurring,
                  issuelog.solving_resp_username,
                  issuelog.resp_username,
                  issuelog.closed,
                  issuelog.issue,
                  IF(auction.auction_number, auction.auction_number, issuelog.obj_id) AS `number`,
                  IF(auction.txnid, auction.txnid, '') AS `txnid`,
                  users.name AS user_name,
                  users.email AS resp_email,
                  users_solving.name AS solving_user_name,
                  GROUP_CONCAT(DISTINCT empd.name SEPARATOR ', ') AS department_name,
                  tl_rec.Updated AS added_time_to_recurring,
                  users_tl_rec.name AS added_person_to_recurring,
                  ep.title AS position_title,
                  (
                      SELECT comments.content
                      FROM comments
                      WHERE comments.obj_id = issuelog.id
                            AND comments.obj IN ('issuelog', 'issuelog_comment', 'issuelog_corrective')
                            AND comments.content != ''
                      ORDER BY comments.id DESC
                      LIMIT 1
                    ) AS last_comment,
                  (
                      SELECT CONCAT('by ',users.`name`,' on ',tl_com.Updated)
                      FROM total_log tl_com
                        JOIN users ON users.id = tl_com.username_id
                      WHERE tl_com.TableID = MAX(comments.id)
                            AND " . get_table_name('comments', 'tl_com') . "
                            AND " . get_field_name('id', 'tl_com') . "
                    ) AS last_comment_added,
                  (
                      SELECT tl_com.Updated
                      FROM total_log tl_com
                        JOIN users ON users.id = tl_com.username_id
                      WHERE tl_com.TableID = MAX(comments.id)
                            AND " . get_table_name('comments', 'tl_com') . "
                            AND " . get_field_name('id', 'tl_com') . "
                    ) AS last_comment_added_date,
                  (
                      SELECT users.`name`
                      FROM total_log tl_com
                        JOIN users ON users.id = tl_com.username_id
                      WHERE tl_com.TableID = MAX(comments.id)
                            AND " . get_table_name('comments', 'tl_com') . "
                            AND " . get_field_name('id', 'tl_com') . "
                    ) AS last_comment_added_name,
                  tl.Updated AS added_time,
                  users_tl.name AS added_person,
                  users_tl_st.name AS change_by,
                  users_tl.email AS added_email,
                  tl_st.Updated AS change_time,
                  (SELECT GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(tl_st_open.Updated, ' ', 1))) AS open_dates,
                  (SELECT GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(tl_st_not_open.Updated, ' ', 1))) AS not_open_dates,
                  (SELECT COUNT(*) FROM issue_checkpoint ic WHERE ic.issuelog_id = issuelog.id) as checkpoints_total,
                  (SELECT COUNT(*) FROM issue_checkpoint ic WHERE ic.issuelog_id = issuelog.id AND ic.done = 1) as checkpoints_done,
                    CASE issuelog.obj
                        WHEN 'shop_cat' THEN 'Shop catalogue: '
                        WHEN 'shop_catalogue' THEN 'Shop catalogue: '
                        WHEN 'shipping_plan' THEN 'Shipping plan: '
                        WHEN 'sa' THEN 'SA: '
                        WHEN 'offer' THEN 'Offer: '
                        WHEN 'article' THEN 'Article: '
                        WHEN 'supplier' THEN 'Supplier: '
                        WHEN 'auction' THEN 'Auftrag: '
                        WHEN 'rma' THEN 'Ticket: '
                        WHEN 'ww_order' THEN 'WWO: '
                        WHEN 'op_order' THEN 'OP Order: '
                        WHEN 'route' THEN 'Route: '
                        WHEN 'manual' THEN 'Manual'
                        WHEN 'rating' THEN 'Rating: '
                        WHEN 'insurance' THEN 'Insurance: '
                        WHEN 'shippingInvoiceResults' THEN 'Shipping invoice: '
                        WHEN 'monitoredShippingPrices' THEN 'Monitored shipping price: '
                        WHEN 'wwo' THEN 'WWO: '
                        WHEN 'car' THEN 'Car: '
                        WHEN 'fork_lift' THEN 'Forklift'
                    END AS where_did,
                    CASE issuelog.obj
                        WHEN 'shop_cat' THEN CONCAT('shop_cat.php?cat_id=',issuelog.obj_id)
                        WHEN 'shop_catalogue' THEN CONCAT('shop_cat.php?cat_id=',issuelog.obj_id)
                        WHEN 'shipping_plan' THEN CONCAT('shipping_plan.php?id=',issuelog.obj_id)
                        WHEN 'sa' THEN CONCAT('react/condensed/condensed_sa/',issuelog.obj_id,'/')
                        WHEN 'offer' THEN CONCAT('offer.php?id=',issuelog.obj_id)
                        WHEN 'article' THEN CONCAT('article.php?original_article_id=',issuelog.obj_id)
                        WHEN 'supplier' THEN CONCAT('op_suppliers.php?company_id=',issuelog.obj_id)
                        WHEN 'auction' THEN CONCAT('auction.php?number=',auction.auction_number,'&txnid=',auction.txnid)
                        WHEN 'rma' THEN CONCAT('rma.php?rma_id=',issuelog.obj_id,'&number=',rma.auction_number,'&txnid=',rma.txnid)
                        WHEN 'ww_order' THEN CONCAT('ware2ware_order.php?id=',issuelog.obj_id)
                        WHEN 'op_order' THEN CONCAT('op_order.php?id=',issuelog.obj_id)
                        WHEN 'route' THEN CONCAT('route.php?id=',issuelog.obj_id)
                        WHEN 'rating' THEN CONCAT('rating_case.php?id=',issuelog.obj_id)
                        WHEN 'insurance' THEN CONCAT('insurance.php?id=',issuelog.obj_id)
                        WHEN 'shippingInvoiceResults' THEN CONCAT('react/shipping_pages/invoice_settings/result/',issuelog.obj_id,'/')
                        WHEN 'monitoredShippingPrices' THEN CONCAT('react/logs/shipping_price_monitor/',issuelog.obj_id,'/')
                        WHEN 'wwo' THEN CONCAT('ware2ware_order.php?id=',issuelog.obj_id)
                        WHEN 'car' THEN CONCAT('car.php?id=',issuelog.obj_id)
                        WHEN 'fork_lift' THEN CONCAT('fork_lift.php?id=',issuelog.obj_id)
                    END AS url,
                    CASE issuelog.obj
                        WHEN 'supplier' THEN sup.name
                        WHEN 'car' THEN CONCAT('Car: ', issuelog.obj_id, ' (', (SELECT cars.name FROM cars WHERE cars.id = issuelog.obj_id LIMIT 1) ,')')
                        WHEN 'fork_lift' THEN CONCAT('Forklift: ', issuelog.obj_id, ' (', (SELECT fork_lift.model FROM fork_lift WHERE fork_lift.id = issuelog.obj_id LIMIT 1) ,')')
                        WHEN 'shippingInvoiceResults' THEN CONCAT ('Shipping invoice ', ail.name)
                    END AS url_text
                ";

        $joins = "LEFT JOIN users ON users.username = issuelog.resp_username
                  LEFT JOIN users users_solving ON users_solving.username = issuelog.solving_resp_username
                  LEFT JOIN auction ON auction.id = issuelog.obj_id AND issuelog.obj = 'auction'
                  LEFT JOIN auction slave_auction ON slave_auction.main_auction_number = auction.auction_number AND slave_auction.main_txnid = auction.txnid
                  LEFT JOIN rma ON rma.rma_id = issuelog.obj_id AND issuelog.obj = 'rma'
                  LEFT JOIN auction auction_rma ON rma.auction_number = auction_rma.auction_number AND rma.txnid = auction_rma.txnid
                  LEFT JOIN orders ON IFNULL(slave_auction.auction_number, auction.auction_number) = orders.auction_number and IFNULL(slave_auction.txnid, auction.txnid) = orders.txnid AND orders.manual = 0
                  LEFT JOIN orders orders_rma ON rma.auction_number = orders_rma.auction_number and rma.txnid = orders_rma.txnid
                  LEFT JOIN auction_par_varchar ON auction.auction_number = auction_par_varchar.auction_number AND auction.txnid = auction_par_varchar.txnid AND auction_par_varchar.`key` = 'country_shipping'
                  LEFT JOIN auction_par_varchar apv_rma ON rma.auction_number = apv_rma.auction_number AND rma.txnid = apv_rma.txnid AND apv_rma.`key` = 'country_shipping'
                  LEFT JOIN comments ON issuelog.id = comments.obj_id AND comments.obj IN ('issuelog', 'issuelog_comment', 'issuelog_corrective')
                  LEFT JOIN total_log tl ON 
                    tl.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl') . "
                    AND " . get_field_name('id', 'tl') . "
                  LEFT JOIN total_log tl_rec ON 
                    tl_rec.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl_rec') . "
                    AND " . get_field_name('recurring', 'tl_rec') . "
                  LEFT JOIN total_log tl_st ON 
                    tl_st.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl_st') . "
                    AND " . get_field_name('status', 'tl_st') . "
                  LEFT JOIN total_log tl_st_open ON 
                    tl_st_open.TableID = issuelog.id 
                    AND tl_st_open.New_value = 'open'
                    AND " . get_table_name('issuelog', 'tl_st_open') . "
                    AND " . get_field_name('status', 'tl_st_open') . "
                  LEFT JOIN total_log tl_st_not_open ON 
                    tl_st_not_open.TableID = issuelog.id AND 
                    tl_st_not_open.New_value != 'open'
                    AND " . get_table_name('issuelog', 'tl_st_not_open') . "
                    AND " . get_field_name('status', 'tl_st_not_open') . "
                  LEFT JOIN users users_tl ON users_tl.id = tl.username_id
                  LEFT JOIN users users_tl_rec ON users_tl_rec.id = tl_rec.username_id
                  LEFT JOIN users users_tl_st ON users_tl_st.id = tl_st.username_id
                  LEFT JOIN issuelog_type ON issuelog_type.issuelog_id = issuelog.id
                  LEFT JOIN issue_type is_type ON is_type.id = issuelog_type.type_id
                  LEFT JOIN op_company sup ON sup.id = issuelog.obj_id AND issuelog.obj = 'supplier'
                  LEFT JOIN issuelog_dep isd ON isd.issuelog_id = issuelog.id
                  LEFT JOIN emp_department empd ON empd.id IN (isd.department_id)
                  LEFT JOIN autoship_import_log ail ON ail.id = issuelog.obj_id AND issuelog.obj = 'shippingInvoiceResults'
                  LEFT JOIN autoship_invoice_settings ais ON ais.id = ail.autoship_invoice_setting_id
                  LEFT JOIN emp_position ep ON ep.id = issuelog.employee_position
              ";
        /**
         *  Pagination logic
         */
        $page = 1;
        $issues_per_page = 50;
        if ($params['page'] && $params['page'] > 1) {
            $page = (int)$params['page'];
        }
        if ($params['issues_per_page'] && $params['issues_per_page'] > 0) {
            $issues_per_page = (int)$params['issues_per_page'];
        }
        if ($page > 1) {
            $limit = " LIMIT " . ($issues_per_page * ($page - 1) + 1) . ", " . $issues_per_page;
        } else {
            $limit = " LIMIT " . $issues_per_page;
        }

        $result['issue_pagination'] = [
            'page' => $page,
            'per_page' => $issues_per_page,
        ];

        /*
         *  Main filter query
         */
        $q = "SELECT /*issuelog filter*/ SQL_CALC_FOUND_ROWS
                " . $select . "
            FROM issuelog
                " . $joins . "
            WHERE 1 " . ($where ? ' AND ' . implode(' AND ', $where) : '') . "
                GROUP BY issuelog.id
                ORDER BY issuelog.id DESC
                " . $limit;
        //        echo $q; die();

        $issue_list = $dbr->getAll($q);
        $totalRows = $dbr->getOne('SELECT FOUND_ROWS()');

        $result['issue_list'] = $issue_list;
        $result['issue_pagination']['total'] = $totalRows;

        foreach ($result['issue_list'] as $key => $issue) {
            /**
             * Count pending days, get issue types
             */
            $q = "SELECT issuelog_type.type_id id, issue_type.name
                    FROM issuelog_type
                    LEFT JOIN issue_type ON issue_type.id = issuelog_type.type_id
                    WHERE issuelog_id = " . $issue->id;
            $types = $dbr->getAll($q);

            $result['issue_list'][$key]->issue_type = [];
            if ($types) {
                $result['issue_list'][$key]->issue_type = $types;
            }
            $result['issue_list'][$key]->days_passed = 0;
            $open_dates = explode(',', $issue->open_dates);
            $open_dates = array_diff($open_dates, ['']);
            $open_date = min($open_dates);
            $not_open_dates = explode(',', $issue->not_open_dates);
            $not_open_dates = array_diff($not_open_dates, ['']);
            if (count($not_open_dates) < count($open_dates)) {
                $close_date = date('Y-m-d');
            } else {
                $close_date = max($not_open_dates);
            }
            if ($open_date && $close_date) {
                $datetime1 = new DateTime($open_date);
                $datetime2 = new DateTime($close_date);
                $interval = $datetime1->diff($datetime2);
                $result['issue_list'][$key]->days_passed = $interval->format('%a');
            }

            // Convert department_id field to array
            if (isset($result['issue_list'][$key]->department_id)) {
                $result['issue_list'][$key]->department_id = explode(', ', $result['issue_list'][$key]->department_id);
            }
        }

        return $result;
    }

    /**
     * @description Returns the Issues by filter conditions in short format
     * @return IssueLog[] An array with instances of the Issues.
     * @var $params array of filter params
     */
    public static function findByFilterParamsShort($params)
    {
        //$db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $result = [];
        $where = [];
        /*
         * Supported params:
         *  - status/status_not
         *  - obj/obj_id
         */
        if ($params['status']) {
            if (is_array($params['status'])) {
                $st = implode(',', array_map(static function ($v){
                    return "'" . mysql_real_escape_string($v) . "'";
                }, $params['status']));
                $where[] = " issuelog.status IN (" . $st . ")";
            } else {
                $where[] = " issuelog.status = '" . $params['status'] . "'";
            }
        }
        if ($params['status_not']) {
            if (is_array($params['status_not'])) {
                $st = implode(',', array_map(static function ($v){
                    return "'" . mysql_real_escape_string($v) . "'";
                }, $params['status_not']));
                $where[] = " issuelog.status NOT IN (" . $st . ")";
            } else {
                $where[] = " issuelog.status != '" . $params['status_not'] . "'";
            }
        }
        if ($params['obj']) {
            $where[] = " issuelog.obj = '" . $params['obj'] . "'";
        }
        if ($params['obj_id']) {
            $where[] = " issuelog.obj_id = " . $params['obj_id'];
        }

        $select = "
                  issuelog.id,
                  issuelog.status,
                  tl.Updated AS added_time,
                  users.name AS user_name,
                  GROUP_CONCAT(DISTINCT empd.name SEPARATOR ', ') AS department_name,
                  issuelog.issue,
                  GROUP_CONCAT(issue_type.name SEPARATOR ', ') issue_types,
                  issuelog.resp_username,
                  CONCAT('by ', users_tl_st.name, ' on ', tl_st.Updated) as log
                ";

        $joins = "LEFT JOIN total_log tl ON tl.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl') . "
                    AND " . get_field_name('id', 'tl') . "
                  LEFT JOIN users ON users.username = issuelog.resp_username
                  LEFT JOIN issuelog_type ON issuelog_type.issuelog_id = issuelog.id
                  LEFT JOIN issue_type ON issue_type.id = issuelog_type.type_id
                  LEFT JOIN total_log tl_st ON tl_st.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl_st') . "
                    AND " . get_field_name('status', 'tl_st') . "
                  LEFT JOIN users users_tl_st ON users_tl_st.id = tl_st.username_id
                  LEFT JOIN issuelog_dep isd ON isd.issuelog_id = issuelog.id
                  LEFT JOIN emp_department empd ON empd.id IN (isd.department_id)
              ";

        $q = "SELECT /*short issuelog*/" . $select . "
              FROM issuelog
                " . $joins . "
              WHERE 1 " . ($where ? ' AND ' . implode(' AND ', $where) : '') . "
                    GROUP BY issuelog.id
                    ORDER BY issuelog.id DESC
             ";
        //echo $q; die();
        $result['issue_list'] = $dbr->getAll($q);

        return $result;
    }

    /**
     * @description Get allowed to change issue status ids
     * @param $username
     * @return array of issue ids
     * @throws Exception
     */
    public static function getAllowedToChangeIssueStatusIds($username)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $q = "SELECT issuelog.id 
              FROM issuelog 
                JOIN total_log tl ON 
                    tl.TableID = issuelog.id
                    AND " . get_table_name('issuelog', 'tl') . "
                    AND " . get_field_name('id', 'tl') . "
                JOIN users ON users.id = tl.username_id
              WHERE users.username = '" . $username . "'";

        return $dbr->getAll($q);
    }

    /**
     * @description Adding issue types
     * @var array $issueTypes - array of issue type ids
     */
    public function addIssueTypes($issueTypes)
    {
        if (count($issueTypes) > 0) {
            foreach ($issueTypes as $type) {
                $q = "INSERT INTO issuelog_type 
                      SET type_id = " . $type . ", 
                          issuelog_id = " . $this->get('id');
                $this->_db->query($q);
            }
        }
    }


    /**
     * @description Saving issue departments
     * @return array
     * @var array $departmentIds - array of issue department ids
     */
    public function saveDepartments($departmentIds)
    {
        if (!is_array($departmentIds)) {
            $departmentIds = explode(',', $departmentIds);
        }
        $this->_db->query("DELETE FROM issuelog_dep WHERE issuelog_dep.issuelog_id = " . $this->get('id'));
        $this->addDepartments($departmentIds);

        return $this->getDepartments();
    }

    /**
     * @description Adding issue departments
     * @return array
     * @var array $departmentIds - array of deprtment ids
     */
    public function addDepartments($departmentIds)
    {
        if (count($departmentIds) > 0) {
            foreach ($departmentIds as $departmentId) {
                if ($departmentId > 0) {
                    $tableName = 'issuelog_dep';
                    $attributes = [
                        'issuelog_id' => $this->get('id'),
                        'department_id' => $departmentId,
                    ];
                    $model = new Model($tableName, $attributes);
                    $model->update();
                }
            }
        }

        return $departmentIds;
    }

    /**
     * @description Getting issue departments
     */
    public function getDepartments()
    {
        $q = "SELECT * FROM issuelog_dep WHERE issuelog_dep.issuelog_id = " . $this->get('id');
        $result = $this->_db->getAll($q);
        $departmentIds = [];
        foreach ($result as $item) {
            $departmentIds[] = $item->department_id;
        }

        return $departmentIds;
    }

    /**
     * @description Replacing issue types
     * @var array $issueTypes - array of issue type ids
     */
    public function replaceIssueTypes($issueTypes)
    {
        $this->_db->query("DELETE FROM issuelog_type WHERE issuelog_id = " . $this->get('id'));
        $this->addIssueTypes($issueTypes);
    }

    /**
     * @description Adding comment for issue
     *
     * @param $message
     * @param string $obj
     */
    public function addComment($message, $obj = 'issuelog')
    {
        $q = "INSERT INTO comments 
              SET content = '" . mysql_real_escape_string($message) . "', 
                  obj = '" . mysql_real_escape_string($obj) . "', 
                  obj_id = " . $this->get('id');
        $this->_db->query($q);
    }

    /**
     * @description Adding file for issue
     * @var $file_name
     * @var $tmp_name
     * @var $mime_type
     */
    public function addFile($file_name, $tmp_name, $mime_type)
    {
        $image_mime_types = [
            'image/gif',
            'image/jpeg',
            'image/jpg',
            'image/png',
        ];

        $video_mime_types = [
            'video/mp4',
            'video/ogg',
            'video/webm',
        ];

        if (in_array($mime_type, $image_mime_types)) {
            $type = 'pic';
        } elseif (in_array($mime_type, $video_mime_types)) {
            $type = 'video';
        } else {
            $type = 'doc';
        }

        $pic = file_get_contents($tmp_name);

        $md5 = (new UploadFiles())->upload($pic);

        //        $md5 = md5($pic);
        //        $filepath = set_file_path($md5);
        //        if (!is_file($filepath)) {
        //            file_put_contents($filepath, $pic);
        //        }

        $tableName = 'issue_pic';
        $attributes = [
            'issuelog_id' => $this->get('id'),
            'name' => mysql_real_escape_string($file_name),
            'hash' => $md5,
            'type' => $type,
        ];
        $model = new Model($tableName, $attributes);
        $model->update();
    }

    /**
     * @description Getting issue type field list
     * @return array
     * @var $show_in_popup - optional
     * @var $inactive - optional
     * @var $issueTypeIds - array if issue type ids (optional)
     */
    public static function getIssueTypeFieldList($issueTypeIds = [], $show_in_popup = -1, $inactive = -1)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        if (is_array($issueTypeIds) && count($issueTypeIds) > 0) {
            $where = " issue_field.type_id IN (" . implode(',', $issueTypeIds) . ") ";
        } else {
            $where = " issue_field.type_id > 0 ";
        }

        if ($show_in_popup >= 0) {
            $where .= " AND issue_field.show_in_popup = " . $show_in_popup;
        }

        if ($inactive >= 0) {
            $where .= " AND issue_field.inactive = " . $inactive . " ";
        }

        $where .= " AND issue_field.name != '' ";

        $q = "SELECT
                  issue_type.name issue_type_name,
                  issue_field.type_id issue_type_id,
                  issue_field.id field_id,
                  issue_field.name field_name,
                  issue_field.show_in_popup,
                  issue_field.inactive,
                  issue_field.article_barcode_field,
                  issue_field.obligatory
              FROM issue_field
                  LEFT JOIN issue_type ON issue_type.id = issue_field.type_id
              WHERE " . $where;

        return $dbr->getAll($q);
    }

    /**
     * @description Getting issue type field list
     *
     * @param $issueFieldId
     * @param $fieldName
     * @param $issueTypeId
     * @param $showInPopup
     * @param $inactive
     * @param $article_barcode_field
     * @param $obligatory
     * @return bool|stdClass
     * @throws Exception
     */
    public static function saveIssueTypeField($issueFieldId, $fieldName, $issueTypeId, $showInPopup, $inactive, $article_barcode_field, $obligatory)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        if (!$fieldName) {
            return false;
        }

        $result = false;
        $tableName = 'issue_field';
        $attributes = [
            'name' => $fieldName,
            'type_id' => $issueTypeId,
            'show_in_popup' => $showInPopup,
            'inactive' => $inactive,
            'article_barcode_field' => $article_barcode_field,
            'obligatory' => $obligatory
        ];
        if ($issueFieldId > 0) {
            $attributes['id'] = $issueFieldId;
        }
        $model = new Model($tableName, $attributes);
        $model->update();
        $fieldId = $model->getID();
        if ($fieldId > 0) {
            $q = "SELECT
                      issue_type.name issue_type_name,
                      issue_field.type_id issue_type_id,
                      issue_field.id field_id,
                      issue_field.name field_name,
                      issue_field.show_in_popup,
                      issue_field.inactive,
                      issue_field.article_barcode_field,
                      issue_field.obligatory
                  FROM issue_field
                      LEFT JOIN issue_type ON issue_type.id = issue_field.type_id
                  WHERE issue_field.id = " . $fieldId . " LIMIT 1";
            $result = $db->getRow($q);
        }

        return $result;
    }

    /**
     * @description Getting issue fiels by type
     * @return array
     * @var $filetype - pic, video, doc
     */
    public function getFiles($filetype)
    {
        global $smarty;

        $issue_id = $this->get('id');
        if (!$issue_id) {
            return [];
        }

        $q = "SELECT
                  ip.*,
                  users.username,
                  tl.Updated as created_time
              FROM issue_pic ip
                  LEFT JOIN total_log tl ON 
                    tl.TableID = ip.id
                    AND " . get_table_name('issue_pic', 'tl') . "
                    AND " . get_field_name('id', 'tl') . "
                  LEFT JOIN users ON users.id= tl.username_id
              WHERE ip.type = '" . $filetype . "' AND 
                    ip.issuelog_id = " . $issue_id;
        $files = $this->_dbr->getAll($q);

        if (count($files) == 0) {
            return [];
        }

        switch ($filetype) {
            case 'pic':
                {
                    $images = [];
                    foreach ($files as $key => $image) {
                        $images[$key]['id'] = $image->id;
                        $images[$key]['name'] = $image->name;
                        $images[$key]['hash'] = $image->hash;
                        $images[$key]['username'] = $image->username;
                        $images[$key]['created_time'] = $image->created_time;
                        $images[$key]['url'] = smarty_function_imageurl([
                            'src' => 'issue',
                            'ext' => pathinfo($image->name, PATHINFO_EXTENSION),
                            'picid' => $image->id,
                            'x' => 200,
                        ], $smarty);
                    }
                    $files = $images;
                    break;
                }
            case 'video':
                {
                    $videos = [];
                    foreach ($files as $key => $video) {
                        $videos[$key]['name'] = $video->name;
                        $videos[$key]['hash'] = $video->hash;
                        $videos[$key]['username'] = $video->username;
                        $videos[$key]['created_time'] = $video->created_time;
                        $videos[$key]['url'] = "/doc.php?issue_doc_id=" . $video->id;
                        $videos[$key]['id'] = $video->id;
                    }
                    $files = $videos;
                    break;
                }
            case 'doc':
                {
                    $docs = [];
                    foreach ($files as $key => $file) {
                        $docs[$key]['name'] = $file->name;
                        $docs[$key]['hash'] = $file->hash;
                        $docs[$key]['username'] = $file->username;
                        $docs[$key]['created_time'] = $file->created_time;
                        $docs[$key]['url'] = "/doc.php?issue_doc_id=" . $file->id;
                        $docs[$key]['id'] = $file->id;
                    }
                    $files = $docs;
                    break;
                }
        }

        return $files;
    }

    /**
     * @description Add/edit issue checkpoint
     * @return int|null
     * @var array $data - checkpoint info, fields:
     *      (issuelog_id, checkpoint_id, description, ordering, done)
     * @var $issue_id
     */
    public function saveCheckpoint($data)
    {
        if ($data['ordering'] == 0) {
            $q = "SELECT MAX(ordering) FROM issue_checkpoint 
                  WHERE issuelog_id = " . $this->get('id');
            $max_ordering = $this->_db->getOne($q);
            $data['ordering'] = (int)$max_ordering + 1;
        }

        if ($data['id'] > 0) {
            $is_new = false;
        } else {
            $is_new = true;
            unset($data['id']);
        }

        $model = new Model('issue_checkpoint', $data, $is_new);
        $model->update();

        return $model->getID();
    }

    /**
     * @description Getting issue checkpoint
     * @return array|stdClass
     * @var $checkpoint_id
     */
    public function getCheckpoint($checkpoint_id)
    {
        $checkpoint_info = [];
        if ($checkpoint_id > 0) {
            try {
                $checkpoint_info = $this->_db->getRow("
                    SELECT * 
                    FROM prologis2.issue_checkpoint 
                    WHERE id = $checkpoint_id");
            } catch (ModelNotFoundException $ex) {
                // Expect exception
            }
        }

        return $checkpoint_info;
    }

    /**
     * @description Save additional field for issue
     * @return int|null
     * @var $value
     * @var $fieldId
     */
    public function saveAdditionalField($fieldId, $value)
    {
        $tableName = 'issue_field_value';
        $issueId = $this->get('id');
        $isNew = true;

        $result = Model::firstBy($tableName, [
            'issue_id' => $issueId,
            'issue_field_id' => $fieldId,
        ]);

        $data = [
            'value' => $value,
            'issue_field_id' => $fieldId,
            'issue_id' => $issueId,
        ];

        if ($result && $result->get('id') > 0) {
            $data['id'] = $result->get('id');
            $isNew = false;
        }

        $model = new Model($tableName, $data, $isNew);
        $model->update();

        return $model->getID();
    }

    /**
     * @description Getting additional fields for issue
     */
    public function getAdditionalFields()
    {
        $car_repair_fields = $additional_fields = [];

        // cars
        $q = "SELECT cars.name, car_repair.* FROM car_repair
                    LEFT JOIN cars ON cars.id = car_repair.car_id 
                  WHERE car_repair.issuelog_id = " . $this->get('id');
        $result = $this->_dbr->getAll($q);
        if (count($result) > 0) {
            $fxrates = getRates();
            foreach ($result as $cr) {
                $car_repair_fields[] = [
                    'car_id' => $cr->car_id,
                    'name' => $cr->name,
                    'parts' => $cr->parts,
                    'price' => $cr->price,
                    'currency' => $cr->currency,
                    'price_eur' => number_format($cr->price * $fxrates[$cr->currency . 'US'] / $fxrates['EURUS'], 2),
                ];
            }
        }

        // other additional fields from `issue_field_value` table
        $q = "SELECT
                  issue_type.name as type_name,
                  issue_field.id as field_id,
                  issue_field.name as field_name,
                  issue_field_value.value as field_value,
                  issue_field.show_in_popup,
                  issue_field.inactive,
                  issue_field.article_barcode_field,
                  (
                    SELECT
                        CONCAT( 'changed by ', users.username, ' on ', tl.Updated)
                    FROM total_log tl
                        LEFT JOIN users ON users.id = tl.username_id
                    WHERE
                        `tl`.TableID = issue_field_value.id AND
                        " . get_table_name('issue_field_value', 'tl') . " AND 
                        " . get_field_name('value', 'tl') . "
                    ORDER BY tl.Updated DESC LIMIT 1
                  ) as `changed`
                FROM issue_field
                  LEFT JOIN issuelog_type ON issue_field.type_id = issuelog_type.type_id
                  LEFT JOIN issue_type ON issue_type.id = issue_field.type_id
                  LEFT JOIN issue_field_value ON issue_field_value.issue_field_id = issue_field.id AND
                                                 issue_field_value.issue_id = issuelog_type.issuelog_id
                WHERE issue_field.name != '' AND
                      issuelog_type.issuelog_id = " . $this->get('id') . " AND
                      issue_field.inactive = 0
                GROUP BY issue_field.id";
        $result = $this->_dbr->getAll($q);

        if (count($result) > 0) {
            foreach ($result as $item) {
                $additional_fields[$item->type_name][] = [
                    'field_id' => $item->field_id,
                    'name' => $item->field_name,
                    'value' => $item->field_value,
                    'show_in_popup' => $item->show_in_popup,
                    'inactive' => $item->inactive,
                    'changed' => $item->changed,
                    'article_barcode_field' => $item->article_barcode_field
                ];
            }
        }

        return [
            'car_repair' => $car_repair_fields,
            'additional_fields' => $additional_fields,
        ];
    }

    /**
     * get allowed to change issue ping, status, department and responsible person in issue
     * @return boolean
     * @var $issue_id
     *
     */
    public static function checkEditAccess($issue_id)
    {
        global $loggedUser;
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $allow_change_filds = false;
        if ($issue_id > 0) {
            $q = "SELECT
                  users.username,
                  u1.username direct_username,
                  u2.username main_username
                FROM total_log tl
                JOIN users ON users.id = tl.username_id
                LEFT JOIN employee e ON e.username = users.username
                LEFT JOIN emp_department ed ON ed.id = e.department_id
                LEFT JOIN users u1 on u1.username = ed.direct_super_username
                LEFT JOIN users u2 on u2.username = ed.main_super_username
                WHERE
                    " . get_table_name('issuelog', 'tl') . "
                    AND " . get_field_name('id', 'tl') . "
                    AND tl.TableID = " . $issue_id;

            $usernames = $dbr->getRow($q);

            if ($loggedUser->get('username')) {
                if ($usernames->username == $loggedUser->get('username') || $usernames->direct_username == $loggedUser->get('username') || $usernames->main_username == $loggedUser->get('username') || $loggedUser->get('admin')) {
                    $allow_change_filds = true;
                }
            }
        }

        return $allow_change_filds;
    }


    /**
     * get allowed to change issue ping, status, department and responsible person in issue
     * @return boolean
     * @var $issue_id
     *
     */
    public static function checkPingEditAccess($issue_id)
    {
        global $loggedUser;

        $allow_change_ping = false;
        if ($issue_id > 0) {
            $issue = new self($issue_id);
            $reporting_person = $issue->getReportingPerson($issue_id);

            if ($loggedUser->get('username')) {
                if ($reporting_person->username == $loggedUser->get('username') || $loggedUser->get('admin')) {
                    $allow_change_ping = true;
                }
            }
        }

        return $allow_change_ping;
    }

    /**
     * @description Saving issue status
     * @return array
     * @var $status
     */
    public function saveStatus($status, $notCheckEditAccess = false)
    {
        global $siteURL;
        $issue_id = $this->get('id');
        $result = [];

        /*
         * Check can user change issue status
         */
        if (self::checkEditAccess($issue_id) || $notCheckEditAccess) {

            $issue = new self($issue_id);
            $issue->set('status', $status);
            $issue->update();

            $issue_log_status = $issue->get('status');
            $issue_totallog = getChangeDataLog('issuelog', 'status', $issue_id);
            $issue_totallog = $issue_totallog[0];

            $issue_info = self::findById($issue_id);
            $issue_info = $issue_info['issue_list'][0];

            $tags = '';
            if ($issue_info->issue_type) {
                foreach ($issue_info->issue_type as $tag) {
                    $tags .= $tag->name . ' ';
                }
            }

            /**
             * @description if issue closed we send email to person who opened it
             * and save comment to data base
             */
            if ($issue_log_status !== '') {
                $reporting_person = $this->getReportingPerson($issue_id);
                $last_comment = $this->_dbr->getRow("SELECT comments.content, comments.id
                                FROM comments
                                WHERE comments.obj_id = $issue_id
                                  AND comments.obj IN ('issuelog')
                                ORDER BY comments.id DESC
                                LIMIT 1;");

                $last_comment_info = $this->_dbr->getRow("
                        SELECT
                          total_log.Updated
                          ,users.name
                        FROM total_log
                        LEFT JOIN users ON users.id = total_log.username_id
                        WHERE
                          table_name_id = get_table___name('comments') AND
                            total_log.TableID = $last_comment->id;
                    ");

                if (isset($reporting_person->email)) {
                    $params = new stdClass();
                    $params->issuelog_id = $issue_id;
                    $params->closed_by = $issue_totallog->name;
                    $params->closed_on = $issue_totallog->Updated;
                    $params->url = $siteURL . "react/logs/issue_logs/" . $issue_id . "/";
                    $params->email_invoice = $reporting_person->email;
                    $params->txnid = 'issuelog';
                    $params->auction_number = $issue_id;
                    $params->departments = $issue_info->department_name;
                    $params->rep_person = $issue_info->added_person;
                    $params->description = $issue_info->issue;
                    $params->tags = $tags;
                    $params->issue_department = $issue_info->department_name;
                    $params->reporting_person = $reporting_person->name;
                    $params->issue_last_comment = $last_comment->content;
                    $params->issue_last_comment_date = $last_comment_info->Updated;
                    $params->issue_last_comment_author = $last_comment_info->name;

                    if ($issue_log_status == 'close') {
                        standardEmail($this->_db, $this->_dbr, $params, 'issue_closed');
                    }
                    if ($issue_log_status == 'open') {
                        standardEmail($this->_db, $this->_dbr, $params, 'issue_opend');
                    }
                }

                // send email to close_notif subscribers
                // see button 'close notification' on single issue page
                $subs = self::getCloseNotifSubs($issue_id);

                if (count($subs) > 0) {
                    foreach ($subs as $sub_email) {
                        if ($sub_email && $reporting_person->email != $sub_email) {
                            $params = new stdClass();
                            $params->issuelog_id = $issue_id;
                            $params->closed_by = $issue_totallog->name;
                            $params->closed_on = $issue_totallog->Updated;
                            $params->url = $siteURL . "react/logs/issue_logs/" . $issue_id . "/";
                            $params->email_invoice = $sub_email;
                            $params->txnid = 'issuelog';
                            $params->auction_number = $issue_id;
                            $params->departments = $issue_info->department_name;
                            $params->rep_person = $issue_info->added_person;
                            $params->description = $issue_info->issue;
                            $params->tags = $tags;
                            $params->issue_department = $issue_info->department_name;
                            $params->reporting_person = $reporting_person->name;
                            $params->issue_last_comment = $last_comment->content;
                            $params->issue_last_comment_date = $last_comment_info->Updated;
                            $params->issue_last_comment_author = $last_comment_info->name;
                            if ($issue_log_status == 'close') {
                                standardEmail($this->_db, $this->_dbr, $params, 'issue_closed');
                            }
                            if ($issue_log_status == 'open') {
                                standardEmail($this->_db, $this->_dbr, $params, 'issue_opend');
                            }
                        }
                    }
                }
                if ($issue_log_status === 'close') {
                    $comment = "Issue closed on " . $issue_totallog->Updated . " by " . $issue_totallog->name;
                }
                if ($issue_log_status === 'open') {
                    $comment = "Issue opened on " . $issue_totallog->Updated . " by " . $issue_totallog->name;
                }
                $issue->addComment($comment, 'issuelog_comment');
            }

            // response for js_backend
            $result['username'] = $issue_totallog->name;
            $result['updated'] = $issue_totallog->Updated;
        }

        return $result;
    }


    /**
     * @description Getting issue reporting person
     * @return array|stdClass
     * @var $issue_id
     */
    public function getReportingPerson($issue_id)
    {
        $result = [];
        if ($issue_id > 0) {
            $q = "SELECT users.email, users.username, users.system_username, users.name
              FROM total_log
              LEFT JOIN users ON users.id = total_log.username_id
              WHERE total_log.TableID = $issue_id
                  AND " . get_table_name('issuelog') . "
                  AND " . get_field_name('id');
            $result = $this->_dbr->getRow($q);
        }

        return $result;
    }


    /**
     * @description Normalize ordering
     * @return
     * @var $checklist
     * @var $issue_id
     */
    public static function checklistReorder($issue_id, $checklist)
    {
        if ($checklist > 0 && $issue_id > 0) {
            $issue = new self($issue_id);

            foreach ($checklist as $k => &$point) {
                $new_ordering = $k + 1;
                if ($point->ordering != $new_ordering) {
                    $point->ordering = $new_ordering;
                    $issue->saveCheckpoint([
                        'id' => $point->id,
                        'issuelog_id' => $issue_id,
                        'description' => $point->description,
                        'ordering' => $point->ordering,
                        'done' => $point->done,
                    ]);
                }
            }
        }

        return $checklist;
    }


    /**
     * @description Getting checklist
     * @return array
     * @var $issue_id
     */
    public static function getChecklist($issue_id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $q = "SELECT * FROM issue_checkpoint WHERE issuelog_id = " . $issue_id . " ORDER BY ordering, id ASC";

        return $dbr->getAll($q);
    }


    /**
     * @description Import checklist from another issue
     * @var $issue_id_from
     * @var $issue_id_to
     */
    public static function importChecklist($issue_id_from, $issue_id_to)
    {
        $checklist = self::getChecklist($issue_id_from);
        if (count($checklist) > 0) {
            $issue = new self($issue_id_to);
            $ordering = $issue->getChecklistMaxOrdering();
            foreach ($checklist as $checkpoint) {
                $ordering++;
                $issue->saveCheckpoint([
                    'id' => 0,
                    'issuelog_id' => $issue_id_to,
                    'description' => mysql_real_escape_string($checkpoint->description),
                    'ordering' => $ordering,
                    'done' => 0,
                ]);
            }
        }
    }

    /**
     * @description Getting checkpoint max ordering
     */
    public function getChecklistMaxOrdering()
    {
        $q = "SELECT MAX(issue_checkpoint.ordering) FROM issue_checkpoint WHERE issue_checkpoint.issuelog_id = " . $this->get('id');

        return $this->_dbr->getOne($q);
    }

    /**
     * @description Duplicating the issue
     * @return int|null
     * @var $params
     * @var $issue_id
     */
    public static function duplicateIssue($issue_id, $params)
    {
        global $loggedUser;
        global $siteURL;

        $issue = new self($issue_id);
        $departments = $issue->getDepartments();
        $tags = $issue->getTags();
        $description = $params['description'] ? $issue->get('issue') : '';

        /*
         * Fill Details
         */
        if ($params['details']) {
            $due_date = $issue->get('due_date');
            $resp_username = $issue->get('resp_username');
            $solving_resp_username = $issue->get('solving_resp_username');
            $obj = $issue->get('obj');
            $obj_id = $issue->get('obj_id');
            $recurring = $issue->get('recurring');

        } else {
            $due_date = null;
            $resp_username = $loggedUser->username;
            $solving_resp_username = null;
            $obj = 'manual';
            $obj_id = 0;
            $recurring = 0;

        }

        if ($params['checklist']) {
            $checklist_title = $issue->get('checklist_title');
        } else {
            $checklist_title = '';
        }

        /*
         *  Create new issue
         */
        $issueNew = new self([
            'issue' => $description,
            'resp_username' => $resp_username,
            'solving_resp_username' => $solving_resp_username,
            'obj' => $obj,
            'recurring' => $recurring,
            'due_date' => $due_date,
            'obj_id' => $obj_id,
            'checklist_title' => $checklist_title,
        ]);



        $issueNew->update();
        $id = $issueNew->getID();

        /*
         *  Fill additional info after creating issue
         */


        if ($id > 0) {
            $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
            $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

            if ($params['details']) {
                $issueNew->saveDepartments($departments);
            }

            if ($params['tags']) { // issue types
                $issue_types = (count($tags) > 0) ? $tags : [Config::getValue('default_issue_type')];
                $issueNew->addIssueTypes($issue_types);
            }

            //TODO Duplicate additional fields? see saveAdditionalField

            /*
             *  Duplicate comments
             */
            if ($params['comments']) {
                $comments = self::getComments($issue_id);
                if (count($comments) > 0) {
                    foreach ($comments as $comment) {
                        $issueNew->addComment($comment->comment, $comment->obj);
                    }
                }
            }

            /*
             * Duplicate checklist
             */
            if ($params['checklist']) {
                $checklist = self::getChecklist($issue_id);
                if (count($checklist) > 0) {
                    foreach ($checklist as $checkpoint) {
                        $issueNew->saveCheckpoint([
                            'id' => 0,
                            'issuelog_id' => $id,
                            'description' => $checkpoint->description,
                            'ordering' => $checkpoint->ordering,
                            'done' => $checkpoint->done,
                        ]);
                    }
                }
            }

            /*
             * Duplicate images
             */
            if ($params['images']) {
                $images = $issue->getFiles('pic');
                if (count($images) > 0) {
                    foreach ($images as $image) {
                        $model = new Model('issue_pic', [
                            'issuelog_id' => $id,
                            'name' => $image['name'],
                            'hash' => $image['hash'],
                            'type' => 'pic',
                        ]);
                        $model->update();
                    }
                }
            }

            /*
             * Duplicate files
             */
            if ($params['files']) {
                $docs = $issue->getFiles('doc');
                if (count($docs) > 0) {
                    foreach ($docs as $doc) {
                        $model = new Model('issue_pic', [
                            'issuelog_id' => $id,
                            'name' => $doc['name'],
                            'hash' => $doc['hash'],
                            'type' => 'doc',
                        ]);
                        $model->update();
                    }
                }
            }

            /*
             * Duplicate videos
             */
            if ($params['videos']) {
                $videos = $issue->getFiles('video');
                if (count($videos) > 0) {
                    foreach ($videos as $video) {
                        $model = new Model('issue_pic', [
                            'issuelog_id' => $id,
                            'name' => $video['name'],
                            'hash' => $video['hash'],
                            'type' => 'video',
                        ]);
                        $model->update();
                    }
                }
            }

            /**
             * also duplicate ping days and user_id into new duplicated issue log
             */
            $issueOld = self::findById($issue_id);
            
            /**
             * duplicating additional fields values,details and barcodes
             */
            $tag_name = $issueOld['issue_list'][0]->issue_type[0]->name;
            $additional_fields = $issueOld['issue_list'][0]->additional_fields[$tag_name];
            
            if($params['details']){
                
                /**
                 * also duplicate ping days and user_id into new duplicated issue log
                 */
                $ping_days = $issueOld['issue_list'][0]->days;
                $user_id = $issueOld['issue_list'][0]->userid;
                
                if(false!==(bool)$ping_days && false!==(bool)$user_id){
                        self::savePingDays($id, $user_id, $ping_days);
                }
                
                if($params['additional_fields']){
                
                    foreach($additional_fields as $field){

                           $issueNew->saveAdditionalField($field['field_id'],$field['value']);

                    }

                    $barcodes = $issue->getIssueBarcodes($issue_id);
                  
                    if (count($barcodes)){

                        foreach ($barcodes as $barcode_id => $barcode_value){

                            if(isset($barcode_value['issue_field_id']) && isset($barcode_id)){ 

                                $issueNew->setIssueBarcode($barcode_id, $barcode_value['issue_field_id']);
                            }
                        }
                    }
                }
            }

            /**
             * send email to person who duplicate issue
             */
            $tags_for_email = $dbr->getOne("SELECT GROUP_CONCAT(name)
                FROM issue_type
                  LEFT JOIN issuelog_type ON issue_type.id = issuelog_type.type_id
                WHERE
                  issuelog_type.issuelog_id=$id");
            $departments_for_email = $dbr->getOne("SELECT GROUP_CONCAT(name) FROM issuelog_dep isd 
                LEFT JOIN emp_department ep ON isd.department_id=ep.id WHERE isd.issuelog_id =$id");


            /**
             * send email to person who duplicate issue
             */
            $auction = new stdClass();
            $auction->issuelog_id = $id;
            $auction->oldissue_id = $issue_id;
            $auction->url = $siteURL . "react/logs/issue_logs/$id/";
            $auction->txnid = 'issuelog';
            $auction->auction_number = $id;
            $auction->tags = $tags_for_email;
            $auction->departments = $departments_for_email;
            $auction->rep_person = $issueNew->get('resp_username');
            $auction->description = $issueNew->get('issue');
            $auction->template = 'issue_opend';
            $auction->duplicate = 1;

            $duplicate_author_email = $dbr->getOne("SELECT email FROM users WHERE username = '" . $loggedUser->username . "' ");
            if ($duplicate_author_email) {
                $auction->email= $duplicate_author_email;
                $auction->email_invoice = $duplicate_author_email;
                label\MessageProcess::process($auction->template, $auction);
            }


        }

        return $id;
    }

    /**
     * @description Getting issue types
     */
    public function getTags()
    {
        $q = "SELECT
                  issue_type.id
                FROM issue_type
                  LEFT JOIN issuelog_type ON issue_type.id = issuelog_type.type_id
                WHERE
                  issuelog_type.issuelog_id = " . $this->get('id');
        $result = $this->_db->getAll($q);
        $tags = [];
        foreach ($result as $item) {
            $tags[] = $item->id;
        }

        return $tags;
    }
    
         /**
     * @description Getting issue tag names
     */
    public function getTagNames()
    {
        $tag_names = $this->_dbr->getOne("SELECT GROUP_CONCAT(name)
                FROM issue_type
                  LEFT JOIN issuelog_type ON issue_type.id = issuelog_type.type_id
                WHERE
                  issuelog_type.issuelog_id={$this->get('id')}"); 
                  
        return $tag_names;          
    }
    
    
    /**
     * @description Getting issue comments
     * @param $issue_id
     * @return array
     * @throws Exception
     */
    public static function getComments($issue_id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $q = "SELECT
                  comments.id AS comment_id
                  , comments.content AS comment
                  , comments.obj_id AS issue_id
                      , comments.obj AS obj
                  , users.username AS username
                  , total_log.Updated AS create_date
                FROM comments
                  LEFT JOIN total_log ON total_log.TableID = comments.id
                         AND " . get_table_name('comments') . "
                         AND " . get_field_name('id') . "
                  LEFT JOIN users ON users.id = total_log.username_id
                WHERE comments.content != '' AND comments.obj_id = " . $issue_id . "
                ORDER BY create_date ASC";

        return $dbr->getAll($q);
    }


    /**
     * @description Save issue filter settings
     *
     * @return int|null
     * @var $title - filter title
     * @var $filter_set - json data
     * @var $filter_id - int, required for update? 0 for new record
     */
    public static function saveFilterSettings($filter_id = 0, $title, $filter_set)
    {
        $isNew = true;
        $data = [
            'title' => $title,
            'filter_set' => $filter_set,
            'page' => 'issue_logs',
        ];

        if ($filter_id > 0) {
            $data['id'] = $filter_id;
            $isNew = false;
        }

        $model = new Model('saved_filters', $data, $isNew);
        $model->update();

        return $model->getID();
    }

    /**
     * @description Save issue ping days
     *
     * @return int|string|null
     * @var $user_id
     * @var $days - days with no activity in issue
     * @var $issue_id
     */
    public static function savePingDays($issue_id, $user_id, $days)
    {
        $issue = new self($issue_id);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $q = "SELECT id FROM issue_ping_days WHERE issuelog_id = " . $issue_id . " LIMIT 1";
        $id = $dbr->getOne($q);
        $isNew = true;
        $data = [
            'issuelog_id' => $issue_id,
            'user_id' => $user_id,
            'days' => $days,
        ];
        if ($id > 0) {
            $data['id'] = $id;
            $isNew = false;
        }
        $model = new Model('issue_ping_days', $data, $isNew);
        $model->update();
        $id = $model->getID();
        $issue->addComment("Set ping days: $days", "issuelog_comment");

        return $id;
    }

    /**
     * @description set issue's ping common days
     * @param integer $days
     * @return integer
     */
    public static function saveCommonPingDays($days)
    {
        Config::setValue("issue_common_ping_days", $days);
        Config::setValue("issue_common_ping_days_set_date", date("Y-m-d"));

        return $days;
    }

    /**
     * @description Toogle issue close_notif option for user
     *
     * @return int|string
     * @var $username
     * @var $issue_id
     */
    static function closeNotifToogle($issue_id, $username)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        $q = "SELECT id FROM close_notif WHERE obj_id = " . $issue_id . " AND username = '" . $username . "' AND obj = 'issuelog'";
        $id = $dbr->getOne($q);

        if ($id > 0) {
            $db->query("DELETE FROM close_notif WHERE id = " . $id . " LIMIT 1");
            $response = 0;
        } else {
            $data = [
                'obj_id' => $issue_id,
                'username' => $username,
                'obj' => 'issuelog',
            ];
            $model = new Model('close_notif', $data, true);
            $model->update();
            $id = $model->getID();

            if ($id > 0) {
                $response = 1;
                //$response = $dbr->getRow("SELECT * FROM close_notif WHERE id = " . $id . " LIMIT 1");
            } else {
                $response = 'error';
            }
        }

        return $response;
    }

    /**
     * @description Send issue close notification to subscribers emails
     *
     * @return array|void
     * @var $issue_id
     */
    static function getCloseNotifSubs($issue_id)
    {
        global $loggedUser;
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        if (!$loggedUser->username) {
            return;
        }

        $emails = [];

        $q = "SELECT
                  close_notif.*,
                  users.email
                FROM close_notif
                  LEFT JOIN users ON users.username = close_notif.username
                WHERE close_notif.obj_id = " . $issue_id . " AND
                      close_notif.obj = 'issuelog'";
        $subs = $dbr->getAll($q);

        if (count($subs) > 0) {
            foreach ($subs as $sub) {
                if ($sub->email) {
                    $tmp_emails = explode(',', $sub->email);
                    if (count($tmp_emails) > 0) {
                        foreach ($tmp_emails as $email) {
                            $emails[] = $email;
                        }
                    }
                }
            }
        }

        return $emails;
    }

    /**
     * @description get chnages in ping days
     * @param int $ping_id
     * @return object
     */
    static function getPingLog($ping_id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $q = "select u.name AS username, tl.Updated
            from prologis2.total_log tl
            left join users u on u.id=tl.username_id
            where
                tl.tableid=" . $ping_id . "
                AND " . get_table_name('issue_ping_days', 'tl') . "
                AND " . get_field_name('days', 'tl') . "
            order by tl.Updated desc limit 1";

        return $dbr->getRow($q);
    }

    /**
     * @description get ping autoincriment id
     * @param int $issuelog_id
     * @return int
     */
    static function getPingId($issuelog_id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $id = $dbr->getOne("SELECT id FROM prologis2.issue_ping_days WHERE issuelog_id = " . $issuelog_id);

        return $id;
    }

    /**
     * @description add articles to issues
     * @param integer $article_id
     * @param integer $issue_field_id
     * @return void
     */
    public function setIssueArticle($article_id, $issue_field_id)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $article_id = (int)$article_id;
        $issue_field_id = (int)$issue_field_id;
        $db->query("INSERT INTO issuelog_articles SET issuelog_id = " . $this->get('id') . ", article_id = " . $article_id . ", issue_field_id = " . $issue_field_id);
    }

    /**
     * @description add article qty
     * @param integer $article_id
     * @param integer $qty
     * @param integer $issue_field_id
     * @return void
     */
    public function setIssueArticleQty($article_id, $qty, $issue_field_id)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $article_id = (int)$article_id;
        $qty = (int)$qty;
        $issue_field_id = (int)$issue_field_id;
        $db->query("UPDATE issuelog_articles SET article_qty = " . $qty . " WHERE issuelog_id = " . $this->get('id') . " AND article_id = " . $article_id . " AND issue_field_id = " . $issue_field_id);
    }

    /**
     * @description add barcodes to issues
     * @param integer $barcode_id
     * @param integer $issue_field_id
     * @return void
     */
    public function setIssueBarcode($barcode_id, $issue_field_id)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $barcode_id = (int)$barcode_id;
        $issue_field_id = (int)$issue_field_id;
        $db->query("INSERT INTO issuelog_barcodes SET issuelog_id = " . $this->get('id') . ", barcode_id = " . $barcode_id . ", issue_field_id = " . $issue_field_id);
    }

    /**
     * @description delete article or barcode related to issue
     * @param string $type
     * @param integer $item_id
     * @param integer $issue_field_id
     * @return void
     */
    public function deleteBarcodeArticle($type, $item_id, $issue_field_id)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $item_id = (int)$item_id;
        $issue_field_id = (int)$issue_field_id;
        if ($type === 'barcode') {
            $db->query("DELETE FROM issuelog_barcodes WHERE issuelog_id = " . $this->get('id') . " AND barcode_id = " . $item_id . " AND issue_field_id = " . $issue_field_id);
        } else {
            $db->query("DELETE FROM issuelog_articles WHERE issuelog_id = " . $this->get('id') . " AND article_id = " . $item_id . " AND issue_field_id = " . $issue_field_id);
        }
    }

    /**
     * @description get barcodes related to issue
     * @param integer $issue_id
     * @return array
     */
    public static function getIssueBarcodes($issue_id)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $issue_id = (int)$issue_id;
        $barcodes = [];
        $items = $db->getAll("SELECT barcode_dn.id, barcode_dn.barcode, ib.issue_field_id
                                          FROM issuelog_barcodes AS ib
                                          LEFT JOIN barcode_dn ON barcode_dn.id = ib.barcode_id
                                          WHERE issuelog_id = " . $issue_id);
        foreach ($items as $item) {
            $barcodes[$item->id]['number'] = $item->barcode;
            $barcodes[$item->id]['issue_field_id'] = $item->issue_field_id;
        }

        return $barcodes;
    }

    /**
     * @description get articles related to issue
     * @param integer $issue_id
     * @return array
     */
    public static function getIssueArticles($issue_id)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $issue_id = (int)$issue_id;
        $articles = [];
        $items = $db->getAll("SELECT article.iid, article.article_id, ia.article_qty, ia.issue_field_id
                                          FROM issuelog_articles AS ia
                                          LEFT JOIN article ON article.iid = ia.article_id
                                          WHERE issuelog_id = " . $issue_id);
        foreach ($items as $item) {
            $articles[$item->iid]['number'] = $item->article_id;
            $articles[$item->iid]['qty'] = $item->article_qty;
            $articles[$item->iid]['issue_field_id'] = $item->issue_field_id;
        }

        return $articles;
    }

    /**
     * change barcodes or articles related to the issue via ajax
     * @param array $articles_barcodes_fields
     * @return void
     */
    public function changeBarcodeArticleAjax($articles_barcodes_fields)
    {
        $result = [];
        foreach ($articles_barcodes_fields as $additional) {
            $issue_field_id = (int)$additional['field_id'];

            foreach ($additional['barcodes'] as $item) {
                if (strpos($item, '/') !== false) {
                    $barcode = new Barcode($this->_db, $this->_dbr, $item);
                    $barcode_iid = $barcode->get('id');
                    if (!strlen($barcode_iid)) {
                        $this->_result['error'] = 'Barcode is not existed';
                        $this->output();
                    }
                    $this->setIssueBarcode($barcode_iid, $issue_field_id);
                    $result['success'][] = "Barcode $item is added!";
                } else {
                    $result['error'][] = "Cannot determine input barcode $item!";
                }
            }
            foreach ($additional['articles'] as $item => $article_qty) {
                if (is_numeric($item)) {
                    $article = new Article($this->_db, $this->_dbr, (int)$item);
                    $article_iid = $article->get('iid');
                    if (!strlen($article_iid)) {
                        $result['error'][] = "Cannot determine input article $item!";
                    }
                    $this->setIssueArticle($article_iid, $issue_field_id);
                    $this->setIssueArticleQty($article_iid, $article_qty, $issue_field_id);
                    $this->_result['success'] = 'Article is added!';
                    $types = $this->getTags();
                    /**
                     * if issue type is certain we change issue's responsible to article resposible
                     */
                    if (in_array(15, $types) || in_array(24, $types)) {
                        $purch_employees = $article->getArticleResponsibles();
                        $this->set('resp_username', $purch_employees[0]->username);
                        $this->update();
                        global $siteURL;
                        global $loggedUser;
                        $newuser_email = $this->_db->getOne("
                          SELECT email 
                          FROM users 
                            WHERE username='" . $purch_employees[0]->username . "'");
                        $ret = new stdClass;
                        $ret->from = $loggedUser->get('email');
                        $ret->from_name = $loggedUser->get('name');
                        $ret->email_invoice = $newuser_email;
                        $ret->saved_id = $this->get('id');
                        $ret->auction_number = $this->get('id');
                        $ret->txnid = 'issuelog';
                        $ret->url = $siteURL . "react/logs/issue_logs/{$this->get('id')}/";
                        $ret->email_title = 'Please answer Issue';
                        standardEmail($this->_db, $this->_dbr, $ret, 'new_responsible_issue');
                    }
                    $result['success'][] = "Article $item is added!";
                } else {
                    $result['error'][] = "Cannot determine input article $item!";
                }
            }
        }
    }
}
