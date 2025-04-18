<?php

namespace label\Sitemap;

/**
 * Class SitemapUrl
 * Instance of the class collect all info about single url
 */
class SitemapUrl
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
     * SitemapUrl constructor.
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
     * @param string $language language as ISO 639-1 code
     * @param string $url full url
     */
    public function addAlternateLanguage($language, $url)
    {
        if (isset($this->languages[$language])) {
            throw new \LogicException('Url in this language was already set');
        }
        //@todo what to do if url the same in different language

        $this->languages[$language] = $url;
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
    }

    /**
     * Return list of alternate languages urls
     * @return array
     */
    public function getAlternateLanguages()
    {
        return $this->languages;
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