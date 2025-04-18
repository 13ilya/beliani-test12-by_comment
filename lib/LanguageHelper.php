<?php
class LanguageHelper {
	public static $matches = array(
		'en' => 'english',
		'nl' => 'dutch',
		'fr' => 'french',
		'de' => 'german',
		'hu' => 'Hungarian',
		'it' => 'italian',
		'pl' => 'polish',
		'pt' => 'portugal',
		'es' => 'spanish',
		'sv' => 'swedish'
	);
	
	private $_accept_languages;
	private $_shop_catalogue;
	private $_customer;
	private $_seller_langs;
		
	public function __construct($shopCatalogue, $customer = null, $seller_langs)
    {
		$this->_shop_catalogue = $shopCatalogue;
		$this->_customer = $customer;
		$this->_seller_langs = $seller_langs;
    }
    /**
     * Define language based on 'Accept Language' header and seller languages
     * @param array $seller_langs
     * @return string|bool
     */
    public static function defineLanguageFromBrowser($seller_langs) {
		if (($list = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']))) {
			if (preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/', $list, $list)) {
				$browser_languages = array_combine($list[1], $list[2]);
                foreach ($browser_languages as $n => $v) {
                    $browser_languages[$n] = $v ? $v : 1;
                }
                arsort($browser_languages, SORT_NUMERIC);
            }
        } else {			
			$browser_languages = [];
		}    
    
        foreach ($browser_languages as $lang => $priority) {
            $code = substr($lang, 0, 2);
            if (isset(LanguageHelper::$matches[$code]) && in_array(LanguageHelper::$matches[$code], $seller_langs)) {
                return LanguageHelper::$matches[$code];
            }
        }
        return false;
    }
	
	public function defineLanguage()
	{ 
		// from cookies
		if (!empty($_COOKIE["shop_lang"]))
		{
			$deflang = $_COOKIE["shop_lang"];
		}
		// from customer
		elseif ($this->_customer)
		{
			$deflang = $this->_customer->lang;
		}
		// default seller
		elseif ($this->_shop_catalogue->_seller->get('use_default_lang'))
		{
			$deflang = $this->_shop_catalogue->_seller->get('default_lang');
		}
		// from item
		elseif ($this->_shop_catalogue->_item_language || $this->_shop_catalogue->_cat_language)
		{
			$deflang = $this->_shop_catalogue->_item_language ? $this->_shop_catalogue->_item_language : $this->_shop_catalogue->_cat_language;
		}
		else
		{
			$deflang = self::defineLanguageFromBrowser($this->_seller_langs);
			
			if ($deflang === false)
			{
				$deflang = $this->_shop_catalogue->_shop->sellerInfo_default_lang;
			}
		}
		return $deflang;
	}
	
	public function getLanguageShort($lang, $base64 = false)
	{ 
		switch ($lang) {
			case 'polish':
				$lang = 'pl';
				break;
			case 'english': 
				$lang = 'en';
				break;
			case 'german': 
				$lang = 'de';
				break;
			case 'spanish': 
				$lang = 'es';
				break;
			case 'french': 
				$lang = 'fr';
				break;
			case 'italian': 
				$lang = 'it';
				break;
			case 'portugal': 
				$lang = 'pt';
				break;
			case 'hungarian': 
				$lang = 'hu';
				break;
			case 'dutch': 
				$lang = 'nl';
				break;
			case 'swedish': 
				$lang = 'sv';
				break;		
			default:
				$lang = 'en';
				break;
		}	
		
		if($base64) $lang = str_replace(array('=','+','/'),array('_','-',','),base64_encode($lang));
		return $lang;
	}
}
?>