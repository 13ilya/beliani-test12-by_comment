<?php
//require_once 'PEAR.php';
//require_once 'util.php';
//require_once 'lib/Auction.php';
//require_once 'lib/Offer.php';
//require_once 'lib/Article.php';
//require_once 'lib/SellerInfo.php';
//require_once 'lib/Saved.php';
//require_once 'lib/PaymentMethod.php';

/**
 * Class Shop_Catalogue
 *
 * options_bit 1 bit - login by masterpass, 2 bit - enable sandbox safepay in login
 */
class Shop_Catalogue
{
	public $is_homepage;
	public $mobile = false;
    var $data;
    /**
     * @var \MDB2_Driver_mysql
     */
    var $_db;
    /**
     * @var \MDB2_Driver_mysql
     */
    var $_dbr;
    var $_error;
    var $_shop;
    var $_seller;
	var $_config_api_par;
	var $currency_code;
	var $main_currency;
	var $currencies = array();
	public $currencies_disabled = 0;
	var $_fake_free_shipping;
	var $_item_language;
	var $_cat_language;
    
    private static $offers_cache = null;
    
    private $_alias = '';
    
    /**
     * Flag - is preview
     * @var bool
     */
    private $isPreview = false;
    
    /**
     * @var string http|https
     */
    public $http;
    /**
     * @var string domain used in static condent
     */
    public $cdn_domain;
    var $_mobile_show_subtitles_in_article_list;    // options for mobile blocks
	var $_tpls = [
        "_shop_banner_gallery",
        "_dynamic_arrivals",
        "_shop_offers",
        "_shop_offer_question",
        "_shop_offers_live",
        "_shop_offers_live_mobile",
        "shop_overall_rating",
        "shop_cart",
        "shop_pics_left",
        "shop_pics_right",
        "shop_news",
        "shop_left_menu",
        "shop_offers",
        "shop_header",
        "shop_reg_login",
        "shop_reg_login_log",
        "shop_reg_login_reg",
        "shop_main",
        "shop_recommend",
        "shop_static_content",
        "shop_passreco",
        "shop_static_content_news",
        "shop_offer",
        "shop_order4_wait",
        "shop_order4",
        "shop_rating_thanks",
        "shop_rating",
        "shop_person",
        "shop_login",
        "shop_cabinet",
        "shop_looks",
        "person_orders",
        "shop_offer_details",
        "shop_order1",
        "shop_order2",
        "shop_nomenu",
        "shop_main",
        "shop_promo",
        "shop_service",
        "data_security_explanation",
        "_shop_offer_rating",
        "_shop_offers_cats",
        "_shop_header_topmenu",
        "_shop_voucher_free_sas",
        "shop_page",
        // cache
        "_dynamic_footer_voucher_mobile",
        "_dynamic_footer_voucher",
        "_dynamic_header_right",
        "_dynamic_last_visited",
        "_dynamic_last_offers",
        "_dynamic_question_block",
        "_dynamic_popup_block",
        "_dynamic_service_bonus_tab",
        "_dynamic_offer_availability",
        "_dynamic_offer_availability_mobile",
        "_dynamic_offer_expecteddelivery",
        "_dynamic_offer_expecteddelivery_mobile",
        //Preview grid
        "_offer_description_grid",
        "_product_teaser",
        "grid_elements", 
        // cache
        "_shop_filters",
        "_shop_offer_rating_short",
        "_shop_image",
        "_shop_image_primary",
        "_shop_image_simple",
        "shop_faq",
        "shop_cart_center",
        "shop_wish_center",
        "_shop_offer",
        "_shop_offers_voucher",
        "_shop_faq_dd",
        "_shop_content_sr",
        "_shop_ambs",
        "_shop_offer_sims",
        "_shop_ask4email",
        "_shop_wish_offers",
        "_shop_posts",
        "partials/craft",
        // mobile
        "mobile_js_code",
        "_mobile_header",
        "_mobile_footer",
        "_mobile_homepage",
        "_mobile_login",
        "_mobile_left_menu",
        "_mobile_userform",
        "_mobile_cats",
        "_shop_header_banner",
        "_banner_discount_top",
    ];
    
	var $_mulang_fields = [
        'main_bottom_text'
        ,'url_prefix'
        ,'title'
        ,'title_prefix'
        ,'title_suffix'
        ,'not_found'
        ,'left_block_text1'
        ,'left_block_text2'
        ,'right_block_text1'
        ,'right_block_text2'
        ,'unsubscribe_message_html'
        ,'unsubscribe_thanks_html'
        ,'homepage_title'
#			,'warranty_html'
#			,'delivery_html'
        ,'product_page_bottom_text'
        ,'RightOfReturnText'
        ,'RightOfReturnURL'
        ,'TS_ShopID'
        ,'overall_rating_bottom_text'
        ,'checkout_bottom_text'
        ,'homepage_keywords'
        ,'homepage_description'
        ,'ambassador_text'
        ,'ambassador_text_after_request'
        ,'ambassador_thanks_html'
        ,'ambassador_no_html'
        ,'ambassador_never_html'
        ,'cookie_warning_html'
        ,'slogan'
        ,'fb_share_text'
        ,'onstock_remonder_html'
        ,'vat_text'
        ,'birthday_popup_html'
        ,'trustpilot_popup_html'
        ,'good_rate_thanks_subject'
        ,'good_rate_thanks_html'
        ,'rating_remember_subject'
        ,'rating_remember_html'
        ,'header_banner_mobile_text'
        ,'header_banner_text_1'
        ,'header_banner_text_2'
        ,'mobile_overlay_text'
    ];

	/**
	 * Key-value storage to store routes for categories
	 * @var array
	 */
	private $routesCache = array();

	/**
	 * Key-value storage to store category names
	 * @var array
	 */
	private $categoryNameCache = array();

    function Shop_Catalogue($db, $dbr, $id=0, $lang='')
    {
		global $debug;
		$time = getmicrotime();
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('db Shop_Catalogue::Shop_Catalogue expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        if (!is_a($dbr, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('dbr Shop_Catalogue::Shop_Catalogue expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
		if ($id) {
			$this->_shop = $dbr->getRow("select shop.*
				, si.default_lang sellerInfo_default_lang
				from shop
				join seller_information si on si.username=shop.username
				where shop.id=$id");
		} else {
			$q = "select shop.*
				, si.default_lang sellerInfo_default_lang
				from shop
				join seller_information si on si.username=shop.username
				where upper('".$_SERVER['HTTP_HOST']."') in (upper(shop.url), upper(concat('www.',shop.url))
					,upper(shop.murl)
				    )
				    AND shop.inactive != 1
				";
			$this->_shop = $dbr->getRow($q);
		}
        if (!$this->_shop->id) {
            if (APPLICATION_ENV == 'docker') {
                $id = 1;
                $this->_shop = $dbr->getRow("select shop.*, si.default_lang sellerInfo_default_lang
				from shop
				join seller_information si on si.username=shop.username
				where shop.id=$id");
            } else {
                return 0;
            }
        }
		if ($this->_shop->mobile && isset($_COOKIE['off_mobile']) && !$_COOKIE['off_mobile'])
		{
			$this->mobile = true;
			$this->_shop->layout = '_mobile';
            $this->getMobileShowSubtitlesInArticleList();
		}
		if (
		    ($this->_shop->mobile)
		    && !empty($_SERVER['HTTP_HOST'])
		    && ($_SERVER['HTTP_HOST'] == $this->_shop->murl)
		) {
			$this->mobile = true;
			$this->_shop->layout = '_mobile';
            $this->getMobileShowSubtitlesInArticleList();
		}

		$this->_seller = new SellerInfo($db, $dbr, $this->_shop->username, $lang);
		if ($lang=='') $this->_shop->lang = $this->_shop->sellerInfo_default_lang;
			else $this->_shop->lang = $lang;
		$this->_shop->curr = Auction::getCurr($db, $dbr, $this->_shop->siteid);
		$this->_shop->curr_code = Auction::getCurrCode($db, $dbr, $this->_shop->siteid);
		$this->currency_code = $this->_shop->curr_code;
		$this->main_currency = $this->_shop->curr;
		$PHPSESSID = isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : '';
		$this->_shop->tracking_codes = $dbr->getAll("select shop_tracking_code.*, IFNULL(sts.id, 0) already_shown
			from shop_tracking_code
			left join shop_tracking_code_session sts on sts.shop_tracking_code_id=shop_tracking_code.id
				and sts.PHPSESSID='".mysql_real_escape_string($PHPSESSID)."'
		where shop_tracking_code.inactive=0 and shop_tracking_code.shop_id=".$this->_shop->id);

		// exclude shopgate script when mobile is on
		if ($this->_shop->mobile)
		{
			foreach ($this->_shop->tracking_codes as $key => $code)
			{
				if (mb_stripos($code->code, 'shopgate') !== false)
					$this->_shop->tracking_codes[$key]->_hide = true;
			}
		}

		$db->query("insert ignore into shop_tracking_code_session (shop_tracking_code_id, PHPSESSID)
			select id, '".mysql_real_escape_string($PHPSESSID)."'
			from shop_tracking_code
			where onetime=1 and inactive=0 and shop_id=".$this->_shop->id);
		$this->_config_api_par = $dbr->getAssoc("SELECT cap.id AS par_id, (
				SELECT MAX(ca.value)
				FROM config_api ca
				WHERE ca.par_id = cap.id
				AND ca.siteid = '".$this->_shop->siteid."'
				) AS par_value
				FROM config_api_par cap
				where cap.id<>6");
		foreach($this->_tpls as $tpl_name) {
			if (file_exists('templates/shop'.$this->_shop->layout.'/'.$tpl_name.'.tpl'))
				$this->_tpls[$tpl_name] = 'shop'.$this->_shop->layout.'/'.$tpl_name.'.tpl';
			else
				$this->_tpls[$tpl_name] = 'shop'.Config::get($this->_db, $this->_dbr, 'def_shop_template').'/'.$tpl_name.'.tpl';
		}
		$exts = explode('.', $this->_shop->onstock_icon_fn); $ext = end($exts);
		$this->_shop->onstock_icon_fn_ext = $ext;
		if ($this->_shop->lang!='none') {
			$this->translate($this->_shop->lang);
		} // if we need lang

		// get currencies list and set disable flag for currencies dropdown menu in case if no more than
		// main currency or no currencies in the list at all
		$this->currencies = $this->getCurrenciesArray();
		if(count($this->currencies) == 0 ||
			(count($this->currencies) == 1 && array_key_exists($this->currency_code, $this->currencies))) {
			$this->currencies_disabled = 1;
		}

		$this->defineCurrency();
		$this->_isHomePage();
        
        if ($this->_shop->ssl && APPLICATION_ENV != 'docker') {
            $this->http = 'https';
        } else {
            $this->http = 'http';
        }

        if ($this->_shop->cdn) {
            $this->cdn_domain = $this->http . '://' . $this->_shop->url;
        } else {
            $this->cdn_domain = '';
        }        

    }

    /**
     * Get $this->_shop param
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (property_exists($this, $name))
        {
            return $this->$name;
        }
        else if (isset($this->_shop->$name))
        {
            return $this->_shop->$name;
        }
        
        return null;
    }
    
    /**
     * Set $this->_shop param
     * 
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        if (isset($this->_shop->$name)) {
            $this->_shop->$name = $value;
        }
    }
    
	/**
	 * Get currency list to display dropdown for show offer currency choice.
	 *
	 * @author Alexander Verba
	 * @return array list of available currencies for shop ID
	 */
	public function getCurrenciesArray() {
		return $this->_dbr->getAssoc("select a.curr_code, b.description
        from
          shop_show_currencies a
        right join
          config_api_values b on a.curr_code=b.value
        where
          b.inactive=0 and b.par_id=7 and a.shop_id=" . $this->_shop->id . " order by b.ordering;");
	}

	/**
	 * Convert ShopPrice/ShopHPrice using rates for current (source rate) to new rate
	 *
	 * @author Alexander Verba
	 * @param $item
	 * @param $src_rate
	 * @param $dst_rate
	 * @return mixed
	 */
	private function _convert_prices(&$item, $src_rate, $dst_rate) {
		$fields = array('ShopPrice', 'ShopHPrice', 'shipping_cost');
		foreach($fields as $next_filed) {
			if(!isset($item->$next_filed)) continue;
			$item->$next_filed = round(
				(((float)$item->$next_filed * $src_rate) * (1 / $dst_rate)),
				0
			);
		}
		return $item;
	}

	/**
	 * Get currency name by currency code
	 *
	 * @author Alexander Verba
	 * @param $code
	 * @return mixed
	 */
	public function get_currency_name_by_code($code) {
		return $this->_dbr->getOne("select description from config_api_values where par_id=7 and value='$code'");
	}

	/**
	 * Convert offer prices on the article/catgory pages by selected currency
	 * (could by array or just stdClass with ShopPrice/ShopHPrice)
	 *
	 * @author Alexander Verba
	 * @param $offers
	 * @return mixed
	 */
	public function convertPrices($offers) {
		$currency_code = $this->currency_code;
		$currency_code_src = $this->_shop->curr_code;
		$this->_shop->curr = $this->get_currency_name_by_code($currency_code);
		if($currency_code == $currency_code_src) {
			return $offers;
		}
		$all_rates = getRates();
		$ratecode_dst = $currency_code . 'US';
		$ratecode_src = $currency_code_src . 'US';
		$rate_values['src'] = 1 / (float)$all_rates[$ratecode_src];
		$rate_values['dst'] = (float)$all_rates[$ratecode_dst];
		if(isset($offers->ShopPrice)) {
			return $this->_convert_prices($offers, $rate_values['src'], $rate_values['dst']);
		}
		array_walk($offers, function(&$val, $key, $rate_values) {
			$this->_convert_prices($val, $rate_values['src'], $rate_values['dst']);
		}, $rate_values);

		return $offers;
	}

	private function defineCurrency()
	{
		// reset customer selected currency when enter in cart
		if (preg_match('/(checkout_control_data.php)|(\/cart)/', $_SERVER['REQUEST_URI']))
		{
			$this->currencies_disabled = 1;
			setcookie('currency_code', '', 0, '/');
		}
		// set currency code from session if user was selected some currency
		if(!empty($_COOKIE["currency_code"]))
			$this->currency_code = $_COOKIE['currency_code'];
	}

	private function _isHomePage()
	{
		extract($_REQUEST);
		if (
			!$id
			&& !isset($search)
			&& !isset($voucher)
			&& !isset($show_customer)
			&& !isset($cabinet)
			&& !isset($shop_looks)
            && !isset($person_orders)
			&& !isset($shop_offer)
			&& !isset($checkout)
			&& !isset($content)
			&& !isset($service_id)
			&& !isset($news)
			&& !isset($register)
			&& !isset($passreco)
			&& !isset($wish)
            && !isset($cwish)
			&& !isset($item)
			&& !isset($cat)
			&& !isset($cart_str)
			&& !isset($order)
			&& !isset($rating)
			&& !isset($overall_rating)
			&& !isset($order_wait)
		) {
			$this->is_homepage = true;
		} else {
			$this->is_homepage = false;
		}
	}

	function isProductPage()
	{
		extract($_REQUEST);
		return isset($item);
	}
    /**
     * Check is page is a wishlist page
     * @return bool
     */
	function isWishlistPage()
	{
		extract($_REQUEST);
		return (isset($wish) || isset($cwish));
	}

    function translate($lang) {
        $this->_shop->lang = $lang;
        $this->_seller = new SellerInfo($this->_db, $this->_dbr, $this->_shop->username, $lang);

        $english = Auction::getTranslation($this->_db, $this->_dbr, $this->_shop->siteid, $this->_shop->lang);
        $this->_shop->english = array_map('htmlspecialchars', $english);
        
        $english_shop = Auction::getTranslationShop($this->_db, $this->_dbr, $this->_shop->siteid, $this->_shop->lang);
        $this->_shop->english_shop = $english_shop;
        
        switch ($lang) {
            case 'english':
                $this->_shop->locale = 'en_EN';
                break;
            case 'german':
                $this->_shop->locale = 'de_DE';
                break;
            case 'spanish':
                $this->_shop->locale = 'es_ES';
                break;
            case 'french':
                $this->_shop->locale = 'fr_FR';
                break;
            default:
                $this->_shop->locale = 'en_EN';
                break;
        }// switch
        
        $this->_shop->bonus_groups = $this->listBonusGroups($this->_shop->lang, $this->_seller->data->ebaycountry);
        
        $mulang_fields_value = $this->_dbr->getAssoc("SELECT field_name, `value`
                FROM translation
                WHERE table_name = 'shop'
                AND field_name in ('".implode("','",$this->_mulang_fields)."'
                )
                AND language = '".$this->_shop->lang."'
                AND id =".$this->_shop->id);
        foreach($this->_mulang_fields as $rec) {
            $this->_shop->$rec = substitute($mulang_fields_value[$rec], $this->_seller->data);
        }
        
        $this->_shop->warranty_html = $this->_dbr->getOne("SELECT `value`
                FROM translation
                WHERE table_name = 'shop_content'
                AND field_name = 'html'
                AND language = '".$this->_shop->lang."'
                AND id =".(int)$this->_shop->warranty_content_id);
        $this->_shop->delivery_html = $this->_dbr->getOne("SELECT `value`
                FROM translation
                WHERE table_name = 'shop_content'
                AND field_name = 'html'
                AND language = '".$this->_shop->lang."'
                AND id =".(int)$this->_shop->delivery_content_id);
        
        $this->_shop->payment_methods = $this->getPayments();
        
		$this->_fake_free_shipping = ($this->_seller->data->fake_free_shipping?
				"+IF(sa.ShopPrice>".(1*$this->_seller->data->fake_free_shipping_above)."
					, IF(IF(o.sshipping_plan_free, 1, IFNULL(tsshipping_plan_free_tr.value,0)), 0, spc.shipping_cost)
					, 0)":"");

        foreach($this->_shop->tracking_codes as $k=>$r) {
            $this->_shop->tracking_codes[$k]->code = substitute($this->_shop->tracking_codes[$k]->code, $this->_shop);
        }
	}

    function listAll($parent_id=0, $level=0, $hidden='(0,1)')
    {
		global $notassigned;
        
        if ( !is_array($parent_id))
        {
            $parent_id = [$parent_id];
        }
        
		$q = "SELECT sc.id, IFNULL(msc.id,sc.id) id4name, sc.master_shop_cat_id, msc.id master_cat_id,
			sc.parent_id,
			scs.offercount,
			sc.ordering,
			sc.date_limited,
			(sc.icon IS NOT NULL) as icon,
			sc.icon_inactive,
			sc.opened,
			scs.hidden,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'topmenu_name'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as topmenu_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'topbanner_name'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as topbanner_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'pagemenu_name'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as pagemenu_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'alt'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as alt,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as description
			, $level level
			, scs.id as shop_catalogue_shop_id
			, (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='topmenu' and scgs.shop_catalogue_id=sc.id) cats_topmenu
			, (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='topmenu' and scgs.shop_catalogue_id=sc.id) cats_topmenu_alt_title
			, (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='topbanner' and scgs.shop_catalogue_id=sc.id) cats_topbanner
			, (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='topbanner' and scgs.shop_catalogue_id=sc.id) cats_topbanner_alt_title
			, (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='pagemenu' and scgs.shop_catalogue_id=sc.id) cats_pagemenu
			, (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='pagemenu' and scgs.shop_catalogue_id=sc.id) cats_pagemenu_alt_title
            " . ($this->mobile ? ", scs.pic_color_mobile as pic_color" : ", scs.pic_color") . "
            " . ($this->mobile ? ", scs.cat_color_mobile as cat_color" : ", scs.cat_color") . "
			FROM shop_catalogue sc
			left join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			left join shop_catalogue_shop mscs on mscs.id=sc.master_shop_cat_id
			left join shop_catalogue msc on mscs.shop_catalogue_id=msc.id
			where (scs.shop_id=".$this->_shop->id." or (scs.shop_id is null  and '$notassigned'='1'))
			and sc.parent_id IN (" . implode(',', $parent_id) . ")
			and IFNULL(scs.hidden,1) in $hidden
			ORDER BY sc.ordering";
        $r = $this->_dbr->getAll($q);

        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		
        $res = [];

        global $allNodes_array;
		global $getall;
		global $id;
        
        $children_ids = array_map(function($v) {return (int)$v->id4name;}, $r);
        if ($children_ids)
        {
            $children = $this->listAll($children_ids, $level+1, $hidden);
        }
        
		foreach($r as $key=>$rec){
			$rec->level_nbsp = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $rec->level);
			//$children = $this->listAll($rec->id4name, $level+1, $hidden);
			//$rec->childcount = count($children);
            
//			if (($rec->opened && !$id) ||
//					in_array($rec->id, $allNodes_array)
//					|| $getall) {
//                
//				$rec->children = $children;
//			}
            
            $rec->childcount = 0;
            foreach ($children as $child)
            {
                if ($child->parent_id == $rec->id4name)
                {
                    $rec->childcount++;
                    if (($rec->opened && !$id) ||
                            in_array($rec->id, $allNodes_array)
                            || $getall) 
                    {
                        $rec->children[] = $child;
                    }
                }
            }
            
			$res[] = $rec;
		}
        return $res;
    }

    static function slistAll($db, $dbr, $parent_id=0, $level=0, $hidden='(0,1)')
    {
		$q = "SELECT sc.id, IFNULL(msc.id,sc.id) id4name, sc.master_shop_cat_id, msc.id master_cat_id,
			sc.parent_id,
			scs.offercount,
			sc.ordering,
			sc.date_limited,
			sc.icon_inactive,
			sc.opened,
			scs.hidden
			, $level level
			, scs.id as shop_catalogue_shop_id
			, (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='topmenu' and scgs.shop_catalogue_id=sc.id) cats_topmenu
			, (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='topmenu' and scgs.shop_catalogue_id=sc.id) cats_topmenu_alt_title
			, (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='topbanner' and scgs.shop_catalogue_id=sc.id) cats_topbanner
			, (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='topbanner' and scgs.shop_catalogue_id=sc.id) cats_topbanner_alt_title
			, (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='pagemenu' and scgs.shop_catalogue_id=sc.id) cats_pagemenu
			, (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='pagemenu' and scgs.shop_catalogue_id=sc.id) cats_pagemenu_alt_title
			FROM shop_catalogue sc
			left join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			left join shop_catalogue_shop mscs on mscs.id=sc.master_shop_cat_id
			left join shop_catalogue msc on mscs.shop_catalogue_id=msc.id
			where scs.shop_id is null
			and sc.parent_id=$parent_id
			ORDER BY sc.ordering";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		$res = array();
		global $ids2show;
		global $level1_id;
		global $allNodes_array;
		global $getall;
		global $id;
		foreach($r as $key=>$rec){
			$rec->level_nbsp = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $rec->level);
			$children = Shop_Catalogue::slistAll($db, $dbr, $rec->id4name, $level+1, $hidden);
			$rec->childcount = count($children);
			if (/*($level==0 && $level1_id==$rec->id) || ($level>0 && */
					($rec->opened && !$id) ||
					in_array($rec->id, $allNodes_array)
					|| $getall) {
				$rec->children = $children;
			}
			$res[] = $rec;
			$res = array_merge((array)$res/*, (array)$rec->children*/);
		}
        return $res;
    }

    function listArray()
    {
        $ret = array();
        $list = $this->listAll();
        foreach ((array)$list as $rec) {
            $ret[$rec->id] = $rec->id.str_repeat("	", $rec->level).$rec->name." (".$rec->offercount.")";
        }
        return $ret;
    }

    function listAllArray()
    {
        $ret = array();
        $list = $this->listAll();
        foreach ((array)$list as $rec) {
            $ret[$rec->id] = $rec->id.str_repeat("	", $rec->level).$rec->name." (".$rec->offercount.")";
        }
        return $ret;
    }

    function inSubTree($id, $root_id)
    {
//			die('Hallo');
        if ($id==$root_id) {
            return true;
        }
        $r = $this->_dbr->getAll("SELECT sc.id FROM shop_catalogue sc
			join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			where scs.shop_id=".$this->_shop->id."
			and sc.parent_id=$root_id ORDER BY sc.ordering");
		foreach($r as $key=>$rec){
			if ($this->inSubTree($id, $rec->id)) return true;
		}
        return false;
    }

    function getAllNodes($shop_catalogue_id, $notassigned_val = null)
	{
		if (PEAR::isError($shop_catalogue_id)) {
            print_r($shop_catalogue_id);
            die();
        }
		if (!$shop_catalogue_id) return 0;
		global $notassigned;
        if ( ! is_null($notassigned_val)) {
            $notassigned = $notassigned_val;
        }
        
		$function = "getAllNodes($shop_catalogue_id, $notassigned)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
		$q = "select sc.id, IFNULL(msc.id,sc.id) id4name, sc.master_shop_cat_id, msc.id master_cat_id,
			#IFNULL(parent_msc.id,parent_sc.id) parent_id,
			sc.parent_id,
			scs.offercount,
			sc.ordering,
			sc.opened,
			(sc.icon IS NOT NULL) as icon,
			sc.icon_inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as description
			from shop_catalogue sc
			join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			left join shop_catalogue_shop mscs on mscs.id=sc.master_shop_cat_id
			left join shop_catalogue msc on mscs.shop_catalogue_id=msc.id

			left join shop_catalogue parent_sc on sc.parent_id=parent_sc.id
			left join shop_catalogue_shop parent_scs on parent_scs.shop_catalogue_id=parent_sc.id
			left join shop_catalogue_shop parent_mscs on parent_mscs.id=parent_sc.master_shop_cat_id
			left join shop_catalogue parent_msc on parent_mscs.shop_catalogue_id=parent_msc.id
			where (scs.shop_id=".$this->_shop->id." or (scs.shop_id is null  and '$notassigned'='1'))
		and sc.id=$shop_catalogue_id limit 1";
        
//		if ($shop_catalogue_id==370) echo $q.'<br>';
		$cat = $this->_dbr->getRow($q);
		$res = array_merge((array)$cat->id,(array)$this->getAllNodes($cat->parent_id));
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $res);
		return $res;
	}

    function getRoute($shop_catalogue_id) {
		$res = '/';
		if (!$shop_catalogue_id) return $res;
		$function = "getRoute($shop_catalogue_id)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
		$route = $this->getAllNodesRecs($shop_catalogue_id);
			foreach($route as $cat) {
				$res .= $cat->alias.'/';
			}
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $res);
		return $res;
	}

    function getAllNodesRecs($shop_catalogue_id, $notassigned_val = null)
	{
		global $notassigned;
        if (!is_null($notassigned_val)) {
            $notassigned = $notassigned_val;
        }
        
		if (PEAR::isError($shop_catalogue_id)) {
            print_r($shop_catalogue_id);
            die();
        }
		if (!$shop_catalogue_id) return 0;
		$function = "getAllNodesRecs($shop_catalogue_id, $notassigned)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
		$q = "select sc.id, IFNULL(msc.id,sc.id) id4name, sc.master_shop_cat_id, msc.id master_cat_id,
			#IFNULL(parent_msc.id,parent_sc.id) parent_id,
			sc.parent_id,
			scs.offercount,
			sc.ordering,
			sc.opened,
			(sc.icon IS NOT NULL) as icon,
			sc.icon_inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_catalogue'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = IFNULL(msc.id,sc.id)) as description
			from shop_catalogue sc
			join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			left join shop_catalogue_shop mscs on mscs.id=sc.master_shop_cat_id
			left join shop_catalogue msc on mscs.shop_catalogue_id=msc.id

			left join shop_catalogue parent_sc on sc.parent_id=parent_sc.id
			left join shop_catalogue_shop parent_scs on parent_scs.shop_catalogue_id=parent_sc.id
			left join shop_catalogue_shop parent_mscs on parent_mscs.id=parent_sc.master_shop_cat_id
			left join shop_catalogue parent_msc on parent_mscs.shop_catalogue_id=parent_msc.id
			where (scs.shop_id=".$this->_shop->id." or (scs.shop_id is null  and '$notassigned'='1'))
		and sc.id=$shop_catalogue_id limit 1";
//		if ($shop_catalogue_id==370) echo $q.'<br>';
		$cat = $this->_dbr->getRow($q);
		if ($cat->parent_id) {
			$res = $this->getAllNodesRecs($cat->parent_id);
		} else {
			$res = [];
		}
		$res[] = $cat;
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $res);
		return $res;
	}

    static function sgetAllNodes($db, $dbr, $shop_catalogue_id)
	{
		if (!$shop_catalogue_id) return 0;
		$q = "select sc.id, IFNULL(msc.id,sc.id) id4name, sc.master_shop_cat_id, msc.id master_cat_id,
			#IFNULL(parent_msc.id,parent_sc.id) parent_id,
			sc.parent_id,
			scs.offercount,
			sc.ordering,
			sc.opened
			from shop_catalogue sc
			left join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			left join shop_catalogue_shop mscs on mscs.id=sc.master_shop_cat_id
			left join shop_catalogue msc on mscs.shop_catalogue_id=msc.id

			left join shop_catalogue parent_sc on sc.parent_id=parent_sc.id
			left join shop_catalogue_shop parent_scs on parent_scs.shop_catalogue_id=parent_sc.id
			left join shop_catalogue_shop parent_mscs on parent_mscs.id=parent_sc.master_shop_cat_id
			left join shop_catalogue parent_msc on parent_mscs.shop_catalogue_id=parent_msc.id
			where scs.shop_id is null
		and sc.id=$shop_catalogue_id";
		$cat = $dbr->getRow($q);
		return array_merge((array)$cat->id,(array)Shop_Catalogue::sgetAllNodes($cat->parent_id));
	}

    function listAllLeftMenu($shop_catalogue_id, $parent_master_cat_id=0, $hidden='(0,1)', $showall=0)
    {
		$function = "listAllLeftMenu($shop_catalogue_id, $parent_master_cat_id, $hidden, $showall)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if (strlen($chached_ret)) {
			return $chached_ret;
		}
        $ret = array();
//		print_r($chached_ret);
		global $allNodes_array;
		global $notassigned;
		$allNodes_array = (array)$this->getAllNodes($shop_catalogue_id);
		$allNodes_array = array_merge($allNodes_array, array($parent_master_cat_id));
		$allNodes =  implode(',', $allNodes_array);
		global $getall;
		$getall = $showall;
        
		if (!$shop_catalogue_id) {
			if (!strlen($shop_catalogue_id)) 
            {
                $shop_catalogue_id=0;
            }
            
			$q = "select group_concat(sc.id separator ',') from shop_catalogue sc
				join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
				where  (scs.shop_id=".$this->_shop->id." or (scs.shop_id is null  and '$notassigned'='1'))
				and ((sc.opened and $shop_catalogue_id=0) or $showall)";

			$shop_catalogue_id=$this->_dbr->getOne($q);
			if (!strlen($shop_catalogue_id)) 
            {
                $shop_catalogue_id=0;
            }
		}
		$q = "select sc.id f1, sc.id f2 from shop_catalogue sc
			join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			where  (scs.shop_id=".$this->_shop->id." or (scs.shop_id is null  and '$notassigned'='1'))
			and sc.parent_id in
			($shop_catalogue_id, $allNodes)
			and IFNULL(scs.hidden,1) in $hidden
			";
#			echo $q.'<br>';
		global $ids2show;
		global $level1_id;
#		echo 'allNodes_array='; print_r($allNodes_array);echo '<br>';
		$ids2show = $this->_dbr->getAssoc($q);
		$level1_id = $shop_catalogue_id;
		if (PEAR::isError($ids2show)) {
            aprint_r($ids2show);
            return;
        }
        
        $list = $this->listAll(0, 0, $hidden);

        foreach ((array)$list as $rec) {
	        if (strlen($rec->alias)) 
            {
                $ret[] = $rec;
            }
        }
        
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        return $ret;
    }

    static function slistAllLeftMenu($db, $dbr, $shop_catalogue_id, $parent_master_cat_id=0, $hidden='(0,1)', $showall=0)
    {
        $ret = array();
//		print_r($chached_ret);
		global $allNodes_array;
		$allNodes_array = (array)Shop_Catalogue::sgetAllNodes($db, $dbr, $shop_catalogue_id);
		$allNodes_array = array_merge($allNodes_array, array($parent_master_cat_id));
		$allNodes =  implode(',', $allNodes_array);
		global $getall;
		$getall = $showall;
		if (!$shop_catalogue_id) {
			if (!strlen($shop_catalogue_id)) $shop_catalogue_id=0;
			$q = "select group_concat(sc.id separator ',') from shop_catalogue sc
				left join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
				where scs.shop_id is null";
#			echo $q.'<br>';
			$shop_catalogue_id=$dbr->getOne($q);
			if (!strlen($shop_catalogue_id)) $shop_catalogue_id=0;
		}
		$q = "select sc.id f1, sc.id f2 from shop_catalogue sc
			left join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			where scs.shop_id is null
			and sc.parent_id in
			($shop_catalogue_id, $allNodes)
			";
#			echo $q.'<br>';
		global $ids2show;
		global $level1_id;
#		echo 'allNodes_array='; print_r($allNodes_array);echo '<br>';
		$ids2show = $dbr->getAssoc($q);
		$level1_id = $shop_catalogue_id;
		if (PEAR::isError($ids2show)) {
            aprint_r($ids2show);
            return;
        }
        $list = Shop_Catalogue::slistAll($db, $dbr, 0, 0, $hidden);
        foreach ((array)$list as $rec) {
	        $ret[] = $rec;
        }
        return $ret;
    }

    function listArrayLeftMenu($shop_catalogue_id, $hidden='(0,1)', $showall=0, $add_ids=0)
    {
        $ret = array();
        $list = $this->listAllLeftMenu($shop_catalogue_id, 0, $hidden, $showall);
        foreach ((array)$list as $rec) {
	            $ret[$rec->id] = ($add_ids?$rec->id:'').' '.$rec->name;
				foreach($rec->children as $rec1) {
		            $ret[$rec1->id] = "&nbsp;&nbsp;&nbsp;&nbsp;".($add_ids?$rec1->id:'').' '.$rec1->name;
					foreach($rec1->children as $rec2) {
			            $ret[$rec2->id] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".($add_ids?$rec2->id:'').' '.$rec2->name;
						foreach($rec2->children as $rec3) {
				            $ret[$rec3->id] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".($add_ids?$rec3->id:'').' '.$rec3->name;
							foreach($rec3->children as $rec4) {
					            $ret[$rec4->id] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".($add_ids?$rec4->id:'').' '.$rec4->name;
							}
						}
					}
				}
        }
        return $ret;
    }

    function getValues($name_id, $shop_catalogue_id, $sa_ids) {
        $params = implode(chr(0), [$name_id, $shop_catalogue_id, $sa_ids]);
		$function = "getValues($params)_b";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		$lang = $this->_shop->lang;
		if ($chached_ret) {
			return $chached_ret;
		}
		$name = $this->_dbr->getRow("select * from Shop_Names where id=$name_id");
			if ($name->SelectionMode=='Range') {
				$q = "select distinct sv.id, sv.ValueDec
						from Shop_Values sv
						join Shop_Value_Cat svc on svc.ValueID=sv.id
						where sv.NameID=$name->id and svc.shop_id=".$this->_shop->id."
							and svc.shop_catalogue_id=$shop_catalogue_id
					and sv.inactive=0
					and sv.ValueDec between ".$name->MinValue." and ".$name->MaxValue."
					order by sv.ordering";
				$ret = $this->_dbr->getAssoc($q);
			}
			if ($name->SelectionMode=='Dropdown') {
				if ($name->translatable) {
					$q = "select distinct sv.id, t.value ValueText
						from Shop_Values sv
						join Shop_Value_Cat svc on svc.ValueID=sv.id
						left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
							and t.language='$lang' and t.id=sv.id
						where sv.NameID=$name->id and svc.shop_id=".$this->_shop->id."
							and svc.shop_catalogue_id=$shop_catalogue_id
					and sv.inactive=0
					order by sv.ordering";
				} else {
					$q = "select distinct sv.id, ".($name->ValueType=='dec'?'sv.ValueDec':'sv.ValueText')."
						from Shop_Values sv
						join Shop_Value_Cat svc on svc.ValueID=sv.id
						where sv.NameID=$name->id and svc.shop_id=".$this->_shop->id."
							and svc.shop_catalogue_id=$shop_catalogue_id
					and sv.inactive=0
					order by sv.ordering";
				}
			} elseif ($name->SelectionMode=='Multicheck') {
				if ($name->translatable) {
					$q = "select distinct sv.id, t.value ValueText
						from Shop_Values sv
						join Shop_Value_Cat svc on svc.ValueID=sv.id
						join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
							and t.language='$lang' and t.id=sv.id
						where sv.NameID=$name->id and svc.shop_id=".$this->_shop->id."
							and svc.shop_catalogue_id=$shop_catalogue_id
					and sv.inactive=0 and t.value<>''
					order by sv.ordering";
				} else {
					$q = "select distinct sv.*
						from Shop_Values sv
						join Shop_Value_Cat svc on svc.ValueID=sv.id
						where sv.NameID=$name->id and svc.shop_id=".$this->_shop->id."
							and svc.shop_catalogue_id=$shop_catalogue_id
					and sv.inactive=0
					order by sv.ordering";
				}
//				echo 'Values: '.$q.'<br>';
				$ret = $this->_dbr->getAll($q);
				foreach($ret as $kvv=>$vvv) {
					$ret[$kvv]->sa_cat = $this->_dbr->getOne("select spv.saved_id from sa".$this->_shop->id." sa
							left join sa_all master_sa on sa.master_sa=master_sa.id
							left join saved_parvalues spv on IFNULL(master_sa.id, sa.id)=spv.saved_id
							where spv.ValueID={$vvv->id} and sa.shop_catalogue_id=$shop_catalogue_id limit 1");
					$ret[$kvv]->sa = $this->_dbr->getOne("select spv.saved_id from sa".$this->_shop->id." sa
							left join sa_all master_sa on sa.master_sa=master_sa.id
							left join saved_parvalues spv on IFNULL(master_sa.id, sa.id)=spv.saved_id
							where spv.ValueID={$vvv->id} and sa.shop_catalogue_id=$shop_catalogue_id
							".(isset($sa_ids)?" and sa.id in (".implode(',',$sa_ids).")":'')."
							limit 1");
				}
			}
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
		return $ret;
	}

    function getNames($shop_catalogue_id, $block='') {
		$function = "getNames($shop_catalogue_id, $block)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
		$q = "select t_title.value title, sn.*
				, t_measur.value mchar
				, t_descr.value description
				, max(snc.show_on_top) show_on_top
			from Shop_Name_Cat snc
			join Shop_Names sn on snc.NameID=sn.id
			left join Shop_Name_Shop sns on sns.NameID=sn.id and sns.shop_id=".$this->_shop->id."
			join translation t_title on t_title.table_name='Shop_Names' and t_title.field_name='title'
				and t_title.id=sn.id and t_title.language='".$this->_shop->lang."'
			left join translation t_descr on t_descr.table_name='Shop_Names' and t_descr.field_name='description'
				and t_descr.id=sn.id and t_descr.language='".$this->_shop->lang."'
			left join translation t_measur on t_measur.table_name='Shop_Names' and t_measur.field_name='measur'
				and t_measur.id=sn.id and t_measur.language='".$this->_shop->lang."'
			where snc.shop_id=".$this->_shop->id." and snc.shop_catalogue_id=$shop_catalogue_id
			and IFNULL(sns.inactive, sn.inactive)=0
			".(strlen($block)?"sns.{$block}=1":'')."
			group by sn.id
			order by IFNULL(sns.ordering, sn.ordering)";
//		echo "$q<br>";
		$ret = $this->_dbr->getAll($q);
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
		return $ret;
	}

	function processNames($id, $names, $values_array, $sa_ids) {
		$dbr = $this->_dbr;
		foreach($names as $k=>$name) {
			if ($name->SelectionMode=='Range') {
				if (isset($values_array)) {
					if (is_array($values_array[$name->id])) {
						$names[$k]->value0 = $values_array[$name->id][0];
						$names[$k]->value1 = $values_array[$name->id][1];
					} else {
						list($names[$k]->value0, $names[$k]->value1) = explode(' - ', $values_array[$name->id]);
					}
				}
				if (strpos($name->def_value,']]')) {
					$def_value = str_replace('[[','',str_replace(']]','',$name->def_value));
					$q = "select min(1*par_value) min_value, max(1*par_value) max_value
						from saved_params sp
						join sa".$this->_shop->id." sa on sa.id=sp.saved_id
						where par_key='$def_value'
						and sa.shop_catalogue_id=$id";
				} else {
					$q = "select min(1*IFNULL(sv.ValueDec,spv.FreeValueDec)) min_value
						, max(1*IFNULL(sv.ValueDec,spv.FreeValueDec)) max_value
						from saved_parvalues spv
						left join Shop_Values sv on sv.id=spv.ValueID
						left join sa".$this->_shop->id." sa on sa.id=spv.saved_id
						where 1
						and sa.shop_catalogue_id=$id
						and spv.NameID=$name->id";
				}
				$va = $dbr->getRow($q);
#				echo "$q<br>";
#				echo 'Values for range '.$name->id.': '.$va->min_value.'-'.$va->max_value.'<br>';
				$names[$k]->MinValue = $va->min_value;
				$names[$k]->MaxValue = $va->max_value;
				$values = $this->getValues($name->id, $id, $sa_ids);
				$names[$k]->values = array();
				foreach($values as $vid=>$value) {
					if (!count($names[$k]->values)) $from = $names[$k]->MinValue; else $from = $to;
					if ($value > $names[$k]->MaxValue) { $to=$names[$k]->MaxValue; $nomore = 1;} else $to = $value;
					$names[$k]->values["$from - $to"] = "$from ".$names[$k]->mchar." - $to ".$names[$k]->mchar;
				}
				if (!$nomore) {
					$from = $to;
					$to=$names[$k]->MaxValue;
					$names[$k]->values["$from - $to"] = "$from ".$names[$k]->mchar." - $to ".$names[$k]->mchar;
				}
			}
			if ($name->SelectionMode=='Dropdown') {
				$names[$k]->values = $this->getValues($name->id, $id, $sa_ids);
				$names[$k]->selected_values = $values_array[$name->id];
			} elseif ($name->SelectionMode=='Multicheck') {
				$names[$k]->values = $this->getValues($name->id, $id, $sa_ids);
				if (is_array($values_array[$name->id])) {
					$names[$k]->selected_values = array_fill_keys($values_array[$name->id], 1);
				} else {
					$names[$k]->selected_values = array_fill_keys(array($values_array[$name->id]), 1);
				}
			}
			unset($names[$k]->selected_values['']);
		} // foreach $names
	}
    
    /**
     * Prepare getOffersByPars query
     * @return String
     */
    private function _getOffersByParsSelectQuery() {
		return "
			select /*getOffersByPars*/ distinct sa.mi_id, sa.id, o.offer_id, sa.id as saved_id, sa.id orig_id
            , IFNULL(master_sa.id, sa.id) master_sa
			, IFNULL(sa.master_ShopSAAlias,1) master_ShopSAAlias
			, IFNULL(sa.master_ShopDesription,1) master_ShopDesription
			, IFNULL(sa.master_descriptionShop,1) master_descriptionShop
			, IFNULL(sa.master_descriptionTextShop,1) master_descriptionTextShop
			, IFNULL(sa.master_pics,1) master_pics
			, IFNULL(sa.master_banner,1) master_banner
			, IFNULL(sa.master_sims,1) master_sims
			, IFNULL(sa.master_others,1) master_others
			, max(sa.shop_catalogue_id) cat_id
			, alias.name ShopDescription
			, sa.ShopHPrice $this->_fake_free_shipping as ShopHPrice
			, sa.ShopMinusPercent
			, sa.ShopShortDescription
			, sa.ShopPrice $this->_fake_free_shipping as ShopPrice
			, alias.name alias

			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='alt' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) alt
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='title' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) title
			, o.name offer_name
			, IFNULL(tShopSAAlias.value, tShopSAAlias_default.value) ShopSAAlias
			, orig_o.offer_id orig_offer_id
			, orig_o.available
			, orig_o.available_weeks
			, orig_o.available_date
			, fget_avg_rating(sa.id) rating
			, (SELECT group_concat(concat(al.article_id,':',al.default_quantity,':',a.weight_per_single_unit))
				FROM article_list al
				JOIN offer_group og ON al.group_id = og.offer_group_id and base_group_id=0
				join article a on a.article_id=al.article_id and a.admin_id=0
				WHERE og.offer_id = o.offer_id and al.inactive=0 and og.additional=0) als
			from sa".$this->_shop->id." sa
			left join sa_all master_sa on sa.master_sa=master_sa.id
			join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
			left join offer orig_o on orig_o.offer_id=sa.offer_id
			join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
				and t1.id=47 and t1.language='".$this->_shop->lang."'
			left join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
				and t2.id=(57-orig_o.available)  and t2.language='".$this->_shop->lang."'
			left join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa'
				and tShopDesription.field_name='ShopDesription'
				and tShopDesription.language = '".$this->_shop->lang."'
			left join translation tShopDesription_default on tShopDesription_default.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription_default.table_name='sa'
				and tShopDesription_default.field_name='ShopDesription'
				and tShopDesription_default.language = '".$this->_seller->data->default_lang."'
			join offer_name alias on IFNULL(tShopDesription.value, tShopDesription_default.value)=alias.id
			left join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopSAAlias.table_name='sa'
				and tShopSAAlias.field_name='ShopSAAlias'
				and tShopSAAlias.language = '".$this->_shop->lang."'
			left join translation tShopSAAlias_default on tShopSAAlias_default.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopSAAlias_default.table_name='sa'
				and tShopSAAlias_default.field_name='ShopSAAlias'
				and tShopSAAlias_default.language = '".$this->_seller->data->default_lang."'
			left join translation tsshipping_plan_free_tr
				on tsshipping_plan_free_tr.language=sa.siteid
				and tsshipping_plan_free_tr.id=orig_o.offer_id
				and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
			join translation tsshipping_plan_id
				on tsshipping_plan_id.language=sa.siteid
				and tsshipping_plan_id.id=orig_o.offer_id
				and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
			join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
			join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
			join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')";
    }
    
    /**
     * Prepare getOffersByPars query
     * 
     * @param int $shop_catalogue_id
     * @param string $where
     * @param string $ordering
     * @return string
     */
    private function _getOffersByParsQuery($shop_catalogue_id, $where, $ordering) {
		return $this->_getOffersByParsSelectQuery() . 
            "
                where 1 and tShopSAAlias.value != ''
                and o.hidden=0 and IFNULL(sa.old,0)=0
                and sa.shop_catalogue_id = $shop_catalogue_id
                $where
                group by sa.id
                order by $ordering";
    }
    
    /**
     * Prepare getOffersByPars query     * 
     * @param int $id
     * @return string
     */
    private function _getMainMultiOffersByParsQuery($id) {
		return $this->_getOffersByParsSelectQuery() . 
                "
                    where 1 and tShopSAAlias.value != ''
                    and o.hidden=0 and IFNULL(sa.old,0)=0
                    and IFNULL(master_sa.id, sa.id) = $id";
    }

    function getOffersByPars($names_array, $values_array, $shop_catalogue_id, $sort)
    {
		global $debug;
        
        global $debug_speed, $getMDB;
        $__debug_time = microtime(true);

        $function = "getOffersByPars($shop_catalogue_id, $sort, ".serialize($values_array).")";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
		if ($debug) {
			echo 'NAMES:'; print_r($names_array); echo '<br>';
			echo 'VALUES:'; print_r($values_array); echo '<br>';
		}
        

        foreach ($names_array as $name_id) {
			$name = $this->_dbr->getRow("select * from Shop_Names where id=$name_id");

			switch ($name->SelectionMode) {
				case 'Range':
					if (!is_array($values_array[$name_id])) {
						$values_array[$name_id] = explode(' - ',$values_array[$name_id]);
					}
					if (count($values_array[$name_id])<2) continue;
					if (!strlen($values_array[$name_id][0]) || !strlen($values_array[$name_id][1])) continue;
					if (strpos($name->def_value,']]')) {
						$def_value = str_replace('[[','',str_replace(']]','',$name->def_value));
						$where .= " and  /*IFNULL(master_sa.id,sa.id)*/ sa.id in (
							select saved_id
							from saved_params
							where par_key='$def_value'
							and par_value between ".$values_array[$name_id][0]." and ".$values_array[$name_id][1].")";
					} else {
						$where .= " and IFNULL(master_sa.id,sa.id) in (
							select spv.saved_id
							from saved_parvalues spv
							left join Shop_Values sv on sv.id=spv.ValueID
							where IFNULL(sv.ValueDec,spv.FreeValueDec) >= ".$values_array[$name_id][0]."
								and IFNULL(sv.ValueDec,spv.FreeValueDec) <= ".$values_array[$name_id][1]."
							and spv.NameID=$name_id)";
					}
					break;
				case 'Multicheck':
					if (!is_array($values_array[$name_id])) {
						if (strlen($values_array[$name_id])) $values_array[$name_id] = array($values_array[$name_id]);
						else unset($values_array[$name_id]);
					}
					foreach($values_array[$name_id] as $kkk=>$rrr) if (!strlen($rrr)) unset($values_array[$name_id][$kkk]);
					if (count($values_array[$name_id])) {
						foreach($values_array[$name_id] as $k=>$dummy) $values_array[$name_id][$k] = mysql_real_escape_string($values_array[$name_id][$k]);
						if ($name->ValueType=='img') {
                            $ids = $this->_dbr->getOne("select group_concat(concat(\"'\",spv.saved_id,\"'\"))
                                from saved_parvalues spv
								join Shop_Values sv on sv.id=spv.ValueID
								where sv.id in (".implode(",",$values_array[$name_id]).")
								and spv.NameID=$name_id ");

                            if ($ids) {
                                $where .= " and IFNULL(master_sa.id,sa.id) in ($ids)";
                            }

//							$where .= " and IFNULL(master_sa.id,sa.id) in (
//								select spv.saved_id
//								from saved_parvalues spv
//								join Shop_Values sv on sv.id=spv.ValueID
//								where sv.id in (".implode(",",$values_array[$name_id]).")
//								and spv.NameID=$name_id)";

						}
                        else if ($name->ValueType=='text') {

                            $ids = $this->_dbr->getOne("select group_concat(concat(\"'\",spv.saved_id,\"'\"))
                                from saved_parvalues spv
								left join Shop_Values sv on sv.id=spv.ValueID
								left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
									/*and t.language='".$this->_shop->lang."'*/ and t.id=sv.id and t.value<>''
								where IFNULL(t.value,IFNULL(sv.ValueText,spv.FreeValueText)) in ('".implode("','",$values_array[$name_id])."')
								and spv.NameID=$name_id ");

                            if ($ids) {
                                $where .= " and IFNULL(master_sa.id,sa.id) in ($ids)";
                            }

//							$where .= " and IFNULL(master_sa.id,sa.id) in (
//								select spv.saved_id
//								from saved_parvalues spv
//								left join Shop_Values sv on sv.id=spv.ValueID
//								left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
//									/*and t.language='".$this->_shop->lang."'*/ and t.id=sv.id and t.value<>''
//								where IFNULL(t.value,IFNULL(sv.ValueText,spv.FreeValueText)) in ('".implode("','",$values_array[$name_id])."')
//								and spv.NameID=$name_id)";
						}
					}
					break;
				case 'Dropdown':
					if (strlen($values_array[$name_id])) {
						if ($name->ValueType=='img') {
							$where .= " and IFNULL(master_sa.id,sa.id) in (
								select spv.saved_id
								from saved_parvalues spv
								where spv.ValueID = ".$values_array[$name_id]."
								and spv.NameID=$name_id)";
						} elseif ($name->ValueType=='text') {
							$where .= " and IFNULL(master_sa.id,sa.id) in (
								select spv.saved_id
								from saved_parvalues spv
								where spv.ValueID = '".$values_array[$name_id]."'
								and spv.NameID=$name_id)";
						} elseif ($name->ValueType=='dec') {
							$where .= " and IFNULL(master_sa.id,sa.id) in (
								select spv.saved_id
								from saved_parvalues spv
								where spv.ValueID =".$values_array[$name_id]."
								and spv.NameID=$name_id)";
						}
					}
					break;
			}
		}
        
		switch ($sort) {
			case 'rating':
				$ordering = "rating desc";
			break;
			case 'price':
				$ordering = "1*sa.ShopPrice";
			break;
			case '-price':
				$ordering = "1*sa.ShopPrice desc";
			break;
			default:
				$ordering = "IFNULL(sa.shopspos_cat*1,0)";
			break;
		}

		$query = $this->_getOffersByParsQuery($shop_catalogue_id, $where, $ordering);
		$ret = $this->_dbr->getAll($query);
        
       	if (PEAR::isError($ret)) {
			if ($debug) echo "<pre>$query</pre><br>";
            die('getOffersByPars error');
        }
        
        $routes = [];
                
		foreach ($ret as $k=>$r) {
            if ( ! $r->ShopSAAlias) {
                unset($ret[$k]);
                continue;
            }
            
            $is_multi = $this->_isMulti($r->master_sa);
            if ($is_multi !== null) {
                if ($is_multi > 0) {
                    if ( ! isset($multi_offers[$is_multi])) {
                        foreach ($ret as $_multi) {
                            if ($_multi->master_sa == $is_multi) {
                                $r = $ret[$k] = $multi_offers[$is_multi] = $_multi;
                                break;
                            }
                        }
                        
                        if ( ! isset($multi_offers[$is_multi])) {
                            $query = $this->_getMainMultiOffersByParsQuery($is_multi);
                            $_offer = $this->_dbr->getRow($query);
                            if ($_offer)
                            {
                                $r = $ret[$k] = $multi_offers[$is_multi] = $_offer;
                            }
                            else 
                            {
                                $is_multi = null;
                            }
                        }
                    } else {
                        unset($ret[$k]);
                        continue;
                    }
                } else {
                    if ( ! isset($multi_offers[$r->master_sa])) {
                        $multi_offers[$r->master_sa] = $r;
                    } else {
                        unset($ret[$k]);
                        continue;
                    }
                }
            }
            
            $ret[$k]->is_multi = $is_multi !== null;
            if ($ret[$k]->is_multi) {
                $ret[$k]->ShopDescription = $this->_getShopMultiDescription($r->master_sa);
                $price = $this->_getShopMultiPrice($r->master_sa);
                if ($price) {
                    $ret[$k]->ShopMinusPercent = $price->ShopMinusPercent;
                    $ret[$k]->ShopPrice = $price->ShopPrice;
                    $ret[$k]->ShopHPrice = $price->ShopHPrice;
                }
            }
            
//            $pic = \SavedPic::getPrimary($r->master_sa);
//
//            $ret[$k]->doc_id = isset($pic->doc_id) ? $pic->doc_id : 0;
//            $ret[$k]->wdoc_id = isset($pic->wdoc_id) ? $pic->wdoc_id : 0;
//            $ret[$k]->cdoc_id = isset($pic->cdoc_id) ? $pic->cdoc_id : 0;
//            
//            $ret[$k]->color_type = $this->getColorType($r->saved_id);

            // Disable SA if we have not main picture
//            if ( ! $ret[$k]->doc_id) {
//                unset($ret[$k]);
//                continue;
//            }

			$ret[$k]->rating_statistic = $this->getRating($r->saved_id);
			$ret[$k]->alt_def = str_replace('"',"'",$ret[$k]->ShopDescription);//.'_'.$ret[$k]->doc_id;
			$ret[$k]->title_def = str_replace('"',"'",$ret[$k]->ShopDescription).' '.$ret[$k]->doc_id;
            
            if ( ! isset($routes[$r->cat_id]))
            {
                $route = $this->getAllNodes($r->cat_id);
                $cat = (int)array_pop($route);
                $routes[$r->cat_id] = $this->_dbr->getOne("
                    SELECT `value`
                    FROM translation
                    WHERE table_name = 'shop_catalogue'
                    AND field_name = 'name'
                    AND language = '{$this->_shop->lang}'
                    AND id = ".$cat."
                ");
            }
            
            $ret[$k]->cat = $routes[$r->cat_id];
            
            if ($r->master_sims && $r->master_sa && $r->master_sa!=$r->orig_id) {
                $master_sims_ids[] = (int)$r->master_sa;
//				$sims = Shop_Catalogue::sgetSims($this->_dbr, $r->master_sa, 1, $this->_shop->username, 0);
			} else {
                $sims_ids[] = (int)$r->orig_id;
//				$sims = Shop_Catalogue::sgetSims($this->_dbr, $r->orig_id, 0, $this->_shop->username, 0);
			}
            
//            if ($r->master_sims && $r->master_sa && $r->master_sa != $r->orig_id) {
//                $sims = Shop_Catalogue::sgetSims($this->_dbr, $r->master_sa, 1, $this->_shop->username, 0);
//            } else {
//                $sims = Shop_Catalogue::sgetSims($this->_dbr, $r->orig_id, 0, $this->_shop->username, 0);
//            }
//            foreach ($sims as $kk => $sim) {
//                $sims[$kk] = $this->getOffer($sim->sim_saved_id);
//                
//                if (!$sims[$kk]->saved_id) {
//                    unset($sims[$kk]);
//                }
//            }
//            $ret[$k]->sims = $sims;
		}
        

        $master_sims = [];
        if ($master_sims_ids) 
        {
            $q = "select ss.saved_id as saved_id, sp.saved_id as sim_saved_id
                from saved_params sp
                join saved_sim ss on ss.saved_id IN (" . implode(',', $master_sims_ids) . ") and 1*sp.par_value=ss.sim_saved_id and ss.inactive=0
                join saved_params sp_username on sp.saved_id=sp_username.saved_id and sp_username.par_key='username'
                    and sp_username.par_value='{$this->_shop->username}'
                LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id=sp.saved_id and sp_auction_name.par_key = 'auction_name'
                join saved_auctions sa on sa.id=sp.saved_id and sa.inactive in (0) and sa.old in (0)
                where sp.par_key='master_sa' and sp.saved_id
                order by ss.ordering";
                    
            foreach ($this->_dbr->getAll($q) as $sim)
            {
                $master_sims[$sim->saved_id][] = $sim->sim_saved_id;
            }
        } 
        
        $offer_sims = [];
        if ($sims_ids)
        {
            $q = "select ss.saved_id
                , ss.sim_saved_id
                from saved_sim ss
                LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id = ss.sim_saved_id and sp_auction_name.par_key = 'auction_name'
                join saved_auctions sa on sa.id=ss.sim_saved_id and sa.old=0 and sa.inactive=0
                where ss.saved_id IN (" . implode(',', $sims_ids) . ") and ss.inactive=0 and ss.sim_saved_id
                order by ss.ordering";
            
            foreach ($this->_dbr->getAll($q) as $sim)
            {
                $offer_sims[$sim->saved_id][] = $sim->sim_saved_id;
            }
        }

        $sims_ids = [];
        foreach ($ret as $k => $r)
        {
            if ($r->master_sims && $r->master_sa && $r->master_sa!=$r->orig_id) 
            {
				$sims = $master_sims[$r->master_sa];
			} 
            else 
            {
                $sims = $offer_sims[$r->orig_id];
			}

			foreach($sims as $kk => $sim) {
				$sims[$kk] = $this->getOffer($sim);
                
				if (!$sims[$kk]->saved_id) {
                    unset($sims[$kk]);
                } else {
                    $sims_ids[] = $sims[$kk]->master_sa;
                }
			}

            $ret[$k]->sims = $sims;
        }
        
        $master_ids = array_map(function($v) {return (int)$v->master_sa;}, $ret);
        $saved_ids = array_map(function($v) {return (int)$v->saved_id;}, $ret);
        
        $master_ids = array_unique($master_ids);
        $saved_ids = array_unique($saved_ids);
        $sims_ids = array_unique($sims_ids);
        
        if ($saved_ids)
        {
            $stop_empty_warehouse = $this->_dbr->getAll("select `saved_id`, `par_value`
                from `saved_params` where `saved_id` IN (" . implode(',', $saved_ids) . ") 
                    and `par_key` like 'stop_empty_warehouse_shop%'");
            
            $stop_empty_warehouse_array = [];
            foreach ($stop_empty_warehouse as $saved_id => $_warehouse)
            {
                $stop_empty_warehouse_array[$saved_id][] = $_warehouse;
            }
            
            $color_types = $this->getColorType($master_ids);

            $pics = \SavedPic::getPrimary($master_ids, $this->_shop->lang);
            
            if ($this->_shop->master_banner) {
                $banners = $this->loadBanners($master_ids);
            } else {
                $banners = $this->loadBanners($saved_ids);
            }
            
            $sims_banners = $this->loadBanners($sims_ids);

            foreach ($ret as $k => $r)
            {
                $ret[$k]->path_prefix = $pics[$r->master_sa]->path_prefix;

                $ret[$k]->doc_id = isset($pics[$r->master_sa]->doc_id) ? $pics[$r->master_sa]->doc_id : 0;
                $ret[$k]->wdoc_id = isset($pics[$r->master_sa]->wdoc_id) ? $pics[$r->master_sa]->wdoc_id : 0;
                $ret[$k]->cdoc_id = isset($pics[$r->master_sa]->cdoc_id) ? $pics[$r->master_sa]->cdoc_id : 0;

                $ret[$k]->primary_pic_ext = ['color' => $pics[$r->master_sa]->ext_color, 'whitesh' => $pics[$r->master_sa]->ext_whitesh, 'whitenosh' => $pics[$r->master_sa]->ext_whitenosh];

                // Disable SA if we have not main picture
                if ( ! $ret[$k]->doc_id) {
                    unset($ret[$k]);
                    continue;
                }

                $ret[$k]->details = serialize(['stop_empty_warehouse_shop' => $stop_empty_warehouse_array[$r->saved_id]]);
                $ret[$k]->color_types = $color_types[$r->master_sa];
                
                if ($this->_shop->master_banner) 
                {
                    $ret[$k]->banner = isset($banners[$r->master_sa]) ? $banners[$r->master_sa] : false;
                }
                else 
                {
                    $ret[$k]->banner = isset($banners[$r->saved_id]) ? $banners[$r->saved_id] : false;
                }
                
                foreach ($ret[$k]->sims as $simk => $sim)
                {
                    $ret[$k]->sims[$simk]->banner = isset($sims_banners[$sim->master_sa]) ? $sims_banners[$sim->master_sa] : false;
                }
            }
        }
        
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        return $ret;
    }
    
    function _getMaxShopMinusPercent($cat_id) 
    {
        if ( !is_array($cat_id))
        {
            $cat_id = [$cat_id];
        }
        
        $function = "_getMaxShopMinusPercent(" . implode(',', $cat_id) . ")";
        $chached_ret = cacheGet($function, $this->_shop->id, '');
		if ($chached_ret) {
            return $chached_ret;
		}
        
        $maxShopMinusPercent = $this->_dbr->getAssoc("SELECT `shop_catalogue_id`, MAX(`ShopMinusPercent`*1) 
                FROM `sa" . $this->_shop->id . "` 
                WHERE `shop_catalogue_id` IN (" . implode(', ', $cat_id) . ")
                GROUP BY `shop_catalogue_id`");
        
        cacheSet($function, $this->_shop->id, '', $maxShopMinusPercent);
        return $maxShopMinusPercent;
    }

    function getBlock($id, $mobile = null) {
        $mobile = !is_null($mobile) ? $mobile : (int)$this->mobile;

        $function = "getBlock($id, $mobile)";
        $chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
            return $chached_ret;
		}

        $query = "select `scgs`.`group_code`, scs.shop_catalogue_id, sc.*, t_name.value cat_name
            " . ($this->mobile ? ", scs.pic_color_mobile as pic_color" : ", scs.pic_color") . "
            " . ($this->mobile ? ", scs.cat_color_mobile as cat_color" : ", scs.cat_color") . "
            , (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_catalogue'
                AND field_name = 'topmenu_name'
                AND language = '{$this->_shop->lang}'
                AND id = sc.id) as topmenu_name
            , (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_catalogue'
                AND field_name = 'topbanner_name'
                AND language = '{$this->_shop->lang}'
                AND id = sc.id) as topbanner_name
            , (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_catalogue'
                AND field_name = 'pagemenu_name'
                AND language = '{$this->_shop->lang}'
                AND id = sc.id) as pagemenu_name
            , (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_catalogue'
                AND field_name = 'smallmainpagemenu_name'
                AND language = '{$this->_shop->lang}'
                AND id = sc.id) as smallmainpagemenu_name
            , (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_catalogue'
                AND field_name = 'mainpagemenu_name'
                AND language = '{$this->_shop->lang}'
                AND id = sc.id) as mainpagemenu_name
            , (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='topmenu' and scgs.shop_catalogue_id=sc.id) cats_topmenu
            , (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='topmenu' and scgs.shop_catalogue_id=sc.id) cats_topmenu_alt_title
            , (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='topbanner' and scgs.shop_catalogue_id=sc.id) cats_topbanner
            , (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='topbanner' and scgs.shop_catalogue_id=sc.id) cats_topbanner_alt_title
            , (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='pagemenu' and scgs.shop_catalogue_id=sc.id) cats_pagemenu
            , (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='pagemenu' and scgs.shop_catalogue_id=sc.id) cats_pagemenu_alt_title
            , (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='mainpagemenu' and scgs.shop_catalogue_id=sc.id) cats_mainpagemenu
            , (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='mainpagemenu' and scgs.shop_catalogue_id=sc.id) cats_mainpagemenu_alt_title
            , (select count(*) from shop_catalogue_group_show scgs where scgs.group_code='smallmainpagemenu' and scgs.shop_catalogue_id=sc.id) cats_smallmainpagemenu
            , (select use_alt_title from shop_catalogue_group_show scgs where scgs.group_code='smallmainpagemenu' and scgs.shop_catalogue_id=sc.id) cats_smallmainpagemenu_alt_title

        FROM `shop_catalogue_group_show` `scgs`
        JOIN `shop_catalogue_shop` `scs` ON `scs`.`shop_catalogue_id` = `scgs`.`shop_catalogue_id`
        JOIN `shop_catalogue` `sc` ON `scs`.`shop_catalogue_id` = `sc`.`id`
        JOIN `shop_catalogue_group` `scg` ON `scg`.`code` = `scgs`.`group_code`
        JOIN `translation` `t_name` ON `t_name`.`table_name` = 'shop_catalogue' 
            AND `t_name`.`field_name` = 'name' 
            AND `t_name`.`language` = '{$this->_shop->lang}'
            AND `t_name`.`id` = `sc`.`id`

        WHERE `scs`.`shop_id` = " . $this->_shop->id . "
            AND `scg`.`inactive` = '0'
            AND NOT `scs`.`hidden`

        ORDER BY IFNULL(`scgs`.`ordering`, `sc`.`ordering`)
        ";

        $ret = $this->_dbr->getAll($query);
        if ( ! $ret)
        {
            return [];
        }
        
        $ids = [];
        $shop_catalogue_ids = [];
        foreach ($ret as $key => $row)
        {
            if ($row->group_code != 'mainpagemenu' && $row->group_code != 'mainpagemobile')
            {
                if ($row->parent_id != $id)
                {
                    unset($ret[$key]);
                    continue;
                }
            }
            
            $ret[$key]->cat_route = $this->getRoute($row->id);
            $ret[$key]->icon = strlen($row->icon);
            
            $ids[] = (int)$row->id;
            $shop_catalogue_ids[] = (int)$row->shop_catalogue_id;
        }
        
        $maxShopMinusPercent = $this->_getMaxShopMinusPercent(array_unique($shop_catalogue_ids));
        foreach ($ret as $key => $row)
        {
            if ($row->shop_catalogue_id == $shop_catalogue_id)
            {
                $ret[$key]->maxShopMinusPercent = $maxShopMinusPercent[$row->shop_catalogue_id];
            }
        }
        
        $query = "SELECT
                sc1.id sc1
                , sc2.id sc2
                , sc3.id sc3
                , sc4.id sc4
            FROM shop_catalogue sc1
            LEFT JOIN shop_catalogue sc2 on sc2.parent_id = sc1.id
            LEFT JOIN shop_catalogue sc3 on sc3.parent_id = sc2.id
            LEFT JOIN shop_catalogue sc4 on sc4.parent_id = sc3.id
            WHERE sc1.id IN (" . implode(',', array_unique($ids)) . ")";

        $res = $this->_dbr->getAll($query);

        $category_ids = [];
        $children_ids = [];
        foreach ($res as $row)
        {
            if (!isset($category_ids[(int)$row->sc1]))
            {
                $category_ids[(int)$row->sc1][] = (int)$row->sc1;
                $children_ids[] = (int)$row->sc1;
            }
            if ($row->sc2 && !in_array((int)$row->sc2, $category_ids[(int)$row->sc1]))
            {
                $category_ids[(int)$row->sc1][] = (int)$row->sc2;
                $children_ids[] = (int)$row->sc2;
            }
            if ($row->sc3 && !in_array((int)$row->sc3, $category_ids[(int)$row->sc1]))
            {
                $category_ids[(int)$row->sc1][] = (int)$row->sc3;
                $children_ids[] = (int)$row->sc3;
            }
            if ($row->sc4 && !in_array((int)$row->sc4, $category_ids[(int)$row->sc1]))
            {
                $category_ids[(int)$row->sc1][] = (int)$row->sc4;
                $children_ids[] = (int)$row->sc4;
            }
        }

        $children_values = $this->_dbr->getAssoc("SELECT `saved_params`.`par_value`, COUNT(`sa`.`id`)
                FROM `saved_params`
                LEFT JOIN `saved_auctions` `sa` ON `sa`.`id` = `saved_params`.saved_id
                LEFT JOIN `saved_params` `master_sa`
                    ON `master_sa`.`saved_id` = `sa`.`id`
                    AND `master_sa`.`par_key` = _utf8'master_sa'
                LEFT JOIN `saved_params` `sp_offer_id`
                    ON `sp_offer_id`.`saved_id` = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
                    AND `sp_offer_id`.`par_key` = 'offer_id'
                LEFT JOIN `offer` `o` ON `o`.`offer_id` = `sp_offer_id`.`par_value`
                WHERE `saved_params`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]'
                AND `saved_params`.`par_value` IN (" . implode(', ', $children_ids) . ")
                AND `sa`.`inactive` = 0
                AND `o`.`hidden` = 0
                GROUP BY `saved_params`.`par_value`");
        
        foreach ($category_ids as $category_id => $children_ids)
        {
            $category_ids[$category_id] = 0;
            foreach ($children_ids as $child_id)
            {
                $category_ids[$category_id] += (int)$children_values[$child_id];
            }
        }

        $result = [];
        foreach ($ret as $key => $row)
        {
            $row->offercount = $category_ids[$row->id];
            $result[$row->group_code][] = $row;
        }
        
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $result);
        return $result;
    }

    /**
     * 
     * Cheack SA by multi or not
     * 
     * @param int $saved_id
     * @return mixed
     */
    private function _isMulti($saved_id) {
        return $this->_dbr->getOne("SELECT `par_value` FROM `saved_params`
				WHERE `par_key` = 'master_multi' AND `saved_id` = ?", null, [$saved_id]);
    }
    
    /**
     * Get common description for multi SA
     * 
     * @param type $saved_id
     * @return type
     */
    private function _getShopMultiDescription($saved_id) {
        $description = $this->_dbr->getOne("SELECT `value` FROM `translation`
            WHERE `table_name` = 'sa' AND `field_name` = 'ShopMultiDesription'
                AND `id` = ? AND `language` = ?", null, [$saved_id, $this->_shop->lang]);
        
        if ( ! $description) {
            $description = $this->_dbr->getOne("SELECT `value` FROM `translation`
                WHERE `table_name` = 'sa' AND `field_name` = 'ShopMultiDesription'
                    AND `id` = ? AND `language` = ?", null, [$saved_id, $this->_seller->data->default_lang]);
        }
        
        return $description;
    }

    /**
     * Get minimum price for multi SA
     * 
     * @param int $saved_id
     * @return object
     */
    private function _getShopMultiPrice($saved_id) {
		$function = "_getShopMultiPrice($saved_id)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}

        $saved_ids = $this->_dbr->getOne("SELECT GROUP_CONCAT(`saved_id`) 
            FROM `saved_params` WHERE `par_key` = 'master_multi' AND `par_value` = ?", null, $saved_id);
        
        if ($saved_ids) {
            $saved_ids = explode(',', $saved_ids);
        } else {
            $saved_ids = [];
        }
        $saved_ids[] = $saved_id;
        $saved_ids = implode(',', $saved_ids);
        
        $price = $this->_dbr->getRow("SELECT `sa`.`ShopMinusPercent`, `sa`.`ShopPrice`, `sa`.`ShopHPrice`
        	FROM sa{$this->_shop->id} AS sa
            LEFT JOIN `sa_all` AS `master_sa` ON `sa`.`master_sa` = `master_sa`.`id`
            WHERE IFNULL(`master_sa`.`id`, `sa`.`id`) IN ($saved_ids)
            ORDER BY `sa`.`ShopPrice` * 1 ASC LIMIT 1");
            
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $price);
        return $price;
    }
    
    /**
     * Prepare query for getOffers
     * 
     * @param string $fakefreeshipping
     * @return string
     */
    private function _getOffersSelectQuery($fakefreeshipping) {
        return "
            select /*getOffers*/ distinct sa.mi_id, sa.id, o.offer_id, sa.id as saved_id, sa.id orig_id
                , IFNULL(master_sa.id, sa.id) master_sa
                , 0 as visits
                , shop_rating_cache.avg*100 rated
                , IFNULL(master_sa.id,sa.id) ifnullmastersa
                , IFNULL(sa.master_ShopSAAlias,1) master_ShopSAAlias
                , IFNULL(sa.master_ShopDesription,1) master_ShopDesription
                , IFNULL(sa.master_descriptionShop,1) master_descriptionShop
                , IFNULL(sa.master_descriptionTextShop,1) master_descriptionTextShop
                , IFNULL(sa.master_pics,1) master_pics
                , IFNULL(sa.master_banner,1) master_banner
                , IFNULL(sa.master_sims,1) master_sims
                , IFNULL(sa.master_others,1) master_others
                , IFNULL(sa.master_ShopSAKeywords,1) master_ShopSAKeywords
                , IFNULL(sa.master_ShopShortTitle,1) master_ShopShortTitle
                , IFNULL(sa.master_icons,1) master_icons
                , IFNULL(sa.master_amazon,1) master_amazon
                , max(sa.shop_catalogue_id) cat_id
                , alias.name ShopDescription
                , sa.ShopHPrice $fakefreeshipping as ShopHPrice
                , sa.ShopMinusPercent
                , sa.ShopShortDescription
                , sa.ShopPrice $fakefreeshipping as ShopPrice
                , (0 {$this->_fake_free_shipping}) as fake_free_shipping
                , alias.name alias
                , o.name offer_name
                , IFNULL(tShopSAAlias.value, tShopSAAlias_default.value) ShopSAAlias
                , IFNULL(tShopShortTitle.value, tShopShortTitle_default.value) ShopShortTitle
                , orig_o.offer_id orig_offer_id
                , orig_o.available
                , orig_o.available_weeks
                , orig_o.available_date
                , IF(orig_o.available
                    , '{$this->_shop->english_shop[214]}'
                    ,IF(orig_o.available_weeks
                        , CONCAT('{$this->_shop->english_shop[216]}', ' ', DATE(date_add(NOW(), INTERVAL orig_o.available_weeks week)))
                        , IF(orig_o.available_date='0000-00-00'
                            , '{$this->_shop->english_shop[215]}'
                            , CONCAT('{$this->_shop->english_shop[216]}',' ', orig_o.available_date)
                        )
                    )
                ) available_text
                #, sa.details
                , sa.ShopShippingCharge
                , o.sshipping_plan_free
                , sa.siteid
            from sa{$this->_shop->id} sa
            left join sa_all master_sa on sa.master_sa=master_sa.id
            join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
            left join offer orig_o on orig_o.offer_id=sa.offer_id
            join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
                and t1.id=47 and t1.language='{$this->_shop->lang}'
            left join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
                and t2.id=(57-orig_o.available)  and t2.language='{$this->_shop->lang}'
            left join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopDesription.table_name='sa'
                and tShopDesription.field_name='ShopDesription'
                and tShopDesription.language = '{$this->_shop->lang}'
            left join translation tShopDesription_default on tShopDesription_default.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopDesription_default.table_name='sa'
                and tShopDesription_default.field_name='ShopDesription'
                and tShopDesription_default.language = '{$this->_seller->data->default_lang}'
            join offer_name alias on IFNULL(tShopDesription.value, tShopDesription_default.value)=alias.id
            left join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopSAAlias.table_name='sa'
                and tShopSAAlias.field_name='ShopSAAlias'
                and tShopSAAlias.language = '{$this->_shop->lang}'
            left join translation tShopSAAlias_default on tShopSAAlias_default.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopSAAlias_default.table_name='sa'
                and tShopSAAlias_default.field_name='ShopSAAlias'
                and tShopSAAlias_default.language = '{$this->_seller->data->default_lang}'
            left join translation tShopShortTitle on tShopShortTitle.id=IF(IFNULL(sa.master_ShopShortTitle,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopShortTitle.table_name='sa'
                and tShopShortTitle.field_name='ShopShortTitle'
                and tShopShortTitle.language = '{$this->_shop->lang}'
            left join translation tShopShortTitle_default on tShopShortTitle_default.id=IF(IFNULL(sa.master_ShopShortTitle,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopShortTitle_default.table_name='sa'
                and tShopShortTitle_default.field_name='ShopShortTitle'
                and tShopShortTitle_default.language = '{$this->_seller->data->default_lang}'
            left join translation tsshipping_plan_free_tr
                on tsshipping_plan_free_tr.language=sa.siteid
                and tsshipping_plan_free_tr.id=orig_o.offer_id
                and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
            join translation tsshipping_plan_id
                on tsshipping_plan_id.language=sa.siteid
                and tsshipping_plan_id.id=orig_o.offer_id
                and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
            join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
            join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
            join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
            left join shop_rating_cache on shop_rating_cache.shop_id=1 and shop_rating_cache.saved_id=sa.id";            
    }
    
    /**
     * Prepare query for getOffers
     * 
     * @param string $fakefreeshipping
     * @param array $ids
     * @param string $ordering
     * @return string
     */
    private function _getOffersQuery($fakefreeshipping, $ids, $ordering) {
        return $this->_getOffersSelectQuery($fakefreeshipping) . 
            "
                where 1 and tShopSAAlias.value != ''
                and sa.username='{$this->_shop->username}'
                and sa.siteid='{$this->_shop->siteid}'
                and o.hidden=0 and IFNULL(sa.old,0)=0
                and sa.id>0
                and sa.shop_catalogue_id in (" . implode(',', $ids) . ")
                group by sa.id
                order by $ordering";
    }
    
    /**
     * Prepare query for getOffers
     * 
     * @param string $fakefreeshipping
     * @param array $ids
     * @param string $ordering
     * @return string
     */
    private function _getMainMultiOffersQuery($fakefreeshipping, $id) {
        return $this->_getOffersSelectQuery($fakefreeshipping) . 
                "
                    where 1 and tShopSAAlias.value != ''
                    and sa.username='{$this->_shop->username}'
                    and sa.siteid='{$this->_shop->siteid}'
                    and o.hidden=0 and IFNULL(sa.old,0)=0
                    and sa.id>0
                    and IFNULL(master_sa.id, sa.id) = $id
                    group by sa.id";
    }
    
    /**
     * 
     * Get list SA by catalogue ids
     * 
     * @global bool $debug
     * @global bool $all_products
     * @param int $shop_catalogue_id
     * @param string $sort
     * @param bool $cache
     * @param bool $fakefreeshipping
     * @param bool $shop_pic_color
     * @param bool $all_products_val
     * @return array
     */
    function getOffers($shop_catalogue_id, $sort='', $cache=1, $fakefreeshipping=true, $shop_pic_color = null, $all_products_val = null)
    {
		global $debug;
		global $all_products;
        global $debug_speed, $getMDB;
        $__debug_time = microtime(true);
        
        if ( ! is_null($all_products_val)) {
            $all_products = $all_products_val;
        }
        
		if (!$all_products) {
			$childs = $this->_dbr->getOne("select count(*)
                from shop_catalogue sc
                join shop_catalogue_shop scs on sc.id=scs.shop_catalogue_id
                where sc.hidden=0 and scs.hidden=0 and sc.parent_id=$shop_catalogue_id and scs.shop_id=".$this->_shop->id);
			if ($childs && $this->_shop->leafs_only) return;
		}

        $shop_pic_color = is_null($shop_pic_color) ? $shop_pic_color : $this->_shop->shop_pic_color;
        
        $params = implode(chr(0), [$shop_catalogue_id, $sort, 1, $fakefreeshipping, $shop_pic_color, $all_products]);
		$function = "getOffers($params)_b";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($cache && $chached_ret) {
			return $chached_ret;
		}
        
		$ids = array();
		$ids[] = $shop_catalogue_id;
		if ($all_products) 
        {
            foreach ($this->listAll($shop_catalogue_id) as $rec) 
            {
                $ids[]=$rec->id;
            }
        }

		switch ($sort) {
			case '-visits':
				$ordering = "visits desc";
			break;
			case 'price':
				$ordering = "1*sa.ShopPrice";
			break;
			case '-price':
				$ordering = "1*sa.ShopPrice desc";
			break;
			case 'bestrated':
				$ordering = "CAST(rated as decimal) desc";
			break;
			default:
				$ordering = "IFNULL(sa.shopspos_cat*1,999)";
			break;
		}
		
        $fakefreeshipping = $fakefreeshipping ? $this->_fake_free_shipping : '';
        
		$query = $this->_getOffersQuery($fakefreeshipping, $ids, $ordering);
		$ret = $this->_dbr->getAll($query);

       	if (PEAR::isError($ret)) {
            if ($debug) print_r($ret);
            die("/*getOffers*/ error");
        }
        
        if ($ret)
        {
            $sa_ids = array_map(function($v) {return (int)$v->master_sa;}, $ret);
            $add_rec_query = "
                    select

                    sa.id, 

                    IF(IF(0, 1, IFNULL(tsshipping_plan_free_tr.value,0)), 0, spc.shipping_cost) as shipping_cost

                , (select translation.value from translation
                        join saved_doc on saved_doc.doc_id=translation.id
                                where table_name='saved_doc' and field_name='alt' and translation.language='english'
                                and saved_doc.saved_id=sa.id order by saved_doc.primary desc limit 1) alt
                , (select translation.value from translation
                        join saved_doc on saved_doc.doc_id=translation.id
                                where table_name='saved_doc' and field_name='title' and translation.language='english'
                                and saved_doc.saved_id=sa.id order by saved_doc.primary desc limit 1) title
                , (SELECT group_concat(concat(al.article_id,':',al.default_quantity,':',a.weight_per_single_unit))
                    FROM article_list al
                    JOIN offer_group og ON al.group_id = og.offer_group_id and base_group_id=0
                    join article a on a.article_id=al.article_id and a.admin_id=0
                    WHERE og.offer_id = o.offer_id and al.inactive=0 and og.additional=0) als

                from sa1 sa
                left join sa_all master_sa on sa.master_sa=master_sa.id
                join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
                left join offer orig_o on orig_o.offer_id=sa.offer_id

                left join translation tsshipping_plan_free_tr
                    on tsshipping_plan_free_tr.language='{$this->_shop->siteid}'
                    and tsshipping_plan_free_tr.id=orig_o.offer_id
                    and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
                join translation tsshipping_plan_id
                    on tsshipping_plan_id.language='{$this->_shop->siteid}'
                    and tsshipping_plan_id.id=orig_o.offer_id
                    and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
                join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
                join config_api_values cav on cav.par_id=5 and cav.value='{$this->_shop->siteid}'
                join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
                where sa.id IN (" . implode(',', $sa_ids) . ")

                group by sa.id
            ";
            $add_rec_values = $this->_dbr->getAssoc($add_rec_query);
        }
        
        $last_cat_short_title = $this->_dbr->getOne("
            SELECT `value`
            FROM `translation`
            WHERE table_name = 'shop_catalogue'
                AND field_name = 'short_title'
                AND language = '{$this->_shop->lang}'
                AND id = ".$shop_catalogue_id."
        ");
                
        $routes = [];
        
        $master_sims_ids = [];
        $sims_ids = [];
        
        $multi_offers = [];
		foreach ($ret as $k=>$r) {
            if ( ! $r->ShopSAAlias) {
                unset($ret[$k]);
                continue;
            }
            
            $is_multi = null;
            if ($cache)
            {
                $is_multi = $this->_isMulti($r->master_sa);
            }
            
            if ($is_multi !== null) {
                if ($is_multi > 0) {
                    if ( ! isset($multi_offers[$is_multi])) {
                        foreach ($ret as $_multi) {
                            if ($_multi->master_sa == $is_multi) {
                                $r = $ret[$k] = $multi_offers[$is_multi] = $_multi;
                                break;
                            }
                        }
                        
                        if ( ! isset($multi_offers[$is_multi])) {
                            $query = $this->_getMainMultiOffersQuery($fakefreeshipping, $is_multi);
                            $_offer = $this->_dbr->getRow($query);
                            if ($_offer)
                            {
                                $r = $ret[$k] = $multi_offers[$is_multi] = $_offer;
                            }
                            else 
                            {
                                $is_multi = null;
                            }
                        }
                    } else {
                        unset($ret[$k]);
                        continue;
                    }
                } else {
                    if ( ! isset($multi_offers[$r->master_sa])) {
                        $multi_offers[$r->master_sa] = $r;
                    } else {
                        unset($ret[$k]);
                        continue;
                    }
                }
            }
            
            $ret[$k]->is_multi = $is_multi !== null;
            if ($ret[$k]->is_multi) {
                $ret[$k]->ShopDescription = $this->_getShopMultiDescription($r->master_sa);
                //var_dump($ret[$k]->ShopDescription);
                $price = $this->_getShopMultiPrice($r->master_sa);
                if ($price) {
                    $ret[$k]->ShopMinusPercent = $price->ShopMinusPercent;
                    $ret[$k]->ShopPrice = $price->ShopPrice;
                    $ret[$k]->ShopHPrice = $price->ShopHPrice;
                }
            }
           
            if ( ! $r) {
                unset($ret[$k]);
                continue;
            }

            if (isset($add_rec_values[$r->master_sa]))
            {
                $add_rec = (object)$add_rec_values[$r->master_sa];
            }
            else 
            {
                $q = "
                    select IF(IF({$r->sshipping_plan_free}, 1, IFNULL(tsshipping_plan_free_tr.value,0)), 0, spc.shipping_cost) as shipping_cost

                , (select translation.value from translation
                        join saved_doc on saved_doc.doc_id=translation.id
                                where table_name='saved_doc' and field_name='alt' and translation.language='".$this->_shop->lang."'
                                and saved_doc.saved_id={$r->master_sa} order by saved_doc.primary desc limit 1) alt
                , (select translation.value from translation
                        join saved_doc on saved_doc.doc_id=translation.id
                                where table_name='saved_doc' and field_name='title' and translation.language='".$this->_shop->lang."'
                                and saved_doc.saved_id={$r->master_sa} order by saved_doc.primary desc limit 1) title
                , (SELECT group_concat(concat(al.article_id,':',al.default_quantity,':',a.weight_per_single_unit))
                    FROM article_list al
                    JOIN offer_group og ON al.group_id = og.offer_group_id and base_group_id=0
                    join article a on a.article_id=al.article_id and a.admin_id=0
                    WHERE og.offer_id = {$r->offer_id} and al.inactive=0 and og.additional=0) als
                from saved_auctions sa
                left join translation tsshipping_plan_free_tr
                    on tsshipping_plan_free_tr.language={$r->siteid}
                    and tsshipping_plan_free_tr.id={$r->orig_offer_id}
                    and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
                join translation tsshipping_plan_id
                    on tsshipping_plan_id.language={$r->siteid}
                    and tsshipping_plan_id.id={$r->orig_offer_id}
                    and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
                join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
                join config_api_values cav on cav.par_id=5 and cav.value={$r->siteid}
                join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
                where sa.id={$r->master_sa}
                ";
                $add_rec = $this->_dbr->getRow($q);
            }
            
            if (PEAR::isError($add_rec)) { print_r($add_rec); die();}
            
			$ret[$k]->alt = $add_rec->alt;
			$ret[$k]->title = $add_rec->title;
			$ret[$k]->als = $add_rec->als;
			$ret[$k]->shipping_cost = $add_rec->shipping_cost;

			// shipping_cost logic
			if ($this->_seller->get('free_shipping') || $this->_seller->get('free_shipping_total'))
			{
				$ret[$k]->shipping_cost = 0;
			}
			$ret[$k]->shipping_cost = $ret[$k]->shipping_cost - $ret[$k]->fake_free_shipping;
			// shipping_cost logic

			$ret[$k]->rating_statistic = $this->getRating($r->saved_id);
			$ret[$k]->alt_def = str_replace('"',"'",$ret[$k]->ShopDescription);//.'_'.$ret[$k]->doc_id;
			$ret[$k]->title_def = str_replace('"',"'",$ret[$k]->ShopDescription).' '.$ret[$k]->doc_id;
            
            /**
             * @todo get rid of this variable, replace it in every place to slash
             */
			$ret[$k]->cat_route = '/';
            /**
             * @todo rewrite this cycle, we need only the last element
             */
            
            if ( ! isset($routes[$r->cat_id]))
            {
                $route = $this->getAllNodes($r->cat_id);
                $cat = (int)array_pop($route);
                $routes[$r->cat_id] = $this->_dbr->getOne("
                    SELECT `value`
                    FROM translation
                    WHERE table_name = 'shop_catalogue'
                    AND field_name = 'name'
                    AND language = '{$this->_shop->lang}'
                    AND id = ".$cat."
                ");
            }
            
            $ret[$k]->cat = $routes[$r->cat_id];
            
			$ret[$k]->last_cat_short_title = $last_cat_short_title;

            if ($r->master_sims && $r->master_sa && $r->master_sa!=$r->orig_id) {
                $master_sims_ids[] = (int)$r->master_sa;
//				$sims = Shop_Catalogue::sgetSims($this->_dbr, $r->master_sa, 1, $this->_shop->username, 0);
			} else {
                $sims_ids[] = (int)$r->orig_id;
//				$sims = Shop_Catalogue::sgetSims($this->_dbr, $r->orig_id, 0, $this->_shop->username, 0);
			}
            
//			foreach($sims as $kk => $sim) {
//				$sims[$kk] = $this->getOffer($sim->sim_saved_id);
//                
//				if (!$sims[$kk]->saved_id) {
//                    unset($sims[$kk]);
//                } else {
////                    $sims[$kk]->banner = $this->loadBanner($sims[$kk]->master_sa);
//                    $sims_ids[] = $sims[$kk]->master_sa;
//                }
//			}
//
//            $ret[$k]->sims = $sims;
        }
        
        $master_sims = [];
        if ($master_sims_ids) 
        {
            $q = "select ss.saved_id as saved_id, sp.saved_id as sim_saved_id
                from saved_params sp
                join saved_sim ss on ss.saved_id IN (" . implode(',', $master_sims_ids) . ") and 1*sp.par_value=ss.sim_saved_id and ss.inactive=0
                join saved_params sp_username on sp.saved_id=sp_username.saved_id and sp_username.par_key='username'
                    and sp_username.par_value='{$this->_shop->username}'
                LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id=sp.saved_id and sp_auction_name.par_key = 'auction_name'
                join saved_auctions sa on sa.id=sp.saved_id and sa.inactive in (0) and sa.old in (0)
                where sp.par_key='master_sa' and sp.saved_id
                order by ss.ordering";
                    
            foreach ($this->_dbr->getAll($q) as $sim)
            {
                $master_sims[$sim->saved_id][] = $sim->sim_saved_id;
            }
        } 
        
        $offer_sims = [];
        if ($sims_ids)
        {
            $q = "select ss.saved_id
                , ss.sim_saved_id
                from saved_sim ss
                LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id = ss.sim_saved_id and sp_auction_name.par_key = 'auction_name'
                join saved_auctions sa on sa.id=ss.sim_saved_id and sa.old=0 and sa.inactive=0
                where ss.saved_id IN (" . implode(',', $sims_ids) . ") and ss.inactive=0 and ss.sim_saved_id
                order by ss.ordering";
            
            foreach ($this->_dbr->getAll($q) as $sim)
            {
                $offer_sims[$sim->saved_id][] = $sim->sim_saved_id;
            }
        }

        $sims_ids = [];
        foreach ($ret as $k => $r)
        {
            if ($r->master_sims && $r->master_sa && $r->master_sa!=$r->orig_id) 
            {
				$sims = $master_sims[$r->master_sa];
			} 
            else 
            {
                $sims = $offer_sims[$r->orig_id];
			}

			foreach($sims as $kk => $sim) {
				$sims[$kk] = $this->getOffer($sim);
                
				if (!$sims[$kk]->saved_id) {
                    unset($sims[$kk]);
                } else {
                    $sims_ids[] = $sims[$kk]->master_sa;
                }
			}

            $ret[$k]->sims = $sims;
        }
        
        $master_ids = array_map(function($v) {return (int)$v->master_sa;}, $ret);
        $saved_ids = array_map(function($v) {return (int)$v->saved_id;}, $ret);
        
        $master_ids = array_unique($master_ids);
        $saved_ids = array_unique($saved_ids);
        $sims_ids = array_unique($sims_ids);
        
        if ($saved_ids)
        {
            $stop_empty_warehouse = $this->_dbr->getAll("select `saved_id`, `par_value`
                from `saved_params` where `saved_id` IN (" . implode(',', $saved_ids) . ") 
                    and `par_key` like 'stop_empty_warehouse_shop%'");
            
            $stop_empty_warehouse_array = [];
            foreach ($stop_empty_warehouse as $saved_id => $_warehouse)
            {
                $stop_empty_warehouse_array[$saved_id][] = $_warehouse;
            }
            
            $color_types = $this->getColorType($master_ids);

            $pics = \SavedPic::getPrimary($master_ids, $this->_shop->lang);
            
            if ($this->_shop->master_banner) {
                $banners = $this->loadBanners($master_ids);
            } else {
                $banners = $this->loadBanners($saved_ids);
            }
            
            $sims_banners = $this->loadBanners($sims_ids);

            foreach ($ret as $k => $r)
            {
                $ret[$k]->path_prefix = $pics[$r->master_sa]->path_prefix;

                $ret[$k]->doc_id = isset($pics[$r->master_sa]->doc_id) ? $pics[$r->master_sa]->doc_id : 0;
                $ret[$k]->wdoc_id = isset($pics[$r->master_sa]->wdoc_id) ? $pics[$r->master_sa]->wdoc_id : 0;
                $ret[$k]->cdoc_id = isset($pics[$r->master_sa]->cdoc_id) ? $pics[$r->master_sa]->cdoc_id : 0;

                $ret[$k]->primary_pic_ext = ['color' => $pics[$r->master_sa]->ext_color, 'whitesh' => $pics[$r->master_sa]->ext_whitesh, 'whitenosh' => $pics[$r->master_sa]->ext_whitenosh];

                // Disable SA if we have not main picture
                if ( ! $ret[$k]->doc_id) {
                    unset($ret[$k]);
                    continue;
                }

                $ret[$k]->details = serialize(['stop_empty_warehouse_shop' => $stop_empty_warehouse_array[$r->saved_id]]);
                $ret[$k]->color_types = $color_types[$r->master_sa];
                
                if ($this->_shop->master_banner) 
                {
                    $ret[$k]->banner = isset($banners[$r->master_sa]) ? $banners[$r->master_sa] : false;
                }
                else 
                {
                    $ret[$k]->banner = isset($banners[$r->saved_id]) ? $banners[$r->saved_id] : false;
                }
                
                foreach ($ret[$k]->sims as $simk => $sim)
                {
                    $ret[$k]->sims[$simk]->banner = isset($sims_banners[$sim->master_sa]) ? $sims_banners[$sim->master_sa] : false;
                }
            }
        }

        $out = print_r($ret, true);
        file_put_contents('getOffers2.log', substr($out, 0, 1000000));
        
        if ($cache)
        {
            cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        }
        return $ret;
    }
    
    /**
     * Load banner data for Saved
     * @param int $saved_id
     * @return array  
     */
    public function loadBanner($saved_id) 
    {
        return \SavedBanner::getForSa($saved_id, $this->_shop->lang, $this->_seller->data->default_lang);
    }
    
    public function loadBanners($saved_ids) 
    {
        return \SavedBanner::getForSas($saved_ids, $this->_shop->lang, $this->_seller->data->default_lang);
    }

    function getOffer($sa_id, $from_cache = true)
    {
        if (!(int)$sa_id) return;
		global $debug;
        
        $page = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if ($page && ! $this->isPreview && stripos($page, '?') === false 
                && stripos($page, '.php') === false 
                && stripos($page, '/cart/') === false 
                && stripos($page, '/wish/') === false 
                && stripos($page, '/lang/') === false 
                && stripos($page, '/currency/') === false 
                && stripos($page, '/delcart/') === false 
                && stripos($page, '/content/') === false 
                && stripos($page, 'rating/') === false 
                && stripos($page, '/cabinet/') === false 
                && stripos($page, '/show_customer/') === false 
                && stripos($page, '/logout/') === false 
                && stripos($page, '/person_orders/') === false 
                && stripos($page, '/passreco/') === false 
                && stripos($page, '/clearCart/') === false 
                && stripos($page, '_description/') === false 
                && stripos($page, '/voucher/') === false 
                && stripos($page, '/voucher/') === false 
                && stripos($page, '/news/') === false 
                && stripos($page, '/order_wait/') === false 
                && stripos($page, '/order/') === false) {
            $this->_db->execParam("REPLACE INTO `sa_shop_url` (`sa_id`, `page`) VALUES (?, ?)", [$sa_id, $page]);
            if (mt_rand(1, 10000) <= 1) {
                $this->_db->query('DELETE FROM `sa_shop_url` WHERE `date` < DATE_ADD(NOW(), INTERVAL -14 DAY)');
            }
        }
        
        if (isset(self::$offers_cache[$sa_id]))
        {
            return self::$offers_cache[$sa_id];
        }
        
		$function = "getOffer($sa_id)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret && $from_cache) {
            self::$offers_cache[$sa_id] = $chached_ret;
			return $chached_ret;
		}

        $q = "
            select /*getOffer*/ distinct sa.id, sa.id orig_id, o.offer_id, sa.id as saved_id
            , IFNULL(sa.master_ShopSAAlias,1) master_ShopSAAlias
            , IFNULL(sa.master_ShopDesription,1) master_ShopDesription
            , IFNULL(sa.master_descriptionShop,1) master_descriptionShop
            , IFNULL(sa.master_descriptionTextShop,1) master_descriptionTextShop
            , IFNULL(sa.master_pics,1) master_pics
            , IFNULL(sa.master_banner,1)master_banner
            , IFNULL(sa.master_sims,1) master_sims
            , IFNULL(sa.master_others,1) master_others
            , IFNULL(sa.master_ShopSAKeywords,1) master_ShopSAKeywords
            , IFNULL(sa.master_ShopShortTitle,1) master_ShopShortTitle
            , IFNULL(sa.master_icons,1) master_icons
            , IFNULL(sa.master_amazon,1) master_amazon
            , IFNULL(master_sa.id, sa.id) master_sa
            , max(sa.shop_catalogue_id) cat_id
            , alias.name ShopDescription
            , sa.template_id
            , sa.ShopHPrice {$this->_fake_free_shipping} as ShopHPrice
            , sa.ShopMinusPercent
            , sa.ShopShortDescription
            , (0 {$this->_fake_free_shipping}) as fake_free_shipping
            , sa.ShopPrice {$this->_fake_free_shipping} as ShopPrice
            , alias.name alias
            , (select translation.value from translation
                    join saved_doc on saved_doc.doc_id=translation.id
                            where table_name='saved_doc' and field_name='alt' and translation.language='{$this->_shop->lang}'
                            and saved_doc.saved_id=IFNULL(master_sa.id, sa.id)
                order by ".($this->_shop->shop_pic_color=='white'?"IF(white_back=2,0,1)":"IF(white_back=0,0,1)").", `primary` desc, ordering limit 1) alt
            , (select translation.value from translation
                    join saved_doc on saved_doc.doc_id=translation.id
                            where table_name='saved_doc' and field_name='title' and translation.language='{$this->_shop->lang}'
                            and saved_doc.saved_id=IFNULL(master_sa.id, sa.id)
                order by ".($this->_shop->shop_pic_color=='white'?"IF(white_back=2,0,1)":"IF(white_back=0,0,1)").", `primary` desc, ordering limit 1) title
            , o.name offer_name
            , IFNULL(IFNULL(tShopSAAlias.value, tShopSAAlias_default.value), '{$this->_alias}') ShopSAAlias
            , IF(orig_o.available, concat(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), '') available
            , orig_o.offer_id orig_offer_id
            , orig_o.available_weeks
            , orig_o.available_date
            , IFNULL(tdescriptionTextShop2.value, tdescriptionTextShop2_default.value) descriptionTextShop2

            from sa{$this->_shop->id} sa
            left join sa_all master_sa on sa.master_sa=master_sa.id
            join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
            left join offer orig_o on orig_o.offer_id=sa.offer_id
            join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
                and t1.id=47 and t1.language='{$this->_shop->lang}'
            join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
                and t2.id=(57-orig_o.available)  and t2.language='{$this->_shop->lang}'
            left join translation tdescriptionTextShop2 on tdescriptionTextShop2.id=sa.id
                and tdescriptionTextShop2.table_name='sa'
                and tdescriptionTextShop2.field_name='descriptionTextShop2'
                and tdescriptionTextShop2.language = '{$this->_shop->lang}'
            left join translation tdescriptionTextShop2_default on tdescriptionTextShop2_default.id=sa.id
                and tdescriptionTextShop2_default.table_name='sa'
                and tdescriptionTextShop2_default.field_name='descriptionTextShop2'
                and tdescriptionTextShop2_default.language = '{$this->_seller->data->default_lang}'
            left join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopDesription.table_name='sa'
                and tShopDesription.field_name='ShopDesription'
                and tShopDesription.language = '{$this->_shop->lang}'
            left join translation tShopDesription_default on tShopDesription_default.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopDesription_default.table_name='sa'
                and tShopDesription_default.field_name='ShopDesription'
                and tShopDesription_default.language = '{$this->_seller->data->default_lang}'
            left join offer_name alias on IFNULL(tShopDesription.value, tShopDesription_default.value)=alias.id
            left join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopSAAlias.table_name='sa'
                and tShopSAAlias.field_name='ShopSAAlias'
                and tShopSAAlias.language = '{$this->_shop->lang}'
            left join translation tShopSAAlias_default on tShopSAAlias_default.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopSAAlias_default.table_name='sa'
                and tShopSAAlias_default.field_name='ShopSAAlias'
                and tShopSAAlias_default.language = '{$this->_seller->data->default_lang}'
            left join translation tsshipping_plan_free_tr
                on tsshipping_plan_free_tr.language=sa.siteid
                and tsshipping_plan_free_tr.id=orig_o.offer_id
                and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
            join translation tsshipping_plan_id
                on tsshipping_plan_id.language=sa.siteid
                and tsshipping_plan_id.id=orig_o.offer_id
                and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
            join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
            join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
            join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
                where 1
            and sa.username='" . mysql_real_escape_string($this->_shop->username) . "'
            and sa.siteid='" . $this->_shop->siteid . "'
            and o.hidden=0 and IFNULL(sa.old,0)=0
            and sa.id='$sa_id'
            group by sa.id
            order by IFNULL(sa.shopspos_cat*1,0)";
            
        $ret = $this->_dbr->getRow($q);

        if ( ! $ret && $this->isPreview)
        {
            $q = "
            select /*getOfferInactive*/ distinct sa.id, sa.id orig_id, o.offer_id, sa.id as saved_id
            , IFNULL(`shop`.`master_ShopSAAlias`,1) master_ShopSAAlias
            , IFNULL(`shop`.`master_ShopDesription`,1) master_ShopDesription
            , IFNULL(`shop`.`master_descriptionShop`,1) master_descriptionShop
            , IFNULL(`shop`.`master_descriptionTextShop`,1) master_descriptionTextShop
            , IFNULL(`shop`.`master_pics`,1) master_pics
            , IFNULL(`shop`.`master_banner`,1)master_banner
            , IFNULL(`shop`.`master_sims`,1) master_sims
            , IFNULL(`shop`.`master_others`,1) master_others
            , IFNULL(`shop`.`master_ShopSAKeywords`,1) master_ShopSAKeywords
            , IFNULL(`shop`.`master_ShopShortTitle`,1) master_ShopShortTitle
            , IFNULL(`shop`.`master_icons`,1) master_icons
            , IFNULL(`shop`.`master_amazon`,1) master_amazon
            , IFNULL(master_sa.id, sa.id) master_sa
            , max(`sp_shop_catalogue_id`.`par_value`) cat_id
            , alias.name ShopDescription
            , sa.template_id
            , `sp_ShopHPrice`.`par_value` {$this->_fake_free_shipping} as ShopHPrice
            , `sp_ShopMinusPercent`.`par_value` ShopMinusPercent
            , `sp_ShopShortDescription`.`par_value` AS `ShopShortDescription`
            , (0 {$this->_fake_free_shipping}) as fake_free_shipping
            , `sp_ShopPrice`.`par_value` {$this->_fake_free_shipping} as ShopPrice
            , alias.name alias
            , (select translation.value from translation
                    join saved_doc on saved_doc.doc_id=translation.id
                            where table_name='saved_doc' and field_name='alt' and translation.language='{$this->_shop->lang}'
                            and saved_doc.saved_id=IFNULL(master_sa.id, sa.id)
                order by ".($this->_shop->shop_pic_color=='white'?"IF(white_back=2,0,1)":"IF(white_back=0,0,1)").", `primary` desc, ordering limit 1) alt
            , (select translation.value from translation
                    join saved_doc on saved_doc.doc_id=translation.id
                            where table_name='saved_doc' and field_name='title' and translation.language='{$this->_shop->lang}'
                            and saved_doc.saved_id=IFNULL(master_sa.id, sa.id)
                order by ".($this->_shop->shop_pic_color=='white'?"IF(white_back=2,0,1)":"IF(white_back=0,0,1)").", `primary` desc, ordering limit 1) title
            , o.name offer_name
            , IFNULL(IFNULL(tShopSAAlias.value, tShopSAAlias_default.value), '{$this->_alias}') ShopSAAlias
            , IF(orig_o.available, concat(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), '') available
            , orig_o.offer_id orig_offer_id
            , orig_o.available_weeks
            , orig_o.available_date
            , IFNULL(tdescriptionTextShop2.value, tdescriptionTextShop2_default.value) descriptionTextShop2

            from `saved_auctions` `sa` 
            join `shop` on `shop`.`id` = '{$this->_shop->id}'
            left join `saved_params` `sp_master_sa` on `sp_master_sa`.`saved_id` = `sa`.`id` and `sp_master_sa`.`par_key` = 'master_sa'
            join `saved_params` `sp_shop_catalogue_id` on `sp_shop_catalogue_id`.`saved_id` = `sa`.`id` and `sp_shop_catalogue_id`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]'
            left join `saved_params` `sp_ShopHPrice` on `sp_ShopHPrice`.`saved_id` = `sa`.`id` and `sp_ShopHPrice`.`par_key` = 'ShopHPrice' 
            left join `saved_params` `sp_ShopMinusPercent` on `sp_ShopMinusPercent`.`saved_id` = `sa`.`id` and `sp_ShopMinusPercent`.`par_key` = 'ShopMinusPercent' 
            left join `saved_params` `sp_ShopShortDescription` on `sp_ShopShortDescription`.`saved_id` = `sa`.`id` and `sp_ShopShortDescription`.`par_key` = 'ShopShortDescription'
            left join `saved_params` `sp_ShopPrice` on `sp_ShopPrice`.`saved_id` = `sa`.`id` and `sp_ShopPrice`.`par_key` = 'ShopPrice'
            join `saved_params` `sp_offer_id` on `sp_offer_id`.`saved_id` = `sa`.`id` and `sp_offer_id`.`par_key` = 'offer_id'
            join `saved_params` `sp_siteid` on `sp_siteid`.`saved_id` = `sa`.`id` and `sp_siteid`.`par_key` = 'siteid'
            join `saved_params` `sp_username` on `sp_username`.`saved_id` = `sa`.`id` and `sp_username`.`par_key` = 'username' and `sp_username`.`par_value` = shop.username
            left join `saved_params` `sp_shopspos_cat` FORCE INDEX (`saved_id_par_key`) on `sp_shopspos_cat`.`saved_id` = `sa`.`id` and `sp_shopspos_cat`.`par_key` = concat('shopspos_cat[{$this->_shop->id}][',`sp_shop_catalogue_id`.`par_value`,']')

            left join sa_all master_sa on `sp_master_sa`.`par_value`=master_sa.id
            
            join offer o on o.offer_id=IFNULL(master_sa.offer_id, `sp_offer_id`.`par_value`)
            left join offer orig_o on orig_o.offer_id=`sp_offer_id`.`par_value`
            join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
                and t1.id=47 and t1.language='{$this->_shop->lang}'
            join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
                and t2.id=(57-orig_o.available)  and t2.language='{$this->_shop->lang}'
            left join translation tdescriptionTextShop2 on tdescriptionTextShop2.id=sa.id
                and tdescriptionTextShop2.table_name='sa'
                and tdescriptionTextShop2.field_name='descriptionTextShop2'
                and tdescriptionTextShop2.language = '{$this->_shop->lang}'
            left join translation tdescriptionTextShop2_default on tdescriptionTextShop2_default.id=sa.id
                and tdescriptionTextShop2_default.table_name='sa'
                and tdescriptionTextShop2_default.field_name='descriptionTextShop2'
                and tdescriptionTextShop2_default.language = '{$this->_seller->data->default_lang}'
            left join translation tShopDesription on tShopDesription.id=IF(IFNULL(`shop`.`master_ShopDesription`,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopDesription.table_name='sa'
                and tShopDesription.field_name='ShopDesription'
                and tShopDesription.language = '{$this->_shop->lang}'
            left join translation tShopDesription_default on tShopDesription_default.id=IF(IFNULL(`shop`.`master_ShopDesription`,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopDesription_default.table_name='sa'
                and tShopDesription_default.field_name='ShopDesription'
                and tShopDesription_default.language = '{$this->_seller->data->default_lang}'
            left join offer_name alias on IFNULL(tShopDesription.value, tShopDesription_default.value)=alias.id
            left join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(`shop`.`master_ShopSAAlias`,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopSAAlias.table_name='sa'
                and tShopSAAlias.field_name='ShopSAAlias'
                and tShopSAAlias.language = '{$this->_shop->lang}'
            left join translation tShopSAAlias_default on tShopSAAlias_default.id=IF(IFNULL(`shop`.`master_ShopSAAlias`,1),IFNULL(master_sa.id, sa.id), sa.id)
                and tShopSAAlias_default.table_name='sa'
                and tShopSAAlias_default.field_name='ShopSAAlias'
                and tShopSAAlias_default.language = '{$this->_seller->data->default_lang}'
            left join translation tsshipping_plan_free_tr
                on tsshipping_plan_free_tr.language=`sp_siteid`.`par_value`
                and tsshipping_plan_free_tr.id=orig_o.offer_id
                and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
            join translation tsshipping_plan_id
                on tsshipping_plan_id.language=`sp_siteid`.`par_value`
                and tsshipping_plan_id.id=orig_o.offer_id
                and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
            join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
            join config_api_values cav on cav.par_id=5 and cav.value=`sp_siteid`.`par_value`
            join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
                where 1
            and `sp_username`.`par_value`='" . mysql_real_escape_string($this->_shop->username) . "'
            and `sp_siteid`.`par_value`='" . $this->_shop->siteid . "'
            and o.hidden=0 and IFNULL(sa.old,0)=0
            and sa.id='$sa_id'
            group by sa.id
            order by IFNULL(`sp_shopspos_cat`.`par_value`*1,0)";
            
            $ret = $this->_dbr->getRow($q);
        }
        
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
            //die();
        }
        
        if ( ! $ret->alias || ! $ret->ShopSAAlias) {
            return false;
        }
        
        $pic = \SavedPic::getPrimary($ret->master_sa, $this->_shop->lang);
        $ret->path_prefix = $pic->path_prefix;
        
        $ret->doc_id = isset($pic->doc_id) ? $pic->doc_id : 0;
        $ret->wdoc_id = isset($pic->wdoc_id) ? $pic->wdoc_id : 0;
        $ret->cdoc_id = isset($pic->cdoc_id) ? $pic->cdoc_id : 0;
        
        $ret->color_type = $this->getColorType($sa_id);

        // Disable SA if we have not main picture
        if ( ! $ret->doc_id) {
            return false;
        }

        $route = $this->getAllNodes($ret->cat_id);
        $route = array_reverse($route);
        $ret->_route = $route;
        $ret->cat_route = '/';

        foreach($route as $cat) {
            $ret->cat = $this->_dbr->getOne("
                SELECT `value`
                FROM translation
                WHERE table_name = 'shop_catalogue'
                AND field_name = 'name'
                AND language = ?
                AND id = ?
                ", null, [$this->_shop->lang, $cat]);
            break;
        }
        
        $cat_array = $this->getAllNodesRecs($ret->cat_id);
        foreach ($cat_array as $catid => $catname) {
            if ($catname->alias) {
                $ret->cat_route .= $catname->alias . '/';
            }
        }
        
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        self::$offers_cache[$sa_id] = $ret;
        return $ret;
    }

    /**
     * Get SA available text by all languages
     * 
     * @param int $sa_id
     * @return array
     */
    function getOfferMultiLangShopAvailable($sa_id)
    {
        $sa = $this->getOffer($sa_id);
        $shopAvailableDate = $this->_dbr->getOne("SELECT available_date FROM offer WHERE offer_id='{$sa->orig_offer_id}'");
        $shopAvailableDate = strftime($this->_seller->get('date_format'), strtotime($shopAvailableDate));
        
        $query = "SELECT t1.language, IF(
                    orig_o.available, 
                        CONCAT(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), 
                        IF(
                            orig_o.available_weeks, 
                            CONCAT(t3.value, ' ',  DATE(date_add(NOW(), INTERVAL orig_o.available_weeks week))), 
                            IF(
                                orig_o.available_date='0000-00-00', 
                                t4.value, 
                                CONCAT(t3.value, ' {$shopAvailableDate}')
                            )
                        )
                    ) available
                FROM sa{$this->_shop->id} sa
                LEFT JOIN offer orig_o ON orig_o.offer_id = sa.offer_id
                JOIN translation t1 ON t1.table_name='translate_shop' AND t1.field_name = 'translate_shop'
                    AND t1.id = 47
                JOIN translation t2 ON t2.table_name='translate_shop' AND t2.field_name = 'translate_shop'
                    AND t2.id = (57-orig_o.available)
                JOIN translation t3 ON t3.table_name='translate_shop' AND t3.field_name = 'translate_shop'
                    AND t3.id = 216
                JOIN translation t4 ON t4.table_name='translate_shop' AND t4.field_name = 'translate_shop'
                    AND t4.id = 215
                    WHERE 1
                    AND t1.language = t2.language
                    AND t1.language = t3.language
                    AND t1.language = t4.language
                    AND sa.username = '{$this->_shop->username}'
                    AND sa.siteid = '{$this->_shop->siteid}'
                    AND sa.id = '$sa_id'
                    GROUP BY t1.language";

        return $this->_dbr->getAssoc($query);
    }
    
    /**
     * Get SA description by all languages
     * 
     * @param int $sa_id
     * @return array
     */
    function getOfferMultiLangShopDescription($sa_id)
    {
        $q = "SELECT `translation`.`language`, `offer_name`.`name`
                FROM `sa{$this->_shop->id}` `sa`
                LEFT JOIN `sa_all` `master_sa` ON `sa`.`master_sa`=`master_sa`.`id`
                LEFT JOIN `translation` ON `translation`.`id`=IF(IFNULL(`sa`.`master_ShopDesription`,1),IFNULL(`master_sa`.`id`, `sa`.`id`), `sa`.`id`)
                    AND `translation`.`table_name`='sa'
                    AND `translation`.`field_name`='ShopDesription'
                LEFT JOIN `offer_name` ON `translation`.`value`=`offer_name`.`id`
                WHERE 1
                    AND `sa`.`username`=?
                    AND `sa`.`siteid`=?
                    AND `sa`.`id`=?

                GROUP BY `translation`.`language`";
        return $this->_dbr->getAssoc($q, null, [$this->_shop->username, $this->_shop->siteid, $sa_id]);
    }

    function getCustomerOffers($customer_id)
    {
		$sas = $this->_dbr->getOne("select group_concat(distinct saved_id)
			from prologis_log.customer_page_log where customer_id=$customer_id and IFNULL(saved_id,0)>0");
		if (!strlen($sas)) return;
		$q = "#getCustomerOffers($customer_id)
			select sa.id, o.offer_id, sa.id as saved_id
			, max(sa.shop_catalogue_id) cat_id
			/*, (select par_value
				from saved_params sp
				where sp.par_key like 'shop_catalogue_id%'
				and saved_id=sa.id
				order by id desc limit 1) cat_id*/
			, alias.name ShopDescription
			, sa.ShopHPrice  $this->_fake_free_shipping as ShopHPrice
			, sa.ShopMinusPercent
			, sa.ShopShortDescription
			, sa.ShopPrice  $this->_fake_free_shipping as ShopPrice
			, alias.name alias
			, tShopSAAlias.value ShopSAAlias
			, (select doc_id from saved_doc where saved_id=IF(IFNULL(sa.master_pics,1), IFNULL(master_sa.id, sa.id), sa.id)
				order by ".($this->_shop->shop_pic_color=='white'?"IF(white_back=2,0,1)":"IF(white_back=0,0,1)").", `primary` desc, ordering limit 1) doc_id
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='alt' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) alt
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='title' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) title
			, o.name offer_name
			,(select max(time) from prologis_log.customer_page_log where customer_id=$customer_id and saved_id=sa.id) last_time
			, IF(orig_o.available, concat(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), '') available
			from sa".$this->_shop->id." sa
			left join sa_all master_sa on sa.master_sa=master_sa.id
/*			join (select saved_id, max(time) last_time
				from prologis_log.customer_page_log where customer_id=$customer_id and IFNULL(saved_id,0)>0
				group by saved_id
				) tt on tt.saved_id=sa.id*/
			join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
			left join offer orig_o on orig_o.offer_id=sa.offer_id
			join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
				and t1.id=47 and t1.language='".$this->_shop->lang."'
			left join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
				and t2.id=(57-orig_o.available)  and t2.language='".$this->_shop->lang."'
			join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa'
				and tShopDesription.field_name='ShopDesription'
				and tShopDesription.language = '".$this->_shop->lang."'
			join offer_name alias on tShopDesription.value=alias.id
			join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopSAAlias.table_name='sa'
				and tShopSAAlias.field_name='ShopSAAlias'
				and tShopSAAlias.language = '".$this->_shop->lang."'
			left join translation tsshipping_plan_free_tr
				on tsshipping_plan_free_tr.language=sa.siteid
				and tsshipping_plan_free_tr.id=orig_o.offer_id
				and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
			join translation tsshipping_plan_id
				on tsshipping_plan_id.language=sa.siteid
				and tsshipping_plan_id.id=orig_o.offer_id
				and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
			join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
			join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
			join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
				where 1 and tShopSAAlias.value<>''
			and sa.id in ($sas)
			and o.hidden=0 and IFNULL(sa.old,0)=0
			group by sa.id
#			order by last_time desc
#			limit 12
			";
//		echo $q;
		$ret = $this->_dbr->getAll($q);
		global $debug;
		if ($debug) echo $q.'<br>';
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
            //die();
        }
		function sortitems1($a, $b) {
			$res = -1*strcmp($a->last_time, $b->last_time);
//			echo "$a->ShopDescription: $a->last_time, $b->last_time<br>";
			return $res;
		}
		$r = usort($ret, 'sortitems1');
		foreach ($ret as $k=>$r) {
			if ($k>=12) { unset($ret[$k]); continue; }
			$ret[$k]->cat_route = '/';
//			echo '<br>'.$ret[$k]->cat_route; print_r($route);
		}
        return $ret;
    }

    function getLastCustomerOffer($customer_id)
    {
		global $debug;
		$q = "select saved_id
					from prologis_log.customer_page_log
					where customer_id=$customer_id and IFNULL(saved_id,0)>0
					order by `time` desc limit 1";
//		if ($debug) echo $q.'<br>';
//		echo $q;
		$lastOffer = $this->_dbr->getRow($q);
		return $this->getOffer($lastOffer->saved_id);
    }

    function getLastCustomerOffers($customer_id, $count = 4)
    {
		global $debug;
		$q = "select distinct saved_id
					from prologis_log.customer_page_log
					where customer_id=$customer_id and IFNULL(saved_id,0)>0
					order by `time` desc limit $count";
		$lastOffers = $this->_dbr->getAll($q);
		$res = [];
		foreach($lastOffers as $sa) {
			$_offer = $this->getOffer($sa->saved_id);
            $_offer->rating_statistic = $this->getRating($sa->saved_id);
            if ($_offer && $_offer->doc_id) {
                $res[] = $_offer;
            }
		}
		return $res;
    }

    function getPHPSESSIDOffers($PHPSESSID)
    {
		$q = "select saved_id, max(time) last_time
				from prologis_log.shop_page_log where PHPSESSID='$PHPSESSID'
				 and IFNULL(saved_id,0)>0
				group by saved_id
				order by last_time desc";
		$sas = $this->_dbr->getAll($q);
		$res = array();
		foreach($sas as $sa) {
			$res[] = $this->getOffer($sa->saved_id);
		}
		return $res;

		$q = "#getPHPSESSIDOffers($PHPSESSID)
			select distinct sa.id, o.offer_id, sa.id as saved_id
			, (select par_value
				from saved_params sp
				join shop_catalogue_shop scs on scs.shop_id=".$this->_shop->id." and scs.shop_catalogue_id=par_value*1
				join shop_catalogue sc on scs.shop_catalogue_id=sc.id
				where sp.par_key = 'shop_catalogue_id[".$this->_shop->id."]'
				and saved_id=sa.id
				and scs.hidden=0 and sc.hidden=0
				order by sp.id desc limit 1) cat_id
#			, alias.name ShopDescription
			, sa.ShopHPrice  $this->_fake_free_shipping as ShopHPrice
			, sa.ShopMinusPercent
			, sa.ShopShortDescription
			, sa.ShopPrice  $this->_fake_free_shipping as ShopPrice
#			, alias.name alias
#			, tShopSAAlias.value ShopSAAlias
			, (select doc_id from saved_doc where saved_id=IF(IFNULL(sa.master_pics,1), IFNULL(master_sa.id, sa.id), sa.id)
				order by ".($this->_shop->shop_pic_color=='white'?"IF(white_back=2,0,1)":"IF(white_back=0,0,1)").", `primary` desc, ordering limit 1) doc_id
			, o.name offer_name
			, IF(orig_o.available, concat(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), '') available
#			, tShopDesription.value tShopDesription_value
			, IFNULL(master_sa.id, sa.id) master_sa_id
			from sa".$this->_shop->id." sa
			left join sa_all master_sa on sa.master_sa=master_sa.id
			join (select saved_id, max(time) last_time
				from prologis_log.shop_page_log where PHPSESSID='$PHPSESSID'
				 and IFNULL(saved_id,0)>0
				group by saved_id
				) tt on tt.saved_id=sa.id
			join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
			left join offer orig_o on orig_o.offer_id=sa.offer_id
			join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
				and t1.id=47 and t1.language='".$this->_shop->lang."'
			left join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
				and t2.id=(57-orig_o.available)  and t2.language='".$this->_shop->lang."'
			join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
#				and tShopDesription.table_name='sa'
#				and tShopDesription.field_name='ShopDesription'
#				and tShopDesription.language = '".$this->_shop->lang."'
#			join offer_name alias on tShopDesription.value=alias.id
#			join translation tShopSAAlias on tShopSAAlias.id=IFNULL(master_sa.id, sa.id)
#				and tShopSAAlias.table_name='sa'
#				and tShopSAAlias.field_name='ShopSAAlias'
#				and tShopSAAlias.language = '".$this->_shop->lang."'
			left join translation tsshipping_plan_free_tr
				on tsshipping_plan_free_tr.language=sa.siteid
				and tsshipping_plan_free_tr.id=orig_o.offer_id
				and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
			join translation tsshipping_plan_id
				on tsshipping_plan_id.language=sa.siteid
				and tsshipping_plan_id.id=orig_o.offer_id
				and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
			join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
			join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
			join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
				where 1 and tShopSAAlias.value<>''
			and sa.username='".$this->_shop->username."'
			and sa.siteid=".$this->_shop->siteid."
			and o.hidden=0 and IFNULL(sa.old,0)=0
			order by tt.last_time desc
			";
		$ret = $this->_dbr->getAll($q);
//		echo $q.'<br>';
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
            //die();
        }
		foreach ($ret as $k=>$r) {
			$ret[$k]->tShopDesription_value = $this->_dbr->getOne("select value
				from translation tShopDesription where tShopDesription.id={$r->master_sa_id}
				and tShopDesription.table_name='sa'
				and tShopDesription.field_name='ShopDesription'
				and tShopDesription.language = '".$this->_shop->lang."'");
			$ret[$k]->alias = $ret[$k]->ShopDescription = $this->_dbr->getOne("select name from offer_name
				where id=".$r->tShopDesription_value);
			$ret[$k]->ShopSAAlias = $this->_dbr->getOne("select value from translation tShopSAAlias
			where tShopSAAlias.id={$r->id}
				and tShopSAAlias.table_name='sa'
				and tShopSAAlias.field_name='ShopSAAlias'
				and tShopSAAlias.language = '".$this->_shop->lang."'");
			$ret[$k]->cat_route = '/';
//			echo '<br>'.$ret[$k]->cat_route; print_r($route);
		}
        return $ret;
    }

    function getLastOffer($PHPSESSID)
    {
		$q = "select saved_id
				from prologis_log.shop_page_log where PHPSESSID='$PHPSESSID'
				 and IFNULL(saved_id,0)>0
				 order by `time` desc limit 1";
		$lastOffer = $this->_dbr->getRow($q);
		return $this->getOffer($lastOffer->saved_id);

    }

    function getLastOffers($PHPSESSID, $count = 4)
    {
        $PHPSESSID = mysql_real_escape_string($PHPSESSID);
		$q = "select distinct saved_id
				from prologis_log.shop_page_log where PHPSESSID='$PHPSESSID'
				 and IFNULL(saved_id,0)>0
				 order by `time` desc limit $count";

		$lastOffers = $this->_dbr->getAll($q);
		$res = [];
		foreach($lastOffers as $sa) {
            $_offer = $this->getOffer($sa->saved_id);
            $_offer->rating_statistic = $this->getRating($sa->saved_id);
            if ($_offer && $_offer->doc_id) {
                $res[] = $_offer;
            }
		}

		return $res;
    }

	/**
	 * Prepare part of SQL query for counting relevance of found result whil searching
	 * @param $keywords array of keywords and key phrases
	 * @return string
	 */
	private static function prepareRelevanceCounting($keywords)
	{
		$relevance = array();
		foreach ($keywords as $key) {
			if (substr($key, 0, 1) === '+') {
				$key = substr($key, 1);
			}
			$key = mysql_real_escape_string($key);
			$relevance[] = "
				(
					(
						IF(
							t.alias LIKE '% $key %'
								OR t.alias LIKE '$key %'
								OR t.alias LIKE '% $key'
								OR t.alias = '$key',
							1,
							0)
					) + (
						IF(
							t.alias LIKE '%$key%',
							1,
							0
						)
					) + (
						IF(
							t.category_name LIKE '%$key%',
							2,
							0
						)
					)
				)";
		}
		return implode('+', $relevance);
	}

	/**
	 * Split user search line on phrases, process special search operators.
	 * User can use next operators:
	 * 		"some words" - search for phrase
	 * 		+word - mandatory word or phrase (if + set before quotes)
	 * @param string $searchLine line for search, f.e.
	 * 	"first phrase" word2 +word3 +"fourth phrase"
	 * @return array of string, f.e. for input above
	 * 	['first phrase', 'word2', '+word3', '+fourth phrase']
	 */
	private static function splitSearchLine($searchLine)
	{
		/**
		 * Filter special sphinx chars and algorithm chars
		 */
		$searchLine = strtolower($searchLine);
		$searchLine = str_replace('$$', '', $searchLine);

		/**
		 * Split for phrases
		 */
		$searchLine = str_replace('\"', '"', $searchLine);
		$keys = explode('"', $searchLine);
		$phrases = array();
		for ($i=1; $i<sizeof($keys); $i+=2){
			$phrase = trim($keys[$i]);
			if (strlen($phrase) !== 0) {
				$phrases[$i] = trim(str_replace('+', ' ', $phrase));
				$searchLine = str_replace('"'.$keys[$i].'"', '$$phrase'.$i.'$$', $searchLine);
			}
		}
		$searchLine = str_replace('+', ' +', $searchLine);
		$searchLine = str_replace('$$$$', '$$ $$', $searchLine);
		while (strpos($searchLine, '  ')!==false) {
			$searchLine = str_replace('  ', ' ', $searchLine);
		}

		/**
		 * Input phrases into result array
		 */
		$result = explode(' ', $searchLine);
		foreach ($result as $id => $item) {
			if (substr(ltrim($item, '+'), 0, 8) === '$$phrase') {
				$phraseId = (int)str_replace('$$phrase', '', $item);
				$result[$id] = str_replace('$$phrase'.$phraseId.'$$', $phrases[$phraseId], $result[$id]);
			}
			if (strlen(str_replace([' ', '+', '-'], '', $result[$id])) === 0) {
				unset($result[$id]);
			}
		}
		return $result;
	}

    /**
     * Prepare search query
     * 
     * @param string $relevance
     * @param string $where
     * @param string $ordering
     * @param int $offset
     * @param int $limit
     * @return String
     */
    private function _getSearchOffersQuery($relevance, $where, $ordering = 'saved_id', $offset = 0, $limit = 1) {
        $relevance = empty($relevance) ? 0 : $relevance;
        
        return "
            SELECT
                t.*,
                fget_avg_rating(t.saved_id) AS rating,
                $relevance AS relevance
            FROM (
                SELECT
                    MIN(IFNULL(sa.shopspos_cat*1, 0)) shopspos_cat,
                    sa.offer_id,
                    sa.id AS saved_id,
                    sa.id orig_id,
                    IFNULL(master_sa.id, sa.id) master_sa,
                    IFNULL(sa.master_ShopSAAlias, 1) master_ShopSAAlias,
                    IFNULL(sa.master_ShopDesription, 1) master_ShopDesription,
                    IFNULL(sa.master_descriptionShop, 1) master_descriptionShop,
                    IFNULL(sa.master_descriptionTextShop, 1) master_descriptionTextShop,
                    IFNULL(sa.master_pics, 1) master_pics,
                    IFNULL(sa.master_banner, 1) master_banner,
                    IFNULL(sa.master_sims, 1) master_sims,
                    IFNULL(sa.master_others, 1) master_others,
                    IFNULL(sa.master_ShopSAKeywords,1) master_ShopSAKeywords,
                    IFNULL(sa.master_ShopShortTitle,1) master_ShopShortTitle,
                    IFNULL(sa.master_icons,1) master_icons,
                    IFNULL(sa.master_amazon,1) master_amazon,
                    MAX(sa.shop_catalogue_id*1) cat_id,
                    alias.name ShopDescription,
                    sa.ShopHPrice {$this->_fake_free_shipping} AS ShopHPrice,
                    sa.ShopMinusPercent,
                    sa.ShopShortDescription,
                    sa.ShopPrice {$this->_fake_free_shipping} AS ShopPrice,
                    alias.name alias,
                    IF(IF(o.sshipping_plan_free, 1, IFNULL(tsshipping_plan_free_tr.value,0)), 0, spc.shipping_cost) AS shipping_cost,
                    IFNULL(tShopSAAlias.value, tShopSAAlias_default.value) ShopSAAlias
                    , (
                        SELECT translation.value
                        FROM translation
                            INNER JOIN saved_doc ON
                                saved_doc.doc_id = translation.id
                        WHERE
                            table_name = 'saved_doc'
                            AND field_name = 'alt'
                            AND translation.language = '{$this->_shop->lang}'
                            AND saved_doc.saved_id = IFNULL(master_sa.id, sa.id)
                        ORDER BY saved_doc.primary DESC
                        LIMIT 1
                    ) alt,
                    (
                        SELECT translation.value
                        FROM translation
                            INNER JOIN saved_doc ON
                                saved_doc.doc_id=translation.id
                        WHERE
                            table_name = 'saved_doc'
                            AND field_name = 'title'
                            AND translation.language = '{$this->_shop->lang}'
                            AND saved_doc.saved_id=IFNULL(master_sa.id, sa.id)
                        ORDER BY saved_doc.primary DESC
                        LIMIT 1
                    ) title,
                    orig_o.offer_id orig_offer_id,
                    orig_o.available,
                    orig_o.available_weeks,
                    orig_o.available_date,
                    GROUP_CONCAT(IFNULL(IFNULL(category.value, category_default.value),'') SEPARATOR ' ') AS category_name,
                    (
                        SELECT GROUP_CONCAT(CONCAT(
                            al.article_id, ':', al.default_quantity, ':', a.weight_per_single_unit
                        ))
                        FROM article_list al
                            INNER JOIN offer_group og ON
                                al.group_id = og.offer_group_id
                                AND base_group_id = 0
                            INNER JOIN article a ON
                                a.article_id = al.article_id
                                AND a.admin_id = 0
                        WHERE
                            og.offer_id = o.offer_id
                            AND al.inactive = 0
                            AND og.additional = 0
                    ) als
                FROM sa{$this->_shop->id} sa
                    LEFT JOIN sa_all master_sa ON
                        sa.master_sa = master_sa.id
                    INNER JOIN offer o ON
                        o.offer_id = IFNULL(master_sa.offer_id, sa.offer_id)
                    LEFT JOIN offer orig_o ON
                        orig_o.offer_id = sa.offer_id
                    LEFT JOIN translation tShopDesription ON
                        tShopDesription.id = IF(
                            IFNULL(sa.master_ShopDesription, 1),
                            IFNULL(master_sa.id, sa.id),
                            sa.id
                        )
                        AND tShopDesription.table_name = 'sa'
                        AND tShopDesription.field_name = 'ShopDesription'
                        AND tShopDesription.language = '{$this->_shop->lang}'
                    LEFT JOIN translation tShopDesription_default ON
                        tShopDesription_default.id = IF(
                            IFNULL(sa.master_ShopDesription, 1),
                            IFNULL(master_sa.id, sa.id),
                            sa.id
                        )
                        AND tShopDesription_default.table_name = 'sa'
                        AND tShopDesription_default.field_name = 'ShopDesription'
                        AND tShopDesription_default.language = '{$this->_seller->data->default_lang}'
                    INNER JOIN offer_name alias ON
                        IFNULL(tShopDesription.value, tShopDesription_default.value) = alias.id
                    LEFT JOIN translation tShopSAAlias ON
                        tShopSAAlias.id = IF(
                            IFNULL(sa.master_ShopSAAlias, 1),
                            IFNULL(master_sa.id, sa.id),
                            sa.id
                        )
                        AND tShopSAAlias.table_name = 'sa'
                        AND tShopSAAlias.field_name = 'ShopSAAlias'
                        AND tShopSAAlias.language = '{$this->_shop->lang}'
                    LEFT JOIN translation tShopSAAlias_default ON
                        tShopSAAlias_default.id = IF(
                            IFNULL(sa.master_ShopSAAlias, 1),
                            IFNULL(master_sa.id, sa.id),
                            sa.id
                        )
                        AND tShopSAAlias_default.table_name = 'sa'
                        AND tShopSAAlias_default.field_name = 'ShopSAAlias'
                        AND tShopSAAlias_default.language = '{$this->_seller->data->default_lang}'
                    LEFT JOIN translation category ON
                        category.id = sa.shop_catalogue_id
                        AND category.table_name = 'shop_catalogue'
                        AND category.field_name = 'name'
                        AND category.language = '{$this->_shop->lang}'
                    LEFT JOIN translation category_default ON
                        category_default.id = sa.shop_catalogue_id
                        AND category_default.table_name = 'shop_catalogue'
                        AND category_default.field_name = 'name'
                        AND category_default.language = '{$this->_seller->data->default_lang}'
                    LEFT JOIN translation tsshipping_plan_free_tr
                        on tsshipping_plan_free_tr.language = {$this->_shop->siteid}
                        AND tsshipping_plan_free_tr.id = orig_o.offer_id
                        AND tsshipping_plan_free_tr.table_name = 'offer'
                        AND tsshipping_plan_free_tr.field_name = 'sshipping_plan_free_tr'
                    INNER JOIN translation tsshipping_plan_id ON
                        tsshipping_plan_id.language = {$this->_shop->siteid}
                        AND tsshipping_plan_id.id = orig_o.offer_id
                        AND tsshipping_plan_id.table_name = 'offer'
                        AND tsshipping_plan_id.field_name = 'sshipping_plan_id'
                    INNER JOIN shipping_plan_country spc ON
                        spc.shipping_plan_id = tsshipping_plan_id.value
                    INNER JOIN config_api_values cav ON
                        cav.par_id = 5
                        AND cav.value = {$this->_shop->siteid}
                    INNER JOIN country c ON
                        c.code = spc.country_code
                        AND c.name = REPLACE(cav.description, 'United Kingdom', 'UK')
                WHERE
                    $where
                GROUP BY sa.id
            ) t
            ORDER BY $ordering
            LIMIT $offset , $limit
        ";
    }
    
	/**
	 * Search offers that include some text in his fields
	 * @param $key string with words for search
	 * @param int $page page number, used for pagination
	 * @param int $limit limit results per page, used for pagination
	 * @param string $sort sort field
	 * can be one of : rating|price|-price, for all another used default sorting - by relevancy
	 * @return array
	 * @todo get rid of $limit param - it doesn't used
	 */
	function searchOffers($key, $page = 1, $limit = 0, $sort = '')
	{
		if ((int)$page < 1) {
			$page = 1;
		}

		if ($this->_shop->search_results) {
			$limit = $this->_shop->search_results;
		} else {
			$limit = 100;
		}
        
        $function = "searchOffers($key,$page,$limit,$sort)";
        $chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
        if ($chached_ret)
        {
            return $chached_ret;
        }

		$offset = ($page - 1)*$limit;

		$searchKeys = self::splitSearchLine($key);
		$searchEngine = \label\Sphinx::getInstance();
		$ids = $searchEngine->findSimilarOffers($searchKeys, $this->_shop->lang, $this->_shop->id);

		switch ($sort) {
			case 'rating':
				$ordering = 'rating DESC';
				break;
			case 'price':
				$ordering = '1*t.ShopPrice ASC';
				break;
			case '-price':
				$ordering = '1*t.ShopPrice DESC';
				break;
			case '-relevance':
				//break skipped
			default:
				$ordering = 'relevance DESC';
				break;
		}

		$relevance = self::prepareRelevanceCounting($searchKeys);
        
		if (count($ids)) {
			/**
			 * @todo maybe it's not needed to count every time rating and relevance
			 */

            $where = " sa.id IN (" . implode(',', $ids) . ") ";
			$queryForSimilarOffers = $this->_getSearchOffersQuery($relevance, $where, $ordering, $offset, $limit);
			$resultOffers = $this->_dbr->getAll($queryForSimilarOffers);

			$foundRowsCount = $searchEngine->getLastQueryRowsCount();
			$debugBar = \label\DebugToolbar\DebugToolbar::getInstance();
			/**
			 * Filling full info about offers
			 */
            
			foreach ($resultOffers as $k => $offer) {
                if ( ! $offer->ShopSAAlias) {
                    unset($resultOffers[$k]);
                    continue;
                }
                
                $is_multi = $this->_isMulti($offer->master_sa);
                
                if ($is_multi !== null) {
                    if ($is_multi > 0) {
                        if ( ! isset($multi_offers[$is_multi])) {
                            foreach ($resultOffers as $_multi) {
                                if ($_multi->master_sa == $is_multi) {
                                    $offer = $resultOffers[$k] = $multi_offers[$is_multi] = $_multi;
                                    break;
                                }
                            }

                            if ( ! isset($multi_offers[$is_multi])) {
                                $where = " IFNULL(master_sa.id, sa.id) = '$is_multi' ";
                                $query = $this->_getSearchOffersQuery($relevance, $where);
                                $_offer = $this->_dbr->getRow($query);
                                if ($_offer)
                                {
                                    $offer = $resultOffers[$k] = $multi_offers[$is_multi] = $_offer;
                                }
                                else 
                                {
                                    $is_multi = null;
                                }
                            }
                        } else {
                            unset($resultOffers[$k]);
                            continue;
                        }
                    } else {
                        if ( ! isset($multi_offers[$offer->master_sa])) {
                            $multi_offers[$offer->master_sa] = $offer;
                        } else {
                            unset($resultOffers[$k]);
                            continue;
                        }
                    }
                }

                $resultOffers[$k]->is_multi = $is_multi !== null;
                if ($resultOffers[$k]->is_multi) {
                    $resultOffers[$k]->ShopDescription = $this->_getShopMultiDescription($offer->master_sa);
                    $price = $this->_getShopMultiPrice($offer->master_sa);
                    if ($price) {
                        $resultOffers[$k]->ShopMinusPercent = $price->ShopMinusPercent;
                        $resultOffers[$k]->ShopPrice = $price->ShopPrice;
                        $resultOffers[$k]->ShopHPrice = $price->ShopHPrice;
                    }
                }
            
                $pic = \SavedPic::getPrimary($offer->master_sa, $this->_shop->lang);
                $resultOffers[$k]->path_prefix = $pic->path_prefix;

                $resultOffers[$k]->doc_id = isset($pic->doc_id) ? $pic->doc_id : 0;
                $resultOffers[$k]->wdoc_id = isset($pic->wdoc_id) ? $pic->wdoc_id : 0;
                $resultOffers[$k]->cdoc_id = isset($pic->cdoc_id) ? $pic->cdoc_id : 0;
                
                $resultOffers[$k]->color_type = $this->getColorType($offer->saved_id);

                // Disable SA if we have not main picture
                if ( ! $resultOffers[$k]->doc_id) {
                    unset($resultOffers[$k]);
                    continue;
                }
                                
                $time = getmicrotime();
				$resultOffers[$k]->sizeofresults = $foundRowsCount;
				$add_rec = $this->_dbr->getRow("
					SELECT
						IF(
							NOT tinactivedescriptionShop1.value,
							tdescriptionShop1.value,
							IF(
								NOT tinactivedescriptionShop2.value,
								tdescriptionShop2.value,
								IF(
									NOT tinactivedescriptionShop3.value,
									tdescriptionShop3.value,
									NULL))
						) description,
						tShopSAKeywords.value add_keywords
					FROM saved_auctions sa
					LEFT JOIN translation tinactivedescriptionShop1 ON
						tinactivedescriptionShop1.id = sa.id
						AND tinactivedescriptionShop1.table_name = 'sa'
						AND tinactivedescriptionShop1.field_name = 'inactivedescriptionShop1'
						AND tinactivedescriptionShop1.language = '" . $this->_shop->lang . "'
					LEFT JOIN translation tdescriptionShop1 ON
						tdescriptionShop1.id = sa.id
						AND tdescriptionShop1.table_name = 'sa'
						AND tdescriptionShop1.field_name = 'descriptionShop1'
						AND tdescriptionShop1.language = '" . $this->_shop->lang . "'
					LEFT JOIN translation tinactivedescriptionShop2 ON
						tinactivedescriptionShop2.id = sa.id
						AND tinactivedescriptionShop2.table_name = 'sa'
						AND tinactivedescriptionShop2.field_name = 'inactivedescriptionShop2'
						AND tinactivedescriptionShop2.language = '" . $this->_shop->lang . "'
					LEFT JOIN translation tdescriptionShop2 ON
						tdescriptionShop2.id = sa.id
						AND tdescriptionShop2.table_name = 'sa'
						AND tdescriptionShop2.field_name = 'descriptionShop2'
						AND tdescriptionShop2.language = '" . $this->_shop->lang . "'
					LEFT JOIN translation tinactivedescriptionShop3 ON
						tinactivedescriptionShop3.id = sa.id
						AND tinactivedescriptionShop3.table_name = 'sa'
						AND tinactivedescriptionShop3.field_name = 'inactivedescriptionShop3'
						AND tinactivedescriptionShop3.language = '" . $this->_shop->lang . "'
					LEFT JOIN translation tdescriptionShop3 ON
						tdescriptionShop3.id = sa.id
						AND tdescriptionShop3.table_name = 'sa'
						AND tdescriptionShop3.field_name = 'descriptionShop3'
						AND tdescriptionShop3.language = '" . $this->_shop->lang . "'
					LEFT JOIN translation tShopSAKeywords ON
						tShopSAKeywords.id = IF(
                            IFNULL(sa.master_ShopSAKeywords, 1),
                            IFNULL(master_sa.id, sa.id),
                            sa.id
                        )
						AND tShopSAKeywords.table_name = 'sa'
						AND tShopSAKeywords.field_name = 'ShopSAKeywords'
						AND tShopSAKeywords.language = '" . $this->_shop->lang . "'
					WHERE sa.id = {$offer->ifnullmastersa}
				");
				$resultOffers[$k]->description = $add_rec->description;
				$resultOffers[$k]->add_keywords = $add_rec->add_keywords;
				$resultOffers[$k]->rating_statistic = $this->getRating($offer->saved_id);
				$resultOffers[$k]->alt_def = str_replace('"', "'", $resultOffers[$k]->ShopDescription) . '_' . $resultOffers[$k]->doc_id;
				$resultOffers[$k]->title_def = str_replace('"', "'", $resultOffers[$k]->ShopDescription) . '_' . $resultOffers[$k]->doc_id;
				$route = $this->getAllNodes($offer->cat_id);
				$route = array_reverse($route);
				$resultOffers[$k]->cat_route = '/';
				foreach ($route as $cat) {
					$resultOffers[$k]->cat = $this->_dbr->getOne("
						SELECT `value`
						FROM translation
						WHERE table_name = 'shop_catalogue'
							AND field_name = 'name'
							AND language = '{$this->_shop->lang}'
							AND id = " . $cat . "
						");
				}
				if ($offer->master_sims && $offer->master_sa && $offer->master_sa != $offer->orig_id) {
					$sims = Shop_Catalogue::sgetSims($this->_dbr, $offer->master_sa, 1, $this->_shop->username, 0);
				} else {
					$sims = Shop_Catalogue::sgetSims($this->_dbr, $offer->orig_id, 0, $this->_shop->username, 0);
				}
				foreach ($sims as $kk => $sim) {
					$sims[$kk] = $this->getOffer($sim->sim_saved_id);
                    
					if (!$sims[$kk]->saved_id) {
						unset($sims[$kk]);
					}
				}
				$resultOffers[$k]->sims = $sims;
				$debugBar['messages']->info(getmicrotime()-$time);
                
                
                // shipping_cost logic
                if ($this->_seller->get('free_shipping') || $this->_seller->get('free_shipping_total'))
                {
                    $resultOffers[$k]->shipping_cost = 0;
                }
                $resultOffers[$k]->shipping_cost = $resultOffers[$k]->shipping_cost - $this->fake_free_shipping;
                // shipping_cost logic
                $resultOffers[$k] = $this->convertPrices($resultOffers[$k]);
			}
            
            cacheSet($function, $this->_shop->id, $this->_shop->lang, $resultOffers);

			return $resultOffers;
		}
		return [];
	}

	/**
	 * Fast version of searchOffers
	 * It use less parameters for fast ajax search
	 * @param string $keyword
	 * @return array list of found offers
	 */
    function searchOffersLite($keyword)
    {
		$limit = 1000;

		$keywordsArray = explode(' ', trim($keyword));
		//remove words with lenght <= 2
		$keywordsArray = array_map(function($n){ if (mb_strlen($n) > 2) return $n;}, $keywordsArray);

		$similarCategoriesIdsQuery = "
			SELECT `translation`.`id`
			FROM `translation`
				INNER JOIN `saved_params` ON
					`saved_params`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]'
					AND `saved_params`.`par_value` != ''
					AND `saved_params`.`par_value` = `translation`.`id`
			WHERE
				`translation`.`table_name` = 'shop_catalogue'
				AND `translation`.`field_name` = 'name'
				AND `translation`.`language` = '".$this->_shop->lang."'";
		foreach ($keywordsArray as $key) {
			$similarCategoriesIdsQuery .= "
				AND `translation`.`value` LIKE '%$key%'";
		}
		$similarCategoriesIdsQuery .= "
			GROUP BY `saved_params`.`par_value`
		";

		$matched_categories = $this->_dbr->getCol($similarCategoriesIdsQuery);

		if (!empty($matched_categories)) {
			$similarOffersQuery = $this->_getLiteSearchQuery($keywordsArray, $matched_categories, $limit);
			$offers = $this->_dbr->getAll($similarOffersQuery);
		}
		if (!isset($offers) || empty($offers)) {
			$similarOffersQuery = $this->_getLiteSearchQuery($keywordsArray, array(), $limit);
			$offers = $this->_dbr->getAll($similarOffersQuery);
		}

        $multi_offers = [];
		foreach ($offers as $k => $offer) {
            $is_multi = $this->_isMulti($offer->saved_id);
            if ($is_multi !== null) {
                if ($is_multi > 0) {
                    if ( ! isset($multi_offers[$is_multi])) {
                        $query = "SELECT DISTINCT `sa`.`id` as `sa_id`
                            ,`shop`.`master_ShopSAKeywords`
                            ,`shop`.`master_ShopSAAlias`
                            , IFNULL(`master_sa`.`id`, `sa`.`id`) as `saved_id`
                            , `tShopSAAlias`.`value` `ShopSAAlias`
                            , (SELECT `saved_params`.`par_value`
                                FROM `saved_params`
                                WHERE `saved_params`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]' 
                                    AND `saved_params`.`par_value` != '' 
                                    AND `saved_params`.`saved_id` = `saved_id`) AS `cat_id`
                            , (	select doc_id from saved_pic
                                where saved_id = IFNULL(`master_sa`.`id`, `sa`.`id`)
                                order by `primary` desc, ordering limit 1) doc_id
                            from sa{$this->_shop->id} sa
                            left join sa_all master_sa on sa.master_sa=master_sa.id
                            join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
                            left join offer orig_o on orig_o.offer_id=sa.offer_id
                            join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                                and tShopSAAlias.table_name='sa'
                                and tShopSAAlias.field_name='ShopSAAlias'
                                and tShopSAAlias.language = '{$this->_shop->lang}'
                            JOIN shop on shop.id='{$this->_shop->id}'
                            where 1

                                and sa.username='".$this->_shop->username."'
                                and sa.siteid=".$this->_shop->siteid."
                                and o.hidden=0 and IFNULL(sa.old,0)=0
                                and IFNULL(`master_sa`.`id`, `sa`.`id`) = ?";
                                
                        $_offer = $this->_dbr->getRow($query, null, [$is_multi]);
                        if ($_offer)
                        {
                            $offer = $offers[$k] = $multi_offers[$is_multi] = $_offer;
                        }
                        else 
                        {
                            $is_multi = null;
                        }
                    } else {
                        unset($offers[$k]);
                        continue;
                    }
                } else {
                    if ( ! isset($multi_offers[$offer->saved_id])) {
                        $multi_offers[$offer->saved_id] = $offer;
                    } else {
                        unset($offers[$k]);
                        continue;
                    }
                }
            }
            
            if ( ! $offer->ShopSAAlias) {
                unset($offers[$k]);
                continue;
            }
            
            $offers[$k]->is_multi = $is_multi !== null;
            if ($offers[$k]->is_multi) {
                $offers[$k]->alias = $this->_getShopMultiDescription($offer->saved_id);
                $price = $this->_getShopMultiPrice($offer->saved_id);
                if ($price) {
                    $offers[$k]->ShopMinusPercent = $price->ShopMinusPercent;
                    $offers[$k]->ShopPrice = $price->ShopPrice;
                    $offers[$k]->ShopHPrice = $price->ShopHPrice;
                }
            }
            
            $offers[$k]->cat_route = $this->getCategoryRoute($offer->cat_id);
            $offers[$k]->cat_name = $this->getCategoryName($offer->cat_id);
            
            $pic = \SavedPic::getPrimary($offer->saved_id);
            
            $offers[$k]->doc_id = isset($pic->doc_id) ? $pic->doc_id : 0;
            $offers[$k]->wdoc_id = isset($pic->wdoc_id) ? $pic->wdoc_id : 0;
            $offers[$k]->cdoc_id = isset($pic->cdoc_id) ? $pic->cdoc_id : 0;

            $offers[$k]->color_type = $this->getColorType($offer->saved_id);

            // Disable SA if we have not main picture
            if ( ! $offer->doc_id) {
                unset($offers[$k]);
                continue;
            }
		}

        return $offers;
    }

	/**
	 * Get full route for category from cache, if absentee - from db and store in cache
	 * @param int $categoryId
	 * @return string starting and ending with /
	 */
	private function getCategoryRoute($categoryId)
	{
		if (!isset($this->routesCache[$categoryId])) {
			$this->fillRoutesCache($categoryId);
		}
		return $this->routesCache[$categoryId];
	}

	/**
	 * Get name for category
	 * @param int $categoryId
	 * @return string
	 */
	private function getCategoryName($categoryId)
	{
		if (!isset($this->categoryNameCache[$categoryId])) {
			$this->fillCategoryNameCache($categoryId);
		}
		return $this->categoryNameCache[$categoryId];

	}

	/**
	 * Get full route for category from db.
	 * Store result in cache.
	 * @param int $categoryId
	 */
	private function fillCategoryNameCache($categoryId)
	{
		$route = $this->getAllNodes($categoryId);
		$this->categoryNameCache[$categoryId] = $this->_dbr->getOne("
			SELECT `value`
			FROM translation
			WHERE
				table_name = 'shop_catalogue'
				AND field_name = 'name'
				AND language = '{$this->_shop->lang}'
				AND id = ".$route[0]."
		");
	}

	/**
	 * Get name for category from db.
	 * Store result in cache.
	 * @param int $categoryId
	 */
	private function fillRoutesCache($categoryId)
	{
		$route = $this->getAllNodes($categoryId);

		$route = array_reverse($route);
		$this->routesCache[$categoryId] = '/';
		foreach($route as $cat) {
			if ($cat) {//last category is 0, I don't know why
				$this->routesCache[$categoryId] .= $this->_dbr->getOne("
					SELECT `value`
					FROM translation
					WHERE
						table_name = 'shop_catalogue'
						AND field_name = 'alias'
						AND language = '{$this->_shop->lang}'
						AND id = " . $cat . "
				");
				$this->routesCache[$categoryId] .= '/';
			}
		}
	}

	/**
	 * Prepare sql query to select similar offers
	 * @param array $key_parts array of separated words
	 * @param array $matched_categories array of categories to search
	 * @param int $limit
	 * @return string sql query
	 */
	private function _getLiteSearchQuery($key_parts, $matched_categories, $limit)
	{
		$relevancy = '';
		$conditions = array();
		foreach ($key_parts as $key) {
			$relevancy .= empty($relevancy) ? '' : ' + ';
			$relevancy .= "
				IF(
					(
						UPPER(`alias`.`name`) like UPPER('% $key %')
						OR UPPER(`alias`.`name`) like UPPER('$key %')
						OR UPPER(`alias`.`name`) like UPPER('% $key')
						OR UPPER(`alias`.`name`) = UPPER('$key')),
					1,
					0)
				+ IF(
					UPPER(`alias`.`name`) LIKE UPPER('%$key%'),
					1,
					0)
				+ IF(
					(
						UPPER(`shopMultiName`.`value`) like UPPER('% $key %')
						OR UPPER(`shopMultiName`.`value`) like UPPER('$key %')
						OR UPPER(`shopMultiName`.`value`) like UPPER('% $key')
						OR UPPER(`shopMultiName`.`value`) = UPPER('$key')),
					1,
					0)
				+ IF(
					UPPER(`shopMultiName`.`value`) LIKE UPPER('%$key%'),
					1,
					0)
				+ IF(
					(
						UPPER(`tdescriptionShop1`.`value`) LIKE UPPER('%$key%')
						OR UPPER(`tdescriptionShop2`.`value`) LIKE UPPER('%$key%')
						OR UPPER(`tdescriptionShop3`.`value`) LIKE UPPER('%$key%')),
					1,
					0)
				+ IF(
					UPPER(`tShopSAKeywords`.`value`) like UPPER('%$key%'),
					1,
					0)
			";

			$conditions[] = "
				UPPER(`alias`.`name`) LIKE UPPER('%$key%')
				OR UPPER(`shopMultiName`.`value`) LIKE UPPER('%$key%')
				OR (
					UPPER(`tdescriptionShop1`.`value`) LIKE UPPER('%$key%')
					OR UPPER(`tdescriptionShop2`.`value`) LIKE UPPER('%$key%')
					OR UPPER(`tdescriptionShop3`.`value`) LIKE UPPER('%$key%')
				)
				OR UPPER(`tShopSAKeywords`.`value`) LIKE UPPER('%$key%')
			";
		}

		$fake_free_shipping = str_replace('sa.ShopPrice', '`sp_ShopPrice`.`par_value`', $this->_fake_free_shipping);

		$q = "SELECT
				`sa`.`id` as `sa_id`
                ,`shop`.`master_ShopSAKeywords`
                ,`shop`.`master_ShopSAAlias`
				, IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`) as `saved_id`
				,`list`.`par_value` as `cat_id`
				, ($relevancy) as `relevancy`
				, `sp_ShopPrice`.`par_value` $fake_free_shipping AS `ShopPrice`
				, `alias`.`name` as `alias`
				, `tShopSAAlias`.`value` `ShopSAAlias`
				, (	select
						doc_id
					from saved_pic
					where saved_id = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
					order by `primary` desc, ordering limit 1) doc_id
			FROM (
				SELECT `saved_id`,`par_value`
				FROM `saved_params`
				WHERE `saved_params`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]' ";

			if (!empty($matched_categories))
				$q .= "AND `saved_params`.`par_value` IN ('" . implode("','", $matched_categories) . "') ";
			else
				$q .= "AND `saved_params`.`par_value` != '' ";
			$q .= "GROUP BY `saved_id`
			) `list`

			/* sa */
			LEFT JOIN `saved_auctions` `sa`
				ON `sa`.`id` = `list`.saved_id

			/* master_sa detection */
			LEFT JOIN `saved_params` `master_sa`
				ON `master_sa`.`saved_id` = `sa`.`id`
				AND `master_sa`.`par_key` = _utf8'master_sa'
                
            JOIN shop on shop.id='".$this->_shop->id."'

			/* offer */
			LEFT JOIN `saved_params` `sp_offer_id`
				ON `sp_offer_id`.`saved_id` = `sa`.`id`
				AND `sp_offer_id`.`par_key` = 'offer_id'
			LEFT JOIN `offer` `o` ON `o`.`offer_id` = `sp_offer_id`.`par_value`

			/* title */
			LEFT JOIN `translation` `title`
			   ON `title`.`language` = '".$this->_shop->lang."'
				AND `title`.`table_name` = 'sa'
				AND `title`.`field_name` = 'ShopDesription'
				AND `title`.`id` = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
			LEFT JOIN `offer_name` `alias` on `title`.`value` = `alias`.`id`

			/* Multi */
			LEFT JOIN `saved_params` `sp_multi_id`
				ON `sp_multi_id`.`saved_id` = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
				AND `sp_multi_id`.`par_key` = 'master_multi'
				AND `sp_multi_id`.`par_value` = '0'

			/* Multi description */
			LEFT JOIN `translation` `shopMultiName` on `shopMultiName`.`id` = `sp_multi_id`.`saved_id`
				AND `shopMultiName`.`table_name` = 'sa'
				AND `shopMultiName`.`field_name` = 'ShopMultiDesription'
				AND `shopMultiName`.`language` = '".$this->_shop->lang."'

			/* description */
			LEFT JOIN `translation` `tdescriptionShop1` on `tdescriptionShop1`.`id` = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
				AND `tdescriptionShop1`.`table_name` = 'sa'
				AND `tdescriptionShop1`.`field_name` = 'descriptionShop1'
				AND `tdescriptionShop1`.`language` = '".$this->_shop->lang."'
			LEFT JOIN `translation` `tdescriptionShop2` on `tdescriptionShop2`.`id` = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
				AND `tdescriptionShop2`.`table_name` = 'sa'
				AND `tdescriptionShop2`.`field_name` = 'descriptionShop2'
				AND `tdescriptionShop2`.`language` = '".$this->_shop->lang."'
			LEFT JOIN `translation` `tdescriptionShop3` on `tdescriptionShop3`.`id` = IF(`master_sa`.`par_value` != 0, `master_sa`.`par_value`, `sa`.`id`)
				AND `tdescriptionShop3`.`table_name` = 'sa'
				AND `tdescriptionShop3`.`field_name` = 'descriptionShop3'
				AND `tdescriptionShop3`.`language` = '".$this->_shop->lang."'

			/* keywords */
			LEFT JOIN `translation` `tShopSAKeywords` 
                ON `tShopSAKeywords`.`id` = IF(
                    IFNULL(`shop`.`master_ShopSAKeywords`, 1),
                    IFNULL(`master_sa`.`par_value`, `sa`.`id`),
                    `sa`.`id`
                )
                AND `tShopSAKeywords`.`table_name` = 'sa'
				AND `tShopSAKeywords`.`field_name` = 'ShopSAKeywords'
				AND `tShopSAKeywords`.`language` = '".$this->_shop->lang."'

			/* price */
			LEFT JOIN `saved_params` `sp_ShopPrice`
				ON `sp_ShopPrice`.`saved_id` = `sa`.`id`
				AND `sp_ShopPrice`.`par_key` = 'ShopPrice'

			/* shipping */
			LEFT JOIN `saved_params` `sp_siteid`
				ON `sp_siteid`.`saved_id` = `sa`.`id`
				AND `sp_siteid`.`par_key` = 'siteid'
			LEFT JOIN `translation` `tsshipping_plan_free_tr`
				ON `tsshipping_plan_free_tr`.`id` = `o`.`offer_id`
				AND `tsshipping_plan_free_tr`.`table_name` = 'offer'
				AND `tsshipping_plan_free_tr`.`field_name` = 'sshipping_plan_free_tr'
				AND `tsshipping_plan_free_tr`.`language` = sp_siteid.par_value
			LEFT JOIN `translation` `tsshipping_plan_id`
				ON `tsshipping_plan_id`.`language` = sp_siteid.par_value
				AND `tsshipping_plan_id`.`id` = `o`.`offer_id`
				AND `tsshipping_plan_id`.`table_name` = 'offer'
				AND `tsshipping_plan_id`.`field_name` = 'sshipping_plan_id'
			LEFT JOIN `shipping_plan_country` `spc`
				ON `spc`.`shipping_plan_id` = `tsshipping_plan_id`.`value`

			/* href */
            LEFT JOIN `translation` `tShopSAAlias` 
                ON `tShopSAAlias`.`id` = IF(IFNULL(`shop`.master_ShopSAAlias,1),IFNULL(NULLIF(`master_sa`.`par_value`, 0), `sa`.`id`), `sa`.`id`)
                AND `tShopSAAlias`.`table_name` = 'sa'
                AND `tShopSAAlias`.`field_name` = 'ShopSAAlias'
                AND `tShopSAAlias`.`language` = '".$this->_shop->lang."'
			WHERE `sa`.`inactive` = 0
			AND `o`.`hidden` = 0
			AND `spc`.`country_code` = '{$this->_seller->data->defshcountry}'
			AND (".implode(' OR ', $conditions).")

			ORDER BY `relevancy` DESC
			LIMIT $limit;
		";
		return $q;
	}

    function voucherOffers($code)
    {
		$sas = $this->_dbr->getOne("select group_concat(saved_id)
			from shop_promo_sa
			where code_id=(select id from shop_promo_codes where shop_id=".$this->_shop->id." and code='$code')");
		if (!strlen($sas)) return;

		$q = "
			select /*voucherOffers*/ distinct sa.id, o.offer_id, sa.id as saved_id, IFNULL(master_sa.id, sa.id) `master_sa`
			, (select max(par_value*1)
				from saved_params sp
				where sp.par_key like 'shop_catalogue_id[".$this->_shop->id."]'
				and saved_id=sa.id
				/*order by id desc limit 1*/) cat_id
			, alias.name ShopDescription
			, sa.ShopHPrice  $this->_fake_free_shipping as ShopHPrice
			, sa.ShopMinusPercent
			, sa.ShopShortDescription
			, sa.ShopPrice  $this->_fake_free_shipping as ShopPrice
			, alias.name alias
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='alt' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) alt
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='title' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) title
			, o.name offer_name
			, tShopSAAlias.value ShopSAAlias
			, IF(orig_o.available, concat(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), '') available
			, (SELECT group_concat(concat(al.article_id,':',al.default_quantity,':',a.weight_per_single_unit))
				FROM article_list al
				JOIN offer_group og ON al.group_id = og.offer_group_id and base_group_id=0
				join article a on a.article_id=al.article_id and a.admin_id=0
				WHERE og.offer_id = o.offer_id and al.inactive=0 and og.additional=0) als
			from sa".$this->_shop->id." sa
			left join sa_all master_sa on sa.master_sa=master_sa.id
			join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
			left join offer orig_o on orig_o.offer_id=sa.offer_id
			join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
				and t1.id=47 and t1.language='".$this->_shop->lang."'
			left join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
				and t2.id=(57-orig_o.available)  and t2.language='".$this->_shop->lang."'
			#join shop_catalogue sc on sa.shop_catalogue_id=sc.id
			#join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa'
				and tShopDesription.field_name='ShopDesription'
				and tShopDesription.language = '".$this->_shop->lang."'
			join offer_name alias on tShopDesription.value=alias.id
			join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopSAAlias.table_name='sa'
				and tShopSAAlias.field_name='ShopSAAlias'
				and tShopSAAlias.language = '".$this->_shop->lang."'
			left join translation tsshipping_plan_free_tr
				on tsshipping_plan_free_tr.language=sa.siteid
				and tsshipping_plan_free_tr.id=orig_o.offer_id
				and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
			join translation tsshipping_plan_id
				on tsshipping_plan_id.language=sa.siteid
				and tsshipping_plan_id.id=orig_o.offer_id
				and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
			join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
			join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
			join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
				where 1 and tShopSAAlias.value<>''
			#and scs.shop_id=".$this->_shop->id."
			and sa.username='".$this->_shop->username."'
			and sa.siteid=".$this->_shop->siteid."
			and o.hidden=0 and IFNULL(sa.old,0)=0
			and sa.id in ($sas)
			order by IFNULL(sa.shopspos_cat*1,0)";
		$ret = $this->_dbr->getAll($q);
//		echo $q.'<br>';die();
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
        }
		foreach ($ret as $k=>$r) {
            $pic = \SavedPic::getPrimary($r->master_sa);

            $ret[$k]->doc_id = isset($pic->doc_id) ? $pic->doc_id : 0;
            $ret[$k]->wdoc_id = isset($pic->wdoc_id) ? $pic->wdoc_id : 0;
            $ret[$k]->cdoc_id = isset($pic->cdoc_id) ? $pic->cdoc_id : 0;

            // Disable SA if we have not main picture
            if ( ! $ret[$k]->doc_id) {
                unset($ret[$k]);
                continue;
            }
            
            $ret[$k]->categories_ids = $this->_dbr->getAssoc("SELECT DISTINCT sa.shop_catalogue_id cat_id, scs1.pic_color cat_color
                FROM sa{$this->_shop->id} sa
                JOIN shop_catalogue sc1 ON sc1.id=sa.shop_catalogue_id
                JOIN shop_catalogue_shop scs1 ON sc1.id=scs1.shop_catalogue_id
                WHERE sc1.hidden=0 AND scs1.hidden=0 AND scs1.shop_id={$this->_shop->id} AND sa.id={$r->saved_id}");

            $shop_pic_color = false;
            foreach ($ret[$k]->categories_ids as $color) {
                if ($shop_pic_color === false) {
                    $shop_pic_color = $color;
                } elseif ($shop_pic_color != $color) {
                    $shop_pic_color = $this->_shop->shop_pic_color;
                    break;
                } else {
                    $shop_pic_color = $color;
                }
            }
            $ret[$k]->shop_pic_color = $shop_pic_color;

            if ($shop_pic_color == 'color') {
                $ret[$k]->color_type = 'color';
            } else {
                $ret[$k]->color_type = 'whitesh';
            }

			$ret[$k]->alt_def = str_replace('"',"'",$ret[$k]->ShopDescription).'_'.$ret[$k]->doc_id;
			$ret[$k]->title_def = str_replace('"',"'",$ret[$k]->ShopDescription).'_'.$ret[$k]->doc_id;
			$route = $this->getAllNodes($r->cat_id);
			$route = array_reverse($route);
			$ret[$k]->cat_route = '/';
			foreach($route as $cat) {
				$ret[$k]->cat = $this->_dbr->getOne("
					SELECT `value`
					FROM translation
					WHERE table_name = 'shop_catalogue'
					AND field_name = 'name'
					AND language = '{$this->_shop->lang}'
					AND id = ".$cat."
					");
			}
//			echo '<br>'.$ret[$k]->cat_route; print_r($route);
		}
        return $ret;
    }

    function searchContents($key, $page=1, $limit=0)
    {
		$key = str_replace('\"','"',$key);
		$keys = explode('"',$key);
		$phrases = array();
		for($i=1;$i<sizeof($keys);$i+=2){
			if (trim($keys[$i])) {
				$phrases[$i]=$keys[$i];
				$key = str_replace('"'.$keys[$i].'"', '$$phrase'.$i.'$$', $key);
			}
		}
		$key = str_replace('+',' +',$key);
		$key = str_replace('$$$$','$$ $$',$key);
		while (strpos($key,'  ')!==false) $key = str_replace('  ',' ',$key);
		$vars = explode(' ', $key);
		$conds = array();
		if ($this->_shop->search_results_text) $limit=$this->_shop->search_results_text;
		else $limit = 100;
		foreach ($vars as $var) {
			if (substr($var, 0, 1)=='+') {
				$var = trim(ltrim($var,'+'));
				$operator = 'AND';
			} else {
				$var = trim($var);
				$operator = 'OR';
			}
			if (substr($var, 0, 8)=='$$phrase') {
				$id = (int)str_replace('phrase','',str_replace('$$','',$var));
				$key = $phrases[$id];
//				$key = str_replace('$$','',$var);
			} else {
				$key = $var;
			}
			if (!sizeof($conds)) $operator = '';
/*			if ($operator=='AND') {
				$conds[] = " $operator (UPPER(t1.value) like UPPER('%$key%') ";
				$conds[] = " UPPER(t2.value) like UPPER('%$key%')) ";
			} else */{
				$conds[] = " UPPER(t1.value) like UPPER('%$key%') ";
				$conds[] = " UPPER(t2.value) like UPPER('%$key%') ";
				$conds[] = " UPPER(t4.value) like UPPER('%$key%') ";
				$rels[] = "IF(UPPER(t4.value) like UPPER('%$key%'),1,0)";
			}
		}
		$cond = implode($conds);
		$rel = implode('+',$rels);

		$qs = array();
		foreach($conds as $c) {
			$qs[] = "select distinct sc.id, t2.value title_translation, t4.value title_menu_translation, t2.language, t1.value html_translation, t3.value alias
			, $rel as relevancy
			from shop_content sc
			join shop_content_shop scs on sc.id=scs.shop_content_id
			join translation t1 on t1.table_name='shop_content' and t1.field_name='html' and t1.id=sc.id and t1.language = '".$this->_shop->lang."'
			join translation t2 on t2.table_name='shop_content' and t2.field_name='title' and t2.id=sc.id and t1.language=t2.language
			join translation t4 on t4.table_name='shop_content' and t4.field_name='title_menu' and t4.id=sc.id and t1.language=t4.language
			join translation t3 on t3.table_name='shop_content' and t3.field_name='alias' and t3.id=sc.id and t1.language=t3.language
			where t3.value<>'' and scs.shop_id=".$this->_shop->id." and sc.inactive=0 and scs.inactive=0
				and $c
			";
		}
		$q = "select SQL_CALC_FOUND_ROWS * from (".implode(' union ', $qs)
			.") ttt order by relevancy desc
			limit ".(($page-1)*$limit).", $limit"
			;
//		echo nl2br($q);
		$ret = $this->_dbr->getAll($q);
		$sizeofresults = $this->_dbr->getOne("SELECT FOUND_ROWS()");
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
            die($q);
        }
		foreach($ret as $k=>$r){
			$ret[$k]->q = $q;
			$ret[$k]->sizeofresults = $sizeofresults;
			$ret[$k]->html_translation = substr(strip_tags($ret[$k]->html_translation),0,360).' ... ';
		}
        return $ret;
    }

    function searchDefs($key)
    {
		$key = str_replace('\"','"',$key);
		$keys = explode('"',$key);
		$phrases = array();
		for($i=1;$i<sizeof($keys);$i+=2){
			if (trim($keys[$i])) {
				$phrases[$i]=$keys[$i];
				$key = str_replace('"'.$keys[$i].'"', '$$phrase'.$i.'$$', $key);
			}
		}
		$key = str_replace('+',' +',$key);
		$key = str_replace('$$$$','$$ $$',$key);
		while (strpos($key,'  ')!==false) $key = str_replace('  ',' ',$key);
		$vars = explode(' ', $key);
		$conds = array();
		foreach ($vars as $var) {
			if (substr($var, 0, 1)=='+') {
				$var = trim(ltrim($var,'+'));
				$operator = 'AND';
			} else {
				$var = trim($var);
				$operator = 'OR';
			}
			if (substr($var, 0, 8)=='$$phrase') {
				$id = (int)str_replace('phrase','',str_replace('$$','',$var));
				$key = $phrases[$id];
//				$key = str_replace('$$','',$var);
			} else {
				$key = $var;
			}
			if (!sizeof($conds)) $operator = '';
			$conds[] = " $operator (UPPER(t1.value) like UPPER('%" . mysql_real_escape_string($key) . "%') or UPPER(t2.value) like UPPER('%" . mysql_real_escape_string($key) . "%')) ";
		}
		$cond = implode($conds);

		$q = "select distinct sc.id, t1.value keyword_translation, t2.language, t2.value text_translation
			, t3.value url
			from shop_search_defs sc
			join translation t1 on t1.table_name='shop_search_defs' and t1.field_name='keyword' and t1.id=sc.id and t1.language = '".$this->_shop->lang."'
			join translation t2 on t2.table_name='shop_search_defs' and t2.field_name='text' and t2.id=sc.id and t1.language=t2.language
			left join translation t3 on t3.table_name='shop_search_defs' and t3.field_name='url' and t3.id=sc.id and t1.language=t3.language
			where sc.shop_id=".$this->_shop->id." and sc.inactive=0
				and $cond";
//		echo $q;
		$ret = $this->_dbr->getAll($q);
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
            die();
        }
		foreach($ret as $k=>$r){
			$ret[$k]->fulltext_translation = $ret[$k]->text_translation;
			if (strlen(strip_tags($ret[$k]->text_translation))>360)
				$ret[$k]->text_translation = substr(strip_tags($ret[$k]->text_translation),0,360).' ... ';
			else
				$ret[$k]->text_translation = strip_tags($ret[$k]->text_translation);
		}
        return $ret;
    }

    function frontOffers($cached = 1)
    {
		if ($this->_shop->leafs_only) return;
		$function = "frontOffers()";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($cached && $chached_ret) {
			return $chached_ret;
		}
        
		$q = "
			select /*frontOffers*/ distinct sa.mi_id, sa.id, o.offer_id, sa.id as saved_id, IFNULL(master_sa.id,sa.id) ifnullmastersa
			, (select max(par_value*1)
				from saved_params sp
				where sp.par_key like 'shop_catalogue_id%'
				and saved_id=sa.id
				/*order by id desc limit 1*/) cat_id
			, alias.name ShopDescription
			, sa.shopspos
			, sa.ShopHPrice  $this->_fake_free_shipping as ShopHPrice
			, sa.ShopMinusPercent
			, sa.ShopShortDescription
			, sa.ShopPrice  $this->_fake_free_shipping as ShopPrice
			, alias.name alias
			, tShopSAAlias.value ShopSAAlias
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='alt' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) alt
			, (select translation.value from translation
					join saved_doc on saved_doc.doc_id=translation.id
							where table_name='saved_doc' and field_name='title' and translation.language='".$this->_shop->lang."'
							and saved_doc.saved_id=IFNULL(master_sa.id, sa.id) order by saved_doc.primary desc limit 1) title
			, o.name offer_name
			, IF(orig_o.available, concat(t1.value,' ',SUBSTRING_INDEX(t2.value,' ',1)), '') available
			from sa".$this->_shop->id." sa
			left join sa_all master_sa on sa.master_sa=master_sa.id
			join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
			left join offer orig_o on orig_o.offer_id=sa.offer_id
			join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
				and t1.id=47 and t1.language='".$this->_shop->lang."'
			left join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
				and t2.id=(57-orig_o.available)  and t2.language='".$this->_shop->lang."'
			#join shop_catalogue sc on sa.shop_catalogue_id=sc.id
			#join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa'
				and tShopDesription.field_name='ShopDesription'
				and tShopDesription.language = '".$this->_shop->lang."'
			join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
				and tShopSAAlias.table_name='sa'
				and tShopSAAlias.field_name='ShopSAAlias'
				and tShopSAAlias.language = '".$this->_shop->lang."'
			join offer_name alias on tShopDesription.value=alias.id
			left join translation tsshipping_plan_free_tr
				on tsshipping_plan_free_tr.language=sa.siteid
				and tsshipping_plan_free_tr.id=orig_o.offer_id
				and tsshipping_plan_free_tr.table_name='offer' and tsshipping_plan_free_tr.field_name='sshipping_plan_free_tr'
			join translation tsshipping_plan_id
				on tsshipping_plan_id.language=sa.siteid
				and tsshipping_plan_id.id=orig_o.offer_id
				and tsshipping_plan_id.table_name='offer' and tsshipping_plan_id.field_name='sshipping_plan_id'
			join shipping_plan_country spc on spc.shipping_plan_id=tsshipping_plan_id.value
			join config_api_values cav on cav.par_id=5 and cav.value=sa.siteid
			join country c on c.code=spc.country_code and c.name=REPLACE(cav.description,'United Kingdom','UK')
				where 1 and tShopSAAlias.value<>''
			#and scs.shop_id=".$this->_shop->id."
			and sa.username='".$this->_shop->username."'
			and sa.siteid=".$this->_shop->siteid."
			and o.hidden=0 and IFNULL(sa.old,0)=0
			and sa.id>0
			and sa.shops=".$this->_shop->id."
			order by IFNULL(sa.shopspos*1,0)";
		$ret = $this->_dbr->getAll($q);
       	if (PEAR::isError($ret)) {
            aprint_r($ret);
            die();
        }
		foreach ($ret as $k=>$r) {
            $pic = \SavedPic::getPrimary($r->ifnullmastersa);

            $ret[$k]->doc_id = isset($pic->doc_id) ? $pic->doc_id : 0;
            $ret[$k]->wdoc_id = isset($pic->wdoc_id) ? $pic->wdoc_id : 0;
            $ret[$k]->cdoc_id = isset($pic->cdoc_id) ? $pic->cdoc_id : 0;

            // Disable SA if we have not main picture
            if ( ! $ret[$k]->doc_id) {
                unset($ret[$k]);
                continue;
            }
            
            $ret[$k]->categories_ids = $this->_dbr->getAssoc("SELECT DISTINCT sa.shop_catalogue_id cat_id, scs1.pic_color cat_color
                FROM sa{$this->_shop->id} sa
                JOIN shop_catalogue sc1 ON sc1.id=sa.shop_catalogue_id
                JOIN shop_catalogue_shop scs1 ON sc1.id=scs1.shop_catalogue_id
                WHERE sc1.hidden=0 AND scs1.hidden=0 AND scs1.shop_id={$this->_shop->id} AND sa.id={$r->saved_id}");

            $shop_pic_color = false;
            foreach ($ret[$k]->categories_ids as $color) {
                if ($shop_pic_color === false) {
                    $shop_pic_color = $color;
                } elseif ($shop_pic_color != $color) {
                    $shop_pic_color = $this->_shop->shop_pic_color;
                    break;
                } else {
                    $shop_pic_color = $color;
                }
            }
            $ret[$k]->shop_pic_color = $shop_pic_color;

            if ($shop_pic_color == 'color') {
                $ret[$k]->color_type = 'color';
            } else {
                $ret[$k]->color_type = 'whitesh';
            }            

			$add_rec = $this->_dbr->getRow("
				select IF(
					NOT tinactivedescriptionShop1.value
					,tdescriptionShop1.value,
					IF(
					NOT tinactivedescriptionShop2.value
					,tdescriptionShop2.value,
					IF(
					NOT tinactivedescriptionShop3.value
					,tdescriptionShop3.value,
					NULL))) description
				from saved_auctions sa
			left join translation tinactivedescriptionShop1 on tinactivedescriptionShop1.id=sa.id
				and tinactivedescriptionShop1.table_name='sa'
				and tinactivedescriptionShop1.field_name='inactivedescriptionShop1'
				and tinactivedescriptionShop1.language = '".$this->_shop->lang."'
			left join translation tdescriptionShop1 on tdescriptionShop1.id=sa.id
				and tdescriptionShop1.table_name='sa'
				and tdescriptionShop1.field_name='descriptionShop1'
				and tdescriptionShop1.language = '".$this->_shop->lang."'
			left join translation tinactivedescriptionShop2 on tinactivedescriptionShop2.id=sa.id
				and tinactivedescriptionShop2.table_name='sa'
				and tinactivedescriptionShop2.field_name='inactivedescriptionShop2'
				and tinactivedescriptionShop2.language = '".$this->_shop->lang."'
			left join translation tdescriptionShop2 on tdescriptionShop2.id=sa.id
				and tdescriptionShop2.table_name='sa'
				and tdescriptionShop2.field_name='descriptionShop2'
				and tdescriptionShop2.language = '".$this->_shop->lang."'
			left join translation tinactivedescriptionShop3 on tinactivedescriptionShop3.id=sa.id
				and tinactivedescriptionShop3.table_name='sa'
				and tinactivedescriptionShop3.field_name='inactivedescriptionShop3'
				and tinactivedescriptionShop3.language = '".$this->_shop->lang."'
			left join translation tdescriptionShop3 on tdescriptionShop3.id=sa.id
				and tdescriptionShop3.table_name='sa'
				and tdescriptionShop3.field_name='descriptionShop3'
				and tdescriptionShop3.language = '".$this->_shop->lang."'
			where sa.id={$r->ifnullmastersa}
			");
			$ret[$k]->description = $add_rec->description;
			if ($this->_seller->shop_pic_color == 'white' && (int)$r->doc_id_w)
				$ret[$k]->doc_id = (int)$r->wdoc_id;
			else
				$ret[$k]->doc_id = (int)$r->cdoc_id;
			$ret[$k]->rating_statistic = $this->getRating($r->saved_id);
			$ret[$k]->alt_def = str_replace('"',"'",$ret[$k]->ShopDescription).'_'.$ret[$k]->doc_id;
			$ret[$k]->title_def = str_replace('"',"'",$ret[$k]->ShopDescription).'_'.$ret[$k]->doc_id;
			$route = $this->getAllNodes($r->cat_id);
			$route = array_reverse($route);
			$ret[$k]->cat_route = '/';
			foreach($route as $cat) {
				$ret[$k]->cat = $this->_dbr->getOne("
					SELECT `value`
					FROM translation
					WHERE table_name = 'shop_catalogue'
					AND field_name = 'name'
					AND language = '{$this->_shop->lang}'
					AND id = ".$cat."
					");
			}
//			echo '<br>'.$ret[$k]->ShopDescription.' cat='.$r->cat_id.': '.$ret[$k]->cat_route; print_r($route);
		}
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        return $ret;
    }

    function getChildren($shop_catalogue_id)
    {
		$ret = $this->_dbr->getAll("select sc.* from shop_catalogue sc
			join shop_catalogue_shop scs on scs.shop_catalogue_id=sc.id
			where scs.shop_id=".$this->_shop->id."
			and sc.parent_id=$shop_catalogue_id
			ORDER BY sc.ordering");
        return $ret;
    }

    function recount($parent_id=0, $level=0)
    {
			$q = "delete from shop_cache where shop_id={$this->_shop->id} and `function` like 'listAllLeftMenu%'";
			$this->_db->query($q);
			$q = "update shop_catalogue_shop set offercount=
				(select count(*) from sa{$this->_shop->id} where shop_catalogue_id=shop_catalogue_shop.shop_catalogue_id)
				where shop_id={$this->_shop->id}";
			$this->_db->query($q);
		return;
/*		if (!$parent_id) {
			$q = "update shop_catalogue set offercount=0
			where id in (select shop_catalogue_id from shop_catalogue_shop where shop_id={$this->_shop->id})";
//			echo "$q<br>";
			$this->_db->query($q);
		}
		$q = "SELECT id FROM shop_catalogue where parent_id=$parent_id
				and id in (select shop_catalogue_id from shop_catalogue_shop where shop_id={$this->_shop->id})";
        $r = $this->_dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		$allcount = 0;
		if (count($r)) {
			foreach($r as $key=>$rec){
				$count = $this->recount($rec->id, $level+1);
//				$allcount += $count;
//				echo $rec->id.": $count<br>";
				$q = "update shop_catalogue set offercount=$count where id=".$rec->id;
				$r = $this->_db->query($q);
			}
			$q = "SELECT count(offer.offer_id)
	      FROM offer left join sa{$this->_shop->id} sa1 on sa1.offer_id=offer.offer_id
			where 1=1  AND NOT offer.hidden  AND offer.old=0
			AND sa1.shop_catalogue_id in ($parent_id)";
			return $allcount+(int)$this->_dbr->getOne($q);
		} else {
			$q = "SELECT count(offer.offer_id)
	      FROM offer left join sa{$this->_shop->id} sa1 on sa1.offer_id=offer.offer_id
			where 1=1  AND NOT offer.hidden  AND offer.old=0
			AND sa1.shop_catalogue_id in ($parent_id)";
			return (int)$this->_dbr->getOne($q);
		}			*/
    }
    /**
     * Add offer to wishlist
     * @param int $offer_id
     * @param string $varname
     * $return array $shop_cart
     */
    function addWish($offer_id, $varname='shop_wish')
    {
		$shop_cart = unserialize($_COOKIE[$varname]);
		if (is_array($offer_id)) 
        {
			foreach($offer_id as $id) 
            {
				$shop_cart[] = (int)$id;
			}
		} 
        else if ((int)$offer_id) 
        {
			$shop_cart[] = (int)$offer_id;
        }
        
        $shop_cart = array_unique($shop_cart);
        setcookie($varname, serialize($shop_cart), time()+24*60*60, '/');

        $this->saveTempWish($shop_cart);

		return $shop_cart;
    }
    /**
     * Makes wishlist public
     * @return string 
     */
    function publishWishList() {
        $hash = md5(date('Y-m-d H:i:s'));
        $this->_db->query("INSERT INTO `wishlist` SET 
            `shop_id` = " . $this->_shop->id . ",
            `hash` = '$hash',
            `created` = NOW()");
        $wishlist_id = (int)$this->_db->getOne('select LAST_INSERT_ID()');

        $wishes = $this->getWish();
        foreach ($wishes as $wish) {
            $this->_db->query("INSERT INTO `wishlist_saved` SET 
                `wishlist_id` = $wishlist_id
                , `saved_id` = $wish");
        }
        
        return $hash;
    }
    /**
     * Get offer's articles list with default quantity. Used in SA-to-cart adding
     * @param Offer $offer_id
     * @return array
     */
    function getOfferInput(Offer $offer) 
    {
        $groups = Group::listAll(
            $this->_db, 
            $this->_dbr, 
            $offer, 
            $this->_seller->get("defshcountry"), 
            $this->_shop->lang, 
            $this->_shop->siteid
        );
        
        $input = [];
        $quantity = 1;
        
        foreach ($groups as $i => $group) {
            foreach ($group->articles as $j => $article) {
                if ($article->inactive) continue;
                $input[$article->article_list_id] = isset($input[$article->article_list_id])
                    ? $input[$article->article_list_id] : $article->default_quantity * $quantity;
            }
        }
        
        return $input;
    }

    function addCart($offer_id, $input, $varname='shop_cart')
    {
		$wishlist = $_COOKIE['shop_wish'];
		$shop_cart = unserialize($_COOKIE[$varname]);
		foreach($shop_cart as $k=>$cart) {
			foreach($cart as $cart_offer_id=>$dummy) {
				if ($cart_offer_id==$offer_id) {
					unset($shop_cart[$k]);
				}
			}
		}
		if ((int)$offer_id) {
			foreach ($input as $article_list_id=>$quantity) {
				if (!(int)$quantity) {
					unset($input[$article_list_id]);
				}
			}
			$offer = array($offer_id=>$input);
			$shop_cart[] = $offer;
			if ($varname!="shop_wish") {
				setcookie("shop_wish", $wishlist, 0/*time()+3600*24*720*/, '/');
			}
            $new_cart = serialize($shop_cart);
            $_COOKIE[$varname] = $new_cart;
			$res = setcookie($varname, $new_cart, time()+24*60*60, '/');
        }
		return $shop_cart;
    }

    function setCart($cart, $varname='shop_cart')
    {
		global $cart_array;
		$curr_cart = $cart_array; //$this->getCart($varname);
//		die(serialize($cart->offers));
		if (!is_array($curr_cart->offers) || !count($curr_cart->offers))
			setcookie($varname, serialize($cart->offers), time()+24*60*60, '/');
	}
    function setWish($cart, $varname='shop_wish')
    {
		$curr_cart = $this->getWish($varname);
		if (!is_array($curr_cart) || !count($curr_cart))
			setcookie($varname, serialize($cart), time()+24*60*60, '/');
	}


    function getCart($varname='shop_cart', $complete=0)
    {
		global $debug, $loggedCustomer;
		$time = getmicrotime();
		$offers = unserialize($_COOKIE[$varname]);
		$total = 0;
		$offers_objects = array();
		$articles_objects = array();
		$materials_objects = array();
        $free_sas = [];
		$materials_count = 0;
		$mulang_fields = array('name'
							   ,'description'
							   ,'shipping_plan_id'
							   ,'high_price'
							   ,'add_item_cost'
							   );
		$Charge_shipping_array = array();
		$shipping_array = array();
        
        if (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) {
            $promo_code = $_POST['promo_code'];
        } elseif (isset($_COOKIE['shop_cart_promo']) && !empty($_COOKIE['shop_cart_promo'])) {
            $promo_code = $_COOKIE['shop_cart_promo'];
        } else {
           $promo_code = false;
        }
        
        if ($promo_code) {
            $promo = $this->getVoucher($promo_code, $loggedCustomer);
            foreach ($promo->free_articles_sa as $free_sa) {
                $free_sas[] = $free_sa->saved_id;
            }
        }
        
		if ($complete) {
			foreach ($offers as $key => $shop_offer) {
				foreach ($shop_offer as $saved_id => $input) {
					$offer = $this->getOffer($saved_id);
                    if ( ! $offer || ! isset($offer->ShopPrice)) {
                        unset($offers[$key][$saved_id]);
                        continue;
                    }
                    
					$master_saved_id = $this->_dbr->getOne("select IFNULL(master_sa.id,sa.id)
						from sa".$this->_shop->id." sa
						left join sa_all master_sa on sa.master_sa=master_sa.id
						where sa.id=$saved_id");
					$offer_id = $this->_dbr->getOne("select IFNULL(master_sa.offer_id,sa.offer_id)
						from sa".$this->_shop->id." sa
						left join sa_all master_sa on sa.master_sa=master_sa.id
						where sa.id=$saved_id");
					$name_id = $this->_dbr->getOne("
						select tShopDesription.`value`
							from sa".$this->_shop->id." sa
								left join sa_all master_sa on sa.master_sa=master_sa.id
								join translation tShopDesription on tShopDesription.id=IF(IFNULL(sa.master_ShopDesription,1),IFNULL(master_sa.id, sa.id), sa.id)
							where 1
							and sa.id = $saved_id
							and tShopDesription.table_name='sa'
							and tShopDesription.field_name='ShopDesription'
							and tShopDesription.language = '".$this->_shop->lang."'");
					$offers_objects[$key] = new Offer($this->_db, $this->_dbr, $offer->offer_id, $this->_shop->lang);
					$offers_objects[$key]->getAvailableText();
					$offers_objects[$key]->data->doc_id = $this->_dbr->getOne("select doc_id from saved_pic where saved_id=$master_saved_id order by `primary` desc limit 1");
                    $offers_objects[$key]->banner = $this->loadBanner($master_saved_id);
                    $offers_objects[$key]->color_type = $offer->color_type;
					if ($this->mobile)
					{
						$offers_objects[$key]->rating = $this->getOfferRating($saved_id);
						$offers_objects[$key]->ShopHPrice = $offer->ShopHPrice;
						$offers_objects[$key]->ShopMinusPercent = $offer->ShopMinusPercent;
					}
					$input_prices = array();
					$shipping = 0;
					foreach ($input as $article_list_id => $quantity) {
						$al_rec = $this->_dbr->getRow("select article_list.*
							from article_list
							where article_list_id=$article_list_id");
						$article_id = $al_rec->article_id;
                        $time = getmicrotime();
						$articles_objects[$article_list_id] = new Article($this->_db, $this->_dbr, $article_id, -1, 0, $this->_shop->lang);
						
						foreach ($articles_objects[$article_list_id]->materials as $material) {
							if ($material->hidePacking) {
								$materials_objects[] = new Article($this->_db, $this->_dbr, $material->article_id, -1, 0, $this->_shop->lang);
								$materials_objects[$materials_count]->quantity = $quantity * $material->shm_quantity;
								$materials_objects[$materials_count]->data->pic_id = substr($materials_objects[$materials_count]->data->picture_URL, strpos($materials_objects[$materials_count]->data->picture_URL,'picid_')+6);
								$materials_objects[$materials_count]->data->pic_id = explode('_',$materials_objects[$materials_count]->data->pic_id);
								$materials_objects[$materials_count]->data->wpic_id = $materials_objects[$materials_count]->data->pic_id[0];
								$materials_count++;
							}
						}

						$alias_id = $al_rec->alias_id;
						$group = $this->_dbr->getRow("select og.*, offer.condensed
							from offer_group og
							join offer on offer.offer_id=og.offer_id
							join article_list al on al.group_id = og.offer_group_id
							where al.article_list_id=$article_list_id");
						if ($group->condensed && !$group->additional) {
							$offers_objects[$key]->weight += round($articles_objects[$article_list_id]->data->weight);
						}

                        if ($group->condensed && $group->main) {
							$price = $offer->ShopPrice;
						} else {
							$price = Offer::getShopPrice($this->_db, $this->_dbr, $saved_id, $article_list_id
                                , $this->_shop->lang, $this->_shop->siteid);
                            
                            if ($price === false) {
                                unset($offers[$key][$saved_id][$article_list_id]);
                                continue;
                            }
                            
							if ($debug) {echo 'NOT condensed price='.$price.'<br>';}
							if ($group->main) {
								$price += $offer->fake_free_shipping;
								$offer->fake_free_shipping = 0;
							}
						}
                        
                        if (in_array($saved_id, $free_sas)) {
                            $articles_objects[$article_list_id]->_original_price = $price;
                            $price = 0;
                        }
                        
						$articles_objects[$article_list_id]->price = $price;
						$articles_objects[$article_list_id]->default_quantity = $al_rec->default_quantity;
						if (strpos($articles_objects[$article_list_id]->data->picture_URL,'pic_id=')) {
							list($dummy, $articles_objects[$article_list_id]->data->pic_id) = explode('pic_id=',$articles_objects[$article_list_id]->data->picture_URL);
						} else {
							$articles_objects[$article_list_id]->data->pic_id = substr($articles_objects[$article_list_id]->data->picture_URL
								, strpos($articles_objects[$article_list_id]->data->picture_URL,'picid_')+6);
							list($articles_objects[$article_list_id]->data->pic_id,$dummy) = explode('_',$articles_objects[$article_list_id]->data->pic_id);
						}
                        if (strpos($articles_objects[$article_list_id]->data->wpicture_URL,'wpic_id=')) {
                            list($dummy, $articles_objects[$article_list_id]->data->wpic_id) = explode('wpic_id=',$articles_objects[$article_list_id]->data->wpicture_URL);
                        } else {
                            $articles_objects[$article_list_id]->data->wpic_id = substr($articles_objects[$article_list_id]->data->wpicture_URL
                                , strpos($articles_objects[$article_list_id]->data->wpicture_URL,'picid_')+6);
                            list($articles_objects[$article_list_id]->data->wpic_id,$dummy) = explode('_',$articles_objects[$article_list_id]->data->wpic_id);
                        }
                        $articles_objects[$article_list_id]->group = $this->_dbr->getRow("select * from offer_group
							where offer_group_id=(select group_id from article_list where article_list_id=$article_list_id)");
                        
						$input_prices[$article_list_id] = $articles_objects[$article_list_id]->price;
						$total+=$input_prices[$article_list_id]*$quantity;
						foreach ($mulang_fields as $fld) {
							$articles_objects[$article_list_id]->data->$fld =
							  $this->_dbr->getOne("select value from translation where id='$article_id'
									       and table_name='article' and field_name='$fld' and language in
										   ('".$this->_shop->lang."','".$this->_shop->siteid."')");
						}
						if ((int)$alias_id) {
							$articles_objects[$article_list_id]->data->name = $this->_dbr->getOne("select value from translation
											where id='$alias_id'
									       and table_name='article_alias' and field_name='name' and language in
										   ('".$this->_shop->lang."','".$this->_shop->siteid."')");
						}
						// shipping cost
						$all_group_id = (int)$this->_dbr->getOne("select group_id from article_list where article_list_id=$article_list_id");
						$base_group_id = (int)$this->_dbr->getOne("select offer_group_id from offer_group
							where base_group_id=$all_group_id");
						if ($base_group_id) $all_group_id = $base_group_id;
						$q = "
							SELECT IF(".(int)$this->_seller->data->free_shipping_total."
									or o.sshipping_plan_free or IFNULL(t_o.value,0), 0,
								IF(og.main, IFNULL(spco.shipping_cost, 0), 0)
								) as shipping_cost
							FROM offer_group og
							LEFT JOIN offer o ON og.offer_id = o.offer_id
							LEFT JOIN translation t_o ON t_o.id = o.offer_id
								and t_o.table_name='offer' and t_o.field_name='sshipping_plan_free_tr'
								and t_o.language='".$this->_shop->siteid."'
							LEFT JOIN shipping_plan_country spco ON (
											     (SELECT value
											     FROM translation
											     WHERE table_name = 'offer'
											     AND field_name = 'sshipping_plan_id'
											     AND language = '".$this->_shop->siteid."'
											     AND id = og.offer_id))
											=spco.shipping_plan_id
											and spco.country_code='".$this->_seller->data->defshcountry."'
							WHERE 1
							and og.offer_group_id=$all_group_id";
	//					if ($debug) echo $q;
//						echo "q2: $q<br />";//die();

						$art_shipping = $this->_dbr->getOne($q);
						if ($group->condensed && $group->main) {
							$art_shipping -= $offer->fake_free_shipping;
						}
						$shipping += $quantity * $art_shipping;
//						echo $article_list_id.': '.$quantity.' * '.$art_shipping.'<br>';
					}
					$offers_objects[$key]->input_prices = $input_prices;
					foreach($input_prices as $k=>$r) $offers_objects[$key]->total_price += $input_prices[$k]*$input[$k];
					$q = "select name from offer_name where deleted=0 and id='$name_id'";
					$offers_objects[$key]->alias = $this->_dbr->getOne($q);
					$q = "SELECT * FROM `saved_pic` 
                            WHERE `saved_id` = '$master_saved_id'
                            ORDER BY `img_type`, `ordering` ASC LIMIT 1";
					$doc = $this->_dbr->getRow($q);
					$offers_objects[$key]->doc_id = $doc->doc_id;
					$offers_objects[$key]->doc = $doc;
					$q = "SELECT
					translation.`value`
							from sa".$this->_shop->id." sa
								left join sa_all master_sa on sa.master_sa=master_sa.id
								join translation on translation.id=IFNULL(master_sa.id,sa.id)
						WHERE 1
						and sa.id = $saved_id
						AND translation.table_name = 'sa'
						AND translation.field_name = 'ShopSAAlias'
						AND translation.language = '".$this->_shop->lang."'";
					$offers_objects[$key]->data->ShopSAAlias = $this->_dbr->getOne($q);
					$q = "select sa.ShopShippingCharge
						from sa".$this->_shop->id." sa
						where sa.id=".(int)$saved_id;
					$ShopShippingCharge = $this->_dbr->getOne($q);
					if ($ShopShippingCharge) {
						$Charge_shipping_array[] = $shipping;
					} else {
						$shipping_array[] = $shipping;
					}
					if ((int)$this->_seller->data->free_shipping && $total > (int)$this->_seller->data->free_shipping_above) {
						$shipping_array = array(); $Charge_shipping_array = array();
					}
				} // foreach offer
			} // foreach SA in cart
		} // if complete

		$shop_cart = new stdClass;
		$shop_cart->offers = $offers;
		$shop_cart->offers_objects = $offers_objects;
		$shop_cart->articles_objects = $articles_objects;
		$shop_cart->materials_objects = $materials_objects;
		$shop_cart->total = $total;
		$shop_cart->shipping_cost = array_sum($Charge_shipping_array)+max($shipping_array);
		if ($complete) {
			foreach ($shop_cart->offers as $k=>$rr) foreach ($rr as $sa_id=>$r){
				$sa = $this->_dbr->getRow("
					select distinct tShopSAAlias.value ShopSAAlias
					, (select sp.par_value from saved_params sp
					where sp.par_key like 'shop_catalogue_id[".$this->_shop->id."]'
					and saved_id=$sa_id
					order by id desc limit 1) cat_id
				from sa".$this->_shop->id." sa
				left join sa_all master_sa on sa.master_sa=master_sa.id
				join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
					and tShopSAAlias.table_name='sa'
					and tShopSAAlias.field_name='ShopSAAlias'
					and tShopSAAlias.language = '".$this->_shop->lang."'
				where sa.id=$sa_id
				");
	//			print_r($route);
				$shop_cart->offers_objects[$k]->ShopSAAlias = $sa->ShopSAAlias;
				$shop_cart->offers_objects[$k]->cat_route = '/';
			}
		} // if completed

        return $shop_cart;
    }
    /**
     * Get wishlist
     * @param string $varname
     * @return array $shop_cart
     */
    function getWish($varname='shop_wish')
    {
        global $loggedCustomer;
        if ($loggedCustomer) {
            $shop_cart = $this->_dbr->getCol("SELECT `saved_id` FROM `customer_cart`
                WHERE `shop_id` = " . $this->_shop->id . "
                AND `customer_id` = " . $loggedCustomer->id . "
                AND `cart_wish` = '$varname'");
        } else {
            $shop_cart = unserialize($_COOKIE[$varname]);
        }

		return $shop_cart;
    }
    /**
     * Get public wishlist
     * @return array $shop_cart
     */
    function getPublicWish($wishlist_hash)
    {
        $wishlist_hash = mysql_escape_string($wishlist_hash);
        $shop_cart = $this->_dbr->getCol("SELECT `wishlist_saved`.`saved_id` 
            FROM `wishlist`
            JOIN `wishlist_saved` ON `wishlist_saved`.`wishlist_id` = `wishlist`.`id`
            WHERE `wishlist`.`shop_id` = " . $this->_shop->id . "
            AND `wishlist`.`hash` = '$wishlist_hash'");
		return $shop_cart;
    }

    function saveTempCart($loggedCustomer, $varname='shop_cart')
    {
		$offers = unserialize($_COOKIE[$varname]);
//		print_r($offers); die();
		foreach ($offers as $key => $shop_offer) {
			foreach ($shop_offer as $saved_id => $input) {
				foreach ($input as $article_list_id => $quantity) {
					$r = $this->_db->query("insert into customer_cart set
						shop_id=".$this->_shop->id.",
						customer_id=".$loggedCustomer->id.",
            			`saved_id` = $saved_id,
            			`article_list_id` = $article_list_id,
			            `quantity` = $quantity,
						add_datetime=NOW(),
						cart_wish='$varname',
						url='".$_SERVER['HTTP_REFERER']."'");
					if (PEAR::isError($r)) {print_r($r); die();}
				}
			}
		}
    }
    /**
     * Save wishlist to DB
     * $param array $offers
     */
    function saveTempWish($offers)
    {
        global $loggedCustomer;
        $varname='shop_wish';

        if ($loggedCustomer) {
            foreach ($offers as $key => $saved_id) {
                $exist = $this->_dbr->getOne("SELECT `id` FROM `customer_cart`
                    WHERE `saved_id` = $saved_id
                    AND `shop_id` = " . $this->_shop->id . "
                    AND `customer_id` = " . $loggedCustomer->id . "
                    AND `cart_wish` = '$varname'
                    LIMIT 1");

                if (!$exist)
                    $r = $this->_db->query("insert into customer_cart set
                        `shop_id` = ".$this->_shop->id.",
                        `customer_id` = ".$loggedCustomer->id.",
                        `saved_id` = $saved_id,
                        `article_list_id` = 0,
                        `quantity` = 0,
                        `add_datetime` = NOW(),
                        `cart_wish` = '$varname',
                        `url` = '".$_SERVER['HTTP_REFERER']."'");
                if (PEAR::isError($r)) {print_r($r); die();}
            }
        }
    }

    /**
     * If customer is logged - delete offer form DB saved whishlist
     * @param int $offer
     */
    function deleteTempWish($offer) {
        global $loggedCustomer;
        $varname='shop_wish';

        if ($loggedCustomer) {
            $this->_db->query("DELETE FROM `customer_cart`
                WHERE `saved_id` = " . (int)$offer . "
                AND `shop_id` = " . $this->_shop->id . "
                AND `customer_id` = " . $loggedCustomer->id . "
                AND `cart_wish` = '$varname'
                LIMIT 1");
        }
    }

    function clearTempCart($loggedCustomer, $varname='shop_cart')
    {
		$last = $this->_dbr->getOne("select max(add_datetime) from customer_cart WHERE shop_id=".(int)$this->_shop->id."
			and cart_wish='$varname' and customer_id=".(int)$loggedCustomer->id);
		return $this->_db->query("DELETE FROM customer_cart WHERE shop_id=".(int)$this->_shop->id."
			and cart_wish='$varname' and customer_id=".(int)$loggedCustomer->id." and add_datetime='$last'");
    }

    function delCart($key, $varname='shop_cart')
    {
		$shop_cart = unserialize($_COOKIE[$varname]);
//		print_r($shop_cart); echo "<br> $key"; die();
		unset($shop_cart[$key]);
		$res = setcookie($varname, serialize($shop_cart), time()+24*60*60, '/');
    }
    
    function isOfferInCart($offer_id) {
    	$shop_cart = unserialize($_COOKIE['shop_cart']);
		if ($offer_id) {
			foreach ($shop_cart as $id=>$offer) {
				foreach ($offer as $cart_offer_id=>$dummy) {
					if ($offer_id==$cart_offer_id) {
						return true;
					}
				}
			}
        }
        return false;
    }

    function delCartOffer($offer_id, $varname='shop_cart')
    {
		$shop_cart = unserialize($_COOKIE[$varname]);
		if ((int)$offer_id) {
			foreach ($shop_cart as $id=>$offer) {
				foreach ($offer as $cart_offer_id=>$dummy) {
					if ($offer_id==$cart_offer_id) {
						unset($shop_cart[$id]);
					}
				}
			}
        }
		$res = setcookie($varname, serialize($shop_cart), time()+24*60*60, '/');
        $_COOKIE[$varname] = serialize($shop_cart);
    }
    /**
     * Delete offer from whishlist
     * @param int $saved_id
     * @param string $varname
     */
    function delWishOffer($saved_id, $varname='shop_wish')
    {
		if ((int)$saved_id) {
			$shop_cart = unserialize($_COOKIE[$varname]);
			foreach ($shop_cart as $id=>$cart_saved_id) {
				if ($saved_id==$cart_saved_id) {
					unset($shop_cart[$id]);
				}
			}
			$res = setcookie($varname, serialize($shop_cart), time()+24*60*60, '/');
            $this->deleteTempWish($saved_id);
        }
    }

    function clearCart($varname='shop_cart')
    {
		setcookie($varname, '', 0, '/');

        global $loggedCustomer;
        if ($loggedCustomer && $varname == 'shop_wish') {
            $this->_db->query("DELETE FROM `customer_cart`
                WHERE `shop_id` = " . $this->_shop->id . "
                AND `customer_id` = " . $loggedCustomer->id . "
                AND `cart_wish` = '$varname'");
        }
    }

    function getMainAuction($loggedCustomer)
    {
		$r = $this->_db->query("DELETE FROM auction WHERE auction_number=0");
		if (PEAR::isError($r)) aprint_r($r);
		$r = $this->_db->query("DELETE FROM orders WHERE auction_number=0");
		if (PEAR::isError($r)) aprint_r($r);
		$auction_number = $this->_db->getOne(
	            "SELECT IFNULL(MAX(auction_number+1), 1) FROM auction
				WHERE txnid in (3) and not IFNULL(custom_auction_number,0)");
		$already_exists = $this->_db->getOne(
	            "SELECT count(*) FROM auction
				WHERE auction_number=$auction_number");
		while ($already_exists) {
			$auction_number++;
			$already_exists = $this->_db->getOne(
		            "SELECT count(*) FROM auction
					WHERE auction_number=$auction_number");
		}
	    $auction = new Auction($this->_db, $this->_dbr);
    	$auction->set('username', $this->_shop->username);
	    $auction->set('auction_number', (int)$auction_number);
	    $auction->set('end_time', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
	    $auction->set('txnid', 3);
	    $auction->set('offer_id', 0);
	    $auction->set('listing_fee', 0);
	    $auction->set('siteid', $this->_shop->siteid);
	    $auction->set('process_stage', STAGE_ORDERED);
	    $auction->set('email', '');
	    $auction->set('winning_bid', 0);
	    $auction->set('status_change', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
		$auction->set('server', $_SERVER['HTTP_HOST']);
	    $auction->set('dontsend_marked_as_Shipped', $this->_seller->get('dontsend_marked_as_Shipped'));
	    $auction->set('quantity', 1);
		if ($loggedCustomer) {
	    	$auction->set('customer_id', $loggedCustomer->id);
		}
	   	$auction->update();
//	    $offer = new Offer($db, $dbr, $auction->get('offer_id'));
//	    if ($offer->get('supervisor_alert') > 0 && $offer->get('supervisor_alert') > requestVar('price')) {
//	   	    standardEmail($db, $dbr, $auction, 'supervisor_alert');
//	    }
		return $auction;
	}

    function getAuction($saved_id)
    {
		$r = $this->_db->query("DELETE FROM auction WHERE auction_number=0");
		if (PEAR::isError($r)) aprint_r($r);
		$r = $this->_db->query("DELETE FROM orders WHERE auction_number=0");
		if (PEAR::isError($r)) aprint_r($r);
		$auction_number = $this->_db->getOne(
	            "SELECT IFNULL(MAX(auction_number+1), 1) FROM auction
				WHERE txnid in (3) and not IFNULL(custom_auction_number,0)");
		$already_exists = $this->_db->getOne(
	            "SELECT count(*) FROM auction
				WHERE auction_number=$auction_number and txnid=3");
		while ($already_exists) {
			$auction_number++;
			$already_exists = $this->_db->getOne(
		            "SELECT count(*) FROM auction
					WHERE auction_number=$auction_number and txnid=3");
		}
        
        $query = "select par_value from saved_params where saved_id = ? and par_key = 'offer_id'";
        $offer_id = $this->_dbr->getOne($query, null, [$saved_id]);        
        
        if ( ! $offer_id) {
            $query = "SELECT `o`.`offer_id`
                from sa{$this->_shop->id} sa
                    left join sa_all master_sa on sa.master_sa=master_sa.id
                    join offer o on o.offer_id=IFNULL(master_sa.offer_id, sa.offer_id)
                WHERE 1
                    and sa.username=?
                    and sa.siteid=?
                    and o.hidden=0 and IFNULL(sa.old,0)=0
                    and sa.id=?
                group by sa.id";
            $offer_id = $this->_dbr->getOne($query, null, [$this->_shop->username, $this->_shop->siteid, $saved_id]);        
        }
        
        $name_id = $this->_dbr->getOne("select value from translation 
            where field_name = 'ShopDesription' and table_name = 'sa'
                and id = ?
                and language = ?
                limit 1", null, [$saved_id, $this->_shop->lang]); 
        
	    $auction = new Auction($this->_db, $this->_dbr);
    	$auction->set('username', $this->_shop->username);
	    $auction->set('auction_number', (int)$auction_number);
	    $auction->set('end_time', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
	    $auction->set('txnid', 3);
	    $auction->set('offer_id', $offer_id);
	    $auction->set('saved_id', $saved_id);
	    $auction->set('name_id', $name_id);
	    $auction->set('listing_fee', 0);
	    $auction->set('siteid', $this->_shop->siteid);
	    $auction->set('process_stage', STAGE_ORDERED);
	    $auction->set('email', '');
	    $auction->set('winning_bid', 0);
	    $auction->set('status_change', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
	    $auction->set('quantity', 1);
	    $auction->set('no_emails', 1);
	    $auction->set('freeze_date', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
	    $auction->set('no_emails_by', 'shop');
	   	$auction->update();
		return $auction;
	}

    function checkPerson(&$personal, &$errors, &$perserror, $same_address, $id=0, $src='')
    {
	$shop_id = $this->_dbr->getOne("select shop_id from customer where id = $id"); 
        
        /**
        * check show or not gender
        * @var $no_gender
        */
        if ($shop_id) {
            $no_gender = $this->_dbr->getOne("select no_gender from shop where id = " . $shop_id);
        } else {
            $no_gender = 0;
        }
           
        $haserrors = false;
			$ask_for_state = $this->_dbr->getOne("select value from config_api where par_id=23 and siteid=".$this->_shop->siteid);
			if ($ask_for_state) {
				$qrystr = "select distinct state f1, state f2 from vat_state
					where country_code='".countryToCountryCode($personal['country_invoice'])."' and now() between date_from and date_to ";
				$states = $this->_dbr->getAssoc($qrystr);
				if (count($states) && $personal['state_invoice']=='') {
                    $errors[] = $this->_shop->english[227];
					$perserror['state'] = 1;
				}
				$qrystr = "select distinct state f1, state f2 from vat_state
					where country_code='".countryToCountryCode($personal['country_shipping'])."' and now() between date_from and date_to ";
				$states = $this->_dbr->getAssoc($qrystr);
				if (count($states) && $personal['state_shipping']=='') {
                    $errors[] = $this->_shop->english[227];
					$perserror['state'] = 1;
				}
			}
                $fieldsreq = array( 'gender', 'firstname', 'name', 'street', 'zip', 'city', 'country', 'email');
                $fields = array('company', 'gender', 'firstname', 'name', 'street', 'house', 'zip', 'city', 'country', 'state', 'email', 'tel', 'cel', 'tel_country_code', 'cel_country_code');
                if (!strlen($personal['gender_invoice']) && !$no_gender) {
                    $errors[] = $this->_shop->english[173];
                }
                if (!$personal['same_address']
					&& (
						!strlen($personal['gender_shipping'])
						)
					&& !$no_gender) {
                    $errors[] = $this->_shop->english[174];
                }
                if (!strlen($personal['name_invoice'])
					|| !strlen($personal['firstname_invoice'])) {
                    $errors[] = $this->_shop->english[2];
                }
                if (!$personal['same_address']
					&& (
						!strlen($personal['name_shipping'])
						|| !strlen($personal['firstname_shipping'])
						)
					) {
                    $errors[] = $this->_shop->english[3];
                }
                if (strlen($personal['street_invoice']) *
                strlen($personal['city_invoice']) *
                strlen($personal['country_invoice'])
                == 0
                ) {
                    $errors[] = $this->_shop->english[4];
                }
                if (!$personal['same_address'] &&
                strlen($personal['street_shipping']) *
				strlen($personal['city_shipping']) *
                strlen($personal['country_shipping'])
                == 0
                ) {
                    $errors[] = $this->_shop->english[5];
                }
				if ((strlen($personal['zip_invoice'])===0) || (strlen($personal['zip_shipping'])===0
					&& !$personal['same_address'] && isset($personal['zip_invoice']) && isset($personal['zip_shipping'])))
					$errors[] = $this->_shop->english[152];
                
				foreach($fields as $fld) {
					if (isset($personal[$fld.'_invoice'])) {
						$personal[$fld.'_invoice'] = $personal[$fld.'_invoice'];
						$personal[$fld.'_shipping'] = ($same_address)?$personal[$fld.'_invoice']:$personal[$fld.'_shipping'];
					}
				}
				foreach($fieldsreq as $fld) {
                                        if ($fld == 'gender' && $no_gender) {
                                            continue;
                                        }
					if (isset($personal[$fld.'_invoice'])) {
                                            if ((strlen($personal[$fld.'_invoice'])===0) || (strlen($personal[$fld.'_shipping'])===0)) {
                                                $haserrors = true;
                                                $perserror[$fld] = 1;
                                            }
                                        }
				}
                
//				print_r($personal);
			    if ($this->_dbr->getOne("select count(*) from customer{$src} where email = '".$personal['email']."' and id<>$id
						and seller_username='".$this->_shop->username."'")) {
//					echo "select count(*) from customer where email = '".$personal['email']."' and id<>$id";
					$errors[] = $this->_shop->english_shop[8];
				}

                $personal['email_invoice'] = checkEmailPerson($personal['email_invoice'], $personal['email'], 'email_invoice');
                
                if (! $personal['email_invoice'])
                {
                    $personal['email_invoice'] = $personal['email'];
                }
                
                if ( ! $personal['email_invoice']) {
                    $errors[] = $this->_shop->english[8];
                }

                $personal['email_shipping'] = checkEmailPerson($personal['email_shipping'], $personal['email'], 'email_shipping');
                
                if (! $personal['email_shipping'])
                {
                    $personal['email_shipping'] = $personal['email'];
                }
                
                if (!$personal['same_address'] && ! $personal['email_invoice'] && ! $personal['email_shipping']) {
                    $errors[] = $this->_shop->english[9];
                }

                $zip_invoice = validateZIP($personal['zip_invoice'], $personal['country_invoice']);
                $zip_shipping = validateZIP($personal['zip_shipping'], $personal['country_shipping']);
                if ($zip_invoice) {
                    $errors[] = $this->_shop->english_shop[316] . ' ' . $zip_invoice;
                } else if (!$personal['same_address'] && $zip_shipping) {
                    $errors[] = $this->_shop->english_shop[316] . ' ' . $zip_shipping;
                }

                if (!strlen($personal['tel_invoice']) && !strlen($personal['cel_invoice'])) {
                    $errors[] = $this->_shop->english[6];
					$perserror['tel'] = 1;
					$perserror['cel'] = 1;
                }
                if (!$personal['same_address'] && !strlen($personal['tel_shipping']) && !strlen($personal['cel_shipping'])) {
                    $errors[] = $this->_shop->english[7];
					$perserror['tel'] = 1;
					$perserror['cel'] = 1;
                }

                if(
                    (strlen($personal['tel_invoice']) && !strlen($personal['tel_country_code_invoice'])) || 
                    (strlen($personal['cel_invoice']) && !strlen($personal['cel_country_code_invoice'])) 
                ){
                    $errors[] = $this->_shop->english[241];
                    $perserror['tel'] = 1;
                    $perserror['cel'] = 1;
                }
                if(
                    (strlen($personal['tel_shipping']) && !strlen($personal['tel_country_code_shipping'])) ||
                    (strlen($personal['cel_shipping']) && !strlen($personal['cel_country_code_shipping']))
                ){
                    $errors[] = $this->_shop->english[242];
                    $perserror['tel'] = 1;
                    $perserror['cel'] = 1;
                }

                // add leading 0 for SE, FI tel numbers
                if(
                    strlen($personal['tel_invoice']) 
                    && ($personal['tel_country_code_invoice'] == 'SE' || $personal['tel_country_code_invoice'] == 'FI')
                    && substr($personal['tel_invoice'], 0, 1) != '0'
                ){
                    $personal['tel_invoice'] = "0" . $personal['tel_invoice'];
                }
                if(
                    strlen($personal['tel_shipping'])
                    && ($personal['tel_country_code_shipping'] == 'SE' || $personal['tel_country_code_shipping'] == 'FI') 
                    && substr($personal['tel_shipping'], 0, 1) != '0'
                ){
                    $personal['tel_shipping'] = "0" . $personal['tel_shipping'];
                }
                if(
                    strlen($personal['cel_invoice'])
                    && ($personal['cel_country_code_invoice'] == 'SE' || $personal['cel_country_code_invoice'] == 'FI') 
                    && substr($personal['cel_invoice'], 0, 1) != '0'
                ){
                    $personal['cel_invoice'] = "0" . $personal['cel_invoice'];
                }
                if(
                    strlen($personal['cel_shipping'])
                    && ($personal['cel_country_code_shipping'] == 'SE' || $personal['cel_country_code_shipping'] == 'FI') 
                    && substr($personal['cel_shipping'], 0, 1) != '0'
                ){
                    $personal['cel_shipping'] = "0" . $personal['cel_shipping'];
                }
                
				$cntry_code = $personal['country_shipping'];
				$zip = $personal['zip_shipping'];
                if (isZipInRange($this->_db, $this->_dbr, $cntry_code, 'blocked', $zip)) {
                    $errors[] = $this->_shop->english[138];
                }
                $phones = array(
                $this->_shop->english[10] => $personal['tel_invoice'],
                $this->_shop->english[13] => $personal['tel_shipping'],
                );
                $n = 0;
                foreach ($phones as $name=>$phone) {
                    if ((!$personal['same_address'] || !$n) && !preg_match('~^([\s\d\(\)\+\-]+)?$~', $phone)) {
//                        $errors[] = sprintf($this->_shop->english[16], $name);
                    }
                    $n++;
                }
		return $haserrors;
	}

    function addPerson($personal, $login, $password)
    {
	    $personal['email'] = $login;
		$personal['password'] = sha1($password);
	    $personal['shop_id'] = $this->_shop->id;
	    $personal['seller_username'] = $this->_shop->username;
    	$personal['country_shipping'] = countryCodeToCountry($personal['country_shipping']);
    	$personal['country_invoice'] = countryCodeToCountry($personal['country_invoice']);
		$customer_id = 1+$this->_db->getOne("select fget_customer_id()");
		$q = "replace customer set id=$customer_id, lang='{$this->_shop->lang}', code=round(rand()*1000000) ";
		foreach($personal as $field=>$value) {
			if ($field=='same_address') continue;
			$q .=", $field='$value'";
		}
		$r = $this->_db->query($q);
		if (PEAR::isError($r)) aprint_r($r);
		$r = $this->_db->query("delete from shop_customer_spam where shop_id=".$this->_shop->id." and customer_id=$customer_id");
		if (PEAR::isError($r)) aprint_r($r);
#		if ($personal['spam']) {
#			$r = $this->_db->query("insert into shop_customer_spam set shop_id=".$this->_shop->id.", customer_id=$customer_id");
#			if (PEAR::isError($r)) aprint_r($r);
#		}
		$r = $this->_db->query("insert into shop_customer_spam set shop_id=".$this->_shop->id.", customer_id=$customer_id");
		if (PEAR::isError($r)) aprint_r($r);
		return $customer_id;
	}

    function getPerson($loggedCustomer, $same_address=0)
    {
		$personal = array();
        $fields = array('company', 'gender', 'firstname', 'name', 'street', 'house', 'zip', 'city', 'country', 'email', 'tel', 'cel', 'tel_country_code', 'cel_country_code', 'state');
		foreach($fields as $fld) {
			$fldname = $fld.'_invoice';
			$personal[$fldname] = ($loggedCustomer->$fldname);
			$fldname = $fld.'_shipping';
			$personal[$fldname] = ($same_address)?$personal[$fld.'_invoice']:($loggedCustomer->$fldname);
		}
    	$personal['country_shipping'] = countryToCountryCode($personal['country_shipping']);
    	$personal['country_invoice'] = countryToCountryCode($personal['country_invoice']);
		$personal['spam'] = $loggedCustomer->spam;
		if (strlen($loggedCustomer->birthdate) && $loggedCustomer->birthdate!='0000-00-00') $personal['birthdate'] = strftime($this->_seller->get('date_format'),strtotime($loggedCustomer->birthdate));
		$personal['vat'] = $loggedCustomer->vat;
		$r = $this->_dbr->getOne("select 1 from shop_customer_spam where shop_id=".$this->_shop->id." and precode is null
			 and customer_id=".$loggedCustomer->id);
		$personal['spam'] = (int)$r;
		return $personal;
	}

    function updatePerson($personal, $login, $password, $id, $return_diff = false)
    {
	    $personal['email'] = $login;
		if (!strlen($password)) unset($personal['password']);
			else $personal['password'] = sha1($password);

		if ($return_diff)
		{
			global $loggedCustomer;
			$person = $this->getPerson($loggedCustomer);
			$diff = array();

			$translation_groups = array(
				'shipping' => $this->_shop->english[42],
				'invoice' => $this->_shop->english[41]
			);

			$translation_fields = array(
				'company' => $this->_shop->english[43],
				'gender' => $this->_shop->english[169],
				'firstname' => $this->_shop->english[166],
				'name' => $this->_shop->english[167],
				'street' => $this->_shop->english[45],
				'house' => $this->_shop->english[282],
				'zip' => $this->_shop->english[46],
				'city' => $this->_shop->english[47],
				'country' => $this->_shop->english[48],
				'cel' => $this->_shop->english[168],
				'tel' => $this->_shop->english[79],
			);

			foreach($personal as $name => $value)
			{
				if (isset($person[$name]) && $person[$name] != $value)
				{
					$parts = explode('_', $name);
					$title = $translation_groups[$parts[1]] . ' ' . $translation_fields[$parts[0]];
					$title = str_replace(':', '', $title);
					$title = str_replace('*', '', $title);
					$diff[$name] = $title;
				}
			}
		}

    	$personal['country_shipping'] = countryCodeToCountry($personal['country_shipping']);
    	$personal['country_invoice'] = countryCodeToCountry($personal['country_invoice']);
		$personal['birthdate'] = $this->_dbr->getOne("select STR_TO_DATE('".$personal['birthdate']."','".$this->_seller->get('date_format')."')");
		
        if ($id)
        {
            $q = "update customer set id=id, lang='".$this->_shop->lang."' ";
            foreach($personal as $field=>$value) {
                if ($field=='same_address' || $field=='phonebox') continue;
                $q .=", $field='".mysql_escape_string($value)."'";
            }
            $q .=" where id=$id";
        }
        else 
        {
            $id = 1 + $this->_dbr->getOne("SELECT fget_customer_id()");
            
            $q = "INSERT INTO customer SET `id` = '$id', `lang` = '".$this->_shop->lang."' ";
            foreach($personal as $field=>$value) {
                if ($field=='same_address' || $field=='phonebox') continue;
                $q .=", `$field` = '" . mysql_real_escape_string($value) . "'";
            }
        }
        
		$r = $this->_db->query($q);
        
		if (PEAR::isError($r)) { aprint_r($r); die($q);}
		if (isset($personal['spam'])) {
			$r = $this->_db->query("delete from shop_customer_spam where shop_id=".$this->_shop->id." and customer_id=$id");
			if (PEAR::isError($r)) aprint_r($r);
			if ($personal['spam']) {
				$r = $this->_db->query("insert into shop_customer_spam set shop_id=".$this->_shop->id.", customer_id=$id");
				if (PEAR::isError($r)) aprint_r($r);
			} else {
			    setcookie("shop_show_overlay",'');
			}
		}
		return $return_diff ? $diff : true;
	}

    function getOrder($auction_number, $txnid)
    {
		$promo = $this->_dbr->getRow("select shop_promo_codes.*, shop_promo_values.percent, shop_promo_values.amount
			from shop_promo_codes
			join shop_promo_values on shop_promo_codes.id = shop_promo_values.code_id
			where shop_promo_codes.id=(
				select code_id from auction au where au.auction_number=$auction_number and au.txnid=$txnid
			)");
		$r = $this->_dbr->getAll("select subau.* from
			auction subau
			join auction au on au.auction_number=subau.main_auction_number and au.txnid=subau.main_txnid
			where au.auction_number=$auction_number and au.txnid=$txnid
			");
		$offers = array();
		foreach($r as $rec){
			$offers[] = array($rec->saved_id =>
				array ( 'input' => $this->_dbr->getAssoc("select o.article_list_id, o.quantity
					from orders o
					join auction subau on subau.auction_number=o.auction_number and subau.txnid=o.txnid
					join offer of on subau.offer_id=of.offer_id
					#join offer_group og on of.offer_id=og.offer_id
					join article_list al on /*al.group_id=og.offer_group_id and */al.article_list_id=o.article_list_id
					join auction au on au.auction_number=subau.main_auction_number and au.txnid=subau.main_txnid
					where subau.auction_number=".$rec->auction_number." and subau.txnid=".$rec->txnid." order by o.ordering")
					, 'auction_number' => $rec->auction_number
					, 'txnid' => $rec->txnid
				)
			);
		}
		$offers_objects = array();
		$articles_objects = array();
		$mulang_fields = array('name'
							   ,'description'
							   ,'shipping_plan_id'
							   ,'high_price'
							   ,'add_item_cost'
							   );
		foreach ($offers as $key => $shop_offer) {
			foreach ($shop_offer as $saved_id => $input_arr) {
				$getoffer = $this->getOffer($saved_id);
//                if ( ! $getoffer) {
//                    unset($shop_offer[$saved_id]);
//                    continue;
//                }

				$offer_id = $this->_dbr->getOne("select
							REPLACE(SUBSTRING_INDEX( SUBSTRING_INDEX( SUBSTRING_INDEX( details, 's:8:".'"offer_id"'.";', -1 ) , ';', 1 ), ':', -1),'".'"'."','')
					from saved_auctions where id=$saved_id");
				$offers_objects[$key] = new Offer($this->_db, $this->_dbr, $offer_id, $this->_shop->lang);
				$input_prices = $promo_input_prices = array();
				foreach ($input_arr['input'] as $article_list_id => $quantity) {
					$al_rec = $this->_dbr->getRow("select article_list.*
						from article_list
						where article_list_id=$article_list_id");
					$article_id = $al_rec->article_id;
					$alias_id = $al_rec->alias_id;
					$articles_objects[$article_list_id] = new Article($this->_db, $this->_dbr, $article_id);
					$group = $this->_dbr->getRow("select og.*, offer.condensed
						from offer_group og
						join offer on offer.offer_id=og.offer_id
						join article_list al on al.group_id = og.offer_group_id
						where al.article_list_id=$article_list_id");
					if ($group->condensed && $group->main) {
						$price = $getoffer->ShopPrice;
					} else {
						$price = Offer::getShopPrice($this->_db, $this->_dbr, $saved_id, $article_list_id
						, $this->_shop->lang, $this->_shop->siteid);
					}
					$articles_objects[$article_list_id]->price = $price;
					$input_prices[$article_list_id] = $articles_objects[$article_list_id]->price;
					$promo_input_prices[$article_list_id] =
						$this->_dbr->getOne("select price from orders where article_list_id=$article_list_id
							and auction_number=".$input_arr['auction_number']." and txnid=".$input_arr['txnid']);
					foreach ($mulang_fields as $fld) {
						$articles_objects[$article_list_id]->data->$fld =
						  $this->_dbr->getOne("select value from translation where id='$article_id'
								       and table_name='article' and field_name='$fld' and language in
									   ('".$this->_shop->lang."','".$this->_shop->siteid."')");
					}
					if ((int)$alias_id)
						$articles_objects[$article_list_id]->data->name = $this->_dbr->getOne("select value from translation
										where id='$alias_id'
								       and table_name='article_alias' and field_name='name' and language in
									   ('".$this->_shop->lang."','".$this->_shop->siteid."')");
				}
				$offers_objects[$key]->input_prices = $input_prices;
				$offers_objects[$key]->promo_input_prices = $promo_input_prices;
			}
		}
		$shop_cart = new stdClass;
		$shop_cart->offers = $offers;
		$shop_cart->offers_objects = $offers_objects;
		$shop_cart->articles_objects = $articles_objects;
//		print_r($shop_cart->offers);
//		print_r($shop_cart->offers_objects);
		return $shop_cart;
    }

	function listFrontOverlays($where=''){
		return $this->_dbr->getAll("select shop_front_overlay.id,
			shop_front_overlay.shop_id,
			shop_front_overlay.name,
			shop_front_overlay.mobile,
			shop_front_overlay.default,
			shop_front_overlay.skipdays,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_front_overlay'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_front_overlay.id) as html,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_front_overlay'
				AND field_name = 'html_customer'
				AND language = '".$this->_shop->lang."'
				AND id = shop_front_overlay.id) as html_customer
			, shop.frontoverlay_skipdays_unregistered
			, shop.frontoverlay_skipdays_registered
			from shop_front_overlay
			join shop on shop.id=shop_front_overlay.shop_id
			where shop_front_overlay.shop_id=".$this->_shop->id."
			$where");
	}

	function listNews($limit='', $inactive=0){
		global $debug;
        
		$function = "listNews($limit, $inactive)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
        
		$this->_db->query("set lc_time_names='".$this->_shop->locale."'");
		$ret = $this->_dbr->getAll("select shop_news.id,
			shop_news.shop_id,
			shop_news.name,
			shop_news.created,
			shop_news.inactive,
			DATE_FORMAT(created, '%W, %d %M %Y') as created_de,
			t_short_descr.value as short_descr,
			t_alias.value as alias,
			t_title.value as title,
			t_html.value as html
			from shop_news
left join translation t_short_descr on t_short_descr.table_name = 'shop_news'
				AND t_short_descr.field_name = 'short_descr'
				AND t_short_descr.language = '".$this->_shop->lang."'
				AND t_short_descr.id = shop_news.id
left join translation t_alias on t_alias.table_name = 'shop_news'
				AND t_alias.field_name = 'alias'
				AND t_alias.language = '".$this->_shop->lang."'
				AND t_alias.id = shop_news.id
left join translation t_title on t_title.table_name = 'shop_news'
				AND t_title.field_name = 'title'
				AND t_title.language = '".$this->_shop->lang."'
				AND t_title.id = shop_news.id
left join translation t_html on t_html.table_name = 'shop_news'
				AND t_html.field_name = 'html'
				AND t_html.language = '".$this->_shop->lang."'
				AND t_html.id = shop_news.id
			where shop_id=".$this->_shop->id."
			and inactive in ($inactive)
			order by created desc $limit");
        
		foreach($ret as $k=>$r) $ret[$k]->created_de = strftime('%A, %d %B %Y',strtotime($r->created));
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
		return $ret;
	}

	function getNews($id){
		$this->_db->query("set lc_time_names='".$this->_shop->locale."'");
		return $this->_dbr->getRow("select shop_news.id,
			shop_news.shop_id,
			shop_news.name,
			shop_news.created,
			DATE_FORMAT(created, '%W, %d %M %Y') as created_de,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_news'
				AND field_name = 'short_descr'
				AND language = '".$this->_shop->lang."'
				AND id = shop_news.id) as short_descr,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_news'
				AND field_name = 'keywords'
				AND language = '".$this->_shop->lang."'
				AND id = shop_news.id) as keywords,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_news'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_news.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_news'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = shop_news.id) as description,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_news'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_news.id) as html
			from shop_news
			where shop_id=".$this->_shop->id." and id=$id");
	}

	function listContent($nocache=0){
		global $debug;
		$function = "listContent()";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if (!$nocache && $chached_ret) {
			return $chached_ret;
		}
		$q = "select shop_content.id,
			shop_content.shop_id,
			shop_content.name,
			shop_content_shop.ordering,
			shop_content_shop.inactive/* + shop_content.inactive*/ as inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'title_menu'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as title_menu,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as html
			from shop_content
			join shop_content_shop on shop_content_shop.shop_content_id=shop_content.id
			where shop_content_shop.shop_id=".$this->_shop->id."
			order by ordering";
		if ($debug) echo $q.'<br>';
		$res = $this->_dbr->getAll($q);
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $res);
		return $res;
	}

	function getContent($id){
		$q = "select shop_content.id,
			shop_content.shop_id,
			shop_content.name,
			shop_content.ordering,
			shop_content.ask4email,
			shop_content.ask4comment,
			shop_content.source_code,
			shop_content.tpl,
			shop_content.inactive + shop_content.inactive as inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as description,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'keywords'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as keywords,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'ask4email_popuptext'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as ask4email_popuptext,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as html
			from shop_content
			join shop_content_shop on shop_content_shop.shop_content_id=shop_content.id
			where shop_content_shop.shop_id=".$this->_shop->id." and shop_content.id=$id";
		$res = $this->_dbr->getRow($q);

		global $smarty;

		$shop_contest = $this->_dbr->getRow("select id from shop_contests where id in (
			select shop_contest
			from shop_content
			where id=$id
		)");

		$shop_content_id = $id;
		$shop_contest_id = $shop_contest->id;
		if($shop_contest_id>0){
			$fields_pre = $this->_dbr->getAll("select * from shop_contests_fields where shop_contests_id=$shop_contest_id");
			$fields = (object) array();
			foreach($fields_pre as $field) {
				$name = $field->name;
				$fields->$name = '1';
			}
		}
		$smarty->assign('shopCatalogue', $this);
		$voucher_form = $smarty->fetch($this->_tpls['shop_promo']);
		$res->html = str_replace("[[voucher_form]]", $voucher_form, $res->html);
		if ( ($res->ask4email) || (!empty($fields)) ) {
			$smarty->assign('content_rec', $res);
			$smarty->assign('content_fields', $fields);
			$smarty->assign('shop_content_id', $shop_content_id);
			$smarty->assign('shop_contest_id', $shop_contest_id);
			$res->html .= $smarty->fetch($this->_tpls['_shop_ask4email']);
		}

		return $res;
	}

	function getContentByName($name){
		$q = "select shop_content.id,
			shop_content.shop_id,
			shop_content.name,
			shop_content.ordering,
			shop_content.inactive,
			shop_content.ask4email,
			shop_content.tpl,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as description,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'keywords'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as keywords,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'ask4email_popuptext'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as ask4email_popuptext,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id) as html
			from shop_content
			join shop_content_shop on shop_content_shop.shop_content_id=shop_content.id
			where shop_content_shop.shop_id=".$this->_shop->id." and (shop_content.name='$name' or (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = shop_content.id)='$name')
			limit 1";
		$res = $this->_dbr->getRow($q);
		return $res;
	}

	function getPartner($id){
		$q = "select spp.*
			, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_content'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as html
			, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_partner'
				AND field_name = 'email_subject'
				AND language = '".$this->_shop->lang."'
				AND id = spp.id) as email_subject
			, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_partner'
				AND field_name = 'email_body'
				AND language = '".$this->_shop->lang."'
				AND id = spp.id) as email_body
			, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_partner'
				AND field_name = 'thanks_text'
				AND language = '".$this->_shop->lang."'
				AND id = spp.id) as thanks_text
			from shop_promo_partner spp
			join shop_content sc on spp.shop_content_id=sc.id
			where spp.shop_id=".$this->_shop->id." and spp.id=$id";
		$res = $this->_dbr->getRow($q);
		return $res;
	}

	function listMeta(){
		return $this->_dbr->getAll("select
						(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_meta'
				AND field_name = 'meta_value'
				AND language = '".$this->_shop->lang."'
				AND id = shop_meta.id) as meta_value,
 				shop_meta.id,
				shop_meta.shop_id,
				shop_meta.meta_name
			from shop_meta
			where shop_id=".$this->_shop->id);
	}

	function listBanners($active='1', $block='', $category=''){
		// find all the parents for the category
		if ($category) 	{
			$categories = implode(',',$this->getAllNodes($category));
		}
		$q = "
			select shop_banner.*
			, IF(shop_banner.deactivate_from<NOW(),0,
					IF(shop_banner.activate_from is null
						, active
						, IF(shop_banner.activate_from<NOW() and IFNULL(shop_banner.deactivate_from,'9999-12-31')>NOW(),1,0)
					)
				) active,
			substring_index((SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'pic'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id), '.', -1) as ext,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id) as html,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'mobile_pic'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id) as mobile_pic,
			substring_index((SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'mobile_pic'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id), '.', -1) as mobile_ext,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'mobile_html'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id) as mobile_html,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'alt'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id) as alt,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_banner'
				AND field_name = 'banner_text'
				AND language = '".$this->_shop->lang."'
				AND id = shop_banner.id) as banner_text
			, sbb.code bblock
			from shop_banner
                        join shop_banner_block sbb ON shop_banner.block_id = sbb.id
			where shop_banner.shop_id=".$this->_shop->id."
			and IF(shop_banner.deactivate_from<NOW(),0,
					IF(shop_banner.activate_from is null
						, active
						, IF(shop_banner.activate_from<NOW() and IFNULL(shop_banner.deactivate_from,'9999-12-31')>NOW(),1,0)
					)
				) in ($active)
			".($block==''?'':"and sbb.code in ('$block')")."
			" . ($category == '' ? '' : "and (shop_banner.category = $category 
				or (shop_banner.subcategories=1 and shop_banner.category in ($categories)))") . "
			order by sbb.code, shop_banner.ordering";
//		echo "<pre>" . htmlspecialchars($q) . "</pre>";
		$banners = $this->_dbr->getAll($q);
		if (PEAR::isError($banners)) {
            aprint_r($banners);
            return;
        }

		foreach($banners as $k=>$r) {
			$banners[$k]->html = $this-> _fetchBannerHtml($r, 'html');
			$banners[$k]->mobile_html = $this-> _fetchBannerHtml($r, 'mobile_html');
		}
		return $banners;
	}

	private function _fetchBannerHtml($banner, $field)
	{
		global $smarty;
		$smarty->assign('banner', $banner);

		$msg = $banner->$field;
		$msg = substitute($msg, $banner);
		$msg = str_replace('<style', '{literal}<style', $msg);
		$msg = str_replace('</style>', '</style>{/literal}', $msg);
        
        $res = fetchfromstring($msg);
        
        if ($this->_shop->cdn) {
            $res = processContent($res, $this->cdn_domain);
        }

		return $res;
	}

	function listServices($service_id, $level=0){
		$service_id = (int)$service_id;
		global $shop_ids;
		global $debug;
		$shop_ids[] = $this->_shop->id;
		if ($debug) echo 'listServices start<br>';
		if ($this->_shop->services_shop_id && !in_array($this->_shop->services_shop_id, $shop_ids)) {
			if ($debug) echo 'listServices in parent shop<br>';
			$function = "listServices($service_id, $level)";
			$chached_ret = cacheGet($function, $this->_shop->services_shop_id, $this->_shop->lang);
			if ($chached_ret) {
				return $chached_ret;
			}
			$shop = new Shop_Catalogue($this->_db, $this->_dbr, $this->_shop->services_shop_id, $this->_shop->lang);
			return $shop->listServices($service_id);
		}
		$function = "listServices($service_id, $level)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
			return $chached_ret;
		}
		if ($debug) echo "listServices NO CACHE for $function<br>";
        $ret = array();
        $list = $this->listAllServices($service_id, $level);
/*		$allNodes_array = (array)$this->getAllServicesNodes($service_id);
		$allNodes =  implode(',', $allNodes_array);
		if (!$service_id) {
			$service_id=(int)$this->_dbr->getOne("select group_concat(sc.id separator ',')
				from shop_service sc
				where sc.shop_id=".$this->_shop->id."
				and sc.opened");
		}*/
		$q = "select sc.id f1, sc.id f2
			from shop_service sc
			where sc.shop_id=".$this->_shop->id."
			and sc.parent_id in
			($service_id/*, $allNodes*/)";
		$ids = $this->_dbr->getAssoc($q);
		  if (PEAR::isError($ids)) {
            aprint_r($ids);
            return;
        }
        foreach ((array)$list as $rec) {
			/*if (in_array($rec->id, $ids)) */{
				$rec->children = $this->listServices($rec->id, $level+1);
				$rec->childcount = count($rec->children);
	            $ret[] = $rec;
			}
        }
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        return $ret;
	}

    function listAllServices($parent_id=0, $level=0)
    {
		global $shop_ids;
		$shop_ids[] = $this->_shop->id;
		if ($this->_shop->services_shop_id && !in_array($this->_shop->services_shop_id, $shop_ids)) {
			$shop = new Shop_Catalogue($this->_db, $this->_dbr, $this->_shop->services_shop_id, $this->_shop->lang);
			return $shop->listAllServices($parent_id, $level);
		}
		$q = "SELECT sc.id,
			sc.shop_id,
			sc.ordering,
			sc.parent_id,
			sc.opened,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'attached_doc'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as attached_doc_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'link'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as link,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as html
			, $level level
			FROM shop_service sc
			where sc.shop_id=".$this->_shop->id."
			and sc.parent_id=$parent_id ORDER BY sc.ordering";
//			echo 'Services for '.$parent_id.' : '.$q.'<br>';
        $r = $this->_dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		$res = array();
		foreach($r as $key=>$rec){
			$res[] = $rec;
#			$children = $this->listAllServices($rec->id, $level+1);
#			$res = array_merge((array)$res, (array)$children);
		}
        return $res;
    }

    function getAllServicesNodes($service_id)
	{
		global $shop_ids;
		$shop_ids[] = $this->_shop->id;
		if ($this->_shop->services_shop_id && !in_array($this->_shop->services_shop_id, $shop_ids)) {
			$shop = new Shop_Catalogue($this->_db, $this->_dbr, $this->_shop->services_shop_id, $this->_shop->lang);
			return $shop->getAllServicesNodes($service_id);
		}
		if (!$service_id) return 0;
		$cat = $this->_dbr->getRow("select sc.* from shop_service sc
			where sc.shop_id=".$this->_shop->id."
		and sc.id=$service_id");
		return array_merge((array)$cat->id,(array)$this->getAllServicesNodes($cat->parent_id));
	}

	function getService($id){
		return $this->_dbr->getRow("select sc.id,
			sc.shop_id,
			sc.ordering,
			sc.parent_id,
			sc.opened,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'attached_doc'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as attached_doc_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'link'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as link,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'alias'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as alias,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_service'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as html
			FROM shop_service sc where id=$id");
	}

	function listVouchers($date='', $name='', $used='', $expired='', $copied='', $inactive='', $paid='', $security=''
		, $shop_id='', $description='', $marked_for_payment='', $code_subs='', $name_subs='', $date_from='', $date_to=''){
		global $debug;
		global $nocalc;
		if (strlen($date)) $date = " and '$date' between date_from and date_to ";
		$where = ''; $where_int = '';
		if (strlen($description)) $where .= " and IFNULL(name,'')='' and exists (SELECT null
				FROM translation
				WHERE table_name = 'shop_promo_codes'
				AND field_name = 'name'
				AND id = t.id
				and `value` = '".trim($description)."') ";
		if (!strlen($shop_id)) $where_int .= " and shop_id=".$this->_shop->id." ";
			elseif ($shop_id==0) $where_int .= "";
			else $where_int .=" and shop_id=".$shop_id." ";
		if (strlen($name)) {
			if ($name == -1) {
				$names_active = $this->_dbr->getAssoc("select name f1, name f2 from
					(select distinct trim(name) name from shop_promo_codes
						left join total_log tl on tl.table_name='shop_promo_codes' and tl.field_name='id' and tl.tableid=shop_promo_codes.id
						where name<>''  ".($shop_id?"and shop_id=$shop_id":'')."
						and now() between if(date_to_days is not null, tl.updated, date_from)
							and if(date_to_days is not null, DATE_ADD(tl.updated, interval date_to_days day), date_to)
						) t
					where IFNULL((select sum(sold_for_amount) from shop_promo_codes where name=t.name and not inactive and not dont_take_sold_for_amount  ".($shop_id?"and shop_id=$shop_id":'')."),0)
							-IFNULL((select sum(amount) from shop_promo_payment where code_name=t.name),0)
							>0
					order by name
					");
				foreach($names_active as $r1=>$dummy) $names_active[$r1] = mysql_escape_string($names_active[$r1]);
				$names_active = "'".implode("','", $names_active)."'";
				$where_int .= " and shop_promo_codes.name in ($names_active) ";
			} elseif ($name == -4) {
				$names_active = $this->_dbr->getAssoc("select name f1, name f2 from
					(select distinct trim(name) name
						from shop_promo_codes
						left join total_log tl on tl.table_name='shop_promo_codes' and tl.field_name='id' and tl.tableid=shop_promo_codes.id
						where name<>'' ".($shop_id?"and shop_id=$shop_id":'')."
						and now() not between if(date_to_days is not null, tl.updated, date_from)
							and if(date_to_days is not null, DATE_ADD(tl.updated, interval date_to_days day), date_to)
						) t
					where IFNULL((select sum(sold_for_amount) from shop_promo_codes where name=t.name and not inactive and not dont_take_sold_for_amount ".($shop_id?"and shop_id=$shop_id":'')."),0)
							-IFNULL((select sum(amount) from shop_promo_payment where code_name=t.name),0)
							>0
					order by name
					");
				foreach($names_active as $r1=>$dummy) $names_active[$r1] = mysql_escape_string($names_active[$r1]);
				$names_active = "'".implode("','", $names_active)."'";
				$where_int .= " and shop_promo_codes.name in ($names_active) ";
			} elseif ($name == -2) {
				$names_inactive = $this->_dbr->getAssoc("select name f1, name f2 from
					(select distinct trim(name) name
						from shop_promo_codes
						left join total_log tl on tl.table_name='shop_promo_codes' and tl.field_name='id' and tl.tableid=shop_promo_codes.id
						where name<>''  ".($shop_id?"and shop_id=$shop_id":'')."
						and now() between if(date_to_days is not null, tl.updated, date_from)
							and if(date_to_days is not null, DATE_ADD(tl.updated, interval date_to_days day), date_to)
						) t
					where IFNULL((select sum(sold_for_amount) from shop_promo_codes where name=t.name and not inactive and not dont_take_sold_for_amount ".($shop_id?"and shop_id=$shop_id":'')."),0)
							-IFNULL((select sum(amount) from shop_promo_payment where code_name=t.name),0)
							<=0
					order by name
					");
				foreach($names_inactive as $r1=>$dummy) $names_inactive[$r1] = mysql_escape_string($names_inactive[$r1]);
				$names_inactive = "'".implode("','", $names_inactive)."'";
				$where_int .= " and shop_promo_codes.name in ($names_inactive) ";
			} elseif ($name == -3) {
				$names_inactive = $this->_dbr->getAssoc("select name f1, name f2 from
					(select distinct trim(name) name
						from shop_promo_codes
						left join total_log tl on tl.table_name='shop_promo_codes' and tl.field_name='id' and tl.tableid=shop_promo_codes.id
						where name<>''  ".($shop_id?"and shop_id=$shop_id":'')."
						and now() not between if(date_to_days is not null, tl.updated, date_from)
							and if(date_to_days is not null, DATE_ADD(tl.updated, interval date_to_days day), date_to)
						) t
					where IFNULL((select sum(sold_for_amount) from shop_promo_codes where name=t.name and not inactive and not dont_take_sold_for_amount ".($shop_id?"and shop_id=$shop_id":'')."),0)
							-IFNULL((select sum(amount) from shop_promo_payment where code_name=t.name),0)
							<=0
					order by name
					");
				foreach($names_inactive as $r1=>$dummy) $names_inactive[$r1] = mysql_escape_string($names_inactive[$r1]);
				$names_inactive = "'".implode("','", $names_inactive)."'";
				$where_int .= " and shop_promo_codes.name in ($names_inactive) ";
			} elseif (strlen(trim($name))) {
				$where_int .= " and shop_promo_codes.name = '".trim($name)."' ";
			}
		} else {
			$where_int .= " and 0";
		}
//		echo '<br>$where_int='.$where_int.'<br>';
		if (strlen($code_subs)) $where_int .= " and shop_promo_codes.code like '%$code_subs%' ";
		if (strlen($name_subs)) $where_int .= " and shop_promo_codes.name like '%$name_subs%' ";

		if (strlen($used)) $where .= " and ".($used?'':'NOT')." auctions>=`usage` ";
		if (strlen($expired)) $where .= " and NOW() ".($expired?'NOT':'')." between date_from and date_to ";
		if ((int)$copied) $where .= " and copied_from_id=$copied";
		if (strlen($inactive)) $where .= " and inactive = $inactive ";
		if (strlen($security)) $where .= " and security = $security ";
		if (strlen($paid)) $where .= " and sold_for_amount and ".($paid?'NOT':'')." paid_amount >= sold_for_amount*auctions";
		if (strlen($marked_for_payment)) $where .= " and ".($marked_for_payment?'':'NOT')." marked_for_payment";

		if ($date_from && $date_to) {
			$delivery_date = " AND delivery_date_real BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
			$where .= " AND au.delivery_date_real BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
		}

		$q = "select t.* from (
			select
				(select SUM(i.total_price) from auction au join invoice i on i.invoice_number=au.invoice_number
					where au.deleted=0 and au.code_id=shop_promo_codes.id) total_price,
				tl.updated created_on,
				(select IFNULL(u.name, tl.username)
					from users u where u.system_username=tl.username) created_by,
			if(shop_promo_codes.descr_is_name
				, shop_promo_codes.name
				, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_codes'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = shop_promo_codes.id)) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_codes'
				AND field_name = 'html_txt'
				AND language = '".$this->_shop->lang."'
				AND id = shop_promo_codes.id) as html_txt,
			shop_promo_codes.inactive,
			shop_promo_codes.id,
			if(date_to_days is not null, tl.updated, shop_promo_codes.date_from) date_from,
			if(date_to_days is not null, date_add(tl.updated, interval date_to_days day), shop_promo_codes.date_to) date_to,
			-DATEDIFF(NOW(), date_add(tl.updated, interval date_to_days day)) days_left,
			shop_promo_codes.usage,
			shop_promo_codes.shop_id,
			shop_promo_codes.code,
			shop_promo_codes.min_amount,
			shop_promo_codes.free_article_price_limit,
			shop_promo_codes.free_article_free_shipping,
			shop_promo_codes.days_available,
			shop_promo_codes.type,
			shop_promo_codes.marked_for_payment,
			shop_promo_codes.days_send,
			shop_promo_codes.days_remind,
			shop_promo_codes.type_send,
			shop_promo_codes.pdf_txt,
			shop_promo_codes.article_id,
			shop_promo_codes.copied_from_id,
			shop_promo_codes.security_code,
			shop_promo_codes.name name1,
			shop_promo_codes.dont_take_sold_for_amount,
			IFNULL(shop_promo_codes.security,0) security,
			shop_promo_codes.sold_for_amount,
			CONCAT(shop_promo_codes.date_from,'-',shop_promo_codes.date_to) date_range,
			IF(DATE(NOW())>shop_promo_codes.date_to, 'red','black') date_color
			, shop_promo_values.percent, shop_promo_values.amount,
			(select group_concat(CONCAT(shop_promo_articles.quantity, ' x ', shop_promo_articles.article_id, ': ', translation.value)
					 separator '<br>')
				from shop_promo_articles
				join article on article.admin_id=0 and shop_promo_articles.article_id = article.article_id
				left join translation on article.article_id=translation.id and translation.language='".$this->_shop->lang."'
					and translation.table_name='article' and translation.field_name='name'
				where code_id=shop_promo_codes.id
				group by code_id
			) free_articles
			, (select max(end_time) from auction where deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id) last_time
			, (select count(*) from auction where deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id) auctions

			, (select group_concat(ff_number separator ',')
					from auction
					where IFNULL(ff_number,'')<>'' and deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id) ff_numbers
			, (select sum(total_price+total_shipping+total_cod+total_cc_fee) from auction
				join invoice on invoice.invoice_number=auction.invoice_number
				where deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id) invoice_amount
			, (select count(distinct customer_id) from auction where deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id) customers
			, (select group_concat(CONCAT(apv_firstname.value,' ',apv_name.value) separator '<br>')
				from auction
				left join auction_par_varchar apv_firstname 
						on apv_firstname.auction_number=auction.auction_number 
						and apv_firstname.txnid=auction.txnid 
						and apv_firstname.key='firstname_invoice'
				left join auction_par_varchar apv_name 
						on apv_name.auction_number=auction.auction_number 
						and apv_name.txnid=auction.txnid 
						and apv_name.key='name_invoice'
				where auction.deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id
				) customer
			, (select IFNULL(sum(amount),0) from shop_promo_payment where code_id=shop_promo_codes.id) paid_amount
			, (select CONCAT('Was ',IF(tl.new_value=1, 'added', 'removed'),' by ', IFNULL(u1.name, tl.username)
				, ' on ', tl.updated)
				from total_log tl
				left join users u1 on u1.system_username=tl.username
				where table_name='shop_promo_codes' and field_name='marked_for_payment' and tableid=shop_promo_codes.id
				order by tl.updated desc limit 1
				) marked_for_payment_log
			from shop_promo_codes
			join shop_promo_values on shop_promo_codes.id = shop_promo_values.code_id
			left join total_log tl on tl.table_name='shop_promo_codes' and tl.field_name='id' and tl.tableid=shop_promo_codes.id
				and tl.old_value is null
			left join users u on u.system_username=tl.username
			where 1
			$date
			$where_int
			) t
			left join auction au on au.code_id=t.id
			where 1
			$where
			group by t.id
			order by name1
			";
		//echo $q; //die();
//		if ($debug) echo $q;
		$r = $this->_dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		
		foreach($r as $k=>$dummy) {
			$q = "SELECT
				au.auction_number,
				au.txnid,
				au.ff_number,
				group_concat(tn.number separator '<br>') tns,
				au.delivery_date_real
			FROM auction au
				JOIN tracking_numbers tn on au.auction_number=tn.auction_number and au.txnid=tn.txnid
			WHERE au.deleted=0 and au.code_id=".$r[$k]->id."
			GROUP BY CONCAT(au.auction_number, '/', au.txnid)";
			$r[$k]->auctions_data = $this->_dbr->getAll($q);
			if (!$nocalc) {
				foreach($r[$k]->auctions_data as $kk=>$auction) {
					$calc = Auction::getCalcs($this->_db, $this->_dbr, array($auction), 1);
					foreach($calc as $calc) {
						if ($calc->sum) {
							$r[$k]->brutto_income_2 += $calc->brutto_income_2;
							$r[$k]->brutto_income_2_EUR += $calc->brutto_income_2_EUR;
						}
					}
					$subaus = $this->_dbr->getAll("select auction_number, txnid from auction where
						main_auction_number=$auction->auction_number and main_txnid=$auction->txnid");
					foreach($subaus as $subau) {
						$calc = Auction::getCalcs($this->_db, $this->_dbr, array($subau), 1);
						foreach($calc as $calc) {
							if ($calc->sum) {
								$r[$k]->brutto_income_2 += $calc->brutto_income_2;
								$r[$k]->brutto_income_2_EUR += $calc->brutto_income_2_EUR;
							}
						}
					}
				}
			}
		}
		return $r;
	}

    static function getVoucherComments($db, $dbr, $id)
    {
		$q = "SELECT '' as prefix
			, shop_promo_comment.id
			, shop_promo_comment.create_date
			, shop_promo_comment.username
			, IFNULL(users.name, shop_promo_comment.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, shop_promo_comment.comment
			 from shop_promo_comment
			 LEFT JOIN users ON shop_promo_comment.username = users.username
			where code_id=$id
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

    static function addVoucherComment($db, $dbr, $id,
		$username,
		$create_date,
		$comment
		)
    {
        $id = (int)$id;
		$username = mysql_escape_string($username);
		$create_date = mysql_escape_string($create_date);
		$comment = mysql_escape_string($comment);
        $r = $db->query("insert into shop_promo_comment set
			code_id=$id,
			username='$username',
			create_date='$create_date',
			comment='$comment'");
    }

    static function delVoucherComment($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("delete from shop_promo_comment where id=$id");
    }

	function listVoucherNames($date='', $name/*description*/='', $used='', $expired='', $copied='', $inactive='', $paid='', $security=''){
		if (strlen($date)) $date = " and '$date' between date_from and date_to ";
		$where = '';
		if (strlen($name)) $where .= " and name = '".($name)."' ";
		if (strlen($used)) $where .= " and ".($used?'':'NOT')." auctions>=`usage` ";
		if (strlen($expired)) $where .= " and NOW() ".($expired?'NOT':'')." between date_from and date_to ";
		if ((int)$copied) $where .= " and copied_from_id=$copied";
		if (strlen($inactive)) $where .= " and inactive = $inactive ";
		if (strlen($security)) $where .= " and security = $security ";
		if (strlen($paid)) $where .= " and sold_for_amount and ".($paid?'NOT':'')." paid_amount >= sold_for_amount*auctions";

		$q = "select name, sum(sold_for_amount) sold_for_amount from (
			select shop_promo_codes.*
			, (select IFNULL(sum(amount),0) from shop_promo_payment where code_id=shop_promo_codes.id) paid_amount
			, (select count(*) from auction where deleted=0 and auction.code_id and auction.code_id=shop_promo_codes.id) auctions
			from shop_promo_codes
			join shop_promo_values on shop_promo_codes.id = shop_promo_values.code_id
			left join total_log tl on tl.table_name='shop_promo_codes' and tl.field_name='id' and tl.tableid=shop_promo_codes.id
				and tl.old_value is null
			left join users u on u.system_username=tl.username
			where 1
			$date
			and shop_id=".$this->_shop->id."
			) t
			where 1
			$where
			group by name
			order by name
			";
//		echo $q; //die();
		$r = $this->_dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		return $r;
	}

	function processTemplate($code_id, $template_id, $lang){
		$cat = $this->_dbr->getRow("select shop_promo_codes.*, shop_promo_values.percent, shop_promo_values.amount
			, (select count(*) from auction where deleted=0 and code_id and code_id=shop_promo_codes.id) used
			, shop.name shop
			from shop_promo_codes
			join shop_promo_values on shop_promo_codes.id = shop_promo_values.code_id
			join shop on shop.id = shop_promo_codes.shop_id
			where shop_promo_codes.id=".$code_id);
		$template = '<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>'.$this->_dbr->getOne("select t.value from translation t
			where t.language='{$lang}' and t.id=".$template_id."
			and t.table_name='shop_promo_template' and field_name='html'").'
</body>
</html>';
		$cat->voucher_code = $cat->code;
		$cat->voucher_id = $cat->id;
		$cat->voucher_amount = $cat->amount;
		$cat->voucher_percent = $cat->percent;
//		$cat->voucher_expiration_date = $exp_date;
		$cat->currency = $shop->_shop->curr;
		$conditions = $this->_dbr->getAssoc("select shop_promo_condition.id,
			(select value from translation where table_name='shop_promo_condition' and field_name='cond_text' and id=shop_promo_condition.id and language='{$lang}') translated_text
			from shop_promo_condition
			join shop_promo_condition_promo on condition_id=shop_promo_condition.id and code_id=$code_id
			order by shop_promo_condition.ordering
				");
		$cat->conditions = (implode(' - ',$conditions));
		for($i=1;$i<=3;$i++) {
					$sas = $this->_dbr->getAssoc("select saved_id,
						IFNULL(translated_text,
							(select value from translation where table_name='translate' and field_name='translate'
							and id=22 and language='{$lang}')
						) translated_text
						from (
						select shop_promo_sa.saved_id,
						(select offer_name.name
						from translation
						join offer_name on translation.value=offer_name.id
						where translation.table_name='sa'
						and translation.field_name='ShopDesription'
						and translation.id=shop_promo_sa.saved_id and translation.language='{$lang}') translated_text
						from shop_promo_sa
						where code_id=$code_id and shop_promo_sa.block=$i
						) t
							");
					$cat->sas .= ', <br/>'.implode(', <br/>',$sas);
		}
		$picsdocs = Shop_Catalogue::getDocs($this->_db, $this->_dbr, $code_id, '', '', 'shop_promo_doc', 'code_id');
		foreach($picsdocs as $pic) {
			$fn = 'voucher_image_'.$pic->code;
			$cat->$fn = "http".($shop->_shop->ssl?'s':'')
				."://www.{$this->_shop->url}/images/cache/{$lang}_src_voucher_picid_{$pic->code}_code_{$code_id}_image.jpg";
		}
		$template = substitute($template,$cat);
		return $template;
	}

	static function duplicateVoucher($db, $dbr, $id, $codes, $flds2set, $voucher_amount='amount'){
		$codes_ok = array();
		$codes_failed = array();
		$name = $dbr->getOne("select name from shop_promo_codes where id=$id");
        $r = $db->query("EXPLAIN shop_promo_codes");
        if (PEAR::isError($r)) {
            aprint_r($r); die();
        }
        while ($field = $r->fetchRow()) {
            if ($field->Field=='id') continue;
            $flds[] = '`'.$field->Field.'`';
        }
        $flds1 = implode(',', $flds);
        $flds2 = $flds1;
        foreach($flds2set as $fld=>$val) {
            $flds2 = str_replace('`'.$fld.'`', $val, $flds2);
        }
        
        $freesas = $dbr->getAll("select * from shop_promo_free_sa where shop_promo_free_sa.code_id=$id");            
            
		foreach ($codes as $code) {
			$article_id = Article::getNextId(3);
				$q = "insert into article set name='$name'
					,article_id='$article_id', admin_id=3
					";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$q = "insert into shop_promo_codes ($flds1)
					select ".str_replace('`article_id`', "'$article_id'", str_replace('`copied_from_id`', $id, str_replace('`code`', "'$code'", $flds2)))."
					from shop_promo_codes where id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$new_id = mysql_insert_id();
				if (!$new_id) {
					$new_id = $db->getOne("select max(id) from shop_promo_codes where article_id='$article_id'");
				}
				$q = "insert into rma_notif (obj, auction_id, username)
					select obj, $new_id, username from rma_notif where obj='voucher' and auction_id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code.' '.$q; continue;}
				$q = "insert into shop_promo_values (code_id,percent,amount,`type`,`usage`)
					select $new_id,percent,$voucher_amount,`type`,`usage` from shop_promo_values where code_id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$q = "insert into translation (`language`,table_name,field_name,id,`value`,unchecked,updated)
					select `language`,table_name,field_name,$new_id,`value`,unchecked,updated from translation
						where table_name='shop_promo_codes' and id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$q = "insert into shop_promo_condition_promo (code_id,condition_id)
					select $new_id,condition_id from shop_promo_condition_promo where code_id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$q = "insert into shop_promo_sa (code_id,saved_id,quantity)
					select $new_id,saved_id,quantity from shop_promo_sa where code_id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$q = "insert into shop_promo_articles (code_id,article_id,quantity,`type`,`usage`,old_price,new_price)
					select $new_id,article_id,quantity,`type`,`usage`,old_price,new_price from shop_promo_articles where code_id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
				$q = "insert into shop_promo_comment (code_id,`comment`,create_date,username)
					select $new_id,comment,create_date,username from shop_promo_comment where code_id=$id";
				$r = $db->query($q);
				if (PEAR::isError($r)) { aprint_r($r); $codes_failed[] = $code; continue;}
                
                if (!empty($freesas)) {
                    foreach ($freesas as $freesa) {
                        $q = "insert into shop_promo_free_sa (code_id, saved_id, quantity, new_price)
                            select $new_id,{$freesa->saved_id},1,0";
                        $r = $db->query($q);
                    }
                }
                
				$codes_ok[$new_id] = $code;
		} // foreach code
		return compact('codes_ok', 'codes_failed', 'new_id');
	}

	function getVoucher($code, $loggedCustomer, $date='NOW()', $cart=''){
		if (!strlen($code)) return;
		$q = "select
				if (date_to_days is not null
					, if(DATEDIFF(date($date), (select date(updated) from total_log where table_name='shop_promo_codes' and field_name='id'
							and tableid=shop_promo_codes.id)) > date_to_days, 1, 0)
					, if(not date($date) between date(date_from) and date(date_to), 1, 0)
				) outdated,
			if(shop_promo_codes.descr_is_name
				, shop_promo_codes.name
				, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_codes'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = shop_promo_codes.id)) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_codes'
				AND field_name = 'html_txt'
				AND language = '".$this->_shop->lang."'
				AND id = shop_promo_codes.id) as html_txt,
				shop_promo_codes.id,
				shop_promo_codes.date_from,
				shop_promo_codes.date_to,
				shop_promo_codes.usage,
				shop_promo_codes.shop_id,
				shop_promo_codes.code,
				shop_promo_codes.min_amount,
				shop_promo_codes.free_article_price_limit,
				shop_promo_codes.free_article_free_shipping,
				shop_promo_codes.days_available,
				shop_promo_codes.type,
				shop_promo_codes.days_send,
				shop_promo_codes.days_remind,
				shop_promo_codes.type_send,
				shop_promo_codes.article_id,
				shop_promo_codes.basegroup,
				shop_promo_codes.landing_page,
				shop_promo_codes.date_to_days,
				shop_promo_codes.is_affili,
				shop_promo_codes.is_requested,
				shop_promo_codes.free_shipping
				, shop_promo_values.percent
				, shop_promo_values.amount
				, shop_promo_codes.usage_customer
				, shop_promo_codes.notforbonus
				, shop_promo_codes.notforshipping
				, (select count(*) from auction where deleted=0 and code_id and code_id=shop_promo_codes.id) total_count
				, (select count(*) from auction where deleted=0 and code_id and code_id=shop_promo_codes.id
					and customer_id=".(int)$loggedCustomer->id.") customer_count
				, shop_promo_codes.usage_notification
				, shop_promo_codes.expiration_notification
				, shop_promo_codes.alt_usage_notification_email
				, shop_promo_codes.alt_expiration_notification_email
				, shop_promo_codes.rma_notif
				, shop_promo_codes.def_source_seller_id
				, shop_promo_codes.min_sa_free
				, shop_promo_codes.min_sa_free_cnt
				, shop_promo_codes.free_article_price_limit
				, shop_promo_codes.free_article_free_shipping
                , shop_promo_codes.free_sa_quantity
			from shop_promo_codes
			join shop_promo_values on shop_promo_codes.id = shop_promo_values.code_id
			where shop_promo_codes.inactive = 0 and code='$code' and shop_promo_codes.inactive = 0
			#and NOW() between date_from and date_to
			and shop_id=".$this->_shop->id;
//		echo $q;
		$r = $this->_dbr->getRow($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		if (!$r->id) {
			//$personal_vouchers = Shop_Catalogue::getPersonalVouchers($this->_db, $this->_dbr, $loggedCustomer->id, $this->_shop->id);
			foreach($personal_vouchers as $pv) {
				if ($pv->voucher_code != $code) continue;
				$q = "select
					if(shop_promo_codes.descr_is_name
				, shop_promo_codes.name
				, (SELECT `value`
						FROM translation
						WHERE table_name = 'shop_promo_codes'
						AND field_name = 'name'
						AND language = '".$this->_shop->lang."'
						AND id = shop_promo_codes.id)) as name,
					(SELECT `value`
						FROM translation
						WHERE table_name = 'shop_promo_codes'
						AND field_name = 'html_txt'
						AND language = '".$this->_shop->lang."'
						AND id = shop_promo_codes.id) as html_txt,
						shop_promo_codes.id,
						shop_promo_codes.date_from,
						shop_promo_codes.date_to,
						shop_promo_codes.usage,
						shop_promo_codes.shop_id,
						shop_promo_codes.code,
						shop_promo_codes.min_amount,
						shop_promo_codes.free_article_price_limit,
						shop_promo_codes.free_article_free_shipping,
						shop_promo_codes.days_available,
						shop_promo_codes.type,
						shop_promo_codes.days_send,
						shop_promo_codes.days_remind,
						shop_promo_codes.type_send,
						shop_promo_codes.article_id
						, shop_promo_values.percent, shop_promo_values.amount
						, {$pv->id} as code_email_id
					from shop_promo_codes
					join shop_promo_values on shop_promo_codes.id = shop_promo_values.code_id
					where shop_promo_codes.inactive = 0
					and shop_promo_codes.id=".$pv->voucher->id;
				$r = $this->_dbr->getRow($q);
				if (date("Y-m-d H:i:s")>$pv->voucher->expire_date){
					$r->outdated = 1;
				}
				if (strlen($pv->auction->end_time)){
					$r->used = 1;
				}
				break;
			}
		}
		if ($r->id) {
			$q = "select
					shop_promo_articles.id
					, shop_promo_articles.code_id
					, shop_promo_articles.article_id
					, shop_promo_articles.quantity
					, shop_promo_articles.`type`
					, shop_promo_articles.`usage`
					, shop_promo_articles.old_price
					, ".($r->percent>0?'shop_promo_articles.old_price*'.(1-($r->percent/100)):'shop_promo_articles.new_price')." as new_price
					, IFNULL(t_name.value,article.name) name
					, IFNULL(t_description.value,article.description) description
				from shop_promo_articles
				join article on article.admin_id=0 and shop_promo_articles.article_id = article.article_id
				left join translation t_name on t_name.table_name = 'article'
					AND t_name.field_name = 'name'
					AND t_name.language = '".$this->_shop->lang."'
					AND t_name.id = article.article_id
				left join translation t_description on t_description.table_name = 'article'
					AND t_description.field_name = 'description'
					AND t_description.language = '".$this->_shop->lang."'
					AND t_description.id = article.article_id
				where code_id=".$r->id;
			$r->free_articles = $this->_dbr->getAll($q);
//			echo $q;
			$r->free_articles_sa = $this->_dbr->getAll("select shop_promo_free_sa.*, sp_auction_name.par_value name
				, sp_offer_id.par_value offer_id
				from shop_promo_free_sa
				join saved_params sp_offer_id on sp_offer_id.saved_id=shop_promo_free_sa.saved_id
					and sp_offer_id.par_key = 'offer_id'
				join saved_params sp_auction_name on sp_auction_name.saved_id=shop_promo_free_sa.saved_id
					and sp_auction_name.par_key = 'auction_name'
				where shop_promo_free_sa.code_id=".$r->id);
#			foreach($r->free_articles_sa as $kk=>$rr) {
#				$r->free_articles_sa[$kk]->input = $this->_dbr->getAssoc("select ");
#			}
			$r->sa_array1 = $this->_dbr->getAssoc("select shop_promo_sa.saved_id f1, shop_promo_sa.saved_id f2
				from shop_promo_sa
				where block=1 and code_id=".$r->id);
			$r->sa_array2 = $this->_dbr->getAssoc("select shop_promo_sa.saved_id f1, shop_promo_sa.saved_id f2
				from shop_promo_sa
				where block=2 and code_id=".$r->id);
			$r->sa_array3 = $this->_dbr->getAssoc("select shop_promo_sa.saved_id f1, shop_promo_sa.saved_id f2
				from shop_promo_sa
				where block=3 and code_id=".$r->id);
			$r->disco_articles_amt = $this->_dbr->getAssoc("select article_id, disco_amt
				from shop_promo_disco_articles where code_id=".$r->id);
			$r->disco_articles_perc = $this->_dbr->getAssoc("select article_id, disco_perc
				from shop_promo_disco_articles where code_id=".$r->id);
		}

		if ($cart!='') {
			foreach($cart->offers as $key => $offer) {
				foreach($offer as $sa_id => $articles) {
					$sas[$sa_id] = $sa_id;
				}
			}

			$allow = true;
			if (count($r->sa_array1)) {
				$allow = false;
				foreach($sas as $saved_id) {
					if (isset($r->sa_array1[$saved_id])) {
						$allow = true;
						break;
					} // if condition accomplished
				} // foreach sa from cart
			} // if SA condition 1 is not empty
			if (!$allow) { return; }

			if (count($r->sa_array2)) {
				$allow = false;
				foreach($sas as $saved_id) {
					if (isset($r->sa_array2[$saved_id])) {
						$allow = true;
						break;
					} // if condition accomplished
				} // foreach sa from cart
			} // if SA condition 2 is not empty
			if (!$allow) { return; }

			if (count($r->sa_array3)) {
				$allow = false;
				foreach($sas as $saved_id) {
					if (isset($r->sa_array3[$saved_id])) {
						$allow = true;
						break;
					} // if condition accomplished
				} // foreach sa from cart
			} // if SA condition 3 is not empty
			if (!$allow) { return; }

		}
        if (PEAR::isError($r->free_articles)) {
            aprint_r($r->free_articles); die();
            return;
        }
		return $r;
	}
    
    function getSALowerThen($price) {
        $price = (int)$price;
        return $this->_dbr->getCol("SELECT DISTINCT(`id`) 
            FROM `sa" . $this->_shop->id . "` 
            WHERE `ShopPrice` < $price 
            GROUP BY `id`");
    }

	function listBonus($lang=''){
		if ($lang=='') $lang=$this->_shop->lang;
		return listBonus($this->_db, $this->_dbr, $this->_shop->username, $lang);
/*		$q = "select id,shop_id,percent,description_url,article_id,ordering,def,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_bonus'
				AND field_name = 'title'
				AND language = '$lang'
				AND id = shop_bonus.id) as title,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_bonus'
				AND field_name = 'description'
				AND language = '$lang'
				AND id = shop_bonus.id) as description
			from shop_bonus
			where country_code=(select ebaycountry from seller_information where username=
					(select username from shop where id=".$this->_shop->id."))
			order by ordering";
		$bonuses = $this->_dbr->getAll($q);
		foreach($bonuses as $k=>$dummy) {
			$bonuses[$k]->description_nl2br =
				strip_tags(str_replace("\r",'',str_replace("\n",'',nl2br($bonuses[$k]->description))));
		}
//		echo $q;
		return $bonuses;*/
	}

	function listBonusGroups($lang = '', $country_code = ''){
		if ($lang=='') $lang=$this->_shop->lang;
        
        $groups = listBonusGroup($this->_db, $this->_dbr, $this->_shop->username, $lang, $country_code);
        
        $groups_ids = array_map(function($v) {return (int)$v->id;}, $groups);
        
        $query = "SELECT shop_bonus.id
            , shop_bonus.shop_id
            , shop_bonus.description_url
            , shop_bonus.article_id
            , shop_bonus.ordering
            , (select def from shop_bonus_seller where bonus_id=shop_bonus.id and username='{$this->_shop->username}') def
            , shop_bonus.inactive
            , shop_bonus.add_date
            , shop_bonus.add_date_exclude_sat
            , shop_bonus.add_date_exclude_sun
            , shop_bonus.add_date_exclude_holi
            , shop_bonus.add_date_days
            , date(date_add(NOW(),interval shop_bonus.add_date_days day)) shipon_date
            , date(date_add(NOW(),interval shop_bonus.add_date_days-1 day)) shipon_date_1
            , date_format(date(date_add(NOW(),interval shop_bonus.add_date_days day)), '{$this->_seller->data->date_format}') shipon_date_formatted
            , shop_bonus.group_id
            , shop_bonus.type_in_group,
            (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_bonus'
                AND field_name = 'title'
                AND language = '$lang'
                AND id = shop_bonus.id) as title,
            (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_bonus'
                AND field_name = 'description'
                AND language = '$lang'
                AND id = shop_bonus.id) as description
            , shop_bonus_seller.percent
            , shop_bonus_seller.amount
            , shop_bonus.donf_offer_overstocked
            , shop_bonus.dont_offer_overstocked_except1
            , (select group_concat(saved_id)
                from saved_params where par_key = CONCAT('bonus_exclude[',shop_bonus.id,']')) excluded_sas
            , (select group_concat(saved_id)
                from shop_bonus_sa where bonus_id=shop_bonus.id) included_sas
            , (select concat('Was set ',IF(shop_bonus.inactive, 'INACTIVE', 'ACTIVE')
                    , ' by ', IFNULL(u.name, tl.username)
                    , ' on ', tl.updated)
                from total_log tl
                left join users u on u.system_username=tl.username
                where tl.table_name='shop_bonus' and tl.field_name='inactive' and tl.tableid=shop_bonus.id
                order by updated desc limit 1) last_inactive
            , shop_bonus_seller.percent seller_percent
            , shop_bonus_seller.amount seller_amount
            from shop_bonus
            left join shop_bonus_seller on shop_bonus_seller.bonus_id=shop_bonus.id
                    and shop_bonus_seller.username='{$this->_shop->username}'
            where shop_bonus.country_code='$country_code'
            and shop_bonus.group_id IN (" . implode(',', $groups_ids) . ") 
            and shop_bonus.inactive=0 
            order by shop_bonus.ordering";
        $bonuses = $this->_dbr->getAll($query);
        foreach($bonuses as $key => $bonus) {
            $bonuses[$key]->description_nl2br =
                strip_tags(str_replace(["\n", "\r"], '', nl2br($bonus->description)));
            $bonuses[$key]->onlyShippingArts = [];
        }
        
        if ($bonuses > 0) {
            $bonusesIds = array_map(function($v) {return (int)$v->id;}, $bonuses);
            
            $queryShippingArts = '
                SELECT `shipping_art_id`, `shop_bonus_id`
                FROM `shop_bonus_shipping_art`
                WHERE `shipping_art_id` AND `shop_bonus_id` IN (' . implode(', ', $bonusesIds) . ')
            ';
            $shippingArtsRaw = $this->_dbr->getAll($queryShippingArts);
            
            $shippingArts = [];
            foreach ($shippingArtsRaw as $shippingArt) {
                $shippingArts[$shippingArt->shop_bonus_id][] = $shippingArt->shipping_art_id;
            }
            
            foreach ($bonuses as $key => $bonus) {
                if (isset($shippingArts[$bonus->id])) {
                    $bonuses[$key]->onlyShippingArts = $shippingArts[$bonus->id];
                }
            }
        }
        
		foreach($groups as $k=>$r) {
            foreach ($bonuses as $key => $bonus) {
                if ($r->id == $bonus->group_id) {
                    $groups[$k]->bonuses[] = $bonus;
                }
            }
		}
//		foreach($groups as $k=>$r) {
//			$groups[$k]->bonuses = listBonus($this->_db, $this->_dbr, $this->_shop->username, $lang, $r->id, '0', $country_code);
//		}
        
		return $groups;
	}

	function makeVoucherPDF($text){
		$tmp = 'tmp';
    	$filename = 'export.pdf';
        $pdf = &File_PDF::factory('P', 'cm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(false);
	    $pdf->addPage();
        $pdf->setFillColor('rgb', 0, 0, 0);
        $pdf->setDrawColor('rgb', 0, 0, 0);
        $pdf->setFont('arial','B', 8);
		$pdf->setLeftMargin(2);
	    $pdf->setXY(1, $y);	$pdf->multiCell(20, 0.5, $text, 0, 'L');
    	$pdf->close();
    	$pdf->save($tmp . '/' . $filename, true);
		$content = file_get_contents($tmp . '/' . $filename);
		return $content;
	}

	static function getPersonalVouchers($db, $dbr, $customer_id, $shop_id=0){
		$vouchers = $dbr->getAll("select email_log.*
			from email_log
			where template='personal_voucher'
			and auction_number=$customer_id");
		$customer = $dbr->getRow("select *
			from customer
			where id=$customer_id");
		foreach($vouchers as $key=>$voucher) {
			$notes = unserialize($voucher->notes);
			$vouchers[$key]->voucher_code = $notes['voucher_code'];
			$q = "select pc.*, shop.name shop_name
				, DATE_ADD('".$voucher->date."', INTERVAL pc.days_available DAY) expire_date
				, DATE(DATE_SUB(DATE_ADD('".$voucher->date."', INTERVAL pc.days_available DAY), INTERVAL pc.days_remind DAY)) remind_date
				,
				if(pc.descr_is_name
				, pc.name
				, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_promo_codes'
				AND field_name = 'name'
				AND language = '".$customer->lang."'
				AND id = pc.id)) as trname
				from shop_promo_codes pc
				join shop on shop.id=pc.shop_id
				where pc.id=".$notes['voucher_id']."";
//			echo $q.'<br>';
			$vouchers[$key]->voucher = $dbr->getRow($q);
			if ($shop_id && $vouchers[$key]->voucher->shop_id!=$shop_id) unset($vouchers[$key]);
			$q = "select * from auction where code_id=".$vouchers[$key]->voucher->id."
				and code_email_id = ".$vouchers[$key]->id."
				and customer_id=$customer_id";
//			echo $q.'<br>';
			$vouchers[$key]->auction = $dbr->getRow($q);
		}
		return $vouchers;
	}

	/**
	 * @return array
     */
	function getPayments() {
		$q = "select
			pm.id, pm.code,  
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'shipping_name'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as shipping_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'fee_name'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as fee_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'afterwards_message'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as afterwards_message,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'additional_fields'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as additional_fields,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_payment_method'
				AND field_name = 'note'
				AND language = '".$this->_shop->lang."'
				AND id = spm.id) as `note`,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'comment'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as `comment`,
			spm.allow,
			IFNULL(spm.fee,pm.fee) fee,
			spm.fee_amt,
			IFNULL(spm.icon,pm.icon) icon,
			spm.def,
			spm.id spmid
		from payment_method pm
		left join shop_payment_method spm on spm.payment_method_id=pm.id and spm.shop_id=".$this->_shop->id."
		where spm.allow and pm.hide_in_shop = 0
		order by spm.ordering, pm.id
		";
		$payment_methods = $this->_dbr->getAll($q);
		foreach($payment_methods as $k=>$r) {
			$payment_methods[$k]->note = str_replace("\n",'<br>',$payment_methods[$k]->note);
			$payment_methods[$k]->comment = str_replace("\n",'<br>',$payment_methods[$k]->comment);
		}
		if(isset($_COOKIE['shop_login_by']) && $_COOKIE['shop_login_by'] == 'masterpass'){
			$method = [];
			foreach($payment_methods as $v){
				if($v->code === 'master_shp'){
					$method[] = $v;
					break;
				}
			}
			if(!empty($method)){
				$payment_methods = $method;
				$payment_methods[0]->def = 1;
			}

		}
		return $payment_methods;
	}

	function getPaymentByCode($code) {
		$payment_method = $this->_dbr->getRow("select
			pm.id, pm.code, pm.TS_code,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'shipping_name'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as shipping_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'fee_name'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as fee_name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'afterwards_message'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as afterwards_message,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'payment_method'
				AND field_name = 'additional_fields'
				AND language = '".$this->_shop->lang."'
				AND id = pm.id) as additional_fields,
			spm.allow,
			IFNULL(spm.fee,pm.fee) fee,
			spm.fee_amt,
			IFNULL(spm.icon,pm.icon) icon
		from payment_method pm
		left join shop_payment_method spm on spm.payment_method_id=pm.id and spm.shop_id=".$this->_shop->id."
		where spm.allow and pm.code='$code'
		order by pm.id
		");
		return $payment_method;
	}

	function listPartners(){
		return $this->_dbr->getAll("select shop_partners.id,
			shop_partners.shop_id,
			shop_partners.description,
			shop_partners.url,
			shop_partners.ordering,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_partners'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_partners.id) as title
			from shop_partners
			where shop_partners.shop_id=".$this->_shop->id."
			order by shop_partners.ordering");
	}

	function listLogos($inactive='0', $block_id=0){
		if ($block_id) $where = " and shop_logos.block_id=$block_id ";
		$q = "select shop_logos.id,
			shop_logos.shop_id,
			shop_logos.name,
			shop_logos.ordering,
			shop_logos.inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_logos'
				AND field_name = 'description'
				AND language = '".$this->_shop->lang."'
				AND id = shop_logos.id) as description,
			(SELECT `tf`.`md5`
				FROM prologis_log.translation_files2 AS `tf`
				WHERE tf.table_name = 'shop_logos'
				AND tf.field_name = 'url'
				AND tf.language = '".$this->_shop->lang."'
				AND tf.id*1 = shop_logos.id) as file,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_logos'
				AND field_name = 'url'
				AND language = '".$this->_shop->lang."'
				AND id = shop_logos.id) as url,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_logos'
				AND field_name = 'alt'
				AND language = '".$this->_shop->lang."'
				AND id = shop_logos.id) as alt,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_logos'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_logos.id) as title,
			(SELECT substring_index(`value`, '.', -1)
				FROM translation
				WHERE table_name = 'shop_logos'
				AND field_name = 'logo'
				AND language = '".$this->_shop->lang."'
				AND id = shop_logos.id) as ext
				, shop_logo_block.code block
			from shop_logos
			left join shop_logo_block on shop_logo_block.id=shop_logos.block_id
			where shop_logos.shop_id=".$this->_shop->id."
			and shop_logos.inactive in ($inactive)
			$where
			order by shop_logos.ordering";
//		echo $q;
        $logos = [];
        foreach ($this->_dbr->getAll($q) as $_logo) {
            $_logo->file = get_file_path($_logo->file);
            $logos[] = $_logo;
        }

		return $logos;
	}

	function listLogoBlocks($inactive='0'){
		$r = $this->_dbr->getAll("select shop_logo_block.id,
			shop_logo_block.shop_id,
			shop_logo_block.code,
			shop_logo_block.ordering,
			shop_logo_block.inactive,
			shop_logo_block.show_everywhere,
			shop_logo_block.show_in_checkout_pages,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_logo_block'
				AND field_name = 'title'
				AND language = '".$this->_shop->lang."'
				AND id = shop_logo_block.id) as title
			from shop_logo_block
			where shop_logo_block.shop_id=".$this->_shop->id."
			and shop_logo_block.inactive in ($inactive)
			order by shop_logo_block.ordering");
		if (PEAR::isError($r)) { aprint_r($r); die();}
		return $r;
	}

    static function getDocs($db, $dbr, $shop_id, $lang='', $deflang='', $table_name='shop_doc', $key_name='shop_id')
    {
		global $smarty;
		$function = "Shop_catalogue::getDocs($shop_id, $lang, $deflang, $table_name, $key_name)";
		$chached_ret = cacheGet($function, $shop_id, $lang);
		$chached_ret_r = cacheGet($function.'_r', $shop_id, $lang);
		if (0 && $chached_ret && $chached_ret_r) {
            $smarty->assign('data_translations', $chached_ret_r);
            return $chached_ret;
		}
		$q = "SELECT *
			from $table_name
			where $key_name=$shop_id";
        $docs = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
			die();
        }
		$fld = 'data';
		$r = array();
		foreach ($docs as $key=>$doc) {
			$r[$doc->doc_id] = $dbr->getAssoc("select language, iid from translation where id={$doc->doc_id}
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
			$docs[$key]->type = $r[$doc->doc_id][$lang]->type;
			$docs[$key]->alt = $r[$doc->doc_id][$lang]->alt;
			$docs[$key]->title = $r[$doc->doc_id][$lang]->title;
			if (!$docs[$key]->type) $docs[$key]->type = $r[$doc->doc_id][$deflang]->type;
			if (!$docs[$key]->alt) $docs[$key]->alt = $r[$doc->doc_id][$deflang]->alt;
			if (!$docs[$key]->title) $docs[$key]->title = $r[$doc->doc_id][$deflang]->title;
			$docs[$key]->version = $r[$doc->doc_id][$lang]->version;
		}
		$smarty->assign('data_translations', $r);
		cacheSet($function.'_r', $shop_id, $lang, $r);
		cacheSet($function, $shop_id, $lang, $docs);
        return $docs;
    }

    static function addLargeDoc($db, $dbr, $shop_id,
		$name, $fn, $lang, $edit_id=0, $table_name='shop_doc', $key_name='shop_id')
    {
		$name = mysql_escape_string($name);
		if (! $edit_id) {
			$r = $db->query("INSERT INTO $table_name SET name='$name', $key_name=$shop_id");
			$edit_id = mysql_insert_id();
        }

		$fld = 'data';

		$iid = (int)$dbr->getOne("select iid from translation where id=$edit_id
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
		if ($iid) {
			$q = "update translation set value='$name' where iid='$iid'";
		}
        else {
			$q = "insert into translation set value='$name'
			, id=$edit_id
			, table_name='$table_name' , field_name='$fld'
			, language = '$lang'";
		}

		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}

		update_version($table_name, $fld, $edit_id, $lang);

		$iid = (int)$dbr->getOne("select iid
			from prologis_log.translation_files2 where id='$edit_id'
			and table_name='$table_name' and field_name='$fld' and language = '$lang'");

        $content = file_get_contents($fn);
        $md5 = md5($content);
        $filename = set_file_path($md5);
        if ( ! is_file($filename)) {
            file_put_contents($filename, $content);
        }

		if ($iid) {
			$r = $db->query("update prologis_log.translation_files2 set md5='$md5' where id=$iid");
		}
        else {
			$r = $db->query("insert into prologis_log.translation_files2 set
					id=$edit_id,
					table_name='$table_name' , field_name='$fld',
					language = '$lang',
					md5='$md5'");
		}
		if (PEAR::isError($r)) { aprint_r($r); die();}

		$q = "select distinct id, ftp_server,  ftp_password, ftp_username
			from shop
			where id=$shop_id
			";
		$shops = $dbr->getAll($q);
		foreach($shops as $shop) {
			$conn_id = ftp_connect($shop->ftp_server);
#			echo 'connect to '.$shop->ftp_server.'<br>';
			ftp_login ($conn_id, $shop->ftp_username, $shop->ftp_password);
			$mode = ftp_pasv($conn_id, TRUE);
			$buff = ftp_nlist($conn_id, "public_html/images/cache/*shopurl_*".$shop->url."_*.*");
#			echo 'try to find '."public_html/images/cache/*shopurl_*".$shop->url."_*.*".'<br>';
			foreach($buff as $fn) {
				if (strpos($fn, "shopurl")) {
					$r = ftp_delete($conn_id, $fn);
#					echo 'deleted '.$fn.'<br>';
				}
			}
			$buff = ftp_nlist($conn_id, "public_html/images/cache/*src_shop_picid_".$edit_id."_*.*");
			foreach($buff as $fn) {
				if (strpos($fn, "picid_".$edit_id)) {
					$r = ftp_delete($conn_id, $fn);
#					echo 'delete FTP'.$fn.'<br>';
				}
			}
			ftp_close($conn_id);
			foreach (glob("images/cache/*shopurl_*".$shop->url."_*.*") as $filename) {
			    unlink($filename);
#				echo 'delete file '.$filename.'<br>';
			}
		}
#		echo " try to find images/cache/*src_shop_picid_".$edit_id."_*.*<br>";
		foreach (glob("images/cache/*src_shop_picid_".$edit_id."_*.*") as $filename) {
		    unlink($filename);
			echo 'delete file '.$filename.'<br>';
		}
#		die();
		return $edit_id;
    }

//    static function addLargeDoc($db, $dbr, $shop_id,
//		$name,
//		$fn, $lang, $edit_id=0, $table_name='shop_doc', $key_name='shop_id'
//		)
//    {
//		$name = mysql_escape_string($name);
//  		global $db_user;
//  		global $db_pass;
//		global $db_name;
//		global $db_host_no_port;
//		$pass = escapeshellcmd($db_pass);
//		$user = escapeshellcmd($db_user);
//		$newfn = $fn.'.sql';
//		if (!$edit_id) {
//			$r = $db->query("insert into $table_name set name='$name',
//					$key_name=$shop_id");
//			$edit_id = mysql_insert_id();
//		}
//		$fld = 'data';
//		$iid = (int)$dbr->getOne("select iid from translation where id=$edit_id
//			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
//		if ($iid) {
//			$q = "update translation set value='$name' where iid='$iid'";
//		} else {
//			$q = "insert into translation set value='$name'
//			, id=$edit_id
//			, table_name='$table_name' , field_name='$fld'
//			, language = '$lang'";
//		}
//		$r = $db->query($q);
//		if (PEAR::isError($r)) { aprint_r($r); die();}
//
//				update_version($table_name, $fld, $edit_id, $lang);
//
//		$iid = (int)$dbr->getOne("select iid
//			from prologis_log.translation_files2 where id='$edit_id'
//			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
//		$md5 = md5(base64_encode(file_get_contents($fn)));
//		$data_id = (int)$dbr->getOne("select id from prologis_log.data_storage where md5sum='$md5'");
//		if (!$data_id) {
//			$m = file_put_contents($newfn, "insert into prologis_log.data_storage set
//					md5sum='$md5',
//					value='".base64_encode(file_get_contents($fn))."'");
//		}
//		file_put_contents('tmp/pic.sql', file_get_contents($newfn));
//		$m = exec("mysql -u $user --password=$pass -h $db_host_no_port $db_name < $newfn");
//		unlink($newfn);
//		if (!$data_id) $data_id = (int)$db->getOne("select id from prologis_log.data_storage where md5sum='$md5'");
//		if ($iid) {
//			$r = $db->query("update prologis_log.translation_files2 set data_id=$data_id where id=$iid");
//		} else {
//			$r = $db->query("insert into prologis_log.translation_files2 set
//					id=$edit_id,
//					table_name='$table_name' , field_name='$fld',
//					language = '$lang',
//					data_id=$data_id");
//		}
//		if (PEAR::isError($r)) { aprint_r($r); die();}
//		$q = "select distinct id, ftp_server,  ftp_password, ftp_username
//			from shop
//			where id=$shop_id
//			";
//		$shops = $dbr->getAll($q);
//		foreach($shops as $shop) {
//			$conn_id = ftp_connect($shop->ftp_server);
//#			echo 'connect to '.$shop->ftp_server.'<br>';
//			ftp_login ($conn_id, $shop->ftp_username, $shop->ftp_password);
//			$mode = ftp_pasv($conn_id, TRUE);
//			$buff = ftp_nlist($conn_id, "public_html/images/cache/*shopurl_*".$shop->url."_*.*");
//#			echo 'try to find '."public_html/images/cache/*shopurl_*".$shop->url."_*.*".'<br>';
//			foreach($buff as $fn) {
//				if (strpos($fn, "shopurl")) {
//					$r = ftp_delete($conn_id, $fn);
//#					echo 'deleted '.$fn.'<br>';
//				}
//			}
//			$buff = ftp_nlist($conn_id, "public_html/images/cache/*src_shop_picid_".$edit_id."_*.*");
//			foreach($buff as $fn) {
//				if (strpos($fn, "picid_".$edit_id)) {
//					$r = ftp_delete($conn_id, $fn);
//#					echo 'delete FTP'.$fn.'<br>';
//				}
//			}
//			ftp_close($conn_id);
//			foreach (glob("images/cache/*shopurl_*".$shop->url."_*.*") as $filename) {
//			    unlink($filename);
//#				echo 'delete file '.$filename.'<br>';
//			}
//		}
//#		echo " try to find images/cache/*src_shop_picid_".$edit_id."_*.*<br>";
//		foreach (glob("images/cache/*src_shop_picid_".$edit_id."_*.*") as $filename) {
//		    unlink($filename);
//			echo 'delete file '.$filename.'<br>';
//		}
//#		die();
//		return $edit_id;
//    }

    static function addDocAlt($db, $dbr, $id,
		$lang, $edit_id=0
		, $alt='', $title='', $table_name='shop_doc'
		)
    {
		$alt = mysql_escape_string($alt);
		$title = mysql_escape_string($title);
		$r = $db->query($q);
		$iid_alt = (int)$dbr->getOne("select iid from translation where id=$edit_id
			and table_name='$table_name' and field_name='alt' and language = '$lang'");
		if ($iid_alt) {
			$q = "update translation set value='$alt' where iid=$iid_alt";
		} else {
			$q = "insert into translation set value='$alt'
			, id=$edit_id
			, table_name='$table_name' , field_name='alt'
			, language = '$lang'";
		}
//		echo $q.'<br>';
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		$iid_title = (int)$dbr->getOne("select iid from translation where id=$edit_id
			and table_name='$table_name' and field_name='title' and language = '$lang'");
		if ($iid_title) {
			$q = "update translation set value='$title' where iid=$iid_title";
		} else {
			$q = "insert into translation set value='$title'
			, id=$edit_id
			, table_name='$table_name' , field_name='title'
			, language = '$lang'";
		}
		$r = $db->query($q);
		if (PEAR::isError($r)) { aprint_r($r); die();}
		return $edit_id;
    }

    static function deleteDoc($db, $dbr, $doc_id, $lang='', $table_name='shop_doc')
    {
        $doc_id = (int)$doc_id;
		$edit_id = $doc_id;
		if ($lang=='') {
	        $r = $db->query("delete from $table_name where doc_id=$doc_id");
			if (PEAR::isError($r)) { aprint_r($r); die();}
			$langwhere = "";
		} else {
			$langwhere = " and language = '$lang'";
		}
	    $r = $db->query("delete from prologis_log.translation_files2 where id='$edit_id'
			and table_name='$table_name' $langwhere");
		if (PEAR::isError($r)) { aprint_r($r); die();}
	    $r = $db->query("delete from translation where id=$edit_id
			and table_name='$table_name' $langwhere");
		if (PEAR::isError($r)) { aprint_r($r); die();}
    }

	function listSearchDefs(){
		return $this->_dbr->getAll("select shop_search_defs.id,
			shop_search_defs.shop_id,
			shop_search_defs.ordering,
			shop_search_defs.inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_search_defs'
				AND field_name = 'keyword'
				AND language = '".$this->_shop->lang."'
				AND id = shop_search_defs.id) as keyword,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_search_defs'
				AND field_name = 'text'
				AND language = '".$this->_shop->lang."'
				AND id = shop_search_defs.id) as `text`
			from shop_search_defs
			where shop_search_defs.shop_id=".$this->_shop->id."
			order by shop_search_defs.ordering");
	}

	function getSearchDef($id){
		return $this->_dbr->getRow("select shop_search_defs.id,
			shop_search_defs.shop_id,
			shop_search_defs.ordering,
			shop_search_defs.inactive,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_search_defs'
				AND field_name = 'keyword'
				AND language = '".$this->_shop->lang."'
				AND id = shop_search_defs.id) as keyword,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_search_defs'
				AND field_name = 'text'
				AND language = '".$this->_shop->lang."'
				AND id = shop_search_defs.id) as `text`
			from shop_search_defs
			where shop_search_defs.shop_id=".$this->_shop->id." and shop_search_defs.id=$id");
	}

	function getAffiliVoucher($promo_code, $loggedCustomer){
		require_once("affili/AffiliPrintCom.php");
		$talkToAffiliprint = new AffiliPrintCom;
		$talkToAffiliprint->myConfig->AFFILIATEUID = $this->_seller->data->AffiliateUID;
		if (!$talkToAffiliprint->localValidateBonuscode($promo_code)) return false;
//		echo 'localValidateBonuscode for '.$promo_code.' passed<br>';
		if (!$talkToAffiliprint->remoteValidateBonuscode($promo_code)) return false;
//		echo 'remoteValidateBonuscode for '.$promo_code.' passed<br>';
		$parent_code = $talkToAffiliprint->getCampaignUid();
//		echo '$parent_code='.$parent_code;
		$parent_voucher = $this->getVoucher($parent_code, $loggedCustomer);
		if ($parent_voucher) $parent_voucher->affili = 1;
		if ($parent_voucher->is_affili) return $parent_voucher;
			else return;
	}

	function redeemAffiliVoucher($promo_code, $basketValue="0", $orderUid = "0", $orderInfo = ""){
		require_once("affili/AffiliPrintCom.php");
		$talkToAffiliprint = new AffiliPrintCom;
		$talkToAffiliprint->myConfig->AFFILIATEUID = $this->_seller->data->AffiliateUID;
		if ($talkToAffiliprint->remoteRedeemBonuscode($promo_code, $basketValue, $orderUid, $orderInfo)) {
//			echo "Redemption OK<br>";
		} else {
			echo "Redemption $promo_code not successful<br>";
			die();
		}
	}

	function getContentID($alias){
		return (int)$this->_dbr->getOne("SELECT translation.id
				FROM translation
				JOIN shop_content on shop_content.id=translation.id
				JOIN shop_content_shop on shop_content.id=shop_content_shop.shop_content_id
				WHERE shop_content_shop.inactive=0
                    AND shop_content_shop.shop_id = '" . (int)$this->_shop->id."'
                    AND translation.table_name = 'shop_content'
                    AND translation.field_name = 'alias'
                    AND translation.`value` = '" . mysql_escape_string($alias) . "'
				LIMIT 1");
	}

	function checkContentID($alias){
		$query = "SELECT translation.language, translation.id
				FROM translation
				JOIN shop_content on shop_content.id=translation.id
				JOIN shop_content_shop on shop_content.id=shop_content_shop.shop_content_id
				WHERE shop_content_shop.inactive=0
                    AND shop_content_shop.shop_id = '" . (int)$this->_shop->id."'
                    AND translation.table_name = 'shop_content'
                    AND translation.field_name = 'alias'
                    AND translation.`value` = '" . mysql_escape_string($alias) . "'
				";

		$items = $this->_dbr->getAssoc($query);
        foreach ($items as $lang => $dummy)
        {
            if ($lang == $this->_shop->lang)
            {
                return false;
            }
        }
        
		$langs = array_keys($items);
		return $langs[0];
    }

	function getNewsID($alias) {
		return (int)$this->_dbr->getOne("SELECT translation.id
				FROM translation
				JOIN shop_news on shop_news.id=translation.id
				WHERE 1
                    AND shop_news.shop_id = " . (int)$this->_shop->id . "
                    AND translation.table_name = 'shop_news'
                    AND translation.field_name = 'alias'
                    AND translation.`value` = '" . mysql_escape_string($alias) . "'
				LIMIT 1");
	}

	function checkNewsID($alias) {
		$query = "SELECT translation.language, translation.id
				FROM translation
				JOIN shop_news on shop_news.id=translation.id
				WHERE 1
                    AND shop_news.shop_id = " . (int)$this->_shop->id . "
                    AND translation.table_name = 'shop_news'
                    AND translation.field_name = 'alias'
                    AND translation.`value` = '" . mysql_escape_string($alias) . "'
				";

		$items = $this->_dbr->getAssoc($query);
        foreach ($items as $lang => $dummy)
        {
            if ($lang == $this->_shop->lang)
            {
                return false;
            }
        }
        
		$langs = array_keys($items);
		return $langs[0];
	}

	function getPartnerID($alias){
		$q = "SELECT id
				FROM shop_promo_partner
				WHERE title = '".mysql_escape_string($alias)."'
				and shop_id = ".$this->_shop->id."
				limit 1";
		return (int)$this->_dbr->getOne($q);
	}

	function getServiceID($alias, $parent_alias='', $parent_parent_alias=''){
		global $shop_ids;
		$shop_ids[] = $this->_shop->id;
        
		if ($this->_shop->services_shop_id && !in_array($this->_shop->services_shop_id, $shop_ids)) 
        {
			$shop_id = $this->_shop->services_shop_id;
		} 
        else 
        {
			$shop_id = $this->_shop->id;
		}
        
        $parent_join = '';
        if ($parent_alias)
        {
            $parent_join = "
				join shop_service parent on shop_service.parent_id=parent.id
				join translation tparent on tparent.table_name = 'shop_service'
					AND tparent.field_name = 'alias'
					AND tparent.language = translation.language
					AND tparent.`value` = '".mysql_escape_string($parent_alias)."'
					and tparent.id = parent.id
            ";
        }
        
        $parent_parent_join = '';
        if ($parent_alias && $parent_parent_alias)
        {
            $parent_parent_join = "
				join shop_service parent_parent on parent.parent_id=parent_parent.id
				join translation tparent_parent on tparent_parent.table_name = 'shop_service'
					AND tparent_parent.field_name = 'alias'
					AND tparent_parent.language = translation.language
					and tparent_parent.id = parent_parent.id
					and tparent_parent.`value` = '".mysql_escape_string($parent_parent_alias)."'
            ";
        }
        
		$query = "SELECT translation.id
				FROM translation
				join shop_service on shop_service.id=translation.id
				
                $parent_join
                $parent_parent_join

				WHERE 1
				AND shop_service.shop_id = $shop_id
				AND translation.table_name = 'shop_service'
				AND translation.field_name = 'alias'
				AND translation.`value` = '".mysql_escape_string($alias)."'
				limit 1";
        
		return (int)$this->_dbr->getOne($query);
	}
    
	function checkServiceID($alias, $parent_alias='', $parent_parent_alias=''){
		global $shop_ids;
		$shop_ids[] = $this->_shop->id;
        
		if ($this->_shop->services_shop_id && !in_array($this->_shop->services_shop_id, $shop_ids)) 
        {
			$shop_id = $this->_shop->services_shop_id;
		} 
        else 
        {
			$shop_id = $this->_shop->id;
		}
        
        $parent_join = '';
        if ($parent_alias)
        {
            $parent_join = "
				join shop_service parent on shop_service.parent_id=parent.id
				join translation tparent on tparent.table_name = 'shop_service'
					AND tparent.field_name = 'alias'
					AND tparent.language = translation.language
					AND tparent.`value` = '".mysql_escape_string($parent_alias)."'
					and tparent.id = parent.id
            ";
        }
        
        $parent_parent_join = '';
        if ($parent_alias && $parent_parent_alias)
        {
            $parent_parent_join = "
				join shop_service parent_parent on parent.parent_id=parent_parent.id
				join translation tparent_parent on tparent_parent.table_name = 'shop_service'
					AND tparent_parent.field_name = 'alias'
					AND tparent_parent.language = translation.language
					and tparent_parent.id = parent_parent.id
					and tparent_parent.`value` = '".mysql_escape_string($parent_parent_alias)."'
            ";
        }
        
		$query = "SELECT translation.language, translation.id
				FROM translation
				join shop_service on shop_service.id=translation.id
				
                $parent_join
                $parent_parent_join

				WHERE 1
				AND shop_service.shop_id = $shop_id
				AND translation.table_name = 'shop_service'
				AND translation.field_name = 'alias'
				AND translation.`value` = '".mysql_escape_string($alias)."'";
        
        $items = $this->_dbr->getAssoc($query);
        foreach ($items as $lang => $dummy)
        {
            if ($lang == $this->_shop->lang)
            {
                return false;
            }
        }
        
		$langs = array_keys($items);
		return $langs[0];
	}

	function getCatalogueID($alias, $parent_id=0){
		global $debug;
		$q = "SELECT translation.id, translation.language
				FROM translation
				join shop_catalogue on shop_catalogue.id=translation.id
				join shop_catalogue_shop on shop_catalogue.id=shop_catalogue_shop.shop_catalogue_id
				WHERE 1
				AND shop_catalogue_shop.shop_id = ".$this->_shop->id."
				AND translation.table_name = 'shop_catalogue'
				AND translation.field_name = 'alias'
#				AND translation.language = '".$this->_shop->lang."'
				AND translation.`value` = '".mysql_escape_string($alias)."'
				and shop_catalogue.parent_id = $parent_id
				#and shop_catalogue_shop.hidden=0
				#and shop_catalogue.hidden=0
				#take the shop language in priority
				order by IF(translation.language = '".$this->_shop->lang."',0,1), shop_catalogue_shop.hidden, shop_catalogue.hidden
				limit 1";
		if ($debug) echo '<br>'.$q.'<br>';
		$res = $this->_dbr->getRow($q);
		$this->_cat_language = $res->language;
#		if ((int)$res->id) {
#			$this->_shop->lang = $res->language;
#		}
		return (int)$res->id;
	}

	function checkCatalogueID($alias, $parent_id=0){
		$query = "SELECT translation.language, translation.id
				FROM translation
				JOIN shop_catalogue on shop_catalogue.id=translation.id
				JOIN shop_catalogue_shop on shop_catalogue.id=shop_catalogue_shop.shop_catalogue_id
				WHERE 1
                    AND shop_catalogue_shop.shop_id = " . $this->_shop->id . "
                    AND translation.table_name = 'shop_catalogue'
                    AND translation.field_name = 'alias'
                    AND translation.`value` = '".mysql_escape_string($alias)."'
    				and shop_catalogue.parent_id = '$parent_id'
				";

		$items = $this->_dbr->getAssoc($query, null, [$alias]);
        foreach ($items as $lang => $dummy)
        {
            if ($lang == $this->_shop->lang)
            {
                return false;
            }
        }
        
		$langs = array_keys($items);
		return $langs[0];
	}

	function getItemID($alias){
		$query = "SELECT sa.id, language
                FROM sa{$this->_shop->id} sa
				left join sa_all master_sa on sa.master_sa=master_sa.id
				join translation on translation.id=IF(IFNULL(sa.master_ShopSAAlias, 1), IFNULL(master_sa.id,sa.id), sa.id)
				WHERE 1
				AND translation.table_name = 'sa'
				AND translation.field_name = 'ShopSAAlias'
				AND translation.`value` = ?
				limit 1";

		$item = $this->_dbr->getRow($query, null, [$alias]);
		$this->_item_language = $item->language;
        $this->_alias = $alias;
		return (int)$item->id;
	}
    
	function checkItemID($alias){
		$query = "SELECT `language`, `sa`.`id`
                FROM `sa{$this->_shop->id}` `sa`
				LEFT JOIN `sa_all` `master_sa` ON `sa`.`master_sa` = `master_sa`.`id`
				JOIN `translation` ON `translation`.`id` = IF(
                    IFNULL(`sa`.`master_ShopSAAlias`, 1), 
                    IFNULL(`master_sa`.`id`, `sa`.`id`), 
                    `sa`.`id`)
				WHERE 1
				AND `translation`.`table_name` = 'sa'
				AND `translation`.`field_name` = 'ShopSAAlias'
				AND `translation`.`value` = ?
				";

		$items = $this->_dbr->getAssoc($query, null, [$alias]);

        foreach ($items as $lang => $dummy)
        {
            if ($lang == $this->_shop->lang)
            {
                return false;
            }
        }
        
		$langs = array_keys($items);
		return $langs[0];
	}

    /**
     * 
     * Get last catalogue alias, for offerer
     * 
     * @param string $alias
     * @return boolean|string
     */
    function getInactiveOfferCatalogue($alias) {
        $cat_id = (int)$this->_dbr->getOne("
                SELECT MAX( CAST(`sa`.`par_value` AS UNSIGNED) ) AS `cat_id`
                FROM `saved_params` AS `sa`
                JOIN `translation` ON `translation`.`id`=`sa`.`saved_id`
                    AND `translation`.`table_name` = 'sa'
                    AND `translation`.`field_name` = 'ShopSAAlias'
                    AND `translation`.`value` = ?
                WHERE `sa`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]'
            ", null, [$alias]);
                            
        if ( ! $cat_id) {
            $cat_id = (int)$this->_dbr->getOne("
                SELECT MAX( CAST(`catalogue`.`par_value` AS UNSIGNED) ) AS `cat_id`

                FROM `saved_params` `sa`
                JOIN `saved_params` `master_sa` ON `master_sa`.`saved_id` = `sa`.`par_value` AND `sa`.`par_key` = 'master_sa'
                JOIN `saved_params` AS `username` ON `username`.`saved_id` = `sa`.`saved_id` AND `username`.`par_key` = 'username'
                JOIN `saved_params` AS `siteid` ON `siteid`.`saved_id` = `sa`.`saved_id` AND `siteid`.`par_key` = 'siteid'
                JOIN `saved_params` AS `catalogue` ON `catalogue`.`saved_id` = `sa`.`saved_id` AND `catalogue`.`par_key` = 'shop_catalogue_id[{$this->_shop->id}]'

                JOIN `translation` ON `translation`.`id` = `master_sa`.`saved_id`
                    AND `translation`.`table_name` = 'sa'
                    AND `translation`.`field_name` = 'ShopSAAlias'
                    AND `translation`.`value` = ?

                WHERE `username`.`par_value` = '{$this->_shop->username}'
                    AND `siteid`.`par_value` = '{$this->_shop->siteid}'
            ", null, [$alias]);
        }
        
        if ( ! $cat_id) {
            return false;
        }
            
        $cat_route = '/';
        $cat_array = $this->getAllNodesRecs($cat_id);
        foreach ($cat_array as $catname) {
            if (!isset($catname->alias) || empty($catname->alias)) {
                return false;
            } else {
                $cat_route .= $catname->alias . '/';
            }
        }

        return $cat_route;
    }

	function getAllItemID($alias){
		$q = "select sa.id from saved_auctions `sa`
			join `saved_params` `sp_shop_catalogue_id` on `sp_shop_catalogue_id`.`saved_id` = `sa`.`id`
			and `sp_shop_catalogue_id`.`par_key` = 'shop_catalogue_id[".$this->_shop->id."]'
			join translation on translation.id=sa.id
				AND translation.table_name = 'sa'
				AND translation.field_name = 'ShopSAAlias'
#				AND translation.language = '".$this->_shop->lang."'
				AND translation.`value` = '".mysql_escape_string($alias)."'
				limit 1";
//		echo '<br>'.$q; die();
		return (int)$this->_dbr->getOne($q);
	}

	function getAllShopCountries() {
		return $this->_dbr->getAll("select distinct ebaycountry code, country.name, shop_dontship.shop_id
			from shop
			join seller_information si on si.username=shop.username
			join country on country.code=si.ebaycountry
			left join shop_dontship on shop_dontship.shop_id=".$this->_shop->id." and country.code=shop_dontship.country_code
			");

	}

	function getAvailableShop4Country($country_code) {
		return $this->_dbr->getAll("select shop.*
			from shop
			join seller_information si on si.username=shop.username
			join country on country.code=si.ebaycountry
			where not exists (select null from shop_dontship
				where shop_id=shop.id and shop_dontship.country_code='$country_code')
			");

	}

	function getAllSkinsArray() {
		return $this->_dbr->getAssoc("select distinct ss.id, ss.name
			from shop_skin ss
			");

	}

	function getAllSkins() {
		return $this->_dbr->getAll("select distinct ss.*, sss.shop_id
			from shop_skin ss
			left join shop_skin_shop sss on sss.shop_id=".$this->_shop->id." and sss.skin_id=ss.id
			");

	}

	function getAvailableSkins() {
		return $this->_dbr->getAll("select distinct ss.*, sss.shop_id
			, (select `value` from translation where table_name='shop_skin' and field_name='title'
				and id=ss.id and language='".$this->_shop->lang."') title
			from shop_skin ss
			join shop_skin_shop sss on sss.shop_id=".$this->_shop->id." and sss.skin_id=ss.id
			");
	}

	function getSkin($name) {
		return $this->_dbr->getRow("select ss.*
			, (select `value` from translation where table_name='shop_skin' and field_name='title'
				and id=ss.id and language='".$this->_shop->lang."') title
			from shop_skin ss
			join shop_skin_shop sss on sss.shop_id=".$this->_shop->id." and sss.skin_id=ss.id
			where ss.name='".mysql_escape_string($name)."'
			");
	}

	function getOverallRatingArray($cat_id=0) {
		$ratings = $this->getRating(0, $cat_id, true);
		$ratings->id = 160+round($ratings->avg);
		$ratings->rating_text = $this->_shop->english_shop[$ratings->id];
		$ratings->avg_int = number_format($ratings->avg,2);
		return $ratings;
	}

    /**
     * Generate html to show rating
     * @param int $page number of page for pagination
     * @param int $cat_id category identifier, of 0 - whole shop
     * @return string ready-to-use html
     */
	function getOverallRatings($page = 1, $cat_id = 0) {
		global $smarty;
        
        $function = "getOverallRatings($page,$cat_id)";
        $chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
        if ($chached_ret)
        {
            return $chached_ret;
        }
        
		$rating_statistic = $this->getRating(0, $cat_id, true);
		$smarty->assign("rating_statistic", $rating_statistic);
        $limit = !$this->mobile ? $this->_shop->questions_per_page : $this->_shop->questions_per_page_mobile;
        $qrystr = "SELECT * FROM (
                SELECT DISTINCT
                    `af`.`auction_number`
                    , `af`.`txnid`
                    , `af`.`type`
                    , `af`.`code`
                    , `af`.`id`
                    , DATE(`af`.`datetime`) `datetime`
                    , `af`.`text`
                    , `af`.`display_name`
                    , `au_name`.`value` `name`
                    , `au_firstname`.`value` `firstname`
                    , CONCAT(ROUND(100*`af`.`code` / 5), '%') `perc`

                FROM `auction_feedback` `af`
                JOIN `auction` `au` ON `af`.`auction_number` = `au`.`auction_number` 
                    AND `af`.`txnid` = `au`.`txnid`
                LEFT JOIN `auction` `subau` ON `subau`.`main_auction_number` = `au`.`auction_number` 
                    AND `subau`.`main_txnid` = `au`.`txnid`
                JOIN `saved_auctions` AS `sa` ON `sa`.`id` = `subau`.`saved_id`
                JOIN `auction_par_varchar` `au_name` ON `au_name`.`auction_number` = `au`.`auction_number` 
                    AND `au_name`.`txnid` = `au`.`txnid`
                    AND `au_name`.`key` = 'name_invoice'
                JOIN `auction_par_varchar` `au_firstname` ON `au_firstname`.`auction_number` = `au`.`auction_number` 
                    AND `au_firstname`.`txnid` = `au`.`txnid`
                    AND `au_firstname`.`key` = 'firstname_invoice'

                WHERE NOT `au`.`hiderating` 
                    AND type='received' 
                    AND NOT `af`.`hidden` 
                    AND NOT `sa`.`inactive` 
                    AND `au`.`txnid` = 3
                    AND `au`.`username` = '" . $this->_shop->username . "'

                UNION ALL

                SELECT DISTINCT
                0
                , 0
                , 0
                , `rating`
                , 0
                , `date` `datetime`
                , `text`
                , NULL
                , `name`, '' `firstname`
                , CONCAT(ROUND(100 * `rating` / 5), '%') `perc`

                FROM `saved_custom_ratings` `scr`
                JOIN `saved_params` `sp` ON `sp`.`saved_id` = `scr`.`saved_id` 
                    AND `sp`.`par_key` = 'username'
                JOIN `saved_auctions` AS `sa` ON `sa`.`id` = `scr`.`saved_id`

                WHERE NOT `hidden`
                    AND NOT `sa`.`inactive` 
                    AND `sp`.`par_value` = '".$this->_shop->username."'
            ) `t`
            ORDER BY `t`.`datetime` DESC LIMIT " . (($page-1)*$limit) . ", " . $limit . "
            ";
		$ratings = $this->_dbr->getAll($qrystr);
		$smarty->assign("ratings", $ratings);
        $given_text = $this->_dbr->getAll("
            SELECT af.auction_number, af.text, tl.Updated
            FROM auction_feedback af
            JOIN total_log tl ON 
                tl.table_name = 'auction_feedback' 
                AND tl.field_name = 'iid' 
                AND tl.tableid = af.iid
            LEFT JOIN users u ON u.system_username = tl.username
            WHERE af.type = 'given'
        ");
        $smarty->assign("given_text", $given_text);
		if ($rating_statistic->count>20) {
            $ss = ! $this->mobile ? 
                    split_string_action($rating_statistic->count, 20, $page, 'fill_shop_ratings(0, '.$cat_id.', [[p]], "'.$this->_shop->lang.'", "div_rating_tab", 0)', 5, 5) : 
                    split_string_action($rating_statistic->count, 20, $page, 'global.ajaxRequest.fill_shop_ratings(0, '.$cat_id.', [[p]], "'.$this->_shop->lang.'", "div_rating_tab", 0)', 5, 5, true);
			$smarty->assign('split_string', $ss);
			$smarty->assign('cat_id', $cat_id);
			$smarty->assign('saved_id', 0);
			$smarty->assign('lang', $this->_shop->lang);
		}
		$translationShop = $this->_shop->english_shop;
		$smarty->assign("translationShop", $translationShop);
                
        /**
         * @description get answer to reiting
         * @var $given_text
         * @var Smarty $smarty
         */
        $given_text = $this->_dbr->getAll("
            SELECT af.auction_number, af.text, SUBSTRING_INDEX(tl.Updated, ' ', 1) Updated
            FROM auction_feedback af
            JOIN total_log tl ON 
                tl.table_name = 'auction_feedback' 
                AND tl.field_name = 'iid' 
                AND tl.tableid = af.iid
            LEFT JOIN users u ON u.system_username = tl.username
            WHERE af.type = 'given'
        ");
        $smarty->assign("given_text", $given_text);

        /**
         * @description get seller brand name
         * @var $brand_name
         * @var Smarty $smarty
         */
        $brand_name = $this->_seller->get('brand_name');
        $smarty->assign("brand_name", $brand_name);
                
                /***/
		$res = $smarty->fetch($this->_tpls['_shop_offer_rating']);
        
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $res);
		return $res;
	}

	function listFollowUsOn(){
		return $this->_dbr->getAll("select *
			from shop_followus
			where shop_followus.shop_id=".$this->_shop->id."
			order by shop_followus.ordering");
	}


	function listFAQ($faq_id, $inactive=0){
		if (!strlen($inactive)) $inactive = '0,1';
		$faq_id = (int)$faq_id;
		$function = "listFAQ($faq_id,$inactive)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret) {
            return $chached_ret;
        }
        $ret = array();
        $list = $this->listAllFAQ(0, 0, $inactive);
//		echo 'LIST:';print_r($list);
		$allNodes_array = (array)$this->getAllFAQNodes($faq_id, $inactive);
//		print_r($allNodes_array);
		$allNodes =  implode(',', $allNodes_array);
/*		if (!$faq_id) {
			$faq_id=$this->_dbr->getOne("select group_concat(sc.id separator ',')
				from shop_faq sc
				where sc.shop_id=".$this->_shop->id."
				and inactive in ($inactive)
				and parent_id=0
				");
		}*/
		$q = "select sc.id f1, sc.id f2
			from shop_faq sc
			where sc.shop_id=".$this->_shop->id."
			and sc.parent_id in
			($faq_id, $allNodes)
			and inactive in ($inactive)
			";
		$ids = $this->_dbr->getAssoc($q);
//		print_r($ids);
		  if (PEAR::isError($ids)) {
            aprint_r($ids);
            return;
        }
        foreach ((array)$list as $rec) {
			if (in_array($rec->id, $ids)) {
				$rec->childcount = count($this->listAllFAQ($rec->id,$rec->level,$inactive));
	            $ret[] = $rec;
			}
        }
		cacheSet($function, $this->_shop->id, $this->_shop->lang, $ret);
        return $ret;
	}

    function listAllFAQ($parent_id=0, $level=0, $inactive=0)
    {
		if (!strlen($inactive)) $inactive = '0,1';
		$q = "SELECT sc.id,
			sc.shop_id,
			sc.ordering,
			sc.inactive,
			sc.parent_id,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_faq'
				AND field_name = 'name'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as name,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_faq'
				AND field_name = 'html'
				AND language = '".$this->_shop->lang."'
				AND id = sc.id) as html
			, $level level
			FROM shop_faq sc
			where sc.shop_id=".$this->_shop->id."
			and sc.parent_id=$parent_id
			and inactive in ($inactive)
			ORDER BY sc.ordering";
//		echo $q.'<br>';
        $r = $this->_dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
		$res = array();
		foreach($r as $key=>$rec){
			$res[] = $rec;
			$children = $this->listAllFAQ($rec->id, $level+1, $inactive);
			$res = array_merge((array)$res, (array)$children);
		}
        return $res;
    }

    function getAllFAQNodes($faq_id, $inactive=0)
	{
		if (!strlen($inactive)) $inactive = '0,1';
		if (!$faq_id) return 0;
		$cat = $this->_dbr->getRow("select sc.* from shop_faq sc
			where sc.shop_id=".$this->_shop->id."
			and inactive in ($inactive)
		and sc.id=$faq_id");
		return array_merge((array)$cat->id,(array)$this->getAllFAQNodes($cat->parent_id, $inactive));
	}

	function fill_shop_ratings($saved_id, $cat_id, $username, $limit, $page=1, $div_name='div_rating_tab', $no_pagination = 0, $is_main = 0) {
		global $smarty;
        
        $username = mysql_real_escape_string($username);
        
        $function = "fill_shop_ratings($saved_id,$username,$cat_id,$limit,$page,$div_name,$no_pagination,$is_main)";
        $chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
        if ($chached_ret)
        {
            return $chached_ret;
        }
        
		if ($cat_id) {
			$sas = $this->_dbr->getOne("select group_concat(id) from sa{$this->_shop->id} where shop_catalogue_id=$cat_id");
			$saved_id = $sas;
		} 
        else 
        {
			$sas = $saved_id;
		}
        
		if (!strlen($saved_id)) 
        {
            $saved_id=0;
        }
        
        if ($saved_id)
        {
            foreach($this->_dbr->getAll("SELECT `par_value`
                    FROM `saved_params` WHERE `saved_id` IN ($saved_id)
                        AND `par_key` LIKE 'ratings_inherited_from[%'") as $sa) {
                $sa->par_value = (int)$sa->par_value;
                if ($sa->par_value) {
                    $sas .= ", {$sa->par_value}";
                }
            }
        }
        
        $function_rating = "fill_shop_ratings($saved_id,$username,total)";
        $rating_statistic = cacheGet($function_rating, $this->_shop->id, '');
        if ( ! $rating_statistic)
        {
            $qrystr = "select AVG(t.code) avg, COUNT(*) count, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
                from (
                select af.code
                            from auction_feedback af
                            join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                            join customer c on au.customer_id=c.id
                            left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
                            join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
                            where not au.hiderating ".($saved_id?" and subau.saved_id in ($sas) ":" and au.username='$username'")." and not af.hidden and au.txnid=3
                                and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
                union all
                select af.code
                            from auction_feedback af
                            join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                            join customer c on au.customer_id=c.id
                            join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
                            where not au.hiderating ".($saved_id?" and au.saved_id in ($sas) ":" and au.username='$username'")." and not af.hidden and au.txnid=3
                                and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
                union all
                select rating
                            from saved_custom_ratings scr
                            join saved_params sp on sp.saved_id=scr.saved_id and sp.par_key='username'
                            where 1 ".($saved_id?" and scr.saved_id in ($sas) ":" and sp.par_value='$username'")." and not hidden) t
                ";
            $rating_statistic = $this->_dbr->getRow($qrystr);
            cacheSet($function_rating, $this->_shop->id, '', $rating_statistic);
        }
        
		$smarty->assign("rating_statistic", $rating_statistic);
        
        $function_rating = "fill_shop_ratings($saved_id,$username,$page,$limit,page)";
        $ratings = cacheGet($function_rating, $this->_shop->id, '');
        if ( ! $ratings)
        {
            $qrystr = "select * from (
                select
                    af.auction_number
                    , af.txnid
                    , af.type
                    , af.code
                    , af.id
                    , DATE(af.datetime) `datetime`
                    , af.text
                    , af.display_name
                    , au_name.value name, au_firstname.value firstname, CONCAT(ROUND(100*af.code/5),'%') perc
                    , c.hide_name_in_rating
                from auction_feedback af
                join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                join customer c on au.customer_id=c.id
                join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
                    and au_name.key='name_invoice'
                join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
                    and au_firstname.key='firstname_invoice'
                left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
                join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
                where not au.hiderating ".($saved_id?" and subau.saved_id in ($sas) ":" and au.username='$username'")." and not af.hidden and au.txnid=3
                    and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
                    and af.type = 'received'
                    
                        union
                        
                select
                    af.auction_number
                    , af.txnid
                    , af.type
                    , af.code
                    , af.id
                    , DATE(af.datetime) `datetime`
                    , af.text
                    , af.display_name
                    , au_name.value name, au_firstname.value firstname, CONCAT(ROUND(100*af.code/5),'%') perc
                    , c.hide_name_in_rating
                from auction_feedback af
                join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                join customer c on au.customer_id=c.id
                join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
                    and au_name.key='name_invoice'
                join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
                    and au_firstname.key='firstname_invoice'
                join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
                where not au.hiderating ".($saved_id?" and au.saved_id in ($sas) ":" and au.username='$username'")." and not af.hidden and au.txnid=3
                    and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
                    and af.type = 'received'
                        
                        union all
                        
                select distinct
                0
                , 0
                , 0
                , rating
                , 0
                , `date`
                , `text`
                , null
                , name, '' firstname
                , CONCAT(ROUND(100*rating/5),'%') perc
                , 0
                from saved_custom_ratings scr
                join saved_params sp on sp.saved_id=scr.saved_id and sp.par_key='username'
                where 1 ".($saved_id?" and scr.saved_id in ($sas) ":" and sp.par_value='$username'")." and not hidden
                ) t
                order by t.datetime desc limit ".(($page-1)*$limit).", $limit
                ";

            $ratings = $this->_dbr->getAll($qrystr);
            cacheSet($function_rating, $this->_shop->id, '', $ratings);
        }
        
        $pages_count = ceil($rating_statistic->count / $limit);
        $prev_page = $page > 1 ? $page - 1 : 1;
        $next_page = $page < $pages_count ? $page + 1 : $pages_count;

        $pagination = new stdClass;
        $pagination->prev_page = $prev_page;
        $pagination->next_page = $next_page;
        $pagination->page = $page;
        $pagination->pages_count = $pages_count;

		$smarty->assign("ratings", $ratings);
		if ($div_name=='div_ratings5' || $no_pagination) 
        {
			$split_string = '';
		} 
		else 
        {
            if (!$cat_id) 
            {
                $saved_id_r = $saved_id;
                $cat_id_r = 0;
            } 
            else 
            {
                $saved_id_r = 0;
                $cat_id_r = $cat_id;
            }
            
            if ($rating_statistic->count > $limit) 
            {
                if (!$this->mobile) 
                {
                    $split_string = split_string_action($rating_statistic->count, $limit, $page, 'fill_shop_ratings('.$saved_id_r.', '.$cat_id_r.', [[p]], "'.$this->_shop->lang.'", "'.$div_name.'", 0)', 5, 5);
                }
                else 
                {
                    $smarty->assign('prev_page', $pagination->prev_page);
                    $smarty->assign('next_page', $pagination->next_page);
                    $smarty->assign('current_page', $pagination->page);
                    $smarty->assign('pages_count', $pagination->pages_count);
                    
                    $smarty->assign('saved_id_r', $saved_id_r);
                    $smarty->assign('cat_id_r', $cat_id_r);
                }
            }
        }

		$smarty->assign('is_main', $is_main);
		$smarty->assign('saved_id', $saved_id);
		$smarty->assign('cat_id', $cat_id);
		$smarty->assign('lang', $this->_shop->lang);
		$smarty->assign('split_string', $split_string);
		$smarty->assign("page", $page);
		$smarty->assign("translationShop", $this->_shop->english_shop);
                
        /**
         * @description get answer to reiting
         * @var $given_text
         * @var Smarty $smarty
         */
        $given_text = $this->_dbr->getAll("
            SELECT af.auction_number, af.text, SUBSTRING_INDEX(tl.Updated, ' ', 1) Updated
            FROM auction_feedback af
            JOIN total_log tl ON 
                tl.table_name = 'auction_feedback' 
                AND tl.field_name = 'iid' 
                AND tl.tableid = af.iid
            LEFT JOIN users u ON u.system_username = tl.username
            WHERE af.type = 'given'
        ");
        $smarty->assign("given_text", $given_text);

        /**
         * @description get seller brand name
         * @var $brand_name
         * @var Smarty $smarty
         */
        $brand_name = $this->_seller->get('brand_name');
        $smarty->assign("brand_name", $brand_name);
                
        /***/
        
        $template = $smarty->fetch($this->_tpls['_shop_offer_rating']);
        $template = preg_replace('#\s+#iu', ' ', $template);
        $template = preg_replace('# +#iu', ' ', $template);
        $template = preg_replace('#<!--.*?-->#iu', '', $template);
        $template = str_replace('> <', '><', $template);
        
        $return = new stdClass;
        $return->template = $template;
        $return->pagination = $pagination;
        
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $return);
        
		return $return;
	}

	/*
	 * receive the feedbacks method
	 */
    function fill_shop_ratings_json($saved_id, $cat_id, $username, $limit) {

        $function = "fill_shop_ratings_json($saved_id,$cat_id,$limit)";
        $chached_ret = cacheGet($function, $this->_shop->id, '');
        if ($chached_ret)
        {
            return $chached_ret;
        }
        
        if ($cat_id) {
            $sas = $this->_dbr->getOne("select group_concat(id) from sa{$this->_shop->id} where shop_catalogue_id=$cat_id");
            $saved_id = $sas;
        } else {
            $sas = $saved_id;
        }
        
        if (!strlen($saved_id)) $saved_id=0;
        foreach($this->_dbr->getAll("select par_value
				from saved_params where saved_id in ($saved_id)
				and par_key like 'ratings_inherited_from[%'") as $sa) {
            $sa->par_value = (int)$sa->par_value;
            if ($sa->par_value) {
                $sas .= ", {$sa->par_value}";
            }
        }
        
        $qrystr = "select * from (
			select
				af.auction_number
				, af.txnid
				, af.type
				, af.code
				, af.id
				, DATE(af.datetime) `datetime`
				, af.text
				, af.display_name
				, au_name.value name, au_firstname.value firstname, CONCAT(ROUND(100*af.code/5),'%') perc
				, c.hide_name_in_rating
			from auction_feedback af
			join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
			join customer c on au.customer_id=c.id
			join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
				and au_name.key='name_invoice'
			join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
				and au_firstname.key='firstname_invoice'
			join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
			left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
			where not au.hiderating ".($saved_id?" and subau.saved_id in ($sas) ":" and au.username='$username'")." and not af.hidden and au.txnid=3
							and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
					union
			select
				af.auction_number
				, af.txnid
				, af.type
				, af.code
				, af.id
				, DATE(af.datetime) `datetime`
				, af.text
				, af.display_name
				, au_name.value name, au_firstname.value firstname, CONCAT(ROUND(100*af.code/5),'%') perc
				, c.hide_name_in_rating
			from auction_feedback af
			join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
			join customer c on au.customer_id=c.id
			join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
				and au_name.key='name_invoice'
			join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
				and au_firstname.key='firstname_invoice'
			join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
			where not au.hiderating ".($saved_id?" and au.saved_id in ($sas) ":" and au.username='$username'")." and not af.hidden and au.txnid=3
							and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
					union all
			select distinct
			0
			, 0
			, 0
			, rating
			, 0
			, `date`
			, `text`
			, null
			, name, '' firstname
			, CONCAT(ROUND(100*rating/5),'%') perc
			, 0
			from saved_custom_ratings scr
			join saved_params sp on sp.saved_id=scr.saved_id and sp.par_key='username'
			where 1 ".($saved_id?" and scr.saved_id in ($sas) ":" and sp.par_value='$username'")." and not hidden) t
			order by t.datetime desc limit $limit
			";
            
        $ratings = $this->_dbr->getAll($qrystr);
        cacheSet($function, $this->_shop->id, '', $ratings);
        return $ratings;
    }

	function process_offers($offers) {
		$stock_cache = array();
		$warehouses = array();
        
        global $debug_speed, $getMDB;
        $__debug_time = microtime(true);
        
		global $debug;
        if ($debug) $_time = microtime(true);

        $offers_looks = [];
        $saved_ids = array_map(function($v) {return (int)$v->saved_id;}, $offers);
        if ($saved_ids)
        {
            $offers_looks = $this->_dbr->getAssoc("
                SELECT `show_in_category_page`.`saved_id`, `active_look`.`par_value`
                FROM `saved_params` `show_in_category_page`
                JOIN `saved_params` `active_look` ON `active_look`.`saved_id` = `show_in_category_page`.`saved_id`
                WHERE `show_in_category_page`.`par_key` = 'show_in_category_page' 
                    AND `show_in_category_page`.`par_value` = 1
                    AND `show_in_category_page`.`saved_id` IN (" . implode(',', $saved_ids) . ")
                    AND `active_look`.`par_key` = 'active_look'
                    AND `active_look`.`par_value` != 0
            ");
        }
        
        $offer_objs = [];
        $delivery_days = [];
        $orig_offer_ids = array_map(function($v) {return (int)$v->orig_offer_id;}, $offers);
        if ($orig_offer_ids)
        {
            $offer_objs = $this->_dbr->getAssoc("SELECT `offer`.`offer_id` AS `key`, `offer`.*, 
                    DATE(DATE_ADD(NOW(), INTERVAL `available_weeks` WEEK)) AS `available_weeks_date`
                FROM `offer`
                WHERE `offer_id` IN (" . implode(',', $orig_offer_ids) . ")
            ");
            
            $delivery_days = \ShippingMethod::getMultiDeliveryDays($this, $orig_offer_ids);
        }
        
        $icons = [];
        $off_icons = [];
        $offer_ids = array_map(function($v) {return (int)$v->id;}, $offers);
        if ($offer_ids)
        {
            $query = "SELECT IFNULL(`sa`.`master_icons`, 1) `master_icons`, 
                        IFNULL(`master_sa`.`id`, `sa`.`id`) `master_sa`, 
                        `sa`.`id`
                    FROM `sa{$this->_shop->id}` `sa`
                    LEFT JOIN `sa_all` `master_sa` ON `sa`.`master_sa` = `master_sa`.`id`
                    WHERE 
                        `sa`.`username` = '" . mysql_real_escape_string($this->_shop->username) . "'
                        AND `sa`.`siteid` = '" . $this->_shop->siteid . "'
                        AND IFNULL(`sa`.`old`, 0) = 0
                        AND `sa`.`id` IN (" . implode(',', $offer_ids) . ")
                    GROUP BY `sa`.`id`
                    ";
            
            foreach ($this->_dbr->getAll($query) as $icon)
            {
                if ($icon->master_icons && $icon->master_sa) 
                {
                    $icons[(int)$icon->master_sa] = (int)$icon->id;
                }
                else 
                {
                    $icons[$icon->id] = (int)$icon->id;
                }
            }

            if ($icons)
            {
                $offers_icons = $this->getIconsForOffer(array_keys($icons));
                
                $query = "SELECT `saved_id`, `par_key`
                    FROM `saved_params` 
                    WHERE `par_key` LIKE 'icons[%]'
                        AND `par_value` = 1
                        AND `saved_id` IN (" . implode(',', array_keys($icons)) . ")
                    GROUP BY `saved_id`, `par_key`
                    ";
                foreach ($this->_dbr->getAll($query) as $icon)
                {
                    if (preg_match('#icons\[(\d+)\]#iu', $icon->par_key, $matches))
                    {
                        foreach ($offers_icons as $oicon)
                        {
                            if ($oicon->id == $matches[1])
                            {
                                $off_icons[$icons[$icon->saved_id]][] = $oicon;
                            }
                        }
                    }
                }
            }
        }

        if ($debug) var_dump(__LINE__, round(microtime(true) - $_time, 3));
        if ($debug) $_time = microtime(true);

		foreach($offers as $k => $offer) 
        {
            $offers[$k]->shop_looks = [];
            if (isset($offers_looks[$offer->saved_id]))
            {
                $offers[$k]->shop_looks = $this->getLooks($offers_looks[$offer->saved_id], false);
            }
            
			if (!$offer->als) 
            {
				$offer->als = $this->_dbr->getOne("SELECT GROUP_CONCAT(CONCAT(`al`.`article_id`, ':', `al`.`default_quantity`, ':', `a`.`weight_per_single_unit`))
					FROM `article_list` `al`
					JOIN `offer_group` `og` ON `al`.`group_id` = `og`.`offer_group_id` 
                        AND NOT `base_group_id`
					JOIN `article` `a` ON `a`.`article_id` = `al`.`article_id` AND `a`.`admin_id` = 0
					WHERE `og`.`offer_id` = '" . (int)$offer->offer_id . "' 
                        AND NOT `al`.`inactive` AND NOT `og`.`additional`");
			}
            
			$offers[$k]->weight = 0;
			$articles = [];
			foreach(explode(',', $offer->als) as $rr) 
            {
				list($article_id, $default_quantity, $weight_per_single_unit) = explode(':', $rr);
                
				$articles[$article_id] = $default_quantity;
				$offers[$k]->weight += $default_quantity * $weight_per_single_unit;
			}
            
			$minstock = 1000000;
			$pcs = 0;
			$details = unserialize($offer->details);
			$stop_empty_warehouse = $details['stop_empty_warehouse_shop'];
            
			foreach($articles as $article_id => $default_quantity)
            {
                $pcs = 0;
                foreach ($stop_empty_warehouse as $warehouse_id) 
                {
                    if (isset($warehouse_id->par_value))
                    {
                        $warehouse_id = $warehouse_id->par_value;
                    }
                    
                    if ( ! isset($warehouses[$warehouse_id])) 
                    {
                        $warehouses[$warehouse_id] = new \Warehouse($this->_db, $this->_dbr, $warehouse_id);
//                        var_dump($stop_empty_warehouse, $warehouse_id, $warehouses[$warehouse_id]);
                    }
                    
                    if ( ! isset($stock_cache[$article_id][$warehouse_id])) 
                    {
                        $pieces = $warehouses[$warehouse_id]->getPieces($article_id, Config::get(null, null, 'shop_stock_cache_hours'));
                        $stock_cache[$article_id][$warehouse_id] = $pieces;
                    }
                    
                    $pcs += $stock_cache[$article_id][$warehouse_id];
                }
                
                if ($minstock > $pcs) 
                {
                    $minstock = $pcs;
                }
			}
            
            $offer_obj = isset($offer_objs[$offer->orig_offer_id]) ? (object)$offer_objs[$offer->orig_offer_id] : new stdClass;

            $offer_obj->available_text = '';
            if ($offer_obj->available)
            {
                $offer_obj->available_text = $this->_shop->english_shop[214];
                if ($minstock < $this->_shop->min_stock)
                {
                    $offer_obj->available_text .= ' ' . $this->_shop->english_shop[221];
                }
            }
            else if ($offer_obj->available_weeks_date)
            {
                $offer_obj->available_text = $this->_shop->english_shop[216] . ' ' . $offer_obj->available_weeks_date;
            }
            else if ($offer_obj->available_date == '0000-00-00')
            {
                $offer_obj->available_text = $this->_shop->english_shop[215];
            }
            else 
            {
                $offer_obj->available_text = $this->_shop->english_shop[216] . ' ' . $offer_obj->available_date;
            }
            
            $offer_obj->assembled_text = $this->_shop->english[$offer_obj->assembled];
            
			$offers[$k]->assembled = $offer_obj->assembled;
			$offers[$k]->assemble_mins = $offer_obj->assemble_mins;
			$offers[$k]->assembled_text = explode(':', $offer_obj->assembled_text);

            if ($offer_obj->available_date == '0000-00-00')
            {
                $shopAvailableDate = 'NOW()';
            }
            else 
            {
                $shopAvailableDate = "'" . $offer_obj->available_date . "'";
            }
            
			$offers[$k]->expecteddelivery_from =
                    $this->_dbr->getOne("SELECT DATE_ADD($shopAvailableDate, INTERVAL " . (int)$this->_shop->expecteddelivery_min . " DAY)");
			$offers[$k]->expecteddelivery_to =
                    $this->_dbr->getOne("SELECT DATE_ADD($shopAvailableDate, INTERVAL " . (int)$this->_shop->expecteddelivery_max . " DAY)");
            
            $dayofweek = (int)date('N', strtotime($offers[$k]->expecteddelivery_to));
			while($dayofweek >= 6) 
            {
				$offers[$k]->expecteddelivery_to = 
                        $this->_dbr->getOne("SELECT DATE_ADD('" . $offers[$k]->expecteddelivery_to . "', INTERVAL 1 DAY)");
                $dayofweek = (int)date('N', strtotime($offers[$k]->expecteddelivery_to));
			}
            
			$offers[$k]->expecteddelivery_from = 
                    strftime($this->_seller->get('date_format'), strtotime($offers[$k]->expecteddelivery_from));
			$offers[$k]->expecteddelivery_to =
                    strftime($this->_seller->get('date_format'), strtotime($offers[$k]->expecteddelivery_to));

			if ($shopAvailableDate=='0000-00-00') 
            {
				$offers[$k]->shopAvailableDate = $this->_shop->english_shop[192];
			} 
            else 
            {
				$offers[$k]->shopAvailableDate = strftime($this->_seller->get('date_format'), strtotime($shopAvailableDate));
			}
            
            $offers[$k]->delivery_days = isset($delivery_days[$offer->orig_offer_id]) ? (object)$delivery_days[$offer->orig_offer_id] : new stdClass;

			$offers[$k]->available = $offer_obj->available;
			$offers[$k]->available_weeks = $offer_obj->available_weeks;
            
			if ((int)$offer_obj->available_weeks) 
            {
				$offer_obj->available_date = $offer_obj->available_weeks_date;
			}
			if ($offer_obj->available_date === '0000-00-00') 
            {
				$offers[$k]->available_date = '0000-00-00';
			} 
            else 
            {
				$offers[$k]->available_date = strftime($this->_seller->get('date_format'), strtotime($offer_obj->available_date));
			}
            
			$offers[$k]->available_text = str_replace($offer_obj->available_date, 
                strftime($this->_seller->get('date_format'), strtotime($offer_obj->available_date)), 
				$offer_obj->available_text);
            
			if ($minstock < $this->_shop->min_stock) 
            {
                $offers[$k]->min_stock = 1;
            }

            $offers[$k]->icons = isset($off_icons[$offer->id]) ? $off_icons[$offer->id] : [];
        }

        if ($debug) var_dump(__LINE__, round(microtime(true) - $_time, 3));

		return $offers;
	}

	function listStatsPopups() {
		$q = "select p.*
			, group_concat(ps.stat_id) stats
			from shop_statistic_popup p
			left join shop_statistic_popup_stat ps on ps.popup_id=p.id
			where p.shop_id=".$this->_shop->id."
			group by p.id
			order by p.id";
		$res = $this->_dbr->getAll($q);
		return $res;
	}

	function listStats($inactive='0') {
		global $debug;

		$q = "select (select ordering from shop_statistic_set where id=ss.main_rec_id) main_ordering,
			ss.*
			, (SELECT `value`
				FROM translation
				WHERE table_name = 'shop_statistic_set'
				AND field_name = 'title'
                AND language = '{$this->_shop->lang}'
				AND id = ss.id) as title
			from shop_statistic_set ss
            where ss.shop_id={$this->_shop->id} and ss.inactive in ($inactive)
			order by IF(IFNULL(main_rec_id,0)=0,ordering,main_ordering+0.1)
			";
		if ($debug) echo $q.'<br>';
		$res = $this->_dbr->getAll($q);
		foreach($res as $k=>$r) {
			$q = "select * from shop_statistic_set where shop_id=".$this->_shop->id." and inactive in ($inactive)
				and main_rec_id={$r->id}
				order by ordering";
			$res[$k]->subs = $this->_dbr->getAll($q);
		}

		return $res;
	}

	function getStats($saved_id) {
		global $debug, $_SERVER_REMOTE_ADDR;

        if ($debug) var_dump(xdebug_time_index());

		$stats = $this->listStats();
		if ($debug) echo 'Stats1:'.print_r($stats,true).'<br>';
		foreach($stats as $k=>$stat) {
			if ($stat->data=='real') {
				if ($stat->event=='bought') {
					$q = "select TIMEDIFF(NOW(), max(au.end_time)) last_buy,
						count(*) quantity
						from auction au
						#left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
						where au.saved_id=$saved_id
						and au.end_time between DATE_SUB(NOW(), INTERVAL {$stat->time_period_value} {$stat->time_period_type})
							and NOW()
						and au.deleted=0
						";
				}
                else {
					$q = "select TIMEDIFF(NOW(), max(spl.time)) last_buy,
						count(distinct ip) quantity
						from prologis_log.shop_page_log spl
						where spl.saved_id=$saved_id and `server`='www.{$this->_shop->url}'
						and spl.time between DATE_SUB(NOW(), INTERVAL {$stat->time_period_value} {$stat->time_period_type})
							and NOW()
						and spl.time<>NOW()
						";

					if ($stat->constr=='time') {
                        $q .= "and ip<>'{$_SERVER_REMOTE_ADDR}'";
					}
				}

                if ($debug) echo "<pre>$q</pre>";
                if ($debug) var_dump(xdebug_time_index());

				//if ($debug) echo $q.'<br>';
				$r = $this->_dbr->getRow($q);

                if ($debug) var_dump(xdebug_time_index());

				if ($stat->constr=='time') {
					if (!strlen($r->last_buy)) {
						unset($stats[$k]);
						if ($debug) echo 'unset1<br>';
						continue;
					}
					list($h,$m,$s) = explode(':',$r->last_buy);
					$d = (int)($h/24); $h -= $d*24;
					$r->last_buy = $this->_shop->english_shop[237];
					if ((int)$d) $r->last_buy .= ' '.(int)$d.' '.$this->_shop->english_shop[219];
					elseif ((int)$h) $r->last_buy .= ' '.(int)$h.' '.$this->_shop->english_shop[229];
					elseif ((int)$m) $r->last_buy .= ' '.(int)$m.' '.$this->_shop->english_shop[230];
					elseif ((int)$s) $r->last_buy .= ' '.(int)$s.' '.$this->_shop->english_shop[231];
					$r->last_buy .= ' '.$this->_shop->english_shop[232];
					$res = $r->last_buy;
				}
                else {
					$res = $r->quantity;
				}
			}
            else { // for fake
				if ($stat->constr=='time') {
					$q = "select TIMEDIFF(NOW(), max(tl.updated)) as last_buy
						from shop_statistic_cache
						join total_log tl on table_name='shop_statistic_cache' and field_name='id' and tableid=shop_statistic_cache.id
						where sss_id={$stat->id} and saved_id=$saved_id
						and tl.updated between DATE_SUB(NOW(), INTERVAL {$stat->time_period_value} {$stat->time_period_type})
								and NOW()";
//					echo $q;
					$res = $this->_dbr->getOne($q);
					if (!$res) {
						$q = "insert into shop_statistic_cache set saved_id=$saved_id , sss_id = {$stat->id} , `value`='1'";
						$this->_db->query($q);
					}
					$q = "select TIMEDIFF(NOW(), max(tl.updated)) as last_buy
						from shop_statistic_cache
						join total_log tl on table_name='shop_statistic_cache' and field_name='id' and tableid=shop_statistic_cache.id
						where sss_id={$stat->id} and saved_id=$saved_id
						and tl.updated between DATE_SUB(NOW(), INTERVAL {$stat->time_period_value} {$stat->time_period_type})
								and NOW()";
//					echo $q;
					$res = $this->_dbr->getOne($q);
					list($h,$m,$s) = explode(':',$res);
					$d = (int)($h/24); $h -= $d*24;
					$res = $this->_shop->english_shop[237];
					if ((int)$d) $res .= ' '.((int)$d).' '.$this->_shop->english_shop[219];
					elseif ((int)$h) $res .= ' '.((int)$h).' '.$this->_shop->english_shop[229];
					elseif ((int)$m) $res .= ' '.((int)$m).' '.$this->_shop->english_shop[230];
					elseif ((int)$s) $res .= ' '.((int)$s).' '.$this->_shop->english_shop[231];
					$res .= ' '.$this->_shop->english_shop[232];
				} else {
					$res = $this->_dbr->getOne("select max(value)
						from shop_statistic_cache
						join total_log tl on table_name='shop_statistic_cache' and field_name='id' and tableid=shop_statistic_cache.id
						where sss_id={$stat->id} and saved_id=$saved_id
						and tl.updated between DATE_SUB(NOW(), INTERVAL {$stat->time_period_value} {$stat->time_period_type})
								and NOW()");
					if (!$res) {
						$q = "insert into shop_statistic_cache set saved_id=$saved_id , sss_id = {$stat->id} , `value`='1'";
						$this->_db->query($q);
					}
					$res = $this->_dbr->getOne("select max(value)
						from shop_statistic_cache
						join total_log tl on table_name='shop_statistic_cache' and field_name='id' and tableid=shop_statistic_cache.id
						where sss_id={$stat->id} and saved_id=$saved_id
						and tl.updated between DATE_SUB(NOW(), INTERVAL {$stat->time_period_value} {$stat->time_period_type})
								and NOW()");
				}
			}

			if (($res>$stat->min_value && $stat->constr=='quantity') || (strlen($res) && $stat->constr=='time')) {
				$stats[$k]->res = $res;
			} else {
				if ($debug) echo 'unset2<br>';
					unset($stats[$k]);
			}
//			echo $res.'>'.$stat->min_value.'<br>';
		}
		foreach($stats as $k=>$r) {
			if (strpos($r->title, '[[value]]')) {
				$stats[$k]->title = str_replace('[[value]]',$r->res,$r->title);
				$stats[$k]->res = '&nbsp;';
			}
		}
		if ($debug) echo 'Stats2:'.print_r($stats,true).'<br>';

        $debug = false;

		return $stats;
	}

	function processOfferSims($shop_offer) {
		global $smarty;
		global $debug, $getMDB;
		$lang = $this->_shop->lang;
		$sims = $this->_dbr->getAll("select distinct ss.* from saved_sim ss
				join sa".$this->_shop->id." sa on sa.id=ss.sim_saved_id
				where ss.saved_id=$shop_offer and ss.inactive=0
				order by ss.ordering");
		foreach($sims as $k => $sim) {
			$sims[$k] = $this->getOffer($sim->sim_saved_id);
            
			if (!$sims[$k]->saved_id) unset($sims[$k]);
		}

        $smarty->assign('sims', $sims);
	}

	/**
	 * @param int $shop_offer offer identifier in `sa*` table
	 * @param bool $include_schema_data
	 */
	function processOffer($shop_offer, $include_schema_data = false, $template_id = null) {
        $__debug_time = microtime(true);
        
		global $smarty;
		global $debug, $debug_speed, $getMDB;
		global $cart_array, $redis;
		global $_SERVER_REMOTE_ADDR;
		$lang = $this->_shop->lang;
        
		if ($debug) {
			echo "processOffer($shop_offer)<br>";
		}
        
// list of cats
		$ids = $this->_dbr->getAssoc("SELECT DISTINCT sc1.id f1, sc1.id f2
			FROM saved_params sp
			JOIN shop_catalogue sc1 ON sc1.id=sp.par_value
			JOIN shop_catalogue_shop scs1 ON sc1.id=scs1.shop_catalogue_id
			WHERE sc1.hidden=0 AND scs1.hidden=0 AND scs1.shop_id=" . $this->_shop->id . " 
                AND sp.saved_id=" . (int)$shop_offer . "
                AND sp.`par_key` = 'shop_catalogue_id[" . $this->_shop->id . "]' "
			. ($this->_shop->leafs_only ? " and not exists (select null
				from shop_catalogue sc
				join shop_catalogue_shop scs on sc.id=scs.shop_catalogue_id
				where sc.hidden=0 and scs.hidden=0 and sc.parent_id=scs1.shop_catalogue_id and scs.shop_id=" . $this->_shop->id . ")" : '')
		);
		$cat_route_array = [];
        $main_cats = [];
        
		$content_title = '';
		$first = false;

        $shop_cat_id = isset($_COOKIE['shop_cat_id']) ? (int)$_COOKIE['shop_cat_id'] : false;

		foreach ($ids as $id1) {
			$cat_array = $this->getAllNodesRecs($id1);

            if ( ! $shop_cat_id) {
                $shop_cat_id = $cat_array[count($cat_array) - 1];
                $shop_cat_id = isset($shop_cat_id->id) ? (int)$shop_cat_id->id : 0;
            }

			$content_title .= '<div class="cat_route_item"><a href="/">' . $this->_shop->title . '</a>';
			$cat_route_array_item = '<div class="cat_route_item"><a href="/">' . $this->_shop->title . '</a>';
			$sumalias = '/';
			if (!$first) $itemprop_category = $this->_shop->title;
			foreach ($cat_array as $catid => $catname) {
				$sumalias .= $catname->alias . '/';
				$content_title .= ' &raquo; <a href="' . $sumalias . '">' . $catname->name . '</a>';
				$cat_route_array_item .= ' &raquo; <a href="' . $sumalias . '">' . $catname->name . '</a>';
				if (!$first) $itemprop_category .= ' > ' . $catname->name;
                                
                                /**
                                 * @description get main categories
                                 * @var $catid
                                 * @var $main_cats
                                 * @var $sumalias
                                 * @var $catname
                                 */
                                if ($catid == 0) {
                                    $main_cats[] = '<a href="' . $sumalias . '">' . $catname->name . '</a>';
                                }
			}
			$content_title .= '</div>';
			$cat_route_array_item .= '</div>';
			$first = true;
            
            $cat_route_array[$id1] = $cat_route_array_item;
		} // foreach folder

        setcookie("shop_cat_id", $shop_cat_id, 0, '/');
        
        $this->isPreview = (bool)$template_id || isset($_GET['isPreview']);
        
		$smarty->assign('itemprop_category', $itemprop_category);
        
        $sa = $this->getOffer($shop_offer);
        
		if (!$sa) {
			$ret = new stdClass;
			$ret->HTTP_REFERER = print_r($_SERVER, true) . print_r($_REQUEST, true);
			$ret->url404 = (string)$_SERVER['HTTP_HOST'] . (string)$_SERVER['SCRIPT_NAME'] . "?" . (string)$_SERVER['QUERY_STRING'];
			$ret->email_invoice = $this->_dbr->getOne('SELECT group_concat(email) FROM users WHERE deleted=0 AND get_shop_error_404');
			if (strlen($ret->email_invoice)) $r = standardEmail($this->_db, $this->_dbr, $ret, 'shop_error_404');
			addRedirect($http . '://' . (string)$_SERVER['HTTP_HOST'] . (string)$_SERVER['SCRIPT_NAME'] . "?" . (string)$_SERVER['QUERY_STRING']
				, $http . '://' . (string)$_SERVER['HTTP_HOST'] . (string)$_SERVER['REQUEST_URI']
                , $this->_shop->id);
			setcookie("last_redirect", $http . '://' . (string)$_SERVER['HTTP_HOST'] . (string)$_SERVER['REQUEST_URI'], time() + 3600 * 24 * 720, '/');
			$old_shop_offer = $this->getAllItemID($item);
			setcookie("old_shop_offer", $old_shop_offer, time() + 3600 * 24 * 720, '/');

			if ($debug) {
				echo '-404-1';
			} else header("Location: /content/404/");
			exit;
		}
        
        $_time = microtime(true);
        $details = $this->_dbr->getAssoc("SELECT `par_key`, `par_value` 
            FROM `saved_params`
            WHERE `saved_id` = '{$sa->id}'
                AND `par_key` IN ('dont_show', 'shop_catalogue_id[1]', 'ShopRightOfReturn', 'master_shop', 'username', 'siteid')
            GROUP BY `par_key`");
        //$details = Saved::getDetails($sa->id);
        if ($sa->orig_id != $sa->id) {
//            $orig_details = Saved::getDetails($sa->orig_id);
            $orig_details = $this->_dbr->getAssoc("SELECT `par_key`, `par_value` 
                FROM `saved_params`
                WHERE `saved_id` = '{$sa->orig_id}'
                    AND `par_key` = 'ShopRightOfReturn'
                GROUP BY `par_key`");
        } else {
            $orig_details = $details;
        }
        
        if ($sa->master_sa != $sa->id) {
//            $master_details = Saved::getDetails($sa->master_sa);
            $master_details = $this->_dbr->getAssoc("SELECT `par_key`, `par_value` 
                FROM `saved_params`
                WHERE `saved_id` = '{$sa->id}'
                    AND `par_key` IN ('master_shop', 'username', 'siteid')
                GROUP BY `par_key`");

        } else {
            $master_details = $details;
        }
        
        $smarty->assign('looks_for_offer', $this->getLooksForOffer($sa->saved_id, true));
        
        $template_id = $template_id ? (int)$template_id : (int)$sa->template_id;
        $smarty->assign('template_id', $template_id);
        
        if ($template_id)
        {
            $_time = microtime(true);
            $template_details = $this->getOfferDetails($shop_offer);
            
            $template = \SavedTemplates::getBlocks($template_id, $this->_shop->lang);
            if ($template->blocks && is_array($template->blocks))
            {
                foreach ($template->blocks as $_block_id => $_block)
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
                                        
                                        $_value = str_ireplace('%%language%%', $this->_shop->lang, $_value);
                                        $_value = str_ireplace(array_keys($template_details), array_values($template_details), $_value);
                                        $_value = str_ireplace(']]', '_' . $this->_shop->lang . ']]', $_value);
                                        $_value = str_ireplace(array_keys($template_details), array_values($template_details), $_value);
                                        
                                        $_value = preg_replace('#\[\[.*?\]\]#iu', '', $_value);
                                        $template->blocks[$_block_id]->layouts[$_layout_id]->values[$_value_id] = $_value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            $smarty->assign("sa_template", $template);
        }

		if ($include_schema_data) {
			$this->_collectSchemaData($sa);
        }
        
        $main_cats = array_unique($main_cats);
        $smarty->assign('main_cats', $main_cats);
		$smarty->assign('cat_route', $content_title);
		$smarty->assign('cat_route_array', $cat_route_array);
// others
		if ($sa->master_others && $sa->master_sa != $sa->orig_id) 
        {
			$q = "select so.NameID
				, concat($shop_offer,',',group_concat(sp.saved_id))
				from saved_params sp
				join saved_other so on so.saved_id={$sa->master_sa} and 1*sp.par_value=so.other_saved_id
				join saved_params sp_username on sp.saved_id=sp_username.saved_id and sp_username.par_key='username'
					and sp_username.par_value='{$this->_shop->username}'
				LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id=sp.saved_id and sp_auction_name.par_key = 'auction_name'
				join saved_auctions sa on sa.id=sp.saved_id and sa.inactive=0 and sa.old=0
				where sp.par_key='master_sa'
				group by NameID
				having NameID is not null
				order by so.ordering";
		} 
        else 
        {
			$q = "select distinct so.NameID, concat($shop_offer,',',group_concat(so.other_saved_id))
			from saved_other so
			join saved_auctions sa on sa.id=so.other_saved_id
			where so.inactive=0 and so.saved_id={$sa->orig_id} and sa.inactive=0 and sa.old=0
			group by so.NameID
			order by so.ordering";
		}
        
		$others = $this->_dbr->getAssoc($q);
        
		if (count($others)) {
			$q = "SELECT DISTINCT t_title.value title, sn.*
					, t_measur.value mchar
					, t_descr.value description
				FROM saved_other so
				JOIN Shop_Names sn ON sn.id=so.NameID
				LEFT JOIN Shop_Name_Shop sns ON sns.NameID=sn.id AND sns.shop_id=" . $this->_shop->id . "
				JOIN translation t_title ON t_title.table_name='Shop_Names' AND t_title.field_name='title'
					AND t_title.id=sn.id AND t_title.language='" . $this->_shop->lang . "'
				LEFT JOIN translation t_descr ON t_descr.table_name='Shop_Names' AND t_descr.field_name='description'
					AND t_descr.id=sn.id AND t_descr.language='" . $this->_shop->lang . "'
				LEFT JOIN translation t_measur ON t_measur.table_name='Shop_Names' AND t_measur.field_name='measur'
					AND t_measur.id=sn.id AND t_measur.language='" . $this->_shop->lang . "'
				WHERE so.inactive=0 AND IFNULL(sns.inactive, sn.inactive)=0 AND so.saved_id=" . ($sa->master_others && $sa->master_sa != $sa->orig_id ? $sa->master_sa : (int)$shop_offer) . "
				ORDER BY so.ordering";
			$pars = $this->_dbr->getAll($q);
            
			foreach ($pars as $k => $name) {
				/*if ($name->SelectionMode=='Dropdown')*/
				{
					if ($name->translatable) {
						$q = "select distinct sa.id saved_id, " . ($name->ValueType == 'img' ? 'spv.ValueID, ' : '0 as ValueID, ') . "t.value ValueText
							from sa{$this->_shop->id} sa
							left join sa_all on sa_all.id=sa.master_sa
							join saved_parvalues spv on IFNULL(sa_all.id, sa.id)=spv.saved_id
							join Shop_Values sv on sv.id=spv.ValueID and sv.NameID=spv.NameID
							left join saved_other so on so.saved_id=$shop_offer and so.NameID=spv.NameID and so.other_saved_id=spv.saved_id
							left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
								and t.language='$lang' and t.id=sv.id
							where sv.NameID=$name->id
								and sa.id in (" . $others[$name->id] . ")
						and sv.inactive=0
						order by IF(spv.saved_id=$shop_offer, -spv.id, so.ordering)";
						#					echo $q;
					} else {
						$q = "select distinct sa.id saved_id, 0 as ValueID," . ($name->ValueType == 'dec' ? 'sv.ValueDec' : 'sv.ValueText') . " as ValueText
							from sa{$this->_shop->id} sa
							left join sa_all on sa_all.id=sa.master_sa
							join saved_parvalues spv on IFNULL(sa_all.id, sa.id)=spv.saved_id
							join Shop_Values sv on sv.id=spv.ValueID and sv.NameID=spv.NameID
							left join saved_other so on so.saved_id=$shop_offer and so.NameID=spv.NameID and so.other_saved_id=spv.saved_id
							where sv.NameID=$name->id
								and sa.id in (" . $others[$name->id] . ")
						and sv.inactive=0
						order by IF(spv.saved_id=$shop_offer, -spv.id, so.ordering)";
					}
				}
                if ($debug) echo $q . '<br>';
				if (strlen($q)) {
					$values = $this->_dbr->getAll($q);
					$route_values = array();
					foreach ($values as $kk => $value) {
						$saved_id = $value->saved_id;
						$sa_other = $this->getOffer($saved_id);
                        
                        $saved_pic = new \SavedPic($sa_other->master_pics ? $sa_other->master_sa : $sa_other->orig_id);
                        $value->pic = null;
                        
                        foreach ($saved_pic->get(true) as $pic)
                        {
                            if ($pic->primary)
                            {
                                $value->pic = $pic;
                                break;
                            }
                        }
                        
						$value->cat_route = '/' . $sa_other->ShopSAAlias . '.html';
						if ($value->saved_id == $shop_offer) {
							$pars[$k]->selected_value = $value->ValueText;
						}
						$route_values[$value->saved_id] = $value;
					} // foreach value
					$pars[$k]->values = $route_values;
				}
			} // foreach par
            
            foreach ($pars as $key => $dummy)
            {
                usort($pars[$key]->values, function($a, $b) {
                    return $a->ValueText > $b->ValueText;
                });
            }
                        
			$smarty->assign('others', $pars);
//		print_r($pars);
		} // if others
        
// sims
		if ($sa->master_sims && $sa->master_sa && $sa->master_sa != $sa->orig_id) {
			$sims = Shop_Catalogue::sgetSims($this->_dbr, $sa->master_sa, 1, $this->_shop->username, 0);
		} else {
			$sims = Shop_Catalogue::sgetSims($this->_dbr, $sa->orig_id, 0, $this->_shop->username, 0);
		}
        
		foreach ($sims as $k => $sim) {
			$sims[$k] = $this->getOffer($sim->sim_saved_id);
			if (!$sims[$k]->saved_id) unset($sims[$k]);

            $saved_pic = new \SavedPic($sims[$k]->master_pics ? $sims[$k]->master_sa : $sims[$k]->orig_id);
            $sims[$k]->pics = $saved_pic->get(true);
		}
        
        $smarty->assign('sims', $sims);
        
// additional saved_auctions
		if ($sa->master_sa && $sa->master_sa != $sa->orig_id) {
            $additional = $this->_dbr->getCol("SELECT `sp`.`saved_id` as `additional_id`
                FROM `saved_additional_articles`
                JOIN `saved_params` `sp`
                        ON `sp`.`par_key` = 'master_sa' 
                        AND `sp`.`par_value` = `saved_additional_articles`.`additional_id`
                JOIN `saved_auctions` ON `saved_auctions`.`id` = `sp`.`saved_id`
                JOIN `saved_params` `sp_username`
                        ON `sp_username`.`par_key` = 'username' 
                        AND `sp_username`.`par_value` = '{$this->_shop->username}'
                        AND `sp_username`.`saved_id` = `sp`.`saved_id`
                WHERE `saved_additional_articles`.`saved_id` = {$sa->master_sa}
                AND `saved_auctions`.`inactive` = 0 
                AND `saved_auctions`.`old` = 0
                ORDER BY `saved_additional_articles`.`ordering`");
        } else {
            $additional = $this->_dbr->getCol("SELECT `saved_additional_articles`.`additional_id`
                FROM `saved_additional_articles`
                JOIN `saved_auctions` ON `saved_auctions`.`id` = `saved_additional_articles`.`additional_id`
                WHERE `saved_additional_articles`.`saved_id` = {$sa->orig_id}
                AND `saved_auctions`.`inactive` = 0 
                AND `saved_auctions`.`old` = 0
                ORDER BY `saved_additional_articles`.`ordering`");
        }
        
        foreach ($additional as $kk => $additional_saved_id) {
            $additional[$kk] = $this->getOffer($additional_saved_id);
            $additional[$kk]->rating_statistic = $this->getRating($additional_saved_id);
        }
        $smarty->assign('additional', $additional);

		if ((int)$print) $smarty->assign('main_css', $this->_shop->css_print);
        
		$offer_id = $sa->offer_id;
		if (!(int)$offer_id) {
			if (!$debug) header("Location: /");
			else {
				echo "Wrong SA: for $shop_offer ";
				print_r($sa);
			}
			exit;
		}
        
        $offer = new Offer($this->_db, $this->_dbr, $offer_id, $this->_shop->lang);
        if ($sa->orig_offer_id != $offer_id) {
            $orig_offer = new Offer($this->_db, $this->_dbr, $sa->orig_offer_id, $this->_shop->lang);
        } else {
            $orig_offer = $offer;
        }        
        $smarty->assign('orig_offer', $orig_offer);
		
		$main_groups = $this->_dbr->getAll("select
				IFNULL(t_alias_name.value, t.value) name
				, a.weight_per_single_unit
				, a.weight
				, al.default_quantity
				, ap.`dimension_l`
				, ap.`dimension_h`
				, ap.`dimension_w`
                                , (ap.`dimension_l`/2.54) as dimension_l_inch
                                , (ap.`dimension_h`/2.54) as dimension_h_inch
                                , (ap.`dimension_w`/2.54) as dimension_w_inch
			from offer_group og
			join article_list al on al.group_id=og.offer_group_id
			left join article_parcel ap on ap.article_id=al.article_id
			left join translation t_alias_name on t_alias_name.table_name = 'article_alias'
					AND t_alias_name.field_name = 'name'
					AND t_alias_name.language = '{$this->_shop->lang}'
					AND t_alias_name.id = al.alias_id
			join article a on a.article_id=al.article_id and a.admin_id=0
			join translation t on a.article_id=t.id and t.table_name='article'
				and t.field_name='name' and t.language='{$this->_shop->lang}'
			where og.offer_id={$sa->orig_offer_id}
			and (og.main or not og.additional)
			and not og.base_group_inactive
			and al.inactive=0 and al.default_quantity>0");
        
		$smarty->assign('main_groups', $main_groups);
		$smarty->assign('dont_show', $details['dont_show']);
		
		$offer->data->ShopDesription = $sa->ShopDescription;
		/*		$offer->data->ShopDesription = $this->_dbr->getOne("select name from offer_name where id=(
                        select `value` from translation tShopDesription where tShopDesription.id='{$sa->id}'
                            and tShopDesription.table_name='sa'
                            and tShopDesription.field_name='ShopDesription'
                            and tShopDesription.language = '$lang'
                        )");*/
        
		if (isset($orig_details['ShopRightOfReturn'])) {
			if ((int)$orig_details['ShopRightOfReturn']) {
				$offer->ShopRightOfReturn[0] = $this->_shop->english_shop[218];
				$offer->ShopRightOfReturn[1] = ((int)$orig_details['ShopRightOfReturn']) . ' ' . $this->_shop->english_shop[219];
			} else {
				$offer->ShopRightOfReturn[0] = $this->_shop->english_shop[218];
				$offer->ShopRightOfReturn[1] = $this->_shop->english_shop[217];
			}
		} else {
			$offer->ShopRightOfReturn[0] = $this->_shop->english_shop[218];
			$offer->ShopRightOfReturn[1] = $this->_seller->data->default_RightOfReturn . ' ' . $this->_shop->english_shop[219];
		}
		$offer->ShopRightOfReturn1 = $orig_details['ShopRightOfReturn'];
		$offer->assembled = explode(':', $this->_shop->english[$offer->data->assembled]);
		$offer->warranty_duration = explode(':', $this->_seller->data->warranty_duration);
//		$offer->ShopRightOfReturn = explode(':', $offer->ShopRightOfReturn);
        
		$offer->ShopSAKeywords = $this->_dbr->getOne("select `value` from translation t
				where t.id='" . ($sa->master_ShopSAKeywords && $sa->master_sa ? $sa->master_sa : $sa->id) . "'
					and t.table_name='sa'
					and t.field_name='ShopSAKeywords'
					and t.language = '$lang'
				");
        
		$offer_amazon_st = $this->_dbr->getAssoc("SELECT `field_name`, `value` FROM `translation`
				WHERE `id`='" . ($sa->master_amazon && $sa->master_sa ? $sa->master_sa : $sa->orig_id) . "'
					AND `table_name`='sa'
					AND `field_name` IN ('amazon_st_1', 'amazon_st_2', 'amazon_st_3', 'amazon_st_4', 'amazon_st_5', 'amazon_st_6')
					AND `language` = '$lang'
				");
        
        for ($i = 1; $i <= 6; ++$i)
        {
            $index = "amazon_st_$i";
            $offer->$index = $offer_amazon_st[$index];
        }
        
        $master_shop_id = 0;
        if ((int)$master_details['master_shop']) {
            $master_shop_id = (int)$master_details['master_shop'];
        } else {
            $master_shop_id = $this->_dbr->getOne("select id from shop where username='" . $master_details['username'] . "'
                    and siteid='" . (int)$master_details['siteid'] . "' order by id limit 1");
        }
        
        $descriptionTextShop2 = Saved::getDescriptionTextShop2($sa->master_sa, $this->_shop->id, $lang);
        
        $offer->color = $this->_dbr->getOne("select t_color.value from sa" . $this->_shop->id . " sa
            left join saved_params sp_color on sp_color.saved_id=sa.id and sp_color.par_key='color_id'
            left join translation t_color on t_color.table_name='sa_color' and t_color.field_name='name'
                and t_color.id=sp_color.par_value and t_color.language='$lang'
            where sa.id=" . $sa->orig_id . "
            limit 1");
		$offer->ShopSADescription = $this->_dbr->getOne("select value from translation
                where table_name='sa' and field_name='descriptionTextShop2'
				and id=" . ($this->_shop->master_descriptionTextShop ? $sa->master_sa : $sa->orig_id) . " 
				and language='$lang'");

		if (!strlen($offer->ShopSADescription)) $offer->ShopSADescription = $descriptionTextShop2;

		$q = "select IF(picture_URL like '%pic_id%', substring_index(picture_URL,'pic_id=',-1)
	, REPLACE(substring_index(picture_URL,'picid_',-1),'_image.jpg','')) pic_id
	            , IF(wpicture_URL like '%wpic_id%', substring_index(wpicture_URL,'wpic_id=',-1)
	, REPLACE(substring_index(wpicture_URL,'picid_',-1),'_image.jpg','')) wpic_id
	            , a.picture_URL, t.value article_name, t_desc.value article_description
				, t_ap.value article_price
				, t_hp.value high_price
				, og.position og_position, al.position al_position
				from article_list al
				join offer_group og on og.base_group_id=al.group_id
				join offer_group bg on og.base_group_id=bg.offer_group_id
				join article a on a.article_id=al.article_id and a.admin_id=0
				join translation t on t.table_name='article' and t.field_name='name'
				and t.id=a.article_id and t.language='$lang'
				join translation t_desc on t_desc.table_name='article' and t_desc.field_name='description'
				and t_desc.id=a.article_id and t_desc.language='$lang'
				left join translation t_ap on t_ap.table_name='article' and t_ap.field_name='add_item_cost'
				and t_ap.id=a.article_id and t_ap.language='{$this->_shop->siteid}'
				left join translation t_hp on t_hp.table_name='article' and t_hp.field_name='high_price'
				and t_hp.id=a.article_id and t_hp.language='{$this->_shop->siteid}'
				where 1
				and og.offer_id={$sa->orig_offer_id}
				" . ($this->_seller->data->basegroups ? " and og.base_group_id " : " and not og.base_group_id ") . "
				and og.base_group_inactive=0
				and bg.additional
				and al.inactive=0
	union
	select IF(picture_URL like '%pic_id%', substring_index(picture_URL,'pic_id=',-1)
	, REPLACE(substring_index(picture_URL,'picid_',-1),'_image.jpg','')) pic_id
	            , IF(wpicture_URL like '%wpic_id%', substring_index(wpicture_URL,'wpic_id=',-1)
	, REPLACE(substring_index(wpicture_URL,'picid_',-1),'_image.jpg','')) wpic_id
	            , a.picture_URL, t.value article_name, t_desc.value article_description
				, t_ap.value article_price
				, t_hp.value high_price
	, og.position og_position, al.position al_position
				from article_list al
				left join offer_group og on og.offer_group_id=al.group_id
				join article a on a.article_id=al.article_id and a.admin_id=0
				join translation t on t.table_name='article' and t.field_name='name'
				and t.id=a.article_id and t.language='$lang'
				join translation t_desc on t_desc.table_name='article' and t_desc.field_name='description'
				and t_desc.id=a.article_id and t_desc.language='$lang'
				left join translation t_ap on t_ap.table_name='article' and t_ap.field_name='add_item_cost'
				and t_ap.id=a.article_id and t_ap.language='{$this->_shop->siteid}'
				left join translation t_hp on t_hp.table_name='article' and t_hp.field_name='high_price'
				and t_hp.id=a.article_id and t_hp.language='{$this->_shop->siteid}'
				where 1
				and og.additional and og.offer_id={$sa->orig_offer_id}
				and al.inactive=0
				" . ($this->_seller->data->basegroups ? '' : " and not og.base_group_id ") . "
				order by og_position, al_position
				";
		$offer->additional = $this->_dbr->getAll($q);
        
        //	echo $q;
		$offer->data->ShopPrice = $sa->ShopPrice;

		$offer->data->ShopHPrice = $sa->ShopHPrice;
		$offer->data->brand = $sa->brand;
		$offer->data->ShopMinusPercent = number_format($sa->ShopMinusPercent, 0);
		if ($this->_shop->text_descr) {
			$q = "select tr1.value
					from translation tr1
				where tr1.id=" . ($sa->master_descriptionTextShop ? $sa->master_sa : $sa->orig_id) . "
				and tr1.table_name='sa'
				and tr1.field_name in ('descriptionTextShop1')
				and tr1.language = '$lang'";
		} else {
			$q = "select tr1.value
					from translation tr1
				left join translation tr2 on tr1.id=tr2.id
					and tr1.language=tr2.language
					and tr1.table_name=tr2.table_name
					and tr2.field_name=REPLACE(tr1.field_name,'descriptionShop','inactivedescriptionShop')
				where tr1.id=" . ($sa->master_descriptionShop ? $sa->master_sa : $sa->orig_id) . "
				and tr1.table_name='sa'
				and tr1.field_name in ('descriptionShop1'
					,'descriptionShop2'
					,'descriptionShop3'
					,'descriptionShop4'
					,'descriptionShop5'
					,'descriptionShop6')
				and tr1.language = '$lang'
				and IFNULL(tr2.value, 0) != 1
				order by REPLACE(tr1.field_name,'descriptionShop','')*1 limit 1";
		}

//        echo "<pre>$q</pre>";

		$descriptionShop = $this->_dbr->getOne($q);
        
		// Maks, Marta - image alt tag in product descriptions */
		$offer->data->description = preg_replace("/\s+(alt=\"Special offer\")/ui", '', $descriptionShop);
        
        if ($this->_shop->cdn) {
            $offer->data->description = processContent($offer->data->description, $this->cdn_domain);
        }

		/*	$q = "select name from offer_name where deleted=0 and offer_id=".$offer_id." order by id limit 1";
            $offer->data->alias = $this->_dbr->getOne($q);*/
		//$offer->pics = Saved::getDocs($this->_db, $this->_dbr, $sa->master_pics ? $sa->master_sa : $sa->orig_id, $inactive = " and inactive=0 ", $lang, $this->_shop->lang4pics, 0, 0, 0);

        if ($this->_shop->new_image) {
            $saved_pic = new \SavedPic($sa->master_pics ? $sa->master_sa : $sa->orig_id);
            $offer->pics = $saved_pic->withText($lang)->get(true);
            
            if ( ! $this->_shop->no_video) {
                $video = \Saved::getDocs($this->_db, $this->_dbr, $sa->master_pics ? $sa->master_sa : $sa->orig_id, $inactive = " and inactive=0 ", $lang, $this->_shop->lang4pics, 0, 0, 0);
                foreach ($video as $doc) {
                    if ($doc->youtube_code) {
                        $offer->pics[] = $doc;
                    }
                }
            }
        }
        else {
            $offer->pics = \Saved::getDocs($this->_db, $this->_dbr, $sa->master_pics ? $sa->master_sa : $sa->orig_id, $inactive = " and inactive=0 ", $lang, $this->_shop->lang4pics, 0, 0, 0);
        }

		if ($this->_shop->no_video) {
			foreach ($offer->pics as $k1 => $r1) {
				if (strlen(trim($offer->pics[$k1]->youtube_code))) unset($offer->pics[$k1]);
			}
		}
        
		foreach ($offer->pics as $k => $r) {
            if (isset($r->hideinshop) && $r->hideinshop) {
                unset($offer->pics[$k]);
                continue;
            } else if (isset($r->use) && !$r->use) {
                unset($offer->pics[$k]);
                continue;
            }
            
            if ($this->_shop->new_image) {
                if ( ! $r->doc_id) {
                    unset($offer->pics[$k]);
                    continue;
                } elseif (
                    ($this->_shop->dimensions == Saved::DIMENSION_CM && $r->img_type == 'dimensions_inch')
                    || ($this->_shop->dimensions == Saved::DIMENSION_INCH && $r->img_type == 'dimensions_cm')
                ) {
                    unset($offer->pics[$k]);
                    continue;
                }
            }
            
            $offer->pics[$k]->alt_def = str_replace('"', "'", $offer->data->ShopDesription) . '_' . $r->doc_id;
			$offer->pics[$k]->title_def = str_replace('"', "'", $offer->data->ShopDesription) . '_' . $r->doc_id;
			$offer->pics[$k]->color_type = $sa->color_type;
		}
        
        $offer->color_type = $sa->color_type;
        
		if ($sa->orig_offer_id) {
			//get article_groups by offer_id
            
            $articles_ids = $this->_dbr->getOne("SELECT GROUP_CONCAT(a.article_id)
                FROM article a
                    JOIN article_list al ON a.article_id = al.article_id AND NOT admin_id
                    JOIN offer_group og ON og.offer_group_id = al.group_id 
                        AND og.offer_id = '{$sa->orig_offer_id}' and not og.additional and not og.base_group_id
                WHERE NOT a.admin_id and al.inactive=0
                AND NOT a.deleted");
            
            if ($articles_ids) {
                $article_ids_array = explode(',', $articles_ids);
                $subarticles = Article::getSubArticles($article_ids_array);
                $article_ids_array = array_merge($article_ids_array, $subarticles);
                $offer->articles_docs = Article::getDocsFor($article_ids_array, Article::DOCS_USAGE_SITE);
            }
		}
        
		$data_translations2 = array();
		foreach ($offer->articles_docs as $kdoc => $doc) {
			$data_translations2[$doc->doc_id][$lang] = $this->_dbr->getRow("select t1.*
					, t2.value as filename
					, substring_index(t2.value, '.', -1) as ext
					from translation t1
					left join translation t2 on t1.table_name=t2.table_name and t2.field_name='name'
					and t1.id=t2.id and t1.language=t2.language
					where t1.table_name='article_doc' and t1.field_name='description'
					and t1.id=" . $doc->doc_id . " and t1.language='$lang' and t2.value!=''");
			if ($doc->auto_description) {
				$data_translations2[$doc->doc_id][$lang]->value = $this->_shop->english_shop[261] . ' ' . $offer->data->ShopDesription . " #" . ($kdoc + 1); #.' '.$updated;
			}
			/*			if (!strlen($data_translations2[$doc->doc_id][$lang]->value))
                            $data_translations2[$doc->doc_id][$lang] = $this->_dbr->getRow("select *
                                from translation
                                where table_name='article_doc' and field_name='name'
                                and id=".$doc->doc_id." and language='$lang'");*/
			if (!$data_translations2[$doc->doc_id][$lang] || !strlen($data_translations2[$doc->doc_id][$lang]->filename)) {
				unset($offer->articles_docs[$kdoc]);
			} else {
#		   		$exts = explode('.', $data_translations2[$doc->doc_id][$lang]->value); $ext = end($exts);
#				$data_translations2[$doc->doc_id][$lang]->ext = $ext;
			}
		}
        
		if (!count($offer->articles_docs)) unset($offer->articles_docs);
		$smarty->assign('data_translations2', $data_translations2);
		$offer->docs = Saved::getDocs($this->_db, $this->_dbr, $sa->id, $inactive = " and inactive=0 ", $lang, $this->_seller->data->default_lang, 1, 0, 1);

		$q = "SELECT DISTINCT IF(IF(sshipping_plan_free, 1, IFNULL(t_o.value,0)), 0, spc.shipping_cost) AS shipping_cost
				, sa.ShopShippingCharge
			FROM sa" . $this->_shop->id . " sa
			LEFT JOIN sa_all master_sa ON sa.master_sa=master_sa.id
			JOIN offer ON offer.offer_id=sa.offer_id
			LEFT JOIN translation t_o
				ON t_o.language=sa.siteid
				AND t_o.id=sa.offer_id
				AND t_o.table_name='offer' AND t_o.field_name='sshipping_plan_free_tr'
			JOIN translation
				ON translation.language=sa.siteid
				AND translation.id=offer.offer_id
				AND translation.table_name='offer' AND translation.field_name='sshipping_plan_id'
			JOIN shipping_plan_country spc ON spc.shipping_plan_id=translation.value
			JOIN config_api_values cav ON cav.par_id=5 AND cav.value=sa.siteid
			JOIN country c ON c.code=spc.country_code AND c.name=REPLACE(cav.description,'United Kingdom','UK')
			WHERE sa.id=" . (int)$shop_offer;
		//	echo $q;
		$res = $this->_dbr->getRow($q);
        
		$cart = $this->getCart('shop_cart', 1);
		$amtPLUScart = $sa->ShopPrice + $cart->total;
		if (($this->_seller->get('free_shipping')
				&& $amtPLUScart > $this->_seller->get('free_shipping_above'))
			|| $this->_seller->get('free_shipping_total')
		) 
        {
			$offer->data->shipping_cost = 0;
		} 
        else 
        {
			// 2014-07-25 Stephan asked me to show shipping cost every time
			if (1 || $res->ShopShippingCharge) $offer->data->shipping_cost = $res->shipping_cost;
			else $offer->data->shipping_cost = $res->shipping_cost - $shipping_cost;
		}
        
		$offer->data->shipping_cost = $offer->data->shipping_cost - $sa->fake_free_shipping;
		if ($offer->data->shipping_cost < 0) $offer->data->shipping_cost = 0;

		$offer->data->ShopSAAlias = $sa->ShopSAAlias;
        
		$offer->data->ShopPrice = number_format($offer->data->ShopPrice, 2, '.', '');
		$offer->data = $this->convertPrices($offer->data);
		list($offer->data->ShopPrice1, $offer->data->ShopPrice2) = explode('.', $offer->data->ShopPrice);
		$offer->data->cat_route = "http" . ($this->_shop->ssl ? 's' : '') . '://www.' . $this->_shop->url . '/' . $sa->ShopSAAlias . '.html';

        $offer->data->rating_statistic = $this->getRating($sa->saved_id);
        
        $smarty->assign('shopCatalogue', $this);
		$smarty->assign('offer', $offer);
		$smarty->assign('cart', $shop_offer);
		$cats = $this->getAllNodes($details['shop_catalogue_id'][1]);
		$cats = array_reverse($cats);
		$cat_array = array();
		foreach ($cats as $cat) {
			if ($cat) $cat_array[$cat] = $this->_dbr->getOne("SELECT `value`
					FROM translation
					WHERE table_name = 'shop_catalogue'
					AND field_name = 'name'
					AND language = '$lang'
					AND id = " . $cat);
		}
				
		$smarty->assign('cat_array', $cat_array);
		$smarty->assign('content_title', $offer->data->ShopDesription);
		$smarty->assign('title', $offer->data->ShopDesription);
		$def_assemble_mins = $this->_dbr->getOne("SELECT minutes FROM route_delivery_type WHERE code='M'");
		$smarty->assign('def_assemble_mins', $def_assemble_mins);

		if ($this->_shop->ambassador) {
            $ambs = $this->getAmbs($shop_offer);
        }
        
//	print_r($ambs);
		$smarty->assign('ambs', $ambs);
        
        if ($sa->master_icons && $sa->master_sa) {
            $icons = $this->getIconsForOffer($sa->master_sa);
        } else {
            $icons = $this->getIconsForOffer($sa->id);
        }

        $smarty->assign('icons', $icons);
		// icons
        
        $loggedCustomer = new stdClass;
        $loggedCustomer->firstname_invoice = '';
        $loggedCustomer->name_invoice = '';
		$this->_shop->ambassador_text = substitute($this->_shop->ambassador_text, $loggedCustomer);

		/**
		 * Counting parcel weight
		 */
		$q = '
			SELECT SUM(weight_parcel) FROM (
				SELECT IFNULL(sp.id, -ap.id) id, ap.`weight_parcel`, IFNULL(sp.export,1) export
				FROM article_list al
					INNER JOIN offer_group og ON
						al.group_id = og.offer_group_id
						AND NOT base_group_id
					INNER JOIN article_parcel ap ON
						ap.article_id=al.article_id
					LEFT JOIN saved_parcel sp ON
						sp.saved_id='.$sa->master_sa.'
						AND sp.parcel_id=ap.id
				WHERE
					og.offer_id = '.$orig_offer->get('offer_id').'
					AND NOT al.inactive
					AND NOT og.additional
				HAVING export = 1

				UNION

				SELECT sp.id, sp.`weight_parcel`, sp.export
				FROM saved_parcel sp
				WHERE
					sp.saved_id = '.$sa->master_sa.'
					AND sp.parcel_id IS NULL
					AND export = 1
			) t';
		$weight = $this->_dbr->getOne($q);
		$smarty->assign('weight_kg', $weight);
		$smarty->assign('weight_g', $weight * 1000);
        
		$limit = !$this->mobile ? $this->_shop->questions_per_page : $this->_shop->questions_per_page_mobile;

		if (isset($_REQUEST['questions']) && $_REQUEST['questions'] == 'helpful') 
        {
			$questions_sort = 'helpful';
			$questions_order = 'count(sqr.id) desc, sq.`datetime` desc';
		} 
        else 
        {
			$questions_sort = 'newest';
			$questions_order = 'sq.`datetime` desc';
		}

		$qrystr = "select SQL_CALC_FOUND_ROWS  sq.id,
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
				where 1 and sq.saved_id=$shop_offer and sq.published
				group by sq.id
				order by $questions_order limit 0, $limit";
		$questions = $this->_dbr->getAll($qrystr);
		$smarty->assign("questions", $questions);
        
		$cntrec = $this->_dbr->getOne("SELECT FOUND_ROWS()");

        if ($cntrec) {
			$questions_sort_params = array('id' => $shop_offer, 'lang' => $lang, 'div' => 'div_questions', 'full' => 0);
			$smarty->assign('questions_sort_params', $questions_sort_params);
			$smarty->assign('questions_sort', $questions_sort);
			$smarty->assign("page", 1);

			if ($cntrec > $limit)
//                $pagination = !$this->mobile ?
//                    split_string_action($cntrec, $limit, 1, 'fill_shop_questions(' . $shop_offer . ', [[p]], "' . $lang . '", "div_questions", 0)', 5, 5) :
//                    split_string_action_mobile_questions_ratings($cntrec, $limit, 1);
                if(!$this->mobile){
                    $pagination = split_string_action($cntrec, $limit, 1, 'fill_shop_questions(' . $shop_offer . ', [[p]], "' . $lang . '", "div_questions", 0)', 5, 5);
                    $smarty->assign('split_string', $pagination);
                }
                else {
                    $pages_count = ceil($cntrec / $limit);
                    $smarty->assign('current_page', 1);
                    $smarty->assign('pages_count', $pages_count);
                }

			$question_ids = array();
			foreach ($questions as $question) {
				$question_ids[] = (int)$question->id;
			}

			$question_options = $this->_dbr->getAssoc("SELECT shop_sorting.url, translation.value
					FROM shop_sorting
					LEFT JOIN translation ON translation.id = shop_sorting.sort_type_id
					AND table_name = 'shop_sorting_sort_type'
					AND `field_name` = 'title'
					AND `language` = '" . $this->_shop->lang . "'
					WHERE page_type_id = 4");
			$smarty->assign("question_options", $question_options);
		}
        
		$translationShop = $this->_shop->english_shop;
		$smarty->assign("translationShop", $translationShop);
		if (count($questions)){
            $res = !$this->mobile ?
                $smarty->fetch($this->_tpls['_shop_offer_question']) :
                $smarty->fetch('shop_mobile/_shop_offer_question.tpl');
        } else {
            $res = '';
        }
		$smarty->assign('questions', $res);
        $smarty->assign('questions_count', $cntrec);

		foreach ($this->_shop->bonus_groups as $key1 => $group) {
			foreach ($group->bonuses as $key => $bonus) {
				if (in_array((int)$shop_offer, explode(',', $bonus->excluded_sas))
					|| (strlen($bonus->included_sas) && !in_array((int)$shop_offer, explode(',', $bonus->included_sas)))
				) {
					unset($this->_shop->bonus_groups[$key1]->bonuses[$key]);
				}
			}
		}
	}

	private function _collectSchemaData($sa)
	{
		global $smarty;

		$function = "_collectSchemaData({$sa->id})";
		$data = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ( ! $data)
		{
			$data = [];
			$langs = getLangsArray();

			$q = "SELECT `language`, `value`
				FROM `translation`
				WHERE `id` = {$sa->id}
				AND `table_name` = 'sa'
				AND `field_name` = 'ShopSAAlias'
				AND `unchecked` = 1
				AND `language` IN ('" . implode("','", array_keys($langs)) . "')";
			$sa_alias_translations = $this->_dbr->getAssoc($q);

			$data['same_as'] = array();
			foreach ($sa_alias_translations as $lang => $sa_alias_translation)
			{
				$data['same_as'][$lang] = 'http://www.' . $this->_shop->url . '/' . $sa_alias_translation . '.html';
			}

			$data['schema_rating'] = $this->getOfferRating($sa->id);
			$data['model'] = $this->_dbr->getOne("select par_value
                from saved_custom_params
                where saved_id = ?
                and par_key = 'model_name_maiin'
                limit 1", null, [$sa->id]);

			if ($this->_shop->curr == 'Fr.')
				$data['currency'] = 'CHF';
			else
				$data['currency'] = $this->_shop->curr;

			cacheSet($function, $this->_shop->id, $this->_shop->lang, $data);
		}

		$smarty->assign('same_as', $data['same_as']);
		$smarty->assign('schema_rating', $data['schema_rating']);
		$smarty->assign('schema_model', $data['model']);
		$smarty->assign('schema_currency', $data['currency']);
	}

    public function getOfferRatingParams($db, $dbr, $offer_id) {
        return $this->getOfferRating($offer_id);
    }

	public function getOfferRating($offer_id)
	{
		$function = "getOfferRatingParams({$offer_id})";
		$res = cacheGet($function, $this->_shop->id, '');
		if ( ! $res)
		{
			$sas = [$offer_id];
			$q = "select par_value from saved_params where saved_id = ?
                     and par_key like 'ratings_inherited_from[%'";
			foreach($this->_dbr->getAll($q, null, $offer_id) as $additional) {
				if ((int)$additional->par_value) {
                    $sas[] = (int)$additional->par_value;
                }
			}
            
            $sas = implode(',', $sas);
            
			$res = $this->_dbr->getRow("select
					round(AVG(t.code),2) avg
					, COUNT(*) count
					, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
				from
				(
				select af.code
				from auction_feedback af
				join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
				join customer c on au.customer_id=c.id
				join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
				where not au.hiderating and subau.saved_id in ($sas) and not af.hidden and au.txnid=3
                    and au.shop_id = '" . $this->_shop->id . "'
			union all
				select af.code
				from auction_feedback af
				join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
				join customer c on au.customer_id=c.id
				where not au.hiderating and au.saved_id in ($sas) and not af.hidden and au.txnid=3
                    and au.shop_id = '" . $this->_shop->id . "'
			union all
			select rating
				from saved_custom_ratings scr
				where `name`<>'' and saved_id in ($sas) and not hidden) t");

			cacheSet($function, $this->_shop->id, '', $res);
		}
		return $res;
	}

	function getAmbs($shop_offer) {
		$q = "select c.*
#				, id as customer_id
				from auction au
				join customer c on au.customer_id=c.id
				join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
					and au_name.key='name_shipping'
				join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
					and au_firstname.key='firstname_shipping'
				left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
				join invoice i on i.invoice_number=au.invoice_number
				join config on config.name='ambassador_days_after_invoice'
				join auction_par_varchar au_zip on au_zip.auction_number=au.auction_number and au_zip.txnid=au.txnid
					and au_zip.key='zip_shipping'
				join auction_par_varchar au_country on au_country.auction_number=au.auction_number and au_country.txnid=au.txnid
					and au_country.key='country_shipping'
				join auction_par_varchar au_city on au_city.auction_number=au.auction_number and au_city.txnid=au.txnid
					and au_city.key='city_shipping'
				join auction_par_varchar au_street on au_street.auction_number=au.auction_number and au_street.txnid=au.txnid
					and au_street.key='street_shipping'
				left join auction_par_varchar au_house on au_house.auction_number=au.auction_number and au_house.txnid=au.txnid
					and au_house.key='house_shipping'
				join auction_par_varchar au_email on au_email.auction_number=au.auction_number and au_email.txnid=au.txnid
					and au_email.key='email_shipping'
				where not au.hiderating and subau.saved_id=$shop_offer and au.txnid=3
			and DATEDIFF(now(),i.invoice_date)<config.value
			and c.no_showroom < {$this->_shop->ambassador_no_answer}
			and (select count(*)
				from email_log el where el.template='ambassador_request' and el.auction_number=c.id and el.txnid=-5
				and el.notes not like '%answered%'
				)
				 < {$this->_shop->ambassador_ignores}
	/*		and (select count(*)
				from email_log el where el.template='ambassador_request' and el.auction_number=c.id and el.txnid=-5
				and el.notes = CONCAT($shop_offer,'-',c.id,' answered NO')
				)*/
			and c.amb is not null
			and fget_ASent(au.auction_number, au.txnid)=1
			group by c.id
			#order by DATEDIFF(now(),i.invoice_date) desc limit 3
			union
	select c.*
				from auction au
				join customer c on au.customer_id=c.id
				join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
					and au_name.key='name_shipping'
				join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
					and au_firstname.key='firstname_shipping'
				left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
				join invoice i on i.invoice_number=au.invoice_number
				join config on config.name='ambassador_days_after_invoice'
				join auction_par_varchar au_zip on au_zip.auction_number=au.auction_number and au_zip.txnid=au.txnid
					and au_zip.key='zip_shipping'
				join auction_par_varchar au_country on au_country.auction_number=au.auction_number and au_country.txnid=au.txnid
					and au_country.key='country_shipping'
				join auction_par_varchar au_city on au_city.auction_number=au.auction_number and au_city.txnid=au.txnid
					and au_city.key='city_shipping'
				join auction_par_varchar au_street on au_street.auction_number=au.auction_number and au_street.txnid=au.txnid
					and au_street.key='street_shipping'
				left join auction_par_varchar au_house on au_house.auction_number=au.auction_number and au_house.txnid=au.txnid
					and au_house.key='house_shipping'
				join auction_par_varchar au_email on au_email.auction_number=au.auction_number and au_email.txnid=au.txnid
					and au_email.key='email_shipping'
				where not au.hiderating and au.saved_id=$shop_offer and au.txnid=3
			and DATEDIFF(now(),i.invoice_date)<config.value
			and c.no_showroom < {$this->_shop->ambassador_no_answer}
			and (select count(*)
				from email_log el where el.template='ambassador_request' and el.auction_number=c.id and el.txnid=-5
				and el.notes not like '%answered%'
				)
				 < {$this->_shop->ambassador_ignores}
	/*		and (select count(*)
				from email_log el where el.template='ambassador_request' and el.auction_number=c.id and el.txnid=-5
				and el.notes = CONCAT($shop_offer,'-',c.id,' answered NO')
				)*/
			and c.amb is not null
			and fget_ASent(au.auction_number, au.txnid)=1
			group by c.id
			#order by DATEDIFF(now(),i.invoice_date) desc limit 3
			";
	//		echo $q;
                 
		$ambs = $this->_dbr->getAll($q);
		global $loggedCustomer;
		foreach($ambs as $k=>$r) {
			$hasRMA = $this->_dbr->getOne("select count(*) from rma
				join auction au on au.auction_number=rma.auction_number and au.txnid=rma.txnid
				left join auction subau on au.auction_number=subau.main_auction_number and au.txnid=subau.main_txnid
				where ifnull(subau.saved_id, au.saved_id)={$shop_offer} and au.customer_id={$r->id}");
			if ($hasRMA) { unset($ambs[$k]); continue;}
			$hasNegaRating = $this->_dbr->getOne("select count(*) from auction_feedback af
				join auction au on au.auction_number=af.auction_number and au.txnid=af.txnid
				left join auction subau on au.auction_number=subau.main_auction_number and au.txnid=subau.main_txnid
				where ifnull(subau.saved_id, au.saved_id)={$shop_offer} and af.txnid=3 and au.customer_id={$r->id} and af.code<=3");
			if ($hasNegaRating) { unset($ambs[$k]); continue;}
			$ambs[$k]->distance = get_distances($this->_db, $this->_dbr,
				$loggedCustomer->country_shipping
				.' '.$loggedCustomer->zip_shipping
				.' '.$loggedCustomer->city_shipping
				.' '.$loggedCustomer->street_shipping
				.' '.$loggedCustomer->house_shipping
				, $r->country_shipping
				.' '.$r->zip_shipping
				.' '.$r->city_shipping
				.' '.$r->street_shipping
				.' '.$r->house_shipping
				);
			if ($ambs[$k]->distance > $this->_shop->ambassador_km_limit) {/* echo $ambs[$k]->distance.' > '.$this->_shop->ambassador_km_limit.'<br>';*/ unset($ambs[$k]);}
//			if (1*$ambs[$k]->distance==0) {/* echo 'distance is 0<br>'; */unset($ambs[$k]);}
		}
		usort($ambs, function ($a, $b) {
			$res = (((int)($a->distance*100)) - ((int)($b->distance*100)));
			return $res;
		});
		foreach($ambs as $k=>$r) {
			$ambs[$k]->label = chr($k + 65);
			if ($k > $this->_shop->ambassador_show_num) unset($ambs[$k]);
		}
	//	print_r($ambs);
		if (!count($ambs)) $ambs[] = $q;
		return $ambs;
	}

    /**
     * Get active icons for offer
     *
     * @param int $saved_id
     * @return array
     */
    function getIconsForOffer($saved_id)
    {
        if (is_array($saved_id))
        {
            $where = " AND `saved_id` IN (" . implode(',', array_map('intval', $saved_id)) . ") ";
        }
        else 
        {
            $where = " AND `saved_id` = '" . (int)$saved_id . "'";
        }
        
        $query = "SELECT `saved_id`, `par_key`
            FROM `saved_params` 
            WHERE `par_key` LIKE 'icons[%]'
                AND `par_value` = '1'
                $where
            ";

        $icons = [];
        foreach ($this->_dbr->getAll($query) as $icon)
        {
            if (preg_match('#icons\[(\d+)\]#iu', $icon->par_key, $matches))
            {
                $icons[] = (int)$matches[1];
            }
        }

        if ($icons) {
            $query = "SELECT icons.*,
                    '{$this->_shop->lang}' as lang,
                    substring_index((SELECT `value`
                    FROM translation
                    WHERE table_name = 'icons'
                    AND field_name = 'icon'
                    AND language = '{$this->_shop->lang}'
                    AND id = icons.id), '.', -1) as ext,
                    t_o.title as title,
                    t_r.value as resized
            FROM icons
            LEFT JOIN shop_icons ON shop_icons.icon_id = icons.id
            LEFT JOIN (SELECT id, value as title FROM translation WHERE table_name = 'icons'
                AND field_name = 'title'
                AND language = '{$this->_shop->lang}') t_o ON t_o.id = icons.id
            LEFT JOIN (SELECT id, value FROM translation WHERE table_name = 'icons'
                AND field_name = 'resized'
                AND language = '{$this->_shop->lang}') t_r ON t_r.id = icons.id
            WHERE shop_icons.shop_id = {$this->_shop->id}
            AND icons.active = 1 AND icons.id IN (" . implode(',', $icons) . ")";

            return $this->_dbr->getAll($query);
        }

        return [];
    }

	function listIcons($langs, $active)
	{
		$q = "select icons.*";
		foreach ($langs as $lang)
		{
			$q .= ", substring_index((SELECT `value`
							FROM translation
							WHERE table_name = 'icons'
							AND field_name = 'icon'
							AND language = '".$lang."'
							AND id = icons.id), '.', -1) as ".$lang."_ext";
			$q .= ", (SELECT `value`
				FROM translation
				WHERE table_name = 'icons'
				AND field_name = 'title'
				AND language = '".$lang."'
				AND id = icons.id) as ".$lang."_title";
		}
		$q .= " from icons
			join shop_icons on shop_icons.icon_id = icons.id
            where shop_icons.shop_id='{$this->_shop->id}'
			and active = '$active'
			order by icons.ordering";
		$icons = $this->_dbr->getAll($q);

		if (PEAR::isError($icons)) {
            aprint_r($icons);
            return;
        }
		return $icons;
	}

	static function sgetSimsParams($db, $dbr, $saved_id, $master, $orig_username, $inactive='0,1') {
        return self::sgetSims($dbr, $saved_id, $master, $orig_username, $inactive);
    }
    
    static function sgetSims($dbr, $saved_id, $master, $orig_username, $inactive='0,1') {
        global $debug;
        $cache = 1;

        $params = implode(chr(0), [$saved_id, $master, $orig_username, $inactive]);
        $function = "Shop_catalogue::sgetSimsParams($params)_b";
        $chached_ret = cacheGet($function, 0, '');
        if ($cache && $chached_ret) {
            return $chached_ret;
        }

        if ($master) 
        {
            $q = "select {$saved_id} as saved_id, sp.saved_id as sim_saved_id, sp_auction_name.par_value as auction_name, sa.inactive, sa.old
                from saved_params sp
                join saved_sim ss on ss.saved_id={$saved_id} and 1*sp.par_value=ss.sim_saved_id and ss.inactive=0
                join saved_params sp_username on sp.saved_id=sp_username.saved_id and sp_username.par_key='username'
                    and sp_username.par_value='{$orig_username}'
                LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id=sp.saved_id and sp_auction_name.par_key = 'auction_name'
                join saved_auctions sa on sa.id=sp.saved_id and sa.inactive in ($inactive) and sa.old in ($inactive)
                where sp.par_key='master_sa' and sp.saved_id
                order by ss.ordering";
        } 
        else 
        {
            $q = "select distinct ss.id
                , ss.saved_id
                , ss.sim_saved_id
                , ss.inactive+sa.old+sa.inactive as inactive
                , ss.ordering
                , sp_auction_name.par_value as auction_name
                from saved_sim ss
                LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id = ss.sim_saved_id and sp_auction_name.par_key = 'auction_name'
                join saved_auctions sa on sa.id=ss.sim_saved_id and sa.old=0 and sa.inactive=0
                where ss.saved_id=$saved_id and ss.inactive=0 and ss.sim_saved_id
                order by ss.ordering";
        }
        
        if ($debug) echo "<pre>Sims: $q</pre><br>";
        $res = $dbr->getAll($q);
        cacheSet($function, 0, '', $res);
        return $res;
    }

	function round($val) {
		return $this->_shop->intprice ? round($val) : $val;
	}

	function send_next_ambassador_request($requestor_id, $saved_id) {
		$q = "select distinct c.id customer_id, el.id email_id, sa.id saved_id
			, ar_next.ambassador_id, ar_next.requestor_id
			, shop.siteid, shop.username, shop.url, shop.id shop_id, shop.ambassador_profit
			, alias.name ShopDescription
			, tShopSAAlias.value ShopSAAlias
			, ar.id prev_ar_id
			, ar.result prev_ar_result
			, c_requestor.email_invoice requestor_email_invoice
			from customer c
			join shop on shop.id=c.shop_id
			join email_log el on el.template='ambassador_request' and el.auction_number=c.id and el.txnid=-5
			join saved_auctions sa on sa.id=SUBSTRING_INDEX(el.notes,'-',1)
			join amb_requests ar on ar.result is null and ar.request_email_id=el.id
			join customer c_requestor on ar.requestor_id=c_requestor.id
			join amb_requests ar_next on ar_next.result is null and ar_next.requestor_id=c_requestor.id and ar_next.request_email_id=0 and ar_next.saved_id=ar.saved_id
							join translation tShopDesription on tShopDesription.id=sa.id
								and tShopDesription.table_name='sa'
								and tShopDesription.field_name='ShopDesription'
								and tShopDesription.language = c.lang
							join translation tShopSAAlias on tShopSAAlias.id=sa.id
								and tShopSAAlias.table_name='sa'
								and tShopSAAlias.field_name='ShopSAAlias'
								and tShopSAAlias.language = c.lang
			join offer_name alias on tShopDesription.value=alias.id
			where ar.requestor_id=$requestor_id and ar.saved_id=$saved_id limit 1
		";
		echo nl2br($q).'<br>';
		$rec = $this->_dbr->getRow($q);
		if (!$rec->customer_id) return 0;
		$amb = $this->_dbr->getRow("select * from customer where id={$rec->ambassador_id}");
		$amb->ShopDescription = $rec->ShopDescription;
		$amb->ShopSAAlias = $rec->ShopSAAlias;
		$amb->ambassador_profit = $rec->ambassador_profit;
		$amb->url = $rec->url;
		$amb->notes = $rec->saved_id.'-'.$rec->requestor_id;
		$amb->auction_number = $rec->ambassador_id;
		$amb->txnid = -5;
		$amb->customer_id = $rec->ambassador_id;
		$amb->siteid = $rec->siteid;
		$amb->username = $rec->username;
		$amb->server = $rec->url;
		$amb->request_customer_id = $rec->requestor_id;
		$amb->shop_id = $rec->shop_id;
		$amb->attachments[] = 'html';
		$res1 = standardEmail($this->_db, $this->_dbr, $amb, 'ambassador_request');
		if ($res1) {
			$q = "select max(id) from email_log where template='ambassador_request'
				and auction_number = {$rec->ambassador_id} and txnid = -5";
			$request_email_id = $this->_db->getOne($q);
			$this->_db->query("update amb_requests set request_email_id=$request_email_id where request_email_id=0
				and saved_id=$rec->saved_id and requestor_id=$rec->requestor_id");
			$this->_db->query("update amb_requests set request_email_id=request_email_id+1 where request_email_id<0
				and saved_id=$rec->saved_id and requestor_id=$rec->requestor_id");
		}
		return 1;
	}

	function getRatingMode($mode, $saved_id) {
		switch ($mode) {
			case 'PHPSESSID';
				$res = $this->_dbr->getOne("SELECT COUNT(DISTINCT PHPSESSID) FROM `prologis_log`.`shop_page_log` WHERE saved_id=$saved_id");
			break;
			case 'ip';
				$res = $this->_dbr->getOne("SELECT COUNT(DISTINCT ip) FROM `prologis_log`.`shop_page_log` WHERE saved_id=$saved_id");
			break;
			case 'visits';
				$res = $this->_dbr->getOne("SELECT COUNT(*) FROM `prologis_log`.`shop_page_log` WHERE saved_id=$saved_id");
			break;
			case 'orders';
				$res = $this->_dbr->getOne("SELECT COUNT(*) FROM `auction` au
					JOIN auction_par_varchar apv ON apv.`auction_number`=au.`auction_number` AND apv.`txnid`=au.`txnid` AND apv.`key`='server'
					WHERE au.saved_id=$saved_id AND apv.`value` IN ('".$this->_shop->url."','www.".$this->_shop->url."') AND au.`deleted`=0
					");
			break;
			case 'rating';
				$rec = $this->getRating($saved_id);
				$res = $rec->avg;
			break;
		}
		return $res;
	}

    /**
     * Get rating info for certain category or offer or shop.
     * If $saved_id =0 and $cat_id = 0 - getting rating for whole shop.
     * @param int $saved_id used only if $cat_id = 0
     * @param int $cache_secs number of second
     * @param int $cat_id category id
     * @return stdClass with fields
     *  int $count total responses
     *  int $avg average grade
     *  string $perc  average grade in percent
     *  int $diff remaining lifetime for cache
     *  int $sum1 count of 1 grades
     *  int $sum2 count of 2 grades
     *  int $sum3 count of 3 grades
     *  int $sum4 count of 4 grades
     *  int $sum5 count of 5 grades
     */
    function getRating($saved_id, $cat_id = 0, $show_anyway = false) 
    {
        $function = false;
        if ($saved_id)
        {
            $function = "getRating($saved_id)";
        }
        else 
        {
            $function = "getRatingCat($cat_id)";
        }
        
		if ($cat_id) 
        {
			$essenceId = -$cat_id;
		} 
        else 
        {
			$essenceId = $saved_id;
		}
        
        if ( ! $this->_shop->rating && ! $show_anyway)
        {
            return ;
        }
                
        $chached_ret = cacheGet($function, $this->_shop->id, '');
        if ($chached_ret)
        {
            return $chached_ret;
        }

        $sas = $saved_id;
        if (!strlen($saved_id)) 
        {
            $saved_id=0;
        }
        
        foreach($this->_dbr->getAll("SELECT par_value FROM saved_params 
                WHERE saved_id in ($saved_id) AND par_key LIKE 'ratings_inherited_from[%'") as $sa) 
        {
            if ((int)$sa->par_value) 
            {
                $sas .= ", {$sa->par_value}";
            }
        }
        
        if ( ! $saved_id && ! $cat_id) 
        {
            $username = mysql_real_escape_string($this->_shop->username);
            
            $where = " and subau.username='$username' ";
            $where2 = " and sp.par_value='$username' ";
        } 
        else if ($cat_id) 
        {
            $sas = $this->_dbr->getOne("select group_concat(id) from sa".$this->_shop->id." sa where sa.shop_catalogue_id='$cat_id'");
            $where2 = $where = "and subau.saved_id in ($sas)";
        } 
        else if ($saved_id) 
        {
            $where2 = $where = "and subau.saved_id in ($sas)";
        }

        $qrystr = "select AVG(t.code) avg, COUNT(*) count, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
                , sum(if(t.code=1,1,0)) sum1
                , sum(if(t.code=2,1,0)) sum2
                , sum(if(t.code=3,1,0)) sum3
                , sum(if(t.code=4,1,0)) sum4
                , sum(if(t.code=5,1,0)) sum5
            from (
            select af.code
                from auction_feedback af
                join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                join customer c on au.customer_id=c.id
                join auction_par_varchar au_server on au_server.auction_number=au.auction_number and au_server.txnid=au.txnid and au_server.key='server'
                left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
                where not au.hiderating $where and not af.hidden and au.txnid=3
                and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
            union all
            select af.code
                from auction_feedback af
                join auction subau on af.auction_number=subau.auction_number and af.txnid=subau.txnid
                join customer c on subau.customer_id=c.id
                join auction_par_varchar au_server on au_server.auction_number=subau.auction_number and au_server.txnid=subau.txnid and au_server.key='server'
                where not subau.hiderating $where and not af.hidden and subau.txnid=3
                and au_server.value in  ('{$this->_shop->url}','www.{$this->_shop->url}')
            union all
            select rating
                from saved_custom_ratings subau
                join saved_params sp on sp.saved_id=subau.saved_id and sp.par_key='username'
                where `name`<>'' $where2 and not hidden) t
            ";
                
        $rating_statistic = $this->_dbr->getRow($qrystr);
        if (isset($rating_statistic->count))
        {
            $rating_statistic->count = (int)$rating_statistic->count;
        }
        
        cacheSet($function, $this->_shop->id, '', $rating_statistic);
        
        $this->_db->query("REPLACE shop_rating_cache SET
            `shop_id` = '{$this->_shop->id}',
            `saved_id` = '{$essenceId}',
            `ratings` = '".$rating_statistic->count."',
            `avg`= '".$rating_statistic->avg."',
            `sum1`= '".$rating_statistic->sum1."',
            `sum2`= '".$rating_statistic->sum2."',
            `sum3`= '".$rating_statistic->sum3."',
            `sum4`= '".$rating_statistic->sum4."',
            `sum5`= '".$rating_statistic->sum5."',
             `updated` = NOW()
            ");
            
        return $rating_statistic;
	}

	/**
	 * Get name of current shop country
	 * @return string
	 */
	public function getShopCountryName()
	{
		$res = $this->_dbr->getOne(
			'
				SELECT description
				FROM config_api_values
				WHERE
					par_id = 5
					AND value = ?
			',
			null,
			[$this->_shop->siteid]
		);
		return $res;
	}

	/**
	 * Returns shop language as ISO 639-1 code
	 * @return string
	 */
	public function getShopLanguageCode()
	{
		return self::getLanguageCode($this->_shop->lang);
	}

    /**
     * Convert language to ISO 639-1 code
     * @return string
     */
    public static function getLanguageCode($language)
	{
		static $cache = array();
		if (!isset($cache[$language])) {
			$dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
			$res = $dbr->getOne(
				'
					SELECT code
					FROM config_api_values
					WHERE
						par_id = 6
						AND value = ?
				',
				null,
				[$language]
			);
			$cache[$language] = $res;
		}
		return $cache[$language];
	}

    /**
     * Get shop country code
     * @return string code in ISO 3166-1 Alpha 2
     * @throws Exception if country code can not be found
     * @todo check and make possible to edit this data with admin panel
     */
    public function getCountryCode()
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $result = $dbr->getOne(
            '
                SELECT code
                FROM config_api_values
                WHERE
                    par_id = 5
                    AND value = ?
            ',
            null,
            [$this->_shop->siteid]
        );
        if (isset($result)) {
            return $result;
        }
        throw new \Exception('Can not find country code for country ' . $this->_shop->siteid);
    }

    /**
     * Get shop available languages
     * @return string[] array of available languages (key - language name in english, value - language name in original)
     * @throws Exception if languages can not be found
     */
    public function getSellerLanguages()
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $result = $dbr->getAssoc("
            SELECT v.value, v.description
            FROM config_api_values v
                INNER JOIN seller_lang sl ON
                    sl.lang=v.value
                    AND sl.username='{$this->_shop->username}'
            WHERE
                v.par_id = 6
                AND NOT v.inactive
                AND sl.useit = 1
            ORDER BY sl.ordering");

        if (count($result) > 0) {
            return $result;
        }
        throw new \Exception('Can not get list of available languages for shop ' . $this->_shop->username);
    }

    /**
     * Filter bonuses based on shipping art of offers
     * Unset unproper bonuses
     * @param int[] $offerIds
     * @param int|null $saved_id
     */
    public function filterBonuses($offerIds, $saved_id = null)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $bonusesIds = [];
        foreach ($this->_shop->bonus_groups[0]->bonuses as $bonus) {
            $bonusesIds[] =  $bonus->id;
        }

		// if shop#1 we take original, else take master
		// array of shippingArts for all bought SAs - taken from master SAs
        $shippingArts = $dbr->getAssoc(" SELECT distinct sp.par_value, 1
            FROM sa" . $this->_shop->id . " sa
			left join sa_all master_sa on sa.master_sa=master_sa.id
			left join saved_params sp on sp.par_key='shipping_art' and sp.saved_id=IFNULL(master_sa.id, sa.id)
            WHERE sa.offer_id IN (". implode(', ', $offerIds) . ")
		");
        foreach ($this->_shop->bonus_groups as $bonusGroupId => $bonusGroup) {
            foreach ($bonusGroup->bonuses as $bonusId => $bonus) {
                $bonusLeave = true;

                if (count($bonus->onlyShippingArts) > 0) {
                    $bonusLeave = false;
                    foreach ($shippingArts as $shippingArtId => $unused) {
                        if (in_array($shippingArtId, $bonus->onlyShippingArts)) {
                            $bonusLeave = true;
                        }
                    }
                }

                if ($saved_id 
                    && (strlen($bonus->excluded_sas) && in_array($saved_id, explode(',', $bonus->excluded_sas))
                        || (strlen($bonus->included_sas) && !in_array($saved_id, explode(',', $bonus->included_sas))))
                ) {
                    $bonusLeave = false;
                }

                if (!$bonusLeave) {
                    unset($this->_shop->bonus_groups[$bonusGroupId]->bonuses[$bonusId]);
                }
            }
        }
    }

    /**
     * Enabled login by masterpass (1 bit of options_bit is enable login by masterpass)
     * @return int
     */
    public function loginByMasterpass()
    {
        return ((int)$this->_shop->options_bit) & 0x1;
    }
    /**
     * Enabled sandbox safeypay (2 bit - enable sandbox safepay in login)
     * @return int
     */
    public function enabledSandboxSafeypay()
    {
        return ((int)$this->_shop->options_bit) & 0x2;
    }

    /**
     * Get options for mobile blocks
     * @return array
     */
    public function getMobileShowSubtitlesInArticleList()
    {
        // get current options for mobile blocks
        $this->_mobile_show_subtitles_in_article_list = $this->_dbr->getAssoc("select REPLACE(REPLACE(smbs.block_key,' ',''),'&',''),smbs.value from shop_mobile_block_subtitle smbs where shop_id={$this->_shop->id}");
        return $this->_mobile_show_subtitles_in_article_list;
    }
    /**
     * Get color type for SA
     * Return enum 'color'|'whitesh'
     * 
     * @param int $saved_id
     * @return string 
     */
    public function getColorType($saved_id) {
        if (is_array($saved_id))
        {
            return $this->getColorTypes($saved_id);
        }
        
        $categories_ids = $this->_dbr->getAssoc("SELECT DISTINCT sa.shop_catalogue_id cat_id, scs1.pic_color cat_color
            FROM sa{$this->_shop->id} sa
            JOIN shop_catalogue sc1 ON sc1.id=sa.shop_catalogue_id
            JOIN shop_catalogue_shop scs1 ON sc1.id=scs1.shop_catalogue_id
            WHERE sc1.hidden=0 AND scs1.hidden=0 AND scs1.shop_id={$this->_shop->id} AND sa.id={$saved_id}");

        $shop_pic_color = false;
        foreach ($categories_ids as $color) {
            if ($shop_pic_color === false) {
                $shop_pic_color = $color;
            } elseif ($shop_pic_color != $color) {
                $shop_pic_color = $this->_shop->shop_pic_color;
                break;
            } else {
                $shop_pic_color = $color;
            }
        }

        return $shop_pic_color == 'color' ? 'color' : 'whitesh';
    }
    
    private function getColorTypes($saved_ids) {
        $categories_ids = $this->_dbr->getAll("SELECT DISTINCT sa.shop_catalogue_id cat_id, 
                scs1.pic_color cat_color, sa.id
            FROM sa{$this->_shop->id} sa
            JOIN shop_catalogue sc1 ON sc1.id=sa.shop_catalogue_id
            JOIN shop_catalogue_shop scs1 ON sc1.id=scs1.shop_catalogue_id
            WHERE sc1.hidden=0 AND scs1.hidden=0 AND scs1.shop_id={$this->_shop->id} AND sa.id IN (" . implode(',', $saved_ids) . ")");

        $shop_pic_color = [];
        foreach ($saved_ids as $saved_id) 
        {
            $shop_pic_color[$saved_id] = false;
        }
        
        foreach ($categories_ids as $color) 
        {
            if ($shop_pic_color[$color->id] === false) {
                $shop_pic_color[$color->id] = $color->cat_color;
            } elseif ($shop_pic_color[$color->id] != $color->cat_color) {
                $shop_pic_color[$color->id] = $this->_shop->shop_pic_color;
                break;
            } else {
                $shop_pic_color[$color->id] = $color->cat_color;
            }
        }
        
        foreach ($saved_ids as $saved_id) 
        {
            $shop_pic_color[$saved_id] = $shop_pic_color[$saved_id] == 'color' ? 'color' : 'whitesh';
        }

        return $shop_pic_color;
    }

    public function getLookFirst() {
		$query = "SELECT `id` FROM `shop_looks` WHERE NOT `inactive` ORDER BY `ordering` LIMIT 1";
		return (int)$this->_dbr->getOne($query);
    }

    public function getLookId($look) {
		$query = "SELECT `shop_looks`.`id`
            FROM `shop_looks`
            JOIN `translation` ON `translation`.`id` = `shop_looks`.id
                AND `translation`.`table_name`='shop_looks'
                AND `translation`.`field_name`='Alias'
             WHERE 1 
                AND `translation`.`value`=?
                AND NOT `shop_looks`.`inactive`
            LIMIT 1";

		return (int)$this->_dbr->getOne($query, null, [$look]);
    }

    public function checkLookId($look) {
		$query = "SELECT `translation`.`language`, `shop_looks`.`id`
            FROM `shop_looks`
            JOIN `translation` ON `translation`.`id` = `shop_looks`.id
                AND `translation`.`table_name`='shop_looks'
                AND `translation`.`field_name`='Alias'
             WHERE 1 
                AND `translation`.`value`=?
                AND NOT `shop_looks`.`inactive`
        ";
        
		$items = $this->_dbr->getAssoc($query, null, [$look]);
        foreach ($items as $lang => $dummy)
        {
            if ($lang == $this->_shop->lang)
            {
                return false;
            }
        }
        
		$langs = array_keys($items);
		return $langs[0];
    }

    /**
     * Get list all shop looks
     */
    public function getLooksForOffer($saved_id, $show_active = false, $get_spots = true) {
        $saved_id = (int)$saved_id;
        
		$function = "getLooks('FOR_OFFER', " . $saved_id . ", " . ($show_active ? 'true' : 'false') . ", " . ($get_spots ? 'true' : 'false') . ")";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
        
        if ($chached_ret) {
            return $chached_ret;
        }
        
        // Get Slave shop looks
        $query[] = "
            SELECT `sls`.`look_id`
                , `slave`.`saved_id`

            FROM `shop_looks_spots` AS `sls` 
            LEFT JOIN `saved_params` AS `slave` ON `slave`.`par_key` = 'master_sa' 
                AND `slave`.`par_value` = `sls`.`saved_id`

            JOIN `saved_auctions` AS `sa` ON `sa`.`id` = `slave`.`saved_id`

            JOIN `saved_params` AS `username` ON 
                `username`.`par_key` = 'username' 
                AND `username`.`saved_id` = `slave`.`saved_id`

            JOIN `saved_params` AS `siteid` ON 
                `siteid`.`par_key` = 'siteid' 
                AND `siteid`.`saved_id` = `slave`.`saved_id`

            " . 
                
                ($show_active ? 
                "
                JOIN `saved_params` AS `sp_look` ON 
                    `sp_look`.`par_key` = 'active_look' 
                    AND `sp_look`.`par_value` = `sls`.`look_id`
                    AND `sp_look`.`saved_id` = `slave`.`saved_id`
                " : 
                "") .
                
            "
            WHERE NOT `sa`.`inactive` 

                AND `username`.`par_value` = '{$this->_shop->username}'
                AND `siteid`.`par_value` = '{$this->_shop->siteid}'

                AND `slave`.`saved_id` = '" . $saved_id . "'
        ";
        
        // Get Master shop looks
        $query[] = "
            SELECT `sls`.`look_id`
                , `sls`.`saved_id`

            FROM `shop_looks_spots` AS `sls` 

            JOIN `saved_auctions` AS `sa` ON `sa`.`id` = `sls`.`saved_id`

            JOIN `saved_params` AS `username` ON 
                `username`.`par_key` = 'username' 
                AND `username`.`saved_id` = `sls`.`saved_id`

            JOIN `saved_params` AS `siteid` ON 
                `siteid`.`par_key` = 'siteid' 
                AND `siteid`.`saved_id` = `sls`.`saved_id`
                
            " . 
                
                ($show_active ? 
                "
                JOIN `saved_params` AS `sp_look` ON 
                    `sp_look`.`par_key` = 'active_look' 
                    AND `sp_look`.`par_value` = `sls`.`look_id`
                    AND `sp_look`.`saved_id` = `sls`.`saved_id`

                " : 
                "") .
                
            "
            WHERE NOT `sa`.`inactive`

                AND `username`.`par_value` = '{$this->_shop->username}'
                AND `siteid`.`par_value` = '{$this->_shop->siteid}'

                AND `sls`.`saved_id` = '" . $saved_id . "'
        ";

        $query = implode(" UNION ALL ", $query);
        $looks_ids = $this->_dbr->getAssoc($query);
        
        if ($looks_ids)
        {
            $query = "
                SELECT `sl`.`id` AS `look_id`, `sl`.`id`, `sl`.`image_ext`, `sl`.`title`, `tf`.`md5`
                    , IFNULL(`tAlias`.`value`, `tAlias_default`.`value`) AS `Alias`
                    , IFNULL(`tDescription`.`value`, `tDescription_default`.`value`) AS `Description`
                    , IFNULL(`tTitle`.`value`, `tTitle_default`.`value`) AS `Title`
                FROM `shop_looks` AS `sl` 
                
                LEFT JOIN `prologis_log`.`translation_files2` AS `tf` ON `tf`.`table_name`='shop_looks'
                    AND `tf`.`field_name`='look'
                    AND `tf`.`id`=`sl`.`id`
                LEFT JOIN `translation` AS `tAlias` ON `tAlias`.`table_name`='shop_looks'
                    AND `tAlias`.`field_name`='Alias'
                    AND `tAlias`.`id`=`sl`.`id`
                    AND `tAlias`.`language`='{$this->_shop->lang}'
                LEFT JOIN `translation` AS `tAlias_default` ON `tAlias_default`.`table_name`='shop_looks'
                    AND `tAlias_default`.`field_name`='Alias'
                    AND `tAlias_default`.`id`=`sl`.`id`
                    AND `tAlias_default`.`language`='{$this->_seller->data->default_lang}'
                    
                LEFT JOIN `translation` AS `tDescription` ON `tDescription`.`table_name`='shop_looks'
                    AND `tDescription`.`field_name`='Description'
                    AND `tDescription`.`id`=`sl`.`id`
                    AND `tDescription`.`language`='{$this->_shop->lang}'
                LEFT JOIN `translation` AS `tDescription_default` ON `tDescription_default`.`table_name`='shop_looks'
                    AND `tDescription_default`.`field_name`='Description'
                    AND `tDescription_default`.`id`=`sl`.`id`
                    AND `tDescription_default`.`language`='{$this->_seller->data->default_lang}'
                    
                LEFT JOIN `translation` AS `tTitle` ON `tTitle`.`table_name`='shop_looks'
                    AND `tTitle`.`field_name`='Title'
                    AND `tTitle`.`id`=`sl`.`id`
                    AND `tTitle`.`language`='{$this->_shop->lang}'
                LEFT JOIN `translation` AS `tTitle_default` ON `tTitle_default`.`table_name`='shop_looks'
                    AND `tTitle_default`.`field_name`='Title'
                    AND `tTitle_default`.`id`=`sl`.`id`
                    AND `tTitle_default`.`language`='{$this->_seller->data->default_lang}'
                    
                WHERE 
                    `sl`.`id` IN (" . implode(',', array_keys($looks_ids)) . ")
                    AND NOT `sl`.`inactive`
                GROUP BY `look_id`
                ORDER BY `sl`.`ordering`
            ";
            
            $looks = $this->_dbr->getAssoc($query);
        
            $looks_ids = [];
            foreach ($looks as $_key => $_look) {
                if ( ! $_look['md5'] || ! $_look['Alias']) {
                    unset($looks[$_key]);
                    continue;
                }

                $looks[$_key]['spots'] = [];

                $looks_ids[] = (int)$_look['id'];
            }

            if ($looks_ids && $get_spots) {
                $looks_ids = implode(",", $looks_ids);

                $spots = $this->_getLooksSpots($looks_ids);

                foreach ($spots as $offer) {
                    $offer->offer = $this->getOffer($offer->saved_id);
                    if ($offer->offer) {
                        $offer->offer->rating_statistic = $this->getRating($offer->saved_id);
                        $looks[$offer->look_id]['spots'][] = $offer;
                    }
                }
            }
            
            cacheSet($function, $this->_shop->id, $this->_shop->lang, $looks);
        }
        
        return $looks;
    }

    /**
     * Get list all shop looks
     */
    public function getLooks($look = 0, $get_spots = true) {
        
        if ($get_spots)
        {
            $function = "getLooks($look)";
        }
        else
        {
            $function = "getLooks($look,false)";
        }
        
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
        
        if ($chached_ret) {
            return $chached_ret;
        }
        
        $limit = '';
        $where = '';
        if ($look === 'homepage') {
            $limit = " LIMIT " . $this->shop_looks_on_homepage;
            $where = " AND `sl`.`show_homepage` ";
        } else if ($look > 0) {
            $look = (int)$look;
            $where = " AND `sl`.`id` = $look ";
        }
        
        $query = "SELECT `sl`.`id` AS `look_id`, `sl`.`id`, `sl`.`image_ext`, `sl`.`title`, `tf`.`md5`
                    , IFNULL(`tAlias`.`value`, `tAlias_default`.`value`) AS `Alias`
                    , IFNULL(`tDescription`.`value`, `tDescription_default`.`value`) AS `Description`
                    , IFNULL(`tTitle`.`value`, `tTitle_default`.`value`) AS `Title`
                    
                FROM `shop_looks` AS `sl` 
                
                LEFT JOIN `prologis_log`.`translation_files2` AS `tf` ON `tf`.`table_name`='shop_looks'
                    AND `tf`.`field_name`='look'
                    AND `tf`.`id`=`sl`.`id`
                LEFT JOIN `translation` AS `tAlias` ON `tAlias`.`table_name`='shop_looks'
                    AND `tAlias`.`field_name`='Alias'
                    AND `tAlias`.`id`=`sl`.`id`
                    AND `tAlias`.`language`=?
                LEFT JOIN `translation` AS `tAlias_default` ON `tAlias_default`.`table_name`='shop_looks'
                    AND `tAlias_default`.`field_name`='Alias'
                    AND `tAlias_default`.`id`=`sl`.`id`
                    AND `tAlias_default`.`language`=?
                    
                LEFT JOIN `translation` AS `tDescription` ON `tDescription`.`table_name`='shop_looks'
                    AND `tDescription`.`field_name`='Description'
                    AND `tDescription`.`id`=`sl`.`id`
                    AND `tDescription`.`language`=?
                LEFT JOIN `translation` AS `tDescription_default` ON `tDescription_default`.`table_name`='shop_looks'
                    AND `tDescription_default`.`field_name`='Description'
                    AND `tDescription_default`.`id`=`sl`.`id`
                    AND `tDescription_default`.`language`=?
                    
                LEFT JOIN `translation` AS `tTitle` ON `tTitle`.`table_name`='shop_looks'
                    AND `tTitle`.`field_name`='Title'
                    AND `tTitle`.`id`=`sl`.`id`
                    AND `tTitle`.`language`=?
                LEFT JOIN `translation` AS `tTitle_default` ON `tTitle_default`.`table_name`='shop_looks'
                    AND `tTitle_default`.`field_name`='Title'
                    AND `tTitle_default`.`id`=`sl`.`id`
                    AND `tTitle_default`.`language`=?
                    
                WHERE 1
                    $where
                    AND NOT `sl`.`inactive`
                GROUP BY `look_id`
                ORDER BY `sl`.`ordering`
                $limit ";

        $looks = $this->_dbr->getAssoc($query, null, [$this->_shop->lang, $this->_seller->data->default_lang, 
            $this->_shop->lang, $this->_seller->data->default_lang, 
            $this->_shop->lang, $this->_seller->data->default_lang]);
        
        $looks_ids = [];
        foreach ($looks as $_key => $_look) {
            if ( ! $_look['md5'] || ! $_look['Alias']) {
                unset($looks[$_key]);
                continue;
            }
            
            $looks[$_key]['spots'] = [];
            $looks[$_key]['settings'] = [];
            
            $looks_ids[] = (int)$_look['id'];
        }
        
        $looks_ids = implode(",", $looks_ids);

        if ($looks_ids)
        {
            $query = "SELECT `shop_look_id`, `shop_look_par_value_id` FROM `shop_look_settings` WHERE `shop_look_id` IN ( {$looks_ids} )";
            foreach ($this->_dbr->getAll($query) as $settings) {
                $looks[$settings->shop_look_id]['settings'][] = (int)$settings->shop_look_par_value_id;
            }
        }
        
        if ($looks_ids && $get_spots) {
            $spots = $this->_getLooksSpots($looks_ids);
            foreach ($spots as $offer) {
                $offer->offer = $this->getOffer($offer->saved_id);
                if ($offer->offer) {
                    $offer->offer->rating_statistic = $this->getRating($offer->saved_id);
                    $looks[$offer->look_id]['spots'][] = $offer;
                }
            }
        }

        cacheSet($function, $this->_shop->id, $this->_shop->lang, $looks);
        return $looks;
    }

    private function _getLooksSpots($looks_ids) 
    {
        $query = [];
        $query[] = "
            SELECT IFNULL(`slave`.`saved_id`, `sls`.`saved_id`) AS `saved_id`
                , `sls`.`id` AS `spot_id`
                , `sls`.`x` AS `left`, `sls`.`y` AS `top`
                , `sls`.`look_id`

            FROM `shop_looks_spots` AS `sls` 

            LEFT JOIN `saved_params` AS `slave` ON `slave`.`par_key` = 'master_sa' 
                AND `slave`.`par_value` = `sls`.`saved_id`

            JOIN `saved_auctions` AS `sa` ON `sa`.`id` = IFNULL(`slave`.`saved_id`, `sls`.`saved_id`)

            JOIN `saved_params` AS `username` ON 
                `username`.`par_key` = 'username' 
                AND `username`.`saved_id` = IFNULL(`slave`.`saved_id`, `sls`.`saved_id`)

            JOIN `saved_params` AS `siteid` ON 
                `siteid`.`par_key` = 'siteid' 
                AND `siteid`.`saved_id` = IFNULL(`slave`.`saved_id`, `sls`.`saved_id`)

            WHERE NOT `sa`.`inactive`

                AND `username`.`par_value` = '{$this->_shop->username}'
                AND `siteid`.`par_value` = '{$this->_shop->siteid}'

                AND `sls`.`look_id` IN ( {$looks_ids} )
        ";

        $query[] = "
            SELECT `sls`.`saved_id`
                , `sls`.`id` AS `spot_id`
                , `sls`.`x` AS `left`, `sls`.`y` AS `top`
                , `sls`.`look_id`

            FROM `shop_looks_spots` AS `sls` 

            JOIN `saved_auctions` AS `sa` ON `sa`.`id` = `sls`.`saved_id`

            JOIN `saved_params` AS `username` ON 
                `username`.`par_key` = 'username' 
                AND `username`.`saved_id` = `sls`.`saved_id`

            JOIN `saved_params` AS `siteid` ON 
                `siteid`.`par_key` = 'siteid' 
                AND `siteid`.`saved_id` = `sls`.`saved_id`

            WHERE NOT `sa`.`inactive`

                AND `username`.`par_value` = '{$this->_shop->username}'
                AND `siteid`.`par_value` = '{$this->_shop->siteid}'

                AND `sls`.`look_id` IN ( {$looks_ids} )
        ";

        $query = "SELECT * FROM (
            " . implode("\nUNION ALL\n", $query) . "
                ) `t`

            GROUP BY `saved_id`
            ORDER BY `saved_id`
            ";
        
        return $this->_dbr->getAll($query);
    }

    /**
     * Gett settings for looks
     */
    public function getLooksSettings($pars_ids = [])
    {
        if ( ! $pars_ids)
        {
            return null;
        }
        
        $settings = $this->_dbr->getAssoc("
            SELECT `shop_look_par`.`id`, IFNULL(`translation`.`value`, `translation_def`.`value`) AS `name`, '' AS `values`
            FROM `shop_look_par`
            LEFT JOIN `translation` ON `translation`.`table_name` = 'shop_look_par'
                AND `translation`.`field_name` = 'name'
                AND `translation`.`language` = '{$this->_shop->lang}'
                AND `translation`.`id` = `shop_look_par`.`id`
                
            LEFT JOIN `translation` `translation_def` ON `translation_def`.`table_name` = 'shop_look_par'
                AND `translation_def`.`field_name` = 'name'
                AND `translation_def`.`language` = '{$this->_seller->data->default_lang}'
                AND `translation_def`.`id` = `shop_look_par`.`id`
                
            WHERE NOT `shop_look_par`.`inactive`
        ");
                
        foreach ($settings as $key => $setting)
        {
            $settings[$key]['values'] = [];
        }

        if ($settings)
        {
            $settings_ids = array_map('intval', array_keys($settings));
            $pars_ids = array_map('intval', $pars_ids);

            $values = $this->_dbr->getAll("SELECT `shop_look_par_value`.`id`, `shop_look_par_value`.`par_id`, 
                    IFNULL(`translation`.`value`, `translation_def`.`value`) AS `name`
                    
                FROM `shop_look_par_value` 
                LEFT JOIN `translation` ON `translation`.`table_name` = 'shop_look_par_value'
                    AND `translation`.`field_name` = `shop_look_par_value`.`id`
                    AND `translation`.`language` = '{$this->_shop->lang}'

                LEFT JOIN `translation` `translation_def` ON `translation_def`.`table_name` = 'shop_look_par_value'
                    AND `translation_def`.`field_name` = `shop_look_par_value`.`id`
                    AND `translation_def`.`language` = '{$this->_seller->data->default_lang}'

                WHERE 
                    `shop_look_par_value`.`par_id` IN (" . implode(',', $settings_ids) . ")
                    AND `shop_look_par_value`.`id` IN (" . implode(',', $pars_ids) . ")");

            foreach ($values as $value) 
            {
                $settings[$value->par_id]['values'][$value->id] = $value->name;
            }
        }
        
        foreach ($settings as $key => $setting)
        {
            if ( ! $setting['values'])
            {
                unset($settings[$key]);
            }
        }
        
        return $settings;
    }
    
    /**
     * @desc Get new arrivals
     * @var $new_arrivals_list
     * @var $new_arrivals_ids
     * @return Array of objects
     */
    public function getNewArrivals()
    {
        $new_arrivals_list = array();
        
        foreach ($this->getNewArrivalsIds() as $id) {
            $offer = $this->getOffer($id);
            
            if ($offer)
            {
                $offer->banner = $this->loadBanner($id);
                $offer->rating_statistic = $this->getRating($id);
                $new_arrivals_list[$id] = $offer;
            }
        }

        return $new_arrivals_list;
    }
    
    /**
     * @desc Get new arrivals ids
     * @var $new_arrivals_list
     * @var $new_arrivals_ids
     * @return Array of objects
     */
    public function getNewArrivalsIds()
    {
        $function = 'getNewArrivalsIds()';
        $chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
        
        if ($chached_ret)
        {
            return $chached_ret;
        }
        
        $new_arrivals_ids = [];
//        $query = "
//            SELECT DISTINCT `saved_id` FROM (
//
//                SELECT `saved_params`.`id`, `sa`.`id` AS `saved_id`
//                FROM `sa" . $this->_shop->id . "` `sa`
//                JOIN `saved_auctions` ON `saved_auctions`.`id` = `sa`.`id`
//                JOIN `translation` `tShopDesription` ON `tShopDesription`.`id` = `sa`.`id`
//                    AND `tShopDesription`.`table_name`='sa'
//                    AND `tShopDesription`.`field_name`='ShopDesription'
//                    AND `tShopDesription`.`language` = '" . $this->_shop->lang . "'
//                JOIN `translation` `tShopDesription_default` ON `tShopDesription_default`.`id` = `sa`.`id`
//                    AND `tShopDesription_default`.`table_name`='sa'
//                    AND `tShopDesription_default`.`field_name`='ShopDesription'
//                    AND `tShopDesription_default`.`language` = '" . $this->_seller->data->default_lang . "'
//                JOIN `offer_name` `alias` ON IFNULL(`tShopDesription`.`value`, `tShopDesription_default`.`value`) = `alias`.`id`
//                JOIN `saved_params` ON `saved_params`.`saved_id` = `sa`.`master_sa` 
//                    AND `par_key` = 'updated'
//
//                WHERE `alias`.`name` != ''
//                    AND NOT `saved_auctions`.`inactive`
//                    AND `sa`.`old` = 0
//
//                UNION 
//
//                SELECT `saved_params`.`id`, `sa`.`id` AS `saved_id`
//                FROM `sa" . $this->_shop->id . "` `sa`
//                JOIN `saved_auctions` ON `saved_auctions`.`id` = `sa`.`id`
//                JOIN `translation` `tShopDesription` ON `tShopDesription`.`id` = `sa`.`id`
//                    AND `tShopDesription`.`table_name`='sa'
//                    AND `tShopDesription`.`field_name`='ShopDesription'
//                    AND `tShopDesription`.`language` = '" . $this->_shop->lang . "'
//                JOIN `translation` `tShopDesription_default` ON `tShopDesription_default`.`id` = `sa`.`id`
//                    AND `tShopDesription_default`.`table_name`='sa'
//                    AND `tShopDesription_default`.`field_name`='ShopDesription'
//                    AND `tShopDesription_default`.`language` = '" . $this->_seller->data->default_lang . "'
//                JOIN `offer_name` `alias` ON IFNULL(`tShopDesription`.`value`, `tShopDesription_default`.`value`) = `alias`.`id`
//                JOIN `saved_params` ON `saved_params`.`saved_id` = `sa`.`id`
//                    AND `par_key` = 'updated'
//
//                WHERE `alias`.`name` != ''
//                    AND NOT `saved_auctions`.`inactive`
//                    AND `sa`.`old` = 0
//
//            ) `t` ORDER BY `id` DESC LIMIT " . $this->_shop->new_arrival_quantity;
//        $new_arrivals = $this->_dbr->getAll($query);
//        foreach ($new_arrivals as $new) {
//            $new_arrivals_ids[] = (int)$new->saved_id;
//        }
        
        if (count($new_arrivals_ids) < $this->_shop->new_arrival_quantity)
        {
            $query = "
                SELECT DISTINCT `sa`.`id`
                FROM `sa" . $this->_shop->id . "` `sa`
                left join sa_all master_sa on sa.master_sa=master_sa.id
                
                left join translation tShopSAAlias on tShopSAAlias.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                    and tShopSAAlias.table_name='sa'
                    and tShopSAAlias.field_name='ShopSAAlias'
                    and tShopSAAlias.language = '{$this->_shop->lang}'
                left join translation tShopSAAlias_default on tShopSAAlias_default.id=IF(IFNULL(sa.master_ShopSAAlias,1),IFNULL(master_sa.id, sa.id), sa.id)
                    and tShopSAAlias_default.table_name='sa'
                    and tShopSAAlias_default.field_name='ShopSAAlias'
                    and tShopSAAlias_default.language = '{$this->_seller->data->default_lang}'

                WHERE NOT ISNULL(IFNULL(tShopSAAlias.value, tShopSAAlias_default.value))
                    AND NOT `sa`.`inactive`
                    AND `sa`.`old` = 0
                ORDER BY `sa`.`id` DESC 
                LIMIT " . ($this->_shop->new_arrival_quantity - count($new_arrivals_ids));
            $new_arrivals = $this->_dbr->getAll($query);
            foreach ($new_arrivals as $new) {
                $new_arrivals_ids[] = (int)$new->id;
            } 
        }
        
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $new_arrivals_ids, 3600);
        return $new_arrivals_ids;
    }
	
    /**
     * @desc Get a list of auftrags, where the voucher were used
     * @param int $voucher_id id of the voucher
     * @param string $limit limits the query
     * @return Array of objects
     */
	public function get_voucher_auctions($voucher_id, $limit='', $cnt) {
		$db = $this->_db;
		$dbr = $this->_dbr;
		$q = "select SQL_CALC_FOUND_ROWS au.auction_number, au.txnid, au.end_time, au.offer_id, au.customer_id
				, IF(au.saved_id, CONCAT('<a target=\"_blank\" href=\"newauction.php?edit=',au.saved_id,'\">',au.saved_id,'</a>')
					,(select GROUP_CONCAT(CONCAT('<a target=\"_blank\" href=\"newauction.php?edit=',a1.saved_id,'\">',a1.saved_id,'</a>') SEPARATOR '<br><br>') from auction a1
						where a1.main_auction_number=au.auction_number and a1.main_txnid=au.txnid)) saved_id
				, fget_Offer_name(au.auction_number, au.txnid) as offer_name
	/*			, IFNULL(CONCAT('<a target=\"_blank\" href=\"offer.php?id=',o.offer_id,'\">',o.name,'</a>'),
					(select GROUP_CONCAT(CONCAT('<a target=\"_blank\" href=\"offer.php?id=',a1.offer_id,'\">',o1.name,'</a>') SEPARATOR '<br><br>') from offer o1
						join auction a1 on a1.offer_id=o1.offer_id
						where a1.main_auction_number=au.auction_number and a1.main_txnid=au.txnid)) as offer_name*/
				, (select group_concat(rma_id) from rma where auction_number=au.auction_number and txnid=au.txnid) tickets
			from auction au
			left join offer o on o.offer_id=au.offer_id
			where au.deleted=0 and au.code_id=$voucher_id
	#		and au.auction_number=89554
			order by au.end_time desc 
			$limit
			";
		$auctions = $dbr->getAll($q);
		$cnt = $dbr->getOne("SELECT FOUND_ROWS()");
		foreach($auctions as $k=>$auction) {
			$auctions[$k]->brutto_income_2_EUR = 0;
			$calc = Auction::getCalcs($db, $dbr, array($auction), 1, 1);
			foreach($calc as $calc) {
				if ($calc->sum) {
					$auctions[$k]->sold_for_amount = $cat->sold_for_amount;
					$auctions[$k]->sold_for_amount_EUR = $auctions[$k]->sold_for_amount / $calc->curr_rate;
					$auctions[$k]->brutto_income_2 = $calc->brutto_income_2;
					$auctions[$k]->brutto_income_2_EUR = $calc->brutto_income_2_EUR;
					$auctions[$k]->revenue = $calc->revenue;
					$auctions[$k]->revenue_EUR = $calc->revenue_EUR;
				}
			}
			$subaus = $dbr->getAll("select auction_number, txnid from auction where 
				main_auction_number=$auction->auction_number and main_txnid=$auction->txnid");
			foreach($subaus as $subau) {
				$calc = Auction::getCalcs($db, $dbr, array($subau), 1, 1);
				foreach($calc as $calc) {
					if ($calc->sum) {
						$auctions[$k]->brutto_income_2 += $calc->brutto_income_2;
						$auctions[$k]->brutto_income_2_EUR += $calc->brutto_income_2_EUR;
						$auctions[$k]->revenue += $calc->revenue;
						$auctions[$k]->revenue_EUR += $calc->revenue_EUR;
					}
				}
			}
			$after_aus = $dbr->getAll("select * from auction where customer_id={$auction->customer_id} and end_time>'{$auction->end_time}'");
			$after_calc = Auction::getCalcs($db, $dbr, $after_aus, 1, 1);
			foreach($after_calc as $calc) {
					if ($calc->sum) {
						$auctions[$k]->after_brutto_income_2 = $calc->brutto_income_2;
						$auctions[$k]->after_brutto_income_2_EUR = $calc->brutto_income_2_EUR;
						$auctions[$k]->after_revenue = $calc->revenue;
						$auctions[$k]->after_revenue_EUR = $calc->revenue_EUR;
					}
			}
			$auctions[$k]->sold_for_amount_EUR *= 1;
			$auctions[$k]->brutto_income_2 *= 1;
			$auctions[$k]->brutto_income_2_EUR *= 1;
		} // foreach auction
		return $auctions;
	} //get_voucher_auctions

    /**
     * @desruption get pages where we need to hide left menu
     * @return associative array 
     */
    public function getLeftHideMenu()
    {
        $result = $this->_dbr->getAssoc("SELECT id, page FROM shop_left_menu_hide WHERE shop_id = " . $this->_shop->id);
        return $result;
    }
    
    
    public function getOfferDetails($shop_offer) 
    {
        global $debug_speed, $getMDB;
        $__debug_time = microtime(true);
        
		$function = "getOfferDetails($shop_offer)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret && ! $this->isPreview) {
			return $chached_ret;
		}
        
        $all_langs = getLangsArray();
        $sa = $this->getOffer($shop_offer);
        // Get details + SA
//        $details = \Saved::getDetails($sa->saved_id);
//        $details = array_merge($details, (array)$sa);
        
        $offer = new Offer($this->_db, $this->_dbr, $sa->offer_id, $this->_shop->lang);
        
        $details['assembled'] = $this->_shop->english[$offer->data->assembled];
        $details['assemble_mins'] = $offer->data->assemble_mins !== null ? (int)$offer->data->assemble_mins : 
                (int)$this->_dbr->getOne("SELECT minutes FROM route_delivery_type WHERE code='M'");
        
        if ($details['assemble_mins'])
        {
            $details['assemble_mins'] = $this->_shop->english_shop[171] . ' ' . $details['assemble_mins'] . ' min';
        }
        else
        {
            $details['assemble_mins'] = '';
        }
        
        // Get standart mulangs
        $details = array_merge($details, $this->getOfferDetailsMulang($sa->master_sa, $details));
        $details = array_merge($details, $this->getOfferDetailsColors($details['color_id']));
        $details = array_merge($details, $this->getOfferDetailsMaterials($details['material_id']));

//        $details = array_merge($details, $this->getOfferDetailsShopPars($sa->master_sa));
//        $details = array_merge($details, $this->getOfferDetailsCustomPars($sa->master_sa));
        
        $details = array_merge($details, $this->getOfferDetailsDimentions($sa->master_sa, $sa->offer_id, $details));
        $details = array_merge($details, $this->getOfferDetailsMargin($sa->saved_id, $details));
        $details = array_merge($details, $this->getOfferDetailsFields());
        $details = array_merge($details, $this->getOfferDetailsContents());
        $details = array_merge($details, $this->getOfferDetailsDescription($sa->master_sa));
        $details = array_merge($details, $this->getOfferDetailsTranslation());

        $details = \Saved::detailsToArray($details);
        
        $keys = [];
        foreach ($details as $key => $dummy)
        {
            foreach ($all_langs as $lang => $dummy)
            {
                $key = preg_replace("#_$lang]]\$#iu", ']]', $key);
            }
            
            if (isset($keys[$key]))
            {
                continue;
            }
            
            $key = "('" . mysql_real_escape_string($key) . "')";
            $keys[$key] = true;
        }
        
        if ($keys)
        {
            $this->_db->query("INSERT IGNORE INTO `sa_template_suggest` (`name`) VALUES " . implode(',', array_keys($keys)));
        }

        cacheSet($function, $this->_shop->id, $this->_shop->lang, $details);
        
        return $details;
    }
    
    /**
     * Get standart mulangs
     * 
     * @param type $master_sa
     * @param type $details
     * @return type
     */
    private function getOfferDetailsMulang($master_sa, $details) 
    {
        $all_langs = getLangsArray();
        
        $fields = \Saved::$MULANG_FIELDS;
        $fields =  array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, \Saved::$MULANG_FIELDS);
        
        $query = "
            SELECT `field_name`, `language`, `value`
            FROM `translation` 
            WHERE `table_name` = 'sa'
            AND `field_name` IN (" . implode(',', $fields) . ")
            AND `id` = '" . $master_sa . "'
        ";
        
        $mulang_data = [];
        foreach ($this->_dbr->getAll($query) as $mulang)
        {
            $mulang_data[$mulang->field_name][$mulang->language] = $mulang->value;
        }
        
        // Get standart mulangs
        $ShopDesription = false;
        $master_shop_id = false;
        $mulang = [];
        foreach ($mulang_data as $_key => $langs_data)
        {
            if ($_key == 'descriptionTextShop2') 
            {
                if ( ! $master_shop_id)
                {
                    $master_shop_id = $this->_dbr->getOne("SELECT `id` FROM `shop` 
                        WHERE `username` = '" . $details['username'] . "'
                            AND `siteid` = '" . (int)$details['siteid'] . "' 
                            ORDER BY `id` LIMIT 1");
                }
                
                foreach ($all_langs as $lang => $dummy) 
                {
                    if ( ! $langs_data[$lang])
                    {
                        $langs_data[$lang] = \Saved::getDescriptionTextShop2($master_sa, $master_shop_id, $lang);
                    }
                }
            }
            else if ($_key == 'ShopDesription') 
            {
                if ( ! $ShopDesription)
                {
                    $ShopDesription = $this->_dbr->getAssoc("select language, value from translation where table_name='sa' 
                        and field_name='ShopDesription' and id=" . $master_sa);
                }
                
                foreach ($langs_data as $lang_id => $_value) 
                {
                    $langs_data[$lang_id] = $this->_dbr->getOne("select name from offer_name where id='" . (int)$_value->value . "'");
                    if (!strlen($details[$lang_id])) 
                    {
                        $langs_data[$lang_id] = $this->_dbr->getOne("select name from offer_name where id='" . (int)$ShopDesription[$lang_id] . "'");
                    }
                }
            }

            foreach ($langs_data as $_lang => $_value)
            {
                $mulang[$_key][$_lang] = is_string($_value) ? $_value : $_value->value;
            }
        }

        return $mulang;
    }
    
    /**
     * Get colors mulangs
     * 
     * @param type $color_id
     */
    private function getOfferDetailsColors($color_id) 
    {
        $mulang = [];
        
        $query = "
            SELECT `language`, `value`
            FROM `translation` 
            WHERE `table_name` = 'sa'
            AND `field_name` = 'sa_color'
            AND `id` = '" . $color_id . "'
        ";
        
        foreach ($this->_dbr->getAssoc($query) as $_lang => $_value)
        {
            $mulang['colors'][$_lang] = $_value;
        }
        
        return $mulang;
    }
    
    /**
     * Get materials mulangs
     * 
     * @param type $material_id
     */
    private function getOfferDetailsMaterials($material_id) 
    {
        $mulang = [];
        
        $query = "
            SELECT `language`, `value`
            FROM `translation` 
            WHERE `table_name` = 'sa'
            AND `field_name` = 'sa_material'
            AND `id` = '" . $material_id . "'
        ";
        
        foreach ($this->_dbr->getAssoc($query) as $_lang => $_value)
        {
            $mulang['materials'][$_lang] = $_value;
        }
        return $mulang;
    }
    
    /**
     * Get shop params
     * 
     * @param type $master_sa
     * @return type
     */
    private function getOfferDetailsShopPars($master_sa) 
    {
        $all_langs = getLangsArray();
        
        $query = "SELECT REPLACE(REPLACE(`par_key`, 'shop_catalogue_id[', ''), ']', '') `shop_id`, `par_value` `shop_cataloue_id`
                FROM `saved_params` `sp`
                WHERE `saved_id` = '" . $master_sa . "' AND `par_key` LIKE 'shop_catalogue_id[%]'";
        $cats = $this->_dbr->getAll($query);

        $cats_conds = [];
        foreach ($cats as $cat) 
        {
            $cats_conds[] = " (`shop_id` = '" . $cat->shop_id . "' AND `shop_catalogue_id` = '" . $cat->shop_cataloue_id . "') ";
        }

        $pars = [];
        if ($cats_conds)
        {
            $query = "SELECT DISTINCT `sn`.* FROM `Shop_Name_Cat` `snc`
                JOIN `Shop_Names` `sn` ON `sn`.`id` = `snc`.`NameID` AND IFNULL(`sn`.`def_value`, '') = ''
                WHERE " . implode(' OR ', $cats_conds);
            $pars = $this->_dbr->getAll($query);
        }

        $details = [];
        foreach ($pars as $par_key => $par) 
        {
            if ($par->translatable) 
            {
                foreach ($all_langs as $lang => $dummy) 
                {
                    switch ($par->ValueType) 
                    {
                        case 'text':
                            $q = "select spv.*
                                , IFNULL(t.value, spv.FreeValueText) as value
                                from saved_parvalues spv
                                left join Shop_Values sv on sv.id=spv.ValueID
                                left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
                                    and t.language='{$lang}' and t.id=sv.id
                                where spv.saved_id='" . $master_sa . "' and spv.NameID='{$par->id}'
                                and sv.inactive=0
                                order by sv.ordering";
                            break;
                        case 'dec':
                            $q = "select spv.*
                                , IFNULL(t.value, spv.FreeValueDec) as value
                                from saved_parvalues spv
                                left join Shop_Values sv on sv.id=spv.ValueID
                                left join translation t on t.table_name='Shop_Values' and t.field_name='ValueDec'
                                    and t.language='{$lang}' and t.id=sv.id
                                where spv.saved_id='" . $master_sa . "' and spv.NameID='{$par->id}'
                                and sv.inactive=0
                                order by sv.ordering";
                            break;
                        case 'img':
                            $q = "select spv.*
                                , t.value as value
                                from saved_parvalues spv
                                left join Shop_Values sv on sv.id=spv.ValueID
                                left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
                                    and t.language='{$lang}' and t.id=sv.id
                                where spv.saved_id='" . $master_sa . "' and spv.NameID='{$par->id}'
                                and sv.inactive=0
                                order by spv.id";
                            break;
                    }
                    $values = $this->_dbr->getAll($q);
                    foreach ($values as $i => $value) {
                        $details['shop_par_name_' . str_replace(' ', '_', $par->Name) . '_' . $lang . '_' . $i] = $value->value;
                    } // foreach value

                } // foreach lang
            } 
            else 
            {
                switch ($par->ValueType) 
                {
                    case 'text':
                        $qvalue = "IFNULL(sv.ValueText, spv.FreeValueText)";
                        break;
                    case 'dec':
                        $qvalue = "IFNULL(sv.ValueDec, spv.FreeValueDec)";
                        break;
                    case 'img':
                        $qvalue = "sv.id";
                        break;
                }

                $q = "select spv.*
                    , $qvalue as value
                    from saved_parvalues spv
                    left join Shop_Values sv on sv.id=spv.ValueID
                    where saved_id='" . $master_sa . "' and spv.NameID='{$par->id}'
                            and sv.inactive=0
                            order by sv.ordering";
                $values = $this->_dbr->getAll($q);
                foreach ($values as $i => $value) {
                    $details['shop_par_name_' . str_replace(' ', '_', $par->Name) . '_' . $i] = $value->value;
                } // foreach value
            } // if not translatable
        } // foreach par
        
        return $details;
    }
    
    /**
     * Get custom params
     * 
     * @param type $master_sa
     * @return type
     */
    private function getOfferDetailsCustomPars($master_sa) 
    {
        // Get custom params
        $query = "
            SELECT `t`.`par_key` AS `key`
                , GROUP_CONCAT(DISTINCT `scp`.`par_value`) AS `value`
                , (SELECT MAX(`scp`.`inactive`) FROM `saved_custom_params` `scp`
                    WHERE `scp`.`par_key`=`t`.`par_key` LIMIT 1) AS `inactive`
            FROM (SELECT DISTINCT(`par_key`) `par_key` FROM `saved_custom_params`) `t`
            LEFT JOIN `saved_custom_params` `scp` ON `scp`.`par_key`=`t`.`par_key` AND `saved_id` = '" . $master_sa . "'
            GROUP BY `t`.`par_key` 
            HAVING NOT `inactive`
            ORDER BY `t`.`par_key`
        ";

        $details = [];
        foreach ($this->_dbr->getAll($query) as $_value) {
            $details[$_value->key] = $_value->value;
        }
        
        return $details;
    }
    
    /**
     * 
     * @param type $master_sa
     */
    private function getOfferDetailsDimentions($master_sa, $offer_id, $details) 
    {
        // Det dimentions
        $stop_empty_warehouse = [];
        switch ($this->_seller->data->seller_channel_id) {
            case 1:
                $_stock_warehouse_parkey = 'stop_empty_warehouse';
//                $stop_empty_warehouse = $details['stop_empty_warehouse'];
                if ($details['fixedprice']) {
                    $shipping_plan_id_fn = 'f';
                } else {
                    $shipping_plan_id_fn = '';
                }
                break;
            case 2:
                $_stock_warehouse_parkey = 'stop_empty_warehouse_ricardo';
//                $stop_empty_warehouse = $details['stop_empty_warehouse_ricardo'];
                if ($details['Ricardo']['Channel'] == 2)
                    $shipping_plan_id_fn = 'f';
                else
                    $shipping_plan_id_fn = '';
                break;
            case 3:
                $_stock_warehouse_parkey = 'stop_empty_warehouse_amazon';
//                $stop_empty_warehouse = $details['stop_empty_warehouse_amazon'];
                $shipping_plan_id_fn = '';
                break;
            case 4:
                $_stock_warehouse_parkey = 'stop_empty_warehouse_shop';
//                $stop_empty_warehouse = $details['stop_empty_warehouse_shop'];
                $shipping_plan_id_fn = 's';
                break;
            case 5:
                $_stock_warehouse_parkey = 'stop_empty_warehouse_Allegro';
//                $stop_empty_warehouse = $details['stop_empty_warehouse_Allegro'];
                $shipping_plan_id_fn = 'a';
                break;
        }

        $stop_empty_warehouse = $this->_dbr->getCol("select `par_value`
            from `saved_params` where `saved_id` = '" . (int)$master_sa . "' and `par_key` like '{$_stock_warehouse_parkey}%'");

        $resMinStock = getMinStock($this->_db, $this->_dbr, $master_sa, $offer_id, $stop_empty_warehouse, 4);

        $return = [];
        foreach (\Warehouse::listArray($this->_db, $this->_dbr) as $wid => $wname) {
            $return['minavailable_' . strtolower(str_replace(':', '', str_replace(' ', '_', $wname)))] = $resMinStock['minavas'][$wid];
        }

        $return['total_article_number'] = $resMinStock['total_article_number'];
        if ( ! isset($details['total_carton_number'])) 
        {
            $return['total_carton_number'] = $resMinStock['total_article_number'];
        }

        $return['minstock'] = $resMinStock['minstock'];
        $return['minavailable'] = $resMinStock['minava'];
        $return['weight_kg'] = $resMinStock['weight'];
        $return['weight_g'] = $resMinStock['weight'] * 1000;

        $return['weight_kg_text'] = $this->_shop->english[113] . ' ';
        if ($return['weight_kg'] > 1)
        {
            $return['weight_kg_text'] .= round($return['weight_kg']) . ' Kg';
        }
        else
        {
            $return['weight_kg_text'] .= round($return['weight_kg'], 2) . ' Kg';
        }
        
//            $saved_dimensions = new \SavedDimensions($master_sa, $offer_id, $this->_seller->data->id, $this->_db, $this->_dbr);
//            $dimensions = $saved_dimensions->get();
        
        return $return;
    }
    
    private function getOfferDetailsMargin($saved_id, $details) 
    {
        $return = [];
        
        $margin = getSAMargin((int)$saved_id);
        $return['margin_abs'] = $margin->margin_abs;
        $return['margin_perc'] = $margin->margin_perc;
        $return['total_purchase_price_local'] = $margin->total_purchase_price_local;
        $return['total_purchase_price_local_sh_vat'] = $margin->total_purchase_price_local_sh_vat;

        // Get source sellers prices
        $ss_list = $this->_dbr->getAll("SELECT * FROM `source_seller` WHERE `pp_show`=1");

        $margin->total_purchase_price_local_sh_vat = $margin->total_purchase_price_local_sh_vat * 1;
        $margin->total_purchase_price_local = $margin->total_purchase_price_local * 1;

        foreach ($ss_list as $kss => $ss) 
        {
            $field_text = $ss->pp_formula;
            $field_text = str_replace('[[total_purchase_price_local]]', $margin->total_purchase_price_local, $field_text);
            $field_text = str_replace('[[total_purchase_price_local_sh_vat]]', $margin->total_purchase_price_local_sh_vat, $field_text);
            $field_text = str_replace('[[ShopPrice]]', (float)$details['ShopPrice'], $field_text);
            $field_text = str_replace('[[ShopHPrice]]', (float)$details['ShopHPrice'], $field_text);

            if (empty($field_text))
            {
                $field_text = 0;
            }
            else
            {
                $field_text = eval("return $field_text;");
            }

            $return["ss_pp_" . str_replace(' ', '_', $ss->name)] = $field_text;
        }
        
        return $return;
    }

    private function getOfferDetailsFields() 
    {
        $fields = $this->_dbr->getAssoc("
            SELECT `sa_field`.`id`, IFNULL(`translation`.`value`, `translation_def`.`value`) AS `name`
            FROM `sa_field`
            LEFT JOIN `translation` ON `translation`.`table_name` = 'sa_field'
                AND `translation`.`field_name` = 'field_name'
                AND `translation`.`language` = '{$this->_shop->lang}'
                AND `translation`.`id` = `sa_field`.`id`
                
            LEFT JOIN `translation` `translation_def` ON `translation_def`.`table_name` = 'sa_field'
                AND `translation_def`.`field_name` = 'field_name'
                AND `translation_def`.`language` = '{$this->_seller->data->default_lang}'
                AND `translation_def`.`id` = `sa_field`.`id`
                
            WHERE NOT `sa_field`.`inactive`
        ");
        
        $details = [];
        foreach ($fields as $id => $name)
        {
            $details["field_" . $id] = $name;
            $details["field_" . $name] = $name;
        }
        
        return $details;
    }
    
    private function getOfferDetailsContents() 
    {
        $contents = $this->_dbr->getAssoc("
                SELECT `id`, `name`
                FROM `sa_content`
                WHERE NOT `inactive`
                ");
        
        $details = [];
        foreach ($contents as $id => $name)
        {
            $details["content_" . $id] = $name;
            $details["content_" . $name] = $name;
        }
        
        return $details;
    }
    
    protected function getOfferDetailsDescription($master_sa)
    {
        $type_id = (int)$this->_dbr->getOne("
            SELECT `par_value`
            FROM `saved_params`
            WHERE `par_key` = 'sa_type' AND `saved_id` = '{$master_sa}'");

        if ( ! $type_id) 
        {
            return [];
        }
        
        $contents = [];
        $contents_all = $this->_dbr->getAll("
            SELECT CONCAT(`sa_content_field`.`field_id`, '_', `sa_content`.`id`) AS `key`
                , `sa_content`.`id`
                , `sa_content_field`.`field_id`
                , `sa_content`.`name` AS `content_name`
                , IFNULL(IFNULL(`translation_field`.`value`, `translation_field_def`.`value`), `sa_field`.`name`) AS `field_name`
                , `sa_field`.`name` AS `sa_field_name`
                , `sa_content`.`kind`
                , `sa_content`.`alt_name`
                , `sa_content`.`formula`
                , GROUP_CONCAT(`sa_content_value`.`id`) AS `values`
            
            FROM `sa_content`
            JOIN `sa_content_field` ON `sa_content_field`.`content_id` = `sa_content`.`id`
            
            JOIN `sa_field_type` ON `sa_field_type`.`field_id` = `sa_content_field`.`field_id`
            JOIN `sa_field` ON `sa_field`.`id` = `sa_field_type`.`field_id`
            LEFT JOIN `translation` `translation_field` ON `translation_field`.`table_name` = 'sa_field'
                AND `translation_field`.`field_name` = 'field_name'
                AND `translation_field`.`language` = '{$this->_shop->lang}'
                AND `translation_field`.`id` = `sa_field`.`id`
                
            LEFT JOIN `translation` `translation_field_def` ON `translation_field_def`.`table_name` = 'sa_field'
                AND `translation_field_def`.`field_name` = 'field_name'
                AND `translation_field_def`.`language` = '{$this->_seller->data->default_lang}'
                AND `translation_field_def`.`id` = `sa_field`.`id`

            LEFT JOIN `sa_content_value` ON `sa_content_value`.`content_id` = `sa_content`.`id`
            WHERE 
                NOT `sa_content`.`inactive` 
                AND NOT `sa_field`.`inactive` 
                AND `sa_field_type`.`type_id` = '" . $type_id . "'
            GROUP BY `key`
        ");
                
        $content_ids = [];
        $content_fields = [];
        foreach ($contents_all as $_content) 
        {
            if ( ! isset($contents[$_content->key])) {
                $contents[$_content->key] = $_content;                    

                $contents[$_content->key]->values = explode(',', $contents[$_content->key]->values);
                $contents[$_content->key]->values = array_map('intval', $contents[$_content->key]->values);

                $contents[$_content->key]->contents = [];
                if ($contents[$_content->key]->values) 
                {
                    $content_ids[] = (int)$_content->id;
                    $content_fields = array_merge($content_fields, $contents[$_content->key]->values);
                }
            }
        }
        
        if ($content_ids)
        {
            $content_ids = implode(',', $content_ids);
            
            $content_fields = array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, $content_fields);
            $content_fields = implode(',', $content_fields);
            
            $content_values = $this->_dbr->getAll("
                SELECT `translation`.`id`, `translation`.`field_name`, 
                    IFNULL(`translation`.`value`, `default`.`value`) AS `value`
                FROM `translation`
                LEFT JOIN `translation` AS `default` ON `default`.`table_name` = `translation`.`table_name`
                    AND `default`.`field_name` = `translation`.`field_name`
                    AND `default`.`id` = `translation`.`id`
                WHERE `translation`.`table_name` = 'sa_content_value'
                    AND `translation`.`field_name` IN (" . $content_fields . ")
                    AND `translation`.`id` IN (" . $content_ids . ")
                    AND `translation`.`language` = '{$this->_shop->lang}'
                    AND IFNULL(IFNULL(`translation`.`value`, `default`.`value`), '') != ''
                    AND `default`.`language` = '{$this->_seller->data->default_lang}'
            ");

            $content_values_array = [];
            foreach ($content_values as $_value)
            {
                $content_values_array[$_value->field_name][$_value->id] = $_value->value;
            }
                    
            foreach ($contents as $key => $content) 
            {
                if ($content->values) 
                {
                    foreach($content->values as $mulang_field) 
                    {
                        $contents[$key]->contents[$mulang_field][$this->_shop->lang] = $content_values_array[$mulang_field][$content->id];
//                        foreach ($content_values as $_value)
//                        {
//                            if ($_value->field_name == $mulang_field && $_value->id == $content->id)
//                            {
////                                $_field = new stdClass;
////                                $_field->value = $_value->value;
////                                $_field->language = $this->_shop->lang;
////                                $_field->id = $_value->id;
////                                $_field->table_name = 'sa_content_value';
////                                $_field->field_name = $_value->field_name;
//                                
//                                $contents[$key]->contents[$mulang_field][$this->_shop->lang] = $_value->value;
//                            }
//                        }
                    }
                }
            }
        }
        
        $sa_description = $this->_dbr->getAll("
            SELECT `sa_field_content_value_sa`.*
            FROM `saved_params`
            JOIN `sa_field_content_value_sa` ON `sa_field_content_value_sa`.`id` = `saved_params`.`par_value`
            WHERE `par_key` = 'sa_description[]' AND `saved_id` = '{$master_sa}'
        ");
            
//        $details = [];
        $fields_titles = [];
        $description_details = [];
        if ($sa_description) 
        {
            foreach ($sa_description as $_checked) 
            {
                $key = $_checked->field_id . '_' .$_checked->content_id;
                if (in_array($key, array_keys($contents)))
                {
                    $description = '';
//                    $description_langs = [];
//                    foreach ($all_langs as $lang => $dummy)
//                    {
//                        $description_langs[$lang] = '';
//                    }
                    
                    if ($_checked->value)
                    {
                        $_content_name = $contents[$key]->content_name;
                        if (stripos($_checked->value, 'x') === false && stripos($_checked->value, '/') === false)
                        {
                            $description = round($_checked->value, 3) . " " . $_content_name;
                        }
                        else
                        {
                            $description = explode('/', strtolower($_checked->value));
                            $description = array_map(function($v) {
                                return stripos($v, 'x') === false ? round($v, 3) : $v;
                            }, $description);
                            $description = implode(" / ", $description);
                            
                            $description = explode('x', $description);
                            $description = array_map(function($v) {
                                return stripos($v, '/') === false ? round($v, 3) : $v;
                            }, $description);
                            $description = implode(" x ", $description);
                            
                            $description = $description . " " . $_content_name;
                        }
                        
                        if ($this->_shop->dimensions != 1)
                        {
                            if ($contents[$key]->formula && stripos($contents[$key]->formula, ':=') === 0)
                            {
                                if (stripos($_checked->value, 'x') === false && stripos($_checked->value, '/') === false)
                                {
                                    $contents[$key]->formula = str_ireplace(',', '.', $contents[$key]->formula);
                                    $contents[$key]->formula = str_ireplace('[[value]]', $_checked->value, $contents[$key]->formula);
                                    $contents[$key]->formula = preg_replace('#^:=#iu', '', $contents[$key]->formula);

                                    try
                                    {
                                        $description = @eval("return {$contents[$key]->formula};");
                                        $description = is_numeric($description) ? round($description, 3) : $description;
                                    }
                                    catch (Exception $e) 
                                    {
                                        $description = '';
                                    }
                                }
                                else
                                {
                                    $description = [];
                                    $formula = str_ireplace(',', '.', $contents[$key]->formula);
                                    
                                    $_checked_value = explode('/', strtolower($_checked->value));
                                    foreach ($_checked_value as $_value)
                                    {
                                        $_value = explode('x', $_value);
                                        $_value = array_map(function($v) use ($formula) {
                                            
                                            $_formula = str_ireplace('[[value]]', $v, $formula);
                                            $_formula = preg_replace('#^:=#iu', '', $_formula);

                                            $_description = '';
                                            try
                                            {
                                                $_description = @eval("return {$_formula};");
                                                $_description = is_numeric($_description) ? round($_description, 3) : $_description;
                                            }
                                            catch (Exception $e) 
                                            {
                                                $_description = '';
                                            }

                                            return $_description;
                                        }, $_value);
                                        $description[] = implode(' x ', $_value);
                                    }
                                    
                                    $description = implode(" / ", $description);
                                }
                                
                                if ($contents[$key]->alt_name)
                                {
                                    $description .= " " . $contents[$key]->alt_name;
                                }
                            }
                            else
                            {
                                $description = explode('/', strtolower($_checked->value));
                                $description = array_map(function($v) {
                                    return stripos($v, 'x') === false ? round($v, 3) : $v;
                                }, $description);
                                $description = implode(" / ", $description);

                                $description = explode('x', $description);
                                $description = array_map(function($v) {
                                    return stripos($v, '/') === false ? round($v, 3) : $v;
                                }, $description);
                                $description = implode(" x ", $description);

                                $description = $description . " " . $_content_name;
                            }
                        }
                    }
                    else if ( ! $_checked->value_id)
                    {
                        $langs_keys = [];
                        foreach ($contents[$key]->values as $value)
                        {
                            if ($contents[$key]->contents[$value] && is_array($contents[$key]->contents[$value]))
                            {
                                if ($contents[$key]->contents[$value][$this->_shop->lang])
                                {
                                    $description .= 
                                            "<li>" . $contents[$key]->contents[$value][$this->_shop->lang] . "</li>";
                                }
                                
//                                foreach ($contents[$key]->contents[$value] as $lang => $value)
//                                {
//                                    $langs_keys[$lang] = true;
//                                    if ($value)
//                                    {
//                                        $description_langs[$lang] .= 
//                                                "<li>" . $value . "</li>";
//                                    }
//                                }
                            }
                        }
                        
                        $description = 
                                "<ul class='contentFull'>" . $description . "</ul>";
                        
//                        foreach ($langs_keys as $lang => $dummy)
//                        {
//                            $description_langs[$lang] = 
//                                    "<ul class='contentFull'>" . $description_langs[$lang] . "</ul>";
//                        }
                    }
                    else 
                    {
                        $description = '';
                        if ($contents[$key]->contents[$_checked->value_id] && is_array($contents[$key]->contents[$_checked->value_id]))
                        {
                            $description = 
                                    $contents[$key]->contents[$_checked->value_id][$this->_shop->lang];
                            
//                            foreach ($contents[$key]->contents[$_checked->value_id] as $lang => $value)
//                            {
//                                $description_langs[$lang] = $value;
//                            }
                        }
                    }
                    
//                    $details["field_" . $contents[$key]->field_id] = $description;
                    $description_details[$contents[$key]->sa_field_name][] = $description;
                    $description_details[$contents[$key]->sa_field_name . "_" . $contents[$key]->field_id][] = $description;
                    $description_details["description_" . $contents[$key]->field_id][] = $description;
                    $description_details["description_" . $contents[$key]->field_id . "_" . $contents[$key]->id][] = $description;
                    $description_details["description_" . $contents[$key]->sa_field_name . "_" . $contents[$key]->content_name][] = $description;
                    
                    $fields_titles[$contents[$key]->sa_field_name] = $contents[$key]->field_name;
                    $fields_titles[$contents[$key]->sa_field_name . "_" . $contents[$key]->field_id] = $contents[$key]->field_name;
                    $fields_titles["description_" . $contents[$key]->field_id] = $contents[$key]->field_name;
                    $fields_titles["description_" . $contents[$key]->field_id . "_" . $contents[$key]->id] = $contents[$key]->field_name;
                    $fields_titles["description_" . $contents[$key]->sa_field_name . "_" . $contents[$key]->content_name] = $contents[$key]->field_name;
                    
//                    foreach ($description_langs as $lang => $value)
//                    {
//                        $details["description_" . $contents[$key]->field_id . "_" . $contents[$key]->id . "_" . $lang] = $value;
//                        $details["description_" . $contents[$key]->field_name . "_" . $contents[$key]->content_name . "_" . $lang] = $value;
//                    }
                }
            }
        }
        
        $details = [];
        foreach ($description_details as $field => $description)
        {
            if ( ! isset($details[$field])) 
            {
                if (stripos(implode("\n", $description), "<ul class='contentFull'>") === false)
                {
                    $details[$field] = isset($fields_titles[$field]) ? $fields_titles[$field] . ': ' : '';
                }
                else 
                {
                    $details[$field] = '';
                }
            }
            
            $description = array_values($description);
            for ($i = 0; $i < count($description); ++$i)
            {
                $descr = $description[$i];
                
                //$details[$field] .= $descr . '<br/>';
                $details[$field] .= $descr . ($i < count($description) - 1 ? ', ' : '') . "\n";
            }
        }
        
        return $details;
    }
    
    protected function getOfferDetailsTranslation() 
    {
		$function = "getOfferDetails(TRANSLATION)";
		$chached_ret = cacheGet($function, $this->_shop->id, $this->_shop->lang);
		if ($chached_ret && ! $this->isPreview) {
			return $chached_ret;
		}
        
        $details = $this->_dbr->getAssoc("
            SELECT CONCAT(`translation`.`table_name`, '_', IFNULL(`translation`.`id`, `default`.`id`)), 
                IFNULL(`translation`.`value`, `default`.`value`) 
            FROM `translation`
            LEFT JOIN `translation` AS `default` ON `default`.`table_name` = `translation`.`table_name`
                AND `default`.`id` = `translation`.`id`
            WHERE `translation`.`table_name` = 'translate'
            AND `translation`.`language` = '{$this->_shop->lang}'
            AND `default`.`language` = '{$this->_seller->data->default_lang}'

            UNION

            SELECT CONCAT(`translation`.`table_name`, '_', IFNULL(`translation`.`id`, `default`.`id`)), 
                IFNULL(`translation`.`value`, `default`.`value`) 
            FROM `translation`
            LEFT JOIN `translation` AS `default` ON `default`.`table_name` = `translation`.`table_name`
                AND `default`.`id` = `translation`.`id`
            WHERE `translation`.`table_name` = 'translate_shop'
            AND `translation`.`language` = '{$this->_shop->lang}'
            AND `default`.`language` = '{$this->_seller->data->default_lang}'
            ");
            
        cacheSet($function, $this->_shop->id, $this->_shop->lang, $details);
        
        return $details;
    }
    
    private function getCatsRoutesMultilangs($cat_id, $lang = false) 
    {
        $where = '';
        $limit = '';
        if ($lang)
        {
            $where = " AND `translation`.`language` = '" . mysql_real_escape_string($lang) . "' ";
            $limit = ' LIMIT 1 ';
        }
        
        $query = "
            SELECT `sc`.`id`, `sc`.`parent_id`, `translation`.`language`, `translation`.`value`
            FROM `shop_catalogue` `sc`
            JOIN `shop_catalogue_shop` `scs` ON `scs`.`shop_catalogue_id` = `sc`.`id`
            LEFT JOIN `shop_catalogue_shop` `mscs` ON `mscs`.`id` = `sc`.`master_shop_cat_id`
            LEFT JOIN `shop_catalogue` `msc` ON `mscs`.`shop_catalogue_id` = `msc`.`id`

            JOIN `translation` ON `translation`.`id` = IFNULL(`msc`.`id`, `sc`.`id`)
                AND `translation`.`table_name` = 'shop_catalogue'
                AND `translation`.`field_name` = 'alias'

            WHERE `scs`.`shop_id` = '" . $this->_shop->id . "' 
                AND `sc`.`id` = '" . (int)$cat_id . "'
                AND `translation`.`value` != ''
                $where
                $limit
        ";
        
        if ($lang)
        {
            $cats = $this->_dbr->getRow($query);
            if ($cats->parent_id)
            {
                $cats->parent = $this->getCatsRoutesMultilangs($cats->parent_id, $cats->language);
            }
        }
        else 
        {
            $cats = $this->_dbr->getAll($query);
            foreach ($cats as $key => $value)
            {
                if ($value->parent_id)
                {
                    $cats[$key]->parent = $this->getCatsRoutesMultilangs($value->parent_id, $value->language);
                }
            }
        }
        
        return $cats;
    }
    
    private function createCatsRoutesMultilangs($category) 
    {
        $return = [$category->value];
        if ($category->parent_id)
        {
            $return = array_merge($return, $this->createCatsRoutesMultilangs($category->parent));
        }
        return $return;
    }
    
    public function getLangsUris($section, $id, $langs, $default = '/') 
    {
        switch ($section)
        {
            case 'cat':
                $langs_uris = [];
                foreach ($this->getCatsRoutesMultilangs($id) as $cat)
                {
                    if ($cat->value)
                    {
                        $langs_uris[$cat->language][] = $cat->value;

                        if ($cat->parent_id && $cat->parent)
                        {
                            $langs_uris[$cat->language] = array_merge($langs_uris[$cat->language], $this->createCatsRoutesMultilangs($cat->parent));
                        }
                    }
                }

                foreach ($langs_uris as $language => $cats)
                {
                    $cats = array_reverse($cats);
                    $langs_uris[$language] = '/' . implode('/', $cats) . '/';
                }
                break;
            case 'offer':
                $langs_uris = $this->_dbr->getAssoc("
                    SELECT `translation`.`language`, CONCAT('/', `translation`.`value`, '.html') 
                    FROM `sa" . $this->_shop->id . "` `sa`
    				LEFT JOIN `sa_all` `master_sa` ON `sa`.`master_sa` = `master_sa`.`id`
    				JOIN `translation` ON `translation`.`id` = IF(IFNULL(`sa`.`master_ShopSAAlias`, 1), IFNULL(`master_sa`.`id`, `sa`.`id`), `sa`.`id`)
                    WHERE
                        `translation`.`table_name` = 'sa'
                        AND `translation`.`field_name` = 'ShopSAAlias'
        				AND `translation`.`value` != ''
        				AND `sa`.`id` = '" . (int)$id . "'
                        AND NOT `sa`.`inactive`
                    GROUP BY `translation`.`language`
                        ");
                break;
            case 'look':
                $langs_uris = $this->_dbr->getAssoc("
                    SELECT `translation`.`language`, CONCAT('/shop_looks/', `translation`.`value`, '.html') 
                    FROM `translation`
                    JOIN `shop_looks` ON `shop_looks`.`id` = `translation`.`id`
                    WHERE
                        `translation`.`table_name` = 'shop_looks'
                        AND `translation`.`field_name` = 'Alias'
        				AND `translation`.`id` = '" . (int)$id . "'
        				AND `translation`.`value` != ''
                        AND NOT `shop_looks`.`inactive`
                        ");
                break;
            case 'content':
                $langs_uris = $this->_dbr->getAssoc("
                    SELECT `translation`.`language`, CONCAT('/content/', `translation`.`value`, '/') 
                    FROM `translation`
                    JOIN `shop_content` ON `shop_content`.`id` = `translation`.`id`
                    JOIN `shop_content_shop` ON `shop_content`.`id` = `shop_content_shop`.`shop_content_id`
                    WHERE
                        `translation`.`table_name` = 'shop_content'
                        AND `translation`.`field_name` = 'alias'
        				AND `translation`.`id` = '" . (int)$id . "'
        				AND `translation`.`value` != ''
                        AND NOT `shop_content_shop`.`inactive`
                        AND `shop_content_shop`.`shop_id` = '" . $this->_shop->id . "'
                        ");
                break;
            case 'service':
                
                global $shop_ids;
                $shop_ids[] = $this->_shop->id;
        
                if ($this->_shop->services_shop_id && !in_array($this->_shop->services_shop_id, $shop_ids)) 
                {
                    $shop_id = $this->_shop->services_shop_id;
                } 
                else 
                {
                    $shop_id = $this->_shop->id;
                }
                
                $ids = $this->_dbr->getRow("
                    SELECT 
                    `shop_service`.`id`
                    , `p1`.`id` AS `parent_id`
                    , `p2`.`id` AS `parent_parent_id`

                    FROM `shop_service`
                    LEFT JOIN `shop_service` AS `p1` ON `p1`.`id` = `shop_service`.`parent_id`
                    LEFT JOIN `shop_service` AS `p2` ON `p2`.`id` = `p1`.`parent_id`
                    WHERE 
                        `shop_service`.`shop_id` = $shop_id
                        AND `shop_service`.`id` = '" . (int)$id . "'
                ");
                
                $langs_uris = $this->_dbr->getAssoc("
                    SELECT `translation`.`language`, 
                    
                        IF (IFNULL(`p2`.`value`, '') != '' AND IFNULL(`p2`.`id`, 0) != 0, 
                            CONCAT('/service/', `p2`.`value`, '/', `p1`.`value`, '/', `translation`.`value`, '/') , 
                            IF (IFNULL(`p1`.`value`, '') != '' AND IFNULL(`p1`.`id`, 0) != 0, 
                                CONCAT('/service/', `p1`.`value`, '/', `translation`.`value`, '/') , 
                                CONCAT('/service/', `translation`.`value`, '/')
                            )
                        )

                    FROM `translation`

                    LEFT JOIN `translation` `p1` ON 
                        `p1`.`table_name` = 'shop_service'
                        AND `p1`.`field_name` = 'alias'
                        AND `p1`.`id` = '" . (int)$ids->parent_id . "'
                        AND `p1`.`value` != ''
                        AND `p1`.`language` = `translation`.`language`

                    LEFT JOIN `translation` `p2` ON 
                        `p2`.`table_name` = 'shop_service'
                        AND `p2`.`field_name` = 'alias'
                        AND `p2`.`id` = '" . (int)$ids->parent_parent_id . "'
                        AND `p2`.`value` != ''
                        AND `p2`.`language` = `translation`.`language`

                    WHERE
                        `translation`.`table_name` = 'shop_service'
                        AND `translation`.`field_name` = 'alias'
                        AND `translation`.`id` = '" . (int)$ids->id . "'
                        AND `translation`.`value` != ''

                        AND IFNULL(`p2`.`id`, 0) = " . (int)$ids->parent_parent_id . "
                        AND IFNULL(`p1`.`id`, 0) = " . (int)$ids->parent_id . "

                        ");
                break;
            case 'news':
                $langs_uris = $this->_dbr->getAssoc("
                    SELECT `translation`.`language`, CONCAT('/news/', `translation`.`value`, '/') 
                    FROM `translation`
                    JOIN `shop_news` ON `shop_news`.`id` = `translation`.`id`
                    WHERE
                        `translation`.`table_name` = 'shop_news'
                        AND `translation`.`field_name` = 'alias'
        				AND `translation`.`id` = '" . (int)$id . "'
        				AND `translation`.`value` != ''
                        AND `shop_news`.`shop_id` = '" . $this->_shop->id . "'
                        ");
                break;
            default:
                if ($default == '/')
                {
                    $langs_codes = array_keys($langs);
                    $langs_codes = array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, $langs_codes);
                    
                    $langs_uris = $this->_dbr->getAssoc("
                            SELECT `value`, CONCAT('/', LOWER(`code`))
                            FROM `config_api_values`
                            WHERE 
                                `par_id` = 6 
                                AND NOT `inactive`
                                AND `value` IN (" . implode(',', $langs_codes) . ")
                            ");
                }
                else 
                {
                    $langs_uris = array_combine(array_keys($langs), array_fill(0, count($langs), $default));
                }
                break;
        }
        
        $langs_uris = array_intersect_key($langs_uris, $langs);
        
        $langs_uris_temp = $langs_uris;
        foreach ($langs_uris as $lang => $url)
        {
            foreach ($langs_uris_temp as $lang1 => $url1)
            {
                if ($lang == $lang1)
                {
                    continue;
                }
                
                if ($url == $url1)
                {
                    $langs_uris[$lang] = '/lang/' . $lang . $url;
                    break;
                }
            }
        }

        return $langs_uris;
    }
}
