<?php

namespace label\Spam;

/**
 * Class Spam
 * Class used to process send messages
 */
class Spam
{
    
    private $_db;
    private $_dbr;
    
    private $_job;
    
    private $_def_smtp;
    
    private $_domain;
    
    public function __construct($domain)
    {
        $this->_db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $this->_dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $this->_domain = $domain;
        
//        $all_servers = $this->_dbr->getAssoc("SELECT `id`, `ip` FROM `spam_server` WHERE NOT `inactive`");
        
//        foreach ($all_servers as $_domain) 
//        {
//            $ip = gethostbyname($_domain);
//            if ($this->_domain == $ip)
//            {
//                $this->_domain = $_domain;
//                break;
//            }
//        }
    } 
    
    public function newsletter_cron_inactive() 
    {
        $query = "SELECT * FROM `shop_spam_logo`
                WHERE `tocheck` AND NOT `finished` AND `last_action` < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $res = $this->_dbr->getAll($query);
        foreach ($res as $user) 
        {
            $user->email = $user->email_invoice = $this->_dbr->getOne("SELECT email FROM users WHERE username='{$user->username}'");
            $user->newsletter_url = 'https://www.prologistics.info/news_email.php?id=' . $user->spam_id;
            standardEmail(null, null, $user, 'newsletter_cron_inactive');
            echo 'Send email to ' . $user->username . ' <br>' . "\n";
        }
    }
    
    public function get_spam() 
    {
        $query = "
            SELECT `ssl`.* 
            FROM `shop_spam_logo` `ssl`
            JOIN `shop_spam` `ss` ON `ss`.`id`=`ssl`.`spam_id`
            WHERE 1
                AND NOT `ssl`.`finished` 
                AND NOT `ssl`.`inactive` 
                AND `ssl`.`plan` <= NOW() 
                AND `ss`.`old`=0
                AND (`ssl`.`cron_domain` = '" . mysql_real_escape_string( $this->_domain ) . "' OR IFNULL(`ssl`.`cron_domain`, '') = '')
            LIMIT 1
        ";
        
        $this->_job = $this->_dbr->getRow($query);

        return (bool)$this->_job;
    }
    
    public function initialize() 
    {
        $started = date('Y-m-d H:i:s');
        
        if ( ! $this->_job->first_action)
        {
            $this->_db->query("UPDATE `shop_spam_logo` 
                SET `first_action` = '$started' 
                WHERE `first_action` IS NULL AND `id` = '" . (int)$this->_job->id . "'");
        }
        $this->_db->query("UPDATE `shop_spam_logo` 
            SET `last_action` = '$started' WHERE `id` = '" . (int)$this->_job->id . "'");
        
        $all_smtp = $this->_dbr->getAssoc("
            SELECT `smtp`.`id` AS `k`, `smtp`.`id` AS `v`
            FROM `smtp` 
            JOIN `shop_spam_smtp` `sss` ON `sss`.`smtp_id` = `smtp`.`id`
            WHERE `smtp`.`active` = 1 AND `sss`.`spam_id` = '" . (int)$this->_job->spam_id . "'
        ");

        if ( ! $all_smtp) {
            $all_smtp = [30];
        }

        shuffle($all_smtp);
        $this->_def_smtp = $all_smtp[0];
        
        return true;
    }
    
    public function send() 
    {
        switch ($this->_job->type)
        {
            case 'selected':
            case 'shop':
                $spam_id = (int)$this->_job->spam_id;

                $spam = $this->_dbr->getRow("SELECT * FROM `shop_spam` WHERE `id`='" . $spam_id . "'");
                $xheaders = $this->_dbr->getAll("SELECT * FROM `shop_spam_header` WHERE `spam_id`='" . $spam_id . "'");
                $docs = $this->_dbr->getAll("SELECT * FROM `shop_spam_doc` WHERE `shop_spam_id` = '" . $spam_id . "'");

                $spam->docs = [];
                foreach ($docs as $_spam) {
                    $_spam->data = get_file_path($_spam->data);
                    $spam->docs[] = $_spam;
                }
                
                switch ($this->_job->type)
                {
                    case 'selected':
                        $this->send_selected($spam, $xheaders);
                        break;
                    case 'shop':
                        $this->send_shop($spam, $xheaders);
                        break;
                }
                
                break;
        }
    }
    
    private function send_selected($spam, $xheaders) 
    {
        $protocol = 'Newsletter was sent to the following emails:' . "<br>\n";
        
        $spam_logo_id = (int)$this->_job->id;
        $spam_id = (int)$this->_job->spam_id;

        $sent_emails_array = [];
        $all_customers = $this->_dbr->getAll("
            SELECT `id`, `src_` `src`
                , CONCAT(
                    IF(
                        `src_`='', 
                        'Shop', 
                        IF(
                            `src_`='_auction', 
                            'Auction', 
                            'Journalist'
                        )
                    )
                    , `customer_id`) `customer_id`
                , `customer_id` `rec_customer_id`
                , IF(
                    `src_`='', 
                    -5, 
                    IF(
                        `src_`='_auction', 
                        -6, 
                        -4
                    )
                ) as txnid
                FROM `prologis_log`.`shop_spam_logo_email`
                WHERE `result`='' AND `spam_logo_id`='" . $spam_logo_id . "'");
        
        if (!count($all_customers))
        {
            $this->send_protocol($protocol);
            return;
        }
        
        shuffle($all_customers);
        
        foreach ($all_customers as $customer_rec) 
        {
            $query = "
                SELECT `id` 
                FROM `prologis_log`.`shop_spam_logo_email`
                WHERE `id` = " . $customer_rec->id . " AND `result`=''
                LIMIT 1
            ";
            
            if ( ! $this->_db->getOne($query))
            {
                continue;
            }

            $this->_db->query("UPDATE `prologis_log`.`shop_spam_logo_email` 
                    SET `server`='" . mysql_real_escape_string( $this->_domain ) . "', 
                        `result`='in process' WHERE `id`='" . (int)$customer_rec->id . "'");
            
            $email = $this->_dbr->getOne("
                SELECT GROUP_CONCAT(`email`) FROM (
                        SELECT `email` 
                        FROM `customer" . $customer_rec->src . "`
                        WHERE `id` = '" . (int)$customer_rec->rec_customer_id . "'
                    UNION 
                        SELECT `email_invoice` 
                        FROM `customer" . $customer_rec->src . "` 
                        WHERE `id` = '" . (int)$customer_rec->rec_customer_id . "'
                    UNION 
                        SELECT `email_shipping` 
                        FROM `customer" . $customer_rec->src . "` 
                        WHERE `id` = '" . (int)$customer_rec->rec_customer_id . "'
                ) t WHERE IFNULL(`email`, '') != ''
            ");
            
            if (\PEAR::isError($email)) {
                print_r($email);
                die();
            }

            if (!strlen($email))
            {
                continue;
            }

            // check if we already sent it
            if ( ! in_array($email, $sent_emails_array)) {
                // add to already sent
                $sent_emails_array[] = $email;
                
                // send
                $auction = new \stdClass;
                $auction->email = $email;

                $auction->spam = $spam;
                $auction->auction_number = $customer_rec->rec_customer_id;
                $auction->txnid = $customer_rec->txnid;
                $auction->src = $customer_rec->src;
                $auction->shop_id = $this->_job->shop_id;
                $auction->username = \Config::get(null, null, 'aatokenSeller');
                $auction->shop_spam_id = $spam_id;
                
                $customer = $this->_dbr->getRow("
                    SELECT * 
                    FROM `customer" . $customer_rec->src . "` 
                    WHERE `id` = '" . (int)$customer_rec->rec_customer_id . "'
                ");
                
                $auction->customer = $customer;
                foreach ($xheaders as $k => $r) 
                {
                    $xheaders[$k]->header = substitute($xheaders[$k]->header, (array) $customer);
                }
                $auction->xheaders = $xheaders;
                $protocol .= $email;
                
                if (standardEmail(null, null, $auction, 'customer_shop_news', 0, 0, false, $this->_def_smtp)) 
                {
                    if ((int) $this->_job->delay)
                    {
                        sleep((int) $this->_job->delay);
                    }
                    
                    $protocol .= $customer->email . " - sent <br>\n";
                    $email_id = $this->_db->getOne("SELECT MAX(`id`) FROM `email_log`");
                    $result = 'OK';
                    // log sending for all the customers

                    $table = 'shop_spam_customer' . $auction->src;
                    $q = "SELECT COUNT(*) FROM `$table`
                            WHERE `customer_id` = '" . (int)$auction->auction_number . "' 
                                AND `shop_spam_id` = '" . $spam_id . "'";
                    
                    $already_sent_to_this_customer = $this->_db->getOne($q);
                    if ($already_sent_to_this_customer) 
                    {
                        $q = "UPDATE `$table` SET `sent` = `sent` + 1 
                                WHERE `customer_id` = '" . (int)$auction->auction_number . "' 
                                    AND `shop_spam_id` = '" . $spam_id . "'";
                        
                        $r = $this->_db->query($q);
                        if (\PEAR::isError($r)) 
                        {
                            print_r($r);
                            die();
                        }
                    } 
                    else 
                    {
                        $q = "INSERT INTO `$table` SET `sent` = 1, `read` = 0 
                                , `customer_id` = '" . (int)$auction->auction_number . "'
                                , `shop_spam_id` = '" . $spam_id . "'";
                        $r = $this->_db->query($q);
                        if (\PEAR::isError($r)) 
                        {
                            $q = "UPDATE `$table` SET `sent` = `sent` + 1 
                                    WHERE `customer_id` = '" . (int)$auction->auction_number . "' 
                                        AND `shop_spam_id` = '" . $spam_id . "'";
                            $r = $this->_db->query($q);
                            if (\PEAR::isError($r)) 
                            {
                                print_r($r);
                                die();
                            }
                        }
                    }
                } 
                else 
                {
                    $protocol .= " - FAILED <br>\n";
                    $email_id = 0;
                    $result = 'FAILED';
                    global $conv;
                    $this->_db->query("INSERT INTO `prologis_log`.`shop_spam_logo_email_failed` 
                            (`shop_spam_logo_email_id`, `conversation`)
                        VALUES ('" . (int)$customer_rec->id . "','" . mysql_real_escape_string($conv) . "')");
                } // sending
                
                $this->set_log($email_id, $result, (int)$auction->auction_number, $auction->src);
            } // if not already sent
        } // foreach selected customer
        
        $this->send_protocol($protocol);
    }
    
    private function send_shop($spam, $xheaders, $protocol) 
    {
        $protocol = 'Newsletter was sent to the following emails:' . "<br>\n";
        
        $spam_logo_id = (int)$this->_job->id;
        $spam_id = (int)$this->_job->spam_id;

        $auctions = $this->_dbr->getAll("
            SELECT `customer`.*, `shop_spam_logo_email`.`id` AS `shop_spam_logo_email_id`
            FROM `customer` 
            JOIN (
                SELECT `id`, `customer_id`
                FROM `prologis_log`.`shop_spam_logo_email`
                WHERE `result`='' AND `spam_logo_id` = '$spam_logo_id' LIMIT 1
            ) AS `shop_spam_logo_email` ON `customer`.`id` = `shop_spam_logo_email`.`customer_id`
        ");
        
        if ( ! count($auctions)) 
        {
            return false;
        }

        foreach ($auctions as $auction) 
        {
            $this->_db->query("UPDATE `prologis_log`.`shop_spam_logo_email` 
                    SET `result`='in process' WHERE `id`='" . (int)$auction->shop_spam_logo_email_id . "'");
            $finished = $thisd->_dbr->getOne("SELECT `finished` FROM `shop_spam_logo` WHERE `id` = '$spam_logo_id'");
            if ($finished)
            {
                break;
            }
            
            $protocol .= $auction->firstname_invoice . ' ' 
                    . $auction->name_invoice . '(' 
                    . $auction->email_invoice . ')';
            
            $auction->spam = $spam;
            $auction->auction_number = $auction->id;
            $auction->shop_id = (int) $this->_job->shop_id;
            $auction->txnid = -5;
            $auction->username = \Config::get(null, null, 'aatokenSeller');
            $auction->shop_spam_id = $spam_id;
            foreach ($xheaders as $k => $r) {
                $xheaders[$k]->header = substitute($xheaders[$k]->header, (array) $auction);
            }
            $auction->xheaders = $xheaders;

            if (standardEmail(null, null, $auction, 'customer_shop_news', 0, 0, false, $this->_def_smtp)) 
            {
                if ((int) $this->_job->delay)
                {
                    sleep((int) $this->_job->delay);
                }
                
                $email_id = $this->_db->getOne("SELECT MAX(`id`) FROM `email_log`");
                $result = 'OK';
                $protocol .= " - sent<br>\n";
                $shop_spam_customer_row = $this->_db->getRow("SELECT * FROM `shop_spam_customer`
                        WHERE `customer_id` = '" . (int)$auction->id . "' 
                            AND `shop_spam_id` = '" . (int)$spam_id . "'");
                
                if ($shop_spam_customer_row->sent) 
                {
                    break; // we cannot send more than one same newsmail
                } 
                else 
                {
                    $r = $this->_db->query("INSERT INTO `shop_spam_customer` 
                        SET `sent` = 1, `read` = 0, 
                            `customer_id`='" . (int)$auction->id . "', 
                            `shop_spam_id`='" . (int)$spam_id . "'");
                    if (\PEAR::isError($r)) {
                        print_r($r);
                        break; // we cannot send more than one same newsmail
                    }
                }
            } 
            else 
            {
                $protocol .= " - FAILED<br>\n";
                $email_id = 0;
                $result = 'FAILED';
            }
            
            $this->set_log($email_id, $result, (int)$auction->id, '');
        }

        $this->send_protocol($protocol);
    }
    
    private function set_log($email_id, $result, $auction_number, $auction_src) 
    {
        // log operation row
        $r = $this->_db->query("
            UPDATE `prologis_log`.`shop_spam_logo_email` 
            SET
                `email_id` = '" . $email_id . "',
                `result` = '" . mysql_real_escape_string($result) . "'
            WHERE 
                `spam_logo_id` = '" . (int)$this->_job->id . "'
                AND `customer_id` = '" . (int)$auction_number . "' 
                AND `src_` = '" . $auction_src . "'
        ");
        if (\PEAR::isError($r)) {
            print_r($r);
            die();
        }

        $r = $this->_db->query("UPDATE `shop_spam_logo` SET `last_action`=NOW() 
                WHERE `id` = '" . (int)$this->_job->id . "'");
        if (\PEAR::isError($r)) {
            print_r($r);
            die();
        }
    }
    
    private function send_protocol($protocol) 
    {
        $spam_logo_id = (int)$this->_job->id;
        
        $customers_rest = $this->_dbr->getOne("SELECT COUNT(*)
                FROM `prologis_log`.`shop_spam_logo_email`
                WHERE `result`='' AND `spam_logo_id`='$spam_logo_id'");
        
        if (!$customers_rest) {
            $r = $this->_db->query("UPDATE `shop_spam_logo` SET `finished`=1 WHERE `id`='$spam_logo_id'");
            if (\PEAR::isError($r)) {
                print_r($r);
                die();
            }
            
            $obj = new \stdClass;
            $obj->email_invoice = $this->_dbr->getOne("SELECT `email` FROM `users` 
                    WHERE `username`='" . mysql_real_escape_string($this->_job->username) . "'");
            $obj->auction_number = 0;
            $obj->txnid = 0;
            $obj->protocol = str_replace('<br>', "\n", $protocol);
            standardEmail(null, null, $obj, 'customer_shop_news_result');
            $protocol = '';
        }
    }
}