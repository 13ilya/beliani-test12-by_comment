<?php

namespace label;

/**
 * Class LiveNotification
 * Used to work with notifications about alst viewed products on shops
 * It uses sockets for live notifications and storing into db that notifications.
 * Db used to get last viewd pages on page startup.
 * @todo use redis instead of mysql
 * @todo make same class for new arrivals, imho make interface and implement both classes 
 */
class LiveNotification
{
    private $id;
    private $site;
    private $PHPSESSID;
    private $page;
    private $userIp;
    private $userCity;
    private $userCountry;
    private $userRegion;
    private $tdif;
    private $altText;
    private $availableText;
    private $shopDescriptionText;
    private $shopDescriptionArray;
    private $imageNew;
    private $imageId;
    private $imageWDoc;
    private $imageCDoc;
    private $imageCDN;
    private $link;
    private $channel;
    private $swap_id;
    private $priсe;
    private $price_old;
    private $discount;
    private $rating;
    private $image_size;
    private $banner;
    private $offer_available;
    private $date_available;
    private $stock_color;
    private $available_weeks;

    /**
     * @return int product id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string from when it was
     */
    public function getTimeDiff()
    {
        return $this->tdif;
    }

    /**
     * @return string user city based on the geo ip
     */
    public function getUserCity()
    {
        return $this->userCity;
    }

    /**
     * @return string user country code based on geo ip
     */
    public function getUserCountry()
    {
        return $this->userCountry;
    }

    /**
     * @return string user region based on geo ip
     */
    public function getUserRegion()
    {
        return $this->userRegion;
    }
    
    
    /**
     * @param string $param
     */
    public function setAvailableWeeks($param)
    {
        $this->available_weeks = mysql_real_escape_string($param);
    }

    /**
     * @param array $param
     */
    public function setStockColor($param)
    {
        $this->stock_color = $param;
    }

    /**
     * @param string $date
     */
    public function setDateAvailable($date)
    {
        $this->date_available = mysql_real_escape_string($date);
    }

    /**
     * @param array of objects $param
     */
    public function setOfferAvailable($param)
    {
        $this->offer_available = $param;
    }

    /**
     * @param string $channel
     */
    public function setChannel($channel)
    {
        $this->channel = mysql_real_escape_string($channel);
    }
    
    /**
     * @param integer $rating
     */
    public function setRating($rating)
    {
        $this->rating = $rating;
    }

    /**
     * @param float $price
     */
    public function setPrice($price)
    {
        $this->priсe = (float)$price;
    }
    
    /**
     * @param float $price
     */
    public function setPriceOld($price)
    {
        $this->price_old = (float)$price;
    }
    
    /**
     * @param integer $discount
     */
    public function setDiscount($discount)
    {
        $this->discount = (int)$discount;
    }
    
    /**
     * @param array $banner
     */
    public function setBanner($banner)
    {
        $this->banner = $banner;
    }
    
    /**
     * @desc set auction id that should be swap to last new
     * @param integer $id 
     */
    public function setSwapId($id)
    {
        $this->swap_id = (int)$id;
    }
    
    /**
     * @desc set image size
     * @param integer $size 
     */
    public function setImageSize($size)
    {
        $this->image_size = (int)$size;
    }
    
    /**
     * @param int $id product id
     */
    public function setId($id)
    {
        $this->id = (int)$id;
    }

    /**
     * @param string $site sitename where it was
     */
    public function setSite($site)
    {
        $this->site = $site;
    }

    /**
     * @param string $PHPSESSID user session identifier
     */
    public function setPHPSESSID($PHPSESSID)
    {
        $this->PHPSESSID = $PHPSESSID;
    }

    /**
     * @param string $page
     * @todo not used
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * @param string $userIp user IP address
     */
    public function setUserIp($userIp)
    {
        $this->userIp = $userIp;
    }

    /**
     * @param string $altText
     */
    public function setAltText($altText)
    {
        $this->altText = $altText;
    }

    /**
     * @param string $availableText
     */
    public function setAvailableText($availableText)
    {
        $this->availableText = $availableText;
    }

    /**
     * @param string $shopDescriptionText
     */
    public function setShopDescriptionText($shopDescriptionText)
    {
        $this->shopDescriptionText = $shopDescriptionText;
    }

    /**
     * @param string $shopDescriptionArray
     */
    public function setShopMultiLangDescriptionText($shopDescriptionArray)
    {
        $this->shopDescriptionArray = $shopDescriptionArray;
    }

    /**
     * @param bool $imageNew
     */
    public function setImageNew($imageNew)
    {
        $this->imageNew = $imageNew;
    }

    /**
     * @param int $imageId
     */
    public function setImageId($imageId)
    {
        $this->imageId = $imageId;
    }

    /**
     * @param int $imageWDoc
     */
    public function setImageWDoc($imageWDoc)
    {
        $this->imageWDoc = $imageWDoc;
    }

    /**
     * @param int $imageCDoc
     */
    public function setImageCDoc($imageCDoc)
    {
        $this->imageCDoc = $imageCDoc;
    }

    /**
     * @param string $cdn
     */
    public function setImageCDN($cdn)
    {
        $this->imageCDN = $cdn;
    }

    /**
     * @param string $link
     */
    public function setLink($link)
    {
        $this->link = $link;
    }

    /**
     * Notify subscribers and store data into db
     * @param bool $fake should subscriber be ignored or not
     * @param int $secAgo when page was visited
     * @return int number of notified subscribers
     */
    public function notify($fake = false, $secAgo = 0)
    {
        $this->fillGeoData();

        $this->save($secAgo);

        if (!$fake) {
            return $this->pushToSockets();
        }
        return 0;
    }

    /**
     * Get last notifications
     * @param int $limit
     * @param string $site
     * @param bool $fake
     * @return self[]
     */
    public static function getLast($limit, $site, $fake)
    {
        $dbr = DB::getInstance(DB::USAGE_WRITE); //USAGE_WRITE because it could be right after insertion
        $rows = $dbr->getAll(
            '
                SELECT
                    distinct spl.*,
                    TIMEDIFF(NOW(),time) tdif
                FROM prologis_log.shop_page_log3 spl
                WHERE
                    spl.saved_id <> 0
                    AND (
                        `server` = ?
                        OR `server`= ?
                    )
                    ' . (!$fake ? ' AND ip IS NOT NULL ' : '') . '
                ORDER BY spl.time DESC
                LIMIT ' . (int)$limit,
            null,
            [
                'www.' . $site,
                $site,
            ]
        );
        $result = [];
        foreach ($rows as $record) {
            $notification = new self;
            $notification->id = $record->saved_id;
            $notification->tdif = $record->tdif;
            $notification->site = $record->server;
            $notification->page = $record->page;
            $notification->userCountry = $record->country;
            $notification->userRegion = $record->region;
            $notification->userCity = $record->city;
            $result[] = $notification;
        }
        return $result;
    }

    /**
     * FUNCTION WITH SIDE EFFECT
     * Generate fake notifications and store it into db
     * @param int $shopId
     * @param string $shopUrl
     * @param int $mins approximately when it was
     * @param int $count count of fake records
     * @return self[] fake notifications
     */
    public static function generateAndStoreFake($shopId, $shopUrl, $mins, $count)
    {
        $dbr = DB::getInstance(DB::USAGE_READ);
        $db = DB::getInstance(DB::USAGE_WRITE);
        $randomRecords = $dbr->getAll(
            '
                SELECT *
                FROM (
                    SELECT DISTINCT
                        id saved_id
                    FROM sa' . (int)$shopId . '
                    WHERE
                        ShopPrice > 0
                    ORDER BY RAND()
                    LIMIT ?
                ) t
                ORDER BY 2
            ',
            null,
            [
                $count,
            ]
        );
        /**
         * @todo order by date
         * @todo that records could be already in table - process that situation:
         *      exception when insert
         *      count result records less as expected
         */

        $result = [];
        foreach ($randomRecords as $record) {
            $notification = new self;
            $notification->id = $record->saved_id;
            $notification->notify(true, $mins * 60 * (mt_rand() / mt_getrandmax()));

            $saved_id = (int)$record['saved_id'];
            $mins = (int)$mins;
            $shopUrl = mysql_real_escape_string($shopUrl);
            $db->query('
                INSERT INTO prologis_log.shop_page_log3
                (saved_id, time, server, country)
                VALUES (' . $saved_id . ', DATE_SUB(NOW(), INTERVAL ' . $mins . '*RAND() SECOND), \'' . $shopUrl . '\', \'CH\')'
            );
            $result[] = $notification;
        }
        return $result;
    }

    /**
     * Removing old notifications
     * @todo build cron for it
     */
    public static function deleteOld()
    {
        $db = DB::getInstance(DB::USAGE_WRITE);
        $db->query('DELETE FROM prologis_log.shop_page_log3 WHERE TIMEDIFF(NOW(),`time`) > \'00:30:00\'');
    }

    /**
     * Storing notification into db
     * @param int $secAgo when it was
     */
    private function save($secAgo = 0)
    {
        $db = DB::getInstance(DB::USAGE_WRITE);

        $db->query('
            REPLACE prologis_log.shop_page_log3
            SET
                `PHPSESSID` = \'' . mysql_real_escape_string($this->PHPSESSID) . '\',
                `page` = \'' . mysql_real_escape_string($this->page) . '\',
                `time` = DATE_SUB(NOW(), INTERVAL ' . (int)$secAgo . ' SECOND),
                `server`= \'' . mysql_real_escape_string($this->site) . '\',
                `saved_id` = ' . (int)$this->id . ',
                `ip` = \'' . mysql_real_escape_string($this->userIp) . '\',
                `country` = \'' . mysql_real_escape_string($this->userCountry) . '\',
                `region` = \'' . mysql_real_escape_string($this->userRegion) . '\',
                `city` = \'' . mysql_real_escape_string($this->userCity) . '\''
        );
    }

    /**
     * Fill geo data based on stored IP
     */
    private function fillGeoData()
    {
        $geo = geoip_record_by_name($this->userIp);
        
        $this->userRegion = $geo['region'];
        $this->userCity = utf8_encode($geo['city']);
        $this->userCountry = $geo['country_code'];
    }

    /**
     * Push notification into redis
     * @return int number notified subscribers
     * @global \Smarty $smarty
     */
    private function pushToSockets()
    {
        global $smarty;
        include_once ROOT_DIR . '/plugins/function.saImageUrl.php';
        include_once ROOT_DIR . '/plugins/function.bannerCss.php';
        
        $dbr = DB::getInstance(DB::USAGE_READ);
        $db = DB::getInstance(DB::USAGE_WRITE);

        $message = [
            'site' => $this->site,
            'message' => [
                'id' => $this->id,
                'swap_id' => $this->swap_id ? $this->swap_id : '',
                'src_white_image' => $this->imageCDN . smarty_function_saImageUrl(
                    [
                        'src' => 'sa',
                        'saved_id' => $this->id,
                        'picid' => $this->imageId,
                        'x' => $this->image_size,
                        'type' => 'whitesh',
                        'new_image' => $this->imageNew,
                        'white_shadow_id' => $this->imageWDoc,
                        'white_noshadow_id' => $this->imageCDoc,
                    ],
                    $smarty
                ),
                'src_color_image' => $this->imageCDN . smarty_function_saImageUrl(
                    [
                        'src' => 'sa',
                        'saved_id' => $this->id,
                        'picid' => $this->imageId,
                        'x' => $this->image_size,
                        'type' => 'color',
                        'new_image' => $this->imageNew,
                        'white_shadow_id' => $this->imageWDoc,
                        'white_noshadow_id' => $this->imageCDoc,
                    ],
                    $smarty
                ),
                'banner' => $this->banner ? $this->banner : '',
                'banner_styles' => $this->banner ? smarty_function_bannerCss(
                        ['banner' => $this->banner, 'config' => \Config::getAll($db, $dbr), 'x' => $this->image_size]
                        , $smarty) : '',
                'alt' => $this->altText,
                'rating' => $this->rating,
                'price' => $this->priсe ? $this->priсe : '',
                'price_old' => $this->price_old ? $this->price_old : '',
                'discount' => $this->discount ? $this->discount : '',
                'shop_description' => $this->shopDescriptionText,
                'shop_description_multi' => $this->shopDescriptionArray,
                'available_text' => $this->availableText,
                'city' => $this->userCity,
                'country' => $this->userCountry,
                'link' => $this->link,
                'offer_available' => $this->offer_available,
                'date_available' => $this->date_available ? $this->date_available : '',
                'stock_color' => $this->stock_color,
                'available_weeks' => $this->available_weeks,
            ],
        ];

        $redis = RedisProvider::getInstance(RedisProvider::USAGE_NOTIFICATION);
        return $redis->publish($this->channel, json_encode($message));
    }
}