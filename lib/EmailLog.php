<?php
require_once 'PEAR.php';

class EmailLog
{
    public static function LogSMS($auction_number, $txnid, $template, $number, $provider, $message_id=0, $content='', $id=0, $time="NOW()")
    {
		$db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
		$content = mysql_real_escape_string($content);
		$number = mysql_real_escape_string($number);

		if (empty($auction_number)) {
			$auction_number = 'NULL';
		}
		if (empty($txnid)) {
			$txnid = 0;
		}

		if ($id) {
			$q = "update sms_log SET `date` = $time, auction_number=$auction_number, txnid=$txnid
		    , template='$template', number='$number', provider='$provider', message_id='$message_id', content='$content'
			where id=$id";
    	    $r = $db->query($q);
        	if (PEAR::isError($r)) aprint_r($r);
		} else {
			$q = "INSERT INTO sms_log SET `date` = $time, auction_number=$auction_number, txnid=$txnid
		    , template='$template', number='$number', provider='$provider', message_id='$message_id', content='$content'";
    	    $r = $db->query($q);
        	if (PEAR::isError($r)) aprint_r($r);
			$id = mysql_insert_id();
		}
		return $id;
    }

    static function Log($db, $dbr, $auction_number, $txnid, $action, $to='', $from='', $smtp_server='', $subject='', $content='', $notes=''
		, $attachments=array(), $id=0, $time="NOW()", $failed=0)
    {
		  $template_rec = $dbr->getRow("select * from template_names where name='$action'");
		$to = mysql_real_escape_string($to);
		$from = mysql_real_escape_string($from);
		$notes = mysql_real_escape_string($notes);
		$table_name = $template_rec->log_table;
		if (!strlen($table_name)) $table_name = 'email_log';
		if ($id) {
			$q = "update $table_name SET `date` = $time, auction_number=$auction_number, txnid=$txnid
		    , template='$action', recipient='$to', sender='$from', smtp_server='$smtp_server', notes='$notes', failed=$failed
			where id=$id";
//			echo $q;
    	    $r = $db->query($q);
        	if (PEAR::isError($r)) aprint_r($r);
		} else {
			$q = "INSERT INTO $table_name SET `date` = $time, auction_number=$auction_number, txnid=$txnid
		    , template='$action', recipient='$to', sender='$from', smtp_server='$smtp_server', notes='$notes', failed=$failed";
//			echo $q;
    	    $r = $db->query($q);
        	if (PEAR::isError($r)) aprint_r($r);
			$id = mysql_insert_id();
		}
		if (!$id) $id = $dbr->getOne("select max(id) from $table_name where auction_number=$auction_number and txnid=$txnid");
        $GLOBALS['last_email_log_id'] = $id;
		$subject = mysql_real_escape_string($subject);
		$content = mysql_real_escape_string($content);
        $r = $db->query("INSERT ignore INTO prologis_log.{$table_name}_content SET `id` = $id, content='$content', subject='$subject'");
        if (PEAR::isError($r)) aprint_r($r);
		if (!$dbr->getOne("select count(*) from {$table_name}_attachment where `id` = $id")) foreach($attachments as $attachment) {
			if ($attachment=='html') {
				$q = "INSERT IGNORE INTO {$table_name}_attachment SET `id` = $id
				, name='html'";
			} else {
				$q = "INSERT IGNORE INTO {$table_name}_attachment SET `id` = $id
				, name='".mysql_real_escape_string($attachment->name)."'"
				.($failed?", content='".base64_encode($attachment->data)."'"
					:"");
			}
    	    $r = $db->query($q);
	        if (PEAR::isError($r)) aprint_r($r);
		}
    }


    static function listAll($db, $dbr, $auction_number, $txnid, $template='', $limit='', $order='')
    {
		$tables = $db->getAssoc("select distinct log_table f1, log_table f2 from template_names");
		$qa = array();
		foreach($tables as $table_name) {
			$qa[] = "SELECT email_log.read_date,
				email_log.date,
			    email_log.id,
			    email_log.recipient,
			    email_log.sender,
			    email_log.smtp_server,
			    email_log.notes,
			    email_log.failed,
				email_log.template email_log_template,
			    IFNULL(template_names.`desc`, email_log.template) as template,
				 email_log_content.content,
				IFNULL(u.name, tl.username) username,
                template_names.html
                
				FROM {$table_name} email_log
			    LEFT JOIN prologis_log.{$table_name}_content email_log_content ON email_log.id = email_log_content.id 
			    LEFT JOIN template_names ON email_log.template = template_names.name 
				left join total_log tl on table_name='email_log' and field_name='id' 
					and tableid=email_log.id
				left join users u on u.system_username=tl.username
			    WHERE auction_number =$auction_number AND txnid=$txnid 
				$template";
		}
        
		$q = " select * from (
		".implode(" union all ", $qa)."
		) t ORDER BY `date` $order $limit";
        
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($log = $r->fetchRow()) {
			if ( ! $log->html && strpos($log->content, '<html>')===false) {
                $log->content = nl2br($log->content);
            }
            
			$log->content = str_replace('"', '&quot;', $log->content);
			$log->content = str_replace("\n"," ",$log->content);
			$log->content = str_replace("\r"," ",$log->content);
			$log->content = str_replace("'","\\'",$log->content);
            $log->content = str_replace("&#039;","\\'",$log->content);
            $log->content = preg_replace('/ +/',' ',$log->content);
			$log->content = str_replace("/image_shop.php?id=","/image_shop.php?idd=",$log->content);
			$log->attachments = $db->getAll("select * from email_log_attachment where id=".$log->id);
            $list[] = $log;
        }

        return $list;
    }
    static function listAllUnique($db, $dbr, $auction_number, $txnid)
    {
        $r = $db->query(
            "SELECT DISTINCT IFNULL(template_names.name, email_log.template) FROM email_log 
	    LEFT JOIN template_names ON email_log.template = template_names.name 
	    WHERE auction_number =$auction_number AND txnid=$txnid"
        );
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($log = $r->fetchRow()) {
            $list[] = $log;
        }
        return $list;
    }

    static function listAllSMS($db, $dbr, $auction_number, $txnid, $template='', $limit='', $order='')
    {
		$q = "SELECT sms_log.auction_number,
		sms_log.txnid,
		sms_log.content,
		sms_log.id,
		sms_log.date,
		sms_log.message_id,
		sms_log.number,
		sms_log.provider,
		sms_log.template,
		'' recipient,
		IFNULL(u.name, tl.username) username
		FROM sms_log 
	    LEFT JOIN template_names ON sms_log.template = template_names.name 
		left join total_log tl on table_name='sms_log' and field_name='id' 
			and tableid=sms_log.id
		left join users u on u.system_username=tl.username
	    WHERE auction_number =$auction_number AND txnid=$txnid 
		$template
		union all
		SELECT distinct email_log.auction_number,
		email_log.txnid,
		email_log_content.content,
		email_log.id,
		email_log.date,
		email_log.id message_id,
		email_log_content.subject number,
		email_log.recipient provider,
		email_log.template,
		email_log.recipient,
		IFNULL(u.name, tl.username) username
		FROM email_log 
	    LEFT JOIN prologis_log.email_log_content ON email_log.id = email_log_content.id 
	    LEFT JOIN template_names ON email_log.template = template_names.name 
		left join total_log tl on table_name='email_log' and field_name='id' 
			and tableid=email_log.id
		left join users u on u.system_username=tl.username
		join sms_email on sms_email.email=email_log.recipient #and sms_email.inactive=0
	    WHERE auction_number =$auction_number AND txnid=$txnid 
		$template
		ORDER BY `date` $order $limit";
		file_put_contents('rating.sql', $q);
        $r = $db->query($q);
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($log = $r->fetchRow()) {
			$log->content = str_replace("\r", '<br/>', str_replace('"', '&quot;', $log->content));
			$log->content = str_replace("\n"," ",$log->content);
			$log->content = str_replace("\r"," ",$log->content);
			$log->content = str_replace("'","\\'",$log->content);
            $list[] = $log;
        }
        return $list;
    }
}