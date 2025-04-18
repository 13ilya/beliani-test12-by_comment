<?php

namespace label\Sitemap\Location;
use label\Sitemap\Item\SitemapImage;
use label\Sitemap\Item\SitemapVideo;

/**
 * Class MediaLocation
 * This class is parent to media files for sitemap
 */
abstract class MediaLocation
{
    /**
     * @var SitemapVideo[]|SitemapImage[]
     */
    protected $files;

    /**
     * Url page with media files
     * @var string
     */
    private $location;

    /**
     * MediaLocation constructor.
     * @param string $location url page with media files
     */
    public function __construct($location)
    {
        $this->location = $location;
    }

    /**
     * Magic method for process get* methods.
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get'.ucfirst($name);
        if(method_exists($this, $getter)) {
            return $this->$getter();
        }
        throw new \BadMethodCallException();
    }

    /**
     * Return url page with media files
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Return list of files in location
     * @return SitemapVideo[]|SitemapImage[]
     */
    protected function getFiles()
    {
        return array_values($this->files);
    }

    /**
     * Addition media file to location
     * @param SitemapVideo|SitemapImage $file
     */
    protected function addFile($file)
    {
        $this->files[$file->url] = $file;
    }

}