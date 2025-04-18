<?php

namespace label\NginxCache;

use label\DB;
use label\NginxCache\Exception\BadCookieException;
use label\NginxCache\Exception\BadDomainException;
use label\NginxCache\Exception\BadSchemaException;
use label\NginxCache\Exception\BadUrlException;
use label\NginxCache\Exception\ClearCacheException;
use label\NginxCache\Exception\TooManyException;

/**
 * Makes cover and describe data used by nginx to cache frontend pages.
 * The instance describe set of cached pages.
 */
class NginxCache
{
    /**
     * @var string[] cookies used by nginx
     */
    private static $acceptCookies = [
        'shop_lang',
        'skin',
        '[deprecated]',//not used more, but saved in cache key
        'off_mobile',
        'currency_code',
    ];

    /**
     * @var string
     */
    private $schema;

    /**
     * @var string shop domain name (should be with leading 'www.')
     */
    private $domain;

    /**
     * @var string
     */
    private $url;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var string[] cookies for current set
     */
    private $cookies = [
        '[deprecated]' => '',
    ];

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = (bool)$debug;
    }

    /**
     * @param string $schema
     * @throws BadSchemaException
     */
    public function setSchema($schema)
    {
        $schemas = ['http', 'https'];
        if (!in_array($schema, $schemas)) {
            throw new BadSchemaException();
        }
        $this->schema = $schema;
    }

    /**
     * @param string $domain could be with/without leading 'www.', f.e. 'www.beliani.at'
     * @throws BadDomainException
     */
    public function setDomain($domain)
    {
        $dbr = DB::getInstance(DB::USAGE_WRITE);
        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4, strlen($domain)-4);
        }
        $countShops = (int)$dbr->getOne(
            'SELECT COUNT(id) FROM shop WHERE domain = ?',
            null,
            [$domain]
        );
        if ($countShops === 0) {
            throw new BadDomainException();
        }
        $this->domain = 'www.' . $domain;
    }

    /**
     * @param string $url absolute url without domain with leading slash, f.e. '/new/'
     * @throws BadUrlException
     */
    public function setUrl($url)
    {
        $urlForParse = 'http://test1.ru' . $url;
        if (parse_url($urlForParse) === false) {
            throw new BadUrlException();
        }
        $this->url = $url;
    }

    /**
     * @param string $name
     * @param string $value
     * @throws BadCookieException
     */
    public function setCookie($name, $value)
    {
        if (isset($this->cookies[$name])) {
            file_put_contents(
                TMP_DIR . '/spider_error.txt',
                date('Y-m-d H:i:s') . '|COOKIE-PRESENTED.cookie:' . $name,
                FILE_APPEND
            );
            throw new BadCookieException();
        }
        $this->cookies[$name] = $value;
    }

    /**
     * Return list of cached pages keys
     * @return string[]
     * @throws ClearCacheException
     * @todo return real count of matched records (from cache_clean.pl)
     */
    public function getMatchedKeys()
    {
        $command = 'sudo /CACHE/cache_clean.pl ' . escapeshellarg($this->buildKey());
        $response = `$command`;
        
        if ($this->debug)
        {
            echo "<pre>$command\n$response\n<pre>";
        }
        
        if (preg_match('#(\d+) cache files matched#iu', $response, $matches)) {
            return $matches[1];
        }
        
        throw new ClearCacheException();
    }

    /**
     * Get count of cached pages
     * @return int
     */
    public function getCount()
    {
        return $this->getMatchedKeys();
    }

    /**
     * Clear found cache records
     * @param int $limit
     * @return string[] cleared records
     * @throws ClearCacheException
     * @throws TooManyException
     * @todo return real count of deleted records (from cache_clean.pl)
     */
    public function clear($limit = 100)
    {
        if (isset($limit)) {
            if ($this->getCount() > $limit) {
                throw new TooManyException();
            }
        }
        
        $command = 'sudo /CACHE/cache_clean.pl ' . escapeshellarg($this->buildKey()) . ' -f';
        $response = `$command`;
        
        if (preg_match('#(\d+) cache files deleted#iu', $response, $matches)) {
            return $matches[1];
        }
        
        throw new ClearCacheException();
    }

    /**
     * Build cache key for passed data
     * @return string
     * @todo if needed - erase useless ".*"
     */
    public function buildKey()
    {
        $key = '';
        if (isset($this->schema)) {
            $key .= $this->schema;
        } else {
            $key .= '.*';
        }
        if (isset($this->domain)) {
            $key .= $this->domain;
        } else {
            $key .= '.*';
        }
        if (isset($this->url)) {
            $key .= $this->url;
        } else {
            $key .= '.*';
        }
        foreach (self::$acceptCookies as $cookie) {
            $key .= '|';
            if (isset($this->cookies[$cookie])) {
                $key .= $this->cookies[$cookie];
            } else {
                $key .= '.*';
            }
        }
        return $key;
    }
}