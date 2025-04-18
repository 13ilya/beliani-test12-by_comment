<?php
require_once 'PEAR.php';

	function addZipRange($db, $dbr, 
		$country_code, 
		$mark, 
		$zip_from, 
		$zip_to
		) {
        $country_code = mysql_real_escape_string($country_code);
        $mark = mysql_real_escape_string($mark);
        $zip_from = mysql_real_escape_string($zip_from);
        $zip_to = mysql_real_escape_string($zip_to);
		$r = $db->query("INSERT INTO zip_range SET country_code='$country_code', 
		mark='$mark', 
		zip_from='$zip_from', 
		zip_to='$zip_to'");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }

	function getZipRanges($db, $dbr, $country_code, $mark) {
        $country_code = mysql_real_escape_string($country_code);
        $mark = mysql_real_escape_string($mark);
		$r = $dbr->getAll("SELECT 
				id,
				zip_from, 
				zip_to
				FROM zip_range WHERE mark='$mark' AND country_code='$country_code'");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        return $r;
    }

	function isZipInRange($db, $dbr, $country_code, $mark, $zip) {
		$country_code = mysql_real_escape_string($country_code);
		if (strlen($country_code) > 2) 
        	$country_code = CountryToCountryCode($country_code);
        $mark = mysql_real_escape_string($mark);
        $zip = mysql_real_escape_string($zip);
		$q = "SELECT count(*)
				FROM zip_range WHERE mark='$mark' AND country_code='$country_code'
				AND '$zip' BETWEEN zip_from AND zip_to";
		$r = $dbr->getOne($q);
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r);
            return;
        }
        return $r;
    }

?>