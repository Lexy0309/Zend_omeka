<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DownloadManager\Api\Representation\DownloadRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class createDocumentKey extends AbstractPlugin
{
    /**
     * @var CreateMediaHash
     */
    protected $createMediaHash;

    /**
     * @param CreateMediaHash $createMediaHash
     */
    public function __construct(CreateMediaHash $createMediaHash)
    {
        $this->createMediaHash = $createMediaHash;
    }

    /**
     * Create a document key for a file, identified or not.
     *
     * @param MediaRepresentation $media
     * @param DownloadRepresentation $download
     * @return string
     */
    public function __invoke(MediaRepresentation $media, DownloadRepresentation $download = null)
    {
        $filename = $media->filename() . ($media->extension() ? '.' . $media->extension() : '');
        $fileKey = $media->storageId() . '=' . $filename;
        $hashPassword = $download ? $download->hashPassword() : $this->uniqueHash($media);
        $documentKey = hash('sha256', $media->sha256() . '/' . $hashPassword . '/' . sha1($fileKey));
        return $documentKey;
    }

    /**
     * Determine the unique hash for the media.
     *
     * @param MediaRepresentation $media
     * @return string
     */
    protected function uniqueHash(MediaRepresentation $media)
    {
        $createMediaHash = $this->createMediaHash;
        return $createMediaHash($media);
    }
}
