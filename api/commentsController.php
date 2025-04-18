<?php

/**
 * @description api react for comments
 * @author Ilya Khalizov
 * @version 1.0
 * 
 */

class commentsController extends apiController
{   
    private $action;

    public function __construct() {
        parent::__construct();
    }
    
    /**
     * @description get comments according to parameters
     * @return json
     * 
     * @var $where
     * @var $this
     * @var $query
     * @var $obj
     *  
     */
    public function  listAction()
    {
        $where = '';
        
        if ($this->action != 'save' && $this->action != 'delete' && $this->action != 'changeResponsible') {
            if ($this->_input['comment_id']) {
                $where .= " AND comments.id = " . $this->_input['comment_id'];
            }
            if ($this->_input['comment']) {
                $where .= " AND comments.content LIKE '%" . $this->_input['comment'] ."%'";
            }
        }
        if ($this->_input['comment_type']) {
            if ($this->action == 'save' || $this->action == 'delete' || $this->action == 'changeResponsible') {
                $where .= " AND comments.obj LIKE '%issuelog%'";
                $obj = 'issuelog';
            } else {
                $where .= " AND comments.obj LIKE '%" . $this->_input['comment_type'] . "%'";
                $obj = explode('_', $this->_input['comment_type']);
                $obj = strtolower($obj[0]);
            }
        }
        if ($this->_input['page_id']) {
            $where .= " AND comments.obj_id = " . $this->_input['page_id'];
        }
        
        $query = "SELECT 
                comments.id
                , comments.content AS comment
                , comments.obj_id AS page_id
                , comments.obj AS comment_type
                , (
                    SELECT employee.id
                    FROM total_log
                    LEFT JOIN users ON users.system_username = total_log.username
                    LEFT JOIN employee ON employee.username = users.username
                    WHERE 
                        total_log.TableID = comments.id 
                        AND total_log.`Table_name` = 'comments' 
                        AND total_log.Field_name = 'id'
                ) AS employee_id
                , (
                    SELECT users.id 
                    FROM total_log
                    LEFT JOIN users ON users.system_username = total_log.username 
                    WHERE 
                        total_log.TableID = comments.id 
                        AND total_log.`Table_name` = 'comments' 
                        AND total_log.Field_name = 'id'
                ) AS user_id
                , (
                    SELECT users.username 
                    FROM total_log
                    LEFT JOIN users ON users.system_username = total_log.username 
                    WHERE 
                        total_log.TableID = comments.id 
                        AND total_log.`Table_name` = 'comments' 
                        AND total_log.Field_name = 'id'
                ) AS username
                , (
                    SELECT users.`name` 
                    FROM total_log
                    LEFT JOIN users ON users.system_username = total_log.username 
                    WHERE 
                        total_log.TableID = comments.id 
                        AND total_log.`Table_name` = 'comments' 
                        AND total_log.Field_name = 'id'
                    ) AS full_username
                , (
                    SELECT total_log.Updated
                    FROM total_log
                    LEFT JOIN users ON users.system_username = total_log.username 
                    WHERE 
                        total_log.TableID = comments.id 
                        AND total_log.`Table_name` = 'comments' 
                        AND total_log.Field_name = 'id'
                ) AS create_date
                , '' AS prefix
                , '' AS cusername
                FROM comments WHERE content != '' $where
                UNION
                SELECT 
                     NULL id
                    , CONCAT('Alarm (Pending): ', alarms.comment) AS comment
                    , NULL page_id
                    , '' comment_type
                    , NULL employee_id
                    , NULL user_id
                    , alarms.username
                    , IFNULL(u.name, alarms.username) full_username
                    , (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
                    , 'alarm' AS prefix
                    , alarms.username cusername
                FROM alarms
                JOIN users u ON u.username = alarms.username
                WHERE type='$obj'
                    AND type_id=" . $this->_input['page_id'] . "
                ORDER BY id ASC";
        $this->_result['comments'] = $this->_db->getAll($query);
        
        /**
         * check if comment is notified 
         * @var $comment_notified_flag
         * @var $comment_notified
         */
        $comment_notified_flag = false;
        $comment_notified = "
            select count(*) 
            from comment_notif 
            where obj='$obj' 
                and obj_id=" . $this->_input['page_id'] . " 
                and username='" . $this->_loggedUser->get('username') . "'";
        $comment_notified = $this->_dbr->getOne($comment_notified);
        if ($comment_notified) {
            $comment_notified_flag = true;
        }
        $this->_result['comment_notified'] = $comment_notified_flag;
        
        $this->emaillogAction();
        $this->output();
    }
    
    /**
     * @description save or update comment
     * @return json
     * 
     * @var $this
     * @var $query
     * @var $id
     * @var $obj
     * @var $obj_id
     * @var $content
     * 
     */
    public function saveAction()
    { 
        $this->action = 'save';
        
        if ($this->_input['comment_id']) {
            $comment_id = (int)$this->_input['comment_id'];
        }
        if ($this->_input['comment_type']) {
            $comment_type = mysql_real_escape_string($this->_input['comment_type']);
        }
        if ($this->_input['page_id']) {
            $page_id = (int)$this->_input['page_id'];
        }
        if ($this->_input['comment']) {
            $content = mysql_real_escape_string($this->_input['comment']);
        }
        
        $query = "INSERT INTO comments SET content = '$content', obj = '$comment_type', obj_id = $page_id";
        $this->_db->query($query);
        $comment_type = explode('_', $comment_type);
        $comment_type = strtolower($comment_type[0]);
        comment_notif($this->_db, $this->_dbr, $comment_type, $page_id);
        $this->listAction();
    }
    
    /**
     * @description delete comment
     * @return json
     * 
     * @var $this
     * @var $query
     * @var $comment_id
     * 
     */
    public function deleteAction()
    {   
        $this->action = 'delete';
        
        if ($this->_input['comment_id']) {
            $comment_id = (int)$this->_input['comment_id'];
        }
        
        $query = "DELETE FROM comments WHERE id = " . $comment_id;
        
        $this->_db->query($query);
        $this->listAction();
    }
    
    /**
     * @description get email log for changing responsible person
     */
    public function emaillogAction()
    {
        $log = \EmailLog::listAll($this->_db, $this->_dbr, $this->_input['page_id'], -21, '', '', 'DESC');
        $this->_result['email_log'] = $log;
    }
}

