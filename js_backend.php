<?php

require_once "JsHttpRequest/JsHttpRequest.php";
require_once 'config.php';
require_once 'connect.php';
require_once 'lib/EmailLog.php';
require_once 'lib/Config.php';
require_once 'lib/op_Order.php';
require_once 'lib/Order.php';
require_once 'lib/Offer.php';
require_once 'lib/Article.php';
require_once 'lib/Barcode.php';
require_once 'lib/Insurance.php';
require_once 'lib/Warehouse.php';
require_once 'lib/Auction.php';
require_once 'lib/User.php';
require_once 'lib/Rating.php';
require_once 'lib/Saved.php';
require_once 'util.php';
require_once 'lib/Minifier.php';
require_once 'lib/LoadingArea.php';
require_once 'lib/ShippingMethod.php';

$JsHttpRequest = new JsHttpRequest("utf8");
$fn = $_REQUEST['fn'];

switch ($fn)
{
    case 'delAlarm':
        $id = requestVar('id', 0);
        $qrystr = "update alarms set `status`='Closed' where `id`=$id";
        //$qrystr = "delete from alarms where id=$id";
        $res = $db->query($qrystr);
        break;
    case 'alarm':
        $status = mysql_escape_string(requestVar('status'));
        if ($status == 'Off alarm')
        {
            $status = 'Closed';
        }
        else
        {
            $status = 'Pending';
        }
        $type = mysql_escape_string(requestVar('type'));
        $type_id = mysql_escape_string(requestVar('type_id'));
        $date = mysql_escape_string(requestVar('date'));
        $comment = mysql_escape_string(requestVar('comment'));
        $username = mysql_escape_string(requestVar('username'));
        if (!strlen($username))
            $username = $loggedUser->get("username");
        $qrystr1 = "select id from alarms where `type`='$type' and `type_id`=$type_id and `username` " . ($username == 'NULL' ? ' is null' : "='$username'");
        $alert_id = $dbr->getOne($qrystr1);
        if ($alert_id)
        {
            $qrystr = "update alarms set `status`='$status', `date`='$date', `comment`='$comment' where `id`=$alert_id";
        }
        else
        {
            $qrystr = "insert into alarms set `status`='$status', `date`='$date', `comment`='$comment'
				, `type`='$type', `type_id`=$type_id, `username`=" . ($username == 'NULL' ? ' null' : " '$username'");
        }
        $res = $db->query($qrystr);
        break;

    case 'set_issue_inactive':
        $issue_id = requestVar('page_id', 0);
        $db->query("update issuelog set inactive = if(inactive, 0, 1) where id = $issue_id");
        break;

    case 'set_issue_recurring':
        $issue_id = requestVar('page_id', 0);
        $db->query("update issuelog set recurring = if(recurring, 0, 1) where id = $issue_id");
        $issue_totallog = getChangeDataLog('issuelog', 'recurring', $issue_id);
        $issue_totallog = $issue_totallog[0];
        $res = "by " . $issue_totallog->name . " on " . $issue_totallog->Updated;
        break;

    case 'comment_notif':
        $id = requestVar('id', 0);
        $obj = mysql_escape_string(requestVar('obj'));
        $qrystr = "select count(*) from comment_notif where obj='$obj' and obj_id=$id and username='" . $loggedUser->get('username') . "'";
        $cnt = $dbr->getOne($qrystr);

        $res2[] = $qrystr;

        if ($cnt)
        {
            $qrystr = "delete from comment_notif where obj='$obj' and obj_id=$id and username='" . $loggedUser->get('username') . "'";
            $res = '<b><font color="red">Off</font></b>';
        }
        else
        {
            $qrystr = "insert into comment_notif set obj='$obj', obj_id=$id, username='" . $loggedUser->get('username') . "'";
            $res = '<b><font color="green">On</font></b>';
        }

        $res2[] = $qrystr;
        $res1 = $db->query($qrystr);
        break;

    /**
     * @descrition delete from issuelog
     * @var $auction
     * @var $rma
     * @var DB $db, $dbr
     * @var $id
     * @var $issue_totallog
     * @var $res
     * @var $days_passed
     * @var $issue_log_status
     * @var $who_made_issue
     * @var $allow_change_filds
     * @var $issue_created_by
     */
    case 'changeIssueState':
        $id = (int) requestVar('page_id');
        $issue_state = mysql_real_escape_string(requestVar('issue_state'));

        /**
         * check if user can close issue
         */
        $allow_change_filds = false;
        $issue_created_by = $dbr->getOne("
                    SELECT users.username
                    FROM total_log
                    JOIN users ON users.system_username = total_log.username
                    WHERE `Table_name` = 'issuelog'
                        AND Field_name = 'id'
                        AND TableID = $id");
        if ($issue_created_by == $loggedUser->get('username') || $loggedUser->get('admin'))
        {
            $allow_change_filds = true;
        }
        if ($allow_change_filds)
        {
            $db->query("UPDATE issuelog SET status = '$issue_state' WHERE id = " . $id);
            $issue_log_status = $dbr->getOne("SELECT status FROM issuelog WHERE id = $id");
            $issue_totallog = getChangeDataLog('issuelog', 'status', $id);
            $issue_totallog = $issue_totallog[0];
            $res = "by " . $issue_totallog->name . " on " . $issue_totallog->Updated;

            $res2 = $id;

            $who_made_issue = $dbr->getOne("
                    SELECT users.email
                    FROM total_log
                    LEFT JOIN users ON users.system_username = total_log.username
                    WHERE total_log.`Table_name` = 'issuelog'
                        AND total_log.Field_name = 'id'
                        AND total_log.TableID = $id");

            /**
             * @description if issue closed we send email to person who opened it
             * and save comment to data base
             */
            if ($issue_log_status == 'close')
            {
                $db->query("
                        INSERT INTO comments
                        SET
                            content = 'Removed from Issue Log on " . $issue_totallog->Updated . " by " . $issue_totallog->name . "'
                            , obj_id = $id
                            , obj='issuelog_comment'");
                $auction = new stdClass();
                $auction->issuelog_id = $id;
                $auction->closed_by = $issue_totallog->name;
                $auction->closed_on = $issue_totallog->Updated;
                $auction->url = $siteURL . "react/logs/issue_logs/$id/";
                $auction->email_invoice = $who_made_issue;
                standardEmail($db, $dbr, $auction, 'issue_closed');
            }
        }
        break;

    /**
     * @descrition add to issuelog
     * @var $issue_titles
     * @var $depatament
     * @var $responsible
     * @var $auction
     * @var $rma
     * @var DB $db, $dbr
     * @var $id
     * @var $issue_totallog
     * @var $res
     * @var $default_issue_type
     * @var $type
     */
    case 'addIssueLog':
        $issue_title = mysql_real_escape_string(requestVar('issue_name'));
        $due_date = mysql_real_escape_string(requestVar('due_date'));
        $depatament = (int) requestVar('depatament_id');
        $responsible = mysql_real_escape_string(requestVar('responsible_id'));
        $obj_id = (int) requestVar('obj_id', 0);
        $obj = mysql_real_escape_string(requestVar('obj', 'manual'));
        $default_issue_type = Config::get($db, $dbr, 'default_issue_type');
        $issue_types = requestVar('issue_type', []);
        if (empty($issue_types))
        {
            $issue_types[] = $default_issue_type;
        }
        $recurring = (int) requestVar('recurring', 0);
        $qrystr = "INSERT INTO issuelog
                SET issue = '$issue_title'
                    , department_id = $depatament
                    , resp_username = '$responsible'
                    , solving_resp_username = '$responsible'
                    , obj = '$obj'
                    , recurring = $recurring
                    , due_date = '$due_date'
                    , obj_id = " . $obj_id;
        $db->query($qrystr);
        $id = $db->getOne('SELECT LAST_INSERT_ID()');
        foreach ($issue_types as $type)
        {
            $db->query("insert into issuelog_type set type_id = $type, issuelog_id = $id");
        }
        $issue_totallog = getChangeDataLog('issuelog', 'id', $id);
        $issue_totallog = $issue_totallog[0];

        /**
         * @description if issue opend we send email to person who opened it
         * and save comment to data base
         */
        $responsible_person_email = $dbr->getOne("
                SELECT users.email
                FROM issuelog
                JOIN users ON users.username = issuelog.resp_username
                WHERE issuelog.id = $id");

        /**
         * insert comment setting due date
         * @var $due_date
         * @var $issue_totallog
         * @var $id
         */
        if ($due_date)
        {
            $db->query("INSERT INTO comments SET content = 'Due Date set $due_date by user $issue_totallog->name', obj = 'issuelog', obj_id = $id");
        }

        $res = "by " . $issue_totallog->name . " on " . $issue_totallog->Updated;
        $res2 = $id;
        break;
}

$_RESULT = array(
    "uri" => (string) $_SERVER['SCRIPT_NAME'] . "?" . (string) $_SERVER['QUERY_STRING'],
    "fn" => $fn,
    "str" => $qrystr,
    "str1" => $qrystr1,
    "res" => $res,
    "res7" => $res7,
    "res6" => $res6,
    "res5" => $res5,
    "res4" => $res4,
    "res3" => $res3,
    "res2" => $res2,
    "res1" => $res1,
    "res0" => $res0,
    "old_sp" => $old_sp,
    "vars" => $vars,
    "log_table" => $log_table
);
//print_r($_RESULT);
?>