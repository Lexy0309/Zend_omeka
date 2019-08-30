<?php
namespace External\Mvc\Controller\Plugin;

use Omeka\File\Downloader;
use Omeka\File\Validator;
use Omeka\Stdlib\ErrorStore;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ImportThumbnail extends AbstractPlugin
{
    /**
     * @var Downloader
     */
    protected $downloader;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @param Downloader $downloader
     * @param Validator $validator
     */
    public function __construct(Downloader $downloader, Validator $validator)
    {
        $this->downloader = $downloader;
        $this->validator = $validator;
    }

    /**
     * Import an external thumbnail directly.
     *
     * @param string $thumbnailUrl
     * @param string $storageId
     * @param ErrorStore $errorStore
     * @return bool True if thumbnails are created.
     */
    public function __invoke($thumbnailUrl, $storageId, ErrorStore $errorStore = null)
    {
        $hasThumbnails = false;
        $tempThumbnailFile = $this->downloader->download($thumbnailUrl, $errorStore);
        if ($tempThumbnailFile) {
            $tempThumbnailFile->setStorageId($storageId);
            $path = parse_url($thumbnailUrl, PHP_URL_PATH);
            $tempThumbnailFile->setSourceName($path);
            if ($this->validator->validate($tempThumbnailFile, $errorStore)) {
                $hasThumbnails = $tempThumbnailFile->storeThumbnails();
            }
            $tempThumbnailFile->delete();
        }

        return $hasThumbnails;
    }
}

