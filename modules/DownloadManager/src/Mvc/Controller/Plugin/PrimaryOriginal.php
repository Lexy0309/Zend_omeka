<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Determine if an item has a primary media locally or externally.
 */
class PrimaryOriginal extends AbstractPlugin
{
    /**
     * Determine if an item has a primary media with a file, locally or externally.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param bool $stored
     * @return MediaRepresentation|null
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $withStoredFile = true)
    {
        /** @var MediaRepresentation $media */
        $media = $resource->primaryMedia();
        if (empty($media)) {
            return null;
        }

        if ($media->hasOriginal()) {
            return $media;
        }

        if ($withStoredFile) {
            return null;
        }

        // Check if the caller wants the media, even if the file is not stored.
        if ($media->ingester() === 'external' || $media->renderer() === 'ebsco') {
            return $media;
        }

        return null;
    }
}
