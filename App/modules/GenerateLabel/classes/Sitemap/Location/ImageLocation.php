<?php

namespace label\Sitemap\Location;

use label\Sitemap\Item\SitemapImage;

/**
 * Class ImageLocation
 * Used to collect all images related to some location
 */
class ImageLocation extends MediaLocation
{
    const GOOGLE_IMAGES_LIMIT = 1000;

    /**
     * Add image to images list
     * @param SitemapImage $image
     * @throws \Exception
     */
    public function addImage(SitemapImage $image)
    {
        if (count($this->getImages()) >= self::GOOGLE_IMAGES_LIMIT) {
            throw new \Exception('Too much files (>'.self::GOOGLE_IMAGES_LIMIT.') on location '.$this->getLocation());
        }
        $this->addFile($image);
    }

    /**
     * Get list of images
     * @return SitemapImage[]
     */
    public function getImages()
    {
        return $this->getFiles();
    }

}