<?php
require_once 'PEAR.php';

class AuctionLog
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    static function Log($db, $dbr, $auction_number, $txnid, $username, $action)
    {
        $r = $db->query(
            "INSERT INTO prologis_log.auction_log SET `time` = NOW(), auction_number=$auction_number
				, txnid=$txnid
				, username='$username'
				, action='$action'"
        );
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
    }


    static function listAllbyAuction($db, $dbr, $auction_number, $txnid)
    {
        $r = $db->query(
            "SELECT auction_log.action, auction_log.time,  IFNULL( users.name, auction_log.username ) as full_username 
			, users.username
			FROM prologis_log.auction_log LEFT JOIN users ON auction_log.username=users.username 
				WHERE auction_number =$auction_number AND txnid=$txnid ORDER BY `time`"
        );
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
        $list = array();
        while ($log = $r->fetchRow()) {
            $list[] = $log;
        }
        return $list;
    }

    static function listAllbyUser($db, $dbr, $username)
    {
        $r = $db->query(
            "SELECT * FROM prologis_log.auction_log WHERE username='$username' ORDER BY `time`"
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

}
?>