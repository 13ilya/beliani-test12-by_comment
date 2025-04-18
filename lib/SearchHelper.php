<?php

/**
 * Class SearchHelper
 * Used for work with user search requests.
 */
class SearchHelper
{
	private $_keyword;
	private $_lang;
	private $_shopCatalogue;
	private $_offers = array();
	private $_similar_words;
	private $_categories = array();
	private $_5_offers = array();

	/**
	 * SearchHelper constructor.
	 * @param string $keyword
	 * @param string $lang
	 * @param Shop_Catalogue $shopCatalogue
	 */
	public function __construct($keyword, $lang, Shop_Catalogue $shopCatalogue)
	{
		$this->_keyword = $keyword;
		$this->_lang = $lang;
		$this->_shopCatalogue = $shopCatalogue;
	}

	/**
	 * Initiate search request
	 * @return array with next elements:
	 * -offers - array of similar offers
	 * -similar - array of similar search requests
	 * -categories - array of similar categories
	 */
	public function getResult()
	{
		$function = "SearchHelper::getResult({$this->_keyword})";
		$chached_result = cacheGet(
			$function, 
			$this->_shopCatalogue->_shop->id, 
			$this->_shopCatalogue->_shop->lang
		);

		if ($chached_result) {
            return $chached_result;
        }
        
        $this->_findSimilarWords();
        $this->_findOffers();
        $this->_fillCategories();
        $this->_filterOffers();

        $result = array(
            'offers' => $this->_5_offers,
            'similar' => $this->_similar_words,
            'categories' => array_slice($this->_categories, 0, 6)
        );

        cacheSet(
            $function, 
            $this->_shopCatalogue->_shop->id, 
            $this->_shopCatalogue->_shop->lang, 
            $result
        );
		
		return $result;
	}

	private function _findOffers()
	{
		$this->_offers = $this->_shopCatalogue->searchOffersLite(mysql_real_escape_string($this->_keyword));
	}

	/**
	 * Search similar search requests
	 * Result recorded in $this->_similar_words
	 */
	private function _findSimilarWords()
	{
		$sphinx = \label\Sphinx::getInstance();
		$this->_similar_words = $sphinx->findSimilarSearchRequests($this->_keyword, $this->_lang);
	}

	private function _fillCategories()
	{
		foreach ($this->_offers as $offer) {
			if (isset($this->_categories[$offer->cat_route])) {
				$this->_categories[$offer->cat_route]['count']++;
			} else {
				$this->_categories[$offer->cat_route] = array(
					'title' => $offer->cat_name,
					'url' => $offer->cat_route,
					'count' => 1
				);
			}
		}

		usort($this->_categories, function($a, $b) {
			if ($a['count'] == $b['count']) return 0;
			return ($a['count'] < $b['count']) ? 1 : -1;
		});
	}

	private function _filterOffers()
	{
		$ids = array();
		foreach ($this->_offers as $offer) {
			if (count($this->_5_offers) == 5) {
				break;
			}

			if (!in_array((int)$offer->sa_id, $ids) && $offer->doc_id) {
				$this->_5_offers[] = $offer;
				$ids[] = (int)$offer->sa_id;
			}
		}
	}
}