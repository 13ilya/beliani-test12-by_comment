<?php
namespace label\Spider;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use label\NginxCache\NginxCache;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SpiderJob
 * Class works with queue of jobs to to go clear frontend pages cache and go through them
 */
class SpiderJob
{
    /**
     * @var bool
     */
    private static $trackResponse = false;

    /**
     * @var bool
     */
    private static $emulate = false;

    /**
     * @var callable
     */
    private static $callbackMessage;

    /**
     * @var bool
     */
    private static $ignoreCert;

    /**
     * @var mixed[]
     */
    public $args = [];

    /**
     * @var mixed[]
     */
    public $purged = [];
    
    /**
     * @param bool $value
     */
    public static function setIgnoreCertificate($value)
    {
        self::$ignoreCert = (bool)$value;
    }

    /**
     * Should response be tracked or not
     * @param bool $value
     */
    public static function setTrackResponse($value)
    {
        self::$trackResponse = (bool)$value;
    }

    /**
     * Should spider actually call pages or just emulate (used for debug)
     * @param bool $value
     */
    public static function setEmulate($value)
    {
        self::$emulate = (bool)$value;
    }

    /**
     * Set function to throw message up to called code
     * @param callable $function
     */
    public static function setCallbackMessage($function)
    {
        self::$callbackMessage = $function;
    }

    /**
     * Actually do job
     * @return bool
     * @throws JobException
     */
    public function perform()
    {
        $this->throwMessage('report', 'Start');
        foreach ($this->args as $job) {
            if ($job['action'] === 'clear') {
//                $this->clearCache($job['schema'], $job['domain'], $job['url'], $job['cookies']);
            } elseif ($job['action'] === 'crawl') {
                $this->crawl($job['schema'], $job['domain'], $job['url'], $job['cookies']);
            } else {
                throw new JobException();
            }
        }
        return true;
    }

    /**
     * @param string $schema
     * @param string $domain
     * @param string $url
     * @param string[] $cookies
     */
    private function clearCache($schema, $domain, $url, $cookies)
    {
        $cache = $this->findCache($schema, $domain, $url, $cookies);
        $this->throwMessage('debug', 'cache-key:' . $cache->buildKey());
        foreach ($cache->getMatchedKeys() as $key) {
            $this->throwMessage('debug', 'matched-key:' . $key);
        }

        if (!self::$emulate) {
            $this->throwMessage('report', 'clearing cache...');
            $clearedRecords = $cache->clear(50);
            foreach($clearedRecords as $record) {
                $this->throwMessage('debug', 'cleared:' . $record);
            }
        }
    }
    
    private function purgeCache($schema, $domain, $url, $cookies) {
        $purge_id = md5(implode('.', [$schema, $domain, $url]));
        $this->throwMessage('debug', 'purgeCache' . $domain . $url);
        if (isset($this->purged[$purge_id])) {
            $this->throwMessage('debug', 'purgeCache Already purged');
            return false;
        }
        
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

        $this->throwMessage('debug', 'purgeCache Response:' . $res);
    }

    /**
     * @param string $schema
     * @param string $domain
     * @param string $url
     * @param string[] $cookies
     */
    private function crawl($schema, $domain, $url, $cookies)
    {
        $this->purgeCache($schema, $domain, $url, $cookies);
        return 0;
        
        $fullUrl = $schema . '://' . $domain . $url;
        $this->throwMessage('debug', 'url:' . $fullUrl);
        $jar = new CookieJar(true, self::prepareCookies($cookies, $domain));
        $this->throwMessage('debug', 'cookies:' . json_encode($cookies));
        if (!self::$emulate) {
            $this->throwMessage('report', 'calling...');
            $response = $this->provider()->get($fullUrl, ['cookies' => $jar]);
            $this->throwMessage('report', 'code:' . $response->getStatusCode());
            $this->throwMessage('debug', 'headers:' . json_encode($response->getHeaders()));
            if ($response->getStatusCode() !== 200) {
                $this->logBadResponse(
                    $response,
                    [
                        'schema' => $schema,
                        'domain' => $domain,
                        'url' => $url,
                        'cookies' => $cookies,
                    ]
                );
            }
            if (self::$trackResponse) {
                $this->throwMessage('report', 'response:' . $response->getBody());
            }
        }
    }

    /**
     * Prepare NginxCache object based on data about page
     * @param string $schema
     * @param string $domain
     * @param string $url
     * @param string[] $cookies
     * @return NginxCache
     */
    private function findCache($schema, $domain, $url, $cookies)
    {
        $cache = new NginxCache();
        if (isset($schema)) {
            $cache->setSchema($schema);
        }
        if (isset($domain)) {
            $cache->setDomain($domain);
        }
        if (isset($url)) {
            $cache->setUrl($url);
        }
        if (count($cookies) > 0) {
            foreach ($cookies as $name => $value) {
                $cache->setCookie($name, $value);
            }
        }
        return $cache;
    }

    /**
     * Throw message back to called code
     * @param string $level
     * @param string $message
     */
    private function throwMessage($level, $message)
    {
        if (isset(self::$callbackMessage)) {
            call_user_func_array(self::$callbackMessage, ['level' => $level, 'message' => $message]);
        }
    }

    /**
     * Prepare client to call remote host
     * @return Client
     */
    private function provider()
    {
        if (!isset($this->provider)) {
            $this->provider = new Client([
                'allow_redirects' => true,
                'verify' => !self::$ignoreCert,
            ]);
        }
        return $this->provider;
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
     * Log responses of spider if bad code presented
     * @param ResponseInterface $response
     * @param mixed[] $args
     */
    private function logBadResponse(ResponseInterface $response, $args)
    {
        $this->throwMessage('report', 'arguments:' . json_encode($args));
        $this->throwMessage('report', 'code:' . $response->getStatusCode());
        $this->throwMessage('report', 'headers:' . json_encode($response->getHeaders()));
        file_put_contents(
            TMP_DIR . '/spider_error.txt',
            date('Y-m-d H:i:s') . '|BAD-RESPONSE.args:' . json_encode($args) . PHP_EOL
                . '|code:' . $response->getStatusCode() . PHP_EOL
                . '|headers:' . json_encode($response->getHeaders()) . PHP_EOL,
            FILE_APPEND
        );
    }
}