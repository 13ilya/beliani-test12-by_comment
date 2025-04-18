<?php

/**
 * @description api react for issue log
 * @author Ilya Khalizov
 * @version 1.0
 * 
 * @var $where
 * @var $this
 * @var $allow_change_filds
 * @var $issue_created_by
 * @var $default_issue_tyoe
 * @var $currentuser_issues
 */

class issueLogController extends apiController
{
    
    /**
     * equels issue id
     */
    private $_page_id;
    
    public function __construct()
    {
        parent::__construct();
        require_once ROOT_DIR . '/plugins/function.imageurl.php';
        if ($this->_input['data']) {
            $data = json_decode($this->_input['data']);
            $this->_page_id = (int)$data->page_id;
        } else {
            $this->_page_id = (int)$this->_input['page_id'];
        }
    }
    
    /**
     * @description get issue's log
     * @return object
     */
    public function listAction()
    {   
        /**
         * @description filter query
        */
        $where = [];
        
        if ($this->_input['status']) {
            $where[] = " issuelog.status = '" . $this->_input['status'] . "'";
        }
        if ($this->_input['type_id']) {
            $types = implode(',', $this->_input['type_id']);
            $where[] = " issuelog_type.type_id IN ($types) ";
        }
        if ($this->_input['show_with_inactive'] == 1) {
            $where[] = " (issuelog.inactive = 1 OR issuelog.inactive = 0) ";
        } else {
            $where[] = " issuelog.inactive = 0 ";
        }
        if ($this->_input['where']) {
            $where[] = " issuelog.obj = '" . $this->_input['where'] . "'";
        }
        if ($this->_input['obj_id']) {
            $where[] = " issuelog.obj_id = " . $this->_input['obj_id'];
        }
        if ($this->_input['page_id']) {
            $where[] = " issuelog.id = " . $this->_input['page_id'];
        }
        if ($this->_input['date_from'] && $this->_input['date_to']) {
            $where[] = "tl.Updated >= '" . $this->_input['date_from'] . " 00:00:00'";
            $where[] = "tl.Updated <= '" . $this->_input['date_to'] . " 23:59:59'";
        } 
        if ($this->_input['date_from'] && !$this->_input['date_to']) {
            $where[] = "tl.Updated >= '" . $this->_input['date_from'] . " 00:00:00'";
        }
        if (!$this->_input['date_from'] && $this->_input['date_to']) {
            $where[] = "tl.Updated <= '" . $this->_input['date_to'] . " 23:59:59'";
        }
        if ($this->_input['department']) {
            $where[] = "issuelog.department_id = '" . $this->_input['department'] . "'";
        }
        if ($this->_input['solving_resp_person']) {
            $where[] = "issuelog.solving_resp_username = '" . $this->_input['solving_resp_person'] . "'";
        }
        if ($this->_input['resp_person']) {
            $where[] = "issuelog.resp_username = '" . $this->_input['resp_person'] . "'";
        }
        if ($this->_input['seller']) {
            $where[] = "(auction.username = '" . $this->_input['seller'] . "' OR auction_rma.username = '" . $this->_input['seller'] . "')";
        }
        if ($this->_input['source_seller']) {
            $where[] = "(auction.source_seller_id = '" . $this->_input['source_seller'] . "' OR auction_rma.source_seller_id = '" . $this->_input['source_seller'] . "')";
        }
        if ($this->_input['shipping_method']) {
            $where[] = "(auction.shipping_method = '" . $this->_input['shipping_method'] . "' OR auction_rma.shipping_method = '" . $this->_input['shipping_method'] . "')";
        }
        if ($this->_input['warehouse_shipped_from']) {
            $where[] = "(orders.send_warehouse_id = '" . $this->_input['warehouse_shipped_from'] . "' OR orders_rma.send_warehouse_id = '" . $this->_input['warehouse_shipped_from'] . "')";
        }
        if ($this->_input['shipping_country']) {
            $where[] = "(auction_par_varchar.value = '" . $this->_input['shipping_country'] . "' OR apv_rma.value = '" . $this->_input['shipping_country'] . "')";
        }
        if ($this->_input['by_comment']) {
            $comment = preg_replace('/\s+/', ' ', $this->_dbr->escape($this->_input['by_comment']));
            /*
            REGEXP_REPLACE(table.field, '\\s+', ' ') поддерживается только в MySQL 8, а текущая БД MySQL 5.6.
            Приходится использовать жуткий костыль из вложенных REPLACE(), который не гарантируют, что в строке не будет пробелов подряд больше 1.
            */
            $where[] = '(
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(issuelog.issue, \'\n\', \' \'), \'\r\', \' \'), \'\t\', \' \'), \'   \', \' \'), \'  \', \' \') LIKE \'%' . $comment . '%\'
               OR EXISTS (
                  SELECT 1
                     FROM `comments`
                  WHERE comments.obj_id = issuelog.id
                     AND LOWER(comments.obj) LIKE \'%issuelog%\'
                     AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(comments.content, \'\n\', \' \'), \'\r\', \' \'), \'\t\', \' \'), \'   \', \' \'), \'  \', \' \') LIKE \'%' . $comment . '%\'
               )
               OR EXISTS (
                  SELECT 1
                     FROM `alarms`
                  WHERE alarms.type_id = issuelog.id
                     AND alarms.type = \'issuelog\'
                     AND alarms.comment LIKE \'%' . $comment . '%\'
               )
            )';
        }

        $this->_result['issue_list'] = $this->_dbr->getAll("            
            SELECT 
                issuelog.*
                , IF(auction.auction_number, auction.auction_number, issuelog.obj_id) AS `number`
                , IF(auction.txnid, auction.txnid, '') AS `txnid`
                , users.`name` AS user_name
                , users_solving.`name` AS solving_user_name
                , empd.`name` AS department_name
                , (
                    SELECT Updated 
                    FROM total_log 
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'recurring' 
                        AND TableID = issuelog.id
                    ORDER BY Updated DESC
                    LIMIT 1
                    ) AS added_time_to_recurring
                , (
                    SELECT users.`name` 
                    FROM total_log 
                    JOIN users ON users.system_username = total_log.username
                    WHERE total_log.`Table_name` = 'issuelog' 
                        AND total_log.Field_name = 'recurring' 
                        AND total_log.TableID = issuelog.id
                    ORDER BY total_log.Updated DESC
                    LIMIT 1
                    ) AS added_person_to_recurring
                , (
                    SELECT comments.content 
                    FROM comments 
                    WHERE comments.obj_id = issuelog.id
                        AND comments.obj = 'issuelog_comment'
                        ORDER BY comments.id DESC 
                        LIMIT 1
                    ) AS last_comment
                , (
                    SELECT comments.id 
                    FROM comments 
                    WHERE comments.obj_id = issuelog.id
                        AND comments.obj = 'issuelog_comment'
                        ORDER BY comments.id DESC 
                        LIMIT 1
                    ) AS last_comment_id
                , (
                    SELECT CONCAT('by ',users.`name`,' on ',total_log.Updated)
                    FROM total_log
                    JOIN users ON users.system_username = total_log.username 
                    WHERE total_log.TableID = last_comment_id
                        AND total_log.`Table_name`= 'comments'
                        AND total_log.Field_name = 'id'
                    ) AS last_comment_added
                , (
                    SELECT CONCAT('by ',users.`name`,' on ',total_log.Updated)
                    FROM total_log
                    JOIN users ON users.system_username = total_log.username 
                    WHERE total_log.TableID = last_comment_id
                        AND total_log.`Table_name`= 'comments'
                        AND total_log.Field_name = 'content'
                        ORDER BY total_log.Updated DESc
                        LIMIT 1
                    ) AS last_comment_updated
                , (
                    SELECT Updated 
                    FROM total_log 
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'id' 
                        AND TableID = issuelog.id
                    ) AS added_time
                , (
                    SELECT users.`name` 
                    FROM total_log 
                    JOIN users ON users.system_username = total_log.username
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'id' 
                        AND TableID = issuelog.id
                    ) AS added_person
                , (
                    SELECT users.`name` 
                    FROM total_log 
                    JOIN users ON users.system_username = total_log.username
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'status'
                        AND TableID = issuelog.id
                    ORDER BY total_log.Updated DESC
                    LIMIT 1
                    ) AS change_by
                , (
                    SELECT Updated
                    FROM total_log 
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'status' 
                        AND TableID = issuelog.id
                    ORDER BY total_log.Updated DESC
                    LIMIT 1 
                    ) AS change_time
                , 
                (SELECT GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(Updated, ' ', 1))
                FROM total_log 
                WHERE `Table_name` = 'issuelog' 
                    AND Field_name = 'status'
                    AND New_value = 'open'
                    AND TableID = issuelog.id
                    ORDER BY Updated) AS open_dates              
                , (SELECT GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(Updated, ' ', 1))
                FROM total_log 
                WHERE `Table_name` = 'issuelog' 
                    AND Field_name = 'status' 
                    AND TableID = issuelog.id
                    AND New_value != 'open'
                    ORDER BY Updated) AS not_open_dates
                , issuelog.obj,
                    CASE obj
                        WHEN 'shop_cat' THEN 'Shop catalogue: '
                        WHEN 'shipping_plan' THEN 'Shipping plan: '
                        WHEN 'condensed_sa' THEN 'SA: '
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
                    END AS where_did,
                    CASE obj
                        WHEN 'shop_cat' THEN CONCAT('shop_cat.php?cat_id=',issuelog.obj_id)
                        WHEN 'shipping_plan' THEN CONCAT('shipping_plan.php?id=',issuelog.obj_id)
                        WHEN 'condensed_sa' THEN CONCAT('react/condensed/condensed_sa/',issuelog.obj_id,'/')
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
                    END AS url
            FROM issuelog
            LEFT JOIN emp_department empd ON empd.id = issuelog.department_id
            LEFT JOIN users ON users.username = issuelog.resp_username
            LEFT JOIN users users_solving ON users_solving.username = issuelog.solving_resp_username
            LEFT JOIN issue_type ON issue_type.id = issuelog.type_id
            LEFT JOIN auction ON auction.id = issuelog.obj_id AND issuelog.obj = 'auction'
            LEFT JOIN rma ON rma.rma_id = issuelog.obj_id AND issuelog.obj = 'rma'
            LEFT JOIN auction auction_rma ON rma.auction_number = auction_rma.auction_number AND rma.txnid = auction_rma.txnid
            LEFT JOIN orders ON auction.auction_number = orders.auction_number and auction.txnid = orders.txnid
            LEFT JOIN orders orders_rma ON rma.auction_number = orders_rma.auction_number and rma.txnid = orders_rma.txnid
            LEFT JOIN auction_par_varchar ON auction.auction_number = auction_par_varchar.auction_number AND auction.txnid = auction_par_varchar.txnid AND auction_par_varchar.`key` = 'country_shipping'
            LEFT JOIN auction_par_varchar apv_rma ON rma.auction_number = apv_rma.auction_number AND rma.txnid = apv_rma.txnid AND apv_rma.`key` = 'country_shipping'
            LEFT JOIN total_log tl ON tl.`Table_name` = 'issuelog' AND tl.Field_name = 'id' AND tl.TableID = issuelog.id
            LEFT JOIN issuelog_type ON issuelog_type.issuelog_id = issuelog.id
                WHERE 1 " . ($where ? ' AND ' . implode(' AND ', $where) : '') . "
                    GROUP BY added_time
                    ORDER BY added_time DESC");

        $this->_result['allow_change_filds'] = $this->checkPermision();

        /**
         * count pending days, get issue types
         * @var $open_dates
         * @var $not_open_dates
         * @var $datetime1
         * @var $datetime2
         * @var $interval
         * @var $types
         */
        foreach ($this->_result['issue_list'] as $key=>$issue) {
            $types = $this->_dbr->getAll("
                select 
                    issuelog_type.type_id id
                    , issue_type.`name`
                from issuelog_type
                left join issue_type on issue_type.id = issuelog_type.type_id
                where issuelog_id = $issue->id");
            $this->_result['issue_list'][$key]->issue_type = [];
            if ($types) {
                $this->_result['issue_list'][$key]->issue_type = $types;
            }
            $this->_result['issue_list'][$key]->days_passed = 0;
            $open_dates = explode(',', $issue->open_dates);
            $open_dates = array_diff($open_dates, array(''));
            $not_open_dates = explode(',', $issue->not_open_dates);
            $not_open_dates = array_diff($not_open_dates, array(''));
            if (count($not_open_dates) < count($open_dates)) {
                array_push($not_open_dates, date('Y-m-d'));
            }
            if (!empty($open_dates) && !empty($not_open_dates)) {
                foreach ($open_dates as $open_date) {
                    foreach ($not_open_dates as $not_open_date) {
                        if ($open_date && $not_open_date) {
                            $datetime1 = new DateTime($open_date);
                            $datetime2 = new DateTime($not_open_date);
                            $interval = $datetime1->diff($datetime2);
                            $this->_result['issue_list'][$key]->days_passed += $interval->format('%a');
                        }
                    }
                }
            }
        }

        /**
         * get allowed to change issue status ids
         */
        $currentuser_issues = $this->_dbr->getAll("
            SELECT issuelog.id 
            FROM issuelog 
            JOIN total_log tl ON tl.`Table_name` = 'issuelog' AND tl.Field_name = 'id' AND tl.TableID = issuelog.id
            JOIN users ON users.system_username = tl.username
            WHERE users.username = '" . $this->_loggedUser->get('username') . "' ");
        
        $this->_result['allow_change_filds_creator'] = $currentuser_issues;
        $this->_result['admin'] = $this->_loggedUser->get('admin');
        $this->output();
    }
     
    /**
     * @description change responsible person
     * @return json
     * 
     * @var $this
     * @var $query
     * @var $page_id
     * @var $resp_person
     * @var $user
     * 
     */ 
    public function changeResponsibleAction()
    {
        if ($this->_input['resp_person']) {
            $resp_person = mysql_real_escape_string($this->_input['resp_person']);
        }

        $query = "UPDATE issuelog SET resp_username = '" . $resp_person . "' WHERE id = " . $this->_page_id;
        if ($this->_db->query($query)) {
            $this->_result['success'] = true;
            $user = $this->_dbr->getAssoc("SELECT username, `name` FROM users WHERE username = '$resp_person'");
            $this->_result['resp_username'] = $user;
            $this->_result['issuelog_id'] = $this->_page_id;
            $this->sendAndLogEmail('responsible');
        } else {
            $this->_result['success'] = false;
        }
        $this->output();
    }
    
    /**
     * @description change solving responsible person
     * @return json
     * 
     * @var $this
     * @var $query
     * @var $page_id
     * @var $resp_person
     * @var $user
     * 
     */ 
    public function changeSolvingResponsibleAction()
    {
        if ($this->checkPermision()) {
            if ($this->_input['solving_resp_person']) {
                $solving_resp_person = mysql_real_escape_string($this->_input['solving_resp_person']);
            }

            $query = "UPDATE issuelog SET solving_resp_username = '" . $solving_resp_person . "' WHERE id = " . $this->_page_id;
            if ($this->_db->query($query)) {
                $this->_result['success'] = true;
                $user = $this->_dbr->getAssoc("SELECT username, `name` FROM users WHERE username = '$solving_resp_person'");
                $this->_result['resp_username'] = $user;
                $this->_result['issuelog_id'] = $this->_page_id;
                $this->sendAndLogEmail('solving');
            } else {
                $this->_result['success'] = false;
            }
            $this->output();
        }
    }
    
    /**
     * @description change department 
     * @return json
     * 
     * @var $this
     * @var $query
     * @var $departament
     * 
     */ 
    public function changeDepartmentAction()
    {
        if ($this->checkPermision()) {
            $department = (int)$this->_input['department'];

            $query = "UPDATE issuelog SET department_id = $department WHERE id = $this->_page_id";
            if ($this->_db->query($query)) {
                $this->_result['success'] = true;
            } else {
                $this->_result['success'] = false;
            }
            $this->output();
        }
    }
    
    /**
     * set due date 
     * @var $due_date
     * @var $this
     */
    public function setDueDateAction() 
    {
        $due_date = mysql_real_escape_string($this->_input['due_date']);
        $this->_db->query("update issuelog set due_date = '$due_date' where id = $this->_page_id");
        $this->_result['success'] = true;
        $this->output();
    }
    
    /**
     * delete due date
     * @var $this
     */
    public function deleteDueDate()
    {
        $this->_db->query("update issuelog set due_date = NULL where id = $this->_page_id");
        $this->_result['success'] = true;
        $this->output();
    }
    
    /**
     * @description change type
     * @return json
     * 
     * @var $this
     * @var $query
     * @var $issue_type_id
     * 
     */ 
    public function changeIssueTypeAction()
    {
        if ($this->checkPermision()) {
            $issue_type_id = $this->_input['issue_type_id'];
            $this->_db->query("delete from issuelog_type where issuelog_id = $this->_page_id");
            foreach ($issue_type_id as $issue_type_id) {
                $this->_db->query("insert into issuelog_type SET type_id = " . (int)$issue_type_id . ", issuelog_id = $this->_page_id");
            }
            $this->_result['success'] = true;
            $this->output();
        }
    }
    
    /**
     * @description save the issue images and files
     * @var $images
     * @var $imgs_qty
     * @var $file_name
     * @var $file_extension
     * @var $type
     * @var $pic
     * @var $md5
     * @var $file
     */
    public function saveImagesAction()
    {
        $images = $_FILES['imgs'] ? $_FILES['imgs'] : [];
        if ($images) {
            $imgs_qty = count($images['tmp_name'])-1;
            if ($imgs_qty >= 0) { 
                for(; $imgs_qty >= 0; $imgs_qty--) {
                    $file_name = $images['name'][$imgs_qty];
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    if (in_array(strtolower($file_extension), array('png', 'gif', 'jpg', 'jpeg'))) {
                        $type = 'pic';
                    } else {
                        $type = 'doc';
                    }
                    $pic = file_get_contents($images['tmp_name'][$imgs_qty]);
                    $md5 = md5($pic);
                    $this->_db->query("
                        INSERT INTO issue_pic 
                        SET 
                            issuelog_id = " . $this->_page_id . ",
                            `name` = '" . mysql_real_escape_string($file_name) . "', 
                            `hash` = '$md5', 
                            `type` = '$type' ");
                    $file = set_file_path($md5);
                    if ( ! is_file($file)) {
                        file_put_contents($file, $pic);
                    }
                }
            }
        }
        $this->getIssueImagesAction();
    }
    
    /**
     * @description delete the issue images and files
     */
    public function deleteImagesAction()
    {          
        $images = $this->_input['images_delete'] ? $this->_input['images_delete'] : [];
        foreach ($images as $image) {
            $image_hash = $this->_dbr->getOne("SELECT `hash` FROM issue_pic WHERE id = $image");
            $image_path = set_file_path($image_hash);
            if (is_file($image_path)) {
                unlink($image_path);
            }
            $this->_db->query("DELETE FROM issue_pic WHERE id = $image");
        }
        $this->getIssueImagesAction();
    }

    /**
     * @description get issue images
     * @return object
     */
    public function getIssueImagesAction()
    {   
        global $smarty;
        $images_all = [];        
        $width = $this->_input['width'];
        $images = $this->_dbr->getAll("SELECT id FROM issue_pic WHERE `type` = 'pic' AND issuelog_id = $this->_page_id");
        $i = 0;
        foreach ($images as $id) {
            $images_all[$i]['id'] = $id->id;
            $images_all[$i]['url'] = smarty_function_imageurl([
                'src' => 'issue',
                'picid' => $id->id,
                'x' => 200], $smarty);
            $i++;
        }
        $this->_result['images'] = $images_all;
        $files_urls = [];
        $files = $this->_dbr->getAll("SELECT id, `name` FROM issue_pic WHERE `type` = 'doc' AND issuelog_id = $this->_page_id");
        if ($files) {
            $i = 0;
            foreach ($files as $file) {
                $files_urls[$i]['name'] = $file->name;
                $files_urls[$i]['url'] = "/doc.php?issue_doc_id=$file->id";
                $files_urls[$i]['id'] = $file->id;
                $i++;
            }
        }
        $this->_result['files'] = $files_urls;
        $this->output();
    }
    
    /**
     * get issue types
     * @return object
     */
    public function getIssueTypesAction() {
        $query = "SELECT `name`, inactive, id FROM issue_type";
        $result = $this->_dbr->getAll($query);
        $this->_result['issue_types'] = $result;
        $default_issue_type = \Config::get($this->_db, $this->_dbr, 'default_issue_type');
        $this->_result['default_issue_type'] = $default_issue_type;
        $this->output();
    }
    
    /**
     * set issue types
     * @return object
     */
    public function setIssueTypesAction() {
        if ($this->_input['name'] && !$this->_input['type_id']) {
            $name = mysql_real_escape_string($this->_input['name']);
            $this->_db->query("INSERT INTO issue_type (`name`) VALUES ('" . $name . "')");
        }
        if ($this->_input['name'] && $this->_input['type_id']) {
            $name = mysql_real_escape_string($this->_input['name']);
            $this->_db->query("UPDATE issue_type SET `name` = '$name' WHERE id = " . $this->_input['type_id']);
        }
        if ($this->_input['default']) {
            \Config::set($this->_db, $this->_dbr, 'default_issue_type', (int)$this->_input['default']);
        }
        if ($this->_input['inactive'] >= 0 && $this->_input['type_id']) {
            $this->_db->query("UPDATE issue_type SET inactive = " . (int)$this->_input['inactive'] . " WHERE id = " . $this->_input['type_id']);
        }
        if ($this->_input['inspection'] >= 0 && $this->_input['type_id']) {
            $this->_db->query("UPDATE issue_type SET inspection = " . (int)$this->_input['inspection'] . " WHERE id = " . $this->_input['type_id']);
        }
        $this->_result['success'] = true;
        $this->getIssueTypesAction();
    }
    
    /**
     * @description send email and log it when choose new responsible person
     */
    private function sendAndLogEmail($type)
    {
        if ($type == 'responsible') {
            $person = $this->_input['resp_person'];
            $field = 'resp_username';
        } 
        if ($type == 'solving') {
            $person = $this->_input['solving_resp_person'];
            $field = 'solving_resp_username';
        }
        global $siteURL;
        $current_respuser= $this->_db->getOne("select " . $field . " from issuelog where id=" . $this->_page_id);
        $newuser_email = $this->_db->getOne("select email from users where username='" . $person . "'");
        if ($current_respuser != $resp_person) {
            if (strlen($person)) {
                $ret = new stdClass;
                $ret->from = $this->_loggedUser->get('email');
                $ret->from_name = $this->_loggedUser->get('name');
                $user = new User($this->_db, $this->_dbr, $person);
                $ret->email_invoice = $newuser_email;
                $ret->saved_id = $this->_page_id;
                $ret->auction_number = $this->_page_id;
                $ret->txnid = -21;
                $ret->url = $siteURL . "react/logs/issue_logs/$this->_page_id/";
                standardEmail($this->_db, $this->_dbr, $ret, 'new_responsible_issue');
            }
        }
    }
    
    /**
     * get allowed to change issue status, department and responsible person in issue
     * @return boolean
     */
    private function checkPermision() 
    {
        $allow_change_filds = false;
        if ($this->_input['page_id']) {
            $issue_created_by = $this->_dbr->getOne("
                    SELECT users.username
                    FROM total_log 
                    JOIN users ON users.system_username = total_log.username
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'id' 
                        AND TableID = " . $this->_input['page_id']);

            if ($issue_created_by == $this->_loggedUser->get('username') || $this->_loggedUser->get('admin')) {
                $allow_change_filds = true;
            }
        }
        return $allow_change_filds;
    }
    
    /**
     * print checked issues
     * @var $issue_ids
     * @var $this
     * @var $dompdf
     * @var $smarty
     * @var $content
     * @var $html
     */
    public function issuesPrintAction() 
    {
        
        global $smarty;
        $issue_ids = $this->_input['issue_ids'];
        $issue_ids = implode(',', $issue_ids);
        $issues = $this->_dbr->getAll("
            SELECT 
                GROUP_CONCAT(issue_type.`name` separator '<br>') issue_type
                , issuelog.issue
                , (
                    SELECT Updated 
                    FROM total_log 
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'id' 
                        AND TableID = issuelog.id
                    ) AS added_time
                , (
                    SELECT users.`name` 
                    FROM total_log 
                    JOIN users ON users.system_username = total_log.username
                    WHERE `Table_name` = 'issuelog' 
                        AND Field_name = 'id' 
                        AND TableID = issuelog.id
                    ) AS added_person
                , users.`name` solving_person
                , empd.`name` departament
                , issuelog.obj,
                    CASE obj
                        WHEN 'supplier' THEN CONCAT('Supplier: ', issuelog.obj_id)
                        WHEN 'auction' THEN CONCAT('Auftrag: ', 
                            IF(auction.auction_number, auction.auction_number, issuelog.obj_id), 
                            IF(auction.txnid, CONCAT('/',auction.txnid), ''))
                        WHEN 'rma' THEN CONCAT('Ticket: ', issuelog.obj_id)
                        WHEN 'ww_order' THEN CONCAT('WWO: ', issuelog.obj_id)
                        WHEN 'op_order' THEN CONCAT('OP Order: ', issuelog.obj_id)
                        WHEN 'route' THEN CONCAT('Route: ', issuelog.obj_id)
                        WHEN 'manual' THEN 'Manual'
                        WHEN 'rating' CONCAT(THEN 'Rating: ', issuelog.obj_id)
                        WHEN 'insurance' THEN CONCAT('Insurance: ', issuelog.obj_id)
                    END AS where_did
            FROM issuelog
            LEFT JOIN emp_department empd ON empd.id = issuelog.department_id
            LEFT JOIN users ON users.username = issuelog.resp_username
            LEFT JOIN users users_solving ON users_solving.username = issuelog.solving_resp_username
            LEFT JOIN issue_type ON issue_type.id = issuelog.type_id
            LEFT JOIN auction ON auction.id = issuelog.obj_id AND issuelog.obj = 'auction'
            LEFT JOIN rma ON rma.rma_id = issuelog.obj_id AND issuelog.obj = 'rma'
            LEFT JOIN auction auction_rma ON rma.auction_number = auction_rma.auction_number AND rma.txnid = auction_rma.txnid
            LEFT JOIN orders ON auction.auction_number = orders.auction_number and auction.txnid = orders.txnid
            LEFT JOIN orders orders_rma ON rma.auction_number = orders_rma.auction_number and rma.txnid = orders_rma.txnid
            LEFT JOIN auction_par_varchar ON auction.auction_number = auction_par_varchar.auction_number AND auction.txnid = auction_par_varchar.txnid AND auction_par_varchar.`key` = 'country_shipping'
            LEFT JOIN auction_par_varchar apv_rma ON rma.auction_number = apv_rma.auction_number AND rma.txnid = apv_rma.txnid AND apv_rma.`key` = 'country_shipping'
            LEFT JOIN total_log tl ON tl.`Table_name` = 'issuelog' AND tl.Field_name = 'id' AND tl.TableID = issuelog.id
            LEFT JOIN issuelog_type ON issuelog_type.issuelog_id = issuelog.id
            WHERE issuelog.id IN ($issue_ids)
                GROUP BY added_time
                ORDER BY added_time DESC
        ");
        
        if ($issues) {
            $smarty->assign('issues', $issues);
            $html = $smarty->fetch('issues.tpl');
            require_once("dompdf/dompdf_config.inc.php");
            $dompdf = new DOMPDF();
            $dompdf->set_paper('A4', 'landscape');
            $dompdf->load_html($html);
            $dompdf->render();
            $content = $dompdf->output();
            header("Content-type: application/pdf");
            header("Content-disposition: inline; filename=issues.pdf");
            echo $content;
        }
    }
}