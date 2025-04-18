<?php
require_once 'PEAR.php';
require_once 'util.php';

class ShippingMethod
{
    var $data;
    var $_db;
    var $_dbr;
    var $_error;
    var $_isNew;

    function ShippingMethod($db, $dbr, $id = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('ShippingMethod::ShippingMethod expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN shipping_method");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM shipping_method WHERE shipping_method_id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
			$this->data->country_name = countryCodeToCountry($this->data->country); 
            if (!$this->data) {
                $this->_error = PEAR::raiseError("ShippingMethod::ShippingMethod : record $id does not exist");
                return;
            }
            $this->_isNew = false;
        }
    }

    function set($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('ShippingMethod::update : no data');
        }
        foreach ($this->data as $field => $value) {
			if ($field == 'country_name') continue;
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE shipping_method_id='" . mysql_escape_string($this->data->shipping_method_id) . "'";
        }
        $r = $this->_db->query("$command shipping_method SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r; //print_r($r);
        }
        if ($this->_isNew) {
            $this->data->offer_id = mysql_insert_id();
        }
        return $r;
    }

    static function listAll($db, $dbr, $dead=0)
    {
	  global $method_filter_str;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
		$q = "SELECT shipping_method.*
			, CONCAT(country, ': ', company_name) company_name1
			FROM shipping_method where deleted=$dead $method_filter_str order by country, company_name";
        $r = $dbr->getAll($q);
/*		foreach($r as $k=>$method) {
			$r[$k]->company_name = $method->country.': '.$method->company_name;
		}*/
//		echo "SELECT * FROM shipping_method where deleted=$dead $method_filter_str";
        if (PEAR::isError($r)) {
            print_r($r);
            return;
        }
        return $r;
    }

    static function listArray($db, $dbr, $dead=0)
    {
        $ret = array();
        $list = ShippingMethod::listAll($db, $dbr, $dead);
        foreach ((array)$list as $method) {
            $ret[$method->shipping_method_id] = $method->country.': '.$method->company_name;
        }
        return $ret;
    }

    function validate(&$errors)
    {
        $errors = array();
        if (empty($this->data->company_name)) {
            $errors[] = 'Company name is required';
        }
        if (empty($this->data->phone)) {
            $errors[] = 'Phone is required';
        }
        return !count($errors);
    }

    function DPDWS_getAuth()
    {
		require_once 'XML/Unserializer.php';
		$opts = array(
				);
		$us =new XML_Unserializer( $opts );

		$xml_getAuth = '
		    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
		     xmlns:ns="http://dpd.com/common/service/types/LoginService/2.0">
		     <soapenv:Header/>
		     <soapenv:Body>
		     <ns:getAuth>
		     <delisId>'.$this->data->dpdws_delisId.'</delisId>
		     <password>'.$this->data->dpdws_password.'</password>
		     <messageLanguage>en_EN</messageLanguage>
		     </ns:getAuth>
		     </soapenv:Body>
		    <soapenv:Envelope>
		    ';
		
	    $headers_getAuth = array(
	        "POST  HTTP/1.1",
	        "Content-type: application/soap+xml; charset=\"utf-8\"",
	        "SOAPAction: \"http://dpd.com/common/service/LoginService/2.0/getAuth\"",
	        "Content-length: ".strlen($xml_getAuth)
	    );
	
//		$url = 'https://public-ws-stage.dpd.com/services/LoginService/V2_0/';
		$url = 'https://public-ws.dpd.com/services/LoginService/V2_0/';
	    $getAuth = curl_init($url);
	    curl_setopt($getAuth, CURLOPT_SSL_VERIFYHOST, 0);
	    curl_setopt($getAuth, CURLOPT_SSL_VERIFYPEER, 0);
	    curl_setopt($getAuth, CURLOPT_POST, 1);
	    curl_setopt($getAuth, CURLOPT_HTTPHEADER, $headers_getAuth);
	    curl_setopt($getAuth, CURLOPT_POSTFIELDS, "$xml_getAuth");
	    curl_setopt($getAuth, CURLOPT_RETURNTRANSFER, 1);
	
	    $output_getAuth = curl_exec($getAuth);
		$us->unserialize($output_getAuth);
		$result = $us->getUnserializedData();
		return $result['soap:Body']['ns2:getAuthResponse']['return']['authToken'];
    }

    function DPDWS_ShipmentService($auth, $number, $auction)
    {
		require_once 'XML/Unserializer.php';
		$opts = array(
				);
		$us =new XML_Unserializer( $opts );
		switch ($auction->getMyLang()) {
			case 'english': $lang='en_EN';
				break;
			case 'german': $lang='de_DE';
				break;
			case 'french': $lang='fr_FR';
				break;
			case 'polish': $lang='pl_PL';
				break;
			case 'dutch': $lang='de_DE';
				break;
			case 'swedish': $lang='se_SE';
				break;
			case 'Hungarian': $lang='hu_HU';
				break;
			case 'italian': $lang='it_IT';
				break;
			case 'portugal': $lang='pt_PT';
				break;
			case 'spanish': $lang='es_ES';
				break;
		}
//$lang='en_EN';
		$recipient_country=countryToCountryCode($auction->get('country_shipping'));
		if ($recipient_country=='UK') $recipient_country='GB';
		$xml = '
<soapenv:Envelope 
		xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
		xmlns:ns="http://dpd.com/common/service/types/Authentication/2.0" 
		xmlns:ns1="http://dpd.com/common/service/types/ShipmentService/3.1">
	<soapenv:Header>
		<ns:authentication>
			<delisId>'.$this->data->dpdws_delisId.'</delisId>
			<authToken>'.$auth.'</authToken>
			<messageLanguage>'.$lang.'</messageLanguage>
		</ns:authentication>
	</soapenv:Header>
	<soapenv:Body>
		<ns1:storeOrders>
			<printOptions>
				<printerLanguage>PDF</printerLanguage>
				<paperFormat>A6</paperFormat>
			</printOptions>
			<order>
				<generalShipmentData>
					<identificationNumber>77777</identificationNumber>
					<sendingDepot>0163</sendingDepot>
					<product>CL</product>
					<mpsCompleteDelivery>0</mpsCompleteDelivery>
					<sender>
						<name1>Qualitrade GbmH</name1>
						<street>Gewerbegebiet</street>
						<country>DE</country>
						<zipCode>17291</zipCode>
						<city>Prenzlau</city>
						<customerNumber>12345679</customerNumber>
					</sender>
					<recipient>
						<name1>'.$auction->get('firstname_shipping').' '.$auction->get('name_shipping').'</name1>
						<name2>'.$auction->get('company_shipping').'</name2>
						<street>'.$auction->get('street_shipping').' '.$auction->get('house_shipping').'</street>
						'.(strlen($auction->get('state_shipping'))?('<state>'.$auction->get('state_shipping').'</state>'):'').'
						<country>'.$recipient_country.'</country>
						<zipCode>'.$auction->get('zip_shipping').'</zipCode>
						<city>'.$auction->get('city_shipping').'</city>
					</recipient>
				</generalShipmentData>
				<parcels>
					<parcelLabelNumber>'.$number.'</parcelLabelNumber>
				</parcels>
				<productAndServiceData>
					<orderType>consignment</orderType>
                 <predict>
                  <channel>1</channel>
                  <value>'.$auction->get('email_shipping').'</value>
                  <!--Optional:-->
                  <language>'.substr($lang,3,2).'</language>
               </predict>
				</productAndServiceData>
			</order>
		</ns1:storeOrders>
	</soapenv:Body>
</soapenv:Envelope>
		    ';
	    $headers = array(
        "POST  HTTP/1.1",
        "Content-type: application/soap+xml; charset=\"utf-8\"",
        "SOAPAction: \"http://dpd.com/common/service/ShipmentService/3.1/storeOrders\"",
        "Content-length: ".strlen($xml)
    );
	if ($auction->get('auction_number')==231545)  echo $xml;
//		$url = 'https://public-ws-stage.dpd.com/services/ShipmentService/V3_1/';
		$url = 'https://public-ws.dpd.com/services/ShipmentService/V3_1/';
	    $getAuth = curl_init($url);
	    curl_setopt($getAuth, CURLOPT_SSL_VERIFYHOST, 0);
	    curl_setopt($getAuth, CURLOPT_SSL_VERIFYPEER, 0);
	    curl_setopt($getAuth, CURLOPT_POST, 1);
	    curl_setopt($getAuth, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($getAuth, CURLOPT_POSTFIELDS, "$xml");
	    curl_setopt($getAuth, CURLOPT_RETURNTRANSFER, 1);
	    $output_getAuth = curl_exec($getAuth);
		$us->unserialize($output_getAuth);
		$result = $us->getUnserializedData();
		return $result;
    }

    /**
     * Returns info about delivery (time and default country) based on product and it's default delivery method
     *
     * @param shopCatalogue $shopCatalogue shop object
     * @param int $offerId offer identifier
     * @return array keys are:
     *  def_day - shipping within (default country),
     *  other_day - shipping within (other countries),
     *  def_date - shipping up to (default country),
     *  other_date - shipping up to (other countries),
     *  def_country - code of default county for delivery method
     * Example of return: array(
     *  def_day => (int)5,
     *  other_day => (int)10,
     *  def_date => (string)'2016-06-10',
     *  other_date => (string)'2016-06-15',
     *  def_county => (string)'DE'
     * )
     */
    public static function getDeliveryDays($shopCatalogue, $offerId, $date_format = false) {
        $function = "getDeliveryDays({$shopCatalogue->_shop->id}, $offerId, $date_format)";
        $chached_ret = cacheGet($function, $shopCatalogue->_shop->id, '');

        if ($chached_ret) {
            return $chached_ret;
        }

        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        $queryDeliveryDays = '
            SELECT
                `shipping_method`.`delivery_days_for_def_country` AS `def_day`,
                `shipping_method`.`delivery_days_for_other_country` AS `other_day`,
                DATE_ADD(
                    IF (
                        `o`.`available` = \'0\',
                        `o`.`available_date`,
                        CURDATE()
                    ),
                    INTERVAL `shipping_method`.`delivery_days_for_def_country` DAY
                ) AS `def_date`,
                DATE_ADD(
                    IF (
                        `o`.`available` = \'0\',
                        `o`.`available_date`,
                        CURDATE()
                    ),
                    INTERVAL `shipping_method`.`delivery_days_for_other_country` DAY
                ) AS `other_date`,
                IF (
                        `o`.`available` = \'0\',
                        `o`.`available_date`,
                        CURDATE()
                    ) AS `curdate`,
	            `shipping_method`.`country` AS `def_country`
            FROM `offer` `o`
            INNER JOIN `translation` ON
                `translation`.`id` = `o`.`offer_id`
                AND `translation`.`table_name` = \'offer\'
                AND `translation`.`field_name` = \'sshipping_plan_id\'
                AND `translation`.`language` = '. $shopCatalogue->_shop->siteid .'
            INNER JOIN `shipping_plan` ON
                `shipping_plan`.shipping_plan_id = `translation`.`value`
            INNER JOIN `shipping_cost` ON
            	`shipping_cost`.`id` = `shipping_plan`.`shipping_cost_id`
            INNER JOIN `shipping_method` ON
                `shipping_cost`.`shipping_method_id` = `shipping_method`.`shipping_method_id`
            WHERE
                `o`.`offer_id` = ' . $offerId . '
            LIMIT 1';
        $result = $dbr->getRow($queryDeliveryDays);

        $_shop = $dbr->getRow("select shop.*
				, si.default_lang sellerInfo_default_lang
                , country.holi_plan_id
				from shop
				join seller_information si on si.username=shop.username
                join country on country.code=si.country
				where shop.id=" . $shopCatalogue->_shop->id);
        $_seller = new SellerInfo($db, $dbr, $_shop->username);
        
        if ($_shop->holi_plan_id) {
            $holidays = $dbr->getCol('select holidate from holi_plan_date 
                where holidate > now() and holi_plan_id = ' . (int)$_shop->holi_plan_id);
        }

        $stop_empty_warehouse = array_map(function($v) {return (int)$v->warehouse_id;}, $dbr->getAll("SELECT 
                `w`.`warehouse_id` FROM `warehouse` AS `w`
            JOIN `config_api_values` AS `cav` ON `cav`.`code` = `w`.`country_code`
            WHERE `cav`.`par_id` = '5' 
                AND `cav`.`value` = ? 
                AND NOT `cav`.`inactive`
                AND NOT `w`.`inactive`", null, [$shopCatalogue->_shop->siteid]));

        $resMinStock = getMinStock($db, $dbr, 0, $offerId, $stop_empty_warehouse, 4);
        
        if ($resMinStock['minava'] < 1) {
            $result->def_day += $shopCatalogue->_shop->delivery_crosswarehouse;
            $result->other_day += $shopCatalogue->_shop->delivery_crosswarehouse;
            $result->def_date = date('Y-m-d', strtotime('+'.$shopCatalogue->_shop->delivery_crosswarehouse.' days', strtotime($result->def_date)));
            $result->other_date = date('Y-m-d', strtotime('+'.$shopCatalogue->_shop->delivery_crosswarehouse.' days', strtotime($result->other_date)));
        }
        
        $skip_function = function(DateTime $date, $holidays)
        {
            if (isset($holidays) && !empty($holidays) && in_array($date->format('Y-m-d'), $holidays)) {
                return true;
            }
            
            $week = (int)$date->format('w');
            if($week == 6 || $week == 0){
                return true;
            }
            
            return false;
        };
        
        //calculation of final date not including days off
        $date = $prev_date = new DateTime($result->curdate);

        $i = 0;

        while($i < $result->def_day)
        {
            $date->add(new DateInterval('P1D'));
            $i++;

            while ($skip_function($date, $holidays)) {
                $date->add(new DateInterval('P1D'));
            }
        }
        
        $date_format = $date_format ? $date_format : str_replace('%', '', $_seller->get('date_format'));
        $result->def_date = $date->format($date_format);
        $date = new DateTime($result->curdate);
        $i = 0;
        while($i < $result->other_day){
            $date->add(new DateInterval('P1D'));
            $i++;
            
            while ($skip_function($date, $holidays)) {
                $date->add(new DateInterval('P1D'));
            }
        }
        $result->other_date = $date->format($date_format);

        cacheSet($function, $shopCatalogue->_shop->id, '', $result, 86400);

        return $result;
    }

    /**
     * Returns info about delivery (time and default country) based on product and it's default delivery method
     *
     * @param shopCatalogue $shopCatalogue shop object
     * @param int $offerId offer identifier
     * @return array keys are:
     *  def_day - shipping within (default country),
     *  other_day - shipping within (other countries),
     *  def_date - shipping up to (default country),
     *  other_date - shipping up to (other countries),
     *  def_country - code of default county for delivery method
     * Example of return: array(
     *  def_day => (int)5,
     *  other_day => (int)10,
     *  def_date => (string)'2016-06-10',
     *  other_date => (string)'2016-06-15',
     *  def_county => (string)'DE'
     * )
     */
    public static function getMultiDeliveryDays($shopCatalogue, $offerIds) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        $queryDeliveryDays = "
            SELECT
                `o`.`offer_id`, 
                `shipping_method`.`delivery_days_for_def_country` AS `def_day`,
                `shipping_method`.`delivery_days_for_other_country` AS `other_day`,
                DATE_ADD(
                    IF (
                        `o`.`available` = '0',
                        `o`.`available_date`,
                        CURDATE()
                    ),
                    INTERVAL `shipping_method`.`delivery_days_for_def_country` DAY
                ) AS `def_date`,
                DATE_ADD(
                    IF (
                        `o`.`available` = '0',
                        `o`.`available_date`,
                        CURDATE()
                    ),
                    INTERVAL `shipping_method`.`delivery_days_for_other_country` DAY
                ) AS `other_date`,
                IF (
                        `o`.`available` = '0',
                        `o`.`available_date`,
                        CURDATE()
                    ) AS `curdate`,
	            `shipping_method`.`country` AS `def_country`
            FROM `offer` `o`
            INNER JOIN `translation` ON
                `translation`.`id` = `o`.`offer_id`
                AND `translation`.`table_name` = 'offer'
                AND `translation`.`field_name` = 'sshipping_plan_id'
                AND `translation`.`language` = '" . $shopCatalogue->_shop->siteid . "'
            INNER JOIN `shipping_plan` ON
                `shipping_plan`.shipping_plan_id = `translation`.`value`
            INNER JOIN `shipping_cost` ON
            	`shipping_cost`.`id` = `shipping_plan`.`shipping_cost_id`
            INNER JOIN `shipping_method` ON
                `shipping_cost`.`shipping_method_id` = `shipping_method`.`shipping_method_id`
            WHERE
                `o`.`offer_id` IN ( " . implode(',', $offerIds) . " )
            GROUP BY `o`.`offer_id`";
        
        $result = $dbr->getAssoc($queryDeliveryDays);
        
        $holi_plan = $dbr->getRow("SELECT `country`.`holi_plan_id`, `si`.`date_format`
				FROM `seller_information` `si` 
                JOIN `country` ON `country`.`code` = `si`.`country`
				WHERE `si`.`username` = '" . $shopCatalogue->_shop->username . "'");
        
        if ($holi_plan->holi_plan_id) {
            $holidays = $dbr->getCol('select holidate from holi_plan_date 
                where holidate > now() and holi_plan_id = ' . (int)$holi_plan->holi_plan_id);
        }
        
        $stop_empty_warehouse = array_map(function($v) {return (int)$v->warehouse_id;}, $dbr->getAll("SELECT 
                `w`.`warehouse_id` FROM `warehouse` AS `w`
            JOIN `config_api_values` AS `cav` ON `cav`.`code` = `w`.`country_code`
            WHERE `cav`.`par_id` = '5' 
                AND `cav`.`value` = ? 
                AND NOT `cav`.`inactive`
                AND NOT `w`.`inactive`", null, [$shopCatalogue->_shop->siteid]));
        $stop_empty_warehouse = array_unique($stop_empty_warehouse);
        
        $cache = \Config::get(null, null, 'shop_stock_cache_hours');
        $ava_cache = [];
        
        $query = "SELECT `al`.`article_id`, `al`.`default_quantity`, `article`.`deleted`, 
                `op_company`.`name` `op_company_name`, `og`.`offer_id`
            FROM `article_list` `al`
            JOIN `offer_group` `og` ON `al`.`group_id` = `og`.`offer_group_id` AND NOT `base_group_id`
            JOIN `article` ON `article`.`article_id` = `al`.`article_id` AND `article`.`admin_id` = 0
            LEFT JOIN `op_company` ON `op_company`.`id` = `article`.`company_id`
            WHERE 
                `og`.`offer_id` IN (" . implode(',', $offerIds) . ") 
                AND NOT `al`.`inactive` 
                AND NOT `og`.`additional` 
                AND `al`.`default_quantity` > 0";
        $articles = $dbr->getAll($query);
        
        $article_stock = [];
        
        foreach ($offerIds as $offerId)
        {
            foreach ($articles as $rarticle) {
                if ($rarticle->offer_id != $offerId)
                {
                    continue;
                }
                
                foreach ($stop_empty_warehouse as $warehouse_id) {
                    if ( ! isset($minavas[$offerId][$warehouse_id]))
                    {
                        $minavas[$offerId][$warehouse_id] = 1000000;
                    }
                    
                    if (!isset($stock_cache[$offerId][$article_id][$warehouse_id])) {
                        
                        if ( ! isset($article_stock[$rarticle->article_id]))
                        {
                            $article_stock[$rarticle->article_id] = (int)$db->getOne("SELECT 
                                    fget_Article_stock_cache({$rarticle->article_id}, {$warehouse_id}, {$cache}) - 
                                    fget_Article_reserved({$rarticle->article_id}, {$warehouse_id})");
                        }
                                
                        $ava_cache[$offerId][$article_id][$warehouse_id] = floor($article_stock[$rarticle->article_id] / $rarticle->default_quantity);
                    } 
                    else 
                    {
                        continue;
                    }

                    $minavas[$offerId][$warehouse_id] = min($minavas[$offerId][$warehouse_id], $ava_cache[$offerId][$article_id][$warehouse_id]);
                }
            }
            
            $minavas[$offerId] = array_sum($minavas[$offerId]);
            if ($minavas[$offerId] < 1) {
                $result[$offerId]['def_day'] += $shopCatalogue->_shop->delivery_crosswarehouse;
                $result[$offerId]['other_day'] += $shopCatalogue->_shop->delivery_crosswarehouse;
                $result[$offerId]['def_date'] = date('Y-m-d', strtotime('+'.$shopCatalogue->_shop->delivery_crosswarehouse.' days', strtotime($result->def_date)));
                $result[$offerId]['other_date'] = date('Y-m-d', strtotime('+'.$shopCatalogue->_shop->delivery_crosswarehouse.' days', strtotime($result->other_date)));
            }
        }
        
        $skip_function = function(DateTime $date, $holidays)
        {
            if (isset($holidays) && !empty($holidays) && in_array($date->format('Y-m-d'), $holidays)) {
                return true;
            }
            
            $week = (int)$date->format('w');
            if($week == 6 || $week == 0){
                return true;
            }
            
            return false;
        };
        
        foreach ($offerIds as $offerId)
        {
            //calculation of final date not including days off
            $date = $prev_date = new DateTime($result[$offerId]['curdate']);

            $i = 0;

            while($i < $result[$offerId]['def_day'])
            {
                $date->add(new DateInterval('P1D'));
                $i++;

                while ($skip_function($date, $holidays)) {
                    $date->add(new DateInterval('P1D'));
                }
            }

            $date_format = str_replace('%', '', $holi_plan->date_format);
            $result[$offerId]['def_date'] = $date->format($date_format);
            $date = new DateTime($result[$offerId]['curdate']);
            $i = 0;
            while($i < $result[$offerId]['other_day']){
                $date->add(new DateInterval('P1D'));
                $i++;

                while ($skip_function($date, $holidays)) {
                    $date->add(new DateInterval('P1D'));
                }
            }
            $result[$offerId]['other_date'] = $date->format($date_format);
        }
        
        return $result;
    }
}
