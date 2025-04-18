<?php

class PrinterLog {
    public static function Log($auction_number, $txnid, $username, $action, $data_hash = NULL)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $query = "INSERT INTO printer_log SET log_date = NOW(), 
                auction_number=?, txnid=?, username=?, action=?, data_hash=?";
        $res = $db->execParam($query, [$auction_number, $txnid, $username, $action, $data_hash]);
    }

    static function listAll($auction_number, $txnid)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        return $dbr->getAll("SELECT `pl`.`auction_number`
                , `pl`.`action`
                , `pl`.`log_date`
                , `pl`.`txnid`
                , IFNULL(`u`.`name`, `pl`.`username`) AS `fullusername`
                , `u`.`username` AS `username`
            FROM `printer_log` AS `pl`
            LEFT JOIN `users` AS `u` ON `pl`.`username`=`u`.`username`
            WHERE `auction_number`=? AND `txnid`=? ORDER BY `log_date`", null, [$auction_number, $txnid]);
    }

}
