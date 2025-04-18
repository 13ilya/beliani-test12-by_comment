<?php

namespace label\Sitemap;

use label\DB;
use label\Sitemap\Item\SitemapImage;
use label\Sitemap\Item\SitemapVideo;

require_once LIB_DIR.'/ShopCatalogue.php';

/**
 * Class Generator
 * Used to collect data for sitemap and generate sitemap file.
 */
class Generator
{
    /**
     * Limit 50Mb (1024*1024*50=52428800) for one file
     */
    const GOOGLE_FILESIZE_LIMIT = 52428800;

    /**
     * Shop identifier
     * @var int
     */
    private $shopId;

    /**
     * Instance that store all urls
     * @var SitemapData
     */
    private $data;

    /**
     * Instance to get all shop data
     * @var \Shop_Catalogue
     */
    private $shopCatalogue;

    /**
     * Plug to store last modified data
     * @var string DD-MM-YYYY
     * @todo use real date instead
     */
    private $lastModified;

    /**
     * List of lanuages enabled in shop
     * @var array
     */
    private $availableLanguages;

    private $xmlContent;
    private $xmlImagesArray = [];
    private $xmlVideos;
    private $xmlIndex;
    private $fullDomain;

    /**
     * Is enabled report about special chars used in urls
     * @var bool
     */
    private $specialCharsReportEnabled = false;

    /**
     * Storage for unappropriates urls
     * @var string[]
     */
    private $specialCharsReportInfo = [];

    /**
     * Generator constructor.
     * @param int $shopId shop id
     */
    public function __construct($shopId)
    {
        $this->shopId = $shopId;

        $this->shopCatalogue = new \Shop_Catalogue(DB::getInstance(DB::USAGE_WRITE), DB::getInstance(DB::USAGE_READ), $this->shopId);

        $this->lastModified = date('Y-m-d');

        /**
         * Resorting languages
         * Set default language first
         */
        $this->availableLanguages = $this->shopCatalogue->getSellerLanguages();
        if (!is_array($this->availableLanguages)) {
            $this->availableLanguages = [];
        }
        unset($this->availableLanguages[$this->shopCatalogue->_seller->data->default_lang]);
        $this->availableLanguages = array_keys(array_merge([$this->shopCatalogue->_seller->data->default_lang => null], $this->availableLanguages));

        if ($this->shopCatalogue->_shop->ssl) {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }
        $this->fullDomain = $protocol.'://www.'.$this->shopCatalogue->_shop->url;

        $this->data = new SitemapData($this->fullDomain);
    }

    /**
     * Collect data
     */
    public function run()
    {
        $this->generateCatalog();
        $this->generateNews();
        $this->generateContent();
        $this->generateServices();
        $this->generateShopLooks();
    }

    /**
     * Write generated data to files according to site
     * @return void
     */
    public function writeInFile()
    {
        $this->makeXml();
        $hostname = 'www.'.$this->shopCatalogue->_shop->url;
        if (!is_dir(TMP_DIR.'/sitemap/'.$hostname)) {
            if (!mkdir(TMP_DIR.'/sitemap/'.$hostname)) {
                throw new \Exception('Can not create temp dir for sitemap files');
            }
        }

        $filename = TMP_DIR.'/sitemap/' . $hostname . '/index.xml';
        $filesize = file_put_contents($filename, $this->xmlIndex);
        if (($filesize === false) || ($filesize > self::GOOGLE_FILESIZE_LIMIT)) {
            $errorFile = $filename;
        }

        $filename = TMP_DIR.'/sitemap/' . $hostname . '/content.xml';
        $filesize = file_put_contents($filename, $this->xmlContent);
        if (($filesize === false) || ($filesize > self::GOOGLE_FILESIZE_LIMIT)) {
            $errorFile = $filename;
        }

        foreach (glob(TMP_DIR . '/sitemap/' . $hostname . '/images_*.xml') as $previousFile) {
            unlink($previousFile);
        }
        foreach ($this->xmlImagesArray as $key => $xmlImages) {
            $key++;
            $filename = TMP_DIR . '/sitemap/' . $hostname . '/images_' . $key . '.xml';
            $filesize = file_put_contents($filename, $xmlImages);
            if (($filesize === false) || ($filesize > self::GOOGLE_FILESIZE_LIMIT)) {
                $errorFile = $filename;
            }
        }

        if (isset($this->xmlVideos)) {
            $filesize = file_put_contents(TMP_DIR.'/sitemap/' . $hostname . '/videos.xml', $this->xmlVideos);
            if (($filesize === false) || ($filesize > self::GOOGLE_FILESIZE_LIMIT)) {
                $errorFile = $filename;
            }
        }

        if (isset($errorFile)) {
            throw new \Exception('Error in sitemap file (it can be too big or can not write): '.$errorFile);
        }
    }

    /**
     * Returns list of ununique urls with location data (language code and location id)
     * @return array
     */
    public function getUnuniqueUrls()
    {
        return $this->data->getUnuniqueUrls();
    }

    /**
     * @return string
     */
    public function getFullDomain()
    {
        return $this->fullDomain;
    }

    /**
     * Enable collecting info about unappropriate characters in url
     */
    public function enableSpecialCharsReport()
    {
        $this->specialCharsReportEnabled = true;
    }

    public function getSpecialCharsReport()
    {
        return $this->specialCharsReportInfo;
    }

    /**
     * Generate urls for services
     */
    private function generateServices()
    {
        $shopCatalogue = clone $this->shopCatalogue;
        foreach ($this->availableLanguages as $language) {
            $shopCatalogue->_shop->lang = $language;

            foreach ($shopCatalogue->listServices(1) as $service) {
                if (!empty($service->alias)) {
                    $url = '/service_id/'.$service->alias.'/';
                    $idLocation = 'service_' . $service->id;
                    $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                } else {
                    //@todo report
                }
            }
        }
    }

    /**
     * Generate urls for news
     */
    private function generateNews()
    {
        $shopCatalogue = clone $this->shopCatalogue;
        foreach ($this->availableLanguages as $language) {
            $shopCatalogue->_shop->lang = $language;

            foreach($shopCatalogue->listNews() as $singleNews) {
                if (!empty($singleNews->alias)) {
                    $url = '/news/' . $singleNews->alias . '/';
                    $idLocation = 'news_' . $singleNews->id;
                    $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                } else {
                    //@todo report
                }
            }
        }
    }

    /**
     * Generate urls for content
     */
    private function generateContent()
    {
        $shopCatalogue = clone $this->shopCatalogue;
        foreach ($this->availableLanguages as $language) {
            $shopCatalogue->_shop->lang = $language;

            foreach($shopCatalogue->listContent() as $singleContent) {
                if (empty($singleContent->inactive)) {
                    if (!empty($singleContent->alias)) {
                        if ($singleContent->alias !== '404') {
                            $url = '/content/' . $singleContent->alias . '/';
                            $idLocation = 'content_' . $singleContent->id;
                            $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                        }
                    } else {
                        //@todo report
                    }
                }
            }
        }
    }

    /**
     * Generate urls for shop looks
     */
    private function generateShopLooks()
    {
        $shopCatalogue = clone $this->shopCatalogue;
        foreach ($this->availableLanguages as $language) {
            $shopCatalogue->_shop->lang = $language;

            foreach($shopCatalogue->getLooks() as $singleLook) {
                if ($singleLook['Alias']) {
                    $url = '/shop_looks/' . $singleLook['Alias'] . '.html';
                    $idLocation = 'shop_looks_' . $singleLook['id'];
                    $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                } else {
                    //@todo report
                }
            }
        }
    }

    /**
     * Generate all catalog categories urls and products urls.
     */
    private function generateCatalog()
    {
        $shopCatalogue = clone $this->shopCatalogue;
        foreach ($this->availableLanguages as $language) {
            $shopCatalogue->_shop->lang = $language;

            foreach ($shopCatalogue->listAll(0, 0) as $cat) {
                $url = '/' . urlencode($cat->alias) . '/';
                $this->specialCharsReportCheckAndAdd($url, '/' . $cat->alias . '/');
                $idLocation = 'catalogue_' . $cat->id;
                $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                foreach ($cat->children as $cat1) {
                    $url = '/' . urlencode($cat->alias) . '/' . urlencode($cat1->alias) . '/';
                    $this->specialCharsReportCheckAndAdd($url, '/' . $cat->alias . '/' . $cat1->alias . '/');
                    $idLocation = 'catalogue_' . $cat1->id;
                    $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                    foreach ($cat1->children as $cat2) {
                        $url = '/' . urlencode($cat->alias) . '/' . urlencode($cat1->alias) . '/' . urlencode($cat2->alias) . '/';
                        $this->specialCharsReportCheckAndAdd($url, '/' . $cat->alias . '/' . $cat1->alias . '/' . $cat2->alias . '/');
                        $idLocation = 'catalogue_' . $cat2->id;
                        $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                        $offers = $shopCatalogue->getOffers($cat2->id);
                        foreach ($offers as $offer) {
                            $url = $offer->cat_route . urlencode($offer->ShopSAAlias) . '.html';
                            $this->specialCharsReportCheckAndAdd($url, $offer->cat_route . $offer->ShopSAAlias . '.html');
                            $idLocation = 'offer_' . $offer->id;
                            $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                            $this->generateMediaFiles($offer, $language);
                        }
                    }
                    $offers = $shopCatalogue->getOffers($cat1->id);
                    foreach ($offers as $offer) {
                        $url = $offer->cat_route . urlencode($offer->ShopSAAlias) . '.html';
                        $this->specialCharsReportCheckAndAdd($url, $offer->cat_route . $offer->ShopSAAlias . '.html');
                        $idLocation = 'offer_' . $offer->id;
                        $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                        $this->generateMediaFiles($offer, $language);
                    }
                }
                $offers = $shopCatalogue->getOffers($cat->id);
                foreach ($offers as $offer) {
                    $url = $offer->cat_route . urlencode($offer->ShopSAAlias) . '.html';
                    $this->specialCharsReportCheckAndAdd($url, $offer->cat_route . $offer->ShopSAAlias . '.html');
                    $idLocation = 'offer_' . $offer->id;
                    $this->data->addPage($idLocation, $url, $shopCatalogue->getShopLanguageCode());
                    $this->generateMediaFiles($offer, $language);
                }
            }
        }
    }

    /**
     * Generate images and videos according to passed offer
     * @param \stdClass $offer
     * @param string $language
     */
    private function generateMediaFiles($offer, $language)
    {
        $offerId = $offer->id;
        if (!$this->data->issetOfferMedia($offerId, \Shop_Catalogue::getLanguageCode($language))) {
            $docs = \Saved::getDocs(
                DB::getInstance(DB::USAGE_WRITE),
                DB::getInstance(DB::USAGE_READ),
                $offer->master_pics ? $offer->master_sa : $offer->orig_id,
                ' AND inactive=0 ',
                $language);
            foreach ($docs as $doc) {
                if (!empty($doc->youtube_code)) {
                    $this->data->addOfferVideo(
                        $offerId,
                        \Shop_Catalogue::getLanguageCode($language),
                        new SitemapVideo(
                            'https://www.youtube.com/watch?v=' . $doc->youtube_code,
                            $doc->title,
                            self::getOfferDescription($offerId, $language),
                            $this->getOfferCategoryName($offerId, $language),
                            $this->getOfferTags($offer->master_amazon ? $offer->master_sa : $offerId, $language)
                        )
                    );
                } else {
                    $this->data->addOfferImage(
                        $offerId,
                        \Shop_Catalogue::getLanguageCode($language),
                        new SitemapImage(
                            '/images/cache/' . $language . '_src_saved_picid_' . $doc->doc_id . '.' . $doc->ext . (!empty($doc->version) ? ('?ver=' . $doc->version) : ''),
                            !empty(trim($doc->alt)) ? $doc->alt : $this->getOfferName($offerId, $language),
                            !empty(trim($doc->title)) ? $doc->title : $this->getOfferName($offerId, $language)
                        )
                    );
                }
            }
        }
    }

    /**
     * Process all urls into valid sitemap xml file.
     * @return void
     * @todo maybe make pages urls without alternate lang if only one language presented
     */
    private function makeXml()
    {
        $pages = new \Smarty();
        $pages->assign('urlSet', $this->data->getPageLocations());
        $pages->assign('lastModified', $this->lastModified);
        $pages->assign('countryCode', strtolower($this->shopCatalogue->getCountryCode()));
        $this->xmlContent = $pages->fetch('sitemap/pages.tpl');

        $imagesSitemapCount = 0;
        if ($this->data->areImagesPresented()) {
            $images = new \Smarty();
            foreach ($this->data->getImageLocations() as $locations) {
                $imagesSitemapCount++;
                $images->assign('imageLocations', $locations);
                $this->xmlImagesArray[] = $images->fetch('sitemap/images.tpl');
            }
        }

        $videosSitemap = false;
        if (count($this->data->getVideoLocations())) {
            $videosSitemap = true;

            $videos = new \Smarty();
            $videos->assign('videoLocations', $this->data->getVideoLocations());
            $this->xmlVideos = $videos->fetch('sitemap/videos.tpl');
        }

        $index = new \Smarty();
        $index->assign('imagesSitemapCount', $imagesSitemapCount);
        $index->assign('videosSitemap', $videosSitemap);
        $index->assign('domain', $this->fullDomain);
        $index->assign('lastModified', $this->lastModified);
        $this->xmlIndex = $index->fetch('sitemap/index.tpl');
    }

    /**
     * Get category name for passed offer.
     * It include every category that has that offer and every parent for that categories.
     * All categories splitted into one string.
     * @param int $offerId
     * @param string $language
     * @return string
     * @todo find 'better' category
     */
    private function getOfferCategoryName($offerId, $language)
    {
        static $cache = [];
        if (!isset($cache[$this->shopCatalogue->_shop->id][$offerId])) {
            $dbr = DB::getInstance(DB::USAGE_READ);
            $query = '
                SELECT sa.shop_catalogue_id
                FROM sa'.$this->shopCatalogue->_shop->id.' sa
                WHERE sa.id = ?';
            $categories = $dbr->getAll($query, null, [$offerId]);
            $categoriesIds = [];
            foreach ($categories as $category) {
                $categoriesIds[$category->shop_catalogue_id] = true;
                $nodes = $this->shopCatalogue->getAllNodes($category->shop_catalogue_id);
                foreach ($nodes as $node) {
                    $categoriesIds[$node] = true;
                }
            }
            unset($categoriesIds[0]);

            $result = [];
            foreach ($categoriesIds as $categoryId => $unused) {
                foreach (self::getCategoryName($categoryId) as $lang => $name) {
                    $result[$lang][] = $name;
                }
            }
            foreach ($result as $lang => $nameSet) {
                $cache[$this->shopCatalogue->_shop->id][$offerId][$lang] = implode(', ', $nameSet);
            }
        }
        return $cache[$this->shopCatalogue->_shop->id][$offerId][$language];
    }

    /**
     * Get offer tags
     * @param int $offerId
     * @param string $language
     * @return string[]
     */
    private function getOfferTags($offerId, $language)
    {
        static $cache = [];
        if (!isset($cache[$offerId])) {
            foreach($this->getOfferName($offerId) as $lang => $title) {
                $cache[$offerId][$lang][] = $title;
            }

            $db = DB::getInstance(DB::USAGE_READ);
            $querySearchTerms = '
                SELECT language, value
                FROM translation
                WHERE
                    id = ?
                    AND field_name LIKE \'amazon_st_%\'
                    AND value != \'\'
                ORDER BY field_name';
            $terms = $db->getAll($querySearchTerms, null, [$offerId]);
            foreach($terms as $term) {
                $cache[$offerId][$term->language][] = $term->value;
            }
        }
        return implode(', ', $cache[$offerId][$language]);
    }

    /**
     * Return offer title (name)
     * @param int $offerId
     * @param string|null $language
     * @return string[]|array[]
     */
    private function getOfferName($offerId, $language = null)
    {
        static $cache = [];
        if (!isset($cache[$this->shopCatalogue->_shop->id][$offerId])) {
            $db = DB::getInstance(DB::USAGE_READ);

            $queryTitle = '
                SELECT translation.language, offer_name.name
                FROM translation
                    LEFT JOIN offer_name ON
                        translation.value = offer_name.id
                WHERE
                    table_name = \'sa\'
                    AND translation.id = ?
                    AND translation.field_name = \'ShopDesription\'
                    AND translation.value != \'\'';
            $titles = $db->getAll($queryTitle, null, [$offerId]);
            foreach ($titles as $title) {
                $cache[$this->shopCatalogue->_shop->id][$offerId][$title->language] = $title->name;
            }
        }
        if (isset($language)) {
            return $cache[$this->shopCatalogue->_shop->id][$offerId][$language];
        }
        return $cache[$this->shopCatalogue->_shop->id][$offerId];
    }

    /**
     * Return category names array(all languages) for passed category id.
     * @param int $categoryId
     * @return string[]
     */
    private static function getCategoryName($categoryId)
    {
        static $cache = [];
        if (!isset($cache[$categoryId])) {
            $dbr = DB::getInstance(DB::USAGE_READ);
            $query = '
                SELECT language, value
                FROM translation
                WHERE table_name = \'shop_catalogue\'
                    AND field_name = \'name\'
                    AND id = ?
            ';
            $categoriesNames = $dbr->getAll($query, null, [$categoryId]);
            foreach($categoriesNames as $categoryName) {
                $cache[$categoryId][$categoryName->language] = $categoryName->value;
            }
        }
        return $cache[$categoryId];
    }

    /**
     * Get offer main description
     * @param int $offerId
     * @param string $language
     * @return string
     */
    private static function getOfferDescription($offerId, $language)
    {
        static $cache = [];
        if (!isset($cache[$offerId])) {
            $db = DB::getInstance(DB::USAGE_READ);
            $query = '
                SELECT language, value
                FROM translation
                WHERE 
                    table_name = \'sa\'
                    AND field_name = \'descriptionTextShop1\'
                    AND id = ?';
            $descriptions = $db->getAll($query, null, [$offerId]);
            foreach($descriptions as $description) {
                $cache[$offerId][$description->language] = strip_tags($description->value);
            }
        }
        return $cache[$offerId][$language];
    }

    /**
     * Check and write in report if urls are not match
     * @param string $rawUrl
     * @param string $encodedUrl
     * @return void
     */
    private function specialCharsReportCheckAndAdd($encodedUrl, $rawUrl)
    {
        if ($this->specialCharsReportEnabled) {
            if ($rawUrl !== $encodedUrl) {
                $this->specialCharsReportInfo[] = $rawUrl.':'.$encodedUrl;
            }
        }

    }
}