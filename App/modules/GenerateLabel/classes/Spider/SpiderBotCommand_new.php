<?php
namespace label\Spider;

use label\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Promise;

use GuzzleHttp\Exception\ServerException;

/**
 * Class SpiderBuildCommand
 * Command used to create list of all products/categories pages and cookies
 */
class SpiderBotCommand extends Command
{
    const OPTION_SHOP_ID = 'shop-id';
    const OPTION_SHOW_DEBUG = 'show-debug';
    const OPTION_THREADS = 'threads';

    const MAX_THREADS = 4;

    /**
     * @var InputInterface
     */
    private static $input;

    /**
     * @var OutputInterface
     */
    private static $output;
    
    private $_db;
    private $_dbr;

    /**
     * Show and log messages
     * @param string $message
     */
    public function processMessage($message)
    {
        $milliseconds = intval(explode(' ', microtime())[0] * 1000);
        $record = date('Y-m-d H:i:s') . '.' . $milliseconds
                . '|p' . str_pad(getmypid(), 7, '0', STR_PAD_LEFT)
                . '|' . $message;
        self::$output->writeln($record);
        file_put_contents(TMP_DIR . '/spider.bot.txt', $record . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('spider:bot')
            ->setDescription('Bot spider for preheating cache.')
            ->addOption(
                self::OPTION_SHOP_ID,
                null,
                InputOption::VALUE_IS_ARRAY  | InputOption::VALUE_REQUIRED,
                'Shop ID'
            )
            ->addOption(
                self::OPTION_SHOW_DEBUG,
                null,
                InputOption::VALUE_NONE,
                'output debug data'
            )
            ->addOption(
                self::OPTION_THREADS,
                null,
                InputOption::VALUE_REQUIRED,
                'limit threads'
            );
    }/** @noinspection PhpMissingParentCallCommonInspection */

    /**
     * Run the current command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$output = $output;
        self::$input = $input;
        
        $this->_dbr = DB::getInstance(DB::USAGE_READ);
        $this->_db = DB::getInstance(DB::USAGE_WRITE);
        
        exec('ps auxwww|grep "spider:bot"|grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);
        
        foreach ($res as $k => $v)
        {
            if (stripos($v, 'APPLICATION_ENV=develop'))
            {
                unset($res[$k]);
            }
        }
        
        $res = count($res);
        if ($res > self::MAX_THREADS) {
            $this->processMessage("Already $res / " . self::MAX_THREADS . " threads launched");
            return 0;
        }
        
//        file_put_contents(TMP_DIR . '/spider.bot.txt', "");
        
        $this->processMessage("Found $res threads launched");
        
        if ((!empty($input->getOption(self::OPTION_SHOP_ID)))) 
        {
            $condition = ' AND id IN (' . implode(', ', $input->getOption(self::OPTION_ID)) . ')';
        } 
        else 
        {
            $condition = '';
        }
        
        $shopsIds = array_map(
            function($element){return $element->id;},
            $this->_dbr->getAll('SELECT id FROM shop WHERE NOT inactive ' . $condition)
        );
            
        $shopsIds = [1, 2, 3, 6, 7, 8, 9, 10, 12, 17, 18, 19, 21, 22, 23, 24, 25, 26, 27];
            
        foreach ($shopsIds as $shop_id)
        {
            $this->processShop($shop_id);
        }
            
        return 0;
    }
    
    private function processShop($shop_id)
    {
        $shop = $this->_dbr->getRow("SELECT `siteid`, `name`, `url`, `ssl`, `username`
                FROM `shop` WHERE `id` = '" . $shop_id . "'");
        $this->processMessage("Current shop ID: $shop_id, {$shop->name}");
        
        $langs = ['xx'];
        if ($shop_id == 1)
        {
            $shop->url = 'www.beliani.ch';
            $shop->ssl = 1;

            $langs = ['en', 'de', 'pl', 'fr', 'it', 'es', 'pt'];
        }
        
        if ($shop_id == 12)
        {
            $shop->url = 'www.beliani.pl';
            $shop->ssl = 1;
        }
        
        $shop_url = ($shop->ssl ? "https://" : "http://") . $shop->url . '/';
        $this->provider($shop_url);
        
        $links = $this->getSitemap($shop_url, $langs);
        
        $this->processMessage("Found: " . count($links) . " links");
        
        $langs = $this->_dbr->getAssoc("SELECT `lang`, `lang` `v`
            FROM `seller_lang`
            WHERE `username` = '" . mysql_real_escape_string($shop->username) . "'
                AND `useit` = 1");
        
        $langs = $this->_dbr->getAssoc("SELECT LOWER(`config_api_values`.`code`), `seller_lang`.`lang`
            FROM `seller_lang`
            JOIN `config_api_values` ON `seller_lang`.`lang` = `config_api_values`.`value`
            WHERE `seller_lang`.`username` = '" . mysql_real_escape_string($shop->username) . "'
                AND `seller_lang`.`useit` = 1
                AND `config_api_values`.`par_id` = 6 
                AND NOT `config_api_values`.`inactive`");
        
        if ($shop_id == 1)
        {
            $langs = [
                'en' => 'english', 
                'de' => 'german', 
                'pl' => 'polish', 
                'fr' => 'french', 
                'it' => 'italian', 
                'sp' => 'spanish', 
                'pt' => 'portugal'];
        }
        
        foreach ($links as $lang => $link)
        {
            $links_categories = [];
            
            foreach ($link as $key => $item)
            {
                if (preg_match('#\/$#iu', $item))
                {
                    $links_categories[] = $item;
                    unset($link[$key]);
                }
            }
            
            shuffle($links_categories);
            shuffle($link);
            
            $links[$lang] = array_merge($links_categories, $link);
        }
        
        
        foreach ($links as $lang_link => $link)
        {
            $counter = 0;
            
            foreach ($link as $item)
            {
                $counter++;
                $this->processMessage("Link: [$lang_link] $item, ($counter / " . count($links[$lang_link]) . ")");
    //            $this->processAsynkLinks($shop->url, $link, $langs);

//                $purge = parse_url($item);
//                
//                $this->purgeCache($purge['scheme'], $purge['host'], $purge['path']);
                $this->processLinks($shop->url, $item, $langs[$lang_link]);
                
//                foreach ($langs as $lang)
//                {
//                    $this->processLinks($shop->url, $link, $lang);
//                }
            }
        }
    }
    
    private function processAsynkLinks($domain, $fullUrl, $langs)
    {
        $_time = microtime(true);
        
        try 
        {
            foreach ($langs as $lang)
            {
                $cookies = [
                    'shop_lang' => $lang,
                    'skin' => 'autumn',
                    'off_mobile' => 0,
                ];
                $jar = new CookieJar(true, self::prepareCookies($cookies, $domain));

                $promises[$lang . "|0"] = $this->provider()->getAsync($fullUrl, ['cookies' => $jar]);

                $cookies = [
                    'shop_lang' => $lang,
                    'skin' => 'autumn',
                    'off_mobile' => 1,
                ];
                $jar = new CookieJar(true, self::prepareCookies($cookies, $domain));

                $promises[$lang . "|1"] = $this->provider()->getAsync($fullUrl, ['cookies' => $jar]);
            }
            
            $results = Promise\settle($promises)->wait();
            foreach ($results as $lang => $result)
            {
                $lang = explode("|", $lang);
                $this->processMessage("Status: [{$lang[0]}, {$lang[1]}]" . 
                        "\t" . $result['value']->getStatusCode());
            }
        }
        catch (Exception $e)
        {
             $this->processMessage("EXCEPTION: " . $e->getMessage());
        }
        catch (GuzzleHttp\Exception\ServerException $e)
        {
             $this->processMessage("GuzzleHttp EXCEPTION: " . $e->getMessage());
        }
        
        $this->processMessage("Time: " . round(microtime(true) - $_time, 2) . "s");
    }
    
    private function processLinks($domain, $fullUrl, $lang)
    {
//        $this->processMessage("\tLink: $fullUrl, Lang: $lang");
        
        $this->firedLink($domain, $fullUrl, $lang, 0);
        $this->firedLink($domain, $fullUrl, $lang, 1);
    }
    
    private function firedLink($domain, $fullUrl, $lang, $off_mobile) 
    {
        for ($i = 0; $i < 10; ++$i)
        {
            $_time = microtime(true);
            
            $cookies = [
                'shop_lang' => $lang,
                'skin' => 'autumn',
                'off_mobile' => $off_mobile,
            ];

            try 
            {
                $jar = new CookieJar(true, self::prepareCookies($cookies, $domain));
                $response = $this->provider()->get($fullUrl, ['cookies' => $jar]);

                $this->processMessage("Status: [$lang, $off_mobile]" . 
                        "\t" . $response->getStatusCode() . 
                        "\tTime: " . round(microtime(true) - $_time, 2) . "s");
                
                if ($response->getStatusCode() == 200) 
                {
                    break;
                }

//                $url = parse_url($fullUrl);
//                $this->purgeCache($url['scheme'], $url['host'], $url['path']);
            }
            catch (Exception $e)
            {
                 $this->processMessage("EXCEPTION: " . $e->getMessage());
                 break;
            }
            catch (ServerException $e)
            {
                 $this->processMessage("EXCEPTION: " . $e->getMessage());
                 break;
            }
        }
    }
    
    private function getSitemap($url, $langs = []) 
    {
        $response = $this->provider->get('sitemap.xml');
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        
        $response = simplexml_load_string($response->getBody());
        
        $sitemap_links = [];
        foreach ($response->sitemap as $sitemap)
        {
            $sitemap = (string)$sitemap->loc;
            if (stripos($sitemap, '_content') !== false)
            {
                $sitemap_links[] = $sitemap;
            }
        }
        
        $links = [];
        foreach ($sitemap_links as $sitemap)
        {
            $response = $this->provider->get($sitemap);
            
            if ($response->getStatusCode() !== 200) {
                continue;
            }
            
            $response = $response->getBody();
            $response = str_replace("xhtml:link", "xhtml_link", $response);
            
            $response = simplexml_load_string($response);
            
            foreach ($response->url as $url)
            {
                $good = 0;
                foreach ($url->xhtml_link as $attribute)
                {
                    $attribute = (array)$attribute->attributes();
                    $attribute = $attribute['@attributes'];
                        
                    $lang = explode('-', $attribute['hreflang']);
                    $lang = $lang[0];

                    if (in_array('xx', $langs) || in_array($lang, $langs))
                    {
                        $links[$lang][] = trim((string)$attribute['href']);
                        $good++;
                    }
                }
                
                if ($good < count($langs))
                {
//                    $links[] = trim((string)$url->loc);
                }
            }
        }
        
        foreach ($links as $lang => $link)
        {
            $links[$lang] = array_values(array_unique($link));
        }
        
        return $links;
    }

    /**
     * Something like CookieJar::fromArray() (the difference is in strict mode)
     * Prepare raw cookies to work with it
     * @see CookieJar::fromArray()
     * @param $cookies
     * @param $domain
     * @return array
     */
    private static function prepareCookies($cookies, $domain)
    {
        $result = [];
        foreach ($cookies as $name => $value) {
            if ($value !== '') {
                $result[] = new SetCookie([
                    'Name' => $name,
                    'Value' => $value,
                    'Domain' => $domain,
                    'Discard' => true
                ]);
            }
        }
        return $result;
    }
    
    /**
     * Prepare client to call remote host
     * @return Client
     */
    private function provider($url)
    {
        $this->provider = new Client([
            'base_uri' => $url,
            'allow_redirects' => true,
            'verify' => false,
        ]);
        return $this->provider;
    }
    
    /**
     * 
     * @param type $schema
     * @param type $domain
     * @param type $url
     * @param type $cookies
     */
    private function purgeCache($schema, $domain, $url) {
        $purge_id = md5(implode('.', [$schema, $domain, $url]));
        $this->processMessage('purgeCache' . $domain . $url);
        
        $is_purged = cacheGet("PURGECACHE@/$purge_id", '', '');
        
        if ($is_purged) {
            $this->processMessage('purgeCache Already purged');
            return false;
        }
        
        cacheSet("PURGECACHE@/$purge_id", '', '', 1);
        $this->purged[$purge_id] = true;
        
        $curl = new \Curl(null, [
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.92 Safari/537.4',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 60
        ]);

        $PURGE_URL = 'https://www.beliani.ch/shop_cache_clear.php?hash=2192xbwBvkBiZu5Gx1Gx&delete=1';
        $PURGE_URL .= "&schema=" . rawurlencode($schema);
        $PURGE_URL .= "&domain=" . rawurlencode($domain);
        $PURGE_URL .= "&url=" . rawurlencode($url);

        $curl->set_url($PURGE_URL);
        $res = $curl->exec();

        $this->processMessage('purgeCache Response:' . $res);
    }
}