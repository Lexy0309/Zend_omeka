<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\GetCurrentDownload;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get the salt of a document downloaded by a user.
 */
class ReadDownloadSalt extends AbstractHelper
{
    /**
     * @var GetCurrentDownload
     */
    protected $getCurrentDownload;

    /**
     * @param GetCurrentDownload $getCurrentDownload
     */
    public function __construct(
        GetCurrentDownload $getCurrentDownload
    ) {
        $this->getCurrentDownload = $getCurrentDownload;
    }

    /**
     * Read the key for a document downloaded by a user.
     *
     * @param MediaRepresentation $media
     * @param User $user
     * @return string|null
     */
    public function __invoke(MediaRepresentation $media, User $user = null)
    {
        $getCurrentDownload = $this->getCurrentDownload;
        $download = $getCurrentDownload($media->item(), $user);
        if (empty($download)) {
            return;
        }
        return $download->salt();
    }
}
