<?php
namespace MediaQuality\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\Store\StoreInterface;
use Zend\View\Helper\AbstractHelper;

class MediaQualities extends AbstractHelper
{
    /**
     * @var array
     */
    protected $processors;

    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @param array $processors
     * @param StoreInterface $store
     * @param string $basePath
     */
    public function __construct(array $processors, StoreInterface $store, $basePath)
    {
        $this->processors = $processors;
        $this->store = $store;
        $this->basePath = $basePath;
    }

    /**
     * List available files of a media by quality, original included, with data.
     *
     * An empty array is returned when there is no derivative (only original),
     * except if the quality is set to "original" and the media is a file.
     *
     * @todo Save the media file sizes to avoid to reread it each time.
     *
     * @param AbstractResourceEntityRepresentation $resource Media or item
     * @param string|bool $quality The unique quality to get. If true, return
     * the original quality even if there is no other media qualities.
     * @return array|null Data about available files (media, quality, storage
     * path, url, file size, media type), ordered by media id and with media if
     * item. An empty array is returned if there is no derivative file. If a
     * quality is set, returns only the data for it.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $quality = null)
    {
        $resourceName = $resource->resourceName();
        switch ($resourceName) {
            case 'media':
                if ($quality === null) {
                    return $this->listMediaQualities($resource);
                }

                if ($quality === true) {
                    $result = $this->listMediaQualities($resource);
                    if ($result) {
                        return $result;
                    }
                    $result = $this->fetchMediaQuality($resource, 'original');
                    if (empty($result)) {
                        return;
                    }
                    return ['original' => $result];
                }

                return $this->fetchMediaQuality($resource, $quality);

            // Recursive call for item.
            case 'items':
                $result = [];
                foreach ($resource->media() as $media) {
                    $mediaQualities = $this->__invoke($media, $quality);
                    if ($mediaQualities) {
                        $result[$media->id()] = [
                            'media' => $media,
                            'qualities' => $mediaQualities,
                        ];
                    }
                }
                return $result;
        }
    }

    protected function fetchMediaQuality(MediaRepresentation $media, $quality)
    {
        $isManaged = $this->isManaged($media);
        if ($isManaged) {
            return $this->mediaQuality($media, $quality);
        }
        if (($quality === 'original' || $quality === true)
            && $media->hasOriginal()
            && $media->renderer() === 'file'
        ) {
            return $this->mediaQuality($media, 'original');
        }
    }

    protected function listMediaQualities(MediaRepresentation $media)
    {
        $isManaged = $this->isManaged($media);
        if (!$isManaged) {
            return;
        }

        $result = [];
        foreach ($this->processors[$media->mediaType()] as $quality => $mediaProcessor) {
            $data = $this->mediaQuality($media, $quality);
            if ($data) {
                $result[$quality] = $data;
            }
        }

        if (empty($result)) {
            return;
        }

        // Add original data.
        $data = $this->mediaQuality($media, 'original');
        $result = ['original' => $data] + $result;
        return $result;
    }

    protected function isManaged(MediaRepresentation $media)
    {
        if (!$media->hasOriginal()) {
            return false;
        }
        if ($media->renderer() !== 'file') {
            return false;
        }
        return !empty($this->processors[$media->mediaType()]);
    }

    protected function mediaQuality(MediaRepresentation $media, $quality)
    {
        if ($quality === 'original' || $quality === true) {
            $storagePath = 'original'
                . DIRECTORY_SEPARATOR . $media->filename();
            $mediaType = $media->mediaType();
        } else {
            $mediaProcessor = $this->processors[$media->mediaType()][$quality];
            $storagePath = $mediaProcessor['dir']
                . DIRECTORY_SEPARATOR . $media->storageId()
                . '.' . $mediaProcessor['extension'];
            $mediaType = $mediaProcessor['mediaType'];
        }

        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
        if (file_exists($filepath)) {
            return [
                'quality' => $quality,
                'mediaType' => $mediaType,
                'storagePath' => $storagePath,
                'url' => $this->store->getUri($storagePath),
                'filesize' => filesize($filepath),
            ];
        }
    }
}
