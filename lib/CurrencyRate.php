<?php
class CurrencyRate {
    /**
     *
     */
    private $_date;
    /**
     *
     */
    private $_rate;
    /**
     * Constructor
     */
    public function __construct($date) {
        $this->_date = $date;
    }
    /** 
     *
     */
    public function request() {
        $url = "http://api.nbp.pl/api/exchangerates/tables/A/{$this->_date}/?format=json";

	    $curl = curl_init($url);
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	    $result = curl_exec($curl);
        curl_close($curl);

        if ($result !== false) {
            $data = json_decode($result);
            if ($data && is_array($data) && is_object($data[0])) {
                foreach ($data[0]->rates as $rate) {
                    if ($rate->code == 'EUR') {
                        $this->_rate = $rate->mid;
                    }
                }
            } elseif (strpos($result, 'NotFound') !== false) {
                $this->_rate = 0;
            }
        }
        
        return $this;
    }
    /**
     *
     */
    public function save() {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        
        if (isset($this->_rate)) {
            $id = $db->getOne("SELECT `id` FROM `rate_day` WHERE `date` = '" . mysql_escape_string($this->_date) . "'");
            if ($id) {
                $db->query("UPDATE `rate_day` SET `rate` = {$this->_rate} WHERE `id` = $id");
            } else {
                $db->query("INSERT INTO `rate_day` 
                    SET `rate` = {$this->_rate},
                    `date` = '" . mysql_escape_string($this->_date) . "'");
            }
        }
        
        return $this->_rate;
    }
    /**
     * Get PLN/EUR rate for date $date. If date is vacation - grab previous date.
     * @param string $date. Y-m-d format
     * @return string
     */
    public static function getRate($date) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $rate = $dbr->getOne("SELECT `rate` FROM `rate_day` WHERE `date` = '" . mysql_escape_string($date) . "'");
        
        if (!isset($rate)) {
            $currency_rate = new self($date);
            $rate = $currency_rate->request()->save();
        } 
        
        if (!$rate) {
            $day_before = $date;
            $days_to_process = 7;
            $current_day = 1;
            while(!$rate && $current_day <= $days_to_process) {
                $day_before = date('Y-m-d', strtotime('-1 day', strtotime($day_before)));
                $rate = $dbr->getOne("SELECT `rate` FROM `rate_day` WHERE `date` = '" . $day_before . "'");
                if (!isset($rate)) {
                    $currency_rate = new self($day_before);
                    $rate = $currency_rate->request()->save();
                }
                $current_day++;
            }
        }
        
        return $rate;
    }
}