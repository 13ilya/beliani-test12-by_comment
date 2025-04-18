<?php
require_once 'lib/Saved.php';

class EbaySaved extends Saved
{
    /**
     * saved_params that will be loaded by default
     */
	protected $_default_saved_param_fields = array(
		'master_sa','offer_id', 'siteid', 'username', 'auction_name', 'ean_code', 'startprice', 'subtitle',
        'categoryid', 'categoryid2', 'StoreCategoryID', 'StoreCategory2ID', 'title',
        /* Settings */
        'nownew', 'duration', 'quantity', 'stop_empty_level', 'stop_empty', 'nrepeats', 'dont_restart', 'second_chance',
        /* Shipping */
        'DispatchTimeMax', 
	);
    /**
     * 
     */
    private $_shop_saved;
	/** 
     * Retrieves Saved from corresponding shop
     */
    private function __getShopSaved()
    {
        if (!$this->_shop_saved) {
            $shop_saved_id = self::findShopSavedId($this->id);
            
            if ($shop_saved_id) {
                $fields = ['saved_params', 'titles', 'ShopDesription'];
                $this->_shop_saved = new Saved($this->_db, $this->_dbr, $shop_saved_id, $fields);
                
                if ($this->_shop_saved->isSlave()) {
                    $this->_shop_saved_master = new Saved($this->_db, $this->_dbr, $this->_shop_saved->getSavedParam('master_sa'), $fields);
                }
            } else {
                throw new Exception('Shop SA wasn\'t found');
            }
        }

        return $this->_shop_saved;
    }
    /**
     *
     */
    protected function _getDefaultLang()
    {
        if (!$this->default_lang) {
            $this->default_lang = $this->__getShopSaved()->getSeller()->default_lang;
        }
        
        return $this->default_lang;
    }
    /**
     *
     */
    protected function _getPaymentMethods()
    {
        if (!$this->payment_methods) {
            $this->payment_methods = [];
            $siteid = $this->getSavedParam('siteid');

            $this->payment_methods['options']['methods'] = $this->_dbr->getAssoc("SELECT cav.value, cav.description
                FROM config_api_values cav
                JOIN config_api ca ON cav.par_id = ca.par_id
                AND cav.value = ca.value
                WHERE ca.siteid = $siteid
                AND cav.par_id =12
                order by cav.description");
            
            $selected = $this->_dbr->getAssoc("SELECT par_key, par_value FROM saved_params 
                WHERE saved_id = {$this->id} AND par_key LIKE 'payment[%'");
            foreach ($selected as $key => $value) {
                $parts = explode('[', $key);
                $parts1 = explode(']', $parts[1]);
                $this->payment_methods['data']['methods'][$parts1[0]] = $value;
            }
        }
        
        return $this->payment_methods;
    }
    /**
     * Retrieves Titles from corresponding shop Saved
     */
    protected function _getTitles()
    {
        $this->titles = [];
        
        if ($this->__getShopSaved()->isSlave()) {
            $this->titles['list'] = $this->_shop_saved_master->titles;
            foreach ($this->titles['list'] as $lang => $data) {
                if ($lang != $this->_getDefaultLang()) {
                    unset($this->titles['list'][$lang]);
                }
            }
        
            $master_sa = $this->__getShopSaved()->getSavedParam('master_sa');
            $ShopDesription = mulang_fields_Get(array('ShopDesription'), 'sa', $master_sa);
            $selected = $ShopDesription['ShopDesription_translations'][$this->_getDefaultLang()]->value;
            $this->titles['selected'] = $selected;    
        }
    }
    /**
	 * Get Category params
	 */
	protected function _getSettings()
	{
        $this->settings = [];
        
        global $reporder;
        $this->settings['repetitions'] = $this->_loadRepetitions($this->id, $reporder);
    }
    /**
     *
     */
    protected function _setSettings($data)
    {
        if (!isset($this->repetitions_to_update)) {
            $this->repetitions_to_update = [];
        }
        
        if (!isset($this->new_repetitions)) {
            $this->new_repetitions = [];
        }
    
        foreach ($data as $name => $value) {
            if ($name == 'repetitions') {
                $updatable = ['days'];
                
                foreach ($value as $key => $interval) {
                    if (isset($this->settings['repetitions'][$interval->id])) {
                        foreach ($updatable as $field) {
                            if ($interval[$field] != $this->settings['repetitions'][$interval->id]->{$field}) {
                                $this->repetitions_to_update[$key] = $interval;
                            }
                        }
                    } else {
                        $this->new_repetitions[] = $interval;
                    }
                }
            }
        }
    }
    /**
     * Save settings field
     */
    protected function _saveSettings() 
    {
        foreach ($this->new_repetitions as $interval) {
            $now_time = date('H:i:s');
            if ($now_time < $interval->start_at) {
                $last_repeat = date('Y-m-d ', strtotime('-1 day', time())) . $interval->start_at;
            } else {
                $last_repeat = date('Y-m-d ') . $interval->start_at;
            }        
        
            $res = $this->_db->execParam(
                'INSERT INTO repetition SET
                    days=?, duration=?, featured=?, weekdays=?, start_date=?, start_at=?, last_repeat=?, auction_id=?, suspend=?',
                array(
                    $interval->days,
                    $interval->duration,
                    $interval->featured,
                    $interval->weekdays,
                    $interval->start_date,
                    $interval->start_at,
                    $last_repeat,
                    $this->id,
                    $interval->suspend)
            );
        }

        foreach ($this->repetitions_to_update as $interval) {
        
        }
    }
    /**
     *
     */
    private function _loadRepetitions($id, $order)
    {
        if ($order) {
            $repetition = $this->_dbr->getAll("SELECT * FROM repetition WHERE auction_id=$id order by $order");
        } else {
            $repetition = $this->_dbr->getAll("SELECT * FROM repetition WHERE auction_id=$id");
        }
        
        if (PEAR::isError($repetition)) {
            print_r($repetition);
            return;
        } elseif (!$repetition) {
            return null;
        }
        
        foreach ($repetition as $i => $interval) {
            $interval->local = $interval->start_at;
            $interval->date_prefix = $interval->id . 'repdate_';
            $interval->time_prefix = $interval->id . 'reptime_';
            $wd = array();
            if ($interval->weekdays & 1) $wd[0] = 1;
            if ($interval->weekdays & 2) $wd[1] = 1;
            if ($interval->weekdays & 4) $wd[2] = 1;
            if ($interval->weekdays & 8) $wd[3] = 1;
            if ($interval->weekdays & 16) $wd[4] = 1;
            if ($interval->weekdays & 32) $wd[5] = 1;
            if ($interval->weekdays & 64) $wd[6] = 1;
            $interval->weekdays = $wd;
            
            $repetition[$interval->id] = $interval;
            unset($repetition[$i]);
            //$repetition[$i] = $interval;
        }
        return $repetition;
    }
    /**
     *
     */
     protected function _getShipping()
     {
        if ($this->shipping) {
            return $this->shipping;
        }
        
        $this->shipping = ['options' => [], 'data' => []];
        $details = \Saved::getDetails($this->id);
        $siteid = $this->getSavedParam('siteid');
        
        $this->shipping['options']['availability'] = $this->_getAvailability();
        
        $this->shipping['options']['DispatchTimeMax'] = array(
            '0' => '0',
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '10' => '10',
            '15' => '15',
            '20' => '20',
            '30' => '30',
        );
        
        $shipping_container_rows = $this->_dbr->getAll("SELECT par_key, par_value FROM saved_params 
            WHERE saved_id = {$this->id} AND par_key LIKE 'ShippingContainer[%'");
            
        $this->shipping['data']['shipping_container'] = self::convertSavedParamsToArray($shipping_container_rows);
            
        $this->shipping['options']['shipping_methods'] = $this->_dbr->getAssoc("select name, description 
            from api_ShippingService
            where id<50000 
            and country_code='" . SiteToCountryCode($siteid) . "' order by id");

        $this->shipping['options']['alldomesticcountries'] = $this->_dbr->getAssoc("select ShippingLocation, Description 
            from api_ShippingLocations
            where siteid='$siteid' order by ShippingLocation");
            
        $this->shipping['options']['intmethods'] = $this->_dbr->getAssoc("select name, description 
            from api_ShippingService
            where id>=50000 and country_code='" . SiteToCountryCode($siteid) . "' order by id");
            
        $this->shipping['options']['currency'] = siteToSymbol($siteid);
        
        $international_shipping_container = $this->__getShopSaved()->getSellerInfo()->getInternationalShipping();

        $margin = $this->_getMargin();
        $shipping_cost = [];
        foreach ($international_shipping_container as $key => $row) {
            foreach ($row['InternationalShippingCountry'] as $country_code) {
                $q = "select shipping_cost
                    from saved_auctions sa
                    join saved_params sp_offer 
                        on sa.id=sp_offer.saved_id 
                        and sp_offer.par_key='offer_id'
                    join saved_params sp_username 
                        on sa.id=sp_username.saved_id 
                        and sp_username.par_key='username'
                    join saved_params sp_site 
                        on sa.id=sp_site.saved_id 
                        and sp_site.par_key='siteid'
                    join seller_information si 
                        on si.username=sp_username.par_value
                    join translation
                        on language=sp_site.par_value
                        and translation.id=sp_offer.par_value
                        and table_name='offer' and field_name='{$margin->shipping_plan_id_fn}'
                    join shipping_plan_country spc on shipping_plan_id=translation.value
                        and spc.country_code = '$country_code'
                    where sa.id=" . $this->id;
                $shipping_cost[$country_code] = $this->_dbr->getOne($q);
            }
        }
        
        $this->shipping['options']['InternationalShippingContainer'] = $international_shipping_container;
        $this->shipping['options']['shipping_cost'] = $shipping_cost;
        
        $this->shipping['data']['InternationalShippingInactive'] = [];
        
        $q = "select spc_all.country_code, spc_all.shipping_cost-IF(si.free_shipping or si.free_shipping_total or IFNULL(t_o.value,0) or offer.{$margin->shipping_plan_fn}_free
		,spc.shipping_cost,0)
								from saved_auctions sa
				join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
				join offer on offer.offer_id=sp_offer.par_value
				join seller_information si on si.username='" . $this->getSavedParam('username') . "'
								left join translation t_o
									on t_o.language='" . $this->getSavedParam('siteid') . "'
									and t_o.id=sp_offer.par_value
									and t_o.table_name='offer' and t_o.field_name='{$margin->shipping_plan_fn}_free_tr'
								join translation
									on translation.language='" . $this->getSavedParam('siteid') . "'
									and translation.id=sp_offer.par_value
									and translation.table_name='offer' and translation.field_name='{$margin->shipping_plan_fn}_id'
								join shipping_plan_country spc on spc.shipping_plan_id=translation.value
								join shipping_plan_country spc_all on spc_all.shipping_plan_id=translation.value
								and spc.country_code = si.defshcountry
								where sa.id=" . $this->id;
        $this->shipping['options']['shipping_cost_seller_all'] = $this->_dbr->getAssoc($q);        
        
        return $this->shipping;
     }
    /**
     * Setter for `shipping` fiels
     */
    protected function _setShipping($in)
    {
        $this->_getShipping();
        
        $this->update_shipping_container = true;
        $this->shipping['data']['shipping_container'] = $in['data']['shipping_container'];
    }
    protected function _saveShipping()
    {
        if ($this->update_shipping_container) {
            $r = $this->_db->query('DELETE FROM `saved_params` 
                WHERE saved_id = ' . $this->id . ' AND par_key LIKE "ShippingContainer[%"');
            
            foreach ($this->shipping['data']['shipping_container'] as $key => $row) {
                foreach ($row as $field => $value) {
                    $par_key = mysql_escape_string("ShippingContainer[$key][$field]");
                    $par_value = mysql_escape_string($value);
                    $r = $this->_db->query("INSERT INTO `saved_params` SET 
                        par_key = '$par_key', 
                        par_value = '$par_value', 
                        saved_id = " . $this->id);
                }
            }
        }
    }
     /**
      *  Get `saved_auctions` row
      */
     protected function _getSavedAuction()
     {
        if (!$this->saved_auction) {
            $this->saved_auction = $this->_dbr->getRow("SELECT 
                    *, 
                    DATE_ADD(FROM_UNIXTIME(last_repeat), 
                    INTERVAL repeat_days DAY) as start_time
                FROM saved_auctions 
                WHERE id=" . $this->id); 
        }
        return $this->saved_auction;
     }
    /**
     *
     */
    protected function _getLastFixes()
    {
        $q = "select * from (
               select auction.winning_bid, (SELECT cav.description FROM config_api ca
                LEFT join config_api_values cav on ca.par_id=cav.par_id and ca.value=cav.value
                where ca.par_id =7 and siteid=auction.siteid limit 1) curr,
            listings.server, listings.username, seller_information.seller_name, listings.siteid, listings.auction_number, IFNULL(auction.txnid,'??') as txnid, listings.start_time, offer_name.name as alias, listings.end_time
                            , (select ROUND(sum(
                                (ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price
                            + ac.shipping_cost -ac.vat_shipping - ac.effective_shipping_cost
                            + ac.COD_cost -ac.vat_COD - ac.effective_COD_cost
                            - ac.packing_cost/ac.curr_rate)),2)
                                 from auction_calcs ac where auction_number=auction.auction_number and txnid=auction.txnid) as brutto_income
            , listings.finished
            , listings.params
            , (select count(*) from autoread_scan where result=0 and auction_number=listings.auction_number) scanned_all
            , (select count(*) from autoread_scan where result=0 and auction_number=listings.auction_number
                and datetime between DATE_SUB(NOW(), INTERVAL 24 hour) and NOW()) scanned_24
            from listings
            left join auction on auction.auction_number=listings.auction_number
            left join offer_name on listings.name_id=offer_name.id
            left join seller_information on listings.username=seller_information.username
            where listings.saved_id={$this->id} #and IFNULL(auction.deleted,0)=0
                        and not exists(select null from auction_comment where auction.auction_number=auction_comment.auction_number
                            and auction.txnid=auction_comment.txnid
                            and `comment`
                            like concat(' Second chance auction of%'))
            ) t order by end_time desc, start_time desc LIMIT 0 , 20";
        
        $this->last_fixes = $this->_dbr->getAll($q);
        
        foreach ($this->last_fixes as $id => $auction) {
            $this->last_fixes[$id]->_cgi_eBay = getParByName($this->_db, $this->_dbr, $auction->siteid, "_cgi_eBay");
            $this->last_fixes[$id]->params = unserialize($this->last_fixes[$id]->params);
            $this->last_fixes[$id]->ListingDuration = $this->last_fixes[$id]->params['Item']['ListingDuration'];

        }
    }
    
    
    /**
	 * Get Category params
	 */
	protected function _getCategoryParams()
	{
        $this->category_params = [];
        $func = create_function('$a', 'return $a->CategoryName;');        

        $category = (int)$this->getSavedParam('category');
        if ($category) {
            $this->category_params['category'] = $this->__getCategoryNames($category);
            $category_path = Category::path($this->_db, $this->_dbr, $this->getSavedParam('siteid'), $category);
            $this->category_params['category_name'] = implode('::', array_map($func, $category_path));
            
            $category_row_id = $this->_dbr->getOne("select id from saved_params where saved_id={$this->id} and par_key='category'");
            $this->category_params['category_log'] = $this->_dbr->getCol("select 
                CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                from total_log
                join users on users.system_username=total_log.username
                where table_name='saved_params' and tableid=$category_row_id and field_name='par_value'
                order by updated desc limit 5");            
        }

        $category2 = (int)$this->getSavedParam('category2');
        if ($category2) {
            $this->category_params['category2'] = $this->__getCategoryNames($category2);
            $category2_path = Category::path($this->_db, $this->_dbr, $this->getSavedParam('siteid'), $category2);
            $this->category_params['category2_name'] = implode('::', array_map($func, $category2_path));
            
            $category_row_id = $this->_dbr->getOne("select id from saved_params where saved_id={$this->id} and par_key='category2'");
            $this->category_params['category2_log'] = $this->_dbr->getCol("select 
                CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                from total_log
                join users on users.system_username=total_log.username
                where table_name='saved_params' and tableid=$category_row_id and field_name='par_value'
                order by updated desc limit 5");              
        }
	}
    /**
     * 
     */
    private function __getCategoryNames($category)
    {
        $vars = \Saved::getDetails($this->id);
        $siteid = $this->getSavedParam('siteid');

        $qrystr = "select Sp_Names.*,
				IFNULL(n1.id, Sp_Names.id) ParentId
				, d.id deleted
			from Sp_Names
			left join Sp_Names n1 on n1.siteid=$siteid and n1.CategoryID=$category
				and n1.Name=Sp_Names.ParentName
			left join Sp_Names_Deleted d on d.saved_id={$this->id} and d.categoryid=$category
				and d.NameID=Sp_Names.id
			where Sp_Names.siteid=$siteid and Sp_Names.CategoryID=$category
			and IFNULL(Sp_Names.saved_id,'{$this->id}')='{$this->id}' and Sp_Names.deleted=0";

		$names = $this->_dbr->getAll($qrystr);
        foreach($names as $key=>$name) {
			$qrystr = "select Value f1, Value f2 from Sp_Values where siteid=$siteid and CategoryID=$category
				and name='{$name->Name}'";
			$names[$key]->Values = $this->_dbr->getAssoc($qrystr);
			$names[$key]->ids = array();
			for($i=0;$i<$names[$key]->MaxValues;$i++) $names[$key]->ids[$i] = $i;
			$qrystr = "select par_value f1, par_value f2 from saved_params where saved_id={$this->id} and par_key like 'sp[{$name->id}]%'
				and par_value <> ''
				and par_value not in (\"".implode('", "', $names[$key]->Values)."\")
			";
			$names[$key]->MultiValues = $this->_dbr->getAssoc($qrystr);
            
            $names[$key]->data = $vars['sp'][$name->id];
		}
        
        return $names;
    }
    
	/**
	 * Get unserialized SA details
	 * @return array
	 */
	public function getSavedParam($field = null)
	{
		if (!isset($this->saved_params))
		{
			if ($field)
				$to_collect = array_merge($this->_default_saved_param_fields, array($field));
			else	
				$to_collect = $this->_default_saved_param_fields;
				
            $seller_saved_params = $this->__getShopSaved()->getSellerInfo()->getEbaySavedParams();
                
			$fields = implode("','", $this->_default_saved_param_fields);
			$saved_params = $this->_dbr->getAssoc('SELECT `par_key`, `par_value`
				FROM `saved_params`
				WHERE `par_key` IN (\'' . $fields . '\') 
				AND `saved_id` = ' . $this->id);
                
            $this->saved_params = $saved_params + $seller_saved_params;
		}
		
		if ($field && !isset($this->saved_params[$field]))
			$this->saved_params[$field] = $this->_dbr->getOne('SELECT `par_value`
				FROM `saved_params`
				WHERE `par_key` =  "' . $field . '"
				AND `saved_id` = ' . $this->id);
                
		return $field ? (isset($this->saved_params[$field]) ? $this->saved_params[$field] : '') : $this->saved_params;
	}
    /**
     * Get info for button block
     */
	protected function _getButtons()
    {   
        $server = $this->getSellerInfo()->getServer();
    
        $shop_saved_buttons = $this->__getShopSaved()->_getButtons();
        $this->buttons = [
            'auction_name' => 'Ebay - ' . $shop_saved_buttons['master_auction_name'],
            'update_url' => "/revise_auctions.php?auction_number={$this->id}",
            'start_url' => "http://$server/list_auction.php?id={$this->id}"
        ];
    }
    /**
     * Find corresponding shop SA
     * @param int $saved_id
     */
    public static function findShopSavedId($ebay_saved_id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $params = $dbr->getAssoc("SELECT `par_key`, `par_value`
            FROM `saved_params`
            WHERE `par_key` IN ('siteid', 'offer_id') 
            AND `saved_id` = " . $ebay_saved_id);
        $siteid = $params['siteid'];
        $offer_id = $params['offer_id'];
        
        $shop_username = $dbr->getOne("SELECT `shop`.`username` 
            FROM `shop` 
            JOIN `site_defshop` 
                ON `site_defshop`.`siteid` = `shop`.`siteid` 
                AND `site_defshop`.`shop_id` = `shop`.`id`
            WHERE `shop`.`siteid` = $siteid");
        
        $shop_saved_id = $dbr->getOne("select sp_offer.saved_id from saved_params sp_offer
            join saved_params sp_siteid 
                on sp_siteid.par_key = 'siteid' 
                and sp_siteid.par_value = '$siteid'
                and sp_siteid.saved_id = sp_offer.saved_id
            join saved_params sp_usermane 
                on sp_usermane.par_key = 'username' 
                and sp_usermane.par_value = '$shop_username'
                and sp_usermane.saved_id = sp_offer.saved_id
            where sp_offer.par_key = 'offer_id' and sp_offer.par_value = $offer_id");
        
        return $shop_saved_id;
    }
    /**
     * Get data from `saved_params` and `ebay_seller_saved_params` 
     * 
     * @param int $saved_id
     * @param bool $default Use old details or not 
     * @return array
     */
    public static function getDetails($ebay_saved_id, $default = true) 
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $shop_saved_id = self::findShopSavedId($ebay_saved_id);
        
        if ($shop_saved_id) {
            $username = $dbr->getOne("SELECT `par_value`
                FROM `saved_params`
                WHERE `par_key` = 'username'
                AND `saved_id` = " . $shop_saved_id);

            $sellerinfo = new SellerInfo($db, $dbr, $username, 'english');
            $seller_vars = $sellerinfo->getEbaySavedParams();
        }
        
        $ebay_vars = \Saved::getDetails($ebay_saved_id);
        
        return $ebay_vars + $seller_vars;
    }    
}