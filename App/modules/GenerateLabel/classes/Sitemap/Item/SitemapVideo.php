<?php

namespace label\Sitemap\Item;

/**
 * Class SitemapVideo
 * Contain info about single video used to show on sitemap
 * @todo make getter-setter
 */
class SitemapVideo
{
    public $url;
    public $thumbnail;
    public $title;
    public $description;
    public $category;
    public $tags;

    /**
     * SitemapVideo constructor.
     * @param string $url
     * @param string $title
     * @param string $description
     * @param string $category
     * @param string[] $tags
     */
    public function __construct($url, $title, $description, $category, $tags)
    {
        $this->url = $url;
        $this->title = $title;
        $this->description = $description;
        $this->category = $category;
        $this->tags = $tags;
    }
}