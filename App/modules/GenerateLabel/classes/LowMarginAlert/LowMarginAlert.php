<?php

namespace label\LowMarginAlert;

use label\DB;

/**
 * Class LowMarginAlert
 * Class used to alert users, when SA margin is below 'low_margin' value
 */
class LowMarginAlert {

    public function execute_alert(){

        $db = DB::getInstance(DB::USAGE_WRITE);
        $dbr = DB::getInstance(DB::USAGE_READ);

        $sql = "SELECT sa.id,
                       sp_low_margin.saved_id,sp_low_margin.par_value FROM saved_auctions sa
                LEFT JOIN saved_params sp_low_margin on sa.id=sp_low_margin.saved_id
                AND sp_low_margin.par_key='low_margin'
                WHERE sp_low_margin.saved_id IS NOT NULL";
        $list = $dbr->getAssoc($sql);
        foreach($list as $list_item){

            $saved_id = $list_item['saved_id'];
            $low_margin = $list_item['par_value'];
            $sa_margin = getSAMargin($saved_id);
            $margin_perc = $sa_margin->margin_perc;

            if(!empty($low_margin) && ($margin_perc<$low_margin)){

                // sending emails
                $sql = "SELECT u.email,u.id FROM seller_notif sn
                        JOIN seller_notif_type snt ON sn.notif_type_id = snt.id
                        JOIN users u ON u.id = sn.user_id
                        WHERE snt.code = 'low_margin_alert_email'";
                $emails = $dbr->getAll($sql);
                foreach($emails as $email_item){

                    $user = $dbr->getRow("SELECT * FROM users u
                                          WHERE u.id = {$email_item->id}");
                    $user->email_invoice=$user->email;
                    $user->saved_id = $saved_id;

                    $res = standardEmail($db,$dbr,$user,'low_margin_alert');

                    if($res) {
                        // mark SA as 'alerted'
                        $param_key = 'low_margin_alerted';
                        $param_value = 1;
                        $sp_id = (int)$dbr->getOne("SELECT id FROM saved_params
                                                    WHERE par_key='{$param_key}' AND saved_id={$saved_id}");
                        if (!$sp_id) {
                            $r = $db->query("replace saved_params set par_value='{$param_value}', par_key='{$param_key}', saved_id={$saved_id}");
                            if (\PEAR::isError($r)) {
                                die('Error in replace saved_params');
                            }
                        } else {
                            $r = $db->query("UPDATE saved_params SET par_value='{$param_value}'
                                             WHERE par_key='{$param_key}' and saved_id={$saved_id}");
                            if (\PEAR::isError($r)) {
                                die('Error is update saved_params');
                            }
                        }
                    }
                }
            }
        }
    }

}