<?php

namespace label\Sitemap\Item;

/**
 * Class SitemapImage
 * Contain info about single image used to show on sitemap
 * @todo make getter-setter
 */
class SitemapImage
{
    public $url;
    public $caption;
    public $title;

    /**
     * SitemapImage constructor.
     * @param string $url
     * @param string $caption
     * @param string $title
     */
    public function __construct($url, $caption, $title)
    {
        $this->url = $url;
        $this->caption = $caption;
        $this->title = $title;
    }
}