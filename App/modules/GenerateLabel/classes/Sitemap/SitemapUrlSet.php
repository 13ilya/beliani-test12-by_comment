<?php

namespace label\Sitemap;

/**
 * Class SitemapUrlSet
 * Class used to collect all urls in sitemap
 */
class SitemapUrlSet implements \Iterator
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var array of SitemapUrl
     */
    private $elements = [];

    /**
     * SitemapUrlSet constructor.
     * @param string $baseUrl full site url, f.e. https://www.beliani.net
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Add url to sitemap
     * @param string $idLocation
     * @param string $url
     * @param string $language language as ISO 639-1 code
     */
    public function addUrl($idLocation, $url, $language)
    {
        if (!isset($this->elements[$idLocation])) {
            $this->elements[$idLocation] = new SitemapUrl($this->baseUrl.$url);
        }
        $this->elements[$idLocation]->addAlternateLanguage($language, $this->baseUrl.$url);
    }

    /**
     * /Iterator implementation
     * @return array
     */
    public function next()
    {
        return next($this->elements);
    }

    /**
     * /Iterator implementation
     * @return bool
     */
    public function valid()
    {
        return key($this->elements) !== null;
    }

    /**
     * /Iterator implementation
     * @return array
     */
    public function current()
    {
        return current($this->elements);
    }

    /**
     * /Iterator implementation
     * @return mixed
     */
    public function rewind()
    {
        return reset($this->elements);
    }

    /**
     * /Iterator implementation
     * @return mixed
     */
    public function key()
    {
        return key($this->elements);
    }
}