<?php
require_once 'PEAR.php';
require_once 'lib/SellerInfo.php';
require_once 'lib/Offer.php';

class Saved
{
	/**
	 * Data was changed by other user message
	 */
	const MESSAGE_DATA_IS_CHANGED = 'Data is already changed';
	/**
	 * Data was changed by other user message
	 */
	const MESSAGE_CUSTOM_PARAM_NOT_UNIQUE = 'The value should be unique.';
    /**
     * @const Default shop dimensions - cm
     */
    const DIMENSION_CM = 1;
    /**
     * @const Default shop dimensions - inch
     */
    const DIMENSION_INCH = 2;    
    /**
    * Saved ID
    * @var int
    */
	public $id;
	
	public static $doc_data_translations;
    /**
    * Reference to database
    * @var object
    */
    private $_db;
	private $_dbr;
    /**
    * Holds data record
    * @var object
    */
    public $data;
    /**
    * Similair SAs
    * @var array
    */
	public $sims;
	public static $MULANG_FIELDS = array('ShopDesription', 'ShopSAKeywords', 'ShopSADescription', 'ShopSAAlias'
,'inactivedescriptionShop1','inactivedescriptionShop2','inactivedescriptionShop3','inactivedescriptionShop4','inactivedescriptionShop5','inactivedescriptionShop6'
,'descriptionShop1','descriptionShop2','descriptionShop3','descriptionShop4','descriptionShop5','descriptionShop6'
,'descriptionShop_comment1','descriptionShop_comment2','descriptionShop_comment3','descriptionShop_comment4','descriptionShop_comment5','descriptionShop_comment6'
,'descriptionTextShop1','descriptionTextShop2', 'descriptionTextShop3'
, 'amazon_bp_1', 'amazon_bp_2', 'amazon_bp_3', 'amazon_bp_4', 'amazon_bp_5', 'amazon_bp_6', 'amazon_bp_7', 'amazon_bp_8', 'amazon_bp_9', 'amazon_bp_10'
, 'amazon_st_1', 'amazon_st_2', 'amazon_st_3', 'amazon_st_4', 'amazon_st_5', 'amazon_st_6', 'ShopShortTitle'
, 'ShopMultiDesription'
);
	private $mulang_fields_original = array('ShopDesription', 'ShopSAKeywords', 'ShopSADescription', 'ShopSAAlias'
,'inactivedescriptionShop1','inactivedescriptionShop2','inactivedescriptionShop3','inactivedescriptionShop4','inactivedescriptionShop5','inactivedescriptionShop6'
,'descriptionShop1','descriptionShop2','descriptionShop3','descriptionShop4','descriptionShop5','descriptionShop6'
,'descriptionShop_comment1','descriptionShop_comment2','descriptionShop_comment3','descriptionShop_comment4','descriptionShop_comment5','descriptionShop_comment6');

	protected $_default_saved_param_fields = array(
		'color_id','master_sa','offer_id', 'material_id', 'siteid', 'brand', 'shipping_art', 'ShopRightOfReturn', 
		'ShopPrice', 'ShopMinusPercent', 'ShopHPrice', 'saved_id', 'username', 'auction_name', 'ean_code', 'total_carton_number',
		'ShopPriceTemp', 'ShopPriceTempDays', 'shopAvailableDate', 'ShopPriceTempUntil', 'snooze_date_shop',
        'dedicated_us', 'dedicated_eu'
	);

    /**
     * Details from saved_auctions table
     * @var type array
     */
    private $_details;
    /**
     * Used to store debug info
     * @var array
     */
    private $_debug = [];
    /**
     * Timer
     * @var float
     */
    private $_timer;
    
    /**
     * 
     * @param type $db
     * @param type $dbr
     * @param type $id
     * @param type $fields
     * @param type $with_log
     * @param type $additional
     * @return type
     * @throws Exception
     */
	public function __construct(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id, $fields=[], $with_log = 0, $additional = [], $langs = null)
	{
        $this->_timer = microtime(true);
        
		$this->id = (int)$id;
		
        $this->_db = $db;
		$this->_dbr = $dbr;
		
		extract($additional);
        
        if ($langs) {
            $this->_langs = $langs;
        }
		
		if ($this->id) {
			$q = "SELECT * FROM `saved_auctions` WHERE `id` = '{$this->id}' LIMIT 1";
			$this->data = $this->_dbr->getRow($q);
		} 
        else if ($username && !$this->id && $master_id) {
			$this->_createSlave($username, $master_id);
		} 
        else {
			throw new Exception('Error loading SA: mismatch of parameters');
		}
		
		if (!$this->data)
			throw new Exception('Error loading SA: object not found');

		$this->_shop = $this->_dbr->getRow("SELECT * FROM `shop` 
			WHERE `shop`.`username` = '" . $this->getSavedParam('username') . "' 
			AND `shop`.`inactive` = 0
			LIMIT 1");
			
		if (!$this->getSavedParam('siteid'))
			$this->_details['siteid'] = $this->_shop->id;
        
        $this->_details = $dbr->getOne("SELECT details FROM saved_auctions WHERE id={$this->id}");
        $this->_details = unserialize($this->_details);
        
        if (isset($notget) && $notget)
        {
            return;
        }
        
		foreach($fields as $field) 
		{
            $this->loadField($field, $with_log);
		}
	}
    /**
     * Possibility to load fields after model has been created
     * @var string $field
     */
    public function loadField($field, $with_log) {
        $this->_toDebug($field);
        
        if (!$this->_takeFromMaster($field))
        {
            if ($this->id && $this->_isMulangField($field)) 
            {
                $this->_loadMulangField($field, $with_log);
            } 
            // example: field "shop_params" will look for _getShopParams() method
            else if (method_exists($this, '_get' . self::snakeToCamel($field)))
            {
                $method = '_get' . self::snakeToCamel($field);
                $this->$method();
            }
        }
        
        return $this->$field;
    }
    /**
     * Load mulang field and fill languages with empty value
     * @param string $field
     * @param int $with_log
     * @return array
     */ 
    private function _loadMulangField($field, $with_log = 0)
    {
        $res = mulang_fields_Get(array($field), 'sa', $this->id, $with_log);
        $field_key = $field.'_translations';
        
        foreach ($res as $field_data => $langs) {
            foreach ($langs as $lang => $data) {
                if (!in_array($lang, array_keys($this->_langs))) {
                    unset($res[$field_key][$lang]);
                }
            }

            // fill empty langs 
            $diff = array_diff(array_keys($this->_langs), array_keys($langs));
            foreach ($diff as $lang)
            {
                $empty = new stdClass();
                $empty->language = $lang;
                $empty->value = '';
                $empty->table_name = 'sa';
                $empty->field_name = $field;
                $res[$field_key][$lang] = $empty;
            }
        }

        $this->$field = $res[$field_key];
        return $this->$field;
    }
	/*
	 * Create new slave
	 */
	protected function _createSlave($username, $master_sa)
	{
		$master_sa = (int)$master_sa;
		$username = mysql_escape_string($username);
        
        $double = $this->_dbr->getOne("SELECT COUNT(*) FROM `saved_params` `master`
            JOIN `saved_params` `username` 
                ON `username`.`saved_id` = `master`.`saved_id` 
                AND `username`.`par_key` = 'username'
            WHERE `master`.`par_key` = 'master_sa' 
                AND `master`.`par_value` = $master_sa
                AND `username`.`par_value` = '$username'");
                
        if ($double) {
            throw new Exception('Slave already exists');
        }
        
        $same_as_master = $this->_dbr->getOne("SELECT `id` FROM `saved_params` 
            WHERE `par_key` = 'username' AND `par_value` = '$username' AND `saved_id` = $master_sa LIMIT 1");
		
        if ($same_as_master) {
            throw new Exception('Can\'t create slave equal to master');
        }
        
		$siteid = $this->_dbr->getOne("SELECT `siteid` FROM `shop` WHERE `username` = '$username' AND `shop`.`inactive` = 0");
		
		$r = $this->_db->query("INSERT INTO `saved_auctions` SET 
			`details` = '', `inactive` = 1, `last_repeat` = 0, `repeat_days` = 0, `nrepeats` = 0,
			`stop_empty` = 0, `details_google` = '', `use_notsold` = 0, `responsible_uname` = ''");

		if (PEAR::isError($r)) { aprint_r($r); die();}
		$this->id = $this->_db->getOne('select LAST_INSERT_ID()');
		
		$r = $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
			VALUES({$this->id}, 'master_sa', $master_sa), ({$this->id}, 'username', '$username'), ({$this->id}, 'siteid', '$siteid')");
            
        $default_warehouses = $this->_dbr->getCol("SELECT warehouse_id FROM seller_stop_empty_warehouse WHERE username='$username'");
        foreach ($default_warehouses as $key => $warehouse_id) {
            $r = $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                VALUES({$this->id}, 'stop_empty_warehouse_shop[$key]', $warehouse_id)");
        }

		if (PEAR::isError($r)) { aprint_r($r); die();}
		
		$q = "SELECT * FROM `saved_auctions` WHERE `saved_auctions`.`id` = {$this->id} LIMIT 1";
		$this->data = $this->_dbr->getRow($q);
	}
	/**
	 * Field getter
	 */
	public function __get($field)
	{
		if ($field == '_langs' && !isset($this->_langs)) {
			$this->_langs = getLangsArray();
            
            if ($this->isSlave()) {
                $avalangs = $this->_dbr->getCol("select v.value  
                    from  config_api_values v
                    left join seller_lang sl on sl.lang=v.value and sl.username='" . $this->getSavedParam('username') . "'
                    where v.par_id=6 
                    and not v.inactive
                    and sl.useit = 1
                    order by sl.ordering");
                $this->_langs = array_intersect_key($this->_langs, array_flip($avalangs));
            }
		} elseif ($field == '_offer' && !isset($this->_offer) && $this->getSavedParam('offer_id')) {
			$this->_offer = new Offer($this->_db, $this->_dbr, $this->getSavedParam('offer_id'));
		} elseif ($field == '_sellerinfo' && !isset($this->_sellerinfo) && $this->getSavedParam('username')) {
			$this->_sellerinfo = new SellerInfo($this->_db, $this->_dbr, $this->getSavedParam('username'), 'english');
		} elseif ($field == 'available_sas' && !isset($this->available_sas)) {
			$this->getAvailableSas();
		}
        elseif ( ! $field)
        {
            return null;
        }
		
        return $this->$field;		
	}
    /**
     * Duplicate SA to new SA. Only for slaves.
     */
    public function duplicateShopSlave($inactive = true)
    {
        if ($this->isSlave()) {
            $vars = self::getDetails($this->id);
            
            $ignore = ['ean_code', 'ratings_inherited_from'];
            
            foreach ($ignore as $field) {
                unset($vars[$field]);
            }
        
            $this->_db->execParam("INSERT INTO saved_auctions (details, last_repeat,
                scheduled, repeat_days, nrepeats, stop_empty, use_notsold, export, inactive)
                (select ?, last_repeat,
                scheduled, repeat_days, nrepeats, stop_empty, use_notsold, 0, " . (int)$inactive . "
                from saved_auctions WHERE id=?)", [serialize($vars), $this->id]);

            $new_saved_id = (int)$this->_db->queryOne('SELECT LAST_INSERT_ID() FROM saved_auctions');
            
            $this->_toDebug('DUPLICATING ' . $this->id . ' -> ' . $new_saved_id . '(master: ' . $this->saved_params['master_sa'] . ') ');

            $for_insert = [];
            foreach ($this->saved_params as $key => $value) {
                if (!in_array($key, $ignore))
                    $for_insert[] = "($new_saved_id, '$key', '$value')";
            }
            
            $q = 'INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`) VALUES'.implode(', ', $for_insert);
            $this->_db->query($q);
            
            return $new_saved_id;
        } else {
            return false;
        }
    }
	/**
	 * Take a string_like_this and return a StringLikeThis
     * @param string 
     * @return string 
	 */
	static function snakeToCamel($val) 
	{
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $val)));  
	}  
	/*
	 * Checks is field should be taken from master
	 */
	protected function _takeFromMaster($field)
	{
		$switch = 'master_' . $field;
		return $this->isSlave() && isset($this->_shop->$switch) && $this->_shop->$switch;
	}
	/**
	 * Get languages
	 * @return array
	 */ 
	public function getLangs()
	{
		return $this->_langs;
	}
	/**
	 * Set SA property
	 * @param string
	 */
	public function set($field, $value, $old_value = null)
	{
		// example: field "shop_params" will look for _setShopParams() method
		if (method_exists($this, '_set' . self::snakeToCamel($field)))
		{
			$method = '_set' . self::snakeToCamel($field);
			$this->$method($value, $old_value);
		}
		else if ($this->_isMulangField($field))
		{
			foreach ($value as $lang => $data)
			{
                if (is_string($data)) {
                    $data = ['value' => $data];
                }
                
                if (isset($old_value[$lang]) && is_string($old_value[$lang])) {
                    $old_value[$lang] = ['value' => $old_value[$lang]];
                }
                
				if (isset($this->{$field}[$lang]->value) && isset($old_value[$lang]['value']) && $this->{$field}[$lang]->value != $old_value[$lang]['value'])
				{
					throw new Exception(self::MESSAGE_DATA_IS_CHANGED);
				}
				else
				{
					$this->{$field}[$lang] = is_array($data) ? array_to_object($data) : $data;
				}
			}
		}
		else
		{
			$this->$field = $value;
		}
	}
    
    /**
     * Generate HTML code of description
     * @param integer $template_id
     */
    public function generateHTML($template_id = 0, $lang = 'english')
    {
        global $smarty;
        
        $shopCatalogue = new \Shop_Catalogue($this->_db, $this->_dbr, 0, $lang);
        
        $template_details = $shopCatalogue->getOfferDetails($this->id);

        $template = (new \SavedTemplates($template_id))->get();
        if ($template->data['blocks'] && is_array($template->data['blocks']))
        {
            foreach ($template->data['blocks'] as $_block_id => $_block)
            {
                if ($_block->layouts && is_array($_block->layouts))
                {
                    foreach ($_block->layouts as $_layout_id => $_layout)
                    {
                        if ($_layout->values && is_array($_layout->values))
                        {
                            foreach ($_layout->values as $_value_id => $_value)
                            {
                                if ($_value && is_string($_value) && strpos($_value, '[[') !== false)
                                {
                                    $_value = preg_replace('#\[\[.*?\_f(\d+)\]\]#iu', '[[description_\\1]]', $_value);
                                    $_value = preg_replace('#\[\[.*?\_c(\d+)\]\]#iu', '[[content_\\1]]', $_value);

                                    $_value = str_ireplace('%%language%%', $shopCatalogue->_shop->lang, $_value);
                                    $_value = str_ireplace(array_keys($template_details), array_values($template_details), $_value);
                                    $_value = str_ireplace(']]', '_' . $shopCatalogue->_shop->lang . ']]', $_value);
                                    $_value = str_ireplace(array_keys($template_details), array_values($template_details), $_value);

                                    $_value = preg_replace('#\[\[.*?\]\]#iu', '', $_value);
                                    $template->data['blocks'][$_block_id]->layouts[$_layout_id]->values[$_value_id] = $_value;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $offer = new stdClass;
        
        $saved_pic = new \SavedPic($this->id);
        $offer->pics = $saved_pic->withText($lang)->get(true);
        if ( ! $shopCatalogue->_shop->no_video) {
            $video = \Saved::getDocs($this->_db, $this->_dbr, $this->id, $inactive = " and inactive=0 ", $lang, $shopCatalogue->_shop->lang4pics, 0, 0, 0);
            foreach ($video as $doc) {
                if ($doc->youtube_code) {
                    $offer->pics[] = $doc;
                }
            }
        }
        
        $offer->docs = \Saved::getDocs($this->_db, $this->_dbr, $this->id, $inactive = " and inactive=0 ", $lang, $shopCatalogue->_seller->data->default_lang, 1, 0, 1);
        
        $offer->data->rating_statistic = $shopCatalogue->getRating($this->id);
        
		$qrystr = "select SQL_CALC_FOUND_ROWS sq.id,
						DATE(sq.`datetime`) `datetime`,
				          sq.`uri`,
				          sq.`saved_id`,
				          sq.`shop_id`,
				          sq.`lang`,
				          sq.`question`,
				          sq.`marked`,
				          sq.`published`,
				          sq.`answer`,
						  shop.name shop,
						  count(sqr.id) as votes
				from shop_question sq
				join shop on shop.id=sq.shop_id
				left join shop_question_rating sqr on sqr.question_id = sq.id
				where 1 and sq.saved_id='" . $this->id . "' and sq.published
				group by sq.id
				order by sq.`datetime` desc limit 0, " . $shopCatalogue->_shop->questions_per_page;
		$questions = $this->_dbr->getAll($qrystr);
        
		$questions_count = $this->_dbr->getOne("SELECT FOUND_ROWS()");
        if ($questions_count) 
        {
			$questions_sort_params = ['id' => $this->id, 'lang' => $lang, 'div' => 'div_questions', 'full' => 0];
			$smarty->assign('questions_sort_params', $questions_sort_params);
			$smarty->assign('questions_sort', 'newest');
			$smarty->assign('page', 1);

			if ($questions_count > $shopCatalogue->_shop->questions_per_page)
            {
                $pagination = split_string_action($questions_count, $shopCatalogue->_shop->questions_per_page, 1, 'fill_shop_questions(' . $this->id . ', [[p]], "' . $lang . '", "div_questions", 0)', 5, 5);
                $smarty->assign('split_string', $pagination);
            }

			$question_options = $this->_dbr->getAssoc("SELECT shop_sorting.url, translation.value
					FROM shop_sorting
					LEFT JOIN translation ON translation.id = shop_sorting.sort_type_id
					AND table_name = 'shop_sorting_sort_type'
					AND `field_name` = 'title'
					AND `language` = '" . $shopCatalogue->_shop->lang . "'
					WHERE page_type_id = 4");
			$smarty->assign("question_options", $question_options);
		}
        
		$smarty->assign("translationShop", $shopCatalogue->_shop->english_shop);
		if (count($questions))
        {
            $questions = $smarty->fetch('shop/_shop_offer_question.tpl');
        } 
        else 
        {
            $questions = '';
        }
        
        $limit = $shopCatalogue->_shop->ratings_per_page_article ? $shopCatalogue->_shop->ratings_per_page_article : $shopCatalogue->_shop->ratings_per_page;

        $ratings = $shopCatalogue->fill_shop_ratings((int)$this->id, 0, $shopCatalogue->_shop->username, 
                $limit, 1, 'div_rating_tab', 1, 0);
        $ratings = isset($ratings->template) ? $ratings->template : '';

        $smarty->assign([
            'cart' => $this->id, 
            'config' => \Config::getAll(), 
            'offer' => $offer, 
            'shopCatalogue' => $shopCatalogue, 
            'ratings' => $ratings, 
            'questions' => $questions, 
            'questions_count' => $questions_count, 
            'sa_template' => $template, 
            'tpls' => [
                'grid_elements' => 'shop/grid_elements.tpl', 
                '_dynamic_question_block' => 'shop/_dynamic_question_block.tpl', 
            ], 
        ]);
        
        return $smarty->fetch('shop/_offer_description_grid.tpl');
    }
    
	/**
	 * Set shpp params data
	 */
	private function _setDimensions($data, $old = null)
	{
		$this->_saved_dimensions->setData($data, $old);
	}
	/**
	 * Set shpp params data
	 */
	private function _setShopParams($data, $old_data = null)
	{
		$this->_saved_shop_params->setData($data, $old_data);
	}
	/**
	 * Set sims data
	 */
	protected function _setSims($data, $old_data = null)
	{
		$this->_saved_sims->setData($data, $old_data);
	}
	/**
	 * Save sims data
	 */
	protected function _saveSims()
	{
		$this->_saved_sims->save();
	}
	/**
	 * Set saved params
	 */
	private function _setSavedParams($data, $old_data = null)
	{
        if (!isset($this->saved_params_to_update)) {
            $this->saved_params_to_update = [];
        }
		
		foreach ($data as $name => $value)
		{
			if (isset($old_data[$name]) && $old_data[$name] != $this->saved_params[$name])
			{
                $this->_toDebug('- CHANGED: ' . $name);
                $this->_toDebug(', CURRENT: ' . print_r($this->saved_params[$name], true));
                $this->_toDebug(', FRONTEND: ' . print_r($old_data[$name], true) . ' -');
				throw new Exception(self::MESSAGE_DATA_IS_CHANGED);
			}
			elseif (!isset($this->saved_params[$name]) || $this->saved_params[$name] != $value)
			{
				$this->saved_params[$name] = mysql_escape_string($value);
				$this->saved_params_to_update[] = $name;
			}
		}
	}
	/**
	 * Defines is it slave
	 */
	public function isSlave()
	{
		return (bool)$this->getSavedParam('master_sa');
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
				
			$fields = implode("','", $this->_default_saved_param_fields);
			$this->saved_params = $this->_dbr->getAssoc('SELECT `par_key`, `par_value`
				FROM `saved_params`
				WHERE `par_key` IN (\'' . $fields . '\') 
				AND `saved_id` = ' . $this->id);
		}
		
		if ($field && !isset($this->saved_params[$field]))
			$this->saved_params[$field] = $this->_dbr->getOne('SELECT `par_value`
				FROM `saved_params`
				WHERE `par_key` =  "' . $field . '"
				AND `saved_id` = ' . $this->id);
			
		return $field ? (isset($this->saved_params[$field]) ? $this->saved_params[$field] : '') : $this->saved_params;
	}
	/**
	 * Get SA icons
	 */
	protected function _getH2DescriptionLimitShop()
	{
		$this->H2_description_limit_shop = \Config::get(null, null, 'H2_description_limit_shop');
	}
	/**
	 * Get SA icons
	 */
	protected function _getIcons()
	{
		require_once 'lib/SavedIcons.php';
		$this->_saved_icons = new SavedIcons($this->id, $this->_shop->id, $this->_db, $this->_dbr);
		$this->icons = $this->_saved_icons->get();
	}
	/**
	 * Set SA icons
	 */
	protected function _setIcons($in, $old = null)
	{
		$this->_saved_icons->setData($in, $old);
	}
	/**
	 * Set SA icons
	 */
	protected function _saveIcons()
	{
		$this->_saved_icons->save();
	}
	/**
	 * Get SA dimensions
	 */
	protected function _getDimensions()
	{
		require_once 'lib/SavedDimensions.php';
		
		if (!$this->_offer || !$this->_sellerinfo)
			throw new Exception('Offer and Seller must be defined');
			
		$offer_id = $this->_offer->get('offer_id');
		$seller_id = $this->_sellerinfo->get('id');
		$this->_saved_dimensions = new SavedDimensions($this->id, $offer_id, $seller_id, $this->_db, $this->_dbr);
		$this->dimensions = $this->_saved_dimensions->get();
	}
	/*
	* Get SA titles
	* @return array
	*/
	protected function _getTitles()
	{
		$names_array = array();
		foreach ($this->_offer->names as $name) 
		{
			if (
				($this->_sellerinfo->data->seller_channel_id==1 && $name->lang==$this->_sellerinfo->data->default_lang)
				||
				($this->_sellerinfo->data->seller_channel_id==2 && ($name->lang=='german' || $name->lang=='french'))
				||
				($this->_sellerinfo->data->seller_channel_id==3 && $name->lang==$this->_sellerinfo->data->default_lang)
				||
				($this->_sellerinfo->data->seller_channel_id==4)
				||
				($this->_sellerinfo->data->seller_channel_id==5 && ($name->lang=='polish'))
			) 
			$names_array[$name->id]=$name->name;
		}

		$this->titles = array();
		foreach($this->_langs as $lang_id => $lang)
		{
			foreach ($this->_offer->names as $name) 
			{
				if (in_array($name->name, $names_array)) $this->titles[$name->lang][$name->id]=$name->name;
			}
		}
        
		return $this->titles;
	}
	/*
	 * Get SA others
	 * @return array
	 */
	protected function _getOthers()
	{
		if(!isset($this->_saved_shop_params))
			$this->_getShopParams();
		
		require_once 'lib/SavedOthers.php';
		$this->_saved_others = new SavedOthers($this->id, $this->_saved_shop_params->sa_names, $this->available_sas, $this->_db, $this->_dbr);
		$this->others = $this->_saved_others->get();
	}
	/**
	 * Set others data
	 */
	protected function _setOthers($data, $old = null)
	{
		$this->_saved_others->setData($data, $old);
	}
	/**
	 * Save others data
	 */
	protected function _saveOthers()
	{
		$this->_saved_others->save();
	}
	/** 
	 * Get Sims
	 */
	public function _getSims()
	{
		require_once 'lib/SavedSims.php';
		$this->_saved_sims = new SavedSims($this->id, $this->getSavedParam('username'), $this->available_sas, $this->_db, $this->_dbr);
		$this->sims = $this->_saved_sims->get();
	}
    /**
     * Get SA Seller
     * @return stdClass
     */
    public function getSeller()
    {
        return $this->_sellerinfo->data;
    }
    /**
     * Get SA Seller
     * @return stdClass
     */
    public function getSellerInfo()
    {
        return $this->_sellerinfo;
    }
    /**
     * Get info for button block
     */
	protected function _getButtons() 
    {   
        if ($this->buttons) {
            return $this->buttons;
        }
        
        $lang = $this->_sellerinfo->data->default_lang;
        $master_id = (int)$this->getSavedParam('master_sa');
        
        if (!$this->isSlave() || !$this->_takeFromMaster('ShopSAAlias')) {
            $url = !empty($this->ShopSAAlias[$lang]->value) ? $this->ShopSAAlias[$lang]->value . '.html' : '';
        } else {
            $alias = mulang_fields_Get(array('ShopSAAlias'), 'sa', $master_id);
            $url = $alias['ShopSAAlias_translations'][$lang]->value . '.html';
        }
        
        if ($this->isSlave()) {
            $master_auction_name = $this->_dbr->getOne("SELECT `par_value` FROM `saved_params` 
                WHERE `par_key` = 'auction_name' AND `saved_id` = $master_id LIMIT 1");
        } else {
           $master_auction_name = $this->getSavedParam('auction_name');
        }
        
        $searched = $this->_shop->url . '/' . $url;
        $redirects = $this->_dbr->getAll("SELECT `id`,`dest_url` FROM `redirect` 
            WHERE `req_url` LIKE '%" . $searched . "' AND `dest_url` <> '' AND `disabled` = 0
            UNION 
            SELECT `id`,`dest_url` FROM `redirect` 
            WHERE `src_url` LIKE '%" . $searched . "' AND `dest_url` <> '' AND `disabled` = 0");
        
        $this->buttons = [
            'default_url' => $url,
            'redirects' => $redirects,
            'master_auction_name' => $master_auction_name
        ];
        
        return $this->buttons;
    }
    
	public function getSims($force = false)
	{
		if (!is_array($this->sims) || $force)
		{
			$this->sims = Shop_Catalogue::sgetSims($this->_dbr, $this->id, false, $this->getSavedParam('username'));
		}
		return $this->sims;	
	}
	/**
	 * Collect available to assign saved
	 */
	public function getAvailableSas()
	{
		$this->available_sas = $this->_dbr->getAssoc("SELECT saved_auctions.id, CONCAT(saved_auctions.id,': ',sp_auction_name.par_value) name
			FROM saved_auctions
			left join saved_params sp_siteid on saved_auctions.id=sp_siteid.saved_id and sp_siteid.par_key='siteid'
			left join saved_params sp_offer on saved_auctions.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
			left join saved_params sp_username on saved_auctions.id=sp_username.saved_id and sp_username.par_key='username'
			LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id=saved_auctions.id and sp_auction_name.par_key = 'auction_name'
			left join seller_information si on si.username=sp_username.par_value
			left join seller_channel on seller_channel.id = si.seller_channel_id
			left join offer on offer_id=sp_offer.par_value
			where not IFNULL(saved_auctions.old,0) and si.seller_channel_id = 4 and sp_username.par_value='" . $this->getSavedParam('username') . "'
			order by saved_auctions.id");
	}
	/**
	 * update SA sims
	 * @param array
	 */
	public function updateSims($new_sims)
	{
		if (is_array($new_sims))
		{
			$sims = $this->getSims();
			
			$assigned = array();
			foreach ($sims as $sim)
			{
				$assigned[] = $sim->sim_saved_id;
			}
			$to_assign = array_diff($new_sims, $assigned);
			$to_delete = array_diff($assigned, $new_sims);
			foreach ($to_assign as $similar_sa)
			{
				$res = $this->_db->query("insert ignore into saved_sim (saved_id, sim_saved_id) values ({$this->id}, $similar_sa)");
				$res = $this->_db->query("insert ignore into saved_sim (saved_id, sim_saved_id) values ($similar_sa, {$this->id})");
			}
			foreach ($to_delete as $similar_sa)
			{
				$res = $this->_db->query("delete from saved_sim where saved_id = {$this->id} and sim_saved_id = $similar_sa");
				$res = $this->_db->query("delete from saved_sim where saved_id = $similar_sa and sim_saved_id = {$this->id}");
			}
			
			$this->getSims(true);
			
			return array_merge($to_assign, $to_delete);
		}
		else
		{
			return false;
		}
	}
	/**
	 * Get saved custom params
	 */
	protected function _getSavedCustomParams()
	{
        $q = "select t.par_key as `key`
            , group_concat(distinct scp.par_value) as `value`
            , (select max(scp.inactive) from saved_custom_params scp
                    where scp.par_key=t.par_key limit 1) as inactive
            from (select distinct(par_key) par_key from saved_custom_params) t
            left join saved_custom_params scp on scp.par_key=t.par_key and saved_id={$this->id}
            group by t.par_key order by t.par_key";

        $this->saved_custom_params = $this->_dbr->getAll($q);        
	}
	/**
	 * Set saved custom params
	 */
	protected function _setSavedCustomParams($data, $old_data = null)
	{
        if (is_array($data) && !empty($data)) {
            if (isset($data[0])) {
                $this->_new_saved_custom_param = $data[0];
                unset($data[0]);
            }
            
            $this->_changed_saved_custom_param = [];
            foreach($data as $_key => $row)  {
                foreach ($this->saved_custom_params as $saved_custom_param) {
                    if ($saved_custom_param->key == $row['key']) {
                        if (isset($old_data[$_key]) 
                            && ($old_data[$_key]['value'] != $saved_custom_param->value || $old_data[$_key]['inactive'] != $saved_custom_param->inactive)) {
                            throw new Exception(self::MESSAGE_DATA_IS_CHANGED);	                            
                        }
                        
                        if ($saved_custom_param->value != $row['value'] || $saved_custom_param->inactive != $row['inactive']) {
                            $this->_changed_saved_custom_param[$row['key']] = $row;
                        }
                    }
                }
            }
        }
    }
	/**
	 * Save saved custom params
	 */
	protected function _saveSavedCustomParams()
	{
        if (isset($this->_new_saved_custom_param) && is_array($this->_new_saved_custom_param) && !empty($this->_new_saved_custom_param)) {
            $key = mysql_escape_string($this->_new_saved_custom_param['key']);
            $value = mysql_escape_string($this->_new_saved_custom_param['value']);
            
            if (!$this->_isUniqueSavedCustomParam($key, $value)) {
                throw new Exception(self::MESSAGE_CUSTOM_PARAM_NOT_UNIQUE);	
            }
            
            $this->_db->query("INSERT INTO `saved_custom_params` (`saved_id`, `par_key`, `par_value`, `global`, `inactive`)
                VALUES ({$this->id}, '$key', '$value', 0, 0)");
            unset($this->_new_saved_custom_param);
        }
        
        if (isset($this->_changed_saved_custom_param) && is_array($this->_changed_saved_custom_param) && !empty($this->_changed_saved_custom_param)) {
            foreach ($this->_changed_saved_custom_param as $key => $row) {
                $key = mysql_escape_string($key);
                $value = mysql_escape_string($row['value']);
                $inactive = (int)$row['inactive'];
                
                if (!$this->_isUniqueSavedCustomParam($key, $value)) {
                    throw new Exception(self::MESSAGE_CUSTOM_PARAM_NOT_UNIQUE);	
                }
                
                $exist = $this->_dbr->getOne("SELECT `id` FROM `saved_custom_params` 
                    WHERE `saved_id` = {$this->id} AND `par_key` = '$key'");
                
                if ($exist) {
                    $res = $this->_db->query("UPDATE `saved_custom_params` 
                        SET `par_value` = '$value', `inactive` = $inactive
                        WHERE `saved_id` = {$this->id} AND `par_key` = '$key'"
                    );
                } else {
                    $this->_db->query("INSERT INTO `saved_custom_params` (`saved_id`, `par_key`, `par_value`, `global`, `inactive`)
                        VALUES ({$this->id}, '$key', '$value', 0, 0)");
                }   
            }
            unset($this->_changed_saved_custom_param);
        }
    }
    /**
     * Check is `saved_custom_params` is unique
     * @param string
     * @param string
     * @return bool
     */
     private function _isUniqueSavedCustomParam($key, $value)
     {
        $need_to_be_checked = $this->_dbr->getOne("SELECT `id` FROM `saved_custom_params_unique` WHERE `par_key` = '$key'");
        if ($need_to_be_checked) {
            $exist = $this->_dbr->getOne("SELECT `saved_id` FROM `saved_custom_params` 
                WHERE `par_key` = '$key' AND `par_value` = '$value' AND `inactive` = 0");
            
            return !$exist;
        }
        return true;
     }
	/**
	 * Get SA country
	 */
	protected function _getCountry()
	{
		$this->country = new stdClass();
		
        $siteid = $this->getSavedParam('siteid');
		if (isset($siteid))
			$this->country->data = $this->_dbr->getOne('select value from config_api_values 
				where par_id=5 and not inactive and value = ' . $siteid);
		else
			$this->country->data = null;
		
		$this->country->options['countries'] = $this->_dbr->getAssoc("select value, description from config_api_values 
			where par_id=5 and not inactive order by value");
	}
	/**
	 * Set new SA country
	 */
	protected function _setCountry($data, $old_data)
	{
		if ((int)$data['data'])
		{
			if (isset($old_data['data']) && $old_data['data'] != $this->country->data)
			{
				throw new Exception(self::MESSAGE_DATA_IS_CHANGED);	
			}
			$this->__country_changed = ($this->country->data != (int)$data['data']);
			$this->country->data = (int)$data['data'];
		}
	}
	/**
	 * Save new SA country
	 */
	protected function _saveCountry()
	{
		if ($this->__country_changed)
		{
			$this->_setSavedParams(['siteid' => $this->country->data]);
			$this->_saveSavedParams();
			unset($this->__country_changed);
		}
	}	
	/**
	 * Get SA Seller Name
	 */
	protected function _getSellerName()
	{
		$this->seller_name = new stdClass();
		$this->seller_name->options['sellers'] = SellerInfo::listArrayActive($this->_db, $this->_dbr);
		$this->seller_name->data = $this->getSavedParam('username');
	}
	/**
	 * Set new SA Seller Name
	 */
	protected function _setSellerName($data, $old_data)
	{
		$new_seller_name = mysql_escape_string($data['data']);
		if (!empty($new_seller_name))
		{
			if (isset($old_data['data']) && $old_data['data'] != $this->seller_name->data)
			{
				throw new Exception(self::MESSAGE_DATA_IS_CHANGED);	
			}
		
			$old_seller_name = $this->getSavedParam('username');
			$this->__seller_name_changed = ($new_seller_name != $old_seller_name);
			$this->seller_name->data = $new_seller_name;
		}
	}
	/**
	 * Save new SA Seller Name
	 */
	protected function _saveSellerName()
	{
		if ($this->__seller_name_changed)
		{
			$this->_setSavedParams(['username' => $this->seller_name->data]);
			$this->_saveSavedParams();
			unset($this->__seller_name_changed);
		}
	}
	/**
	 * Get Offers
	 */
	protected function _getOffers()
	{
		$this->offers = Offer::listArray($this->_db, $this->_dbr, true, true, '0');
	}
	/**
	 * Get Offer Name
	 */
	protected function _getOfferName()
	{
        $offer_id = $this->getSavedParam('offer_id');
        $this->_getOffers();

        if ($offer_id && isset($this->offers[$offer_id])) {
            $this->offer_name = $this->offers[$offer_id];
        }
	}
	/**
	 * Get SA Brand
	 */
	protected function _getBrand()
	{
		$this->brand = $this->getSavedParam('brand');
		if (!$this->brand)
		{
			$merchant_shop = $this->_dbr->getRow("select * from merchant_shop 
				where merchant_id={$this->merchant_id} and shop_id={$this->getSavedParam('siteid')}");
	
			$this->brand = $merchant_shop->brand;
		}
	}
	/**
	 * Get logs
	 */
	protected function _getLogs()
	{
		$channel = $this->_sellerinfo->get('seller_channel_id');
		$saved_id = $this->id;

        $this->logs = new stdClass();
        switch ($channel) {
            case 1:
                $price_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='startprice'");
                break;
            case 2:
                $price_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='Ricardo[startprice]'");
                $price_row_id1 = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='Ricardo[BuyItNowPrice]'");
                break;
            case 4:
                $price_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='ShopPrice'");
                $high_price_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='ShopHPrice'");
                break;
            case 5:
                $price_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='Allegro[startprice]'");
                $price_row_id1 = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='Allegro[BuyItNowPrice]'");
                break;
        }
        if ($high_price_row_id) {
            $this->logs->high_price_log = $this->_dbr->getAssoc("select total_log.id
                    , CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                    from total_log
                    join users on users.system_username=total_log.username
                    where table_name='saved_params' and tableid=$high_price_row_id and field_name='par_value'
                    order by updated desc limit 5");
        }
        if ($price_row_id) {
            $this->logs->price_log = $this->_dbr->getAssoc("select total_log.id
                    , CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                    from total_log
                    join users on users.system_username=total_log.username
                    where table_name='saved_params' and tableid=$price_row_id and field_name='par_value'
                    order by updated desc limit 5");
        }
        if ($price_row_id1) {
            $this->logs->price_log1 = $this->_dbr->getAssoc("select total_log.id
                    , CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                    from total_log
                    join users on users.system_username=total_log.username
                    where table_name='saved_params' and tableid=$price_row_id1 and field_name='par_value'
                    order by updated desc limit 5");
        }

        $category_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='category'");
        if ($category_row_id)
            $this->logs->category_log = $this->_dbr->getAssoc("select total_log.id
                , CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                from total_log
                join users on users.system_username=total_log.username
                where table_name='saved_params' and tableid=$category_row_id and field_name='par_value'
                order by updated desc limit 5");
        
        $category_row_id = $this->_dbr->getOne("select id from saved_params where saved_id=" . $saved_id . " and par_key='category2'");
        if ($category_row_id)
            $this->logs->category_log2 = $this->_dbr->getAssoc("select total_log.id
                , CONCAT(total_log.new_value, ' by ', users.name, ' on ', total_log.updated)
                from total_log
                join users on users.system_username=total_log.username
                where table_name='saved_params' and tableid=$category_row_id and field_name='par_value'
                order by updated desc limit 5");
		
		$this->logs->available_lastchange = $this->_dbr->getOne("select CONCAT('Was changed by ',IFNULL(u.name, tl.username),' on ', tl.updated)
			from total_log tl
			left join users u on u.system_username=tl.username
			where table_name='offer'
			and tableid = ".$this->getSavedParam('offer_id')."
			and field_name in ('available','available_date','available_weeks')
			order by tl.updated desc limit 1");

		$sp_ids = $this->_dbr->getAssoc("select id f1,id f2 from saved_params where saved_id={$this->id} and par_key in ('snooze_date_shop')");
		$snooze_date = $this->getSavedParam('snooze_date_shop');

		if (count($sp_ids) && strlen($snooze_date) && $snooze_date >= date('Y-m-d')) 
		{
			$this->logs->snooze_lastchange = $this->_dbr->getOne("select CONCAT('Snoozed by ',IFNULL(u.name, tl.username),' on ',tl.updated,' till $snooze_date')
			from total_log tl
			left join users u on u.system_username=tl.username
			where table_name='saved_params'
			and tableid in (" . implode(',', $sp_ids) . ")
			and field_name='par_value'
			order by tl.updated desc limit 1");
		}
	}
	/**
	 * Get margin
	 */
	protected function _getMargin()
	{
        if (!$this->margin) {
            $this->margin = getSAMargin($this->id);
            
            $channel = $this->_sellerinfo->get('seller_channel_id');
            switch ($channel) {
                case 1:
                    if ($this->getSavedParam('fixedprice')) {
                        $shipping_plan_fn = 'fshipping_plan';
                        $shipping_plan_id_fn = 'fshipping_plan_id';
                        $shipping_plan_free_fn = 'fshipping_plan_free';
                    } else {
                        $shipping_plan_fn = 'shipping_plan';
                        $shipping_plan_id_fn = 'shipping_plan_id';
                        $shipping_plan_free_fn = 'shipping_plan_free';
                    }
                    break;
                case 2:
                    if ($this->getSavedParam('Ricardo') == 2) {
                        $shipping_plan_fn = 'fshipping_plan';
                        $shipping_plan_id_fn = 'fshipping_plan_id';
                        $shipping_plan_free_fn = 'fshipping_plan_free';
                    } else {
                        $shipping_plan_fn = 'shipping_plan';
                        $shipping_plan_id_fn = 'shipping_plan_id';
                        $shipping_plan_free_fn = 'shipping_plan_free';
                    }
                    break;
                case 3:
                    $shipping_plan_id_fn = 'sshipping_plan_id';
                    $shipping_plan_fn = 'sshipping_plan';
                    $shipping_plan_free_fn = 'sshipping_plan_free';
                    break;
                case 4:
                    $shipping_plan_id_fn = 'sshipping_plan_id';
                    $shipping_plan_fn = 'sshipping_plan';
                    $shipping_plan_free_fn = 'sshipping_plan_free';
                    break;
                case 5:
                    if ($this->getSavedParam('Allegro') == 2) {
                        $shipping_plan_fn = 'fshipping_plan';
                        $shipping_plan_id_fn = 'fshipping_plan_id';
                        $shipping_plan_free_fn = 'fshipping_plan_free';
                    } else {
                        $shipping_plan_fn = 'shipping_plan';
                        $shipping_plan_id_fn = 'shipping_plan_id';
                        $shipping_plan_free_fn = 'shipping_plan_free';
                    }
                    break;
            }
            
            $this->margin->shipping_plan_fn = $shipping_plan_fn;
            $this->margin->shipping_plan_id_fn  = $shipping_plan_id_fn;
            $this->margin->shipping_plan_free_fn  = $shipping_plan_free_fn;
            
            $sellerinfo = new \SellerInfo($this->_db, $this->_dbr, $this->getSavedParam('username'), 'english');
            $sellerUsername = $sellerinfo->get('username');
            $vars = $this->getSavedParam($sellerUsername);
            
            $shopPrice = isset($vars['BuyItNowPrice']) ? $vars['BuyItNowPrice'] : $this->getSavedParam('ShopPrice');
            
            $this->margin->shipping_cost_seller = $this->_dbr->getOne("select 
                IF((si.free_shipping AND si.free_shipping_above <= '".$shopPrice."') or si.free_shipping_total or IFNULL(t_o.value,0) or offer.{$shipping_plan_fn}_free, 0, spc.shipping_cost) shipping_cost
                                    from saved_auctions sa
                    join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
                    join offer on offer.offer_id=sp_offer.par_value
                    join seller_information si on si.username='".$this->getSavedParam('username')."'
                                    left join translation t_o
                                        on t_o.language='" . $this->getSavedParam('siteid') . "'
                                        and t_o.id=sp_offer.par_value
                                        and t_o.table_name='offer' and t_o.field_name='{$shipping_plan_fn}_free_tr'
                                    join translation
                                        on translation.language='" . $this->getSavedParam('siteid') . "'
                                        and translation.id=sp_offer.par_value
                                        and translation.table_name='offer' and translation.field_name='{$shipping_plan_fn}_id'
                                    join shipping_plan_country spc on spc.shipping_plan_id=translation.value
                                    and spc.country_code = si.defshcountry
                                    where sa.id=" . $this->id);
            $this->margin->total_cost_seller = $this->margin->shipping_cost_seller + $this->getSavedParam('ShopPrice');
            $this->margin->currency = siteToSymbol($this->getSavedParam('siteid'));
        }
        
        return $this->margin;
	}
	/**
	 * Get source seller prices
	 */
	protected function _getSourceSellerPrices()
	{
		$ss_list = $this->_dbr->getAll("select * from source_seller where pp_show=1");
		$this->margin->total_purchase_price_local_sh_vat = $this->margin->total_purchase_price_local_sh_vat * 1;
		$this->margin->total_purchase_price_local = $this->margin->total_purchase_price_local * 1;
		
		foreach ($ss_list as $kss => $ss) 
		{
			$field_text = $ss->pp_formula;
			$field_text = str_replace('[[total_purchase_price_local]]', $this->margin->total_purchase_price_local, $field_text);
			$field_text = str_replace('[[total_purchase_price_local_sh_vat]]', $this->margin->total_purchase_price_local_sh_vat, $field_text);
			$field_text = str_replace('[[ShopPrice]]', $this->getSavedParam('ShopPrice'), $field_text);
			$field_text = str_replace('[[ShopHPrice]]', $this->getSavedParam('ShopHPrice'), $field_text);
			if (empty($field_text))
			{
				$field_text = 0;
			}
			else
			{
				eval("\$field_text = $field_text;");
			}
			$ss_list[$kss]->value = $field_text;
			$ss_list[$kss]->parname = "[[ss_pp_" . str_replace(' ', '_', $ss->name) . "]]";
		}
		$this->source_seller_prices = $ss_list;
	}
	/**
	 * Get shop looks
	 */
	protected function _getShopLooks()
	{
        $details = Saved::getDetails($this->id);
        $shop_id = (int)$this->_dbr->getOne("SELECT `id` FROM `shop` 
                WHERE `username` = '{$details['username']}' AND NOT inactive LIMIT 1");

        $shopCatalogue = new \Shop_Catalogue($this->_db, $this->_dbr, $shop_id, '');
		$this->shop_looks = $shopCatalogue->getLooksForOffer($this->id, false, false);
        
        if ( ! $this->shop_looks)
        {
            $this->shop_looks = new stdClass;
        }
        
        $this->active_look = (int)$this->_dbr->getOne("
            SELECT `par_value`
            FROM `saved_params`
            WHERE `par_key` = 'active_look' AND `saved_id` = '{$this->id}'
        ");
            
        $this->show_in_category_page = (int)$this->_dbr->getOne("
            SELECT `par_value`
            FROM `saved_params`
            WHERE `par_key` = 'show_in_category_page' AND `saved_id` = '{$this->id}'
        ");
            
        cacheClear("getLooks('FOR_OFFER'%");
	}
	/**
	 * Set new shop looks data
	 */
	private function _setActiveLook($data, $old_data = null)
	{
		$this->active_look = (int)$data;
	}
	/**
	 * Set new shop looks data
	 */
	private function _setShowInCategoryPage($data, $old_data = null)
	{
		$this->show_in_category_page = (int)$data;
	}
	/**
	 * Save shop looks
	 */
	private function _saveActiveLook()
	{
        $details_id = (int)$this->_dbr->getOne("SELECT `id` FROM `saved_params`
            WHERE `par_key` = 'active_look' AND `saved_id` = '{$this->id}'");
        
        if ($details_id)
        {
            $this->_db->query("UPDATE `saved_params` SET `par_value` = '{$this->active_look}'
                WHERE `id` = '{$details_id}'");
        }
        else
        {
            $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                VALUES ('{$this->id}', 'active_look', '{$this->active_look}')");
        }
	}
	/**
	 * Save shop looks
	 */
	private function _saveShowInCategoryPage()
	{
        $details_id = (int)$this->_dbr->getOne("SELECT `id` FROM `saved_params`
            WHERE `par_key` = 'show_in_category_page' AND `saved_id` = '{$this->id}'");
        
        if ($details_id)
        {
            $this->_db->query("UPDATE `saved_params` SET `par_value` = '{$this->show_in_category_page}'
                WHERE `id` = '{$details_id}'");
        }
        else
        {
            $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                VALUES ('{$this->id}', 'show_in_category_page', '{$this->show_in_category_page}')");
        }
	}
	/**
	 * Get catalogue
	 */
	protected function _getSaType()
	{
		$sa_type = $this->_dbr->getRow("
            SELECT `par_value`, 
                (
                    SELECT 
                        CONCAT(' by ', IFNULL(`users`.`name`, `total_log`.`username`), ' on ', `total_log`.`updated`)

                    FROM `total_log` 
                    LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`

                    WHERE 
                        `total_log`.`table_name`='saved_params' 
                        AND `total_log`.`field_name`='par_key' 
                        AND `total_log`.`new_value`='sa_type' 
                        AND `total_log`.`TableID`=`saved_params`.`id`

                    ORDER BY `total_log`.`id` DESC
                    LIMIT 1
                ) AS `log`
            FROM `saved_params`

            WHERE `par_key` = 'sa_type' AND `saved_id` = '{$this->id}'
        ");
        
		$this->sa_type = (int)$sa_type->par_value;
		$this->sa_type_log = $sa_type->log;
        
		$this->sa_types = $this->_dbr->getAll('SELECT `id`, `title`, IFNULL(`parent_id`, 0) `parent_id`
                FROM `sa_type` WHERE NOT `sa_type`.`inactive`');
	}
	/**
	 * Set new catalogue data
	 */
	private function _setSaType($data, $old_data = null)
	{
		$this->sa_type = (int)$data;
	}
	/**
	 * Save catalogue
	 */
	private function _saveSaType()
	{
        $details_id = (int)$this->_dbr->getOne("SELECT `id` FROM `saved_params`
            WHERE `par_key` = 'sa_type' AND `saved_id` = '{$this->id}'");
        
        if ($details_id)
        {
            $this->_db->query("UPDATE `saved_params` SET `par_value` = '{$this->sa_type}'
                WHERE `id` = '{$details_id}'");
        }
        else
        {
            $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                VALUES ('{$this->id}', 'sa_type', '{$this->sa_type}')");
        }

		$this->_db->query("UPDATE `saved_auctions` SET `template_id` = NULL WHERE `id` = '{$this->id}'");
	}
	/**
	 * Get saved templates
	 */
	protected function _getSavedTemplates()
	{
		$_is_slave = (bool)$this->_dbr->getOne("SELECT `par_value` 
			FROM `saved_params` WHERE `par_key` = 'master_sa' AND `saved_id` = '{$this->id}'");

        $saved_ids = [];
        if ( ! $_is_slave)
        {
            $q = "SELECT * FROM (
                    SELECT `master`.`saved_id` as 'id', `shop`.`id` as 'shop_id', `saved_auctions`.`inactive`
                    FROM `saved_params` `master`
                    JOIN `saved_params` `slave` ON `slave`.`saved_id` = `master`.`saved_id`
                    JOIN `seller_information` ON `seller_information`.`username` = `slave`.`par_value`
                    JOIN `shop` ON `shop`.`username` = `seller_information`.`username`
                    JOIN `saved_auctions` ON `saved_auctions`.`id` = `master`.`saved_id`
                    WHERE `master`.`par_key` = 'master_sa' 
                    AND `master`.`par_value` = '{$this->id}'
                    AND `slave`.`par_key` = 'username'
                    ORDER BY `master`.`saved_id` DESC) `t`
                GROUP BY `t`.`shop_id`";
            $slaves = $this->_dbr->getAll($q);
            
            $saved_ids = array_map(function($v) {return (int)$v->id;}, $slaves);
        }
        
        $saved_ids[] = $this->id;

        $this->saved_templates = $this->_dbr->getAssoc("
                SELECT `id`, `template_id` 
                FROM `saved_auctions`
                WHERE `id` IN (" . implode(',', $saved_ids) . ")
                ");
	}
	/**
	 * Set saved templates
	 */
	private function _setSavedTemplates($data, $old_data = null)
	{
		$this->saved_templates = $data;
	}
	/**
	 * Save saved templates
	 */
	private function _saveSavedTemplates()
	{
        if ($this->saved_templates && is_array($this->saved_templates))
        {
            foreach ($this->saved_templates as $saved_id => $template_id)
            {
                $saved_id = (int)$saved_id;
                $template_id = (int)$template_id;
                
                $old_template_id = (int)$this->_dbr->getOne("SELECT `template_id` FROM `saved_auctions` WHERE `id` = '" . $saved_id . "'");
                if ($old_template_id != $template_id)
                {
                    $this->_db->execParam("UPDATE `saved_auctions` SET `template_id` = ? WHERE `id` = ?", 
                            [$template_id ? $template_id : null, $saved_id]);
                }
            }
        }
	}
	/**
	 * Get catalogue
	 */
	function _getDescription($all = true)
	{
        $type_id = (int)$this->_dbr->getOne("
            SELECT `par_value`
			FROM `saved_params`
			WHERE `par_key` = 'sa_type' AND `saved_id` = '{$this->id}'");
        
        if ( ! $type_id) 
        {
            return;
        }

        $data = [];
        
        $field_order_list = $this->_dbr->getAll("
                SELECT 
                    `sa_template`.`id`, 
                    `sa_template_block_element_prop`.`value`
                
                FROM `sa_template_block` 
                LEFT JOIN `sa_template_block_layout` ON 
                    `sa_template_block_layout`.`id` = `sa_template_block`.`layout_id`
                    AND NOT `sa_template_block_layout`.`inactive`
                    
                JOIN `sa_template_block_element` ON `sa_template_block_element`.`block_id` = `sa_template_block`.`id`
                    
                JOIN `sa_template_block_element_prop` ON `sa_template_block_element_prop`.`block_element_id` = `sa_template_block_element`.`id`
                    
                JOIN `sa_template` ON 
                    `sa_template`.`id` = `sa_template_block`.`template_id`
                    
                JOIN `sa_template_sa_type` ON 
                    `sa_template_sa_type`.`template_id` = `sa_template`.`id`
                
                WHERE 
                    `sa_template_sa_type`.`type_id` = '$type_id'
                    AND NOT `sa_template`.`inactive`
                    AND NOT ISNULL(`sa_template_block_element_prop`.`value`)
                    AND `sa_template_block_element_prop`.`value` != ''
                        
                ORDER BY `sa_template_block`.`ordering` ASC, 
                    `sa_template_block_element`.`col_id` ASC,
                    `sa_template_block_element`.`ordering` ASC
        ");
        
        $templates_fields = [];
        
        $field_order = [];
        foreach ($field_order_list as $item)
        {
            if (preg_match_all('#\_f(\d+)\]\]#iu', $item->value, $matches))
            {
                $field_order = array_merge($field_order, $matches[1]);
                $templates_fields[$item->id][] = $matches[1][0];
            }
        }
        $field_order = array_reverse(array_values(array_unique($field_order)));
        
        $templates = [];
        if ($templates_fields)
        {
            $templates = $this->_dbr->getAssoc("SELECT `id`, `title` FROM `sa_template` 
                    WHERE NOT `inactive` AND `id` IN ( " . implode(',', array_keys($templates_fields)) . " )");
        }

        $fields = $this->_dbr->getAssoc("
            SELECT 
                `sa_field`.`id` AS `k`
                , `sa_field`.`id`
                , `sa_field`.`name` AS `system_name`
                , 0 AS `order`
            FROM `sa_field`
            JOIN `sa_field_type` ON `sa_field_type`.`field_id` = `sa_field`.`id`
            WHERE 
                NOT `sa_field`.`inactive` 
                AND `sa_field_type`.`type_id` = '$type_id'");

        $fields_ids = array_map(function($v) { return (int)$v['id'];}, $fields);

        if ($all && $fields_ids) 
        {
            $fields_names = $this->_dbr->getAll("
                    SELECT `id`, `language`, `value` 
                    FROM `translation` 
                    WHERE 
                        `table_name` = 'sa_field'
                        AND `field_name` = 'field_name'
                        AND `id` IN ( " . implode(',', $fields_ids) . " )
                    ");

            foreach ($fields as $key => $_field) 
            {
                $fields[$key]['field_values'] = [];
                
                foreach ($fields_names as $_mulang)
                {
                    if ($_mulang->id == $key)
                    {
                        $fields[$key]['field_values'][$_mulang->language] = $_mulang->value;
//                        $fields[$key]['field_values'][$_mulang->language] = new stdClass;
//                        $fields[$key]['field_values'][$_mulang->language]->language = $_mulang->language;
//                        $fields[$key]['field_values'][$_mulang->language]->value = $_mulang->value;
                    }
                }
                
                $fields[$key]['templates'] = [];
                foreach ($templates_fields as $template_id => $template_field_ids)
                {
                    if (in_array($key, $template_field_ids))
                    {
                        $fields[$key]['templates'][] = $template_id;
                    }
                }
                
                //$mulang_data = mulang_fields_Get(['field_name'], 'sa_field', $_field['id'], 0);
                //$fields[$key]['field_values'] = $mulang_data['field_name_translations'];
                $fields[$key]['contents'] = [];

                for ($i = 0; $i < count($field_order); ++$i)
                {
                    if ($_field['id'] == $field_order[$i])
                    {
                        $fields[$key]['order'] = $i + 1;
                    }
                }
                
                $fields[$key] = array_to_object($fields[$key]);
            }
        }
        
        if ($all && $fields_ids) 
        {
            $contents = [];
            $contents_all = $this->_dbr->getAll("
                SELECT `sa_content_field`.`field_id` AS `field_id`
                    , `sa_content`.`id`
                    , `sa_content`.`name` AS `system_name`
                    , `sa_content`.`kind`
                    , `sa_content`.`alt_name`
                    , `sa_content`.`formula`
                    , GROUP_CONCAT(`sa_content_value`.`id`) AS `values`
                FROM `sa_content`
                JOIN `sa_content_field` ON `sa_content_field`.`content_id` = `sa_content`.`id`
                LEFT JOIN `sa_content_value` ON `sa_content_value`.`content_id` = `sa_content`.`id`
                WHERE 
                    NOT `sa_content`.`inactive` 
                    AND `sa_content_field`.`field_id` IN ( " . implode(',', $fields_ids) . " )
                GROUP BY `field_id`, `id`
            ");
            
            $contents_ids = [];
            $contents_values = [];
            foreach ($contents_all as $_content) 
            {
                $fields[$_content->field_id]->contents[] = (int)$_content->id;
                
                if ( ! isset($contents[$_content->id])) {
                    $contents[$_content->id] = $_content;                    
                    
                    $contents[$_content->id]->values = explode(',', $contents[$_content->id]->values);
                    $contents[$_content->id]->values = array_map('intval', $contents[$_content->id]->values);

                    $contents[$_content->id]->contents = [];
                    if ($contents[$_content->id]->values) 
                    {
                        $contents_values = array_merge($contents_values, $contents[$_content->id]->values);
                        $contents_ids[] = (int)$_content->id;
                    }
                }
            }

            if ($contents_ids && $contents_values)
            {
                $contents_ids = implode(',', $contents_ids);

                $contents_values = array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, $contents_values);
                $contents_values = implode(',', $contents_values);
                
                $mulang_data = $this->_dbr->getAll("
                    SELECT `id`, `field_name`, `language`, `value`
                    FROM `translation`
                    WHERE `table_name` = 'sa_content_value'
                        AND `field_name` IN (" . $contents_values . ")
                        AND `translation`.`id` IN (" . $contents_ids . ")
                ");
                
                $mulang_data_array = [];
                foreach ($mulang_data as $_mulang)
                {
                    $mulang_data_array[$_mulang->id][] = $_mulang;
                }
                
                foreach ($contents as $key => $_content)
                {
                    if (isset($mulang_data_array[$_content->id]) && is_array($mulang_data_array[$_content->id]))
                    {
                        foreach ($mulang_data_array[$_content->id] as $_mulang)
                        {
                            if (in_array($_mulang->field_name, $_content->values))
                            {
                                $contents[$key]->contents[$_mulang->field_name][$_mulang->language] = $_mulang->value;
//                                $contents[$key]->contents[$_mulang->field_name][$_mulang->language] = new stdClass;
//                                $contents[$key]->contents[$_mulang->field_name][$_mulang->language]->language = $_mulang->language;
//                                $contents[$key]->contents[$_mulang->field_name][$_mulang->language]->value = $_mulang->value;
                            }
                        }
                    }
//                    foreach ($mulang_data as $_mulang)
//                    {
//                        if ($_mulang->id == $_content->id && in_array($_mulang->field_name, $_content->values))
//                        {
//                            $contents[$key]->contents[$_mulang->field_name][$_mulang->language] = new stdClass;
//                            $contents[$key]->contents[$_mulang->field_name][$_mulang->language]->language = $_mulang->language;
//                            $contents[$key]->contents[$_mulang->field_name][$_mulang->language]->value = $_mulang->value;
//                        }
//                    }
                }
            }
        }
        
        if ($all)
        {
            $contents_ids = array_keys($contents);
        }
        else 
        {
            $contents_ids = $this->_dbr->getAssoc("
                SELECT `sa_content`.`id`, `sa_content`.`id` `v`
                FROM `sa_content`
                JOIN `sa_content_field` ON `sa_content_field`.`content_id` = `sa_content`.`id`
                WHERE 
                    NOT `sa_content`.`inactive` 
                    AND `sa_content_field`.`field_id` IN ( " . implode(',', $fields_ids) . " )
            ");
        }
        
        $sa_description = $this->_dbr->getAll("
            SELECT `sa_field_content_value_sa`.*, 
                (
                    SELECT 
                        CONCAT(' by ', IFNULL(`users`.`name`, `total_log`.`username`), ' on ', `total_log`.`updated`)

                    FROM `total_log` 
                    LEFT JOIN `users` ON `users`.`system_username`=`total_log`.`username`

                    WHERE 
                        `total_log`.`table_name`='saved_params' 
                        AND `total_log`.`field_name`='par_key' 
                        AND `total_log`.`new_value` = 'sa_description[]' 
                        AND `total_log`.`TableID`=`saved_params`.`id`

                    ORDER BY `total_log`.`id` DESC
                    LIMIT 1

                ) AS `log`
            FROM `saved_params`
            JOIN `sa_field_content_value_sa` ON `sa_field_content_value_sa`.`id` = `saved_params`.`par_value`

            WHERE `par_key` = 'sa_description[]' AND `saved_id` = '{$this->id}'
                
            ORDER BY `saved_params`.`id` ASC
        ");
            
        $checked = [];
        $checked_log = [];
        if ($sa_description) 
        {
            foreach ($sa_description as $_checked) 
            {
                if (in_array($_checked->field_id, $fields_ids) && in_array($_checked->content_id, $contents_ids))
                {
                    $checked_log[$_checked->field_id][$_checked->content_id] = $_checked->log;
                    if ($_checked->value)
                    {
                        $checked[$_checked->field_id][$_checked->content_id] = $_checked->value;
                    }
                    else if ( ! $_checked->value_id)
                    {
                        $checked[$_checked->field_id][$_checked->content_id] = 'full';
                    }
                    else 
                    {
                        $checked[$_checked->field_id][$_checked->content_id][] = $_checked->value_id;
                    }
                }
            }
        }

        $this->description = new stdClass;
        $this->description->checked = $checked ? $checked : new stdClass;
        $this->description->checked_log = $checked_log ? $checked_log : new stdClass;
        
        if ($all)
        {
            $this->description->options->fields = array_to_object($fields);
            $this->description->options->contents = array_to_object($contents);
            $this->description->options->templates = array_to_object($templates);
        }
	}
	/**
	 * Set new catalogue data
	 */
	private function _setDescription($data, $old_data = null)
	{
		$this->description = $data['checked'];
	}
	/**
	 * Save catalogue
	 */
	private function _saveDescription()
	{
        $sa_old_checked = $this->_db->getAssoc("
            SELECT `id`, `par_value`
            FROM `saved_params` 
            WHERE `saved_id` = '{$this->id}' AND `par_key` = 'sa_description[]'
        ");
        $sa_old_checked = array_map('intval', $sa_old_checked);
        
        $sa_new_checked = [];
        if ($this->description && is_array($this->description)) 
        {
            foreach ($this->description as $field_id => $value) 
            {
                $field_id = (int)$field_id;
                if ( ! $this->_dbr->getOne("SELECT `id` FROM `sa_field` WHERE `id` = '" . $field_id . "' AND NOT `inactive`")) 
                {
                    continue;
                }
                
                foreach ($value as $content_id => $value_id) 
                {
                    $content_id = (int)$content_id;
                    $content_kind = $this->_dbr->getOne("SELECT `kind` FROM `sa_content` WHERE `id` = '" . $content_id. "' AND NOT `inactive`");
                    
                    if ( ! $content_kind) 
                    {
                        continue;
                    }
                    
                    $value = null;
                    if ($content_kind == 'full') 
                    {
                        $value_id = null;
                    }
                    else if ($content_kind == 'digits') 
                    {
                        if (stripos($value_id, 'x') === false && stripos($value_id, '/') === false)
                        {
                            $value = (float)$value_id;
                        }
                        else
                        {
                            $value = $value_id;
                        }
                        
                        if ( ! $value)
                        {
                            $value = '';
                        }
                        
                        $value_id = null;
                    }
                    
                    if ($content_kind == 'full' || $content_kind == 'digits')
                    {
                        $query = "
                            SELECT `id` FROM `sa_field_content_value_sa` 
                            WHERE 
                                `field_id` = '$field_id'
                                AND `content_id` = '$content_id'
                                AND `value_id` IS NULL ";

                        if ($content_kind == 'digits') 
                        {
                            if ($value)
                            {
                                $query .= " AND `value` = '$value' ";
                            }
                            else
                            {
                                $query .= " AND `value` = '' ";
                            }
                        }

                        $sa_checked = (int)$this->_dbr->getOne($query);

                        if ( ! $sa_checked)
                        {
                            $this->_db->execParam('INSERT INTO `sa_field_content_value_sa` (`field_id`, `content_id`, `value_id`, `value`)
                                VALUES (?, ?, ?, ?)', [$field_id, $content_id, null, $value]);

                            $sa_checked = (int)$this->_db->queryOne('SELECT LAST_INSERT_ID() FROM sa_field_content_value_sa');
                        }

                        $sa_new_checked[] = $sa_checked;

                        if ( !in_array($sa_checked, $sa_old_checked))
                        {
                            $this->_db->execParam('INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                                VALUES (?, ?, ?)', [$this->id, "sa_description[]", $sa_checked]);
                        }
                        
                        if ($content_kind == 'digits' && ! $value) 
                        {
                            $field_content_id = $this->_dbr->getOne("SELECT `id` 
                                FROM `sa_field_content_value_sa` 
                                WHERE 
                                    `field_id` = '$field_id'
                                    AND `content_id` = '$content_id'
                                    AND `value_id` IS NULL
                                    AND `value` = ''");

                            $this->_db->execParam('DELETE FROM `saved_params` 
                                WHERE `saved_id` = ? AND `par_key` = ? AND `par_value` = ?', 
                                    [$this->id, "sa_description[]", $field_content_id]);

                            $this->_db->execParam('DELETE FROM `sa_field_content_value_sa` 
                                WHERE `id` = ?', [$field_content_id]);
                        }
                    }
                    else
                    {
                        if ( !is_array($value_id))
                        {
                            $value_id = [$value_id];
                        }
                        
                        foreach ($value_id as $_value_id)
                        {
                            $query = "
                                SELECT `id` FROM `sa_field_content_value_sa` 
                                WHERE 
                                    `field_id` = '$field_id'
                                    AND `content_id` = '$content_id'
                                    AND `value_id` = '" . mysql_real_escape_string($_value_id) . "'";

                            $sa_checked = (int)$this->_db->getOne($query);

                            if ( ! $sa_checked)
                            {
                                $this->_db->execParam('INSERT INTO `sa_field_content_value_sa` (`field_id`, `content_id`, `value_id`, `value`)
                                    VALUES (?, ?, ?, ?)', [$field_id, $content_id, $_value_id, null]);

                                $sa_checked = (int)$this->_db->queryOne('SELECT LAST_INSERT_ID() FROM sa_field_content_value_sa');
                            }

                            $this->_db->execParam('INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                                VALUES (?, ?, ?)', [$this->id, "sa_description[]", $sa_checked]);
//                            $sa_new_checked[] = $sa_checked;
//
//                            if ( !in_array($sa_checked, $sa_old_checked))
//                            {
//                                $this->_db->execParam('INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
//                                    VALUES (?, ?, ?)', [$this->id, "sa_description[]", $sa_checked]);
//                            }
                        }
                    }
                }
            }
        }
        
        $sa_delete_checked = [];
        foreach ($sa_old_checked as $key => $value)
        {
            if ( ! in_array($value, $sa_new_checked))
            {
                $sa_delete_checked[] = $value;
                $this->_db->query("DELETE FROM `saved_params` WHERE `id` = '$key'");
            }
        }
        
        foreach ($sa_delete_checked as $value)
        {
            $query = "
                SELECT `saved_id`
                FROM `saved_params` 
                WHERE `par_key` = 'sa_description[]' AND `par_value` = '" . $value . "'
            ";
            if ( ! $this->_db->getOne($query))
            {
                $this->_db->query("DELETE FROM `sa_field_content_value_sa` WHERE `id` = '" . $value . "'");
            }
        }
	}
	/**
	 * Get catalogue
	 */
	protected function _getCatalogue()
	{
		require_once 'lib/SavedCatalogue.php';
		$username = $this->getSavedParam('username');
		$siteid = $this->getSavedParam('siteid');
		
        if ($siteid == '')
			throw new Exception('Country must be defined');
			
		$this->_saved_catalogue = new SavedCatalogue($this->id, $username, $siteid, $this->_db, $this->_dbr);
		$this->catalogue = $this->_saved_catalogue->get();
	}
	/**
	 * Set new catalogue data
	 */
	private function _setCatalogue($data, $old_data = null)
	{
		$this->_saved_catalogue->setData($data, $old_data);
	}
	/**
	 * Save catalogue
	 */
	private function _saveCatalogue()
	{
		$this->_saved_catalogue->save();
	}
	/**
	 * Get excluded bonuses
	 */
	protected function _getBonus()
	{
		require_once 'lib/SavedBonus.php';
		$this->_saved_bonus = new SavedBonus($this->id, $this->getSavedParam('username'), $this->_sellerinfo->get('default_lang'), $this->_db, $this->_dbr);
		$this->bonus = $this->_saved_bonus->get();
	}
	/**
	 * Set new bonus data
	 */
	protected function _setBonus($data, $old_data = null)
	{
		$this->_saved_bonus->setData($data, $old_data);
	}
	/**
	 * Save bonus
	 */
	protected function _saveBonus()
	{
		$this->_saved_bonus->save();
	}
	/**
	 * Get saved pics
	 */
	protected function _getPic()
	{
		require_once 'lib/SavedPic.php';
		$this->_saved_pic = new SavedPic($this->id, $this->_db, $this->_dbr);
		$this->pic = $this->_saved_pic->get();
	}
	/**
	 * New pic upload
	 */
	public function uploadPic($params)
	{
		$this->_saved_pic->uploadPic($params);
		$this->pic = $this->_saved_pic->get();
	}
	/**
	 * Swap pics
     * $param int $source_doc_id
     * $param string $source_type
     * $param int $target_doc_id
     * $param array $params
	 */
    public function swapPics($source_doc_id, $source_type, $target_doc_id, $target_type, $params)
	{
		$this->_saved_pic->swapPics($source_doc_id, $source_type, $target_doc_id, $target_type, $params);
		$this->pic = $this->_saved_pic->get();
	}
	/**
	 * Set new pics data
	 */
	protected function _setPic($data, $old_data = null)
	{
		$this->_saved_pic->setData($data, $old_data);
	}
	/**
	 * Save bonus
	 */
	protected function _savePic()
	{
		$this->_saved_pic->save();
	}
	/**
	 * Get rating
	 */
	protected function _getRating()
	{
		require_once 'lib/SavedRating.php';
		$this->_saved_rating = new SavedRating($this->id, $this->_db, $this->_dbr);
		$this->rating = $this->_saved_rating->get();
	}
	/**
	 * Set rating
	 */
	protected function _setRating($data, $old_data = null)
	{
		$this->_saved_rating->setData($data, $old_data);
	}
	/**
	 * Save rating
	 */
	protected function _saveRating()
	{
		$this->_saved_rating->save();
	}
    /**
     * Get SA's additional articles. Table `saved_additional_articles`
     */
    protected function _getAdditionalArticles()
    {
        $this->additional_articles = $this->_dbr->getAssoc("SELECT 
                    `saved_additional_articles`.*
                    , `sp_auction_name`.`par_value` `title`
                    , `saved_auctions`.`inactive`
                FROM `saved_additional_articles` 
                LEFT JOIN `saved_params` `sp_auction_name` 
                    ON `sp_auction_name`.`saved_id` = `saved_additional_articles`.`additional_id`
                    AND `sp_auction_name`.`par_key` = 'auction_name'
                LEFT JOIN `saved_auctions`
                    ON `saved_auctions`.`id` = `saved_additional_articles`.`additional_id`
                WHERE `saved_additional_articles`.`saved_id` = {$this->id}");
        
        return $this->additional_articles;
    }
    /**
     * Set SA's additional articles field.
     */
    protected function _setAdditionalArticles($data, $old = null)
    {
        $this->__additional_articles_to_assign = [];
        $this->__additional_articles_changed = [];
        $new_ids = [];
        $existing_ids = array_keys($this->additional_articles);
        
        foreach ($data as $id => $row) {
            if (isset($this->additional_articles[$id])  // change data
                && ($this->additional_articles[$id]['additional_id'] != $row['additional_id'] 
                    || $this->additional_articles[$id]['ordering'] != $row['ordering']
                )
            ) { 
                $this->additional_articles[$id]['additional_id'] = $row['additional_id'];
                $this->additional_articles[$id]['ordering'] = $row['ordering'];
                $this->__additional_articles_changed[] = $id;
            } elseif (strpos($id, 'new') !== false) { // new data
                $this->additional_articles[$id] = $row;
				$this->__additional_articles_to_assign[] = $row;
            }

            if (is_int($id)) {
                $new_ids[] = $id;
            }
        }
        
        sort($existing_ids);
        sort($new_ids);
        $this->__additional_articles_to_delete = array_diff($existing_ids, $new_ids);
    }
    /**
     * Save SA's additional articles.
     */
    protected function _saveAdditionalArticles()
    {
        foreach ($this->__additional_articles_to_assign as $additional_article) {
            $this->_db->query("insert into saved_additional_articles set
                saved_id=" . (int)$this->id . "
                , additional_id=" . (int)$additional_article['additional_id'] . "
                , ordering=" . (int)$additional_article['ordering']);
        }
        $this->__additional_articles_to_assign = [];

        foreach ($this->__additional_articles_changed as $additional_article_id) {
            if (isset($this->additional_articles[$additional_article_id])) {
                $this->_db->query("update saved_additional_articles set
                    additional_id=" . (int)$this->additional_articles[$additional_article_id]['additional_id'] . "
                    , ordering=" . (int)$this->additional_articles[$additional_article_id]['ordering'] . "
                    where saved_id=" . (int)$this->id . "
                    and id=" . (int)$additional_article_id . "
                    limit 1");
            }
        }
        $this->__additional_articles_changed = [];

        foreach ($this->__additional_articles_to_delete as $additional_article_id) {
            $this->_db->query("delete from saved_additional_articles where
                id=" . (int)$additional_article_id . "
                and saved_id=" . (int)$this->id . "
                limit 1");
        }
        $this->__additional_articles_to_delete = [];
    }
	/**
	 * Get availability
	 */
	protected function _getAvailability()
	{
		if ($this->_offer)
		{
			if ($this->_offer->get('available')) 
			{
				$offerAvailability = 'In stock';
			} 
			else 
			{
				if ((int)$this->_offer->get('available_weeks')) 
				{
					$offerAvailability = 'Out of stock till '.$this->_dbr->getOne("select date_add(NOW(), INTERVAL ".(int)$this->_offer->get('available_weeks')." week)");
				}
				else 
				{
					$offerAvailability = 'Out of stock till '.$this->_offer->get('available_date');
				}
			}
			
			$this->availability = $offerAvailability;
		}
		else
			$this->availability = '';
            
        return $this->availability;
	}
	/**
	 * Get shop parameters
	 */
	protected function _getShopParams()
	{
		require_once 'lib/SavedShopParams.php';
		$this->_saved_shop_params = new SavedShopParams($this->id, $this->_sellerinfo->data->default_lang, $this->_db, $this->_dbr);
		$this->shop_params = $this->_saved_shop_params->get();
	}
	/**
	 * Save shop parameters
	 */
	protected function _saveShopParams()
	{
		$this->_saved_shop_params->save();
	}
	/**
	 * Get last 20 shop
	 */
	protected function _getLastShop()
	{
		$q = "select * from (
			   select IFNULL(mau.winning_bid, au.winning_bid) winning_bid
			   , (SELECT cav.description FROM config_api ca
				LEFT join config_api_values cav on ca.par_id=cav.par_id and ca.value=cav.value
				where ca.par_id =7 and siteid=IFNULL(mau.siteid, au.siteid) limit 1) curr
				, seller_information.username
				, seller_information.seller_name
				, IFNULL(mau.siteid, au.siteid) siteid
				, IFNULL(mau.auction_number, au.auction_number) auction_number
				, IFNULL(mau.txnid, au.txnid) as txnid
				, offer_name.name as alias
				, IFNULL(mau.end_time, au.end_time) end_time
							, (select ROUND(sum(
								(IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
							+ IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
							+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
							- IFNULL(ac.packing_cost,0)/ac.curr_rate)),2)
								 from auction_calcs ac
								 join orders o on o.id = ac.order_id
								 left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
								 where ac.auction_number=mau.auction_number and o.hidden=0
									and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
								 and ac.txnid=mau.txnid)
							+ (select ROUND(sum(
								(IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
							+ IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
							+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
							- IFNULL(ac.packing_cost,0)/ac.curr_rate)),2)
								 from auction_calcs ac
								 join orders o on o.id = ac.order_id
								 left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
								 where ac.auction_number=au.auction_number and o.hidden=0
									and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
								 and ac.txnid=au.txnid) as brutto_income
			from auction au
			left join offer_name on au.name_id=offer_name.id
			left join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
			left join seller_information on IFNULL(mau.username, au.username)=seller_information.username
				where au.txnid=3 and mau.saved_id=" . $this->id . " and IFNULL(au.deleted,0)=0
			union all
				   select IFNULL(mau.winning_bid, au.winning_bid) winning_bid
			   , (SELECT cav.description FROM config_api ca
				LEFT join config_api_values cav on ca.par_id=cav.par_id and ca.value=cav.value
				where ca.par_id =7 and siteid=IFNULL(mau.siteid, au.siteid) limit 1) curr
				, seller_information.username
				, seller_information.seller_name
				, IFNULL(mau.siteid, au.siteid) siteid
				, IFNULL(mau.auction_number, au.auction_number) auction_number
				, IFNULL(mau.txnid, au.txnid) as txnid
				, offer_name.name as alias
				, IFNULL(mau.end_time, au.end_time) end_time
							, (select ROUND(sum(
								(IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
							+ IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
							+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
							- IFNULL(ac.packing_cost,0)/ac.curr_rate)),2)
								 from auction_calcs ac
								 join orders o on o.id = ac.order_id
								 left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
								 where ac.auction_number=mau.auction_number and o.hidden=0
									and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
								 and ac.txnid=mau.txnid)
							+ (select ROUND(sum(
								(IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
							+ IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
							+ IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
							- IFNULL(ac.packing_cost,0)/ac.curr_rate)),2)
								 from auction_calcs ac
								 join orders o on o.id = ac.order_id
								 left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
								 where ac.auction_number=au.auction_number and o.hidden=0
									and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
								 and ac.txnid=au.txnid) as brutto_income
			from auction au
			left join offer_name on au.name_id=offer_name.id
			left join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
			left join seller_information on IFNULL(mau.username, au.username)=seller_information.username
			where au.txnid=3 and au.saved_id=" . $this->id . " and IFNULL(au.deleted,0)=0
			) t		order by end_time desc LIMIT 0 , 20";
			
		$last20shop = $this->_dbr->getAll($q);
			
		if (!empty($last20shop))
			foreach ($last20shop as $id => $auction) {
				$last20shop[$id]->_cgi_eBay = getParByName($this->_db, $this->_dbr, $auction->siteid, "_cgi_eBay");
				$last20shop[$id]->params = unserialize($last20shop[$id]->params);
				$last20shop[$id]->ListingDuration = $last20shop[$id]->params['Item']['ListingDuration'];
			};

		usort($last20shop, 'sortitems');
		$this->last_shop = $last20shop;
	}
	/**
	 * Get stock
	 */
	 protected function _getStock()
	 {
		$channel = $this->_sellerinfo->get('seller_channel_id');
		switch ($channel) {
		case 1:
			$this->_stock_warehouse_parkey = 'stop_empty_warehouse';
			break;
		case 2:
			$this->_stock_warehouse_parkey = 'stop_empty_warehouse_ricardo';
			break;
		case 3:
			$this->_stock_warehouse_parkey = 'stop_empty_warehouse_Allegro';
			break;
		case 4:
			$this->_stock_warehouse_parkey = 'stop_empty_warehouse_shop';
			break;
		case 5:
			$this->_stock_warehouse_parkey = 'stop_empty_warehouse_amazon';
			break;
		}
		
		$saved_params = $this->_dbr->getAssoc("select `par_key`, `par_value`
			from `saved_params` where `saved_id` = " . $this->id . " 
			and `par_key` in ('stop_empty_shop', 'stop_empty_level_shop', 'snooze_days_shop')");
		
		$stop_empty_warehouse = $this->_dbr->getCol("select `par_value`
			from `saved_params` where `saved_id` = " . $this->id . " and `par_key` like '{$this->_stock_warehouse_parkey}%'");
		
		$min_stock = getMinStock($this->_db, $this->_dbr, (int)$this->id, (int)$this->getSavedParam('offer_id'), $stop_empty_warehouse, 4);
		$warehouses = Warehouse::listArray($this->_db, $this->_dbr);

		$minavas = [];
		foreach ($warehouses as $id => $name)
		{
			if (in_array($id, $stop_empty_warehouse))
			{
				$minavas[] = ['title' => "Minstock for $name", 'qty' => $min_stock['minavas'][$id]];
			}
		}
        
        $countries = $this->_dbr->getAssoc("select distinct warehouse.country_code, country.name
            from warehouse
            join country on country.code=warehouse.country_code
            where not warehouse.inactive
            order by ordering");
			
		$this->stock = [
			'data' => [
				'stop_empty_warehouse' => $stop_empty_warehouse,
				'stop_empty_shop' => $saved_params['stop_empty_shop'],
				'stop_empty_level_shop' => $saved_params['stop_empty_level_shop'],
				'snooze_days_shop' => $saved_params['snooze_days_shop'],
			],
			'options' => [
				'minava' => $this->_sellerinfo->get('warehouse_migration') ? $min_stock['minava_with_migration'] : $min_stock['minava_without_migration'],
				'minavas' => $minavas,
				'warehouses' => $warehouses,
				'total_article_number' => $min_stock['total_article_number'],
                'countries' => $countries
			]
		];
	 }
	/**
	 * Set stock data
	 */
	protected function _setStock($in, $old = null)
	{
		$this->_stock_changed_fields = [];
		$fields = ['stop_empty_shop', 'stop_empty_level_shop', 'snooze_days_shop'];
		foreach ($fields as $field)
		{
			if ($this->stock['data'][$field] != $in['data'][$field])
			{
				if (isset($old['data'][$field]) && $old['data'][$field] != $this->stock['data'][$field])
					throw new Exception(self::MESSAGE_DATA_IS_CHANGED);		
				
				$this->stock['data'][$field] = (int)$in['data'][$field];
				$this->_stock_changed_fields['saved_params'][] = $field;
			}
		}
		
		if (isset($old['data']['stop_empty_warehouse']) && !(empty($this->stock['data']['stop_empty_warehouse']) && empty($old['data']['stop_empty_warehouse'])))
		{
			$assigned = sort($this->stock['data']['stop_empty_warehouse']);
			$_assigned = sort($old['data']['stop_empty_warehouse']);
			if ($assigned != $_assigned)
				throw new Exception(self::MESSAGE_DATA_IS_CHANGED);		
		}
		
        $this->_stock_changed_fields['stop_empty_warehouse'] = $in['data']['stop_empty_warehouse'];
	}
	/**
	 * Save stock data
	 */
	protected function _saveStock()
	{
		foreach ($this->_stock_changed_fields['saved_params'] as $field)
		{
			$this->_setSavedParams([$field => (int)$this->stock['data'][$field]]);
		}

		$this->_saveSavedParams();
		
        if ($this->_stock_changed_fields['stop_empty_warehouse']) {
			$this->_db->query("DELETE FROM `saved_params` 
                WHERE `saved_id` =  {$this->id}
                AND `par_key` LIKE 'stop_empty_warehouse_shop[%]'");
        }
        
		foreach ($this->_stock_changed_fields['stop_empty_warehouse'] as $id => $warehouse_id)
		{
			$this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`) 
				VALUES ({$this->id}, '{$this->_stock_warehouse_parkey}[$id]', " . (int)$warehouse_id . ")");
		}
		$this->_stock_changed_fields['stop_empty_warehouse'] = [];
	}
	 /*
	  * Get SA colors
	  * @return array
	  */
	protected function _getColors()
	{
		$this->colors = $this->_dbr->getAssoc("select id, (select value from translation 
			where table_name='sa_color' and field_name='name' and language='english' and id=sa_color.id)
			from sa_color order by ordering");
	}
	/*
	 * Get SA materials
	 */
	protected function _getMaterials()
	{
		$this->materials = $this->_dbr->getAssoc("select id, (select value from translation 
			where table_name='sa_material' and field_name='name' and language='english' and id=sa_material.id)
			from sa_material order by ordering");
	}
	/**
	 * Get SA comments
	 */
	protected function _getCommentBlock()
	{
		global $loggedUser;
		
		$alarm = $this->_dbr->getRow("select IFNULL(alarms.type,'saved_auctions') `type`, saved_auctions.id type_id
			, alarms.status, alarms.date, alarms.comment
			from saved_auctions
			left join alarms on alarms.type='saved_auctions'
			and alarms.type_id=saved_auctions.id
			and alarms.username='" . $loggedUser->get('username') . "'
			where saved_auctions.id=" . $this->id . "");
			
		if (!$this->isSlave())
		{
			$q = "SELECT `saved_params`.`saved_id` 
				FROM `saved_params`
				WHERE `saved_params`.`par_key` = 'master_sa' 
				AND `saved_params`.`par_value` = {$this->id}";
				
			$saved_ids = $this->_dbr->getCol($q);
			$saved_ids[] = $this->id;
			
			$comments = self::getComments($this->_db, $this->_dbr, $saved_ids);
		}
		else
		{
			$comments = self::getComments($this->_db, $this->_dbr, $this->id);
		}
			
		
		$users = User::listArray($this->_db, $this->_dbr);
        
        $emailLog = EmailLog::listAll($this->_db, $this->_dbr, $this->id, -11);
        foreach ($emailLog as $i => $entry) {
            $emailLog[$i]->date = serverToLocal($entry->date, $timediff);
        }
		
		$this->comment_block = [
			'data' => [
				'responsible_uname' => $this->data->responsible_uname,
			],
			'options' => [
				'alarm' => $alarm,
				'comments' => $comments,
				'users' => $users,
                'email_log' => $emailLog,
			]
		];
	}
	/*
	 * Get video
	 */
	protected function _getVideo()
	{
		$docs_with_video = [];
		$docs = self::getDocs($this->_db, $this->_dbr, $this->id, '', '', '', 0);
		
		$codes = [];
		foreach (self::$doc_data_translations as $doc_id => $langs)
		{
			foreach ($langs as $lang => $data)
			{
				if (isset($data->youtube_code) && !empty($data->youtube_code))
				{
					$codes[$doc_id][$lang] = $data->youtube_code;
                    $uses[$doc_id][$lang] = (int)$data->use;
                    
					if (!in_array($doc_id, $docs_with_video)) {
						$docs_with_video[] = $doc_id;
                    }
				}
			}
		}
		
		$video = [];
		foreach ($docs as $doc) {
			if (in_array($doc->doc_id, $docs_with_video)) {
				$video[$doc->doc_id] = $doc;
				
				$filled = array_fill_keys(array_keys($langs), '');
				$video[$doc->doc_id]->codes = array_merge($filled, $codes[$doc->doc_id]);
                $video[$doc->doc_id]->use = $uses[$doc->doc_id];
			}
		}
		$this->video = $video;
	}
	/**
	 * Set new video data
	 * @var array
	 */
	protected function _setVideo($in, $old = null)
	{
		$to_assign = array_diff(array_keys($in['data']), array_keys($this->video));
		$this->__video_to_assign = [];
		foreach ($to_assign as $new_key)
			$this->__video_to_assign[] = $in['data'][$new_key];
		
		$changed = [];
        $comparable = ['inactive', 'ordering'];
		foreach ($in['data'] as $doc_id => $row)
		{
			if (isset($this->video[$doc_id]))
			{
                foreach ($comparable as $field) {
					if (isset($old['data'][$doc_id][$field]) && $old['data'][$doc_id][$field] != $this->video[$doc_id]->{$field}) {
						throw new Exception(self::MESSAGE_DATA_IS_CHANGED);
					} elseif ($this->video[$doc_id]->{$field} != $row[$field]) {
						$changed[$doc_id][$field] = $row[$field];
					}
                }
                
                foreach ($row['use'] as $lang => $use) {
					if (isset($old['data'][$doc_id]['use'][$lang]) && $old['data'][$doc_id]['use'][$lang] != $this->video[$doc_id]->use[$lang]) {
						throw new Exception(self::MESSAGE_DATA_IS_CHANGED);
					} elseif ($this->video[$doc_id]->use[$lang] != $use) {
						$changed[$doc_id]['use'][$lang] = $use;
					}
                }

				foreach ($row['codes'] as $lang => $code)
				{
					if (isset($old['data'][$doc_id]['codes'][$lang]) && $old['data'][$doc_id]['codes'][$lang] != $this->video[$doc_id]->codes[$lang]) {
						throw new Exception(self::MESSAGE_DATA_IS_CHANGED);
					} elseif ($this->video[$doc_id]->codes[$lang] != $code) {
						$changed[$doc_id]['codes'][$lang] = $code;
					}
				}
			}
		}
		$this->__video_to_change = $changed;
	}
	/**
	 * Save video 
	 */
	protected function _saveVideo()
	{
		foreach ($this->__video_to_assign as $video);
		{
			$doc_id = 0;
			foreach ($video['codes'] as $lang => $code)
				if (!empty($code)) {
					$doc_id = Saved::addYTDoc(
                        $this->_db, 
                        $this->_dbr, 
                        (int)$this->id, 
                        mysql_escape_string($code), 
                        mysql_escape_string($lang), 
                        (int)$doc_id);
				}
		}
		foreach ($this->__video_to_change as $doc_id => $data) {
            foreach ($data as $field => $row) {
                if ($field == 'use') {
                    foreach ($row as $lang => $use) {
                        $iid_use = (int)$this->_db->getOne('SELECT `iid` FROM `translation` 
                            WHERE `id` = ' . (int)$doc_id . ' 
                            AND `table_name` = "saved_doc" 
                            AND `field_name` = "use" 
                            AND `language` = "' . mysql_escape_string($lang) . '"
                            LIMIT 1');

                        if ($iid_use) {
                            $this->_db->query('UPDATE `translation` SET `value` = "' . (int)$use . '" WHERE `iid` = ' . $iid_use);
                        } else {
                            $this->_db->query('INSERT INTO `translation` SET `value` = "' . (int)$use . '"
                                , `id` = ' . (int)$doc_id . ' 
                                , table_name="saved_doc"  
                                , field_name="use"
                                , language = "' . mysql_escape_string($lang) . '"');
                        }            
                    }
                } else if ($field == 'codes') {
                    foreach ($row as $lang => $code) {
                        Saved::addYTDoc($this->_db, $this->_dbr, $this->id, $code, $lang, $doc_id);
                    }
                } else {
                    $this->_db->query('UPDATE `saved_doc` 
                        SET `' . mysql_escape_string($field) . '` = ' . (int)$row . '
                        WHERE `doc_id` = ' . $doc_id . ' LIMIT 1');
                }
            }
        }

		if (!empty($this->__video_to_assign) || !empty($this->__video_to_change))
		{
			cacheClear("Saved::getDocs({$this->id}%");
			cacheClear("Saved::getPics({$this->id},%");
			$this->_getVideo();
		}
			
		$this->__video_to_assign = [];
		$this->__video_to_change = [];
	}
    /**
     * Init editable fields for slave 
     */
    protected function _getEditableFields() 
    {
        $shop_fields = $this->_dbr->getRow("SELECT *
            FROM `shop` WHERE `siteid` = " . $this->getSavedParam('siteid') . " AND `username` = '" . $this->getSavedParam('username') . "'");
        $this->editable_fields = [];
        foreach ($shop_fields as $column => $value) {
            if (strpos($column, 'master_') !== false && !in_array($column, ['master_pics']) && !$value) {
                $field_name = str_replace('master_', '', $column);
                $this->editable_fields[] = $field_name;
            }
        }
    }
    /**
     * Getter for descriptionTextShop2
     */
    protected function _getDescriptionTextShop2() 
    {
        $this->_loadMulangField('descriptionTextShop2');
        
        foreach ($this->descriptionTextShop2 as $lang => $row) {
            if (!$row->value) {
                $row->value = self::getDescriptionTextShop2($this->id, $this->_shop->id, $lang);
            }
        }
    }
    /**
     * Setter for descriptionTextShop2 field
     * @param array $in
     * @param array|null $old
     */
    protected function _setDescriptionTextShop2($in, $old = null) 
    {
        $this->__descriptionTextShop2_changed = [];
        foreach ($in as $lang => $data) {
            if (isset($old[$lang]) && ($old[$lang]['value'] != $this->descriptionTextShop2[$lang]->value)) {
                throw new Exception(self::MESSAGE_DATA_IS_CHANGED);
            }
        
            if ($this->descriptionTextShop2[$lang]->value != $data['value']) {
                $this->descriptionTextShop2[$lang]->value = $data['value'];
                $this->__descriptionTextShop2_changed[] = $lang;
            }
        }
    }
    /**
     * Saver for descriptionTextShop2 field
     */
    protected function _saveDescriptionTextShop2() 
    {
        $mulang_source = [];
        foreach ($this->__descriptionTextShop2_changed as $lang) {
            $mulang_source['descriptionTextShop2'][$lang] = $this->descriptionTextShop2[$lang]->value;
        }
        mulang_fields_Update(['descriptionTextShop2'], 'sa', $this->id, $mulang_source);
    }
    /**
     * Function used to replace empty descriptionTextShop2
     * @param int $saved_id
     * @param int $shop_id
     * @param $lang
     * @return string
     */
    public static function getDescriptionTextShop2($saved_id, $shop_id, $lang)
    {
        if ( ! $shop_id || ! $saved_id) {
            return '';
        }
        
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $query = "SELECT DISTINCT CONCAT(
                COALESCE(`alias`.`name`, ''), ' ✓ ',
                (SELECT `value` FROM `translation` WHERE `table_name`='shop' 
                        AND `field_name`='title_suffix'
                        AND `id`=?
                        AND `language`=?)
            )

            FROM offer_name alias

            LEFT JOIN translation tShopDesription on 
                tShopDesription.table_name='sa'
                and tShopDesription.field_name='ShopDesription'
                and tShopDesription.language = ?
                and tShopDesription.value=alias.id

            WHERE tShopDesription.id=?
            LIMIT 1";

        return $dbr->getOne($query, null, [$shop_id, $lang, $lang, $saved_id]);
    }
	
    static function getDocs($db, $dbr, $saved_id, $inactive='', $lang='', $deflang='', $file=0, $system=0, $cached=0)
    {
		if (!(int)$saved_id) return array();
		global $smarty;
		global $debug;
		global $_SERVER_REMOTE_ADDR;
//		if ($_SERVER_REMOTE_ADDR == '37.29.74.218') $debug=1;
        $params = implode(chr(0), [$saved_id, $inactive, $lang, $deflang, $file, $system]);
		$function = "Saved::getDocs($params)_b";
		$chached_ret = cacheGet($function, (int)$shop_id, $lang);
		$chached_ret_r = cacheGet($function.'_r', (int)$shop_id, $lang);
		if (!$debug && $cached && $chached_ret && $chached_ret_r) {
            self::$doc_data_translations = $chached_ret_r;
            $smarty->assign('data_translations'.$file, $chached_ret_r);
            return $chached_ret;
		}
		$q = "SELECT saved_doc.doc_id, saved_doc.name, saved_doc.saved_id, saved_doc.primary, saved_doc.inactive, saved_doc.white_back, ordering, saved_doc.dimensions
			from saved_doc 
			where file=$file and `system`=$system and saved_id=$saved_id $inactive ORDER BY `primary` desc, ordering";
		if ($debug) echo "$q<br>";
        $docs = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
			die();
        }
		if ($system) {
            cacheSet($function.'_r', (int)$shop_id, $lang, $r);
            cacheSet($function, (int)$shop_id, $lang, $docs);
            
            return $docs;
        }
        
		$table_name = 'saved_doc';
		$fld = 'data';
		$r = array();
		foreach ($docs as $key=>$doc) {
			$r[$doc->doc_id] = $dbr->getAssoc("select language, iid from translation where id='{$doc->doc_id}'
					       and table_name='$table_name' and field_name='$fld'");
            
			foreach($r[$doc->doc_id] as $language=>$iid) {
				$r[$doc->doc_id][$language] = $dbr->getRow("select translation.*, IFNULL(versions.version,0) version
					from translation 
					left join versions on translation.id=versions.id and translation.table_name=versions.table_name
								and translation.field_name=versions.field_name and translation.language = versions.language
					where translation.iid=$iid");
	   			$exts = explode('.', $r[$doc->doc_id][$language]->value); $ext = end($exts);
				$r[$doc->doc_id][$language]->ext = strtolower($ext);
				$r[$doc->doc_id][$language]->type = in_array($ext,array('flv'))?'video':'pic';
				$trrec = $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
							,SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_by
						from translation 
						left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
						left join users on users.system_username=total_log.username
						where 1
						and translation.iid = $iid
						group by translation.id
						order by translation.id+1
						");
				$r[$doc->doc_id][$language]->last_on = $trrec->last_on;
				$r[$doc->doc_id][$language]->last_by = $trrec->last_by;
				if ($r[$doc->doc_id][$language]->type=='video') {
					$q = "select md5 from prologis_log.translation_files2
						where id='{$doc->doc_id}' 
						and table_name='saved_doc' and field_name='data' and language = '$language'";
//					echo $q;
					$rr = $dbr->getOne($q);
                    $rr = get_file_path($rr);
                                        
					file_put_contents("tmppic/{$doc->doc_id}.flv", $rr);
					$movie = new ffmpeg_movie("tmppic/{$doc->doc_id}.flv");
					if (is_a($movie,'ffmpeg_movie')) {
						$r[$doc->doc_id][$language]->X = $movie->getFrameWidth();
						$r[$doc->doc_id][$language]->Y = $movie->getFrameHeight();
					}
					unlink("tmppic/{$doc->doc_id}.flv");
//					print_r($r);
				} // video size
			} // foreach uploaded files
			$youtubes = $dbr->getAssoc("select language, `value` from translation where id='{$doc->doc_id}'
					       and table_name='$table_name' and field_name='youtube_code'");
			foreach($youtubes as $language=>$code) {
				$r[$doc->doc_id][$language]->youtube_code = $code;
			}
			$alts = $dbr->getAssoc("select language, `value` from translation where id='{$doc->doc_id}'
					       and table_name='$table_name' and field_name='alt'");
			foreach($alts as $language=>$code) {
				$r[$doc->doc_id][$language]->alt = $code;
			}
			$titles = $dbr->getAssoc("select language, `value` from translation where id='{$doc->doc_id}'
					       and table_name='$table_name' and field_name='title'");
			foreach($titles as $language=>$code) {
				$r[$doc->doc_id][$language]->title = $code;
			}
			$uses = $dbr->getAssoc("select language, `value` from translation where id='{$doc->doc_id}'
					       and table_name='$table_name' and field_name='use'");
			foreach($uses as $language=>$code) {
				$r[$doc->doc_id][$language]->use = $code;
			}
			$docs[$key]->type = $r[$doc->doc_id][$lang]->type;
			$docs[$key]->ext = $r[$doc->doc_id][$lang]->ext;
			if (!strlen($docs[$key]->ext)) $docs[$key]->ext = 'jpg';
			$docs[$key]->alt = $r[$doc->doc_id][$lang]->alt;
			$docs[$key]->title = $r[$doc->doc_id][$lang]->title;
			$docs[$key]->X = $r[$doc->doc_id][$lang]->X;
			$docs[$key]->Y = $r[$doc->doc_id][$lang]->Y;
			$docs[$key]->youtube_code = $r[$doc->doc_id][$lang]->youtube_code;
			$docs[$key]->use = $r[$doc->doc_id][$deflang]->use + $r[$doc->doc_id][$lang]->use;
			$docs[$key]->version = $r[$doc->doc_id][$lang]->version;
//			echo 'youtube_code{'.$key.'}('.$doc->doc_id.')='.$r[$doc->doc_id][$lang]->youtube_code.'<br>';
			if (!$docs[$key]->type) $docs[$key]->type = $r[$doc->doc_id][$deflang]->type;
			if (!$docs[$key]->alt) $docs[$key]->alt = $r[$doc->doc_id][$deflang]->alt;
			if (!$docs[$key]->title) $docs[$key]->title = $r[$doc->doc_id][$deflang]->title;
		} // foreach doc
		self::$doc_data_translations = $r;
		$smarty->assign('data_translations'.$file, $r);
		if ($debug) { echo 'Pics0: '; print_r($docs); }
		cacheSet($function.'_r', (int)$shop_id, $lang, $r);
		cacheSet($function, (int)$shop_id, $lang, $docs);
        return $docs;
    }

    /**
     * 
     * Return pics for new logic (by react)
     * 
     * @global type $debug
     * @global type $shop_id
     * @global type $lang
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param type $saved_id
     * @param type $inactive
     * @param bool $cached
     * @return array
     */
    static function getPics(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $saved_id, $inactive=0, $cached=0)
    {
        $saved_id = (int)$saved_id;
        
        if ( ! $saved_id) {
            return [];
        }
        
		global $debug;
		global $shop_id, $lang;
        
        $function = "Saved::getPics($saved_id, $inactive)";
		$chached_ret = cacheGet($function, (int)$shop_id, $lang);
		if (!$debug && $cached && $chached_ret) {
            return $chached_ret;
		}
        
        $dimensions = $dbr->getOne('SELECT `dimensions` FROM `shop` WHERE `id` = ' . (int)$shop_id);

		$q = "SELECT *, 'pic' AS `type` FROM `saved_pic`
			WHERE `saved_id` = $saved_id 
            AND `inactive` IN ($inactive) 
            AND `img_type` != " . ($dimensions == self::DIMENSION_INCH ? 'dimensions_cm' : 'dimensions_inch') . "
            ORDER BY `img_type`, `ordering`";

		if ($debug) echo "$q<br>";
        $docs = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
			die();
        }

		cacheSet($function, (int)$shop_id, $lang, $docs);
        return $docs;
    }

    static function addLargeDoc($db, $dbr, $saved_id,
		$name, $description, $fn, $lang, $edit_id=0, $file=0, $use=0)
    {
		$name = mysql_escape_string($name);
		$description = mysql_escape_string($description);
        
//		echo "UPLOAD new doc $newfn<br>";
		if (!$edit_id) {
			$r = $db->query("insert into saved_doc set saved_id=$saved_id, file=$file");
			$edit_id = mysql_insert_id();
		}
        
		$table_name = 'saved_doc'; $fld = 'data';
		$iid = (int)$dbr->getOne("select iid from translation where id=$edit_id 
			and table_name='$table_name' and field_name='data' and language = '$lang'");
		if ($iid) {
			$q = "update translation set value='$name' where iid=$iid";
			$r = $db->query($q);
			if (PEAR::isError($r)) { aprint_r($r); die();}
		} else {
			$q = "insert into translation set value='$name'
			, id=$edit_id 
			, table_name='$table_name' , field_name='data'
			, language = '$lang'";
			$r = $db->query($q);
			if (PEAR::isError($r)) { aprint_r($r); die();}
			$iid = mysql_insert_id();
		}

		update_version($table_name, $fld, $edit_id, $lang);

        $file_content = file_get_contents($fn);
        $md5 = md5($file_content);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $file_content);
        }
        
		$iid = (int)$dbr->getOne("select iid from prologis_log.translation_files2 where id='$edit_id' 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
        
		if ($iid) {
			$r = $db->query("update prologis_log.translation_files2 set md5='$md5' where iid=$iid");
		} else {
			$r = $db->query("insert into prologis_log.translation_files2 set 
					id='$edit_id', 
					table_name='$table_name' , field_name='$fld',
					language = '$lang', 
					md5='$md5'");
		}
        
		if (PEAR::isError($r)) { aprint_r($r); die();}
		$q = "select distinct shop.id, ftp_server,  ftp_password, ftp_username
			from saved_params
			join shop on SUBSTRING_INDEX(SUBSTRING_INDEX(par_key,']',1),'[',-1)=shop.id
			where saved_id=$saved_id
			and par_key like 'shop_catalogue_id%'";
		$shops = $dbr->getAll($q);
		foreach($shops as $shop) {
			if (!strlen($shop->ftp_server)) continue;
			$conn_id = ftp_connect($shop->ftp_server);
//			echo 'connect to '.$shop->ftp_server.'<br>';
			ftp_login ($conn_id, $shop->ftp_username, $shop->ftp_password);
			$mode = ftp_pasv($conn_id, TRUE);
			$buff = ftp_nlist($conn_id, "public_html/images/cache/*picid_".$edit_id."_*.*");
//			echo "try to find a mask "."public_html/images/cache/*picid_".$edit_id."_*.*".'<br>';
			foreach($buff as $fn) {
//				echo "checking $fn<br>";
				if (strpos($fn, "picid_".$edit_id)) {
					$r = ftp_delete($conn_id, $fn);
//					echo 'delete FTP'.$fn.'<br>';
				}
			}
			ftp_close($conn_id);
		}
		foreach (glob("images/cache/*picid_".$edit_id."_*.*") as $filename) {
		    unlink($filename);
            $logger = new \Monolog\Logger('deleted_images');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(TMP_DIR.'/deleted_images.txt'));
            $logger->info('found', ['file' => __FILE__, 'line' => __LINE__, 'filename' => $filename, 'user' => (isset($_COOKIE['ebas_username']) ? $_COOKIE['ebas_username'] : 'not set'),]);
//			echo 'delete file '.$filename.'<br>';
		}
        
		$q = "select iid from translation where id=$edit_id 
			and table_name='$table_name' and field_name='use' and language = '$lang'";
		$iid_use = (int)$dbr->getOne($q);
#		echo "iid_use=$iid_use<br>";
		if ($iid_use) {
			$q = "update translation set value='$use' where iid=$iid_use";
		} else {
			$q = "insert into translation set value='$use'
			, id=$edit_id 
			, table_name='$table_name' , field_name='use'
			, language = '$lang'";
		}
//		echo $q;
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		return $edit_id;	
    }

    static function addDocAlt($db, $dbr, $saved_id,
		$lang, $edit_id=0
		, $alt='', $title='', $use=0
		)
    {
		$alt = mysql_escape_string($alt);
		$title = mysql_escape_string($title);
		if (!$edit_id) {
			$r = $db->query("insert into saved_doc set 
					saved_id=$saved_id, file=0");
			$edit_id = mysql_insert_id();
		}			
		$table_name = 'saved_doc'; 
		$r = $db->query($q);
		$iid_alt = (int)$dbr->getOne("select iid from translation where id='$edit_id' 
			and table_name='$table_name' and field_name='alt' and language = '$lang'");
		if ($iid_alt) {
			$q = "update translation set value='$alt' where iid=$iid_alt";
		} else {
			$q = "insert into translation set value='$alt'
			, id='$edit_id' 
			, table_name='$table_name' , field_name='alt'
			, language = '$lang'";
		}
//		echo $q.'<br>';
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}

		$iid_title = (int)$dbr->getOne("select iid from translation where id='$edit_id' 
			and table_name='$table_name' and field_name='title' and language = '$lang'");
		if ($iid_title) {
			$q = "update translation set value='$title' where iid=$iid_title";
		} else {
			$q = "insert into translation set value='$title'
			, id='$edit_id' 
			, table_name='$table_name' , field_name='title'
			, language = '$lang'";
		}
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}

		$q = "select iid from translation where id='$edit_id' 
			and table_name='$table_name' and field_name='use' and language = '$lang'";
#		echo $q.'<br>';
		$iid_use = (int)$dbr->getOne($q);
#		echo "iid_use=$iid_use<br>";
		if ($iid_use) {
			$q = "update translation set value='$use' where iid=$iid_use";
		} else {
			$q = "insert into translation set value='$use'
			, id='$edit_id' 
			, table_name='$table_name' , field_name='use'
			, language = '$lang'";
		}
#		echo $q.'<br>'; die();
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		return $edit_id;	
    }

    static function addYTDoc($db, $dbr, $saved_id,
		$code, $lang, $edit_id=0
		)
    {
		$code = mysql_escape_string($code);
		if (!$edit_id) {
			$r = $db->query("insert into saved_doc set 
					saved_id=$saved_id, file=0");
			$edit_id = $db->getOne('select LAST_INSERT_ID()');
		}
		
		if (!$edit_id)
			throw new Exception('doc_id is not defined');
			
		$table_name = 'saved_doc'; $fld = 'youtube_code';
		$iid = (int)$dbr->getOne("select iid from translation where id='$edit_id' 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
		if ($iid) {
			$q = "update translation set value='$code' where iid='$iid'";
		} else {
			$q = "insert into translation set value='$code'
			, id='$edit_id' 
			, table_name='$table_name' , field_name='$fld'
			, language = '$lang'";
		}
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		return $edit_id;	
    }

    static function deleteDoc($db, $dbr, $doc_id, $lang='')
    {
        $doc_id = (int)$doc_id;
		$table_name = 'saved_doc'; 
		$edit_id = $doc_id;
		if ($lang=='') {
	        $r = $db->query("delete from saved_doc where doc_id=$doc_id");
			if (PEAR::isError($r)) { aprint_r($r); die();}
			$langwhere = "";
		} else {
			$langwhere = " and language = '$lang'";
		}
	    $r = $db->query("delete from prologis_log.translation_files2 where id='$edit_id' 
			and table_name='$table_name' $langwhere");
		if (PEAR::isError($r)) { aprint_r($r); die();}
	    $r = $db->query("delete from translation where id='$edit_id' 
			and table_name='$table_name' $langwhere");
		if (PEAR::isError($r)) { aprint_r($r); die();}
    }

    static function setDocPrimary($db, $dbr, $doc_id, $block)
    {
        $doc_id = (int)$doc_id;
		$saved_id=$dbr->getOne("select saved_id from saved_doc where doc_id=$doc_id");
        $r = $db->query("update saved_doc set `primary`=0 where saved_id=$saved_id and `primary`=$block");
        $r = $db->query("update saved_doc set `primary`=$block where doc_id=$doc_id");
    }
	
    static function setDocDimensions($db, $dbr, $doc_id, $value = 1)
    {
        $doc_id = (int)$doc_id;
        $r = $db->query("update saved_doc set `dimensions`=$value where doc_id=$doc_id");
    }

    static function reorderDocs($db, $dbr,$shopimgordering) {
		foreach($shopimgordering as $doc_id=>$ordering) {
			$q = "update saved_doc set ordering=$ordering where doc_id=$doc_id";
	        $r = $db->query($q);
			if (PEAR::isError($r)) { aprint_r($r); die();}
			echo "$q<br>";
		}
	}
	
    static function setDocPreview($db, $dbr, $doc_id, $data, $lang)
    {
        $doc_id = (int)$doc_id;
		$table_name = 'saved_doc'; $fld = 'preview';
		$edit_id = $doc_id; 
		$iid = (int)$dbr->getOne("select iid from prologis_log.translation_files2 where id='$edit_id' 
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
		$md5 = md5($data);
        
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $data);
        }
        
		if ($iid) {
			$q = "update prologis_log.translation_files2 set md5 = '$md5' where iid='$iid'";
		} else {
			$q = "insert into prologis_log.translation_files2 set data_id=$data_id
			, id=$edit_id 
			, table_name='$table_name' , field_name='$fld'
            , md5 = '$md5'
			, language = '$lang'";
		}
//		die($q);
		$r = $db->query($q);
		if (PEAR::isError($r)) print_r($r);
    }

    static function getComments($db, $dbr, $id)
    {
		if (is_array($id) && !empty($id))
		{
			$where_str_1 = 'saved_id IN (' . implode(', ', $id) . ')';
			$where_str_2 = 'saved_auctions.id IN (' . implode(', ', $id) . ')';
		}
		else
		{
			$where_str_1 = "saved_id=$id";
			$where_str_2 = "saved_auctions.id=$id";
		}
		
		$q = "SELECT '' as prefix
			, saved_comment.id
			, saved_comment.create_date
			, saved_comment.username
			, saved_comment.username as cusername
			, IFNULL(users.name, saved_comment.username) full_username
			, IFNULL(users.name, saved_comment.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, saved_comment.comment
			, users.id user_id
			, employee.id employee_id
			 from saved_comment 
			 LEFT JOIN users ON saved_comment.username = users.username
			 left join employee on employee.username=users.username
			where $where_str_1
		UNION ALL
		select CONCAT('Alarm (',alarms.status,'):') as prefix
			, NULL as id
			, (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
			, alarms.username username
			, alarms.username cusername
			, IFNULL(users.name, alarms.username) full_username
			, IFNULL(users.name, alarms.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, alarms.comment 
			, users.id user_id		
			, employee.id employee_id
			from saved_auctions
			join alarms on alarms.type='saved_auctions' and alarms.type_id=saved_auctions.id
			LEFT JOIN users ON alarms.username = users.username
		    left join employee on employee.username=users.username
			where $where_str_2
		ORDER BY create_date";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addComment($db, $dbr, $id,
		$username,
		$create_date,
		$comment
		)
    {
        $id = (int)$id;
		$username = mysql_escape_string($username);
		$create_date = mysql_escape_string($create_date);
		$comment = mysql_escape_string($comment);
        $r = $db->query("insert into saved_comment set 
			saved_id=$id, 
			username='$username',
			create_date='$create_date',
			comment='$comment'");
    }

    static function delComment($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("delete from saved_comment where id=$id");
    }

    /**
     * Update `saved_auction` table
     */
    public function updateData($export)
    {
        $this->_db->query("UPDATE `saved_auctions` 
                SET `export` = '" . ((int)$export ? '1' : '0') . "' 
                WHERE `id` = '" . $this->id . "'");
    }
    
	/**
	 * Save method
	 */
	public function update($fields)
	{
        $this->_toDebug('- UPDATE MULANG -');
        
		$this->_saveMulang($fields);
        
		foreach($fields as $field)
		{
            $this->_toDebug("- UPDATE $field -");
			if (method_exists($this, '_save' . self::snakeToCamel($field)))
			{
				$method = '_save' . self::snakeToCamel($field);
				$this->$method();
			}
		}
        
        cacheClearFast("getOffer({$this->id})");
	}
	/**
	 * Save dimensions
	 */
	private function _saveDimensions()
	{
		$this->_saved_dimensions->save();
	}
	/**
	 * Save saved_params
	 */
	private function _saveSavedParams()
	{
		foreach ($this->saved_params_to_update as $param_name)
		{
			$exist = $this->_db->getOne("SELECT id FROM `saved_params` 
				WHERE `saved_id` = {$this->id} 
				AND `par_key` = '" . mysql_escape_string($param_name) . "'");
			
            if ($param_name == 'offer_id') { // we need to check is offer hidden
                $value = Offer::getNotHiddenId($this->getSavedParam($param_name));
            } else {
                $value = mysql_escape_string($this->getSavedParam($param_name));
            }
            
			if ($exist) {
				$r = $this->_db->query("UPDATE `saved_params` SET `par_value` = '" . $value . "'
					WHERE `par_key` = '" . mysql_escape_string($param_name) . "'
					AND `saved_id` = " . $this->id . "
					LIMIT 1");
				if (PEAR::isError($r)) { aprint_r($r); die();}
			} else {
				$r = $this->_db->query("INSERT INTO `saved_params` 
					SET `par_value` = '" . $value . "',
					`par_key` = '" . mysql_escape_string($param_name) . "',
					`saved_id` = " . $this->id);
				if (PEAR::isError($r)) { aprint_r($r); die();}
			}
		}
		$this->saved_params_to_update = array();
	}
	/**
	 * Save only mulang SA fields
	 */
	private function _saveMulang($fields)
	{
		if (empty($fields))
			$fields = self::$MULANG_FIELDS;
		
		$mulang_source = array();
		foreach ($fields as $field)
		{
			if ($this->$field)
			{
				if ($this->_isMulangField($field))
				{
					foreach ($this->$field as $lang => $val)
					{
						$mulang_source[$field][$lang] = $val->value;
					}
				}
			}
		}
        
		if ($mulang_source) {
			mulang_fields_Update($fields, 'sa', $this->id, $mulang_source);
        }
	}
	/*
	 * Check field is mulang
	 * @param string
	 */
	private function _isMulangField($field)
	{
		return in_array($field, self::$MULANG_FIELDS);
	}
    
    /**
     * Get data from `saved_params` and convert into array like `saved_auctions`.`details`
     * 
     * @param int $saved_id
     * @param bool $default Use old details or not 
     * @return array
     */
    public static function getDetails($saved_id, $default = true) 
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        if ($default) {
            $details = $db->getOne("SELECT details FROM saved_auctions WHERE id={$saved_id}");
            $details = (array)unserialize($details);

            $default_details = $details;
        } else {
            $default_details = $details = [];
        }

        $query = "SELECT `par_key`, `par_value` FROM `saved_params` WHERE `saved_id` = $saved_id";
        foreach ($db->getAll($query) as $row) {
            $par_key = $row->par_key;
            $par_value = $row->par_value;
            
            if (strpos($par_key, '[') === false) {
                if (isset($default_details[$par_key])) {
                    $details[$par_key] = $par_value;
                }
                else {
                    $details[$par_key] = $par_value;
                }
            }
            else if (preg_match('#^(.+)\[(.+)\]\[(.+)\]\[(.+)\]$#iu', $par_key, $matches)) {
                if (isset($default_details[$matches[1]][$matches[2]][$matches[3]][$matches[4]])) {
                }
                
                if (isset($details[$matches[1]][$matches[2]][$matches[3]][$matches[4]])) {
                    if ( !is_array($details[$matches[1]][$matches[2]][$matches[3]][$matches[4]])) {
                        continue;
                        $details[$matches[1]][$matches[2]][$matches[3]][$matches[4]] = [$details[$matches[1]][$matches[2]][$matches[3]][$matches[4]]];
                    }
                    $details[$matches[1]][$matches[2]][$matches[3]][$matches[4]][] = $par_value;
                }
                else {
                    $details[$matches[1]][$matches[2]][$matches[3]][$matches[4]] = $par_value;
                }
            }
            else if (preg_match('#^(.+)\[(.+)\]\[(.+)\]$#iu', $par_key, $matches)) {
                if (isset($default_details[$matches[1]][$matches[2]][$matches[3]])) {
                }
                
                if (isset($details[$matches[1]][$matches[2]][$matches[3]])) {
                    if ( !is_array($details[$matches[1]][$matches[2]][$matches[3]])) {
                        continue;
                        $details[$matches[1]][$matches[2]][$matches[3]] = [$details[$matches[1]][$matches[2]][$matches[3]]];
                    }
                    $details[$matches[1]][$matches[2]][$matches[3]][] = $par_value;
                }
                else {
                    $details[$matches[1]][$matches[2]][$matches[3]] = $par_value;
                }
            }
            else if (preg_match('#^(.+)\[(.+)\]$#iu', $par_key, $matches)) {
                if (isset($default_details[$matches[1]][$matches[2]])) {
                }
                
                if (strpos($par_key, 'material_id[') !== false && isset($details['material_id']) && is_string($details['material_id'])) {
                    continue;
                }
                
                if (strpos($par_key, 'new_other_sa[') !== false && isset($details['new_other_sa']) && is_string($details['new_other_sa'])) {
                    continue;
                }
                
                if (strpos($par_key, 'new_other_NameID[') !== false && isset($details['new_other_NameID']) && is_string($details['new_other_NameID'])) {
                    continue;
                }
                
                if (isset($details[$matches[1]][$matches[2]])) {
                    if ( !is_array($details[$matches[1]][$matches[2]])) { // value is not an array
                        if (strpos($par_key, 'stop_empty_warehouse') !== false)
                        {
                            $details[$matches[1]][$matches[2]] = $par_value;
                        } else {
                            if (strpos($par_key, 'shop_catalogue_id') === false)
                            {
                                continue;
                            }
                    
                            $details[$matches[1]][$matches[2]] = [$par_value];
                        }
                    } else {
                        $details[$matches[1]][$matches[2]][] = $par_value;
                    }
                }
                else {
                    $details[$matches[1]][$matches[2]] = $par_value;
                }
            }
        }
        
        if ( ! isset($details['saved_id']) || ! $details['saved_id']) {
            $details['saved_id'] = $saved_id;
        }

        return $details;
    }
    
    /**
     * 
     * @param type $details
     * @return type
     */
    public static function detailsToArray($details, $global_key = '') 
    {
        $return = [];
        foreach ($details as $_key => $_value)
        {
            if (strpos($_key, 'saved_custom_params') !== false || strpos($_key, 'custom_cat_par') !== false)
            {
                unset($details[$_key]);
                continue;
            }
            
            $_key = $global_key . $_key;
            
            if ( !is_array($_value))
            {
                $return["[[" . $_key . "]]"] = $_value;
            }
            else 
            {
                $return = array_merge($return, self::detailsToArray($_value, $_key . "_"));
            }
        }
        return $return;
    }
       
    /**
     * Clear redis cache for current offer
     */
    public function clear_redis_cache($master_id = true) {
      
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $details = Saved::getDetails($this->id);
        
        $shop_catalogue_ids = [];
        if (isset($details['shop_catalogue_id']) && is_array($details['shop_catalogue_id'])) {
            foreach ($details['shop_catalogue_id'] as $_sid => $_catalogue) {
                $shop_catalogue_ids[$_sid] = array_merge((array)$_catalogue, (array)$shop_catalogue_ids[$sid]);
            }
        }
        $shop_id = (int)$db->getOne("select id from shop where username = '{$details['username']}' and inactive = '0'");
        $shops[$shop_id][] = $this->id;
        
        if ( ! $master_id) {
            $slaves = $db->getAssoc("SELECT `saved_id` AS `f1`, `saved_id` AS `f2` FROM `saved_params`
                WHERE `par_key` = 'master_sa' AND `par_value` = {$this->id}");
            foreach ($slaves as $_id) {
                $details = Saved::getDetails($_id);
                $shop_id = (int)$db->getOne("select id from shop where username = '{$details['username']}' and inactive = '0'");
                $shops[$shop_id][] = $_id;
                
                if (isset($details['shop_catalogue_id']) && is_array($details['shop_catalogue_id'])) {
                    foreach ($details['shop_catalogue_id'] as $_sid => $_catalogue) {
                        $shop_catalogue_ids[$_sid] = array_merge((array)$_catalogue, (array)$shop_catalogue_ids[$sid]);
                    }
                }
            }
        }

        $catalogue_sorts = ['visits', '-visits', 'price', '-price', 'bestrated', '-bestrated', ''];
        $fns = [];
        
        $_delete_catalogues = [];
        $_delete_offers = [];
        
        foreach (array_keys($shops) as $sid) {
            $sid = (int)$sid;
            
            $shop_pic_color = $db->getOne("SELECT shop_pic_color FROM `shop` WHERE `id` = '$sid'");
            $langs = $db->getAssoc("select v.value, v.description
                from  config_api_values v
                join seller_lang sl on sl.lang=v.value
                join shop s on sl.username=s.username
                where v.par_id=6 and not v.inactive
                and sl.useit=1 and s.id = '$sid'
                order by sl.ordering");            
            $langs = array_keys($langs);
            
            foreach ($langs as $_lang) {
                foreach ($shops[$sid] as $_id) {
                    $_delete_offers[$_id] = true;
//                    $fns[$sid][$_lang]["getOffer({$_id})"] = 1;
                }
//                $fns[$sid][$_lang]["frontOffers()"] = 1;
                
                foreach ($shop_catalogue_ids[$sid] as $_catalogue) {
                    $_delete_catalogues[$_catalogue] = true;
                }
            }
        }
        
        cacheClear("frontOffers()");
        foreach ($_delete_catalogues as $_catalogue => $_dummy) {
            cacheClear("getOffers($_catalogue%");
        }
        foreach ($_delete_offers as $_id => $_dummy) {
            cacheClear("getOffer($_id)");
            cacheClear("getOfferDetails($_id)");
        }
        
//        $showed = [];
//        $prepare_fns = [];
//        foreach ($fns as $_shop => $shop_data) {
//            foreach ($shop_data as $_lang => $langs_data) {
//                foreach ($langs_data as $_fn => $dummy) {
//                    $_fn = str_replace(' ', '', $_fn);
//                    cacheClearFast($_fn, $_shop, $_lang);
//                    if ( ! isset($showed[$_fn])) {
//                        $showed[$_fn] = true;
//                        cacheClearFast($_fn, 0, '');
//                    }
//                    
//                    $prepare_fns[] = "~~{$_shop}~~{$_fn}~~{$_lang}~~";
//                }
//            }
//        }
//        return $prepare_fns;
        return true;
    }
        
    /**
     * @param int $master_id
     * @return boolean
     */
    public function add_urls_to_spider($master_id = true) {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $ids = [$this->id];
        if ( ! $master_id) {
            $slaves = $dbr->getAssoc("SELECT `saved_id` AS `f1`, `saved_id` AS `f2` FROM `saved_params`
                WHERE `par_key` = 'master_sa' AND `par_value` = {$this->id}");
            foreach ($slaves as $_id) {
                $ids[] = (int)$_id;
            }
        }

        $response = [];
        
        foreach ($ids as $sa_id) {
            $details = Saved::getDetails($sa_id);
            $shop_id = $dbr->getRow("SELECT `id`, `leafs_only`, `username` FROM `shop` 
                    WHERE `username` = '{$details['username']}' AND NOT inactive LIMIT 1");
            if ( ! $shop_id || ! $shop_id->id) {
                continue;
            }
            
            $username = $shop_id->username;
            $leafs_only = $shop_id->leafs_only;
            $shop_id = $shop_id->id;
            
            $pages = $dbr->getAssoc("SELECT `page` AS `key`, `page` AS `val` FROM `sa_shop_url` WHERE `sa_id` = $sa_id");
            $db->execParam("DELETE FROM `sa_shop_url` WHERE `sa_id` = ?", [$sa_id]);
            
            foreach ($pages as $_url) {
                $db->execParam("DELETE FROM `sa_shop_url` WHERE `page` = ?", [$_url]);
                
                if ($_url) {
                    $_url = explode('/', $_url);
                    array_shift($_url);
                    $_url = implode('/', $_url);
                
                    $response[$shop_id][] = "/$_url";
                }
            }

            $pages = $dbr->getAssoc("SELECT `value`, `id` FROM `translation` 
                    WHERE `table_name` = 'sa' AND `field_name` = 'ShopSAAlias' AND `id` = $sa_id");

            foreach ($pages as $_url => $dummy) {
                if ($_url) {
                    $response[$shop_id][] = "/{$_url}.html";
                }
            }

            $cats_ids = $this->_dbr->getAssoc("SELECT DISTINCT sc1.id f1, sc1.id f2
                FROM saved_params sp
                JOIN shop_catalogue sc1 ON sc1.id=sp.par_value
                JOIN shop_catalogue_shop scs1 ON sc1.id=scs1.shop_catalogue_id
                WHERE sc1.hidden=0 AND scs1.hidden=0 AND scs1.shop_id='" . $shop_id . "'
                    AND sp.saved_id='" . $sa_id . "'
                    AND sp.`par_key` = 'shop_catalogue_id[" . $shop_id . "]' "
                . ($leafs_only ? " and not exists (select null
                    from shop_catalogue sc
                    join shop_catalogue_shop scs on sc.id=scs.shop_catalogue_id
                    where sc.hidden=0 and scs.hidden=0 and sc.parent_id=scs1.shop_catalogue_id and scs.shop_id='" . $shop_id . "')" : '')
            );
            
            $langs = $this->_dbr->getAssoc("SELECT `lang`, `lang` `v`
                FROM `seller_lang`
                WHERE `username` = '" . mysql_real_escape_string($username) . "'
                    AND `useit` = 1");
            
            foreach ($langs as $lang)
            {
                $shopCatalogue = new \Shop_Catalogue($db, $dbr, $shop_id, $lang);
                foreach ($cats_ids as $cat_id1) 
                {
                    $cat_array = $shopCatalogue->getAllNodesRecs($cat_id1);
                    $sumalias = '/';
                    foreach ($cat_array as $catid => $catname) 
                    {
                        $sumalias .= $catname->alias . '/';
                        $response[$shop_id][] = $sumalias;
                    }
                } // foreach folder
            }
        }
        
        foreach ($response as $shop_id => $_urls) {
            $_urls = array_values(array_unique($_urls));
            foreach ($_urls as $_url) {
                add_url_to_spider($shop_id, $_url);
            }
        }
        
        return $response;
    }
    
	/**
	 * Defines is it multi
	 */
	public function isMulti()
	{
		return $this->getSavedParam('master_multi') !== '';
	}
	/**
	 * Defines is it multi
	 */
	public function isMultiMaster()
	{
		return $this->getSavedParam('master_multi') !== '' && $this->getSavedParam('master_multi') == 0;
	}
    
    public function setMainMulti($main_id) {
        
        $main_parent_id = $this->_dbr->getOne("SELECT `par_value` FROM `saved_params` 
                WHERE `saved_id` = ? AND `par_key` = 'master_multi'", null, [$main_id]);
        
        if ($main_parent_id == $this->id) {
            $ids = $this->_dbr->getAssoc("SELECT `iid` AS `key`, `iid` AS `value` FROM `translation` 
                WHERE `table_name` = 'sa' AND `field_name` = 'ShopMultiDesription' AND `id` = ?", null, [$this->id]);

            if ($ids) {
                $this->_db->query("UPDATE `translation` SET `id` = '$main_id' 
                    WHERE `iid` IN (" . implode(',', $ids) . ")");
            }

            $this->_db->query("UPDATE `saved_params` SET `par_value` = '$main_id' 
                WHERE `par_key` = 'master_multi' AND `par_value` = '{$this->id}'");

            $this->_db->query("UPDATE `saved_params` SET `par_value` = '$main_id' 
                WHERE `par_key` = 'master_multi' AND `saved_id` = '{$this->id}'");

            $this->_db->query("UPDATE `saved_params` SET `par_value` = '0' 
                WHERE `par_key` = 'master_multi' AND `saved_id` = '$main_id'");
        }
    }
    
    /**
     * Create multi SA
     * @return boolean
     */
    public function createMulti() {
        if ( ! $this->isMulti()) {
            $r = $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`)
                VALUES({$this->id}, 'master_multi', '0')");
                
            if (PEAR::isError($r)) { aprint_r($r); die();}
            
            $ShopDesription = $this->_loadMulangField('ShopDesription', 1);
            $titles = $this->_getTitles();

            $mulang_source = [];
            foreach ($ShopDesription as $lang => $item) {
                if ($item->value && isset($titles[$lang][$item->value])) {
                    $mulang_source['ShopMultiDesription'][$lang] = $titles[$lang][$item->value];
                }
            }

            mulang_fields_Update(['ShopMultiDesription'], 'sa', $this->id, $mulang_source);
            
            return $this->id;
        }
        
        return 0;
    }
    
    /**
     * Put message into debug collector
     * @param string $message
     */
    private function _toDebug($message) {
        $timer = round(microtime(true) - $this->_timer, 2);
        $this->_debug[] = $message . '(' . $timer . 's)';
    }
    /**
     * Output debug info
     * @return string
     */
    public function getDebug() {
        return implode('", \n"', $this->_debug);
    }
    /**
     *
     */
    public static function convertSavedParamsToArray($rows) {
        $extractRowNumber = function($par_key) {
           $parts = explode('[', $par_key);

            if (isset($parts[1])) {
                $parts1 = explode(']', $parts[1]);
                if (isset($parts1[0])) {
                    return $parts1[0];    
                }
            }

            return false;
        };
        
        $data = [];
        foreach ($rows as $row) {
            $row_id = $extractRowNumber($row->par_key);

            $parts = explode('[', $row->par_key); // InternationalShippingContainer[0][InternationalShippingCountry]
            $parts1 = explode(']', $parts[2]);
            $field = explode(']', $parts1[0]);
            
            if ($field[0] == 'InternationalShippingCountry') {
                $data[$row_id][$field[0]][] = $row->par_value;
            } else {
                $data[$row_id][$field[0]] = $row->par_value;
            }
        }
        
        return $data;
     }
}