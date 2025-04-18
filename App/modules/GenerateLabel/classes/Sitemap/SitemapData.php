<?php

namespace label\Sitemap;

use label\Sitemap\Item\SitemapImage;
use label\Sitemap\Item\SitemapPage;
use label\Sitemap\Item\SitemapVideo;
use label\Sitemap\Location\ImageLocation;
use label\Sitemap\Location\VideoLocation;

/**
 * Class SitemapData
 * Class used to collect all data in sitemap
 * @todo rename class
 */
class SitemapData
{
    const GOOGLE_LOCATIONS_LIMIT = 50000;

    /**
     * Artificial number to fit into sitemap size limit
     */
    const IMAGES_PER_FILE = 70000;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var SitemapPage[]
     */
    private $offerLocations = [];

    /**
     * @var SitemapPage[]
     */
    private $pageLocations = [];

    /**
     * @var ImageLocation[][]
     */
    private $imageLocations = [];

    /**
     * @var VideoLocation[][]
     */
    private $videoLocations = [];

    /**
     * @var array
     */
    private $urlCache = [];

    /**
     * SitemapData constructor.
     * @param string $baseUrl full site url, f.e. https://www.beliani.net
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Getter for all pages in set
     * @return Item\SitemapPage[]
     */
    public function getPageLocations()
    {
        return $this->pageLocations;
    }

    /**
     * Return info are images presented in set
     * @return bool
     */
    public function areImagesPresented()
    {
        return (bool)count($this->imageLocations);
    }

    /**
     * Getter to image locations in set.
     * This method return array of array of image locations, splitted to fit for one sitemap file.
     * Used generator.
     * @return \Generator|ImageLocation[]
     */
    public function getImageLocations()
    {
        $result = [];
        $countImagesInResult = 0;
        foreach ($this->imageLocations as $offerId => $langLocations) {
            foreach ($langLocations as $language => $location) {
                if ($countImagesInResult >= self::IMAGES_PER_FILE) {
                    yield array_values($result);
                    $result = [];
                    $countImagesInResult = 0;
                }
                $result[] = $location;
                $countImagesInResult += count($location->getImages());
            }
        }
        yield $result;
    }

    /**
     * @return Location\VideoLocation[]
     */
    public function getVideoLocations()
    {
        $result = [];
        foreach ($this->videoLocations as $offerId => $langLocations) {
            foreach ($langLocations as $language => $location) {
                $images = [];
                foreach ($this->imageLocations[$offerId] as $imageLocation) {
                    $images = array_merge($images, $imageLocation->getImages());
                }
                $location->updateThumbnails($images);
                $result[] = $location;
            }
        }
        return $result;
    }

    /**
     * Add url to sitemap
     * @param string $idLocation
     * @param string $url
     * @param string $languageCode language as ISO 639-1 code
     */
    public function addPage($idLocation, $url, $languageCode)
    {
        if (count($this->pageLocations) >= self::GOOGLE_LOCATIONS_LIMIT) {
            throw new \Exception('Too much locations in sitemap');
        }
        
        if (!isset($this->pageLocations[$idLocation][$this->baseUrl.$url])) {
            $this->pageLocations[$idLocation][$this->baseUrl.$url] = $languageCode;
        }
        
        if (!isset($this->offerLocations[$idLocation])) {
            $this->offerLocations[$idLocation] = new SitemapPage($this->baseUrl.$url);
        } else {
            if ( ! in_array($url, array_keys($this->urlCache))) {
                $this->offerLocations[$idLocation]->addAlternateLanguage($languageCode, $this->baseUrl.$url);
            }
        }
        
        $this->urlCache[$url][] = [
            'language' => $languageCode,
            'location' => $idLocation,
        ];
    }

    /**
     * Add image associated with offer
     * @param int $offerId
     * @param string $languageCode language as ISO 639-1 code
     * @param SitemapImage $image image to add
     */
    public function addOfferImage($offerId, $languageCode, SitemapImage $image)
    {
        if (!isset($this->imageLocations[$offerId][$languageCode])) {
            $location = $this->getOfferLocation($offerId, $languageCode);
            $this->imageLocations[$offerId][$languageCode] = new ImageLocation($location);
        }

        $image->url = $this->baseUrl.$image->url;
        $this->imageLocations[$offerId][$languageCode]->addImage($image);
    }

    /**
     * Add video associated with offer
     * @param int $offerId
     * @param string $languageCode language as ISO 639-1 code
     * @param SitemapVideo $video video to add
     */
    public function addOfferVideo($offerId, $languageCode, SitemapVideo $video)
    {
        if (!isset($this->videoLocations[$offerId][$languageCode])) {
            $location = $this->getOfferLocation($offerId, $languageCode);
            $this->videoLocations[$offerId][$languageCode] = new VideoLocation($location);
        }

        $this->videoLocations[$offerId][$languageCode]->addVideo($video);
    }

    /**
     * Check if set images or videos for offer
     * @param int $offerId
     * @param string $languageCode language as ISO 639-1 code
     * @return bool
     */
    public function issetOfferMedia($offerId, $languageCode)
    {
        return (
            isset($this->imageLocations[$offerId][$languageCode])
            || isset($this->videoLocations[$offerId][$languageCode])
        );
    }

    /**
     * Returns list of ununique urls with location data (language code and location id)
     * @return array
     */
    public function getUnuniqueUrls()
    {
        $result = [];
        foreach ($this->urlCache as $url => $locationSet) {
            if (count($locationSet) > 1) {
                $result[] = [
                    'url' => $url,
                    'locationSet' => $locationSet,
                ];
            }
        }
        return $result;
    }

    /**
     * Searchs for location for some offer id and language.
     * @param int $offerId
     * @param string $languageCode language as ISO 639-1 code
     * @return mixed
     * @throws \Exception
     */
    private function getOfferLocation($offerId, $languageCode)
    {
        $idLocation = 'offer_'.$offerId;
        if (!isset($this->offerLocations[$idLocation])) {
            foreach ($this->offerLocations as $key => $singleUrl) {
                $keyParts = explode('_', $key);
                $keyId = array_pop($keyParts);
                if (($keyParts[0] === 'offer') && ($keyId == $offerId)) {
                    $idLocation = $key;
                    break;
                }

            }
        }
        if (!isset($this->offerLocations[$idLocation])) {
            throw new \Exception('Missed base location for image '.$idLocation);
        }
        return $this->offerLocations[$idLocation]->getLanguageLocation($languageCode);
    }
}