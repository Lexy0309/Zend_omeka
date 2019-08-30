<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\CreateDocumentKey;
use DownloadManager\Mvc\Controller\Plugin\GetCurrentDownload;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get the key for a document downloaded by a user.
 */
class ReadDocumentKey extends AbstractHelper
{
    /**
     * @var GetCurrentDownload
     */
    protected $getCurrentDownload;

    /**
     * @var CreateDocumentKey
     */
    protected $createDocumentKey;

    /**
     * @param GetCurrentDownload $getCurrentDownload
     * @param CreateDocumentKey $createDocumentKey
     */
    public function __construct(
        GetCurrentDownload $getCurrentDownload,
        CreateDocumentKey $createDocumentKey
    ) {
        $this->getCurrentDownload = $getCurrentDownload;
        $this->createDocumentKey = $createDocumentKey;
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
        $createDocumentKey = $this->createDocumentKey;
        return $createDocumentKey($media, $download);
    }
}
