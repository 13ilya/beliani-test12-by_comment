<?php
namespace label\Spider;

use label\DB;
use label\RedisProvider;

/**
 * Class SpiderBuildCommand
 * Command used to create list of all products/categories pages and cookies
 */
class SpiderBuild
{

    /**
     * @var bool
     */
    private $mobileOnly = false;

    /**
     * @var bool
     */
    private $desktopOnly = false;

    /**
     * @var bool
     */
    private $reportOnly;

    /**
     * @var mixed[]
     */
    private $queue = [];

    /**
     * @var \Shop_Catalogue
     */
    private $shopCatalogue;

    /**
     * @var int
     */
    private $countJobs = 0;
    
    /**
     * @var string[][] $fluentCookiesSet array of fluent cookies
     */
    private $fluentCookiesSet = [];

    /**
     * @param string $schema
     * @throws BadSchemaException
     */
    public function __construct($shop_id = 0)
    {
        if ($shop_id) {
            $this->setShopId($shop_id);
        }
        
        $this->fluentCookiesSet = [['off_mobile' => '0'], ['off_mobile' => '1']];
        
        \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));
    }
    
    /**
     * @param int $shop_id
     */
    public function setShopId($shop_id)
    {
        $dbr = DB::getInstance(DB::USAGE_READ);
        $db = DB::getInstance(DB::USAGE_WRITE);
        
        $shopCatalogue = new \Shop_Catalogue($db, $dbr, $shop_id);
        $shopCatalogue->_shop->lang = $shopCatalogue->_seller->data->default_lang;
        $this->shopCatalogue = $shopCatalogue;
    }
    
    /**
     * Push Ids to queue
     * @param mixed $saved_id
     */
    public function pushIdsQueue($saved_id) 
    {
        $db = DB::getInstance(DB::USAGE_WRITE);
        $dbr = DB::getInstance(DB::USAGE_READ);
        
        if ( !is_array($saved_id)) {
            $saved_id = [$saved_id];
        }
        $saved_id = array_values(array_unique($saved_id));

        $clearQueue = [];
        foreach ($saved_id as $sa_id) {
            $details = \Saved::getDetails($sa_id);
            $shop_id = $dbr->getOne("SELECT `id` FROM `shop` 
                    WHERE `username` = ? AND NOT inactive LIMIT 1", null, [$details['username']]);
            
            if ( ! $shop_id) {
                continue;
            }
            
            $this->setShopId($shop_id);

            $pages = $dbr->getAssoc("SELECT `value`, `id` FROM `translation` 
                    WHERE `table_name` = 'sa' AND `field_name` = 'ShopSAAlias' AND `id` = ?", null, [$sa_id]);

            foreach ($pages as $_url => $dummy) {
                if ($_url) {
                    $response[$sa_id] = $this->pushQueue("/{$_url}.html");
                }
            }
            
            $clearQueue[] = "getOffer($sa_id)";
        }
        
        $recache = new \label\RedisCache\RedisBuild();
        $recache->pushClearQueue($clearQueue);
        
        return $response;
    }

    /**
     * Push to queue
     * @param string $url url without domain with leading slash
     * @param string $lang
     * @return string
     */
    public function pushQueue($url, $lang = '')
    {
        $dbr = DB::getInstance(DB::USAGE_READ);
        
        if ($this->shopCatalogue->_shop->ssl) {
            $schema = 'https';
        } else {
            $schema = 'http';
        }

        if ( ! $lang) 
        {
            $langs = $dbr->getAssoc("SELECT `lang`, `lang` `v`
                FROM `seller_lang`
                WHERE `username` = '" . mysql_real_escape_string($this->shopCatalogue->_shop->username) . "'
                    AND `useit` = 1");

        }
        else if ( ! is_array($lang))
        {
            $langs = [$lang];            
        }

        $domain = 'www.' . $this->shopCatalogue->_shop->url;

        $jobs = [];

        $cookies = [];
        if ($this->desktopOnly xor $this->mobileOnly) {
            $cookies = ['off_mobile' => $this->desktopOnly ? '1' : '0'];
        }

//        $jobs[] = [
//            'action' => 'clear',
//            'schema' => $schema,
//            'domain' => $domain,
//            'url' => $url,
//            'cookies' => $cookies,
//        ];

        foreach ($langs as $lang)
        {
            $constCookiesSet[] = [
                'shop_lang' => $lang,
                'skin' => 'autumn',
                'currency_code' => '',
            ];
        }
        
        $constCookiesSet[] = [
            'shop_lang' => '',
            'skin' => 'autumn',
            'currency_code' => '',
        ];
        foreach ($this->fluentCookiesSet as $cookiesSet) {
            foreach ($constCookiesSet as $constCookies) {
                $cookies = array_merge($constCookies, $cookiesSet);
                $jobs[] = [
                    'action' => 'crawl',
                    'schema' => $schema,
                    'domain' => $domain,
                    'url' => $url,
                    'cookies' => $cookies,
                ];
            }
        }
        
        $keyQueue = md5(serialize($jobs));
        if (!isset($this->queue[$keyQueue])) {
            $this->queue[$keyQueue] = true;
            $identifier = str_pad(++$this->countJobs, 5, '0', STR_PAD_LEFT);
            if ($this->reportOnly) {
                $token = 'REPORT-ONLY';
            } else {
                \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));
                $token = \Resque::enqueue('spider', '\label\Spider\SpiderJob', $jobs);
            }
            $message =
                '[' . $identifier . ']cookies-set:' . json_encode($this->fluentCookiesSet)
                . '|schema:' . $schema . '|domain:' . $domain . '|url:' . $url . '|token:' . $token;
        } else {
            $message = '[00000]duplicate';
        }

        return $message;
    }
}