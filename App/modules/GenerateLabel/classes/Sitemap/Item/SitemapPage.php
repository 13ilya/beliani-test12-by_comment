<?php

namespace label\Sitemap\Item;

/**
 * Class SitemapPage
 * Instance of the class collect all info about single url
 */
class SitemapPage
{
    /**
     * @var string
     */
    private $location;

    /**
     * Collect alternate languages urls
     * @var array
     */
    private $languages = [];

    /**
     * SitemapPage constructor.
     * @param string $url full url
     */
    public function __construct($url)
    {
        if (urlencode($url) !== $url) {
            //@todo process it
        }
        $this->location = $url;
    }

    /**
     * Add alternate language for current url
     * @param string $languageCode language as ISO 639-1 code
     * @param string $url full url
     */
    public function addAlternateLanguage($languageCode, $url)
    {
        if (isset($this->languages[$languageCode])) {
            return;
//            throw new \LogicException('Url in this language was already set: ' . $languageCode . ' ' . $url);
        }
        //@todo what to do if url the same in different language

        $this->languages[$languageCode] = $url;
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
     * Magic setter for set* methods
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $setter = 'set'.ucfirst($name);
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            throw new \BadMethodCallException();
        }
    }

    /**
     * @param string $languageCode language as ISO 639-1 code
     * @return string
     */
    public function getLanguageLocation($languageCode)
    {
        return $this->languages[$languageCode];
    }

    /**
     * Return list of alternate languages urls
     * @return array
     */
    public function getAlternateLanguages()
    {
        return $this->languages;
    }

    public function getVideos()
    {
        return $this->videos;
    }

    public function setVideos($value)
    {
        $this->videos = $value;
    }

    public function getImages()
    {
        return $this->images;
    }

    public function setImages($value)
    {
        $this->images = $value;
    }

    /**
     * Return main url/location of this url
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }
}