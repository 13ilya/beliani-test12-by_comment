<?php
/**
 * Bot detector. Using browser Agent string
 */
class BotDetector {
    public static $bots = array(
		'aranhabot',
        'bingbot',
        'Googlebot',
        'AhrefsBot',
        'FeedBot',
        'BingPreview',
        'YandexImages',
        'SEOkicks-Robot',
        'preisroboter',
        'JobboerseBot',
        'SeznamBot',
        'AdsBot-Google',
        'CUBOT',
        'seoscanners',
        'Baiduspider',
        'Pinterest',
        'YahooCacheSystem',
        'TinEye-bot',
        'TrovaPrezzi-ShopBot',
        'YandexBot',
        'Twitterbot',
        'MJ12bot',
        'YandexImageResizer',
        'BingWeb',
	);
    /**
     * Define is visitor is a bot by User-Agent browser header.
     * @return bool
     */
    public static function isBot() {
        if ($_SERVER['HTTP_USER_AGENT'] && !empty($_SERVER['HTTP_USER_AGENT'])) {
            foreach (self::$bots as $bot){
                if (stripos($_SERVER['HTTP_USER_AGENT'], $bot) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
}