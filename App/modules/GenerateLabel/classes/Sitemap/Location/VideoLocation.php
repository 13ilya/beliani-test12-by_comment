<?php

namespace label\Sitemap\Location;

use label\Sitemap\Item\SitemapImage;
use label\Sitemap\Item\SitemapVideo;

/**
 * Class VideoLocation
 * Used to collect all videos related to some location
 */
class VideoLocation extends MediaLocation
{
    /**
     * Add video to videos list
     * @param SitemapVideo $video
     */
    public function addVideo(SitemapVideo $video)
    {
        $this->addFile($video);
    }

    /**
     * Get list of videos
     * @return SitemapVideo[]
     */
    public function getVideos()
    {
        return $this->getFiles();
    }

    /**
     * Update/set thumbnails to all videos
     * @param SitemapImage[] $images
     * @todo check images resolution
     */
    public function updateThumbnails($images)
    {
        $i = 0;
        foreach($this->files as $key => $video) {
            $this->files[$key]->thumbnail = $images[$i++]->url;
            if ($i === count($images)) {
                $i = 0;
            }
        }
    }
}
