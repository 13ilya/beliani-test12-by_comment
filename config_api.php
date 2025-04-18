<?php
/**
 * URL of the API value
 */
if (!file_exists('tmp/block_api')) {
    $_apiUrl_value = 'https://api.sandbox.ebay.com/ws/api.dll';

    $_cgi_eBay = 'http://cgi.sandbox.ebay.com/ws/eBayISAPI.dll?ViewItem&Item=';
    $_feedback_eBay = 'http://feedback.sandbox.ebay.de/ws/eBayISAPI.dll?CreateDispute';
    $_relist_eBay = 'http://cgi5.sandbox.ebay.de/ws/eBayISAPI.dll?RelistItem&Item=';
};

function setCountrySettings($db, $dbr, $code, $vars)
{
    $r = $db->query("delete from config_api where siteid='" . mysql_real_escape_string($code) . "'");
    if (PEAR::isError($r)) print_r($r);
    foreach ($vars as $par_id => $par_value) {
        if (is_array($par_value)) foreach ($par_value as $par_value1) {
            $query = '';
            $query .= "`value`='" . mysql_real_escape_string($par_value1) . "'";
            $where = ", siteid='" . mysql_real_escape_string($code) . "', par_id=$par_id";
            $r = $db->query("INSERT INTO config_api SET $query $where");
            if (PEAR::isError($r)) print_r($r);
        } else {
            if ($par_id <> 'code') {
                $query = '';
                $query .= "`value`='" . mysql_real_escape_string($par_value) . "'";
                $where = ", siteid='" . mysql_real_escape_string($code) . "', par_id=$par_id";
                $r = $db->query("INSERT INTO config_api SET $query $where");
                if (PEAR::isError($r)) print_r($r);
            }
        }
    };
}

function getCountrySettings($db, $dbr, $code)
{
    if (file_exists('tmp/block_api')) return;
    $r = $dbr->getAll("SELECT (
				SELECT MAX(ca.value)
				FROM config_api ca
				WHERE ca.par_id = cap.id
				AND ca.siteid = '" . mysql_real_escape_string($code) . "'
				) AS par_value, cap.name AS par_name, cap.id AS par_id, cap.type AS par_type
				, cap.source AS par_source
				FROM config_api_par cap
				where cap.id<>6");
    if (PEAR::isError($r)) print_r($r);
    return $r;
}

$getParByName_cache = array();

function getParByName($db, $dbr, $code, $parname)
{
    if (!is_a($dbr, 'MDB2_Driver_mysql')) {
        $error = PEAR::raiseError('Auction::Auction expects its argument to be a MDB2_Driver_mysql object');
        print_r($error);
        die();
        return;
    }
    if (file_exists('tmp/block_api')) return;
    global $getParByName_cache;
    if (isset($getParByName_cache[$code][$parname])) return $getParByName_cache[$code][$parname];
    $r = $dbr->getOne("
				SELECT IFNULL(cav.description, ca.value)
				FROM config_api ca 
					JOIN config_api_par cap ON ca.par_id=cap.id
					left join config_api_values cav on ca.par_id=cav.par_id and cav.value=ca.value
				WHERE ca.siteid = '" . mysql_real_escape_string($code) . "'
				AND cap.name = '" . mysql_real_escape_string($parname) . "'");
    if (PEAR::isError($r)) aprint_r($r);
    $getParByName_cache[$code][$parname] = $r;
    return $r;
}